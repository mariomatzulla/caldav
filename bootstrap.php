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


$classLoader = require rtrim(realpath(__DIR__ . '/../../../typo3'), '\\/') . '/../vendor/autoload.php';
\TYPO3\CMS\Core\Core\Bootstrap::initializeClassLoader($classLoader);
\TYPO3\CMS\Core\Core\Bootstrap::startOutputBuffering();

\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run();
\TYPO3\CMS\Core\Core\Environment::initialize(
            \TYPO3\CMS\Core\Core\Environment::getContext(),
            \TYPO3\CMS\Core\Core\Environment::isCli(),
            \TYPO3\CMS\Core\Core\Environment::isComposerMode(),
            \TYPO3\CMS\Core\Core\Environment::getProjectPath(),
            rtrim(realpath(__DIR__ . '/../../..'), '\\/'),
//            \TYPO3\CMS\Core\Core\Environment::getPublicPath(),
            \TYPO3\CMS\Core\Core\Environment::getVarPath(),
            \TYPO3\CMS\Core\Core\Environment::getConfigPath(),
            \TYPO3\CMS\Core\Core\Environment::getCurrentScript(),
            // This is ugly, as this change fakes the directory
            //dirname(\TYPO3\CMS\Core\Core\Environment::getCurrentScript(), 4) . DIRECTORY_SEPARATOR . 'index.php',
            \TYPO3\CMS\Core\Core\Environment::isWindows() ? 'WINDOWS' : 'UNIX'
        );
$configurationManager = \TYPO3\CMS\Core\Core\Bootstrap::createConfigurationManager();
$configurationManager->exportConfiguration();

$failsafe = True;
$disableCaching = True;
$coreCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('core', $disableCaching);
$packageCache = \TYPO3\CMS\Core\Core\Bootstrap::createPackageCache($coreCache);
$packageManager = \TYPO3\CMS\Core\Core\Bootstrap::createPackageManager(
    \TYPO3\CMS\Core\Package\FailsafePackageManager::class,
    $packageCache
);

//$coreCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('core', true);
//$packageManager = \TYPO3\CMS\Core\Core\Bootstrap::createPackageManager(
//    \TYPO3\CMS\Core\Package\FailsafePackageManager::class,
//    $coreCache
//);
\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Package\PackageManager::class,$packageManager);
$assetsCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('assets', true);
$hashCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('hash', true);
$pagesectionCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('pagesection', true);
\TYPO3\CMS\Core\Core\Bootstrap::baseSetup();

$runtimeCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('runtime', true);
$l10nCache = \TYPO3\CMS\Core\Core\Bootstrap::createCache('l10n', true);
$cacheManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class, TRUE);
$cacheManager->registerCache($runtimeCache);
$cacheManager->registerCache($l10nCache);
$cacheManager->registerCache($coreCache);
$cacheManager->registerCache($assetsCache);
$cacheManager->registerCache($hashCache);
$cacheManager->registerCache($pagesectionCache);
$languageStore = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\LanguageStore::class, $packageManager, $cacheManager);
$localizationFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\LocalizationFactory::class, $languageStore, $cacheManager);
$locales = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Locales::class);

\TYPO3\CMS\Core\Utility\GeneralUtility::addInstance(\TYPO3\CMS\Core\Localization\LanguageServiceFactory::class, \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\LanguageServiceFactory::class, $locales, $localizationFactory, $coreCache));

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::setPackageManager($packageManager);
$packageDependentCacheIdentifier = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Package\Cache\PackageDependentCacheIdentifier::class, $packageManager);
\TYPO3\CMS\Core\Utility\GeneralUtility::addInstance(\TYPO3\CMS\Core\Package\Cache\PackageDependentCacheIdentifier::class, $packageDependentCacheIdentifier);
\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class, \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class, $assetsCache, $packageDependentCacheIdentifier->withPrefix('BackendIcons')->toString()));

$providerConfigurationLoader = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\ExpressionLanguage\ProviderConfigurationLoader::class, $packageManager, $coreCache, $packageDependentCacheIdentifier->withPrefix("ExpressionLanguageProviders")->toString());
\TYPO3\CMS\Core\Utility\GeneralUtility::addInstance(\TYPO3\CMS\Core\ExpressionLanguage\ProviderConfigurationLoader::class, $providerConfigurationLoader);

