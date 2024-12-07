#!/usr/local/bin/php
<?php

{};

### user defines

include_once "limits.php";

### end user defines

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
include "plotlyhist.php";

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

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

if ( !$cgstate->state->saxsiqfile || !$cgstate->state->saxsprfile ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

if ( !$cgstate->state->flex || !count( $cgstate->state->flex ) ) {
    error_exit( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !$cgstate->state->mmcrunname ) {
    error_exit( "No MMC run name found, did you <i>Run MMC</i>?" );
}

$statsname = $cgstate->state->mmcrunname . ".dcd.stats";

## do we have results locally?
$lpath      = "monomer_monte_carlo/$statsname";
$lresults   = file_exists( $lpath );

## perhaps a state variable is better?
$cgstate->state->mmcstride = $input->mmcstride;

## clear output
$ga->tcpmessage( [
                     'processing_progress' => 0.01
                     ,"progress_text"      => ''
                 ]);

if ( $lresults && $cgstate->state->mmcdownloaded ) {
    $histname = "monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.accepted_rg_results_data.txt";

    if ( $input->extractframes ) {
        $tmpout = (object)[];
        $tmpout->_textarea = "MMC results already retrieved\n";
        $statsname         = $cgstate->state->mmcrunname . ".dcd.stats";
        $tmpout->_textarea .= "\n" . `cat monomer_monte_carlo/$statsname 2> /dev/null`;

        $res = plotly_hist( $histname, $tmpout, $cgstate->state->mmcstride );
        $reqframes = count( $tmpout->histplot->data[1]->x );
        
        $pdbname          = $cgstate->state->output_load->name;

        if ( !file_exists( "monomer_monte_carlo/$pdbname" ) ) {
            error_exit( "MMC results for $pdbname not found, did you <i>Run MMC</i> on this structure?" );
        }

        $tmpout->_textarea .= "Preparing to extract $reqframes frames\n";

        $ga->tcpmessage( $tmpout );

        if ( $reqframes > $max_frames ) {
            $ga->tcpmessage( [
                                 'processing_progress' => 0
                                 ,"progress_text"      => ''
                             ]);
            error_exit( "A maximum of $max_frames frames are currently supported for processing, your stride requests $reqframes" );
        }

        $ga->tcpmessage( [
                             'processing_progress' => 0.5
                         ]);

        progress_text( "Extracting frames" );

        sleep(1);

        $dcdname          = $cgstate->state->mmcrunname . ".dcd";
        $mmcextracted     = preg_replace( '/\.(pdb|PDB)$/', '', $cgstate->state->output_load->name ) . "_extracted.pdb";
        $mmcsplitbasename = preg_replace( '/\.(pdb|PDB)$/', '', $cgstate->state->output_load->name );

        ## how do we define chunk size -c ?
        # $cmd = "cd monomer_monte_carlo && mdconvert -c 1700 -s $input->mmcstride -t $pdbname -o out.pdb $dcdname";
        $chunk = $max_frames * $input->mmcstride;
        $cmd = "cd monomer_monte_carlo && rm $mmcextracted 2>/dev/null; grep -Pv '^CRYST1' $pdbname > $pdbname.noCRYST1.pdb && mdconvert -c $chunk -s $input->mmcstride -t $pdbname.noCRYST1.pdb -o $mmcextracted $dcdname";

        # $ga->tcpmessage( [ "_textarea" => "cmd $cmd\n" ] );
        $cmdres     = run_cmd( $cmd, false, false );
        $ga->tcpmessage( [ "_textarea" =>
                           "$cmdres\n"
                           . "creating individual model files\n\n"
                         ] );
        if ( $run_cmd_last_error_code ) {
            error_exit( "Error extracting MMC frames : $cmdres\n" );
        }

        $cmd = "rm -rf preselected; mkdir preselected && cd monomer_monte_carlo && $scriptdir/calcs/splitmodels.pl $mmcextracted ../preselected/$mmcsplitbasename";
        #$ga->tcpmessage( [ "_textarea" => "$cmd\n" ] );
        $cmdres     = run_cmd( $cmd, false, false );
        #$ga->tcpmessage( [ "_textarea" => "$cmdres\n" ] );
        if ( $run_cmd_last_error_code ) {
            error_exit( "Error extracting MMC frames : $cmdres\n" );
        }

        $output->_textarea = "MMC frames at stride $input->mmcstride extracted\n";

        $cgstate->state->mmcextracted = "monomer_monte_carlo/$mmcextracted";
        
    } else {
        $output->_textarea = "MMC results already retrieved\n";
        $statsname         = $cgstate->state->mmcrunname . ".dcd.stats";
        $output->_textarea .= "\n" . `cat monomer_monte_carlo/$statsname 2> /dev/null`;
        $res = plotly_hist( $histname, $output, $cgstate->state->mmcstride );
        if ( strlen( $res ) ) {
            $output->_textarea .= $res;
        }
    }
    if ( !$cgstate->save() ) {
        echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
        exit;
    }

    $output->processing_progress = 0;
    $output->progress_text = progress_text( 'Processing complete', '', true );

    echo json_encode( $output );
    exit();
}

if ( $input->extractframes ) {
    $ga->tcpmessage( [
                         'processing_progress' => 0
                     ]);
    error_exit( "Please successfully retrieve results & validate the Stride before extracting frames" );
}

### !!!!!remote & path should be in a config file

## do we have results on the remote?
$rpath      = "/opt/genapp/sassie2/results/users/$input->_logon/no_project_specified/" . $cgstate->state->mmcrunname . "/monomer_monte_carlo";
$cmd        = "timeout 2 ssh jobrunner@zazzie.genapp.rocks ls $rpath/$statsname";
$cmdres     = run_cmd( $cmd, false, true );
$rresults   = count( $cmdres ) == 1 && trim( $cmdres[ 0 ] ) == "$rpath/$statsname";
if ( !$rresults ) {
    error_exit( "No MMC results found, it may still be running<br>If you are sure MMC completed, try running again using the <b>exact</b> parameters" );
}

$ga->tcpmessage( [
                     'processing_progress' => 0.5
                 ]);

progress_text( "Retrieving results" );

$ga->tcpmessage( [ "_textarea" => "Retrieving results (this may take awhile, esp. for large structures.)\n" ] );
$cmd        = "timeout 120 rsync -avz --partial jobrunner@zazzie.genapp.rocks:$rpath .";

# $ga->tcpmessage( [ "_textarea" => "cmd: $cmd\n" ] );
$cmdres = run_cmd( $cmd, false, true );
if ( $run_cmd_last_error_code ) {
    $ga->tcpmessage( [ "_textarea" => "cmdres: " . implode( "\n\t", $cmdres ) . "\n" ] );
    error_exit( "Error trying to retrieve results, please try again\n" );
}

$output->_textarea = "Results downloaded\n";

$output->_textarea .= "\n" . `cat monomer_monte_carlo/$statsname 2> /dev/null`;

$histname = "monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.accepted_rg_results_data.txt";
$res = plotly_hist( $histname, $output, $cgstate->state->mmcstride );
if ( strlen( $res ) ) {
    $output->_textarea .= $res;
}

$cgstate->state->mmcdownloaded = true;
if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## process inputs here to produce output

## log results to textarea

#$output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
#$output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

$output->processing_progress = 0;
$output->progress_text = progress_text( 'Processing complete', '', true );

echo json_encode( $output );
