<?php
{};

## a class for managing Iq, Pr NNLS and Plotly

include_once "common.php";
include_once "limits.php";

class SAS {

    public $last_error;

    private $data;
    private $plots;
    private $debug;
    private $exit_on_error;
    private $scriptdir;


    const WIDTH_LINE            = 1;
    const WIDTH_ERROR_CAP       = 1;
    const DOMAIN_YAXIS_RESID    = [ 0.25, 1 ];
    const DOMAIN_YAXIS2_RESID   = [ 0, 0.21 ];
    const PLOT_IQ_XAXIS_TITLE   = "q [&#8491;<sup>-1</sup>]";
    const PLOT_PR_XAXIS_TITLE   = "Distance [&#8491;]";
    const PR_MIN_ERRORS_MULT    = 1e-2;
    const PLOTLY_COLORS         = [
        '#1f77b4'  # muted blue
        ,'#ff7f0e'  # safety orange
        ,'#2ca02c'  # cooked asparagus green
        ,'#d62728'  # brick red
        ,'#9467bd'  # muted purple
        ,'#8c564b'  # chestnut brown
        ,'#e377c2'  # raspberry yogurt pink
        ,'#7f7f7f'  # middle gray
        ,'#bcbd22'  # curry yellow-green
        ,'#17becf'  # blue-teal
        ];

    ## php 8.1 has enum, should eventually replace

    const PLOT_IQ    = 0;
    const PLOT_PR    = 1;
    
    private $plot_tmpl;
    
    ## n.b. had to remove layout:yaxis:title:standoff:20 as it didn't seem be be honored for the subplot

