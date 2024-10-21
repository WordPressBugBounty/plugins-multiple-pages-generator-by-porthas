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
class Block extends Core {

	/**
	 * Registers the shortcode.
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] ); 
		add_action( 'wp_loaded', array( $this, 'add_attributes_to_blocks' ), 999 );
		add_filter( 'render_block', array( $this, 'render_blocks' ), 999, 2 );
	}

	/**
	 * Renders the block content based on the conditions.
	 *
	 * @param string $block_content The block content.
	 * @param array $block The block.
	 *
	 * @return string The block content.
	 */
	public function render_blocks( $block_content, $block ): string {
		if ( ! isset( $block['attrs']['mpgConditions'] ) ) {
			return $block_content;
		}
		$conditions = $block['attrs']['mpgConditions'];
		if ( empty( $conditions['conditions'] ) ) {
			return $block_content;
		}
		try {
			if ( $this->should_render( $conditions['conditions'], $conditions['logic'] ) ) {
				return $block_content;
			}

			return '';
		} catch ( \Exception $e ) {
			if ( is_user_logged_in() ) {
				return esc_html( $e->getMessage() );
			}
		}

		return $block_content;
	}

	/**
	 * Adds the mpgConditions attribute to the blocks.
	 */
	public function add_attributes_to_blocks() {
		$registered_blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();

		foreach ( $registered_blocks as $name => $block ) {
			$block->attributes['mpgConditions'] = array(
				'type'       => 'object',
				'default'    => array( 'logic' => self::LOGIC_AND, 'conditions' => array() ),
				'properties' => array(
					'logic'      => array(
						'type'    => 'string',
						'default' => self::LOGIC_AND,
						'enum'    => array( self::LOGIC_AND, self::LOGIC_OR ),
					),
					'conditions' => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type'       => 'object',
							'properties' => array(
								'column'   => array(
									'type' => 'string',
								),
								'operator' => array(
									'type' => 'string',
									'enum' => array_keys( $this->get_operators() ),
								),
								'value'    => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			);
		}
	}

	/**
	 * Enqueues the block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		$dependencies = require_once MPG_MAIN_DIR . '/blocks/build/conditional/index.asset.php';
		wp_enqueue_script( 'mpg-conditional-block', MPG_MAIN_URL . '/blocks/build/conditional/index.js', $dependencies['dependencies'], $dependencies['version'], true );
		wp_enqueue_style( 'mpg-conditional-block', MPG_MAIN_URL . '/blocks/build/conditional/index.css', array(), $dependencies['version'] );
		wp_style_add_data( 'mpg-conditional-block', 'rtl', 'replace' );
		$compare_operators = $this->get_operators();
		unset( $compare_operators[ self::OPERATOR_HAS_VALUE ] );
		unset( $compare_operators[ self::OPERATOR_EMPTY ] );
		wp_localize_script( 'mpg-conditional-block', 'mpgCondData', [
			'operators'        => $this->get_operators(),
			'compareOperators' => $compare_operators
		] );
	}

}