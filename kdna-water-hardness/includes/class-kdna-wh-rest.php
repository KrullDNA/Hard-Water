<?php
/**
 * REST endpoint for the lookup.
 *
 * Public and read-only. Anyone can ask what the water hardness is at a
 * postcode, which is the point of the tool, so there is no authentication.
 * Every argument is validated and sanitised on the way in regardless, and the
 * lookup itself never trusts the client's own validation.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_REST
 */
class KDNA_WH_REST {

	/**
	 * Route namespace.
	 */
	const NAMESPACE_V1 = 'kdna-wh/v1';

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/lookup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_lookup' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'country'  => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'ISO 3166-1 alpha-2 country code.', 'kdna-water-hardness' ),
						'sanitize_callback' => array( 'KDNA_WH_DB', 'normalise_country' ),
						'validate_callback' => array( __CLASS__, 'validate_country' ),
					),
					'postcode' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => __( 'Postcode, ZIP or postal code, as typed.', 'kdna-water-hardness' ),
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => array( __CLASS__, 'validate_postcode' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/countries',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_countries' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Rejects anything that is not a two letter code before it reaches the
	 * lookup.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_country( $value ) {
		return '' !== KDNA_WH_DB::normalise_country( $value );
	}

	/**
	 * A first pass on the postcode: present, and short enough to be one.
	 *
	 * The country-specific pattern is applied inside the lookup rather than
	 * here, because a wrongly formatted postcode is a result the visitor
	 * should see explained, not a 400 the JavaScript has to interpret.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_postcode( $value ) {
		$value = trim( (string) $value );

		return '' !== $value && strlen( $value ) <= 16;
	}

	/**
	 * Handles a lookup request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_lookup( WP_REST_Request $request ) {
		$result = KDNA_WH_Lookup::lookup(
			$request->get_param( 'country' ),
			$request->get_param( 'postcode' )
		);

		$result['source_summary'] = KDNA_WH_Lookup::source_summary( $result );

		// Logged here rather than inside lookup() so the admin, the importer
		// or anything else can run a lookup without it counting as a visitor.
		KDNA_WH_Lookup::record( $result );

		/**
		 * Filters the lookup result before it is returned.
		 *
		 * @param array           $result  The result.
		 * @param WP_REST_Request $request The request.
		 */
		$result = apply_filters( 'kdna_wh_lookup_result', $result, $request );

		$response = rest_ensure_response( $result );

		/*
		 * Hardness figures change once a year at most, but a result is still
		 * per-postcode and a shared cache holding one visitor's answer for the
		 * next is worse than no cache. Transient caching on the server side
		 * arrives in Stage 7.
		 */
		$response->header( 'Cache-Control', 'no-store, max-age=0' );

		return $response;
	}

	/**
	 * Returns the countries that have data, with their field configuration.
	 *
	 * The shortcode renders this into the page already, so the front end does
	 * not call this. It exists so the data is reachable for anything built
	 * against the plugin later, and to make the same list verifiable from
	 * outside the admin.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_countries() {
		return rest_ensure_response(
			array(
				'countries' => array_values( KDNA_WH_Countries::for_script() ),
				'default'   => KDNA_WH_Countries::default_country(),
			)
		);
	}

	/**
	 * The full URL of the lookup endpoint, for handing to the front end.
	 *
	 * @return string
	 */
	public static function lookup_url() {
		return rest_url( self::NAMESPACE_V1 . '/lookup' );
	}
}
