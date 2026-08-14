<?php
/**
 * The contract every data source implements.
 *
 * A source answers one question: given a normalised postcode, which supply
 * zones does it fall in and what is the hardness of each? Where the answer
 * comes from, a local table or a remote provider, is the adapter's business
 * and nothing above it needs to know.
 *
 * Zones are returned in exactly the shape the database returns them, so the
 * lookup, the bands and the front end are all unchanged by swapping a source.
 * That is the whole point of the layer.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interface KDNA_WH_Source
 */
interface KDNA_WH_Source {

	/**
	 * The country this source answers for.
	 *
	 * @return string ISO 3166-1 alpha-2.
	 */
	public function get_country();

	/**
	 * A short name for the admin, e.g. "Imported CSV" or a provider's name.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether this source is configured well enough to be used at all.
	 *
	 * A source that answers false is skipped rather than tried and failed, so
	 * a half-configured API never costs a visitor a timeout.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * The supply zones a postcode falls in.
	 *
	 * Never returns an error. A source that cannot answer returns an empty
	 * array, or whatever it can fall back to, because a visitor must never see
	 * a failure that belongs to a third party.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return array List of zone rows: zone_name, utility_name,
	 *               hardness_caco3, confidence, source_url, source_date.
	 */
	public function get_zones( $postcode );
}
