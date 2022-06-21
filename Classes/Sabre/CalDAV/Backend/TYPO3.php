<?php

namespace Sabre\CalDAV\Backend;

use Sabre\CalDAV;
use Sabre\DAV;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Xml\Element\Sharee;
use Sabre\VObject;

/**
 * TYPO3 CalDAV backend
 *
 * This backend is used to store calendar-data in a TYPO3 database
 *
 * @copyright Copyright (C) Mario Matzulla. All rights reserved.
 * @author Mario Matzulla (http://www.matzullas.de)
 * @license http://sabre.io/license/ Modified BSD License
 */
class TYPO3 extends AbstractBackend implements SyncSupport {

  /**
   * We need to specify a max date, because we need to stop *somewhere*
   *
   * On 32 bit system the maximum for a signed integer is 2147483647, so
   * MAX_DATE cannot be higher than date('Y-m-d', 2147483647) which results
   * in 2038-01-19 to avoid problems when the date is converted
   * to a unix timestamp.
   */
  const MAX_DATE = '2038-01-01';

  const DEBUG_THIS = FALSE;

  /**
   * pdo
   *
   * @var \PDO
   */
  public $pdo;

  /**
   * The table name that will be used for calendars
   *
   * @var string
   */
  public $calendarTableName = 'tx_cal_calendar';

  /**
   * The table name that will be used for calendars instances.
   *
   * A single calendar can have multiple instances, if the calendar is
   * shared.
   *
   * @var string
   */
  public $calendarInstancesTableName = 'tx_cal_calendar';

  /**
   * The table name that will be used for calendar objects
   *
   * @var string
   */
  public $calendarObjectTableName = 'tx_cal_event';

  /**
   * The table name that will be used for tracking changes in calendars.
   *
   * @var string
   */
  public $calendarChangesTableName = 'calendarchanges';

  /**
   * The table name that will be used inbox items.
   *
   * @var string
   */
  public $schedulingObjectTableName = 'schedulingobjects';

  /**
   * The table name that will be used for calendar subscriptions.
   *
   * @var string
   */
  public $calendarSubscriptionsTableName = 'calendarsubscriptions';

  /**
   * List of CalDAV properties, and how they map to database fieldnames
   *
   * Add your own properties by simply adding on to this array
   *
   * @var array
   */
  public $propertyMap = array (
      '{DAV:}displayname' => 'title',
      '{urn:ietf:params:xml:ns:caldav}calendar-description' => 'tx_caldav_data'
  );
  // '{urn:ietf:params:xml:ns:caldav}calendar-timezone' => 'timezone',
  // '{http://apple.com/ns/ical/}calendar-order' => 'calendarorder',
  // '{http://apple.com/ns/ical/}calendar-color' => 'calendarcolor'
  
  /**
   * List of subscription properties, and how they map to database fieldnames.
   *
   * @var array
   */
  public $subscriptionPropertyMap = [ 
      '{DAV:}displayname' => 'title',
      '{http://apple.com/ns/ical/}refreshrate' => 'refreshrate',
      '{http://apple.com/ns/ical/}calendar-order' => 'calendarorder',
      '{http://apple.com/ns/ical/}calendar-color' => 'calendarcolor',
      '{http://calendarserver.org/ns/}subscribed-strip-todos' => 'striptodos',
      '{http://calendarserver.org/ns/}subscribed-strip-alarms' => 'stripalarms',
      '{http://calendarserver.org/ns/}subscribed-strip-attachments' => 'stripattachments'
  ];

  /**
   * Creates the backend
   *
   * @param \PDO $pdo          
   */
  function __construct(\PDO $pdo) {

    $this->pdo = $pdo;
  }

  /**
   * Returns a list of calendars for a principal.
   *
   * Every project is an array with the following keys:
   * * id, a unique id that will be used by other functions to modify the
   * calendar. This can be the same as the uri or a database key.
   * * uri. This is just the 'base uri' or 'filename' of the calendar.
   * * principaluri. The owner of the calendar. Almost always the same as
   * principalUri passed to this method.
   *
   * Furthermore it can contain webdav properties in clark notation. A very
   * common one is '{DAV:}displayname'.
   *
   * Many clients also require:
   * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
   * For this property, you can just return an instance of
   * Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet.
   *
   * If you return {http://sabredav.org/ns}read-only and set the value to 1,
   * ACL will automatically be put in read-only mode.
   *
   * @param string $principalUri          
   * @return array
   */
  function getCalendarsForUser($principalUri) {

    $principalUriParts = explode( "/", $principalUri );
    $usernameVar = array_pop( $principalUriParts );
    
    $stmt = $this->pdo->prepare( "SELECT uid, username, tx_cal_calendar FROM fe_users WHERE username = ? AND deleted=0" );
    $stmt->execute( array (
        $usernameVar
    ) );
    
    $calendars = [ ];
    
    while ( $user = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      
      $stmt2 = $this->pdo->prepare( "SELECT * FROM tx_cal_calendar WHERE uid in (?)" );
      $stmt2->execute( array (
          $user ['tx_cal_calendar']
      ) );
      
      while ( $row = $stmt2->fetch( \PDO::FETCH_ASSOC ) ) {
        
        $components = explode( ',', 'VEVENT,VTODO' );
        
        $calendar = [ 
            'id' => [ 
                ( int ) $row ['uid'],
                ( int ) $row ['uid']
            ],
            'uri' => $row ['title'],
            'principaluri' => $principalUri,
            '{' . CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => $row ['tstamp'] ? $row ['tstamp'] : '0',
            '{http://sabredav.org/ns}sync-token' => 'http://sabredav.org/ns/sync-token/' . ($row ['tstamp'] ? $row ['tstamp'] : '0'),
            '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet( $components ),
            '{DAV:}displayname' => $row ['title'],
            '{urn:ietf:params:xml:ns:caldav}calendar-description' => '',
            '{urn:ietf:params:xml:ns:caldav}calendar-timezone' => null,
            '{http://apple.com/ns/ical/}calendar-order' => 0,
            '{http://apple.com/ns/ical/}calendar-color' => null
        ];
        
        $calendars [] = $calendar;
      }
    }
    return $calendars;
  }

