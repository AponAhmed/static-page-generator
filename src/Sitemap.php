<?php

namespace Aponahmed\StaticPageGenerator;

/**
 * Description of sitemap
 *
 * @author Mahabub
 */
class Sitemap {

    public $fileName = "static";
    private $sitemapOption;
    private $options;
    public $tempData;
    public $dataMap;
    public string $dirName;
    public int $fileCount;
    public $n = 0;
    public $HtmlSidebar = [];
    public $sitemapFiles = [];

    //put your code here
    public function __construct($options) {
        $this->siteUrl = site_url();
        $this->options = $options;
        $this->fileName = $this->options['sitemapName'];
        $this->dirName = $this->options['static_sitemap_directory'];

        //Making of Folder where store xml files
        $this->get_optionSitemap();
        if (!empty($this->dirName) && !is_dir(ABSPATH . $this->dirName)) {
            mkdir(ABSPATH . $this->dirName, 0777, true);
            chmod(ABSPATH . $this->dirName, 0777);
            file_put_contents(ABSPATH . $this->dirName . "/index.php", "<?php //Silence is golden");
        }
        //echo "<pre>";
        //var_dump($this->sitemapOption);
        //exit;
    }

    /**
     * Get Options 
     */
    function get_optionSitemap() {
        $defaultOption = [
            'sitemap_max_links' => 1000,
            'post_types' => ['page'],
            'taxonomies' => []
        ];
        $otherField = get_option('sitemap_options');
        if ($otherField != "") {
            $this->sitemapOption = json_decode($otherField, true);
        } else {
            $this->sitemapOption = $defaultOption;
        }
        $postTypes = get_option('sitemap_post_types');
        if ($postTypes != "") {
            $this->sitemapOption['post_types'] = json_decode($postTypes, true);
        }
        $taxonomies = get_option('sitemap_taxonomies');
        if ($taxonomies != "") {
            $taxx = json_decode($taxonomies, true);
            $taxx = !is_array($taxx) ? array() : $taxx;
            $this->sitemapOption['taxonomies'] = array_unique($taxx);
        }
    }

    /**
     * Get Depth Of URL 
     * @param String $url
     * @return int 
     */
    function getDepth($url) {
        $RQURI = str_replace($this->siteUrl, "", $url);
        $parts = explode("/", $RQURI);
        $parts = array_unique(array_filter(array_map('trim', $parts)));
        return count($parts);
    }

