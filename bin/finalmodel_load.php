#!/usr/local/bin/php
<?php
{};

$request = json_decode( file_get_contents( "php://stdin" ) );
$result  = (object)[];

function error_exit_hook( $msg ) {
    global $result;
    $result->error = $msg;
    echo json_encode( $result );
    exit;
}

if ( $request === NULL ) {
    error_exit_hook( "Invalid JSON input provided" );
}

if ( !isset( $request->_project ) || !strlen( $request->_project ) ) {
    error_exit_hook( "A project must be selected!" );
}

if ( !file_exists( "state.json" ) ) {
    error_exit_hook( "Project $request->_project has not been defined, Please <i>'Define project'</i> first" );
}    

$scriptdir = dirname( __FILE__ );
require "$scriptdir/common.php";
$cgstate = new cgrun_state();

if ( !isset( $cgstate->state->output_load ) ) {
    error_exit_hook( "Project $request->_project has been defined, but apparently not been loaded, Please <i>'Load structure'</i> first" );
}    

if ( !isset( $cgstate->state->saxsiqfile )
     || !isset( $cgstate->state->saxsprfile )
     || !isset( $cgstate->state->output_loadsaxs )
     || !isset( $cgstate->state->output_load ) ) {
    error_exit_hook( "Please <i>'Load Structure'</i> first" );
}    

if ( !isset( $cgstate->state->flex ) || !count( $cgstate->state->flex ) ) {
    error_exit_hook( "No Flexible regions have been defined, Please run <i>'Structure info & flexible regions SAXS'</i> first" );
}    

if ( !isset( $cgstate->state->mmcdownloaded )
     || !$cgstate->state->mmcdownloaded
     || !isset( $cgstate->state->mmcextracted )
     || !strlen( $cgstate->state->mmcextracted ) ) {
    error_exit_hook( "No retrieved and extracted MMC results found. Please run <i>'Retrieve MMC'</i> first" );
}    

if ( !isset( $cgstate->state->output_iqpr ) ) {
    error_exit_hook( "Please run <i>Compute I(q)/P(r)</i> first" );
}

#if ( !isset( $cgstate->state->nnlsiqresults )
#    || !isset( $cgstate->state->nnlsprresults ) ) {
#    error_exit_hook( "No preselected results found, Please run <i>Compute I(q)/P(r)</i> first" );
#}

if ( isset( $cgstate->state->output_load->iqplot ) ) {
    $result->iqplot = &$cgstate->state->output_load->iqplot;
}

if ( isset( $cgstate->state->output_final ) ) {
    if ( isset( $cgstate->state->output_final->iqplotwaxsis ) ) {
        $result->iqplotwaxsis = &$cgstate->state->output_final->iqplotwaxsis;
    }
    if ( isset( $cgstate->state->output_final->iqresultswaxsis ) ) {
        $result->iqresultswaxsis = &$cgstate->state->output_final->iqresultswaxsis;
        if ( isset( $cgstate->state->output_final->csvdownloads ) ) {
            $result->iqresultswaxsis .= $cgstate->state->output_final->csvdownloads;
        }
    }
    if ( isset( $cgstate->state->output_final->pr_recon ) ) {
        $result->pr_recon = &$cgstate->state->output_final->pr_recon;
    }
    if ( isset( $cgstate->state->output_final->struct ) ) {
        $result->struct = &$cgstate->state->output_final->struct;
    }
    if ( isset( $cgstate->state->output_final->histplotfinal ) ) {
        $result->histplotfinal = &$cgstate->state->output_final->histplotfinal;
    }
    if ( isset( $cgstate->state->output_final->_textarea ) ) {
        $result->_textarea = $cgstate->state->output_final->_textarea;
    }
}

$result->desc                      = $cgstate->state->description;
$result->pname                     = $request->_project;
$result->downloads                 = $cgstate->state->output_load->downloads;

#### can replace with $cgstate->state->output_final->histplotfinal if isset ... leaving now for legacy checks
if ( !isset( $result->histplotfinal ) ) {
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

    if ( isset( $cgstate->state->iq_waxsis_nnlsresults )
         && isset( $cgstate->state->iq_waxsis_nnlsresults_colors )
        ) {
        final_hist( $result, $cgstate->state->iq_waxsis_nnlsresults, $cgstate->state->iq_waxsis_nnlsresults_colors, $rgdata );
    } else if ( isset( $cgstate->state->nnlsiqresultswaxsis ) ) {
        final_hist( $result, $cgstate->state->nnlsiqresultswaxsis, array_fill( 0, count( $cgstate->state->nnlsiqresultswaxsis ), "black" ), $rgdata );
    }
}

echo json_encode( $result );
exit;
