<?php

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

require_once '/wordpress/wp-load.php';
require_once '/plugin/calico-google-reviews.php';

function cgr_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

CGR_Plugin::activate();

cgr_test_assert( shortcode_exists( 'calico_google_reviews' ), 'Shortcode was not registered.' );
cgr_test_assert( wp_next_scheduled( 'cgr_daily_cleanup' ), 'Daily cleanup was not scheduled.' );
cgr_test_assert( ! wp_next_scheduled( 'cgr_five_minute_collection' ), 'Legacy five-minute rolling collection is still scheduled.' );
cgr_test_assert( ! wp_next_scheduled( 'cgr_weekly_collection' ) && ! wp_next_scheduled( 'cgr_outscraper_sync' ), 'Legacy single-location schedules are still active.' );
cgr_test_assert( wp_next_scheduled( 'cgr_location_sync' ), 'Multi-location synchronization was not scheduled.' );
cgr_test_assert( 'hourly' === wp_get_schedule( 'cgr_location_sync' ), 'Multi-location due checks are not scheduled hourly.' );

$secret    = 'test-secret-' . wp_generate_password( 12, false );
$encrypted = CGR_Crypto::encrypt( $secret );
cgr_test_assert( ! is_wp_error( $encrypted ), 'Credential encryption failed.' );
cgr_test_assert( $secret === CGR_Crypto::decrypt( $encrypted ), 'Credential encryption did not round-trip.' );

$database = new CGR_Database();
$api      = new CGR_Google_API();
$locations = $api->get_locations();
cgr_test_assert( 1 === count( $locations ) && isset( $locations['primary'] ), 'Legacy settings were not migrated into a primary location.' );
$location = 'accounts/123/locations/456';
$review   = array(
	'location_key'       => $location,
	'external_id'        => $location . '/reviews/789',
	'source'             => 'places',
	'reviewer_name'      => 'Test Reviewer',
	'reviewer_photo_url' => 'https://example.com/avatar.jpg',
	'rating'             => 5,
	'review_text'        => 'A test review.',
	'create_time'        => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
	'update_time'        => gmdate( 'Y-m-d H:i:s' ),
	'reply_text'         => 'A preserved business response.',
	'reply_update_time'  => gmdate( 'Y-m-d H:i:s' ),
	'source_url'         => 'https://example.com/review/789',
	'last_seen_at'       => gmdate( 'Y-m-d H:i:s' ),
	'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
);

cgr_test_assert( ! is_wp_error( $database->upsert_review( $review ) ), 'Review insert failed.' );
$review['review_text'] = 'An updated test review.';
cgr_test_assert( ! is_wp_error( $database->upsert_review( $review ) ), 'Review update failed.' );
$saved = $database->get_reviews( $location, 10 );
cgr_test_assert( 1 === count( $saved ), 'Review upsert created a duplicate.' );
cgr_test_assert( 'An updated test review.' === $saved[0]['review_text'], 'Review upsert did not update content.' );
cgr_test_assert( 'places' === $saved[0]['source'], 'Review source was not stored.' );

$export_columns = array( 'location_key', 'external_id', 'source', 'reviewer_name', 'reviewer_photo_url', 'reviewer_url', 'rating', 'review_text', 'create_time', 'update_time', 'reply_text', 'reply_update_time', 'source_url', 'last_seen_at', 'expires_at' );
$export_rows    = $database->get_export_reviews( 500, 0 );
cgr_test_assert( 1 === count( $export_rows ), 'Export query did not return every stored review.' );
$export_path = wp_tempnam( 'cgr-export.csv' );
$export_file = fopen( $export_path, 'w' );
fputcsv( $export_file, $export_columns );
fputcsv( $export_file, array_values( $export_rows[0] ) );
fclose( $export_file );
$database->delete_location_reviews( $location );
$importer = new CGR_Importer( $database, $api );
$restored = $importer->import_csv( $export_path );
cgr_test_assert( ! is_wp_error( $restored ) && 1 === $restored['imported'], 'Exported review could not be re-imported.' );
$restored_again = $importer->import_csv( $export_path );
cgr_test_assert( ! is_wp_error( $restored_again ) && 1 === count( $database->get_reviews( $location, 10 ) ), 'Re-importing an export created a duplicate.' );
cgr_test_assert( 0 === $restored_again['imported'] && 1 === $restored_again['skipped'], 'Duplicate export rows were not skipped.' );
$restored_review = $database->get_reviews( $location, 10 );
cgr_test_assert( 'places' === $restored_review[0]['source'] && $review['external_id'] === $restored_review[0]['external_id'], 'Export import did not preserve source metadata and identifiers.' );
cgr_test_assert( $review['reply_text'] === $restored_review[0]['reply_text'] && $review['source_url'] === $restored_review[0]['source_url'], 'Export import did not preserve replies and source links.' );
unlink( $export_path );

