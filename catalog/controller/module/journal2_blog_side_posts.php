<?php
class ControllerModuleJournal2BlogSidePosts extends Controller {

    private static $CACHEABLE = null;

    protected $data = array();

    protected function render() {
        return Front::$IS_OC2 ? $this->load->view($this->template, $this->data) : parent::render();
    }

    public function __construct($registry) {
        parent::__construct($registry);
        if (!defined('JOURNAL_INSTALLED')) {
            return;
        }
        $this->load->model('journal2/module');
        $this->load->model('journal2/blog');
        $this->load->model('tool/image');

        if (self::$CACHEABLE === null) {
            self::$CACHEABLE = (bool)$this->journal2->settings->get('config_system_settings.blog_side_posts_cache');
        }
    }

    public function index($setting) {
        if (!defined('JOURNAL_INSTALLED')) {
            return;
        }

        if (!$this->model_journal2_blog->isEnabled()) {
            return;
        }

        Journal2::startTimer(get_class($this));

        /* get module data from db */
        $module_data = $this->model_journal2_module->getModule($setting['module_id']);
        if (!$module_data || !isset($module_data['module_data']) || !$module_data['module_data']) return;

        if (Journal2Cache::$mobile_detect->isMobile() && !Journal2Cache::$mobile_detect->isTablet() && $this->journal2->settings->get('responsive_design')) return;

        $hash = isset($this->request->server['REQUEST_URI']) ? md5($this->request->server['REQUEST_URI']) : null;

        $cache_property = "module_journal_blog_side_posts_{$setting['module_id']}_{$setting['layout_id']}_{$setting['position']}_{$hash}";

        $cache = $this->journal2->cache->get($cache_property);

        if ($cache === null || self::$CACHEABLE !== true || $hash === null) {
            $module = mt_rand();

            $this->data['module'] = $module;
            $this->data['heading_title'] = Journal2Utils::getProperty($module_data, 'module_data.title.value.' . $this->config->get('config_language_id'), 'Not Translated');

            $module_type = Journal2Utils::getProperty($module_data, 'module_data.module_type', 'newest');
            $limit = Journal2Utils::getProperty($module_data, 'module_data.limit', 5);

            $posts = array();

            switch ($module_type) {
                case 'newest':
                case 'comments':
                case 'views':
                    $posts = $this->model_journal2_blog->getPosts(array(
                        'sort'          => $module_type,
                        'start'         => 0,
                        'limit'         => $limit
                    ));
                    break;
                case 'related':
                    if (isset($this->request->get['route']) && $this->request->get['route'] === 'product/product' && isset($this->request->get['product_id'])) {
                        $posts = $this->model_journal2_blog->getRelatedPosts($this->request->get['product_id'], $limit);
                    }
                    break;
            }

            if (!$posts) return;

            if (in_array($setting['position'], array('column_left', 'column_right'))) {
                $this->data['is_column'] = true;
                $this->data['grid_classes'] = 'xs-100 sm-100 md-100 lg-100 xl-100';
            } else {
                $this->data['is_column'] = false;
                $columns = in_array($setting['position'], array('top', 'bottom')) ? 0 : $this->journal2->settings->get('config_columns_count', 0);
                $this->data['grid_classes'] = Journal2Utils::getProductGridClasses(Journal2Utils::getProperty($module_data, 'items_per_row.value'), $this->journal2->settings->get('site_width', 1024), $columns);
            }

            $this->data['image_width']  = $this->journal2->settings->get('side_post_image_width', 55);
            $this->data['image_height'] = $this->journal2->settings->get('side_post_image_height', 55);

            $this->data['posts'] = array();
            foreach ($posts as $post) {
                $this->data['posts'][] = array(
                    'name'          => $post['name'],
                    'comments'      => $post['comments'],
                    'date'          => date($this->language->get('date_format_short'), strtotime($post['date'])),
                    'image'         => Journal2Utils::resizeImage($this->model_tool_image, $post, $this->data['image_width'], $this->data['image_height'], 'crop'),
                    'href'          => $this->url->link('journal2/blog/post', 'journal_blog_post_id=' . $post['post_id'])
                );
            }

            $this->template = $this->config->get('config_template') . '/template/journal2/module/blog_side_posts.tpl';

            if (self::$CACHEABLE === true) {
                $html = Minify_HTML::minify($this->render(), array(
                    'xhtml' => false,
                    'jsMinifier' => 'j2_js_minify'
                ));
                $this->journal2->cache->set($cache_property, $html);
            }
        } else {
            $this->template = $this->config->get('config_template') . '/template/journal2/cache/cache.tpl';
            $this->data['cache'] = $cache;
        }

        $output = $this->render();

        Journal2::stopTimer(get_class($this));

        return $output;
    }

}
