<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Plugin {

	/** @var CGR_Plugin|null */
	private static $instance = null;

	/** @var CGR_Database */
	private $database;

	/** @var CGR_Google_API */
	private $api;

	/** @var CGR_Sync */
	private $sync;

	/** @var CGR_Importer */
	private $importer;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->database = new CGR_Database();
		$this->api      = new CGR_Google_API();
		$this->sync     = new CGR_Sync( $this->database, $this->api );
		$this->importer = new CGR_Importer( $this->database, $this->api );

		new CGR_Admin( $this->database, $this->api, $this->sync, $this->importer );
		new CGR_Credential_Policy();
		$jobs = new CGR_Outscraper_Jobs( $this->database, $this->api );
		add_action( CGR_Outscraper_Jobs::HOOK, array( $jobs, 'poll' ) );
		$frontend = new CGR_Frontend( $this->database, $this->api );

		add_action( 'cgr_location_sync', array( $this->sync, 'run_due_location_syncs' ) );
		add_action( 'cgr_daily_cleanup', array( $this->database, 'delete_expired_reviews' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 20 );
		add_action( 'elementor/widgets/register', array( $frontend, 'register_elementor_widget' ) );
		add_action( 'elementor/elements/categories_registered', array( $frontend, 'register_elementor_category' ) );
	}

	public static function activate() {
		$database = new CGR_Database();
		$database->install();
		self::remove_legacy_connection_settings();
		$api = new CGR_Google_API();
		$api->retire_sample_connection();

		if ( ! wp_next_scheduled( 'cgr_daily_cleanup' ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', 'cgr_daily_cleanup' );
		}

		wp_clear_scheduled_hook( 'cgr_five_minute_collection' );
		wp_clear_scheduled_hook( 'cgr_daily_sync' );
		wp_clear_scheduled_hook( 'cgr_weekly_collection' );
		wp_clear_scheduled_hook( 'cgr_outscraper_sync' );
		if ( ! wp_next_scheduled( 'cgr_location_sync' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'cgr_location_sync' ); }
	}

	public static function deactivate() {
		$api = new CGR_Google_API();
		foreach ( $api->get_locations() as $id => $location ) { wp_clear_scheduled_hook( CGR_Outscraper_Jobs::HOOK, array( $id ) ); }
		wp_clear_scheduled_hook( 'cgr_daily_cleanup' );
		wp_clear_scheduled_hook( 'cgr_five_minute_collection' );
		wp_clear_scheduled_hook( 'cgr_weekly_collection' );
		wp_clear_scheduled_hook( 'cgr_outscraper_sync' );
		wp_clear_scheduled_hook( 'cgr_location_sync' );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['cgr_weekly'] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display'  => __( 'Once weekly', 'calico-google-reviews' ),
		);
		return $schedules;
	}

	public static function reschedule_outscraper( $choice ) {
		wp_clear_scheduled_hook( 'cgr_outscraper_sync' );
		if ( ! wp_next_scheduled( 'cgr_location_sync' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'cgr_location_sync' ); }
	}

	public function maybe_upgrade() {
		$jobs = new CGR_Outscraper_Jobs( $this->database, $this->api );
		$jobs->resume();
		if ( CGR_VERSION !== get_option( 'cgr_db_version' ) ) {
			$this->database->install();
			self::remove_legacy_connection_settings();
			wp_clear_scheduled_hook( 'cgr_daily_sync' );
			wp_clear_scheduled_hook( 'cgr_five_minute_collection' );
			$this->api->retire_sample_connection();
		}
		wp_clear_scheduled_hook( 'cgr_weekly_collection' );
		wp_clear_scheduled_hook( 'cgr_outscraper_sync' );
		if ( ! wp_next_scheduled( 'cgr_location_sync' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'cgr_location_sync' ); }
	}

	private static function remove_legacy_connection_settings() {
		$settings = get_option( 'cgr_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( isset( $settings['mode'] ) && 'business' === $settings['mode'] ) {
			$settings['mode'] = 'all';
		}
		foreach ( array( 'oauth_client_id', 'oauth_client_secret_enc', 'location_name' ) as $legacy_key ) {
			unset( $settings[ $legacy_key ] );
		}
		update_option( 'cgr_settings', $settings, false );
		delete_option( 'cgr_oauth_tokens' );
	}
}
