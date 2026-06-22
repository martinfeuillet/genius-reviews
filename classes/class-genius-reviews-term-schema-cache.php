<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache Product JSON-LD data for WooCommerce product category and attribute pages.
 *
 * @since      1.2.1.14
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/classes
 */
class Genius_Reviews_Term_Schema_Cache {

	const TABLE                    = 'genius_reviews_term_schema';
	const CRON_HOOK                = 'genius_reviews_refresh_term_schema';
	const ACTION_GROUP             = 'genius-reviews';
	const MIN_REVIEWS              = 3;
	const REFRESH_INTERVAL_OPTION  = 'gr_term_schema_refresh_interval';
	const DEFAULT_REFRESH_INTERVAL = WEEK_IN_SECONDS;

	/**
	 * Register cache hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh' ) );
		add_action( 'init', array( __CLASS__, 'schedule_refresh' ), 20 );
	}

	/**
	 * Create the cache table and schedule refresh.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            term_id bigint(20) unsigned NOT NULL,
            taxonomy varchar(64) NOT NULL,
            jsonld longtext NOT NULL,
            rating_value decimal(4,2) NOT NULL DEFAULT 0.00,
            review_count int(11) unsigned NOT NULL DEFAULT 0,
            offer_count int(11) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY term_taxonomy (term_id, taxonomy),
            KEY review_count (review_count)
        ) {$charset_collate};"
		);

		self::schedule_refresh();
	}

	/**
	 * Queue a near-future refresh after review imports or edits.
	 *
	 * @return void
	 */
	public static function queue_refresh() {
		if ( ! self::has_scheduled_refresh() ) {
			self::schedule_refresh();
		}

		if ( ! get_transient( 'gr_term_schema_refresh_queued' ) ) {
			set_transient( 'gr_term_schema_refresh_queued', 1, 5 * MINUTE_IN_SECONDS );

			if ( self::use_action_scheduler() ) {
				as_schedule_single_action( time() + MINUTE_IN_SECONDS, self::CRON_HOOK, array(), self::ACTION_GROUP );
			} else {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK );
			}
		}
	}

	/**
	 * Schedule weekly cache refresh if missing.
	 *
	 * @return void
	 */
	public static function schedule_refresh() {
		if ( self::use_action_scheduler() ) {
			if ( ! as_has_scheduled_action( self::CRON_HOOK, array(), self::ACTION_GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, self::get_refresh_interval(), self::CRON_HOOK, array(), self::ACTION_GROUP );
			}

			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'genius_reviews_term_schema_interval', self::CRON_HOOK );
		}
	}

	/**
	 * Clear scheduled cache refresh.
	 *
	 * @return void
	 */
	public static function clear_schedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CRON_HOOK, array(), self::ACTION_GROUP );
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Check whether a scheduled refresh already exists.
	 *
	 * @return bool
	 */
	private static function has_scheduled_refresh() {
		if ( self::use_action_scheduler() && as_has_scheduled_action( self::CRON_HOOK, array(), self::ACTION_GROUP ) ) {
			return true;
		}

		return (bool) wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Prefer WooCommerce Action Scheduler when available so the job is visible in Scheduled Actions.
	 *
	 * @return bool
	 */
	private static function use_action_scheduler() {
		return function_exists( 'as_has_scheduled_action' )
			&& function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Add the configurable cron schedule.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public static function add_cron_schedule( $schedules ) {
		$schedules['genius_reviews_term_schema_interval'] = array(
			'interval' => self::get_refresh_interval(),
			'display'  => __( 'Genius Reviews term schema interval', 'genius-reviews' ),
		);

		return $schedules;
	}

	/**
	 * Get allowed interval choices.
	 *
	 * @return array
	 */
	public static function get_refresh_interval_choices() {
		return array(
			HOUR_IN_SECONDS     => __( 'Toutes les heures', 'genius-reviews' ),
			6 * HOUR_IN_SECONDS => __( 'Toutes les 6 heures', 'genius-reviews' ),
			DAY_IN_SECONDS      => __( 'Tous les jours', 'genius-reviews' ),
			WEEK_IN_SECONDS     => __( 'Toutes les semaines', 'genius-reviews' ),
		);
	}

	/**
	 * Get configured refresh interval in seconds.
	 *
	 * @return int
	 */
	public static function get_refresh_interval() {
		$interval = (int) get_option( self::REFRESH_INTERVAL_OPTION, self::DEFAULT_REFRESH_INTERVAL );
		$choices  = self::get_refresh_interval_choices();

		return isset( $choices[ $interval ] ) ? $interval : self::DEFAULT_REFRESH_INTERVAL;
	}

	/**
	 * Save the refresh interval and recreate the scheduled action.
	 *
	 * @param int $interval
	 * @return void
	 */
	public static function set_refresh_interval( $interval ) {
		$interval = (int) $interval;
		$choices  = self::get_refresh_interval_choices();

		if ( ! isset( $choices[ $interval ] ) ) {
			$interval = self::DEFAULT_REFRESH_INTERVAL;
		}

		update_option( self::REFRESH_INTERVAL_OPTION, $interval, false );
		self::clear_schedule();
		self::schedule_refresh();
	}

	/**
	 * Empty cached term schemas.
	 *
	 * @return bool
	 */
	public static function clear_cache() {
		global $wpdb;

		self::install();

		$table = self::get_table_name();
		return $wpdb->query( "TRUNCATE TABLE {$table}" ) !== false;
	}

	/**
	 * Refresh cached schema for all WooCommerce product categories and attributes.
	 *
	 * @return array
	 */
	public static function refresh() {
		delete_transient( 'gr_term_schema_refresh_queued' );

		$stats = array(
			'terms'   => 0,
			'schemas' => 0,
			'skipped' => 0,
		);

		if ( ! class_exists( 'WooCommerce' ) ) {
			return $stats;
		}

		self::install();

		foreach ( self::get_supported_taxonomies() as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term ) {
					++$stats['terms'];
					if ( self::refresh_term( $term ) ) {
						++$stats['schemas'];
					} else {
						++$stats['skipped'];
					}
				}
			}
		}

		return $stats;
	}

	/**
	 * Get cached JSON-LD for a term.
	 *
	 * @param int    $term_id
	 * @param string $taxonomy
	 * @return array
	 */
	public static function get_cached_schema( $term_id, $taxonomy ) {
		global $wpdb;

		$table = self::get_table_name();
		$json  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT jsonld FROM {$table} WHERE term_id = %d AND taxonomy = %s AND review_count >= %d LIMIT 1",
				(int) $term_id,
				(string) $taxonomy,
				self::MIN_REVIEWS
			)
		);

		if ( empty( $json ) ) {
			return array();
		}

		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Get cached rating stats for a term, warming the cache for this term if needed.
	 *
	 * @param WP_Term $term
	 * @return array
	 */
	public static function get_term_stats( WP_Term $term ) {
		global $wpdb;

		self::get_or_refresh_schema( $term );

		$table = self::get_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rating_value, review_count FROM {$table} WHERE term_id = %d AND taxonomy = %s AND review_count >= %d LIMIT 1",
				(int) $term->term_id,
				(string) $term->taxonomy,
				self::MIN_REVIEWS
			),
			ARRAY_A
		);

		if ( empty( $row ) ) {
			return array(
				'avg'   => 0,
				'count' => 0,
			);
		}

		return array(
			'avg'   => (float) $row['rating_value'],
			'count' => (int) $row['review_count'],
		);
	}

	/**
	 * Refresh one term immediately, then return its cached schema.
	 *
	 * This is used as a narrow fallback when the weekly cron has not warmed the cache yet.
	 *
	 * @param WP_Term $term
	 * @return array
	 */
	public static function get_or_refresh_schema( WP_Term $term ) {
		$schema = self::get_cached_schema( $term->term_id, $term->taxonomy );
		if ( ! empty( $schema ) ) {
			return $schema;
		}

		self::install();
		self::refresh_term( $term );

		return self::get_cached_schema( $term->term_id, $term->taxonomy );
	}

	/**
	 * Check whether the current request is a supported WooCommerce taxonomy archive.
	 *
	 * @return bool
	 */
	public static function is_supported_page() {
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			return true;
		}

		$term = get_queried_object();
		return $term instanceof WP_Term && strpos( $term->taxonomy, 'pa_' ) === 0 && is_tax( $term->taxonomy );
	}

	/**
	 * Refresh cached schema for one term.
	 *
	 * @param WP_Term $term
	 * @return bool
	 */
	private static function refresh_term( WP_Term $term ) {
		global $wpdb;

		$schema_data = self::build_schema( $term );
		$table       = self::get_table_name();

		if ( empty( $schema_data ) ) {
			$wpdb->delete(
				$table,
				array(
					'term_id'  => (int) $term->term_id,
					'taxonomy' => $term->taxonomy,
				),
				array( '%d', '%s' )
			);
			return false;
		}

		$wpdb->replace(
			$table,
			array(
				'term_id'      => (int) $term->term_id,
				'taxonomy'     => $term->taxonomy,
				'jsonld'       => wp_json_encode( $schema_data['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'rating_value' => $schema_data['rating_value'],
				'review_count' => $schema_data['review_count'],
				'offer_count'  => $schema_data['offer_count'],
				'updated_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%f', '%d', '%d', '%s' )
		);

		return true;
	}

	/**
	 * Build Product schema for a taxonomy term from Genius Reviews stats.
	 *
	 * @param WP_Term $term
	 * @return array
	 */
	private static function build_schema( WP_Term $term ) {
		$product_ids = self::get_term_product_ids( $term );
		if ( empty( $product_ids ) ) {
			return array();
		}

		$weighted_total = 0.0;
		$review_count   = 0;
		$low_price      = null;
		$high_price     = null;
		$offer_count    = 0;

		foreach ( $product_ids as $product_id ) {
			$rating = self::get_product_rating( (int) $product_id );
			if ( $rating['avg'] > 0 && $rating['count'] > 0 ) {
				$weighted_total += $rating['avg'] * $rating['count'];
				$review_count   += $rating['count'];
			}

			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product || ! $product->is_in_stock() ) {
				continue;
			}

			$price = (float) $product->get_price();
			if ( $price <= 0 ) {
				continue;
			}

			$low_price  = $low_price === null ? $price : min( $low_price, $price );
			$high_price = $high_price === null ? $price : max( $high_price, $price );
			++$offer_count;
		}

		if ( $review_count < self::MIN_REVIEWS || $low_price === null || $high_price === null || $offer_count < 1 ) {
			return array();
		}

		$term_link = get_term_link( $term );
		if ( is_wp_error( $term_link ) ) {
			return array();
		}

		$rating_value = round( $weighted_total / $review_count, 2 );
		$jsonld       = array(
			'@context'        => 'https://schema.org/',
			'@type'           => 'Product',
			'@id'             => $term_link . '#category-product',
			'name'            => $term->name,
			'description'     => self::get_term_description( $term ),
			'brand'           => array(
				'@type' => 'Brand',
				'name'  => self::get_formatted_site_name(),
			),
			'offers'          => array(
				'@type'         => 'AggregateOffer',
				'priceCurrency' => get_woocommerce_currency(),
				'lowPrice'      => self::format_price( $low_price ),
				'highPrice'     => self::format_price( $high_price ),
				'offerCount'    => $offer_count,
				'availability'  => 'https://schema.org/InStock',
			),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating_value,
				'reviewCount' => $review_count,
				'bestRating'  => (float) apply_filters( 'genius_reviews_best_rating', 5 ),
				'worstRating' => (float) apply_filters( 'genius_reviews_worst_rating', 1 ),
			),
		);

		$image = self::get_term_image( $term );
		if ( $image !== '' ) {
			$jsonld['image'] = $image;
		}

		return array(
			'jsonld'       => $jsonld,
			'rating_value' => $rating_value,
			'review_count' => $review_count,
			'offer_count'  => $offer_count,
		);
	}

	/**
	 * Get products assigned to the term.
	 *
	 * @param WP_Term $term
	 * @return int[]
	 */
	private static function get_term_product_ids( WP_Term $term ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'term_id',
						'terms'    => array( (int) $term->term_id ),
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Get cached product rating from Genius Reviews product stats.
	 *
	 * @param int $product_id
	 * @return array
	 */
	private static function get_product_rating( $product_id ) {
		return array(
			'avg'   => (float) get_post_meta( $product_id, '_gr_avg_rating', true ),
			'count' => (int) get_post_meta( $product_id, '_gr_review_count', true ),
		);
	}

	/**
	 * Return product category and product attribute taxonomies.
	 *
	 * @return string[]
	 */
	private static function get_supported_taxonomies() {
		$taxonomies = array( 'product_cat' );

		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attribute_taxonomies = wc_get_attribute_taxonomies();
			foreach ( $attribute_taxonomies as $attribute ) {
				if ( ! empty( $attribute->attribute_name ) ) {
					$taxonomies[] = wc_attribute_taxonomy_name( $attribute->attribute_name );
				}
			}
		} else {
			foreach ( get_taxonomies( array(), 'names' ) as $taxonomy ) {
				if ( strpos( $taxonomy, 'pa_' ) === 0 ) {
					$taxonomies[] = $taxonomy;
				}
			}
		}

		return array_values( array_unique( array_filter( $taxonomies, 'taxonomy_exists' ) ) );
	}

	/**
	 * Return schema-safe term description.
	 *
	 * @param WP_Term $term
	 * @return string
	 */
	private static function get_term_description( WP_Term $term ) {
		$description = term_description( $term->term_id, $term->taxonomy );
		$description = trim( wp_strip_all_tags( (string) $description ) );

		if ( $description !== '' ) {
			return $description;
		}

		return sprintf( __( 'Products in %s', 'genius-reviews' ), $term->name );
	}

	/**
	 * Return term thumbnail URL when defined.
	 *
	 * @param WP_Term $term
	 * @return string
	 */
	private static function get_term_image( WP_Term $term ) {
		$thumbnail_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $thumbnail_id <= 0 ) {
			return '';
		}

		$image = wp_get_attachment_image_url( $thumbnail_id, 'full' );
		return ! empty( $image ) ? $image : '';
	}

	/**
	 * Format the site name for Brand schema.
	 *
	 * @return string
	 */
	private static function get_formatted_site_name() {
		$name = trim( (string) get_bloginfo( 'name' ) );
		$name = preg_replace( '/\.[a-z]{2,}$/i', '', $name );
		$name = preg_replace( '/[_-]+/', ' ', $name );
		$name = preg_replace( '/(?<=[a-z])(?=[A-Z])/', ' ', $name );
		$name = trim( preg_replace( '/\s+/', ' ', $name ) );

		if ( function_exists( 'mb_convert_case' ) ) {
			return mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' );
		}

		return ucwords( strtolower( $name ) );
	}

	/**
	 * Format prices without unnecessary trailing zeroes.
	 *
	 * @param float $price
	 * @return string
	 */
	private static function format_price( $price ) {
		return rtrim( rtrim( number_format( (float) $price, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Return full table name for term schema cache.
	 *
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}
}
