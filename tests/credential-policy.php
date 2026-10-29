<?php
require_once '/wordpress/wp-load.php';
require_once '/plugin/calico-google-reviews.php';
function policy_assert( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$recipients = array();
add_filter( 'pre_wp_mail', function ( $result, $args ) use ( &$recipients ) { $recipients[] = $args['to']; return true; }, 10, 2 );
update_option( 'admin_email', 'owner@example.com' );
$api = new CGR_Google_API();
$api->get_locations();
$api->update_settings( array( 'outscraper_api_key_enc' => 'legacy-secret' ) );
$other = $api->create_location( 'Second' );
$api->update_location( $other, array( 'outscraper_api_key_enc' => 'second-secret' ) );
update_option( 'cgr_credential_policy', array( 'enabled' => true, 'expires' => time() - 1 ) );
CGR_Credential_Policy::check();
foreach ( $api->get_locations() as $location ) { policy_assert( '' === $location['outscraper_api_key_enc'], 'Expired location credential survived.' ); }
policy_assert( '' === get_option( 'cgr_settings' )['outscraper_api_key_enc'], 'Legacy key survived.' );
policy_assert( array( 'owner@example.com' ) === $recipients, 'Default admin recipient failed.' );
CGR_Credential_Policy::check();
policy_assert( 1 === count( $recipients ), 'Duplicate expiration email.' );
policy_assert( is_wp_error( CGR_Credential_Policy::reserve( 10 ) ), 'Expired request allowed.' );
update_option( 'cgr_credential_policy', array( 'enabled' => true, 'expires' => time() + 10 * DAY_IN_SECONDS, 'email' => 'agency@example.com', 'per_pull' => 30, 'monthly_pulls' => 2, 'monthly_reviews' => 50 ) );
policy_assert( 30 === CGR_Credential_Policy::reserve( 0 ), 'Unlimited pull was not capped.' );
policy_assert( 20 === CGR_Credential_Policy::reserve( 100 ), 'Remaining monthly budget not respected.' );
policy_assert( is_wp_error( CGR_Credential_Policy::reserve( 1 ) ), 'Monthly limit bypassed.' );
policy_assert( 'agency@example.com' === end( $recipients ), 'Custom warning recipient failed.' );
update_option( 'cgr_credential_usage', array( 'month' => '2000-01', 'pulls' => 999, 'reviews' => 999 ) );
policy_assert( 10 === CGR_Credential_Policy::reserve( 10 ), 'Calendar month reset failed.' );
$s = CGR_Credential_Policy::settings(); $s['paused'] = true; update_option( 'cgr_credential_policy', $s );
policy_assert( is_wp_error( CGR_Credential_Policy::reserve( 1 ) ), 'Pause bypassed.' );
echo "Credential protection regression tests passed. Email delivery was intercepted.\n";
