<?php

namespace Sabre\DAVACL\PrincipalBackend;

use Sabre\DAV;
use Sabre\DAV\MkCol;
use Sabre\HTTP\URLUtil;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * PDO principal backend
 *
 * This is a simple principal backend that maps exactly to the users table, as
 * used by Sabre_DAV_Auth_Backend_PDO.
 *
 * It assumes all principals are in a single collection. The default collection
 * is 'principals/', but this can be overriden.
 *
 * @package Sabre
 * @subpackage DAVACL
 * @copyright Copyright (C) 2012-2015 Mario Matzulla. All rights reserved.
 * @author Mario Matzulla (http://www.matzullas.de)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class TYPO3 extends AbstractBackend implements CreatePrincipalSupport {

  /**
   * PDO table name for 'principals'
   *
   * @var string
   */
  public $tableName = 'fe_users';

  /**
   * PDO table name for 'group members'
   *
   * @var string
   */
  public $groupMembersTableName = 'fe_groups';

  /**
   * pdo
   *
   * @var PDO
   */
  protected $pdo;

  /**
   * Sets up the backend.
   *
   * @param PDO $pdo          
   * @param string $tableName          
   */
  function __construct(\PDO $pdo) {

    $this->pdo = $pdo;
  }

  /**
   * Returns a list of principals based on a prefix.
   *
   * This prefix will often contain something like 'principals'. You are only
   * expected to return principals that are in this base path.
   *
   * You are expected to return at least a 'uri' for every user, you can
   * return any additional properties if you wish so. Common properties are:
   * {DAV:}displayname
   * {http://sabredav.org/ns}email-address - This is a custom SabreDAV
   * field that's actualy injected in a number of other properties. If
   * you have an email address, use this property.
   *
   * @param string $prefixPath          
   * @return array
   */
  function getPrincipalsByPrefix($prefixPath) {

    $result = $this->pdo->query( 'SELECT username, email, name FROM `' . $this->tableName . '`' );
    
    $principals = array ();
    
    while ( $row = $result->fetch( \PDO::FETCH_ASSOC ) ) {
      
      // Checking if the principal is in the prefix
      list ( $rowPrefix ) = URLUtil::splitPath( 'principals/' . $row ['username'] );
      if ($rowPrefix !== $prefixPath)
        continue;
      
      $principals [] = array (
          'uri' => 'principals/' . $row ['username'],
          '{DAV:}displayname' => $row ['name'] ? $row ['name'] : basename( 'principals/' . $row ['username'] ),
          '{http://sabredav.org/ns}email-address' => $row ['email']
      );
    }
    
    return $principals;
  }

  /**
   * Returns a specific principal, specified by it's path.
   * The returned structure should be the exact same as from
   * getPrincipalsByPrefix.
   *
   * @param string $path          
   * @return array
   */
  function getPrincipalByPath($path) {

    $pathParts = GeneralUtility::trimExplode( '/', $path );
    $name = $pathParts [1];
    $stmt = $this->pdo->prepare( 'SELECT uid, username, email, name FROM `' . $this->tableName . '` WHERE username = ?' );
    $stmt->execute( array (
        $name
    ) );
    
    $users = array ();
    
    $row = $stmt->fetch( \PDO::FETCH_ASSOC );
    
    if (! $row)
      return;
    $return = array (
        'id' => $row ['uid'],
        'uri' => 'principals/' . $row ['username'],
        '{DAV:}displayname' => $row ['name'] ? $row ['name'] : basename( $row ['username'] ),
        '{http://sabredav.org/ns}email-address' => $row ['email']
    );
    return $return;
  }

  /**
   * Updates one ore more webdav properties on a principal.
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
   * @param string $path          
   * @param DAV\PropPatch $propPatch          
   */
  function updatePrincipal($path, DAV\PropPatch $propPatch) {
    // TODO implement this
    return;
    $propPatch->handle( array_keys( $this->fieldMap ), function ($properties) use ($path) {
      
      $query = "UPDATE " . $this->tableName . " SET ";
      $first = true;
      
      $values = [ ];
      
      foreach ( $properties as $key => $value ) {
        
        $dbField = $this->fieldMap [$key] ['dbField'];
        
        if (! $first) {
          $query .= ', ';
        }
        $first = false;
        $query .= $dbField . ' = :' . $dbField;
        $values [$dbField] = $value;
      }
      
      $query .= " WHERE uri = :uri";
      $values ['uri'] = $path;
      
      $stmt = $this->pdo->prepare( $query );
      $stmt->execute( $values );
      
      return true;
    } );
  }

  /**
   * This method is used to search for principals matching a set of
   * properties.
   *
   * This search is specifically used by RFC3744's principal-property-search
   * REPORT.
   *
   * The actual search should be a unicode-non-case-sensitive search. The
   * keys in searchProperties are the WebDAV property names, while the values
   * are the property values to search on.
   *
   * By default, if multiple properties are submitted to this method, the
   * various properties should be combined with 'AND'. If $test is set to
   * 'anyof', it should be combined using 'OR'.
   *
   * This method should simply return an array with full principal uri's.
   *
   * If somebody attempted to search on a property the backend does not
   * support, you should simply return 0 results.
   *
   * You can also just return 0 results if you choose to not support
   * searching at all, but keep in mind that this may stop certain features
   * from working.
   *
   * @param string $prefixPath          
   * @param array $searchProperties          
   * @param string $test          
   * @return array
   */
  function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
    // TODO implement this
    return [ ];
    if (count( $searchProperties ) == 0)
      return [ ]; // No criteria
    
    $query = 'SELECT uri FROM ' . $this->tableName . ' WHERE ';
    $values = [ ];
    foreach ( $searchProperties as $property => $value ) {
      switch ($property) {
        case '{DAV:}displayname' :
          $column = "displayname";
          break;
        case '{http://sabredav.org/ns}email-address' :
          $column = "email";
          break;
        default :
          // Unsupported property
          return [ ];
      }
      if (count( $values ) > 0)
        $query .= (strcmp( $test, "anyof" ) == 0 ? " OR " : " AND ");
      $query .= 'lower(' . $column . ') LIKE lower(?)';
      $values [] = '%' . $value . '%';
    }
    $stmt = $this->pdo->prepare( $query );
    $stmt->execute( $values );
    
    $principals = [ ];
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      
      // Checking if the principal is in the prefix
      list ( $rowPrefix ) = URLUtil::splitPath( $row ['uri'] );
      if ($rowPrefix !== $prefixPath)
        continue;
      
      $principals [] = $row ['uri'];
    }
    
    return $principals;
  }

  /**
   * Finds a principal by its URI.
   *
   * This method may receive any type of uri, but mailto: addresses will be
   * the most common.
   *
   * Implementation of this API is optional. It is currently used by the
   * CalDAV system to find principals based on their email addresses. If this
   * API is not implemented, some features may not work correctly.
   *
   * This method must return a relative principal path, or null, if the
   * principal was not found or you refuse to find it.
   *
   * @param string $uri          
   * @param string $principalPrefix          
   * @return string
   */
  function findByUri($uri, $principalPrefix) {
    // TODO implement this
    return;
    $value = null;
    $scheme = null;
    list ( $scheme, $value ) = explode( ":", $uri, 2 );
    if (empty( $value ))
      return null;
    
    $uri = null;
    switch ($scheme) {
      case "mailto" :
        $query = 'SELECT uri FROM ' . $this->tableName . ' WHERE lower(email)=lower(?)';
        $stmt = $this->pdo->prepare( $query );
        $stmt->execute( [ 
            $value
        ] );
        
        while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
          // Checking if the principal is in the prefix
          list ( $rowPrefix ) = URLUtil::splitPath( $row ['uri'] );
          if ($rowPrefix !== $principalPrefix)
            continue;
          
          $uri = $row ['uri'];
          break; // Stop on first match
        }
        break;
      default :
        // unsupported uri scheme
        return null;
    }
    return $uri;
  }

  /**
   * Returns the list of members for a group-principal
   *
   * @param string $principal          
   * @return array
   */
  function getGroupMemberSet($principal) {

    $principal = $this->getPrincipalByPath( $principal );
    if (! $principal)
      throw new Sabre_DAV_Exception( 'Principal not found' );
    
    $stmt = $this->pdo->prepare( "SELECT uid, tx_cal_calendar FROM fe_users WHERE uid = ? AND deleted=0" );
    $stmt->execute( array (
        $principal ['id']
    ) );
    
    $calendarIds = '';
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      $calendarIds = $row ['tx_cal_calendar'];
    }
    
    $stmt = $this->pdo->prepare( "SELECT * FROM tx_cal_calendar WHERE uid in (?)" );
    $stmt->execute( array (
        $calendarIds
    ) );
    
    $result = array ();
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      $result [] = $principal ['uri'] . '/' . $row ['title'];
    }
    return $result;
  }

  /**
   * Returns the list of members for a group-principal
   *
   * @param string $principal          
   * @return array
   */
  function getGroupMembership($principal) {

    $principal = $this->getPrincipalByPath( $principal );
    if (! $principal)
      throw new Sabre_DAV_Exception( 'Principal not found' );
    
    $stmt = $this->pdo->prepare( "SELECT uid, tx_cal_calendar FROM fe_users WHERE uid = ? AND deleted=0" );
    $stmt->execute( array (
        $principal ['id']
    ) );
    
    $calendarIds = '';
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      $calendarIds = $row ['tx_cal_calendar'];
    }
    
    $stmt = $this->pdo->prepare( "SELECT * FROM tx_cal_calendar WHERE uid in (?)" );
    $stmt->execute( array (
        $calendarIds
    ) );
    
    $result = array ();
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      $result [] = $principal ['uri'];
    }
    return $result;
  }

  /**
   * Updates the list of group members for a group principal.
   *
   * The principals should be passed as a list of uri's.
   *
   * @param string $principal          
   * @param array $members          
   * @return void
   */
  public function setGroupMemberSet($principal, array $members) {
    // TODO: implement this
    return;
    // Grabbing the list of principal id's.
    $stmt = $this->pdo->prepare( 'SELECT id, uri FROM `' . $this->tableName . '` WHERE uri IN (? ' . str_repeat( ', ? ', count( $members ) ) . ');' );
    $stmt->execute( array_merge( array (
        $principal
    ), $members ) );
    
    $memberIds = array ();
    $principalId = null;
    
    while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
      if ($row ['uri'] == $principal) {
        $principalId = $row ['id'];
      } else {
        $memberIds [] = $row ['id'];
      }
    }
    if (! $principalId)
      throw new Sabre_DAV_Exception( 'Principal not found' );
      
      // Wiping out old members
    $stmt = $this->pdo->prepare( 'DELETE FROM `' . $this->groupMembersTableName . '` WHERE principal_id = ?;' );
    $stmt->execute( array (
        $principalId
    ) );
    
    foreach ( $memberIds as $memberId ) {
      
      $stmt = $this->pdo->prepare( 'INSERT INTO `' . $this->groupMembersTableName . '` (principal_id, member_id) VALUES (?, ?);' );
      $stmt->execute( array (
          $principalId,
          $memberId
      ) );
    }
  }

  /**
   * Creates a new principal.
   *
   * This method receives a full path for the new principal. The mkCol object
   * contains any additional webdav properties specified during the creation
   * of the principal.
   *
   * @param string $path          
   * @param MkCol $mkCol          
   * @return void
   */
  function createPrincipal($path, MkCol $mkCol) {
    // TODO: implement this
    return;
    $stmt = $this->pdo->prepare( 'INSERT INTO ' . $this->tableName . ' (uri) VALUES (?)' );
    $stmt->execute( [ 
        $path
    ] );
    $this->updatePrincipal( $path, $mkCol );
  }
}
