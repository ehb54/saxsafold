#!/usr/local/bin/php
<?php

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

## does the project already exist ?


## make sure project is loaded

if ( !$cgstate->state->loaded ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

## does the project already exist ?

if ( !$cgstate->state->loaded ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile || !isset($cgstate->state->qmin) || !$cgstate->state->qmax || !$cgstate->state->qpoints ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

if ( !$cgstate->state->flex || !count( $cgstate->state->flex ) ) {
    error_exit( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !$cgstate->state->mmcrunname ) {
    error_exit( "No MMC run name found, did you <i>Run MMC</i>?" );
}

if ( !$cgstate->state->mmcdownloaded || !$cgstate->state->mmcextracted || !strlen( $cgstate->state->mmcextracted ) ) {
    error_exit( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    


## initial plots
$plots = (object) [];

require "$scriptdir/plotly_computeiqpr.php";
plotlycomputeiqpr( $cgstate->state->output_loadsaxs, $plots );

## initial message 
$tmpout = (object)[];

$tmpout->iqplot       = $plots->iqplot;
$tmpout->iqplotall    = $plots->iqplotall;
$tmpout->iqplotsel    = $plots->iqplotsel;

$tmpout->prplot       = $plots->prplot;
$tmpout->prplotall    = $plots->prplotall;
$tmpout->prplotsel    = $plots->prplotsel;

$ga->tcpmessage( $tmpout );

## process inputs here to produce output

if ( $input->iqmethod != "pepsi" ) {
    error_exit( "Only PEPSI-SAXS currently supported" );
}

## compute Iq


$pdbs = run_cmd( "cd preselected 2> /dev/null && ls *.pdb", false, true );
if ( $run_cmd_last_error_code ) {
    error_exit( "Error getting selected PDBs : Code $run_cmd_last_error_code\n" );
}

if ( !count( $pdbs ) ) {
    error_exit( "Hmm. No selected PDBs found!, perhaps retry <i>Retrieve MMC</i>" );
}

$pos = 0;
foreach ( $pdbs as $pdb ) {
    ++$pos;
    $ga->tcpmessage( [
                         "_progress" => $pos / count( $pdbs )
                         , "_textarea" => "Processing model $pdb\n"
                     ] );
    
    # with exp data file: $cmd    = "cd preselected && Pepsi-SAXS " . $cgstate->state->saxsiqfile . " -ms " . $cgstate->state->qmax . " -ns " . $cgstate->state->qpoints . " $pdb";
    $cmd    = "cd preselected && Pepsi-SAXS -ms " . $cgstate->state->qmax . " -ns " . $cgstate->state->qpoints . " $pdb";
    # $ga->tcpmessage( [ "_textarea" => "$cmd\n" ] );
    $cmdres = run_cmd( $cmd, false );
    if ( $run_cmd_last_error_code ) {
        error_exit( "Error computing I(q) : $cmdres" );
    }
    $iqfile  = "preselected/" . preg_replace( '/\.pdb$/', '.out', $pdb );
    if ( !file_exists( $iqfile ) ) {
        error_exit( "Expected Iq file missing : $iqfile" );
    }
        
    $addcurveres = plotlyloadcurve( $plots->iqplotall, $iqfile, "model $pos", 225 * 700 );
    if ( strlen( $addcurveres ) ) {
        error_exit( "Error loading computed Iq data : $addcurveres" );
    }
    $ga->tcpmessage( [ "iqplotall" => $plots->iqplotall ] );
}
    
$output->iqplotall = $plots->iqplotall;

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
