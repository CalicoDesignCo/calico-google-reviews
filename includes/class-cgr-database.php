<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Database {

	public function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cgr_reviews';
	}

	public function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = $this->table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			location_key varchar(255) NOT NULL,
			external_id varchar(255) NOT NULL,
			source varchar(32) NOT NULL DEFAULT 'manual',
			reviewer_name varchar(255) NOT NULL DEFAULT '',
			reviewer_photo_url text NULL,
			reviewer_url text NULL,
			rating tinyint(1) unsigned NOT NULL DEFAULT 0,
			review_text longtext NULL,
			create_time datetime NULL,
			update_time datetime NULL,
			reply_text longtext NULL,
			reply_update_time datetime NULL,
			source_url text NULL,
			last_seen_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY location_review (location_key(120), external_id(120)),
			KEY location_expiry (location_key(120), expires_at),
			KEY update_time (update_time),
			KEY source (source)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'cgr_db_version', CGR_VERSION, false );
	}

	public function upsert_review( array $review ) {
		global $wpdb;

		$defaults = array(
			'location_key'     => '',
			'external_id'      => '',
			'source'           => 'manual',
			'reviewer_name'    => '',
			'reviewer_photo_url' => '',
			'reviewer_url'     => '',
			'rating'           => 0,
			'review_text'      => '',
			'create_time'      => null,
			'update_time'      => null,
			'reply_text'       => '',
			'reply_update_time' => null,
			'source_url'       => '',
			'last_seen_at'     => current_time( 'mysql', true ),
			'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + ( 29 * DAY_IN_SECONDS ) ),
		);
		$data     = wp_parse_args( $review, $defaults );

		if ( '' === $data['location_key'] || '' === $data['external_id'] ) {
			return new WP_Error( 'cgr_review_identifier_missing', __( 'Google returned a review without a stable identifier.', 'calico-google-reviews' ) );
		}

		$sql = "INSERT INTO {$this->table_name()}
			(location_key, external_id, source, reviewer_name, reviewer_photo_url, reviewer_url, rating, review_text, create_time, update_time, reply_text, reply_update_time, source_url, last_seen_at, expires_at)
			VALUES (%s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
			source = VALUES(source), reviewer_name = VALUES(reviewer_name), reviewer_photo_url = VALUES(reviewer_photo_url), reviewer_url = VALUES(reviewer_url), rating = VALUES(rating),
			review_text = VALUES(review_text), create_time = VALUES(create_time), update_time = VALUES(update_time),
			reply_text = VALUES(reply_text), reply_update_time = VALUES(reply_update_time), source_url = VALUES(source_url),
			last_seen_at = VALUES(last_seen_at), expires_at = VALUES(expires_at)";

		$result = $wpdb->query(
			$wpdb->prepare(
				$sql,
				$data['location_key'],
				$data['external_id'],
				sanitize_key( $data['source'] ),
				$data['reviewer_name'],
				$data['reviewer_photo_url'],
				$data['reviewer_url'],
				(int) $data['rating'],
				$data['review_text'],
				$data['create_time'],
				$data['update_time'],
				$data['reply_text'],
				$data['reply_update_time'],
				$data['source_url'],
				$data['last_seen_at'],
				$data['expires_at']
			)
		);

		return false === $result ? new WP_Error( 'cgr_database_error', $wpdb->last_error ) : $result;
	}

	public function review_exists( array $review ) {
		global $wpdb;
		$external_id = isset( $review['external_id'] ) ? trim( (string) $review['external_id'] ) : '';
		$location_key = isset( $review['location_key'] ) ? trim( (string) $review['location_key'] ) : '';
		$location_keys = isset( $review['_location_keys'] ) && is_array( $review['_location_keys'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $review['_location_keys'] ) ) ) : array( $location_key );
		if ( ! in_array( $location_key, $location_keys, true ) ) { $location_keys[] = $location_key; }
		$location_sql = implode( ',', array_fill( 0, count( $location_keys ), '%s' ) );
		if ( '' !== $external_id ) {
			$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_name()} WHERE location_key IN ({$location_sql}) AND external_id = %s LIMIT 1", array_merge( $location_keys, array( $external_id ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $found ) {
				return true;
			}
		}

		$name   = self::normalize_duplicate_value( isset( $review['reviewer_name'] ) ? $review['reviewer_name'] : '' );
		$text   = self::normalize_duplicate_value( isset( $review['review_text'] ) ? $review['review_text'] : '' );
		$rating = isset( $review['rating'] ) ? absint( $review['rating'] ) : 0;
		$date   = self::duplicate_date( isset( $review['create_time'] ) ? $review['create_time'] : '' );
		if ( '' === $name || ! $rating ) {
			return false;
		}

		$candidates = $wpdb->get_results(
			$wpdb->prepare( "SELECT reviewer_name, review_text, create_time FROM {$this->table_name()} WHERE location_key IN ({$location_sql}) AND rating = %d", array_merge( $location_keys, array( $rating ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
		foreach ( $candidates as $candidate ) {
			if ( $name === self::normalize_duplicate_value( $candidate['reviewer_name'] ) && $text === self::normalize_duplicate_value( $candidate['review_text'] ) && $date === self::duplicate_date( $candidate['create_time'] ) ) {
				return true;
			}
		}
		return false;
	}

	public function get_reviews( $location_key, $limit = 10 ) {
		global $wpdb;
		$limit = max( 1, min( 50, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE location_key = %s AND expires_at > %s ORDER BY update_time DESC, id DESC LIMIT %d",
				$location_key,
				current_time( 'mysql', true ),
				$limit
			),
			ARRAY_A
		);
	}

	public function get_display_reviews( $source = 'all', $limit = 10, array $ratings = array(), $require_text = false, $offset = 0, array $location_keys = array() ) {
		global $wpdb;
		$limit   = max( 1, min( 100, absint( $limit ) ) );
		$offset  = max( 0, absint( $offset ) );
		$where   = array( 'expires_at > %s' );
		$values  = array( current_time( 'mysql', true ) );
		$ratings = array_values( array_intersect( array( 1, 2, 3, 4, 5 ), array_map( 'absint', $ratings ) ) );

		if ( in_array( $source, array( 'places', 'outscraper', 'business', 'csv', 'manual' ), true ) ) {
			$where[]  = 'source = %s';
			$values[] = $source;
		}
		if ( ! empty( $ratings ) ) {
			$where[] = 'rating IN (' . implode( ',', array_fill( 0, count( $ratings ), '%d' ) ) . ')';
			$values  = array_merge( $values, $ratings );
		}
		if ( $require_text ) {
			$where[] = "TRIM(COALESCE(review_text, '')) <> ''";
		}
		$location_keys = array_values( array_filter( array_map( 'sanitize_text_field', $location_keys ) ) );
		if ( $location_keys ) {
			$where[] = 'location_key IN (' . implode( ',', array_fill( 0, count( $location_keys ), '%s' ) ) . ')';
			$values = array_merge( $values, $location_keys );
		}

		$values[] = $limit;
		$values[] = $offset;
		$sql      = "SELECT * FROM {$this->table_name()} WHERE " . implode( ' AND ', $where ) . ' ORDER BY COALESCE(update_time, create_time) DESC, id DESC LIMIT %d OFFSET %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function count_display_reviews( $source = 'all', array $ratings = array(), $require_text = false, array $location_keys = array() ) {
		global $wpdb;
		$where   = array( 'expires_at > %s' );
		$values  = array( current_time( 'mysql', true ) );
		$ratings = array_values( array_intersect( array( 1, 2, 3, 4, 5 ), array_map( 'absint', $ratings ) ) );

		if ( in_array( $source, array( 'places', 'outscraper', 'business', 'csv', 'manual' ), true ) ) {
			$where[]  = 'source = %s';
			$values[] = $source;
		}
		if ( ! empty( $ratings ) ) {
			$where[] = 'rating IN (' . implode( ',', array_fill( 0, count( $ratings ), '%d' ) ) . ')';
			$values  = array_merge( $values, $ratings );
		}
		if ( $require_text ) {
			$where[] = "TRIM(COALESCE(review_text, '')) <> ''";
		}
		$location_keys = array_values( array_filter( array_map( 'sanitize_text_field', $location_keys ) ) );
		if ( $location_keys ) {
			$where[] = 'location_key IN (' . implode( ',', array_fill( 0, count( $location_keys ), '%s' ) ) . ')';
			$values = array_merge( $values, $location_keys );
		}

		$sql = "SELECT COUNT(*) FROM {$this->table_name()} WHERE " . implode( ' AND ', $where );
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function get_rating_summary( array $location_keys = array() ) {
		global $wpdb;
		$where  = array( 'expires_at > %s' );
		$values = array( current_time( 'mysql', true ) );
		$location_keys = array_values( array_filter( array_map( 'sanitize_text_field', $location_keys ) ) );
		if ( $location_keys ) {
			$where[] = 'location_key IN (' . implode( ',', array_fill( 0, count( $location_keys ), '%s' ) ) . ')';
			$values = array_merge( $values, $location_keys );
		}
		$sql = "SELECT COUNT(*) AS total, AVG(rating) AS average, SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS five_star FROM {$this->table_name()} WHERE " . implode( ' AND ', $where );
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array(
			'total'     => isset( $row['total'] ) ? (int) $row['total'] : 0,
			'average'   => isset( $row['average'] ) && null !== $row['average'] ? (float) $row['average'] : '',
			'five_star' => isset( $row['five_star'] ) ? (int) $row['five_star'] : 0,
		);
	}

	public function query_reviews( array $args = array() ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array( 'search' => '', 'source' => '', 'rating' => 0, 'location_keys' => array(), 'page' => 1, 'per_page' => 20 )
		);
		$where  = array( '1=1' );
		$values = array();

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(reviewer_name LIKE %s OR review_text LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}
		if ( '' !== $args['source'] ) {
			$where[]  = 'source = %s';
			$values[] = sanitize_key( $args['source'] );
		}
		if ( $args['rating'] ) {
			$where[]  = 'rating = %d';
			$values[] = max( 1, min( 5, absint( $args['rating'] ) ) );
		}
		$location_keys = is_array( $args['location_keys'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $args['location_keys'] ) ) ) : array();
		if ( $location_keys ) {
			$where[] = 'location_key IN (' . implode( ',', array_fill( 0, count( $location_keys ), '%s' ) ) . ')';
			$values = array_merge( $values, $location_keys );
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$this->table_name()} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $values ? $wpdb->prepare( $count_sql, $values ) : $count_sql );
		$per_page  = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset    = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;
		$list_sql  = "SELECT * FROM {$this->table_name()} WHERE {$where_sql} ORDER BY COALESCE(update_time, create_time) DESC, id DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $values, array( $per_page, $offset ) );

		return array(
			'items' => $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ), ARRAY_A ),
			'total' => $total,
		);
	}

	public function delete_reviews( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name()} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function delete_all_reviews() {
		global $wpdb;
		return $wpdb->query( "DELETE FROM {$this->table_name()}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public function duplicate_report() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$this->table_name()} ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$groups = array();
		foreach ( $rows as $row ) {
			$external = trim( (string) $row['external_id'] );
			$fingerprint = self::duplicate_fingerprint( $row );
			$scope = (string) $row['location_key'] . ':';
			$key = '' !== $external ? $scope . 'external:' . $external : $scope . 'review:' . $fingerprint;
			$groups[ $key ][] = $row;
			if ( '' !== $external && '' !== $fingerprint ) {
				$groups[ $scope . 'review:' . $fingerprint ][] = $row;
			}
		}

		$remove = array();
		$duplicate_groups = 0;
		foreach ( $groups as $group ) {
			$unique = array();
			foreach ( $group as $row ) { $unique[ (int) $row['id'] ] = $row; }
			if ( count( $unique ) < 2 ) { continue; }
			$duplicate_groups++;
			usort( $unique, array( __CLASS__, 'compare_duplicate_records' ) );
			array_shift( $unique );
			foreach ( $unique as $row ) { $remove[ (int) $row['id'] ] = true; }
		}
		return array( 'groups' => $duplicate_groups, 'duplicates' => count( $remove ), 'ids' => array_keys( $remove ) );
	}

	public function remove_duplicates() {
		$report = $this->duplicate_report();
		$report['removed'] = empty( $report['ids'] ) ? 0 : (int) $this->delete_reviews( $report['ids'] );
		return $report;
	}

	private static function duplicate_fingerprint( array $review ) {
		$name = self::normalize_duplicate_value( isset( $review['reviewer_name'] ) ? $review['reviewer_name'] : '' );
		if ( '' === $name ) { return ''; }
		return hash( 'sha256', $name . '|' . absint( $review['rating'] ) . '|' . self::normalize_duplicate_value( isset( $review['review_text'] ) ? $review['review_text'] : '' ) . '|' . self::duplicate_date( isset( $review['create_time'] ) ? $review['create_time'] : '' ) );
	}

	private static function normalize_duplicate_value( $value ) {
		$value = strtolower( trim( wp_strip_all_tags( (string) $value ) ) );
		return preg_replace( '/\s+/u', ' ', $value );
	}

	private static function duplicate_date( $value ) {
		$timestamp = $value ? strtotime( $value ) : false;
		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	private static function compare_duplicate_records( $left, $right ) {
		$fields = array( 'review_text', 'reply_text', 'reviewer_photo_url', 'reviewer_url', 'source_url' );
		$left_score = 0;
		$right_score = 0;
		foreach ( $fields as $field ) {
			$left_score += '' !== trim( (string) $left[ $field ] ) ? 1 : 0;
			$right_score += '' !== trim( (string) $right[ $field ] ) ? 1 : 0;
		}
		$left_score += strlen( (string) $left['review_text'] ) + strlen( (string) $left['reply_text'] );
		$right_score += strlen( (string) $right['review_text'] ) + strlen( (string) $right['reply_text'] );
		if ( $left_score !== $right_score ) { return $right_score - $left_score; }
		$left_time = strtotime( $left['update_time'] ? $left['update_time'] : $left['create_time'] );
		$right_time = strtotime( $right['update_time'] ? $right['update_time'] : $right['create_time'] );
		if ( $left_time !== $right_time ) { return $right_time - $left_time; }
		return (int) $right['id'] - (int) $left['id'];
	}

	public function get_export_reviews( $limit = 500, $offset = 0 ) {
		global $wpdb;
		$limit  = max( 1, min( 5000, absint( $limit ) ) );
		$offset = max( 0, absint( $offset ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_key, external_id, source, reviewer_name, reviewer_photo_url, reviewer_url, rating, review_text, create_time, update_time, reply_text, reply_update_time, source_url, last_seen_at, expires_at FROM {$this->table_name()} ORDER BY id ASC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	public function delete_missing_reviews( $location_key, $sync_started_at ) {
		global $wpdb;
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name()} WHERE location_key = %s AND last_seen_at < %s",
				$location_key,
				$sync_started_at
			)
		);
	}

	public function delete_location_reviews( $location_key ) {
		global $wpdb;
		return $wpdb->delete( $this->table_name(), array( 'location_key' => $location_key ), array( '%s' ) );
	}

	public function delete_expired_reviews() {
		global $wpdb;
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_name()} WHERE expires_at <= %s",
				current_time( 'mysql', true )
			)
		);
	}
}
