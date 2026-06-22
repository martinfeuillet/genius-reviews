<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genius_Reviews_Shortcodes {


	/**
	 * Shortcode [genius_reviews_grid product_id="123" limit="6"] ou  [genius_reviews_grid]
	 */
	public static function grid( $atts = array() ) {
		global $product;

		$atts = shortcode_atts(
			array(
				'product_id'     => 0,
				'limit'          => 6,
				'remove_spacing' => 0,
			),
			$atts,
			'genius_reviews_grid'
		);

		if ( empty( $atts['product_id'] ) && function_exists( 'is_product' ) && is_product() && $product ) {
			$atts['product_id'] = $product->get_id();
		}

		ob_start();
		echo Genius_Reviews_Render::grid( $atts );
		return ob_get_clean();
	}

	/**
	 * Shortcode [genius_reviews_slider limit="10"]
	 */
	public static function slider( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'product_id' => 0,
				'limit'      => 10,
				'scope'      => 'global',
				'term_id'    => 0,
				'taxonomy'   => '',
				'mode'       => '',
			),
			$atts,
			'genius_reviews_slider'
		);

		ob_start();
		echo '<div class="gr-slider">';
		echo Genius_Reviews_Render::slider( $atts );
		echo '</div>';
		return ob_get_clean();
	}


	/**
	 * Shortcode [genius_reviews_badge product_id="123"]
	 */
	public static function badge( $atts = array() ) {
		global $product;

		$default_use_global_count = (int) get_option( 'gr_option_fallback_reviews_all', 0 );

		$atts = shortcode_atts(
			array(
				'product_id'       => 0,
				'use_global_count' => $default_use_global_count,
				'scope'            => 'product',
				'term_id'          => 0,
				'taxonomy'         => '',
				'mode'             => '',
			),
			$atts,
			'genius_reviews_badge'
		);

		if ( empty( $atts['product_id'] ) && $atts['scope'] !== 'category' && function_exists( 'is_product' ) && is_product() && $product ) {
			$atts['product_id'] = $product->get_id();
		}

		ob_start();
		echo Genius_Reviews_Render::badge( $atts );
		return ob_get_clean();
	}

	/**
	 * Shortcode [genius_reviews_category_badge]
	 */
	public static function category_badge( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'term_id'  => 0,
				'taxonomy' => '',
				'mode'     => 'compact_rating',
				'debug'    => 0,
			),
			$atts,
			'genius_reviews_category_badge'
		);

		$atts['scope'] = 'category';

		ob_start();
		echo Genius_Reviews_Render::badge( $atts );
		$html = ob_get_clean();

		if ( $html === '' && ! empty( $atts['debug'] ) && current_user_can( 'manage_options' ) ) {
			return '<span class="gr-badge-debug">Genius Reviews: aucun badge categorie genere.</span>';
		}

		return $html;
	}

	/**
	 * Shortcode [genius_reviews_summary]
	 *
	 * Affiche un résumé compact réutilisable.
	 */
	public static function summary( $atts = array() ) {
		global $product;

		$atts = shortcode_atts(
			array(
				'product_id'   => 0,
				'scope'        => 'global',
				'stars'        => 1,
				'average'      => 1,
				'count'        => 1,
				'separator'    => 'sur',
				'count_prefix' => '+ de',
				'count_suffix' => __( 'avis vérifiés', 'genius-reviews' ),
			),
			$atts,
			'genius_reviews_summary'
		);

		if ( empty( $atts['product_id'] ) && $atts['scope'] !== 'global' && function_exists( 'is_product' ) && is_product() && $product ) {
			$atts['product_id'] = $product->get_id();
		}

		ob_start();
		echo Genius_Reviews_Render::summary( $atts );
		return ob_get_clean();
	}

	/**
	 * Shortcode [genius_reviews_all limit="12"]
	 * Affiche les avis produits + avis boutique avec onglets.
	 */
	public static function grid_all_reviews( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'limit' => 12,
				'sort'  => 'date_desc',
			),
			$atts,
			'genius_reviews_all'
		);

		ob_start();
		echo Genius_Reviews_Render::grid_all_reviews( $atts );
		return ob_get_clean();
	}
}
