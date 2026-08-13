<?php
/**
 * Database access layer.
 *
 * Every query in the plugin goes through this class. Nothing else talks to
 * $wpdb directly. All values passed into SQL use $wpdb->prepare().
 *
 * Table names are built from $wpdb->prefix internally and never come from
 * user input, so they are interpolated directly, which is standard practice
 * in WordPress and the only way to reference a table in a prepared statement
 * on older versions.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_DB
 */
class KDNA_WH_DB {

	/**
	 * Option key holding the installed schema version.
	 */
	const OPT_DB_VERSION = 'kdna_wh_db_version';

	/*
	 * -----------------------------------------------------------------------
	 * Table names
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Supply zones and their hardness figures.
	 *
	 * @return string
	 */
	public static function zones_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_wh_zones';
	}

	/**
	 * Postcode to zone mappings. Many to many by design.
	 *
	 * @return string
	 */
	public static function postcodes_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_wh_postcodes';
	}

	/**
	 * Anonymous log of every lookup performed.
	 *
	 * @return string
	 */
	public static function lookups_table() {
		global $wpdb;
		return $wpdb->prefix . 'kdna_wh_lookups';
	}

	/**
	 * All three tables, keyed by short name. Used by the activator and the
	 * admin status panel.
	 *
	 * @return array
	 */
	public static function tables() {
		return array(
			'zones'     => self::zones_table(),
			'postcodes' => self::postcodes_table(),
			'lookups'   => self::lookups_table(),
		);
	}

	/**
	 * Checks whether a given table exists in the database.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	public static function table_exists( $table ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- schema check, cannot use an API.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return $found === $table;
	}

	/**
	 * True only when all three tables are present.
	 *
	 * @return bool
	 */
	public static function all_tables_exist() {
		foreach ( self::tables() as $table ) {
			if ( ! self::table_exists( $table ) ) {
				return false;
			}
		}
		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Zones
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Inserts a supply zone.
	 *
	 * Confidence defaults to estimated so unverified data can never be
	 * presented as authoritative by accident.
	 *
	 * @param array $data {
	 *     @type string $country_code   ISO 3166-1 alpha-2.
	 *     @type string $utility_name   Water utility responsible for the zone.
	 *     @type string $zone_name      Named supply zone.
	 *     @type float  $hardness_caco3 Canonical value in mg/L as CaCO3.
	 *     @type string $confidence     verified or estimated.
	 *     @type string $source_url     Link to the published report.
	 *     @type string $source_date    Publication date, Y-m-d.
	 * }
	 * @return int|WP_Error New zone_id, or WP_Error on failure.
	 */
	public static function insert_zone( array $data ) {
		global $wpdb;

		$row = self::prepare_zone_row( $data );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$row['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$result = $wpdb->insert( self::zones_table(), $row, self::zone_formats( $row ) );

		if ( false === $result ) {
			return new WP_Error( 'kdna_wh_insert_failed', __( 'Could not save the supply zone.', 'kdna-water-hardness' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates an existing supply zone.
	 *
	 * @param int   $zone_id Zone to update.
	 * @param array $data    Any subset of the zone fields.
	 * @return bool|WP_Error True on success.
	 */
	public static function update_zone( $zone_id, array $data ) {
		global $wpdb;

		$zone_id = absint( $zone_id );

		if ( ! $zone_id ) {
			return new WP_Error( 'kdna_wh_bad_zone_id', __( 'Invalid zone reference.', 'kdna-water-hardness' ) );
		}

		$row = self::prepare_zone_row( $data, true );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$row['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$result = $wpdb->update(
			self::zones_table(),
			$row,
			array( 'zone_id' => $zone_id ),
			self::zone_formats( $row ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'kdna_wh_update_failed', __( 'Could not update the supply zone.', 'kdna-water-hardness' ) );
		}

		return true;
	}

	/**
	 * Fetches a single zone.
	 *
	 * @param int $zone_id Zone identifier.
	 * @return array|null Associative row, or null when not found.
	 */
	public static function get_zone( $zone_id ) {
		global $wpdb;

		$table = self::zones_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE zone_id = %d", absint( $zone_id ) ), ARRAY_A );

		return $row ? self::cast_zone_row( $row ) : null;
	}

	/**
	 * Fetches zones with optional filtering and paging.
	 *
	 * @param array $args {
	 *     @type string $country_code Restrict to one country.
	 *     @type string $confidence   verified or estimated.
	 *     @type string $search       Matches utility or zone name.
	 *     @type int    $limit        Default 100. Use 0 for no limit.
	 *     @type int    $offset       Default 0.
	 * }
	 * @return array List of associative rows.
	 */
	public static function get_zones( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'country_code' => '',
				'confidence'   => '',
				'search'       => '',
				'limit'        => 100,
				'offset'       => 0,
			)
		);

		$table  = self::zones_table();
		$where  = array( '1=1' );
		$params = array();

		if ( $args['country_code'] ) {
			$where[]  = 'country_code = %s';
			$params[] = self::normalise_country( $args['country_code'] );
		}

		if ( $args['confidence'] ) {
			$where[]  = 'confidence = %s';
			$params[] = self::normalise_confidence( $args['confidence'] );
		}

		if ( $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( utility_name LIKE %s OR zone_name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY country_code ASC, utility_name ASC, zone_name ASC';

		$limit = absint( $args['limit'] );

		if ( $limit ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = absint( $args['offset'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		return array_map( array( __CLASS__, 'cast_zone_row' ), (array) $rows );
	}

	/**
	 * Counts zones, optionally for one country.
	 *
	 * @param string $country_code Optional ISO country code.
	 * @return int
	 */
	public static function count_zones( $country_code = '' ) {
		return self::count_rows( self::zones_table(), $country_code );
	}

	/**
	 * Deletes every zone for a country, and the postcode mappings that point
	 * at those zones. Used by the data browser in Stage 2.
	 *
	 * @param string $country_code ISO country code.
	 * @return int Number of zones removed.
	 */
	public static function delete_zones_by_country( $country_code ) {
		global $wpdb;

		$country = self::normalise_country( $country_code );

		if ( ! $country ) {
			return 0;
		}

		// Remove the mappings first so no orphan rows are left behind.
		self::delete_postcodes_by_country( $country );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$deleted = $wpdb->delete( self::zones_table(), array( 'country_code' => $country ), array( '%s' ) );

		return (int) $deleted;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Postcode mappings
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Maps one postcode to one zone. A postcode appearing more than once is
	 * valid and expected, because postcodes routinely span supply zones.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $postcode     Raw postcode, normalised here.
	 * @param int    $zone_id      Zone to link to.
	 * @return int|WP_Error Row id.
	 */
	public static function insert_postcode( $country_code, $postcode, $zone_id ) {
		global $wpdb;

		$country  = self::normalise_country( $country_code );
		$postcode = self::normalise_postcode( $postcode );
		$zone_id  = absint( $zone_id );

		if ( ! $country || ! $postcode || ! $zone_id ) {
			return new WP_Error( 'kdna_wh_bad_mapping', __( 'A postcode mapping needs a country, a postcode and a zone.', 'kdna-water-hardness' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$result = $wpdb->insert(
			self::postcodes_table(),
			array(
				'country_code' => $country,
				'postcode'     => $postcode,
				'zone_id'      => $zone_id,
			),
			array( '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'kdna_wh_insert_failed', __( 'Could not save the postcode mapping.', 'kdna-water-hardness' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Inserts many postcode mappings in a single query.
	 *
	 * Postcode files run to tens of thousands of rows, so inserting one at a
	 * time is far too slow. The importer in Stage 2 calls this in batches.
	 *
	 * @param array $rows List of arrays with country_code, postcode, zone_id.
	 * @return int Number of rows inserted.
	 */
	public static function insert_postcodes_bulk( array $rows ) {
		global $wpdb;

		$values       = array();
		$placeholders = array();

		foreach ( $rows as $row ) {
			$country  = self::normalise_country( isset( $row['country_code'] ) ? $row['country_code'] : '' );
			$postcode = self::normalise_postcode( isset( $row['postcode'] ) ? $row['postcode'] : '' );
			$zone_id  = absint( isset( $row['zone_id'] ) ? $row['zone_id'] : 0 );

			if ( ! $country || ! $postcode || ! $zone_id ) {
				continue;
			}

			$placeholders[] = '(%s, %s, %d)';
			$values[]       = $country;
			$values[]       = $postcode;
			$values[]       = $zone_id;
		}

		if ( ! $placeholders ) {
			return 0;
		}

		$table = self::postcodes_table();
		$sql   = "INSERT INTO {$table} (country_code, postcode, zone_id) VALUES " . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Counts postcode mappings, optionally for one country.
	 *
	 * @param string $country_code Optional ISO country code.
	 * @return int
	 */
	public static function count_postcodes( $country_code = '' ) {
		return self::count_rows( self::postcodes_table(), $country_code );
	}

	/**
	 * Removes every postcode mapping for a country.
	 *
	 * @param string $country_code ISO country code.
	 * @return int Rows removed.
	 */
	public static function delete_postcodes_by_country( $country_code ) {
		global $wpdb;

		$country = self::normalise_country( $country_code );

		if ( ! $country ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$deleted = $wpdb->delete( self::postcodes_table(), array( 'country_code' => $country ), array( '%s' ) );

		return (int) $deleted;
	}

	/**
	 * The core lookup query. Returns every zone a postcode falls in.
	 *
	 * More than one row is a normal result, not an error. The front end
	 * decides from these rows whether the answer is confident, a range, or
	 * inconclusive because the zones fall in different bands.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $postcode     Raw postcode, normalised here.
	 * @return array List of zone rows.
	 */
	public static function get_zones_for_postcode( $country_code, $postcode ) {
		global $wpdb;

		$country  = self::normalise_country( $country_code );
		$postcode = self::normalise_postcode( $postcode );

		if ( ! $country || ! $postcode ) {
			return array();
		}

		$zones     = self::zones_table();
		$postcodes = self::postcodes_table();

		$sql = "SELECT z.*
			FROM {$postcodes} AS p
			INNER JOIN {$zones} AS z ON z.zone_id = p.zone_id
			WHERE p.country_code = %s AND p.postcode = %s
			ORDER BY z.hardness_caco3 ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are internal.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $country, $postcode ), ARRAY_A );

		return array_map( array( __CLASS__, 'cast_zone_row' ), (array) $rows );
	}

	/**
	 * Lists the countries that actually have zone data present.
	 *
	 * This is what drives the front-end country selector. Importing a
	 * country's CSV makes it appear in the dropdown with no code change.
	 *
	 * @return array List of ISO country codes.
	 */
	public static function get_countries_with_data() {
		global $wpdb;

		$table = self::zones_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- no user input in this query.
		$codes = $wpdb->get_col( "SELECT DISTINCT country_code FROM {$table} ORDER BY country_code ASC" );

		return array_map( 'strval', (array) $codes );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Lookup log
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Records one lookup.
	 *
	 * The hardness and band are stored as served, not as a reference to the
	 * zone, so historic results stay accurate after the data is refreshed.
	 * No IP address, no email, no personal identifiers.
	 *
	 * @param string     $country_code   ISO country code.
	 * @param string     $postcode       Normalised postcode.
	 * @param float|null $hardness_caco3 Value served, or null when inconclusive.
	 * @param string     $band           Band matched, or an empty string.
	 * @return int|WP_Error Row id.
	 */
	public static function log_lookup( $country_code, $postcode, $hardness_caco3, $band = '' ) {
		global $wpdb;

		$data = array(
			'country_code' => self::normalise_country( $country_code ),
			'postcode'     => self::normalise_postcode( $postcode ),
			'band'         => substr( sanitize_text_field( (string) $band ), 0, 32 ),
			'created_at'   => current_time( 'mysql' ),
		);

		$formats = array( '%s', '%s', '%s', '%s' );

		// A null hardness is meaningful here: it marks an inconclusive or
		// unmatched lookup, which is worth counting.
		if ( null !== $hardness_caco3 && '' !== $hardness_caco3 ) {
			$data['hardness_caco3'] = round( (float) $hardness_caco3, 2 );
			$formats[]              = '%f';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table.
		$result = $wpdb->insert( self::lookups_table(), $data, $formats );

		if ( false === $result ) {
			return new WP_Error( 'kdna_wh_log_failed', __( 'Could not record the lookup.', 'kdna-water-hardness' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetches logged lookups for the admin log page.
	 *
	 * @param array $args {
	 *     @type string $country_code Restrict to one country.
	 *     @type string $date_from    Y-m-d, inclusive.
	 *     @type string $date_to      Y-m-d, inclusive.
	 *     @type int    $limit        Default 100. Use 0 for no limit.
	 *     @type int    $offset       Default 0.
	 * }
	 * @return array List of associative rows.
	 */
	public static function get_lookups( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'country_code' => '',
				'date_from'    => '',
				'date_to'      => '',
				'limit'        => 100,
				'offset'       => 0,
			)
		);

		$table  = self::lookups_table();
		$where  = array( '1=1' );
		$params = array();

		if ( $args['country_code'] ) {
			$where[]  = 'country_code = %s';
			$params[] = self::normalise_country( $args['country_code'] );
		}

		if ( $args['date_from'] ) {
			$where[]  = 'created_at >= %s';
			$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( $args['date_from'] ) );
		}

		if ( $args['date_to'] ) {
			$where[]  = 'created_at <= %s';
			$params[] = gmdate( 'Y-m-d 23:59:59', strtotime( $args['date_to'] ) );
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC';

		$limit = absint( $args['limit'] );

		if ( $limit ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = absint( $args['offset'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		return (array) $rows;
	}

	/**
	 * Counts logged lookups, optionally for one country.
	 *
	 * @param string $country_code Optional ISO country code.
	 * @return int
	 */
	public static function count_lookups( $country_code = '' ) {
		return self::count_rows( self::lookups_table(), $country_code );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Shared helpers
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Counts rows in one of the plugin tables, optionally filtered by country.
	 *
	 * @param string $table        Full table name, always internal.
	 * @param string $country_code Optional ISO country code.
	 * @return int
	 */
	private static function count_rows( $table, $country_code = '' ) {
		global $wpdb;

		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		$country = self::normalise_country( $country_code );

		if ( $country ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE country_code = %s", $country ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- no user input.
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return (int) $count;
	}

	/**
	 * Cleans and validates the fields of a zone row before it reaches SQL.
	 *
	 * @param array $data    Raw field values.
	 * @param bool  $partial True when updating, so missing fields are skipped
	 *                       rather than treated as required.
	 * @return array|WP_Error Cleaned row.
	 */
	private static function prepare_zone_row( array $data, $partial = false ) {
		$row = array();

		if ( isset( $data['country_code'] ) ) {
			$row['country_code'] = self::normalise_country( $data['country_code'] );
		}

		if ( isset( $data['utility_name'] ) ) {
			$row['utility_name'] = substr( sanitize_text_field( $data['utility_name'] ), 0, 255 );
		}

		if ( isset( $data['zone_name'] ) ) {
			$row['zone_name'] = substr( sanitize_text_field( $data['zone_name'] ), 0, 255 );
		}

		if ( isset( $data['hardness_caco3'] ) ) {
			$row['hardness_caco3'] = round( (float) $data['hardness_caco3'], 2 );
		}

		if ( isset( $data['confidence'] ) ) {
			$row['confidence'] = self::normalise_confidence( $data['confidence'] );
		}

		if ( isset( $data['source_url'] ) ) {
			$row['source_url'] = esc_url_raw( $data['source_url'] );
		}

		if ( isset( $data['source_date'] ) ) {
			$row['source_date'] = self::normalise_date( $data['source_date'] );
		}

		if ( ! $partial ) {
			// Every displayed figure must be traceable to a published
			// document, so the source fields are required, not optional.
			$required = array( 'country_code', 'zone_name', 'hardness_caco3', 'source_url', 'source_date' );

			foreach ( $required as $field ) {
				$value = isset( $row[ $field ] ) ? $row[ $field ] : '';

				// A hardness of zero is a legitimate value, so only genuinely
				// empty fields are rejected.
				if ( '' === $value || null === $value ) {
					return new WP_Error(
						'kdna_wh_missing_field',
						sprintf(
							/* translators: %s: database field name. */
							__( 'Missing required zone field: %s', 'kdna-water-hardness' ),
							$field
						)
					);
				}
			}

			// Unverified data is never presented as authoritative by accident.
			if ( ! isset( $row['confidence'] ) ) {
				$row['confidence'] = 'estimated';
			}

			if ( ! isset( $row['utility_name'] ) ) {
				$row['utility_name'] = '';
			}
		}

		return $row;
	}

	/**
	 * Returns the $wpdb format strings matching a prepared zone row.
	 *
	 * @param array $row Cleaned zone row.
	 * @return array
	 */
	private static function zone_formats( array $row ) {
		$map = array(
			'country_code'   => '%s',
			'utility_name'   => '%s',
			'zone_name'      => '%s',
			'hardness_caco3' => '%f',
			'confidence'     => '%s',
			'source_url'     => '%s',
			'source_date'    => '%s',
			'updated_at'     => '%s',
		);

		$formats = array();

		foreach ( array_keys( $row ) as $key ) {
			$formats[] = isset( $map[ $key ] ) ? $map[ $key ] : '%s';
		}

		return $formats;
	}

	/**
	 * Casts a zone row out of the database into sensible PHP types. MySQL
	 * returns DECIMAL columns as strings, which breaks numeric comparison
	 * when working out bands.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function cast_zone_row( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}

		if ( isset( $row['zone_id'] ) ) {
			$row['zone_id'] = (int) $row['zone_id'];
		}

		if ( isset( $row['hardness_caco3'] ) ) {
			$row['hardness_caco3'] = (float) $row['hardness_caco3'];
		}

		return $row;
	}

	/**
	 * Normalises a country code to uppercase ISO 3166-1 alpha-2.
	 *
	 * @param string $country_code Raw code.
	 * @return string Two letters, or an empty string.
	 */
	public static function normalise_country( $country_code ) {
		$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $country_code ) );

		return 2 === strlen( $code ) ? $code : '';
	}

	/**
	 * Normalises a postcode for storage and matching: uppercase, no spaces,
	 * no hyphens. Country-specific rules such as truncating a US ZIP+4 to
	 * five digits are handled by the lookup class in Stage 3.
	 *
	 * @param string $postcode Raw postcode.
	 * @return string
	 */
	public static function normalise_postcode( $postcode ) {
		$clean = strtoupper( (string) $postcode );
		$clean = preg_replace( '/[^A-Z0-9]/', '', $clean );

		return substr( (string) $clean, 0, 12 );
	}

	/**
	 * Forces the confidence field to one of its two allowed values.
	 *
	 * @param string $confidence Raw value.
	 * @return string verified or estimated.
	 */
	public static function normalise_confidence( $confidence ) {
		return 'verified' === strtolower( trim( (string) $confidence ) ) ? 'verified' : 'estimated';
	}

	/**
	 * Converts a date from a source file into Y-m-d, or an empty string when
	 * it cannot be read.
	 *
	 * @param string $date Raw date.
	 * @return string
	 */
	private static function normalise_date( $date ) {
		$date = trim( (string) $date );

		if ( '' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date );

		return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
	}
}
