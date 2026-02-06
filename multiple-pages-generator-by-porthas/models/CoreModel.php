<?php

class MPG_CoreModel
{
	/**
	 * Holds the details of the current row found based on the path, i.e the row of the active virtual page.
	 *
	 * @var array $current_row
	 */
	private static $current_row = [];

	public static function mpg_get_redirect_rules( $needed_path, $projects = array() ) {

		global $wpdb, $pagenow, $post;
		$needed_path = preg_replace( '/(\/+)/', '/', $needed_path ); // Remove double slashes from URL.

		if ( is_admin() && false !== strpos( $needed_path, $pagenow ) ) {
			return [];
		}
		// If the requested path is empty, return an empty array.
		if ( empty( $needed_path ) ) {
			return [];
		}

		// If the requested URL is post/term then it will return an empty array.
		if ( function_exists( 'get_queried_object' ) && ! empty( get_queried_object() ) ) {
			return [];
		}

		// If the requested URL is page/post/cpt post then it will return an empty array.
		if ( ! empty( $post ) && $post->post_name === $needed_path ) {
			return [];
		}

		// If the requested URL is post/cpt single-post then it will return an empty array.
		if ( function_exists( 'is_single' ) && is_single() ) {
			return [];
		}

		// array of multi URLs
		$redirect_rules = [];
		$fetch_query    = "SELECT * FROM " . $wpdb->prefix . MPG_Constant::MPG_PROJECTS_TABLE;

		if ( empty( $projects ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
			$projects = $wpdb->get_results( sprintf( '%s %s', $fetch_query, ' WHERE `apply_condition` LIKE "%' . ICL_LANGUAGE_CODE . '%"' ) );
		}
		if ( empty( $projects ) ) {
			$projects = $wpdb->get_results( $fetch_query );
		}
		$needs_path_variants = MPG_Helper::generate_path_variants( $needed_path );

		$urls_data = [];

		foreach ( $projects as $project ) {
			$urls_array  = $project->urls_array ? json_decode( $project->urls_array ) : [];
			if ( empty( $urls_array ) ) {
				$urls_data = MPG_DatasetModel::get_index( $project->id, 'permalinks' );
				$urls_array = array_keys( $urls_data );
			}
			$urls_array  = is_array( $urls_array ) ? $urls_array : [];
			try {
				if ( null === $project->schedule_periodicity ) {
					$updated_project_data = MPG_Helper::mpg_live_project_data_update( $project );
					if ( is_object( $updated_project_data ) ) {
						$project = $updated_project_data;
						if ( $project->urls_array ) {
							$urls_array = json_decode( $project->urls_array );
							$urls_array = is_array( $urls_array ) ? $urls_array : array();
						}
					}
				}
			} catch ( \Exception $exception ) {
				// Use project_id parameter to prevent circular reference in logging
				MPG_LogsController::mpg_write( $project->id ?? 0, 'warning', $exception->getMessage() . ' path: ' . $needed_path);
			}
			if ( empty( $urls_array ) ) {
				continue;
			}
			foreach ( $urls_array as $iteration => $raw_single_url ) {
				$single_url = urldecode( $raw_single_url );
				$single_url = preg_replace( '/(\/+)/', '/', $single_url ); // Remove double slashes from URL.

				switch ( $project->url_mode ) {

					case 'with-trailing-slash':
						if ( ! MPG_Helper::mpg_string_end_with( $_SERVER['REQUEST_URI'], '/' ) ) {
							if ( $single_url === $needed_path ) {
								wp_safe_redirect( $_SERVER['REQUEST_URI'] . '/', 302 );
								break;
							}
						}
						break;

					case 'without-trailing-slash':
						if ( MPG_Helper::mpg_string_end_with( $_SERVER['REQUEST_URI'], '/' ) ) {
							if ( $single_url === $needed_path ) {
								wp_safe_redirect( rtrim( $_SERVER['REQUEST_URI'], '/' ), 302 );
								break;
							}
						}
						break;

					default:
				}
				$url_match_condition = isset( $needs_path_variants[ $single_url ] );

				$lang_str = $project->apply_condition;

				if ( $url_match_condition ) {
					// it's important to check is position eqal to false, but not a 0 or any other numbers .
					if ( is_string( $lang_str ) && strpos( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], $lang_str ) === false ) {
						return [];
					}
					$redirect_rules = [
						'template_id' => $project->template_id,
						'project_id'  => $project->id
					];
					if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
						//Polylang stores the translated post ID in a different post, so we need to localize it.
						if ( defined( 'POLYLANG_VERSION' ) ) {
							$post_id                       = pll_get_post( $project->template_id, ICL_LANGUAGE_CODE );
							$redirect_rules['template_id'] = empty( $post_id ) ? $project->template_id : $post_id;
						} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {

							$redirect_rules['template_id'] = apply_filters( 'wpml_object_id', $project->template_id, $project->entity_type, true, ICL_LANGUAGE_CODE );
						}
					}


					global $wp_object_cache;

					// In this way solving the problem with mess in generated pages with Redis Object caching enabled.
					if ( defined( 'WP_REDIS_VERSION' ) && method_exists( $wp_object_cache, 'redis_instance' ) ) {
						$wp_object_cache->redis_instance()->del( str_replace( '_', '', $wpdb->prefix ) . ':posts:' . $project->template_id );
					}


					if ( defined( 'MPG_EXPERIMENTAL_FEATURES' ) && MPG_EXPERIMENTAL_FEATURES === true ) {

						if ( extension_loaded( 'memcached' ) ) {

							if ( defined( __NAMESPACE__ . '\PLUGIN_SLUG' ) && __NAMESPACE__ . '\PLUGIN_SLUG' === 'sg-cachepress' ) {

								$memcache = new \Memcached();
								if ( defined( 'MPG_MEMCACHED_HOST' ) && defined( 'MPG_MEMCACHED_PORT' ) ) {
									$memcache->addServer( MPG_MEMCACHED_HOST, MPG_MEMCACHED_PORT );
								} else {
									$memcache->addServer( '127.0.0.1', '11211' );
								}

								$keys_list = $memcache->getAllKeys();
								if ( $keys_list ) {
									foreach ( $keys_list as $index => $key ) {
										if ( strpos( $key, ':posts:' . $project->template_id ) !== false ) {
											$memcache->delete( $keys_list[ $index ] );
										}
									}
								}
							}
						}
					}

					self::$current_row[$project->id] = $iteration;

					// This is for new-datasets that have an index file.
					if ( ! empty( $urls_data ) && array_key_exists( $raw_single_url, $urls_data ) ) {
						$item = $urls_data[ $raw_single_url ];
						self::$current_row[ $project->id . '_index' ] = $item;
					}

					break 2; // Останавливаем весь цикл. Ведь один УРЛ найден.
				}
			}
		}

