<?php

class MPG_Validators
{
	const SOURCE_TYPE_URL = 'direct_link';
	const SOURCE_TYPE_UPLOAD = 'upload_file';

    public static function mpg_match($current_project_id, $search_in_project_id, $current_header, $match_with)
    {
        if (!$current_project_id) {
            throw new Exception(__('Attribute "current-project-id" is missing or has wrong project id', 'multiple-pages-generator-by-porthas'));
        }

        if (!$search_in_project_id) {
            throw new Exception(__('Attribute "search-in-project-id" is missing or has wrong project id', 'multiple-pages-generator-by-porthas'));
        }

        if (!$current_header) {
            throw new Exception(__('Attribute "current-header" is missing', 'multiple-pages-generator-by-porthas'));
        }

        if (!$match_with) {
            throw new Exception(__('Attribute "match-with" is missing', 'multiple-pages-generator-by-porthas'));
        }
    }

    public static function mpg_order_params($order_by, $direction)
    {
        if ($direction && !in_array($direction, ['asc', 'desc', 'random'])) {
            throw new Exception(__('Attribute "direction" may be equals to "asc", "desc" or "random"', 'multiple-pages-generator-by-porthas'));
        }

        if (!$order_by && $direction && $direction !== 'random') {
            throw new Exception(__('Attribute `direction` must be used with `order-by` attribute. Exclusion: if direction is random', 'multiple-pages-generator-by-porthas'));
        }
    }

	/**
	 * Check if the given value is a valid timestamp.
	 *
	 * @param string $timestamp
	 *
	 * @return bool
	 */
	public static function is_timestamp( $timestamp ): bool {
		return ( (string) (int) $timestamp === $timestamp )
		       && ( $timestamp <= PHP_INT_MAX )
		       && ( $timestamp >= ~PHP_INT_MAX );
	}

	public static function nonce_check(){
		check_ajax_referer( MPG_BASENAME, 'securityNonce' );
		current_user_can( MPG_MenuController::MENU_ROLE ) || 	wp_die( -1, 403 );;
	}

	public static function validate_source_type( $source_type, $default_input = self::SOURCE_TYPE_UPLOAD ) {
		$available_sources = [ self::SOURCE_TYPE_UPLOAD, self::SOURCE_TYPE_URL ];
		$default           = in_array( $default_input, $available_sources ) ? $default_input : self::SOURCE_TYPE_UPLOAD;
		if ( empty( $source_type ) ) {
			return $default;
		}
		if ( ! in_array( $source_type, $available_sources ) ) {
			return $default;
		}

		return $source_type;
	}
}
