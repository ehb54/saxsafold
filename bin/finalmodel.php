#!/usr/local/bin/php
<?php

{};

$do_testing = false;

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

## setup elastic manager for 

require_once "em.php";

## multiple ems
## acquire & release in groups
## split names amoungst group

$em = new em();
function em_shutdown() {
    global $em;
    if ( isset( $em ) ) {
        $em->release_if_has_instance();
    }
}
register_shutdown_function( 'em_shutdown' );

## does the project already exist ?


## make sure project is loaded

if ( !isset( $cgstate->state->loaded ) ) {
    error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !isset( $cgstate->state->saxsiqfile ) || !isset( $cgstate->state->saxsprfile ) ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

## does the project already exist ?

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !isset( $cgstate->state->saxsiqfile )
     || !isset( $cgstate->state->saxsprfile )
     || !isset( $cgstate->state->qmin )
     || !isset( $cgstate->state->qmax )
     || !isset( $cgstate->state->qpoints ) ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

if ( !isset( $cgstate->state->flex ) || !count( $cgstate->state->flex ) ) {
    error_exit( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !isset( $cgstate->state->mmcrunname ) ) {
    error_exit( "No MMC run name found, did you <i>Run MMC</i>?" );
}

if ( !isset( $cgstate->state->mmcdownloaded )
     || !$cgstate->state->mmcdownloaded
     || !isset( $cgstate->state->mmcextracted )
     || !strlen( $cgstate->state->mmcextracted )
     || !isset( $cgstate->state->mmcframecount ) ) {
    error_exit( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    

if ( !isset( $cgstate->state->output_iqpr ) ) {
    error_exit( "Did you <i>Compute I(q)/P(r)</i>?" );
}

#if ( !isset( $cgstate->state->nnlsiqresults )
#    || !isset( $cgstate->state->nnlsprresults ) ) {
#    error_exit( "Did you successfully <i>Compute I(q)/P(r)</i>?" );
#}

## process inputs here to produce output

# if ( $input->adjacent_frames > $cgstate->state->mmcstride / 2 ) {
#    error_exit( "The maximum value allowed for the <i>Additional adjacent frame count</i> is " . ( intval( $cgstate->state->mmcstride / 2 ) ) );
# }

$procdir = "waxsissets";
$waxsis_data_name = "I(q)<sub>W</sub> mod. 0";

## does the project already exist ?

require_once "remove.php";

$restore_old_data = function() {
    global $ga;
    global $input;

    $obj = (object)[];

    ## had to reload as we have already changed $cgstate when estimated time etc
    $cgstate = new cgrun_state();

    if ( isset( $cgstate->state->output_load->iqplot ) ) {
        $obj->iqplot = unserialize(serialize($cgstate->state->output_load->iqplot));

        foreach( $obj->iqplot->data as $curve ) {
            if ( $curve->name == "WAXSiS" ) {
                $curve->name = "I(q)<sub>W</sub> mod. 0";
                break;
            }
        }
    }

    if ( isset( $cgstate->state->output_final ) ) {
        if ( isset( $cgstate->state->output_final->iqplotwaxsis ) ) {
            $obj->iqplotwaxsis = &$cgstate->state->output_final->iqplotwaxsis;
        }
        if ( isset( $cgstate->state->output_final->iqresultswaxsis ) ) {
            $obj->iqresultswaxsis = &$cgstate->state->output_final->iqresultswaxsis;
            if ( isset( $cgstate->state->output_final->csvdownloads ) ) {
                $obj->iqresultswaxsis .= $cgstate->state->output_final->csvdownloads;
            }
        }
        if ( isset( $cgstate->state->output_final->pr_recon ) ) {
            $obj->pr_recon = &$cgstate->state->output_final->pr_recon;
        }
        if ( isset( $cgstate->state->output_final->struct ) ) {
            $obj->struct = &$cgstate->state->output_final->struct;
        }
        if ( isset( $cgstate->state->output_final->histplotfinal ) ) {
            $obj->histplotfinal = &$cgstate->state->output_final->histplotfinal;
        }
        if ( isset( $cgstate->state->output_final->_textarea ) ) {
            $obj->_textarea = $cgstate->state->output_final->_textarea;
        }
    }

#    $obj->desc                      = $cgstate->state->description;
    $obj->pname                     = $input->_project;
    $obj->downloads                 = $cgstate->state->output_load->downloads;

    $obj->processing_progress = 0;

    $ga->tcpmessage( $obj );
};

question_prior_results( __FILE__, $restore_old_data, "<br><br><strong>N.B.</strong> Previously computed WAXSiS results for structures are cached. You can also cancel at the subsequent <i>Proceed with Computations?</i>&nbsp; message window &mdash; which includes a time estimate &mdash; without loss of prior results.<br>If you are trying to re-run failed WAXSiS computations, you will not loose any previously successfully computed curves." );
## &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;


## clear output
$ga->tcpmessage( [
                     'processing_progress' => 0.01
                     ,"progress_text"      => ''
                     ,"histplotfinal"      => ''
                     ,"iqplotwaxsis"       => ''
                     ,"struct"             => ''
                 ] );

### build up set of models

include "finalmodel_funcs.php";
include "waxsis.php";
include "sas.php";
$sas = new SAS( false );

$errors = "";
$frameset = [];

switch( $input->waxsis_convergence_mode ) {
    case "normal"   : $waxsis_suffix = "_n"; break;
    case "thorough" : $waxsis_suffix = "_t"; break;
    case "quick"    : $waxsis_suffix = "_q"; break;
    default         : error_exit( "internal error - unknown or unsupported WAXSiS convergence mode" );
}

if ( !initial_model_set( $frameset, $errors ) ) {
    error_exit( $errors );
}

$org_frames = $frameset;

if ( !add_adjacent_frames( $input->adjacent_frames, $frameset, $errors ) ) {
    error_exit( $errors );
}

#$ga->tcpmessage( [ "_message" => [ "text" => "frameset: " . json_encode( $frameset ) ] ] );
$ga->tcpmessage( [ "_textarea" => "Models to include : " . implode( ",", $frameset ) . "\n" ] );

$frames_left = [];

if ( is_dir( $procdir ) ) {
    if ( !get_frames_to_run( $procdir, $frameset, $frames_left, $errors ) ) {
        error_exit( $errors );
    }
} else {
    $frames_left = $frameset;
}

if ( count( $frames_left ) ) {
    $ga->tcpmessage( [ "_textarea" => "Models to process (without prior results) : " . implode( ",", $frames_left ) . "\n" ] );
}
#$ga->tcpmessage( [ "_message" => [ "text" => "frames_left: " . json_encode( $frames_left ) ] ] );

$ga->tcpmessage( [ "_textarea" => "\n" ] );

$models_to_process_count = count( $frames_left );

## check whether model 0 will need a fresh WAXSiS run (convergence changed, no cached file yet)
$load_convergence_predlg = isset( $cgstate->state->waxsis_load_convergence )
    ? $cgstate->state->waxsis_load_convergence
    : $waxsis_convergence_mode;

$waxsis_model0_cached_file_predlg = "waxsis/intensity_waxsis${waxsis_suffix}.calc";
$model0_recompute_needed = ( $load_convergence_predlg !== $input->waxsis_convergence_mode )
                           && !file_exists( $waxsis_model0_cached_file_predlg );

$models_to_estimate = $models_to_process_count + ( $model0_recompute_needed ? 1 : 0 );

$convergence_changed = ( $load_convergence_predlg !== $input->waxsis_convergence_mode );

if ( isset( $cgstate->state->waxsis_last_run_time_minutes ) &&
     $cgstate->state->waxsis_last_run_time_minutes > 0 ) {
    $estimated_time_to_completion = dhms_from_minutes( intval( $cgstate->state->waxsis_last_run_time_minutes * $models_to_estimate * 1.2 + .5 ) );
} else {
    $estimated_time_to_completion = "*unknown duration*";
}

$ga->tcpmessage( [ "_textarea" =>
                   "Original models preselected : " . count( $org_frames ) . "\n"
                   . "Number of models including adjacent frames : " . count( $frameset ) . "\n"
                   . "\n"
                   . "\n"
                 ] );

## ask really proceed

$estimate_note = $convergence_changed
    ? "<br><br><i>Note: convergence mode changed from '$load_convergence_predlg' (Load Structure) to '$input->waxsis_convergence_mode'. "
      . ( $model0_recompute_needed
          ? "Model 0 (Load Structure structure) will also be recomputed (+1 included in count above). "
          : "Model 0 uses a previously cached result for this convergence. " )
      . "Time estimate is based on Load Structure timing and may differ significantly.</i>"
    : "<br><br>Projected time will be updated upon completion of each new WAXSiS run at your selected convergence mode.";

$response =
    json_decode(
        $ga->tcpquestion(
            [
             "id"           => "q1"
             ,"title"       => "<h5>Proceed with computations? </h5>"
             ,"icon"        => "warning.png"
             ,"text"        => "The estimated time to complete WAXSiS<br>calculations on <strong>$models_to_estimate</strong> models is <strong>$estimated_time_to_completion</strong>$estimate_note"
             ,"timeouttext" => "The time to respond has expired, please submit again."
             ,"buttons"     => [ "Yes, proceed", "Cancel for now" ]
             ,"fields" => [
                 [
                  "id"          => "l1"
                  ,"type"       => "label"
                  ,"label"      => ""
                  ,"align"      => "center"
                 ]
             ]
            ]
        )
    );

if ( isset( $response->error ) && strlen( $response->error ) ) {
    error_exit( "Please submit again" );
}

if ( $response->_response->button == "cancelfornow" ) {
    error_exit( "Canceled - prior results kept", true, $restore_old_data, 'information.png' );
}

## collect models

if ( !$do_testing ) {
## why should we clear the directory?
    if ( !is_dir( $procdir ) ) {
        run_cmd( "rm -fr $procdir; mkdir $procdir" );
#    } else {
#        run_cmd( "mkdir $procdir" );
    }    
}

## link existing frames

progress_text( "Extracting additional frames if needed" );

$ga->tcpmessage( [ 'processing_progress' => 0.01 ] );

$names = [];

if ( !link_existing_frames( $frameset, "preselected", $procdir, $names, $errors ) ) {
    error_exit( $errors );
}

# $output->_textarea = json_encode( $names, JSON_PRETTY_PRINT ) . "\n";

## get instance to run waxsis
### will need to get multiples

progress_text( 'Waiting for resources to run WAXSiS calculations.' );

if ( !$em->acquire( gethostname() . ":$logon:$input->_uuid" ) ) {
    error_exit( $em->errors );
}

$em_ip = $em->ip();
$em_id = $em->id();

$textarea_key = "_textarea";

$ga->tcpmessage( [ $textarea_key => "Acquired resources for WAXSiS ($em_id, $em_ip)\n" ] );

## run waxsis calcs

$waxsis_params = 
    (object)[
        'qpoints'             => $cgstate->state->qpoints + 10 # 10 to compensate for low-q region
        ,'maxq'               => $cgstate->state->qmax * $max_q_multiplier  # extend to prevent extrapolation when interpolating
        ,'convergence'        => $input->waxsis_convergence_mode
        ,'expfile'            => $cgstate->state->saxsiqfile
        ,'solvent_e_density'  => $cgstate->state->solvent_e_density
        ,'subdir'             => 'waxsisfinal'
        ,'host'               => $em_ip
    ];


$count = count( $names );

if ( !$count ) {
    error_exit( "no frames found to process" );
}

## setup sas with Exp. I(q) data

$plotname = "I(q) waxsis nnls";
$sas->create_plot_from_plot( SAS::PLOT_IQ, $plotname, $cgstate->state->output_load->iqplot
                             ,[
                                 'title' => PLOT_TITLE_IQ_NNLS_WAXSIS_FINAL
                                 ,'titlefontsize' => 14
                             ]);

$sas->remove_plot_data( $plotname, "Res./SD" );
$sas->remove_plot_data( $plotname, "WAXSiS" );
$sas->remove_data( "Res./SD" );

$chi2  = -1;
$rmsd  = -1;
$scale = 0;

## define waxsis_cb here so it is available for model 0 recompute below as well as the per-model loop
$waxsis_lc = 0;
$waxsis_cb = function( $line ) {
    global $ga;
    global $textarea_key;
    global $waxsis_lc;

    $waxsis_lc++;

    if ( preg_match( '/(Running yasara|Yasara MD|mdrun|Retrying)/i', $line ) ) {
        $ga->tcpmessage( [
                             $textarea_key => $line
                         ] );
    }
};

## check if model 0 (load structure) WAXSiS needs recomputing for the selected convergence mode

$waxsis_model0_cached_file = "waxsis/intensity_waxsis${waxsis_suffix}.calc";

$load_convergence = isset( $cgstate->state->waxsis_load_convergence )
    ? $cgstate->state->waxsis_load_convergence
    : $waxsis_convergence_mode;  ## fallback for projects run before this feature

if ( $load_convergence !== $input->waxsis_convergence_mode ) {
    if ( file_exists( $waxsis_model0_cached_file ) ) {
        $ga->tcpmessage( [ $textarea_key =>
            "WAXSiS model 0 previously computed for convergence '$input->waxsis_convergence_mode', using cached result\n"
        ] );
    } else {
        $ga->tcpmessage( [ $textarea_key =>
            "WAXSiS convergence changed from '$load_convergence' (Load Structure) to '$input->waxsis_convergence_mode' (Final Model).\n"
            . "Recomputing WAXSiS for model 0 (Load Structure structure)...\n"
        ] );

        $model0_pdb = preg_replace( '/-somo\.pdb$/', '', $cgstate->state->output_load->name ) . "-somo.pdb";
        $model0_params = clone $waxsis_params;
        $model0_params->subdir = 'waxsis';

        $time_start = dt_now();
        run_waxsis( $model0_pdb, $model0_params, $waxsis_cb );
        $model0_recompute_time = dt_duration_minutes( $time_start, dt_now() );
        $tot_waxsis_time += $model0_recompute_time;
        $avg_waxsis_time  = $tot_waxsis_time;

        if ( !file_exists( "waxsis/intensity_waxsis.calc" ) ) {
            error_exit( "WAXSiS did not produce the expected I(q) file for model 0" );
        }
        run_cmd( "cp waxsis/intensity_waxsis.calc $waxsis_model0_cached_file" );
    }

    ## reload model 0 WAXSiS data into the sas object, interpolated and scaled onto Exp. I(q) grid
    ## (mirrors the per-model loop: load -> interpolate -> scale_nchi2 -> remove intermediates)
    $sas->remove_data( "WAXSiS" );
    $sas->load_file( SAS::PLOT_IQ, "WAXSiS org", $waxsis_model0_cached_file );
    $sas->interpolate( "WAXSiS org", "Exp. I(q)", "WAXSiS interp" );
    $sas->scale_nchi2( "Exp. I(q)", "WAXSiS interp", "WAXSiS", $chi2, $scale );
    $sas->remove_data( "WAXSiS org" );
    $sas->remove_data( "WAXSiS interp" );
    $sas->add_plot( $plotname, "WAXSiS" );
} else {
    if ( !file_exists( $waxsis_model0_cached_file ) ) {
        ## first run after this feature was added: cache the existing result
        run_cmd( "cp waxsis/intensity_waxsis.calc $waxsis_model0_cached_file 2>/dev/null" );
    }
}

## always recompute rmsd, chi^2, p_value and rebuild output_load->iqplot
## (stats must be consistent with the current convergence and displayed results)
$m0_sas = new SAS( false );
$m0_chi2  = -1;
$m0_rmsd  = -1;
$m0_scale = 0;
if (
    $m0_sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q)", $cgstate->state->output_loadsaxs->iqplot )
    && $m0_sas->plot_options( "I(q)", [ 'title' => PLOT_TITLE_IQ_WAXSIS ] )
    && $m0_sas->load_file( SAS::PLOT_IQ, "WAXSiS_org", $waxsis_model0_cached_file )
    && $m0_sas->interpolate( "WAXSiS_org", "Exp. I(q)", "WAXSiS_interp" )
    && $m0_sas->scale_nchi2( "Exp. I(q)", "WAXSiS_interp", "WAXSiS", $m0_chi2, $m0_scale )
    && $m0_sas->rmsd( "Exp. I(q)", "WAXSiS", $m0_rmsd )
    && $m0_sas->add_plot( "I(q)", "WAXSiS" )
    && $m0_sas->calc_residuals( "Exp. I(q)", "WAXSiS", "Res./SD" )
    && $m0_sas->add_plot_residuals( "I(q)", "Res./SD" )
    && $m0_sas->plot_trace_options( "I(q)", "Res./SD", [ 'linecolor_number' => 1 ] )
    ) {
    $m0_rmsd = round( $m0_rmsd, 3 );
    $m0_chi2 = round( $m0_chi2, 3 );
    $m0_annotate_msg = "";
    if ( $m0_rmsd != -1 ) {
        $m0_annotate_msg .= "RMSD $m0_rmsd   ";
    }
    if ( $m0_chi2 != -1 ) {
        $m0_annotate_msg .= "nChi^2 $m0_chi2   ";
    }
    $m0_pvalueresults = (object)[];
    $m0_sas->compute_p_value( "Exp. I(q)", "WAXSiS", $m0_pvalueresults );
    if ( isset( $m0_pvalueresults->p_value ) ) {
        $m0_annotate_msg .= sprintf( "P-value %.3f <span style='color:%s'>&#9724;</span> ", $m0_pvalueresults->p_value, $m0_pvalueresults->p_value >= 0.05 ? 'green' : ($m0_pvalueresults->p_value >= 0.01 ? 'yellow' : 'red') );
    }
    if ( strlen( $m0_annotate_msg ) ) {
        $m0_sas->annotate_plot( "I(q)", $m0_annotate_msg );
    }
    $cgstate->state->output_load->iqplot = $m0_sas->plot( "I(q)" );
} else {
    $ga->tcpmessage( [ $textarea_key => "Warning: could not recompute model 0 stats: " . $m0_sas->last_error . "\n" ] );
}

$sas->rename_data( "WAXSiS", $waxsis_data_name );

## seed per-model loop timing: if model 0 was just recomputed, use that time as the initial avg;
## otherwise fall back to the saved Load Structure time
if ( isset( $model0_recompute_time ) && $model0_recompute_time > 0 ) {
    $avg_waxsis_time = $model0_recompute_time;
    $tot_waxsis_time = $model0_recompute_time;
    $models_processed_init = 1;
} else {
    $avg_waxsis_time = isset( $cgstate->state->waxsis_last_run_time_minutes ) && $cgstate->state->waxsis_last_run_time_minutes > 0
        ? $cgstate->state->waxsis_last_run_time_minutes
        : 0
        ;
    $tot_waxsis_time = 0;
    $models_processed_init = 0;
}

# $ga->tcpmessage( [ "_message" => [ "text" => "avg_waxsis_time: $avg_waxsis_time" ] ] );

$waxsis_lc = 0;  ## reset line counter for per-model loop

$waxsisiqfile    = $waxsis_params->subdir . "/intensity_waxsis.calc";
$iqfiles         = [];
$alliqframes     = [ $waxsis_data_name ];
$waxsis_failures = [];

$chi2  = -1;
$rmsd  = -1;
$scale = 0;
$to_compute = $models_to_process_count;
$pos = 0;
#$ga->tcpmessage( [ "_message" => [ "text" => "avg_waxsis_time (2): $avg_waxsis_time" ] ] );
$models_processed = $models_processed_init;

switch( $input->waxsis_convergence_mode ) {
    case "normal"   : $waxsis_suffix = "_n"; break;
    case "thorough" : $waxsis_suffix = "_t"; break;
    case "quick"    : $waxsis_suffix = "_q"; break;
    default         : error_exit( "internal error - unknown or unsupported WAXSiS convergence mode" );
}

## denominator includes model 0 if it was recomputed, so progress fraction stays in [0,1)
$tot_models_to_process_count = $models_to_process_count + $models_processed_init;
    
## allow multiple remotes

foreach ( $names as $name ) {
    
    if ( $models_to_process_count ) {
        $pos_frac = ( $models_processed + .5 ) / $tot_models_to_process_count;
    } else {
        $pos_frac = .5;
    }

    $frame = $frameset[ $pos ];
    $pdbnoext = preg_replace( '/\.pdb$/', '', $name );

    if ( $models_processed ) {
        $avg_waxsis_time = $tot_waxsis_time / $models_processed;
    }

    if ( $avg_waxsis_time > 0 ) {
        $estimated_time_to_completion = dhms_from_minutes( intval( $avg_waxsis_time * ( $models_to_process_count ) * 1.2 ) + .5 );
    } else {
        $estimated_time_to_completion = "*unknown duration*";
    }
    
    $ga->tcpmessage( [ 'processing_progress' => $pos_frac
                     , '_textarea' => "Processing frame $frame\n" ] );

    progress_text( "Running WAXSiS calculations on frame $frame<br>Estimated $estimated_time_to_completion remaining" );

    $ok = false;

    if ( !$do_testing ) {
        $iqfile = "$procdir/$pdbnoext-waxsis${waxsis_suffix}.dat";
        if ( !file_exists( $iqfile ) ) {
            $time_start = dt_now();
            $ok =
                run_waxsis(
                    "$procdir/$name"
                    ,$waxsis_params
                    ,$waxsis_cb
                    ,false
                );
            $tot_waxsis_time += dt_duration_minutes( $time_start, dt_now() );

            if ( $ok ) {
                if ( !file_exists( $waxsisiqfile ) ) {
                    error_exit( "WAXSiS did not produce the expected I(q) file" );
                }

                run_cmd( "mv $waxsisiqfile $iqfile" );
                $iqfiles[] = $iqfile;
            } else {
                $waxsis_failures[] = $frame;
            }
            --$models_to_process_count;
            ++$models_processed;
        } else {
            
            $ga->tcpmessage( [ '_textarea' => "Frame $frame previously processed\n" ] );

            $iqfiles[] = $iqfile;
            $ok = true;
        }
    } else {
        $iqfiles[] = "$procdir/$pdbnoext-waxsis.dat";
    }
    
    if ( $ok ) {
        $thisiqframe   = "I(q)<sub>W</sub> mod. $frame";
        $alliqframes[] = $thisiqframe;
        #    $ga->tcpmessage( [
        #                         "_textarea" =>
        #                         "thisiqframe $thisiqframe\n"
        #                     ] );
        
        $sas->load_file( SAS::PLOT_IQ, "$thisiqframe org", end( $iqfiles ) );
        $sas->interpolate( "$thisiqframe org", "Exp. I(q)", "$thisiqframe interp" );
        $sas->scale_nchi2( "Exp. I(q)", "$thisiqframe interp", $thisiqframe, $chi2, $scale );
        $sas->remove_data( "$thisiqframe org" );
        $sas->remove_data( "$thisiqframe interp" );
    }

    ++$pos;
}

## waxsis done, release elastic resources
$em->release();

## nnls

## NNLS on I(q)

progress_text( "Running NNLS on I(q)" );

#$ga->tcpmessage( [
#                     "_textarea" =>
#                     "alliqframes" . json_encode( $alliqframes, JSON_PRETTY_PRINT ) . "\n"
#                 ] );

$iqresults = [];

$sas->nnls( "Exp. I(q)", $alliqframes, "I(q)<sub>W</sub> NNLS fit", $iqresults, true );

# $ga->tcpmessage( [ "_textarea" => "iqresults => " . json_encode( $iqresults, JSON_PRETTY_PRINT ) ] );

$sas->add_plot( $plotname, "I(q)<sub>W</sub> NNLS fit" );

foreach ( $iqresults as $k => $v ) {
    $sas->add_plot( $plotname, $k );
}

### residuals
$chi2  = -1;
$rmsd  = -1;
$scale = 0;

$sas->scale_nchi2( "Exp. I(q)", "I(q)<sub>W</sub> NNLS fit", "I(q) NNLS fit-rescaled", $chi2, $scale );
$sas->rmsd( "Exp. I(q)", "I(q)<sub>W</sub> NNLS fit", $rmsd );
$sas->calc_residuals( "Exp. I(q)", "I(q)<sub>W</sub> NNLS fit", "I(q)<sub>W</sub> fit Res./SD" );

## move NNLS fit to last curve, but before residuals
$sas->remove_plot_data( $plotname, "I(q)<sub>W</sub> NNLS fit" );
$sas->recolor_plot( $plotname, [ 1 ] );
$sas->add_plot( $plotname, "I(q)<sub>W</sub> NNLS fit" );
$sas->plot_trace_options( $plotname, "I(q)<sub>W</sub> NNLS fit", [ 'linecolor_number' => 1 ] );

$sas->add_plot_residuals( $plotname, "I(q)<sub>W</sub> fit Res./SD" );
$sas->plot_trace_options( $plotname, "I(q)<sub>W</sub> fit Res./SD", [ 'linecolor_number' => 1 ] );

$rmsd = round( $rmsd, 3 );
$chi2 = round( $chi2, 3 );
$annotate_msg = "";
if ( $rmsd != -1 ) {
    $annotate_msg .= "RMSD $rmsd   ";
}
if ( $chi2 != -1 ) {
    $annotate_msg .= "nChi^2 $chi2   ";
}

## p-values
$pvalueresults = (object)[];
$sas->compute_p_value( "Exp. I(q)", "I(q)<sub>W</sub> NNLS fit", $pvalueresults );
if ( isset( $pvalueresults->p_value ) ) {
# large square   $annotate_msg .= sprintf( "P-value %f <span style='color:red'>&#11035;&#65038;</span> ", $pvalueresults->p_value );
    $annotate_msg .= sprintf( "P-value %.3f <span style='color:%s'>&#9724;</span> ", $pvalueresults->p_value, $pvalueresults->p_value >= 0.05 ? 'green' : ($pvalueresults->p_value >= 0.01 ? 'yellow' : 'red') );
# n & c, no square   $annotate_msg .= sprintf( "P-value %f C %d N %d  ", $pvalueresults->p_value, $pvalueresults->longest_run, $pvalueresults->total_length );
}

if ( strlen( $annotate_msg ) ) {
    $sas->annotate_plot( $plotname, $annotate_msg );
}


/*
$ga->tcptextarea( $sas->data_summary( $sas->data_names() ) );
$ga->tcptextarea( $sas->dump_plots() );
$pvalueresults = (object)[];
$sas->compute_p_value( "Exp. I(q)", "I(q)<sub>W</sub> mod. 0", $pvalueresults );
if ( isset( $pvalueresults->p_value ) ) {
    $annotate_msg = sprintf( "P-value %f C %d N %d  ", $pvalueresults->p_value, $pvalueresults->longest_run, $pvalueresults->total_length );
    $sas->annotate_plot( "iqplot", $annotate_msg, true );
}
*/

/* save for after color assignment
$ga->tcpmessage(
    [
     "iqplotwaxsis" => $sas->plot( $plotname )
    ]
    );

$output->iqplotwaxsis = $sas->plot( $plotname );
*/


### summary results

$fitname = $input->_project . ".fit";
$sas->save_fit( "Exp. I(q)", "I(q)<sub>W</sub> NNLS fit", $fitname );

$output->iqresultswaxsis = nnls_results_to_html( $iqresults );

### save results to state

$cgstate->state->iq_waxsis_nnlsresults = json_decode( json_encode( $iqresults ) );

## setup csvdownloads

$bname     = preg_replace( '/-somo\.pdb$/', '', $cgstate->state->output_load->name );
$sassomoiqname = $bname . "_waxsis_somo_iq.csv";
$sascoliqname = $bname . "_waxsis_iq.csv";

## rename I(q)<sub>W</sub> names to plain-text form for CSV column headers
## (plot trace names already baked; data store rename does not affect them)
$csv_nnls_fit = str_replace( 'I(q)<sub>W</sub> ', 'I(q) ', "I(q)<sub>W</sub> NNLS fit" );
$sas->rename_data( "I(q)<sub>W</sub> NNLS fit", $csv_nnls_fit );

$csv_alliqframes = [];
foreach ( $alliqframes as $iqframe ) {
    $csvframe = str_replace( 'I(q)<sub>W</sub> ', 'I(q) WAXSiS ', $iqframe );
    if ( $csvframe !== $iqframe ) {
        $sas->rename_data( $iqframe, $csvframe );
    }
    $csv_alliqframes[] = $csvframe;
}

$sas->save_data_csv(
    array_merge( [ "Exp. I(q)", $csv_nnls_fit ], $csv_alliqframes )
    ,$sassomoiqname
    ,1
    ,'/I\(q\) /'
    ,"$bname "
    );

$sas->save_data_csv_tr(
    array_merge( [ "Exp. I(q)", $csv_nnls_fit ], $csv_alliqframes )
    ,$sascoliqname
    ,1
    ,'/I\(q\) /'
    ,"$bname "
    );

## setup pdb

$pdboutname = "waxsisfinalset.pdb";
$pdbout     = "";

# $ga->tcpmessage( [ "_textarea" => "nnlsresults :\n" . json_encode( $iqresults, JSON_PRETTY_PRINT ) . "\n" ] );

$pdbnames = (object)[];

foreach ( $iqresults as $name => $conc ) {
    $tmpname = explode( ' ', $name );
    $frame = end( $tmpname );

    ## for testing
    $pdbname = "${bname}-somo.pdb";
    $pdbnames->waxsis = $pdbname;
    ## end for testing  

    if ( $name == $waxsis_data_name ) {
        $pdbname = "${bname}-somo.pdb";
        if ( !file_exists( "$pdbname" ) ) {
            error_exit( "expected file '$pdbname' not found" );
        }
        $pdbnames->waxsis = $pdbname;

        $pdbout .= "MODEL $waxsis_model_number\n"
            . run_cmd( "grep -P '^(ATOM|HETATM)' $pdbname" )
            . "ENDMDL\n"
            ;
        
    } else {
        $frame_padded = str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
        $pdbname = "${bname}-somo-m$frame_padded.pdb";
        if ( !file_exists( "$procdir/$pdbname" ) ) {
            error_exit( "expected file '$procdir/$pdbname' not found" );
        }
        $pdbnames->$frame = "$procdir/$pdbname";

        $pdbout .= "MODEL $frame\n"
            . run_cmd( "grep -P '^(ATOM|HETATM)' $procdir/$pdbname" )
            . "ENDMDL\n"
            ;
    }
};

$pdbout .= "END\n";

if ( !file_put_contents( $pdboutname, $pdbout ) ) {
    error_exit( "error creating '$pdboutname'" );
}    

## $ga->tcpmessage( [ "_textarea" => "pdbnames[]:\n" . json_encode( $pdbnames, JSON_PRETTY_PRINT ) . "\n" ] );
$cgstate->state->waxsis_final_pdb_names = $pdbnames;

$output->downloads = $cgstate->state->output_load->downloads;
$output->iqresultswaxsis .=
    "<div>"
    . "&nbsp;&nbsp;&nbsp;"
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sascoliqname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) SOMO style csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sassomoiqname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>PDB (NMR-style) &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $pdboutname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>FIT &#x21D3;</a>&nbsp;&nbsp;&nbsp;<br>&nbsp;", $fitname )
    . "<br>&nbsp;"
    . "</div>"
    ;

$output->struct = (object) [
    "file" => "results/users/$logon/$base_dir/$pdboutname"
    #    ,"script" => "background white;ribbon only;select */29; color blue; select */30; color green; frame all"
    ,"script" => "background white;ribbon only;"
    ];

## for color match
$pos = 0;
$cgstate->state->iq_waxsis_nnlsresults_colors = (object)[];

foreach ( $iqresults as $name => $conc ) {
    if ( $name == $waxsis_data_name ) {
        $frame = $waxsis_model_number;
    } else {
        $tmpname = explode( ' ', $name );
        $frame = end( $tmpname );
    }
    $cgstate->state->iq_waxsis_nnlsresults_colors->$name = get_color( $pos );
    $sas->plot_trace_options( $plotname, $name, [ 'linecolor' => get_color( $pos ) ] );

    $output->struct->script .= "select */$frame;color " . get_color( $pos++ ) . ";";
}
$output->struct->script .= "frame all;";

# $output->_textarea .= "script : " . $output->struct->script . "\n";

#$output->struct = "results/users/$logon/$base_dir/$pdboutname";

$ga->tcpmessage(
    [
     "iqplotwaxsis" => $sas->plot( $plotname )
    ]
    );

$output->iqplotwaxsis = $sas->plot( $plotname );

## final rg plot

require_once "plotlyhist.php";

$rgdata = (object) [];

if ( isset( $cgstate->state->output_load->Rg ) ) {
    $rgdata->{ "Original model<br>SOMO computed" } =
        (object) [
            "Rg" => $cgstate->state->output_load->Rg
            ,"color" => "blue"
        ];
};

if ( isset( $cgstate->state->output_load->prplot ) ) {
    require_once "sas.php";
    $sas = new SAS();
    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)", $cgstate->state->output_load->prplot );
    $prrg = 0;
    $sas->compute_rg_from_pr( "Exp. P(r)", $prrg );
    $rgdata->{ "Exp. P(r)<br>SOMO computed on regular grid" } =
        (object) [
            "Rg" => $prrg
            ,"color" => "brown"
        ];
}

final_hist( $output, $cgstate->state->iq_waxsis_nnlsresults, $cgstate->state->iq_waxsis_nnlsresults_colors, $rgdata, $input->adjacent_frames );
$cgstate->state->final_adjacent_frames = $input->adjacent_frames;

## pr reconstruct
#    };

$plotnameorg       = "P(r) orig.";
$plotnamewaxsis    = "P(r) waxsis reconstruct";
$pr_recon_id       = "pr_recon";

$sas->create_plot_from_plot( SAS::PLOT_PR, $plotnameorg, $cgstate->state->output_load->prplot );
$sas->create_plot_from_plot( SAS::PLOT_PR, $plotnamewaxsis, $cgstate->state->output_load->prplot );
$sas->plot_options( $plotnamewaxsis,
    [
    "titlefontsize" => 14
    ,"title" => "P(r) Expt. vs starting and<br>reconstructed from final models selected"
    ] );

# $ga->tcptextarea( $sas->data_summary( $sas->data_names() ) );
# $ga->tcpdebugjson( '$cgstate->state->iq_waxsis_nnlsresults', $cgstate->state->iq_waxsis_nnlsresults );
# $ga->tcpdebugjson( '$cgstate->state->waxsis_final_pdb_names', $cgstate->state->waxsis_final_pdb_names );

$prfiles = array_values( (array)$cgstate->state->waxsis_final_pdb_names);
$prnames = [];
foreach ( $prfiles as $k => $v ) {
    $prnames[ $k ] = "notnormed P(r) mod. " . model_no_from_pdb_name( $v );
}

# $ga->tcpdebugjson( '$prnames', $prnames );

$sas->compute_pr_many( $prfiles, $prnames );
$sas->extend_pr( array_merge( $prnames, [ "Exp. P(r)" ] ) );

## migrate old-style iq_waxsis_nnlsresults keys if present
if ( isset( $cgstate->state->iq_waxsis_nnlsresults ) ) {
    $migrated_nnls   = (object)[];
    $migrated_colors = (object)[];
    $old_prefix      = 'I(q) WAXSiS mod. ';
    $new_prefix      = 'I(q)<sub>W</sub> mod. ';
    foreach ( $cgstate->state->iq_waxsis_nnlsresults as $k => $v ) {
        $newk = str_replace( $old_prefix, $new_prefix, $k );
        $migrated_nnls->$newk = $v;
        if ( isset( $cgstate->state->iq_waxsis_nnlsresults_colors->$k ) ) {
            $migrated_colors->$newk = $cgstate->state->iq_waxsis_nnlsresults_colors->$k;
        }
    }
    $cgstate->state->iq_waxsis_nnlsresults        = $migrated_nnls;
    $cgstate->state->iq_waxsis_nnlsresults_colors = $migrated_colors;
}

$scalednames = [];
$normednames = [];
$messages    = '';

foreach ( $prnames as $v ) {
    $frame = frame_no_from_data_name( $v );
    $normedname = substr( $v, 10 );
    $scaledname = "scaled $normedname";
    $normednames[] = $normedname;

    $sas->norm_pr( $v, $cgstate->state->output_load->mw, $normedname );

    if ( isset( $cgstate->state->iq_waxsis_nnlsresults->{ "I(q)<sub>W</sub> mod. $frame" } ) ) {
        $sas->norm_pr( $v, $cgstate->state->output_load->mw * $cgstate->state->iq_waxsis_nnlsresults->{ "I(q)<sub>W</sub> mod. $frame" }, $scaledname );
        $scalednames[] = $scaledname;
    }
    $sas->remove_data( $v );
}

# $ga->tcpdebugjson( '$scalednames', $scalednames );
# $ga->tcptextarea( $messages );

$combinedname = "Recon.";
$residname = "Recon. Resid.";
$sas->sum_data( $scalednames, $combinedname );
$sas->add_plot( $plotnamewaxsis, $combinedname );
$sas->remove_data( $scalednames );
$sas->remove_data( $normednames );

$rmsd = 1e99;
$sas->rmsd_residuals( "Exp. P(r)", $combinedname, $residname, $rmsd );
$sas->add_plot_residuals( $plotnamewaxsis, $residname );
$rmsd = sprintf( "%.2f", $rmsd );
$sas->annotate_plot( $plotnamewaxsis, "Recon. RMSD $rmsd", true );
$sas->plot_trace_options( $plotnamewaxsis, "Resid.", [ 'linecolor_number' => 1 ] );
$sas->plot_trace_options( $plotnamewaxsis, $combinedname, [ 'linecolor_number' => 2 ] );
$sas->plot_trace_options( $plotnamewaxsis, $residname, [ 'linecolor_number' => 2 ] );

$output->$pr_recon_id = $sas->plot( $plotnamewaxsis );

## save state

if ( count( $waxsis_failures ) ) {
    $msg =
        "WAXSiS simulation failures occured on " . count( $waxsis_failures ) . " Models:\n"
        . implode( ' ', $waxsis_failures ) . "'<br>"
        . "These frames are excluded from the final NNLS fit<br>"
        ;

    $output->_message = [
        "text" => $msg
        ,"icon" => "warning.png"
        ];

    $ga->tcpmessage( [ "_textarea" => $msg ] );
}

$cgstate->state->final_waxsis_failures = $waxsis_failures;
$cgstate->state->output_final  = $output;

if ( isset( $ga->cache_obj->_textarea ) ) {
    $cgstate->state->output_final->_textarea = $ga->cache_obj->_textarea;
}

## unsaved outputs (since they were previously saved

if ( isset( $cgstate->state->output_load ) 
     && isset( $cgstate->state->output_load->iqplot ) ) {
    $output->iqplot = unserialize(serialize($cgstate->state->output_load->iqplot));

    foreach( $output->iqplot->data as $curve ) {
        if ( $curve->name == "WAXSiS" ) {
            $curve->name = "I(q)<sub>W</sub> mod. 0";
            break;
        }
    }
}

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

#$output->_textarea =
#    "iqfiles[]:\n" . json_encode( $iqfiles, JSON_PRETTY_PRINT ) . "\n"
    #    . $sas->data_summary( $sas->data_names() )
#    ;

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";
$output->processing_progress = 0;
$output->progress_text = progress_text( 'Processing complete', '', true );
unset( $output->_textarea );

echo json_encode( $output );
