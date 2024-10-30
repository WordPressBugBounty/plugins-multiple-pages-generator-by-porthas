<?php

require_once(realpath(__DIR__ . '/../helpers/Array.php'));
require_once(realpath(__DIR__ . '/../helpers/Helper.php'));
require_once(realpath(__DIR__ . '/../helpers/Parser.php'));
require_once(realpath(__DIR__ . '/../models/ProjectModel.php'));
require_once(realpath(__DIR__ . '/../controllers/CoreController.php'));
require_once(realpath(__DIR__ . '/display/load.php'));
require_once(realpath(__DIR__ . '/../controllers/DatasetController.php'));
require_once(realpath(__DIR__ . '/../controllers/ProjectController.php'));
require_once(realpath(__DIR__ . '/../controllers/SpintaxController.php'));
require_once(realpath(__DIR__ . '/../controllers/CacheController.php'));

class MPG_HookController
{

    public static function init_base()
    {

        $rest_prefix = trailingslashit( rest_get_url_prefix() );
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $is_rest_api_request = isset( $_SERVER['REQUEST_URI'] ) ? strpos( wp_unslash( $_SERVER['REQUEST_URI'] ), $rest_prefix ) !== false : false;
        if ( $is_rest_api_request ) {
            return;
        }

        // Modifies the "Thank you" notice in the admin footer.
        add_filter( 'admin_footer_text',

            function ( $footer_text ) {
                $current_screen = get_current_screen();

                $is_mpg_page = false;
                $mpg_page_ids = ['mpg_page_mpg-project-builder', 'toplevel_page_mpg-dataset-library', 'mpg_page_mpg-advanced-settings', 'mpg_page_mpg-search-settings' ];

                if ( !empty( $current_screen ) && isset( $current_screen->id ) ) {
                    foreach ( $mpg_page_ids as $page_to_check ) {
                        if ( strpos($current_screen->id, $page_to_check ) !== false) {
                            $is_mpg_page = true;
                            break;
                        }
                    }
                }

                if ( $is_mpg_page === true ) {
                    $footer_text = sprintf(
                        __('Enjoying %1$s? %2$s %3$s rating. Thank you.', 'mpg'),
                        MPG_NAME,
                        '<strong>' . esc_html__('Please leave us a', 'mpg') . '</strong>',
                        '<a href="https://wordpress.org/support/plugin/multiple-pages-generator-by-porthas/reviews/" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
                    );
                }

                return $footer_text;
            }
        );

        // Excluding template pages from search / loop
        add_action('pre_get_posts', function ($query) {
            if ( ! empty( $_GET['elementor-preview'] ) && is_numeric( $_GET['elementor-preview'] ) ) {
                return;
            }
            if ( ! is_admin() && ! defined( 'TI_UNIT_TESTING' ) ) {
                $templates_ids = MPG_ProjectModel::mpg_get_all_templates_id();
                if ($templates_ids) {
                    $query->query_vars['post__not_in'] = $templates_ids;
                }
            }
        });


        add_action( 'wp', function( $wp ) {
            if ( class_exists( '\wpbuddy\rich_snippets\Frontend_Controller', false ) ) {
                global $wp_the_query;
                $path           = MPG_Helper::mpg_get_request_uri();
                $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules( $path );
                if ( ! empty( $redirect_rules['template_id'] ) ) {
                    $wp_the_query->queried_object_id = $redirect_rules['template_id'];
                }
                $wp_the_query->is_singular = true;
            }
        }, 1 );

        $mpg_index_file = plugin_dir_path(__DIR__) . 'porthas-multi-pages-generator.php';

        // Подключает .mo файл перевода из указанной папки.
        add_action('plugins_loaded', array('MPG_Helper', 'mpg_set_language_folder_path'));

        // Register additional (weekly) interval for cron because WP hasn't weekly period
        add_filter('cron_schedules', array('MPG_Helper', 'mpg_cron_weekly'));
        // Register additional (monthly) interval for cron because WP hasn't monthly period
        add_filter('cron_schedules', array('MPG_Helper', 'mpg_cron_monthly'));
	    add_action( 'admin_head', function () {

		    if ( ! empty( get_option( 'mpg_legacy_user', '' ) ) ) {
			    return;
		    }
		    global $wpdb;
		    $projects = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE . "  limit 1" );

		    update_option( 'mpg_legacy_user', ! empty( $projects ) ? 'yes' : 'no' );
	    } );
        // Создаем хук, который будет вызывать функция wp_schedule_event, и wp_schedule_single_event, в момент
        // когда наступает время скачки и развертывания файла по расписанию
        add_action('mpg_schedule_execution', ['MPG_ProjectController', 'mpg_scheduled_cron_handler'], 10, 5);


