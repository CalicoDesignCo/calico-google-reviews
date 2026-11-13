<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Sync {

	/** @var CGR_Database */
	private $database;

	/** @var CGR_Google_API */
	private $api;

	public function __construct( CGR_Database $database, CGR_Google_API $api ) {
		$this->database = $database;
		$this->api      = $api;
	}

	public function run_outscraper_sync( $location_id = '' ) {
		$settings = $this->api->get_location_settings( $location_id );
		if ( empty( $settings['outscraper_query'] ) || empty( $settings['outscraper_api_key_enc'] ) ) {
			return;
		}
		$importer = new CGR_Importer( $this->database, $this->api );
		$result   = $importer->import_outscraper( $settings['id'] );
		if ( is_wp_error( $result ) ) {
			$this->api->update_location( $settings['id'], array( 'last_error' => $result->get_error_message() ) );
		}
		return $result;
	}

	public function run_due_location_syncs() {
		$now = time();
		foreach ( $this->api->get_locations() as $location ) {
			$job = CGR_Outscraper_Jobs::get( $location['id'] );
			// Pending results have their own cron; uncertain/failed requests need explicit admin attention.
			if ( $job && 'complete' !== $job['status'] ) { continue; }
			$interval = 'hourly' === $location['outscraper_schedule'] ? HOUR_IN_SECONDS : ( 'daily' === $location['outscraper_schedule'] ? DAY_IN_SECONDS : WEEK_IN_SECONDS );
			$last_sync = $location['outscraper_last_sync'];
			if ( $job && ! empty( $job['completed'] ) && ( ! $last_sync || $job['completed'] > strtotime( $last_sync . ' UTC' ) ) ) { $last_sync = gmdate( 'Y-m-d H:i:s', $job['completed'] ); }
			if ( ! empty( $location['outscraper_query'] ) && ! empty( $location['outscraper_api_key_enc'] ) && $this->is_due( $last_sync, $interval, $now ) ) {
				$this->run_outscraper_sync( $location['id'] );
			}
		}
	}

	private function is_due( $last_sync, $interval, $now ) {
		$last = $last_sync ? strtotime( $last_sync . ' UTC' ) : 0;
		return ! $last || $last + $interval <= $now;
	}

}
