<?php

class MPG_Validators
{

    public static function mpg_match($current_project_id, $search_in_project_id, $current_header, $match_with)
    {
        if (!$current_project_id) {
            throw new Exception(__('Attribute "current-project-id" is missing or has wrong project id', 'mpg'));
        }

        if (!$search_in_project_id) {
            throw new Exception(__('Attribute "search-in-project-id" is missing or has wrong project id', 'mpg'));
        }

        if (!$current_header) {
            throw new Exception(__('Attribute "current-header" is missing', 'mpg'));
        }

        if (!$match_with) {
            throw new Exception(__('Attribute "match-with" is missing', 'mpg'));
        }
    }

    public static function mpg_order_params($order_by, $direction)
    {
        if ($direction && !in_array($direction, ['asc', 'desc', 'random'])) {
            throw new Exception(__('Attribute "direction" may be equals to "asc", "desc" or "random"', 'mpg'));
        }

        if (!$order_by && $direction && $direction !== 'random') {
            throw new Exception(__('Attribute `direction` must be used with `order-by` attribute. Exclusion: if direction is random', 'mpg'));
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
}
