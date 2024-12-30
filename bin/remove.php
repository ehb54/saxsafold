<?php

{};

require_once "common.php";
require_once "computeiqpr_defines.php";

## $toclear is an array of state variables
## $toremove is an array of subdirectories

function any_prior_results( $name, &$toclear, &$toremove ) {
    global $cgstate;
    global $mdatas;
    
    if ( !isset( $cgstate ) || !isset( $mdatas ) ) {
        error_exit( "any_prior_results() requires \$cgstate & \$mdatas to be set" );
        return false;
    }

    $toclear  = [];
    $toremove = [];

    $usename = pathinfo( $name, PATHINFO_FILENAME );

    switch ( $usename ) {

        case  "defineproject" : {
            $toclear[] = "description";
            $toclear[] = "loaded";
        }
        case  "loadsaxs" : {
            $toclear[] = "qmin";
            $toclear[] = "qmax";
            $toclear[] = "qpoints";
            $toclear[] = "output_loadsaxs";
            $toclear[] = "saxsiqfile";
            $toclear[] = "saxsprfile";
        }
        case  "loadstructure" : {
            $toclear[] = "solvent_e_density";
            $toclear[] = "is_alphafold";
            $toclear[] = "output_load";
            $toclear[] = "waxsis_last_run_time_minutes";
        }
        case  "structureflex" : {
            $toclear[] = "flex";
            $toclear[] = "output_flex";
        }
        case  "runmmc" : {
            $toclear[] = "mmcrunname"; # , ... actually set in runmmc_load.php & save;
            $toremove[] = "waxsissets";
            $toremove[] = "preselected";
        }
        case  "retrievemmc" : {
            $toclear[] = "mmcstride";
            $toclear[] = "mmcoffset";
            $toclear[] = "mmcextracted";
            $toclear[] = "mmcframecount";
            $toclear[] = "mmcdownloaded";
        }
        case  "computeiqpr" : {
            $toclear[] = "computeiqpr_prerrors";
            $toclear[] = "output_iqpr";
            foreach ( $mdatas as $mdata ) {
                $toclear[] = $mdata->tags->nnlsresults;
            }
        }
        case  "finalmodel" : {
            $toclear[] = "final_adjacent_frames";
            $toclear[] = "final_waxsis_failures";
            $toclear[] = "iq_waxsis_nnlsresults";
            $toclear[] = "output_final";
            $toclear[] = "iq_waxsis_nnlsresults_colors";
        }

        break;
        
        default : {
            error_exit( "any_prior_results() unknown module '$name'" );
            return false;
        }
    }

    foreach ( $toclear as $k => $v ) {
        if ( !isset( $cgstate->state->$v ) ) {
            unset( $toclear[$k] );
        }
    }

    foreach ( $toremove as $k => $v ) {
        if ( !is_dir( $v ) ) {
            unset( $toremove[$k] );
        }
    }
    
    return count( $toclear ) + count( $toremove );
}

function question_prior_results( $name ) {
    global $ga;
    global $cgstate;
    global $input;
    
    if ( !isset( $ga ) || !isset( $cgstate ) || !isset( $input ) ) {
        error_exit( "question_prior_results() requires \$ga, \$cgstate & \$input to be set" );
        return false;
    }

    $toclear  = [];
    $toremove = [];

    $usename = pathinfo( $name, PATHINFO_FILENAME );

    if ( !any_prior_results( $name, $toclear, $toremove ) ) {
        ## nothing to clear

        if ( $usename == "defineproject" ) {
            $cgstate->state->loaded       = true;
            $cgstate->state->description  = $input->desc;
        }
            
        return true;
    }

    ## question user
    
    $response =
        json_decode(
            $ga->tcpquestion(
                [
                 "id"           => "q1"
                 ,"title"       => "<h5>Project '$input->_project' has existing previous results</h5>"
                 ,"icon"        => "warning.png"
                 ,"text"        => ""
                 ,"timeouttext" => "The time to respond has expired, please submit again."
                 ,"buttons"     => [ "Erase previous results", "Keep previous results" ]
                 ,"fields" => [
                     [
                      "id"          => "l1"
                      ,"type"       => "label"
                      ,"label"      => "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;If you Erase results, this will be permenant!"
                      ,"align"      => "center"
                     ]
                 ]
                ]

            )
        );

    if ( isset( $response->error ) && strlen( $response->error ) ) {
        error_exit( "Please submit again" );
    }

    if ( $response->_response->button == "keeppreviousresults" ) {
        if ( $usename == "defineproject" ) {
            if ( $response->_response->button == "keeppreviousresults" &&
                 $cgstate->state->description != $input->desc ) {
                if ( strlen( trim( $input->desc ) ) ) {
                    $response =
                        json_decode(
                            $ga->tcpquestion(
                                [
                                 "id"           => "q1"
                                 ,"title"       => "<h5>You have chosen to keep previous results and have changed the description</h5>"
                                 ,"icon"        => "warning.png"
                                 ,"text"        => ""
                                 ,"timeouttext" => "The time to respond has expired, please submit again."
                                 ,"buttons"     => [ "Replace the description", "Keep previous description" ]
                                 ,"fields" => []
                                ]
                            )
                        );
                    if ( $response->_response->button == "replacethedescription" ) {
                        $cgstate->state->description = $input->desc;
                    } else {
                        $ga->tcpmessage( [ "desc" => $cgstate->state->description ] );
                    }
                } else {
                    $ga->tcpmessage( [ "desc" => $cgstate->state->description ] );
                }
            }            

            if ( isset( $response->error ) && strlen( $response->error ) ) {
                error_exit( "Please submit again" );
            }

            if ( $response->_response->button == "erasepreviousresults" ) {
                $cgstate->state               = (object)[];
                $cgstate->state->loaded       = true;
                $cgstate->state->description  = $input->desc;
                unset( $toclear->loaded );
                unset( $toclear->description );
            }
            return true;
        } else {
#            error_exit( "Canceled '$usename'" );
            error_exit( "Canceled" );
        }
    }

    foreach ( $toclear as $v ) {
        unset( $cgstate->state->$v );
    }

    foreach ( $toremove as $v ) {
        $cmd .= "rm -fr $v 2>&1 > /dev/null\n";
    }
    # $ga->tcpmessage( [ "_textarea" => $cmd ] );
    `$cmd`;
}

/*

## testing

$cgstate = new cgrun_state();

$to_unset  = [];
$to_remove = [];

if ( any_prior_results( $argv[1], $to_unset, $to_remove ) ) {
    echo "to_unset:\n" . json_encode( $to_unset, JSON_PRETTY_PRINT ) . "\n";
    echo "to_remove:\n" . json_encode( $to_remove, JSON_PRETTY_PRINT ) . "\n";
} else {
    echo "nothing to clear nor remove\n";
}

*/
