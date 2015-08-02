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

/**
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 *        
 */
class EventService {
	
	/**
	 * 
	 * @param unknown $eventService
	 * @param unknown $eventObject
	 */
	public function saveEvent($eventService, $eventObject) {
		$this->_updateEvent($eventObject);
		$this->_updateCalendar($eventObject->getCalendarUid());
	}
	
	/**
	 * 
	 * @param unknown $eventService
	 * @param unknown $eventObject
	 */
	public function updateEvent($eventService, $eventObject) {
		$this->saveEvent($eventService, $eventObject);
	}
	
	/**
	 *
	 * @param unknown $eventService
	 * @param unknown $eventObject
	 */
	public function removeEvent($eventService, $eventObject) {
		$this->_updateCalendar($eventObject->getCalendarUid());
	}
	
	/**
	 * 
	 * @param unknown $eventObject
	 * @throws \RuntimeException
	 */
	private function _updateEvent($eventObject) {
		$viewObj = \TYPO3\CMS\Cal\Utility\Registry::Registry('basic','viewcontroller');
		$masterArray = Array($eventObject);
		$drawnIcs = $viewObj->drawIcs($masterArray, '', false);
		$table = 'tx_cal_event';
		$where = 'uid = '.$eventObject->getUid();
		$eventData = Array('tx_caldav_data'=>$drawnIcs);
		$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$eventData);
		if (FALSE === $result){
			throw new \RuntimeException('Could not update event record: '.$GLOBALS ['TYPO3_DB']->sql_error(), 1438367693);
		}
	}
	
	/**
	 * 
	 * @param unknown $calendarId
	 * @throws \RuntimeException
	 */
	private function _updateCalendar($calendarId) {
		$table = 'tx_cal_calendar';
		$where = 'uid = '.$calendarId;
		$eventData = Array('tstamp'=>time());
		$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$eventData);
		if (FALSE === $result){
			throw new \RuntimeException('Could not update calendar record: '.$GLOBALS ['TYPO3_DB']->sql_error(), 1438367694);
		}
	}
}
?>