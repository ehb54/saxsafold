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

if ( !$cgstate->state->output_load->struct ) {
    error_exit_hook( "Project $request->_project has been defined, but apparently not been loaded, Please <i>'Load structure'</i> first" );
}    

if ( !$cgstate->state->is_alphafold ) {
    error_exit_hook( "Project $request->_project is not identified as an AlphaFold structure" );
}    

$pdbfile = $cgstate->state->output_load->name;
if ( !file_exists( $pdbfile ) ) {
    error_exit_hook( "PDB file $pdbfile could not be read" );
}

## read and process

$cmd = "$scriptdir/calcs/compute_flexible_regions.pl " . $request->{'autoflex-autoflexconfidencelevel'} . " 5 $pdbfile 2> /dev/null";
$cmdres = explode( "\n", trim( `$cmd` ) );

$selects = "background white; color structure; ribbon only";

$result->nflex = count( $cmdres );
for ( $i = 0; $i < $result->nflex; ++$i ) {
    $result->{"nflex-flexrange-$i"} = $cmdres[ $i ];
    if ( strlen( trim( $cmdres[ $i ] ) ) ) {
        $selects .= ";select " . str_replace( ",", "-", $cmdres[ $i ] ) . "; color green";
    }
}

$result->struct = $cgstate->state->output_load->struct;
$result->struct->script = $selects;

echo json_encode( $result );
exit;
