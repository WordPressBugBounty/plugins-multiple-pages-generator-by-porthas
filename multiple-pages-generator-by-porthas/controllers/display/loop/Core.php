<?php

namespace MPG\Display\Loop;

use MPG\Display\Base_Display;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Core
 *
 * @package MPG\Display\Loop
 */
abstract class Core extends Base_Display {
	/**
	 * Constants for ordering directions.
	 */
	const ORDER_BY_RANDOM = 'random'; // Order by random
	const ORDER_BY_ASC = 'asc';       // Order by ascending
	const ORDER_BY_DESC = 'desc';     // Order by descending

	/**
	 * Renders the content based on the provided attributes and conditions.
	 *
	 * @param int $project_id The ID of the project.
	 * @param array $args {
	 *     Array of arguments for rendering.
	 *
	 *     @type int|string $limit The limit for the number of items to render. (e.g., 10 or 'all')
	 *     @type bool $unique_rows Whether to render unique rows.
	 *     @type array $conditions {
	 *         Array of conditions to evaluate.
	 *
	 *         @type array[] $condition {
	 *             Individual condition to evaluate.
	 *
	 *             @type string $column The column to evaluate.
	 *             @type mixed $value The value to compare against.
	 *             @type string $operator The operator to use for comparison (e.g., '=', '>', '<', 'LIKE').
	 *         }
	 *         @type string $logic The logical operator to apply between conditions (AND/OR).
	 *     }
	 *     @type string $order_by The column to order the results by.
	 *     @type string $direction The direction to order results in (ASC or DESC).
	 *     @type string $base_url The base URL to be used in the rendered content.
	 * }
	 *
	 * @param string $content The content to render.
	 *
	 * @return string The rendered content. This will be an HTML string or another type of formatted content based on the implementation.
	 */
	public function render( int $project_id, array $args, string $content ): string {
		if ( empty( $project_id ) || ( $project_data = \MPG_ProjectModel::get_project_by_id( $project_id ) ) === false ) {
			throw new \Exception( __( 'Invalid or empty project id provided.', 'multiple-pages-generator-by-porthas' ) );
		}
		if ( ! empty( $args['direction'] ) && ! in_array( $args['direction'], array_keys( $this->get_order() ) ) ) {
			throw new \Exception( __( 'Attribute "direction" may be equals to "asc", "desc" or "random"', 'multiple-pages-generator-by-porthas' ) );
		}

		if ( ! empty( $args['order_by'] ) && empty( $args['direction'] ) && $args['direction'] !== self::ORDER_BY_RANDOM ) {
			throw new \Exception( __( 'Attribute `direction` must be used with `order-by` attribute. Exclusion: if direction is random', 'multiple-pages-generator-by-porthas' ) );
		}

		$headers       = \MPG_ProjectModel::get_headers_from_project( $project_data );
		$dataset_array = \MPG_Helper::mpg_get_dataset_array( $project_data );
		if ( empty( $dataset_array ) ) {
			throw new \Exception( __( 'No dataset found for project.', 'multiple-pages-generator-by-porthas' ) );
		}
		$limit = ! empty( $args['limit'] ) ? intval( $args['limit'] ) : 10000; // We really don't need to show more than this items on a page.

		if ( ! empty( $args['conditions']['conditions'] ) ) {

			//Translate placeholders to actual values. We need to do this before changing the current project id since the placeholders reference the current loop.
			$args['conditions']['conditions'] = $this->translate_conditions( $args['conditions']['conditions'] );
		}
		\MPG_ProjectModel::set_current_project_id( $project_id );
		$filtered_dataset_index = [];
		$count                  = 0;
		unset( $dataset_array[0] ); // we remove the headers row.
		if ( ! empty( $args['conditions']['conditions'] ) ) {
			foreach ( $dataset_array as $index => $row ) {
				if ( ! $this->evaluate_row_for_conditions( $args['conditions']['conditions'], $args['conditions']['logic'] ?? self::LOGIC_AND, $headers , $row) ) {
					continue;
				}

				$filtered_dataset_index[] = $index ;
			}
		}
		if ( empty( $filtered_dataset_index ) && ! empty( $args['conditions']['conditions'] ) ) {
			throw new \Exception( __( 'No data found for the provided conditions.', 'multiple-pages-generator-by-porthas' ) );
		}

		if ( empty( $filtered_dataset_index ) ) {
			$filtered_dataset_index = array_keys( $dataset_array );
		}
		//We can apply the ordering now.
		if ( ! empty( $args['direction'] ) ) {
			if ( $args['direction'] === self::ORDER_BY_RANDOM ) {
				shuffle( $filtered_dataset_index );
			} elseif ( ! empty( $args['order_by'] ) ) {
				if ( ( $order_index = \MPG_ProjectModel::headers_have_column( $headers, $args['order_by'] ) ) === false ) {
					throw new \Exception( __( 'Invalid column to order by.', 'multiple-pages-generator-by-porthas' ) );
				}
				$dataset_to_order = [];
				//We extract the data from the column we want to sort by.
				foreach ( $filtered_dataset_index as $dataset_index ) {
					$dataset_to_order[ $dataset_index ] = $dataset_array[ $dataset_index  ][ $order_index ] ?? '';
				}
				if ( $args['direction'] === self::ORDER_BY_ASC ) {
					asort( $dataset_to_order );
				} else {
					arsort( $dataset_to_order );
				}
				$filtered_dataset_index = array_keys( $dataset_to_order );
			}
		}
		$filtered_dataset_index = array_slice( $filtered_dataset_index, 0, $limit );

		//Now we need to apply the loop to the content.
		$urls_array = $project_data->urls_array ? json_decode( $project_data->urls_array ) : [];
		$short_codes              = \MPG_CoreModel::mpg_shortcodes_composer( $headers );
		$extended_content         = [];
		//We need to backup the internal row index to set it back once we are done.
		$current_row_backup = \MPG_CoreModel::get_current_row( $project_id );
		foreach ( $filtered_dataset_index as $index ) {
			//We set the internal index to the current row. This would be used to other shortcodes to get the current row that they will apply the value for, i.e mpg-if.
			\MPG_CoreModel::set_current_row( $project_id, $index);
			$content_template                     = $content;
			$strings                              = $dataset_array[ $index ];

			$strings[ count( $short_codes ) - 1 ] = \MPG_CoreModel::path_to_url( $urls_array[ $index - 1  ] );

			if ( ! empty( $args['base_url'] ) ) {
				$strings[ count( $short_codes ) - 1 ] = $args['base_url'] . $strings[ count( $short_codes ) - 1 ];
			}
			$content_template = \MPG_CoreModel::replace_content( $content_template, $strings, $short_codes, $project_data->space_replacer );
			if ( $args['unique_rows'] ?? false ) {
				$content_signature = crc32( $content_template );
				if ( isset( $extended_content[ $content_signature ] ) ) {
					continue;
				}
				$extended_content[ $content_signature ] = $content_template;
			} else {
				$extended_content[] = $content_template;
			}
		}
		//We reset the internal index to the original value.
		\MPG_CoreModel::set_current_row( $project_id, $current_row_backup );

		return implode( '', $extended_content );
	}


	/**
	 * Retrieves the available ordering options.
	 *
	 * This function returns an associative array of the available ordering options
	 * for displaying data. The keys are the constants representing the ordering
	 * directions, and the values are the localized strings for each direction.
	 *
	 * @return array The array of ordering options with keys as constants and values as localized strings.
	 */
	public function get_order() {
		return [
			self::ORDER_BY_RANDOM => __( 'Random', 'multiple-pages-generator-by-porthas' ),
			self::ORDER_BY_ASC    => __( 'Ascending', 'multiple-pages-generator-by-porthas' ),
			self::ORDER_BY_DESC   => __( 'Descending', 'multiple-pages-generator-by-porthas' ),
		];
	}
}