#!/usr/local/bin/php
<?php

{};

### user configuration

$logfile           = "structcalcs.log";
$alphafold_dataset = "public-datasets-deepmind-alphafold-v4";
$MAX_RESULTS       = 25;
$MAX_RESULTS_SHOWN = 10;

###  number seconds between checking to see if the command process is still running
$poll_interval_seconds = 2;

####  frequency of actual UI updates, multiply this by the $poll_interval_seconds to determine actual user update time
$poll_update_freq      = 1;

### end user configuration

$textarea_key = '_textarea';

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
require "waxsis.php";

$cgstate = new cgrun_state();

## make sure project is loaded

if ( !isset( $cgstate->state->loaded ) ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project" );
}

if ( !isset( $cgstate->state->saxsiqfile )
     || !isset( $cgstate->state->saxsprfile ) ) {
    error_exit( "Please <i>'Load SAXS'</i> first" );
}    

## does the project already exist ?

## clear output
$ga->tcpmessage( [
                     'processing_progress' => 0.01
                     ,"progress_text"      => ''
                     ,"name"               => ''
                     ,"title"              => ''
                     ,"warnings"           => ''
                     ,"afmeanconf"         => ''
                     ,"source"             => ''
                     ,"somodate"           => ''
                     ,"mw"                 => ''
                     ,"psv"                => ''
                     ,"hyd"                => ''
                     ,"Rg"                 => ''
#                     ,"ExtX"               => ''
#                     ,"ExtY"               => ''
#                     ,"ExtZ"               => ''
                     ,"sheet"              => ''
                     ,"helix"              => ''
                     ,"downloads"          => ''
                 ] );


require_once "remove.php";

$restore_old_data = function() {
    global $cgstate;
    global $ga;
    global $input;

    $obj = (object)[];

    $obj->desc  = $cgstate->state->description;
    $obj->pname = $input->_project;

    if ( isset( $cgstate->state->output_load ) ) {
        $obj = $cgstate->state->output_load;
    }
    
    $obj->processing_progress = 0;

    $ga->tcpmessage( $obj );
};

question_prior_results( __FILE__, $restore_old_data );


## process inputs here to produce output

## alphafold, somoaf or pdb/somoaf ?

