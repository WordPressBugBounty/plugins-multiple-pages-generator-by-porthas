<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class MPG_Constant{

   const DATASET_SPREADSHEET_CSV_URL = 'https://docs.google.com/spreadsheets/d/10usA3azffwWwFJurjIFMJm3g3SCSt8Ba/export?format=xlsx&id=10usA3azffwWwFJurjIFMJm3g3SCSt8Ba';

   const MPG_PROJECTS_TABLE = 'mpg_projects';
   const MPG_SPINTAX_TABLE  = 'mpg_spintax';
   const MPG_CACHE_TABLE = 'mpg_cache';
   const MPG_LOGS_TABLE = 'mpg_logs';

   const DEFAULT_SPACE_REPLACER = '-';
   const DEFAULT_URL_MODE = 'both';

   const MPG_WEEK_IN_SECONDS = 604800;
   const MPG_TWO_MONTH_IN_SECONDS = 5259492;
   const MPG_SIX_MONTH_IN_SECONDS = 15778476;

   const EXCLUDED_PROJECTS_IN_ROBOT = 'mpg_excluded_templates_projects_in_robot';
}