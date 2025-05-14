<?php
{};

require "sas.php";

## testing

#$do_testing_iq = true;
#$do_testing_pr = true;
#$do_testing_pr_timing = true;
#$do_testing_nnls  = true;

if ( isset( $do_testing_iq ) && $do_testing_iq ) {
    $sas = new SAS( true );

    $iqfile     = 'waxsis/lyzexp.dat';
    $waxsisfile = 'waxsis/intensity_waxsis.calc';
    
    $chi2  = -1;
    $rmsd  = -1;
    $scale = 0;

    if (
        $sas->load_file( SAS::PLOT_IQ, "Exp. I(q)", $iqfile  )
        && $sas->load_file( SAS::PLOT_IQ, "WAXSiS_org", $waxsisfile  )
        && $sas->interpolate( "WAXSiS_org", "Exp. I(q)", "WAXSiS_interp" )
        && $sas->scale_nchi2( "Exp. I(q)", "WAXSiS_interp", "WAXSiS", $chi2, $scale )
        && $sas->rmsd( "Exp. I(q)", "WAXSiS", $rmsd )
        ) {
        echo "ok\n";
    } else {
        error_exit( $sas->last_error );
    }

    $plotname = "I(q)";

    $sas->create_plot(
        SAS::PLOT_IQ
        ,$plotname
        ,[
            "Exp. I(q)"
            ,"WAXSiS"
        ]
        );

    $rmsd = round( $rmsd, 3 );
    $chi2 = round( $chi2, 3 );
    $annotate_msg = "";
    if ( $rmsd != -1 ) {
        $annotate_msg .= "RMSD $rmsd   ";
    }
    if ( $chi2 != -1 ) {
        $annotate_msg .= "nChi^2 $chi2   ";
    }

    if ( strlen( $annotate_msg ) ) {
        $sas->annotate_plot( "I(q)", $annotate_msg );
    }
    
    if ( !$sas->plot_trace_options( $plotname, "WAXSiS", [ 'linecolor_number' => 1 ] ) ) {
        echo $sas->last_error . "\n";
        exit(-1);
    }

    $sas->calc_residuals( "Exp. I(q)", "WAXSiS", "Res./SD" );
    $sas->add_plot_residuals( $plotname, "Res./SD" );

    if ( !$sas->plot_trace_options( $plotname, "Res./SD", [ 'linecolor_number' => 1 ] ) ) {
        echo $sas->last_error . "\n";
        exit(-1);
    }

    # $sas->plot_residuals( $plotname, false );

    
    file_put_contents( "dump_data.json", $sas->dump_data() );
    file_put_contents( "dump_plots.json", json_encode( $sas->plot( $plotname ), JSON_PRETTY_PRINT ) );

    $outfile = "plotout.json";
    file_put_contents( $outfile, "\n" .  json_encode( $sas->plot( $plotname ) ) . "\n\n" );
    echo "cat $outfile\n";
}

if ( isset( $do_testing_pr ) && $do_testing_pr ) {
    $sas = new SAS( true );

    $plotname = "P(r)";
    
    $prfile     = "lyzexp_ift.sprr";
    $prfilebin1 = "lyzexp_bin1_ift.dat";
    $pdbfile    = "AF-G0A007-F1-model_v4-somo.pdb";

    $rmsd       = -1;
    
    if (
        $sas->load_file( SAS::PLOT_PR, "P(r)-org", $prfile  )
        && $sas->compute_pr( $pdbfile, "P(r)-computed", 1 )
        && $sas->interpolate( "P(r)-org", "P(r)-computed", "P(r)-org-interp" )
        && $sas->norm_pr( "P(r)-org-interp", 14303, "P(r)-org-interp-norm" )
        && $sas->norm_pr( "P(r)-computed",   14303, "P(r)-computed-norm" )
        && $sas->rmsd_residuals( "P(r)-org-interp-norm", "P(r)-computed-norm", "Resid.", $rmsd )
        && $sas->create_plot( SAS::PLOT_PR
                              ,$plotname
                              ,[
                                  "P(r)-org-interp-norm"
                                  ,"P(r)-computed-norm"
                              ]
        )
        && $sas->add_plot_residuals( $plotname, "Resid." )
        && $sas->plot_options( $plotname, [ 'yaxistitle' => 'Freq. Norm. [Da]' ] )
        ) {
        echo "ok\n";
    } else {
        error_exit( $sas->last_error );
    }

    $rmsd = round( $rmsd, 3 );
    $annotate_msg = "";
    if ( $rmsd != -1 ) {
        $annotate_msg .= "RMSD $rmsd   ";
    }

    if ( strlen( $annotate_msg ) ) {
        $sas->annotate_plot( $plotname, $annotate_msg );
    }

#    if ( !$sas->remove_plot_data( $plotname, "P(r)-computed-norm") ) {
#        error_exit( $sas->last_error );
#    }

#    if ( !$sas->remove_plot_data( $plotname, "Resid." ) ) {
#        error_exit( $sas->last_error );
#    }

    $sas->plot_residuals( $plotname, true );

    echo 
        $sas->common_grids(
            [
             "P(r)-org-interp-norm"
             ,"P(r)-computed-norm"
            ]
        )
        ? "grids match\n"
        : "grids do NOT match\n"
        ;

    foreach ( [
                  "P(r)-org"
                  ,"P(r)-computed"
                  ,"P(r)-org-interp"
                  ,"P(r)-org-interp-norm"
                  ,"P(r)-computed-norm"
                  ,"Resid."
              ] as $v ) {
        # $sas->remove_data( $v );
    }
    
    $sas->plot_options( $plotname,
                        [
                         "showlegend" => false
                         ,"titlefontsize" => 12
                         ,"title" => "P(r)<br>Expt. + all computed on subselected MMC models" 
                        ] );

    file_put_contents( "dump_data.json", $sas->dump_data() );
    file_put_contents( "dump_plots.json", json_encode( $sas->plot( $plotname ), JSON_PRETTY_PRINT ) );

    $outfile = "plotout.json";
    file_put_contents( $outfile, "\n" .  json_encode( $sas->plot( $plotname ) ) . "\n\n" );
    echo "cat $outfile\n";
}

