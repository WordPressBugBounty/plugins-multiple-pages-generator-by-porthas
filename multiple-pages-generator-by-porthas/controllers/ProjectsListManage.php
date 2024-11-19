<?php
/**
 * Manage project list.
 *
 * @package MPG
 */

// If check class exists or not.
if ( ! class_exists( 'ProjectsListManage' ) ) {

	/**
	 * Declare class `ProjectsListManage`
	 */
	class ProjectsListManage {

		/**
		 * Display form Data
		 *
		 * @param string $search Search string.
		 * @param int    $per_page per page.
		 * @return mix.
		 */
		public function projects_list( $search = '', $per_page = 20 ) {
			global $wpdb;
			$where = '';
			if ( ! empty( $search ) ) {
				$search = preg_replace( '/[^A-Za-z0-9\-]/', '', $search );
				$search = $wpdb->esc_like( $search );
				$where .= " WHERE name LIKE '%$search%'";
			}
			$orderby = 'ORDER BY name DESC';
			$paged   = isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] - 1 ) * $per_page ) : 0;
			if ( isset( $_GET['_mpg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_mpg_nonce'] ) ), MPG_BASENAME ) ) {
				if ( ! empty( $_GET['orderby'] ) && ! empty( $_GET['order'] ) ) {
					$get_orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
					$order       = strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) );
					if ( in_array( $get_orderby, array( 'name', 'created_at' ), true ) && in_array( $order, array( 'DESC', 'ASC' ), true ) ) {
						$orderby = "ORDER by $get_orderby $order";
					}
				}
			}
			$where     .= sprintf( ' %s LIMIT %d OFFSET %d', $orderby, $per_page, $paged );
			$table_name = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
			$retrieve_data = $wpdb->get_results( "SELECT * FROM $table_name" . $where );
			return $retrieve_data;
		}

		/**
		 * Total Projects
		 *
		 * @return object.
		 */
		public function total_projects() {
			global $wpdb;
			$table_name = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
			$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
			$where      = '';
			if ( ! empty( $search ) ) {
				$search = preg_replace( '/[^A-Za-z0-9\-]/', '', $search );
				$search = $wpdb->esc_like( $search );
				$where .= " WHERE name LIKE '%$search%'";
			}
			$total_projects = $wpdb->get_results( "SELECT COUNT(*) as count FROM $table_name" . $where ); // phpcs:ignore
			$total_projects = reset( $total_projects );
			return (int) $total_projects->count;
		}

		/**
		 * Delete record by id
		 *
		 * @param int $del_id Id.
		 * @return true.
		 */
		public function delete_project( $project_id ) {



			if ( ! $project_id ) {
				throw new Exception( __( 'Project ID is missing', 'mpg' ) );
			}

			$project = MPG_ProjectModel::get_project_by_id( $project_id );
			if ( ! empty( $project ) ) {
				MPG_ProjectModel::deleteFileByPath( MPG_DatasetModel::get_dataset_path_by_project( $project ) );
			}

			if ( ! empty( $project->sitemap_filename ) ) {
				foreach (
					[
						ABSPATH . $project->sitemap_filename . '.xml',
						ABSPATH . $project->sitemap_filename . '-index.xml'
					] as $path
				) {
					if ( file_exists( $path ) ) {
						unlink( $path );
					}
				}

				// Но если есть ...-index, то сделовательно, есть и дочерние файлы, которые тоже надо "подчистить"
				$name = str_replace( '-index', '', $project->sitemap_filename );

				foreach ( glob( ABSPATH . $name . '*.xml' ) as $path ) {
					if ( file_exists( $path ) ) {
						unlink( $path );
					}
				}
			}

			// Удаляем крон-задачу, если есть
			if ( $project->schedule_source_link && $project->schedule_notificate_about && $project->schedule_periodicity && $project->schedule_notification_email ) {
				MPG_ProjectModel::mpg_remove_cron_task_by_project_id( $project_id, [ $project ] ); // we are using [project] just to maintain compatibility with the function.
			}

			if ( $project->exclude_in_robots ) {
				// Удаляем ссылку на страницу-шаблон, если она есть.
				MPG_ProjectModel::mpg_processing_robots_txt( false, $project->template_id );
			}

			if ( $project->sitemap_url ) {
				// Удалим карту сайта из robots.txt
				MPG_ProjectModel::mpg_remove_sitemap_from_robots( $project->sitemap_url );
			}


			global $wpdb;
			$table_name = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
			$wpdb->delete( $table_name, array( 'id' => $project_id ) ); // phpcs:ignore

			// Удаляем все строки для текущего проекта из БД (Spintax)
			MPG_SpintaxModel::flush_cache_by_project_id( $project_id );

			return true;
		}

		/**
		 * Clone a project.
		 *
		 * @param int $project_id
		 *
		 * @return int
		 */
		public function clone_project( int $project_id ) :int {
			global $wpdb;
			$table = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
			// Get column names except for the 'id' column
			$columns_query = $wpdb->get_results( "SHOW COLUMNS FROM {$table}" );

			$columns = array();
			foreach ( $columns_query as $column ) {
				if ( $column->Field !== 'id' ) { // Exclude the 'id' column (auto-increment)
					$columns[] = $column->Field;
				}
			}

			$columns_list = implode( ', ', $columns );

			$original_row = $wpdb->get_row( $wpdb->prepare( "SELECT {$columns_list} FROM {$table} WHERE id = %d", $project_id ), ARRAY_A );

			if ( $original_row ) {
				// Modify the 'name' column to add the "clone of #id" suffix
				$original_row['name'] .= ' ' .sanitize_text_field( sprintf( __( '(clone of #%d)', 'mpg' ), $project_id ) );
				$original_path = MPG_DatasetModel::get_dataset_path_by_project( $project_id );
				$original_row['source_path'] = '';
				// Insert the cloned row into the table
				$wpdb->insert( $table, $original_row );

				// Get the ID of the newly inserted row
				$new_id = $wpdb->insert_id;
				$destination = MPG_ProjectModel::clone_dataset_file( $original_path, $new_id );
				$wpdb->update( $table, array( 'source_path' => $destination ), array( 'id' => $new_id ) );
				return $new_id;
			}

			return 0;
		}
		/**
		 * Bulk delete
		 *
		 * @param array $ids ids.
		 */
		public function bulk_delete( array $ids ) {
			foreach ( $ids as $id ) {
				$this->delete_project( (int )$id );
			}
		}

		/**
		 * Export project.
		 *
		 * @param int $project_id Project IDs.
		 */
		public function export_projects( $project_id = 0 ) {
			global $wpdb;
			if ( ! $project_id ) {
				return 0;
			}
			$project_data = \MPG_ProjectModel::get_project_by_id( $project_id );
			if ( empty( $project_data ) ) {
				return 0;
			}
			$upload_files   = array();
			$exclude_fields = array(
				'id',
				'urls_array',
				'sitemap_url',
				'created_at',
				'updated_at',
				'headers',
			);

			foreach ( $exclude_fields as $exclude_field ) {
				if ( isset( $project_data->$exclude_field ) ) {
					unset( $project_data->$exclude_field );
				}
			}

			if ( ! empty( $project_data->template_id ) ) {
				$project_data->template_name = get_the_title( $project_data->template_id );
				unset( $project_data->template_id );
			}

			$source_path = false;
			if ( ! empty( $project_data->source_path ) ) {
				$source_path = MPG_DatasetModel::get_dataset_path_by_project( $project_data );
				unset( $project_data->source_path );
			}

			if ( isset( $project_data->source_type ) && 'upload_file' === $project_data->source_type ) {
				$filename = 'mpg-export-' . time() . '.zip';

				// Create a new ZIP archive.
				$zip = new ZipArchive();

				// Bail if ZIP file couldn't be created.
				if ( $zip->open( $filename, ZipArchive::CREATE ) !== true ) {
					return 0;
				}

				$zip->addFromString( 'mpg-export.json', wp_json_encode( $project_data, JSON_PRETTY_PRINT ) );
				if ( $source_path ) {
					$extension      = pathinfo( $source_path, PATHINFO_EXTENSION );
					$local_filename = isset( $project_data->name ) ? sanitize_title( $project_data->name ) : '';
					$local_filename = $local_filename . '.' . $extension;
					$zip->addFile( $source_path, $local_filename );
				}
				$zip->close();
				// Output ZIP data, prompting the browser to auto download as a ZIP file now.
				header( 'Content-type: application/zip' );
				header( 'Content-Disposition: attachment; filename=' . $filename );
				header( 'Pragma: no-cache' );
				header( 'Expires: 0' );
				readfile( $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				unlink( $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			} elseif ( isset( $project_data->source_type ) && 'direct_link' === $project_data->source_type ) {
				$project_data = wp_json_encode( $project_data, JSON_PRETTY_PRINT );
				$filename     = 'mpg-export-' . time() . '.json';
				header( 'Content-Type: application/json' );
				header( 'Content-Disposition: attachment; filename=' . $filename );
				header( 'Content-Length: ' . strlen( $project_data ) );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $project_data;
			}
			exit();
		}

		/**
		 * Import projects.
		 */
		public function import_projects() {
			global $wpdb;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$mpg_import = isset( $_FILES['mpg_import'] ) ? $_FILES['mpg_import'] : array();
			if ( empty( $mpg_import['name'] ) ) {
				return 0;
			}

			$json_file = $mpg_import['tmp_name'];

			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;

			$import_data = $wp_filesystem->get_contents( $json_file );
			$import_data = $import_data ? json_decode( $import_data, true ) : array();
			if ( empty( $import_data ) ) {
				wp_die( __( 'Invalid Project JSON file', 'mpg' ) );
			}
			$template_id = 0;

			if ( ! empty( $import_data['template_name'] ) ) {
				$template_name = str_replace( '&#8217;', "'", $import_data['template_name'] );
				$template_id   = post_exists( $template_name, '', '', $import_data['entity_type'] );
				if ( ! $template_id ) {
					$template_id = wp_insert_post(
						array(
							'post_title'  => $template_name,
							'post_type'   => $import_data['entity_type'],
							'post_status' => 'publish',
						)
					);
				}
				unset( $import_data['template_name'] );
			}
			$import_data['template_id'] = $template_id;

			$table = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $import_data );
			$new_id     = $wpdb->insert_id;
			$urls_array = array();

			if ( isset( $import_data['source_type'] ) && 'direct_link' === $import_data['source_type'] ) {
				$original_file_url = isset( $import_data['original_file_url'] ) ? $import_data['original_file_url'] : '';

				$worksheet_id      = ! empty( $import_data['worksheet_id'] ) ? $import_data['worksheet_id'] : '';
				$original_file_url = MPG_Helper::mpg_get_direct_csv_link( $original_file_url, $worksheet_id );
				$ext               = MPG_Helper::mpg_get_extension_by_path( $original_file_url );
				$destination       = MPG_DatasetModel::uploads_base_path() . $new_id . '.' . $ext;
				$download_dataset = MPG_DatasetModel::download_file( $original_file_url, $destination );
				if ( ! $download_dataset ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					do_action( 'themeisle_log_event', MPG_NAME, sprintf( 'Unable to download file = %s', print_r( $destination, true ) ), 'debug', __FILE__, __LINE__ );
				}
				$urls_array = MPG_ProjectModel::mpg_generate_urls_from_dataset( $destination, $import_data['url_structure'], $import_data['space_replacer'] );

				$update_options_array = array( 'source_path' => basename( $destination ), 'urls_array' => wp_json_encode( $urls_array ) );

				MPG_ProjectModel::mpg_update_project_by_id( $new_id, $update_options_array );
			}
			return 1;
		}
	}
}
