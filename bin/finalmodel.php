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

if ( !isset( $cgstate->state->output_iqpr ) ) {
    error_exit( "Did you <i>Compute I(q)/P(r)</i>?" );
}

if ( !isset( $cgstate->state->output_iqpr->iqplotall ) ) {
    error_exit( "Did you successfully <i>Compute I(q)/P(r)</i>?" );
}

## process inputs here to produce output

$plotjsonname = "iqprplots.json";

dt_store_now( "plot-0" );

$plots = (object)[
    "_height" => floatval( $input->_height )
    ,"_width" => floatval( $input->_width )
    ,"plotlydata" => &$cgstate->state->output_iqpr->iqplotall
#    ,"plotlydata" => &$cgstate->state->output_iqpr->prplotall
    ];

dt_store_now( "plot-1" );
# save file

if ( !file_put_contents( $plotjsonname, json_encode( $plots ) ) ) {
    error_exit( "Error creating $plotjsonname" );
}

dt_store_now( "plot-2" );

## call python to produce images

$res = run_cmd( "$scriptdir/plotiqprall.py $plotjsonname" );

dt_store_now( "plot-3" );

## json decode response

$plotsobj = json_decode( $res );

dt_store_now( "plot-4" );

## summary times

$ga->tcpmessage( [ "_textarea" =>
                   "make object           : " . dhms_from_minutes( dt_store_duration( "plot-0", "plot-1" ) ) . "\n"
                   . "save $plotjsonname   : " . dhms_from_minutes( dt_store_duration( "plot-1", "plot-2" ) ) . "\n"
                   . "run python            : " . dhms_from_minutes( dt_store_duration( "plot-2", "plot-3" ) ) . "\n"
                   . "json decode response  : " . dhms_from_minutes( dt_store_duration( "plot-3", "plot-4" ) ) . "\n"
                   . "\n"
                 ]);

## log results to textarea

$output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
$output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

$output->plottest = <<<__EOD
<div><img style="width:{$plotsobj->width}px;height:{$plotsobj->height}px" src="data:image/png;base64;charset=utf-8, $plotsobj->plotlydata" /></div>
__EOD;


echo json_encode( $output );
