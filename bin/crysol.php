<?php

{};

$scriptdir = dirname(__FILE__);

require_once "$scriptdir/common.php";

$crysol_defaults =
    (object) [
        'maxq'                  => 0.5
        ,'qpoints'              => 501
        ,'solvent_e_density'    => .334
        ,'random_seed'          => 'no'
        ,'install_path'         => '/host/ATSAS-3.2.1-1'
        ,'hydration_shell'      => 'directional'
        ,'fibonacci_grid'       => 17
        ,'spherical_harmonics'  => 20
        ,'expfile'              => ''
        ,'subdir'               => '.'
    ];

function run_crysol( $pdb, $config, $cb_on_write ) {
    global $ga;
    global $crysol_defaults;
    global $run_cmd_last_error_code;

    $config = object_set_defaults( $config, $crysol_defaults );

    if ( !file_exists( $pdb ) ) {
        error_exit( "Structure file '$pdb' does not exist" );
    }

    if ( strlen( $config->expfile ) && !file_exists( $config->expfile ) ) {
        $dir = getcwd();
        error_exit( "I(q) file '$config->expfile' does not exist dir '$dir'" );
    }

    $pdbnopath = preg_replace( '/^.*\//', '', $pdb );

## any cleanup needed ?
#    run_cmd( "rm -f $config->subdir/$pdb $config->subdir/intensity_waxsis.calc $config->subdir/fittedCalcInterpolated_waxsis.fit 2>/dev/null;"
#             . " ln -f $pdb"
#             . ( strlen( $config->expfile ) ? " " . $config->expfile : "" )
#             . " $config->subdir/"
#        );

    $cmd =
        "cd $config->subdir &&"
        . " env ATSAS=$config->install_path"
        . " $config->install_path/bin/crysol"
        . ( strlen( $config->expfile ) ? " -expfile " . basename( $config->expfile ) : "" )
        . " -smax $config->maxq"
        . " -ns $config->qpoints"
        . " -dns $config->solvent_e_density"
        . " -fb $config->fibonacci_grid"
        . " -lm $config->spherical_harmonics"
        #  . " -units A" ## ? what are supported unit values
        . " $pdbnopath"
        . ( strlen( $config->expfile ) ? ' ' .  basename( $config->expfile ) : '' )
        . " 2>&1" # expose error output
        ;

    $cb_on_write( "$cmd\n" );

    $cb_on_write( "Calling CRYSOL\n" );

    run_streaming_cmd( $cmd, $cb_on_write, false, false, 'waxsis/last_run_errors.txt' );

    $cb_on_write( "CRYSOL run returned code $run_cmd_last_error_code\n" );

    if ( $run_cmd_last_error_code ) {
        error_exit( "Error running CRYSOL on structure" );
    }
    return true;
}

## testing
/*
$cb_on_write = function( $line ) {
    echo "callback received : $line";
};

run_crysol( 
    $argv[ 1 ]
    , (object)[ 
        'qpoints' => 200
        , 'maxq' => .4983631
        , 'convergence' => 'quick'
#        , 'expfile' => 'lyzexp.dat'
        , 'solvent_e_density' => .335 
    ]
    , $cb_on_write 
    );

# echo "\ntest done\n";

*/