  /**
   * Creates a new calendar for a principal.
   *
   * If the creation was a success, an id must be returned that can be used
   * to reference this calendar in other methods, such as updateCalendar.
   *
   * @param string $principalUri          
   * @param string $calendarUri          
   * @param array $properties          
   * @return string
   */
  function createCalendar($principalUri, $calendarUri, array $properties) {
    // We handle calendar names like ids. Until we change this we can not hanlde this feature
    throw new DAV\Exception\NotImplemented( 'Not implemented: createCalendar' );
    $pid = 0;
    $userId = 0;
    $userCalendars = [ ];
    // First find another calendar for this user and use it's PID
    $principalUriParts = explode( "/", $principalUri );
    $stmt = $this->pdo->prepare( "SELECT uid, username, tx_cal_calendar FROM fe_users WHERE username = ? AND deleted=0" );
    $stmt->execute( array (
        array_pop( $principalUriParts )
    ) );
    
    while ( $user = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      $userId = $user ['uid'];
      $stmt2 = $this->pdo->prepare( "SELECT * FROM tx_cal_calendar WHERE uid in (?)" );
      $stmt2->execute( array (
          $user ['tx_cal_calendar']
      ) );
      $userCalendars = array (
          $user ['tx_cal_calendar']
      );
      while ( $row = $stmt2->fetch( \PDO::FETCH_ASSOC ) ) {
        $pid = $row ['pid'];
      }
    }
    
    $calendarUriParts = explode( "/", $calendarUri );
    $fieldNames = array (
        'pid',
        'tstamp'
    );
    $values = array (
        ':pid' => $pid,
        ':time' => time()
    );
    
    // Default value
    $sccs = '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set';
    // $fieldNames[] = 'components';
    if (! isset( $properties [$sccs] )) {
      // $values[':components'] = 'VEVENT,VTODO';
    } else {
      if (! ($properties [$sccs] instanceof CalDAV\Xml\Property\SupportedCalendarComponentSet)) {
        throw new DAV\Exception( 'The ' . $sccs . ' property must be of type: Sabre_CalDAV_Xml_Property_SupportedCalendarComponentSet' );
      }
      // $values[':components'] = implode(',', $properties[$sccs]->getValue());
    }
    
    foreach ( $this->propertyMap as $xmlName => $dbName ) {
      if (isset( $properties [$xmlName] )) {
        
        $myValue = $properties [$xmlName];
        $values [':' . $dbName] = $properties [$xmlName];
        $fieldNames [] = $dbName;
      }
    }
    
    $stmt = $this->pdo->prepare( "INSERT INTO tx_cal_calendar (" . implode( ', ', $fieldNames ) . ") VALUES (" . implode( ', ', array_keys( $values ) ) . ")" );
    $stmt->execute( $values );
    
    $calendarId = $this->pdo->lastInsertId( $this->calendarInstancesTableName );
    
    $userCalendars [] = $calendarId;
    
    $stmt = $this->pdo->prepare( 'UPDATE fe_users SET tx_cal_calendar = ? WHERE uid = ?' );
    $stmt->execute( array (
        implode( ',', $userCalendars ),
        $userId
    ) );
    
    return [ 
        $calendarId,
        $calendarId
    ];
  }

  /**
   * Updates properties for a calendar.
   *
   * The list of mutations is stored in a Sabre\DAV\PropPatch object.
   * To do the actual updates, you must tell this object which properties
   * you're going to process with the handle() method.
   *
   * Calling the handle method is like telling the PropPatch object "I
   * promise I can handle updating this property".
   *
   * Read the PropPatch documentation for more info and examples.
   *
   * @param mixed $calendarId          
   * @param \Sabre\DAV\PropPatch $propPatch          
   * @return void
   */
  function updateCalendar($calendarId, \Sabre\DAV\PropPatch $propPatch) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $supportedProperties = array_keys( $this->propertyMap );
    $supportedProperties [] = '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp';
    
