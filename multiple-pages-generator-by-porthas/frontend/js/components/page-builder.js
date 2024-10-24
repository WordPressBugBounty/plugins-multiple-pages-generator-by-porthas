import {
    mpgUpdateState,
    mpgGetState,
    convertHeadersToShortcodes,
    renderShortcodePill,
    generateUrlPreview,
    fillUrlStructureShortcodes,
    domToUrlStructure,
    setHeaders,
    rebuildSandboxShortcode,
    getProjectIdFromUrl,
} from '../helper.js';
import { translate } from '../../lang/init.js';
import { Upload } from '../../libs/jquery.ajaxFileUpload.js';
import {
    fillCustomTypeDropdown,
    fillDataPreviewAndUrlGeneration,
    renderTableWithAllURLs,
} from '../models/page-builder-model.js';

// При переходе на определенный проект = надо грузить конфиг с БД и закидывать в стейт именно для него.
// А если проект новый - то загружать дефолтный конфиг.
(function mpg_init() {
    mpgUpdateState('separator', '-'); // @todo: првоерить что это нормально работает.
})();

jQuery(window).on('beforeunload', function () {
    localStorage.removeItem('mpg_state');
});

// ========  Delete project  ========
jQuery('.delete-project').on('click', async function (e) {
    e.preventDefault();

    let decision = confirm(
        translate[
            'Are you sure, that you want to delete project? This action can not be undone.'
        ]
    );

    if (decision) {
        let project = await jQuery.post(ajaxurl, {
            action: 'mpg_delete_project',
            projectId: getProjectIdFromUrl(),
            securityNonce: backendData.securityNonce,
        });

        let projectData = JSON.parse(project);

        if (!projectData.success) {
            toastr.error(projectData.error, 'Can not delete project');
        }

        toastr.success(
            translate['Your project was successfully deleted'],
            translate['Deleted!']
        );

        setTimeout(() => {
            location.href = backendData.datasetLibraryUrl;
        }, 3000);
    }
});

jQuery('select[name="periodicity"]').on('change', function () {
    let value = jQuery(this).children('option:selected').val();

    let remoteUrl = jQuery(
        '.direct-link-schedule-form input[name="datetime_upload_remote_file"]'
    );
    let notificationLevel = jQuery(
        '.direct-link-schedule-form select[name="notification_level"]'
    );
    let notificationEmail = jQuery(
        '.direct-link-schedule-form input[name="notification_email"]'
    );
    if(value !== 'once'){
        jQuery('.mpg-date-changes').removeClass('mpg-hidden');
    }else{
        jQuery('.mpg-date-changes').addClass('mpg-hidden');
    }
    if (value !== 'now' && value !== 'once') {
        // Делаем остальные поля доступными.
        if (value !== 'ondemand') {
            remoteUrl.removeClass('disabled').attr('required', 'required');
        }
        notificationLevel.removeClass('disabled').attr('required', 'required');
        notificationEmail.removeClass('disabled').attr('required', 'required');

        jQuery(this)
        .parents('.sub-section')
        .find('.block-with-tooltip.sync-options.d-none')
        .removeClass('d-none');
    } else {

        notificationLevel.addClass('disabled').removeAttr('required');
        notificationEmail.addClass('disabled').removeAttr('required');
    }
    if (value === 'ondemand') {
        jQuery('#mpg-webhook-url').removeClass('d-none');
    }else{
        jQuery('#mpg-webhook-url').addClass('d-none');
    }
});

jQuery('select[name="notification_level"]').on('change', function () {
    let value = jQuery(this).children('option:selected').val();
    if (value === 'do-not-notify' ) {
        jQuery(this)
        .parents('.sub-section')
        .find('.block-with-tooltip.nf-options')
        .addClass('d-none');
        return;
    }
    jQuery(this)
    .parents('.sub-section')
    .find('.block-with-tooltip.nf-options')
    .removeClass('d-none');
});

// Подгружаем посты (сущности) которые есть в выбраном кастом типе.
jQuery(document).on('change', '#mpg_entity_type_dropdown', async function () {
    let customType = jQuery(this).val();

    await fillCustomTypeDropdown({ data: { entity_type: customType } });
});