$manual   = $importer->import_manual(
	array(
		'reviewer_name' => 'Manual Reviewer',
		'rating'        => 4,
		'review_text'   => 'Imported manually with enough words to verify the expandable review text control works correctly.',
		'review_date'   => '2026-08-17',
		'reply_text'    => 'Thank you for your feedback.',
	)
);
cgr_test_assert( ! is_wp_error( $manual ) && 1 === $manual['imported'], 'Manual review import failed.' );
cgr_test_assert( 1 === count( $database->get_display_reviews( 'manual', 10 ) ), 'Manual review was not queryable by source.' );
cgr_test_assert( 1 === count( $database->get_display_reviews( 'manual', 10, array( 4 ), true ) ), 'Rating and written-text filters excluded a valid review.' );
cgr_test_assert( 0 === count( $database->get_display_reviews( 'manual', 10, array( 5 ), true ) ), 'Rating filter included the wrong star rating.' );

$bulk_rows = array();
for ( $i = 1; $i <= 7; $i++ ) {
	$bulk_rows[] = array( 'external_id' => 'bulk-' . $i, 'reviewer_name' => 'Bulk Reviewer ' . $i, 'rating' => 4, 'review_text' => 'Pagination review number ' . $i, 'review_date' => '2026-08-' . sprintf( '%02d', $i ) );
}
$bulk = $importer->import_rows( $bulk_rows, 'manual' );
cgr_test_assert( ! is_wp_error( $bulk ) && 7 === $bulk['imported'], 'Bulk pagination fixtures could not be imported.' );
cgr_test_assert( 8 === $database->count_display_reviews( 'manual', array( 4 ), true ), 'Display review count did not include every filtered review.' );
$second_batch = $database->get_display_reviews( 'manual', 3, array( 4 ), true, 2 );
cgr_test_assert( 3 === count( $second_batch ), 'Offset review query did not return the requested batch.' );

