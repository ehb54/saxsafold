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
include_once "computeiqpr_defines.php";


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

if ( count( $messages ) ) {
    error_exit( implode( "<br>", $messages ) );
}

## ok we should have multiple $projects with identical experimental data
## now we want to get all WAXSiS data, run NNLS again and produce results similar to finalmodel

## start over, clear sas

$sas->remove_data( $sas->data_names() );

## get CSV waxsis data

foreach ( $input->projects as $project ) {
    ## get name (again) for expected somo saxs iq file
    $bname          = preg_replace( '/-somo\.pdb$/', '', $cgstates->$project->state->output_load->name );
    $sassomoiqname  = $bname . "_waxsis_somo_iq.csv";
    $sassomoiqfname = "../$project/$sassomoiqname";

    ## load file data into sas
    $sas->load_somo_csv_file( SAS::PLOT_IQ, "$project: ", $sassomoiqfname );

    ## remove NNLS fit
    $sas->remove_data( "$project: $bname NNLS fit" );

    if ( $project != $firstproject ) {
        if ( !$sas->compare_data( "$firstproject: Exp. I(q)", "$project: Exp. I(q)" ) ) {
            $messages[] = "Project '$project' and '$firstproject' have differing I(q) data";
            continue;
        }
        ## remove Exp. I(q) and WAXSiS mod. 0 from all but first project
        $sas->remove_data( [
                               "$project: Exp. I(q)"
                               ,"$project: $bname WAXSiS mod. 0"
                           ] );
    }
}

if ( count( $messages ) ) {
    error_exit( implode( "<br>", $messages ) );
}

## rename Exp I(q)
$sas->rename_data( "$firstproject: Exp. I(q)", "Exp. I(q)" );

## rename WAXSiS
$sas->regex_rename_data( $sas->data_names( '/ WAXSiS/' ), '/:.* WAXSiS/', ': WAXSiS' );

# $ga->tcpmessage( [ '_textarea' => $sas->data_summary( $sas->data_names() ) ] );

$non_target_names = $sas->data_names( '/[A-Za-z0-9_]+:.* WAXSiS mod\. \d/' );

# $ga->tcpmessage( [ '_textarea' => $sas->data_summary( $non_target_names ) ] );

## run NNLS

$iqresults = [];

$sas->nnls( "Exp. I(q)", $non_target_names, "I(q) NNLS fit", $iqresults, true );

$ga->tcpmessage( [ '_textarea' => "iqresults:" . json_encode( $iqresults, JSON_PRETTY_PRINT ) . "\n" ] );

## I(q) nnls fit plot

## setup sas with Exp. I(q) data

$waxsis_data_name = "I(q) WAXSiS mod. 0";
$plotname = "I(q) waxsis nnls";
$sas->create_plot_from_plot( SAS::PLOT_IQ, $plotname, $cgstates->$firstproject->state->output_load->iqplot
                             ,[
                                 'title' => "I(q)<br>Expt. + NNLS selected/reconstructed<br>from all computed on preselected models"
                                 ,'titlefontsize' => 14
                             ]);

$sas->remove_plot_data( $plotname, "Res./SD" );
$sas->remove_plot_data( $plotname, "WAXSiS" );
$sas->remove_data( "Res./SD" );
$sas->rename_data( "WAXSiS", $waxsis_data_name );
$sas->add_plot( $plotname, "I(q) NNLS fit" );
foreach ( $iqresults as $k => $v ) {
    $sas->add_plot( $plotname, $k );
}

### residuals
$chi2  = -1;
$rmsd  = -1;
$scale = 0;

$sas->scale_nchi2( "Exp. I(q)", "I(q) NNLS fit", "I(q) NNLS fit-rescaled", $chi2, $scale );
$sas->rmsd( "Exp. I(q)", "I(q) NNLS fit", $rmsd );
$sas->calc_residuals( "Exp. I(q)", "I(q) NNLS fit", "I(q) fit Res./SD" );

## move NNLS fit to last curve, but before residuals
$sas->remove_plot_data( $plotname, "I(q) NNLS fit" );
$sas->recolor_plot( $plotname, [ 1 ] );
$sas->add_plot( $plotname, "I(q) NNLS fit" );
$sas->plot_trace_options( $plotname, "I(q) NNLS fit", [ 'linecolor_number' => 1 ] );

