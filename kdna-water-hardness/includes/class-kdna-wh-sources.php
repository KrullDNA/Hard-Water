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
	 * How long an API answer is kept before the provider is asked again.
	 *
	 * Thirty days. Hardness figures change once a year at most, so calling a
	 * provider per lookup is wasted latency and, on a metered plan, wasted
	 * money.
	 */
	const DEFAULT_TTL = 30 * DAY_IN_SECONDS;

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
			'source_type'    => 'csv',
			'links'          => array(),
			'last_import'    => '',
			'api_endpoint'   => '',
			'api_key'        => '',
			'api_adapter'    => 'json',
			'api_ttl'        => self::DEFAULT_TTL,
			'api_confidence' => 'verified',
			'api_error'      => '',
			'api_error_at'   => '',
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

		$saved = update_option( self::OPTION, $all, false );

		// Where an answer comes from, and which countries are reachable, both
		// change here, so cached answers can no longer be trusted.
		KDNA_WH_Lookup::bump_cache();

		return $saved;
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

	/**
	 * Saves a country's API configuration.
	 *
	 * The key is stored in the site's own settings and is never written into
	 * the plugin, into a URL that would reach a server log, or into the cache
	 * key.
	 *
	 * @param string $country_code ISO country code.
	 * @param array  $api          Endpoint, key, adapter, TTL and confidence.
	 * @return bool
	 */
	public static function save_api_settings( $country_code, array $api ) {
		$config = self::get_country( $country_code );

		if ( isset( $api['api_endpoint'] ) ) {
			$config['api_endpoint'] = esc_url_raw( trim( (string) $api['api_endpoint'] ) );
		}

		if ( isset( $api['api_key'] ) ) {
			$config['api_key'] = trim( sanitize_text_field( $api['api_key'] ) );
		}

		if ( isset( $api['api_adapter'] ) ) {
			$adapters              = self::get_adapters();
			$adapter               = sanitize_key( $api['api_adapter'] );
			$config['api_adapter'] = isset( $adapters[ $adapter ] ) ? $adapter : 'json';
		}

		if ( isset( $api['api_ttl'] ) ) {
			// An hour at the least, so a misconfiguration cannot turn into a
			// call on every page view. A year at the most.
			$config['api_ttl'] = max( HOUR_IN_SECONDS, min( YEAR_IN_SECONDS, absint( $api['api_ttl'] ) ) );
		}

		if ( isset( $api['api_confidence'] ) ) {
			$config['api_confidence'] = 'estimated' === $api['api_confidence'] ? 'estimated' : 'verified';
		}

		return self::save_country( $country_code, $config );
	}

	/**
	 * How long this country's API answers are cached for.
	 *
	 * @param string $country_code ISO country code.
	 * @return int Seconds.
	 */
	public static function get_ttl( $country_code ) {
		$config = self::get_country( $country_code );
		$ttl    = absint( $config['api_ttl'] );

		/**
		 * Filters the cache lifetime for a country's API answers.
		 *
		 * @param int    $ttl     Seconds.
		 * @param string $country ISO country code.
		 */
		return (int) apply_filters( 'kdna_wh_api_ttl', $ttl ? $ttl : self::DEFAULT_TTL, KDNA_WH_DB::normalise_country( $country_code ) );
	}

	/**
	 * What confidence an API answer is stored with when the provider does not
	 * say. Choosing a provider is itself a statement of trust in it, so this
	 * defaults to verified, but it is a setting because not every provider
	 * deserves that.
	 *
	 * @param string $country_code ISO country code.
	 * @return string verified or estimated.
	 */
	public static function get_api_confidence( $country_code ) {
		$config = self::get_country( $country_code );

		return 'estimated' === $config['api_confidence'] ? 'estimated' : 'verified';
	}

	/**
	 * Records the last error a provider returned, for the admin.
	 *
	 * The visitor never sees any of this. Somebody still has to be able to
	 * find out why a country quietly stopped using its API.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $message      What the provider said.
	 * @return void
	 */
	public static function record_api_error( $country_code, $message ) {
		$config = self::get_country( $country_code );

		$config['api_error']    = substr( (string) $message, 0, 300 );
		$config['api_error_at'] = current_time( 'mysql' );

		self::save_country( $country_code, $config );
	}

	/**
	 * Clears the recorded error.
	 *
	 * @param string $country_code ISO country code.
	 * @return void
	 */
	public static function clear_api_error( $country_code ) {
		$config = self::get_country( $country_code );

		$config['api_error']    = '';
		$config['api_error_at'] = '';

		self::save_country( $country_code, $config );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Adapters
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The API adapters available to choose from.
	 *
	 * Adding a provider means writing a subclass of KDNA_WH_Source_API and
	 * adding one line to this filter. Nothing else in the plugin changes,
	 * which is the point of the interface.
	 *
	 * @return array Adapter key to class name.
	 */
	public static function get_adapters() {
		$adapters = array(
			'json' => 'KDNA_WH_Source_JSON',
		);

		/**
		 * Filters the registered API adapters.
		 *
		 * @param array $adapters Adapter key to class name. Each class must
		 *                        extend KDNA_WH_Source_API.
		 */
		return apply_filters( 'kdna_wh_api_adapters', $adapters );
	}

	/**
	 * Builds the source for a country.
	 *
	 * Falls back to local data whenever the API path is chosen but not usable,
	 * so a half-finished configuration degrades to the working thing rather
	 * than to nothing.
	 *
	 * @param string $country_code ISO country code.
	 * @return KDNA_WH_Source
	 */
	public static function get_adapter( $country_code ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );
		$config  = self::get_country( $country );

		if ( 'api' === $config['source_type'] ) {
			$adapters = self::get_adapters();
			$key      = isset( $config['api_adapter'] ) ? $config['api_adapter'] : 'json';
			$class    = isset( $adapters[ $key ] ) ? $adapters[ $key ] : '';

			if ( $class && class_exists( $class ) && is_subclass_of( $class, 'KDNA_WH_Source_API' ) ) {
				$source = new $class( $country, $config );

				if ( $source->is_available() ) {
					/**
					 * Filters the source used for a country.
					 *
					 * @param KDNA_WH_Source $source  The source.
					 * @param string         $country ISO country code.
					 * @param array          $config  The country's configuration.
					 */
					return apply_filters( 'kdna_wh_source', $source, $country, $config );
				}
			}
		}

		return apply_filters( 'kdna_wh_source', new KDNA_WH_Source_CSV( $country ), $country, $config );
	}

	/**
	 * Whether a country is set up to answer at all, whether from imported data
	 * or from a provider.
	 *
	 * A country served by an API holds no local data until its first lookups
	 * have been written through, so asking the tables alone would keep it out
	 * of the country selector and make it unreachable.
	 *
	 * @return array List of ISO country codes.
	 */
	public static function get_serviceable_countries() {
		$countries = KDNA_WH_DB::get_countries_with_data();

		foreach ( self::get_all() as $code => $config ) {
			if ( in_array( $code, $countries, true ) ) {
				continue;
			}

			$type     = isset( $config['source_type'] ) ? $config['source_type'] : 'csv';
			$endpoint = isset( $config['api_endpoint'] ) ? trim( (string) $config['api_endpoint'] ) : '';

			if ( 'api' === $type && '' !== $endpoint ) {
				$countries[] = $code;
			}
		}

		sort( $countries );

		return $countries;
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
