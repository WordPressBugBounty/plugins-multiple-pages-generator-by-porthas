<?php
namespace MPG\Display\Loop;

use MPG\Display\Loop\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class Block
 *
 * @package MPG\Display\Core
 */
class Block extends Core {

	/**
	 * Registers the shortcode.
	 */
	public function register(){
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block(){
		register_block_type(
			MPG_MAIN_DIR . '/blocks/build/loop',
			array(
				'render_callback' => [$this,'render_block'],
			)
		);
		$projects = \MPG_ProjectModel::get_projects();
        $headers_by_project = [];
		foreach ( $projects as $project ) {
			try {
				$headers_by_project[ $project->id ] = \MPG_ProjectModel::get_headers_from_project( $project );
			} catch ( \Exception $e ) {
				\MPG_LogsController::mpg_write( $project->id, 'error', sprintf( 'Headers are empty: %s %s', $e->getMessage(), var_export( $project, true ) ), __FILE__, __LINE__ );
			}
		}
		$compare_operators = $this->get_operators();
		unset( $compare_operators[ self::OPERATOR_HAS_VALUE ] );
		unset( $compare_operators[ self::OPERATOR_EMPTY ] );
		wp_localize_script(
			'mpg-loop-editor-script',
			'mpgLoop',
			array(
				'operators'        => $this->get_operators(),
				'compareOperators' => $compare_operators,
				'projects' => wp_list_pluck($projects, 'name', 'id'),
				'orders' => $this->get_order(),
				'projectHeaders' => $headers_by_project,
			)
		);
		add_action( 'enqueue_block_editor_assets', function () {

			wp_enqueue_style( 'mpg-loop-editor-styles', MPG_MAIN_URL . '/blocks/build/loop/index.css', array(), MPG_PLUGIN_VERSION );
			wp_style_add_data( 'mpg-loop-editor-styles', 'rtl', 'replace' );
		} );

	}
	/**
	 * Renders the block content.
	 *
	 * @param array $attributes The block attributes.
	 * @param string $content The block content.
	 * @param array $block The block data.
	 *
	 * @return string The rendered block content.
	 */
	public function render_block($attributes,	$content, $block){

		$content = empty( $content ) ? ( $attributes['innerBlocksContent'] ?? '' ) : $content;

		$current_project_backup = \MPG_ProjectModel::get_current_project_id();

		if ( ! empty( $attributes['limit'] ) ) {
			$attributes['limit'] = (int) $attributes['limit'] > 0 ? (int) $attributes['limit']  : '';
		}
		try{
			$content = $this->render( $attributes['project_id'], [
				'limit'       => $attributes['limit'],
				'unique_rows' => (bool) $attributes['uniqueRows'],
				'conditions'  => $attributes['conditions'],
				'order_by'    => $attributes['orderBy'],
				'direction'   => $attributes['direction']
			], $content );
			\MPG_ProjectModel::set_current_project_id( $current_project_backup );
			return $content;
		} catch (\Exception $e) {
			\MPG_LogsController::mpg_write($attributes['project_id'], 'error', sprintf( 'Exception in MPG Loop Block: %s %s', $e->getMessage(), var_export($attributes,true) ), __FILE__, __LINE__);
			\MPG_ProjectModel::set_current_project_id( $current_project_backup );

			return is_user_logged_in() ? $e->getMessage() : '';
		}
	}

}