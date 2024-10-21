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
				return strpos( $value, $condition_value ) !== false;
			case self::OPERATOR_NOT_CONTAINS:
				return strpos( $value, $condition_value ) === false;
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
				if ( ! str_starts_with( $condition_value, '/' ) ) {
					$regex_pattern = '/' . $regex_pattern;
				}
				if ( ! str_ends_with( $condition_value, '/' ) ) {
					$regex_pattern = $regex_pattern . '/';
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
			self::OPERATOR_HAS_VALUE           => __( 'Has Any Value', 'mpg' ),
			self::OPERATOR_EQUALS              => __( 'Equals', 'mpg' ),
			self::OPERATOR_NOT_EQUALS          => __( 'Not Equals', 'mpg' ),
			self::OPERATOR_EMPTY               => __( 'Is Empty', 'mpg' ),
			self::OPERATOR_CONTAINS            => __( 'Contains', 'mpg' ),
			self::OPERATOR_NOT_CONTAINS        => __( 'Not Contains', 'mpg' ),
			self::OPERATOR_GREATER_THAN        => __( 'Greater Than', 'mpg' ),
			self::OPERATOR_GREATER_THAN_EQUALS => __( 'Greater Than or Equals', 'mpg' ),
			self::OPERATOR_LESS_THAN           => __( 'Less Than', 'mpg' ),
			self::OPERATOR_LESS_THAN_EQUALS    => __( 'Less Than or Equals', 'mpg' ),
			self::OPERATOR_REGEX               => __( 'Matches Regular Expression', 'mpg' ),
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
		$current_project = \MPG_ProjectModel::get_current_project_id();
		if ( empty( $current_project ) ) {
			return $conditions;
		}
		$data_row     = \MPG_CoreModel::get_current_datarow( $current_project );
		$project_data = \MPG_ProjectModel::get_project_by_id( $current_project );
		$headers      = \MPG_ProjectModel::get_headers_from_project( $project_data );

		foreach ( $conditions as $index => $condition ) {
			if ( ! isset( $condition['value'] ) || empty( $condition['value'] ) ) {
				continue;
			}
			if ( str_starts_with( $condition['value'], '{{' ) && str_ends_with( $condition['value'], '}}' ) ) {

				$column_index = \MPG_ProjectModel::headers_have_column( $headers, $condition['value'] );

				if ( $column_index === false ) {
					continue;
				}
				$conditions[ $index ]['value'] = $data_row[ $column_index ];
			}
		}

		return $conditions;
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
	public function evaluate_row_for_conditions( array $conditions, string $logic, array $headers, array $row = [], int $project_id = null ): bool {

		$current_project_id = (int) empty( $project_id ) ? \MPG_ProjectModel::get_current_project_id() : $project_id;
		$current_row        = empty( $row ) ? \MPG_CoreModel::get_current_datarow( $current_project_id ) : $row;

		//We need to check if the columns exist in the project headers.
		foreach ( $conditions as $condition_index => $condition ) {
			$column       = $condition['column'];
			$column_index = \MPG_ProjectModel::headers_have_column( $headers, $column );
			if ( $column_index === false ) {
				throw new \Exception( sprintf( __( 'Column: %s is not found in project #%s.', 'mpg' ), $condition['column'], $current_project_id ) );
			}
			$conditions[ $condition_index ]['column_index'] = $column_index;
		}
		$conditions_met = 0;
		foreach ( $conditions as $condition ) {
			$column_index = $condition['column_index'] ?? false;
			if ( $column_index === false ) {
				throw new \Exception( __( 'No column index provided to compare data for. Please ensure that the condition array includes a valid column index.', 'mpg' ) );
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