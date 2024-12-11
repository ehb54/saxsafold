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

if ( !isset( $cgstate->state->nnlsiqresults )
    || !isset( $cgstate->state->nnlsprresults ) ) {
    error_exit( "Did you successfully <i>Compute I(q)/P(r)</i>?" );
}

## process inputs here to produce output

if ( $input->adjacent_frames > $cgstate->state->mmcstride / 2 ) {
    error_exit( "The maximum value allowed for the <i>Additional adjacent frame count</i> is " . ( intval( $cgstate->state->mmcstride / 2 ) ) );
}

### build up set of models

include "finalmodel_funcs.php";
include "waxsis.php";
include "sas.php";
$sas = new SAS( false );

$errors = "";
$frameset = [];

if ( !initial_model_set( $frameset, $errors ) ) {
    error_exit( $errors );
}

$org_frames = $frameset;

if ( !add_adjacent_frames( $input->adjacent_frames, $frameset, $errors ) ) {
    error_exit( $errors );
}

$models_to_process_count = count( $frameset );

if ( isset( $cgstate->state->waxsis_last_run_time_minutes ) &&
     $cgstate->state->waxsis_last_run_time_minutes > 0 ) {
    $estimated_time_to_completion = dhms_from_minutes( intval( $cgstate->state->waxsis_last_run_time_minutes * $models_to_process_count * 1.2 + .5 ) );
} else {
    $estimated_time_to_completion = "*unknown duration*";
}

$ga->tcpmessage( [ "_textarea" =>
                   "Original models preselected " . count( $org_frames ) . "\n"
                   . "Total models with additional frames $models_to_process_count\n"
                   . "\n"
                   . "\n"
                 ] );


## ask really proceed

