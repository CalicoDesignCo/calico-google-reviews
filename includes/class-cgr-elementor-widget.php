<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

final class CGR_Elementor_Widget extends Widget_Base {
	private static $frontend;

	public static function set_frontend( CGR_Frontend $frontend ) { self::$frontend = $frontend; }
	public function get_name() { return 'calico-google-reviews'; }
	public function get_title() { return __( 'Calico Google Reviews', 'calico-google-reviews' ); }
	public function get_icon() { return 'eicon-review'; }
	public function get_categories() { return array( 'calico' ); }
	public function get_keywords() { return array( 'google', 'reviews', 'rating', 'testimonial', 'calico' ); }
	public function get_style_depends() { return array( 'cgr-reviews' ); }
	public function get_script_depends() { return array( 'cgr-reviews' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Reviews', 'calico-google-reviews' ) ) );
		$this->add_control( 'location', array( 'label' => __( 'Location', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => self::$frontend ? self::$frontend->default_location_id() : 'all', 'options' => self::$frontend ? self::$frontend->location_options() : array( 'all' => __( 'All locations', 'calico-google-reviews' ) ), 'description' => __( 'Choose which business location supplies reviews for this widget, or select All locations for a combined feed.', 'calico-google-reviews' ) ) );
		$this->add_control( 'source', array( 'label' => __( 'Source', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'all', 'options' => array( 'all' => __( 'All stored reviews', 'calico-google-reviews' ), 'places' => __( 'Google Places API', 'calico-google-reviews' ), 'outscraper' => __( 'Outscraper API', 'calico-google-reviews' ), 'csv' => __( 'CSV', 'calico-google-reviews' ), 'manual' => __( 'Manual', 'calico-google-reviews' ) ) ) );
		$this->add_control( 'template', array( 'label' => __( 'Template', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'classic', 'options' => array( 'classic' => __( 'Classic Cards', 'calico-google-reviews' ), 'minimal' => __( 'Minimal', 'calico-google-reviews' ), 'quote' => __( 'Quote', 'calico-google-reviews' ), 'spotlight' => __( 'Spotlight', 'calico-google-reviews' ) ) ) );
		$this->add_control( 'display_layout', array( 'label' => __( 'Display layout', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'grid', 'options' => array( 'grid' => __( 'Grid', 'calico-google-reviews' ), 'carousel' => __( 'Sliding Carousel', 'calico-google-reviews' ) ) ) );
		$this->add_control( 'limit', array( 'label' => __( 'Review limit', 'calico-google-reviews' ), 'description' => __( 'Carousel mode is capped at 30 reviews.', 'calico-google-reviews' ), 'type' => Controls_Manager::NUMBER, 'default' => 6, 'min' => 1, 'max' => 100 ) );
		$this->add_control( 'carousel_dots', array( 'label' => __( 'Navigation dots', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'bottom', 'options' => array( 'none' => __( 'Hidden', 'calico-google-reviews' ), 'top' => __( 'Above carousel', 'calico-google-reviews' ), 'bottom' => __( 'Below carousel', 'calico-google-reviews' ) ), 'condition' => array( 'display_layout' => 'carousel' ) ) );
		$this->add_control( 'carousel_arrows', array( 'label' => __( 'Side arrows', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes', 'condition' => array( 'display_layout' => 'carousel' ) ) );
		$this->add_control( 'carousel_autoplay', array( 'label' => __( 'Automatic scrolling', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'On', 'calico-google-reviews' ), 'label_off' => __( 'Off', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'no', 'condition' => array( 'display_layout' => 'carousel' ) ) );
		$this->add_control( 'carousel_speed', array( 'label' => __( 'Seconds between scrolls', 'calico-google-reviews' ), 'type' => Controls_Manager::NUMBER, 'default' => 5, 'min' => 1, 'max' => 60, 'condition' => array( 'display_layout' => 'carousel', 'carousel_autoplay' => 'yes' ) ) );
		$this->add_control( 'carousel_scroll', array( 'label' => __( 'Slides per scroll', 'calico-google-reviews' ), 'description' => __( 'Used by both arrow clicks and automatic scrolling.', 'calico-google-reviews' ), 'type' => Controls_Manager::NUMBER, 'default' => 1, 'min' => 1, 'max' => 6, 'condition' => array( 'display_layout' => 'carousel' ) ) );
		$this->add_control( 'loading_method', array( 'label' => __( 'Additional reviews', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'none', 'options' => array( 'none' => __( 'None', 'calico-google-reviews' ), 'pagination' => __( 'Pagination', 'calico-google-reviews' ), 'load_more' => __( 'External Load More button', 'calico-google-reviews' ) ), 'condition' => array( 'display_layout' => 'grid' ) ) );
		$this->add_control( 'pagination_position', array( 'label' => __( 'Pagination placement', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'bottom', 'options' => array( 'top' => __( 'Top', 'calico-google-reviews' ), 'bottom' => __( 'Bottom', 'calico-google-reviews' ) ), 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'pagination' ) ) );
		$this->add_control( 'pagination_alignment', array( 'label' => __( 'Pagination alignment', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'center', 'options' => array( 'left' => __( 'Left', 'calico-google-reviews' ), 'center' => __( 'Center', 'calico-google-reviews' ), 'right' => __( 'Right', 'calico-google-reviews' ) ), 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'pagination' ) ) );
		$this->add_control( 'previous_label', array( 'label' => __( 'Previous label', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Previous', 'calico-google-reviews' ), 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'pagination' ) ) );
		$this->add_control( 'next_label', array( 'label' => __( 'Next label', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Next', 'calico-google-reviews' ), 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'pagination' ) ) );
		$this->add_control( 'load_more_count', array( 'label' => __( 'Reviews per click', 'calico-google-reviews' ), 'type' => Controls_Manager::NUMBER, 'default' => 10, 'min' => 1, 'max' => 100, 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'load_more' ) ) );
		$this->add_control( 'trigger_selector', array( 'label' => __( 'Button CSS selector', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => '#loadmore', 'description' => __( 'Set an Elementor Button CSS ID to “loadmore”, then target it here as #loadmore. Use a unique ID for each reviews widget.', 'calico-google-reviews' ), 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'load_more' ) ) );
		$this->add_control( 'loading_label', array( 'label' => __( 'Loading message', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Loading reviews…', 'calico-google-reviews' ), 'condition' => array( 'display_layout' => 'grid', 'loading_method!' => 'none' ) ) );
		$this->add_control( 'complete_label', array( 'label' => __( 'All loaded message', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'All reviews loaded.', 'calico-google-reviews' ), 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'load_more' ) ) );
		$this->add_control( 'error_label', array( 'label' => __( 'Error message', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Reviews could not be loaded. Please try again.', 'calico-google-reviews' ), 'condition' => array( 'display_layout' => 'grid', 'loading_method!' => 'none' ) ) );
		$this->add_control( 'show_business_name', array( 'label' => __( 'Show business name', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'show_aggregate', array( 'label' => __( 'Show aggregate reviews area', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'actual_date', array( 'label' => __( 'Show actual review date', 'calico-google-reviews' ), 'description' => __( 'Turn off for relative dates such as “2 months ago”.', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Actual date', 'calico-google-reviews' ), 'label_off' => __( 'Relative date', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'no' ) );
		$this->add_control( 'date_format', array( 'label' => __( 'Date format', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => 'm/d/Y', 'placeholder' => 'm/d/Y', 'description' => __( 'Uses WordPress date-format characters. m/d/Y displays 12/24/2026.', 'calico-google-reviews' ), 'condition' => array( 'actual_date' => 'yes' ) ) );
		$this->add_control( 'read_more_label', array( 'label' => __( 'Read more label', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'placeholder' => __( 'Use global label', 'calico-google-reviews' ), 'description' => __( 'Translate or rename this widget’s label, for example “View more”. Leave blank to use Google Reviews → Display.', 'calico-google-reviews' ) ) );
		$this->add_responsive_control( 'columns', array( 'label' => __( 'Columns', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 3, 'desktop_default' => 3, 'tablet_default' => 2, 'mobile_default' => 1, 'options' => array( 1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6' ) ) );

		$this->add_control( 'custom_template', array( 'label' => __( 'Use custom card template', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Yes', 'calico-google-reviews' ), 'label_off' => __( 'No', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'no', 'separator' => 'before' ) );
		$repeater = new Repeater();
		$repeater->add_control( 'element', array( 'label' => __( 'Element', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'review', 'options' => array( 'avatar' => __( 'Avatar', 'calico-google-reviews' ), 'name' => __( 'Name', 'calico-google-reviews' ), 'date' => __( 'Date', 'calico-google-reviews' ), 'stars' => __( 'Stars', 'calico-google-reviews' ), 'review' => __( 'Review', 'calico-google-reviews' ), 'reply' => __( 'Response from Business', 'calico-google-reviews' ), 'link' => __( 'View on Google link', 'calico-google-reviews' ) ) ) );
		$repeater->add_control( 'enabled', array( 'label' => __( 'Show', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control(
			'elements',
			array(
				'label'       => __( 'Card elements — drag to reorder', 'calico-google-reviews' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ element }}}',
				'condition'   => array( 'custom_template' => 'yes' ),
				'default'     => array(
					array( 'element' => 'avatar', 'enabled' => 'yes' ),
					array( 'element' => 'name', 'enabled' => 'yes' ),
					array( 'element' => 'date', 'enabled' => 'yes' ),
					array( 'element' => 'stars', 'enabled' => 'yes' ),
					array( 'element' => 'review', 'enabled' => 'yes' ),
					array( 'element' => 'reply', 'enabled' => 'yes' ),
					array( 'element' => 'link', 'enabled' => 'yes' ),
				),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'style_wrapper', array( 'label' => __( 'Wrapper & spacing', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'layout_mode', array( 'label' => __( 'Masonry card heights', 'calico-google-reviews' ), 'description' => __( 'Turn on to let each card end immediately after its content instead of matching row height.', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Masonry', 'calico-google-reviews' ), 'label_off' => __( 'Same height', 'calico-google-reviews' ), 'return_value' => 'masonry', 'default' => '', 'condition' => array( 'display_layout' => 'grid' ) ) );
		$this->add_group_control( Group_Control_Background::get_type(), array( 'name' => 'wrapper_background', 'selector' => '{{WRAPPER}} .cgr-reviews' ) );
		$this->add_responsive_control( 'wrapper_padding', array( 'label' => __( 'Padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-reviews' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'card_gap', array( 'label' => __( 'Card gap', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-review-list' => 'gap: {{SIZE}}{{UNIT}}; column-gap: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-carousel-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_cards', array( 'label' => __( 'Cards', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Background::get_type(), array( 'name' => 'card_background', 'selector' => '{{WRAPPER}} .cgr-review' ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'card_border', 'selector' => '{{WRAPPER}} .cgr-review' ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => 'card_shadow', 'selector' => '{{WRAPPER}} .cgr-review' ) );
		$this->add_responsive_control( 'card_radius', array( 'label' => __( 'Border radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-review' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'card_padding', array( 'label' => __( 'Padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .cgr-review' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->text_style_section( 'reviewer', __( 'Reviewer name', 'calico-google-reviews' ), '.cgr-reviewer-name' );
		$this->text_style_section( 'date', __( 'Date', 'calico-google-reviews' ), '.cgr-date' );
		$this->start_controls_section( 'style_stars', array( 'label' => __( 'Stars', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'star_color', array( 'label' => __( 'Filled color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-star-filled' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'empty_star_color', array( 'label' => __( 'Empty color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-star-empty' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'star_size', array( 'label' => __( 'Size', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 8, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-stars' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->text_style_section( 'review_text', __( 'Review text', 'calico-google-reviews' ), '.cgr-review-text' );
		$this->start_controls_section( 'style_read_more', array( 'label' => __( 'Read more link', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'read_more_color', array( 'label' => __( 'Text color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-read-more summary' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'read_more_hover_color', array( 'label' => __( 'Hover color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-read-more summary:hover' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'read_more_background', array( 'label' => __( 'Background color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-read-more summary' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'read_more_typography', 'selector' => '{{WRAPPER}} .cgr-read-more summary' ) );
		$this->add_responsive_control( 'read_more_padding', array( 'label' => __( 'Padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .cgr-read-more summary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'read_more_radius', array( 'label' => __( 'Border radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-read-more summary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'read_more_spacing', array( 'label' => __( 'Top spacing', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-read-more' => 'margin-top: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'style_reply', array( 'label' => __( 'Business Reply', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'reply_color', array( 'label' => __( 'Text color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-reply' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'reply_background', array( 'label' => __( 'Background color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-reply' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'reply_typography', 'selector' => '{{WRAPPER}} .cgr-reply' ) );
		$this->add_responsive_control( 'reply_radius', array( 'label' => __( 'Border radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-reply' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'reply_padding', array( 'label' => __( 'Container padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .cgr-reply' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'reply_spacing', array( 'label' => __( 'Top spacing', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-reply' => 'margin-top: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->text_style_section( 'links', __( 'Links', 'calico-google-reviews' ), '.cgr-source-link' );
		$this->text_style_section( 'summary', __( 'Summary', 'calico-google-reviews' ), '.cgr-summary' );

		$this->start_controls_section( 'style_pagination', array( 'label' => __( 'Pagination', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'display_layout' => 'grid', 'loading_method' => 'pagination' ) ) );
		$this->add_control( 'pagination_color', array( 'label' => __( 'Text color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-page-button' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'pagination_background', array( 'label' => __( 'Background color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-page-button' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'pagination_hover_color', array( 'label' => __( 'Hover text color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-page-button:hover:not(:disabled)' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'pagination_hover_background', array( 'label' => __( 'Hover background', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-page-button:hover:not(:disabled)' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'pagination_active_color', array( 'label' => __( 'Current-page text', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-page-button[aria-current="page"]' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'pagination_active_background', array( 'label' => __( 'Current-page background', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-page-button[aria-current="page"]' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'pagination_typography', 'selector' => '{{WRAPPER}} .cgr-page-button' ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'pagination_border', 'selector' => '{{WRAPPER}} .cgr-page-button' ) );
		$this->add_responsive_control( 'pagination_padding', array( 'label' => __( 'Button padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .cgr-page-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'pagination_radius', array( 'label' => __( 'Border radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-page-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'pagination_gap', array( 'label' => __( 'Button gap', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-pagination, {{WRAPPER}} .cgr-page-numbers' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'pagination_spacing', array( 'label' => __( 'Spacing from reviews', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-pagination' => 'margin-top: {{SIZE}}{{UNIT}}; margin-bottom: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_carousel_navigation', array( 'label' => __( 'Carousel Navigation', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'display_layout' => 'carousel' ) ) );
		$this->add_control( 'carousel_dots_heading', array( 'label' => __( 'Navigation Dots', 'calico-google-reviews' ), 'type' => Controls_Manager::HEADING ) );
		$this->add_control( 'carousel_dot_color', array( 'label' => __( 'Dormant color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_dot_hover_color', array( 'label' => __( 'Hover color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot:hover' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_dot_active_color', array( 'label' => __( 'Active color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot.is-active' => 'background-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'carousel_dot_border', 'selector' => '{{WRAPPER}} .cgr-carousel-dot' ) );
		$this->add_control( 'carousel_dot_hover_border', array( 'label' => __( 'Hover border color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot:hover' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_dot_active_border', array( 'label' => __( 'Active border color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot.is-active' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'carousel_dot_size', array( 'label' => __( 'Dot size', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 4, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_dot_radius', array( 'label' => __( 'Dot radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dot' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_dot_gap', array( 'label' => __( 'Dot spacing', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-carousel-dots' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_dots_x', array( 'label' => __( 'Dots horizontal offset', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -300, 'max' => 300 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-dots-x: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_dots_y', array( 'label' => __( 'Dots vertical offset', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -200, 'max' => 200 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-dots-y: {{SIZE}}{{UNIT}};' ) ) );

		$this->add_control( 'carousel_arrows_heading', array( 'label' => __( 'Side Arrows', 'calico-google-reviews' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'carousel_arrow_color', array( 'label' => __( 'Dormant icon color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_background', array( 'label' => __( 'Dormant background', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_hover_color', array( 'label' => __( 'Hover icon color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow:hover:not(:disabled)' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_hover_background', array( 'label' => __( 'Hover background', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow:hover:not(:disabled)' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_hover_border', array( 'label' => __( 'Hover border color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow:hover:not(:disabled)' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_active_color', array( 'label' => __( 'Active icon color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow:active:not(:disabled)' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_active_background', array( 'label' => __( 'Active background', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow:active:not(:disabled)' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'carousel_arrow_active_border', array( 'label' => __( 'Active border color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow:active:not(:disabled)' => 'border-color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'carousel_arrow_border', 'selector' => '{{WRAPPER}} .cgr-carousel-arrow' ) );
		$this->add_responsive_control( 'carousel_arrow_size', array( 'label' => __( 'Button size', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 20, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_arrow_icon_size', array( 'label' => __( 'Icon size', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 8, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_arrow_radius', array( 'label' => __( 'Arrow radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-carousel-arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_previous_x', array( 'label' => __( 'Previous arrow horizontal offset', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -300, 'max' => 300 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-prev-x: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_previous_y', array( 'label' => __( 'Previous arrow vertical offset', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -300, 'max' => 300 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-prev-y: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_next_x', array( 'label' => __( 'Next arrow horizontal offset', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -300, 'max' => 300 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-next-x: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'carousel_next_y', array( 'label' => __( 'Next arrow vertical offset', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -300, 'max' => 300 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-layout-carousel' => '--cgr-next-y: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private function text_style_section( $id, $label, $selector ) {
		$this->start_controls_section( 'style_' . $id, array( 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( $id . '_color', array( 'label' => __( 'Color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $selector => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => $id . '_typography', 'selector' => '{{WRAPPER}} ' . $selector ) );
		$this->end_controls_section();
	}

	protected function render() {
		if ( ! self::$frontend ) { return; }
		$settings = $this->get_settings_for_display();
		$settings['columns'] = ! empty( $settings['columns'] ) ? $settings['columns'] : 3;
		$settings['columns_tablet'] = ! empty( $settings['columns_tablet'] ) ? $settings['columns_tablet'] : 2;
		$settings['columns_mobile'] = ! empty( $settings['columns_mobile'] ) ? $settings['columns_mobile'] : 1;
		echo self::$frontend->render( $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
