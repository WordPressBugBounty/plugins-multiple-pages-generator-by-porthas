<?php

namespace MPG\Display\Match;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode extends Core {
	public function register() {
		add_shortcode( 'mpg_match', [ $this, 'shortcode' ] );
	}

	/**
	 * Match shortcode is just a shorter version for loop.
	 *
	 * @param array $atts
	 * @param string|null $content
	 *
	 * @return string
	 */
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

			'where'       => '',

			'current-project-id'  => '',
			'search-in-project-id'  => '',
			'current-header'  => '',
			'match-with'  => '',

			'limit'       => '',
			'unique-rows' => 'no',
			'order-by'    => '',
			'direction'   => '',
			'base-url'    => ''
		), array_change_key_case( (array) $atts ) );


		$atts['search-in-project-id']     = $this->normalize_condition_part( $atts['search-in-project-id'] );
		$atts['current-header']     = $this->normalize_condition_part( $atts['current-header'] );
		$atts['match-with']     = $this->normalize_condition_part( $atts['match-with'] );

		//Get project if we are a single virtual page context, to set back the project id to be used afterwards.
		$current_project_backup = \MPG_ProjectModel::get_current_project_id();
		$atts['limit']          = $this->normalize_condition_part( $atts['limit'] );
		$atts['order-by']       = $this->normalize_condition_part( $atts['order-by'] );
		$atts['direction']      = $this->normalize_condition_part( $atts['direction'] );
		$atts['base-url']       = $this->normalize_condition_part( $atts['base-url'] );
		$atts['where']          = $this->normalize_condition_part( $atts['where'] );

		if( ! str_starts_with($atts['match-with'],'{{')){
			$atts['match-with'] = '{{'.$atts['match-with'];
		}
		if ( ! str_ends_with( $atts['match-with'], '}}' ) ) {
			$atts['match-with'] = $atts['match-with'] . '}}';
		}
		if ( ! empty( $atts['limit'] ) ) {
			$atts['limit'] = (int) $atts['limit'] > 0 ? (int) $atts['limit'] : '';
		}
		$atts['unique-rows']      = $atts['unique-rows'] === 'yes';
		$conditions               = array(
			'conditions' => array(
				[ //we add the first condition.
					'column'   => $atts['current-header'],
					'value'    => $atts['match-with'],
					'operator' => self::OPERATOR_CONTAINS
				]
			),
			'logic'      => self::LOGIC_AND
		);

		$conditions['conditions'] = array_merge($conditions['conditions'], $this->extract_where_conditions( $atts['where'] ));

		$conditions['conditions'] = array_merge( $conditions['conditions'], $this->extract_numbered_conditions( $atts ) );
		try {
			$content = $this->render( $atts['search-in-project-id'], [
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
			\MPG_LogsController::mpg_write( $atts['project-id'], 'error', sprintf( 'Exception in [mpg_match] shortcode: %s %s', $e->getMessage(), var_export( $atts, true ) ), __FILE__, __LINE__ );
			\MPG_ProjectModel::set_current_project_id( $current_project_backup );

			return is_user_logged_in() ? $e->getMessage() : '';
		}
	}
}