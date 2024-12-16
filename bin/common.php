<?php
{};

include_once "limits.php";

class cgrun_state {
    private $statefile;

    public $state;
    public $errors;

    function __construct() {
        $this->statefile = "state.json";
        $this->errors    = "";
        if ( file_exists( $this->statefile ) ) {
            $this->state = json_decode( file_get_contents( $this->statefile ) );
        } else {
            $this->state = (object)[];
        }
    }

    public function save() {
        try {
            if ( false === file_put_contents( $this->statefile, json_encode( $this->state ) ) ) {
                $this->errors .= "Error storing $this->statefile";
                return false;
            }
            chmod( $this->statefile, 0660 );
            return true;
        } catch ( Exception $e ) {
            $this->errors .= "Error storing $this->statefile";
            return false;
        }
    }

    public function init() {
        $this->state = (object)[];
        return $this->save();
    }
        
    public function dump( $msg = false ) {
        return ( $msg ? "$msg:\n" : "" ) . json_encode( $this->state, JSON_PRETTY_PRINT ) . "\n";
    }
}

## messages

$msg_admin = "<br><br>If this problem persists, Please contact the administrators via the <i>Feedback</i> tab.";

## utility functions

function mkdir_if_needed( $dir ) {
    if ( is_dir( $dir ) ) {
        return true;
    }
    mkdir( $dir, 0770 );
    chmod( $dir, 0770 );
    return is_dir( $dir );
}

# take an object of values and set to default obj values if not present in object
function object_set_defaults( $inobj, $defobj ) {
    if ( !$inobj ) {
        $inobj = (object)[];
    }

    foreach ( (array) $defobj as $k => $v ) {
        if ( !isset( $inobj->{$k} ) ) {
            $inobj->{$k} = $v;
        }
    }

    return $inobj;
}

function run_cmd( $cmd, $exit_if_error = true, $array_result = false ) {
    global $run_cmd_last_error_code;

    exec( "$cmd 2>&1", $res, $run_cmd_last_error_code );
    if ( $exit_if_error && $run_cmd_last_error_code ) {
        error_exit( "shell command [$cmd] returned result:<br>" . implode( "<br> ", $res ) . "<br>and with exit status '$run_cmd_last_error_code'" );
    }
    if ( !$array_result ) {
        return implode( "\n", $res ) . "\n";
    }
    return $res;
}

function run_streaming_cmd( $cmd, $cb_on_write, $exit_if_error = true, $array_result = false, $stderr_file = "error-output.txt" ) {
    global $run_cmd_last_error_code;

    $descriptorspec = array(
        0 => array( "pipe", "r" ),
        1 => array( "pipe", "w" ),
        2 => array( "file", $stderr_file, "w" )
        );

    $process = proc_open( $cmd, $descriptorspec, $pipes );

    $res = [];

    if ( is_resource( $process ) ) {
        
        # close stdin to proc
        fclose( $pipes[0] );

        while ( !feof( $pipes[1] ) ) {
            $line  = fgets( $pipes[1] );
            $res[] = $line;
            $cb_on_write( $line );
        }

        fclose($pipes[1]);

        $run_cmd_last_error_code = proc_close($process);

        if ( $exit_if_error && $run_cmd_last_error_code ) {
            error_exit( "shell command [$cmd] returned result:<br>" . implode( "<br> ", $res ) . "<br>and with exit status '$run_cmd_last_error_code'" );
        }
        if ( !$array_result ) {
            return implode( "\n", $res ) . "\n";
        }
        return $res;
    }

    ## !is_resource
    
    if ( $exit_if_error ) {
        error_exit( "shell command [$cmd] failed to run" );
    } 
    $run_cmd_last_error_code = -1;
    return $array_result ? [] : "";
}

function json_exit() {
    global $output;
    echo json_encode( $output );
    exit;
}    

function error_exit( $msg, $nonotify = true ) {
    if ( !strlen( $msg ) ) {
        $msg = "Empty error message!";
    }
    $msgobj = (object) [
        "_disable_notify" => $nonotify
        ,"_message" => (object) [
            "icon" => "toast.png"
            ,"text" => $msg
        ]
        ];

    echo json_encode( $msgobj );
#    echo '{"_message":{"icon":"toast.png","text":"' . $msg . '"}}';
    exit;
}
function error_exit_admin( $msg ) {
    global $msg_admin;
    error_exit( "$msg$msg_admin" );
}

function tf_str( $flag ) {
    return $flag ? "true" : "false";
}

