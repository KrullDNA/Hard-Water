<?php
/**
 * Lookup logic.
 *
 * Turns a country and a postcode into a result the front end can render. The
 * important part is what happens when a postcode spans more than one supply
 * zone, which is common and is the case a simpler design gets wrong: rather
 * than picking a figure, the result says plainly that the postcode covers
 * more than one zone and gives the range.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Lookup
 */
class KDNA_WH_Lookup {

	/**
	 * One zone, or several that agree on the figure.
	 */
	const STATE_CONFIDENT = 'confident';

	/**
	 * Several zones with different figures.
	 */
	const STATE_RANGE = 'range';

	/**
	 * A valid postcode that is not in the data.
	 */
	const STATE_NO_MATCH = 'no_match';

	/**
	 * Not a postcode for the selected country.
	 */
	const STATE_INVALID = 'invalid';

	/**
	 * How old a source report can be before the figure is treated as dated.
	 * Reported alongside the result now, and used to force an inconclusive
	 * answer once the bands and their copy exist in Stage 4.
	 */
	const STALE_YEARS = 3;

	/**
	 * Performs a lookup.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $postcode     Postcode as the visitor typed it.
	 * @return array The result, always with a state.
	 */
	public static function lookup( $country_code, $postcode ) {
		$requested = KDNA_WH_DB::normalise_country( $country_code );
		$available = KDNA_WH_DB::get_countries_with_data();

		// An unknown country is treated as no match rather than an error. The
		// visitor did nothing wrong, and there is nothing for them to fix.
		if ( ! $requested || ! in_array( $requested, $available, true ) ) {
			return self::build_result(
				self::STATE_NO_MATCH,
				$requested,
				KDNA_WH_DB::normalise_postcode( $postcode ),
				array(),
				__( 'We do not have water hardness data for that country yet.', 'kdna-water-hardness' )
			);
		}

		$config = KDNA_WH_Countries::get( $requested );
		$raw    = trim( (string) $postcode );

		/*
		 * The client checks this too, for immediate feedback. This check is
		 * the one that counts: anything can post to the endpoint.
		 */
		if ( ! KDNA_WH_Countries::is_valid_format( $requested, $raw ) ) {
			return self::build_result(
				self::STATE_INVALID,
				$requested,
				'',
				array(),
				sprintf(
					/* translators: 1: field label such as Postcode, 2: an example postcode. */
					__( 'That does not look like a %1$s. Try something like %2$s.', 'kdna-water-hardness' ),
					strtolower( $config['label'] ),
					$config['placeholder'] ? $config['placeholder'] : '3000'
				)
			);
		}

		$normalised = KDNA_WH_Countries::normalise_postcode( $requested, $raw );
		$zones      = KDNA_WH_DB::get_zones_for_postcode( $requested, $normalised );

		if ( ! $zones ) {
			return self::build_result(
				self::STATE_NO_MATCH,
				$requested,
				$normalised,
				array(),
				__( 'We do not have a reading for that postcode yet. Your water utility publishes hardness figures for your area and can tell you exactly.', 'kdna-water-hardness' )
			);
		}

		$values = array_map(
			function ( $zone ) {
				return (float) $zone['hardness_caco3'];
			},
			$zones
		);

		$min = min( $values );
		$max = max( $values );

		/*
		 * Several zones that agree on the figure is still a confident answer.
		 * It only becomes a range when they actually differ, so a postcode
		 * split across two zones reading 96 each is not dressed up as
		 * uncertainty it does not have.
		 */
		$state = ( abs( $max - $min ) < 0.005 ) ? self::STATE_CONFIDENT : self::STATE_RANGE;

		return self::build_result( $state, $requested, $normalised, $zones );
	}

