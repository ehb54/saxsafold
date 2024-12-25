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
include "sas.php";
$sas = new SAS( false );

$ga        = new GenApp( $input, $output );
$fdir      = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir  = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon     = $input->_logon;
$scriptdir = dirname(__FILE__);

## get state

include_once "common.php";
$cgstate = new cgrun_state();

## make sure project is loaded

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project " );
}

## process inputs here to produce output

## clean up filenames

$input->saxsiqfile[0] =  clean_up_filename_and_copy_if_needed( $input->saxsiqfile[0] );
$input->saxsprfile[0] =  clean_up_filename_and_copy_if_needed( $input->saxsprfile[0] );

## possibly plot (easy for P(r), we have the code)

## plotly

$iqfile = $input->saxsiqfile[0];

if (
    $sas->load_file( SAS::PLOT_IQ, "Exp. I(q)", $iqfile )
    && $sas->create_plot( SAS::PLOT_IQ, "I(q)", [ "Exp. I(q)" ] )
    ) {
    $output->iqplot = $sas->plot( "I(q)" );
} else {
    error_exit( $sas->last_error );
}

$prfile = $input->saxsprfile[0];

if (
    $sas->load_file( SAS::PLOT_PR, "Exp. P(r)", $prfile )
    && $sas->create_plot( SAS::PLOT_PR, "P(r)", [ "Exp. P(r)" ] )
    ) {
    $output->prplot = $sas->plot( "P(r)" );
} else {
    error_exit( $sas->last_error );
}

## save state

$cgstate->state->saxsiqfile      = $input->saxsiqfile[0];
$cgstate->state->saxsprfile      = $input->saxsprfile[0];
$cgstate->state->output_loadsaxs = $output;
$cgstate->state->qmax            = end( $output->iqplot->data[0]->x);
$cgstate->state->qmin            = $output->iqplot->data[0]->x[0];
$cgstate->state->qpoints         = count( $output->iqplot->data[0]->x);

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
