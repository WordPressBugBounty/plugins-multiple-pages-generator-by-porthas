<?php
/**
 * Display all project list template.
 *
 * @package MPG
 */

?>
<div class="wrap">
	<h2 style="display:inline-block; margin-right: 5px;"><?php esc_html_e( 'Projects', 'mpg' ); ?></h2>
	<?php
		$new_project_url = add_query_arg(
			'page',
			'mpg-dataset-library',
			admin_url( 'admin.php' )
		);
		?>
	<div class="mpg-header-action">
        <a href="<?php echo esc_url_raw( $new_project_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Project', 'mpg' ); ?></a>
        <a href="#" id="mpg_import" class="page-title-action mpg-export-import-btn<?php echo ! mpg_app()->is_premium() ? ' mpg-export-import-btn-pro' : ''; ?>"><?php echo ! mpg_app()->is_premium() ? '<span class="dashicons dashicons-lock"></span>' : '<span class="dashicons dashicons-upload"></span>'; ?><?php esc_html_e( 'Import projects', 'mpg' ); ?></a>   
    </div>
	<hr class="wp-header-end">
    <div class="mpg-import-field hidden">
        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( add_query_arg( array( 'page' => 'mpg-project-builder', 'action' => 'mpg_import_projects', '_wpnonce' => wp_create_nonce( 'mpg_import_projects' ) ), admin_url( 'admin.php' ) ) ); ?>">
            <h4> <?php _e( "Choose the project's .json file to import.", 'mpg' ); ?></h4><input type="file" accept=".json" name="mpg_import" required>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'mpg' ); ?></button>
        </form>
    </div>
	<form method="get">
		<?php $projects_list->prepare_items(); ?>
		<p class="search-box">
			<input type="hidden" name="page" value="<?php esc_attr_e( 'mpg-project-builder', 'mpg' ); ?>">
			<label class="screen-reader-text" for="search_email-search-input"><?php esc_html_e( 'Search:', 'mpg' ); ?></label>
			<input type="search" id="search_email-search-input" name="s" value="<?php echo isset( $_GET['s'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification ?>" placeholder="<?php esc_attr_e( 'Search by project name', 'mpg' ); ?>">
			<input type="hidden" name="_mpg_nonce" value="<?php echo esc_attr( wp_create_nonce( MPG_BASENAME ) ); ?>">
			<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search', 'mpg' ); ?>">
		</p>
		<?php $projects_list->display(); ?>
	</form>
</div>
<!-- HTML for the modal -->
<div id="mpg-modal" class="wp-core-ui mpg-modal" style="display:none;">
    <div class="modal-content"><button type="button" class="notice-dismiss close-modal">
            <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this dialog', 'mpg' ); ?></span>
        </button>
        <div class="modal-header">
            <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Cloning projects is a PRO feature', 'mpg' ); ?></h2>
        </div>
        <div class="modal-body">
            <p><?php esc_html_e( 'We\'re sorry, cloning projects is not available on your plan. Please upgrade to the Pro plan to unlock all these features and enhance your product fields management capabilities.', 'mpg' ); ?></p>
        </div>
        <div class="modal-footer">
            <div class="button-container"><a href="<?php echo esc_url(mpg_app()->get_upgrade_url('clone')); ?>" target="_blank" rel="noopener " class="button button-primary button-large"><?php esc_html_e( 'Upgrade to PRO', 'mpg' ); ?><span aria-hidden="true" class="dashicons dashicons-external"></span></a></div>
        </div>
    </div>
</div>
<?php
$license_data      = mpg_app()->get_license_data();
$renew_license_url = mpg_app()->get_upgrade_url( 'renew' );
if ( ! empty( $license ) && ( is_object( $license ) && isset( $license->key ) ) ) {
	$download_id       = $license->download_id ?? '';
	$license_key       = $license->key;
	$renew_license_url = tsdk_utmify( 'https://store.themeisle.com/?edd_license_key=' . $license_key . '&download_id=' . $download_id, 'mpg_license_block' );
}

?>
<!-- HTML for the modal -->
<div id="mpg-modal-edit" class="wp-core-ui mpg-modal" style="display:none;">
    <div class="modal-content">
        <button type="button" class="notice-dismiss close-modal">
            <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this dialog', 'mpg' ); ?></span>
        </button>
        <div class="modal-header">
            <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Alert!', 'mpg' ); ?></h2>
        </div>
        <div class="modal-body">
            <p><?php esc_html_e( 'In order to edit premium projects, benefit from updates and support for MPG Premium plugin, please renew your license code or activate it.', 'mpg' ); ?></p>
        </div>
        <div class="modal-footer">
            <div class="button-container">
                <a href="<?php echo esc_url( $renew_license_url ); ?>" target="_blank" rel="noopener "
                   class="button button-primary button-large"><?php esc_html_e( 'Renew License', 'mpg' ); ?><span
                            aria-hidden="true" class="dashicons dashicons-external"></span></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=mpg-advanced-settings' ) ); ?>" target="_blank"
                   rel="noopener "
                   class="button button-secondary button-large"><?php esc_html_e( 'Activate License', 'mpg' ); ?></a>
            </div>
        </div>
    </div>
</div>

<div id="mpg_preview_all_urls" class="wp-core-ui mpg-modal" style="display:none;">
    <div class="modal-content"><button type="button" class="notice-dismiss close-modal">
            <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this dialog', 'mpg' ); ?></span>
        </button>
        <div class="modal-body">
            <table id="mpg_mpg_preview_all_urls_table" class="display" width="100%">
                <tr>
                    <td><span class="spinner is-active" style="float: none;"></span></td>
                </tr>
            </table>
        </div>
    </div>
