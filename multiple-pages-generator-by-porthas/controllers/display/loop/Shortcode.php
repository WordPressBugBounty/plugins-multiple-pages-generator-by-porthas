<?php

namespace MPG\Display\Loop;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode extends Core {
	public function register() {
		add_shortcode( 'mpg', [ $this, 'shortcode' ] );
	}

	public function shortcode( array $atts, string $content = null ): string {

		$atts = shortcode_atts( array(
			'column1'     => '',
			'value1'      => '',
			'operator1'   => self::OPERATOR_HAS_VALUE,
			'column2'     => '',
			'value2'      => '',
			'operator2'   => self::OPERATOR_HAS_VALUE,
			'column3'     => '',
			'value3'      => '',
			'operator3'   => self::OPERATOR_HAS_VALUE,
			'column4'     => '',
			'value4'      => '',
			'operator4'   => self::OPERATOR_HAS_VALUE,
			'column5'     => '',
			'value5'      => '',
			'operator5'   => self::OPERATOR_HAS_VALUE,
			'limit'       => '',
			'unique-rows' => 'no',
			'logic'       => '',
			'project-id'  => '',
			'where'       => '',
			'operator'    => 'or',
			'order-by'    => '',
			'direction'   => '',
			'base-url'    => ''
		), array_change_key_case( (array) $atts ) );
		//Get project if we are a single virtual page context, to set back the project id to be used afterwards.
		$current_project_backup = \MPG_ProjectModel::get_current_project_id();
		$atts['limit']          = $this->normalize_condition_part( $atts['limit'] );
		$atts['project-id']     = $this->normalize_condition_part( $atts['project-id'] );
		$atts['logic']          = $this->normalize_condition_part( $atts['logic'] );
		$atts['order-by']       = $this->normalize_condition_part( $atts['order-by'] );
		$atts['direction']      = $this->normalize_condition_part( $atts['direction'] );
		$atts['base-url']       = $this->normalize_condition_part( $atts['base-url'] );
		$atts['where']          = $this->normalize_condition_part( $atts['where'] );

		if ( ! empty( $atts['limit'] ) ) {
			$atts['limit'] = (int) $atts['limit'] > 0 ? (int) $atts['limit'] : '';
		}
		$atts['unique-rows']      = $atts['unique-rows'] === 'yes';
		$atts['operator']         = $atts['operator'] === 'or' ? self::LOGIC_OR : self::LOGIC_AND;
		$conditions               = array(
			'conditions' => array(),
			'logic'      => empty( $atts['logic'] ) ? $atts['operator'] : $atts['logic']
		);
		$conditions['conditions'] = $this->extract_where_conditions( $atts['where'] );

		$conditions['conditions'] = array_merge( $conditions['conditions'], $this->extract_numbered_conditions( $atts ) );
		try {
			$content = $this->render( $atts['project-id'], [
				'limit'       => $atts['limit'],
				'unique_rows' => $atts['unique-rows'],
				'conditions'  => $conditions,
				'order_by'    => $atts['order-by'],
				'direction'   => $atts['direction'],
				'base_url'    => $atts['base-url']
			], $content );
			\MPG_ProjectModel::set_current_project_id( $current_project_backup );

			return $content;
		} catch ( \Exception $e ) {
			\MPG_LogsController::mpg_write( $atts['project-id'], 'error', sprintf( 'Exception in [mpg] shortcode: %s %s', $e->getMessage(), var_export( $atts, true ) ), __FILE__, __LINE__ );
			\MPG_ProjectModel::set_current_project_id( $current_project_backup );

			return is_user_logged_in() ? $e->getMessage() : '';
		}
	}
}