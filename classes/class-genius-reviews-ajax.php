<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genius_Reviews_Ajax {


	// --------IMPORT CSV-------//
	public static function ajax_upload_csv() {
		check_ajax_referer( 'gr_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'perm' );
		}
		if ( empty( $_FILES['csv']['tmp_name'] ) ) {
			wp_send_json_error( array( 'msg' => 'nofile' ) );
		}

		$uploaded = wp_handle_upload( $_FILES['csv'], array( 'test_form' => false ) );
		if ( isset( $uploaded['error'] ) ) {
			wp_send_json_error( array( 'msg' => $uploaded['error'] ) );
		}

		$state     = array(
			'file'        => $uploaded['file'],
			'offset'      => 0,
			'delimiter'   => null,
			'header'      => null,
			'created'     => 0,
			'updated'     => 0,
			'skipped'     => 0,
			'rows'        => 0,
			'per_product' => array(),
		);
		$import_id = wp_generate_uuid4();
		self::save_import_state( $import_id, $state );

		wp_send_json_success(
			array(
				'total'     => self::count_lines( $uploaded['file'] ),
				'import_id' => $import_id,
			)
		);
	}

	public static function ajax_process_chunk() {
		check_ajax_referer( 'gr_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'perm' );
		}

		$import_id = isset( $_POST['import_id'] ) ? sanitize_text_field( wp_unslash( $_POST['import_id'] ) ) : '';
		$state     = self::get_import_state( $import_id );
		if ( ! $state || empty( $state['file'] ) ) {
			wp_send_json_error( array( 'msg' => 'state-missing' ) );
		}

		$chunk = isset( $_POST['chunk'] ) ? max( 10, (int) $_POST['chunk'] ) : 150;
		$fh    = fopen( $state['file'], 'r' );
		if ( ! $fh ) {
			wp_send_json_error( array( 'msg' => 'open-failed' ) );
		}

		if ( empty( $state['delimiter'] ) ) {
			$probe              = fgets( $fh );
			$state['delimiter'] = ( substr_count( $probe, ';' ) > substr_count( $probe, ',' ) ) ? ';' : ',';
			rewind( $fh );
			$state['header']      = array_map( 'trim', fgetcsv( $fh, 0, $state['delimiter'] ) );
			$state['offset']      = ftell( $fh );
			$state['rows']        = 0;
			$state['created']     = $state['updated'] = $state['skipped'] = 0;
			$state['per_product'] = array();
		} else {
			fseek( $fh, $state['offset'] );
		}

		$required = array(
			'title',
			'body',
			'rating',
			'review_date',
			'source',
			'curated',
			'reviewer_name',
			'reviewer_email',
			'product_id',
			'product_handle',
			'reply',
			'reply_date',
			'picture_urls',
			'ip_address',
			'location',
		);
		$idx      = array_flip( $state['header'] );

		$processed = 0;
		while ( $processed < $chunk && ( $row = fgetcsv( $fh, 0, $state['delimiter'] ) ) !== false ) {
			++$state['rows'];
			++$processed;
			$state['offset'] = ftell( $fh );

			if ( count( $row ) < count( $state['header'] ) ) {
				++$state['skipped'];
				continue;
			}

			$data = array();

			foreach ( $required as $col ) {
				$data[ $col ] = isset( $idx[ $col ] ) ? trim( (string) $row[ $idx[ $col ] ] ) : '';
			}

			$pid_raw = trim( (string) $data['product_id'] );

			if ( $pid_raw === '' || strtolower( $pid_raw ) === 'all-reviews-page' ) {
				// Avis global (non lié à un produit)
				$data['product_id'] = 0;
			} else {
				$data['product_id'] = (int) $pid_raw;
			}

			$post_title = ! empty( $data['title'] )
				? wp_strip_all_tags( $data['title'] )
				: mb_substr( wp_strip_all_tags( $data['body'] ), 0, 50 );

			$uid = self::generate_uid( $data );

			$existing = get_posts(
				array(
					'post_type'      => 'genius_review',
					'meta_key'       => '_gr_uid',
					'meta_value'     => $uid,
					'fields'         => 'ids',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);

			$postarr = array(
				'post_type'    => 'genius_review',
				'post_status'  => 'publish',
				'post_title'   => $post_title,
				'post_content' => wp_kses_post( $data['body'] ),
				'post_parent'  => (int) $data['product_id'],
			);

			if ( $existing ) {
				$postarr['ID'] = (int) $existing[0];
				wp_update_post( $postarr );
				$rid = (int) $existing[0];
				++$state['updated'];
			} else {
				$rid = wp_insert_post( $postarr );
				add_post_meta( $rid, '_gr_uid', $uid, true );
				++$state['created'];
			}

			update_post_meta( $rid, '_gr_display_title', sanitize_text_field( $data['title'] ) );
			update_post_meta( $rid, '_gr_rating', (float) $data['rating'] );
			update_post_meta( $rid, '_gr_review_date', sanitize_text_field( $data['review_date'] ) );
			update_post_meta( $rid, '_gr_source', sanitize_text_field( $data['source'] ) );
			update_post_meta( $rid, '_gr_curated', sanitize_text_field( $data['curated'] ) );
			update_post_meta( $rid, '_gr_reviewer_name', sanitize_text_field( $data['reviewer_name'] ) );
			update_post_meta( $rid, '_gr_reviewer_hash', hash( 'sha256', strtolower( $data['reviewer_email'] ) ) );
			update_post_meta( $rid, '_gr_product_id', (int) $data['product_id'] );
			update_post_meta( $rid, '_gr_product_handle', sanitize_text_field( $data['product_handle'] ) );
			update_post_meta( $rid, '_gr_reply', wp_kses_post( $data['reply'] ) );
			update_post_meta( $rid, '_gr_reply_date', sanitize_text_field( $data['reply_date'] ) );
			update_post_meta( $rid, '_gr_ip', sanitize_text_field( $data['ip_address'] ) );
			update_post_meta( $rid, '_gr_location', sanitize_text_field( $data['location'] ) );

			$picture_urls = self::normalize_picture_urls( $data['picture_urls'] );
			if ( ! empty( $picture_urls ) ) {
				update_post_meta( $rid, '_gr_picture_urls', $picture_urls );
			} else {
				delete_post_meta( $rid, '_gr_picture_urls' );
			}

			// Image
			$primary_picture = reset( $picture_urls );
			if ( $primary_picture ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$tmp = download_url( $primary_picture, 15 );
				if ( ! is_wp_error( $tmp ) ) {
					$file_array = array(
						'name'     => wp_basename( parse_url( $primary_picture, PHP_URL_PATH ) ),
						'tmp_name' => $tmp,
					);
					$att_id     = media_handle_sideload( $file_array, $rid );
					if ( ! is_wp_error( $att_id ) && ! has_post_thumbnail( $rid ) ) {
						set_post_thumbnail( $rid, $att_id );
					}
				}
			}

			$pid = (int) $data['product_id'];
			if ( ! isset( $state['per_product'][ $pid ] ) ) {
				$state['per_product'][ $pid ] = array(
					'name'    => $data['product_handle'],
					'added'   => 0,
					'updated' => 0,
					'skipped' => 0,
					'avg'     => 0,
					'count'   => 0,
				);
			}
			if ( $existing ) {
				++$state['per_product'][ $pid ]['updated'];
			} else {
				++$state['per_product'][ $pid ]['added'];
			}
		}

		$total    = max( 0, self::count_lines( $state['file'] ) );
		$percent  = $total ? min( 100, round( ( $state['rows'] / $total ) * 100 ) ) : 100;
		$complete = feof( $fh ) || ( $state['rows'] >= $total );

		fclose( $fh );

		if ( $complete ) {
			$percent = 100;
			foreach ( array_keys( $state['per_product'] ) as $pid ) {
				self::recalc_product( $pid );
				$state['per_product'][ $pid ]['avg']   = (float) get_post_meta( $pid, '_gr_avg_rating', true );
				$state['per_product'][ $pid ]['count'] = (int) get_post_meta( $pid, '_gr_review_count', true );
			}
			if ( is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'queue_refresh' ) ) ) {
				Genius_Reviews_Term_Schema_Cache::queue_refresh();
			}
			self::delete_import_state( $import_id );
		} else {
			self::save_import_state( $import_id, $state );
		}

		$progress_step = $total > 0 ? round( 100 / $total, 2 ) : 0;

		wp_send_json_success(
			array(
				'percent'       => $percent,
				'progress_step' => $progress_step,
				'complete'      => $complete,
				'created'       => (int) $state['created'],
				'updated'       => (int) $state['updated'],
				'skipped'       => (int) $state['skipped'],
				'perProduct'    => $state['per_product'],
			)
		);
	}

	public static function ajax_sync_products() {
		check_ajax_referer( 'gr_sync_products', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'perm' );
		}

		global $wpdb;

		$product_ids = $wpdb->get_col(
			"SELECT DISTINCT pm.meta_value
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_gr_product_id'
              AND p.post_type = 'genius_review'
              AND p.post_status != 'trash'"
		);

		if ( empty( $product_ids ) ) {
			wp_send_json_success(
				array(
					'count' => 0,
				)
			);
		}

		$synced = 0;
		foreach ( $product_ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid <= 0 ) {
				continue;
			}

			self::recalc_product( $pid );
			++$synced;
		}

		wp_send_json_success(
			array(
				'count' => $synced,
			)
		);
	}

	public static function ajax_refresh_term_schema_cache() {
		check_ajax_referer( 'gr_refresh_term_schema_cache', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'perm' );
		}

		if ( ! is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'refresh' ) ) ) {
			wp_send_json_error( 'schema-cache-unavailable' );
		}

		$stats = Genius_Reviews_Term_Schema_Cache::refresh();

		wp_send_json_success(
			array(
				'terms'   => isset( $stats['terms'] ) ? (int) $stats['terms'] : 0,
				'schemas' => isset( $stats['schemas'] ) ? (int) $stats['schemas'] : 0,
				'skipped' => isset( $stats['skipped'] ) ? (int) $stats['skipped'] : 0,
			)
		);
	}

	public static function ajax_clear_term_schema_cache() {
		check_ajax_referer( 'gr_clear_term_schema_cache', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'perm' );
		}

		if ( ! is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'clear_cache' ) ) ) {
			wp_send_json_error( 'schema-cache-unavailable' );
		}

		$cleared = Genius_Reviews_Term_Schema_Cache::clear_cache();

		if ( ! $cleared ) {
			wp_send_json_error( 'clear-failed' );
		}

		wp_send_json_success();
	}


	public static function recalc_product( $product_id ) {
		$q     = new WP_Query(
			array(
				'post_type'      => 'genius_review',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'meta_query'     => array(
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
		$total = 0;
		$count = 0;
		foreach ( $q->posts as $rid ) {
			$r = (float) get_post_meta( $rid, '_gr_rating', true );
			if ( $r > 0 ) {
				$total += $r;
				++$count;
			}
		}
		$avg = $count ? round( $total / $count, 2 ) : 0;
		update_post_meta( $product_id, '_gr_avg_rating', $avg );
		update_post_meta( $product_id, '_gr_review_count', $count );

		if ( is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'queue_refresh' ) ) ) {
			Genius_Reviews_Term_Schema_Cache::queue_refresh();
		}
	}

	private static function generate_uid( $data ) {
		$clean = function ( $v ) {
			// minuscule + trim + espaces normalisés
			return trim( preg_replace( '/\s+/', ' ', strtolower( (string) $v ) ) );
		};

		$date = ! empty( $data['review_date'] ) ? strtotime( $data['review_date'] ) : '';

		return md5(
			$clean( $data['reviewer_email'] ) . '|' .
			(int) $data['product_id'] . '|' .
			$date . '|' .
			mb_substr( $clean( $data['body'] ), 0, 50 )
		);
	}

	private static function normalize_picture_urls( $raw_urls ) {
		if ( empty( $raw_urls ) ) {
			return array();
		}

		if ( is_array( $raw_urls ) ) {
			$candidates = $raw_urls;
		} else {
			$candidates = preg_split( '/\s*,\s*/', (string) $raw_urls );
		}

		$normalized = array();
		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}
			$candidate = trim( $candidate );
			if ( $candidate === '' ) {
				continue;
			}

			$sanitized = esc_url_raw( $candidate );
			if ( $sanitized && filter_var( $sanitized, FILTER_VALIDATE_URL ) ) {
				$normalized[ $sanitized ] = true;
			}
		}

		return array_keys( $normalized );
	}

	private static function save_import_state( $import_id, $state ) {
		if ( $import_id === '' ) {
			return false;
		}

		return update_user_meta( get_current_user_id(), self::state_key( $import_id ), $state );
	}

	private static function get_import_state( $import_id ) {
		if ( $import_id === '' ) {
			return false;
		}

		return get_user_meta( get_current_user_id(), self::state_key( $import_id ), true );
	}

	private static function delete_import_state( $import_id ) {
		if ( $import_id === '' ) {
			return false;
		}

		return delete_user_meta( get_current_user_id(), self::state_key( $import_id ) );
	}

	private static function state_key( $import_id = '' ) {
		$user_id = get_current_user_id();

		if ( $import_id === '' ) {
			return 'gr_state_' . $user_id;
		}

		return 'gr_state_' . $user_id . '_' . $import_id;
	}

	private static function count_lines( $file ) {
		$c = 0;
		$f = fopen( $file, 'r' );
		if ( ! $f ) {
			return 0;
		}
		while ( ( $line = fgets( $f ) ) !== false ) {
			if ( trim( $line ) !== '' ) {
				++$c;
			}
		}
		fclose( $f );

		// On enlève l'entête
		return max( 0, $c - 1 );
	}


	// --------REVIEWS------//
	public static function gr_load_reviews() {
		check_ajax_referer( 'gr_public_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
		$limit      = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 6;
		$page       = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
		$offset     = ( $page - 1 ) * $limit;
		$sort       = isset( $_POST['sort'] ) ? sanitize_text_field( $_POST['sort'] ) : 'date_desc';
		$mode       = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : '';

		$sort_args = Genius_Reviews_Query_Helper::map_sort( $sort );
		if ( $mode === 'all_reviews' ) {
			$offset += 1;
		}

		$args = array(
			'post_type'      => 'genius_review',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_gr_curated',
					'value'   => 'ok',
					'compare' => '=',
				),
			),
			'offset'         => $offset,
		);

		$args = array_merge( $args, $sort_args );

		if ( $product_id ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}
			$args['meta_query'][] = array(
				'key'   => '_gr_product_id',
				'value' => $product_id,
			);
		}

		$q = new WP_Query( $args );

		$html = '';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$html .= Genius_Reviews_Render::review_card( get_the_ID(), $mode === 'all_reviews' ? 'all' : 'grid' );
			}
			wp_reset_postdata();
		}

		wp_send_json_success(
			array(
				'html'     => $html,
				'count'    => $q->post_count,
				'offset'   => $offset + $q->post_count,
				'has_more' => ( $offset + $q->post_count ) < (int) $q->found_posts,
			)
		);
	}
}
