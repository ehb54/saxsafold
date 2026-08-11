#!/usr/bin/python3

import argparse
import mdtraj as md
import numpy as np

def parse_arguments():
    parser = argparse.ArgumentParser( description='Compute Rgs of a dcd' )
    parser.add_argument( '--dcdfile', required=True, help='Path to the dcd file' )
    parser.add_argument( '--pdbfile', required=True, help='Path to the pdb (topology) file' )
    return parser.parse_args()

def rgs( dcdname, pdbname, outaccepted, outstats ):
    """compute rgs"""

    traj = md.load( dcdname, top=pdbname );

    # Compute Rg for each frame, in Angstroms
    rgs = md.compute_rg( traj ) * 10

    # Save Rg data to file
    with open( outaccepted, "w" ) as file:
        # Print to the file
        print( "# manually loaded DCD: Frame #, Rg in Angstroms", file=file )
        for i, rg in enumerate( rgs ):
            print( f"{i+1}\t{rg:.2f}", file=file )

    # calc stats

    # Min, max, average Rg
    min_rg = np.min( rgs )
    max_rg = np.max( rgs )
    avg_rg = np.mean( rgs )

    # Compute XYZ ranges ( traj.xyz is in nanometers )
    xyz = traj.xyz  # shape ( n_frames, n_atoms, 3 )
    min_xyz = xyz.min( axis=( 0, 1 ) ) * 10  # Convert to Angstrom
    max_xyz = xyz.max( axis=( 0, 1 ) ) * 10  # Convert to Angstrom
    range_xyz = max_xyz - min_xyz

    # Print formatted output
    with open( outstats, "w" ) as file:
        print( f"lowest Rg = {min_rg:9.2f}   highest Rg = {max_rg:9.2f}", file=file )
        print( f"average  Rg = {avg_rg:9.2f}\n", file=file )

        print( f"minimum x = {min_xyz[0]:7.2f}       maximum x = {max_xyz[0]:6.2f} -> range: {range_xyz[0]:5.2f} Angstroms", file=file )
        print( f"minimum y = {min_xyz[1]:7.2f}       maximum y = {max_xyz[1]:6.2f} -> range: {range_xyz[1]:5.2f} Angstroms", file=file )
        print( f"minimum z = {min_xyz[2]:7.2f}       maximum z = {max_xyz[2]:6.2f} -> range: {range_xyz[2]:5.2f} Angstroms", file=file )

def main():
    args = parse_arguments()
    rgs( args.dcdfile, args.pdbfile, args.dcdfile + ".accepted_rg_results_data.txt", args.dcdfile + ".stats" )
    
if __name__ == "__main__":
    main()
