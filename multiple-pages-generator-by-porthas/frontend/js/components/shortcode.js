import {
    mpgGetState,
    copyTextToClipboard,
    renderShortCodesDropdown,
    mpgUpdateState,
    insertTextAfterCursor,
    rebuildSandboxShortcode,
} from '../helper.js';

import { __ } from '@wordpress/i18n';

function renderConditionRowDom(dropdownOptions) {

    return `<div class="condition-row">
  <select class="mpg_headers_condition_key_dropdown input-data">
    <option value="not-set" selected>-- Not set --</option>${dropdownOptions}</select>
   <select class="mpg_rule_operator input-data">
        <option value="=" selected>=</option>
        <option value="!=" >!=</option>
        <option value=">" >></option>
        <option value="<" ><</option>
        <option value=">=" >>=</option>
        <option value="<=" ><=</option>
    </select>
  <select disabled="disabled" class=" mpg_headers_condition_value_dropdown input-data">
    <option disabled selected>${__('Choose header at first', 'multiple-pages-generator-by-porthas')}</option>
  </select>
  <button class="add-new-condition" style="display: inline-flex;"><span class="dashicons dashicons-no-alt"></span></button>
</div>`;
}

function shortCodeTabInit() {
    const headers = mpgGetState('headers');

    const shortcodeSelector = jQuery('.shortcode-preview-output');
    const insertShortcodeDropdown = jQuery(
        '#mpg_shortcode_tab_insert_shortcode_dropdown'
    );
    const headersShortcodeDropdown = jQuery('.shortcode-headers-dropdown');

    let dropdownOptions;

    headers.forEach((header, index) => {
        const headerValue = header.startsWith('mpg_') ? header : `mpg_${header}`;
        dropdownOptions += `<option value="${index}" >${headerValue}</option>`;
    });

    // Добавляем первый блок для условий
    jQuery('.condition-container').append(
        renderConditionRowDom(dropdownOptions)
    );

    jQuery('#mpg_order_by').append(dropdownOptions);

    headers.forEach((rawHeader) => {
        // Тут походу еще надо заменять пробелы на _, и бывают всякие сиволы доллара, и т.п.

        let header = rawHeader.toLowerCase().startsWith('mpg_')
            ? rawHeader.toLowerCase().replace(/ /g, '_')
            : `mpg_${rawHeader.toLowerCase().replace(/ /g, '_')}`;

        headersShortcodeDropdown.append(new Option(header, header));

        // При загрузке страницы, сразу выводим в блок шорткод, который является первым option
        shortcodeSelector.text(
            `{{${headersShortcodeDropdown.find('option:first-child').val()}}}`
        );
    });

    // ================ Insert Shortcode ===============
    insertShortcodeDropdown.empty();

    if (headers) {
        renderShortCodesDropdown(headers, insertShortcodeDropdown);
    }

    insertShortcodeDropdown.select2({ width: '100%' });

    let textarea = 'mpg_shortcode_sandbox_textarea';

    jQuery(`#${textarea}`).on('blur', function () {
        window.isTextareaBlured = true;
    });

    insertShortcodeDropdown.on('change', function () {
        let choosedShortcode = jQuery(this).find('option:selected').text();

        // Если человек не поставил курсор в текстареа, чтобы вставить шорткод в позицию курсора,
        // то мы автоматом ставим шорткод между [mpg][/mpg]
        if (window.isTextareaBlured) {
            insertTextAfterCursor(textarea, `{{${choosedShortcode}}}`);
        } else {
            const sandboxValue = jQuery(`#${textarea}`).val();

            const positionOfClosedBrace = sandboxValue.indexOf('"]');

            // +2 - накидываем длинну строки "]
            insertTextAfterCursor(
                textarea,
                `{{${choosedShortcode}}}`,
                positionOfClosedBrace + 2
            );
        }
    });

    // Меняем пустое значение на реальный id проекта
    let sandBoxTextarea = jQuery('#mpg_shortcode_sandbox_textarea').val();
    const projectIdRegExp = /project-id="(.*?)"/gm;

    const updatedProjectId = sandBoxTextarea.replace(
        projectIdRegExp,
        `project-id="${mpgGetState('projectId')}"`
    );

    jQuery('#mpg_shortcode_sandbox_textarea').val(updatedProjectId);
    initEvents();
}

jQuery(document).on('change', '.shortcode-headers-dropdown', function () {
    let shortcode = jQuery(this).val();
    jQuery('.shortcode-preview-output').html(`{{${shortcode}}}`);
});

