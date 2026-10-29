<?php
/**
 * Plugin Name: Calico Google Reviews
 * Description: Collects, imports, manages, and displays Google reviews using Google Places API, Outscraper API, CSV, or manual entry.
 * Version: 1.12.1
 * Plugin URI: https://github.com/CalicoDesignCo/calico-google-reviews
 * Update URI: https://github.com/CalicoDesignCo/calico-google-reviews
 * Author: Calico Design
 * Text Domain: calico-google-reviews
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CGR_VERSION', '1.12.1' );
define( 'CGR_FILE', __FILE__ );
define( 'CGR_PATH', plugin_dir_path( __FILE__ ) );
define( 'CGR_URL', plugin_dir_url( __FILE__ ) );

require_once CGR_PATH . 'includes/class-cgr-updater.php';
CGR_Updater::register( __FILE__ );

require_once CGR_PATH . 'includes/class-cgr-crypto.php';
require_once CGR_PATH . 'includes/class-cgr-database.php';
require_once CGR_PATH . 'includes/class-cgr-google-api.php';
require_once CGR_PATH . 'includes/class-cgr-credential-policy.php';
require_once CGR_PATH . 'includes/class-cgr-sync.php';
require_once CGR_PATH . 'includes/class-cgr-importer.php';
require_once CGR_PATH . 'includes/class-cgr-admin.php';
require_once CGR_PATH . 'includes/class-cgr-frontend.php';
require_once CGR_PATH . 'includes/class-cgr-plugin.php';

register_activation_hook( __FILE__, array( 'CGR_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CGR_Plugin', 'deactivate' ) );

CGR_Plugin::instance();
