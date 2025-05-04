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

## setup

include "genapp.php";
include "datetime.php";
include_once "common.php";


$ga        = new GenApp( $input, $output );
$fdir      = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir  = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon     = $input->_logon;
$scriptdir = dirname(__FILE__);

include "sas.php";
$sas       = new SAS( false );

## get state

## do we have at least 2 projects?

if ( count( $input->projects ) < 2 ) {
    error_exit( "At least 2 projects must be selected" );
}

## read state for each project

$cgstates = (object)[];

$messages = [];

$firstproject = $input->projects[0];

foreach ( $input->projects as $project ) {
    $cgstates->$project = new cgrun_state( "../$project/state.json" );
    if ( !count( (array) $cgstates->$project->state ) ) {
        $messages[] = "Project '$project' is empty";
        continue;
    }

    if ( !isset( $cgstates->$project->state->output_loadsaxs ) ) {
        $messages[] = "<i>Load SAXS /i> has not been completd for project '$project'";
        continue;
    }

    if ( !isset( $cgstates->$project->state->output_final ) ) {
        $messages[] = "<i>Final model selection</i> has not been completd for project '$project'";
        continue;
    }

    if ( !isset( $cgstates->$project->state->output_loadsaxs->iqplot ) ) {
        $messages[] = "Project '$project' is somehow missing the loaded I(q) data";
        continue;
    }        

    if ( !isset( $cgstates->$project->state->output_loadsaxs->prplot ) ) {
        $messages[] = "Project '$project' is somehow missing the loaded P(r) data";
        continue;
    }        

    ## get I(q) data from project

    {
        $plotname = "iqplot";
        $sas->create_plot_from_plot( SAS::PLOT_IQ, "$project:$plotname", $cgstates->$project->state->output_load->iqplot );
        $sas->remove_plot( "$project:$plotname" );

        ## copy Exp. I(q) to data specific
        $sas->copy_data( 'Exp. I(q)', "$project: Exp. I(q)" );

        ## remove extra
        $sas->remove_data_if_exists( [ 'Exp. I(q)', 'WAXSiS', 'Res./SD', 'Resid.' ] );
        
    }

    ## get P(r) data from project
    
    {
        $plotname = "prplot";
        $sas->create_plot_from_plot( SAS::PLOT_PR, "$project:$plotname", $cgstates->$project->state->output_load->prplot );
        $sas->remove_plot( "$project:$plotname" );

        ## copy Exp. P(r) to data specific
        $sas->copy_data( 'Exp. P(r)', "$project: Exp. P(r)" );

        ## remove extra
        $sas->remove_data_if_exists( [ 'Exp. P(r)', 'WAXSiS', 'Res./SD', 'Resid.' ] );
    }


    if ( $project != $firstproject ) {
        if ( !$sas->compare_data( "$firstproject: Exp. I(q)", "$project: Exp. I(q)" ) ) {
            $messages[] = "Project '$project' and '$firstproject' have differing I(q) data";
            continue;
        }
        if ( !$sas->compare_data( "$firstproject: Exp. P(r)", "$project: Exp. P(r)" ) ) {
            $messages[] = "Project '$project' and '$firstproject' have differing P(r) data";
            continue;
        }
    }
}

$ga->tcpmessage( [ '_textarea' => $sas->data_summary( $sas->data_names() ) ] );


if ( count( $messages ) ) {
    error_exit( implode( "<br>", $messages ) );
}

## process inputs here to produce output

## log results to textarea

$output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
$output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );

