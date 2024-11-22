<?php
{};

## a class for handling Iq Pr
## 

class SAS {

    public $last_error;

    private $data;
    private $debug;

    function __construct( $debug = false ) {
        $this->debug = $debug;

        if ( $this->debug ) { echo "SAS::construct()\n"; }
        
        $this->data =
            (object) [
                "name" => (object) [
                ]
            ];
    }

    function load_file( $name, $filename ) {
        if ( $this->debug ) { echo "SAS::load_file()\n"; }
        $this->last_error = "";
        
        if ( isset( $this->data->name->$name ) ) {
            $this->last_error = "SAS: Duplicate name '$name'";
            return false;
        }

        $this->data->name->$name = (object) [];
        return true;
    }

    function load_plot( $name, $plot ) {
        if ( $this->debug ) { echo "SAS::load_plot()\n"; }
        $last_error = "";
    }

    function interpolate( $toname, $fromname ) {
        if ( $this->debug ) { echo "SAS::interpolate()\n"; }
        $last_error = "";
    }

    function chi2( $name1, $name2 ) {
        if ( $this->debug ) { echo "SAS::chi2()\n"; }
        $last_error = "";
    }

    function dumpdata() {
        if ( $this->debug ) { echo "SAS::dumpdata()\n"; }
        return json_encode( $this->data, JSON_PRETTY_PRINT ) . "\n";
    }
}

## testing

$sas = new SAS( true );

if ( !($sas->load_file( "hi", "there" )) ) {
    echo $sas->last_error . "\n";
    echo "sas loaded failed\n";
} else {
    echo "sas loaded ok\n";
}
if ( !($sas->load_file( "hi", "there" )) ) {
    echo $sas->last_error . "\n";
    echo "sas loaded failed\n";
} else {
    echo "sas loaded ok\n";
}
        
echo $sas->dumpdata();