// Обрабатываем выбраный файл как источник.
jQuery('input[name="mpg_upload_file_input"]').on('change', async function () {
    var file = jQuery(this)[0].files[0];
    var upload = new Upload(file);

    // Если загружаем файл, значит надо очистить инпут для ссылки на файл. (в соседней вкладке)
    jQuery('#direct_link input[name="direct_link_input"]').val('');

    // Create project before upload.
    await mainProjectSave();

    // try {
    let uploadFileRawResponse = await upload.doUpload();

    let uploadFileResponse = JSON.parse(uploadFileRawResponse);

    if (!uploadFileResponse.success) {
        throw uploadFileResponse.error;
    }

    mpgUpdateState('source', {
        type: 'upload_file',
        path: uploadFileResponse.data.path,
    });

    toastr.success(
        translate['We will use this file as source'],
        translate['Got it!'],
        { timeOut: 5000 }
    );

    let projectId = getProjectIdFromUrl();
    if ( ! projectId ) {
        projectId = mpgGetState('projectId');
    }

    let sourceBlockRawResponse = await jQuery.post(ajaxurl, {
        action: 'mpg_upsert_project_source_block',
        type: 'upload_file',
        projectId: projectId,
        path: uploadFileResponse.data.path,
        securityNonce: backendData.securityNonce,
    });

    let sourceBlockResponse = JSON.parse(sourceBlockRawResponse);

    if (!sourceBlockResponse.success) {
        toastr.error(
            translate[
                'Something went wrong while saving project data. Try reload page'
            ],
            translate['Can not update project']
        );
    }

    if (!setHeaders(sourceBlockResponse)) {
        throw translate['Can not get headers form source file'];
    }

    const headers = mpgGetState('headers');

    fillDataPreviewAndUrlGeneration(sourceBlockResponse, headers);

    if (!sourceBlockResponse.data.url_structure) {
        fillUrlStructureShortcodes(headers);
    }

    jQuery(this)
    .parents('.sub-section')
    .find('.use-direct-link-button')
    .removeAttr('disabled');
});

jQuery('input[name="direct_link_input"]').on('input', function () {
    const fieldValue = jQuery(this).val();

    if (fieldValue && fieldValue.includes('google.com')) {
        jQuery('.worksheet-id').find('input').removeClass('disabled');
    } else {
        jQuery('.worksheet-id').find('input').addClass('disabled');
    }

    if ( fieldValue !== '' ) {
        jQuery('.worksheet-id').next('.block-with-tooltip').find('select').removeClass('disabled');
        jQuery('.use-direct-link-button').removeClass('disabled');
    } else {
         jQuery('.worksheet-id').next('.block-with-tooltip').find('select').addClass('disabled');
        jQuery('.use-direct-link-button').addClass('disabled');
    }
});

jQuery('input[name="worksheet_id"]').on('input', function () {
    const fieldValue = jQuery(this).val();

    if (fieldValue === '0') {
        toastr.warning(
            translate[
                'Worksheet ID cannot be zero. If your document has one sheet or you would like to use the first sheet - just keep this field empty'
            ],
            translate['Wrong worksheet id'],
            { timeOut: 10000 }
        );
    }
});

// При клике на таб загрузки файла - скрываем прогресс-бар загрузки.
jQuery('#upload_file_tab').on('click', function () {
    jQuery('#progress-wrp').hide();
});

jQuery('#mpg_url_mode_group input').on('click', function () {
    const urlMode = jQuery(`#mpg_url_mode_group input:checked`).attr('id');

    mpgUpdateState('urlMode', urlMode);

    jQuery('#mpg_url_constructor').trigger('mpg_render_urls');
});

jQuery('#mpg_preview_modal_link').on('click', function (e) {
    e.preventDefault();

    jQuery('#mpg_preview_modal').modal();

    const headers = mpgGetState('headers');
    let projectId = getProjectIdFromUrl();
    if ( ! projectId ) {
        projectId = mpgGetState('projectId');
    }
    const previewTabTableContainer = jQuery('#mpg_data_full_preview_table');

    const initObject = {
        serverSide: true,
        columns: convertHeadersToShortcodes(headers),
        retrieve: true,
        resposive: true,
        ajax: {
            url: `${ajaxurl}?action=mpg_get_data_for_preview&projectId=${projectId}&securityNonce=${backendData.securityNonce}`,
            type: 'POST',
            // success: function (res) {  Может пригодится чтобы прятать лоадер }
        },
        language: {
            "lengthMenu": "Show _MENU_ entries",
        }
    };

    // Перед тем как отрисовать новую таблицу, сначала удалим старую
    previewTabTableContainer.DataTable(initObject).clear().destroy();
    previewTabTableContainer.empty();
    previewTabTableContainer.DataTable(initObject);
});

