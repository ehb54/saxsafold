#!/usr/local/bin/php
<?php
{};

## replace_expt_iq.php: replace a project's experimental I(q) by a rescaled copy of the same curve ( for example
## the same data normalized as I*(q) ), and bring every stored plot built on it along. See $notes for the details.

$notes = <<<__EOD
replace_expt_iq.php: replace a saxsafold project's experimental I(q) by a rescaled copy of the same curve,
for example the same data normalized as I*(q), and bring every stored plot built on it along.

usage ( inside the saxsafold container, in the project directory, as the web server user ):

  cd <results>/users/<user>/<project>
  runuser -u www-data -- php <saxsafold>/bin/replace_expt_iq.php --check NEW_IQ_FILE [--sd file|scaled]
  runuser -u www-data -- php <saxsafold>/bin/replace_expt_iq.php --apply NEW_IQ_FILE --sd file|scaled

  --check FILE      run every check and print what would change; writes nothing
  --apply FILE      run every check and, only if all pass, make the change ( state.json is backed up first )
  --sd file         take the SDs from FILE
  --sd scaled       keep the project's SDs, multiplied by the same factor as the intensities
                    ( --check without --sd uses the file's SDs when it has them )
  --tolerance REL   how far the point-by-point ratios may stray from one constant ( default 1e-4 )
  --help            this text

checks ( --apply writes nothing unless all pass ):
  - no job of this project is running: its last job has finished and no process runs one
  - FILE has exactly the q grid of the stored experimental curve
  - FILE's intensities are the stored ones times one constant, point by point ( the scale factor )
  - with --sd file: FILE's SDs are the stored ones times one constant
  - every residual and fit statistic recomputed with sas.php agrees with what the factors predict

what changes in state.json:
  - the experimental curve in every stored plot that holds it ( Load SAXS, Load structure,
    Compute I(q)/P(r), Final model ); the curves fitted to it are scaled by the same factor, and their
    residuals and RMSD / nChi^2 / P-value annotations are recomputed with sas.php
  - the experimental Guinier result, its plot and its log text, recomputed with the stored settings
  - saxsiqfile, and the I(q) download link of Load structure; FILE is copied into the project if needed
  NNLS results do not change: a constant factor on the data and the SDs leaves them unchanged.

what does not change, and still shows the old scale:
  - the "all computed curves" images and the csv downloads of Compute I(q)/P(r)
  - the experimental P(r)
  Afterwards, rerun Final model to redo the final NNLS on the new data ( its WAXSiS curves are cached ).

exit status: 0 checks passed ( with --apply: written ), 1 a check failed and nothing was written,
             2 usage or setup error

__EOD;

$scriptdir = dirname( __FILE__ );

function rei_usage_error( $msg ) {
    global $notes;
    fwrite( STDERR, "replace_expt_iq: $msg\n\n" );
    echo $notes;
    exit( 2 );
}

function rei_setup_error( $msg ) {
    fwrite( STDERR, "replace_expt_iq: $msg\n" );
    exit( 2 );
}

## -- arguments: without any, only the help

$args    = array_slice( $argv, 1 );
$mode    = "";
$newfile = "";
$sdmode  = "";
$tol     = 1e-4;

while ( count( $args ) ) {
    $arg = array_shift( $args );
    switch ( $arg ) {
        case "--check" :
        case "--apply" :
            if ( strlen( $mode ) ) {
                rei_usage_error( "give only one of --check and --apply" );
            }
            $mode    = substr( $arg, 2 );
            $newfile = (string) array_shift( $args );
            if ( !strlen( $newfile ) ) {
                rei_usage_error( "$arg needs the new I(q) file" );
            }
            break;
        case "--sd" :
            $sdmode = (string) array_shift( $args );
            if ( $sdmode != "file" && $sdmode != "scaled" ) {
                rei_usage_error( "--sd takes 'file' or 'scaled'" );
            }
            break;
        case "--tolerance" :
            $tol = floatval( array_shift( $args ) );
            if ( $tol <= 0 ) {
                rei_usage_error( "--tolerance needs a positive number" );
            }
            break;
        case "--help" :
            echo $notes;
            exit( 0 );
        default :
            rei_usage_error( "unknown argument '$arg'" );
    }
}

if ( !strlen( $mode ) ) {
    echo $notes;
    exit( 0 );
}
if ( $mode == "apply" && !strlen( $sdmode ) ) {
    rei_usage_error( "--apply needs --sd file or --sd scaled" );
}

require_once "$scriptdir/common.php";
require_once "$scriptdir/sas.php";

$failures = [];

function rei_problem( $msg ) {
    global $failures;
    $failures[] = $msg;
    echo "  PROBLEM: $msg\n";
}

## -- the project, and that nothing of it is running

if ( !file_exists( "state.json" ) ) {
    rei_setup_error( "no state.json here: run this in the project directory" );
}
if ( $mode == "apply" && !is_writable( "state.json" ) ) {
    rei_setup_error( "state.json is not writable by this user: run it as the web server user ( runuser -u www-data -- php ... )" );
}
if ( !file_exists( $newfile ) ) {
    rei_setup_error( "$newfile does not exist" );
}

$project = basename( getcwd() );
if ( !preg_match( '/^[A-Za-z0-9._+-]+$/', basename( $newfile ) ) ) {
    rei_setup_error( "the file name '" . basename( $newfile ) . "' has spaces or other special characters, which break the commands that use it later: copy it to a plain name first" );
}
echo "project $project\n";

$inputs = glob( "_log/_input_*" );
if ( count( $inputs ) ) {
    usort( $inputs, function( $a, $b ) { return filemtime( $a ) - filemtime( $b ); } );
    $last   = end( $inputs );
    $uuid   = substr( basename( $last ), strlen( "_input_" ) );
    $job    = json_decode( file_get_contents( $last ) );
    $module = isset( $job->_module ) ? $job->_module : "?";
    if ( file_exists( "_log/_stdout_$uuid" ) ) {
        echo "  last job: $module, started " . date( "Y-m-d H:i", filemtime( $last ) )
            . ", finished " . date( "Y-m-d H:i", filemtime( "_log/_stdout_$uuid" ) ) . "\n";
    } else {
        rei_problem( "the last job ( $module, started " . date( "Y-m-d H:i", filemtime( $last ) ) . " ) has not finished" );
    }
}
foreach ( glob( "/proc/[0-9]*/cmdline" ) as $f ) {
    $cmd = @file_get_contents( $f );
    if ( $cmd !== false && strpos( $cmd, "\"_project\":\"$project\"" ) !== false && basename( dirname( $f ) ) != getmypid() ) {
        rei_problem( "process " . basename( dirname( $f ) ) . " is running a job of this project" );
    }
}

## -- the stored experimental curve and the new one

$cgstate = new cgrun_state();
$state   = $cgstate->state;

if ( !isset( $state->output_loadsaxs->iqplot->data[ 0 ]->name )
     || $state->output_loadsaxs->iqplot->data[ 0 ]->name != "Exp. I(q)" ) {
    rei_setup_error( "state.json holds no experimental I(q) from Load SAXS" );
}
$exp0 = $state->output_loadsaxs->iqplot->data[ 0 ];
$ox   = $exp0->x;
$oy   = $exp0->y;
$osd  = isset( $exp0->error_y->array ) ? $exp0->error_y->array : null;
$n    = count( $ox );
$oldfile = isset( $state->saxsiqfile ) ? $state->saxsiqfile : "";

printf( "  current experimental I(q): %s ( %d points, q %.6g .. %.6g )\n", basename( $oldfile ), $n, $ox[ 0 ], $ox[ $n - 1 ] );

$sasnew = new SAS( false, false );
if ( !$sasnew->load_file( SAS::PLOT_IQ, "new", $newfile ) || !$sasnew->create_plot( SAS::PLOT_IQ, "new", [ "new" ] ) ) {
    rei_setup_error( "can not read $newfile as an I(q) file: " . $sasnew->last_error );
}
$newtrace = $sasnew->plot( "new" )->data[ 0 ];
$nx  = $newtrace->x;
$ny  = $newtrace->y;
$nsd = isset( $newtrace->error_y->array ) ? $newtrace->error_y->array : null;

printf( "  new experimental I(q):     %s ( %d points, q %.6g .. %.6g, %s )\n", basename( $newfile ), count( $nx ), $nx[ 0 ], end( $nx ), $nsd === null ? "no SDs" : "with SDs" );

## the factor relating two arrays point by point: the median ratio over the points well away from zero, and
## the largest deviation of any point from it, relative to the larger of the point and 1e-3 of the largest point
function rei_factor( $num, $den, &$factor, &$deviation ) {
    $big = 0;
    foreach ( $den as $v ) {
        $big = max( $big, abs( $v ) );
    }
    $ratios = [];
    foreach ( $den as $i => $v ) {
        if ( abs( $v ) >= 1e-3 * $big ) {
            $ratios[] = $num[ $i ] / $v;
        }
    }
    if ( !count( $ratios ) ) {
        $factor    = 0;
        $deviation = INF;
        return;
    }
    sort( $ratios );
    $factor    = $ratios[ intdiv( count( $ratios ), 2 ) ];
    $deviation = 0;
    foreach ( $den as $i => $v ) {
        $deviation = max( $deviation, abs( $num[ $i ] - $factor * $v ) / max( abs( $factor * $v ), 1e-3 * abs( $factor ) * $big ) );
    }
}

$k = 0;
$c = 0;
if ( count( $nx ) != $n ) {
    rei_problem( sprintf( "the new file has %d points, the stored curve %d: not the same q grid", count( $nx ), $n ) );
} else {
    $dq = 0;
    for ( $i = 0; $i < $n; ++$i ) {
        $dq = max( $dq, abs( $nx[ $i ] - $ox[ $i ] ) / max( abs( $ox[ $i ] ), 1e-12 ) );
    }
    if ( $dq > 1e-6 ) {
        rei_problem( sprintf( "the q grids differ ( largest relative difference %.2g )", $dq ) );
    } else {
        printf( "  q grid: identical ( largest relative difference %.2g )\n", $dq );
    }

    rei_factor( $ny, $oy, $k, $dev );
    printf( "  intensities: new = stored x %.6g, every point within %.2g of that ( tolerance %.2g )\n", $k, $dev, $tol );
    if ( $dev > $tol || $k <= 0 ) {
        rei_problem( "the new intensities are not the stored ones times one positive constant: not a rescaled copy of the same curve" );
    }

    if ( $nsd !== null && $osd !== null ) {
        rei_factor( $nsd, $osd, $c, $devsd );
        printf( "  SDs in the new file: stored x %.6g, every point within %.2g; relative errors x %.4g compared with the stored ones\n", $c, $devsd, $c / $k );
        if ( $sdmode == "file" && ( $devsd > $tol || $c <= 0 ) ) {
            rei_problem( "the new SDs are not the stored ones times one constant: with --sd file the fits would change, not just rescale" );
        }
    }
}

if ( !strlen( $sdmode ) ) {
    $sdmode = $nsd !== null ? "file" : "scaled";
    echo "  ( no --sd given: this check uses --sd $sdmode )\n";
}
if ( $sdmode == "file" && $nsd === null ) {
    rei_problem( "the new file has no SDs: use --sd scaled" );
}
if ( $osd === null ) {
    rei_problem( "the stored curve has no SDs" );
}
if ( count( $failures ) ) {
    echo "\nchecks failed, nothing written\n";
    exit( 1 );
}

## the SD factor actually applied, and the new experimental arrays
$csel = $sdmode == "file" ? $c : $k;
$newy = $ny;
if ( $sdmode == "file" ) {
    $newsd = $nsd;
} else {
    $newsd = [];
    foreach ( $osd as $v ) {
        $newsd[] = $v * $k;
    }
}
printf( "  --sd %s: SDs x %.6g, so residuals/SD x %.4g and nChi^2 x %.4g\n", $sdmode, $csel, $k / $csel, ( $k / $csel ) ** 2 );

## -- every stored plot that holds this experimental curve

function rei_find_figures( $node, $path, &$figures ) {
    if ( is_object( $node ) ) {
        if ( isset( $node->data ) && is_array( $node->data ) && count( $node->data )
             && is_object( $node->data[ 0 ] ) && isset( $node->data[ 0 ]->x ) && isset( $node->data[ 0 ]->y ) ) {
            $figures[] = [ $path, $node ];
            return;
        }
        foreach ( $node as $key => $v ) {
            if ( is_object( $v ) || is_array( $v ) ) {
                rei_find_figures( $v, strlen( $path ) ? "$path.$key" : $key, $figures );
            }
        }
    } elseif ( is_array( $node ) ) {
        foreach ( $node as $key => $v ) {
            if ( is_object( $v ) || is_array( $v ) ) {
                rei_find_figures( $v, $path . "[$key]", $figures );
            }
        }
    }
}

function rei_same_grid( $x ) {
    global $ox, $n;
    if ( !is_array( $x ) || count( $x ) != $n ) {
        return false;
    }
    for ( $i = 0; $i < $n; ++$i ) {
        if ( !is_numeric( $x[ $i ] ) || abs( $x[ $i ] - $ox[ $i ] ) > 1e-6 * max( abs( $ox[ $i ] ), 1e-12 ) ) {
            return false;
        }
    }
    return true;
}

function rei_is_stored_exp( $t ) {
    global $oy, $n;
    if ( !isset( $t->name ) || $t->name != "Exp. I(q)" || !rei_same_grid( $t->x ) || count( $t->y ) != $n ) {
        return false;
    }
    $big = max( array_map( 'abs', $oy ) );
    for ( $i = 0; $i < $n; ++$i ) {
        if ( abs( $t->y[ $i ] - $oy[ $i ] ) > 1e-6 * max( abs( $oy[ $i ] ), 1e-3 * $big ) ) {
            return false;
        }
    }
    return true;
}

function rei_scale( $array, $factor ) {
    $out = [];
    foreach ( $array as $v ) {
        $out[] = is_numeric( $v ) ? $v * $factor : $v;
    }
    return $out;
}

## residuals and fit statistics of one fit in a ( patched ) figure, computed with sas.php as the modules do
function rei_stats( $fig, $fitname, &$result ) {
    $sas = new SAS( false, false );
    if ( !$sas->create_plot_from_plot( SAS::PLOT_IQ, "figure", json_decode( json_encode( $fig ) ) )
         || !$sas->calc_residuals( "Exp. I(q)", $fitname, "__residuals" )
         || !$sas->create_plot( SAS::PLOT_IQ, "__residuals", [ "__residuals" ] ) ) {
        return $sas->last_error;
    }
    $result = (object)[ "residuals" => $sas->plot( "__residuals" )->data[ 0 ]->y ];
    $rmsd   = -1;
    $chi2   = -1;
    $scale  = 0;
    if ( !$sas->rmsd( "Exp. I(q)", $fitname, $rmsd ) || !$sas->scale_nchi2( "Exp. I(q)", $fitname, "__rescaled", $chi2, $scale ) ) {
        return $sas->last_error;
    }
    $result->rmsd = $rmsd;
    $result->chi2 = $chi2;
    $pvalue = (object)[];
    $sas->compute_p_value( "Exp. I(q)", $fitname, $pvalue );
    $result->p_value = isset( $pvalue->p_value ) ? $pvalue->p_value : null;
    return "";
}

$figures = [];
rei_find_figures( $state, "", $figures );

$newbase = basename( $newfile );
$oldbase = basename( $oldfile );
$touched = 0;
$mismatched = [];

echo "\nstored plots holding the experimental curve:\n";

foreach ( $figures as list( $path, $fig ) ) {
    $exp = null;
    foreach ( $fig->data as $t ) {
        if ( rei_is_stored_exp( $t ) ) {
            $exp = $t;
            break;
        }
    }
    if ( !$exp ) {
        foreach ( $fig->data as $t ) {
            if ( isset( $t->name ) && $t->name == "Exp. I(q)" ) {
                $mismatched[] = $path;
                break;
            }
        }
        continue;
    }
    ++$touched;
    echo "  $path\n";

    ## sort the other traces: residuals ( second y axis ), curves on the same grid ( fitted to the data ), others
    $residuals = [];
    $scaled    = [];
    $others    = [];
    foreach ( $fig->data as $t ) {
        if ( $t === $exp ) {
            continue;
        }
        if ( isset( $t->yaxis ) && $t->yaxis == "y2" ) {
            $residuals[] = $t;
        } elseif ( rei_same_grid( $t->x ) ) {
            $scaled[] = $t;
        } else {
            $others[] = $t;
        }
    }

    $oldfig = json_decode( json_encode( $fig ) );   ## the stored figure, for recomputing its own statistics

    ## the experimental curve, and the curves fitted to it ( linear in the data: they scale exactly )
    $exp->y = $newy;
    if ( isset( $exp->error_y ) ) {
        $exp->error_y->array = $newsd;
    }
    foreach ( $scaled as $t ) {
        $t->y = rei_scale( $t->y, $k );
        if ( isset( $t->error_y->array ) ) {
            $t->error_y->array = rei_scale( $t->error_y->array, $k );
        }
    }
    echo "    experimental curve replaced" . ( count( $scaled ) ? ", " . count( $scaled ) . " fitted curve(s) x $k" : "" ) . "\n";
    if ( count( $others ) ) {
        echo "    left alone ( not on the experimental q grid ): " . implode( ", ", array_map( function( $t ) { return isset( $t->name ) ? $t->name : "?"; }, $others ) ) . "\n";
    }

    ## the fit each residual belongs to: "X fit Res./SD" -> "X NNLS fit", a lone "Res./SD" -> the only fitted curve.
    ## Its residuals and statistics are recomputed with sas.php twice: on the stored data, which must reproduce the
    ## stored residuals and annotation ( so the fit and the recipe are the right ones ), and on the new data, which
    ## must give the stored values rescaled by the factors.
    $statsfit = null;
    foreach ( $residuals as $t ) {
        $fitname = null;
        if ( substr( $t->name, -strlen( " fit Res./SD" ) ) == " fit Res./SD" ) {
            $fitname = substr( $t->name, 0, -strlen( " fit Res./SD" ) ) . " NNLS fit";
        } elseif ( $t->name == "Res./SD" && count( $scaled ) == 1 ) {
            $fitname = $scaled[ 0 ]->name;
        }
        $found = false;
        foreach ( $scaled as $s ) {
            $found = $found || $s->name == $fitname;
        }
        if ( !$found ) {
            rei_problem( "$path: no fit found for the residuals '$t->name'" );
            continue;
        }
        $old = null;
        $new = null;
        $err = rei_stats( $oldfig, $fitname, $old );
        if ( !strlen( $err ) ) {
            $err = rei_stats( $fig, $fitname, $new );
        }
        if ( strlen( $err ) ) {
            rei_problem( "$path: residuals of '$fitname' could not be computed: $err" );
            continue;
        }
        $big     = max( 1e-300, max( array_map( 'abs', $t->y ) ) );
        $replay  = 0;
        $rescale = 0;
        foreach ( $old->residuals as $i => $v ) {
            $replay  = max( $replay, abs( $v - $t->y[ $i ] ) / $big );
            $rescale = max( $rescale, abs( $new->residuals[ $i ] - $v * $k / $csel ) / ( $big * $k / $csel ) );
        }
        if ( $replay > 1e-6 ) {
            rei_problem( sprintf( "$path: sas.php does not reproduce the stored residuals of '$fitname' ( off by %.2g of their range )", $replay ) );
        }
        if ( $rescale > 1e-2 ) {
            rei_problem( sprintf( "$path: the new residuals of '$fitname' are not the stored ones x %.4g ( off by %.2g of their range )", $k / $csel, $rescale ) );
        }
        $t->y     = $new->residuals;
        $statsfit = [ $fitname, $old, $new ];
        printf( "    residuals of '%s' recomputed: = stored x %.4g to within %.2g of their range\n", $fitname, $k / $csel, $rescale );
    }
    if ( !$statsfit && count( $scaled ) == 1 && !count( $residuals ) ) {
        $old = null;
        $new = null;
        if ( !strlen( rei_stats( $oldfig, $scaled[ 0 ]->name, $old ) ) && !strlen( rei_stats( $fig, $scaled[ 0 ]->name, $new ) ) ) {
            $statsfit = [ $scaled[ 0 ]->name, $old, $new ];
        }
    }

    ## the annotation: the file name on the Load SAXS plot, the fit statistics on the others
    if ( isset( $fig->layout->annotations[ 0 ]->text ) ) {
        $text = $fig->layout->annotations[ 0 ]->text;
        if ( strlen( $oldbase ) && strpos( $text, $oldbase ) !== false ) {
            $text = str_replace( $oldbase, $newbase, $text );
            echo "    annotation: file name updated\n";
        }
        if ( preg_match( '/RMSD|nChi\^2|P-value/', $text ) ) {
            if ( !$statsfit ) {
                rei_problem( "$path: the annotation has fit statistics but no fit to recompute them from" );
            } else {
                list( $fitname, $old, $new ) = $statsfit;
                $num = '(-?[0-9.]+(?:[eE][-+]?[0-9]+)?)';
                ## the stored annotation must be what sas.php gives on the stored data ( rounded as the modules round )
                if ( preg_match( "/RMSD $num/", $text, $m ) && abs( floatval( $m[ 1 ] ) - round( $old->rmsd, 3 ) ) > 0.0011 ) {
                    rei_problem( "$path: stored RMSD $m[1] is not reproduced by sas.php ( " . round( $old->rmsd, 3 ) . " )" );
                }
                if ( preg_match( "/nChi\\^2 $num/", $text, $m ) && abs( floatval( $m[ 1 ] ) - round( $old->chi2, 3 ) ) > 0.0011 ) {
                    rei_problem( "$path: stored nChi^2 $m[1] is not reproduced by sas.php ( " . round( $old->chi2, 3 ) . " )" );
                }
                if ( preg_match( '/P-value ([0-9.]+)/', $text, $m ) && $old->p_value !== null && abs( floatval( $m[ 1 ] ) - $old->p_value ) > 0.0011 ) {
                    rei_problem( sprintf( "$path: stored P-value %s is not reproduced by sas.php ( %.3f )", $m[ 1 ], $old->p_value ) );
                }
                ## and the new statistics must be the old ones rescaled
                if ( abs( $new->rmsd - $old->rmsd * $k ) > 1e-2 * abs( $old->rmsd * $k ) ) {
                    rei_problem( "$path: new RMSD $new->rmsd is not the stored $old->rmsd x $k" );
                }
                if ( abs( $new->chi2 - $old->chi2 * ( $k / $csel ) ** 2 ) > 1e-2 * abs( $old->chi2 * ( $k / $csel ) ** 2 ) ) {
                    rei_problem( "$path: new nChi^2 $new->chi2 is not the stored $old->chi2 x " . round( ( $k / $csel ) ** 2, 4 ) );
                }
                if ( $old->p_value !== null && $new->p_value !== null && abs( $new->p_value - $old->p_value ) > 0.01 ) {
                    rei_problem( sprintf( "$path: new P-value %.3f differs from the stored %.3f", $new->p_value, $old->p_value ) );
                }
                $rmsd = round( $new->rmsd, 3 );
                $chi2 = round( $new->chi2, 3 );
                $text = preg_replace( "/RMSD $num/", "RMSD $rmsd", $text );
                $text = preg_replace( "/nChi\\^2 $num/", "nChi^2 $chi2", $text );
                if ( $new->p_value !== null ) {
                    $color = $new->p_value >= 0.05 ? 'green' : ( $new->p_value >= 0.01 ? 'yellow' : 'red' );
                    $text  = preg_replace( "/P-value [0-9.]+ <span style='color:[a-z]+'>/", sprintf( "P-value %.3f <span style='color:%s'>", $new->p_value, $color ), $text );
                }
                printf( "    annotation recomputed for '%s': RMSD %s -> %s, nChi^2 %s -> %s, P-value %s -> %s\n", $fitname,
                        round( $old->rmsd, 3 ), $rmsd, round( $old->chi2, 3 ), $chi2,
                        $old->p_value === null ? "-" : sprintf( "%.3f", $old->p_value ), $new->p_value === null ? "-" : sprintf( "%.3f", $new->p_value ) );
            }
        }
        $fig->layout->annotations[ 0 ]->text = $text;
    }
}

if ( !$touched ) {
    rei_problem( "no stored plot holds the experimental curve" );
}
if ( count( $mismatched ) ) {
    echo "  left alone, they hold a different experimental curve than Load SAXS: " . implode( ", ", $mismatched ) . "\n";
}

## -- the experimental Guinier result, recomputed with the settings it was made with

if ( isset( $state->exp_guinier ) ) {
    $params = isset( $state->exp_guinier->params ) ? (array) $state->exp_guinier->params : [];
    $origin = isset( $state->exp_guinier->origin ) ? $state->exp_guinier->origin : "loadsaxs";
    $sasg   = new SAS( false, false );
    $r      = null;
    if ( $sasg->create_plot_from_plot( SAS::PLOT_IQ, "I(q)", json_decode( json_encode( $state->output_loadsaxs->iqplot ) ) )
         && $sasg->guinier_search( "Exp. I(q)", $r, $params ) ) {
        printf( "\nexperimental Guinier ( settings %s, from %s ): Rg %.2f -> %.2f A, I(0) %.6g -> %.6g\n",
                count( $params ) ? json_encode( $params ) : "automatic", $origin,
                $state->exp_guinier->rg, $r->rg, $state->exp_guinier->i0, $r->i0 );
        $r->params = (object) $params;
        $r->origin = $origin;
        if ( isset( $state->output_loadsaxs->guinierplot ) ) {
            $state->output_loadsaxs->guinierplot = $sasg->guinier_search_plot( "Exp. I(q)", $r, "Guinier plot of $newbase" );
            echo "  Load SAXS Guinier plot rebuilt\n";
        }
        if ( $origin == "loadsaxs" && isset( $state->output_loadsaxs->_textarea ) ) {
            $state->output_loadsaxs->_textarea =
                "Experimental I(q) " . SAS::guinier_search_summary_text( $r ) . "\n"
                . ( count( $params ) ? "  Guinier settings: " . json_encode( $params ) . "\n" : "" )
                . ( count( $r->warnings ) ? "  " . implode( "\n  ", $r->warnings ) . "\n" : "" );
            echo "  Load SAXS log text rebuilt\n";
        }
        $state->exp_guinier = $r;
    } else {
        rei_problem( "the Guinier search failed on the new curve: " . $sasg->last_error );
    }
}

## -- file name, download link, q range

if ( strlen( $oldfile ) && realpath( dirname( $oldfile ) ) !== realpath( getcwd() ) ) {
    rei_problem( "the stored I(q) file $oldfile is not in this project directory" );
}
$newpath = ( strlen( $oldfile ) ? dirname( $oldfile ) : getcwd() ) . "/$newbase";
$copyneeded = realpath( dirname( $newfile ) ) !== realpath( getcwd() );
if ( $copyneeded && file_exists( $newbase ) && md5_file( $newbase ) !== md5_file( $newfile ) ) {
    rei_problem( "a different file named $newbase is already in the project directory" );
}
echo "\nsaxsiqfile: $oldbase -> $newbase" . ( $copyneeded ? " ( copied into the project )" : "" ) . "\n";
$state->saxsiqfile = $newpath;
if ( isset( $state->output_load->downloads ) && strlen( $oldbase ) && strpos( $state->output_load->downloads, $oldbase ) !== false ) {
    $state->output_load->downloads = str_replace( $oldbase, $newbase, $state->output_load->downloads );
    echo "Load structure I(q) download link updated\n";
}
if ( isset( $state->qpoints ) && $state->qpoints != $n ) {
    rei_problem( "state qpoints $state->qpoints does not match the curve's $n points" );
}

## -- what still shows the old scale

$images = [];
if ( isset( $state->output_iqpr ) ) {
    foreach ( $state->output_iqpr as $key => $v ) {
        if ( is_string( $v ) && strpos( $v, "data:image/png" ) !== false ) {
            $images[] = "output_iqpr.$key";
        }
    }
}
$csvs = [];
foreach ( glob( "*.csv" ) as $f ) {
    $fh   = fopen( $f, "r" );
    $line = $fh ? fgets( $fh, 65536 ) : "";
    $fh && fclose( $fh );
    if ( strpos( $line, "Exp." ) !== false || strpos( $line, "Type; q:" ) !== false ) {
        $csvs[] = $f;
    }
}
echo "\nnot changed, still on the old scale:\n";
echo "  images: " . ( count( $images ) ? implode( ", ", $images ) : "none" ) . "\n";
echo "  csv downloads: " . ( count( $csvs ) ? implode( ", ", $csvs ) : "none" ) . " ( Final model's are rewritten by its rerun )\n";
echo "  the experimental P(r)\n";

## -- write

if ( count( $failures ) ) {
    echo "\nchecks failed, nothing written\n";
    exit( 1 );
}
if ( $mode == "check" ) {
    echo "\nall checks passed. nothing written ( --check ); run again with --apply FILE --sd $sdmode to make the change\n";
    exit( 0 );
}

$backup = "state.json." . date( "Ymd-His" ) . ".before_replace_expt_iq";
if ( !copy( "state.json", $backup ) ) {
    rei_setup_error( "could not back up state.json to $backup, nothing written" );
}
chmod( $backup, 0660 );
if ( $copyneeded && !file_exists( $newbase ) && !copy( $newfile, $newbase ) ) {
    rei_setup_error( "could not copy $newfile into the project, nothing written" );
}
if ( !$cgstate->save() ) {
    rei_setup_error( "could not write state.json: $cgstate->errors ( the backup $backup is intact )" );
}
echo "\nwritten. backup of the previous state: $backup\n";
echo "next: rerun Final model with the same settings to redo the final NNLS on the new data\n";
exit( 0 );
