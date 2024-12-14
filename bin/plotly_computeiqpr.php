<?php
{};

function setup_computeiqpr_plots( $outobj ) {
    global $sas;
    global $cgstate;

    $titlefontsize = 15;

    $sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q)", $cgstate->state->output_load->iqplot, [ "title" => "I(q)" ] );
    $outobj->iqplot = $sas->plot( "I(q)" );

    $sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q) all mmc", $cgstate->state->output_loadsaxs->iqplot, [ "title" => "I(q)<br>Expt. + all computed on subselected MMC models" ] );
    $sas->plot_options( "I(q) all mmc",
                        [
                         "showlegend" => false
                         ,"titlefontsize" => $titlefontsize
                         ,"showeditchart" => false
                        ] );
    $outobj->iqplotall = $sas->plot( "I(q) all mmc" );

    $sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q) sel", $cgstate->state->output_loadsaxs->iqplot, [ "title" => "I(q)<br>Expt. + NNLS selected/reconstructed<br>from all computed on subselected MMC models" ] );
    $sas->plot_options( "I(q) sel",
                        [
                         "titlefontsize" => $titlefontsize - 1
                        ] );
    $outobj->iqplotsel = $sas->plot( "I(q) sel" );

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
    $outobj->prplotsel = $sas->plot( "P(r) sel" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r) we sel", $cgstate->state->output_load->prplot, [ "title" => "P(r) [fit using SDs]<br>Expt. NNLS selected/reconstructed<br>from all computed on subselected MMC models" ] );
    $sas->plot_residuals( "P(r) we sel", false );
    $sas->remove_plot_data( "P(r) we sel", "Comp." );
    $sas->remove_plot_data( "P(r) we sel", "Resid." );
    $sas->annotate_plot( "P(r) we sel", "" );
    $sas->plot_options( "P(r) we sel",
                        [
                         "titlefontsize" => $titlefontsize - 1
                        ] );
    $outobj->prweplotsel = $sas->plot( "P(r) we sel" );

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
