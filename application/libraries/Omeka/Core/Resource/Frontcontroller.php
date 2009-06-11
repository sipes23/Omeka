<?php 
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2009
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 **/

/**
 * 
 *
 * @package Omeka
 * @copyright Center for History and New Media, 2009
 **/
class Omeka_Core_Resource_Frontcontroller extends Zend_Application_Resource_Frontcontroller
{
    public function init()
    {
        // Front controller
        $front = Zend_Controller_Front::getInstance();
        $front->addControllerDirectory(CONTROLLER_DIR, 'default');
                                                        
        // Action helpers
        $this->initializeActionHelpers();        
        
        // This requires that the options have been properly instantiated.
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('Options');
        
        $options = $bootstrap->getResource('Options');
        $front->registerPlugin(new Omeka_Controller_Plugin_ViewScripts($options));
        
        return $front;
    }
        
    private function initializeActionHelpers()
    {
        $this->initViewRenderer();
        $this->initResponseContexts();
        $this->initSearchHelper();
    }
        
    private function initSearchHelper()
    {
        $searchHelper = new Omeka_Controller_Action_Helper_SearchItems;
        Zend_Controller_Action_HelperBroker::addHelper($searchHelper);
    }
    
    private function initViewRenderer()
    {
        $viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
        $view = new Omeka_View();
        $viewRenderer->setView($view)
                     ->setViewSuffix('php');  
                     
        // Register the view object so that it can be called by the view helpers.
        Zend_Registry::set('view', $view);   
    }
    
    /**
     * Define the custom response format contexts for Omeka.
     * 
     * Plugin writers should use the 'define_response_contexts' filter to modify
     * or expand the list of formats that existing controllers may respond to.
     *
     * @link http://framework.zend.com/manual/en/zend.controller.actionhelpers.html#zend.controller.actionhelpers.contextswitch
     * 
     * Example of a definition of a response context through the ZF API:
     * 
     * $contexts->addContext('dc', array(
     *    'suffix'    => 'dc',
     *    'headers'   => array('Content-Type' => 'text/xml'),
     *    'callbacks' => array(
     *        'init' => 'atBeginningDoThis',
     *        'post' => 'afterwardsDoThis'
     *    ) 
     *  ));
     * 
     * @return void
     **/    
    private function initResponseContexts()
    {        
        Zend_Controller_Action_HelperBroker::addHelper(new Omeka_Controller_Action_Helper_ContextSwitch);
        $contexts = Zend_Controller_Action_HelperBroker::getStaticHelper('contextSwitch');
                
        $contexts->setContextParam('output');
                
        $contextArray = array(
             'dcmes-xml' => array(
                 'suffix'    => 'dcmes-xml',
                 'headers'   => array('Content-Type' => 'text/xml')
             ),
             'rss2' => array(
                 'suffix'    => 'rss2',
                 'headers'   => array('Content-Type' => 'text/xml')
             )
         );

        if ($pluginBroker = $this->getBootstrap()->getResource('PluginBroker')) {
             $contextArray = $pluginBroker->applyFilters('define_response_contexts', $contextArray);
        }
        
        $contexts->addContexts($contextArray); 
    }       
    
}