    function __construct( $debug = false, $exit_on_error = true ) {
        $this->debug         = $debug;
        $this->exit_on_error = $exit_on_error;
        $this->scriptdir     = dirname(__FILE__);
        
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
                    "title" : {
                         "text" : "I(q)"
                    }
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
                       ,"type" : "log"
                       ,"title" : {
                           "text" : "q [&#8491;<sup>-1</sup>]"
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                       }
                       ,"showticklabels" : true
                       ,"showline"       : true
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
                       ,"showline"       : true
                    }
                    ,"xaxis2" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "log"
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
                       ,"showline"       : true
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
                       ,"visible"        : false
                       ,"showline"       : true
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
                    "title" : {
                         "text" : "P(r)"
                    }
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
                       ,"showticklabels" : true
                       ,"showline"       : false
                    }
                    ,"yaxis" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"title" : {
                           "text" : "Frequency a.u."
                            ,"font" : {
                                "color"  : "rgb(0,5,80)"
                            }
                        }
                       ,"showline"       : false
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
                       ,"showline"       : true
                    }
                    ,"yaxis2" : {
                       "gridcolor" : "rgba(111,111,111,0.5)"
                       ,"type" : "linear"
                       ,"title" : {
                           "text" : "Resid."
                           ,"font" : {
                               "color"  : "rgb(0,5,80)"
                           }
                       }
                       ,"visible"       : false
                       ,"showline"      : false
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
        ];
    }

    private function error_exit( $msg ) {
        if ( $this->exit_on_error ) {
            if ( !strlen( $msg ) ) {
                $msg = "SAS::Empty error message!";
            }
            ## replace newline with <br>
            $msg = str_replace( "\n", "<br>", $msg );
            ## replace quote with escaped quote
            $msg = str_replace( '"', "\\\"", $msg );
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

    function debug_json( $tag, $obj ) {
        if ( $this->debug ) {
            echo "$tag:\n" . json_encode( $obj, JSON_PRETTY_PRINT ) . "\n";
        }
    }

    # does the data have errors
    function data_has_errors( $name ) {
        $this->debug_msg( "SAS::data_has_errors( '$name' )" );
        return isset( $this->data->$name );

        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::data_has_errors() name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$name->error_y ) ) {
            return false;
        }

        if ( max( $this->data->$name->error_y ) == 0 ) {
            return false;
        }

        return true;
    }

    function data_name_exists( $name ) {
        $this->debug_msg( "SAS::data_name_exists( '$name' )" );
        return isset( $this->data->$name );
    }

    function plots_name_exists( $name ) {
        $this->debug_msg( "SAS::plots_name_exists( '$name' )" );
        return isset( $this->plots->$name );
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
    
    function significant_digits( $value ) {
        global $significant_digits_to_use;

        if ( !$significant_digits_to_use ) {
            return $value;
        }

        if ($value == 0) {
            $decimalPlaces = $significant_digits_to_use - 1;
        } elseif ($value < 0) {
            $decimalPlaces = $significant_digits_to_use - floor(log10($value * -1)) - 1;
        } else {
            $decimalPlaces = $significant_digits_to_use - floor(log10($value)) - 1;
        }

        return floatval( ($decimalPlaces > 0)
                         ? number_format($value, $decimalPlaces, '.', '') : round($value, $decimalPlaces) );
    }

    # save_file

    function save_file( $name, $file ) {
        $this->debug_msg( "SAS::save_file( '$name', '$file' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::save_file() curve named '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        $contents = "# SAXSAFOLD produced data file\n";
        
        if ( isset( $this->data->$name->error_y ) ) {
            if ( $this->data->$name->type == self::PLOT_PR ) {
                $contents .= "# r\tP(r)\tSD\n";
            } else {
                $contents .= "# q\tI(q)\tSD\n";
            }
            for ( $i = 0; $i < count( $this->data->$name->x ); ++$i ) {
                $contents .=
                    sprintf( "%f\t%f\t%f\n"
                             ,$this->data->$name->x[$i]
                             ,$this->data->$name->y[$i]
                             ,$this->data->$name->error_y[$i]
                    );
            }
        } else {
            if ( $this->data->$name->type == self::PLOT_PR ) {
                $contents .= "# r\tP(r)\n";
            } else {
                $contents .= "# qr\tI(q)\n";
            }

            for ( $i = 0; $i < count( $this->data->$name->x ); ++$i ) {
                $contents .=
                    sprintf( "%f\t%f\n"
                             ,$this->data->$name->x[$i]
                             ,$this->data->$name->y[$i]
                    );
            }
        }

        if ( !file_put_contents( $file, $contents ) ) {
            $this->last_error = "SAS::save_file() error writing file '$file'";
            return $this->error_exit( $this->last_error );
        }

        return true;
    }

    # load somo csv file as data
    function load_somo_csv_file( $type, $prefix, $file, $includeSDs = true, $tag = "" ) {
        $this->debug_msg( "SAS::load_somo_csv_file( $type, '$prefix', '$file' )" );
        $this->last_error = "";
        if ( strlen( $tag ) ) { $tag = ' ' . $tag; };

        if ( !$this->valid_type( $type ) ) {
            $this->last_error = "SAS: load_somo_csv_file() Invalid type $type";
            return $this->error_exit( $this->last_error );
        }

        if ( $type != self::PLOT_IQ ) {
            $this->last_error = "SAS: load_somo_csv_file() only type PLOT_IQ currently supported";
            return $this->error_exit( $this->last_error );
        }

        if ( !file_exists( $file ) ) {
            $this->last_error = "Expected$tag file '$file' does not exist\n";
            return $this->error_exit( $this->last_error );
        }

        if ( !($data = file_get_contents( $file ) ) ) {
            $this->last_error = "Expected$tag file '$file' can not be read\n";
            return $this->error_exit( $this->last_error );
        }
            
        $lines = explode( "\n", $data );

        if ( !count( $lines ) ) {
            $this->last_error = "File '$file' has no lines\n";
            return $this->error_exit( $this->last_error );
        }
            
        if ( !preg_match( '/^"Name","Type; q:",/', $lines[0] ) ) {
            $this->last_error = "File '$file' does not have the correct format on line 1";
            return $this->error_exit( $this->last_error );
        }

        $linedata = explode( ",", $lines[0] );
        ## remove 1st 2 and last 2 elements
        array_shift( $linedata );
        array_shift( $linedata );
        array_pop( $linedata );
        array_pop( $linedata );
        
        $x_values = array_map( 'self::significant_digits', array_map( 'floatval', $linedata ) );

        $this->debug_json( "x_values", $x_values );

        array_shift( $lines );

        ## cache results before storing to enable checking for duplicates etc
        $cache = (object)[];

        foreach ( $lines as $line ) {
            $line = str_replace( '"', '', $line );
            $linedata = explode( ",", $line );

            if ( count( $linedata ) < 3 ) {
                ## skip short lines
                continue;
            }

            $name   = "$prefix" . array_shift( $linedata );
            $dtype  = array_shift( $linedata );
            $values = array_map( 'self::significant_digits', array_map( 'floatval', $linedata ) );

            if ( $this->data_name_exists( $name ) ) {
                $this->last_error = "Error when loading data from $file, Duplicate data name '$name'";
                return $this->error_exit( $this->last_error );
            }

            if ( !isset( $cache->$name ) ) {
                $cache->$name = (object)[];
            }
            
            switch ( $dtype ) {
                case 'I(q)' :
                {
                    if ( isset( $cache->$name->y ) ) {
                        $this->last_error = "Error when loading data from $file, Duplicate row data '$name'";
                        return $this->error_exit( $this->last_error );
                    }
                    $cache->$name->y = $values;
                }
                break;

                case 'I(q) sd' :
                {
                    if ( isset( $cache->$name->error_y ) ) {
                        $this->last_error = "Error when loading data from $file, Duplicate row SD data '$name'";
                        return $this->error_exit( $this->last_error );
                    }
                    $cache->$name->error_y = $values;
                }
                break;

                default :
                {
                    $this->last_error = "Error when loading data from $file, Unknown data type '$dtype' encountered";
                    return $this->error_exit( $this->last_error );
                }
                break;
            }
        }                

        ## SDs and no data check

        foreach ( $cache as $name => $v ) {
            if ( !isset( $v->y ) ) {
                $this->last_error = "Error when loading data from $file, Data for '$name' has only SDs";
                return $this->error_exit( $this->last_error );
            }
        }

        ## all ok, add data
        foreach ( $cache as $name => $v ) {
            $this->data->$name = (object) [ 'type' => $type ];
            $this->data->$name->x = unserialize( serialize( $x_values ) );
            $this->data->$name->y = $v->y;
            if ( isset( $v->error_y ) ) {
                $this->data->$name->error_y = $v->error_y;
            }
        }

        return true;
    }

    # load file as data
    function load_file( $type, $name, $file, $includeSDs = true, $tag = "" ) {
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

            if ( FALSE !== strpos( $data, "G N O M" ) ) {
                ## GNOM file, skip to PR section

                $plotin_pr = [];

                $found_pr = false;
                foreach ( $plotin as $line ) {
                    if ( $found_pr ) {
                        $plotin_pr[] = $line;
                        continue;
                    }
                    if ( FALSE != strpos( $line, 'Distance distribution' ) ) {
                        $found_pr = true;
                    }
                }

                $plotin = $plotin_pr;
            }

            # remove blank & text & comment lines & lines that start with quotes
            $plotin = preg_grep( '/^(\s*--|\s*#|\s*$|\s*[A-Za-z\'"~*])/', $plotin, PREG_GREP_INVERT );

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

                        $this->data->$name->x[] = $this->significant_digits( floatval($linevals[0]) );
                        $this->data->$name->y[] = $this->significant_digits( floatval($linevals[1]) );
                    }
                }
                break;

                default : {
                    $this->data->$name->x       = [];
                    $this->data->$name->y       = [];
                    $this->data->$name->error_y = [];
                    foreach ( $plotin as $linein ) {
                        $linevals = preg_split( '/\s+/', trim( $linein ) );

                        $this->data->$name->x[]       = $this->significant_digits( floatval($linevals[0]) );
                        $this->data->$name->y[]       = $this->significant_digits( floatval($linevals[1]) );
                        $this->data->$name->error_y[] = $this->significant_digits( floatval($linevals[2]) );
                    }
                    if ( !$includeSDs ) {
                        ## remove SDs ... this is needed for .sprr files which have a nonSD value in the 3rd column
                        unset( $this->data->$name->error_y );
                    }
                }
                break;
            }
        }
        
        return true;
    }

    # maxq / sets $q argument to maxq
    function maxq( $name, &$q ) {
        $this->debug_msg( "SAS::maxq( '$name' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::maxq() curve name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$name->type != self::PLOT_IQ ) {
            $this->last_error = "SAS::maxq() curve name '$name' is not an I(q)";
            return $this->error_exit( $this->last_error );
        }

        if ( !count( $this->data->$name->x ) ) {
            $this->last_error = "SAS::maxq() curve name '$name' has no data";
            return $this->error_exit( $this->last_error );
        }
        
        $q = end( $this->data->$name->x );
        return true;
    }        

    # minq / sets $q argument to minq
    function minq( $name, &$q ) {
        $this->debug_msg( "SAS::minq( '$name' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::minq() curve name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$name->type != self::PLOT_IQ ) {
            $this->last_error = "SAS::minq() curve name '$name' is not an I(q)";
            return $this->error_exit( $this->last_error );
        }
        
        if ( !count( $this->data->$name->x ) ) {
            $this->last_error = "SAS::minq() curve name '$name' has no data";
            return $this->error_exit( $this->last_error );
        }
        
        $q = $this->data->$name->x[0];
        return true;
    }        

    # dmax / sets $r argument to d-max from P(r)
    function dmax( $name, &$r ) {
        $this->debug_msg( "SAS::dmax( '$name' )" );
        $this->last_error = "";
        
        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::dmax() curve name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$name->type != self::PLOT_PR ) {
            $this->last_error = "SAS::dmax() curve name '$name' is not a P(r)";
            return $this->error_exit( $this->last_error );
        }
        
        if ( !count( $this->data->$name->x ) ) {
            $this->last_error = "SAS::dmax() curve name '$name' has no data";
            return $this->error_exit( $this->last_error );
        }

        $pos = count( $this->data->$name->x ) - 1;

        while( $pos > 0 && $this->data->$name->y[ $pos ] <= 0 ) {
            --$pos;
        }

        if ( $pos >= 0 ) {
            $r = $this->data->$name->x[ $pos ];
        } else {
            $this->last_error = "SAS::dmax() curve name '$name' does not appear to have any positive values";
            return $this->error_exit( $this->last_error );
        }

        return true;
    }        

    # data_convert_nm_to_angstrom - converts data in nm to angstrom
    function data_convert_nm_to_angstrom( $name ) {
        $this->debug_msg( "SAS::data_convert_nm_to_angstrom( '$name' )" );
        $this->last_error = "";
        
        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::data_convert_nm_to_angstrom() curve name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !count( $this->data->$name->x ) ) {
            $this->last_error = "SAS::data_convert_nm_to_angstrom() curve name '$name' has no data";
            return $this->error_exit( $this->last_error );
        }
    
        switch( $this->data->$name->type ) {
            case self::PLOT_PR : {
                foreach ( $this->data->$name->x as $k => $v ) {
                    $this->data->$name->x[ $k ] *= 10;
                }
            }
            break;

            case self::PLOT_IQ : {
                foreach ( $this->data->$name->x as $k => $v ) {
                    $this->data->$name->x[ $k ] *= .1;
                }
            }
            break;

            default : {
                $this->last_error = "SAS::data_convert_nm_to_angstrom() curve name '$name' has an unknown type";
                return $this->error_exit( $this->last_error );
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

    # recolor_plot
    function recolor_plot( $name, $exclude_color_numbers = '' ) {
        $this->debug_msg( "SAS::recolor_plot( '$name', exclude_color_numbers[] )" );
        $this->last_error = "";

        if ( !$this->plots_name_exists( $name ) ) {
            $this->last_error = "SAS::recolor_plot() plot name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( is_array( $exclude_color_numbers ) ) {
            $max_colors = count( self::PLOTLY_COLORS );
            $any_valid_colors = false;
            for ( $i = 0; $i < $max_colors; ++$i ) {
                if ( !in_array( $i, $exclude_color_numbers ) ) {
                    $any_valid_colors = true;
                    break;
                }
            }
            if ( !$any_valid_colors ) {
                $this->last_error = "SAS::recolor_plot() all colors are excluded";
                return $this->error_exit( $this->last_error );
            }
            $use_color = 0;
            foreach ( $this->plots->$name->data as $v ) {
                while( in_array( $use_color % $max_colors, $exclude_color_numbers ) ) {
                    ++$use_color;
                }
                $this->plot_trace_options( $name, $v->name, [ 'linecolor_number' => $use_color ] );
                ++$use_color;
            }
        } else {            
            $use_color = 0;
            foreach ( $this->plots->$name->data as $v ) {
                $this->plot_trace_options( $name, $v->name, [ 'linecolor_number' => $use_color ] );
                ++$use_color;
            }
        }
        return true;
    }

    # adds an annotation to a plot
    function annotate_plot( $plotname, $msg, $append = false ) {
        $this->debug_msg( "SAS::annotate_plot( '$plotname', '$msg' )" );
        $this->last_error = "";

        if ( !isset( $this->plots->$plotname ) ) {
            $this->last_error = "annotate_plot() plot '$plotname' does not exist\n";
            return $this->error_exit( $this->last_error );
        }

        if ( $append && isset( $this->plots->$plotname->layout->annotations[0]->text ) ) {
            $this->plots->$plotname->layout->annotations[0]->text .= $msg;
        } else {
            $this->plots->$plotname->layout->annotations[0]->text = $msg;
        }            

        return true;
    }

    # plot_trace_options() options for individual traces
    function plot_trace_options( $name, $dataname, $options ) {
        $this->debug_msg( "SAS::plot_trace_options( '$name', '$dataname', options[] )" );
        $this->last_error = "";

        if ( !$this->plots_name_exists( $name ) ) {
            $this->last_error = "SAS::plot_trace_options() plot name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !is_array( $options ) ) {
            $this->last_error = "SAS::plot_trace_options() \$options is not an array";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->plots->$name->data ) ) {
            $this->last_error = "SAS::plot_trace_options() plot '$name' has no data";
            return $this->error_exit( $this->last_error );
        }
            
        $plotdata = false;

        foreach ( $this->plots->$name->data as $v ) {
            if ( $v->name == $dataname ) {
                $plotdata = $v;
                break;
            }
        }

        if ( !is_object( $plotdata ) ) {
            $this->last_error = "SAS::plot_trace_options() plot '$name' has not trace name '$dataname'";
            return $this->error_exit( $this->last_error );
        }

        foreach ( $options as $k => $v ) {
            switch ( $k ) {
                case "linecolor" : {
                    $plotdata->line->color = $v;
                }
                break;
                case "linecolor_number" : {
                    $plotdata->line->color = self::PLOTLY_COLORS[ $v % count( self::PLOTLY_COLORS ) ];
                }
                break;

                default : {
                    $this->last_error = "SAS::plot_trace_options() option '$k' unknown";
                    return $this->error_exit( $this->last_error );
                }
            }
        }
                    
        return true;
    }   
    # plot_options() sets variuos plot options
    function plot_options( $name, $options ) {
        $this->debug_msg( "SAS::plot_options( '$name', options[] )" );
        $this->last_error = "";

        if ( !$this->plots_name_exists( $name ) ) {
            $this->last_error = "SAS::plot_options() plot name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !is_array( $options ) ) {
            $this->last_error = "SAS::plot_options() \$options is not an array";
            return $this->error_exit( $this->last_error );
        }
            
        foreach ( $options as $k => $v ) {
            switch ( $k ) {
                case "title" :
                    if ( !is_object( $this->plots->$name->layout->title ) ) {
                        $this->plots->$name->layout->title = (object) [];
                    }
                    $this->plots->$name->layout->title->text = $v;
                    break;

                case "showlegend" :
                    $this->plots->$name->layout->$k = $v;
                    break;

                case "showeditchart" :
                    $this->plots->$name->config->showLink = $v;
                    break;

                case "yaxistitle" :
                    $this->plots->$name->layout->yaxis->title->text = $v;
                    break;
                
                case "yaxis2title" :
                    $this->plots->$name->layout->yaxis2->title->text = $v;
                    break;
                
                case "titlefontsize" :
                    if ( !is_object( $this->plots->$name->layout->title ) ) {
                        $this->plots->$name->layout->title = (object) [ "text" => $this->plots->$name->layout->title ];
                    }
                    if ( !isset( $this->plots->$name->layout->title->font ) ) {
                        $this->plots->$name->layout->title->font = (object)[];
                    }                        
                    $this->plots->$name->layout->title->font->size = $v;
                    break;

                default :
                    $this->last_error = "plot_options() unknown option $k";
                    return $this->error_exit( $this->last_error );
            }
        }

        return true;

    }

    # creates a plot from an existing plot
    function create_plot_from_plot( $type, $name, $org_plot, $options = null ) {
        $this->debug_msg( "SAS::create_plot( $type, '$name', files )" );
        $this->last_error = "";

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
            if ( $this->data_name_exists( $v->name )
                ) {

                ## check if duplicate data, if so, allow
                $tmpname = $v->name;
                if (
                    $this->data->$tmpname->type == $type
                    && $this->data->$tmpname->x == $v->x
                    && $this->data->$tmpname->y == $v->y
                    &&
                    (
                     (
                      !isset( $this->data->$tmpname->error_y )
                      && !isset( $v->error_y )
                     )
                     ||
                     (
                      isset( $this->data->$tmpname->error_y )
                      && isset( $v->error_y )
                      && isset( $v->error_y->array )
                      && $this->data->$tmpname->error_y == $v->error_y->array
                     )
                    )
                    ) {
                    ## ok to reuse
                } else {
                    $this->last_error = "create_plot_from_plot() curve '$v->name' already exists as data\n";
                    return $this->error_exit( $this->last_error );
                }
            }
        }
        
        $this->plots->$name = unserialize( serialize( $org_plot ) );

        if ( is_array( $options ) ) {
            if ( !$this->plot_options( $name, $options ) ) {
                unset( $this->plots->$name );
                return $this->error_exit( $this->last_error );
            }
        }

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

    # set_pr_error_y_nonzero - what it says ;)
    function set_pr_error_y_nonzero( $name ) {
        $this->debug_msg( "SAS::set_pr_error_y_nonzero( '$name' )" );
        $this->last_error = "";
        
        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::set_pr_error_y_nonzero() name '$name' is not a data name\n";
            return $this->error_exit( $this->last_error );
        }
        
        if ( $this->data->$name->type != self::PLOT_PR ) {
            $this->last_error = "SAS::set_pr_error_y_nonzero() name '$name' is not a PR type\n";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$name->error_y )
             || !count( $this->data->$name->error_y )
            ) {
            return true; # it's ok, the curve has no errors so nothing to do
            # $this->last_error = "SAS::set_pr_error_y_nonzero() name '$name' has no SDs";
            # return $this->error_exit( $this->last_error );
        }

        $count = count( $this->data->$name->error_y );
        $min   = 1e99;

        for ( $i = 0; $i < $count; ++$i ) {
            if ( $this->data->$name->error_y[ $i ] > 0
                 && $min > $this->data->$name->error_y[ $i ] ) {
                $min = $this->data->$name->error_y[ $i ];
            }
        }

        if ( $min == 1e99 ) {
            $this->last_error = "SAS::set_pr_error_y_nonzero() name '$name' has no positive SDs";
            return $this->error_exit( $this->last_error );
        }
            
        $newminSD = $min * self::PR_MIN_ERRORS_MULT;

        for ( $i = 0; $i < $count; ++$i ) {
            if ( $this->data->$name->error_y[ $i ] <= 0 ) {
                $this->data->$name->error_y[ $i ] = $newminSD;
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
            if ( !$this->plot_options( $name, $options ) ) {
                unset( $this->plots->$name );
                return $this->error_exit( $this->last_error );
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

    # copy data 
    function copy_data( $fromname, $toname, $deep = false ) {
        $this->debug_msg( "SAS::copy_data( '$fromname', '$toname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::copy_data() name $fromname is not a data name\n";
            return $this->error_exit( $this->last_error );
        }
        
        if ( $this->data_name_exists( $toname ) ) {
            $this->last_error = "SAS::copy_data() name $toname already exists\n";
            return $this->error_exit( $this->last_error );
        }
        
        if ( $deep ) {
            $this->data->$toname = unserialize( serialize( $this->data->$fromname ) );
        } else {
            $this->data->$toname = $this->data->$fromname;
        }

        return true;
    }

    # regex rename data 
    function regex_rename_data( $names, $regex_from, $regex_to ) {
        $this->debug_msg( "SAS::regex_rename_data( \$names[], '$regex_from', '$regex_to' )" );
        $this->last_error = "";

        if ( !is_array( $names ) ) {
            $names = [ $names ];
        }

        foreach ( $names as $name ) {
            $toname = preg_replace( $regex_from, $regex_to, $name );
            if ( !$this->rename_data( $name, $toname ) ) {
                return false;
            }
        }

        return true;
    }

    # rename data 
    function rename_data( $fromname, $toname ) {
        $this->debug_msg( "SAS::rename_data( '$fromname', '$toname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::rename_data() name $fromname is not a data name\n";
            return $this->error_exit( $this->last_error );
        }
        
        if ( $this->data_name_exists( $toname ) ) {
            $this->last_error = "SAS::rename_data() name $toname already exists\n";
            return $this->error_exit( $this->last_error );
        }
        
        $this->data->$toname = $this->data->$fromname;

        unset( $this->data->$fromname );
        return true;
    }

    # remove data if exists - string or array
    function remove_data_if_exists( $names ) {
        $this->debug_msg( "SAS::remove_data_if_exists( names[] )" );
        $this->last_error = "";

        if ( !is_array( $names ) ) {
            $names = [ $names ];
        }

        foreach ( $names as $name ) {
            if ( $this->data_name_exists( $name ) ) {
                $this->remove_data( $name );
            }
        }

        return true;
    }

    # remove data - string or array
    function remove_data( $names ) {
        $this->debug_msg( "SAS::remove_data( names[] )" );
        $this->last_error = "";

        if ( !is_array( $names ) ) {
            $names = [ $names ];
        }

        foreach ( $names as $name ) {
            if ( !$this->data_name_exists( $name ) ) {
                $this->last_error = "SAS::remove_data() name '$name' is not a data name\n";
                return $this->error_exit( $this->last_error );
            }
            unset( $this->data->$name );
        }

        return true;
    }

    # remove data/trace from plot 
    function remove_plot_data( $name, $dataname ) {
        $this->debug_msg( "SAS::remove_plot_data( '$name', '$dataname' )" );
        $this->last_error = "";

        if ( !$this->plots_name_exists( $name ) ) {
            $this->last_error = "SAS::remove_plot_data() name $name is not a plot name\n";
            return $this->error_exit( $this->last_error );
        }
        
        foreach ( $this->plots->$name->data as $k => $v ) {
            if ( $v->name == $dataname ) {
                unset( $this->plots->$name->data[ $k ] );
                $this->plots->$name->data = array_values( $this->plots->$name->data );
                return true;
            }
        }

        $this->last_error = "SAS::remove_plot_data() name $dataname not found in plot $plot\n";
        return $this->error_exit( $this->last_error );
    }

    # remove plot
    function remove_plot( $name ) {
        $this->debug_msg( "SAS::remove_plot( '$name' )" );
        $this->last_error = "";

        if ( !$this->plots_name_exists( $name ) ) {
            $this->last_error = "SAS::remove_plot() name $name is not a plot name\n";
            return $this->error_exit( $this->last_error );
        }
        
        unset( $this->plots->$name );
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

        if ( !isset( $this->plots->$name->type ) ) {
            $this->last_error = "add_plot() plot $name does not have a type defined\n";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $this->data->$dataname->type ) ) {
            $this->last_error = "add_plot() data '$dataname' does not have a type defined\n";
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

        if ( $this->plots_name_exists( $name ) ) {
            return $this->plots->$name;
        }

        $this->last_error = "plot() plot name '$name' does not exist";
        return $this->error_exit( $this->last_error );
    }

    ## interpolate $fromname to $toname's grid resulting in $destname
    function interpolate( $fromname, $toname, $destname ) {
        $this->debug_msg( "SAS::interpolate( '$fromname', '$toname', '$destname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::interpolate() curve name '$fromname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $toname ) ) {
            $this->last_error = "SAS::interpolate() curve name '$toname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data_name_exists( $destname ) ) {
            $this->last_error = "SAS::interpolate() curve name '$destname' already exists";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$fromname->type != $this->data->$toname->type ) {
            $this->last_error = "SAS::interpolate() curves named '$fromname' and '$toname' have differing types";
            return $this->error_exit( $this->last_error );
        }
            
        ## build up interpolate object for us_saxs_cmds_t json usage
        ## getconf ARG_MAX could be checked for size, but currently 2505728 on a ubuntu 20.04 container

        ## special padding for P(r) curve to allow extrapolation
        if ( $this->data->$toname->type == self::PLOT_PR
             && (
                 end( $this->data->$fromname->x ) != end( $this->data->$toname->x )
                 || $this->data->$fromname->x[0] > $this->data->$toname->x[0]
                 )
            ) {
            ## from grid is longer, extend to grid permanently by its spacing
            if ( end( $this->data->$fromname->x ) > end( $this->data->$toname->x ) ) {
                if ( !$this->regular_grid( $toname ) ) {
                    $this->last_error = "SAS::interpolate() to curve '$toname' has a shorter grid than '$fromname', but doesn't have equal spacing";
                    return $this->error_exit( $this->last_error );
                }
                $spacing = $this->data->$toname->x[1] - $this->data->$toname->x[0];
                if ( isset( $this->data->$toname->error_y ) ) {
                    $minerr = min( $this->data->$toname->error_y );
                }

                while( end( $this->data->$fromname->x ) > end( $this->data->$toname->x ) ) {
                    $this->data->$toname->x[] = end( $this->data->$toname->x ) + $spacing;
                    $this->data->$toname->y[] = 0;
                    if ( isset( $this->data->$toname->error_y ) ) {
                        $this->data->$toname->error_y[] = $minerr;
                    }
                }
            }

            $from_x = unserialize( serialize( $this->data->$fromname->x ) );
            $from_y = unserialize( serialize( $this->data->$fromname->y ) );
            if ( isset( $this->data->$fromname->error_y ) ) {
                $from_e = unserialize( serialize( $this->data->$fromname->error_y ) );
            }

            if ( $this->data->$fromname->x[0] > $this->data->$toname->x[0] ) {
                array_unshift( $from_x, 0 );
                array_unshift( $from_y, 0 );
                if ( $this->data->$fromname->error_y ) {
                    array_unshift( $from_e, min( $this->data->$fromname->error_y ) );
                }
            }                    
                    
            if ( end( $this->data->$fromname->x ) < end( $this->data->$toname->x ) ) {
                $from_x[] = end( $this->data->$toname->x );
                $from_y[] = 0;
                if ( isset( $this->data->$fromname->error_y ) ) {
                    $from_e[] = min( $this->data->$fromname->error_y );
                }
            }
            $cmdarg =
                '{"interpolate":1'
                      . ',"from_x":' . json_encode( $from_x )
                      . ',"from_y":' . json_encode( $from_y )
                      . ( isset( $from_e ) ? ',"from_e":' . json_encode( $from_e ) : '' )
                      . ',"to_x":' . json_encode( $this->data->$toname->x )
                      . '}'
                ;
        } else {
            $cmdarg =
                '{"interpolate":1'
                      . ',"from_x":' . json_encode( $this->data->$fromname->x )
                      . ',"from_y":' . json_encode( $this->data->$fromname->y )
                      . ( isset( $this->data->$fromname->error_y ) ? ',"from_e":' . json_encode( $this->data->$fromname->error_y ) : '' )
                      . ',"to_x":' . json_encode( $this->data->$toname->x )
                      . '}'
                ;
        }

        $cmd = "/ultrascan3/us_somo/bin64/us_saxs_cmds_t json '$cmdarg' 2>&1";

        # file_put_contents( "temp_cmdarg.txt", $cmdarg );
        # file_put_contents( "temp_cmd.sh", $cmd );

        $res = run_cmd( $cmd );
        
        # file_put_contents( "temp_cmd_result.txt", $res );

        if ( null === ( $resobj = json_decode( $res ) ) ) {
            $this->last_error = "SAS::interpolate() error interpolating curve '$fromname' to '$toname' - invalid JSON returned";
            return $this->error_exit( $this->last_error );
        }

        if ( isset( $resobj->errors ) ) {
            $this->last_error = "SAS::interpolate() error interpolating curve '$fromname' to '$toname' - $resobj->errors";
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

    # extend_pr() - extend all P(r) to compatible lengths
    ## some P(r) might be longer than others, so the idea is to add zeros
    ## if they all have SDs, then use the self::PR_MIN_ERROR factor and the minimum SD
    function extend_pr( $prnames ) {
        $this->debug_msg( "SAS::extend_pr( prnames[] )" );
        $this->last_error = "";

        if ( !is_array( $prnames ) ) {
            $this->last_error = "SAS::extend_pr() argument \$prnames is not an array";
            return $this->error_exit( $this->last_error );
        }

        if ( !count( $prnames ) ) {
            $this->last_error = "SAS::extend_pr() argument \$prnames is empty";
            return $this->error_exit( $this->last_error );
        }

        $gridmaxlen  = 0;
        $gridmaxname = "";

        foreach ( $prnames as $prname ) {
            if ( !$this->data_name_exists( $prname ) ) {
                $this->last_error = "SAS::extend_pr() name '$prname' does not exist";
                return $this->error_exit( $this->last_error );
            }
            if ( $this->data->$prname->type != self::PLOT_PR ) {
                $this->last_error = "SAS::extend_pr() name '$prname' is not a P(r)";
                return $this->error_exit( $this->last_error );
            }
            $thisgridlen = count( $this->data->$prname->x );
            if ( $gridmaxlen < $thisgridlen ) {
                $gridmaxlen  = $thisgridlen;
                $gridmax     = $this->data->$prname->x;
                $gridmaxname = $prname;
            }
        }

        foreach ( $prnames as $prname ) {
            $gridlen = count( $this->data->$prname->x );
            $minlen  = min( $gridmaxlen, $gridlen );
            if ( array_slice( $this->data->$prname->x, 0, $minlen )
                 != array_slice( $gridmax, 0, $minlen ) ) {
                $this->last_error = "SAS::extend_pr() name '$prname' has an incompatible grid with '$gridmaxname'";
                return $this->error_exit( $this->last_error );
            }
        }

        ## if we got this far, it should be ok to extend all

        foreach ( $prnames as $prname ) {
            $thisgridlen = count( $this->data->$prname->x );

            if ( $thisgridlen < $gridmaxlen ) {
                $add_zeros = $gridmaxlen - $thisgridlen;

                $this->data->$prname->x = $this->data->$gridmaxname->x;
                for ( $i = 0; $i < $add_zeros; ++$i ) {
                    $this->data->$prname->y[] = 0;
                }
                            
                if ( isset( $this->data->$prname->error_y ) ) {
                    for ( $i = 0; $i < $add_zeros; ++$i ) {
                        $this->data->$prname->error_y[] = min( $this->data->$prname->error_y );
                    }
                }
            }
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

    # rmsd_residuals - calculate rmsd & residuals curve
    function rmsd_residuals( $name1, $name2, $destname, &$rmsd ) {
        $this->debug_msg( "SAS::rmsd_residuals( '$name1', '$name2', '$destname' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name1 ) ) {
            $this->last_error = "SAS::rmsd_residuals() curve name '$name1' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $name2 ) ) {
            $this->last_error = "SAS::rmsd_residuals() curve name '$name2' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data_name_exists( $destname ) ) {
            $this->last_error = "SAS::rmsd_residuals() curve name '$destname' already exists";
            return $this->error_exit( $this->last_error );
        }

        if ( count( array_diff( $this->data->$name1->x, $this->data->$name2->x ) ) ) {
            $this->last_error = "SAS::rmsd_residuals() curves named '$name1' and '$name2' have incompatible grids";
            return $this->error_exit( $this->last_error );
        }

        $len1 = count( $this->data->$name1->y );
        $len2 = count( $this->data->$name2->y );

        if ( $len1 != $len2 ) {
            $this->last_error = "SAS::rmsd_residuals() curves named '$name1' and '$name2' have incompatible grids only in the Y";
            return $this->error_exit( $this->last_error );
        }
            
        $msd = 0;
        
        $this->data->$destname       = (object)[];
        $this->data->$destname->type = $this->data->$name1->type;
        $this->data->$destname->x    = unserialize( serialize( $this->data->$name1->x ) );
        $this->data->$destname->y    = [];
        
        for ( $i = 0; $i < $len1; ++$i ) {
            $msd += pow( $this->data->$name1->y[$i] - $this->data->$name2->y[$i], 2 );
            $this->data->$destname->y[] =  $this->significant_digits( $this->data->$name1->y[$i] - $this->data->$name2->y[$i] );
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

    # norm_pr() - normalize P(r) by mw
    function norm_pr( $fromname, $mw, $normedname ) {
        $this->debug_msg( "SAS::norm_pr( '$fromname', $mw, $normedname )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $fromname ) ) {
            $this->last_error = "SAS::norm_pr() data name '$fromname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data_name_exists( $normedname ) ) {
            $this->last_error = "SAS::norm_pr() data name '$normedname' already exists";
            return $this->error_exit( $this->last_error );
        }

        if ( $mw <= 0 ) {
            $this->last_error = "SAS::norm_pr() invalid mw ($mw)";
            return $this->error_exit( $this->last_error );
        }
        
        if ( $this->data->$fromname->type != self::PLOT_PR ) {
            $this->last_error = "SAS::norm_pr() data name '$fromname' invalid type";
            return $this->error_exit( $this->last_error );
        }

        $sum = array_sum( $this->data->$fromname->y );

        $scale = $mw / $sum;

        $len = count( $this->data->$fromname->x );

        $this->data->$normedname       = (object)[];
        $this->data->$normedname->type = $this->data->$fromname->type;
        $this->data->$normedname->x    = unserialize( serialize( $this->data->$fromname->x ) );

        if ( isset( $this->data->$fromname->error_y ) && $len == count( $this->data->$fromname->error_y ) ) {
            for ( $i = 0; $i < $len; ++$i ) {
                $this->data->$normedname->y[ $i ]       = $this->significant_digits( $this->data->$fromname->y[ $i ] * $scale );
                $this->data->$normedname->error_y[ $i ] = $this->significant_digits( $this->data->$fromname->error_y[ $i ] * $scale );
            }
        } else {
            for ( $i = 0; $i < $len; ++$i ) {
                $this->data->$normedname->y[ $i ] = $this->significant_digits( $this->data->$fromname->y[ $i ] * $scale );
            }
        }            

        $this->debug_msg( "sum $normedname len $len " . array_sum( $this->data->$normedname->y ) );

        return true;
    }

    function compute_rg_from_pr( $name, &$rg ) {
        $this->debug_msg( "SAS::compute_rg_from_pr( '$name' )" );
        $this->last_error = "";
        
        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::compute_rg_from_pr() data name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }
        
        if ( $this->data->$name->type != self::PLOT_PR ) {
            $this->last_error = "SAS::compute_rg_from_pr() data name '$name' is not a P(r) curve";
            return $this->error_exit( $this->last_error );
        }
   
        if ( count( $this->data->$name->x ) < 2 ) {
            $this->last_error = "SAS::compute_rg_from_pr() data name '$name' has too few points";
            return $this->error_exit( $this->last_error );
        }

        $pts = count( $this->data->$name->x );

        if ( $pts != count( $this->data->$name->y ) ) {
            $this->last_error = "SAS::compute_rg_from_pr() data name '$name' has a mismatch in data length";
            return $this->error_exit( $this->last_error );
        }

        $intgrl_r2_pr = 0;
        $intgrl_pr    = 0;
   
        $r = $this->data->$name->x;
        $pr = $this->data->$name->y;

        $dr = $r[1] - $r[0];

        for ( $i = 0; $i < $pts; ++$i ) {
            $intgrl_r2_pr += $r[ $i ] * $r[ $i ] * $pr[ $i ] * $dr;
            $intgrl_pr    += $pr[ $i ] * $dr;
        }

        if ( $intgrl_pr <= 0 ) {
            $this->last_error = "SAS::compute_rg_from_pr() data name '$name' integral of p(r) is zero or negative";
            return $this->error_exit( $this->last_error );
        }

        $rg = sqrt( $intgrl_r2_pr / ( 2.0 * $intgrl_pr ) );

        return true;
    }

    # compute_pr_many() - call somo to compute p(r) multiple structures
    function compute_pr_many( $pdbnames, $prnames, $binsize = 1, $debug_log = "" ) {
        $this->debug_msg( "SAS::compute_pr_many( pdbnames[], prnames[], $binsize )" );
        $this->last_error = "";
        global $run_cmd_last_error_code;

        foreach ( $prnames as $prname ) {
            if ( $this->data_name_exists( $prname ) ) {
                $this->last_error = "SAS::compute_pr_many() data name '$prname' already exists";
                return $this->error_exit( $this->last_error );
            }
        }

        $count_names = count( $prnames );

        if ( count( $pdbnames ) != $count_names ) {
            $this->last_error = "SAS::compute_pr_many() names count does match pdb count";
            return $this->error_exit( $this->last_error );
        }

        foreach ( $pdbnames as $pdbname ) {
            if ( !file_exists( $pdbname ) ) {
                $this->last_error = "SAS::compute_pr_many() file name '$pdbname' does not exist";
                return $this->error_exit( $this->last_error );
            }
        }

        if ( $binsize != 1 ) {
            ## fix this by exposing binsize in calcpr.pl && us_hydrodyn_script.cpp
            $this->last_error = "SAS::compute_pr_many() only binsize 1 currently supported";
            return $this->error_exit( $this->last_error );
        }
        
        $cmd = "$this->scriptdir/calcs/calcpr.pl " . implode( " ", $pdbnames ) . " 2>&1";

        if ( strlen( $debug_log ) ) {
            $res = run_cmd( $cmd, false, true );
            if ( $run_cmd_last_error_code ) {
                if ( count( $res ) > 22 ) {
                    ## very long error, save full in $debug_log and report 1st 10 .. last 10 lines
                    file_put_contents( $debug_log, implode( "\n", $res ) );
                    $res = array_merge( array_slice( $res, 0, 10 ), [ '...' ], array_slice( $res, -10, 10 ) );
                }
                $this->error_exit( "Full results in <i>$debug_log</i><br>shell command [$cmd] returned result:<br>" . implode( "<br> ", $res ) . "<br>and with exit status '$run_cmd_last_error_code'" );
            }
        } else {
            $res = run_cmd( $cmd, true, true );
        }

        $prfiles = explode( ' ', end( $res ) );

        foreach ( $prfiles as $prfile ) {
            if ( !file_exists( $prfile ) ) {
                $this->last_error = "SAS::compute_pr_many() expected file '$prfile' does not exist";
                return $this->error_exit( $this->last_error );
            }
        }

        if ( count( $prfiles ) != $count_names ) {
            $this->last_error = "SAS::compute_pr_many() names count does not prfiles count";
            return $this->error_exit( $this->last_error );
        }

        ## no SDs in the produced .sprr file, but a 3rd column exists, so need to exclude
        for ( $i = 0; $i < $count_names; ++$i ) {
            if ( !$this->load_file( self::PLOT_PR, $prnames[$i], $prfiles[$i], false ) ) {
                return false;
            }
        }

        return true;
    }        

    # compute_pr() - call somo to compute p(r)
    function compute_pr( $pdbname, $prname, $binsize = 1 ) {
        $this->debug_msg( "SAS::compute_pr( '$pdbname', '$prname', $binsize )" );
        $this->last_error = "";
        global $run_cmd_last_error_code;

        if ( $this->data_name_exists( $prname ) ) {
            $this->last_error = "SAS::compute_pr() data name '$prname' already exists";
            return $this->error_exit( $this->last_error );
        }

        if ( !file_exists( $pdbname ) ) {
            $this->last_error = "SAS::compute_pr() file name '$pdbname' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $binsize != 1 ) {
            ## fix this by exposing binsize in calcpr.pl && us_hydrodyn_script.cpp
            $this->last_error = "SAS::compute_pr() only binsize 1 currently supported";
            return $this->error_exit( $this->last_error );
        }
        
        $cmd = "$this->scriptdir/calcs/calcpr.pl $pdbname 2>&1";

        $res = run_cmd( $cmd, true, true );

        $prfile = end( $res );

        if ( !file_exists( $prfile ) ) {
            $this->last_error = "SAS::compute_pr() expected file '$prfile' does not exist";
            return $this->error_exit( $this->last_error );
        }            

        ## no SDs in the produced .sprr file, but a 3rd column exists, so need to exclude
        return $this->load_file( self::PLOT_PR, $prname, $prfile, false );

        # $this->last_error = "SAS::compute_pr() not fully implemented";
        # return $this->error_exit( $this->last_error );
    }        

    # regular_grid() - checks grid for regular spacing
    function regular_grid( $name ) {
        $this->debug_msg( "SAS::regular_grid( '$name' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name ) ) {
            $this->last_error = "SAS::regular_grid() data name '$name' does not exist";
            return $this->error_exit( $this->last_error );
        }

        $len = count( $this->data->$name->x );

        if ( $len < 2 ) {
            $this->last_error = "SAS::regular_grid() data name '$name' has less than 2 elements";
            return $this->error_exit( $this->last_error );
        }
    
        $spacing = $this->data->$name->x[1] - $this->data->$name->x[0];

        for ( $i = 2; $i < $len; ++$i ) {
            if ( $this->data->$name->x[$i] - $this->data->$name->x[$i - 1] != $spacing ) {
                return false;
            }
        }

        return true;
    }

    # data_names() - names of all data names matching regexp, default all
    function data_names( $regexp = '//' ) {
        $this->debug_msg( "SAS::data_names( '$regexp' )" );
        $this->last_error = "";

        return array_values( preg_grep( $regexp, array_keys( (array) $this->data ) ) );
    }

    # plot_names() - names of all data names matching regexp, default all
    function plot_names( $regexp = '//' ) {
        $this->debug_msg( "SAS::plot_names( '$regexp' )" );
        $this->last_error = "";

        return preg_grep( $regexp, array_keys( (array) $this->plots ) );
    }

    # save_data_csv() save the data to a csv suitable for loading in us_somo
    function save_data_csv( $names, $csvname = 'sasdata-somo.csv', $mw = 1, $namefromregexp = '', $nameto = '' ) {
        $this->debug_msg( "SAS::save_data_csv( names[], '$csvname' )" );
        $this->last_error = "";

        if ( !is_array( $names ) ) {
            $this->last_error = "SAS::save_data_csv() argument \$names is not an array";
            return $this->error_exit( $this->last_error );
        }

        if ( !count( $names ) ) {
            $this->last_error = "SAS::save_data_csv() array \$names is empty";
            return $this->error_exit( $this->last_error );
        }

        $type = $this->data->{ $names[0] }->type;

        foreach ( $names as $name ) {
            if ( $this->data->$name->type != $type ) {
                $this->last_error = "SAS::save_data_csv() data must all be the same type";
                return $this->error_exit( $this->last_error );
            }
                
            if ( !$this->data_name_exists( $name ) ) {
                $this->last_error = "SAS::save_data_csv() name '$name' does not exist";
                return $this->error_exit( $this->last_error );
            }
        }

        if ( !$this->common_grids( $names ) ) {
            return $this->error_exit( $this->last_error );
        }

        $iqpr =
            $type == self::PLOT_IQ
            ? "I(q)"
            : "P(r)"
            ;

        $output =
            $type == self::PLOT_IQ
            ? '"Name","Type; q:",'
            : '"Name","MW (Daltons)","Area","Type; r:",'
            ;

        $output .=
            implode( ',', $this->data->{ $names[0] }->x )
            . ",,\"Plotted $iqpr curves\"\n"
            ;
        
        foreach ( $names as $name ) {
            if ( strlen( $namefromregexp ) ) {
                $usename = preg_replace( $namefromregexp, $nameto, $name );
            } else {
                $usename = $name;
            }

            if ( $type == self::PLOT_IQ ) {
                $output .=
                    "\"$usename\",\"$iqpr\","
                    . implode( ',', $this->data->$name->y )
                    . "\n"
                    ;

                if ( isset( $this->data->$name->error_y ) ) {
                    $output .=
                        "\"$usename\",\"$iqpr sd\","
                        . implode( ',', $this->data->$name->error_y )
                        . "\n"
                        ;
                }
            } else {

                $output .=
                    "\"$usename\",$mw,"
                    . array_sum( $this->data->$name->y )
                    . ','
                    . "\"$iqpr\","
                    . implode( ',', $this->data->$name->y )
                    . "\n"
                    ;

                if ( isset( $this->data->$name->error_y ) ) {
                    $output .=
                        "\"$usename\",$mw,"
                        . array_sum( $this->data->$name->y )
                        . ','
                        . "\"$iqpr sd\","
                        . implode( ',', $this->data->$name->error_y )
                        . "\n"
                        ;
                }
            }
        }
                
        if ( !file_put_contents( $csvname, $output ) ) {
            $this->last_error = "SAS::save_data_csv() error trying to create output file '$csvname'";
            return $this->error_exit( $this->last_error );
        }
        
        return false;
    }

    # save_data_csv_tr() save the data to a csv suitable for normal users
    function save_data_csv_tr( $names, $csvname = 'sasdata-col.csv', $mw = 1, $namefromregexp = '', $nameto = '' ) {
        $this->debug_msg( "SAS::save_data_csv_tr( names[], '$csvname' )" );
        $this->last_error = "";

        if ( !is_array( $names ) ) {
            $this->last_error = "SAS::save_data_csv_tr() argument \$names is not an array";
            return $this->error_exit( $this->last_error );
        }

        if ( !count( $names ) ) {
            $this->last_error = "SAS::save_data_csv_tr() array \$names is empty";
            return $this->error_exit( $this->last_error );
        }

        $type = $this->data->{ $names[0] }->type;

        foreach ( $names as $name ) {
            if ( $this->data->$name->type != $type ) {
                $this->last_error = "SAS::save_data_csv_tr() data must all be the same type";
                return $this->error_exit( $this->last_error );
            }
                
            if ( !$this->data_name_exists( $name ) ) {
                $this->last_error = "SAS::save_data_csv_tr() name '$name' does not exist";
                return $this->error_exit( $this->last_error );
            }
        }

        if ( !$this->common_grids( $names ) ) {
            return $this->error_exit( $this->last_error );
        }

        $iqpr =
            $type == self::PLOT_IQ
            ? "I(q)"
            : "P(r)"
            ;

        $output =
            $type == self::PLOT_IQ
            ? '"q [1/Angstrom]",'
            : '"r [Angstrom]",'
            ;

        foreach ( $names as $name ) {
            if ( strlen( $namefromregexp ) ) {
                $usename = preg_replace( $namefromregexp, $nameto, $name );
            } else {
                $usename = $name;
            }

            $output .= "\"$usename\",";
            if ( isset( $this->data->$name->error_y ) ) {
                $output .= "\"$usename SD\",";
            }
        }
        $output .= "\n";
        
        $len = count( $this->data->{$names[0]}->x );

        for ( $i = 0; $i < $len; ++$i ) {
            $output .= $this->data->{$names[0]}->x[$i] . ',';
            
            foreach ( $names as $name ) {
                $output .= $this->data->{$names[0]}->y[$i] . ',';
                if ( isset( $this->data->$name->error_y ) ) {
                    $output .= $this->data->{$names[0]}->error_y[$i] . ',';
                }
            }
            $output .= "\n";
        }
        
        if ( !file_put_contents( $csvname, $output ) ) {
            $this->last_error = "SAS::save_data_csv_tr() error trying to create output file '$csvname'";
            return $this->error_exit( $this->last_error );
        }
        
        return false;
    }



    # data_summary() - data summary info
    function data_summary( $names ) {
        $this->debug_msg( "SAS::data_summary( names[] )" );
        $this->last_error = "";

        if ( !is_array( $names ) ) {
            $this->last_error = "SAS::data_summary() argument \$names is not an array";
            return $this->error_exit( $this->last_error );
        }

        $fmt = " %-30s | %-10s | %-10s | %-10s | %-12s | %-12s | %-12s | %-12s | %-12s | %-12s\n";
        $out =
            sprintf( $fmt
                     ,"Data name"
                     ,"x count"
                     ,"y count"
                     ,"e count"
                     ,"sum x"
                     ,"sum y"
                     ,"sum e"
                     ,"min e"
                     ,"min q"
                     ,"max q or dmax"
            );
        
        $out .= str_repeat( "-", 163 ) . "\n";

        foreach ( $names as $name ) {
            if ( !$this->data_name_exists( $name ) ) {
                $this->last_error = "SAS::data_summary() name '$name' does not exist";
                return $this->error_exit( $this->last_error );
            }

            $data = (object) [
                "minq" => 'n/a' 
                ,"maxq" => 'n/a'
                ,"dmax" => 'n/a'
                ];

            if ( $this->data->$name->type == self::PLOT_IQ ) {
                $this->minq( $name, $data->minq );
                $this->maxq( $name, $data->maxq );
            } 
            if ( $this->data->$name->type == self::PLOT_PR ) {
                $this->dmax( $name, $data->dmax );
            }

            $out .= sprintf( $fmt
                             ,$name
                             ,count( $this->data->$name->x )
                             ,count( $this->data->$name->y )
                             ,( isset( $this->data->$name->error_y ) ? count( $this->data->$name->error_y ) : 0 )
                             ,array_sum( $this->data->$name->x )
                             ,sprintf( "%.2f", array_sum( $this->data->$name->y ) )
                             ,( isset( $this->data->$name->error_y ) ? sprintf( "%.2g", array_sum( $this->data->$name->error_y ) ) : "n/a" )
                             ,( isset( $this->data->$name->error_y ) ? sprintf( "%.2g", min( $this->data->$name->error_y ) ) : "n/a" )
                             ,$data->minq
                             ,( $this->data->$name->type == self::PLOT_IQ ? $data->maxq : $data->dmax )
                );
        }

        $out .= str_repeat( "-", 163 ) . "\n";

        return $out;
    }

    # common_grids() - check for common grids
    function common_grids( $names ) {
        $this->debug_msg( "SAS::common_grids( names[] )" );
        $this->last_error = "";
        
        if ( !is_array( $names ) ) {
            $this->last_error = "SAS::common_grids() argument \$names is not an array";
            return $this->error_exit( $this->last_error );
        }

        if ( count( $names ) < 2 ) {
            $this->last_error = "SAS::common_grids() nothing to compare, count of \$names < 2";
            return $this->error_exit( $this->last_error );
        }

        foreach ( $names as $name ) {
            if ( !$this->data_name_exists( $name ) ) {
                $this->last_error = "SAS::common_grids() name '$name' does not exist";
                return $this->error_exit( $this->last_error );
            }
        }

        $firstarray = $names[0];
        $refgrid = $this->data->$firstarray->x;
        foreach ( $names as $name ) {
            if ( $refgrid != $this->data->$name->x ) {
                $this->last_error = "SAS::common_grids() '$name' grid does not match grid '$firstarray'";
                # echo json_encode( $this->data->$name->x, JSON_PRETTY_PRINT ) . "\n";
                # echo json_encode( $refgrid, JSON_PRETTY_PRINT ) . "\n";
                return false;
            }
        }

        return true;
    }

    private function mod_sort( $a, $b ) {
        $av = explode( ' ', $a );
        $bv = explode( ' ', $b );

        $ai = intval( end( $av ) );
        $bi = intval( end( $bv ) );
        
        return $ai > $bi;
    }

    # sum_data - sums data if grids are compatible

    function sum_data( $names, $combinedname ) {
        $this->debug_msg( "SAS::sum_data( names[], '$combinedname' )" );
        $this->last_error = "";

        if ( strlen( $combinedname ) && $this->data_name_exists( $combinedname ) ) {
            $this->last_error = "SAS::sum_data() data name '$combinedname' already exists";
            return $this->error_exit( $this->last_error );
        }            

        $names_count = count( $names );

        if ( $names_count == 0 ) {
            $this->last_error = "SAS::sum_data() empty names[]";
            return $this->error_exit( $this->last_error );
        }            

        if ( $names_count == 1 ) {
            ## single curve, ok to copy
            return $this->copy_data( $names[ 0 ], $combinedname, true );
        }

        if ( !$this->common_grids( $names ) ) {
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->copy_data( $names[ 0 ], $combinedname, true ) ) {
            return $this->error_exit( $this->last_error );
        }            

        $datasize = count( $this->data->$combinedname->y );

        for ( $i = 1; $i < $names_count; ++$i ) {
            for ( $j = 0; $j < $datasize; ++$j ) {
                $this->data->$combinedname->y[ $j ] += $this->data->{ $names[ $i ] }->y[ $j ];
            }
        }

        return true;
    }

    # NNLS - 
    function nnls( $targetname, $names, $combinedname, &$results, $use_errors = true, $cutthreshhold = 0.0045, $normalize = true ) {
        $this->debug_msg( "SAS::nnls( '$targetname', names[], &\$results[] )" );
        $this->last_error = "";
        global $run_cmd_last_error_code;

        ## $combinedname is the sum of the nnls fit curves, good for chi2 if errors used

        if ( strlen( $combinedname ) && $this->data_name_exists( $combinedname ) ) {
            $this->last_error = "SAS::nnls() data name '$combinedname' already exists";
            return $this->error_exit( $this->last_error );
        }            

        ## common_grids() also validates data exists
        if ( !$this->common_grids( array_merge( [ $targetname ], $names ) ) ) {
            return $this->error_exit( $this->last_error );
        }

        if ( $use_errors ) {
            if ( !isset( $this->data->$targetname->error_y )
                 || count( $this->data->$targetname->error_y ) != count( $this->data->$targetname->y )
                ) {
                $this->last_error = "SAS::nnls() fit with SD requested, but SDs do not exist or mismatch for '$targetname'";
                return $this->error_exit( $this->last_error );
            }
            if ( min( $this->data->$targetname->error_y ) <= 0 ) {
                $this->last_error = "SAS::nnls() fit with SD requested, but not all SDs are positive for '$targetname'";
                return $this->error_exit( $this->last_error );
            }
        }            
                
        ## run nnls via us_somo
        ## support very large datasets, use "nnls.json"

        $nnlsfile = "nnls.json";

        ## create contents
        $nnlsobj = (object)[
            'target'      => &$this->data->$targetname  ## note target:x is not needed, but if we unset, it will clear in the reference, not worth deep copy
            ,'data'       => (object)[]
            ,'use_errors' => $use_errors ? 1 : 0
            ,'sd_factor'  => $this->data->$targetname->type == self::PLOT_IQ ? "1/sd" : "1/sd"
            ];

        $all_data_has_errors = true;

        foreach ( $names as $name ) {
            $nnlsobj->data->$name = &$this->data->$name->y;
            if ( !isset( $this->data->$name->error_y ) ) {
                $all_data_has_errors = false;
            }
        }

        if ( false === file_put_contents( $nnlsfile, json_encode( $nnlsobj ) ) ) {
            $this->last_error = "SAS::NNLS() - failed to create file '$nnlsfile'";
            return $this->error_exit( $this->last_error );
        }
            
        $cmdarg = '{"nnls":1,"file":"' . $nnlsfile . '"}';
        
        $cmd = "/ultrascan3/us_somo/bin64/us_saxs_cmds_t json '$cmdarg' 2>&1";

        $res = run_cmd( $cmd, true, false );

        $resobj = json_decode( $res );

        if ( isset( $resobj->errors ) ) {
            $this->last_error = "SAS::NNLS() - errors: $resobj->errors";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $resobj->data ) ) {
            $this->last_error = "SAS::NNLS() - result did not include data";
            return $this->error_exit( $this->last_error );
        }

        if ( !isset( $resobj->combined_fit_y ) ) {
            $this->last_error = "SAS::NNLS() - result did not include combined_fit_y";
            return $this->error_exit( $this->last_error );
        }

        ## normalize results

        $sum = 0;

        $procobj = (object)[];
        
        foreach ( $resobj->data as $k => $v ) {
            if ( $resobj->data->$k > $cutthreshhold ) {
                $procobj->$k = floatval( $v );
                $sum += $procobj->$k;
            }
        }

        if ( $normalize && $sum > 0 ) {
            foreach ( $procobj as &$v ) {
                $v /= $sum;
            }
        }

        ## sort by model #

        # $this->debug_json( "procobj", $procobj );

        $procarray = array_keys( (array) $procobj );

        # $this->debug_json( "procarray before usort", $procarray );

        usort( $procarray, fn( $a, $b ) => $this->mod_sort( $a, $b ) );

        # $this->debug_json( "procarray after usort", $procarray );

        $finalarray = [];

        ## somehow procobj is funky ... json looks good but values can be the keys?
        ## recreating it seems to fix
        $procobj = unserialize( serialize( $procobj ) );

        # $this->debug_json( "procobj after unserialize", $procobj );

        foreach ( $procarray as $v ) {
            $finalarray[ $v ] = $procobj->$v;
        }

        $len = count( $this->data->{$names[0]}->x );

        if ( $all_data_has_errors ) {
            ## compute combined errors
            $this->debug_msg( "all data has SDs, will compute SDs for fit." );
            $error_y = array_fill( 0, $len, 0 );

            foreach ( $finalarray as $k => $v ) {
                if ( !$this->data_name_exists( $k ) ) {
                    $this->last_error = "SAS::NNLS() - returned model not in data '$k'";
                    return $this->error_exit( $this->last_error );
                }
                for ( $i = 0; $i < $len; ++$i ) {
                    $error_y[$i] += $v * $v * $this->data->$k->error_y[$i] *  $this->data->$k->error_y[$i];
                }
            }
            for ( $i = 0; $i < $len; ++$i ) {
                $error_y[$i] = sqrt( $error_y[$i] );
            }
        }
        
        if ( strlen( $combinedname ) ) {
            $firstarray = $names[0];
            $this->data->$combinedname = (object)[];
            $this->data->$combinedname->type = $this->data->$firstarray->type;
            $this->data->$combinedname->x    = $this->data->$firstarray->x;
            $this->data->$combinedname->y    = $resobj->combined_fit_y;
            if ( $all_data_has_errors ) {
                $this->data->$combinedname->error_y = $error_y;
            }
        }

        $results = $finalarray;

        # $this->debug_json( "finalarray", $finalarray );

        return true;
    }

    # compare_data - return true if matched, false if not
    function compare_data( $name1, $name2, $compare_errors = true ) {
        $this->debug_msg( "SAS::compare_data( '$name1', '$name2' )" );
        $this->last_error = "";

        if ( !$this->data_name_exists( $name1 ) ) {
            $this->last_error = "SAS: name '$name1' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data_name_exists( $name2 ) ) {
            $this->last_error = "SAS: name '$name2' does not exist";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$name1->type != $this->data->$name2->type ) {
            $this->last_error = "SAS: name '$name1' and '$name2' are different types";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->data->$name1->x != $this->data->$name2->x
             || $this->data->$name1->y != $this->data->$name2->y
            ) {
            return false;
        }

        if ( $compare_errors ) {
            if ( !isset( $this->data->$name1->error_y )
                 && !isset( $this->data->$name2->error_y ) ) {
                return true;
            }
            if (
                (
                 !isset( $this->data->$name1->error_y )
                 && isset( $this->data->$name2->error_y )
                )
                ||
                (
                 isset( $this->data->$name1->error_y )
                 && !isset( $this->data->$name2->error_y )
                )
                ) {
                return false;
            }
            return $this->data->$name1->error_y == $this->data->$name2->error_y;
        }
        return true;
    }

    # dump_data() - return data object json encoded
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

    # dump_plots() - return plots object json encoded
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

    # dump() - return data and plots objects json encoded
    function dump( $pretty = true ) {
        $this->debug_msg( "SAS::dump()" );
        return
            $this->dump_data( $pretty )
            . $this->dump_plots( $pretty )
            ;
   }

    ## get annotation text from plot and return array with nChi^2 & RMSD values if present
    function plot_stats( $plot ) {
        $result = (object)[];
        if ( !isset( $plot->layout ) ) {
            $result->error = "Plot has no layout";
            return $result;
        }
        if ( !isset( $plot->layout->annotations ) ) {
            $result->error = "Plot has no annotations " . json_encode( $plot->layout );
            return $result;
        }
        if ( !count( $plot->layout->annotations ) ) {
            $result->error = "Plot annotations are empty " . json_encode( $plot->layout->annotations );
            return $result;
        }
        
        if ( !isset( $plot->layout->annotations[0]->text ) ) {
            $result->error = "Plot has no annotations text"  . json_encode( $plot->layout->annotations[0] );
            return $result;
        }
        if ( !strlen( $plot->layout->annotations[0]->text ) ) {
            $result->error = "Plot annotations text is empty";
            return $result;
        }
        ## for debugging $result->text = $plot->layout->annotations[0]->text;
        if ( preg_match( '/RMSD ([^ ]+)/', $plot->layout->annotations[0]->text, $matches ) ) {
            $result->RMSD = floatval( $matches[ 1 ] );
            $result->fit  = $result->RMSD;
        }
        if ( preg_match( '/nChi\^2 ([^ ]+)/', $plot->layout->annotations[0]->text, $matches ) ) {
            $result->nChi2 = floatval( $matches[ 1 ] );
            $result->fit  = $result->nChi2;
        }
        return $result;
    }
}
