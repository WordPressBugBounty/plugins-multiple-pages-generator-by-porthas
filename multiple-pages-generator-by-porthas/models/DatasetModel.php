<?php

if (!defined('ABSPATH')) exit;

use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Type;

require_once(realpath(__DIR__ . '/../helpers/Constant.php'));

class MPG_DatasetModel
{
	/**
	 * Get the chunk size to use for dataset chunking.
	 *
	 * @return int The chunk size
	 */
	public static function get_chunk_size() {
		return apply_filters( 'mpg_chunk_size', 5000 );
	}

	/**
	 * Get the threshold at which to start chunking datasets.
	 *
	 * @return int The threshold
	 */
	public static function get_chunk_threshold() {
		return apply_filters( 'mpg_chunk_threshold', 5000 );
	}

	/**
	 * Get project path.
	 * 
	 * @param int $project_id The project ID
	 * @return string The path to the project folder
	 */
	public static function get_project_path( $project_id ) {
		return self::uploads_base_path() . 'project-' . $project_id . '/';
	}

	/**
	 * Get the path to the index file for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return string The path to the index file
	 */
	public static function get_index_path( $project_id ) {
		return self::get_project_path( $project_id ) . 'index.json';
	}

	/**
	 * Get the path to the chunks directory for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return string The path to the chunks directory
	 */
	public static function get_chunks_dir( $project_id ) {
		return self::get_project_path( $project_id ) . 'chunks/';
	}

	/**
	 * Get the path to a specific chunk file.
	 * 
	 * @param int $project_id The project ID
	 * @param int $chunk_number The chunk number
	 * @return string The path to the chunk file
	 */
	public static function get_chunk_path( $project_id, $chunk_number ) {
		return self::get_chunks_dir( $project_id ) . sprintf( 'chunk_%06d.json', $chunk_number );
	}

	/**
	 * Check if a dataset is chunked.
	 * 
	 * @param int $project_id The project ID
	 * @return bool Whether the dataset is chunked
	 */
	public static function is_dataset_chunked( $project_id ) {
		return file_exists( self::get_index_path( $project_id ) );
	}

