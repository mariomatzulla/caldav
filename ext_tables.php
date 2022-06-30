<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}
$_EXTKEY = $GLOBALS['_EXTKEY'] = 'caldav';
$extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath ($_EXTKEY);

if (TYPO3_MODE=="BE")	{
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule("tools","calicsgenerator","",$extPath."Classes/Backend/Modul/");
}

if (TYPO3_MODE == "BE") {
	if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger ( TYPO3_version ) < '8000000') {
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule( "tools", "calicsgenerator", "", $extPath . "Classes/Backend/Modul/" );
	} else {
		// Add module
		\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
				'tools',
				'txcaldavM1',
				'',
				'',
				[
						'routeTarget' => \TYPO3\CMS\Caldav\Backend\Modul\IcsGeneratorModul::class . '::mainAction',
						'access' => 'admin',
						'name' => 'tools_txcaldavM1',
						'icon' => 'EXT:' . $_EXTKEY . '/Classes/Backend/Modul/icon_tx_caldav_ics_generator.svg',
						'labels' => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_ics_generator.xml'
				]
				);
	}
}


?>