<?php
/**
 * Lookup Log screen controller.
 *
 * Handles the CSV export, clearing the log, and the geolocation database
 * controls that sit on the Settings screen.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Admin_Log
 */
class KDNA_WH_Admin_Log {

	/**
	 * Registers the handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_kdna_wh_export_log', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_kdna_wh_clear_log', array( __CLASS__, 'handle_clear' ) );
		add_action( 'admin_post_kdna_wh_save_geo', array( __CLASS__, 'handle_save_geo' ) );
		add_action( 'admin_post_kdna_wh_update_geo', array( __CLASS__, 'handle_update_geo' ) );
		add_action( 'admin_post_kdna_wh_delete_geo', array( __CLASS__, 'handle_delete_geo' ) );
	}

	/**
	 * Confirms the request is genuine and permitted.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( KDNA_WH_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'kdna-water-hardness' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Builds a URL back to the log screen.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => KDNA_WH_Admin::MENU_SLUG . '-log' ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Reads the filters out of the request, for both the screen and the
	 * export, so what you download is what you were looking at.
	 *
	 * @return array
	 */
	public static function current_filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters on a list screen.
		return array(
			'country_code' => isset( $_REQUEST['country'] ) ? KDNA_WH_DB::normalise_country( wp_unslash( $_REQUEST['country'] ) ) : '',
			'band'         => isset( $_REQUEST['band'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['band'] ) ) : '',
			'search'       => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'date_from'    => isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '',
			'date_to'      => isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '',
			'orderby'      => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'lookups',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/*
	 * -----------------------------------------------------------------------
	 * Export
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Sends the log as a CSV, honouring whatever filters are applied.
	 *
	 * Written straight to the output stream rather than built in memory, so a
	 * log with a hundred thousand rows in it exports without trouble.
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::guard( 'kdna_wh_export_log' );

		$filters           = self::current_filters();
		$filters['limit']  = 0;
		$filters['offset'] = 0;

		$rows     = KDNA_WH_DB::get_lookup_aggregate( $filters );
		$filename = 'water-hardness-lookups-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- writing to the response, not the filesystem.
		$out = fopen( 'php://output', 'w' );

		// Excel reads a plain UTF-8 CSV as Windows-1252 unless it finds a byte
		// order mark, which mangles any accented zone name.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		fputcsv(
			$out,
			array(
				__( 'Country', 'kdna-water-hardness' ),
				__( 'Postcode', 'kdna-water-hardness' ),
				__( 'Band', 'kdna-water-hardness' ),
				__( 'Lookups', 'kdna-water-hardness' ),
				__( 'Average hardness (mg/L CaCO3)', 'kdna-water-hardness' ),
				__( 'First seen', 'kdna-water-hardness' ),
				__( 'Last seen', 'kdna-water-hardness' ),
			),
			',',
			'"',
			''
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['country_code'],
					// Prefixed so a spreadsheet keeps a leading zero and does
					// not turn 3000 into a number or M5V3L9 into a date.
					self::csv_text( $row['postcode'] ),
					self::band_label( $row['band'], $row['country_code'] ),
					$row['lookups'],
					null === $row['avg_hardness'] ? '' : $row['avg_hardness'],
					$row['first_seen'],
					$row['last_seen'],
				),
				',',
				'"',
				''
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Stops a spreadsheet reinterpreting a postcode, and stops a value
	 * beginning with a formula character being treated as one.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	private static function csv_text( $value ) {
		$value = (string) $value;

		// A leading =, +, - or @ makes Excel treat the cell as a formula.
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}

	/**
	 * Turns a stored band key into the label configured for that country,
	 * falling back to the state name for the lookups that never had a band.
	 *
	 * @param string $band    Stored band key or state.
	 * @param string $country ISO country code.
	 * @return string
	 */
	public static function band_label( $band, $country ) {
		if ( '' === $band ) {
			return __( 'Unknown', 'kdna-water-hardness' );
		}

		$states = array(
			'inconclusive' => __( 'Inconclusive', 'kdna-water-hardness' ),
			'no_match'     => __( 'No data held', 'kdna-water-hardness' ),
			'confident'    => __( 'Unbanded', 'kdna-water-hardness' ),
			'range'        => __( 'Unbanded', 'kdna-water-hardness' ),
		);

		if ( isset( $states[ $band ] ) ) {
			return $states[ $band ];
		}

		$config = KDNA_WH_Bands::get_country( $country );

		return isset( $config['bands'][ $band ]['label'] ) ? $config['bands'][ $band ]['label'] : $band;
	}

	/**
	 * Empties the log.
	 *
	 * @return void
	 */
	public static function handle_clear() {
		self::guard( 'kdna_wh_clear_log' );

		$before  = isset( $_POST['before'] ) ? sanitize_text_field( wp_unslash( $_POST['before'] ) ) : '';
		$removed = KDNA_WH_DB::delete_lookups( $before );

		KDNA_WH_Admin_Import::add_notice(
			'success',
			sprintf(
				/* translators: %s: number of records. */
				_n( '%s logged lookup deleted.', '%s logged lookups deleted.', $removed, 'kdna-water-hardness' ),
				number_format_i18n( $removed )
			)
		);

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Geolocation
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Saves the geolocation settings.
	 *
	 * @return void
	 */
	public static function handle_save_geo() {
		self::guard( 'kdna_wh_save_geo' );

		$enabled = ! empty( $_POST['geo_enabled'] );
		$account = isset( $_POST['account_id'] ) ? sanitize_text_field( wp_unslash( $_POST['account_id'] ) ) : '';
		$key     = isset( $_POST['licence_key'] ) ? sanitize_text_field( wp_unslash( $_POST['licence_key'] ) ) : '';

		KDNA_WH_Geo::save_settings(
			array(
				'enabled'     => $enabled,
				'account_id'  => $account,
				'licence_key' => $key,
			)
		);

		// The monthly refresh is only worth running when there is a key to
		// run it with.
		if ( '' !== trim( $key ) ) {
			KDNA_WH_Geo::schedule();
		} else {
			KDNA_WH_Geo::unschedule();
		}

		KDNA_WH_Admin_Import::add_notice( 'success', __( 'Geolocation settings saved.', 'kdna-water-hardness' ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => KDNA_WH_Admin::MENU_SLUG,
					'tab'  => 'advanced',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Downloads the geolocation database now.
	 *
	 * @return void
	 */
	public static function handle_update_geo() {
		self::guard( 'kdna_wh_update_geo' );

		$result = KDNA_WH_Geo::update_database();

		if ( is_wp_error( $result ) ) {
			KDNA_WH_Admin_Import::add_notice( 'error', $result->get_error_message() );
		} else {
			KDNA_WH_Admin_Import::add_notice( 'success', __( 'Geolocation database updated.', 'kdna-water-hardness' ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => KDNA_WH_Admin::MENU_SLUG,
					'tab'  => 'advanced',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Removes the downloaded geolocation database.
	 *
	 * @return void
	 */
	public static function handle_delete_geo() {
		self::guard( 'kdna_wh_delete_geo' );

		KDNA_WH_Geo::delete_database();
		KDNA_WH_Admin_Import::add_notice( 'success', __( 'Geolocation database removed. Detection falls back to Cloudflare, then to the default country.', 'kdna-water-hardness' ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => KDNA_WH_Admin::MENU_SLUG,
					'tab'  => 'advanced',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
