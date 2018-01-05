<?php
/**
 * Tine 2.0
  * 
 * @package     Setup
 * @subpackage  Initialize
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Jonas Fischer <j.fischer@metaways.de>
 * @copyright   Copyright (c) 2008-2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * Class to handle application initialization
 * 
 * @package     Setup
 * @subpackage  Initialize
 */
class Setup_Initialize
{
    /**
     * Plugins for Setup
     * @var array
     */
    private static $_plugins = array();
    
    /**
     * array with user role rights, overwrite this in your app to add more rights to user role
     * 
     * @var array
     */
    protected $_userRoleRights = array(
        Tinebase_Acl_Rights::RUN
    );

    /**
     * Run customizations for the given classname
     * @param string $classname
     */
    private function _applyCustomizations($classname) {
        if (isset(self::$_plugins[$classname]) && class_exists(self::$_plugins[$classname])) {
            $plugin = self::$_plugins[$classname];
            if(!in_array('Setup_Initialize_Custom_Interface', class_implements($plugin))) {
                Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . " Plugin $plugin is not an implementation of Setup_Initialize_Custom_Interface");     
                return;
            }
            
            try {           
                call_user_func(array($plugin, 'applyCustomizations'));
            } catch (Exception $e) {
                Setup_Core::getLogger()->err(__METHOD__ . '::' . __LINE__ . ' Error when tries to run plugin for ' .  $classname . ': ' . $e->getMessage());     
            }
        }
    }
    
    /**
     * Call {@see _initialize} on an instance of the concrete Setup_Initialize class for the given {@param $_application}  
     * 
     * @param Tinebase_Model_Application $_application
     * @param array | optional $_options
     * @return void
     */
    public static function initialize(Tinebase_Model_Application $_application, $_options = null)
    {
        $applicationName = $_application->name;
        $classname = "{$applicationName}_Setup_Initialize";
        $instance = new $classname;
        
        Setup_Core::getLogger()->info(__METHOD__ . '::' . __LINE__ . ' Initializing application: ' . $applicationName);
        $instance->_initialize($_application, $_options);
        $instance->_applyCustomizations($classname);   
    }    
    
    /**
     * Call {@see _createInitialRights} on an instance of the concrete Setup_Initialize class for the given {@param $_application}  
     * 
     * @param Tinebase_Model_Application $_application
     * @param array | optional $_options
     * @return void
     */
    public static function initializeApplicationRights(Tinebase_Model_Application $_application, $_options = null)
    {
        $applicationName = $_application->name;
        $classname = "{$applicationName}_Setup_Initialize";
        $instance = new $classname;
        $instance->_createInitialRights($_application);
    }
    
    /**
     * initialize application
     *
     * @param Tinebase_Model_Application $_application
     * @param array | optional $_options
     * @return void
     */
    protected function _initialize(Tinebase_Model_Application $_application, $_options = null)
    {
        $this->_createInitialRights($_application);
        
        $reflectionClass = new ReflectionClass($this);
        $methods = $reflectionClass->getMethods();
        foreach ($methods as $method) {
            $methodName = $method->name;
            if (preg_match('/^_initialize.+/', $methodName)) {
                $this->$methodName($_application, $_options);
            }
        }
    }
    
    /**
     * create inital rights
     * 
     * @todo make hard coded role names ('user role' and 'admin role') configurable
     * 
     * @param Tinebase_Application $application
     * @return void
     */
    protected function _createInitialRights(Tinebase_Model_Application $_application)
    {
	// RCS
	// START
        //$roleRights = array(
        //    'user role'     => $this->_userRoleRights,
        //    'admin role'    => Tinebase_Application::getInstance()->getAllRights($_application->getId()),
        //);

        $roleRights = array(
        	'user role'     => $this->_userRoleRights,
        	'admin role'    => Tinebase_Application::getInstance()->getAllRights($_application->getId()),
		'manager role' => $this->_userRoleRights,
        );
	// END
        
        foreach ($roleRights as $roleName => $rights) {
            try {
                $role = Tinebase_Acl_Roles::getInstance()->getRoleByName($roleName);
            } catch(Tinebase_Exception_NotFound $tenf) {
                Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ . ' ' . $tenf->getMessage());
                continue;
            }
            
            foreach ($rights as $right) {
                try {
                    Tinebase_Acl_Roles::getInstance()->addSingleRight($role->getId(), $_application->getId(), $right);
                } catch(Exception $e) {
                    Tinebase_Core::getLogger()->warn(__METHOD__ . '::' . __LINE__ 
                        . ' Cannot add right: ' . $right . ' for application: ' . $_application->name
                        . ' - ' . $roleName . ' - ' . print_r($e->getMessage(), true)
                    );
                }
            }
        }
    }
    
    /**
     * Associates a plugin to a setup class
     * @param string plugin
     */
    public static function addPlugin($plugin) 
    {
        self::$_plugins[get_called_class()] = $plugin;
    }    
}
