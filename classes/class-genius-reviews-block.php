<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! function_exists( 'register_block_type' ) ) {
	return;
}

class Genius_Reviews_Block {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	public static function register_block() {
		register_block_type(
			'genius-reviews/grid',
			array(
				'render_callback' => array( __CLASS__, 'render_grid' ),
				'attributes'      => array(
					'product_id' => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'limit'      => array(
						'type'    => 'integer',
						'default' => 6,
					),
				),
			)
		);
	}

	public static function render_grid( $atts ) {
		$atts = wp_parse_args(
			$atts,
			array(
				'product_id' => 0,
				'limit'      => 6,
			)
		);

		ob_start();
		echo Genius_Reviews_Render::grid( $atts );
		return ob_get_clean();
	}
}
