<?php
/**
 * This file is part of the {@link http://aksw.org/Projects/Erfurt Erfurt} project.
 *
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version $Id$
 */

/**
 * A class providing support for access control.
 * 
 * This class provides support for model, action (and statement) based access control.
 * The access control informations are stored in a triple store.
 *
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @package erfurt
 * @subpackage ac
 * @author Stefan Berger <berger@intersolut.de>
 * @author Philipp Frischmuth <pfrischmuth@googlemail.com>
 */
class Erfurt_Ac_Default 
{    
    // ------------------------------------------------------------------------
    // --- Private properties -------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Instance of the ac model.
     * @var Erfurt_Rdf_Model
     */
    private $_acModel = null;
    
    /**
     * Contains the action configuration from the configurations (both ini and ac model).
     * @var array
     */
    private $_actionConfig = null;
    
    /**
     * Contains a reference to a auth object.
     * @var Zend_Auth
     */
    private $_auth = null;
    
    /**
     * Contains the configuration.
     * @var Zend_Config
     */
    private $_config = null;
    
    private $_isInit = false;
    
    /**
     * Contains the configured ac concept uris.
     * @var array
     */
    private $_uris = array(
        'acBaseUri'          => 'http://ns.ontowiki.net/SysOnt/',
        'acModelUri'         => 'http://localhost/OntoWiki/Config/',
        'anonymousUserUri'   => 'http://ns.ontowiki.net/SysOnt/Anonymous',
        'superUserUri'       => 'http://ns.ontowiki.net/SysOnt/SuperAdmin',
        'propAnyModel'       => 'http://ns.ontowiki.net/SysOnt/AnyModel',
        'propGrantModelView' => 'http://ns.ontowiki.net/SysOnt/grantModelView',
        'propDenyModelView'  => 'http://ns.ontowiki.net/SysOnt/denyModelView',
        'propGrantModelEdit' => 'http://ns.ontowiki.net/SysOnt/grantModelEdit',
        'propDenyModelEdit'  => 'http://ns.ontowiki.net/SysOnt/denyModelEdit',
        'actionClassUri'     => 'http://ns.ontowiki.net/SysOnt/Action',
        'propAnyAction'      => 'http://ns.ontowiki.net/SysOnt/AnyAction',
        'propGrantAccess'    => 'http://ns.ontowiki.net/SysOnt/grantAccess',
        'propDenyAccess'     => 'http://ns.ontowiki.net/SysOnt/denyAccess',
        'modelClassUri'      => 'http://ns.ontowiki.net/SysOnt/Model',
        'actionConfigUri'    => 'http://ns.ontowiki.net/SysOnt/rawConfig'
    );
        
    /**
     * Contains the user rights for all fetched users.
     * @var array
     */
    private $_userRights = array();

    /**
     * Contains a template for the default permissions of a user.
     * @var array
     */
    private $_userRightsTemplate = array(
        'userAnyModelViewAllowed' => false,
        'userAnyModelEditAllowed' => false,
        'userAnyActionAllowed'    => false,
        'grantAccess'             => array(),
        'denyAccess'              => array(),
        'grantModelView'          => array(),
        'denyModelView'           => array(),
        'grantModelEdit'          => array(),
        'denyModelEdit'           => array()
    );
    
    // ------------------------------------------------------------------------
    // --- Public methods -----------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Delivers the action configuration for a given action
     * 
     * @param string $actionSpec The URI of the action.
     * @return array Returns an array with the action spec.
     */
    public function getActionConfig($actionSpec)
    {   
        $this->_init();
        
        if (null === $this->_actionConfig) {
            // Fetch the action config.
            $actionConfig = array();
            
            // First we set the default values from (ini) config. These values will then be overwritten by
            // values from the (ac model) config.
            foreach ($this->_config->ac->action->config->toArray() as $actions) {
                $actionConfig[$actions['uri']] = $actions['spec'];
            }
            
            // Now fetch the config from ac model and overwrite the values.
            require_once 'Erfurt/Sparql/SimpleQuery.php';
            $query = new Erfurt_Sparql_SimpleQuery();
            $query->setProloguePart('SELECT ?s ?o')
                  ->setWherePart('WHERE {
                      ?s <' . $this->_uris['actionConfigUri'] . '> ?o .
                  }');
            
            $result = $this->_sparql($this->_acModel, $query);
            if ($result) {
                foreach ($result as $row) {
                    $s = $row['s'];
                    $o = explode('=', $row['o']);
                    
                    // remove quotas
                    if (substr($o[1], 0, 1) === '"') {
                        $o[1] = substr($o[1], 1);
                    }
                    if (substr($o[1], -1) === '"') {
                        $o[1] = substr($o[1], 0,  -1);
                    }
                      
                    // Check whether config for uri is already set.
                    if (!isset($actionConfig[$s])) {
                        $actionConfig[$s] = array();
                    } 
                    
                    $actionConfig[$s][$o[0]] = $o[1];
                }
            }
            
            $this->_actionConfig = $actionConfig;
        }
        
        // Return the action config for the given spec if available.
        $actionUri = $this->_uris['acBaseUri'] . $actionSpec;
        
        if (isset($this->_actionConfig[$actionUri])) {
            return $this->_actionConfig[$actionUri];
        } else {
            return array();
        }
    }
    
