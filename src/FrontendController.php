<?php

namespace Aponahmed\StaticPageGenerator;

/**
 * Description of FrontendController
 *
 * @author Mahabub
 */
class FrontendController {

    private $options;

    //put your code here
    public function __construct($options) {
        $this->options = $options;
        $gMode = get_option('staticGmood');
        //var_dump($gMode);
        //exit;
        if (!is_admin()) {//Testing 
            //sleep(10);
        }
        if ($gMode !== '1' && $this->options['listGmood'] == '0') {
            add_action('template_redirect', [$this, 'disableCustomPostType']);
        }
    }

    function disableCustomPostType($template) {
        $post = get_queried_object();
        if ($post instanceof \WP_Post && $post->post_type == $this->options['postType']) {
            wp_redirect(home_url());
        }
        return $template;
    }

}
