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

## make sure project is loaded

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !isset( $cgstate->state->saxsiqfile ) || !isset( $cgstate->state->saxsprfile ) ){
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

## does the project already exist ?

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !isset( $cgstate->state->saxsiqfile )
     || !isset( $cgstate->state->saxsprfile )
     || !isset($cgstate->state->qmin )
     || !isset( $cgstate->state->qmax )
     || !isset( $cgstate->state->qpoints ) ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

if ( !isset( $cgstate->state->flex )
     || !count( $cgstate->state->flex ) ) {
    error_exit( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !isset( $cgstate->state->mmcrunname ) ) {
    error_exit( "No MMC run name found, did you <i>Run MMC</i>?" );
}

if ( !isset( $cgstate->state->mmcdownloaded )
     || !isset( $cgstate->state->mmcextracted )
     || !strlen( $cgstate->state->mmcextracted ) ) {
    error_exit( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    

include_once "limits.php";
require "sas.php";
include "crysol.php";
require "computeiqpr_funcs.php";
$sas = new SAS();

## does the project already exist ?

require_once "remove.php";

$restore_old_data = function() {
    global $cgstate;
    global $ga;
    global $input;
    global $mdatas;

    $obj = (object)[];

    setup_computeiqpr_plots( $obj );

    if ( isset( $cgstate->state->output_iqpr ) ) {

        foreach ( $mdatas as $mdata ) {
            if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->plotallhtml} ) ) {
                $obj->{$mdata->tags->plotallhtml} = &$cgstate->state->output_iqpr->{$mdata->tags->plotallhtml};
            }
            if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->plotsel} ) ) {
                $obj->{$mdata->tags->plotsel} = &$cgstate->state->output_iqpr->{$mdata->tags->plotsel};
            } else {
                unset( $obj->{$mdata->tags->plotsel} );
            }

            if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->results} ) ) {
                $obj->{$mdata->tags->results} = &$cgstate->state->output_iqpr->{$mdata->tags->results};
            }
            if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->header_id} ) ) {
                $obj->{$mdata->tags->header_id} = &$cgstate->state->output_iqpr->{$mdata->tags->header_id};
            }
            if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->downloads} ) ) {
                $obj->{$mdata->tags->downloads} = &$cgstate->state->output_iqpr->{$mdata->tags->downloads};
            }
        }

        if ( isset( $cgstate->state->output_iqpr->pr_plotallhtml ) ) {
            $obj->pr_plotallhtml = &$cgstate->state->output_iqpr->pr_plotallhtml;
        }
        if ( isset( $cgstate->state->output_iqpr->pr_results ) ) {
            $obj->pr_results = &$cgstate->state->output_iqpr->pr_results;
        }
        if ( isset( $cgstate->state->output_iqpr->pr_plotsel ) ) {
            $obj->pr_plotsel = &$cgstate->state->output_iqpr->pr_plotsel;
        }
        if ( isset( $cgstate->state->output_iqpr->pr_header ) ) {
            $obj->pr_header = &$cgstate->state->output_iqpr->pr_header;
        }
        if ( isset( $cgstate->state->output_iqpr->pr_downloads ) ) {
            $obj->pr_downloads = &$cgstate->state->output_iqpr->pr_downloads;
        }
        if ( isset( $cgstate->state->output_iqpr->prwe_downloads ) ) {
            $obj->prwe_downloads = &$cgstate->state->output_iqpr->prwe_downloads;
        }

        if ( isset( $cgstate->state->computeiqpr_prerrors ) &&
             $cgstate->state->computeiqpr_prerrors ) {
            if ( isset( $cgstate->state->output_iqpr->prwe_plotsel ) ) {
                $obj->prwe_plotsel = &$cgstate->state->output_iqpr->prwe_plotsel;
            }
            if ( isset( $cgstate->state->output_iqpr->prwe_results ) ) {
                $obj->prwe_results = &$cgstate->state->output_iqpr->prwe_results;
            }
        } else {
            unset( $obj->prwe_plotsel );
        }
    } else {
        unset( $obj->pr_plotsel );
        unset( $obj->prwe_plotsel );
        foreach ( $mdatas as $mdata ) {
            unset( $obj->{ $mdata->tags->plotsel } );
        }
    }                      

    unset( $obj->iq_p_plotall );
    unset( $obj->iq_c3_plotall );
    unset( $obj->iqplotall );
    unset( $obj->prplotall );

    $obj->processing_progress = 0;

    $ga->tcpmessage( $obj );
};

