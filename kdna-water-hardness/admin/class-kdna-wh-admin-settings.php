<?php
/**
 * Settings screen controller.
 *
 * Saves the classification bands and the copy attached to them, per country.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Admin_Settings
 */
class KDNA_WH_Admin_Settings {

	/**
	 * Registers the form handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_kdna_wh_save_bands', array( __CLASS__, 'handle_save_bands' ) );
		add_action( 'admin_post_kdna_wh_reset_bands', array( __CLASS__, 'handle_reset_bands' ) );
		add_action( 'admin_post_kdna_wh_save_settings', array( __CLASS__, 'handle_save_settings' ) );
	}

	/**
	 * Confirms the request is genuine and the user is allowed to make it.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( KDNA_WH_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'kdna-water-hardness' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Sends the user back to the Settings page.
	 *
	 * @param array $args Query arguments.
	 * @return void
	 */
	private static function redirect( array $args = array() ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge( array( 'page' => KDNA_WH_Admin::MENU_SLUG ), $args ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Saves one country's bands and copy.
	 *
	 * @return void
	 */
	public static function handle_save_bands() {
		self::guard( 'kdna_wh_save_bands' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$code    = KDNA_WH_DB::normalise_country( $country );

		if ( ! $code ) {
			KDNA_WH_Admin_Import::add_notice( 'error', __( 'No country was selected.', 'kdna-water-hardness' ) );
			self::redirect();
		}

		/*
		 * Both arrays are cleaned field by field inside save_country(): text
		 * fields are stripped of markup, the body allows only what a post
		 * allows, thresholds become numbers and colours must be valid hex.
		 * Unslashing here is all that is needed before that.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field in save_country().
		$bands = isset( $_POST['bands'] ) && is_array( $_POST['bands'] ) ? wp_unslash( $_POST['bands'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field in save_country().
		$copy = isset( $_POST['copy'] ) && is_array( $_POST['copy'] ) ? wp_unslash( $_POST['copy'] ) : array();

		$warning = self::threshold_warning( $bands );

		KDNA_WH_Bands::save_country( $code, $bands, $copy );

		if ( $warning ) {
			KDNA_WH_Admin_Import::add_notice( 'warning', $warning );
		} else {
			KDNA_WH_Admin_Import::add_notice( 'success', __( 'Bands and copy saved.', 'kdna-water-hardness' ) );
		}

		self::redirect( array( 'country' => $code ) );
	}

	/**
	 * Checks the thresholds make a sensible ladder, and describes the problem
	 * if they do not.
	 *
	 * The values are still saved. Refusing the whole form over one number
	 * would lose the copy typed alongside it, and the scale copes with a badly
	 * ordered set by sorting it. This is a warning, not a rejection.
	 *
	 * @param array $bands Submitted band values.
	 * @return string Empty when the thresholds are fine.
	 */
	private static function threshold_warning( array $bands ) {
		$previous = null;
		$previous_label = '';

		foreach ( KDNA_WH_Bands::default_bands() as $key => $default ) {
			$input = isset( $bands[ $key ] ) && is_array( $bands[ $key ] ) ? $bands[ $key ] : array();

			if ( empty( $input['enabled'] ) && 'soft' !== $key ) {
				continue;
			}

			$min   = isset( $input['min'] ) ? (float) $input['min'] : (float) $default['min'];
			$label = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : $default['label'];

			if ( null !== $previous && $min <= $previous ) {
				return sprintf(
					/* translators: 1: band label, 2: the band before it. */
					__( 'Saved, but check your thresholds: %1$s starts at or below %2$s, so the bands overlap. Each band should start at a higher figure than the one before it.', 'kdna-water-hardness' ),
					$label,
					$previous_label
				);
			}

			$previous       = $min;
			$previous_label = $label;
		}

		return '';
	}

	/**
	 * Puts a country back to the default bands and copy.
	 *
	 * @return void
	 */
	public static function handle_reset_bands() {
		self::guard( 'kdna_wh_reset_bands' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$code    = KDNA_WH_DB::normalise_country( $country );

		KDNA_WH_Bands::reset_country( $code );
		KDNA_WH_Admin_Import::add_notice( 'success', __( 'Bands and copy reset to the defaults.', 'kdna-water-hardness' ) );

		self::redirect( array( 'country' => $code ) );
	}

	/**
	 * Saves the settings that apply to every country.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		self::guard( 'kdna_wh_save_settings' );

		KDNA_WH_Bands::save_settings(
			array(
				'stale_years'        => isset( $_POST['stale_years'] ) ? absint( wp_unslash( $_POST['stale_years'] ) ) : 3,
				'inconclusive_stale' => ! empty( $_POST['inconclusive_stale'] ),
			)
		);

		KDNA_WH_Admin_Import::add_notice( 'success', __( 'Settings saved.', 'kdna-water-hardness' ) );

		self::redirect( array( 'tab' => 'advanced' ) );
	}
}
