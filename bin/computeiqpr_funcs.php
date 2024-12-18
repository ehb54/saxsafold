<?php
{};

$mdatas = (object) [
    'pepsi' => (object)[
        'title'          => 'PEPSI-SAXS'
        ,'prefix'        => 'I(q)<sub>P</sub>'
        ,'plotallname'   => 'I(q) all mmc p'
        ,'plotselname'   => 'I(q) sel p'
        ,'datext'        => '-p.dat'
        ,'csvnamesuffix' => 'p'
        ## html tags
        ,'tags' => (object) [
            'header_id'   => 'iq_p_header'
            ,'plotall'      => 'iq_p_plotall'
            ,'plotallhtml'  => 'iq_p_plotallhtml'
            ,'plotsel'      => 'iq_p_plotsel'
            ,'results'      => 'iq_p_results'
            ,'downloads'    => 'iq_p_downloads'
            ,'nnlsresults'  => 'iq_p_nnlsresults'
        ]
    ]
    ,'crysol3' => (object)[
        'title'          => 'CRYSOL'
        ,'prefix'        => 'I(q)<sub>C</sub>'
        ,'plotallname'   => 'I(q) all mmc c'
        ,'plotselname'   => 'I(q) sel c'
        ,'datext'        => '-c3.dat'
        ,'csvnamesuffix' => 'c3'
        ## html tags
        ,'tags' => (object) [
            'header_id'   => 'iq_c3_header'
            ,'plotall'      => 'iq_c3_plotall'
            ,'plotallhtml'  => 'iq_c3_plotallhtml'
            ,'plotsel'      => 'iq_c3_plotsel'
            ,'results'      => 'iq_c3_results'
            ,'downloads'    => 'iq_c3_downloads'
            ,'nnlsresults'  => 'iq_c3_nnlsresults'
        ]
    ]
    ];

function setup_computeiqpr_plots( $outobj ) {
    global $sas;
    global $cgstate;
    global $mdatas;

    $titlefontsize = 15;

    $sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q)", $cgstate->state->output_load->iqplot, [ "title" => "I(q)" ] );
    $outobj->iqplot = $sas->plot( "I(q)" );

    foreach ( $mdatas as $mdata ) {
        $sas->create_plot_from_plot( SAS::PLOT_IQ, $mdata->plotallname, $cgstate->state->output_loadsaxs->iqplot, [ "title" => "I(q)<br>Expt. + all computed on subselected MMC models" ] );
        $sas->plot_options( $mdata->plotallname
                            ,[
                             "showlegend" => false
                             ,"titlefontsize" => $titlefontsize
                             ,"showeditchart" => false
                            ] );
        $outobj->{$mdata->tags->plotall} = $sas->plot( $mdata->plotallname );

        $sas->create_plot_from_plot( SAS::PLOT_IQ, $mdata->plotselname, $cgstate->state->output_loadsaxs->iqplot, [ "title" => "I(q)<br>Expt. + NNLS selected/reconstructed<br>from all computed on subselected MMC models" ] );
        $sas->plot_options( $mdata->plotselname
                            ,[
                             "titlefontsize" => $titlefontsize - 1
                            ] );
        $outobj->{$mdata->tags->plotsel} = $sas->plot( $mdata->plotselname );
    }

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)", $cgstate->state->output_load->prplot, [ "title" => "P(r)" ] );
    $outobj->prplot = $sas->plot( "P(r)" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r) all mmc", $cgstate->state->output_load->prplot, [ "title" => "P(r)<br>Expt. + all computed on subselected MMC models" ] );
    $sas->plot_residuals( "P(r) all mmc", false );
    $sas->remove_plot_data( "P(r) all mmc", "Comp." );
    $sas->remove_plot_data( "P(r) all mmc", "Resid." );
    $sas->annotate_plot( "P(r) all mmc", "" );
    $sas->plot_options( "P(r) all mmc",
                        [
                         "showlegend" => false
                         ,"titlefontsize" => $titlefontsize
                         ,"showeditchart" => false
                        ] );
    $outobj->prplotall = $sas->plot( "P(r) all mmc" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r) sel", $cgstate->state->output_load->prplot, [ "title" => "P(r) [fit without using SDs]<br>Expt. + NNLS selected/reconstructed<br>from all computed on subselected MMC models" ] );
    $sas->plot_residuals( "P(r) sel", false );
    $sas->remove_plot_data( "P(r) sel", "Comp." );
    $sas->remove_plot_data( "P(r) sel", "Resid." );
    $sas->annotate_plot( "P(r) sel", "" );
    $sas->plot_options( "P(r) sel",
                        [
                         "titlefontsize" => $titlefontsize - 1
                        ] );
    $outobj->pr_plotsel = $sas->plot( "P(r) sel" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r) we sel", $cgstate->state->output_load->prplot, [ "title" => "P(r) [fit using SDs]<br>Expt. NNLS selected/reconstructed<br>from all computed on subselected MMC models" ] );
    $sas->plot_residuals( "P(r) we sel", false );
    $sas->remove_plot_data( "P(r) we sel", "Comp." );
    $sas->remove_plot_data( "P(r) we sel", "Resid." );
    $sas->annotate_plot( "P(r) we sel", "" );
    $sas->plot_options( "P(r) we sel",
                        [
                         "titlefontsize" => $titlefontsize - 1
                        ] );
    $outobj->prwe_plotsel = $sas->plot( "P(r) we sel" );

}

function plot_to_image( $plotobj ) {
    global $input;
    global $scriptdir;

    $plotjsonname = "plot4image.json";

    $plots = (object)[
        "_height" => floatval( $input->_height )
        ,"_width" => floatval( $input->_width )
        ,"plotlydata" => $plotobj
        ];

    if ( !file_put_contents( $plotjsonname, json_encode( $plots ) ) ) {
        error_exit( "Error creating $plotjsonname" );
    }

    $res = run_cmd( "$scriptdir/plotly2img.py $plotjsonname" );

    $plotsobj = json_decode( $res );

    if ( !$plotsobj
         || !isset( $plotsobj->plotlydata )
         || !isset( $plotsobj->height )
         || !isset( $plotsobj->width )
        ) {
        error_exit( "Error producing image from plot" );
    }

## safari didn't like charset=utf-8
#        <div><img style="width:{$plotsobj->width}px;height:{$plotsobj->height}px" src="data:image/png;base64;charset=utf-8,$plotsobj->plotlydata" /></div>

    $plotimg = <<<__EOD
        <div><img style="width:{$plotsobj->width}px;height:{$plotsobj->height}px" src="data:image/png;base64,$plotsobj->plotlydata" /></div>
        __EOD;

    return $plotimg;
}