question_prior_results( __FILE__, $restore_old_data );

## clear output
$ga->tcpmessage( [
                     'processing_progress' => 0.01
                     ,"progress_text"      => ''
                     ,"iq_p_results"       => ''
                     ,"iq_c3_results"      => ''
                     ,"pr_results"         => ''
                 ]);

### clear state data

## note - $output is fully reset, so only top-level state info needs to be cleared

if ( isset( $cg->state->pr_nnlsresults ) ) {
    unset( $cg->state->pr_nnlsresults );
}

if ( isset( $cg->state->prwe_nnlsresults ) ) {
    unset( $cg->state->prwe_nnlsresults );
}

foreach ( $mdatas as $mdata ) {
    if ( isset( $cg->state->{$mdata->tags->nnlsresults} ) ) {
        unset( $cg->state->{$mdata->tags->nnlsresults} );
    }
}

## initial plots

$plots = (object) [];

setup_computeiqpr_plots( $plots );

if ( isset( $input->prerrors ) ) {
    if ( !$sas->data_has_errors( 'Exp. P(r)' ) ) {
        error_exit( "<i>Use Errors in P(r) fitting</i> requested, but the Experimenal P(r) data has no defined SDs" );
    }
}
        
## plotlycomputeiqpr( $cgstate->state->output_loadsaxs, $plots );

## initial message 
$tmpout = (object)[];

$tmpout->iqplot       = $plots->iqplot;
$tmpout->prplot       = $plots->prplot;

## add other plots ?

$ga->tcpmessage( $tmpout );

## process inputs here to produce output

$bname     = preg_replace( '/-somo\.pdb$/', '', $cgstate->state->output_load->name );

# $ga->tcpmessage( [ '_textarea' => "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n" ] );

if ( !isset( $input->iqmethod ) ) {
    error_exit( "At least one <i>I(q) computation method</i> must be selected" );
}
if ( !is_array( $input->iqmethod ) ) {
    error_exit( "Internal error - iqmethod is not an array" );
}

# $ga->tcpmessage( [ "_message" => [ "text" =>  "This module is under development/modification" ] ] );

### check if crysol selected and if so, verify academic usage

if ( in_array( "crysol3", $input->iqmethod )
     || in_array( "crysol2", $input->iqmethod ) 
   ) {
    require_once "$input->_webroot/$input->_application/ajax/ga_db_lib.php";

    if ( !ga_db_status( ga_db_open( $error_json_exit ) ) ) {
        error_exit( "failed to connect to db" );
    }

    $userdoc = ga_db_output( ga_db_findOne( 'users', '', [ '_logon' => $input->logon ] ) );

    # $output->_textarea = json_encode( $userdoc, JSON_PRETTY_PRINT ) . "\n----\n";
    ## remove the 1 || below to only ask once
    if ( 1 || !isset( $userdoc->academicAgree ) ) {
        $response =
            json_decode(
                $ga->tcpquestion(
                    [
                     "id"           => "q1"
                     ,"title"       => "<h5>CRYSOL is for Academic Use Only</h5>"
                     ,"icon"        => "warning.png"
                     ,"text"        => ""
                     ,"timeouttext" => "The time to respond has expired, please submit again."
                     ,"buttons"     => [ "I hereby agree that this work is exclusively for academic usage and my email address may be shared with BIOSAXS GmbH", "No this work is NOT for academic usage" ]
                     ,"fields" => [
                         [
                          "id"          => "l1"
                          ,"type"       => "label"
                          ,"label"      => "By clicking <strong><i>I hearby agree...</i><strong> below, your registered email address may be shared with BIOSAXS GmbH"
                          ,"align"      => "center"
                         ]
                     ]
                    ]

                )
            );

        if ( $response->_response->button != "iherebyagreethatthisworkisexclusivelyforacademicusageandmyemailaddressmaybesharedwithbiosaxsgmbh" ) {
            $output->_message = [
                "text" => "Processing canceled by user request"
                ,'icon' => 'information.png'
                ];
            $output->processing_progress = 0;
            $output->_disable_notify = true;
            echo json_encode( $output );
            exit;
        }

        if ( isset( $response->error ) && strlen( $response->error ) ) {
            error_exit( "Please submit again" );
        }

        # update db

        if ( !isset( $userdoc->academicAgree ) ) {
            $userdoc->academicAgree = [];
        }

        $userdoc->academicAgree[] = [ "CRYSOL" => ga_db_output( ga_db_date() ) ];

        if ( !ga_db_status(
                  ga_db_update(
                      'users',
                      '',
                      [ "_id" => $userdoc->_id ]
                      ,[ '$set' => [
                             'academicAgree' => $userdoc->academicAgree
                         ] ]
                  )
             )
            ) {
            error_exit( "Error updating the database" );
        }
    }
}

