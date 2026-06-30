<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Genius_Reviews_Admin_Page {


	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=genius_review',
			__( 'Options & Import', 'genius-reviews' ),
			__( 'Options & Import', 'genius-reviews' ),
			'manage_woocommerce',
			'gr-options',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Nope.' );
		}

		if ( ! empty( $_POST['gr_options_nonce'] ) && wp_verify_nonce( $_POST['gr_options_nonce'], 'gr_save_options' ) ) {
			update_option( 'gr_option_active_reviews_on_product_page', ! empty( $_POST['gr_option_active_reviews_on_product_page'] ) ? 1 : 0 );
			update_option( 'gr_option_active_badge_on_product_page', ! empty( $_POST['gr_option_active_badge_on_product_page'] ) ? 1 : 0 );
			update_option( 'gr_option_active_badge_on_collection_page', ! empty( $_POST['gr_option_active_badge_on_collection_page'] ) ? 1 : 0 );
			update_option( 'gr_option_fallback_reviews_all', ! empty( $_POST['gr_option_fallback_reviews_all'] ) ? 1 : 0 );

			if ( isset( $_POST['gr_color_brand_custom'] ) ) {
				update_option( 'gr_color_brand_custom', sanitize_hex_color( $_POST['gr_color_brand_custom'] ) ?: '#6EC522' );
			}

			if ( isset( $_POST['gr_color_star_custom'] ) ) {
				update_option( 'gr_color_star_custom', sanitize_hex_color( $_POST['gr_color_star_custom'] ) ?: '#6EC522' );
			}

			if ( isset( $_POST['gr_color_star_icon_custom'] ) ) {
				update_option( 'gr_color_star_icon_custom', sanitize_hex_color( $_POST['gr_color_star_icon_custom'] ) ?: '#FFFFFF' );
			}

			foreach ( array( 5, 4, 3, 2, 1 ) as $rating_color_level ) {
				$field_name = 'gr_color_star_' . $rating_color_level . '_custom';
				if ( isset( $_POST[ $field_name ] ) ) {
					$default_colors = array(
						5 => '#6EC522',
						4 => '#92D329',
						3 => '#FFCE0C',
						2 => '#FF9232',
						1 => '#EB3531',
					);
					update_option( $field_name, sanitize_hex_color( $_POST[ $field_name ] ) ?: $default_colors[ $rating_color_level ] );
				}
			}

			if ( isset( $_POST['gr_color_star_empty_custom'] ) ) {
				update_option( 'gr_color_star_empty_custom', sanitize_hex_color( $_POST['gr_color_star_empty_custom'] ) ?: '#E5E5E5' );
			}

			if ( isset( $_POST['gr_term_schema_refresh_interval'] ) && is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'set_refresh_interval' ) ) ) {
				Genius_Reviews_Term_Schema_Cache::set_refresh_interval( (int) $_POST['gr_term_schema_refresh_interval'] );
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Options sauvegardées.', 'genius-reviews' ) . '</p></div>';
		}

		$active_reviews_on_product_page  = (int) get_option( 'gr_option_active_reviews_on_product_page', 0 );
		$active_badge_on_product_page    = (int) get_option( 'gr_option_active_badge_on_product_page', 0 );
		$active_badge_on_collection_page = (int) get_option( 'gr_option_active_badge_on_collection_page', 0 );
		$fallback_reviews_all            = (int) get_option( 'gr_option_fallback_reviews_all', 0 );

		$color_brand_custom           = get_option( 'gr_color_brand_custom', '#6EC522' );
		$color_star_custom            = get_option( 'gr_color_star_custom', '#6EC522' );
		$color_star_icon_custom       = get_option( 'gr_color_star_icon_custom', '#FFFFFF' );
		$color_star_levels            = array(
			5 => get_option( 'gr_color_star_5_custom', '#6EC522' ),
			4 => get_option( 'gr_color_star_4_custom', '#92D329' ),
			3 => get_option( 'gr_color_star_3_custom', '#FFCE0C' ),
			2 => get_option( 'gr_color_star_2_custom', '#FF9232' ),
			1 => get_option( 'gr_color_star_1_custom', '#EB3531' ),
		);
		$color_star_empty_custom      = get_option( 'gr_color_star_empty_custom', '#E5E5E5' );
		$term_schema_refresh_interval = is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'get_refresh_interval' ) )
			? Genius_Reviews_Term_Schema_Cache::get_refresh_interval()
			: WEEK_IN_SECONDS;
		$term_schema_refresh_choices  = is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'get_refresh_interval_choices' ) )
			? Genius_Reviews_Term_Schema_Cache::get_refresh_interval_choices()
			: array();
		?>
		<div class="wrap !p-0">
			<div class="tw bg-white container mx-auto p-6">
				<h1 class="text-2xl font-semibold mb-6"><?php _e( 'Genius Reviews — Options & Import CSV', 'genius-reviews' ); ?></h1>

				<!-- Options -->
				<form method="post" class="mb-8 bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-6">
		<?php wp_nonce_field( 'gr_save_options', 'gr_options_nonce' ); ?>

	<!-- HEADER -->
	<div class="flex items-start justify-between gap-4">
		<div>
			<h2 class="text-xl font-semibold mb-1">
				<?php _e( 'Options d’affichage', 'genius-reviews' ); ?>
			</h2>
			<p class="text-gray-600 leading-relaxed">
				<?php
				_e(
					'Activez automatiquement les blocs côté boutique et/ou utilise les shortcodes ci-dessous selon vos besoins.',
					'genius-reviews'
				);
				?>
			</p>
		</div>
		<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
			<?php esc_html_e( 'Genius Reviews', 'genius-reviews' ); ?>
		</span>
	</div>

	<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
		<div class="space-y-4">
			<div class="flex items-center justify-between">
				<h3 class="text-base font-semibold text-gray-900"><?php _e( 'Affichage automatique', 'genius-reviews' ); ?></h3>
				<span class="text-xs text-gray-500"><?php _e( 'Boutique', 'genius-reviews' ); ?></span>
			</div>

			<div class="p-4 border border-gray-200 rounded-xl">
				<label for="gr-option-reviews-on-product-page" class="block text-sm font-medium text-gray-900 mb-1">
					<?php _e( 'Activer la grille des avis sur la page produit', 'genius-reviews' ); ?>
				</label>
				<div class="flex items-center justify-between">
					<p class="text-sm text-gray-600 max-w-6/10">
						<?php _e( 'Affiche la grille en bas de chaque fiche produit (si des avis sont disponibles).', 'genius-reviews' ); ?>
					</p>
					<label for="gr-option-reviews-on-product-page"
							class="relative block h-6 w-11 rounded-full bg-gray-300 transition-colors cursor-pointer has-[:checked]:bg-[var(--color-brand)]">
						<input type="checkbox"
								id="gr-option-reviews-on-product-page"
								name="gr_option_active_reviews_on_product_page"
								value="1"
								class="sr-only peer"
								<?php checked( $active_reviews_on_product_page, 1 ); ?>>
						<span class="absolute inset-y-0 start-0 m-[2px] size-5 rounded-full bg-white shadow transition-[inset-inline-start] peer-checked:start-5"></span>
					</label>
				</div>
			</div>

			<div class="p-4 border border-gray-200 rounded-xl">
				<label for="gr-option-fallback-reviews-all" class="block text-sm font-medium text-gray-900 mb-1">
					<?php _e( 'Afficher des avis globaux si le produit n\'en a pas', 'genius-reviews' ); ?>
				</label>
				<div class="flex items-center justify-between">
					<p class="text-sm text-gray-600 max-w-6/10">
						<?php _e( 'Si la fiche produit n’a aucun avis, afficher les avis globaux du catalogue à la place (la moyenne du produit ne change pas).', 'genius-reviews' ); ?>
					</p>
					<label for="gr-option-fallback-reviews-all"
							class="relative block h-6 w-11 rounded-full bg-gray-300 transition-colors cursor-pointer has-[:checked]:bg-[var(--color-brand)]">
						<input type="checkbox"
								id="gr-option-fallback-reviews-all"
								name="gr_option_fallback_reviews_all"
								value="1"
								class="sr-only peer"
								<?php checked( $fallback_reviews_all, 1 ); ?>>
						<span class="absolute inset-y-0 start-0 m-[2px] size-5 rounded-full bg-white shadow transition-[inset-inline-start] peer-checked:start-5"></span>
					</label>
				</div>
			</div>

			<div class="p-4 border border-gray-200 rounded-xl">
				<label for="gr-option-badge-on-product-page" class="block text-sm font-medium text-gray-900 mb-1">
					<?php _e( 'Activer le badge d\'avis sur la page produit', 'genius-reviews' ); ?>
				</label>
				<div class="flex items-center justify-between">
					<p class="text-sm text-gray-600 max-w-6/10">
						<?php _e( 'Petit badge (moyenne + nombre d’avis) visible en haut de la fiche produit.', 'genius-reviews' ); ?>
					</p>
					<label for="gr-option-badge-on-product-page"
							class="relative block h-6 w-11 rounded-full bg-gray-300 transition-colors cursor-pointer has-[:checked]:bg-[var(--color-brand)]">
						<input type="checkbox"
								id="gr-option-badge-on-product-page"
								name="gr_option_active_badge_on_product_page"
								value="1"
								class="sr-only peer"
								<?php checked( $active_badge_on_product_page, 1 ); ?>>
						<span class="absolute inset-y-0 start-0 m-[2px] size-5 rounded-full bg-white shadow transition-[inset-inline-start] peer-checked:start-5"></span>
					</label>
				</div>
			</div>

			<div class="p-4 border border-gray-200 rounded-xl">
				<label for="gr-option-badge-on-collection-page" class="block text-sm font-medium text-gray-900 mb-1">
					<?php _e( 'Activer le badge d\'avis sur la page collection', 'genius-reviews' ); ?>
				</label>
				<div class="flex items-center justify-between">
					<p class="text-sm text-gray-600 max-w-6/10">
						<?php _e( 'Affiche le badge dans les listings/collections produits.', 'genius-reviews' ); ?>
					</p>
					<label for="gr-option-badge-on-collection-page"
							class="relative block h-6 w-11 rounded-full bg-gray-300 transition-colors cursor-pointer has-[:checked]:bg-[var(--color-brand)]">
						<input type="checkbox"
								id="gr-option-badge-on-collection-page"
								name="gr_option_active_badge_on_collection_page"
								value="1"
								class="sr-only peer"
								<?php checked( $active_badge_on_collection_page, 1 ); ?>>
						<span class="absolute inset-y-0 start-0 m-[2px] size-5 rounded-full bg-white shadow transition-[inset-inline-start] peer-checked:start-5"></span>
					</label>
				</div>
			</div>
		</div>

		<div class="space-y-4">
			<div class="p-4 border border-gray-200 rounded-xl">
				<div class="flex items-start justify-between gap-4 mb-4">
					<div>
						<h3 class="text-base font-semibold text-gray-900"><?php _e( 'Apparence', 'genius-reviews' ); ?></h3>
						<p class="text-sm text-gray-600 mt-1">
							<?php _e( 'Personnalisez les accents et les étoiles affichés côté boutique.', 'genius-reviews' ); ?>
						</p>
					</div>
					<div class="space-y-0.5" aria-hidden="true" data-gr-star-level-preview>
						<?php foreach ( array( 5, 4, 3, 2, 1 ) as $preview_level ) : ?>
							<div class="flex gap-0.5">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<?php
									echo Genius_Reviews_Render::render_star_svg(
										$i <= $preview_level,
										$preview_level,
										'',
										22,
										array(
											'data-gr-star-level' => (string) $preview_level,
											'data-gr-star-state' => $i <= $preview_level ? 'filled' : 'empty',
											'style' => 'color: ' . ( $i <= $preview_level ? $color_star_levels[ $preview_level ] : $color_star_empty_custom ) . ';',
										)
									);
									?>
								<?php endfor; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="grid grid-cols-1 gap-4">
					<div>
						<label for="gr-color-brand-custom" class="block text-sm font-medium text-gray-900 mb-2">
							<?php _e( 'Couleur principale', 'genius-reviews' ); ?>
						</label>
						<div class="flex items-center gap-3">
							<input type="text"
									id="gr-color-brand-custom"
									name="gr_color_brand_custom"
									value="<?php echo esc_attr( $color_brand_custom ); ?>"
									class="border rounded cursor-pointer p-0"
									data-coloris>
							<span class="text-sm text-gray-600">
								<?php _e( 'Boutons, liens et accents. Le hover est auto-généré.', 'genius-reviews' ); ?>
							</span>
						</div>
					</div>

					<div>
						<div class="flex items-center justify-between gap-3 mb-2">
							<label class="block text-sm font-medium text-gray-900">
								<?php _e( 'Couleurs des étoiles par note', 'genius-reviews' ); ?>
							</label>
							<span class="text-xs text-gray-500"><?php _e( '5 à 1 étoiles', 'genius-reviews' ); ?></span>
						</div>
						<div class="grid grid-cols-1 gap-2">
							<?php foreach ( array( 5, 4, 3, 2, 1 ) as $color_level ) : ?>
								<div class="flex items-center gap-3">
									<span class="text-sm text-gray-700 w-[126px]">
										<?php echo esc_html( sprintf( _n( '%d étoile', '%d étoiles', $color_level, 'genius-reviews' ), $color_level ) ); ?>
									</span>
									<input type="text"
											id="gr-color-star-<?php echo esc_attr( (string) $color_level ); ?>-custom"
											name="gr_color_star_<?php echo esc_attr( (string) $color_level ); ?>_custom"
											value="<?php echo esc_attr( $color_star_levels[ $color_level ] ); ?>"
											class="border rounded cursor-pointer p-0"
											data-coloris
											data-gr-star-level-input="<?php echo esc_attr( (string) $color_level ); ?>">
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div>
						<label for="gr-color-star-icon-custom" class="block text-sm font-medium text-gray-900 mb-2">
							<?php _e( 'Couleur de l’étoile', 'genius-reviews' ); ?>
						</label>
						<div class="flex items-center gap-3">
							<input type="text"
									id="gr-color-star-icon-custom"
									name="gr_color_star_icon_custom"
									value="<?php echo esc_attr( $color_star_icon_custom ); ?>"
									class="border rounded cursor-pointer p-0"
									data-coloris>
							<span class="text-sm text-gray-600">
								<?php _e( 'Couleur du symbole étoile à l’intérieur du carré.', 'genius-reviews' ); ?>
							</span>
						</div>
					</div>

					<div>
						<label for="gr-color-star-empty-custom" class="block text-sm font-medium text-gray-900 mb-2">
							<?php _e( 'Couleur des étoiles inactives', 'genius-reviews' ); ?>
						</label>
						<div class="flex items-center gap-3">
							<input type="text"
									id="gr-color-star-empty-custom"
									name="gr_color_star_empty_custom"
									value="<?php echo esc_attr( $color_star_empty_custom ); ?>"
									class="border rounded cursor-pointer p-0"
									data-coloris>
							<span class="text-sm text-gray-600">
								<?php _e( 'Utilisée pour compléter les notes inférieures à 5.', 'genius-reviews' ); ?>
							</span>
						</div>
					</div>
				</div>
			</div>

			<div class="p-4 border border-gray-200 rounded-xl">
				<label for="gr-term-schema-refresh-interval" class="block text-sm font-medium text-gray-900 mb-2">
					<?php _e( 'Cache schémas catégories', 'genius-reviews' ); ?>
				</label>
				<select id="gr-term-schema-refresh-interval"
						name="gr_term_schema_refresh_interval"
						class="block w-full border rounded-lg px-3 py-2">
					<?php foreach ( $term_schema_refresh_choices as $interval => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $interval ); ?>" <?php selected( $term_schema_refresh_interval, (int) $interval ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="text-xs text-gray-500 mt-2">
					<?php _e( 'Fréquence du recalcul automatique des JSON-LD catégories et attributs produits.', 'genius-reviews' ); ?>
				</p>
			</div>
		</div>
	</div>

	<div class="pt-2 border-t">
		<button type="submit" class="gr-btn">
			<?php _e( 'Sauvegarder', 'genius-reviews' ); ?>
		</button>
	</div>