jQuery('.project-builder .spaces-replacer').on('click', function () {
    jQuery('.project-builder .spaces-replacer').removeClass('active');

    jQuery(this).addClass('active');

    mpgUpdateState('separator', jQuery(this).text());
    jQuery('#mpg_url_constructor').trigger('mpg_render_urls');
});

// При выборе шорткода из выпадающего списка, вставляем его в поле билдера для url.
jQuery('#mpg_main_tab_insert_shortcode_dropdown').on('change', function () {
    let shortcode = jQuery(
        '#mpg_main_tab_insert_shortcode_dropdown option:selected'
    ).text();

    jQuery('#mpg_url_constructor')
        .append(renderShortcodePill(shortcode))
        .trigger('mpg_render_urls');
});

// Удаляем блок при клике на крестик.
jQuery('#mpg_url_constructor').on(
    'click',
    '.shortcode-chunk .close',
    function () {
        jQuery(this).parent().remove();
        jQuery('#mpg_url_constructor').trigger('mpg_render_urls');
    }
);

jQuery('#mpg_url_constructor').on('keydown', function (event) {
    const deniedChars = [
        '<',
        '(',
        '[',
        '{',
        '\\',
        '^',
        '=',
        '$',
        '!',
        '|',
        ']',
        '}',
        ')',
        '?',
        '*',
        '+',
        '>',
        '@',
        '#',
        '%',
        ':',
        ';',
        '&',
        '`',
        "'",
        ',',
    ];

    toastr.options.preventDuplicates = true;
    if (deniedChars.includes(event.key)) {
        toastr.warning(
            translate['Unsupported char. Supported only _, -, /, ~, ., ='],
            'Warning'
        );
        return false;
    }
});

// Если изменяется что-то в url билдере - надо "перерисовать" preview url.
jQuery('#mpg_url_constructor').on(
    'mpg_render_urls input',
    function (e, action) {
        //  Если  будут изменения в структуре url'а - надо делать ссылку не кликабельной.
        let inputHtml = jQuery(this).text();

        // Когда человек собирает УРЛ во вкладке Main, мы ему этот же УРЛ подкидываем в shortcodes preview, для удобства
        rebuildSandboxShortcode(jQuery(this).html());

        const headers = mpgGetState('headers');
        const spaceReplacer = mpgGetState('separator');

        let linksAccumulator = '<ul>';
        const row = mpgGetState('datasetFirstRow'); // первый ряд

        let link = generateUrlPreview(inputHtml, headers, spaceReplacer, row);

        if (mpgGetState('urlMode') === 'without-trailing-slash') {
            link = link.replace(/\/$/, '');
        }

        if (action === 'init') {
            linksAccumulator += `<li><a target="_blank" href="${link}">${link}</a></li>`;
            jQuery('#mpg_preview_all_urls_link').removeClass('disabled-link');
        } else {
            linksAccumulator += `<li>${link}</li>`;
            jQuery('#mpg_preview_all_urls_link').addClass('disabled-link');
        }

        linksAccumulator += '</ul>';

        jQuery('#mpg_preview_url_list').html(linksAccumulator);
    }
);

jQuery('#mpg_preview_all_urls_link').on('click', renderTableWithAllURLs);

jQuery('#mpg_upload_file_input').on('change', function () {
    //get the file name
    var fileName = jQuery(this).val();
    //replace the "Choose a file" label
    jQuery(this).next('.mpg_upload_file-label').html(fileName);
});

jQuery('#mpg_unschedule_task').on('click', async function () {
    let decision = confirm(
        translate['Are you sure, that you want to unschedule task?']
    );

    if (decision) {
        let project = await jQuery.post(ajaxurl, {
            action: 'mpg_unschedule_cron_task',
            projectId: getProjectIdFromUrl(),
            securityNonce: backendData.securityNonce,
        });

        let projectData = JSON.parse(project);

        if (!projectData.success) {
            toastr.error(
                projectData.error,
                translate['Can not unschedule task']
            );
            return false;
        }

        toastr.success(
            translate['Task was successfully unschedule'],
            translate['Unscheduled!']
        );

        setTimeout(() => {
            location.href = `${
                backendData.projectPage
            }&action=edit_project&id=${getProjectIdFromUrl()}`;
        }, 1000);
    }
});

