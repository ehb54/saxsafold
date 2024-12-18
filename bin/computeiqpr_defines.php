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
