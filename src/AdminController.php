<?php

namespace Aponahmed\StaticPageGenerator;

use Aponahmed\StaticPageGenerator\adminViews;
use Aponahmed\StaticPageGenerator\Sitemap;

/**
 * Description of AdminController
 *
 * @author Mahabub
 */
class AdminController extends adminViews
{

    public $options;
    private $siteUrl;
    private $fileName = "static";

    //put your code here
    public function __construct($options)
    {

        $this->siteUrl = site_url();
        $this->options = $options;
        $this->fileName = $this->options['sitemapName'];
        add_action('admin_enqueue_scripts', [$this, 'adminScript']);
        add_action('admin_enqueue_scripts', [$this, 'codemirror_enqueue_scripts']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('add_meta_boxes', [$this, 'generateMetaBox']);
        add_action('save_post', [$this, 'store_meta_data']);
        add_action('wp_trash_post', [$this, 'delete_post'], 10, 1);
        add_filter("manage_{$this->options['postType']}_posts_columns", [$this, 'multiPostColumn'], -100, 1);
        add_action("manage_{$this->options['postType']}_posts_custom_column", [$this, 'multipostCustomColumn'], 10, 2);
    }

    function multiPostColumn($columns)
    {
        $columns['progress_of_generate'] = __('Progress of Generate', 'multipost-static');
        //        $columns['control_of_static_pages'] = __('Manage', 'multipost-static');
        return $columns;
    }

    function delete_post($id)
    {
        $post = get_post($id);
        if ($post->post_type == $this->options['postType']) {
            $this->removeLinks($id);
        }
    }

    static function debug()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
    }

    function store_meta_data($post_id)
    {
        if (array_key_exists('slugStructure', $_POST)) {
            update_post_meta(
                $post_id,
                'slugStructure',
                trim($_POST['slugStructure'])
            );
        }
        if (array_key_exists('keywordFile', $_POST)) {
            update_post_meta(
                $post_id,
                'keywordFile',
                trim($_POST['keywordFile'])
            );
        }
        if (array_key_exists('numberOfGenerate', $_POST)) {
            update_post_meta(
                $post_id,
                'numberOfGenerate',
                trim($_POST['numberOfGenerate'])
            );
        }
    }

    function getCount($fileName, $postID)
    {

        if (empty($fileName)) {
            //Default Key
            $data = $this->dataMap(false, $postID);

            $bigElement = $data;
            //Short by Length
            usort($bigElement, function ($a, $b) {
                return count($b) - count($a);
            });
            $bigElement = $bigElement[0];
            return count($bigElement);
        } else {
            $filePath = __SPG_CONTENT_CSV . $fileName . ".csv";
            $fp = file($filePath, FILE_SKIP_EMPTY_LINES);
            return count($fp) - 1;
        }
    }

    /**
     * Get Shortcodes according file
     * @param type $fileName
     * @return boolean
     */
    function getShortcodes($fileName = "")
    {
        if (empty($fileName)) {
            //Default Key
            $keys = $this->keyFiles();
            return $keys;
        } else {
            $filePath = __SPG_CONTENT_CSV . $fileName . ".csv";
            if (file_exists($filePath)) {
                //$content = file_get_contents($filePath);
                $f = fopen($filePath, 'r');
                $line = fgetcsv($f);
                fclose($f);

                return $line;
            } else {
                return false;
            }
            //Key From CSV
        }
    }

    function generateMetaBox()
    {
        add_meta_box(
            'static_post_generator_metabox', // Unique ID
            'Generate Static Page', // Box title
            [$this, 'generateMetaBoxCallback'], // Content callback, must be of type callable
            $this->options['postType'], // Post type
            'side',
            'high'
        );
    }