    /**
     * Generate XML
     * @param type $main
     * @return boolean
     */
    public function generateXml($main = false) {
        $doc = new \DOMDocument('1.0', "UTF-8");
        $doc->formatOutput = true;

        if ($main) {
            $urlSet = $doc->createElement("sitemapindex");
        } else {
            $urlSet = $doc->createElement("urlset");
        }

        $attArr = [
            'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation' => 'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd',
            'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9'
        ];

        //XML Processing
        if (isset($this->sitemapOption['enable_priority']) && $this->sitemapOption['enable_priority'] == '1' && isset($this->sitemapOption['enable_change_freq']) && $this->sitemapOption['enable_change_freq'] == '1') {
            $stylePath = $this->siteUrl . "/wp-content/plugins/sitemap-generator/assets/xml-styles/xml-sitemap.xsl";
        } elseif (isset($this->sitemapOption['enable_priority']) && $this->sitemapOption['enable_priority'] != '1' && isset($this->sitemapOption['enable_change_freq']) && $this->sitemapOption['enable_change_freq'] != '1') {
            $stylePath = $this->siteUrl . "/wp-content/plugins/sitemap-generator/assets/xml-styles/xml-sitemapno.xsl";
        } elseif (isset($this->sitemapOption['enable_priority']) && $this->sitemapOption['enable_priority'] == '1') {
            $stylePath = $this->siteUrl . "/wp-content/plugins/sitemap-generator/assets/xml-styles/xml-sitemapprio.xsl";
        } else {
            $stylePath = $this->siteUrl . "/wp-content/plugins/sitemap-generator/assets/xml-styles/xml-sitemapfreq.xsl";
        }
        if ($main) {
            $stylePath = $this->siteUrl . "/wp-content/plugins/sitemap-generator/assets/xml-styles/xml-sitemapset.xsl";
        }

        $stylePath = preg_replace('/([^:])(\/{2,})/', '$1/', $stylePath);
        $xslt = $doc->createProcessingInstruction('xml-stylesheet', ' type="text/xsl" href="' . $stylePath . '"');
        $doc->appendChild($xslt);

        //Creating Attributes
        foreach ($attArr as $key => $value) {
            $attr = $doc->createAttribute($key);
            $attr->value = $value;
            $urlSet->appendChild($attr);
        }
        //Add Attributes
        $doc->appendChild($urlSet);

        //URL Loop
        foreach ($this->tempData as $id => $link) {
            //$link = $info[0];
            //----------------
            if ($main) {
                $url = $doc->createElement("sitemap");
            } else {
                $url = $doc->createElement("url");
            }

            $depth = $this->getDepth($link);
            $depthIndex = "dpth$depth";

            $defaultLastMod = date(DATE_ATOM);
            if (isset($info[2]) && !empty($info[2])) {
                $defaultLastMod = date(DATE_ATOM, strtotime($info[2]));
            }
            if (isset($this->sitemapOption['sitemap_last_modified']) && !empty($this->sitemapOption['sitemap_last_modified'])) {
                $defaultLastMod = date(DATE_ATOM, strtotime($this->sitemapOption['sitemap_last_modified']));
            }
            //$defaultLastMod = !isset($this->sitemapOption['sitemap_last_modified']) || empty($this->sitemapOption['sitemap_last_modified']) ? $defaultLastMod : date(DATE_ATOM, strtotime($this->options['sitemap_last_modified']));
            //Required Elements
            $urleElements = [
                'loc' => $link,
                'lastmod' => $defaultLastMod,
            ];

            if (!$main) { //This Section not Apply for Main Sitemap File
                //ChangeFreq [optional]
                if (isset($this->sitemapOption['enable_change_freq']) && $this->sitemapOption['enable_change_freq'] == "1") {
                    $urleElements['changefreq'] = $this->sitemapOption['sitemapFreqDpth'][$depthIndex];
                }
                //Priority [optional]
                if (isset($this->sitemapOption['enable_priority']) && $this->sitemapOption['enable_priority'] == "1") {
                    $urleElements['priority'] = $this->sitemapOption['sitemapPriorityDpth'][$depthIndex];
                }
            }

            foreach ($urleElements as $el => $val) {
                $e = $doc->createElement($el);
                $e->appendChild($doc->createTextNode($val));
                $url->appendChild($e);
            }

            //Final Append
            $urlSet->appendChild($url);
        }
        //$this->dirName = $this->dirName == 'root' ? "" : $this->dirName;
        if (!$main) {
            $fileNumber = $this->fileCount ? $this->fileCount : "";
            $fileName = ABSPATH . $this->dirName . "/" . "static-" . $this->fileName . $fileNumber;

            //trake files for further action such as delete
            $this->sitemapFiles[] = $this->dirName . "/" . "static-" . $this->fileName . $fileNumber . ".xml"; //

            if ($doc->save($fileName . ".xml")) {
                $fileUri = $this->siteUrl . "/" . $this->dirName . "/" . "static-" . $this->fileName . $fileNumber . ".xml";
                return $this->trimSlash($fileUri);
            } else {
                return false;
            }
        } else {
            $fileName = ABSPATH . $this->fileName;
            //trake files for further action such as delete
            $this->sitemapFiles[] = $this->fileName . ".xml"; //

            if ($doc->save($fileName . ".xml")) {
                $fileUri = $this->siteUrl . "/" . $this->fileName . ".xml";
                return $this->trimSlash($fileUri);
            }
        }
        return false;
    }

