<?php
/**
 * AISEO Taxonomy SEO Settings
 *
 * Handles SEO settings for categories, tags, and custom taxonomies
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Taxonomy_SEO {
    
    /**
     * Option key for taxonomy meta
     */
    const OPTION_KEY = 'aiseo_taxonomy_meta';
    
    /**
     * Meta fields for taxonomies
     */
    private $meta_fields = array(
        'title',
        'description',
        'keywords',
        'canonical',
        'noindex',
        'nofollow',
    );
    
    /**
     * Initialize taxonomy SEO
     */
    public function init() {
        // Add SEO fields to taxonomy edit screens
        $this->register_taxonomy_fields();
        
        // Hook into wp_head for taxonomy meta tags
        add_action('wp_head', array($this, 'output_taxonomy_meta'), 1);
        add_filter('wp_robots', array($this, 'filter_term_robots'), 30);
        
        // Filter document title for taxonomies
        add_filter('pre_get_document_title', array($this, 'filter_taxonomy_title'), 5);
        add_filter('document_title_parts', array($this, 'filter_title_parts'), 10);
    }
    
    /**
     * Register taxonomy fields for all public taxonomies
     */
    private function register_taxonomy_fields() {
        $taxonomies = get_taxonomies(array('public' => true), 'names');
        
        foreach ($taxonomies as $taxonomy) {
            // Add fields to edit form
            add_action("{$taxonomy}_edit_form_fields", array($this, 'render_edit_fields'), 10, 2);
            
            // Save fields
            add_action("edited_{$taxonomy}", array($this, 'save_term_meta'), 10, 2);
            add_action("created_{$taxonomy}", array($this, 'save_term_meta'), 10, 2);
        }

        add_action('admin_enqueue_scripts', array($this, 'enqueue_term_assets'));
        add_action('wp_ajax_aiseo_generate_term_seo', array($this, 'ajax_generate_term_seo'));
    }

    /**
     * Generate buttons on term edit screens only.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_term_assets($hook) {
        if ($hook !== 'term.php') {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || empty($screen->taxonomy)) {
            return;
        }
        wp_enqueue_script(
            'aiseo-term-seo',
            AISEO_PLUGIN_URL . 'admin/js/aiseo-term-seo.js',
            array('jquery'),
            AISEO_VERSION,
            true
        );
        wp_localize_script('aiseo-term-seo', 'aiseoTermSeo', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiseo_admin_nonce'),
            'taxonomy' => $screen->taxonomy,
            'i18n' => array(
                'generating' => __('Generating...', 'aiseo'),
                'failed' => __('Failed to generate', 'aiseo'),
                'connectionError' => __('Connection error', 'aiseo'),
                'generateTitle' => __('Generate title', 'aiseo'),
                'generateDescription' => __('Generate description', 'aiseo'),
            ),
        ));
    }

    /**
     * AI title or description for a category or tag, like Yoast Premium.
     */
    public function ajax_generate_term_seo() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
        $field = isset($_POST['field']) ? sanitize_key(wp_unslash($_POST['field'])) : '';
        if (!$term_id || !$taxonomy || !in_array($field, array('title', 'description'), true)) {
            wp_send_json_error(__('Invalid request.', 'aiseo'));
        }
        if (!current_user_can('edit_term', $term_id)) {
            wp_send_json_error(__('Permission denied', 'aiseo'));
        }
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(__('Term not found.', 'aiseo'));
        }
        $content = $term->name;
        if (is_string($term->description) && $term->description !== '') {
            $content .= "\n\n" . wp_strip_all_tags($term->description);
        }
        $keyword = '';
        $meta = $this->get_term_meta($term_id, $taxonomy);
        if (!empty($meta['keywords']) && is_string($meta['keywords'])) {
            $keyword = $meta['keywords'];
        }
        $api = new AISEO_API();
        $result = $field === 'title'
            ? $api->generate_title($content, $keyword)
            : $api->generate_meta_description($content, $keyword);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        wp_send_json_success($result);
    }
    
    /**
     * Render SEO fields on taxonomy edit screen
     *
     * @param WP_Term $term Term object
     * @param string $taxonomy Taxonomy name
     */
    public function render_edit_fields($term, $taxonomy) {
        $meta = $this->get_term_meta($term->term_id, $taxonomy);
        
        wp_nonce_field('aiseo_taxonomy_meta', 'aiseo_taxonomy_meta_nonce');
        ?>
        <tr class="form-field">
            <th colspan="2">
                <h2 style="margin: 20px 0 10px; padding: 0; font-size: 1.3em;">
                    <?php esc_html_e('AISEO SEO Settings', 'aiseo'); ?>
                </h2>
            </th>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="aiseo_title"><?php esc_html_e('SEO Title', 'aiseo'); ?></label>
            </th>
            <td>
                <input type="text" 
                       name="aiseo_title" 
                       id="aiseo_title" 
                       value="<?php echo esc_attr($meta['title']); ?>" 
                       class="large-text" />
                <p>
                    <button type="button" class="button aiseo-generate-term-seo" data-field="title" data-term-id="<?php echo esc_attr((string) $term->term_id); ?>">
                        <?php esc_html_e('Generate title', 'aiseo'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php esc_html_e('Custom SEO title for this term archive page. Leave empty to use default.', 'aiseo'); ?>
                </p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="aiseo_description"><?php esc_html_e('SEO Description', 'aiseo'); ?></label>
            </th>
            <td>
                <textarea name="aiseo_description" 
                          id="aiseo_description" 
                          rows="3" 
                          class="large-text"><?php echo esc_textarea($meta['description']); ?></textarea>
                <p>
                    <button type="button" class="button aiseo-generate-term-seo" data-field="description" data-term-id="<?php echo esc_attr((string) $term->term_id); ?>">
                        <?php esc_html_e('Generate description', 'aiseo'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php esc_html_e('Leave empty to use the category or tag description format in Settings. Recommended: 155–160 characters.', 'aiseo'); ?>
                </p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="aiseo_keywords"><?php esc_html_e('Meta Keywords', 'aiseo'); ?></label>
            </th>
            <td>
                <input type="text" 
                       name="aiseo_keywords" 
                       id="aiseo_keywords" 
                       value="<?php echo esc_attr($meta['keywords']); ?>" 
                       class="large-text" />
                <p class="description">
                    <?php esc_html_e('Comma-separated keywords for this term.', 'aiseo'); ?>
                </p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row">
                <label for="aiseo_canonical"><?php esc_html_e('Canonical URL', 'aiseo'); ?></label>
            </th>
            <td>
                <input type="url" 
                       name="aiseo_canonical" 
                       id="aiseo_canonical" 
                       value="<?php echo esc_url($meta['canonical']); ?>" 
                       class="large-text" />
                <p class="description">
                    <?php esc_html_e('Custom canonical URL. Leave empty to use default term URL.', 'aiseo'); ?>
                </p>
            </td>
        </tr>
        
        <tr class="form-field">
            <th scope="row"><?php esc_html_e('Robots Settings', 'aiseo'); ?></th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="aiseo_noindex" 
                           value="1" 
                           <?php checked($meta['noindex'], true); ?> />
                    <?php esc_html_e('NoIndex this term (prevent search engines from indexing)', 'aiseo'); ?>
                </label>
                <br />
                <label>
                    <input type="checkbox" 
                           name="aiseo_nofollow" 
                           value="1" 
                           <?php checked($meta['nofollow'], true); ?> />
                    <?php esc_html_e('NoFollow this term (prevent search engines from following links)', 'aiseo'); ?>
                </label>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Save term meta
     *
     * @param int $term_id Term ID
     * @param int $tt_id Term taxonomy ID
     */
    public function save_term_meta($term_id, $tt_id) {
        // Verify nonce
        if (!isset($_POST['aiseo_taxonomy_meta_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiseo_taxonomy_meta_nonce'])), 'aiseo_taxonomy_meta')) {
            return;
        }
        
        if (!current_user_can('edit_term', $term_id)) {
            return;
        }
        
        // Get taxonomy
        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return;
        }
        
        $taxonomy = $term->taxonomy;
        
        // Get all taxonomy meta
        $all_meta = get_option(self::OPTION_KEY, array());
        
        // Initialize taxonomy array if needed
        if (!isset($all_meta[$taxonomy])) {
            $all_meta[$taxonomy] = array();
        }
        
        // Save meta fields
        $meta = array();
        
        if (isset($_POST['aiseo_title'])) {
            $meta['title'] = sanitize_text_field(wp_unslash($_POST['aiseo_title']));
        }
        
        if (isset($_POST['aiseo_description'])) {
            $meta['description'] = sanitize_textarea_field(wp_unslash($_POST['aiseo_description']));
        }
        
        if (isset($_POST['aiseo_keywords'])) {
            $meta['keywords'] = sanitize_text_field(wp_unslash($_POST['aiseo_keywords']));
        }
        
        if (isset($_POST['aiseo_canonical'])) {
            $meta['canonical'] = esc_url_raw(wp_unslash($_POST['aiseo_canonical']));
        }
        
        $meta['noindex'] = isset($_POST['aiseo_noindex']) ? true : false;
        $meta['nofollow'] = isset($_POST['aiseo_nofollow']) ? true : false;
        
        // Store meta
        $all_meta[$taxonomy][$term_id] = $meta;
        
        update_option(self::OPTION_KEY, $all_meta);
    }
    
    /**
     * Get term meta
     *
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name
     * @return array Meta values
     */
    public function get_term_meta($term_id, $taxonomy) {
        $all_meta = get_option(self::OPTION_KEY, array());
        
        $defaults = array(
            'title' => '',
            'description' => '',
            'keywords' => '',
            'canonical' => '',
            'noindex' => false,
            'nofollow' => false,
        );
        
        if (isset($all_meta[$taxonomy][$term_id])) {
            return wp_parse_args($all_meta[$taxonomy][$term_id], $defaults);
        }
        
        return $defaults;
    }
    
    /**
     * Update term meta via API
     *
     * @param int $term_id Term ID
     * @param string $taxonomy Taxonomy name
     * @param array $meta Meta values to update
     * @return bool Success
     */
    public function update_term_meta($term_id, $taxonomy, $meta) {
        $all_meta = get_option(self::OPTION_KEY, array());
        
        if (!isset($all_meta[$taxonomy])) {
            $all_meta[$taxonomy] = array();
        }
        
        // Get existing meta
        $existing = isset($all_meta[$taxonomy][$term_id]) ? $all_meta[$taxonomy][$term_id] : array();
        
        // Merge with new values
        $updated = wp_parse_args($meta, $existing);
        
        // Sanitize
        if (isset($updated['title'])) {
            $updated['title'] = sanitize_text_field($updated['title']);
        }
        if (isset($updated['description'])) {
            $updated['description'] = sanitize_textarea_field($updated['description']);
        }
        if (isset($updated['keywords'])) {
            $updated['keywords'] = sanitize_text_field($updated['keywords']);
        }
        if (isset($updated['canonical'])) {
            $updated['canonical'] = esc_url_raw($updated['canonical']);
        }
        if (isset($updated['noindex'])) {
            $updated['noindex'] = (bool) $updated['noindex'];
        }
        if (isset($updated['nofollow'])) {
            $updated['nofollow'] = (bool) $updated['nofollow'];
        }
        
        $all_meta[$taxonomy][$term_id] = $updated;
        
        return update_option(self::OPTION_KEY, $all_meta);
    }
    
    /**
     * Output taxonomy meta tags
     */
    public function output_taxonomy_meta() {
        if (!is_category() && !is_tag() && !is_tax()) {
            return;
        }
        
        $term = get_queried_object();
        
        if (!$term || !isset($term->term_id)) {
            return;
        }
        
        $meta = $this->get_term_meta($term->term_id, $term->taxonomy);
        
        // Meta description
        $description = !empty($meta['description']) ? $meta['description'] : '';
        if ($description === '' && class_exists('AISEO_Title_Templates')) {
            $description = (new AISEO_Title_Templates())->format_term_description($term);
        }
        if ($description === '') {
            $description = $term->description;
        }
        if (!empty($description)) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }
        
        // Meta keywords
        if (!empty($meta['keywords'])) {
            echo '<meta name="keywords" content="' . esc_attr($meta['keywords']) . '">' . "\n";
        }
        
        // Canonical URL
        $canonical = !empty($meta['canonical']) ? esc_url($meta['canonical']) : '';
        if ($canonical === '') {
            $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
            if ($paged > 1) {
                $canonical = esc_url(get_pagenum_link($paged));
            } else {
                $term_link = get_term_link($term);
                $canonical = is_wp_error($term_link) ? '' : esc_url($term_link);
            }
        }
        if ($canonical !== '') {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        }
        
    }

    /**
     * Apply a term's own robots setting to the single robots tag.
     *
     * @param array $robots Robots directives.
     * @return array
     */
    public function filter_term_robots($robots) {
        if (!is_category() && !is_tag() && !is_tax()) {
            return $robots;
        }
        $term = get_queried_object();
        if (!$term || !isset($term->term_id)) {
            return $robots;
        }
        $meta = $this->get_term_meta($term->term_id, $term->taxonomy);
        if (!empty($meta['noindex'])) {
            $robots['noindex'] = true;
            unset($robots['index']);
        }
        if (!empty($meta['nofollow'])) {
            $robots['nofollow'] = true;
            unset($robots['follow']);
        }
        return $robots;
    }
    
    /**
     * Filter taxonomy title
     *
     * @param string $title Current title
     * @return string Modified title
     */
    public function filter_taxonomy_title($title) {
        if (!is_category() && !is_tag() && !is_tax()) {
            return $title;
        }
        
        $term = get_queried_object();
        
        if (!$term || !isset($term->term_id)) {
            return $title;
        }
        
        $meta = $this->get_term_meta($term->term_id, $term->taxonomy);
        
        if (!empty($meta['title'])) {
            return $meta['title'];
        }
        
        return $title;
    }
    
    /**
     * Filter title parts
     *
     * @param array $title_parts Title parts
     * @return array Modified title parts
     */
    public function filter_title_parts($title_parts) {
        if (!is_category() && !is_tag() && !is_tax()) {
            return $title_parts;
        }
        
        $term = get_queried_object();
        
        if (!$term || !isset($term->term_id)) {
            return $title_parts;
        }
        
        $meta = $this->get_term_meta($term->term_id, $term->taxonomy);
        
        if (!empty($meta['title'])) {
            // Replace entire title
            return array('title' => $meta['title']);
        }
        
        return $title_parts;
    }
    
    /**
     * Get all terms with SEO meta for a taxonomy
     *
     * @param string $taxonomy Taxonomy name
     * @return array Terms with meta
     */
    public function get_taxonomy_terms_with_meta($taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));
        
        if (is_wp_error($terms)) {
            return array();
        }
        
        $result = array();
        
        foreach ($terms as $term) {
            $meta = $this->get_term_meta($term->term_id, $taxonomy);
            $result[] = array(
                'term_id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
                'seo' => $meta,
            );
        }
        
        return $result;
    }
}
