#!/usr/bin/perl

use File::Temp qw(tempdir);
use File::Temp qw(tempfile);
use File::Basename;
use Cwd qw(cwd);
use JSON;
use Data::Dumper;

$scriptdir = dirname(__FILE__);
require "$scriptdir/utility.pm";
# require "$scriptdir/mapping.pm";
require "$scriptdir/pdbutil.pm";

## user config

$setupsomodir = "$scriptdir/../utils/somoinit/setupsomodir.pl";
$ultrascan    = "/ultrascan3";  # where ultrascan source is installed
$threads      = 6;
$debug++;

%progress_weights = (
    "cd" => 10
    ,"bm" => 20
    ,"pr" => 20
    ,"ch" => 60
    ,"pp" => 10
);    

## end user config

## developer config

$somo  = "env HOME=`pwd` $scriptdir/../utils/xvfb-run-safe us_somo.sh -p -g ";

$progress_tot_weight   = 99;
$progress_start_offset = 4; ## for chimera

@progress_seq = (
    "cd"
    ,"bm"
    ,"pr"
    ,"ch"
    ,"pp"
    );

## end developer config

$| = 1;

## progress utility

sub progress_init {
    my $p = $progress_start_offset;

    for my $k ( @progress_seq ) {
        error_exit( "missing progress weight $k" ) if !exists $progress_weights{$k};
        $progress_base{$k} = $p;
        $p += $progress_weights{$k};
    }

    $progress_total_weight = $p;
}

sub progress {
    my $p = shift;
    my ( $k, $val ) = $p =~ /_*~pgrs\s+(\S+)\s+:\s+(\S+)\s*$/;
    
    error_exit( "missing progress base '$k'" ) if !exists $progress_base{$k};
#    print "progress string '$p' k $k val $val\n";

    sprintf( "%.3f", ( $progress_base{$k} + $val * $progress_weights{$k} ) / $progress_total_weight );
}
    
sub progress_test {
    for my $i ( @progress_seq ) {
        for my $j ( 0, .5, 1 ) {
            print sprintf( "$i $j : %s\n", progress( "~pgrs $i : $j" ) );
        }
    }
    error_exit( "testing" );
}

progress_init();

## end progress utility

$notes = "usage: $0 pdb

computes P(r) on pdb

";

$f = shift || die $notes;

error_exit( "$f does not exist" ) if !-e $f;
error_exit( "$f is not readable" ) if !-r $f;
error_exit( "$f does not end with .pdb" ) if $f !~ /\.pdb$/;
error_exit( "$setupsomodir does not exist" ) if !-e $setupsomodir;
error_exit( "$setupsomodir is not readable" ) if !-r $setupsomodir;
error_exit( "$setupsomodir is not executable" ) if !-x $setupsomodir;
    
if ( !-e "ultrascan/etc/usrc.conf" ) {
    run_cmd( "$setupsomodir `pwd`" );
} else {
    print "skipping usrc init, already present\n" if $debug;
}

# check if newer revision present

{
    my $revfile  = "$ultrascan/us_somo/develop/include/us_revision.h";
    my $instfile = "ultrascan/etc/somorevision";
    error_exit( "$revfile does not exist" ) if !-e $revfile;
    my $usrev   = `head -1 /ultrascan3/us_somo/develop/include/us_revision.h | awk -F\\\" '{ print \$2 }'`;
    chomp $usrev;
    my $instrev = `cat $instfile`;
    chomp $instrev;
    if ( $usrev ne $instrev ) {
        `echo $usrev > $instfile`;
        for my $f (
            "somo.atom"
            ,"somo.config"
            ,"somo.defaults"
            ,"somo.hybrid"
            ,"somo.hydrated_rotamer"
            ,"somo.residue"
            ,"somo.saxs_atoms"
            ) {
            my $source = "$ultrascan/us_somo/etc/$f.new";
            error_exit( "$source does not exist" ) if !-e $source;
            error_exit( "$source is not readable" ) if !-r $source;
            print `cp $ultrascan/us_somo/etc/$f.new ultrascan/etc/$f`;
        }
        print "new configs installed\n";
    } else {
        print "revision ok\n";
    }
    print "somo revision: $usrev\n";
}

