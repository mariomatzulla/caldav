<?php

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

if(!defined('PATH_thisScript')) {
#	define('PATH_thisScript', str_replace('//', '/', str_replace('\\', '/',
#		(PHP_SAPI == 'fpm-fcgi' || PHP_SAPI == 'cgi' || PHP_SAPI == 'isapi' || PHP_SAPI == 'cgi-fcgi') &&
#		($_SERVER['ORIG_PATH_TRANSLATED'] ? $_SERVER['ORIG_PATH_TRANSLATED'] : $_SERVER['PATH_TRANSLATED']) ?
#		($_SERVER['ORIG_PATH_TRANSLATED'] ? $_SERVER['ORIG_PATH_TRANSLATED'] : $_SERVER['PATH_TRANSLATED']) :
#		($_SERVER['ORIG_SCRIPT_FILENAME'] ? $_SERVER['ORIG_SCRIPT_FILENAME'] : $_SERVER['SCRIPT_FILENAME']))));
	define('PATH_thisScript',str_replace('//', '/', str_replace('\\', '/',dirname($_SERVER['SCRIPT_FILENAME']))));
}
if (!defined('PATH_site')) 			define('PATH_site', str_replace('typo3conf/ext/caldav','',PATH_thisScript));
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

// *********************
// Timetracking started
// *********************
if ($_COOKIE['be_typo_user']) {
	require_once(PATH_t3lib.'class.t3lib_timetrack.php');
	$TT = new t3lib_timeTrack;
} else {
	require_once(PATH_t3lib.'class.t3lib_timetracknull.php');
	$TT = new t3lib_timeTrackNull;
}

$TT->start();
$TT->push('','Script start');


// *********************
// Mandatory libraries included
// *********************
$TT->push('Include class t3lib_db, t3lib_div, t3lib_extmgm','');
	require_once(PATH_t3lib.'class.t3lib_div.php');
	require_once(PATH_t3lib.'class.t3lib_extmgm.php');
$TT->pull();

require(PATH_t3lib.'config_default.php');

if (!defined ('TYPO3_db')) 	die ('The configuration file was not included.');	// the name of the TYPO3 database is stored in this constant. Here the inclusion of the config-file is verified by checking if this var is set.
if (!t3lib_extMgm::isLoaded('cms'))	die('<strong>Error:</strong> The main frontend extension "cms" was not loaded. Enable it in the extension manager in the backend.');

if (!defined('PATH_tslib')) {
	define('PATH_tslib', t3lib_extMgm::extPath('cms').'tslib/');
}




// *********************
// Error & Exception handling
// *********************
if ($TYPO3_CONF_VARS['SC_OPTIONS']['errors']['exceptionHandler'] !== '') {
	$TT->push('Register Exceptionhandler', '');
	if ($TYPO3_CONF_VARS['SYS']['errorHandler'] !== '') {
			// register an error handler for the given errorHandlerErrors
		$errorHandler = t3lib_div::makeInstance($TYPO3_CONF_VARS['SYS']['errorHandler'], $TYPO3_CONF_VARS['SYS']['errorHandlerErrors']);
			// set errors which will be converted in an exception
		$errorHandler->setExceptionalErrors($TYPO3_CONF_VARS['SC_OPTIONS']['errors']['exceptionalErrors']);
	}
	$exceptionHandler = t3lib_div::makeInstance($TYPO3_CONF_VARS['SC_OPTIONS']['errors']['exceptionHandler']);
	$TT->pull();
}

$TYPO3_DB = t3lib_div::makeInstance('t3lib_DB');
$TYPO3_DB->debugOutput = $TYPO3_CONF_VARS['SYS']['sqlDebug'];

$CLIENT = t3lib_div::clientInfo();				// Set to the browser: net / msie if 4+ browsers
$TT->pull();

require_once (t3lib_extMgm::extPath('cal').'controller/class.tx_cal_tsfe.php');
// ***********************************
// Create $TSFE object (TSFE = TypoScript Front End)
// Connecting to database
// ***********************************
$TSFE = t3lib_div::makeInstance('tslib_fe',
	$TYPO3_CONF_VARS,
	t3lib_div::_GP('id'),
	t3lib_div::_GP('type'),
	t3lib_div::_GP('no_cache'),
	t3lib_div::_GP('cHash'),
	t3lib_div::_GP('jumpurl'),
	t3lib_div::_GP('MP'),
	t3lib_div::_GP('RDCT')
);
	// Initialize FE user object:
$feUserObj = tslib_eidtools::initFeUser();
tslib_eidtools::connectDB();
require_once(t3lib_extMgm::extPath('simulatestatic').'class.tx_simulatestatic.php');
require_once(t3lib_extMgm::extPath('statictemplates').'class.tx_statictemplates.php');
require_once(t3lib_extMgm::extPath('cal').'model/class.tx_cal_date.php');
require_once(t3lib_extMgm::extPath('cal').'service/class.tx_cal_icalendar_service.php');
/*

CalendarServer example

This server features CalDAV and ACL support

*/

// settings

// If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
// You can override the baseUri here.
// $baseUri = '/';

$typo_db_host = str_replace(':',';port=',TYPO3_db_host);

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

if (isset($baseUri))
    $server->setBaseUri($baseUri);

// Support for html frontend
$browser = new Sabre_DAV_Browser_Plugin();
$server->addPlugin($browser);

// And off we go!
$server->exec();