$response =
    json_decode(
        $ga->tcpquestion(
            [
             "id"           => "q1"
             ,"title"       => "<h5>Proceed with computations? </h5>"
             ,"icon"        => "warning.png"
             ,"text"        => "The estimated time to complete WAXSiS<br>calculations on <strong>$models_to_process_count</strong> models is <strong>$estimated_time_to_completion</strong>"
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

if ( $response->_response->button == "cancelfornow" ) {
    $output->_textarea = "Processing canceled by user request\n";
    echo json_encode( $output );
    exit;
}

## collect models

$procdir = "waxsissets";

if ( !$do_testing ) {
## why should we clear the directory?
    if ( !is_dir( $procdir ) ) {
        run_cmd( "rm -fr $procdir; mkdir $procdir" );
#    } else {
#        run_cmd( "mkdir $procdir" );
    }    
}

## link existing frames

progress_text( "Extracting " . ( $models_to_process_count - count( $org_frames ) ) . " additonal frames" );
$ga->tcpmessage( [ 'processing_progress' => 0.01 ] );

$names = [];

if ( !link_existing_frames( $frameset, "preselected", $procdir, $names, $errors ) ) {
    error_exit( $errors );
}

# $output->_textarea = json_encode( $names, JSON_PRETTY_PRINT ) . "\n";


## run waxsis calcs

$waxsis_params = 
    (object)[
        'qpoints'             => $cgstate->state->qpoints + 10 # 10 to compensate for low-q region
        ,'maxq'               => $cgstate->state->qmax * $max_q_multiplier  # extend to prevent extrapolation when interpolating
        ,'convergence'        => $waxsis_convergence_mode
        ,'expfile'            => $cgstate->state->saxsiqfile
        ,'solvent_e_density'  => $cgstate->state->solvent_e_density
        ,'subdir'             => 'waxsisfinal'
    ];


$pos = 0;
$count = count( $names );


if ( !$count ) {
    error_exit( "no frames found to process" );
}

## setup sas with Exp. I(q) data

$plotname = "I(q) waxsis nnls";
$sas->create_plot_from_plot( SAS::PLOT_IQ, $plotname, $cgstate->state->output_loadsaxs->iqplot
                             ,[
                                 'title' => "I(q)<br>Expt. + NNLS selected/reconstructed<br>from all computed on preselected models"
                                 ,'titlefontsize' => 14
                             ]);

$avg_waxsis_time = isset( $cgstate->state->waxsis_last_run_time_minutes ) && $cgstate->state->waxsis_last_run_time_minutes > 0
    ? $cgstate->state->waxsis_last_run_time_minutes
    : 0
    ;

$tot_waxsis_time = 0;

$waxsis_lc = 0;
$textarea_key = "_textarea";
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

$waxsisiqfile    = $waxsis_params->subdir . "/intensity_waxsis.calc";
$iqfiles         = [];
$alliqframes     = [];
$waxsis_failures = [];

$chi2  = -1;
$rmsd  = -1;
$scale = 0;

foreach ( $names as $name ) {
    
    $pos_frac = ( $pos + .5 ) / $count;
    $frame = $frameset[ $pos ];
    $pdbnoext = preg_replace( '/\.pdb$/', '', $name );

    if ( $pos ) {
        $avg_waxsis_time = $tot_waxsis_time / $pos;
    }

    if ( $avg_waxsis_time > 0 ) {
        $estimated_time_to_completion = dhms_from_minutes( intval( $avg_waxsis_time * ( $count - $pos ) * 1.2 ) + .5 );
    } else {
        $estimated_time_to_completion = "*unknown duration*";
    }
    
    $ga->tcpmessage( [ 'processing_progress' => $pos_frac ] );

    progress_text( "Running WAXSiS calculations on frame $frame<br>Estimated $estimated_time_to_completion remaining" );

    $ok = false;

    if ( !$do_testing ) {
        $iqfile = "$procdir/$pdbnoext-waxsis.dat";
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
        } else {
            $iqfiles[] = $iqfile;
            $ok = true;
        }
    } else {
        $iqfiles[] = "$procdir/$pdbnoext-waxsis.dat";
    }
    
    if ( $ok ) {
        $thisiqframe   = "I(q) WAXSiS mod. $frame";
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

## nnls

## NNLS on I(q)

progress_text( "Running NNLS on I(q)" );

#$ga->tcpmessage( [
#                     "_textarea" =>
#                     "alliqframes" . json_encode( $alliqframes, JSON_PRETTY_PRINT ) . "\n"
#                 ] );

$iqresults = [];

$sas->nnls( "Exp. I(q)", $alliqframes, "I(q) NNLS fit", $iqresults, true );

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
$sas->add_plot_residuals( $plotname, "I(q) fit Res./SD" );

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

$ga->tcpmessage(
    [
     "iqplotwaxsis" => $sas->plot( $plotname )
    ]
    );

$output->iqplotwaxsis = $sas->plot( $plotname );

### summary results

$output->iqresultswaxsis = nnls_results_to_html( $iqresults );

### save results to state

$cgstate->state->nnlsiqresultswaxsis = $iqresults;

## setup csvdownloads

$bname     = preg_replace( '/-somo\.pdb$/', '', $cgstate->state->output_load->name );
$sasiqname = $bname . "_waxsis_iq.csv";

$sas->save_data_csv(
    array_merge( [ "Exp. I(q)", "I(q) NNLS fit" ], $alliqframes )
    ,$sasiqname
    ,1
    ,'/I\(q\) /'
    ,"$bname "
    );
   
$output->csvdownloads =
    "<div>"
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sasiqname )
    . "</div>"
    ;

## setup pdb

$pdboutname = "waxsisfinalset.pdb";
$pdbout     = "";

foreach ( $iqresults as $name => $conc ) {
    $tmpname = explode( ' ', $name );
    $frame = end( $tmpname );
    $frame_padded = str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
    $pdbname = "${bname}-somo-m$frame_padded.pdb";
    if ( !file_exists( "$procdir/$pdbname" ) ) {
        error_exit( "expected file '$procdir/$pdbname' not found" );
    }

    $pdbout .= "MODEL $frame\n"
        . run_cmd( "grep -P '^(ATOM|HETATM)' $procdir/$pdbname" )
        . "ENDMDL\n"
        ;
};

$pdbout .= "END\n";

if ( !file_put_contents( $pdboutname, $pdbout ) ) {
    error_exit( "error creating '$pdboutname'" );
}    

$output->csvdownloads =
    "<div>"
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>I(q) csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sasiqname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>mmPDB &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $pdboutname )
    . "</div>"
    ;

function get_color( $pos ) {
    $colors = [
        "red"
        ,"orange"
        ,"yellow"
        ,"green"
        ,"blue"
        ];

    return $colors[ $pos % count( $colors ) ];
}

$output->struct = (object) [
    "file" => "results/users/$logon/$base_dir/$pdboutname"
    #    ,"script" => "background white;ribbon only;select */29; color blue; select */30; color green; frame all"
    ,"script" => "background white;ribbon only;"
    ];

$pos = 0;
foreach ( $iqresults as $name => $conc ) {
    $tmpname = explode( ' ', $name );
    $frame = end( $tmpname );
    $output->struct->script .= "select */$frame;color " . get_color( $pos++ ) . ";";
}
$output->struct->script .= "frame all;";

# $output->_textarea .= "script : " . $output->struct->script . "\n";

#$output->struct = "results/users/$logon/$base_dir/$pdboutname";

## save state

$cgstate->state->output_final  = $output;

## unsaved outputs (since they were previously saved

if ( isset( $cgstate->state->output_load ) 
     && isset( $cgstate->state->output_load->iqplot ) ) {
    $output->iqplot = &$cgstate->state->output_load->iqplot;
}

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

#$output->_textarea =
#    "iqfiles[]:\n" . json_encode( $iqfiles, JSON_PRETTY_PRINT ) . "\n"
#    . $sas->data_summary( $sas->data_names() )
#    ;

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";
$output->processing_progress = 0;
$output->progress_text = progress_text( 'Processing complete', '', true );

echo json_encode( $output );
