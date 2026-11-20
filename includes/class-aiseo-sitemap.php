<?php
/**
 * AISEO XML Sitemap Generator
 *
 * Generates and manages XML sitemaps for search engines
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Sitemap {
    
    /**
     * Sitemap cache key
     */
    const CACHE_KEY = 'aiseo_sitemap_cache';
    
    /**
     * Cache duration (12 hours)
     */
    const CACHE_DURATION = 43200;
    
    public function init() {
        if (!$this->is_enabled()) {
            return;
        }
        // Disable WordPress core sitemaps (we're replacing them)
        add_filter('wp_sitemaps_enabled', '__return_false');
        
        // Prevent trailing slash redirect for sitemap URLs
        add_filter('redirect_canonical', array($this, 'prevent_sitemap_redirect'), 10, 2);
        
        // Add rewrite rules for sitemap
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Handle sitemap requests
        add_action('template_redirect', array($this, 'handle_sitemap_request'));
        
        // Clear cache on post save/delete
        add_action('save_post', array($this, 'clear_cache'));
        add_action('delete_post', array($this, 'clear_cache'));
        
        // Add sitemap to robots.txt
        add_filter('robots_txt', array($this, 'add_sitemap_to_robots'), 10, 2);
    }

    /**
     * Whether the XML sitemap is switched on.
     *
     * @return bool
     */
    public function is_enabled() {
        $enabled = get_option('aiseo_enable_sitemap', '1');
        return !in_array($enabled, array(false, 0, '0', ''), true);
    }
    
    /**
     * Add rewrite rules for sitemap
     */
    public function add_rewrite_rules() {
        // Add query vars
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_filter('rewrite_rules_array', array($this, 'register_rewrite_rules'));
    }

    /**
     * @param array $vars Query vars.
     * @return array
     */
    public function register_query_vars($vars) {
        $vars[] = 'aiseo_sitemap';
        $vars[] = 'aiseo_sitemap_type';
        return $vars;
    }

    /**
     * @param array $rules Existing rewrite rules.
     * @return array
     */
    public function register_rewrite_rules($rules) {
        $new_rules = array(
            '^sitemap_index\.xml$' => 'index.php?aiseo_sitemap=index',
            '^post-sitemap\.xml$' => 'index.php?aiseo_sitemap=posts-post-1',
            '^page-sitemap\.xml$' => 'index.php?aiseo_sitemap=posts-page-1',
            '^google-news-sitemap\.xml$' => 'index.php?aiseo_sitemap=newsfeed',
            '^video-sitemap\.xml$' => 'index.php?aiseo_sitemap=videofeed',
            '^image-sitemap\.xml$' => 'index.php?aiseo_sitemap=imagefeed',
            '^([a-z0-9_-]+)-sitemap\.xml$' => 'index.php?aiseo_sitemap=posts-$matches[1]-1',
            '^category-sitemap\.xml$' => 'index.php?aiseo_sitemap=taxonomies-category-1',
            '^post_tag-sitemap\.xml$' => 'index.php?aiseo_sitemap=taxonomies-post_tag-1',
            '^wp-sitemap\.xml$' => 'index.php?aiseo_sitemap=index',
            '^sitemap\.xml$' => 'index.php?aiseo_sitemap=index',
            '^wp-sitemap-posts-([^-]+)-([0-9]+)\.xml$' => 'index.php?aiseo_sitemap=posts-$matches[1]-$matches[2]',
            '^wp-sitemap-taxonomies-([^-]+)-([0-9]+)\.xml$' => 'index.php?aiseo_sitemap=taxonomies-$matches[1]-$matches[2]',
            '^wp-sitemap-users-([0-9]+)\.xml$' => 'index.php?aiseo_sitemap=users-$matches[1]',
        );
        return $new_rules + $rules;
    }
    
    /**
     * Handle sitemap requests
     */
    public function handle_sitemap_request() {
        if (!$this->is_enabled()) {
            return;
        }
        $sitemap = $this->sitemap_var_from_request();
        if ($sitemap === '') {
            $query_sitemap = get_query_var('aiseo_sitemap');
            $sitemap = is_string($query_sitemap) ? $query_sitemap : '';
        }
        
        if ($sitemap === '') {
            return;
        }

        status_header(200);
        if (isset($GLOBALS['wp_query']) && $GLOBALS['wp_query'] instanceof WP_Query) {
            $GLOBALS['wp_query']->is_404 = false;
        }
        
        // Generate sitemap
        if ($sitemap === '1' || $sitemap === 'index') {
            $this->output_sitemap_index();
        } elseif ($sitemap === 'newsfeed') {
            $this->output_news_sitemap();
        } elseif ($sitemap === 'videofeed') {
            $this->output_video_sitemap();
        } elseif ($sitemap === 'imagefeed') {
            $this->output_image_sitemap();
        } else {
            // Parse WordPress standard format: posts-{type}-{page} or taxonomies-{type}-{page} or users-{page}
            $parts = explode('-', $sitemap);
            if (count($parts) >= 3 && $parts[0] === 'posts') {
                // posts-post-1 format
                $post_type = $parts[1];
                $page = isset($parts[2]) ? intval($parts[2]) : 1;
                $this->output_sitemap($post_type, $page);
            } elseif (count($parts) >= 3 && $parts[0] === 'taxonomies') {
                // taxonomies-category-1 format
                $taxonomy = $parts[1];
                $page = isset($parts[2]) ? intval($parts[2]) : 1;
                $this->output_taxonomy_sitemap($taxonomy, $page);
            } elseif (count($parts) >= 2 && $parts[0] === 'users') {
                // users-1 format
                $page = isset($parts[1]) ? intval($parts[1]) : 1;
                $this->output_users_sitemap($page);
            } else {
                // Legacy format support
                $this->output_sitemap($sitemap);
            }
        }
        
        exit;
    }

    /**
     * Read a sitemap address directly from the request path.
     * This still serves the file when the rewrite rules have not been flushed.
     *
     * @return string Sitemap query value, or an empty string.
     */
    private function sitemap_var_from_request() {
        $requested = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = wp_parse_url($requested, PHP_URL_PATH);
        $path = trim((string) $path, '/');

        if ($path === 'sitemap_index.xml' || $path === 'sitemap.xml' || $path === 'wp-sitemap.xml') {
            return 'index';
        }
        if ($path === 'google-news-sitemap.xml') {
            return 'newsfeed';
        }
        if ($path === 'video-sitemap.xml') {
            return 'videofeed';
        }
        if ($path === 'image-sitemap.xml') {
            return 'imagefeed';
        }
        if (preg_match('/^wp-sitemap-posts-([a-z0-9_-]+)-([0-9]+)\.xml$/', $path, $matches)) {
            return 'posts-' . $matches[1] . '-' . $matches[2];
        }
        if (preg_match('/^wp-sitemap-taxonomies-([a-z0-9_-]+)-([0-9]+)\.xml$/', $path, $matches)) {
            return 'taxonomies-' . $matches[1] . '-' . $matches[2];
        }
        if (preg_match('/^wp-sitemap-users-([0-9]+)\.xml$/', $path, $matches)) {
            return 'users-' . $matches[1];
        }
        if ($path === 'author-sitemap.xml') {
            return 'users-1';
        }
        if (preg_match('/^([a-z0-9_-]+)-sitemap\.xml$/', $path, $matches)) {
            if (taxonomy_exists($matches[1]) && !post_type_exists($matches[1])) {
                return 'taxonomies-' . $matches[1] . '-1';
            }
            return 'posts-' . $matches[1] . '-1';
        }
        return '';
    }
    
    /**
     * Output sitemap index
     */
    private function output_sitemap_index() {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?xml-stylesheet type="text/xsl" href="' . esc_url(plugins_url('assets/sitemap.xsl', dirname(__FILE__))) . '"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Get enabled post types
        $post_types = $this->get_enabled_post_types();
        
        foreach ($post_types as $post_type) {
            $lastmod = $this->get_post_type_lastmod($post_type);
            if (!$lastmod) {
                continue;
            }
            
            // Use old-style URLs as primary (e.g., post-sitemap.xml, page-sitemap.xml)
            $sitemap_url = home_url("/{$post_type}-sitemap.xml");
            
            echo "\t<sitemap>\n";
            echo "\t\t<loc>" . esc_url($sitemap_url) . "</loc>\n";
            if ($lastmod) {
                echo "\t\t<lastmod>" . esc_html($lastmod) . "</lastmod>\n";
            }
            echo "\t</sitemap>\n";
        }
        
        // Add taxonomy sitemaps
        $taxonomies = $this->get_enabled_taxonomies();
        
        foreach ($taxonomies as $taxonomy) {
            $lastmod = $this->get_taxonomy_lastmod($taxonomy);
            if (!$lastmod) {
                continue;
            }
            
            // Use old-style URLs (e.g., category-sitemap.xml, post_tag-sitemap.xml)
            $sitemap_url = home_url("/{$taxonomy}-sitemap.xml");
            
            echo "\t<sitemap>\n";
            echo "\t\t<loc>" . esc_url($sitemap_url) . "</loc>\n";
            if ($lastmod) {
                echo "\t\t<lastmod>" . esc_html($lastmod) . "</lastmod>\n";
            }
            echo "\t</sitemap>\n";
        }

        $robots = class_exists('AISEO_Robots') ? (new AISEO_Robots())->get_settings() : array();
        if (empty($robots['noindex_author_archives'])) {
            echo "\t<sitemap>\n";
            echo "\t\t<loc>" . esc_url(home_url('/author-sitemap.xml')) . "</loc>\n";
            echo "\t</sitemap>\n";
        }

        if ($this->news_post_type() !== '') {
            echo "\t<sitemap>\n";
            echo "\t\t<loc>" . esc_url(home_url('/google-news-sitemap.xml')) . "</loc>\n";
            echo "\t</sitemap>\n";
        }
        echo "\t<sitemap>\n";
        echo "\t\t<loc>" . esc_url(home_url('/video-sitemap.xml')) . "</loc>\n";
        echo "\t</sitemap>\n";
        echo "\t<sitemap>\n";
        echo "\t\t<loc>" . esc_url(home_url('/image-sitemap.xml')) . "</loc>\n";
        echo "\t</sitemap>\n";
        
        echo '</sitemapindex>';
    }

    /**
     * Google News sitemap of recent items from the news post type.
     */
    private function output_news_sitemap() {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');

        $language = get_option('aiseo_news_language', '');
        if (!is_string($language) || $language === '') {
            $language = get_bloginfo('language');
        }
        if (! is_string($language) || $language === '') {
            $language = 'en';
        }
        $publication = get_option('aiseo_news_publication', '');
        if (!is_string($publication) || $publication === '') {
            $publication = wp_strip_all_tags(get_bloginfo('name'));
        } else {
            $publication = wp_strip_all_tags($publication);
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
        foreach ($this->get_news_posts() as $post) {
            $loc = get_permalink($post);
            if (! is_string($loc) || $loc === '') {
                continue;
            }
            $title = wp_strip_all_tags(get_the_title($post));
            $published = get_post_time('c', true, $post);
            echo "\t<url>\n";
            echo "\t\t<loc>" . esc_url($loc) . "</loc>\n";
            echo "\t\t<news:news>\n";
            echo "\t\t\t<news:publication>\n";
            echo "\t\t\t\t<news:name>" . esc_html($publication) . "</news:name>\n";
            echo "\t\t\t\t<news:language>" . esc_html($language) . "</news:language>\n";
            echo "\t\t\t</news:publication>\n";
            echo "\t\t\t<news:publication_date>" . esc_html($published) . "</news:publication_date>\n";
            echo "\t\t\t<news:title>" . esc_html($title) . "</news:title>\n";
            echo "\t\t</news:news>\n";
            echo "\t</url>\n";
        }
        echo '</urlset>';
    }

    /**
     * @return string News post type name, or empty.
     */
    public function news_post_type() {
        return post_type_exists('news') ? 'news' : 'post';
    }

    /**
     * News items from the last two days (Google News limit).
     *
     * @return WP_Post[]
     */
    private function get_news_posts() {
        $types = get_post_types(array('public' => true), 'names');
        unset($types['attachment']);
        if (!$types) {
            return array();
        }
        $posts = get_posts(array(
            'post_type' => array_values($types),
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'has_password' => false,
            'date_query' => array(
                array(
                    'after' => '48 hours ago',
                    'inclusive' => true,
                ),
            ),
        ));
        $kept = array();
        foreach ($posts as $post) {
            $exclude_sitemap = get_post_meta($post->ID, '_aiseo_exclude_sitemap', true);
            if ($this->post_is_noindex($post->ID) || in_array($exclude_sitemap, array(true, 1, '1'), true)) {
                continue;
            }
            $schema_type = strtolower((string) get_post_meta($post->ID, '_aiseo_schema_type', true));
            if ($post->post_type !== 'news' && $schema_type !== 'newsarticle') {
                continue;
            }
            $kept[] = $post;
        }
        return $kept;
    }

    /**
     * Video sitemap for posts that embed YouTube or Vimeo.
     */
    private function output_video_sitemap() {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        $schema = class_exists('AISEO_Schema') ? new AISEO_Schema() : null;
        foreach ($this->get_video_posts() as $post) {
            $loc = get_permalink($post);
            $video_url = $schema ? $schema->video_url_for_post($post) : '';
            if (!is_string($loc) || $loc === '' || $video_url === '') {
                continue;
            }
            $title = wp_strip_all_tags(get_the_title($post));
            $thumb = get_the_post_thumbnail_url($post, 'full');
            echo "\t<url>\n";
            echo "\t\t<loc>" . esc_url($loc) . "</loc>\n";
            echo "\t\t<video:video>\n";
            if (is_string($thumb) && $thumb !== '') {
                echo "\t\t\t<video:thumbnail_loc>" . esc_url($thumb) . "</video:thumbnail_loc>\n";
            }
            echo "\t\t\t<video:title>" . esc_html($title) . "</video:title>\n";
            echo "\t\t\t<video:player_loc>" . esc_url($video_url) . "</video:player_loc>\n";
            echo "\t\t</video:video>\n";
            echo "\t</url>\n";
        }
        echo '</urlset>';
    }

    /**
     * Image sitemap of featured, gallery and in-content images, like Rank Math.
     */
    private function output_image_sitemap() {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        $types = $this->get_enabled_post_types();
        if ($types) {
            $posts = get_posts(array(
                'post_type' => $types,
                'post_status' => 'publish',
                'posts_per_page' => 200,
                'orderby' => 'modified',
                'order' => 'DESC',
                'has_password' => false,
                'no_found_rows' => true,
            ));
            foreach ($posts as $post) {
                $exclude_sitemap = get_post_meta($post->ID, '_aiseo_exclude_sitemap', true);
                if ($this->post_is_noindex($post->ID) || in_array($exclude_sitemap, array(true, 1, '1'), true)) {
                    continue;
                }
                $images = $this->get_post_images($post);
                if (!$images) {
                    continue;
                }
                $loc = get_permalink($post);
                if (!is_string($loc) || $loc === '') {
                    continue;
                }
                echo "\t<url>\n";
                echo "\t\t<loc>" . esc_url($loc) . "</loc>\n";
                foreach ($images as $image) {
                    echo "\t\t<image:image>\n";
                    echo "\t\t\t<image:loc>" . esc_url($image['url']) . "</image:loc>\n";
                    if (!empty($image['title'])) {
                        echo "\t\t\t<image:title>" . esc_html($image['title']) . "</image:title>\n";
                    }
                    if (!empty($image['caption'])) {
                        echo "\t\t\t<image:caption>" . esc_html($image['caption']) . "</image:caption>\n";
                    }
                    echo "\t\t</image:image>\n";
                }
                echo "\t</url>\n";
            }
        }
        echo '</urlset>';
    }

    /**
     * @return WP_Post[]
     */
    private function get_video_posts() {
        $types = $this->get_enabled_post_types();
        if (!$types) {
            return array();
        }
        $posts = get_posts(array(
            'post_type' => $types,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'modified',
            'order' => 'DESC',
            'has_password' => false,
            'no_found_rows' => true,
        ));
        $schema = class_exists('AISEO_Schema') ? new AISEO_Schema() : null;
        $kept = array();
        foreach ($posts as $post) {
            $exclude_sitemap = get_post_meta($post->ID, '_aiseo_exclude_sitemap', true);
            if ($this->post_is_noindex($post->ID) || in_array($exclude_sitemap, array(true, 1, '1'), true)) {
                continue;
            }
            if (!$schema || $schema->video_url_for_post($post) === '') {
                continue;
            }
            $kept[] = $post;
        }
        return $kept;
    }
    
    /**
     * Get enabled taxonomies for sitemap
     *
     * @return array Taxonomy names
     */
    private function get_enabled_taxonomies() {
        $taxonomies = get_taxonomies(array(
            'public' => true,
            'publicly_queryable' => true,
        ), 'names');
        unset($taxonomies['post_format']);
        $saved = get_option('aiseo_sitemap_taxonomies', null);
        if (is_array($saved)) {
            $taxonomies = array_intersect($taxonomies, $saved);
        }
        foreach (array_keys($taxonomies) as $taxonomy) {
            if ($this->taxonomy_is_noindex($taxonomy)) {
                unset($taxonomies[$taxonomy]);
            }
        }
        return apply_filters('aiseo_sitemap_taxonomies', array_values($taxonomies));
    }

    /**
     * Whether a post is excluded from XML sitemaps by noindex flags.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    private function post_is_noindex($post_id) {
        if (class_exists('AISEO_Meta')) {
            return AISEO_Meta::post_is_noindex($post_id);
        }
        return in_array(get_post_meta($post_id, '_aiseo_noindex', true), array(true, 1, '1'), true);
    }
    
    /**
     * Get taxonomy last modified date
     *
     * @param string $taxonomy Taxonomy name
     * @return string|null ISO 8601 date or null
     */
    private function get_taxonomy_lastmod($taxonomy) {
        global $wpdb;
        
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'number' => 1,
            'orderby' => 'count',
            'order' => 'DESC',
        ));
        
        if (empty($terms) || is_wp_error($terms)) {
            return null;
        }
        
        // Get most recent post in this taxonomy
        $term = $terms[0];
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'tax_query' => array(
                array(
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ),
            ),
        ));
        
        if (!empty($posts)) {
            return gmdate('c', strtotime($posts[0]->post_modified_gmt));
        }
        
        return null;
    }

    /**
     * Output a taxonomy sitemap, excluding terms marked noindex.
     *
     * @param string $taxonomy Taxonomy name.
     * @param int    $page     Page number.
     */
    private function output_taxonomy_sitemap($taxonomy, $page = 1) {
        $cache_key = self::CACHE_KEY . '_tax_' . $taxonomy . '_' . (int) $page;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->send_sitemap_xml($cached);
            return;
        }
        $xml = $this->generate_taxonomy_sitemap($taxonomy, (int) $page);
        set_transient($cache_key, $xml, self::CACHE_DURATION);
        $this->send_sitemap_xml($xml);
    }

    /**
     * Output an author sitemap when author archives are indexable.
     *
     * @param int $page Page number.
     */
    private function output_users_sitemap($page = 1) {
        $cache_key = self::CACHE_KEY . '_users_' . (int) $page;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->send_sitemap_xml($cached);
            return;
        }
        $xml = $this->generate_users_sitemap((int) $page);
        set_transient($cache_key, $xml, self::CACHE_DURATION);
        $this->send_sitemap_xml($xml);
    }

    /**
     * @param string $taxonomy Taxonomy name.
     * @param int    $page     Page number.
     * @return string
     */
    private function generate_taxonomy_sitemap($taxonomy, $page) {
        $xml = $this->urlset_open();
        if ($page === 1 && taxonomy_exists($taxonomy) && !$this->taxonomy_is_noindex($taxonomy)) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
            ));
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if ($this->term_is_noindex($term)) {
                        continue;
                    }
                    $link = get_term_link($term);
                    if (is_wp_error($link)) {
                        continue;
                    }
                    $lastmod = $this->latest_modified_gmt(array(
                        'tax_query' => array(
                            array(
                                'taxonomy' => $term->taxonomy,
                                'field' => 'term_id',
                                'terms' => array((int) $term->term_id),
                            ),
                        ),
                    ));
                    $xml .= $this->sitemap_url_entry($link, $lastmod);
                }
            }
        }
        return $xml . '</urlset>';
    }

    /**
     * @param int $page Page number.
     * @return string
     */
    private function generate_users_sitemap($page) {
        $xml = $this->urlset_open();
        $robots = class_exists('AISEO_Robots') ? (new AISEO_Robots())->get_settings() : array();
        if ($page === 1 && empty($robots['noindex_author_archives'])) {
            $author_types = array('post');
            if (post_type_exists('product')) {
                $author_types[] = 'product';
            }
            $users = get_users(array(
                'has_published_posts' => $author_types,
                'fields' => array('ID'),
            ));
            foreach ($users as $user) {
                if (in_array(get_user_meta((int) $user->ID, '_aiseo_noindex', true), array(true, 1, '1'), true)) {
                    continue;
                }
                $lastmod = $this->latest_modified_gmt(array(
                    'author' => (int) $user->ID,
                ));
                $xml .= $this->sitemap_url_entry(get_author_posts_url($user->ID), $lastmod);
            }
        }
        return $xml . '</urlset>';
    }

    /**
     * @param string $taxonomy Taxonomy name.
     * @return bool
     */
    private function taxonomy_is_noindex($taxonomy) {
        if (!class_exists('AISEO_Robots')) {
            return false;
        }
        $settings = (new AISEO_Robots())->get_settings();
        if ($taxonomy === 'category') {
            return !empty($settings['noindex_categories']);
        }
        if ($taxonomy === 'post_tag') {
            return !empty($settings['noindex_tags']);
        }
        return false;
    }

    /**
     * @param WP_Term $term Term object.
     * @return bool
     */
    private function term_is_noindex($term) {
        if (!class_exists('AISEO_Taxonomy_SEO')) {
            return false;
        }
        $meta = (new AISEO_Taxonomy_SEO())->get_term_meta($term->term_id, $term->taxonomy);
        return !empty($meta['noindex']);
    }

    /**
     * @return string
     */
    /**
     * Latest published post modification time in GMT.
     *
     * @param array $query_args Extra query arguments.
     * @return string ISO 8601 time, or an empty string.
     */
    private function latest_modified_gmt($query_args) {
        $posts = get_posts(array_merge(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'fields' => 'ids',
        ), $query_args));
        if (!$posts) {
            return '';
        }
        $modified = get_post_modified_time('c', true, $posts[0]);
        return is_string($modified) ? $modified : '';
    }

    /**
     * @param string $loc     Public URL.
     * @param string $lastmod ISO 8601 time.
     * @return string
     */
    private function sitemap_url_entry($loc, $lastmod) {
        $xml = "\t<url>\n\t\t<loc>" . esc_url($loc) . "</loc>\n";
        if ($lastmod !== '') {
            $xml .= "\t\t<lastmod>" . esc_html($lastmod) . "</lastmod>\n";
        }
        return $xml . "\t</url>\n";
    }

    private function urlset_open() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        return $xml;
    }

    /**
     * @param string $xml Sitemap document.
     */
    private function send_sitemap_xml($xml) {
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML sitemap output with proper content-type header
        echo $xml;
    }
    
    /**
     * Output sitemap for specific post type
     *
     * @param string $post_type Post type
     * @param int $page Page number (default 1)
     */
    private function output_sitemap($post_type, $page = 1) {
        // Check cache
        $cache_key = self::CACHE_KEY . '_' . $post_type . '_' . $page;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            header('Content-Type: application/xml; charset=utf-8');
            header('X-Robots-Tag: noindex, follow');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML sitemap output with proper content-type header
            echo $cached;
            return;
        }
        
        // Generate sitemap
        $sitemap = $this->generate_sitemap($post_type);
        
        // Cache it
        set_transient($cache_key, $sitemap, self::CACHE_DURATION);
        
        // Output
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML sitemap output with proper content-type header
        echo $sitemap;
    }
    
    /**
     * Generate sitemap XML for post type
     *
     * @param string $post_type Post type
     * @return string Sitemap XML
     */
    public function generate_sitemap($post_type) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url(plugins_url('assets/sitemap.xsl', dirname(__FILE__))) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        
        // Get posts
        $posts = $this->get_posts_for_sitemap($post_type);
        
        foreach ($posts as $post) {
            $xml .= $this->generate_url_entry($post);
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * Generate URL entry for post
     *
     * @param WP_Post $post Post object
     * @return string URL entry XML
     */
    private function generate_url_entry($post) {
        $xml = "\t<url>\n";
        
        // Location
        $xml .= "\t\t<loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
        
        // Last modified
        $lastmod = get_post_modified_time('c', false, $post);
        $xml .= "\t\t<lastmod>" . esc_html($lastmod) . "</lastmod>\n";
        
        // Change frequency
        $changefreq = $this->get_changefreq($post);
        $xml .= "\t\t<changefreq>" . esc_html($changefreq) . "</changefreq>\n";
        
        // Priority
        $priority = $this->get_priority($post);
        $xml .= "\t\t<priority>" . esc_html($priority) . "</priority>\n";
        
        // Images
        $images = $this->get_post_images($post);
        foreach ($images as $image) {
            $xml .= "\t\t<image:image>\n";
            $xml .= "\t\t\t<image:loc>" . esc_url($image['url']) . "</image:loc>\n";
            if (!empty($image['title'])) {
                $xml .= "\t\t\t<image:title>" . esc_html($image['title']) . "</image:title>\n";
            }
            if (!empty($image['caption'])) {
                $xml .= "\t\t\t<image:caption>" . esc_html($image['caption']) . "</image:caption>\n";
            }
            $xml .= "\t\t</image:image>\n";
        }
        
        $xml .= "\t</url>\n";
        
        return $xml;
    }
    
    /**
     * Get posts for sitemap
     *
     * @param string $post_type Post type
     * @return array Posts
     */
    private function get_posts_for_sitemap($post_type) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'has_password' => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        
        // Exclude posts marked noindex or explicitly removed from the sitemap.
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for sitemap generation, results are cached
        $args['meta_query'] = array(
            'relation' => 'AND',
            array(
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
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => '_aiseo_exclude_sitemap',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key' => '_aiseo_exclude_sitemap',
                    'value' => array('1', 'true'),
                    'compare' => 'NOT IN',
                ),
            ),
        );

        if ($post_type === 'page' && function_exists('wc_get_page_id')) {
            $exclude = array();
            foreach (array('cart', 'checkout', 'myaccount') as $wc_page) {
                $page_id = wc_get_page_id($wc_page);
                if ($page_id > 0) {
                    $exclude[] = $page_id;
                }
            }
            if ($exclude) {
                $args['post__not_in'] = $exclude;
            }
        }
        if ($post_type === 'product' && taxonomy_exists('product_visibility')) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_visibility',
                    'field' => 'name',
                    'terms' => array('exclude-from-catalog'),
                    'operator' => 'NOT IN',
                ),
            );
        }
        
        $query = new WP_Query($args);
        
        return $query->posts;
    }
    
    /**
     * Get change frequency for post
     *
     * @param WP_Post $post Post object
     * @return string Change frequency
     */
    private function get_changefreq($post) {
        // Check for custom changefreq
        $custom = get_post_meta($post->ID, '_aiseo_sitemap_changefreq', true);
        
        if (!empty($custom)) {
            return $custom;
        }
        
        // Calculate based on post age
        $post_age_days = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
        
        if ($post_age_days < 7) {
            return 'daily';
        } elseif ($post_age_days < 30) {
            return 'weekly';
        } elseif ($post_age_days < 365) {
            return 'monthly';
        } else {
            return 'yearly';
        }
    }
    
    /**
     * Get priority for post
     *
     * @param WP_Post $post Post object
     * @return string Priority (0.0 - 1.0)
     */
    private function get_priority($post) {
        // Check for custom priority
        $custom = get_post_meta($post->ID, '_aiseo_sitemap_priority', true);
        
        if (!empty($custom)) {
            return $custom;
        }

        if (get_post_meta($post->ID, '_aiseo_cornerstone', true) === '1') {
            return '0.9';
        }
        
        // Calculate based on post type and homepage
        if ($post->post_type === 'page') {
            // Homepage gets highest priority
            if (get_option('page_on_front') == $post->ID) {
                return '1.0';
            }
            return '0.8';
        }
        
        // Posts get medium-high priority
        if ($post->post_type === 'post') {
            return '0.6';
        }
        
        // Other post types get medium priority
        return '0.5';
    }
    
    /**
     * Get images from post
     *
     * @param WP_Post $post Post object
     * @return array Images
     */
    private function get_post_images($post) {
        $images = array();
        $seen = array();
        $add = function ($url, $title, $caption) use (&$images, &$seen) {
            if (count($images) >= 5) {
                return;
            }
            $url = $this->absolute_image_url($url);
            if ($url === '' || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $images[] = array(
                'url' => $url,
                'title' => is_string($title) ? $title : '',
                'caption' => is_string($caption) ? $caption : '',
            );
        };

        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $caption = wp_get_attachment_caption($thumbnail_id);
            if (!is_string($caption) || $caption === '') {
                $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                $caption = is_string($alt) ? $alt : '';
            }
            $add(wp_get_attachment_image_url($thumbnail_id, 'full'), get_the_title($thumbnail_id), $caption);
        }

        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product && method_exists($product, 'get_gallery_image_ids')) {
                foreach ($product->get_gallery_image_ids() as $gallery_id) {
                    $gallery_id = (int) $gallery_id;
                    if ($gallery_id < 1) {
                        continue;
                    }
                    $caption = wp_get_attachment_caption($gallery_id);
                    if (!is_string($caption) || $caption === '') {
                        $alt = get_post_meta($gallery_id, '_wp_attachment_image_alt', true);
                        $caption = is_string($alt) ? $alt : '';
                    }
                    $add(wp_get_attachment_image_url($gallery_id, 'full'), get_the_title($gallery_id), $caption);
                }
            }
        }

        if (preg_match_all('/<img\b[^>]*>/i', $post->post_content, $tags)) {
            foreach ($tags[0] as $tag) {
                if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $src)) {
                    continue;
                }
                $alt = '';
                if (preg_match('/\balt=["\']([^"\']*)["\']/i', $tag, $alt_match)) {
                    $alt = $alt_match[1];
                }
                $add($src[1], '', $alt);
            }
        }

        return $images;
    }

    /**
     * Accept only a normal image address, and make a root-relative one absolute.
     *
     * @param string $url Image address.
     * @return string
     */
    private function absolute_image_url($url) {
        if (!is_string($url)) {
            return '';
        }
        $url = trim($url);
        if ($url === '' || stripos($url, 'data:') === 0 || stripos($url, 'javascript:') === 0) {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (isset($url[0]) && $url[0] === '/') {
            $url = home_url($url);
        } elseif (!preg_match('#^https?://#i', $url)) {
            return '';
        }
        $safe = esc_url_raw($url);
        return is_string($safe) ? $safe : '';
    }
    
    /**
     * Get enabled post types for sitemap
     *
     * @return array Post types
     */
    public function get_enabled_post_types() {
        $public_types = get_post_types(array('public' => true), 'names');
        unset($public_types['attachment']);
        $saved = get_option('aiseo_sitemap_post_types', null);
        if (!is_array($saved)) {
            $saved = $public_types;
        }
        return array_values(array_intersect($saved, $public_types));
    }

    /**
     * Public post types that have at least one published item.
     *
     * @return array
     */
    public function get_populated_post_types() {
        $types = array();
        foreach ($this->get_enabled_post_types() as $post_type) {
            if ($this->get_post_type_lastmod($post_type)) {
                $types[] = $post_type;
            }
        }
        return $types;
    }
    
    /**
     * Get last modified date for post type
     *
     * @param string $post_type Post type
     * @return string|false Last modified date or false
     */
    private function get_post_type_lastmod($post_type) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'fields' => 'ids',
        );
        
        $query = new WP_Query($args);
        
        if (!empty($query->posts)) {
            $post = get_post($query->posts[0]);
            return get_post_modified_time('c', false, $post);
        }
        
        return false;
    }
    
    /**
     * Clear sitemap cache
     */
    public function clear_cache() {
        $post_types = $this->get_enabled_post_types();
        
        foreach ($post_types as $post_type) {
            delete_transient(self::CACHE_KEY . '_' . $post_type);
            delete_transient(self::CACHE_KEY . '_' . $post_type . '_1');
        }
        foreach ($this->get_enabled_taxonomies() as $taxonomy) {
            delete_transient(self::CACHE_KEY . '_tax_' . $taxonomy . '_1');
        }
        delete_transient(self::CACHE_KEY . '_users_1');
    }
    
    /**
     * Prevent trailing slash redirect for sitemap URLs
     *
     * @param string $redirect_url The redirect URL
     * @param string $requested_url The requested URL
     * @return string|false Modified redirect URL or false to prevent redirect
     */
    public function prevent_sitemap_redirect($redirect_url, $requested_url) {
        // Check if this is a sitemap request
        if (strpos($requested_url, 'wp-sitemap') !== false || 
            strpos($requested_url, 'sitemap') !== false && strpos($requested_url, '.xml') !== false) {
            // Prevent redirect
            return false;
        }
        
        return $redirect_url;
    }
    
    /**
     * Add sitemap to robots.txt
     *
     * @param string $output Robots.txt output
     * @param bool $public Whether site is public
     * @return string Modified output
     */
    public function add_sitemap_to_robots($output, $public) {
        if ($public) {
            $output .= "\n# AISEO Sitemap\n";
            // Use old-style URL as primary for migration compatibility
            $output .= "Sitemap: " . home_url('/sitemap_index.xml') . "\n";
            $output .= "# Also available at:\n";
            $output .= "# " . home_url('/sitemap.xml') . "\n";
            $output .= "# " . home_url('/wp-sitemap.xml') . "\n";
        }
        
        return $output;
    }
    
    /**
     * Ping search engines about sitemap update
     */
    public function ping_search_engines() {
        // Use old-style URL as primary
        $sitemap_url = urlencode(home_url('/sitemap_index.xml'));
        
        // Google
        wp_remote_get("https://www.google.com/ping?sitemap={$sitemap_url}");
        
        // Bing
        wp_remote_get("https://www.bing.com/ping?sitemap={$sitemap_url}");
    }
    
    /**
     * Get sitemap statistics
     *
     * @return array Statistics
     */
    public function get_sitemap_stats() {
        $stats = array(
            'post_types' => array(),
            'total_urls' => 0,
            'last_generated' => '',
        );
        
        $post_types = $this->get_enabled_post_types();
        
        foreach ($post_types as $post_type) {
            $posts = $this->get_posts_for_sitemap($post_type);
            $count = count($posts);
            
            $stats['post_types'][$post_type] = $count;
            $stats['total_urls'] += $count;
        }
        
        // Check if cached
        $cache_key = self::CACHE_KEY . '_' . $post_types[0];
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            $stats['last_generated'] = 'Cached';
        } else {
            $stats['last_generated'] = 'Not cached';
        }
        
        return $stats;
    }
}