### pdb collection

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

#### P(r) start #####

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

    $output->pr_header = "<hr><strong>P(r) results section</strong><hr>";

    $ga->tcpmessage(
        [
         "processing_progress" => 0 + .4 * ( ( $pos + .5 * $usecount ) / $count_pdbs )
         ,"pr_header" => $output->pr_header
         ,"pr_plotallhtml" => plot_to_image( $sas->plot( "P(r) all mmc" ) )
        ]
        );

    $prnamescomp   = [];
    $prnamesinterp = [];
    $prnamesnormed = [];
    $prnamestocomp = [];
    $prfiles       = [];
    $prfileexists  = [];
    $prpdbstocomp  = [];

    for ( $i = 0; $i < $usecount; ++$i ) {
        $prfiles[ $i ]   = "preselected/${bname}-m" . padded_model_no_from_pdb_name( $prpdbs[ $i ] ) . "-somo-pr.dat";
        $prnamesnormed[] = "P(r) mod. " . model_no_from_pdb_name( $prpdbs[ $i ] );
        $allprnames[]    = end( $prnamesnormed );
        $prnamescomp[]   = end( $prnamesnormed ) . " comp";
        $prnamesinterp[] = end( $prnamesnormed ) . " interp";
        $prpdbs[ $i ]    = "preselected/" . $prpdbs[ $i ];
        if ( file_exists( end( $prfiles ) ) ) {
            $prfileexists[ $i ] = true;
        } else {
            $prfileexists[ $i ] = false;
            $prnamestocomp[] = end( $prnamescomp );
            $prpdbstocomp[] =  $prpdbs[$i];
        }
    }

    if ( count( $prnamestocomp ) ) {
#        $ga->tcpmessage( [
#                             "_textarea" =>
#                             "compute_pr_many() setup\n"
#                             . json_encode( $prpdbstocomp, JSON_PRETTY_PRINT ) . "\n"
#                             . json_encode( $prnamestocomp, JSON_PRETTY_PRINT ) . "\n"
#                         ] );
                             
        $sas->compute_pr_many( $prpdbstocomp, $prnamestocomp );
    }

    for ( $i = 0; $i < $usecount; ++$i ) {
        if ( $prfileexists[ $i ] ) {
            $sas->load_file( SAS::PLOT_PR, $prnamesnormed[$i], $prfiles[$i] );
        } else {
            $sas->interpolate( $prnamescomp[$i], "Comp.", $prnamesinterp[$i] );
            $sas->norm_pr( $prnamesinterp[$i], floatval( $cgstate->state->output_load->mw ), $prnamesnormed[$i] );
            $sas->remove_data( $prnamescomp[$i] );
            $sas->remove_data( $prnamesinterp[$i] );
            $sas->save_file( $prnamesnormed[$i], $prfiles[ $i ] );
        }
        $sas->add_plot( "P(r) all mmc", $prnamesnormed[$i] );
    }
}

#$ga->tcpmessage(
#    [
#     "_textarea" => json_encode( $prfiles , JSON_PRETTY_PRINT ) . "\n"
#    ]
#    );

dt_store_now( "P(r) end" );

$output->pr_plotallhtml = plot_to_image( $sas->plot( "P(r) all mmc" ) );

$ga->tcpmessage(
    [
     "pr_plotallhtml" => $output->pr_plotallhtml
    ]
    );

## NNLS on P(r)

progress_text( "Running NNLS on P(r)" );

$prresults = [];