if ( !isset( $input->searchkey )
     && !isset( $input->searchkeysomoaf )
    ) {
    ## pdb/cif loaded
    if ( !isset( $input->pdbfile[0] ) ) {
        $ga->tcpmessage( [ 'processing_progress' => $progress * 0.3 ] );
        error_exit_admin( "Internal error: No input PDB nor mmCIF file provided" );
    }
    $input->pdbfile[0] =  clean_up_filename_and_copy_if_needed( $input->pdbfile[0] );
    $fpdb = preg_replace( '/.*\//', '', $input->pdbfile[0] );
    $is_alphafold = preg_match( '/^AF-/', $fpdb );
} elseif ( isset( $input->searchkey ) ) {
    ## alphafold download
    $ga->tcpmessage( [ 'processing_progress' => 0 ] );
    $searchkey = strtoupper( "AF-" . preg_replace( '/^AF-/i', '', $input->searchkey ) );

    ## get list of files possible, make $ids[]

    $cmd = "(gsutil ls gs://public-datasets-deepmind-alphafold-v4/${searchkey}*.cif | head -$MAX_RESULTS) 2> /dev/null";
    $res = run_cmd( $cmd, true, true );
    $ids = preg_replace( '/-model.*cif$/', '', preg_replace( '/^.*\/AF-/', '', preg_grep( '/\.cif$/', $res ) ) );
    
    if ( count( $ids ) > 1 ) {

        if ( count( $ids ) == $MAX_RESULTS ) {
            $multiple_msg = "<i>Note - results are limited to $MAX_RESULTS, more matches may exist.<br>Use a longer search string to refine the results.</i><br>";
        } else {
            $multiple_msg = '';
        }

        $response =
            json_decode(
                $ga->tcpquestion(
                    [
                     "id" => "q1"
                     ,"title" => "Multiple search results found"
                     ,"icon"  => "noicon.png"
                     ,"text" =>
                     $multiple_msg
                     . "<hr>"
                     ,"grid" => 3
                     ,"timeouttext" => "The time to respond has expired, please search again."
                     ,"fields" => [
                         [
                          "id" => "lb1"
                          ,"type"       => "listbox"
                          ,"fontfamily" => "monospace"
                          ,"values"     => $ids
                          ,"returns"    => $ids
                          ,"required"   => "true"
                          ,"size"       => count( $ids ) > $MAX_RESULTS_SHOWN ? $MAX_RESULTS_SHOWN : count( $ids )
                          ,"grid"       => [
                              "data"    => [1,3]
                          ]
                         ]
                     ]
                    ]
                )
            );


        if ( isset( $response->error ) && strlen( $response->error ) ) {
            error_exit( "Please submit again", true, $restore_old_data );
        }

        if (
            isset( $response->_response )
            && isset( $response->_response->button )
            && $response->_response->button == "ok"
            && isset( $response->_response->lb1 )
            && strlen( $response->_response->lb1 )
            ) {
            $searchkey = $response->_response->lb1;
        } else {
            $output->_null = "";
            json_exit();
        }
    } else {
        ## one entry, set the searchkey to the full _id
        $searchkey = $ids[ 0 ];
    }

    ## get files themselves

    $cmd = "gsutil -m cp gs://${alphafold_dataset}/AF-${searchkey}-* .";

    $res = run_cmd( $cmd, false, true );

    ## check for files

    # $ga->tcpmessage( [ $textarea_key => "$cmd\n" ] );

    $extensions = [ "model_v4.cif", "confidence_v4.json" ];
    foreach ( $extensions as $v ) {
        $thisf = "AF-${searchkey}-$v";
        if ( !file_exists( $thisf  ) ) {
            $ga->tcpmessage( [ 'processing_progress' => $progress * 0.3 ] );
            error_exit_admin( "Error downloading $thisf from Google cloud, perhaps try again later" );
        }
    }

    ## process

    $is_alphafold = true;
    $fpdb = "AF-${searchkey}-model_v4.cif";
} elseif ( isset( $input->searchkeysomoaf ) ) {
    ## somoaf
    ### open db
    require '/var/www/html/saxsafold/vendor/autoload.php'; ## include Composer's autoloader

    try {
        $db_mongo = new MongoDB\Client();
    } catch ( Exception $e ) {
        $output->errors = "Error connecting to db " . $e->getMessage();
        json_exit();
    }

    $searchkey = preg_replace( '/^AF-/i', '', $input->searchkeysomoaf );


    ### find in db
    try {
        $query = [ '_id' => new \MongoDB\BSON\Regex( '^' . $searchkey, 'i' ) ];

        # $output->_textarea = "query:\n" . json_encode( $query, JSON_PRETTY_PRINT ) . "\n";

        $foundcursor = $db_mongo->somo->afd->find(
            $query
            ,[
                'limit' => $MAX_RESULTS
                ,'projection' => [
                    '_id' => 1
                ]
            ]
            );
    } catch ( MongoDB\Exception\UnsupportedException $e ) {
        $restore_old_data();
        $output->errors = "Error finding " .  $e->getMessage();
        json_exit();
    } catch ( MongoDB\Exception\InvalidArgumentException $e ) {
        $restore_old_data();
        $output->errors = "Error finding " .  $e->getMessage();
        json_exit();
    } catch ( MongoDB\Exception\RuntimeException $e ) {
        $restore_old_data();
        $output->errors = "Error finding " .  $e->getMessage();
        json_exit();
    }

    $ids = [];
    foreach( $foundcursor as $doc ) {
        $ids[] = $doc->_id;
    }

    if ( count( $ids ) == 0 ) {
        $restore_old_data();
        $output->_message =
            [
             'text'  => $input->searchkey . ' did not match any records'
             ,'icon' => 'information.png'
            ];
        json_exit();
    }

    # $output->_textarea .= "output ids:\n" . json_encode( $ids, JSON_PRETTY_PRINT ) . "\n";

    if ( count( $ids ) > 1 ) {

        if ( count( $ids ) == $MAX_RESULTS ) {
            $multiple_msg = "<i>Note - results are limited to $MAX_RESULTS, more matches may exist.<br>Use a longer search string to refine the results.</i><br>";
        } else {
            $multiple_msg = "";
        }

        $response =
            json_decode(
                $ga->tcpquestion(
                    [
                     "id" => "q1"
                     ,"title" => "Multiple search results found"
                     ,"icon"  => "noicon.png"
                     ,"text" =>
                     $multiple_msg
                     . "<hr>"
                     ,"grid" => 3
                     ,"timeouttext" => "The time to respond has expired, please search again."
                     ,"fields" => [
                         [
                          "id" => "lb1"
                          ,"type"       => "listbox"
                          ,"fontfamily" => "monospace"
                          ,"values"     => $ids
                          ,"returns"    => $ids
                          ,"required"   => "true"
                          ,"size"       => count( $ids ) > $MAX_RESULTS_SHOWN ? $MAX_RESULTS_SHOWN : count( $ids )
                          ,"grid"       => [
                              "data"    => [1,3]
                          ]
                         ]
                     ]
                    ]
                )
            );

        if ( isset( $response->error ) && strlen( $response->error ) ) {
            $restore_old_data();
            error_exit( "Please submit again" );
        }

        if (
            isset( $response->_response )
            && isset( $response->_response->button )
            && $response->_response->button == "ok"
            && isset( $response->_response->lb1 )
            && strlen( $response->_response->lb1 )
            ) {
            $output->searchkey = $response->_response->lb1;
        } else {
            $restore_old_data();
            $output->_null = "";
            json_exit();
        }
    } else {
        ## one entry, set the searchkey to the full _id
        $output->searchkey = $ids[ 0 ];
    }

    try {
        $found = $db_mongo->somo->afd->findOne( [ "_id" => $output->searchkey ] );
    } catch ( MongoDB\Exception\UnsupportedException $e ) {
        $restore_old_data();
        $output->errors = "Error finding " .  $e->getMessage();
        json_exit();
    } catch ( MongoDB\Exception\InvalidArgumentException $e ) {
        $restore_old_data();
        $output->errors = "Error finding " .  $e->getMessage();
        json_exit();
    } catch ( MongoDB\Exception\RuntimeException $e ) {
        $restore_old_data();
        $output->errors = "Error finding " .  $e->getMessage();
        json_exit();
    }

    # $ga->tcpmessage( [ "_textarea" => json_encode( $found, JSON_PRETTY_PRINT ) . "\n" ] );

    $basename   = $found->name;
    $somoaffile = "/host/somoaf/pdb/$basename-somo.pdb";
    
    if ( !file_exists( $somoaffile ) ) {
        error_exit( "The post translationally modified structure was not found", true, $restore_old_data );
    }

    run_cmd( "cp $somoaffile ./$basename.pdb" );

    $fpdb = "$basename.pdb";
    $is_alphafold = true;
}

