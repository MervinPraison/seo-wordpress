<?php
/**
 * AISEO RSS Feed Customization
 *
 * Adds custom content before and after RSS feed items
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_RSS {
    
    /**
     * Option key for RSS settings
     */
    const OPTION_KEY = 'aiseo_rss_settings';
    
    /**
     * Default settings
     */
    private $defaults = array(
        'enabled' => false,
        'before_content' => '',
        'after_content' => '',
    );
    
    /**
     * Available placeholders
     */
    private $placeholders = array(
        '%post_title%' => 'Post title',
        '%post_url%' => 'Post URL',
        '%post_author%' => 'Post author name',
        '%post_date%' => 'Post date',
        '%site_name%' => 'Site name',
        '%site_url%' => 'Site URL',
        '%post_excerpt%' => 'Post excerpt',
        '%post_categories%' => 'Post categories',
    );
    
    /**
     * Initialize RSS customization
     */
    public function init() {
        $settings = $this->get_settings();
        
        if (!$settings['enabled']) {
            return;
        }
        
        // Add content before RSS item
        add_filter('the_excerpt_rss', array($this, 'add_before_content'));
        add_filter('the_content_feed', array($this, 'add_before_content'));
        
        // Add content after RSS item
        add_filter('the_excerpt_rss', array($this, 'add_after_content'), 99);
        add_filter('the_content_feed', array($this, 'add_after_content'), 99);
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
        
        if (isset($settings['enabled'])) {
            $current['enabled'] = (bool) $settings['enabled'];
        }
        
        if (isset($settings['before_content'])) {
            $current['before_content'] = wp_kses_post($settings['before_content']);
        }
        
        if (isset($settings['after_content'])) {
            $current['after_content'] = wp_kses_post($settings['after_content']);
        }
        
        return update_option(self::OPTION_KEY, $current);
    }
    
    /**
     * Get available placeholders
     *
     * @return array Placeholders
     */
    public function get_placeholders() {
        return $this->placeholders;
    }
    
    /**
     * Add content before RSS item
     *
     * @param string $content Content
     * @return string Modified content
     */
    public function add_before_content($content) {
        $settings = $this->get_settings();
        
        if (empty($settings['before_content'])) {
            return $content;
        }
        
        $before = $this->parse_placeholders($settings['before_content']);
        
        return $before . $content;
    }
    
    /**
     * Add content after RSS item
     *
     * @param string $content Content
     * @return string Modified content
     */
    public function add_after_content($content) {
        $settings = $this->get_settings();
        
        if (empty($settings['after_content'])) {
            return $content;
        }
        
        $after = $this->parse_placeholders($settings['after_content']);
        
        return $content . $after;
    }
    
    /**
     * Parse placeholders in content
     *
     * @param string $content Content with placeholders
     * @return string Parsed content
     */
    private function parse_placeholders($content) {
        global $post;
        
        if (!$post) {
            return $content;
        }
        
        $replacements = array(
            '%post_title%' => esc_html(get_the_title($post)),
            '%post_url%' => esc_url(get_permalink($post)),
            '%post_author%' => esc_html(get_the_author_meta('display_name', $post->post_author)),
            '%post_date%' => esc_html(get_the_date('', $post)),
            '%site_name%' => esc_html(get_bloginfo('name')),
            '%site_url%' => esc_url(home_url('/')),
            '%post_excerpt%' => esc_html(wp_strip_all_tags(get_the_excerpt($post))),
            '%post_categories%' => esc_html($this->get_post_categories($post)),
        );
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
    }
    
    /**
     * Get post categories as comma-separated string
     *
     * @param WP_Post $post Post object
     * @return string Categories
     */
    private function get_post_categories($post) {
        $categories = get_the_category($post->ID);
        
        if (empty($categories)) {
            return '';
        }
        
        $names = array();
        foreach ($categories as $category) {
            $names[] = $category->name;
        }
        
        return implode(', ', $names);
    }
}
