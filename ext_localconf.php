<?php
if (! defined ( 'TYPO3_MODE' )) {
	die ( 'Access denied.' );
}

$GLOBALS ['TYPO3_CONF_VARS'] ['SC_OPTIONS'] ['t3lib/class.t3lib_tcemain.php'] ['processDatamapClass'] ['tx_caldav'] = 'TYPO3\\CMS\\Caldav\\Hooks\\TceMainProcessdatamap';
$GLOBALS ['TYPO3_CONF_VARS'] [TYPO3_MODE] ['EXTCONF'] ['ext/cal/controller/class.tx_cal_event_service.php'] ['eventServiceClass'] ['tx_caldav'] = 'TYPO3\\CMS\\Caldav\\Hooks\\EventService';

?>