	/**
	 * Create an index for a project's dataset using a streaming approach to minimize memory usage.
	 * 
	 * @param int $project_id The project ID
	 * @return bool Whether the index was created successfully
	 */
	public static function create_index( $project_id ) {
		self::delete_index( $project_id );
		self::create_dataset_chunks( $project_id );

		$chunk_size     = self::get_chunk_size();
		$index_path     = self::get_index_path( $project_id );
		$project_path   = self::get_dataset_path_by_project( $project_id );
		$headers        = self::read_dataset( $project_path, true );
		$project        = MPG_ProjectModel::get_project_url_structure_and_space_replacer( $project_id );
		$url_structure  = isset( $project['url_structure'] ) ? $project['url_structure'] : '';
		$space_replacer = isset( $project['space_replacer'] ) ? $project['space_replacer'] : '';

		$chunks_meta = self::get_dataset_chunks_meta( $project_id );
		$total_rows = isset( $chunks_meta['total_rows'] ) ? $chunks_meta['total_rows'] - 1 : 0;
		$total_chunks = isset( $chunks_meta['total_chunks'] ) ? $chunks_meta['total_chunks'] : 0;

		// Ensure directory exists for the index file
		$index_dir = dirname( $index_path );
		if ( ! file_exists( $index_dir ) ) {
			wp_mkdir_p( $index_dir );
		}

		// Create a temporary file for permalinks
		$permalinks_temp_path = $index_path . '.temp';
		$permalinks_file = fopen( $permalinks_temp_path, 'w' );
		if ( ! $permalinks_file ) {
			return false;
		}

		// Start writing the permalinks as a JSON object
		fwrite( $permalinks_file, '{' );

		$first_entry = true;
		$batch_size = 1000; // Process and write in batches of 1000 entries

		// If there are no chunks (small dataset), process the dataset directly
		if ( $total_chunks === 0 ) {
			$dataset = self::read_dataset( $project_path, false );
			// Remove header row if present
			if ( isset( $dataset[0] ) && $dataset[0] === $headers ) {
				array_shift( $dataset );
			}

			$total_rows = count( $dataset );
			$batch_entries = [];
			$current_batch_size = 0;
			foreach ( $dataset as $offset_in_chunk => $row ) {
				$permalink = MPG_ProjectModel::mpg_generate_url_for_row( $row, $headers, $url_structure, $space_replacer );
				$batch_entries[$permalink] = [
					'chunk' => 0,
					'offset' => $offset_in_chunk + 1, // +1 because header is removed
				];
				$current_batch_size++;
				if ( $current_batch_size >= $batch_size ) {
					self::write_permalink_batch( $permalinks_file, $batch_entries, $first_entry );
					$first_entry = false;
					$batch_entries = [];
					$current_batch_size = 0;
				}
				unset( $permalink );
			}
			if ( $current_batch_size > 0 ) {
				self::write_permalink_batch( $permalinks_file, $batch_entries, $first_entry );
				$first_entry = false;
			}
			unset( $batch_entries );
			gc_collect_cycles();
		} else {
			// Process each chunk separately to reduce memory usage
			for ( $chunk_idx = 0; $chunk_idx < $total_chunks; $chunk_idx++ ) {
				// Get this chunk's data
				$chunk_data = self::get_dataset_chunk( $project_id, $chunk_idx );

				if ( empty( $chunk_data ) ) {
					continue;
				}

				// Prepare batch for more efficient processing
				$batch_entries = [];
				$current_batch_size = 0;

				foreach ( $chunk_data as $offset_in_chunk => $row ) {
					// Skip header row if it's included
					if ( $offset_in_chunk === 0 && isset( $chunk_data[0] ) && $chunk_data[0] === $headers ) {
						continue;
					}

					$permalink = MPG_ProjectModel::mpg_generate_url_for_row( $row, $headers, $url_structure, $space_replacer );

					// Add to current batch
					$batch_entries[$permalink] = [
						'chunk' => $chunk_idx,
						'offset' => $offset_in_chunk,
					];

					$current_batch_size++;

					// When we reach batch size, write the batch to file
					if ( $current_batch_size >= $batch_size ) {
						self::write_permalink_batch( $permalinks_file, $batch_entries, $first_entry );
						$first_entry = false;
						$batch_entries = []; // Clear batch
						$current_batch_size = 0;
					}

					// Free individual row memory
					unset( $permalink );
				}
                
				// Write any remaining entries in the final batch
				if ( $current_batch_size > 0 ) {
					self::write_permalink_batch( $permalinks_file, $batch_entries, $first_entry );
					$first_entry = false;
				}

				// Free memory
				unset( $batch_entries );
				unset( $chunk_data );
				gc_collect_cycles(); // Force garbage collection
			}
		}

		// Close permalinks object
		fwrite( $permalinks_file, '}' );
		fclose( $permalinks_file );

		$index_file = fopen( $index_path, 'w' );
		if ( ! $index_file ) {
			unlink( $permalinks_temp_path );
			return false;
		}

		fwrite( $index_file, '{' );

		$permalinks_contents = file_get_contents( $permalinks_temp_path );
		$permalinks_contents = json_decode( $permalinks_contents, true );
		$permalinks_contents = is_array( $permalinks_contents ) ? $permalinks_contents : [];

		// Write the metadata section
		$meta = [
			'version' => '1.0',
			'total_rows' => $total_rows,
			'total_chunks' => $total_chunks,
			'chunk_size' => $chunk_size,
			'created_at' => date( 'Y-m-d H:i:s' ),
			'column_headers' => $headers,
			'permalink_count' => count( $permalinks_contents ),
		];

		self::set_dataset_chunks_meta( $project_id, $meta );

		fwrite( $index_file, '"meta":' . json_encode( $meta, MPG_JSON_OPTIONS ) . ',' );

		// Write the beginning of indexes section
		fwrite( $index_file, '"indexes":{' );

		// Write the permalinks key
		fwrite( $index_file, '"permalinks":' );

		fwrite( $index_file, json_encode( $permalinks_contents, MPG_JSON_OPTIONS ) );

		unset( $permalinks_contents );

		unlink( $permalinks_temp_path );

		// Close the indexes object and the main JSON object
		fwrite( $index_file, '}}' );

		$file_saved = fclose( $index_file );

		if ( ! $file_saved ) {
			$index_content = file_get_contents( $index_path );
			if ( $index_content ) {
				$index = json_decode( $index_content, true );
				if ( $index ) {
					self::set_index_cache( $project_id, $index );
				}
			}
		}

		MPG_ProjectModel::update_last_check( $project_id );
		return $file_saved;
	}

	/**
	 * Delete the index file for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return bool Whether the index file was deleted
	 */
	public static function delete_index( $project_id ) {
		$index_path = self::get_index_path( $project_id );

		if ( file_exists( $index_path ) ) {
			self::delete_index_cache( $project_id );

			return unlink( $index_path );
		}

		return true;
	}

	/**
	 * Get the index for a project's dataset.
	 * 
	 * @param int $project_id The project ID
	 * @param string $key The key to retrieve from the index (e.g., 'permalinks')
	 * @return array Index data
	 */
	public static function get_index( $project_id, $key = null ) {
		$chunked_index = self::get_index_cache( $project_id );

		if ( $chunked_index !== false ) {
			return isset( $chunked_index['indexes'][ $key ] ) ? $chunked_index['indexes'][ $key ] : $chunked_index;
		}

		$index_path = self::get_index_path( $project_id );

		if ( ! file_exists( $index_path ) ) {
			return array();
		}

		$index_content = file_get_contents( $index_path );

		if ( ! $index_content ) {
			return array();
		}

		$index_data = json_decode( $index_content, true );

		if ( $index_data === null && json_last_error() !== JSON_ERROR_NONE ) {
			return array();
		}

		if ( is_array( $index_data ) ) {
			self::set_index_cache( $project_id, $index_data );
		}

		return isset( $index_data['indexes'][ $key ] ) ? $index_data['indexes'][ $key ] : $index_data;
	}

