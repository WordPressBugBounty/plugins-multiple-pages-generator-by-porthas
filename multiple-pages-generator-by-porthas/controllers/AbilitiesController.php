<?php
/**
 * Registers the plugin abilities with the WordPress Abilities API.
 *
 * @package MPG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once realpath( __DIR__ ) . '/ProjectController.php';
require_once realpath( __DIR__ ) . '/LogsController.php';
require_once realpath( __DIR__ ) . '/../models/CoreModel.php';

/**
 * Thin ability wrappers over the project, dataset and index services.
 */
class MPG_AbilitiesController {

	const CATEGORY         = 'mpg';
	const MAX_LIST_LIMIT   = 100;
	const MAX_PREVIEW_ROWS = 50;
	const MAX_LOG_ENTRIES  = 50;

	/**
	 * Hook the registration callbacks. No-op when the Abilities API is unavailable.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Register the ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Multiple Pages Generator', 'multiple-pages-generator-by-porthas' ),
				'description' => __( 'Manage dataset-driven page generation projects.', 'multiple-pages-generator-by-porthas' ),
			)
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$project_id_schema = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'Project ID.', 'multiple-pages-generator-by-porthas' ),
		);

		$task_state_schema = array(
			'type' => 'string',
			'enum' => array( 'working', 'completed', 'failed', 'cancelled' ),
		);

		$progress_schema = array(
			'type'       => 'object',
			'properties' => array(
				'current' => array( 'type' => 'integer' ),
				'total'   => array( 'type' => 'integer' ),
				'message' => array( 'type' => 'string' ),
			),
		);

		$project_schema = array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'id'            => array( 'type' => 'integer' ),
				'name'          => array( 'type' => 'string' ),
				'entity_type'   => array( 'type' => 'string' ),
				'template_id'   => array( 'type' => 'integer' ),
				'url_pattern'   => array( 'type' => 'string' ),
				'headers'       => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'source'        => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
				'schedule'      => array(
					'type'                 => 'object',
					'additionalProperties' => true,
				),
			),
		);

		wp_register_ability(
			'mpg/list-projects',
			array(
				'label'               => __( 'List projects', 'multiple-pages-generator-by-porthas' ),
				'description'         => __( 'Find page generation projects, optionally filtered by name or template.', 'multiple-pages-generator-by-porthas' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'search'      => array(
							'type'        => 'string',
							'description' => __( 'Filter by project name.', 'multiple-pages-generator-by-porthas' ),
						),
						'template_id' => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Filter by template post ID.', 'multiple-pages-generator-by-porthas' ),
						),
						'limit'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_LIST_LIMIT,
							'default' => 20,
						),
						'offset'      => array(
							'type'    => 'integer',
							'minimum' => 0,
							'default' => 0,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'projects' => array(
							'type'  => 'array',
							'items' => $project_schema,
						),
						'total'    => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'list_projects' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => self::meta( true, false, true ),
			)
		);

		wp_register_ability(
			'mpg/get-project',
			array(
				'label'               => __( 'Get project', 'multiple-pages-generator-by-porthas' ),
				'description'         => __( 'Read a project: template, data source, dataset headers, URL pattern and sync schedule.', 'multiple-pages-generator-by-porthas' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'project_id' ),
					'properties'           => array(
						'project_id' => $project_id_schema,
					),
				),
				'output_schema'       => $project_schema,
				'execute_callback'    => array( __CLASS__, 'get_project' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => self::meta( true, false, true ),
			)
		);

		wp_register_ability(
			'mpg/upsert-project',
			array(
				'label'               => __( 'Create or update project', 'multiple-pages-generator-by-porthas' ),
				'description'         => __( 'Create a project (no project_id) or update one: name, template, direct-link data source, URL pattern and sync schedule. Changing the source or URL pattern rebuilds the project URLs.', 'multiple-pages-generator-by-porthas' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'project_id'                  => $project_id_schema,
						'name'                        => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'template_id'                 => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'ID of the post or page used as the template. Required when creating.', 'multiple-pages-generator-by-porthas' ),
						),
						'exclude_in_robots'           => array( 'type' => 'boolean' ),
						'participate_in_search'       => array( 'type' => 'boolean' ),
						'participate_in_default_loop' => array( 'type' => 'boolean' ),
						'source'                      => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => array( 'type', 'ref' ),
							'properties'           => array(
								'type'         => array(
									'type'        => 'string',
									'enum'        => array( MPG_Validators::SOURCE_TYPE_URL ),
									'description' => __( 'Only direct_link sources can be set here; file uploads need the project builder.', 'multiple-pages-generator-by-porthas' ),
								),
								'ref'          => array(
									'type'        => 'string',
									'description' => __( 'Public URL of a CSV, XLSX, ODS file or a Google Sheets document.', 'multiple-pages-generator-by-porthas' ),
								),
								'worksheet_id' => array(
									'type'        => 'integer',
									'minimum'     => 0,
									'description' => __( 'Google Sheets worksheet (gid).', 'multiple-pages-generator-by-porthas' ),
								),
							),
						),
						'url_pattern'                 => array(
							'type'        => 'string',
							'description' => __( 'URL format template using dataset columns, e.g. {{mpg_city}}/{{mpg_service}}.', 'multiple-pages-generator-by-porthas' ),
						),
						'space_replacer'              => array(
							'type' => 'string',
							'enum' => array( '-', '_', '~', '.', '/', '=' ),
						),
						'url_mode'                    => array(
							'type' => 'string',
							'enum' => array( 'both', 'with-trailing-slash', 'without-trailing-slash' ),
						),
						'schedule'                    => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'required'             => array( 'periodicity' ),
							'description'          => __( 'Source sync schedule (requires a Business plan or higher).', 'multiple-pages-generator-by-porthas' ),
							'properties'           => array(
								'periodicity'             => array(
									'type' => 'string',
									'enum' => array( 'now', 'ondemand', 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly' ),
								),
								'fetch_date_time'         => array(
									'type'        => 'string',
									'description' => __( 'First run, formatted Y/m/d H:i. Required for recurring schedules.', 'multiple-pages-generator-by-porthas' ),
								),
								'timezone'                => array(
									'type'        => 'string',
									'description' => __( 'Timezone identifier for fetch_date_time, e.g. Europe/Berlin.', 'multiple-pages-generator-by-porthas' ),
								),
								'notificate_about'        => array(
									'type'    => 'string',
									'enum'    => array( 'do-not-notify', 'errors-only', 'every-time' ),
									'default' => 'do-not-notify',
								),
								'notification_email'      => array( 'type' => 'string' ),
								'update_modified_on_sync' => array(
									'type' => 'string',
									'enum' => array( 'no', 'onsync', 'column' ),
								),
							),
						),
					),
				),
				'output_schema'       => $project_schema,
				'execute_callback'    => array( __CLASS__, 'upsert_project' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => self::meta( false, false, true ),
			)
		);

		wp_register_ability(
			'mpg/preview-rows',
			array(
				'label'               => __( 'Preview rows', 'multiple-pages-generator-by-porthas' ),
				'description'         => __( 'Preview the placeholder substitutions and generated URL for dataset rows, with URL conflicts and invalid values.', 'multiple-pages-generator-by-porthas' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'project_id' ),
					'properties'           => array(
						'project_id'  => $project_id_schema,
						'row_indexes' => array(
							'type'        => 'array',
							'maxItems'    => self::MAX_PREVIEW_ROWS,
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 0,
							),
							'description' => __( 'Zero-based data row indexes (header excluded). When omitted, the first rows are used.', 'multiple-pages-generator-by-porthas' ),
						),
						'limit'       => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::MAX_PREVIEW_ROWS,
							'default' => 5,
						),
						'url_pattern' => array(
							'type'        => 'string',
							'description' => __( 'Try a URL pattern without saving it. Defaults to the project pattern.', 'multiple-pages-generator-by-porthas' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total_rows' => array( 'type' => 'integer' ),
						'rows'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'row'           => array( 'type' => 'integer' ),
									'substitutions' => array(
										'type'                 => 'object',
										'additionalProperties' => true,
									),
									'path'          => array( 'type' => 'string' ),
									'url'           => array( 'type' => 'string' ),
									'conflicts'     => array(
										'type'  => 'array',
										'items' => array(
											'type'                 => 'object',
											'additionalProperties' => true,
										),
									),
									'invalid'       => array(
										'type'  => 'array',
										'items' => array(
											'type'                 => 'object',
											'additionalProperties' => true,
										),
									),
								),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'preview_rows' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => self::meta( true, false, true ),
			)
		);

		wp_register_ability(
			'mpg/rebuild-project',
			array(
				'label'               => __( 'Rebuild project', 'multiple-pages-generator-by-porthas' ),
				'description'         => __( 'Rebuild the project URL index from its dataset. With refresh_source the dataset is first re-fetched from the project source link. Runs synchronously and replaces the generated URLs; the returned job_id can be passed to mpg/get-project-status.', 'multiple-pages-generator-by-porthas' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'project_id' ),
					'properties'           => array(
						'project_id'     => $project_id_schema,
						'refresh_source' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'project_id' => array( 'type' => 'integer' ),
						'job_id'     => array(
							'type'        => 'string',
							'description' => __( 'Reference to the build that is now active, as "<project_id>:<build_id>".', 'multiple-pages-generator-by-porthas' ),
						),
						'state'      => $task_state_schema,
						'progress'   => $progress_schema,
						'total_rows' => array( 'type' => 'integer' ),
						'url_count'  => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'rebuild_project' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => self::meta(
					false,
					true,
					false,
					array(
						'mode'           => 'poll',
						'status_ability' => 'mpg/get-project-status',
					)
				),
			)
		);

		wp_register_ability(
			'mpg/get-project-status',
			array(
				'label'               => __( 'Get project status', 'multiple-pages-generator-by-porthas' ),
				'description'         => __( 'Read the build state of a project, named by project_id or by the job_id returned from mpg/rebuild-project: active build, row and URL counts, running rebuild, next scheduled sync and recent log entries.', 'multiple-pages-generator-by-porthas' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'project_id' => $project_id_schema,
						'job_id'     => array(
							'type'        => 'string',
							'description' => __( 'Job reference returned by mpg/rebuild-project. Replaces project_id.', 'multiple-pages-generator-by-porthas' ),
						),
						'log_limit'  => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => self::MAX_LOG_ENTRIES,
							'default' => 10,
						),
					),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'additionalProperties' => true,
					'properties'           => array(
						'project_id'  => array( 'type' => 'integer' ),
						'state'       => $task_state_schema,
						'progress'    => $progress_schema,
						'job_id'      => array( 'type' => 'string' ),
						'build_id'    => array( 'type' => 'string' ),
						'build_state' => array(
							'type' => 'string',
							'enum' => array( 'rebuilding', 'ready', 'not_built', 'no_dataset' ),
						),
						'total_rows'  => array( 'type' => 'integer' ),
						'url_count'   => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_project_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'meta'                => self::meta( true, false, true ),
			)
		);
	}

	/**
	 * Build the ability meta.
	 *
	 * @param bool  $readonly    Whether the ability only reads.
	 * @param bool  $destructive Whether the ability replaces or removes data.
	 * @param bool  $idempotent  Whether repeating the call yields the same state.
	 * @param array $task        Long-running task contract, when the ability has one.
	 *
	 * @return array
	 */
	private static function meta( $readonly, $destructive, $idempotent, array $task = array() ) {
		$meta = array(
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			),
			'show_in_rest' => true,
		);

		if ( ! empty( $task ) ) {
			$meta['task'] = $task;
		}

		return $meta;
	}

	/**
	 * Same capability the admin menu and every AJAX handler require.
	 *
	 * @see MPG_Validators::nonce_check()
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( MPG_MenuController::MENU_ROLE );
	}

	/**
	 * List projects.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function list_projects( $input = array() ) {
		global $wpdb;

		$input  = is_array( $input ) ? $input : array();
		$limit  = isset( $input['limit'] ) ? min( self::MAX_LIST_LIMIT, max( 1, (int) $input['limit'] ) ) : 20;
		$offset = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;
		$table  = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;

		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $input['search'] ) ) {
			$where[] = 'name LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( sanitize_text_field( $input['search'] ) ) . '%';
		}
		if ( ! empty( $input['template_id'] ) ) {
			$where[] = 'template_id = %d';
			$args[]  = (int) $input['template_id'];
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( empty( $args ) ? $total_sql : $wpdb->prepare( $total_sql, $args ) );

		$args[] = $limit;
		$args[] = $offset;
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", $args ) );
		// phpcs:enable

		$projects = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$projects[] = self::format_project( $row, false );
		}

		return array(
			'projects' => $projects,
			'total'    => $total,
		);
	}

	/**
	 * Read one project.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function get_project( $input ) {
		$project = self::load_project( $input );
		if ( is_wp_error( $project ) ) {
			return $project;
		}

		return self::format_project( $project, true );
	}

	/**
	 * Create or update a project.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function upsert_project( $input ) {
		$input      = is_array( $input ) ? $input : array();
		$project_id = isset( $input['project_id'] ) ? (int) $input['project_id'] : 0;
		$project    = null;

		// The project builder refuses to open on free installs that are over the project limit.
		if ( ! mpg_app()->can_edit() ) {
			return self::upgrade_required_error( __( 'The free plan is limited to one project. Upgrade to edit projects.', 'multiple-pages-generator-by-porthas' ), 'project-limit' );
		}

		if ( $project_id ) {
			$project = self::load_project( $input );
			if ( is_wp_error( $project ) ) {
				return $project;
			}
		}

		$main_fields = array();
		if ( isset( $input['name'] ) ) {
			$name = sanitize_text_field( $input['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'mpg_invalid_name', __( 'The project name cannot be empty.', 'multiple-pages-generator-by-porthas' ) );
			}
			$main_fields['name'] = $name;
		}

		if ( isset( $input['template_id'] ) ) {
			$template_id = (int) $input['template_id'];
			$template    = get_post( $template_id );
			$post_types  = wp_list_pluck( MPG_ProjectModel::mpg_get_custom_types(), 'name' );
			if ( ! $template || ! in_array( $template->post_type, $post_types, true ) ) {
				return new WP_Error( 'mpg_invalid_template', __( 'The template must be an existing post of a public post type.', 'multiple-pages-generator-by-porthas' ) );
			}
			$main_fields['template_id'] = $template_id;
			$main_fields['entity_type'] = $template->post_type;
		} elseif ( ! $project_id ) {
			return new WP_Error( 'mpg_missing_template', __( 'template_id is required to create a project.', 'multiple-pages-generator-by-porthas' ) );
		}

		foreach ( array( 'exclude_in_robots', 'participate_in_search', 'participate_in_default_loop' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$main_fields[ $flag ] = (bool) $input[ $flag ];
			}
		}

		$source = self::sanitize_source( isset( $input['source'] ) ? $input['source'] : null );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$schedule = self::sanitize_schedule( isset( $input['schedule'] ) ? $input['schedule'] : null );
		if ( is_wp_error( $schedule ) ) {
			return $schedule;
		}

		if ( isset( $input['space_replacer'] ) && ! in_array( $input['space_replacer'], array( '-', '_', '~', '.', '/', '=' ), true ) ) {
			return new WP_Error( 'mpg_invalid_space_replacer', __( 'Unsupported space replacer.', 'multiple-pages-generator-by-porthas' ) );
		}
		if ( isset( $input['url_mode'] ) && ! in_array( $input['url_mode'], array( 'both', 'with-trailing-slash', 'without-trailing-slash' ), true ) ) {
			return new WP_Error( 'mpg_invalid_url_mode', __( 'Unsupported URL mode.', 'multiple-pages-generator-by-porthas' ) );
		}

		if ( null !== $schedule && null === $source && ! $project_id ) {
			return new WP_Error( 'mpg_schedule_needs_source', __( 'A sync schedule needs a direct_link source.', 'multiple-pages-generator-by-porthas' ) );
		}

		try {
			if ( ! $project_id ) {
				$project_id = (int) MPG_ProjectModel::mpg_create_base_carcass(
					isset( $main_fields['name'] ) ? $main_fields['name'] : __( 'New project', 'multiple-pages-generator-by-porthas' ),
					$main_fields['entity_type'],
					$main_fields['template_id'],
					! empty( $main_fields['exclude_in_robots'] )
				);
				if ( ! $project_id ) {
					return new WP_Error( 'mpg_create_failed', __( 'The project could not be created.', 'multiple-pages-generator-by-porthas' ) );
				}
				unset( $main_fields['name'], $main_fields['entity_type'], $main_fields['template_id'] );
			}

			if ( isset( $main_fields['exclude_in_robots'] ) ) {
				$robots_template = isset( $main_fields['template_id'] ) ? $main_fields['template_id'] : ( $project ? (int) $project->template_id : (int) $input['template_id'] );
				MPG_ProjectModel::mpg_processing_robots_txt( $main_fields['exclude_in_robots'], $robots_template );
			}

			$fields = $main_fields;

			if ( null !== $source ) {
				$fields = array_merge( $fields, self::fetch_source( $project_id, $source ) );
			}

			if ( isset( $input['url_pattern'] ) ) {
				$fields['url_structure'] = str_replace( ' ', '_', sanitize_text_field( $input['url_pattern'] ) );
			}
			if ( isset( $input['space_replacer'] ) ) {
				$fields['space_replacer'] = $input['space_replacer'];
			}
			if ( isset( $input['url_mode'] ) ) {
				$fields['url_mode'] = $input['url_mode'];
			}

			if ( null !== $schedule ) {
				$current     = MPG_ProjectModel::get_project_by_id( $project_id );
				$direct_link = null !== $source ? $source['ref'] : '';
				if ( '' === $direct_link && ! empty( $current->schedule_source_link ) ) {
					$direct_link = $current->schedule_source_link;
				}
				if ( '' === $direct_link && ! empty( $current->original_file_url ) ) {
					$direct_link = $current->original_file_url;
				}
				if ( '' === $direct_link ) {
					return new WP_Error( 'mpg_schedule_needs_source', __( 'A sync schedule needs a direct_link source.', 'multiple-pages-generator-by-porthas' ), array( 'project_id' => $project_id ) );
				}
				$fields = array_merge( $fields, self::apply_schedule( $project_id, $current, $direct_link, $schedule ) );
			}

			// URL-affecting changes rebuild the index, as the builder "Save" does.
			$needs_rebuild = null !== $source || isset( $fields['url_structure'] ) || isset( $fields['space_replacer'] );
			if ( $needs_rebuild && ( null !== $source || '' !== MPG_DatasetModel::get_dataset_path_by_project( $project_id ) ) ) {
				$fields['urls_array'] = true;
			}

			if ( ! empty( $fields ) ) {
				MPG_ProjectModel::mpg_update_project_by_id( $project_id, $fields, null !== $source );
			}

			$project = MPG_ProjectModel::get_project_by_id( $project_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'mpg_upsert_failed', $e->getMessage(), array( 'project_id' => $project_id ) );
		}

		if ( empty( $project ) ) {
			return new WP_Error( 'mpg_project_not_found', __( 'Project not found', 'multiple-pages-generator-by-porthas' ) );
		}

		return self::format_project( $project, true );
	}

	/**
	 * Preview substitutions and URLs for dataset rows.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function preview_rows( $input ) {
		$project = self::load_project( $input );
		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$project_id = (int) $project->id;
		$limit      = isset( $input['limit'] ) ? min( self::MAX_PREVIEW_ROWS, max( 1, (int) $input['limit'] ) ) : 5;
		$indexes    = array();
		if ( ! empty( $input['row_indexes'] ) && is_array( $input['row_indexes'] ) ) {
			$indexes = array_slice( array_values( array_unique( array_map( 'absint', $input['row_indexes'] ) ) ), 0, self::MAX_PREVIEW_ROWS );
		}

		try {
			$dataset_path = MPG_DatasetModel::get_dataset_path_by_project( $project );
			if ( empty( $dataset_path ) ) {
				return new WP_Error( 'mpg_no_dataset', __( 'Project dataset not found', 'multiple-pages-generator-by-porthas' ) );
			}

			// Raw header row, as MPG_DatasetModel::create_index() reads it, so columns line up with the index.
			$headers = MPG_DatasetModel::read_dataset( $dataset_path, true, $project_id );
			if ( empty( $headers ) || ! is_array( $headers ) ) {
				return new WP_Error( 'mpg_no_headers', __( 'The CSV file contains empty or invalid headers. Please check and ensure all headers are correct.', 'multiple-pages-generator-by-porthas' ) );
			}

			if ( empty( $indexes ) ) {
				$indexes = range( 0, $limit - 1 );
			}

			// Row 0 of the dataset is the header, so data row N lives at dataset offset N + 1.
			$rows       = array();
			$total_rows = 0;
			if ( MPG_DatasetModel::is_dataset_chunked( $project_id ) ) {
				$meta       = MPG_DatasetModel::get_dataset_chunks_meta( $project_id );
				$total_rows = isset( $meta['total_rows'] ) ? max( 0, (int) $meta['total_rows'] - 1 ) : 0;
				foreach ( $indexes as $index ) {
					$found = MPG_DatasetModel::get_dataset_chunks( $project_id, false, 1, $index + 1 );
					if ( ! empty( $found[0] ) ) {
						$rows[ $index ] = $found[0];
					}
				}
			} else {
				$dataset    = MPG_DatasetModel::read_dataset( $dataset_path );
				$total_rows = max( 0, count( $dataset ) - 1 );
				foreach ( $indexes as $index ) {
					if ( isset( $dataset[ $index + 1 ] ) ) {
						$rows[ $index ] = $dataset[ $index + 1 ];
					}
				}
				unset( $dataset );
			}

			$url_structure  = isset( $input['url_pattern'] ) ? str_replace( ' ', '_', sanitize_text_field( $input['url_pattern'] ) ) : (string) $project->url_structure;
			$space_replacer = ! empty( $project->space_replacer ) ? $project->space_replacer : MPG_Constant::DEFAULT_SPACE_REPLACER;
			$placeholders   = self::placeholders( $headers );
			$is_saved       = (string) $project->url_structure === $url_structure;

			// Placeholders of the pattern that do not match a dataset column.
			$unknown = array();
			if ( preg_match_all( '/{{(.*?)}}/', $url_structure, $matches ) ) {
				$unknown = array_values( array_diff( array_unique( $matches[0] ), $placeholders ) );
			}

			$own_index      = $is_saved ? MPG_DatasetModel::get_index( $project_id, 'permalinks' ) : array();
			$other_projects = self::other_project_ids( $project_id );
			$seen           = array();
			$result         = array();

			foreach ( $indexes as $index ) {
				if ( ! isset( $rows[ $index ] ) ) {
					continue;
				}
				$row  = array_values( $rows[ $index ] );
				$path = MPG_ProjectModel::mpg_generate_url_for_row( $row, $headers, $url_structure, $space_replacer );
				if ( 'without-trailing-slash' === $project->url_mode ) {
					$path = rtrim( $path, '/' );
				}

				$substitutions = array();
				$invalid       = array();
				foreach ( $placeholders as $column => $placeholder ) {
					$value                         = isset( $row[ $column ] ) ? wp_strip_all_tags( (string) $row[ $column ] ) : '';
					$substitutions[ $placeholder ] = $value;
					if ( '' === trim( $value ) && false !== strpos( $url_structure, $placeholder ) ) {
						$invalid[] = array(
							'code'        => 'empty_url_value',
							'placeholder' => $placeholder,
						);
					}
				}
				foreach ( $unknown as $placeholder ) {
					$invalid[] = array(
						'code'        => 'unknown_placeholder',
						'placeholder' => $placeholder,
					);
				}
				if ( '' === trim( $path, '/' ) ) {
					$invalid[] = array( 'code' => 'empty_url' );
				}

				$conflicts = array();
				$key       = trailingslashit( $path );
				if ( isset( $seen[ $key ] ) ) {
					$conflicts[] = array(
						'code' => 'duplicate_row',
						'row'  => $seen[ $key ],
					);
				}
				$seen[ $key ] = $index;

				// The index keeps one row per URL: a different winner means another row shares this URL.
				if ( isset( $own_index[ $key ]['chunk'], $own_index[ $key ]['offset'] ) ) {
					$winner = MPG_DatasetModel::get_dataset_row( $project_id, $own_index[ $key ]['chunk'], $own_index[ $key ]['offset'] );
					if ( ! empty( $winner ) && array_values( $winner ) !== $row ) {
						$conflicts[] = array( 'code' => 'duplicate_in_project' );
					}
				}

				foreach ( $other_projects as $other_id ) {
					$other_index = MPG_DatasetModel::get_index( $other_id, 'permalinks' );
					if ( isset( $other_index[ $key ] ) ) {
						$conflicts[] = array(
							'code'       => 'other_project',
							'project_id' => $other_id,
						);
					}
				}

				$url     = MPG_CoreModel::path_to_url( $path );
				$post_id = '' !== trim( $path, '/' ) ? url_to_postid( $url ) : 0;
				if ( $post_id ) {
					$conflicts[] = array(
						'code'    => 'existing_content',
						'post_id' => $post_id,
					);
				}

				$result[] = array(
					'row'           => $index,
					'substitutions' => $substitutions,
					'path'          => $path,
					'url'           => $url,
					'conflicts'     => $conflicts,
					'invalid'       => $invalid,
				);
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'mpg_preview_failed', $e->getMessage() );
		}

		return array(
			'total_rows' => $total_rows,
			'rows'       => $result,
		);
	}

	/**
	 * Rebuild the project index, optionally re-fetching the source first.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function rebuild_project( $input ) {
		$project = self::load_project( $input );
		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$project_id = (int) $project->id;

		try {
			$source_path = MPG_DatasetModel::get_dataset_path_by_project( $project );
			if ( empty( $project->source_path ) || empty( $source_path ) ) {
				return new WP_Error( 'mpg_no_dataset', __( 'Your project has not properly configured source file', 'multiple-pages-generator-by-porthas' ) );
			}

			$fields = array( 'urls_array' => true );

			if ( ! empty( $input['refresh_source'] ) ) {
				$link = ! empty( $project->schedule_source_link ) ? $project->schedule_source_link : ( isset( $project->original_file_url ) ? $project->original_file_url : '' );
				// download_file() treats anything that is not a valid URL as a local path to move.
				if ( empty( $link ) || false === wp_http_validate_url( $link ) ) {
					return new WP_Error( 'mpg_no_source_link', __( 'Your project has not properly configured source.', 'multiple-pages-generator-by-porthas' ) );
				}

				// Same sequence as the scheduled sync (MPG_ProjectController::mpg_scheduled_cron_handler()).
				$direct_link = MPG_Helper::mpg_get_direct_csv_link( $link, ! empty( $project->worksheet_id ) ? $project->worksheet_id : null );
				if ( empty( $direct_link ) || false === wp_http_validate_url( $direct_link ) || ! MPG_DatasetModel::download_file( $direct_link, $source_path ) ) {
					return new WP_Error( 'mpg_download_failed', __( 'Document is not accesible.', 'multiple-pages-generator-by-porthas' ) );
				}

				$fields = array_merge( $fields, MPG_ProjectModel::mpg_prepare_headers_sync( $project, $source_path ) );
			} elseif ( ! file_exists( $source_path ) ) {
				return new WP_Error( 'mpg_dataset_missing', __( 'The dataset file is missing.', 'multiple-pages-generator-by-porthas' ) );
			}

			MPG_ProjectModel::mpg_update_project_by_id( $project_id, $fields, true );
			MPG_DatasetModel::clear_project_cache( $project_id );

			if ( ! empty( $input['refresh_source'] ) ) {
				$urls = array_keys( MPG_DatasetModel::get_index( $project_id, 'permalinks' ) );
				MPG_SitemapGenerator::maybe_create_sitemap( $project, $urls );
			}
		} catch ( Exception $e ) {
			MPG_LogsController::mpg_write( $project_id, 'warning', $e->getMessage(), __FILE__, __LINE__ );

			return new WP_Error( 'mpg_rebuild_failed', $e->getMessage() );
		}

		$meta       = MPG_DatasetModel::get_dataset_chunks_meta( $project_id );
		$total_rows = isset( $meta['total_rows'] ) ? (int) $meta['total_rows'] : 0;

		return array(
			'project_id' => $project_id,
			'job_id'     => $project_id . ':' . MPG_DatasetModel::get_active_build_id( $project_id ),
			'state'      => 'completed',
			'progress'   => array(
				'current' => $total_rows,
				'total'   => $total_rows,
				'message' => __( 'Rebuild finished.', 'multiple-pages-generator-by-porthas' ),
			),
			'total_rows' => $total_rows,
			'url_count'  => isset( $meta['permalink_count'] ) ? (int) $meta['permalink_count'] : 0,
		);
	}

	/**
	 * Read the build state of a project.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public static function get_project_status( $input ) {
		global $wpdb;

		$input     = is_array( $input ) ? $input : array();
		$job_build = '';
		if ( isset( $input['job_id'] ) && '' !== $input['job_id'] ) {
			// A job_id is "<project_id>:<build_id>", as returned by rebuild_project().
			if ( ! is_string( $input['job_id'] ) || ! preg_match( '/^([1-9][0-9]*):([A-Za-z0-9._-]+)$/', $input['job_id'], $job_parts ) ) {
				return new WP_Error( 'mpg_invalid_job_id', __( 'Invalid job ID', 'multiple-pages-generator-by-porthas' ) );
			}
			if ( isset( $input['project_id'] ) && (int) $input['project_id'] !== (int) $job_parts[1] ) {
				return new WP_Error( 'mpg_invalid_job_id', __( 'Invalid job ID', 'multiple-pages-generator-by-porthas' ) );
			}
			$input['project_id'] = (int) $job_parts[1];
			$job_build           = $job_parts[2];
		}

		$project = self::load_project( $input );
		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$project_id   = (int) $project->id;
		$dataset_path = MPG_DatasetModel::get_dataset_path_by_project( $project );
		$build_id     = MPG_DatasetModel::get_active_build_id( $project_id );
		$meta         = MPG_DatasetModel::is_dataset_chunked( $project_id ) ? MPG_DatasetModel::get_dataset_chunks_meta( $project_id ) : false;
		$meta         = is_array( $meta ) ? $meta : array();

		$lock       = json_decode( (string) get_option( 'mpg_index_lock_' . $project_id, '' ), true );
		$rebuilding = is_array( $lock ) && isset( $lock['expires_at'] ) && (int) $lock['expires_at'] > time();

		if ( $rebuilding ) {
			$build_state = 'rebuilding';
		} elseif ( empty( $dataset_path ) ) {
			$build_state = 'no_dataset';
		} elseif ( empty( $meta ) ) {
			$build_state = 'not_built';
		} else {
			$build_state = 'ready';
		}

		$total_rows = isset( $meta['total_rows'] ) ? (int) $meta['total_rows'] : 0;
		$url_count  = isset( $meta['permalink_count'] ) ? (int) $meta['permalink_count'] : 0;

		// A job_id is only handed out once its build is active, so that build being active, or replaced
		// by a newer one, means the job finished. Without a job_id the state describes the project.
		if ( '' !== $job_build && false !== $build_id && $job_build === (string) $build_id ) {
			$state   = 'completed';
			$message = __( 'Rebuild finished.', 'multiple-pages-generator-by-porthas' );
		} elseif ( $rebuilding ) {
			$state   = 'working';
			$message = __( 'A rebuild is running.', 'multiple-pages-generator-by-porthas' );
		} elseif ( false !== $build_id && ! empty( $meta ) ) {
			$state   = 'completed';
			$message = '' !== $job_build ? __( 'Rebuild finished; a newer build is now active.', 'multiple-pages-generator-by-porthas' ) : __( 'Rebuild finished.', 'multiple-pages-generator-by-porthas' );
		} else {
			$state   = 'failed';
			$message = __( 'The project has no active build.', 'multiple-pages-generator-by-porthas' );
		}

		$status = array(
			'project_id'          => $project_id,
			'state'               => $state,
			'progress'            => array(
				'current' => 'working' === $state ? 0 : $total_rows,
				'total'   => $total_rows,
				'message' => $message,
			),
			'job_id'              => '' !== $job_build ? $project_id . ':' . $job_build : ( false !== $build_id ? $project_id . ':' . $build_id : '' ),
			'build_id'            => (string) $build_id,
			'build_state'         => $build_state,
			'built_at'            => isset( $meta['created_at'] ) ? (string) $meta['created_at'] : '',
			'total_rows'          => $total_rows,
			'url_count'           => $url_count,
			'duplicate_url_count' => max( 0, $total_rows - $url_count ),
			'dataset_file_exists' => ! empty( $dataset_path ) && file_exists( $dataset_path ),
			'updated_at'          => ! empty( $project->updated_at ) ? gmdate( 'c', (int) $project->updated_at ) : '',
			'schedule'            => self::format_schedule( $project ),
			'sitemap_url'         => ! empty( $project->sitemap_url ) ? (string) $project->sitemap_url : '',
		);

		// The log viewer is limited to editors and administrators (MPG_LogsController::mpg_get_log_by_project_id()).
		$log_limit = isset( $input['log_limit'] ) ? min( self::MAX_LOG_ENTRIES, max( 0, (int) $input['log_limit'] ) ) : 10;
		if ( $log_limit > 0 && ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) ) {
			$logs_table = $wpdb->prefix . MPG_Constant::MPG_LOGS_TABLE;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$logs           = $wpdb->get_results( $wpdb->prepare( "SELECT level, url, message, datetime FROM {$logs_table} WHERE project_id = %d ORDER BY id DESC LIMIT %d", $project_id, $log_limit ), ARRAY_A );
			$status['logs'] = is_array( $logs ) ? $logs : array();
		}

		return $status;
	}

	/**
	 * Load the project named by the input.
	 *
	 * @param mixed $input Ability input.
	 *
	 * @return stdClass|WP_Error
	 */
	private static function load_project( $input ) {
		$project_id = is_array( $input ) && isset( $input['project_id'] ) ? (int) $input['project_id'] : 0;
		if ( $project_id < 1 ) {
			return new WP_Error( 'mpg_missing_project_id', __( 'Missing project ID', 'multiple-pages-generator-by-porthas' ) );
		}

		try {
			$project = MPG_ProjectModel::get_project_by_id( $project_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'mpg_project_error', $e->getMessage() );
		}

		if ( empty( $project ) ) {
			return new WP_Error( 'mpg_project_not_found', __( 'Project not found', 'multiple-pages-generator-by-porthas' ) );
		}

		return $project;
	}

	/**
	 * IDs of every project except the given one.
	 *
	 * @param int $project_id Project to leave out.
	 *
	 * @return int[]
	 */
	private static function other_project_ids( $project_id ) {
		global $wpdb;

		$table = $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE id != %d ORDER BY id DESC LIMIT 100", $project_id ) );

		return array_map( 'intval', is_array( $ids ) ? $ids : array() );
	}

	/**
	 * Placeholders per column, named as MPG_ProjectModel::mpg_generate_url_for_row() names them.
	 *
	 * @param array $headers Dataset headers.
	 *
	 * @return string[]
	 */
	private static function placeholders( array $headers ) {
		$placeholders = array();
		foreach ( array_values( $headers ) as $column => $raw_header ) {
			$header                  = strtolower( str_replace( ' ', '_', (string) $raw_header ) );
			$placeholders[ $column ] = 0 === strpos( $header, 'mpg_' ) ? '{{' . $header . '}}' : '{{mpg_' . $header . '}}';
		}

		return $placeholders;
	}

	/**
	 * Validate the source input.
	 *
	 * @param mixed $source Raw source input.
	 *
	 * @return array|null|WP_Error
	 */
	private static function sanitize_source( $source ) {
		if ( null === $source ) {
			return null;
		}
		if ( ! is_array( $source ) || empty( $source['type'] ) || empty( $source['ref'] ) ) {
			return new WP_Error( 'mpg_invalid_source', __( 'The source needs a type and a ref.', 'multiple-pages-generator-by-porthas' ) );
		}
		if ( MPG_Validators::SOURCE_TYPE_URL !== $source['type'] ) {
			return new WP_Error( 'mpg_unsupported_source', __( 'Only direct_link sources are supported. Upload dataset files from the project builder.', 'multiple-pages-generator-by-porthas' ) );
		}

		$ref = esc_url_raw( trim( (string) $source['ref'] ) );
		// download_file() treats anything that is not a valid URL as a local path to move.
		if ( '' === $ref || false === wp_http_validate_url( $ref ) ) {
			return new WP_Error( 'mpg_invalid_source_url', __( 'The source URL is not valid.', 'multiple-pages-generator-by-porthas' ) );
		}

		return array(
			'type'         => MPG_Validators::SOURCE_TYPE_URL,
			'ref'          => $ref,
			'worksheet_id' => isset( $source['worksheet_id'] ) ? absint( $source['worksheet_id'] ) : 0,
		);
	}

	/**
	 * Download a direct-link source into the project dataset file.
	 *
	 * Same steps as the builder "Fetch and use" flow: MPG_DatasetController::mpg_download_file_by_link()
	 * followed by MPG_ProjectController::mpg_upsert_project_source_block().
	 *
	 * @param int   $project_id Project ID.
	 * @param array $source     Sanitized source.
	 *
	 * @return array Project fields to store.
	 * @throws Exception When the file cannot be fetched or read.
	 */
	private static function fetch_source( $project_id, array $source ) {
		$direct_link = MPG_Helper::mpg_get_direct_csv_link( $source['ref'], $source['worksheet_id'] ? $source['worksheet_id'] : null );
		if ( empty( $direct_link ) || false === wp_http_validate_url( $direct_link ) ) {
			throw new Exception( __( 'Document is not accesible.', 'multiple-pages-generator-by-porthas' ) );
		}

		$ext = strtolower( MPG_Helper::mpg_get_extension_by_path( $direct_link ) );
		if ( ! in_array( $ext, array( 'csv', 'xls', 'xlsx', 'ods' ), true ) ) {
			throw new Exception( __( 'Unsupported file extension', 'multiple-pages-generator-by-porthas' ) );
		}

		$base_path = MPG_DatasetModel::uploads_base_path();
		$temp_path = $base_path . 'temp-ability-' . $project_id . '.' . $ext;
		if ( ! MPG_DatasetModel::download_file( $direct_link, $temp_path ) ) {
			throw new Exception( __( 'Document is not accesible.', 'multiple-pages-generator-by-porthas' ) );
		}

		$headers = MPG_DatasetController::get_headers( $temp_path );
		if ( empty( $headers ) || ! is_array( $headers ) ) {
			wp_delete_file( $temp_path );
			throw new Exception( __( 'The CSV file contains empty or invalid headers. Please check and ensure all headers are correct.', 'multiple-pages-generator-by-porthas' ) );
		}

		$new_path = MPG_ProjectController::get_project_path( $project_id, basename( $temp_path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $temp_path, $new_path ) ) {
			wp_delete_file( $temp_path );
			// translators: $s the name of the new location.
			throw new Exception( sprintf( __( 'The file cannot be moved to %s. Ensure the folder has the correct permissions.', 'multiple-pages-generator-by-porthas' ), $new_path ) );
		}

		// Drop a previous dataset stored under another extension.
		$stale_files = glob( $base_path . $project_id . '.*' );
		foreach ( is_array( $stale_files ) ? $stale_files : array() as $project_file ) {
			if ( $project_file !== $new_path ) {
				wp_delete_file( $project_file );
			}
		}

		$fields = array(
			'source_type'       => MPG_Validators::SOURCE_TYPE_URL,
			'source_path'       => basename( $new_path ),
			'original_file_url' => $source['ref'],
			'headers'           => wp_json_encode( $headers ),
		);
		if ( $source['worksheet_id'] ) {
			$fields['worksheet_id'] = $source['worksheet_id'];
		}

		return $fields;
	}

	/**
	 * Build the "upgrade required" error, carrying the product upgrade link.
	 *
	 * Same URL as MPG_Premium::get_upgrade_url(), tagged with the `mcp` location.
	 *
	 * @param string $message Translated reason.
	 * @param string $area    Gated feature key, used as the UTM campaign.
	 *
	 * @return WP_Error
	 */
	private static function upgrade_required_error( $message, $area ) {
		$upgrade_url = 'https://themeisle.com/plugins/multi-pages-generator/upgrade';
		if ( function_exists( 'tsdk_utmify' ) ) {
			$upgrade_url = tsdk_utmify( tsdk_translate_link( $upgrade_url ), $area, 'mcp' );
		}

		return new WP_Error(
			'mpg_upgrade_required',
			// translators: %1$s: the reason the request was refused, %2$s: the upgrade URL.
			sprintf( __( '%1$s Upgrade: %2$s', 'multiple-pages-generator-by-porthas' ), $message, $upgrade_url ),
			array( 'upgrade_url' => $upgrade_url )
		);
	}

	/**
	 * Validate the schedule input.
	 *
	 * @param mixed $schedule Raw schedule input.
	 *
	 * @return array|null|WP_Error
	 */
	private static function sanitize_schedule( $schedule ) {
		if ( null === $schedule ) {
			return null;
		}

		// Scheduled sync is gated the same way in pro/project_utils.php.
		if ( ! mpg_app()->is_license_of_type( 2 ) ) {
			return self::upgrade_required_error( __( 'Scheduled source sync is not available on your plan.', 'multiple-pages-generator-by-porthas' ), 'scheduled-sync' );
		}

		$periodicity = is_array( $schedule ) && isset( $schedule['periodicity'] ) ? sanitize_text_field( $schedule['periodicity'] ) : '';
		if ( ! in_array( $periodicity, array( 'now', 'ondemand', 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly' ), true ) ) {
			return new WP_Error( 'mpg_invalid_schedule', __( 'Unsupported schedule periodicity.', 'multiple-pages-generator-by-porthas' ) );
		}

		$notificate_about = isset( $schedule['notificate_about'] ) ? sanitize_text_field( $schedule['notificate_about'] ) : 'do-not-notify';
		if ( ! in_array( $notificate_about, array( 'do-not-notify', 'errors-only', 'every-time' ), true ) ) {
			return new WP_Error( 'mpg_invalid_schedule', __( 'Unsupported notification mode.', 'multiple-pages-generator-by-porthas' ) );
		}

		$update_modified = isset( $schedule['update_modified_on_sync'] ) ? sanitize_text_field( $schedule['update_modified_on_sync'] ) : 'no-update';
		if ( ! in_array( $update_modified, array( 'no', 'no-update', 'onsync', 'column' ), true ) ) {
			return new WP_Error( 'mpg_invalid_schedule', __( 'Unsupported update_modified_on_sync value.', 'multiple-pages-generator-by-porthas' ) );
		}

		$email = isset( $schedule['notification_email'] ) ? sanitize_email( $schedule['notification_email'] ) : '';
		if ( 'do-not-notify' !== $notificate_about && ! is_email( $email ) ) {
			return new WP_Error( 'mpg_invalid_schedule', __( 'Please enter a valid email address.', 'multiple-pages-generator-by-porthas' ) );
		}

		$fetch_date_time = isset( $schedule['fetch_date_time'] ) ? sanitize_text_field( $schedule['fetch_date_time'] ) : '';
		$timezone        = isset( $schedule['timezone'] ) ? sanitize_text_field( $schedule['timezone'] ) : '';
		$recurring       = ! in_array( $periodicity, array( 'now', 'ondemand' ), true );
		if ( $recurring ) {
			if ( '' === $timezone || ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
				return new WP_Error( 'mpg_invalid_schedule', __( 'A valid timezone is required for recurring schedules.', 'multiple-pages-generator-by-porthas' ) );
			}
			if ( false === DateTime::createFromFormat( '!Y/m/d H:i', $fetch_date_time, new DateTimeZone( $timezone ) ) ) {
				return new WP_Error( 'mpg_invalid_schedule', __( 'The scheduled sync date is invalid.', 'multiple-pages-generator-by-porthas' ) );
			}
		}

		return array(
			'periodicity'             => $periodicity,
			'notificate_about'        => $notificate_about,
			'notification_email'      => '' !== $email ? $email : null,
			'update_modified_on_sync' => $update_modified,
			'fetch_date_time'         => $recurring ? $fetch_date_time : null,
			'timezone'                => $recurring ? $timezone : null,
		);
	}

	/**
	 * Replace the cron event and return the schedule fields to store.
	 *
	 * Mirrors the schedule part of MPG_ProjectController::mpg_upsert_project_url_block().
	 *
	 * @param int    $project_id  Project ID.
	 * @param stdClass $project     Project before the change.
	 * @param string $direct_link Source link to sync from.
	 * @param array  $schedule    Sanitized schedule.
	 *
	 * @return array
	 * @throws Exception When the cron event cannot be created.
	 */
	private static function apply_schedule( $project_id, $project, $direct_link, array $schedule ) {
		MPG_ProjectController::mpg_replace_project_cron_event(
			$project_id,
			$project,
			$direct_link,
			$schedule['notificate_about'],
			$schedule['periodicity'],
			$schedule['notification_email'],
			$schedule['fetch_date_time'],
			$schedule['timezone']
		);

		$fields = array(
			'schedule_periodicity'    => 'now' !== $schedule['periodicity'] ? $schedule['periodicity'] : null,
			'update_modified_on_sync' => $schedule['update_modified_on_sync'],
		);
		if ( 'now' !== $schedule['periodicity'] ) {
			$fields['schedule_source_link']        = $direct_link;
			$fields['schedule_notificate_about']   = $schedule['notificate_about'];
			$fields['schedule_notification_email'] = $schedule['notification_email'];
		}

		return $fields;
	}

	/**
	 * Schedule part of a project.
	 *
	 * @param stdClass $project Project row.
	 *
	 * @return array
	 */
	private static function format_schedule( $project ) {
		$next_run = false;
		if ( ! empty( $project->schedule_periodicity ) && ! empty( $project->schedule_source_link ) && ! empty( $project->schedule_notificate_about ) ) {
			$next_run = wp_next_scheduled(
				'mpg_schedule_execution',
				array(
					(int) $project->id,
					$project->schedule_source_link,
					$project->schedule_notificate_about,
					$project->schedule_periodicity,
					isset( $project->schedule_notification_email ) ? $project->schedule_notification_email : null,
				)
			);
		}

		return array(
			'periodicity'             => ! empty( $project->schedule_periodicity ) ? (string) $project->schedule_periodicity : '',
			'source_link'             => ! empty( $project->schedule_source_link ) ? (string) $project->schedule_source_link : '',
			'notificate_about'        => ! empty( $project->schedule_notificate_about ) ? (string) $project->schedule_notificate_about : '',
			'notification_email'      => ! empty( $project->schedule_notification_email ) ? (string) $project->schedule_notification_email : '',
			'update_modified_on_sync' => ! empty( $project->update_modified_on_sync ) ? (string) $project->update_modified_on_sync : '',
			'next_run'                => $next_run ? gmdate( 'c', (int) $next_run ) : '',
		);
	}

	/**
	 * Shape a project row for output.
	 *
	 * @param stdClass $project Project row.
	 * @param bool   $full    Include headers and sitemap settings.
	 *
	 * @return array
	 */
	private static function format_project( $project, $full ) {
		$project_id = (int) $project->id;
		$meta       = MPG_DatasetModel::is_dataset_chunked( $project_id ) ? MPG_DatasetModel::get_dataset_chunks_meta( $project_id ) : false;

		$data = array(
			'id'             => $project_id,
			'name'           => (string) $project->name,
			'entity_type'    => (string) $project->entity_type,
			'template_id'    => (int) $project->template_id,
			'template_title' => $project->template_id ? get_the_title( (int) $project->template_id ) : '',
			'source'         => array(
				'type' => ! empty( $project->source_type ) ? (string) $project->source_type : ( ! empty( $project->original_file_url ) ? MPG_Validators::SOURCE_TYPE_URL : MPG_Validators::SOURCE_TYPE_UPLOAD ),
				'file' => ! empty( $project->source_path ) ? basename( $project->source_path ) : '',
				'ref'  => ! empty( $project->original_file_url ) ? (string) $project->original_file_url : '',
			),
			'url_pattern'    => (string) $project->url_structure,
			'space_replacer' => (string) $project->space_replacer,
			'url_mode'       => isset( $project->url_mode ) ? (string) $project->url_mode : MPG_Constant::DEFAULT_URL_MODE,
			'url_count'      => is_array( $meta ) && isset( $meta['permalink_count'] ) ? (int) $meta['permalink_count'] : 0,
			'schedule'       => self::format_schedule( $project ),
			'created_at'     => ! empty( $project->created_at ) ? gmdate( 'c', (int) $project->created_at ) : '',
			'updated_at'     => ! empty( $project->updated_at ) ? gmdate( 'c', (int) $project->updated_at ) : '',
			'edit_url'       => admin_url( 'admin.php?page=mpg-project-builder&action=edit_project&id=' . $project_id ),
		);

		if ( ! $full ) {
			return $data;
		}

		$headers = array();
		try {
			$headers = array_values( array_map( 'strval', MPG_ProjectModel::get_headers_from_project( $project ) ) );
		} catch ( Exception $e ) {
			$headers = array();
		}

		$data['headers']                     = $headers;
		$data['placeholders']                = array_values( self::placeholders( $headers ) );
		$data['source']['worksheet_id']      = ! empty( $project->worksheet_id ) ? (int) $project->worksheet_id : 0;
		$data['exclude_in_robots']           = ! empty( $project->exclude_in_robots );
		$data['participate_in_search']       = ! empty( $project->participate_in_search );
		$data['participate_in_default_loop'] = ! empty( $project->participate_in_default_loop );
		$data['apply_condition']             = ! empty( $project->apply_condition ) ? (string) $project->apply_condition : '';
		$data['sitemap_url']                 = ! empty( $project->sitemap_url ) ? (string) $project->sitemap_url : '';

		return $data;
	}
}
