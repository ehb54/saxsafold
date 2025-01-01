<?php

{};

require_once "common.php";
require_once "computeiqpr_defines.php";

## $toclear is an array of state variables
## $toremove is an array of subdirectories

function any_prior_results( $name, &$toclear, &$toremove, &$moduleswithresults ) {
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
            $toclear[] = "mmcdownloaded";
            $toclear[] = "mmcstride";
            $toclear[] = "mmcoffset";
            $toremove[] = "waxsissets";
            $toremove[] = "preselected";
        }
        case  "retrievemmc" : {
            $toclear[] = "mmcextracted";
            $toclear[] = "mmcframecount";
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
    
    $moduleswithresults = [];
    $reported = [];

    foreach ( $toclear as $v ) {
        
        switch ( $v ) {
            case "description" :
            case "loaded" :
                break;

            case "qmin" :
            case "qmax" :
            case "qpoints" :
            case "output_loadsaxs" :
            case "saxsiqfile" :
            case "saxsprfile" :
            {
                $title = "Load SAXS";
                if ( !isset( $reported[ $title  ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                }
            }
            break;

            case "solvent_e_density" :
            case "is_alphafold" :
            case "output_load" :
            case "waxsis_last_run_time_minutes" :
            {
                $title = "Load structure";
                if ( !isset( $reported[ $title  ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                }
            }
            break;

            case "flex" :
            case "output_flex" :
            {
                $title = "Structure info / flexible regions";
                if ( !isset( $reported[ $title ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                 }
            }
            break;                  
                                    
            case "mmcrunname" :     
            case "mmcdownloaded" :
            case "mmcstride" :
            case "mmcoffset" :
            {
                $title = "Run MMC";
                if ( !isset( $reported[ $title ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                }
            }
            break;                 
                                   
            case "mmcextracted" :  
            case "mmcframecount" :
            {
                $title = "Retrieve MMC";
                if ( !isset( $reported[ $title ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                }
            }
            break;

            case "computeiqpr_prerrors" :
            case "output_iqpr" :
            {
                $title = "Compute I(q)/P(r) Preselect models";
                if ( !isset( $reported[ $title ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                }
            }
            break;
            
            case "final_adjacent_frames" :
            case "final_waxsis_failures" :
            case "iq_waxsis_nnlsresults" :
            case "output_final" :
            case "iq_waxsis_nnlsresults_colors" :
            {
                $title = "Final model selection using WAXSiS";
                if ( !isset( $reported[ $title ] ) ) {
                    $moduleswithresults[] = $title;
                    $reported[ $title ]   = true;
                }
            }
            break;
            
# unless we want to add the $datas
#            default : {
#                error_exit( "any_prior_results() unknown field '$v'" );
#                return false;
#            }
        }
    }

    return count( $toclear ) + count( $toremove );
}

function question_prior_results( $name, $removecb = null ) {
    global $ga;
    global $cgstate;
    global $input;
    
    if ( !isset( $ga ) || !isset( $cgstate ) || !isset( $input ) ) {
        error_exit( "question_prior_results() requires \$ga, \$cgstate & \$input to be set", true, $removecb );
        return false;
    }

    $toclear            = [];
    $toremove           = [];
    $moduleswithresults = [];

    $usename = pathinfo( $name, PATHINFO_FILENAME );

    if ( !any_prior_results( $name, $toclear, $toremove, $moduleswithresults ) ) {
        ## nothing to clear

        if ( $usename == "defineproject" ) {
            $cgstate->state->loaded       = true;
            $cgstate->state->description  = $input->desc;
        }
            
        return true;
    }

    $resultsfound = '<strong><i><br>' . implode( '<br>', $moduleswithresults ) . '<br><br></i></strong>';
    # $resultsfound = '<br>' .implode( '<br>', $toclear );

    ## question user
    
    $response =
        json_decode(
            $ga->tcpquestion(
                [
                 "id"           => "q1"
                 ,"title"       => "<h5>Project '$input->_project' has existing previous results</h5>$resultsfound"
                 ,"icon"        => "warning.png"
                 ,"text"        => ""
                 ,"timeouttext" => "The time to respond has expired, please submit again."
                 ,"buttons"     => [ "Erase previous results", "Keep previous results" ]
                 ,"fields" => [
                     [
                      "id"          => "l1"
                      ,"type"       => "label"
                      ,"label"      => "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;If you Erase results, this will be permenant!<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;All results for subsequent stages indicated above for this project will be lost!"
                      ,"align"      => "center"
                     ]
                 ]
                ]

            )
        );

    if ( isset( $response->error ) && strlen( $response->error ) ) {
        error_exit( "Please submit again", true, $removecb );
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
                error_exit( "Please submit again", true, $removecb );
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
            error_exit( "Canceled - prior results kept", true, $removecb, 'information.png' );
        }
    }

    foreach ( $toclear as $v ) {
        unset( $cgstate->state->$v );
    }

    if ( count( $toremove ) ) {
        $cmd = '';
        foreach ( $toremove as $v ) {
            $cmd .= "rm -fr $v 2>&1 > /dev/null\n";
        }
        # $ga->tcpmessage( [ "_textarea" => $cmd ] );
        `$cmd`;
    }
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
