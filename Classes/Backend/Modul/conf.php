<?php

	// DO NOT REMOVE OR CHANGE THESE 3 LINES:
#define('TYPO3_MOD_PATH', '../typo3conf/ext/cal/mod1/');
#$BACK_PATH='../../../../typo3/';
$MCONF["name"]="tools_calicsgenerator";

	
$MCONF["access"]="admin";
#$MCONF["script"]="index.php";
$MCONF["script"]="_DISPATCH";

$MLANG["default"]["tabs_images"]["tab"] = "moduleicon.gif";
$MLANG ["default"] ["ll_ref"] = "LLL:EXT:caldav/Resources/Private/Language/locallang_ics_generator_mod.xml";
?>