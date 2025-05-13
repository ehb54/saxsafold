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

$firstproject     = $input->projects[0];

## keep track of best I(q) fit & best P(r) fit, these will be displayed for reference
$best = (object)[
    "iq" => (object) [
        "project" => $firstproject
        ,"fit"    => 1e99
        ]
    ,"pr" => (object) [
        "project" => $firstproject
        ,"fit"    => 1e99
        ]
    ];

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

    #$ga->tcpmessage( [ '_textarea' =>
    #                   "iqplot $project stats " . json_encode( $sas->plot_stats( $cgstates->$project->state->output_load->iqplot ) ) . "\n"
    #                   . "prplot $project stats " . json_encode( $sas->plot_stats( $cgstates->$project->state->output_load->prplot ) ) . "\n"
    #                 ] );

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

    if ( $project == $firstproject ) {
        $iqfit = $sas->plot_stats( $cgstates->$project->state->output_load->iqplot  );
        if ( isset( $iqfit->fit ) ) {
            $best->iq->fit = $iqfit->fit;
        }
        $prfit = $sas->plot_stats( $cgstates->$project->state->output_load->prplot  );
        if ( isset( $prfit->fit ) ) {
            $best->pr->fit = $prfit->fit;
        }
    } else {

        $iqfit = $sas->plot_stats( $cgstates->$project->state->output_load->iqplot  );
        if ( isset( $iqfit->fit ) ) {
            if ( $best->iq->fit > $iqfit->fit ) {
                $best->iq->fit      = $iqfit->fit;
                $best->iq->project  = $project;
            }
        }

        $prfit = $sas->plot_stats( $cgstates->$project->state->output_load->prplot  );
        if ( isset( $prfit->fit ) ) {
            if ( $best->pr->fit > $prfit->fit ) {
                $best->pr->fit     = $prfit->fit;
                $best->pr->project = $project;
            }
        }

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

# $ga->tcpmessage( [ '_textarea' => "best:" . json_encode( $best, JSON_PRETTY_PRINT ) . "\n" ] );

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
        ## remove Exp. I(q) from all but first project
        $sas->remove_data( [
                               "$project: Exp. I(q)"
                           ] );
        ## remove WAXSiS mod 0 if it is not identical to the first proejct's
        if ( $sas->compare_data( "$firstproject: $firstbname WAXSiS mod. 0", "$project: $bname WAXSiS mod. 0" ) ) {
            $sas->remove_data( [
                                   "$project: Exp. I(q)"
                               ] );
        }
    } else {
        $firstbname = $bname;
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

## fake results for testing, set to 0 or comment the line below for production
# $test_fake_nnlsresults = 99;

if ( isset( $test_fake_nnlsresults ) && $test_fake_nnlsresults > 0 ) {
    $ga->tcpmessagebox( [ "icon" => "warning.png", "text" => "Results FAKED for testing!!" ] );
    
    $iqresults = [];
    $pos = 0;
    foreach ( $non_target_names as $name ) {
        $iqresults[ $name ] = 1 / count( $non_target_names );
        if ( ++$pos >= $test_fake_nnlsresults ) {
            break;
        }
    }
}

# $ga->tcpmessage( [ '_textarea' => "iqresults:" . json_encode( $iqresults, JSON_PRETTY_PRINT ) . "\n" ] );

## org Iq plot

$output->iqplot = unserialize( serialize( $cgstates->{$best->iq->project}->state->output_load->iqplot ) );
$output->iqplot->layout->title->text .= " project " . $best->iq->project;

## I(q) nnls fit plot

## setup sas with Exp. I(q) data

$waxsis_data_name    = "WAXSiS mod. 0";
$waxsis_data_name_iq = "I(q) $waxsis_data_name";
$plotname = "I(q) waxsis nnls";
$sas->create_plot_from_plot( SAS::PLOT_IQ, $plotname, $cgstates->$firstproject->state->output_load->iqplot
                             ,[
                                 'title' => "I(q)<br>Expt. + NNLS selected/reconstructed<br>from all computed on preselected models"
                                 ,'titlefontsize' => 14
                             ]);

$sas->remove_plot_data( $plotname, "Res./SD" );
$sas->remove_plot_data( $plotname, "WAXSiS" );
$sas->remove_data( "Res./SD" );
$sas->rename_data( "WAXSiS", $waxsis_data_name_iq );
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

$joinname   = "joined-" . implode( "-", $input->projects );
$pdboutname = "$joinname-waxsis-nnls.pdb";
$pdbout     = "";
$procdir    = "waxsissets";

# $ga->tcpmessage( [ '_textarea' => "pdboutname $pdboutname" . "\n" ] );

$pdbnames   = (object)[];
$prfiles    = [];

## need to offset duplicate frame numbers or else the jsmol will fail for frame select

$frameused             = [];
$name_to_frame         = (object)[];
$frame_offset_messages = [];

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

    if ( $name == "$project: $waxsis_data_name" ) {
        $pdbname = "../$project/${bname}-somo.pdb";
        if ( !file_exists( "$pdbname" ) ) {
            error_exit( "expected file '$pdbname' not found" );
        }
        $pdbnames->waxsis = $pdbname;
        $prfiles[]        = $pdbname;

        $use_frame = $waxsis_model_number;
        while( isset( $framesused[ $use_frame ] ) ) {
            ++$use_frame;
        }
        $framesused[ $use_frame ] = true;
        $name_to_frame->$name = $use_frame;
        if ( $use_frame != $waxsis_model_number ) {
            $frame_messages[] = "project $project model $frame was renumberd to model $use_frame";
        }
        
        $pdbout .=
            "REMARK project $project MODEL $waxsis_model_number\n"
            . "MODEL $use_frame\n"
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
        $prfiles[]        = "../$project/$procdir/$pdbname";

        $use_frame = $frame;
        while( isset( $framesused[ $use_frame ] ) ) {
            ++$use_frame;
        }
        $framesused[ $use_frame ] = true;
        $name_to_frame->$name = $use_frame;
        if ( $use_frame != $frame ) {
            $frame_messages[] = "project $project model $frame was renumberd to model $use_frame";
        }

        $pdbout .=
            "REMARK project $project MODEL $frame\n"
            . "MODEL $use_frame\n"
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

# $ga->tcpmessage( [ '_textarea' => "name_to_frame:" . json_encode( $name_to_frame, JSON_PRETTY_PRINT ) . "\n" ] );

## for color match
$pos = 0;
$iq_waxsis_nnlsresults_colors = (object)[];

foreach ( $iqresults as $name => $conc ) {
    if ( !preg_match( '/^([^:]*):/', $name, $matches ) ) {
        error_exit( "Could not determine project name from '$name'" );
    }
    $project = $matches[1];

    if ( !isset( $cgstates->$project ) ) {
        error_exit( "Project '$project' is not loaded" );
    }

    $bname = preg_replace( '/-somo\.pdb$/', '', $cgstates->$project->state->output_load->name );

    if ( !isset( $name_to_frame->$name ) ) {
        error_exit( "Internal error: missing frame information for '$name'" );
    }

    $frame = $name_to_frame->$name;

    $iq_waxsis_nnlsresults_colors->$name = get_color( $pos );
    $sas->plot_trace_options( $plotname, $name, [ 'linecolor' => get_color( $pos ) ] );

    $output->struct->script .= "select */$frame;color " . get_color( $pos++ ) . ";";
}
$output->struct->script .= "frame all;";

## downloads
$sassomoiqname = $joinname . "_waxsis_somo_iq.csv";
$sascoliqname  = $joinname . "_waxsis_iq.csv";

$csvoutnames = array_merge( [ "Exp. I(q)", "I(q) NNLS fit" ], $non_target_names );

# $ga->tcpmessage( [ '_textarea' => $sas->data_summary( $csvoutnames ) ] );

$sas->save_data_csv(
    $csvoutnames
    ,$sassomoiqname
    ,1
    ,'/(WAXSiS |I\(q\) )/'
    ,''
    );

$sas->save_data_csv_tr(
    $csvoutnames
    ,$sascoliqname
    ,1
    ,'/(WAXSiS |I\(q\) )/'
    ,''
    );

$output->iqresultswaxsis .= 
# $output->csvdownloads =
    "<div>"
    . "&nbsp;&nbsp;&nbsp;"
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sascoliqname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) SOMO style csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sassomoiqname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>PDB (NMR-style) &#x21D3;</a>&nbsp;&nbsp;&nbsp;<br>&nbsp;", $pdboutname )
    . "</div>"
    ;


## reconstruct pr

$saspr = new SAS( false );

## $prfiles[] collected previously when building joined PDB
# $ga->tcpmessage( [ '_textarea' => "prfiles:" . json_encode( $prfiles, JSON_PRETTY_PRINT ). "\n" ] );

$plotnamewaxsis    = "P(r) waxsis reconstruct";
$pr_recon_id       = "pr_recon";

$saspr->create_plot_from_plot( SAS::PLOT_PR, $plotnamewaxsis, $cgstates->{$best->pr->project}->state->output_load->prplot );

$saspr->plot_options( $plotnamewaxsis,
    [
    "titlefontsize" => 14
    ,"title" => "P(r) Expt. vs starting and<br>reconstructed from final models selected"
    ] );

$prnames = [];
foreach ( $prfiles as $k => $v ) {
    $project = 'unknown';
    if ( !preg_match( '/^\.\.\/([^\/]+)\//', $v, $matches ) ) {
        error_exit( "Internal error - unable to extract project from '$v'" );
    }
    $project = $matches[1];
    $prnames[ $k ] = "notnormed $project: P(r) mod. " . model_no_from_pdb_name( $v );
}

# $ga->tcpmessage( [ '_textarea' => "prfiles:" . json_encode( $prfiles, JSON_PRETTY_PRINT ). "\n" ] );
# $ga->tcpmessage( [ '_textarea' => "prnames:" . json_encode( $prnames, JSON_PRETTY_PRINT ). "\n" ] );

$saspr->compute_pr_many( $prfiles, $prnames );
$saspr->extend_pr( array_merge( $prnames, [ "Exp. P(r)" ] ) );

$scalednames = [];
$normednames = [];
$messages    = '';

# $ga->tcpmessage( [ '_textarea' => $saspr->data_summary( $saspr->data_names() ) ] );

foreach ( $prnames as $v ) {
    if ( !preg_match( '/^notnormed ([^:]+):/', $v, $matches ) ) {
        error_exit( "Internal error - unable to extract project from '$v'" );
    }
    $project = $matches[1];
    $frame = frame_no_from_data_name( $v );
    $normedname = substr( $v, 10 );
    $scaledname = "scaled $normedname";
    $normednames[] = $normedname;

    $saspr->norm_pr( $v, $cgstates->{$best->pr->project}->state->output_load->mw, $normedname );

    if ( isset( $iqresults[ "$project: WAXSiS mod. $frame" ] ) ) {
        $saspr->norm_pr( $v, $cgstates->{$best->pr->project}->state->output_load->mw * $iqresults[ "$project: WAXSiS mod. $frame" ], $scaledname );
        $scalednames[] = $scaledname;
    } else {
        error_exit( "Internal error - iqresults does not contain '$project: WAXSiS mod. $frame'" );
    }
        
    $saspr->remove_data( $v );
}

# $ga->tcpdebugjson( '$scalednames', $scalednames );
# $ga->tcptextarea( $messages );

$combinedname = "Recon.";
$residname = "Recon. Resid.";
$saspr->sum_data( $scalednames, $combinedname );
$saspr->add_plot( $plotnamewaxsis, $combinedname );
$saspr->remove_data( $scalednames );
$saspr->remove_data( $normednames );

$rmsd = 1e99;
$saspr->rmsd_residuals( "Exp. P(r)", $combinedname, $residname, $rmsd );
$saspr->add_plot_residuals( $plotnamewaxsis, $residname );
$rmsd = sprintf( "%.2f", $rmsd );
$saspr->annotate_plot( $plotnamewaxsis, "Recon. RMSD $rmsd", true );
$saspr->plot_trace_options( $plotnamewaxsis, "Resid.", [ 'linecolor_number' => 1 ] );
$saspr->plot_trace_options( $plotnamewaxsis, $combinedname, [ 'linecolor_number' => 2 ] );
$saspr->plot_trace_options( $plotnamewaxsis, $residname, [ 'linecolor_number' => 2 ] );

$output->$pr_recon_id = $saspr->plot( $plotnamewaxsis );


$output->_textarea = '';

if ( count( $frame_messages ) ) {
    $output->_textarea .=
        "Some PDB model numbers have been changed due to duplication across projects:\n"
        . implode( ".\n", $frame_messages ) . ".\n"
        . "This change is made in the PDB (NMR-style) download file, NOT in the model numbers shown elsewhere.\n"
        . "There is a REMARK included with these details within the PDB file before each model.\n"
        ;
}

## log results to textarea

#$output->_textarea = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
#$output->_textarea .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );

