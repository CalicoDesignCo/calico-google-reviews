<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Google_API {
	private function source_defaults() {
		return array(
			'id'                     => '',
			'location_name'          => '',
			'mode'                   => 'all',
			'place_id'               => '',
			'location_title'         => '',
			'location_maps_uri'      => '',
			'average_rating'         => '',
			'total_reviews'          => '',
			'last_error'             => '',
			'review_request_url'     => '',
			'outscraper_query'       => '',
			'outscraper_api_key_enc' => '',
			'outscraper_limit'       => 100,
			'outscraper_last_sync'   => '',
			'outscraper_schedule'    => 'weekly',
			'legacy_keys'            => array(),
		);
	}


	/** Preserve stored review aliases and links while removing retired connection secrets. */
	public function retire_sample_connection() {
		$locations = $this->get_locations();
		foreach ( $locations as $id => &$location ) {
			$location['legacy_keys'] = array_values( array_unique( array_merge( (array) $location['legacy_keys'], $this->legacy_keys( $location ) ) ) );
			if ( 'place' === $location['mode'] || 'business' === $location['mode'] ) { $location['mode'] = 'all'; }
			unset( $location['maps_api_key_enc'], $location['rolling_collection'], $location['last_sync'] );
		}
		unset( $location );
		update_option( 'cgr_locations', $locations, false );
		$settings = get_option( 'cgr_settings', array() );
		if ( ! is_array( $settings ) ) { $settings = array(); }
		if ( isset( $settings['mode'] ) && in_array( $settings['mode'], array( 'place', 'business' ), true ) ) { $settings['mode'] = 'all'; }
		unset( $settings['maps_api_key_enc'], $settings['rolling_collection'], $settings['last_sync'] );
		update_option( 'cgr_settings', $settings, false );
	}

	public function get_settings() {
		$defaults = array(
			'mode'                 => 'all',
			'place_id'             => '',
			'location_title'       => '',
			'location_maps_uri'    => '',
			'average_rating'       => '',
			'total_reviews'        => '',
			'last_error'           => '',
			'review_request_url'    => '',
			'outscraper_query'     => '',
			'outscraper_api_key_enc' => '',
			'outscraper_limit'     => 100,
			'outscraper_last_sync' => '',
			'outscraper_schedule'  => 'weekly',
			'display_source'       => 'all',
			'visible_ratings'      => array( 1, 2, 3, 4, 5 ),
			'require_review_text'  => 'no',
			'truncate_reviews'     => 'no',
			'review_word_limit'    => 40,
			'read_more_label'      => __( 'Read more…', 'calico-google-reviews' ),
		);

		return wp_parse_args( get_option( 'cgr_settings', array() ), $defaults );
	}

	public function update_settings( array $changes ) {
		$settings = array_merge( $this->get_settings(), $changes );
		update_option( 'cgr_settings', $settings, false );
		$source_keys = array_keys( $this->source_defaults() );
		if ( array_intersect( $source_keys, array_keys( $changes ) ) && get_option( 'cgr_locations', false ) ) {
			$this->update_location( $this->get_default_location_id(), array_intersect_key( $changes, array_flip( $source_keys ) ) );
		}
		return $settings;
	}

	public function get_locations() {
		$locations = get_option( 'cgr_locations', array() );
		if ( is_array( $locations ) && ! empty( $locations ) ) {
			foreach ( $locations as $id => $location ) {
				$locations[ $id ] = wp_parse_args( is_array( $location ) ? $location : array(), $this->source_defaults() );
				$locations[ $id ]['id'] = $id;
			}
			return $locations;
		}

		$legacy = $this->get_settings();
		$id = 'primary';
		$location = wp_parse_args( array_intersect_key( $legacy, array_flip( array_keys( $this->source_defaults() ) ) ), $this->source_defaults() );
		$location['id'] = $id;
		$location['location_name'] = ! empty( $legacy['location_title'] ) ? $legacy['location_title'] : __( 'Primary Location', 'calico-google-reviews' );
		$location['legacy_keys'] = $this->legacy_keys( $location );
		$locations = array( $id => $location );
		update_option( 'cgr_locations', $locations, false );
		update_option( 'cgr_default_location', $id, false );
		return $locations;
	}

	public function get_default_location_id() {
		$locations = $this->get_locations();
		$id = sanitize_key( get_option( 'cgr_default_location', '' ) );
		return isset( $locations[ $id ] ) ? $id : (string) key( $locations );
	}

	public function get_location_settings( $location_id = '' ) {
		$locations = $this->get_locations();
		$location_id = sanitize_key( $location_id );
		if ( ! isset( $locations[ $location_id ] ) ) { $location_id = $this->get_default_location_id(); }
		return $locations[ $location_id ];
	}

	public function update_location( $location_id, array $changes ) {
		$locations = $this->get_locations();
		$location_id = sanitize_key( $location_id );
		if ( ! isset( $locations[ $location_id ] ) ) { return new WP_Error( 'cgr_location_missing', __( 'The selected location does not exist.', 'calico-google-reviews' ) ); }
		$legacy_keys = array_merge( is_array( $locations[ $location_id ]['legacy_keys'] ) ? $locations[ $location_id ]['legacy_keys'] : array(), $this->legacy_keys( $locations[ $location_id ] ) );
		$locations[ $location_id ] = array_merge( $locations[ $location_id ], $changes, array( 'id' => $location_id ) );
		$locations[ $location_id ]['legacy_keys'] = array_values( array_unique( array_merge( $legacy_keys, $this->legacy_keys( $locations[ $location_id ] ) ) ) );
		update_option( 'cgr_locations', $locations, false );
		return $locations[ $location_id ];
	}

	public function create_location( $name ) {
		$locations = $this->get_locations();
		$base = sanitize_key( sanitize_title( $name ) );
		if ( '' === $base ) { $base = 'location'; }
		$id = $base;
		$number = 2;
		while ( isset( $locations[ $id ] ) ) { $id = $base . '-' . $number++; }
		$location = $this->source_defaults();
		$location['id'] = $id;
		$location['location_name'] = sanitize_text_field( $name );
		$locations[ $id ] = $location;
		update_option( 'cgr_locations', $locations, false );
		return $id;
	}

	public function delete_location( $location_id ) {
		$locations = $this->get_locations();
		$location_id = sanitize_key( $location_id );
		if ( ! isset( $locations[ $location_id ] ) || count( $locations ) < 2 ) { return new WP_Error( 'cgr_location_delete', __( 'Keep at least one location.', 'calico-google-reviews' ) ); }
		unset( $locations[ $location_id ] );
		update_option( 'cgr_locations', $locations, false );
		if ( $location_id === get_option( 'cgr_default_location' ) ) { update_option( 'cgr_default_location', (string) key( $locations ), false ); }
		return true;
	}

	public function get_location_keys( $location_id ) {
		if ( 'all' === $location_id || '' === $location_id ) { return array(); }
		$location = $this->get_location_settings( $location_id );
		$keys = array_merge( array( 'location:' . $location['id'] ), is_array( $location['legacy_keys'] ) ? $location['legacy_keys'] : array() );
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $keys ) ) ) );
	}

	private function legacy_keys( array $location ) {
		$keys = array();
		if ( ! empty( $location['place_id'] ) ) { $keys[] = 'places:' . $location['place_id']; }
		if ( ! empty( $location['outscraper_query'] ) ) { $keys[] = 'outscraper:' . hash( 'sha256', $location['outscraper_query'] ); }
		return $keys;
	}

	public function get_outscraper_api_key( $location_id = '' ) {
		$settings = $this->get_location_settings( $location_id );
		return CGR_Crypto::decrypt( $settings['outscraper_api_key_enc'] );
	}

	public function get_review_request_url( $location_id = '' ) {
		$settings = $this->get_location_settings( $location_id );
		if ( ! empty( $settings['review_request_url'] ) ) {
			return esc_url_raw( $settings['review_request_url'] );
		}
		$place_id = ! empty( $settings['outscraper_query'] ) ? $settings['outscraper_query'] : $settings['place_id'];
		if ( empty( $place_id ) ) {
			return '';
		}
		return add_query_arg( 'placeid', $place_id, 'https://search.google.com/local/writereview' );
	}

	public function fetch_outscraper_reviews( $location_id = '' ) {
		CGR_Credential_Policy::check();
		$settings = $this->get_location_settings( $location_id );
		$key      = $this->get_outscraper_api_key( $settings['id'] );
		if ( '' === $settings['outscraper_query'] || '' === $key ) {
			return new WP_Error( 'cgr_outscraper_settings_missing', __( 'Enter both an Outscraper API key and a Place ID.', 'calico-google-reviews' ) );
		}
		if ( preg_match( '#^https?://#i', $settings['outscraper_query'] ) ) {
			return new WP_Error( 'cgr_outscraper_url_unsupported', __( 'Enter a Place ID instead of a URL.', 'calico-google-reviews' ) );
		}
		$allowed_limit = CGR_Credential_Policy::reserve( max( 0, min( 10000, absint( $settings['outscraper_limit'] ) ) ) );
		if ( is_wp_error( $allowed_limit ) ) { return $allowed_limit; }

		$url = add_query_arg(
			array(
				'query'        => $settings['outscraper_query'],
				'reviewsLimit' => $allowed_limit,
				'sort'         => 'newest',
				'source'       => 'google',
				'async'        => 'true',
			),
			'https://api.outscraper.com/google-maps-reviews'
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'redirection' => 0,
				'headers' => array( 'X-API-KEY' => $key ),
			)
		);
		return $this->decode_outscraper_job( $response );
	}

	/** Fetch only an existing job; never reserves another scrape or follows a provider-supplied URL. */
	public function fetch_outscraper_result( $request_id, $location_id ) {
		$permission = CGR_Credential_Policy::can_request();
		if ( is_wp_error( $permission ) ) { return $permission; }
		$key = $this->get_outscraper_api_key( $location_id );
		if ( ! $key ) { return new WP_Error( 'cgr_missing_key', __( 'Re-enter the Outscraper API key to retrieve this request.', 'calico-google-reviews' ) ); }
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,160}$/D', $request_id ) ) { return new WP_Error( 'cgr_invalid_job_id', __( 'Invalid Outscraper request ID.', 'calico-google-reviews' ) ); }
		return $this->decode_outscraper_job( wp_remote_get( 'https://api.outscraper.com/requests/' . rawurlencode( $request_id ), array( 'timeout' => 20, 'redirection' => 0, 'headers' => array( 'X-API-KEY' => $key ) ) ), $request_id );
	}

	private function decode_outscraper_job( $response, $request_id = '' ) {
		if ( is_wp_error( $response ) ) { return $response; }
		$http = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 204 === $http || ( is_array( $data ) && isset( $data['status'] ) && in_array( strtolower( $data['status'] ), array( 'failure', 'failed', 'error' ), true ) ) ) {
			return new WP_Error( 'cgr_job_failed', __( 'Outscraper reports that this request failed.', 'calico-google-reviews' ) );
		}
		if ( ! in_array( $http, array( 200, 202 ), true ) || ! is_array( $data ) ) {
			return new WP_Error( $http >= 400 && $http < 500 ? 'cgr_job_rejected' : 'cgr_job_response', sprintf( __( 'Outscraper API returned HTTP %d or an unreadable response.', 'calico-google-reviews' ), $http ) );
		}
		$id = isset( $data['id'] ) && is_string( $data['id'] ) ? $data['id'] : $request_id;
		if ( $id && ! preg_match( '/^[a-zA-Z0-9_-]{1,160}$/D', $id ) ) { return new WP_Error( 'cgr_job_response', __( 'Outscraper returned an invalid request ID.', 'calico-google-reviews' ) ); }
		if ( $request_id && $id !== $request_id ) { return new WP_Error( 'cgr_job_response', __( 'Outscraper returned a different request ID.', 'calico-google-reviews' ) ); }
		$status = isset( $data['status'] ) ? strtolower( (string) $data['status'] ) : '';
		if ( 'success' === $status && isset( $data['data'] ) && is_array( $data['data'] ) ) { return array( 'id' => $id, 'status' => 'success', 'data' => $data['data'] ); }
		if ( $id && in_array( $status, array( 'pending', 'running', 'processing', 'queued' ), true ) ) { return array( 'id' => $id, 'status' => 'pending' ); }
		return new WP_Error( 'cgr_job_response', __( 'Outscraper did not return a usable request ID or completed data.', 'calico-google-reviews' ) );
	}

}