	/**
	 * Set chunked cache for the index file.
	 * Breaks large index into smaller chunks to avoid max_allowed_packet errors.
	 *
	 * @param int $project_id The project ID
	 * @return bool Whether all chunks were cached successfully
	 */
	public static function set_index_cache( $project_id, $index ) {
		$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );

		$meta_key = wp_hash( "mpg_index_meta_{$project_id}" );
		$meta = isset( $index['meta'] ) ? $index['meta'] : [];
		$success = set_transient( $meta_key, $meta, $expiration );

		$permalinks = isset( $index['indexes']['permalinks'] ) ? $index['indexes']['permalinks'] : [];

		if ( ! empty( $permalinks ) ) {
			$chunk_size       = self::get_chunk_size();
			$permalink_chunks = array_chunk( $permalinks, $chunk_size, true ); // Keep keys intact

			$chunks_meta_key = wp_hash( "mpg_index_permalinks_meta_{$project_id}" );
			$chunks_meta = [
				'chunk_count' => count( $permalink_chunks ),
				'total_permalinks' => count( $permalinks )
			];
			$success = set_transient( $chunks_meta_key, $chunks_meta, $expiration ) && $success;

			foreach ( $permalink_chunks as $chunk_index => $chunk ) {
				$chunk_key = wp_hash( "mpg_index_permalinks_{$project_id}_{$chunk_index}" );
				$encoded_chunk = json_encode( $chunk, MPG_JSON_OPTIONS );
				$success = set_transient( $chunk_key, $encoded_chunk, $expiration ) && $success;
			}
		}

