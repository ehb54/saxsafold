#!/usr/local/bin/php
<?php

{};

## user defines

## setting dcddir to the mmc version for now
## later adjust subsequent process to recognize DCD
$dcddir = "monomer_monte_carlo";

$self = __FILE__;

if ( count( $argv ) != 2 ) {
    echo '{"error":"$self requires a JSON input object"}';
    exit;
}

$json_input = $argv[1];

$input = json_decode( $json_input );

if ( !$input ) {
    echo '{"error":"$self - invalid JSON."}';
    exit;
}

$output = (object)[];

## process inputs here to produce output

include "genapp.php";
include "datetime.php";

$ga        = new GenApp( $input, $output );
$fdir      = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir  = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon     = $input->_logon;
$scriptdir = dirname(__FILE__);

## get state

require "common.php";
$cgstate = new cgrun_state();

## make sure project is defined

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project " );
}

## any other checks?

## run name
$runname = "run_$input->_project";

## make $runname/$dcddir
if ( is_dir( "$runname/$dcddir" ) ) {
    run_cmd( "rm -fr $runname/$dcddir;  mkdir $runname/$dcddir" );
} else {
    run_cmd( "mkdir -p $runname/$dcddir" );
}

## copy topfile & dcd file to $dcddir
$ga->tcpmessage( [
                     'processing_progress' => 0.25
                     ,"progress_text"      => 'Copying DCD file'
                 ]);

$cmd = "cp " . $input->topfile[0] . " $runname/$dcddir/$runname.pdb && cp " .  $input->topfile[0] . " $runname/$dcddir/" . $cgstate->state->output_load->name . " && ln " . $input->dcdfile[0] . " $runname/$dcddir/$runname.dcd";
run_cmd( $cmd );

## compute Rgs, stats
$ga->tcpmessage( [
                     'processing_progress' => 0.5
                     ,"progress_text"      => 'Computing Rgs on each DCD frame'
                 ]);


$cmd = "cd $runname/$dcddir && $scriptdir/calcs/dcdrg.py --dcdfile $runname.dcd --pdbfile $runname.pdb";
run_cmd( $cmd );

$ga->tcpmessage( [
                     'processing_progress' => 0
                     ,"progress_text"      => 'Complete'
                 ]);

## save state

$cgstate->state->mmcrunname = "run_$input->_project";

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

$output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
$output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
