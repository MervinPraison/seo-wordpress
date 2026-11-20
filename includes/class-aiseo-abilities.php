<?php
/**
 * WordPress Abilities API tools for MCP Adapter clients.
 *
 * Rank Math, AIOSEO and SEOPress expose SEO actions to assistants in 2026.
 * These abilities stay honest: real site data only, no invented ranks.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Abilities {

    /**
     * Register category and abilities when WordPress 6.9+ is present.
     */
    public function init() {
        if (!function_exists('wp_register_ability') || !function_exists('wp_register_ability_category')) {
            return;
        }
        add_action('wp_abilities_api_categories_init', array($this, 'register_category'));
        add_action('wp_abilities_api_init', array($this, 'register_abilities'));
    }

    /**
     * @return bool
     */
    public static function is_supported() {
        return function_exists('wp_register_ability') && function_exists('wp_register_ability_category');
    }

    /**
     * Ability category used by every aiseo/* tool.
     */
    public function register_category() {
        wp_register_ability_category(
            'aiseo',
            array(
                'label' => __('Praison AI SEO', 'aiseo'),
                'description' => __('Read and update Praison AI SEO settings, post meta, and IndexNow.', 'aiseo'),
            )
        );
    }

    /**
     * Register discoverable tools. Each permission_callback uses a real capability.
     */
    public function register_abilities() {
        $this->register_one(
            'aiseo/get-site-seo',
            array(
                'label' => __('Get site SEO status', 'aiseo'),
                'description' => __('Returns whether schema, sitemaps, llms.txt, IndexNow and Search Console are on, plus public URLs. Does not invent search rankings.', 'aiseo'),
                'execute_callback' => array($this, 'get_site_seo'),
                'permission_callback' => array($this, 'can_read'),
                'readonly' => true,
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => new stdClass(),
                    'additionalProperties' => false,
                ),
            )
        );
        $this->register_one(
            'aiseo/get-post-seo',
            array(
                'label' => __('Get post SEO fields', 'aiseo'),
                'description' => __('Returns the saved SEO title, description, keyword, canonical and noindex flag for one post.', 'aiseo'),
                'execute_callback' => array($this, 'get_post_seo'),
                'permission_callback' => array($this, 'can_edit_given_post'),
                'readonly' => true,
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                            'description' => __('Post ID.', 'aiseo'),
                        ),
                    ),
                    'required' => array('post_id'),
                    'additionalProperties' => false,
                ),
            )
        );
        $this->register_one(
            'aiseo/update-post-seo',
            array(
                'label' => __('Update post SEO fields', 'aiseo'),
                'description' => __('Saves SEO title, description, focus keyword and/or noindex for one post. Empty strings clear the field.', 'aiseo'),
                'execute_callback' => array($this, 'update_post_seo'),
                'permission_callback' => array($this, 'can_edit_given_post'),
                'readonly' => false,
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                            'description' => __('Post ID.', 'aiseo'),
                        ),
                        'title' => array(
                            'type' => 'string',
                            'description' => __('SEO title. Omit to leave unchanged.', 'aiseo'),
                        ),
                        'description' => array(
                            'type' => 'string',
                            'description' => __('Meta description. Omit to leave unchanged.', 'aiseo'),
                        ),
                        'keyword' => array(
                            'type' => 'string',
                            'description' => __('Focus keyword. Omit to leave unchanged.', 'aiseo'),
                        ),
                        'noindex' => array(
                            'type' => 'boolean',
                            'description' => __('Whether the post should be noindex. Omit to leave unchanged.', 'aiseo'),
                        ),
                    ),
                    'required' => array('post_id'),
                    'additionalProperties' => false,
                ),
            )
        );
        $this->register_one(
            'aiseo/ping-indexnow',
            array(
                'label' => __('Ping IndexNow', 'aiseo'),
                'description' => __('Submits one URL on this site to IndexNow. The URL must belong to this WordPress site.', 'aiseo'),
                'execute_callback' => array($this, 'ping_indexnow'),
                'permission_callback' => array($this, 'can_manage_settings'),
                'readonly' => false,
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'url' => array(
                            'type' => 'string',
                            'description' => __('Absolute URL on this site.', 'aiseo'),
                        ),
                    ),
                    'required' => array('url'),
                    'additionalProperties' => false,
                ),
            )
        );
    }

    /**
     * @param string $name Ability name.
     * @param array  $args Ability args.
     */
    private function register_one($name, $args) {
        $readonly = !empty($args['readonly']);
        unset($args['readonly']);
        $args['category'] = 'aiseo';
        $args['meta'] = array(
            'public' => true,
            'show_in_rest' => true,
            'mcp' => array(
                'public' => true,
                'type' => 'tool',
            ),
            'annotations' => array(
                'readonly' => $readonly,
                'destructive' => false,
                'idempotent' => $readonly,
            ),
        );
        wp_register_ability($name, $args);
    }

    /**
     * @param mixed $input Ability input.
     * @return array
     */
    private function input_array($input) {
        if (is_object($input) && method_exists($input, 'get_params')) {
            $input = $input->get_params();
        }
        return is_array($input) ? $input : array();
    }

    /**
     * @param mixed $input Ability input.
     * @return bool
     */
    public function can_read($input = array()) {
        unset($input);
        return current_user_can('edit_posts');
    }

    /**
     * @param mixed $input Ability input.
     * @return bool
     */
    public function can_manage_settings($input = array()) {
        unset($input);
        return current_user_can('aiseo_manage_settings');
    }

    /**
     * @param mixed $input Ability input.
     * @return bool
     */
    public function can_edit_given_post($input = array()) {
        $input = $this->input_array($input);
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        return $post_id > 0 && current_user_can('edit_post', $post_id);
    }

    /**
     * @param mixed $input Ability input.
     * @return array
     */
    public function get_site_seo($input = array()) {
        unset($input);
        $feature_on = static function ($option, $default = '1') {
            return !in_array(get_option($option, $default), array(false, 0, '0', ''), true);
        };
        $gsc_connected = class_exists('AISEO_Search_Console') && (new AISEO_Search_Console())->is_connected();
        return array(
            'site_url' => home_url('/'),
            'blog_public' => get_option('blog_public') !== '0',
            'schema' => $feature_on('aiseo_enable_schema'),
            'schema_aggregation' => $feature_on('aiseo_enable_schema') && $feature_on('aiseo_enable_schema_aggregation', '0'),
            'schema_aggregation_url' => home_url('/aiseo-schema.json'),
            'sitemap' => $feature_on('aiseo_enable_sitemap'),
            'sitemap_url' => home_url('/sitemap_index.xml'),
            'llms_txt' => $feature_on('aiseo_enable_llms'),
            'llms_txt_url' => home_url('/llms.txt'),
            'llms_full' => $feature_on('aiseo_enable_llms') && $feature_on('aiseo_enable_llms_full', '0'),
            'indexnow' => $feature_on('aiseo_enable_indexnow'),
            'social_tags' => $feature_on('aiseo_enable_social_tags'),
            'search_console_connected' => $gsc_connected,
            'abilities_api' => true,
        );
    }

    /**
     * @param mixed $input Ability input.
     * @return array|WP_Error
     */
    public function get_post_seo($input = array()) {
        $input = $this->input_array($input);
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        $post = $post_id ? get_post($post_id) : null;
        if (!$post instanceof WP_Post) {
            return new WP_Error('aiseo_post_missing', __('Post not found.', 'aiseo'));
        }
        $title = get_post_meta($post_id, '_aiseo_meta_title', true);
        $description = get_post_meta($post_id, '_aiseo_meta_description', true);
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        $canonical = get_post_meta($post_id, '_aiseo_canonical', true);
        return array(
            'post_id' => $post_id,
            'edit_url' => get_edit_post_link($post_id, 'raw'),
            'permalink' => get_permalink($post_id),
            'title' => is_string($title) ? $title : '',
            'description' => is_string($description) ? $description : '',
            'keyword' => is_string($keyword) ? $keyword : '',
            'canonical' => is_string($canonical) ? $canonical : '',
            'noindex' => class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post_id),
            'nofollow' => class_exists('AISEO_Meta') && AISEO_Meta::post_is_nofollow($post_id),
        );
    }

    /**
     * @param mixed $input Ability input.
     * @return array|WP_Error
     */
    public function update_post_seo($input = array()) {
        $input = $this->input_array($input);
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('aiseo_post_missing', __('Post not found.', 'aiseo'));
        }
        if (array_key_exists('title', $input)) {
            $title = sanitize_text_field((string) $input['title']);
            if ($title === '') {
                delete_post_meta($post_id, '_aiseo_meta_title');
            } else {
                update_post_meta($post_id, '_aiseo_meta_title', $title);
            }
        }
        if (array_key_exists('description', $input)) {
            $description = sanitize_textarea_field((string) $input['description']);
            if ($description === '') {
                delete_post_meta($post_id, '_aiseo_meta_description');
            } else {
                update_post_meta($post_id, '_aiseo_meta_description', $description);
            }
        }
        if (array_key_exists('keyword', $input)) {
            $keyword = sanitize_text_field((string) $input['keyword']);
            if ($keyword === '') {
                delete_post_meta($post_id, '_aiseo_focus_keyword');
            } else {
                update_post_meta($post_id, '_aiseo_focus_keyword', $keyword);
            }
        }
        if (array_key_exists('noindex', $input)) {
            $noindex = !empty($input['noindex']);
            if ($noindex) {
                update_post_meta($post_id, '_aiseo_noindex', '1');
                update_post_meta($post_id, '_aiseo_robots_index', 'noindex');
            } else {
                delete_post_meta($post_id, '_aiseo_noindex');
                delete_post_meta($post_id, '_aiseo_robots_index');
            }
        }
        delete_transient('aiseo_dashboard_audit');
        return $this->get_post_seo(array('post_id' => $post_id));
    }

    /**
     * @param mixed $input Ability input.
     * @return array|WP_Error
     */
    public function ping_indexnow($input = array()) {
        $input = $this->input_array($input);
        $url = isset($input['url']) ? esc_url_raw((string) $input['url']) : '';
        if ($url === '') {
            return new WP_Error('aiseo_indexnow_invalid', __('A URL is required.', 'aiseo'));
        }
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $url_host = wp_parse_url($url, PHP_URL_HOST);
        if (!$site_host || !$url_host || strtolower((string) $site_host) !== strtolower((string) $url_host)) {
            return new WP_Error('aiseo_indexnow_host', __('The URL must belong to this site.', 'aiseo'));
        }
        $indexnow = new AISEO_IndexNow();
        $result = $indexnow->submit_url($url);
        if (is_wp_error($result)) {
            return $result;
        }
        $code = is_array($result) ? (int) wp_remote_retrieve_response_code($result) : 0;
        return array(
            'url' => $url,
            'http_status' => $code,
            'submitted' => $code >= 200 && $code < 300,
        );
    }
}
