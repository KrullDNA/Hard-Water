<?php
/**
 * Per-country field configuration.
 *
 * A postcode field is not one field. The label, the example, what counts as
 * valid, and which keyboard a phone should raise all change by country, and
 * getting any of them wrong makes the tool feel broken to the visitor.
 *
 * Patterns are written to work unchanged in both PHP and JavaScript, so the
 * client and the server agree on what is valid. That means no delimiters, no
 * flags, and character classes written out in both cases rather than relying
 * on a case-insensitive flag one engine might apply differently.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Countries
 */
class KDNA_WH_Countries {

	/**
	 * The countries the plugin knows the field rules for.
	 *
	 * A country absent from this list still works. It falls back to the
	 * permissive defaults below, so importing data for a new country is enough
	 * to make the tool work there, and adding an entry here only sharpens the
	 * validation and the wording.
	 *
	 * @return array
	 */
	public static function all() {
		$countries = array(
			'AU' => array(
				'label'       => __( 'Postcode', 'kdna-water-hardness' ),
				'placeholder' => '3000',
				'pattern'     => '^\d{4}$',
				'keyboard'    => 'numeric',
				'maxlength'   => 4,
				'unit'        => 'mg_l_caco3',
			),
			'NZ' => array(
				'label'       => __( 'Postcode', 'kdna-water-hardness' ),
				'placeholder' => '6011',
				'pattern'     => '^\d{4}$',
				'keyboard'    => 'numeric',
				'maxlength'   => 4,
				'unit'        => 'mg_l_caco3',
			),
			'US' => array(
				'label'       => __( 'ZIP Code', 'kdna-water-hardness' ),
				'placeholder' => '90210',
				'pattern'     => '^\d{5}(-\d{4})?$',
				'keyboard'    => 'numeric',
				'maxlength'   => 10,
				'unit'        => 'mg_l_caco3',
			),
			'GB' => array(
				'label'       => __( 'Postcode', 'kdna-water-hardness' ),
				'placeholder' => 'SW1A 1AA',
				// Standard UK format, plus the one-off GIR 0AA.
				'pattern'     => '^(([A-Za-z]{1,2}\d[A-Za-z\d]?)|([Gg][Ii][Rr])) ?\d[A-Za-z]{2}$',
				'keyboard'    => 'text',
				'maxlength'   => 9,
				'unit'        => 'mg_l_caco3',
			),
			'CA' => array(
				'label'       => __( 'Postal Code', 'kdna-water-hardness' ),
				'placeholder' => 'M5V 3L9',
				'pattern'     => '^[A-Za-z]\d[A-Za-z] ?\d[A-Za-z]\d$',
				'keyboard'    => 'text',
				'maxlength'   => 7,
				'unit'        => 'mg_l_caco3',
			),
		);

		/**
		 * Filters the per-country field configuration.
		 *
		 * Adding a country here is optional. It is how you would give a new
		 * country a tighter validation pattern or its own wording without
		 * editing the plugin.
		 *
		 * @param array $countries Country code to field configuration.
		 */
		return apply_filters( 'kdna_wh_country_config', $countries );
	}

	/**
	 * The configuration for one country, with defaults filled in.
	 *
	 * @param string $country_code ISO country code.
	 * @return array
	 */
	public static function get( $country_code ) {
		$code = KDNA_WH_DB::normalise_country( $country_code );
		$all  = self::all();

		/*
		 * The fallback is deliberately permissive. A country with imported
		 * data but no entry above should still work, and it is better to
		 * accept something odd and return no match than to refuse a postcode
		 * that is perfectly valid in a country nobody wrote a rule for.
		 */
		$defaults = array(
			'code'        => $code,
			'name'        => KDNA_WH_Sources::country_name( $code ),
			'label'       => __( 'Postcode', 'kdna-water-hardness' ),
			'placeholder' => '',
			'pattern'     => '^[A-Za-z0-9][A-Za-z0-9 -]{1,11}$',
			'keyboard'    => 'text',
			'maxlength'   => 12,
			'unit'        => KDNA_WH_Units::CANONICAL,
		);

		$config = isset( $all[ $code ] ) ? $all[ $code ] : array();
		$config = array_merge( $defaults, $config );

		// The code and name always come from the country itself, never from a
		// stale entry in the configuration array.
		$config['code'] = $code;
		$config['name'] = KDNA_WH_Sources::country_name( $code );

		if ( ! KDNA_WH_Units::is_valid( $config['unit'] ) ) {
			$config['unit'] = KDNA_WH_Units::CANONICAL;
		}

		$config['unit_label'] = KDNA_WH_Units::abbr( $config['unit'] );

		return $config;
	}

