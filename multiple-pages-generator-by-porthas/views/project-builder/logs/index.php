<div class="tab-pane main-tabpane" id="logs" role="tabpanel" aria-labelledby="logs-tab">
    <div class="mpg-container d-flex align-items-start logs-page">
        <div class='main-inner-content'>
            <div class="card w-100 p-0 m-0 mb-4 mpg-card">
                <div class="card-header d-flex justify-content-between">
                    <h2 class="project-name-header">
                        <?php _e('Logs', 'multiple-pages-generator-by-porthas'); ?>
                    </h2>
                    <button id="mpg_clear_log_by_project_id" class="btn btn-danger btn-link"><?php _e("Clear logs", 'multiple-pages-generator-by-porthas');?></button>
                </div>
                <div class="card-body">
                    <table id="mpg_logs_table" class="display"></table>
                </div>
            </div>
        </div>
        <div class="sidebar-container">
            <?php require_once('sidebar.php') ?>
        </div>
    </div>
 </div>