#!/usr/bin/env php

<?php
/**
 * Tine 2.0 migration script
 * - This script move data from table config to file config.inc.php
 * - This is a necessary procedure for adopting of multidomain.
 *
 * @package     HelperScripts
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Flávio Gomes da Silva Lisboa <flavio.lisboa@serpro.gov.br>
 * @author      Guilherme Striquer Bisotto <guilherme.bisotto@serpro.gov.br>
 * @copyright   Copyright (c) 2014 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 *
 */

// for migrating from a folder different of tine20, append t=[folder name] to commmand line, for example, t=expressov3
define('APPNAME', getAppName($argv));

main();

/**
 * Start of migration process
 */
function main()
{

    prepareEnvironment();

    try {
        $opts = new Zend_Console_Getopt(array(
                'domain|d-s'=>'migrate data from table config to config file of specified domain',
                'global|g'=>'migrate data from table config to global config file',
                'help'=>'help option with no required parameter'
        )
        );
        $opts->parse();
    } catch (Zend_Console_Getopt_Exception $e) {
        echo $e->getUsageMessage();
        exit;
    }

    if($opts->getOption('help')) {
        die("ERROR: ".$opts->getUsageMessage()."\n");
    }

    $domain = $opts->getOption('d');
    if(empty($domain)) $domain = 'default';
    if($opts->getOption('g')) $domain = 'global';

    migrateConfigData($domain);
}

/**
 * Sets the include path and loads autoloader classes
 */
function prepareEnvironment()
{
    $paths = array(
            realpath(dirname(__FILE__) . '/../' . APPNAME),
            realpath(dirname(__FILE__) . '/../' . APPNAME . '/library'),
            get_include_path()
    );
    set_include_path(implode(PATH_SEPARATOR, $paths));

    require_once 'Zend/Loader/Autoloader.php';
    $autoloader = Zend_Loader_Autoloader::getInstance();
    $autoloader->setFallbackAutoloader(true);
    Tinebase_Autoloader::initialize($autoloader);
}


/**
 * write data from database to config file
 *
 * @param Zend_Config   $_config
 * @param string        $_configFile
 */
function writeConfigToFile($_config, $_configFile)
{
    echo "Saving file \"$_configFile\"...";
    if($_config instanceof Zend_Config)
    {
        try {
            $writer = new Zend_Config_Writer_Array(array(
                'config'   => $_config,
                'filename' => $_configFile,
            ));
            $writer->write();
        } catch (Exception $e) {
            die("ERROR: ".$e->getMessage()."\n");
        }
        echo "OK!\n";
    } else {
        echo "ERROR: Wrong data type\n";
    }
}

/**
 * Create a config object from database
 *
 * @param array $_dbData
 * @return Zend_Config
 */
function createConfigFromDb($_dbData)
{
    $config = new Zend_Config(array(), true);
    foreach($_dbData as $data) {
        if($data['application'] == 'Tinebase') {
            $attribute = $data['name'];
            $arrayData = Zend_Json::decode($data['value']);
        } else {
            $attribute = $data['application'];
            $arrayData = ($config->$attribute instanceof Zend_Config) ? $config->$attribute->toArray() : array();
            $arrayData[$data['name']] = $data['value'];
        }
        $config->$attribute = $arrayData;
    }
    return $config;
}

/**
 * Get connection database data
 *
 * @param Zend_Config $_dbConfig
 * @return array
 */
function getArrayDataFromDb($_dbConfig)
{
    $adapterConfig = array(
        'username'    => $_dbConfig->username,
        'password'    => $_dbConfig->password,
        'host'        => $_dbConfig->host,
        'dbname'      => $_dbConfig->dbname,
        'port'        => !empty($_dbConfig->port) ? $_dbConfig->port : 5432,
    );

    echo "Fetching data...";
    try {
        $db = @Zend_Db::factory($_dbConfig->adapter, $adapterConfig);
    } catch(Exception $e) {
        die("ERROR: ".$e->getMessage()."\n");
    }
    $adapterConfig['adapter'] = $_dbConfig->adapter;
    $adapterConfig['tableprefix'] = $tablePrefix = $_dbConfig->tableprefix;

    $select = $db->select()
        ->from($tablePrefix . 'config', array("name", "value"))
        ->join($tablePrefix . 'applications',
                $tablePrefix."applications.id = ".$tablePrefix."config.application_id",
                array('application' => 'name'))
        ->order($tablePrefix.'applications.name DESC');

    try {
        $result = $db->fetchAll($select);
    } catch (Exception $e) {
        die("ERROR: ".$e->getMessage()."\n");
    }
    echo "OK!\n";

    $result = array_merge(
        array(
            array(
                'application' => 'Tinebase',
                'name'        => 'database',
                'value'       => Zend_Json::encode($adapterConfig)
            )
        )
        , $result
    );

    return $result;
}

/**
 * Create template file for a domain
 *
 * @param string $_configFile
 */
function createTemplateFile($_configFile)
{
    echo "Creating template config file...";
    $templateConfig['database'] = array(
        'username'    => "DATABASE_USERNAME",
        'password'    => "DATABASE_PASSWORD",
        'host'        => "DATABASE_HOST",
        'dbname'      => "DATABASE_NAME",
        'adapter'     => "pdo_mysql",
        'tableprefix' => "tine20_"
    );

    $config = new Zend_Config($templateConfig, TRUE);
    echo "OK!\n";

    writeConfigToFile($config, $_configFile);
}

/**
 *
 * @param string $domain
 */
function migrateConfigData($domain)
{
    echo "Migrating \"$domain\" from Db...\n";

    if ($domain == 'global'){
        $configPath = realpath(__DIR__ . '/../' . APPNAME);
    } else {
        $configPath = realpath(__DIR__ . '/../' . APPNAME . '/domains') . "/$domain";
    }

    if(!file_exists($configPath)) {
        echo "WARNING: Directory $configPath doesn't exist\n";
        echo "Creating dir...";
        if(!@mkdir(realpath($configPath, 0755, TRUE))) {
            die("ERROR: Impossible to create directory\n");
        }
        echo "OK!\n";
    }

    $configFile = $configPath . '/config.inc.php';
    if(!file_exists($configFile)) {
        echo "ERROR: file ".$configFile." doesn't exist...\n";
        createTemplateFile($configFile);
        die("WARNING: Fill \"$configFile\" with correct database information.\n");
    }

    echo "Loading file \"$configFile\"...";
    if(file_exists($configFile)) {
        $config = new Zend_Config(require $configFile, TRUE);
    } else {
        die("ERROR: Config file: $configFile not found!\n");
    }
    echo "OK!\n";

    $dbData = getArrayDataFromDb($config->database);
    $config = createConfigFromDb($dbData);
    writeConfigToFile($config, $configFile);
}

/**
 * get installation folder name
 *
 * @param array $argv
 * @return string
 */
function getAppName(array $argv)
{
    $appName = 'tine20';
    foreach ($argv as $arg){
        if (substr($arg, 0, 2) == 't='){
            $appName = trim(substr($arg, 2));
            break;
        }
    }

    return $appName;
}
