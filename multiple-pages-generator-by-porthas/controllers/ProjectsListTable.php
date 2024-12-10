<?php
/**
 * Projects list.
 *
 * @package MPG
 */

// Include WP_List_Table class file.
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

// If check class exists or not.
if ( ! class_exists( 'Projects_List_Table' ) ) {

	/**
	 * Declare Class `Projects_List_Table`
	 */
	class Projects_List_Table extends WP_List_Table {

		/**
		 * Pagination per page.
		 *
		 * @var $per_page.
		 */
		public $per_page;

		/**
		 * Public constructor.
		 */
		public function __construct() {

			// Set parent defaults.
			parent::__construct();

			$this->per_page = $this->get_items_per_page( 'mpg_projects_per_page', 20 );
		}

		/**
		 * Entry Data
		 *
		 * @param string $search_by_name Project search by name.
		 * @return Records
		 */
		public function get_projects( $search_by_name ) {
			$projects_list_manage = new ProjectsListManage();
			$per_page             = $this->per_page;
			$data                 = $projects_list_manage->projects_list( $search_by_name, $per_page );
			return $data;
		}

		/**
		 * Prepare items
		 */
		public function prepare_items() {
			$search_by_name = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : ''; // phpcs:ignore
			$this->items    = $this->get_projects( $search_by_name );

			$per_page      = $this->per_page;
			$total_entries = new ProjectsListManage();
			$total_item    = $total_entries->total_projects();
			$this->set_pagination_args(
				array(
					'total_items' => $total_item,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total_item / $per_page ),
				)
			);

			$sortable              = $this->get_sortable_columns();
			$columns               = $this->get_columns();
			$this->_column_headers = array( $columns, array(), $sortable );
			$this->views();
		}

		/**
		 * Columns
		 *
		 * @return string Column.
		 */
		public function get_columns() {
			$columns = array(
				'cb'          => '<input type="checkbox" />',
				'name'        => __( 'Project Name', 'multiple-pages-generator-by-porthas' ),
				'pages'       => __( 'Pages', 'multiple-pages-generator-by-porthas' ),
				'next_sync'   => __( 'Next Sync', 'multiple-pages-generator-by-porthas' ),
				'source_type' => __( 'Source Type', 'multiple-pages-generator-by-porthas' ),
				'created_at'  => __( 'Date', 'multiple-pages-generator-by-porthas' ),
			);
			return $columns;
		}

		/**
		 * Default Column
		 *
		 * @param mix $item Item.
		 * @param int $column_name Column name.
		 * @return mix
		 */
		public function column_default( $item, $column_name ) {
			switch ( $column_name ) {
				case 'name':
					return $item->$column_name;
				case 'pages':
					$total_pages = isset( $item->urls_array ) ? count( json_decode( $item->urls_array, true ) ) : 0;
					// translators: %d: the number of total page.
					return '<a href="#" class="mpg-preview-urls" data-project_id="' . esc_attr( $item->id ). '" class="">' . sprintf( esc_html__( 'See All %d URLs', 'multiple-pages-generator-by-porthas' ), $total_pages ) . '</a>';
				case 'next_sync':
					if ( 'direct_link' !== $item->source_type ) {
						return '—';
					}
					$next_sync = esc_html__( 'Live', 'multiple-pages-generator-by-porthas' );
					if ( ! empty( $item->schedule_periodicity ) && ! in_array( $item->schedule_periodicity, array( 'now', 'once', 'ondemand' ), true ) ) {
						$next_sync = wp_next_scheduled(
							'mpg_schedule_execution',
							array(
								(int) $item->id,
								$item->schedule_source_link,
								$item->schedule_notificate_about,
								$item->schedule_periodicity,
								$item->schedule_notification_email,
							)
						);
						$next_sync = date_i18n( 'Y m d \a\t H:i:s', $next_sync );
					} elseif ( ! empty( $item->schedule_periodicity ) ) {
						if ( 'once' === $item->schedule_periodicity ) {
							$next_sync = esc_html__( 'Manual', 'multiple-pages-generator-by-porthas' );
						} elseif ( 'now' === $item->schedule_periodicity ) {
							$next_sync = esc_html__( 'Live', 'multiple-pages-generator-by-porthas' );
						} elseif ( 'ondemand' === $item->schedule_periodicity ) {
							$next_sync = esc_html__( 'On Demand', 'multiple-pages-generator-by-porthas' );
						}
					}
					// translators: %d: the date of the next schedule.
					return sprintf( esc_html__( 'Next scheduled execution: %s', 'multiple-pages-generator-by-porthas' ), esc_html( $next_sync ) );
				case 'created_at':
					return date_i18n( 'd M, Y, H:i a', $item->$column_name );
				case 'source_type':
					$column_name = ! empty( $item->$column_name ) ? $item->$column_name : '';
					$source_type = str_replace( '_', ' ', $column_name );
					return esc_html( ucwords( $source_type ) );
				default:
					return __( 'No Data Found', 'multiple-pages-generator-by-porthas' );
			}
		}

		/**
		 * View Record button.
		 *
		 * @param string $item email.
		 * @return sttring Field and action
		 */
		public function column_name( $item ) {
			$edit_url = add_query_arg(
				array(
					'page'   => 'mpg-project-builder',
					'action' => 'edit_project',
					'id'     => $item->id,
				),
				admin_url( 'admin.php' )
			);
			if ( ! mpg_app()->can_edit() ) {
				$edit_url = '#';
			}
			$action = array(
				'id_show' => sprintf( '<span>#%d</span>', $item->id ),
				'edit' => sprintf(
					'<a href="%s" class="%s"style="%s" >%s</a>',
					esc_url( $edit_url ),
					mpg_app()->can_edit() ? 'mpg-edit-btn' : 'mpg-edit-btn-pro',
					! mpg_app()->can_edit() ? 'opacity:0.5;' : '',
					esc_html__( 'Edit', 'multiple-pages-generator-by-porthas' ),
				),
				'delete' => sprintf(
					'<a href="%s" onclick="return confirm(\'%s\');">%s</a>',
					esc_url(
						add_query_arg(
							array(
								'page'     => 'mpg-project-builder',
								'action'   => 'delete_project',
								'id'       => $item->id,
								'_wpnonce' => wp_create_nonce( 'mpg-delete-project' ),
							),
							admin_url( 'admin.php' )
						)
					),
					esc_html__( 'Are you sure you want to delete this project?', 'multiple-pages-generator-by-porthas' ),
					esc_html__( 'Delete', 'multiple-pages-generator-by-porthas' )
				),
			);
			$clone_action = sprintf(
				                '<a href="%s" class="%s"style="%s">%s</a>',
				                mpg_app()->is_premium() ? esc_url(
					                add_query_arg(
						                array(
							                'page'   => 'mpg-project-builder',
							                'action' => 'clone_project',
							                '_wpnonce' => wp_create_nonce( 'mpg-clone-project' ),
							                'id'     => $item->id,
						                ),
						                admin_url( 'admin.php' )
					                )
				                ) : '#',
				                mpg_app()->is_premium() ? 'mpg-clone-btn' : 'mpg-clone-btn-pro',
				                ! mpg_app()->is_premium() ? 'opacity:0.5;' : '',
				                ( ! mpg_app()->is_premium() ? '<span style="font-size: 13px;line-height: 1.5em;width: 13px;height: 13px;" class="dashicons dashicons-lock"></span>' : '' )  . esc_html__( 'Clone', 'multiple-pages-generator-by-porthas' )
			);

			$export_action = sprintf(
				                '<a href="%s" class="%s"style="%s">%s</a>',
				                mpg_app()->is_premium() ? esc_url(
					                add_query_arg(
						                array(
							                'page'   => 'mpg-project-builder',
							                'action' => 'export_all_projects',
							                '_wpnonce' => wp_create_nonce( 'mpg-export-projects' ),
							                'id'     => $item->id,
						                ),
						                admin_url( 'admin.php' )
					                )
				                ) : '#',
				                mpg_app()->is_premium() ? 'mpg-export-btn' : 'mpg-export-btn-pro',
				                ! mpg_app()->is_premium() ? 'opacity:0.5;' : '',
				                ( ! mpg_app()->is_premium() ? '<span style="font-size: 13px;line-height: 1.5em;width: 13px;height: 13px;" class="dashicons dashicons-lock"></span>' : '' )  . esc_html__( 'Export', 'multiple-pages-generator-by-porthas' )
			);

			if(mpg_app()->is_premium()){
				$action = [
					'id_show' => sprintf( '<span>#%d</span>', $item->id ),
					'edit' => $action['edit'],
					'clone' => $clone_action,
					'export' => $export_action,
					'delete' => $action['delete'],
				];
			}else{
				$action['clone'] = $clone_action;
				$action['export'] = $export_action;
			}
			return sprintf( '%1$s %2$s', $item->name, $this->row_actions( $action ) );
		}

		/**
		 * Bulk delete actions.
		 */
		public function get_bulk_actions() {
			$delete_action = array(
				'bulk-delete' => __( 'Delete', 'multiple-pages-generator-by-porthas' ),
			);
			return $delete_action;
		}

		/**
		 * Checkbox in row.
		 *
		 * @param int $item id.
		 * @return id;
		 */
		public function column_cb( $item ) {
			return sprintf( '<input type="checkbox" name="project_ids[]" value="%d" />', $item->id );
		}

		/**
		 * No projects found.
		 */
		public function no_items() {
			esc_html_e( 'No projects found.', 'multiple-pages-generator-by-porthas' );
		}

		/**
		 * Sortable columans.
		 */
		protected function get_sortable_columns() {
			$sortable_columns = array(
				'name'       => array( 'name', false ),
				'created_at' => array( 'created_at', false ),
			);
			return $sortable_columns;
		}

		/**
		 * Prints column headers, accounting for hidden and sortable columns.
		 *
		 * @since 3.1.0
		 *
		 * @param bool $with_id Whether to set the ID attribute or not
		 */
		public function print_column_headers( $with_id = true ) {
			list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

			$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			$current_url = remove_query_arg( 'paged', $current_url );
			$_mpg_nonce  = wp_create_nonce( MPG_BASENAME );

			$current_orderby = '';
			$current_order   = 'asc';
			if ( isset( $_GET['_mpg_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_mpg_nonce'] ) ), MPG_BASENAME ) ) {
				if ( isset( $_GET['orderby'] ) ) {
					$current_orderby = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
				}

				if ( ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) ) {
					$current_order = 'desc';
				}
			}

			if ( ! empty( $columns['cb'] ) ) {
				static $cb_counter = 1;
				$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' .
					/* translators: Hidden accessibility text. */
					__( 'Select All', 'multiple-pages-generator-by-porthas' ) .
				'</label>' .
				'<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
				$cb_counter++;
			}

			foreach ( $columns as $column_key => $column_display_name ) {
				$class = array( 'manage-column', "column-$column_key" );

				if ( in_array( $column_key, $hidden, true ) ) {
					$class[] = 'hidden';
				}

				if ( 'cb' === $column_key ) {
					$class[] = 'check-column';
				} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ), true ) ) {
					$class[] = 'num';
				}

				if ( $column_key === $primary ) {
					$class[] = 'column-primary';
				}

				if ( isset( $sortable[ $column_key ] ) ) {
					list( $orderby, $desc_first ) = $sortable[ $column_key ];

					if ( $current_orderby === $orderby ) {
						$order = 'asc' === $current_order ? 'desc' : 'asc';

						$class[] = 'sorted';
						$class[] = $current_order;
					} else {
						$order = strtolower( $desc_first );

						if ( ! in_array( $order, array( 'desc', 'asc' ), true ) ) {
							$order = $desc_first ? 'desc' : 'asc';
						}

						$class[] = 'sortable';
						$class[] = 'desc' === $order ? 'asc' : 'desc';
					}

					$column_display_name = sprintf(
						'<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>',
						esc_url( add_query_arg( compact( 'orderby', 'order', '_mpg_nonce' ), $current_url ) ),
						$column_display_name
					);
				}

				$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
				$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
				$id    = $with_id ? "id='$column_key'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . implode( ' ', $class ) . "'";
				}

				echo "<$tag $scope $id $class>$column_display_name</$tag>";
			}
		}
	}
}
