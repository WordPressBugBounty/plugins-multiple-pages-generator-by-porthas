<?php

/**
 * Class MPG_I18n
 *
 * Handles internationalization (i18n) functionality for the Multiple Pages Generator plugin.
 */
class MPG_I18n {

    /**
     * Get the translated label.
     * 
     * @param string $label_slug The string slug.
     * 
     * @return string
     */
    public static function get_label( $label_slug ) {
        $labels = array(
            'pro' => array(
                'licenseTitle'          => __( 'MPG Pro license', 'multiple-pages-generator-by-porthas' ),
                'enterLicenseFotUpdate' => __( 'Enter your license from Themeisle purchase history in order to get plugin updates', 'multiple-pages-generator-by-porthas' ),
                'activate'              => __( 'Activate', 'multiple-pages-generator-by-porthas' ),
                'deactivate'            => __( 'Deactivate', 'multiple-pages-generator-by-porthas' ),
                'invalidProvider'       => __( 'Invalid license provided', 'multiple-pages-generator-by-porthas' ),
                // translators: mark that the product license is valid.
                'valid'                 => __( 'Valid', 'multiple-pages-generator-by-porthas' ),
                // translators: it is followed by the expiration date.
                'expires'               => __( 'Expires', 'multiple-pages-generator-by-porthas' ),
            )
        );

        if ( empty( $label_slug ) ) {
			return '';
		}
		/**
		 * Allow accessing labels by key.
		 */
		$keys = explode( '.', $label_slug );
		if ( count( $keys ) === 1 && isset( $labels[ $keys[0] ] ) ) {
			return $labels[ $keys[0] ];
		}
		if ( count( $keys ) === 2 && isset( $labels[ $keys[0] ][ $keys[1] ] ) ) {
			return $labels[ $keys[0] ][ $keys[1] ];
		}

		return '';
    }
}