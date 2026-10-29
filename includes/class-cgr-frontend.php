<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Frontend {
	private $database;
	private $api;

	public function __construct( CGR_Database $database, CGR_Google_API $api ) {
		$this->database = $database;
		$this->api      = $api;
		add_shortcode( 'calico_google_reviews', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_ajax_cgr_load_reviews', array( $this, 'load_reviews' ) );
		add_action( 'wp_ajax_nopriv_cgr_load_reviews', array( $this, 'load_reviews' ) );
	}

	public function register_assets() {
		wp_register_style( 'cgr-reviews', CGR_URL . 'assets/css/reviews.css', array(), CGR_VERSION );
		wp_register_script( 'cgr-reviews', CGR_URL . 'assets/js/reviews.js', array(), CGR_VERSION, true );
		wp_localize_script( 'cgr-reviews', 'cgrReviews', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'cgr_load_reviews' ), 'pageLoaded' => __( 'Review page %d loaded.', 'calico-google-reviews' ) ) );
	}
	public function register_elementor_category( $manager ) { $manager->add_category( 'calico', array( 'title' => __( 'Calico', 'calico-google-reviews' ), 'icon' => 'fa fa-plug' ) ); }

	public function register_elementor_widget( $manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) { return; }
		require_once CGR_PATH . 'includes/class-cgr-elementor-widget.php';
		require_once CGR_PATH . 'includes/class-cgr-elementor-summary-widget.php';
		CGR_Elementor_Widget::set_frontend( $this );
		CGR_Elementor_Summary_Widget::set_frontend( $this );
		$manager->register( new CGR_Elementor_Widget() );
		$manager->register( new CGR_Elementor_Summary_Widget() );
	}
	public function location_options() {
		$options = array( 'all' => __( 'All locations', 'calico-google-reviews' ) );
		foreach ( $this->api->get_locations() as $id => $location ) { $options[ $id ] = $location['location_name']; }
		return $options;
	}
	public function default_location_id() { return $this->api->get_default_location_id(); }

	public function render_shortcode( $attributes ) { return $this->render( $attributes ); }

	public function render_summary( $attributes = array() ) {
		$defaults = array(
			'location'                  => $this->api->get_default_location_id(),
			'layout'                    => 'horizontal',
			'show_total_reviews'        => 'yes',
			'total_reviews_label'       => __( 'Total reviews', 'calico-google-reviews' ),
			'show_average_rating'       => 'yes',
			'average_rating_label'      => __( 'Average rating', 'calico-google-reviews' ),
			'show_five_star_reviews'    => 'yes',
			'five_star_reviews_label'   => __( '5-star reviews', 'calico-google-reviews' ),
			'show_google_reviews_link'  => 'yes',
			'google_reviews_link_text'  => __( 'View our Google reviews', 'calico-google-reviews' ),
			'google_reviews_url'        => '',
			'show_leave_review_link'    => 'yes',
			'leave_review_link_text'    => __( 'Leave a review', 'calico-google-reviews' ),
			'leave_review_url'          => '',
		);
		$attributes = wp_parse_args( $attributes, $defaults );
		$locations  = $this->api->get_locations();
		$location   = sanitize_key( $attributes['location'] );
		if ( 'all' !== $location && ! isset( $locations[ $location ] ) ) { $location = $this->api->get_default_location_id(); }

		$location_keys = $this->api->get_location_keys( $location );
		$stored_summary = $this->database->get_rating_summary( $location_keys );
		$stored_total  = $stored_summary['total'];
		$five_star     = $stored_summary['five_star'];
		$total         = 0;
		$average       = '';
		$google_url    = '';
		$review_url    = '';

		if ( 'all' === $location ) {
			$weighted_rating = 0.0;
			$weighted_count  = 0;
			foreach ( $locations as $location_settings ) {
				$location_total = '' !== $location_settings['total_reviews'] ? absint( $location_settings['total_reviews'] ) : 0;
				$total += $location_total;
				if ( $location_total && '' !== $location_settings['average_rating'] ) {
					$weighted_rating += (float) $location_settings['average_rating'] * $location_total;
					$weighted_count  += $location_total;
				}
			}
			if ( $weighted_count ) { $average = $weighted_rating / $weighted_count; }
			if ( ! $total ) { $total = $stored_total; }
			if ( '' === $average ) { $average = $stored_summary['average']; }
		} else {
			$location_settings = $this->api->get_location_settings( $location );
			$total      = '' !== $location_settings['total_reviews'] ? absint( $location_settings['total_reviews'] ) : $stored_total;
			$average    = '' !== $location_settings['average_rating'] ? (float) $location_settings['average_rating'] : $stored_summary['average'];
			$google_url = ! empty( $location_settings['location_maps_uri'] ) ? esc_url( $location_settings['location_maps_uri'] ) : '';
			$review_url = esc_url( $this->api->get_review_request_url( $location ) );
		}
		$google_override = is_array( $attributes['google_reviews_url'] ) ? ( isset( $attributes['google_reviews_url']['url'] ) ? $attributes['google_reviews_url']['url'] : '' ) : $attributes['google_reviews_url'];
		$review_override = is_array( $attributes['leave_review_url'] ) ? ( isset( $attributes['leave_review_url']['url'] ) ? $attributes['leave_review_url']['url'] : '' ) : $attributes['leave_review_url'];
		if ( $google_override ) { $google_url = esc_url( $google_override ); }
		if ( $review_override ) { $review_url = esc_url( $review_override ); }

		$layout = 'vertical' === $attributes['layout'] ? 'vertical' : 'horizontal';
		ob_start();
		?>
		<div class="cgr-review-summary-widget cgr-review-summary-<?php echo esc_attr( $layout ); ?>">
			<?php if ( 'yes' === $attributes['show_total_reviews'] ) : ?>
				<div class="cgr-summary-item cgr-summary-total-reviews"><span class="cgr-summary-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span><span class="cgr-summary-label"><?php echo esc_html( $attributes['total_reviews_label'] ); ?></span></div>
			<?php endif; ?>
			<?php if ( 'yes' === $attributes['show_average_rating'] && '' !== $average ) : ?>
				<div class="cgr-summary-item cgr-summary-average-rating"><span class="cgr-summary-value"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?></span><span class="cgr-summary-label"><?php echo esc_html( $attributes['average_rating_label'] ); ?></span></div>
			<?php endif; ?>
			<?php if ( 'yes' === $attributes['show_five_star_reviews'] ) : ?>
				<div class="cgr-summary-item cgr-summary-five-star-reviews"><span class="cgr-summary-value"><?php echo esc_html( number_format_i18n( $five_star ) ); ?></span><span class="cgr-summary-label"><?php echo esc_html( $attributes['five_star_reviews_label'] ); ?></span></div>
			<?php endif; ?>
			<?php if ( 'yes' === $attributes['show_google_reviews_link'] && $google_url && '' !== trim( $attributes['google_reviews_link_text'] ) ) : ?>
				<a class="cgr-summary-link cgr-summary-google-link" href="<?php echo esc_url( $google_url ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $attributes['google_reviews_link_text'] ); ?></a>
			<?php endif; ?>
			<?php if ( 'yes' === $attributes['show_leave_review_link'] && $review_url && '' !== trim( $attributes['leave_review_link_text'] ) ) : ?>
				<a class="cgr-summary-link cgr-summary-leave-review-link" href="<?php echo esc_url( $review_url ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $attributes['leave_review_link_text'] ); ?></a>
			<?php endif; ?>
		</div>
		<?php
		wp_enqueue_style( 'cgr-reviews' );
		return ob_get_clean();
	}

	public function render( $attributes = array() ) {
		$settings = $this->api->get_settings();
		$default_location = $this->api->get_default_location_id();
		$default_location_settings = $this->api->get_location_settings( $default_location );
		$defaults = array(
			'location'           => $default_location,
			'source'             => $this->default_source( $default_location_settings['mode'] ),
			'limit'              => 6,
			'template'           => 'classic',
			'columns'            => 3,
			'columns_tablet'     => 2,
			'columns_mobile'     => 1,
			'show_summary'       => 'yes',
			'show_business_name' => 'yes',
			'show_aggregate'     => 'yes',
			'custom_template'    => 'no',
			'elements'           => array(),
			'layout_mode'        => 'equal',
			'read_more_label'    => '',
			'actual_date'        => 'no',
			'date_format'        => 'm/d/Y',
			'loading_method'     => 'none',
			'pagination_position' => 'bottom',
			'pagination_alignment' => 'center',
			'load_more_count'    => 10,
			'trigger_selector'   => '#loadmore',
			'previous_label'     => __( 'Previous', 'calico-google-reviews' ),
			'next_label'         => __( 'Next', 'calico-google-reviews' ),
			'loading_label'      => __( 'Loading reviews…', 'calico-google-reviews' ),
			'complete_label'     => __( 'All reviews loaded.', 'calico-google-reviews' ),
			'error_label'        => __( 'Reviews could not be loaded. Please try again.', 'calico-google-reviews' ),
			'display_layout'     => 'grid',
			'carousel_dots'      => 'bottom',
			'carousel_arrows'    => 'yes',
			'carousel_autoplay'  => 'no',
			'carousel_speed'     => 5,
			'carousel_scroll'    => 1,
		);
		$attributes   = shortcode_atts( $defaults, $attributes, 'calico_google_reviews' );
		$locations    = $this->api->get_locations();
		$location_id  = 'all' === $attributes['location'] || isset( $locations[ sanitize_key( $attributes['location'] ) ] ) ? sanitize_key( $attributes['location'] ) : $default_location;
		$location_settings = 'all' === $location_id ? $default_location_settings : $this->api->get_location_settings( $location_id );
		$review_settings = array_merge( $settings, $location_settings );
		$location_keys = $this->api->get_location_keys( $location_id );
		$source       = in_array( $attributes['source'], array( 'all', 'places', 'outscraper', 'business', 'csv', 'manual' ), true ) ? $attributes['source'] : $defaults['source'];
		$template     = in_array( $attributes['template'], array( 'classic', 'minimal', 'quote', 'spotlight' ), true ) ? $attributes['template'] : 'classic';
		$ratings      = is_array( $settings['visible_ratings'] ) ? $settings['visible_ratings'] : array( 1, 2, 3, 4, 5 );
		$carousel     = 'carousel' === $attributes['display_layout'];
		$limit        = max( 1, min( $carousel ? 30 : 100, absint( $attributes['limit'] ) ) );
		$loading      = ! $carousel && in_array( $attributes['loading_method'], array( 'pagination', 'load_more' ), true ) ? $attributes['loading_method'] : 'none';
		$reviews      = $this->database->get_display_reviews( $source, $limit, $ratings, 'yes' === $settings['require_review_text'], 0, $location_keys );
		$total        = 'none' === $loading ? count( $reviews ) : $this->database->count_display_reviews( $source, $ratings, 'yes' === $settings['require_review_text'], $location_keys );
		$custom       = 'yes' === $attributes['custom_template'];
		if ( empty( $reviews ) ) { return $this->status( __( 'No stored reviews match the current front-end filters.', 'calico-google-reviews' ) ); }

		$columns_value = absint( $attributes['columns'] );
		$tablet_value  = absint( $attributes['columns_tablet'] );
		$mobile_value  = absint( $attributes['columns_mobile'] );
		$columns = $columns_value ? max( 1, min( 6, $columns_value ) ) : 3;
		$tablet  = $tablet_value ? max( 1, min( 4, $tablet_value ) ) : 2;
		$mobile  = $mobile_value ? max( 1, min( 2, $mobile_value ) ) : 1;
		$classes = array( 'cgr-reviews', 'cgr-template-' . $template );
		if ( $carousel ) { $classes[] = 'cgr-layout-carousel'; }
		if ( ! $carousel && 'masonry' === $attributes['layout_mode'] ) { $classes[] = 'cgr-layout-masonry'; }
		if ( $custom ) { $classes[] = 'cgr-custom-template'; }
		$show_name      = 'all' !== $location_id && 'yes' === $attributes['show_summary'] && 'yes' === $attributes['show_business_name'];
		$show_aggregate = 'all' !== $location_id && 'yes' === $attributes['show_summary'] && 'yes' === $attributes['show_aggregate'];

		$attributes['location']            = $location_id;
		$attributes['source']              = $source;
		$attributes['limit']               = $limit;
		$attributes['template']            = $template;
		$attributes['loading_method']      = $loading;
		$attributes['pagination_position'] = 'top' === $attributes['pagination_position'] ? 'top' : 'bottom';
		$attributes['pagination_alignment'] = in_array( $attributes['pagination_alignment'], array( 'left', 'center', 'right' ), true ) ? $attributes['pagination_alignment'] : 'center';
		$attributes['load_more_count']     = max( 1, min( 100, absint( $attributes['load_more_count'] ) ) );
		$attributes['trigger_selector']    = sanitize_text_field( $attributes['trigger_selector'] );
		$config = $this->client_config( $attributes );
		$pages  = max( 1, (int) ceil( $total / $limit ) );
		$dots   = in_array( $attributes['carousel_dots'], array( 'top', 'bottom' ), true ) ? $attributes['carousel_dots'] : 'none';
		$arrows = 'yes' === $attributes['carousel_arrows'];
		$carousel_config = array(
			'autoplay' => 'yes' === $attributes['carousel_autoplay'],
			'speed'    => max( 1, min( 60, absint( $attributes['carousel_speed'] ) ) ) * 1000,
			'scroll'   => max( 1, min( 6, absint( $attributes['carousel_scroll'] ) ) ),
		);

		wp_enqueue_style( 'cgr-reviews' );
		if ( 'none' !== $loading || $carousel || 'masonry' === $attributes['layout_mode'] ) { wp_enqueue_script( 'cgr-reviews' ); }
		ob_start();
		?>
		<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="--cgr-cols:<?php echo esc_attr( $columns ); ?>;--cgr-cols-tablet:<?php echo esc_attr( $tablet ); ?>;--cgr-cols-mobile:<?php echo esc_attr( $mobile ); ?>" aria-label="<?php esc_attr_e( 'Google reviews', 'calico-google-reviews' ); ?>"<?php if ( 'none' !== $loading ) : ?> data-cgr-loading="<?php echo esc_attr( $loading ); ?>" data-cgr-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" data-cgr-offset="<?php echo esc_attr( count( $reviews ) ); ?>" data-cgr-has-more="<?php echo esc_attr( $total > count( $reviews ) ? '1' : '0' ); ?>"<?php endif; ?><?php if ( 'masonry' === $attributes['layout_mode'] && ! $carousel ) : ?> data-cgr-masonry="1"<?php endif; ?><?php if ( $carousel ) : ?> data-cgr-carousel="1" data-cgr-carousel-config="<?php echo esc_attr( wp_json_encode( $carousel_config ) ); ?>"<?php endif; ?>>
		<?php if ( $show_name || $show_aggregate ) : ?><header class="cgr-summary"><div><?php if ( $show_name && $location_settings['location_title'] ) : ?><h2 class="cgr-business-name"><?php echo esc_html( $location_settings['location_title'] ); ?></h2><?php endif; ?><?php if ( $show_aggregate && '' !== $location_settings['average_rating'] ) : ?><div class="cgr-summary-rating"><strong><?php echo esc_html( number_format_i18n( (float) $location_settings['average_rating'], 1 ) ); ?></strong><?php echo $this->stars( (float) $location_settings['average_rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?><?php if ( $show_aggregate && '' !== $location_settings['total_reviews'] ) : ?><p class="cgr-review-count"><?php echo esc_html( sprintf( _n( '%s Google review', '%s Google reviews', (int) $location_settings['total_reviews'], 'calico-google-reviews' ), number_format_i18n( (int) $location_settings['total_reviews'] ) ) ); ?></p><?php endif; ?></div></header><?php endif; ?>
		<?php if ( 'pagination' === $loading && 'top' === $attributes['pagination_position'] && $pages > 1 ) { echo $this->pagination( 1, $pages, $attributes ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( $carousel && 'top' === $dots ) : ?><div class="cgr-carousel-dots cgr-carousel-dots-top"></div><?php endif; ?>
		<?php if ( $carousel ) : ?><div class="cgr-carousel-shell"><?php if ( $arrows ) : ?><button type="button" class="cgr-carousel-arrow cgr-carousel-previous" data-direction="previous">‹</button><?php endif; ?><div class="cgr-carousel-viewport"><div class="cgr-review-list"><?php echo $this->review_items( $reviews, $review_settings, $attributes, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div><?php if ( $arrows ) : ?><button type="button" class="cgr-carousel-arrow cgr-carousel-next" data-direction="next">›</button><?php endif; ?></div><?php else : ?><div class="cgr-review-list"><?php echo $this->review_items( $reviews, $review_settings, $attributes, 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
		<?php if ( $carousel && 'bottom' === $dots ) : ?><div class="cgr-carousel-dots cgr-carousel-dots-bottom"></div><?php endif; ?>
		<?php if ( 'pagination' === $loading && 'bottom' === $attributes['pagination_position'] && $pages > 1 ) { echo $this->pagination( 1, $pages, $attributes ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( 'none' !== $loading ) : ?><p class="cgr-loading-status screen-reader-text" aria-live="polite"></p><?php endif; ?>
		</section>
		<?php
		return ob_get_clean();
	}

	public function load_reviews() {
		if ( ! check_ajax_referer( 'cgr_load_reviews', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The review request expired. Refresh the page and try again.', 'calico-google-reviews' ) ), 403 );
		}
		$raw    = isset( $_POST['config'] ) ? json_decode( wp_unslash( $_POST['config'] ), true ) : array();
		$config = $this->sanitize_client_config( is_array( $raw ) ? $raw : array() );
		$method = $config['loading_method'];
		if ( ! in_array( $method, array( 'pagination', 'load_more' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid review loading method.', 'calico-google-reviews' ) ), 400 );
		}

		$settings     = $this->api->get_settings();
		$location_settings = 'all' === $config['location'] ? $this->api->get_location_settings() : $this->api->get_location_settings( $config['location'] );
		$review_settings = array_merge( $settings, $location_settings );
		$location_keys = $this->api->get_location_keys( $config['location'] );
		$ratings      = is_array( $settings['visible_ratings'] ) ? $settings['visible_ratings'] : array( 1, 2, 3, 4, 5 );
		$require_text = 'yes' === $settings['require_review_text'];
		$page         = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
		$offset       = 'pagination' === $method ? ( $page - 1 ) * $config['limit'] : ( isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : $config['limit'] );
		$count        = 'pagination' === $method ? $config['limit'] : $config['load_more_count'];
		$total        = $this->database->count_display_reviews( $config['source'], $ratings, $require_text, $location_keys );
		$reviews      = $this->database->get_display_reviews( $config['source'], $count, $ratings, $require_text, $offset, $location_keys );
		$next_offset  = $offset + count( $reviews );
		$pages        = max( 1, (int) ceil( $total / $config['limit'] ) );

		wp_send_json_success(
			array(
				'html'       => $this->review_items( $reviews, $review_settings, $config, $offset ),
				'pagination' => 'pagination' === $method ? $this->pagination( min( $page, $pages ), $pages, $config ) : '',
				'offset'     => $next_offset,
				'hasMore'    => $next_offset < $total,
				'total'      => $total,
				'page'       => min( $page, $pages ),
			)
		);
	}

	private function review_items( array $reviews, array $settings, array $display, $offset ) {
		$custom = 'yes' === $display['custom_template'];
		$order  = $this->element_order( $display['elements'] );
		ob_start();
		foreach ( $reviews as $index => $review ) {
			$featured = 'spotlight' === $display['template'] && 0 === $index && ( 'pagination' === $display['loading_method'] || 0 === (int) $offset );
			$this->review( $review, $settings, $featured, $custom, $order, $display );
		}
		return ob_get_clean();
	}

	private function pagination( $current, $pages, array $display ) {
		if ( $pages < 2 ) { return ''; }
		$current = max( 1, min( absint( $current ), absint( $pages ) ) );
		ob_start();
		$alignment = isset( $display['pagination_alignment'] ) && in_array( $display['pagination_alignment'], array( 'left', 'center', 'right' ), true ) ? $display['pagination_alignment'] : 'center';
		?><nav class="cgr-pagination cgr-pagination-<?php echo esc_attr( $alignment ); ?>" aria-label="<?php esc_attr_e( 'Review pages', 'calico-google-reviews' ); ?>">
		<button type="button" class="cgr-page-button cgr-page-previous" data-page="<?php echo esc_attr( max( 1, $current - 1 ) ); ?>" <?php disabled( 1 === $current ); ?>><?php echo esc_html( $display['previous_label'] ); ?></button>
		<span class="cgr-page-numbers"><?php
		$last = 0;
		for ( $page = 1; $page <= $pages; $page++ ) {
			if ( 1 !== $page && $pages !== $page && abs( $page - $current ) > 2 ) { continue; }
			if ( $last && $page > $last + 1 ) { echo '<span class="cgr-page-ellipsis" aria-hidden="true">…</span>'; }
			?><button type="button" class="cgr-page-button cgr-page-number" data-page="<?php echo esc_attr( $page ); ?>" <?php if ( $page === $current ) : ?>aria-current="page" disabled<?php endif; ?>><?php echo esc_html( $page ); ?></button><?php
			$last = $page;
		}
		?></span><button type="button" class="cgr-page-button cgr-page-next" data-page="<?php echo esc_attr( min( $pages, $current + 1 ) ); ?>" <?php disabled( $current === $pages ); ?>><?php echo esc_html( $display['next_label'] ); ?></button>
		</nav><?php
		return ob_get_clean();
	}

	private function client_config( array $attributes ) {
		return $this->sanitize_client_config(
			array(
				'location'            => $attributes['location'],
				'source'              => $attributes['source'],
				'limit'               => $attributes['limit'],
				'template'            => $attributes['template'],
				'custom_template'     => $attributes['custom_template'],
				'elements'            => $attributes['elements'],
				'read_more_label'     => $attributes['read_more_label'],
				'actual_date'         => $attributes['actual_date'],
				'date_format'         => $attributes['date_format'],
				'loading_method'      => $attributes['loading_method'],
				'pagination_position' => $attributes['pagination_position'],
				'pagination_alignment' => $attributes['pagination_alignment'],
				'load_more_count'     => $attributes['load_more_count'],
				'trigger_selector'    => $attributes['trigger_selector'],
				'previous_label'      => $attributes['previous_label'],
				'next_label'          => $attributes['next_label'],
				'loading_label'       => $attributes['loading_label'],
				'complete_label'      => $attributes['complete_label'],
				'error_label'         => $attributes['error_label'],
			)
		);
	}

	private function sanitize_client_config( array $config ) {
		$source   = isset( $config['source'] ) && in_array( $config['source'], array( 'all', 'places', 'outscraper', 'business', 'csv', 'manual' ), true ) ? $config['source'] : 'all';
		$locations = $this->api->get_locations();
		$location = isset( $config['location'] ) ? sanitize_key( $config['location'] ) : $this->api->get_default_location_id();
		if ( 'all' !== $location && ! isset( $locations[ $location ] ) ) { $location = $this->api->get_default_location_id(); }
		$template = isset( $config['template'] ) && in_array( $config['template'], array( 'classic', 'minimal', 'quote', 'spotlight' ), true ) ? $config['template'] : 'classic';
		$method   = isset( $config['loading_method'] ) && in_array( $config['loading_method'], array( 'pagination', 'load_more' ), true ) ? $config['loading_method'] : 'none';
		return array(
			'location'            => $location,
			'source'              => $source,
			'limit'               => isset( $config['limit'] ) ? max( 1, min( 100, absint( $config['limit'] ) ) ) : 6,
			'template'            => $template,
			'custom_template'     => isset( $config['custom_template'] ) && 'yes' === $config['custom_template'] ? 'yes' : 'no',
			'elements'            => isset( $config['elements'] ) && is_array( $config['elements'] ) ? $config['elements'] : array(),
			'read_more_label'     => isset( $config['read_more_label'] ) ? sanitize_text_field( $config['read_more_label'] ) : '',
			'actual_date'         => isset( $config['actual_date'] ) && 'yes' === $config['actual_date'] ? 'yes' : 'no',
			'date_format'         => isset( $config['date_format'] ) ? sanitize_text_field( $config['date_format'] ) : 'm/d/Y',
			'loading_method'      => $method,
			'pagination_position' => isset( $config['pagination_position'] ) && 'top' === $config['pagination_position'] ? 'top' : 'bottom',
			'pagination_alignment' => isset( $config['pagination_alignment'] ) && in_array( $config['pagination_alignment'], array( 'left', 'center', 'right' ), true ) ? $config['pagination_alignment'] : 'center',
			'load_more_count'     => isset( $config['load_more_count'] ) ? max( 1, min( 100, absint( $config['load_more_count'] ) ) ) : 10,
			'trigger_selector'    => isset( $config['trigger_selector'] ) ? sanitize_text_field( $config['trigger_selector'] ) : '#loadmore',
			'previous_label'      => isset( $config['previous_label'] ) && '' !== trim( $config['previous_label'] ) ? sanitize_text_field( $config['previous_label'] ) : __( 'Previous', 'calico-google-reviews' ),
			'next_label'          => isset( $config['next_label'] ) && '' !== trim( $config['next_label'] ) ? sanitize_text_field( $config['next_label'] ) : __( 'Next', 'calico-google-reviews' ),
			'loading_label'       => isset( $config['loading_label'] ) && '' !== trim( $config['loading_label'] ) ? sanitize_text_field( $config['loading_label'] ) : __( 'Loading reviews…', 'calico-google-reviews' ),
			'complete_label'      => isset( $config['complete_label'] ) && '' !== trim( $config['complete_label'] ) ? sanitize_text_field( $config['complete_label'] ) : __( 'All reviews loaded.', 'calico-google-reviews' ),
			'error_label'         => isset( $config['error_label'] ) && '' !== trim( $config['error_label'] ) ? sanitize_text_field( $config['error_label'] ) : __( 'Reviews could not be loaded. Please try again.', 'calico-google-reviews' ),
		);
	}

	private function review( array $review, array $settings, $featured, $custom, array $order, array $display ) {
		$date = '';
		if ( ! empty( $review['create_time'] ) ) {
			$timestamp = strtotime( $review['create_time'] . ' UTC' );
			if ( $timestamp && 'yes' === $display['actual_date'] ) {
				$format = '' !== trim( $display['date_format'] ) ? sanitize_text_field( $display['date_format'] ) : 'm/d/Y';
				$date   = wp_date( $format, $timestamp, wp_timezone() );
			} else {
				$date = $timestamp ? sprintf( __( '%s ago', 'calico-google-reviews' ), human_time_diff( $timestamp, time() ) ) : '';
			}
		}
		$source_url = ! empty( $review['source_url'] ) ? $review['source_url'] : $settings['location_maps_uri'];
		?>
		<article class="cgr-review<?php echo $featured ? ' cgr-featured' : ''; ?>">
			<span class="cgr-quote-mark" aria-hidden="true">“</span>
			<?php if ( $custom ) : foreach ( $order as $part ) { $this->review_part( $part, $review, $date, $source_url, $settings, $display ); } else : ?>
			<header class="cgr-reviewer"><?php $this->avatar( $review ); ?><div><?php $this->name( $review ); ?><?php $this->date( $review, $date ); ?></div></header>
			<?php echo $this->stars( (float) $review['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php $this->review_text( $review, $settings, $display ); ?>
			<?php $this->reply( $review ); ?>
			<?php $this->link( $source_url ); ?>
			<?php endif; ?>
		</article><?php
	}

	private function review_part( $part, array $review, $date, $source_url, array $settings, array $display ) {
		echo '<div class="cgr-custom-part cgr-custom-' . esc_attr( $part ) . '">';
		switch ( $part ) {
			case 'avatar': $this->avatar( $review ); break;
			case 'name': $this->name( $review ); break;
			case 'date': $this->date( $review, $date ); break;
			case 'stars': echo $this->stars( (float) $review['rating'] ); break; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			case 'review': $this->review_text( $review, $settings, $display ); break;
			case 'reply': $this->reply( $review ); break;
			case 'link': $this->link( $source_url ); break;
		}
		echo '</div>';
	}

	private function avatar( array $review ) {
		if ( ! empty( $review['reviewer_photo_url'] ) ) : ?><img class="cgr-avatar" src="<?php echo esc_url( $review['reviewer_photo_url'] ); ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else : ?><span class="cgr-avatar cgr-avatar-fallback" aria-hidden="true"><?php echo esc_html( $this->initial( $review['reviewer_name'] ) ); ?></span><?php endif;
	}

	private function name( array $review ) {
		if ( ! empty( $review['reviewer_url'] ) ) : ?><a class="cgr-reviewer-name" href="<?php echo esc_url( $review['reviewer_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $review['reviewer_name'] ); ?></a><?php else : ?><strong class="cgr-reviewer-name"><?php echo esc_html( $review['reviewer_name'] ); ?></strong><?php endif;
	}

	private function date( array $review, $date ) {
		if ( $date ) : ?><time class="cgr-date" datetime="<?php echo esc_attr( mysql2date( 'c', $review['create_time'], false ) ); ?>"><?php echo esc_html( $date ); ?></time><?php endif;
	}

	private function review_text( array $review, array $settings, array $display ) {
		if ( $review['review_text'] ) {
			echo '<div class="cgr-review-text">' . $this->review_text_markup( $review['review_text'], $settings, $display ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	private function reply( array $review ) {
		if ( $review['reply_text'] ) : ?><div class="cgr-reply"><strong><?php esc_html_e( 'Response from the business', 'calico-google-reviews' ); ?></strong><p><?php echo nl2br( esc_html( $review['reply_text'] ) ); ?></p></div><?php endif;
	}

	private function link( $source_url ) {
		if ( $source_url ) : ?><a class="cgr-source-link" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View on Google', 'calico-google-reviews' ); ?></a><?php endif;
	}

	private function review_text_markup( $text, array $settings, array $display ) {
		$text  = trim( wp_strip_all_tags( $text ) );
		$limit = max( 5, min( 500, absint( $settings['review_word_limit'] ) ) );
		$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( 'yes' !== $settings['truncate_reviews'] || count( $words ) <= $limit ) { return nl2br( esc_html( $text ) ); }
		$intro = implode( ' ', array_slice( $words, 0, $limit ) );
		$rest  = implode( ' ', array_slice( $words, $limit ) );
		$label = '' !== trim( $display['read_more_label'] ) ? sanitize_text_field( $display['read_more_label'] ) : $settings['read_more_label'];
		return '<span class="cgr-review-intro">' . esc_html( $intro ) . '…</span><details class="cgr-read-more"><summary>' . esc_html( $label ) . '</summary><span>' . esc_html( $rest ) . '</span></details>';
	}

	private function element_order( $items ) {
		$allowed = array( 'avatar', 'name', 'date', 'stars', 'review', 'reply', 'link' );
		$order   = array();
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				$element = is_array( $item ) && isset( $item['element'] ) ? sanitize_key( $item['element'] ) : sanitize_key( $item );
				$enabled = ! is_array( $item ) || ! isset( $item['enabled'] ) || 'yes' === $item['enabled'];
				if ( $enabled && in_array( $element, $allowed, true ) && ! in_array( $element, $order, true ) ) { $order[] = $element; }
			}
		}
		return empty( $order ) ? $allowed : $order;
	}

	private function stars( $rating ) {
		$rounded = max( 0, min( 5, (int) round( $rating ) ) );
		$output  = '<span class="cgr-stars" role="img" aria-label="' . esc_attr( sprintf( __( '%s out of 5 stars', 'calico-google-reviews' ), number_format_i18n( $rating, 1 ) ) ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) { $output .= '<span aria-hidden="true" class="' . ( $i <= $rounded ? 'cgr-star-filled' : 'cgr-star-empty' ) . '">★</span>'; }
		return $output . '</span>';
	}

	private function initial( $name ) { $name = trim( wp_strip_all_tags( $name ) ); return '' === $name ? '?' : ( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) ) ); }
	private function default_source( $mode ) { return 'place' === $mode ? 'places' : ( 'outscraper' === $mode ? 'outscraper' : 'all' ); }
	private function status( $message ) { return current_user_can( 'manage_options' ) ? '<p class="cgr-status-message">' . esc_html( $message ) . '</p>' : ''; }
}
