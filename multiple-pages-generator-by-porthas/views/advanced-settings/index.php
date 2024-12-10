<div class="tab-pane main-tabpane" id="advanced" role="tabpanel" aria-labelledby="advanсed-tab">
    <div class='main-inner-content shadowed'>

        <div class="advanced-page">
            <div class="advanced-tab-top">
                <h2><?php _e('Advanced settings', 'multiple-pages-generator-by-porthas'); ?></h2>
            </div>

            <?php do_action( 'mpg_advanced_setting_before' ); ?>

            <section>
                <!-- Update database tables structure -->
                <p class="mpg-subtitle"><?php _e('Update tables structure', 'multiple-pages-generator-by-porthas'); ?></p>
                <p style="margin-top: 1rem;"><?php _e("This section allows to set up hooks which MPG will fire on, update (actualize) structure of MPG's tables after an update. The same result can be achieved by activating and deactivating the plugin", 'multiple-pages-generator-by-porthas'); ?></p>


                <button id="mpg_update_tables_structure" class="btn btn-primary"><?php _e("Update", 'multiple-pages-generator-by-porthas'); ?></button>
            </section>


            <!-- Hooks -->

            <section>
                <p class="mpg-subtitle"><?php _e('Page Builders Compatibility', 'multiple-pages-generator-by-porthas'); ?></p>
                <p style="margin-top: 1rem;"><?php _e('Use these settings only if the generated page is displayed incorrectly, there is no header, footer, or non-replaced shortcodes. MPG has a universal default setting, but sometimes it does not work properly, because different users have different plugins, builders, versions of Wordpress, and so on. Therefore, if you see problems with the pages - change these options to achieve the desired effect.', 'multiple-pages-generator-by-porthas'); ?></p>

                <p style="margin-top: 1rem;"><?php _e('As we noticed, the next configuration is working, but if no - feel free to change it to make generated pages working for you:', 'multiple-pages-generator-by-porthas'); ?></p>

                <ul style="font-size: 13px">
                    <li><?php _e('Native text editor (Gutenberg): hook name - "template_redirect", priority - high', 'multiple-pages-generator-by-porthas'); ?></li>
                    <li><?php _e('Thrive Architect: hook name - "posts_selection", priority - normal', 'multiple-pages-generator-by-porthas'); ?></li>
                    <li><?php _e('Divi pagebuilder: hook name - "posts_selection", priority - high', 'multiple-pages-generator-by-porthas'); ?></li>
                    <li><?php _e('Elementor Pro: hook name - "pre_handle_404", priority - high', 'multiple-pages-generator-by-porthas'); ?></li>
                </ul>

                <form class="mpg-hooks-block">
                    <select id="mpg_hook_name" required="required">
                        <option disabled="true" value="" selected><?php _e('Hook', 'multiple-pages-generator-by-porthas'); ?></option>
                        <option value="pre_handle_404">pre_handle_404</option>
                        <option value="posts_selection">posts_selection</option>
                        <option value="template_redirect">template_redirect</option>
                        <option value="wp">wp</option>
                    </select>

                    <select id="mpg_hook_priority" required="required">
                        <option disabled="true" value="" selected><?php _e('Priority', 'multiple-pages-generator-by-porthas'); ?></option>
                        <option value="1"><?php _e('High', 'multiple-pages-generator-by-porthas'); ?></option>
                        <option value="10"><?php _e('Normal', 'multiple-pages-generator-by-porthas'); ?></option>
                        <option value="100"><?php _e('Low', 'multiple-pages-generator-by-porthas'); ?></option>
                    </select>

                    <button type="submit" class="btn btn-primary"><?php _e('Update', 'multiple-pages-generator-by-porthas'); ?></button>
                </form>
            </section>

            <!-- ABSPATH -->
            <section>
                <p class="mpg-subtitle"><?php _e('WordPress base path', 'multiple-pages-generator-by-porthas'); ?></p>
                <p style="margin-top: 1rem;"><?php _e('Use these settings only if problems occur when generating sitemaps. Some hosting providers change value of ABSPATH constant due to security reasons, however, this prevents the plugin from working correctly.', 'multiple-pages-generator-by-porthas'); ?></p>


                <form class="mpg-path-block">
                    <select required="required">
                        <option value="abspath">ABSPATH</option>
                        <option value="wp-content">Path based on wp-content folder location</option>
                    </select>

                    <button type="submit" class="btn btn-primary"><?php _e('Update', 'multiple-pages-generator-by-porthas'); ?></button>
                </form>
            </section>



            <!-- Branding position -->
            <?php if (!mpg_app()->is_premium()) { ?>
                <section>
                    <p class="mpg-subtitle"><?php _e('Branding position', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e('Use this setting if you want to move branding block to another side of a page', 'multiple-pages-generator-by-porthas'); ?></p>

                    <form class="mpg-branding-position-block">
                        <select id="mpg_change_branding_position" required="required">
                            <option value="right">Right</option>
                            <option value="left">Left</option>
                        </select>

                        <button type="submit" class="btn btn-primary"><?php _e('Update', 'multiple-pages-generator-by-porthas'); ?></button>
                    </form>
                </section>
            <?php } ?>

            <!-- Help us improve -->
            <section>
                <p class="mpg-subtitle"><?php esc_html_e( 'Help us improve', 'multiple-pages-generator-by-porthas' ); ?></p>
                <form class="mpg-help-us-improve">
                    <label style="font-size: 13px;margin-top: 10px;">
                        <input type="checkbox" name="mpg_enable_telemetry" value="1" <?php echo ( 'yes' === get_option('multi_pages_plugin_logger_flag', false) ) ? 'checked' : ''; ?> />
                        <?php esc_html_e( 'Send data about plugin settings to measure the usage of the features. The data is private and not shared with third-party entities. Only plugin data is collected without sensitive information.', 'multiple-pages-generator-by-porthas' ); ?>
                    </label>
                </form>
            </section>
        </div>

    </div>
</div>

<div class="sidebar-container">
    <?php require_once('sidebar.php') ?>
</div>
</div>