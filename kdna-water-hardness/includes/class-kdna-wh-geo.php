<?php
/**
 * Country detection, and keeping the geolocation database current.
 *
 * Resolution order, cheapest and most reliable first:
 *
 *   1. Cloudflare's CF-IPCountry header. Free, no external call, no latency,
 *      and no dependency. If the site is behind Cloudflare nothing else runs.
 *   2. A MaxMind GeoLite2 Country lookup against a local database file.
 *   3. Australia.
 *
 * And if the country it lands on holds no data, Australia again, because a
 * correctly detected country with an empty dropdown is worse than a wrong one
 * that works.
 *
 * None of this is ever more than a convenience. Mobile traffic resolves to
 * whichever state the carrier's gateway sits in, and a VPN resolves wherever
 * it exits, so the selector always stays changeable by hand.
 *
 * No IP address is stored anywhere, at any point.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Geo
 */
class KDNA_WH_Geo {

	/**
	 * Option holding the MaxMind credentials and the update history.
	 */
	const OPTION = 'kdna_wh_geo';

	/**
	 * The cron hook that refreshes the database.
	 */
	const CRON_HOOK = 'kdna_wh_update_geoip';

	/**
	 * Custom schedule name. WordPress ships nothing longer than weekly.
	 */
	const SCHEDULE = 'kdna_wh_monthly';

	/**
	 * The file the database is kept as.
	 */
	const DB_FILENAME = 'GeoLite2-Country.mmdb';

	/**
	 * Registers the cron schedule and its handler.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- adding a longer interval, not a shorter one.
		add_action( self::CRON_HOOK, array( __CLASS__, 'update_database' ) );
	}

	/**
	 * Adds a monthly interval.
	 *
	 * Hardness data changes annually at most and the country database only has
	 * to be roughly current, so monthly is generous. MaxMind publish more
	 * often than that, and nothing breaks by being a few weeks behind.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function register_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( 'Once a month', 'kdna-water-hardness' ),
		);

		return $schedules;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Settings
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Reads the stored settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION, array() );

		return array_merge(
			array(
				'enabled'      => true,
				'account_id'   => '',
				'licence_key'  => '',
				'last_updated' => '',
				'last_error'   => '',
				'last_attempt' => '',
				'build_epoch'  => 0,
			),
			is_array( $settings ) ? $settings : array()
		);
	}

	/**
	 * Writes the settings back.
	 *
	 * @param array $settings Settings to merge in.
	 * @return bool
	 */
	public static function save_settings( array $settings ) {
		return update_option( self::OPTION, array_merge( self::get_settings(), $settings ), false );
	}

