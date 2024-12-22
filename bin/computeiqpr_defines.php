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

function get_color( $pos ) {
    $colors = [
        "aqua" #00FFFF
        ,"blueviolet" #8A2BE2
        ,"brown" #A52A2A
        ,"burlywood" #DEB887
        ,"cadetblue" #5F9EA0
        ,"chartreuse" #7FFF00
        ,"chocolate" #D2691E
        ,"coral" #FF7F50
        ,"cornflowerblue" #6495ED
        ,"crimson" #DC143C
        ,"cyan" #00FFFF
        ,"darkblue" #00008B
        ,"darkcyan" #008B8B
        ,"darkgoldenrod" #B8860B
        ,"darkgray" #A9A9A9
        ,"darkgreen" #006400
        ,"darkkhaki" #BDB76B
        ,"darkmagenta" #8B008B
        ,"darkolivegreen" #556B2F
        ,"darkorange" #FF8C00
        ,"darkorchid" #9932CC
        ,"darkred" #8B0000
        ,"darksalmon" #E9967A
        ,"darkseagreen" #8FBC8F
        ,"darkslateblue" #483D8B
        ,"darkslategray" #2F4F4F
        ,"darkturquoise" #00CED1
        ,"darkviolet" #9400D3
        ,"deeppink" #FF1493
        ,"deepskyblue" #00BFFF
        ,"dimgray" #696969
        ,"dodgerblue" #1E90FF
        ,"firebrick" #B22222
        ,"forestgreen" #228B22
        ,"fuchsia" #FF00FF
#        ,"gainsboro" #DCDCDC
        ,"gold" #FFD700
        ,"goldenrod" #DAA520
        ,"gray" #808080
        ,"green" #008000
        ,"greenyellow" #ADFF2F
        ,"hotpink" #FF69B4
        ,"indianred" #CD5C5C
        ,"indigo" #4B0082
        ,"khaki" #F0E68C
        ,"lavender" #E6E6FA
#        ,"lavenderblush" #FFF0F5
        ,"lawngreen" #7CFC00
        ,"lightblue" #ADD8E6
        ,"lightcoral" #F08080
        ,"lightcyan" #E0FFFF
        ,"lightgoldenrodyellow" #FAFAD2
        ,"lightgreen" #90EE90
        ,"lightgrey" #D3D3D3
        ,"lightpink" #FFB6C1
        ,"lightsalmon" #FFA07A
        ,"lightseagreen" #20B2AA
        ,"lightskyblue" #87CEFA
        ,"lightslategray" #778899
        ,"lightsteelblue" #B0C4DE
        ,"lime" #00FF00
        ,"limegreen" #32CD32
        ,"magenta" #FF00FF
        ,"maroon" #800000
        ,"mediumaquamarine" #66CDAA
        ,"mediumblue" #0000CD
        ,"mediumorchid" #BA55D3
        ,"mediumpurple" #9370DB
        ,"mediumseagreen" #3CB371
        ,"mediumslateblue" #7B68EE
        ,"mediumspringgreen" #00FA9A
        ,"mediumturquoise" #48D1CC
        ,"mediumvioletred" #C71585
        ,"midnightblue" #191970
        ,"moccasin" #FFE4B5
        ,"navy" #000080
        ,"olive" #808000
        ,"olivedrab" #6B8E23
        ,"orange" #FFA500
        ,"orangered" #FF4500
        ,"orchid" #DA70D6
        ,"palegoldenrod" #EEE8AA
        ,"palegreen" #98FB98
        ,"paleturquoise" #AFEEEE
        ,"palevioletred" #DB7093
#        ,"papayawhip" #FFEFD5
        ,"peachpuff" #FFDAB9
        ,"peru" #CD853F
        ,"pink" #FFC0CB
        ,"plum" #DDA0DD
        ,"powderblue" #B0E0E6
        ,"purple" #800080
        ,"red" #FF0000
        ,"rosybrown" #BC8F8F
        ,"royalblue" #4169E1
        ,"saddlebrown" #8B4513
        ,"salmon" #FA8072
        ,"sandybrown" #F4A460
        ,"seagreen" #2E8B57
#        ,"seashell" #FFF5EE
        ,"sienna" #A0522D
        ,"silver" #C0C0C0
        ,"skyblue" #87CEEB
        ,"slateblue" #6A5ACD
        ,"slategray" #708090
        ,"springgreen" #00FF7F
        ,"steelblue" #4682B4
        ,"tan" #D2B48C
        ,"teal" #008080
        ,"thistle" #D8BFD8
        ,"tomato" #FF6347
        ,"turquoise" #40E0D0
        ,"violet" #EE82EE
        ,"wheat" #F5DEB3
        ,"yellow" #FFFF00
        ,"yellowgreen" #9ACD32
        ];

    return $colors[ $pos % count( $colors ) ];
}