function progress_text( $msg, $decor = '&diams;&diams;&diams;', $just_return_string = false ) {
    global $ga;

    if ( strlen( $msg ) ) {
        $str = "<h5 style=\"color:blue\"><center>$decor $msg $decor</center></h5>";
    } else {
        $str = '';
    }

    if ( $just_return_string ) {
        return $str;
    }
    
    $ga->tcpmessage( [ 'progress_text' => $str ] );
}

function nnls_results_to_html( $obj ) {
    $res =
        "<div style='font-family:monospace;width=100%'><small>"
        . "<table>"
        . "<tr><th style='padding:0 15px 0 15px;text-align:center'>&nbsp;Model&nbsp;</th><th style='padding:0 15px 0 15px'>&nbsp;Fit contrib. %&nbsp;</th></tr>"
        ;

    foreach ( $obj as $k => $v ) {

        $res .=
            "<tr><td style='padding:0 15px 0 15px'>&nbsp;$k&nbsp;</td><td style='padding:0 15px 0 15px;text-align:center'>"
            . sprintf( "%.1f", 100 * $v )
            . "</td></tr>"
            ;
    }
    $res .= "</table></small><br></div>";
    return $res;
}

function extract_dcd_frame( $frame, $pdb, $dcd, $outdir, $skipifexists = false ) {
    global $max_frame_digits;

    if ( $frame < 1 ) {
        error_exit( "extract_dcd_frame() : invalid frame number requested ($frame)" );
    }

    if ( !file_exists( $pdb ) ) {
        error_exit( "extract_dcd_frame() : $pdb does not exist" );
    }
        
    if ( !file_exists( $dcd ) ) {
        error_exit( "extract_dcd_frame() : $dcd does not exist" );
    }

    $frame_padded = str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
    $outname = "$outdir/" . preg_replace( '/\.pdb$/', '', $pdb ) . "-m$frame_padded.pdb";

    if ( file_exists( $outname ) ) {
        if ( $skipifexists ) {
            return $outname;
        }
        unlink( $outname );
    }
        
    $pdbuse = "$pdb.noCRYST1.pdb";

    if ( !file_exists( $pdbuse ) ) {
        $cmd = "grep -Pv '^CRYST1' $pdb > $pdbuse";
        run_cmd( $cmd );
    }

    mkdir_if_needed( $outdir );
    if ( !is_dir( $outdir ) ) {
        error_exit( "could not make directory $outdir" );
    }

    $mdconvertframe = $frame - 1;
    
    $cmd = "mdconvert -i $mdconvertframe -t $pdbuse -o $outname.tmp.pdb $dcd && grep -Pv '^(MODEL|ENDMDL)' $outname.tmp.pdb > $outname && rm $outname.tmp.pdb";

    run_cmd( $cmd );

    return $outname;
}

function model_no_from_pdb_name( $pdb ) {
    $model = preg_replace( '/^.*-m(\d+)\.pdb$/', '$1', $pdb );
    $model = preg_replace( '/^0+/', '', $model );
    return intval( $model );
}

function clean_up_filename_and_copy_if_needed( $filename ) {
    $filename_no_path    = preg_replace( '/^.*\//', '', $filename );
    $path                = dirname( $filename );
    $filename_fixed_up   = preg_replace( '/[^a-zA-Z0-9_.]/', '_', $filename_no_path );
    if ( $filename_fixed_up == $filename_no_path ) {
        return $filename;
    }
    if ( !copy( $filename, $filename_fixed_up ) ) {
        error_exit( "Error copying '$filename' to '$filename_fixed_up'" );
    }

    return $filename_fixed_up;
}

## tests

/*
    extract_dcd_frame( 14400, "AF-G0A007-F1-model_v4-somo.pdb", "monomer_monte_carlo/run_G0A007.dcd", "preselectedtest" );
extract_dcd_frame( 14400, "AF-G0A007-F1-model_v4-somo.pdb", "monomer_monte_carlo/run_G0A007.dcd", "preselectedtest", true );
*/
/* 
$nnlsres = [
    "mod 1" => .5
    ,"mod 2" => .3
    ,"mod 27" => .2
    ];

echo nnls_results_to_html( $nnlsres ) . "\n";
*/    

/*
$cgstate = new cgrun_state();

echo $cgstate->dump( "initial state" );

$cgstate->state->xyz = "hi";

echo $cgstate->dump( "after set xyz" );

$cgstate->save();

*/
