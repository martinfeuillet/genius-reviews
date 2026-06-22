<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}


class Genius_Reviews_Elementor extends \Elementor\Widget_Base {

	public function get_name() {
		return 'genius_reviews'; }
	public function get_title() {
		return __( 'Genius Reviews', 'genius-reviews' ); }
	public function get_icon() {
		return 'eicon-comments'; }
	public function get_categories() {
		return array( 'general' ); }

	protected function register_controls() {
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Options', 'genius-reviews' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'product_id',
			array(
				'label'   => __( 'Produit ID', 'genius-reviews' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 0,
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Nombre d’avis', 'genius-reviews' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 6,
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		echo Genius_Reviews_Render::grid( $settings );
	}
}