// Save project source block.
var projectSourceBlockSave = async function( pid = 0 ) {
    // Обрабатываем вставку ссылки на удаленный файл, который выбран как источник данных
    jQuery('#upload_file input[name="mpg_upload_file_input"]').val('');

    const fileUrl = jQuery(
        '#direct_link input[name="direct_link_input"]'
    ).val();

    if (!fileUrl) {
        toastr.warning(
            translate['You need to paste link to file before using it'],
            translate['Missing URL']
        );
        return;
    }

    const projectId = pid > 1 ? pid : getProjectIdFromUrl();

    const worksheetId = jQuery('input[name="worksheet_id"]').val().length
        ? jQuery('input[name="worksheet_id"]').val()
        : null;

    // При клике на кнопку- делаем ajax запрос, по ссылке скачиваем файл, ложим его в папку temp и возвращаем на фронт path
    let uploadFileRawResponse = await jQuery.post(ajaxurl, {
        action: 'mpg_download_file_by_url',
        projectId,
        fileUrl,
        worksheetId,
        securityNonce: backendData.securityNonce,
    });

    if (! uploadFileRawResponse) {
        toastr.error(
            translate[
                'Something went wrong while saving project data. Try reload page'
            ]
        );
        return;
    }
    let uploadFileResponse = JSON.parse(uploadFileRawResponse);

    if (uploadFileResponse.success !== true) {
        throw uploadFileResponse.error;
    }

    mpgUpdateState('source', {
        type: 'direct_link',
        path: uploadFileResponse.data.path,
    });

    toastr.success(
        translate['We will use this link to file as source'],
        translate['Uploaded successfully!'],
        { timeOut: 5000 }
    );

    let sourceBlockRawResponse = await jQuery.post(ajaxurl, {
        action: 'mpg_upsert_project_source_block',
        projectId: projectId,
        type: 'direct_link',
        path: uploadFileResponse.data.path,
        securityNonce: backendData.securityNonce,
    });

    let sourceBlockResponse = JSON.parse(sourceBlockRawResponse);

    if (!sourceBlockResponse.success) {
        toastr.error(
            translate[
                'Something went wrong while saving project data. Try reload page'
            ],
            translate['Can not update project']
        );
        return;
    }

    if (setHeaders(sourceBlockResponse)) {
        const headers = mpgGetState('headers');

        fillDataPreviewAndUrlGeneration(sourceBlockResponse, headers);

        if (!sourceBlockResponse.data.url_structure) {
            fillUrlStructureShortcodes(headers);
        }

        jQuery(
            'a[href="#shortcode"], a[href="#sitemap"], a[href="#spintax"]'
        ).removeClass('disabled');
    }

    // need to show block
    jQuery('#mpg_next_cron_execution').text(
        sourceBlockResponse.data.nextExecutionTimestamp
    );
};

// Main project save.
var mainProjectSave = async function() {
    let projectIdValue = getProjectIdFromUrl();
    const projectName = jQuery('.project-name').val();
    const entityType = jQuery('#mpg_entity_type_dropdown').val();
    const templateId = jQuery('#mpg_set_template_dropdown').val();
    const applyCondition = jQuery('#mpg_apply_condition').val();
    const submitButton = jQuery(this).find('button');
    submitButton.next('span.spinner').addClass('is-active');
    submitButton.attr('disabled', true);

    if ( ! projectIdValue ) {
        projectIdValue = mpgGetState('projectId');
    }
    const payload = {
        action: 'mpg_upsert_project_main',
        // null - это знак, что надо создавать новй проект, а если projectId есть, то обновляем
        projectId: projectIdValue,
        projectName,
        entityType,
        templateId,
        applyCondition,
        excludeInRobots: jQuery('#mpg_exclude_template_in_robots').is(
            ':checked'
        ),
        participateInSearch: jQuery('#mpg_participate_in_search').is(
            ':checked'
        ),
        participateInDefaultLoop: jQuery('#mpg_participate_in_default_loop').is(
            ':checked'
        ),
        securityNonce: backendData.securityNonce,
    };

    let response = await jQuery.post(ajaxurl, payload);

    let project = JSON.parse(response);

    if (!project.success) {
        toastr.error(
            translate[
                'Something went wrong while saving project data. Details:'
            ] + project.error,
            translate['Can not update project']
        );
        return;
    }

    let { projectId } = project.data;

    mpgUpdateState( 'projectId', projectIdValue || projectId );

    delete payload.securityNonce;
    delete payload.projectName;
    Object.keys(payload).forEach(
        (key) =>
            (payload[key] == null || payload[key] === undefined) &&
            delete payload[key]
    ); // Delete all null values

    jQuery(
        'a[href="#shortcode"], a[href="#sitemap"],  a[href="#spintax"], .save-changes-block .save-changes'
    ).removeClass('disabled');

    window?.tiTrk?.with('multi').set('save-changes-1', {
        feature: 'dashboard-saved-changes',
        featureComponent: 'project-main',
        value: payload,
        groupId: mpgGetState('projectId'),
    });
    window?.tiTrk?.uploadEvents();


    return mpgGetState('projectId');
};

