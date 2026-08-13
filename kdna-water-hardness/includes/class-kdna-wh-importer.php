<?php
/**
 * CSV import.
 *
 * This is the class that makes international expansion a data job rather than
 * a development job, so it takes source files as they come: any column order,
 * any of the five supported units, and the inconsistencies real utility
 * spreadsheets contain.
 *
 * A large file is processed across several requests. Each request reads a
 * block of rows from where the last one stopped and stores its progress, so a
 * forty thousand row postcode file does not have to finish inside one PHP
 * timeout.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_Importer
 */
class KDNA_WH_Importer {

	/**
	 * Rows handled per request. Small enough to finish well inside a default
	 * timeout, large enough that a big file does not take dozens of round
	 * trips.
	 */
	const CHUNK_SIZE = 1000;

	/**
	 * Rows per INSERT statement.
	 */
	const BATCH_SIZE = 250;

	/**
	 * How many failed rows are kept for the report. The count of the rest is
	 * still reported; only the detail is dropped, to keep the stored job from
	 * growing without limit on a file that is wrong from top to bottom.
	 */
	const MAX_ERRORS = 300;

	/**
	 * How long an unfinished import is kept before it is cleaned up.
	 */
	const JOB_LIFETIME = 12 * HOUR_IN_SECONDS;

	/**
	 * Prefix for the transient holding an import's progress.
	 */
	const JOB_PREFIX = 'kdna_wh_import_';

	/*
	 * -----------------------------------------------------------------------
	 * Field definitions
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The fields each CSV type can supply, with the header names that should
	 * map to them automatically.
	 *
	 * @param string $type zones or postcodes.
	 * @return array
	 */
	public static function fields( $type ) {
		if ( 'postcodes' === $type ) {
			return array(
				'postcode'     => array(
					'label'    => __( 'Postcode', 'kdna-water-hardness' ),
					'required' => true,
					'help'     => __( 'The postcode, ZIP or postal code. Case and spacing do not matter.', 'kdna-water-hardness' ),
					'aliases'  => array( 'postcode', 'post code', 'postal code', 'postalcode', 'zip', 'zip code', 'zipcode', 'pcode' ),
				),
				'zone_name'    => array(
					'label'    => __( 'Zone name', 'kdna-water-hardness' ),
					'required' => false,
					'help'     => __( 'Must match a zone name already imported for this country. Not needed if you map a zone ID instead.', 'kdna-water-hardness' ),
					'aliases'  => array( 'zone', 'zone name', 'supply zone', 'supply system', 'system', 'scheme', 'water supply zone' ),
				),
				'utility_name' => array(
					'label'    => __( 'Utility name', 'kdna-water-hardness' ),
					'required' => false,
					'help'     => __( 'Only needed when the same zone name is used by more than one utility.', 'kdna-water-hardness' ),
					'aliases'  => array( 'utility', 'utility name', 'water utility', 'provider', 'supplier', 'water company', 'authority' ),
				),
				'zone_id'      => array(
					'label'    => __( 'Zone ID', 'kdna-water-hardness' ),
					'required' => false,
					'help'     => __( 'The plugin\'s own zone ID, if your file already carries it. Takes precedence over the zone name.', 'kdna-water-hardness' ),
					'aliases'  => array( 'zone id', 'zone_id', 'zoneid' ),
				),
				'country_code' => array(
					'label'    => __( 'Country code', 'kdna-water-hardness' ),
					'required' => false,
					'help'     => __( 'Only needed if the file covers more than one country. Otherwise the country chosen above is used.', 'kdna-water-hardness' ),
					'aliases'  => array( 'country', 'country code', 'iso', 'iso code', 'country_code' ),
				),
			);
		}

		return array(
			'zone_name'      => array(
				'label'    => __( 'Zone name', 'kdna-water-hardness' ),
				'required' => true,
				'help'     => __( 'The named supply zone, as the utility publishes it. Shown to the visitor, so it is worth getting right.', 'kdna-water-hardness' ),
				'aliases'  => array( 'zone', 'zone name', 'supply zone', 'supply system', 'system', 'scheme', 'locality', 'area', 'water supply zone' ),
			),
			'hardness'       => array(
				'label'    => __( 'Hardness value', 'kdna-water-hardness' ),
				'required' => true,
				'help'     => __( 'The number only. The unit is set once for the whole file, below.', 'kdna-water-hardness' ),
				'aliases'  => array( 'hardness', 'total hardness', 'hardness value', 'caco3', 'mg/l', 'ppm', 'value', 'result', 'hardness as caco3', 'total hardness as caco3' ),
			),
			'utility_name'   => array(
				'label'    => __( 'Utility name', 'kdna-water-hardness' ),
				'required' => false,
				'help'     => __( 'The water authority. Displayed next to the result as a credibility signal.', 'kdna-water-hardness' ),
				'aliases'  => array( 'utility', 'utility name', 'water utility', 'provider', 'supplier', 'water company', 'authority', 'corporation' ),
			),
			'confidence'     => array(
				'label'    => __( 'Confidence', 'kdna-water-hardness' ),
				'required' => false,
				'help'     => __( 'Cells reading "verified" are treated as verified. Everything else is treated as estimated.', 'kdna-water-hardness' ),
				'aliases'  => array( 'confidence', 'verified', 'status', 'data quality', 'quality' ),
			),
			'source_url'     => array(
				'label'    => __( 'Source URL', 'kdna-water-hardness' ),
				'required' => true,
				'help'     => __( 'Link to the published report the figure came from.', 'kdna-water-hardness' ),
				'aliases'  => array( 'source', 'source url', 'url', 'link', 'reference', 'report', 'report url', 'source link' ),
			),
			'source_date'    => array(
				'label'    => __( 'Source date', 'kdna-water-hardness' ),
				'required' => true,
				'help'     => __( 'Publication date of that report. Any readable date format is accepted.', 'kdna-water-hardness' ),
				'aliases'  => array( 'date', 'source date', 'published', 'publication date', 'report date', 'data date', 'year' ),
			),
			'country_code'   => array(
				'label'    => __( 'Country code', 'kdna-water-hardness' ),
				'required' => false,
				'help'     => __( 'Only needed if the file covers more than one country. Otherwise the country chosen above is used.', 'kdna-water-hardness' ),
				'aliases'  => array( 'country', 'country code', 'iso', 'iso code', 'country_code' ),
			),
		);
	}

