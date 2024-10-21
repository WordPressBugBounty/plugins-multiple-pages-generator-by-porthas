<div class="tab-pane main-tabpane" id="sitemap" role="tabpanel" aria-labelledby="sitemap-tab">
    <div class="mpg-container d-flex align-items-start">
        <div class='main-inner-content'>
            <div class="card w-100 p-0 m-0 mb-4 mpg-card">
                <div class="card-header">
                    <h2 class="project-name-header"><?php _e('Sitemap', 'mpg'); ?></h2>
                </div>
                <div class="card-body">
                    <form method="post" id="sitemap-form">
                        <div class="sub-section">
                            <div class="block-with-tooltip">
                                <div class="left">
                                    <?php _e('File name', 'mpg'); ?>
                                    <div class="tooltip-circle" data-tippy-content="<?php _e('Name your file list. MPG will append .xml at the end.', 'mpg');?>">
                                        <span class="dashicons dashicons-info-outline"></span>
                                    </div>
                                </div>
                                <div class="right">
                                    <input type="text" class="input-data" name="sitemap_filename_input" required placeholder="multipage-sitemap" value="multipage-sitemap">
                                </div>
                            </div>
                            <div class="block-with-tooltip">
                                <div class="left">
                                    <?php _e('Max URLs per sitemap file', 'mpg'); ?>
                                    <div class="tooltip-circle" data-tippy-content="<?php _e('This allows you to break a very large sitemap file into a main sitemap with submaps. Typically not required though some SEOs have different preferences.', 'mpg');?>">
                                        <span class="dashicons dashicons-info-outline"></span>
                                    </div>
                                </div>
                                <div class="right">
                                    <input class="input-data" type="number" min="1" step="1" value="50000" required name="sitemap_max_urls_input">
                                </div>
                            </div>
                            <div class="block-with-tooltip">
                                <div class="left">
                                    <?php _e('Frequency', 'mpg'); ?>
                                    <div class="tooltip-circle" data-tippy-content="<?php _e('Tell search engine how frequently you expect to update the pages. This setting typically doesn’t carry a lot of wait unless the content is cornerstone.', 'mpg');?>">
                                        <span class="dashicons dashicons-info-outline"></span>
                                    </div>
                                </div>
                                <div class="right">
                                    <select name="sitemap_frequency_input" class="input-data" required>
                                        <option value="always"><?php _e('Always', 'mpg'); ?></option>
                                        <option value="hourly"><?php _e('Hourly', 'mpg'); ?></option>
                                        <option value="daily"><?php _e('Daily', 'mpg'); ?></option>
                                        <option value="weekly"><?php _e('Weekly', 'mpg'); ?></option>
                                        <option value="monthly"><?php _e('Monthly', 'mpg'); ?></option>
                                        <option value="yearly"><?php _e('Yearly', 'mpg'); ?></option>
                                        <option value="never"><?php _e('Never', 'mpg'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="block-with-tooltip">
                                <div class="left">
                                    <?php esc_html_e( 'Priority', 'mpg' ); ?>
                                    <div class="tooltip-circle" data-tippy-content="<?php esc_attr_e( 'This allows you to set the priority attribute value.', 'mpg' );?>">
                                        <span class="dashicons dashicons-info-outline"></span>
                                    </div>
                                </div>
                                <div class="right">
                                    <input type="text" name="sitemap_priority" value="1" class="input-data">
                                </div>
                            </div>
                            <div class="block-with-tooltip">
                                <div class="left">
                                    <?php _e('Add sitemap to robots.txt', 'mpg'); ?>
                                    <div class="tooltip-circle" data-tippy-content="<?php _e('MPG can automatically add the sitemap file location to your robots.txt to make it easier for search engines to find.', 'mpg');?>">
                                        <span class="dashicons dashicons-info-outline"></span>
                                    </div>
                                </div>
                                <div class="right">
                                    <div class="form-check form-switch">
                                        <label class="form-check-label" for="robottext">
                                            <input type="checkbox" name="sitemap_robot" value="1" id="robottext">
                                            <small></small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr/>
                        <div class="save-changes-block">
                            <input type="submit" class="generate-sitemap btn btn-primary"
                                value="<?php _e('Save and generate', 'mpg'); ?>" />
                            <div class="sitemap-status">
                                <?php _e('Current sitemap:', 'mpg'); ?> <span id="mpg_sitemap_url"></span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="sidebar-container">
            <?php require_once('sidebar.php') ?>
        </div>
    </div>
</div>