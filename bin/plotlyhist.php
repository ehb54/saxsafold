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

    if ( isset( $cgstate->state->mmcdownloaded ) ) {
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
#                                                ,"line" => [
#                                                    "color" => "rgb(8,48,107)"
#                                                    ,"width" => 1.5
#                                                ]
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
#                                                ,"line" => [
#                                                    "color" => "rgb(8,48,107)"
#                                                    ,"width" => 1.5
#                                               ]
                                            ]
                                        ]
                                      ]
                                 )
                             ) );
            
            
            # need to sort nnlsresults

            $pos    = 0;
            $avgrg2 = 0;

            file_put_contents( "/tmp/checkrg", "plotlyhist final func() running\n",  FILE_APPEND );
            foreach ( $nnlsresults as $k => $v ) {
                $namev = explode( ' ', $k );
                $model = end( $namev );
                if ( $model == "WAXSiS" || $model == 0 ) {
                    $plot->data[2]->x[]             = floatval( sprintf( "%.1f", $cgstate->state->output_load->Rg ) );
                    $plot->data[2]->y[]             = floatval( sprintf( "%.1f", 100 * $v ) );
                    $plot->data[2]->customdata[]    = "Model $model";
                    $plot->data[2]->marker->color[] =
                        (
                         isset( $nnlsresults_colors )
                         && isset( $nnlsresults_colors->$k )
                        )
                        ? $nnlsresults_colors->$k
                        : "black";
                    $avgrg2 += $v * $cgstate->state->output_load->Rg * $cgstate->state->output_load->Rg;
                } else {
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
                    $avgrg2 += $v * $reshist->histplot->data[0]->y[ $model - 1 ] * $reshist->histplot->data[0]->y[ $model - 1 ];
                }
            }

            $avgrg = sqrt( $avgrg2 );

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
                $rg_abscissa                     = sprintf( "%.1f", $v->Rg );
                $rg_use_ordinate[ $rg_abscissa ] = isset( $rg_use_ordinate[ $rg_abscissa ] ) ? $rg_use_ordinate[ $rg_abscissa ] + .2 : 1;
                $plot->data[3]->x[]              = floatval( $rg_abscissa );
                $plot->data[3]->y[]              = $rg_use_ordinate[ $rg_abscissa ];
                $plot->data[3]->customdata[]     = $k;
                $plot->data[3]->marker->color[]  = $v->color;
            }

            $result->histplotfinal = $plot;

            # echo "\$reshist->histplot->data\n" . json_encode(  $reshist->histplot->data , JSON_PRETTY_PRINT ) . "\n\n";
            # echo "\$plot->layout\n" . json_encode(  $plot->layout , JSON_PRETTY_PRINT ) . "\n\n";
            # echo "\$plot->data[2]\n" . json_encode(  $plot->data[2] , JSON_PRETTY_PRINT ) . "\n\n";
            # echo "\$plot\n" . json_encode(  $plot, JSON_PRETTY_PRINT ) . "\n\n";

        }
    }
}

function merge_histograms( $x1, $y1, $x2, $y2, $n_bins ) {
    ## Step 1: Find global range
    $min_x = min( min( $x1 ), min( $x2 ) );
    $max_x = max( max( $x1 ), max( $x2 ) );
    $bin_width = ( $max_x - $min_x ) / $n_bins;

    ## Step 2: Create bin centers and zeroed counts
    $bins = [];
    $counts = array_fill( 0, $n_bins, 0 );

    for ( $i = 0; $i < $n_bins; $i++ ) {
        $bins[$i] = $min_x + ( $i + 0.5 ) * $bin_width;
    }

    ## Step 3: Function to add data to bins
    $add_to_bins = function( $x, $y ) use ( &$counts, $min_x, $bin_width, $n_bins ) {
        foreach ( $x as $i => $xi ) {
            $bin_index = floor( ( $xi - $min_x ) / $bin_width );
            if ( $bin_index >= 0 && $bin_index < $n_bins ) {
                $counts[$bin_index] += $y[$i];
            }
        }
    };

    $add_to_bins( $x1, $y1 );
    $add_to_bins( $x2, $y2 );

    return [ $bins, $counts ];
}

