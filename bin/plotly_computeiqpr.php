<?php
{};

function plotlycomputeiqpr( $inobj, $outobj ) {
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
    
