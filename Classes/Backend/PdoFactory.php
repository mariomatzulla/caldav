<?php
namespace TYPO3\CMS\Caldav\Backend;

use PDO;

class PdoFactory {
    
    public static function generate() {
        $pdo;
        if (\TYPO3\CMS\Core\Utility\VersionNumberUtility::convertVersionNumberToInteger (TYPO3_version) >= 8000000) {
            $typo_db_host = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'];
            $typo_db_db = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'];
            $typo_db_user = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'];
            $typo_db_password = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'];
            if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'])) {
                $typo_db_host = $typo_db_host.';port='.$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'];
            } else {
                $typo_db_host = str_replace(':',';port=',$typo_db_host);
            }
            $pdo = new PDO('mysql:host='.$typo_db_host.';dbname='.$typo_db_db,$typo_db_user,$typo_db_password);
        } else {
            // If you want to run the SabreDAV server in a custom location (using mod_rewrite for instance)
            // You can override the baseUri here.
            // $baseUri = '/';
            if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port'])) {
                $typo_db_host = TYPO3_db_host.';port='.$GLOBALS['TYPO3_CONF_VARS']['DB']['port'];
            } else {
                $typo_db_host = str_replace(':',';port=',TYPO3_db_host);
            }
            $pdo = new PDO('mysql:host='.$typo_db_host.';dbname='.TYPO3_db,TYPO3_db_username,TYPO3_db_password);
        }
        return $pdo;
    }
}