function joined_hist( $result, $nnlsresults, $nnlsresults_colors, $rgdata ) {
    global $cgstates;
    global $best;
    global $plotly_hist_bin_count;
    # echo "joined_hist\n";

    ## need to loop over cgstates and merge info
    ## perhaps setup with firstproject and then merge or rewrite plotly_hist() to support multiple datasets?
    ## we store adjacent for each final result   "final_adjacent_frames": "1"

    ## accumulate all plots

    $reshists = (object)[];

    foreach ( $cgstates as $project => $cgstate ) {
        # echo "joined_hist project $project\n";

        if ( isset( $cgstate->state->mmcdownloaded ) ) {
            # echo "mmc downloaded\n";
            ## histogram
            $histname = "../$project/monomer_monte_carlo/" . $cgstate->state->mmcrunname . ".dcd.accepted_rg_results_data.txt";
            if ( file_exists( $histname ) ) {
                $reshists->$project = (object)[];
                # echo "project $project final adjacent frames : " . $cgstate->state->final_adjacent_frames . "\n";
                $res = plotly_hist( $histname, $reshists->$project, $cgstate->state->mmcstride, $cgstate->state->mmcoffset, $cgstate->state->final_adjacent_frames );
            }
        }
    }

    ## merge available plots
    ## $reshist->histplot is the plot of all frame Rg value by frame count
    ##                   ->data[0] is the full set
    ##                   ->data[1] is the subselected set
    ## $reshist->histplot2 is the plot of frequency by Rg
    ##                   ->data[0] is the full set
    ##                   ->data[1] is the subselected set
    
    foreach ( $reshists as $project => $reshist ) {
        # echo "project $project \$reshist->histplot2->data[0]:\n" . json_encode( $reshist->histplot2->data[0], JSON_PRETTY_PRINT ) . "\n\n";

        if ( !isset( $accumres ) ) {
            $accumres = $reshist;
            continue;
        }
        
        ## now accumulate plots in $reshist to $accumres;
        
        $frames0prev = count( $accumres->histplot->data[0]->x );
        $frames0this = count( $reshist->histplot->data[0]->x );
        $frames0post = $frames0prev + $frames0this;

        $frames1prev = count( $accumres->histplot->data[1]->x );
        $frames1this = count( $reshist->histplot->data[1]->x );
        $frames1post = $frames1prev + $frames1this;

        ## --> histplot->data[0].y 
        $accumres->histplot->data[0]->y = array_merge( $accumres->histplot->data[0]->y, $reshist->histplot->data[0]->y );
        ## these are frame numbers, simply appending for the full set
        $accumres->histplot->data[0]->x = range( 1, $frames0post );
        $accumres->histplot->data[0]->name = "$frames0post Frames";

        ## --> histplot->data[1]
        $accumres->histplot->data[1]->y = array_merge( $accumres->histplot->data[1]->y, $reshist->histplot->data[1]->y );
        ## these are subselected frame numbers, simply appending as we don't need the offset detail as we are not displaying the plot
        $accumres->histplot->data[1]->x = range( 1, $frames1post );
        $accumres->histplot->data[1]->name = "$frames1post Frames";

        ## --> histplot2->data[0]
        
        ## denormalize

        $x1 = $accumres->histplot2->data[0]->x;
        $y1 = $accumres->histplot2->data[0]->y;

        $x2 = $reshist->histplot2->data[0]->x;
        $y2 = $reshist->histplot2->data[0]->y;

        foreach ( $y1 as &$y ) {
            $y *= $frames0prev;
        }

        foreach ( $y2 as &$y ) {
            $y *= $frames0this;
        }

        # echo "normed:\n" . json_encode(
        # (object) [
        # "x1" => $x1
        # ,"y1" => $y1
        # ,"x2" => $x2
        # ,"y2" => $y2
        # ], JSON_PRETTY_PRINT ) . "\n";

        list( $merged_x, $merged_y ) = merge_histograms( $x1, $y1, $x2, $y2, $plotly_hist_bin_count );

        ## normalize

        $norm = 1.0 / ( $frames0prev + $frames0this );

        foreach ( $merged_y as &$y ) {
            $y *= $norm;
        }

        $accumres->histplot2->data[0]->x = $merged_x;
        $accumres->histplot2->data[0]->y = $merged_y;
        $accumres->histplot2->data[0]->name = "All MMC<br>$frames0post Frames<br>All projects";

        ## --> histplot2->data[1]
        
        ## denormalize

        $x1 = $accumres->histplot2->data[1]->x;
        $y1 = $accumres->histplot2->data[1]->y;

        $x2 = $reshist->histplot2->data[1]->x;
        $y2 = $reshist->histplot2->data[1]->y;

        foreach ( $y1 as &$y ) {
            $y *= $frames1prev;
        }

        foreach ( $y2 as &$y ) {
            $y *= $frames1this;
        }
        
        list( $merged_x, $merged_y ) = merge_histograms( $x1, $y1, $x2, $y2, $plotly_hist_bin_count );

        ## normalize

        $norm = 1.0 / ( $frames1prev + $frames1this );

        foreach ( $merged_y as &$y ) {
            $y *= $norm;
        }

        $accumres->histplot2->data[1]->x = $merged_x;
        $accumres->histplot2->data[1]->y = $merged_y;
        $accumres->histplot2->data[1]->name = "Subselected MMC<br>$frames1post Frames<br>All projects";

        # echo "\$reshists->$project\n" . json_encode( $reshist, JSON_PRETTY_PRINT ) . "\n\n";
        # echo "\$accumres:\n" . json_encode( $accumres, JSON_PRETTY_PRINT ) . "\n\n";
    }

    #    echo "\$accumres->histplot2->data[0]:\n" . json_encode( $accumres->histplot2->data[0], JSON_PRETTY_PRINT ) . "\n\n";
    #    echo "\$accumres->histplot2->data[1]:\n" . json_encode( $accumres->histplot2->data[1], JSON_PRETTY_PRINT ) . "\n\n";
    #    error_exit( "testing" );

    $plot = $accumres->histplot2;
                
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
                                                #                                                ,"line" => [
                                                #                                                    "color" => "rgb(8,48,107)"
                                                #                                                    ,"width" => 1.5
                                                #                                                ]
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
                                                #                                                ,"line" => [
                                                #                                                    "color" => "rgb(8,48,107)"
                                                #                                                    ,"width" => 1.5
                                                #                                               ]
                                            ]
                                        ]
                                      ]
                         )
                     ) );

    # need to sort nnlsresults

    $pos    = 0;
    $avgrg2 = 0;

    # file_put_contents( "/tmp/checkrg", "plotlyhist final func() running\n",  FILE_APPEND );
    foreach ( $nnlsresults as $k => $v ) {
        if ( !preg_match( '/^([^:]*):/', $k, $matches ) ) {
            return "Error: Could not determine project name from '$name'";
        }
        $project = $matches[1];
        
        $namev = explode( ' ', $k );
        $model = end( $namev );

        if ( $model == "WAXSiS" || $model == 0 ) {
            $plot->data[2]->x[]             = floatval( sprintf( "%.1f", $cgstates->{$best->iq->project}->state->output_load->Rg ) );
            $plot->data[2]->y[]             = floatval( sprintf( "%.1f", 100 * $v ) );
            $plot->data[2]->customdata[]    = "$project Model $model";
            $plot->data[2]->marker->color[] =
                (
                 isset( $nnlsresults_colors )
                 && isset( $nnlsresults_colors->$k )
                )
                ? $nnlsresults_colors->$k
                : "black";
            $avgrg2 += $v * $cgstates->{$best->iq->project}->state->output_load->Rg * $cgstates->{$best->iq->project}->state->output_load->Rg;
        } else {
            $plot->data[2]->x[]             = floatval( sprintf( "%.1f", $reshists->$project->histplot->data[0]->y[ $model - 1 ] ) );
            $plot->data[2]->y[]             = floatval( sprintf( "%.1f", 100 * $v ) );
            $plot->data[2]->customdata[]    = "$project Model $model";
            $plot->data[2]->marker->color[] =
                (
                 isset( $nnlsresults_colors )
                 && isset( $nnlsresults_colors->$k )
                )
                ? $nnlsresults_colors->$k
                : "black";
            $avgrg2 += $v * $reshists->$project->histplot->data[0]->y[ $model - 1 ] * $reshists->$project->histplot->data[0]->y[ $model - 1 ];
        }
    }

    $avgrg = sqrt( $avgrg2 );

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
        $rg_abscissa                     = sprintf( "%.1f", $v->Rg );
        $rg_use_ordinate[ $rg_abscissa ] = isset( $rg_use_ordinate[ $rg_abscissa ] ) ? $rg_use_ordinate[ $rg_abscissa ] + .2 : 1;
        $plot->data[3]->x[]              = floatval( $rg_abscissa );
        $plot->data[3]->y[]              = $rg_use_ordinate[ $rg_abscissa ];
        $plot->data[3]->customdata[]     = $k;
        $plot->data[3]->marker->color[]  = $v->color;
    }

    $result->histplotfinal = $plot;

    # echo "\$reshist->histplot->data\n" . json_encode(  $reshist->histplot->data , JSON_PRETTY_PRINT ) . "\n\n";
    # echo "\$plot->layout\n" . json_encode(  $plot->layout , JSON_PRETTY_PRINT ) . "\n\n";
    # echo "\$plot->data[2]\n" . json_encode(  $plot->data[2] , JSON_PRETTY_PRINT ) . "\n\n";
    # echo "\$plot\n" . json_encode(  $plot, JSON_PRETTY_PRINT ) . "\n\n";
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
