<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = 'EXT:caldav/hooks/class.tx_caldav_tcemain_processdatamap.php:tx_caldav_tcemain_processdatamap';

?>