$sas->extend_pr( array_merge( [ "Exp. P(r)" ], $allprnames  ) );
$sas->nnls( "Exp. P(r)", $allprnames, "P(r) NNLS fit", $prresults, false );

### build up P(r) sel plot

$plotname = "P(r) sel";

$sas->add_plot( $plotname, "P(r) NNLS fit" );

foreach ( $prresults as $k => $v ) {
    $sas->add_plot( $plotname, $k );
}

### residuals
$rmsd_pr = -1;
$chi2_pr = -1;
$scale   = 0;

$sas->rmsd_residuals( "Exp. P(r)", "P(r) NNLS fit", "P(r) fit Resid.", $rmsd_pr );

## move NNLS fit to last curve, but before residuals
$sas->remove_plot_data( $plotname, "P(r) NNLS fit" );
$sas->recolor_plot( $plotname, [ 1 ] );
$sas->add_plot( $plotname, "P(r) NNLS fit" );
$sas->plot_trace_options( $plotname, "P(r) NNLS fit", [ 'linecolor_number' => 1 ] );

$sas->add_plot_residuals( $plotname, "P(r) fit Resid." );
$sas->plot_trace_options( $plotname, "P(r) fit Resid.", [ 'linecolor_number' => 1 ] );
$sas->plot_options( $plotname, [ "yaxis2title" => "Resid." ] );

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
    $sas->annotate_plot( $plotname, $annotate_msg );
}

$output->pr_results = nnls_results_to_html( $prresults );

### save results to state

### TODO join all results into a final set?

$cgstate->state->pr_nnlsresults = $prresults;

$csvsomoname = "${bname}_somo_pr.csv";
$csvcolname = "${bname}_pr.csv";

$sas->save_data_csv(
    array_merge( [ "Exp. P(r)", "P(r) NNLS fit" ], $allprnames )
    ,$csvsomoname
    ,$cgstate->state->output_load->mw
    ,'/P\(r\) /'
    ,"$bname "
    );

$sas->save_data_csv_tr(
    array_merge( [ "Exp. P(r)", "P(r) NNLS fit" ], $allprnames )
    ,$csvcolname
    ,$cgstate->state->output_load->mw
    ,'/P\(r\) /'
    ,"$bname "
    );

$output->pr_downloads =
    "<div>"
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>P(r) csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $csvcolname )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>P(r) SOMO style csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;<br>&nbsp;", $csvsomoname )
    ;

$ga->tcpmessage(
    [
     "processing_progress" => .9
     ,"pr_plotsel" => $sas->plot( $plotname )
     ,"pr_results" => nnls_results_to_html( $prresults )
     ,"pr_downloads" => $output->pr_downloads
#     ,"_textarea" => json_encode( $prresults, JSON_PRETTY_PRINT ) . "\n"
    ]
    );
    
$output->pr_plotsel = $sas->plot( $plotname );

# $ga->tcpmessage( [ "_textarea" => "P(r) time " . dhms_from_minutes( dt_store_duration( "P(r) start", "P(r) end" ) ) . "\n" ] );

##### NNLS on P(r) with errors (we) ####

