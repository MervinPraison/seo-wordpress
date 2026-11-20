<?php
/**
 * HTML sitemap shortcode, matching Rank Math / SEOPress visitor sitemaps.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Html_Sitemap {

    /**
     * Register the shortcode.
     */
    public function init() {
        add_shortcode('aiseo_html_sitemap', array($this, 'shortcode'));
    }

    /**
     * Render a grouped list of public, indexable posts.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public function shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'limit' => 100,
            ),
            $atts,
            'aiseo_html_sitemap'
        );
        $limit = absint($atts['limit']);
        if ($limit < 1) {
            $limit = 100;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $types = get_post_types(array('public' => true), 'objects');
        unset($types['attachment']);
        if (!$types) {
            return '';
        }

        $html = '<nav class="aiseo-html-sitemap" aria-label="' . esc_attr__('HTML sitemap', 'aiseo') . '">';
        foreach ($types as $name => $object) {
            $posts = get_posts(array(
                'post_type' => $name,
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'orderby' => 'title',
                'order' => 'ASC',
                'has_password' => false,
                'no_found_rows' => true,
            ));
            $items = '';
            foreach ($posts as $post) {
                $exclude = get_post_meta($post->ID, '_aiseo_exclude_sitemap', true);
                if ((class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post->ID)) || in_array($exclude, array(true, 1, '1'), true)) {
                    continue;
                }
                $url = get_permalink($post);
                if (!is_string($url) || $url === '') {
                    continue;
                }
                $items .= '<li><a href="' . esc_url($url) . '">' . esc_html(get_the_title($post)) . '</a></li>';
            }
            if ($items === '') {
                continue;
            }
            $label = isset($object->labels->name) ? $object->labels->name : $name;
            $html .= '<section class="aiseo-html-sitemap-group">';
            $html .= '<h2>' . esc_html($label) . '</h2>';
            $html .= '<ul>' . $items . '</ul>';
            $html .= '</section>';
        }
        $html .= '</nav>';

        return $html;
    }
}
