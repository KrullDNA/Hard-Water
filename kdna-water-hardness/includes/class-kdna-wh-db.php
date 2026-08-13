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
	 * Inserts many zones in a single query.
	 *
	 * Used by the CSV importer, which does its own row-level validation and
	 * reports errors per row. Because of that this method trusts what it is
	 * given and does not apply the required-source rule that insert_zone()
	 * enforces. Anything reaching here has already been checked.
	 *
	 * @param array $rows List of cleaned zone rows.
	 * @return int Number of rows inserted.
	 */
	public static function insert_zones_bulk( array $rows ) {
		global $wpdb;

		$values       = array();
		$placeholders = array();
		$now          = current_time( 'mysql' );

		foreach ( $rows as $row ) {
			$has_date = ! empty( $row['source_date'] );

			/*
			 * An empty source_date has to be written as a literal NULL. Passed
			 * through a %s placeholder it becomes an empty string, and an
			 * empty string in a DATE column is rejected outright under MySQL
			 * strict mode, which would fail the entire batch rather than the
			 * one row.
			 */
			$placeholders[] = $has_date
				? '(%s, %s, %s, %f, %s, %s, %s, %s)'
				: '(%s, %s, %s, %f, %s, %s, NULL, %s)';

			$values[] = self::normalise_country( isset( $row['country_code'] ) ? $row['country_code'] : '' );
			$values[] = substr( (string) ( isset( $row['utility_name'] ) ? $row['utility_name'] : '' ), 0, 255 );
			$values[] = substr( (string) ( isset( $row['zone_name'] ) ? $row['zone_name'] : '' ), 0, 255 );
			$values[] = round( (float) ( isset( $row['hardness_caco3'] ) ? $row['hardness_caco3'] : 0 ), 2 );
			$values[] = self::normalise_confidence( isset( $row['confidence'] ) ? $row['confidence'] : '' );
			$values[] = isset( $row['source_url'] ) ? (string) $row['source_url'] : '';

			if ( $has_date ) {
				$values[] = (string) $row['source_date'];
			}

			$values[] = $now;
		}

		if ( ! $placeholders ) {
			return 0;
		}

		$table = self::zones_table();
		$sql   = "INSERT INTO {$table} (country_code, utility_name, zone_name, hardness_caco3, confidence, source_url, source_date, updated_at) VALUES " . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		return false === $result ? 0 : (int) $result;
	}

	/**
	 * Builds a lookup of zone names to zone ids for one country, so the
	 * postcode importer can resolve the zone named in each row.
	 *
	 * Two keys are produced per zone: the zone name on its own, and the
	 * utility and zone name together. Where a zone name appears under more
	 * than one utility the bare key is marked ambiguous, and the importer
	 * asks for a utility column rather than guessing.
	 *
	 * @param string $country_code ISO country code.
	 * @return array {
	 *     @type array $names     Normalised zone name to zone_id.
	 *     @type array $ambiguous Normalised zone names appearing more than once.
	 *     @type array $pairs     Normalised utility and zone name to zone_id.
	 * }
	 */
	public static function get_zone_name_map( $country_code ) {
		$zones = self::get_zones(
			array(
				'country_code' => $country_code,
				'limit'        => 0,
			)
		);

		$names     = array();
		$ambiguous = array();
		$pairs     = array();

		foreach ( $zones as $zone ) {
			$name_key = self::name_key( $zone['zone_name'] );
			$pair_key = self::name_key( $zone['utility_name'] . '|' . $zone['zone_name'] );

			$pairs[ $pair_key ] = (int) $zone['zone_id'];

			if ( isset( $names[ $name_key ] ) ) {
				$ambiguous[ $name_key ] = true;
				continue;
			}

			$names[ $name_key ] = (int) $zone['zone_id'];
		}

		return array(
			'names'     => $names,
			'ambiguous' => $ambiguous,
			'pairs'     => $pairs,
		);
	}

	/**
	 * Normalises a name for matching: lowercase, no punctuation, single
	 * spaces. Lets "Yanchep / Two Rocks" match "Yanchep Two Rocks".
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function name_key( $name ) {
		$key = strtolower( trim( (string) $name ) );
		$key = preg_replace( '/[^a-z0-9|]+/', ' ', $key );

		return trim( (string) preg_replace( '/\s+/', ' ', $key ) );
	}

	/**
	 * Returns the zone ids that exist, out of a list of candidate ids. Used to
	 * validate a CSV that maps postcodes by zone id rather than by name.
	 *
	 * @param array $zone_ids Candidate ids.
	 * @return array Ids that exist, as integers.
	 */
	public static function filter_existing_zone_ids( array $zone_ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $zone_ids ) );

		if ( ! $ids ) {
			return array();
		}

		$ids          = array_unique( $ids );
		$table        = self::zones_table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$found = $wpdb->get_col( $wpdb->prepare( "SELECT zone_id FROM {$table} WHERE zone_id IN ({$placeholders})", array_values( $ids ) ) );

		return array_map( 'absint', (array) $found );
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
	 * Counts zones matching the data browser's filters, so its pagination can
	 * show a real total rather than only a next link.
	 *
	 * @param array $args Same country_code and search as get_zones().
	 * @return int
	 */
	public static function count_zones_filtered( array $args = array() ) {
		global $wpdb;

		$table  = self::zones_table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['country_code'] ) ) {
			$where[]  = 'country_code = %s';
			$params[] = self::normalise_country( $args['country_code'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( utility_name LIKE %s OR zone_name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Counts postcode mappings matching the data browser's filters.
	 *
	 * @param array $args Same country_code and search as get_postcode_mappings().
	 * @return int
	 */
	public static function count_mappings_filtered( array $args = array() ) {
		global $wpdb;

		$table  = self::postcodes_table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['country_code'] ) ) {
			$where[]  = 'country_code = %s';
			$params[] = self::normalise_country( $args['country_code'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'postcode LIKE %s';
			$params[] = $wpdb->esc_like( self::normalise_postcode( $args['search'] ) ) . '%';
		}

		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Deletes specific zones by id, and any postcode mappings pointing at
	 * them, so the data browser can remove a selection rather than a whole
	 * country.
	 *
	 * @param array $zone_ids Zone ids to remove.
	 * @return int Number of zones removed.
	 */
	public static function delete_zones( array $zone_ids ) {
		global $wpdb;

		$ids = array_unique( array_filter( array_map( 'absint', $zone_ids ) ) );

		if ( ! $ids ) {
			return 0;
		}

		$zones        = self::zones_table();
		$postcodes    = self::postcodes_table();
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		$values       = array_values( $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$postcodes} WHERE zone_id IN ({$placeholders})", $values ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$zones} WHERE zone_id IN ({$placeholders})", $values ) );

		return false === $deleted ? 0 : (int) $deleted;
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
	 * Returns the postcode and zone pairs that already exist, out of a list of
	 * candidate postcodes.
	 *
	 * The importer calls this once per chunk so that re-uploading the same
	 * file does not double every mapping. Without it, an append import run
	 * twice would show every zone twice in a result.
	 *
	 * @param string $country_code ISO country code.
	 * @param array  $postcodes    Normalised postcodes to check.
	 * @return array Set of "postcode|zone_id" keys, for fast lookup.
	 */
	public static function get_existing_mappings( $country_code, array $postcodes ) {
		global $wpdb;

		$country = self::normalise_country( $country_code );
		$list    = array_unique( array_filter( array_map( array( __CLASS__, 'normalise_postcode' ), $postcodes ) ) );

		if ( ! $country || ! $list ) {
			return array();
		}

		$table        = self::postcodes_table();
		$placeholders = implode( ', ', array_fill( 0, count( $list ), '%s' ) );
		$values       = array_merge( array( $country ), array_values( $list ) );

		$sql = "SELECT postcode, zone_id FROM {$table} WHERE country_code = %s AND postcode IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );

		$existing = array();

		foreach ( (array) $rows as $row ) {
			$existing[ $row['postcode'] . '|' . (int) $row['zone_id'] ] = true;
		}

		return $existing;
	}

	/**
	 * Fetches postcode mappings with their zone details, for the data browser.
	 *
	 * @param array $args {
	 *     @type string $country_code Restrict to one country.
	 *     @type string $search       Matches the postcode.
	 *     @type int    $limit        Default 100.
	 *     @type int    $offset       Default 0.
	 * }
	 * @return array
	 */
	public static function get_postcode_mappings( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'country_code' => '',
				'search'       => '',
				'limit'        => 100,
				'offset'       => 0,
			)
		);

		$postcodes = self::postcodes_table();
		$zones     = self::zones_table();
		$where     = array( '1=1' );
		$params    = array();

		if ( $args['country_code'] ) {
			$where[]  = 'p.country_code = %s';
			$params[] = self::normalise_country( $args['country_code'] );
		}

		if ( $args['search'] ) {
			$where[]  = 'p.postcode LIKE %s';
			$params[] = $wpdb->esc_like( self::normalise_postcode( $args['search'] ) ) . '%';
		}

		$sql = "SELECT p.id, p.country_code, p.postcode, p.zone_id, z.zone_name, z.utility_name, z.hardness_caco3, z.confidence
			FROM {$postcodes} AS p
			LEFT JOIN {$zones} AS z ON z.zone_id = p.zone_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY p.country_code ASC, p.postcode ASC';

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
	 * Counts mappings that point at a zone which no longer exists. Orphans
	 * should never occur, since deleting a zone removes its mappings, but a
	 * hand-edited database or a part-finished import can leave them, and a
	 * silent orphan means a postcode that matches nothing.
	 *
	 * @param string $country_code Optional ISO country code.
	 * @return int
	 */
	public static function count_orphan_mappings( $country_code = '' ) {
		global $wpdb;

		$postcodes = self::postcodes_table();
		$zones     = self::zones_table();
		$country   = self::normalise_country( $country_code );

		$sql = "SELECT COUNT(*) FROM {$postcodes} AS p
			LEFT JOIN {$zones} AS z ON z.zone_id = p.zone_id
			WHERE z.zone_id IS NULL";

		if ( $country ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table names are internal.
			return (int) $wpdb->get_var( $wpdb->prepare( $sql . ' AND p.country_code = %s', $country ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- no user input.
		return (int) $wpdb->get_var( $sql );
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

	/**
	 * Builds the shared WHERE clause for the log queries.
	 *
	 * @param array $args Filters.
	 * @return array The clause and its parameters.
	 */
	private static function lookup_filters( array $args ) {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['country_code'] ) ) {
			$where[]  = 'country_code = %s';
			$params[] = self::normalise_country( $args['country_code'] );
		}

		if ( ! empty( $args['band'] ) ) {
			$where[]  = 'band = %s';
			$params[] = substr( (string) $args['band'], 0, 32 );
		}

		if ( ! empty( $args['search'] ) ) {
			global $wpdb;
			$where[]  = 'postcode LIKE %s';
			$params[] = $wpdb->esc_like( self::normalise_postcode( $args['search'] ) ) . '%';
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( $args['date_from'] ) );
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = gmdate( 'Y-m-d 23:59:59', strtotime( $args['date_to'] ) );
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * The lookup log grouped by postcode and band.
	 *
	 * This is the shape the log is actually useful in. A raw list of every
	 * lookup answers nothing; counts per postcode show where the hard-water
	 * customers are, which is what the geographic targeting is for.
	 *
	 * @param array $args {
	 *     @type string $country_code Restrict to one country.
	 *     @type string $band         Restrict to one band.
	 *     @type string $search       Postcode prefix.
	 *     @type string $date_from    Y-m-d, inclusive.
	 *     @type string $date_to      Y-m-d, inclusive.
	 *     @type string $orderby      lookups, postcode or last_seen.
	 *     @type int    $limit        Default 50. Zero for no limit.
	 *     @type int    $offset       Default 0.
	 * }
	 * @return array
	 */
	public static function get_lookup_aggregate( array $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'orderby' => 'lookups',
				'limit'   => 50,
				'offset'  => 0,
			)
		);

		list( $where, $params ) = self::lookup_filters( $args );

		$table = self::lookups_table();

		// Only a fixed set of orderings is allowed, because a column name
		// cannot be a bound parameter.
		$orderings = array(
			'lookups'   => 'lookups DESC, postcode ASC',
			'postcode'  => 'postcode ASC',
			'last_seen' => 'last_seen DESC',
			'hardness'  => 'avg_hardness DESC',
		);

		$order = isset( $orderings[ $args['orderby'] ] ) ? $orderings[ $args['orderby'] ] : $orderings['lookups'];

		$sql = "SELECT country_code, postcode, band,
				COUNT(*) AS lookups,
				AVG(hardness_caco3) AS avg_hardness,
				MIN(created_at) AS first_seen,
				MAX(created_at) AS last_seen
			FROM {$table}
			WHERE {$where}
			GROUP BY country_code, postcode, band
			ORDER BY {$order}";

		$limit = absint( $args['limit'] );

		if ( $limit ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = absint( $args['offset'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			function ( $row ) {
				$row['lookups']      = (int) $row['lookups'];
				$row['avg_hardness'] = null === $row['avg_hardness'] ? null : round( (float) $row['avg_hardness'], 2 );
				return $row;
			},
			(array) $rows
		);
	}

	/**
	 * How many postcode and band groups match, for paging the log.
	 *
	 * @param array $args Same filters as get_lookup_aggregate().
	 * @return int
	 */
	public static function count_lookup_aggregate( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = self::lookup_filters( $args );

		$table = self::lookups_table();

		$sql = "SELECT COUNT(*) FROM (
				SELECT 1 FROM {$table} WHERE {$where}
				GROUP BY country_code, postcode, band
			) AS grouped";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * Totals per band, for the summary across the top of the log.
	 *
	 * @param array $args Same filters as get_lookup_aggregate().
	 * @return array
	 */
	public static function get_band_totals( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = self::lookup_filters( $args );

		$table = self::lookups_table();

		$sql = "SELECT band, COUNT(*) AS lookups
			FROM {$table}
			WHERE {$where}
			GROUP BY band
			ORDER BY lookups DESC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		$rows = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );

		return array_map(
			function ( $row ) {
				$row['lookups'] = (int) $row['lookups'];
				return $row;
			},
			(array) $rows
		);
	}

	/**
	 * Counts the lookups matching the filters.
	 *
	 * @param array $args Same filters as get_lookup_aggregate().
	 * @return int
	 */
	public static function count_lookups_filtered( array $args = array() ) {
		global $wpdb;

		list( $where, $params ) = self::lookup_filters( $args );

		$table = self::lookups_table();
		$sql   = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- built from placeholders above.
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) );
	}

	/**
	 * The first and last dates in the log, to frame the date filters.
	 *
	 * @return array First and last, as Y-m-d H:i:s or empty strings.
	 */
	public static function get_lookup_date_range() {
		global $wpdb;

		$table = self::lookups_table();

		if ( ! self::table_exists( $table ) ) {
			return array(
				'first' => '',
				'last'  => '',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input.
		$row = $wpdb->get_row( "SELECT MIN(created_at) AS first, MAX(created_at) AS last FROM {$table}", ARRAY_A );

		return array(
			'first' => isset( $row['first'] ) ? (string) $row['first'] : '',
			'last'  => isset( $row['last'] ) ? (string) $row['last'] : '',
		);
	}

	/**
	 * Empties the log, either entirely or up to a date.
	 *
	 * @param string $before Optional Y-m-d. Everything before it goes.
	 * @return int Rows removed.
	 */
	public static function delete_lookups( $before = '' ) {
		global $wpdb;

		$table = self::lookups_table();

		if ( $before ) {
			$timestamp = strtotime( $before );

			if ( ! $timestamp ) {
				return 0;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is internal.
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d 00:00:00', $timestamp ) ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- no user input.
			$deleted = $wpdb->query( "DELETE FROM {$table}" );
		}

		return false === $deleted ? 0 : (int) $deleted;
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
			// null rather than an empty string: $wpdb->insert() turns null
			// into a real SQL NULL, where '' is rejected by a DATE column
			// under MySQL strict mode.
			$date               = self::normalise_date( $data['source_date'] );
			$row['source_date'] = '' === $date ? null : $date;
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
