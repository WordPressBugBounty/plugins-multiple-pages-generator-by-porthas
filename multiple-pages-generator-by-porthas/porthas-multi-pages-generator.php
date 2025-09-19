<?php

/**
 * Plugin Name: Multiple Pages Generator by Themeisle
 * Plugin URI: https://themeisle.com/plugins/multi-pages-generator/
 * Description: Plugin for generation of multiple frontend pages from .csv, .xlsx, .ods, or Google Sheets.
*  * WordPress Available:  yes 
 *
 * Author: Themeisle
 * Author URI: https://themeisle.com
 * Version: 4.1.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

defined( 'MPG_BASENAME' ) || define( 'MPG_BASENAME', __FILE__ );
defined( 'MPG_MAIN_DIR' ) || define( 'MPG_MAIN_DIR', dirname( __FILE__ ) );
defined( 'MPG_PRODUCT_SLUG' ) || define( 'MPG_PRODUCT_SLUG', basename( MPG_MAIN_DIR ) );
defined( 'MPG_MAIN_URL' ) || define( 'MPG_MAIN_URL', plugins_url( '', __FILE__ ));
defined( 'MPG_UPLOADS_DIR' ) || define( 'MPG_UPLOADS_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'mpg-uploads' . DIRECTORY_SEPARATOR );
defined( 'MPG_UPLOADS_URL' ) || define( 'MPG_UPLOADS_URL', WP_CONTENT_URL . DIRECTORY_SEPARATOR . 'mpg-uploads' . DIRECTORY_SEPARATOR );
defined( 'MPG_CACHE_DIR' ) || define( 'MPG_CACHE_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'mpg-cache' . DIRECTORY_SEPARATOR );
defined( 'MPG_CACHE_URL' ) || define( 'MPG_CACHE_URL', WP_CONTENT_URL . DIRECTORY_SEPARATOR . 'mpg-cache' . DIRECTORY_SEPARATOR );
defined( 'MPG_NAME' ) || define( 'MPG_NAME', 'Multiple Pages Generator' );
defined( 'MPG_BASE_IMG_PATH' ) || define( 'MPG_BASE_IMG_PATH', plugin_dir_url( __FILE__ ) . 'frontend/images' );
defined( 'MPG_DATABASE_VERSION' ) || define( 'MPG_DATABASE_VERSION', '1.0.0' );
defined( 'MPG_PLUGIN_VERSION' ) || define( 'MPG_PLUGIN_VERSION', '4.1.0' );
defined( 'MPG_FREE_SLUG' ) || define( 'MPG_FREE_SLUG', 'multiple-pages-generator-by-porthas' );
defined( 'MPG_TRANSLATION_CACHE_KEY_PREFIX' ) || define( 'MPG_TRANSLATION_CACHE_KEY_PREFIX', 'mpg_translation_check' );

// to redirect all themeisle_log_event to error log.
if ( ! defined( 'MPG_LOCAL_DEBUG' ) ) {
	define( 'MPG_LOCAL_DEBUG', false );
}

if ( ! defined( 'MPG_JSON_OPTIONS' ) ) {
	if ( defined( 'JSON_INVALID_UTF8_IGNORE' ) ) {
		define( 'MPG_JSON_OPTIONS', JSON_INVALID_UTF8_IGNORE );
	} else {
		define( 'MPG_JSON_OPTIONS', 0 );
	}
}

add_action( 'admin_init', function () {
	if ( is_plugin_active( 'multi-pages-plugin-premium/porthas-multi-pages-generator.php' ) && (
			is_plugin_active( 'multi-pages-plugin/porthas-multi-pages-generator.php' ) || is_plugin_active( 'multiple-pages-generator-by-porthas/porthas-multi-pages-generator.php' ) ) ) {
		is_plugin_active( 'multi-pages-plugin/porthas-multi-pages-generator.php' ) && deactivate_plugins( [ 'multi-pages-plugin/porthas-multi-pages-generator.php' ] );
		is_plugin_active( 'multiple-pages-generator-by-porthas/porthas-multi-pages-generator.php' ) && deactivate_plugins( [ 'multiple-pages-generator-by-porthas/porthas-multi-pages-generator.php' ] );
		add_action( 'admin_notices', function () {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong><br>%s</p><p></p></div>',
				sprintf(
					/* translators: %s: Name of deactivated plugin */
					__( '%s plugin deactivated.', 'multiple-pages-generator-by-porthas'),
					__( 'Multiple Pages Generator (Free)', 'multiple-pages-generator-by-porthas' )
				),
				__( 'Using the Premium version of Multiple Pages Generator is not requiring using the Free version anymore.', 'multiple-pages-generator-by-porthas' )
			);
		} );
	}
} );