    function setStaticManualGenerateEvent()
    {
        $args = array(
            'post_type' => $this->options['postType'],
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'static_manualGenerate',
                    'value' => '1',
                    'compare' => '='
                ),
            )
        );
        $query = new \WP_Query($args);
        $info = [];
        if ($query->post_count > 0) {
            foreach ($query->posts as $id) {
                $total = get_post_meta($id, 'numberOfGenerate', true);
                $generated = $this->countLinks($id);
                if ($generated < $total) { //Not Complete 
                    ob_start();
                    $this->generateStaticPageSingle($id);
                    $resp = ob_get_clean();
                    $respArr = json_decode($resp, true);
                    if (isset($respArr['lIndex'])) {
                        $info[] = ['id' => $id, 'done' => $respArr['lIndex'], 'total' => $total];
                    }
                } else {
                    $info[] = ['id' => $id, 'done' => $total, 'total' => $total];
                }
            }
        }
        echo json_encode($info);
        wp_die();
    }

    function manualGenerateStatus()
    {
        if (isset($_POST['postID']) && !empty($_POST['postID'])) {
            $id = $_POST['postID'];
            $currentStatus = get_post_meta($id, 'static_manualGenerate', true);
            if ($currentStatus === false) {
                $currentStatus = "0";
            }

            if ($currentStatus == '0') {
                $currentStatus = '1';
                update_post_meta($id, 'cronStatus', '0'); //Disable from Cron When Manual Generate 
                //$this->generateStaticPage($id);
            } else {
                $currentStatus = '0';
            }
            update_post_meta($id, 'static_manualGenerate', $currentStatus);
            ob_get_clean();

            echo $currentStatus;
        }
        wp_die();
    }

    function changeStaticCronStatus()
    {
        if (isset($_POST['postID']) && !empty($_POST['postID']) && isset($_POST['status']) && !empty($_POST['status'])) {
            $st = '0';
            $id = $_POST['postID'];
            if ($_POST['status'] == 'true') {
                $st = '1';
                //$this->generateStaticPage($id);
            }
            update_post_meta($id, 'cronStatus', $st);
        }
        wp_die();
    }

    function keyFiles($dir = false)
    {
        if (!$dir) {
            $dir = __SPG_CONTENT_DATA;
        }
        $ignored = array('.', '..', '.svn', '.htaccess');

        $files = array();
        foreach (scandir($dir) as $file) {
            $filePath = pathinfo($file);
            $fileName = $filePath['filename'];
            if (in_array($file, $ignored))
                continue;
            $files[$fileName] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        $files = array_keys($files);
        return ($files) ? $files : false;
    }

    /**
     * ajax Load CSV file Content 
     */
    function loadCsv()
    {
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $filePath = __SPG_CONTENT_CSV . $_POST['name'] . ".csv";
            if (file_exists($filePath)) {
                echo file_get_contents($filePath);
            }
        }
        wp_die();
    }

    /**
     * ajax update CSV file Content 
     */
    function updateCsvFile()
    {
        if (isset($_POST['name']) && !empty($_POST['name']) && isset($_POST['val']) && !empty($_POST['val'])) {
            $filePath = __SPG_CONTENT_CSV . $_POST['name'] . ".csv";
            if (file_exists($filePath)) {
                echo file_put_contents($filePath, stripslashes($_POST['val']));
            }
        }
    }

    /**
     * Delete Csv File
     */
    function removeCsv()
    {
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $filePath = __SPG_CONTENT_CSV . $_POST['name'] . ".csv";
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        wp_die();
    }

    /**
     * Upload and store Csv File
     */
    function svgFile4keyworg()
    {
        if (isset($_FILES['csvUpload']) && !empty($_FILES['csvUpload'])) {
            $file = $_FILES['csvUpload'];
            $dir = __SPG_CONTENT_CSV;
            $allowTypes = array('csv');
            $fileName = $file['name'];
            $targetFilePath = $dir . $fileName;
            $fileInfo = pathinfo($targetFilePath);
            if (in_array($fileInfo['extension'], $allowTypes)) {
                if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                    echo json_encode(['error' => false, 'fname' => $fileInfo['filename']]);
                } else {
                    echo json_encode(['error' => true, 'fname' => '']);
                }
            }
        }
        wp_die();
    }

    /**
     * SVG File Lists
     */
    function csvFiles()
    {
        $dir = __SPG_CONTENT_CSV;
        return $this->keyFiles($dir);
    }

    // function quickLinkGenerate()
    // {
    //     self::debug();
    //     if (isset($_POST['str']) && !empty($_POST['str'])) {
    //         $str = trim($_POST['str']);
    //         $lines = preg_split("/\r\n|\n|\r/", $str);
    //         $resp = [];
    //         foreach ($lines as $line) {
    //             $part = explode('|', $line);
    //             $id = $part[0];
    //             $shortCods = $part[1];
    //             $slug = isset($part[2]) ? $part[2] : "";
    //             $orgLink = get_permalink($id);
    //             if (empty($slug)) {
    //                 continue;
    //             }
    //             $fileName = __SPG_CONTENT . "temp/$id.html";
    //             if (!file_exists($fileName)) {
    //                 $this->generateStaticPage($id);
    //             }
    //             $content = file_get_contents($fileName);
    //             //Temp Generated
    //             //$keywordFile = get_post_meta($id, 'keywordFile', true);
    //             //$codes = $this->getShortcodes($keywordFile);
    //             //Shortcodes
    //             $codesInf = explode(",", $shortCods);
    //             $find = [];
    //             $replace = [];
    //             foreach ($codesInf as $inf) {
    //                 $codePart = explode(":", $inf);
    //                 $find[] = "{" . $codePart[0] . "}";
    //                 $replace[] = $codePart[1];
    //             }
    //             $content = str_replace($find, $replace, $content);

    //             $content = $this->internalLinkFilter($content, $slug); //Slug for Skip

    //             $slug = str_replace($find, $replace, $slug);
    //             $slug = $this->slugFilter($slug);

    //             $actualLink = $this->ActualLink($slug);
    //             $content = str_replace($orgLink, $actualLink, $content);

    //             $res = $this->writeFile($content, $slug);
    //             if ($res) {
    //                 $resp[] = $actualLink;
    //             }
    //         }
    //         echo json_encode($resp);
    //     }
    //     wp_die();
    // }

    /**
     * Ajax Request for curl data of page and store temp with page id
     */
    function generateStaticPage($id = false)
    {
        update_option('staticGmood', '1');
        $die = false;
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            $id = $_POST['id'];
            $die = true;
        }
        if ($id) {
            update_option('staticGmood', '0');
            echo json_encode(['error' => false, 'id' => $id]);
            if ($die) {
                wp_die();
            }

            //Limit of Generated
            if (isset($_POST['limit'])) {
                update_post_meta($id, 'numberOfGenerate', trim($_POST['limit']));
            }

            $link = get_permalink($id);
            $http = new \WP_Http();
            $response = @$http->request($link, ['timeout' => 120]);

            if ($response && isset($response['response']['code']) && $response['response']['code'] == 200) {
                $fileName = __SPG_CONTENT . "temp/$id.html";
                //existing File of Links by ID
                //$linksFile = __SPG_CONTENT . "links/$id.txt";
                //if (file_exists($linksFile)) {
                // unlink($linksFile);
                // }
                if (file_put_contents($fileName, $response['body'])) {
                    echo json_encode(['error' => false, 'id' => $id]);
                } else {
                    echo json_encode(['error' => true, 'msg' => "Page content couldn't Stored"]);
                }
            } else {
                //error
                echo json_encode(['error' => true, 'msg' => 'Page content Retrive Error:' . $response['response']['code']]);
            }
        }
        update_option('staticGmood', '0');
        if ($die) {
            wp_die();
        }
    }

    public function getContentById($id)
    {
        //return ['id' => $id];
        //ob_start();
        error_reporting(0);
        update_option('staticGmood', '1');

        $link = get_permalink($id);
        $http = new \WP_Http();
        $response = @$http->request($link, ['timeout' => 120]);

        update_option('staticGmood', '0');
        //ob_clean();
        //WP_Error
        if ($response && isset($response['response']['code']) && $response['response']['code'] == 200) {
            $re = '/postid-(\d+)/m';
            return preg_replace($re, "", $response['body']);
        } else {
            return false;
        }
    }

    function generateStaticPageAll($id = false, $limit = 200)
    {
        $this->removeLinks($id);
        self::debug();
        $startTime = microtime(true);
        $die = false;
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $die = true;
        }
        if ($id) {
            $orgLink = get_permalink($id);
            $slugStructure = get_post_meta($id, 'slugStructure', true);
            if (!empty($slugStructure)) {
                $slugStructure = str_replace(" ", "-", strtolower($slugStructure));
            }

            $content = ['id' => $id];
            $linkStr = "";
            $n = 0;
            for ($i = 0; $i < $limit; $i++) {
                $index = $i;
                if ($content) {
                    $slug = $this->filterData($slugStructure, $index, $id);
                    $slug = $this->slugFilter($slug);
                    $content['slug'] = $slug;

                    $replacer = $this->filterDataMap($index, $id);
                    $actualLink = $this->ActualLink($slug);
                    $replacer['find'][] = $orgLink;
                    $replacer['replace'][] = $actualLink;
                    $content['replacer'] = $replacer;

                    if ($slug != "" && $content != "") {
                        if ($this->writeFile(json_encode($content), $slug)) {
                            $n++;
                            $linkStr .= $slug . "\n";
                            if ($n > 500) { //update file after Every 500 link
                                $linkStr = "";
                                $n = 0;
                                $this->addLink($id, $linkStr);
                            }
                            //$tTaken = microtime(true) - $startTime;
                        }
                    }
                }
            }
            $this->addLink($id, $linkStr);
        }
        if ($die) {
            wp_die();
        }
    }

    function generateStaticPageSingle($id = false)
    {
        $atATime = 17;
        self::debug();
        $startTime = microtime(true);
        $die = false;
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $die = true;
        }
        $total = get_post_meta($id, 'numberOfGenerate', true);
        $generated = $this->countLinks($id);

        if ($id) {
            $orgLink = get_permalink($id);
            $slugStructure = get_post_meta($id, 'slugStructure', true);
            if (!empty($slugStructure)) {
                $slugStructure = str_replace(" ", "-", strtolower($slugStructure));
            }

            //$index = get_post_meta($id, 'lastIndex', true);
            $index = $this->countLinks($id);
            //var_dump($index);
            //exit;
            if ($index === false) {
                $index = 0;
            }


            $content = ['id' => $id];
            $info = [];
            for ($i = 0; $i < $atATime; $i++) {
                if ($generated >= $total)
                    break;
                $generated++;

                if ($content) {
                    $slug = $this->filterData($slugStructure, $index, $id);
                    $slug = $this->slugFilter($slug);

                    //$content = $this->internalLinkFilter($content, $slug); //Slug for Skip
                    $content['slug'] = $slug;

                    $replacer = $this->filterDataMap($index, $id);

                    $actualLink = $this->ActualLink($slug);
                    //$content = str_replace($orgLink, $actualLink, $content);
                    $replacer['find'][] = $orgLink;
                    $replacer['replace'][] = $actualLink;
                    $content['replacer'] = $replacer;

                    if ($slug != "" && $content != "") {
                        if ($this->writeFile(json_encode($content), $slug)) {
                            $this->addLink($id, $slug);
                            $info['error'] = false;
                            $info['links'][] = $actualLink;
                            $info['lIndex'] = $index;
                        } else {
                            $info['error'] = true;
                            $info['lIndex'] = ($index > 0 ? ($index - 1) : 0);
                        }
                    }
                } else {
                    $info = ['error' => true, 'msg' => 'Page Content Missing'];
                }
                $index += 1;
            }
        }
        update_post_meta($id, 'lastIndex', $index);

        $tTaken = microtime(true) - $startTime;
        $info['time'] = number_format($tTaken, 2);

        echo json_encode($info);
        if ($die) {
            wp_die();
        }
    }

    function slugFilter($slug)
    {
        $slug = $slug;
        $slug = strtolower($slug);
        $slug = str_replace(" ", "-", strtolower($slug));
        return trim($slug);
    }

    function removeLinks($id)
    {
        $temp = __SPG_CONTENT . "temp/$id.html";
        if (file_exists($temp)) {
            unlink($temp);
        }
        update_post_meta($id, 'lastIndex', 0);
        $filename = __SPG_CONTENT . "links/$id.txt";
        if (file_exists($filename)) {
            $content = file_get_contents($filename);
            $slugs = preg_split("/\r\n|\n|\r/", $content);
            if (is_array($slugs)) {
                $slugs = array_unique(array_filter($slugs));
                foreach ($slugs as $slug) {
                    $staticPage = __SPG_CONTENT . "pages/" . $slug;
                    if (file_exists($staticPage)) {
                        unlink($staticPage);
                        // $n++;
                    }
                }
            }
            unlink($filename);
        }
    }

    function addLink($id, $slug)
    {
        $filename = __SPG_CONTENT . "links/$id.txt";
        $file = fopen($filename, "a");
        fwrite($file, $slug . PHP_EOL);
        fclose($file);
    }

    function countLinks($id)
    {
        $filename = __SPG_CONTENT . "links/$id.txt";
        if (file_exists($filename)) {
            $lines = count(file($filename));
            return $lines;
        }
        return 0;
    }

    function regenerate()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $filename = __SPG_CONTENT . "links/$id.txt";
            $tempFile = __SPG_CONTENT . "temp/$id.html";
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            if (file_exists($filename)) {
                unlink($filename);
            }
            //$this->generateStaticPage($id);
        }
        wp_die();
    }

    function deleteStaticPages()
    {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $n = 0;
            update_post_meta($id, 'lastIndex', 0);
            update_post_meta($id, 'cronStatus', "0");

            $filename = __SPG_CONTENT . "links/$id.txt";
            if (file_exists($filename)) {
                $content = file_get_contents($filename);
                $slugs = preg_split("/\r\n|\n|\r/", $content);
                if (is_array($slugs)) {
                    $slugs = array_unique(array_filter($slugs));

                    foreach ($slugs as $slug) {
                        $staticPage = __SPG_CONTENT . "pages/" . $slug;
                        //var_dump(file_exists($staticPage), $staticPage);
                        if (file_exists($staticPage)) {
                            unlink($staticPage);
                            $n++;
                        }
                    }
                    echo json_encode(['error' => false, 'msg' => "Deleted $n files"]);
                }
                unlink($filename);
            } else {
                echo json_encode(['error' => true, 'msg' => 'Link File Missing, Maybe Not Generated yet']);
            }
        } else {
            echo json_encode(['error' => true, 'msg' => 'Invalid Request']);
        }
        wp_die();
    }

    function ActualLink($slug = "")
    {
        $siteUrl = get_site_url();
        $options = $this->options;
        if ($options['custom_slug_enable'] == "1") {
            $customSlug = $options['static_page_custom_slug'];
        } else {
            $customSlug = "";
        }
        $link = $siteUrl . "/" . $customSlug . "/" . $slug . "/";
        $link = preg_replace('/([^:])(\/{2,})/', '$1/', $link);
        if (strpos($link, '.html') !== false) {
            $link = trim($link, "/");
        }
        return $link;
    }

    function deleteSitemaps($return = false)
    {
        $filesStr = get_option('static_sitemaps_files');
        $files = array();
        if ($filesStr) {
            $files = json_decode($filesStr);
        }
        foreach ($files as $file) {
            if (!empty($file)) {
                $filePath = ABSPATH . "/" . $file;
                $filePath = preg_replace('/([^:])(\/{2,})/', '$1/', $filePath);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
        if ($return) {
            return;
        }
        wp_die();
    }

    function rrmdir($src)
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }

    public static function allLinks()
    {
        $dir = __SPG_CONTENT . "links/";
        $links = [];
        $ignored = array('.', '..', '.svn', '.htaccess');
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored))
                continue;

            $content = file_get_contents($dir . $file);
            $slugs = preg_split("/\r\n|\n|\r/", $content);
            if (is_array($slugs)) {
                $slugs = array_unique(array_filter($slugs));
                foreach ($slugs as $slug) {
                    $link = self::ActualLinkStatic($slug);
                    $links[] = $link;
                    //$links[] = $link;
                }
            }
        }
        return $links;
    }

    static function ActualLinkStatic($slug = "")
    {
        $siteUrl = get_site_url();
        $options = get_option('static_page_options');
        if (isset($options['custom_slug_enable']) && $options['custom_slug_enable'] == "1") {
            $customSlug = $options['static_page_custom_slug'];
        } else {
            $customSlug = "";
        }
        $link = $siteUrl . "/" . $customSlug . "/" . $slug . "/";
        $link = preg_replace('/([^:])(\/{2,})/', '$1/', $link);
        if (strpos($link, '.html') !== false) {
            $link = trim($link, "/");
        }
        return $link;
    }

    function generateALLLink()
    {
        $args = array(
            'post_type' => $this->options['postType'],
            'fields' => 'ids'
        );
        $query = new \WP_Query($args);
        foreach ($query->posts as $id) {
            $total = get_post_meta($id, 'numberOfGenerate', true);
            $this->generateStaticPageAll($id, $total);
        }
    }

    function generateStaticSitemap()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        //$this->generateALLLink(); //Generate All Link File before Generate Sitemap
        $this->deleteSitemaps(true); //Delete Existing generated Files

        $dir = __SPG_CONTENT . "links/";
        $ignored = array('.', '..', '.svn', '.htaccess');
        $linkPerFile = $this->options['file_max_link'];
        //$links = array();
        $dataMaps = [];
        $maps = [];
        $htmlSidebar = [];
        //var_dump($this->options);
        $n = 0;
        $sitemapGenerator = new Sitemap($this->options);
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored))
                continue;

            $content = file_get_contents($dir . $file);
            $slugs = preg_split("/\r\n|\n|\r/", $content);
            if (is_array($slugs)) {
                $slugs = array_unique(array_filter($slugs));
                $inf = pathinfo($file);
                $id = $inf['filename'];
                $name = get_the_title($id);
                $slugsArr = array_chunk($slugs, $linkPerFile);

                $dataMaps[$id]['name'] = $name;
                $dataMaps[$id]['linksgroup'] = [];

                //$sitemapGenerator = new Sitemap($this->options);
                foreach ($slugsArr as $k => $arr) {
                    $fName = sanitize_title($name);
                    //File Name to slug                    
                    $tempLinks = [];
                    foreach ($arr as $slug) {
                        $link = $this->ActualLink($slug);
                        $tempLinks[] = $link;
                        //$links[] = $link;
                    }

                    $n++;
                    $htmlSitemapPath = site_url();
                    $htmlSitemapPath .= "/";
                    if ($n > 1) {
                        if ($this->options['static_sitemap_directory'] == 'root') {
                            $htmlSitemapPath .= "static-";
                        } else {
                            $htmlSitemapPath .= $this->options['static_sitemap_directory'] . "/static-";
                        }
                        //$htmlSitemapPath .= $this->options['static_sitemap_directory'] . "/";
                    }

                    $htmlSitemapPath .= $fName;
                    $htmlSitemapPath .= $k != 0 ? $k : '';
                    $htmlSitemapPath .= '.html';
                    $htmlSitemapPath = $sitemapGenerator->trimSlash($htmlSitemapPath);
                    if ($n == 1) {
                        $htmlSitemapPath = site_url();
                        $htmlSitemapPath .= "/" . $this->options['sitemapName'] . ".html";
                    }

                    $dataMaps[$id]['linksgroup'][] = $tempLinks;
                    //Generate Area
                    $sitemapGenerator->fileName = $fName;
                    $sitemapGenerator->tempData = $tempLinks;
                    $sitemapGenerator->fileCount = $k;
                    $indx = $k != 0 ? $k : '';
                    $htmlSidebar[] = "<li><a href=\"$htmlSitemapPath\">{$fName} $indx</a></li>";
                    $maps[] = $sitemapGenerator->generateXml();
                }
            }
        }
        $sitemapGenerator->tempData = $maps;
        $sitemapGenerator->fileName = $this->options['sitemapName'];
        $file = $sitemapGenerator->generateXml(true);

        $sitemapGenerator->generateHtml($dataMaps, $htmlSidebar);

        update_option('static_sitemaps_files', json_encode($sitemapGenerator->sitemapFiles));
        //var_dump($sitemapGenerator->sitemapFiles);
        if ($file) {
            echo $file;
        }
        wp_die();
    }

    /**
     * Write Page File into file manager
     * @param string $str
     * @param string $slug
     * @return int
     */
    function writeFile($str, $slug)
    {
        $fileName = __SPG_CONTENT . "pages/" . $slug;
        $pathInfo = pathinfo($fileName);
        if (!is_dir($pathInfo['dirname'])) {
            mkdir($pathInfo['dirname'], 0777, true);
            chmod($pathInfo['dirname'], 0777);
        }
        $fileName = strtolower($fileName);
        return file_put_contents($fileName, $str);
    }

    /**
     * Shortcode [internallink limit="10"]
     */
    function internalLinkFilter($str, $exc = "")
    {
        //return $str;
        $re = '/\[internallink(.*?)\]/m'; //limit=(”|"|″)(\d+)(”|"|″)
        $m = preg_match_all($re, $str, $matches, PREG_SET_ORDER, 0);
        if (!$m) {
            return $str;
        }
        $attr = shortcode_parse_atts($matches[0][1]);
        if (is_array($attr)) {
            $attr = array_filter($attr, function ($v) {
                return str_replace(['”', '″', '&#8221;', '&#8243;'], "", $v);
            });
        } else {
            $attr = [];
        }

        if (!isset($attr['limit'])) {
            $attr['limit'] = 10;
        }

        //Generated Links  
        $dir = __SPG_CONTENT . "links/";
        $ignored = array('.', '..', '.svn', '.htaccess');

        $links = array();
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored))
                continue;
            $content = file_get_contents($dir . $file);
            $slugs = preg_split("/\r\n|\n|\r/", $content);
            if (is_array($slugs)) {
                $slugs = array_unique(array_filter($slugs));
                foreach ($slugs as $slug) {
                    if ($exc == $slug) {
                        continue;
                    }
                    $link = $this->ActualLink($slug);
                    $links[] = $link;
                }
            }
        }
        shuffle($links);
        $parts = array_chunk($links, intval($attr['limit']));
        if (count($parts) > 0) {
            $disLinks = $parts[0];
            $linkArr = [];
            foreach ($disLinks as $link) {
                $inf = pathinfo($link);
                $readAbleName = ucwords(str_replace("-", " ", $inf['filename']));
                $linkArr[] = "<a href=\"$link\">$readAbleName</a>";
            }
            $htm = implode(", ", $linkArr);
            $str = str_replace($matches[0], $htm, $str);
        } else {
            $str = str_replace($matches[0], "", $str);
        }


        return $str;
    }

    /**
     * Replace or Filter String With Current Index of Keyword
     * @param type $str
     * @param type $index
     * @return String
     */
    function filterData($str = "", $index = 0, $postID = false)
    {
        $data = $this->dataMap(false, $postID);
        $cods = array_keys($data);
        $cods = array_filter(array_map(function ($b) {
            return '{' . $b . '}';
        }, $cods));

        $bigElement = $data;
        //Short by Length
        usort($bigElement, function ($a, $b) {
            return count($b) - count($a);
        });
        $bigElement = $bigElement[0];
        if (!isset($bigElement[$index])) {
            return "";
        }

        $replace = [];
        foreach ($data as $k => $valArr) {
            if (isset($valArr[$index])) {
                $replace[] = $valArr[$index];
            } else {
                $replace[] = "";
            }
        }


        return str_replace($cods, $replace, $str);
    }

    function filterDataMap($index = 0, $postID = false)
    {
        $data = $this->dataMap(false, $postID);
        $cods = array_keys($data);
        $cods = array_filter(array_map(function ($b) {
            return '{' . $b . '}';
        }, $cods));

        $bigElement = $data;
        //Short by Length
        usort($bigElement, function ($a, $b) {
            return count($b) - count($a);
        });
        $replace = [];
        $bigElement = $bigElement[0];
        if (!isset($bigElement[$index])) {
            return $replace;
        }

        foreach ($data as $k => $valArr) {
            if (isset($valArr[$index])) {
                $replace[] = $valArr[$index];
            } else {
                $replace[] = "";
            }
        }
        return ['find' => $cods, 'replace' => $replace];
    }

    /**
     * Data Map By Keyword
     * @param string $dir
     * @return array
     */
    function dataMap($dir = false, $postID = false)
    {
        $data = [];
        $keywordFile = get_post_meta($postID, 'keywordFile', true);
        if ($postID && $keywordFile != "") {
            $keywordFile = get_post_meta($postID, 'keywordFile', true);
            $filePath = __SPG_CONTENT_CSV . $keywordFile . ".csv";

            if (($handle = fopen($filePath, "r")) !== FALSE) {
                $n = 0;
                $headers = [];
                while (($dataRow = fgetcsv($handle, 10000, ",")) !== FALSE) {
                    $n++;
                    if ($n == 1) {
                        $headers = $dataRow;
                        foreach ($headers as $head) {
                            $data[$head] = [];
                        }
                        continue;
                    } //Header---
                    //var_dump($headers);

                    foreach ($dataRow as $k => $col) {
                        $indx = $headers[$k];
                        $data[$indx][] = $col;
                    }
                }
            }
        } else {
            if (!$dir) {
                $dir = __SPG_CONTENT_DATA;
            }
            $ignored = array('.', '..', '.svn', '.htaccess');

            $files = array();
            foreach (scandir($dir) as $file) {
                $filePath = pathinfo($file);
                $fileName = $filePath['filename'];
                if (in_array($file, $ignored))
                    continue;
                $content = file_get_contents($dir . $file);
                $arr = preg_split("/\r\n|\n|\r/", $content);
                $data[$fileName] = array_unique(array_filter($arr));
            }
        }
        //[
        //'index1'=>[0,1,2,3],
        //'index2'=>[0,1,2,3],
        //]
        return $data;
    }

    //Ajax Request
    function loadKeyesData()
    {
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $fileName = trim($_POST['name']) . ".txt";
            if (file_exists(__SPG_CONTENT_DATA . "$fileName")) {
                echo file_get_contents(__SPG_CONTENT_DATA . "$fileName");
            }
        }
        wp_die();
    }

    //Ajax Request
    function removeList()
    {
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $fileName = trim($_POST['name']) . ".txt";
            if (file_exists(__SPG_CONTENT_DATA . "$fileName")) {
                unlink(__SPG_CONTENT_DATA . "$fileName");
                echo "Deleted $_POST[name]";
            }
        }
        wp_die();
    }

    //Ajax Request
    function StoreKeywords()
    {
        if (isset($_POST['name']) && !empty($_POST['name'])) {
            $fileName = trim($_POST['name']) . ".txt";
            if (file_put_contents(__SPG_CONTENT_DATA . "$fileName", trim($_POST['value']))) {
                echo trim($_POST['value']);
            }
        }
        wp_die();
    }

    /**
     * Admin Script Init
     */
    public function adminScript($hook)
    {
        wp_enqueue_style('subscribe-admin-style', __SPG_ASSET . 'admin-style.css');
    }

    function codemirror_enqueue_scripts($hook)
    {
        $arg = array('type' => 'text/css');
        $cm_settings['codeEditor'] = wp_enqueue_code_editor($arg);
        wp_localize_script('jquery', 'cm_settings', $cm_settings);

        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');
        wp_enqueue_script('keywordManage', __SPG_ASSET . 'keyword-manage.js', ['jquery']);
    }

    function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=static_post',
            'Keywords',
            'Keywords',
            'manage_options',
            'static-page-keys',
            [$this, 'static_page_keywords']
        );
        add_submenu_page(
            'edit.php?post_type=static_post',
            'Settings',
            'Settings',
            'manage_options',
            'static-page-settings',
            [$this, 'static_page_settings']
        );
    }

    function staticPageOptionsStore()
    {
        $optionData = [];
        parse_str($_POST['formdata'], $optionData);
        $optionData = $optionData['data'];
        update_option('static_page_options', $optionData);
        wp_die();
    }
}
