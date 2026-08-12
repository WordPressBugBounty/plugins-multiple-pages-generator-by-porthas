<?php

namespace MPG\Display;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Base_Display
 *
 * @package MPG\Display
 */
abstract class Base_Display implements DisplayInterface {

	/**
	 * Whether the last translate_conditions() call resolved a current-page context tag
	 * (e.g. {{mpg_current_title}}). Loops use this to keep the current page's own row,
	 * which is otherwise excluded as a self-link (#734).
	 *
	 * @var bool
	 */
	protected $conditions_used_context_tag = false;

	/**
	 * Logical AND operator for conditions.
	 * Used to require all conditions to be met.
	 */
	const LOGIC_AND = 'all';

	/**
	 * Logical OR operator for conditions.
	 * Used to require any condition to be met.
	 */
	const LOGIC_OR = 'any';

	/**
	 * Operator to check if a value exists.
	 */
	const OPERATOR_HAS_VALUE = 'has_value';

	/**
	 * Operator to check if values are equal.
	 */
	const OPERATOR_EQUALS = 'equals';

	/**
	 * Operator to check if values are not equal.
	 */
	const OPERATOR_NOT_EQUALS = 'not_equals';

	/**
	 * Operator to check if a value is empty.
	 */
	const OPERATOR_EMPTY = 'empty';

	/**
	 * Operator to check if a value contains a substring.
	 */
	const OPERATOR_CONTAINS = 'contains';

	/**
	 * Operator to check if a value does not contain a substring.
	 */
	const OPERATOR_NOT_CONTAINS = 'not_contains';

	/**
	 * Operator to check if a value is greater than another value.
	 */
	const OPERATOR_GREATER_THAN = 'greater_than';

	/**
	 * Operator to check if a value is greater or equal than another value.
	 */
	const OPERATOR_GREATER_THAN_EQUALS = 'gte';

	/**
	 * Operator to check if a value is less than another value.
	 */
	const OPERATOR_LESS_THAN = 'less_than';
	/**
	 * Operator to check if a value is less or equals than another value.
	 */
	const OPERATOR_LESS_THAN_EQUALS = 'lte';

	/**
	 * Operator to check if a value matches a regular expression.
	 */
	const OPERATOR_REGEX = 'regex';


	/**
	 * Normalizes a condition part.
	 *
	 * @param string $part The part to normalize.
	 *
	 * @return string The normalized part.
	 */
	public function normalize_condition_part( $part ) {
		return trim( html_entity_decode( $part ), " \t\n\r\0\x0B\"'”″" );
	}

	/**
	 * Evaluates a condition.
	 *
	 * @param string $value The value to evaluate.
	 * @param string $condition_value The value to compare against.
	 * @param string $operator The operator to use for comparison.
	 *
	 * @return bool Whether the condition is met.
	 */
	protected function evaluate_condition( string $value, string $condition_value, string $operator ): bool {
		$value = trim( $value );
		switch ( $operator ) {
			case self::OPERATOR_EQUALS:
				return strtolower( $value ) === strtolower( $condition_value );
			case self::OPERATOR_NOT_EQUALS:
				return $value != $condition_value;
			case self::OPERATOR_EMPTY:
				return empty( $value );
			case self::OPERATOR_CONTAINS:
				return strpos( strtolower( $value ), strtolower( $condition_value ) ) !== false;
			case self::OPERATOR_NOT_CONTAINS:
				return strpos( strtolower( $value ), strtolower( $condition_value ) ) === false;
			case self::OPERATOR_GREATER_THAN:
				$value = is_numeric( $value ) ? $value : false;
				if ( $value === false ) {
					return false;
				}

				return $value > $condition_value;
			case self::OPERATOR_GREATER_THAN_EQUALS:
				$value = is_numeric( $value ) ? $value : false;
				if ( $value === false ) {
					return false;
				}

				return $value >= $condition_value;
			case self::OPERATOR_LESS_THAN:
				$value = is_numeric( $value ) ? $value : false;
				if ( $value === false ) {
					return false;
				}

				return $value < $condition_value;
			case self::OPERATOR_LESS_THAN_EQUALS:
				$value = is_numeric( $value ) ? $value : false;
				if ( $value === false ) {
					return false;
				}

				return $value <= $condition_value;
			case self::OPERATOR_REGEX:
				$regex_pattern = $condition_value;
				if ( ! preg_match( '/^\/.*\/[imsxuADU]*$/', $condition_value ) ) {
					$regex_pattern = '/' . $condition_value . '/i';
				}
				return preg_match( $regex_pattern, $value );
			default:
				//Default is self::OPERATOR_HAS_VALUE
				return ! empty( $value );
		}
	}

