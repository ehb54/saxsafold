<?php

{};

require_once "computeiqpr_defines.php";

function initial_model_set( &$results, &$errormsg ) {
    global $cgstate;
    global $mdatas;

    $results = [];

    foreach ( $mdatas as $k => $mdata ) {
        if ( isset( $cgstate->state->{$mdata->tags->nnlsresults} ) ) {
            foreach ( $cgstate->state->{$mdata->tags->nnlsresults} as $k => $v ) {
                $modv = explode( ' ', $k );
                $results[] = intval( end( $modv ) );
            }
        }
    }
    
    if ( isset( $cgstate->state->pr_nnlsresults ) ) {
        foreach ( $cgstate->state->pr_nnlsresults as $k => $v ) {
            $modv = explode( ' ', $k );
            $results[] = intval( end( $modv ) );
        }
    }
        
    if ( isset( $cgstate->state->prwe_nnlsresults ) ) {
        foreach ( $cgstate->state->prwe_nnlsresults as $k => $v ) {
            $modv = explode( ' ', $k );
            $results[] = intval( end( $modv ) );
        }
    }
        
    if ( !count( $results ) ) {
        $errormsg = "No results found";
        return false;
    }

    $results = array_unique( $results, SORT_NUMERIC );

    sort( $results );

    return true;
}

function add_adjacent_frames( $adjacentframes, &$results, &$errormsg ) {
    global $cgstate;
    if ( !isset( $cgstate->state->mmcframecount ) ) {
        $errormsg = "\$cgtate->state->mmcframecount is not set";
        return false;
    }

    if ( $cgstate->state->mmcframecount <= 0 ) {
        $errormsg = "\$cgtate->state->mmcframecount is not set";
        return false;
    }
    
    $extendedset = [];
    
    foreach ( $results as $v ) {
        for ( $i = max( $v - $adjacentframes, 0 );
              $i <= min( $v + $adjacentframes, $cgstate->state->mmcframecount - 1 );
              ++$i ) {
            $extendedset[] = $i;
        }
    }

    $results = array_unique( $extendedset, SORT_NUMERIC );
    
    sort( $results );

    return true;
}

function get_frames_to_run( $checkdir, $frames, &$framesleft, &$errormsg ) {
    global $cgstate;
    global $max_frame_digits;
    global $waxsis_suffix;

    if ( !is_dir( $checkdir ) ) {
        $errormsg = "Expected directory '$checkdir' does not exist";
        return false;
    }        

    if ( !isset( $cgstate->state->output_load->name ) ) {
        $errormsg = "No name defined";
        return false;
    }

    $name = preg_replace( '/\.pdb$/', '', $cgstate->state->output_load->name );

    $framesleft = [];

    foreach ( $frames as $frame ) {
        $frame_padded = str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
        $waxsis_file = "$checkdir/$name-m$frame_padded-waxsis${waxsis_suffix}.dat";
        if ( !file_exists( $waxsis_file ) ) {
            $framesleft[] = $frame;
        }
    }

    return true;
}

function link_existing_frames( $frames, $fromdir, $todir, &$names, &$errormsg ) {
    global $cgstate;
    global $max_frame_digits;

    if ( !is_dir( $todir ) ) {
        $errormsg = "Expected directory '$todir' does not exist";
        return false;
    }        

    if ( !isset( $cgstate->state->output_load->name ) ) {
        $errormsg = "No name defined";
        return false;
    }

    $names = [];

    $name = preg_replace( '/\.pdb$/', '', $cgstate->state->output_load->name );

    foreach ( $frames as $frame ) {
        $frame_padded = str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
        $model_file = "$fromdir/$name-m$frame_padded.pdb";
        $names[]    = "$name-m$frame_padded.pdb";
        if ( file_exists( $model_file ) ) {
            run_cmd( "ln -f $model_file $todir" );
        } else {
            extract_dcd_frame(
                $frame
                ,$cgstate->state->output_load->name
                ,"monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd"
                ,$todir
                ,true
                );
        }
    }

    return true;
}

/*
function final_hist( &$results ) {
    global $cgstate;

    if ( $cgstate->state->mmcdownloaded ) {
        ## histogram
        $histname = "monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.accepted_rg_results_data.txt";
        if ( file_exists( $histname ) ) {
            require_once "plotlyhist.php";
            $reshist = (object)[];
            $res = plotly_hist( $histname, $reshist, $cgstate->state->mmcstride, $cgstate->state->mmcoffset );
            $results->histplotfinal = $reshist->histplot2;
        }
    }
}
*/

## testing
/*
require "common.php";
$cgstate = new cgrun_state();

$results = [];
$errormsg = "";

if ( !initial_model_set( $results, $errormsg ) ) {
    error_exit( $errormsg );
}

echo "results:\n" . json_encode( $results, JSON_PRETTY_PRINT ) . "\n";
$results[] = 0;

    
if ( !add_adjacent_frames( 1, $results, $errormsg ) ) {
    error_exit( $errormsg );
}

echo "results after adjacent frames:\n" . json_encode( $results, JSON_PRETTY_PRINT ) . "\n";

$procdir = "testingsel";

if ( is_dir( $procdir ) ) {
    run_cmd( "rm -fr $procdir; mkdir $procdir" );
} else {
    run_cmd( "mkdir $procdir" );
}

$names = [];

if ( !link_existing_frames( $results, "preselected", $procdir, $names, $errormsg ) ) {
    error_exit( $errormsg );
}

echo "created names are\n" . json_encode( $names, JSON_PRETTY_PRINT ) . "\n";
*/
