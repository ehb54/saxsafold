#!/usr/bin/perl

use File::Temp qw(tempdir);
use File::Temp qw(tempfile);
use File::Basename;
use Cwd qw(cwd);
use JSON;
use Data::Dumper;

$scriptdir = dirname(__FILE__);
require "$scriptdir/utility.pm";
require "$scriptdir/mapping.pm";
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
    ,"pp" => 50
);    

## end user config

## developer config

$somo  = "env HOME=`pwd` $scriptdir/../utils/xvfb-run-safe us_somo.sh -p -g ";
$maxit = "env RCSBROOT=/maxit-v11.100-prod-src /maxit-v11.100-prod-src/bin/maxit";

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

$notes = "usage: $0 pdb|cif

loads structure, some initial calcs

";

$f = shift || die $notes;

error_exit( "$f does not exist" ) if !-e $f;
error_exit( "$f is not readable" ) if !-r $f;
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

## convert cif to pdb

if ( $f =~ /cif$/ ) {
    my $cif  = $f;
    my $pdb  = $f;
    $pdb     =~ s/\.cif/.pdb/i;
    my $logf = "maxitcif2pdb.log";
    {
        my $cmd = "$maxit -input $cif -output $pdb -o 2 -log $logf";
        run_cmd( $cmd, true );
        if ( run_cmd_last_error() ) {
            error_exit( "ERROR [%d] - $f running maxit cif->pdb $cmd\n", run_cmd_last_error() );
        }
    }
    {
        # add title source for cif
        my $cmd = "$scriptdir/cif2pdbtitlesource.pl $cif $pdb";
        run_cmd( $cmd, true );
        if ( run_cmd_last_error() ) {
            error_exit( "ERROR [%d] - cif->pdb $cmd\n", run_cmd_last_error() );
        }
    }
    {
        # mean confidence if confidence json available
        my $cifbase = $cif;
        $cifbase =~ s/-model.*\.cif$//;
        my $globmatch = "${cifbase}*confidence*.json";
        my @conffile = glob( $globmatch );
        if ( @conffile == 1 ) {
            my $conffile = $conffile[0];
            my $jsonstr  = `cat $conffile`;
            my $json     = decode_json( $jsonstr );
            my $sumconf = 0;
            for ( my $i = 0; $i < @{$$json{confidenceScore}}; ++$i ) {
                $sumconf += $$json{confidenceScore}[$i];
            }
            $afmeanconf = sprintf( "%.2f", $sumconf / scalar @{$$json{confidenceScore}} );
        }
    }
    $f = $pdb;
}    

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

## prepare pdb

print "__+in 1 : prepare structure starting\n";
print "__+in 1b : \n";
{
    my $cmd = "$scriptdir/prepare.pl $f";
    run_cmd( $cmd, true );
    if ( run_cmd_last_error() ) {
        error_exit( sprintf( "ERROR [%d] - $f running prepare computation $cmd", run_cmd_last_error() ) );
    } else {
        print "__+in 2 : prepare structure complete\n";
    }
}

## run somo

$fpdbnoext = $f;
$fpdbnoext =~ s/\.pdb$//i;
$fpdb = "$fpdbnoext-somo.pdb";
$somoloadstatsfile = "$fpdbnoext-loadstats.csv";

my ( $fh, $ft ) = tempfile( "somocmds.XXXXXX", UNLINK => 1 );
print $fh
    "threads $threads\n"
    . "norasmol\n"
    . "progress prog_prefix\n"
    . "saveparams init\n"
    . "saveparams results.name\n"
    . "saveparams results.mass\n"
    . "saveparams results.vbar\n"
    . "saveparams results.D20w\n"
    . "saveparams results.D20w_sd\n"
    . "saveparams results.s20w\n"
    . "saveparams results.s20w_sd\n"
    . "saveparams results.rs\n"
    . "saveparams results.rs_sd\n"
    . "saveparams results.viscosity\n"
    . "saveparams results.viscosity_sd\n"
    . "saveparams results.asa_rg_pos\n"
    . "saveparams max_ext_x\n"
    . "batch set residue stop\n"
    . "batch set atom stop\n"
    . "batch selectall\n"
    . "batch set residue info\n"
    . "batch set atom info\n"
    . "batch load\n"
    . "batch saveparams\n"
    . "somo overwrite\n"
    . "batch overwrite\n"
    . "somo saveloadstats ../$somoloadstatsfile\n"
    . "exit\n"
    ;
close $fh;

## compute CD spectrum
### disabled
if ( 0 ) {
    my $pwd = cwd;
    my $template = "cdrun.XXXXXXXXX";
    my $dir = tempdir( $template, CLEANUP => 1 );
    my $cdcmd = "python2 /SESCA/scripts/SESCA_main.py \@pdb";

    my $fb  =  $fpdb;
    print sprintf( "__~pgrs al : %s\n", progress( "~pgrs cd : 0" ) );
    print "__+cd 1 : compute CD spectrum starting\n";
    print "__+cd 1b : \n";
    my $cmd = "ln $fpdb $dir/ && cd $dir && $cdcmd $fpdb && grep -v Workdir: CD_comp.out | perl -pe 's/ \\/srv.*SESCA\\// SESCA\\//' > $pwd/ultrascan/results/${fpdbnoext}-sesca-cd.dat";
    run_cmd( $cmd, true );
    if ( run_cmd_last_error() ) {
        error_exit( sprintf( "ERROR [%d] - $fpdb running SESCA computation $cmd\n", run_cmd_last_error() ) );
    } else {
        print "__+cd 2 : compute CD spectrum complete\n";
        print sprintf( "__~pgrs al : %s\n", progress( "~pgrs cd : 1" ) );
    }
}

