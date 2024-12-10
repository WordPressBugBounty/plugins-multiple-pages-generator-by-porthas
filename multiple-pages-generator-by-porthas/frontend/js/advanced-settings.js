import { __ } from '@wordpress/i18n';
export function initAdvance(){
jQuery('#mpg_update_tables_structure').on('click', async function () {

    const event = await jQuery.post(ajaxurl, {
        action: 'mpg_activation_events',
        isAjax: true,
        securityNonce: backendData.securityNonce
    });

    let eventData = JSON.parse(event);

    if (!eventData.success) {
        toastr.error(eventData.error, __('Failed', 'multiple-pages-generator-by-porthas'));
    } else {
        toastr.success(__('MPG tables structure updated successfully', 'multiple-pages-generator-by-porthas'), __('Success', 'multiple-pages-generator-by-porthas'), { timeOut: 5000 });
    }
})

jQuery('.advanced-page .mpg-hooks-block').on('submit', async function (e) {

    e.preventDefault();

    const selectedHook = jQuery('#mpg_hook_name').val();
    const hookPriority = jQuery('#mpg_hook_priority').val();

    const event = await jQuery.post(ajaxurl, {
        action: 'mpg_set_hook_name_and_priority',
        'hook_name': selectedHook,
        'hook_priority': hookPriority,
        'securityNonce': backendData.securityNonce
    });

    let eventData = JSON.parse(event);

    if (!eventData.success) {
        toastr.error(eventData.error, __('Failed', 'multiple-pages-generator-by-porthas'));
    } else {
        toastr.success(__('Hook settings updated successfully', 'multiple-pages-generator-by-porthas'), __('Success', 'multiple-pages-generator-by-porthas'), { timeOut: 5000 });
    }
});

jQuery('.advanced-page .mpg-path-block').on('submit', async function (e) {

    e.preventDefault();

    const basePath = jQuery(this).find('select').val();

    const event = await jQuery.post(ajaxurl, {
        action: 'mpg_set_basepath',
        'basepath': basePath,
        securityNonce: backendData.securityNonce
    });

    let eventData = JSON.parse(event);

    if (!eventData.success) {
        toastr.error(eventData.error, __('Failed', 'multiple-pages-generator-by-porthas'));
    } else {
        toastr.success(__('Basepath settings updated successfully', 'multiple-pages-generator-by-porthas'), __('Success', 'multiple-pages-generator-by-porthas'), { timeOut: 5000 });
    }
});



jQuery('.advanced-page .mpg-cache-hooks-block').on('submit', async function (e) {

    e.preventDefault();

    const selectedHook = jQuery('#mpg_cache_hook_name').val();
    const hookPriority = jQuery('#mpg_cache_hook_priority').val();

    const event = await jQuery.post(ajaxurl, {
        action: 'mpg_set_cache_hook_name_and_priority',
        'cache_hook_name': selectedHook,
        'cache_hook_priority': hookPriority,
        securityNonce: backendData.securityNonce

    });

    let eventData = JSON.parse(event);

    if (!eventData.success) {
        toastr.error(eventData.error, __('Failed', 'multiple-pages-generator-by-porthas'));
    } else {
        toastr.success(__('Hook settings updated successfully', 'multiple-pages-generator-by-porthas'), __('Success', 'multiple-pages-generator-by-porthas'), { timeOut: 5000 });
    }
});



jQuery('.advanced-page .mpg-branding-position-block').on('submit', async function (e) {

    e.preventDefault();

    const position = jQuery('#mpg_change_branding_position').val();

    const event = await jQuery.post(ajaxurl, {
        action: 'mpg_set_branding_position',
        'branding_position': position ? position : 'left',
        securityNonce: backendData.securityNonce
    });

    let eventData = JSON.parse(event);

    if (!eventData.success) {
        toastr.error(eventData.error, __('Failed', 'multiple-pages-generator-by-porthas'));
    } else {
        toastr.success(__('Hook settings updated successfully', 'multiple-pages-generator-by-porthas'), __('Success', 'multiple-pages-generator-by-porthas'), { timeOut: 5000 });
    }
});

jQuery('.advanced-page .mpg-pro-license').on('submit', async function (e) {

    e.preventDefault();

    const _this = jQuery(this);
    _this
    .find('.btn-primary')
    .attr('disabled', true);

    const event = jQuery.post(
            ajaxurl,
            jQuery(this).serialize(),
            function (response) {
                if (!response.success) {
                    toastr.error(response.message);
                    _this
                    .find('.btn-primary')
                    .removeAttr('disabled');
                } else {
                    toastr.success(response.message, { timeOut: 5000 });

                    _this
                    .find('.btn-primary')
                    .removeAttr('disabled')
                    .text(response.button_text);

                    if (response.action === 'activate') {
                        _this
                        .find('#license_key')
                        .attr('disabled', true)
                        .val(response.key);

                        _this
                        .find('input[name="_action"]')
                        .val('deactivate');

                        _this
                        .find('.mpg-license-message')
                        .removeClass('d-none')
                        .html(response.expiration);
                    } else {
                        _this
                        .find('#license_key')
                        .removeAttr('disabled')
                        .val(response.key);

                        _this
                        .find('input[name="_action"]')
                        .val('activate');

                        _this
                        .find('.mpg-license-message')
                        .addClass('d-none');
                    }
                }
            },
            'json'
        );
});

jQuery('.advanced-page #license_key').on( 'input', function() {
    jQuery('.advanced-page input[name="license_key"]').val(jQuery(this).val());
} );
}