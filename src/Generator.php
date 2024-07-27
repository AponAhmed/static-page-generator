<?php

namespace Aponahmed\StaticPageGenerator;

use Aponahmed\StaticPageGenerator\AdminController;
use Aponahmed\StaticPageGenerator\FrontendController;
use PerformanceChecker;
use WP_Query;

/**
 * Description of Generator
 *
 * @author Mahabub
 */
class Generator
{

    private $frontEnd;
    private  AdminController $backEnd;
    private $options;

    public function __construct()
    {
        //add_action('init', array($this, 'start_timer'), 0);

        $this->options = [
            'postType' => 'static_post',
            //'gmood' => get_option('staticGmood'),
        ];

        //Folder Creation and Resource File initialization
        $this->initFiles();
        $this->getOptions();
        //Scheduler Event set
        $this->SetScheduler();
        $this->initDir();
        add_action('init', [$this, 'static_post_generator'], 0);
        add_action('init', [$this, 'rewrite_set_preview'], 0);
        add_filter('query_vars', [$this, 'query_var_set_preview']);

        // if ($this->options['custom_slug_enable'] == "1") {
        //     add_action('init', [$this, 'rewrite_set'], 0);
        //     add_filter('query_vars', [$this, 'query_var_set']);
        //     $this->customRewrite();
        // } else {
        //     add_action('template_include', [$this, 'nonCustomSlug'], 1);
        // }

        add_action('template_include', [$this, 'nonCustomSlug'], 0);

        add_action('template_include', [$this, 'staticPostTemplate']);
        //
        $this->backEnd = new AdminController($this->options);
        // if (is_admin()) {

        // }
        $this->frontEnd = new FrontendController($this->options);
        //Ajax 
        add_action('wp_ajax_loadKeywords', [$this->backEnd, 'loadKeyesData']);
        add_action('wp_ajax_StoreKeywords', [$this->backEnd, 'StoreKeywords']);
        add_action('wp_ajax_removeList', [$this->backEnd, 'removeList']);
        add_action('wp_ajax_generateStaticPage', [$this->backEnd, 'generateStaticPage']);
        add_action('wp_ajax_generateStaticPageSingle', [$this->backEnd, 'generateStaticPageSingle']);
        add_action('wp_ajax_staticPageOptionsStore', [$this->backEnd, 'staticPageOptionsStore']);
        add_action('wp_ajax_generateStaticSitemap', [$this->backEnd, 'generateStaticSitemap']);
        add_action('wp_ajax_deleteStaticPages', [$this->backEnd, 'deleteStaticPages']);
        add_action('wp_ajax_regenerate', [$this->backEnd, 'regenerate']);
        add_action('wp_ajax_svgFile4keyworg', [$this->backEnd, 'svgFile4keyworg']);
        add_action('wp_ajax_loadCsv', [$this->backEnd, 'loadCsv']);
        add_action('wp_ajax_removeCsv', [$this->backEnd, 'removeCsv']);
        add_action('wp_ajax_downloadCsv', [$this->backEnd, 'downloadCsv']);
        add_action('wp_ajax_updateCsvFile', [$this->backEnd, 'updateCsvFile']);
        add_action('wp_ajax_changeStaticCronStatus', [$this->backEnd, 'changeStaticCronStatus']);
        add_action('wp_ajax_deleteSitemaps', [$this->backEnd, 'deleteSitemaps']);
        add_action('wp_ajax_manualGenerateStatus', [$this->backEnd, 'manualGenerateStatus']);
        add_action('wp_ajax_setStaticManualGenerateEvent', [$this->backEnd, 'setStaticManualGenerateEvent']);
        // add_action('wp_ajax_quickLinkGenerate', [$this->backEnd, 'quickLinkGenerate']);
    }


    public function start_timer()
    {
        global $wp_start_time;
        //echo "---hello";
        $wp_start_time = microtime(true);
    }

    function SetScheduler()
    {
        add_filter('cron_schedules', array($this, 'cron_time_intervals'));
        add_action('wp', array($this, 'cron_scheduler'));
        add_action('cast_my_spell', array($this, 'every_three_minutes_event_func'));
    }

    public function cron_time_intervals($schedules)
    {
        $schedules['minutes_10'] = array(
            'interval' => intval($this->options['cronInterval']),
            'display' => 'Once 10 minutes'
        );
        return $schedules;
    }

