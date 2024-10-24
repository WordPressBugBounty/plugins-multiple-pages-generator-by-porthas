import {
    convertHeadersToShortcodes,
    mpgUpdateState,
    renderShortCodesDropdown,
} from '../helper.js';
import { translate } from '../../lang/init.js';
import { mpgGetState } from '../helper.js';

async function fillCustomTypeDropdown(projectData) {
    let customTypes = await jQuery.post(ajaxurl, {
        action: 'mpg_get_posts_by_custom_type',
        custom_type_name: projectData.data.entity_type,
        template_id: projectData.data.template_id,
        securityNonce: backendData.securityNonce,
    });

    let postsData = JSON.parse(customTypes);

    if (postsData.success !== true) {
        throw postsData.error;
    }

    let setTemplateDropdown = jQuery('#mpg_set_template_dropdown');

    // Очищаем выпадающий список перед тем, как кидать туда новые сущности
    setTemplateDropdown.empty();

    postsData.data.forEach((entity) => {
        //  ставим selected для предварительно выбранного шаблона.
        if (entity.id === parseInt(projectData.data.template_id)) {
            setTemplateDropdown.append(
                new Option(entity.title, entity.id, false, true)
            );
        } else {
            if (entity.is_home) {
                let option = new Option(
                    `${entity.title} (${translate['Front page']})`,
                    entity.id
                );
                option.disabled = true;
                setTemplateDropdown.append(option);
            } else {
                setTemplateDropdown.append(new Option(entity.title, entity.id));
            }
        }
    });

    // Получав ссылку из value - делаем на нее редирект
    setTemplateDropdown.on('change', function () {
        var templateId = jQuery(this).val();
        if (templateId?.includes('post-new')) {
            window.open(jQuery(this).val(), '_blank');
        }
        if ( templateId > 0 ) {
            jQuery(this)
            .parent('div')
            .removeClass('col-sm-12 pr-0')
            .addClass('col-sm-9')
            .next('#mpg_edit_template_link')
            .removeClass('d-none disabled')
            .attr('href', function() {
                return jQuery(this).data('edit_link').replace('#id#', templateId);
            });
        }
    });

    if (projectData.data.source_type === 'direct_link') {
        jQuery('#direct_link').click();
    }
    setTemplateDropdown.select2({
        placeholder: projectData.data.entity_type === 'post' ? translate['+ Add new post'] : `${translate['+ Add new']} ${projectData.data.entity_type}`,
        width: '100%',
        minimumInputLength: 3,
        ajax: {
            delay: 250,
            url: ajaxurl,
            dataType: 'json',
            method: 'post',
            data: function (term) {
                return {
                    action: 'mpg_get_posts_by_custom_type',
                    custom_type_name: projectData.data.entity_type,
                    q: term,
                    securityNonce: backendData.securityNonce,
                };
            },
            processResults: function (res) {
                if (projectData.data.entity_type === 'post') {
                    res.data.push({
                        id: backendData.mpgAdminPageUrl + 'post-new.php',
                        title: translate['+ Add new post'],
                    });
                } else if (projectData.data.entity_type) {
                    res.data.push({
                        id:
                            backendData.mpgAdminPageUrl +
                            'post-new.php?post_type=' +
                            projectData.data.entity_type,
                        title:
                            translate['+ Add new'] +
                            ' ' +
                            projectData.data.entity_type,
                    });
                }
                return {
                    results: jQuery.map(res.data, function (obj) {
                        return {
                            id: obj.id,
                            text: obj.title,
                            disabled: obj.is_home || false,
                        }
                    } )
                }
            }
        }
    }).on('select2:close', function(){
        var templateId = jQuery(this).val();
        if (templateId?.includes('post-new')) {
            jQuery(this).html('');
        }
    });
}

function fillDataPreviewAndUrlGeneration(project, headers) {

    // Достаем из ответа, и ставим в стейт первый ряд данных, чтобы сформировать превью для url.
    mpgUpdateState('datasetFirstRow', project.data.rows[0]);

    const summaryBlock = jQuery('#collapse_2 .summary');

    summaryBlock
    .parents('.sub-section.d-none')
    .removeClass('d-none');

    const summaryBlockContent = summaryBlock.text();
    //  Ставим правильное значение для количества рядов и заголовков в файле
    summaryBlock.text(
        summaryBlockContent
            .replace('[rows]', project.data.totalRows)
            .replace('[headers]', headers?.length)
    );

    // ['Url']  => [{title: 'mpg_url'}]
    let columnsStorage = convertHeadersToShortcodes(headers);

    const dataTableContainer = jQuery('#mpg_dataset_limited_rows_table');

    const initObject = {
        data: project.data.rows,
        columns: columnsStorage,
        paging: false,
        searching: false,
        ordering: false,
        retrieve: true,
        language: {
            "lengthMenu": "Show _MENU_ entries",
        },
        responsive: true,
    };

    // Перед тем как отрисовать новую таблицу, сначала удалим старую
    dataTableContainer.DataTable(initObject).clear().destroy();
    dataTableContainer.empty();
    let table = dataTableContainer.DataTable(initObject);

    {
        // Прячем колонки, которые не помищеются, чтобы небыло скрола.
        try {
            let tableContainer = jQuery('.data-table-container');
            let containerWidth = tableContainer.width();
            let widthStorage = 0;
            let tableHeaders = jQuery(
                '#mpg_dataset_limited_rows_table thead th'
            );
            let columnsToHide = [];

            jQuery.each(tableHeaders, function (index, elem) {
                widthStorage += jQuery(elem).outerWidth();

                if (widthStorage > containerWidth) {
                    columnsToHide.push(index); // например 5, 6, 7 ..., потому что первых 4 помещаются.
                }
            });
            table.columns(columnsToHide).visible(false);
        } catch (err) {
            console.error(err);
        }
    }

    // Insert shortcodes
    const insertShorecodeDropdown = jQuery(
        '#mpg_main_tab_insert_shortcode_dropdown'
    );
    insertShorecodeDropdown.removeAttr('disabled').prop('disabled', false);
    insertShorecodeDropdown.empty();

    if (headers) {
        renderShortCodesDropdown(headers, insertShorecodeDropdown);
    }

    // Перерисовка поля с превью url
    jQuery('#mpg_url_constructor').attr('contenteditable', true).trigger('input');

    insertShorecodeDropdown.select2({
        width: '100%',
    });
}

function renderTableWithAllURLs(e) {
    e.preventDefault();

    const projectId = mpgGetState('projectId');
    if ( ! projectId ) {
        return;
    }
    jQuery('#mpg_preview_all_urls').modal();
    const previewTabTableContainer = jQuery('#mpg_mpg_preview_all_urls_table');

    const initObject = {
        serverSide: true,
        columns: [{ title: 'mpg_url' }],
        paging: true,
        searching: true,
        retrieve: true,
        ajax: {
            url: `${ajaxurl}?action=mpg_preview_all_urls&projectId=${projectId}&securityNonce=${backendData.securityNonce}`,
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
}

export {
    fillCustomTypeDropdown,
    fillDataPreviewAndUrlGeneration,
    renderTableWithAllURLs,
};
