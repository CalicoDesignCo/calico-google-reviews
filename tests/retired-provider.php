<?php
require_once '/wordpress/wp-load.php';
require_once '/plugin/calico-google-reviews.php';
function retirement_assert( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
update_option( 'cgr_settings', array( 'mode' => 'place', 'place_id' => 'ChIJArchive', 'maps_api_key_enc' => 'retired-secret', 'rolling_collection' => 'yes' ) );
delete_option( 'cgr_locations' );
$api = new CGR_Google_API();
$api->retire_sample_connection();
$location = $api->get_location_settings();
retirement_assert( ! isset( $location['maps_api_key_enc'], $location['rolling_collection'] ) && 'all' === $location['mode'], 'Retired location configuration survived.' );
$legacy = get_option( 'cgr_settings' );
retirement_assert( ! isset( $legacy['maps_api_key_enc'] ) && ! isset( $legacy['rolling_collection'] ), 'Legacy secret survived.' );
retirement_assert( in_array( 'places:ChIJArchive', $api->get_location_keys( 'primary' ), true ), 'Archive location alias was lost.' );
retirement_assert( false !== strpos( $api->get_review_request_url(), 'ChIJArchive' ), 'Existing review-request link was lost.' );
$api->retire_sample_connection();
retirement_assert( ! method_exists( $api, 'fetch_place' ) && ! method_exists( $api, 'get_maps_api_key' ), 'Retired network methods remain callable.' );
retirement_assert( ! has_action( 'admin_post_cgr_sync_place' ) && ! has_action( 'admin_post_cgr_test_place' ), 'Retired action handlers remain registered.' );
$api->update_location( 'primary', array( 'mode' => 'outscraper', 'outscraper_query' => 'ChIJNewListing', 'outscraper_api_key_enc' => CGR_Crypto::encrypt( 'fake-outs-key' ) ) );
$calls = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$calls ) {
	retirement_assert( 0 === strpos( $url, 'https://api.outscraper.com/' ), 'Request sent to a retired provider.' );
	retirement_assert( 'fake-outs-key' === $args['headers']['X-API-KEY'], 'Outscraper authentication failed.' );
	$calls++;
	return array( 'response' => array( 'code' => 200 ), 'headers' => array(), 'body' => '{"status":"Success","data":[]}' );
}, 10, 3 );
$result = $api->fetch_outscraper_reviews( 'primary' );
retirement_assert( ! is_wp_error( $result ) && 1 === $calls, 'Outscraper request no longer works.' );
retirement_assert( false !== strpos( $api->get_review_request_url(), 'ChIJNewListing' ), 'Outscraper Place ID is not used for review links.' );
echo "Retired provider migration and Outscraper regression tests passed. No external API requests made.\n";
