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
    if ( isset( $cgstate->state->output_loadsaxs->guinierplot ) ) {
        $result->guinierplot = $cgstate->state->output_loadsaxs->guinierplot;
    }
}

## Guinier fields below the graph show the settings and range actually used, so a resubmit reproduces the fit
if ( isset( $cgstate->state->exp_guinier ) ) {
    $g      = $cgstate->state->exp_guinier;
    $auto   = ( ( $g->source ?? "" ) == "user" && isset( $g->auto ) ) ? $g->auto : $g;
    $params = isset( $auto->params ) ? $auto->params : (object)[];
    if ( isset( $auto->qmin ) ) {
        $result->guinier_qmin     = sprintf( "%.4f", $params->qmin ?? $auto->qmin );
        $result->guinier_qmax     = sprintf( "%.4f", $params->qmax ?? $auto->qmax );
        $result->guinier_qrgmax   = sprintf( "%.2f", $params->qrgmax ?? 1.3 );
        $result->guinier_maxrelsd = isset( $params->maxrelsd ) ? sprintf( "%g", $params->maxrelsd ) : "";
    }
    $result->saxs_rg_override = ( $g->source ?? "" ) == "user" ? sprintf( "%.2f", $g->rg ) : "";
}

$result->desc  = $cgstate->state->description;
$result->pname = $request->_project;

echo json_encode( $result );
exit;
