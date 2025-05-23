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
    error_exit_hook( "Project $request->_project has not been defined, Please <i>'Define project'</i> first" );
}    

$scriptdir = dirname( __FILE__ );
require "$scriptdir/common.php";
$cgstate = new cgrun_state();

if ( !isset( $cgstate->state->saxsiqfile ) || !isset( $cgstate->state->saxsprfile ) ) {
    error_exit_hook( "Please <i>'Load SAXS'</i> first" );
}    

if ( isset( $cgstate->state->output_load ) ) {
    $result = $cgstate->state->output_load;
    if ( isset( $cgstate->state->is_alphafold ) && $cgstate->state->is_alphafold ) {
        $result->confidencelegend = confidence_legend();
    } else {
        $result->confidencelegend = "";
    }
}

$result->desc  = $cgstate->state->description;
$result->pname = $request->_project;


echo json_encode( $result );
exit;
