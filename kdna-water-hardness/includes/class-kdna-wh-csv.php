<?php
/**
 * CSV file reading.
 *
 * Source data arrives as whatever the utility published, so this deliberately
 * tolerates the things real files do: a byte order mark from Excel, semicolon
 * or tab separators, Windows line endings, blank lines, and rows with fewer
 * columns than the header.
 *
 * Reading is done by byte offset rather than line number so a large file can
 * be imported across several requests without re-reading it from the start
 * each time.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_CSV
 */
class KDNA_WH_CSV {

	/**
	 * Separators tried when detecting the format, in order of likelihood.
	 */
	const DELIMITERS = array( ',', ';', "\t", '|' );

	/**
	 * Opens a CSV file for reading.
	 *
	 * @param string $path Absolute path.
	 * @return resource|WP_Error File handle.
	 */
	private static function open( $path ) {
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'kdna_wh_csv_unreadable', __( 'The uploaded file could not be read. It may have been cleaned up already, in which case upload it again.', 'kdna-water-hardness' ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a large file line by line, which WP_Filesystem cannot do.
		$handle = fopen( $path, 'r' );

		if ( ! $handle ) {
			return new WP_Error( 'kdna_wh_csv_unreadable', __( 'The uploaded file could not be opened.', 'kdna-water-hardness' ) );
		}

		return $handle;
	}

	/**
	 * Reads one row from an open handle, with the arguments PHP 8.4 expects.
	 *
	 * The escape character is explicitly disabled. Leaving it at the default
	 * backslash makes PHP 8.4 emit a deprecation notice, and also mangles any
	 * field containing a backslash, which Windows paths in a notes column
	 * routinely do.
	 *
	 * @param resource $handle    Open file handle.
	 * @param string   $delimiter Field separator.
	 * @return array|false Row, or false at end of file.
	 */
	private static function read_row( $handle, $delimiter ) {
		return fgetcsv( $handle, 0, $delimiter, '"', '' );
	}

	/**
	 * Works out which separator a file uses, by reading the first line and
	 * seeing which candidate splits it into the most columns.
	 *
	 * @param string $path Absolute path.
	 * @return string Delimiter, defaulting to a comma.
	 */
	public static function detect_delimiter( $path ) {
		$handle = self::open( $path );

		if ( is_wp_error( $handle ) ) {
			return ',';
		}

		$line = fgets( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( false === $line ) {
			return ',';
		}

		$line  = self::strip_bom( $line );
		$best  = ',';
		$count = 0;

		foreach ( self::DELIMITERS as $delimiter ) {
			$fields = count( str_getcsv( $line, $delimiter, '"', '' ) );

			if ( $fields > $count ) {
				$count = $fields;
				$best  = $delimiter;
			}
		}

		return $best;
	}

	/**
	 * Reads the header row and returns the column names.
	 *
	 * @param string $path      Absolute path.
	 * @param string $delimiter Field separator.
	 * @return array|WP_Error List of column names.
	 */
	public static function read_header( $path, $delimiter = ',' ) {
		$handle = self::open( $path );

		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		$row = self::read_row( $handle, $delimiter );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( ! is_array( $row ) ) {
			return new WP_Error( 'kdna_wh_csv_empty', __( 'That file appears to be empty.', 'kdna-water-hardness' ) );
		}

		$header = array();

		foreach ( $row as $index => $name ) {
			$name = 0 === $index ? self::strip_bom( (string) $name ) : (string) $name;
			$name = trim( $name );

			// A blank header still needs a usable label in the mapping screen.
			$header[] = '' === $name
				? sprintf(
					/* translators: %d: column number. */
					__( 'Column %d', 'kdna-water-hardness' ),
					$index + 1
				)
				: $name;
		}

		return $header;
	}

	/**
	 * Reads the first few data rows, for the preview shown on the mapping
	 * screen. Seeing real values next to the column names is the quickest way
	 * to spot a mis-mapped column.
	 *
	 * @param string $path      Absolute path.
	 * @param string $delimiter Field separator.
	 * @param int    $limit     How many rows.
	 * @return array
	 */
	public static function read_preview( $path, $delimiter = ',', $limit = 3 ) {
		$handle = self::open( $path );

		if ( is_wp_error( $handle ) ) {
			return array();
		}

		// Skip the header.
		self::read_row( $handle, $delimiter );

		$rows = array();

		while ( count( $rows ) < $limit ) {
			$row = self::read_row( $handle, $delimiter );

			if ( ! is_array( $row ) ) {
				break;
			}

			if ( self::is_blank_row( $row ) ) {
				continue;
			}

			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $rows;
	}

	/**
	 * Counts the data rows in a file, excluding the header and blank lines.
	 * Used only to show a total on the progress screen.
	 *
	 * @param string $path      Absolute path.
	 * @param string $delimiter Field separator.
	 * @return int
	 */
	public static function count_rows( $path, $delimiter = ',' ) {
		$handle = self::open( $path );

		if ( is_wp_error( $handle ) ) {
			return 0;
		}

		self::read_row( $handle, $delimiter );

		$count = 0;

		while ( true ) {
			$row = self::read_row( $handle, $delimiter );

			if ( ! is_array( $row ) ) {
				break;
			}

			if ( ! self::is_blank_row( $row ) ) {
				$count++;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $count;
	}

	/**
	 * Reads a block of rows starting from a byte offset.
	 *
	 * Passing the byte offset back in on the next request is what makes a
	 * large import resumable: the file is never re-read from the beginning.
	 *
	 * Each row is returned with the row number it occupies in the file, so the
	 * error report can name the row the user has to go and fix. Row 1 is the
	 * header, so the first data row is row 2, matching what a spreadsheet
	 * shows. A quoted field containing line breaks counts as one row, again
	 * matching the spreadsheet rather than the raw text file.
	 *
	 * @param string $path       Absolute path.
	 * @param string $delimiter  Field separator.
	 * @param int    $offset     Byte offset to resume from. Zero starts at the
	 *                           first data row, skipping the header.
	 * @param int    $limit      Maximum rows to return.
	 * @param int    $start_line Row number the next record will occupy.
	 * @return array|WP_Error {
	 *     @type array $rows   Each with a line number and its data.
	 *     @type int   $offset Byte offset to resume from next time.
	 *     @type int   $line   Row number to resume numbering from.
	 *     @type bool  $eof    True when the end of the file was reached.
	 *     @type int   $blank  Blank rows skipped in this block.
	 * }
	 */
	public static function read_chunk( $path, $delimiter, $offset, $limit, $start_line = 2 ) {
		$handle = self::open( $path );

		if ( is_wp_error( $handle ) ) {
			return $handle;
		}

		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		} else {
			// Skip the header row on the first pass.
			self::read_row( $handle, $delimiter );
		}

		$rows  = array();
		$blank = 0;
		$eof   = false;
		$line  = max( 2, (int) $start_line );

		while ( count( $rows ) < $limit ) {
			$row = self::read_row( $handle, $delimiter );

			if ( ! is_array( $row ) ) {
				$eof = true;
				break;
			}

			if ( self::is_blank_row( $row ) ) {
				$blank++;
				$line++;
				continue;
			}

			$rows[] = array(
				'line' => $line,
				'data' => $row,
			);

			$line++;
		}

		$position = ftell( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'rows'   => $rows,
			'offset' => false === $position ? $offset : (int) $position,
			'line'   => $line,
			'eof'    => $eof,
			'blank'  => $blank,
		);
	}

	/**
	 * True when a row holds nothing but empty strings. fgetcsv returns
	 * array( null ) for a blank line, which is not the same as end of file
	 * and must not stop the import.
	 *
	 * @param array $row Row from fgetcsv.
	 * @return bool
	 */
	private static function is_blank_row( $row ) {
		foreach ( (array) $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Removes the UTF-8 byte order mark that Excel puts at the start of a
	 * saved CSV. Left in place it becomes part of the first column name, so
	 * the header reads as something invisible rather than "postcode".
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function strip_bom( $text ) {
		$bom = pack( 'H*', 'EFBBBF' );

		return (string) preg_replace( "/^{$bom}/", '', (string) $text );
	}
}
