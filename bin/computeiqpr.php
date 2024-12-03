#!/usr/local/bin/php
<?php

{};

## user defines

$plot_freq = 5;

## end user defines

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

## clear output
$ga->tcpmessage( [
                     'processing_progress' => 0.01
                     ,"progress_text"      => ''
                 ]);

## initial plots
require "sas.php";
$sas = new SAS();

$plots = (object) [];

require "$scriptdir/plotly_computeiqpr.php";

setup_computeiqpr_plots( $plots );

## plotlycomputeiqpr( $cgstate->state->output_loadsaxs, $plots );

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
$plot_count = 0;
    
progress_text( "Computing I(q) & P(r)" );

foreach ( $pdbs as $pdb ) {
    ++$pos;
    $ga->tcpmessage( [
                         "processing_progress" => $pos / count( $pdbs )
                     ] );
    
    $prdataname = "P(r) mod. $pos";

    $sas->compute_pr( "preselected/$pdb", "$prdataname comp" );
    $sas->interpolate( "$prdataname comp", "Comp.", "$prdataname interp" );
    $sas->norm_pr( "$prdataname interp", floatval( $cgstate->state->output_load->mw ), $prdataname );
    $sas->add_plot( "P(r) all mmc", $prdataname );

    # with exp data file: $cmd    = "cd preselected && Pepsi-SAXS " . $cgstate->state->saxsiqfile . " -ms " . $cgstate->state->qmax . " -ns " . $cgstate->state->qpoints . " $pdb";
    $cmd    = "cd preselected && Pepsi-SAXS -ms " . $cgstate->state->qmax * 1.01 . " -ns " . $cgstate->state->qpoints . " $pdb";
    # $ga->tcpmessage( [ "_textarea" => "$cmd\n" ] );
    $cmdres = run_cmd( $cmd, false );
    if ( $run_cmd_last_error_code ) {
        error_exit( "Error computing I(q) : $cmdres" );
    }
    $iqfile  = "preselected/" . preg_replace( '/\.pdb$/', '.out', $pdb );
    if ( !file_exists( $iqfile ) ) {
        error_exit( "Expected Iq file missing : $iqfile" );
    }

    $chi2  = -1;
    $rmsd  = -1;
    $scale = 0;
    
    $iqdataname = "I(q) mod. $pos";
        
    $sas->load_file( SAS::PLOT_IQ, "$iqdataname orig", $iqfile, false );
    $sas->interpolate( "$iqdataname orig", "Exp. I(q)", "$iqdataname interp" );
    $sas->scale_nchi2( "Exp. I(q)", "$iqdataname interp", $iqdataname, $chi2, $scale );
    $sas->add_plot( "I(q) all mmc", $iqdataname );
    
    foreach ( [
                  "$prdataname comp"
                  ,"$prdataname interp"
                  ,$prdataname
                  ,"$iqdataname orig"
                  ,"$iqdataname interp"
                  ,$iqdataname
              ] as $v) {
        $sas->remove_data( $v );
    }

    if ( !(++$plot_count % $plot_freq ) ) {
        $ga->tcpmessage(
            [
             "iqplotall" => $sas->plot( "I(q) all mmc" )
             ,"prplotall" => $sas->plot( "P(r) all mmc" )
            ]
            );
# limit for testing
#        if ( $plot_count > 100 ) {
#            break;
#        }
    }
}
    
$output->iqplotall = $sas->plot( "I(q) all mmc" );
$output->prplotall = $sas->plot( "P(r) all mmc" );

## save state

$cgstate->state->output_iqpr  = $output;

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

$output->processing_progress = 0;
progress_text( 'Processing complete', '' );

echo json_encode( $output );