</form>

				<div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-5 mb-8">
	<div>
		<div class="flex flex-col gap-2 mb-4 md:flex-row md:items-start md:justify-between">
			<div>
				<h3 class="text-base font-semibold text-gray-900"><?php _e( 'Shortcodes disponibles', 'genius-reviews' ); ?></h3>
				<p class="text-sm text-gray-600 mt-1">
					<?php _e( 'Insérez les blocs d’avis où vous le souhaitez dans vos pages, templates ou builders.', 'genius-reviews' ); ?>
				</p>
			</div>
		</div>

		<ul class="grid grid-cols-1 gap-4 lg:grid-cols-2">
			<li class="rounded-xl bg-gray-100 p-4">
				<p class="text-xs font-semibold text-gray-600 mb-2"><?php _e( 'Grille d’avis (produit courant ou ID précis)', 'genius-reviews' ); ?></p>
				<div class="flex flex-wrap items-center gap-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_grid]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy="[genius_reviews_grid]">
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_grid product_id="123" limit="6" sort="date_desc"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_grid product_id="123" limit="6" sort="date_desc"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_grid remove_spacing="1"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_grid remove_spacing="1"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
			</li>

			<li class="rounded-xl bg-gray-100 p-4">
				<p class="text-xs font-semibold text-gray-600 mb-2"><?php _e( 'Carrousel (sélection d’avis globales)', 'genius-reviews' ); ?></p>
				<div class="flex flex-wrap items-center gap-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_slider limit="12" sort="rating_desc"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_slider limit="12" sort="rating_desc"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_slider limit="12" sort="rating_desc" mode="compact"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_slider limit="12" sort="rating_desc" mode="compact"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_slider scope="category" limit="12" sort="rating_desc"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_slider scope="category" limit="12" sort="rating_desc"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_slider scope="category" term_id="12" taxonomy="product_cat" limit="12"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_slider scope="category" term_id="12" taxonomy="product_cat" limit="12"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
			</li>

			<li class="rounded-xl bg-gray-100 p-4">
				<p class="text-xs font-semibold text-gray-600 mb-2"><?php _e( 'Badge (produit courant ou ID précis)', 'genius-reviews' ); ?></p>
				<div class="flex flex-wrap items-center gap-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_badge]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy="[genius_reviews_badge]">
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_badge product_id="123"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_badge product_id="123"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_badge scope="category" mode="compact_rating"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_badge scope="category" mode="compact_rating"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_category_badge]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_category_badge]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<div class="flex flex-wrap items-center gap-2 mt-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_badge scope="category" term_id="12" taxonomy="product_cat" mode="compact_rating"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_badge scope="category" term_id="12" taxonomy="product_cat" mode="compact_rating"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
			</li>

			<li class="rounded-xl bg-gray-100 p-4">
				<p class="text-xs font-semibold text-gray-600 mb-2">
					<?php _e( 'Tous les avis (onglets Produits / Boutique + stats globales)', 'genius-reviews' ); ?>
				</p>
				<div class="flex flex-wrap items-center gap-2">
					<code class="px-2 py-1 bg-white rounded text-sm">[genius_reviews_all limit="12" sort="date_desc"]</code>
					<button type="button" class="gr-copy px-2 py-1 text-xs border rounded bg-white" data-copy='[genius_reviews_all limit="12" sort="date_desc"]'>
						<?php _e( 'Copier', 'genius-reviews' ); ?>
					</button>
				</div>
				<p class="text-[11px] text-gray-500 mt-3">
					<?php _e( 'Paramètres de tri disponibles : date_desc, date_asc, rating_desc, rating_asc.', 'genius-reviews' ); ?>
				</p>
			</li>
			</ul>
			</div>
					</div>

					<div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-5 my-8">
					<div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
						<div>
							<h2 class="text-xl font-semibold text-gray-900"><?php _e( 'Synchronisation', 'genius-reviews' ); ?></h2>
							<p class="text-sm text-gray-600 mt-1">
								<?php _e( 'Gardez les moyennes, volumes d’avis et schémas JSON-LD alignés avec les données importées.', 'genius-reviews' ); ?>
							</p>
						</div>
						<span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
							<?php esc_html_e( 'Maintenance', 'genius-reviews' ); ?>
						</span>
					</div>

					<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
						<div class="flex flex-col justify-between gap-4 rounded-xl border border-gray-200 bg-gray-100 p-4">
							<div>
								<h3 class="text-sm font-semibold text-gray-900"><?php _e( 'Notes produits', 'genius-reviews' ); ?></h3>
								<p class="text-xs text-gray-600 mt-1">
									<?php _e( 'Recalcule la moyenne et le volume stockés sur chaque fiche produit.', 'genius-reviews' ); ?>
								</p>
							</div>
							<div class="space-y-2">
								<button type="button"
									id="gr-sync-ratings"
									class="gr-btn w-full"
									data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_sync_products' ) ); ?>">
									<?php _e( 'Synchroniser', 'genius-reviews' ); ?>
								</button>
								<span id="gr-sync-status" class="block min-h-5 text-xs text-gray-600"></span>
							</div>
						</div>

						<div class="flex flex-col justify-between gap-4 rounded-xl border border-gray-200 bg-gray-100 p-4">
							<div>
								<h3 class="text-sm font-semibold text-gray-900"><?php _e( 'Schémas catégories', 'genius-reviews' ); ?></h3>
								<p class="text-xs text-gray-600 mt-1">
									<?php _e( 'Lance un recalcul manuel du cache JSON-LD catégories et attributs.', 'genius-reviews' ); ?>
								</p>
							</div>
							<div class="space-y-2">
								<button type="button"
									id="gr-refresh-term-schema"
									class="gr-btn gr-btn-secondary w-full"
									data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_refresh_term_schema_cache' ) ); ?>">
									<?php _e( 'Tester le cron', 'genius-reviews' ); ?>
								</button>
								<span id="gr-term-schema-status" class="block min-h-5 text-xs text-gray-600"></span>
							</div>
						</div>

						<div class="flex flex-col justify-between gap-4 rounded-xl border border-gray-200 bg-gray-100 p-4">
							<div>
								<h3 class="text-sm font-semibold text-gray-900"><?php _e( 'Cache schémas', 'genius-reviews' ); ?></h3>
								<p class="text-xs text-gray-600 mt-1">
									<?php _e( 'Vide le cache pour forcer une régénération propre au prochain passage.', 'genius-reviews' ); ?>
								</p>
							</div>
							<div class="space-y-2">
								<button type="button"
									id="gr-clear-term-schema"
									class="gr-btn gr-btn-dark w-full"
									data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'gr_clear_term_schema_cache' ) ); ?>">
									<?php _e( 'Vider le cache', 'genius-reviews' ); ?>
								</button>
								<span id="gr-clear-term-schema-status" class="block min-h-5 text-xs text-gray-600"></span>
							</div>
						</div>
					</div>
				</div>

