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

include "genapp.php";
include "datetime.php";

$ga        = new GenApp( $input, $output );
$fdir      = preg_replace( "/^.*\/results\//", "results/", $input->_base_directory );
$base_dir  = preg_replace( '/^.*\//', '', $input->_base_directory );
$logon     = $input->_logon;
$scriptdir = dirname(__FILE__);

## for isprojectlocked()
$wdir      = preg_replace( "/\/results\/.*$/", "", $input->_base_directory );
include "$wdir/ajax/joblog.php";

## get state

require "common.php";
$cgstate = new cgrun_state();

if ( isprojectlocked( $input->_base_directory ) ) {
    if (
        (
         isset( $cgstate->state->description )
         && $input->desc != $cgstate->state->description
         && strlen( $input->desc )
        )
        ||
        (
         !isset( $cgstate->state->description )
         && strlen( $input->desc )
        )
        ) {
        $output->_message =
            [
             "text" => "A job is currently running in this project<br>Your updated description will not be saved"
            ];
    }

    $output->_textarea = "Current project is set to $input->pname\n";
    echo json_encode( $output );
    exit;
}

## does the project already exist ?

require_once "remove.php";
question_prior_results( __FILE__ );

/*
if ( isset( $cgstate->state->loaded ) ) {

    $response =
        json_decode(
            $ga->tcpquestion(
                [
                 "id"           => "q1"
                 ,"title"       => "<h5>Project '$input->_project' is already defined</h5>"
                 ,"icon"        => "warning.png"
                 ,"text"        => ""
                 ,"timeouttext" => "The time to respond has expired, please submit again."
                 ,"buttons"     => [ "Erase previous results", "Keep previous results" ]
                 ,"fields" => [
                     [
                      "id"          => "l1"
                      ,"type"       => "label"
                      ,"label"      => "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;If you Erase results, this will be permanent!"
                      ,"align"      => "center"
                     ]
                 ]
                ]

            )
        );
    

    if ( isset( $response->error ) && strlen( $response->error ) ) {
        error_exit( "Please submit again" );
    }

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
    }
} else {
    $cgstate->state->loaded       = true;
    $cgstate->state->description  = $input->desc;
}
*/

## process inputs here to produce output

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

$output->{'_textarea'} = "Current project is set to $input->pname\n";

echo json_encode( $output );
