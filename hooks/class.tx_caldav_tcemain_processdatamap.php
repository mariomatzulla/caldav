<?php
/***************************************************************
 * Copyright notice
 *
 * (c) 2005-2008 Mario Matzulla
 * (c) 2005-2008 Christian Technology Ministries International Inc.
 * All rights reserved
 *
 * This file is part of the Web-Empowered Church (WEC)
 * (http://WebEmpoweredChurch.org) ministry of Christian Technology Ministries 
 * International (http://CTMIinc.org). The WEC is developing TYPO3-based
 * (http://typo3.org) free software for churches around the world. Our desire
 * is to use the Internet to help offer new life through Jesus Christ. Please
 * see http://WebEmpoweredChurch.org/Jesus.
 *
 * You can redistribute this file and/or modify it under the terms of the 
 * GNU General Public License as published by the Free Software Foundation;
 * either version 2 of the License, or (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This file is distributed in the hope that it will be useful for ministry,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the file!
 ***************************************************************/

define('ICALENDAR_PATH', 	t3lib_extMgm::extPath('cal').'model/class.tx_model_iCalendar.php');
require_once(t3lib_extMgm::extPath('cal').'controller/class.tx_cal_functions.php');

/**
 * This hook extends the tcemain class.
 * It catches changes on tx_cal_event
 *
 * @author	Mario Matzulla <mario(at)matzullas.de>
 */
class tx_caldav_tcemain_processdatamap {

	
	function processDatamap_afterDatabaseOperations($status, $table, $id, &$fieldArray, &$tcemain) {
		
		/* If we have a new calendar event */
		if (($table == 'tx_cal_event' || $table == 'tx_cal_exception_event') && count($fieldArray)>1) {
			require_once(t3lib_extMgm::extPath('cal').'controller/class.tx_cal_functions.php');
			require_once(t3lib_extMgm::extPath('cal').'/controller/class.tx_cal_api.php');
			$event = t3lib_BEfunc::getRecord ($table, $status=='new'?$tcemain->substNEWwithIDs[$id]:$id);
			
			/* If we're in a workspace, don't notify anyone about the event */
			if($event['pid'] > 0) {
				/* Check Page TSConfig for a preview page that we should use */
				$pageTSConf = t3lib_befunc::getPagesTSconfig($event['pid']);
				if($pageTSConf['options.']['tx_cal_controller.']['pageIDForPlugin']) {
					$pageIDForPlugin = $pageTSConf['options.']['tx_cal_controller.']['pageIDForPlugin'];
				} else {
					$pageIDForPlugin = $event['pid'];
				}
			
				$page = t3lib_BEfunc::getRecord('pages', intval($pageIDForPlugin), 'doktype');

				if($page['doktype'] != 254) {
					$tx_cal_api = t3lib_div :: makeInstance('tx_cal_api');
					$tx_cal_api = &$tx_cal_api->tx_cal_api_without($pageIDForPlugin);

					if($table == 'tx_cal_event'){
						$eventObject = $tx_cal_api->findEvent($event['uid'], 'tx_cal_phpicalendar', '');
						if ($eventObject->conf['view.']['event.']['phpicalendarEventTemplate']) {
							$oldPath = &$eventObject->conf['view.']['event.']['phpicalendarEventTemplate'];
						} else {
							$oldPath = &$eventObject->conf['view.']['event.']['eventModelTemplate'];
						}
						$oldView = $eventObject->conf['view'];
						$eventObject->conf['view'] = 'single_ics';
						$extPath=t3lib_extMgm::extPath('cal');
						
						$oldPath = str_replace('EXT:cal/', $extPath, $oldPath);
						//$oldPath = str_replace(PATH_site, '', $oldPath);
						$eventObject->conf['view.']['event.']['phpicalendarEventTemplate'] = $oldPath;
						$eventObject->conf['view.']['event.']['eventModelTemplate'] = $oldPath;
						$oldBackPath = $GLOBALS['TSFE']->tmpl->getFileName_backPath;
						$GLOBALS['TSFE']->tmpl->getFileName_backPath = '';
						$fileInfo = t3lib_div::split_fileref($oldPath);
						$GLOBALS['TSFE']->tmpl->allowedPaths[] = $fileInfo['path'];
						
						$viewObj = &tx_cal_registry::Registry('basic','viewcontroller');
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