    /**
     * REmove Double Slash from URL
     * @param type $url
     * @return type
     */
    function trimSlash($url) {
        return preg_replace('/([^:])(\/{2,})/', '$1/', $url);
    }

    /**
     * Html Sitemap Generate 
     */
    function handleHtmlGenerate() {
        $sidebarHtml = implode("\n", $this->HtmlSidebar); //Html Of Sidebar

        $bodyHtml = "";
        $fileNumber = $this->fileCount ? $this->fileCount : "";
        $path = $this->dirName . "/" . "static-" . $this->fileName . $fileNumber . ".html";

        foreach ($this->tempData as $id => $link) {
            $bodyHtml .= "<li><a href=\"$link\"><span class=\"title\">$link</span></a></li>";
        }

        //var_dump($bodyHtml);
        $dataHtmlSideBarAct = str_replace($path . "\"", $path . "\" " . " class='active'", $sidebarHtml);
        $this->generateHtmlFile($bodyHtml, $path, $dataHtmlSideBarAct);
        //Body Html
    }

    function generateHtml($dataMap, $sidebarhtml) {
        $sidebarhtml = implode("\n", $sidebarhtml); //Html Of Sidebar
        $n = 0;
        foreach ($dataMap as $id => $singleItem) {
            if (isset($singleItem['linksgroup']) && is_array($singleItem['linksgroup']) && count($singleItem['linksgroup']) > 0) {
                foreach ($singleItem['linksgroup'] as $k => $links) {
                    $fileName = "";
                    if ($n != 0) {
                        if ($this->dirName == '') {
                            $fileName .= "static-";
                        } else {
                            $fileName .= $this->dirName . "/static-";
                        }
                    }

                    $name = $singleItem['name'];
                    $fileName .= sanitize_title($name);
                    if ($k != 0) {
                        $fileName = $fileName . $k;
                    }
                    $bodyHtml = "";
                    foreach ($links as $link) {
                        $bodyHtml .= "<li><a href=\"$link\"><span class=\"title\">$link</span></a></li>";
                    }
                    if ($n == 0) {
                        //$fileName = "static-sitemap";
                        $fileName = $this->fileName;
                    }

                    $dataHtmlSideBarAct = str_replace($fileName . ".html\"", $fileName . ".html\" " . " class='active'", $sidebarhtml);

                    $n++;
                    $this->sitemapFiles[] = $fileName . ".html";
                    $this->generateHtmlFile($bodyHtml, $fileName . ".html", $dataHtmlSideBarAct);
                }
            }
        }
    }

