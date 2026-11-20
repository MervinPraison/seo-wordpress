<?php
/**
 * AISEO Global Robots Settings
 *
 * Handles global robots meta settings for different page types
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Robots {
    
    /**
     * Option key for robots settings
     */
    const OPTION_KEY = 'aiseo_robots_settings';
    
    /**
     * Default settings
     */
    private $defaults = array(
        // Archive types
        'noindex_categories' => false,
        'noindex_tags' => false,
        'noindex_author_archives' => false,
        'noindex_date_archives' => true,
        'noindex_search_results' => true,
        'noindex_paginated' => false,
        
        // NoFollow settings
        'nofollow_categories' => false,
        'nofollow_tags' => false,
        'nofollow_author_archives' => false,
        'nofollow_date_archives' => false,
        'nofollow_external_links' => false,
        
        // Other settings
        'noindex_empty_taxonomies' => true,
        'noindex_attachment_pages' => true,
        'add_noodp' => false,
        'add_noydir' => false,
        'block_ai_crawlers' => false,
        'noindex_out_of_stock' => false,
    );
    
    /**
     * Initialize robots settings
     */
    public function init() {
        add_filter('wp_robots', array($this, 'filter_wp_robots'), 20);
        add_filter('robots_txt', array($this, 'block_ai_crawlers'), 15, 2);
        add_filter('robots_txt', array($this, 'woocommerce_paths'), 16, 2);
        add_filter('robots_txt', array($this, 'append_extra_rules'), 30, 2);
        add_action('template_redirect', array($this, 'redirect_attachment_page'), 1);
        add_action('pre_get_posts', array($this, 'exclude_noindex_from_feed'));
        
        // Filter external links
        add_filter('the_content', array($this, 'filter_external_links'), 99);
        add_filter('comment_text', array($this, 'filter_external_links'), 99);
    }
    
    /**
     * Get all settings
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
        
        // Sanitize and merge
        foreach ($settings as $key => $value) {
            if (array_key_exists($key, $this->defaults)) {
                $current[$key] = (bool) $value;
            }
        }
        
        return update_option(self::OPTION_KEY, $current);
    }
    
    /**
     * Output robots meta tag
     */
    public function filter_wp_robots($robots) {
        if (is_404()) {
            $robots['noindex'] = true;
            return $robots;
        }
        if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
            return $robots;
        }
        if (is_singular() && !is_attachment()) {
            return $robots;
        }
        
        $settings = $this->get_settings();
        $directives = array();
        
        // Check page type and apply settings
        if (is_category()) {
            if ($settings['noindex_categories']) {
                $directives[] = 'noindex';
            }
            if ($settings['nofollow_categories']) {
                $directives[] = 'nofollow';
            }
            
            // Check for empty taxonomy
            if ($settings['noindex_empty_taxonomies']) {
                $term = get_queried_object();
                if ($term && $term->count === 0) {
                    $directives[] = 'noindex';
                }
            }
        }
        
        if (is_tag()) {
            if ($settings['noindex_tags']) {
                $directives[] = 'noindex';
            }
            if ($settings['nofollow_tags']) {
                $directives[] = 'nofollow';
            }
            
            // Check for empty taxonomy
            if ($settings['noindex_empty_taxonomies']) {
                $term = get_queried_object();
                if ($term && $term->count === 0) {
                    $directives[] = 'noindex';
                }
            }
        }
        
        if (is_author()) {
            if ($settings['noindex_author_archives']) {
                $directives[] = 'noindex';
            }
            $author = get_queried_object();
            if ($author instanceof WP_User) {
                $author_noindex = get_user_meta($author->ID, '_aiseo_noindex', true);
                if (in_array($author_noindex, array(true, 1, '1'), true)) {
                    $directives[] = 'noindex';
                }
            }
            if ($settings['nofollow_author_archives']) {
                $directives[] = 'nofollow';
            }
        }
        
        if (is_date()) {
            if ($settings['noindex_date_archives']) {
                $directives[] = 'noindex';
            }
            if ($settings['nofollow_date_archives']) {
                $directives[] = 'nofollow';
            }
        }
        
        if (is_search()) {
            if ($settings['noindex_search_results']) {
                $directives[] = 'noindex';
            } else {
                unset($robots['noindex']);
            }
        }
        
        if (is_attachment()) {
            if ($settings['noindex_attachment_pages']) {
                $directives[] = 'noindex';
            }
        }
        
        if (is_paged() && $settings['noindex_paginated']) {
            $directives[] = 'noindex';
        }

        if (function_exists('is_shop') && (is_shop() || is_product_taxonomy())) {
            if (isset($_GET['orderby']) || isset($_GET['min_price']) || isset($_GET['max_price']) || isset($_GET['filtering'])) {
                $directives[] = 'noindex';
            }
        }
        
        if ($settings['add_noodp']) {
            $directives[] = 'noodp';
        }
        if ($settings['add_noydir']) {
            $directives[] = 'noydir';
        }
        
        foreach (array_unique($directives) as $directive) {
            $robots[$directive] = true;
            if ($directive === 'noindex') {
                unset($robots['index']);
            }
            if ($directive === 'nofollow') {
                unset($robots['follow']);
            }
        }
        
        return $robots;
    }

    /**
     * Optionally refuse known AI training crawlers in robots.txt.
     *
     * @param string $output Existing robots.txt body.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    /**
     * Known AI training crawlers, matching Yoast-style bot lists.
     *
     * @return string[]
     */
    public function get_known_ai_crawlers() {
        return array(
            'GPTBot',
            'ChatGPT-User',
            'Google-Extended',
            'Google-CloudVertexBot',
            'ClaudeBot',
            'anthropic-ai',
            'CCBot',
            'Bytespider',
            'PerplexityBot',
            'Applebot-Extended',
            'meta-externalagent',
            'FacebookBot',
            'cohere-ai',
            'Diffbot',
            'Amazonbot',
        );
    }

    /**
     * Agents selected in Settings. Empty means all known agents (legacy).
     *
     * @return string[]
     */
    public function get_blocked_ai_crawlers() {
        $known = $this->get_known_ai_crawlers();
        $saved = get_option('aiseo_blocked_ai_agents', array());
        if (!is_array($saved) || !$saved) {
            return $known;
        }
        return array_values(array_intersect($saved, $known));
    }

    /**
     * @param string[] $agents Posted agent names.
     */
    public function save_blocked_ai_crawlers($agents) {
        if (!is_array($agents)) {
            $agents = array();
        }
        $agents = array_values(array_intersect($agents, $this->get_known_ai_crawlers()));
        if (!$agents) {
            delete_option('aiseo_blocked_ai_agents');
            return;
        }
        update_option('aiseo_blocked_ai_agents', $agents);
    }

    public function block_ai_crawlers($output, $public) {
        $settings = $this->get_settings();
        if (empty($settings['block_ai_crawlers'])) {
            return $output;
        }
        $agents = $this->get_blocked_ai_crawlers();
        if (!$agents) {
            return $output;
        }
        $block = "\n# AI crawlers (Praison AI SEO)\n";
        foreach ($agents as $agent) {
            $block .= 'User-agent: ' . $agent . "\nDisallow: /\n\n";
        }
        return rtrim((string) $output) . "\n" . $block;
    }

    /**
     * Keep WooCommerce cart, checkout, and account URLs out of crawlers.
     *
     * @param string $output Existing robots.txt body.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    public function woocommerce_paths($output, $public) {
        if (!$public || !function_exists('wc_get_page_id')) {
            return $output;
        }
        $paths = array();
        foreach (array('cart', 'checkout', 'myaccount') as $page) {
            $page_id = (int) wc_get_page_id($page);
            if ($page_id < 1) {
                continue;
            }
            $permalink = get_permalink($page_id);
            $path = is_string($permalink) ? wp_parse_url($permalink, PHP_URL_PATH) : '';
            if (!is_string($path) || $path === '' || $path === '/') {
                continue;
            }
            $paths[] = untrailingslashit($path) . '/';
        }
        $paths = array_unique($paths);
        if (!$paths) {
            return $output;
        }
        $block = "\n# WooCommerce (Praison AI SEO)\nUser-agent: *\n";
        foreach ($paths as $path) {
            $block .= 'Disallow: ' . $path . "\n";
        }
        return rtrim((string) $output) . "\n" . $block;
    }

    /**
     * Extra robots.txt lines from Settings, like Yoast and Rank Math.
     *
     * @param string $output Existing body.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    public function append_extra_rules($output, $public) {
        if (!$public) {
            return $output;
        }
        $extra = $this->get_extra_rules();
        if ($extra === '') {
            return $output;
        }
        return rtrim((string) $output) . "\n\n# Extra rules (Praison AI SEO)\n" . $extra . "\n";
    }

    /**
     * @return string
     */
    public function get_extra_rules() {
        $extra = get_option('aiseo_robots_extra', '');
        return is_string($extra) ? $this->sanitize_extra_rules($extra) : '';
    }

    /**
     * @param string $raw Posted or stored rules.
     * @return string
     */
    public function sanitize_extra_rules($raw) {
        $raw = sanitize_textarea_field($raw);
        $raw = str_replace(array('<?', "\0"), '', $raw);
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $kept = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $kept[] = '';
                continue;
            }
            if ($line[0] === '#') {
                $kept[] = $line;
                continue;
            }
            if (!preg_match('/^(User-agent|Disallow|Allow|Crawl-delay|Sitemap|Host)\s*:/i', $line)) {
                continue;
            }
            $kept[] = $line;
            if (count($kept) >= 80) {
                break;
            }
        }
        return trim(implode("\n", $kept));
    }

    /**
     * Send attachment pages to the parent post, or to the file when there is no parent.
     */
    public function redirect_attachment_page() {
        if (!is_attachment() || is_feed() || is_preview()) {
            return;
        }
        $settings = $this->get_settings();
        if (empty($settings['noindex_attachment_pages'])) {
            return;
        }
        $attachment_id = get_queried_object_id();
        if (!$attachment_id) {
            return;
        }
        $parent_id = wp_get_post_parent_id($attachment_id);
        $target = $parent_id ? get_permalink($parent_id) : wp_get_attachment_url($attachment_id);
        if (!is_string($target) || $target === '') {
            return;
        }
        if (wp_safe_redirect($target, 301)) {
            exit;
        }
    }

    /**
     * Keep noindex posts out of the RSS and Atom feeds.
     *
     * @param WP_Query $query Query.
     */
    public function exclude_noindex_from_feed($query) {
        if (!$query instanceof WP_Query || is_admin() || !$query->is_main_query() || !$query->is_feed() || $query->is_comment_feed()) {
            return;
        }
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_aiseo_noindex',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_aiseo_noindex',
                'value' => array('1', 'true'),
                'compare' => 'NOT IN',
            ),
        );
        $query->set('meta_query', $meta_query);
        $query->set('has_password', false);
    }

    /**
     * Filter external links to add nofollow
     *
     * @param string $content Content
     * @return string Modified content
     */
    public function filter_external_links($content) {
        if (!is_string($content) || $content === '') {
            return $content;
        }
        $settings = $this->get_settings();
        if (empty($settings['nofollow_external_links'])) {
            return $content;
        }

        $site_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        return preg_replace_callback('/<a\b[^>]*>/i', function ($matches) use ($site_host) {
            $tag = $matches[0];
            if (!preg_match('/\bhref\s*=\s*(["\'])([^"\']+)\1/i', $tag, $href_match)) {
                return $tag;
            }
            $host = wp_parse_url(html_entity_decode($href_match[2], ENT_QUOTES), PHP_URL_HOST);
            if (!is_string($host) || $host === '' || strtolower($host) === $site_host) {
                return $tag;
            }
            if (preg_match('/\brel\s*=\s*(["\'])([^"\']*)\1/i', $tag, $rel_match)) {
                $rels = preg_split('/\s+/', trim($rel_match[2]));
                if (!is_array($rels)) {
                    $rels = array();
                }
                foreach (array('nofollow', 'noopener') as $rel) {
                    if (!in_array($rel, $rels, true)) {
                        $rels[] = $rel;
                    }
                }
                $rels = array_values(array_filter($rels, 'strlen'));
                $new_rel = 'rel=' . $rel_match[1] . implode(' ', $rels) . $rel_match[1];
                $updated = preg_replace('/\brel\s*=\s*(["\'])([^"\']*)\1/i', $new_rel, $tag, 1);
                return is_string($updated) ? $updated : $tag;
            }
            $updated = preg_replace('/<a\b/i', '<a rel="nofollow noopener"', $tag, 1);
            return is_string($updated) ? $updated : $tag;
        }, $content);
    }
}
