<?php
/**
 * AISEO Homepage SEO Settings
 *
 * Handles SEO settings for homepage, blog page, and global title templates
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Homepage_SEO {
    
    /**
     * Option keys for homepage SEO settings
     */
    const OPTION_HOME_TITLE = 'aiseo_home_title';
    const OPTION_HOME_DESCRIPTION = 'aiseo_home_description';
    const OPTION_HOME_KEYWORDS = 'aiseo_home_keywords';
    const OPTION_BLOG_TITLE = 'aiseo_blog_title';
    const OPTION_BLOG_DESCRIPTION = 'aiseo_blog_description';
    const OPTION_BLOG_KEYWORDS = 'aiseo_blog_keywords';
    
    /**
     * Initialize homepage SEO
     */
    public function init() {
        // Hook into wp_head for meta tags
        add_action('wp_head', array($this, 'output_homepage_meta'), 1);
        
        // Filter document title for homepage/blog
        add_filter('pre_get_document_title', array($this, 'filter_homepage_title'), 5);
        add_filter('document_title_parts', array($this, 'filter_title_parts'), 10);
    }
    
    /**
     * Get all homepage SEO settings
     *
     * @return array Settings array
     */
    public function get_settings() {
        return array(
            'home_title' => get_option(self::OPTION_HOME_TITLE, ''),
            'home_description' => get_option(self::OPTION_HOME_DESCRIPTION, ''),
            'home_keywords' => get_option(self::OPTION_HOME_KEYWORDS, ''),
            'blog_title' => get_option(self::OPTION_BLOG_TITLE, ''),
            'blog_description' => get_option(self::OPTION_BLOG_DESCRIPTION, ''),
            'blog_keywords' => get_option(self::OPTION_BLOG_KEYWORDS, ''),
        );
    }
    
    /**
     * Update homepage SEO settings
     *
     * @param array $settings Settings to update
     * @return bool Success
     */
    public function update_settings($settings) {
        $updated = false;
        
        if (isset($settings['home_title'])) {
            update_option(self::OPTION_HOME_TITLE, sanitize_text_field($settings['home_title']));
            $updated = true;
        }
        
        if (isset($settings['home_description'])) {
            update_option(self::OPTION_HOME_DESCRIPTION, sanitize_textarea_field($settings['home_description']));
            $updated = true;
        }
        
        if (isset($settings['home_keywords'])) {
            update_option(self::OPTION_HOME_KEYWORDS, sanitize_text_field($settings['home_keywords']));
            $updated = true;
        }
        
        if (isset($settings['blog_title'])) {
            update_option(self::OPTION_BLOG_TITLE, sanitize_text_field($settings['blog_title']));
            $updated = true;
        }
        
        if (isset($settings['blog_description'])) {
            update_option(self::OPTION_BLOG_DESCRIPTION, sanitize_textarea_field($settings['blog_description']));
            $updated = true;
        }
        
        if (isset($settings['blog_keywords'])) {
            update_option(self::OPTION_BLOG_KEYWORDS, sanitize_text_field($settings['blog_keywords']));
            $updated = true;
        }
        
        return $updated;
    }
    
    /**
     * Output homepage/blog meta tags
     */
    public function output_homepage_meta() {
        // Only on front page or blog page
        if (!is_front_page() && !is_home()) {
            return;
        }
        
        $settings = $this->get_settings();
        
        // Front page (static or posts)
        if (is_front_page() && is_home()) {
            // Default homepage (Your latest posts)
            $this->output_meta_tags(
                $settings['home_title'],
                $settings['home_description'],
                $settings['home_keywords']
            );
        } elseif (is_front_page()) {
            // A static front page is a singular page. AISEO_Meta already prints its description.
            return;
        } elseif (is_home()) {
            // Blog page (static page for posts)
            $blog_page_id = get_option('page_for_posts');
            $page_desc = get_post_meta($blog_page_id, '_aiseo_meta_description', true);
            $page_keywords = get_post_meta($blog_page_id, '_aiseo_meta_keywords', true);
            
            // Use global settings as fallback
            $description = !empty($page_desc) ? $page_desc : $settings['blog_description'];
            $keywords = !empty($page_keywords) ? $page_keywords : $settings['blog_keywords'];
            
            $this->output_meta_tags('', $description, $keywords);
        }
    }
    
    /**
     * Output meta tags
     *
     * @param string $title Title (not used for meta, just for reference)
     * @param string $description Meta description
     * @param string $keywords Meta keywords
     */
    private function output_meta_tags($title, $description, $keywords) {
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
        
        if (!empty($keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
        }
    }
    
    /**
     * Filter homepage title
     *
     * @param string $title Current title
     * @return string Modified title
     */
    public function filter_homepage_title($title) {
        $settings = $this->get_settings();
        
        // Front page with latest posts
        if (is_front_page() && is_home()) {
            if (!empty($settings['home_title'])) {
                return $settings['home_title'];
            }
        }
        // Static front page
        elseif (is_front_page()) {
            $front_page_id = get_option('page_on_front');
            $page_title = get_post_meta($front_page_id, '_aiseo_meta_title', true);
            
            if (!empty($page_title)) {
                return $page_title;
            } elseif (!empty($settings['home_title'])) {
                return $settings['home_title'];
            }
        }
        // Blog page
        elseif (is_home()) {
            $blog_page_id = get_option('page_for_posts');
            $page_title = get_post_meta($blog_page_id, '_aiseo_meta_title', true);
            
            if (!empty($page_title)) {
                return $page_title;
            } elseif (!empty($settings['blog_title'])) {
                return $settings['blog_title'];
            }
        }
        
        return $title;
    }
    
    /**
     * Filter title parts for more control
     *
     * @param array $title_parts Title parts array
     * @return array Modified title parts
     */
    public function filter_title_parts($title_parts) {
        $settings = $this->get_settings();
        
        // Front page with latest posts
        if (is_front_page() && is_home()) {
            if (!empty($settings['home_title'])) {
                // Replace entire title
                return array('title' => $settings['home_title']);
            }
        }
        // Blog page
        elseif (is_home()) {
            if (!empty($settings['blog_title'])) {
                $title_parts['title'] = $settings['blog_title'];
            }
        }
        
        return $title_parts;
    }
    
    /**
     * Get homepage canonical URL
     *
     * @return string Canonical URL
     */
    public function get_homepage_canonical() {
        if (is_front_page()) {
            return home_url('/');
        }
        
        if (is_home()) {
            $blog_page_id = get_option('page_for_posts');
            if ($blog_page_id) {
                return get_permalink($blog_page_id);
            }
        }
        
        return home_url('/');
    }
}