## are we ok to run / any pre-run checks

## clear out ultrascan/results

## clear out somo results

run_cmd( "rm ultrascan/results/* > /dev/null 2> /dev/null", false );

## create the command(s)

#$ga->tcpmessage( [ $textarea_key => "base_dir is '$base_dir'\n" ] );
#$ga->tcpmessage( [ $textarea_key => "fpdb is $fpdb\n" ] );
#$ga->tcpmessage( [ $textarea_key => "scriptdir is $scriptdir\n" ] );
$cmd = "$scriptdir/calcs/structcalcs.pl $fpdb 2>&1 > $logfile";
#$ga->tcpmessage( [ $textarea_key => "command is $cmd\n" ] );

## ready to run, fork & execute cmd in child

progress_text( 'Running structural calculations...' );

## fork ... child will exec

$pid = pcntl_fork();
if ( $pid == -1 ) {
    echo '{"_message":{"icon":"toast.png","text":"Unable to fork process.<br>This should not happen.<br>Please contact the administrators via the <i>Feedback</i> tab"}}';
    exit;
}

## prepare to run

$errors = false;

if ( $pid ) {
    ## parent
    init_ui();
    $updatenumber = 0;
    while ( !$jobdone && file_exists( "/proc/$pid/stat" ) ) {
        ## is Z/defunct ?
        $stat = file_get_contents( "/proc/$pid/stat" );
        $stat_fields = explode( ' ', $stat );
        if ( count( $stat_fields ) > 2 && $stat_fields[2] == "Z" ) {
            break;
        }
        ## still running
        if ( !( $updatenumber++ % $poll_update_freq ) ) {
            ## update UI
            #$ga->tcpmessage( [ $textarea_key => "update the UI $updatenumber - $pid - $jobdone\n" ] );
            update_ui();
        } else {
            ## simply checking for job completion
            #$ga->tcpmessage( [ $textarea_key => "polling update $updatenumber - $pid - $jobdone\n" ] );
        }
        sleep( $poll_interval_seconds );
    } 
    # $ga->tcpmessage( [ $textarea_key => "proc/$pid/stat gone\n" ] );
    ## get exit status from /proc/$pid
    # doesn't work pcntl_waitpid( $pid, $status, WNOHANG );
    pcntl_waitpid( $pid, $status );
    # $ga->tcpmessage( [ $textarea_key => "wait_pid returned\n" ] );
    update_ui();
} else {
    ## child
    ob_start();
    $ga->tcpmessage( [ $textarea_key => "\nComputations starting on $fpdb\n" ] );
    ##    $ga->tcpmessage( [ "stdoutlink" => "$fdir/charmm-gui/namd/$ofile.stdout" ] );

    $time_start = dt_now();
    shell_exec( $cmd );
    $time_end   = dt_now();
    $ga->tcpmessage( [ $textarea_key =>
                       "\nStructural Computations ending\n"
                       . "Duration: " . dhms_from_minutes( dt_duration_minutes( $time_start, $time_end ) ) . "\n"
                     ] );
    ob_end_clean();
    exit();
}