		return $success;
	}
	
	/**
	 * Get chunked index from cache and reassemble it.
	 *
	 * @param int $project_id The project ID
	 * @return array|false The index data or false if not in cache
	 */
	public static function get_index_cache( $project_id ) {
		$meta_key = wp_hash( "mpg_index_meta_{$project_id}" );
		$meta = get_transient( $meta_key );
		
		if ( $meta === false ) {
			return false;
		}
		
		$chunks_meta_key = wp_hash( "mpg_index_permalinks_meta_{$project_id}" );
		$chunks_meta = get_transient( $chunks_meta_key );
		
		$permalinks = [];
		
		if ( $chunks_meta !== false && isset( $chunks_meta['chunk_count'] ) ) {
			   for ( $i = 0; $i < $chunks_meta['chunk_count']; $i++ ) {
				   $chunk_key = wp_hash( "mpg_index_permalinks_{$project_id}_{$i}" );
				   $chunk = get_transient( $chunk_key );
				   if ( $chunk === false ) {
					   return false;
				   }
				   $decoded_chunk = json_decode( $chunk, true );
				   if ( is_array( $decoded_chunk ) ) {
					   $permalinks = array_merge( $permalinks, $decoded_chunk );
				   }
			}
		}
		
		return [
			'meta' => $meta,
			'indexes' => [
				'permalinks' => $permalinks
			]
		];
	}
	
	/**
	 * Delete all chunked index cache data for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return void
	 */
	public static function delete_index_cache( $project_id ) {
		$meta_key = wp_hash( "mpg_index_meta_{$project_id}" );
		delete_transient( $meta_key );
		
		$chunks_meta_key = wp_hash( "mpg_index_permalinks_meta_{$project_id}" );
		$chunks_meta = get_transient( $chunks_meta_key );
		
		if ( $chunks_meta !== false && isset( $chunks_meta['chunk_count'] ) ) {
			for ( $i = 0; $i < $chunks_meta['chunk_count']; $i++ ) {
				$chunk_key = wp_hash( "mpg_index_permalinks_{$project_id}_{$i}" );
				delete_transient( $chunk_key );
			}
		}
		
		delete_transient( $chunks_meta_key );
	}

	/**
	 * Create dataset chunks for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return bool Whether the dataset was chunked
	 */
	public static function create_dataset_chunks( $project_id ) {
		self::delete_dataset_chunks( $project_id );

		$chunk_size = self::get_chunk_size();
		$chunk_threshold = self::get_chunk_threshold();
		$project_path = self::get_dataset_path_by_project( $project_id );

		$ext = MPG_Helper::mpg_get_extension_by_path( $project_path );
		$reader = MPG_Helper::mpg_get_spout_reader_by_extension( $ext );
		$reader->setShouldFormatDates( true );
		
		try {
			$reader->open($project_path);
			$total_rows = 0;
			$header_row = null;
			
			foreach ( $reader->getSheetIterator() as $sheet ) {
				foreach ( $sheet->getRowIterator() as $row ) {
					$row_data = $row->toArray();
					
					if ( $row_data[0] === null ) {
						continue;
					}
					
					// Store header row (first row)
					if ( $total_rows === 0 ) {
						$header_row = $row_data;
					}
					
					$total_rows++;
				}
				// We only process the first sheet
				break;
			}

			$reader->close();

			// If we don't have enough rows, don't bother chunking
			if ( $total_rows <= 1 ) {
				return false;
			}

			$data_rows = $total_rows - 1; // Exclude header
			$total_chunks = ceil( $data_rows / $chunk_size );

			if ( $total_rows < $chunk_threshold ) {
				return false;
			}

			$chunks_dir = self::get_chunks_dir( $project_id );
			if ( ! file_exists( $chunks_dir ) ) {
				wp_mkdir_p( $chunks_dir );
			}

			$chunks_meta = [
				'total_chunks' => $total_chunks,
				'chunk_size' => $chunk_size,
				'total_rows' => $total_rows,
				'created_at' => date( 'Y-m-d H:i:s' ),
			];
			
			self::set_dataset_chunks_meta( $project_id, $chunks_meta );

			$reader->open( $project_path );
			
			$current_chunk = 0;
			$rows_in_current_chunk = 0;
			$current_chunk_data = [];
			$row_counter = 0;

			foreach ( $reader->getSheetIterator() as $sheet ) {
				foreach ( $sheet->getRowIterator() as $row ) {
					$row_data = $row->toArray();

					if ( $row_data[0] === null ) {
						continue;
					}

					// Process header row separately
					if ( $row_counter === 0 ) {
						$row_counter++;
						continue;
					}

					if ( $rows_in_current_chunk === 0 ) {
						$current_chunk_data = [$header_row];
					}

					$current_chunk_data[] = $row_data;
					$rows_in_current_chunk++;
					$row_counter++;

					$is_last_row = ( $row_counter === $total_rows );
					if ( $rows_in_current_chunk >= $chunk_size || $is_last_row ) {
						$chunk_path = self::get_chunk_path( $project_id, $current_chunk );
						$success = file_put_contents( $chunk_path, json_encode( $current_chunk_data, MPG_JSON_OPTIONS ) );

						if ( $success !== false ) {
							self::set_dataset_chunk_cache( $project_id, $current_chunk, $current_chunk_data );
						}

						$current_chunk++;
						$rows_in_current_chunk = 0;

						// Force garbage collection to free memory
						unset( $current_chunk_data );
						gc_collect_cycles();
						$current_chunk_data = [];
					}
				}
				// We only process the first sheet
				break;
			}

			$reader->close();
			return true;
		} catch ( Exception $e ) {
			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );
			return false;
		}
	}

	/**
	 * Get a specific dataset chunk for a project.
	 * 
	 * @param int $project_id The project ID
	 * @param int $chunk_number The chunk number
	 * @return array The chunk data
	 */
	public static function get_dataset_chunk( $project_id, $chunk_number ) {
		$cached_chunk = self::get_dataset_chunk_cache( $project_id, $chunk_number );
		if ( $cached_chunk !== false ) {
			return $cached_chunk;
		}
		
		$chunk_path = self::get_chunk_path( $project_id, $chunk_number );

		if ( file_exists( $chunk_path ) ) {
			$chunk_content = file_get_contents( $chunk_path );
			if ( $chunk_content ) {
				$chunk_data = json_decode( $chunk_content, true );
				if ( is_array( $chunk_data ) ) {
					self::set_dataset_chunk_cache( $project_id, $chunk_number, $chunk_data );
					return $chunk_data;
				}
			}
		}

		return [];
	}
	
	/**
	 * Get dataset chunk from cache.
	 *
	 * @param int $project_id The project ID
	 * @param int $chunk_number The chunk number
	 * @return array|false The chunk data or false if not in cache
	 */
	public static function get_dataset_chunk_cache( $project_id, $chunk_number ) {
		$chunk_key = wp_hash( "mpg_dataset_chunk_{$project_id}_{$chunk_number}" );
		$cached = get_transient( $chunk_key );
		return json_decode( $cached, true );
	}
	
	/**
	 * Set dataset chunk in cache.
	 *
	 * @param int $project_id The project ID
	 * @param int $chunk_number The chunk number
	 * @param array $chunk_data The chunk data to cache
	 * @return bool Whether the chunk was cached successfully
	 */
	public static function set_dataset_chunk_cache( $project_id, $chunk_number, $chunk_data ) {
		$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );
		$chunk_key = wp_hash( "mpg_dataset_chunk_{$project_id}_{$chunk_number}" );
		$encoded_chunk = json_encode( $chunk_data, MPG_JSON_OPTIONS );
		return set_transient( $chunk_key, $encoded_chunk, $expiration );
	}
	
	/**
	 * Set dataset chunks metadata in cache.
	 *
	 * @param int $project_id The project ID
	 * @param array $meta The metadata to cache
	 * @return bool Whether the metadata was cached successfully
	 */
	public static function set_dataset_chunks_meta( $project_id, $meta ) {
		$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );
		$meta_key = wp_hash( "mpg_dataset_chunks_meta_{$project_id}" );
		return set_transient( $meta_key, $meta, $expiration );
	}

	/**
	 * Get dataset chunks metadata from cache, or fallback to index file's meta section.
	 *
	 * @param int $project_id The project ID
	 * @return array|false The metadata or false if not available
	 */
	public static function get_dataset_chunks_meta( $project_id ) {
		$meta_key = wp_hash( "mpg_dataset_chunks_meta_{$project_id}" );
		$meta = get_transient( $meta_key );
		if ( $meta !== false ) {
			return $meta;
		}
		// Fallback: Try to read from index file's meta section
		$index_path = self::get_index_path( $project_id );
		if ( file_exists( $index_path ) ) {
			$index_content = file_get_contents( $index_path );
			if ( $index_content ) {
				$index_data = json_decode( $index_content, true );
				if ( isset( $index_data['meta'] ) ) {
					self::set_dataset_chunks_meta( $project_id, $index_data['meta'] );
					return $index_data['meta'];
				}
			}
		}
		return false;
	}

	/**
	 * Get all dataset chunks for a project.
	 * 
	 * @param int $project_id The project ID
	 * @param bool $headers_only Whether to only return the headers
	 * @return array An array of all chunk data or just the headers if $headers_only is true
	 */
	public static function get_dataset_chunks( $project_id, $headers_only = false, $limit = null, $offset = 0 ) {
		$rows = [];
		$chunks_meta = self::get_dataset_chunks_meta( $project_id );

		if ( $chunks_meta === false || !isset( $chunks_meta['total_chunks'] ) ) {
			return $rows;
		}

		$total_chunks = $chunks_meta['total_chunks'];
		$is_first_chunk = true;
		$row_count = 0;
		$current_row_global = 0;
		$needed = ( $limit !== null ) ? $limit : PHP_INT_MAX;

		for ( $chunk_number = 0; $chunk_number < $total_chunks; $chunk_number++ ) {
			$chunk_data = self::get_dataset_chunk( $project_id, $chunk_number );

			if ( !empty( $chunk_data ) ) {
				// If this isn't the first chunk, remove the header row (first row)
				if ( !$is_first_chunk && count( $chunk_data ) > 1 ) {
					array_shift( $chunk_data );
				}

				if ( $headers_only ) {
					// Return only the header row from the first chunk
					return isset($chunk_data[0]) ? $chunk_data[0] : [];
				}

				foreach ( $chunk_data as $row ) {
					// Skip rows until offset is reached
					if ( $current_row_global < $offset ) {
						$current_row_global++;
						continue;
					}

					$rows[] = $row;
					$row_count++;
					$current_row_global++;

					if ( $row_count >= $needed ) {
						return $rows;
					}
				}

				$is_first_chunk = false;
			}
		}

		return $rows;
	}

	/**
	 * Get a specific row from a dataset chunk.
	 * 
	 * @param int $project_id The project ID
	 * @return array The row data
	 */
	public static function get_dataset_row( $project_id, $chunk, $offset ) {
		$chunk_data = self::get_dataset_chunk( $project_id, $chunk );

		if ( !empty( $chunk_data ) && isset( $chunk_data[ $offset ] ) ) {
			return $chunk_data[ $offset ];
		}

		return [];
	}

	/**
	 * Delete all dataset chunks for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return void
	 */
	public static function delete_dataset_chunks( $project_id ) {
		$chunks_dir = self::get_chunks_dir( $project_id );

		if ( file_exists( $chunks_dir ) ) {
			$files = glob( $chunks_dir . '*.json' );
			foreach ( $files as $file ) {
				unlink( $file );
			}

			rmdir( $chunks_dir );
		}

		$chunks_meta = self::get_dataset_chunks_meta( $project_id );
		$total_chunks = 0;

		if ( $chunks_meta !== false && isset( $chunks_meta['total_chunks'] ) ) {
			$total_chunks = $chunks_meta['total_chunks'];
		}

		for ( $chunk_number = 0; $chunk_number < $total_chunks; $chunk_number++ ) {
			$chunk_key = wp_hash( "mpg_dataset_chunk_{$project_id}_{$chunk_number}" );
			delete_transient( $chunk_key );
		}

		$meta_key = wp_hash( "mpg_dataset_chunks_meta_{$project_id}" );
		delete_transient( $meta_key );
	}

	/**
	 * Delete project folders.
	 * 
	 * @param int $project_id The project ID
	 * @return void
	 */
	public static function delete_project_folders( $project_id ) {
		$project_path = self::get_project_path( $project_id );
		self::delete_dataset_chunks( $project_id );
		self::delete_index( $project_id );

		if ( file_exists( $project_path ) ) {
			rmdir( $project_path );
		}
	}

	/**
	 * Download a file from a URL to a specified destination path.
	 *
	 * @param string $link The URL of the file to download.
	 * @param string $destination_path The local path where the file should be saved.
	 * @return bool True on success, false on failure.
	 */
	public static function download_file( $link, $destination_path ): bool {
		try {
			if ( empty( $destination_path ) ) {
				throw new Exception( 'Destination path is empty' );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( wp_http_validate_url( $link ) !== false ) {
				$tmp_path = download_url( $link );
				// Make sure there were no errors.
				if ( is_wp_error( $tmp_path ) ) {
					throw new Exception( $tmp_path->get_error_message() );
				}
			} else {
				$tmp_path = $link;
			}

			WP_Filesystem();
			global $wp_filesystem;
			// Make dir if not exists.
			if ( ! $wp_filesystem->exists( MPG_UPLOADS_DIR ) ) {
				$wp_filesystem->mkdir( MPG_UPLOADS_DIR, FS_CHMOD_DIR );
			}
			if ( ! $wp_filesystem->exists( dirname($destination_path) ) ) {
				$wp_filesystem->mkdir( dirname($destination_path), FS_CHMOD_DIR );
			}

			// Move temp file to final destination.
			$updated = $wp_filesystem->move( $tmp_path, $destination_path, true );

			// File delete and re-fetch in case of the file is not writeable.
			if ( ! $updated && is_readable( $destination_path ) ) {
				$wp_filesystem->delete( $destination_path );
				return $wp_filesystem->move( $tmp_path, $destination_path, true );
			}

			return true;
		} catch ( Exception $e ) {
			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

			return false;
		}
    }

	public static function uploads_base_path(){
		$base_path = MPG_UPLOADS_DIR;
		$blog_id   = get_current_blog_id();
		if ( is_multisite() && $blog_id > 1 ) {
			$base_path = MPG_UPLOADS_DIR . $blog_id . DIRECTORY_SEPARATOR;
		}
		return $base_path;
	}
	/**
	 * Get the base URL for uploads.
	 *
	 * This function retrieves the base URL for uploads, taking into account
	 * whether the site is part of a multisite network. If the site is part
	 * of a multisite network and the blog ID is greater than 1, the blog ID
	 * is appended to the base URL.
	 *
	 * @return string The base URL for uploads.
	 */
	public static function uploads_base_url(){
		$base_url = MPG_UPLOADS_URL;
		$blog_id   = get_current_blog_id();
		if ( is_multisite() && $blog_id > 1 ) {
			$base_url = MPG_UPLOADS_URL . $blog_id .DIRECTORY_SEPARATOR ;
		}
		return $base_url;
	}
	/**
	 * Get the dataset path by project.
	 *
	 * This function retrieves the dataset path associated with a given project.
	 * It handles both numeric project IDs and project objects, and ensures the
	 * dataset path is correctly formatted and updated in the database if necessary.
	 *
	 * @param mixed $project The project ID or project object.
	 * @return string The dataset path.
	 */
	public static function get_dataset_path_by_project( $project ) {

		global $wpdb;
		$dataset_path = '';
		$project_id = is_numeric( $project ) ? $project : $project->id;
		if ( is_numeric( $project ) ) {
			$dataset_path = $wpdb->get_var(
				$wpdb->prepare( "SELECT source_path FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . " WHERE id=%d", $project_id )
			);
		}
		if ( isset( $project->source_path ) ) {
			$dataset_path = $project->source_path;
		}
		if ( empty( $dataset_path ) ) {
			return '';
		}
		$base_path = self::uploads_base_path();
		//We check if the path has any directory separator, if not we assume it is a filename.
		if ( strpos( $dataset_path, DIRECTORY_SEPARATOR ) === false ) {
			return $base_path . $dataset_path;
		}
		//This is a legacy code, we need to check if the path is relative or absolute using wp-content.
		if ( false === strpos( $dataset_path, 'wp-content' ) ) {
			// The relative path is given we need to convert it to absolute path.
			$dataset_path = MPG_UPLOADS_DIR . $dataset_path;
			$filename     = basename( $dataset_path );
			$wpdb->update( $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, [ 'source_path' => $filename ], [ 'id' => $project_id ] );
			return $dataset_path;
		}
		// We check if the path is absolute, and convert it to relative.
		if( str_starts_with( $dataset_path, $base_path ) ) {
			$filename     = basename( $dataset_path );
			//We update the source file with the proper one.
			$wpdb->update( $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, [ 'source_path' => $filename ], [ 'id' => $project_id ] );
			return $dataset_path;
		}
		//The path is absolute but is using a different directory, maybe due to server migration or hosting change.
		if( ! str_starts_with( $dataset_path, MPG_UPLOADS_DIR ) ) {
			$filename     = basename( $dataset_path );
			$wpdb->update( $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, [ 'source_path' => $filename ], [ 'id' => $project_id ] );

			return $base_path . $filename;
		}

		return $dataset_path;
	}

	/**
	 * Get project ID by dataset path.
	 * 
	 * @param string $dataset_path The dataset path.
	 * @return int|null The project ID or null if not found.
	 */
	public static function get_project_id_by_dataset_path( $dataset_path ) {
		global $wpdb;
		$filename = basename( $dataset_path );
		$transient_key = 'mpg_project_id_by_dataset_' . md5( $filename );
		$cached_id = get_transient( $transient_key );

		if ( $cached_id !== false ) {
			return (int) $cached_id;
		}

		$project_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . " WHERE source_path=%s", $filename )
		);

		if ( $project_id ) {
			set_transient( $transient_key, (int) $project_id, DAY_IN_SECONDS );
			return (int) $project_id;
		}

		return null;
	}

	/**
	 * Read dataset from file.
	 *
	 * @param string $file
	 * @param bool $headers_only If true, only headers will be read.
	 *
	 * @return array
	 * @throws \Box\Spout\Common\Exception\IOException
	 * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
	 */
	public static function read_dataset_original( string $file, bool $headers_only = false ):array {
		if ( ! $headers_only ) {
			$cached = self::get_cache( $file );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$dataset_array = ! mpg_app()->is_premium() ? new MpgArray( [],mpg_app()->is_legacy_user() ? 300000 : 0 ) : new MpgLargeArray();

		$ext    = MPG_Helper::mpg_get_extension_by_path( $file );
		$reader = MPG_Helper::mpg_get_spout_reader_by_extension( $ext );
		$reader->setShouldFormatDates( true );
		$reader->open( $file );

		try {
			foreach ( $reader->getSheetIterator() as $sheet ) {
				foreach ( $sheet->getRowIterator() as $row ) {
					$row = $row->toArray();
					if ( $row[0] !== null ) {
						if ( $headers_only ) {
							return $row;
						}
						$dataset_array[] = $row;
					}
				}
			}
		} catch ( Exception $e ) {
			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );
		}
		$reader->close();

		$result = $dataset_array->toArray();

		if ( ! $headers_only ) {
			self::set_cache( $file, $result );
		}
		return $result;
	}

	/**
	 * Read dataset from file.
	 *
	 * @param string $file
	 * @param bool $headers_only If true, only headers will be read.
	 * @param int|null $project_id Optional project ID for caching
	 * @param int|null $limit Optional limit on number of rows to read
	 * @param int|null $offset Optional offset to start reading from
	 *
	 * @return array
	 * @throws \Box\Spout\Common\Exception\IOException
	 * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
	 */
	public static function read_dataset( string $file, bool $headers_only = false, $project_id = null, $limit = null, $offset = null ):array {
		if ( $project_id !== null && self::is_dataset_chunked( $project_id ) ) {
			$data = self::get_dataset_chunks( $project_id, $headers_only, $limit, $offset );

			if ( ! empty( $data ) ) {
				return $data;
			}
		}
		
		return self::read_dataset_original( $file, $headers_only );
	}

	/**
	 * Get dataset with the count.
	 * 
	 * @param string $file The dataset file path
	 * @param int|null $project_id Optional project ID for caching
	 * @param int|null $limit Optional limit on number of rows to read
	 * @param int|null $offset Optional offset to start reading from
	 * 
	 * @return array The dataset rows
	 * @throws \Box\Spout\Common\Exception\IOException
	 * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
	 */
	public static function get_dataset( string $file, $project_id = null, $limit = null, $offset = null ) {
		$dataset = self::read_dataset( $file, false, $project_id, $limit, $offset );

		return [
			'data' => $dataset,
			'count' => count( $dataset ),
			'total' => self::get_dataset_row_count( $dataset, $project_id )
		];
	}

	/**
	 * Get the total row count of a dataset.
	 * 
	 * @param array $current_data Current data to avoid re-reading
	 * @param int|null $project_id Project ID for caching
	 * 
	 * @return int The total number of rows in the dataset
	 * @throws \Box\Spout\Common\Exception\IOException
	 * @throws \Box\Spout\Reader\Exception\ReaderNotOpenedException
	 */
	public static function get_dataset_row_count( $current_data, $project_id = null  ) {
		if ( $project_id !== null && self::is_dataset_chunked( $project_id ) ) {
			$chunks_meta = self::get_dataset_chunks_meta( $project_id );
			if ( $chunks_meta !== false && isset( $chunks_meta['total_rows'] ) ) {
				return $chunks_meta['total_rows'];
			}
		}

		if ( is_array( $current_data ) ) {
			return count( $current_data );
		}
	}

	/**
	 * Helper method to write a batch of permalinks to the index file
	 * 
	 * @param resource $file The file handle to write to
	 * @param array $batch The batch of permalinks to write
	 * @param bool $is_first Whether this is the first batch being written
	 * @return void
	 */
	protected static function write_permalink_batch( $file, $batch, $is_first ) {
		if ( empty( $batch ) ) {
			return;
		}
		
		$batch_json = '';
		$first_in_batch = true;
		
		foreach ( $batch as $permalink => $data ) {
			if ( ! $is_first || ! $first_in_batch ) {
				$batch_json .= ',';
			}
			$first_in_batch = false;
			
			$batch_json .= json_encode( $permalink, MPG_JSON_OPTIONS ) . ':' . json_encode( $data, MPG_JSON_OPTIONS );
		}
		
		fwrite( $file, $batch_json );
	}

	/**
	 * Cache dataset for faster access.
	 *
	 * @param int $project_id
	 * @param array $dataset
	 * @param int $expiration
	 *
	 * @return void
	 */
	public static function set_cache( $file, array $dataset ): void {
		$chunk_size   = self::get_chunk_size();
		$total_rows   = count( $dataset );
		$chunk_count  = (int) ceil( $total_rows / $chunk_size );
		$cache_keys   = [];

		$project_id = self::get_project_id_by_dataset_path( $file );

		if ( $project_id ) {
			$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );
			MPG_ProjectModel::update_last_check( $project_id );
		} else {
			$expiration = DAY_IN_SECONDS;
		}

		for ( $i = 0; $i < $chunk_count; $i++ ) {
			$chunk_data = array_slice( $dataset, $i * $chunk_size, $chunk_size );
			$chunk_key  = wp_hash( "mpg_dataset_cache_" . $file . "_" . $i );
			set_transient( $chunk_key, json_encode( $chunk_data, MPG_JSON_OPTIONS ), $expiration );
			$cache_keys[] = $chunk_key;
		}

		// Save metadata
		$meta = [
			'total_rows'  => $total_rows,
			'chunk_count' => $chunk_count,
			'cache_keys'  => $cache_keys
		];

		$meta_key = wp_hash( "mpg_dataset_cache_meta_" . $file );
		set_transient( $meta_key, json_encode( $meta, MPG_JSON_OPTIONS ), $expiration );
	}

	/**
	 * Get cached dataset.
	 *
	 * @param int $project_id
	 *
	 * @return array|false
	 */
	public static function get_cache( $file ) {
		$meta_key = wp_hash( "mpg_dataset_cache_meta_" . $file );
		$meta_json = get_transient( $meta_key );

		if ( $meta_json === false ) {
			return false;
		}

		$meta = json_decode( $meta_json, true );

		if ( !is_array($meta) || empty( $meta['cache_keys'] ) ) {
			return false;
		}

		$dataset = [];

		foreach ( $meta['cache_keys'] as $chunk_key ) {
			$chunk_json = get_transient( $chunk_key );
			if ( $chunk_json !== false ) {
				$chunk = json_decode( $chunk_json, true );
				if ( is_array( $chunk ) ) {
					$dataset = array_merge( $dataset, $chunk );
				}
			}
		}

		return $dataset;
	}

	/**
	 * Delete cached dataset.
	 *
	 * @param int $project_id
	 *
	 * @return void
	 */
	public static function delete_cache( $file ): void {
		$meta_key = wp_hash( "mpg_dataset_cache_meta_" . $file );
		$meta_json = get_transient( $meta_key );
		if ( $meta_json !== false ) {
			$meta = json_decode( $meta_json, true );
			if ( is_array( $meta ) && ! empty( $meta['cache_keys'] ) ) {
				foreach ( $meta['cache_keys'] as $chunk_key ) {
					delete_transient( $chunk_key );
				}
			}
		}
		delete_transient( $meta_key );
	}

	public static function mpg_read_dataset_hub() {
		$path_to_dataset_hub = MPG_DatasetModel::uploads_base_path() . 'temp-dataset_hub.xlsx';

		if ( ! wp_doing_ajax() ) {
			$download_result = MPG_DatasetModel::download_file( MPG_Constant::DATASET_SPREADSHEET_CSV_URL, $path_to_dataset_hub );

			if ( ! $download_result ) {
				do_action( 'themeisle_log_event', MPG_NAME, sprintf( 'Unable to download hub data sheet %s', MPG_Constant::DATASET_SPREADSHEET_CSV_URL ), 'debug', __FILE__, __LINE__ );
				throw new Exception('Unable to download hub data sheet');
			}
		}


		return self::read_dataset( $path_to_dataset_hub );
	}
}