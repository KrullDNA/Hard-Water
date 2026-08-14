<?php
/** Runs the real CSVs through the real lookup, to see what a visitor would get. */
error_reporting( E_ALL );
set_error_handler( function ( $n, $s, $f, $l ) { echo "NOTICE: $s in $f:$l\n"; exit( 1 ); } );

define( 'ABSPATH', '/wp/' );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['options'] = array(); $GLOBALS['transients'] = array();
function __( $t, $d = '' ) { return $t; }
function absint( $v ) { return abs( (int) $v ); }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function current_time( $f ) { return 'timestamp' === $f ? time() : gmdate( 'Y-m-d H:i:s' ); }
function apply_filters( $t, $v ) { return $v; }
function get_option( $k, $d = false ) { return 'date_format' === $k ? 'j F Y' : ( $GLOBALS['options'][ $k ] ?? $d ); }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function mysql2date( $f, $d ) { return gmdate( 'j F Y', strtotime( $d ) ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $c ) ? $c : null; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function esc_url_raw( $u ) { return (string) $u; }
function wp_kses_post( $s ) { return (string) $s; }
function get_transient( $k ) { return false; }
function set_transient( ...$a ) { return true; }
class WP_Error { public $c, $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; }
	function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }

$Z = array(); $P = array();
$fh = fopen( '/home/user/Hard-Water/data/au-zones.csv', 'r' ); fgetcsv( $fh, 0, ',', '"', '' );
while ( ( $r = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) {
	$Z[ $r[0] ] = array( 'zone_id' => count( $Z ) + 1, 'country_code' => 'AU', 'zone_name' => $r[0],
		'utility_name' => $r[1], 'hardness_caco3' => (float) $r[2], 'confidence' => $r[3],
		'source_url' => $r[4], 'source_date' => $r[5] ?: '2025-01-01' );
}
$fh = fopen( '/home/user/Hard-Water/data/au-postcodes.csv', 'r' ); fgetcsv( $fh, 0, ',', '"', '' );
while ( ( $r = fgetcsv( $fh, 0, ',', '"', '' ) ) !== false ) { $P[ $r[0] ][] = $r[1]; }

class KDNA_WH_DB {
	public static function normalise_country( $c ) { $x = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $c ) ); return 2 === strlen( $x ) ? $x : ''; }
	public static function normalise_postcode( $p ) { return substr( (string) preg_replace( '/[^A-Z0-9]/', '', strtoupper( (string) $p ) ), 0, 12 ); }
	public static function get_countries_with_data() { return array( 'AU' ); }
	public static function get_zones_for_postcode( $c, $p ) {
		$out = array();
		foreach ( $GLOBALS['P'][ $p ] ?? array() as $n ) { if ( isset( $GLOBALS['Z'][ $n ] ) ) { $out[] = $GLOBALS['Z'][ $n ]; } }
		return $out;
	}
}
class KDNA_WH_Sources {
	public static function country_name( $c ) { return 'AU' === $c ? 'Australia' : $c; }
	public static function get_serviceable_countries() { return array( 'AU' ); }
	public static function get_adapter( $c ) { return new Stub(); }
}
class Stub { function get_country() { return 'AU'; } function get_label() { return 's'; } function is_available() { return true; }
	function get_zones( $p ) { return KDNA_WH_DB::get_zones_for_postcode( 'AU', $p ); } }

$b = '/home/user/Hard-Water/kdna-water-hardness/includes/';
require $b . 'class-kdna-wh-units.php'; require $b . 'class-kdna-wh-countries.php';
require $b . 'class-kdna-wh-bands.php'; require $b . 'class-kdna-wh-lookup.php';

printf( "%d zones, %d postcodes loaded\n\n", count( $Z ), count( $P ) );
printf( "%-8s %-12s %-9s %s\n", 'POSTCODE', 'STATE', 'FIGURE', 'WHY' );
foreach ( array( '3220' => 'Geelong', '3216' => 'Highton', '3213' => 'Batesford/Lovely Banks',
	'3331' => 'Bannockburn', '3328' => 'Teesdale', '3250' => 'Colac', '3228' => 'Torquay',
	'6043' => 'Two Rocks', '3000' => 'Melbourne', '5086' => 'Adelaide north', '9999' => 'nowhere' ) as $pc => $where ) {
	$r = KDNA_WH_Lookup::lookup( 'AU', (string) $pc );
	$fig = 'invalid' === $r['state'] || 'no_match' === $r['state'] ? '-'
		: ( $r['range_display'] ?: $r['value_display'] . ' ' . $r['unit_label'] );
	printf( "%-8s %-12s %-9s %s\n", $pc, $r['state'], $fig,
		$r['band_label'] ?: ( $r['zone_count'] > 1 ? $r['zone_count'] . ' zones, ' . $where : $where ) );
}
