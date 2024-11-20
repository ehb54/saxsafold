<?php

{};

# 

$scriptdir = dirname(__FILE__);

require_once "$scriptdir/common.php";

# ssh host docker run -i --rm -v /genappdata/container_mounts/saxsafold/saxsafold-results/users/emre/struct1/tmp:/genapp/run genapp_alphamultisaxshub:Calculations-waxsis_0827_2 waxsis -s AF-G0A007-F1-model_v4-somo.pdb -q 0.5 -nq 501 -curve_q_unit A -scatt_convention q -constant_subtractions yes  -buffer_subtract total -ligand remove_cryst_agents -replace_selen yes -convergence normal -solvent_density 0.334 -envelope_dist 7 -random_seed yes -nt 8 -go

$waxsis_config =
    (object) [
        "maxq"               => 0.5
        ,"qpoints"           => 501
        ,"solvent_e_density" => .334
        ,"hostpath"          => '/genappdata/container_mounts/saxsafold'
        ,"threads"           => 12
    ];

# -q 0.5 -nq 501 -curve_q_unit A -scatt_convention q -constant_subtractions yes  -buffer_subtract total -ligand remove_cryst_agents -replace_selen yes -convergence normal -solvent_density 0.334 -envelope_dist 7 -random_seed yes -nt 8 -go

function run_waxsis( $pdb, $config ) {
    global $ga;
    global $waxsis_config;
    $config = $waxsis_config;
    if ( !file_exists( $pdb ) ) {
        error_exit( "$pdb does not exist" );
    }

    $cwd = preg_replace( '/^\/host/', $config->hostpath , getcwd() );

    $cmd = "ssh host docker run -i --rm"
        . " -v $cwd:/genapp/run genapp_alphamultisaxshub:Calculations-waxsis_0827_2"
        . " waxsis"
        . " -s $pdb"
        . " -q $config->maxq"
        . " -nq $config->qpoints"
        . " -curve_q_unit A"
        . " -scatt_convention q"
        . " -constant_subtractions yes"
        . " -buffer_subtract total"
        . " -ligand remove_cryst_agents"
        . " -replace_selen yes"
        . " -convergence normal"
        . " -solvent_density $config->solvent_e_density"
        . " -envelope_dist 7"
        . " -random_seed yes"
        . " -nt $config->threads"
        . " -go"
        ;
    echo "$cmd\n";
}

## testing

run_waxsis( $argv[ 1 ], "" );
