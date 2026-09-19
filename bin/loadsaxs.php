#!/usr/local/bin/php
<?php

$self = __FILE__;

if ( count( $argv ) != 2 ) {
    echo '{"error":"$self requires a JSON input object"}';
    exit;
}

$json_input = $argv[1];

$input = json_decode( $json_input );

if ( !$input ) {
    echo '{"error":"$self - invalid JSON."}';
    exit;
}

$output = (object)[];

include "genapp.php";
include "datetime.php";
include "sas.php";
$sas = new SAS( false );

$ga        = new GenApp( $input, $output );
$fdir      = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir  = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon     = $input->_logon;
$scriptdir = dirname(__FILE__);

## get state

include_once "common.php";
$cgstate = new cgrun_state();

## make sure project is loaded

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project " );
}

require_once "remove.php";

$restore_old_data = function() {
    global $cgstate;
    global $ga;

    $obj = (object)[];

    if ( isset( $cgstate->state->output_loadsaxs ) ) {
        if ( isset( $cgstate->state->output_loadsaxs->iqplot ) ) {
            $obj->iqplot = $cgstate->state->output_loadsaxs->iqplot;
        }
        if ( isset( $cgstate->state->output_loadsaxs->prplot ) ) {
            $obj->prplot = $cgstate->state->output_loadsaxs->prplot;
        }
        if ( isset( $cgstate->state->output_loadsaxs->guinierplot ) ) {
            $obj->guinierplot = $cgstate->state->output_loadsaxs->guinierplot;
        }
    }
    
    $ga->tcpmessage( $obj );
};

question_prior_results( __FILE__, $restore_old_data );

## process inputs here to produce output

## clean up filenames

$input->saxsiqfile[0] =  clean_up_filename_and_copy_if_needed( $input->saxsiqfile[0] );
$input->saxsprfile[0] =  clean_up_filename_and_copy_if_needed( $input->saxsprfile[0] );

## possibly plot (easy for P(r), we have the code)

## plotly

$iqfile = $input->saxsiqfile[0];

if (
    $sas->load_file( SAS::PLOT_IQ, "Exp. I(q)", $iqfile )
    && $sas->create_plot( SAS::PLOT_IQ, "I(q)", [ "Exp. I(q)" ] )
    ) {
    if ( isset( $input->saxsiq_in_nm ) ) {
       $sas->data_convert_nm_to_angstrom( "Exp. I(q)" );
    }
    $qmin = "";
    $qmax = "";
    $sas->minq( "Exp. I(q)", $qmin );
    $sas->maxq( "Exp. I(q)", $qmax );

    $qmax = sprintf( "%.4f", $qmax );
    
    $sas->annotate_plot( "I(q)", pathinfo( $iqfile, PATHINFO_BASENAME ) . "  <i>q<sub>max</sub></i> = $qmax &#x212B;<sup>-1</sup>" );

    ## experimental Guinier Rg via US-SOMO Guinier search, kept in state for the final Rg plots
    ## optional Guinier controls from the form ( empty = the search's defaults ); recorded with the result
    $guinier_params = [];
    ## the form takes q^2 limits ( what the Guinier plot shows ); the search takes q
    foreach ( [ 'guinier_q2min' => 'qmin', 'guinier_q2max' => 'qmax', 'guinier_qrgmax' => 'qrgmax', 'guinier_maxrelsd' => 'maxrelsd' ] as $field => $key ) {
        if ( isset( $input->$field ) && strlen( trim( $input->$field ) ) ) {
            if ( !is_numeric( trim( $input->$field ) ) || floatval( $input->$field ) < 0 ) {
                error_exit( "Guinier setting '$field' must be a non-negative number" );
            }
            if ( floatval( $input->$field ) > 0 ) {
                $guinier_params[ $key ] = ( $key == 'qmin' || $key == 'qmax' ) ? sqrt( floatval( $input->$field ) ) : floatval( $input->$field );
            }
        }
    }
    if ( isset( $guinier_params[ 'qmin' ] ) && isset( $guinier_params[ 'qmax' ] ) && $guinier_params[ 'qmin' ] >= $guinier_params[ 'qmax' ] ) {
        error_exit( "Guinier q^2 min must be below Guinier q^2 max" );
    }

    $exp_guinier = null;
    if ( $sas->guinier_search( "Exp. I(q)", $exp_guinier, $guinier_params ) ) {
        $exp_guinier->params = (object) $guinier_params;
        $cgstate->state->exp_guinier = $exp_guinier;
        ## the summary is the title of the Guinier plot; a second annotation line on the I(q) plot
        ## spills below that plot and collides with the Guinier plot placed under it
        $output->_textarea = ( $output->_textarea ?? "" )
            . "Experimental I(q) " . SAS::guinier_search_summary_text( $exp_guinier ) . "\n"
            . ( count( $guinier_params ) ? "  Guinier settings: " . json_encode( $guinier_params ) . "\n" : "" )
            . ( count( $exp_guinier->warnings ) ? "  " . implode( "\n  ", $exp_guinier->warnings ) . "\n" : "" );
        $output->guinierplot = $sas->guinier_search_plot( "Exp. I(q)", $exp_guinier, "Guinier plot of " . pathinfo( $iqfile, PATHINFO_BASENAME ) );
    } else {
        unset( $cgstate->state->exp_guinier );
        $output->_textarea = ( $output->_textarea ?? "" ) . "Guinier Rg of the experimental I(q) not computed: " . $sas->last_error . "\n";
    }

    $output->iqplot = $sas->plot( "I(q)" );
} else {
    error_exit( $sas->last_error );
}

$prfile = $input->saxsprfile[0];

if (
    $sas->load_file( SAS::PLOT_PR, "Exp. P(r)", $prfile )
    && $sas->create_plot( SAS::PLOT_PR, "P(r)", [ "Exp. P(r)" ] )
    ) {
    if ( isset( $input->saxspr_in_nm ) ) {
       $sas->data_convert_nm_to_angstrom( "Exp. P(r)" );
    }
    $sas->annotate_plot( "P(r)", pathinfo( $prfile, PATHINFO_BASENAME ) );
    $output->prplot = $sas->plot( "P(r)" );
} else {
    error_exit( $sas->last_error );
}

## save state

$cgstate->state->saxsiqfile      = $input->saxsiqfile[0];
$cgstate->state->saxsprfile      = $input->saxsprfile[0];
$cgstate->state->output_loadsaxs = $output;
$cgstate->state->qmax            = end( $output->iqplot->data[0]->x);
$cgstate->state->qmin            = $output->iqplot->data[0]->x[0];
$cgstate->state->qpoints         = count( $output->iqplot->data[0]->x);

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