</div>

<div id="mpg_import_export" class="wp-core-ui mpg-modal" style="display:none;">
    <div class="modal-content"><button type="button" class="notice-dismiss close-modal">
            <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this dialog', 'mpg' ); ?></span>
        </button>
        <div class="modal-header">
            <h2><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Import/Export projects is a PRO feature', 'mpg' ); ?></h2>
        </div>
        <div class="modal-body">
            <p><?php esc_html_e( 'We\'re sorry, import/export projects is not available on your plan. Please upgrade to the Pro plan to unlock all these features and enhance your product fields management capabilities.', 'mpg' ); ?></p>
        </div>
        <div class="modal-footer">
            <div class="button-container"><a href="<?php echo esc_url(mpg_app()->get_upgrade_url('import-export')); ?>" target="_blank" rel="noopener " class="button button-primary button-large"><?php esc_html_e( 'Upgrade to PRO', 'mpg' ); ?><span aria-hidden="true" class="dashicons dashicons-external"></span></a></div>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // Function to open the modal
        function openModal() {
            $('#mpg-modal').show();
        }

        // Function to close the modal
        function closeModal(e) {
            $(e)
            .parents('div.wp-core-ui')
            .hide();
        }

        // Open modal when a button is clicked
        $('.mpg-clone-btn-pro').on('click', function() {
            openModal();
        });
        // Open modal when a button is clicked
        $('.mpg-edit-btn-pro').on('click', function() {
            $('#mpg-modal-edit').show();
        });

        // Close modal when close button or overlay is clicked
        $('.close-modal').on('click', function() {
            closeModal(this);
        });

        // Close modal when Esc key is pressed
        $(document).on('keyup', function(e) {
            if (e.key === "Escape") closeModal();
        });

        // Preview urls.
        $(document).on('click', '.mpg-preview-urls', function(e) {
            e.preventDefault();

            jQuery('#mpg_preview_all_urls').show();
            var projectId = $(this).data('project_id');
            const previewTabTableContainer = jQuery('#mpg_mpg_preview_all_urls_table');

            const initObject = {
                serverSide: true,
                columns: [{ 'title': 'mpg_url' }],
                retrieve: true,
                ajax: {
                    "url": `${ajaxurl}?action=mpg_preview_all_urls&projectId=${projectId}&securityNonce=<?php echo wp_create_nonce( MPG_BASENAME ); ?>`,
                    "type": "POST",
                }
            };
            // Перед тем как отрисовать новую таблицу, сначала удалим старую
            previewTabTableContainer.DataTable(initObject).clear().destroy();
            previewTabTableContainer.empty();
            previewTabTableContainer.DataTable(initObject);
        });

        $('.mpg-export-import-btn-pro, .mpg-export-btn-pro').on('click', function(e) {
            e.preventDefault();
            jQuery('#mpg_import_export').show();
        });

        $(document).on('click', '#mpg_import:not(.mpg-export-import-btn-pro)', function(e) {
            e.preventDefault();
            jQuery('.mpg-import-field').toggleClass('hidden');
        });
    });
    document.addEventListener('DOMContentLoaded', function() {
        let url = new URL(window.location.href);
        if (url.searchParams.has('imported')) {
            url.searchParams.delete('imported');
            history.replaceState(history.state, '', url.href);
        }
    });
</script>

<style>
    .mpg-modal {
        position: fixed;
        z-index: 100000;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;

        overflow-x: hidden;
        overflow-y: auto;
        background: rgba(0,0,0,0.7);
    }
    select[name="mpg_mpg_preview_all_urls_table_length"]{
        width: 50px;
    }
    .mpg-modal .modal-content {
        position: relative;
        background: #fff;
        padding: 20px;
        border-radius: 3px;
        max-width: 500px;
        width: auto;
        margin: 1.75rem auto  ;
    }
    .mpg-modal .modal-body {
        text-align: center;
    }
    .mpg-modal .modal-header {
        padding-bottom: 10px;
        margin-bottom: 10px;
        position: relative;
    }
    .mpg-modal .modal-header .dashicons {
        font-size: 1.3em;
        line-height: inherit;
    }
    .mpg-modal .modal-header h2 {
        text-align: center;
    }
    .mpg-modal .close-modal {
        position: absolute;
        top: 0;
        right: 0;
    }
    .mpg-modal .modal-footer .dashicons{

        vertical-align: middle;
        font-size: initial;
    }
    .mpg-modal .modal-footer {
        padding-top: 10px;
        margin-top: 10px;
        text-align: center;
    }
    #mpg_preview_all_urls .modal-content{
        max-width: 800px;
        padding: 32px 20px;
    }
    #mpg_preview_all_urls table.dataTable th, #mpg_preview_all_urls table.dataTable td{
        text-align: left;
    }
    #mpg_preview_all_urls .dataTables_wrapper .dataTables_info{
        padding-top: 19px;
    }
    #mpg_preview_all_urls .dataTables_wrapper .dataTables_paginate{
        padding-top: 12px;
    }
    .wp-core-ui .mpg-export-import-btn {
        display: inline-flex !important;
        align-items: center;
    }
    .mpg-header-action {
        display: inline-flex !important;
        gap: 8px;
    }
    .mpg-export-import-btn-pro {
        opacity: 0.5;
    }
    .mpg-import-field {
        background-color: #fff;
        max-width: 400px;
        width: 100%;
        margin: 0 auto;
        position: relative;
        padding: 15px;
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
    }
    .mpg-import-field.hidden {
        display: none;
    }
</style>