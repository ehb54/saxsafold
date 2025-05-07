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

    if ( !isset( $cgstates->$project->state->output_load ) ) {
        $messages[] = "Project '$project' is somehow missing the load structure data";
        continue;
    }        

    if ( !isset( $cgstates->$project->state->output_load->name ) ) {
        $messages[] = "Project '$project' somehow has incomplete load structure data";
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

    ## get name for expected somo saxs iq file
    $bname          = preg_replace( '/-somo\.pdb$/', '', $cgstates->$project->state->output_load->name );
    $sassomoiqname  = $bname . "_waxsis_somo_iq.csv";
    $sassomoiqfname = "../$project/$sassomoiqname";

    if ( !file_exists( $sassomoiqfname ) ) {
        $messages[] = "Expected final model results file '$project/$sassomoiqname' does not exist";
        continue;
    }
    if ( !filesize( $sassomoiqfname ) ) {
        $messages[] = "Expected final model results file '$project/$sassomoiqname' is empty";
        continue;
    }
}

$ga->tcpmessage( [ '_textarea' => $sas->data_summary( $sas->data_names() ) ] );

if ( count( $messages ) ) {
    error_exit( implode( "<br>", $messages ) );
}

## ok we should have multiple $projects with identical experimental data
## now we want to get all WAXSiS data, run NNLS again and produce results similar to finalmodel

## start over, clear sas

$sas->remove_data( $sas->data_names() );

## get waxsis data

foreach ( $input->projects as $project ) {
    ## get name (again) for expected somo saxs iq file
    $bname          = preg_replace( '/-somo\.pdb$/', '', $cgstates->$project->state->output_load->name );
    $sassomoiqname  = $bname . "_waxsis_somo_iq.csv";
    $sassomoiqfname = "../$project/$sassomoiqname";

    ## load file data into sas
    $sas->load_somo_csv_file( SAS::PLOT_IQ, "$project: ", $sassomoiqfname );
}

$ga->tcpmessage( [ '_textarea' => $sas->data_summary( $sas->data_names() ) ] );

$ga->tcpmessage( [ '_textarea' => json_encode( $input->projects, JSON_PRETTY_PRINT ) ] . "\n" );

## accumulate data for each project

$bname     = preg_replace( '/-somo\.pdb$/', '', $cgstate->state->output_load->name );


## process inputs here to produce output

## log results to textarea

$output->_textarea = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
$output->_textarea .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );

