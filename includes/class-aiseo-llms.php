<?php
/**
 * llms.txt for AI crawlers.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Llms {

    /**
     * Register the front-end file and the robots.txt pointer.
     */
    public function init() {
        add_action('template_redirect', array($this, 'maybe_serve'), 0);
        add_filter('robots_txt', array($this, 'robots_pointer'), 20, 2);
    }

    /**
     * @return bool
     */
    public function is_enabled() {
        $enabled = get_option('aiseo_enable_llms', '1');
        return !in_array($enabled, array(false, 0, '0', ''), true);
    }

    /**
     * Optional longer Markdown file for assistants that want full excerpts.
     *
     * @return bool
     */
    public function full_is_enabled() {
        return $this->is_enabled() && !in_array(get_option('aiseo_enable_llms_full', '0'), array(false, 0, '0', ''), true);
    }

    /**
     * @param string $output Existing robots.txt body.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    public function robots_pointer($output, $public) {
        if (!$public || !$this->is_enabled()) {
            return $output;
        }
        $output = rtrim($output) . "\n\n# llms.txt: " . esc_url_raw(home_url('/llms.txt')) . "\n";
        if ($this->full_is_enabled()) {
            $output .= '# llms-full.txt: ' . esc_url_raw(home_url('/llms-full.txt')) . "\n";
        }
        return $output;
    }

    /**
     * Serve /llms.txt.
     */
    public function maybe_serve() {
        $serve_full = $this->full_is_enabled() && $this->is_request('/llms-full.txt');
        if (!$this->is_enabled() || (!$this->is_request('/llms.txt') && !$serve_full)) {
            return;
        }
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $serve_full ? $this->document_full() : $this->document(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text we assembled.
        exit;
    }

    /**
     * @param string $path Request path to match.
     * @return bool
     */
    private function is_request($path) {
        $requested = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $current = wp_parse_url($requested, PHP_URL_PATH);
        return untrailingslashit((string) $current) === untrailingslashit($path);
    }

    /**
     * @param int $post_id Post ID.
     * @return bool
     */
    private function is_noindex($post_id) {
        if (class_exists('AISEO_Meta')) {
            return AISEO_Meta::post_is_noindex($post_id);
        }
        return in_array(get_post_meta($post_id, '_aiseo_noindex', true), array(true, 1, '1'), true);
    }

    /**
     * Public post types included in llms.txt.
     *
     * @return string[]
     */
    public function included_post_types() {
        $all = get_post_types(array('public' => true), 'names');
        unset($all['attachment']);
        $all = array_values($all);
        $saved = get_option('aiseo_llms_types', null);
        if (! is_array($saved)) {
            return $all;
        }
        return array_values(array_intersect($saved, $all));
    }

    /**
     * Max items per post type, 1–500.
     *
     * @return int
     */
    public function item_limit() {
        $limit = absint(get_option('aiseo_llms_limit', 100));
        if ($limit < 1) {
            $limit = 100;
        }
        if ($limit > 500) {
            $limit = 500;
        }
        return $limit;
    }

    /**
     * Markdown document of the site's public posts and pages.
     *
     * @return string
     */
    public function document() {
        $lines = array('# ' . wp_strip_all_tags(get_bloginfo('name')));
        $tagline = wp_strip_all_tags(get_bloginfo('description'));
        if ($tagline !== '') {
            $lines[] = '> ' . $tagline;
        }
        $lines[] = '';

        $extra = get_option('aiseo_llms_extra', '');
        if (is_string($extra) && trim($extra) !== '') {
            $lines[] = trim($extra);
            $lines[] = '';
        }

        $objects = get_post_types(array('public' => true), 'objects');
        $limit = $this->item_limit();
        foreach ($this->included_post_types() as $post_type) {
            if (! isset($objects[$post_type])) {
                continue;
            }
            $heading = wp_strip_all_tags($objects[$post_type]->labels->name);
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'modified',
                'order' => 'DESC',
                'has_password' => false,
            ));
            $items = array();
            foreach ($posts as $post) {
                if ($this->is_noindex($post->ID)) {
                    continue;
                }
                $summary = get_post_meta($post->ID, '_aiseo_meta_description', true);
                if (!is_string($summary) || $summary === '') {
                    $summary = $post->post_excerpt !== '' ? $post->post_excerpt : wp_trim_words(wp_strip_all_tags($post->post_content), 30, '');
                }
                $summary = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $summary)));
                $line = '- [' . str_replace(array('[', ']'), '', wp_strip_all_tags($post->post_title)) . '](' . get_permalink($post) . ')';
                if ($summary !== '') {
                    $line .= ': ' . $summary;
                }
                $items[] = $line;
            }
            if ($items) {
                $lines[] = '## ' . $heading;
                $lines[] = '';
                $lines = array_merge($lines, $items);
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Longer Markdown file with 120-word excerpts (AIOSEO llms-full.txt equivalent).
     *
     * @return string
     */
    public function document_full() {
        $lines = array($this->document(), '', '---', '', '# Full excerpts', '');
        $objects = get_post_types(array('public' => true), 'objects');
        $limit = $this->item_limit();
        foreach ($this->included_post_types() as $post_type) {
            if (!isset($objects[$post_type])) {
                continue;
            }
            $posts = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'modified',
                'order' => 'DESC',
                'has_password' => false,
            ));
            $items = array();
            foreach ($posts as $post) {
                if ($this->is_noindex($post->ID)) {
                    continue;
                }
                $excerpt = wp_trim_words(wp_strip_all_tags($post->post_content), 120, '');
                $title = str_replace(array('[', ']'), '', wp_strip_all_tags($post->post_title));
                $block = '### [' . $title . '](' . get_permalink($post) . ')';
                if ($excerpt !== '') {
                    $block .= "\n\n" . $excerpt;
                }
                $items[] = $block;
            }
            if ($items) {
                $lines[] = '## ' . wp_strip_all_tags($objects[$post_type]->labels->name);
                $lines[] = '';
                $lines[] = implode("\n\n", $items);
                $lines[] = '';
            }
        }
        return implode("\n", $lines);
    }
}
