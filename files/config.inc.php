<?php
return array (
  'domain' => 'JABBER_DOMAIN', 
  'maxLoginFailures' => 5,
  'mailapplication' => 'Expressomail',
  'maxfiltertypeemail' => 5,
  'maxfiltertypecalendar' => 5,
  'disableaccesslog' => true,
  'enabledApplications' => 'Tinebase,Admin,Addressbook,Calendar,Tasks,Webconference,Messenger,Expressomail,AppLauncher,ActiveSync',
  'modssl' => 
  array (
    'redirectUrlmodSsl' => 'http://10.0.2.15',
    'username_callback' => 'Custom_Auth_ModSsl_UsernameCallback_Cpf',
    'casfile' => '/opt/security/cas/todos.cer',
    'crlspath' => '/opt/security/crls',
  ),
  'maxMessageSize' => 52428800,
  'captcha' => 
  array (
    'count' => 3,
  ),
'Messenger' => 
  array (
    'messenger' => '{"domain":"JABBER_DOMAIN","resource":"qualquer coisa","format":"email","rtmfpServerUrl":"","tempFiles":"\\/tmp"}',
  ),


  'Webconference' =>  
   array ( 
    'roomStatus' => '{"name":"roomStatus","records":     [{"id":"A","value":"Active","system":true},{"id":"E","value":"Expired","system":true}]}',
    'wconfRoles' => '{"name":"wconfRoles","records":[{"id":"MODERATOR","value":"Moderator","system":true},{"id":"ATTENDEE","value":"Attendee","system":true}]}',
  ),

  'Tinebase_User_BackendType' => 'Ldap',
  'Tinebase_User_BackendConfiguration' => 
  array (
    'host' => '127.0.0.1',
    'username' => 'cn=ldap-admin,ZPMYDN',
    'password' => 'INPUT_PASSWORD',
    'userDn' => 'ou=usuarios,ZPMYDN',
    'groupsDn' => 'ou=grupos,ZPMYDN',
    'minUserId' => '1000',
    'maxUserId' => '99999',
    'minGroupId' => '900',
    'maxGroupId' => '999',
    'defaultUserGroupName' => 'grupo-user',
    'defaultAdminGroupName' => 'grupo-admin',
    'groupUUIDAttribute'=> 'gidNumber',
    'userUUIDAttribute'=> 'uidNumber',
    'readonly' => '0',
    'masterLdapHost' => '127.0.0.1',
    'masterLdapUsername' => 'cn=ldap-admin,ZPMYDN',
    'masterLdapPassword' => 'INPUT_PASSWORD',
    'mailListControl' => '1',
    'mailListDn' => 'ou=listas,ZPMYDN',
    'checkExpiredPassword' => '0',
    'passwordExpirationAttribute' => 'phpgwAccountExpires',
    'passwordExpirationInterval' => '60',
  ),
  'Tinebase_Authentication_BackendType' => 'Ldap',
  'sieve' => 
  array (
    'active' => 'true',
    'hostname' => '127.0.0.1',
    'port' => '4190',
    'ssl' => 'none',
  ),

  'Tinebase_Authentication_BackendConfiguration' => 
  array (
    'accountFilterFormat' => '(&(objectclass=posixaccount)(mail=%s))',
    'accountCanonicalForm' => '2',
    'tryUsernameSplit' => false,
    'bindRequiresDn' => '1',
    'host' => '127.0.0.1',
    'username' => 'cn=ldap-admin,ZPMYDN',
    'password' => 'INPUT_PASSWORD',
    'baseDn' => 'ZPMYDN',
  ),
  'database' => 
  array (
    'host' => '127.0.0.1',
    'dbname' => 'db_MYOU',
    'username' => 'user_MYOU',
    'password' => 'INPUT_PASSWORD',
    'adapter' => 'pdo_pgsql',
    'tableprefix' => 'tine20_',
    'port' => 5432,
    'profiler' => true,
  ),
  'profiler' => 
  array (
    'queryProfiles' => true,
    'queryProfilesDetails' => true,
  ),
  'redirecting' => 
  array (
    'active' => true,
    'ldapAttribute' => 'manager',
    'cookieName' => 'BALANCEID',
    'defaultCookieValue' => '.kristina',
  ),
  'sessionIpValidation' => 
  array (
    'active' => false,
    'source' => 'header',
    'header' => 'X-Forwarded-For',
  ),
  'setupuser' => 
  array (
    'username' => 'tine-admin',
    'password' => 'INPUT_PASSWORD',
  ),
  'logger' => 
  array (
    'active' => true,
    'priority' => 7,
    'filename' => '/tmp/expressov3/JABBER_DOMAIN/expressov3.log',
  ),
  'caching' => 
  array (
    'active' => false,
    'customexpirable' => true,
    'lifetime' => 3600,
    'backend' => 'File',
    'dirlevel' => 1,
    'read_control' => false,
    'path' => '/tmp/expressov3/JABBER_DOMAIN/tine20cache',
    'memcached' => 
    array (
      'host' => 'localhost',
      'port' => 11211,
    ),
    'redis' => 
    array (
      'host' => 'localhost',
      'port' => 6379,
    ),
  ),
  'actionqueue' => 
  array (
    'active' => false,
    'backend' => 'Redis',
    'host' => 'localhost',
    'port' => 6379,
  ),
  'session' => 
  array (
    'lifetime' => 86400,
    'backend' => 'File',
    'path' => '/var/lib/php5',
    'host' => 'localhost',
    'port' => 6379,
    'storeAclIntoSession' => true,
    'storeSqlIntoSession' => false,
    'storePreferenceIntoSession' => false,
  ),
  'tmpdir' => '/tmp/expressov3/tine20tmp',
  'helpUrl' => 'http://comunidadeexpresso.serpro.gov.br/expressov3/tutorial/html/index.html',
  'filesdir' => '/tmp/expressov3/tine20files',
  'mapPanel' => 0,
  'applauncher' => 
  array (
    'domain' => 'JABBER_DOMAIN',
    'applications' => 
    array (
      'listadmin' => 'https://URL_ADM_LISTAS(v2)',
    ),
  ),
  'denySurveys' => true,
  'themes' => 
  array (
    'default' => 0,
    'cookieTheme' => '',
    'themelist' => 
    array (
      0 => 
      array (
        'name' => 'Tine 2.0 Default Skin',
        'path' => 'tine20',
        'useBlueAsBase' => 1,
      ),
      1 => 
      array (
        'name' => 'Expresso 3.0',
        'path' => 'expresso30',
        'useBlueAsBase' => 1,
      ),
      2 => 
      array (
        'name' => 'Serpro',
        'path' => 'serpro',
        'useBlueAsBase' => 1,
      ),
    ),
  ),
  'plugins' => 
  array (
    'mobile' => 
    array (
      'url' => 'https://URL_MOBILE',
      'redirect' => false,
    ),
    'active' => true,
  ),
  'allowedJsonOrigins' => 
  array (
    0 => '10.0.2.15',
  ),
  'bugreportUrl' => '',
  'helpdoc' => 
  array (
    'active' => false,
    'text' => '',
    'title' => '',
    'url' => '',
  ),
  'email' => 
  array (
    'maxContactAddToUnknown' => 10,
  ),
  'stateprovider' => 
  array (
    'provider' => 'localStorage',
  ),
  'certificate' => 
  array (
    'active' => true,
    'useKeyEscrow' => true,
    'masterCertificate' => '/usr/share/ssl/certs/http/cas/mastercert.pem',
  ),
  'theme' => 
  array (
    'active' => true,
    'load' => true,
    'path' => 'serpro',
    'useBlueAsBase' => true,
    'backgroundImageUrl' => '',
    'messageImageUrl' => '',
    'messageLinkUrl' => '',
  ),
  'smtp' =>
  array (
   'active' => 'true',
   'backend' => 'standard',
   'hostname' => '127.0.0.1',
   'port' => '25',
   'ssl' => 'none',
   'auth' => 'none',
   'primarydomain' => 'JABBER_DOMAIN',
   ),
 'imap' =>
  array (
   'active' => 'true',
   'backend' => 'cyrus',
   'host' => '127.0.0.1',
   'port' => '143',
   'ssl' => 'none',
   'useSystemAccount' => '1',
   'domain' => '',
   'dbmail' => 'port:3306',
   'cyrus' =>
   array (
    'admin' => 'cyrus-admin',
    'password' => 'INPUT_PASSWORD',
    'useProxyAuth' => '0',
   ),
  ),

);
