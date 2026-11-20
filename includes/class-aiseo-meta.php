<?php
/**
 * AISEO Meta Tags Handler
 *
 * Injects SEO meta tags into page <head>
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Meta {
    
    /**
     * Initialize meta tags handler
     */
    public function init() {
        // Hook into wp_head to inject meta tags
        add_action('wp_head', array($this, 'inject_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_author_description'), 2);
        add_action('wp_head', array($this, 'output_post_type_description'), 2);
        add_action('wp_head', array($this, 'output_archive_canonical'), 2);
        add_filter('wp_robots', array($this, 'filter_post_robots'), 20);
        add_action('show_user_profile', array($this, 'render_author_seo_fields'));
        add_action('edit_user_profile', array($this, 'render_author_seo_fields'));
        add_action('personal_options_update', array($this, 'save_author_seo_fields'));
        add_action('edit_user_profile_update', array($this, 'save_author_seo_fields'));
        add_action('init', array($this, 'register_editor_meta'), 20);
        
        // Filter document title
        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 10, 1);
        add_filter('wp_title', array($this, 'filter_wp_title'), 10, 3);
        
        // Replace the canonical and the default robots tag. Leave the theme title tag in place.
        remove_action('wp_head', 'rel_canonical');
        remove_action('wp_head', 'noindex', 1);
    }

    /**
     * Expose SEO fields to the block editor REST API.
     */
    public function register_editor_meta() {
        $types = get_post_types(array('show_in_rest' => true), 'names');
        unset($types['attachment']);
        $fields = array(
            '_aiseo_meta_title' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_meta_description' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            '_aiseo_focus_keyword' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_additional_keywords' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            '_aiseo_noindex' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_nofollow' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_noarchive' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_nosnippet' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_exclude_sitemap' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_cornerstone' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_canonical_url' => array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ),
            '_aiseo_breadcrumb_title' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_og_title' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_og_description' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            '_aiseo_og_image' => array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ),
            '_aiseo_twitter_card_type' => array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_twitter_card_meta'),
            ),
            '_aiseo_schema_type' => array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_schema_type_meta'),
            ),
            '_aiseo_event_start' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_event_location' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_job_location' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_job_type' => array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_job_type_meta'),
            ),
            '_aiseo_job_through' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            '_aiseo_redirect_to' => array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
            ),
            '_aiseo_redirect_type' => array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_redirect_type_meta'),
            ),
        );
        foreach ($types as $type) {
            foreach ($fields as $key => $field) {
                register_post_meta($type, $key, array(
                    'show_in_rest' => true,
                    'single' => true,
                    'type' => $field['type'],
                    'auth_callback' => array($this, 'editor_meta_auth'),
                    'sanitize_callback' => $field['sanitize_callback'],
                ));
            }
        }
    }

    /**
     * @param bool   $allowed   Whether the user can edit the meta.
     * @param string $meta_key  Meta key.
     * @param int    $object_id Post ID.
     * @return bool
     */
    public function editor_meta_auth($allowed, $meta_key, $object_id) {
        unset($allowed);
        if (in_array($meta_key, array('_aiseo_redirect_to', '_aiseo_redirect_type'), true)) {
            return current_user_can('aiseo_manage_redirects') && current_user_can('edit_post', $object_id);
        }
        return current_user_can('edit_post', $object_id);
    }

    /**
     * @param mixed $value Raw meta.
     * @return string
     */
    public function sanitize_twitter_card_meta($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, array('summary', 'summary_large_image'), true) ? $value : '';
    }

    /**
     * @param mixed $value Raw meta.
     * @return string
     */
    public function sanitize_schema_type_meta($value) {
        $value = sanitize_key((string) $value);
        $allowed = array('auto', 'article', 'blogposting', 'webpage', 'newsarticle', 'product', 'howto', 'recipe', 'event', 'course', 'jobposting');
        return in_array($value, $allowed, true) ? $value : 'auto';
    }

    /**
     * @param mixed $value Raw meta.
     * @return string
     */
    public function sanitize_job_type_meta($value) {
        $value = strtoupper(str_replace('-', '_', sanitize_key((string) $value)));
        $allowed = array('FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'OTHER');
        return in_array($value, $allowed, true) ? $value : 'FULL_TIME';
    }

    /**
     * @param mixed $value Raw meta.
     * @return string
     */
    public function sanitize_redirect_type_meta($value) {
        $value = sanitize_text_field((string) $value);
        return in_array($value, array('301', '302', '307', '308', '410'), true) ? $value : '';
    }
    
    /**
     * Inject all meta tags into head
     */
    public function inject_meta_tags() {
        if (!is_singular()) {
            return;
        }
        
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Output meta description
        $this->output_meta_description($post);
        
        // Output canonical URL
        $this->output_canonical($post);
        
        // Output additional meta tags
        $this->output_additional_meta($post);
    }
    
    /**
     * Output title tag
     *
     * @param WP_Post $post Post object
     */
    private function output_title_tag($post) {
        $title = $this->get_meta_title($post);
        
        if (empty($title)) {
            // Fallback to default WordPress title
            return;
        }
        
        echo '<title>' . esc_html($title) . '</title>' . "\n";
    }
    
    /**
     * Output meta description
     *
     * @param WP_Post $post Post object
     */
    private function output_meta_description($post) {
        $description = $this->get_meta_description($post);
        
        if (empty($description)) {
            return;
        }
        
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    /**
     * Use the author biography as the archive description.
     */
    public function output_author_description() {
        if (!is_author()) {
            return;
        }
        $user = get_queried_object();
        if (!$user instanceof WP_User) {
            return;
        }
        $description = get_user_meta($user->ID, '_aiseo_description', true);
        if (!is_string($description) || trim($description) === '') {
            $description = get_user_meta($user->ID, 'description', true);
        }
        if (!is_string($description) || trim($description) === '') {
            return;
        }
        $description = AISEO_Helpers::truncate_text(wp_strip_all_tags($description), 160);
        if ($description === '') {
            return;
        }
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    /**
     * Use the post type description on its archive.
     */
    public function output_post_type_description() {
        if (!is_post_type_archive()) {
            return;
        }
        $post_type = get_queried_object();
        if (!$post_type instanceof WP_Post_Type || !is_string($post_type->description)) {
            return;
        }
        $description = AISEO_Helpers::truncate_text(wp_strip_all_tags($post_type->description), 160);
        if ($description === '') {
            return;
        }
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    }

    /**
     * Canonical for the blog home, author archives, date archives, and post type archives.
     * Singular posts and taxonomy archives print their own.
     */
    public function output_archive_canonical() {
        if (is_singular() || is_category() || is_tag() || is_tax() || is_search() || is_404()) {
            return;
        }
        if (!is_author() && !is_date() && !is_home() && !is_post_type_archive()) {
            return;
        }

        $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
        $url = '';
        if (is_author()) {
            $user = get_queried_object();
            if (!$user instanceof WP_User) {
                return;
            }
            $url = $paged > 1 ? get_pagenum_link($paged) : get_author_posts_url($user->ID);
        } elseif (is_home()) {
            $posts_page = (int) get_option('page_for_posts');
            $url = $paged > 1 ? get_pagenum_link($paged) : ($posts_page ? get_permalink($posts_page) : home_url('/'));
        } else {
            $url = get_pagenum_link($paged);
        }

        $url = esc_url($url);
        if ($url === '') {
            return;
        }
        echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
    }
    
    /**
     * Output canonical URL
     *
     * @param WP_Post $post Post object
     */
    private function output_canonical($post) {
        $canonical = self::canonical_url($post);
        if ($canonical === '') {
            return;
        }
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }

    /**
     * Canonical for this view of a post. Later pages of a split post point at themselves.
     * A saved canonical URL replaces that.
     *
     * @param WP_Post $post Post object.
     * @return string
     */
    public static function canonical_url($post) {
        if (!$post instanceof WP_Post) {
            return '';
        }
        $custom = get_post_meta($post->ID, '_aiseo_canonical_url', true);
        if (is_string($custom) && $custom !== '') {
            return esc_url($custom);
        }
        $page = (int) get_query_var('page');
        if ($page > 1 && is_singular()) {
            $permalink = get_permalink($post->ID);
            if (get_option('permalink_structure') === '') {
                return esc_url(add_query_arg('page', $page, $permalink));
            }
            global $wp_rewrite;
            if ('page' === get_option('show_on_front') && (int) get_option('page_on_front') === (int) $post->ID) {
                $suffix = $wp_rewrite->pagination_base . '/' . $page;
            } else {
                $suffix = (string) $page;
            }
            return esc_url(trailingslashit($permalink) . user_trailingslashit($suffix, 'single_paged'));
        }
        return esc_url(get_permalink($post->ID));
    }
    
    /**
     * Output robots meta tag
     *
     * @param WP_Post $post Post object
     */
    public function filter_post_robots($robots) {
        if (!is_singular() || is_attachment()) {
            return $robots;
        }
        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return $robots;
        }
        if ($post->post_password !== '') {
            $robots['noindex'] = true;
        }
        if (self::post_is_noindex($post->ID)) {
            $robots['noindex'] = true;
        }
        if (self::post_is_nofollow($post->ID)) {
            $robots['nofollow'] = true;
        }
        if ($this->robot_meta_on($post->ID, '_aiseo_noarchive')) {
            $robots['noarchive'] = true;
        }
        if ($this->robot_meta_on($post->ID, '_aiseo_nosnippet')) {
            $robots['nosnippet'] = true;
        }
        return $robots;
    }

    /**
     * @param int    $post_id Post ID.
     * @param string $key     Meta key.
     * @return bool
     */
    private function robot_meta_on($post_id, $key) {
        return in_array(get_post_meta($post_id, $key, true), array(true, 1, '1'), true);
    }

    /**
     * Whether a post should be noindexed, including legacy import keys.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function post_is_noindex($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }
        if (in_array(get_post_meta($post_id, '_aiseo_noindex', true), array(true, 1, '1'), true)) {
            return true;
        }
        if (get_post_meta($post_id, '_aiseo_robots_index', true) === 'noindex') {
            return true;
        }
        return self::product_is_out_of_stock_noindex($post_id);
    }

    /**
     * Optional WooCommerce setting: noindex products that are out of stock.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    private static function product_is_out_of_stock_noindex($post_id) {
        if (!function_exists('wc_get_product')) {
            return false;
        }
        $robots = get_option('aiseo_robots_settings', array());
        if (!is_array($robots) || empty($robots['noindex_out_of_stock'])) {
            return false;
        }
        $product = wc_get_product($post_id);
        if (!$product) {
            return false;
        }
        return !$product->is_in_stock();
    }

    /**
     * Whether a post should be nofollowed, including legacy import keys.
     *
     * @param int $post_id Post ID.
     * @return bool
     */
    public static function post_is_nofollow($post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            return false;
        }
        if (in_array(get_post_meta($post_id, '_aiseo_nofollow', true), array(true, 1, '1'), true)) {
            return true;
        }
        return get_post_meta($post_id, '_aiseo_robots_follow', true) === 'nofollow';
    }
    
    /**
     * Output additional meta tags
     *
     * @param WP_Post $post Post object
     */
    private function output_additional_meta($post) {
        // Author meta tag
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        
        if ($author) {
            echo '<meta name="author" content="' . esc_attr($author->display_name) . '">' . "\n";
        }
        
        // Published time
        echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post->ID)) . '">' . "\n";
        
        // Modified time
        echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post->ID)) . '">' . "\n";
        
        // Keywords (if set)
        $keywords = get_post_meta($post->ID, '_aiseo_meta_keywords', true);
        if (!empty($keywords)) {
            echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
        }
        
        // Focus keyword as primary keyword
        $focus_keyword = get_post_meta($post->ID, '_aiseo_focus_keyword', true);
        if (!empty($focus_keyword)) {
            echo '<meta name="news_keywords" content="' . esc_attr($focus_keyword) . '">' . "\n";
        }
    }
    
    /**
     * Filter document title
     *
     * @param string $title Document title
     * @return string Filtered title
     */
    public function filter_document_title($title) {
        if (!is_singular()) {
            return $title;
        }

        $post = get_queried_object();
        if (!$post instanceof WP_Post) {
            return $title;
        }

        $custom = get_post_meta($post->ID, '_aiseo_meta_title', true);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $title;
    }
    
    /**
     * Filter wp_title
     *
     * @param string $title Title
     * @param string $sep Separator
     * @param string $seplocation Separator location
     * @return string Filtered title
     */
    public function filter_wp_title($title, $sep, $seplocation) {
        if (!is_singular()) {
            return $title;
        }
        
        global $post;
        
        if (!$post) {
            return $title;
        }
        
        $custom = get_post_meta($post->ID, '_aiseo_meta_title', true);
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $title;
    }
    
    /**
     * Get meta title for post
     *
     * @param WP_Post $post Post object
     * @return string Meta title
     */
    private function get_meta_title($post) {
        // Check for custom meta title
        $meta_title = get_post_meta($post->ID, '_aiseo_meta_title', true);
        
        if (!empty($meta_title)) {
            return $meta_title;
        }

        if (class_exists('AISEO_Title_Templates')) {
            $formatted = (new AISEO_Title_Templates())->format_post_title($post);
            if ($formatted !== '') {
                return $formatted;
            }
        }
        
        $site_name = get_bloginfo('name');
        return $post->post_title . ' - ' . $site_name;
    }
    
    /**
     * Get meta description for post
     *
     * @param WP_Post $post Post object
     * @return string Meta description
     */
    private function get_meta_description($post) {
        // Check for custom meta description
        $meta_description = get_post_meta($post->ID, '_aiseo_meta_description', true);
        
        if (!empty($meta_description)) {
            return $meta_description;
        }

        if (class_exists('AISEO_Title_Templates')) {
            $formatted = (new AISEO_Title_Templates())->format_post_description($post);
            if ($formatted !== '') {
                return $formatted;
            }
        }
        
        // Fallback to excerpt
        if (!empty($post->post_excerpt)) {
            return AISEO_Helpers::truncate_text($post->post_excerpt, 160);
        }
        
        // Fallback to content
        return AISEO_Helpers::truncate_text($post->post_content, 160);
    }
    
    /**
     * Get all meta tags for a post (for testing/display)
     *
     * @param int $post_id Post ID
     * @return array Meta tags
     */
    public function get_meta_tags($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return array();
        }
        
        $meta_tags = array(
            'title' => $this->get_meta_title($post),
            'description' => $this->get_meta_description($post),
            'canonical' => get_post_meta($post_id, '_aiseo_canonical_url', true) ?: get_permalink($post_id),
            'robots' => $this->get_robots_directives($post_id),
            'keywords' => get_post_meta($post_id, '_aiseo_meta_keywords', true),
            'focus_keyword' => get_post_meta($post_id, '_aiseo_focus_keyword', true),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'published_time' => get_the_date('c', $post_id),
            'modified_time' => get_the_modified_date('c', $post_id),
        );
        
        return array_filter($meta_tags); // Remove empty values
    }
    
    /**
     * Get robots directives for a post
     *
     * @param int $post_id Post ID
     * @return string Robots directives
     */
    private function get_robots_directives($post_id) {
        $robots = array();
        
        $noindex = get_post_meta($post_id, '_aiseo_noindex', true);
        $nofollow = get_post_meta($post_id, '_aiseo_nofollow', true);
        $noarchive = get_post_meta($post_id, '_aiseo_noarchive', true);
        $nosnippet = get_post_meta($post_id, '_aiseo_nosnippet', true);
        
        $robots[] = $noindex ? 'noindex' : 'index';
        $robots[] = $nofollow ? 'nofollow' : 'follow';
        
        if ($noarchive) {
            $robots[] = 'noarchive';
        }
        
        if ($nosnippet) {
            $robots[] = 'nosnippet';
        }
        
        return implode(', ', $robots);
    }
    
    /**
     * Update meta tags for a post
     *
     * @param int $post_id Post ID
     * @param array $meta_data Meta data to update
     * @return bool Success
     */
    public function update_meta_tags($post_id, $meta_data) {
        $updated = false;
        
        // Update meta title
        if (isset($meta_data['title'])) {
            update_post_meta($post_id, '_aiseo_meta_title', sanitize_text_field($meta_data['title']));
            $updated = true;
        }
        
        // Update meta description
        if (isset($meta_data['description'])) {
            update_post_meta($post_id, '_aiseo_meta_description', sanitize_textarea_field($meta_data['description']));
            $updated = true;
        }
        
        // Update canonical URL
        if (isset($meta_data['canonical'])) {
            update_post_meta($post_id, '_aiseo_canonical_url', esc_url_raw($meta_data['canonical']));
            $updated = true;
        }
        
        // Update robots directives
        if (isset($meta_data['noindex'])) {
            update_post_meta($post_id, '_aiseo_noindex', (bool) $meta_data['noindex']);
            $updated = true;
        }
        
        if (isset($meta_data['nofollow'])) {
            update_post_meta($post_id, '_aiseo_nofollow', (bool) $meta_data['nofollow']);
            $updated = true;
        }
        
        if (isset($meta_data['noarchive'])) {
            update_post_meta($post_id, '_aiseo_noarchive', (bool) $meta_data['noarchive']);
            $updated = true;
        }
        
        if (isset($meta_data['nosnippet'])) {
            update_post_meta($post_id, '_aiseo_nosnippet', (bool) $meta_data['nosnippet']);
            $updated = true;
        }
        
        // Update keywords
        if (isset($meta_data['keywords'])) {
            update_post_meta($post_id, '_aiseo_meta_keywords', sanitize_text_field($meta_data['keywords']));
            $updated = true;
        }
        
        return $updated;
    }

    /**
     * SEO fields on the user profile, matching Yoast author archives.
     *
     * @param WP_User $user User.
     */
    public function render_author_seo_fields($user) {
        if (!$user instanceof WP_User || !current_user_can('edit_user', $user->ID)) {
            return;
        }
        $title = get_user_meta($user->ID, '_aiseo_title', true);
        $description = get_user_meta($user->ID, '_aiseo_description', true);
        $noindex = get_user_meta($user->ID, '_aiseo_noindex', true);
        wp_nonce_field('aiseo_author_seo', 'aiseo_author_seo_nonce');
        ?>
        <h2><?php esc_html_e('AISEO author archive', 'aiseo'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="aiseo_author_title"><?php esc_html_e('SEO title', 'aiseo'); ?></label></th>
                <td>
                    <input type="text" class="regular-text" name="aiseo_author_title" id="aiseo_author_title" value="<?php echo esc_attr(is_string($title) ? $title : ''); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aiseo_author_description"><?php esc_html_e('Meta description', 'aiseo'); ?></label></th>
                <td>
                    <textarea class="large-text" rows="3" name="aiseo_author_description" id="aiseo_author_description"><?php echo esc_textarea(is_string($description) ? $description : ''); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Search engines', 'aiseo'); ?></th>
                <td>
                    <label for="aiseo_author_noindex">
                        <input type="checkbox" name="aiseo_author_noindex" id="aiseo_author_noindex" value="1" <?php checked(in_array($noindex, array(true, 1, '1'), true)); ?> />
                        <?php esc_html_e('Noindex this author archive', 'aiseo'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * @param int $user_id User ID.
     */
    public function save_author_seo_fields($user_id) {
        $user_id = absint($user_id);
        if (!$user_id || !current_user_can('edit_user', $user_id)) {
            return;
        }
        if (!isset($_POST['aiseo_author_seo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiseo_author_seo_nonce'])), 'aiseo_author_seo')) {
            return;
        }
        $title = isset($_POST['aiseo_author_title']) ? sanitize_text_field(wp_unslash($_POST['aiseo_author_title'])) : '';
        $description = isset($_POST['aiseo_author_description']) ? sanitize_textarea_field(wp_unslash($_POST['aiseo_author_description'])) : '';
        if ($title === '') {
            delete_user_meta($user_id, '_aiseo_title');
        } else {
            update_user_meta($user_id, '_aiseo_title', $title);
        }
        if ($description === '') {
            delete_user_meta($user_id, '_aiseo_description');
        } else {
            update_user_meta($user_id, '_aiseo_description', $description);
        }
        if (!empty($_POST['aiseo_author_noindex'])) {
            update_user_meta($user_id, '_aiseo_noindex', '1');
        } else {
            delete_user_meta($user_id, '_aiseo_noindex');
        }
    }
}
