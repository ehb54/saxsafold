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

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile ) {
    error_exit_hook( "Please <i>'Load SAXS'</i> first" );
}    

if ( !$cgstate->state->flex || !count( $cgstate->state->flex ) ) {
    error_exit_hook( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

## not sure if we want a timestamp, that will change the name every time
# $timestamp = `date '+%Y%m%d%H%M%S'`;
$cgstate->state->mmcrunname = "run_$request->_project";

$result->desc                 = $cgstate->state->description;
$result->pname                = $request->_project;
$result->downloads            = $cgstate->state->output_load->downloads;

$result->runname              = $cgstate->state->mmcrunname;
$result->pdbfile              = $cgstate->state->output_load->name;
$result->dcdfile              = "$result->runname.dcd";
$result->trials               = 50000;
$result->goback               = 20;
$result->temp                 = 300;
# $result->moltype_list_box     = "protein";
$result->numranges            = count( $cgstate->state->flex );
$result->reslow               = str_replace( ":", ",", str_replace( ",", "-", implode( ":", $cgstate->state->flex ) ) );
$result->dtheta               = implode( ",", array_fill( 0, $result->numranges, "30.0" ) );
$result->residue_alignment    = "10-50";
# $result->overlap_list_box     = "heavy atoms";

if ( !$cgstate->save() ) {
    error_exit_hook( "Could not save important state important state information: $cgstate->errors" );
}

echo json_encode( $result );
exit;
