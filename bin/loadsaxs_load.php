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

## Guinier fields below the graph show the settings and range actually used ( as q^2, like the plot ),
## so a resubmit reproduces the fit and an edit refines it
if ( isset( $cgstate->state->exp_guinier->qmin ) ) {
    $g      = $cgstate->state->exp_guinier;
    $params = isset( $g->params ) ? $g->params : (object)[];
    $qmin   = $params->qmin ?? $g->qmin;
    $qmax   = $params->qmax ?? $g->qmax;
    $result->guinier_q2min    = sprintf( "%.6f", $qmin * $qmin );
    $result->guinier_q2max    = sprintf( "%.6f", $qmax * $qmax );
    $result->guinier_qrgmax   = sprintf( "%.2f", $params->qrgmax ?? 1.3 );
    $result->guinier_maxrelsd = isset( $params->maxrelsd ) ? sprintf( "%g", $params->maxrelsd ) : "";
}

$result->desc  = $cgstate->state->description;
$result->pname = $request->_project;

echo json_encode( $result );
exit;
