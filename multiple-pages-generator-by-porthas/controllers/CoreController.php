<?php
require_once(realpath(__DIR__) . '/../controllers/LogsController.php');

require_once(realpath(__DIR__) . '/../models/CoreModel.php');
require_once(realpath(__DIR__) . '/../models/SEOModel.php');

require_once(realpath(__DIR__) . '/../helpers/Validators.php');

class MPG_CoreController
{
    public static function core($redirect_rules, $post, $template_post_id, $path)
    {
        global  $wp_query;

        // do changes in title and content
        $project_id = $redirect_rules['project_id'];
	    MPG_ProjectModel::set_current_project_id( $project_id );
        add_action('elementor/frontend/element/before_render', function ($post) use ($project_id) {
            return MPG_CoreModel::mpg_shortcode_replacer($post->post_content, $project_id);
        });
	    add_filter( 'elementor/frontend/the_content', function ( $content ) use ( $project_id ) {
		    return MPG_CoreModel::mpg_shortcode_replacer( $content, $project_id );
	    } );

        $project = MPG_ProjectModel::get_project_by_id($project_id);
	    $post_modified = MPG_ProjectModel::get_vpage_modified_date( $project );
	    if ( $post_modified !== false ) {
		    $post_date = $post->post_date;
		    //We choose which is the lastest one between those two, to avoid changing the date of the post to something older vs the current one.
		    if ( strtotime( $post_date ) < $post_modified ) {
			    $post->post_modified     = date( 'Y-m-d H:i:s', $post_modified );
			    $post->post_modified_gmt = gmdate( 'Y-m-d H:i:s', $post_modified );

			    add_filter( 'get_the_modified_date', function ( $the_time, $format, $current_post ) use ( $post_modified, $post ) {
				    if ( $current_post->ID !== $post->ID ) {
					    return $the_time;
				    }

				    return date( ! empty( $format ) ? $format : get_option( 'date_format' ), $post_modified );
			    }, 99, 3 );
			    wp_cache_replace( $post->ID, $post, 'posts' );
		    }
	    }

	    // Решает проблему с соц. плагинами. (правильные ссылки на страницы без шорткодов)
	    $post->post_title = MPG_CoreModel::mpg_shortcode_replacer($post->post_title, $project_id);
	    $post->post_content = MPG_CoreModel::mpg_shortcode_replacer($post->post_content, $project_id);
        // Override canonical URL.
        if ( apply_filters( 'mpg_enable_canonical_url_generate', true ) ) {
            remove_action( 'wp_head', 'ampforwp_home_archive_rel_canonical', 1 );
            remove_action( 'wp_head', 'rel_canonical' );
            remove_action( 'template_redirect', 'redirect_canonical' );

            add_action( 'wp_head', function () use ($project) {
                global $wp;

                $trail_slash = $project->url_mode === 'without-trailing-slash' ? '' : '/';

                printf('<link rel="canonical" href="%1$s' . $trail_slash . '">' . "\n",  esc_url_raw(home_url($wp->request)));

            }, 1, 1 );
        }

		$thumbnail_info = MPG_CoreModel::mpg_thumbnail_replacer( $project_id );
		/**
		* Replace thumbnail image.
		*
		* @param mixed $html thumbnail image html.
		*/
	    add_filter(
		    'post_thumbnail_html',
		    function ( $html, $post_id, $post_thumbnail_id, $size, $attr ) use ( $thumbnail_info ) {

			    if ( ! empty( $thumbnail_info ) ) {
				    $html = self::apply_featured_attributes( $thumbnail_info, $post_id, $post_thumbnail_id, $size, $attr ) ;

			    }

			    return $html;
		    },
		    10,
		    5
	    );

		/**
		* Replace thumbnail image.
		*
		* @param boolean thumbnail_info check template has thumbnail or not.
		*/
		add_filter(
			'has_post_thumbnail',
			function ( $has_thumbnail ) use ( $thumbnail_info ) {
				if ( ! empty( $thumbnail_info ) ) {
					$has_thumbnail = true;
				}
				return $has_thumbnail;
			},
			10
		);

        MPG_SEOModel::mpg_all_in_one_seo_pack($project_id);

        MPG_SEOModel::mpg_yoast($project_id);

        MPG_SEOModel::mpg_rank_math($post, $project_id);

        MPG_SEOModel::mpg_seopress($project_id);

        MPG_SEOModel::mpg_squirrly_seo($project_id);

        MPG_SEOModel::mpg_the_seo_framework( $project_id );
	    add_action( 'wp_head', function () use ( $project_id, $path ) {
		    ob_start( function ( $buffer ) use ( $project_id ) {
			    return MPG_CoreModel::mpg_shortcode_replacer( $buffer, $project_id );
		    } );
	    }, 9, 0 );
	    add_action( 'wp_print_footer_scripts', function () use ( $project_id, $path ) {
		    ob_end_flush();
	    }, 10, 0 );
        add_action('wp_footer', function () {
            if (!mpg_app()->is_premium()) {

                $position =  get_option('mpg_branding_position');

                if($position === 'left'){
                    $float = 'le' . 'ft: 2' . '0px;';
                }else{
                    $float = 'ri' . 'ght: 2' . '0px;';
                }

                printf( '<span style="position:fixed; %s bottom: 10px; z-index:1000; font-size: 16px">Generated by <a href="https://mpgwp.com" target="_blank" rel="nofollow">MPG</a></span>', $float );
            }
        });


        $hook_name = get_option('mpg_cache_hook_name') ? get_option('mpg_cache_hook_name') : 'wp_print_footer_scripts';
        $hook_priority = get_option('mpg_cache_hook_priority') ? get_option('mpg_cache_hook_priority') : 10;

        // setup template post as global, this is needed for the_title(), the_permalink()
        setup_postdata($GLOBALS['post'] = &$post);
        // set the post as cached, it is necessary for the get_post () function, it will return the replaced data when this function is called
        set_transient( (string) $post->ID, $post );
        // set status code 200, because on default this page not exist and return 404 code

        // Перезаписывает URL (основной) для поста\кастом поста\страницы. Это решать проблему с заменой шорткодов в УРЛах
        // если у пользователя есть плагины, например для шаринга в соц. сети, то там неправильные ссылки,
        // или с точки зрения SEO: <link rel="alternate" с этим хуком становится правильным.

        foreach (['post', 'page', 'post_type'] as $type) {
            add_filter($type . '_link', function ($url, $post_id, $sample) use ($type) {
                return apply_filters('wpse_link', $url, $post_id, $sample, $type);
            }, 1, 3);
        }

        $permalink = get_permalink($template_post_id);

        add_filter('wpse_link', function ($url, $post_id) use ($permalink, $template_post_id) {
            global $wp;
	        if ( defined( 'POLYLANG_VERSION' ) && $template_post_id !== $post_id ) {
				// We check if the permalink belongs is a translated version of the template.
		        $translations = pll_get_post_translations( $post_id );
		        if ( in_array( $template_post_id, $translations ) ) {
			        $language_to_switch = array_search( $post_id, $translations );

			        return PLL()->links_model->switch_language_in_link( home_url( $wp->request ), PLL()->model->get_language( $language_to_switch ) );
		        }
	        }
            if ($url === $permalink ) {
                return home_url($wp->request);
            } else {
                return  $url;
            }
        }, 1, 4);

        status_header( 200 );
        // set important settings for page query
        $wp_query->queried_object = $post;
        $wp_query->is_404 = false;
        $wp_query->queried_object_id = $post->ID;
        $wp_query->post_count = 1;
        $wp_query->current_post = -1;
        $wp_query->posts = array($post);
        $wp_query->is_author = false;

        if ($project->entity_type === 'post') {
            $wp_query->is_single = true;
            $wp_query->is_page = false;
            $wp_query->is_singular = true;
        } else {
            $wp_query->is_single = false;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
        }
	    defined( 'MPG_IS_SINGLE' ) || define( 'MPG_IS_SINGLE', true );
    }


