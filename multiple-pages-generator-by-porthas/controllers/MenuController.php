<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( realpath( __DIR__ . '/DatasetController.php' ) );
require_once( realpath( __DIR__ . '/ProjectController.php' ) );
require_once( realpath( __DIR__ . '/AdvancedSettingsController.php' ) );
require_once( realpath( __DIR__ . '/SearchController.php' ) );
require_once( realpath( __DIR__ . '/ProjectsListManage.php' ) );
require_once( realpath( __DIR__ . '/ProjectsListTable.php' ) );


class MPG_MenuController {
    const MENU_ROLE = 'edit_pages';
	public static function init() {
		add_action( 'admin_menu', 'mpg_main_sidebar_menu', 9, 0 );
		add_action( 'admin_head', 'mpg_main_footer_style' );

		function mpg_main_sidebar_menu() {


			add_menu_page( 'MPG', 'MPG', MPG_MenuController::MENU_ROLE    , 'mpg-project-builder', '__return_null', plugin_dir_url( __FILE__ ) . '/../../frontend/images/logo_mpg.svg' );

			add_submenu_page( 'mpg-project-builder', __( 'Create new', 'mpg' ), __( 'Create New +', 'mpg' ), MPG_MenuController::MENU_ROLE , 'mpg-dataset-library', array( 'MPG_DatasetController', 'get_all' ) );

			$hook = add_submenu_page( 'mpg-project-builder', __( 'All Projects', 'mpg' ), __( 'All Projects', 'mpg' ), MPG_MenuController::MENU_ROLE , 'mpg-project-builder', array( 'MPG_ProjectController', 'builder' ) );
			add_action( 'load-' . $hook, array( 'MPG_ProjectController', 'handle_project_builder' ) );

			add_submenu_page( 'mpg-project-builder', __( 'Advanced settings', 'mpg' ), __( 'Advanced settings', 'mpg' ), MPG_MenuController::MENU_ROLE , 'mpg-advanced-settings', array( 'MPG_AdvancedSettingsController', 'render' ) );

			add_submenu_page( 'mpg-project-builder', __( 'Search settings', 'mpg' ), __( 'Search settings', 'mpg' ), MPG_MenuController::MENU_ROLE , 'mpg-search-settings', array( 'MPG_SearchController', 'render' ) );

		}

		function mpg_main_footer_style() {
			?>
			<style>
				li.toplevel_page_mpg-project-builder .wp-first-item {
					display: none !important;
				}
			</style>
			<?php
		}
	}
}
