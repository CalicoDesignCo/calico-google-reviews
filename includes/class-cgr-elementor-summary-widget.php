<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

final class CGR_Elementor_Summary_Widget extends Widget_Base {
	private static $frontend;

	public static function set_frontend( CGR_Frontend $frontend ) { self::$frontend = $frontend; }
	public function get_name() { return 'calico-review-summary'; }
	public function get_title() { return __( 'Calico Review Summary', 'calico-google-reviews' ); }
	public function get_icon() { return 'eicon-rating'; }
	public function get_categories() { return array( 'calico' ); }
	public function get_keywords() { return array( 'google', 'reviews', 'rating', 'summary', 'count', 'calico' ); }
	public function get_style_depends() { return array( 'cgr-reviews' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Review Summary', 'calico-google-reviews' ) ) );
		$this->add_control( 'location', array( 'label' => __( 'Location', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => self::$frontend ? self::$frontend->default_location_id() : 'all', 'options' => self::$frontend ? self::$frontend->location_options() : array( 'all' => __( 'All locations', 'calico-google-reviews' ) ), 'description' => __( 'Individual locations inherit their saved Google links. All locations combines the available totals and ratings; enter custom link URLs below if links should also appear in that view.', 'calico-google-reviews' ) ) );
		$this->add_control( 'layout', array( 'label' => __( 'Layout', 'calico-google-reviews' ), 'type' => Controls_Manager::SELECT, 'default' => 'horizontal', 'options' => array( 'horizontal' => __( 'Horizontal', 'calico-google-reviews' ), 'vertical' => __( 'Vertical', 'calico-google-reviews' ) ) ) );

		$this->add_control( 'show_total_reviews', array( 'label' => __( 'Total reviews', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'total_reviews_label', array( 'label' => __( 'Total reviews label', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Total reviews', 'calico-google-reviews' ), 'condition' => array( 'show_total_reviews' => 'yes' ) ) );
		$this->add_control( 'show_average_rating', array( 'label' => __( 'Average rating', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'average_rating_label', array( 'label' => __( 'Average rating label', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Average rating', 'calico-google-reviews' ), 'condition' => array( 'show_average_rating' => 'yes' ) ) );
		$this->add_control( 'show_five_star_reviews', array( 'label' => __( 'Number of 5-star reviews', 'calico-google-reviews' ), 'description' => __( 'Counts stored 5-star reviews for the selected location. A complete Outscraper API import provides the most complete count.', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'five_star_reviews_label', array( 'label' => __( '5-star reviews label', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( '5-star reviews', 'calico-google-reviews' ), 'condition' => array( 'show_five_star_reviews' => 'yes' ) ) );

		$this->add_control( 'show_google_reviews_link', array( 'label' => __( 'Link to Google reviews', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes', 'separator' => 'before' ) );
		$this->add_control( 'google_reviews_link_text', array( 'label' => __( 'Google reviews link text', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'View our Google reviews', 'calico-google-reviews' ), 'condition' => array( 'show_google_reviews_link' => 'yes' ) ) );
		$this->add_control( 'google_reviews_url', array( 'label' => __( 'Google reviews URL override', 'calico-google-reviews' ), 'description' => __( 'Optional. Leave blank to use the selected location\'s saved Google link.', 'calico-google-reviews' ), 'type' => Controls_Manager::URL, 'placeholder' => 'https://', 'condition' => array( 'show_google_reviews_link' => 'yes' ) ) );
		$this->add_control( 'show_leave_review_link', array( 'label' => __( 'Link to leave a review', 'calico-google-reviews' ), 'type' => Controls_Manager::SWITCHER, 'label_on' => __( 'Show', 'calico-google-reviews' ), 'label_off' => __( 'Hide', 'calico-google-reviews' ), 'return_value' => 'yes', 'default' => 'yes' ) );
		$this->add_control( 'leave_review_link_text', array( 'label' => __( 'Leave a review link text', 'calico-google-reviews' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Leave a review', 'calico-google-reviews' ), 'condition' => array( 'show_leave_review_link' => 'yes' ) ) );
		$this->add_control( 'leave_review_url', array( 'label' => __( 'Leave a review URL override', 'calico-google-reviews' ), 'description' => __( 'Optional. Leave blank to use the selected location\'s generated or saved review-request link.', 'calico-google-reviews' ), 'type' => Controls_Manager::URL, 'placeholder' => 'https://', 'condition' => array( 'show_leave_review_link' => 'yes' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_layout', array( 'label' => __( 'Layout', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'alignment', array( 'label' => __( 'Alignment', 'calico-google-reviews' ), 'type' => Controls_Manager::CHOOSE, 'options' => array( 'left' => array( 'title' => __( 'Left', 'calico-google-reviews' ), 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => __( 'Center', 'calico-google-reviews' ), 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => __( 'Right', 'calico-google-reviews' ), 'icon' => 'eicon-text-align-right' ) ), 'default' => 'left', 'selectors_dictionary' => array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ), 'selectors' => array( '{{WRAPPER}} .cgr-review-summary-widget' => 'justify-content: {{VALUE}}; align-items: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'item_gap', array( 'label' => __( 'Item gap', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-review-summary-widget' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Background::get_type(), array( 'name' => 'container_background', 'selector' => '{{WRAPPER}} .cgr-review-summary-widget' ) );
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => 'container_border', 'selector' => '{{WRAPPER}} .cgr-review-summary-widget' ) );
		$this->add_responsive_control( 'container_radius', array( 'label' => __( 'Border radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-review-summary-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'container_padding', array( 'label' => __( 'Padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .cgr-review-summary-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_values', array( 'label' => __( 'Values', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'value_color', array( 'label' => __( 'Color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-summary-value' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'value_typography', 'selector' => '{{WRAPPER}} .cgr-summary-value' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_labels', array( 'label' => __( 'Labels', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'label_color', array( 'label' => __( 'Color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-summary-label' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'label_typography', 'selector' => '{{WRAPPER}} .cgr-summary-label' ) );
		$this->add_responsive_control( 'label_gap', array( 'label' => __( 'Value-to-label gap', 'calico-google-reviews' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .cgr-summary-item' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'style_links', array( 'label' => __( 'Links', 'calico-google-reviews' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'link_color', array( 'label' => __( 'Color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-summary-link' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'link_hover_color', array( 'label' => __( 'Hover color', 'calico-google-reviews' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .cgr-summary-link:hover, {{WRAPPER}} .cgr-summary-link:focus' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'link_typography', 'selector' => '{{WRAPPER}} .cgr-summary-link' ) );
		$this->add_responsive_control( 'link_padding', array( 'label' => __( 'Padding', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( '{{WRAPPER}} .cgr-summary-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'link_radius', array( 'label' => __( 'Border radius', 'calico-google-reviews' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .cgr-summary-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		if ( ! self::$frontend ) { return; }
		echo self::$frontend->render_summary( $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
