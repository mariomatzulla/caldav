<?php

namespace TYPO3\CMS\Caldav\Backend\Modul;

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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Module 'Indexer' for the 'cal' extension.
 *
 * @author Mario Matzulla <mario(at)matzullas.de>
 */
class IcsGeneratorModul {

  /**
   * Array containing submitted data when editing or adding a task
   *
   * @var array
   */
  protected $submittedData = [ ];

  /**
   * Array containing all messages issued by the application logic
   * Contains the error's severity and the message itself
   *
   * @var array
   */
  protected $messages = [ ];

  /**
   *
   * @var string Key of the CSH file
   */
  protected $cshKey;

  /**
   *
   * @var string
   */
  protected $backendTemplatePath = '';

  /**
   *
   * @var \TYPO3\CMS\Fluid\View\StandaloneView
   */
  protected $view;

  /**
   * The name of the module
   *
   * @var string
   */
  protected $moduleName = 'tools_txcaldavM1';

  /**
   *
   * @var string Base URI of scheduler module
   */
  protected $moduleUri;

  /**
   * ModuleTemplate Container
   *
   * @var ModuleTemplate
   */
  protected $moduleTemplate;

  protected $request;

  var $pageinfo;

  /**
   *
   * @return \TYPO3\CMS\Caldav\Backend\Modul\IcsGeneratorModul
   */
  public function __construct() {

    $this->moduleTemplate = GeneralUtility::makeInstance( ModuleTemplate::class );
    $this->getLanguageService()->includeLLFile( 'EXT:caldav/Resources/Private/Language/locallang_ics_generator.xml' );
    $this->MCONF = [ 
        'name' => $this->moduleName
    ];
    $this->cshKey = '_MOD_' . $this->moduleName;
    $this->backendTemplatePath = ExtensionManagementUtility::extPath( 'cal' ) . 'Resources/Private/Templates/Backend/IcsGenerator/';
    $this->view = GeneralUtility::makeInstance( \TYPO3\CMS\Fluid\View\StandaloneView::class );
    $this->view->getRequest()->setControllerExtensionName( 'caldav' );
    $this->view->setPartialRootPaths( [ 
        ExtensionManagementUtility::extPath( 'caldav' ) . 'Resources/Private/Templates/Backend/IcsGenerator/Partials/'
    ] );
    $this->moduleUri = $this->getModuleUrl( $this->moduleName );
    
    $pageRenderer = GeneralUtility::makeInstance( PageRenderer::class );
    $pageRenderer->loadRequireJsModule( 'TYPO3/CMS/Backend/Modal' );
    $pageRenderer->loadRequireJsModule( 'TYPO3/CMS/Backend/SplitButtons' );
  }

  /**
   * Wrapper used for unit testing.
   *
   * @param string $moduleName          
   * @param array $urlParameters          
   * @return string
   */
  protected function getModuleUrl(string $moduleName, array $urlParameters = []): string {

    /** @var \TYPO3\CMS\Backend\Routing\UriBuilder $uriBuilder */
    $uriBuilder = GeneralUtility::makeInstance( \TYPO3\CMS\Backend\Routing\UriBuilder::class );
    return ( string ) $uriBuilder->buildUriFromRoute( $moduleName, $urlParameters );
  }

  /**
   * Adds items to the ->MOD_MENU array.
   * Used for the function menu selector.
   */
  public function menuConfig() {

    $this->MOD_MENU = Array (
        'function' => Array (
            '1' => $this->getLanguageService()->getLL( 'function1' ),
            '2' => $this->getLanguageService()->getLL( 'function2' ),
            '3' => $this->getLanguageService()->getLL( 'function3' )
        )
    );
  }

  /**
   * Injects the request object for the current request or subrequest
   * Simply calls main() and init() and outputs the content
   *
   * @param ServerRequestInterface $request
   *          the current request
   * @return ResponseInterface the response with the content
   */
  public function mainAction(ServerRequestInterface $request) {

    $GLOBALS ['SOBE'] = $this;
    $this->request = $request;
    $this->menuConfig();
    $this->main();
    
    $this->moduleTemplate->setContent( $this->content );
    return new \TYPO3\CMS\Core\Http\HtmlResponse( $this->moduleTemplate->renderContent() );
  }

  /**
   * Gets the current backend user.
   *
   * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
   */
  protected function getBackendUser() {

    return $GLOBALS ['BE_USER'];
  }

  /**
   * Generates the action menu
   */
  protected function getModuleMenu() {

    $menu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
    $menu->setIdentifier( 'CaldavJumpMenu' );
    
    foreach ( $this->MOD_MENU ['function'] as $controller => $title ) {
      
      $item = $menu->makeMenuItem()->setHref( $this->getModuleUrl( $this->moduleName, [ 
          'id' => $this->id,
          'SET' => [ 
              'function' => $controller
          ]
      ] ) )->setTitle( $title );
      
      if (intval( $controller ) == intval( $this->MOD_SETTINGS ['function'] )) {
        $item->setActive( true );
      }
      $menu->addMenuItem( $item );
    }
    $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu( $menu );
  }
  
