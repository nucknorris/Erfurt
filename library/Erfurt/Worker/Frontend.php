<?php
/**
 * This file is part of the {@link http://erfurt-framework.org Erfurt} project.
 *
 * @copyright Copyright (c) 2013, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Frontend class (worker client) for worker jobs.
 *
 * @category Erfurt
 * @package Erfurt
 * @author Christian Würker <christian.wuerker@ceusmedia.de>
 */
class Erfurt_Worker_Frontend
{
    /**
     *  Protected singleton instance of worker frontend.
     *  @static
     *  @var    array
     */
    static protected $instance;

    /**
     *  Construction is not allowed.
     *  Please use getInstance() instead.
.    *  @access     protected
     *  @return     void
     */
    protected function __construct( Zend_Config $config )
    {
        $this->client   = new GearmanClient();
        $this->client->addServers( $config->worker->servers );
    }

    /**
     *  Cloning is not allowed.
.    *  @access     protected
     *  @return     void
     */
    protected function __clone()
    {
    }
    
    /**
     *  Calls for synchronous execution of an registrered worker job.
     *  This method only allows synchronous job execution. Use callAsync() for asynchronous job execution.
     *  @access     public
     *  @param      string      $jobName
     *  @param      mixed       $workload       Workload for job, may be empy
     *  @param      integer     $priority       Flag: priority of execution, 1: high, 0: normal, -1: low, default: normal
     *  @return     string      Job result (may be serialized)
     */
    public function call( $jobName, $workload = NULL, $priority = 0 )
    {
        $method = "doNormal";
        if( $priority === 1 ){
            $method = "doHigh";
        }
        else if( $priority === -1 ){
            $method = "doLow";
        }
        $workload   = $this->prepareWorkload( $workload );
        return $this->client->$method( $jobName, $workload );
    }

    /**
     *  Calls for asynchronous execution of an registrered worker job.
     *  This method only allows asynchronous job execution. Use call() for synchronous job execution.
     *  @access     public
     *  @param      string      $jobName
     *  @param      mixed       $workload       Workload for job, may be empy
     *  @param      integer     $priority       Flag: priority of execution, 1: high, 0: normal, -1: low, default: normal
     *  @return     string      Job handle assigned by the Gearman server
     */
    public function callAsync( $jobName, $workload = NULL, $priority = 0 ){
        $method = "doBackground";
        if( $priority === 1 ){
            $method = "doHighBackground";
        }
        else if( $priority === -1 ){
            $method = "doLowBackground";
        }
        $workload   = $this->prepareWorkload( $workload );
        $jobHandle  = $this->client->$method( $jobName, $workload );
        if( $this->client->returnCode() !== GEARMAN_SUCCESS ){
            throw new Erfurt_Worker_Exception(
                "Asynchronous job call failed"
            );
        }
        return $jobHandle;
    }

    /**
     *  Returns singleton instance of registry.
     *  @static
     *  @access     public
     *  @return     Erfurt_Worker_Frontend      Singleton instance of worker frontend 
     *  @throws     RuntimeException            if worker is not configured
     *  @throws     RuntimeException            if worker is not enabled
     */
    static public function getInstance( Zend_Config $config )
    {
        if( !self::$instance ){
            if( !$config->worker ){
                throw new RuntimeException(
                    "Worker not configured"
                );
            }
            if( !$config->worker->enable ){
                throw new RuntimeException(
                    "Worker is not enabled"
                );
            }
            self::$instance = new self( $config );
        }
        return self::$instance;
    }

    /**
     *  Indicates whether a asynchronous job is still running.
     *  @access     public
     *  @param      string      $jobHandle      Job handle assigned by the Gearman server
     *  @return     boolean
     */
    public function isStillRunning( $jobHandle ){
        $status = $this->client->jobStatus( $jobHandle );
        return $status[0];
    }

    /**
     *  Prepares workload for job call by serializing if not string or integer.
     *  @access     public
     *  @param      mixed       $workload       Workload to be prepared for job call
     *  @return     string      String presentation of workload
     */
    protected function prepareWorkload( $workload ){
        switch( strtolower( gettype( $workload ) ) ){
            case "array":
            case "object":
                $workload   = serialize( $workload );
                break;
        }
        return $workload;
    }
}