    /**
     * Delievers a  list of allowed actions for the current user.
     *  
     * @return array Returns a list of allowed actions.
     */
    public function getAllowedActions() 
    {   
        $this->_init();
         
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        
        // filter denied actions
        $ret = array();
        foreach ($userRights['grantAccess'] as $allowed) {
            if (in_array($allowed, $userRights['denyAccess'])) {
                continue;
            }
            $ret[] = $allowed;
        }
        
        return $ret;
    }
    
    /**
     * Delievers a list of allowed models.
     *  
     * @param string $type Name of the access type.
     * @return array Returns a list of allowed models.
     */
    public function getAllowedModels($type = 'view') 
    {   
        $this->_init();
        
        $type = strtolower($type);
        // not supported type?
        if (!in_array($type, array('view', 'edit'))) {
            return array();
        }
         
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        
        $ret = array();
        $grantModelKey  = ($type === 'view') ? 'grantModelView' : 'grantModelEdit';
        $denyModelKey   = ($type === 'view') ? 'denyModelView'  : 'denyModelEdit';
        
        // filter denied models
        foreach ($userRights[$grantModelKey] as $allowed) {
            if (in_array($allowed, $userRights[$denyModelKey])) {
                continue;
            } 
            $ret[] = $allowed;
        }
        
        return $ret;
    }
    
    /**
     * Delievers a list of denied actions for the current user.
     *
     * @return array Returns a list of denied actions.
     */
    public function getDeniedActions() 
    {   
        $this->_init();
         
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        
        return $userRights['denyAccess']; 
    }
    
    /**
     * Delievers a list of denied models.
     *
     * @param string $type Name of the access type.
     * @return array Returns a list of denied models.
     */
    public function getDeniedModels($type = 'view') 
    {   
        $this->_init();
        
        $type = strtolower($type);
        // not supported type?
        if (!in_array($type, array('view', 'edit'))) {
            return array();
        }
        
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
            
        $denyModelKey = ($type === 'view') ? 'denyModelView' : 'denyModelEdit';
        
        return $userRights[$denyModelKey];
    }
    
    /**
     * Checks whether the given action is allowed for the current user on the
     * given model uri.
     *
     * @param string $type Name of the access-type (view, edit).
     * @param string $modelUri The uri of the graph to check.
     * @return boolean Returns whether allowed or denied.
     */
    public function isModelAllowed($type, $modelUri) 
    {
        $this->_init();
        
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        $type       = strtolower($type);
        
        // type = view; check whether allowed
        if ($type === 'view') {
            // explicit forbidden
            if (in_array($modelUri, $userRights['denyModelView'])) {
                return false;
            }   
            // view explicit allowed and not denied
            else if (in_array($modelUri, $userRights['grantModelView'])) {
                return true;
            }
            // view in edit allowed and not denied
            else if (in_array($modelUri, $userRights['grantModelEdit'])) {
                return true;
            }
            // any model
            else if ($this->isAnyModelAllowed('view')) {
                return true;
            }
        }
            
        // type = edit; check whether allowed
        if ($type === 'edit') {
            // explicit forbidden
            if (in_array($modelUri, $userRights['denyModelEdit'])) {
                return false;
            }
            // edit allowed and not denied
            else if (in_array($modelUri, $userRights['grantModelEdit'])) {
                return true;
            }
            // any model
            else if ($this->isAnyModelAllowed('edit')) {
                return true;
            }
        }
        
        // deny everything else => false
        return false;
    }
    
