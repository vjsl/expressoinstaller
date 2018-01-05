<?php
/**
 * Tine 2.0
 *
 * @package     Tinebase
 * @subpackage  Group
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @author      Guilherme Striquer Bisotto <guilherme.bisotto@serpro.gov.br>
 */

/**
 * Group ldap backend
 * 
 * @package     Tinebase
 * @subpackage  Group
 */
class Tinebase_Group_Ldap extends Tinebase_Group_Abstract
{
    /**
     * Constant for Group Membership cache id
     */
    const GROUPMEMBERSHIP = 'groups_membership';

    const PLUGIN_SAMBA = 'Tinebase_Group_LdapPlugin_Samba';
    
    /**
     * the ldap backend
     *
     * @var Tinebase_Ldap
     */
    protected $_ldap;

    /**
     * @var Tinebase_Ldap
     */
    protected $_masterLdap = NULL;
    
    /**
     * ldap config options
     *
     * @var array
     */
    protected $_options;
    
    /**
     * list of plugins 
     * 
     * @var array
     */
    protected $_ldapPlugins = array();
    
    /**
     * name of the ldap attribute which identifies a group uniquely
     * for example gidNumber, entryUUID, objectGUID
     * @var string
     */
    protected $_groupUUIDAttribute;
    
    /**
     * name of the ldap attribute which identifies a user uniquely
     * for example uidNumber, entryUUID, objectGUID
     * @var string
     */
    protected $_userUUIDAttribute;
    
    /**
     * the basic group ldap filter (for example the objectclass)
     *
     * @var string
     */
    protected $_groupBaseFilter      = 'objectclass=posixgroup';
    
    /**
     * the basic user ldap filter (for example the objectclass)
     *
     * @var string
     */
    protected $_userBaseFilter      = 'objectclass=posixaccount';
    
    /**
     * the basic user search scope
     *
     * @var integer
     */
    protected $_groupSearchScope     = Zend_Ldap::SEARCH_SCOPE_SUB;
    
    /**
     * the basic user search scope
     *
     * @var integer
     */
    protected $_userSearchScope      = Zend_Ldap::SEARCH_SCOPE_SUB;
    
    protected $_isReadOnlyBackend    = false;

    /**
    * list of required object classes for groups
    *
    * @var array
    */
    protected $_requiredObjectClass = array(
        'top',
        'posixGroup'
    );
    
    /**
     * the constructor
     *
     * @param  array $options Options used in connecting, binding, etc.
     */
    public function __construct(array $_options = array()) 
    {
        parent::__construct($_options);
        
        if(empty($_options['userUUIDAttribute'])) {
            $_options['userUUIDAttribute'] = 'entryUUID';
        }
        if(empty($_options['groupUUIDAttribute'])) {
            $_options['groupUUIDAttribute'] = 'entryUUID';
        }
        if(empty($_options['baseDn'])) {
            $_options['baseDn'] = $_options['userDn'];
        }
        if(empty($_options['userFilter'])) {
            $_options['userFilter'] = 'objectclass=posixaccount';
        }
        if(empty($_options['userSearchScope'])) {
            $_options['userSearchScope'] = Zend_Ldap::SEARCH_SCOPE_SUB;
        }
        if(empty($_options['groupFilter'])) {
            $_options['groupFilter'] = 'objectclass=posixgroup';
        }

        $this->_options = $_options;

        if (array_key_exists('readonly', $_options)) {
            $this->_isReadOnlyBackend = (bool)$_options['readonly'];
        }
        if ((isset($_options['ldap']) || array_key_exists('ldap', $_options))) {
            $this->_ldap = $_options['ldap'];
        }
        if (isset($_options['requiredObjectClass'])) {
            $this->_requiredObjectClass = (array)$_options['requiredObjectClass'];
        }

        if (isset($_options['groupSchemas'])) {
            $this->_getExtraLDAPschemas();
        }

        $this->_userUUIDAttribute  = strtolower($this->_options['userUUIDAttribute']);
        $this->_groupUUIDAttribute = strtolower($this->_options['groupUUIDAttribute']);
        $this->_baseDn             = $this->_options['baseDn'];
        $this->_userBaseFilter     = $this->_options['userFilter'];
        $this->_userSearchScope    = $this->_options['userSearchScope'];
        $this->_groupBaseFilter    = $this->_options['groupFilter'];
                
        try {
            $ldap = Tinebase_Core::getUserBackend();
            $this->_ldap       = empty($this->_ldap) ? $ldap['user'] : $this->_ldap;
            $this->_masterLdap = isset($ldap['master']) ? $ldap['master'] : NULL;
        } catch(Zend_Ldap_Exception $zle) {
            throw new Tinebase_Exception_Backend_Ldap('Could not bind to LDAP: ' . $zle->getMessage());
        }
        
        $this->_sql = new Tinebase_Group_Sql();
        
        foreach ($this->_plugins as $plugin) {
            if ($plugin instanceof Tinebase_Group_Plugin_LdapInterface) {
                $this->registerLdapPlugin($plugin);
            }
        }       
    }
    
    
    /**
     * register ldap plugin
     * 
     * @param Tinebase_Group_Plugin_LdapInterface $plugin
     */
    public function registerLdapPlugin(Tinebase_Group_Plugin_LdapInterface $plugin)
    {
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " Registering " . get_class($plugin) . ' LDAP plugin.');
        
