import { mpgGetState } from '../helper.js';
import { __ } from '@wordpress/i18n';
export function initLogs(){
jQuery('a[href="#logs"]').on('click', function () {

    const projectId = mpgGetState('projectId');
    const initObject = {
        processing: true,
        ajax: {
            "url": `${ajaxurl}?action=mpg_get_log_by_project_id&projectId=${projectId}&securityNonce=${backendData.securityNonce}`,
            "type": "POST"
        },
        columns: [
            { data: 'id' },
            { data: 'project_id' },
            { data: 'level' },
            { data: 'url' },
            { data: 'message' },
            { data: 'datetime' }
        ],
        serverSide: true,
        searching: false,
        retrieve: true,
        language: {
            "lengthMenu": "Show _MENU_ entries",
        }
    }

    jQuery('#mpg_logs_table').DataTable(initObject);


    jQuery('#mpg_clear_log_by_project_id').on('click', async function () {

        const projectId = mpgGetState('projectId');

        let project = await jQuery.post(ajaxurl, {
            action: 'mpg_clear_log_by_project_id',
            projectId,
            securityNonce: backendData.securityNonce
        });

        let projectData = JSON.parse(project)

        if (!projectData.success) {
            toastr.error(projectData.error, __('Can not clear log for current project', 'multi-pages-plugin'));
            return false;
        }

        toastr.success(__('Log was cleared', 'multi-pages-plugin'), __('Done!', 'multi-pages-plugin'));

        const logsTable = jQuery('#mpg_logs_table');

        logsTable.DataTable(initObject).clear().destroy();
        logsTable.empty();
        logsTable.DataTable(initObject);

    });
})
}