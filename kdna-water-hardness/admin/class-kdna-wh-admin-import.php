<?php
/**
 * Data Import screen controller.
 *
 * Handles every form submission on the Data Import page: uploading a CSV,
 * confirming the column mapping, running the import, managing source links,
 * and deleting data. Every one checks a nonce and the user's capability
 * before touching anything.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Admin_Import
 */
class KDNA_WH_Admin_Import {

	/**
	 * Registers the form handlers and the progress page refresh.
	 *
	 * @return void
	 */
	public static function init() {
		$actions = array(
			'kdna_wh_upload'       => 'handle_upload',
			'kdna_wh_map'          => 'handle_map',
			'kdna_wh_run'          => 'handle_run',
			'kdna_wh_cancel'       => 'handle_cancel',
			'kdna_wh_save_link'    => 'handle_save_link',
			'kdna_wh_delete_link'  => 'handle_delete_link',
			'kdna_wh_save_country' => 'handle_save_country',
			'kdna_wh_delete_data'  => 'handle_delete_data',
			'kdna_wh_delete_zones' => 'handle_delete_zones',
			'kdna_wh_clear_api_cache' => 'handle_clear_api_cache',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'admin_post_' . $action, array( __CLASS__, $method ) );
		}

		add_action( 'admin_head', array( __CLASS__, 'print_progress_refresh' ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Shared helpers
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Confirms the request is a genuine submission from a permitted user.
	 * Every handler calls this first.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( KDNA_WH_Admin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage water hardness data.', 'kdna-water-hardness' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Stores a message to show on the next page load. Held against the user
	 * rather than passed through the URL, so the text cannot be rewritten by
	 * editing the address bar.
	 *
	 * @param string $type    success, error or warning.
	 * @param string $message Text to display.
	 * @return void
	 */
	public static function add_notice( $type, $message ) {
		set_transient(
			'kdna_wh_notice_' . get_current_user_id(),
			array(
				'type'    => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
				'message' => (string) $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Reads and clears the stored message.
	 *
	 * @return array|null
	 */
	public static function get_notice() {
		$key    = 'kdna_wh_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! $notice ) {
			return null;
		}

		delete_transient( $key );

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Builds a URL back to the Data Import page.
	 *
	 * @param array $args Query arguments to add.
	 * @return string
	 */
	public static function page_url( array $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => KDNA_WH_Admin::MENU_SLUG . '-import' ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Sends the user back to the Data Import page.
	 *
	 * @param array $args Query arguments.
	 * @return void
	 */
	private static function redirect( array $args = array() ) {
		wp_safe_redirect( self::page_url( $args ) );
		exit;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Import
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Step one: receive the file and read its header.
	 *
	 * @return void
	 */
	public static function handle_upload() {
		self::guard( 'kdna_wh_upload' );

		$type    = isset( $_POST['csv_type'] ) ? sanitize_key( wp_unslash( $_POST['csv_type'] ) ) : 'zones';
		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';

		// A country typed into the "add another country" box wins over the
		// dropdown, which sits at its placeholder value in that case.
		if ( isset( $_POST['new_country'] ) && '' !== trim( (string) wp_unslash( $_POST['new_country'] ) ) ) {
			$country = sanitize_text_field( wp_unslash( $_POST['new_country'] ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed to wp_handle_upload, which validates it.
		$file = isset( $_FILES['csv_file'] ) ? $_FILES['csv_file'] : array();

		$job = KDNA_WH_Importer::create_job( $file, $type, $country );

		if ( is_wp_error( $job ) ) {
			self::add_notice( 'error', $job->get_error_message() );
			self::redirect();
		}

		self::redirect(
			array(
				'step'  => 'map',
				'token' => $job['token'],
			)
		);
	}

	/**
	 * Step two: store the confirmed mapping and begin.
	 *
	 * @return void
	 */
	public static function handle_map() {
		self::guard( 'kdna_wh_map' );

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		$mapping = array();

		if ( isset( $_POST['mapping'] ) && is_array( $_POST['mapping'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is cast to an integer below.
			foreach ( wp_unslash( $_POST['mapping'] ) as $field => $index ) {
				$mapping[ sanitize_key( $field ) ] = (int) $index;
			}
		}

		$options = array(
			'unit'                 => isset( $_POST['unit'] ) ? sanitize_key( wp_unslash( $_POST['unit'] ) ) : KDNA_WH_Units::CANONICAL,
			'default_confidence'   => isset( $_POST['default_confidence'] ) ? sanitize_key( wp_unslash( $_POST['default_confidence'] ) ) : 'estimated',
			'allow_missing_source' => ! empty( $_POST['allow_missing_source'] ),
			'replace'              => ! empty( $_POST['replace'] ),
		);

		$job = KDNA_WH_Importer::start( $token, $mapping, $options );

		if ( is_wp_error( $job ) ) {
			self::add_notice( 'error', $job->get_error_message() );
			self::redirect(
				array(
					'step'  => 'map',
					'token' => $token,
				)
			);
		}

		self::redirect(
			array(
				'step'  => 'run',
				'token' => $job['token'],
			)
		);
	}

	/**
	 * Step three: process one block of rows, then return to the progress page.
	 *
	 * The progress page sends the browser straight back here for the next
	 * block. Going back to a real page between blocks, rather than redirecting
	 * from handler to handler, keeps a large file well clear of the browser's
	 * limit on consecutive redirects.
	 *
	 * @return void
	 */
	public static function handle_run() {
		self::guard( 'kdna_wh_run' );

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$job   = KDNA_WH_Importer::process_chunk( $token );

		if ( is_wp_error( $job ) ) {
			self::add_notice( 'error', $job->get_error_message() );
			self::redirect();
		}

		self::redirect(
			array(
				'step'  => 'done' === $job['stage'] ? 'report' : 'run',
				'token' => $token,
			)
		);
	}

	/**
	 * Abandons an import and deletes the uploaded file.
	 *
	 * @return void
	 */
	public static function handle_cancel() {
		self::guard( 'kdna_wh_cancel' );

		$token = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';

		KDNA_WH_Importer::delete_job( $token );
		self::add_notice( 'info', __( 'Import cancelled and the uploaded file removed.', 'kdna-water-hardness' ) );
		self::redirect();
	}

	/**
	 * Sends the browser on to the next block of rows while an import is
	 * running. A refresh rather than a script, so it works regardless of the
	 * admin's JavaScript state, and shows honest progress between blocks.
	 *
	 * @return void
	 */
	public static function print_progress_refresh() {
		if ( ! isset( $_GET['page'], $_GET['step'], $_GET['token'] ) ) {
			return;
		}

		if ( KDNA_WH_Admin::MENU_SLUG . '-import' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( 'run' !== sanitize_key( wp_unslash( $_GET['step'] ) ) ) {
			return;
		}

		if ( ! current_user_can( KDNA_WH_Admin::CAPABILITY ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		$job   = KDNA_WH_Importer::get_job( $token );

		if ( ! $job || 'run' !== $job['stage'] ) {
			return;
		}

		printf(
			'<meta http-equiv="refresh" content="0;url=%s">',
			esc_url( self::run_url( $token ) )
		);
	}

	/**
	 * The URL that processes the next block of rows.
	 *
	 * @param string $token Job token.
	 * @return string
	 */
	public static function run_url( $token ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'kdna_wh_run',
					'token'  => $token,
				),
				admin_url( 'admin-post.php' )
			),
			'kdna_wh_run'
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Source links and country configuration
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Adds or updates a source link.
	 *
	 * @return void
	 */
	public static function handle_save_link() {
		self::guard( 'kdna_wh_save_link' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';

		$result = KDNA_WH_Sources::save_link(
			$country,
			array(
				'id'           => isset( $_POST['link_id'] ) ? sanitize_text_field( wp_unslash( $_POST['link_id'] ) ) : '',
				'label'        => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
				'url'          => isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '',
				'region'       => isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '',
				'last_checked' => isset( $_POST['last_checked'] ) ? sanitize_text_field( wp_unslash( $_POST['last_checked'] ) ) : '',
				'data_date'    => isset( $_POST['data_date'] ) ? sanitize_text_field( wp_unslash( $_POST['data_date'] ) ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			self::add_notice( 'error', $result->get_error_message() );
		} else {
			self::add_notice( 'success', __( 'Source link saved.', 'kdna-water-hardness' ) );
		}

		self::redirect(
			array(
				'tab'     => 'countries',
				'country' => KDNA_WH_DB::normalise_country( $country ),
			)
		);
	}

	/**
	 * Removes a source link.
	 *
	 * @return void
	 */
	public static function handle_delete_link() {
		self::guard( 'kdna_wh_delete_link' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$link_id = isset( $_POST['link_id'] ) ? sanitize_text_field( wp_unslash( $_POST['link_id'] ) ) : '';

		if ( KDNA_WH_Sources::delete_link( $country, $link_id ) ) {
			self::add_notice( 'success', __( 'Source link removed.', 'kdna-water-hardness' ) );
		} else {
			self::add_notice( 'error', __( 'That source link could not be found.', 'kdna-water-hardness' ) );
		}

		self::redirect(
			array(
				'tab'     => 'countries',
				'country' => KDNA_WH_DB::normalise_country( $country ),
			)
		);
	}

	/**
	 * Saves a country's source type.
	 *
	 * @return void
	 */
	public static function handle_save_country() {
		self::guard( 'kdna_wh_save_country' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$type    = isset( $_POST['source_type'] ) ? sanitize_key( wp_unslash( $_POST['source_type'] ) ) : 'csv';

		KDNA_WH_Sources::set_source_type( $country, $type );

		// Entered in days, because nobody thinks in seconds.
		$days = isset( $_POST['api_ttl_days'] ) ? absint( wp_unslash( $_POST['api_ttl_days'] ) ) : 30;

		KDNA_WH_Sources::save_api_settings(
			$country,
			array(
				'api_endpoint'   => isset( $_POST['api_endpoint'] ) ? sanitize_text_field( wp_unslash( $_POST['api_endpoint'] ) ) : '',
				'api_key'        => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
				'api_adapter'    => isset( $_POST['api_adapter'] ) ? sanitize_key( wp_unslash( $_POST['api_adapter'] ) ) : 'json',
				'api_ttl'        => max( 1, $days ) * DAY_IN_SECONDS,
				'api_confidence' => isset( $_POST['api_confidence'] ) ? sanitize_key( wp_unslash( $_POST['api_confidence'] ) ) : 'verified',
			)
		);

		/*
		 * A configuration change makes any recorded failure historic, and the
		 * pause that came with it should not outlive the fix. Cached answers go
		 * too: they were fetched under the old settings.
		 */
		KDNA_WH_Sources::clear_api_error( $country );
		KDNA_WH_Source_API::clear_cache( $country );

		self::add_notice( 'success', __( 'Country settings saved.', 'kdna-water-hardness' ) );

		self::redirect(
			array(
				'tab'     => 'countries',
				'country' => KDNA_WH_DB::normalise_country( $country ),
			)
		);
	}

	/**
	 * Empties a country's cached API answers, and lifts the pause that follows
	 * a provider failure.
	 *
	 * @return void
	 */
	public static function handle_clear_api_cache() {
		self::guard( 'kdna_wh_clear_api_cache' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$removed = KDNA_WH_Source_API::clear_cache( $country );

		KDNA_WH_Sources::clear_api_error( $country );

		self::add_notice(
			'success',
			sprintf(
				/* translators: %s: number of cached answers. */
				_n( '%s cached answer cleared.', '%s cached answers cleared.', $removed, 'kdna-water-hardness' ),
				number_format_i18n( $removed )
			)
		);

		self::redirect(
			array(
				'tab'     => 'countries',
				'country' => KDNA_WH_DB::normalise_country( $country ),
			)
		);
	}

	/*
	 * -----------------------------------------------------------------------
	 * Deleting data
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Deletes a country's zones or postcode mappings.
	 *
	 * @return void
	 */
	public static function handle_delete_data() {
		self::guard( 'kdna_wh_delete_data' );

		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$scope   = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
		$code    = KDNA_WH_DB::normalise_country( $country );

		if ( ! $code ) {
			self::add_notice( 'error', __( 'No country was selected.', 'kdna-water-hardness' ) );
			self::redirect( array( 'tab' => 'browse' ) );
		}

		// Whatever is removed, the answers change.
		KDNA_WH_Lookup::bump_cache();

		if ( 'postcodes' === $scope ) {
			$removed = KDNA_WH_DB::delete_postcodes_by_country( $code );

			self::add_notice(
				'success',
				sprintf(
					/* translators: 1: number of mappings, 2: country code. */
					_n( '%1$s postcode mapping deleted for %2$s.', '%1$s postcode mappings deleted for %2$s.', $removed, 'kdna-water-hardness' ),
					number_format_i18n( $removed ),
					$code
				)
			);
		} else {
			// Zones and their mappings go together: a mapping pointing at a
			// deleted zone would match nothing.
			$removed = KDNA_WH_DB::delete_zones_by_country( $code );

			self::add_notice(
				'success',
				sprintf(
					/* translators: 1: number of zones, 2: country code. */
					_n( '%1$s zone deleted for %2$s, along with its postcode mappings.', '%1$s zones deleted for %2$s, along with their postcode mappings.', $removed, 'kdna-water-hardness' ),
					number_format_i18n( $removed ),
					$code
				)
			);
		}

		self::redirect(
			array(
				'tab'     => 'browse',
				'country' => $code,
			)
		);
	}

	/**
	 * Deletes the zones ticked in the data browser.
	 *
	 * @return void
	 */
	public static function handle_delete_zones() {
		self::guard( 'kdna_wh_delete_zones' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is cast to an integer below.
		$ids     = isset( $_POST['zone_ids'] ) ? (array) wp_unslash( $_POST['zone_ids'] ) : array();
		$ids     = array_map( 'absint', $ids );
		$country = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';

		if ( ! array_filter( $ids ) ) {
			self::add_notice( 'warning', __( 'No zones were selected.', 'kdna-water-hardness' ) );
		} else {
			$removed = KDNA_WH_DB::delete_zones( $ids );

			KDNA_WH_Lookup::bump_cache();

			self::add_notice(
				'success',
				sprintf(
					/* translators: %s: number of zones. */
					_n( '%s zone deleted, along with its postcode mappings.', '%s zones deleted, along with their postcode mappings.', $removed, 'kdna-water-hardness' ),
					number_format_i18n( $removed )
				)
			);
		}

		self::redirect(
			array(
				'tab'     => 'browse',
				'country' => KDNA_WH_DB::normalise_country( $country ),
			)
		);
	}
}
