#!/usr/local/bin/php
<?php
{};

$request = json_decode( file_get_contents( "php://stdin" ) );
$result  = (object)[];

function error_exit_hook( $msg ) {
    global $result;
    $result->error = $msg;
    echo json_encode( $result );
    exit;
}

if ( $request === NULL ) {
    error_exit_hook( "Invalid JSON input provided" );
}

if ( !strlen( $request->_project ) ) {
    error_exit_hook( "A project must be selected!" );
}

if ( !file_exists( "state.json" ) ) {
    error_exit_hook( "Project $request->_project has not been defined, Please <i>'Define project'</i> first" );
}    

$scriptdir = dirname( __FILE__ );
require "$scriptdir/common.php";
$cgstate = new cgrun_state();

if ( !isset( $cgstate->state->output_load ) ) {
    error_exit_hook( "Project $request->_project has been defined, but apparently not been loaded, Please <i>'Load structure'</i> first" );
}    

if ( !isset( $cgstate->state->saxsiqfile )
     || !isset( $cgstate->state->saxsprfile )
     || !isset( $cgstate->state->output_loadsaxs )
     || !isset( $cgstate->state->output_load ) ) {
    error_exit_hook( "Please <i>'Load Structure'</i> first" );
}    

if ( !isset( $cgstate->state->flex ) || !count( $cgstate->state->flex ) ) {
    error_exit_hook( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !isset( $cgstate->state->mmcdownloaded )
     || !$cgstate->state->mmcdownloaded
     || !isset( $cgstate->state->mmcextracted )
     || !strlen( $cgstate->state->mmcextracted ) ) {
    error_exit_hook( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    

if ( !isset( $cgstate->state->nnlsiqresults )
    || !isset( $cgstate->state->nnlsprresults ) ) {
    error_exit_hook( "No preselected results found, Please run <i>Compute I(q)/P(r)</i> first" );
}

if ( isset( $cgstate->state->output_load ) ) {
    if ( isset( $cgstate->state->output_load->iqplot ) ) {
        $result->iqplot = &$cgstate->state->output_load->iqplot;
    }
}

if ( isset( $cgstate->state->output_final ) ) {
    if ( isset( $cgstate->state->output_final->iqplotwaxsis ) ) {
        $result->iqplotwaxsis = &$cgstate->state->output_final->iqplotwaxsis;
    }
    if ( isset( $cgstate->state->output_final->iqresultswaxsis ) ) {
        $result->iqresultswaxsis = &$cgstate->state->output_final->iqresultswaxsis;
    }
    if ( isset( $cgstate->state->output_final->csvdownloads ) ) {
        $result->csvdownloads = &$cgstate->state->output_final->csvdownloads;
    }
}


$result->desc                      = $cgstate->state->description;
$result->pname                     = $request->_project;
$result->downloads                 = $cgstate->state->output_load->downloads;

echo json_encode( $result );
exit;