// Save project URL block.
var projectUrlBlockSave = async function ( pid = 0 ) {
    const projectId = pid > 0 ? pid : getProjectIdFromUrl();
    const urlMode = jQuery(
        `input:radio[name='mpg_url_mode_group']:checked`
    ).val();
    const urlStructureField = jQuery('#mpg_url_constructor').html();
    let parsedUrlStructure = '';

    if (urlStructureField) {
        parsedUrlStructure = domToUrlStructure(urlStructureField);
    }

    const replacer = jQuery('.spaces-replacer.active').html();

    if (!parsedUrlStructure.includes('{{mpg_')) {
        toastr.warning(
            translate['Your URL must contain at least one shortcode'],
            translate['Wrong URL structure']
        );
        return;
    }

    let dataObject = {
        action: 'mpg_upsert_project_url_block',
        projectId: projectId,
        urlStructure: parsedUrlStructure.toLowerCase(),
        replacer,
        urlMode: urlMode
    };

    if (mpgGetState('source')) {
        dataObject.sourceType = mpgGetState('source').type;
    }

    let directLink = jQuery('input[name="direct_link_input"]:visible').val();
    let periodicity = jQuery('select[name="periodicity"]:visible').val();

    if (
        directLink &&
        periodicity &&
        periodicity !== 'now' &&
        periodicity !== 'once'
    ) {
        // Да, у нас есть path, но по нему файл не скачаешь. Надо  иметь url.
        dataObject.directLink = directLink;

        dataObject.timezone = jQuery(
            'input[name="mpg_timezone_name"]'
        ).val();
        dataObject.fetchDateTime = jQuery(
            'input[name="datetime_upload_remote_file"]'
        ).val();
        dataObject.notificateAbout = jQuery(
            'select[name="notification_level"]:visible'
        ).val();
        dataObject.notificationEmail = jQuery(
            'input[name="notification_email"]:visible'
        ).val();
    }
    if (periodicity) {
        dataObject.periodicity = periodicity;
    }
    dataObject.update_modified_on_sync = jQuery('select[name="update_modified_on_sync"]').val();
    if (jQuery(`input[name="worksheet_id"]`).val()) {
        dataObject.worksheetId = jQuery('input[name="worksheet_id"]:visible').val();
    } else {
        dataObject.worksheetId = null;
    }
    dataObject.securityNonce = backendData.securityNonce;

    let response = await jQuery.post(ajaxurl, dataObject);

    let project = JSON.parse(response);

    if (!project.success) {
        toastr.error(
            translate[
                'Something went wrong while saving project data. Try reload page'
            ],
            translate['Can not update project']
        );
        return;
    }

    toastr.success(
        translate['Project saved sucessully'],
        translate['Success']
    );

    delete dataObject.securityNonce;
    delete dataObject.notificationEmail;
    delete dataObject.directLink;
    Object.keys(dataObject).forEach(
        (key) =>
            (dataObject[key] == null || dataObject[key] === undefined) &&
            delete dataObject[key]
    ); // Delete all null values

    window?.tiTrk?.with('multi').set('save-changes-2', {
        feature: 'dashboard-saved-changes',
        featureComponent: 'project-url-block',
        value: dataObject,
        groupId: projectId,
    });

    window?.tiTrk?.uploadEvents();
};