    /**
     * Checks whether the given action is allowed for the current user.
     *
     * @param string $action The name of the action.
     * @return boolean Returns whether action is allowed or not.
     */
    public function isActionAllowed($action) 
    {
        $this->_init();

        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        $actionUri  = $this->_uris['acBaseUri'] . $action;

        // Action explicitly not allowed
        if (in_array($actionUri, $userRights['denyAccess'])) {
            return false;
        } 
        // Action explicitly allowed
        else if (in_array($actionUri, $userRights['grantAccess'])) {
            return true;
        }
        // Every Action allowed
        else if ($this->isAnyActionAllowed()) {
            return true;
        } 
        // No record for action found, so create and disallow
        else {
        
            // add statements only if user is local erfurt user
            if ($user->isAnonymousUser() || $user->isDbUser() || $user->isWebId() || $user->isOpenId()) {
                return false;
            } else {
                // try to generate nice label (insert whitespace before uppercase chars)
                $label = preg_replace_callback(
                    "/[A-Z]{1}[a-z]{2,}/",
                    create_function(
                        '$matches',
                        'return \' \' . $matches[0];'
                     ),
                     $action
                );
            
                // array for new statements (an action instance pus label and the user deny-action statement)
                $actionStmt = array(
                    $actionUri => array ( 
                        EF_RDF_TYPE => array ( 
                            array ( 'type' => 'uri' , 'value' => $this->_uris['actionClassUri'] )
                        ) ,
                        EF_RDFS_LABEL => array (
                            array ( 'type' => 'literal' , 'value' => $label )
                        )
                    ) ,
                    $user->getUri() => array (
                        $this->_uris['propDenyAccess'] => array (
                            array ( 'type' => 'uri' , 'value' => $actionUri )
                        )
                    )
                );
                $store = Erfurt_App::getInstance()->getStore();
                $store->addMultipleStatements($this->_uris['acModelUri'], $actionStmt, false);
                return false;
            }
        }
    }
    
    /**
     * Checks whether any action is allowed for the current user.
     *
     * @return boolean Returns whether an action is allowed or not.
     */
    public function isAnyActionAllowed() 
    {   
        $this->_init(); 
        
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        
        return $userRights['userAnyActionAllowed'];
    }
    
    /**
     * Checks whether the current user has the given permission 
     * for any models. 
     *
     * @param string $type (optional) Contains view or edit.
     * @return boolean Returns whether allowed or denied.
     */
    public function isAnyModelAllowed($type = 'view') 
    {
        $this->_init();
        
        $user       = $this->_getUser();
        $userRights = $this->_getUserModelRights($user->getUri());
        $type       = strtolower($type);
        
        if ($type === 'view') {
            // any model view allowed?
            if ($userRights['userAnyModelViewAllowed'] === true) {
                return true;
            }
            // any model edit allowed? (implies view right)
            else if ($userRights['userAnyModelEditAllowed'] === true) {
                return true;
            }
            // not allowed!
            else {
                return false;
            }
        }
        
        if ($type === 'edit') {
            // any model edit allowed?
            if ($userRights['userAnyModelEditAllowed'] === true) {
                return true;
            } 
            // not allowed!
            else {
                return false;
            }           
        }
        
        // deny everything else => false
        return false;
    }
        
    /**
     * Adds a right to a model for the current user.
     * 
     * @param string $modelUri The URI of the model. 
     * @param string $type Type of access: view or edit.
     * @param string $perm Type of permission: grant or deny.
     * @throws Erfurt_Exception Throws an exception if wrong type was submitted or
     * wrong perm type was submitted.
     */
    public function setUserModelRight($modelUri, $type = 'view', $perm = 'grant') 
    {    
        $this->_init();
       
        $user       = $this->_getUser();
        $type       = strtolower($type);
        
        // is type supported?
        if (!in_array($type, array('view', 'edit'))) {
            require_once 'Erfurt/Ac/Exception.php';
            throw new Erfurt_Ac_Exception('Wrong access type submitted');
        }
            
        // is permission supported?
        if (!in_array($perm, array('grant', 'deny'))) {
            require_once 'Erfurt/Ac/Exception.php';
            throw new Erfurt_Ac_Exception('Wrong permission type submitted');
        }
            
        // set the property for the right to add...
        if ($type === 'view') {
            if ($perm === 'grant') {
                $prop = $this->_uris['propGrantModelView'];
                $right = 'grantModelView';
            }
            // else the permission is deny
            else {
                $prop = $this->_uris['propDenyModelView'];
                $right = 'denyModelView';
            }
        } 
        // else the type is edit
        else {
            if ($perm === 'grant') {
                $prop = $this->_uris['propGrantModelEdit'];
                $right = 'grantModelEdit';
            }
            // else the permission is deny
            else {
                $prop = $this->_uris['propDenyModelEdit'];
                $right = 'denyModelEdit';
            }
        }
        
        // Update the array that contains the right for the user.
        $this->_userRights[$user->getUri()][$right][] = $modelUri;

// TODO set the right cache tags, such that cache is invalidated!!!
        $store = Erfurt_App::getInstance()->getStore();
        $store->addStatement($this->_acModel->getModelUri(), $user->getUri(), $prop, array('type' => 'uri', 'value' => $modelUri), false);
    }
    
