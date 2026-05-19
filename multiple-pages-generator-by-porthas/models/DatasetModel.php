<?php

if (!defined('ABSPATH')) exit;

use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Type;

require_once(realpath(__DIR__ . '/../helpers/Constant.php'));

class MPG_DatasetModel
{
	/**
	 * Holds the build ID resolved for each project during the current request.
	 *
	 * @var array
	 */
	protected static $resolved_build_ids = [];

	/**
	 * Get the chunk size to use for dataset chunking.
	 *
	 * @return int The chunk size
	 */
	public static function get_chunk_size() {
		return apply_filters( 'mpg_chunk_size', 5000 );
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
	 * Get the path to the builds directory for a project.
	 *
	 * @param int $project_id The project ID.
	 * @return string
	 */
	public static function get_builds_dir( $project_id ) {
		return self::get_project_path( $project_id ) . 'builds/';
	}

	/**
	 * Get the path to a specific build directory.
	 *
	 * @param int    $project_id The project ID.
	 * @param string $build_id The build ID.
	 * @return string
	 */
	public static function get_build_path( $project_id, $build_id ) {
		return self::get_builds_dir( $project_id ) . trailingslashit( $build_id );
	}

	/**
	 * Get the path to the manifest that points to the active build.
	 *
	 * @param int $project_id The project ID.
	 * @return string
	 */
	public static function get_active_build_manifest_path( $project_id ) {
		return self::get_project_path( $project_id ) . 'current-build.json';
	}

	/**
	 * Get the path to the index file for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return string The path to the index file
	 */
	public static function get_index_path( $project_id, $build_id = null ) {
		if ( null === $build_id ) {
			$build_id = self::get_active_build_id( $project_id );
		}

		if ( false === $build_id ) {
			return self::get_project_path( $project_id ) . 'index.json';
		}

		return self::get_build_path( $project_id, $build_id ) . 'index.json';
	}

	/**
	 * Get the path to the chunks directory for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return string The path to the chunks directory
	 */
	public static function get_chunks_dir( $project_id, $build_id = null ) {
		if ( null === $build_id ) {
			$build_id = self::get_active_build_id( $project_id );
		}

		if ( false === $build_id ) {
			return self::get_project_path( $project_id ) . 'chunks/';
		}

		return self::get_build_path( $project_id, $build_id ) . 'chunks/';
	}

	/**
	 * Get the path to a specific chunk file.
	 * 
	 * @param int $project_id The project ID
	 * @param int $chunk_number The chunk number
	 * @return string The path to the chunk file
	 */
	public static function get_chunk_path( $project_id, $chunk_number, $build_id = null ) {
		return self::get_chunks_dir( $project_id, $build_id ) . sprintf( 'chunk_%06d.json', $chunk_number );
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
	 * Return the build ID used for reads in the current request.
	 *
	 * @param int $project_id The project ID.
	 * @return string|false
	 */
	public static function get_active_build_id( $project_id ) {
		if ( array_key_exists( $project_id, self::$resolved_build_ids ) ) {
			return self::$resolved_build_ids[ $project_id ];
		}

		$manifest_path = self::get_active_build_manifest_path( $project_id );
		$build_id      = false;

		if ( file_exists( $manifest_path ) ) {
			$manifest_contents = file_get_contents( $manifest_path );
			$manifest_data     = json_decode( $manifest_contents, true );
			if ( isset( $manifest_data['build_id'] ) && is_string( $manifest_data['build_id'] ) ) {
				$manifest_build_id = sanitize_file_name( $manifest_data['build_id'] );
				if ( $manifest_build_id !== '' && file_exists( self::get_index_path( $project_id, $manifest_build_id ) ) ) {
					$build_id = $manifest_build_id;
				}
			}
		}

		self::$resolved_build_ids[ $project_id ] = $build_id;

		return $build_id;
	}

	/**
	 * Create an index for a project's dataset using a streaming approach to minimize memory usage.
	 * 
	 * @param int $project_id The project ID
	 * @return bool Whether the index was created successfully
	 */
	public static function create_index( $project_id, $lock_token = null ) {
		$has_external_lock = is_string( $lock_token ) && '' !== $lock_token;
		if ( ! $has_external_lock ) {
			$lock_token = self::acquire_index_generation_lock( $project_id );
			if ( false === $lock_token ) {
				return false;
			}
		}

		$build_id        = self::generate_index_run_token();
		$build_published = false;

		try {
			if ( ! self::create_dataset_chunks( $project_id, $build_id ) ) {
				return false;
			}

			$chunk_size     = self::get_chunk_size();
			$index_path     = self::get_index_path( $project_id, $build_id );
			$project_path   = self::get_dataset_path_by_project( $project_id );
			$headers        = self::read_dataset( $project_path, true );
			$project        = MPG_ProjectModel::get_project_url_structure_and_space_replacer( $project_id );
			$url_structure  = isset( $project['url_structure'] ) ? $project['url_structure'] : '';
			$space_replacer = isset( $project['space_replacer'] ) ? $project['space_replacer'] : '';

			$chunks_meta  = self::get_dataset_chunks_meta( $project_id, $build_id );
			$total_rows   = isset( $chunks_meta['total_rows'] ) ? $chunks_meta['total_rows'] - 1 : 0;
			$total_chunks = isset( $chunks_meta['total_chunks'] ) ? $chunks_meta['total_chunks'] : 0;

			$index_dir = dirname( $index_path );
			if ( ! file_exists( $index_dir ) ) {
				wp_mkdir_p( $index_dir );
			}

			$run_token            = self::generate_index_run_token();
			$permalinks_temp_path = self::get_index_temp_path( $index_path, 'permalinks', $run_token );
			$index_temp_path      = self::get_index_temp_path( $index_path, 'index', $run_token );
			$permalinks_file      = fopen( $permalinks_temp_path, 'w' );
			if ( ! $permalinks_file ) {
				return false;
			}

			if ( 1 !== fwrite( $permalinks_file, '{' ) ) {
				self::cleanup_on_error( $permalinks_file, $permalinks_temp_path );
				return false;
			}

			$first_entry = true;
			$batch_size  = 1000;

			for ( $chunk_idx = 0; $chunk_idx < $total_chunks; $chunk_idx++ ) {
				$chunk_data = self::get_dataset_chunk( $project_id, $chunk_idx, $build_id );

				if ( empty( $chunk_data ) ) {
					continue;
				}

				$batch_entries      = [];
				$current_batch_size = 0;

				foreach ( $chunk_data as $offset_in_chunk => $row ) {
					if ( $offset_in_chunk === 0 && isset( $chunk_data[0] ) && $chunk_data[0] === $headers ) {
						continue;
					}

					$permalink                   = MPG_ProjectModel::mpg_generate_url_for_row( $row, $headers, $url_structure, $space_replacer );
					$batch_entries[ $permalink ] = [
						'chunk'  => $chunk_idx,
						'offset' => $offset_in_chunk,
					];

					$current_batch_size++;

					if ( $current_batch_size >= $batch_size ) {
						if ( ! self::flush_permalink_batch( $permalinks_file, $batch_entries, $first_entry, $current_batch_size ) ) {
							self::cleanup_on_error( $permalinks_file, $permalinks_temp_path );
							return false;
						}
					}

					unset( $permalink );
				}

				if ( $current_batch_size > 0 ) {
					if ( ! self::flush_permalink_batch( $permalinks_file, $batch_entries, $first_entry, $current_batch_size ) ) {
						self::cleanup_on_error( $permalinks_file, $permalinks_temp_path );
						return false;
					}
				}

				unset( $batch_entries );
				unset( $chunk_data );
				gc_collect_cycles();
			}

			if ( 1 !== fwrite( $permalinks_file, '}' ) ) {
				self::cleanup_on_error( $permalinks_file, $permalinks_temp_path );
				return false;
			}
			fclose( $permalinks_file );

			$index_file = fopen( $index_temp_path, 'w' );
			if ( ! $index_file ) {
				self::cleanup_on_error( null, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			if ( ! self::write_file_fragment( $index_file, '{' ) ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			if ( ! file_exists( $permalinks_temp_path ) ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$permalinks_contents = @file_get_contents( $permalinks_temp_path );
			if ( false === $permalinks_contents ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			$permalinks_contents = json_decode( $permalinks_contents, true );
			$permalinks_contents = is_array( $permalinks_contents ) ? $permalinks_contents : [];

			$meta = [
				'version'         => '1.0',
				'build_id'        => $build_id,
				'total_rows'      => $total_rows,
				'total_chunks'    => $total_chunks,
				'chunk_size'      => $chunk_size,
				'created_at'      => date( 'Y-m-d H:i:s' ),
				'column_headers'  => $headers,
				'permalink_count' => count( $permalinks_contents ),
			];

			$index_data = [
				'meta'    => $meta,
				'indexes' => [
					'permalinks' => $permalinks_contents,
				],
			];

			self::set_dataset_chunks_meta( $project_id, $meta, $build_id );

			if ( ! self::write_file_fragment( $index_file, '"meta":' . json_encode( $meta, MPG_JSON_OPTIONS ) . ',' ) ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			if ( ! self::write_file_fragment( $index_file, '"indexes":{' ) ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			if ( ! self::write_file_fragment( $index_file, '"permalinks":' ) ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			if ( ! self::write_file_fragment( $index_file, json_encode( $index_data['indexes']['permalinks'], MPG_JSON_OPTIONS ) ) ) {
				self::cleanup_on_error( $index_file, $permalinks_temp_path, $index_temp_path );
				return false;
			}

			unlink( $permalinks_temp_path );

			if ( ! self::write_file_fragment( $index_file, '}}' ) ) {
				self::cleanup_on_error( $index_file, null, $index_temp_path );
				return false;
			}

			if ( ! fclose( $index_file ) ) {
				self::cleanup_on_error( null, null, $index_temp_path );
				return false;
			}

			if ( ! self::replace_index_file( $index_temp_path, $index_path ) ) {
				self::cleanup_on_error( null, null, $index_temp_path );
				return false;
			}

			self::set_index_cache( $project_id, $index_data, $build_id );
			if ( ! self::publish_active_build( $project_id, $build_id ) ) {
				return false;
			}

			$build_published = true;
			MPG_ProjectModel::update_last_check( $project_id );

			self::prune_build_artifacts( $project_id, $build_id );

			return true;
		} finally {
			if ( ! $build_published ) {
				self::delete_build_snapshot( $project_id, $build_id );
			}
			if ( ! $has_external_lock ) {
				self::release_index_generation_lock( $project_id, $lock_token );
			}
		}
	}

	/**
	 * Delete the index file for a project.
	 * 
	 * @param int $project_id The project ID
	 * @return bool Whether the index file was deleted
	 */
	public static function delete_index( $project_id, $build_id = null ) {
		$resolved_build_id = self::resolve_build_id_context( $project_id, $build_id );
		$index_path        = self::get_index_path( $project_id, $resolved_build_id );
		self::delete_index_cache( $project_id, $resolved_build_id );

		if ( file_exists( $index_path ) ) {
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
		$build_id      = self::get_active_build_id( $project_id );
		$chunked_index = self::get_index_cache( $project_id, $build_id );

		if ( $chunked_index !== false ) {
			return isset( $chunked_index['indexes'][ $key ] ) ? $chunked_index['indexes'][ $key ] : $chunked_index;
		}

		$index_path = self::get_index_path( $project_id, $build_id );

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
			self::set_index_cache( $project_id, $index_data, $build_id );
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
	public static function set_index_cache( $project_id, $index, $build_id = null ) {
		$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );
		$cache_token = self::get_cache_build_token( $project_id, $build_id );

		$meta_key = wp_hash( "mpg_index_meta_{$project_id}_{$cache_token}" );
		$meta = isset( $index['meta'] ) ? $index['meta'] : [];
		$success = set_transient( $meta_key, $meta, $expiration );

		$permalinks = isset( $index['indexes']['permalinks'] ) ? $index['indexes']['permalinks'] : [];

		if ( ! empty( $permalinks ) ) {
			$chunk_size       = self::get_chunk_size();
			$permalink_chunks = array_chunk( $permalinks, $chunk_size, true ); // Keep keys intact

			$chunks_meta_key = wp_hash( "mpg_index_permalinks_meta_{$project_id}_{$cache_token}" );
			$chunks_meta = [
				'chunk_count' => count( $permalink_chunks ),
				'total_permalinks' => count( $permalinks )
			];
			$success = set_transient( $chunks_meta_key, $chunks_meta, $expiration ) && $success;

			foreach ( $permalink_chunks as $chunk_index => $chunk ) {
				$chunk_key = wp_hash( "mpg_index_permalinks_{$project_id}_{$chunk_index}_{$cache_token}" );
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
	public static function get_index_cache( $project_id, $build_id = null ) {
		$cache_token = self::get_cache_build_token( $project_id, $build_id );
		$meta_key = wp_hash( "mpg_index_meta_{$project_id}_{$cache_token}" );
		$meta = get_transient( $meta_key );
		
		if ( $meta === false ) {
			return false;
		}
		
		$chunks_meta_key = wp_hash( "mpg_index_permalinks_meta_{$project_id}_{$cache_token}" );
		$chunks_meta = get_transient( $chunks_meta_key );
		
		$permalinks = [];
		
		if ( $chunks_meta !== false && isset( $chunks_meta['chunk_count'] ) ) {
			   for ( $i = 0; $i < $chunks_meta['chunk_count']; $i++ ) {
				   $chunk_key = wp_hash( "mpg_index_permalinks_{$project_id}_{$i}_{$cache_token}" );
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
	public static function delete_index_cache( $project_id, $build_id = null ) {
		$cache_token = self::get_cache_build_token( $project_id, $build_id );
		$meta_key = wp_hash( "mpg_index_meta_{$project_id}_{$cache_token}" );
		delete_transient( $meta_key );
		
		$chunks_meta_key = wp_hash( "mpg_index_permalinks_meta_{$project_id}_{$cache_token}" );
		$chunks_meta = get_transient( $chunks_meta_key );
		
		if ( $chunks_meta !== false && isset( $chunks_meta['chunk_count'] ) ) {
			for ( $i = 0; $i < $chunks_meta['chunk_count']; $i++ ) {
				$chunk_key = wp_hash( "mpg_index_permalinks_{$project_id}_{$i}_{$cache_token}" );
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
	public static function create_dataset_chunks( $project_id, $build_id = null ) {
		$chunk_size = self::get_chunk_size();
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

				$chunks_dir = self::get_chunks_dir( $project_id, $build_id );
				if ( ! file_exists( $chunks_dir ) ) {
					wp_mkdir_p( $chunks_dir );
				}

			$chunks_meta = [
				'total_chunks' => $total_chunks,
				'chunk_size' => $chunk_size,
				'total_rows' => $total_rows,
				'created_at' => date( 'Y-m-d H:i:s' ),
			];
			
				self::set_dataset_chunks_meta( $project_id, $chunks_meta, $build_id );

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
							$chunk_path = self::get_chunk_path( $project_id, $current_chunk, $build_id );
							$success = file_put_contents( $chunk_path, json_encode( $current_chunk_data, MPG_JSON_OPTIONS ) );

							if ( $success !== false ) {
								self::set_dataset_chunk_cache( $project_id, $current_chunk, $current_chunk_data, $build_id );
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
				if ( is_string( $build_id ) && '' !== $build_id ) {
					self::delete_build_snapshot( $project_id, $build_id );
				}
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
	public static function get_dataset_chunk( $project_id, $chunk_number, $build_id = null ) {
		$resolved_build_id = self::resolve_build_id_context( $project_id, $build_id );
		$cached_chunk      = self::get_dataset_chunk_cache( $project_id, $chunk_number, $resolved_build_id );
		if ( ! empty( $cached_chunk ) ) {
			return $cached_chunk;
		}
		
		$chunk_path = self::get_chunk_path( $project_id, $chunk_number, $resolved_build_id );

		if ( file_exists( $chunk_path ) ) {
			$chunk_content = file_get_contents( $chunk_path );
			if ( $chunk_content ) {
				$chunk_data = json_decode( $chunk_content, true );
				if ( is_array( $chunk_data ) ) {
					self::set_dataset_chunk_cache( $project_id, $chunk_number, $chunk_data, $resolved_build_id );
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
	public static function get_dataset_chunk_cache( $project_id, $chunk_number, $build_id = null ) {
		$chunk_key = wp_hash( "mpg_dataset_chunk_{$project_id}_{$chunk_number}_" . self::get_cache_build_token( $project_id, $build_id ) );
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
	public static function set_dataset_chunk_cache( $project_id, $chunk_number, $chunk_data, $build_id = null ) {
		$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );
		$chunk_key = wp_hash( "mpg_dataset_chunk_{$project_id}_{$chunk_number}_" . self::get_cache_build_token( $project_id, $build_id ) );
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
	public static function set_dataset_chunks_meta( $project_id, $meta, $build_id = null ) {
		$expiration = MPG_ProjectModel::get_project_schedule_periodicity( $project_id );
		$meta_key = wp_hash( "mpg_dataset_chunks_meta_{$project_id}_" . self::get_cache_build_token( $project_id, $build_id ) );
		return set_transient( $meta_key, $meta, $expiration );
	}

	/**
	 * Get dataset chunks metadata from cache, or fallback to index file's meta section.
	 *
	 * @param int $project_id The project ID
	 * @return array|false The metadata or false if not available
	 */
	public static function get_dataset_chunks_meta( $project_id, $build_id = null ) {
		$resolved_build_id = self::resolve_build_id_context( $project_id, $build_id );
		$meta_key          = wp_hash( "mpg_dataset_chunks_meta_{$project_id}_" . self::get_cache_build_token( $project_id, $resolved_build_id ) );
		$meta = get_transient( $meta_key );
		if ( $meta !== false ) {
			return $meta;
		}
		// Fallback: Try to read from index file's meta section
		$index_path = self::get_index_path( $project_id, $resolved_build_id );
		if ( file_exists( $index_path ) ) {
			$index_content = file_get_contents( $index_path );
			if ( $index_content ) {
				$index_data = json_decode( $index_content, true );
				if ( isset( $index_data['meta'] ) ) {
					self::set_dataset_chunks_meta( $project_id, $index_data['meta'], $resolved_build_id );
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
	public static function get_dataset_chunks( $project_id, $headers_only = false, $limit = null, $offset = 0, $build_id = null ) {
		$rows = [];
		$resolved_build_id = self::resolve_build_id_context( $project_id, $build_id );
		$chunks_meta       = self::get_dataset_chunks_meta( $project_id, $resolved_build_id );

		if ( $chunks_meta === false || !isset( $chunks_meta['total_chunks'] ) ) {
			return $rows;
		}

		$total_chunks = $chunks_meta['total_chunks'];
		$is_first_chunk = true;
		$row_count = 0;
		$current_row_global = 0;
		$needed = ( $limit !== null ) ? $limit : PHP_INT_MAX;

		for ( $chunk_number = 0; $chunk_number < $total_chunks; $chunk_number++ ) {
			$chunk_data = self::get_dataset_chunk( $project_id, $chunk_number, $resolved_build_id );

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
	public static function get_dataset_row( $project_id, $chunk, $offset, $build_id = null ) {
		$chunk_data = self::get_dataset_chunk( $project_id, $chunk, $build_id );

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
	public static function delete_dataset_chunks( $project_id, $build_id = null ) {
		$resolved_build_id = self::resolve_build_id_context( $project_id, $build_id );
		$chunks_dir        = self::get_chunks_dir( $project_id, $resolved_build_id );

		if ( file_exists( $chunks_dir ) ) {
			$files = glob( $chunks_dir . '*.json' );
			foreach ( $files as $file ) {
				unlink( $file );
			}

			rmdir( $chunks_dir );
		}

		self::delete_dataset_chunks_cache( $project_id, $resolved_build_id );
	}

	/**
	 * Delete cached transients for dataset chunks.
	 *
	 * @param int          $project_id The project ID.
	 * @param string|false $build_id   The build ID or false for legacy layout.
	 * @return void
	 */
	protected static function delete_dataset_chunks_cache( $project_id, $build_id ) {
		$chunks_meta  = self::get_dataset_chunks_meta( $project_id, $build_id );
		$total_chunks = ( $chunks_meta !== false && isset( $chunks_meta['total_chunks'] ) )
			? $chunks_meta['total_chunks']
			: 0;

		for ( $chunk_number = 0; $chunk_number < $total_chunks; $chunk_number++ ) {
			$chunk_key = wp_hash( "mpg_dataset_chunk_{$project_id}_{$chunk_number}_" . self::get_cache_build_token( $project_id, $build_id ) );
			delete_transient( $chunk_key );
		}

		$meta_key = wp_hash( "mpg_dataset_chunks_meta_{$project_id}_" . self::get_cache_build_token( $project_id, $build_id ) );
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
		$builds_dir   = self::get_builds_dir( $project_id );

		self::delete_dataset_chunks( $project_id, false );
		self::delete_index( $project_id, false );

		if ( file_exists( $builds_dir ) ) {
			$build_paths = glob( $builds_dir . '*' );
			if ( is_array( $build_paths ) ) {
				foreach ( $build_paths as $build_path ) {
					if ( is_dir( $build_path ) ) {
						self::delete_build_snapshot( $project_id, basename( $build_path ) );
					}
				}
			}

			@rmdir( $builds_dir );
		}

		$manifest_path = self::get_active_build_manifest_path( $project_id );
		if ( file_exists( $manifest_path ) ) {
			unlink( $manifest_path );
		}

		delete_option( self::get_index_generation_lock_key( $project_id ) );

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
			if ( $cached !== false && ! empty( $cached ) ) {
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
	 * Clean up temporary files and close file handles on error
	 *
	 * @param resource|false|null $file_handle The file handle to close (optional)
	 * @param string|null $temp_file_path Path to temporary file to delete (optional)
	 * @param string|null $index_file_path Path to index file to delete (optional)
	 * @return void
	 */
	protected static function cleanup_on_error( $file_handle = null, $temp_file_path = null, $index_file_path = null ) {
		if ( $file_handle && is_resource( $file_handle ) ) {
			fclose( $file_handle );
		}
		if ( $temp_file_path && file_exists( $temp_file_path ) ) {
			unlink( $temp_file_path );
		}
		if ( $index_file_path && file_exists( $index_file_path ) ) {
			unlink( $index_file_path );
		}
	}

	/**
	 * Helper method to write a string to a file handle and verify all bytes were written.
	 *
	 * @param resource $file The file handle to write to.
	 * @param string   $content The content to write.
	 * @return bool True when the complete string was written, false otherwise.
	 */
	protected static function write_file_fragment( $file, $content ) {
		$expected_bytes = strlen( $content );
		$written_bytes  = fwrite( $file, $content );

		return $written_bytes === $expected_bytes;
	}

	/**
	 * Flush a permalink batch and advance the JSON object state.
	 *
	 * @param resource $file The file handle to write to.
	 * @param array    $batch The current batch of permalinks.
	 * @param bool     $is_first Whether the next write is still the first JSON entry.
	 * @param int      $current_batch_size Number of entries currently buffered.
	 * @return bool
	 */
	protected static function flush_permalink_batch( $file, array &$batch, &$is_first, &$current_batch_size ) {
		if ( ! self::write_permalink_batch( $file, $batch, $is_first ) ) {
			return false;
		}

		$is_first           = false;
		$batch              = [];
		$current_batch_size = 0;

		return true;
	}

	/**
	 * Generate a per-run token for locks and temporary files.
	 *
	 * @return string
	 */
	protected static function generate_index_run_token() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		return uniqid( 'mpg_', true );
	}

	/**
	 * Build a unique temporary path for an index generation run.
	 *
	 * @param string $index_path Base index path.
	 * @param string $label Temp file label.
	 * @param string $run_token Unique run token.
	 * @return string
	 */
	protected static function get_index_temp_path( $index_path, $label, $run_token ) {
		return sprintf( '%s.%s.%s.tmp', $index_path, $label, $run_token );
	}

	/**
	 * Atomically replace the live index file with a completed temp file.
	 *
	 * @param string $source_path The completed temp file.
	 * @param string $destination_path The live index path.
	 * @return bool
	 */
	protected static function replace_index_file( $source_path, $destination_path ) {
		return rename( $source_path, $destination_path );
	}

	/**
	 * Acquire a project rebuild lock for callers that need to update config and publish atomically.
	 *
	 * @param int $project_id The project ID.
	 * @return string|false
	 */
	public static function begin_index_generation( $project_id ) {
		return self::acquire_index_generation_lock( $project_id );
	}

	/**
	 * Release a project rebuild lock previously acquired by begin_index_generation().
	 *
	 * @param int    $project_id The project ID.
	 * @param string $lock_token The lock token.
	 * @return void
	 */
	public static function end_index_generation( $project_id, $lock_token ) {
		self::release_index_generation_lock( $project_id, $lock_token );
	}

	/**
	 * Acquire a per-project lock before rebuilding the index.
	 *
	 * @param int $project_id The project ID.
	 * @return string|false Lock token on success, false when the lock is already held.
	 */
	protected static function acquire_index_generation_lock( $project_id, $allow_recovery = true ) {
		$lock_key   = self::get_index_generation_lock_key( $project_id );
		$lock_token = self::generate_index_run_token();
		$lock_value = wp_json_encode(
			[
				'token'      => $lock_token,
				'expires_at' => time() + self::get_index_generation_lock_ttl(),
			]
		);

		if ( add_option( $lock_key, $lock_value, '', false ) ) {
			return $lock_token;
		}

		$current_lock = self::parse_index_generation_lock_value( get_option( $lock_key ) );
		if ( $allow_recovery && ( empty( $current_lock ) || self::is_index_generation_lock_expired( $current_lock ) ) ) {
			delete_option( $lock_key );

			return self::acquire_index_generation_lock( $project_id, false );
		}

		return false;
	}

	/**
	 * Release a previously acquired per-project index lock.
	 *
	 * @param int    $project_id The project ID.
	 * @param string $lock_token The lock token.
	 * @return void
	 */
	protected static function release_index_generation_lock( $project_id, $lock_token ) {
		$current_lock = self::parse_index_generation_lock_value( get_option( self::get_index_generation_lock_key( $project_id ) ) );

		if ( isset( $current_lock['token'] ) && is_string( $current_lock['token'] ) && hash_equals( $current_lock['token'], $lock_token ) ) {
			delete_option( self::get_index_generation_lock_key( $project_id ) );
		}
	}

	/**
	 * Get the option key used for the per-project index lock.
	 *
	 * @param int $project_id The project ID.
	 * @return string
	 */
	protected static function get_index_generation_lock_key( $project_id ) {
		return 'mpg_index_lock_' . absint( $project_id );
	}

	/**
	 * Return the lock TTL for index generation.
	 *
	 * @return int
	 */
	protected static function get_index_generation_lock_ttl() {
		return (int) apply_filters( 'mpg_index_lock_ttl', 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Publish the completed build as the active snapshot for future requests.
	 *
	 * @param int $project_id The project ID.
	 * @return bool
	 */
	protected static function publish_active_build( $project_id, $build_id ) {
		$manifest_path = self::get_active_build_manifest_path( $project_id );

		// Capture the previous build ID before overwriting the manifest.
		$previous_build_id = null;
		if ( file_exists( $manifest_path ) ) {
			$old_manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
			if ( isset( $old_manifest['build_id'] ) && is_string( $old_manifest['build_id'] ) ) {
				$previous_build_id = sanitize_file_name( $old_manifest['build_id'] );
			}
		}

		$temp_path     = self::get_index_temp_path( $manifest_path, 'manifest', self::generate_index_run_token() );
		$manifest_file = fopen( $temp_path, 'w' );

		if ( ! $manifest_file ) {
			return false;
		}

		$manifest_contents = wp_json_encode(
			[
				'build_id'    => $build_id,
				'published_at' => time(),
			],
			MPG_JSON_OPTIONS
		);

		if ( ! self::write_file_fragment( $manifest_file, $manifest_contents ) ) {
			self::cleanup_on_error( $manifest_file, $temp_path );
			return false;
		}

		if ( ! fclose( $manifest_file ) ) {
			self::cleanup_on_error( null, $temp_path );
			return false;
		}

		$published = rename( $temp_path, $manifest_path );
		if ( $published ) {
			unset( self::$resolved_build_ids[ $project_id ] );

			// Clean up transient caches for the previous build.
			if ( null !== $previous_build_id && '' !== $previous_build_id && $previous_build_id !== $build_id ) {
				self::delete_index_cache( $project_id, $previous_build_id );
				self::delete_dataset_chunks_cache( $project_id, $previous_build_id );
			}
		}

		return $published;
	}

	/**
	 * Prune stale build artifacts after a new snapshot has been published.
	 *
	 * Deletes orphaned temp files and inactive build directories that are
	 * older than the stale artifact TTL. The active build is never deleted.
	 *
	 * @param int         $project_id The project ID.
	 * @param string|null $active_build_id The currently published build ID.
	 * @return void
	 */
	public static function prune_build_artifacts( $project_id, $active_build_id = null ) {
		$safe_active_id = is_string( $active_build_id ) && '' !== $active_build_id
			? sanitize_file_name( $active_build_id )
			: null;

		$prune_before = time() - self::get_stale_artifact_ttl();
		$project_path = self::get_project_path( $project_id );
		$builds_dir   = self::get_builds_dir( $project_id );

		// Clean stale temp files in the project root.
		$tmp_files = glob( $project_path . '*.tmp' );
		if ( is_array( $tmp_files ) ) {
			foreach ( $tmp_files as $tmp_file ) {
				if ( is_file( $tmp_file ) ) {
					$mtime = filemtime( $tmp_file );
					if ( false === $mtime || $mtime < $prune_before ) {
						@unlink( $tmp_file );
					}
				}
			}
		}

		// Clean stale inactive build directories.
		$build_paths = glob( $builds_dir . '*' );
		if ( ! is_array( $build_paths ) ) {
			return;
		}

		foreach ( $build_paths as $build_path ) {
			if ( ! is_dir( $build_path ) ) {
				continue;
			}

			$build_id = basename( $build_path );
			if ( null !== $safe_active_id && $build_id === $safe_active_id ) {
				continue;
			}

			$mtime = filemtime( $build_path );
			if ( false !== $mtime && $mtime >= $prune_before ) {
				continue;
			}

			self::delete_build_snapshot( $project_id, $build_id );
		}
	}

	/**
	 * Resolve the build context for cache and path helpers.
	 *
	 * @param int              $project_id The project ID.
	 * @param string|false|null $build_id Explicit build ID or false for legacy layout.
	 * @return string|false
	 */
	protected static function resolve_build_id_context( $project_id, $build_id = null ) {
		return null === $build_id ? self::get_active_build_id( $project_id ) : $build_id;
	}

	/**
	 * Return the cache token for the given build context.
	 *
	 * @param int              $project_id The project ID.
	 * @param string|false|null $build_id Explicit build ID or false for legacy layout.
	 * @return string
	 */
	protected static function get_cache_build_token( $project_id, $build_id = null ) {
		$resolved_build_id = self::resolve_build_id_context( $project_id, $build_id );

		return false === $resolved_build_id ? 'legacy' : sanitize_file_name( $resolved_build_id );
	}

	/**
	 * Delete a staged build snapshot and its caches.
	 *
	 * @param int    $project_id The project ID.
	 * @param string $build_id The build ID.
	 * @return void
	 */
	protected static function delete_build_snapshot( $project_id, $build_id ) {
		if ( ! is_string( $build_id ) || '' === $build_id ) {
			return;
		}

		self::delete_index_cache( $project_id, $build_id );
		self::delete_dataset_chunks_cache( $project_id, $build_id );

		$build_path = self::get_build_path( $project_id, $build_id );
		if ( file_exists( $build_path ) ) {
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			$wp_filesystem->delete( $build_path, true );
		}
	}

	/**
	 * Return how long stale artifacts must age before pruning.
	 *
	 * @return int Seconds. Minimum is HOUR_IN_SECONDS.
	 */
	protected static function get_stale_artifact_ttl() {
		return max( HOUR_IN_SECONDS, (int) apply_filters( 'mpg_stale_artifact_ttl', HOUR_IN_SECONDS ) );
	}

	/**
	 * Parse raw lock option data into an array.
	 *
	 * @param mixed $lock_value Raw option value.
	 * @return array
	 */
	protected static function parse_index_generation_lock_value( $lock_value ) {
		$decoded = json_decode( (string) $lock_value, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Check whether a stored index lock has expired.
	 *
	 * @param array $lock_data Parsed lock data.
	 * @return bool
	 */
	protected static function is_index_generation_lock_expired( array $lock_data ) {
		return ! isset( $lock_data['expires_at'] ) || (int) $lock_data['expires_at'] < time();
	}

	/**
	 * Helper method to write a batch of permalinks to the provided file handle.
	 *
	 * @param resource $file The file handle to write to.
	 * @param array    $batch The batch of permalinks to write.
	 * @param bool     $is_first Whether this is the first batch being written.
	 * @return bool True on success, false on failure
	 */
	protected static function write_permalink_batch( $file, $batch, $is_first ) {
		if ( empty( $batch ) ) {
			return true;
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

		return self::write_file_fragment( $file, $batch_json );
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

	/**
	 * Checks if the source file exists and attempts to regenerate the index from it.
	 * 
	 * @param int    $project_id   The project ID.
	 * @param object $project      The project object.
	 * @return bool True if the index was successfully regenerated, false otherwise.
	 */
	public static function regenerate_index( $project_id, $project ) {
		$build_id   = self::get_active_build_id( $project_id );
		$index_path = self::get_index_path( $project_id, $build_id );
		$success    = false;

		if ( file_exists( $index_path ) ) {
			return true;
		}

		$source_type = $project->source_type ?? '';
		if ( 'upload_file' !== $source_type ) {
			return false;
		}

		$source_path = self::get_dataset_path_by_project( $project );
		if ( empty( $source_path ) || ! file_exists( $source_path ) ) {
			MPG_LogsController::mpg_write(
				$project_id,
				'error',
				sprintf(
					// translators: %1$d is the project ID, %2$s is the expected source file path.
					esc_html__( 'Source file is missing for project #%1$d at path: %2$s', 'multiple-pages-generator-by-porthas' ),
					$project_id,
					$source_path
				)
			);
			return false;
		}

		try {
			$index_generated = self::create_index( $project_id );
			
			if ( ! $index_generated ) {
				clearstatcache( true, $index_path );
 				if ( file_exists( $index_path ) ) {
 					$index_generated = true;
 				} else {
 					MPG_LogsController::mpg_write(
 						$project_id,
 						'info',
 						esc_html__( 'Index regeneration is already in progress for this project.', 'multiple-pages-generator-by-porthas' )
 					);
 					return true;
 				}
			}

			// Preserve the backward-compatibility marker without rereading the dataset.
 			// The index was just regenerated and already contains the permalink data needed
 			// for self-heal, so avoid rebuilding URLs from the source file here.
			$url_structure = $project->url_structure ?? '';
			
			if ( ! empty( $url_structure ) ) {
				$fields_array = [
 					'urls_array' => true,
 				];
 				MPG_ProjectModel::mpg_update_project_by_id( $project_id, $fields_array, true );
			}

			self::clear_project_cache( $project_id );

			$success = true;
			
			MPG_LogsController::mpg_write(
				$project_id,
				'info',
				esc_html__( 'Index successfully regenerated from source file.', 'multiple-pages-generator-by-porthas' )
			);
		} catch ( Exception $e ) {
			MPG_LogsController::mpg_write(
				$project_id,
				'error',
				sprintf(
					// translators: %1$d is the project ID, %2$s is the exception message.
					esc_html__( 'Exception during regeneration for project #%1$d: %2$s', 'multiple-pages-generator-by-porthas' ),
					$project_id,
					$e->getMessage()
				)
			);
		}

		return $success;
	}

	/**
	 * Clear all cached data for a project to force fresh reads.
	 * 
	 * @param int $project_id The project ID.
	 */
	public static function clear_project_cache( $project_id ) {
		if ( isset( self::$resolved_build_ids[ $project_id ] ) ) {
			unset( self::$resolved_build_ids[ $project_id ] );
		}

		$build_id = self::get_active_build_id( $project_id );

		self::delete_index_cache( $project_id, $build_id );
		self::delete_dataset_chunks_cache( $project_id, $build_id );
	}
}
