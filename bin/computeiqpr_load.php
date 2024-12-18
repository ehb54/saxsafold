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

if ( !strlen( $request->_project ) ) {
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

$result->desc                      = $cgstate->state->description;
$result->pname                     = $request->_project;
#$result->downloads                 = $cgstate->state->output_load->downloads;

# require "$scriptdir/plotly_computeiqpr.php";
# plotlycomputeiqpr( $cgstate->state->output_loadsaxs, $result );

if ( !isset( $cgstate->state->output_load->iqplot )
     || !isset( $cgstate->state->output_load->prplot )
    ) {
    error_exit_hook( "Output from <i>'Load Structure'</i> incomplete" );
}    

if ( !isset( $cgstate->state->output_loadsaxs->iqplot )
     || !isset( $cgstate->state->output_loadsaxs->prplot )
    ) {
    error_exit_hook( "Output from <i>'Load SAXS'</i> incomplete" );
}    
    
require "$scriptdir/sas.php";
require "$scriptdir/computeiqpr_funcs.php";

$sas = new SAS();

setup_computeiqpr_plots( $result, $mdata );

if ( isset( $cgstate->state->output_iqpr ) ) {
    
    foreach ( $mdatas as $mdata ) {
        if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->plotallhtml} ) ) {
            $result->{$mdata->tags->plotallhtml} = &$cgstate->state->output_iqpr->{$mdata->tags->plotallhtml};
        }
        if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->plotsel} ) ) {
            $result->{$mdata->tags->plotsel} = &$cgstate->state->output_iqpr->{$mdata->tags->plotsel};
        }
        if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->results} ) ) {
            $result->{$mdata->tags->results} = &$cgstate->state->output_iqpr->{$mdata->tags->results};
        }
        if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->header_id} ) ) {
            $result->{$mdata->tags->header_id} = &$cgstate->state->output_iqpr->{$mdata->tags->header_id};
        }
        if ( isset( $cgstate->state->output_iqpr->{$mdata->tags->downloads} ) ) {
            $result->{$mdata->tags->downloads} = &$cgstate->state->output_iqpr->{$mdata->tags->downloads};
        }
    }

    if ( isset( $cgstate->state->output_iqpr->pr_plotallhtml ) ) {
        $result->pr_plotallhtml = &$cgstate->state->output_iqpr->pr_plotallhtml;
    }
    if ( isset( $cgstate->state->output_iqpr->pr_results ) ) {
        $result->pr_results = &$cgstate->state->output_iqpr->pr_results;
    }
    if ( isset( $cgstate->state->output_iqpr->pr_plotsel ) ) {
        $result->pr_plotsel = &$cgstate->state->output_iqpr->pr_plotsel;
    }
    if ( isset( $cgstate->state->output_iqpr->pr_header ) ) {
        $result->pr_header = &$cgstate->state->output_iqpr->pr_header;
    }
    if ( isset( $cgstate->state->output_iqpr->pr_downloads ) ) {
        $result->pr_downloads = &$cgstate->state->output_iqpr->pr_downloads;
    }
    if ( isset( $cgstate->state->output_iqpr->prwe_downloads ) ) {
        $result->prwe_downloads = &$cgstate->state->output_iqpr->prwe_downloads;
    }

    if ( isset( $cgstate->state->computeiqpr_prerrors ) &&
         $cgstate->state->computeiqpr_prerrors ) {
        if ( isset( $cgstate->state->output_iqpr->prwe_plotsel ) ) {
            $result->prwe_plotsel = &$cgstate->state->output_iqpr->prwe_plotsel;
        }
        if ( isset( $cgstate->state->output_iqpr->prwe_results ) ) {
            $result->prwe_results = &$cgstate->state->output_iqpr->prwe_results;
        }
    } else {
        unset( $result->prwe_plotsel );
    }
}


## unset() below can be removed, but keeping for legacy run testing
unset( $result->iq_p_plotall );
unset( $result->iq_c3_plotall );
unset( $result->iqplotall );
unset( $result->prplotall );

echo json_encode( $result );
exit;
