<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genius_Reviews_Render {

	private static $term_product_ids_cache = array();

	private static function default_star_colors() {
		return array(
			5 => '#58AF59',
			4 => '#92D329',
			3 => '#FFCE0C',
			2 => '#FF9232',
			1 => '#EB3531',
		);
	}

	private static function star_color_style( $filled, $rating ) {
		$rating              = max( 1, min( 5, (int) round( $rating ) ) );
		$default_star_colors = self::default_star_colors();

		if ( ! $filled ) {
			return 'color: var(--color-star-empty-custom, #E5E5E5);';
		}

		return 'color: var(--color-star-' . $rating . '-custom, ' . $default_star_colors[ $rating ] . ');';
	}

	public static function render_star_svg( $filled, $rating, $classes = '', $size = 22, $attrs = array() ) {
		$rating           = max( 1, min( 5, (int) round( $rating ) ) );
		$size             = in_array( (int) $size, array( 16, 18, 22 ), true ) ? (int) $size : 22;
		$state_class      = $filled ? 'gr-star-filled gr-star-rating-' . $rating : 'gr-star-empty';
		$attrs            = is_array( $attrs ) ? $attrs : array();
		$attrs['class']   = trim( $classes . ' ' . $state_class );
		$attrs['style']   = trim( ( isset( $attrs['style'] ) ? $attrs['style'] . ' ' : '' ) . self::star_color_style( $filled, $rating ) );
		$attrs['width']   = (string) $size;
		$attrs['height']  = (string) $size;
		$attrs['viewBox'] = '0 0 25 25';
		$attrs['fill']    = 'none';

		$attr_html = '';
		foreach ( $attrs as $name => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			$attr_html .= ' ' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return '<svg xmlns="http://www.w3.org/2000/svg"' . $attr_html . '>
            <path d="M25 0H0V25H25V0Z" fill="currentColor"/>
            <path d="M12.1792 4L14.1858 10.1756H20.6792L15.4259 13.9923L13.8026 15.1718L12.1792 16.3512L6.92591 20.168L8.93249 13.9923L3.6792 10.1756H10.1726L12.1792 4Z" fill="var(--color-star-icon-custom, #FFFFFF)"/>
            <path d="M18 20L15.5 13.5L12.5 16L18 20Z" fill="var(--color-star-icon-custom, #FFFFFF)"/>
        </svg>';
	}


	/**
	 * Injecte les couleurs de marque (principale et hover) dans le <head>.
	 * Récupère la couleur personnalisée depuis les options
	 *
	 * @return void
	 */
	public static function inject_brand_color() {
		$color = get_option( 'gr_color_brand_custom', '#58AF59' );
		if ( ! $color ) {
			$color = '#58AF59';
		}

		$star_color        = get_option( 'gr_color_star_custom', '#58AF59' );
		$star_icon_color   = get_option( 'gr_color_star_icon_custom', '#FFFFFF' );
		$star_empty_color  = get_option( 'gr_color_star_empty_custom', '#E5E5E5' );
		$star_level_colors = self::default_star_colors();
		foreach ( $star_level_colors as $level => $default_color ) {
			$star_level_colors[ $level ] = get_option( 'gr_color_star_' . $level . '_custom', $default_color );
		}

		// Génère automatiquement la couleur de hover
		$hover = self::adjust_brightness( $color, -20 );

		echo '<style>
        :root {
            --color-brand-custom: ' . esc_html( $color ) . ';
            --color-brand-custom-hover: ' . esc_html( $hover ) . ';
            --color-star-custom: ' . esc_html( $star_color ) . ';
            --color-star-icon-custom: ' . esc_html( $star_icon_color ) . ';
            --color-star-empty-custom: ' . esc_html( $star_empty_color ) . ';
            --color-star-5-custom: ' . esc_html( $star_level_colors[5] ) . ';
            --color-star-4-custom: ' . esc_html( $star_level_colors[4] ) . ';
            --color-star-3-custom: ' . esc_html( $star_level_colors[3] ) . ';
            --color-star-2-custom: ' . esc_html( $star_level_colors[2] ) . ';
            --color-star-1-custom: ' . esc_html( $star_level_colors[1] ) . ';
        }
    </style>';
	}

	/**
	 * Génère le bloc principal d’avis clients sous forme de grille.
	 *
	 * Récupère les avis liés à un produit spécifique,
	 * calcule les moyennes et compte les notes, puis affiche le template complet.
	 *
	 * @param array $args {
	 *     @type int    $product_id ID du produit (facultatif).
	 *     @type int    $limit      Nombre maximum d’avis à afficher.
	 *     @type string $sort       Type de tri (date_desc, rating_asc, etc.).
	 * }
	 * @return string HTML complet de la grille d’avis.
	 */
	public static function grid( $args = array() ) {
		$defaults = array(
			'product_id'     => 0,
			'limit'          => 6,
			'sort'           => 'date_desc',
			'remove_spacing' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( isset( $_GET['gr-sort'] ) ) {
			$args['sort'] = sanitize_text_field( $_GET['gr-sort'] );
		}

		$sort_args       = Genius_Reviews_Query_Helper::map_sort( $args['sort'] );
		$sort_meta_query = isset( $sort_args['meta_query'] ) ? $sort_args['meta_query'] : null;

		$q_args = array(
			'post_type'      => 'genius_review',
			'posts_per_page' => $args['limit'],
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_gr_curated',
					'value'   => 'ok',
					'compare' => '=',
				),
			),
		);

		if ( $args['product_id'] ) {
			$q_args['meta_query'][] = array(
				'key'   => '_gr_product_id',
				'value' => $args['product_id'],
			);
		}

		if ( ! empty( $sort_meta_query ) ) {
			$q_args['meta_query'][] = $sort_meta_query;
			unset( $sort_args['meta_query'] );
		}

		$q_args = array_merge( $q_args, $sort_args );

		$q = new WP_Query( $q_args );

		$fallback_reviews_all = (int) get_option( 'gr_option_fallback_reviews_all', 0 );

		if ( ! $q->have_posts() && $fallback_reviews_all && $args['product_id'] ) {
			$fallback_meta_query = array(
				array(
					'key'     => '_gr_curated',
					'value'   => 'ok',
					'compare' => '=',
				),
			);

			if ( ! empty( $sort_meta_query ) ) {
				$fallback_meta_query[] = $sort_meta_query;
			}

			$fallback_query = new WP_Query(
				array_merge(
					array(
						'post_type'      => 'genius_review',
						'posts_per_page' => $args['limit'],
						'post_status'    => 'publish',
						'meta_query'     => $fallback_meta_query,
					),
					$sort_args
				)
			);

			$stats = Genius_Reviews_Query_Helper::get_global_stats();

			if ( $fallback_query->have_posts() && $stats['count'] > 0 ) {
				return self::grid_all_reviews(
					array(
						'limit'          => $args['limit'],
						'sort'           => $args['sort'],
						'remove_spacing' => $args['remove_spacing'] ?? 0,
					)
				);
			}
		}

		if ( ! $q->have_posts() ) {
			return self::render_no_reviews();
		}

		$avg   = (float) get_post_meta( $args['product_id'], '_gr_avg_rating', true );
		$count = (int) get_post_meta( $args['product_id'], '_gr_review_count', true );

		// Détails par note
		$counts_by_rating = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
		);

		$meta_query_stats = array(
			array(
				'key'     => '_gr_curated',
				'value'   => 'ok',
				'compare' => '=',
			),
		);

		if ( $args['product_id'] ) {
			$meta_query_stats[] = array(
				'key'   => '_gr_product_id',
				'value' => $args['product_id'],
			);
		}

		$q_stats = new WP_Query(
			array(
				'post_type'      => 'genius_review',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => $meta_query_stats,
			)
		);

		if ( $q_stats->have_posts() ) {
			foreach ( $q_stats->posts as $review_id ) {
				$rating = (int) get_post_meta( $review_id, '_gr_rating', true );
				if ( $rating >= 1 && $rating <= 5 ) {
					++$counts_by_rating[ $rating ];
				}
			}
		}

		return self::render_grid( $q, $avg, $count, $args, $counts_by_rating );
	}


	/**
	 * Génère un carrousel d’avis clients.
	 *
	 * Utilisé notamment sur la page d’accueil ou les pages vitrines.
	 * Affiche les avis sous forme de slides.
	 *
	 * @param array $args {
	 *     @type int    $limit Nombre d’avis à afficher.
	 *     @type string $sort  Type de tri.
	 *     @type string $scope global|category.
	 * }
	 * @return string|null HTML du carrousel ou null si aucun avis trouvé.
	 */
	public static function slider( $args = array() ) {
		$defaults      = array(
			'limit'    => 12,
			'sort'     => 'rating_desc',
			'scope'    => 'global',
			'term_id'  => 0,
			'taxonomy' => '',
			'mode'     => '',
		);
		$args          = wp_parse_args( $args, $defaults );
		$args['limit'] = max( 1, (int) $args['limit'] );
		$args['mode']  = sanitize_key( (string) ( $args['mode'] ?? '' ) );
		$scope         = sanitize_key( (string) ( $args['scope'] ?? 'global' ) );

		$sort_args = Genius_Reviews_Query_Helper::map_sort( $args['sort'] );

		if ( $scope === 'category' ) {
			return self::category_slider( $args, $sort_args );
		}

		$q_args = array(
			'post_type'      => 'genius_review',
			'posts_per_page' => $args['limit'],
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_gr_curated',
					'value'   => 'ok',
					'compare' => '=',
				),
			),
		);

		if ( ! empty( $sort_args['meta_query'] ) ) {
			$q_args['meta_query'] = array_merge( $q_args['meta_query'], (array) $sort_args['meta_query'] );
			unset( $sort_args['meta_query'] );
		}

		$q_args = array_merge( $q_args, $sort_args );
		$q      = new WP_Query( $q_args );

		$stats = Genius_Reviews_Query_Helper::get_global_stats();
		$avg   = $stats['avg'];
		$count = $stats['count'];

		if ( $count < 1 ) {
			return;
		}

		$html = self::render_carousel( $q, $avg, $count, $args );

		return $html;
	}

	/**
	 * Génère un carrousel ciblé sur la catégorie courante avec appoint d'avis produits.
	 *
	 * @param array $args
	 * @param array $sort_args
	 * @return string|null
	 */
	private static function category_slider( $args, $sort_args ) {
		$term = self::resolve_badge_term( $args );
		if ( ! $term instanceof WP_Term ) {
			return self::product_slider( $args, $sort_args );
		}

		$limit          = max( 3, (int) $args['limit'] );
		$product_ids    = self::get_slider_term_product_ids( $term );
		$category_stats = self::get_slider_term_stats( $product_ids );

		$category_review_ids = array();
		if ( ! empty( $product_ids ) ) {
			$category_review_ids = self::get_slider_review_ids(
				$limit,
				$sort_args,
				array(
					array(
						'key'     => '_gr_product_id',
						'value'   => $product_ids,
						'compare' => 'IN',
					),
				)
			);
		}

		$review_ids               = $category_review_ids;
		$use_global_product_stats = false;
		if ( empty( $category_review_ids ) ) {
			$product_review_ids       = self::get_slider_review_ids(
				$limit,
				$sort_args,
				self::get_slider_product_meta_query()
			);
			$review_ids               = $product_review_ids;
			$use_global_product_stats = true;
		} elseif ( count( $review_ids ) < 3 ) {
			$product_review_ids = self::get_slider_review_ids(
				3 - count( $review_ids ),
				$sort_args,
				self::get_slider_product_meta_query(),
				$review_ids
			);
			$review_ids         = array_merge( $review_ids, $product_review_ids );
		}

		if ( empty( $review_ids ) ) {
			return;
		}

		if ( $use_global_product_stats ) {
			$stats = self::get_slider_product_stats();
			if ( (int) ( $stats['count'] ?? 0 ) < 1 ) {
				$stats = self::get_review_ids_stats( $review_ids );
			}
		} elseif ( (int) ( $category_stats['count'] ?? 0 ) > 0 ) {
			$stats = $category_stats;
		} else {
			$stats = self::get_review_ids_stats( $review_ids );
		}

		$avg   = (float) ( $stats['avg'] ?? 0 );
		$count = (int) ( $stats['count'] ?? 0 );
		if ( $count < 1 || $avg <= 0 ) {
			return;
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'genius_review',
				'posts_per_page' => count( $review_ids ),
				'post_status'    => 'publish',
				'post__in'       => $review_ids,
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
			)
		);

		return self::render_carousel( $q, $avg, $count, $args );
	}

	/**
	 * Génère un carrousel d'avis produits globaux.
	 *
	 * @param array $args
	 * @param array $sort_args
	 * @return string|null
	 */
	private static function product_slider( $args, $sort_args ) {
		$limit      = max( 1, (int) ( $args['limit'] ?? 12 ) );
		$review_ids = self::get_slider_review_ids(
			$limit,
			$sort_args,
			self::get_slider_product_meta_query()
		);

		if ( empty( $review_ids ) ) {
			return;
		}

		$stats = self::get_slider_product_stats();
		if ( (int) ( $stats['count'] ?? 0 ) < 1 ) {
			$stats = self::get_review_ids_stats( $review_ids );
		}

		$avg   = (float) ( $stats['avg'] ?? 0 );
		$count = (int) ( $stats['count'] ?? 0 );
		if ( $count < 1 || $avg <= 0 ) {
			return;
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'genius_review',
				'posts_per_page' => count( $review_ids ),
				'post_status'    => 'publish',
				'post__in'       => $review_ids,
				'orderby'        => 'post__in',
				'no_found_rows'  => true,
			)
		);

		return self::render_carousel( $q, $avg, $count, $args );
	}


	/**
	 * Affiche un petit badge de note pour un produit donné.
	 *
	 * Montre la moyenne d’étoiles et le nombre total d’avis.
	 *
	 * @param array $args {
	 *     @type int $product_id ID du produit concerné.
	 *     @type int $use_global_count Affiche les stats globales (boutique) si activé.
	 * }
	 * @return string HTML du badge, vide si aucune donnée.
	 */
	public static function badge( $args = array() ) {
		$defaults = array(
			'product_id'       => 0,
			'use_global_count' => 0,
			'scope'            => 'product',
			'term_id'          => 0,
			'taxonomy'         => '',
			'mode'             => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$mode  = sanitize_key( (string) $args['mode'] );
		$scope = sanitize_key( (string) $args['scope'] );

		if ( $scope === 'category' ) {
			$term = self::resolve_badge_term( $args );
			if ( ! $term instanceof WP_Term || ! is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'get_term_stats' ) ) ) {
				return '';
			}

			$stats = Genius_Reviews_Term_Schema_Cache::get_term_stats( $term );
			$avg   = (float) ( $stats['avg'] ?? 0 );
			$count = (int) ( $stats['count'] ?? 0 );

			if ( $count < 1 || $avg <= 0 ) {
				return '';
			}

			return self::render_badge( $count, $avg, false, $mode );
		}

		$allow_global_fallback = ! empty( $args['use_global_count'] );
		$product_id            = (int) $args['product_id'];

		if ( ! $product_id && ! $allow_global_fallback ) {
			return '';
		}

		if ( $product_id ) {
			$stats = Genius_Reviews_Query_Helper::get_product_stats( $product_id );
			$avg   = (float) ( $stats['avg'] ?? 0 );
			$count = (int) ( $stats['count'] ?? 0 );

			if ( $count > 0 && $avg > 0 ) {
				return self::render_badge( $count, $avg, false, $mode );
			}
		}

		if ( ! $allow_global_fallback ) {
			return '';
		}

		$stats = Genius_Reviews_Query_Helper::get_global_stats();
		$avg   = $stats['avg'];
		$count = $stats['count'];
		if ( empty( $avg ) || empty( $count ) ) {
			return '';
		}

		return self::render_badge( $count, $avg, true, $mode );
	}

	/**
	 * Resolve term for category badge.
	 *
	 * @param array $args
	 * @return WP_Term|null
	 */
	private static function resolve_badge_term( $args ) {
		$term_id  = (int) ( $args['term_id'] ?? 0 );
		$taxonomy = sanitize_key( (string) ( $args['taxonomy'] ?? '' ) );

		if ( $term_id > 0 && $taxonomy !== '' ) {
			$term = get_term( $term_id, $taxonomy );
			return $term instanceof WP_Term ? $term : null;
		}

		$queried = get_queried_object();
		if ( $queried instanceof WP_Term && ( $queried->taxonomy === 'product_cat' || strpos( $queried->taxonomy, 'pa_' ) === 0 ) ) {
			return $queried;
		}

		return null;
	}

	/**
	 * Retourne les produits publiés d'un terme, avec cache statique par requête.
	 *
	 * @param WP_Term $term
	 * @return int[]
	 */
	private static function get_slider_term_product_ids( WP_Term $term ) {
		$cache_key = $term->taxonomy . ':' . (int) $term->term_id;
		if ( isset( self::$term_product_ids_cache[ $cache_key ] ) ) {
			return self::$term_product_ids_cache[ $cache_key ];
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => array( (int) $term->term_id ),
					),
				),
			)
		);

		self::$term_product_ids_cache[ $cache_key ] = array_map( 'intval', $query->posts );

		return self::$term_product_ids_cache[ $cache_key ];
	}

	/**
	 * Calcule les stats d'une catégorie depuis les stats produit Genius Reviews.
	 *
	 * @param int[] $product_ids
	 * @return array
	 */
	private static function get_slider_term_stats( $product_ids ) {
		global $wpdb;

		$product_ids = array_values( array_filter( array_unique( array_map( 'intval', (array) $product_ids ) ) ) );
		if ( empty( $product_ids ) ) {
			return array(
				'avg'   => 0,
				'count' => 0,
			);
		}

		$weighted_total = 0.0;
		$review_count   = 0;

		foreach ( array_chunk( $product_ids, 500 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$rows         = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT avg_meta.meta_value AS avg_rating, count_meta.meta_value AS review_count
                    FROM {$wpdb->posts} AS p
                    INNER JOIN {$wpdb->postmeta} AS avg_meta
                        ON avg_meta.post_id = p.ID AND avg_meta.meta_key = '_gr_avg_rating'
                    INNER JOIN {$wpdb->postmeta} AS count_meta
                        ON count_meta.post_id = p.ID AND count_meta.meta_key = '_gr_review_count'
                    WHERE p.ID IN ({$placeholders})
                      AND p.post_type = 'product'
                      AND p.post_status = 'publish'",
					$chunk
				),
				ARRAY_A
			);

			foreach ( (array) $rows as $row ) {
				$avg   = (float) ( $row['avg_rating'] ?? 0 );
				$count = (int) ( $row['review_count'] ?? 0 );

				if ( $avg <= 0 || $count <= 0 ) {
					continue;
				}

				$weighted_total += $avg * $count;
				$review_count   += $count;
			}
		}

		return array(
			'avg'   => $review_count ? round( $weighted_total / $review_count, 2 ) : 0,
			'count' => $review_count,
		);
	}

	/**
	 * Calcule les stats des avis produits curés.
	 *
	 * @return array
	 */
	private static function get_slider_product_stats() {
		global $wpdb;

		$ratings = $wpdb->get_col(
			"SELECT rating_meta.meta_value
            FROM {$wpdb->posts} AS p
            INNER JOIN {$wpdb->postmeta} AS rating_meta
                ON rating_meta.post_id = p.ID AND rating_meta.meta_key = '_gr_rating'
            INNER JOIN {$wpdb->postmeta} AS curated_meta
                ON curated_meta.post_id = p.ID AND curated_meta.meta_key = '_gr_curated'
            INNER JOIN {$wpdb->postmeta} AS product_meta
                ON product_meta.post_id = p.ID AND product_meta.meta_key = '_gr_product_id'
            WHERE p.post_type = 'genius_review'
              AND p.post_status = 'publish'
              AND curated_meta.meta_value = 'ok'
              AND CAST(product_meta.meta_value AS UNSIGNED) > 0"
		);

		$ratings = array_filter(
			array_map( 'floatval', (array) $ratings ),
			function ( $rating ) {
				return $rating > 0;
			}
		);
		$count   = count( $ratings );

		return array(
			'avg'   => $count ? round( array_sum( $ratings ) / $count, 2 ) : 0,
			'count' => $count,
		);
	}

	/**
	 * Meta query des avis produits hors filtre catégorie.
	 *
	 * @return array
	 */
	private static function get_slider_product_meta_query() {
		return array(
			array(
				'key'     => '_gr_product_id',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			),
		);
	}

	/**
	 * Retourne uniquement les IDs d'avis curés pour le slider.
	 *
	 * @param int   $limit
	 * @param array $sort_args
	 * @param array $extra_meta_query
	 * @param int[] $exclude_ids
	 * @return int[]
	 */
	private static function get_slider_review_ids( $limit, $sort_args, $extra_meta_query = array(), $exclude_ids = array() ) {
		$limit = max( 0, (int) $limit );
		if ( $limit < 1 ) {
			return array();
		}

		$meta_query = array(
			array(
				'key'     => '_gr_curated',
				'value'   => 'ok',
				'compare' => '=',
			),
		);
		$meta_query = array_merge( $meta_query, (array) $extra_meta_query );

		if ( ! empty( $sort_args['meta_query'] ) ) {
			$meta_query = array_merge( $meta_query, (array) $sort_args['meta_query'] );
			unset( $sort_args['meta_query'] );
		}

		$q_args = array_merge(
			array(
				'post_type'              => 'genius_review',
				'posts_per_page'         => $limit,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query,
			),
			$sort_args
		);

		$exclude_ids = array_values( array_filter( array_map( 'intval', (array) $exclude_ids ) ) );
		if ( ! empty( $exclude_ids ) ) {
			$q_args['post__not_in'] = $exclude_ids;
		}

		$query = new WP_Query( $q_args );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Calcule les stats sur une petite sélection d'avis affichés.
	 *
	 * @param int[] $review_ids
	 * @return array
	 */
	private static function get_review_ids_stats( $review_ids ) {
		$total = 0.0;
		$count = 0;

		foreach ( array_map( 'intval', (array) $review_ids ) as $review_id ) {
			$rating = (float) get_post_meta( $review_id, '_gr_rating', true );
			if ( $rating > 0 ) {
				$total += $rating;
				++$count;
			}
		}

		return array(
			'avg'   => $count ? round( $total / $count, 2 ) : 0,
			'count' => $count,
		);
	}

	/**
	 * Affiche un résumé compact réutilisable.
	 *
	 * @param array $args {
	 *     @type int    $product_id    ID du produit.
	 *     @type string $scope         global|product
	 *     @type int    $stars         Affiche les étoiles.
	 *     @type int    $average       Affiche la moyenne.
	 *     @type int    $count         Affiche le compteur.
	 *     @type string $separator     Texte entre moyenne et compteur.
	 *     @type string $count_prefix  Texte avant le nombre d'avis.
	 *     @type string $count_suffix  Texte après le nombre d'avis.
	 * }
	 * @return string
	 */
	public static function summary( $args = array() ) {
		$defaults = array(
			'product_id'   => 0,
			'scope'        => 'global',
			'stars'        => 1,
			'average'      => 1,
			'count'        => 1,
			'separator'    => 'sur',
			'count_prefix' => '+ de',
			'count_suffix' => __( 'avis vérifiés', 'genius-reviews' ),
		);

		$args = wp_parse_args( $args, $defaults );

		$scope        = sanitize_key( (string) $args['scope'] );
		$product_id   = (int) $args['product_id'];
		$show_stars   = ! empty( $args['stars'] );
		$show_average = ! empty( $args['average'] );
		$show_count   = ! empty( $args['count'] );
		$separator    = sanitize_text_field( (string) $args['separator'] );
		$count_prefix = sanitize_text_field( (string) $args['count_prefix'] );
		$count_suffix = sanitize_text_field( (string) $args['count_suffix'] );

		if ( $scope === 'product' && $product_id > 0 ) {
			$stats = Genius_Reviews_Query_Helper::get_product_stats( $product_id );
		} else {
			$stats = Genius_Reviews_Query_Helper::get_global_stats();
		}

		$avg   = (float) ( $stats['avg'] ?? 0 );
		$count = (int) ( $stats['count'] ?? 0 );

		if ( $avg <= 0 || $count < 1 ) {
			return '';
		}

		return self::render_summary(
			$avg,
			$count,
			array(
				'show_stars'   => $show_stars,
				'show_average' => $show_average,
				'show_count'   => $show_count,
				'separator'    => $separator,
				'count_prefix' => $count_prefix,
				'count_suffix' => $count_suffix,
			)
		);
	}

	/**
	 * Affiche une grille complète avec onglets :
	 * - Avis Produits (product_id > 0)
	 * - Avis Boutique (product_id = 0)
	 *
	 * @param array $args
	 * @return string
	 */
	public static function grid_all_reviews( $args = array() ) {
		$defaults = array(
			'limit'          => 12,
			'sort'           => 'date_desc',
			'remove_spacing' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		if ( isset( $_GET['gr-sort'] ) ) {
			$args['sort'] = sanitize_text_field( $_GET['gr-sort'] );
		}

		$sort_args = Genius_Reviews_Query_Helper::map_sort( $args['sort'] );

		$base_meta = array(
			array(
				'key'     => '_gr_curated',
				'value'   => 'ok',
				'compare' => '=',
			),
		);

		$meta_products = array_merge(
			$base_meta,
			array(
				array(
					'key'     => '_gr_product_id',
					'value'   => 0,
					'compare' => '>',
				),
			)
		);

		$meta_shop = array_merge(
			$base_meta,
			array(
				array(
					'key'     => '_gr_product_id',
					'value'   => 0,
					'compare' => '=',
				),
			)
		);

		if ( ! empty( $sort_args['meta_query'] ) ) {
			$meta_products = array_merge( $meta_products, (array) $sort_args['meta_query'] );
			$meta_shop     = array_merge( $meta_shop, (array) $sort_args['meta_query'] );
			unset( $sort_args['meta_query'] );
		}

		$q_products = new WP_Query(
			array_merge(
				array(
					'post_type'      => 'genius_review',
					'posts_per_page' => $args['limit'],
					'post_status'    => 'publish',
					'meta_query'     => $meta_products,
				),
				$sort_args
			)
		);

		$q_shop = new WP_Query(
			array_merge(
				array(
					'post_type'      => 'genius_review',
					'posts_per_page' => $args['limit'],
					'post_status'    => 'publish',
					'meta_query'     => $meta_shop,
				),
				$sort_args
			)
		);

		$stats            = Genius_Reviews_Query_Helper::get_global_stats();
		$avg              = $stats['avg'];
		$count            = $stats['count'];
		$counts_by_rating = $stats['counts_by_rating'];

		$args['mode'] = 'all';

		return self::render_grid(
			array(
				'q_products' => $q_products,
				'q_shop'     => $q_shop,
			),
			$avg,
			$count,
			$args,
			$counts_by_rating
		);
	}




	/**
	 * Construit une carte d’avis individuelle.
	 *
	 * Utilisé dans la grille et le carrousel.
	 * Contient les étoiles, le titre, l’extrait, le nom et la date.
	 *
	 * @param int    $post_id ID du post (avis).
	 * @param string $mode    Mode d’affichage ("grid" ou "slider").
	 * @return string HTML complet de la carte.
	 */
	public static function review_card( $post_id, $mode = '' ) {
		$rating           = (int) get_post_meta( $post_id, '_gr_rating', true );
		$reviewer         = get_post_meta( $post_id, '_gr_reviewer_name', true );
		$date             = get_post_meta( $post_id, '_gr_review_date', true );
		$title            = get_post_meta( $post_id, '_gr_display_title', true );
		$content          = get_the_content( $post_id );
		$product_id       = (int) get_post_meta( $post_id, '_gr_product_id', true );
		$picture_urls_raw = get_post_meta( $post_id, '_gr_picture_urls', true );
		$picture_urls     = array();

		if ( is_array( $picture_urls_raw ) ) {
			foreach ( $picture_urls_raw as $picture_url ) {
				if ( is_string( $picture_url ) && filter_var( $picture_url, FILTER_VALIDATE_URL ) ) {
					$picture_urls[] = $picture_url;
				}
			}
		}

		ob_start(); ?>
		<div class="gr-review-card">
			<div class="gr-review-card-header">
				<div class="gr-review-card-stars">
					<?php echo self::render_stars( $rating, 'gr-review-card-star', 18 ); ?>
				</div>
				<div class="gr-review-card-date">
					<?php
					if ( $date ) {
						$timestamp = strtotime( $date );
						if ( $timestamp ) {
							printf(
								__( 'Il y a %s', 'genius-reviews' ),
								human_time_diff( $timestamp, current_time( 'timestamp' ) )
							);
						} else {
							echo esc_html( $date );
						}
					}
					?>
				</div>
			</div>
			<?php if ( $mode === 'all' && $product_id > 0 ) : ?>
				<?php $product = wc_get_product( $product_id ); ?>
				<?php if ( $product ) : ?>
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="gr-review-card-product">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $title !== '' ) : ?>
				<?php if ( $product_id > 0 ) : ?>
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>" class="gr-review-card-title">
						<?php echo esc_html( $title ); ?>
					</a>
				<?php else : ?>
					<p class="gr-review-card-title"><?php echo esc_html( $title ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $mode !== 'slider' && count( $picture_urls ) > 1 ) : ?>
				<div class="gr-review-card-gallery">
					<?php foreach ( $picture_urls as $picture_url ) : ?>
						<img src="<?php echo esc_url( $picture_url ); ?>" alt="<?php echo esc_attr( $title ); ?>"
							class="gr-review-card-thumb" loading="lazy" />
					<?php endforeach; ?>
				</div>
			<?php elseif ( $mode !== 'slider' && count( $picture_urls ) === 1 ) : ?>
				<div class="gr-review-card-media">
					<img src="<?php echo esc_url( $picture_urls[0] ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="review-img"
						loading="lazy" />
				</div>
			<?php elseif ( $mode === 'grid' && has_post_thumbnail( $post_id ) ) : ?>
				<div class="gr-review-card-media">
					<?php
					echo get_the_post_thumbnail(
						$post_id,
						'medium',
						array( 'class' => 'review-img' )
					);
					?>
				</div>
			<?php endif; ?>
			<?php if ( $mode === 'slider' ) : ?>
				<?php
				$limit           = 120;
				$truncated       = mb_strlen( $content ) > $limit;
				$excerpt_display = $truncated ? mb_substr( $content, 0, $limit ) . '…' : $content;
				?>
				<p class="gr-review-card-excerpt gr-excerpt">
					<?php echo esc_html( $excerpt_display ); ?>
					<?php if ( $truncated ) : ?>
						<span class="gr-review-card-full-text gr-full-text"><?php echo esc_html( $content ); ?></span>
						<button type="button" class="gr-review-card-read-more gr-read-more">
							<?php _e( 'Voir plus', 'genius-reviews' ); ?>
						</button>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p class="gr-review-card-content"><?php echo esc_html( $content ); ?></p>
			<?php endif; ?>
			<p class="gr-review-card-author">
				<?php echo esc_html( $reviewer ?: __( 'Client', 'genius-reviews' ) ); ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Rendu HTML complet de la grille des avis.
	 *
	 * Génère les statistiques, les barres de répartition, le tri,
	 * le bouton “voir plus” et la liste des avis.
	 *
	 * @param $query            Objet WP_Query des avis.
	 * @param float                                    $avg              Note moyenne.
	 * @param int                                      $count            Nombre total d’avis.
	 * @param array                                    $args             Paramètres initiaux de la grille.
	 * @param array                                    $counts_by_rating Détails par note.
	 * @return string HTML du bloc.
	 */
	private static function render_grid( $query, $avg, $count, $args, $counts_by_rating ) {
		$is_all_mode = isset( $args['mode'] ) && $args['mode'] === 'all' && is_array( $query );

		ob_start();
		$counts_by_rating = isset( $counts_by_rating ) ? $counts_by_rating : array();
		$total            = max( 1, array_sum( $counts_by_rating ) );
		?>

		<?php
		$grid_classes = 'gr-bloc !space-y-8.5';
		if ( empty( $args['remove_spacing'] ) ) {
			$grid_classes .= ' !max-w-[1260px] !mx-auto !p-4 md:!p-12.5';
		}
		?>
		<div class="<?php echo esc_attr( $grid_classes ); ?>">
			<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
				<div>
					<div class="flex flex-col gap-2">
						<span class="text-lg font-bold"><?php esc_html_e( 'Avis de nos clients', 'genius-reviews' ); ?></span>
						<div class="flex gap-2 items-center">
							<div class="flex gap-0.5">
								<?php
								$rounded_avg = max( 1, min( 5, (int) round( $avg ) ) );
								echo self::render_stars( $rounded_avg, '', 22 );
								?>
							</div>
							<span class="text-base font-medium"><?php echo number_format( $avg, 1 ); ?></span>
						</div>
					</div>
					<p class="text-base">
						<?php
						$label = sprintf(
							__( 'Basé sur %1$s%3$s avis%2$s', 'genius-reviews' ),
							'<span class="font-bold">',
							'</span>',
							number_format_i18n( (int) $count )
						);
						echo wp_kses_post( $label );
						?>
					</p>
				</div>
				<div class="space-y-0.5">
					<div class="space-y-0.5">
						<?php $default_star_colors = self::default_star_colors(); ?>
						<?php
						for ( $i = 5; $i >= 1; $i-- ) :
							$percent   = ( $counts_by_rating[ $i ] / $total ) * 100;
							$bar_style = 'width: ' . $percent . '%; background-color: var(--color-star-' . $i . '-custom, ' . $default_star_colors[ $i ] . ');';
							?>
							<div class="flex items-center gap-2.5">
								<div class="flex gap-0.5">
									<?php for ( $j = 1; $j <= 5; $j++ ) : ?>
										<?php echo self::render_star_svg( $j <= $i, $i, '', 16 ); ?>
									<?php endfor; ?>
								</div>
								<div class="flex-1 h-3 w-[126px] bg-gray-light rounded-full overflow-hidden">
									<div class="h-full rounded-full" style="<?php echo esc_attr( $bar_style ); ?>"></div>
								</div>

								<div class="w-6 text-right text-gray-medium text-sm">
									<?php echo $counts_by_rating[ $i ]; ?>
								</div>
							</div>
						<?php endfor; ?>
					</div>
				</div>
				<?php
				$lang        = substr( get_locale(), 0, 2 );
				$gr_slugs    = array(
					'fr' => 'questionnaire-feedback',
					'nl' => 'feedbackvragenlijst',
					'de' => 'fragebogen-feedback',
					'it' => 'questionario-di-feedback',
					'es' => 'cuestionario-de-opinion',
					'sv' => 'frageformular-feedback',
					'pl' => 'kwestionariusz-feedback',
				);
				$target_slug = isset( $gr_slugs[ $lang ] ) ? $gr_slugs[ $lang ] : $gr_slugs['fr'];
				$target_url  = home_url( '/' . $target_slug . '/' );
				?>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( $target_url ); ?>" class="gr-btn bg-brand-custom hover:bg-brand-custom-hover">
						<?php _e( 'Écrire un avis', 'genius-reviews' ); ?>
					</a>
				<?php else : ?>
					<?php
					$login_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( get_permalink() );
					?>
					<p class="text-sm text-gray-500 mt-4">
						<?php
						echo wp_kses_post(
							sprintf(
								__( 'Vous devez être <a href="%s" class="text-brand-custom hover:underline font-medium">connecté(e)</a> pour écrire un avis.', 'genius-reviews' ),
								esc_url( $login_url )
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<form method="get">
				<div class="flex justify-end relative">
					<select id="gr-sort" name="gr-sort" onchange="this.form.submit()"
						class="!appearance-none !bg-none !rounded-lg !border-none !h-auto !pl-10 !p-3.5 !shadow-lg !text-lg !font-bold !w-full">
						<option value="date_desc" <?php selected( $args['sort'], 'date_desc' ); ?>>
							<?php _e( 'Plus récents', 'genius-reviews' ); ?>
						</option>
						<option value="date_asc" <?php selected( $args['sort'], 'date_asc' ); ?>>
							<?php _e( 'Plus anciens', 'genius-reviews' ); ?>
						</option>
						<option value="rating_desc" <?php selected( $args['sort'], 'rating_desc' ); ?>>
							<?php _e( 'Meilleures notes', 'genius-reviews' ); ?>
						</option>
						<option value="rating_asc" <?php selected( $args['sort'], 'rating_asc' ); ?>>
							<?php _e( 'Moins bonnes notes', 'genius-reviews' ); ?>
						</option>
					</select>
					<span class=" pointer-events-none absolute left-3 top-1/2 translate-y-[-70%]">
						<svg xmlns="http://www.w3.org/2000/svg" width="17" height="12" viewBox="0 0 17 12" fill="none">
							<path
								d="M0 1C0 0.801088 0.0790175 0.610322 0.21967 0.46967C0.360322 0.329018 0.551088 0.25 0.75 0.25H15.75C15.9489 0.25 16.1397 0.329018 16.2803 0.46967C16.421 0.610322 16.5 0.801088 16.5 1C16.5 1.19891 16.421 1.38968 16.2803 1.53033C16.1397 1.67098 15.9489 1.75 15.75 1.75H0.75C0.551088 1.75 0.360322 1.67098 0.21967 1.53033C0.0790175 1.38968 0 1.19891 0 1ZM2.5 6C2.5 5.80109 2.57902 5.61032 2.71967 5.46967C2.86032 5.32902 3.05109 5.25 3.25 5.25H13.25C13.4489 5.25 13.6397 5.32902 13.7803 5.46967C13.921 5.61032 14 5.80109 14 6C14 6.19891 13.921 6.38968 13.7803 6.53033C13.6397 6.67098 13.4489 6.75 13.25 6.75H3.25C3.05109 6.75 2.86032 6.67098 2.71967 6.53033C2.57902 6.38968 2.5 6.19891 2.5 6ZM5.5 11C5.5 10.8011 5.57902 10.6103 5.71967 10.4697C5.86032 10.329 6.05109 10.25 6.25 10.25H10.25C10.4489 10.25 10.6397 10.329 10.7803 10.4697C10.921 10.6103 11 10.8011 11 11C11 11.1989 10.921 11.3897 10.7803 11.5303C10.6397 11.671 10.4489 11.75 10.25 11.75H6.25C6.05109 11.75 5.86032 11.671 5.71967 11.5303C5.57902 11.3897 5.5 11.1989 5.5 11Z"
								fill="black" />
						</svg>
					</span>
					<span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
						<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 10 10" fill="none">
							<g clip-path="url(#clip0_5_3880)">
								<path
									d="M5.00009 7.85059C5.17925 7.85059 5.35845 7.78218 5.49508 7.64567L9.79486 3.34579C10.0684 3.07226 10.0684 2.6288 9.79486 2.35538C9.52145 2.08197 9.07807 2.08197 8.80452 2.35538L5.00009 6.1601L1.19551 2.35565C0.922099 2.08224 0.478653 2.08224 0.205264 2.35565C-0.0683911 2.62906 -0.0683911 3.0724 0.205264 3.34592L4.50497 7.64583C4.64168 7.78231 4.82087 7.85059 5.00009 7.85059Z"
									fill="black" />
							</g>
							<defs>
								<clipPath id="clip0_5_3880">
									<rect width="10" height="10" fill="white"
										transform="matrix(-4.37114e-08 -1 -1 4.37114e-08 10 10)" />
								</clipPath>
							</defs>
						</svg>
					</span>
				</div>
			</form>
			<?php if ( $is_all_mode ) : ?>
				<?php
				$q_products     = $query['q_products'];
				$q_shop         = $query['q_shop'];
				$total_products = $q_products->found_posts;
				$total_shop     = $q_shop->found_posts;
				?>
				<div class="flex gap-4 border-b border-gray-200 mb-6">
					<button class="gr-tab !bg-transparent text-brand-custom hover:text-brand-custom-hover gr-tab-active"
						data-tab="products">
						<?php
						echo wp_kses_post(
							sprintf(
								__( 'Avis sur Produits (%s)', 'genius-reviews' ),
								number_format_i18n( (int) $q_products->found_posts )
							)
						);
						?>
					</button>
					<button class="gr-tab !bg-transparent text-brand-custom hover:text-brand-custom-hover" data-tab="shop">
						<?php
						echo wp_kses_post(
							sprintf(
								__( 'Avis sur Boutique (%s)', 'genius-reviews' ),
								number_format_i18n( (int) $q_shop->found_posts )
							)
						);
						?>
					</button>
				</div>
				<div id="gr-tab-products" class="gr-tab-content">
					<?php self::render_grid_inner( $q_products, $args ); ?>
				</div>
				<div id="gr-tab-shop" class="gr-tab-content hidden">
					<?php self::render_grid_inner( $q_shop, $args ); ?>
				</div>
				<?php if ( $total_products > ( $args['limit'] ?? 12 ) || $total_shop > ( $args['limit'] ?? 12 ) ) : ?>
					<div class="flex justify-center mt-6">
						<button id="gr-load-more" class="gr-btn bg-brand-custom hover:bg-brand-custom-hover"
							data-limit="<?php echo esc_attr( $args['limit'] ); ?>"
							data-total-products="<?php echo esc_attr( $total_products ); ?>"
							data-total-shop="<?php echo esc_attr( $total_shop ); ?>" data-mode="all_reviews">
							<?php _e( 'Voir plus d’avis', 'genius-reviews' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div id="gr-tab-products" class="gr-tab-content">
					<?php self::render_grid_inner( $query, $args ); ?>
				</div>
				<?php if ( $count > ( $args['limit'] ?? 12 ) ) : ?>
					<div class="flex justify-center mt-6">
						<button id="gr-load-more" class="gr-btn bg-brand-custom hover:bg-brand-custom-hover"
							data-limit="<?php echo esc_attr( $args['limit'] ); ?>" data-total-products="<?php echo esc_attr( $count ); ?>"
							data-total-shop="0">
							<?php _e( 'Voir plus d’avis', 'genius-reviews' ); ?>
						</button>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}



	/**
	 * Rendu HTML du carrousel d’avis.
	 *
	 * Construit la structure compatible Swiper.js avec les flèches et slides.
	 *
	 * @param WP_Query $query Objet WP_Query des avis.
	 * @param float    $avg   Moyenne des notes.
	 * @param int      $count Nombre total d’avis.
	 * @return string HTML du carrousel complet.
	 */
	private static function render_carousel( WP_Query $query, $avg, $count, $args = array() ) {
		ob_start();
		$mode             = sanitize_key( (string) ( $args['mode'] ?? '' ) );
		$carousel_classes = 'gr-bloc gr-carousel';
		if ( $mode === 'compact' ) {
			$carousel_classes .= ' gr-carousel-compact';
		}

		if ( ! $query->have_posts() ) {
			echo '<div class="gr-bloc text-center !p-6">';
			echo '<p class="text-gray-500">' . __( 'Aucun avis disponible.', 'genius-reviews' ) . '</p>';
			echo '</div>';
			return ob_get_clean();
		}
		?>

		<div class="<?php echo esc_attr( $carousel_classes ); ?>">
			<div class="gr-carousel-rating">
				<span class="gr-carousel-label"><?php echo esc_html( self::rating_label( $avg ) ); ?></span>
				<div class="gr-carousel-stars">
					<?php
					$rounded_avg = max( 1, min( 5, (int) round( $avg ) ) );
					echo self::render_stars( $rounded_avg, '', 22 );
					?>
				</div>
				<span class="gr-carousel-count">
					<?php
					$label = sprintf(
						__( 'Basé sur %1$s%3$s avis%2$s', 'genius-reviews' ),
						'<span class="font-bold">',
						'</span>',
						number_format_i18n( (int) $count )
					);
					echo wp_kses_post( $label );
					?>
				</span>
			</div>
			<div class="gr-carousel-slider">
				<div class="splide gr-splide" data-mode="<?php echo esc_attr( $mode ); ?>">
					<div class="splide__track">
						<ul class="splide__list">
							<?php
							while ( $query->have_posts() ) :
								$query->the_post();
								?>
								<li class="splide__slide gr-carousel-slide">
									<?php echo self::review_card( get_the_ID(), 'slider' ); ?>
								</li>
								<?php
							endwhile;
							wp_reset_postdata();
							?>
						</ul>
					</div>
					<div class="gr-splide-button gr-splide-button-prev">
						<svg xmlns="http://www.w3.org/2000/svg" width="6" height="11" viewBox="0 0 6 11" fill="none"
							class="gr-splide-button-icon">
							<path d="M5.21641 9.45946L1.16235 5.4054L5.21641 1.35135" stroke-width="1.5" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</div>
					<div class="gr-splide-button gr-splide-button-next">
						<svg xmlns="http://www.w3.org/2000/svg" width="6" height="11" viewBox="0 0 6 11" fill="none"
							class="gr-splide-button-icon gr-splide-button-icon-next">
							<path d="M5.21641 9.45946L1.16235 5.4054L5.21641 1.35135" stroke-width="1.5" stroke-linecap="round"
								stroke-linejoin="round" />
						</svg>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Rendu du badge de note (étoiles + nombre d’avis).
	 *
	 * Utilisé sur les pages produits et collections.
	 *
	 * @param int    $count     Nombre total d’avis.
	 * @param float  $avg       Note moyenne du produit.
	 * @param bool   $is_global Badge boutique.
	 * @param string $mode     Variante d'affichage.
	 * @return string HTML du badge.
	 */
	private static function render_badge( $count, $avg, $is_global = false, $mode = '' ) {
		$mode        = sanitize_key( (string) $mode );
		$rounded_avg = max( 1, min( 5, round( (float) $avg ) ) );

		ob_start();
		if ( $mode === 'compact_rating' ) {
			$rating_label = self::rating_label( (float) $avg );
			?>
			<div class="gr-badge gr-badge-compact-rating" aria-label="<?php echo esc_attr( sprintf( __( 'Note moyenne de %1$s sur 5 basee sur %2$s avis', 'genius-reviews' ), number_format_i18n( (float) $avg, 1 ), number_format_i18n( (int) $count ) ) ); ?>">
				<span class="gr-badge-rating-label">
					<?php echo esc_html( sprintf( '%s %s', $rating_label, number_format_i18n( (float) $avg, 1 ) ) ); ?>
				</span>
				<div class="gr-badge-stars" aria-hidden="true">
					<?php echo self::render_stars( $rounded_avg, 'gr-badge-star-size', 16 ); ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
		?>
		<div class="gr-badge">
			<div class="gr-badge-stars">
				<?php echo self::render_stars( $rounded_avg, 'gr-badge-star-size', 16 ); ?>
			</div>
			<span class="gr-badge-count">
				<?php
				$label = sprintf(
					_n( '%s avis', '%s avis', (int) $count, 'genius-reviews' ),
					number_format_i18n( (int) $count )
				);
				if ( $is_global ) {
					$label .= ' (' . __( 'Boutique', 'genius-reviews' ) . ')';
				}
				echo esc_html( $label );
				?>
			</span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Rendu compact réutilisable.
	 *
	 * @param float $avg
	 * @param int   $count
	 * @param array $args
	 * @return string
	 */
	private static function render_summary( $avg, $count, $args = array() ) {
		$defaults = array(
			'show_stars'   => true,
			'show_average' => true,
			'show_count'   => true,
			'separator'    => 'sur',
			'count_prefix' => '+ de',
			'count_suffix' => __( 'avis vérifiés', 'genius-reviews' ),
		);

		$args            = wp_parse_args( $args, $defaults );
		$rounded_avg     = max( 1, min( 5, round( $avg ) ) );
		$formatted_avg   = number_format_i18n( $avg, 1 );
		$formatted_count = number_format_i18n( $count );

		ob_start();
		?>
		<span class="gr-summary-rating" aria-label="<?php echo esc_attr( sprintf( __( 'Note moyenne de %1$s sur 5 basee sur %2$s avis', 'genius-reviews' ), $formatted_avg, $formatted_count ) ); ?>">
			<?php if ( ! empty( $args['show_stars'] ) ) : ?>
				<span class="gr-summary-rating-stars" aria-hidden="true">
					<?php echo self::render_stars( $rounded_avg, 'gr-summary-rating-star', 18 ); ?>
				</span>
			<?php endif; ?>

			<?php if ( ! empty( $args['show_average'] ) ) : ?>
				<span class="gr-summary-rating-average"><?php echo esc_html( $formatted_avg ); ?></span>
			<?php endif; ?>

			<?php if ( ! empty( $args['show_count'] ) ) : ?>
				<span class="gr-summary-rating-text">
					<?php if ( ! empty( $args['show_average'] ) && $args['separator'] !== '' ) : ?>
						<span class="gr-summary-rating-separator"><?php echo esc_html( $args['separator'] ); ?></span>
					<?php endif; ?>
					<?php if ( $args['count_prefix'] !== '' ) : ?>
						<span class="gr-summary-rating-prefix"><?php echo esc_html( $args['count_prefix'] ); ?></span>
					<?php endif; ?>
					<span class="gr-summary-rating-count"><?php echo esc_html( $formatted_count ); ?></span>
					<?php if ( $args['count_suffix'] !== '' ) : ?>
						<span class="gr-summary-rating-suffix"><?php echo esc_html( $args['count_suffix'] ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</span>
		<?php
		return ob_get_clean();
	}


	// Génère juste la grille (cartes d’avis)
	private static function render_grid_inner( WP_Query $query, $args ) {
		?>
		<div class="gr-grid" data-product-id="<?php echo esc_attr( $args['product_id'] ?? 0 ); ?>">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				echo self::review_card( get_the_ID(), $args['mode'] ?: 'grid' );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
	}




	/**
	 * Affiche un message lorsqu’aucun avis n’est disponible.
	 *
	 * @return string HTML du message “Aucun avis”.
	 */
	private static function render_no_reviews() {
		ob_start();
		?>
		<div class="gr-bloc gr-no-reviews">
			<span class="gr-no-reviews-title"><?php esc_html_e( 'Avis de nos clients', 'genius-reviews' ); ?></span>
			<div class="gr-no-reviews-stars-wrapper">
				<div class="gr-no-reviews-stars">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<?php echo self::render_star_svg( false, 1, 'gr-no-reviews-star', 22 ); ?>
					<?php endfor; ?>
				</div>
			</div>
			<p class="gr-no-reviews-text"><?php esc_html_e( 'Aucun avis pour le moment', 'genius-reviews' ); ?></p>
			<?php
			$lang        = substr( get_locale(), 0, 2 );
			$gr_slugs    = array(
				'fr' => 'questionnaire-feedback',
				'nl' => 'feedbackvragenlijst',
				'de' => 'fragebogen-feedback',
				'it' => 'questionario-di-feedback',
				'es' => 'cuestionario-de-opinion',
				'sv' => 'frageformular-feedback',
				'pl' => 'kwestionariusz-feedback',
			);
			$target_slug = isset( $gr_slugs[ $lang ] ) ? $gr_slugs[ $lang ] : $gr_slugs['fr'];
			$target_url  = home_url( '/' . $target_slug . '/' );
			?>
			<?php if ( is_user_logged_in() ) : ?>
				<a href="<?php echo esc_url( $target_url ); ?>" class="gr-btn bg-brand-custom hover:bg-brand-custom-hover">
					<?php _e( 'Écrire un avis', 'genius-reviews' ); ?>
				</a>
			<?php else : ?>
				<?php
				$login_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( get_permalink() );
				?>
				<p class="gr-no-reviews-login">
					<?php
					echo wp_kses_post(
						sprintf(
							__( 'Vous devez être <a href="%s" class="text-brand-custom hover:underline font-medium">connecté(e)</a> pour écrire un avis.', 'genius-reviews' ),
							esc_url( $login_url )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Génère le code SVG des étoiles pour une note donnée.
	 *
	 * @param int    $rating Note entre 1 et 5.
	 * @param string $size   Classes CSS de taille à appliquer.
	 * @return string HTML contenant les 5 étoiles.
	 */
	private static function render_stars( $rating, $classes = '', $size = 22 ) {
		ob_start();
		$rating = max( 1, min( 5, (int) round( $rating ) ) );
		for ( $i = 1; $i <= 5; $i++ ) {
			echo self::render_star_svg( $i <= $rating, $rating, $classes, $size );
		}
		return ob_get_clean();
	}

	/**
	 * Ajuste la luminosité d’une couleur hexadécimale.
	 *
	 * Sert à générer automatiquement la couleur de survol
	 * à partir de la couleur principale.
	 *
	 * @param string $hex   Code hexadécimal de la couleur.
	 * @param int    $steps Niveau de luminosité à ajouter ou retirer.
	 * @return string Nouvelle couleur hex.
	 */
	private static function adjust_brightness( $hex, $steps ) {
		$steps = max( -255, min( 255, $steps ) );
		$hex   = str_replace( '#', '', $hex );

		if ( strlen( $hex ) === 3 ) {
			$hex = str_repeat( substr( $hex, 0, 1 ), 2 ) . str_repeat( substr( $hex, 1, 1 ), 2 ) . str_repeat( substr( $hex, 2, 1 ), 2 );
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT )
			. str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT )
			. str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
	}


	/**
	 * Retourne une étiquette de texte basée sur la moyenne des notes.
	 *
	 * @param float $avg Moyenne des avis.
	 * @return string Libellé correspondant.
	 */
	private static function rating_label( $avg ) {
		if ( $avg >= 4.5 ) {
			return __( 'Excellent', 'genius-reviews' );
		}
		if ( $avg >= 4.0 ) {
			return __( 'Très bien', 'genius-reviews' );
		}
		if ( $avg >= 3.0 ) {
			return __( 'Bien', 'genius-reviews' );
		}
		if ( $avg >= 2.0 ) {
			return __( 'Moyen', 'genius-reviews' );
		}
		return __( 'À éviter', 'genius-reviews' );
	}


	/**
	 * Tronque un texte à un nombre de caractères sans couper les mots.
	 *
	 * @param string $text     Le texte original
	 * @param int    $limit    Nombre max de caractères
	 * @param string $ellipsis Texte ajouté à la fin si tronqué
	 * @return string
	 */
	private static function truncate_text( $text, $limit = 50, $ellipsis = '…' ) {
		$text = trim( wp_strip_all_tags( $text ) );

		if ( mb_strlen( $text ) <= $limit ) {
			return $text;
		}

		$cut = mb_substr( $text, 0, $limit );

		$lastSpace = mb_strrpos( $cut, ' ' );

		if ( $lastSpace !== false ) {
			$cut = mb_substr( $cut, 0, $lastSpace );
		}

		return rtrim( $cut ) . $ellipsis;
	}
}
