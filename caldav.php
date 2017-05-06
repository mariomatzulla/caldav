<?php

$debug = FALSE;
if ($debug) {
    ini_set("display_errors", 1);
    ini_set("track_errors", 1);
    ini_set("html_errors", 1);
    error_reporting(E_ALL);
    
    // *******************************
    // Set error reporting
    // *******************************
    if (defined('E_DEPRECATED')) {
        error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
    } else {
        error_reporting(E_ALL ^ E_NOTICE);
    }
}

include_once 'bootstrap.php';

// settings
$pdo = \TYPO3\CMS\Caldav\Backend\PdoFactory::generate();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Mapping PHP errors to exceptions
// function exception_error_handler($errno, $errstr, $errfile, $errline)
// {
//     throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
// }
// set_error_handler("exception_error_handler");

// Files we need
// require_once PATH_typo3conf.'/ext/caldav/lib/Sabre/autoload.php';

// The object tree needs in turn to be passed to the server class
$server = new Sabre_CalDAV_TYPO3Server($pdo);

if (isset($baseUri)) {
    $server->setBaseUri($baseUri);
}

// Support for html frontend
$browser = new Sabre_DAV_Browser_Plugin();
$server->addPlugin($browser);

// And off we go!
$server->exec();