if ( isset( $errorlines ) && !empty( $errorlines ) ) {
    $ga->tcpmessage( [
                         $textarea_key => "\n\n==========================\nERRORS encountered\n==========================\n$errorlines\n"
                     ] );

    error_exit_admin( $errorlines );
}


## assemble final output

$logresults = explode( "\n", `grep -P '^__:' $logfile` );
$logresults = preg_replace( '/^__: /', '', $logresults );

foreach ( $logresults as $v ) {
    $fields = explode( " : ", $v );
    if ( count( $fields ) > 1 &&
         preg_match( '/^(psv|title||mw|source|title|source|sheet|helix|Rg|somodate|hyd|name|afmeanconf)$/', $fields[0] ) ) {
        $output->{$fields[0]} = $fields[1];
    }
}

### map outputs

#$output->title      = str_replace( 'PREDICTION FOR ', "PREDICTION FOR\n", $found->title );
#$output->source     = str_replace( '; ', "\n", $found->source );
# $output->sp         = $found->sp ? $found->sp : "n/a";
# $output->proc       = $found->proc;
#if ( !$found->proc ) {
#    $output->proc = $found->sp ? "Signal peptide $found->sp removed" : "none";
#}

$output->mw         = sprintf( "%.1f", $output->mw );
## --> $output->hyd        = $found->hyd;
$output->Rg         = digitfix( sprintf( "%.3g", $output->Rg ), 3 );
#$output->ExtX       = sprintf( "%.2f", $output->ExtX );
#$output->ExtY       = sprintf( "%.2f", $output->ExtY );
#$output->ExtZ       = sprintf( "%.2f", $output->ExtZ );
$output->helix      = sprintf( "%.1f", $output->helix );
$output->sheet      = sprintf( "%.1f", $output->sheet );
unset( $output->Eta_sd );

