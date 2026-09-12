<?php
add_action( 'init', function () {
	if ( ( $_GET['amq_debug'] ?? '' ) !== 'go' ) {
		return;
	}
	$products = wc_get_products( [ 'status' => 'publish', 'featured' => true, 'limit' => -1 ] );
	echo 'count: ' . count( $products ) . "\n";
	foreach ( $products as $p ) {
		echo $p->get_id() . ' ' . $p->get_name() . "\n";
	}
	echo "\nfile_mtime_of_plugin: " . filemtime( WPMU_PLUGIN_DIR . '/amouriq-bestsellers-carousel.php' ) . "\n";
	echo "current_time: " . time() . "\n";
	exit;
} );
