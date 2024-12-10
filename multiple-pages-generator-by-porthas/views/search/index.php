<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_head', ['Helper', 'mpg_header_code_container']);
?>

<div class="tab-pane main-tabpane" id="search" role="tabpanel" aria-labelledby="search-tab">
    <div class='main-inner-content shadowed'>

        <div class="search-page">
            <div class="search-tab-top">
                <h2><?php _e('Search settings', 'multiple-pages-generator-by-porthas'); ?></h2>
            </div>

            <form id="mpg_search_settings_form">

                <section>
                    <p class="mpg-subtitle"><?php _e('Single search result template', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e("Paste HTML code of single result on a search results page, and replace static text to shortcodes presented below", 'multiple-pages-generator-by-porthas'); ?></p>

                    <textarea id="mpg_search_settings_result_template" style="width: 100%" rows="5"></textarea>
                    <p><?php _e('Supported shortcodes:', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p> <mark>{{mpg_page_title}}</mark>
                        <mark>{{mpg_page_excerpt}}</mark>
                        <mark>{{mpg_page_author_nickname}}</mark>

                        <mark>{{mpg_page_author_email}}</mark>
                        <mark>{{mpg_page_author_url}}</mark>

                        <mark>{{mpg_page_url}}</mark>
                        <mark>{{mpg_page_date}}</mark>
                        <mark>{{mpg_featured_image_url}}</mark>
                    </p>
                    <p>
                    <?php
                        echo wp_kses(
                            __( 'Defining the HTML structure is <span class="required">required</span>: leaving this area empty will result in MPG-generated pages not appearing in search results.', 'multiple-pages-generator-by-porthas' ),
                            array(
                                'span' => array(
                                    'class' => true,
                                ),
                            )
                        );
                        ?>
                        <a href="<?php echo esc_url( 'https://docs.themeisle.com/article/1461-how-to-search-through-generated-pages' ); ?>" target="_blank"><?php esc_html_e( 'Documentation', 'multiple-pages-generator-by-porthas' ); ?></a>
                    </p>
                </section>

                <section>
                    <p class="mpg-subtitle"><?php _e('Text before MPG search results (optional)', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e('Will not appear if no one generated pages found', 'multiple-pages-generator-by-porthas'); ?></p>
                    <textarea id="mpg_ss_intro_content"  style="width: 100%" rows="2"></textarea>
                </section>

                <section>
                    <p class="mpg-subtitle"><?php _e('Search results block selector', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e('Set selector for block, that will be used as a container for the results from MPG', 'multiple-pages-generator-by-porthas'); ?></p>
                    <input type="text" id="mpg_ss_results_container">
                </section>

                <section>
                    <p class="mpg-subtitle"><?php _e('Is it case sensitive search?', 'multiple-pages-generator-by-porthas'); ?></p>
                    <input type="checkbox"  id="mpg_ss_is_case_sensitive">
                </section>

                <section>
                    <p class="mpg-subtitle"><?php _e('Featured image header name', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e('Use this field to set header name, where is located image URL, that you want to set to each search result, like a mpg_featured_image_url', 'multiple-pages-generator-by-porthas'); ?></p>
                    <input type="text" id="mpg_ss_featured_image_url">
                </section>

                <section>
                    <p class="mpg-subtitle"><?php _e('Excerpt length', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e('Use these setting to set max words in post the search results.', 'multiple-pages-generator-by-porthas'); ?></p>
                    <input type="number" min="0" id="mpg_ss_excerpt_length">
                </section>

                <section>
                    <p class="mpg-subtitle"><?php _e('Max results count on a page', 'multiple-pages-generator-by-porthas'); ?></p>
                    <p style="margin-top: 1rem;"><?php _e('Use these setting to set how many pages \ posts should displayed on a search results page.', 'multiple-pages-generator-by-porthas'); ?></p>
                    <input type="number" min="1" id="mpg_ss_results_count">
                </section>

                <button type="submit" style="margin: 25px" class="btn btn-primary"><?php _e("Update", 'multiple-pages-generator-by-porthas'); ?></button>
            </form>
        </div>
    </div>
</div>

<div class="sidebar-container">
    <?php require_once('sidebar.php') ?>
</div>
</div>