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

if ( !isset( $request->_project ) || !strlen( $request->_project ) ) {
    error_exit_hook( "A project must be selected!" );
}

if ( !file_exists( "state.json" ) ) {
    error_exit_hook( "Project $request->_project has not been define, Please <i>'Define project'</i> first" );
}    

$scriptdir = dirname( __FILE__ );
require "$scriptdir/common.php";
$cgstate = new cgrun_state();

if ( isset( $cgstate->state->output_loadsaxs ) ) {
    if ( isset( $cgstate->state->output_loadsaxs->iqplot ) ) {
        $result->iqplot = $cgstate->state->output_loadsaxs->iqplot;
    }
    if ( isset( $cgstate->state->output_loadsaxs->prplot ) ) {
        $result->prplot = $cgstate->state->output_loadsaxs->prplot;
    }
}

$result->desc  = $cgstate->state->description;
$result->pname = $request->_project;

echo json_encode( $result );
exit;
