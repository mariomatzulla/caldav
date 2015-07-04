<?php
ini_set("display_errors", 1);
ini_set("track_errors", 1);
ini_set("html_errors", 1);
error_reporting(E_ALL);
// *******************************
// Checking PHP version
// *******************************
if (version_compare(phpversion(), '5.2', '<'))	die ('TYPO3 requires PHP 5.2.0 or higher.');

// *******************************
// Set error reporting
// *******************************
if (defined('E_DEPRECATED')) {
	error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED);
} else {
	error_reporting(E_ALL ^ E_NOTICE);
}

$TYPO3_MISC['microtime_start'] = microtime(true);
define('TYPO3_OS', stristr(PHP_OS,'win')&&!stristr(PHP_OS,'darwin')?'WIN':'');
define('TYPO3_MODE','FE');

$PATH_thisScript = str_replace('//', '/', str_replace('\\', '/',dirname($_SERVER['SCRIPT_FILENAME'])));

if (!defined('PATH_site')) 			define('PATH_site', str_replace('typo3conf/ext/caldav','',$PATH_thisScript));
if (!defined('PATH_t3lib')) 		define('PATH_t3lib', PATH_site.'t3lib/');

define('TYPO3_mainDir', 'typo3/');		// This is the directory of the backend administration for the sites of this TYPO3 installation.
define('PATH_typo3', PATH_site.TYPO3_mainDir);
define('PATH_typo3conf', PATH_site.'typo3conf/');

if (!defined('PATH_tslib')) {
	if (@is_dir(PATH_site.TYPO3_mainDir.'sysext/cms/tslib/')) {
		define('PATH_tslib', PATH_site.TYPO3_mainDir.'sysext/cms/tslib/');
	} elseif (@is_dir(PATH_site.'tslib/')) {
		define('PATH_tslib', PATH_site.'tslib/');
	}
}
$TYPO3_AJAX = false;

define('PATH_thisScript',str_replace('//', '/', str_replace('\\', '/',dirname($_SERVER['SCRIPT_FILENAME']))).'/caldav.php');

if (!@is_dir(PATH_typo3conf))	die('Cannot find configuration. This file is probably executed from the wrong location.');

// *********************
// Unset variable(s) in global scope (fixes #13959)
// *********************
unset($error);

// *********************
// Prevent any output until AJAX/compression is initialized to stop
// AJAX/compression data corruption
// *********************
ob_start();

require __DIR__ . '/../../../typo3/sysext/core/Classes/Core/Bootstrap.php';
\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
->baseSetup('')
->redirectToInstallerIfEssentialConfigurationDoesNotExist();

\TYPO3\CMS\Core\Core\Bootstrap::getInstance()
	->startOutputBuffering()
	->loadConfigurationAndInitialize()
	->loadTypo3LoadedExtAndExtLocalconf(TRUE)
	->applyAdditionalConfigurationSettings();

// Timetracking started
$configuredCookieName = trim($GLOBALS['TYPO3_CONF_VARS']['BE']['cookieName']);
if (empty($configuredCookieName)) {
	$configuredCookieName = 'be_typo_user';
}
if ($_COOKIE[$configuredCookieName]) {
	$TT = new \TYPO3\CMS\Core\TimeTracker\TimeTracker();
} else {
	$TT = new \TYPO3\CMS\Core\TimeTracker\NullTimeTracker();
}

$TT->start();
$TT->push('','Script start');

\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->initializeTypo3DbGlobal();

/** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
$TSFE = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
		'TYPO3\\CMS\\Frontend\\Controller\\TypoScriptFrontendController',
		$TYPO3_CONF_VARS,
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('id'),
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('type'),
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('no_cache'),
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('cHash'),
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('jumpurl'),
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('MP'),
		\TYPO3\CMS\Core\Utility\GeneralUtility::_GP('RDCT')
);


$TSFE->connectToDB();
$TSFE->sendRedirect();

$TT->pull();

/*

CalendarServer example

This server features CalDAV and ACL support

*/

// settings

// If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
// You can override the baseUri here.
// $baseUri = '/';
if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port'])) {
	$typo_db_host = TYPO3_db_host.';port='.$GLOBALS['TYPO3_CONF_VARS']['DB']['port'];
} else {
	$typo_db_host = str_replace(':',';port=',TYPO3_db_host);
}


/* Database */
//$pdo = new PDO('sqlite:data/db.sqlite');
$pdo = new PDO('mysql:host='.$typo_db_host.';dbname='.TYPO3_db,TYPO3_db_username,TYPO3_db_password);
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
//set_error_handler("exception_error_handler");

// Files we need
require_once PATH_typo3conf.'/ext/caldav/lib/Sabre/autoload.php';


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
