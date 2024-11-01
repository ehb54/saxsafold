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

if ( !$cgstate->state->loaded ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project " );
}

if ( count( $input->flexrange ) ) {
   $cgstate->state->flex = $input->flexrange;
}

$output->_textarea = "Flexible regions saved\n";

if ( $cgstate->state->mmcdownloaded ) {
   unset( $cgstate->state->mmcdownloaded );
   $output->_textarea .= "Note: previous MMC results have already been retrieved, you will need to Run MMC & Retrieve MMC again for further processing\n";
}

$cgstate->state->mmcstride = 10;

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea


#$output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
#$output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
