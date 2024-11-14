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

if ( !$cgstate->state->output_load ) {
    error_exit_hook( "Project $request->_project has been defined, but apparently not been loaded, Please <i>'Load structure'</i> first" );
}    

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile || !$cgstate->state->output_loadsaxs) {
    error_exit_hook( "Please <i>'Load SAXS'</i> first" );
}    

if ( !$cgstate->state->flex || !count( $cgstate->state->flex ) ) {
    error_exit_hook( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !$cgstate->state->mmcdownloaded || !$cgstate->state->mmcextracted || !strlen( $cgstate->state->mmcextracted ) ) {
    error_exit_hook( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    

$result->desc                      = $cgstate->state->description;
$result->pname                     = $request->_project;
$result->downloads                 = $cgstate->state->output_load->downloads;

require "$scriptdir/plotly_computeiqpr.php";
plotlycomputeiqpr( $cgstate->state->output_loadsaxs, $result );

echo json_encode( $result );
exit;
