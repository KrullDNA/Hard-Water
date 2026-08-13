<?php
/**
 * A minimal reader for the MaxMind DB binary format.
 *
 * Only what a country lookup needs: open the file, walk the search tree for an
 * IP address, and decode the record it lands on. It is written against the
 * published MaxMind DB file format specification.
 *
 * The alternative was requiring MaxMind's own PHP library through Composer,
 * which a WordPress plugin cannot reasonably ask a site owner to install. This
 * is the price of the geolocation fallback working out of the box.
 *
 * The file is read through a handle with seeks rather than loaded into memory.
 * A GeoLite2 Country database is around nine megabytes, and a lookup only
 * needs a few dozen bytes of it.
 *
 * @package KDNA_Water_Hardness
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KDNA_WH_MMDB
 */
class KDNA_WH_MMDB {

	/**
	 * Marks the start of the metadata block, near the end of the file.
	 */
	const METADATA_MARKER = "\xAB\xCD\xEFMaxMind.com";

	/**
	 * How far back from the end of the file to look for that marker.
	 */
	const METADATA_MAX_SIZE = 131072;

	/**
	 * Open file handle.
	 *
	 * @var resource|null
	 */
	private $handle = null;

	/**
	 * Decoded metadata.
	 *
	 * @var array
	 */
	private $metadata = array();

	/**
	 * Number of nodes in the search tree.
	 *
	 * @var int
	 */
	private $node_count = 0;

	/**
	 * Bits per record. 24, 28 or 32.
	 *
	 * @var int
	 */
	private $record_size = 0;

	/**
	 * Bytes per node, which is two records.
	 *
	 * @var int
	 */
	private $node_bytes = 0;

	/**
	 * Where the data section begins, in bytes from the start of the file.
	 *
	 * @var int
	 */
	private $data_start = 0;

	/**
	 * 4 or 6.
	 *
	 * @var int
	 */
	private $ip_version = 6;

	/**
	 * The node an IPv4 lookup starts from in an IPv6 database. Worked out once
	 * and kept, because finding it means walking 96 bits.
	 *
	 * @var int|null
	 */
	private $ipv4_start = null;