## run somo

my $prfile    = "ultrascan/somo/saxs/${fpdbnoext}-somo_1b1.sprr_x";
my $hydrofile = "ultrascan/somo/$fpdbnoext.csv";

my @expected_outputs =
    (
#     $hydrofile
#     ,$prfile
    );

## clean up before running

unlink glob "ultrascan/somo/$fpdbnoext*";
unlink glob "ultrascan/somo/saxs/$fpdbnoext*";

$cmd = "$somo $ft $fpdb";

# my $cmd = "$$p_config{somoenv} && cd $$p_config{pdbstage2} && $$p_config{somorun} -g $ft $fpdb";
# run_cmd( $cmd, true, 4 ); # try 2x since very rarely zeno call crashes and/or hangs?

print "command is $cmd\n";
print "__+somo 0 : Structural calculations starting\n";

open $ch, "$cmd 2>&1 |";

$processlog = "";

$errors_in_pdb_reported = 0;
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
            if ( $l =~ /ERRORS PRESENT/ && !$errors_in_pdb_reported ) {
                $errors .= $l;
                $errors_in_pdb_reported++;
            }
            if ( $l =~ /^~texte/ ) {
                last;
            }
            next if $l =~ /(^All options set to default values| created\.$|^Bead models have overlap, dimensionless|hybrid name missing|^PDB Options|^Created)/;
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

print "__+somo 99999 : Structural computations complete\n";
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

## rename and move p(r)

### p(r) disabled

if ( 0 ) {
    my $cmd = "mv $prfile ultrascan/results/${fpdbnoext}-pr.dat";
    run_cmd( $cmd, true );
    if ( run_cmd_last_error() ) {
        error_exit(sprintf( "ERROR [%d] - $fpdb mv error $cmd", run_cmd_last_error() ) );
    }
}

## build up data for mongo insert

my %data;

# get any somo "load stats"
$somoloadstatsfile = "ultrascan/somo/$somoloadstatsfile";
error_exit( "unexpected: $somoloadstatsfile does not exist" ) if !-e $somoloadstatsfile;

print "__+pp 2 : checking stats file\n";
{
    my @hdata = `cat $somoloadstatsfile`;

    if ( @hdata != 2 ) {
        error_exit( "ERROR - $fpdb SOMO expected result $somoloadstatsfile does not contain 2 lines");
    }

    grep chomp, @hdata;

    ## split up csv and validate parameters
    {
        my @headers = split /,/, $hdata[0];
        my @params  = split /,/, $hdata[1];

        grep s/"//g, @headers;

        my %hmap = map { $_ => 1 } @headers;

        ## are all headers present?

        for my $k ( keys %csvhl2mongo ) {
            if ( !exists $hmap{$k} ) {
                error_exit( "ERROR - $fpdb SOMO expected result $hydrofile does not contain header '$k'" );
            }
        }

        ## create data
        for ( my $i = 0; $i < @headers; ++$i ) {
            my $h = $headers[$i];

            ## skip any extra fields
            next if !exists $csvhl2mongo{$h};

            $data{ $csvhl2mongo{$h} } = $params[$i];
        }
    }
}

## extract csv info for creation of mongo insert

### disabled

if ( 0 ) {
    error_exit( "unexpected: $hydrofile does not exist" ) if !-e $hydrofile;

    {
        my @hdata = `cat $hydrofile`;

        if ( @hdata != 2 ) {
            error_exit( "ERROR - $fpdb SOMO expected result $hydrofile does not contain 2 lines" );
        }

        grep chomp, @hdata;

        ## split up csv and validate parameters
        {
            my @headers = split /,/, $hdata[0];
            my @params  = split /,/, $hdata[1];

            grep s/"//g, @headers;

            my %hmap = map { $_ => 1 } @headers;
            
            ## are all headers present?

            for my $k ( keys %csvh2mongo ) {
                if ( !exists $hmap{$k} ) {
                    error_#xit("ERROR - $fpdb SOMO expected result $hydrofile does not contain header '$k'" );
                }
            }

            ## create data
            for ( my $i = 0; $i < @headers; ++$i ) {
                my $h = $headers[$i];

                ## skip any extra fields
                next if !exists $csvh2mongo{$h};

                $data{ $csvh2mongo{$h} } = $params[$i];
            }

        }
    }
}

## additional fields
# $data{_id}      = "${id}-${pdb_frame}${pdb_variant}";
$data{name}     = $fpdb; ## or $f?
my $processing_date = `date`;
chomp $processing_date;
$data{somodate} = $processing_date;

