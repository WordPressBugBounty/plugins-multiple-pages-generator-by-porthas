<?php

namespace MPG\Display\Conditional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcode
 *
 * @package MPG\Display\Conditional
 */
class Shortcode extends Core {

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		add_shortcode( 'mpg-if', array( $this, 'shortcode' ) );
	}

	/**
	 * Handles the shortcode [mpg-if].
	 *
	 * @param array $atts Shortcode attributes.
	 * @param string|null $content Shortcode content.
	 *
	 * @return string Rendered output.
	 */
	public function shortcode( array $atts, string $content = null ): string {
		$atts = shortcode_atts( array(
			'column'    => '',
			'value'     => '',
			'operator'  => self::OPERATOR_HAS_VALUE,
			'column1'   => '',
			'value1'    => '',
			'operator1' => self::OPERATOR_HAS_VALUE,
			'column2'   => '',
			'value2'    => '',
			'operator2' => self::OPERATOR_HAS_VALUE,
			'column3'   => '',
			'value3'    => '',
			'operator3' => self::OPERATOR_HAS_VALUE,
			'column4'   => '',
			'value4'    => '',
			'operator4' => self::OPERATOR_HAS_VALUE,
			'column5'   => '',
			'value5'    => '',
			'operator5' => self::OPERATOR_HAS_VALUE,
			'logic'     => 'all',
			'where'     => ''
		), array_change_key_case( (array) $atts ) );
		$conditions = array( 'conditions' => array(), 'logic' => $atts['logic'] );

		if ( ! empty( $atts['column'] ) ) {
			$conditions['conditions'][] = array(
				'column'   => $this->normalize_condition_part( $atts['column'] ),
				'value'    => $this->normalize_condition_part( $atts['value'] ),
				'operator' => $this->normalize_condition_part( $atts['operator'] )
			);
		}
		for ( $i = 1; $i <= 5; $i ++ ) {
			if ( empty( $atts[ 'column' . $i ] ) ) {
				continue;
			}
			$conditions['conditions'][] = array(
				'column'   => $this->normalize_condition_part( $atts[ 'column' . $i ] ),
				'value'    => $this->normalize_condition_part( $atts[ 'value' . $i ] ),
				'operator' => $this->normalize_condition_part( $atts[ 'operator' . $i ] ),
			);
		}
		if ( ! empty( $atts['where'] ) ) {
			$conditions['conditions'] = array_merge( $conditions['conditions'], $this->extract_where_conditions( $atts['where'] ) );
		}

		try {
			if ( $this->should_render( $conditions['conditions'], $conditions['logic'] ) ) {
				return do_shortcode( $content );
			}

			return '';
		} catch ( \Exception $e ) {
			return is_user_logged_in() ? esc_html( $e->getMessage() ) : '';
		}
	}
}