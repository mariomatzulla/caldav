<?php
namespace TYPO3\CMS\Caldav\Hooks;
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

define ('ICALENDAR_PATH', \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath ('cal') . 'Classes/Model/ICalendar.php');

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * This hook extends the tcemain class.
 * It catches changes on tx_cal_event
 *
 * @author	Mario Matzulla <mario(at)matzullas.de>
 */
class TceMainProcessdatamap {

	
	public function processDatamap_afterDatabaseOperations($status, $table, $id, &$fieldArray, &$tcemain) {
		
		/* If we have a new calendar event */
		if (($table == 'tx_cal_event' || $table == 'tx_cal_exception_event') && count($fieldArray)>1) {
			$event = BackendUtility::getRecord ($table, $status=='new'?$tcemain->substNEWwithIDs[$id]:$id);
			
			/* If we're in a workspace, don't notify anyone about the event */
			if($event['pid'] > 0) {
				/* Check Page TSConfig for a preview page that we should use */
				$pageTSConf = BackendUtility::getPagesTSconfig($event['pid']);
				if($pageTSConf['options.']['tx_cal_controller.']['pageIDForPlugin']) {
					$pageIDForPlugin = $pageTSConf['options.']['tx_cal_controller.']['pageIDForPlugin'];
				} else {
					$pageIDForPlugin = $event['pid'];
				}
			
				$page = BackendUtility::getRecord('pages', intval($pageIDForPlugin), 'doktype');

				if($page['doktype'] != 254) {
					$tx_cal_api = new \TYPO3\CMS\Cal\Controller\Api ();
					$tx_cal_api = &$tx_cal_api->tx_cal_api_without($pageIDForPlugin);
					
					if(! isset ($tx_cal_api->conf ['view.'] ['allowedViewsToLinkTo'])) {
						return;
					}

					if($table == 'tx_cal_event'){
						$eventObject = $tx_cal_api->modelObj->findEvent($event['uid'], 'tx_cal_phpicalendar', $tx_cal_api->conf['pidList'], false, false, false, true, true);

						if ($eventObject->conf['view.']['event.']['phpicalendarEventTemplate']) {
							$oldPath = &$eventObject->conf['view.']['event.']['phpicalendarEventTemplate'];
						} else {
							$oldPath = &$eventObject->conf['view.']['event.']['eventModelTemplate'];
						}
						$oldView = $eventObject->conf['view'];
						$eventObject->conf['view'] = 'single_ics';
						$extPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath ('cal');
						
						$oldPath = 'EXT:cal/Resources/Private/Templates/v2/event_model.tmpl';
						$oldPath = str_replace('EXT:cal/', $extPath, $oldPath);
						//$oldPath = str_replace(PATH_site, '', $oldPath);
						$eventObject->conf['view.']['event.']['phpicalendarEventTemplate'] = $oldPath;
						$eventObject->conf['view.']['event.']['eventModelTemplate'] = $oldPath;
						$oldBackPath = $GLOBALS['TSFE']->tmpl->getFileName_backPath;
						$GLOBALS['TSFE']->tmpl->getFileName_backPath = '';
						$fileInfo = GeneralUtility::split_fileref($oldPath);
						$GLOBALS['TSFE']->tmpl->allowedPaths[] = $fileInfo['path'];
						
						$viewObj = \TYPO3\CMS\Cal\Utility\Registry::Registry('basic','viewcontroller');
						$masterArray = Array($eventObject);
						$drawnIcs = $viewObj->drawIcs($masterArray, '', false);
						$table = 'tx_cal_event';
						$where = 'uid = '.$event['uid'];
						$eventData = Array('tx_caldav_data'=>$drawnIcs);
						$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$eventData);
						
						$table = 'tx_cal_calendar';
						$where = 'uid = '.$event['calendar_id'];
						$eventData = Array('tstamp'=>time());
						$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$eventData);

						$GLOBALS['TSFE']->tmpl->getFileName_backPath = $oldBackPath;
						$eventObject->conf['view'] = $oldView;
					}
				}
			}
		} 
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/caldav/hooks/class.tx_caldav_tcemain_processdatamap.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/caldav/hooks/class.tx_caldav_tcemain_processdatamap.php']);
}
?>