### additional fields from the pdb
print "__+pp 3 : getting additional fields from the pdb\n";
{
    my @lpdb     = `cat $fpdb`;
    grep chomp, @lpdb;

#    {
#        my @lheaders = grep /^HEADER/, @lpdb;
#        if ( @lheaders != 1 ) {
#            error_exit( "ERROR - $fpdb pdb does not contain exactly one header line" );
#        } else {
#            if ( $lheaders[0] =~ /HEADER\s*(\S+)\s*$/ ) {
#                $data{afdate} = $1;
#            } else {
#                $data{afdate} = "unknown";
#            }
#        }
#    }
    {
        my @lsource  = grep /^SOURCE/, @lpdb;
        grep s/^SOURCE   .//, @lsource;
        grep s/\s*$//, @lsource;
        my $src = join '', @lsource;
        if ( $src ) {
            $data{source} = $src;
        } else {
            $data{source} = "unknown";
        }
    }
    {
        my @ltitle  = grep /^TITLE/, @lpdb;
        grep s/^TITLE   ..//, @ltitle;
        grep s/\s*$//, @ltitle;
        my $title = join '', @ltitle;
        $title =~ s/^\s*//;
        if ( $title ) {
            $data{title} = $title;
        } else {
            $data{title} = "unknown";
        }
    }
    {
        my $pdbinfo = run_cmd( "$scriptdir/pdbinfo.pl $fpdb" );
        if ( run_cmd_last_error() ) {
            error_exit( sprintf( "ERROR [%d] - $fpdb extrading pdb chain and sequence info", run_cmd_last_error() ) );
        } else {
            $data{pdbinfo} = $pdbinfo;
        }
    }

    #### helix/sheet

    {
        my $lastresseq = 0;
        my $helixcount = 0;
        my $sheetcount = 0;

        for my $l ( @lpdb ) {
            my $r = pdb_fields( $l );
            my $recname = $r->{recname};
            if ( $recname =~ /^HELIX/ ) {
                my $initseqnum = $r->{initseqnum};
                my $endseqnum  = $r->{endseqnum};
                $helixcount += $endseqnum - $initseqnum;
                next;
            } elsif ( $recname =~ /^SHEET/ ) {
                my $initseqnum = $r->{initseqnum};
                my $endseqnum  = $r->{endseqnum};
                $sheetcount += $endseqnum - $initseqnum;
                next;
            } elsif ( $recname =~ /^ATOM/ ) {
                my $resseq = $r->{resseq};
                if ( $lastresseq != $resseq ) {
                    $lastresseq = $resseq;
                    ++$rescount;
                }
            }
        }

        $data{helix} = sprintf( "%.2f", $helixcount * 100.0 / ( $rescount - 1.0 ) );
        $data{sheet} = sprintf( "%.2f", $sheetcount * 100.0 / ( $rescount - 1.0 ) );
    }

    $data{Dtr} *= 1e7;
    $data{Dtr_sd} *= 1e7;

    $data{afmeanconf} = $afmeanconf ? $afmeanconf : "n/a";

    for my $k ( keys %data ) {
        print "__: $k : $data{$k}\n";
    }

    ## create results csv
    {
        my $csvdata = qq{"Model name","Title","Source","Hydrodynamic calculations date","Chains residue sequence start and end","Molecular mass [Da]","Partial specific volume [cm^3/g]","Hydration [g/g]","Radius of gyration (+r) [A] (from PDB atomic structure)","Maximum extensions X [nm]","Maximum extensions Y [nm]","Maximum extensions Z [nm]","Helix %","Sheet %"\n};
        $csvdata .= qq{"$f","$data{title}","$data{source}","$data{somodate}","$data{pdbinfo}",$data{mw},$data{psv},$data{hyd},$data{Rg},$data{ExtX},$data{ExtY},$data{ExtZ},$data{helix},$data{sheet}\n};
        open OUT, ">ultrascan/results/${fpdbnoext}.csv";
        print OUT $csvdata;
        close OUT;
    }
}

## add processlog
print "__+pp 4 : creating process log\n";

{
    my $fo = "ultrascan/results/${fpdbnoext}-process-log.txt";
    open my $fh, ">$fo";
    print $fh $processlog;
    close $fh;
}

## make tar & zip files
print "__+pp 5 : make tar & zip files\n";

{
    my $template = "zip.XXXXXXXXX";
    my $dir = tempdir( $template, CLEANUP => 1 );
    ### link contents into tar directory
    my $cmd =
        "cd $dir"
        . " && ln ../ultrascan/results/*  ."
        . " && rm AF*-tfc-somo.pdb; "
        . " tar Jcf ../ultrascan/results/${fpdbnoext}-somo.txz *"
        . " && zip ../ultrascan/results/${fpdbnoext}-somo.zip *"
        ;
    run_cmd( $cmd, true );
    if ( run_cmd_last_error() ) {
        error_exit( sprintf( "$0: ERROR [%d] - $fpdb error creating txz & zips $cmd", run_cmd_last_error() ) );
    }
}
print sprintf( "__~pgrs al : %s\n", progress( "~pgrs pp : .5" ) );

print "__~finished : done\n";