	/**
	 * Gets the supported operators.
	 *
	 * @return array<string> The supported operators.
	 */
	public function get_operators(): array {
		return [
			self::OPERATOR_HAS_VALUE           => __( 'Has Any Value', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_EQUALS              => __( 'Equals', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_NOT_EQUALS          => __( 'Not Equals', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_EMPTY               => __( 'Is Empty', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_CONTAINS            => __( 'Contains', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_NOT_CONTAINS        => __( 'Not Contains', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_GREATER_THAN        => __( 'Greater Than', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_GREATER_THAN_EQUALS => __( 'Greater Than or Equals', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_LESS_THAN           => __( 'Less Than', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_LESS_THAN_EQUALS    => __( 'Less Than or Equals', 'multiple-pages-generator-by-porthas' ),
			self::OPERATOR_REGEX               => __( 'Matches Regular Expression', 'multiple-pages-generator-by-porthas' ),
		];
	}

	/**
	 * Parses a where condition string into an associative array.
	 *
	 * @param string $condition The condition string to parse.
	 *
	 * @return array{column: string, value: string, operator: string} The parsed condition as an associative array with the following keys:
	 *                          - 'column' (string): The column to evaluate.
	 *                          - 'value' (mixed): The value to compare against.
	 *                          - 'operator' (string): The operator to use for comparison.
	 */
	function parse_where( string $condition ): array {
		// Define the supported operators
		$operators = array( '!=', '>=', '<=', '=', '>', '<' );

		// Match the condition with attribute, operator, and value using regex
		foreach ( $operators as $operator ) {
			$pattern = '/\s*(\w+)\s*' . preg_quote( $operator, '/' ) . '\s*([\w\s\'"$^}{]*)\s*/'; // Regex to extract attribute, operator, and value
			if ( preg_match( $pattern, $condition, $matches ) ) {
				$attribute = trim( $matches[1] ); // Extract attribute
				$value     = $this->normalize_condition_part( $matches[2] ); // Extract value, stripping quotes

				// Check the operator and evaluate the condition
				switch ( $operator ) {
					case '=':
						if ( str_starts_with( $value, '^' ) || str_ends_with( $value, '$' ) ) {
							$operator = self::OPERATOR_REGEX;
							break;
						}
						if($value === ''){
							$operator = self::OPERATOR_EMPTY;
							break;
						}
						$operator = self::OPERATOR_CONTAINS; // This is to match the previous behavior of the plugin
						break;
					case '!=':
						if ( $value === '' ) {
							$operator = self::OPERATOR_HAS_VALUE;
							break;
						}
						$operator = self::OPERATOR_NOT_EQUALS;
						break;
					case '>':
						$operator = self::OPERATOR_GREATER_THAN;
						break;
					case '>=':
						$operator = self::OPERATOR_GREATER_THAN_EQUALS;
						break;
					case '<':
						$operator = self::OPERATOR_LESS_THAN;
						break;
					case '<=':
						$operator = self::OPERATOR_LESS_THAN_EQUALS;
						break;
					default:
						return [];
				}
				return [ 'column' => $attribute, 'value' => $value, 'operator' => $operator ];
			}
		}

		return []; // Return empty if the condition is invalid or not matched
	}

	/**
	 * Extracts where conditions from a string.
	 *
	 * @param string $where The where conditions string.
	 *
	 * @return array<array{column: string, value: string, operator: string}> The extracted conditions as an array of associative arrays with the following keys:
	 *                          - 'column' (string): The column to evaluate.
	 *                          - 'value' (mixed): The value to compare against.
	 *                          - 'operator' (string): The operator to use for comparison.
	 */
	public function extract_where_conditions( string $where ): array {
		$conditions_where = array_filter( explode( ';', $where ) );
		$conditions       = [];
		// Evaluate each condition
		foreach ( $conditions_where as $condition ) {
			$conditions_array = $this->parse_where( $condition );
			if ( empty( $conditions_array ) ) {
				continue;
			}
			$conditions[] = $conditions_array;
		}

		return $conditions;
	}

	/**
	 * Extracts numbered conditions from the provided attributes.
	 *
	 * This function processes the attributes array to extract conditions
	 * based on numbered keys (e.g., 'column1', 'value1', 'operator1', etc.).
	 * It supports up to 5 numbered conditions.
	 *
	 * @param array $atts The attributes array containing conditions.
	 *                    Expected keys:
	 *                    - 'column': The column name for the first condition.
	 *                    - 'value': The value for the first condition.
	 *                    - 'operator': The operator for the first condition.
	 *                    - 'column1', 'value1', 'operator1': The column, value, and operator for the first numbered condition.
	 *                    - 'column2', 'value2', 'operator2': The column, value, and operator for the second numbered condition.
	 *                    - 'column3', 'value3', 'operator3': The column, value, and operator for the third numbered condition.
	 *                    - 'column4', 'value4', 'operator4': The column, value, and operator for the fourth numbered condition.
	 *                    - 'column5', 'value5', 'operator5': The column, value, and operator for the fifth numbered condition.
	 *
	 * @return array An array of conditions, where each condition is an associative array with the following keys:
	 *               - 'column' (string): The column name.
	 *               - 'value' (string): The value to compare.
	 *               - 'operator' (string): The operator to use for comparison.
	 */
	public function extract_numbered_conditions( array $atts ): array {
		$conditions = [];
		if ( ! empty( $atts['column'] ) ) {
			$conditions[] = array(
				'column'   => $this->normalize_condition_part( $atts['column'] ),
				'value'    => $this->normalize_condition_part( $atts['value'] ),
				'operator' => $this->normalize_condition_part( $atts['operator'] )
			);
		}
		for ( $i = 1; $i <= 5; $i ++ ) {
			if ( empty( $atts[ 'column' . $i ] ) ) {
				continue;
			}
			$conditions[] = array(
				'column'   => $this->normalize_condition_part( $atts[ 'column' . $i ] ),
				'value'    => $this->normalize_condition_part( $atts[ 'value' . $i ] ),
				'operator' => $this->normalize_condition_part( $atts[ 'operator' . $i ] ),
			);
		}

		return $conditions;
	}

	/**
	 * Translates conditions by replacing placeholders with actual values from the current project data row.
	 *
	 * This function processes an array of conditions, checking if the value of each condition is a placeholder
	 * (e.g., '{{column_name}}'). If a placeholder is found, it replaces it with the corresponding value from the
	 * current project's data row.
	 *
	 * @param array $conditions The array of conditions to translate. Each condition is expected to be an associative array
	 *                          with at least a 'value' key.
	 *
	 * @return array The translated conditions with placeholders replaced by actual values.
	 */
	public function translate_conditions( $conditions ) {
		$this->conditions_used_context_tag = false;
		// Dataset-column tags need a current project + row (loop rendered inside a generated MPG page);
		// context tags like {{mpg_current_title}} don't — they resolve against the queried WP page, so
		// they must still work when there is no current project (e.g. loop on a normal single post). See #530.
		$current_project = \MPG_ProjectModel::get_current_project_id();
		$data_row        = null;
		$headers         = null;
		if ( ! empty( $current_project ) ) {
			$data_row = \MPG_CoreModel::get_current_datarow( $current_project );
			// Dataset-column tags can only resolve when a row is available; otherwise leave them
			// untouched (get_current_datarow() returns false when no row matches the request).
			if ( is_array( $data_row ) && ! empty( $data_row ) ) {
				$project_data = \MPG_ProjectModel::get_project_by_id( $current_project );
				$headers      = \MPG_ProjectModel::get_headers_from_project( $project_data );
			}
		}

		foreach ( $conditions as $index => $condition ) {
			if ( ! isset( $condition['value'] ) || empty( $condition['value'] ) ) {
				continue;
			}
			// PREG_PATTERN_ORDER so $matches[0] holds every tag in the value, not only the first one.
			preg_match_all('/{{mpg_\S+?}}/m', $condition['value'], $matches, PREG_PATTERN_ORDER, 0);

			foreach ( $matches[0] as $match ) {
				if ( null !== $headers ) {
					$column_index = \MPG_ProjectModel::headers_have_column( $headers, $match );
					if ( $column_index !== false ) {
						$conditions[ $index ]['value'] = str_replace( $match, $data_row[ $column_index ], $conditions[ $index ]['value'] );
						continue;
					}
				}
				// Not a dataset column: resolve current-page context tags (e.g. {{mpg_current_title}})
				// or a developer-supplied value. Unknown tags are left untouched. See issue #530.
				$resolved = $this->resolve_current_context_tag( $match );
				if ( null !== $resolved ) {
					$conditions[ $index ]['value'] = str_replace( $match, $resolved, $conditions[ $index ]['value'] );
					$this->conditions_used_context_tag = true;
				}
			}
		}
		return $conditions;
	}

	/**
	 * Resolve a filter tag against the current page context (issue #530).
	 *
	 * Built-in: {{mpg_current_title}} -> the title of the page the loop is rendered on. Any tag can
	 * be resolved by developers via the `mpg/{tag}` filter, e.g. add_filter( 'mpg/mpg_current_author', ... ).
	 *
	 * @param string $tag The full tag, e.g. "{{mpg_current_title}}".
	 * @return string|null The resolved value, or null to leave the tag untouched.
	 */
	protected function resolve_current_context_tag( string $tag ) {
		$name  = trim( $tag, '{}' ); // e.g. "mpg_current_title".
		$value = null;

		if ( 'mpg_current_title' === $name ) {
			$queried_object = get_queried_object();
			// Generated MPG pages reuse the real template ID but carry their replaced title on the
			// request's queried object. Reloading by ID would return the raw {{mpg_*}} template title
			// from the post cache. The raw post_title is used instead of get_the_title() because the
			// display filters texturize punctuation ("-" becomes &#8211;), which would never match the
			// raw spreadsheet values these conditions are compared against (#734).
			$value = $queried_object instanceof \WP_Post ? $queried_object->post_title : '';
		}

		/**
		 * Resolve a loop-filter tag to a value from the current page context.
		 *
		 * Return null to leave the tag untouched.
		 *
		 * @param string|null $value The resolved value, or null when unknown.
		 * @param string      $tag   The full tag, e.g. "{{mpg_current_title}}".
		 */
		return apply_filters( 'mpg/' . $name, $value, $tag );
	}

	/**
	 * Whether the last translate_conditions() call resolved a current-page context tag.
	 *
	 * @return bool True when a tag such as {{mpg_current_title}} was substituted.
	 */
	public function conditions_used_context_tag(): bool {
		return $this->conditions_used_context_tag;
	}

	/**
	 * Evaluates a row of data against a set of conditions.
	 *
	 * This function checks if a row of data meets the specified conditions based on the provided logic.
	 * It supports both AND and OR logic for evaluating the conditions.
	 *
	 * @param array<array{column_index: int, column: string, value: string, operator: string}> $conditions The conditions to evaluate. Each condition should be an associative array with the following keys:
	 *                          - 'column_index' (int): The index of the column to evaluate.
	 *                          - 'column' (string): The column name.
	 *                          - 'value' (mixed): The value to compare against.
	 *                          - 'operator' (string): The operator to use for comparison.
	 * @param string $logic The logic to use for evaluation. Can be 'all' (AND) or 'any' (OR).
	 * @param array $headers The headers of the project.
	 * @param int|null $project_id Optional. The ID of the project. If not provided, the current project ID will be used.
	 * @param array $row Optional. The row of data to evaluate. If not provided, the current data row will be used.
	 *
	 * @return bool True if the row meets the conditions based on the specified logic, false otherwise.
	 * @throws \Exception
	 */
	public function evaluate_row_for_conditions( array $conditions, string $logic, array $headers, array $row = [], ?int $project_id = null ): bool {

		$current_project_id = (int) empty( $project_id ) ? \MPG_ProjectModel::get_current_project_id() : $project_id;
		$current_row        = empty( $row ) ? \MPG_CoreModel::get_current_datarow( $current_project_id ) : $row;

		//We need to check if the columns exist in the project headers.
		foreach ( $conditions as $condition_index => $condition ) {
			$column       = $condition['column'];
			$column_index = \MPG_ProjectModel::headers_have_column( $headers, $column );
			if ( $column_index === false ) {
				// translators: %1$s: the name of the colum, %2$s: the name of the project.
				throw new \Exception( sprintf( __( 'Column: %1$s is not found in project #%2$s.', 'multiple-pages-generator-by-porthas' ), $condition['column'], $current_project_id ) );
			}
			$conditions[ $condition_index ]['column_index'] = $column_index;
		}
		$conditions_met = 0;
		foreach ( $conditions as $condition ) {
			$column_index = $condition['column_index'] ?? false;
			if ( $column_index === false ) {
				throw new \Exception( __( 'No column index provided to compare data for. Please ensure that the condition array includes a valid column index.', 'multiple-pages-generator-by-porthas' ) );
			}
			$value         = $current_row[ $column_index ] ?? '';
			$condition_met = $this->evaluate_condition( $value, $condition['value'] ?? '', $condition['operator'] ?? self::OPERATOR_HAS_VALUE );

			if ( $condition_met ) {
				//For OR logic we just need one condition to be met.
				if ( $logic === self::LOGIC_OR ) {
					return true;
				}
				$conditions_met ++;
			}

		}
		if ( $logic === self::LOGIC_AND && $conditions_met === count( $conditions ) ) {
			return true;
		}

		return false;

	}
}
