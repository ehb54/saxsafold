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

## get state

require "common.php";
$cgstate = new cgrun_state();

## make sure project is loaded

if ( !$cgstate->state->loaded ) {
   error_exit( "You must first <i>Define project</i> for this project $input->_project " );
}

## process inputs here to produce output

## possibly plot (easy for P(r), we have the code)

## plotly

$iqfile = $input->saxsiqfile[0];
if ( file_exists( $iqfile ) ) {
    if ( $iqfiledata = file_get_contents( $iqfile ) ) {
        $plotin = explode( "\n", $iqfiledata );
        $plot = json_decode(
            '{
                "data" : [
                    {
                     "x"        : []
                     ,"y"       : []
                     ,"error_y" : {
                         "type"  : "data"
                         ,"array"   : []
                         ,"visible" : true
                     }
                     ,"type" : "scatter"
                     ,"line" : {
                         "color"  : "rgb(150,150,222)"
                         ,"width" : 1
                      }
                    }
                 ]
                 ,"layout" : {
                    "title" : "I(q)"
                    ,"font" : {
                        "color"  : "rgb(0,5,80)"
                    }
                    ,"paper_bgcolor": "rgba(0,0,0,0)"
                    ,"plot_bgcolor": "rgba(0,0,0,0)"
                    ,"xaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "linear"
                       ,"title" : {
                       "text" : "q [&#8491;<sup>-1</sup>]"
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                     }
                    }
                    ,"yaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "log"
                       ,"title" : {
                       "text" : "I(q) a.u."
                       ,"standoff" : 20
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                     }
                    }
                 }
            }'
            );

        ## first two lines are headers
        array_shift( $plotin );
        array_shift( $plotin );

        ## $plot->plotincount = count( $plotin );
        
        foreach ( $plotin as $linein ) {
            $linevals = preg_split( '/\s+/', trim( $linein ) );

            if ( count( $linevals ) > 2 ) {
                $plot->data[0]->x[] = floatval($linevals[0]);
                $plot->data[0]->y[] = floatval($linevals[1]);
                $plot->data[0]->error_y->array[] = floatval($linevals[2]);
            } else {
                if ( count( $linevals ) == 2 ) {
                    $plot->data[0]->x[] = floatval($linevals[0]);
                    $plot->data[0]->y[] = floatval($linevals[1]);
                }
            }
        }
            
        if ( isset( $papercolors ) && $papercolors ) {
            $plot->data[0]->line->color               = "rgb(50,50,122)";
            $plot->layout->font->color                = "rgb(0,0,0)";
            $plot->layout->xaxis->title->font->color  = "rgb(0,0,0)";
            $plot->layout->yaxis->title->font->color  = "rgb(0,0,0)";
            $plot->layout->xaxis->gridcolor           = "rgb(150,150,150)";
            $plot->layout->yaxis->gridcolor           = "rgb(150,150,150)";
        }

        $plot->layout->paper_bgcolor = 'white';

        $output->iqplot = $plot;
    }
}

$prfile = $input->saxsprfile[0];
if ( file_exists( $prfile ) ) {
    if ( $prfiledata = file_get_contents( $prfile ) ) {
        $plotin = explode( "\n", $prfiledata );
        $plot = json_decode(
            '{
                "data" : [
                    {
                     "x"        : []
                     ,"y"       : []
                     ,"error_y" : {
                         "type"  : "data"
                         ,"array"   : []
                         ,"visible" : true
                     }
                     ,"type" : "scatter"
                     ,"line" : {
                         "color"  : "rgb(150,150,222)"
                         ,"width" : 1
                      }
                    }
                 ]
                 ,"layout" : {
                    "title" : "P(r)"
                    ,"font" : {
                        "color"  : "rgb(0,5,80)"
                    }
                    ,"paper_bgcolor": "rgba(0,0,0,0)"
                    ,"plot_bgcolor": "rgba(0,0,0,0)"
                    ,"xaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"title" : {
                       "text" : "Distance [&#8491;]"
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                     }
                    }
                    ,"yaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"title" : {
                       "text" : "Frequency a.u."
                       ,"standoff" : 20
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                     }
                    }
                 }
            }'
            );

        ## first two lines are headers
        array_shift( $plotin );
        array_shift( $plotin );

        ## $plot->plotincount = count( $plotin );
        
        foreach ( $plotin as $linein ) {
            $linevals = preg_split( '/\s+/', trim( $linein ) );

            if ( 0 ) { # something wrong here, errorbars look strange

                if ( count( $linevals ) > 2 ) {
                    $plot->data[0]->x[] = floatval($linevals[0]);
                    $plot->data[0]->y[] = floatval($linevals[1]);
                    $plot->data[0]->error_y->array[] = floatval($linevals[2]);
                } else {
                    if ( count( $linevals ) == 2 ) {
                        $plot->data[0]->x[] = floatval($linevals[0]);
                        $plot->data[0]->y[] = floatval($linevals[1]);
                    }
                }
            }

            if ( count( $linevals ) >= 2 ) {
                $plot->data[0]->x[] = floatval($linevals[0]);
                $plot->data[0]->y[] = floatval($linevals[1]);
            }
        }
            
        if ( isset( $papercolors ) && $papercolors ) {
            $plot->data[0]->line->color               = "rgb(50,50,122)";
            $plot->layout->font->color                = "rgb(0,0,0)";
            $plot->layout->xaxis->title->font->color  = "rgb(0,0,0)";
            $plot->layout->yaxis->title->font->color  = "rgb(0,0,0)";
            $plot->layout->xaxis->gridcolor           = "rgb(150,150,150)";
            $plot->layout->yaxis->gridcolor           = "rgb(150,150,150)";
        }

        $plot->layout->paper_bgcolor = 'white';

        $output->prplot = $plot;
    }
}

## save state

$cgstate->state->saxsiqfile      = $input->saxsiqfile[0];
$cgstate->state->saxsprfile      = $input->saxsprfile[0];
$cgstate->state->output_loadsaxs = $output;
$cgstate->state->qmax            = end( $output->iqplot->data[0]->x);
$cgstate->state->qmin            = $output->iqplot->data[0]->x[0];
$cgstate->state->qpoints         = count( $output->iqplot->data[0]->x);

if ( !$cgstate->save() ) {
    echo '{"_message":{"icon":"toast.png","text":"Save state failed: ' . $cgstate->errors . '"}}';
    exit;
}

## log results to textarea

# $output->{'_textarea'} = "JSON output from executable:\n" . json_encode( $output, JSON_PRETTY_PRINT ) . "\n";
# $output->{'_textarea'} .= "JSON input from executable:\n"  . json_encode( $input, JSON_PRETTY_PRINT )  . "\n";

echo json_encode( $output );
