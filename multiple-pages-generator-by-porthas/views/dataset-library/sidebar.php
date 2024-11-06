<div class="">  

    <div class="block sidebar-block shadowed">

        <div class="sidebar-video-block">
			<h4><?php _e('How it works in 60 seconds.', 'mpg') ?></h4>
			<iframe  height="200" style="max-height: 200px;" src="https://www.youtube.com/embed/u5l5PB1DaSc?si=sgmp6P_Yr_fx-cyH" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>

	    <?php require_once plugin_dir_path( __FILE__ ) . '../sites-sidebar.php'; ?>
        <div class="sidebar-block-inner-content">
        <h4><?php _e('Where to start with MPG', 'mpg'); ?></h4>
        <ul>
            <li>
                <div class="number">1</div>
                <p><?php _e('Select a template to try or build up from or if you are experienced user - start "From scratch".', 'mpg');?></p>
            </li>

            <li>
                <div class="number">2</div>
                <p><?php _e('Set or modify your template entity. Load or modify source URL/file. Adjust URL generation format accordingly to your needs. Add shortcodes from source file to your template to generate unique content.', 'mpg');?></p>
            </li>

            <li>
                <div class="number">3</div>
                <p><?php _e('Enjoy! Don’t forget that editing these pages is as easy as modifying your template file and/or data source.', 'mpg');?></p>
            </li>
        </ul>
        </div>
    </div>
	<?php require_once plugin_dir_path( __FILE__ ) . '../sidebar-subscribe.php'; ?>

</div>
