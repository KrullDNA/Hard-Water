<?php
/**
 * Activation and schema creation.
 *
 * Three custom tables rather than post types, because the data is relational,
 * runs to hundreds of thousands of postcode rows, and is never edited one row
 * at a time through the WordPress editor.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Activator
 */
class KDNA_WH_Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();

		update_option( KDNA_WH_DB::OPT_DB_VERSION, KDNA_WH_DB_VERSION );

		// Records when the plugin was first installed, useful later for
		// distinguishing a fresh install from an upgrade.
		if ( ! get_option( 'kdna_wh_installed_at' ) ) {
			update_option( 'kdna_wh_installed_at', current_time( 'mysql' ) );
		}
	}

	/**
	 * Re-runs table creation when the schema version has moved on. Hooked to
	 * plugins_loaded so an update applied by FTP, without a deactivate and
	 * reactivate cycle, still lands its schema changes.
	 *
	 * This compares a stored option and nothing else. Checking whether the
	 * tables really exist would mean three SHOW TABLES queries on every page
	 * load of the site, which is far too high a price for a situation that
	 * only arises if someone drops a table by hand. The Settings screen
	 * reports a missing table instead.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( KDNA_WH_DB_VERSION === get_option( KDNA_WH_DB::OPT_DB_VERSION ) ) {
			return;
		}

		self::create_tables();

		update_option( KDNA_WH_DB::OPT_DB_VERSION, KDNA_WH_DB_VERSION );
	}

	/**
	 * Creates or updates the three tables with dbDelta().
	 *
	 * dbDelta is fussy about formatting: one field per line, two spaces after
	 * PRIMARY KEY, and every index named. The SQL below follows those rules,
	 * so do not reformat it.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$zones     = KDNA_WH_DB::zones_table();
		$postcodes = KDNA_WH_DB::postcodes_table();
		$lookups   = KDNA_WH_DB::lookups_table();

		/*
		 * Supply zones. One row per named zone per utility, holding the
		 * canonical hardness figure and the document it came from.
		 *
		 * source_url and source_date are not optional in practice: every
		 * number shown to a customer must be traceable to a published utility
		 * report, both for credibility and because the figures date.
		 *
		 * confidence defaults to estimated so unverified data is never
		 * presented as authoritative by accident.
		 */
		$sql_zones = "CREATE TABLE {$zones} (
			zone_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			country_code char(2) NOT NULL DEFAULT '',
			utility_name varchar(255) NOT NULL DEFAULT '',
			zone_name varchar(255) NOT NULL DEFAULT '',
			hardness_caco3 decimal(7,2) NOT NULL DEFAULT 0.00,
			confidence enum('verified','estimated') NOT NULL DEFAULT 'estimated',
			source_url text NULL,
			source_date date NULL DEFAULT NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (zone_id),
			KEY country_code (country_code),
			KEY confidence (confidence),
			KEY source_date (source_date)
		) {$charset_collate};";

		/*
		 * Postcode to zone mappings, many to many.
		 *
		 * A postcode appearing more than once is valid and expected, because
		 * postcodes routinely span two or more supply zones. varchar(12)
		 * accommodates every format the plugin will meet: Australia 4 digits,
		 * UK up to 8 alphanumeric, US 5 or 9, Canada 6.
		 */
		$sql_postcodes = "CREATE TABLE {$postcodes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			country_code char(2) NOT NULL DEFAULT '',
			postcode varchar(12) NOT NULL DEFAULT '',
			zone_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY country_postcode (country_code,postcode),
			KEY zone_id (zone_id)
		) {$charset_collate};";

		/*
		 * Lookup log.
		 *
		 * The hardness and band are denormalised on purpose, so historic
		 * results stay accurate after the underlying data is refreshed.
		 * No IP address, no email, no personal identifiers: postcode plus
		 * timestamp is enough for the geographic insight and keeps the plugin
		 * clear of personal data handling.
		 */
		$sql_lookups = "CREATE TABLE {$lookups} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			country_code char(2) NOT NULL DEFAULT '',
			postcode varchar(12) NOT NULL DEFAULT '',
			hardness_caco3 decimal(7,2) NULL DEFAULT NULL,
			band varchar(32) NOT NULL DEFAULT '',
			created_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY country_postcode (country_code,postcode),
			KEY band (band),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_zones );
		dbDelta( $sql_postcodes );
		dbDelta( $sql_lookups );
	}

	/**
	 * Drops the three tables and the plugin's options.
	 *
	 * Deliberately not wired to deactivation or uninstall. Deactivating a
	 * plugin must never destroy a customer's imported data, and the data set
	 * here represents real compilation cost. This exists so a future uninstall
	 * routine, or a developer cleaning up a test site, has one honest place to
	 * call.
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		foreach ( KDNA_WH_DB::tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names are internal, and DROP cannot be prepared.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( KDNA_WH_DB::OPT_DB_VERSION );
		delete_option( 'kdna_wh_installed_at' );
	}
}