if ( isset( $do_testing_pr_timing ) && $do_testing_pr_timing ) {
    $sas = new SAS( true );

    $plotname = "P(r)";

    $pdbs = [
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0001.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0002.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0003.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0004.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0005.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0006.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0007.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0008.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0009.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0010.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0011.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0012.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0013.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0014.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0015.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0016.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0017.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0018.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0019.pdb",
        "preselected/AF-Q06187-F1-model_v4-somo-somo-m0020.pdb"
        ];

    $names = [
        "P(r) mod. 1",
        "P(r) mod. 2",
        "P(r) mod. 3",
        "P(r) mod. 4",
        "P(r) mod. 5",
        "P(r) mod. 6",
        "P(r) mod. 7",
        "P(r) mod. 8",
        "P(r) mod. 9",
        "P(r) mod. 10",
        "P(r) mod. 11",
        "P(r) mod. 12",
        "P(r) mod. 13",
        "P(r) mod. 14",
        "P(r) mod. 15",
        "P(r) mod. 16",
        "P(r) mod. 17",
        "P(r) mod. 18",
        "P(r) mod. 19",
        "P(r) mod. 20"
        ];

    $limit = 10;
    $mw    = 76297;
    $exppr = "SASDF83-A176_norm.dat";
    
    $pdbs  = array_slice( $pdbs, 0, $limit );
    $names = array_slice( $names, 0, $limit );

    $sas->load_file( SAS::PLOT_PR, "Exp. P(r)-orig", $exppr );
    $sas->compute_pr_many( $pdbs, $names, 1 );
    $sas->interpolate( "Exp. P(r)-orig", $names[0], "Exp. P(r)" );
    foreach ( $names as $name ) {
        $sas->interpolate( $name, "Exp. P(r)", "$name interp" );
        $sas->norm_pr( "$name interp", $mw, "$name norm" );
    }

    file_put_contents( "dump_data.json", $sas->dump_data() );
}