$sas->add_plot_residuals( $plotname, "I(q) fit Res./SD" );
$sas->plot_trace_options( $plotname, "I(q) fit Res./SD", [ 'linecolor_number' => 1 ] );

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
    $sas->annotate_plot( $plotname, $annotate_msg );
}

### summary results

$output->iqresultswaxsis = nnls_results_to_html( $iqresults );

$output->iqplotwaxsis = $sas->plot( $plotname );

## setup pdb

$pdboutname = "joined-" . implode( "-", $input->projects ) . "-waxsis-nnls.pdb";
$pdbout     = "";
$procdir    = "waxsissets";

$ga->tcpmessage( [ _textarea => "pdboutname $pdboutname" . "\n" ] );

$pdbnames = (object)[];

foreach ( $iqresults as $name => $conc ) {
    if ( !preg_match( '/^([^:]*):/', $name, $matches ) ) {
        error_exit( "Could not determine project name from '$name'" );
    }
    $project = $matches[1];

    if ( !isset( $cgstates->$project ) ) {
        error_exit( "Project '$project' is not loaded" );
    }

    $bname          = preg_replace( '/-somo\.pdb$/', '', $cgstates->$project->state->output_load->name );

    $tmpname = explode( ' ', $name );
    $frame = end( $tmpname );

    ## for testing
    $pdbname = "${bname}-somo.pdb";
    $pdbnames->waxsis = $pdbname;
    ## end for testing  

    $ga->tcpmessage( [ _textarea => "pdbname $pdbname name $name project $project frame $frame" . "\n" ] );

    if ( $name == "$project: $waxsis_data_name" ) {
        $pdbname = "../$project/${bname}-somo.pdb";
        if ( !file_exists( "$pdbname" ) ) {
            error_exit( "expected file '$pdbname' not found" );
        }
        $pdbnames->waxsis = $pdbname;

        $pdbout .=
            "REMARK project $project\n"
            . "MODEL $waxsis_model_number\n"
            . run_cmd( "grep -P '^(ATOM|HETATM)' $pdbname" )
            . "ENDMDL\n"
            ;
        
    } else {
        $frame_padded = str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
        $pdbname = "${bname}-somo-m$frame_padded.pdb";
        if ( !file_exists( "../$project/$procdir/$pdbname" ) ) {
            error_exit( "expected file '$project/$procdir/$pdbname' not found" );
        }
        $pdbnames->$frame = "../$project/$procdir/$pdbname";

        $pdbout .=
            "REMARK project $project\n"
            . "MODEL $frame\n"
            . run_cmd( "grep -P '^(ATOM|HETATM)' ../$project/$procdir/$pdbname" )
            . "ENDMDL\n"
            ;
    }
};

$pdbout .= "END\n";

if ( !file_put_contents( $pdboutname, $pdbout ) ) {
    error_exit( "error creating '$pdboutname'" );
}    

$output->struct = (object) [
    "file" => "results/users/$logon/$base_dir/$pdboutname"
    ,"script" => "background white;ribbon only;"
    ];

## for color match
$iq_waxsis_nnlsresults_colors = (object)[];

foreach ( $iqresults as $name => $conc ) {
    if ( !preg_match( '/^([^:]*):/', $name, $matches ) ) {
        error_exit( "Could not determine project name from '$name'" );
    }
    $project = $matches[1];


    if ( !isset( $cgstates->$project ) ) {
        error_exit( "Project '$project' is not loaded" );
    }

    $bname          = preg_replace( '/-somo\.pdb$/', '', $cgstates->$project->state->output_load->name );

    if ( $name == "$project: $waxsis_data_name" ) {
        $frame = $waxsis_model_number;
    } else {
        $tmpname = explode( ' ', $name );
        $frame = end( $tmpname );
    }
    $iq_waxsis_nnlsresults_colors->$name = get_color( $pos );
    $sas->plot_trace_options( $plotname, $name, [ 'linecolor' => get_color( $pos ) ] );

    $output->struct->script .= "select */$frame;color " . get_color( $pos++ ) . ";";
}
$output->struct->script .= "frame all;";

## downloads

$output->csvdownloads =
    "<div>"
    . "&nbsp;&nbsp;&nbsp;"
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sascoliqname )
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) SOMO style csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sassomoiqname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>PDB (NMR-style) &#x21D3;</a>&nbsp;&nbsp;&nbsp;<br>&nbsp;", $pdboutname )
    . "</div>"
    ;


## log results to textarea

#$output->_textarea = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
#$output->_textarea .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );

