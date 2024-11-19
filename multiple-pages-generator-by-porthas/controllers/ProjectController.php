<?php

require_once(realpath(__DIR__) . '/../controllers/DatasetController.php');

require_once(realpath(__DIR__ . '/../views/project-builder/index.php'));

require_once(realpath(__DIR__) . '/../models/ProjectModel.php');
require_once(realpath(__DIR__) . '/../models/SitemapModel.php');
require_once(realpath(__DIR__) . '/../models/DatasetModel.php');


class MPG_ProjectController
{

    public static function builder()
    {
        // Сначала даем возможность пользователю выбрать тип сущности, с которой он хочет работать,
        // а уже потом, когда он выберет, ajax'ом подгрузим записи которые в нем есть,
        // чтобы не создавать зависимых списков

        if ( ! empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'edit_project', 'from_scratch' ), true ) ) {
	        if ( ! mpg_app()->can_edit() ) {
		        echo sprintf('<meta http-equiv="refresh" content="1; URL=%s" /> ',esc_url( admin_url( 'admin.php?page=mpg-project-builder' ) ));

                return;
	        }
            $entities_array = MPG_ProjectModel::mpg_get_custom_types();
	        if ( $_GET['action'] === 'edit_project' ) {
		        if ( ! mpg_app()->is_license_of_type( 2 ) ) {
			        do_action( 'themeisle_sdk_load_banner', 'mpg' );
		        }
	        }
            MPG_ProjectBuilderView::render( $entities_array );
            return;
        }

       
        if ( ! mpg_app()->is_license_of_type(2) ) {
            do_action( 'themeisle_sdk_load_banner', 'mpg' );
        }

        // Display project list table.
        $projects_list = new Projects_List_Table();
        require_once plugin_dir_path( __FILE__ ) . '../views/projects-list/projects.php';
    }


    public static function mpg_upsert_project_main()
    {


	    MPG_Validators::nonce_check();
        try {

            if (isset($_POST['projectName']) && isset($_POST['entityType']) && isset($_POST['templateId'])) {

                $project_id            = isset($_POST['projectId'])      ?        (int) $_POST['projectId'] : null;
                $project_name          = $_POST['projectName']           ?        sanitize_text_field($_POST['projectName']) :  __('New project', 'mpg');
                $entity_type           =                                          sanitize_text_field($_POST['entityType']);
                $template_id           = (int) $_POST['templateId'];
                $apply_condition       = $_POST['applyCondition']        ?        sanitize_text_field($_POST['applyCondition']) : null;
                $participate_in_search = $_POST['participateInSearch']   ?        filter_var($_POST['participateInSearch'], FILTER_VALIDATE_BOOLEAN)  : false;

                $participate_in_default_loop = isset( $_POST['participateInDefaultLoop'] ) ? filter_var( wp_unslash( $_POST['participateInDefaultLoop'] ), FILTER_VALIDATE_BOOLEAN ) : false;

                // Приводим строку к Boolean типу.
                $exclude_in_robots = isset($_POST['excludeInRobots']) ? filter_var($_POST['excludeInRobots'], FILTER_VALIDATE_BOOLEAN) : false;

                MPG_ProjectModel::mpg_processing_robots_txt($exclude_in_robots, $template_id);

                // Если с фронта пришел project_id - значит это update, если null - значит создаем новый проект

                if ($project_id) {


                    $fields_array = [
                        'name' => $project_name,
                        'entity_type' => $entity_type,
                        'template_id' => $template_id,
                        'apply_condition' => $apply_condition,
                        'exclude_in_robots' => $exclude_in_robots,
                        'participate_in_search' => $participate_in_search,
                        'participate_in_default_loop' => $participate_in_default_loop
                    ];

                    MPG_ProjectModel::mpg_update_project_by_id($project_id, $fields_array);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'projectId' => $project_id
                        ]
                    ]);
                } else {

                    // Ставим дефолтное название проекту, задаем created_at и updated_at время, и другие нужные данные
                    $project_id = MPG_ProjectModel::mpg_create_base_carcass($project_name, $entity_type, $template_id, $exclude_in_robots);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'projectId' => $project_id
                        ]
                    ]);
                }
            }
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }


	/**
     * Return the project path where the file will be uploaded.
     *
	 * @param $project_id
	 * @param $path
	 *
	 * @return string
	 */
	public static function get_project_path( int $project_id, string $path ): string {

		$ext = MPG_Helper::mpg_get_extension_by_path( $path );

		return MPG_DatasetModel::uploads_base_path() . $project_id . '.' . $ext;
	}

	public static function mpg_upsert_project_source_block() {

		MPG_Validators::nonce_check();

		try {

			$project_id  = isset( $_POST['projectId'] ) ? (int) $_POST['projectId'] : null;
			$type = MPG_Validators::validate_source_type( $_POST['type'] ?? '' );
			$folder_path = isset( $_POST['path'] ) ? sanitize_text_field( $_POST['path'] ) : null;

			$ext = MPG_Helper::mpg_get_extension_by_path( $folder_path );
			if ( ! in_array( $ext, [ 'csv', 'xls', 'xlsx', 'ods' ], true ) ) {
				throw new Exception( __( 'Unsupported file extension', 'mpg' ) );
			}
			// Ensure the file is within the intended directory and is readable.
			if ( ! $folder_path || ( ! is_readable( $folder_path ) || ! str_contains( $folder_path, MPG_DatasetModel::uploads_base_path() ) ) ) {
				throw new Exception( __( 'The file could not be uploaded. Double-check the file format and size, then try again.', 'mpg' ) );
			}
			$headers = MPG_DatasetController::get_headers( $folder_path );
			if ( empty( $headers ) || ! is_array( $headers ) ) {
				throw new Exception( __( 'The CSV file contains empty or invalid headers. Please check and ensure all headers are correct.', 'mpg' ) );
			}

			$rows = MPG_DatasetController::get_rows( $folder_path, 5 );
			if ( empty( $rows ) || ! is_array( $rows ) ) {
				throw new Exception( __( 'Some rows in the file are invalid. Double-check the data and try uploading once more.', 'mpg' ) );
			}

            $sanitized_filename = sanitize_file_name( basename( $folder_path ) );
			$new_path = self::get_project_path( $project_id, $sanitized_filename );

            $project = MPG_ProjectModel::get_project_by_id($project_id);
            $url_structure = ! empty( $project ) ? $project->url_structure : '';
			// Move the file to mpg-uploads folder.
			$success = rename( $folder_path, $new_path );
			if ( ! $success ) {
				throw new Exception( sprintf( __( 'The file cannot be moved to %s. Ensure the folder has the correct permissions.', 'mpg' ), $new_path ) );
			}
			// Delete any file with the same project_id but different extension, after sanitizing each filename.
			$base_path = MPG_DatasetModel::uploads_base_path();
			$files     = glob( $base_path . $project_id . '.*' );
			foreach ( $files as $project_file ) {
				if ( $project_file !== $new_path ) {
					$sanitized_project_file = $base_path . sanitize_file_name( basename( $project_file ) );
					if ( file_exists( $sanitized_project_file ) ) {
						unlink( $sanitized_project_file );
					}
				}
			}

			$project       = MPG_ProjectModel::get_project_by_id( $project_id );
			$url_structure = $project->url_structure;

			$fields_array = [
				'source_type' => $type,
				'source_path' => basename( $new_path ),
				'headers'     => json_encode( $headers )
			];

			MPG_ProjectModel::mpg_update_project_by_id( $project_id, $fields_array );

			echo json_encode( [
				'success' => true,
				'data'    => [
					'headers'       => $headers,
					'rows'          => $rows['rows'],
					'totalRows'     => $rows['total_rows'],
					'projectId'     => $project_id,
					'path'          => $new_path,
					// В процессе сохранения проекта, мы перемещаем датасет с temp в uploads.
					// Надо этот новый путь передать на фронт, чтобы с ним можно было работать в следующих вкладках
					'url_structure' => $url_structure
				]
			] );
		} catch ( Exception $e ) {

			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

			echo json_encode( [ 'success' => false, 'error' => $e->getMessage() ] );
		}

		wp_die();
	}



    // Работает с нижней кнопкой save
    public static function mpg_upsert_project_url_block()
    {

	    MPG_Validators::nonce_check();

        try {

            $project_id =         isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;
            $url_structure =      isset($_POST['urlStructure']) ? sanitize_text_field($_POST['urlStructure']) : null;
            $space_replacer =     isset($_POST['replacer']) ? sanitize_text_field(($_POST['replacer'])) : MPG_Constant::DEFAULT_SPACE_REPLACER;
            $url_mode =           isset($_POST['urlMode']) ? sanitize_text_field(($_POST['urlMode'])) : MPG_Constant::DEFAULT_URL_MODE;

            $direct_link =        isset($_POST['directLink']) ? esc_url_raw($_POST['directLink']) : null;

	        $args                    = apply_filters( 'mpg_update_project_args', [] );
	        $periodicity             = $args['periodicity'] ?? false;
	        $timezone                = $args['timezone'] ?? false;
	        $fetch_date_time         = $args['fetch_date_time'] ?? false;
	        $notificate_about        = $args['notificate_about'] ?? false;
	        $notification_email      = $args['notification_email'] ?? false;
	        $update_modified_on_sync = $args['update_modified_on_sync'] ?? 'no-update';
	        $update_modified_on_sync = $periodicity === 'once' ? 'no-update' : $update_modified_on_sync;
	        $source_type             = false;
	        if ( isset( $_POST['sourceType'] ) ) {
		        $source_type = MPG_Validators::validate_source_type( $_POST['sourceType'], ! empty( $direct_link ) ? MPG_Validators::SOURCE_TYPE_URL : MPG_Validators::SOURCE_TYPE_UPLOAD );
	        }
	        $worksheet_id = isset( $_POST['worksheetId'] ) ? (int) $_POST['worksheetId'] : null;

	        $update_options_array = [
		        'url_structure'  => str_replace( ' ', '_', $url_structure ),
		        'space_replacer' => $space_replacer,
		        'url_mode'       => $url_mode
	        ];

	        if ( ! empty( $source_type ) ) {
		        $update_options_array['source_type'] = $source_type;
	        }
            // Тут будет либо числовое значение, либо null. null полезен в том случае, если человек больше не хочет работать с вторым-третим листом, а хочет с первым
            // поэтому, удалив значение с поля на фронте, он имеет возможность поставить null в БД
            $update_options_array['worksheet_id'] = $worksheet_id !== 0 ? $worksheet_id : null;


            // Имея загруженный dataset, заменитель пробелов и структуру URL'ов, можно собрать массив из url с реальными данными
            $project = MPG_ProjectModel::get_project_by_id($project_id);

	        if ( empty( $project ) ) {
		        throw new Exception( __( 'Can\'t get project', 'mpg' ) );
	        }

	        $dataset_path = MPG_DatasetModel::get_dataset_path_by_project( $project );

	        if ( empty( $dataset_path ) ) {
		        throw new Exception( __( 'Can\'t get dataset path', 'mpg' ) );
	        }
            $urls_array = MPG_ProjectModel::mpg_generate_urls_from_dataset($dataset_path, $url_structure, $space_replacer,true );
            $update_options_array['urls_array'] = json_encode($urls_array['urls_array'], JSON_UNESCAPED_UNICODE);

            // =============================  Schedule ==========================
            // С какими параметрами крон-задача ставится, с такими ее надо и отключать. Поэтому храним это в базе
            // Это список аргументов которые надо передеать в хук.

            // now - это для тех случаев, когда человке хочет применить файл сейчас. И ему не нужно заводить крон-таб
            if ($direct_link && $fetch_date_time && ! in_array( $periodicity, array( 'now', 'once','ondemand' ), true ) && $notificate_about && $notification_email) {

                $datetime = DateTime::createFromFormat('Y/m/d H:i', $fetch_date_time, new DateTimeZone($timezone));
                $hook_execution_time = $datetime->getTimestamp();

                $data_for_hook = [$project_id, $direct_link, $notificate_about, $periodicity, $notification_email];

                if (in_array($periodicity, ['hourly', 'twicedaily', 'daily', 'weekly', 'monthly'])) {
                    if (!wp_next_scheduled('mpg_schedule_execution')) {

                        wp_schedule_event($hook_execution_time, $periodicity, 'mpg_schedule_execution', $data_for_hook);
                    }
                }

                $update_options_array = array_merge($update_options_array, [
                    'schedule_source_link' => $direct_link,
                    'schedule_periodicity' => $periodicity,
                    'schedule_notificate_about' => $notificate_about,
                    'schedule_notification_email' => $notification_email
                ]);
            } else {
                $update_options_array = array_merge($update_options_array, [
                    'schedule_periodicity' => 'now' !== $periodicity ? $periodicity : null,
                ]);
	            if ( $periodicity === 'ondemand' ) {
		            $update_options_array = array_merge( $update_options_array, [
			            'schedule_source_link'        => $direct_link,
			            'schedule_notificate_about'   => $notificate_about,
			            'schedule_notification_email' => $notification_email
		            ] );
	            }
            }
            $update_options_array['update_modified_on_sync'] = $update_modified_on_sync;

            MPG_ProjectModel::mpg_update_project_by_id($project_id, $update_options_array);

            $periodicity = $project->schedule_periodicity ?? null;
            $expiration  = 0;
            if ( null === $periodicity ) {
                $expiration = MPG_Helper::get_live_update_interval();
            }
	        MPG_DatasetModel::set_cache( $project_id, $urls_array['dataset_array'], $expiration );
            echo json_encode(['success' => true]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }

    // чтобы получить объект из базы по определенному project_id.
    public static function mpg_get_project()
    {


	    MPG_Validators::nonce_check();
        try {

            $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;

            if (!$project_id) {
                throw new Exception(__('Missing project ID', 'mpg'));
            }

            $project = MPG_ProjectModel::get_project_by_id( $project_id);

            if (empty($project)) {
                throw new Exception(__('Project not found', 'mpg'));
            }

            $response = (array) $project;

            $dataset_path = ! empty( $response['source_path'] ) ? $response['source_path'] : '';
	        if ( empty( $dataset_path ) ) {
		        throw new Exception( __( 'Project dataset not found', 'mpg' ) );
	        }

            if ($project->schedule_periodicity && $project->schedule_source_link && $project->schedule_notificate_about) {

                $response['nextExecutionTimestamp'] = wp_next_scheduled('mpg_schedule_execution', [
                    (int) $project_id,
                    $project->schedule_source_link,
                    $project->schedule_notificate_about,
                    $project->schedule_periodicity,
                    $project->schedule_notification_email
                ]);
            }

            if ( isset($project->source_path ) ) {

	            $rows = MPG_DatasetController::get_rows( MPG_DatasetModel::get_dataset_path_by_project( $project ), 5 );

                $response['rows'] = wp_doing_ajax( 'wp_ajax_mpg_get_project' ) ? map_deep( $rows['rows'], 'wp_strip_all_tags' ) : $rows['rows'];
                $response['totalRows'] = $rows['total_rows'];

                $response['spintax_cached_records_count'] = MPG_SpintaxController::get_cached_records_count($project_id);

	            $response['source_url'] = basename( $project->source_path );
	            $response['source_url_full'] = MPG_DatasetModel::uploads_base_url() . basename( $project->source_path );

                echo json_encode([
                    'success' => true,
                    'data' => $response
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $response
                ]);
            }
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    public static function mpg_delete_project()
    {

	    MPG_Validators::nonce_check();

        try {
	        $project_id      = isset( $_POST['projectId'] ) ? (int) $_POST['projectId'] : null;
	        $project_manager = new ProjectsListManage( );
	        $project_manager->delete_project( $project_id );
	        echo json_encode( [
		        'success' => true
	        ] );
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    // ============ Permalink structure ==============

    public static function mpg_get_permalink_structure()
    {

	    MPG_Validators::nonce_check();
        try {

            echo json_encode([
                'success' => true,
                'data' => get_option('permalink_structure')
            ]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }



    public static function mpg_change_permalink_structure()
    {

	    MPG_Validators::nonce_check();
        try {

            if (update_option('permalink_structure', '/%postname%/')) {
                echo json_encode([
                    'success' => true,
                    'data' => __('Permalink structure was changed to /postname/', 'mpg')
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => __('Permalink structure was not changed', 'mpg')
                ]);
            }
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }



    // ================== Sitemap   ==================

    public static function mpg_check_is_sitemap_name_is_uniq()
    {

	    MPG_Validators::nonce_check();

	    try {
		    $filename = isset( $_POST['filename'] ) ? sanitize_text_field( $_POST['filename'] ) : null;

		    if ( get_option( 'mpg_site_basepath' ) ) {
			    $sitemap_path = get_option( 'mpg_site_basepath' )['value'] . $filename . '.xml';
		    } else {
			    $sitemap_path = ABSPATH . $filename . '.xml';
		    }

		    echo json_encode( [
			    'success' => true,
			    'unique'  => ! is_file( $sitemap_path )
		    ] );
	    } catch ( Exception $e ) {

		    do_action( 'themeisle_log_event', MPG_NAME, sprintf( __( 'Can\'t create sitemap, due to: %s', 'mpg' ), $e->getMessage() ), 'debug', __FILE__, __LINE__ );

		    echo json_encode( [
			    'success' => false,
			    'error'   => __( 'Can\'t create sitemap, due to: ', 'mpg' ) . $e->getMessage()
		    ] );
	    }

        wp_die();
    }


	public static function mpg_generate_sitemap() {
		MPG_Validators::nonce_check();

		try {

			$project_id            = isset( $_POST['projectId'] ) ? (int) $_POST['projectId'] : null;
			$filename              = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : null;
			$max_url               = isset( $_POST['maxUrlPerFile'] ) ? (int) $_POST['maxUrlPerFile'] : 50000;
			$update_freq           = isset( $_POST['frequency'] ) ? esc_sql( $_POST['frequency'] ) : null;
			$add_to_robots         = isset( $_POST['addToRobotsTxt'] ) ? filter_var( $_POST['addToRobotsTxt'], FILTER_VALIDATE_BOOLEAN ) : false;
			$previous_sitemap_name = isset( $_POST['previousSitemapName'] ) ? esc_sql( $_POST['previousSitemapName'] ) : null;
			$priority              = isset( $_POST['priority'] ) ? sanitize_text_field( $_POST['priority'] ) : 1;

			MPG_ProjectModel::mpg_update_project_by_id( $project_id, [
				'sitemap_filename'         => $filename,
				'sitemap_max_url'          => $max_url,
				'sitemap_update_frequency' => $update_freq,
				'sitemap_add_to_robots'    => $add_to_robots,
				'sitemap_priority'         => $priority,
			] );

			$project = MPG_ProjectModel::get_project_by_id( $project_id );

			$raw_urls_list = ! empty( $project ) ? $project->urls_array : null;

			if ( empty( $raw_urls_list ) ) {
				throw new Exception( __( 'Project don\'t have any URLs.', 'mpg' ) );
			}

			$urls_list = json_decode( $raw_urls_list, true );


			if ( ! empty( $previous_sitemap_name ) ) {
				foreach (
					[
						ABSPATH . $previous_sitemap_name . '.xml',
						ABSPATH . $previous_sitemap_name . '-index.xml'
					] as $main_file_path
				) {

					if ( file_exists( $main_file_path ) ) {
						// Это удаляется главный файл (либо он единственный, либо ...-index).
						unlink( $main_file_path );
					}

					// Но если есть ...-index, то сделовательно, есть и дочерние файлы, которые тоже надо "подчистить"
					$name = str_replace( '-index', '', $previous_sitemap_name );

					foreach ( glob( ABSPATH . $name . '*.xml' ) as $path ) {
						if ( file_exists( $path ) ) {
							unlink( $path );
						}
					}

					$sitemap_url = untrailingslashit( get_site_url() ) . '/' . $previous_sitemap_name . '.xml';
					MPG_ProjectModel::mpg_remove_sitemap_from_robots( $sitemap_url );
				}
			}

			MPG_SitemapGenerator::run( $urls_list, $filename, $max_url, $update_freq, $add_to_robots, $project_id );

			if ( count( $urls_list ) >= $max_url ) {
				$sitemap_filename = $filename ? $filename . '-index.xml' : 'multipage-sitemap-index.xml';
			} else {
				$sitemap_filename = $filename ? $filename . '.xml' : 'multipage-sitemap.xml';
			}

			$sitemap_full_path = untrailingslashit( get_site_url() ) . '/' . $sitemap_filename;

			MPG_ProjectModel::mpg_update_project_by_id( $project_id, [ 'sitemap_url' => $sitemap_full_path ] );

			echo json_encode( [
				'success' => true,
				'data'    => $sitemap_full_path
			] );
		} catch ( Exception $e ) {

			do_action( 'themeisle_log_event', MPG_NAME, sprintf( __( 'Can\'t create sitemap, due to: %s', 'mpg' ), $e->getMessage() ), 'debug', __FILE__, __LINE__ );

			echo json_encode( [
				'success' => false,
				'error'   => __( 'Can\'t create sitemap, due to: ', 'mpg' ) . $e->getMessage()
			] );
		}

		wp_die();
	}


    public static function mpg_scheduled_cron_handler($project_id, $link, $notificate_about, $periodicity, $notification_email)
    {

        try {

            $project = MPG_ProjectModel::get_project_by_id($project_id);


	        if ( empty( $project ) || empty( $project->source_path ) ) {
		        throw new Exception( __( 'Your project has not properly configured source file', 'mpg' ) );
	        }
	        $source_path = MPG_DatasetModel::get_dataset_path_by_project( $project );

	        $worksheet_id = ! empty( $project->worksheet_id ) ? $project->worksheet_id : null;

	        // Имея путь к файлу, мы можем его открыть и перезаписать содержимое.
	        // Но сначала надо скачать файл (получить содержимое), который пользователь хочет применить
	        $direct_link = MPG_Helper::mpg_get_direct_csv_link( $link, $worksheet_id );

	        MPG_DatasetModel::download_file( $direct_link, $source_path );

	        $url_structure  = $project->url_structure;
	        $space_replacer = $project->space_replacer;

	        $urls_array = MPG_ProjectModel::mpg_generate_urls_from_dataset( $source_path, $url_structure, $space_replacer );

            MPG_ProjectModel::mpg_update_project_by_id( $project_id, [ 'urls_array' => json_encode( $urls_array, JSON_UNESCAPED_UNICODE ) ], true );
	        MPG_SitemapGenerator::maybe_create_sitemap( $urls_array, $project );


            // Теперь, когда мы заменили файл с данными на тот, что пользователь указал по ссылке пользователь
            if ($notificate_about === 'every-time') {
                wp_mail(
                    $notification_email,
                    __('MPG schedule execution report: ok', 'mpg'),
                    __('Hi. <br>Scheduled task was completed successfully. File was deployed: ', 'mpg') . $direct_link
                );
            }

        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, sprintf( __( 'Hi. <br>In process of execution the next error occurred: %s', 'mpg' ), $e->getMessage() ), 'debug', __FILE__, __LINE__ );

            if ($notificate_about === 'errors-only') {
                wp_mail(
                    $notification_email,
                    __('MPG schedule execution report: failed', 'mpg'),
                    __('Hi. <br>In process of execution the next error occurred: ', 'mpg') . $e->getMessage()
                );
            }

            MPG_LogsController::mpg_write($project_id, 'warning', __('Exception in scheduled execution: ', 'mpg') . $e->getMessage());
        }

        // If cron task is repetitive - we should't delete this option. Because task still isn't completed
        // А если это была одиночная задача, то хук удалится сам, а в БД подчистим вручную.
        if ($periodicity === 'once') {

            MPG_ProjectModel::mpg_update_project_by_id($project_id, [
                'schedule_periodicity' => null,
                'schedule_source_link' => null,
                'schedule_notificate_about' => null,
                'schedule_notification' => null
            ]);
        }
    }

    public static function mpg_unschedule_cron_task()
    {

	    MPG_Validators::nonce_check();

        try {

            $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;

            $project = (array) MPG_ProjectModel::get_project_by_id($project_id);

            MPG_ProjectModel::mpg_remove_cron_task_by_project_id($project_id, $project);

            echo json_encode([
                'success' => true
            ]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => true,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    public static function mpg_set_hook_name_and_priority()
    {
	    MPG_Validators::nonce_check();

        try {

            $hook_name = sanitize_text_field($_POST['hook_name']);
            $hook_priority = sanitize_text_field($_POST['hook_priority']);

            if ($hook_name !== 'pre_handle_404' && $hook_name !== 'posts_selection' && $hook_name !== 'template_redirect' && $hook_name !== 'wp') {
                throw new Exception(__('Hook name is not correct', 'mpg'));
            }

            if ($hook_priority !== '1' && $hook_priority !== '10' && $hook_priority !== '100') {
                throw new Exception(__('Hook priority is not correct', 'mpg'));
            }

            update_option('mpg_hook_name', $hook_name);
            update_option('mpg_hook_priority', $hook_priority);

            echo json_encode([
                'success' => true
            ]);

            wp_die();
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            wp_die();
        }
    }

    public static function mpg_get_hook_name_and_priority()
    {
	    MPG_Validators::nonce_check();

        echo json_encode([
            'success' => true,
            'data' => [
                'hook_name' => get_option('mpg_hook_name'),
                'hook_priority' => get_option('mpg_hook_priority')
            ]
        ]);

        wp_die();
    }

    // Footer cache hooks
    public static function mpg_set_cache_hook_name_and_priority()
    {
	    MPG_Validators::nonce_check();

        try {

            $hook_name = sanitize_text_field($_POST['cache_hook_name']);
            $hook_priority = sanitize_text_field($_POST['cache_hook_priority']);

            if ($hook_name !== 'get_footer' && $hook_name !== 'wp_footer' && $hook_name !== 'wp_print_footer_scripts') {
                throw new Exception(__('Hook name is not correct', 'mpg'));
            }

            if ($hook_priority !== '1' && $hook_priority !== '10' && $hook_priority !== '100' &&  $hook_priority !== '10000') {
                throw new Exception(__('Hook priority is not correct', 'mpg'));
            }

            update_option('mpg_cache_hook_name', $hook_name);
            update_option('mpg_cache_hook_priority', $hook_priority);

            echo json_encode([
                'success' => true
            ]);

            wp_die();
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            wp_die();
        }
    }

    public static function mpg_get_cache_hook_name_and_priority()
    {

	    MPG_Validators::nonce_check();

        echo json_encode([
            'success' => true,
            'data' => [
                'cache_hook_name' => get_option('mpg_cache_hook_name'),
                'cache_hook_priority' => get_option('mpg_cache_hook_priority')
            ]
        ]);

        wp_die();
    }

    // Basepath
    public static function mpg_set_basepath()
    {

	    MPG_Validators::nonce_check();

        try {
            $basepath = sanitize_text_field($_POST['basepath']);

            if ($basepath !== 'abspath' && $basepath !== 'wp-content') {
                throw new Exception(__('Basepath is not correct', 'mpg'));
            }

            switch ($basepath) {
                case 'abspath':
                    update_option('mpg_site_basepath', [
                        'type' => 'abspath',
                        'value' => ABSPATH
                    ]);
                    break;
                case 'wp-content':
                    update_option('mpg_site_basepath', [
                        'type' => 'wp-content',
                        'value' => str_replace('wp-content', '', WP_CONTENT_DIR)
                    ]);
                    break;
            }

            echo json_encode([
                'success' => true
            ]);
            wp_die();
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            wp_die();
        }
    }

    public static function mpg_get_basepath()
    {
	    MPG_Validators::nonce_check();

        echo json_encode([
            'success' => true,
            'data' => get_option('mpg_site_basepath') ? get_option('mpg_site_basepath')['type'] : null
        ]);
        wp_die();
    }

    /**
     * Project builder menu callback.
     */
    public static function handle_project_builder() {
        if ( class_exists( 'ProjectsListManage', false ) ) {
            $projects_list_manage = new ProjectsListManage();
            $action               = ! empty( $_GET['action'] ) ? sanitize_title( wp_unslash( $_GET['action'] ) ) : '';
            $project_id           = ! empty( $_GET['id'] ) ? sanitize_title( wp_unslash( $_GET['id'] ) ) : 0;
            $redirect             = false;
	        $nonce                = ! empty( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

            if ( 'delete_project' === $action && $project_id ) {
	            if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'mpg-delete-project' ) ) {
		            wp_die( __( 'Security check failed', 'mpg' ) );
	            }

	            $redirect = false;
	            if ( $projects_list_manage->delete_project( $project_id ) ) {
		            $redirect = admin_url( 'admin.php?page=mpg-project-builder' );
	            }
            } elseif ( ! empty( $_GET['project_ids'] ) ) {
	            check_ajax_referer( MPG_BASENAME, '_mpg_nonce' );
                $project_ids = array_map( 'intval', $_GET['project_ids'] );

                if ( isset( $_GET['action2'] ) && 'bulk-delete' === $_GET['action2'] ) {
	                 $projects_list_manage->bulk_delete( $project_ids );
                }
	            $redirect = admin_url( 'admin.php?page=mpg-project-builder' );
            }elseif ( 'clone_project' === $action && $project_id){
	            if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'mpg-clone-project' ) ) {
		            wp_die( __( 'Security check failed', 'mpg' ) );
	            }

	            $cloned_id = $projects_list_manage->clone_project( $project_id );
	            if ( empty( $cloned_id ) ) {
		            wp_die( __( 'Error while cloning project', 'mpg' ) );
	            }
	            wp_redirect( admin_url( add_query_arg(
		            array(
			            'page'   => 'mpg-project-builder',
			            'action' => 'edit_project',
			            'id'     => $cloned_id
		            ),
		            'admin.php'
	            ) ), '301' );
                die();
            }elseif ( 'export_all_projects' === $action){
                if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'mpg-export-projects' ) ) {
                    wp_die( __( 'Security check failed', 'mpg' ) );
                }

                $projects_list_manage->export_projects( $project_id );

                wp_redirect( admin_url( add_query_arg(
                    array(
                        'page'   => 'mpg-project-builder',
                    ),
                    'admin.php'
                ) ), '301' );
                die();
            } elseif ( 'mpg_import_projects' === $action){
                if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'mpg_import_projects' ) ) {
                    wp_die( __( 'Security check failed', 'mpg' ) );
                }

                $projects_list_manage->import_projects();

                wp_redirect( admin_url( add_query_arg(
                    array(
                        'page'   => 'mpg-project-builder',
                        'imported' => 1,
                    ),
                    'admin.php'
                ) ), '301' );
                die();
            }

            if ( $redirect ) {
                wp_redirect(
                    add_query_arg(
                        array(
                            'page' => 'mpg-project-builder',
                            'deleted' => true,
                        ),
                        admin_url( 'admin.php' )
                    )
                );
                exit;
            }

            $option = 'per_page';
            $args   = array(
                'label'   => __( 'Number of items Per Page : ', 'mpg' ),
                'default' => 20,
                'option'  => 'mpg_projects_per_page',
            );
            add_screen_option( $option, $args );

            // Admin notices.
            add_action( 'admin_notices', array( 'MPG_ProjectController', 'show_admin_notices' ) );

        }
    }

    /**
     * Display admin notice.
     */
    public static function show_admin_notices() {
        if ( ! empty( $_GET['deleted'] ) ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Successfully deleted.', 'mpg' ); ?></p>
        </div>
        <?php
        } elseif( ! empty( $_GET['imported'] ) ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Successfully imported.', 'mpg' ); ?></p>
            </div>
            <?php
        }
    }

    // License
    public static function mpg_ti_toggle_license()
    {

        check_ajax_referer( MPG_BASENAME, 'nonce' );

        try {
            if ( ! isset( $_POST['license_key'] ) || ! isset( $_POST['_action'] ) ) {
                wp_send_json(
                    array(
                        'message' => __( 'Invalid Action. Please refresh the page and try again.', 'neve-pro-addon' ),
                        'success' => false,
                    )
                );
            }

            $key    = sanitize_text_field( wp_unslash( $_POST['license_key'] ) );
            $action = sanitize_text_field( wp_unslash( $_POST['_action'] ) );

            $response = apply_filters( 'themeisle_sdk_license_process_mpg', $key, $action );
            if ( is_wp_error( $response ) ) {
                wp_send_json(
                    array(
                        'message' => $response->get_error_message(),
                        'success' => false,
                    )
                );
            }

            $status = apply_filters( 'product_mpg_license_status', false );

            echo json_encode([
                'success' => true,
                'message' => $action === 'activate' ? esc_html__( 'Activated', 'mpg' ) : esc_html__( 'Deactivated', 'mpg' ),
                'key'     =>  'valid' === $status ? str_repeat( '*', 30 ) . substr( $key, - 5 ) : '',
                'button_text' => $action === 'activate' ? esc_html__( 'Deactivate', 'mpg' ) : esc_html__( 'Activate', 'mpg' ),
                'expiration'  => '<span class="dashicons dashicons-yes-alt"></span>' . esc_html__( 'Valid — Expires', 'mpg' ) . ' ' . mpg_app()->get_license_expiration_date() . '</p>',
                'action'      => $action,
            ]);
            wp_die();
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            wp_die();
        }
    }
    public static function mpg_ti_subscribe()
    {
        check_ajax_referer( MPG_BASENAME, 'nonce' );
        try {

            $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            if ( is_email( $email ) ) {
                $request_res = wp_remote_post(
                    'https://api.themeisle.com/tracking/subscribe',
                    array(
                        'timeout' => 100,
                        'headers' => array(
                            'Content-Type'  => 'application/json',
                            'Cache-Control' => 'no-cache',
                            'Accept'        => 'application/json, */*;q=0.1',
                        ),
                        'body'    => wp_json_encode(
                            array(
                                'slug'  => 'mpg',
                                'site'  => home_url(),
                                'email' => $email,
                                'data'  => array(
                                    'segment' => array(),
                                ),
                            )
                        ),
                    )
                );
                if ( ! is_wp_error( $request_res ) ) {
                    $body = json_decode( wp_remote_retrieve_body( $request_res ) );
                    if ( 'success' === $body->code ) {
                        update_user_meta( get_current_user_id(), '_mpg_dismiss_subscribe_notice', true );

                        wp_send_json(
                            array(
                                'status' => 1,
                            )
                        );
                    }
                }
                wp_send_json(
                    array(
                        'status'  => 0,
                        'message' => __( 'Something went wrong please try again.', 'mpg' ),
                    )
                );
            } else {
                wp_send_json(
                    array(
                        'status'  => 0,
                        'message' => __( 'Please enter a valid email address.', 'mpg' ),
                    )
                );
            }
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => 0, 'message' => $e->getMessage()]);
        }

        wp_die();
    }

    public static function mpg_dismiss_subscribe_notice() {
        if ( ! isset( $_GET['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_nonce'] ) ), MPG_BASENAME ) ) {
            wp_redirect( wp_get_referer() );
            exit;
        }

        if ( ! is_user_logged_in() ) {
            wp_redirect( wp_get_referer() );
            exit;
        }

        update_user_meta( get_current_user_id(), '_mpg_dismiss_subscribe_notice', true );
        wp_redirect( wp_get_referer() );
        exit;
    }
}
