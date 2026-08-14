<?php
/**
 * Plugin Name:       Water Hardness Lookup
 * Plugin URI:        https://krulldna.com/
 * Description:       Front-end postcode lookup returning local tap water hardness, a classification band and brand copy. Brand-agnostic and multi-country by data import.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Krull Design & Advertising
 * Author URI:        https://krulldna.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kdna-water-hardness
 * Domain Path:       /languages
 *
 * @package KDNA_Water_Hardness
 */

// Block direct access. Nothing in this file should run outside WordPress.
defined( 'ABSPATH' ) || exit;

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 * KDNA_WH_VERSION    Plugin version, used to bust asset caches later.
 * KDNA_WH_DB_VERSION Schema version. Bump this whenever a table changes so the
 *                    upgrade routine knows to re-run dbDelta().
 */
define( 'KDNA_WH_VERSION', '1.0.0' );
define( 'KDNA_WH_DB_VERSION', '1.0.0' );
define( 'KDNA_WH_FILE', __FILE__ );
define( 'KDNA_WH_PATH', plugin_dir_path( __FILE__ ) );
define( 'KDNA_WH_URL', plugin_dir_url( __FILE__ ) );
define( 'KDNA_WH_SLUG', 'kdna-water-hardness' );

/*
 * ---------------------------------------------------------------------------
 * Includes
 * ---------------------------------------------------------------------------
 * The main file stays deliberately thin: constants, includes and hooks only.
 * All real logic lives in the classes below.
 */
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-db.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-units.php';
require_once KDNA_WH_PATH . 'includes/sources/interface-kdna-wh-source.php';
require_once KDNA_WH_PATH . 'includes/sources/class-kdna-wh-source-csv.php';
require_once KDNA_WH_PATH . 'includes/sources/class-kdna-wh-source-api.php';
require_once KDNA_WH_PATH . 'includes/sources/class-kdna-wh-source-json.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-sources.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-countries.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-bands.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-geo.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-lookup.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-rest.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-shortcode.php';
require_once KDNA_WH_PATH . 'includes/class-kdna-wh-activator.php';

if ( is_admin() ) {
	require_once KDNA_WH_PATH . 'includes/class-kdna-wh-csv.php';
	require_once KDNA_WH_PATH . 'includes/class-kdna-wh-importer.php';
	require_once KDNA_WH_PATH . 'admin/class-kdna-wh-admin.php';
	require_once KDNA_WH_PATH . 'admin/class-kdna-wh-admin-import.php';
	require_once KDNA_WH_PATH . 'admin/class-kdna-wh-admin-settings.php';
	require_once KDNA_WH_PATH . 'admin/class-kdna-wh-admin-log.php';
}

/*
 * ---------------------------------------------------------------------------
 * Lifecycle hooks
 * ---------------------------------------------------------------------------
 */

// Create the three custom tables when the plugin is activated.
register_activation_hook( __FILE__, array( 'KDNA_WH_Activator', 'activate' ) );

/**
 * Runs on every load. Checks whether the stored schema version matches the
 * current one and re-runs the table creation if it does not. This covers the
 * case where the plugin folder is replaced by FTP without a deactivate and
 * reactivate cycle, which is how updates usually reach a live site.
 */
add_action( 'plugins_loaded', array( 'KDNA_WH_Activator', 'maybe_upgrade' ) );

/**
 * Loads the translation files.
 *
 * Hooked to init rather than plugins_loaded because WordPress 6.7 and later
 * emit a notice when translations are loaded before init.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'kdna-water-hardness', false, dirname( plugin_basename( KDNA_WH_FILE ) ) . '/languages' );
	}
);

/*
 * ---------------------------------------------------------------------------
 * Front end
 * ---------------------------------------------------------------------------
 */

// The lookup endpoint. Public and read-only.
add_action( 'rest_api_init', array( 'KDNA_WH_REST', 'register_routes' ) );

// Country detection, and the monthly refresh of its database.
KDNA_WH_Geo::init();

// The shortcode, and the assets it needs. Registered on every request so the
// shortcode works wherever it appears, but only enqueued where it renders.
KDNA_WH_Shortcode::init();

/*
 * ---------------------------------------------------------------------------
 * Elementor
 * ---------------------------------------------------------------------------
 * The widget is the primary front end, but Elementor is not a dependency: the
 * shortcode works without it and nothing here loads unless Elementor is there.
 */
add_action(
	'elementor/loaded',
	function () {
		require_once KDNA_WH_PATH . 'elementor/class-kdna-wh-elementor.php';

		KDNA_WH_Elementor::init();

		if ( is_admin() ) {
			add_action( 'admin_notices', array( 'KDNA_WH_Elementor', 'version_notice' ) );
		}
	}
);

/*
 * ---------------------------------------------------------------------------
 * Admin
 * ---------------------------------------------------------------------------
 */

if ( is_admin() ) {
	add_action( 'admin_menu', array( 'KDNA_WH_Admin', 'register_menu' ) );
	add_action( 'admin_enqueue_scripts', array( 'KDNA_WH_Admin', 'enqueue_assets' ) );

	KDNA_WH_Admin_Import::init();
	KDNA_WH_Admin_Settings::init();
	KDNA_WH_Admin_Log::init();
}

/**
 * Removes import files left behind by an import that was never finished.
 *
 * The event is registered on activation and cleared on deactivation, so a
 * deactivated plugin leaves no scheduled work behind.
 */
add_action( 'kdna_wh_cleanup_imports', array( 'KDNA_WH_Importer', 'cleanup_orphans' ) );

/**
 * The cleanup callback lives in a class that is only loaded in the admin, so
 * on a cron request it has to be pulled in explicitly.
 */
add_action(
	'kdna_wh_cleanup_imports',
	function () {
		if ( ! class_exists( 'KDNA_WH_Importer' ) ) {
			require_once KDNA_WH_PATH . 'includes/class-kdna-wh-csv.php';
			require_once KDNA_WH_PATH . 'includes/class-kdna-wh-importer.php';
		}
	},
	5
);

// Clear the scheduled cleanup when the plugin is switched off.
register_deactivation_hook( __FILE__, array( 'KDNA_WH_Activator', 'deactivate' ) );
