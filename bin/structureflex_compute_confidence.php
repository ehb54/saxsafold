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
    error_exit_hook( "Project $request->_project has not been loaded, Please <i>'load structure'</i> first" );
}    

$scriptdir = dirname( __FILE__ );
require "$scriptdir/common.php";
$cgstate = new cgrun_state();

if ( !$cgstate->state->output_load ) {
    error_exit_hook( "Project $request->_project has a state file, but apparently not been loaded, Please <i>'load structure'</i> first" );
}    

if ( !$cgstate->state->is_alphafold ) {
    error_exit_hook( "Project $request->_project is not identified as an AlphaFold structure" );
}    

$pdbfile = $cgstate->state->output_load->name;
if ( !file_exists( $pdbfile ) ) {
    error_exit_hook( "PDB file $pdbfile could not be read" );
}

## read and process

## currently bogus results

$result->nflex = 3;
$result->{"nflex-flexrange-0"} = "1,20";
$result->{"nflex-flexrange-1"} = "30,40";
$result->{"nflex-flexrange-2"} = "70,80";
    
echo json_encode( $result );
exit;
