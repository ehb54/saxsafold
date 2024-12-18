#!/usr/local/bin/php
<?php

{};

$prog = array_shift( $argv );

include "common.php";

$notes = <<<__EOD

usage: php $prog files

converts previously generated quick waxsis .dat files to the proper name and increments model number


__EOD;

if ( !count( $argv ) ) {
    echo $notes;
    exit;
}

function model_no_from_waxsis_dat( $file ) {
    $model = preg_replace( '/^.*-m(\d+)-waxsis\.dat$/', '$1', $file );
    $model = preg_replace( '/^0+/', '', $model );
    return intval( $model );
}

function padded_model_no_from_frame( $frame ) {
    global $max_frame_digits;
    return str_repeat( '0', $max_frame_digits - strlen( $frame + 0 ) ) . ( $frame + 0 );
}

foreach ( $argv as $f ) {
    if ( !file_exists( $f ) ) {
        echo "file $f does not exist\n";
        exit;
    }
    $modelnum = model_no_from_waxsis_dat( $f ); 

    if ( !$modelnum ) {
        echo "invalid model number $modelnum\n";
        exit;
    }

    $base = preg_replace( '/(^.*-m)\d+-waxsis\.dat$/', '$1', $f );
    $newfile = $base . padded_model_no_from_frame( $modelnum + 1 ) . "-waxsis_q.dat";
    echo "$f => model $modelnum  base '$base' newfile '$newfile'\n";
    if ( !rename( $f, $newfile ) ) {
        echo "error renaming $f => $newfile\n";
        exit;
    }
}