        // Remove cron task when user deactivate plugin
        register_deactivation_hook($mpg_index_file,  array('MPG_Helper', 'mpg_set_deactivation_option'));

        // Создаем таблицу для проектов (если ее еще нет) при активации хука.
        register_activation_hook($mpg_index_file,  array('MPG_Helper', 'mpg_activation_events'));

        // Include styles and scripts in MGP plugin pages only
        add_action('admin_enqueue_scripts', array('MPG_Helper', 'mpg_admin_assets_enqueue'));

        add_action('wp_enqueue_scripts', array('MPG_Helper', 'mpg_front_assets_enqueue'));


        // https://stackoverflow.com/questions/58931144/enqueue-javascript-with-type-module
        add_filter('script_loader_tag', array('MPG_Helper', 'mpg_add_type_attribute'), 10, 3);

        // Other
        add_action('wp_ajax_mpg_get_permalink_structure', ['MPG_ProjectController', 'mpg_get_permalink_structure']);
        add_action('wp_ajax_mpg_change_permalink_structure', ['MPG_ProjectController', 'mpg_change_permalink_structure']);
        add_action('wp_ajax_mpg_ti_subscribe', ['MPG_ProjectController', 'mpg_ti_subscribe']);
        add_action('admin_action_mpg_dismiss_subscribe_notice', ['MPG_ProjectController', 'mpg_dismiss_subscribe_notice']);

        add_action('admin_head', ['MPG_Helper', 'mpg_header_code_container']);

        add_action( 'plugins_loaded', function() {
            if ( class_exists( 'UAGB_Front_Assets', false ) ) {
                add_action( 'template_redirect', array( UAGB_Front_Assets::get_instance(), 'set_initial_variables' ) );
            }
        }, 99 );

	    add_action( 'init', function () {
		    $db_version = get_option( 'mpg_database_version' );

		    if ( $db_version === MPG_DATABASE_VERSION ) {
			    return;
		    }
		    MPG_Helper::mpg_activation_events();
	    } );
        // Allow usage of mpg shortcode inside link controls.
	    add_action( 'elementor/widget/before_render_content', function ($widget) {
		    add_filter(
			    'clean_url', function ( $good_protocol_url, $original_url, $_context ) {
			    $mpg_shortcode = 'mpg_.*';
			    preg_match( "/{$mpg_shortcode}/i", $original_url, $matches );
			    if ( ! empty( $matches ) ) {
				    return $original_url;
			    }
			    return $good_protocol_url;
		    },99, 3 );
	    }, 99, 1 );
        // Ставим noindex для страницы шаблона
        add_filter('template_redirect', function () {
            $templates_ids = MPG_ProjectModel::mpg_get_all_templates_id();

            $queried_obj_id = get_queried_object_id();
            global $wp;

            if (in_array($queried_obj_id, $templates_ids) && in_array(get_post($queried_obj_id)->guid, [home_url($wp->request), home_url($wp->request) . '/'])) {
                header('X-Robots-Tag: noindex');
            }
        }, 1, 1);

        // Filter language URL in the menu switcher.
	    add_filter(
		    'wpml_ls_language_url',
		    function ( $url, $data ) {
			    if ( ! MPG_Helper::is_mpg_single() ) {
				    return $url;
			    }
			    global $sitepress;
			    $code = $sitepress->get_language_from_url( $url );
			    return $sitepress->convert_url_string( MPG_CoreModel::path_to_url( MPG_Helper::mpg_get_request_uri() ), $code );
		    },
		    10,
		    2
	    );
        // Handles the WPML langhref attribute translation.
        // WPML is using the wp_query before we set the template id, so we need handle here the translation for the virtual pages.
	    add_filter( 'wpml_hreflangs', function ( $hreflang_items ) {
		    if ( is_404() ) {
			    return $hreflang_items;
		    }
		    if ( ! MPG_Helper::is_mpg_single() ) {

			    return $hreflang_items;
		    }
		    global $sitepress;
		    foreach ( $hreflang_items as $key => $hreflang_item ) {
			    $code                   = $sitepress->get_language_from_url( $hreflang_item );
			    $hreflang_items[ $key ] = $sitepress->convert_url_string( MPG_CoreModel::path_to_url( MPG_Helper::mpg_get_request_uri() ), $code );
		    }

		    return $hreflang_items;
	    } );


