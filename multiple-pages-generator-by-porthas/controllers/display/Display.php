<?php
namespace MPG\Display;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
interface DisplayInterface {
	/**
	 * Registers the display element.
	 */

	public function register();
}