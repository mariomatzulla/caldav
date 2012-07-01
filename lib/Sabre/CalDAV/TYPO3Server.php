<?php

/**
 * CalDAV server
 *
 * This script is a convenience script. It quickly sets up a WebDAV server
 * with caldav and ACL support, and it creates the root 'principals' and
 * 'calendars' collections.
 *
 * Note that if you plan to do anything moderately complex, you are advised to 
 * not subclass this server, but use Sabre_DAV_Server directly instead. This 
 * class is nothing more than an 'easy setup'.
 * 
 * @package Sabre
 * @subpackage CalDAV
 * @copyright Copyright (C) 2012 Mario Matzulla. All rights reserved.
 * @author Mario Matzulla (http://www.matzullas.de)
 * @license http://code.google.com/p/sabredav/wiki/License Modified BSD License
 */
class Sabre_CalDAV_TYPO3Server extends Sabre_DAV_Server {

    /**
     * The authentication realm
     *
     * Note that if this changes, the hashes in the auth backend must also 
     * be recalculated. 
     * 
     * @var string
     */
    public $authRealm = 'SabreDAV';

    /**
     * Sets up the object. A PDO object must be passed to setup all the backends.
     * 
     * @param PDO $pdo 
     */
    public function __construct(PDO $pdo) {

        /* Backends */
        $authBackend = new Sabre_DAV_Auth_Backend_TYPO3($pdo);
        $calendarBackend = new Sabre_CalDAV_Backend_TYPO3($pdo);
        $principalBackend = new Sabre_DAVACL_PrincipalBackend_TYPO3($pdo);
        
        /* Directory structure */
        $tree = array(
        		new Sabre_CalDAV_Principal_Collection($principalBackend),
        		new Sabre_CalDAV_CalendarRootNode($principalBackend, $calendarBackend),
        );
        
        /* Initializing server */
        parent::__construct($tree);
        
        /* Server Plugins */
        $authPlugin = new Sabre_DAV_Auth_Plugin($authBackend,$this->authRealm);
        $this->addPlugin($authPlugin);
        
        $aclPlugin = new Sabre_DAVACL_Plugin();
        $this->addPlugin($aclPlugin);
        
        $caldavPlugin = new Sabre_CalDAV_Plugin();
        $this->addPlugin($caldavPlugin);

    }

}
