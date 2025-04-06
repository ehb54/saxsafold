<?php

{};

require_once "em.php";

$em = new em();
function em_shutdown() {
    ## echo "em_shutdown\n";
    global $em;
    if ( isset( $em ) ) {
        $em->release_if_has_instance();
    }
}
register_shutdown_function( 'em_shutdown' );

echo $em->status() . "\n";

$pid = getmypid();

echo "pid is $pid\n";

echo "acquire:\n";
$em->acquire( "test-$pid" );
echo sprintf( "ip is %s, id is %s\n", $em->ip(), $em->id() );

echo "status while acquired:\n";
echo $em->status() . "\n";

error_exit( "early shutdown" );

echo "sleeping\n";
sleep( 15 );

echo "release:\n";
$em->release();

echo "status after release:\n";
echo $em->status() . "\n";

