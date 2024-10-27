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

$result = $cgstate->state->output_load;
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

echo json_encode( $result );
exit;
