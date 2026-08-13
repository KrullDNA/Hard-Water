<?php
/**
 * The local data source.
 *
 * Wraps the tables the CSV importer fills. This is the default everywhere, and
 * the fallback everywhere else: when an API is unreachable, out of quota or
 * simply slow, this is what answers instead.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Source_CSV
 */
class KDNA_WH_Source_CSV implements KDNA_WH_Source {

	/**
	 * ISO country code.
	 *
	 * @var string
	 */
	protected $country;

	/**
	 * Constructor.
	 *
	 * @param string $country ISO country code.
	 */
	public function __construct( $country ) {
		$this->country = KDNA_WH_DB::normalise_country( $country );
	}

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
		return __( 'Imported data', 'kdna-water-hardness' );
	}

	/**
	 * Local data is always available, even when it holds nothing for a given
	 * postcode. There is no configuration that can be missing and no call that
	 * can fail.
	 *
	 * @return bool
	 */
	public function is_available() {
		return '' !== $this->country;
	}

	/**
	 * The zones a postcode falls in, from the local tables.
	 *
	 * @param string $postcode Normalised postcode.
	 * @return array
	 */
	public function get_zones( $postcode ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		return KDNA_WH_DB::get_zones_for_postcode( $this->country, $postcode );
	}
}
