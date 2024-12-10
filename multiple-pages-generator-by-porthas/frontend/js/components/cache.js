import { mpgGetState } from '../helper.js';
import { __ } from '@wordpress/i18n';

export function initCache(){
    jQuery('#cache').on('click', '.card .enable-cache', async function () {

        const cacheType = jQuery(this).parent().attr('data-cache-type');

        if (!['disk', 'database'].includes(cacheType)) {
            console.error(
                __('Passed unsupported type of cache', 'multiple-pages-generator-by-porthas')
            );
        }

        let rawCacheEnablingStatus = await jQuery.post(ajaxurl, {
            action: 'mpg_enable_cache',
            projectId: mpgGetState('projectId'),
            type: cacheType,
            securityNonce: backendData.securityNonce
        });

        let cacheEnablingStatus = JSON.parse(rawCacheEnablingStatus)

        if (!cacheEnablingStatus.success) {
            toastr.error(cacheEnablingStatus.error, __('Failed', 'multiple-pages-generator-by-porthas'));
        }

        toastr.success(cacheEnablingStatus.data, __('Success', 'multiple-pages-generator-by-porthas'))

        jQuery('.cache-page .card-footer button.btn')
            .attr('disabled', 'disabled');

        jQuery(`.cache-page div[data-cache-type=${cacheType}] .enable-cache`)
            .removeAttr('disabled')
            .removeClass('btn-success enable-cache')
            .addClass('btn-warning disable-cache')
            .text( 
                __('Disable', 'multiple-pages-generator-by-porthas')
            );

        jQuery(`.cache-page div[data-cache-type=${cacheType}] .flush-cache`)
            .removeAttr('disabled')
            .removeClass('btn-light')
            .addClass('btn-danger');

        await getActualCacheStat();

    });

    jQuery('#cache').on('click', '.card .disable-cache', async function () {

        const cacheType = jQuery(this).parent().attr('data-cache-type');

        if (!['disk', 'database'].includes(cacheType)) {
            console.error(
                __( 'Passed unsupported type of cache', 'multiple-pages-generator-by-porthas')
            );
        }

        let rawCahceDisablingStatus = await jQuery.post(ajaxurl, {
            action: 'mpg_disable_cache',
            projectId: mpgGetState('projectId'),
            type: cacheType,
            securityNonce: backendData.securityNonce
        });

        let cahceDisablingStatus = JSON.parse(rawCahceDisablingStatus)

        if (!cahceDisablingStatus.success) {
            toastr.error(cahceDisablingStatus.error, __('Failed', 'multiple-pages-generator-by-porthas'));
        }

        toastr.success(cahceDisablingStatus.data, __('Success!', 'multiple-pages-generator-by-porthas'))

        jQuery('.cache-page .card-footer button.btn')
            .removeAttr('disabled');

        jQuery(`.cache-page button.disable-cache`)
            .addClass('btn-success enable-cache')
            .removeClass('btn-warning disable-cache')
            .text(
                __('Enable', 'multiple-pages-generator-by-porthas')
            );

        jQuery(`.cache-page button.flush-cache`)
            .attr('disabled', 'disabled')
            .addClass('btn-light')
            .removeClass('btn-danger');

        await getActualCacheStat();
    });


    jQuery(`.cache-page button.flush-cache`).on('click', async function () {

        let decision = confirm(
            __('Are you sure, that you want to flush cache? This action can not be undone.', 'multiple-pages-generator-by-porthas')
        );

        if (decision) {
            const cacheType = jQuery(this).parent().attr('data-cache-type');

            let rawCacheFlushStatus = await jQuery.post(ajaxurl, {
                action: 'mpg_flush_cache',
                projectId: mpgGetState('projectId'),
                type: cacheType,
                securityNonce: backendData.securityNonce
            });

            let cacheFlushStatus = JSON.parse(rawCacheFlushStatus)

            if (!cacheFlushStatus.success) {
                toastr.error(cacheFlushStatus.error, __('Failed', 'multiple-pages-generator-by-porthas'));
            }

            toastr.success(cacheFlushStatus.data, __('Success', 'multiple-pages-generator-by-porthas'));

            await getActualCacheStat();
        }
    });

    jQuery('#cache-tab').on('click', getActualCacheStat);
}

async function getActualCacheStat() {

    if (jQuery('.cache-page .buttons .btn.disable-cache').length) {

        const cacheType = jQuery('.disable-cache').parent().attr('data-cache-type');

        if (!['disk', 'database'].includes(cacheType)) {
            console.error(
                __( 'Passed unsupported type of cache', 'multiple-pages-generator-by-porthas')
            );
        }

        let rawCacheStats = await jQuery.post(ajaxurl, {
            action: 'mpg_cache_statistic',
            projectId: mpgGetState('projectId'),
            type: cacheType,
            securityNonce: backendData.securityNonce
        });

        let cacheStats = JSON.parse(rawCacheStats)

        if (!cacheStats.success) {
            toastr.error(cacheStats.error, __('Failed', 'multiple-pages-generator-by-porthas'));
        } else {

            jQuery('.cache-page .pages-in-cache, .cache-page .cache-size').text(
                __('N/A', 'multiple-pages-generator-by-porthas')
            );

            jQuery(`.cache-page .${cacheType} .pages-in-cache`).text(cacheStats.data.pagesCount);
            jQuery(`.cache-page .${cacheType} .cache-size`).text(cacheStats.data.pagesSize);
        }

    } else {
        // Если все типы кеша выключены
        jQuery('.cache-page .pages-in-cache, .cache-page .cache-size').text(
            __('N/A', 'multiple-pages-generator-by-porthas')
        );

    }
}