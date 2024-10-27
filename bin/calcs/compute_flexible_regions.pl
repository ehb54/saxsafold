#!/usr/bin/perl

use File::Basename;
my $dirname = dirname(__FILE__);

$notes = "usage: $0 confidence-level minimum-length pdb

creates ranges of residues in pdb that are below the confidence level
";

require "$dirname/pdbutil.pm";

$confthresh = shift;
die $notes if !length( $confthresh );

$minlength = shift || die $notes;

die "minimum-length must be greater than one!\n" if $minlength <= 1;

$f = shift || die $notes;
die "$f does not exist\n" if !-e $f;

open $fh, $f || die "$f open error $!\n";
@l = <$fh>;
close $fh;

$regionnum = 0;

foreach $l ( @l ) {
    my $r = pdb_fields( $l );
    next if $r->{"recname"}  !~ /^(ATOM|HETATM)$/;

    my $chainid = $r->{chainid};
    my $resseq  = $r->{resseq};
    my $tf      = $r->{tf};

    if ( $tf > $confthresh ) {
        if ( $flex_start{$regionnum} ) {
            ++$regionnum;
        }
        next;
    }

    if ( !$flexregions{$regionnum}++ ) {
        $flex_start{$regionnum} = $resseq;
        $flex_end  {$regionnum} = $resseq;
    } else {
        $flex_end  {$regionnum} = $resseq;
    }
        
    print "$chainid $resseq $tf\n" if $debug;
}

$out = "";
    
for ( $i = 0; $i <= $regionnum; ++$i ) {
    if ( $flex_start{ $regionnum }
         && ( $flex_end{ $i } - $flex_start{ $i } ) >= $minlength - 1
        ) {
        $out .= sprintf( "%d,%d\n", $flex_start{ $i }, $flex_end{ $i } );
    }
}

print $out;

