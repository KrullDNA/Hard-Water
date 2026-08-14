<?php
/**
 * Base class for a remote data source.
 *
 * Everything a provider adapter needs except the provider: caching, the
 * request, the fallback, and writing successful answers into the local tables
 * so they accumulate. A concrete adapter supplies a URL and a way to read the
 * response, and gets the rest.
 *
 * Three rules run through all of it.
 *
 * A visitor never sees a third party's failure. Every error path ends in local
 * data or an empty result, never in a message about someone else's server.
 *
 * A provider is never called twice for the same postcode inside the cache
 * window. Hardness changes once a year at most, so per-lookup calls are pure
 * waste and, on a metered plan, pure cost.
 *
 * A provider may disappear. Every successful answer is written into the local
 * tables, so the data set grows into its own fallback and the tool degrades
 * rather than breaks if the contract ends.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Source_API
 */
abstract class KDNA_WH_Source_API implements KDNA_WH_Source {

	/**
	 * How long to wait for a provider. A visitor is watching a spinner, so
	 * this is deliberately short: a slow answer is worse than a local one.
	 */
	const TIMEOUT = 5;

	/**
	 * How long to stop calling a provider that has just failed.
	 *
	 * Without this, a provider that is down turns every single page view into
	 * a five second wait. One request pays the timeout, and everyone for the
	 * next few minutes goes straight to local data.
	 */
	const BACKOFF = 5 * MINUTE_IN_SECONDS;

	/**
	 * How long an answer of "no data for that postcode" is kept.
	 *
	 * Much shorter than a successful answer. Coverage is the thing most likely
	 * to change, and a postcode wrongly remembered as uncovered for a month is
	 * a customer told nothing is known about them.
	 */
	const EMPTY_TTL = DAY_IN_SECONDS;

	/**
	 * ISO country code.
	 *
	 * @var string
	 */
	protected $country;

	/**
	 * The country's stored configuration.
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * The local source, used whenever this one cannot answer.
	 *
	 * @var KDNA_WH_Source_CSV
	 */
	protected $local;

	/**
	 * Constructor.
	 *
	 * @param string $country ISO country code.
	 * @param array  $config  The country's configuration.
	 */
	public function __construct( $country, array $config = array() ) {
		$this->country = KDNA_WH_DB::normalise_country( $country );
		$this->config  = $config ? $config : KDNA_WH_Sources::get_country( $this->country );
		$this->local   = new KDNA_WH_Source_CSV( $this->country );
	}

	/*
	 * -----------------------------------------------------------------------
	 * What a concrete adapter must supply
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Turns a provider's response body into zone rows.
	 *
	 * Return an empty array when the provider has no data for the postcode,
	 * and a WP_Error when the response could not be understood, which is
	 * treated as a failure and falls back to local data.
	 *
	 * @param string $body     Raw response body.
	 * @param string $postcode Normalised postcode.
	 * @param array  $response The full wp_remote_get response.
	 * @return array|WP_Error
	 */
	abstract protected function parse_response( $body, $postcode, $response );

	/**
	 * The URL to call for a postcode.
	 *
	 * The default substitutes into the endpoint configured in the admin, so a
	 * provider whose URL is a simple template needs no code at all. Override
	 * for anything more involved.
	 *
	 * Placeholders: {postcode} and {country}, plus {key} for providers that
	 * want the key in the path or query rather than a header.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return string
	 */
	protected function build_url( $postcode ) {
		$endpoint = (string) $this->config['api_endpoint'];

		if ( false === strpos( $endpoint, '{postcode}' ) ) {
			// No placeholder, so the postcode goes on as a query argument.
			return add_query_arg( 'postcode', rawurlencode( $postcode ), $endpoint );
		}

		return str_replace(
			array( '{postcode}', '{country}', '{key}' ),
			array( rawurlencode( $postcode ), rawurlencode( $this->country ), rawurlencode( (string) $this->config['api_key'] ) ),
			$endpoint
		);
	}

