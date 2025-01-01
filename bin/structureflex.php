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

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project " );
}

require_once "remove.php";

$restore_old_data = function() {
    global $cgstate;
    global $ga;
    global $input;

    $obj = (object)[];

    $obj->desc  = $cgstate->state->description;
    $obj->pname = $input->_project;

    if ( isset( $cgstate->state->output_load ) ) {
        $obj = $cgstate->state->output_load;
        unset( $obj->_textarea );
    }

    if ( isset( $cgstate->state->flex ) && count( $cgstate->state->flex ) ) {
        $obj->nflex = count( $cgstate->state->flex );
        for ( $i = 0; $i < $obj->nflex; ++$i ) {
            $obj->{"nflex-flexrange-$i"} = $cgstate->state->flex[ $i ];
        }
    }

    $obj->processing_progress = 0;

    $ga->tcpmessage( $obj );
};

question_prior_results( __FILE__, $restore_old_data );

function mod_ranges( $a, $b ) {
    $av = explode( ',', $a );
    $bv = explode( ',', $b );

    $ai = intval( $av[0] );
    $bi = intval( $bv[0] );
    
    return $ai > $bi;
}

if ( count( $input->flexrange ) ) {
    usort( $input->flexrange, fn( $a, $b ) => mod_ranges( $a, $b ) );
    $cgstate->state->flex = $input->flexrange;
}

$output->_textarea = "Flexible regions saved\n";

if ( isset( $cgstate->state->mmcdownloaded ) ) {
   unset( $cgstate->state->mmcdownloaded );
   $output->_textarea .= "Note: previous MMC results have already been retrieved, you will need to Run MMC & Retrieve MMC again for further processing\n";
}

$cgstate->state->mmcstride = 10;
$output->struct = $cgstate->state->output_load->struct;
$output->struct->script = "background white; color structure; ribbon only";
if ( isset( $cgstate->state->flex ) && count( $cgstate->state->flex ) ) {
    $output->struct->script .= "; select ";
    foreach ( $cgstate->state->flex as $v ) {
        $vs = explode( ",", $v );
        if ( count( $vs ) == 2 ) {
            $output->struct->script .=  $vs[0] . "-" . $vs[1] . ",";
        }
    }
    $output->struct->script = preg_replace( '/,$/', '', $output->struct->script );
    $output->struct->script .= "; color green";
}

$cgstate->state->output_flex = $output;

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea


#$output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
#$output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
