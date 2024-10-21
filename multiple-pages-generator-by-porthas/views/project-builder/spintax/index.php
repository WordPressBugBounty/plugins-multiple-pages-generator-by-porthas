 <div class="tab-pane main-tabpane" id="spintax" role="tabpanel" aria-labelledby="spintax-tab">
    <div class="mpg-container d-flex align-items-start spintax-page">
        <div class='main-inner-content'>
            <div class="card w-100 p-0 m-0 mb-4 mpg-card">
                <div class="card-header">
                    <h2 class="project-name-header">
                        <?php _e('Spintax', 'mpg'); ?>
                    </h2>
                </div>
                <div class="card-body">
                    <div class="sub-section">
                        <div class="spin-condition p-0 m-0">
                            <?php _e('Enter Spintax conditions here, and click to Spin button to get results', 'mpg'); ?>
                        </div>
                        <div class="textarea-block">
                            <textarea class="input-data m-0" id="mpg_spintax_input_textarea">{At {Themeisle|our team|our company}, we specialize in providing {cutting-edge|innovative|user-friendly} tools like the MPG plugin, designed to {enhance|improve|boost} your website's {SEO|visibility|performance} while {accelerating|speeding up|simplifying} page creation.|Our {dedicated|experienced|skilled} developers are {committed|focused|devoted} to {delivering|offering|providing} {solutions|tools|features} that {empower|enable|assist} website owners and agencies in creating {hundreds|thousands|multiple} SEO-optimized pages {quickly|efficiently|without hassle}.|With MPG, you can {trust|count on|rely on} us to {take your website|elevate your online presence|streamline your content strategy} to the next level.}

{Feel free to {contact|reach out to|get in touch with} us today to {learn more|discover|find out} how MPG can {support|help|enhance} your website's {growth|SEO efforts|page creation process}.|We {encourage|invite|welcome} you to {connect with us|contact us|reach out} to {explore|discuss|learn about} the benefits of using MPG for {your projects|client websites|business needs}.|Don't hesitate to {contact|reach out|get in touch} for a {consultation|detailed discussion|demo} on how MPG can {revolutionize|transform|improve} your {SEO strategy|page building process|website management}.}

                        </textarea>
                        </div>
                        <div class="spin-message" id="mpg_spintax_output_textarea">
                            <?php _e('Click Spin button to see result', 'mpg'); ?>
                        </div>
                        <div class="example-shortcode">
                            <?php _e('This is example of results string, that will we shown instead of [mpg_spintax] shortcode', 'mpg'); ?>
                        </div>
                        <div class="save-changes-block p-0">
                            <div class="mpg-spin-btn">
                                <input type="button" id="mpg_spin" class="btn btn-primary" value="<?php _e('Spin!', 'mpg'); ?>" />
                                <span class="spinner"></span>
                            </div>
                            <input type="button" class="copy-spintax-output btn btn-link" value="<?php _e('Copy expression', 'mpg'); ?>" />
                        </div>
                        <hr/>
                        <div class="spin-condition pt-3 m-0">
                            <?php _e('What is Spintax and How Can You Use It?', 'mpg'); ?>
                        </div>
                        <div class="spin-message" id="mpg_spintax_output_textarea">
                            <p><?php esc_html_e( 'Spintax allows you to create dynamic, unique content by using variations of words and phrases. This is especially useful when generating multiple pages using MPG, as it helps to avoid duplicate content, making each page unique and improving SEO.', 'mpg' ); ?></p>
                            <p><?php esc_html_e( 'By utilizing Spintax in your templates, you can automatically generate content that varies across pages, offering better user experiences and search engine performance.', 'mpg' ); ?></p>
                            <p><?php esc_html_e( 'For example: {hello|hi|hey} would generate "hello," "hi," or "hey" depending on the configuration. This flexibility ensures that your content feels personalized and varied across different instances.', 'mpg' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card w-100 p-0 m-0 mb-4 mpg-card">
                <div class="card-header">
                    <h2 class="project-name-header">
                        <?php _e('Cache', 'mpg'); ?>
                    </h2>
                </div>
                <div class="card-body">
                    <div class="sub-section">
                        <div class="cache-content">
                            <?php _e('When users go to one of generated URL at first time, MPG generate and save in cache (i.e. database) some string, according to provided Spintax expression.', 'mpg'); ?>
                            <?php _e('Each time, when users visit the same URL again, Spintax string is retrieved from the cache, and will not be generated again until you clear cache.', 'mpg'); ?>
                        </div>
                        <div class="cache-info">
                            <button class="btn btn-danger"><?php _e('Flush cache', 'mpg') ?></button>
                            <div class="cache-records m-0">
                                <span><?php _e('Records in cache for current project:', 'mpg'); ?> <span class="num-rows"></span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sidebar-container">
            <?php require_once('sidebar.php') ?>
        </div>
    </div>
 </div>