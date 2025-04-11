#!/usr/bin/perl

use File::Basename;
my $dirname = dirname(__FILE__);

$notes = "usage: $0 pdb

returns pdb chain count
";

require "$dirname/pdbutil.pm";


$f = shift || die $notes;
die "$f does not exist\n" if !-e $f;

open $fh, $f || die "$f open error $!\n";
@l = <$fh>;
close $fh;

foreach $l ( @l ) {
    my $r = pdb_fields( $l );
    next if $r->{'recname'}  !~ /^(ATOM|HETATM)$/;
    next if $r->{'resname'} =~ /^(HOH|WAT)$/;
    my $chainid = $r->{'chainid'};
    $chains++ if !$chains{$chainid}++;
}

print "$chains\n";