$base_name = preg_replace( '/-somo\.(cif|pdb)$/i', '', $output->name );

$output->downloads  = "<div style='margin-top:0.5rem;margin-bottom:0rem;'>";

$output->downloads .=
    sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-somo.pdb>PDB &#x21D3;</a>&nbsp;&nbsp;&nbsp;",           $base_name )
    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-somo.cif>mmCIF &#x21D3;</a>&nbsp;&nbsp;&nbsp;",         $base_name )
    ;

if ( isset( $cgstate->state->saxsiqfile )
     && isset( $cgstate->state->saxsprfile )
    ) {
    $output->downloads .=
        sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>Iq &#x21D3;</a>&nbsp;&nbsp;&nbsp;", preg_replace( '/^.*\//', '', $cgstate->state->saxsiqfile ) )
        . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/%s>Pr &#x21D3;</a>&nbsp;&nbsp;&nbsp;", preg_replace( '/^.*\//', '', $cgstate->state->saxsprfile ) )
        ;
}

$output->downloads .= "</div>";

#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-pr.dat>P(r) &#x21D3;</a>&nbsp;&nbsp;&nbsp;",            $base_name )
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-sesca-cd.dat>CD &#x21D3;</a>&nbsp;&nbsp;&nbsp;",        $base_name )
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s.csv>CSV &#x21D3;</a>&nbsp;&nbsp;&nbsp;",                $base_name )
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-process-log.txt>Log &#x21D3;</a>&nbsp;&nbsp;&nbsp;",    $base_name )
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-somo.zip>All zip'd &#x21D3;</a>&nbsp;&nbsp;&nbsp;",     $base_name )
#    . sprintf( "<a target=_blank href=results/users/$logon/$base_dir/ultrascan/results/%s-somo.txz>All txz'd &#x21D3;</a>&nbsp;&nbsp;&nbsp;",     $base_name )
#    . "</div>"
#    ;

## pdb
if ( file_exists( sprintf( "ultrascan/results/%s-tfc-somo.pdb", $base_name ) ) ) {
    $output->struct = [
        "file" => sprintf( "results/users/$logon/$base_dir/ultrascan/results/%s-tfc-somo.pdb", $base_name )
        ,"script" => "ribbon only; color temperature"
        ];
    $output->confidencelegend = confidence_legend();
} else {
    $output->struct = [
        "file" => sprintf( "results/users/$logon/$base_dir/ultrascan/results/%s-somo.pdb", $base_name )
        ,"script" => "ribbon only; color structure"
        ];
}                

if ( isset( $cgstate->state->output_loadsaxs->iqplot ) ) {
    $output->iqplot = $cgstate->state->output_loadsaxs->iqplot;
}
# if ( isset( $cgstate->state->output_loadsaxs->prplot ) ) {
#     $output->prplot = $cgstate->state->output_loadsaxs->prplot;
# }

## run P(r) rcomputation

$pdb_pr = "$base_name-somo.pdb";
$rmsd_pr = -1;

if (
    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)-tmp", $cgstate->state->output_loadsaxs->prplot )
    && $sas->compute_pr( $pdb_pr, "Comp. P(r)" )
    && $sas->interpolate( "Exp. P(r)", "Comp. P(r)", "Exp. P(r)-interp" )
    && $sas->remove_data( "Exp. P(r)" )
    && $sas->norm_pr( "Exp. P(r)-interp", $output->mw, "Exp. P(r)" )
    && $sas->norm_pr( "Comp. P(r)", $output->mw, "Comp." )
    && $sas->rmsd_residuals( "Exp. P(r)", "Comp.", "Resid.", $rmsd_pr )
    && $sas->create_plot( SAS::PLOT_PR
                          ,"P(r)"
                          ,[
                              "Exp. P(r)"
                              ,"Comp."
                          ]
    )
    && $sas->add_plot_residuals( "P(r)", "Resid." )
    && $sas->plot_trace_options( "P(r)", "Resid.", [ 'linecolor_number' => 1 ] )
    && $sas->plot_options( "P(r)", [ 'yaxistitle' => 'Norm. Freq. [Da]' ] )
    ) {
} else {
    error_exit( $sas->last_error );
}

