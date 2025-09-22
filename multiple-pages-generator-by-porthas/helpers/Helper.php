<?php

require_once(realpath(__DIR__ . '/Constant.php'));

if (!defined('ABSPATH')) exit;

use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Type;

class MPG_Helper
{
    public static $urls_array;

    public static function init() { 
        add_action( 'admin_enqueue_scripts', array( 'MPG_Helper', 'register_internal_pages' ) );
        add_filter( 'themeisle-sdk/survey/' . MPG_PRODUCT_SLUG, array( 'MPG_Helper', 'register_survey' ), 10, 2 );
    }

    // Подключает .mo файл перевода из указанной папки.
    public static function mpg_set_language_folder_path()
    {
        load_plugin_textdomain('multiple-pages-generator-by-porthas', false, dirname(plugin_basename(__DIR__)) . '/lang/');
    }

    // Register additional (monthly) interval for cron because WP hasn't weekly period
    public static function mpg_cron_monthly($schedules)
    {
        $schedules['monthly'] = array(
            'interval' => 60 * 60 * 24 * 30,
            'display' => __('Monthly', 'multiple-pages-generator-by-porthas')
        );

        return $schedules;
    }

    // Register additional (monthly) interval for cron because WP hasn't monthly period
    public static function mpg_cron_weekly($schedules)
    {
        $schedules['weekly'] = array(
            'interval' => 60 * 60 * 24 * 7,
            'display' => __('Weekly', 'multiple-pages-generator-by-porthas')
        );

        return $schedules;
    }

    public static function mpg_activation_events()
    {
	    $is_ajax = isset( $_POST['isAjax'] ) ? (bool) $_POST['isAjax'] : false;
        if ( $is_ajax ) {
	        MPG_Validators::nonce_check();
        }
        try {

            if (is_multisite()) {

                // Если это мультисайт, то для каждого мультисайта создаем в БД
                foreach (get_sites() as $site) {

                    $blog_id = intval($site->blog_id);

                    // Если индекс = 1, значит это главный сайт. Его файлы ложим в корень, а для дочерних - в подпапки.
                    // Делаю так на случай того, если мультисйт переделают в обычный, чтобы остались работать пути для главного сайта
                    // (который станет единственным)

                    $blog_index = $blog_id === 1 ? '' : $blog_id;

                    $uploads_folder_path = MPG_UPLOADS_DIR . $blog_index;

                    if (!file_exists($uploads_folder_path)) {
                        mkdir($uploads_folder_path);
                    }


                    $cache_folder_path = MPG_CACHE_DIR . $blog_index;

                    if (!file_exists($cache_folder_path)) {
                        mkdir($cache_folder_path);
                    }

                    MPG_ProjectModel::mpg_create_database_tables($blog_index);
                }
            } else {
                if ( ! file_exists( WP_CONTENT_DIR . '/mpg-uploads' ) ) {
                    mkdir( WP_CONTENT_DIR . '/mpg-uploads' );
                }

                if ( ! file_exists( WP_CONTENT_DIR . '/mpg-cache' ) ) {
                    mkdir( WP_CONTENT_DIR . '/mpg-cache' );
                }

                MPG_ProjectModel::mpg_create_database_tables('');
            }

            if ($is_ajax) {
                echo json_encode(['success' =>  true]);
                wp_die();
            }
        } catch (Exception $e) {
            if ($is_ajax) {

                do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
                wp_die();
            }
        }
    }




    public static function mpg_send_analytics_data()
    {

	    MPG_Validators::nonce_check();
      // nothing here.
    }

    // Remove cron task when user deactivate plugin
    public static function mpg_set_deactivation_option()
    {
        wp_clear_scheduled_hook('schedule_execution');
    }


