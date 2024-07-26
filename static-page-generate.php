<?php

/**
 * Plugin Name: Multi Posts Generator (Static)
 * Plugin URI: https://siatexltd.com/wp-update-path/plugins/static-page-generate/
 * Description: To generate static Page
 * Author: SiATEX
 * Author URI: https://www.siatex.com
 * Version: 2.7.7
 */

namespace StaticPage;

use Aponahmed\StaticPageGenerator\Generator;

require_once 'vendor/autoload.php';
define('__SPG_DIR', dirname(__FILE__));
define('__SPG_ASSET', plugin_dir_url(__FILE__) . "assets/");
define('__SPG_CONTENT', WP_CONTENT_DIR . "/spg/");

define('__SPG_CSV_DIR', __SPG_DIR . "/csv/");
define('__SPG_DATA_DIR', __SPG_DIR . "/data/");

define('__SPG_CONTENT_CSV', WP_CONTENT_DIR . "/spg/csvs/");
define('__SPG_CONTENT_DATA', WP_CONTENT_DIR . "/spg/data/");

$pageGenerator = Generator::init();


//var_dump($pageGenerator);
