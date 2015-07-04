<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath ($_EXTKEY);

if (TYPO3_MODE=="BE")	{
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule("tools","calicsgenerator","",$extPath."Classes/Backend/Modul/");
}


?>