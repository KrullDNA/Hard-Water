<?php
/**
 * Water hardness unit conversion.
 *
 * The plugin stores one canonical unit, mg/L as CaCO3, and converts on the way
 * in and on the way out. Source files are converted at import, and results are
 * converted again at display according to the country's preference. Mixed
 * units are never stored, because that is how international expansion turns
 * into a rebuild.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Units
 */
class KDNA_WH_Units {

	/**
	 * The canonical unit. Everything in the database is stored in this.
	 */
	const CANONICAL = 'mg_l_caco3';

	/**
	 * Conversion factors, expressed as the number of mg/L CaCO3 in one unit.
	 *
	 * To convert a source value to canonical, multiply by the factor.
	 * To convert a canonical value for display, divide by the factor.
	 *
	 * @return array Unit key to multiplier.
	 */
	public static function factors() {
		return array(
			'mg_l_caco3' => 1.0,     // mg/L as CaCO3, also written ppm. Australia, USA.
			'mg_l_ca'    => 2.497,   // mg/L as calcium. Some UK reporting.
			'clark'      => 14.3,    // Clark degrees. UK.
			'dh'         => 17.85,   // German degrees. Germany and central Europe.
			'fh'         => 10.0,    // French degrees. France.
		);
	}

	/**
	 * Human-readable detail for each unit: a full name for admin dropdowns, a
	 * short suffix for the front end, and how many decimals to show.
	 *
	 * The small units carry a decimal place because rounding 5.4 Clark to 5
	 * loses meaningful precision, while whole numbers are correct for ppm.
	 *
	 * @return array Unit key to detail array.
	 */
	public static function units() {
		return array(
			'mg_l_caco3' => array(
				'label'    => __( 'mg/L as CaCO3 (ppm)', 'kdna-water-hardness' ),
				'abbr'     => __( 'mg/L', 'kdna-water-hardness' ),
				'used_in'  => __( 'Australia, USA, New Zealand', 'kdna-water-hardness' ),
				'decimals' => 0,
			),
			'mg_l_ca'    => array(
				'label'    => __( 'mg/L as Ca', 'kdna-water-hardness' ),
				'abbr'     => __( 'mg/L Ca', 'kdna-water-hardness' ),
				'used_in'  => __( 'Some UK reporting', 'kdna-water-hardness' ),
				'decimals' => 1,
			),
			'clark'      => array(
				'label'    => __( 'Clark degrees', 'kdna-water-hardness' ),
				'abbr'     => __( '°Clark', 'kdna-water-hardness' ),
				'used_in'  => __( 'United Kingdom', 'kdna-water-hardness' ),
				'decimals' => 1,
			),
			'dh'         => array(
				'label'    => __( 'German degrees', 'kdna-water-hardness' ),
				'abbr'     => __( '°dH', 'kdna-water-hardness' ),
				'used_in'  => __( 'Germany, central Europe', 'kdna-water-hardness' ),
				'decimals' => 1,
			),
			'fh'         => array(
				'label'    => __( 'French degrees', 'kdna-water-hardness' ),
				'abbr'     => __( '°fH', 'kdna-water-hardness' ),
				'used_in'  => __( 'France', 'kdna-water-hardness' ),
				'decimals' => 1,
			),
		);
	}

	/**
	 * Checks whether a unit key is one the plugin knows about.
	 *
	 * @param string $unit Unit key.
	 * @return bool
	 */
	public static function is_valid( $unit ) {
		return array_key_exists( self::clean_key( $unit ), self::factors() );
	}

	/**
	 * Converts a value from any supported unit into the canonical mg/L CaCO3.
	 * Used at import.
	 *
	 * @param float  $value Source value.
	 * @param string $unit  Source unit key.
	 * @return float Canonical value, rounded to two decimals.
	 */
	public static function to_canonical( $value, $unit = self::CANONICAL ) {
		$factors = self::factors();
		$key     = self::clean_key( $unit );

		if ( ! isset( $factors[ $key ] ) ) {
			$key = self::CANONICAL;
		}

		return round( (float) $value * $factors[ $key ], 2 );
	}

