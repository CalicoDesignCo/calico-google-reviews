<?php

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

WP_Filesystem();
$result = unzip_file( '/releases/calico-google-reviews-v1.10.0.zip', WP_PLUGIN_DIR );
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}

$plugins = get_plugins();
if ( ! isset( $plugins['calico-google-reviews/calico-google-reviews.php'] ) ) {
	throw new RuntimeException( 'WordPress could not discover the packaged plugin file.' );
}

echo "WordPress discovered the packaged plugin successfully.\n";