if ( isset( $do_testing_nnls ) && $do_testing_nnls ) {
    $sas = new SAS( true );

    $plotname = "P(r)";

    $pdbs = [
        "preselected/AF-G0A007-F1-model_v4-somo-m0000003.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000028.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000053.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000078.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000103.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000128.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000153.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000178.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000203.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000228.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000253.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000278.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000303.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000328.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000353.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000378.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000403.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000428.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000453.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000478.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000503.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000528.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000553.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000578.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000603.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000628.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000653.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000678.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000703.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000728.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000753.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000778.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000803.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000828.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000853.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000878.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000903.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000928.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000953.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0000978.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001003.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001028.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001053.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001078.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001103.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001128.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001153.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001178.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001203.pdb"
        ,"preselected/AF-G0A007-F1-model_v4-somo-m0001228.pdb"
        ];

    $names = [];
    foreach ( $pdbs as $k => $v ) {
        $names[ $k ] = "P(r) mod. " . model_no_from_pdb_name( $v );
    }

    $limit = min( 3, count( $names ) );
    $mw    = 14301;
    $exppr = "lyzexp_ift.sprr";
    
    $pdbs  = array_slice( $pdbs, 0, $limit );
    $names = array_slice( $names, 0, $limit );

    $sas->load_file( SAS::PLOT_PR, "Exp. P(r)-orig", $exppr );
    $sas->compute_pr_many( $pdbs, $names, 1 );
    $sas->interpolate( "Exp. P(r)-orig", $names[0], "Exp. P(r)-interp" );
    $sas->norm_pr( "Exp. P(r)-interp", $mw, "Exp. P(r)" );
    $sas->remove_data( "Exp. P(r)-orig" );
    $sas->remove_data( "Exp. P(r)-interp" );
    
    echo "data names\n" . json_encode( $sas->data_names( '//' ), JSON_PRETTY_PRINT ) . "\n";
    echo "plot names\n" . json_encode( $sas->plot_names( ), JSON_PRETTY_PRINT ) . "\n";
    
    foreach ( $names as $name ) {
        $sas->interpolate( $name, "Exp. P(r)", "$name interp" );
        $sas->norm_pr( "$name interp", $mw, "$name norm" );
        $sas->remove_data( "$name interp" );
        $sas->remove_data( $name );
        $sas->rename_data( "$name norm", $name );
    }

    # echo $sas->data_summary( array_merge( [ "Exp. P(r)" ], $names  ) );

    $results = [];
    
    $sas->extend_pr( array_merge( [ "Exp. P(r)" ], $names  ) );
    $sas->set_pr_error_y_nonzero( "Exp. P(r)" );

    # echo $sas->data_summary( array_merge( [ "Exp. P(r)" ], $names  ) );

    $sas->nnls( "Exp. P(r)", $names, "fit curve", $results, false );

    echo "Result:\n----\n" . json_encode( $results, JSON_PRETTY_PRINT ) . "\n";
    echo "Sum contributions " . array_sum( (array) $results ) . "\n";

    echo $sas->data_summary( array_merge( [ "Exp. P(r)", "fit curve" ], $names  ) );

    $sas->save_data_csv( array_merge( [ "Exp. P(r)", "fit curve" ], $names  ) );
    $sas->save_data_csv_tr( array_merge( [ "Exp. P(r)", "fit curve" ], $names  ) );
    
    file_put_contents( "dump_data.json", $sas->dump_data() );
}

/*
$sas = new SAS( true );
$sas->load_file( SAS::PLOT_IQ, "sas_g_ang iq", "SAS_G_ang.dat" );
$sas->load_file( SAS::PLOT_PR, "sas_g_ang pr", "SAS_G_ang.out" );
echo $sas->data_summary( [ "sas_g_ang iq" ] );
$sas->save_file( "sas_g_ang iq", "test_iq.dat" );
$sas->save_file( "sas_g_ang pr", "test_pr.dat" );

$rg = 0;
$sas->compute_rg_from_pr( "sas_g_ang pr", $rg );
echo "rg is $rg\n";

*/

/*
$sas = new SAS( true );
$cgstate = new cgrun_state();
# $sas->debug_json( "output_final", $cgstate->state->output_final );
$sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q) waxsis", $cgstate->state->output_final->iqplotwaxsis );
$names = $sas->data_names( '/WAXSiS/' );
echo $sas->data_summary( $names );
$results = (object)[];
$sas->nnls( "Exp. I(q)", $names, "I(q) NNLS fit WAXSiS", $results );
$sas->debug_json( "results", $results );
echo $sas->data_summary( $sas->data_names( '/(WAXSiS|NNLS)/' ) );
echo $sas->compare_data( "I(q) NNLS fit", "I(q) NNLS fit WAXSiS", false ) ? "data matches\n" : "data doesn't match\n";
echo $sas->compare_data( "I(q) NNLS fit", "I(q) NNLS fit WAXSiS" ) ? "errors match\n" : "errors don't match\n";
echo $sas->compare_data( "I(q) NNLS fit", "I(q) WAXSiS mod. 1197" ) ? "different data match\n" : "different data doesn't match\n";
*/

/*

$sas = new SAS( true );
$cgstate = new cgrun_state();
$plotname = "test";
$sas->create_plot_from_plot( SAS::PLOT_IQ, $plotname, $cgstate->state->output_final->iqplotwaxsis );
$sas->recolor_plot( $plotname, range( 0, 20 ) );
$outfile = "plotout.json";
file_put_contents( $outfile, "\n" .  json_encode( $sas->plot( $plotname ) ) . "\n\n" );
*/

