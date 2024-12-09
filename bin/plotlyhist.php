<?php

{};

$plotly_hist_bin_count = 100;

function plotly_hist( $histname, $result, $stride = 0, $offset = 0 ) {
    global $papercolors;
    global $plotly_hist_bin_count;

    if ( !file_exists( $histname ) ) {
        return "\nError: expected MMC Rg histogram file not found!\n";
    } else {
        ## plotly

        if ( $histfiledata = file_get_contents( $histname ) ) {
            $plotin = explode( "\n", $histfiledata );

            ## Rg histogram bins
            $rghisto = (object)[ "rgfull" => []
                                 ,"rgstride" => [] ];

            
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

            ## Rg histogram plot
            $plot2 = json_decode(
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
                        "title" : "MMC Rg Histogram"
                        ,"showlegend" : false
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                        ,"paper_bgcolor": "rgba(0,0,0,0)"
                        ,"plot_bgcolor": "rgba(0,0,0,0)"
                        ,"xaxis" : {
                           "gridcolor" : "rgba(111,111,111,0.5)"
                           ,"title" : {
                            "text" : "Rg [&#8491;]"
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                         }
                        }
                        ,"yaxis" : {
                           "gridcolor" : "rgba(111,111,111,0.5)"
                           ,"title" : {
                            "text" : "Normalized Frequency"
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
                $plot->data[1]->name = "Stride $stride<br>Offset $offset";
            }

            $line = 0;
            foreach ( $plotin as $linein ) {
                ++$line;

                $linevals = preg_split( '/\s+/', trim( $linein ) );

                if ( count( $linevals ) >= 2 ) {
                    $plot->data[0]->x[] = floatval($linevals[0]);
                    $plot->data[0]->y[] = floatval($linevals[1]);
                    if ( $stride && !(( $line + $offset ) % $stride )) {
                        $plot->data[1]->x[] = floatval($linevals[0]);
                        $plot->data[1]->y[] = floatval($linevals[1]);
                    }                        
                }
            }

            $bin_size = ( max( $plot->data[0]->y ) - min( $plot->data[0]->y ) ) / $plotly_hist_bin_count;

            foreach ( $plot->data[0]->y as $rg ) {
                @$rghisto->rgfull[ (string)( round( $rg / $bin_size ) * $bin_size ) ]++;
            }
            
            foreach ( $plot->data[1]->y as $rg ) {
                @$rghisto->rgstride[ (string)( round( $rg / $bin_size ) * $bin_size ) ]++;
            }

            ksort( $rghisto->rgfull, SORT_NUMERIC );
            ksort( $rghisto->rgstride, SORT_NUMERIC );

            ## populate rg hist plot data
            if ( $rgfullsum = array_sum( $rghisto->rgfull ) ) {
                foreach( $rghisto->rgfull as $rg => $cnt ) {
                    $plot2->data[0]->x[] = $rg;
                    $plot2->data[0]->y[] = $cnt / $rgfullsum;
                }
            }
            
            if ( $rgstridesum = array_sum( $rghisto->rgstride ) ) {
                foreach( $rghisto->rgstride as $rg => $cnt ) {
                    $plot2->data[1]->x[] = $rg;
                    $plot2->data[1]->y[] = $cnt / $rgstridesum;
                }
            }

            $plot->data[0]->name  = "All MMC<br>" . count( $plot->data[0]->x ) . " Frames";
            $plot2->data[0]->name = "All MMC<br>" . count( $plot->data[0]->x ) . " Frames";

            if ( $stride ) {
                $plot->data[1]->name  = "Stride $stride<br>" . count( $plot->data[1]->x ) . " Frames";
                $plot2->data[1]->name = "Stride $stride<br>" . count( $plot->data[1]->x ) . " Frames";
                if ( $offset ) {
                    $plot->data[1]->name  = "Stride $stride<br>Offset $offset<br>" . count( $plot->data[1]->x ) . " Frames";
                    $plot2->data[1]->name = "Stride $stride<br>Offset $offset<br>" . count( $plot->data[1]->x ) . " Frames";
                }
            }

            if ( isset( $papercolors ) && $papercolors ) {
                $plot->data[0]->line->color               = "rgb(50,50,122)";
                $plot->data[1]->line->color               = "rgb(122,50,50)";
                $plot->layout->font->color                = "rgb(0,0,0)";
                $plot->layout->xaxis->title->font->color  = "rgb(0,0,0)";
                $plot->layout->yaxis->title->font->color  = "rgb(0,0,0)";
                $plot->layout->xaxis->gridcolor           = "rgb(150,150,150)";
                $plot->layout->yaxis->gridcolor           = "rgb(150,150,150)";

                $plot2->data[0]->line->color              = "rgb(50,50,122)";
                $plot2->data[1]->line->color              = "rgb(122,50,50)";
                $plot2->layout->font->color               = "rgb(0,0,0)";
                $plot2->layout->xaxis->title->font->color = "rgb(0,0,0)";
                $plot2->layout->yaxis->title->font->color = "rgb(0,0,0)";
                $plot2->layout->xaxis->gridcolor          = "rgb(150,150,150)";
                $plot2->layout->yaxis->gridcolor          = "rgb(150,150,150)";
            }

            $plot->layout->paper_bgcolor  = 'white';
            $plot2->layout->paper_bgcolor = 'white';

            $result->histplot  = $plot;
            $result->histplot2 = $plot2;
        }
    }
    return "";
}
