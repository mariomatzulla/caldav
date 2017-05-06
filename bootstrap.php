<?php
$TYPO3_MISC['microtime_start'] = microtime(true);
define('TYPO3_OS', stristr(PHP_OS, 'win') && ! stristr(PHP_OS, 'darwin') ? 'WIN' : '');
define('TYPO3_MODE', 'FE');

$PATH_thisScript = str_replace('//', '/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'])));

if (! defined('PATH_site'))
    define('PATH_site', str_replace('typo3conf/ext/caldav', '', $PATH_thisScript));
if (! defined('PATH_t3lib'))
    define('PATH_t3lib', PATH_site . 't3lib/');

define('TYPO3_mainDir', 'typo3/'); // This is the directory of the backend administration for the sites of this TYPO3 installation.
define('PATH_typo3', PATH_site . TYPO3_mainDir);
define('PATH_typo3conf', PATH_site . 'typo3conf/');

if (! defined('PATH_tslib')) {
    if (@is_dir(PATH_site . TYPO3_mainDir . 'sysext/cms/tslib/')) {
        define('PATH_tslib', PATH_site . TYPO3_mainDir . 'sysext/cms/tslib/');
    } elseif (@is_dir(PATH_site . 'tslib/')) {
        define('PATH_tslib', PATH_site . 'tslib/');
    }
}

$TYPO3_AJAX = false;

define('PATH_thisScript', str_replace('//', '/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME']))) . '/caldav.php');

if (! @is_dir(PATH_typo3conf))
    die('Cannot find configuration. This file is probably executed from the wrong location.');
    
    // *********************
    // Unset variable(s) in global scope (fixes #13959)
    // *********************
unset($error);

// *********************
// Prevent any output until AJAX/compression is initialized to stop
// AJAX/compression data corruption
// *********************
ob_start();

$classLoader = require rtrim(realpath(__DIR__ . '/../../../typo3'), '\\/') . '/../vendor/autoload.php';
\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->initializeClassLoader($classLoader);

\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run();
if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) >= 8000000) {
    \TYPO3\CMS\Core\Core\Bootstrap::getInstance()->setRequestType(1);
}
\TYPO3\CMS\Core\Core\Bootstrap::getInstance()->baseSetup()
    ->loadConfigurationAndInitialize(TRUE)
    ->loadTypo3LoadedExtAndExtLocalconf(TRUE)
    ->setFinalCachingFrameworkCacheConfiguration()
    ->defineLoggingAndExceptionConstants()
    ->unsetReservedGlobalVariables()
    ->initializeTypo3DbGlobal();

    
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
$TT->push('', 'Script start');
$TT->pull();
        
