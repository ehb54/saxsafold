#!/usr/bin/perl


$notes = "usage: $0 pdb basename*

creates basename-####.pdb for each model in the source
removes H
";


$f = shift || die $notes;
$bn = shift || die $notes;
die "$f does not exist\n" if !-e $f;
die "$f is not readable\n" if !-r $f;

sub mytrim {
    my $s = shift;
    $s =~ s/^\s+|\s+$//g;
    $s;
}

sub mypad {
    my $s = shift;
    my $l = shift;
    while( length( $s ) < $l ) {
        $s .= " ";
    }
    $s;
}

sub myleftpad0 {
    my $s = shift;
    my $l = shift;
    while( length( $s ) < $l ) {
        $s = "0$s";
    }
    $s;
}

sub pdb_fields {
    my $l = shift;
    my %r;

    $r{ "recname" } = mytrim( substr( $l, 0, 6 ) );

    # pdb data from https://www.wwpdb.org/documentation/file-format-content/format33

    if ( $r{ "recname" } eq "LINK" ) {
        $r{ "name1"     } = mytrim( substr( $l, 12, 4 ) );
        $r{ "resname1"  } = mytrim( substr( $l, 17, 3 ) );
        $r{ "chainid1"  } = mytrim( substr( $l, 21, 1 ) );
        $r{ "resseq1"   } = mytrim( substr( $l, 22, 4 ) );
        $r{ "name2"     } = mytrim( substr( $l, 42, 4 ) );
        $r{ "resname2"  } = mytrim( substr( $l, 47, 3 ) );
        $r{ "chainid2"  } = mytrim( substr( $l, 51, 1 ) );
        $r{ "resseq2"   } = mytrim( substr( $l, 52, 4 ) );
        $r{ "length"    } = mytrim( substr( $l, 73, 5 ) );
    } elsif ( $r{ "recname" } eq "ATOM" ||
                $r{ "recname" } eq "HETATM" ) {
        $r{ "serial"    } = mytrim( substr( $l,  6, 5 ) );
        $r{ "name"      } = mytrim( substr( $l, 12, 4 ) );
        $r{ "resname"   } = mytrim( substr( $l, 17, 4 ) ); # note this is officially only a 3 character field!
        $r{ "chainid"   } = mytrim( substr( $l, 21, 1 ) );
        $r{ "resseq"    } = mytrim( substr( $l, 22, 4 ) );
        $r{ "element"   } = mytrim( substr( $l, 76, 2 ) );
        $r{ "x"         } = mytrim( substr( $l, 30, 8 ) );
        $r{ "y"         } = mytrim( substr( $l, 38, 8 ) );
        $r{ "z"         } = mytrim( substr( $l, 46, 8 ) );
        $r{ "element"   } = mytrim( substr( $l, 76, 2 ) );
    } elsif ( $r{ "recname" } eq 'CONECT' ) {
        $r{ "serial"    } = mytrim( substr( $l,  6, 5 ) );
        $r{ "bond1"     } = mytrim( substr( $l, 11, 5 ) );
        $r{ "bond2"     } = mytrim( substr( $l, 16, 5 ) );
        $r{ "bond3"     } = mytrim( substr( $l, 21, 5 ) );
        $r{ "bond4"     } = mytrim( substr( $l, 26, 5 ) );
    } elsif  ( $r{ "recname" } eq 'MODEL' ) {
        $r{ "model"     } = mytrim( substr( $l, 10, 4 ) );
    }
    \%r;
}

open IN, $f || die "$f open error $!\n";
@l = <IN>;
close IN;
grep chomp, @l;

my @ol;
undef $model;

foreach $l ( @l ) {
    $r = pdb_fields( $l );
    if ( $r->{"recname"}  =~ /^MODEL$/ ) {
        if ( @ol && $model ) {
            my $fo = sprintf( ">$bn-m%s.pdb", myleftpad0( $model, 4 ) );
            open my $fh, $fo || die "could not create fo\n";
            print $fh ( join "\n", @ol ) . "\n";
            close $fh;
            print "$fo\n";
        }
        $model = $r->{"model"};
        @ol = ();
        push @ol, $l;
        next;
    } elsif ( $r->{"recname"}  =~ /^(ATOM|HETATM)$/ &&
              $r->{"element"}  =~ /^H$/ ) {
        next;
    }
    push @ol, $l;
}

if ( @ol && $model ) {
    my $fo = sprintf( ">$bn-m%s.pdb", myleftpad0( $model, 4 ) );
    open my $fh, $fo || die "could not create fo\n";
    print $fh ( join "\n", @ol ) . "\n";
    close $fh;
    print "$fo\n";
}