$rmsd_pr = round( $rmsd_pr, 3 );
$annotate_msg = "";
if ( $rmsd_pr != -1 ) {
    $annotate_msg .= "RMSD $rmsd_pr   ";
}

if ( strlen( $annotate_msg ) ) {
    $sas->annotate_plot( "P(r)", $annotate_msg );
}

$output->prplot = $sas->plot( "P(r)" );

$ga->tcpmessage( $output );
progress_text( 'Structural computations complete (see results below). Running WAXSiS calculations.<br>Please be patient as WAXSiS calculations can take some time to complete ...' );

## WAXSiS run

$ga->tcpmessage( [
                     $textarea_key =>
                     "WAXSiS computations startings\n"
                     . "solvent_e_density $input->solvent_e_density\n"
                     ,'processing_progress' => 0.3
                 ] );

$waxsis_lc = 0;

$waxsis_cb = function( $line ) {
    global $ga;
    global $textarea_key;
    global $waxsis_lc;

    $waxsis_lc++;

    $ga->tcpmessage( [
                         $textarea_key => $line
                         ,'processing_progress' => min(0.3 + 0.6 * ( $waxsis_lc / 118 ), .95 )
                     ] );
};
    
$waxsis_params = 
    (object)[
        'qpoints'             => $cgstate->state->qpoints + 10 # 10 to compensate for low-q region
        ,'maxq'               => $cgstate->state->qmax * $max_q_multiplier  # extend to prevent extrapolation when interpolating
        ,'convergence'        => $waxsis_convergence_mode
        ,'expfile'            => $cgstate->state->saxsiqfile
        ,'solvent_e_density'  => (float) $input->solvent_e_density
    ];

$waxsis_cb( json_encode( $waxsis_params, JSON_PRETTY_PRINT ) . "\n" );

## run_waxsis currently should error out directly, nothing to catch here, could change this if we wanted

## for testing expediency, optionally run WAXSiS
if ( 1 ) {
   
    $time_start = dt_now();
    run_waxsis(
        $output->name
        ,$waxsis_params
        ,$waxsis_cb
        );
    $time_end   = dt_now();
    $cgstate->state->waxsis_last_run_time_minutes = dt_duration_minutes( $time_start, $time_end );
}

progress_text( 'Assembling final results ...' );

#$ga->tcpmessage( [
#                     $textarea_key => "WAXSiS Fitted curve data:\n" . json_encode( $waxsis_fitted_data, JSON_PRETTY_PRINT ) . "\n"
#                 ] );

## output files?

### waxsis/fittedCalcInterpolated_waxsis.fit

## setup Iq/Pr plots

# $waxsisfile = "waxsis/fittedCalcInterpolated_waxsis.fit";
$waxsisfile = "waxsis/intensity_waxsis.calc";

if ( !file_exists( $waxsisfile ) ) {
    error_exit( "WAXSiS output file '$waxsisfile' does not exist" );
}

$chi2  = -1;
$rmsd  = -1;
$scale = 0;

if (
    $sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q)", $cgstate->state->output_loadsaxs->iqplot )
    && $sas->load_file( SAS::PLOT_IQ, "WAXSiS_org", $waxsisfile  )
    && $sas->interpolate( "WAXSiS_org", "Exp. I(q)", "WAXSiS_interp" )
    && $sas->scale_nchi2( "Exp. I(q)", "WAXSiS_interp", "WAXSiS", $chi2, $scale )
    && $sas->rmsd( "Exp. I(q)", "WAXSiS", $rmsd )
    && $sas->add_plot( "I(q)", "WAXSiS" )
    && $sas->calc_residuals( "Exp. I(q)", "WAXSiS", "Res./SD" )
    && $sas->add_plot_residuals( "I(q)", "Res./SD" )
    && $sas->plot_trace_options( "I(q)", "Res./SD", [ 'linecolor_number' => 1 ] )
    ) {
    $output->iqplot = $sas->plot( "I(q)" );
} else {
    error_exit( $sas->last_error );
}