    // Create the virtual page with content from template and set settings for view this page like normal WP page
    public static function mpg_view_multipages_elementor($false, $wp_query) // $wp_query не удалять
    {

        $path = MPG_Helper::mpg_get_request_uri(); // это та часть что идет после папки установки WP. тпиа wp.com/xxx
        $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($path);

        if ($redirect_rules) {
            // If requested URL is available in array of all URL's

            $template_post_id = $redirect_rules['template_id'];
            $post = get_post($template_post_id);

            self::core($redirect_rules, $post, $template_post_id, $path);
        } else {
            MPG_LogsController::mpg_write($redirect_rules['project_id'], 'warning', __('URL generated in MPG and some page or post slug is equal.', 'mpg'));
        }
    }

    // Create the virtual page with content from template and set settings for view this page like normal WP page
    public static function mpg_view_multipages_standard()
    {
        global $mpg_dataset, $mpg_urls_array;
        $path = MPG_Helper::mpg_get_request_uri(); // это та часть что идет после папки установки WP. тпиа wp.com/xxx
        $redirect_rules = MPG_CoreModel::mpg_get_redirect_rules($path);

        if ($redirect_rules) {
            // If requested URL is available in array of all URL's

            $template_post_id = $redirect_rules['template_id'];
            $post = get_post($template_post_id);

            if (is_404() && $post->post_status !== 'draft') {
                // define('IS_MPG_PAGE', true);
                // echo 'DEFINE CONST';
                self::core($redirect_rules, $post, $template_post_id, $path);
            } else {
                MPG_LogsController::mpg_write($redirect_rules['project_id'], 'warning', __('URL generated in MPG and some page or post slug is equal.', 'mpg'));
            }
        }
    }