  // If you chose 'web' as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
  /**
   * Main function of the module.
   * Write the content to $this->content
   */
  function main() {
    
    // Access check!
    // The page will show only if there is a valid page and if this page may be viewed by the user
    $this->pageinfo = \TYPO3\CMS\Backend\Utility\BackendUtility::readPageAccess( $this->id, $this->perms_clause );
    $access = is_array( $this->pageinfo ) ? 1 : 0;
    
    if (($this->id && $access) || ($this->getBackendUser()->isAdmin() && ! $this->id)) {
      
      // Set the form
      $this->content = '<form name="tx_cal_form" id="tx_cal_form" method="post" action="">';
      
      // Prepare main content
      $this->content .= '<h1>' . $this->getLanguageService()->getLL( 'function.' . $this->MOD_SETTINGS ['function'] ) . '</h1>';
      $this->content .= $this->getModuleContent();
      $this->content .= '</form>';
    } else {
      // If no access or if ID == zero
      $this->content = '<h1>' . $this->getLanguageService()->getLL( 'title.' ) . '</h1>';
      $this->content .= '<div style="padding-top: 5px;"></div>';
    }
    
    $this->getModuleMenu();
  }

  /**
   * Generates the module content
   */
  protected function getModuleContent() {

    $content = '';
    switch (intval( $this->request->getQueryParams() ['SET'] ['function'] )) {
      case 2 :
        $postVarArray = GeneralUtility::_POST();
        $pageIds = Array ();
        foreach ( $postVarArray as $name => $value ) {
          if ($name == 'pageId') {
            $pageIds [intval( $value )] = intval( $value );
          }
        }
        
        if (! empty( $pageIds )) {
          $content = $this->getLanguageService()->getLL( 'generateIcs' ) . '<br/>';
          $rgc = new \TYPO3\CMS\Caldav\Service\IcsGenerator( 0 );
          $content .= $this->getLanguageService()->getLL( 'found' ) . $rgc->countEventsWithoutIcs();
          $content .= $this->getLanguageService()->getLL( 'toBeProcessed' ) . '<br /><br />';
          foreach ( $pageIds as $eventPage => $pluginPage ) {
            $rgc->pageIDForPlugin = $pluginPage;
            $content .= 'PID ' . $eventPage;
            $content .= '<br /><br />' . $this->getLanguageService()->getLL( 'generateIcs' );
            $rgc->generateIcs();
            $content .= '<br />' . $rgc->getInfo();
          }
        } else {
          $pid = 0;
          $selectFieldIds = Array ();
          $content .= $this->getLanguageService()->getLL( 'selectPage' ) . '<br /><br />';
          
          $label = '<label>' . $this->getLanguageService()->getLL( 'tableHeader2' ) . '</label>';
          
          $table [] = '<div class="form-group col-sm-12" id="pageId_colId' . $pid . '">' . $label . '<div class="form-control-wrap">' . '<div class="input-group" id="pageId_colId' . $pid . '_row-wrapper">' . '<input name="pageId" value="' . $value . '" class="form-control  t3js-clearable" data-date-type="date" data-date-offset="0" type="text" id="tceforms-pageId_colId' . $pid . '_row">' . '</div>' . '</div>' . '</div>';
          $content .= implode( LF, $table );
          $scontent .= '<br /><br /><input type="submit" value="' . $this->getLanguageService()->getLL( 'submit' ) . '" onclick="return markSelections();"/>';
          
          $selectFields = '';
          foreach ( $selectFieldIds as $selectFieldId ) {
            $selectFields .= ' var o' . $selectFieldId . ' = document.getElementById("' . $selectFieldId . '");if(o' . $selectFieldId . '.options.length > 0){o' . $selectFieldId . '.options[0].selected = "selected";} else {notComplete = 1;}';
          }
          $content .= '<script type="text/javascript">function markSelections(){ var notComplete = 0;' . $selectFields . ' if(notComplete == 1){alert("' . $this->getLanguageService()->getLL( 'notAllPagesAssigned' ) . '");return false;}return true;}</script>';
          
          $content .= $this->getLanguageService()->getLL( 'startIndexing' ) . $scontent;
        }
        break;
      case 3 :
        $rgc = new \TYPO3\CMS\Caldav\Service\IcsGenerator( 0 );
        $content .= '<h2>Check</h2>' . $rgc->check();
        break;
      default :
        $content .= '<h2>' . $this->getLanguageService()->getLL( 'notice_header' ) . '</h2>';
        $content .= '<p>' . $this->getLanguageService()->getLL( 'notice' ) . '</p>';
        $content .= '<h2>' . $this->getLanguageService()->getLL( 'capabilities_header' ) . '</h2>';
        $content .= '<p>' . $this->getLanguageService()->getLL( 'capabilities' ) . '</p>';
        break;
    }
    return $content;
  }

  /**
   * Returns LanguageService
   *
   * @return LanguageService
   */
  protected function getLanguageService(): \TYPO3\CMS\Core\Localization\LanguageService {

    return $GLOBALS ['LANG'];
  }
}
?>