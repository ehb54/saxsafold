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
                        ] );
    $outobj->iqplotall = $sas->plot( "I(q) all mmc" );

    $sas->create_plot_from_plot( SAS::PLOT_IQ, "I(q) sel", $cgstate->state->output_loadsaxs->iqplot, [ "title" => "I(q) <br>Expt. + NNLS selected/reconstructed from all computed on subselected MMC models" ] );
    $sas->plot_options( "I(q) sel",
                        [
                         "titlefontsize" => $titlefontsize
                        ] );
    $outobj->iqplotsel = $sas->plot( "I(q) sel" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r)", $cgstate->state->output_load->prplot, [ "title" => "P(r)" ] );
    $outobj->prplot = $sas->plot( "P(r)" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r) all mmc", $cgstate->state->output_load->prplot, [ "title" => "P(r) <br>Expt. + all computed on subselected MMC models" ] );
    $sas->plot_residuals( "P(r) all mmc", false );
    $sas->remove_plot_data( "P(r) all mmc", "Comp." );
    $sas->remove_plot_data( "P(r) all mmc", "Resid." );
    $sas->annotate_plot( "P(r) all mmc", "" );
    $sas->plot_options( "P(r) all mmc",
                        [
                         "showlegend" => false
                         ,"titlefontsize" => $titlefontsize
                        ] );
    $outobj->prplotall = $sas->plot( "P(r) all mmc" );

    $sas->create_plot_from_plot( SAS::PLOT_PR, "P(r) sel", $cgstate->state->output_load->prplot, [ "title" => "P(r) <br>Expt. + NNLS selected/reconstructed from all computed on subselected MMC models" ] );
    $sas->plot_residuals( "P(r) sel", false );
    $sas->remove_plot_data( "P(r) sel", "Comp." );
    $sas->remove_plot_data( "P(r) sel", "Resid." );
    $sas->annotate_plot( "P(r) sel", "" );
    $sas->plot_options( "P(r) sel",
                        [
                         "titlefontsize" => $titlefontsize
                        ] );
    $outobj->prplotsel = $sas->plot( "P(r) sel" );
}

/*
function plotlycomputeiqpr_old( $inobj, $outobj ) {
    $outobj->iqplot                    = unserialize( serialize( $inobj->iqplot ) );
    $outobj->iqplotall                 = unserialize( serialize( $inobj->iqplot ) );
    $outobj->iqplotsel                 = unserialize( serialize( $inobj->iqplot ) );

    $outobj->prplot                    = unserialize( serialize( $inobj->prplot ) );
    $outobj->prplotall                 = unserialize( serialize( $inobj->prplot ) );
    $outobj->prplotsel                 = unserialize( serialize( $inobj->prplot ) );

    $outobj->iqplot->layout->title     = "Experimental I(q)";
    $outobj->iqplotall->layout->title  = "Experimental I(q)<br>all computed MMC models";
    $outobj->iqplotsel->layout->title  = "Experimental I(q)<br>all preselected computed MMC models";

    $outobj->prplot->layout->title     = "Experimental data derived P(r)";
    $outobj->prplotall->layout->title  = "Experimental data derived P(r)<br>all computed MMC models";
    $outobj->prplotsel->layout->title  = "Experimental data-derived P(r)<br>all preselected computed MMC models";
    
    $outobj->iqplot->data[0]->name     = "Exp. I(q)";
    $outobj->iqplotall->data[0]->name  = "Exp. I(q)";
    $outobj->iqplotsel->data[0]->name  = "Exp. I(q)";

};
*/

/*
function plotlyloadcurve( $plot, $filename, $title, $scale = 1 ) {
    if ( $data = file_get_contents( $filename ) ) {
        $plotin  = explode( "\n", $data );

        # remove comment lines
        $plotin = preg_grep( '/^\s*#/', $plotin, PREG_GREP_INVERT );

        $plotpos = count( $plot->data );
        $plot->data[$plotpos] = json_decode(
            '[
               {
                 "x"        : []
                 ,"y"       : []
                 ,"type" : "scatter"
                 ,"line" : {
                      ,"width" : 1
                 }
               ]'
            );
            
        foreach ( $plotin as $linein ) {
            $linevals = preg_split( '/\s+/', trim( $linein ) );

            if ( count( $linevals ) >= 2 ) {
                $plot->data[$plotpos]->x[] = floatval($linevals[0]);
                $plot->data[$plotpos]->y[] = floatval($linevals[1]) * $scale;
            }
        }
        $plot->data[$plotpos]->name = $title;
        return "";
    } else {
        return "Could not load file $file";
    }
}
    
*/
