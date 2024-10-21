<?php
namespace MPG\Display\Conditional;

use MPG\Display\Base_Display;
use MPG\Display\DisplayInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class Core
 *
 * @package MPG\Display\Conditional
 */
abstract class Core extends Base_Display {

	/**
	 * Renders the output based on the provided conditions and logic.
	 *
	 * @param array<int, array{column: string, value: string, operator: string}> $conditions Array of conditions to evaluate. Each condition is an associative array with the following keys:
	 *                          - 'column' (string): The column to evaluate.
	 *                          - 'value' (mixed): The value to compare against.
	 *                          - 'operator' (string): The operator to use for comparison.
	 * @param string $logic The logic to apply between conditions.
	 *
	 * @return bool If the output should be sent back.
	 * @throws \Exception
	 */
	public function should_render( array $conditions, string $logic ): bool {
		if ( empty( $conditions ) || ! isset( $conditions[0] ) || ! is_array( $conditions[0] ) || ! isset( $conditions[0]['column'] ) || empty( $conditions[0]['column'] ) ) {
			throw new \Exception( __( 'No conditions defined.', 'mpg' ) );
		}
		if($logic !== self::LOGIC_AND && $logic !== self::LOGIC_OR){
			throw new \Exception( __( 'Invalid logic value provided.', 'mpg' ) );
		}

		if ( empty( \MPG_ProjectModel::get_current_project_id() ) ) {
			throw new \Exception( __( 'No MPG project found to check data for.', 'mpg' ) );
		}
		$current_project_id = (int)\MPG_ProjectModel::get_current_project_id();
		$project_data = \MPG_ProjectModel::get_project_by_id( $current_project_id );
		$headers = \MPG_ProjectModel::get_headers_from_project( $project_data );

		return $this->evaluate_row_for_conditions( $conditions, $logic, $headers );
	}
}