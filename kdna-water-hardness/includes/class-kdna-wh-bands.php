<?php
/**
 * Classification bands and the copy attached to them.
 *
 * A band is defined by the value it starts at, and runs until the next band
 * begins. Storing only the lower threshold means the bands cannot overlap or
 * leave a gap between them, which is the whole class of misconfiguration a
 * pair of min and max fields invites.
 *
 * Everything here is per country, because conventions vary. Nothing is
 * hardcoded to a brand: all customer-facing wording is editable in the admin.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Bands
 */
class KDNA_WH_Bands {

	/**
	 * Option holding per-country bands and copy.
	 */
	const OPTION = 'kdna_wh_bands';

	/**
	 * Option holding the settings that are not per country.
	 */
	const OPTION_SETTINGS = 'kdna_wh_settings';

	/**
	 * The two result states that carry copy but are not bands.
	 */
	const STATE_KEYS = array( 'inconclusive', 'no_match' );

	/**
	 * The default bands, in the Australian convention from the brief.
	 *
	 * Band keys never change, because the copy is stored against them. Labels,
	 * thresholds and colours are all editable, and a band can be switched off
	 * for a country that uses fewer.
	 *
	 * @return array
	 */
	public static function default_bands() {
		return array(
			'soft'      => array(
				'label'   => __( 'Soft', 'kdna-water-hardness' ),
				'min'     => 0,
				'colour'  => '#8ecae6',
				'enabled' => true,
			),
			'moderate'  => array(
				'label'   => __( 'Moderately hard', 'kdna-water-hardness' ),
				'min'     => 60,
				'colour'  => '#219ebc',
				'enabled' => true,
			),
			'hard'      => array(
				'label'   => __( 'Hard', 'kdna-water-hardness' ),
				'min'     => 120,
				'colour'  => '#ffb703',
				'enabled' => true,
			),
			'very_hard' => array(
				'label'   => __( 'Very hard', 'kdna-water-hardness' ),
				'min'     => 180,
				'colour'  => '#fb8500',
				'enabled' => true,
			),
		);
	}

