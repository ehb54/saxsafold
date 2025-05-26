#!/usr/local/bin/php
<?php

{};

$prog = array_shift( $argv );
$scriptdir = dirname( __FILE__ );

include "common.php";

function cli_error_exit( $msg ) {
    fwrite( STDERR, "$msg\nTerminating due to errors.\n" );
    exit(-1);
}

$notes = <<<__EOD
usage: $prog {options} 

utility to get relevant files from user's project

Options

--help                   : print this information and exit

--user username          : user (required)
--project project        : project (required)
--tgzout filename        : package needed SAXS and Structure files in tgz archive

__EOD;

$u_argv = $argv;

$debug                = 0;

while( count( $u_argv ) && substr( $u_argv[ 0 ], 0, 1 ) == "-" ) {
    switch( $arg = $u_argv[ 0 ] ) {
        case "--help": {
            echo $notes;
            exit;
        }
        case "--debug": {
            array_shift( $u_argv );
            $debug++;
            break;
        }
        case "--user": {
            array_shift( $u_argv );
            if ( !count( $u_argv ) ) {
                cli_error_exit( "ERROR: option '$arg' requires an argument\n$notes" );
            }
            $user = array_shift( $u_argv );
            break;
        }
        case "--project": {
            array_shift( $u_argv );
            if ( !count( $u_argv ) ) {
                cli_error_exit( "ERROR: option '$arg' requires an argument\n$notes" );
            }
            $project = array_shift( $u_argv );
            break;
        }
        case "--tgzout": {
            array_shift( $u_argv );
            if ( !count( $u_argv ) ) {
                cli_error_exit( "ERROR: option '$arg' requires an argument\n$notes" );
            }
            $tgzout = array_shift( $u_argv );
            break;
        }
      default:
        cli_error_exit( "\nUnknown option '$u_argv[0]'\n\n$notes" );
    }
}

if ( count( $u_argv ) ) {
    echo $notes;
    exit;
}

if ( !isset( $user ) ) {
    cli_error_exit( "--user must be specified" );
}

if ( !isset( $project ) ) {
    cli_error_exit( "--project must be specified" );
}

$dir = "$scriptdir/../results/users/$user/$project";

if ( !is_dir( $dir ) ) {
    cli_error_exit( "directory $dir does not exist" );
}

$statefile = "$dir/state.json";

if ( !file_exists( $statefile ) ) {
    cli_error_exit( "state file $statefile does not exist" );
}

$cgstate = new cgrun_state( $statefile );

$res = (object)[
    "iqfile"  => basename( $cgstate->state->saxsiqfile )
    ,"prfile" => basename( $cgstate->state->saxsprfile )
    ,"pdb"    => basename(  $cgstate->state->output_load->name )
    ];

echo json_encode( $res, JSON_PRETTY_PRINT ) . "\n";

if ( isset( $tgzout ) ) {
    $cmd = "cd $dir && tar zcf $tgzout " . implode( " ", (array) $res );
    echo run_cmd( "$cmd" );
    echo "created:\n$tgzout\n";
}
