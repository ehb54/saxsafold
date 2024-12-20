<?php

{};

$plotly_hist_bin_count = 100;

function plotly_hist( $histname, $result, $stride = 0, $offset = 0, $adjacent = 0 ) {
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
                           ,"showline"       : true
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
                           ,"showline"       : true
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
                           ,"showline"       : true
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
                           ,"showline"       : true
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
                    for ( $adj = -$adjacent; $adj <= $adjacent; ++$adj ) {
                        if ( $stride && !(( $line + $offset + $adj) % $stride )) {
                            $plot->data[1]->x[] = floatval($linevals[0]);
                            $plot->data[1]->y[] = floatval($linevals[1]);
                            break;
                        }
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
                $plot->data[1]->name  = "Stride $stride<br>";
                $plot2->data[1]->name = "Stride $stride<br>";
                if ( $offset ) {
                    $plot->data[1]->name  .= "Offset $offset<br>";
                    $plot2->data[1]->name .= "Offset $offset<br>";
                }
                if ( $adjacent ) {
                    $plot->data[1]->name  .= "$adjacent Adjacent frames included<br>";
                    $plot2->data[1]->name .= "$adjacent Adjacent frames included<br>";
                }
                $plot->data[1]->name .= count( $plot->data[1]->x ) . " Frames";
                $plot2->data[1]->name .= count( $plot->data[1]->x ) . " Frames";
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

function final_hist( $result, $nnlsresults, $nnlsresults_colors, $rgdata, $adjacent = 0 ) {
    global $cgstate;

    if ( $cgstate->state->mmcdownloaded ) {
        ## histogram
        $histname = "monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.accepted_rg_results_data.txt";
        if ( file_exists( $histname ) ) {
            $reshist = (object)[];
            $res = plotly_hist( $histname, $reshist, $cgstate->state->mmcstride, $cgstate->state->mmcoffset, $adjacent );
            $plot = $reshist->histplot2;

            $plot->layout =
                (object) array_merge( (array) $plot->layout,
                                      [
                                       "xaxis2" => (object) [
                                           "gridcolor" => "rgba(111,111,111,0.5)"
                                           ,"type" => "linear"
                                           ,"title" => [
                                               "text" => ""
                                               ,"font" => [
                                                   "color"  => "rgb(0,5,80)"
                                               ]
                                           ]
                                           ,"showticklabels" => true
                                           ,"visible"        => true
                                           ,"matches"        => "x"
                                           ,"anchor"         => "y2"
                                           ,"showline"       => true
                                       ]
                                       ,"yaxis2" => (object) [
                                           "gridcolor" => "rgba(111,111,111,0.5)"
                                           ,"type" => "linear"
                                           ,"title" => [
                                               "text" => "% Contributing"
                                               ,"font" => [
                                                   "color"  => "rgb(0,5,80)"
                                               ]
                                               ,"standoff"       => 40
                                           ]
                                           ,"visible"        => true
                                           ,"showline"       => true
                                       ]
                                       ,"xaxis3" => (object) [
                                           "showgrid" => false
                                           ,"type" => "linear"
                                           ,"title" => [
                                               "text" => ""
                                               ,"font" => [
                                                   "color"  => "rgb(0,5,80)"
                                               ]
                                           ]
                                           ,"showticklabels" => false
                                           ,"visible"        => false
                                           ,"matches"        => "x"
                                           ,"anchor"         => "y3"
                                           ,"showline"       => false
                                       ]
                                       ,"yaxis3" => (object) [
                                           "showgrid" => false
                                           ,"type" => "linear"
                                           ,"title" => [
                                               "text" => ""
                                           ]
                                           ,"visible"        => false
                                           ,"showline"       => false
                                       ]
                                      ]
                );

            $plot = (object) array_merge( (array) $plot,
                                          [
                                           "config" => [
                                               "showLink" => true
                                                   ,"plotlyServerURL" => "https://chart-studio.plotly.com"
                                                   ,"responsive" => true
                                           ]
                                          ] );
                                           
            $plot->layout->title              = "NNLS fitting models Rg (top)<br>MMC Rg Histogram (bottom)";
            $plot->layout->showlegend         = true;

            $plot->layout->yaxis->title->text = "Norm. Frequency";
            $plot->layout->yaxis->domain      = [ 0, .4 ]; 
            $plot->layout->yaxis2->domain     = [ 0.5, .9 ];
            $plot->layout->yaxis3->domain     = [ 0.915, 1 ];
            $plot->layout->legend             = [ "x" => 1.1, "y" => .1 ];

#            $plot->layout->barmode            = "group";
#            $plot->layout->barmode            = "stack";
#            $plot->layout->barmode            = "overlay";

            $plot->data =
                json_decode( json_encode(
                                 array_merge( (array) $plot->data,
                                      [ (object) [
                                            "x" => []
                                            ,"y" => []
                                            ,"customdata" => []
                                            ,"hovertemplate" => '%{customdata}<br>%{y}%<br>Rg %{x}'
                                            ,"name" => "WAXSiS NNLS fit"
                                            ,"type" => "bar"
                                            ,"yaxis" =>  "y2"
                                            ,"showlegend" => false
                                            ,"marker" => [
                                                "color" => []
#                                                ,"opacity" => 0.7
                                                ,"line" => [
                                                    "color" => "rgb(8,48,107)"
                                                    ,"width" => 1.5
                                                ]
                                            ]
                                        ]
                                        ,
                                        (object) [
                                            "x" => []
                                            ,"y" => []
                                            ,"customdata" => []
                                            ,"hovertemplate" => '%{customdata}<br>Rg %{x}'
                                            ,"name" => ""
                                            ,"yaxis" =>  "y3"
                                            ,"mode"  => "markers"
                                            ,"showlegend" => false
                                            ,"marker" => [
                                                "color" => []
                                                ,"size" => 10
                                                ,"symbol" => "triangle-down"
                                                ,"line" => [
                                                    "color" => "rgb(8,48,107)"
                                                    ,"width" => 1.5
                                                ]
                                            ]
                                        ]
                                      ]
                                 )
                             ) );
            
            
            # need to sort nnlsresults


            $pos = 0;
            $avgrg = 0;

            foreach ( $nnlsresults as $k => $v ) {
                $namev = explode( ' ', $k );
                $model = end( $namev );
                $plot->data[2]->x[]             = floatval( sprintf( "%.1f", $reshist->histplot->data[0]->y[ $model - 1 ] ) );
                $plot->data[2]->y[]             = floatval( sprintf( "%.1f", 100 * $v ) );
                $plot->data[2]->customdata[]    = "Model $model";
                $plot->data[2]->marker->color[] =
                    (
                     isset( $nnlsresults_colors )
                     && isset( $nnlsresults_colors->$k )
                    )
                    ? $nnlsresults_colors->$k
                    : "black";
                $avgrg += $v * $reshist->histplot->data[0]->y[ $model - 1 ];
            }

            $plot->data[2]->width = ( max( $plot->data[0]->x ) - min( $plot->data[0]->x ) ) / (count( $plot->data[2]->x ) * 10 );

            $avgrg_key  = "Weighted average of NNLS fit";
            $avgrg_value = (object) [
                "Rg" => $avgrg
                ,"color" => "green"
                ];

            if ( !isset( $rgdata ) || !is_object( $rgdata ) ) {
                $rgdata = (object)[];
            }

            $rgdata->{$avgrg_key} = $avgrg_value;
            $rg_use_ordinate = [];
            
            foreach ( $rgdata as $k => $v ) {
                $rg_use_ordinate[ $v->Rg ]      = isset( $rg_use_ordinate[ $v->Rg ] ) ? $rg_use_ordinate[ $v->Rg ] + .2 : 1;
                $plot->data[3]->x[]             = floatval( sprintf( "%.1f", $v->Rg ) );
                $plot->data[3]->y[]             = $rg_use_ordinate[ $v->Rg ];
                $plot->data[3]->customdata[]    = $k;
                $plot->data[3]->marker->color[] = $v->color;
            }

            $result->histplotfinal = $plot;

            # echo "\$reshist->histplot->data\n" . json_encode(  $reshist->histplot->data , JSON_PRETTY_PRINT ) . "\n\n";
            # echo "\$plot->layout\n" . json_encode(  $plot->layout , JSON_PRETTY_PRINT ) . "\n\n";
            # echo "\$plot->data[2]\n" . json_encode(  $plot->data[2] , JSON_PRETTY_PRINT ) . "\n\n";
            # echo "\$plot\n" . json_encode(  $plot, JSON_PRETTY_PRINT ) . "\n\n";

        }
    }
}

/*
### testing

require "common.php";
$cgstate = new cgrun_state();

$result = (object)[];

#$nnlsresults = $cgstate->state->prwe_nnlsresults;
#$nnlsresults = $cgstate->state->pr_nnlsresults;
#$nnlsresults = $cgstate->state->iq_c3_nnlsresults;
#$nnlsresults = $cgstate->state->iq_p_nnlsresults;
#$nnlsresults = $cgstate->state->nnlsprresults;
$nnlsresults = $cgstate->state->iq_waxsis_nnlsresults;

require_once "sas.php";
$sas = new SAS();
$sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)", $cgstate->state->output_load->prplot );
$prrg = 0;
$sas->compute_rg_from_pr( "Exp. P(r)", $prrg );
echo "rg is $prrg\n";

$rgdata = (object) [
    "Original model<br>SOMO computed" => (object) [
        "Rg" => $cgstate->state->output_load->Rg
        ,"color" => "blue"
    ]
    ,"Exp. P(r)<br>SOMO computed on regular grid" => (object) [
        "Rg" => $prrg
        ,"color" => "brown"
    ]
    ];


$nnlsresults_colors = $cgstate->state->iq_waxsis_nnlsresults_colors;
final_hist( $result, $nnlsresults, $nnlsresults_colors, $rgdata );
echo json_encode(  $nnlsresults , JSON_PRETTY_PRINT ) . "\n\n";
echo json_encode(  $nnlsresults_colors , JSON_PRETTY_PRINT ) . "\n\n";

#final_hist( $result, $cgstate->state->nnlsiqresultswaxsis );
#echo json_encode(  $cgstate->state->nnlsiqresultswaxsis , JSON_PRETTY_PRINT ) . "\n\n";


file_put_contents( "plotout.json", "\n" . json_encode( $result->histplotfinal ) . "\n\n" );

echo "\ncat plotout.json\n\n";


*/
