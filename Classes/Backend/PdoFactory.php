<?php

namespace TYPO3\CMS\Caldav\Backend;

use PDO;

class PdoFactory {

  public static function generate() {

    $pdo;
    $configurationManager = \TYPO3\CMS\Core\Core\Bootstrap::createConfigurationManager();
    $typo_db_host = $configurationManager->getLocalConfigurationValueByPath( 'DB/Connections/Default/host' );
    $typo_db_db = $configurationManager->getLocalConfigurationValueByPath( 'DB/Connections/Default/dbname' );
    $typo_db_user = $configurationManager->getLocalConfigurationValueByPath( 'DB/Connections/Default/user' );
    $typo_db_password = $configurationManager->getLocalConfigurationValueByPath( 'DB/Connections/Default/password' );
    try {
      $typo_db_host = $typo_db_host . ';port=' . $configurationManager->getLocalConfigurationValueByPath( 'DB/Connections/Default/port' );
    } catch ( \TYPO3\CMS\Core\Utility\Exception\MissingArrayPathException $e ) {
      $typo_db_host = str_replace( ':', ';port=', $typo_db_host );
    }
    $pdo = new PDO( 'mysql:host=' . $typo_db_host . ';dbname=' . $typo_db_db, $typo_db_user, $typo_db_password );
    return $pdo;
  }
}