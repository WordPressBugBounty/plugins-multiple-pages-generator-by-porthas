<?php

if (!defined('ABSPATH')) {
    exit;
}

class MPG_DatasetLibraryView
{
    public static function render($datasets_list)
    {

        add_action('admin_head', ['Helper', 'mpg_header_code_container']);

	    $is_pro = mpg_app()->is_premium();

?>
		<div class="page-header-top">
			<div class="page-title d-flex flex-wrap align-items-center justify-content-between">
	            <h1><?php esc_html_e( 'New Project', 'multiple-pages-generator-by-porthas' ); ?></h1>
	            <?php
	            $plugin_data = get_plugin_data( MPG_BASENAME );
	            if ( ! empty( $plugin_data['Version'] ) ) {
	                $version = $plugin_data['Version'];
	                ?>
	                <div class="project-version">
	                    MPG <?php echo $is_pro ? esc_html( $version ) : 'Lite ' . esc_html( $version ); ?>
	                </div>
	                <?php
	            }
	            ?>
	        </div>
		</div>
        <div class="dataset-library">

            <div class="main-page-container">
            	
			<?php
			global $wpdb;
			$projects       = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}" . MPG_Constant::MPG_PROJECTS_TABLE );
			$projects_count = count( $projects );
			if ( ! $is_pro && $projects_count > 0 ) :
				?>
				<div class="mpg-notice mpg-notice-top">
						<?php
						echo wp_kses(
							sprintf( 
								// translators: %1$s the upgrade URL (with label "upgrading to MPG Pro").
								__( 'Project limit reached! Create unlimited projects, by %1$s', 'multiple-pages-generator-by-porthas' ),
								'<a href="' . esc_url( mpg_app()->get_upgrade_url( 'setupProject' ) ) . '" target="_blank">' . __( 'upgrading to MPG Pro', 'multiple-pages-generator-by-porthas' ) . '</a>'
							),
							array(
								'a' => array(
									'href'   => true,
									'class'  => true,
									'target' => true,
								),
							)
						);
						?>
				</div>
			<?php endif; ?>
                <div class="main-inner-content shadowed">
                    <div class="top-content">
                        <form name="filterform" onsubmit="return false;">
                            <div class="left-block">
                                <h2><?php _e('How would you like to start?', 'multiple-pages-generator-by-porthas'); ?></h2>
                                
                            </div>
                            <div class="right-block">
                                <input type="search" name="filterinput" id="filterinput" placeholder="<?php _e('Search for template', 'multiple-pages-generator-by-porthas'); ?>" />
                            </div>
                        </form>
                    </div>

                    <div class="middle-content-container">
                    	<div class="mpg-project-list-wrap">
                    		<?php
                    		if ( ! $is_pro && $projects_count > 0 ) :
                    			?>
                    			<div class="overlay">
                    				<div class="mpg-notice mpg-inner-notice">
                    					<?php
                    					echo wp_kses(
											sprintf(
												// translators: %1$s the upgrade URL (with label "upgrading to MPG Pro").
												__( 'Unlock all templates and create unlimited projects, by %1$s', 'multiple-pages-generator-by-porthas' ), 
												'<a href="' . esc_url( mpg_app()->get_upgrade_url( 'setupProject' ) ) . '" target="_blank">' . __( 'upgrading to MPG Pro', 'multiple-pages-generator-by-porthas' ) .'</a>'
											),
                    						array(
                    							'a' => array(
                    								'href'   => true,
                    								'class'  => true,
                    								'target' => true,
                    							),
                    						)
                    					);
                    					?>
                    				</div>
                    			</div>
                    		<?php endif; ?>
                    		<ul id="dataset_list">
                    			<?php
                            	// Replace headers row to "From scratch"
                    			$datasets_list[0] = array(1 => __('From scratch', 'multiple-pages-generator-by-porthas'), 2 => 'fa fa-file');
                    			foreach ($datasets_list as $index => $dataset) {
                                // Избавляемся от пустых рядов
                    				if (isset($dataset[0]) && !$dataset[0]) {
                    					continue;
									}
									?>

                    				<li>

                    					<a <?php if (!$is_pro && $projects_count > 0) {
                    						echo 'class="disable-tile"';
                    					} ?> <?php
                    					if (!$is_pro & $projects_count > 0) {
                    						$link = '#';
                    					} else {
                    						if ($index === 0) {
                    							$link = add_query_arg(
                    								array(
                    									'page' => 'mpg-project-builder',
                    									'action' => 'from_scratch',
                    								),
                    								admin_url( 'admin.php' )
                    							);
                    						} else {
                    							$link = add_query_arg( 'page', 'mpg-deploy-dataset', admin_url( 'admin.php' ) );
                    						}
                    					} ?> <?php
                    					if (isset($dataset[0])) {

                    						echo  'data-dataset-id=' .  (int) $dataset[0];
                    					} else {
                    						'data-dataset-id=""';
                    					} ?> href="<?php echo esc_url($link); ?>">

                    					<?php if (!$is_pro & $projects_count > 0) { ?>
                    						<div class="pro-field">Pro</div>
                    					<?php } ?>

                    					<i class="<?php echo esc_textarea($dataset[2]); ?>"></i>
                    					<span><?php echo esc_textarea($dataset[1]); ?></span>
                    					<div class="dataset-filesize">
                    						<?php if (isset($dataset[13])) {
                    							echo esc_textarea($dataset[13]);
                    						} ?></div>
                    					</a>

                    				</li>
                    			<?php } ?>
                    		</ul>
                    	</div>
                    </div>

                    <div class="load-more-container">
                        <a href="#" class="load-more hide"><?php _e('Load more', 'multiple-pages-generator-by-porthas'); ?></a>
                    </div>
                </div>

                <div class="sidebar-container">
                    <?php require_once('sidebar.php'); ?>
                </div>
            </div>
        </div>

<?php }
}
