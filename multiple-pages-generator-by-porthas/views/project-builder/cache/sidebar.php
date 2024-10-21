
<div class="block sidebar-block shadowed">
    <div class="sidebar-block-inner-content">
        <h4><?php _e('Use cache to speed up page loading.', 'mpg') ?></h4>

        <ul>
            <li>
                <div class="number">1</div>
                <p><?php _e('Choose to use Disk or Database for storage of the cache files. There is no difference in speed.', 'mpg'); ?></p>
            </li>

            <li>
                <div class="number">2</div>
                <p><?php _e('Depending on your host, you may have limits in disk or database space, therefore select the appropriate method for your scenario.', 'mpg'); ?></p>
            </li>

            <li>
                <div class="number">3</div>
                <p><?php _e('Any time you edit data source file or template - cache is flushed automatically.', 'mpg'); ?></p>
            </li>

        </ul>
    </div>
</div>
<?php require plugin_dir_path( __FILE__ ) . '../../sidebar-subscribe.php'; ?>
