<?php

if (!defined('ABSPATH')) exit;

use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Type;

require_once(realpath(__DIR__ . '/../helpers/Constant.php'));

class MPG_DatasetModel
{

	public static function download_file( $link, $destination_path ): bool {
		try {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;
			$content = $wp_filesystem->get_contents( $link );
			if ( empty( $content ) ) {
				return false;
			}
			// Make dir if not exists.
			if ( ! $wp_filesystem->exists( MPG_UPLOADS_DIR ) ) {
				$wp_filesystem->mkdir( MPG_UPLOADS_DIR, FS_CHMOD_DIR );
			}
			if ( ! $wp_filesystem->exists( dirname($destination_path) ) ) {
				$wp_filesystem->mkdir( dirname($destination_path), FS_CHMOD_DIR );
			}
			// Update project source file.
			$updated = $wp_filesystem->put_contents( $destination_path, $content, FS_CHMOD_FILE );

			// File delete and re-fetch in case of the file is not writeable.
			if ( ! $updated && is_readable( $destination_path ) ) {
				$wp_filesystem->delete( $destination_path );
				$updated = $wp_filesystem->put_contents( $destination_path, $content, FS_CHMOD_FILE );
				return $updated;
			}

			return true;
		} catch ( Exception $e ) {
			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

			return false;
		}
    }


    public static function get_dataset_path_by_project_id($project_id)
    {

        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT source_path FROM {$wpdb->prefix}" .  MPG_Constant::MPG_PROJECTS_TABLE . " WHERE id=%d", $project_id)
        );
        if ( empty( $results ) ) {
        	return '';
        }
        if ( false === strpos( $results[0]->source_path, 'wp-content' ) ) {
            $results[0]->source_path = MPG_UPLOADS_DIR . $results[0]->source_path;
        }
        return $results[0]->source_path;
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
	public static function read_dataset( string $file, bool $headers_only = false ):array {
		$dataset_array = ! mpg_app()->is_premium() ? new MpgArray( [],mpg_app()->is_legacy_user() ? 300000 : 0 ) : new MpgLargeArray();

		$ext           = MPG_Helper::mpg_get_extension_by_path( $file );
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

		return $dataset_array->toArray();
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
	public static function set_cache( int $project_id, array $dataset, int $expiration = 0 ) {
		$key_name = wp_hash( 'dataset_array_' . $project_id );
		set_transient( $key_name, wp_json_encode( $dataset, MPG_JSON_OPTIONS ), $expiration );
		MPG_ProjectModel::update_last_check( $project_id );
	}

	/**
	 * Get cached dataset.
	 *
	 * @param int $project_id
	 *
	 * @return array|false
	 */
	public static function get_cache( int $project_id ) {
		$key_name      = wp_hash( 'dataset_array_' . $project_id );
		//This is a legacy transient, we check this first for those who still use it.
		$dataset_array = get_transient( 'dataset_array_' . $project_id );
		if ( false === $dataset_array ) {
			$dataset = get_transient( $key_name );
			if ( $dataset === false ) {
				return false;
			}
			if ( is_array( $dataset ) ) {
				return $dataset;
			}
			return json_decode( $dataset );
		}

		return false;
	}
	/**
	 * Delete cached dataset.
	 *
	 * @param int $project_id
	 *
	 * @return void
	 */
	public static function delete_cache( int $project_id ) {
		delete_transient( 'dataset_array_' . $project_id );
		delete_transient( wp_hash( 'dataset_array_' . $project_id ) );
	}
	public static function mpg_read_dataset_hub() {
		$path_to_dataset_hub = plugin_dir_path( __DIR__ ) . 'temp/dataset_hub.xlsx';

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