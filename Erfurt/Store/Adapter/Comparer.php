<?php

/**
 * This file is part of the {@link http://aksw.org/Projects/Erfurt Erfurt} project.
 *
 * @copyright Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/** Erfurt_Store_Adapter_Interface */
require_once 'Erfurt/Store/Adapter/Interface.php';

/** Erfurt_Store_Sql_Interface */
require_once 'Erfurt/Store/Sql/Interface.php';

class Erfurt_Store_Adapter_Comparer_Exception extends Erfurt_Store_Exception{
    function  __construct($method, $ref, $actual) {
        function mydumpStr($var){
            $str = '<pre>'; // This is for correct handling of newlines
            ob_start();
            var_dump($var);
            $a=ob_get_contents();
            ob_end_clean();
            $str .= htmlspecialchars($a,ENT_QUOTES); // Escape every HTML special chars (especially > and < )
            $str .= '</pre>';
            return $str;
        }
        parent::__construct('comparer detected a difference at method "'.$method.'": return should be '.mydumpStr($ref).' but is '.mydumpStr($actual));
    }
}

/**
 * A dummy Adapter for the Erfurt Semantic Web Framework.
 *
 * compares the result of two adapters and throws errors if they are different
 *
 * @category Erfurt
 * @package Store_Adapter
 * @author Jonas Brekle <jonas.brekle@gmail.com>
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class Erfurt_Store_Adapter_Virtuoso implements Erfurt_Store_Adapter_Interface, Erfurt_Store_Sql_Interface
{

    /**
     * Adapter option array
     * @var array
     */
    protected $_adapterOptions = null;
    
    /**
     *
     * @var Erfurt_Store_Adapter_Interface 
     */
    protected $_candidate = null;
    /**
     *
     * @var Erfurt_Store_Adapter_Interface
     */
    protected $_reference = null;


    // ------------------------------------------------------------------------
    // --- Magic Methods ------------------------------------------------------
    // ------------------------------------------------------------------------
    
    /**
     * Constructor.
     *
     * @throws Erfurt_Store_Adapter_Exception
     */
    public function __construct($adapterOptions)
    {
        $this->_adapterOptions = $adapterOptions;

        $config = Erfurt_App::getInstance()->getConfig();

        if($config->store->isset($adapterOptions['candidate'])){
            $candidateConf = $config->store->get($adapterOptions['candidate']);
        }
        if($config->store->isset($adapterOptions['reference'])){
            $referenceConf = $config->store->get($adapterOptions['reference']);
        }

        if(!isset($candidateConf) || !isset ($referenceConf)){
            throw new Erfurt_Store_Exception("the requested adapters to be compared have no options set in config.ini");
        }

        $candidateClassName = 'Erfurt_Store_Adapter_'.ucfirst($adapterOptions['candidate']);
        if(!class_exists($candidateClassName)){
            throw new Erfurt_Store_Exception("the requested adapter class ".$candidateClassName." does not exist");
        }
        $referenceClassName = 'Erfurt_Store_Adapter_'.ucfirst($adapterOptions['reference']);
        if(!class_exists($referenceClassName)){
            throw new Erfurt_Store_Exception("the requested adapter class ".$referenceClassName." does not exist");
        }

        $this->_candidate = new $candidateClassName($candidateConf);
        $this->_reference = new $referenceClassName($referenceConf);
    }

    protected static $_strictMethods = array('isModelAvailable');
    protected static $_setMethods = array('sparqlQuery');

    protected function nestedArrayMutualInclusion($arr1, $arr2){
        foreach($arr1 as $key => $val){
            if(!isset ($arr2[$key])){
                return false;
            } else {
                if(gettype($arr1[$key]) != gettype($arr2[$key])){
                    return false;
                } else {
                    if(is_array($arr1[$key])){
                        if(!nestedArrayMutualInclusion($arr1[$key], $arr2[$key])){
                            return false;
                        }
                    } else {
                        if($arr1[$key] != $arr2[$key]){
                            return false;
                        }
                    }
                }
            }
        }
        foreach($arr2 as $key => $val){
            if(!isset ($arr1[$key])){
                return false;
            } else {
                if(gettype($arr1[$key]) != gettype($arr2[$key])){
                    return false;
                } else {
                    if(is_array($arr1[$key])){
                        if(!nestedArrayMutualInclusion($arr1[$key], $arr2[$key])){
                            return false;
                        }
                    } else {
                        if($arr1[$key] != $arr2[$key]){
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }


    public function  __call($name, $arguments) {
        $refThrowed = false;
        try {
            $ref = call_user_func_array(array($this->_reference, $name), $arguments);
        } catch (Exception $e){
            $refThrowed = $e;
        }
        $candThrowed = false;
        try {
            $cand = call_user_func_array(array($this->_candidate, $name), $arguments);
        } catch (Exception $e){
            $candThrowed = $e;
        }
        if($candThrowed != false && $refThrowed == false){
            throw new Erfurt_Store_Exception("Candidate throwed an exception but reference didn't".PHP_EOL.$candThrowed->getTraceAsString());
        }
        if($candThrowed == false && $refThrowed != false){
            throw new Erfurt_Store_Exception("Reference throwed an exception but Candidate didn't".PHP_EOL.$candThrowed->getTraceAsString());
        }

        if(in_array($name, self::$_strictMethods)){
            if($ref !== $cand){
                throw new Erfurt_Store_Adapter_Comparer_Exception($name, $ref, $cand);
            }
        } else  if(in_array($name, self::$_setMethods)){
            if(!nestedArrayMutualInclusion($ref,$cand)){
                throw new Erfurt_Store_Adapter_Comparer_Exception($name, $ref, $cand);
            }
        }

        return $cand;
    }
}