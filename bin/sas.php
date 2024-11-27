<?php
{};

## a class for handling Iq Pr

include_once "common.php";

class SAS {

    public $last_error;

    private $data;
    private $plots;
    private $debug;
    private $exit_on_error;

    const WIDTH_LINE            = 1;
    const WIDTH_ERROR_CAP       = 1;
    const DOMAIN_YAXIS_RESID    = [ 0.25, 1 ];
    const DOMAIN_YAXIS2_RESID   = [ 0, 0.21 ];
    const PLOT_IQ_XAXIS_TITLE   = "q [&#8491;<sup>-1</sup>]";
    const PLOT_PR_XAXIS_TITLE   = "Frequency a.u.";

    ## php 8.1 has enum, should eventually replace

    const PLOT_IQ    = 0;
    const PLOT_PR    = 1;
    
    private $plot_tmpl;
    
    ## n.b. had to remove layout:yaxis:title:standoff:20 as it didn't seem be be honored for the subplot

    function __construct( $debug = false, $exit_on_error = true ) {
        $this->debug         = $debug;
        $this->exit_on_error = $exit_on_error;

        if ( $this->debug ) { echo "SAS::construct()\n"; }
        
        $this->data  = (object) [];
        $this->plots = (object) [];

        $this->plot_tmpl = [
        # PLOT_IQ must == 0
        json_decode(
            '{
                 "type" : 0
                 ,"data" : [
                 ]
                 ,"layout" : {
                    "title" : "I(q)"
                    ,"font" : {
                        "color"  : "rgb(0,5,80)"
                    }
                    ,"margin" : {
                        "b" : 100
                    }
                    ,"paper_bgcolor": "white"
                    ,"plot_bgcolor": "white"
                    ,"xaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "linear"
                       ,"title" : {
                           "text" : "q [&#8491;<sup>-1</sup>]"
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                       }
                       ,"showticklabels" : false
                    }
                    ,"yaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "log"
                       ,"title" : {
                           "text" : "I(q) a.u."
                           ,"font" : {
                               "color"  : "rgb(0,5,80)"
                           }
                       }
                    }
                    ,"xaxis2" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "linear"
                       ,"title" : {
                           "text" : ""
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                       }
                       ,"showticklabels" : true
                       ,"visible"        : false
                       ,"matches"        : "x"
                       ,"anchor"         : "y2"
                    }
                    ,"yaxis2" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "linear"
                       ,"title" : {
                           "text" : "Res./SD"
                           ,"font" : {
                               "color"  : "rgb(0,5,80)"
                           }
                       }
                       ,"visible"    : false
                    }
                    ,"annotations" : [ 
                     {
                        "xref"       : "paper"
                        ,"yref"      : "paper"
                        ,"x"         : -0.1
                        ,"xanchor"   : "left"
                        ,"y"         : -0.3
                        ,"yanchor"   : "top"
                        ,"text"      : ""
                        ,"showarrow" : false
                     }
                   ]
                 }
                 ,"config" : {
                    "showLink" : true
                    ,"plotlyServerURL": "https://chart-studio.plotly.com"
                    ,"responsive" : true
                 }
            }'
        )
        ,
        # PLOT_PR must == 1
        json_decode(
            '{
                "type" : 1
                ,"data" : [
                 ]
                 ,"layout" : {
                    "title" : "P(r)"
                    ,"font" : {
                        "color"  : "rgb(0,5,80)"
                    }
                    ,"margin" : {
                        "b" : 100
                    }
                    ,"paper_bgcolor": "white"
                    ,"plot_bgcolor": "white"
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
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                     }
                    }
                    ,"annotations" : [ 
                     {
                        "xref"       : "paper"
                        ,"yref"      : "paper"
                        ,"x"         : 0
                        ,"xanchor"   : "left"
                        ,"y"         : -0.2
                        ,"yanchor"   : "top"
                        ,"text"      : ""
                        ,"showarrow" : false
                     }
                   ]
                 }
                 ,"config" : {
                    "showLink" : true
                    ,"plotlyServerURL": "https://chart-studio.plotly.com"
                    ,"responsive" : true
                 }
            }'
        )
        ];
    }

    private function error_exit( $msg ) {
        if ( $this->exit_on_error ) {
            if ( !strlen( $msg ) ) {
                $msg = "SAS::Empty error message!";
            }
            echo '{"_message":{"icon":"toast.png","text":"' . $msg . '"}}';
            if ( $this->debug ) { echo "\n"; };
            exit;
        }
        return false;
    }
            
    private function debug_msg( $msg ) {
        if ( $this->debug ) {
            echo "$msg\n";
        }
    }

    function data_name_exists( $name ) {
        $this->debug_msg( "SAS::data_name_exists( '$name' )" );
        return isset( $this->data->$name );
    }

    function valid_type( $type ) {
        $this->debug_msg( "SAS::valid_types( $type )" );
        switch ( $type ) {
            case self::PLOT_IQ :
                return true;

            case self::PLOT_PR :
                return true;
        }            
        return false;
    }
    
    function load_file( $type, $name, $file, $tag = "" ) {
        $this->debug_msg( "SAS::load_file( $type, '$name', '$file' )" );
        $this->last_error = "";
        if ( strlen( $tag ) ) { $tag = ' ' . $tag; };
        
        if ( !$this->valid_type( $type ) ) {
            $this->last_error = "SAS: load_file() Invalid type $type";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data_name_exists( $name ) ) {
            $this->last_error = "SAS: Duplicate name '$name'";
            return $this->error_exit( $this->last_error );
        }

        if ( !file_exists( $file ) ) {
            $this->last_error = "Expected$tag file '$file' does not exist\n";
            return $this->error_exit( $this->last_error );
        }

        $this->data->$name = (object) [ 'type' => $type ];

        if ( $data = file_get_contents( $file ) ) {
            $plotin  = explode( "\n", $data );

            $this->debug_msg( "Got " . count( $plotin ) . " lines of data\n" );

            # remove blank & text & comment lines & lines that start with quotes
            $plotin = preg_grep( '/^(\s*#|\s*$|\s*[A-Za-z\'"~*])/', $plotin, PREG_GREP_INVERT );

            # any lines left
            if ( !count( $plotin ) ) {
                unset( $this->data->$name );
                $this->last_error = "File '$file' is empty\n";
                return $this->error_exit( $this->last_error );
            }

            # echo json_encode( $plotin, JSON_PRETTY_PRINT );
            
            # determine if load errors or not based upon 1st data line

            $linevals = preg_split( '/\s+/', trim( array_values( $plotin )[0] ) );

            switch( count( $linevals ) ) {
                case 0 : {
                    unset( $this->data->$name );
                    $this->last_error = "File '$file' unexpected empty data line\n";
                    return $this->error_exit( $this->last_error );
                }

                case 1 : {
                    unset( $this->data->$name );
                    $this->last_error = "File '$file' data line has only one element\n";
                    return $this->error_exit( $this->last_error );
                }

                case 2 : {
                    $this->data->$name->x = [];
                    $this->data->$name->y = [];
                    foreach ( $plotin as $linein ) {
                        $linevals = preg_split( '/\s+/', trim( $linein ) );

                        $this->data->$name->x[] = floatval($linevals[0]);
                        $this->data->$name->y[] = floatval($linevals[1]);
                    }
                }
                break;

                default : {
                    $this->data->$name->x       = [];
                    $this->data->$name->y       = [];
                    $this->data->$name->error_y = [];
                    foreach ( $plotin as $linein ) {
                        $linevals = preg_split( '/\s+/', trim( $linein ) );

                        $this->data->$name->x[]       = floatval($linevals[0]);
                        $this->data->$name->y[]       = floatval($linevals[1]);
                        $this->data->$name->error_y[] = floatval($linevals[2]);
                    }
                }
                break;

            }
        }
        
        return true;
    }

    # calc_residuals / SD using SD of $targetdata, make as $residualname
    function calc_residuals( $targetname, $fromname, $residualname ) {
        $this->debug_msg( "SAS::calc_residuals( '$targetname', '$fromname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $targetname ) ) {
            $this->last_error = "SAS::calc_residuals() curve name '$targetname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::calc_residuals() curve name '$fromname' does not exist";
            return $this->error_exit( $this->last_error );
        }
        
        if ( count( array_diff( $this->data->$targetname->x, $this->data->$fromname->x ) ) ) {
            $this->last_error = "SAS::calc_residuals() curves named '$targetname' and '$fromname' have incompatible grids";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$targetname->error_y ) ) {
            $this->last_error = "SAS::calc_residuals() curve name '$targetname' has no SDs";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$targetname->type != $this->data->$fromname->type ) {
            $this->last_error = "SAS: curves named '$targetname' and '$fromname' have differing types";
            return $this->error_exit( $this->last_error );
        }
            
        if ( $this->data_name_exists( $residualname ) ) {
            $this->last_error = "SAS:calc_residuals() curve name '$residualname' already exists";
            return $this->error_exit( $this->last_error );
        }
        
        $len1 = count( $this->data->$targetname->y );
        $len2 = count( $this->data->$targetname->error_y );
        $len3 = count( $this->data->$fromname->y );

        if ( $len1 < 2 ) {
            $this->last_error = "SAS::calc_residuals() curves have too few points";
            return $this->error_exit( $this->last_error );
        }

        if ( $len1 != $len2 ) {
            $this->last_error = "SAS::calc_residuals() curve name '$targetname' has an inconsistent number of SDs";
            return $this->error_exit( $this->last_error );
        }

        if ( $len1 != $len3 ) {
            $this->last_error = "SAS::calc_residuals() curves named '$targetname' and '$fromname' have incompatible grids only in the Y";
            return $this->error_exit( $this->last_error );
        }

        $residuals = [];

        for ( $i = 0; $i < $len1; ++$i ) {
            if ( $this->data->$targetname->error_y[ $i ] == 0 ) {
                $this->last_error = "SAS::nchi2() curve named '$targetname' has a zero SD at pos $i";
                return $this->error_exit( $this->last_error );
            }                
            $residuals[ $i ] = ( $this->data->$targetname->y[$i] - $this->data->$fromname->y[$i] ) / $this->data->$targetname->error_y[ $i ];
        }

        $this->data->$residualname       = (object)[];
        $this->data->$residualname->type = $this->data->$targetname->type;
        $this->data->$residualname->x    = unserialize( serialize( $this->data->$targetname->x ) );
        $this->data->$residualname->y    = $residuals;

        return true;
    }

    # adds an annotation to a plot
    function annotate_plot( $plotname, $msg ) {

        if ( !isset( $this->plots->$plotname ) ) {
            $this->last_error = "annotate_plot() '$names' does not exist\n";
            return $this->error_exit( $this->last_error );
        }

        $this->plots->$plotname->layout->annotations[0]->text = $msg;

        return true;
    }

    # creates a plot from an existing plot
    function create_plot_from_plot( $type, $name, $org_plot ) {
        $this->debug_msg( "SAS::create_plot( $type, '$name', files )" );

        if ( !$this->valid_type( $type ) ) {
            $this->last_error = "SAS: create_plot_from_plot() Invalid type $type";
            return $this->error_exit( $this->last_error );
        }

        if ( isset( $this->plots->$name ) ) {
            $this->last_error = "create_plot_from_plot() '$names' already exists\n";
            return $this->error_exit( $this->last_error );
        }

        ## check for duplicate data names

        foreach( $org_plot->data as $v ) {
            if ( $this->data_name_exists( $v->name ) ) {
                $this->last_error = "create_plot_from_plot() curve '$v->name' already exists as data\n";
                return $this->error_exit( $this->last_error );
            }
        }
        
        $this->plots->$name = unserialize( serialize( $org_plot ) );

        ## create data

        foreach( $this->plots->$name->data as $v ) {
            $dataname = $v->name;
            $this->data->$dataname = (object) [];
            $this->data->$dataname->type = $type;
            $this->data->$dataname->x = &$v->x;
            $this->data->$dataname->y = &$v->y;
            if ( isset( $v->error_y ) ) {
                $this->data->$dataname->error_y = &$v->error_y->array;
            }
        }

        return true;
    }

    # creates a plot object containing the specified datanames
    function create_plot( $type, $name, $datanames, $options = null ) {
        $this->debug_msg( "SAS::create_plot( $type, '$name', files )" );
        $this->last_error = "";

        if ( !$this->valid_type( $type ) ) {
            $this->last_error = "SAS: create_plot() Invalid type $type";
            return $this->error_exit( $this->last_error );
        }

        if ( isset( $this->plots->$name ) ) {
            $this->last_error = "create_plot() '$names' already exists\n";
            return $this->error_exit( $this->last_error );
        }

        if ( !count( $datanames ) ) {
            $this->last_error = "create_plot() empty datanames\n";
            return $this->error_exit( $this->last_error );
        }

        $this->plots->$name = unserialize( serialize( $this->plot_tmpl[ $type ] ) );

        if ( is_array( $options ) ) {
            foreach ( $options as $k => $v ) {
                switch ( $k ) {
                    case "title" :
                        $this->plots->$name->layout->$k = $v;
                        break;

                    case "showlegend" :
                        $this->plots->$name->layout->$k = $v;
                        break;

                    default :
                        unset( $this->plots->$name );
                        $this->last_error = "create_plot() unknown option $k";
                        return $this->error_exit( $this->last_error );
                }
            }
        }

        foreach ( $datanames as $dataname ) {
            if ( !$this->add_plot( $name, $dataname ) ) {
                return $this->error_exit( $this->last_error );
            }
        }
        return true;
    }

    # adds to existing plot object using 2nd trace (residuals plot)
    function plot_residuals( $name, $do_plot_residuals = true ) {
        $this->debug_msg( "SAS::plot_residuals( '$name' )" );
        $this->last_error = "";

        if ( !isset( $this->plots->$name ) ) {
            $this->last_error = "plot_residuals() name $name is not a plot name\n";
            return $this->error_exit( $this->last_error );
        }

        if ( $do_plot_residuals ) {
            $this->plots->$name->layout->yaxis->domain  = self::DOMAIN_YAXIS_RESID;
            $this->plots->$name->layout->yaxis2->domain = self::DOMAIN_YAXIS2_RESID;
            ## make traces visible
            foreach ( $this->plots->$name->data as $v ) {
                if ( $v->xaxis == "x2" ) {
                    $v->visible = true;
                }
            }
            ## set showticklables
            $this->plots->$name->layout->xaxis->showticklabels  = false;
            $this->plots->$name->layout->xaxis2->showticklabels = true;
            ## set axes visiblity
            $this->plots->$name->layout->xaxis2->visible = true;
            $this->plots->$name->layout->yaxis2->visible = true;
            ## set xaxis titles
            $this->plots->$name->layout->xaxis->title    = "";
            switch( $this->plots->$name->type ) {
                case self::PLOT_IQ : {
                    $this->plots->$name->layout->xaxis2->title   = self::PLOT_IQ_XAXIS_TITLE;
                }
                break;
                case self::PLOT_PR : {
                    $this->plots->$name->layout->xaxis2->title   = self::PLOT_PR_XAXIS_TITLE;
                }
                break;
                default : {
                    $this->last_error = "plot_residuals() name $name has an invalid type\n";
                    return $this->error_exit( $this->last_error );
                }
            }
        } else {
            $this->plots->$name->layout->yaxis->domain  = [ 0, 1 ];
            $this->plots->$name->layout->yaxis2->domain  = [ 0, 0.001 ];
            ## make traces invisible
            foreach ( $this->plots->$name->data as $v ) {
                if ( $v->xaxis == "x2" ) {
                    $v->visible = false;
                }
            }
            ## set showticklables
            $this->plots->$name->layout->xaxis->showticklabels  = true;
            $this->plots->$name->layout->xaxis2->showticklabels = false;
            ## set axes visiblity
            $this->plots->$name->layout->xaxis2->visible = false;
            $this->plots->$name->layout->yaxis2->visible = false;

            ## set xaxis titles
            $this->plots->$name->layout->xaxis2->title    = "";

            switch( $this->plots->$name->type ) {
                case self::PLOT_IQ : {
                    $this->plots->$name->layout->xaxis->title   = self::PLOT_IQ_XAXIS_TITLE;
                }
                break;
                case self::PLOT_PR : {
                    $this->plots->$name->layout->xaxis->title   = self::PLOT_PR_XAXIS_TITLE;
                }
                break;
                default : {
                    $this->last_error = "plot_residuals() name $name has an invalid type\n";
                    return $this->error_exit( $this->last_error );
                }
            }
        }

        return true;
    }

    # adds to existing plot object using 2nd trace (residuals plot) & turn on residuals plot
    function add_plot_residuals( $name, $dataname ) {
        $this->debug_msg( "SAS::add_plot_residuals( '$name', '$dataname' )" );
        $this->last_error = "";

        if ( $this->add_plot( $name, $dataname, 2 ) ) {
            return $this->plot_residuals( $name );
        }
        return false;
    }

    # adds to existing plot object 
    function add_plot( $name, $dataname, $trace = "" ) {
        $this->debug_msg( "SAS::add_plot( '$name', '$dataname', '$trace' )" );
        $this->last_error = "";

        if ( !isset( $this->plots->$name ) ) {
            $this->last_error = "add_plot() name $name is not a plot name\n";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$dataname ) ) {
            $this->last_error = "add_plot() dataname $dataname is not a data name\n";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->plots->$name->type != $this->data->$dataname->type ) {
            $this->last_error = "add_plot() data type does not match plot type\n";
            return $this->error_exit( $this->last_error );
        }

        $this->plots->$name->data[] =
            (object)[
                "x"        => &$this->data->$dataname->x
                ,"y"       => &$this->data->$dataname->y
                ,"type"    => "scatter"
                ,"name"    => $dataname
                ,"line"    => (object) [
                    "width" => self::WIDTH_LINE
                    # ,"color"  => "rgb(150,150,222)"
                ]
                ,"xaxis"   => "x$trace"
                ,"yaxis"   => "y$trace"
                ,"visible" => true
            ]
            ;

        error_log( "add_plot $name $dataname 5\n", 3, "logerr.txt" );

        if ( isset( $this->data->$dataname->error_y ) ) {
            end( $this->plots->$name->data )->error_y = (object)[
                "type"       => "data"
                ,"array"     => &$this->data->$dataname->error_y
                ,"visible"   => true
                ,"thickness" => self::WIDTH_LINE
                ,"width"     => self::WIDTH_ERROR_CAP
                ];
        }

        return true;
    }
    
    # returns the plotly
    function plot( $name ) {
        $this->debug_msg( "SAS::load_plot( '$name' )" );
        $this->last_error = "";

        if ( $this->plots->$name ) {
            return $this->plots->$name;
        }

        $this->last_error = "plot() plot name '$name' does not existn";
        return $this->error_exit( $this->last_error );
    }

    ## interpolate $fromname to $toname's grid resulting in $destname
    function interpolate( $fromname, $toname, $destname ) {
        $this->debug_msg( "SAS::interpolate( '$fromname', '$toname', '$destname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS: curve name '$fromname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $toname ) ) {
            $this->last_error = "SAS: curve name '$toname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data_name_exists( $destname ) ) {
            $this->last_error = "SAS: curve name '$destname' already exists";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$fromname->type != $this->data->$toname->type ) {
            $this->last_error = "SAS: curves named '$fromname' and '$toname' have differing types";
            return $this->error_exit( $this->last_error );
        }
            
        ## build up interpolate object for us_saxs_cmds_t json usage
        ## getconf ARG_MAX could be checked for size, but currently 2505728 on a ubuntu 20.04 container

        $cmdarg =
            '{"interpolate":1'
                  . ',"from_x":' . json_encode( $this->data->$fromname->x )
                  . ',"from_y":' . json_encode( $this->data->$fromname->y )
                  . ( $this->data->$fromname->y ? ',"from_e":' . json_encode( $this->data->$fromname->error_y ) : '' )
                  . ',"to_x":' . json_encode( $this->data->$toname->x )
                  . '}'
            ;

        $cmd = "/ultrascan3/us_somo/bin64/us_saxs_cmds_t json '$cmdarg' 2>&1";

        # file_put_contents( "temp_cmdarg.txt", $cmdarg );
        # file_put_contents( "temp_cmd.sh", $cmd );

        $res = run_cmd( $cmd );
        
        # file_put_contents( "temp_cmd_result.txt", $res );

        if ( null === ( $resobj = json_decode( $res ) ) ) {
            $this->last_error = "SAS: error interpolating curve '$fromname' to '$toname' - invalid JSON returned";
            return $this->error_exit( $this->last_error );
        }

        if ( isset( $resobj->errors ) ) {
            $this->last_error = "SAS: error interpolating curve '$fromname' to '$toname' - $resobj->errors";
            return $this->error_exit( $this->last_error );
        }

        ## create new curve, the interpolated one

        $this->data->$destname = (object)[];
        $this->data->$destname->type = $this->data->$toname->type;
        ## do we need this unserialize/serialize ?
        $this->data->$destname->x    = unserialize( serialize( $this->data->$toname->x ) );
        $this->data->$destname->y    = $resobj->to_y;
        if ( isset( $resobj->to_e ) ) {
            $this->data->$destname->error_y    = $resobj->to_e;
        }

        return true;
    }

    # rmsd - calculate rmsd
    function rmsd( $name1, $name2, &$rmsd ) {
        $this->debug_msg( "SAS::rmsd( '$name1', '$name2' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name1 ) ) {
            $this->last_error = "SAS::rmsd() curve name '$name1' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $name2 ) ) {
            $this->last_error = "SAS::rmsd() curve name '$name2' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( count( array_diff( $this->data->$name1->x, $this->data->$name2->x ) ) ) {
            $this->last_error = "SAS::rmsd() curves named '$name1' and '$name2' have incompatible grids";
            return $this->error_exit( $this->last_error );
        }

        $len1 = count( $this->data->$name1->y );
        $len2 = count( $this->data->$name2->y );

        if ( $len1 != $len2 ) {
            $this->last_error = "SAS::rmsd() curves named '$name1' and '$name2' have incompatible grids only in the Y";
            return $this->error_exit( $this->last_error );
        }
            
        $msd = 0;
        
        for ( $i = 0; $i < $len1; ++$i ) {
            $msd += pow( $this->data->$name1->y[$i] - $this->data->$name2->y[$i], 2 );
        }

        $rmsd = sqrt( $msd );
        return true;
    }

    # nchi2 - calculate chi2, assumes $targetname has the SDs
    function nchi2( $targetname, $fromname, &$chi2 ) {
        $this->debug_msg( "SAS::nchi2( '$targetname', '$fromname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $targetname ) ) {
            $this->last_error = "SAS::nchi2() curve name '$targetname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::nchi2() curve name '$fromname' does not exist";
            return $this->error_exit( $this->last_error );
        }
        
        if ( count( array_diff( $this->data->$targetname->x, $this->data->$fromname->x ) ) ) {
            $this->last_error = "SAS::nchi2() curves named '$targetname' and '$fromname' have incompatible grids";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$targetname->error_y ) ) {
            $this->last_error = "SAS::nchi2() curve name '$targetname' has no SDs";
            return $this->error_exit( $this->last_error );
        }
        
        $len1 = count( $this->data->$targetname->y );
        $len2 = count( $this->data->$targetname->error_y );
        $len3 = count( $this->data->$fromname->y );

        if ( $len1 < 2 ) {
            $this->last_error = "SAS::nchi2() curves have too few points";
            return $this->error_exit( $this->last_error );
        }

        if ( $len1 != $len2 ) {
            $this->last_error = "SAS::nchi2() curve name '$targetname' has an inconsistent number of SDs";
            return $this->error_exit( $this->last_error );
        }

        if ( $len1 != $len3 ) {
            $this->last_error = "SAS::nchi2() curves named '$targetname' and '$fromname' have incompatible grids only in the Y";
            return $this->error_exit( $this->last_error );
        }

        $chi2 = 0;
        
        for ( $i = 0; $i < $len1; ++$i ) {
            if ( $this->data->$targetname->error_y[ $i ] == 0 ) {
                $this->last_error = "SAS::nchi2() curve named '$targetname' has a zero SD at pos $i";
                return $this->error_exit( $this->last_error );
            }                
            $chi2 += pow( ( $this->data->$targetname->y[$i] - $this->data->$fromname->y[$i] ) / $this->data->$targetname->error_y[ $i ] , 2 );
        }

        $chi2 /= $len1 - 1;

        return true;
    }

    # scale_nchi2 - calculate chi2 & scale, assumes $targetname has the SDs
    function scale_nchi2( $targetname, $fromname, $destname, &$chi2, &$scale ) {
        $this->debug_msg( "SAS::scale_nchi2( '$targetname', '$fromname', '$destname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $targetname ) ) {
            $this->last_error = "SAS::scale_nchi2() curve name '$targetname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::scale_nchi2() curve name '$fromname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data_name_exists( $destname ) ) {
            $this->last_error = "SAS::scale_nchi2() curve name '$destname' exists";
            return $this->error_exit( $this->last_error );
        }
        
        if ( count( array_diff( $this->data->$targetname->x, $this->data->$fromname->x ) ) ) {
            $this->last_error = "SAS::scale_nchi2() curves named '$targetname' and '$fromname' have incompatible grids";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$targetname->error_y ) ) {
            $this->last_error = "SAS::scale_nchi2() curve name '$targetname' has no SDs";
            return $this->error_exit( $this->last_error );
        }
        
        $len1 = count( $this->data->$targetname->y );
        $len2 = count( $this->data->$targetname->error_y );
        $len3 = count( $this->data->$fromname->y );

        if ( $len1 < 2 ) {
            $this->last_error = "SAS::scale_nchi2() curves have too few points";
            return $this->error_exit( $this->last_error );
        }

        if ( $len1 != $len2 ) {
            $this->last_error = "SAS::scale_nchi2() curve name '$targetname' has an inconsistent number of SDs";
            return $this->error_exit( $this->last_error );
        }

        if ( $len1 != $len3 ) {
            $this->last_error = "SAS::scale_nchi2() curves named '$targetname' and '$fromname' have incompatible grids only in the Y";
            return $this->error_exit( $this->last_error );
        }

        ## compute scale

        $scale = 0e0;

        $Sxx = 0e0;
        $Sxy = 0e0;

        $oneoversd2 = [];

        for ( $i = 0; $i < $len1; ++$i ) {
            if ( $this->data->$targetname->error_y[ $i ] == 0 ) {
                $this->last_error = "SAS::scale_nchi2() curve named '$targetname' has a zero SD at pos $i";
                return $this->error_exit( $this->last_error );
            }                
            $oneoversd2[ $i ] =  1.0 / pow( $this->data->$targetname->error_y[ $i ], 2 );
            $Sxx += $this->data->$fromname->y[ $i ] * $this->data->$fromname->y[ $i ] * $oneoversd2[ $i ];
            $Sxy += $this->data->$fromname->y[ $i ] * $this->data->$targetname->y[ $i ] * $oneoversd2[ $i ];
        }
        
        if ( $Sxx != 0 ) {
            $scale = $Sxy / $Sxx;
        } else {
            $scale = 1e0;
        }
        
        $chi2 = 0;
        
        for ( $i = 0; $i < $len1; ++$i ) {
            $chi2 += $oneoversd2[ $i ] *
                pow( ( $scale * $this->data->$fromname->y[$i] - $this->data->$targetname->y[$i] ), 2 );
        }

        $chi2 /= $len1 - 1;

        ## create scaled curve

        $this->data->$destname = (object)[];
        $this->data->$destname->type = $this->data->$fromname->type;
        ## do we need this unserialize/serialize ?
        $this->data->$destname->x    = unserialize( serialize( $this->data->$fromname->x ) );
        $this->data->$destname->y    = unserialize( serialize( $this->data->$fromname->y ) );
        if ( isset( $this->data->$fromname->error_y ) ) {
            $this->data->$destname->error_y    = unserialize( serialize( $this->data->$fromname->error_y ) );
            for ( $i = 0; $i < $len1; ++$i ) {
                $this->data->$destname->y[ $i ]       *= $scale;
                $this->data->$destname->error_y[ $i ] *= $scale;
            }
        } else {
            for ( $i = 0; $i < $len1; ++$i ) {
                $this->data->$destname->y[ $i ]       *= $scale;
            }
        }

        return true;
    }

    function common_grid( $name1, $name2 ) {
        $this->debug_msg( "SAS::common_grid( '$name1', '$name2' )" );
        $this->last_error = "";
        $this->last_error = "SAS::common_grid() not yet implemented!";
        return $this->error_exit( $this->last_error );
    }

    function dump_data( $pretty = true ) {
        $this->debug_msg( "SAS::dump_data()" );
        
        return
            "\$data =\n"
            . ( $pretty
                ? json_encode( $this->data, JSON_PRETTY_PRINT )
                : json_encode( $this->data )
            )
            . "\n"
            ;

    }

    function dump_plots( $pretty = true ) {
        $this->debug_msg( "SAS::dump_plots()" );

        return
            "\$plots =\n"
            . ( $pretty
                ? json_encode( $this->plots, JSON_PRETTY_PRINT )
                : json_encode( $this->plots )
                )
            . "\n"
            ;
    }


    function dump( $pretty = true ) {
        $this->debug_msg( "SAS::dump()" );
        return
            $this->dump_data( $pretty )
            . $this->dump_plots( $pretty )
            ;
    }
}

