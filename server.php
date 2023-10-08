<?php

require 'vendor/autoload.php';

require 'config.php';
require 'Helios.php';
require 'AuthBackend.php';
require 'CardBackend.php';
require 'PrincipalBackend.php';

use Sabre\DAV\Server;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!IsSet($GLOBALS['DBCONFIG_PHP_INCLUDED']))
{
    include('PDO.php');
    $GLOBALS['DBCONFIG_PHP_INCLUDED'] = 1;

    global $db;
    $db = new DB();
    try
    {
        $db->Connect();
    }
    catch (Exception $exception) {}
}

$authBackend = new HeliosAuthBackend(); // Backed for Helios authentication
$principalBackend = new HeliosPrincipalBackend();
$carddavBackend = new HeliosCardDAVBackend();

$nodes = [
    new \Sabre\CalDAV\Principal\Collection($principalBackend),
    new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend)
];

$server = new \Sabre\DAV\Server($nodes);
$server->setBaseUri('/server.php');

$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, "GeZC"));
$server->addPlugin(new \Sabre\DAVACL\Plugin());
$server->addPlugin(new \Sabre\DAV\Browser\Plugin());

//$server->addPlugin(new \Sabre\DAV\PropertyStorage\Plugin(new \Sabre\DAV\PropertyStorage\Backend\PDO($this->pdo)));

$server->addPlugin(new \Sabre\DAV\Sync\Plugin());
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());

$server->on('exception', 'exception');
$server->exec();

/**
 * Log failed accesses, for further processing by tools like Fail2Ban.
 *
 * @return void
 */
function exception($e) {
    if ($e instanceof \Sabre\DAV\Exception\NotAuthenticated) {
        // Applications may make their first call without auth so don't log these attempts
        // Pattern from sabre/dav/lib/DAV/Auth/Backend/AbstractDigest.php
        if (!preg_match("/No 'Authorization: (Basic|Digest)' header found./", $e->getMessage())) {
            if (isset($config['system']["failed_access_message"]) && $config['system']["failed_access_message"] !== "") {
                $log_msg = str_replace("%u", "(name stripped-out)", 'user %u authentication failure for Hestia');
                error_log($log_msg, 4);
            }
        }
    } else {
        error_log($e);
    }
}

function Debug($file, $line, $text)
{
    global $app_settings;

    if ($app_settings['Debug'])
    {
        $arrStr = explode("/", $file);
        $arrStr = array_reverse($arrStr );
        $arrStr = explode("\\", $arrStr[0]);
        $arrStr = array_reverse($arrStr );

        $toLog = sprintf("%s: %s (%d), %s\n", date("Y-m-d H:i:s"), $arrStr[0], $line, $text);

        if ($app_settings['LogDir'] == "syslog")
        {
            error_log($toLog);
        }
        else
        {
            error_log($toLog, 3, $app_settings['LogDir'] . "debug.txt");
        }
    }
}



function HestiaError($file, $line, $text)
{
    global $app_settings;

    if ($app_settings['DbError'])
    {
        $arrStr = explode("/", $file);
        $arrStr = array_reverse($arrStr );
        $arrStr = explode("\\", $arrStr[0]);
        $arrStr = array_reverse($arrStr );

        $toLog = sprintf("%s: %s (%d), %s\n", date("Y-m-d H:i:s"), $arrStr[0], $line, $text);

        if ($app_settings['LogDir'] == "syslog")
            error_log($toLog);
        else
            error_log($toLog, 3, $app_settings['LogDir'] . "error.txt");
    }
}

