<?php
/**
 * Source link registry and per-country source configuration.
 *
 * Every country's data was compiled from somewhere. This records where, so
 * that whoever refreshes the figures in two years opens one screen and finds
 * every source document listed, with no institutional knowledge required.
 * It is the difference between a maintainable tool and one that quietly goes
 * stale.
 *
 * Held in an option rather than a fourth table: the volume is a few dozen
 * links, and the data model in the brief is deliberately three tables.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Sources
 */
class KDNA_WH_Sources {

	/**
	 * Option holding the whole registry, keyed by country code.
	 */
	const OPTION = 'kdna_wh_sources';

	/**
	 * Utilities publish annually, so anything older than 18 months is flagged
	 * for review.
	 */
	const STALE_MONTHS = 18;

	/**
	 * Reads the whole registry.
	 *
	 * @return array Country code to configuration.
	 */
	public static function get_all() {
		$all = get_option( self::OPTION, array() );

		return is_array( $all ) ? $all : array();
	}

	/**
	 * Reads one country's configuration, with defaults filled in.
	 *
	 * @param string $country_code ISO country code.
	 * @return array
	 */
	public static function get_country( $country_code ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );
		$all     = self::get_all();

		$defaults = array(
			'source_type'  => 'csv',
			'links'        => array(),
			'last_import'  => '',
			'api_endpoint' => '',
			'api_key'      => '',
		);

		$config = isset( $all[ $country ] ) && is_array( $all[ $country ] ) ? $all[ $country ] : array();

