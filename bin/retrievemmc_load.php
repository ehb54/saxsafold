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

if ( !$cgstate->state->mmcrunname ) {
    error_exit_hook( "No MMC run name found, did you <i>Run MMC</i>?" );
}

$result->desc                 = $cgstate->state->description;
$result->pname                = $request->_project;
$result->downloads            = $cgstate->state->output_load->downloads;
$result->mmcstride            = $cgstate->state->mmcstride;

if ( $cgstate->state->mmcdownloaded ) {

    $result->_textarea        = "MMC results already retrieved\n\n";
    $statsname                = "monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.stats";
    if ( !file_exists( $statsname ) ) {
        $result->_textarea       .= "\nError: expected MMC stats file not found!\n";
    }

    $result->_textarea       .= "\n" . `cat $statsname 2> /dev/null`;

    ## histogram
    $histname = "monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.accepted_rg_results_data.txt";
    require_once "plotlyhist.php";
    $res = plotly_hist( $histname, $result, $cgstate->state->mmcstride, $cgstate->state->mmcoffset );
    if ( strlen( $res ) ) {
        $result->_textarea .= $res;
    }
}

echo json_encode( $result );
exit;
