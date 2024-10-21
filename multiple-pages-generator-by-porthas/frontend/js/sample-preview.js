( function( window, wp ){
    if ( ! MPGSamplePreview?.previewUrl ) {
        return;
    }
    var link_id = 'mpg_view_sample_url';
    var link_html = '<a id="' + link_id + '" class="button button-primary button-large" href="' + MPGSamplePreview?.previewUrl + '" target="_blank">' + MPGSamplePreview?.buttonText + '</a>';

    // check if gutenberg's editor root element is present.
    var editorEl = document.getElementById( 'editor' );
    if( !editorEl ){ // do nothing if there's no gutenberg root element on page.
        return;
    }

    wp.data.subscribe( function () {
        setTimeout( function () {
            if ( !document.getElementById( link_id ) ) {
                var toolbalEl = editorEl.querySelector( '.editor-header__toolbar' );
                if( toolbalEl instanceof HTMLElement ){
                    toolbalEl.insertAdjacentHTML( 'beforeend', link_html );
                }
            }
        }, 1 )
    } );

} )( window, wp )