<?php

if (!defined('ABSPATH')) exit;

require_once(realpath(__DIR__ . '/../models/DatasetModel.php'));
require_once(realpath(__DIR__ . '/../models/ProjectModel.php'));
require_once(realpath(__DIR__ . '/../models/SitemapModel.php'));

require_once(realpath(__DIR__ . '/../helpers/Constant.php'));
require_once(realpath( __DIR__ . '/../lib/src/Spout/Autoloader/autoload.php' ));

require_once(realpath(__DIR__ . '/../views/dataset-library/index.php'));

class MPG_DatasetController
{
    // Datasets from Sheets with names, size, icon
    // Получаю все 50-100 записей, и типа через DataTables делаю поиск по названию, и load more
    public static function get_all()
    {
        try {

            if (!extension_loaded('zip')) {
                echo __("You haven't installed or enabled ZIP extension for PHP, so MPG may not work properly", 'mpg');
            }

            // dataset_hub - it's a file from Google Sheets with datasets list (configs)
            $datasets_list = MPG_DatasetModel::mpg_read_dataset_hub();

            return MPG_DatasetLibraryView::render($datasets_list);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, __('Error occured in process of getting datasets. More detail in logs.', 'mpg'), 'debug', __FILE__, __LINE__ );

            echo __('Error occured in process of getting datasets. More detail in logs.', 'mpg');
        }
    }

    // Скачиваем с Sheets настройки, создаем сущность нужного типа (страница),
    public static function mpg_deploy()
    {
	    MPG_Validators::nonce_check();
        try {

            $dataset_id = isset($_POST['datasetId']) ? (int) ($_POST['datasetId']) : null;

            if (!$dataset_id) {
                throw new Exception(__('Wrong or missing dataset ID', 'mpg'));
            }

            // 1. Надо получить "конфиг" выбранного датасета
            $datasets_list = MPG_DatasetModel::mpg_read_dataset_hub();

            $_dataset_id = array_search( $dataset_id, array_column( $datasets_list, '0' ), true );
            $dataset_config = isset( $datasets_list[ $_dataset_id ] ) ? $datasets_list[ $_dataset_id ] : null;

            if (!$dataset_config) {
                throw new Exception(__('Needed dataset was not found', 'mpg'));
            }

            // 2. Надо создать страницу (или пост), дать ей название и получить id
            $entity_id = wp_insert_post([
                'post_title' => $dataset_config[4], // entity title
                'post_content' => $dataset_config[5], // entity content
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => $dataset_config[3] // entity type
            ]);

            // 3. Надо создать каркас в БД: name, entity_type (post / page), entity_id
            $project_id = MPG_ProjectModel::mpg_create_base_carcass($dataset_config[1], $dataset_config[3], $entity_id, false);

            if ( ! empty( $dataset_config[5] ) ) {
                $post_content = preg_replace( '/project-id=".*?"/', 'project-id="' . $project_id . '"', $dataset_config[5] );
                $post_content = preg_replace( '/href="(?!.*{{mpg_.*}}).*?"/', 'href="/' . $dataset_config[7] . '"', $post_content );
                wp_update_post(
                    array(
                        'ID' => $entity_id,
                        'post_content' => $post_content,
                    )
                );
            }

            // 4. Скачиваем и развертываем dataset
            $source_path = $dataset_config[6];
            $ext = MPG_Helper::mpg_get_extension_by_path($source_path);


            $destination_path =  MPG_DatasetModel::uploads_base_path() . $project_id . '.' . $ext;
            // Начинаем собирать объект для записи в БД
            $insert_data = [
                'source_type' => 'upload_file',
                'source_path' => basename( $destination_path )
            ];

            $download_dataset = MPG_DatasetModel::download_file($source_path, $destination_path);

            if ($download_dataset !== true) {
                throw new Exception($download_dataset, 'mpg');
            }
            if ( ! file_exists( $destination_path ) ) {
                $download_dataset = MPG_DatasetModel::download_file($source_path, $destination_path);
                if ($download_dataset !== true) {
                    throw new Exception($download_dataset, 'mpg');
                }
            }

            $headers = MPG_DatasetController::get_headers($destination_path);
            // Докидываем в массив еще и заголовки
            $insert_data['headers'] = json_encode($headers);

            $url_structure = $dataset_config[7];
            $space_replacer = $dataset_config[8];

            $urls_array = MPG_ProjectModel::mpg_generate_urls_from_dataset($destination_path, $url_structure, $space_replacer);

            $insert_data['url_structure'] = $url_structure;
            $insert_data['urls_array']     = json_encode($urls_array);
            $insert_data['space_replacer'] = $space_replacer;
            $insert_data['exclude_in_robots'] = 1;


            // Если надо - создаем карту сайта
            $sitemap_filename = isset($dataset_config[9]) ? $dataset_config[9] : null;
            $sitemap_max_url = isset($dataset_config[10]) ? $dataset_config[10] : null;
            $sitemap_update_frequency = isset($dataset_config[11]) ? $dataset_config[11] : null;
            $sitemap_add_to_robots = isset($dataset_config[12]) ? $dataset_config[12] : null;

            if ($sitemap_filename && $sitemap_max_url && $sitemap_update_frequency && $sitemap_add_to_robots) {

                $sitemap_url = MPG_SitemapGenerator::run($urls_array, $sitemap_filename, $sitemap_max_url, $sitemap_update_frequency, $sitemap_add_to_robots, $project_id);

                $insert_data['sitemap_filename'] = $sitemap_filename;
                $insert_data['sitemap_max_url'] = $sitemap_max_url;
                $insert_data['sitemap_update_frequency'] = $sitemap_update_frequency;
                $insert_data['sitemap_add_to_robots'] = $sitemap_add_to_robots;

                $sitemap_filename = $sitemap_filename ? $sitemap_filename . '.xml' : 'multipage-sitemap.xml';

                $insert_data['sitemap_url'] = $sitemap_url;
            }

            MPG_ProjectModel::mpg_update_project_by_id($project_id, $insert_data);

            echo json_encode([
                'success' => true,
                'data' => [
                    'projectId' => $project_id
                ]
            ]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        wp_die();
    }

    // возвращает массив из названий заголовков
    // Бывают случаи, где get_headers вызывается из функции, где уже есть прочитан source-файл.
    // Поэтому я сразу передаю заголовки как массив. Если эе эта функция вызывается саба по себе, тогда читаем с файла.
    public static function get_headers($path_to_dataset, $headers = null)
    {

        if (!$headers) {
	        $headers = MPG_DatasetModel::read_dataset( $path_to_dataset, true );
        }

        foreach ($headers as $key => $header) {
            $header          = preg_split( '/[^A-Za-z0-9\-]/', $header );
            $header          = array_filter( $header );
            $headers[ $key ] = join( '_', $header );
        }

        $headers =  array_filter($headers, function ($row) {
            // Таки образом, убираем null, котроые phpSpreadsheets чситывает с Xlsx файлов
            // https://github.com/PHPOffice/PhpSpreadsheet/issues/708
            if ($row) {
                return  $row;
            }
        });

        // Чтобы точно убедится что это одномерный массив, и он не имеет ключей
        return array_values($headers);
    }

    // возвращает массив самих данных.
	public static function get_rows( $path_to_dataset, $limit ) {

		$dataset_array = MPG_DatasetModel::read_dataset( $path_to_dataset );

		// Срезаем N элементов с датасета, пропуская хедер.
		$limit = count( $dataset_array ) >= 5 ? $limit : count( $dataset_array ) - 1;

		return [
			'rows'       => array_slice( $dataset_array, 1, $limit ),
			'total_rows' => count( $dataset_array )
		];
	}

    // Возвращает данные для модалки с превью датасета.
    public static function mpg_get_data_for_preview()
    {

	    MPG_Validators::nonce_check();
        try {

            $project_id = (int) $_GET['projectId'];

            $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 0;
            $start = isset($_POST['start']) ? (int) $_POST['start'] : 1;
            $length = isset($_POST['length']) ? (int) $_POST['length'] : 10;
            $search_value = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : null;

            $path_to_dataset = MPG_DatasetModel::get_dataset_path_by_project($project_id);

	        $dataset_array = MPG_DatasetModel::read_dataset( $path_to_dataset );

            $data = [];
            $search_results_length = 0;

            if ($search_value) {
                $search_string = trim(strtolower($search_value));

                foreach ($dataset_array as $row) {
                    // @todo: стоило бы это оптимизоровать. Возможно, для больгих датасетов поиск будет тупить
                    $row = array_map('strtolower', $row);
                    $row = array_map('trim', $row);

                    if (array_search($search_string, $row, true) !== false) {
                        $data[] = $row;
                    }
                }

                $search_results_length = count($data);

                $data = array_slice($data, $start, $length);
            } else {
                // +1  чтобы пропустить заголовок
                $data = array_slice($dataset_array, $start + 1, $length);
            }

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => count($dataset_array),
                'recordsFiltered' => $search_value ? $search_results_length : count($dataset_array),
                'data' => map_deep( $data, 'wp_strip_all_tags' ),
                'headers' =>  MPG_DatasetController::get_headers($path_to_dataset, $dataset_array[0])
            ]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }


	public static function mpg_preview_all_urls() {

		MPG_Validators::nonce_check();
		try {

			$project_id = (int) $_GET['projectId'];

			$draw         = isset( $_POST['draw'] ) ? (int) $_POST['draw'] : 0;
			$start        = isset( $_POST['start'] ) ? (int) $_POST['start'] : 1;
			$length       = isset( $_POST['length'] ) ? (int) $_POST['length'] : 10;
			$search_value = isset( $_POST['search']['value'] ) ? sanitize_text_field( $_POST['search']['value'] ) : '';

			$project = MPG_ProjectModel::get_project_by_id( $project_id );

			if ( empty($project) ) {
				throw new Exception( __( 'Can\'t get project', 'mpg' ) );
			}

			$urls_array    = $project->urls_array ? json_decode( $project->urls_array ) : [];
			$data          = [];
			$search_string = trim( strtolower( $search_value ) );
			$filtered      = 0;
			$page_results  = 0;
			foreach ( $urls_array as $index => $row ) {
				$filtered ++;
				if ( $index + 1 < $start ) {
					continue;
				}
				if ( ! empty( $search_string ) && ! str_contains( $row, $search_string ) ) {
					$filtered --;
					continue;
				}
				if ( $project->url_mode === 'without-trailing-slash' ) {
					$row = rtrim( $row, '/' );
				}
				if ( $page_results > $length ) {
					continue;
				}
				$data[] = [ '<a target="_blank" href="' . MPG_CoreModel::path_to_url( $row ) . '">' . MPG_CoreModel::path_to_url( $row ) . '</a>' ];
				$page_results ++;
			}


			echo json_encode( [
				'data'            => $data,
				'draw'            => $draw,
				'recordsTotal'    => count( $urls_array ),
				'recordsFiltered' => $filtered
			] );
		} catch ( Exception $e ) {

			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

			echo json_encode( [ 'success' => false, 'error' => $e->getMessage() ] );
		}

		wp_die();
	}



    public static function mpg_download_file_by_link()
    {
	    MPG_Validators::nonce_check();

        try {

            $project_id = (int) $_POST['projectId'];
            $link = isset($_POST['fileUrl']) ? sanitize_text_field($_POST['fileUrl']) : null;
            $worksheet_id = isset($_POST['worksheetId']) ? (int) $_POST['worksheetId'] : null;

            if ($link && $project_id) {

                $direct_link = MPG_Helper::mpg_get_direct_csv_link($link, $worksheet_id);

                $ext = MPG_Helper::mpg_get_extension_by_path($direct_link);

				// default to csv as file extension for non-matches to prevent
	            // security issues
				if ( ! in_array( strtolower( $ext ), ['csv', 'xls', 'xlsx', 'ods'] ) ) {
					throw new Exception(__('Unsupported file extension', 'mpg'));
				}

                $destination = MPG_DatasetModel::uploads_base_path() . 'temp-unlinked_file.' . $ext;

                if (MPG_DatasetModel::download_file($direct_link, $destination)) {

                    MPG_ProjectModel::mpg_update_project_by_id($project_id, [
                        'source_type' => 'direct_link',
                        'original_file_url' => $link
                    ]);

                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'path' => $destination
                        ]
                    ]);
                }
            } else {
                throw new Exception(__('Link or project ID is missing', 'mpg'));
            }
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, sprintf( 'Can\'t download file by URL. Details: %s', $e->getMessage() ), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => __('Can\'t download file by URL. Details:', 'mpg') . $e->getMessage()
            ]);
        }

        wp_die();
    }

    // Это для set trigger. После того, как человек выберет хедер - надо вернуть оттуда все уникальные значения
    public static function mpg_get_unique_rows_in_column()
    {
	    MPG_Validators::nonce_check();

        try {

            $project_id = (int) $_POST['projectId'];

            $project = MPG_ProjectModel::get_project_by_id($project_id);

	        $path_to_dataset = MPG_DatasetModel::get_dataset_path_by_project( $project );

	        if ( empty( $path_to_dataset ) ) {
		        throw new Exception( __( 'Dataset path was not defined', 'mpg' ) );
	        }

            $choosed_culumn_number = (int) $_POST['choosedColumnNumber'];
			$dataset = MPG_DatasetModel::read_dataset( $path_to_dataset );
	        $storage = array_column( $dataset,$choosed_culumn_number );
	        $storage = array_unique( $storage );
	        $storage = array_filter($storage);
            array_shift($storage);
			array_unshift($storage, ""); //add an empty value to the beginning of the array for users to use it.
            echo json_encode([
                'success' => true,
                'data' => $storage
            ]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, sprintf( 'Details: %s', $e->getMessage() ), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => __('Details:', 'mpg') . $e->getMessage()
            ]);
        }

        wp_die();
    }
}
