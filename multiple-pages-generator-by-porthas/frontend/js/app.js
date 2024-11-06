
import {doInit, handleAdvance} from "./components/onload";

import { __ } from '@wordpress/i18n';
import {mpgUpdateState} from "./helper.js";
import {pageBuilderInit} from "./components/page-builder";
import {initSiteMapEvents} from "./components/sitemap";
import {initSpintax} from "./components/spintax";
import {initCache} from "./components/cache";
import {initLogs} from "./components/logs";
import {initDataset} from "./dataset-library";
import {initAdvance} from "./advanced-settings";
import {initSearch} from "./search-settings";


jQuery(document).ready(async function () {

    function getProjectIdFromUrl() {
        if (location.href.includes('mpg-project-builder&action=edit_project&id=')) {

            const url = new URL(location.href);
            return url.searchParams.get('id');
        }

        return null;
    }


    const openedProjectId = getProjectIdFromUrl();

    if (openedProjectId) {
        jQuery(`#toplevel_page_mpg-dataset-library .wp-submenu a`).each((index, elem) => {
            const urlParams = new URLSearchParams(jQuery(elem).attr('href'));
            if (urlParams.get('id') === openedProjectId) {
                jQuery(elem).parent().addClass('current');
            }
        })
    }
    pageBuilderInit();
    await doInit();
    handleAdvance();
    initSiteMapEvents();
    initSpintax();
    initCache();
    initLogs();
    initDataset();
    initAdvance();
    await initSearch();
});


jQuery(document).ready(function () {

    jQuery.post(ajaxurl, {
        action: 'mpg_get_permalink_structure',
        securityNonce: backendData.securityNonce
    }).then(permalink => {

        let permalinkData = JSON.parse(permalink)

        if (!permalinkData.success) {
            toastr.error(permalinkData.error, 'Checking permalink structure');
        }

        if (permalinkData.data === '') {
            toastr.warning(`${__('Your permailnk structure is Plain. MPG needed to change permalink structure to any other, like a /postname/. Do you want to', 'multi-pages-plugin')} <a href="#" style="color:green;" class="fix-permalink-structure">${__('fix it?', 'multi-pages-plugin')}</a>`, __('Wrong permalink structure', 'multi-pages-plugin'), { timeOut: 10000 });
        }
    });

    // Инициализация тултипов
    tippy('[data-tippy-content]');


    // ==================     Datetime picker init ===============
    let dateObject = new Date();
    jQuery('input[name="datetime_upload_remote_file"]').datetimepicker({
        minuteStepping: 1,               //set the minute stepping
        minDate: `1/1/1900`,
        minTime: `${dateObject.getHours()}:${dateObject.getMinutes()}`,
        step: 10 // minutes
    });

    jQuery('input[name="mpg_timezone_name"]').val(Intl.DateTimeFormat().resolvedOptions().timeZone);

    mpgUpdateState('limit', 5);
});


jQuery(document).on('click', '.fix-permalink-structure', function (e) {

    e.preventDefault();

    jQuery.post(ajaxurl, {
        action: 'mpg_change_permalink_structure',
        securityNonce: backendData.securityNonce
    }).then(permalink => {

        let permalinkData = JSON.parse(permalink)

        if (!permalinkData.success) {
            toastr.error(__('Checking permalink structure failed, due to: ', 'multi-pages-plugin') + permalinkData.error, __('Failed', 'multi-pages-plugin'));
        } else {
            toastr.success(permalinkData.data, __('Success', 'multi-pages-plugin'));
        }
    });
});

jQuery(document).on('submit', 'form#subscribe-form', function (e) {
    e.preventDefault();
    var _this = jQuery(this);
    _this
        .addClass('sent');

    var mainElement = jQuery(this).parents('.mpg-free-seo-guide');

    jQuery.post(
        ajaxurl,
        jQuery(this).serialize(),
        function(response) {
            if ( response.status ) {
                mainElement?.find('.mpg-title')?.text( mainElement.find('.mpg-title').data('success_title') );
                mainElement?.find('.mpg-form-message')?.text( mainElement.find('.mpg-form-message').data('success_message') );
                mainElement?.find('.mpg-image img:not(.d-none)')?.addClass('d-none').next('img').removeClass('d-none');
            } else {
                alert( response.message );
                _this
                    .removeClass('sent');
            }
        },
        'json'
    )
        .fail(function( xhr ){
            console.log(xhr);
            _this
                .removeClass('sent');
        })
});

