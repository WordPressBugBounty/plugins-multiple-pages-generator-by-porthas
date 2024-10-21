<?php
namespace MPG\Display;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
//TODO we need to transform this into an autoloader at some point.

$mpg_available_displays = [
	'conditional' => [
		'shortcode',
		'elementor',
		'block',
		'inline'
	],
	'loop' => [
		'shortcode',
		'inline',
		'block'
	],
	'match' => [
		'shortcode',
		'inline'
	]
];
require_once realpath( __DIR__ . '/Display.php' );
require_once realpath( __DIR__ . '/Base_Display.php' );
foreach ( $mpg_available_displays as $display_type => $displays ) {
	require_once realpath( __DIR__ . '/' . $display_type . '/Core.php' );
	foreach ( $displays as $display_integration ) {
		require_once realpath( __DIR__ . '/' . $display_type . '/' . ucwords( $display_integration,'_' ) . '.php' );
		$display_integration = __NAMESPACE__ . '\\' . ucwords( $display_type,'_' ) . '\\' . ucwords( $display_integration, '_' );
		$display_integration = new $display_integration();
		$display_integration->register();
	}
}