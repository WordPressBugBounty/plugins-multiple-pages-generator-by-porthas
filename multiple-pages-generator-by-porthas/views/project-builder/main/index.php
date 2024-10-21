<div class="tab-pane main-tabpane active in" id="main" role="tabpanel" aria-labelledby="main-tab">
    <div class="mpg-container d-flex align-items-start">
        <div class="main-inner-content">
            <div class="accordion accordion-group" id="accordions">
                <?php $is_pro = mpg_app()->is_premium(); ?>
                <!-- Project Name -->
                <div class="mpg-project-name">
                    <p><?php esc_html_e('Project name', 'mpg'); ?> <span id="mpg-id-block" class="d-none"></span></p>
                    <div class="sub-section">
                        <input type="text" class="project-name input-data" required placeholder="<?php esc_html_e('Your Project Name', 'mpg'); ?>">
                    </div>
                </div>
                <!-- Source -->
                <div class="accordion-pane mpg-card">
                    <div data-parent="#accordions" class="card-header d-flex align-items-center flex-wrap justify-content-between" data-toggle="collapse" data-target="#collapse_1" aria-expanded="false" aria-controls="collapse_1">
                        <h2>
                            <div class="card-step"><?php esc_html_e( 'Step 1', 'mpg' ); ?></div>
                            <?php esc_html_e('Select source', 'mpg'); ?>
                        </h2>
                        <div class="collapse-actions d-flex align-items-center">
                            <span class="dashicons "></span>

                        </div>
                    </div>
                    <div id="collapse_1" class="collapse show" aria-labelledby="collapse_1" data-parent="#accordion">
                        <div class="card-body">
                            <div class="tab-content">
                                <div class="sub-section pb-0">
                                    <div class="block-with-tooltip">
                                        <div class="left">
                                            <?php esc_html_e('Source Type', 'mpg'); ?>
                                        </div>
                                        <div class="right">
                                            <select class="input-data select-source-option">
                                                <option value="direct_link"><?php esc_html_e('Direct link', 'mpg') ?></option>
                                                <option value="upload_file"><?php esc_html_e('Upload file', 'mpg') ?></option>
                                            </select>
                                            <div class="help-text">
                                                <?php esc_html_e( 'Choose to upload content from a file or sync it from a link.', 'mpg' ); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <hr/>
                                </div>
                                <div class="mt-0" id="direct_link" role="tabpanel" aria-labelledby="direct_link-tab">
                                    <form class="direct-link-schedule-form" style="width:100%">
                                        <div class="sub-section">
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php esc_html_e('Direct link to source file', 'mpg'); ?>
                                                    <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Load any Google Sheet or csv that’s available on the internet. Make sure the file has public access.', 'mpg') ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <input type="url" name="direct_link_input" class="input-data" required="required" placeholder="<?php esc_html_e('https://', 'mpg'); ?>">
                                                    <div class="help-text">
                                                        <?php esc_html_e( 'Make sure the file has public access.', 'mpg' ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip worksheet-id">
                                                <div class="left">
                                                    <?php esc_html_e('Worksheet ID (optional)', 'mpg'); ?>
                                                    <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Copy and paste the worksheet ID from Google Sheets here. If you leave this field empty, the first sheet will be used automatically.', 'mpg') ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <input type="number" name="worksheet_id" class="input-data disabled" placeholder="<?php esc_html_e('Like a 123456789', 'mpg'); ?>">
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php $is_higher_plan = mpg_app()->is_license_of_type( 2 ); ?>
                                                    <div class="d-flex align-items-center gap-5">
                                                        <?php esc_html_e('Sync frequency', 'mpg'); ?>
                                                        <?php echo ! $is_higher_plan ? '<span class="pro-field">Pro</span>' : ''; ?>
                                                        <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Set how often MPG will fetch the dataset above.', 'mpg'); ?>">
                                                            <span class="dashicons dashicons-info-outline"></span>
                                                        </div>
                                                    </div>
                                                    <?php if ( ! $is_higher_plan ) : ?>
                                                    <div class="pro-feature-read-more">
                                                        <?php esc_html_e( 'This is a PRO Feature.', 'mpg' ); ?>
                                                        <a href="<?php echo esc_url( mpg_app()->get_upgrade_url('SyncFrequency' ) ); ?>" target="_blank">
                                                            <?php esc_html_e( 'Learn More', 'mpg' ); ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="right">
                                                    <select name="periodicity" <?php echo $is_higher_plan ? '' : 'disabled="true"'; ?> class="input-data disabled" required>
                                                        <option value="once"><?php esc_html_e('Manual', 'mpg'); ?></option>
                                                        <?php if ( $is_pro ) : ?>
                                                            <option value="now"><?php esc_html_e('Live', 'mpg'); ?></option>
                                                            <option value="hourly"><?php esc_html_e('Hourly', 'mpg'); ?></option>
                                                            <option value="ondemand"><?php esc_html_e('On Demand', 'mpg'); ?></option>
                                                            <option value="twicedaily"><?php esc_html_e('Twice per day', 'mpg'); ?></option>
                                                            <option value="daily"><?php esc_html_e('Daily', 'mpg'); ?></option>
                                                            <option value="weekly"><?php esc_html_e('Weekly', 'mpg'); ?></option>
                                                            <option value="monthly"><?php esc_html_e('Monthly', 'mpg'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                    <div class="help-text">
                                                        <?php esc_html_e( 'Choose how often MPG will check the dataset above for changes.', 'mpg' ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip mpg-date-changes mpg-hidden">
                                                <div class="left">
                                                    <div class="d-flex align-items-center gap-5">
                                                        <?php esc_html_e('Update Date on Data Change', 'mpg'); ?>
                                                        <?php echo ! $is_higher_plan ? '<span class="pro-field">Pro</span>' : ''; ?>
                                                        <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('When new data is synced, this option controls how the modification date is handled for the generated pages.', 'mpg'); ?>">
                                                            <span class="dashicons dashicons-info-outline"></span>
                                                        </div>
                                                    </div>
                                                    <?php if ( ! $is_higher_plan ) : ?>
                                                    <div class="pro-feature-read-more">
                                                        <?php esc_html_e( 'This is a PRO Feature.', 'mpg' ); ?>
                                                        <a href="<?php echo esc_url( mpg_app()->get_upgrade_url('SyncFrequency' ) ); ?>" target="_blank">
                                                            <?php esc_html_e( 'Learn More', 'mpg' ); ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="right">
                                                    <select name="update_modified_on_sync" class="input-data " >
                                                            <option value="no"><?php esc_html_e('Don\'t update the date', 'mpg'); ?></option>
                                                            <option value="onsync"><?php esc_html_e('Update all dates on sync ', 'mpg'); ?></option>
                                                            <option value="column"><?php esc_html_e('Update individual dates on sync', 'mpg'); ?></option>
                                                    </select>
                                                    <div class="help-text">
                                                        <?php esc_html_e( 'Update the modification date of generated pages when data changes?', 'mpg' ); ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="block-with-tooltip d-none"  id="mpg-webhook-url">
                                                <div class="left">
                                                    <div class="d-flex align-items-center gap-5">
                                                        <p><?php _e('Webhook URL', 'mpg'); ?></p>

                                                    </div>
                                                    <?php if ( ! $is_higher_plan ) : ?>
                                                    <div class="pro-feature-read-more">
                                                        <?php esc_html_e( 'This is a PRO Feature.', 'mpg' ); ?>
                                                        <a href="<?php echo esc_url( mpg_app()->get_upgrade_url('SyncFrequency' ) ); ?>" target="_blank">
                                                            <?php esc_html_e( 'Learn More', 'mpg' ); ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="right">
                                                    <code><?php echo esc_url( MPG_Helper::get_webhook_url( sanitize_text_field( $_GET['id'] ?? '' ) ) ) ?></code>
                                                    <div class="help-text">
	                                                    <?php esc_html_e('To trigger MPG to fetch new data, send a POST request to the provided webhook URL.', 'mpg'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip d-none sync-options" id="mpg-sync-first">
                                                <?php $is_higher_plan = mpg_app()->is_license_of_type( 2 ); ?>
                                                <input type="hidden" name="mpg_timezone_name" value="">
                                                <div class="left">
                                                    <?php esc_html_e('First Fetch Date/Time for Sync', 'mpg'); ?>
                                                    <?php echo ! $is_higher_plan ? '<span class="pro-field">Pro</span>' : ''; ?>
                                                    <div class="tooltip-circle tooltip-align" data-tippy-content="<?php esc_html_e('Set the date and time when MPG should first attempt to fetch your file.', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                    <?php if ( ! $is_higher_plan ) : ?>
                                                    <div class="pro-feature-read-more">
                                                        <?php esc_html_e( 'This is a PRO Feature.', 'mpg' ); ?>
                                                        <a href="<?php echo esc_url( mpg_app()->get_upgrade_url('FirstFetch' ) ); ?>" target="_blank">
                                                            <?php esc_html_e( 'Learn More', 'mpg' ); ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="right">
                                                    <div class="d-flex align-items-center gap-15">
                                                        <input class="disabled input-data" name="datetime_upload_remote_file" <?php echo  $is_higher_plan ? '' : 'disabled="true"'; ?> type="text" autocomplete="off">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip d-none sync-options">
                                                <div class="left">
                                                    <div class="d-flex align-items-center gap-5">
                                                        <?php esc_html_e('Notification', 'mpg'); ?>
                                                        <?php echo ! $is_higher_plan ? '<span class="pro-field">Pro</span>' : ''; ?>
                                                        <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('MPG can send a notification each time after it fetches your dataset. It can be on error or every time it fetches.', 'mpg'); ?>">
                                                            <span class="dashicons dashicons-info-outline"></span>
                                                        </div>
                                                    </div>
                                                    <?php if ( ! $is_higher_plan ) : ?>
                                                    <div class="pro-feature-read-more">
                                                        <?php esc_html_e( 'This is a PRO Feature.', 'mpg' ); ?>
                                                        <a href="<?php echo esc_url( mpg_app()->get_upgrade_url('Notification' ) ); ?>" target="_blank">
                                                            <?php esc_html_e( 'Learn More', 'mpg' ); ?>
                                                        </a>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="right">
                                                    <select class="disabled input-data" name="notification_level" <?php echo  $is_higher_plan ? '' : 'disabled="true"'; ?>>
                                                        <option value="do-not-notify"><?php esc_html_e('Do not notify', 'mpg'); ?></option>
                                                        <option value="errors-only"><?php esc_html_e('Errors only', 'mpg'); ?></option>
                                                        <option value="every-time"><?php esc_html_e('Every time', 'mpg'); ?></option>
                                                    </select>
                                                    <div class="help-text">
                                                        <?php esc_html_e( 'Receive helpful notifications each time MPG fetches your source.', 'mpg' ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip d-none nf-options" style="margin-bottom:20px;align-items: baseline;">
                                                <div class="left">
                                                    <?php esc_html_e('Notification Email', 'mpg'); ?>
                                                    <?php echo ! $is_higher_plan ? '<span class="pro-field">Pro</span>' : ''; ?>
                                                    <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Specify the email which we shall use to notify you, if opted in.', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <div class="d-flex align-items-center gap-15 mb-3">
                                                        <input class="disabled input-data" name="notification_email" <?php echo  $is_higher_plan ? '' : 'disabled="true"'; ?> type="email" value="<?php echo get_option('admin_email'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip mpg-next-cron" style="display: none;">
                                                <div class="left">
                                                    <div id="mpg_next_cron_execution"></div>
                                                </div>
                                                <div class="right">
                                                   <input type="button" id="mpg_unschedule_task" value="<?php esc_html_e('Unschedule', 'mpg'); ?>" class="btn btn-danger">
                                                </div>
                                            </div>
                                            <div class="save-changes-block p-0">
                                                <button type="submit" class=" blue-gradient-btn btn btn-primary use-direct-link-button disabled"><?php esc_html_e('Fetch and use', 'mpg'); ?></button>
                                                <span class="spinner"></span>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="mt-0" id="upload_file" role="tabpanel" aria-labelledby="upload_file-tab" style="display: none">
                                    <form action="">
                                        <div class="sub-section">
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php esc_html_e('Choose .csv, .xlsx or .ods file from your computer', 'mpg'); ?>
                                                </div>
                                                <div class="right">
                                                    <div class="custom-file mpg_upload_file">
                                                        <div class="col-sm-9">
                                                            <input type="file" name="mpg_upload_file_input" accept=".csv, .ods, .xlsx" class="custom-file-input" id="mpg_upload_file_input" aria-describedby="inputGroupFileAddon04">
                                                            <label class="custom-file-label mpg_upload_file-label" for="mpg_upload_file_input"><?php esc_html_e('Click to browse', 'mpg'); ?></label>
                                                        </div>
                                                        <a class="col-ms-3 btn disabled btn-outline-primary" id="mpg_in_use_dataset_link" target="_blank" download>N/A</a>
                                                    </div>
                                                    <div id="progress-wrp">
                                                        <div class="progress">
                                                            <div class="progress-bar progress-bar-striped bg-success" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Data Preview -->
                <div class="accordion-pane mpg-card">
                    <div data-parent="#accordions" class="card-header d-flex align-items-center justify-content-between" data-toggle="collapse" data-target="#collapse_2" aria-expanded="true" aria-controls="collapse_2">
                        <h2>
                            <div class="card-step"><?php esc_html_e( 'Step 2', 'mpg' ); ?></div>
                            <?php esc_html_e('Preview data', 'mpg') ?>
                        </h2>
                        <div class="collapse-actions d-flex align-items-center">
                            <span class="dashicons "></span>

                        </div>
                    </div>
                    <div id="collapse_2" class="collapse show" aria-labelledby="collapse_2" data-parent="#accordion">
                        <div class="card-body">
                            <div class="modal bd-example-modal-lg" id="mpg_preview_modal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-body">
                                            <table id="mpg_data_full_preview_table" class="display responsive nowrap" width="100%">
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="sub-section data-table-container">
                                <table valign="middle" id="mpg_dataset_limited_rows_table" class="table table-bordered table-hover dt-responsive" width="100%">
                                    <tr>
                                        <td valign="middle" class="vertical-align">
                                            <div class="inside-table text-center d-flex items-center justify-content-center h-100">
                                                Data preview will appear here
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="sub-section pt-0 d-none">
                            <div class="w-100 d-flex align-items-center justify-content-between gap-15">
                                <p class="mb-0 summary"><?php esc_html_e('[rows] rows / [headers] headers', 'mpg'); ?></p>
                                <a href="#" id="mpg_preview_modal_link"><?php esc_html_e('Preview all Data', 'mpg') ?></a>
                            </div>
                        </div>
                        <?php if ( ! $is_pro && ! mpg_app()->is_legacy_user()) : ?>
                        <div class="sub-section pt-0 pb-0">
                            <div class="alert alert-primary" role="alert">
                                <div class="icon">
                                    <span class="dashicons dashicons-info-outline"></span>
                                </div>
                                <div class="info">
	                                <?php echo sprintf( esc_html__( 'Your current plan allows processing up to 50 rows from your source. %sUpgrade%s to the PRO version for unlimited row processing and unlock the full potential of your data.', 'mpg' ), '<br/><a href="' . mpg_app()->get_upgrade_url( 'higher-limit' ) . '" target="_blank" rel="noreferrer">', '</a>' ); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Project Setting -->
                <div class="accordion-pane mpg-card">
                    <div data-parent="#accordions" class="card-header d-flex align-items-center flex-wrap justify-content-between" data-toggle="collapse" data-target="#collapse_3" aria-expanded="false" aria-controls="collapse_3">
                        <h2>
                            <div class="card-step"><?php esc_html_e( 'Step 3', 'mpg' ); ?></div>
                            <?php esc_html_e('Project settings', 'mpg'); ?>
                        </h2>
                        <div class="collapse-actions">
                            <span class="dashicons "></span></a>

                        </div>
                    </div>
                    <div id="collapse_3" class="collapse show" aria-labelledby="collapse_3" data-parent="#accordion">
                        <div class="card-body">
                            <form class="main-template-info">
                                <div class="sub-section pb-0">
                                    <ul class="nav nav-tabs" role="tablist" id="general-advance-setting">
                                        <li class="nav-item" role="presentation">
                                            <a href="#general-setting" class="nav-link active" data-toggle="tab" data-target="#general-setting" type="button" role="tab" aria-controls="general-setting" aria-selected="true">
                                                <?php esc_html_e( 'General', 'mpg' ); ?>
                                            </a>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <a href="#advance-setting" class="nav-link" data-toggle="tab" data-target="#advance-setting" type="button" role="tab" aria-controls="advance-setting" aria-selected="false">
                                                <?php esc_html_e( 'Advanced settings', 'mpg' ); ?>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="tab-content">
                                    <div class="tab-pane fade show active m-0" id="general-setting" role="tabpanel" aria-labelledby="general-setting">
                                        <div class="sub-section">
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php esc_html_e('Entity type', 'mpg'); ?>
                                                    <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Select the type of content for your template. MPG supports all entity types in your Wordpress installation, including posts and pages.', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <select class="input-data" id="mpg_entity_type_dropdown" style="width:100%" required>
                                                        <option value="" disabled="disabled" selected="selected"><?php esc_html_e('Choose entity type', 'mpg'); ?></option>
                                                        <?php
                                                        foreach ($entities_array as $entity) {
                                                            echo '<option value="' . esc_textarea($entity['name']) . '">' . esc_textarea($entity['label']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <hr/>
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php esc_html_e('Template', 'mpg'); ?>
                                                    <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Select the entity you wish to use as a template for the generated content. MPG will replace any shortcodes when accessing the site through generated URL accordingly to your source file.', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right d-flex align-items-center">
                                                    <div class="col-sm-12 pl-0 pr-0">
                                                        <select class="input-data" id="mpg_set_template_dropdown" required>
                                                            <option value="" disabled="disabled" selected="selected" >
                                                                <?php esc_html_e('Choose template', 'mpg'); ?>
                                                            </option>
                                                        </select>
                                                    </div>
                                                    <a class="col-ms-3 btn btn-outline-primary disabled d-none" id="mpg_edit_template_link" href="#" data-edit_link="<?php echo esc_url( add_query_arg( array( 'post' => '#id#', 'action' => 'edit' ), admin_url( 'post.php' ) ) ); ?>" target="_blank"><?php esc_html_e( 'Edit template', 'mpg' ); ?></a>

                                                </div>
                                                <div class="help-text" >
	                                                <?php echo sprintf( esc_html__( 'Learn step-by-step how to set up and use the template page in Multi Pages Generator, including dynamic variables, visibility rules, and loop builder. %sRead the complete guide here%s', 'mpg' ), '<a href="https://docs.themeisle.com/article/2071-how-to-use-the-template-page-in-mpg-for-programmatic-seo" target="_blank">', '</a>' ); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade m-0" id="advance-setting" role="tabpanel" aria-labelledby="advance-setting">
                                        <div class="sub-section">
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <div class="label">
                                                        <?php esc_html_e('Apply template if URL contains', 'mpg'); ?>
                                                    </div>
                                                    <div class="tooltip-circle tooltip-align" data-tippy-content="<?php esc_html_e('URLs related with the template will work ONLY if generated URL will contain specified part. Like a /en/ or ?lang=it', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <input type="text" id="mpg_apply_condition" maxlength="199" class="input-data" placeholder="<?php esc_html_e('Like a ?lang=en or /en/ (optional)', 'mpg'); ?>">
                                                </div>
                                            </div>
                                            <hr/>
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <div class="label">
                                                        <?php esc_html_e('Hide this template from search engines and your site content', 'mpg'); ?>
                                                    </div>
                                                    <div class="tooltip-circle tooltip-align" data-tippy-content="<?php esc_html_e('It’s is highly suggested to exclude template page from being indexed by search engines as it contains shortcodes. Also, the page/post will be excluded from search results in WordPress, categories and widgets, like a Recent posts. All generated pages will remain visible.', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <div class="form-check form-switch">
                                                        <label class="form-check-label" for="mpg_exclude_template_in_robots">
                                                            <input class="input-checkbox" type="checkbox" id="mpg_exclude_template_in_robots" checked>
                                                            <small></small>
                                                        </label>
                                                    </div>
                                                    <div class="help-text">
                                                        <?php esc_html_e( 'We recommend enabling this option since the template page contains shortcodes.', 'mpg' ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <hr/>
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php esc_html_e('Include generated pages in your site search results?', 'mpg'); ?>
                                                    <a href="<?php echo esc_url( 'https://docs.themeisle.com/article/1461-how-to-search-through-generated-pages' ); ?>" target="_blank">
                                                            <?php esc_html_e( 'Learn More', 'mpg' ); ?>
                                                    </a>
                                                    <div class="tooltip-circle tooltip-align" data-tippy-content="<?php esc_html_e('Enable this option if you want the pages created with MPG to be included in the search results on your website. Disable it if you prefer these pages to remain hidden from site searches. This option works only if you configure Search Settings correctly.', 'mpg'); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <div class="form-check form-switch">
                                                        <label class="form-check-label" for="mpg_participate_in_search">
                                                            <input class="input-checkbox" type="checkbox" id="mpg_participate_in_search">
                                                            <small></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="block-with-tooltip">
                                                <div class="left">
                                                    <?php esc_html_e( 'Display generated pages in your site content listings and query loop?', 'mpg' ); ?>
                                                    <div class="tooltip-circle tooltip-align" data-tippy-content="<?php esc_attr_e( 'This option controls whether the pages created with MPG appear in your website’s content listings, such as blog archives, post lists, or other site-wide content feeds.', 'mpg' ); ?>">
                                                        <span class="dashicons dashicons-info-outline"></span>
                                                    </div>
                                                </div>
                                                <div class="right">
                                                    <div class="form-check form-switch">
                                                        <label class="form-check-label" for="mpg_participate_in_default_loop">
                                                            <input class="input-checkbox" type="checkbox" id="mpg_participate_in_default_loop">
                                                            <small></small>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Url Generate -->
                <div class="accordion-pane mpg-card">
                    <div data-parent="#accordions"  class="card-header d-flex align-items-center justify-content-between" data-toggle="collapse" data-target="#collapse_4" aria-expanded="true" aria-controls="collapse_4">
                        <h2>
                            <div class="card-step"><?php esc_html_e( 'Step 4', 'mpg' ); ?></div>
                            <?php esc_html_e('Generate URLs', 'mpg'); ?>
                        </h2>
                        <span class="collapse show dashicons "></span>
                    </div>
                    <div id="collapse_4" class="collapse show" aria-labelledby="collapse_4" data-parent="#accordion">
                        <div class="card-body">
                            <div class="sub-section">
                                    <div class="block-with-tooltip">
                                        <div class="left">
                                            <?php esc_html_e('URL Format Template', 'mpg'); ?>
                                            <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Type in the desired format of the generated URLs. MPG supports any combination of shortcodes, plain text, and separators.', 'mpg'); ?>">
                                                <span class="dashicons dashicons-info-outline"></span>
                                            </div>
                                        </div>
                                        <div class="right">
                                            <div class="d-flex align-items-center gap-15">
                                                <div id="mpg_url_constructor" class="input-data" contenteditable="false"></div>
                                                <div style="max-width: 180px; width: 100%"><select id="mpg_main_tab_insert_shortcode_dropdown" disabled></select></div>
                                            </div>
                                            <div class="help-text">
                                                <?php esc_html_e( 'Choose how the tags are used in the URL generation and preview it in the right sidebar.', 'mpg' ); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <hr />
                                    <div class="block-with-tooltip">
                                        <div class="left">
                                            <?php esc_html_e('Default separator', 'mpg'); ?>
                                            <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('The default separator will replace any spaces in your shortcodes when generating URLs. All unsupported characters will be trimmed', 'mpg'); ?>">
                                                <span class="dashicons dashicons-info-outline"></span>
                                            </div>
                                        </div>
                                        <div class="right spacers-block">
                                            <div class="spaces-replacer active">-</div>
                                            <div class="spaces-replacer">_</div>
                                            <div class="spaces-replacer">~</div>
                                            <div class="spaces-replacer">.</div>
                                            <div class="spaces-replacer">/</div>
                                            <div class="spaces-replacer">=</div>
                                        </div>
                                    </div>
                                    <hr />
                                    <div class="block-with-tooltip">
                                        <div class="left">
                                            <?php esc_html_e('Trailing slash settings', 'mpg'); ?>
                                            <div class="tooltip-circle" data-tippy-content="<?php esc_html_e('Allow to generate URLs with trailing slashes, without trailing slashes or use default mode', 'mpg'); ?>">
                                                <span class="dashicons dashicons-info-outline"></span>
                                            </div>
                                        </div>
                                        <div class="right">
                                            <div id="mpg_url_mode_group" class="btn-group">
                                                <input class="btn-check" type="radio" value="both" id="both" name="mpg_url_mode_group" checked>
                                                <label for="both" class="btn btn-outline-primary"><?php esc_html_e('Default', 'mpg'); ?></label>
                                                <input class="btn-check" type="radio" value="with-trailing-slash" id="with-trailing-slash" name="mpg_url_mode_group">
                                                <label for="with-trailing-slash" class="btn btn-outline-primary"><?php esc_html_e('With trailing slash', 'mpg'); ?></label>
                                                <input class="btn-check" type="radio" value="without-trailing-slash" id="without-trailing-slash" name="mpg_url_mode_group">
                                                <label for="without-trailing-slash" class="btn btn-outline-primary"><?php esc_html_e('Without trailing slash', 'mpg'); ?></label>
                                            </div>
                                        </div>
                                    </div>
                                    <hr />
                                    <div class="block-with-tooltip">
                                        <div class="left">
                                            <?php esc_html_e('URL preview', 'mpg'); ?>
                                        </div>
                                        <div class="right">
                                            <div id="mpg_preview_url_list"></div>
                                            <a href="#" id="mpg_preview_all_urls_link"><?php esc_html_e('See all URLs', 'mpg') ?></a>
                                        </div>
                                    </div>
                                    <div class="modal bd-example-modal-lg" id="mpg_preview_all_urls" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content">
                                                <div class="modal-body">
                                                    <table id="mpg_mpg_preview_all_urls_table" class="display" width="100%">
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-primary" role="alert">
                    <div class="icon">
                        <img src=<?php echo esc_url( MPG_BASE_IMG_PATH . '/alert-primary.png' ); ?> alt="" />
                    </div>
                    <div class="info">
                        <?php
                            echo wp_kses(
                                sprintf( __( 'The generated pages are <strong>virtual</strong> and won’t appear under the All Pages tab to prevent overloading your site.', 'mpg' ) ),
                                array(
                                    'strong' => true,
                                )
                            );
                            ?>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="save-changes-block p-0">
                        <button class="save-changes btn btn-primary disabled"><?php echo isset( $_GET['id'] ) ? esc_html__( 'Save changes', 'mpg' ) : esc_html__( 'Publish', 'mpg' ); ?></button>
                        <span class="spinner"></span>
                    </div>
                    <a href="#" class="delete-project" style="display:none;">
                        <?php esc_html_e('Delete template', 'mpg'); ?>
                    </a>
                </div>
            </div>
        <div class="sidebar-container">
            <?php require_once('sidebar.php') ?>
        </div>
    </div>
</div>