# $ga->tcpmessage( [ $textarea_key => $sas->dump() ] );

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
    $sas->annotate_plot( "I(q)", $annotate_msg );
}

# $output->prplot = $cgstate->state->output_loadsaxs->prplot;

$output->warnings = $warningsent ? '<div style="color:red"><b>Warnings, check the progress window</b></div>' : "No warnings"; 


## log results to textarea

# $output->{$textarea_key} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{$textarea_key} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

## not being used for load structure for now

## save state

$cgstate->state->loaded            = true;
$cgstate->state->output_load       = $output;
$cgstate->state->is_alphafold      = $is_alphafold;
$cgstate->state->solvent_e_density = floatval( $input->solvent_e_density );

if ( isset( $ga->cache_obj->_textarea ) ) {
    $cgstate->state->output_load->_textarea = $ga->cache_obj->_textarea;
}

if ( isset( $input->refpdb ) ) {
    $cgstate->state->refpdb = $input->refpdb;
} else {
    unset( $cgstate->state->refpdb );
}

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

$output->processing_progress = 0;
$output->progress_text = progress_text( 'Processing complete', '', true );

echo json_encode( $output );

function init_ui() {
    global $ga;
    global $ofile;
    global $linesshown;
    global $warningsent;
    global $jobdone;

    $linesshown   = (object)[];
    $warningsent  = false;
    $jobdone      = 0;
}

function update_ui( $message = true ) {
    global $ga;
    global $ofile;
    global $logfile;
    global $warningsent;
    global $jobdone;
    global $textarea_key;

    global $linesshown;
    global $errorlines;

    ## collect available results and append to ui

    $log = explode( "\n", `grep -P '^__' $logfile` );
    $progresslines = preg_grep( '/^__~pgrs al : /', $log );
    if ( count( $progresslines ) ) {
        $progress = floatVal( preg_replace( '/^__~pgrs al : /', '', end( $progresslines ) ) );
        $ga->tcpmessage( [ 'processing_progress' => $progress * 0.3 ] );
    }
    $is_done = preg_grep( '/^__~finished/', $log );
        
    $textlines  = preg_grep( '/^__\+/', $log );
    $errorlines = implode( "<br>", preg_replace( '/^__E : /', '', preg_grep( '/^__E : /', $log ) ) );

    $textout = [];
    
    if ( count( $textlines ) ) {
        foreach ( $textlines as $v ) {
            preg_match( '/^__\+([^:]+) : (.*)$/', $v, $matches );
            if ( count( $matches ) > 2 ) {
                if ( !isset( $linesshown->{$matches[1]} ) ) {
                    $textout[] = $matches[2];
                    if ( !$warningsent &&
                         preg_match( '/Encountered the following warnings/', $matches[2] ) ) {
                        $warningsent = true;
                        $ga->tcpmessagebox(
                            [
                             "icon" => "warning.png"
                             , "text" => "Warnings were generated.<br>See the Progress window or the Downloads <i>Log</i> for details"
                            ]
                            );
                    }
                    $linesshown->{$matches[1]} = true;
                }
            }
        }
    }

    if ( count( $textout ) ) {
        $ga->tcpmessage( [
                             $textarea_key => implode( "\n", $textout ) . "\n"
                         ] );
    }

    if ( count( $is_done ) ) {
        $jobdone = 1;
        #$ga->tcpmessage( [
        #$textarea_key => "is_done --> jobdone $jobdone\n"
        #] );
    }
}

function digitfix( $strval, $digits ) {
    $strnodp = str_replace( ".", "", $strval );
    if ( strlen($strnodp) >= $digits ) {
        return $strval;
    }
    if ( strpos( $strval, "." ) ) {
        return $strval . "0";
    }
    return $strval . ".0";
}
