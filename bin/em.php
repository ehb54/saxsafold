<?php

{}

require_once "common.php";

class em {
    public $errors;

    private $dockerprefix = "ssh host docker exec -w /home/jobrunner/openstack/elasticmanager elasticmanager php em_client.php";
    private $flavor       = "m3.large";

    private $has_instance = false;
    private $instance_id;
    private $instance_ip;
    private $debug;
    
    function __construct( $debug = false ) {
        $this->debug = $debug;
        if ( $this->debug ) {
            echo "em::_construct\n";
        }
    }

    function __destruct() {
        if ( $this->debug ) {
            echo "em::_destruct\n";
        }
        if ( $this->has_instance ) {
            $this->release();
        }
    }

    function status() {
        $cmd = "$this->dockerprefix --status";
        return `$cmd 2>&1`;
    }

    function release_if_has_instance() {
        if ( $this->debug ) {
            echo "release if has instance\n";
        }
        if ( $this->has_instance ) {
            $this->release();
        }
    }

    function release() {
        if ( !$this->has_instance ) {
            error_exit( "em:internal error - release without acquire" );
        }

        $cmd = "$this->dockerprefix --release $this->instance_id";
        $res = run_cmd( $cmd );

        $this->has_instance = false;
    }

    function acquire( $id = "em_test_instance" ) {
        if ( $this->has_instance ) {
            error_exit( "em:internal error - double acquire" );
        }
            
        ## acquire an instance

        $cmd = "$this->dockerprefix --acquire $this->flavor $id";
        $res = run_cmd( $cmd );
        $arr = explode( " ", trim( $res ) );
        $this->instance_id = $arr[ 0 ];
        $this->instance_ip = $arr[ 1 ];
        
        if ( !preg_match( '/^\d+$/', $this->instance_id )
             || !preg_match( '/^\d+\.\d+\.\d+\.\d+$/', $this->instance_ip ) ) {
            $this->errors = "Could not acquire instance, please try again later";
            return false;
            ## error_exit( "em:em acquire failed" );
        }

        $this->has_instance = true;
        return true;
    }

    function id() {
        if ( !$this->has_instance ) {
            error_exit( "em:id() called without instance" );
        }
        return $this->instance_id;
    }

    function ip() {
        if ( !$this->has_instance ) {
            error_exit( "em:ip() called without instance" );
        }
        return $this->instance_ip;
    }
}
