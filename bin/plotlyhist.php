<?php

{};

function plotly_hist( $histname, $result, $stride = 0 ) {
    global $papercolors;

    if ( !file_exists( $histname ) ) {
        return "\nError: expected MMC Rg histogram file not found!\n";
    } else {
        ## plotly

        if ( $histfiledata = file_get_contents( $histname ) ) {
            $plotin = explode( "\n", $histfiledata );
            $plot = json_decode(
                '{
                    "data" : [
                        {
                         "x"        : []
                         ,"y"       : []
                         ,"type" : "scatter"
                         ,"name" : "Full data"
                         ,"line" : {
                             "color"  : "rgb(150,150,222)"
                             ,"width" : 1
                          }
                        }
                        ,{
                         "x"        : []
                         ,"y"       : []
                         ,"type" : "scatter"
                         ,"name" : "Decimated"
                         ,"line" : {
                             "color"  : "rgb(50,255,50)"
                             ,"width" : 1
                          }
                        }
                     ]
                     ,"layout" : {
                        "title" : "MMC Rg"
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                        ,"paper_bgcolor": "rgba(0,0,0,0)"
                        ,"plot_bgcolor": "rgba(0,0,0,0)"
                        ,"xaxis" : {
                           "gridcolor" : "rgba(111,111,111,0.5)"
                           ,"title" : {
                            "text" : "MMC Frame"
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                         }
                        }
                        ,"yaxis" : {
                           "gridcolor" : "rgba(111,111,111,0.5)"
                           ,"title" : {
                            "text" : "Rg [&#8491;]"
                           ,"standoff" : 20
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                         }
                        }
                     }
                }'
                );

            ## first line is a header
            array_shift( $plotin );

            if ( $stride ) {
                $plot->data[1]->name = "Stride $stride";
            }

            $line = 0;
            foreach ( $plotin as $linein ) {
                ++$line;

                $linevals = preg_split( '/\s+/', trim( $linein ) );

                if ( count( $linevals ) >= 2 ) {
                    $plot->data[0]->x[] = floatval($linevals[0]);
                    $plot->data[0]->y[] = floatval($linevals[1]);
                    if ( $stride && !($line % $stride )) {
                        $plot->data[1]->x[] = floatval($linevals[0]);
                        $plot->data[1]->y[] = floatval($linevals[1]);
                    }                        
                }
            }

            $plot->data[0]->name = "All MMC<br>" . count( $plot->data[0]->x ) . " Frames";

            if ( $stride ) {
                $plot->data[1]->name = "Stride $stride<br>" . count( $plot->data[1]->x ) . " Frames";
            }
            
            if ( isset( $papercolors ) && $papercolors ) {
                $plot->data[0]->line->color               = "rgb(50,50,122)";
                $plot->data[1]->line->color               = "rgb(122,50,50)";
                $plot->layout->font->color                = "rgb(0,0,0)";
                $plot->layout->xaxis->title->font->color  = "rgb(0,0,0)";
                $plot->layout->yaxis->title->font->color  = "rgb(0,0,0)";
                $plot->layout->xaxis->gridcolor           = "rgb(150,150,150)";
                $plot->layout->yaxis->gridcolor           = "rgb(150,150,150)";
            }

            $result->histplot = $plot;
        }
    }
    return "";
}
