<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Genius_Reviews
 * @subpackage Genius_Reviews/includes
 * @author     Your Name <email@example.com>
 */
class Genius_Reviews_Deactivator {


	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		if ( ! class_exists( 'Genius_Reviews_Term_Schema_Cache' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'classes/class-genius-reviews-term-schema-cache.php';
		}

		Genius_Reviews_Term_Schema_Cache::clear_schedule();
		flush_rewrite_rules();
	}
}