    // ------------------------------------------------------------------------
    // --- Private methods ----------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Fetches the current user from the auth object.
     * 
     * @return array Returns a user spec array on success.
     * @throws Erfurt_Ac_Exception Throws an exception if no valid user is given.
     */ 
    private function _getUser()
    {
        if ($this->_auth->hasIdentity()) {
            // Identity exists; get it
            return $this->_auth->getIdentity();
        } else {
            require_once 'Erfurt/Ac/Exception.php';
            throw new Erfurt_Ac_Exception('No valid user was given.');
        }
    }
    
    /**
     * Gets the user rights for the current user.
     * In case the user uri was not fetched, it is fetched.
     * 
     * @param string $userURI The URI of the user.
     * @return array Returns an array that contains the user rights.
     */
    private function _getUserModelRights($userURI) 
    {
        if (!isset($this->_userRights[$userURI])) {
            // In this case we need to fetch the rights for the user.
            $userRights = $this->_userRightsTemplate;
         
            // Super admin, i.e. a user that has database rights (only for debugging purposes and only if
            // enabled in config).
            if (($userURI === $this->_uris['superUserUri']) && ((boolean)$this->_config->ac->allowDbUser === true)) {
                $userRights['userAnyActionAllowed']     = true;
                $userRights['userAnyModelEditAllowed']  = true;
                $userRights['userAnyModelViewAllowed']  = true;

                $this->_userRights[$userURI] = $userRights;
                return $userRights;
            }
            
            require_once 'Erfurt/Sparql/SimpleQuery.php';
            
            $sparqlQuery = new Erfurt_Sparql_SimpleQuery();
            $sparqlQuery->setProloguePart('SELECT ?group ?p ?o')
                        ->setWherePart('WHERE { 
                            ?group ?p ?o . 
                            ?group <' . $this->_config->ac->group->membership . '> <' . $userURI . '> 
                        }'); 
            
            if ($result = $this->_sparql($this->_acModel, $sparqlQuery)) {
                $this->_filterAccess($result, $userRights);
            }
            
            $sparqlQuery = new Erfurt_Sparql_SimpleQuery();
            $sparqlQuery->setProloguePart('SELECT ?s ?p ?o')
                        ->setWherePart('WHERE { 
                            ?s ?p ?o . 
                            FILTER (sameTerm(?s, <' . $userURI . '>))
                        }');
            
            if ($result = $this->_sparql($this->_acModel, $sparqlQuery)) {
                $this->_filterAccess($result, $userRights);
            }
            
            // Now check for forbidden anyModel.
            // view
            if (in_array($this->_uris['propAnyModel'], $userRights['denyModelView'])) {
                $userRights['userAnyModelViewAllowed'] = false;
                $userRights['userAnyModelEditAllowed'] = false;
                $userRights['grantModelView']          = array();
                $userRights['grantModelEdit']          = array();
            }
            // edit
            if (in_array($this->_uris['propAnyModel'], $userRights['denyModelEdit'])) {
                $userRights['userAnyModelEditAllowed'] = false;
                $userRights['grantModelEdit']          = array();
            }
            
            $this->_userRights[$userURI] = $userRights;
        }

        return $this->_userRights[$userURI];
    }
    
