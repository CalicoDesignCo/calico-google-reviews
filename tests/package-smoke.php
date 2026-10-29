<?php

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

WP_Filesystem();
$result = unzip_file( '/release.zip', WP_PLUGIN_DIR );
if ( is_wp_error( $result ) ) {
	throw new RuntimeException( $result->get_error_message() );
}

$plugins = get_plugins();
if ( ! isset( $plugins['calico-google-reviews/calico-google-reviews.php'] ) ) {
	throw new RuntimeException( 'WordPress could not discover the packaged plugin file.' );
}

$activation = activate_plugin( 'calico-google-reviews/calico-google-reviews.php' );
if ( is_wp_error( $activation ) ) {
	throw new RuntimeException( $activation->get_error_message() );
}
if ( ! defined( 'CGR_VERSION' ) || '1.12.0' !== CGR_VERSION ) {
	throw new RuntimeException( 'The packaged plugin did not activate as version 1.12.0.' );
}

echo "WordPress discovered and activated the packaged plugin successfully.\n";