	/**
	 * The arguments for the HTTP request.
	 *
	 * The key is sent as a bearer token by default, which is the common case,
	 * and is never written into the URL unless the endpoint template asks for
	 * it, so it stays out of server logs and out of the cache key.
	 *
	 * @return array
	 */
	protected function request_args() {
		$args = array(
			'timeout'     => self::TIMEOUT,
			'redirection' => 2,
			'headers'     => array(
				'Accept' => 'application/json',
			),
		);

		$key = trim( (string) $this->config['api_key'] );

		if ( '' !== $key && false === strpos( (string) $this->config['api_endpoint'], '{key}' ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $key;
		}

		/**
		 * Filters the request arguments for a water hardness API call.
		 *
		 * @param array  $args    Arguments for wp_remote_get().
		 * @param string $country ISO country code.
		 * @param array  $config  The country's configuration.
		 */
		return apply_filters( 'kdna_wh_api_request_args', $args, $this->country, $this->config );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The interface
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The country this source answers for.
	 *
	 * @return string
	 */
	public function get_country() {
		return $this->country;
	}

	/**
	 * A short name for the admin.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Remote API', 'kdna-water-hardness' );
	}

	/**
	 * Whether there is enough configuration to be worth calling.
	 *
	 * @return bool
	 */
	public function is_available() {
		return '' !== $this->country && '' !== trim( (string) $this->config['api_endpoint'] );
	}

	/**
	 * The zones a postcode falls in.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return array
	 */
	public function get_zones( $postcode ) {
		$postcode = KDNA_WH_DB::normalise_postcode( $postcode );

		if ( '' === $postcode ) {
			return array();
		}

		if ( ! $this->is_available() ) {
			return $this->local->get_zones( $postcode );
		}

		// Already asked recently.
		$cached = $this->get_cached( $postcode );

		if ( null !== $cached ) {
			return $cached ? $cached : $this->local->get_zones( $postcode );
		}

		// Asked recently and it failed, so do not make this visitor wait too.
		if ( $this->is_backing_off() ) {
			return $this->local->get_zones( $postcode );
		}

		$zones = $this->fetch( $postcode );

		if ( is_wp_error( $zones ) ) {
			$this->record_failure( $zones );

			return $this->local->get_zones( $postcode );
		}

		$this->set_cached( $postcode, $zones );

		if ( $zones ) {
			$this->write_through( $postcode, $zones );

			return $zones;
		}

		// The provider knows nothing about this postcode, but we might.
		return $this->local->get_zones( $postcode );
	}

	/*
	 * -----------------------------------------------------------------------
	 * The request
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Calls the provider and reads the answer.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return array|WP_Error Zone rows, or an error to fall back on.
	 */
	protected function fetch( $postcode ) {
		$response = wp_remote_get( $this->build_url( $postcode ), $this->request_args() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// 404 is a real answer: the provider has nothing for this postcode.
		if ( 404 === $code ) {
			return array();
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'kdna_wh_api_quota',
				__( 'The provider reported the request quota is exhausted.', 'kdna-water-hardness' )
			);
		}

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error(
				'kdna_wh_api_auth',
				__( 'The provider rejected the API key.', 'kdna-water-hardness' )
			);
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'kdna_wh_api_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The provider returned HTTP %d.', 'kdna-water-hardness' ),
					$code
				)
			);
		}

		return $this->parse_response( (string) wp_remote_retrieve_body( $response ), $postcode, $response );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Caching
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The transient name for a postcode.
	 *
	 * Hashed because a transient name is limited in length and a postcode is
	 * not the only thing in the key. The endpoint is included so that changing
	 * provider, or changing the URL, invalidates everything cached under the
	 * old one rather than serving it for another month.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return string
	 */
	protected function cache_key( $postcode ) {
		// The country stays readable in the name so one country's cache can be
		// cleared without touching another's.
		return 'kdna_wh_src_' . strtolower( $this->country ) . '_' . md5(
			$postcode . '|' . (string) $this->config['api_endpoint']
		);
	}

	/**
	 * Reads a cached answer.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return array|null The zones, an empty array for a cached "no data", or
	 *                    null when nothing is cached.
	 */
	protected function get_cached( $postcode ) {
		$cached = get_transient( $this->cache_key( $postcode ) );

		if ( false === $cached ) {
			return null;
		}

		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Stores an answer.
	 *
	 * @param string $postcode Normalised postcode.
	 * @param array  $zones    Zone rows, possibly empty.
	 * @return void
	 */
	protected function set_cached( $postcode, array $zones ) {
		$ttl = $zones ? KDNA_WH_Sources::get_ttl( $this->country ) : self::EMPTY_TTL;

		set_transient( $this->cache_key( $postcode ), $zones, $ttl );
	}

	/**
	 * Whether this provider is currently being left alone after a failure.
	 *
	 * @return bool
	 */
	protected function is_backing_off() {
		return (bool) get_transient( $this->backoff_key() );
	}

	/**
	 * The transient marking a provider as recently failed.
	 *
	 * @return string
	 */
	protected function backoff_key() {
		return 'kdna_wh_src_down_' . strtolower( $this->country );
	}

	/**
	 * Notes that a call failed, and stops calling for a while.
	 *
	 * The reason is stored for the admin, because the visitor is never going
	 * to see it and somebody has to be able to find out.
	 *
	 * @param WP_Error $error What went wrong.
	 * @return void
	 */
	protected function record_failure( $error ) {
		set_transient( $this->backoff_key(), 1, self::BACKOFF );

		KDNA_WH_Sources::record_api_error( $this->country, $error->get_error_message() );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Write-through
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Saves an answer into the local tables.
	 *
	 * Over time this turns the provider's coverage into a local data set that
	 * survives the provider. Zones already held are matched by name rather
	 * than duplicated, and a mapping already present is left alone.
	 *
	 * @param string $postcode Normalised postcode.
	 * @param array  $zones    Zone rows from the provider.
	 * @return void
	 */
	protected function write_through( $postcode, array $zones ) {
		/**
		 * Filters whether API results are written into the local tables.
		 *
		 * Some providers forbid storing their data. Check the licence before
		 * depending on this, and switch it off here if storage is not allowed.
		 *
		 * @param bool   $enabled Whether to store results.
		 * @param string $country ISO country code.
		 */
		if ( ! apply_filters( 'kdna_wh_api_write_through', true, $this->country ) ) {
			return;
		}

		$map      = KDNA_WH_DB::get_zone_name_map( $this->country );
		$existing = KDNA_WH_DB::get_existing_mappings( $this->country, array( $postcode ) );

		foreach ( $zones as $zone ) {
			$name = isset( $zone['zone_name'] ) ? (string) $zone['zone_name'] : '';

			if ( '' === $name ) {
				continue;
			}

			$utility  = isset( $zone['utility_name'] ) ? (string) $zone['utility_name'] : '';
			$pair_key = KDNA_WH_DB::name_key( $utility . '|' . $name );
			$name_key = KDNA_WH_DB::name_key( $name );

			$zone_id = 0;

			if ( isset( $map['pairs'][ $pair_key ] ) ) {
				$zone_id = (int) $map['pairs'][ $pair_key ];
			} elseif ( isset( $map['names'][ $name_key ] ) && ! isset( $map['ambiguous'][ $name_key ] ) ) {
				$zone_id = (int) $map['names'][ $name_key ];
			}

			if ( ! $zone_id ) {
				$inserted = KDNA_WH_DB::insert_zones_bulk( array( $this->prepare_zone( $zone ) ) );

				if ( ! $inserted ) {
					continue;
				}

				// Re-read the map so the new zone is found, and so a second
				// zone in the same response does not insert a duplicate.
				$map      = KDNA_WH_DB::get_zone_name_map( $this->country );
				$zone_id  = isset( $map['pairs'][ $pair_key ] ) ? (int) $map['pairs'][ $pair_key ] : 0;

				if ( ! $zone_id && isset( $map['names'][ $name_key ] ) ) {
					$zone_id = (int) $map['names'][ $name_key ];
				}
			}

			if ( ! $zone_id || isset( $existing[ $postcode . '|' . $zone_id ] ) ) {
				continue;
			}

			KDNA_WH_DB::insert_postcode( $this->country, $postcode, $zone_id );

			$existing[ $postcode . '|' . $zone_id ] = true;
		}
	}

	/**
	 * Fills in the fields the local tables need but a provider may not send.
	 *
	 * The endpoint stands as the citation when the provider gives no document
	 * of its own, because a figure with nothing behind it is exactly what the
	 * confidence field exists to catch.
	 *
	 * @param array $zone A zone row from the provider.
	 * @return array
	 */
	protected function prepare_zone( array $zone ) {
		$source_url = isset( $zone['source_url'] ) && $zone['source_url']
			? $zone['source_url']
			: (string) $this->config['api_endpoint'];

		return array(
			'country_code'   => $this->country,
			'utility_name'   => isset( $zone['utility_name'] ) ? $zone['utility_name'] : '',
			'zone_name'      => isset( $zone['zone_name'] ) ? $zone['zone_name'] : '',
			'hardness_caco3' => isset( $zone['hardness_caco3'] ) ? (float) $zone['hardness_caco3'] : 0,
			'confidence'     => isset( $zone['confidence'] ) ? $zone['confidence'] : KDNA_WH_Sources::get_api_confidence( $this->country ),
			'source_url'     => $source_url,
			'source_date'    => isset( $zone['source_date'] ) && $zone['source_date'] ? $zone['source_date'] : gmdate( 'Y-m-d' ),
		);
	}

	/**
	 * Empties this country's cached answers.
	 *
	 * Transients have no wildcard delete, so the rows are removed directly.
	 * The alternative is waiting a month for a bad answer to expire.
	 *
	 * @param string $country ISO country code.
	 * @return int Number of cached answers removed.
	 */
	public static function clear_cache( $country ) {
		$code = strtolower( KDNA_WH_DB::normalise_country( $country ) );

		if ( '' === $code ) {
			return 0;
		}

		// Lifting the pause matters as much as clearing the answers: after
		// fixing a broken configuration nobody wants to wait five minutes to
		// find out whether it worked.
		delete_transient( 'kdna_wh_src_down_' . $code );

		return KDNA_WH_DB::delete_transients_by_prefix( 'kdna_wh_src_' . $code . '_' );
	}
}