    function cron_scheduler()
    {
        if (!wp_next_scheduled('cast_my_spell')) {
            wp_schedule_event(time(), 'minutes_10', 'cast_my_spell');
        }
    }

    function every_three_minutes_event_func()
    {
        // do something
        $this->backEnd = new AdminController($this->options);
        $args = array(
            'post_type' => $this->options['postType'],
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'cronStatus',
                    'value' => '1',
                    'compare' => '='
                ),
            )
        );
        $query = new \WP_Query($args);
        //var_dump($query);
        foreach ($query->posts as $id) {
            //var_dump($id);
            $total = get_post_meta($id, 'numberOfGenerate', true);
            $generated = $this->backEnd->countLinks($id);
            if ($generated < $total) { //Not Complete 
                $this->backEnd->generateStaticPageSingle($id);
            }
        }
        //echo "This is raned From Cron";
    }

    public static function init()
    {
        return new Generator();
    }

    function nonCustomSlug($template)
    {
        global $wp, $perform;

        if (is_404()) {
            if ($perform && defined('WP_PERFORMANCE') && WP_PERFORMANCE) {
                //var_dump($perform);
                $perform->start('staticpage', 'Static Page Generate', ['file' => __FILE__, 'line' => __LINE__]);
            }

            $siteUrl = get_bloginfo('url') . "/";
            $siteUrl = preg_replace('/([^:])(\/{2,})/', '$1/', $siteUrl);
            $current_url = home_url(add_query_arg(array(), $wp->request));

            $rqUri = $_SERVER['REQUEST_URI'];
            // Check if the URL does not end with a slash and does not contain ".html" at the end
            if (substr($rqUri, -1) !== '/' && substr($rqUri, -5) !== '.html') {
                // Add a forward slash at the end
                $new_url = $rqUri . '/';
                // Redirect to the new URL
                wp_redirect($new_url, 301); // 301 indicates a permanent redirect
                exit;
            }

            $slug = str_replace($siteUrl, "", $current_url);
            $slug = preg_replace('/([^:])(\/{2,})/', '$1/', $slug);
            $file = __SPG_CONTENT . "pages/" . $slug;

            if (file_exists($file)) {
                http_response_code(200);
                echo $this->generateContent($file);

                if ($perform && defined('WP_PERFORMANCE') && WP_PERFORMANCE) {
                    $perform->end('staticpage');
                    echo $perform->html(true);
                }
                exit;
            } else {
                return $template;
            }
        } else {
            return $template;
        }
    }

    function performanceColor($ms)
    {
        if ($ms < 1500) {
            return 'green';
        } elseif ($ms < 2500) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    function generateContent($file)
    {
        global $perform;

        $jsonData = file_get_contents($file);
        $data = json_decode($jsonData, true);
        //Example Uses

        if ($perform && defined('WP_PERFORMANCE') && WP_PERFORMANCE) {
            $perform->start('get_static_page_content', 'Static Page Get Content', ['file' => __FILE__, 'line' => __LINE__]);
        }

        $content = $this->getContentById($data['id']);

        if ($perform && defined('WP_PERFORMANCE') && WP_PERFORMANCE) {
            $perform->end('get_static_page_content');
        }

        $content = str_replace($data['replacer']['find'], $data['replacer']['replace'], $content);
        $content = $this->backEnd->internalLinkFilter($content, $data['slug']);
        return do_shortcode($content);
    }

    public function getContentById($id)
    {
        global $post, $wp_query;
        ob_start();
        $post = get_post($id);
        if ($post) {
            // Set the global post variable
            $GLOBALS['post'] = $post;
            setup_postdata($post);

            // Modify the global $wp_query to set the post as the queried object
            $wp_query = new WP_Query(array(
                'p' => $post->ID,
                'post_type' => $post->post_type
            ));
            $wp_query->is_single = true;
            $wp_query->queried_object = $post;
            $wp_query->queried_object_id = $post->ID;
            // $wp_query->is_page = ($post->post_type === 'page');
            $wp_query->is_singular = true;
            $wp_query->is_404 = false;
            // Load the appropriate template
            $template = get_page_template();
            //var_dump($template);
            if (!$template) {
                $template = get_template_directory() . '/page.php';
            }
            if ($template) {
                include($template);
            }
            // Reset post data after custom query
            wp_reset_postdata();
        }

        return ob_get_clean();
    }



    public function getContentById_crul($id)
    {
        //return "---";
        error_reporting(0);
        // update_option('staticGmood', '1');
        $link = get_permalink($id);
        $http = new \WP_Http();
        $response = $http->request($link); //['timeout' => 1]
        //update_option('staticGmood', '0');
        //ob_clean();
        //WP_Error
        if ($response && isset($response['response']['code']) && $response['response']['code'] == 200) {
            $re = '/postid-(\d+)/m';
            return preg_replace($re, "", $response['body']);
        } else {
            return false;
        }
    }

    function customRewrite()
    {
        $options = $this->getOptions();
        $customSlug = $options['static_page_custom_slug'];
        if (strpos($_SERVER['REQUEST_URI'], "$customSlug/")) {
            $requestUrl = trim($_SERVER['REQUEST_URI']);
            $re = "/($customSlug\/)(.*$)/m";
            preg_match_all($re, $requestUrl, $matches, PREG_SET_ORDER, 0);
            if (isset($matches[0][2])) {
                $slug = $matches[0][2];
                $file = __SPG_CONTENT . "pages/" . $slug;
                if (file_exists($file)) {
                    http_response_code(200);
                    echo $this->generateContent($file);
                    exit;
                }
                // if (file_exists($file)) {
                //     echo file_get_contents($file);
                //     exit;
                // }
            }
        }
    }

    function initFiles()
    {
        $files = ['countrycity.csv', 'worldcities.csv', 'country.csv', 'city-usa.csv'];
        $fDir = __SPG_DIR . "/csv/";
        foreach ($files as $file) {
            $fileHt = __SPG_CONTENT_CSV . $file;
            if (!file_exists($fileHt) && file_exists($fDir . $file)) {
                if (is_dir(__SPG_CONTENT_CSV)) {
                    copy($fDir . $file, $fileHt);
                }
            }
        }
        //Files 
        // Source directory
        $sourceDir = __SPG_DATA_DIR;
        // Destination directory
        $destinationDir = __SPG_CONTENT_DATA;

        // Get the list of files in the source directory
        $files = scandir($sourceDir);
        // Copy each file to the destination directory
        foreach ($files as $file) {
            // Exclude '.' and '..' entries
            if ($file === '.' || $file === '..') {
                continue;
            }
            // Construct the source and destination paths
            $sourcePath = $sourceDir . '/' . $file;
            $destinationPath = $destinationDir . '/' . $file;
            // Copy the file
            copy($sourcePath, $destinationPath);
        }
    }

    function initDir()
    {
        if (!is_dir(__SPG_CONTENT)) {
            mkdir(__SPG_CONTENT, 0777, true);
            chmod(__SPG_CONTENT, 0777);
        }
        if (!is_dir(__SPG_CONTENT . "temp")) {
            mkdir(__SPG_CONTENT . "temp", 0777, true);
            chmod(__SPG_CONTENT . "temp", 0777);
        }
        if (!is_dir(__SPG_CONTENT . "pages")) {
            mkdir(__SPG_CONTENT . "pages", 0777, true);
            chmod(__SPG_CONTENT . "pages", 0777);
        }
        if (!is_dir(__SPG_CONTENT . "links")) {
            mkdir(__SPG_CONTENT . "links", 0777, true);
            chmod(__SPG_CONTENT . "links", 0777);
        }
        if (!is_dir(__SPG_CONTENT_CSV)) {
            mkdir(__SPG_CONTENT_CSV, 0777, true);
            chmod(__SPG_CONTENT_CSV, 0777);
        }
        if (!is_dir(__SPG_CONTENT_DATA)) {
            mkdir(__SPG_CONTENT_DATA, 0777, true);
            chmod(__SPG_CONTENT_DATA, 0777);
        }
    }

    function getOptions()
    {
        $options = get_option('static_page_options');
        $options = $options ? $options : [];
        $default = [
            'static_page_custom_slug' => 'customized',
            'custom_slug_enable' => '0',
            'sitemapName' => 'static',
            'static_sitemap_directory' => '',
            'file_max_link' => '1000',
            'cronInterval' => 30,
            'quick_link_info' => '',
            'quick_mood' => '0',
            'listGmood' => '0',
        ];
        $options = array_merge($default, $options);

        $this->options = array_merge($this->options, $options);
        return $this->options;
    }

    function rewrite_set_preview()
    {
        add_rewrite_rule('preview-static/(.*)[/]?$', 'index.php?preview_id=$matches[1]', 'top');
    }

    function rewrite_set()
    {
        $options = $this->getOptions();
        $customSlug = $options['static_page_custom_slug'];
        add_rewrite_rule($customSlug . '/(.*)[/]?$', 'index.php?static=$matches[1]', 'top');
    }

    function staticPostTemplate($template)
    {
        if (get_query_var('preview_id') && get_query_var('preview_id') != '') {
            $postID = get_query_var('preview_id');
            if (is_user_logged_in()) {
                $this->previewOutput($postID);
                exit;
            } else {
                wp_redirect(home_url());
            }
        } //Preview
        if (get_query_var('static') != false || get_query_var('static') != '') {
            $themePath = get_template_directory();
            $notFoundTemplate = $themePath . "/404.php";
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return $notFoundTemplate;
        }
        return $template;

        $slug = get_query_var('static');
        $file = __SPG_CONTENT . "pages/" . $slug;
        if (file_exists($file)) {
            echo file_get_contents($file);
        } else {
            wp_redirect(home_url());
        }
        exit;
    }

    function previewOutput($id = false)
    {
        //update_option('staticGmood', '1');
        echo $this->getContentById($id);
        exit;
        // if ($id && !empty($id)) {
        //     $link = get_permalink($id);
        //     $http = new \WP_Http();
        //     $response = @$http->request($link, ['timeout' => 120]);
        //     if (is_a($response, 'WP_Error')) {
        //         var_dump($response);
        //     } else {
        //         if ($response && isset($response['response']['code']) && $response['response']['code'] == 200) {
        //             echo $response['body'];
        //         }
        //     }
        // }
        //update_option('staticGmood', '0');
    }

    /**
     * 
     * @param type $query_vars
     * @return Array
     */
    function query_var_set($query_vars)
    {
        $query_vars[] = 'static';
        return $query_vars;
    }

    function query_var_set_preview($query_vars)
    {
        $query_vars[] = 'preview_id';
        return $query_vars;
    }

    // Register Custom Post Type
    function static_post_generator()
    {
        $labels = array(
            'name' => _x('Multi Posts', 'Post Type General Name', 'static-post-generator'),
            'singular_name' => _x('Multi Post', 'Post Type Singular Name', 'static-post-generator'),
            'menu_name' => __('Multi Posts', 'static-post-generator'),
            'name_admin_bar' => __('Multi Posts', 'static-post-generator'),
            'archives' => __('Item Archives', 'static-post-generator'),
            'attributes' => __('Static Attributes', 'static-post-generator'),
            'parent_item_colon' => __('Parent Item:', 'static-post-generator'),
            'all_items' => __('All Items', 'static-post-generator'),
            'add_new_item' => __('Add New Item', 'static-post-generator'),
            'add_new' => __('Add New', 'static-post-generator'),
            'new_item' => __('New Item', 'static-post-generator'),
            'edit_item' => __('Edit Item', 'static-post-generator'),
            'update_item' => __('Update Item', 'static-post-generator'),
            'view_item' => __('View Item', 'static-post-generator'),
            'view_items' => __('View Items', 'static-post-generator'),
            'search_items' => __('Search Item', 'static-post-generator'),
            'not_found' => __('Not found', 'static-post-generator'),
            'not_found_in_trash' => __('Not found in Trash', 'static-post-generator'),
            'featured_image' => __('Featured Image', 'static-post-generator'),
            'set_featured_image' => __('Set featured image', 'static-post-generator'),
            'remove_featured_image' => __('Remove featured image', 'static-post-generator'),
            'use_featured_image' => __('Use as featured image', 'static-post-generator'),
            'insert_into_item' => __('Insert into item', 'static-post-generator'),
            'uploaded_to_this_item' => __('Uploaded to this item', 'static-post-generator'),
            'items_list' => __('Items list', 'static-post-generator'),
            'items_list_navigation' => __('Items list navigation', 'static-post-generator'),
            'filter_items_list' => __('Filter items list', 'static-post-generator'),
        );
        $args = array(
            'label' => __('Multi Post', 'static-post-generator'),
            'description' => __('Post Type Description', 'static-post-generator'),
            'labels' => $labels,
            'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 5,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'public' => true,
            'publicly_queryable' => true,
            'query_var' => true,
            'capability_type' => 'post',
            'show_in_rest' => false,
            'rewrite' => array('slug' => 'static'),
            'menu_icon' => 'dashicons-edit-page',
        );
        register_post_type($this->options['postType'], $args);
    }
}
