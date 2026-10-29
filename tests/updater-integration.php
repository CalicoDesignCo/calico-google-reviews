<?php
// Run inside a disposable WordPress Playground, with release ZIPs at /artifacts.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$checks = array();
function verify_cgr( $condition, $label ) {
    global $checks;
    if ( ! $condition ) { throw new Exception( 'FAIL: ' . $label ); }
    $checks[] = $label;
}
wp_set_current_user( 1 );
$plugin = 'calico-google-reviews/calico-google-reviews.php';
deactivate_plugins( $plugin, true );
$installer = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$installed = $installer->install( '/artifacts/calico-google-reviews.zip' );
verify_cgr( ! is_wp_error( $installed ) && true === $installed, 'Exact release ZIP installs on clean WordPress' );
$activation = activate_plugin( $plugin );
verify_cgr( ! is_wp_error( $activation ) && is_plugin_active( $plugin ), 'Plugin activates without errors' );
$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
verify_cgr( '1.12.1' === $data['Version'], 'Installed version is 1.12.1' );
verify_cgr( false !== has_filter( 'update_plugins_github.com', array( 'CGR_Updater', 'update' ) ), 'Native WordPress updater hook registered' );
update_option( 'cgr_settings', array( 'read_more_label' => 'Saved setting survives' ) );

// The first public release has no older public updater-enabled version. Use a
// local header-only older-version fixture, then exercise real WP update APIs.
$path = WP_PLUGIN_DIR . '/' . $plugin;
$new_source = file_get_contents( $path );
file_put_contents( $path, str_replace( 'Version: 1.12.1', 'Version: 1.11.9', $new_source ) );
wp_clean_plugins_cache( true );
$metadata = array( 'id' => 1, 'tag_name' => 'v1.12.1', 'draft' => false, 'prerelease' => false, 'assets' => array( array( 'name' => 'calico-google-reviews.zip', 'state' => 'uploaded', 'size' => filesize( '/artifacts/calico-google-reviews.zip' ), 'browser_download_url' => CGR_Updater::REPO . '/releases/download/v1.12.1/calico-google-reviews.zip' ) ) );
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( $metadata ) {
    if ( CGR_Updater::API === $url ) { return array( 'headers' => array(), 'body' => wp_json_encode( $metadata ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }
    if ( false !== strpos( $url, 'api.wordpress.org/plugins/update-check/' ) ) { return array( 'headers' => array(), 'body' => '{"plugins":{},"translations":[],"no_update":{}}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() ); }
    return $pre;
}, 10, 3 );
delete_site_transient( CGR_Updater::CACHE );
wp_update_plugins();
$updates = get_site_transient( 'update_plugins' );
verify_cgr( isset( $updates->response[$plugin] ) && '1.12.1' === $updates->response[$plugin]->new_version, 'WordPress offers 1.12.1 to an isolated older-version fixture' );
$package = $updates->response[$plugin]->package;
verify_cgr( CGR_Updater::REPO . '/releases/download/v1.12.1/calico-google-reviews.zip' === $package, 'Offered package is version-pinned' );
// Substitute the exact built ZIP for network transport only, keeping the real
// Plugin_Upgrader flow and actual installed plugin/settings intact.
add_filter( 'upgrader_pre_download', static function ( $reply, $url ) use ( $package ) {
    if ( $url !== $package ) { return $reply; }
    $temp = wp_tempnam( 'ccr-package.zip' ); copy( '/artifacts/calico-google-reviews.zip', $temp ); return $temp;
}, 10, 2 );
$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$result = $upgrader->bulk_upgrade( array( $plugin ) );
verify_cgr( is_array( $result ) && ! empty( $result[$plugin] ) && ! is_wp_error( $result[$plugin] ), 'Real WordPress Plugin_Upgrader completes the standard bulk/AJAX upgrade path' );
wp_clean_plugins_cache( true );
$after = get_plugin_data( $path );
verify_cgr( '1.12.1' === $after['Version'], 'Upgrade installs actual 1.12.1 files' );
verify_cgr( is_plugin_active( $plugin ), 'Plugin remains active after upgrade' );
verify_cgr( 'Saved setting survives' === get_option( 'cgr_settings' )['read_more_label'], 'Saved review settings survive upgrade' );
$bad = $metadata; $bad['prerelease'] = true; verify_cgr( false === CGR_Updater::parse( $bad ), 'Prereleases rejected' );
$bad = $metadata; $bad['assets'][0]['browser_download_url'] = 'https://example.com/foreign.zip'; verify_cgr( false === CGR_Updater::parse( $bad ), 'Foreign packages rejected' );
verify_cgr( false === CGR_Updater::update( false, array( 'Version' => '9.0.0' ), $plugin, array() ), 'Downgrades rejected' );
echo count( $checks ) . ' WordPress install, update and updater checks passed on WordPress ' . $GLOBALS['wp_version'] . ' / PHP ' . PHP_VERSION . ".\n";