function rebuildWhere() {
    let where = [];

    // После выбора значения из второго дропдуана - собираем значение из всех, и кидаем в стейт.
    jQuery('.condition-row').each(function () {
        let key = jQuery(this)
            .find('.mpg_headers_condition_key_dropdown option:selected')
            .text();
        let value = jQuery(this)
            .find('.mpg_headers_condition_value_dropdown option:selected')
            .text();
        let operator_rule = jQuery(this)
            .find('.mpg_rule_operator')
            .val();
        // Encode the '<' symbol to '&lt;' to prevent issues with shortcode attribute parsing.
        if(operator_rule === '<' || operator_rule === '<='){
            operator_rule = operator_rule.replace( '<', '&lt;');
        }
        if (key !== '-- Not set --') {
            // Храню это как массив объектов, а не как объект, чтобы можно было
            // разные значения where для полей с одинаковыми названиями. (чтобы ключи не перезатирались)
            where.push({ value: value, operator: operator_rule, key:key} );

            mpgUpdateState('where', where);
        }
    });

    rebuildSandboxShortcode();
}
function initEvents(){
jQuery('.shortcode-copy').on('click', function () {
    if (copyTextToClipboard(jQuery('#mpg_shortcode_sandbox_textarea').val())) {
        toastr.success(
            __('Shortcode copied to clipboard!', 'multiple-pages-generator-by-porthas'),
            __('Success', 'multiple-pages-generator-by-porthas'),
            { timeOut: 3000 }
        );
    }
});

jQuery('.copy-shortcode-btn').on('click', function () {
    let shortcodeSelector = jQuery('.shortcode-preview-output');
    let shortcodeText = '';

    if (!shortcodeSelector.html().trim()) {
        shortcodeText =
            '{{' +
            headersShortcodeDropdown.find('option:first-child').val() +
            '}}';
    } else {
        shortcodeText = shortcodeSelector.html();
    }

    if (copyTextToClipboard(`${shortcodeText}`)) {
        toastr.success(
            __('Shortcode copied to clipboard!', 'multiple-pages-generator-by-porthas'),
            __('Success', 'multiple-pages-generator-by-porthas'),
            { timeOut: 3000 }
        );
    } else {
        toastr.warning(
            __('Looks like something went wrong while copying shortcode', 'multiple-pages-generator-by-porthas'),
            __('Hmm', 'multiple-pages-generator-by-porthas'),
            { timeOut: 3000 }
        );
    }
});

jQuery('.project-builder').on(
    'change',
    '.mpg_headers_condition_key_dropdown',
    async function () {
        let choosedColumnNumber = jQuery(this).val();

        jQuery(this).siblings('.mpg_headers_condition_value_dropdown').empty();
        jQuery('.mpg_headers_condition_value_dropdown').prepend(
            jQuery('<option></option>').html('Loading...')
        );

        let column = await jQuery.post(ajaxurl, {
            action: 'mpg_get_unique_rows_in_column',
            choosedColumnNumber,
            projectId: mpgGetState('projectId'),
            securityNonce: backendData.securityNonce,
        });

        let columnData = JSON.parse(column);

        if (!columnData.success) {
            toastr.error(columnData.error, 'Error');
            return;
        }

        jQuery(this).siblings('.mpg_headers_condition_value_dropdown').empty();

        columnData.data.forEach((value, index) => {
            jQuery(this)
                .siblings('.mpg_headers_condition_value_dropdown')
                .append(new Option(value, index));
        });

        let checkedKeyOption = jQuery(this).find('option:checked');

        if (checkedKeyOption.val() === 'not-set') {
            jQuery(this)
                .siblings('.mpg_headers_condition_value_dropdown')
                .attr('disabled', 'disabled');

            mpgUpdateState('where', []);

            rebuildSandboxShortcode();
        } else {
            jQuery(this)
                .siblings('.mpg_headers_condition_value_dropdown')
                .removeAttr('disabled');
        }
    }
);
jQuery(document).on(
    'blur',
    '.mpg_headers_condition_value_dropdown',
    async function () {
        rebuildWhere()
    }
);
jQuery(document).on(
    'blur',
    '.mpg_rule_operator',
    async function () {
        rebuildWhere()
    }
);

jQuery('#mpg_operator_selector').on('blur', function () {
    let operator = jQuery('#mpg_operator_selector option:selected').val();

    let sandboxValue = jQuery('#mpg_shortcode_sandbox_textarea').val();

    const operatorRegExp = /logic="(.*?)"/gm;
    let match = operatorRegExp.exec(sandboxValue);

    // Заменяем то что есть сейчас в блоке с кодом, на то что пользователь выбрал в дропдуане
    let newCondition = sandboxValue.replace(
        `logic="${match[1]}"`,
        `logic="${operator}"`
    );

    jQuery('#mpg_shortcode_sandbox_textarea').val(newCondition);
});

jQuery(document).on('click', '.add-new-rule', function (e) {
    e.preventDefault();
    const headers = mpgGetState('headers');
    let dropdownOptions;

    headers.forEach((header, index) => {
        const headerValue = header.startsWith('mpg_') ? header : `mpg_${header}`;
        dropdownOptions += `<option value="${index}" >${headerValue}</option>`;
    });

    jQuery('.condition-container').append(
        renderConditionRowDom(dropdownOptions)
    );
});

jQuery('.condition-container').on('click', 'button', function () {
    if (jQuery(this).hasClass('btn-success')) {
        jQuery('.condition-row')
            .find('button:eq(0)')
            .addClass('btn-danger')
            .addClass('mpp-remove-action')
            .removeClass('btn-success')
            .html('-');

        const headers = mpgGetState('headers');

        let dropdownOptions;

        headers.forEach((header, index) => {
            const headerValue = header.startsWith('mpg_') ? header : `mpg_${header}`;
            dropdownOptions += `<option value="${index}" >${headerValue}</option>`;
        });

        jQuery('.condition-container').append(
            renderConditionRowDom(dropdownOptions)
        );
    } else {
        let allWhereConditions = getMpgWhereState();

        let key = jQuery(this)
            .parent()
            .find('.mpg_headers_condition_key_dropdown option:selected')
            .text();
        let value = jQuery(this)
            .parent()
            .find('.mpg_headers_condition_value_dropdown option:selected')
            .text();
        let ruleop = jQuery(this)
            .parent()
            .find('.mpg_rule_operator')
            .val();
        // Encode the '<' symbol to '&lt;' to prevent issues with shortcode attribute parsing.
        if(ruleop === '<' || ruleop === '<='){
            ruleop = ruleop.replace( '<', '&lt;');
        }
        if (allWhereConditions) {
            allWhereConditions = allWhereConditions.filter(function (
                whereState
            ) {

                if (
                    whereState.key === key &&
                    whereState.value === value &&
                    whereState.operator === ruleop
                ) {
                    return false;
                }
                return true;
            });
            mpgUpdateState('where', allWhereConditions);
        }

        jQuery(this).parent().remove();

        rebuildSandboxShortcode(allWhereConditions);
    }
});
jQuery('#mpg_limit').on('change', function () {
    mpgUpdateState('limit', jQuery(this).val());
    rebuildSandboxShortcode();
});

jQuery('#mpg_direction').on('change', function () {
    const directionValue = jQuery(this).find('option:checked').val();

    if (['asc', 'desc', 'random'].includes(directionValue)) {
        mpgUpdateState('direction', directionValue);
        rebuildSandboxShortcode();

        // Если сортируем случайным образом - нет смысла выбирать столбец.
        jQuery('#mpg_order_by').attr('disabled', directionValue === 'random');
    } else {
        toastr.warning(
            __('Chosen wrong direction', 'multiple-pages-generator-by-porthas'),
            __('Error', 'multiple-pages-generator-by-porthas')
        );
    }
});

jQuery('#mpg_order_by').on('change', function () {
    const orderByValue = jQuery(this).find('option:checked').text();

    mpgUpdateState('order-by', orderByValue);
    rebuildSandboxShortcode();
});

jQuery('#mpg_unique_rows').on('change', function () {
    const uniqueRowsValue = jQuery(this).find('option:checked').val();

    mpgUpdateState('unique-rows', uniqueRowsValue.toLowerCase());
    rebuildSandboxShortcode();
});

// ================== Preview  =================

jQuery('#shortcode .shortcode-preview').on('click', async function () {
    let textareaValue = jQuery('#mpg_shortcode_sandbox_textarea').val();
    try {
        const contentRegex = /\[mpg.*?](.*?)\[\/mpg\]/;
        const projectIdRegExp = /project-id="(.*?)"/;
        const whereRegExp = /where="(.*?)"/;
        const operatorRegExp = /logic="(.*?)"/;
        const orderByRegExp = /order-by="(.*?)"/;
        const directionRegExp = /direction="(.*?)"/;
        const limitRegExp = /limit="(.*?)"/;
        const uniqueRowsRegExp = /unique-rows="(.*?)"/;

        // ==============   Content   ===============
        let contentMatches = contentRegex.exec(
            textareaValue.replace(/\n/g, '')
        );

        let content;
        if (contentMatches && contentMatches[1]) {
            content = contentMatches[1];
        } else {
            throw __('You need to fill some static content with shortcodes between [mpg] [/mpg]', 'multiple-pages-generator-by-porthas');
        }

        // =================  ProjectId  =================
        let projectIdMatches = projectIdRegExp.exec(
            textareaValue.replace(/\n/g, '')
        );

        let projectId;
        if (projectIdMatches && projectIdMatches[1]) {
            projectId = projectIdMatches[1].trim();
        }

        // =================  Where  =================
        let whereMatches = whereRegExp.exec(textareaValue.replace(/\n/g, ''));

        let where;
        if (whereMatches && whereMatches[1]) {
            where = whereMatches[1].trim();
        }

        // =================  Operator  =================
        let operatorMatches = operatorRegExp.exec(
            textareaValue.replace(/\n/g, '')
        );

        let operator;
        if (operatorMatches && operatorMatches[1]) {
            operator = operatorMatches[1].trim();
        }

        // =================  Limit  =================
        let limitMatches = limitRegExp.exec(textareaValue.replace(/\n/g, ''));

        let limit;
        if (limitMatches && limitMatches[1]) {
            limit = limitMatches[1].trim();
        }

        // ================ Order By =================
        let orderByMatches = orderByRegExp.exec(
            textareaValue.replace(/\n/g, '')
        );

        let orderBy;
        if (orderByMatches && orderByMatches[1]) {
            orderBy = orderByMatches[1].trim();
        }

        // ================ Order By =================
        let directionMatches = directionRegExp.exec(
            textareaValue.replace(/\n/g, '')
        );

        let direction;
        if (directionMatches && directionMatches[1]) {
            direction = directionMatches[1].trim();
        }

        // ================ Unique Rows =================
        let uniqueRowsMatches = uniqueRowsRegExp.exec(
            textareaValue.replace(/\n/g, '')
        );

        let uniqueRows;
        if (uniqueRowsMatches && uniqueRowsMatches[1]) {
            uniqueRows = uniqueRowsMatches[1].trim();
        }

        // ==================  AJAX =================
        let shortcodePreview = await jQuery.post(ajaxurl, {
            action: 'mpg_shortcode',
            content,
            projectId,
            where,
            operator,
            orderBy,
            direction,
            limit,
            uniqueRows,
            securityNonce: backendData.securityNonce,
        });

        let shortcodePreviewData = JSON.parse(shortcodePreview);

        if (!shortcodePreviewData.success) {
            toastr.error(shortcodePreviewData.error, __('Error', 'multiple-pages-generator-by-porthas'));
            return;
        }

        const previewData = shortcodePreviewData.data;

        // Решает проблему с тем, что если в превью в ссылку вставить порткод, типа {{mpg_city}},
        // то ссылка ведет на страниццу админки, типа domain.com/wp-admin/dzenzelivka, следствие - 404.
        if (
            previewData.includes('href="') &&
            !previewData.includes(backendData.baseUrl)
        ) {
            let htmlWithCorrectLink = previewData.replace(
                /href="/g,
                `href="${backendData.baseUrl}/`
            );

            jQuery('.mpg_list_preview-block').html(htmlWithCorrectLink);
        } else {
            jQuery('.mpg_list_preview-block').html(shortcodePreviewData.data);
        }
    } catch (error) {
        toastr.warning(error, __('Incorrect input', 'multiple-pages-generator-by-porthas'));
    }
});
}
export function getMpgWhereState(){
    let allWhereConditions = mpgGetState('where');
    if(!allWhereConditions || allWhereConditions.length === 0){
        return [];
    }
    if (allWhereConditions[0].hasOwnProperty('operator')) {
        return allWhereConditions;
    }
    allWhereConditions =  Object.keys(allWhereConditions).map(key => ({
        column: key,
        operator: '=',
        value: obj[key]
    }));
    mpgUpdateState('where', allWhereConditions);
}


export { shortCodeTabInit };
