<?php

namespace Aponahmed\StaticPageGenerator;

/**
 * Description of adminViews
 *
 * @author Mahabub
 */
class adminViews
{

    function multipostCustomColumn($column, $post_id)
    {
        switch ($column) {
            case 'progress_of_generate':
                $total = get_post_meta($post_id, 'numberOfGenerate', true);
                $cron = get_post_meta($post_id, 'cronStatus', true);
                $generated = $this->countLinks($post_id);
                $perc = 0;
                if ($total > 0 && $generated > 0) {
                    $perc = (100 / $total) * $generated;
                }
                $manualStatus = get_post_meta($post_id, 'static_manualGenerate', true);
?>
                <div class="listControllerWprogress" data-id='<?php echo $post_id ?>'>
                    <div class="prog-wrap" id="prog<?php echo $post_id  ?>">
                        <div class="spg-progress visible">
                            <div class="spg-progress-bar" style="width: <?php echo $perc ?>%;"></div>
                        </div>
                        <div class="ingo-generate">
                            <label>Generated : <span class="done-generate"><?php echo $generated ?> </span> of <span class="total-page"><?php echo $total ?></span></label>
                            <button type="button" class="generateNow"><?php echo $manualStatus == '1' ? "Stop" : "Manual Generate" ?></button>
                            <button type="button" class="replaceGenerate"><span class="dashicons dashicons-update"></span></button>
                        </div>
                    </div>
                    <div class="actionControl">
                        <a target="_blank" href="<?php echo site_url() ?>/preview-static/<?php echo $post_id ?>/" class="preview-staticPage" onclick='previewStaticPage(this)'>Preview</a>
                        <div class="switch" title="Generate By Cron Job">
                            <input class="cronEnable" type="checkbox" <?php echo $cron == '1' ? 'checked' : ''
                                                                        ?> onchange="changeStaticCronStatus(this)" data-id="<?php echo $post_id ?>" id="c<?php echo $post_id ?>">
                            <label for="c<?php echo $post_id ?>"><span></span></label>
                        </div>
                        <button title="Clean Generated Resources" type="button" onclick="deleteStaticPages(<?php echo $post_id ?>, this)"><span class="dashicons dashicons-trash"></span></button>

                    </div>
                </div>
        <?php
                break;
        }
    }

    //put your code here
    function generateMetaBoxCallback()
    {
        global $post;
        $slugStructure = get_post_meta($post->ID, 'slugStructure', true);
        $keywordFile = get_post_meta($post->ID, 'keywordFile', true);
        $codes = $this->getShortcodes($keywordFile);

        $countData = $this->getCount($keywordFile, $post->ID);

        $codesHtm = "";
        foreach ($codes as $code) {
            $codesHtm .= "<span onclick='insertCode(this)' class='code-tool' data-code='{" . $code . "}'>{" . $code . "}</span>";
        }
        $genCount = get_post_meta($post->ID, 'numberOfGenerate', true);
        $genCount = $genCount > $countData ? $countData : $genCount;


        $generated = $this->countLinks($post->ID);
        $perc = 0;
        if ($genCount > 0 && $generated > 0) {
            $perc = (100 / $genCount) * $generated;
        }
        //var_dump($perc);
        $links = $this->getLinks($post->ID);
        ?>
        <div class="shortcodeToolbar">
            <?php echo $codesHtm ?>
            <span class="removeTool">&times;</span>
        </div>


        <div class="keywordSelector">
            <label>Keyword</label>
            <select name="keywordFile" class="custom-select">
                <option value="">Default</option>
                <?php
                $files = $this->csvFiles();
                foreach ($files as $file) {
                ?>
                    <option <?php echo $keywordFile == $file ? 'selected' : "" ?> value="<?php echo $file ?>"><?php echo $file ?></option>
                <?php
                }
                ?>
            </select>
        </div>
        <label>Total Data Found: <?php echo $countData ?></label>
        <div class="availableCodes">
            <?php echo $codesHtm ?>
        </div>
        <label>Permalink</label>
        <input type="text" id="slugStructure" name="slugStructure" value="<?php echo $slugStructure ?>" placeholder="Slug Structure" />
        <div class="generateWrap">
            <button type="button" id="generateBtn" data-progress="<?php echo $perc ?>" onclick="GenerateStaticPage(<?php echo $post->ID; ?>, this)">Generate</button>
            <input type="number" min="1" onkeyup="this.value><?php echo $countData ?>?this.value=<?php echo $countData ?>:''" max="<?php echo $countData ?>" name="numberOfGenerate" id="numberOfGenerate" value="<?php echo $genCount ? $genCount : $countData ?>" />
            <button type="button" onclick="deleteStaticPages(<?php echo $post->ID; ?>, this)"><span class="dashicons dashicons-trash"></span></button>
        </div>
        <div class="spg-progress visible">
            <div class="spg-progress-bar progDetails" style="width:<?php echo $perc ?>%"></div>
        </div>
        <div><a href='javascript:void(0)' onclick="jQuery('.generatedLinks').slideToggle('slow')">List of Links</a></div>
        <div class="generatedLinks hidden">
            <ul id="staticLinksUl">
                <?php foreach ($links as $link) : ?>
                    <?php
                    // Check if the URL does not end with a slash and does not contain ".html" at the end
                    if (substr($link, -1) !== '/' && substr($link, -5) !== '.html') {
                        // Add a forward slash at the end
                        $link = $link . '/';
                    }
                    ?>
                    <li><a href="<?php echo site_url() . "/" . $link; ?>" target="_blank"><?php echo site_url() . "/" . $link; ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php
    }

