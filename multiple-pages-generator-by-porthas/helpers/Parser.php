<?php

class MPG_Parser
{

	/**
	 * Apply various normalization to the row when preparing for display.
	 *
	 * @param $strings
	 *
	 * @return void
	 */
	public static function normalize_row(&$strings){
		array_walk($strings, function(&$s) {
			$s = str_replace('$', '&dollar;', $s);
		});
	}

	/**
	 * Localize content for various plugins.
	 *
	 * We need to localize the content before doing any replacing.
	 *
	 * @param $content
	 *
	 * @return void
	 */
	public static function localize_content( &$content ): void {
		if ( function_exists( 'trp_translate' ) ) {
			// Translate the content for TranslatePress.
			// Check if there are languages to translate and if we are not in the default language.
			$languages = trp_get_languages( 'nodefault' );
			if ( count( $languages ) === 0 ) {
				return;
			}
			if ( ! isset( $languages[ trp_get_locale() ] ) ) {
				return;
			}
			$content = trp_translate( $content, null, false );
			return;
		}
	}
}
