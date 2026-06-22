<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genius_Reviews_Query_Helper {

	/**
	 * Retourne les paramètres de tri pour WP_Query
	 *
	 * @param string $sort (date_desc, date_asc, rating_desc, rating_asc)
	 * @return array
	 */
	public static function map_sort( $sort = 'date_desc' ) {
		switch ( $sort ) {
			case 'date_asc':
				return array(
					'meta_key' => '_gr_review_date',
					'orderby'  => 'meta_value',
					'order'    => 'ASC',
				);

			case 'rating_desc':
				return array(
					'meta_query' => array(
						'relation'      => 'AND',
						'rating_clause' => array(
							'key'  => '_gr_rating',
							'type' => 'NUMERIC',
						),
						'date_clause'   => array(
							'key'  => '_gr_review_date',
							'type' => 'DATE',
						),
					),
					'orderby'    => array(
						'rating_clause' => 'DESC',
						'date_clause'   => 'DESC',
					),
				);

			case 'rating_asc':
				return array(
					'meta_query' => array(
						'relation'      => 'AND',
						'rating_clause' => array(
							'key'  => '_gr_rating',
							'type' => 'NUMERIC',
						),
						'date_clause'   => array(
							'key'  => '_gr_review_date',
							'type' => 'DATE',
						),
					),
					'orderby'    => array(
						'rating_clause' => 'ASC',
						'date_clause'   => 'DESC',
					),
				);

			default:
				return array(
					'meta_key' => '_gr_review_date',
					'orderby'  => 'meta_value',
					'order'    => 'DESC',
				);
		}
	}

	/**
	 * Calcule les statistiques globales (moyenne et nombre d'avis)
	 *
	 * @return array ['avg' => float, 'count' => int]
	 */
	public static function get_global_stats() {
		global $wpdb;

		$ratings = $wpdb->get_col(
			"
        SELECT r.meta_value
        FROM {$wpdb->postmeta} AS r
        INNER JOIN {$wpdb->posts} AS p ON p.ID = r.post_id
        INNER JOIN {$wpdb->postmeta} AS c ON c.post_id = p.ID
        WHERE r.meta_key = '_gr_rating'
          AND c.meta_key = '_gr_curated'
          AND c.meta_value = 'ok'
          AND p.post_type = 'genius_review'
          AND p.post_status = 'publish'
    "
		);

		$ratings = array_map( 'intval', $ratings );
		$count   = count( $ratings );
		$avg     = $count ? round( array_sum( $ratings ) / $count, 2 ) : 0;

		// Répartition par note
		$counts_by_rating = array(
			1 => 0,
			2 => 0,
			3 => 0,
			4 => 0,
			5 => 0,
		);
		foreach ( $ratings as $r ) {
			if ( $r >= 1 && $r <= 5 ) {
				++$counts_by_rating[ $r ];
			}
		}

		return array(
			'avg'              => $avg,
			'count'            => $count,
			'counts_by_rating' => $counts_by_rating,
		);
	}

	/**
	 * Calcule les statistiques d'un produit (moyenne et nombre d'avis) en ne gardant
	 * que les avis validés (_gr_curated = ok).
	 *
	 * @param int $product_id
	 * @return array ['avg' => float, 'count' => int]
	 */
	public static function get_product_stats( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return array(
				'avg'   => 0,
				'count' => 0,
			);
		}

		$q = new WP_Query(
			array(
				'post_type'      => 'genius_review',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_gr_product_id',
						'value'   => $product_id,
						'compare' => '=',
					),
					array(
						'key'     => '_gr_curated',
						'value'   => 'ok',
						'compare' => '=',
					),
				),
			)
		);

		$total = 0.0;
		$count = 0;
		foreach ( $q->posts as $review_id ) {
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
}