/*
$sas = new SAS( true );
$cgstate = new cgrun_state();
#$sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q)", $cgstate->state->output_load->iqplot );
#$sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)", $cgstate->state->output_load->prplot );
$sas->create_plot_from_plot( SAS::PLOT_IQ, "P(q)c3", $cgstate->state->output_iqpr->iq_c3_plotsel );
$sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q)p", $cgstate->state->output_iqpr->iq_p_plotsel );
$sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)", $cgstate->state->output_iqpr->pr_plotsel );
#echo $sas->data_summary( $sas->data_names( '/(Exp|Mod)/') );
echo $sas->data_summary( $sas->data_names( '/(Exp| mod)/' ) );
foreach ( $sas->data_names( '/(Exp| mod)/' ) as $v ) {
    $sas->data_convert_nm_to_angstrom( $v );
}
echo $sas->data_summary( $sas->data_names( '/(Exp| mod)/' ) );
*/

/*
## test new pr plot with results

$outfile        = "plotout.json";
$plotnameorg    = "P(r) orig.";
$plotnamewaxsis = "P(r) waxsis reconstruct";

$sas = new SAS( true );
$cgstate = new cgrun_state();
$sas->create_plot_from_plot( SAS::PLOT_PR, $plotnameorg, $cgstate->state->output_load->prplot );
$sas->create_plot_from_plot( SAS::PLOT_PR, $plotnamewaxsis, $cgstate->state->output_load->prplot );
$sas->plot_options( $plotnamewaxsis,
    [
    "titlefontsize" => 14
    ,"title" => "P(r) Expt. vs starting and<br>reconstructed from final models selected"
    ] );

echo $sas->data_summary( $sas->data_names() );
$sas->debug_json( '$cgstate->state->iq_waxsis_nnlsresults', $cgstate->state->iq_waxsis_nnlsresults );
$sas->debug_json( '$cgstate->state->waxsis_final_pdb_names', $cgstate->state->waxsis_final_pdb_names );

$prfiles = array_values( (array)$cgstate->state->waxsis_final_pdb_names);
$prnames = [];
foreach ( $prfiles as $k => $v ) {
    $prnames[ $k ] = "notnormed P(r) mod. " . model_no_from_pdb_name( $v );
}

$sas->compute_pr_many( $prfiles, $prnames );
$sas->extend_pr( array_merge( $prnames, [ "Exp. P(r)" ] ) );

$scalednames = [];
$normednames = [];

foreach ( $prnames as $v ) {
    $frame = frame_no_from_data_name( $v );
#    if ( $frame == 0 ) {
#        $frame = 'waxsis';
#    }
    $normedname = substr( $v, 10 );
    $scaledname = "scaled $normedname";
    $normednames[] = $normedname;

    $sas->norm_pr( $v, $cgstate->state->output_load->mw, $normedname );

    if ( isset( $cgstate->state->iq_waxsis_nnlsresults->{ "I(q) WAXSiS mod. $frame" } ) ) {
#        $sas->add_plot( $plotnamewaxsis, $normedname );

        $sas->norm_pr( $v, $cgstate->state->output_load->mw * $cgstate->state->iq_waxsis_nnlsresults->{ "I(q) WAXSiS mod. $frame" }, $scaledname );
        $scalednames[] = $scaledname;
    }
    $sas->remove_data( $v );
}

## create sum (maybe a class function ?)

$combinedname = "Recon.";
$residname = "Recon. Resid.";
$sas->sum_data( $scalednames, $combinedname );
$sas->add_plot( $plotnamewaxsis, $combinedname );
$sas->remove_data( $scalednames );
$sas->remove_data( $normednames );

$rmsd = 1e99;
$sas->rmsd_residuals( "Exp. P(r)", $combinedname, $residname, $rmsd );
$sas->add_plot_residuals( $plotnamewaxsis, $residname );
$rmsd = sprintf( "%.2f", $rmsd );
$sas->annotate_plot( $plotnamewaxsis, "Recon. RMSD $rmsd", true );
$sas->plot_trace_options( $plotnamewaxsis, "Resid.", [ 'linecolor_number' => 1 ] );
$sas->plot_trace_options( $plotnamewaxsis, $combinedname, [ 'linecolor_number' => 2 ] );
$sas->plot_trace_options( $plotnamewaxsis, $residname, [ 'linecolor_number' => 2 ] );

echo $sas->data_summary( $sas->data_names() );
file_put_contents( $outfile, "\n" .  json_encode( $sas->plot( $plotnamewaxsis ) ) . "\n\n" );

*/

## test function load_somo_csv_file()


$sas = new SAS( true );

$csvfile = "AF-G0A007-F1-model_v4_waxsis_somo_iq.csv";

$sas->load_somo_csv_file( SAS::PLOT_IQ, "G0A007D: ", $csvfile );

echo $sas->data_summary( $sas->data_names() );

$sas->regex_rename_data( $sas->data_names( '/ WAXSiS/' ), '/^.* WAXSiS/', 'I(q) WAXSiS' );

echo $sas->data_summary( $sas->data_names() );