$siteConfiguration = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\SiteConfiguration::class, \TYPO3\CMS\Core\Core\Environment::getConfigPath(). '/sites', $coreCache);
\TYPO3\CMS\Core\Utility\GeneralUtility::setSingletonInstance(\TYPO3\CMS\Core\Configuration\SiteConfiguration::class,$siteConfiguration);

$container = new \TYPO3\CMS\Core\DependencyInjection\FailsafeContainer();
$eventDispatcher = new \TYPO3\CMS\Core\EventDispatcher\EventDispatcher( new \TYPO3\CMS\Core\EventDispatcher\ListenerProvider( $container ) );
$pageTsConfigLoader = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\Loader\PageTsConfigLoader::class, $eventDispatcher);
\TYPO3\CMS\Core\Utility\GeneralUtility::addInstance(\TYPO3\CMS\Core\Configuration\Loader\PageTsConfigLoader::class,$pageTsConfigLoader);



call_user_func(function () {
  $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['dbalAndAdodbExtraction']
  = \TYPO3\CMS\Typo3DbLegacy\Updates\DbalAndAdodbExtractionUpdate::class;

  // Initialize database connection in $GLOBALS and connect
  $databaseConnection = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Typo3DbLegacy\Database\DatabaseConnection::class);
  $databaseConnection->setDatabaseName(
      $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] ?? ''
      );
  $databaseConnection->setDatabaseUsername(
      $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] ?? ''
      );
  $databaseConnection->setDatabasePassword(
      $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] ?? ''
      );

  $databaseHost = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] ?? '';
  if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'])) {
    $databaseConnection->setDatabasePort($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']);
  } elseif (strpos($databaseHost, ':') > 0) {
    // @TODO: Find a way to handle this case in the install tool and drop this
    list($databaseHost, $databasePort) = explode(':', $databaseHost);
    $databaseConnection->setDatabasePort($databasePort);
  }
  if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['unix_socket'])) {
    $databaseConnection->setDatabaseSocket(
        $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['unix_socket']
        );
  }
  $databaseConnection->setDatabaseHost($databaseHost);

  $databaseConnection->debugOutput = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sqlDebug'] ?? false;

  if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['persistentConnection'])
      && $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['persistentConnection']
      ) {
        $databaseConnection->setPersistentDatabaseConnection(true);
      }

      $isDatabaseHostLocalHost = in_array($databaseHost, ['localhost', '127.0.0.1', '::1'], true);
      if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverOptions'])
          && $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverOptions'] & MYSQLI_CLIENT_COMPRESS
          && !$isDatabaseHostLocalHost
          ) {
            $databaseConnection->setConnectionCompression(true);
          }

          if (!empty($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['initCommands'])) {
            $commandsAfterConnect = TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(
                LF,
                str_replace(
                    '\' . LF . \'',
                    LF,
                    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['initCommands']
                    ),
                true
                );
            $databaseConnection->setInitializeCommandsAfterConnect($commandsAfterConnect);
          }

          $GLOBALS['TYPO3_DB'] = $databaseConnection;
          $GLOBALS['TYPO3_DB']->initialize();
});
error_reporting(0);
ini_set('display_errors', 0);
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
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::setPackageManager($packageManager);
\TYPO3\CMS\Core\Core\Bootstrap::loadTypo3LoadedExtAndExtLocalconf(FALSE, $coreCache);

    
// Timetracking started
// $configuredCookieName = trim($GLOBALS['TYPO3_CONF_VARS']['BE']['cookieName']);
// if (empty($configuredCookieName)) {
//     $configuredCookieName = 'be_typo_user';
// }
// if ($_COOKIE[$configuredCookieName]) {
//     $TT = new \TYPO3\CMS\Core\TimeTracker\TimeTracker();
// } else {
//     $TT = new \TYPO3\CMS\Core\TimeTracker\NullTimeTracker();
// }
$TT = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\TimeTracker\TimeTracker::class);
$TT->setEnabled(false);
// $TT->start();
// $TT->push('', 'Script start');
// $TT->pull();
        