    public static function mpg_admin_assets_enqueue($hook_suffix)
    {
        // echo $hook_suffix;

        // Include styles and scripts in MGP plugin pages only
        if (
            strpos($hook_suffix, 'mpg_page_mpg-dataset-library') !== false ||
            strpos($hook_suffix, 'mpg_page_mpg-advanced-settings') !== false ||
            strpos($hook_suffix, 'mpg_page_mpg-search-setting') !== false ||
            ( strpos($hook_suffix, '_mpg-project-builder') !== false && ! empty( $_GET['action'] ) && in_array( $_GET['action'], array( 'edit_project', 'from_scratch' ), true ) )
        ) {

            wp_enqueue_script('mpg_listFilter',                 plugins_url('frontend/libs/jquery.listfilter.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);
            wp_enqueue_script('mpg_datatable_js',               plugins_url('frontend/libs/dataTables/jquery.dataTables.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);
            wp_enqueue_script('mpg_bootstrap_js',               plugins_url('frontend/libs/bootstrap/bootstrap.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);
            wp_enqueue_script('mpg_datetime_picker',            plugins_url('frontend/libs/datetimepicker/jquery.datetimepicker.full.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);
            wp_enqueue_script('mpg_select2_js',                 plugins_url('frontend/libs/select2/select2.full.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);
            wp_enqueue_script('mpg_toast_js',                   plugins_url('frontend/libs/toast/toast.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);

            wp_enqueue_script('mpg_popper_1_js',                 plugins_url('frontend/libs/popper/popper.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);

            wp_enqueue_script('mpg_tippy_2_js',                 plugins_url('frontend/libs/popper/tippy-bundle.umd.min.js', __DIR__), array('jquery'), MPG_PLUGIN_VERSION);
	        $has_build    = is_readable( MPG_MAIN_DIR . '/frontend/build/app.asset.php' );
	        $app_assets   = $has_build ? require MPG_MAIN_DIR . '/frontend/build/app.asset.php' : array(
		        'dependencies' => array(),
		        'version'      => MPG_PLUGIN_VERSION
	        );
	        $app_location = $has_build ? plugins_url( 'frontend/build/app.js', __DIR__ ) : plugins_url( 'frontend/js/app.js', __DIR__ );
	        wp_enqueue_script( 'mpg_main_js', $app_location, array_merge( array( 'jquery' ), $app_assets['dependencies'] ), $app_assets['version'] );

            wp_localize_script('mpg_main_js', 'backendData', [
                'baseUrl'           => home_url('/'),
                'lang_code'         => defined( 'ICL_LANGUAGE_CODE' ) && 'en' !== ICL_LANGUAGE_CODE ? sprintf( '/%s/', ICL_LANGUAGE_CODE ) : '',
                'datasetLibraryUrl' => admin_url('admin.php?page=mpg-dataset-library'),
                'projectPage'       => admin_url('admin.php?page=mpg-project-builder'),
                'mpgAdminPageUrl'   => admin_url(),
                'mpgUploadDir'      => MPG_CACHE_URL,
				'version' => MPG_PLUGIN_VERSION,
                'securityNonce'     => wp_create_nonce( MPG_BASENAME ),
                'isPro'             => mpg_app()->is_premium(),
            ]);

            load_script_textdomain( 'mpg_main_js', 'multiple-pages-generator-by-porthas' );

            wp_enqueue_style('mpg_datatable',                   plugins_url('frontend/libs/dataTables/jquery.dataTables.min.css', __DIR__) , array(), MPG_PLUGIN_VERSION);
            wp_enqueue_style('mpg_bootstrap_css',               plugins_url('frontend/libs/bootstrap/bootstrap.min.css', __DIR__) , array(), MPG_PLUGIN_VERSION);
            wp_enqueue_style('mpg_datetimepicker_css',          plugins_url('frontend/libs/datetimepicker/jquery.datetimepicker.full.min.css', __DIR__) , array(), MPG_PLUGIN_VERSION);
            wp_enqueue_style('mpg_toast_css',                   plugins_url('frontend/libs/toast/toast.css', __DIR__) , array(), MPG_PLUGIN_VERSION);
            wp_enqueue_style('mpg_select2_css',                 plugins_url('frontend/libs/select2/select2.min.css',   __DIR__) , array(), MPG_PLUGIN_VERSION);

            wp_enqueue_style('mpg_font_awesome_css',            plugins_url('frontend/css/font-awesome.css',   __DIR__) , array(), MPG_PLUGIN_VERSION);

            wp_enqueue_style('mpg_main_css',                    plugins_url('frontend/css/style.css', __DIR__) , array(), MPG_PLUGIN_VERSION);

            wp_add_inline_style( 'mpg_main_css', '.condition-row {display: inline-flex;}.condition-row:not(:last-child) .add-new-condition:last-child {display:none;}.condition-row select {display: inline-flex;min-width: 170px;}.condition-row:first-child .mpg_headers_condition_value_dropdown:disabled + .btn-danger:not(.mpp-remove-action) {display: none;} .condition-container + .tooltip-circle {margin-left: 45px;}' );
        } elseif ( strpos($hook_suffix, 'mpg-project-builder') !== false ) {
            wp_enqueue_script('mpg_datatable_js',               plugins_url('frontend/libs/dataTables/jquery.dataTables.min.js', __DIR__), array('jquery') , MPG_PLUGIN_VERSION);
            wp_enqueue_style('mpg_datatable',                   plugins_url('frontend/libs/dataTables/jquery.dataTables.min.css', __DIR__) , array(), MPG_PLUGIN_VERSION);
        }
    }

    public static function mpg_front_assets_enqueue()
    {

        if (is_search()) {
            wp_enqueue_script('mpg_searchpage', plugins_url('frontend/js/mpg-front-search.js', __DIR__),  array('jquery'), MPG_PLUGIN_VERSION);

            wp_localize_script('mpg_searchpage', 'backendData', [
                'ajaxurl'           => admin_url('admin-ajax.php'),
                'mpgUploadDir'      => MPG_CACHE_URL,
                'securityNonce'     => wp_create_nonce( MPG_BASENAME ),
            ]);
        }
    }




    // Return the path of URL
	public static function mpg_get_request_uri() {
		global $wp;
		$full_url_path = home_url( $wp->request );
		$home_url      = explode( '?', home_url() )[0];
		$current_url   = urldecode( str_ireplace( $home_url, '/', $full_url_path ) );
		if ( ! str_contains( $current_url, '?' ) ) {
			$current_url = $current_url . '/';
		}
		$current_url = preg_replace( '/(\/+)/', '/', $current_url );

		return strtolower( $current_url );
	}

    public static function mpg_get_extension_by_path($path)
    {

        $regexp = '/format=(xlsx|ods|csv)/s';

        preg_match_all($regexp, $path, $matches, PREG_SET_ORDER, 0);

        // Если это ссылка на Gooole Drive ( шареный документ, то ок), а если нет - то берем из конца строки,
        // то что после последней точки
        if ($matches) {
            return $matches[0][1];
        } else {

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            // Если в расширении есть точка - обрезаем,
            return strpos($ext, '.') === 0 ? ltrim($ext, $ext[0]) : $ext;
        }
    }

    public static function array_flatten($array)
    {
        if (!is_array($array)) {
            return false;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::array_flatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }
        return $result;
    }
	/**
	 * Slugifies an array of strings, replacing spaces and processing special characters.
	 *
	 * This function iterates over an array of strings, replacing spaces with a specified
	 * character and processing special characters. The last index of the array is ignored
	 * as it is assumed to be a URL that does not need slugification.
	 *
	 * @param array $strings        The array of strings to be slugified.
	 * @param string $space_replacer The character to replace spaces with.
	 *
	 * @return array The modified array of strings with spaces replaced and special characters processed.
	 */
	public static function slugify_strings( array $strings, string $space_replacer ): array {
		foreach ( $strings as $index => $string ) {
			//we ignore the last index since that is the URL and we don't need to slugify it.
			if ($index === array_key_last($strings)) {
				continue;
			}
			//if the string is a URL, we ignore it.
			if ( preg_match( '/^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?(\?.*)?$/', $string ) ) {
				continue;
			}
			$strings[ $index ] = str_replace( ' ', $space_replacer, $string );
			$strings[ $index ] = MPG_ProjectModel::mpg_processing_special_chars( $strings[ $index ], $space_replacer );
		}
		return $strings;
	}
    public static function mpg_header_code_container()
    {

        $code = '';

        echo $code;
    }

	/**
	 * Extracts the worksheet ID from a given Google Sheets URL.
	 *
	 * This function parses the URL components and checks for the presence of the 'gid' parameter
	 * in the query string or fragment. If found, it returns the 'gid' value.
	 *
	 * @param string $url The URL from which to extract the worksheet ID.
	 * @return string|false The extracted worksheet ID, or false if no 'gid' is found.
	 */
	public static function extract_worksheet_from_url($url) {
		// Parse the URL components
		$urlComponents = parse_url($url);

		// Check if the query string exists
		if (isset($urlComponents['query'])) {
			parse_str($urlComponents['query'], $queryParams);

			// Check and return the gid parameter if it exists
			if (isset($queryParams['gid'])) {
				return $queryParams['gid'];
			}
		}

		// Check for gid in the fragment (after #)
		if (isset($urlComponents['fragment'])) {
			parse_str($urlComponents['fragment'], $fragmentParams);

			if (isset($fragmentParams['gid'])) {
				return $fragmentParams['gid'];
			}
		}

		// If no gid is found
		return false;
	}
	/**
	 * Extracts the document ID from a given Google Docs URL.
	 *
	 * This function parses the URL components and checks if the path exists.
	 * If the path exists, it uses a regular expression to match and extract the document ID.
	 *
	 * @param string $url The URL from which to extract the document ID.
	 * @return string The extracted document ID, or a message indicating no document ID was found.
	 */
	public static function extract_documentid_from_url($url) {
		// Parse the URL components
		$urlComponents = parse_url($url);

		// Check if the path exists in the URL
		if (isset($urlComponents['path'])) {
			// Match the document ID using a regex
			if (preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $urlComponents['path'], $matches)) {
				return $matches[1]; // Return the matched document ID
			}
		}

		// If no document ID is found
		return false;
	}
    public static function mpg_get_direct_csv_link($raw_link, $worksheet_id = null)
    {

        // false = substring was not found in target string
	    if ( strpos( $raw_link, 'docs.google.com' ) !== false or strpos( $raw_link, 'drive.google.com' ) !== false ) {
		    $worksheet_from_url = self::extract_worksheet_from_url( $worksheet_id );
		    $documentId         = self::extract_documentid_from_url( $raw_link );
		    if ( empty( $documentId ) ) {
			    return false;
		    }
		    $final_url          = 'https://docs.google.com/spreadsheets/d/' . $documentId . '/export?format=csv&id=' . $documentId;

		    if ( ! empty( $worksheet_id ) ) {
			    $final_url .= '&gid=' . $worksheet_id;
		    } elseif ( ! empty( $worksheet_from_url ) ) {
			    $final_url .= '&gid=' . $worksheet_from_url;
		    }
		    return $final_url;
	    }

        return $raw_link;
    }

    public static function mpg_get_spout_reader_by_extension($ext)
    {

        if ($ext === 'csv') {
            $reader = ReaderFactory::createFromType(Type::CSV); // for CSV files
        } else if ($ext === 'xlsx') {
            $reader = ReaderFactory::createFromType(Type::XLSX); // for XLSX files
        } elseif ($ext === 'ods') {
            $reader = ReaderFactory::createFromType(Type::ODS); // for ODS files
        } else {
            // translators: %s: the name of file extension.
            throw new Exception( sprintf( __('Unsupported file extension: %s', 'multiple-pages-generator-by-porthas'), $ext ) );
        }

	    $reader->setShouldFormatDates(true);
        return $reader;
    }

    public static function mpg_get_dataset_array( stdClass $project = null )
    {
	    $project_id         = isset( $project->id ) ? $project->id : 0;
	    $dataset_path       = MPG_DatasetModel::get_dataset_path_by_project( $project );

        global $mpg_dataset;
        if ( ! empty( $mpg_dataset[ $project_id ] ) ) {
            if ( is_array( $mpg_dataset[ $project_id ] ) ) {
                return $mpg_dataset[ $project_id ];
            }
            return json_decode( $mpg_dataset[ $project_id ] );
        }

        $dataset_array = MPG_DatasetModel::read_dataset( $dataset_path, false, $project_id );

        if ( ! doing_action( 'wp_ajax_mpg_get_search_results' ) ) {
            $mpg_dataset[ $project_id ] = $dataset_array;
        }
        if ( is_array( $dataset_array ) ) {
            return $dataset_array;
        }
        return json_decode( $dataset_array );
    }

    static function mpg_string_start_with($str, $needle)
    {
        return substr($str, 0, 1) === $needle;
    }


    static function mpg_string_end_with($str, $needle)
    {
        return substr($str, -1, 1) === $needle;
    }

    public static function mpg_prepare_post_excerpt($short_codes, $strings, $post_content)
    {
        $string = preg_replace('/\[.*?\]/m', '', $post_content);
        $string = str_replace(["\r", "\n"], ['', ''], $string);
        $string = strip_tags($string);
        $excerpt_length = (int) get_option('mpg_search_settings')['mpg_ss_excerpt_length'];
        if ( ! has_shortcode( $post_content, 'mpg_spintax' ) ) {
            $string = wp_trim_words($string, $excerpt_length );
            return preg_replace($short_codes, $strings, $string);
        }
        $string = preg_replace($short_codes, $strings, $string);
        $string = MPG_SpintaxModel::mpg_generate_spintax_string($string);
        $string = wp_trim_words($string, $excerpt_length );
        return $string;
    }

    public static function mpg_unique_array_by_field_value($array, $field)
    {
        $unique_array = [];
        foreach ($array as $element) {
            $hash = $element[$field];
            $unique_array[$hash] = $element;
        }

        return array_values($unique_array);
    }

	/**
	 * Get live update interval.
	 *
	 * @return mixed|null
	 */
	public static function get_live_update_interval(){
		/**
		 * Filter the live data update interval.
		 *
		 * @param int $interval The interval in seconds. Default is 15 minutes.
		 */
		return apply_filters( 'mpg_live_data_update_interval', MINUTE_IN_SECONDS * 15 );
	}
    /**
     * Live project data update.
     */
    public static function mpg_live_project_data_update( stdClass $project = null ) {

        $project_id         = isset( $project->id ) ? $project->id : 0;
        $dataset_path       = MPG_DatasetModel::get_dataset_path_by_project( $project );
        $periodicity        = isset( $project->schedule_periodicity ) ? $project->schedule_periodicity : null;
        $source_direct_link = isset( $project->original_file_url ) ? $project->original_file_url : '';
        $worksheet_id       = isset( $project->worksheet_id ) ? $project->worksheet_id : '';
        $space_replacer     = isset( $project->space_replacer ) ? $project->space_replacer : '';
        $url_structure      = isset( $project->url_structure ) ? $project->url_structure : '';
        $source_type = MPG_Validators::validate_source_type( $project->source_type ?? '', ! empty( $source_direct_link ) ? MPG_Validators::SOURCE_TYPE_URL : MPG_Validators::SOURCE_TYPE_UPLOAD );

        $expiration = 0;
        if ( null === $periodicity ) {
            $expiration = self::get_live_update_interval();
        }
        if ( $source_type !== MPG_Validators::SOURCE_TYPE_URL ) {
            return $project;
        }

        $dataset_array = MPG_DatasetModel::get_cache( $project_id ) || MPG_DatasetModel::get_dataset_chunk_cache( $project_id, 0 );

        if ( empty( $dataset_path ) ) {
            return $project;
        }
        if ( empty( $source_direct_link ) ) {
            return $project;
        }

        if ( empty( $dataset_array ) && $expiration > 0 ) {
            $direct_link = MPG_Helper::mpg_get_direct_csv_link( $source_direct_link, $worksheet_id );
            MPG_DatasetModel::download_file( $direct_link, $dataset_path );
            $urls_array = MPG_ProjectModel::mpg_generate_urls_from_dataset( $dataset_path, $url_structure, $space_replacer, true );
            $urls_array = $urls_array['urls_array'];
            $fields_array = array();
            self::$urls_array = $urls_array;
            $fields_array['urls_array'] = true; // If set to true, it means we need to regenerate the file.
            MPG_ProjectModel::mpg_update_project_by_id( $project_id, $fields_array, true );
            MPG_ProjectModel::update_last_check( $project_id );
            $project->urls_array = $urls_array['urls_array'];
            MPG_SitemapGenerator::maybe_create_sitemap( $project, $urls_array );
        }
        return $project;
    }

	/**
	 * Return the webhook URL for the project.
	 *
	 * @param $project_id
	 *
	 * @return string
	 */
	public static function get_webhook_url( $project_id ) {
		return rest_url( 'mpg/webhook/' . $project_id . '/?hash=' . hash_hmac( 'sha256', $project_id, self::get_webhook_key() ) );
	}

	/*
	 * Get the webhook key.
	 */
	public static function get_webhook_key() {
		return defined( 'MPG_WEBHOOK_KEY' ) ? MPG_WEBHOOK_KEY : ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'mpgftw' );
	}
    /**
     * Filter found posts.
     *
     * @param int $found_posts WP_Post found posts.
     * @return int
     */
    public static function mpg_found_posts( $found_posts ) {
        global $mpg_default_posts;
        return $mpg_default_posts > 0 ? count( $mpg_default_posts ) + $found_posts : $found_posts;
    }

    /**
     * Handle posts results.
     *
     * @param array  $posts WP_Post array.
     * @param object $query WP_Query object.
     * @return array
     */
    public static function mpg_posts_results( $posts, $query ) {
	if ( ! $query instanceof WP_Query || ( ! $query->is_home && ! $query->is_search ) ) {
	    return $posts;
	}
        if ( is_admin() ) {
            return $posts;
        }
        global $mpg_default_posts;
        if ( empty( $mpg_default_posts ) ) {
            return $posts;
        }
        $posts_per_page = $query->get( 'posts_per_page' );
        $posts_per_page = $posts_per_page > 0 ? $posts_per_page : get_option( 'posts_per_page' );
        $paged          = $query->get( 'paged' );
        $paged          = $paged > 1 ? $paged - 1 : 0;
        if ( empty( $posts ) ) {
            $total_publish_post = wp_count_posts();
            $total_publish_post = (int) $total_publish_post->publish;
            $posts              = range( 1, $total_publish_post );
        }
        $posts                = array_merge( $posts, $mpg_default_posts );
        $query->found_posts   = is_array( $posts ) ? count( $posts ) : $query->found_posts;
        $posts                = array_chunk( $posts, $posts_per_page );
        $query->max_num_pages = ceil( $query->found_posts / $posts_per_page );
        $query->posts         = isset( $posts[ $paged ] ) ? $posts[ $paged ] : array();
        return $query->posts;
    }

    /**
     * Handle pre get posts.
     *
     * @param object $query WP_Query object.
     * @return void
     */
    public static function mpg_pre_get_posts( $query ) {
	if ( ! $query instanceof WP_Query || ( ! $query->is_home && ! $query->is_search ) ) {
	    return;
	}
        if ( is_admin() ) {
            return;
        }
        $where       = ' WHERE `participate_in_default_loop` = 1';
        $project_ids = MPG_ProjectModel::mpg_get_project_ids_by_where( $where );
        $project_ids = apply_filters( 'mpg_projects_participate_in_default_loop', $project_ids );

        $post_type = $query->get( 'post_type' );
        if ( ! empty( $post_type ) && ! in_array( $post_type, apply_filters( 'mpg_default_loop_post_type', array( 'post' ) ), true ) ) {
            return;
        }
        global $mpg_default_posts;
        foreach ( $project_ids as $project_id ) {
            $project       = \MPG_ProjectModel::get_project_by_id( $project_id );
            $dataset_array = MPG_Helper::mpg_get_dataset_array( $project );
            $urls_array    = $project->urls_array ? json_decode( $project->urls_array, true ) : array();

            $headers       = $project->headers;
            $headers_array = json_decode( $headers );
            $headers_array = array_map(
                function ( $raw_header ) {
                    $header = str_replace( ' ', '_', strtolower( $raw_header ) );
                    if ( strpos( $header, 'mpg_' ) !== 0 ) {
                        $header = 'mpg_' . $header;
                    }
                    return $header;
                },
                $headers_array
            );
            // Get header number by name.
            $featured_image_url = array_search( 'mpg_image', $headers_array, true );
            $template_id        = isset( $project->template_id ) ? (int) $project->template_id : 0;
            $template           = get_post( $template_id );
            $mpg_default_posts  = array();
            if ( $template instanceof \WP_Post ) {
                $template_name    = $template->post_title;
                $template_content = $template->post_content;
                $short_codes      = \MPG_CoreModel::mpg_shortcodes_composer( $headers_array );
                foreach ( $urls_array as $index => $url ) {
                    $index   = ++$index;
                    $strings = $dataset_array[ $index ];

                    // Create duplicate post array.
                    $duplicate_post                   = new \WP_Post( new stdClass() );
                    $replaced_shortcodes_string_title = preg_replace( $short_codes, $strings, $template_name );
                    $replaced_shortcodes_string       = $replaced_shortcodes_string_title;
                    // Store results.
                    $duplicate_post->ID                  = $project->template_id;
                    $duplicate_post->filter              = 'raw';
                    $duplicate_post->post_title          = $replaced_shortcodes_string;
                    $duplicate_post->post_name           = $url;
                    $duplicate_post->post_content        = preg_replace( $short_codes, $strings, $template_content );
                    $duplicate_post->post_author         = $template->post_author;
                    $duplicate_post->post_date           = $template->post_date;
                    $duplicate_post->post_featured_image = ! empty( $featured_image_url ) ? esc_url( $featured_image_url ) : null;

                    $mpg_default_posts[] = $duplicate_post;
                }
            }
        }
    }

	/**
	 * Get the data used for the survey.
	 *
	 * @return array
	 * @see survey.js
	 */
	public static function get_survey_metadata() {

		$license_saved = get_option( 'multi_pages_plugin_premium_license_data', array() );

		$current_time        = time();
		$install_date        = min( get_option( 'multiple_pages_generator_by_porthas_install', $current_time ), get_option( 'multi_pages_plugin_premium_install', $current_time ) );
		$install_days_number = intval( ( $current_time - $install_date ) / DAY_IN_SECONDS );

		$version = get_plugin_data( MPG_BASENAME );
		$version = ! empty( $version['Version'] ) ? $version['Version'] : '';

		$created_projects_num_cache_key = 'mpg_created_projects';
		$created_projects_num_limit     = 50;
		$created_projects_num           = get_transient( $created_projects_num_cache_key );

		if ( false === $created_projects_num ) {
			$created_projects_num = ( new ProjectsListManage() )->total_projects( $created_projects_num_limit, false );
			set_transient( $created_projects_num_cache_key, $created_projects_num, $created_projects_num_limit >= $created_projects_num ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		} else {
			$created_projects_num = intval( $created_projects_num );
		}
		
		$survey_data = array(
			'environmentId' => 'clskhdqhz8qevpodw3om6y3fw',
			'attributes'    => array(
				'license_status'      => ! empty( $license_saved->license ) ? $license_saved->license : 'invalid',
				'version'             => $version,
				'plan'                => mpg_app()->get_license_type(),
				'install_days_number' => $install_days_number,
				'projects_number'     => $created_projects_num
			),
		);

		if ( isset( $license_saved->key ) ) {
			$survey_data['attributes']['license_key'] = apply_filters( 'themeisle_sdk_secret_masking', $license_saved->key );
		}

		return $survey_data;
	}

	/**
	 * Register the survey script for plugin pages.
	 *
	 * @param string $plugin     The plugin slug identifier
	 * @param string $page_slug  The page slug where the survey will be registered
	 * 
	 * @return array|null Survey metadata array if plugin is not free version, null otherwise
	 */
	public static function register_survey( $data, $page_slug ) {
		return self::get_survey_metadata();
	}

	/**
	 * Check if the edited post is an MPG template.
	 * Should be used only in admin context.
	 * It checks for translated versions of the template as well.
	 *
	 * @return bool
	 */
	public static function is_edited_post_a_template( $post_id = null ): bool {
		$post_id = empty( $post_id ) ? ( isset( $_GET['post'] ) ? (int) $_GET['post'] : 0 ) : $post_id;
		if ( empty( $post_id ) ) {
			return false;
		}
		global $wpdb;
		$project_id = MPG_ProjectModel::get_project_by_template_id( $post_id );
		if ( $project_id > 0 ) {
			return true;
		}
		//we check if this is a translated version of the template.
		if ( defined( 'POLYLANG_VERSION' ) && function_exists( 'pll_get_post_translations' ) ) {
			$translations = pll_get_post_translations( $post_id );
			foreach ( $translations as $lang => $translated_post_id ) {
				$project_id = MPG_ProjectModel::get_project_by_template_id( $post_id );
				if ( ! empty( $project_id ) ) {
					return true;
				}
			}
		}

		if ( defined( 'WPML_PLUGIN_BASENAME' ) ) {
			$trid         = apply_filters( 'wpml_element_trid', null, $post_id );
			$translations = apply_filters( 'wpml_get_element_translations', null, $trid );
			if ( ! empty( $translations ) ) {
				$translations_ids   = wp_list_pluck( $translations, 'element_id' );
				$translations_ids[] = $trid;
				foreach ( $translations_ids as $translations_id ) {
					$project_id = MPG_ProjectModel::get_project_by_template_id( $post_id );
					if ( ! empty( $project_id ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Generate various variants for path based on the environment for better matching.
	 *
	 * @param $base_path
	 *
	 * @return array
	 */
	public static function generate_path_variants($base_path){
		$variants               = array();
		$variants[ $base_path ] = true;
		if ( defined( 'ICL_LANGUAGE_CODE' ) && 'en' !== ICL_LANGUAGE_CODE ) {
			if ( strpos( $base_path, '/' . ICL_LANGUAGE_CODE ) === 0 ) {
				$variants[ substr( $base_path, strlen( '/' . ICL_LANGUAGE_CODE ) ) ] = true;
			}
			$lang_path_without_qlang                                 = remove_query_arg( 'lang', $base_path );
			$variants[ trailingslashit( $lang_path_without_qlang ) ] = true;
		}
		if ( defined( 'AMPFORWP_VERSION' ) ) {
			$variants[ untrailingslashit( $base_path ) . '/amp' ]  = true;
			$variants[ untrailingslashit( $base_path ) . '/amp/' ] = true;
		}
		return $variants;

	}

	/**
	 * Check if we are in the single virtual page rendering context.
	 *
	 * @return bool
	 */
	public static function is_mpg_single() {
		return defined( 'MPG_IS_SINGLE' ) && MPG_IS_SINGLE;
	}

    /**
     * Enqueue block editor assers for `view sample MPG urls`.
     */
    public static function block_editor_assets_enqueue() {
        global $pagenow, $post;
        if ( 'post.php' !== $pagenow || ! $post ) {
            return;
        }
        $project_id = MPG_ProjectModel::get_project_by_template_id( $post->ID );
        if ( ! $project_id ) {
            return;
        }
        $project    = \MPG_ProjectModel::get_project_by_id( $project_id );
        $urls_array = isset( $project->urls_array ) ? json_decode( $project->urls_array ) : array();
        $urls       = array();
        foreach ( $urls_array as $index => $row ) {
            if ( 'without-trailing-slash' === $project->url_mode ) {
                $row = rtrim( $row, '/' );
            }
            $urls[] = MPG_CoreModel::path_to_url( $row );
        }
        shuffle( $urls );
        wp_enqueue_script( 'mpg-sample-preview', plugins_url( 'frontend/js/sample-preview.js', __DIR__ ), array( 'wp-edit-post', 'wp-dom-ready', 'wp-data', 'wp-components' ), true, true );
        wp_localize_script(
            'mpg-sample-preview',
            'MPGSamplePreview',
            array(
                'previewUrl' => reset( $urls ),
                'buttonText' => __( 'View Sample MPG URL', 'multiple-pages-generator-by-porthas' ),
            )
        );
    }

    public static function register_internal_pages() {
        $screen = get_current_screen();

        if ( empty( $screen ) || empty( $screen->base ) ) {
            return;
        }

        $page_slug = '';
        if ( 'mpg_page_mpg-dataset-library' === $screen->base ) {
            $page_slug = 'dataset-library';
        }

        if ( 'toplevel_page_mpg-project-builder' === $screen->base ) {
            if ( isset( $_GET['action'] ) ) {
                $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
                if ( 'from_scratch' === $action ) {
                    $page_slug = 'new-project'; 
                } elseif ( 'edit_project' === $action ) {
                    $page_slug = 'edit-project';
                }
            } else {
                $page_slug = 'projects';
            }
        }

        if ( 'mpg_page_mpg-advanced-settings' === $screen->base ) {
            $page_slug = 'advanced-settings';
        }

        if ( 'mpg_page_mpg-search-settings' === $screen->base ) {
            $page_slug = 'search-settings';
        }

        if ( ! empty( $page_slug ) ) {
            do_action( 'themeisle_internal_page', MPG_PRODUCT_SLUG, $page_slug );
        }
    }
}