if ( isset( $input->prerrors ) ) {

    progress_text( "Running NNLS on P(r) with SDs" );

    $prweresults = [];

    $sas->set_pr_error_y_nonzero( "Exp. P(r)" );

    $sas->nnls( "Exp. P(r)", $allprnames, "P(r) NNLS fit w/SDs", $prweresults, true );

    ### build up P(r)-we sel plot

    $plotname = "P(r) we sel";

    $sas->add_plot( $plotname, "P(r) NNLS fit w/SDs" );

    foreach ( $prweresults as $k => $v ) {
        $sas->add_plot( $plotname, $k );
    }

    ### residuals
    $rmsd_prwe = -1;
    $chi2_prwe = -1;
    $scale   = 0;

#    $sas->scale_nchi2( "Exp. P(r)", "P(r) NNLS fit w/SDs", "P(r) NNLS fit w/SDs-rescaled", $chi2_prwe, $scale );
    $sas->rmsd_residuals( "Exp. P(r)", "P(r) NNLS fit w/SDs", "P(r) fit w/SDs Resid.", $rmsd_prwe );
#    $sas->rmsd( "Exp. P(r)", "P(r) NNLS fit w/SDs", $rmsd_prwe );
#    $sas->calc_residuals( "Exp. P(r)", "P(r) NNLS fit w/SDs", "P(r) fit w/SDs Resid." );

    ## move NNLS fit to last curve, but before residuals
    $sas->remove_plot_data( $plotname, "P(r) NNLS fit w/SDs" );
    $sas->recolor_plot( $plotname, [ 1 ] );
    $sas->add_plot( $plotname, "P(r) NNLS fit w/SDs" );
    $sas->plot_trace_options( $plotname, "P(r) NNLS fit w/SDs", [ 'linecolor_number' => 1 ] );

    $sas->add_plot_residuals( $plotname, "P(r) fit w/SDs Resid." );
    $sas->plot_trace_options( $plotname, "P(r) fit w/SDs Resid.", [ 'linecolor_number' => 1 ] );
    $sas->plot_options( $plotname, [ "yaxis2title" => "Resid." ] );

    $rmsd_prwe = round( $rmsd_prwe, 3 );
    $chi2_prwe = round( $chi2_prwe, 3 );
    $annotate_msg = "";
    if ( $rmsd_prwe != -1 ) {
        $annotate_msg .= "RMSD $rmsd_prwe   ";
    }
    if ( $chi2_prwe != -1 ) {
        $annotate_msg .= "nChi^2 $chi2_prwe   ";
    }
    if ( strlen( $annotate_msg ) ) {
        $sas->annotate_plot( $plotname, $annotate_msg );
    }

    $output->prwe_results = nnls_results_to_html( $prweresults );
    

    ### save results to state

    $cgstate->state->prwe_nnlsresults = $prweresults;

    $sassomoprwename = $bname . "_somo_pr_w_sd.csv";
    $sascolprwename = $bname . "_pr_w_sd.csv";

    $sas->save_data_csv(
        array_merge( [ "Exp. P(r)", "P(r) NNLS fit w/SDs" ], $allprnames )
        ,$sassomoprwename
        ,$cgstate->state->output_load->mw
        ,'/P\(r\) /'
        ,"$bname "
        );

    $sas->save_data_csv_tr(
        array_merge( [ "Exp. P(r)", "P(r) NNLS fit w/SDs" ], $allprnames )
        ,$sascolprwename
        ,$cgstate->state->output_load->mw
        ,'/P\(r\) /'
        ,"$bname "
        );

    $output->prwe_downloads =
        "<div>"
        . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>P(r) w/SDs csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sascolprwename )
        . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>P(r) w/SDs SOMO style csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $sassomoprwename )
        ;

    $ga->tcpmessage(
        [
         "processing_progress" => .95
         ,"prwe_plotsel" => $sas->plot( $plotname )
         ,"prwe_results" => nnls_results_to_html( $prweresults )
         ,"prwe_downloads" => $output->prwe_downloads
     #     ,"_textarea" => json_encode( $prweresults, JSON_PRETTY_PRINT ) . "\n"
    ]
    );

    $output->prwe_plotsel = $sas->plot( $plotname );

} else {
    unset( $cgstate->state->nnlsprweresults );
    unset( $cgstate->state->prwe_nnlsresults );
    $output->prwe_results = "";
}

#### I(q) section #####

$textarea_key = "_textarea";

$crysol_cb = function( $line ) {
    global $ga;
    global $textarea_key;
#    $ga->tcpmessage( [
#                         $textarea_key => $line
#                     ] );
};

$alliqnames = [];