// Передача данных с первой секции на сервер
jQuery('.save-changes-block').on('click', '.save-changes', async function (e) {
    e.preventDefault(); // Это для того, чтобы сработала вализация на поля
    let hasErrors = false;
    jQuery('.main-inner-content input, .main-inner-content select').each(function(e){
           if(jQuery(this).attr('required') && !jQuery(this).val() && jQuery(this).is(':visible')){
               jQuery(this)[0].reportValidity();
               console.log(jQuery(this));
                hasErrors = true;
            }
    })
    if(hasErrors){
        return;
    }
    jQuery(this).parent('.save-changes-block').find('span.spinner').addClass('is-active');
    jQuery(this).parent('.save-changes-block').find('button').attr('disabled', true);


    var projectId = await mainProjectSave();

    await projectUrlBlockSave(projectId);

    setTimeout(() => {
        window.location.href =
            backendData.projectPage + '&action=edit_project&id=' + projectId;
    }, 1000);

});

// Save project source block.
jQuery('.direct-link-schedule-form').on('submit', async function (e) {
    e.preventDefault(); // Это для того, чтобы сработала вализация на поля

    const submitButton = jQuery(this).find('button');
    submitButton.next('span.spinner').addClass('is-active');
    submitButton.attr('disabled', true);

    // Create project before save source block.
    var projectId = await mainProjectSave();
    
    await projectSourceBlockSave(projectId);

    submitButton.next('span.spinner').removeClass('is-active');
    submitButton.attr('disabled', false);
});

/**
 * Enable/disable telemetry.
 */
document
    .querySelector('input[name="mpg_enable_telemetry"]')
    ?.addEventListener('change', async function (event) {
        const response = await fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'mpg_options_update',
                securityNonce: backendData.securityNonce,
                enableTelemetry: event.target.checked ? 1 : 0,
            }),
        });

        const data = await response.json();

        if (!data.success) {
            event.target.checked = !event.target.checked;
            toastr.error(data.error, translate['Error']);
        }
    });

// Main Dashboard tab tracking.
[
    'mpg_apply_condition',
    'mpg_exclude_template_in_robots',
    'mpg_participate_in_search',
    'mpg_participate_in_default_loop',
].forEach((option) => {
    /** @type {HTMLInputElement} */
    const element = document.querySelector(`#${option}`);

    if (!element) {
        return;
    }

    element.addEventListener('change', (event) => {
        window.tiTrk?.with('multi').set(option, {
            feature: 'dashboard',
            featureComponent: option,
            featureValue: {
                checked: event.target?.checked,
                inputValue: event.target?.value,
            },
            groupId: 'main',
        });
    });
});

document
    .querySelector('button.use-direct-link-button')
    ?.addEventListener('click', () => {
        window.tiTrk?.with('multi').add({
            feature: 'dashboard',
            featureComponent: 'source-upload',
            featureValue: 'direct-link',
            groupId: 'main',
        });
    });

document
    .querySelector('input[name="mpg_upload_file_input"]')
    ?.addEventListener('click', () => {
        window.tiTrk?.with('multi').add({
            feature: 'dashboard',
            featureComponent: 'source-upload',
            featureValue: 'upload-file',
            groupId: 'main',
        });
    });

// Cache dashboard tab tracking.
document
    .querySelector('[data-cache-type="disk"] button.enable-cache')
    ?.addEventListener('click', (event) => {
        window.tiTrk?.with('multi').set('enable-disk-cache', {
            feature: 'dashboard',
            featureComponent: 'disk',
            featureValue: event.target?.disabled ? 'disable' : 'enable',
            groupId: 'cache',
        });
    });

document
    .querySelector('[data-cache-type="database"] button.enable-cache')
    ?.addEventListener('click', (event) => {
        window.tiTrk?.with('multi').set('enable-disk-cache', {
            feature: 'dashboard',
            featureComponent: 'database',
            featureValue: event.target?.disabled ? 'disable' : 'enable',
            groupId: 'cache',
        });
    });

document.querySelectorAll('button.flush-cache')?.forEach((button) => {
    button?.addEventListener('click', () => {
        window.tiTrk?.with('multi').add({
            feature: 'dashboard',
            featureComponent: 'flush',
            groupId: 'cache',
        });
    });
});

// Setup preset tracking.
document.querySelectorAll('#dataset_list li a').forEach((elem) => {
    if (!elem || !elem.getAttribute('data-dataset-id')) {
        return;
    }

    const label = elem.querySelector('span')?.innerText;
    if (!label) {
        return;
    }

    elem.addEventListener('click', (e) => {
        window.tiTrk?.with('multi').add({
            feature: 'setup',
            featureComponent: 'preset',
            featureValue: {
                label: label,
                value: elem.getAttribute('data-dataset-id'),
            },
        });
        window.tiTrk?.uploadEvents();
    });
});
