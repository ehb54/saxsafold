<?php

{}

require_once "common.php";

## client for the elastic manager's pool of instances that run WAXSiS ( genapp elasticmanager, em_client.php )
## SAXSAFOLD_EM_CLIENT, when set, replaces the command prefix ( for testing without the manager )

class em {
    public $errors;

    private $dockerprefix = "ssh host docker exec -w /home/jobrunner/openstack/elasticmanager elasticmanager php em_client.php";
    private $flavor       = "m3.2xl";

    private $has_instance = false;
    private $instance_id;
    private $instance_ip;
    private $debug;
    
    function __construct( $debug = false ) {
        $this->debug = $debug;
        if ( strlen( getenv( "SAXSAFOLD_EM_CLIENT" ) ) ) {
            $this->dockerprefix = getenv( "SAXSAFOLD_EM_CLIENT" );
        }
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

    function has_instance() {
        return $this->has_instance;
    }

    function release_if_has_instance() {
        if ( $this->debug ) {
            echo "release if has instance\n";
        }
        if ( $this->has_instance ) {
            $this->release();
        }
    }

    ## never fatal: it also runs at shutdown and from signal handlers, and a failed release must not abort a job
    ## whose WAXSiS results are already computed. A failure is written to stderr, the job's error log.
    function release() {
        global $run_cmd_last_error_code;

        if ( !$this->has_instance ) {
            error_exit( "em:internal error - release without acquire" );
        }

        ## cleared before the call, so a signal arriving during it does not release a second time
        $this->has_instance = false;

        $cmd = "$this->dockerprefix --release $this->instance_id";
        $res = run_cmd( $cmd, false );
        if ( $run_cmd_last_error_code ) {
            fwrite( STDERR, "em: release of instance $this->instance_id failed, exit status $run_cmd_last_error_code: $res" );
        }
    }

    ## waits inside the manager until an instance is idle. A job cancelled meanwhile leaves that request queued,
    ## and the manager later gives the next idle instance to a job that no longer exists: use acquire_polling()
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

    ## one attempt that does not wait ( em_client --acquire ... --nowait ). Returns true when an instance was
    ## acquired, false when none is idle ( $this->errors empty ) or on a failure ( $this->errors set ). A miss
    ## prints the usual "error : could not acquire instance" and exits 0, so only the output tells it from a
    ## success; a WARNING line ( e.g. a flavor the manager does not have ) makes it a failure, not a miss.
    ## The termination signals are held off during the call: a job cancelled while it is in flight still
    ## learns which instance it got, and its handler gives that instance back once they are let through.
    function try_acquire( $id ) {
        global $run_cmd_last_error_code;

        if ( $this->has_instance ) {
            error_exit( "em:internal error - double acquire" );
        }
        $this->errors = "";

        $held = function_exists( 'pcntl_sigprocmask' );
        if ( $held ) {
            pcntl_sigprocmask( SIG_BLOCK, [ SIGTERM, SIGHUP, SIGINT ] );
        }

        $cmd      = "$this->dockerprefix --acquire $this->flavor $id --nowait";
        $lines    = array_values( array_filter( array_map( 'trim', run_cmd( $cmd, false, true ) ), 'strlen' ) );
        $granted  = preg_grep( '/^\d+\s+\d+\.\d+\.\d+\.\d+$/', $lines );
        $warnings = preg_grep( '/^WARNING:/', $lines );
        $acquired = false;

        if ( $run_cmd_last_error_code ) {
            $this->errors = "Could not reach the compute resource manager, please try again later";
            fwrite( STDERR, "em: [$cmd] exit status $run_cmd_last_error_code: " . implode( " | ", $lines ) . "\n" );
        } elseif ( count( $granted ) == 1 ) {
            list( $this->instance_id, $this->instance_ip ) = preg_split( '/\s+/', reset( $granted ) );
            $this->has_instance = true;
            $acquired           = true;
        } elseif ( count( $warnings ) || !in_array( "error : could not acquire instance", $lines ) ) {
            $this->errors = "Unexpected reply from the compute resource manager, please contact the administrators";
            fwrite( STDERR, "em: [$cmd] unexpected reply: " . implode( " | ", $lines ) . "\n" );
        }

        if ( $held ) {
            pcntl_sigprocmask( SIG_UNBLOCK, [ SIGTERM, SIGHUP, SIGINT ] );
        }
        return $acquired;
    }

    ## acquires by repeating try_acquire() every $interval seconds. Unlike acquire(), nothing waits inside the
    ## manager, so cancelling the job while it waits leaves nothing behind. $on_wait( $seconds_waited ) is called
    ## after each miss, e.g. to show the wait. Returns false only on a failure ( $this->errors set ).
    function acquire_polling( $id, $on_wait = null, $interval = 30 ) {
        $start = time();
        while ( !$this->try_acquire( $id ) ) {
            if ( strlen( $this->errors ) ) {
                return false;
            }
            if ( is_callable( $on_wait ) ) {
                $on_wait( time() - $start );
            }
            sleep( $interval );
        }
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

## gives back an instance held by $em however the job ends: a normal end or error_exit() through a shutdown
## function, and the signals that end PHP without running shutdown functions or destructors: SIGTERM, which
## GenApp's cancel sends ( followed 5 s later by SIGKILL, which cannot be caught ), SIGHUP and SIGINT
function em_release_on_exit( $em ) {
    register_shutdown_function( function() use ( $em ) {
        $em->release_if_has_instance();
    } );
    if ( !function_exists( 'pcntl_async_signals' ) ) {
        return;
    }
    pcntl_async_signals( true );
    foreach ( [ SIGTERM, SIGHUP, SIGINT ] as $signal ) {
        pcntl_signal( $signal, function( $signo ) use ( $em ) {
            $em->release_if_has_instance();
            exit( 128 + $signo );
        } );
    }
}
