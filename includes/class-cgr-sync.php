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

	public function collect_place_reviews( $location_id = '' ) {
		$settings = $this->api->get_location_settings( $location_id );
		if ( 'yes' !== $settings['rolling_collection'] || empty( $settings['place_id'] ) || empty( $settings['maps_api_key_enc'] ) ) {
			return;
		}
		$data = $this->api->fetch_place( $settings['id'] );
		if ( is_wp_error( $data ) ) {
			$this->api->update_location( $settings['id'], array( 'last_error' => $data->get_error_message() ) );
			return $data;
		}

		$count = 0;
		$now   = current_time( 'mysql', true );
		foreach ( isset( $data['reviews'] ) ? $data['reviews'] : array() as $review ) {
			$author = isset( $review['authorAttribution'] ) && is_array( $review['authorAttribution'] ) ? $review['authorAttribution'] : array();
			$text   = isset( $review['text']['text'] ) ? $review['text']['text'] : ( isset( $review['originalText']['text'] ) ? $review['originalText']['text'] : '' );
			$date   = $this->api_time_to_mysql( isset( $review['publishTime'] ) ? $review['publishTime'] : '' );
			$id     = isset( $review['name'] ) ? $review['name'] : hash( 'sha256', ( isset( $author['displayName'] ) ? $author['displayName'] : '' ) . '|' . $text . '|' . $date );
			$review_data = array(
					'location_key'       => 'location:' . $settings['id'],
					'external_id'        => sanitize_text_field( $id ),
					'source'             => 'places',
					'reviewer_name'      => sanitize_text_field( isset( $author['displayName'] ) ? $author['displayName'] : __( 'Google reviewer', 'calico-google-reviews' ) ),
					'reviewer_photo_url' => esc_url_raw( isset( $author['photoUri'] ) ? $author['photoUri'] : '' ),
					'reviewer_url'       => esc_url_raw( isset( $author['uri'] ) ? $author['uri'] : '' ),
					'rating'             => isset( $review['rating'] ) ? absint( $review['rating'] ) : 0,
					'review_text'        => sanitize_textarea_field( $text ),
					'create_time'        => $date,
					'update_time'        => $date,
					'source_url'         => esc_url_raw( isset( $review['googleMapsUri'] ) ? $review['googleMapsUri'] : ( isset( $data['googleMapsUri'] ) ? $data['googleMapsUri'] : '' ) ),
					'last_seen_at'       => $now,
					'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + ( 10 * YEAR_IN_SECONDS ) ),
					'_location_keys'     => $this->api->get_location_keys( $settings['id'] ),
				);
			if ( $this->database->review_exists( $review_data ) ) {
				continue;
			}
			$saved = $this->database->upsert_review( $review_data );
			if ( ! is_wp_error( $saved ) ) {
				$count++;
			}
		}
		$this->api->update_location(
			$settings['id'],
			array(
				'location_title'    => isset( $data['displayName']['text'] ) ? sanitize_text_field( $data['displayName']['text'] ) : $settings['location_title'],
				'location_maps_uri' => isset( $data['googleMapsUri'] ) ? esc_url_raw( $data['googleMapsUri'] ) : $settings['location_maps_uri'],
				'average_rating'    => isset( $data['rating'] ) ? (float) $data['rating'] : $settings['average_rating'],
				'total_reviews'     => isset( $data['userRatingCount'] ) ? absint( $data['userRatingCount'] ) : $settings['total_reviews'],
				'last_sync'         => $now,
				'last_error'        => '',
			)
		);
		return array( 'count' => $count );
	}

	public function run_due_location_syncs() {
		$now = time();
		foreach ( $this->api->get_locations() as $location ) {
			if ( 'yes' === $location['rolling_collection'] && ! empty( $location['place_id'] ) && ! empty( $location['maps_api_key_enc'] ) && $this->is_due( $location['last_sync'], WEEK_IN_SECONDS, $now ) ) {
				$this->collect_place_reviews( $location['id'] );
			}
			$interval = 'hourly' === $location['outscraper_schedule'] ? HOUR_IN_SECONDS : ( 'daily' === $location['outscraper_schedule'] ? DAY_IN_SECONDS : WEEK_IN_SECONDS );
			if ( ! empty( $location['outscraper_query'] ) && ! empty( $location['outscraper_api_key_enc'] ) && $this->is_due( $location['outscraper_last_sync'], $interval, $now ) ) {
				$this->run_outscraper_sync( $location['id'] );
			}
		}
	}

	private function is_due( $last_sync, $interval, $now ) {
		$last = $last_sync ? strtotime( $last_sync . ' UTC' ) : 0;
		return ! $last || $last + $interval <= $now;
	}

	private function api_time_to_mysql( $value ) {
		if ( ! $value ) {
			return null;
		}

		$timestamp = strtotime( $value );
		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
