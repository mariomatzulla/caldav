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
// throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
// }
// set_error_handler("exception_error_handler");

// Files we need
require_once PATH_typo3conf.'/ext/caldav/vendor/autoload.php';

// The object tree needs in turn to be passed to the server class
$server = new Sabre_CalDAV_TYPO3Server($pdo);


// Support for html frontend
$browser = new Sabre_DAV_Browser_Plugin();
$server->addPlugin($browser);

// ************

// Backends
$authBackend = new Sabre\DAV\Auth\Backend\TYPO3($pdo);
$calendarBackend = new Sabre\CalDAV\Backend\TYPO3($pdo);
$principalBackend = new Sabre\DAVACL\PrincipalBackend\TYPO3($pdo);

// Directory structure
$tree = [
    new Sabre\CalDAV\Principal\Collection($principalBackend),
    new Sabre\CalDAV\CalendarRoot($principalBackend, $calendarBackend)
];

$server = new Sabre\DAV\Server($tree);

if (!isset($baseUri)) {
    $basename = pathinfo(PATH_site)['basename'];
    $baseUri = '/'.substr(PATH_thisScript, strpos(PATH_thisScript, $basename));
}
$server->setBaseUri($baseUri);

/* Server Plugins */
$authPlugin = new Sabre\DAV\Auth\Plugin($authBackend);
$server->addPlugin($authPlugin);

$aclPlugin = new Sabre\DAVACL\Plugin();
$server->addPlugin($aclPlugin);

/* CalDAV support */
$caldavPlugin = new Sabre\CalDAV\Plugin();
$server->addPlugin($caldavPlugin);

/* Calendar subscription support */
$server->addPlugin(new Sabre\CalDAV\Subscriptions\Plugin());

/* Calendar scheduling support */
//$server->addPlugin(new Sabre\CalDAV\Schedule\Plugin());

/* WebDAV-Sync plugin */
$server->addPlugin(new Sabre\DAV\Sync\Plugin());

/* CalDAV Sharing support */
$server->addPlugin(new Sabre\DAV\Sharing\Plugin());
$server->addPlugin(new Sabre\CalDAV\SharingPlugin());

// Support for html frontend
$browser = new Sabre\DAV\Browser\Plugin();
$server->addPlugin($browser);

// And off we go!
$server->exec();
