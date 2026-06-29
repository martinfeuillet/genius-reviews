<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle JSON-LD output for SEO
 *
 * @since      1.0.0
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/classes
 */
class Genius_Reviews_Output_Json_Ld {

	/**
	 * Whether Rank Math is handling Product schema on this request.
	 *
	 * @var bool
	 */
	private static $rank_math_product_entity_seen = false;

	/**
	 * Whether Rank Math is handling Organization schema on this request.
	 *
	 * @var bool
	 */
	private static $rank_math_organization_entity_seen = false;

	/**
	 * Whether WooCommerce core output (and we enriched) a Product entity this request.
	 *
	 * @var bool
	 */
	private static $woocommerce_product_entity_seen = false;

	public function __construct() {
		add_filter( 'rank_math/snippet/rich_snippet_product_entity', array( $this, 'extend_rank_math_product_entity' ), 20 );
		add_filter( 'rank_math/json_ld', array( $this, 'extend_rank_math_json_ld' ), 99, 2 );

		add_filter( 'woocommerce_structured_data_product', array( $this, 'extend_woocommerce_structured_data_product' ), 20, 2 );

		add_action( 'wp_head', array( $this, 'output_term_product_jsonld' ), 20 );
		add_action( 'wp', array( $this, 'register_fallback_jsonld_outputs' ), 20 );
	}

	/**
	 * Enrich WooCommerce core's Product structured data with Genius Reviews data.
	 *
	 * WooCommerce core emits the Product JSON-LD whenever no SEO plugin takes it
	 * over (e.g. when Rank Math outputs only the BreadcrumbList). Enriching that
	 * entity in place adds our review data and return policy without creating a
	 * second Product sharing the same #product @id.
	 *
	 * @param array      $markup
	 * @param WC_Product $product
	 * @return array
	 */
	public function extend_woocommerce_structured_data_product( $markup, $product ) {
		self::$woocommerce_product_entity_seen = true;

		if ( ! is_array( $markup ) || ! $product instanceof WC_Product ) {
			return $markup;
		}

		return self::merge_review_data_into_product( $markup, $product );
	}