	/**
	 * The countries a visitor can actually choose: those with zone data
	 * present, in the order they should appear.
	 *
	 * This is what makes expansion a data task. Importing a country's CSV puts
	 * it in the dropdown, with no code change.
	 *
	 * @return array Country code to configuration.
	 */
	public static function available() {
		$available = array();

		foreach ( KDNA_WH_DB::get_countries_with_data() as $code ) {
			$available[ $code ] = self::get( $code );
		}

		// Present them by name rather than by code, so the list reads the way
		// a person would expect.
		uasort(
			$available,
			function ( $a, $b ) {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $available;
	}

	/**
	 * Decides which country to show first.
	 *
	 * Geolocation arrives in Stage 5 and will slot in ahead of this. For now
	 * it is the requested country if it has data, otherwise Australia,
	 * otherwise whatever is available.
	 *
	 * @param string $requested Preferred country code.
	 * @return string Country code, or an empty string when nothing has data.
	 */
	public static function default_country( $requested = '' ) {
		$available = KDNA_WH_DB::get_countries_with_data();

		if ( ! $available ) {
			return '';
		}

		$code = KDNA_WH_DB::normalise_country( $requested );

		if ( $code && in_array( $code, $available, true ) ) {
			return $code;
		}

		/**
		 * Filters the fallback country used when nothing better is known.
		 *
		 * @param string $fallback ISO country code.
		 */
		$fallback = KDNA_WH_DB::normalise_country( apply_filters( 'kdna_wh_fallback_country', 'AU' ) );

		if ( $fallback && in_array( $fallback, $available, true ) ) {
			return $fallback;
		}

		return reset( $available );
	}

	/**
	 * Checks a postcode against its country's pattern.
	 *
	 * Runs on the raw value as typed, before normalisation, because the
	 * patterns allow for the spaces and hyphens people actually enter.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $postcode     Postcode as typed.
	 * @return bool
	 */
	public static function is_valid_format( $country_code, $postcode ) {
		$config = self::get( $country_code );
		$value  = trim( (string) $postcode );

		if ( '' === $value ) {
			return false;
		}

		/*
		 * A hash delimiter is safe here: no pattern contains one, and using it
		 * avoids escaping the slashes and backslashes the patterns are full of.
		 *
		 * The D modifier matters more than it looks. By default PCRE lets $
		 * match before a trailing newline, where JavaScript's $ does not, so
		 * "3000\n" would pass server-side validation while failing in the
		 * browser. Both callers trim their input, but the two engines have to
		 * agree on their own terms rather than by luck.
		 */
		$result = preg_match( '#' . $config['pattern'] . '#D', $value );

		// A broken pattern from a filter must not quietly reject everything.
		if ( false === $result ) {
			return true;
		}

		return 1 === $result;
	}

	/**
	 * Prepares a postcode for matching against the database.
	 *
	 * Everything is uppercased with spaces and punctuation removed, and then
	 * the country's own rule is applied on top. A US ZIP+4 is cut back to its
	 * first five digits, because the data is held at five digit level and
	 * 90210-1234 should find 90210 rather than nothing.
	 *
	 * @param string $country_code ISO country code.
	 * @param string $postcode     Postcode as typed.
	 * @return string
	 */
	public static function normalise_postcode( $country_code, $postcode ) {
		$code  = KDNA_WH_DB::normalise_country( $country_code );
		$clean = KDNA_WH_DB::normalise_postcode( $postcode );

		if ( 'US' === $code ) {
			$clean = substr( $clean, 0, 5 );
		}

		/**
		 * Filters the normalised postcode, for a country needing its own rule.
		 *
		 * @param string $clean    Normalised postcode.
		 * @param string $code     ISO country code.
		 * @param string $postcode The postcode as it was typed.
		 */
		return apply_filters( 'kdna_wh_normalise_postcode', $clean, $code, $postcode );
	}

	/**
	 * The configuration the front end needs, trimmed to what it uses and safe
	 * to hand to JavaScript.
	 *
	 * @return array
	 */
	public static function for_script() {
		$out = array();

		foreach ( self::available() as $code => $config ) {
			$out[ $code ] = array(
				'code'        => $config['code'],
				'name'        => $config['name'],
				'label'       => $config['label'],
				'placeholder' => $config['placeholder'],
				'pattern'     => $config['pattern'],
				'keyboard'    => $config['keyboard'],
				'maxlength'   => (int) $config['maxlength'],
			);
		}

		return $out;
	}
}
