<?php

{};

$scriptdir = dirname(__FILE__);

require_once "$scriptdir/common.php";

$waxsis_defaults =
    (object) [
        'maxq'               => 0.5
        ,'qpoints'           => 501
        ,'solvent_e_density' => .334
        ,'hostpath'          => '/genappdata/container_mounts/saxsafold'
        ,'threads'           => 12
        ,'subdir'            => 'waxsis'
        ,'convergence'       => 'normal'
        ,'random_seed'       => 'no'
    ];

# example cmd
# ssh host docker run -i --rm -v /genappdata/container_mounts/saxsafold/saxsafold-results/users/emre/struct1/tmp:/genapp/run genapp_alphamultisaxshub:Calculations-waxsis_0827_2 waxsis -s AF-G0A007-F1-model_v4-somo.pdb -q 0.5 -nq 501 -curve_q_unit A -scatt_convention q -constant_subtractions yes  -buffer_subtract total -ligand remove_cryst_agents -replace_selen yes -convergence normal -solvent_density 0.334 -envelope_dist 7 -random_seed yes -nt 8 -go

function run_waxsis( $pdb, $config, $cb_on_write ) {
    global $ga;
    global $waxsis_defaults;
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

    run_cmd( "rm -f $config->subdir/$pdb $config->subdir/intensity_waxsis.calc $config->subdir/fittedCalcInterpolated_waxsis.fit 2>/dev/null;"
             . " ln -f $pdb"
             . ( strlen( $config->expfile ) ? " " . $config->expfile : "" )
             . " $config->subdir/"
        );
    
    $cwd = preg_replace( '/^\/host/', $config->hostpath , getcwd() );

    $cmd = "ssh host docker run"
        ## n.b. the --user is looked up in the docker image's /etc/{passwd,group}!
        . " --user www-data:www-data"
        . " -i --rm"
        . " -v $cwd/$config->subdir:/genapp/run genapp_alphamultisaxshub:Calculations-waxsis_0827_2"
        . " waxsis"
        . " -s $pdb"
        . ( strlen( $config->expfile ) ? " -expfile $config->expfile " : "" )
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

    echo "$cmd\n";

    run_streaming_cmd( $cmd, $cb_on_write, false, false, 'waxsis/last_run_errors.txt' );
}

## testing

#  $cb_on_write = function( $line ) {
#     echo "callback received : $line";
# };

# run_waxsis( $argv[ 1 ], (object)[ 'qpoints' => 200, 'maxq' => .4983631, 'convergence' => 'quick', 'expfile' => 'lyzexp.dat', 'solvent_e_density' => .335 ], $cb_on_write );

# echo "\ntest done\n";
    