    $propPatch->handle( $supportedProperties, function ($mutations) use ($calendarId, $instanceId) {
      $newValues = [ ];
      foreach ( $mutations as $propertyName => $propertyValue ) {
        
        switch ($propertyName) {
          case '{' . CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' :
            $fieldName = 'transparent';
            $newValues [$fieldName] = $propertyValue->getValue() === 'transparent';
            break;
          default :
            $fieldName = $this->propertyMap [$propertyName];
            $newValues [$fieldName] = $propertyValue;
            break;
        }
      }
      $valuesSql = [ ];
      unset( $newValues ['calendarcolor'] );
      foreach ( $newValues as $fieldName => $value ) {
        $valuesSql [] = $fieldName . ' = ?';
      }
      
      if (! empty( $newValues )) {
        $stmt = $this->pdo->prepare( "UPDATE tx_cal_calendar SET " . implode( ', ', $valuesSql ) . " WHERE uid = ?" );
        $newValues ['id'] = $calendarId;
        $stmt->execute( array_values( $newValues ) );
      }
      
      $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_calendar WHERE uid = ?' );
      $stmt->execute( array (
          $calendarId
      ) );
      $calendarRow = $stmt->fetch();
      $this->clearCache( $calendarRow ['pid'] );
      
      $this->addChange( $calendarId, "", 2 );
      
      return true;
    } );
  }

  /**
   * Delete a calendar and all it's objects
   *
   * @param mixed $calendarId          
   * @return void
   */
  function deleteCalendar($calendarId) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_calendar WHERE uid = ?' );
    $stmt->execute( array (
        $calendarId
    ) );
    $calendarRow = $stmt->fetch();
    
    $stmt = $this->pdo->prepare( 'DELETE FROM tx_cal_event WHERE calendar_id = ?' );
    $stmt->execute( array (
        $calendarId
    ) );
    
    $stmt = $this->pdo->prepare( 'DELETE FROM tx_cal_calendar WHERE uid = ?' );
    $stmt->execute( array (
        $calendarId
    ) );
    
    $stmt = $this->pdo->prepare( "SELECT uid, tx_cal_calendar FROM fe_users WHERE FIND_IN_SET(?,tx_cal_calendar) AND deleted=0" );
    $stmt->execute( array (
        $calendarId
    ) );
    
    while ( $user = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      $userId = $user ['uid'];
      $userCalendars = array (
          $user ['tx_cal_calendar']
      );
      if (($key = array_search( $calendarId, $userCalendars )) !== false) {
        unset( $userCalendars [$key] );
      }
      $stmt2 = $this->pdo->prepare( 'UPDATE fe_users SET tx_cal_calendar = ? WHERE uid = ?' );
      $stmt2->execute( array (
          implode( ',', $userCalendars ),
          $userId
      ) );
    }
    
    $this->clearCache( $calendarRow ['pid'] );
  }

  /**
   * Returns all calendar objects within a calendar.
   *
   * Every item contains an array with the following keys:
   * * calendardata - The iCalendar-compatible calendar data
   * * uri - a unique key which will be used to construct the uri. This can
   * be any arbitrary string, but making sure it ends with '.ics' is a
   * good idea. This is only the basename, or filename, not the full
   * path.
   * * lastmodified - a timestamp of the last modification time
   * * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
   * ' "abcdef"')
   * * size - The size of the calendar objects, in bytes.
   * * component - optional, a string containing the type of object, such
   * as 'vevent' or 'vtodo'. If specified, this will be used to populate
   * the Content-Type header.
   *
   * Note that the etag is optional, but it's highly encouraged to return for
   * speed reasons.
   *
   * The calendardata is also optional. If it's not returned
   * 'getCalendarObject' will be called later, which *is* expected to return
   * calendardata.
   *
   * If neither etag or size are specified, the calendardata will be
   * used/fetched to determine these numbers. If both are specified the
   * amount of times this is needed is reduced by a great degree.
   *
   * @param mixed $calendarId          
   * @return array
   */
  function getCalendarObjects($calendarId) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $now = new \TYPO3\CMS\Cal\Model\CalDate();
    $now->setHour( 0 );
    $now->setMinute( 0 );
    $now->setSecond( 0 );
    
    $then = new \TYPO3\CMS\Cal\Model\CalDate();
    $then->setHour( 0 );
    $then->setMinute( 0 );
    $then->setSecond( 0 );
    
    $then->setYear( $now->getYear() + 50 ); // all events for the next 50 years
    
    $archiveDays = 7;
    if(isset($GLOBALS ['TYPO3_CONF_VARS'] ['EXT'] ['extConf'] ['caldav'])){
      $confArr = unserialize( $GLOBALS ['TYPO3_CONF_VARS'] ['EXT'] ['extConf'] ['caldav'] );
      $archiveDays = intval( $confArr ['archiveDays'] );
      if ($archiveDays < 1) {
        $archiveDays = 7;
      }
    }
    
    $now->addSeconds( - 86400 * $archiveDays ); // include the last x days
    
    $formattedStarttime = $now->format( '%Y%m%d' );
    $formattedEndtime = $then->format( '%Y%m%d' );
    
    // $stmt = $this->pdo->prepare ( 'SELECT * FROM tx_cal_event WHERE calendar_id = ? AND deleted = 0 AND hidden = 0' );
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_event WHERE calendar_id = ? AND deleted = 0 AND hidden = 0 AND ((tx_cal_event.start_date>=? AND tx_cal_event.start_date<=?) OR (tx_cal_event.end_date<=? AND tx_cal_event.end_date>=?) OR (tx_cal_event.end_date>=? AND tx_cal_event.start_date<=?) or (start_date<=? AND (freq IN ("day", "week", "month", "year") AND (until>=? OR until=0))) OR (tx_cal_event.rdate AND tx_cal_event.rdate_type IN ("date_time", "date", "period")))' );
    $stmt->execute( array (
        $calendarId,
        $formattedStarttime,
        $formattedEndtime,
        $formattedEndtime,
        $formattedStarttime,
        $formattedEndtime,
        $formattedStarttime,
        $formattedEndtime,
        $formattedStarttime
    ) );
    $eventArray = $stmt->fetchAll();
    $preparedArray = $this->getEventsFromResult( $eventArray );
    
    return $preparedArray;
  }

  private function getEventsFromResult($eventArray) {

    $preparedArray = Array ();
    foreach ( $eventArray as $eventRow ) {
      if ($eventRow ['tx_caldav_uid'] == '' && $eventRow ['icsUid'] == '') {
        $eventRow ['tx_caldav_uid'] = 'a1b2c3_' . $eventRow ['calendar_id'] . '_' . $eventRow ['uid'];
        $eventRow ['icsUid'] = $eventRow ['tx_caldav_uid'];
        $stmt = $this->pdo->prepare( "UPDATE tx_cal_event SET tx_caldav_uid = ?, icsUid = ? WHERE uid = ?" );
        $stmt->execute( Array (
            $eventRow ['tx_caldav_uid'],
            $eventRow ['icsUid'],
            $eventRow ['uid']
        ) );
      } else {
        if ($eventRow ['tx_caldav_uid'] == '') {
          $eventRow ['tx_caldav_uid'] = $eventRow ['icsUid'];
          $stmt = $this->pdo->prepare( "UPDATE tx_cal_event SET tx_caldav_uid = ? WHERE uid = ?" );
          $stmt->execute( Array (
              $eventRow ['tx_caldav_uid'],
              $eventRow ['uid']
          ) );
        } else {
          if ($eventRow ['icsUid'] == '') {
            $eventRow ['icsUid'] = $eventRow ['tx_caldav_uid'];
            $stmt = $this->pdo->prepare( "UPDATE tx_cal_event SET icsUid = ? WHERE uid = ?" );
            $stmt->execute( Array (
                $eventRow ['icsUid'],
                $eventRow ['uid']
            ) );
          }
        }
      }
      // TODO: fill other infos too: component
      $calendarData = rtrim( $eventRow ['tx_caldav_data'] );
      
      $preparedArray [] = Array (
          'id' => $eventRow ['uid'],
          'uri' => $eventRow ['tx_caldav_uid'],
          'lastmodified' => $eventRow ['tstamp'],
          'displayname' => $eventRow ['title'],
          'calendardata' => $calendarData,
          'etag' => md5( $calendarData ),
          'size' => strlen( $calendarData ),
          'calendarid' => $eventRow ['calendar_id']
      );
    }
    return $preparedArray;
  }

  /**
   * Returns information from a single calendar object, based on it's object
   * uri.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * The returned array must have the same keys as getCalendarObjects. The
   * 'calendardata' object is required here though, while it's not required
   * for getCalendarObjects.
   *
   * This method must return null if the object did not exist.
   *
   * @param mixed $calendarId          
   * @param string $objectUri          
   * @return array|null
   */
  function getCalendarObject($calendarId, $objectUri) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_event WHERE calendar_id = ? AND tx_caldav_uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        $calendarId,
        $objectUri
    ) );
    $eventRow = $stmt->fetch();
    
    if (! $eventRow) {
      return null;
    }
    // TODO: return component
    $calendarData = rtrim( $eventRow ['tx_caldav_data'] );
    
    return [ 
        'id' => $eventRow ['uid'],
        'uri' => $eventRow ['tx_caldav_uid'],
        'lastmodified' => $eventRow ['tstamp'],
        'displayname' => $eventRow ['title'],
        'calendardata' => $calendarData,
        'etag' => md5( $calendarData ),
        'size' => strlen( $calendarData ),
        'calendarid' => $calendarId
    ];
  }

  /**
   * Returns a list of calendar objects.
   *
   * This method should work identical to getCalendarObject, but instead
   * return all the calendar objects in the list as an array.
   *
   * If the backend supports this, it may allow for some speed-ups.
   *
   * @param mixed $calendarId          
   * @param array $uris          
   * @return array
   */
  function getMultipleCalendarObjects($calendarId, array $uris) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    
    $result = [ ];
    foreach ( $uris as $uri ) {
      $result [] = $this->getCalendarObject( $calendarId, $uri );
    }
    return $result;
  }

  /**
   * Creates a new calendar object.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * It is possible return an etag from this function, which will be used in
   * the response to this PUT request. Note that the ETag must be surrounded
   * by double-quotes.
   *
   * However, you should only really return this ETag if you don't mangle the
   * calendar-data. If the result of a subsequent GET to this object is not
   * the exact same as this request body, you should omit the ETag.
   *
   * @param mixed $calendarId          
   * @param string $objectUri          
   * @param string $calendarData          
   * @return string|null
   */
  function createCalendarObject($calendarId, $objectUri, $calendarData) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $extraData = $this->getDenormalizedData( $calendarData );
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_calendar WHERE uid = ?' );
    $stmt->execute( array (
        $calendarId
    ) );
    $calendarRow = $stmt->fetch();
    
    $stmt = $this->pdo->prepare( 'INSERT INTO tx_cal_event (pid,calendar_id, tx_caldav_uid, tx_caldav_data, tstamp, l18n_diffsource, ext_url, image, attachment, attendee) VALUES (?,?,?,?,?,?,?,?,?,?)' );
    $stmt->execute( array (
        $calendarRow ['pid'],
        $calendarId,
        $objectUri,
        $calendarData,
        time(),
        '',
        '',
        '',
        '',
        ''
    ) );
    // TODO: add other data too
    // $extraData['etag'],
    // $extraData['size'],
    // $extraData['componentType'],
    // $extraData['firstOccurence'],
    // $extraData['lastOccurence'],
    // $extraData['uid'],
    
    $uid = $this->pdo->lastInsertId();
    $stmt = $this->pdo->prepare( 'UPDATE tx_cal_calendar SET tstamp = tstamp + 1 WHERE uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        $calendarId
    ) );
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM fe_users where tx_cal_calendar like ? AND deleted = 0' );
    $stmt->execute( array (
        $calendarId
    ) );
    $user = $stmt->fetch();
    
    $this->updateCalEvent( $calendarId, $objectUri, $calendarData, $user );
    $this->clearCache( $calendarRow ['pid'] );
    $this->addChange( $calendarId, $objectUri, 1 );
    
    return '"' . $extraData ['etag'] . '"';
  }

  /**
   * Updates an existing calendarobject, based on it's uri.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * It is possible return an etag from this function, which will be used in
   * the response to this PUT request. Note that the ETag must be surrounded
   * by double-quotes.
   *
   * However, you should only really return this ETag if you don't mangle the
   * calendar-data. If the result of a subsequent GET to this object is not
   * the exact same as this request body, you should omit the ETag.
   *
   * @param mixed $calendarId          
   * @param string $objectUri          
   * @param string $calendarData          
   * @return string|null
   */
  function updateCalendarObject($calendarId, $objectUri, $calendarData) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $extraData = $this->getDenormalizedData( $calendarData );
    
    $stmt = $this->pdo->prepare( 'UPDATE tx_cal_event SET tx_caldav_data = ?, tstamp = ? WHERE calendar_id = ? AND tx_caldav_uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        rtrim( $calendarData ),
        time(),
        $calendarId,
        $objectUri
    ) );
    $stmt = $this->pdo->prepare( 'UPDATE tx_cal_calendar SET tstamp = ? WHERE uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        time(),
        $calendarId
    ) );
    $stmt = $this->pdo->prepare( 'SELECT * FROM fe_users where tx_cal_calendar like ? AND deleted = 0' );
    $stmt->execute( array (
        $calendarId
    ) );
    $user = $stmt->fetch();
    $this->updateCalEvent( $calendarId, $objectUri, rtrim( $calendarData ), $user );
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_event WHERE calendar_id = ?' );
    $stmt->execute( array (
        $calendarId
    ) );
    $calendarRow = $stmt->fetch();
    $this->clearCache( $calendarRow ['pid'] );
    
    $this->addChange( $calendarId, $objectUri, 2 );
    
    return '"' . $extraData ['etag'] . '"';
  }

  /**
   * Deletes an existing calendar object.
   *
   * The object uri is only the basename, or filename and not a full path.
   *
   * @param mixed $calendarId          
   * @param string $objectUri          
   * @return void
   */
  function deleteCalendarObject($calendarId, $objectUri) {

    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_event WHERE calendar_id = ? AND tx_caldav_uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        $calendarId,
        $objectUri
    ) );
    $eventRow = $stmt->fetch();
    
    $stmt = $this->pdo->prepare( 'DELETE FROM tx_cal_event WHERE calendar_id = ? AND tx_caldav_uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        $calendarId,
        $objectUri
    ) );
    $stmt = $this->pdo->prepare( 'UPDATE tx_cal_calendar SET tstamp = tstamp + 1 WHERE uid = ? AND deleted = 0 AND hidden = 0' );
    $stmt->execute( array (
        $calendarId
    ) );
    
    $service = new \TYPO3\CMS\Cal\Service\ICalendarService();
    $service->clearAllImagesAndAttachments( $eventRow ['uid'] );
    $this->clearCache( $eventRow ['pid'] );
    
    $this->addChange( $calendarId, $objectUri, 3 );
  }

  /**
   * Performs a calendar-query on the contents of this calendar.
   *
   * The calendar-query is defined in RFC4791 : CalDAV. Using the
   * calendar-query it is possible for a client to request a specific set of
   * object, based on contents of iCalendar properties, date-ranges and
   * iCalendar component types (VTODO, VEVENT).
   *
   * This method should just return a list of (relative) urls that match this
   * query.
   *
   * The list of filters are specified as an array. The exact array is
   * documented by \Sabre\CalDAV\CalendarQueryParser.
   *
   * Note that it is extremely likely that getCalendarObject for every path
   * returned from this method will be called almost immediately after. You
   * may want to anticipate this to speed up these requests.
   *
   * This method provides a default implementation, which parses *all* the
   * iCalendar objects in the specified calendar.
   *
   * This default may well be good enough for personal use, and calendars
   * that aren't very large. But if you anticipate high usage, big calendars
   * or high loads, you are strongly adviced to optimize certain paths.
   *
   * The best way to do so is override this method and to optimize
   * specifically for 'common filters'.
   *
   * Requests that are extremely common are:
   * * requests for just VEVENTS
   * * requests for just VTODO
   * * requests with a time-range-filter on a VEVENT.
   *
   * ..and combinations of these requests. It may not be worth it to try to
   * handle every possible situation and just rely on the (relatively
   * easy to use) CalendarQueryValidator to handle the rest.
   *
   * Note that especially time-range-filters may be difficult to parse. A
   * time-range filter specified on a VEVENT must for instance also handle
   * recurrence rules correctly.
   * A good example of how to interpret all these filters can also simply
   * be found in \Sabre\CalDAV\CalendarQueryFilter. This class is as correct
   * as possible, so it gives you a good idea on what type of stuff you need
   * to think of.
   *
   * This specific implementation (for the PDO) backend optimizes filters on
   * specific components, and VEVENT time-ranges.
   *
   * @param mixed $calendarId          
   * @param array $filters          
   * @return array
   */
  function calendarQuery($calendarId, array $filters) {
    
    // throw new DAV\Exception\NotImplemented('Not implemented: calendarQuery');
    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $componentType = null;
    $requirePostFilter = true;
    $timeRange = null;
    
    // if no filters were specified, we don't need to filter after a query
    if (! $filters ['prop-filters'] && ! $filters ['comp-filters']) {
      $requirePostFilter = false;
    }
    
    // Figuring out if there's a component filter
    if (count( $filters ['comp-filters'] ) > 0 && ! $filters ['comp-filters'] [0] ['is-not-defined']) {
      $componentType = $filters ['comp-filters'] [0] ['name'];
      
      // Checking if we need post-filters
      if (! $filters ['prop-filters'] && ! $filters ['comp-filters'] [0] ['comp-filters'] && ! $filters ['comp-filters'] [0] ['time-range'] && ! $filters ['comp-filters'] [0] ['prop-filters']) {
        $requirePostFilter = false;
      }
      // There was a time-range filter
      if ($componentType == 'VEVENT' && isset( $filters ['comp-filters'] [0] ['time-range'] )) {
        $timeRange = $filters ['comp-filters'] [0] ['time-range'];
        
        // If start time OR the end time is not specified, we can do a
        // 100% accurate mysql query.
        if (! $filters ['prop-filters'] && ! $filters ['comp-filters'] [0] ['comp-filters'] && ! $filters ['comp-filters'] [0] ['prop-filters'] && (! $timeRange ['start'] || ! $timeRange ['end'])) {
          $requirePostFilter = false;
        }
      }
    }
    
    $values = [ 
        'calendarid' => $calendarId
    ];
    
    // TODO we only support VEVENT. Make VTODO available too
    if ($componentType) {
      $query .= " AND componenttype = :componenttype";
      $values ['componenttype'] = $componentType;
    }
    
    $formattedStarttime = '';
    $formattedEndtime = '';
    
    $now = new \TYPO3\CMS\Cal\Model\CalDate();
    $now->setHour( 0 );
    $now->setMinute( 0 );
    $now->setSecond( 0 );
    
    $then = new \TYPO3\CMS\Cal\Model\CalDate();
    $then->setHour( 0 );
    $then->setMinute( 0 );
    $then->setSecond( 0 );
    
    $then->setYear( $now->getYear() + 50 ); // all events for the next 50 years
    $confArr = unserialize( $GLOBALS ['TYPO3_CONF_VARS'] ['EXT'] ['extConf'] ['caldav'] );
    $archiveDays = intval( $confArr ['archiveDays'] );
    if ($archiveDays < 1) {
      $archiveDays = 7;
    }
    $now->addSeconds( - 86400 * $archiveDays ); // include the last x days
    
    $formattedStarttime = $now->format( '%Y%m%d' );
    $formattedEndtime = $then->format( '%Y%m%d' );
    
    if ($timeRange && $timeRange ['start']) {
      $query .= " AND lastoccurence > :startdate";
      $values ['startdate'] = $timeRange ['start']->getTimeStamp();
    }
    
    if ($timeRange && $timeRange ['end']) {
      $query .= " AND firstoccurence < :enddate";
      $values ['enddate'] = $timeRange ['end']->getTimeStamp();
    }
    
    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_event WHERE calendar_id = ? AND deleted = 0 AND hidden = 0 AND ((tx_cal_event.start_date>=? AND tx_cal_event.start_date<=?) OR (tx_cal_event.end_date<=? AND tx_cal_event.end_date>=?) OR (tx_cal_event.end_date>=? AND tx_cal_event.start_date<=?) or (start_date<=? AND (freq IN ("day", "week", "month", "year") AND (until>=? OR until=0))) OR (tx_cal_event.rdate AND tx_cal_event.rdate_type IN ("date_time", "date", "period")))' );
    $stmt->execute( array (
        $calendarId,
        $formattedStarttime,
        $formattedEndtime,
        $formattedEndtime,
        $formattedStarttime,
        $formattedEndtime,
        $formattedStarttime,
        $formattedEndtime,
        $formattedStarttime
    ) );
    
    $result = [ ];
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      if ($requirePostFilter) {
        if (! $this->validateFilterForObject( $row, $filters )) {
          continue;
        }
      }
      $result [] = $row ['tx_caldav_uid'];
    }
    
    return $result;
  }

  /**
   * This method validates if a filter (as passed to calendarQuery) matches
   * the given object.
   *
   * @param array $object          
   * @param array $filters          
   * @return bool
   */
  protected function validateFilterForObject(array $object, array $filters) {
    
    // Unfortunately, setting the 'calendardata' here is optional. If
    // it was excluded, we actually need another call to get this as
    // well.
    if (! isset( $object ['tx_caldav_data'] )) {
      $object = $this->getCalendarObject( $object ['calendar_id'], $object ['tx_caldav_uid'] );
    }
    
    $vObject = VObject\Reader::read( $object ['tx_caldav_data'] );
    
    $validator = new CalDAV\CalendarQueryValidator();
    $result = $validator->validate( $vObject, $filters );
    
    // Destroy circular references so PHP will GC the object.
    $vObject->destroy();
    
    return $result;
  }

  /**
   * Searches through all of a users calendars and calendar objects to find
   * an object with a specific UID.
   *
   * This method should return the path to this object, relative to the
   * calendar home, so this path usually only contains two parts:
   *
   * calendarpath/objectpath.ics
   *
   * If the uid is not found, return null.
   *
   * This method should only consider * objects that the principal owns, so
   * any calendars owned by other principals that also appear in this
   * collection should be ignored.
   *
   * @param string $principalUri          
   * @param string $uid          
   * @return string|null
   */
  function getCalendarObjectByUID($principalUri, $uid) {

    throw new DAV\Exception\NotImplemented( 'Not implemented' );
    $query = <<<SQL
SELECT
    calendar_instances.uri AS calendaruri, calendarobjects.uri as objecturi
FROM
    $this->calendarObjectTableName AS calendarobjects
LEFT JOIN
    $this->calendarInstancesTableName AS calendar_instances
    ON calendarobjects.calendarid = calendar_instances.calendarid
WHERE
    calendar_instances.principaluri = ?
    AND
    calendarobjects.uid = ?
SQL;
    
    $stmt = $this->pdo->prepare( $query );
    $stmt->execute( [ 
        $principalUri,
        $uid
    ] );
    
    if ($row = $stmt->fetch( \PDO::FETCH_ASSOC )) {
      return $row ['calendaruri'] . '/' . $row ['objecturi'];
    }
  }

  /**
   * The getChanges method returns all the changes that have happened, since
   * the specified syncToken in the specified calendar.
   *
   * This function should return an array, such as the following:
   *
   * [
   * 'syncToken' => 'The current synctoken',
   * 'added' => [
   * 'new.txt',
   * ],
   * 'modified' => [
   * 'modified.txt',
   * ],
   * 'deleted' => [
   * 'foo.php.bak',
   * 'old.txt'
   * ]
   * ];
   *
   * The returned syncToken property should reflect the *current* syncToken
   * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
   * property this is needed here too, to ensure the operation is atomic.
   *
   * If the $syncToken argument is specified as null, this is an initial
   * sync, and all members should be reported.
   *
   * The modified property is an array of nodenames that have changed since
   * the last token.
   *
   * The deleted property is an array with nodenames, that have been deleted
   * from collection.
   *
   * The $syncLevel argument is basically the 'depth' of the report. If it's
   * 1, you only have to report changes that happened only directly in
   * immediate descendants. If it's 2, it should also include changes from
   * the nodes below the child collections. (grandchildren)
   *
   * The $limit argument allows a client to specify how many results should
   * be returned at most. If the limit is not specified, it should be treated
   * as infinite.
   *
   * If the limit (infinite or not) is higher than you're willing to return,
   * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
   *
   * If the syncToken is expired (due to data cleanup) or unknown, you must
   * return null.
   *
   * The limit is 'suggestive'. You are free to ignore it.
   *
   * @param mixed $calendarId          
   * @param string $syncToken          
   * @param int $syncLevel          
   * @param int $limit          
   * @return array
   */
  function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: getChangesForCalendar' );
    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    // Current synctoken
    $stmt = $this->pdo->prepare( 'SELECT synctoken FROM ' . $this->calendarTableName . ' WHERE id = ?' );
    $stmt->execute( [ 
        $calendarId
    ] );
    $currentToken = $stmt->fetchColumn( 0 );
    
    if (is_null( $currentToken ))
      return null;
    
    $result = [ 
        'syncToken' => $currentToken,
        'added' => [ ],
        'modified' => [ ],
        'deleted' => [ ]
    ];
    
    if ($syncToken) {
      
      $query = "SELECT uri, operation FROM " . $this->calendarChangesTableName . " WHERE synctoken >= ? AND synctoken < ? AND calendarid = ? ORDER BY synctoken";
      if ($limit > 0)
        $query .= " LIMIT " . ( int ) $limit;
        
        // Fetching all changes
      $stmt = $this->pdo->prepare( $query );
      $stmt->execute( [ 
          $syncToken,
          $currentToken,
          $calendarId
      ] );
      
      $changes = [ ];
      
      // This loop ensures that any duplicates are overwritten, only the
      // last change on a node is relevant.
      while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
        
        $changes [$row ['uri']] = $row ['operation'];
      }
      
      foreach ( $changes as $uri => $operation ) {
        
        switch ($operation) {
          case 1 :
            $result ['added'] [] = $uri;
            break;
          case 2 :
            $result ['modified'] [] = $uri;
            break;
          case 3 :
            $result ['deleted'] [] = $uri;
            break;
        }
      }
    } else {
      // No synctoken supplied, this is the initial sync.
      $query = "SELECT uri FROM " . $this->calendarObjectTableName . " WHERE calendarid = ?";
      $stmt = $this->pdo->prepare( $query );
      $stmt->execute( [ 
          $calendarId
      ] );
      
      $result ['added'] = $stmt->fetchAll( \PDO::FETCH_COLUMN );
    }
    return $result;
  }

  /**
   * Adds a change record to the calendarchanges table.
   *
   * @param mixed $calendarId          
   * @param string $objectUri          
   * @param int $operation
   *          1 = add, 2 = modify, 3 = delete.
   * @return void
   */
  protected function addChange($calendarId, $objectUri, $operation) {

    return;
    // TODO What should we do here?
    $stmt = $this->pdo->prepare( 'INSERT INTO ' . $this->calendarChangesTableName . ' (uri, synctoken, calendarid, operation) SELECT ?, synctoken, ?, ? FROM ' . $this->calendarTableName . ' WHERE id = ?' );
    $stmt->execute( [ 
        $objectUri,
        $calendarId,
        $operation,
        $calendarId
    ] );
    $stmt = $this->pdo->prepare( 'UPDATE ' . $this->calendarTableName . ' SET synctoken = synctoken + 1 WHERE id = ?' );
    $stmt->execute( [ 
        $calendarId
    ] );
  }

  /**
   * Parses some information from calendar objects, used for optimized
   * calendar-queries.
   *
   * Returns an array with the following keys:
   * * etag - An md5 checksum of the object without the quotes.
   * * size - Size of the object in bytes
   * * componentType - VEVENT, VTODO or VJOURNAL
   * * firstOccurence
   * * lastOccurence
   * * uid - value of the UID property
   *
   * @param string $calendarData          
   * @return array
   */
  protected function getDenormalizedData($calendarData) {

    $vObject = VObject\Reader::read( $calendarData );
    $componentType = null;
    $component = null;
    $firstOccurence = null;
    $lastOccurence = null;
    $uid = null;
    foreach ( $vObject->getComponents() as $component ) {
      if ($component->name !== 'VTIMEZONE') {
        $componentType = $component->name;
        $uid = ( string ) $component->UID;
        break;
      }
    }
    if (! $componentType) {
      throw new \Sabre\DAV\Exception\BadRequest( 'Calendar objects must have a VJOURNAL, VEVENT or VTODO component' );
    }
    if ($componentType === 'VEVENT') {
      $firstOccurence = $component->DTSTART->getDateTime()->getTimeStamp();
      // Finding the last occurence is a bit harder
      if (! isset( $component->RRULE )) {
        if (isset( $component->DTEND )) {
          $lastOccurence = $component->DTEND->getDateTime()->getTimeStamp();
        } elseif (isset( $component->DURATION )) {
          $endDate = clone $component->DTSTART->getDateTime();
          $endDate = $endDate->add( VObject\DateTimeParser::parse( $component->DURATION->getValue() ) );
          $lastOccurence = $endDate->getTimeStamp();
        } elseif (! $component->DTSTART->hasTime()) {
          $endDate = clone $component->DTSTART->getDateTime();
          $endDate = $endDate->modify( '+1 day' );
          $lastOccurence = $endDate->getTimeStamp();
        } else {
          $lastOccurence = $firstOccurence;
        }
      } else {
        $it = new VObject\Recur\EventIterator( $vObject, ( string ) $component->UID );
        $maxDate = new \DateTime( self::MAX_DATE );
        if ($it->isInfinite()) {
          $lastOccurence = $maxDate->getTimeStamp();
        } else {
          $end = $it->getDtEnd();
          while ( $it->valid() && $end < $maxDate ) {
            $end = $it->getDtEnd();
            $it->next();
          }
          $lastOccurence = $end->getTimeStamp();
        }
      }
      
      // Ensure Occurence values are positive
      if ($firstOccurence < 0)
        $firstOccurence = 0;
      if ($lastOccurence < 0)
        $lastOccurence = 0;
    }
    
    // Destroy circular references to PHP will GC the object.
    $vObject->destroy();
    
    return [ 
        'etag' => md5( $calendarData ),
        'size' => strlen( $calendarData ),
        'componentType' => $componentType,
        'firstOccurence' => $firstOccurence,
        'lastOccurence' => $lastOccurence,
        'uid' => $uid
    ];
  }

  /**
   * Returns a list of subscriptions for a principal.
   *
   * Every subscription is an array with the following keys:
   * * id, a unique id that will be used by other functions to modify the
   * subscription. This can be the same as the uri or a database key.
   * * uri. This is just the 'base uri' or 'filename' of the subscription.
   * * principaluri. The owner of the subscription. Almost always the same as
   * principalUri passed to this method.
   * * source. Url to the actual feed
   *
   * Furthermore, all the subscription info must be returned too:
   *
   * 1. {DAV:}displayname
   * 2. {http://apple.com/ns/ical/}refreshrate
   * 3. {http://calendarserver.org/ns/}subscribed-strip-todos (omit if todos
   * should not be stripped).
   * 4. {http://calendarserver.org/ns/}subscribed-strip-alarms (omit if alarms
   * should not be stripped).
   * 5. {http://calendarserver.org/ns/}subscribed-strip-attachments (omit if
   * attachments should not be stripped).
   * 7. {http://apple.com/ns/ical/}calendar-color
   * 8. {http://apple.com/ns/ical/}calendar-order
   * 9. {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
   * (should just be an instance of
   * Sabre\CalDAV\Property\SupportedCalendarComponentSet, with a bunch of
   * default components).
   *
   * @param string $principalUri          
   * @return array
   */
  function getSubscriptionsForUser($principalUri) {

    return [ ];
    // TODO implement me
    $fields = array_values( $this->subscriptionPropertyMap );
    $fields [] = 'id';
    $fields [] = 'uri';
    $fields [] = 'source';
    $fields [] = 'principaluri';
    $fields [] = 'lastmodified';
    
    // Making fields a comma-delimited list
    $fields = implode( ', ', $fields );
    $stmt = $this->pdo->prepare( "SELECT " . $fields . " FROM " . $this->calendarSubscriptionsTableName . " WHERE principaluri = ? ORDER BY calendarorder ASC" );
    $stmt->execute( [ 
        $principalUri
    ] );
    
    $subscriptions = [ ];
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      
      $subscription = [ 
          'id' => $row ['id'],
          'uri' => $row ['uri'],
          'principaluri' => $row ['principaluri'],
          'source' => $row ['source'],
          'lastmodified' => $row ['lastmodified'],
          
          '{' . CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new CalDAV\Xml\Property\SupportedCalendarComponentSet( [ 
              'VTODO',
              'VEVENT'
          ] )
      ];
      
      foreach ( $this->subscriptionPropertyMap as $xmlName => $dbName ) {
        if (! is_null( $row [$dbName] )) {
          $subscription [$xmlName] = $row [$dbName];
        }
      }
      
      $subscriptions [] = $subscription;
    }
    
    return $subscriptions;
  }

  /**
   * Creates a new subscription for a principal.
   *
   * If the creation was a success, an id must be returned that can be used to reference
   * this subscription in other methods, such as updateSubscription.
   *
   * @param string $principalUri          
   * @param string $uri          
   * @param array $properties          
   * @return mixed
   */
  function createSubscription($principalUri, $uri, array $properties) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: createSubscription' );
    $fieldNames = [ 
        'principaluri',
        'uri',
        'source',
        'lastmodified'
    ];
    
    if (! isset( $properties ['{http://calendarserver.org/ns/}source'] )) {
      throw new Forbidden( 'The {http://calendarserver.org/ns/}source property is required when creating subscriptions' );
    }
    
    $values = [ 
        ':principaluri' => $principalUri,
        ':uri' => $uri,
        ':source' => $properties ['{http://calendarserver.org/ns/}source']->getHref(),
        ':lastmodified' => time()
    ];
    
    foreach ( $this->subscriptionPropertyMap as $xmlName => $dbName ) {
      if (isset( $properties [$xmlName] )) {
        
        $values [':' . $dbName] = $properties [$xmlName];
        $fieldNames [] = $dbName;
      }
    }
    
    $stmt = $this->pdo->prepare( "INSERT INTO " . $this->calendarSubscriptionsTableName . " (" . implode( ', ', $fieldNames ) . ") VALUES (" . implode( ', ', array_keys( $values ) ) . ")" );
    $stmt->execute( $values );
    
    return $this->pdo->lastInsertId( $this->calendarSubscriptionsTableName . '_id_seq' );
  }

  /**
   * Updates a subscription
   *
   * The list of mutations is stored in a Sabre\DAV\PropPatch object.
   * To do the actual updates, you must tell this object which properties
   * you're going to process with the handle() method.
   *
   * Calling the handle method is like telling the PropPatch object "I
   * promise I can handle updating this property".
   *
   * Read the PropPatch documentation for more info and examples.
   *
   * @param mixed $subscriptionId          
   * @param \Sabre\DAV\PropPatch $propPatch          
   * @return void
   */
  function updateSubscription($subscriptionId, DAV\PropPatch $propPatch) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: updateSubscription' );
    $supportedProperties = array_keys( $this->subscriptionPropertyMap );
    $supportedProperties [] = '{http://calendarserver.org/ns/}source';
    
    $propPatch->handle( $supportedProperties, function ($mutations) use ($subscriptionId) {
      
      $newValues = [ ];
      
      foreach ( $mutations as $propertyName => $propertyValue ) {
        
        if ($propertyName === '{http://calendarserver.org/ns/}source') {
          $newValues ['source'] = $propertyValue->getHref();
        } else {
          $fieldName = $this->subscriptionPropertyMap [$propertyName];
          $newValues [$fieldName] = $propertyValue;
        }
      }
      
      // Now we're generating the sql query.
      $valuesSql = [ ];
      foreach ( $newValues as $fieldName => $value ) {
        $valuesSql [] = $fieldName . ' = ?';
      }
      
      $stmt = $this->pdo->prepare( "UPDATE " . $this->calendarSubscriptionsTableName . " SET " . implode( ', ', $valuesSql ) . ", lastmodified = ? WHERE id = ?" );
      $newValues ['lastmodified'] = time();
      $newValues ['id'] = $subscriptionId;
      $stmt->execute( array_values( $newValues ) );
      
      return true;
    } );
  }

  /**
   * Deletes a subscription
   *
   * @param mixed $subscriptionId          
   * @return void
   */
  function deleteSubscription($subscriptionId) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: deleteSubscription' );
    $stmt = $this->pdo->prepare( 'DELETE FROM ' . $this->calendarSubscriptionsTableName . ' WHERE id = ?' );
    $stmt->execute( [ 
        $subscriptionId
    ] );
  }

  /**
   * Returns a single scheduling object.
   *
   * The returned array should contain the following elements:
   * * uri - A unique basename for the object. This will be used to
   * construct a full uri.
   * * calendardata - The iCalendar object
   * * lastmodified - The last modification date. Can be an int for a unix
   * timestamp, or a PHP DateTime object.
   * * etag - A unique token that must change if the object changed.
   * * size - The size of the object, in bytes.
   *
   * @param string $principalUri          
   * @param string $objectUri          
   * @return array
   */
  function getSchedulingObject($principalUri, $objectUri) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: getSchedulingObject' );
    $stmt = $this->pdo->prepare( 'SELECT uri, calendardata, lastmodified, etag, size FROM ' . $this->schedulingObjectTableName . ' WHERE principaluri = ? AND uri = ?' );
    $stmt->execute( [ 
        $principalUri,
        $objectUri
    ] );
    $row = $stmt->fetch( \PDO::FETCH_ASSOC );
    
    if (! $row)
      return null;
    
    return [ 
        'uri' => $row ['uri'],
        'calendardata' => $row ['calendardata'],
        'lastmodified' => $row ['lastmodified'],
        'etag' => '"' . $row ['etag'] . '"',
        'size' => ( int ) $row ['size']
    ];
  }

  /**
   * Returns all scheduling objects for the inbox collection.
   *
   * These objects should be returned as an array. Every item in the array
   * should follow the same structure as returned from getSchedulingObject.
   *
   * The main difference is that 'calendardata' is optional.
   *
   * @param string $principalUri          
   * @return array
   */
  function getSchedulingObjects($principalUri) {

    return [ ];
    // TODO: implement me
    $stmt = $this->pdo->prepare( 'SELECT id, calendardata, uri, lastmodified, etag, size FROM ' . $this->schedulingObjectTableName . ' WHERE principaluri = ?' );
    $stmt->execute( [ 
        $principalUri
    ] );
    
    $result = [ ];
    foreach ( $stmt->fetchAll( \PDO::FETCH_ASSOC ) as $row ) {
      $result [] = [ 
          'calendardata' => $row ['calendardata'],
          'uri' => $row ['uri'],
          'lastmodified' => $row ['lastmodified'],
          'etag' => '"' . $row ['etag'] . '"',
          'size' => ( int ) $row ['size']
      ];
    }
    
    return $result;
  }

  /**
   * Deletes a scheduling object
   *
   * @param string $principalUri          
   * @param string $objectUri          
   * @return void
   */
  function deleteSchedulingObject($principalUri, $objectUri) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: deleteSchedulingObject' );
    $stmt = $this->pdo->prepare( 'DELETE FROM ' . $this->schedulingObjectTableName . ' WHERE principaluri = ? AND uri = ?' );
    $stmt->execute( [ 
        $principalUri,
        $objectUri
    ] );
  }

  /**
   * Creates a new scheduling object.
   * This should land in a users' inbox.
   *
   * @param string $principalUri          
   * @param string $objectUri          
   * @param string $objectData          
   * @return void
   */
  function createSchedulingObject($principalUri, $objectUri, $objectData) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: createSchedulingObject' );
    $stmt = $this->pdo->prepare( 'INSERT INTO ' . $this->schedulingObjectTableName . ' (principaluri, calendardata, uri, lastmodified, etag, size) VALUES (?, ?, ?, ?, ?, ?)' );
    $stmt->execute( [ 
        $principalUri,
        $objectData,
        $objectUri,
        time(),
        md5( $objectData ),
        strlen( $objectData )
    ] );
  }

  /**
   * Updates the list of shares.
   *
   * @param mixed $calendarId          
   * @param \Sabre\DAV\Xml\Element\Sharee[] $sharees          
   * @return void
   */
  function updateInvites($calendarId, array $sharees) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: updateInvites' );
    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to $calendarId is expected to be an array with a calendarId and an instanceId' );
    }
    $currentInvites = $this->getInvites( $calendarId );
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $removeStmt = $this->pdo->prepare( "DELETE FROM " . $this->calendarInstancesTableName . " WHERE calendarid = ? AND share_href = ? AND access IN (2,3)" );
    $updateStmt = $this->pdo->prepare( "UPDATE " . $this->calendarInstancesTableName . " SET access = ?, share_displayname = ?, share_invitestatus = ? WHERE calendarid = ? AND share_href = ?" );
    
    $insertStmt = $this->pdo->prepare( '
INSERT INTO ' . $this->calendarInstancesTableName . '
    (
        calendarid,
        principaluri,
        access,
        displayname,
        uri,
        description,
        calendarorder,
        calendarcolor,
        timezone,
        transparent,
        share_href,
        share_displayname,
        share_invitestatus
    )
    SELECT
        ?,
        ?,
        ?,
        displayname,
        ?,
        description,
        calendarorder,
        calendarcolor,
        timezone,
        1,
        ?,
        ?,
        ?
    FROM ' . $this->calendarInstancesTableName . ' WHERE id = ?' );
    
    foreach ( $sharees as $sharee ) {
      
      if ($sharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_NOACCESS) {
        // if access was set no NOACCESS, it means access for an
        // existing sharee was removed.
        $removeStmt->execute( [ 
            $calendarId,
            $sharee->href
        ] );
        continue;
      }
      
      if (is_null( $sharee->principal )) {
        // If the server could not determine the principal automatically,
        // we will mark the invite status as invalid.
        $sharee->inviteStatus = \Sabre\DAV\Sharing\Plugin::INVITE_INVALID;
      } else {
        // Because sabre/dav does not yet have an invitation system,
        // every invite is automatically accepted for now.
        $sharee->inviteStatus = \Sabre\DAV\Sharing\Plugin::INVITE_ACCEPTED;
      }
      
      foreach ( $currentInvites as $oldSharee ) {
        
        if ($oldSharee->href === $sharee->href) {
          // This is an update
          $sharee->properties = array_merge( $oldSharee->properties, $sharee->properties );
          $updateStmt->execute( [ 
              $sharee->access,
              isset( $sharee->properties ['{DAV:}displayname'] ) ? $sharee->properties ['{DAV:}displayname'] : null,
              $sharee->inviteStatus ?: $oldSharee->inviteStatus,
              $calendarId,
              $sharee->href
          ] );
          continue 2;
        }
      }
      // If we got here, it means it was a new sharee
      $insertStmt->execute( [ 
          $calendarId,
          $sharee->principal,
          $sharee->access,
          \Sabre\DAV\UUIDUtil::getUUID(),
          $sharee->href,
          isset( $sharee->properties ['{DAV:}displayname'] ) ? $sharee->properties ['{DAV:}displayname'] : null,
          $sharee->inviteStatus ?: \Sabre\DAV\Sharing\Plugin::INVITE_NORESPONSE,
          $instanceId
      ] );
    }
  }

  /**
   * Returns the list of people whom a calendar is shared with.
   *
   * Every item in the returned list must be a Sharee object with at
   * least the following properties set:
   * $href
   * $shareAccess
   * $inviteStatus
   *
   * and optionally:
   * $properties
   *
   * @param mixed $calendarId          
   * @return \Sabre\DAV\Xml\Element\Sharee[]
   */
  function getInvites($calendarId) {

    return [ ];
    // TODO: implement this
    
    if (! is_array( $calendarId )) {
      throw new \InvalidArgumentException( 'The value passed to getInvites() is expected to be an array with a calendarId and an instanceId' );
    }
    list ( $calendarId, $instanceId ) = $calendarId;
    
    $query = <<<SQL
SELECT
    principaluri,
    access,
    share_href,
    share_displayname,
    share_invitestatus
FROM {$this->calendarInstancesTableName}
WHERE
    calendarid = ?
SQL;
    
    $stmt = $this->pdo->prepare( $query );
    $stmt->execute( [ 
        $calendarId
    ] );
    
    $result = [ ];
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      
      $result [] = new Sharee( [ 
          'href' => isset( $row ['share_href'] ) ? $row ['share_href'] : \Sabre\HTTP\encodePath( $row ['principaluri'] ),
          'access' => ( int ) $row ['access'],
          // / Everyone is always immediately accepted, for now.
          'inviteStatus' => ( int ) $row ['share_invitestatus'],
          'properties' => ! empty( $row ['share_displayname'] ) ? [ 
              '{DAV:}displayname' => $row ['share_displayname']
          ] : [ ],
          'principal' => $row ['principaluri']
      ] );
    }
    return $result;
  }

  /**
   * Publishes a calendar
   *
   * @param mixed $calendarId          
   * @param bool $value          
   * @return void
   */
  function setPublishStatus($calendarId, $value) {

    throw new DAV\Exception\NotImplemented( 'Not implemented: setPublishStatus' );
  }

  private function updateCalEvent($calendarId, $objectUri, $calendarData, $user) {

    $stmt = $this->pdo->prepare( 'SELECT * FROM tx_cal_calendar WHERE uid = ?' );
    $stmt->execute( array (
        $calendarId
    ) );
    $calendarRow = $stmt->fetch();
    
    $service = new \TYPO3\CMS\Cal\Service\ICalendarService();
    $components = $service->getiCalendarFromIcsFile( $calendarData );
    
    foreach ( $components->_components as $component ) {
      if ($component->getType() == 'vEvent') {
        $stmt = $this->pdo->prepare( 'UPDATE tx_cal_event SET icsUid = ?, type = ? WHERE tx_caldav_uid = ?' );
        $stmt->execute( array (
            $component->getAttribute( 'UID' ),
            0,
            $objectUri
        ) );
      } else if ($component->getType() == 'vTodo') {
        $stmt = $this->pdo->prepare( 'UPDATE tx_cal_event SET icsUid = ?, type = ? WHERE tx_caldav_uid = ?' );
        $stmt->execute( array (
            $component->getAttribute( 'UID' ),
            4,
            $objectUri
        ) );
      }
    }
    $service->insertCalEventsIntoDB( $components->_components, $calendarId, $calendarRow ['pid'], $user ['uid'], 0, FALSE );
    $this->clearCache( $calendarRow ['pid'] );
  }

  private function clearCache($pid) {

    return;
    $pageTSConf = \TYPO3\CMS\Backend\Utility\BackendUtility::getPagesTSconfig( $pid );
    $pageIDForPlugin = $pid;
    
    if ($pageTSConf ['TCEMAIN.'] ['clearCacheCmd']) {
      $pageIDForPlugin = $pageTSConf ['TCEMAIN.'] ['clearCacheCmd'];
    }
    
    /** @var $tcemain \TYPO3\CMS\Core\DataHandling\DataHandler */
    $tcemain = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance( 'TYPO3\\CMS\\Core\\DataHandling\\DataHandler' );
    $tcemain->stripslashes_values = 0;
    $tcemain->start( array (), array (), new \TYPO3\CMS\Caldav\Backend\FakeBeUser( $pid ) );
    $tcemain->clear_cacheCmd( $pageIDForPlugin ); // ID of the page for which to clear the cache
  }
}