	/**
	 * Starting copy for each band and state.
	 *
	 * Written to be edited. Two things about it are deliberate rather than
	 * incidental, and are worth keeping if the words are rewritten.
	 *
	 * The soft-water block does not read as "this is not for you". Most of
	 * Australia's population is on soft water, so if that result lands as a
	 * rejection the tool works against itself for the majority of the people
	 * who use it. It reframes instead: soft water rinses so efficiently that
	 * it can take more from the skin than it needs to. Different problem,
	 * same product.
	 *
	 * And every line is an appearance claim. Nothing here says the product
	 * treats, repairs or protects anything.
	 *
	 * @return array
	 */
	public static function default_copy() {
		return array(
			'soft'         => array(
				'heading'  => __( 'Your water is soft', 'kdna-water-hardness' ),
				'body'     => __( 'Soft water lathers easily and rinses away fast, which sounds like the ideal and mostly is. The catch is that it rinses so efficiently it can take more from your skin than it needs to, which is why skin can feel tight and look dull after washing even in the softest water.', 'kdna-water-hardness' ),
				'cta_text' => __( 'See the range', 'kdna-water-hardness' ),
				'cta_url'  => '',
			),
			'moderate'     => array(
				'heading'  => __( 'Your water is moderately hard', 'kdna-water-hardness' ),
				'body'     => __( 'There are enough dissolved minerals in your water to leave a fine film on the skin after washing. It is the reason skin can feel tight, and look dull, in the hours after a shower.', 'kdna-water-hardness' ),
				'cta_text' => __( 'See the range', 'kdna-water-hardness' ),
				'cta_url'  => '',
			),
			'hard'         => array(
				'heading'  => __( 'Your water is hard', 'kdna-water-hardness' ),
				'body'     => __( 'Your water carries a high level of dissolved minerals. They stay behind on the skin as a fine film after washing, which is what leaves skin feeling tight and looking dull, and what leaves the same chalky marks on your shower screen.', 'kdna-water-hardness' ),
				'cta_text' => __( 'See the range', 'kdna-water-hardness' ),
				'cta_url'  => '',
			),
			'very_hard'    => array(
				'heading'  => __( 'Your water is very hard', 'kdna-water-hardness' ),
				'body'     => __( 'Your water is among the most mineral-rich in the country. That mineral film is left on the skin every time you wash, and it is what leaves skin feeling tight and looking dull long after you have dried off.', 'kdna-water-hardness' ),
				'cta_text' => __( 'See the range', 'kdna-water-hardness' ),
				'cta_url'  => '',
			),
			'inconclusive' => array(
				'heading'  => __( 'Your postcode covers more than one water supply', 'kdna-water-hardness' ),
				'body'     => __( 'Water hardness is set by supply zone, not by postcode, and yours spans zones that differ enough that a single figure would be misleading. Your water utility publishes the exact figure for your street, and a home test strip will tell you in a minute. Either way it does not change what your skin needs: hard water leaves a mineral film, soft water strips more readily, and the same routine is built for both.', 'kdna-water-hardness' ),
				'cta_text' => __( 'See the range', 'kdna-water-hardness' ),
				'cta_url'  => '',
			),
			'no_match'     => array(
				'heading'  => __( 'We do not have a reading for your postcode yet', 'kdna-water-hardness' ),
				'body'     => __( 'Our data does not cover your area yet. Your water utility publishes hardness figures for every supply zone and can tell you exactly, and a home test strip will do the same in a minute.', 'kdna-water-hardness' ),
				'cta_text' => __( 'See the range', 'kdna-water-hardness' ),
				'cta_url'  => '',
			),
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Reading and writing
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The whole option.
	 *
	 * @return array
	 */
	public static function get_all() {
		$all = get_option( self::OPTION, array() );

		return is_array( $all ) ? $all : array();
	}

	/**
	 * One country's bands and copy, with defaults filled in for anything not
	 * yet configured.
	 *
	 * @param string $country_code ISO country code.
	 * @return array
	 */
	public static function get_country( $country_code ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );
		$all     = self::get_all();
		$stored  = isset( $all[ $country ] ) && is_array( $all[ $country ] ) ? $all[ $country ] : array();

		$bands = self::default_bands();

		if ( isset( $stored['bands'] ) && is_array( $stored['bands'] ) ) {
			foreach ( $bands as $key => $default ) {
				if ( isset( $stored['bands'][ $key ] ) && is_array( $stored['bands'][ $key ] ) ) {
					$bands[ $key ] = array_merge( $default, $stored['bands'][ $key ] );
				}
			}
		}

		$copy = self::default_copy();

		if ( isset( $stored['copy'] ) && is_array( $stored['copy'] ) ) {
			foreach ( $copy as $key => $default ) {
				if ( isset( $stored['copy'][ $key ] ) && is_array( $stored['copy'][ $key ] ) ) {
					$copy[ $key ] = array_merge( $default, $stored['copy'][ $key ] );
				}
			}
		}

		return array(
			'bands' => $bands,
			'copy'  => $copy,
		);
	}

	/**
	 * Saves one country's bands and copy.
	 *
	 * @param string $country_code ISO country code.
	 * @param array  $bands        Band definitions.
	 * @param array  $copy         Copy blocks.
	 * @return bool
	 */
	public static function save_country( $country_code, array $bands, array $copy ) {
		$country = KDNA_WH_DB::normalise_country( $country_code );

		if ( ! $country ) {
			return false;
		}

		$clean_bands = array();

		foreach ( self::default_bands() as $key => $default ) {
			$input = isset( $bands[ $key ] ) && is_array( $bands[ $key ] ) ? $bands[ $key ] : array();

			$clean_bands[ $key ] = array(
				'label'   => isset( $input['label'] ) ? substr( sanitize_text_field( $input['label'] ), 0, 60 ) : $default['label'],
				'min'     => isset( $input['min'] ) ? max( 0, round( (float) $input['min'], 2 ) ) : $default['min'],
				'colour'  => isset( $input['colour'] ) && sanitize_hex_color( $input['colour'] ) ? sanitize_hex_color( $input['colour'] ) : $default['colour'],
				'enabled' => ! empty( $input['enabled'] ),
			);

			// A band with no label is a band nobody can read on the scale.
			if ( '' === $clean_bands[ $key ]['label'] ) {
				$clean_bands[ $key ]['label'] = $default['label'];
			}
		}

		// The lowest band always starts at zero. Anything else would leave
		// readings below it unclassifiable.
		$first = array_key_first( $clean_bands );

		if ( $first ) {
			$clean_bands[ $first ]['enabled'] = true;
			$clean_bands[ $first ]['min']     = 0;
		}

		$all = self::get_all();

		/*
		 * What is already stored, so a block missing from this submission is
		 * left alone rather than blanked.
		 *
		 * This matters because the form only renders the copy fields for bands
		 * that are switched on. Without this, switching a band off and saving
		 * would quietly erase its copy, and switching it back on would return
		 * an empty block with no way to tell what happened.
		 *
		 * A field that is submitted but empty is still an empty field: that is
		 * someone deliberately clearing it, which is different from not being
		 * asked.
		 */
		$existing = isset( $all[ $country ]['copy'] ) && is_array( $all[ $country ]['copy'] ) ? $all[ $country ]['copy'] : array();

		$clean_copy = array();

		foreach ( self::default_copy() as $key => $default ) {
			if ( ! isset( $copy[ $key ] ) || ! is_array( $copy[ $key ] ) ) {
				if ( isset( $existing[ $key ] ) ) {
					$clean_copy[ $key ] = $existing[ $key ];
				}

				continue;
			}

			$input   = $copy[ $key ];
			$current = isset( $existing[ $key ] ) && is_array( $existing[ $key ] )
				? array_merge( $default, $existing[ $key ] )
				: $default;

			$clean_copy[ $key ] = array(
				'heading'  => isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : $current['heading'],
				// Basic formatting and links are allowed; scripts and anything
				// else a post cannot contain are stripped.
				'body'     => isset( $input['body'] ) ? wp_kses_post( trim( (string) $input['body'] ) ) : $current['body'],
				'cta_text' => isset( $input['cta_text'] ) ? sanitize_text_field( $input['cta_text'] ) : $current['cta_text'],
				'cta_url'  => isset( $input['cta_url'] ) ? esc_url_raw( trim( (string) $input['cta_url'] ) ) : $current['cta_url'],
			);
		}

		$all[ $country ] = array(
			'bands' => $clean_bands,
			'copy'  => $clean_copy,
		);

		return update_option( self::OPTION, $all, false );
	}

	/**
	 * Removes a country's configuration, so it falls back to the defaults.
	 *
	 * @param string $country_code ISO country code.
	 * @return bool
	 */
	public static function reset_country( $country_code ) {
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
	 * The scale
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The bands that are switched on, in order, each with the value it starts
	 * at and the value the next one begins at.
	 *
	 * The highest band has no upper bound. It is given a nominal one, the same
	 * width as the band below it, purely so a marker can be placed inside it.
	 *
	 * @param string $country_code ISO country code.
	 * @return array
	 */
	public static function scale( $country_code ) {
		$config = self::get_country( $country_code );

		$enabled = array();

		foreach ( $config['bands'] as $key => $band ) {
			if ( empty( $band['enabled'] ) ) {
				continue;
			}

			$enabled[ $key ] = $band;
		}

		if ( ! $enabled ) {
			$enabled = array( 'soft' => self::default_bands()['soft'] );
		}

		// Order by where each band starts, whatever order they were stored in.
		uasort(
			$enabled,
			function ( $a, $b ) {
				return $a['min'] <=> $b['min'];
			}
		);

		$keys  = array_keys( $enabled );
		$count = count( $keys );
		$scale = array();

		foreach ( $keys as $index => $key ) {
			$band = $enabled[ $key ];
			$min  = (float) $band['min'];

			if ( $index + 1 < $count ) {
				$max = (float) $enabled[ $keys[ $index + 1 ] ]['min'];
			} else {
				// Nominal width for the open-ended top band.
				$previous = $index > 0 ? (float) $enabled[ $keys[ $index - 1 ] ]['min'] : 0.0;
				$width    = max( $min - $previous, 1.0 );
				$max      = $min + $width;
			}

			// A band that starts where the next one does would be zero wide,
			// which would put every marker in it at the same place.
			if ( $max <= $min ) {
				$max = $min + 1;
			}

			$scale[] = array(
				'key'        => $key,
				'label'      => $band['label'],
				'colour'     => $band['colour'],
				'min'        => $min,
				'max'        => $max,
				'open_ended' => ( $index + 1 === $count ),
				'width'      => round( 100 / $count, 4 ),
			);
		}

		return $scale;
	}

	/**
	 * Works out which band a figure falls in.
	 *
	 * Bands are inclusive at the bottom and exclusive at the top, so a reading
	 * of exactly 60 is moderately hard rather than soft. The boundary has to
	 * belong to one of them, and this way each band owns the value it starts
	 * at.
	 *
	 * @param float  $value        Canonical mg/L CaCO3.
	 * @param string $country_code ISO country code.
	 * @return array|null The band, or null when there is no value.
	 */
	public static function classify( $value, $country_code ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$scale = self::scale( $country_code );
		$value = (float) $value;

		foreach ( $scale as $band ) {
			if ( $value >= $band['min'] && $value < $band['max'] ) {
				return $band;
			}
		}

		// Above every threshold, so it belongs to the open-ended top band.
		$last = end( $scale );

		if ( $last && $value >= $last['min'] ) {
			return $last;
		}

		// Below the lowest threshold, which only happens if the first band has
		// been configured to start above zero.
		return reset( $scale ) ?: null;
	}

	/**
	 * Where a figure sits along the scale, as a percentage from the left.
	 *
	 * Each band takes an equal share of the width rather than a share
	 * proportional to its range. A soft band covering 0 to 60 and an
	 * open-ended very hard band would otherwise render at wildly different
	 * sizes, and the top band could not be drawn at all.
	 *
	 * @param float  $value        Canonical mg/L CaCO3.
	 * @param string $country_code ISO country code.
	 * @return float|null Percentage from 0 to 100.
	 */
	public static function position( $value, $country_code ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$scale = self::scale( $country_code );
		$count = count( $scale );

		if ( ! $count ) {
			return null;
		}

		$value = (float) $value;

		foreach ( $scale as $index => $band ) {
			$is_last = ( $index + 1 === $count );

			if ( $value < $band['min'] && 0 === $index ) {
				return 0.0;
			}

			if ( $value >= $band['min'] && ( $value < $band['max'] || $is_last ) ) {
				$span     = $band['max'] - $band['min'];
				$fraction = $span > 0 ? ( $value - $band['min'] ) / $span : 0.0;
				$fraction = max( 0.0, min( 1.0, $fraction ) );

				return round( ( ( $index + $fraction ) / $count ) * 100, 2 );
			}
		}

		return 100.0;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Copy
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The copy block for a band or a state.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $key          Band key, or inconclusive or no_match.
	 * @return array Heading, body, CTA text and CTA URL.
	 */
	public static function copy_for( $country_code, $key ) {
		$config = self::get_country( $country_code );

		$block = isset( $config['copy'][ $key ] ) ? $config['copy'][ $key ] : array();

		$block = array_merge(
			array(
				'heading'  => '',
				'body'     => '',
				'cta_text' => '',
				'cta_url'  => '',
			),
			$block
		);

		// The body is stored already filtered, and filtered again on the way
		// out. It reaches the page as markup so links and emphasis survive.
		$block['body'] = wp_kses_post( $block['body'] );

		/**
		 * Filters a copy block before it is used.
		 *
		 * @param array  $block   Heading, body, CTA text and CTA URL.
		 * @param string $key     Band or state key.
		 * @param string $country ISO country code.
		 */
		return apply_filters( 'kdna_wh_copy_block', $block, $key, KDNA_WH_DB::normalise_country( $country_code ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings that are not per country
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Reads the global settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_SETTINGS, array() );

		return array_merge(
			array(
				'stale_years'        => 3,
				'inconclusive_stale' => true,
			),
			is_array( $settings ) ? $settings : array()
		);
	}

	/**
	 * Saves the global settings.
	 *
	 * @param array $settings Settings to store.
	 * @return bool
	 */
	public static function save_settings( array $settings ) {
		$clean = array(
			// Zero switches the age check off entirely.
			'stale_years'        => max( 0, min( 50, absint( isset( $settings['stale_years'] ) ? $settings['stale_years'] : 3 ) ) ),
			'inconclusive_stale' => ! empty( $settings['inconclusive_stale'] ),
		);

		return update_option( self::OPTION_SETTINGS, $clean, false );
	}

	/**
	 * How old a source report may be before its figure is treated as dated.
	 *
	 * @return int Years, or zero when the check is switched off.
	 */
	public static function stale_years() {
		$settings = self::get_settings();

		return (int) $settings['stale_years'];
	}

	/**
	 * Whether dated data should force an inconclusive result.
	 *
	 * @return bool
	 */
	public static function stale_is_inconclusive() {
		$settings = self::get_settings();

		return ! empty( $settings['inconclusive_stale'] ) && self::stale_years() > 0;
	}
}
