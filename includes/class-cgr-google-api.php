<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Google_API {
	private function source_defaults() {
		return array(
			'id'                     => '',
			'location_name'          => '',
			'mode'                   => 'place',
			'place_id'               => '',
			'maps_api_key_enc'       => '',
			'location_title'         => '',
			'location_maps_uri'      => '',
			'average_rating'         => '',
			'total_reviews'          => '',
			'last_sync'              => '',
			'last_error'             => '',
			'rolling_collection'     => 'yes',
			'review_request_url'     => '',
			'outscraper_query'       => '',
			'outscraper_api_key_enc' => '',
			'outscraper_limit'       => 100,
			'outscraper_last_sync'   => '',
			'outscraper_schedule'    => 'weekly',
			'legacy_keys'            => array(),
		);
	}

	public function get_settings() {
		$defaults = array(
			'mode'                 => 'place',
			'place_id'             => '',
			'maps_api_key_enc'     => '',
			'location_title'       => '',
			'location_maps_uri'    => '',
			'average_rating'       => '',
			'total_reviews'        => '',
			'last_sync'            => '',
			'last_error'           => '',
			'rolling_collection'   => 'yes',
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

	public function get_maps_api_key( $location_id = '' ) {
		$settings = $this->get_location_settings( $location_id );
		return CGR_Crypto::decrypt( $settings['maps_api_key_enc'] );
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
		if ( empty( $settings['place_id'] ) ) {
			return '';
		}
		return add_query_arg( 'placeid', $settings['place_id'], 'https://search.google.com/local/writereview' );
	}

	public function fetch_place( $location_id = '' ) {
		$settings = $this->get_location_settings( $location_id );
		$api_key  = $this->get_maps_api_key( $settings['id'] );
		if ( '' === $settings['place_id'] || '' === $api_key ) {
			return new WP_Error( 'cgr_place_settings_missing', __( 'Enter both a Google Place ID and Google Places API key.', 'calico-google-reviews' ) );
		}

		$url      = 'https://places.googleapis.com/v1/places/' . rawurlencode( $settings['place_id'] ) . '?languageCode=' . rawurlencode( get_locale() );
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => 'id,displayName,rating,userRatingCount,googleMapsUri,reviews',
				),
			)
		);

		return $this->decode_response( $response );
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
				'async'        => 'false',
			),
			'https://api.outscraper.com/google-maps-reviews'
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 120,
				'headers' => array( 'X-API-KEY' => $key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || ! is_array( $data ) ) {
			$message = isset( $data['errorMessage'] ) ? $data['errorMessage'] : sprintf( __( 'Outscraper API returned HTTP %d.', 'calico-google-reviews' ), $status );
			return new WP_Error( 'cgr_outscraper_error', sanitize_text_field( $message ) );
		}
		if ( isset( $data['status'] ) && 'Success' !== $data['status'] ) {
			return new WP_Error( 'cgr_outscraper_pending', __( 'Outscraper API did not complete the request synchronously. Try a smaller review limit.', 'calico-google-reviews' ) );
		}
		return isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
	}

	private function decode_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );
		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( __( 'Google returned HTTP %d.', 'calico-google-reviews' ), $status );
			return new WP_Error( 'cgr_google_api_error', sanitize_text_field( $message ), array( 'status' => $status ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'cgr_invalid_google_response', __( 'Google returned an invalid response.', 'calico-google-reviews' ) );
		}

		return $data;
	}

}
