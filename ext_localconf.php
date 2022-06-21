<?php
if (! defined ('TYPO3_MODE')) die ('Access denied.');

$boot = function () {
  $_EXTKEY = $GLOBALS['_EXTKEY'] = 'cal';
  \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPItoST43 ($_EXTKEY, 'Classes/View/CaldavConnector.php', '_pi1', 'list_type', 1);
  
  $GLOBALS ['TYPO3_CONF_VARS'] ['SC_OPTIONS'] ['t3lib/class.t3lib_tcemain.php'] ['processDatamapClass'] ['tx_caldav'] = 'TYPO3\\CMS\\Caldav\\Hooks\\TceMainProcessdatamap';
  $GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/controller/class.tx_cal_event_service.php'] ['eventServiceClass'] ['tx_caldav'] = 'TYPO3\\CMS\\Caldav\\Hooks\\EventService';
};
$boot();
unset($boot);
?>