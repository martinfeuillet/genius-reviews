<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genius_Reviews_CPT {

	private static function curated_truthy_values() {
		return array( '1', 'ok', 'true', 'yes', 'on' );
	}

	private static function get_meta_fields() {
		return array(
			'_gr_display_title'  => array(
				'label'    => 'Titre affiché',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_rating'         => array(
				'label'      => 'Note',
				'input'      => 'number',
				'attributes' => array(
					'min'  => '0',
					'step' => '0.1',
				),
				'sanitize'   => 'float',
			),
			'_gr_review_date'    => array(
				'label'    => 'Date',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_source'         => array(
				'label'    => 'Source',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_curated'        => array(
				'label'    => 'Curated ?',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_reviewer_name'  => array(
				'label'    => 'Auteur',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_product_id'     => array(
				'label'      => 'Produit ID',
				'input'      => 'number',
				'attributes' => array(
					'min' => '0',
				),
				'sanitize'   => 'int',
			),
			'_gr_product_handle' => array(
				'label'    => 'Handle',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_reply'          => array(
				'label'    => 'Réponse',
				'input'    => 'textarea',
				'sanitize' => 'textarea',
			),
			'_gr_reply_date'     => array(
				'label'    => 'Date réponse',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_ip'             => array(
				'label'    => 'IP',
				'input'    => 'text',
				'sanitize' => 'text',
			),
			'_gr_location'       => array(
				'label'    => 'Localisation',
				'input'    => 'text',
				'sanitize' => 'text',
			),
		);
	}

	public static function register() {
		$labels = array(
			'name'               => __( 'Avis', 'genius-reviews' ),
			'singular_name'      => __( 'Avis', 'genius-reviews' ),
			'add_new'            => __( 'Ajouter un avis', 'genius-reviews' ),
			'add_new_item'       => __( 'Ajouter un nouvel avis', 'genius-reviews' ),
			'edit_item'          => __( 'Éditer l’avis', 'genius-reviews' ),
			'new_item'           => __( 'Nouvel avis', 'genius-reviews' ),
			'view_item'          => __( 'Voir l’avis', 'genius-reviews' ),
			'search_items'       => __( 'Rechercher des avis', 'genius-reviews' ),
			'not_found'          => __( 'Aucun avis', 'genius-reviews' ),
			'not_found_in_trash' => __( 'Aucun avis dans la corbeille', 'genius-reviews' ),
			'menu_name'          => __( 'Genius Reviews', 'genius-reviews' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'menu_icon'           => 'dashicons-format-quote',
		);

		register_post_type( 'genius_review', $args );

		// Meta clés normalisées
		register_post_meta(
			'genius_review',
			'_gr_display_title',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_rating',
			array(
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_review_date',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_source',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_curated',
			array(
				'type'         => 'boolean',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_reviewer_name',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_reviewer_hash',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		); // email hash (GDPR)
		register_post_meta(
			'genius_review',
			'_gr_product_id',
			array(
				'type'         => 'integer',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_product_handle',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_reply',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_reply_date',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_ip',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
		register_post_meta(
			'genius_review',
			'_gr_location',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
			)
		);
	}


	public static function register_metaboxes() {
		add_meta_box(
			'gr_review_details',
			__( 'Détails de l’avis', 'genius-reviews' ),
			array( __CLASS__, 'render_metabox' ),
			'genius_review',
			'normal',
			'default'
		);
	}

	public static function render_metabox( $post ) {
		$fields   = self::get_meta_fields();
		$can_edit = current_user_can( 'manage_options' );

		if ( $can_edit ) {
			wp_nonce_field( 'gr_meta_save', 'gr_meta_nonce' );
		}

		echo '<table class="form-table">';
		foreach ( $fields as $key => $label ) {
			$field = is_array( $label ) ? $label : array(
				'label'    => $label,
				'input'    => 'text',
				'sanitize' => 'text',
			);
			$val   = get_post_meta( $post->ID, $key, true );
			echo '<tr><th><label>' . esc_html( $field['label'] ) . '</label></th><td>';
			if ( $can_edit ) {
				$attributes = array( 'class' => 'widefat' );
				if ( ! empty( $field['attributes'] ) && is_array( $field['attributes'] ) ) {
					$attributes = array_merge( $attributes, $field['attributes'] );
				}
				if ( $field['input'] === 'textarea' ) {
					echo '<textarea name="gr_meta[' . esc_attr( $key ) . ']" class="widefat" rows="3">' . esc_textarea( $val ) . '</textarea>';
				} else {
					$type  = in_array( $field['input'], array( 'text', 'number' ), true ) ? $field['input'] : 'text';
					$attrs = '';
					foreach ( $attributes as $attr_key => $attr_value ) {
						$attrs .= ' ' . esc_attr( $attr_key ) . '="' . esc_attr( $attr_value ) . '"';
					}
					echo '<input type="' . esc_attr( $type ) . '" name="gr_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( $val ) . '"' . $attrs . ' />';
				}
			} elseif ( $field['input'] === 'textarea' ) {
					echo '<textarea readonly class="widefat" rows="3">' . esc_textarea( $val ) . '</textarea>';
			} else {
				echo '<input type="text" readonly class="widefat" value="' . esc_attr( $val ) . '"/>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	public static function save_metabox( $post_id, $post, $update ) {
		if ( ! isset( $_POST['gr_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gr_meta_nonce'] ) ), 'gr_meta_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['gr_meta'] ) || ! is_array( $_POST['gr_meta'] ) ) {
			return;
		}

		$fields = self::get_meta_fields();
		$input  = wp_unslash( $_POST['gr_meta'] );

		foreach ( $fields as $meta_key => $field ) {
			if ( ! array_key_exists( $meta_key, $input ) ) {
				continue;
			}

			$sanitize_type = isset( $field['sanitize'] ) ? $field['sanitize'] : 'text';
			$raw_value     = $input[ $meta_key ];

			if ( is_array( $raw_value ) ) {
				continue;
			}

			$value = self::sanitize_meta_value( $raw_value, $sanitize_type );

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	private static function sanitize_meta_value( $value, $type ) {
		switch ( $type ) {
			case 'int':
				return (string) absint( $value );
			case 'float':
				if ( $value === '' || $value === null ) {
					return '';
				}
				return (string) floatval( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	public static function sync_product_on_save( $post_id, $post, $update ) {
		if ( ! $post instanceof WP_Post || $post->post_type !== 'genius_review' ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::recalc_related_product( $post_id );
	}

	public static function sync_product_on_status_change( $post_id ) {
		if ( get_post_type( $post_id ) !== 'genius_review' ) {
			return;
		}

		self::recalc_related_product( $post_id );
	}

	private static function recalc_related_product( $review_id ) {
		$product_id = (int) get_post_meta( $review_id, '_gr_product_id', true );
		if ( $product_id <= 0 ) {
			return;
		}

		if ( is_callable( array( 'Genius_Reviews_Ajax', 'recalc_product' ) ) ) {
			Genius_Reviews_Ajax::recalc_product( $product_id );
		}
	}

	public static function admin_columns( $columns ) {
		return array(
			'cb'            => $columns['cb'],
			'title'         => __( 'Title', 'genius-reviews' ),
			'gr_product_id' => __( 'Product ID', 'genius-reviews' ),
			'gr_rating'     => __( 'Note', 'genius-reviews' ),
			'gr_curated'    => __( 'Validation', 'genius-reviews' ),
			'date'          => $columns['date'],
		);
	}

	public static function render_admin_column( $column, $post_id ) {
		if ( $column === 'gr_product_id' ) {
			$product_id = (int) get_post_meta( $post_id, '_gr_product_id', true );
			if ( $product_id > 0 ) {
				$edit_link = get_edit_post_link( $post_id );
				if ( ! empty( $edit_link ) ) {
					echo '<a href="' . esc_url( $edit_link ) . '">' . esc_html( (string) $product_id ) . '</a>';
					return;
				}
				echo esc_html( (string) $product_id );
				return;
			}
			echo '—';
			return;
		}

		if ( $column === 'gr_rating' ) {
			$rating = get_post_meta( $post_id, '_gr_rating', true );
			echo $rating !== '' ? esc_html( (string) $rating ) : '—';
			return;
		}

		if ( $column === 'gr_curated' ) {
			$raw          = strtolower( (string) get_post_meta( $post_id, '_gr_curated', true ) );
			$is_validated = in_array( $raw, self::curated_truthy_values(), true );
			echo $is_validated
				? esc_html__( 'Validé', 'genius-reviews' )
				: esc_html__( 'Non validé', 'genius-reviews' );
		}
	}

	public static function admin_sortable_columns( $columns ) {
		$columns['title']         = 'title';
		$columns['gr_product_id'] = 'gr_product_id';
		$columns['gr_rating']     = 'gr_rating';
		$columns['gr_curated']    = 'gr_curated';
		return $columns;
	}

	public static function admin_filters_markup( $post_type ) {
		if ( $post_type !== 'genius_review' ) {
			return;
		}

		$selected_rating  = isset( $_GET['gr_filter_rating'] )
			? sanitize_text_field( wp_unslash( $_GET['gr_filter_rating'] ) )
			: '';
		$selected_curated = isset( $_GET['gr_filter_curated'] )
			? sanitize_text_field( wp_unslash( $_GET['gr_filter_curated'] ) )
			: '';
		?>
		<select name="gr_filter_rating">
			<option value=""><?php esc_html_e( 'Toutes les notes', 'genius-reviews' ); ?></option>
			<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
				<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( $selected_rating, (string) $i ); ?>>
					<?php echo esc_html( (string) $i ); ?>
				</option>
			<?php endfor; ?>
		</select>
		<select name="gr_filter_curated">
			<option value=""><?php esc_html_e( 'Toutes les validations', 'genius-reviews' ); ?></option>
			<option value="1" <?php selected( $selected_curated, '1' ); ?>><?php esc_html_e( 'Validés', 'genius-reviews' ); ?></option>
			<option value="0" <?php selected( $selected_curated, '0' ); ?>><?php esc_html_e( 'Non validés', 'genius-reviews' ); ?></option>
		</select>
		<?php
	}

	public static function admin_handle_query( $query ) {
		if ( ! is_admin() || ! ( $query instanceof WP_Query ) || ! $query->is_main_query() ) {
			return;
		}

		if ( $query->get( 'post_type' ) !== 'genius_review' ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( $orderby === 'gr_product_id' ) {
			$query->set( 'meta_key', '_gr_product_id' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( $orderby === 'gr_rating' ) {
			$query->set( 'meta_key', '_gr_rating' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( $orderby === 'gr_curated' ) {
			$query->set( 'meta_key', '_gr_curated' );
			$query->set( 'orderby', 'meta_value' );
		}

		$meta_query = (array) $query->get( 'meta_query' );

		$rating_filter = isset( $_GET['gr_filter_rating'] )
			? sanitize_text_field( wp_unslash( $_GET['gr_filter_rating'] ) )
			: '';
		if ( $rating_filter !== '' && is_numeric( $rating_filter ) ) {
			$meta_query[] = array(
				'key'     => '_gr_rating',
				'value'   => (string) $rating_filter,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		}

		$curated_filter = isset( $_GET['gr_filter_curated'] )
			? sanitize_text_field( wp_unslash( $_GET['gr_filter_curated'] ) )
			: '';
		$truthy_values  = self::curated_truthy_values();
		if ( $curated_filter === '1' ) {
			$meta_query[] = array(
				'key'     => '_gr_curated',
				'value'   => $truthy_values,
				'compare' => 'IN',
			);
		} elseif ( $curated_filter === '0' ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_gr_curated',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_gr_curated',
					'value'   => $truthy_values,
					'compare' => 'NOT IN',
				),
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}
	}


	// /**
	// * Recalcul automatique quand on enregistre une review
	// */
	// public function recalc_on_save($post_id, $post, $update)
	// {
	// if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;

	// $product_id = (int) get_post_meta($post_id, '_gr_product_id', true);
	// if ($product_id) {
	// if (method_exists('Genius_Reviews_Admin_Page', 'recalc_product')) {
	// Genius_Reviews_Admin_Page::recalc_product($product_id);
	// }
	// }
	// }
}
