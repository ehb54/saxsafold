<?php

{};

$scriptdir = dirname(__FILE__);

require_once "$scriptdir/common.php";

## should be converted to a CLASS!

$waxsis_defaults =
    (object) [
        'maxq'               => 0.5
        ,'qpoints'           => 501
        ,'solvent_e_density' => .334
        ,'hostpath'          => '/genappdata/container_mounts/saxsafold'
        ,'threads'           => $waxsis_threads
        ,'subdir'            => 'waxsis'
        ,'convergence'       => $waxsis_convergence_mode
        ,'random_seed'       => 'no'
        ,'host'              => 'host'
    ];

function waxsis_fitted_data_initval ( $data ) {
    $data->x       = [];
    $data->y       = [];
    $data->error_y = [];
}

$waxsis_fitted_data = (object)[];

waxsis_fitted_data_initval( $waxsis_fitted_data );

# example cmd
# ssh host docker run -i --rm -v /genappdata/container_mounts/saxsafold/saxsafold-results/users/emre/struct1/tmp:/genapp/run genapp_alphamultisaxshub:Calculations-waxsis_0827_2 waxsis -s AF-G0A007-F1-model_v4-somo.pdb -q 0.5 -nq 501 -curve_q_unit A -scatt_convention q -constant_subtractions yes  -buffer_subtract total -ligand remove_cryst_agents -replace_selen yes -convergence normal -solvent_density 0.334 -envelope_dist 7 -random_seed yes -nt 8 -go

function waxis_load_data( $file, $data_struct ) {
    waxsis_fitted_data_initval( $data_struct );
    if ( !file_exists( $file ) ) {
        echo "Expected WAXSiS produced fit '$file' does not exist\n";
        error_exit( "Expected WAXSiS produced fit '$file' does not exist" );
    }
    echo "Expected WAXSiS produced fit '$file' DOES exist\n";
    if ( $data = file_get_contents( $file ) ) {
        $plotin  = explode( "\n", $data );

        echo "Got " . count( $plotin ) . " lines of data\n";

        # remove comment lines
        $plotin = preg_grep( '/^\s*#/', $plotin, PREG_GREP_INVERT );

        foreach ( $plotin as $linein ) {
            $linevals = preg_split( '/\s+/', trim( $linein ) );

            if ( count( $linevals ) > 2 ) {
                $data_struct->x[]       = floatval($linevals[0]);
                $data_struct->y[]       = floatval($linevals[1]);
                $data_struct->error_y[] = floatval($linevals[2]);
            } else {
                if ( count( $linevals ) == 2 ) {
                    $data_struct->x[]    = floatval($linevals[0]);
                    $data_struct->y[]    = floatval($linevals[1]);
                }
            }
        }
    }
}

function run_waxsis( $pdb, $config, $cb_on_write, $exit_waxsis_error = true ) {
    global $ga;
    global $waxsis_defaults;
    global $waxsis_fitted_data;
    global $waxsis_retries;
    global $run_cmd_last_error_code;

    waxsis_fitted_data_initval( $waxsis_fitted_data );
    
    $config = object_set_defaults( $config, $waxsis_defaults );
    echo json_encode( $config, JSON_PRETTY_PRINT ) . "\n";

    if ( !file_exists( $pdb ) ) {
        error_exit( "Structure file '$pdb' does not exist" );
    }

    if ( strlen( $config->expfile ) && !file_exists( $config->expfile ) ) {
        $dir = getcwd();
        error_exit( "I(q) file '$config->expfile' does not exist dir '$dir'" );
    }
    
    if ( !mkdir_if_needed( $config->subdir ) ) {
        error_exit( "could not create subdir '$config->subdir'" );
    }

    $pdbnopath = preg_replace( '/^.*\//', '', $pdb );

    run_cmd( "rm -f $config->subdir/$pdb $config->subdir/intensity_waxsis.calc $config->subdir/fittedCalcInterpolated_waxsis.fit 2>/dev/null;"
             . " ln -f $pdb"
             . ( strlen( $config->expfile ) ? " " . $config->expfile : "" )
             . " $config->subdir/"
        );
    
    $cwd = preg_replace( '/^\/host/', $config->hostpath , getcwd() );

    $cmd = "ssh $config->host docker run"
        ## n.b. the --user is looked up in the docker image's /etc/{passwd,group}!
        . " --user www-data:www-data"
        . " -i --rm"
        . " -v $cwd/$config->subdir:/genapp/run genapp_alphamultisaxshub:Calculations-waxsis_0827_2"
        . " waxsis"
        . " -s $pdbnopath"
        . ( strlen( $config->expfile ) ? " -expfile " . basename( $config->expfile ) : "" )
        . " -q $config->maxq"
        . " -nq $config->qpoints"
        . " -curve_q_unit A"
        . " -scatt_convention q"
        . " -constant_subtractions yes"
        . " -buffer_subtract total"
        . " -ligand remove_cryst_agents"
        . " -replace_selen yes"
        . " -convergence $config->convergence"
        . " -solvent_density $config->solvent_e_density"
        . " -envelope_dist 7"
        . " -random_seed $config->random_seed"
        . " -nt $config->threads"
        . " -go"
        . " 2>&1" # expose error output
        ;

    # $cb_on_write( "$cmd\n" );

    for ( $retries = 0; $retries < $waxsis_retries; ++$retries ) {
        # $cb_on_write( "Calling WAXSiS run\n" );
        run_streaming_cmd( $cmd, $cb_on_write, false, false, 'waxsis/last_run_errors.txt' );
        # $cb_on_write( "WAXSiS run returned code $run_cmd_last_error_code\n" );
        if ( !$run_cmd_last_error_code ) {
            break;
        }
        $cb_on_write( "Retrying failed WAXSiS run " . ( $retries + 1 ) . "\n" );
    }

    if ( $run_cmd_last_error_code ) {
        if ( $exit_on_waxsis_error ) {
            error_exit( "Error running WAXSiS on structure" );
        }
        return false;
    }

    return true;
}

## testing

# waxis_load_data( "waxsis/fittedCalcInterpolated_waxsis.fit", $waxsis_fitted_data );
# echo json_encode( $waxsis_fitted_data, JSON_PRETTY_PRINT ) . "\n";

#  $cb_on_write = function( $line ) {
#     echo "callback received : $line";
# };

# run_waxsis( $argv[ 1 ], (object)[ 'qpoints' => 200, 'maxq' => .4983631, 'convergence' => 'quick', 'expfile' => 'lyzexp.dat', 'solvent_e_density' => .335 ], $cb_on_write );

# echo "\ntest done\n";
    
