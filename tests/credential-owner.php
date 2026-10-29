<?php
require_once '/wordpress/wp-load.php';
require_once '/plugin/calico-google-reviews.php';
function owner_assert( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$owner = wp_insert_user( array( 'user_login' => 'protection-owner', 'user_email' => CGR_Credential_Policy::OWNER_EMAIL, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
$other = wp_insert_user( array( 'user_login' => 'other-administrator', 'user_email' => 'other@example.com', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
owner_assert( ! is_wp_error( $owner ) && ! is_wp_error( $other ), 'Test users failed to create.' );
wp_set_current_user( $owner );
owner_assert( CGR_Credential_Policy::can_manage(), 'Owner administrator was denied.' );
$user = new WP_User( $owner ); $user->set_role( 'subscriber' );
wp_set_current_user( 0 ); wp_set_current_user( $owner );
owner_assert( ! CGR_Credential_Policy::can_manage(), 'Owner email without administrator capability was allowed.' );
$user->set_role( 'administrator' );
wp_set_current_user( $other );
update_option( 'admin_email', CGR_Credential_Policy::OWNER_EMAIL );
update_option( 'cgr_credential_policy', array( 'enabled' => true, 'expires' => time() + DAY_IN_SECONDS, 'email' => 'other@example.com' ) );
owner_assert( ! CGR_Credential_Policy::can_manage(), 'Notification recipient or site email granted policy access.' );
$before = get_option( 'cgr_credential_policy' );
add_filter( 'wp_die_handler', function () { return function ( $message, $title, $args ) { if ( 403 !== $args['response'] ) { throw new RuntimeException( 'Wrong HTTP denial status.' ); } throw new LogicException( 'owner-denied' ); }; } );
$policy = new CGR_Credential_Policy();
foreach ( array( 'page', 'save' ) as $method ) {
	$_POST = array( 'renew' => '1', 'days' => '3650' );
	try { $policy->$method(); throw new RuntimeException( 'Non-owner reached protected action.' ); }
	catch ( LogicException $error ) { owner_assert( 'owner-denied' === $error->getMessage(), 'Unexpected denial.' ); }
}
owner_assert( $before === get_option( 'cgr_credential_policy' ), 'Unauthorized POST changed policy.' );
wp_set_current_user( 0 );
owner_assert( ! CGR_Credential_Policy::can_manage(), 'Anonymous access allowed.' );
echo "Credential owner access tests passed: owner identity, capability, forged POST, direct page and email isolation.\n";
