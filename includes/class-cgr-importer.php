<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Importer {

	/** @var CGR_Database */
	private $database;

	/** @var CGR_Google_API */
	private $api;

	public function __construct( CGR_Database $database, CGR_Google_API $api ) {
		$this->database = $database;
		$this->api      = $api;
	}

	public function import_manual( array $values, $location_id = '' ) {
		$row = array(
			'reviewer_name' => isset( $values['reviewer_name'] ) ? $values['reviewer_name'] : '',
			'rating'        => isset( $values['rating'] ) ? $values['rating'] : 0,
			'review_text'   => isset( $values['review_text'] ) ? $values['review_text'] : '',
			'create_time'   => isset( $values['review_date'] ) ? $values['review_date'] : '',
			'reply_text'    => isset( $values['reply_text'] ) ? $values['reply_text'] : '',
			'source_url'    => isset( $values['source_url'] ) ? $values['source_url'] : '',
		);
		return $this->import_rows( array( $row ), 'manual', false, $location_id );
	}

	public function import_csv( $path, $location_id = '' ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'cgr_csv_unreadable', __( 'The CSV file could not be read.', 'calico-google-reviews' ) );
		}

		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return new WP_Error( 'cgr_csv_open_failed', __( 'The CSV file could not be opened.', 'calico-google-reviews' ) );
		}

		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			return new WP_Error( 'cgr_csv_empty', __( 'The CSV file is empty.', 'calico-google-reviews' ) );
		}
		$headers        = array_map( array( $this, 'normalize_header' ), $headers );
		$export_columns = array( 'location_key', 'external_id', 'source', 'reviewer_name', 'reviewer_photo_url', 'reviewer_url', 'rating', 'review_text', 'create_time', 'update_time', 'reply_text', 'reply_update_time', 'source_url', 'last_seen_at', 'expires_at' );
		$is_export      = empty( array_diff( $export_columns, $headers ) );
		$rows           = array();
		$imported       = 0;
		$skipped        = 0;
		while ( ( $values = fgetcsv( $handle ) ) !== false ) {
			$values = array_pad( $values, count( $headers ), '' );
			$rows[] = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
			if ( count( $rows ) >= 500 ) {
				$result = $this->import_rows( $rows, 'csv', $is_export, $location_id );
				if ( is_wp_error( $result ) ) {
					fclose( $handle );
					return $result;
				}
				$imported += $result['imported'];
				$skipped  += $result['skipped'];
				$rows      = array();
			}
		}
		fclose( $handle );
		if ( $rows ) {
			$result = $this->import_rows( $rows, 'csv', $is_export, $location_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$imported += $result['imported'];
			$skipped  += $result['skipped'];
		}

		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	public function import_outscraper( $location_id = '' ) {
		$location = $this->api->get_location_settings( $location_id );
		$data = $this->api->fetch_outscraper_reviews( $location['id'] );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$rows = array();
		foreach ( $data as $business ) {
			if ( isset( $business['reviews_data'] ) && is_array( $business['reviews_data'] ) ) {
				$rows = array_merge( $rows, $business['reviews_data'] );
			} elseif ( isset( $business['reviews'] ) && is_array( $business['reviews'] ) ) {
				$rows = array_merge( $rows, $business['reviews'] );
			}
		}

		$result = $this->import_rows( $rows, 'outscraper', false, $location['id'] );
		if ( ! is_wp_error( $result ) ) {
			$business = isset( $data[0] ) && is_array( $data[0] ) ? $data[0] : array();
			$this->api->update_location(
				$location['id'],
				array(
					'outscraper_last_sync' => current_time( 'mysql', true ),
					'location_title'       => isset( $business['name'] ) ? sanitize_text_field( $business['name'] ) : '',
					'location_maps_uri'    => isset( $business['location_link'] ) ? esc_url_raw( $business['location_link'] ) : '',
					'average_rating'       => isset( $business['rating'] ) ? (float) $business['rating'] : '',
					'total_reviews'        => isset( $business['reviews'] ) ? absint( $business['reviews'] ) : count( $rows ),
					'last_error'           => '',
				)
			);
		}
		return $result;
	}

	public function import_rows( array $rows, $source, $preserve_metadata = false, $location_id = '' ) {
		$location_settings = $this->api->get_location_settings( $location_id );
		$location = 'location:' . $location_settings['id'];
		$duplicate_location_keys = $this->api->get_location_keys( $location_settings['id'] );
		$imported = 0;
		$skipped  = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				$skipped++;
				continue;
			}
			$name   = $this->pick( $row, array( 'reviewer_name', 'author_title', 'author_name', 'name' ) );
			$text   = $this->pick( $row, array( 'review_text', 'review_text_translated', 'text', 'comment' ) );
			$rating = (int) $this->pick( $row, array( 'rating', 'review_rating', 'stars', 'star_rating' ) );
			$date_value = $this->pick( $row, array( 'create_time', 'review_datetime_utc', 'review_date', 'date', 'datetime' ) );
			$date       = $preserve_metadata ? $this->optional_mysql_date( $date_value ) : $this->mysql_date( $date_value );
			if ( '' === trim( (string) $name ) || $rating < 1 || $rating > 5 ) {
				$skipped++;
				continue;
			}

			$external = $this->pick( $row, array( 'external_id', 'review_id', 'id' ) );
			if ( '' === (string) $external && '' !== $this->pick( $row, array( 'review_link' ) ) ) {
				$external = hash( 'sha256', $this->pick( $row, array( 'review_link' ) ) );
			}
			if ( '' === (string) $external ) {
				$external = hash( 'sha256', strtolower( trim( $name ) ) . '|' . $rating . '|' . trim( $text ) . '|' . $date );
			}

			$row_source   = $preserve_metadata ? sanitize_key( $this->pick( $row, array( 'source' ) ) ) : sanitize_key( $source );
			$row_location = $preserve_metadata ? sanitize_text_field( $this->pick( $row, array( 'location_key' ) ) ) : $location;
			if ( '' === $row_location || ! in_array( $row_source, array( 'places', 'outscraper', 'business', 'csv', 'manual' ), true ) ) {
				$skipped++;
				continue;
			}

			$review_data = array(
					'location_key'       => $row_location,
					'external_id'        => sanitize_text_field( $external ),
					'source'             => $row_source,
					'reviewer_name'      => sanitize_text_field( $name ),
					'reviewer_photo_url' => esc_url_raw( $this->pick( $row, array( 'reviewer_photo_url', 'author_image', 'photo_url' ) ) ),
					'reviewer_url'       => esc_url_raw( $this->pick( $row, array( 'reviewer_url', 'author_link' ) ) ),
					'rating'             => $rating,
					'review_text'        => sanitize_textarea_field( $text ),
					'create_time'        => $date,
					'update_time'        => $preserve_metadata ? $this->optional_mysql_date( $this->pick( $row, array( 'update_time' ) ) ) : $date,
					'reply_text'         => sanitize_textarea_field( $this->pick( $row, array( 'reply_text', 'owner_answer', 'review_reply' ) ) ),
					'reply_update_time'  => $preserve_metadata ? $this->optional_mysql_date( $this->pick( $row, array( 'reply_update_time' ) ) ) : null,
					'source_url'         => esc_url_raw( $this->pick( $row, array( 'source_url', 'review_link', 'google_maps_url' ) ) ),
					'last_seen_at'       => $preserve_metadata ? $this->mysql_date( $this->pick( $row, array( 'last_seen_at' ) ) ) : current_time( 'mysql', true ),
					'expires_at'         => $preserve_metadata ? $this->mysql_date( $this->pick( $row, array( 'expires_at' ) ) ) : gmdate( 'Y-m-d H:i:s', time() + ( 10 * YEAR_IN_SECONDS ) ),
					'_location_keys'     => $preserve_metadata ? array( $row_location ) : $duplicate_location_keys,
				);
			if ( $this->database->review_exists( $review_data ) ) {
				$skipped++;
				continue;
			}
			$saved = $this->database->upsert_review( $review_data );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$imported++;
		}

		return array( 'imported' => $imported, 'skipped' => $skipped );
	}

	private function pick( array $row, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $row[ $key ] ) && '' !== $row[ $key ] ) {
				return is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
			}
		}
		return '';
	}

	private function mysql_date( $value ) {
		$timestamp = $value ? strtotime( $value ) : time();
		return false === $timestamp ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private function optional_mysql_date( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return null;
		}
		$timestamp = strtotime( $value );
		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	private function normalize_header( $header ) {
		$header = strtolower( trim( (string) $header ) );
		$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
		return trim( $header, '_' );
	}
}