	/**
	 * Assembles the result array, including everything the front end needs to
	 * render without doing any conversion of its own.
	 *
	 * @param string $state      One of the state constants.
	 * @param string $country    ISO country code.
	 * @param string $postcode   Normalised postcode.
	 * @param array  $zones      Matching zone rows.
	 * @param string $message    Message for the states that carry one.
	 * @return array
	 */
	private static function build_result( $state, $country, $postcode, array $zones = array(), $message = '' ) {
		$config = KDNA_WH_Countries::get( $country );
		$unit   = $config['unit'];

		$result = array(
			'state'         => $state,
			'country'       => $country,
			'country_name'  => $config['name'],
			'postcode'      => $postcode,
			'unit'          => $unit,
			'unit_label'    => KDNA_WH_Units::abbr( $unit ),
			'message'       => $message,
			'value'         => null,
			'value_display' => '',
			'min'           => null,
			'max'           => null,
			'min_display'   => '',
			'max_display'   => '',
			'range_display' => '',
			'zones'         => array(),
			'zone_count'    => 0,
			'spans_zones'   => false,
			'confidence'    => '',
			'is_estimated'  => false,
			'is_stale'      => false,
			'oldest_source' => '',
		);

		if ( ! $zones ) {
			return $result;
		}

		$values     = array();
		$confidence = 'verified';
		$oldest     = '';

		foreach ( $zones as $zone ) {
			$value    = (float) $zone['hardness_caco3'];
			$values[] = $value;

			// The weakest zone sets the tone for the whole answer. One
			// estimated zone in the set means the answer is not verified.
			if ( 'verified' !== $zone['confidence'] ) {
				$confidence = 'estimated';
			}

			$date = isset( $zone['source_date'] ) ? (string) $zone['source_date'] : '';

			if ( $date && ( '' === $oldest || $date < $oldest ) ) {
				$oldest = $date;
			}

			$result['zones'][] = array(
				'zone_name'       => $zone['zone_name'],
				'utility_name'    => $zone['utility_name'],
				'value'           => $value,
				'value_display'   => KDNA_WH_Units::format( $value, $unit, false ),
				'confidence'      => $zone['confidence'],
				'source_url'      => $zone['source_url'],
				'source_date'     => $date,
				'source_date_display' => $date ? mysql2date( get_option( 'date_format' ), $date ) : '',
			);
		}

		$min = min( $values );
		$max = max( $values );

		$result['min']           = $min;
		$result['max']           = $max;
		$result['min_display']   = KDNA_WH_Units::format( $min, $unit, false );
		$result['max_display']   = KDNA_WH_Units::format( $max, $unit, false );
		$result['zone_count']    = count( $zones );
		$result['spans_zones']   = count( $zones ) > 1;
		$result['confidence']    = $confidence;
		$result['is_estimated']  = 'verified' !== $confidence;
		$result['oldest_source'] = $oldest;

		/*
		 * A figure from a report several years old is still the best available
		 * answer, so it is served rather than withheld. The flag rides along
		 * with the result so that Stage 4, which has the copy to explain
		 * itself properly, can turn it into an inconclusive answer.
		 */
		$result['is_stale'] = '' === $oldest
			? true
			: strtotime( $oldest ) < strtotime( '-' . self::STALE_YEARS . ' years', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		if ( self::STATE_RANGE === $state ) {
			$result['range_display'] = sprintf(
				/* translators: 1: lowest value, 2: highest value, 3: unit, e.g. mg/L. */
				__( '%1$s to %2$s %3$s', 'kdna-water-hardness' ),
				$result['min_display'],
				$result['max_display'],
				$result['unit_label']
			);

			// A range has no single figure, and inventing an average would be
			// exactly the guess this whole data model exists to avoid.
			$result['value']         = null;
			$result['value_display'] = $result['range_display'];
		} else {
			$result['value']         = $min;
			$result['value_display'] = KDNA_WH_Units::format( $min, $unit, false );
		}

		return $result;
	}

	/**
	 * A short description of where a figure came from, for the line under the
	 * result. Naming the supply zone is itself a credibility signal, so it is
	 * used wherever there is one to name.
	 *
	 * @param array $result A result from lookup().
	 * @return string
	 */
	public static function source_summary( array $result ) {
		if ( empty( $result['zones'] ) ) {
			return '';
		}

		$names = array();

		foreach ( $result['zones'] as $zone ) {
			if ( $zone['zone_name'] ) {
				$names[] = $zone['zone_name'];
			}
		}

		$names = array_unique( $names );

		if ( ! $names ) {
			return '';
		}

		if ( 1 === count( $names ) ) {
			return sprintf(
				/* translators: %s: supply zone name. */
				__( 'Supply zone: %s', 'kdna-water-hardness' ),
				$names[0]
			);
		}

		return sprintf(
			/* translators: %s: comma separated list of supply zone names. */
			__( 'Supply zones: %s', 'kdna-water-hardness' ),
			implode( ', ', $names )
		);
	}
}