	/**
	 * Opens a database file.
	 *
	 * @param string $path Absolute path to the .mmdb file.
	 * @return KDNA_WH_MMDB|WP_Error
	 */
	public static function open( $path ) {
		if ( ! is_readable( $path ) || filesize( $path ) < 512 ) {
			return new WP_Error( 'kdna_wh_mmdb_missing', __( 'The geolocation database could not be read.', 'kdna-water-hardness' ) );
		}

		$reader = new self();
		$result = $reader->load( $path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $reader;
	}

	/**
	 * Reads the metadata and works out the shape of the file.
	 *
	 * @param string $path Absolute path.
	 * @return true|WP_Error
	 */
	private function load( $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- random access to a binary file, which WP_Filesystem cannot do.
		$handle = fopen( $path, 'rb' );

		if ( ! $handle ) {
			return new WP_Error( 'kdna_wh_mmdb_open', __( 'The geolocation database could not be opened.', 'kdna-water-hardness' ) );
		}

		$this->handle = $handle;

		$size  = filesize( $path );
		$tail  = min( $size, self::METADATA_MAX_SIZE );
		$start = $size - $tail;

		fseek( $handle, $start );
		$block = fread( $handle, $tail );

		// The last occurrence wins: the marker could appear inside the data.
		$position = strrpos( (string) $block, self::METADATA_MARKER );

		if ( false === $position ) {
			$this->close();
			return new WP_Error( 'kdna_wh_mmdb_format', __( 'That file is not a MaxMind database.', 'kdna-water-hardness' ) );
		}

		$offset = $start + $position + strlen( self::METADATA_MARKER );

		// Metadata is decoded relative to the file start rather than a data
		// section, so the data section base is temporarily zero.
		$this->data_start = 0;

		$metadata = $this->decode( $offset );

		if ( ! is_array( $metadata['value'] ) || empty( $metadata['value']['node_count'] ) ) {
			$this->close();
			return new WP_Error( 'kdna_wh_mmdb_metadata', __( 'The geolocation database metadata could not be read.', 'kdna-water-hardness' ) );
		}

		$this->metadata    = $metadata['value'];
		$this->node_count  = (int) $metadata['value']['node_count'];
		$this->record_size = (int) $metadata['value']['record_size'];
		$this->ip_version  = isset( $metadata['value']['ip_version'] ) ? (int) $metadata['value']['ip_version'] : 6;

		if ( ! in_array( $this->record_size, array( 24, 28, 32 ), true ) ) {
			$this->close();
			return new WP_Error( 'kdna_wh_mmdb_record_size', __( 'The geolocation database uses an unsupported record size.', 'kdna-water-hardness' ) );
		}

		$this->node_bytes = ( $this->record_size * 2 ) / 8;

		// Sixteen zero bytes separate the search tree from the data section.
		$this->data_start = ( $this->node_count * $this->node_bytes ) + 16;

		return true;
	}

	/**
	 * Closes the file.
	 *
	 * @return void
	 */
	public function close() {
		if ( is_resource( $this->handle ) ) {
			fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		$this->handle = null;
	}

	/**
	 * The database's own metadata, including when it was built.
	 *
	 * @return array
	 */
	public function metadata() {
		return $this->metadata;
	}

	/**
	 * Looks up the two letter country code for an IP address.
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return string Country code, or an empty string when not found.
	 */
	public function country( $ip ) {
		$record = $this->record_for( $ip );

		if ( ! is_array( $record ) ) {
			return '';
		}

		/*
		 * country is where the address actually is. registered_country is
		 * where the block is registered, which is the better answer than
		 * nothing when the first is absent, for instance on satellite or
		 * anycast ranges.
		 */
		foreach ( array( 'country', 'registered_country' ) as $key ) {
			if ( isset( $record[ $key ]['iso_code'] ) && is_string( $record[ $key ]['iso_code'] ) ) {
				return strtoupper( $record[ $key ]['iso_code'] );
			}
		}

		return '';
	}

	/**
	 * Walks the search tree and decodes whatever the address lands on.
	 *
	 * @param string $ip IP address.
	 * @return array|null
	 */
	public function record_for( $ip ) {
		if ( ! is_resource( $this->handle ) ) {
			return null;
		}

		$packed = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid address is a normal outcome here.

		if ( false === $packed ) {
			return null;
		}

		$is_ipv4 = 4 === strlen( $packed );

		if ( ! $is_ipv4 && 4 === $this->ip_version ) {
			// An IPv6 address cannot be in an IPv4-only database.
			return null;
		}

		if ( $is_ipv4 && 6 === $this->ip_version ) {
			$node = $this->ipv4_start_node();

			if ( null === $node ) {
				return null;
			}
		} else {
			$node = 0;
		}

		$bits = strlen( $packed ) * 8;

		for ( $i = 0; $i < $bits; $i++ ) {
			if ( $node >= $this->node_count ) {
				break;
			}

			$byte = ord( $packed[ (int) ( $i / 8 ) ] );
			$bit  = 1 & ( $byte >> ( 7 - ( $i % 8 ) ) );
			$node = $this->read_record( $node, $bit );

			if ( null === $node ) {
				return null;
			}
		}

		// Landing exactly on node_count means the address is in the tree but
		// has no data, which is a normal answer rather than a failure.
		if ( $node === $this->node_count ) {
			return null;
		}

		if ( $node < $this->node_count ) {
			return null;
		}

		$offset  = ( $node - $this->node_count - 16 ) + $this->data_start;
		$decoded = $this->decode( $offset );

		return is_array( $decoded['value'] ) ? $decoded['value'] : null;
	}

	/**
	 * Finds the node an IPv4 lookup begins at inside an IPv6 database, by
	 * walking the 96 zero bits that prefix the IPv4 space.
	 *
	 * @return int|null
	 */
	private function ipv4_start_node() {
		if ( null !== $this->ipv4_start ) {
			return $this->ipv4_start;
		}

		$node = 0;

		for ( $i = 0; $i < 96 && $node < $this->node_count; $i++ ) {
			$node = $this->read_record( $node, 0 );

			if ( null === $node ) {
				return null;
			}
		}

		$this->ipv4_start = $node;

		return $node;
	}

	/**
	 * Reads the left or right record out of a node.
	 *
	 * The 28 bit layout is the awkward one: the middle byte carries the top
	 * four bits of both records, the high nibble belonging to the left and the
	 * low nibble to the right.
	 *
	 * @param int $node  Node index.
	 * @param int $index 0 for left, 1 for right.
	 * @return int|null
	 */
	private function read_record( $node, $index ) {
		$base = $node * $this->node_bytes;

		fseek( $this->handle, $base );
		$bytes = fread( $this->handle, $this->node_bytes );

		if ( false === $bytes || strlen( $bytes ) < $this->node_bytes ) {
			return null;
		}

		switch ( $this->record_size ) {
			case 24:
				$chunk = substr( $bytes, $index * 3, 3 );
				return ( ord( $chunk[0] ) << 16 ) | ( ord( $chunk[1] ) << 8 ) | ord( $chunk[2] );

			case 28:
				if ( 0 === $index ) {
					return ( ( ord( $bytes[3] ) & 0xF0 ) << 20 )
						| ( ord( $bytes[0] ) << 16 )
						| ( ord( $bytes[1] ) << 8 )
						| ord( $bytes[2] );
				}

				return ( ( ord( $bytes[3] ) & 0x0F ) << 24 )
					| ( ord( $bytes[4] ) << 16 )
					| ( ord( $bytes[5] ) << 8 )
					| ord( $bytes[6] );

			default:
				$chunk = substr( $bytes, $index * 4, 4 );
				$parts = unpack( 'N', $chunk );
				return (int) $parts[1];
		}
	}

	/*
	 * -----------------------------------------------------------------------
	 * Decoding
	 * -----------------------------------------------------------------------
	 */

	/**
	 * Decodes the value at an offset.
	 *
	 * @param int $offset Absolute byte offset.
	 * @return array Value, and the offset immediately after it.
	 */
	private function decode( $offset ) {
		$control = ord( $this->read_at( $offset, 1 ) );
		$offset++;

		$type = $control >> 5;

		// Type 0 means the real type is in the next byte, offset by seven.
		if ( 0 === $type ) {
			$type = 7 + ord( $this->read_at( $offset, 1 ) );
			$offset++;
		}

		// A pointer carries its size differently from everything else.
		if ( 1 === $type ) {
			return $this->decode_pointer( $control, $offset );
		}

		list( $size, $offset ) = $this->decode_size( $control, $offset );

		switch ( $type ) {
			case 2: // UTF-8 string.
				$value   = $size > 0 ? $this->read_at( $offset, $size ) : '';
				$offset += $size;
				return array(
					'value'  => $value,
					'offset' => $offset,
				);

			case 3: // Double.
				$bytes   = $this->read_at( $offset, 8 );
				$offset += 8;
				$parts   = unpack( 'E', $bytes );
				return array(
					'value'  => $parts ? $parts[1] : 0.0,
					'offset' => $offset,
				);

			case 4: // Bytes.
				$value   = $size > 0 ? $this->read_at( $offset, $size ) : '';
				$offset += $size;
				return array(
					'value'  => $value,
					'offset' => $offset,
				);

			case 5: // uint16.
			case 6: // uint32.
			case 9: // uint64.
				return array(
					'value'  => $this->decode_uint( $offset, $size ),
					'offset' => $offset + $size,
				);

			case 7: // Map.
				return $this->decode_map( $size, $offset );

			case 8: // int32, two's complement.
				$value = $this->decode_uint( $offset, $size );

				if ( $size > 0 && $value >= ( 1 << ( ( $size * 8 ) - 1 ) ) ) {
					$value -= ( 1 << ( $size * 8 ) );
				}

				return array(
					'value'  => $value,
					'offset' => $offset + $size,
				);

			case 10: // uint128, kept as a hex string rather than losing it.
				$value = $size > 0 ? bin2hex( $this->read_at( $offset, $size ) ) : '0';
				return array(
					'value'  => $value,
					'offset' => $offset + $size,
				);

			case 11: // Array.
				return $this->decode_array( $size, $offset );

			case 12: // Data cache container, never appears in a record.
			case 13: // End marker.
				return array(
					'value'  => null,
					'offset' => $offset,
				);

			case 14: // Boolean, where the size is the value.
				return array(
					'value'  => (bool) $size,
					'offset' => $offset,
				);

			case 15: // Float.
				$bytes   = $this->read_at( $offset, 4 );
				$offset += 4;
				$parts   = unpack( 'G', $bytes );
				return array(
					'value'  => $parts ? $parts[1] : 0.0,
					'offset' => $offset,
				);
		}

		return array(
			'value'  => null,
			'offset' => $offset,
		);
	}

	/**
	 * Reads the size out of a control byte, following it into extra bytes for
	 * the larger sizes.
	 *
	 * @param int $control Control byte.
	 * @param int $offset  Offset after the control byte.
	 * @return array Size, and the new offset.
	 */
	private function decode_size( $control, $offset ) {
		$size = $control & 0x1F;

		if ( $size < 29 ) {
			return array( $size, $offset );
		}

		if ( 29 === $size ) {
			$size = 29 + ord( $this->read_at( $offset, 1 ) );
			return array( $size, $offset + 1 );
		}

		if ( 30 === $size ) {
			$size = 285 + $this->decode_uint( $offset, 2 );
			return array( $size, $offset + 2 );
		}

		$size = 65821 + $this->decode_uint( $offset, 3 );

		return array( $size, $offset + 3 );
	}

	/**
	 * Follows a pointer and decodes what it points at.
	 *
	 * @param int $control Control byte.
	 * @param int $offset  Offset after the control byte.
	 * @return array
	 */
	private function decode_pointer( $control, $offset ) {
		$size  = ( $control >> 3 ) & 0x3;
		$value = $control & 0x7;

		switch ( $size ) {
			case 0:
				$pointer = ( $value << 8 ) | ord( $this->read_at( $offset, 1 ) );
				$offset += 1;
				break;

			case 1:
				$pointer = ( ( $value << 16 ) | $this->decode_uint( $offset, 2 ) ) + 2048;
				$offset += 2;
				break;

			case 2:
				$pointer = ( ( $value << 24 ) | $this->decode_uint( $offset, 3 ) ) + 526336;
				$offset += 3;
				break;

			default:
				$pointer = $this->decode_uint( $offset, 4 );
				$offset += 4;
				break;
		}

		$target = $this->decode( $this->data_start + $pointer );

		// The pointer's own value is returned, but reading continues after the
		// pointer rather than after whatever it pointed at.
		return array(
			'value'  => $target['value'],
			'offset' => $offset,
		);
	}

	/**
	 * Decodes a map of key and value pairs.
	 *
	 * @param int $size   Number of pairs.
	 * @param int $offset Offset of the first key.
	 * @return array
	 */
	private function decode_map( $size, $offset ) {
		$map = array();

		for ( $i = 0; $i < $size; $i++ ) {
			$key = $this->decode( $offset );

			// A malformed file must not spin here.
			if ( $key['offset'] <= $offset ) {
				break;
			}

			$offset = $key['offset'];
			$value  = $this->decode( $offset );
			$offset = $value['offset'];

			if ( is_string( $key['value'] ) ) {
				$map[ $key['value'] ] = $value['value'];
			}
		}

		return array(
			'value'  => $map,
			'offset' => $offset,
		);
	}

	/**
	 * Decodes an array.
	 *
	 * @param int $size   Number of entries.
	 * @param int $offset Offset of the first entry.
	 * @return array
	 */
	private function decode_array( $size, $offset ) {
		$list = array();

		for ( $i = 0; $i < $size; $i++ ) {
			$entry = $this->decode( $offset );

			if ( $entry['offset'] <= $offset ) {
				break;
			}

			$offset = $entry['offset'];
			$list[] = $entry['value'];
		}

		return array(
			'value'  => $list,
			'offset' => $offset,
		);
	}

	/**
	 * Reads a big-endian unsigned integer of any width up to eight bytes.
	 *
	 * @param int $offset Absolute offset.
	 * @param int $length Bytes to read.
	 * @return int
	 */
	private function decode_uint( $offset, $length ) {
		if ( $length <= 0 ) {
			return 0;
		}

		$bytes = $this->read_at( $offset, $length );
		$value = 0;

		for ( $i = 0; $i < strlen( $bytes ); $i++ ) {
			$value = ( $value << 8 ) | ord( $bytes[ $i ] );
		}

		return $value;
	}

	/**
	 * Reads raw bytes from the file.
	 *
	 * @param int $offset Absolute offset.
	 * @param int $length Bytes to read.
	 * @return string Always at least $length bytes, padded with nulls if the
	 *                file is truncated, so a corrupt file cannot cause a read
	 *                of a null offset further up.
	 */
	private function read_at( $offset, $length ) {
		if ( $length <= 0 || ! is_resource( $this->handle ) ) {
			return str_repeat( "\0", max( 1, (int) $length ) );
		}

		fseek( $this->handle, $offset );
		$bytes = fread( $this->handle, $length );

		if ( false === $bytes ) {
			$bytes = '';
		}

		if ( strlen( $bytes ) < $length ) {
			$bytes .= str_repeat( "\0", $length - strlen( $bytes ) );
		}

		return $bytes;
	}
}