	/**
	 * Register standalone JSON-LD outputs only when Rank Math is not handling JSON-LD.
	 *
	 * @return void
	 */
	public function register_fallback_jsonld_outputs() {
		if ( self::is_rank_math_active() ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'output_product_jsonld' ), 20 );
		add_action( 'wp_footer', array( $this, 'output_organization_jsonld' ), 20 );
	}

	/**
	 * Output Product JSON-LD fallback when no SEO plugin product entity is present.
	 */
	public function output_product_jsonld() {
		if ( ! is_product() ) {
			return;
		}

		// WooCommerce core already output a Product entity that we enriched; emitting
		// our own here would duplicate the #product @id.
		if ( self::$woocommerce_product_entity_seen ) {
			return;
		}

		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$jsonld = self::build_product_schema( $product, true );
		if ( empty( $jsonld ) ) {
			return;
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			. '</script>';
	}

	/**
	 * Output cached Product JSON-LD for product categories and product attribute archives.
	 *
	 * @return void
	 */
	public function output_term_product_jsonld() {
		if ( is_admin() || ! Genius_Reviews_Term_Schema_Cache::is_supported_page() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$jsonld = Genius_Reviews_Term_Schema_Cache::get_or_refresh_schema( $term );
		if ( empty( $jsonld ) ) {
			return;
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $jsonld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			. '</script>';
	}

	/**
	 * Output Organization JSON-LD fallback when no SEO plugin organization entity is present.
	 */
	public function output_organization_jsonld() {
		if ( is_admin() ) {
			return;
		}

		if ( self::$rank_math_organization_entity_seen || self::is_rank_math_active() ) {
			return;
		}

		$review_data = self::get_shop_review_schema_data();
		if ( empty( $review_data ) ) {
			return;
		}

		$organization = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'Organization',
			'@id'             => home_url( '/' ) . '#organization',
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'aggregateRating' => $review_data['aggregateRating'],
		);

		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( ! empty( $logo_url ) ) {
				$organization['logo'] = $logo_url;
			}
		}

		if ( ! empty( $review_data['review'] ) ) {
			$organization['review'] = $review_data['review'];
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $organization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
			. '</script>';
	}

	/**
	 * Enrich Rank Math's Product or ProductGroup entity with Genius Reviews data.
	 *
	 * The entity is enriched in place (aggregateRating, review, return policy) so
	 * Rank Math's own properties (brand, description, sku, mpn, seller,
	 * priceValidUntil, UnitPriceSpecification) are preserved. This avoids both the
	 * stripped Product and the duplicate Product @id caused by replacing it.
	 *
	 * @param array $entity
	 * @return array
	 */
	public function extend_rank_math_product_entity( $entity ) {
		self::$rank_math_product_entity_seen = true;

		if ( ! is_product() || ! is_array( $entity ) ) {
			return $entity;
		}

		global $product;
		if ( ! $product instanceof WC_Product ) {
			return $entity;
		}

		return self::merge_review_data_into_product( $entity, $product );
	}

	/**
	 * Enrich Rank Math's existing Organization entity in the JSON-LD graph.
	 *
	 * @param array  $data
	 * @param object $jsonld
	 * @return array
	 */
	public function extend_rank_math_json_ld( $data, $jsonld ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		if ( is_product() ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				// Merge into any Product entity Rank Math already output, keeping its
				// native properties. When Rank Math outputs no Product, WooCommerce
				// core does, and we enrich that via woocommerce_structured_data_product
				// instead of adding a second Product that would collide on #product.
				self::enrich_product_entities_in_graph( $data, $product );
			}
		}

		$review_data = self::get_shop_review_schema_data();
		if ( ! empty( $review_data ) ) {
			self::inject_organization_review_data( $data, $review_data );
		}

		return $data;
	}

	/**
	 * Return product reviews JSON-LD.
	 *
	 * @param int $product_id
	 * @param int $limit
	 * @return array
	 */
	private static function get_reviews_jsonld( $product_id, $limit = 10 ) {
		$reviews = get_posts(
			array(
				'post_type'     => 'genius_review',
				'post_status'   => 'publish',
				'numberposts'   => $limit,
				'no_found_rows' => true,
				'meta_key'      => '_gr_review_date',
				'orderby'       => 'meta_value',
				'order'         => 'DESC',
				'meta_query'    => array(
					array(
						'key'     => '_gr_product_id',
						'value'   => (int) $product_id,
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

		$items = array();
		foreach ( $reviews as $review ) {
			$item = self::build_review_schema_item( $review->ID, false );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Return global shop reviews JSON-LD.
	 *
	 * @param int $limit
	 * @return array
	 */
	private static function get_shop_reviews_jsonld( $limit = 10 ) {
		$reviews = get_posts(
			array(
				'post_type'     => 'genius_review',
				'post_status'   => 'publish',
				'numberposts'   => $limit,
				'no_found_rows' => true,
				'meta_key'      => '_gr_review_date',
				'orderby'       => 'meta_value',
				'order'         => 'DESC',
				'meta_query'    => array(
					array(
						'key'     => '_gr_product_id',
						'value'   => 0,
						'compare' => '=',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => '_gr_curated',
						'value'   => 'ok',
						'compare' => '=',
					),
				),
			)
		);

		$items = array();
		foreach ( $reviews as $review ) {
			$item = self::build_review_schema_item( $review->ID, false );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Build product review schema data.
	 *
	 * @param int $product_id
	 * @return array
	 */
	private static function get_product_review_schema_data( $product_id ) {
		$avg   = (float) get_post_meta( $product_id, '_gr_avg_rating', true );
		$count = (int) get_post_meta( $product_id, '_gr_review_count', true );
		if ( $avg <= 0 || $count < 1 ) {
			return array();
		}

		$best_rating  = (float) apply_filters( 'genius_reviews_best_rating', 5 );
		$worst_rating = (float) apply_filters( 'genius_reviews_worst_rating', 1 );

		$data = array(
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $avg,
				'reviewCount' => $count,
				'ratingCount' => $count,
				'bestRating'  => $best_rating,
				'worstRating' => $worst_rating,
			),
		);

		$reviews = self::get_reviews_jsonld( $product_id, 10 );
		if ( ! empty( $reviews ) ) {
			$data['review'] = $reviews;
		}

		return $data;
	}

	/**
	 * Build Product schema for a WooCommerce product.
	 *
	 * @param WC_Product $product
	 * @param bool       $include_context
	 * @return array
	 */
	private static function build_product_schema( WC_Product $product, $include_context = true ) {
		$product_id  = $product->get_id();
		$review_data = self::get_product_review_schema_data( $product_id );
		if ( empty( $review_data ) ) {
			return array();
		}

		$schema = array(
			'@type'           => 'Product',
			'@id'             => get_permalink( $product_id ) . '#product',
			'name'            => $product->get_name(),
			'url'             => get_permalink( $product_id ),
			'image'           => wp_get_attachment_url( $product->get_image_id() ),
			'sku'             => $product->get_sku(),
			'offers'          => array(
				'@type'                   => 'Offer',
				'price'                   => $product->get_price(),
				'priceCurrency'           => get_woocommerce_currency(),
				'availability'            => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
				'url'                     => get_permalink( $product_id ),
				'hasMerchantReturnPolicy' => self::build_merchant_return_policy(),
			),
			'aggregateRating' => $review_data['aggregateRating'],
		);

		if ( $include_context ) {
			$schema = array( '@context' => 'https://schema.org/' ) + $schema;
		}

		if ( ! empty( $review_data['review'] ) ) {
			$schema['review'] = $review_data['review'];
		}

		return $schema;
	}

	/**
	 * Merge Genius Reviews data into an existing Product entity in place.
	 *
	 * Adds the merchant return policy (always) plus aggregateRating and review[]
	 * (when reviews exist), leaving every other property untouched. Idempotent, so
	 * it is safe to run from both the entity filter and the json_ld graph filter.
	 *
	 * @param array      $entity
	 * @param WC_Product $product
	 * @return array
	 */
	private static function merge_review_data_into_product( array $entity, WC_Product $product ) {
		$entity = self::add_return_policy_to_offers( $entity );

		$review_data = self::get_product_review_schema_data( $product->get_id() );
		if ( ! empty( $review_data ) ) {
			$entity['aggregateRating'] = $review_data['aggregateRating'];

			if ( ! empty( $review_data['review'] ) ) {
				$entity['review'] = $review_data['review'];
			}
		}

		return $entity;
	}

	/**
	 * Add the merchant return policy to a Product entity's offer(s).
	 *
	 * Covers a single Offer/AggregateOffer object, a list of offers, and variable
	 * products where Rank Math nests the offers inside each hasVariant[] entry
	 * (ProductGroup) rather than at the top level.
	 *
	 * @param array $entity
	 * @return array
	 */
	private static function add_return_policy_to_offers( array $entity ) {
		$policy = self::build_merchant_return_policy();

		$entity = self::apply_return_policy_to_offer_field( $entity, $policy );

		if ( ! empty( $entity['hasVariant'] ) && is_array( $entity['hasVariant'] ) ) {
			if ( isset( $entity['hasVariant']['@type'] ) ) {
				$entity['hasVariant'] = self::apply_return_policy_to_offer_field( $entity['hasVariant'], $policy );
			} else {
				foreach ( $entity['hasVariant'] as $index => $variant ) {
					if ( is_array( $variant ) ) {
						$entity['hasVariant'][ $index ] = self::apply_return_policy_to_offer_field( $variant, $policy );
					}
				}
			}
		}

		return $entity;
	}

	/**
	 * Attach the return policy to the offers found directly on an entity.
	 *
	 * Handles a single Offer/AggregateOffer object as well as a list of offers.
	 *
	 * @param array $entity
	 * @param array $policy
	 * @return array
	 */
	private static function apply_return_policy_to_offer_field( array $entity, array $policy ) {
		if ( empty( $entity['offers'] ) || ! is_array( $entity['offers'] ) ) {
			return $entity;
		}

		$offers = $entity['offers'];

		if ( isset( $offers['@type'] ) ) {
			$offers['hasMerchantReturnPolicy'] = $policy;
		} else {
			foreach ( $offers as $index => $offer ) {
				if ( is_array( $offer ) ) {
					$offer['hasMerchantReturnPolicy'] = $policy;
					$offers[ $index ]                 = $offer;
				}
			}
		}

		$entity['offers'] = $offers;

		return $entity;
	}

	/**
	 * Build the MerchantReturnPolicy schema for the store's base country.
	 *
	 * The applicable country mirrors the WooCommerce store base country. For the
	 * French store, neighbouring markets (BE, LU, CH) are advertised as well.
	 *
	 * @return array
	 */
	public static function build_merchant_return_policy() {
		$country = self::get_base_country();

		$applicable_country = ( 'FR' === $country )
			? array( 'FR', 'BE', 'LU', 'CH' )
			: $country;

		$policy = array(
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => $applicable_country,
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'   => 14,
			'returnMethod'         => 'https://schema.org/ReturnByMail',
			'returnFees'           => 'https://schema.org/FreeReturn',
		);

		return apply_filters( 'genius_reviews_merchant_return_policy', $policy, $country );
	}

	/**
	 * Get the WooCommerce store base country code (uppercase ISO 3166-1 alpha-2).
	 *
	 * @return string
	 */
	private static function get_base_country() {
		if ( function_exists( 'wc_get_base_location' ) ) {
			$location = wc_get_base_location();
			if ( ! empty( $location['country'] ) ) {
				return strtoupper( $location['country'] );
			}
		}

		return 'FR';
	}

	/**
	 * Build organization review schema data from global shop reviews.
	 *
	 * @return array
	 */
	private static function get_shop_review_schema_data() {
		$stats = self::get_shop_stats();
		if ( $stats['avg'] <= 0 || $stats['count'] < 1 ) {
			return array();
		}

		$best_rating  = (float) apply_filters( 'genius_reviews_best_rating', 5 );
		$worst_rating = (float) apply_filters( 'genius_reviews_worst_rating', 1 );

		$data = array(
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $stats['avg'],
				'reviewCount' => $stats['count'],
				'ratingCount' => $stats['count'],
				'bestRating'  => $best_rating,
				'worstRating' => $worst_rating,
			),
		);

		$reviews = self::get_shop_reviews_jsonld( 10 );
		if ( ! empty( $reviews ) ) {
			$data['review'] = $reviews;
		}

		return $data;
	}

	/**
	 * Calculate global shop review stats only.
	 *
	 * @return array
	 */
	private static function get_shop_stats() {
		$query = new WP_Query(
			array(
				'post_type'      => 'genius_review',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_gr_product_id',
						'value'   => 0,
						'compare' => '=',
						'type'    => 'NUMERIC',
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
		foreach ( $query->posts as $review_id ) {
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
	 * Build a Review schema item.
	 *
	 * @param int  $review_id
	 * @param bool $include_item_reviewed
	 * @return array
	 */
	private static function build_review_schema_item( $review_id, $include_item_reviewed = true ) {
		$rating = (float) get_post_meta( $review_id, '_gr_rating', true );
		if ( $rating <= 0 ) {
			return array();
		}

		$author       = trim( (string) get_post_meta( $review_id, '_gr_reviewer_name', true ) );
		$date         = self::normalize_review_date( get_post_meta( $review_id, '_gr_review_date', true ), $review_id );
		$review_title = trim( (string) get_post_meta( $review_id, '_gr_display_title', true ) );
		$review_body  = trim( wp_strip_all_tags( get_post_field( 'post_content', $review_id ) ) );

		$best_rating  = (float) apply_filters( 'genius_reviews_best_rating', 5 );
		$worst_rating = (float) apply_filters( 'genius_reviews_worst_rating', 1 );

		$item = array(
			'@type'         => 'Review',
			'author'        => array(
				'@type' => 'Person',
				'name'  => self::normalize_author_name( $author ),
			),
			'datePublished' => $date,
			'reviewBody'    => $review_body,
			'reviewRating'  => array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => $best_rating,
				'worstRating' => $worst_rating,
			),
		);

		if ( $review_title !== '' ) {
			$item['name'] = $review_title;
		}

		if ( $include_item_reviewed ) {
			$product_id = (int) get_post_meta( $review_id, '_gr_product_id', true );
			if ( $product_id > 0 ) {
				$item['itemReviewed'] = array(
					'@type' => 'Product',
					'name'  => get_the_title( $product_id ),
					'url'   => get_permalink( $product_id ),
				);
			}
		}

		return $item;
	}

	/**
	 * Normalize a review date to ISO yyyy-mm-dd.
	 *
	 * @param string $date
	 * @param int    $review_id
	 * @return string
	 */
	private static function normalize_review_date( $date, $review_id ) {
		$timestamp = ! empty( $date ) ? strtotime( $date ) : false;
		if ( ! $timestamp ) {
			$timestamp = get_post_time( 'U', true, $review_id );
		}

		return gmdate( 'Y-m-d', (int) $timestamp );
	}

	/**
	 * Normalize author names for schema output.
	 *
	 * @param string $author
	 * @return string
	 */
	private static function normalize_author_name( $author ) {
		$author = $author !== '' ? $author : __( 'Client', 'genius-reviews' );
		$author = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $author ) ) );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $author, 0, 100 );
		}

		return substr( $author, 0, 100 );
	}

	/**
	 * Detect whether Rank Math is active.
	 *
	 * @return bool
	 */
	private static function is_rank_math_active() {
		return defined( 'RANK_MATH_VERSION' )
			|| class_exists( 'RankMath' )
			|| class_exists( '\\RankMath\\Frontend\\JsonLD' );
	}

	/**
	 * Inject global review data into the first Organization entity found in Rank Math's graph.
	 *
	 * @param array $data
	 * @param array $review_data
	 * @return bool
	 */
	private static function inject_organization_review_data( &$data, $review_data ) {
		foreach ( $data as &$value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( self::is_organization_entity( $value ) ) {
				$value['aggregateRating'] = $review_data['aggregateRating'];

				if ( ! empty( $review_data['review'] ) ) {
					$value['review'] = $review_data['review'];
				}

				self::$rank_math_organization_entity_seen = true;
				return true;
			}

			if ( self::inject_organization_review_data( $value, $review_data ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enrich every Product/ProductGroup entity found in Rank Math's graph in place.
	 *
	 * @param array      $data
	 * @param WC_Product $product
	 * @return bool True when at least one Product entity was found and enriched.
	 */
	private static function enrich_product_entities_in_graph( &$data, WC_Product $product ) {
		$found = false;

		foreach ( $data as &$value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( self::is_product_entity( $value ) ) {
				$value                               = self::merge_review_data_into_product( $value, $product );
				self::$rank_math_product_entity_seen = true;
				$found                               = true;
				continue;
			}

			if ( self::enrich_product_entities_in_graph( $value, $product ) ) {
				$found = true;
			}
		}
		unset( $value );

		return $found;
	}

	/**
	 * Check whether a schema entity is an Organization.
	 *
	 * @param array $entity
	 * @return bool
	 */
	private static function is_organization_entity( $entity ) {
		if ( empty( $entity['@type'] ) ) {
			return false;
		}

		$types = is_array( $entity['@type'] ) ? $entity['@type'] : array( $entity['@type'] );
		$types = array_map( 'strval', $types );

		return in_array( 'Organization', $types, true );
	}

	/**
	 * Check whether a schema entity is a Product or ProductGroup.
	 *
	 * @param array $entity
	 * @return bool
	 */
	private static function is_product_entity( $entity ) {
		if ( empty( $entity['@type'] ) ) {
			return false;
		}

		$types = is_array( $entity['@type'] ) ? $entity['@type'] : array( $entity['@type'] );
		$types = array_map( 'strval', $types );

		return in_array( 'Product', $types, true ) || in_array( 'ProductGroup', $types, true );
	}
}
