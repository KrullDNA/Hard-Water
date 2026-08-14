<?php
/**
 * Admin menu registration.
 *
 * Stage 1 registers the top-level menu and the Settings page only. Data Import
 * and Lookup Log are added by later stages, and are deliberately absent rather
 * than present and empty.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Admin
 */
class KDNA_WH_Admin {

	/**
	 * Menu slug for the top-level page. Sub-pages in later stages append to
	 * this, e.g. kdna-water-hardness-import.
	 */
	const MENU_SLUG = 'kdna-water-hardness';

	/**
	 * Capability required to see and use any of the plugin's admin screens.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Registers the top-level menu and its Settings sub-page.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'Water Hardness', 'kdna-water-hardness' ),
			__( 'Water Hardness', 'kdna-water-hardness' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-location-alt',
			58
		);

		// Renaming the first sub-page stops WordPress repeating the top-level
		// title underneath itself.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Water Hardness Settings', 'kdna-water-hardness' ),
			__( 'Settings', 'kdna-water-hardness' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Water Hardness Data', 'kdna-water-hardness' ),
			__( 'Data Import', 'kdna-water-hardness' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-import',
			array( __CLASS__, 'render_import_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Water Hardness Lookup Log', 'kdna-water-hardness' ),
			__( 'Lookup Log', 'kdna-water-hardness' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-log',
			array( __CLASS__, 'render_log_page' )
		);
	}

	/**
	 * Loads the Lookup Log view.
	 *
	 * @return void
	 */
	public static function render_log_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kdna-water-hardness' ) );
		}

		require KDNA_WH_PATH . 'admin/views/log.php';
	}

	/**
	 * Loads the Data Import view.
	 *
	 * @return void
	 */
	public static function render_import_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kdna-water-hardness' ) );
		}

		require KDNA_WH_PATH . 'admin/views/import.php';
	}

	/**
	 * Loads the Settings view.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kdna-water-hardness' ) );
		}

		require KDNA_WH_PATH . 'admin/views/settings.php';
	}

	/**
	 * Loads the admin stylesheet, and only on this plugin's screens.
	 *
	 * @param string $hook_suffix Current admin page identifier.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'kdna-wh-admin',
			KDNA_WH_URL . 'admin/admin.css',
			array(),
			KDNA_WH_VERSION
		);

		wp_enqueue_script(
			'kdna-wh-admin',
			KDNA_WH_URL . 'admin/admin.js',
			array(),
			KDNA_WH_VERSION,
			true
		);
	}

	/**
	 * Gathers the numbers shown in the status panel: whether each table exists
	 * and how many rows it holds.
	 *
	 * @return array
	 */
	public static function get_status() {
		$status = array();

		foreach ( KDNA_WH_DB::tables() as $key => $table ) {
			$exists = KDNA_WH_DB::table_exists( $table );
			$rows   = 0;

			if ( $exists ) {
				switch ( $key ) {
					case 'zones':
						$rows = KDNA_WH_DB::count_zones();
						break;
					case 'postcodes':
						$rows = KDNA_WH_DB::count_postcodes();
						break;
					case 'lookups':
						$rows = KDNA_WH_DB::count_lookups();
						break;
				}
			}

			$status[ $key ] = array(
				'table'  => $table,
				'exists' => $exists,
				'rows'   => $rows,
			);
		}

		return $status;
	}
}