if ( 0 ) {
## rename pdb if required
{
    my ( $name, $ext ) = $f =~ /^(.*)\.(pdb)$/i;
    my $oname = $name;
    $name =~ s/[^a-zA-Z0-9_-]+/_/g;
    if ( $oname ne $name ) {
        $cmd = qq{ln -f "$oname.$ext" $name.$ext};
        run_cmd( $cmd, true );
        if ( run_cmd_last_error() ) {
            error_exit( sprintf( "ERROR [%d] - attempting to rename $cmd", run_cmd_last_error() ) );
        } else {
            print "__+mv 1 : Notice: renamed $oname.$ext to $name.$ext due to unsupported characters in the name\n";
        }
        $f = "$name.$ext";
    }
}

## extract 1st model from multi-model pdb

{
    my $cmd = "grep -P '^MODEL' $f\n";
    my $res = run_cmd( $cmd, true );
    my @l = split /\n/, $res;
    print "grep result:\n$res\n";
# grep will return an error of no match found
#    if ( run_cmd_last_error() ) {
#        error_exit( sprintf( "ERROR [%d] - $f checking for multiple models $cmd", run_cmd_last_error() ) );
#    }
    if ( @l > 1 ) {
        # print "__+mm 1 : multi-model pdb found\n";
        my $cmd = "$scriptdir/pdbsinglemodel.pl $f";
        my $res = run_cmd( $cmd, true );
        if ( run_cmd_last_error() ) {
            error_exit( sprintf( "ERROR [%d] - $f extracting single model from pdb $cmd", run_cmd_last_error() ) );
        }
        chomp $res;
        $f = $res;
        print "__+mm 1 : NOTICE: only first model from the provided multi-model pdb will be processed\n";
    }
}        
}

## run somo

$fpdbnoext = $f;
$fpdbnoext =~ s/\.pdb$//i;
$fpdbnoextnopath = $fpdbnoext;
$fpdbnoextnopath =~ s/^.*\///g;
( $modelno ) = $fpdbnoextnopath =~ /-m0*(\d+)$/;
$modelno = 1 if !$modelno;

$fpdb = $f;

my ( $fh, $ft ) = tempfile( "somocmds.XXXXXX", UNLINK => 1 );
print $fh
    "threads $threads\n"
    . "norasmol\n"
    . "progress prog_prefix\n"
    . "batch selectall\n"
    . "batch prr\n"
    . "somo overwrite\n"
    . "batch overwrite\n"
    . "batch start\n"
    . "exit\n"
    ;
close $fh;

## run somo

my $prfile    = "ultrascan/somo/saxs/${fpdbnoextnopath}_${modelno}b1.sprr_x";
my $hydrofile = "ultrascan/somo/$fpdbnoext.csv";

my @expected_outputs =
    (
     $prfile
    );

$cmd = "$somo $ft $fpdb";

print "command is $cmd\n";

print "__+somo 0 : P(r) calculations starting\n";

open $ch, "$cmd 2>&1 |";

$processlog = "";

while ( my $l = <$ch> ) {
    # print "read line:\n$l\n";
    if ( $l =~ /^~pgrs/ ) {
        print sprintf( "__~pgrs al : %s\n", progress( $l ) );
        next;
    }
    if ( $l =~ /^~texts/ ) {
        my ( $tag ) = $l =~ /^~texts (.*) :/;
        my $lastblank = 0;
        while ( my $l = <$ch> ) {
            if ( $l =~ /^~texte/ ) {
                last;
            }
            next if $l =~ /(^All options set to default values| created\.$|^Bead models have overlap, dimensionless|^Created)/;
            my $thisblank = $l =~ /^\s*$/;
            next if $thisblank && $lastblank;
            $tagcounts{$tag}++;
            print "__+$tag $tagcounts{$tag} : $l";
            $processlog .= $l;
            $lastblank = $thisblank;
        }
    }
}
close $ch;
$last_exit_status = $?;

print "__+somo 99999 : P(r) computations complete\n";
print "__+pp 1 : finalizing results\n";
print sprintf( "__~pgrs al : %s\n", progress( "~pgrs pp : 0" ) );

## cleanup extra files
unlink glob "ultrascan/somo/$fpdbnoext*{asa_res,bead_model,hydro_res,bod}";

## check run was ok
if ( $last_exist_status ) {
    my $error = sprintf( "$0: ERROR [%d] - $fpdb running SOMO computation $cmd\n", run_cmd_last_error() );
    $errors .= $error;
} else {
    for my $eo ( @expected_outputs ) {
        print "checking for: $eo\n";
        if ( !-e $eo ) {
            my $error = "__: ERROR - $fpdb SOMO expected result $eo was not created\n";
            $errors .= $error;
            next;
        }
    }
}

error_exit( $errors ) if $errors;

print "$prfile\n";

### rename and move p(r)
#
#{
#    my $cmd = "mv $prfile ultrascan/results/${fpdbnoext}-pr.dat";
#    run_cmd( $cmd, true );
#    if ( run_cmd_last_error() ) {
#        error_exit(sprintf( "ERROR [%d] - $fpdb mv error $cmd", run_cmd_last_error() ) );
#    }
#}