    function static_page_settings()
    {
        $options = $this->options;
        //var_dump($options);
    ?>
        <div class="wrap">
            <h2>Settings of Static Page Generator</h2>
            <hr>
            <form id="staticPageOptions">
                <div style='width:60%'>
                    <!-- <h3>General Settings</h3>

                    <label>Custom Slug</label>
                    <div class="input-wrap custom-slug-input">
                        <input type="text" name="data[static_page_custom_slug]" value="<?php //echo $options['static_page_custom_slug'] 
                                                                                        ?>">
                        <label>
                            <input type="checkbox" value="1" <?php //echo $options['custom_slug_enable'] == '1' ? 'checked' : "" 
                                                                ?> name="data[custom_slug_enable]">
                            Enable
                        </label>
                    </div>
                    <hr>
                    <label><input type="checkbox" value="1" <?php //echo $options['quick_mood'] == '1' ? 'checked' : "" 
                                                            ?> name="data[quick_mood]">Quick Mood</label>&nbsp;&nbsp; |
                    <input type="hidden" value="0" name="data[listGmood]">
                    &nbsp;&nbsp;<label><input type="checkbox" name="data[listGmood]" <?php //echo $options['listGmood'] == '1' ? 'checked' : "" 
                                                                                        ?> value="1"> Generate Mode(List) </label>
                    <input type="hidden" value="0" name="data[gmood]">;<label>Generate Mode <input type="checkbox" name="data[gmood]"  value="1"></label>
                    <hr>
                    <h3>Quick Link Generate</h3>
                    <label class='description'>ID|find:replace,find2:replace2|slug</label>
                    <textarea id="quickLinkInfo" name="data[quick_link_info]" class="quickLinkInfo"><?php //echo $options['quick_link_info'] 
                                                                                                    ?></textarea>
                    <div class="generatedLinks"></div>
                    <button class="button button-default btnGenerateQuickLink" onclick="quickLinkGenerate(this)">Generate</button>
                    <hr> -->
                    <h3>Sitemap </h3>
                    <hr><!-- comment -->
                    <div class="d-flex">
                        <div class="flex-col">
                            <strong>Dir Name</strong><br>
                            <input type="text" name="data[static_sitemap_directory]" placeholder="root" value="<?php echo $options['static_sitemap_directory'] ?>">
                        </div>
                        <div class="flex-col max-w80">
                            <strong title="Maximum Link in single Sitemap File">Max Links</strong><br>
                            <input type="text" name="data[file_max_link]" value="<?php echo $options['file_max_link'] ?>">
                        </div>
                        <div class="flex-col">
                            <strong>Sitemap Name</strong><br>
                            <input type="text" name="data[sitemapName]" value="<?php echo $options['sitemapName'] ?>">
                        </div>
                    </div>
                    <br>
                    <div class="d-flex">
                        <div class="flex-col">
                            <button type="button" onclick="generateStaticSitemap(this)" class="button button-default">Generate Sitemap</button>
                        </div>
                        <div class="flex-col">
                            <button type="button" class="button button-default" onclick="deleteSitemaps(this)"><span class="dashicons dashicons-trash"></span></button>
                        </div>
                        <div class="flex-col">
                            <a href="<?php echo get_site_url() ?>/<?php echo $options['sitemapName'] ?>.xml" id="sitemanView" target="_blank" title="Make sure you Generated Sitemap">View Sitemap</a>
                        </div>
                    </div>
                    <br><span class="description">Sitemap's other option coming from Sitemap Generator Plugin </span>
                    <hr>
                    <!-- <h3>Cron Job </h3>
                    <hr>
                    <label><strong>Interval (sec)</strong></label><br>
                    <input type="text" name="data[cronInterval]" value="<?php //echo $options['cronInterval'] 
                                                                        ?>">
                    <hr> -->
                    <button type="submit" class="button button-primary ctgUpdModBtn">Update Option</button>
                </div>
            </form>
        </div>
        <script>
            (function(_) {
                _("#staticPageOptions").on('submit', function(e) {
                    _('.ctgUpdModBtn').html('<span class="dashicons dashicons-update loading"></span> Updating');
                    e.preventDefault();
                    let formData = _(e.target).serialize();
                    _.post(ajaxurl, {
                        action: 'staticPageOptionsStore',
                        formdata: formData
                    }, function(response) {
                        _('.ctgUpdModBtn').html('<span class="dashicons dashicons-saved"></span> Updated');
                        setTimeout(function() {
                            _('.ctgUpdModBtn').html('Update');
                        }, 2000);
                    });
                });
            })(jQuery);

            function generateStaticSitemap(_this) {
                (function(_) {
                    _(_this).html('<span class="dashicons dashicons-update loading"></span> generating');
                    _.post(ajaxurl, {
                        action: 'generateStaticSitemap'
                    }, function(response) {
                        _("#sitemanView").attr('href', response.file).show();
                        _(_this).html('<span class="dashicons dashicons-saved"></span> Generated');
                        setTimeout(function() {
                            _(_this).html('Generate');
                        }, 2000);
                    });
                })(jQuery);
            }

            function deleteSitemaps(_this) {
                let _ = jQuery;
                _(_this).html('<span class="dashicons dashicons-update loading"></span> Deleting');
                _.post(ajaxurl, {
                    action: 'deleteSitemaps'
                }, function(response) {
                    _(_this).html('<span class="dashicons dashicons-saved"></span> Deleted');
                    setTimeout(function() {
                        _(_this).html('<span class="dashicons dashicons-trash"></span>');
                    }, 2000);
                });
            }
        </script>
    <?php
    }