	/**
	 * Whether country pre-selection is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = self::get_settings();

		/**
		 * Filters whether country detection runs at all.
		 *
		 * Worth switching off on a site with full page caching, where the
		 * first visitor's country would be baked into the cached page for
		 * everyone after them.
		 *
		 * @param bool $enabled Whether detection is enabled.
		 */
		return (bool) apply_filters( 'kdna_wh_geolocation_enabled', ! empty( $settings['enabled'] ) );
	}

	/*
	 * -----------------------------------------------------------------------
	 * Detection
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Works out which country to pre-select.
	 *
	 * @return string ISO country code. Never empty unless nothing has data.
	 */
	public static function detect_country() {
		$fallback = KDNA_WH_Countries::default_country();

		if ( ! self::is_enabled() ) {
			return $fallback;
		}

		$detected = self::detect_raw();

		if ( ! $detected ) {
			return $fallback;
		}

		/*
		 * A country we hold no data for would show an empty tool, so it falls
		 * back exactly as an undetected visitor would. default_country()
		 * already refuses a country with nothing behind it.
		 */
		return KDNA_WH_Countries::default_country( $detected );
	}

	/**
	 * The detected country before it is checked against the data. Separated so
	 * the admin can report what detection actually saw.
	 *
	 * @return string ISO country code, or an empty string.
	 */
	public static function detect_raw() {
		$country = self::from_cloudflare();

		if ( $country ) {
			return $country;
		}

		return self::from_maxmind();
	}

	/**
	 * Reads Cloudflare's country header.
	 *
	 * @return string ISO country code, or an empty string.
	 */
	public static function from_cloudflare() {
		if ( empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			return '';
		}

		$code = KDNA_WH_DB::normalise_country( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );

		/*
		 * Cloudflare uses XX when it cannot tell and T1 for traffic arriving
		 * over Tor. Neither is a country.
		 */
		if ( in_array( $code, array( 'XX', 'T1' ), true ) ) {
			return '';
		}

		return $code;
	}

	/**
	 * Looks the visitor's address up in the local MaxMind database.
	 *
	 * @return string ISO country code, or an empty string.
	 */
	public static function from_maxmind() {
		$path = self::database_path();

		if ( ! file_exists( $path ) ) {
			return '';
		}

		$ip = self::client_ip();

		if ( ! $ip ) {
			return '';
		}

		if ( ! class_exists( 'KDNA_WH_MMDB' ) ) {
			require_once KDNA_WH_PATH . 'includes/class-kdna-wh-mmdb.php';
		}

		$reader = KDNA_WH_MMDB::open( $path );

		if ( is_wp_error( $reader ) ) {
			return '';
		}

		$country = $reader->country( $ip );
		$reader->close();

		return KDNA_WH_DB::normalise_country( $country );
	}

	/**
	 * The visitor's IP address, used for the lookup and then discarded.
	 *
	 * Proxy headers can be forged. That is acceptable here and nowhere else in
	 * the plugin: the worst a forged header achieves is pre-selecting a
	 * different country in a dropdown the visitor can change anyway. It is
	 * never used for access control, and never stored.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$candidates = array();

		// Cloudflare's own header for the real client address.
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts     = explode( ',', $forwarded );
			// The left-most entry is the original client.
			$candidates[] = trim( $parts[0] );
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/**
		 * Filters the candidate client addresses, in order of preference.
		 *
		 * @param array $candidates Candidate IP addresses.
		 */
		$candidates = apply_filters( 'kdna_wh_client_ip_candidates', $candidates );

		foreach ( $candidates as $candidate ) {
			$ip = trim( (string) $candidate );

			// A private or reserved address tells us nothing about location,
			// so it is skipped in favour of the next candidate.
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $ip;
			}
		}

		return '';
	}

	/*
	 * -----------------------------------------------------------------------
	 * The database file
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Where the database is kept.
	 *
	 * @return string Absolute path.
	 */
	public static function database_path() {
		return self::database_dir() . '/' . self::DB_FILENAME;
	}

	/**
	 * The folder the database lives in, created and protected on first use.
	 *
	 * @return string Absolute path with no trailing slash.
	 */
	public static function database_dir() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'kdna-wh-geo';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" );
		}

		$index = $dir . '/index.html';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
			@file_put_contents( $index, '' );
		}

		return $dir;
	}

	/**
	 * Whether a usable database is installed.
	 *
	 * @return bool
	 */
	public static function has_database() {
		$path = self::database_path();

		return file_exists( $path ) && filesize( $path ) > 512;
	}

	/**
	 * Downloads the current GeoLite2 Country database and installs it.
	 *
	 * The download goes to a temporary file and is opened and read before it
	 * replaces anything. A half-finished download or an error page served with
	 * a 200 must never overwrite a database that currently works.
	 *
	 * @return true|WP_Error
	 */
	public static function update_database() {
		$settings = self::get_settings();

		self::save_settings( array( 'last_attempt' => current_time( 'mysql' ) ) );

		if ( '' === trim( (string) $settings['licence_key'] ) ) {
			return self::fail( __( 'No MaxMind licence key has been entered, so there is nothing to download.', 'kdna-water-hardness' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$response = wp_remote_get(
			self::download_url( $settings ),
			array_merge(
				array(
					'timeout'  => 120,
					'stream'   => true,
					'filename' => self::database_dir() . '/download.tar.gz',
				),
				self::request_auth( $settings )
			)
		);

		$archive = self::database_dir() . '/download.tar.gz';

		if ( is_wp_error( $response ) ) {
			self::cleanup( $archive );
			return self::fail( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			self::cleanup( $archive );

			if ( 401 === $code || 403 === $code ) {
				return self::fail( __( 'MaxMind rejected the credentials. Check the account ID and licence key.', 'kdna-water-hardness' ) );
			}

			return self::fail(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'MaxMind returned an unexpected response, HTTP %d.', 'kdna-water-hardness' ),
					$code
				)
			);
		}

		$extracted = self::extract_database( $archive );
		self::cleanup( $archive );

		if ( is_wp_error( $extracted ) ) {
			return self::fail( $extracted->get_error_message() );
		}

		// Prove the file works before it becomes the live one.
		if ( ! class_exists( 'KDNA_WH_MMDB' ) ) {
			require_once KDNA_WH_PATH . 'includes/class-kdna-wh-mmdb.php';
		}

		$reader = KDNA_WH_MMDB::open( $extracted );

		if ( is_wp_error( $reader ) ) {
			self::cleanup( $extracted );
			return self::fail( $reader->get_error_message() );
		}

		$metadata = $reader->metadata();
		$reader->close();

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed rename is reported below.
		if ( ! @rename( $extracted, self::database_path() ) ) {
			self::cleanup( $extracted );
			return self::fail( __( 'The database was downloaded but could not be moved into place. Check the uploads folder is writable.', 'kdna-water-hardness' ) );
		}

		self::save_settings(
			array(
				'last_updated' => current_time( 'mysql' ),
				'last_error'   => '',
				'build_epoch'  => isset( $metadata['build_epoch'] ) ? (int) $metadata['build_epoch'] : 0,
			)
		);

		return true;
	}

	/**
	 * Which URL to download from.
	 *
	 * MaxMind's newer endpoint authenticates with an account ID and key over
	 * HTTP basic auth. The older one takes the key as a query parameter and no
	 * account ID. Both are supported so an existing key keeps working.
	 *
	 * @param array $settings Stored settings.
	 * @return string
	 */
	private static function download_url( array $settings ) {
		if ( '' !== trim( (string) $settings['account_id'] ) ) {
			return 'https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz';
		}

		return add_query_arg(
			array(
				'edition_id'  => 'GeoLite2-Country',
				'license_key' => rawurlencode( trim( (string) $settings['licence_key'] ) ),
				'suffix'      => 'tar.gz',
			),
			'https://download.maxmind.com/app/geoip_download'
		);
	}

	/**
	 * The authorisation header, when an account ID is in use.
	 *
	 * @param array $settings Stored settings.
	 * @return array
	 */
	private static function request_auth( array $settings ) {
		if ( '' === trim( (string) $settings['account_id'] ) ) {
			return array();
		}

		return array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( trim( $settings['account_id'] ) . ':' . trim( $settings['licence_key'] ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP basic auth requires this encoding.
			),
		);
	}

	/**
	 * Pulls the .mmdb file out of MaxMind's tar.gz.
	 *
	 * The archive is read as a gzip stream and the tar walked by hand. PharData
	 * would do this in one line, but Phar is disabled on enough shared hosts
	 * that relying on it would make this feature unavailable exactly where it
	 * is hardest to debug.
	 *
	 * @param string $archive Path to the downloaded archive.
	 * @return string|WP_Error Path to the extracted database.
	 */
	private static function extract_database( $archive ) {
		if ( ! function_exists( 'gzopen' ) ) {
			return new WP_Error( 'kdna_wh_geo_nozlib', __( 'This server cannot read compressed files, so the database cannot be extracted.', 'kdna-water-hardness' ) );
		}

		$handle = gzopen( $archive, 'rb' );

		if ( ! $handle ) {
			return new WP_Error( 'kdna_wh_geo_archive', __( 'The downloaded archive could not be opened.', 'kdna-water-hardness' ) );
		}

		$destination = self::database_dir() . '/download.mmdb';
		$found       = false;

		while ( ! gzeof( $handle ) ) {
			$header = gzread( $handle, 512 );

			if ( false === $header || strlen( $header ) < 512 ) {
				break;
			}

			$name = trim( substr( $header, 0, 100 ), " \0" );

			// Two blocks of nulls mark the end of the archive.
			if ( '' === $name ) {
				break;
			}

			/*
			 * The size is a null or space padded octal string. Anything else
			 * means this is not a tar header, which happens when a download
			 * is truncated or the stream has fallen out of alignment. Feeding
			 * that to octdec() would emit a PHP warning on top of the problem,
			 * so it is checked rather than assumed.
			 */
			$raw_size = trim( substr( $header, 124, 12 ), " \0" );

			if ( '' === $raw_size || preg_match( '/[^0-7]/', $raw_size ) ) {
				break;
			}

			$size = (int) octdec( $raw_size );

			// Entries are padded out to a whole number of 512 byte blocks.
			$padded = ( 0 === $size % 512 ) ? $size : ( $size + ( 512 - ( $size % 512 ) ) );

			if ( '.mmdb' === strtolower( substr( $name, -5 ) ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a large binary file.
				$out = fopen( $destination, 'wb' );

				if ( ! $out ) {
					gzclose( $handle );
					return new WP_Error( 'kdna_wh_geo_write', __( 'The database could not be written. Check the uploads folder is writable.', 'kdna-water-hardness' ) );
				}

				$remaining = $size;

				while ( $remaining > 0 ) {
					$chunk = gzread( $handle, min( 262144, $remaining ) );

					if ( false === $chunk || '' === $chunk ) {
						break;
					}

					fwrite( $out, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					$remaining -= strlen( $chunk );
				}

				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

				// Step over the padding that follows the entry.
				if ( $padded > $size ) {
					gzread( $handle, $padded - $size );
				}

				$found = true;
				break;
			}

			if ( $padded > 0 ) {
				// Skip an entry we do not want, in chunks so a large one does
				// not have to be held in memory.
				$skip = $padded;

				while ( $skip > 0 ) {
					$chunk = gzread( $handle, min( 262144, $skip ) );

					if ( false === $chunk || '' === $chunk ) {
						break 2;
					}

					$skip -= strlen( $chunk );
				}
			}
		}

		gzclose( $handle );

		if ( ! $found ) {
			return new WP_Error( 'kdna_wh_geo_nodb', __( 'No database file was found inside the download.', 'kdna-water-hardness' ) );
		}

		return $destination;
	}

	/**
	 * Records a failure and returns it.
	 *
	 * @param string $message What went wrong.
	 * @return WP_Error
	 */
	private static function fail( $message ) {
		self::save_settings( array( 'last_error' => $message ) );

		return new WP_Error( 'kdna_wh_geo_failed', $message );
	}

	/**
	 * Removes a temporary file.
	 *
	 * @param string $path Absolute path.
	 * @return void
	 */
	private static function cleanup( $path ) {
		if ( $path && file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Removes the installed database.
	 *
	 * @return bool
	 */
	public static function delete_database() {
		if ( ! self::has_database() ) {
			return false;
		}

		wp_delete_file( self::database_path() );

		self::save_settings(
			array(
				'last_updated' => '',
				'build_epoch'  => 0,
			)
		);

		return true;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Scheduling
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Starts the monthly refresh.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, self::SCHEDULE, self::CRON_HOOK );
		}
	}

	/**
	 * Stops it.
	 *
	 * @return void
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * When the next refresh is due.
	 *
	 * @return int Timestamp, or zero when nothing is scheduled.
	 */
	public static function next_update() {
		return (int) wp_next_scheduled( self::CRON_HOOK );
	}
}
