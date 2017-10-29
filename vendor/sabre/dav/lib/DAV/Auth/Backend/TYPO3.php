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
    protected function validateUserPass($username, $password){
    	$_GET['logintype'] = 'login';
		$_GET['user'] = $username;
		$_GET['pass'] = $password;
		$_GET['challenge'] = '';
		$confArr = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['caldav']);
		$_GET['pid'] = $confArr['pids'];
		$GLOBALS['TYPO3_CONF_VARS']['FE']['loginSecurityLevel']='normal';		
		//require_once (PATH_tslib.'/class.tslib_eidtools.php');

		$feUserObj = \TYPO3\CMS\Frontend\Utility\EidUtility::initFeUser();

    	if (is_array($feUserObj->user) && $feUserObj->user['uid'] && $feUserObj->user['is_online']){
    		$this->username = $username;
			$this->email = $feUserObj->user['email'];
			$this->calendar_id = $feUserObj->user['tx_cal_calendar'];
	
	        $user = array(
	            'uri' => 'principals/' . $this->username,
	            'digestHash' => md5($this->username . ':' . 'SabreDAV' . ':' . $this->username),
	        	'calendar_id' => $this->calendar_id
	        );
			
	        if ($this->email){
	        	$user['{http://sabredav.org/ns}email-address'] = $this->email;
	        }
	        
	        return $user;
    	} else {
    		return false;
    	}
    }

    /**
     * Returns a users' information 
     * 
     * @param string $realm 
     * @param string $username 
     * @return string 
     */
    public function getUserInfo($realm,$username) {

    	$stmt = $this->pdo->prepare('SELECT username, password, email, tx_cal_calendar FROM fe_users WHERE username = ?');
        $stmt->execute(array($username));
        $result = $stmt->fetchAll();

        if (!count($result)) return false;
        $user = array(
            'uri' => 'principals/' . $result[0]['username'],
            'digestHash' => md5($result[0]['username'] . ':' . 'SabreDAV' . ':' . $result[0]['password']),
        	'calendar_id' => $result[0]['tx_cal_calendar']
        );
		$this->username = $username;
        if ($result[0]['email']) $user['{http://sabredav.org/ns}email-address'] = $result[0]['email'];
        return $user;

    }

    /**
     * Returns a list of all users
     *
     * @return array
     */
    public function getUsers() {

    	$result = $this->pdo->query('SELECT username, email FROM fe_users WHERE username = \''.$this->username.'\'')->fetchAll();
        
        $rv = array();
        foreach($result as $user) {

            $r = array(
                'uri' => 'principals/' . $user['username'],
            );
            if ($user['email']) $r['{http://sabredav.org/ns}email-address'] = $user['email'];
            $rv[] = $r;

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
    public function authenticate(Sabre_DAV_Server $server,$realm) {
		
		$auth = new Sabre_HTTP_BasicAuth();
        $auth->setHTTPRequest($server->httpRequest);
        $auth->setHTTPResponse($server->httpResponse);
        $auth->setRealm($realm);
        $userpass = $auth->getUserPass();
        if (!$userpass) {
            $auth->requireLogin();
            throw new Sabre_DAV_Exception_NotAuthenticated('No basic authentication headers were found');
        }
        
        // Authenticates the user
        if (!($userData = $this->validateUserPass($userpass[0],$userpass[1]))) {
            $auth->requireLogin();
            throw new Sabre_DAV_Exception_NotAuthenticated('Username or password does not match');
        }
        if (!isset($userData['uri'])) {
            throw new Sabre_DAV_Exception('The returned array from validateUserPass must contain at a uri element');
        }
        $this->currentUser = $userpass[0];
        return true;
    }

}