    function static_page_keywords()
    {
    ?>
        <div class="wrap">
            <h2>KeyWords</h2>
            <hr>
            <nav class="nav-tab-wrapper">
                <a href="#csvFiles" class="nav-tab key-tab nav-tab-active">CSVs</a>
                <a href="#systemKeys" class="nav-tab key-tab">Keywords</a>
            </nav>
            <div class="key-tab-content">
                <div class="key-tab-item" id="systemKeys">
                    <div class="keyword-manage">
                        <div class="keyword-groups">
                            <span class="newKey4Static">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512">
                                    <title>Add New</title>
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 112v288M400 256H112" />
                                </svg>
                                Add New
                            </span>
                            <ul class="keyGroupList">
                                <?php
                                $files = $this->keyFiles();
                                if ($files) {
                                    foreach ($files as $file) {
                                        //var_dump($fileinfo);
                                        echo "<li data-name='$file'><span class='removeList'>&times;</span>$file</li>";
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="keyword-keys">
                            <textarea id="codeEditor"></textarea>
                            <button class="update-keyData" type="button">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512">
                                    <title>Update</title>
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M416 128L192 384l-96-96" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="key-tab-item active" id="csvFiles">
                    <div class="keyword-manage">
                        <div class="keyword-groups">
                            <input style="display: none" onchange="uploadCsvFile2Server(this)" type="file" id="uploadCsv">
                            <label class="uploadSvg" for="uploadCsv">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512">
                                    <title>Cloud Upload</title>
                                    <path d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.06-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.88-112 106.4s54 106.4 120 106.4h56" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" />
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64M256 448.21V207.79" />
                                </svg>
                                Upload
                                <span class="csv-progress"></span>
                            </label>
                            <ul class="svgFileList">
                                <?php
                                $files = $this->csvFiles();
                                if ($files) {
                                    foreach ($files as $file) {
                                        echo "<li data-name='$file' class='csvList' onclick='loadCsv(this)'><span onclick='downloadCsv(this)' class='dwnCsv'><svg xmlns='http://www.w3.org/2000/svg' class='ionicon' viewBox='0 0 512 512'><path fill='none' stroke='currentColor' stroke-linecap='round' stroke-linejoin='round' stroke-width='48' d='M112 268l144 144 144-144M256 392V100'/></svg></span><span class='removeList' onclick='removeCsv(this)'>&times;</span>$file</li>";
                                    }
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="csv-wrap">
                            <div class="svg-data"></div>
                            <button class="update-csvData" type="button">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ionicon" viewBox="0 0 512 512">
                                    <title>Update</title>
                                    <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M416 128L192 384l-96-96" />
                                </svg>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <script>
            let keyWordManager = new keyWordManage({
                newList: jQuery(".newKey4Static"),
                listDom: jQuery(".keyGroupList"),
                textarea: jQuery("#codeEditor"),
                updateBtn: jQuery(".update-keyData"),
            });
            keyWordManager.init();
        </script>
<?php
    }
}
