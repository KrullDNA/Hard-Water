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
	 * A figure exists but cannot honestly be given as one answer.
	 */
	const STATE_INCONCLUSIVE = 'inconclusive';

	/**
	 * A valid postcode that is not in the data.
	 */
	const STATE_NO_MATCH = 'no_match';

	/**
	 * Not a postcode for the selected country.
	 */
	const STATE_INVALID = 'invalid';

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

		return self::build_result( self::decide_state( $zones, $requested ), $requested, $normalised, $zones );
	}

	/**
	 * Decides which of the three matched states a set of zones produces.
	 *
	 * The order matters. A postcode whose zones fall in different bands is
	 * inconclusive whatever else is true of it, because that is the case where
	 * any single figure, or even a range, tells the visitor nothing useful.
	 * Perth is the live example: Water Corporation's zones run from 29 mg/L at
	 * Dwellingup to 228 at Two Rocks, and a postcode straddling soft and hard
	 * cannot be classified.
	 *
	 * @param array  $zones        Matching zone rows.
	 * @param string $country_code ISO country code.
	 * @return string One of the state constants.
	 */
	private static function decide_state( array $zones, $country_code ) {
		$values = array();
		$oldest = '';
		$weak   = false;

		foreach ( $zones as $zone ) {
			$values[] = (float) $zone['hardness_caco3'];

			if ( 'verified' !== $zone['confidence'] ) {
				$weak = true;
			}

			$date = isset( $zone['source_date'] ) ? (string) $zone['source_date'] : '';

			if ( $date && ( '' === $oldest || $date < $oldest ) ) {
				$oldest = $date;
			}
		}

		$min = min( $values );
		$max = max( $values );

		// Trigger one: the range crosses a band boundary.
		$low_band  = KDNA_WH_Bands::classify( $min, $country_code );
		$high_band = KDNA_WH_Bands::classify( $max, $country_code );

		if ( $low_band && $high_band && $low_band['key'] !== $high_band['key'] ) {
			return self::STATE_INCONCLUSIVE;
		}

		// Trigger two: the figure is inferred rather than published.
		if ( $weak ) {
			return self::STATE_INCONCLUSIVE;
		}

		// Trigger three: the newest figure available is old enough to doubt.
		if ( self::is_stale( $oldest ) && KDNA_WH_Bands::stale_is_inconclusive() ) {
			return self::STATE_INCONCLUSIVE;
		}

		/*
		 * Several zones that agree on the figure is still a confident answer.
		 * It only becomes a range when they actually differ, so a postcode
		 * split across two zones reading 96 each is not dressed up as
		 * uncertainty it does not have.
		 */
		return ( abs( $max - $min ) < 0.005 ) ? self::STATE_CONFIDENT : self::STATE_RANGE;
	}

	/**
	 * Whether a source date is old enough that the figure should be doubted.
	 *
	 * A zone with no date at all counts as stale. Not knowing how old a figure
	 * is raises the same question as knowing it is old.
	 *
	 * @param string $date Source date, Y-m-d, or empty.
	 * @return bool
	 */
	private static function is_stale( $date ) {
		$years = KDNA_WH_Bands::stale_years();

		if ( $years <= 0 ) {
			return false;
		}

		if ( '' === $date ) {
			return true;
		}

		return strtotime( $date ) < strtotime( '-' . $years . ' years', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
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
			'band'          => null,
			'band_key'      => '',
			'band_label'    => '',
			'bands'         => KDNA_WH_Bands::scale( $country ),
			'position'      => null,
			'min_position'  => null,
			'max_position'  => null,
			'copy'          => array(),
		);

		/*
		 * The two states with nothing to classify still carry copy, so the
		 * wording a visitor sees is editable rather than baked into the code.
		 */
		if ( self::STATE_NO_MATCH === $state ) {
			$result['copy'] = KDNA_WH_Bands::copy_for( $country, 'no_match' );
		}

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

		$result['is_stale'] = self::is_stale( $oldest );

		/*
		 * Where the answer sits on the scale. Both ends are given, so a range
		 * can be drawn as a span rather than a single marker.
		 */
		$result['min_position'] = KDNA_WH_Bands::position( $min, $country );
		$result['max_position'] = KDNA_WH_Bands::position( $max, $country );

		$band = KDNA_WH_Bands::classify( $min, $country );

		/*
		 * An inconclusive result gets no band. Naming one would be exactly the
		 * false certainty the state exists to avoid, whether the cause is a
		 * postcode straddling two bands, an estimated figure, or a reading old
		 * enough to doubt.
		 */
		if ( self::STATE_INCONCLUSIVE !== $state && $band ) {
			$result['band']       = $band;
			$result['band_key']   = $band['key'];
			$result['band_label'] = $band['label'];
			$result['copy']       = KDNA_WH_Bands::copy_for( $country, $band['key'] );
		}

		if ( self::STATE_INCONCLUSIVE === $state ) {
			$result['copy']   = KDNA_WH_Bands::copy_for( $country, 'inconclusive' );
			$result['reason'] = self::inconclusive_reason( $result );
		}

		/*
		 * Whether there is a spread, rather than which state this is. An
		 * inconclusive result caused by an estimated figure can have a single
		 * zone, and "50 to 50" is not a range.
		 */
		if ( ( $max - $min ) >= 0.005 ) {
			$result['range_display'] = sprintf(
				/* translators: 1: lowest value, 2: highest value, 3: unit, e.g. mg/L. */
				__( '%1$s to %2$s %3$s', 'kdna-water-hardness' ),
				$result['min_display'],
				$result['max_display'],
				$result['unit_label']
			);

			// A spread has no single figure, and inventing an average would be
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
	 * Which of the three triggers made a result inconclusive, and how to say
	 * so plainly.
	 *
	 * This is not a failure message. Saying exactly why one number would be
	 * misleading is more credible than the competitors who simply guess, and
	 * it gives the visitor something they can act on.
	 *
	 * @param array $result The result being built.
	 * @return array Reason key and its explanation.
	 */
	private static function inconclusive_reason( array $result ) {
		$zone_names = array();

		foreach ( $result['zones'] as $zone ) {
			if ( $zone['zone_name'] ) {
				$zone_names[] = $zone['zone_name'];
			}
		}

		$zone_names = array_unique( $zone_names );

		$low  = KDNA_WH_Bands::classify( $result['min'], $result['country'] );
		$high = KDNA_WH_Bands::classify( $result['max'], $result['country'] );

		if ( $low && $high && $low['key'] !== $high['key'] ) {
			return array(
				'key'  => 'band_crossing',
				'text' => sprintf(
					/* translators: 1: lowest band label, 2: highest band label, 3: the range with its unit. */
					__( 'Your postcode covers supply zones ranging from %1$s to %2$s water, %3$s. A single figure would be misleading.', 'kdna-water-hardness' ),
					strtolower( $low['label'] ),
					strtolower( $high['label'] ),
					$result['range_display'] ? $result['range_display'] : trim( $result['min_display'] . ' to ' . $result['max_display'] . ' ' . $result['unit_label'] )
				),
				'zones' => $zone_names,
			);
		}

		if ( $result['is_estimated'] ) {
			return array(
				'key'   => 'estimated',
				'text'  => __( 'The figure we hold for your area is an estimate, worked out from nearby zones rather than published for yours, so we would rather not give it as a straight answer.', 'kdna-water-hardness' ),
				'zones' => $zone_names,
			);
		}

		$years = KDNA_WH_Bands::stale_years();

		return array(
			'key'   => 'stale',
			'text'  => $result['oldest_source']
				? sprintf(
					/* translators: %d: number of years. */
					__( 'The most recent figure published for your area is more than %d years old, so it may no longer be accurate.', 'kdna-water-hardness' ),
					$years
				)
				: __( 'We cannot tell how recent the figure for your area is, so we would rather not give it as a straight answer.', 'kdna-water-hardness' ),
			'zones' => $zone_names,
		);
	}

	/**
	 * Records a lookup.
	 *
	 * What goes in is a country, a postcode, the figure served and the band it
	 * fell in. What does not go in is an IP address, an email address, or
	 * anything else identifying a person. Postcode plus timestamp is enough
	 * for the geographic picture the log exists to give, and keeps the plugin
	 * clear of handling personal data at all.
	 *
	 * @param array $result A result from lookup().
	 * @return void
	 */
	public static function record( array $result ) {
		/**
		 * Filters whether lookups are logged.
		 *
		 * @param bool  $enabled Whether to log.
		 * @param array $result  The result about to be logged.
		 */
		if ( ! apply_filters( 'kdna_wh_log_lookups', true, $result ) ) {
			return;
		}

		// An invalid postcode is a typo, not a place. There is nothing
		// geographic to learn from it and no postcode worth storing.
		if ( self::STATE_INVALID === $result['state'] ) {
			return;
		}

		if ( empty( $result['postcode'] ) ) {
			return;
		}

		/*
		 * The band is stored as served. For an inconclusive or unmatched
		 * lookup the state is stored in its place, so the log can show how
		 * often the tool could not answer, which is worth knowing.
		 */
		$band = $result['band_key'] ? $result['band_key'] : $result['state'];

		KDNA_WH_DB::log_lookup(
			$result['country'],
			$result['postcode'],
			isset( $result['value'] ) ? $result['value'] : null,
			$band
		);
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