    // For ajax call
    public static function mpg_shortcode_ajax()
    {
        MPG_Validators::nonce_check();

        try {

	        $content    = isset( $_POST['content'] ) ? $_POST['content'] : '';
	        $project_id = isset( $_POST['projectId'] ) ? sanitize_text_field( $_POST['projectId'] ) : '';
	        $where      = isset( $_POST['where'] ) ? sanitize_text_field( $_POST['where'] ) : '';
	        $operator   = isset( $_POST['operator'] ) ? sanitize_text_field( $_POST['operator'] ) : \MPG\Display\Base_Display::OPERATOR_HAS_VALUE;

	        $direction = isset( $_POST['direction'] ) ? sanitize_text_field( $_POST['direction'] ) : '';
	        $order_by  = isset( $_POST['orderBy'] ) ? sanitize_text_field( $_POST['orderBy'] ) : '';

	        $limit       = isset( $_POST['limit'] ) ? (int) $_POST['limit']  : 4; // тут 4, т.к. count(arr) начинается с 0
	        $unique_rows = isset( $_POST['uniqueRows'] ) && $_POST['uniqueRows'] === 'yes';

	        $base_url = isset( $_POST['baseUrl'] ) ? sanitize_text_field( $_POST['baseUrl'] ) : '';
	        $inline   = new \MPG\Display\Loop\Inline();
	        $results  = $inline->render( $project_id, [
		        'limit'       => $limit,
		        'direction'   => $direction,
		        'order_by'    => $order_by,
		        'unique_rows' => $unique_rows,
		        'base_url'    => $base_url,
		        'conditions'  => [
			        'conditions' => $inline->extract_where_conditions( $where ),
			        'logic'      => $operator
		        ]
	        ], $content );

	        echo '{"success": true, "data":"' . str_replace( "\n", '<br>', $results ) . '"}';
	        wp_die();
        } catch (Exception $e) {

            do_action( 'themeisle_log_event', MPG_NAME, sprintf( 'Can\'t show preview due to error. Details: %s', $e->getMessage() ), 'debug', __FILE__, __LINE__ );

            echo json_encode([
                'success' => false,
                'error' => __('Can\'t show preview due to error. Details: ' . $e->getMessage())
            ]);
            wp_die();
        }
    }

	/**
	 * Apply attributes to the featured image.
	 *
	 * @param $html
	 * @param $post_id
	 * @param $post_thumbnail_id
	 * @param $size
	 * @param $attr
	 *
	 * @return string The image html.
	 */
	public static function apply_featured_attributes( $html, $post_id, $post_thumbnail_id, $size, $attr ):string {
		$attr = wp_parse_args( $attr, [] );

		if ( isset( $attr['src'] ) ) {
			unset( $attr['src'] );
		}
		if ( isset( $attr['alt'] ) ) {
			unset( $attr['alt'] );
		}
		if ( ! isset( $attr['class'] ) ) {
			$attr['class'] = '';
		}
		$size_class = $size;

		if ( is_array( $size_class ) ) {
			$size_class = implode( 'x', $size_class );
		}

		$attr['class'] .= "attachment-$size_class size-$size_class";
		$attr          = array_map( 'esc_attr', $attr );
		$attr_html = '';
		foreach ( $attr as $name => $value ) {
			$attr_html .= " $name=" . '"' . $value . '"';
		}

		return str_replace( 'data-attributes-empty', $attr_html, $html );
	}
}
