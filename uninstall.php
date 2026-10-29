<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

wp_clear_scheduled_hook( 'cgr_daily_sync' );
wp_clear_scheduled_hook( 'cgr_daily_cleanup' );
wp_clear_scheduled_hook( 'cgr_five_minute_collection' );
wp_clear_scheduled_hook( 'cgr_weekly_collection' );
wp_clear_scheduled_hook( 'cgr_outscraper_sync' );
wp_clear_scheduled_hook( 'cgr_location_sync' );
delete_option( 'cgr_settings' );
delete_option( 'cgr_locations' );
delete_option( 'cgr_default_location' );
delete_option( 'cgr_credential_policy' );
delete_option( 'cgr_credential_usage' );
delete_option( 'cgr_policy_budget_lock' );
delete_option( 'cgr_policy_mail_lock' );
delete_option( 'cgr_oauth_tokens' );
delete_option( 'cgr_db_version' );
delete_transient( 'cgr_sync_lock' );

$table_name = $wpdb->prefix . 'cgr_reviews';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
