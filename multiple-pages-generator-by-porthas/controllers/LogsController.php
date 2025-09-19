<?php
/**
 * Controller for managing log operations.
 * 
 * This controller handles writing, retrieving, and cleaning up logs.
 */
class MPG_LogsController
{
	/**
	 * Get the maximum number of log records to keep.
	 * 
	 * @return int Maximum number of log records, defaults to 1000.
	 */
	public static function get_max_log_records() {
		return apply_filters( 'mpg_max_log_records', 1000 );
	}

	/**
	 * Write a log entry and maintain the maximum number of records.
	 * 
	 * @param int|bool $project_id Project ID or false to detect from URL.
	 * @param string $level Log level.
	 * @param string $message Log message.
	 * @param string $file File that triggered the log.
	 * @param int $line Line number that triggered the log.
	 */
	public static function mpg_write( $project_id = false, $level = 'warning', $message = "", $file = __FILE__, $line = __LINE__ ) {
		global $wpdb;

		$requested_url = MPG_Helper::mpg_get_request_uri();

		if ( ! $project_id ) {
			$redirect_rules = MPG_CoreModel::mpg_get_redirect_rules( $requested_url );
			$project_id     = $redirect_rules['project_id'];
		}
		do_action( 'themeisle_log_event', MPG_NAME, $message, $level, __FILE__, __LINE__ );
		$message .= $message . ' homeurl: ' . home_url();
		$wpdb->insert( $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE, [
			'project_id' => intval( $project_id ),
			'level'      => esc_sql( $level ),
			'url'        => esc_sql( $requested_url ),
			'message'    => esc_sql( $message ),
			'datetime'   => date( 'Y-m-d H:i:s' )
		] );
		
		// Limit the number of log records
		self::limit_log_records();
	}
    public static function mpg_clear_log_by_project_id()
    {
	    MPG_Validators::nonce_check();

        try {

            global $wpdb;

            $project_id = isset($_POST['projectId']) ? (int) $_POST['projectId'] : null;

            if (!$project_id) {
                throw new Exception('Project ID is missing');
            }

            $table_name = $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE;

            $wpdb->delete($table_name, ['project_id' => $project_id]);

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

    /**
     * Limit the number of log records to the maximum specified by the filter.
     * This method is called automatically when a new log is written.
     */
    public static function limit_log_records() {
        global $wpdb;
        
        $max_records = self::get_max_log_records();
        $table_name = $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE;
        
        try {
            // Get total count
            $total_records = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            
            // Only proceed if we have more records than the limit
            if ($total_records > $max_records) {
                // Find IDs of oldest records to delete
                $records_to_delete = $total_records - $max_records;
                
                $ids_to_delete = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT id FROM {$table_name} ORDER BY datetime ASC LIMIT %d",
                        $records_to_delete
                    )
                );
                
                if (!empty($ids_to_delete)) {
                    // Delete the oldest records
                    $ids_string = implode(',', array_map('intval', $ids_to_delete));
                    $wpdb->query("DELETE FROM {$table_name} WHERE id IN ({$ids_string})");
                }
            }
        } catch (Exception $e) {
            do_action('themeisle_log_event', MPG_NAME, 'Error limiting log records: ' . $e->getMessage(), 'error', __FILE__, __LINE__);
        }
    }

    public static function mpg_get_log_by_project_id()
    {

	    MPG_Validators::nonce_check();
		if( ! current_user_can('editor') && ! current_user_can('administrator') ) {
			$response = rest_ensure_response( new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the logs.', 'multiple-pages-generator-by-porthas' ), array( 'status' => 401 ) ) );
			wp_send_json( $response );
		}

        try {

            global $wpdb;

            $project_id = (int) $_GET['projectId'];

            $draw = isset($_POST['draw']) ? (int) $_POST['draw'] : 0;
            $start = isset($_POST['start']) ? (int) $_POST['start'] : 1;
            $length = isset($_POST['length']) ? (int) $_POST['length'] : 10;


            $dataset_array = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE . ' WHERE project_id=' . $project_id);
            $dataset_partial_array = array_slice($dataset_array, $start, $length);


            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => count($dataset_array),
                'recordsFiltered' => count($dataset_array),
                'data' => $dataset_partial_array,
                'headers' =>  ['id', 'project_id', 'level', 'message', 'datetime']
            ]);
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'debug', __FILE__, __LINE__ );

            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        wp_die();
    }
}