<script>
	document.querySelectorAll('.gr-copy').forEach(btn => {
		btn.addEventListener('click', () => {
			const txt = btn.getAttribute('data-copy') || '';
			navigator.clipboard.writeText(txt).then(() => {
				btn.textContent = '<?php echo esc_js( __( 'Copié !', 'genius-reviews' ) ); ?>';
				setTimeout(()=>{ btn.textContent = '<?php echo esc_js( __( 'Copier', 'genius-reviews' ) ); ?>'; }, 1200);
			});
		});
	});

	const syncBtn = document.getElementById('gr-sync-ratings');
	if (syncBtn) {
		const syncStatus = document.getElementById('gr-sync-status');
		const syncingLabel = '<?php echo esc_js( __( 'Synchronisation en cours…', 'genius-reviews' ) ); ?>';
		const successLabel = '<?php echo esc_js( __( 'Synchronisation terminée : %d produit(s) mis à jour.', 'genius-reviews' ) ); ?>';
		const errorLabel = '<?php echo esc_js( __( 'Erreur lors de la synchronisation.', 'genius-reviews' ) ); ?>';

		syncBtn.addEventListener('click', () => {
			const ajaxUrl = syncBtn.getAttribute('data-ajax');
			const nonce = syncBtn.getAttribute('data-nonce');
			if (!ajaxUrl || !nonce) {
				return;
			}

			syncBtn.disabled = true;
			syncBtn.classList.add('opacity-70', 'cursor-not-allowed');
			syncStatus.textContent = syncingLabel;

			const body = 'action=gr_sync_products&nonce=' + encodeURIComponent(nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body
			})
				.then(response => response.json())
				.then(data => {
					if (!data || !data.success) {
						throw new Error('sync_failed');
					}
					const count = data.data && typeof data.data.count !== 'undefined'
						? parseInt(data.data.count, 10)
						: 0;
					const safeCount = isNaN(count) ? 0 : count;
					syncStatus.textContent = successLabel.replace('%d', safeCount);
				})
				.catch(() => {
					syncStatus.textContent = errorLabel;
				})
				.then(() => {
					syncBtn.disabled = false;
					syncBtn.classList.remove('opacity-70', 'cursor-not-allowed');
				});
		});
	}

	const termSchemaBtn = document.getElementById('gr-refresh-term-schema');
	if (termSchemaBtn) {
		const termSchemaStatus = document.getElementById('gr-term-schema-status');
		const runningLabel = '<?php echo esc_js( __( 'Recalcul des schémas en cours…', 'genius-reviews' ) ); ?>';
		const successLabel = '<?php echo esc_js( __( 'Recalcul terminé : %1$schemas schéma(s), %2$skipped ignoré(s), %terms terme(s) parcouru(s).', 'genius-reviews' ) ); ?>';
		const errorLabel = '<?php echo esc_js( __( 'Erreur lors du recalcul des schémas.', 'genius-reviews' ) ); ?>';

		termSchemaBtn.addEventListener('click', () => {
			const ajaxUrl = termSchemaBtn.getAttribute('data-ajax');
			const nonce = termSchemaBtn.getAttribute('data-nonce');
			if (!ajaxUrl || !nonce) {
				return;
			}

			termSchemaBtn.disabled = true;
			termSchemaBtn.classList.add('opacity-70', 'cursor-not-allowed');
			termSchemaStatus.textContent = runningLabel;

			const body = 'action=gr_refresh_term_schema_cache&nonce=' + encodeURIComponent(nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body
			})
				.then(response => response.json())
				.then(data => {
					if (!data || !data.success) {
						throw new Error('schema_refresh_failed');
					}

					const stats = data.data || {};
					termSchemaStatus.textContent = successLabel
						.replace('%schemas', parseInt(stats.schemas || 0, 10))
						.replace('%skipped', parseInt(stats.skipped || 0, 10))
						.replace('%terms', parseInt(stats.terms || 0, 10));
				})
				.catch(() => {
					termSchemaStatus.textContent = errorLabel;
				})
				.then(() => {
					termSchemaBtn.disabled = false;
					termSchemaBtn.classList.remove('opacity-70', 'cursor-not-allowed');
				});
		});
	}

	const clearTermSchemaBtn = document.getElementById('gr-clear-term-schema');
	if (clearTermSchemaBtn) {
		const clearTermSchemaStatus = document.getElementById('gr-clear-term-schema-status');
		const runningLabel = '<?php echo esc_js( __( 'Vidage du cache en cours…', 'genius-reviews' ) ); ?>';
		const successLabel = '<?php echo esc_js( __( 'Cache des schémas vidé.', 'genius-reviews' ) ); ?>';
		const errorLabel = '<?php echo esc_js( __( 'Erreur lors du vidage du cache.', 'genius-reviews' ) ); ?>';

		clearTermSchemaBtn.addEventListener('click', () => {
			const ajaxUrl = clearTermSchemaBtn.getAttribute('data-ajax');
			const nonce = clearTermSchemaBtn.getAttribute('data-nonce');
			if (!ajaxUrl || !nonce) {
				return;
			}

			clearTermSchemaBtn.disabled = true;
			clearTermSchemaBtn.classList.add('opacity-70', 'cursor-not-allowed');
			clearTermSchemaStatus.textContent = runningLabel;

			const body = 'action=gr_clear_term_schema_cache&nonce=' + encodeURIComponent(nonce);

			fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body
			})
				.then(response => response.json())
				.then(data => {
					if (!data || !data.success) {
						throw new Error('schema_clear_failed');
					}
					clearTermSchemaStatus.textContent = successLabel;
				})
				.catch(() => {
					clearTermSchemaStatus.textContent = errorLabel;
				})
				.then(() => {
					clearTermSchemaBtn.disabled = false;
					clearTermSchemaBtn.classList.remove('opacity-70', 'cursor-not-allowed');
				});
		});
	}
