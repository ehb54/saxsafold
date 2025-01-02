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

if ( !isset( $cgstate->state->output_load ) ) {
    error_exit_hook( "Project $request->_project has been defined, but apparently not been loaded, Please <i>'Load structure'</i> first" );
}    

$result = $cgstate->state->output_load;
unset( $result->_textarea );
$result->desc  = $cgstate->state->description;
$result->pname = $request->_project;
if ( isset( $cgstate->state->is_alphafold ) && $cgstate->state->is_alphafold ) {
    $result->autoflex = true;
}

if ( isset( $cgstate->state->flex ) && count( $cgstate->state->flex ) ) {
    $result->nflex = count( $cgstate->state->flex );
    for ( $i = 0; $i < $result->nflex; ++$i ) {
        $result->{"nflex-flexrange-$i"} = $cgstate->state->flex[ $i ];
    }
}

if ( isset( $cgstate->state->output_flex ) && isset( $cgstate->state->output_flex->struct ) ) {
    $result->struct = $cgstate->state->output_flex->struct;
}

echo json_encode( $result );
exit;
