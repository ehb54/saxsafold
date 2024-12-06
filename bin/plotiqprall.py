#!/usr/bin/python3

import plotly.io as pio
import plotly.graph_objects as go
import sys
import os.path
import json
import base64

if len( sys.argv ) != 2:
    sys.exit( "No arguments provided" )

filename = sys.argv[1]

if not os.path.isfile( filename ) :
    sys.exit( "Filename '" + filename + "' does not exist" )

## open load file
    
try:
    with open( filename, 'r') as f:
        data = json.load(f)
except json.JSONDecodeError:
    sys.exit( "JSON decoding error in file '" + filename + "'" )
        
for key in [ '_height', '_width', 'iqplotall', 'prplotall' ]:
    if key not in data:
        sys.exit( "Required key '" + key + "' not in '" + filename + "'" )

usewidth  = int( float( data[ '_width' ] ) * .475 )
useheight = int( usewidth * .6 )

data['iqplotall']['layout']['title']['x'] = .5;
data['iqplotall']['layout']['title']['xanchor'] = 'center';
data['prplotall']['layout']['title']['x'] = .5;
data['prplotall']['layout']['title']['xanchor'] = 'center';

output = {
    'iqplotall' :  base64.b64encode( pio.to_image( go.Figure( data['iqplotall' ] ), format="png", width=usewidth, height=useheight ) ).decode( 'utf-8' )
    ,'prplotall' :  base64.b64encode( pio.to_image( go.Figure( data['prplotall' ] ), format="png", width=usewidth, height=useheight ) ).decode( 'utf-8' )
    ,'height' : useheight
    ,'width'  : usewidth
}

print( json.dumps( output ) )
