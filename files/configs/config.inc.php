<?php
return array (
  'domaindata' => 
  array (
    'domain' => 'CONFIG_DOMAIN',
  ),
  'email' => 
  array (
    'maxContactAddToUnknown' => 10,
    'maxMessageSize' => 52428800,
  ),
  'maxLoginFailures' => 50,
  'mailapplication' => 'Expressomail',
  'maxfiltertypeemail' => 4,
  'disableaccesslog' => true,
  'accesslog' => array(
	'disableactivesyncaccesslog' => true
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
    'filename' => '/tmp/expressov3.log',
  ),
  'caching' => 
  array (
    'active' => false,
    'customexpirable' => true,
    'lifetime' => 3600,
    'backend' => 'File',
    'dirlevel' => 1,
    'read_control' => false,
    'path' => '/tmp',
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
  'session' => 
  array (
    'lifetime' => 86400,
    'backend' => 'File',
    'path' => '/var/lib/php5',
    'host' => 'localhost',
    'port' => 6379,
    'storeAclIntoSession' => false,
    'storeSqlIntoSession' => false,
    'storePreferenceIntoSession' => false,
  ),
  'bugreportUrl' => '',
  'helpdoc' => 
  array (
    'active' => false,
    'text' => '',
    'title' => '',
    'url' => '',
  ),
  'theme' => 
  array (
    'active' => true,
    'load' => true,
    'path' => 'serpro',
    'useBlueAsBase' => true,
    'backgroundImageUrl' => 'bg.jpg',
    'messageImageUrl' => 'banner.png',
    'messageLinkUrl' => '',
    'accessibleLinkUrl' => '',
  ),
  'certificateLoginPath' => '',
  'global' => 
  array (
    'plugins' => 
    array (
      'mobile' => 
      array (
        'redirect' => false,
        'url' => '',
      ),
    ),
  ),
  'captcha' => 
  array (
    'count' => 3,
  ),
);
