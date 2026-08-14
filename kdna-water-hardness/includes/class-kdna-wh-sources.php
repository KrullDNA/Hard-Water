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
		$links  = is_array( $config['links'] ) ? $config['links'] : array();

		/*
		 * Links recorded before the format was tracked have no format. They
		 * are filled in here rather than migrated, so an upgrade does not have
		 * to rewrite the option to keep the admin free of notices.
		 */
		foreach ( $links as $id => $link ) {
			if ( ! isset( $link['format'] ) ) {
				$links[ $id ]['format'] = 'web';
			}
		}

		return $links;
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
	 * What can be at the other end of a source link, and what to call it.
	 *
	 * Worth recording, because most water authorities do not publish a CSV.
	 * They publish an annual report as a PDF, or a page you search by suburb.
	 * Knowing which is which is the difference between a button that opens a
	 * spreadsheet and a button that opens forty pages of appendices.
	 *
	 * @return array Format key to label.
	 */
	public static function formats() {
		return array(
			'csv'  => __( 'CSV', 'kdna-water-hardness' ),
			'xlsx' => __( 'Spreadsheet', 'kdna-water-hardness' ),
			'pdf'  => __( 'PDF report', 'kdna-water-hardness' ),
			'web'  => __( 'Web page', 'kdna-water-hardness' ),
			'api'  => __( 'API', 'kdna-water-hardness' ),
		);
	}

	/**
	 * Reduces whatever was submitted to one of the known formats.
	 *
	 * A web page is the assumption, because it is what most of these links
	 * turn out to be and it is the claim least likely to be wrong.
	 *
	 * @param string $format Raw value.
	 * @return string
	 */
	private static function clean_format( $format ) {
		$format = sanitize_key( $format );

		return isset( self::formats()[ $format ] ) ? $format : 'web';
	}

	/**
	 * What the download button should say for a given format.
	 *
	 * The label is the format's own, rather than "Download latest CSV" on
	 * everything, because a button promising a CSV that opens a PDF is worse
	 * than no button.
	 *
	 * @param string $format Format key.
	 * @return string
	 */
	public static function download_label( $format ) {
		$labels = array(
			'csv'  => __( 'Download latest CSV', 'kdna-water-hardness' ),
			'xlsx' => __( 'Download latest spreadsheet', 'kdna-water-hardness' ),
			'pdf'  => __( 'Open latest PDF', 'kdna-water-hardness' ),
			'web'  => __( 'Open latest data', 'kdna-water-hardness' ),
			'api'  => __( 'Open API endpoint', 'kdna-water-hardness' ),
		);

		$format = self::clean_format( $format );

		return $labels[ $format ];
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
	 *     @type string $format       What is at the other end: csv, xlsx, pdf,
	 *                                web or api.
	 * }
	 * @return string|WP_Error The link id.
	 */
	public static function save_link( $country_code, array $data ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );

		if ( ! $country ) {
			return new WP_Error( 'kdna_wh_bad_country', __( 'Choose a country before saving a source link.', 'kdna-water-hardness' ) );
		}

		$label = isset( $data['label'] ) ? sanitize_text_field( $data['label'] ) : '';
		$url = isset( $data['url'] ) ? esc_url_raw( trim( (string) $data['url'] ) ) : '';

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
			'format'       => self::clean_format( isset( $data['format'] ) ? $data['format'] : '' ),
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
	 * The version of the seeded link set. Raise it when the list below
	 * changes, and the new entries are added to existing installations
	 * without touching what anyone has edited.
	 */
	const SEED_VERSION = 2;

	/**
	 * The Australian water authorities and where each publishes its data.
	 *
	 * Every URL here points at the data itself: the CSV, or the PDF whose
	 * tables carry the hardness figures. Where an authority publishes no file
	 * at all, and several of them do not, the link is the search tool that
	 * takes its place and the format says so, because a button promising a
	 * download that opens a search box is worse than an honest label.
	 *
	 * Only two of these are machine readable. The rest are annual reports
	 * whose figures have to be read out by hand before they can be imported.
	 * That is the state of the sector, not an omission here.
	 *
	 * Direct file links move when a publisher reorganises. That is what the
	 * link checked date is for: the registry flags what has not been
	 * confirmed lately rather than pretending URLs are permanent.
	 *
	 * @return array
	 */
	public static function default_links() {
		return array(

			/*
			 * Machine readable. The only two in the country that can be
			 * imported without transcribing a report first.
			 */
			array(
				'label'  => 'SA Water, water quality systems and suburbs',
				'url'    => 'https://data.sa.gov.au/data/dataset/996ec2ae-d52c-4d7e-be9c-d4dab1c1aa45/resource/097c6b91-40a3-43cc-9ef1-e9e48b7b7f07/download/water-quality-systems-and-suburbs-29-9-2025.csv',
				'region' => 'SA',
				'format' => 'csv',
				'date'   => '2025-09-29',
			),
			array(
				'label'  => 'SA Water, water quality performance results',
				'url'    => 'https://data.gov.au/data/dataset/water-quality1',
				'region' => 'SA',
				'format' => 'csv',
				'date'   => '',
			),

			/*
			 * Annual reports. Hardness is in the aesthetic tables, not the
			 * health ones, which is where people usually look first.
			 */
			array(
				'label'  => 'Water Corporation, Perth aesthetic tables',
				'url'    => 'https://www.watercorporation.com.au/-/media/WaterCorp/Documents/About-us/Our-performance/Drinking-Water-Quality/Dwq-annual-report-perth-aesthetic-tables.pdf',
				'region' => 'WA, Perth',
				'format' => 'pdf',
				'date'   => '2025-06-30',
			),
			array(
				'label'  => 'Water Corporation, South West aesthetic tables',
				'url'    => 'https://www.watercorporation.com.au/-/media/WaterCorp/Documents/About-us/Our-performance/Drinking-Water-Quality/Dwq-annual-report-south-west-aesthetic-tables.pdf',
				'region' => 'WA, South West',
				'format' => 'pdf',
				'date'   => '2025-06-30',
			),
			array(
				'label'  => 'Water Corporation, Goldfields and Agricultural aesthetic tables',
				'url'    => 'https://www.watercorporation.com.au/-/media/WaterCorp/Documents/About-us/Our-performance/Drinking-Water-Quality/Dwq-annual-report-goldfields-and-ag-aesthetic-tables.pdf',
				'region' => 'WA, Goldfields and Agricultural',
				'format' => 'pdf',
				'date'   => '2025-06-30',
			),
			array(
				'label'  => 'Water Corporation, North West aesthetic tables',
				'url'    => 'https://www.watercorporation.com.au/-/media/WaterCorp/Documents/About-us/Our-performance/Drinking-Water-Quality/Dwq-annual-report-north-west-aesthetic-tables.pdf',
				'region' => 'WA, North West',
				'format' => 'pdf',
				'date'   => '2025-06-30',
			),
			array(
				'label'  => 'Hunter Water, typical composition of treated water',
				'url'    => 'https://www.hunterwater.com.au/documents/assets/src/uploads/documents/Fact-Sheets/Water-Quality/Typical-Composition-Table-Hunter-Water-s-Sources-CURRENT-Updated-December-2018.pdf',
				'region' => 'NSW, Hunter',
				'format' => 'pdf',
				'date'   => '2018-12-01',
			),
			array(
				'label'  => 'Icon Water, drinking water quality report',
				'url'    => 'https://www.iconwater.com.au/sites/default/files/2025-10/Drinking%20Water%20Quality%20Report%202024-25.pdf',
				'region' => 'ACT',
				'format' => 'pdf',
				'date'   => '2025-06-30',
			),
			array(
				'label'  => 'TasWater, annual drinking water quality report',
				'url'    => 'https://www.taswater.com.au/ArticleDocuments/286/TasWater%20Annual%20Drinking%20Water%20Quality%20Report%202023-24.pdf.aspx',
				'region' => 'TAS',
				'format' => 'pdf',
				'date'   => '2024-06-30',
			),
			array(
				'label'  => 'Power and Water, drinking water quality report',
				'url'    => 'https://www.powerwater.com.au/__data/assets/pdf_file/0034/355939/2023-Power-and-Water-Drinking-Water-Quality-Report.pdf',
				'region' => 'NT',
				'format' => 'pdf',
				'date'   => '2023-12-31',
			),
			array(
				'label'  => 'Urban Utilities, annual drinking water performance report',
				'url'    => 'https://www.urbanutilities.com.au/sfsites/c/cms/delivery/media/MCK4FTGHWMBRF5XCB334PN7PHPIQ',
				'region' => 'QLD, Brisbane and Ipswich',
				'format' => 'pdf',
				'date'   => '2024-06-30',
			),
			array(
				'label'  => 'Health Victoria, annual report on drinking water quality',
				'url'    => 'https://www.health.vic.gov.au/water/drinking-water-quality-annual-reports',
				'region' => 'VIC, all corporations',
				'format' => 'pdf',
				'date'   => '',
			),

			/*
			 * No file published. These are search tools, and the figure has to
			 * be read off the screen one postcode at a time.
			 */
			array(
				'label'  => 'Sydney Water, water analysis by suburb',
				'url'    => 'https://www.sydneywater.com.au/water-the-environment/how-we-manage-sydney-s-water/safe-drinking-water/water-analysis.html',
				'region' => 'NSW, Sydney',
				'format' => 'web',
				'date'   => '',
			),
			array(
				'label'  => 'Seqwater, water quality report by area',
				'url'    => 'https://www.seqwater.com.au/water-quality-report',
				'region' => 'QLD, South East',
				'format' => 'web',
				'date'   => '',
			),
			array(
				'label'  => 'Unitywater, what is in your water',
				'url'    => 'https://www.unitywater.com/about-us/our-business/water-quality/whats-in-your-water',
				'region' => 'QLD, Sunshine Coast and Moreton Bay',
				'format' => 'web',
				'date'   => '',
			),
			array(
				'label'  => 'NSW Health, drinking water database',
				'url'    => 'https://www.health.nsw.gov.au/environment/water/Pages/drinking-water-database.aspx',
				'region' => 'NSW, regional councils',
				'format' => 'web',
				'date'   => '',
			),
			array(
				'label'  => 'qldwater, reporting and benchmarking',
				'url'    => 'https://qldwater.com.au/reporting',
				'region' => 'QLD, regional councils',
				'format' => 'web',
				'date'   => '',
			),
		);
	}

	/**
	 * Seeds the Australian utility links, and tops them up on upgrade.
	 *
	 * No publication date is recorded, because the date belongs to whichever
	 * report was actually used, and that is only known once someone compiles
	 * the data. Every entry shows as needing review until then, which is the
	 * correct state for a registry nobody has verified yet.
	 *
	 * Matching is by URL, so a link someone has renamed, re-dated or pointed
	 * somewhere better is never overwritten and never duplicated.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		if ( (int) get_option( 'kdna_wh_sources_seeded' ) >= self::SEED_VERSION ) {
			return;
		}

		$existing = array();

		foreach ( self::get_links( 'AU' ) as $link ) {
			$existing[ untrailingslashit( $link['url'] ) ] = true;
		}

		foreach ( self::default_links() as $utility ) {
			if ( isset( $existing[ untrailingslashit( $utility['url'] ) ] ) ) {
				continue;
			}

			self::save_link(
				'AU',
				array(
					'label'     => $utility['label'],
					'url'       => $utility['url'],
					'region'    => $utility['region'],
					'format'    => $utility['format'],
					'data_date' => $utility['date'],
				)
			);
		}

		update_option( 'kdna_wh_sources_seeded', self::SEED_VERSION, false );
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