$frontend = new CGR_Frontend( $database, $api );
$api->update_settings(
	array(
		'visible_ratings'     => array( 4 ),
		'require_review_text' => 'yes',
		'truncate_reviews'    => 'yes',
		'review_word_limit'   => 5,
		'location_title'      => 'Hidden Business',
		'average_rating'      => 4.8,
		'total_reviews'       => 25,
		'location_maps_uri'   => 'https://maps.google.com/example',
		'read_more_label'     => 'Global more',
		'place_id'            => 'ChIJReviewLinkTest',
		'review_request_url'  => '',
	)
);
cgr_test_assert( false !== strpos( $api->get_review_request_url(), 'placeid=ChIJReviewLinkTest' ), 'Place ID review-request link was not generated.' );
$legacy_alias_review = array( 'location_key' => 'places:ChIJReviewLinkTest', 'external_id' => 'legacy-alias-review', 'source' => 'manual', 'reviewer_name' => 'Legacy Alias', 'rating' => 4, 'review_text' => 'Existing review from before multi-location migration.', 'create_time' => '2026-08-01 12:00:00', 'update_time' => '2026-08-01 12:00:00', 'last_seen_at' => gmdate( 'Y-m-d H:i:s' ), 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
$database->upsert_review( $legacy_alias_review );
$alias_import = $importer->import_rows( array( array( 'external_id' => 'legacy-alias-review', 'reviewer_name' => 'Legacy Alias', 'rating' => 4, 'review_text' => 'Existing review from before multi-location migration.', 'review_date' => '2026-08-01' ) ), 'manual' );
cgr_test_assert( 0 === $alias_import['imported'] && 1 === $alias_import['skipped'], 'First multi-location sync would duplicate a legacy review.' );
$api->update_settings( array( 'review_request_url' => 'https://g.page/r/test/review' ) );
cgr_test_assert( 'https://g.page/r/test/review' === $api->get_review_request_url(), 'Official review-request link did not override the generated link.' );
$second_location = $api->create_location( 'North Location' );
cgr_test_assert( 'north-location' === $second_location && 2 === count( $api->get_locations() ), 'A second business location could not be created.' );
$api->update_location( $second_location, array( 'location_title' => 'North Business', 'place_id' => 'ChIJNorthLocation', 'review_request_url' => 'https://g.page/r/north/review' ) );
cgr_test_assert( 'https://g.page/r/north/review' === $api->get_review_request_url( $second_location ), 'Location-specific review-request link was not isolated.' );
$second_manual = $importer->import_manual( array( 'reviewer_name' => 'North Reviewer', 'rating' => 4, 'review_text' => 'A review belonging only to the north location.', 'review_date' => '2026-09-01' ), $second_location );
cgr_test_assert( ! is_wp_error( $second_manual ) && 1 === $second_manual['imported'], 'A review could not be assigned to the second location.' );
cgr_test_assert( 1 === $database->count_display_reviews( 'manual', array( 4 ), true, $api->get_location_keys( $second_location ) ), 'Location-scoped database filtering failed.' );
$second_markup = $frontend->render( array( 'location' => $second_location, 'source' => 'manual', 'limit' => 10 ) );
cgr_test_assert( false !== strpos( $second_markup, 'North Reviewer' ) && false === strpos( $second_markup, 'Manual Reviewer' ), 'Elementor/front-end location filtering mixed reviews.' );
$all_locations_markup = $frontend->render( array( 'location' => 'all', 'source' => 'manual', 'limit' => 100 ) );
cgr_test_assert( false !== strpos( $all_locations_markup, 'North Reviewer' ) && false !== strpos( $all_locations_markup, 'Manual Reviewer' ), 'Combined All locations feed did not include both locations.' );
$location_options = $frontend->location_options();
cgr_test_assert( isset( $location_options['all'], $location_options['primary'], $location_options['north-location'] ), 'Elementor location choices are incomplete.' );
$rating_summary = $database->get_rating_summary( $api->get_location_keys( 'primary' ) );
cgr_test_assert( $rating_summary['total'] >= 8 && isset( $rating_summary['average'], $rating_summary['five_star'] ), 'Location-aware rating summary data was not calculated.' );
$summary_markup = $frontend->render_summary(
	array(
		'location'                 => 'primary',
		'layout'                   => 'vertical',
		'total_reviews_label'      => 'All customer reviews',
		'google_reviews_link_text' => 'Read reviews on Google',
		'leave_review_link_text'   => 'Share your experience',
	)
);
cgr_test_assert( false !== strpos( $summary_markup, 'cgr-review-summary-vertical' ), 'Review Summary vertical layout was not rendered.' );
cgr_test_assert( false !== strpos( $summary_markup, '>25<' ) && false !== strpos( $summary_markup, '>4.8<' ), 'Saved aggregate review values were not rendered.' );
cgr_test_assert( false !== strpos( $summary_markup, 'All customer reviews' ) && false !== strpos( $summary_markup, 'cgr-summary-five-star-reviews' ), 'Review Summary statistic controls were not rendered.' );
cgr_test_assert( false !== strpos( $summary_markup, 'Read reviews on Google' ) && false !== strpos( $summary_markup, 'https://maps.google.com/example' ), 'Google reviews link or custom text was not rendered.' );
cgr_test_assert( false !== strpos( $summary_markup, 'Share your experience' ) && false !== strpos( $summary_markup, 'https://g.page/r/test/review' ), 'Leave-a-review link or custom text was not rendered.' );
$summary_hidden_markup = $frontend->render_summary( array( 'location' => 'primary', 'show_average_rating' => 'no', 'show_google_reviews_link' => 'no', 'show_leave_review_link' => 'no' ) );
cgr_test_assert( false === strpos( $summary_hidden_markup, 'cgr-summary-average-rating' ) && false === strpos( $summary_hidden_markup, '<a ' ), 'Disabled Review Summary elements were rendered.' );
$combined_summary_markup = $frontend->render_summary( array( 'location' => 'all' ) );
cgr_test_assert( false === strpos( $combined_summary_markup, '<a ' ), 'All locations rendered a location-specific link.' );
$combined_link_markup = $frontend->render_summary( array( 'location' => 'all', 'google_reviews_url' => array( 'url' => 'https://example.com/all-reviews' ), 'leave_review_url' => array( 'url' => 'https://example.com/leave-review' ) ) );
cgr_test_assert( false !== strpos( $combined_link_markup, 'https://example.com/all-reviews' ) && false !== strpos( $combined_link_markup, 'https://example.com/leave-review' ), 'Custom Review Summary URL overrides were not rendered.' );
$empty_columns_markup = $frontend->render( array( 'location' => 'primary', 'source' => 'manual', 'columns' => '', 'columns_tablet' => '', 'columns_mobile' => '' ) );
cgr_test_assert( false !== strpos( $empty_columns_markup, '--cgr-cols:3;--cgr-cols-tablet:2;--cgr-cols-mobile:1' ), 'Empty Elementor responsive values did not fall back to 3/2/1 columns.' );
$markup = $frontend->render(
	array(
		'source'             => 'manual',
		'template'           => 'quote',
		'columns'            => 4,
		'columns_tablet'     => 2,
		'columns_mobile'     => 1,
		'show_business_name' => 'no',
		'show_aggregate'     => 'no',
		'layout_mode'        => 'masonry',
		'custom_template'    => 'yes',
		'read_more_label'    => 'View more',
		'actual_date'        => 'yes',
		'date_format'        => 'm/d/Y',
		'elements'           => array(
			array( 'element' => 'name', 'enabled' => 'yes' ),
			array( 'element' => 'date', 'enabled' => 'yes' ),
			array( 'element' => 'review', 'enabled' => 'yes' ),
			array( 'element' => 'reply', 'enabled' => 'yes' ),
			array( 'element' => 'avatar', 'enabled' => 'no' ),
		),
	)
);
cgr_test_assert( false !== strpos( $markup, 'cgr-template-quote' ), 'Template class was not rendered.' );
cgr_test_assert( false !== strpos( $markup, '--cgr-cols:4' ), 'Responsive column value was not rendered.' );
cgr_test_assert( false !== strpos( $markup, 'cgr-layout-masonry' ), 'Masonry layout class was not rendered.' );
cgr_test_assert( false !== strpos( $markup, 'data-cgr-masonry="1"' ), 'Row-ordered masonry initialization data was not rendered.' );
cgr_test_assert( false !== strpos( $markup, 'cgr-read-more' ), 'Expandable Read more markup was not rendered.' );
cgr_test_assert( false !== strpos( $markup, 'View more' ), 'Custom translated Read more label was not rendered.' );
cgr_test_assert( false !== strpos( $markup, '/2026' ) && false === strpos( $markup, ' ago' ), 'Actual date format did not replace the relative date.' );
cgr_test_assert( false === strpos( $markup, 'cgr-summary' ), 'Disabled business and aggregate summary content was rendered.' );
cgr_test_assert( false === strpos( $markup, 'View on Google Maps' ), 'Removed summary Google Maps link was rendered.' );
cgr_test_assert( false === strpos( $markup, 'cgr-attribution' ) && false === strpos( $markup, '>Google Maps<' ), 'Removed Google Maps footer attribution was rendered.' );
cgr_test_assert( false === strpos( $markup, 'cgr-custom-avatar' ), 'Disabled custom template element was rendered.' );
cgr_test_assert( false !== strpos( $markup, 'cgr-custom-reply' ) && false !== strpos( $markup, 'Thank you for your feedback.' ), 'Business reply custom template element was not rendered.' );

$pagination_markup = $frontend->render( array( 'source' => 'manual', 'limit' => 2, 'loading_method' => 'pagination', 'pagination_position' => 'top', 'pagination_alignment' => 'right', 'previous_label' => 'Back', 'next_label' => 'Forward' ) );
cgr_test_assert( 2 === substr_count( $pagination_markup, '<article class="cgr-review' ), 'Pagination did not enforce the per-page review limit.' );
cgr_test_assert( false !== strpos( $pagination_markup, 'data-cgr-loading="pagination"' ) && false !== strpos( $pagination_markup, 'cgr-pagination' ), 'Pagination markup and client configuration are missing.' );
cgr_test_assert( false !== strpos( $pagination_markup, 'Back' ) && false !== strpos( $pagination_markup, 'Forward' ), 'Custom pagination labels were not rendered.' );
cgr_test_assert( false !== strpos( $pagination_markup, 'cgr-pagination-right' ), 'Pagination alignment was not rendered.' );
$load_more_markup = $frontend->render( array( 'source' => 'manual', 'limit' => 2, 'loading_method' => 'load_more', 'load_more_count' => 3, 'trigger_selector' => '#more-reviews' ) );
cgr_test_assert( false !== strpos( $load_more_markup, 'data-cgr-loading="load_more"' ) && false !== strpos( $load_more_markup, '#more-reviews' ), 'External Load More configuration is missing.' );
cgr_test_assert( false === strpos( $load_more_markup, 'cgr-pagination' ), 'Load More mode rendered pagination controls.' );
cgr_test_assert( has_action( 'wp_ajax_cgr_load_reviews' ) && has_action( 'wp_ajax_nopriv_cgr_load_reviews' ), 'Public review-loading AJAX actions are not registered.' );

$carousel_rows = array();
for ( $i = 1; $i <= 25; $i++ ) {
	$carousel_rows[] = array( 'external_id' => 'carousel-' . $i, 'reviewer_name' => 'Carousel Reviewer ' . $i, 'rating' => 4, 'review_text' => 'Carousel review number ' . $i, 'review_date' => '2025-01-' . sprintf( '%02d', $i ) );
}
$carousel_import = $importer->import_rows( $carousel_rows, 'manual' );
cgr_test_assert( ! is_wp_error( $carousel_import ) && 25 === $carousel_import['imported'], 'Carousel fixtures could not be imported.' );
$carousel_markup = $frontend->render( array( 'source' => 'manual', 'limit' => 100, 'display_layout' => 'carousel', 'carousel_dots' => 'top', 'carousel_arrows' => 'yes', 'carousel_autoplay' => 'yes', 'carousel_speed' => 7, 'carousel_scroll' => 2, 'loading_method' => 'pagination', 'columns' => 4, 'columns_tablet' => 2, 'columns_mobile' => 1 ) );
cgr_test_assert( 30 === substr_count( $carousel_markup, '<article class="cgr-review' ), 'Carousel did not enforce its 30-review maximum.' );
cgr_test_assert( false !== strpos( $carousel_markup, 'cgr-layout-carousel' ) && false !== strpos( $carousel_markup, 'data-cgr-carousel="1"' ), 'Carousel class or initialization data is missing.' );
cgr_test_assert( false !== strpos( $carousel_markup, 'cgr-carousel-viewport' ) && false !== strpos( $carousel_markup, 'cgr-carousel-dots-top' ) && false !== strpos( $carousel_markup, 'cgr-carousel-previous' ) && false !== strpos( $carousel_markup, 'cgr-carousel-next' ), 'Carousel track or navigation markup is missing.' );
cgr_test_assert( false === strpos( $carousel_markup, 'cgr-pagination' ) && false === strpos( $carousel_markup, 'data-cgr-loading=' ), 'Carousel mode did not disable conflicting pagination.' );
cgr_test_assert( false !== strpos( $carousel_markup, 'data-cgr-carousel-config=' ) && false !== strpos( html_entity_decode( $carousel_markup ), '"scroll":2' ), 'Carousel autoplay or slides-per-scroll configuration is missing.' );

$duplicate_one = array( 'location_key' => 'duplicate:test', 'external_id' => 'duplicate-a', 'source' => 'manual', 'reviewer_name' => 'Duplicate Person', 'rating' => 5, 'review_text' => 'Exactly the same duplicate body.', 'create_time' => '2026-01-02 12:00:00', 'update_time' => '2026-01-02 12:00:00', 'reply_text' => '', 'last_seen_at' => gmdate( 'Y-m-d H:i:s' ), 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
$duplicate_two = array_merge( $duplicate_one, array( 'external_id' => 'duplicate-b', 'reply_text' => 'The more complete copy.', 'update_time' => '2026-01-03 12:00:00' ) );
$database->upsert_review( $duplicate_one );
$database->upsert_review( $duplicate_two );
$duplicate_report = $database->duplicate_report();
cgr_test_assert( $duplicate_report['duplicates'] >= 1, 'Duplicate review scan did not detect a fallback match.' );
$duplicate_cleanup = $database->remove_duplicates();
cgr_test_assert( $duplicate_cleanup['removed'] >= 1, 'Duplicate cleanup did not remove the less complete copy.' );
$duplicate_saved = $database->get_reviews( 'duplicate:test', 10 );
cgr_test_assert( 1 === count( $duplicate_saved ) && 'The more complete copy.' === $duplicate_saved[0]['reply_text'], 'Duplicate cleanup did not keep the most complete copy.' );

$encrypted_outscraper_key = CGR_Crypto::encrypt( 'test-outscraper-key' );
$api->update_settings( array( 'outscraper_api_key_enc' => $encrypted_outscraper_key, 'outscraper_query' => 'https://example.com/not-supported' ) );
$unsupported_url = $api->fetch_outscraper_reviews();
cgr_test_assert( is_wp_error( $unsupported_url ) && 'cgr_outscraper_url_unsupported' === $unsupported_url->get_error_code(), 'Unsupported URL was not rejected before an API request.' );

$frontend_css = file_get_contents( '/plugin/assets/css/reviews.css' );
cgr_test_assert( false !== strpos( $frontend_css, '.cgr-source-link:hover { text-decoration: none; }' ), 'Review links still add an underline on hover.' );
cgr_test_assert( false !== strpos( $frontend_css, '.cgr-read-more summary:hover { text-decoration: none; }' ), 'Read more still adds an underline on hover.' );

$admin_source = file_get_contents( '/plugin/includes/class-cgr-admin.php' );
cgr_test_assert( false !== strpos( $admin_source, "__( 'Manual Import'" ), 'Manual Import tab label is missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'cgr-instructions-places' ) && false !== strpos( $admin_source, 'cgr-instructions-outscraper' ), 'Linked source instruction anchors are missing.' );
cgr_test_assert( false === strpos( $admin_source, 'cgr-instructions-business' ) && false === strpos( $admin_source, 'Business Profile OAuth' ), 'Removed Business Profile connection is still present.' );
cgr_test_assert( false === strpos( $admin_source, 'exact business search' ) && false === strpos( $admin_source, 'maps.app.goo.gl' ) && false === strpos( $admin_source, 'Outscraper Google Maps Reviews API documentation' ), 'Removed Outscraper guidance is still present.' );
cgr_test_assert( false !== strpos( $admin_source, 'Limit 5 initial reviews, good for testing environments, no backlog review pulling.' ), 'Google Places API source description is missing.' );
cgr_test_assert( false !== strpos( $admin_source, '500 free credits per month available (pull 1 review = 1 credit), so most accounts will pay nothing.' ), 'Outscraper API credit description is missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'Set to weekly to avoid charges and stay under the 500-credit limit.' ), 'Outscraper API credit warning is missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'Google Places API' ) && false !== strpos( $admin_source, 'Outscraper API' ), 'Updated API names are missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'cgr-review-request-link' ) && false !== strpos( $admin_source, 'Copy Link' ) && false !== strpos( $admin_source, 'Test Link' ), 'Review-request link controls are missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'Store newly exposed reviews every seven days' ), 'Seven-day collection label is missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'Export all reviews' ) && has_action( 'admin_post_cgr_export_reviews' ), 'Export All Reviews is not registered.' );
cgr_test_assert( false !== strpos( $admin_source, 'Hourly — high API usage and potential cost' ), 'Outscraper schedule usage warnings are missing.' );
cgr_test_assert( false !== strpos( $admin_source, 'cgr_add_location' ) && false !== strpos( $admin_source, 'cgr_delete_location' ) && false !== strpos( $admin_source, 'Multiple business locations' ), 'Multi-location administration or instructions are missing.' );

$widget_source = file_get_contents( '/plugin/includes/class-cgr-elementor-widget.php' );
cgr_test_assert( false !== strpos( $widget_source, "'location'" ) && false !== strpos( $widget_source, 'All locations' ), 'Elementor location selector is missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'default' => 3, 'desktop_default' => 3" ) && false !== strpos( $widget_source, "! empty( \$settings['columns'] )" ), 'Elementor responsive column defaults or render fallback are missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'reply' => __( 'Response from Business'" ), 'Business reply custom template option is missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'reply_background'" ) && false !== strpos( $widget_source, "'reply_radius'" ), 'Business reply container style controls are missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'loading_method'" ) && false !== strpos( $widget_source, "'pagination_position'" ) && false !== strpos( $widget_source, "'pagination_alignment'" ) && false !== strpos( $widget_source, "'load_more_count'" ) && false !== strpos( $widget_source, "'trigger_selector'" ), 'Elementor review-loading controls are missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'pagination_active_background'" ) && false !== strpos( $widget_source, "'pagination_typography'" ), 'Elementor pagination style controls are missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'display_layout'" ) && false !== strpos( $widget_source, "'carousel_dots'" ) && false !== strpos( $widget_source, "'carousel_arrows'" ), 'Elementor carousel display controls are missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'carousel_autoplay'" ) && false !== strpos( $widget_source, "'carousel_speed'" ) && false !== strpos( $widget_source, "'carousel_scroll'" ), 'Elementor carousel motion controls are missing.' );
cgr_test_assert( false !== strpos( $widget_source, "'carousel_dot_active_color'" ) && false !== strpos( $widget_source, "'carousel_arrow_active_background'" ) && false !== strpos( $widget_source, "'carousel_previous_x'" ) && false !== strpos( $widget_source, "'carousel_next_y'" ), 'Elementor carousel navigation state or positioning controls are missing.' );

$frontend_js = file_get_contents( '/plugin/assets/js/reviews.js' );
cgr_test_assert( false !== strpos( $frontend_js, "body.append( 'action', 'cgr_load_reviews' )" ) && false !== strpos( $frontend_js, "insertAdjacentHTML( 'beforeend'" ), 'Frontend loading script is missing its request or append behavior.' );
cgr_test_assert( false !== strpos( $frontend_js, 'function initCarousel' ) && false !== strpos( $frontend_js, "viewport.addEventListener( 'pointerdown'" ) && false !== strpos( $frontend_js, "window.matchMedia( '(max-width: 600px)'" ), 'Responsive carousel dragging behavior is missing.' );
cgr_test_assert( false !== strpos( $frontend_js, 'restartAutoplay' ) && false !== strpos( $frontend_js, 'current + scrollCount' ) && false !== strpos( $frontend_js, "event.target.closest( 'a, button, summary, details" ), 'Carousel autoplay, scroll count, or interactive-element protection is missing.' );
cgr_test_assert( false !== strpos( $frontend_js, 'function layoutMasonry' ) && false !== strpos( $frontend_js, "card.style.gridRowEnd = 'span ' + span" ) && false !== strpos( $frontend_js, "widget.addEventListener( 'toggle', schedule, true )" ), 'Row-ordered responsive masonry behavior is missing.' );
cgr_test_assert( false !== strpos( $frontend_css, 'grid-auto-rows: 1px' ) && false === strpos( $frontend_css, 'column-count: var(--cgr-cols' ), 'Masonry CSS still uses top-to-bottom columns.' );
cgr_test_assert( false !== strpos( $admin_source, 'cgr_delete_all_reviews' ) && false !== strpos( $admin_source, 'cgr_remove_duplicates' ) && false !== strpos( $admin_source, 'cgr-export-reviews' ), 'Review cleanup tools or export-first links are missing.' );

$database->delete_location_reviews( $location );
cgr_test_assert( 0 === count( $database->get_reviews( $location, 10 ) ), 'Review deletion failed.' );

echo "Calico Google Reviews smoke test passed.\n";
