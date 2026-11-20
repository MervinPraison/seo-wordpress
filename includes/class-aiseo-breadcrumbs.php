<?php
/**
 * AISEO Breadcrumbs
 *
 * Provides visual breadcrumb navigation with schema markup
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Breadcrumbs {
    
    /**
     * Option key for breadcrumbs settings
     */
    const OPTION_KEY = 'aiseo_breadcrumbs_settings';
    
    /**
     * Default settings
     */
    private $defaults = array(
        'enabled' => true,
        'separator' => '»',
        'home_text' => 'Home',
        'show_home' => true,
        'show_current' => true,
        'bold_current' => true,
        'show_on_home' => false,
        'show_in_content' => false,
        'wrapper_class' => 'aiseo-breadcrumbs',
        'schema_enabled' => true,
    );
    
    /**
     * Initialize breadcrumbs
     */
    /**
     * Category used for breadcrumbs and schema.
     * A saved primary category is used only when the post is actually in it.
     *
     * @param int $post_id Post ID.
     * @return WP_Term|null
     */
    public static function primary_category($post_id = 0) {
        $post_id = $post_id ? (int) $post_id : (int) get_the_ID();
        if (!$post_id) {
            return null;
        }
        $categories = get_the_category($post_id);
        if (empty($categories) || is_wp_error($categories)) {
            return null;
        }
        $primary_id = (int) get_post_meta($post_id, '_aiseo_primary_category', true);
        if ($primary_id) {
            foreach ($categories as $category) {
                if ((int) $category->term_id === $primary_id) {
                    return $category;
                }
            }
        }
        return $categories[0];
    }

    /**
     * Title used in the breadcrumb trail for a post.
     *
     * @param int|WP_Post $post Post ID or object.
     * @return string
     */
    public static function post_title($post = 0) {
        $post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
        if (!$post_id) {
            $post_id = (int) get_the_ID();
        }
        $custom = $post_id ? get_post_meta($post_id, '_aiseo_breadcrumb_title', true) : '';
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }
        return get_the_title($post_id);
    }

    public function init() {
        add_shortcode('aiseo_breadcrumbs', array($this, 'shortcode'));
        add_action('aiseo_breadcrumbs', array($this, 'display'));
        add_filter('the_content', array($this, 'prepend_to_content'), 12);
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor'));
    }

    /**
     * Gutenberg block, matching Yoast and Rank Math breadcrumb blocks.
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        register_block_type('aiseo/breadcrumbs', array(
            'api_version' => 2,
            'title' => __('AISEO Breadcrumbs', 'aiseo'),
            'description' => __('Breadcrumb trail for the current page.', 'aiseo'),
            'category' => 'widgets',
            'icon' => 'arrow-right-alt',
            'attributes' => array(
                'separator' => array(
                    'type' => 'string',
                    'default' => '',
                ),
                'homeText' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
            'render_callback' => array($this, 'render_block'),
        ));
    }

    /**
     * Editor script for the breadcrumbs block.
     */
    public function enqueue_block_editor() {
        wp_enqueue_script(
            'aiseo-breadcrumbs-block',
            AISEO_PLUGIN_URL . 'admin/js/aiseo-breadcrumbs-block.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'),
            AISEO_VERSION,
            true
        );
        wp_localize_script('aiseo-breadcrumbs-block', 'aiseoBreadcrumbsBlock', array(
            'i18n' => array(
                'title' => __('AISEO Breadcrumbs', 'aiseo'),
                'description' => __('Breadcrumb trail for the current page.', 'aiseo'),
                'settings' => __('Breadcrumb settings', 'aiseo'),
                'separator' => __('Separator', 'aiseo'),
                'home' => __('Home label', 'aiseo'),
                'placeholder' => __('Breadcrumb trail', 'aiseo'),
            ),
        ));
    }

    /**
     * @param array $attributes Block attributes.
     * @return string
     */
    public function render_block($attributes) {
        $args = array();
        if (!empty($attributes['separator']) && is_string($attributes['separator'])) {
            $args['separator'] = sanitize_text_field($attributes['separator']);
        }
        if (!empty($attributes['homeText']) && is_string($attributes['homeText'])) {
            $args['home_text'] = sanitize_text_field($attributes['homeText']);
        }
        return $this->render($args);
    }

    /**
     * Print the trail at the start of a singular post when the setting is on.
     *
     * @param string $content Post content.
     * @return string
     */
    public function prepend_to_content($content) {
        if (!is_string($content) || !is_singular() || is_feed() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $settings = $this->get_settings();
        if (empty($settings['show_in_content']) || empty($settings['enabled'])) {
            return $content;
        }
        if (strpos($content, 'aiseo-breadcrumbs') !== false || strpos($content, '[aiseo_breadcrumbs') !== false) {
            return $content;
        }
        $html = $this->render();
        if ($html === '') {
            return $content;
        }
        return $html . $content;
    }
    
    /**
     * Get settings
     *
     * @return array Settings
     */
    public function get_settings() {
        $settings = get_option(self::OPTION_KEY, array());
        return wp_parse_args($settings, $this->defaults);
    }
    
    /**
     * Update settings
     *
     * @param array $settings Settings to update
     * @return bool Success
     */
    public function update_settings($settings) {
        $current = $this->get_settings();
        
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, $this->defaults)) {
                if (is_bool($this->defaults[$key])) {
                    $current[$key] = (bool) $value;
                } else {
                    $current[$key] = sanitize_text_field($value);
                }
            }
        }
        
        return update_option(self::OPTION_KEY, $current);
    }
    
    /**
     * Shortcode handler
     *
     * @param array $atts Shortcode attributes
     * @return string Breadcrumbs HTML
     */
    public function shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'separator' => null,
            'home_text' => null,
            'wrapper_class' => null,
        ), $atts, 'aiseo_breadcrumbs');
        
        return $this->render($atts);
    }
    
    /**
     * Display breadcrumbs (for action hook)
     *
     * @param array $args Optional arguments
     */
    public function display($args = array()) {
        echo $this->render($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() escapes crumb text and URLs.
    }
    
    /**
     * Render breadcrumbs
     *
     * @param array $args Optional arguments to override settings
     * @return string Breadcrumbs HTML
     */
    public function render($args = array()) {
        $settings = $this->get_settings();
        
        if (!$settings['enabled']) {
            return '';
        }
        
        // Don't show on homepage if disabled
        if (is_front_page() && !$settings['show_on_home']) {
            return '';
        }
        
        // Merge args with settings
        $separator = isset($args['separator']) && $args['separator'] !== null ? $args['separator'] : $settings['separator'];
        $home_text = isset($args['home_text']) && $args['home_text'] !== null ? $args['home_text'] : $settings['home_text'];
        $wrapper_class = isset($args['wrapper_class']) && $args['wrapper_class'] !== null ? $args['wrapper_class'] : $settings['wrapper_class'];
        
        // Build breadcrumbs
        $breadcrumbs = $this->build_breadcrumbs($home_text);
        
        if (empty($breadcrumbs)) {
            return '';
        }
        
        // Generate HTML
        $html = $this->generate_html($breadcrumbs, $separator, $wrapper_class, $settings);
        
        return $html;
    }
    
    /**
     * Build breadcrumbs array
     *
     * @param string $home_text Home link text
     * @return array Breadcrumbs
     */
    private function build_breadcrumbs($home_text) {
        $settings = $this->get_settings();
        $breadcrumbs = array();
        
        // Home
        if ($settings['show_home']) {
            $breadcrumbs[] = array(
                'title' => $home_text,
                'url' => home_url('/'),
                'current' => is_front_page(),
            );
        }
        
        // Front page - just home
        if (is_front_page()) {
            return $breadcrumbs;
        }
        
        // Blog page
        if (is_home()) {
            $blog_page_id = get_option('page_for_posts');
            if ($blog_page_id) {
                $breadcrumbs[] = array(
                    'title' => get_the_title($blog_page_id),
                    'url' => get_permalink($blog_page_id),
                    'current' => true,
                );
            } else {
                $breadcrumbs[] = array(
                    'title' => __('Blog', 'aiseo'),
                    'url' => '',
                    'current' => true,
                );
            }
            return $breadcrumbs;
        }
        
        // Single post
        if (is_singular('post')) {
            $category = self::primary_category(get_queried_object_id());
            if ($category) {
                
                // Parent categories
                $parents = get_ancestors($category->term_id, 'category');
                $parents = array_reverse($parents);
                
                foreach ($parents as $parent_id) {
                    $parent = get_term($parent_id, 'category');
                    $breadcrumbs[] = array(
                        'title' => $parent->name,
                        'url' => get_term_link($parent),
                        'current' => false,
                    );
                }
                
                $breadcrumbs[] = array(
                    'title' => $category->name,
                    'url' => get_term_link($category),
                    'current' => false,
                );
            }
            
            // Current post
            $breadcrumbs[] = array(
                'title' => self::post_title(),
                'url' => get_permalink(),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Page
        if (is_page()) {
            global $post;
            
            // Parent pages
            if ($post->post_parent) {
                $parents = get_ancestors($post->ID, 'page');
                $parents = array_reverse($parents);
                
                foreach ($parents as $parent_id) {
                    $breadcrumbs[] = array(
                        'title' => self::post_title($parent_id),
                        'url' => get_permalink($parent_id),
                        'current' => false,
                    );
                }
            }
            
            // Current page
            $breadcrumbs[] = array(
                'title' => self::post_title(),
                'url' => get_permalink(),
                'current' => true,
            );
            
            return $breadcrumbs;
        }

        if (function_exists('is_product') && is_product()) {
            if (function_exists('wc_get_page_id')) {
                $shop_id = wc_get_page_id('shop');
                if ($shop_id > 0) {
                    $breadcrumbs[] = array(
                        'title' => get_the_title($shop_id),
                        'url' => get_permalink($shop_id),
                        'current' => false,
                    );
                }
            }
            $product_id = get_queried_object_id();
            $terms = array();
            if ($product_id && function_exists('wc_get_product_terms')) {
                $terms = wc_get_product_terms($product_id, 'product_cat', array(
                    'orderby' => 'parent',
                    'order' => 'DESC',
                ));
            }
            if (is_wp_error($terms) || !is_array($terms)) {
                $terms = array();
            }
            $term = $terms ? $terms[0] : null;
            if ($term instanceof WP_Term) {
                $parents = array_reverse(get_ancestors($term->term_id, 'product_cat'));
                foreach ($parents as $parent_id) {
                    $parent = get_term($parent_id, 'product_cat');
                    if ($parent instanceof WP_Term && !is_wp_error($parent)) {
                        $breadcrumbs[] = array(
                            'title' => $parent->name,
                            'url' => get_term_link($parent),
                            'current' => false,
                        );
                    }
                }
                $breadcrumbs[] = array(
                    'title' => $term->name,
                    'url' => get_term_link($term),
                    'current' => false,
                );
            }
            $breadcrumbs[] = array(
                'title' => self::post_title(),
                'url' => get_permalink(),
                'current' => true,
            );
            return $breadcrumbs;
        }

        if (function_exists('is_shop') && is_shop()) {
            $shop_id = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
            $breadcrumbs[] = array(
                'title' => $shop_id > 0 ? get_the_title($shop_id) : __('Shop', 'aiseo'),
                'url' => $shop_id > 0 ? get_permalink($shop_id) : '',
                'current' => true,
            );
            return $breadcrumbs;
        }

        if (function_exists('is_product_category') && is_product_category()) {
            if (function_exists('wc_get_page_id')) {
                $shop_id = wc_get_page_id('shop');
                if ($shop_id > 0) {
                    $breadcrumbs[] = array(
                        'title' => get_the_title($shop_id),
                        'url' => get_permalink($shop_id),
                        'current' => false,
                    );
                }
            }
            $category = get_queried_object();
            if ($category instanceof WP_Term) {
                $parents = array_reverse(get_ancestors($category->term_id, 'product_cat'));
                foreach ($parents as $parent_id) {
                    $parent = get_term($parent_id, 'product_cat');
                    if ($parent instanceof WP_Term && !is_wp_error($parent)) {
                        $breadcrumbs[] = array(
                            'title' => $parent->name,
                            'url' => get_term_link($parent),
                            'current' => false,
                        );
                    }
                }
                $breadcrumbs[] = array(
                    'title' => $category->name,
                    'url' => get_term_link($category),
                    'current' => true,
                );
            }
            return $breadcrumbs;
        }

        // Other public singular types, such as a custom post type.
        if (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof WP_Post) {
                $post_type = get_post_type_object($post->post_type);
                if ($post_type && !empty($post_type->has_archive)) {
                    $archive = get_post_type_archive_link($post->post_type);
                    if ($archive) {
                        $breadcrumbs[] = array(
                            'title' => $post_type->labels->name,
                            'url' => $archive,
                            'current' => false,
                        );
                    }
                }
                $breadcrumbs[] = array(
                    'title' => self::post_title($post),
                    'url' => get_permalink($post),
                    'current' => true,
                );
            }
            return $breadcrumbs;
        }
        
        // Category
        if (is_category()) {
            $category = get_queried_object();
            
            // Parent categories
            $parents = get_ancestors($category->term_id, 'category');
            $parents = array_reverse($parents);
            
            foreach ($parents as $parent_id) {
                $parent = get_term($parent_id, 'category');
                $breadcrumbs[] = array(
                    'title' => $parent->name,
                    'url' => get_term_link($parent),
                    'current' => false,
                );
            }
            
            $breadcrumbs[] = array(
                'title' => $category->name,
                'url' => get_term_link($category),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Tag
        if (is_tag()) {
            $tag = get_queried_object();
            $breadcrumbs[] = array(
                'title' => $tag->name,
                'url' => get_term_link($tag),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Author
        if (is_author()) {
            $author = get_queried_object();
            $breadcrumbs[] = array(
                'title' => $author->display_name,
                'url' => get_author_posts_url($author->ID),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Date archives
        if (is_date()) {
            if (is_year()) {
                $breadcrumbs[] = array(
                    'title' => get_the_date('Y'),
                    'url' => get_year_link(get_the_date('Y')),
                    'current' => true,
                );
            } elseif (is_month()) {
                $breadcrumbs[] = array(
                    'title' => get_the_date('Y'),
                    'url' => get_year_link(get_the_date('Y')),
                    'current' => false,
                );
                $breadcrumbs[] = array(
                    'title' => get_the_date('F'),
                    'url' => get_month_link(get_the_date('Y'), get_the_date('m')),
                    'current' => true,
                );
            } elseif (is_day()) {
                $breadcrumbs[] = array(
                    'title' => get_the_date('Y'),
                    'url' => get_year_link(get_the_date('Y')),
                    'current' => false,
                );
                $breadcrumbs[] = array(
                    'title' => get_the_date('F'),
                    'url' => get_month_link(get_the_date('Y'), get_the_date('m')),
                    'current' => false,
                );
                $breadcrumbs[] = array(
                    'title' => get_the_date('j'),
                    'url' => get_day_link(get_the_date('Y'), get_the_date('m'), get_the_date('d')),
                    'current' => true,
                );
            }
            
            return $breadcrumbs;
        }
        
        // Search
        if (is_search()) {
            $breadcrumbs[] = array(
                /* translators: %s: text. */
                'title' => sprintf(__('Search: %s', 'aiseo'), get_search_query()),
                'url' => '',
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // 404
        if (is_404()) {
            $breadcrumbs[] = array(
                'title' => __('Page Not Found', 'aiseo'),
                'url' => '',
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Custom post type
        if (is_singular()) {
            $post_type = get_post_type();
            $post_type_obj = get_post_type_object($post_type);
            
            if ($post_type_obj && $post_type_obj->has_archive) {
                $breadcrumbs[] = array(
                    'title' => $post_type_obj->labels->name,
                    'url' => get_post_type_archive_link($post_type),
                    'current' => false,
                );
            }
            
            $breadcrumbs[] = array(
                'title' => self::post_title(),
                'url' => get_permalink(),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Post type archive
        if (is_post_type_archive()) {
            $post_type_obj = get_queried_object();
            $breadcrumbs[] = array(
                'title' => $post_type_obj->labels->name,
                'url' => get_post_type_archive_link($post_type_obj->name),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        // Taxonomy
        if (is_tax()) {
            $term = get_queried_object();
            $taxonomy = get_taxonomy($term->taxonomy);
            
            // Parent terms
            $parents = get_ancestors($term->term_id, $term->taxonomy);
            $parents = array_reverse($parents);
            
            foreach ($parents as $parent_id) {
                $parent = get_term($parent_id, $term->taxonomy);
                $breadcrumbs[] = array(
                    'title' => $parent->name,
                    'url' => get_term_link($parent),
                    'current' => false,
                );
            }
            
            $breadcrumbs[] = array(
                'title' => $term->name,
                'url' => get_term_link($term),
                'current' => true,
            );
            
            return $breadcrumbs;
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Generate HTML output
     *
     * @param array $breadcrumbs Breadcrumbs array
     * @param string $separator Separator
     * @param string $wrapper_class Wrapper class
     * @param array $settings Settings
     * @return string HTML
     */
    private function generate_html($breadcrumbs, $separator, $wrapper_class, $settings) {
        $html = '<nav class="' . esc_attr($wrapper_class) . '" aria-label="' . esc_attr__('Breadcrumb', 'aiseo') . '"><ol>';
        $count = count($breadcrumbs);

        foreach ($breadcrumbs as $index => $crumb) {
            $is_last = ($index === $count - 1);
            if ($is_last && !$settings['show_current']) {
                continue;
            }

            $html .= '<li>';
            if (!empty($crumb['url']) && !$crumb['current']) {
                $html .= '<a href="' . esc_url($crumb['url']) . '">' . esc_html($crumb['title']) . '</a>';
            } else {
                $title = esc_html($crumb['title']);
                if ($settings['bold_current'] && $crumb['current']) {
                    $title = '<strong>' . $title . '</strong>';
                }
                $html .= '<span>' . $title . '</span>';
            }
            $html .= '</li>';

            if (!$is_last) {
                $html .= '<li class="separator" aria-hidden="true">' . esc_html($separator) . '</li>';
            }
        }

        $html .= '</ol></nav>';
        return $html;
    }
    
    /**
     * Get breadcrumbs as array (for REST API)
     *
     * @return array Breadcrumbs data
     */
    public function get_breadcrumbs_array() {
        $settings = $this->get_settings();
        return $this->build_breadcrumbs($settings['home_text']);
    }
}

/**
 * Template function for displaying breadcrumbs
 *
 * @param array $args Optional arguments
 */
function aiseo_breadcrumbs($args = array()) {
    $breadcrumbs = new AISEO_Breadcrumbs();
    $breadcrumbs->display($args);
}