	/**
	 * Converts a canonical mg/L CaCO3 value into a display unit.
	 * Used at render.
	 *
	 * @param float  $value Canonical value.
	 * @param string $unit  Target unit key.
	 * @return float Converted value, rounded to that unit's precision.
	 */
	public static function from_canonical( $value, $unit = self::CANONICAL ) {
		$factors = self::factors();
		$key     = self::clean_key( $unit );

		if ( ! isset( $factors[ $key ] ) || 0.0 === (float) $factors[ $key ] ) {
			$key = self::CANONICAL;
		}

		return round( (float) $value / $factors[ $key ], self::decimals( $key ) );
	}

	/**
	 * Converts directly between any two supported units, via canonical.
	 *
	 * @param float  $value Source value.
	 * @param string $from  Source unit key.
	 * @param string $to    Target unit key.
	 * @return float
	 */
	public static function convert( $value, $from, $to ) {
		return self::from_canonical( self::to_canonical( $value, $from ), $to );
	}

	/**
	 * Formats a canonical value for display, as a number with its unit.
	 *
	 * @param float  $value      Canonical value.
	 * @param string $unit       Target unit key.
	 * @param bool   $with_unit  Append the unit abbreviation.
	 * @return string e.g. "96 mg/L" or "6.7 °Clark".
	 */
	public static function format( $value, $unit = self::CANONICAL, $with_unit = true ) {
		$key       = self::clean_key( $unit );
		$converted = self::from_canonical( $value, $key );
		$formatted = number_format_i18n( $converted, self::decimals( $key ) );

		if ( ! $with_unit ) {
			return $formatted;
		}

		return $formatted . ' ' . self::abbr( $key );
	}

	/**
	 * Returns the short unit suffix, e.g. mg/L.
	 *
	 * @param string $unit Unit key.
	 * @return string
	 */
	public static function abbr( $unit ) {
		$units = self::units();
		$key   = self::clean_key( $unit );

		return isset( $units[ $key ] ) ? $units[ $key ]['abbr'] : $units[ self::CANONICAL ]['abbr'];
	}

	/**
	 * Returns the full unit name for admin dropdowns.
	 *
	 * @param string $unit Unit key.
	 * @return string
	 */
	public static function label( $unit ) {
		$units = self::units();
		$key   = self::clean_key( $unit );

		return isset( $units[ $key ] ) ? $units[ $key ]['label'] : $units[ self::CANONICAL ]['label'];
	}

	/**
	 * How many decimal places this unit should display.
	 *
	 * @param string $unit Unit key.
	 * @return int
	 */
	public static function decimals( $unit ) {
		$units = self::units();
		$key   = self::clean_key( $unit );

		return isset( $units[ $key ] ) ? (int) $units[ $key ]['decimals'] : 0;
	}

	/**
	 * A key to label list, ready for a select field in the admin.
	 *
	 * @return array
	 */
	public static function options() {
		$options = array();

		foreach ( self::units() as $key => $unit ) {
			$options[ $key ] = $unit['label'];
		}

		return $options;
	}

	/**
	 * Tidies a unit key so minor variations from settings or CSV headers still
	 * resolve, e.g. "°dH" or "mg/L CaCO3".
	 *
	 * @param string $unit Raw unit string.
	 * @return string Normalised key.
	 */
	private static function clean_key( $unit ) {
		$key = strtolower( trim( (string) $unit ) );

		// Common spellings that should resolve to a known key.
		$aliases = array(
			'ppm'            => 'mg_l_caco3',
			'mg/l'           => 'mg_l_caco3',
			'mg/l caco3'     => 'mg_l_caco3',
			'mg/l as caco3'  => 'mg_l_caco3',
			'caco3'          => 'mg_l_caco3',
			'mg/l ca'        => 'mg_l_ca',
			'mg/l as ca'     => 'mg_l_ca',
			'ca'             => 'mg_l_ca',
			'clark'          => 'clark',
			'°clark'         => 'clark',
			'clark degrees'  => 'clark',
			'°dh'            => 'dh',
			'dh'             => 'dh',
			'german degrees' => 'dh',
			'°fh'            => 'fh',
			'fh'             => 'fh',
			'french degrees' => 'fh',
		);

		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ];
		}

		return $key;
	}
}
