#!/usr/local/bin/php
<?php

{};

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

$ga        = new GenApp( $input, $output );
$fdir      = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir  = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon     = $input->_logon;
$scriptdir = dirname(__FILE__);

## get state

require "common.php";
$cgstate = new cgrun_state();

## does the project already exist ?


## make sure project is loaded

if ( !$cgstate->state->loaded ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

## does the project already exist ?

if ( !$cgstate->state->loaded ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile || !isset($cgstate->state->qmin) || !$cgstate->state->qmax || !$cgstate->state->qpoints ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

if ( !$cgstate->state->flex || !count( $cgstate->state->flex ) ) {
    error_exit( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !$cgstate->state->mmcrunname ) {
    error_exit( "No MMC run name found, did you <i>Run MMC</i>?" );
}

if ( !$cgstate->state->mmcdownloaded || !$cgstate->state->mmcextracted || !strlen( $cgstate->state->mmcextracted ) ) {
    error_exit( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    

## clear output
$ga->tcpmessage( [
                     'processing_progress' => 0.01
                     ,"progress_text"      => ''
                     ,"iqresults"          => ''
                     ,"prresults"          => ''
                 ]);

## initial plots
include_once "limits.php";
require "sas.php";
$sas = new SAS();

$plots = (object) [];

require "$scriptdir/plotly_computeiqpr.php";

setup_computeiqpr_plots( $plots );

## plotlycomputeiqpr( $cgstate->state->output_loadsaxs, $plots );

## initial message 
$tmpout = (object)[];

$tmpout->iqplot       = $plots->iqplot;
# $tmpout->iqplotall    = $sas->plot( "I(q) all mmc" );
# $tmpout->iqplotsel    = $plots->iqplotsel;

$tmpout->prplot       = $plots->prplot;
# $tmpout->prplotall    = $sas->plot( "P(r) all mmc" );
# $tmpout->prplotsel    = $plots->prplotsel;

$ga->tcpmessage( $tmpout );

## process inputs here to produce output

if ( $input->iqmethod != "pepsi" ) {
    error_exit( "Only PEPSI-SAXS currently supported" );
}

## compute Iq

$pdbs = run_cmd( "cd preselected 2> /dev/null && ls *.pdb", false, true );
if ( $run_cmd_last_error_code ) {
    error_exit( "Error getting selected PDBs : Code $run_cmd_last_error_code\n" );
}

if ( !count( $pdbs ) ) {
    error_exit( "Hmm. No selected PDBs found!, perhaps retry <i>Retrieve MMC</i>" );
}

$pos           = 0;
$testing_limit = isset( $test_limit_max_computeiqpr_frames ) ? $test_limit_max_computeiqpr_frames : 0;
if ( $testing_limit ) {
    $pdbs = array_slice( $pdbs, 0, $testing_limit );
}

$count_pdbs    = count( $pdbs );
if ( $count_pdbs > $max_frames ) {
    error_exit( "Number of Frames ($count_pdbs) to process is greater than the current limit ($max_frames).<br>Rerun <i>Retrieve MMC</i>.<br>Be sure to check <i>Extract Frames</i> and <br>set <i>Stride</i> to ensure the number of Frames subselected is less than the limit." );
}
    
progress_text( "Computing P(r)" );

dt_store_now( "P(r) start" );

## batch group P(r)

if ( !isset( $batch_run_pr_size ) || $batch_run_pr_size <= 0 ) {
    error_exit( "Limit \$batch_run_pr_size is not set to a positive number" );
}

if ( !isset( $update_iq_frequency ) || $update_iq_frequency <= 0 ) {
    error_exit( "Limit \$update_iq_frequency is not set to a positive number" );
}

$allprnames = [];

for ( $pos = 0; $pos < $count_pdbs; $pos += $batch_run_pr_size ) {

    $prpdbs  = array_slice( $pdbs, $pos, $batch_run_pr_size );

    ## last iteration might be short
    $usecount = min( count( $prpdbs ), $batch_run_pr_size );
    progress_text( "Computing P(r) (" . ( $pos + 1 ) . "-" . ( $pos + $usecount ) . " of $count_pdbs)" );

    $ga->tcpmessage(
        [
         "processing_progress" => 0 + .4 * ( ( $pos + .5 * $usecount ) / $count_pdbs )
         ,"prplotallhtml" => plot_to_image( $sas->plot( "P(r) all mmc" ) )
        ]
        );

    $prnamescomp   = [];
    $prnamesinterp = [];
    $prnamesnormed = [];
    
    for ( $i = 0; $i < $usecount; ++$i ) {
        $prnamesnormed[] = "P(r) mod. " . ( $pos + $i + 1 );
        $allprnames[]    = end( $prnamesnormed );
        $prnamescomp[]   = end( $prnamesnormed ) . " comp";
        $prnamesinterp[] = end( $prnamesnormed ) . " interp";
        $prpdbs[ $i ] = "preselected/" . $prpdbs[ $i ];
    }

    $sas->compute_pr_many( $prpdbs, $prnamescomp );

    for ( $i = 0; $i < $usecount; ++$i ) {
        $sas->interpolate( $prnamescomp[$i], "Comp.", $prnamesinterp[$i] );
        $sas->norm_pr( $prnamesinterp[$i], floatval( $cgstate->state->output_load->mw ), $prnamesnormed[$i] );
        $sas->add_plot( "P(r) all mmc", $prnamesnormed[$i] );
        $sas->remove_data( $prnamescomp[$i] );
        $sas->remove_data( $prnamesinterp[$i] );
    }

}
    
dt_store_now( "P(r) end" );

$ga->tcpmessage(
    [
     "prplotallhtml" => plot_to_image( $sas->plot( "P(r) all mmc" ) )
    ]
    );

# $ga->tcpmessage( [ "_textarea" => "P(r) time " . dhms_from_minutes( dt_store_duration( "P(r) start", "P(r) end" ) ) . "\n" ] );

dt_store_now( "I(q) start" );

$pos           = 0;

progress_text( "Computing I(q) (" . ( $pos + 1 ) . "-" . min( $pos + $update_iq_frequency, $count_pdbs ). " of $count_pdbs)" );

$ga->tcpmessage(
    [
     "processing_progress" => .4
    ]);

$alliqnames = [];

foreach ( $pdbs as $pdb ) {
    ++$pos;

    # with exp data file: $cmd    = "cd preselected && Pepsi-SAXS " . $cgstate->state->saxsiqfile . " -ms " . $cgstate->state->qmax . " -ns " . $cgstate->state->qpoints . " $pdb";
    $cmd    = "cd preselected && Pepsi-SAXS -ms " . $cgstate->state->qmax * 1.01 . " -ns " . $cgstate->state->qpoints . " $pdb";
    $cmdres = run_cmd( $cmd, false );
    if ( $run_cmd_last_error_code ) {
        error_exit( "Error computing I(q) : $cmdres" );
    }
    $iqfile  = "preselected/" . preg_replace( '/\.pdb$/', '.out', $pdb );
    if ( !file_exists( $iqfile ) ) {
        error_exit( "Expected Iq file missing : $iqfile" );
    }

    $chi2  = -1;
    $rmsd  = -1;
    $scale = 0;
    
    $iqdataname   = "I(q) mod. $pos";
    $alliqnames[] = $iqdataname;
        
    $sas->load_file( SAS::PLOT_IQ, "$iqdataname orig", $iqfile, false );
    $sas->interpolate( "$iqdataname orig", "Exp. I(q)", "$iqdataname interp" );
    $sas->scale_nchi2( "Exp. I(q)", "$iqdataname interp", $iqdataname, $chi2, $scale );
    $sas->add_plot( "I(q) all mmc", $iqdataname );
    
    foreach ( [
                  "$iqdataname orig"
                  ,"$iqdataname interp"
              ] as $v) {
        $sas->remove_data( $v );
    }

    if ( !($pos % $update_iq_frequency ) ) {
        $ga->tcpmessage(
            [
             "processing_progress" => .4 + .4 * ( ( $pos + .5 * $update_iq_frequency ) / $count_pdbs )
             ,"iqplotallhtml" => plot_to_image( $sas->plot( "I(q) all mmc" ) )
            ]
            );

        progress_text( "Computing I(q) (" . ( $pos + 1 ) . "-" . min( $pos + $update_iq_frequency, $count_pdbs ). " of $count_pdbs)" );
    }
}

dt_store_now( "I(q) end" );

# $ga->tcpmessage( [ "_textarea" => "I(q) time " . dhms_from_minutes( dt_store_duration( "I(q) start", "I(q) end" ) ) . "\n" ] );

$ga->tcpmessage(
    [
     "iqplotallhtml" => plot_to_image( $sas->plot( "I(q) all mmc" ) )
     ,"processing_progress" => .8
#     ,"_textarea" => json_encode( $sas->data_names(), JSON_PRETTY_PRINT ) . "\n"
    ]
    );

## NNLS on P(r)

progress_text( "Running NNLS on P(r)" );

$prresults = [];

$sas->extend_pr( array_merge( [ "Exp. P(r)" ], $allprnames  ) );
$sas->nnls( "Exp. P(r)", $allprnames, "P(r) NNLS fit", $prresults, isset( $input->prerrors ) );

### build up P(r) sel plot

$sas->add_plot( "P(r) sel", "P(r) NNLS fit" );

foreach ( $prresults as $k => $v ) {
    $sas->add_plot( "P(r) sel", $k );
}

### residuals
$rmsd_pr = -1;
$chi2_pr = -1;
$scale   = 0;

if ( isset( $input->prerrors ) ) {
    $sas->scale_nchi2( "Exp. P(r)", "P(r) NNLS fit", "P(r) NNLS fit-rescaled", $chi2_pr, $scale );
    $sas->rmsd( "Exp. P(r)", "P(r) NNLS fit", $rmsd_pr );
    $sas->calc_residuals( "Exp. P(r)", "P(r) NNLS fit", "P(r) fit Res./SD" );
    $sas->add_plot_residuals( "P(r) sel", "P(r) fit Res./SD" );
    $sas->plot_options( "P(r) sel", [ "yaxis2title" => "Res./SD" ] );
} else {
    $sas->rmsd_residuals( "Exp. P(r)", "P(r) NNLS fit", "P(r) fit Resid.", $rmsd_pr );
    $sas->add_plot_residuals( "P(r) sel", "P(r) fit Resid." );
    $sas->plot_options( "P(r) sel", [ "yaxis2title" => "Resid." ] );
}

$rmsd_pr = round( $rmsd_pr, 3 );
$chi2_pr = round( $chi2_pr, 3 );
$annotate_msg = "";
if ( $rmsd_pr != -1 ) {
    $annotate_msg .= "RMSD $rmsd_pr   ";
}
if ( $chi2_pr != -1 ) {
    $annotate_msg .= "nChi^2 $chi2_pr   ";
}
if ( strlen( $annotate_msg ) ) {
    $sas->annotate_plot( "P(r) sel", $annotate_msg );
}

$output->prresults = nnls_results_to_html( $prresults );

### save results to state

$cgstate->state->nnlsprresults = $prresults;

$ga->tcpmessage(
    [
     "processing_progress" => .9
     ,"prplotsel" => $sas->plot( "P(r) sel" )
#     ,"_textarea" => json_encode( $prresults, JSON_PRETTY_PRINT ) . "\n"
    ]
    );
    
$output->prplotsel = $sas->plot( "P(r) sel" );

## NNLS on I(q)

progress_text( "Running NNLS on I(q)" );

$iqresults = [];

$sas->nnls( "Exp. I(q)", $alliqnames, "I(q) NNLS fit", $iqresults );

$sas->add_plot( "I(q) sel", "I(q) NNLS fit" );

foreach ( $iqresults as $k => $v ) {
    $sas->add_plot( "I(q) sel", $k );
}

### residuals
$chi2  = -1;
$rmsd  = -1;
$scale = 0;

$sas->scale_nchi2( "Exp. I(q)", "I(q) NNLS fit", "I(q) NNLS fit-rescaled", $chi2, $scale );
$sas->rmsd( "Exp. I(q)", "I(q) NNLS fit", $rmsd );
$sas->calc_residuals( "Exp. I(q)", "I(q) NNLS fit", "I(q) fit Res./SD" );
$sas->add_plot_residuals( "I(q) sel", "I(q) fit Res./SD" );

$rmsd = round( $rmsd, 3 );
$chi2 = round( $chi2, 3 );
$annotate_msg = "";
if ( $rmsd != -1 ) {
    $annotate_msg .= "RMSD $rmsd   ";
}
if ( $chi2 != -1 ) {
    $annotate_msg .= "nChi^2 $chi2   ";
}
if ( strlen( $annotate_msg ) ) {
    $sas->annotate_plot( "I(q) sel", $annotate_msg );
}

$ga->tcpmessage(
    [
     "iqplotsel" => $sas->plot( "I(q) sel" )
#     ,"_textarea" => json_encode( $iqresults, JSON_PRETTY_PRINT ) . "\n"
#     ,"_textarea" => json_encode( $sas->data_names(), JSON_PRETTY_PRINT ) . "\n"
#     . json_encode( $sas->plot_names(), JSON_PRETTY_PRINT ) . "\n"
#     . $sas->data_summary( $sas->data_names() )
    ]
    );

$output->iqplotsel = $sas->plot( "I(q) sel" );

### summary results

$output->iqresults = nnls_results_to_html( $iqresults );

### save results to state

$cgstate->state->nnlsiqresults = $iqresults;

## rebuild final plots

$output->prplotallhtml = plot_to_image( $sas->plot( "P(r) all mmc" ) );
$output->iqplotallhtml = plot_to_image( $sas->plot( "I(q) all mmc" ) );

## save state

$cgstate->state->output_iqpr  = $output;

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

$output->processing_progress = 0;
$output->progress_text = progress_text( 'Processing complete', '', true );

echo json_encode( $output );
