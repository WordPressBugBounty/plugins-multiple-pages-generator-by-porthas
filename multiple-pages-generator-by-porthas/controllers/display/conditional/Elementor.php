<?php

namespace MPG\Display\Conditional;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Inline
 *
 * @package MPG\Display\Conditional
 */
class Elementor extends Core {

	/**
	 * Registers the shortcode.
	 */
	public function register() {

		add_filter( 'elementor/widget/render_content', [ $this, 'prevent_widget_render_based' ], 99, 2 );
		if ( \MPG_Helper::is_edited_post_a_template() ) {
			add_action( 'elementor/element/after_section_end', [ $this, 'add_custom_advanced_section' ], 10, 3 );
		}
	}

	/**
	 * Wrap the widget content with conditions shortcode.
	 * We need to do this since Elementor render the widget content on save and it displays the static version on frontend.
	 *
	 *
	 * @param string $content The widget content.
	 * @param \Elementor\Widget_Base $widget The widget instance.
	 *
	 * @return string The widget content or the shortcode to wrap the widget content.
	 */
	public function prevent_widget_render_based( $content, $widget ) {

		// We're in the editor, so do not alter the content.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return $content;
		}
		$settings = $widget->get_settings_for_display();
		if ( empty( $settings['mpgc_conditions'] ) ) {
			return $content;
		}

		$conditions = array( 'conditions' => array(), 'logic' => $settings['mpgc_logic_show'] ?? self::LOGIC_AND );

		foreach ( $settings['mpgc_conditions'] as $econdition ) {
			if ( empty( $econdition['mpgc_column_name'] ) ) {
				continue;
			}
			$conditions['conditions'][] = array(
				'column'   => $this->normalize_condition_part( $econdition['mpgc_column_name'] ),
				'value'    => $this->normalize_condition_part( $econdition['mpgc_value_compare'] ),
				'operator' => $this->normalize_condition_part( $econdition['mpgc_operator'] ),
			);
		}
		if ( empty( $conditions['conditions'] ) ) {
			return $content;
		}
		//If we have conditions, we need to wrap them in the if shortcode.
		$shortcode = '';
		foreach ( $conditions['conditions'] as $index => $condition ) {
			$shortcode .= sprintf( ' column%1$d="%2$s" value%1$d="%3$s" operator%1$d="%4$s" ', $index+1, $condition['column'], $condition['value'], $condition['operator'] );
		}
		$shortcode .= ' logic="' . $conditions['logic'] . '"';

		return '[mpg-if ' . $shortcode . ']' . $content . '[/mpg-if]';
	}
	/**
	 * Adds a custom section to the Advanced tab in the Elementor widget settings.
	 *
	 * @param \Elementor\Widget_Base $element The widget instance.
	 * @param string $section_id The section ID.
	 * @param array $args The arguments.
	 */
	function add_custom_advanced_section( $element, $section_id, $args ) {

		// Check if we are in the 'advanced' section
		if ( '_section_style' !== $section_id ) {
			return;
		}

		// Start creating a new section under the Advanced tab
		$element->start_controls_section(
			'mpg_visibility_section', // Section ID
			[
				'label' => __( 'MPG Visibility Conditions', 'mpg' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED, // Add to Advanced tab
			]
		);


		$element->add_control(
			'mpgc_logic_show', [
				'label'       => __( 'Show if', 'mpg' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'description' => __( 'Select the logic to apply to the conditions', 'mpg' ),
				'options'     => [
					self::LOGIC_AND => __( 'All', 'mpg' ),
					self::LOGIC_OR  => __( 'Any', 'mpg' )
				],
				'default'     => self::LOGIC_AND,
			]
		);
		// Add a repeater control
		$repeater = new \Elementor\Repeater();

		// First input area in the repeater (Text Input 1)
		$repeater->add_control(
			'mpgc_column_name', [
				'ai'          => [
					'active' => false,
				],
				'label'       => __( 'Column name', 'mpg' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Column name to compare', 'mpg' ),
				'label_block' => true,
			]
		);

		// Select dropdown in the repeater
		$repeater->add_control(
			'mpgc_operator', [

				'label'   => __( 'Condition', 'mpg' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->get_operators(),
				'default' => self::OPERATOR_HAS_VALUE,
			]
		);
		$operators = $this->get_operators();
		unset( $operators[ self::OPERATOR_HAS_VALUE ] );
		unset( $operators[ self::OPERATOR_EMPTY ] );
		// Second input area in the repeater (Text Input 2)
		$repeater->add_control(
			'mpgc_value_compare', [
				'ai'          => [
					'active' => false,
				],
				'condition'   => [
					'mpgc_operator' => array_keys( $operators ),
				],
				'label'       => __( 'Value', 'mpg' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Value to compare with', 'mpg' ),
				'label_block' => true,
			]
		);


		// Add the repeater to the controls
		$element->add_control(
			'mpgc_conditions',
			[
				'label'       => __( 'Conditions', 'mpg' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'max_items'   => 5,
				'button_text' => esc_html__( 'Add Condition', 'mpg' ),
				'placeholder' => __( 'Add condition', 'mpg' ),
				'default'     => [],
				'title_field' => '{{{ mpgc_column_name }}} ',
			]
		);

		// End the custom section
		$element->end_controls_section();
	}
}