	/**
	 * Guesses a column for each field by matching the file's header names
	 * against the aliases above. The user can correct anything it gets wrong,
	 * but on a tidy file this means the mapping screen is already filled in.
	 *
	 * @param array  $header Column names from the file.
	 * @param string $type   zones or postcodes.
	 * @return array Field name to column index.
	 */
	public static function guess_mapping( array $header, $type ) {
		$mapping = array();
		$taken   = array();

		$normalised = array();

		foreach ( $header as $index => $name ) {
			$normalised[ $index ] = KDNA_WH_DB::name_key( $name );
		}

		foreach ( self::fields( $type ) as $field => $config ) {
			$mapping[ $field ] = -1;

			// An exact match on an alias wins.
			foreach ( $normalised as $index => $name ) {
				if ( in_array( $index, $taken, true ) ) {
					continue;
				}

				if ( in_array( $name, array_map( array( 'KDNA_WH_DB', 'name_key' ), $config['aliases'] ), true ) ) {
					$mapping[ $field ] = $index;
					$taken[]           = $index;
					continue 2;
				}
			}

			// Otherwise accept a header that contains an alias, so
			// "Total hardness (mg/L CaCO3)" still finds the hardness column.
			foreach ( $normalised as $index => $name ) {
				if ( in_array( $index, $taken, true ) || '' === $name ) {
					continue;
				}

				foreach ( $config['aliases'] as $alias ) {
					$alias_key = KDNA_WH_DB::name_key( $alias );

					if ( strlen( $alias_key ) > 2 && false !== strpos( $name, $alias_key ) ) {
						$mapping[ $field ] = $index;
						$taken[]           = $index;
						continue 3;
					}
				}
			}
		}

		return $mapping;
	}

	/*
	 * -----------------------------------------------------------------------
	 * Upload handling
	 * -----------------------------------------------------------------------
	 */

	/**
	 * The private folder uploaded CSVs are held in while an import runs.
	 *
	 * Files are given random names, kept outside the media library, and
	 * deleted as soon as the import finishes. A .htaccess is written for
	 * Apache; on nginx that file is ignored, which is why the random name and
	 * the prompt deletion matter rather than being belt and braces.
	 *
	 * @return string Absolute path, with no trailing slash.
	 */
	public static function upload_dir() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'kdna-wh-imports';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a protection file during an admin request.
			@file_put_contents( $htaccess, "Order deny,allow\nDeny from all\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$index = $dir . '/index.html';

		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- writing a protection file during an admin request.
			@file_put_contents( $index, '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return $dir;
	}

	/**
	 * Takes the uploaded file and starts an import job.
	 *
	 * @param array  $file    One entry from $_FILES.
	 * @param string $type    zones or postcodes.
	 * @param string $country ISO country code selected on the form.
	 * @return array|WP_Error The new job.
	 */
	public static function create_job( $file, $type, $country ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'kdna_wh_forbidden', __( 'You do not have permission to import data.', 'kdna-water-hardness' ) );
		}