if ( ! function_exists( 'mpg_run' ) ) {
	function mpg_run() {
		static $has_run = false;
		if ( $has_run ) {
			return;
		}
		require_once 'helpers/I18n.php';
		$has_run = true;
		// ... Your plugin's main file logic ...
		if ( is_readable( MPG_MAIN_DIR . '/pro/load.php' ) ) {
			require_once MPG_MAIN_DIR . '/pro/load.php';
		}
		require_once 'controllers/CoreController.php';
		require_once 'controllers/HookController.php';
		require_once 'controllers/MenuController.php';
		require_once 'controllers/SearchController.php';
		// Запуск базового функционала подмены данных
		MPG_HookController::init_replacement();
		// Запуск всяких actions, hooks, filters
		MPG_HookController::init_base();
		// Запуск хуков для ajax. Связываем роуты и функции
		MPG_HookController::init_ajax();
		// Инициализация бокового меню в WordPress
		MPG_MenuController::init();

		add_filter( 'themeisle_sdk_products', function ( $products ) {
			$products[] = __FILE__;

			return $products;
		} );
		add_filter( 'themeisle_sdk_hide_dashboard_widget', '__return_false' );
		add_filter(
			'multiple_pages_generator_by_porthas_about_us_metadata',
			function() {
				return array(
					'logo'     => MPG_BASE_IMG_PATH . '/icon-256x256.png',
					'location' => 'mpg-project-builder',
				);
			}
		);

		add_filter(
			'multiple_pages_generator_by_porthas_welcome_metadata',
			function () {
				return array(
					'is_enabled' => ! mpg_app()->is_premium(),
					'pro_name'   => 'Premium',
					'logo'       => MPG_BASE_IMG_PATH . '/icon-256x256.png',
					'cta_link'   => tsdk_translate_link( tsdk_utmify( 'https://themeisle.com/plugins/multi-pages-generator/upgrade/?discount=LOYALUSER583&dvalue=60#pricing', 'mpg-welcome', 'notice' ), 'query' ),
				);
			}
		);

		add_filter(
			'multiple_pages_generator_by_porthas_welcome_upsell_message',
			function () {
				return wpautop(
					sprintf(
						// translators: %1$s: the shortname name of the plugin (MPG), %2$s: the shortname of the PRO version (MPG PRO), %3$s: the upgrade link (with label 'Upgrade now'), %4$s: the shortname of the PRO version (MPG PRO).
						__( 'Thanks for using %1$s for the past 7 days! To help you get even more from it, we’re offering an exclusive deal: upgrade to %2$s within the next 5 days and save up to 60%%. Unlock unlimited rows and projects — %3$s and access all the powerful features of %4$s!', 'multiple-pages-generator-by-porthas' ),
						'<b>' . __( 'MPG', 'multiple-pages-generator-by-porthas' ) . '</b>',
						'<b>' . __( 'MPG PRO', 'multiple-pages-generator-by-porthas' ) . '</b>',
						'<a href="{cta_link}" target="_blank">' . __( 'Upgrade now', 'multiple-pages-generator-by-porthas' ) . '</a>',
						'<b>' . __( 'MPG PRO', 'multiple-pages-generator-by-porthas' ) . '</b>'
					),
					true
				);
			}
		);
		
		add_filter(
			'themesle_sdk_namespace_' . md5( MPG_BASENAME ),
			function () {
				return 'mpg';
			}
		);

		add_filter( 'themeisle_sdk_blackfriday_data', function( $configs) {
			$config = $configs['default'];

			// translators: %1$s - HTML tag, %2$s - discount, %3$s - HTML tag, %4$s - product name.
			$message_template = __( 'Our biggest sale of the year: %1$sup to %2$s OFF%3$s on %4$s. Don\'t miss this limited-time offer.', 'multiple-pages-generator-by-porthas' );
			$product_label    = 'MPG';
			$discount         = '70%';

			$plan    = apply_filters( 'product_mpg_license_plan', 0 );
			$license = apply_filters( 'product_mpg_license_key', false );
			$is_pro  = 0 < $plan;

			if ( $is_pro ) {
				// translators: %1$s - HTML tag, %2$s - discount, %3$s - HTML tag, %4$s - product name.
				$message_template = __( 'Get %1$sup to %2$s off%3$s when you upgrade your %4$s plan or renew early.', 'multiple-pages-generator-by-porthas' );
				$product_label    = 'MPG Pro';
				$discount         = '20%';
			}

			$product_label = sprintf( '<strong>%s</strong>', $product_label );
			$url_params    = array(
				'utm_term' => $is_pro ? 'plan-' . $plan : 'free',
				'lkey'     => ! empty( $license ) ? $license : false,
			);

			$config['message']  = sprintf( $message_template, '<strong>', $discount, '</strong>', $product_label );
			$config['sale_url'] = add_query_arg(
				$url_params,
				tsdk_translate_link( tsdk_utmify( 'https://themeisle.link/mpg-bf', 'bfcm', 'mpg' ) )
			);

			$configs[ MPG_PRODUCT_SLUG ] = $config;

			return $configs;
		});

		$option_name = basename( dirname( MPG_BASENAME ) );
		$option_name = str_replace( '-', '_', strtolower( trim( $option_name ) ) );

		add_filter(
			$option_name . '_lc_no_valid_string',
			function ( $message ) {
				return str_replace( '<a href="%s">', '<a href="' . admin_url( 'admin.php?page=mpg-advanced-settings' ) . '">', $message );
			}
		);

		add_filter( $option_name . '_hide_license_field', '__return_true' );

		// Filter screen option value.
		add_filter(
			'set-screen-option',
			function( $status, $option, $value ) {
				if ( 'mpg_projects_per_page' === $option ) {
					return $value;
				}
			},
			99,
			3
		);
		$vendor_file = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'vendor/autoload.php';
		if ( is_readable( $vendor_file ) ) {
			require_once $vendor_file;
		}

		if( ! defined( 'MPG_DISABLE_TELEMETRY' ) ) {
			add_filter( 'themeisle_sdk_enable_telemetry', '__return_true' );
		}
	}
}

if ( MPG_LOCAL_DEBUG ) {
	add_action( 'themeisle_log_event', 'mpg_themeisle_log_event', 10, 5 );

	/**
	 * Redirect themeisle_log_event to error log.
	 */
	function mpg_themeisle_log_event( $name, $msg, $type, $file, $line ) {
		if ( MPG_NAME === $name ) {
			error_log( sprintf( '%s (%s:%d): %s', $type, $file, $line, $msg ) );
		}
	}
}

require_once 'helpers/Themeisle.php';