</script>

				<!-- Import -->
				<div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-6">
					<div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
						<div>
							<h2 class="text-xl font-semibold text-gray-900"><?php _e( 'Import CSV', 'genius-reviews' ); ?></h2>
							<p class="text-sm text-gray-600 mt-1">
								<?php _e( 'Importez les avis puis suivez les créations, mises à jour et lignes ignorées en temps réel.', 'genius-reviews' ); ?>
							</p>
						</div>
						<span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
							<?php esc_html_e( 'Avis produits', 'genius-reviews' ); ?>
						</span>
					</div>

					<form id="gr-upload-form" method="post" class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="gr_upload_csv" />
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gr_import_nonce' ) ); ?>" />

						<label class="block rounded-xl border border-gray-200 bg-gray-100 p-4">
							<span class="block text-sm font-semibold text-gray-900 mb-2"><?php _e( 'Fichier CSV', 'genius-reviews' ); ?></span>
							<input class="block w-full cursor-pointer rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700" type="file" name="csv" accept=".csv" required>
							<span class="block text-xs text-gray-500 mt-2">
								<?php _e( 'Format accepté : .csv avec les colonnes indiquées plus bas.', 'genius-reviews' ); ?>
							</span>
						</label>

						<div class="flex items-end">
							<button type="submit" class="gr-btn w-full md:w-auto">
								<?php _e( 'Lancer l’import', 'genius-reviews' ); ?>
							</button>
						</div>
					</form>

					<div id="gr-progress" class="hidden rounded-xl border border-gray-200 bg-gray-100 p-4 space-y-3">
						<div class="flex items-center justify-between gap-3 text-sm">
							<span id="gr-progress-label" class="font-medium text-gray-700"><?php _e( 'Préparation…', 'genius-reviews' ); ?></span>
							<span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700"><span id="gr-progress-percent">0</span>%</span>
						</div>
						<div class="h-2 w-full overflow-hidden rounded-full bg-white">
							<div id="gr-progress-bar" class="h-2 bg-emerald-500 transition-all duration-500 ease-in-out" style="width: 0%;"></div>
						</div>
						<div class="text-xs text-gray-600" id="gr-stats-line"></div>
					</div>

					<div id="gr-per-product" class="space-y-3">
						<div class="flex items-center justify-between gap-3">
							<h3 class="text-base font-semibold text-gray-900"><?php _e( 'Détail par produit', 'genius-reviews' ); ?></h3>
							<span class="text-xs text-gray-500"><?php _e( 'Résultat du dernier import', 'genius-reviews' ); ?></span>
						</div>
						<div class="overflow-x-auto rounded-xl border border-gray-200">
							<table class="min-w-full text-sm">
								<thead class="bg-gray-100">
									<tr class="text-left text-gray-700">
										<th class="px-4 py-3 font-semibold"><?php _e( 'Produit', 'genius-reviews' ); ?></th>
										<th class="px-4 py-3 font-semibold"><?php _e( 'ID', 'genius-reviews' ); ?></th>
										<th class="px-4 py-3 font-semibold"><?php _e( 'Ajoutés', 'genius-reviews' ); ?></th>
										<th class="px-4 py-3 font-semibold"><?php _e( 'MàJ', 'genius-reviews' ); ?></th>
										<th class="px-4 py-3 font-semibold"><?php _e( 'Ignorés', 'genius-reviews' ); ?></th>
										<th class="px-4 py-3 font-semibold"><?php _e( 'Moyenne', 'genius-reviews' ); ?></th>
										<th class="px-4 py-3 font-semibold"><?php _e( 'Total', 'genius-reviews' ); ?></th>
									</tr>
								</thead>
								<tbody id="gr-product-rows" class="divide-y divide-gray-200 bg-white"></tbody>
							</table>
						</div>
					</div>

					<div class="rounded-xl bg-gray-100 p-4 text-xs text-gray-600">
						<span class="font-semibold text-gray-700"><?php _e( 'Colonnes attendues :', 'genius-reviews' ); ?></span>
						<code class="mt-2 block whitespace-normal rounded bg-white px-2 py-1 text-[11px] leading-relaxed text-gray-700">title, body, rating, review_date, source, curated, reviewer_name, reviewer_email, product_id, product_handle, reply, reply_date, picture_urls, ip_address, location</code>
					</div>
				</div>


			</div>
		</div>
		<?php
	}
}
