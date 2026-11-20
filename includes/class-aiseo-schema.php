<?php
/**
 * AISEO Schema Markup Generator
 *
 * Generates JSON-LD structured data for better search engine understanding
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Schema {

    /**
     * Print JSON-LD for the current public singular page.
     */
    public function init() {
        add_action('wp_head', array($this, 'print_post_schema'), 20);
        add_action('wp_head', array($this, 'print_archive_schema'), 20);
        add_action('wp_head', array($this, 'print_front_schema'), 20);
        add_action('template_redirect', array($this, 'maybe_serve_aggregation'), 0);
        add_action('rest_api_init', array($this, 'register_aggregation_route'));
        add_filter('robots_txt', array($this, 'robots_pointer'), 21, 2);
    }

    /**
     * Opt-in site-wide schema map for AI systems (Yoast Schema Aggregation equivalent).
     *
     * @return bool
     */
    public function aggregation_is_enabled() {
        $enabled = get_option('aiseo_enable_schema_aggregation', '0');
        return $this->schema_is_enabled() && ! in_array($enabled, array(false, 0, '0', ''), true);
    }

    /**
     * @return string
     */
    public function aggregation_url() {
        return home_url('/aiseo-schema.json');
    }

    /**
     * Point crawlers at the schema map when it is on.
     *
     * @param string $output Existing robots.txt body.
     * @param bool   $public Whether the site is public.
     * @return string
     */
    public function robots_pointer($output, $public) {
        if (!$public || !$this->aggregation_is_enabled()) {
            return $output;
        }
        return rtrim($output) . "\n\n# aiseo-schema.json: " . esc_url_raw($this->aggregation_url()) . "\n";
    }

    /**
     * Serve /aiseo-schema.json.
     */
    public function maybe_serve_aggregation() {
        if (!$this->aggregation_is_enabled() || get_option('blog_public') === '0') {
            return;
        }
        $requested = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $current = wp_parse_url($requested, PHP_URL_PATH);
        if (untrailingslashit((string) $current) !== untrailingslashit(wp_parse_url($this->aggregation_url(), PHP_URL_PATH))) {
            return;
        }
        $json = wp_json_encode($this->aggregation_document(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '') {
            return;
        }
        status_header(200);
        header('Content-Type: application/ld+json; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode.
        exit;
    }

    /**
     * Public REST copy of the schema map.
     */
    public function register_aggregation_route() {
        register_rest_route('aiseo/v1', '/schema-aggregation', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_aggregation'),
            'permission_callback' => array($this, 'rest_aggregation_permission'),
        ));
    }

    /**
     * Public only when the map is on and the site is indexable.
     *
     * @return bool
     */
    public function rest_aggregation_permission() {
        return $this->aggregation_is_enabled() && get_option('blog_public') !== '0';
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function rest_aggregation() {
        if (!$this->aggregation_is_enabled() || get_option('blog_public') === '0') {
            return new WP_Error(
                'aiseo_schema_aggregation_off',
                __('Schema aggregation is off.', 'aiseo'),
                array('status' => 404)
            );
        }
        return rest_ensure_response($this->aggregation_document());
    }

    /**
     * Deduplicated @graph of the site and recent public posts.
     *
     * @return array
     */
    public function aggregation_document() {
        $graph = $this->site_graph_nodes();
        $types = get_post_types(array('public' => true), 'names');
        unset($types['attachment']);
        if ($types) {
            $posts = get_posts(array(
                'post_type' => array_values($types),
                'post_status' => 'publish',
                'posts_per_page' => 50,
                'orderby' => 'modified',
                'order' => 'DESC',
                'has_password' => false,
                'no_found_rows' => true,
            ));
            foreach ($posts as $post) {
                if (class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post->ID)) {
                    continue;
                }
                $generated = $this->generate_schema($post->ID, 'auto');
                if (empty($generated['main']) || !is_array($generated['main'])) {
                    continue;
                }
                $graph = array_merge($graph, $this->connected_post_graph($post, $generated));
            }
        }
        $by_id = array();
        $plain = array();
        foreach ($graph as $node) {
            if (!is_array($node)) {
                continue;
            }
            unset($node['@context']);
            if (!empty($node['@id']) && is_string($node['@id'])) {
                $by_id[$node['@id']] = $node;
            } else {
                $plain[] = $node;
            }
        }
        return array(
            '@context' => 'https://schema.org',
            '@graph' => array_merge(array_values($by_id), $plain),
        );
    }

    /**
     * Whether the schema setting is on.
     *
     * @return bool
     */
    private function schema_is_enabled() {
        $enabled = get_option('aiseo_enable_schema', '1');

        return ! in_array($enabled, array(false, 0, '0', ''), true);
    }

    /**
     * Output one schema graph for the queried post.
     */
    public function print_post_schema() {
        if (is_admin() || is_feed() || ! is_singular() || ! $this->schema_is_enabled()) {
            return;
        }

        $post_id = get_queried_object_id();
        $post = $post_id ? get_post($post_id) : null;
        if (! $post || $post->post_status !== 'publish' || post_password_required($post)) {
            return;
        }

        $generated = $this->generate_schema($post_id, 'auto');
        if (empty($generated['main']) || ! is_array($generated['main'])) {
            return;
        }

        $this->echo_graph(array_merge(
            $this->connected_post_graph($post, $generated),
            $this->site_graph_nodes()
        ));
    }

    /**
     * WebPage, article, breadcrumb and FAQ nodes with matching @id links.
     *
     * @param WP_Post $post      Post.
     * @param array   $generated generate_schema() result.
     * @return array
     */
    private function connected_post_graph($post, $generated) {
        $main = $generated['main'];
        unset($main['@context']);
        $permalink = get_permalink($post);
        if (! is_string($permalink) || $permalink === '') {
            return array($main);
        }
        $home = home_url('/');
        $page_id = $permalink;
        $main_type = isset($generated['main']['@type']) ? $generated['main']['@type'] : '';
        $article_id = $permalink . '#article';
        if ($main_type === 'Product') {
            $article_id = $permalink . '#product';
        } elseif ($main_type === 'HowTo') {
            $article_id = $permalink . '#howto';
        } elseif ($main_type === 'Recipe') {
            $article_id = $permalink . '#recipe';
        } elseif ($main_type === 'Event') {
            $article_id = $permalink . '#event';
        } elseif ($main_type === 'Course') {
            $article_id = $permalink . '#course';
        } elseif ($main_type === 'JobPosting') {
            $article_id = $permalink . '#job';
        }
        $org_id = $this->site_entity_id();
        $website_id = $home . '#website';
        $type = isset($main['@type']) ? $main['@type'] : '';
        if ($type !== 'Product') {
            $main['publisher'] = array('@id' => $org_id);
        }

        $graph = array();
        if ($type === 'WebPage') {
            $main['@id'] = $page_id;
            $main['isPartOf'] = array('@id' => $website_id);
            if (! empty($generated['breadcrumb'])) {
                $main['breadcrumb'] = array('@id' => $permalink . '#breadcrumb');
            }
            $graph[] = $main;
        } else {
            $headline = isset($main['headline']) ? $main['headline'] : (isset($main['name']) ? $main['name'] : $this->get_seo_title($post));
            $webpage = array(
                '@type' => 'WebPage',
                '@id' => $page_id,
                'url' => $permalink,
                'name' => $headline,
                'isPartOf' => array('@id' => $website_id),
                'primaryImageOfPage' => isset($main['image']) ? $main['image'] : null,
                'breadcrumb' => array('@id' => $permalink . '#breadcrumb'),
            );
            if (empty($webpage['primaryImageOfPage'])) {
                unset($webpage['primaryImageOfPage']);
            }
            if (! empty($main['description'])) {
                $webpage['description'] = $main['description'];
            }
            $language = get_bloginfo('language');
            if (is_string($language) && $language !== '') {
                $webpage['inLanguage'] = $language;
            }
            $main['@id'] = $article_id;
            $main['isPartOf'] = array('@id' => $page_id);
            $main['mainEntityOfPage'] = array('@id' => $page_id);
            $webpage['mainEntity'] = array('@id' => $article_id);
            $graph[] = $webpage;
            $graph[] = $main;
        }

        if (! empty($generated['breadcrumb']) && is_array($generated['breadcrumb'])) {
            $breadcrumb = $generated['breadcrumb'];
            unset($breadcrumb['@context']);
            $breadcrumb['@id'] = $permalink . '#breadcrumb';
            $graph[] = $breadcrumb;
        }

        $faq_node = $this->faq_graph_node($post, $page_id);
        if ($faq_node) {
            $graph[] = $faq_node;
        }

        $video_node = $this->video_graph_node($post);
        if ($video_node) {
            $graph[] = $video_node;
        }

        return $graph;
    }

    /**
     * VideoObject when the post embeds a YouTube or Vimeo video that readers can see.
     *
     * @param WP_Post $post Post.
     * @return array|null
     */
    private function video_graph_node($post) {
        $video_url = $this->first_video_url($post);
        if ($video_url === '') {
            return null;
        }
        $thumb = '';
        $image = $this->get_post_image($post->ID);
        if (is_array($image) && ! empty($image['url']) && wp_http_validate_url($image['url'])) {
            $thumb = $image['url'];
        }
        if ($thumb === '' && preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})#', $video_url, $youtube)) {
            $thumb = 'https://img.youtube.com/vi/' . $youtube[1] . '/hqdefault.jpg';
        }
        if ($thumb === '') {
            return null;
        }
        $title = $this->get_seo_title($post);
        $description = $this->get_seo_description($post);
        $node = array(
            '@type' => 'VideoObject',
            '@id' => get_permalink($post) . '#video',
            'name' => $title,
            'description' => $description !== '' ? $description : $title,
            'thumbnailUrl' => $thumb,
            'uploadDate' => get_the_date('c', $post),
        );
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})#', $video_url, $youtube)) {
            $node['embedUrl'] = 'https://www.youtube.com/embed/' . $youtube[1];
        } else {
            $node['contentUrl'] = $video_url;
        }
        return $node;
    }

    /**
     * @param WP_Post $post Post.
     * @return string
     */
    /**
     * Public wrapper for the video sitemap.
     *
     * @param WP_Post $post Post.
     * @return string
     */
    public function video_url_for_post($post) {
        return $this->first_video_url($post);
    }

    private function first_video_url($post) {
        if (! is_string($post->post_content) || $post->post_content === '') {
            return '';
        }
        if (function_exists('parse_blocks')) {
            $from_blocks = $this->video_url_from_blocks(parse_blocks($post->post_content));
            if ($from_blocks !== '') {
                return $from_blocks;
            }
        }
        if (preg_match('#https?://(?:www\.)?(?:youtube\.com/watch\?v=[A-Za-z0-9_-]{11}|youtu\.be/[A-Za-z0-9_-]{11}|vimeo\.com/[0-9]+)#i', $post->post_content, $match)) {
            return esc_url_raw($match[0]);
        }
        return '';
    }

    /**
     * @param array $blocks Gutenberg blocks.
     * @return string
     */
    private function video_url_from_blocks($blocks) {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $name = isset($block['blockName']) ? $block['blockName'] : '';
            $attrs = (isset($block['attrs']) && is_array($block['attrs'])) ? $block['attrs'] : array();
            $url = '';
            if ($name === 'core/embed' && ! empty($attrs['url'])) {
                $url = (string) $attrs['url'];
            } elseif ($name === 'core/video' && ! empty($attrs['src'])) {
                $url = (string) $attrs['src'];
            }
            $url = esc_url_raw($url);
            if ($url !== '' && wp_http_validate_url($url) && $this->is_supported_video_url($url)) {
                return $url;
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $nested = $this->video_url_from_blocks($block['innerBlocks']);
                if ($nested !== '') {
                    return $nested;
                }
            }
        }
        return '';
    }

    /**
     * @param string $url Video URL.
     * @return bool
     */
    private function is_supported_video_url($url) {
        return (bool) preg_match('#(?:youtube\.com/|youtu\.be/|vimeo\.com/|vimeo\.com/video/)#i', $url);
    }

    /**
     * FAQPage node when the post has stored or block FAQs that readers can see.
     *
     * @param WP_Post $post    Post.
     * @param string  $page_id WebPage @id.
     * @return array|null
     */
    private function faq_graph_node($post, $page_id) {
        $faqs = $this->faqs_for_post($post);
        if (! $faqs) {
            return null;
        }
        $questions = array();
        foreach ($faqs as $faq) {
            $questions[] = array(
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ),
            );
        }
        return array(
            '@type' => 'FAQPage',
            '@id' => get_permalink($post) . '#faq',
            'isPartOf' => array('@id' => $page_id),
            'mainEntity' => $questions,
        );
    }

    /**
     * Saved FAQs plus Yoast/Rank Math FAQ blocks in the content.
     *
     * @param WP_Post $post Post.
     * @return array
     */
    private function faqs_for_post($post) {
        $faqs = array();
        $stored = get_post_meta($post->ID, '_aiseo_faqs', true);
        if (is_array($stored)) {
            foreach ($stored as $key => $item) {
                $question = '';
                $answer = '';
                if (is_array($item)) {
                    $question = isset($item['question']) ? $item['question'] : (isset($item['q']) ? $item['q'] : '');
                    $answer = isset($item['answer']) ? $item['answer'] : (isset($item['a']) ? $item['a'] : '');
                } elseif (is_string($key) && ! is_numeric($key) && is_string($item)) {
                    $question = $key;
                    $answer = $item;
                }
                $question = wp_strip_all_tags((string) $question);
                $answer = wp_strip_all_tags((string) $answer);
                if ($question !== '' && $answer !== '') {
                    $faqs[] = array(
                        'question' => $question,
                        'answer' => $answer,
                    );
                }
            }
        }
        if (function_exists('parse_blocks') && is_string($post->post_content) && $post->post_content !== '') {
            $faqs = array_merge($faqs, $this->faqs_from_blocks(parse_blocks($post->post_content)));
        }
        $unique = array();
        $seen = array();
        foreach ($faqs as $faq) {
            $key = strtolower($faq['question']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $faq;
        }
        return $unique;
    }

    /**
     * @param array $blocks Gutenberg blocks.
     * @return array
     */
    private function faqs_from_blocks($blocks) {
        $faqs = array();
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $name = isset($block['blockName']) ? $block['blockName'] : '';
            $attrs = (isset($block['attrs']) && is_array($block['attrs'])) ? $block['attrs'] : array();
            $items = (isset($attrs['questions']) && is_array($attrs['questions'])) ? $attrs['questions'] : array();
            if ($name === 'rank-math/faq-block') {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $question = isset($item['title']) ? wp_strip_all_tags((string) $item['title']) : '';
                    $answer = isset($item['content']) ? wp_strip_all_tags((string) $item['content']) : '';
                    if ($question !== '' && $answer !== '') {
                        $faqs[] = array(
                            'question' => $question,
                            'answer' => $answer,
                        );
                    }
                }
            }
            if ($name === 'aiseo/faq') {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $question = isset($item['question']) ? wp_strip_all_tags((string) $item['question']) : '';
                    $answer = isset($item['answer']) ? wp_strip_all_tags((string) $item['answer']) : '';
                    if ($question !== '' && $answer !== '') {
                        $faqs[] = array(
                            'question' => $question,
                            'answer' => $answer,
                        );
                    }
                }
            }
            if ($name === 'yoast/faq-block') {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $question = $this->yoast_faq_text(isset($item['question']) ? $item['question'] : '');
                    $answer = $this->yoast_faq_text(isset($item['answer']) ? $item['answer'] : '');
                    if ($question !== '' && $answer !== '') {
                        $faqs[] = array(
                            'question' => $question,
                            'answer' => $answer,
                        );
                    }
                }
            }
            if (! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $faqs = array_merge($faqs, $this->faqs_from_blocks($block['innerBlocks']));
            }
        }
        return $faqs;
    }

    /**
     * @param mixed $value Yoast FAQ attribute.
     * @return string
     */
    private function yoast_faq_text($value) {
        if (is_string($value)) {
            return wp_strip_all_tags($value);
        }
        if (! is_array($value)) {
            return '';
        }
        $parts = array();
        array_walk_recursive(
            $value,
            static function ($item) use (&$parts) {
                if (is_string($item) && $item !== '') {
                    $parts[] = $item;
                }
            }
        );
        return wp_strip_all_tags(implode(' ', $parts));
    }

    /**
     * Output a collection or profile graph for an archive.
     */
    public function print_archive_schema() {
        if (is_admin() || is_feed() || is_singular() || ! $this->schema_is_enabled()) {
            return;
        }
        if (! is_category() && ! is_tag() && ! is_tax() && ! is_author() && ! is_post_type_archive()) {
            return;
        }

        $name = '';
        $description = '';
        $url = '';
        $type = 'CollectionPage';

        if (is_post_type_archive()) {
            $post_type = get_queried_object();
            if (! $post_type instanceof WP_Post_Type) {
                return;
            }
            $name = $post_type->labels->name;
            $description = $post_type->description;
            $archive = get_post_type_archive_link($post_type->name);
            if (! is_string($archive)) {
                return;
            }
            $url = $archive;
        } elseif (is_author()) {
            $user = get_queried_object();
            if (! $user instanceof WP_User) {
                return;
            }
            $type = 'ProfilePage';
            $name = $user->display_name;
            $description = get_user_meta($user->ID, 'description', true);
            $url = get_author_posts_url($user->ID);
        } else {
            $term = get_queried_object();
            if (! $term instanceof WP_Term) {
                return;
            }
            $meta = class_exists('AISEO_Taxonomy_SEO') ? (new AISEO_Taxonomy_SEO())->get_term_meta($term->term_id, $term->taxonomy) : array();
            $name = $term->name;
            $description = ! empty($meta['description']) ? $meta['description'] : $term->description;
            $term_link = get_term_link($term);
            if (is_wp_error($term_link)) {
                return;
            }
            $url = $term_link;
        }

        $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
        if ($paged > 1) {
            $url = get_pagenum_link($paged);
        }
        $url = esc_url($url);
        if ($name === '' || $url === '') {
            return;
        }
        $description = is_string($description) ? wp_strip_all_tags($description) : '';

        $items = array();
        $position = 1;
        global $wp_query;
        $archive_posts = ($wp_query instanceof WP_Query) ? $wp_query->posts : array();
        foreach ($archive_posts as $archive_post) {
            $permalink = get_permalink($archive_post);
            if (! $permalink) {
                continue;
            }
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => get_the_title($archive_post),
                'url' => $permalink,
            );
            $position++;
        }

        $graph = array(
            array(
                '@type' => $type,
                'name' => $name,
                'url' => $url,
                'isPartOf' => array(
                    '@type' => 'WebSite',
                    'name' => get_bloginfo('name'),
                    'url' => home_url('/'),
                ),
            ),
            array(
                '@type' => 'BreadcrumbList',
                'itemListElement' => array(
                    array(
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => $this->breadcrumb_home_name(),
                        'item' => home_url('/'),
                    ),
                    array(
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => $name,
                        'item' => $url,
                    ),
                ),
            ),
        );
        if ($description !== '') {
            $graph[0]['description'] = $description;
        }
        if ($items) {
            $graph[] = array(
                '@type' => 'ItemList',
                'itemListElement' => $items,
            );
        }

        $this->echo_graph(array_merge($graph, $this->site_graph_nodes()));
    }

    /**
     * WebSite and Organization on the posts homepage.
     * A static front page already includes these nodes with the page schema.
     */
    public function print_front_schema() {
        if (is_admin() || is_feed() || is_singular() || ! $this->schema_is_enabled()) {
            return;
        }
        if (! is_front_page()) {
            return;
        }
        $this->echo_graph($this->site_graph_nodes());
    }

    /**
     * Site identity and the on-site search action.
     *
     * @return array
     */
    private function site_graph_nodes() {
        $home = home_url('/');
        $website = array(
            '@type' => 'WebSite',
            '@id' => $home . '#website',
            'name' => get_bloginfo('name'),
            'url' => $home,
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => array(
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $home . '?s={search_term_string}',
                ),
                'query-input' => 'required name=search_term_string',
            ),
        );
        $description = wp_strip_all_tags(get_bloginfo('description'));
        if ($description !== '') {
            $website['description'] = $description;
        }
        $organization = $this->generate_organization_schema();
        $organization['@id'] = $this->site_entity_id();
        $website['publisher'] = array('@id' => $organization['@id']);
        return array($website, $organization);
    }

    /**
     * @return string WebSite publisher @id.
     */
    private function site_entity_id() {
        $fragment = $this->site_person_user() ? '#person' : '#organization';
        return home_url('/') . $fragment;
    }

    /**
     * User chosen when the site represents a person.
     *
     * @return WP_User|null
     */
    private function site_person_user() {
        if (get_option('aiseo_site_represents', 'organization') !== 'person') {
            return null;
        }
        $user_id = absint(get_option('aiseo_schema_person_id', 0));
        if (! $user_id) {
            return null;
        }
        $user = get_userdata($user_id);
        return ($user instanceof WP_User) ? $user : null;
    }

    /**
     * Print one JSON-LD graph.
     *
     * @param array $graph Schema nodes.
     */
    private function echo_graph($graph) {
        $clean = array();
        foreach ($graph as $node) {
            if (! is_array($node)) {
                continue;
            }
            unset($node['@context']);
            $clean[] = $node;
        }
        if (! $clean) {
            return;
        }
        $json = wp_json_encode(
            array(
                '@context' => 'https://schema.org',
                '@graph' => $clean,
            ),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (! $json) {
            return;
        }
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode.
    }
    
    /**
     * Generate schema markup for a post
     *
     * @param int $post_id Post ID
     * @param string $schema_type Schema type (auto, article, blogposting, webpage)
     * @return array|false Schema markup array or false on failure
     */
    public function generate_schema($post_id, $schema_type = 'auto') {
        $post = get_post($post_id);
        
        if (!$post) {
            return false;
        }
        
        // Auto-detect schema type if needed
        if ($schema_type === 'auto') {
            $schema_type = $this->detect_schema_type($post);
        }
        
        // Generate base schema
        $schema = array(
            '@context' => 'https://schema.org',
        );
        
        // Generate specific schema based on type
        switch ($schema_type) {
            case 'article':
                $schema = array_merge($schema, $this->generate_article_schema($post));
                break;
            case 'newsarticle':
                $schema = array_merge($schema, $this->generate_article_schema($post));
                $schema['@type'] = 'NewsArticle';
                break;
            case 'blogposting':
                $schema = array_merge($schema, $this->generate_blogposting_schema($post));
                break;
            case 'webpage':
                $schema = array_merge($schema, $this->generate_webpage_schema($post));
                break;
            case 'product':
                $schema = array_merge($schema, $this->generate_product_schema($post));
                break;
            case 'howto':
                $howto = $this->howto_from_post($post);
                $schema = array_merge($schema, $howto ? $howto : $this->generate_article_schema($post));
                break;
            case 'recipe':
                $recipe = $this->recipe_from_post($post);
                $schema = array_merge($schema, $recipe ? $recipe : $this->generate_article_schema($post));
                break;
            case 'event':
                $event = $this->event_from_post($post);
                $schema = array_merge($schema, $event ? $event : $this->generate_article_schema($post));
                break;
            case 'course':
                $schema = array_merge($schema, $this->course_from_post($post));
                break;
            case 'jobposting':
                $schema = array_merge($schema, $this->jobposting_from_post($post));
                break;
            default:
                $schema = array_merge($schema, $this->generate_article_schema($post));
        }
        
        if ($schema_type !== 'product') {
            $schema['publisher'] = $this->generate_organization_schema();
        }
        $language = get_bloginfo('language');
        if (is_string($language) && $language !== '') {
            $schema['inLanguage'] = $language;
        }
        
        // Add breadcrumb schema if applicable
        $breadcrumb = $this->generate_breadcrumb_schema($post);
        
        // Return as array of schemas
        return array(
            'main' => $schema,
            'breadcrumb' => $breadcrumb,
        );
    }
    
    /**
     * Detect appropriate schema type for post
     *
     * @param WP_Post $post Post object
     * @return string Schema type
     */
    private function detect_schema_type($post) {
        $saved = get_post_meta($post->ID, '_aiseo_schema_type', true);
        if (is_string($saved) && $saved !== '') {
            $saved = strtolower(preg_replace('/[^a-z]/', '', $saved));
            $allowed = array('article', 'blogposting', 'webpage', 'newsarticle', 'product', 'howto', 'recipe', 'event', 'course', 'jobposting');
            if (in_array($saved, $allowed, true)) {
                return $saved;
            }
        }

        // Check post type
        if ($post->post_type === 'page') {
            return 'webpage';
        }

        if ($post->post_type === 'news') {
            return 'newsarticle';
        }

        if ($post->post_type === 'product' && function_exists('wc_get_product')) {
            return 'product';
        }
        
        // Check if it's a blog post
        if ($post->post_type === 'post') {
            // Check word count - longer posts are articles
            $word_count = str_word_count(wp_strip_all_tags($post->post_content));
            
            if ($word_count > 1000) {
                return 'article';
            } else {
                return 'blogposting';
            }
        }
        
        return 'article';
    }
    
    /**
     * Generate Article schema
     *
     * @param WP_Post $post Post object
     * @return array Schema data
     */
    private function generate_article_schema($post) {
        $schema = array(
            '@type' => 'Article',
            'headline' => $this->get_seo_title($post),
            'description' => $this->get_seo_description($post),
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'author' => $this->generate_author_schema($post),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post->ID),
            ),
        );
        
        // Add image if available
        $image = $this->get_post_image($post->ID);
        if ($image) {
            $schema['image'] = $image;
        }
        
        // Add word count
        $word_count = str_word_count(wp_strip_all_tags($post->post_content));
        $schema['wordCount'] = $word_count;
        
        $category = class_exists('AISEO_Breadcrumbs') ? AISEO_Breadcrumbs::primary_category($post->ID) : null;
        if ($category) {
            $schema['articleSection'] = $category->name;
        }
        
        return $schema;
    }
    
    /**
     * Generate BlogPosting schema
     *
     * @param WP_Post $post Post object
     * @return array Schema data
     */
    private function generate_blogposting_schema($post) {
        $schema = array(
            '@type' => 'BlogPosting',
            'headline' => $this->get_seo_title($post),
            'description' => $this->get_seo_description($post),
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
            'author' => $this->generate_author_schema($post),
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id' => get_permalink($post->ID),
            ),
        );
        
        // Add image if available
        $image = $this->get_post_image($post->ID);
        if ($image) {
            $schema['image'] = $image;
        }
        
        // Add word count
        $word_count = str_word_count(wp_strip_all_tags($post->post_content));
        $schema['wordCount'] = $word_count;

        $category = class_exists('AISEO_Breadcrumbs') ? AISEO_Breadcrumbs::primary_category($post->ID) : null;
        if ($category) {
            $schema['articleSection'] = $category->name;
        }
        
        return $schema;
    }
    
    /**
     * Generate WebPage schema
     *
     * @param WP_Post $post Post object
     * @return array Schema data
     */
    private function generate_webpage_schema($post) {
        $schema = array(
            '@type' => 'WebPage',
            'name' => $this->get_seo_title($post),
            'description' => $this->get_seo_description($post),
            'url' => get_permalink($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified' => get_the_modified_date('c', $post->ID),
        );
        
        // Add image if available
        $image = $this->get_post_image($post->ID);
        if ($image) {
            $schema['image'] = $image;
        }
        
        return $schema;
    }

    /**
     * WooCommerce Product schema when the shop plugin is active.
     *
     * @param WP_Post $post Product post.
     * @return array
     */
    private function generate_product_schema($post) {
        if (! function_exists('wc_get_product')) {
            return $this->generate_webpage_schema($post);
        }
        $product = wc_get_product($post->ID);
        if (! $product) {
            return $this->generate_webpage_schema($post);
        }

        $schema = array(
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => $this->get_seo_description($post),
            'url' => get_permalink($post->ID),
        );
        $sku = $product->get_sku();
        if (is_string($sku) && $sku !== '') {
            $schema['sku'] = $sku;
        }
        $brand = $product->get_attribute('brand');
        if (is_string($brand) && trim($brand) !== '') {
            $schema['brand'] = array(
                '@type' => 'Brand',
                'name' => wp_strip_all_tags($brand),
            );
        }
        foreach (array('color', 'size', 'material', 'mpn') as $attr) {
            $value = $product->get_attribute($attr);
            if (is_string($value) && trim($value) !== '') {
                $schema[$attr] = wp_strip_all_tags($value);
            }
        }
        if (method_exists($product, 'get_global_unique_id')) {
            $gtin = $product->get_global_unique_id();
            if (is_string($gtin) && $gtin !== '') {
                $schema['gtin'] = $gtin;
            }
        }
        $image = $this->get_post_image($post->ID);
        if ($image) {
            $schema['image'] = $image;
        }

        $price = $product->is_type('variable') ? $product->get_variation_price('min', true) : $product->get_price();
        $availability = 'https://schema.org/OutOfStock';
        if (method_exists($product, 'is_on_backorder') && $product->is_on_backorder()) {
            $availability = 'https://schema.org/BackOrder';
        } elseif ($product->is_in_stock()) {
            $availability = 'https://schema.org/InStock';
        }
        $offer = array(
            '@type' => 'Offer',
            'url' => get_permalink($post->ID),
            'availability' => $availability,
            'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
        );
        if ($price !== '' && $price !== null && is_numeric($price)) {
            $offer['price'] = (string) $price;
        }
        if (method_exists($product, 'is_on_sale') && $product->is_on_sale() && method_exists($product, 'get_date_on_sale_to')) {
            $sale_to = $product->get_date_on_sale_to();
            if (is_object($sale_to) && method_exists($sale_to, 'date')) {
                $offer['priceValidUntil'] = $sale_to->date('Y-m-d');
            }
        }
        $schema['offers'] = $offer;

        $rating_count = (int) $product->get_rating_count();
        $average = (float) $product->get_average_rating();
        if ($rating_count > 0 && $average > 0) {
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $average,
                'reviewCount' => $rating_count,
            );
        }

        return $schema;
    }
    
    /**
     * Generate Organization schema
     *
     * @return array Schema data
     */
    private function generate_organization_schema() {
        $person = $this->site_person_user();
        $same_as = array();
        if ($person) {
            $schema = array(
                '@type' => 'Person',
                'name' => $person->display_name,
                'url' => get_author_posts_url($person->ID),
            );
            $website = $person->user_url;
            if (is_string($website) && wp_http_validate_url($website)) {
                $same_as[] = $website;
            }
        } else {
            $schema = array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => get_site_url(),
            );
        }

        $details = get_option('aiseo_organization_details', array());
        if (!is_array($details)) {
            $details = array();
        }
        if (!$person && !empty($details['name'])) {
            $schema['name'] = $details['name'];
        }
        if (!$person && !empty($details['legal_name'])) {
            $schema['legalName'] = $details['legal_name'];
        }
        if (!$person) {
            $logo_id = !empty($details['logo_id']) ? absint($details['logo_id']) : (int) get_theme_mod('custom_logo');
            if ($logo_id) {
                $logo = wp_get_attachment_image_src($logo_id, 'full');
                if ($logo) {
                    $schema['logo'] = array(
                        '@type' => 'ImageObject',
                        'url' => $logo[0],
                        'width' => $logo[1],
                        'height' => $logo[2],
                    );
                }
            }
        }
        if (!empty($details['phone'])) {
            $schema['telephone'] = $details['phone'];
        }
        if (!empty($details['email']) && is_email($details['email'])) {
            $schema['email'] = $details['email'];
        }
        $address = array();
        foreach (array(
            'street' => 'streetAddress',
            'locality' => 'addressLocality',
            'region' => 'addressRegion',
            'postal' => 'postalCode',
            'country' => 'addressCountry',
        ) as $detail_key => $schema_key) {
            if (!empty($details[$detail_key])) {
                $address[$schema_key] = $details[$detail_key];
            }
        }
        if ($address) {
            $address['@type'] = 'PostalAddress';
            $schema['address'] = $address;
            if (!$person && !empty($address['streetAddress']) && !empty($address['addressLocality'])) {
                $schema['@type'] = array('Organization', 'LocalBusiness');
            }
        }

        $stored = get_option('aiseo_organization_sameas', array());
        if (is_array($stored)) {
            foreach ($stored as $url) {
                if (is_string($url) && wp_http_validate_url($url)) {
                    $same_as[] = $url;
                }
            }
        }
        $twitter = get_option('aiseo_twitter_site', '');
        if (is_string($twitter) && preg_match('/^@([A-Za-z0-9_]{1,15})$/', $twitter, $handle)) {
            $same_as[] = 'https://x.com/' . $handle[1];
        }
        $same_as = array_values(array_unique($same_as));
        if ($same_as) {
            $schema['sameAs'] = $same_as;
        }
        
        return $schema;
    }
    
    /**
     * Generate Author/Person schema
     *
     * @param WP_Post $post Post object
     * @return array Schema data
     */
    private function generate_author_schema($post) {
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        
        if (!$author) {
            return array(
                '@type' => 'Person',
                'name' => 'Unknown',
            );
        }
        
        $schema = array(
            '@type' => 'Person',
            'name' => $author->display_name,
            'url' => get_author_posts_url($author_id),
        );

        $website = $author->user_url;
        if (is_string($website) && wp_http_validate_url($website) && untrailingslashit($website) !== untrailingslashit($schema['url'])) {
            $schema['sameAs'] = array($website);
        }
        $avatar = get_avatar_data($author_id, array('size' => 256));
        if (!empty($avatar['found_avatar']) && !empty($avatar['url']) && wp_http_validate_url($avatar['url'])) {
            $schema['image'] = $avatar['url'];
        }
        
        $description = get_user_meta($author_id, 'description', true);
        if (is_string($description) && trim($description) !== '') {
            $schema['description'] = wp_strip_all_tags($description);
        }
        
        return $schema;
    }
    
    /**
     * Generate BreadcrumbList schema
     *
     * @param WP_Post $post Post object
     * @return array|false Schema data or false
     */
    /**
     * Home label shared with the visible breadcrumb trail.
     *
     * @return string
     */
    private function breadcrumb_home_name() {
        if (class_exists('AISEO_Breadcrumbs')) {
            $settings = (new AISEO_Breadcrumbs())->get_settings();
            if (!empty($settings['home_text']) && is_string($settings['home_text'])) {
                return $settings['home_text'];
            }
        }
        return __('Home', 'aiseo');
    }

    private function generate_breadcrumb_schema($post) {
        $breadcrumbs = array();
        
        // Home
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => 1,
            'name' => $this->breadcrumb_home_name(),
            'item' => get_site_url(),
        );
        
        $position = 2;
        
        // Add categories for posts
        if ($post->post_type === 'post') {
            $category = class_exists('AISEO_Breadcrumbs') ? AISEO_Breadcrumbs::primary_category($post->ID) : null;
            if ($category) {
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $category->name,
                    'item' => get_category_link($category->term_id),
                );
            }
        } elseif ($post->post_type !== 'page') {
            $post_type = get_post_type_object($post->post_type);
            if ($post_type && !empty($post_type->has_archive)) {
                $archive = get_post_type_archive_link($post->post_type);
                if (is_string($archive) && $archive !== '') {
                    $breadcrumbs[] = array(
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $post_type->labels->name,
                        'item' => $archive,
                    );
                }
            }
        }
        
        // Add parent pages
        if ($post->post_parent) {
            $parent_id = $post->post_parent;
            $parents = array();
            
            while ($parent_id) {
                $parent = get_post($parent_id);
                if ($parent) {
                    $parents[] = $parent;
                    $parent_id = $parent->post_parent;
                } else {
                    break;
                }
            }
            
            // Reverse to get correct order
            $parents = array_reverse($parents);
            
            foreach ($parents as $parent) {
                $breadcrumbs[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $parent->post_title,
                    'item' => get_permalink($parent->ID),
                );
            }
        }
        
        // Current page
        $breadcrumbs[] = array(
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $post->post_title,
            'item' => get_permalink($post->ID),
        );
        
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $breadcrumbs,
        );
    }
    
    /**
     * Generate FAQ schema
     *
     * @param array $faqs Array of FAQ items (question => answer)
     * @return array Schema data
     */
    public function generate_faq_schema($faqs) {
        if (empty($faqs)) {
            return false;
        }
        
        $questions = array();
        
        foreach ($faqs as $question => $answer) {
            $questions[] = array(
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text' => $answer,
                ),
            );
        }
        
        return array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $questions,
        );
    }
    
    /**
     * Generate HowTo schema
     *
     * @param string $name How-to name
     * @param array $steps Array of steps
     * @param array $options Additional options
     * @return array Schema data
     */
    public function generate_howto_schema($name, $steps, $options = array()) {
        if (empty($steps)) {
            return false;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => $name,
        );
        
        // Add description if provided
        if (isset($options['description'])) {
            $schema['description'] = $options['description'];
        }
        
        // Add total time if provided
        if (isset($options['total_time'])) {
            $schema['totalTime'] = $options['total_time']; // ISO 8601 duration format
        }
        
        // Add steps
        $step_items = array();
        $position = 1;
        
        foreach ($steps as $step) {
            $step_item = array(
                '@type' => 'HowToStep',
                'position' => $position++,
                'name' => $step['name'],
                'text' => $step['text'],
            );
            
            // Add image if provided
            if (isset($step['image'])) {
                $step_item['image'] = $step['image'];
            }
            
            $step_items[] = $step_item;
        }
        
        $schema['step'] = $step_items;
        
        return $schema;
    }

    /**
     * HowTo markup from the first ordered list in the post.
     *
     * @param WP_Post $post Post.
     * @return array|false
     */
    private function howto_from_post($post) {
        $steps = array();
        if (!is_string($post->post_content) || $post->post_content === '') {
            return false;
        }
        if (preg_match_all('/<ol\b[^>]*>(.*?)<\/ol>/is', $post->post_content, $lists)) {
            foreach ($lists[1] as $list) {
                if (!preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $list, $items)) {
                    continue;
                }
                foreach ($items[1] as $item) {
                    $text = trim(wp_strip_all_tags($item));
                    if ($text === '') {
                        continue;
                    }
                    $steps[] = array(
                        'name' => wp_trim_words($text, 8, ''),
                        'text' => $text,
                    );
                }
                if (count($steps) >= 2) {
                    break;
                }
                $steps = array();
            }
        }
        if (count($steps) < 2) {
            return false;
        }
        $generated = $this->generate_howto_schema(
            $this->get_seo_title($post),
            $steps,
            array('description' => $this->get_seo_description($post))
        );
        if (!is_array($generated)) {
            return false;
        }
        unset($generated['@context']);
        return $generated;
    }

    /**
     * Recipe markup from the first unordered list (ingredients) and ordered list (steps).
     *
     * @param WP_Post $post Post.
     * @return array|false
     */
    private function recipe_from_post($post) {
        $ingredients = $this->list_item_texts($post->post_content, 'ul');
        $steps = $this->list_item_texts($post->post_content, 'ol');
        if (count($ingredients) < 2 || count($steps) < 2) {
            return false;
        }
        $schema = array(
            '@type' => 'Recipe',
            'name' => $this->get_seo_title($post),
            'description' => $this->get_seo_description($post),
            'recipeIngredient' => $ingredients,
            'recipeInstructions' => array(),
        );
        foreach ($steps as $index => $text) {
            $schema['recipeInstructions'][] = array(
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'text' => $text,
            );
        }
        $image = get_the_post_thumbnail_url($post, 'full');
        if (is_string($image) && $image !== '') {
            $schema['image'] = $image;
        }
        $author = get_userdata($post->post_author);
        if ($author) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name' => $author->display_name,
            );
        }
        return $schema;
    }

    /**
     * Event markup from post meta, with organisation address as location fallback.
     *
     * @param WP_Post $post Post.
     * @return array|false
     */
    private function event_from_post($post) {
        $start = get_post_meta($post->ID, '_aiseo_event_start', true);
        $start_iso = '';
        if (is_string($start) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $start)) {
            $timestamp = strtotime($start);
            if ($timestamp) {
                $start_iso = gmdate('c', $timestamp);
            }
        }
        if ($start_iso === '') {
            $start_iso = get_post_time('c', true, $post);
        }
        if (!is_string($start_iso) || $start_iso === '') {
            return false;
        }
        $location_name = get_post_meta($post->ID, '_aiseo_event_location', true);
        if (!is_string($location_name) || $location_name === '') {
            $org = $this->generate_organization_schema();
            $location_name = isset($org['name']) ? $org['name'] : get_bloginfo('name');
        }
        $schema = array(
            '@type' => 'Event',
            'name' => $this->get_seo_title($post),
            'description' => $this->get_seo_description($post),
            'startDate' => $start_iso,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => array(
                '@type' => 'Place',
                'name' => $location_name,
            ),
        );
        $image = get_the_post_thumbnail_url($post, 'full');
        if (is_string($image) && $image !== '') {
            $schema['image'] = $image;
        }
        return $schema;
    }

    /**
     * Course markup with the organisation as provider.
     *
     * @param WP_Post $post Post.
     * @return array
     */
    private function course_from_post($post) {
        $org = $this->generate_organization_schema();
        $schema = array(
            '@type' => 'Course',
            'name' => $this->get_seo_title($post),
            'description' => $this->get_seo_description($post),
            'provider' => array(
                '@type' => isset($org['@type']) ? $org['@type'] : 'Organization',
                'name' => isset($org['name']) ? $org['name'] : get_bloginfo('name'),
            ),
        );
        $url = get_permalink($post);
        if (is_string($url) && $url !== '') {
            $schema['url'] = $url;
        }
        return $schema;
    }

    /**
     * JobPosting markup from post meta, with organisation as employer.
     *
     * @param WP_Post $post Post.
     * @return array
     */
    private function jobposting_from_post($post) {
        $org = $this->generate_organization_schema();
        $location = get_post_meta($post->ID, '_aiseo_job_location', true);
        if (!is_string($location) || $location === '') {
            $location = isset($org['name']) ? $org['name'] : get_bloginfo('name');
        }
        $type = get_post_meta($post->ID, '_aiseo_job_type', true);
        $allowed = array('FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'OTHER');
        if (!is_string($type) || !in_array($type, $allowed, true)) {
            $type = 'FULL_TIME';
        }
        $schema = array(
            '@type' => 'JobPosting',
            'title' => $this->get_seo_title($post),
            'description' => wp_kses_post($post->post_content ? $post->post_content : $this->get_seo_description($post)),
            'datePosted' => get_post_time('c', true, $post),
            'employmentType' => $type,
            'hiringOrganization' => array(
                '@type' => isset($org['@type']) ? $org['@type'] : 'Organization',
                'name' => isset($org['name']) ? $org['name'] : get_bloginfo('name'),
            ),
            'jobLocation' => array(
                '@type' => 'Place',
                'address' => array(
                    '@type' => 'PostalAddress',
                    'addressLocality' => $location,
                ),
            ),
        );
        $through = get_post_meta($post->ID, '_aiseo_job_through', true);
        if (is_string($through) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $through)) {
            $timestamp = strtotime($through);
            if ($timestamp) {
                $schema['validThrough'] = gmdate('c', $timestamp);
            }
        }
        return $schema;
    }

    /**
     * @param string $content HTML.
     * @param string $tag     ul or ol.
     * @return string[]
     */
    private function list_item_texts($content, $tag) {
        $texts = array();
        if (!is_string($content) || $content === '' || !in_array($tag, array('ul', 'ol'), true)) {
            return $texts;
        }
        if (!preg_match_all('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', $content, $lists)) {
            return $texts;
        }
        foreach ($lists[1] as $list) {
            if (!preg_match_all('/<li\b[^>]*>(.*?)<\/li>/is', $list, $items)) {
                continue;
            }
            $batch = array();
            foreach ($items[1] as $item) {
                $text = trim(wp_strip_all_tags($item));
                if ($text !== '') {
                    $batch[] = $text;
                }
            }
            if (count($batch) >= 2) {
                return $batch;
            }
        }
        return $texts;
    }
    
    /**
     * Get SEO title for post
     *
     * @param WP_Post $post Post object
     * @return string SEO title
     */
    private function get_seo_title($post) {
        // Check for custom SEO title
        $seo_title = get_post_meta($post->ID, '_aiseo_meta_title', true);
        
        if (!empty($seo_title)) {
            return $seo_title;
        }
        
        // Fallback to post title
        return $post->post_title;
    }
    
    /**
     * Get SEO description for post
     *
     * @param WP_Post $post Post object
     * @return string SEO description
     */
    private function get_seo_description($post) {
        // Check for custom SEO description
        $seo_description = get_post_meta($post->ID, '_aiseo_meta_description', true);
        
        if (!empty($seo_description)) {
            return $seo_description;
        }
        
        // Fallback to excerpt or content
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }
        
        return AISEO_Helpers::truncate_text($post->post_content, 160);
    }
    
    /**
     * Get post featured image
     *
     * @param int $post_id Post ID
     * @return array|false Image data or false
     */
    private function get_post_image($post_id) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        if ($thumbnail_id) {
            $image = $this->image_object_from_attachment($thumbnail_id);
            if ($image) {
                return $image;
            }
        }

        $post = get_post($post_id);
        if (!$post || !preg_match('/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $post->post_content, $matches)) {
            return false;
        }
        $url = $matches[1];
        if (stripos($url, 'data:') === 0 || stripos($url, 'javascript:') === 0) {
            return false;
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } elseif (isset($url[0]) && $url[0] === '/') {
            $url = home_url($url);
        }
        $url = esc_url_raw($url);
        if (!is_string($url) || !wp_http_validate_url($url)) {
            return false;
        }
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            $image = $this->image_object_from_attachment($attachment_id);
            if ($image) {
                return $image;
            }
        }
        return array(
            '@type' => 'ImageObject',
            'url' => $url,
        );
    }

    /**
     * @param int $attachment_id Attachment ID.
     * @return array|false
     */
    private function image_object_from_attachment($attachment_id) {
        $image = wp_get_attachment_image_src($attachment_id, 'full');
        if (!$image) {
            return false;
        }
        return array(
            '@type' => 'ImageObject',
            'url' => $image[0],
            'width' => $image[1],
            'height' => $image[2],
        );
    }
    
    /**
     * Output schema markup as JSON-LD
     *
     * @param array $schema Schema data
     * @return string JSON-LD script tag
     */
    public function output_schema($schema) {
        if (empty($schema)) {
            return '';
        }
        
        $output = '<script type="application/ld+json">' . "\n";
        $output .= wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $output .= "\n" . '</script>' . "\n";
        
        return $output;
    }
    
    /**
     * Get schema markup for post (for display/testing)
     *
     * @param int $post_id Post ID
     * @param string $schema_type Schema type
     * @return string JSON string
     */
    public function get_schema_json($post_id, $schema_type = 'auto') {
        $schema = $this->generate_schema($post_id, $schema_type);
        
        if (!$schema) {
            return '';
        }
        
        return wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