		return $redirect_rules;
	}
	/**
	 * Replaces shortcodes in the content with the provided strings and handles special cases.
	 *
	 * This function processes the content to replace shortcodes with the corresponding strings.
	 * It also handles special cases such as shortcodes within href tags and loop elements.
	 *
	 * @param string $content        The content in which shortcodes need to be replaced.
	 * @param array $strings        The array of strings to replace the shortcodes with.
	 * @param array $shortcodes     The array of shortcodes to be replaced.
	 * @param string $space_replacer The character to replace spaces with in URLs.
	 *
	 * @return string The content with shortcodes replaced by the corresponding strings.
	 */
	public static function replace_content( string $content, array $strings, array $shortcodes, string $space_replacer ): string {
		MPG_Parser::localize_content( $content );
		$get_shortcodes_regexp = '/\[mpg.*?\[\/mpg.*?\]|\<\!-- wp:mpg\/loop.*?\<\!-- \/wp:mpg\/loop --\>/s';

		preg_match_all( $get_shortcodes_regexp, $content, $mpg_shortcodes, PREG_SET_ORDER, 0 );

		//We remove the loop elements that might reference some other projects.
		if ( ! empty( $mpg_shortcodes ) ) {
			$placeholers = [];
			foreach ( $mpg_shortcodes as $index => $shortcode ) {
				$placeholers[] = '(placeholder_replacer_' . $index . ')';
			}

			$mpg_shortcodes = MPG_Helper::array_flatten( $mpg_shortcodes );

			$content = str_replace( $mpg_shortcodes, $placeholers, $content );
		}
		MPG_Parser::normalize_row( $strings );


		//We need to address when the shortcodes are used in href tags, in this case we need to normalize this value for url use.
		$re = '/href=\\\\?".*?\\\\?"/m';

		preg_match_all($re, $content, $href_matches, PREG_SET_ORDER, 0);
		//If the shortcodes are used in URL, we slugify them.
		if ( ! empty( $href_matches ) ) {
			$strings_url = MPG_Helper::slugify_strings( $strings, $space_replacer );
			foreach ( $href_matches as $href ) {
				$content = str_replace( $href[0], preg_replace( $shortcodes, $strings_url, $href[0] ), $content );
			}
		}
		$content = preg_replace( $shortcodes, $strings, $content );

		//we add back the loop elements
		if ( ! empty( $mpg_shortcodes ) ) {

			$get_placeholders_regexp = '/\(placeholder_replacer_\d{1,3}\)/s';

			preg_match_all( $get_placeholders_regexp, $content, $mpg_placeholders, PREG_SET_ORDER, 0 );

			$mpg_placeholders = MPG_Helper::array_flatten( $mpg_placeholders );

			return str_replace( $mpg_placeholders, $mpg_shortcodes, $content );
		}

		return do_shortcode( $content );
	}
    public static function mpg_shortcode_replacer($content, $project_id)
    {
	    if ( empty( $content ) ) {
		    return $content;
	    }
        preg_match_all('/{{mpg_\S+}}/m', $content, $matches, PREG_SET_ORDER, 0);

	    if ( empty( $matches ) ) {
		    return $content;
	    }

        $project = MPG_ProjectModel::get_project_by_id($project_id);
	    $headers = MPG_ProjectModel::get_headers_from_project( $project);
        $short_codes = self::mpg_shortcodes_composer($headers);

        $urls_array = $project->urls_array ? json_decode($project->urls_array) : [];
        if ( empty( $urls_array ) && is_array( MPG_Helper::$urls_array ) ) {
            $urls_array = MPG_Helper::$urls_array;
        }

        $strings = false;
		$url_match_index = self::get_current_row($project_id);
	    if ( $url_match_index !== false ) {
		    $strings = self::get_current_datarow( $project_id );

		    if ( ! is_array( $strings ) ) {
			    return $content;
		    }
		    // In the URL column, there is a relative address, like /new-york/, and if the user writes [mpg]{{mpg_url}}[/mpg]
		    // then if their WP is installed in a subdirectory (sub), the address will be domain.com/new-york/, not domain.com/sub/new-york
		    // Therefore, we replace the URL in such a way that it is correct.
		    //We always have the URL in the last column. If it is not in the dataset, we will add it to the shortcodes to enable the mpg_url shortcode.
		    $strings[ count( $short_codes ) - 1 ] = MPG_CoreModel::mpg_prepare_mpg_url( $project, $urls_array, $url_match_index );
	    } else {
		    return $content;
	    }

	    return self::replace_content( $content, $strings, $short_codes, $project->space_replacer );
    }
	/**
	 * Project id.
	 *
	 * @param int $project_id project id.
	 */
	public static function mpg_thumbnail_replacer( $project_id ) {
		$thumbnail_html = '';
		$project        = MPG_ProjectModel::get_project_by_id( $project_id );
		// do action with short codes.
		try {
			$headers = MPG_ProjectModel::get_headers_from_project( $project );
		} catch ( Exception $e ) {
			do_action( 'themeisle_log_event', MPG_NAME, $e->getMessage(), 'error', __FILE__, __LINE__ );
			return $thumbnail_html;
		}

		$strings = self::get_current_datarow( $project_id );

		if ( ! is_array( $strings ) || empty( $strings ) ) {
			return $thumbnail_html;
		}

		MPG_Parser::normalize_row($strings);

		$featured_image_url_column = in_array( 'image', $headers ) ? array_search( 'image', $headers ) : false;
		if ( $featured_image_url_column === false ) {
			$featured_image_url_column = in_array( 'featured_image', $headers ) ? array_search( 'featured_image', $headers ) : false;
		}
		if ( $featured_image_url_column !== false && ! empty( $strings[ $featured_image_url_column ] ) ) {
			$thumbnail_html = '<img src="' . esc_url( $strings[ $featured_image_url_column ] ) . '" ';
		} else {
			return $thumbnail_html;
		}

		$alt_text_column = in_array( 'featured_image_alt', $headers ) ? array_search( 'featured_image_alt', $headers ) : false;
		if ( $alt_text_column !== false && ! empty( $strings[ $alt_text_column ] ) ) {
			$thumbnail_html .= ' alt="' . esc_attr( trim( strip_tags( self::replace_shortcodes_in_content( $strings[ $alt_text_column ], $headers, $strings ) ) ) ) . '" ';
		}
		// data-attributes-empty is used as placeholder to replace with attributes such as class, style from post_thumbnail_html filter.
		$thumbnail_html .= ' data-attributes-empty />';
		return $thumbnail_html;
	}

	/**
	 * Prepare shortcodes for replacement.
	 *
	 * @param array $headers
	 *
	 * @return array
	 */
	public static function mpg_shortcodes_composer( array $headers ): array
    {
	    $short_codes = [];
	    foreach ( $headers as $raw_header ) {
			$header = strtolower( $raw_header );
			if($header === 'mpg_url' || $header === 'url') {
				// We always add the url last. If it is not in the dataset, we will add it to the shortcodes to enable the mpg_url shortcode.
				continue;
			}
		    if ( strpos( $header, 'mpg_' ) === 0 ) {
			    $short_code = "/{{" . str_replace( '/', '\/', $header ) . "}}/"; // create template for preg_replace function
		    } else {
			    $short_code = "/{{mpg_" . str_replace( '/', '\/', $header ) . "}}/"; // create template for preg_replace function
		    }

		    $short_code = str_replace( ' ', '_', $short_code );
		    array_push( $short_codes, $short_code );
	    }
		//We add the url in the dataset.
	    $short_codes[] = "/(https?:\/\/)?{{mpg_url}}/";
        return $short_codes;
    }



	public static function get_ceil_value_by_header( $current_project, $dataset_array, $header_value ) {

		$url_index = self::get_current_row( $current_project->id );
		if ( $url_index === false ) {
			return '';
		}
		$strings = $dataset_array[ $url_index + 1 ];
		$headers = MPG_ProjectModel::get_headers_from_project(  $current_project );
		$headers = MPG_ProjectModel::normalize_headers($headers);
		$shortcode_column_index = MPG_ProjectModel::headers_have_column( $headers, $header_value );
		return $strings[ $shortcode_column_index ];
	}


	/**
	 * Replace shortcodes in content.
	 *
	 * @param string $content Content to replace.
	 * @param array  $headers Headers of the project.
	 * @param array  $strings Dataset row with the values which will be replaced.
	 *
	 * @return string
	 */
	public static function replace_shortcodes_in_content( string $content, array $headers, array $strings ):string {
		$shortcodes = self::mpg_shortcodes_composer( $headers );

		return preg_replace( $shortcodes, $strings, $content );
	}

	/**
	 * Generate the URL from the path.
	 *
	 * @param $path
	 *
	 * @return string
	 */
	public static function path_to_url( $path ) {
		//Preserve desired trailing slash behaviour.
		// If we have query strings, we need to account for this.
		$home_url_parts = explode( '?', home_url( $path ) );

		if ( str_ends_with( $path, '/' ) ) {
			$home_url_parts[0] = trailingslashit( $home_url_parts[0] );
		} else {
			$home_url_parts[0] = rtrim( $home_url_parts[0], '/' );
		}

		return $home_url_parts[0] . ( isset( $home_url_parts[1] ) ? '?' . $home_url_parts[1] : '' );
	}

	/**
	 * Prepare the URL for the shortcode replacement.
	 *
	 * @param $project
	 * @param $urls_array
	 * @param $index
	 *
	 * @return string
	 */
	public static function mpg_prepare_mpg_url( $project, $urls_array, $index ) {
		return self::path_to_url( $urls_array[ $index ] );
	}

	/**
	 * Return the current row details from the dataset.
	 *
	 * @param $project_id
	 *
	 * @return false|mixed
	 * @throws Exception
	 */
	public static function get_current_datarow( $project_id ) {
		$index = self::get_current_row( $project_id );

		if ( $index === false ) {
			return false;
		}

		// For projects with index file, we get the current row from particular chunks rather than loading the whole dataset.
		if ( isset( self::$current_row[ $project_id . '_index' ] )  && is_array( self::$current_row[ $project_id . '_index' ] ) ) {
			$item = self::$current_row[ $project_id . '_index' ];
			$dataset = MPG_DatasetModel::get_dataset_row( $project_id, $item['chunk'], $item['offset'] );

			if ( is_array( $dataset ) && ! empty( $dataset ) ) {
				return $dataset;
			}
		}

		$project       = MPG_ProjectModel::get_project_by_id( $project_id );
		$dataset_array = MPG_Helper::mpg_get_dataset_array( $project );

		return $dataset_array[ $index + 1 ] ?? false;
	}
	/**
	 * Get the current row details from the dataset.
	 *
	 * @param int $project_id Project id to look for.
	 *
	 * @return false|int|mixed|string
	 * @throws Exception
	 */
	public static function get_current_row( int $project_id ) {
		if ( isset( self::$current_row[ $project_id ] ) ) {
			return self::$current_row[ $project_id ];
		}
		//If this is not set we need to fetch it.
		$url_path            = MPG_Helper::mpg_get_request_uri();
		$needs_path_variants = MPG_Helper::generate_path_variants( $url_path );
		$project             = MPG_ProjectModel::get_project_by_id( $project_id );

		$urls_array          = $project->urls_array ? json_decode( $project->urls_array ) : [];
		if ( empty( $urls_array ) && is_array( MPG_Helper::$urls_array ) ) {
			$urls_array = MPG_Helper::$urls_array;
		}

		foreach ( $urls_array as $index => $single_url ) {
			$url_match_condition = isset( $needs_path_variants[ $single_url ] );
			if ( $url_match_condition ) {
				self::$current_row[ $project_id ] = $index;

				return $index;
			}
		}

		return false;
	}
	/**
	 * Set the current row index.
	 *
	 * @param $project_id
	 * @param $index
	 */
	public static function set_current_row($project_id, $index){
		self::$current_row[ $project_id ] = $index;
	}
	/**
	 * Reset internal index of the current row.
	 *
	 * @param $project_id
	 *
	 * @return void
	 */
	public static function reset_current_row( $project_id = null ) {
		if ( empty( $project_id ) ) {
			self::$current_row = [];
		} else {
			self::$current_row[ $project_id ] = false;
		}
	}
}