        add_action(
            'the_seo_framework_after_admin_init',
            function() {
                // Filter image URL.
                add_filter(
                    'clean_url',
                    function( $good_protocol_url, $original_url, $_context ) {
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing
                        $is_valid = function_exists( 'the_seo_framework_db_version' ) && ! empty( $_POST['autodescription'] );
                        if ( ! $is_valid ) {
                            global $pagenow;
                            $is_valid = function_exists( 'the_seo_framework_db_version' ) && 'post.php' === $pagenow;
                        }
                        if ( $is_valid ) {
                            $mpg_shortcode = 'mpg_.*';
                            preg_match( "/{$mpg_shortcode}/i", $original_url, $matches );
                            if ( ! empty( $matches ) ) {
                                return $original_url;
                            }
                            return $good_protocol_url;
                        }
                        return $good_protocol_url;
                    },
                    10,
                    3
                );

                // Change `social image URL` field type.
                add_action(
                    'admin_footer',
                    function() {
                        ?>
                        <script>
                            jQuery( document ).ready( function( $ ) {
                                setTimeout( function() {
                                    var socialImageURL = $( 'input[name="autodescription[_social_image_url]"]' );
                                    if ( socialImageURL.length > 0 ) {
                                        socialImageURL.get(0).type = 'text';
                                    }
                                }, 1000 );
                            } );
                        </script>
                        <?php
                    }
                );
            }
        );

        add_filter(
            'clean_url',
            function( $good_protocol_url, $original_url, $_context ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                if ( function_exists( 'aioseo' ) && ! empty( $_POST['aioseo-post-settings'] ) ) {
                    $mpg_shortcode = 'mpg_.*';
                    preg_match( "/{$mpg_shortcode}/i", $original_url, $matches );
                    if ( ! empty( $matches ) ) {
                        return $original_url;
                    }
                    return $good_protocol_url;
                } elseif ( function_exists( 'wpseo_init' ) && ! empty( $_POST['yoast_wpseo_canonical'] ) ) {
                    $yoast_wpseo_canonical = $_POST['yoast_wpseo_canonical'];
                    $mpg_shortcode         = 'mpg_.*';
                    preg_match( "/{$mpg_shortcode}/i", $yoast_wpseo_canonical, $matches );
                    if ( ! empty( $matches ) ) {
                        return urldecode( $yoast_wpseo_canonical );
                    }
                    return $good_protocol_url;
                }
                return $good_protocol_url;
            },
            10,
            3
        );

        // Create new site MPG database when add/update site.
        add_action(
            'wp_insert_site',
            function() {
                MPG_Helper::mpg_activation_events();
            }
        );
        add_action(
            'wp_update_site',
            function() {
                MPG_Helper::mpg_activation_events();
            }
        );

        // Handle WP default loop.
        add_action( 'pre_get_posts', array( 'MPG_Helper', 'mpg_pre_get_posts' ) );
        add_action( 'posts_results', array( 'MPG_Helper', 'mpg_posts_results' ), 10, 2 );
        add_filter( 'found_posts', array( 'MPG_Helper', 'mpg_found_posts' ) );

        // Yoast SEO compatibility.
        $yoast_seo_options = get_option( 'wpseo', array() );

