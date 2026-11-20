<?php
/**
 * WordPress Abilities API tools for the official MCP Adapter.
 *
 * Registration follows WordPress 6.9 plus MCP Adapter 0.7:
 * category on wp_abilities_api_categories_init, abilities on wp_abilities_api_init,
 * typed input/output schemas, meta.public + meta.show_in_rest (6.9 REST),
 * and meta.mcp.public for the default MCP server.
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
     * Official WordPress MCP Adapter plugin (mcp-adapter).
     *
     * @return bool
     */
    public static function adapter_is_active() {
        if (class_exists('WP\MCP\Core\McpAdapter') || class_exists('WP\MCP\Plugin')) {
            return true;
        }
        if (defined('MCP_ADAPTER_VERSION')) {
            return true;
        }
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('mcp-adapter/mcp-adapter.php');
    }

    /**
     * Default MCP HTTP endpoint when the official adapter is active.
     *
     * @return string
     */
    public static function adapter_endpoint() {
        return rest_url('mcp/mcp-adapter-default-server');
    }

    /**
     * Abilities REST list URL (WordPress 6.9).
     *
     * @return string
     */
    public static function rest_list_url() {
        return rest_url('wp-abilities/v1/abilities?category=aiseo');
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
                    'additionalProperties' => false,
                    'default' => array(),
                ),
                'output_schema' => $this->site_seo_output_schema(),
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
                'output_schema' => $this->post_seo_output_schema(),
            )
        );
        $this->register_one(
            'aiseo/list-posts-seo',
            array(
                'label' => __('List post SEO fields', 'aiseo'),
                'description' => __('Returns SEO title, description, keyword and noindex for recent posts of one type. Does not invent search rankings.', 'aiseo'),
                'execute_callback' => array($this, 'list_posts_seo'),
                'permission_callback' => array($this, 'can_read'),
                'readonly' => true,
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type' => 'string',
                            'description' => __('Post type slug. Default post.', 'aiseo'),
                            'default' => 'post',
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => __('How many posts to return, 1–20. Default 10.', 'aiseo'),
                            'minimum' => 1,
                            'maximum' => 20,
                            'default' => 10,
                        ),
                    ),
                    'additionalProperties' => false,
                ),
                'output_schema' => array(
                    'type' => 'object',
                    'required' => array('post_type', 'posts'),
                    'additionalProperties' => false,
                    'properties' => array(
                        'post_type' => array(
                            'type' => 'string',
                        ),
                        'posts' => array(
                            'type' => 'array',
                            'items' => $this->post_seo_output_schema(),
                        ),
                    ),
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
                'output_schema' => $this->post_seo_output_schema(),
            )
        );
        $this->register_one(
            'aiseo/generate-post-seo',
            array(
                'label' => __('Generate post SEO fields', 'aiseo'),
                'description' => __('Suggests an SEO title and/or meta description from the post content. Does not save unless you then call update-post-seo. Does not invent search rankings.', 'aiseo'),
                'execute_callback' => array($this, 'generate_post_seo'),
                'permission_callback' => array($this, 'can_edit_given_post'),
                'readonly' => false,
                'input_schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                            'description' => __('Post ID.', 'aiseo'),
                        ),
                        'fields' => array(
                            'type' => 'array',
                            'description' => __('Which fields to generate. Default title and description.', 'aiseo'),
                            'items' => array(
                                'type' => 'string',
                                'enum' => array('title', 'description'),
                            ),
                            'default' => array('title', 'description'),
                        ),
                    ),
                    'required' => array('post_id'),
                    'additionalProperties' => false,
                ),
                'output_schema' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'post_id' => array(
                            'type' => 'integer',
                        ),
                        'title' => array(
                            'type' => 'string',
                        ),
                        'description' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
            )
        );
        $this->register_one(
            'aiseo/sitemap',
            array(
                'label' => __('XML sitemap URL', 'aiseo'),
                'description' => __('The public XML sitemap index URL when sitemaps are enabled.', 'aiseo'),
                'execute_callback' => array($this, 'get_sitemap_resource'),
                'permission_callback' => array($this, 'can_read'),
                'readonly' => true,
                'mcp_type' => 'resource',
                'uri' => home_url('/sitemap_index.xml'),
                'output_schema' => array(
                    'type' => 'object',
                    'required' => array('url', 'enabled'),
                    'additionalProperties' => false,
                    'properties' => array(
                        'url' => array(
                            'type' => 'string',
                        ),
                        'enabled' => array(
                            'type' => 'boolean',
                        ),
                    ),
                ),
            )
        );
        $this->register_one(
            'aiseo/llms-txt',
            array(
                'label' => __('llms.txt URL', 'aiseo'),
                'description' => __('The public llms.txt URL for AI assistants when that file is enabled.', 'aiseo'),
                'execute_callback' => array($this, 'get_llms_resource'),
                'permission_callback' => array($this, 'can_read'),
                'readonly' => true,
                'mcp_type' => 'resource',
                'uri' => home_url('/llms.txt'),
                'output_schema' => array(
                    'type' => 'object',
                    'required' => array('url', 'enabled'),
                    'additionalProperties' => false,
                    'properties' => array(
                        'url' => array(
                            'type' => 'string',
                        ),
                        'enabled' => array(
                            'type' => 'boolean',
                        ),
                    ),
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
                'output_schema' => array(
                    'type' => 'object',
                    'required' => array('url', 'http_status', 'submitted'),
                    'additionalProperties' => false,
                    'properties' => array(
                        'url' => array(
                            'type' => 'string',
                        ),
                        'http_status' => array(
                            'type' => 'integer',
                        ),
                        'submitted' => array(
                            'type' => 'boolean',
                        ),
                    ),
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
        $mcp_type = isset($args['mcp_type']) ? (string) $args['mcp_type'] : 'tool';
        $uri = isset($args['uri']) ? (string) $args['uri'] : '';
        unset($args['readonly'], $args['mcp_type'], $args['uri']);
        if (!in_array($mcp_type, array('tool', 'resource', 'prompt'), true)) {
            $mcp_type = 'tool';
        }
        $args['category'] = 'aiseo';
        $args['meta'] = array(
            'public' => true,
            'show_in_rest' => true,
            'mcp' => array(
                'public' => true,
                'type' => $mcp_type,
            ),
            'annotations' => array(
                'readonly' => $readonly,
                'destructive' => false,
                'idempotent' => $readonly,
            ),
        );
        if ($uri !== '') {
            $args['meta']['uri'] = $uri;
        }
        wp_register_ability($name, $args);
    }

    /**
     * @return array
     */
    private function post_seo_output_schema() {
        return array(
            'type' => 'object',
            'required' => array('post_id', 'title', 'description', 'keyword', 'canonical', 'noindex', 'nofollow'),
            'additionalProperties' => false,
            'properties' => array(
                'post_id' => array(
                    'type' => 'integer',
                ),
                'edit_url' => array(
                    'type' => array('string', 'null'),
                ),
                'permalink' => array(
                    'type' => array('string', 'null'),
                ),
                'title' => array(
                    'type' => 'string',
                ),
                'description' => array(
                    'type' => 'string',
                ),
                'keyword' => array(
                    'type' => 'string',
                ),
                'canonical' => array(
                    'type' => 'string',
                ),
                'noindex' => array(
                    'type' => 'boolean',
                ),
                'nofollow' => array(
                    'type' => 'boolean',
                ),
            ),
        );
    }

    /**
     * @return array
     */
    private function site_seo_output_schema() {
        $bool = array('type' => 'boolean');
        $url = array('type' => 'string');
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'site_url' => $url,
                'blog_public' => $bool,
                'schema' => $bool,
                'schema_aggregation' => $bool,
                'schema_aggregation_url' => $url,
                'sitemap' => $bool,
                'sitemap_url' => $url,
                'llms_txt' => $bool,
                'llms_txt_url' => $url,
                'llms_full' => $bool,
                'indexnow' => $bool,
                'social_tags' => $bool,
                'search_console_connected' => $bool,
                'abilities_api' => $bool,
                'mcp_adapter' => $bool,
                'mcp_endpoint' => $url,
                'wp_ai_client' => $bool,
            ),
        );
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
            'mcp_adapter' => self::adapter_is_active(),
            'mcp_endpoint' => self::adapter_is_active() ? self::adapter_endpoint() : '',
            'wp_ai_client' => class_exists('AISEO_WP_AI') && AISEO_WP_AI::is_ready(),
        );
    }

    /**
     * @param mixed $input Ability input.
     * @return array|WP_Error
     */
    public function generate_post_seo($input = array()) {
        $input = $this->input_array($input);
        $post_id = isset($input['post_id']) ? absint($input['post_id']) : 0;
        $post = $post_id ? get_post($post_id) : null;
        if (!$post instanceof WP_Post) {
            return new WP_Error('aiseo_post_missing', __('Post not found.', 'aiseo'));
        }
        $fields = isset($input['fields']) && is_array($input['fields']) ? $input['fields'] : array('title', 'description');
        $fields = array_values(array_intersect($fields, array('title', 'description')));
        if ($fields === array()) {
            $fields = array('title', 'description');
        }
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        $keyword = is_string($keyword) ? $keyword : '';
        $content = $post->post_title . "\n\n" . wp_strip_all_tags((string) $post->post_content);
        $api = new AISEO_API();
        $out = array(
            'post_id' => $post_id,
        );
        if (in_array('title', $fields, true)) {
            $title = $api->generate_title($content, $keyword);
            if (is_wp_error($title)) {
                return $title;
            }
            $out['title'] = (string) $title;
        }
        if (in_array('description', $fields, true)) {
            $description = $api->generate_meta_description($content, $keyword);
            if (is_wp_error($description)) {
                return $description;
            }
            $out['description'] = (string) $description;
        }
        return $out;
    }

    /**
     * @param mixed $input Ability input.
     * @return array
     */
    public function get_sitemap_resource($input = array()) {
        unset($input);
        $on = !in_array(get_option('aiseo_enable_sitemap', '1'), array(false, 0, '0', ''), true);
        return array(
            'url' => home_url('/sitemap_index.xml'),
            'enabled' => $on,
        );
    }

    /**
     * @param mixed $input Ability input.
     * @return array
     */
    public function get_llms_resource($input = array()) {
        unset($input);
        $on = !in_array(get_option('aiseo_enable_llms', '1'), array(false, 0, '0', ''), true);
        return array(
            'url' => home_url('/llms.txt'),
            'enabled' => $on,
        );
    }

    /**
     * @param mixed $input Ability input.
     * @return array|WP_Error
     */
    public function list_posts_seo($input = array()) {
        $input = $this->input_array($input);
        $post_type = isset($input['post_type']) ? sanitize_key((string) $input['post_type']) : 'post';
        if ($post_type === '' || !post_type_exists($post_type)) {
            return new WP_Error('aiseo_post_type', __('Unknown post type.', 'aiseo'));
        }
        $limit = isset($input['limit']) ? absint($input['limit']) : 10;
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 20) {
            $limit = 20;
        }
        $ids = get_posts(array(
            'post_type' => $post_type,
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));
        $posts = array();
        foreach ($ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }
            $item = $this->get_post_seo(array('post_id' => (int) $post_id));
            if (!is_wp_error($item)) {
                $posts[] = $item;
            }
        }
        return array(
            'post_type' => $post_type,
            'posts' => $posts,
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
        $edit_url = get_edit_post_link($post_id, 'raw');
        $permalink = get_permalink($post_id);
        return array(
            'post_id' => $post_id,
            'edit_url' => is_string($edit_url) ? $edit_url : null,
            'permalink' => is_string($permalink) ? $permalink : null,
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
