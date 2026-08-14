<?php
/**
 * A general purpose JSON adapter.
 *
 * The base class handles everything except reading the provider's answer, and
 * this reads the shapes a hardness API is actually likely to return. It exists
 * so that selecting the API source type does something useful straight away,
 * rather than waiting on a provider-specific class, and so a real provider
 * adapter has a worked example to follow.
 *
 * It accepts a list of zones, a single zone, or either wrapped in a container
 * key, and it recognises the field names providers commonly use. Anything it
 * cannot read is an error, which falls back to local data rather than guessing.
 *
 * A provider needing more than this gets its own subclass overriding
 * parse_response(), and nothing else changes.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Source_JSON
 */
class KDNA_WH_Source_JSON extends KDNA_WH_Source_API {

	/**
	 * Keys a provider might wrap its list of zones in.
	 */
	const CONTAINERS = array( 'zones', 'data', 'results', 'supplies', 'items', 'records' );

	/**
	 * A short name for the admin.
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'JSON API', 'kdna-water-hardness' );
	}

	/**
	 * Reads a provider's JSON into zone rows.
	 *
	 * @param string $body     Raw response body.
	 * @param string $postcode Normalised postcode.
	 * @param array  $response The full response.
	 * @return array|WP_Error
	 */
	protected function parse_response( $body, $postcode, $response ) {
		$decoded = json_decode( $body, true );

		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'kdna_wh_api_json',
				__( 'The provider did not return readable JSON.', 'kdna-water-hardness' )
			);
		}

		$list = $this->find_zone_list( $decoded );

		if ( null === $list ) {
			return new WP_Error(
				'kdna_wh_api_shape',
				__( 'The provider\'s response did not contain anything recognisable as a supply zone.', 'kdna-water-hardness' )
			);
		}

		$zones = array();

		foreach ( $list as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$zone = $this->read_zone( $entry );

			if ( $zone ) {
				$zones[] = $zone;
			}
		}

		return $zones;
	}

	/**
	 * Finds the list of zones inside whatever the provider sent.
	 *
	 * @param mixed $decoded Decoded JSON.
	 * @return array|null The list, or null when there is nothing usable.
	 */
	protected function find_zone_list( $decoded ) {
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		// An empty response is a valid "nothing here" rather than a problem.
		if ( ! $decoded ) {
			return array();
		}

		// A bare list of zones.
		if ( isset( $decoded[0] ) && is_array( $decoded[0] ) ) {
			return $decoded;
		}

		// A list under a container key.
		foreach ( self::CONTAINERS as $key ) {
			if ( isset( $decoded[ $key ] ) && is_array( $decoded[ $key ] ) ) {
				$inner = $decoded[ $key ];

				if ( ! $inner ) {
					return array();
				}

				return isset( $inner[0] ) && is_array( $inner[0] ) ? $inner : array( $inner );
			}
		}

		// A single zone as the whole response.
		if ( null !== $this->first_present( $decoded, $this->hardness_keys() ) ) {
			return array( $decoded );
		}

		return null;
	}

	/**
	 * Reads one zone out of a provider's entry.
	 *
	 * @param array $entry One entry from the response.
	 * @return array|null
	 */
	protected function read_zone( array $entry ) {
		$raw = $this->first_present( $entry, $this->hardness_keys() );

		if ( null === $raw ) {
			return null;
		}

		$value = KDNA_WH_Importer::parse_number( is_scalar( $raw ) ? (string) $raw : '' );

		if ( is_wp_error( $value ) ) {
			return null;
		}

		// The provider may report in its own unit. mg/L as CaCO3 is assumed
		// unless it says otherwise, which matches how most report.
		$unit = $this->first_present( $entry, array( 'unit', 'units', 'uom', 'measure' ) );
		$unit = is_string( $unit ) && KDNA_WH_Units::is_valid( $unit ) ? $unit : KDNA_WH_Units::CANONICAL;

		$name = $this->first_present( $entry, array( 'zone_name', 'zone', 'supply_zone', 'supplyZone', 'name', 'area', 'district' ) );
		$date = $this->first_present( $entry, array( 'source_date', 'sourceDate', 'published', 'date', 'sample_date', 'year' ) );
		$url  = $this->first_present( $entry, array( 'source_url', 'sourceUrl', 'source', 'url', 'reference' ) );

		$confidence = $this->first_present( $entry, array( 'confidence', 'quality', 'status' ) );

		if ( $date ) {
			$parsed = KDNA_WH_Importer::parse_date( is_scalar( $date ) ? (string) $date : '' );
			$date   = is_wp_error( $parsed ) ? '' : $parsed;
		}

		return array(
			'zone_name'      => $name ? sanitize_text_field( (string) $name ) : sprintf(
				/* translators: %s: country code, used when a provider names no zone. */
				__( '%s supply', 'kdna-water-hardness' ),
				$this->country
			),
			'utility_name'   => sanitize_text_field( (string) $this->first_present( $entry, array( 'utility_name', 'utility', 'supplier', 'company', 'provider', 'water_company' ) ) ),
			'hardness_caco3' => KDNA_WH_Units::to_canonical( $value, $unit ),
			'confidence'     => is_string( $confidence ) && 'estimated' === strtolower( $confidence )
				? 'estimated'
				: KDNA_WH_Sources::get_api_confidence( $this->country ),
			'source_url'     => is_string( $url ) ? esc_url_raw( $url ) : '',
			'source_date'    => is_string( $date ) ? $date : '',
		);
	}

	/**
	 * The field names a hardness figure might arrive under.
	 *
	 * @return array
	 */
	protected function hardness_keys() {
		return array( 'hardness_caco3', 'hardnessCaCO3', 'hardness', 'total_hardness', 'totalHardness', 'value', 'caco3', 'ppm', 'mg_l' );
	}

	/**
	 * Returns the first of a list of keys that is present and not empty.
	 *
	 * @param array $entry Entry to read.
	 * @param array $keys  Keys to try, in order.
	 * @return mixed|null
	 */
	protected function first_present( array $entry, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $entry[ $key ] ) && '' !== $entry[ $key ] && null !== $entry[ $key ] ) {
				return $entry[ $key ];
			}
		}

		return null;
	}
}
