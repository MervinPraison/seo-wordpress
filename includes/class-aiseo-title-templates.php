<?php
/**
 * AISEO Global Title Templates
 *
 * Handles global title templates and suffixes for different page types
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Title_Templates {
    
    /**
     * Option key for title templates
     */
    const OPTION_KEY = 'aiseo_title_templates';
    
    /**
     * Default templates
     */
    private $defaults = array(
        'separator' => '|',
        'separator_position' => 'right', // left or right
        'post_title' => '%title% %sep% %sitename%',
        'page_title' => '%title% %sep% %sitename%',
        'category_title' => '%term% %sep% %sitename%',
        'tag_title' => '%term% %sep% %sitename%',
        'taxonomy_title' => '%term% %sep% %sitename%',
        'archive_title' => '%archive_type% %sep% %sitename%',
        'author_title' => '%author% %sep% %sitename%',
        'search_title' => 'Search Results for "%search%" %sep% %sitename%',
        '404_title' => 'Page Not Found %sep% %sitename%',
        'date_title' => '%date% %sep% %sitename%',
        'post_description' => '%excerpt%',
        'page_description' => '%excerpt%',
        'category_description' => '%term_description%',
        'tag_description' => '%term_description%',
        'taxonomy_description' => '%term_description%',
        'author_description' => '%author_bio%',
    );
    
    /**
     * Available placeholders
     */
    private $placeholders = array(
        '%title%' => 'Post/Page title',
        '%sitename%' => 'Site name',
        '%tagline%' => 'Site tagline',
        '%sep%' => 'Separator',
        '%term%' => 'Category/Tag name',
        '%author%' => 'Author name',
        '%search%' => 'Search query',
        '%date%' => 'Archive date',
        '%archive_type%' => 'Archive type (e.g., "Archives")',
        '%page%' => 'Page number',
        '%excerpt%' => 'Post excerpt or trimmed content',
        '%content%' => 'Trimmed post content',
        '%term_description%' => 'Category or tag description',
        '%author_bio%' => 'Author biographical info',
    );
    
    /**
     * Initialize title templates
     */
    public function init() {
        // Filter document title parts
        add_filter('document_title_parts', array($this, 'filter_title_parts'), 15);
        add_filter('document_title_separator', array($this, 'filter_separator'), 10);
    }
    
    /**
     * Get all templates
     *
     * @return array Templates
     */
    public function get_templates() {
        $templates = get_option(self::OPTION_KEY, array());
        return wp_parse_args($templates, $this->defaults);
    }
    
    /**
     * Update templates
     *
     * @param array $templates Templates to update
     * @return bool Success
     */
    public function update_templates($templates) {
        $current = $this->get_templates();
        
        // Sanitize and merge
        foreach ($templates as $key => $value) {
            if (array_key_exists($key, $this->defaults)) {
                $current[$key] = sanitize_text_field($value);
            }
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
     * Filter document title separator
     *
     * @param string $sep Current separator
     * @return string Modified separator
     */
    public function filter_separator($sep) {
        $templates = $this->get_templates();
        return !empty($templates['separator']) ? $templates['separator'] : $sep;
    }
    
    /**
     * Title format for a post or page, used for meta and Open Graph fallbacks.
     *
     * @param WP_Post $post Post.
     * @return string
     */
    public function format_post_title($post) {
        if (!$post instanceof WP_Post) {
            return '';
        }
        $templates = $this->get_templates();
        $key = ($post->post_type === 'page') ? 'page_title' : 'post_title';
        return $this->parse_template($templates[$key], array('title' => $post->post_title), $templates);
    }

    /**
     * Description format for a post or page.
     *
     * @param WP_Post $post Post.
     * @return string
     */
    public function format_post_description($post) {
        if (!$post instanceof WP_Post) {
            return '';
        }
        $templates = $this->get_templates();
        $key = ($post->post_type === 'page') ? 'page_description' : 'post_description';
        $excerpt = (is_string($post->post_excerpt) && $post->post_excerpt !== '') ? $post->post_excerpt : wp_strip_all_tags((string) $post->post_content);
        $parsed = $this->parse_template(
            $templates[$key],
            array('title' => $post->post_title),
            $templates,
            array(
                '%excerpt%' => AISEO_Helpers::truncate_text($excerpt, 160),
                '%content%' => AISEO_Helpers::truncate_text(wp_strip_all_tags((string) $post->post_content), 160),
            )
        );
        return AISEO_Helpers::truncate_text($parsed, 160);
    }

    /**
     * Description format for a category, tag or custom taxonomy archive.
     *
     * @param WP_Term $term Term.
     * @return string
     */
    public function format_term_description($term) {
        if (!$term instanceof WP_Term) {
            return '';
        }
        $templates = $this->get_templates();
        if ($term->taxonomy === 'category') {
            $key = 'category_description';
        } elseif ($term->taxonomy === 'post_tag') {
            $key = 'tag_description';
        } else {
            $key = 'taxonomy_description';
        }
        $term_desc = is_string($term->description) ? wp_strip_all_tags($term->description) : '';
        $parsed = $this->parse_template(
            $templates[$key],
            array('title' => $term->name),
            $templates,
            array(
                '%term%' => $term->name,
                '%term_description%' => AISEO_Helpers::truncate_text($term_desc, 160),
            )
        );
        $parsed = AISEO_Helpers::truncate_text($parsed, 160);
        if ($parsed === '') {
            $parsed = AISEO_Helpers::truncate_text($term_desc !== '' ? $term_desc : $term->name, 160);
        }
        return $parsed;
    }

    /**
     * Filter document title parts
     *
     * @param array $title_parts Title parts
     * @return array Modified title parts
     */
    public function filter_title_parts($title_parts) {
        if (is_author()) {
            $author = get_queried_object();
            if ($author instanceof WP_User) {
                $custom = get_user_meta($author->ID, '_aiseo_title', true);
                if (is_string($custom) && $custom !== '') {
                    return array('title' => $custom);
                }
            }
        }

        $templates = $this->get_templates();
        
        // Determine page type and get appropriate template
        $template = $this->get_current_template($templates);
        
        if (empty($template)) {
            return $title_parts;
        }
        
        // Parse template and build title
        $parsed_title = $this->parse_template($template, $title_parts, $templates);
        
        if (!empty($parsed_title)) {
            // Return as single title to override default behavior
            return array('title' => $parsed_title);
        }
        
        return $title_parts;
    }
    
    /**
     * Get template for current page type
     *
     * @param array $templates All templates
     * @return string Template string
     */
    private function get_current_template($templates) {
        if (is_singular('post')) {
            return $templates['post_title'];
        }
        
        if (is_page()) {
            return $templates['page_title'];
        }
        
        if (is_category()) {
            return $templates['category_title'];
        }
        
        if (is_tag()) {
            return $templates['tag_title'];
        }

        if (is_tax()) {
            return $templates['taxonomy_title'];
        }
        
        if (is_author()) {
            return $templates['author_title'];
        }
        
        if (is_search()) {
            return $templates['search_title'];
        }
        
        if (is_404()) {
            return $templates['404_title'];
        }
        
        if (is_date()) {
            return $templates['date_title'];
        }
        
        if (is_archive()) {
            return $templates['archive_title'];
        }
        
        return '';
    }
    
    /**
     * Parse template and replace placeholders
     *
     * @param string $template Template string
     * @param array $title_parts Original title parts
     * @param array $templates All templates
     * @param array $extra Extra placeholder replacements.
     * @return string Parsed title
     */
    private function parse_template($template, $title_parts, $templates, $extra = array()) {
        $replacements = array(
            '%sitename%' => get_bloginfo('name'),
            '%tagline%' => get_bloginfo('description'),
            '%sep%' => $templates['separator'],
        );
        if (is_array($extra)) {
            $replacements = array_merge($replacements, $extra);
        }
        
        // Get title based on context
        if (isset($title_parts['title'])) {
            $replacements['%title%'] = $title_parts['title'];
        }
        
        // Term name for taxonomies
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                $replacements['%term%'] = $term->name;
            }
        }
        
        // Author name
        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                $replacements['%author%'] = $author->display_name;
            }
        }
        
        // Search query
        if (is_search()) {
            $replacements['%search%'] = get_search_query();
        }
        
        // Date archives. The main loop has not started when the title is built.
        if (is_date()) {
            $year = (int) get_query_var('year');
            $month = (int) get_query_var('monthnum');
            $day = (int) get_query_var('day');
            if (is_day() && $year && $month && $day) {
                $replacements['%date%'] = date_i18n(get_option('date_format'), mktime(0, 0, 0, $month, $day, $year));
            } elseif (is_month() && $year && $month) {
                $replacements['%date%'] = date_i18n('F Y', mktime(0, 0, 0, $month, 1, $year));
            } elseif ($year) {
                $replacements['%date%'] = (string) $year;
            }
        }
        
        // Archive type
        if (is_archive()) {
            $replacements['%archive_type%'] = post_type_archive_title('', false) ?: __('Archives', 'aiseo');
        }
        
        // Page number
        $page = max((int) get_query_var('paged'), (int) get_query_var('page'));
        if ($page > 1) {
            /* translators: %d: number. */
            $replacements['%page%'] = sprintf(__('Page %d', 'aiseo'), $page);
        } else {
            $replacements['%page%'] = '';
        }
        
        // Replace placeholders
        $title = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
        if ($page > 1 && strpos($template, '%page%') === false && $replacements['%page%'] !== '') {
            $title .= ' ' . $templates['separator'] . ' ' . $replacements['%page%'];
        }
        
        // Clean up multiple separators and spaces
        $sep = preg_quote($templates['separator'], '/');
        $title = preg_replace('/\s*' . $sep . '\s*' . $sep . '\s*/', ' ' . $templates['separator'] . ' ', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title, ' ' . $templates['separator']);
        
        return $title;
    }
}