foreach ( $input->iqmethod as $iqmethod ) {

    if ( !isset( $mdatas->$iqmethod ) ) {
        error_exit( "Method '$iqmethod' not properly setup" );
    }
    $mdata = $mdatas->$iqmethod;
    
    dt_store_now( "I(q) start" );
    
    $pos           = 0;
        
    progress_text( "Computing I(q) (" . ( $pos + 1 ) . "-" . min( $pos + $update_iq_frequency, $count_pdbs ). " of $count_pdbs)" );
    
    $output->{$mdata->tags->header_id} ="<hr><strong>I(q) $mdata->title results section</strong><hr>";

    $ga->tcpmessage(
        [
         "processing_progress" => .4
         ,$mdata->tags->header_id => $output->{$mdata->tags->header_id}
        ]);

    $alliqnames[$iqmethod] = [];
    
    foreach ( $pdbs as $pdb ) {
        ++$pos;

        $iqnewfile = "preselected/" . preg_replace( '/\.pdb$/', $mdata->datext, $pdb );

        if ( !file_exists( $iqnewfile ) ) {
            switch( $iqmethod ) {
                case 'pepsi' : {
                    # with exp data file: $cmd    = "cd preselected && Pepsi-SAXS " . $cgstate->state->saxsiqfile . " -ms " . $cgstate->state->qmax . " -ns " . $cgstate->state->qpoints . " $pdb";
                    $cmd    = "cd preselected && Pepsi-SAXS"
                        . " -ms " . $cgstate->state->qmax * $max_q_multiplier
                        . " -ns " . $cgstate->state->qpoints
                        . " $pdb";
                    $cmdres = run_cmd( $cmd, false );
                    if ( $run_cmd_last_error_code ) {
                        error_exit( "Error computing I(q) : $cmdres" );
                    }
                    $iqfile  = "preselected/" . preg_replace( '/\.pdb$/', '.out', $pdb );
                }
                break;

                case 'crysol3' : {
                    run_crysol( "preselected/$pdb"
                                ,(object)[
                                    'qpoints' => $cgstate->state->qpoints
                                    ,'maxq' => $cgstate->state->qmax * $max_q_multiplier
                                    ,'solvent_e_density' => $cgstate->state->solvent_e_density
                                    ,'subdir' => 'preselected'
                                ]
                                ,$crysol_cb
                        );
                    $iqfile  = "preselected/" . preg_replace( '/\.pdb$/', '.int', $pdb );
                }            
                break;

                case 'crysol2' : {
                    run_crysol2( "preselected/$pdb"
                                 ,(object)[
                                     'qpoints' => $cgstate->state->qpoints
                                     ,'maxq' => $cgstate->state->qmax * $max_q_multiplier
                                     ,'solvent_e_density' => $cgstate->state->solvent_e_density
                                     ,'subdir' => 'preselected'
                                 ]
                                 ,$crysol_cb
                        );
                    $iqfile  = "preselected/" . preg_replace( '/\.pdb$/', '.int', $pdb );
                }            
                break;
            }                            
            
            if ( !file_exists( $iqfile ) ) {
                error_exit( "Expected Iq file missing : $iqfile" );
            }

            if ( !rename( $iqfile, $iqnewfile ) ) {
                error_exit( "Error moving $iqfile to $iqnewfile" );
            }
        }

        $iqfile = $iqnewfile;

        $chi2  = -1;
        $rmsd  = -1;
        $scale = 0;
        
        $iqdataname   = "$mdata->prefix mod. " . model_no_from_pdb_name( $pdb );
        $alliqnames[$iqmethod][] = $iqdataname;
        
        $sas->load_file( SAS::PLOT_IQ, "$iqdataname orig", $iqfile, false );
        $sas->interpolate( "$iqdataname orig", "Exp. I(q)", "$iqdataname interp" );
        $sas->scale_nchi2( "Exp. I(q)", "$iqdataname interp", $iqdataname, $chi2, $scale );
        $sas->add_plot( $mdata->plotallname, $iqdataname );
        
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
                 ,$mdata->tags->plotallhtml => plot_to_image( $sas->plot( $mdata->plotallname ) )
                ]
                );

            progress_text( "Computing I(q) (" . ( $pos + 1 ) . "-" . min( $pos + $update_iq_frequency, $count_pdbs ). " of $count_pdbs)" );
        }
    }

    dt_store_now( "I(q) end" );

    # $ga->tcpmessage( [ "_textarea" => "I(q) time " . dhms_from_minutes( dt_store_duration( "I(q) start", "I(q) end" ) ) . "\n" ] );

    $output->{$mdata->tags->plotallhtml} = plot_to_image( $sas->plot( $mdata->plotallname ) );

    $ga->tcpmessage(
        [
         $mdata->tags->plotallhtml => $output->{$mdata->tags->plotallhtml}
         ,"processing_progress" => .8
         #     ,"_textarea" => json_encode( $sas->data_names(), JSON_PRETTY_PRINT ) . "\n"
        ]
        );

    ## NNLS on I(q)

    progress_text( "Running NNLS on I(q) $mdata->title" );

    $iqresults = [];

    $sas->nnls( "Exp. I(q)", $alliqnames[$iqmethod], "$mdata->prefix NNLS fit", $iqresults );

    $sas->add_plot( $mdata->plotselname, "$mdata->prefix NNLS fit" );

    foreach ( $iqresults as $k => $v ) {
        $sas->add_plot( $mdata->plotselname, $k );
    }

    ### residuals
    $chi2  = -1;
    $rmsd  = -1;
    $scale = 0;

    $sas->scale_nchi2( "Exp. I(q)", "$mdata->prefix NNLS fit", "$mdata->prefix NNLS fit-rescaled", $chi2, $scale );
    $sas->rmsd( "Exp. I(q)", "$mdata->prefix NNLS fit", $rmsd );
    $sas->calc_residuals( "Exp. I(q)", "$mdata->prefix NNLS fit", "$mdata->prefix fit Res./SD" );

    ## move NNLS fit to last curve, but before residuals
    $sas->remove_plot_data( $mdata->plotselname, "$mdata->prefix NNLS fit" );
    $sas->recolor_plot( $mdata->plotselname, [ 1 ] );
    $sas->add_plot( $mdata->plotselname, "$mdata->prefix NNLS fit" );
    $sas->plot_trace_options( $mdata->plotselname, "$mdata->prefix NNLS fit", [ 'linecolor_number' => 1 ] );
    
    $sas->add_plot_residuals( $mdata->plotselname, "$mdata->prefix fit Res./SD" );
    $sas->plot_trace_options( $mdata->plotselname, "$mdata->prefix fit Res./SD", [ 'linecolor_number' => 1 ] );

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
        $sas->annotate_plot( $mdata->plotselname, $annotate_msg );
    }

    $csvsomoname = "${bname}_somo_{$mdata->csvnamesuffix}.csv";
    $csvcolname = "${bname}_{$mdata->csvnamesuffix}.csv";

    $sas->save_data_csv(
        array_merge( [ "Exp. I(q)", "$mdata->prefix NNLS fit" ], $alliqnames[ $iqmethod ] )
        ,$csvsomoname
        ,$cgstate->state->output_load->mw
        ,'/I\(q\)/'
        ," $bname "
        );

    $sas->save_data_csv_tr(
        array_merge( [ "Exp. I(q)", "$mdata->prefix NNLS fit" ], $alliqnames[ $iqmethod ] )
        ,$csvcolname
        ,$cgstate->state->output_load->mw
        ,'/I\(q\)/'
        ," $bname "
        );

    $output->{$mdata->tags->downloads} =
        "<div>"
        . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>$mdata->prefix csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;", $csvcolname )
        . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>$mdata->prefix SOMO style csv &#x21D3;</a>&nbsp;&nbsp;&nbsp;<br>&nbsp;", $csvsomoname )
        ;

    $output->{$mdata->tags->results} = nnls_results_to_html( $iqresults );

    $ga->tcpmessage(
        [
         $mdata->tags->plotsel => $sas->plot( $mdata->plotselname )
         ,$mdata->tags->results => $output->{$mdata->tags->results}
         ,$mdata->tags->downloads => $output->{$mdata->tags->downloads}
         
         #     ,"_textarea" => json_encode( $iqresults, JSON_PRETTY_PRINT ) . "\n"
         #     ,"_textarea" => json_encode( $sas->data_names(), JSON_PRETTY_PRINT ) . "\n"
         #     . json_encode( $sas->plot_names(), JSON_PRETTY_PRINT ) . "\n"
         #     . $sas->data_summary( $sas->data_names() )
        ]
        );

    $output->{$mdata->tags->plotsel} = $sas->plot( $mdata->plotselname );

    ### summary results


    ### save results to state

    $cgstate->state->{$mdata->tags->nnlsresults} = $iqresults;
}

## save state

$cgstate->state->output_iqpr  = $output;
$cgstate->state->computeiqpr_prerrors  = isset( $input->prerrors ) ? true : false;

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->_textarea = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->_textarea .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

$output->processing_progress = 0;
$output->progress_text = progress_text( 'Processing complete', '', true );

echo json_encode( $output );