## testing
# $do_testing = true;

if ( isset( $do_testing ) ) {
    $sas = new SAS( true );

    $iqfile     = 'waxsis/lyzexp.dat';
    $waxsisfile = 'waxsis/intensity_waxsis.calc';
    
    $chi2  = -1;
    $rmsd  = -1;
    $scale = 0;

    if (
        $sas->load_file( SAS::PLOT_IQ, "Exp. I(q)", $iqfile  )
        && $sas->load_file( SAS::PLOT_IQ, "WAXSiS_org", $waxsisfile  )
        && $sas->interpolate( "WAXSiS_org", "Exp. I(q)", "WAXSiS_interp" )
        && $sas->scale_nchi2( "Exp. I(q)", "WAXSiS_interp", "WAXSiS", $chi2, $scale )
        && $sas->rmsd( "Exp. I(q)", "WAXSiS", $rmsd )
        ) {
        echo "ok\n";
    } else {
        error_exit( $sas->last_error );
    }

    $plotname = "I(q)";

    $sas->create_plot(
        SAS::PLOT_IQ
        ,$plotname
        ,[
            "Exp. I(q)"
            ,"WAXSiS"
        ]
        );

    $rmsd = round( $rmsd, 3 );
    $chi2 = round( $chi2, 3 );
    $annotate_msg = "";
    if ( $rmsd != -1 ) {
        $annotate_msg .= "RMSD $rmsd   ";
    }
    if ( $chi2 != -1 ) {
        $annotate_msg .= "nChi^2 $chi2   ";
    }

    if ( strlen( $annotate_msg ) ) {
        $sas->annotate_plot( "I(q)", $annotate_msg );
    }
    
    $sas->calc_residuals( "Exp. I(q)", "WAXSiS", "Res./SD" );
    $sas->add_plot_residuals( $plotname, "Res./SD" );

    $sas->plot_residuals( $plotname, false );
    
    file_put_contents( "dump_data.json", $sas->dump_data() );
    file_put_contents( "dump_plots.json", json_encode( $sas->plot( $plotname ), JSON_PRETTY_PRINT ) );

    $outfile = "plotout.json";
    file_put_contents( $outfile, "\n" .  json_encode( $sas->plot( $plotname ) ) . "\n\n" );
    echo "cat $outfile\n";
}
