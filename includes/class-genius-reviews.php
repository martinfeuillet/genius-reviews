<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/includes
 * @author     Your Name <email@example.com>
 */
class Genius_Reviews {


	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Genius_Reviews_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'GENIUS_REVIEWS_VERSION' ) ) {
			$this->version = GENIUS_REVIEWS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'genius-reviews';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->register_cpt_hooks();
		$this->define_maintenance_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Genius_Reviews_Loader. Orchestrates the hooks of the plugin.
	 * - Genius_Reviews_i18n. Defines internationalization functionality.
	 * - Genius_Reviews_Admin. Defines all hooks for the admin area.
	 * - Genius_Reviews_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-genius-reviews-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-genius-reviews-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-genius-reviews-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( __DIR__ ) . 'public/class-genius-reviews-public.php';

		// Reviews CPT
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-cpt.php';

		// Page Options/Import (menu + vue + ajax)
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-genius-reviews-admin-page.php';

		// Cache JSON-LD catégories et attributs produit
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-term-schema-cache.php';
		Genius_Reviews_Term_Schema_Cache::init();

		// Output JSON LD
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-output-json-ld.php';

		// Render partagé
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-render.php';

		// Gutenberg Block
		if ( function_exists( 'register_block_type' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-block.php';
			Genius_Reviews_Block::init();
		}

		// Elementor widget
		// if (did_action('elementor/loaded')) {
		// require_once plugin_dir_path(dirname(__FILE__)) . 'classes/class-genius-reviews-elementor.php';
		// add_action('elementor/widgets/register', function ($widgets_manager) {
		// $widgets_manager->register(new Genius_Reviews_Elementor());
		// });
		// }

		// Shortcodes
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-shortcodes.php';

		// AJAX
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-ajax.php';

		// Query Helper
		require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-query-helper.php';

		$this->loader = new Genius_Reviews_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Genius_Reviews_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Genius_Reviews_i18n();

		// $this->loader->add_action('init', $plugin_i18n, 'load_plugin_textdomain', 1);

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		// Assets globaux
		$plugin_admin = new Genius_Reviews_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles', 10, 1 );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Page Options/Import
		$this->loader->add_action( 'admin_menu', 'Genius_Reviews_Admin_Page', 'add_menu' );
		$this->loader->add_action( 'wp_ajax_gr_upload_csv', 'Genius_Reviews_Ajax', 'ajax_upload_csv' );
		$this->loader->add_action( 'wp_ajax_gr_process_chunk', 'Genius_Reviews_Ajax', 'ajax_process_chunk' );
		$this->loader->add_action( 'wp_ajax_gr_sync_products', 'Genius_Reviews_Ajax', 'ajax_sync_products' );
		$this->loader->add_action( 'wp_ajax_gr_refresh_term_schema_cache', 'Genius_Reviews_Ajax', 'ajax_refresh_term_schema_cache' );
		$this->loader->add_action( 'wp_ajax_gr_clear_term_schema_cache', 'Genius_Reviews_Ajax', 'ajax_clear_term_schema_cache' );
		add_filter( 'upload_mimes', array( $this, 'allow_csv_uploads' ) );
	}

	public function allow_csv_uploads( $mimes ) {
		if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_woocommerce' ) ) {
			$mimes['csv'] = 'text/csv';
		}
		return $mimes;
	}


	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Genius_Reviews_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		new Genius_Reviews_Output_Json_Ld();

		$this->loader->add_action( 'wp_head', 'Genius_Reviews_Render', 'inject_brand_color' );
		$this->loader->add_action( 'admin_head', 'Genius_Reviews_Render', 'inject_brand_color' );

		$this->loader->add_action( 'wp_ajax_load_reviews', 'Genius_Reviews_Ajax', 'gr_load_reviews' );
		$this->loader->add_action( 'wp_ajax_nopriv_load_reviews', 'Genius_Reviews_Ajax', 'gr_load_reviews' );

		$active_reviews_on_product_page  = get_option( 'gr_option_active_reviews_on_product_page' );
		$active_badge_on_product_page    = get_option( 'gr_option_active_badge_on_product_page' );
		$active_badge_on_collection_page = get_option( 'gr_option_active_badge_on_collection_page' );
		$fallback_reviews_all            = get_option( 'gr_option_fallback_reviews_all' );

		$this->loader->add_action( 'init', 'Genius_Reviews', 'register_shortcodes' );

		if ( $active_reviews_on_product_page != 0 ) {
			add_action(
				'woocommerce_after_single_product',
				function () {
					global $product;
					if ( ! $product ) {
						return;
					}

					echo '<div class="gr-single-product-reviews">';
					echo Genius_Reviews_Render::grid(
						array(
							'product_id' => $product->get_id(),
							'limit'      => 6,
						)
					);
					echo '</div>';
				},
				1
			);
		}

		if ( $active_badge_on_product_page != 0 ) {
			add_action(
				'woocommerce_single_product_summary',
				function () use ( $fallback_reviews_all ) {
					global $product;
					if ( ! $product ) {
						return;
					}

					$badge_args = array(
						'product_id' => $product->get_id(),
					);

					if ( ! empty( $fallback_reviews_all ) ) {
						$badge_args['use_global_count'] = 1;
					}

					echo Genius_Reviews_Render::badge( $badge_args );
				},
				6
			);
		}

		if ( $active_badge_on_collection_page != 0 ) {
			add_action(
				'woocommerce_after_shop_loop_item_title',
				function () {
					global $product;
					if ( ! $product ) {
						return;
					}

					echo Genius_Reviews_Render::badge(
						array(
							'product_id' => $product->get_id(),
						)
					);
				},
				6
			);
		}
	}

	private function register_cpt_hooks() {
		$this->loader->add_action( 'init', 'Genius_Reviews_CPT', 'register' );
		$this->loader->add_action( 'add_meta_boxes', 'Genius_Reviews_CPT', 'register_metaboxes' );
		$this->loader->add_action( 'save_post_genius_review', 'Genius_Reviews_CPT', 'save_metabox', 10, 3 );
		$this->loader->add_action( 'save_post_genius_review', 'Genius_Reviews_CPT', 'sync_product_on_save', 20, 3 );
		$this->loader->add_action( 'before_delete_post', 'Genius_Reviews_CPT', 'sync_product_on_status_change' );
		$this->loader->add_action( 'trashed_post', 'Genius_Reviews_CPT', 'sync_product_on_status_change' );
		$this->loader->add_action( 'untrashed_post', 'Genius_Reviews_CPT', 'sync_product_on_status_change' );
		$this->loader->add_filter( 'manage_edit-genius_review_columns', 'Genius_Reviews_CPT', 'admin_columns' );
		$this->loader->add_action( 'manage_genius_review_posts_custom_column', 'Genius_Reviews_CPT', 'render_admin_column', 10, 2 );
		$this->loader->add_filter( 'manage_edit-genius_review_sortable_columns', 'Genius_Reviews_CPT', 'admin_sortable_columns' );
		$this->loader->add_action( 'restrict_manage_posts', 'Genius_Reviews_CPT', 'admin_filters_markup' );
		$this->loader->add_action( 'pre_get_posts', 'Genius_Reviews_CPT', 'admin_handle_query' );
	}

	/**
	 * Register miscellaneous maintenance hooks.
	 *
	 * @since 1.2.0.6
	 * @return void
	 */
	private function define_maintenance_hooks() {
		$this->loader->add_action( 'upgrader_process_complete', $this, 'purge_wp_rocket_after_update', 10, 2 );
		$this->loader->add_action( 'admin_init', $this, 'purge_wp_rocket_on_first_load' );
		$this->loader->add_action( 'admin_init', $this, 'maybe_install_term_schema_cache' );
	}

	/**
	 * Ensure DB upgrades also run after plugin updates, not only activation.
	 *
	 * @return void
	 */
	public function maybe_install_term_schema_cache() {
		$installed_version = get_option( 'gr_term_schema_cache_version', '' );

		if ( is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'schedule_refresh' ) ) ) {
			Genius_Reviews_Term_Schema_Cache::schedule_refresh();
		}

		if ( $installed_version === GENIUS_REVIEWS_VERSION ) {
			return;
		}

		if ( is_callable( array( 'Genius_Reviews_Term_Schema_Cache', 'install' ) ) ) {
			Genius_Reviews_Term_Schema_Cache::install();
			Genius_Reviews_Term_Schema_Cache::queue_refresh();
			update_option( 'gr_term_schema_cache_version', GENIUS_REVIEWS_VERSION, false );
		}
	}

	/**
	 * Flush WP Rocket cache after the plugin has been updated.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Additional context.
	 * @return void
	 */
	public function purge_wp_rocket_after_update( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
			return;
		}

		$updated_plugins = array();

		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$updated_plugins = $hook_extra['plugins'];
		} elseif ( ! empty( $hook_extra['plugin'] ) ) {
			$updated_plugins = array( $hook_extra['plugin'] );
		}

		if ( empty( $updated_plugins ) ) {
			return;
		}

		$plugin_basename = plugin_basename( GR_PATH . 'genius-reviews.php' );

		if ( ! in_array( $plugin_basename, $updated_plugins, true ) ) {
			return;
		}

		update_option( 'gr_wp_rocket_cache_needs_flush', 1, false );

		if ( $this->clear_wp_rocket_cache() ) {
			update_option( 'gr_wp_rocket_cache_needs_flush', 0, false );
		}
	}

	/**
	 * Trigger WP Rocket cache and CSS cleanup.
	 *
	 * @return bool True if WP Rocket tools were executed, false otherwise.
	 */
	public function clear_wp_rocket_cache() {
		if ( ! function_exists( 'rocket_clean_domain' ) ) {
			return false;
		}

		rocket_clean_domain();

		if ( function_exists( 'rocket_clean_cache_busting' ) ) {
			rocket_clean_cache_busting();
		}

		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify();
		}

		if ( function_exists( 'run_rocket_sitemap_preload' ) ) {
			run_rocket_sitemap_preload();
		}

		if ( function_exists( 'truncate_used_css' ) ) {
			truncate_used_css();
		}

		return true;
	}

	/**
	 * Purge cache automatically the first time an admin loads the dashboard after install/update.
	 *
	 * @return void
	 */
	public function purge_wp_rocket_on_first_load() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_option( 'gr_wp_rocket_cache_needs_flush', 0 ) ) {
			return;
		}

		$this->clear_wp_rocket_cache();

		update_option( 'gr_wp_rocket_cache_needs_flush', 0, false );
	}

	/**
	 * Enregistre les shortcodes du plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_shortcodes() {
		add_shortcode( 'genius_reviews_grid', array( 'Genius_Reviews_Shortcodes', 'grid' ) );
		add_shortcode( 'genius_reviews_slider', array( 'Genius_Reviews_Shortcodes', 'slider' ) );
		add_shortcode( 'genius_reviews_badge', array( 'Genius_Reviews_Shortcodes', 'badge' ) );
		add_shortcode( 'genius_reviews_category_badge', array( 'Genius_Reviews_Shortcodes', 'category_badge' ) );
		add_shortcode( 'genius_reviews_summary', array( 'Genius_Reviews_Shortcodes', 'summary' ) );
		add_shortcode( 'genius_reviews_all', array( 'Genius_Reviews_Shortcodes', 'grid_all_reviews' ) );
	}



	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Genius_Reviews_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
