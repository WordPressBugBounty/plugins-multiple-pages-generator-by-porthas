<?php

if (!defined('ABSPATH')) exit;

require_once(realpath(__DIR__) . '/../helpers/Constant.php');

class MPG_ProjectModel
{
	private static $projects = [];
	/**
	 * @var int $current_project_id The ID of the current project.
	 */
	private static int $current_project_id = 0;

	/**
	 * Get the current project ID.
	 *
	 * @return int The ID of the current project.
	 */
	public static function get_current_project_id() {
		return self::$current_project_id;
	}

	/**
	 * Set the current project ID.
	 *
	 * @param int $project_id The ID of the project to set as current.
	 * @return int The newly set current project ID.
	 */
	public static function set_current_project_id($project_id) {
		return self::$current_project_id = $project_id;
	}


    public static function mpg_create_database_tables($blog_index)
    {
        try {

            if (isset($_POST['isAjax']) && $_POST['isAjax'] === true) {
                $blog_index = (bool) $_POST['isMultisite'];
            }

            global $wpdb;

            if (is_int($blog_index)) {
                $table_prefix = $wpdb->base_prefix . $blog_index . '_';
            } else {
                $table_prefix = $wpdb->base_prefix;
            }

            $mpg_projects_table = $table_prefix . MPG_Constant::MPG_PROJECTS_TABLE;
            $mpg_spintax_table = $table_prefix . MPG_Constant::MPG_SPINTAX_TABLE;
            $mpg_cache_table = $table_prefix . MPG_Constant::MPG_CACHE_TABLE;
            $mpg_logs_table = $table_prefix . MPG_Constant::MPG_LOGS_TABLE;

            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');

            #Check to see if the table exists already, if not, then create it

	        $charset_collate = $wpdb->get_charset_collate();
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($mpg_projects_table))) == $mpg_projects_table) {


                $sql = "CREATE TABLE `" . $mpg_projects_table . "` ( ";

                $sql .= " `id` int(128) NOT NULL AUTO_INCREMENT, ";

                $sql .= " `name` varchar(200) NOT NULL DEFAULT 'New Template', ";
                $sql .= " `entity_type` varchar(50) DEFAULT NULL, ";
                $sql .= " `template_id` int(10) DEFAULT NULL, ";
                $sql .= " `exclude_in_robots` BOOLEAN DEFAULT FALSE,";
                $sql .= " `participate_in_search` BOOLEAN DEFAULT FALSE,";
                $sql .= " `participate_in_default_loop` BOOLEAN DEFAULT FALSE,";

                $sql .= " `source_type` text DEFAULT NULL, ";
                $sql .= " `source_path` text DEFAULT NULL,";
                $sql .= " `worksheet_id` int(20) DEFAULT NULL, ";
                $sql .= " `original_file_url`  varchar(250) DEFAULT NULL,";

                $sql .= " `headers` text DEFAULT NULL, ";
                $sql .= " `url_structure` text DEFAULT NULL, ";
                $sql .= " `urls_array` MEDIUMTEXT DEFAULT NULL, ";
                $sql .= " `space_replacer` varchar(10) NOT NULL DEFAULT '-', ";

                $sql .= " `sitemap_url` varchar(255) DEFAULT NULL, ";
                $sql .= " `sitemap_filename` varchar(255) DEFAULT NULL, ";
                $sql .= " `sitemap_max_url` int(10) DEFAULT NULL, ";
                $sql .= " `sitemap_update_frequency` varchar(200) DEFAULT NULL, ";
                $sql .= " `sitemap_add_to_robots` BOOLEAN DEFAULT NULL, ";

                $sql .= " `schedule_source_link` text DEFAULT NULL, ";
                $sql .= " `schedule_periodicity` varchar(255) DEFAULT NULL, ";
                $sql .= " `schedule_notificate_about` varchar(255) DEFAULT NULL, ";
                $sql .= " `schedule_notification_email` varchar(255) DEFAULT NULL, ";

                $sql .= " `cache_type` varchar(255) NOT NULL DEFAULT 'none', ";

                $sql .= " `created_at` int(20) DEFAULT NULL, ";
                $sql .= " `updated_at` int(20) DEFAULT NULL, ";

                $sql .= "  PRIMARY KEY (`id`) ";
                $sql .= ") $charset_collate; ";

                dbDelta($sql);
                $sql = null;
            }

            if ( ! $wpdb->get_var( "SHOW COLUMNS FROM `$mpg_projects_table` LIKE 'sitemap_priority'" ) ) {
                $wpdb->query( "ALTER TABLE `$mpg_projects_table` ADD `sitemap_priority` float NOT NULL" );
            }
            if ( ! $wpdb->get_var( "SHOW COLUMNS FROM `$mpg_projects_table` LIKE 'participate_in_default_loop'" ) ) {
                $wpdb->query( "ALTER TABLE `$mpg_projects_table` ADD `participate_in_default_loop` BOOLEAN DEFAULT FALSE" );
            }

            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($mpg_spintax_table))) == $mpg_spintax_table) {

                $sql  = "CREATE TABLE  `" . $mpg_spintax_table . "` ( ";
                $sql .= "`id` INT(10) NOT NULL AUTO_INCREMENT , ";
                $sql .= "`project_id` INT(10) NULL ,";
                $sql .= "`url` TEXT NOT NULL ,";
                $sql .= "`spintax_string` TEXT NULL ,";
                $sql .= "PRIMARY KEY (`id`), INDEX `url` (`url`(100))) $charset_collate;";

                dbDelta($sql);
                $sql = null;
            }

            $is_block_id_column_exist = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND  table_name = '" . $mpg_spintax_table . "' AND column_name = 'block_id'");

            if (empty($is_block_id_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_spintax_table . "` ADD `block_id` varchar(255) NOT NULL default '1' AFTER `project_id`");
            }


            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($mpg_cache_table))) == $mpg_cache_table) {

                $sql  = "CREATE TABLE  `" . $mpg_cache_table . "` ( ";
                $sql .= "`id` INT(10) NOT NULL AUTO_INCREMENT , ";
                $sql .= "`project_id` INT(10) NOT NULL,";
                $sql .= "`url` TEXT NOT NULL ,";
                $sql .= "`cached_string` mediumtext NOT NULL,";
                $sql .= "PRIMARY KEY (`id`), INDEX `url` (`url`(500)))$charset_collate;";

                dbDelta($sql);
                $sql = null;
            }

            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($mpg_logs_table))) == $mpg_logs_table) {

                $sql  = "CREATE TABLE  `" . $mpg_logs_table . "` ( ";
                $sql .= "`id` INT(10) NOT NULL AUTO_INCREMENT , ";
                $sql .= "`project_id` INT(10) NOT NULL, ";
                $sql .= "`level` varchar(20) NOT NULL, ";
                $sql .= "`url`  varchar(250) DEFAULT NULL, ";
                $sql .= "`message` text NOT NULL, ";
                $sql .= "`datetime` date NOT NULL, ";
                $sql .= " PRIMARY KEY (`id`), INDEX `url` (`url`(250)))$charset_collate;";

                dbDelta($sql);
                $sql = null;
            }

            $is_cache_column_exist = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND table_name = '" . $mpg_projects_table . "' AND column_name = 'cache_type'");

            if (empty($is_cache_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_projects_table . "` ADD `cache_type` varchar(255) NOT NULL default 'none' AFTER `schedule_notification_email`");
            }

            $is_url_mode_column_exist = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND table_name = '" . $mpg_projects_table . "' AND column_name = 'url_mode'");

            if (empty($is_url_mode_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_projects_table . "` ADD `url_mode` varchar(25) NOT NULL default '" . MPG_Constant::DEFAULT_URL_MODE . "' AFTER `headers`");
            }

            $is_apply_condition_column_exist = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND table_name = '" . $mpg_projects_table . "' AND column_name = 'apply_condition'");

            if (empty($is_apply_condition_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_projects_table . "` ADD `apply_condition` varchar(200) default null  AFTER `url_mode`");
            }

            $is_participate_in_search_column_exist =  $wpdb->get_results("SELECT *  FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND table_name = '" . $mpg_projects_table . "' AND column_name = 'participate_in_search'");
            if (empty($is_participate_in_search_column_exist)) {
                $wpdb->query("ALTER TABLE `" . $mpg_projects_table . "` ADD `participate_in_search` BOOLEAN DEFAULT FALSE  AFTER `exclude_in_robots`");
            }
	        $update_modified_on_sync_exists = $wpdb->get_results( "SELECT *  FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND table_name = '" . $mpg_projects_table . "' AND column_name = 'update_modified_on_sync'" );
	        if ( empty( $update_modified_on_sync_exists ) ) {
		        $wpdb->query( "ALTER TABLE `" . $mpg_projects_table . "` ADD `update_modified_on_sync` varchar(10) DEFAULT FALSE  AFTER `exclude_in_robots`" );
	        }


            $is_logs_table_have_id_column = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND table_name = '" . $mpg_logs_table . "' AND column_name = 'id'");
            if (empty($is_logs_table_have_id_column)) {

                $wpdb->query("ALTER TABLE  `" . $mpg_logs_table . "`  DROP PRIMARY KEY;");
                $wpdb->query("ALTER TABLE  `" . $mpg_logs_table . "` ADD `id` INT(10) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`);");
            }
			update_option('mpg_database_version', MPG_DATABASE_VERSION);
        } catch (Exception $e) {

            // В WprdPress ошибка вида "Wprdpress database error" - не является throwable, т.е она не прырывает ход выполнения скрипта, а просто выводится как echo, и может ломать json ответ.
            // Надо копать в сторону WP_Error
            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            throw new Exception($e->getMessage());
        }
    }

    public static function mpg_create_base_carcass($project_name, $entity_type, $template_id, $exclude_in_robots = false)
    {
        global  $wpdb;

        $current_time_in_unix = time();
        $wpdb->insert($wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, array(

            'name' => $project_name,
            'entity_type' => $entity_type,
            'template_id' => $template_id,
            'exclude_in_robots' => $exclude_in_robots,
            'created_at' => $current_time_in_unix,
            'updated_at' => $current_time_in_unix
        ));

        return $wpdb->insert_id;
    }


    // Возвращает массив названий и типов сущностей зарегистрированіх в WordPress
    public static function mpg_get_custom_types()
    {
        $storage = [];
        $args = array('public' => true);
        $output = 'objects'; // names or objects, note names is the default

        foreach (get_post_types($args, $output) as $post_type) {
            if ($post_type->name === 'attachment') {
                continue;
            }
            array_push($storage, array('name' => $post_type->name, 'label' => $post_type->label));
        }

        return $storage;
    }

    public static function mpg_get_posts_by_custom_type()
    {

	    MPG_Validators::nonce_check();

        try {
            $custom_type_name = sanitize_text_field($_POST['custom_type_name']);

            $template_id = ! empty( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
            $args = array(
                'post_type' => $custom_type_name,
                'posts_per_page' => 10,
                'post_status' => 'publish',
                'post__in' => array( $template_id ),
                'orderby' => 'title',
                'order'   => 'ASC',
            );

            if ( isset( $_POST['q'] ) && ! empty( $_POST['q']['term'] ) ) {
                $args['s'] = sanitize_text_field( $_POST['q']['term'] );
                unset( $args['posts_per_page'] );
                unset( $args['post__in'] );
                add_filter( 'posts_where', array( 'MPG_ProjectModel', 'mpg_get_search_by_title' ), 10, 2 );
            }

            global $sitepress;
            $current_lang = '';

            if ( is_object( $sitepress ) ) {
                $current_lang = $sitepress->get_current_language();
                $sitepress->switch_lang( 'all' );
            }
            $query_object = new WP_Query( $args );

            if ( is_object( $sitepress ) ) {
                $sitepress->switch_lang( $current_lang );
            }
            remove_filter( 'posts_where', array( 'MPG_ProjectModel', 'mpg_get_search_by_title' ), 10 );

            // Свойство posts есть у всех типов, даж если єто page или какой-то кастом. тип.
            if (!$query_object->posts) {
                echo json_encode(array('success' => true, 'data' => []));
                wp_die();
            }

            $storage = [];
            $front_page_id = get_option('page_on_front');

            foreach ($query_object->posts as $post) {

                $entity = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'is_home' => false
                );

                if ($custom_type_name === 'page') {
                    if ($post->ID == $front_page_id) {
                        $entity['is_home'] = true;
                    }
                }

                array_push($storage, $entity);
            }
            echo json_encode(array('success' => true, 'data' => $storage));
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        wp_die();
    }

    public static function mpg_upload_file()
    {

	    MPG_Validators::nonce_check();
        try {

            if (isset($_FILES['file']['name']) && isset($_FILES['file']['tmp_name'])) {

                $project_id = (int) $_POST['projectId'];

                $filename      = sanitize_text_field($_FILES['file']['name']);
                $temp_filename = sanitize_text_field($_FILES['file']['tmp_name']);

                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

	            if ( ! in_array( $ext, [ 'csv', 'xls', 'xlsx', 'ods' ] ) ) {
		            throw new Exception( __( 'Unsupported file extension', 'multiple-pages-generator-by-porthas' ) );
	            }

                $destination = MPG_DatasetModel::uploads_base_path() . 'temp-unlinked_file.' . $ext;

                $move = move_uploaded_file($temp_filename, $destination);

                if ($move) {

                    MPG_ProjectModel::mpg_update_project_by_id($project_id, ['original_file_url' => $filename]);

                    echo json_encode(['success' => true, 'data' => ['path' => $destination, 'original_file_url' => $filename]]);
                    wp_die();
                } else {
                    do_action( 'themeisle_log_event', MPG_NAME, __('Error while uploading file', 'multiple-pages-generator-by-porthas'), 'debug', __FILE__, __LINE__ );
	                throw new Exception( __( 'Error while uploading file', 'multiple-pages-generator-by-porthas' ) );
                }
            }
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            wp_die();
        }
    }

    public static function mpg_options_update() {
	    MPG_Validators::nonce_check();
        
        try {
            if ( ! current_user_can( 'manage_options' ) ) {
                echo json_encode( [ 'success' => false, 'error' => 'You have no permissions to do this' ] );
                wp_die();
            }
            
            if ( isset( $_POST['enableTelemetry'] ) ) {
                $enable_telemetry = (int) $_POST['enableTelemetry'];
                update_option('multi_pages_plugin_logger_flag', $enable_telemetry > 0 ? 'yes' : 'no');
            }

            echo json_encode( [ 'success' => true ] );
        } catch ( Exception $e ) {
            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );
            echo json_encode( [ 'success' => false, 'error' => $e->getMessage() ] );
        }
        wp_die();
    }

    /**
     * Generate URL for a particular row
     * 
     * @param array $row
     * @param array $headers
     * @param string $url_structure
     * @param string $space_replacer
     * 
     * @return string The generated URL.
     */
    public static function mpg_generate_url_for_row( array $row, array $headers, string $url_structure, string $space_replacer ): string
    {
        // Create shortcodes from headers
        $shortcodes = [];
        foreach ($headers as $raw_header) {
            // In headers, always replace spaces with _, and in URLs - replace with what the user chose ($space_replacer)
            $header = str_replace(' ', '_', $raw_header);

            if (strpos($header, 'mpg_') === 0) {
                $shortcodes[] = '{{' . strtolower($header) . '}}';
            } else {
                $shortcodes[] = '{{mpg_' . strtolower($header) . '}}';
            }
        }

        $re = '/{{(.*?)}}/';
        // Process URL structure: replace spaces with space_replacer
        $url_structure = str_replace( ' ', $space_replacer, $url_structure );
        preg_match_all( $re, $url_structure, $matches, PREG_SET_ORDER, 0 );

        if ( empty( $matches ) ) {
            $url_structure = $shortcodes[0];
            $url_structure = str_replace( ' ', $space_replacer, $url_structure );
            preg_match_all( $re, $url_structure, $matches, PREG_SET_ORDER, 0 );
        }

        // Get indexes of columns that correspond to shortcodes
        $indexes = [];
        foreach ( $matches as $match ) {
            // Find the column number by shortcode
            $index = array_search( $match[0], $shortcodes );
            if ( is_int( $index ) ) {
                $indexes[] = $index;
            }
        }

        if ( empty( $indexes ) ) {
            return '/';
        }

        // Get needed shortcodes based on found indexes
        $needed_shortcodes = [];
        foreach ( $indexes as $column_number ) {
            $needed_shortcodes[] = $shortcodes[ $column_number ];
        }

        // Extract values from the row for the identified indexes
        $line = [];
        foreach ( $indexes as $index ) {
            $ceil_value = (string) $row[$index]; // (string) - for cases when the value is null or false
            $line[] = self::mpg_processing_special_chars( $ceil_value, $space_replacer );
        }

        // Replace shortcodes with actual values from columns
        return '/' . str_replace( $needed_shortcodes, $line, $url_structure ) . '/';
    }

    public static function mpg_generate_urls_from_dataset( $dataset_path, $url_structure, $space_replacer, $return_dataset = false )
    {
	    $dataset_array = MPG_DatasetModel::read_dataset( $dataset_path );

        // Get the headers (first row of dataset)
        $headers = ! empty( $dataset_array[0] ) ? $dataset_array[0] : array();

        $urls_array = [];
        $dataset_with_headers = $dataset_array;

        // Remove headers from dataset array
        array_shift( $dataset_array );

        // Generate URL for each row in the dataset
        foreach ( $dataset_array as $row ) {
            $urls_array[] = self::mpg_generate_url_for_row( $row, $headers, $url_structure, $space_replacer );
        }

        if ( $return_dataset ) {
	        // If dataset_with_headers is empty, then return dataset_array
	        // the dataset_with_headers might not be set if the shortcode tags are not used inside the URL Format Template.
            return array(
                'urls_array' => $urls_array,
                'dataset_array' => ! empty ( $dataset_with_headers ) ? $dataset_with_headers : $dataset_array,
            );
        }

        return $urls_array;
    }

	/**
	 * Alternative to mpg_get_project_by_id which return just the project.
	 *
	 * TODO: Remove usage mpg_get_project_by_id and use this function instead.
	 *
	 * @param $project_id
	 *
	 * @return false|mixed
	 * @throws Exception
	 */
	public static function get_project_by_id( int $project_id ) {
		if ( isset( self::$projects[ $project_id ] ) ) {
			return self::$projects[ $project_id ];
		}
		try {

			global $wpdb;
			$project = $wpdb->get_results(
				$wpdb->prepare("SELECT * FROM {$wpdb->prefix}" .  MPG_Constant::MPG_PROJECTS_TABLE . " WHERE id=%d", $project_id)
			);
			if ( empty( $project ) || empty( $project[0] ) ) {
				return false;
			}
			if ( ! isset( $project[0]->source_type ) || empty( $project[0]->source_type ) ) {
				$project[0]->source_type = isset( $project[0]->original_file_url ) && ! empty( $project[0]->original_file_url ) ? 'direct_link' : 'upload_file';
			}

            $project[0]->urls_array = ! empty( $project[0]->urls_array ) ? $project[0]->urls_array : json_encode( array_keys( MPG_DatasetModel::get_index( $project_id, 'permalinks' ) ) );

			self::$projects[ $project_id ] = $project[0];

			return self::$projects[ $project_id ];
		} catch (Exception $e) {

			do_action(
                'themeisle_log_event',
                MPG_NAME,
                __('Can\'t get project by id.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
                'debug',
                __FILE__,
                __LINE__
            );

			throw new Exception(
                __('Can\'t get project by id.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                )
            );
		}
	}

    /**
     * Get the schedule periodicity for a project.
     * 
     * @param mixed $project_id
     * @return int|mixed|null
     */
    public static function get_project_schedule_periodicity( $project_id ) {
        global $wpdb;
        $table = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
        $periodicity = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT schedule_periodicity FROM $table WHERE id = %d LIMIT 1",
                $project_id
            )
        );

        $expiration = DAY_IN_SECONDS;
        if ( null === $periodicity ) {
            $expiration = MPG_Helper::get_live_update_interval();
        }

        return $expiration;
    }

    /**
     * Get project url_structure and space_replacer.
     * 
     * @param int $project_id
     * @return array
     */
    public static function get_project_url_structure_and_space_replacer( int $project_id ): array
    {
        global $wpdb;
        $table = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT url_structure, space_replacer FROM $table WHERE id = %d LIMIT 1",
                $project_id
            ), ARRAY_A
        );

        return is_array( $result ) ? $result : [];
    }

	public static function update_last_check($project_id){

		global $wpdb;
		$wpdb->update( $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, [ 'updated_at' => time() ], [ 'id' => $project_id ] );
		$key_name = wp_hash( 'project_id_' . $project_id );
		delete_transient( $key_name );
	}

    public static function mpg_update_project_by_id(int $project_id, $fields_array, $delete_dataset = false )
    {
        global $wpdb;
        $generate_index = false;

        try {
            if ( empty( $fields_array['worksheet_id'] ) ) {
                unset( $fields_array['worksheet_id'] );
            }

            // For backward compatibility. We don't use urls_array in the DB anymore.
            // It is saved in the directory as a separate file.
            // To make it easier, we generate it in one place here.
            // If urls_array is set to true, it means we need to regenerate it.
            if ( isset( $fields_array['urls_array'] ) && true === $fields_array['urls_array'] ) {
                $fields_array['urls_array'] = null;
                $generate_index = true;
            }

            $wpdb->update($wpdb->prefix .  MPG_Constant::MPG_PROJECTS_TABLE, $fields_array, ['id' => $project_id]);

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            if ( $delete_dataset ) {
	            MPG_DatasetModel::delete_cache( $project_id );
            }

	        if ( isset( self::$projects[ $project_id ] ) ) {
		        unset( self::$projects[ $project_id ] );
	        }

	        if ( $generate_index ) {
                MPG_DatasetModel::create_index( $project_id );
            }
            
            // Save to excluded projects in a 'wp_options' for third-party plugins integration.
            $excluded_projects_in_robot = $wpdb->get_results( "SELECT DISTINCT template_id FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . ' WHERE `exclude_in_robots` = 1', ARRAY_A );
            update_option( MPG_Constant::EXCLUDED_PROJECTS_IN_ROBOT, $excluded_projects_in_robot );

            return true;
        } catch (Exception $e) {

            do_action(
                'themeisle_log_event',
                MPG_NAME,
                __('Can\'t update project by ID.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
                'debug',
                __FILE__,
                __LINE__
            );

            throw new Exception(
                __('Can\'t update project by ID.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                )
            );
        }
    }


    public static function mpg_get_all()
    {

        try {
            global $wpdb;

            $projects = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE);

            echo json_encode([
                'success' => true,
                'data' => count($projects) ? $projects : null
            ]);
        } catch (Exception $e) {

            do_action(
                'themeisle_log_event',
                MPG_NAME,
                __('Can\'t get all projects.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
                'debug',
                __FILE__,
                __LINE__
            );

            throw new Exception(
                __('Can\'t get all projects.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                )
            );
        }

        wp_die();
    }

    public static function deleteProjectFromDb($project_id)
    {

        try {
            global $wpdb;

            //  It returns the number of rows updated, or false on error.
            return $wpdb->delete($wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE, ['id' => $project_id], ['%d']);
        } catch (Exception $e) {

            do_action(
                'themeisle_log_event',
                MPG_NAME,
                __('Can\'t delete project.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
                'debug',
                __FILE__,
                __LINE__
            );

            throw new Exception(
                __('Can\'t delete project.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                )
            );
        }
    }

    public static function deleteFileByPath($path)
    {

        try {

            if (file_exists($path)) {

                if (!unlink($path)) {
                    throw new Exception(__('Can\'t delete file.', 'multiple-pages-generator-by-porthas'));
                }
            }

            return true;
        } catch (Exception $e) {

            // translators: %s: the error message.
            do_action( 'themeisle_log_event', MPG_NAME, sprintf( __('Details: %s', 'multiple-pages-generator-by-porthas'), $e->getMessage() ), 'debug', __FILE__, __LINE__ );

            // translators: %s: the error message.
            throw new Exception( sprintf( __('Details: %s', 'multiple-pages-generator-by-porthas'), $e->getMessage() ));
        }
    }

	/**
	 * Clone an existing dataset file to a new project.
	 *
	 * @param $source_path
	 * @param $project_id
	 *
	 * @return string
	 */
    public static function clone_dataset_file($source_path, $project_id)
    {

        $ext = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
	    $destination = MPG_DatasetModel::uploads_base_path() . $project_id . '.' . $ext;
        copy($source_path, $destination);

        return $destination;
    }

    public static function mpg_remove_cron_task_by_project_id($project_id, $project)
    {

        try {
            if ($project->schedule_source_link && $project->schedule_notificate_about && $project->schedule_periodicity) {
                $cron_arguments = [
                    (int) $project_id,
                    $project->schedule_source_link,
                    $project->schedule_notificate_about,
                    $project->schedule_periodicity,
                    $project->schedule_notification_email
                ];
                wp_clear_scheduled_hook('mpg_schedule_execution', $cron_arguments);

                MPG_ProjectModel::mpg_update_project_by_id($project_id, [
                    'schedule_periodicity' => null,
                    'schedule_source_link' => null,
                    'schedule_notificate_about' => null,
                    'schedule_notification_email' => null
                ]);


                return true;
            } else {
                do_action( 'themeisle_log_event', MPG_NAME, __('Some of needed values is missing, please, recreate task.', 'multiple-pages-generator-by-porthas'), 'debug', __FILE__, __LINE__ );
                throw new Exception(__('Some of needed values is missing, please, recreate task.', 'multiple-pages-generator-by-porthas'));
            }
        } catch (Exception $e) {
            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );
            throw new Exception($e->getMessage());
        }
    }

    public static function mpg_processing_robots_txt($exclude_in_robots, $template_id)
    {
        $path = ABSPATH . 'robots.txt';
        $handle = false;
        if ( is_readable( $path ) ) {
            $handle = fopen($path, 'r');
        }

        // Add each line to an array
        if ($handle) {

            $template_entity_url = get_permalink($template_id);
            $template_entity_url = str_replace(get_site_url(), '', $template_entity_url);

            $robots_string = explode("\n", fread($handle, 2048));

            // Toggle the disallow line.
            if ($exclude_in_robots) {
                if (!in_array('Disallow: ' . $template_entity_url, $robots_string)) {
                    $robots_string[] = 'Disallow: ' . $template_entity_url;
                }
            } else {
                $index = array_search('Disallow: ' . $template_entity_url, $robots_string);

                if ($index !== false) {
                    unset($robots_string[$index]);
                }
            }

            $file_content = implode("\n", $robots_string);

            file_put_contents($path, $file_content);
        }
    }

    public static function mpg_remove_sitemap_from_robots($sitemap_url)
    {
        $handle = fopen(ABSPATH . 'robots.txt', 'r');

        if ($handle) {

            $robots_string = explode("\n", fread($handle, 2048));

            $index = array_search('Sitemap: ' . $sitemap_url, $robots_string);

            if ($index !== false) {
                unset($robots_string[$index]);
            }
            $file_content = implode("\n", $robots_string);

            file_put_contents(ABSPATH . 'robots.txt', $file_content);
        }
    }

    public static function mpg_processing_special_chars($ceil_value, $space_replacer)
    {

        //Обрезаем слеши только вначале и в конце строки.
        $start_end_slashes_trimed = ltrim(rtrim(strtolower($ceil_value), '/'), '/');
        // Перед удалением всех спецсимволов - заменяем пробел на строку, иначе пробелы будут удалены регуляркой ниже.
        $escaped_spaces = preg_replace(
            ['/\s+/u', '/\//', '/\./', '/\-/', '/\_/', '/\~/', '/\=/'],
            ['mpgspaceholder', 'mpgslashholder', 'mpgdotholder', 'mpgdashholder', 'mpglodashholder', 'mpgtildaholder', 'mpgequalholder'],
            $start_end_slashes_trimed
        );


        $special_chars_trimmed =  preg_replace('/[^\w\?\&]/mu', '', $escaped_spaces);

        // То что раньше было пробелом - заменяем на space_replacer
        $back_to_allowed_chars = str_replace(
            ['mpgspaceholder', 'mpgslashholder', 'mpgdotholder', 'mpgdashholder', 'mpglodashholder', 'mpgtildaholder', 'mpgequalholder'],
            [$space_replacer, '/', '.', '-', '_', '~', '='],
            $special_chars_trimmed
        );

        return $back_to_allowed_chars;
    }


    public static function mpg_get_all_templates_id()
    {

        try {
            global $wpdb;

            $ids = $wpdb->get_results("SELECT DISTINCT template_id FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . ' WHERE `exclude_in_robots` = 1', ARRAY_A);
            $storage = [];
            if ($ids && count($ids)) {
                foreach ($ids as $id) {
                    $storage[] = (int) $id['template_id'];
                }
            }

            return $storage;
        } catch (Exception $e) {

            do_action(
                'themeisle_log_event',
                MPG_NAME,
                __('Can\'t get all projects.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
                'debug',
                __FILE__,
                __LINE__
            );

            throw new Exception(
                __('Can\'t get all projects.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
            );
        }
    }

    /**
     * Search by title only.
     *
     * @param string $where SQL where query.
     * @param object $wp_query WP Query Object.
     */
    public static function mpg_get_search_by_title( $where, $wp_query ) {
        global $wpdb;
        if ( $search_term = $wp_query->get( 's' ) ) {
            $where .= ' AND ' . $wpdb->posts . '.post_title LIKE \'%' . esc_sql( $wpdb->esc_like( $search_term ) ) . '%\'';
        }
        return $where;
    }

	/**
	 * Check if column exists in the project headers.
	 *
	 * @param $headers
	 * @param $column
	 *
	 * @return false|int|string
	 */
	public static function headers_have_column( $headers, $column ) {
		$column       = str_replace( [ '{{', '}}' ], '', $column );
		$headers       = \MPG_ProjectModel::normalize_headers( $headers );
		$column       = \MPG_ProjectModel::normalize_headers( [ $column ] )[0];
		$column_index = array_search( $column, $headers );
		if ( $column_index === false && str_starts_with( $column, 'mpg_' ) ) {
			$column       = substr( $column, 4 );
			$column_index = array_search( $column, $headers );
		}

		return $column_index;
	}
	/**
	 * Normalize the headers.
	 *
	 * @param array $headers The headers array.
	 *
	 * @return array
	 */
	public static function normalize_headers( array $headers): array {
		return array_map(function($header_value){
			$header = strtolower( $header_value );
			return str_replace( ' ', '_', $header );
		},$headers);
	}
	/**
	 * Returns the headers from the project.
	 *
	 * If the project don't have the headers then it will return the first row from the dataset.
	 *
	 * @param object $project The project object.
	 *
	 * return array
	 *
	 * @throws Exception
	 */
	public static function get_headers_from_project( $project ): array {

		if ( isset( $project->headers ) && ! empty( $project->headers ) ) {
			$json_array = json_decode( $project->headers, true );
			if ( is_array( $json_array ) ) {
				return $json_array;
			}
		}
		// if the project is missing the headers, we can use the first row from the dataset.
		$dataset_array = MPG_Helper::mpg_get_dataset_array( $project );
		if ( ! empty( $dataset_array ) && isset( $dataset_array[0] ) && is_array( $dataset_array[0] ) ) {
			return $dataset_array[0];
		}
		throw new Exception( 'Headers are missing in the project' );
	}

    public static function mpg_get_project_ids_by_where( $where = '' )
    {

        try {
            global $wpdb;

            $ids = $wpdb->get_results("SELECT `id` FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . $where, ARRAY_A);
            if ( empty( $ids ) ) {
                return array();
            }
            return array_map( 'intval', array_column( $ids, 'id' ) );
        } catch (Exception $e) {

            do_action(
                'themeisle_log_event',
                MPG_NAME,
                __('Can\'t get all projects.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                ),
                'debug',
                __FILE__,
                __LINE__
            );

            throw new Exception(
                __('Can\'t get all projects.', 'multiple-pages-generator-by-porthas') . ' ' . sprintf(
                    // translators: %s: the error message.
                    __('Details: %s', 'multiple-pages-generator-by-porthas'),
                    $e->getMessage()
                )
            );
        }
    }
    /**
     * Get the projects from the database.
     *
     * @param int $limit The number of projects to get.
     * @return array
     */
    public static function get_projects($limit = 1000):array{
        global $wpdb;
        $table = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
        $query = $wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit);
        $results = $wpdb->get_results($query);

        foreach( $results as $key => $project ) {
            $results[ $key ]->urls_array = ! empty( $project->urls_array ) ? $project->urls_array : json_encode( array_keys( MPG_DatasetModel::get_index( $project->id, 'permalinks' ) ) );
        }

        return is_array($results) ? $results : [];
    }
	/**
	 * Get the project ID by template ID.
	 *
	 * This function retrieves the project ID associated with a given template ID.
	 *
	 * @param int $template_id The ID of the template.
	 * @return int|false The project ID if found, false otherwise.
	 */
	public static function get_project_by_template_id( $template_id ): int {
		global $wpdb;
		$project_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . ' WHERE `template_id` = %d limit 1', $template_id ) );
		if ( ! $project_id ) {
			return 0;
		}

		return $project_id;
	}

	/**
	 * Get the modified date for virtual pages.
	 *
	 * This function retrieves the modified date for virtual pages based on the project's settings.
	 * It checks the `update_modified_on_sync` property of the project to determine the source of the modified date.
	 *
	 * @param object $project The project object.
	 * @return int|false The modified date as a timestamp if found, false otherwise.
	 */
	public static function get_vpage_modified_date( $project ) {
		if ( ! mpg_app()->is_license_of_type( 2 ) ) {
			return false;
		}
		// Check if the project has the 'update_modified_on_sync' property set
		if ( ! isset( $project->update_modified_on_sync ) ) {
			return false;
		}

		// If the 'update_modified_on_sync' property is set to 'onsync', return the project's updated_at timestamp
		if ( $project->update_modified_on_sync === 'onsync' ) {
			return MPG_Validators::is_timestamp( $project->updated_at ) ? $project->updated_at : false;
		}

		// If the 'update_modified_on_sync' property is set to 'column', retrieve the modified date from the dataset
		if ( $project->update_modified_on_sync === 'column' ) {
			$headers = MPG_ProjectModel::get_headers_from_project( $project );

			// Check if the 'modified_date' column exists in the headers
			$column_index = \MPG_ProjectModel::headers_have_column( $headers, 'modified_date' );
			if ( $column_index === false ) {
				return false;
			}

			// Get the current data row for the project
			$datarow = MPG_CoreModel::get_current_datarow( $project->id );

			// Return the modified date if it is a valid timestamp, otherwise try to convert it to a timestamp
			return MPG_Validators::is_timestamp( $datarow[ $column_index ] ) ? $datarow[ $column_index ] : ( strtotime( $datarow[ $column_index ] ) === false ? false : strtotime( $datarow[ $column_index ] ) );
		}

		return false;
	}

}