    function generateHtmlFile($PageItemHtml = "", $fileName = "static", $sidebar = "") {
        //print_r($doc);exit;
        //===============HTML==================
        $siteName = get_bloginfo();
        $aditionalMeta = "";
        //if (!$sidebar) {
        $canonical = $this->trimSlash($this->siteUrl . "/$fileName");
        //File Info
        $pInfo = pathinfo($fileName);
        $namyfy = "";
        if ($pInfo) {
            $namyfy = ucwords(str_replace("-", " ", $pInfo['filename']));
        }
        $aditionalMeta .= "<meta name=\"robots\" content=\"noarchive\">";
        $aditionalMeta .= "<meta name=\"robots\" content=\"noindex\">";
        $aditionalMeta .= "<link rel=\"canonical\" href=\"$canonical\">";
        //}
        $desc = "sitemap - $namyfy";
        $htmlData = "<!DOCTYPE html>
<html>
	<head>
                <meta charset=\"UTF-8\">
		<title>$namyfy - Sitemap</title>
		<meta id=\"MetaDescription\" name=\"description\" content=\"$desc\" />
		<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />
		<meta content=\"Xml Sitemap Generator .org\" name=\"Author\" />
                $aditionalMeta
		<style>
*, a {color: #444 !important;}
body, head, #xsg {margin:0px 0px 0px 0px; line-height:22px; color:#666666; width:100%; padding:0px 0px 0px 0px;font-family : Tahoma, Verdana,   Arial, sans-serif; font-size:13px;max-width: 1100px;margin: auto;}

.logo{padding: 15px;max-width: 55px;background: #f6f6f6;border-radius: 50%;}
#xsg ul li a {font-weight:bold; }
#xsg ul ul li a {font-weight:normal; }
#xsg a {text-decoration:none; }
#xsg p {margin:10px 0px 10px 0px;}
#xsg ul {list-style:square; }
#xsg li {margin: 5px 0;}
#xsg th { text-align:left;font-size: 0.9em;padding:2px 10px 2px 2px; border-bottom:1px solid #CCCCCC; border-collapse:collapse;}
#xsg td { text-align:left;font-size: 0.9em; padding:2px 10px 2px 2px; border-bottom:1px solid #CCCCCC; border-collapse:collapse;}
			
#xsg .title {font-size: 0.9em;  color:#132687;  display:inline;}
#xsg .url {font-size: 0.7em; color:#999999;}			
#xsgHeader { width:100%;  margin:0px 0px 5px 0px; border-bottom: 1px solid #f6f6f6;}
#xsgHeader h1 {  padding:0px 0px 0px 20px ; }
#xsgHeader h1 a {color:#132687; font-size:14px; text-decoration:none;cursor:default;}
#xsgBody {padding: 10px 0;display: flex;}
.xsgContent {height: 100%;overflow: hidden;flex-grow: 1;max-width: 75%;}
#xsgFooter { color:#999999; width:100%;  margin:20px 0px 15px 0px; border-top:1px solid #999999;padding: 10px 0px 10px 0px; }
#xsgFooter a {color:#999999; font-size:11px; text-decoration:none;   }    
#xsgFooter span {color:#999999; font-size:11px; text-decoration:none; margin-left:20px; }
.xsgSidebar {flex-basis: 25%;max-width: 25%;}
.xsgSidebar ul {padding: 0;}
.xsgSidebar ul li a {padding: 8px;display: block;border-bottom: 1px solid #eee;font-weight: normal !important; font-size: 16px;text-transform: capitalize;position: relative;padding-left: 12px;}
.xsgSidebar ul li a::before {content: \"\";position: absolute;left: 3px;top: 0; border: 5px solid transparent;border-left-color: #999;top: calc(50% - 5px);}
.xsgSidebar ul li a::after {content: \"\";position: absolute;left: 2px;top: 0;border: 5px solid transparent;border-left-color: transparent;border-left-color: #fff;top: calc(50% - 5px);}
.xsgSidebar ul li {margin: 0 !important;list-style: none;}
.xsgSidebar ul li a.active {background: #f6f6f6;}
#xsgHeader h2 {display: flex;justify-content: space-between;align-items: center;margin: 40px 0;}
#xsgHeader h2 p {font-size: 14px;font-style: italic;font-weight: 300;}
#xsgHeader h2 span {font-size: 28px;padding: 15px 0;position: relative;}
#xsgHeader h2 span::before {content: \"TM\";position: absolute;right: -16px;top: 3px;font-size: 12px;font-weight: 300;line-height: 1;}
		</style>
	</head>
	<body>
            <div id=\"xsg\">
                <div id=\"xsgHeader\">
                    <h1 style='display:none'>H1</h1>
                    <h2>
                        <span>$siteName</span>
                       <p>A Private Label Clothing Manufacturer in Bangladesh Since 1987</p>
                    </h2>
                </div>
                <div id=\"xsgBody\">";
        if ($sidebar) {
            $htmlData .= "<div class=\"xsgSidebar\">
                        <ul>$sidebar</ul>
                    </div>";
        }
        $htmlData .= "<div class=\"xsgContent\">
                        <ul>
                        $PageItemHtml
                        </ul>
                    </div>
                </div>
                <div id=\"xsgFooter\">
                    <span>$siteName HTML Sitemap</span>
                </div>
            </div>
	</body>
</html>";
        $resHtml = file_put_contents(ABSPATH . "$fileName", $htmlData);
    }

}
