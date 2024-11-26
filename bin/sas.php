<?php
{};

## a class for handling Iq Pr
## 

class SAS {

    public $last_error;

    private $data;
    private $plots;
    private $debug;
    private $exit_on_error;

    const WIDTH_LINE      = 1;
    const WIDTH_ERROR_CAP = 1;

    ## php 8.1 has enum, should eventually replace

    const PLOT_IQ    = 0;
    const PLOT_PR    = 1;
    
    private $plot_tmpl;
    
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
                 ,"config" : {
                    "showLink" : true
                    ,"plotlyServerURL": "https://chart-studio.plotly.com"
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
                       ,"standoff" : 20
                        ,"font" : {
                            "color"  : "rgb(0,5,80)"
                        }
                     }
                    }
                 }
                 ,"config" : {
                    "showLink" : true
                    ,"plotlyServerURL": "https://chart-studio.plotly.com"
                 }
            }'
        )
        ];
    }

    private function error_exit( $msg ) {
        if ( $this->exit_on_error ) {
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

    function name_exists( $name ) {
        $this->debug_msg( "SAS::name_exists( '$name' )" );
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

        if ( $this->name_exists( $name ) ) {
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

            # remove blank & text & comment lines
            $plotin = preg_grep( '/^(\s*#|\s*$|\s*[A-Za-z])/', $plotin, PREG_GREP_INVERT );

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

    # creates a plot from an existing plot
    ## N.B. not currently extracting "names" data
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

        $this->plots->$name = unserialize( serialize( $org_plot ) );

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

    # adds to existing plot object 
    function add_plot( $name, $dataname ) {
        $this->debug_msg( "SAS::add_plot( '$name', '$dataname' )" );
        $this->last_error = "";

        if ( !$this->plots->$name ) {
            $this->last_error = "add_plot() name $name is not a plot name\n";
            return $this->error_exit( $this->last_error );
        }

        if ( !$this->data->$dataname ) {
            $this->last_error = "add_plot() dataname $dataname is not a data name\n";
            return $this->error_exit( $this->last_error );
        }

        if ( $this->plots->$name->type != $this->data->$dataname->type ) {
            $this->last_error = "add_plot() data type does not match plot type\n";
            return $this->error_exit( $this->last_error );
        }

        $this->plots->$name->data[] =
            (object)[
                "x"     => &$this->data->$dataname->x
                ,"y"    => &$this->data->$dataname->y
                ,"type" => "scatter"
                ,"name" => $dataname
                ,"line" => (object) [
                    "width" => self::WIDTH_LINE
                    # ,"color"  => "rgb(150,150,222)"
                ]
            ]
            ;

        if ( $this->data->$dataname->error_y ) {
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

    function interpolate( $toname, $fromname, $destname ) {
        $this->debug_msg( "SAS::interpolate( '$toname', '$fromname', '$destname' )" );
        $this->last_error = "";
    }

    function chi2( $name1, $name2 ) {
        $this->debug_msg( "SAS::chi2( '$name1', '$name2' )" );
        $this->last_error = "";
    }

    function common_grid( $name1, $name2 ) {
        $this->debug_msg( "SAS::common_grid( '$name1', '$name2' )" );
        $this->last_error = "";
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

if ( isset( $do_testing ) ) {
    $sas = new SAS( true );

    $files = [
        'waxsis/lyzexp.dat'
        ,'waxsis/fittedCalcInterpolated_waxsis.fit'
        ];

    foreach ( $files as $file ) {
        if ( !($sas->load_file( SAS::PLOT_IQ, basename( $file ), $file )) ) {
            echo $sas->last_error . "\n";
            echo "sas loaded failed for $file\n";
        } else {
            echo "sas loaded ok for $file\n";
        }
    }

    $plotname = "IQ test";
    $sas->create_plot(
        SAS::PLOT_IQ
        ,$plotname
        ,array_map( fn($x) => basename( $x ), $files )
        ,[
            "title" => "funky"
            ,"showlegend" => true
        ]
        );

    # echo $sas->dump_plots( false );
    echo "\n" .  json_encode( $sas->plot( $plotname ) ) . "\n";
}
