<?php

namespace TYPO3\CMS\Caldav\Service;

/**
 * *************************************************************
 * Copyright notice
 *
 * (c) 2010-2015 Mario Matzulla (mario(at)matzullas.de)
 * All rights reserved
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 * *************************************************************
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IcsGenerator {

  var $info = '';

  var $pageIDForPlugin;

  var $table = 'tx_cal_event,tx_cal_calendar';

  var $where = 'tx_cal_event.calendar_id = tx_cal_calendar.uid and tx_cal_calendar.type = 0 and tx_cal_calendar.nearby = 0 and tx_cal_event.type in (0,1,2,3) and tx_cal_event.deleted = 0 and tx_cal_event.hidden = 0 and tx_cal_calendar.deleted = 0 and tx_cal_calendar.hidden = 0 and ((tx_cal_event.tx_caldav_data like "") or (tx_cal_event.tx_caldav_data is null) or (tx_cal_event.tx_caldav_data like "%no event model template file found%" OR tx_cal_event.tx_caldav_data like "%no event model template file found:%" OR tx_cal_event.tx_caldav_data like "%could not find%"))';

  public function __construct($pageIDForPlugin) {

    $this->pageIDForPlugin = $pageIDForPlugin;
  }

  public function getInfo() {

    return $this->info;
  }

  public function check() {

    $select = 'tx_cal_event.*';
    $table = $this->table;
    $where = $this->where;
    
    $return = '';
    
    $results = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($results) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $results ) ) {
        $return .= '<p>[UID:' . $row ['uid'] . '; PID:' . $row ['pid'] . '] ' . $row ['title'] . ' -> ' . $row ['tx_caldav_data'] . '</p><br />';
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $results );
    }
    if ($return == '') {
      $return = 'no problems found';
    }
    return $return;
  }

  public function countEventsWithoutIcs($eventPage = 0) {

    $count = 0;
    $select = 'count(*)';
    $table = $this->table;
    $where = $this->where;
    if ($eventPage > 0) {
      $where = 'tx_cal_event.pid = ' . $eventPage . ' AND ' . $where;
    }
    $results = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($results) {
      while ( $row = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $results ) ) {
        $count = $row ['count(*)'];
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $results );
    }
    return $count;
  }

  public function generateIcs($eventPage = 0) {

    $select = 'tx_cal_event.*';
    $table = $this->table;
    $where = $this->where;
    if ($eventPage > 0) {
      $where = 'tx_cal_event.pid = ' . $eventPage . ' AND ' . $where;
    }
    $info .= 'vor';
    $results = $GLOBALS ['TYPO3_DB']->exec_SELECTquery( $select, $table, $where );
    if ($results) {
      $eventArray = Array ();
      while ( $event = $GLOBALS ['TYPO3_DB']->sql_fetch_assoc( $results ) ) {
        $eventArray [] = $event;
      }
      $GLOBALS ['TYPO3_DB']->sql_free_result( $results );
      $page = BackendUtility::getRecord( 'pages', intval( $this->pageIDForPlugin ), 'doktype' );
      if ($page ['doktype'] != 254) {
        $this->info .= '<ul>';
        $calAPI = new \TYPO3\CMS\Cal\Controller\Api();
        $calAPI = &$calAPI->tx_cal_api_without( $this->pageIDForPlugin );
        
        foreach ( $eventArray as $event ) {
          
          if ($event ['pid'] > 0) {
            /* Check Page TSConfig for a preview page that we should use */
            $eventObject = $calAPI->modelObj->findEvent( $event ['uid'], 'tx_cal_phpicalendar', $event ['pid'], false, false, false, true, true );
            if (is_object( $eventObject )) {
              $this->info .= '<li>' . $event ['title'] . '</li>';
              if ($eventObject->conf ['view.'] ['event.'] ['phpicalendarEventTemplate']) {
                $oldPath = &$eventObject->conf ['view.'] ['event.'] ['phpicalendarEventTemplate'];
              } else {
                $oldPath = &$eventObject->conf ['view.'] ['event.'] ['eventModelTemplate'];
              }
              
              $oldView = $eventObject->conf ['view'];
              $eventObject->conf ['view'] = 'single_ics';
              $extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath( 'cal' );
              
              $eventObject->conf ['view.'] ['event.'] ['eventModelTemplate'] = $extPath . 'Resources/Private/Templates/v2/event_model.tmpl';
              // $oldBackPath = $GLOBALS ['TSFE']->tmpl->getFileName_backPath;
              // $GLOBALS ['TSFE']->tmpl->getFileName_backPath = '';
              $fileInfo = GeneralUtility::split_fileref( $oldPath );
              // $GLOBALS ['TSFE']->tmpl->allowedPaths [] = $fileInfo ['path'];
              
              $viewObj = &\TYPO3\CMS\cal\Utility\Registry::Registry( 'basic', 'viewcontroller' );
              
              $masterArray = Array (
                  $eventObject
              );
              $drawnIcs = $viewObj->drawIcs( $masterArray, '', false );
              
              $table = 'tx_cal_event';
              $where = 'uid = ' . $event ['uid'];
              $eventData = Array (
                  'tx_caldav_data' => rtrim( $drawnIcs )
              )
              // 'tx_caldav_data' => rtrim(utf8_encode($drawnIcs))
              ;
              // update tx_cal_event set tx_caldav_data = null
              if ([ 
                  'tx_caldav_uid'
              ] == null || $event ['tx_caldav_uid'] == '' || strpos( $event ['tx_caldav_uid'], '_' . $event ['calendar_id'] . '_' . $event ['uid'] ) === 0) {
                // $pageTSConf = BackendUtility::getPagesTSconfig ($this->pageIDForPlugin);
                // if ($pageTSConf ['view.'] ['ics.'] ['eventUidPrefix']) {
                // $eventData ['tx_caldav_uid'] = $pageTSConf ['view.'] ['ics.'] ['eventUidPrefix'] . '_' . $event ['calendar_id'] . '_' . $event ['uid'];
                // } else {
                $eventData ['tx_caldav_uid'] = $calAPI->conf ['view.'] ['ics.'] ['eventUidPrefix'] . '_' . $event ['calendar_id'] . '_' . $event ['uid'];
                // }
              }
              $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $eventData );
              
              $table = 'tx_cal_calendar';
              $where = 'tx_cal_calendar.uid = ' . $event ['calendar_id'];
              $eventData = Array (
                  'tstamp' => time()
              );
              $result = $GLOBALS ['TYPO3_DB']->exec_UPDATEquery( $table, $where, $eventData );
              
              // $GLOBALS ['TSFE']->tmpl->getFileName_backPath = $oldBackPath;
              $eventObject->conf ['view'] = $oldView;
              $this->info .= '</li>';
            }
          }
        }
      }
    }
    
    $this->info .= '</ul>Done.';
  }
}

?>