		return array_merge( $defaults, $config );
	}

	/**
	 * Writes one country's configuration back.
	 *
	 * @param string $country_code ISO country code.
	 * @param array  $config       Configuration to store.
	 * @return bool
	 */
	public static function save_country( $country_code, array $config ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );

		if ( ! $country ) {
			return false;
		}

		$all             = self::get_all();
		$all[ $country ] = $config;

		return update_option( self::OPTION, $all, false );
	}

	/**
	 * Removes a country from the registry entirely.
	 *
	 * @param string $country_code ISO country code.
	 * @return bool
	 */
	public static function delete_country( $country_code ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );
		$all     = self::get_all();

		if ( ! isset( $all[ $country ] ) ) {
			return false;
		}

		unset( $all[ $country ] );

		return update_option( self::OPTION, $all, false );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Source type
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Where this country's data comes from: local tables, or a remote API.
	 * The API path is built in Stage 5b; the setting is stored from here so
	 * the configuration does not have to move later.
	 *
	 * @param string $country_code ISO country code.
	 * @return string csv or api.
	 */
	public static function get_source_type( $country_code ) {
		$config = self::get_country( $country_code );

		return 'api' === $config['source_type'] ? 'api' : 'csv';
	}

	/**
	 * Sets the source type for a country.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $type         csv or api.
	 * @return bool
	 */
	public static function set_source_type( $country_code, $type ) {
		$config                = self::get_country( $country_code );
		$config['source_type'] = 'api' === $type ? 'api' : 'csv';

		return self::save_country( $country_code, $config );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Links
	 * -----------------------------------------------------------------------
	 */

	/**
	 * All source links recorded for a country.
	 *
	 * @param string $country_code ISO country code.
	 * @return array
	 */
	public static function get_links( $country_code ) {
		$config = self::get_country( $country_code );

		return is_array( $config['links'] ) ? $config['links'] : array();
	}

	/**
	 * Reads one link.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $link_id      Link identifier.
	 * @return array|null
	 */
	public static function get_link( $country_code, $link_id ) {
		$links = self::get_links( $country_code );

		return isset( $links[ $link_id ] ) ? $links[ $link_id ] : null;
	}

	/**
	 * Adds a link, or updates one when an id is supplied.
	 *
	 * @param string $country_code ISO country code.
	 * @param array  $data {
	 *     @type string $id           Existing link id, empty to add.
	 *     @type string $label        e.g. Water Corporation, Perth metropolitan.
	 *     @type string $url          Direct link to the published report.
	 *     @type string $region       Optional state or area covered.
	 *     @type string $last_checked Date the link was last confirmed working.
	 *     @type string $data_date    Publication date of the report in use.
	 * }
	 * @return string|WP_Error The link id.
	 */
	public static function save_link( $country_code, array $data ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );

		if ( ! $country ) {
			return new WP_Error( 'kdna_wh_bad_country', __( 'Choose a country before saving a source link.', 'kdna-water-hardness' ) );
		}

		$label = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
		$url   = isset( $data['url'] ) ? esc_url_raw( trim( (string) $data['url'] ) ) : '';

		if ( '' === $label ) {
			return new WP_Error( 'kdna_wh_link_no_label', __( 'A source link needs a label, so the next person knows what it is.', 'kdna-water-hardness' ) );
		}

		if ( '' === $url ) {
			return new WP_Error( 'kdna_wh_link_no_url', __( 'A source link needs a valid web address.', 'kdna-water-hardness' ) );
		}

		$config = self::get_country( $country );
		$links  = is_array( $config['links'] ) ? $config['links'] : array();

		$id = isset( $data['id'] ) ? sanitize_key( $data['id'] ) : '';

		if ( '' === $id || ! isset( $links[ $id ] ) ) {
			/*
			 * Lowercase deliberately. Ids are run through sanitize_key() when
			 * they come back from a form, and that lowercases, so an id with
			 * a capital in it would never match again: deleting would silently
			 * fail and editing would add a second copy rather than update.
			 */
			$id = 'lnk_' . strtolower( wp_generate_password( 12, false, false ) );
		}

		$links[ $id ] = array(
			'id'           => $id,
			'label'        => $label,
			'url'          => $url,
			'region'       => isset( $data['region'] ) ? sanitize_text_field( $data['region'] ) : '',
			'last_checked' => self::clean_date( isset( $data['last_checked'] ) ? $data['last_checked'] : '' ),
			'data_date'    => self::clean_date( isset( $data['data_date'] ) ? $data['data_date'] : '' ),
		);

		$config['links'] = $links;

		self::save_country( $country, $config );

		return $id;
	}

	/**
	 * Removes a link.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $link_id      Link identifier.
	 * @return bool
	 */
	public static function delete_link( $country_code, $link_id ) {
		$config = self::get_country( $country_code );
		$links  = is_array( $config['links'] ) ? $config['links'] : array();
		$id     = sanitize_key( $link_id );

		if ( ! isset( $links[ $id ] ) ) {
			return false;
		}

		unset( $links[ $id ] );

		$config['links'] = $links;

		return self::save_country( $country_code, $config );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Import history and staleness
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Records that data was imported for a country.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $type         zones or postcodes.
	 * @param int    $rows         Rows imported.
	 * @return void
	 */
	public static function record_import( $country_code, $type, $rows ) {
		$config = self::get_country( $country_code );
		$type   = 'postcodes' === $type ? 'postcodes' : 'zones';

		$config['last_import']            = current_time( 'mysql' );
		$config[ 'last_import_' . $type ] = current_time( 'mysql' );
		$config[ 'last_rows_' . $type ]   = absint( $rows );

		self::save_country( $country_code, $config );
	}

	/**
	 * The most recent publication date across a country's source links. This
	 * is the date the staleness flag is judged on, because it describes the
	 * data rather than when someone last pressed import.
	 *
	 * @param string $country_code ISO country code.
	 * @return string Y-m-d, or an empty string when no link records one.
	 */
	public static function latest_data_date( $country_code ) {
		$latest = '';

		foreach ( self::get_links( $country_code ) as $link ) {
			if ( empty( $link['data_date'] ) ) {
				continue;
			}

			if ( '' === $latest || $link['data_date'] > $latest ) {
				$latest = $link['data_date'];
			}
		}

		return $latest;
	}

	/**
	 * Whether a country's data should be reviewed.
	 *
	 * A country with no data date recorded is also stale. Not knowing how old
	 * the figures are is the same problem as knowing they are old, and the
	 * flag should say so rather than stay quiet.
	 *
	 * @param string $country_code ISO country code.
	 * @return bool
	 */
	public static function is_stale( $country_code ) {
		$latest = self::latest_data_date( $country_code );

		if ( '' === $latest ) {
			return true;
		}

		return strtotime( $latest ) < strtotime( '-' . self::STALE_MONTHS . ' months', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	}

	/**
	 * A plain-English description of a country's data age, for the panel
	 * heading.
	 *
	 * @param string $country_code ISO country code.
	 * @return string
	 */
	public static function staleness_label( $country_code ) {
		$latest = self::latest_data_date( $country_code );

		if ( '' === $latest ) {
			return __( 'No publication date recorded', 'kdna-water-hardness' );
		}

		$months = (int) floor( ( current_time( 'timestamp' ) - strtotime( $latest ) ) / MONTH_IN_SECONDS ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( $months < 1 ) {
			return __( 'Published within the last month', 'kdna-water-hardness' );
		}

		return sprintf(
			/* translators: %d: number of months. */
			_n( 'Published %d month ago', 'Published %d months ago', $months, 'kdna-water-hardness' ),
			$months
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Countries
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Every country the admin should show a panel for: those with data
	 * imported, and those with a registry entry but no data yet.
	 *
	 * @return array List of ISO country codes.
	 */
	public static function get_known_countries() {
		$with_data = KDNA_WH_DB::get_countries_with_data();
		$in_config = array_keys( self::get_all() );

		$countries = array_unique( array_filter( array_merge( $with_data, $in_config ) ) );

		sort( $countries );

		return $countries;
	}

	/**
	 * A readable country name for a code, falling back to the code itself for
	 * anything outside the list. This is presentation only; the plugin never
	 * restricts which countries can hold data.
	 *
	 * @param string $country_code ISO country code.
	 * @return string
	 */
	public static function country_name( $country_code ) {
		$code = KDNA_WH_DB::normalise_country( $country_code );

		$names = array(
			'AU' => __( 'Australia', 'kdna-water-hardness' ),
			'NZ' => __( 'New Zealand', 'kdna-water-hardness' ),
			'GB' => __( 'United Kingdom', 'kdna-water-hardness' ),
			'US' => __( 'United States', 'kdna-water-hardness' ),
			'CA' => __( 'Canada', 'kdna-water-hardness' ),
			'IE' => __( 'Ireland', 'kdna-water-hardness' ),
			'DE' => __( 'Germany', 'kdna-water-hardness' ),
			'FR' => __( 'France', 'kdna-water-hardness' ),
			'NL' => __( 'Netherlands', 'kdna-water-hardness' ),
			'ZA' => __( 'South Africa', 'kdna-water-hardness' ),
			'SG' => __( 'Singapore', 'kdna-water-hardness' ),
			'AE' => __( 'United Arab Emirates', 'kdna-water-hardness' ),
		);

		/**
		 * Filters the country names shown in the admin.
		 *
		 * @param array $names Country code to display name.
		 */
		$names = apply_filters( 'kdna_wh_country_names', $names );

		return isset( $names[ $code ] ) ? $names[ $code ] : $code;
	}

	/**
	 * Seeds the Australian utility links on first use.
	 *
	 * These are the water authorities whose published reports the Australian
	 * figures are compiled from. Each is recorded at its primary web address,
	 * with no publication date, because the exact report URL and its date are
	 * only known once the data is actually compiled. Every one will show as
	 * needing review until someone fills those in, which is the correct state
	 * for a registry nobody has verified yet.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		if ( get_option( 'kdna_wh_sources_seeded' ) ) {
			return;
		}

		$utilities = array(
			array( 'Water Corporation', 'https://www.watercorporation.com.au/', 'WA' ),
			array( 'SA Water', 'https://www.sawater.com.au/', 'SA' ),
			array( 'Sydney Water', 'https://www.sydneywater.com.au/', 'NSW' ),
			array( 'Yarra Valley Water', 'https://www.yvw.com.au/', 'VIC' ),
			array( 'South East Water', 'https://southeastwater.com.au/', 'VIC' ),
			array( 'Greater Western Water', 'https://www.gww.com.au/', 'VIC' ),
			array( 'Seqwater', 'https://www.seqwater.com.au/', 'QLD' ),
			array( 'Urban Utilities', 'https://urbanutilities.com.au/', 'QLD' ),
			array( 'Icon Water', 'https://www.iconwater.com.au/', 'ACT' ),
			array( 'TasWater', 'https://www.taswater.com.au/', 'TAS' ),
			array( 'Power and Water', 'https://www.powerwater.com.au/', 'NT' ),
		);

		foreach ( $utilities as $utility ) {
			self::save_link(
				'AU',
				array(
					'label'  => $utility[0],
					'url'    => $utility[1],
					'region' => $utility[2],
				)
			);
		}

		update_option( 'kdna_wh_sources_seeded', 1, false );
	}

	/**
	 * Accepts a date in whatever form it was typed and stores it as Y-m-d.
	 *
	 * @param string $date Raw date.
	 * @return string
	 */
	private static function clean_date( $date ) {
		$date = trim( (string) $date );

		if ( '' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date );

		return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : '';
	}
}
