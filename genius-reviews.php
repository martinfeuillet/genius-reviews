<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Genius_Reviews
 *
 * @wordpress-plugin
 * Plugin Name:       Genius Reviews
 * Plugin URI:        http://example.com/genius-reviews-uri/
 * Description: Import d’avis via CSV, affichage sur les fiches produits WooCommerce, JSON-LD optimisé SEO.
 * Version:           1.2.5
 * Author:            Ingenius Agency
 * Author URI:        https://ingenius.agency/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       genius-reviews
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'GENIUS_REVIEWS_VERSION', '1.2.5' );
define( 'GR_PATH', plugin_dir_path( __FILE__ ) );


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-genius-reviews-activator.php
 */
function activate_genius_reviews( $network_wide ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-genius-reviews-activator.php';
	Genius_Reviews_Activator::activate( $network_wide );
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-genius-reviews-deactivator.php
 */
function deactivate_genius_reviews() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-genius-reviews-deactivator.php';
	Genius_Reviews_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_genius_reviews' );
register_deactivation_hook( __FILE__, 'deactivate_genius_reviews' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-genius-reviews.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_genius_reviews() {

	$plugin = new Genius_Reviews();
	$plugin->run();
}
run_genius_reviews();


if ( ! class_exists( 'Puc_v5_Factory' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/martinfeuillet/genius-reviews/',
	__FILE__,
	'genius-reviews'
);


$updateChecker->setBranch( 'master' );