        // Exclude template pages from sitemap.
        if( ! empty( $yoast_seo_options ) && ! empty( $yoast_seo_options['enable_xml_sitemap'] ) ) {
            add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function( $post_ids ) {
                $excluded_projects = get_option( MPG_Constant::EXCLUDED_PROJECTS_IN_ROBOT, array() );
                if( empty( $excluded_projects ) ) {
                    return $post_ids;
                }

                foreach ( $excluded_projects as $excluded_project ) {
                    if( empty( $excluded_project['template_id'] ) || ! is_numeric( $excluded_project['template_id'] ) ) {
                        continue;
                    }

                    $post_ids[] = $excluded_project['template_id'];
                }

                return $post_ids;
            } );
        } 
        /// Block editor assets.
        add_action('enqueue_block_editor_assets', array('MPG_Helper', 'block_editor_assets_enqueue')); 
	    if ( ! wp_next_scheduled( 'mpg_sitemap_check' ) ) {
		    wp_schedule_event( time() + 60, 'twicedaily', 'mpg_sitemap_check' );
	    }
        add_action('mpg_sitemap_check', [__CLASS__, 'check_sitemaps']); 
    }

	/**
	 * Check the validity of sitemaps for multiple projects and regenerate them if they are invalid.
	 *
	 * This function performs the following steps:
	 * 1. Retrieves a list of projects.
	 * 2. For each project, it checks if a sitemap file exists.
	 * 3. Validates the sitemap XML.
	 * 4. If the sitemap is invalid, it logs an error and regenerates the sitemap.
	 */
	public static function check_sitemaps() {
		$projects = MPG_ProjectModel::get_projects( 10 );

		foreach ( $projects as $project ) {
			$filename = $project->sitemap_filename;
			if ( empty( $filename ) ) {
				continue;
			}
			$urls_list = ! empty( $project ) ? $project->urls_array : null;

			if ( empty( $urls_list ) ) {
				continue;
			}

			$urls_list = json_decode( $urls_list, true );

			$sitemap_valid_path = false;
			if ( file_exists( MPG_SitemapGenerator::get_basepath() . $filename . '.xml' ) ) {
				$sitemap_valid_path = MPG_SitemapGenerator::get_basepath() . $filename . '.xml';
			} elseif ( file_exists( MPG_SitemapGenerator::get_basepath() . $filename . '-index.xml' ) ) {
				$sitemap_valid_path = MPG_SitemapGenerator::get_basepath() . $filename . '-index.xml';
			}
			if ( ! empty( $sitemap_valid_path ) ) {
				libxml_use_internal_errors( true ); // Enable internal error handling
				$xml = simplexml_load_file( $sitemap_valid_path );
				if ( $xml === false ) {
					$sitemap_valid_path = false;
				}
			}
			if ( ! empty( $sitemap_valid_path ) ) {
				continue;
			}
			MPG_LogsController::mpg_write( $project->id, 'error', 'Sitemap is not valid, regenerating' );
			// Обновляем карту сайта только в том случае, если она уже есть (не надо создавать, если пользователь не хочет)
			$sitemap_max_url          = $project->sitemap_max_url ?: 5000;
			$sitemap_update_frequency = $project->sitemap_update_frequency ?: 'daily';
			$sitemap_add_to_robots    = $project->sitemap_add_to_robots ?: true;
			try {
				MPG_SitemapGenerator::run( $urls_list, $filename, $sitemap_max_url, $sitemap_update_frequency, $sitemap_add_to_robots, $project->id );
			} catch ( Exception $e ) {
				MPG_LogsController::mpg_write( $project->id, 'error', 'Sitemap generation failed: ' . $e->getMessage() );
			}
		}
	}
    public static function init_ajax()
    {

        // Dataset library
        add_action('wp_ajax_mpg_deploy_dataset', ['MPG_DatasetController', 'mpg_deploy']);

        // Main tab
        add_action('wp_ajax_mpg_get_posts_by_custom_type', array('MPG_ProjectModel', 'mpg_get_posts_by_custom_type'));

        add_action('wp_ajax_mpg_upload_file', array('MPG_ProjectModel', 'mpg_upload_file'));

        add_action('wp_ajax_mpg_options_update', array('MPG_ProjectModel', 'mpg_options_update'));

        add_action('wp_ajax_mpg_upsert_project_main', ['MPG_ProjectController', 'mpg_upsert_project_main']);
        add_action('wp_ajax_mpg_upsert_project_source_block', ['MPG_ProjectController', 'mpg_upsert_project_source_block']);

        add_action('wp_ajax_mpg_upsert_project_url_block', ['MPG_ProjectController', 'mpg_upsert_project_url_block']);

        add_action('wp_ajax_mpg_get_data_for_preview', ['MPG_DatasetController', 'mpg_get_data_for_preview']);

        add_action('wp_ajax_mpg_preview_all_urls', ['MPG_DatasetController', 'mpg_preview_all_urls']);

        add_action('wp_ajax_mpg_get_all_projects', ['MPG_ProjectModel', 'mpg_get_all']);

        add_action('wp_ajax_mpg_get_project', ['MPG_ProjectController', 'mpg_get_project']);

        add_action('wp_ajax_mpg_download_file_by_url', ['MPG_DatasetController', 'mpg_download_file_by_link']);

        add_action('wp_ajax_mpg_get_unique_rows_in_column', ['MPG_DatasetController', 'mpg_get_unique_rows_in_column']);

        add_action('wp_ajax_mpg_delete_project', ['MPG_ProjectController', 'mpg_delete_project']);

        add_action('wp_ajax_mpg_unschedule_cron_task', ['MPG_ProjectController', 'mpg_unschedule_cron_task']);


        // Shortcodes tab
        add_action('wp_ajax_mpg_shortcode', ['MPG_CoreController', 'mpg_shortcode_ajax']);
        add_action('wp_ajax_nopriv_mpg_shortcode', ['MPG_CoreController', 'mpg_shortcode_ajax']);

        //Sitemap tab
        add_action('wp_ajax_mpg_generate_sitemap', ['MPG_ProjectController', 'mpg_generate_sitemap']);
        add_action('wp_ajax_mpg_check_is_sitemap_name_is_uniq', ['MPG_ProjectController', 'mpg_check_is_sitemap_name_is_uniq']);


        // Spintax tab
        add_action('wp_ajax_mpg_generate_spintax', ['MPG_SpintaxController', 'mpg_generate_spintax']);

        add_action('wp_ajax_mpg_flush_spintax_cache', ['MPG_SpintaxController', 'mpg_flush_spintax_cache']);


        // Cache tab
        add_action('wp_ajax_mpg_enable_cache', ['MPG_CacheController', 'mpg_enable_cache']);

        add_action('wp_ajax_mpg_disable_cache', ['MPG_CacheController', 'mpg_disable_cache']);

        add_action('wp_ajax_mpg_flush_cache', ['MPG_CacheController', 'mpg_flush_cache']);

        add_action('wp_ajax_mpg_cache_statistic', ['MPG_CacheController', 'mpg_cache_statistic']);

        // Работает для постов и страниц
        add_action('save_post', ['MPG_CacheController', 'mpg_flush_cache_on_template_update'], 10, 3);

        // Logs tab

        add_action('wp_ajax_mpg_get_log_by_project_id', ['MPG_LogsController', 'mpg_get_log_by_project_id']);

        add_action('wp_ajax_mpg_clear_log_by_project_id', ['MPG_LogsController', 'mpg_clear_log_by_project_id']);


        add_action('wp_ajax_mpg_activation_events', ['MPG_Helper', 'mpg_activation_events']);

        // Advanced settings
        add_action('wp_ajax_mpg_set_hook_name_and_priority', ['MPG_ProjectController', 'mpg_set_hook_name_and_priority']);
        add_action('wp_ajax_mpg_get_hook_name_and_priority', ['MPG_ProjectController', 'mpg_get_hook_name_and_priority']);

        // Basepath
        add_action('wp_ajax_mpg_set_basepath', ['MPG_ProjectController', 'mpg_set_basepath']);
        add_action('wp_ajax_mpg_get_basepath', ['MPG_ProjectController', 'mpg_get_basepath']);

        add_action('wp_ajax_mpg_set_cache_hook_name_and_priority', ['MPG_ProjectController', 'mpg_set_cache_hook_name_and_priority']);
        add_action('wp_ajax_mpg_get_cache_hook_name_and_priority', ['MPG_ProjectController', 'mpg_get_cache_hook_name_and_priority']);

        // Hook position
        add_action('wp_ajax_mpg_set_branding_position', ['MPG_AdvancedSettingsController', 'mpg_set_branding_position']);
        add_action('wp_ajax_mpg_get_branding_position', ['MPG_AdvancedSettingsController', 'mpg_get_branding_position']);



        // Search
        add_action('wp_ajax_mpg_get_search_results', ['MPG_SearchController', 'mpg_search_ajax']);
        add_action('wp_ajax_nopriv_mpg_get_search_results', ['MPG_SearchController', 'mpg_search_ajax']);

        add_action('wp_ajax_mpg_search_settings_upset_options', ['MPG_SearchController', 'mpg_search_settings_upset_options']);
        add_action('wp_ajax_mpg_search_settings_get_options', ['MPG_SearchController', 'mpg_search_settings_get_options']);
        add_action('wp_ajax_nopriv_mpg_search_settings_get_options', ['MPG_SearchController', 'mpg_search_settings_get_options']);

        add_action('wp_ajax_mpg_send_analytics_data', ['MPG_Helper', 'mpg_send_analytics_data']);
        add_action('rest_api_init',array(__CLASS__,'register_rest_routes'));

        // License
        add_action('wp_ajax_mpg_ti_toggle_license', ['MPG_ProjectController', 'mpg_ti_toggle_license']);
    }
    /**
     * Register rest routes.
     */
	public static function register_rest_routes() {

		register_rest_route( 'mpg', '/webhook/(?P<project_id>\d+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'trigger_fetch' ),
			'args'                => array(
				'project_id' => array(
					'validate_callback' => function ( $param, $request, $key ) {
						return is_numeric( $param );
					}
				),
			),
			'permission_callback' => function ( $request ) {
				$secret     = $request->get_param( 'hash' );
				$project_id = $request->get_param( 'project_id' );
				if ( empty( $project_id ) || empty( $secret ) ) {
					return false;
				}
				$hash = hash_hmac( 'sha256', $project_id, MPG_Helper::get_webhook_key() );

				return hash_equals( $hash, $secret );
			}
		) );
	}

	/**
     * Run the update project on demand.
     *
	 * @param $request
	 *
	 * @return WP_Error|WP_HTTP_Response|WP_REST_Response
	 * @throws Exception
	 */
	public static function trigger_fetch( $request ) {
		$project_id  = $request->get_param( 'project_id' );
		$mpg_project = MPG_ProjectModel::mpg_get_project_by_id( $project_id );

		if ( ! isset( $mpg_project[0] ) || ! $mpg_project[0]->schedule_source_link ) {
			return new WP_Error( 'invalid_project', __( 'Your project has not properly configured source.', 'mpg' ) );
		}
		if ( $mpg_project[0]->schedule_periodicity !== 'ondemand' ) {
			return new WP_Error( 'invalid_project', __( 'Your project is not configured to be updated on demand.', 'mpg' ) );
		}
		MPG_ProjectController::mpg_scheduled_cron_handler( $project_id, $mpg_project[0]->schedule_source_link, $mpg_project[0]->schedule_notificate_about, $mpg_project[0]->schedule_periodicity, $mpg_project[0]->schedule_notification_email );

		return rest_ensure_response( [
			'code'    => 'success',
			'message' => sprintf( __( 'Project %s has been updated.' ), $mpg_project[0]->name )
		] );
	}
    public static function init_replacement()
    {
        // отвечает за замену {{шорткодов}} в тексте (например в теле поста, или заголовке)

        $hook_name = get_option('mpg_hook_name');
        $hook_priority = get_option('mpg_hook_priority');

        if ($hook_name && $hook_priority) {
            add_action($hook_name, ['MPG_CoreController', 'mpg_view_multipages_standard'], $hook_priority);
        } else {

            if (defined('ELEMENTOR_PRO_VERSION') && defined('MPG_EXPERIMENTAL_FEATURES') && MPG_EXPERIMENTAL_FEATURES === true) {
                add_action('pre_handle_404', ['MPG_CoreController', 'mpg_view_multipages_elementor'], 1);
            } elseif ( defined( 'FUSION_BUILDER_VERSION' ) && ( defined( 'MPG_EXPERIMENTAL_FEATURES' ) && MPG_EXPERIMENTAL_FEATURES === true ) ) {
                add_action('posts_selection', ['MPG_CoreController', 'mpg_view_multipages_standard'], 1);
            } else if ( defined( 'TVE_IN_ARCHITECT' ) && ( defined( 'MPG_EXPERIMENTAL_FEATURES' ) && MPG_EXPERIMENTAL_FEATURES === true ) ) {
                add_action('posts_selection', ['MPG_CoreController', 'mpg_view_multipages_standard'], 1);
            } else {
                add_action('template_redirect', ['MPG_CoreController', 'mpg_view_multipages_standard'], 1);
            }
        }


        // отвечает за замену {{шорткодов}} в шорткоде wp и where. Например так [mpg where="" project-id=""] {{mpg_some}} [/mpg]
        //add_shortcode('mpg', ['MPG_CoreController', 'mpg_shortcode']);

      //  add_shortcode('mpg_match', ['MPG_CoreController', 'mpg_match']);
        // Отвечает за Spintax функционал
        add_shortcode('mpg_spintax', ['MPG_SpintaxController', 'mpg_spintax_shortcode']);

        add_shortcode('mpg_search', ['MPG_SearchController', 'mpg_search_shortcode']);
    }
}