        $plugin->setLdap($this->_ldap);
        $this->_ldapPlugins[] = $plugin;
    }
        
    /**
     * get group by id
     *
     * @param int $_groupId
     * @return Tinebase_Model_Group
     * @throws  Tinebase_Exception_Record_NotDefined
     */
    public function getGroupById($_groupId)
    {
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        
        $filter = Zend_Ldap_Filter::andFilter(
            Zend_Ldap_Filter::string($this->_groupBaseFilter),
            Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, Zend_Ldap::filterEscape($groupId))
        );
        
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " ldap filter: " . $filter);

        $ldapEntries = $this->_ldap->search(
            $filter, 
            $this->_options['groupsDn'], 
            $this->_groupSearchScope, 
            array('cn', 'description', $this->_groupUUIDAttribute)
        );

        if ($ldapEntries->count() == 0) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Group with id:$_groupId not found");
            throw new Tinebase_Exception_Record_NotDefined('Group not found.');
        }

        $group = $ldapEntries->getFirst();
        
        $result = new Tinebase_Model_Group(array(
            'id'            => $group[$this->_groupUUIDAttribute][0],
            'name'          => $group['cn'][0],
            'description'   => isset($group['description'][0]) ? $group['description'][0] : '' 
        ), TRUE);
        
        return $result;
    }
    
    /**
     * get group by name
     *
     * @param string $_groupName
     * @return Tinebase_Model_Group
     * @throws  Tinebase_Exception_Record_NotDefined
     */
    public function getGroupByName($_groupName)
    {
        $filter = Zend_Ldap_Filter::andFilter(
                Zend_Ldap_Filter::string($this->_groupBaseFilter),
                Zend_Ldap_Filter::equals('cn', Zend_Ldap_Filter_String::escapeValue($_groupName))
                );

        Tinebase_Core::getLogger()->trace(__METHOD__ . "::" . __LINE__ . ":: ldap filter: ".$filter->toString());

        $ldapEntries = $this->_ldap->search(
                $filter,
                $this->_options['groupsDn'],
                $this->_groupSearchScope,
                array('cn', 'description', $this->_groupUUIDAttribute)
                );

        if($ldapEntries->count() == 0) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Group with name:$_groupName not found");
            throw new Tinebase_Exception_Record_NotDefined('Group not found');
        }

        $group = $ldapEntries->getFirst();

        $result = new Tinebase_Model_Group(
                array(
                    'id' => $group[$this->_groupUUIDAttribute][0],
                    'name' => $group['cn'][0],
                    'description' => isset($group['description'][0]) ? $group['description'][0] : ''
        ), TRUE);

        return $result;
    }

    /**
     * Get multiple groups
     *
     * @param string|array $_ids Ids
     * @return Tinebase_Record_RecordSet
     */
    public function getMultiple($_ids)
    {
        if(!empty($_ids)) {
            $_ids = (is_array($_ids) ? $_ids : array($_ids));
            foreach($_ids as $id) {
                $equal = Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, $id);
                $orFilter = !isset($orFilter) ? Zend_Ldap_Filter::orFilter($equal) : $orFilter->addFilter($equal);
            }

            $filter = Zend_Ldap_Filter::andFilter(
                    Zend_Ldap_Filter::string($this->_groupBaseFilter), $orFilter
                    );
        }

        Tinebase_Core::getLogger()->trace(__METHOD__ . "::" . __LINE__ . ":: ldap filter: ". $filter->toString());

        $ldapEntries = $this->_ldap->search(
                $filter,
                $this->_options['groupsDn'],
                $this->_options['groupSearchScope'],
                array('cn', 'description', $this->_groupUUIDAttribute)
                );

        if($ldapEntries->count() == 0) {
            Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Groups not found");
            throw new Tinebase_Exception_Record_NotDefined('Group not found');
        }

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Group');
        foreach($ldapEntries as $ldapEntry) {
            $groupObject = new Tinebase_Model_Group(
                    array(
                        'id' => $ldapEntry[$this->_groupUUIDAttribute][0],
                        'name' => $ldapEntry['cn'][0],
                        'description' => isset($ldapEntry['description'][0]) ? $ldapEntry['description'][0] : ''
                ), TRUE);
            $result->addRecord($groupObject);
        }

        return $result;
    }

    /**
     * get syncable group by id from sync backend
     * @param  mixed  $_groupId  the groupid
     * @return Tinebase_Model_Group
     * @deprecated use Tinebase_Group_Abstract::getGroupById
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11235
     */
    public function getGroupByIdFromSyncBackend($_groupId)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::getGroupById");
        return $this->getGroupById($_groupId);
    }

    /**
     * get list of groups
     *
     * @param string $_filter
     * @param string $_sort
     * @param string $_dir
     * @param int $_start
     * @param int $_limit
     * @return Tinebase_Record_RecordSet with record class Tinebase_Model_Group
     */
    public function getGroups($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        $filter = Zend_Ldap_Filter::string($this->_groupBaseFilter);
        if(!empty($_filter)) {
            $filter = Zend_Ldap_Filter::andFilter(
                    $filter,
                    Zend_Ldap_Filter::contains('cn', $_filter));
        }

        Tinebase_Core::getLogger()->trace(__METHOD__ . "::" . __LINE__ . ":: ldap filter: " . $filter->toString());

        $ldapEntries = $this->_ldap->search(
                $filter,
                $this->_options['groupsDn'],
                $this->_groupSearchScope,
                array('cn', 'description', $this->_groupUUIDAttribute));

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Group');
        if ($ldapEntries->count() == 0) {
            return $result;
        }

        $groupNames = array();
        foreach ($ldapEntries as $ldapEntry) {
            if(in_array($ldapEntry['cn'][0], $groupNames)) {
                continue;
            }

            if(Tinebase_Helper::arrayKeyExists($ldapEntry[$this->_groupUUIDAttribute][0], $groupNames)){
                continue;
            }

            $groupObject = new Tinebase_Model_Group(array(
                'id'          => $ldapEntry[$this->_groupUUIDAttribute][0],
                'name'        => $ldapEntry['cn'][0],
                'description' => isset($ldapEntry['description'][0]) ? $ldapEntry['description'][0] : null
                    ), TRUE);
            $groupNames[$ldapEntry[$this->_groupUUIDAttribute][0]] = $ldapEntry['cn'][0];

            $result->addRecord($groupObject);
        }
        
        return $result;
    }
    
    /**
     * get list of groups from syncbackend
     * @param  string  $_filter
     * @param  string  $_sort
     * @param  string  $_dir
     * @param  int     $_start
     * @param  int     $_limit
     * @return Tinebase_Record_RecordSet with record class Tinebase_Model_Group
     * @deprecated use Tinebase_Group_Abstract::getGroups
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11236
     */
    public function getGroupsFromSyncBackend($_filter = NULL, $_sort = 'name', $_dir = 'ASC', $_start = NULL, $_limit = NULL)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::getGroups");
        return $this->getGroups($_filter, $_sort, $_dir, $_start, $_limit);
    }

    /**
     * replace all current groupmembers with the new groupmembers list
     *
     * @param int $_groupId
     * @param array $_groupMembers
     * @return array
     */
    public function setGroupMembers($_groupId, $_groupMembers)
    {
        if(!$this->_useLdapMaster($ldap)) {
            if ($this->_isReadOnlyBackend) {
                return;
            }
        }
        
        $metaData = $this->_getMetaData($_groupId);
        
        $membersMetaDatas = $this->_getAccountsMetaData((array)$_groupMembers, FALSE);
        if (count($_groupMembers) !== count($membersMetaDatas)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__
                . ' Removing ' . (count($_groupMembers) - count($membersMetaDatas)) . ' no longer existing group members from group ' . $_groupId);
            
            $_groupMembers = array();
            foreach ($membersMetaDatas as $account) {
                $_groupMembers[] = $account[$this->_userUUIDAttribute];
            }
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) 
            Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $group data: ' . print_r($metaData, true));
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) 
            Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $memebers: ' . print_r($membersMetaDatas, true));
        
        $groupDn = $this->_getDn($_groupId);
        
        $memberDn = array();
        $memberUid = array();
        
        foreach ($membersMetaDatas as $memberMetadata) {
            $memberDn[]  = $memberMetadata['dn'];
            $memberUid[] = $memberMetadata['uid'];
        }
        
        $ldapData = array(
            'memberuid' => $memberUid
        );
        
        if ($this->_options['useRfc2307bis']) {
            if (!empty($memberDn)) {
                $ldapData['member'] = $memberDn; // array of dns
            } else {
                $ldapData['member'] = $groupDn; // single dn
            }
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) 
            Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  $dn: ' . $metaData['dn']);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) 
            Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $ldapData: ' . print_r($ldapData, true));
        
        $ldap->update($metaData['dn'], $ldapData);
        
        return $_groupMembers;
    }
    
    /**
     * replace all current groupmembers with the new groupmembers list in sync backend
     * @param  string  $_groupId
     * @param  array   $_groupMembers array of ids
     * @return array with current group memberships (account ids)
     * @deprecated use Tinebase_Group_Abstract::setGroupMembers
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11237
     */
    public function setGroupMembersInSyncBackend($_groupId, $_groupMembers)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::setGroupMembers");
        return $this->setGroupMembers($_groupId, $_groupMembers);
    }

    /**
     * set all groups an account is member of
     *
     * @param  mixed  $_userId    the userid as string or Tinebase_Model_User
     * @param  mixed  $_groupIds
     * 
     * @return array
     */
    public function setGroupMemberships($_userId, $_groupIds)
    {
        if ($this->_isReadOnlyBackend) {
            return;
        }
        
        if ($_groupIds instanceof Tinebase_Record_RecordSet) {
            $_groupIds = $_groupIds->getArrayOfIds();
        }
        
        if(count($_groupIds) === 0) {
            throw new Tinebase_Exception_InvalidArgument('user must belong to at least one group');
        }
        
        $userId = Tinebase_Model_user::convertUserIdToInt($_userId);
        
        $groupMemberships = $this->getGroupMemberships($userId);
        
        $removeGroupMemberships = array_diff($groupMemberships, $_groupIds);
        $addGroupMemberships    = array_diff($_groupIds, $groupMemberships);
        
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' current groupmemberships: ' . print_r($groupMemberships, true));
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' new groupmemberships: ' . print_r($_groupIds, true));
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' added groupmemberships: ' . print_r($addGroupMemberships, true));
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . ' removed groupmemberships: ' . print_r($removeGroupMemberships, true));
        
        foreach ($addGroupMemberships as $groupId) {
            $this->addGroupMember($groupId, $userId);
        }
        
        foreach ($removeGroupMemberships as $groupId) {
            $this->removeGroupMember($groupId, $userId);
        }

        return $this->getGroupMemberships($userId);
    }

    /**
     * replace all current groupmemberships of user in sync backend
     * @param  mixed  $_userId
     * @param  mixed  $_groupIds
     * @return array
     * @deprecated use Tinebase_Group_Abstract::setGroupMemberships
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11238
     */
    public function setGroupMembershipsInSyncBackend($_userId, $_groupIds)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::setGroupMemberships");
        return $this->setGroupMemberships($_userId, $_groupIds);
    }

    /**
     * add a new groupmember to the group
     *
     * @param int $_groupId
     * @param int $_accountId
     */
    public function addGroupMember($_groupId, $_accountId)
    {
        if(!$this->_useLdapMaster($ldap)) {
            if ($this->_isReadOnlyBackend) {
                return;
            }
        }
        
        $userId  = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        
        $memberships = $this->getGroupMembershipsFromSyncBackend($_accountId);
        if (in_array($groupId, $memberships)) {
             if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . " skip adding group member, as $userId is already in group $groupId");
             return;
        }
        
        $groupDn = $this->_getDn($_groupId);
        $ldapData = array();
        
        $accountMetaData = $this->_getAccountMetaData($_accountId);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " account meta data: " . print_r($accountMetaData, true));
        
        $filter = Zend_Ldap_Filter::andFilter(
            Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, Zend_Ldap::filterEscape($groupId)),
            Zend_Ldap_Filter::equals('memberuid', Zend_Ldap::filterEscape($accountMetaData['uid']))
        );
        
        $groups = $ldap->search(
            $filter, 
            $this->_options['groupsDn'], 
            $this->_groupSearchScope, 
            array('dn')
        );

        if (count($groups) == 0) {
            // need to add memberuid
            //$ldapData['memberuid'] = $accountMetaData['uid'];
        }
        
        
        if ($this->_options['useRfc2307bis']) {
            $filter = Zend_Ldap_Filter::andFilter(
                Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, Zend_Ldap::filterEscape($groupId)),
                Zend_Ldap_Filter::equals('member', Zend_Ldap::filterEscape($accountMetaData['dn']))
            );
            
            $groups = $ldap->search(
                $filter, 
                $this->_options['groupsDn'], 
                $this->_groupSearchScope, 
                array('dn')
            );
            
            if (count($groups) == 0) {
                // need to add member
                //$ldapData['member'] = $accountMetaData['dn'];
            }
        }
        try {
            if (!empty($ldapData)) {
                $ldap->addProperty($groupDn, $ldapData);
            }
        } catch (Exception $exc) {
            if($accountMetaData['gidNumber'] == $_groupId){
                Tinebase_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . " Error adding user $userId in group $groupId");
            }else{
                throw $exc;
            }
        }
        if ($this->_options['useRfc2307bis']) {
            // remove groupdn if no longer needed
            $filter = Zend_Ldap_Filter::andFilter(
                Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, Zend_Ldap::filterEscape($groupId)),
                Zend_Ldap_Filter::equals('member', Zend_Ldap::filterEscape($groupDn))
            );
            
            $groups = $ldap->search(
                $filter, 
                $this->_options['groupsDn'], 
                $this->_groupSearchScope, 
                array('dn')
            );
            
            if (count($groups) > 0) {
                $ldapData = array (
                    'member' => $groupDn
                );
                $ldap->deleteProperty($groupDn, $ldapData);
            }
        }
    }

    /**
     * add a new groupmember to group in sync backend
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId string or user object
     * @deprecated use Tinebase_Group_Abstract::addGroupMember
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11233
     */
    public function addGroupMemberInSyncBackend($_groupId, $_accountId)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::addGroupMember");
        return $this->addGroupMember($_groupId, $_accountId);
    }

    /**
     * remove one groupmember from the group
     *
     * @param int $_groupId
     * @param int $_accountId
     * @return unknown
     */
    public function removeGroupMember($_groupId, $_accountId)
    {
        if(!$this->_useLdapMaster($ldap)) {
            if ($this->_isReadOnlyBackend) {
                return;
            }
        }
        
        $userId  = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        
        $memberships = $this->getGroupMemberships($_accountId);
        if (!in_array($groupId, $memberships)) {
             if (Tinebase_Core::isLogLevel(Zend_Log::INFO)) Tinebase_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . " skip removing group member, as $userId is not in group $groupId " . print_r($memberships, true));
             return;
        }
        
        try {
            $groupDn = $this->_getDn($_groupId);
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::CRIT)) Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . 
                " Failed to remove groupmember $_accountId from group $_groupId: " . $tenf->getMessage()
            );
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $tenf->getTraceAsString());
            return;
        }
        
        try {
            $accountMetaData = $this->_getAccountMetaData($_accountId);
        } catch (Tinebase_Exception_NotFound $tenf) {
            if (Tinebase_Core::isLogLevel(Zend_Log::CRIT)) Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . ' user not found in sync backend: ' . $_accountId);
            return;
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . " account meta data: " . print_r($accountMetaData, true));
        
        $memberUidNumbers = $this->getGroupMembers($_groupId);
        
        $ldapData = array(
            'memberuid' => $accountMetaData['uid']
        );
        
        if (isset($this->_options['useRfc2307bis']) && $this->_options['useRfc2307bis']) {
            
            if (count($memberUidNumbers) === 1) {
                // we need to add the group dn, as the member attribute is not allowed to be empty
                $dataAdd = array(
                    'member' => $groupDn
                );
                $ldap->insertProperty($groupDn, $dataAdd);
            } else {
                $ldapData['member'] = $accountMetaData['dn'];
            }
        }
            
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  $dn: ' . $groupDn);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $ldapData: ' . print_r($ldapData, true));
        
        try {
            $ldap->deleteProperty($groupDn, $ldapData);
        } catch (Zend_Ldap_Exception $zle) {
            if (Tinebase_Core::isLogLevel(Zend_Log::CRIT)) Tinebase_Core::getLogger()->crit(__METHOD__ . '::' . __LINE__ . 
                " Failed to remove groupmember {$accountMetaData['dn']} from group $groupDn: " . $zle->getMessage()
            );
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . ' ' . $zle->getTraceAsString());
        }
    }
        
    
    /**
     * remove one member from the group in sync backend
     * @param  mixed  $_groupId
     * @param  mixed  $_accountId
     * @deprecated use Tinebase_Group_Abstract::removeGroupMember
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11241
     */
    public function removeGroupMemberInSyncBackend($_groupId, $_accountId)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::removeGroupMember");
        return $this->removeGroupMember($_groupId, $_accountId);
    }

    /**
     * create a new group
     *
     * @param string $_groupName
     * @return unknown
     */
    public function addGroup(Tinebase_Model_Group $_group)
    {
        if(!$this->_useLdapMaster($ldap)) {
            if ($this->_isReadOnlyBackend) {
                return NULL;
            }
        }
        
        $dn = $this->_generateDn($_group);

        $gidNumber = $this->_generateGidNumber();
        $ldapData = array(
            'objectclass' => $this->_requiredObjectClass,
            'gidnumber'   => $gidNumber,
            'cn'          => $_group->name,
            'description' => $_group->description,
        );

        $ldapData = $this->_getExtraLDAPAttributes($ldapData);
        
        if (isset($this->_options['useRfc2307bis']) && $this->_options['useRfc2307bis'] == true) {
            $ldapData['objectclass'][] = 'groupOfNames';
            // the member attribute can not be emtpy, seems to be common praxis 
            // to set the member attribute to the group dn itself for empty groups
            $ldapData['member']        = $dn;
        }
        
        foreach ($this->_ldapPlugins as $plugin) {
            $plugin->inspectAddGroup($_group, $ldapData);
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  $dn: ' . $dn);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $ldapData: ' . print_r($ldapData, true));
        $ldap->add($dn, $ldapData);

        $groupId = $ldap->getEntry($dn, array($this->_groupUUIDAttribute));
        
        $groupId = $groupId[$this->_groupUUIDAttribute][0];
        
        $group = $this->getGroupByIdFromSyncBackend($groupId);
                
        return $group;
    }
    
    /**
     * create a new group in sync backend
     * @param  Tinebase_Model_Group  $_group
     * @return Tinebase_Model_Group|NULL
     * @deprecated use Tinebase_Group_Abstract::addGroup
     * @deprecated in Task #11188
     * @deprecated will be removed in Task #11232
     */
    public function addGroupInSyncBackend(Tinebase_Model_Group $_group)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::addGroup");
        return $this->addGroup($_group);
    }

    /**
     * updates an existing group
     *
     * @param Tinebase_Model_Group $_account
     * @return Tinebase_Model_Group
     */
    public function updateGroup(Tinebase_Model_Group $_group)
    {
        if(!$this->_useLdapMaster($ldap)) {
            if ($this->_isReadOnlyBackend) {
                return;
            }
        }
        
        $metaData = $this->_getMetaData($_group->getId());
        $dn = $metaData['dn'];

        $objectClasses = isset($metaData['objectclass']) ? $metaData['objectclass'] : array();
        // check if user has all required object classes. This is needed
        // when updating users which where created using different requirements
        foreach ($this->_requiredObjectClass as $className) {
            if (!in_array(strtolower($className), array_map('strtolower', $metaData['objectclass']))) {
                // merge all required classes at once
                $arrayMerge = array_merge($metaData['objectclass'],$this->_requiredObjectClass);
                $objectClasses = array_intersect_key($arrayMerge, array_unique(array_map('strtolower', $arrayMerge)));
               break;
            }
        }

        $ldapData = array(
            'cn'          => $_group->name,
            'description' => $_group->description,
            'objectclass' => $objectClasses
        );

        $ldapData = $this->_getExtraLDAPAttributes($ldapData);
        
        foreach ($this->_ldapPlugins as $plugin) {
            $plugin->inspectUpdateGroup($_group, $ldapData);
        }
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  $dn: ' . $dn);
        if (Tinebase_Core::isLogLevel(Zend_Log::TRACE)) Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ . '  $ldapData: ' . print_r($ldapData, true));
        $ldap->update($dn, $ldapData);

        if (isset($metaData['cn'])) {
            $cn = is_array($metaData['cn']) ? $metaData['cn'][0] : $metaData['cn'];
            if ($cn != $ldapData['cn']) {
                $newDn = "cn={$ldapData['cn']},{$this->_options['groupsDn']}";
                $this->_ldap->rename($dn, $newDn);
            }
        }
        
        $group = $this->getGroupByIdFromSyncBackend($_group);

        return $group;
    }
    
    /**
     * updates an existing group in sync backend
     * @param  Tinebase_Model_Group  $_group
     * @return Tinebase_Model_Group
     * @deprecated use Tinebase_Group_Abstract::updateGroup
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11242
     */
    public function updateGroupInSyncBackend(Tinebase_Model_Group $_group)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: Tinebase_Group_Abstract::updateGroup");
        return $this->updateGroup($_group);
    }

    /**
     * remove groups
     *
     * @param mixed $_groupId
     *
     */
    public function deleteGroups($_groupId)
    {
        if(!$this->_useLdapMaster($ldap)) {
            if ($this->_isReadOnlyBackend) {
                return;
            }
        }
        
        $groupIds = array();
        
        if (is_array($_groupId) or $_groupId instanceof Tinebase_Record_RecordSet) {
            foreach ($_groupId as $groupId) {
                $groupIds[] = Tinebase_Model_Group::convertGroupIdToInt($groupId);
            }
        } else {
            $groupIds[] = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        }

        $this->_updatePrimaryGroupsOfUsers($groupIds);

        foreach ($groupIds as $groupId) {
            $dn = $this->_getDn($groupId);
            $ldap->delete($dn);
        }
    }

    /**
     * delete one or more groups in sync backend
     * @param  mixed   $_groupId
     * @deprecated use Tinebase_Group_Abstract::deleteGroups
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11234
     */
    public function deleteGroupsInSyncBackend($_groupId)
    {
        Tinebase_Core::getLogger()->WARN(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::deleteGroups");
        $this->deleteGroups($_groupId);
    }
    
    /**
     * get dn of an existing group
     *
     * @param  string $_groupId
     * @return string 
     */
    protected function _getDn($_groupId)
    {
        $metaData = $this->_getMetaData($_groupId);
        
        return $metaData['dn'];
    }
    
    /**
     * returns ldap metadata of given group
     *
     * @param  string $_groupId
     * @return array
     * @throws Tinebase_Exception_NotFound
     * 
     * @todo remove obsolete code
     */
    protected function _getMetaData($_groupId)
    {
        $groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        
        $filter = Zend_Ldap_Filter::andFilter(
            Zend_Ldap_Filter::string($this->_groupBaseFilter),
            Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, Zend_Ldap::filterEscape($groupId))
        );

        $result = $this->_ldap->search(
            $filter, 
            $this->_options['groupsDn'], 
            $this->_groupSearchScope, 
            array('objectclass', 'cn')
        );
        
        if (count($result) !== 1) {
            throw new Tinebase_Exception_NotFound("Group with id $_groupId not found.");
        }
        
        return $result->getFirst();
    }
    
    /**
     * get metatada of existing user
     *
     * @param  string  $_userId
     * @return array
     */
    protected function _getUserMetaData($_userId)
    {
        $userId = Tinebase_Model_User::convertUserIdToInt($_userId);

        $filter = Zend_Ldap_Filter::equals(
            $this->_userUUIDAttribute, Zend_Ldap::filterEscape($userId)
        );

        $result = $this->_ldap->search(
            $filter,
            $this->_baseDn,
            $this->_userSearchScope
        );

        if (count($result) !== 1) {
            throw new Tinebase_Exception_NotFound("user with userid $_userId not found");
        }

        return $result->getFirst();
    }
    
    /**
     * returns arrays of metainfo from given accountIds
     *
     * @param array $_accountIds
     * @param boolean $throwExceptionOnMissingAccounts
     * @return array of strings
     */
    protected function _getAccountsMetaData(array $_accountIds, $throwExceptionOnMissingAccounts = TRUE)
    {
        $filterArray = array();
        foreach ($_accountIds as $accountId) {
            $accountId = Tinebase_Model_User::convertUserIdToInt($accountId);
            $filterArray[] = Zend_Ldap_Filter::equals($this->_userUUIDAttribute, Zend_Ldap::filterEscape($accountId));
        }
        $filter = new Zend_Ldap_Filter_Or($filterArray);
        
        if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->debug(__METHOD__ . '::' . __LINE__ . '  $filter: ' . $filter . ' count: ' . count($filterArray));
        
        // fetch all dns at once
        $accounts = $this->_ldap->search(
            $filter, 
            $this->_options['userDn'], 
            $this->_userSearchScope, 
            array('uid', $this->_userUUIDAttribute, 'objectclass', 'gidnumber')
        );
        
        if (count($_accountIds) != count($accounts)) {
            $wantedAccountIds    = array();
            $retrievedAccountIds = array();
            
            foreach ($_accountIds as $accountId) {
                $wantedAccountIds[] = Tinebase_Model_User::convertUserIdToInt($accountId);
            }
            foreach ($accounts as $account) {
                $retrievedAccountIds[] = $account[$this->_userUUIDAttribute][0];
            }
            
            $message = "Some dn's are missing. "  . print_r(array_diff($wantedAccountIds, $retrievedAccountIds), true);
            if ($throwExceptionOnMissingAccounts) {
                throw new Tinebase_Exception_NotFound($message);
            } else {
                if (Tinebase_Core::isLogLevel(Zend_Log::WARN)) Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $message);
            }
        }
        
        $result = array();
        foreach ($accounts as $account) {
            $result[] = array(
                'dn'                        => $account['dn'],
                'objectclass'               => $account['objectclass'],
                'uid'                       => $account['uid'][0],
                'gidNumber'                 => $account['gidnumber'][0],
                $this->_userUUIDAttribute   => $account[$this->_userUUIDAttribute][0]
            );
        }

        return $result;
    }
    
    /**
     * returns a single account dn
     *
     * @param string $_accountId
     * @return string
     */
    protected function _getAccountMetaData($_accountId)
    {
        return Tinebase_Helper::array_value(0, $this->_getAccountsMetaData(array($_accountId)));
    }
    
    /**
     * generates a new dn for a group
     *
     * @param  Tinebase_Model_Group $_group
     * @return string
     */
    protected function _generateDn(Tinebase_Model_Group $_group)
    {

        if(isset($this->_options['groupOU']) && $this->_options['groupOU'] != ""){
            $newDn = "cn={$_group->name},{$this->_options['groupOU']},{$this->_options['groupsDn']}";
        }else{
            $newDn = "cn={$_group->name},{$this->_options['groupsDn']}";
        }
        return $newDn;
    }
    
    /**
     * generates a gidnumber
     *
     * @todo add a persistent registry which id has been generated lastly to
     *       reduce amount of groupid to be transfered
     * 
     * @return int
     */
    protected function _generateGidNumber()
    {
        return (int)time();
    }
    
    /**
     * resolve groupid(for example ldap gidnumber) to uuid(for example ldap entryuuid)
     *
     * @param   string  $_groupId
     * @return  string  the uuid for groupid
     */
    public function resolveSyncAbleGidToUUid($_groupId)
    {
        return $this->resolveGIdNumberToUUId($_groupId);
    }
    
    /**
     * resolve gidnumber to UUID(for example entryUUID) attribute
     * 
     * @param int $_gidNumber the gidnumber
     * @return string 
     */
    public function resolveGIdNumberToUUId($_gidNumber)
    {
        if ($this->_groupUUIDAttribute == 'gidnumber') {
            return $_gidNumber;
        }
        
        $filter = Zend_Ldap_Filter::andFilter(
            Zend_Ldap_Filter::string($this->_groupBaseFilter),
            Zend_Ldap_Filter::equals('gidnumber', Zend_Ldap::filterEscape($_gidNumber))
        );
        
        $groupId = $this->_ldap->search(
            $filter, 
            $this->_options['groupsDn'], 
            $this->_groupSearchScope, 
            array($this->_groupUUIDAttribute)
        )->getFirst();
        
        if ($groupId == null) {
            throw new Tinebase_Exception_NotFound('LDAP group with (gidnumber=' . $_gidNumber . ') not found');
        }
        
        return $groupId[$this->_groupUUIDAttribute][0];
    }
    
    /**
     * resolve UUID(for example entryUUID) to gidnumber
     * 
     * @param string $_uuid
     * @return string
     */
    public function resolveUUIdToGIdNumber($_uuid)
    {
        if ($this->_groupUUIDAttribute == 'gidnumber') {
            return $_uuid;
        }
        
        $filter = Zend_Ldap_Filter::andFilter(
            Zend_Ldap_Filter::string($this->_groupBaseFilter),
            Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, Zend_Ldap::filterEscape($_uuid))
        );
        
        $groupId = $this->_ldap->search(
            $filter, 
            $this->_options['groupsDn'], 
            $this->_groupSearchScope, 
            array('gidnumber')
        )->getFirst();
        
        return $groupId['gidnumber'][0];
    }
    
    /**
     * get list of groupmembers
     *
     * @param int $_groupId
     * @return array(partial => boolean, members -> array())
     */
    public function getGroupMembers($_groupId)
    {
        $_groupId = Tinebase_Model_Group::convertGroupIdToInt($_groupId);
        $groupFilter = Zend_Ldap_Filter::andFilter(
                Zend_Ldap_Filter::string($this->_groupBaseFilter),
                Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, $_groupId)
                );
        $defaultGroupFilter = Zend_Ldap_Filter::andFilter(
                Zend_Ldap_Filter::string($this->_userBaseFilter),
                Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, $_groupId)
                );

        $filter = Zend_Ldap_Filter::orFilter($groupFilter, $defaultGroupFilter);

        Tinebase_Core::getLogger()->debug(__METHOD__ . "::" . __LINE__ . ":: ldap search filter: ".$filter->toString());

        $ldapEntries = $this->_ldap->search(
                $filter,
                $this->_options['groupsDn'],
                $this->_groupSearchScope,
                array('memberuid', $this->_userUUIDAttribute));

        Tinebase_Core::getLogger()->debug(__METHOD__ . "::" . __LINE__ . ":: ldap result count: ".$ldapEntries->count());

        if($ldapEntries->count() == 0) {
            return array();
        } else {
            foreach($ldapEntries as $ldapEntry) {
                if(!isset($ldapEntry['memberuid']) || $ldapEntry['memberuid'] == null) {
                    if(isset($ldapEntry[$this->_userUUIDAttribute]) && $ldapEntry[$this->_userUUIDAttribute] != null) {
                        $entryUid = $ldapEntry[$this->_userUUIDAttribute][0];
                        $members[$entryUid] = $entryUid;
                        continue;
                    }
                }

                $chunkedGroupMetadada = array_chunk($ldapEntry['memberuid'], 50);

                do {
                    $arrayFilter = array();
                    $portion = array_shift($chunkedGroupMetadada);
                    foreach($portion as $memberUid) {
                        $arrayFilter[] = Zend_Ldap_Filter::equals('uid', $memberUid);
                    }

                    if(empty($chunkedGroupMetadada)) {
                        $arrayFilter[] = Zend_Ldap_Filter::equals($this->_groupUUIDAttribute, $_groupId);
                    }

                    $filter = Zend_Ldap_Filter::andFilter(
                            Zend_Ldap_Filter::string($this->_userBaseFilter),
                            new Zend_Ldap_Filter_Or($arrayFilter)
                            );

                    $groupEntries = $this->_ldap->search(
                            $filter,
                            $this->_options['userDn'],
                            $this->_options['userSearchScope'],
                            array($this->_userUUIDAttribute));

                    foreach($groupEntries as $groupEntry) {
                        $entryUid = $groupEntry[$this->_userUUIDAttribute][0];
                        $members[$entryUid] = $entryUid;
                    }

                } while(!empty($chunkedGroupMetadada));
            }
        }

        return $members;
    }

    /**
     * return all groups an account is member of
     *
     * @param mixed $_accountId the account as integer or Tinebase_Model_User
     * @return array
     */
    public function getGroupMemberships($_accountId)
    {
        $cache = Tinebase_Core::getCache();
        $cacheKey = Tinebase_Helper::arrayToCacheId(array(self::GROUPMEMBERSHIP,$_accountId));
        $groups = $cache->load($cacheKey);
        if ($groups) {
            return $groups;
        }
        $metaData = $this->_getUserMetaData($_accountId);
        $filter = Zend_Ldap_Filter::andFilter(
            Zend_Ldap_Filter::string($this->_groupBaseFilter),
            Zend_Ldap_Filter::orFilter(
                Zend_Ldap_Filter::equals('memberuid', Zend_Ldap::filterEscape($metaData['uid'][0])),
                Zend_Ldap_Filter::equals('member',    Zend_Ldap::filterEscape($metaData['dn']))
            )
        );
        
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .' ldap search filter: ' . $filter->toString());
        
        $groups = $this->_ldap->search(
            $filter, 
            $this->_options['groupsDn'], 
            $this->_groupSearchScope, 
            array('cn', 'description', $this->_groupUUIDAttribute)
        );
        
        $memberships = array();
        $memberships[] = $metaData['gidnumber'][0];
        
        foreach ($groups as $group) {
            $memberships[] = $group[$this->_groupUUIDAttribute][0];
        }
        
        Tinebase_Core::getLogger()->trace(__METHOD__ . '::' . __LINE__ .' group memberships: ' . print_r($memberships, TRUE));
        $cache->save($memberships, $cacheKey);
        return $memberships;
    }

    /**
     * get groupmemberships of user from sync backend
     * @param   Tinebase_Model_User|string  $_userId
     * @return  array  list of group ids
     * @deprecated use Tinebase_Group_Abstract::getGroupMemberships
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11243
     */
    public function getGroupMembershipsFromSyncBackend($_userId)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Abstract::getGroupMemberships");
        return $this->getGroupMemberships($_userId);
    }
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Group/Interface/Syncable::mergeMissingProperties
     */
    public static function mergeMissingProperties($syncGroup, $sqlGroup)
    {
        // @TODO see ldap schema, email might be an attribute
        foreach (array('list_id', 'email', 'visibility') as $property) {
            $syncGroup->{$property} = $sqlGroup->{$property};
        }
    }

    /**
     * Loads ldap object and returns a boolean telling if it can write
     * @param mixed $_ldap
     */
    protected function _useLdapMaster(&$_ldap)
    {
        $_ldap = $this->_ldap;
        if ($this->_masterLdap != null) {
            $_ldap = $this->_masterLdap;
            return true;
        }
        return false;
    }

    /**
    * Get extra LDAP schemas from config and add it to $this->_requiredObjectClass
    *
    * @return void
    */
    protected function _getExtraLDAPschemas()
    {
        if(Tinebase_User::getBackendConfiguration('groupSchemas') != ''){
            $extraSchemas = explode(';', Tinebase_User::getBackendConfiguration('groupSchemas'));
            $extraSchemas = array_diff($extraSchemas, $this->_requiredObjectClass);
            $extraSchemas = array_map('strtolower', array_merge($extraSchemas, $this->_requiredObjectClass));
            $this->_requiredObjectClass = $extraSchemas;
        }
    }

     /**
     * Gets the ExtraLdapAttributes from Ldap Config and add it to the $ldapData array
     *
     * @param array $_LdapData
     * @return array
     */
    protected function _getExtraLDAPAttributes(array $_ldapData)
    {
        if(Tinebase_User::getBackendConfiguration('groupAttributes') != ''){
            $extraAttributes = explode(';', Tinebase_User::getBackendConfiguration('groupAttributes'));
            foreach ($extraAttributes as $attribute){
                $tmpArray = explode('=', $attribute);
                if(!array_key_exists($tmpArray[0],$_ldapData)){
                    $_ldapData[$tmpArray[0]] = $tmpArray[1];
                }
            }
        }
        return $_ldapData;
    }

    /**
     * set all groups an user is member of
     * @param  mixed  $_userId   the account as integer or Tinebase_Model_User
     * @param  mixed  $_groupIds
     * @return array
     * @deprecated use Tinebase_Group_Sql::setGroupMemberships
     * @deprecated in Task #11184
     * @deprecated will be removed in Task #11244
     */
    public function setGroupMembershipsInSqlBackend($_userId, $_groupIds)
    {
        Tinebase_Core::getLogger()->warn(__METHOD__ . "::" . __LINE__ . ":: Deprecated method: use Tinebase_Group_Sql::setGroupMemberships");
        return $this->getGroupMemberships($_userId);
    }
}
