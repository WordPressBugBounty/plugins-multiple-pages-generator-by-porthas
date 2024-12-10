

<div class="block sidebar-block shadowed">

    <div class="sidebar-video-block">
        <h4><?php _e('How it works in 60 seconds.', 'multiple-pages-generator-by-porthas') ?></h4>
        <iframe height="200" style="max-height: 200px;" src="https://www.youtube.com/embed/u5l5PB1DaSc?si=sgmp6P_Yr_fx-cyH" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>

	<?php require plugin_dir_path( __FILE__ ) . '../../sites-sidebar.php'; ?>
    <div class="sidebar-block-inner-content">
        <h4><?php _e('Setting up your template', 'multiple-pages-generator-by-porthas'); ?></h4>
        <ul>
            <li>
                <div class="number">1</div>
                <p><?php _e('Name your project. Select the entity you wish to generate. Select or create a template for the selected entity to use for generation of new pages.', 'multiple-pages-generator-by-porthas');?></p>
            </li>

            <li>
                <div class="number">2</div>
                <p><?php _e('Setup your source file by either pointing to a publicly shared google doc or uploading a spreadsheet.', 'multiple-pages-generator-by-porthas');?></p>
            </li>

            <li>
                <div class="number">3</div>
                <p><?php _e('Setup URL Generation format by combining desired text with shortcodes.', 'multiple-pages-generator-by-porthas');?></p>
            </li>

            <li>
                <div class="number">4</div>
                <p><?php _e('Update your template page with shortcodes from source file. Generate sitemap and upload it in addition to your regular sitemap via Google Search Console.', 'multiple-pages-generator-by-porthas');?></p>
            </li>

            <li>
                <div class="number">5</div>
                <p><?php _e('Create in-links to new pages by generating list shortcodes in Shortcode tab to increase page authority.', 'multiple-pages-generator-by-porthas');?></p>
            </li>

        </ul>
    </div>
</div>
<?php require plugin_dir_path( __FILE__ ) . '../../sidebar-subscribe.php'; ?>