		$type    = 'postcodes' === $type ? 'postcodes' : 'zones';
		$country = KDNA_WH_DB::normalise_country( $country );

		if ( ! $country ) {
			return new WP_Error( 'kdna_wh_no_country', __( 'Choose which country this file covers.', 'kdna-water-hardness' ) );
		}

		if ( empty( $file ) || ! isset( $file['name'] ) || '' === $file['name'] ) {
			return new WP_Error( 'kdna_wh_no_file', __( 'Choose a CSV file to upload.', 'kdna-water-hardness' ) );
		}

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'kdna_wh_upload_error', self::upload_error_message( (int) $file['error'] ) );
		}

		$extension = strtolower( (string) pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, array( 'csv', 'txt', 'tsv' ), true ) ) {
			return new WP_Error( 'kdna_wh_not_csv', __( 'That is not a CSV file. Save the spreadsheet as CSV and upload it again.', 'kdna-water-hardness' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		$handled = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'csv' => 'text/csv',
					'tsv' => 'text/tab-separated-values',
					'txt' => 'text/plain',
				),
			)
		);

		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		if ( isset( $handled['error'] ) ) {
			return new WP_Error( 'kdna_wh_upload_failed', $handled['error'] );
		}

		// Rename to something unguessable that ties the file to its job.
		$token = wp_generate_password( 24, false, false );
		$path  = self::upload_dir() . '/' . $token . '.csv';

		if ( ! @rename( $handled['file'], $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$path = $handled['file'];
		}

		$delimiter = KDNA_WH_CSV::detect_delimiter( $path );
		$header    = KDNA_WH_CSV::read_header( $path, $delimiter );

		if ( is_wp_error( $header ) ) {
			wp_delete_file( $path );
			return $header;
		}

		if ( count( $header ) < 2 ) {
			wp_delete_file( $path );
			return new WP_Error(
				'kdna_wh_one_column',
				__( 'That file has only one column. It may have been saved with a separator the plugin did not recognise. Re-save it as a standard comma separated CSV.', 'kdna-water-hardness' )
			);
		}

		$job = array(
			'token'                => $token,
			'type'                 => $type,
			'country'              => $country,
			'path'                 => $path,
			'filename'             => sanitize_file_name( $file['name'] ),
			'delimiter'            => $delimiter,
			'header'               => $header,
			'total_rows'           => KDNA_WH_CSV::count_rows( $path, $delimiter ),
			'mapping'              => self::guess_mapping( $header, $type ),
			'unit'                 => KDNA_WH_Units::CANONICAL,
			'default_confidence'   => 'estimated',
			'allow_missing_source' => false,
			'replace'              => false,
			'stage'                => 'map',
			'offset'               => 0,
			'line'                 => 2,
			'processed'            => 0,
			'imported'             => 0,
			'skipped'              => 0,
			'duplicates'           => 0,
			'error_count'          => 0,
			'errors'               => array(),
			'started_at'           => current_time( 'mysql' ),
		);

		self::save_job( $job );

		return $job;
	}

	/**
	 * Points wp_handle_upload at the plugin's private import folder instead of
	 * the media library. Registered only for the duration of one upload.
	 *
	 * @param array $dirs Upload directory parts.
	 * @return array
	 */
	public static function filter_upload_dir( $dirs ) {
		$dir = self::upload_dir();

		$dirs['path']   = $dir;
		$dirs['url']    = '';
		$dirs['subdir'] = '';

		return $dirs;
	}

	/**
	 * Turns a PHP upload error code into something a person can act on.
	 *
	 * @param int $code PHP upload error constant.
	 * @return string
	 */
	private static function upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return sprintf(
					/* translators: %s: maximum upload size, e.g. 8 MB. */
					__( 'That file is larger than this site allows, which is %s. Split it into two files, or ask your host to raise the upload limit.', 'kdna-water-hardness' ),
					size_format( wp_max_upload_size() )
				);
			case UPLOAD_ERR_PARTIAL:
				return __( 'The upload was interrupted. Try again.', 'kdna-water-hardness' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was chosen.', 'kdna-water-hardness' );
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'The server could not write the uploaded file. This is a hosting configuration problem.', 'kdna-water-hardness' );
			default:
				return __( 'The file could not be uploaded.', 'kdna-water-hardness' );
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Job storage
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Reads a job by token.
	 *
	 * @param string $token Job token.
	 * @return array|null
	 */
	public static function get_job( $token ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );

		if ( '' === $token ) {
			return null;
		}

		$job = get_transient( self::JOB_PREFIX . $token );

		return is_array( $job ) ? $job : null;
	}

	/**
	 * Writes a job back.
	 *
	 * @param array $job Job data.
	 * @return void
	 */
	public static function save_job( array $job ) {
		set_transient( self::JOB_PREFIX . $job['token'], $job, self::JOB_LIFETIME );
	}

	/**
	 * Ends a job and removes its uploaded file.
	 *
	 * @param string $token Job token.
	 * @return void
	 */
	public static function delete_job( $token ) {
		$job = self::get_job( $token );

		if ( $job && ! empty( $job['path'] ) && file_exists( $job['path'] ) ) {
			wp_delete_file( $job['path'] );
		}

		delete_transient( self::JOB_PREFIX . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token ) );
	}

	/**
	 * Deletes import files left behind by an abandoned job. Hooked to a daily
	 * cron event, because a browser closed halfway through an import would
	 * otherwise leave the file sitting in uploads indefinitely.
	 *
	 * @return void
	 */
	public static function cleanup_orphans() {
		$dir = self::upload_dir();
		$now = time();

		foreach ( (array) glob( $dir . '/*.csv' ) as $file ) {
			if ( is_file( $file ) && ( $now - filemtime( $file ) ) > DAY_IN_SECONDS ) {
				wp_delete_file( $file );
			}
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Running the import
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Stores the confirmed mapping and options, and moves the job to the
	 * running stage.
	 *
	 * @param string $token   Job token.
	 * @param array  $mapping Field name to column index.
	 * @param array  $options Unit, confidence default and the two toggles.
	 * @return array|WP_Error The updated job.
	 */
	public static function start( $token, array $mapping, array $options ) {
		$job = self::get_job( $token );

		if ( ! $job ) {
			return new WP_Error( 'kdna_wh_no_job', __( 'That import has expired. Upload the file again.', 'kdna-water-hardness' ) );
		}

		$fields  = self::fields( $job['type'] );
		$columns = count( $job['header'] );
		$clean   = array();

		foreach ( $fields as $field => $config ) {
			$index = isset( $mapping[ $field ] ) ? (int) $mapping[ $field ] : -1;

			$clean[ $field ] = ( $index >= 0 && $index < $columns ) ? $index : -1;
		}

		// Postcode files can identify the zone by name or by id, but they have
		// to do one of the two.
		if ( 'postcodes' === $job['type'] && -1 === $clean['zone_name'] && -1 === $clean['zone_id'] ) {
			return new WP_Error(
				'kdna_wh_no_zone_column',
				__( 'Map either a zone name or a zone ID column, so each postcode can be attached to a supply zone.', 'kdna-water-hardness' )
			);
		}

		foreach ( $fields as $field => $config ) {
			if ( empty( $config['required'] ) || -1 !== $clean[ $field ] ) {
				continue;
			}

			// The source columns are required unless the user has explicitly
			// accepted unsourced rows.
			$is_source = in_array( $field, array( 'source_url', 'source_date' ), true );

			if ( $is_source && ! empty( $options['allow_missing_source'] ) ) {
				continue;
			}

			return new WP_Error(
				'kdna_wh_missing_mapping',
				sprintf(
					/* translators: %s: field label, e.g. Zone name. */
					__( 'Choose which column holds the %s.', 'kdna-water-hardness' ),
					$config['label']
				)
			);
		}

		$unit = isset( $options['unit'] ) ? $options['unit'] : KDNA_WH_Units::CANONICAL;

		$job['mapping']              = $clean;
		$job['unit']                 = KDNA_WH_Units::is_valid( $unit ) ? $unit : KDNA_WH_Units::CANONICAL;
		$job['default_confidence']   = ( isset( $options['default_confidence'] ) && 'verified' === $options['default_confidence'] ) ? 'verified' : 'estimated';
		$job['allow_missing_source'] = ! empty( $options['allow_missing_source'] );
		$job['replace']              = ! empty( $options['replace'] );
		$job['stage']                = 'run';

		// Replacing means starting from a clean slate for that country.
		if ( $job['replace'] ) {
			if ( 'zones' === $job['type'] ) {
				// Removing zones also removes their postcode mappings, since a
				// mapping to a deleted zone matches nothing.
				$job['replaced_zones']     = KDNA_WH_DB::delete_zones_by_country( $job['country'] );
				$job['replaced_postcodes'] = true;
			} else {
				$job['replaced_mappings'] = KDNA_WH_DB::delete_postcodes_by_country( $job['country'] );
			}
		}

		self::save_job( $job );

		return $job;
	}

	/**
	 * Processes the next block of rows.
	 *
	 * @param string $token Job token.
	 * @return array|WP_Error The updated job.
	 */
	public static function process_chunk( $token ) {
		$job = self::get_job( $token );

		if ( ! $job ) {
			return new WP_Error( 'kdna_wh_no_job', __( 'That import has expired. Upload the file again.', 'kdna-water-hardness' ) );
		}

		if ( 'done' === $job['stage'] ) {
			return $job;
		}

		// A job still waiting at the mapping screen has not had its columns
		// confirmed, so it must not be processed with the guessed mapping.
		if ( 'run' !== $job['stage'] ) {
			return new WP_Error( 'kdna_wh_not_started', __( 'That import has not been set up yet. Confirm the columns first.', 'kdna-water-hardness' ) );
		}

		$chunk = KDNA_WH_CSV::read_chunk( $job['path'], $job['delimiter'], $job['offset'], self::CHUNK_SIZE, $job['line'] );

		if ( is_wp_error( $chunk ) ) {
			return $chunk;
		}

		$rows = 'postcodes' === $job['type']
			? self::process_postcode_rows( $chunk['rows'], $job )
			: self::process_zone_rows( $chunk['rows'], $job );

		// Write the valid rows in batches.
		$inserted = 0;

		foreach ( array_chunk( $rows['valid'], self::BATCH_SIZE ) as $batch ) {
			$inserted += 'postcodes' === $job['type']
				? KDNA_WH_DB::insert_postcodes_bulk( $batch )
				: KDNA_WH_DB::insert_zones_bulk( $batch );
		}

		$job['offset']      = $chunk['offset'];
		$job['line']        = $chunk['line'];
		$job['processed']  += count( $chunk['rows'] );
		$job['imported']   += $inserted;
		$job['skipped']    += count( $rows['errors'] );
		$job['duplicates'] += $rows['duplicates'];
		$job['error_count'] += count( $rows['errors'] );

		foreach ( $rows['errors'] as $error ) {
			if ( count( $job['errors'] ) < self::MAX_ERRORS ) {
				$job['errors'][] = $error;
			}
		}

		if ( $chunk['eof'] ) {
			$job['stage']       = 'done';
			$job['finished_at'] = current_time( 'mysql' );

			if ( $job['imported'] > 0 ) {
				KDNA_WH_Sources::record_import( $job['country'], $job['type'], $job['imported'] );

				// New data means the cached answers are answering from the old.
				KDNA_WH_Lookup::bump_cache();
			}

			// The file has done its job, and it is customer-adjacent data
			// sitting in a web-accessible folder. Remove it now rather than
			// waiting for the cleanup cron.
			if ( ! empty( $job['path'] ) && file_exists( $job['path'] ) ) {
				wp_delete_file( $job['path'] );
			}
		}

		self::save_job( $job );

		return $job;
	}

	/**
	 * Validates and prepares a block of zone rows.
	 *
	 * @param array $rows Rows from the CSV reader.
	 * @param array $job  Current job.
	 * @return array Valid rows, errors and duplicate count.
	 */
	private static function process_zone_rows( array $rows, array $job ) {
		$valid  = array();
		$errors = array();

		foreach ( $rows as $row ) {
			$get = self::row_reader( $row['data'], $job['mapping'] );

			$country = '' !== $get( 'country_code' ) ? KDNA_WH_DB::normalise_country( $get( 'country_code' ) ) : $job['country'];

			if ( ! $country ) {
				$errors[] = self::error( $row['line'], __( 'Country code is not a recognised two letter code.', 'kdna-water-hardness' ), $get( 'country_code' ) );
				continue;
			}

			if ( $job['replace'] && $country !== $job['country'] ) {
				$errors[] = self::error(
					$row['line'],
					sprintf(
						/* translators: 1: country in the row, 2: country selected for the import. */
						__( 'Row is for %1$s but this import replaces %2$s only. Import other countries separately.', 'kdna-water-hardness' ),
						$country,
						$job['country']
					),
					$get( 'country_code' )
				);
				continue;
			}

			$zone_name = trim( $get( 'zone_name' ) );

			if ( '' === $zone_name ) {
				$errors[] = self::error( $row['line'], __( 'Zone name is empty.', 'kdna-water-hardness' ), '' );
				continue;
			}

			$hardness = self::parse_number( $get( 'hardness' ) );

			if ( is_wp_error( $hardness ) ) {
				$errors[] = self::error( $row['line'], $hardness->get_error_message(), $get( 'hardness' ) );
				continue;
			}

			$canonical = KDNA_WH_Units::to_canonical( $hardness, $job['unit'] );

			if ( $canonical < 0 ) {
				$errors[] = self::error( $row['line'], __( 'Hardness cannot be negative.', 'kdna-water-hardness' ), $get( 'hardness' ) );
				continue;
			}

			// The column is decimal(7,2), so anything at or above 100000 will
			// not store. A real reading never comes close.
			if ( $canonical >= 100000 ) {
				$errors[] = self::error(
					$row['line'],
					__( 'Hardness is impossibly high. Check the unit selected for this file.', 'kdna-water-hardness' ),
					$get( 'hardness' )
				);
				continue;
			}

			$source_url  = self::parse_url_value( $get( 'source_url' ) );
			$source_date = self::parse_date( $get( 'source_date' ) );

			$has_source = ! is_wp_error( $source_url ) && ! is_wp_error( $source_date ) && '' !== $source_url && '' !== $source_date;

			if ( ! $has_source && ! $job['allow_missing_source'] ) {
				$message = is_wp_error( $source_url ) ? $source_url->get_error_message() : '';

				if ( '' === $message ) {
					$message = is_wp_error( $source_date )
						? $source_date->get_error_message()
						: __( 'Every figure needs a source URL and a publication date.', 'kdna-water-hardness' );
				}

				$errors[] = self::error( $row['line'], $message, trim( $get( 'source_url' ) . ' ' . $get( 'source_date' ) ) );
				continue;
			}

			/*
			 * A row with no traceable source can never be presented as
			 * authoritative, whatever the confidence column says, so the
			 * confidence is forced down rather than trusted.
			 */
			if ( $has_source ) {
				$confidence = -1 !== $job['mapping']['confidence']
					? KDNA_WH_DB::normalise_confidence( $get( 'confidence' ) )
					: $job['default_confidence'];
			} else {
				$confidence = 'estimated';
			}

			$valid[] = array(
				'country_code'   => $country,
				'utility_name'   => trim( $get( 'utility_name' ) ),
				'zone_name'      => $zone_name,
				'hardness_caco3' => $canonical,
				'confidence'     => $confidence,
				'source_url'     => is_wp_error( $source_url ) ? '' : $source_url,
				'source_date'    => is_wp_error( $source_date ) ? '' : $source_date,
			);
		}

		return array(
			'valid'      => $valid,
			'errors'     => $errors,
			'duplicates' => 0,
		);
	}

	/**
	 * Validates and prepares a block of postcode mapping rows.
	 *
	 * @param array $rows Rows from the CSV reader.
	 * @param array $job  Current job.
	 * @return array Valid rows, errors and duplicate count.
	 */
	private static function process_postcode_rows( array $rows, array $job ) {
		$valid      = array();
		$errors     = array();
		$duplicates = 0;
		$parsed     = array();
		$postcodes  = array();
		$zone_ids   = array();

		// First pass: read and check everything that needs no database access.
		foreach ( $rows as $row ) {
			$get = self::row_reader( $row['data'], $job['mapping'] );

			$country = '' !== $get( 'country_code' ) ? KDNA_WH_DB::normalise_country( $get( 'country_code' ) ) : $job['country'];

			if ( ! $country ) {
				$errors[] = self::error( $row['line'], __( 'Country code is not a recognised two letter code.', 'kdna-water-hardness' ), $get( 'country_code' ) );
				continue;
			}

			if ( $job['replace'] && $country !== $job['country'] ) {
				$errors[] = self::error(
					$row['line'],
					sprintf(
						/* translators: 1: country in the row, 2: country selected for the import. */
						__( 'Row is for %1$s but this import replaces %2$s only. Import other countries separately.', 'kdna-water-hardness' ),
						$country,
						$job['country']
					),
					$get( 'country_code' )
				);
				continue;
			}

			$raw_postcode = $get( 'postcode' );
			$postcode     = KDNA_WH_DB::normalise_postcode( $raw_postcode );

			if ( '' === $postcode ) {
				$errors[] = self::error( $row['line'], __( 'Postcode is empty, or contains no letters or numbers.', 'kdna-water-hardness' ), $raw_postcode );
				continue;
			}

			$parsed[] = array(
				'line'      => $row['line'],
				'country'   => $country,
				'postcode'  => $postcode,
				'zone_id'   => -1 !== $job['mapping']['zone_id'] ? absint( $get( 'zone_id' ) ) : 0,
				'zone_name' => trim( $get( 'zone_name' ) ),
				'utility'   => trim( $get( 'utility_name' ) ),
			);

			$postcodes[] = $postcode;

			if ( -1 !== $job['mapping']['zone_id'] ) {
				$zone_ids[] = absint( $get( 'zone_id' ) );
			}
		}

		if ( ! $parsed ) {
			return array(
				'valid'      => array(),
				'errors'     => $errors,
				'duplicates' => 0,
			);
		}

		// Second pass: resolve zones and check for mappings we already hold.
		$name_map  = KDNA_WH_DB::get_zone_name_map( $job['country'] );
		$known_ids = $zone_ids ? array_flip( KDNA_WH_DB::filter_existing_zone_ids( $zone_ids ) ) : array();
		$existing  = KDNA_WH_DB::get_existing_mappings( $job['country'], $postcodes );
		$seen      = array();

		foreach ( $parsed as $item ) {
			$zone_id = 0;

			if ( $item['zone_id'] > 0 ) {
				if ( ! isset( $known_ids[ $item['zone_id'] ] ) ) {
					$errors[] = self::error(
						$item['line'],
						__( 'No zone exists with that zone ID. Import the zones file first.', 'kdna-water-hardness' ),
						(string) $item['zone_id']
					);
					continue;
				}

				$zone_id = $item['zone_id'];
			} else {
				$zone_id = self::resolve_zone_by_name( $item, $name_map, $errors );

				if ( ! $zone_id ) {
					continue;
				}
			}

			$key = $item['postcode'] . '|' . $zone_id;

			// Already in the database, or already queued earlier in this file.
			if ( isset( $existing[ $key ] ) || isset( $seen[ $key ] ) ) {
				$duplicates++;
				continue;
			}

			$seen[ $key ] = true;

			$valid[] = array(
				'country_code' => $item['country'],
				'postcode'     => $item['postcode'],
				'zone_id'      => $zone_id,
			);
		}

		return array(
			'valid'      => $valid,
			'errors'     => $errors,
			'duplicates' => $duplicates,
		);
	}

	/**
	 * Finds the zone a mapping row names, preferring a utility and zone pair
	 * when the file supplies both.
	 *
	 * @param array $item     Parsed row.
	 * @param array $name_map Zone name lookup for the country.
	 * @param array $errors   Error list, appended to by reference.
	 * @return int Zone id, or zero when it could not be resolved.
	 */
	private static function resolve_zone_by_name( array $item, array $name_map, array &$errors ) {
		if ( '' === $item['zone_name'] ) {
			$errors[] = self::error( $item['line'], __( 'Zone name is empty.', 'kdna-water-hardness' ), '' );
			return 0;
		}

		// A utility and zone pair is unambiguous, so try that first.
		if ( '' !== $item['utility'] ) {
			$pair_key = KDNA_WH_DB::name_key( $item['utility'] . '|' . $item['zone_name'] );

			if ( isset( $name_map['pairs'][ $pair_key ] ) ) {
				return (int) $name_map['pairs'][ $pair_key ];
			}
		}

		$name_key = KDNA_WH_DB::name_key( $item['zone_name'] );

		if ( isset( $name_map['ambiguous'][ $name_key ] ) ) {
			$errors[] = self::error(
				$item['line'],
				__( 'More than one zone has this name. Add a utility column to the file so the right one can be chosen.', 'kdna-water-hardness' ),
				$item['zone_name']
			);
			return 0;
		}

		if ( ! isset( $name_map['names'][ $name_key ] ) ) {
			$errors[] = self::error(
				$item['line'],
				__( 'No zone with this name has been imported for this country. Check the spelling, or import the zones file first.', 'kdna-water-hardness' ),
				$item['zone_name']
			);
			return 0;
		}

		return (int) $name_map['names'][ $name_key ];
	}

	/*
	 * -----------------------------------------------------------------------
	 * Value parsing
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Returns a closure that reads a mapped field out of a row, so the
	 * validation code stays readable.
	 *
	 * @param array $data    Row values.
	 * @param array $mapping Field name to column index.
	 * @return callable
	 */
	private static function row_reader( array $data, array $mapping ) {
		return function ( $field ) use ( $data, $mapping ) {
			if ( ! isset( $mapping[ $field ] ) || $mapping[ $field ] < 0 ) {
				return '';
			}

			$index = $mapping[ $field ];

			// A short row is common in hand-edited files and is not itself an
			// error, so a missing column reads as empty.
			return isset( $data[ $index ] ) ? trim( (string) $data[ $index ] ) : '';
		};
	}

	/**
	 * Reads a hardness figure out of a cell.
	 *
	 * Real reports do not hold clean numbers. This accepts thousands
	 * separators, a trailing unit, and the "less than" notation used for
	 * readings below a laboratory's detection limit, where the stated limit
	 * is the only figure available and is the conventional reading. It
	 * refuses a range, because picking one end or the midpoint of "50 to 100"
	 * would be inventing a number.
	 *
	 * @param string $value Raw cell value.
	 * @return float|WP_Error
	 */
	public static function parse_number( $value ) {
		$raw = trim( (string) $value );

		if ( '' === $raw ) {
			return new WP_Error( 'kdna_wh_no_value', __( 'Hardness value is empty.', 'kdna-water-hardness' ) );
		}

		// A range cannot be reduced to one figure without guessing.
		if ( preg_match( '/\d\s*(?:-|–|—|to)\s*\d/i', $raw ) ) {
			return new WP_Error(
				'kdna_wh_range',
				__( 'This looks like a range. Give one figure per zone, and add a second zone row if the utility publishes two.', 'kdna-water-hardness' )
			);
		}

		$clean = str_replace( array( ',', ' ', "\xc2\xa0" ), '', $raw );
		$clean = ltrim( $clean, '<>~≈≤≥' );

		if ( ! preg_match( '/-?\d+(?:\.\d+)?/', $clean, $match ) ) {
			return new WP_Error(
				'kdna_wh_not_a_number',
				__( 'Hardness value is not a number.', 'kdna-water-hardness' )
			);
		}

		return (float) $match[0];
	}

	/**
	 * Checks a source URL.
	 *
	 * @param string $value Raw cell value.
	 * @return string|WP_Error
	 */
	public static function parse_url_value( $value ) {
		$raw = trim( (string) $value );

		if ( '' === $raw ) {
			return new WP_Error( 'kdna_wh_no_url', __( 'Source URL is empty.', 'kdna-water-hardness' ) );
		}

		$url    = esc_url_raw( $raw );
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

		if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error(
				'kdna_wh_bad_url',
				__( 'Source URL is not a valid web address. It should begin with http:// or https://', 'kdna-water-hardness' )
			);
		}

		return $url;
	}

	/**
	 * Reads a publication date out of a cell.
	 *
	 * A bare year is accepted and read as the first of January, because
	 * utility reports are frequently cited by year alone.
	 *
	 * @param string $value Raw cell value.
	 * @return string|WP_Error Y-m-d.
	 */
	public static function parse_date( $value ) {
		$raw = trim( (string) $value );

		if ( '' === $raw ) {
			return new WP_Error( 'kdna_wh_no_date', __( 'Source date is empty.', 'kdna-water-hardness' ) );
		}

		if ( preg_match( '/^(19|20)\d{2}$/', $raw ) ) {
			$raw .= '-01-01';
		}

		$timestamp = strtotime( $raw );

		if ( ! $timestamp ) {
			return new WP_Error(
				'kdna_wh_bad_date',
				__( 'Source date could not be read. Use a format such as 2025-07-01.', 'kdna-water-hardness' )
			);
		}

		// A report cannot have been published in the future, so this is a
		// typo or a mis-read format rather than a date.
		if ( $timestamp > ( time() + DAY_IN_SECONDS ) ) {
			return new WP_Error(
				'kdna_wh_future_date',
				__( 'Source date is in the future. Check the day and month are not the wrong way round.', 'kdna-water-hardness' )
			);
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Builds one entry for the error report.
	 *
	 * @param int    $line    Row number in the file.
	 * @param string $message What is wrong, in plain English.
	 * @param string $value   The offending value.
	 * @return array
	 */
	private static function error( $line, $message, $value ) {
		return array(
			'line'    => (int) $line,
			'message' => $message,
			'value'   => substr( trim( (string) $value ), 0, 120 ),
		);
	}
}
