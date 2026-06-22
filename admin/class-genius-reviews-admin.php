<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/admin
 * @author     Your Name <email@example.com>
 */
class Genius_Reviews_Admin {


	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles( $hook_suffix ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Genius_Reviews_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Genius_Reviews_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if ( $hook_suffix !== 'genius_review_page_gr-options' ) {
			return;
		}

		// Coloris CSS
		wp_enqueue_style(
			'coloris',
			'https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css',
			array(),
			null,
			'all'
		);

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'build/style-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Genius_Reviews_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Genius_Reviews_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		if ( $hook_suffix !== 'genius_review_page_gr-options' ) {
			return;
		}

		wp_enqueue_script(
			'coloris',
			'https://cdn.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js',
			array(),
			null,
			true
		);

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'build/genius-reviews-admin.bundle.js', array( 'jquery' ), $this->version, false );

		wp_localize_script(
			$this->plugin_name,
			'GR_ADMIN',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gr_import_nonce' ),
				'i18n'  => array(
					'uploading' => __( 'Téléversement…', 'genius-reviews' ),
					'importing' => __( 'Import en cours…', 'genius-reviews' ),
					'done'      => __( 'Import terminé', 'genius-reviews' ),
				),
			)
		);
	}
}
