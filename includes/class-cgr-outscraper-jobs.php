<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Durable, per-location requests. A pending or uncertain submission is never submitted again. */
final class CGR_Outscraper_Jobs {
	const HOOK = 'cgr_outscraper_poll';
	private $database;
	private $api;

	public function __construct( CGR_Database $database, CGR_Google_API $api ) {
		$this->database = $database;
		$this->api = $api;
	}

	public static function get( $id ) {
		$job = get_option( 'cgr_outscraper_job_' . sanitize_key( $id ), array() );
		return is_array( $job ) ? $job : array();
	}

	private function save( $id, $job ) {
		update_option( 'cgr_outscraper_job_' . $id, $job, false );
	}

	private function schedule( $id, $timestamp ) {
		$args = array( $id );
		wp_clear_scheduled_hook( self::HOOK, $args );
		wp_schedule_single_event( $timestamp, self::HOOK, $args );
	}

	/** $restart is only used by the separately confirmed admin action. */
	public function run( $id = '', $force = false, $restart = false ) {
		$locations = $this->api->get_locations();
		$id = $id ? sanitize_key( $id ) : $this->api->get_default_location_id();
		if ( ! isset( $locations[ $id ] ) ) { return new WP_Error( 'cgr_location_missing', __( 'This location no longer exists.', 'calico-google-reviews' ) ); }
		$lock = 'cgr_outscraper_lock_' . $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 600 ) { delete_option( $lock ); }
			return new WP_Error( 'cgr_job_busy', __( 'This location is already being checked. Try again shortly.', 'calico-google-reviews' ) );
		}
		try {
			$job = self::get( $id );
			$previous = $job;
			$submitting = false;
			$location = $locations[ $id ];
			if ( $job && 'submitting' === $job['status'] && $job['started'] < time() - 60 ) {
				$job['status'] = 'unknown';
				$job['message'] = __( 'Submission was interrupted before a request ID was saved. Check Outscraper Usage History before confirming another scrape.', 'calico-google-reviews' );
				$this->save( $id, $job );
			}
			$active = $job && in_array( $job['status'], array( 'submitting', 'pending' ), true );
			if ( $restart && $active ) { return new WP_Error( 'cgr_job_active', __( 'Check the existing request instead of starting another scrape.', 'calico-google-reviews' ) ); }
			if ( $restart ) { $job = array(); }
			if ( $job && 'complete' !== $job['status'] && 'pending' !== $job['status'] ) {
				return new WP_Error( 'cgr_job_attention', $job['message'] ? $job['message'] : __( 'This request needs attention. Starting a new request requires confirmation.', 'calico-google-reviews' ) );
			}
			if ( $job && 'pending' === $job['status'] ) {
				if ( ! $force && $job['next_check'] > time() ) { return $job; }
				if ( $job['started'] < time() - DAY_IN_SECONDS ) {
					$job['status'] = 'expired';
					$job['message'] = __( 'Result checks stopped after 24 hours. Outscraper may have expired the result. No replacement scrape was started.', 'calico-google-reviews' );
					$this->save( $id, $job );
					return new WP_Error( 'cgr_job_expired', $job['message'] );
				}
				if ( hash( 'sha256', $location['outscraper_query'] ) !== $job['query_hash'] ) {
					$job['next_check'] = time() + 300;
					$this->schedule( $id, $job['next_check'] );
					$job['message'] = __( 'The Place ID changed while this request was pending. Results were not imported into a different business. Restore the original Place ID to resume checks.', 'calico-google-reviews' );
					$this->save( $id, $job );
					return new WP_Error( 'cgr_job_changed', $job['message'] );
				}
				$response = $this->api->fetch_outscraper_result( $job['id'], $id );
			} else {
				$submitting = true;
				$job = array( 'status' => 'submitting', 'id' => '', 'started' => time(), 'next_check' => 0, 'checks' => 0, 'message' => '', 'query_hash' => hash( 'sha256', $location['outscraper_query'] ) );
				// Persist before the network call: even a PHP crash cannot silently trigger another scrape.
				$this->save( $id, $job );
				$response = $this->api->fetch_outscraper_reviews( $id );
			}
			if ( is_wp_error( $response ) ) {
				// A preflight refusal did not send a scrape. Preserve normal scheduled recovery after renewal/month rollover.
				if ( $submitting && in_array( $response->get_error_code(), array( 'cgr_expired', 'cgr_paused', 'cgr_usage_limit', 'cgr_busy', 'cgr_outscraper_settings_missing', 'cgr_outscraper_url_unsupported' ), true ) ) {
					if ( $previous ) { $this->save( $id, $previous ); } else { delete_option( 'cgr_outscraper_job_' . $id ); }
					return $response;
				}
				$job['message'] = $response->get_error_message();
				if ( ! $job['id'] ) {
					$job['status'] = in_array( $response->get_error_code(), array( 'http_request_failed', 'cgr_job_response' ), true ) ? 'unknown' : 'failed';
					if ( 'unknown' === $job['status'] ) { $job['message'] .= ' ' . __( 'No request ID was received. Check Outscraper Usage History before confirming another scrape.', 'calico-google-reviews' ); }
				} elseif ( 'cgr_job_failed' === $response->get_error_code() ) {
					$job['status'] = 'failed';
				} else {
					// Transport/authentication errors retain the ID and retry retrieval, never submission.
					$job['checks']++;
					$job['next_check'] = time() + min( 300, 60 * $job['checks'] );
					$this->schedule( $id, $job['next_check'] );
				}
				$this->save( $id, $job );
				return new WP_Error( $response->get_error_code(), $job['message'] );
			}
			$job['id'] = $response['id'];
			$job['message'] = '';
			$job['status'] = 'pending';
			$this->save( $id, $job );
			if ( 'success' === $response['status'] ) {
				// Save the ID before importing. If import fails, retrieving again is safe and deduplicated.
				$job['status'] = 'pending';
				$this->save( $id, $job );
				$importer = new CGR_Importer( $this->database, $this->api );
				$result = $importer->import_outscraper_data( $response['data'], $id );
				if ( is_wp_error( $result ) ) {
					$job['message'] = $result->get_error_message();
					$job['status'] = $job['id'] ? 'pending' : 'failed';
					$job['next_check'] = time() + 60;
					if ( $job['id'] ) { $this->schedule( $id, $job['next_check'] ); }
					$this->save( $id, $job );
					return $result;
				}
				$job = array_merge( $job, $result, array( 'status' => 'complete', 'completed' => time(), 'next_check' => 0 ) );
				wp_clear_scheduled_hook( self::HOOK, array( $id ) );
			} else {
				$job['status'] = 'pending';
				$job['checks']++;
				$job['next_check'] = time() + 60;
				$this->schedule( $id, $job['next_check'] );
			}
			$this->save( $id, $job );
			$this->api->update_location( $id, array( 'last_error' => '' ) );
			return $job;
		} finally { delete_option( $lock ); }
	}

	public function poll( $id ) {
		$job = self::get( $id );
		if ( $job && 'pending' === $job['status'] ) {
			$result = $this->run( $id );
			if ( is_wp_error( $result ) && 'cgr_job_busy' === $result->get_error_code() ) { $this->schedule( $id, time() + 60 ); }
			return $result;
		}
	}

	/** Restore checks after reactivation or a missed cron event without submitting new work. */
	public function resume() {
		foreach ( $this->api->get_locations() as $id => $location ) {
			$job = self::get( $id );
			if ( $job && 'pending' === $job['status'] && ! wp_next_scheduled( self::HOOK, array( $id ) ) ) { $this->schedule( $id, max( time() + 5, $job['next_check'] ) ); }
		}
	}
}
