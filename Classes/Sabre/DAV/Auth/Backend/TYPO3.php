<?php

namespace Sabre\DAV\Auth\Backend;

/**
 * This is an authentication backend that uses a database to manage passwords.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class TYPO3 extends AbstractBasic {

  /**
   * Reference to PDO connection
   *
   * @var PDO
   */
  protected $pdo;

  private $username;
  
  public $loginType = 'FE';

  /**
   * PDO table name we'll be using
   *
   * @var string
   */
  public $tableName = 'fe_users';

  /**
   * Creates the backend object.
   *
   * If the filename argument is passed in, it will parse out the specified file fist.
   *
   * @param \PDO $pdo          
   */
  function __construct(\PDO $pdo) {

    $this->pdo = $pdo;
  }

  function getPdo() {

    return $this->pdo;
  }

  /**
   * Validates a username and password
   *
   * If the username and password were correct, this method must return
   * an array with at least a 'uri' key.
   *
   * If the credentials are incorrect, this method must return false.
   *
   * @return bool|array
   */
  protected function validateUserPass($username, $password) {

    $authenticationService = new \TYPO3\CMS\Core\Authentication\AuthenticationService();
    $authenticationService->setLogger( new \Psr\Log\NullLogger() );
    $loginData = [ ];
    $loginData ['status'] = \TYPO3\CMS\Core\Authentication\LoginType::LOGIN;
    $loginData ['uname'] = $username;
    $loginData ['uident_text'] = $password;
    $authInfo = [ ];
    $authInfo ['db_user'] ['table'] = 'fe_users'; // db_groups
    $authInfo ['db_user'] ['username_column'] = 'username';
    $authInfo ['db_user'] ['check_pid_clause'] = 'pid not in (0)';
    $authInfo ['db_user'] ['enable_clause'] = 'deleted = 0';
    $authInfo ['db_user'] ['userid_column'] = 'uid';
    
    
    $pObj = $this;
    $authenticationService->initAuth( 'getUserFe', $loginData, $authInfo, $pObj );
    
    $user = $authenticationService->getUser();
    if($user === FALSE){
      return false;
    }
    //\TYPO3\CMS\Core\Utility\DebugUtility::debug($user);
    if ($authenticationService->authUser( $user ) == 200) {
      $this->username = $username;
      $this->email = $user ['email'];
      $this->calendar_id = $user ['tx_cal_calendar'];
      
      $userData = array (
          'uri' => 'principals/' . $this->username,
          'digestHash' => md5( $this->username . ':' . 'SabreDAV' . ':' . $this->username ),
          'calendar_id' => $this->calendar_id
      );
      
      if ($this->email) {
        $userData ['{http://sabredav.org/ns}email-address'] = $this->email;
      }
      
      return $userData;
    } else {
      return false;
    }
  }

  public $writeAttemptLog = false;

  /**
   * Returns a users' information
   *
   * @param string $realm          
   * @param string $username          
   * @return string
   */
  public function getUserInfo($realm, $username) {

    $stmt = $this->pdo->prepare( 'SELECT username, password, email, tx_cal_calendar FROM fe_users WHERE username = ?' );
    $stmt->execute( array (
        $username
    ) );
    $result = $stmt->fetchAll();
    
    if (! count( $result ))
      return false;
    $user = array (
        'uri' => 'principals/' . $result [0] ['username'],
        'digestHash' => md5( $result [0] ['username'] . ':' . 'SabreDAV' . ':' . $result [0] ['password'] ),
        'calendar_id' => $result [0] ['tx_cal_calendar']
    );
    $this->username = $username;
    if ($result [0] ['email'])
      $user ['{http://sabredav.org/ns}email-address'] = $result [0] ['email'];
    return $user;
  }

  /**
   * Returns a list of all users
   *
   * @return array
   */
  public function getUsers() {

    $result = $this->pdo->query( 'SELECT username, email FROM fe_users WHERE username = \'' . $this->username . '\'' )->fetchAll();
    
    $rv = array ();
    foreach ( $result as $user ) {
      
      $r = array (
          'uri' => 'principals/' . $user ['username']
      );
      if ($user ['email'])
        $r ['{http://sabredav.org/ns}email-address'] = $user ['email'];
      $rv [] = $r;
    }
    
    return $rv;
  }

  /**
   * Authenticates the user based on the current request.
   *
   * If authentication is succesful, true must be returned.
   * If authentication fails, an exception must be thrown.
   *
   * @throws Sabre_DAV_Exception_NotAuthenticated
   * @return bool
   */
  public function authenticate(Sabre_DAV_Server $server, $realm) {

    $auth = new Sabre_HTTP_BasicAuth();
    $auth->setHTTPRequest( $server->httpRequest );
    $auth->setHTTPResponse( $server->httpResponse );
    $auth->setRealm( $realm );
    $userpass = $auth->getUserPass();
    if (! $userpass) {
      $auth->requireLogin();
      throw new Sabre_DAV_Exception_NotAuthenticated( 'No basic authentication headers were found' );
    }
    
    // Authenticates the user
    if (! ($userData = $this->validateUserPass( $userpass [0], $userpass [1] ))) {
      $auth->requireLogin();
      throw new Sabre_DAV_Exception_NotAuthenticated( 'Username or password does not match' );
    }
    if (! isset( $userData ['uri'] )) {
      throw new Sabre_DAV_Exception( 'The returned array from validateUserPass must contain at a uri element' );
    }
    $this->currentUser = $userpass [0];
    return true;
  }
}