    /**
     * Filters the sparql results and saves the results in $userRights var.  
     * 
     * @param array $resultList A list of sparql results.
     * @param array $userRights A reference to an array containing user rights.
     */
    private function _filterAccess($resultList, &$userRights) 
    {     
        foreach ($resultList as $entry) {           
            // any action allowed?
            if (($entry['o'] === $this->_uris['propAnyAction']) 
                    && ($entry['p'] === $this->_uris['propGrantAccess'])) {
                
                $userRights['userAnyActionAllowed'] = true;
            }
            // any model view allowed?
            else if (($entry['o'] === $this->_uris['propAnyModel']) 
                    && ($entry['p'] === $this->_uris['propGrantModelView'])) {
                
                $userRights['userAnyModelViewAllowed'] = true;
            } 
            // any model edit allowed?
            else if (($entry['o'] === $this->_uris['propAnyModel']) 
                    && ($entry['p'] === $this->_uris['propGrantModelEdit'])) {
                
                $userRights['userAnyModelEditAllowed'] = true;
            }
            // grant action?
            else if ($entry['p'] === $this->_uris['propGrantAccess']) {
                if (!in_array($entry['o'], $userRights['grantAccess'])) {
                    $userRights['grantAccess'][] = $entry['o'];
                }   
            }
            // deny action?
            else if ($entry['p'] === $this->_uris['propDenyAccess']) {
                if (!in_array($entry['o'], $userRights['denyAccess'])) {
                    $userRights['denyAccess'][] = $entry['o'];
                }
            }
            // grant model view?
            else if ($entry['p'] === $this->_uris['propGrantModelView']) {
                if (!in_array($entry['o'], $userRights['grantModelView'])) {
                    $userRights['grantModelView'][] = $entry['o'];
                }
            }
            // deny model view?
            else if ($entry['p'] === $this->_uris['propDenyModelView']) {
                if (!in_array($entry['o'], $userRights['denyModelView'])) {
                    $userRights['denyModelView'][] = $entry['o'];
                }
            }
            // grant model edit?
            else if ($entry['p'] === $this->_uris['propGrantModelEdit']) {
                if (!in_array($entry['o'], $userRights['grantModelEdit'])) {
                    $userRights['grantModelEdit'][] = $entry['o'];
                }
            }
            // deny model edit?
            else if ($entry['p'] === $this->_uris['propDenyModelEdit']) {
                if (!in_array($entry['o'], $userRights['denyModelEdit'])) {
                    $userRights['denyModelEdit'][] = $entry['o'];
                }
            }
        }
    }
    
    /**
     * initialisation of models, uris and rights
     * 
     */
    private function _init()
    {
        if ($this->_isInit === true) {
            return;
        }
        
        // Reset the user rights array.
        $this->_userRights = array();
        
        $app = Erfurt_App::getInstance();
        
        $this->_config = $app->getConfig();
        $this->_auth   = $app->getAuth();
        
        // access control informations
        $this->_acModel = $app->getAcModel();
        
        // get custom uri configuration
        $this->_uris['acBaseUri']   = $this->_config->ac->baseUri;
        $this->_uris['acModelUri'] = $this->_acModel->getModelUri();
        $this->_uris['anonymousUserUri']   = $this->_config->ac->user->anonymousUser;
        $this->_uris['superUserUri']       = $this->_config->ac->user->superAdmin;
        $this->_uris['propAnyModel']       = $this->_config->ac->models->anyModel;
        $this->_uris['propGrantModelView'] = $this->_config->ac->models->grantView;
        $this->_uris['propDenyModelView']  = $this->_config->ac->models->denyView;
        $this->_uris['propGrantModelEdit'] = $this->_config->ac->models->grantEdit;
        $this->_uris['propDenyModelEdit']  = $this->_config->ac->models->denyEdit;
        $this->_uris['actionClassUri']     = $this->_config->ac->action->class;
        $this->_uris['propAnyAction']      = $this->_config->ac->action->anyAction;
        $this->_uris['propGrantAccess']    = $this->_config->ac->action->grant;
        $this->_uris['propDenyAccess']     = $this->_config->ac->action->deny;
        $this->_uris['modelClassUri']      = $this->_config->ac->models->class;
        $this->_uris['actionConfigUri']    = $this->_config->ac->action->rawConfig;
        
        $this->_isInit = true;
    }
    
    /**
     * Executes a sparql query against the store. 
     * 
     * @param Erfurt_Rdf_Model Active model instance to query sparql.
     * @param Erfurt_Sparql_SimpleQuery The SPARQL query.
     * @return array Returns an array containig the result.
     */
    private function _sparql($model, $sparqlQuery) 
    {
        $sparqlQuery->addFrom($model->getModelUri());
        $result = $model->getStore()->sparqlQuery($sparqlQuery, array('use_ac' => false));
        
        return $result;
    }
}
