<?php
/**
 * AISEO Post Editor Metabox
 *
 * Adds SEO controls to the post editor
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Metabox {
    
    /**
     * Initialize metabox
     */
    public function init() {
        // Add metabox
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        
        // Save metabox data
        add_action('save_post', array($this, 'save_metabox'), 10, 2);
        
        // Enqueue metabox scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_metabox_scripts'));
        
        // AJAX handlers for AI generation.
        // These use metabox-specific action names: AISEO_Admin registers its own
        // handlers for the unprefixed `aiseo_generate_*` / `aiseo_analyze_content`
        // actions, and loads first, so sharing names meant the admin handlers
        // silently shadowed these ones (and rejected this metabox's nonce).
        add_action('wp_ajax_aiseo_metabox_generate_keyword', array($this, 'ajax_generate_keyword'));
        add_action('wp_ajax_aiseo_metabox_generate_title', array($this, 'ajax_generate_title'));
        add_action('wp_ajax_aiseo_metabox_generate_description', array($this, 'ajax_generate_description'));
        add_action('wp_ajax_aiseo_metabox_analyze_content', array($this, 'ajax_analyze_content'));
    }
    
    /**
     * Add metabox to post editor
     */
    public function add_metabox() {
        $post_types = get_post_types(array('public' => true), 'names');
        unset($post_types['attachment']);
        $enabled = get_option('aiseo_sitemap_post_types', null);
        if (is_array($enabled) && $enabled) {
            $post_types = array_intersect($post_types, $enabled);
        }
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'aiseo_metabox',
                __('AISEO - AI SEO Optimization', 'aiseo'),
                array($this, 'render_metabox'),
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render metabox content
     *
     * @param WP_Post $post Post object
     */
    public function render_metabox($post) {
        // Add nonce for security
        wp_nonce_field('aiseo_metabox_nonce', 'aiseo_metabox_nonce');
        
        // Get current values
        $meta_title = get_post_meta($post->ID, '_aiseo_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_aiseo_meta_description', true);
        $focus_keyword = get_post_meta($post->ID, '_aiseo_focus_keyword', true);
        $canonical_url = get_post_meta($post->ID, '_aiseo_canonical_url', true);
        $noindex = get_post_meta($post->ID, '_aiseo_noindex', true);
        $nofollow = get_post_meta($post->ID, '_aiseo_nofollow', true);
        $noarchive = get_post_meta($post->ID, '_aiseo_noarchive', true);
        $nosnippet = get_post_meta($post->ID, '_aiseo_nosnippet', true);
        
        // Use post title as default if meta title is empty
        if (empty($meta_title)) {
            $meta_title = get_the_title($post->ID);
        }
        
        // Use post excerpt as default if meta description is empty
        if (empty($meta_description)) {
            $meta_description = $post->post_excerpt;
            // If no excerpt, use first 160 characters of content
            if (empty($meta_description)) {
                $content = wp_strip_all_tags($post->post_content);
                $meta_description = wp_trim_words($content, 25, '...');
            }
        }
        
        // Get SEO score if available
        $analysis = new AISEO_Analysis();
        $score_data = $analysis->analyze_post($post->ID, $focus_keyword);
        $seo_score = isset($score_data['overall_score']) ? $score_data['overall_score'] : 0;
        $seo_status = isset($score_data['status']) ? $score_data['status'] : 'poor';
        
        ?>
        <div class="aiseo-metabox">
            <div class="aiseo-metabox-score">
                <div class="aiseo-score-ring-wrap">
                    <div class="aiseo-score-ring">
                        <svg width="70" height="70" viewBox="0 0 70 70" aria-hidden="true">
                            <circle cx="35" cy="35" r="30" fill="none" stroke="#dcdcde" stroke-width="6"/>
                            <circle class="aiseo-score-stroke aiseo-score-stroke-<?php echo esc_attr($seo_status); ?>" cx="35" cy="35" r="30" fill="none" stroke-width="6"
                                    stroke-dasharray="<?php echo esc_attr(($seo_score / 100) * 188); ?> 188" stroke-linecap="round"/>
                        </svg>
                        <div class="aiseo-score-ring-value">
                            <span class="aiseo-score-ring-number"><?php echo esc_html((string) $seo_score); ?></span>
                            <span class="aiseo-score-ring-max">/100</span>
                        </div>
                    </div>
                    <div class="aiseo-score-ring-copy">
                        <strong><?php esc_html_e('SEO score', 'aiseo'); ?></strong>
                        <p><?php echo esc_html(sprintf(/* translators: %s: good, ok, or poor */ __('Status: %s', 'aiseo'), ucfirst($seo_status))); ?></p>
                    </div>
                </div>
            </div>

            <?php $this->render_checklist($score_data, $meta_title, $meta_description, $focus_keyword); ?>

            <div class="aiseo-metabox-tabs" role="tablist">
                <button type="button" class="aiseo-metabox-tab is-active" role="tab" id="aiseo-tab-seo" aria-selected="true" aria-controls="aiseo-panel-seo"><?php esc_html_e('SEO', 'aiseo'); ?></button>
                <button type="button" class="aiseo-metabox-tab" role="tab" id="aiseo-tab-readability" aria-selected="false" aria-controls="aiseo-panel-readability"><?php esc_html_e('Readability', 'aiseo'); ?></button>
                <button type="button" class="aiseo-metabox-tab" role="tab" id="aiseo-tab-social" aria-selected="false" aria-controls="aiseo-panel-social"><?php esc_html_e('Social', 'aiseo'); ?></button>
                <button type="button" class="aiseo-metabox-tab" role="tab" id="aiseo-tab-schema" aria-selected="false" aria-controls="aiseo-panel-schema"><?php esc_html_e('Schema', 'aiseo'); ?></button>
                <button type="button" class="aiseo-metabox-tab" role="tab" id="aiseo-tab-advanced" aria-selected="false" aria-controls="aiseo-panel-advanced"><?php esc_html_e('Advanced', 'aiseo'); ?></button>
            </div>

            <div class="aiseo-metabox-panel is-active" id="aiseo-panel-seo" role="tabpanel" aria-labelledby="aiseo-tab-seo">
                <div class="aiseo-metabox-field">
                    <label for="aiseo_focus_keyword"><?php esc_html_e('Focus keyword', 'aiseo'); ?></label>
                    <div class="aiseo-metabox-field-row">
                        <input type="text" id="aiseo_focus_keyword" name="aiseo_focus_keyword" value="<?php echo esc_attr($focus_keyword); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g. wordpress seo', 'aiseo'); ?>" />
                        <button type="button" class="button button-secondary aiseo-generate-btn" data-field="keyword">
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <?php esc_html_e('Generate with AI', 'aiseo'); ?>
                        </button>
                    </div>
                    <p class="description"><?php esc_html_e('Main keyword to optimise for.', 'aiseo'); ?></p>
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_additional_keywords"><?php esc_html_e('Extra keywords', 'aiseo'); ?></label>
                    <textarea id="aiseo_additional_keywords" name="aiseo_additional_keywords" class="large-text" rows="2"><?php echo esc_textarea(implode(', ', AISEO_Helpers::get_additional_keywords($post->ID))); ?></textarea>
                    <p class="description"><?php esc_html_e('Up to four more phrases, separated by commas. Rank Math allows five keywords in total on the free plan; the first field stays the primary.', 'aiseo'); ?></p>
                </div>
                <?php if (is_object_in_taxonomy($post->post_type, 'category')) : ?>
                    <?php
                    $primary_category = (int) get_post_meta($post->ID, '_aiseo_primary_category', true);
                    $category_choices = get_terms(array(
                        'taxonomy' => 'category',
                        'hide_empty' => false,
                        'number' => 100,
                    ));
                    ?>
                    <div class="aiseo-metabox-field">
                        <label for="aiseo_primary_category"><?php esc_html_e('Primary category', 'aiseo'); ?></label>
                        <select id="aiseo_primary_category" name="aiseo_primary_category">
                            <option value=""><?php esc_html_e('First category', 'aiseo'); ?></option>
                            <?php if (!is_wp_error($category_choices)) : ?>
                                <?php foreach ($category_choices as $choice) : ?>
                                    <option value="<?php echo esc_attr($choice->term_id); ?>" <?php selected($primary_category, (int) $choice->term_id); ?>>
                                        <?php echo esc_html($choice->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Used in breadcrumbs, schema, and the share category. The post must be in this category.', 'aiseo'); ?></p>
                    </div>
                <?php endif; ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_meta_title"><?php esc_html_e('SEO title', 'aiseo'); ?></label>
                    <div class="aiseo-metabox-field-row">
                        <input type="text" id="aiseo_meta_title" name="aiseo_meta_title" value="<?php echo esc_attr($meta_title); ?>" class="aiseo-title-input" placeholder="<?php esc_attr_e('Auto-generated if empty', 'aiseo'); ?>" maxlength="70" />
                        <button type="button" class="button button-secondary aiseo-generate-btn" data-field="title">
                            <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                            <?php esc_html_e('Generate with AI', 'aiseo'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <span class="aiseo-char-count"><span class="aiseo-current-count">0</span> / 60</span>
                        <span class="aiseo-metabox-hint"><?php esc_html_e('Recommended: 50–60 characters', 'aiseo'); ?></span>
                    </p>
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_meta_description"><?php esc_html_e('Meta description', 'aiseo'); ?></label>
                    <textarea id="aiseo_meta_description" name="aiseo_meta_description" class="aiseo-description-input large-text" rows="3" placeholder="<?php esc_attr_e('Auto-generated if empty', 'aiseo'); ?>" maxlength="200"><?php echo esc_textarea($meta_description); ?></textarea>
                    <button type="button" class="button button-secondary aiseo-generate-btn" data-field="description">
                        <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                        <?php esc_html_e('Generate with AI', 'aiseo'); ?>
                    </button>
                    <p class="description">
                        <span class="aiseo-char-count"><span class="aiseo-current-count">0</span> / 160</span>
                        <span class="aiseo-metabox-hint"><?php esc_html_e('Recommended: 150–160 characters', 'aiseo'); ?></span>
                    </p>
                </div>
                <div class="aiseo-metabox-actions">
                    <button type="button" class="button aiseo-analyze-btn">
                        <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                        <?php esc_html_e('Analyse content', 'aiseo'); ?>
                    </button>
                </div>
                <div class="aiseo-analysis-results" hidden>
                    <h3><?php esc_html_e('Content analysis', 'aiseo'); ?></h3>
                    <div class="aiseo-analysis-content"></div>
                </div>
                <div class="aiseo-preview-results">
                    <h3><?php esc_html_e('Search snippet', 'aiseo'); ?></h3>
                    <div class="aiseo-preview-content">
                        <div class="aiseo-snippet-title"><?php echo esc_html($meta_title); ?></div>
                        <div class="aiseo-snippet-url"><?php echo esc_html((string) wp_parse_url(get_permalink($post), PHP_URL_HOST) . (string) wp_parse_url(get_permalink($post), PHP_URL_PATH)); ?></div>
                        <div class="aiseo-snippet-description"><?php echo esc_html($meta_description); ?></div>
                    </div>
                </div>
            </div>

            <div class="aiseo-metabox-panel" id="aiseo-panel-readability" role="tabpanel" aria-labelledby="aiseo-tab-readability" hidden>
                <?php
                $readability = isset($score_data['analyses']['readability']) ? $score_data['analyses']['readability'] : array();
                $sentences = isset($score_data['analyses']['sentence_length']) ? $score_data['analyses']['sentence_length'] : array();
                $paragraphs = isset($score_data['analyses']['paragraph_structure']) ? $score_data['analyses']['paragraph_structure'] : array();
                $flesch = isset($readability['flesch_kincaid']) ? (float) $readability['flesch_kincaid'] : 0;
                ?>
                <div class="aiseo-readability-score">
                    <strong><?php echo esc_html(sprintf(/* translators: %s: Flesch Reading Ease number */ __('Flesch Reading Ease: %s', 'aiseo'), number_format_i18n($flesch, 1))); ?></strong>
                    <p class="description"><?php esc_html_e('60–80 is a comfortable web reading range. This is a text metric, not a search ranking.', 'aiseo'); ?></p>
                </div>
                <ul class="aiseo-seo-checks aiseo-metabox-checks" id="aiseo-live-readability">
                    <?php
                    foreach (array($readability, $sentences, $paragraphs) as $read_check) {
                        if (empty($read_check['message'])) {
                            continue;
                        }
                        $status = isset($read_check['status']) ? $read_check['status'] : 'warning';
                        $status = in_array($status, array('good', 'ok'), true) ? 'good' : ($status === 'poor' ? 'poor' : 'warning');
                        $icon = $status === 'good' ? 'yes-alt' : ($status === 'poor' ? 'dismiss' : 'warning');
                        echo '<li class="aiseo-check-' . esc_attr($status) . '">';
                        echo '<span class="dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span> ';
                        echo esc_html($read_check['message']);
                        echo '</li>';
                    }
                    ?>
                </ul>
            </div>

            <div class="aiseo-metabox-panel" id="aiseo-panel-social" role="tabpanel" aria-labelledby="aiseo-tab-social" hidden>
                <?php
                $og_title = get_post_meta($post->ID, '_aiseo_og_title', true);
                $og_description = get_post_meta($post->ID, '_aiseo_og_description', true);
                $og_image_id = absint(get_post_meta($post->ID, '_aiseo_og_image', true));
                $og_image_url = $og_image_id ? wp_get_attachment_image_url($og_image_id, 'medium') : '';
                $share_title = (is_string($og_title) && $og_title !== '') ? $og_title : $meta_title;
                $share_description = (is_string($og_description) && $og_description !== '') ? $og_description : $meta_description;
                $share_image = $og_image_url ? $og_image_url : get_the_post_thumbnail_url($post, 'medium');
                ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_og_title"><?php esc_html_e('Social title', 'aiseo'); ?></label>
                    <input type="text" id="aiseo_og_title" name="aiseo_og_title" class="large-text" value="<?php echo esc_attr(is_string($og_title) ? $og_title : ''); ?>" />
                    <p class="description"><?php esc_html_e('Used for Open Graph and X. Leave blank to use the SEO title.', 'aiseo'); ?></p>
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_og_description"><?php esc_html_e('Social description', 'aiseo'); ?></label>
                    <textarea id="aiseo_og_description" name="aiseo_og_description" class="large-text" rows="3"><?php echo esc_textarea(is_string($og_description) ? $og_description : ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Used for Open Graph and X. Leave blank to use the meta description.', 'aiseo'); ?></p>
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_og_image"><?php esc_html_e('Social image', 'aiseo'); ?></label>
                    <input type="hidden" id="aiseo_og_image" name="aiseo_og_image" value="<?php echo esc_attr((string) $og_image_id); ?>" />
                    <img class="aiseo-og-preview-img" src="<?php echo $og_image_url ? esc_url($og_image_url) : ''; ?>" alt="" />
                    <div class="aiseo-og-image-tools">
                        <button type="button" class="button aiseo-og-image-select"><?php esc_html_e('Select image', 'aiseo'); ?></button>
                        <button type="button" class="button aiseo-og-image-remove" <?php disabled(!$og_image_id); ?>><?php esc_html_e('Remove', 'aiseo'); ?></button>
                    </div>
                    <p class="description"><?php esc_html_e('Used for Open Graph and X. Leave empty to use the featured image.', 'aiseo'); ?></p>
                </div>
                <?php
                $twitter_card = get_post_meta($post->ID, '_aiseo_twitter_card_type', true);
                if (!in_array($twitter_card, array('summary', 'summary_large_image'), true)) {
                    $twitter_card = '';
                }
                ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_twitter_card_type"><?php esc_html_e('X card type', 'aiseo'); ?></label>
                    <select id="aiseo_twitter_card_type" name="aiseo_twitter_card_type">
                        <option value="" <?php selected($twitter_card, ''); ?>><?php esc_html_e('Automatic (large image when a featured or social image is set)', 'aiseo'); ?></option>
                        <option value="summary" <?php selected($twitter_card, 'summary'); ?>><?php esc_html_e('Summary', 'aiseo'); ?></option>
                        <option value="summary_large_image" <?php selected($twitter_card, 'summary_large_image'); ?>><?php esc_html_e('Summary with large image', 'aiseo'); ?></option>
                    </select>
                </div>
                <div class="aiseo-share-preview">
                    <h3><?php esc_html_e('Social share', 'aiseo'); ?></h3>
                    <div class="aiseo-share-card">
                        <?php if ($share_image) : ?>
                            <img class="aiseo-share-image" src="<?php echo esc_url($share_image); ?>" alt="">
                        <?php endif; ?>
                        <div class="aiseo-share-card-body">
                            <div class="aiseo-share-host"><?php echo esc_html((string) wp_parse_url(home_url(), PHP_URL_HOST)); ?></div>
                            <div class="aiseo-share-title"><?php echo esc_html($share_title); ?></div>
                            <div class="aiseo-share-description"><?php echo esc_html($share_description); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="aiseo-metabox-panel" id="aiseo-panel-schema" role="tabpanel" aria-labelledby="aiseo-tab-schema" hidden>
                <?php
                $schema_type = get_post_meta($post->ID, '_aiseo_schema_type', true);
                $schema_type = is_string($schema_type) ? strtolower($schema_type) : '';
                $schema_choices = array(
                    'auto' => __('Automatic', 'aiseo'),
                    'article' => __('Article', 'aiseo'),
                    'blogposting' => __('Blog posting', 'aiseo'),
                    'webpage' => __('Web page', 'aiseo'),
                    'newsarticle' => __('News article', 'aiseo'),
                    'product' => __('Product', 'aiseo'),
                    'howto' => __('How-to', 'aiseo'),
                    'recipe' => __('Recipe', 'aiseo'),
                    'event' => __('Event', 'aiseo'),
                    'course' => __('Course', 'aiseo'),
                    'jobposting' => __('Job posting', 'aiseo'),
                );
                if ($schema_type === '' || !isset($schema_choices[$schema_type])) {
                    $schema_type = 'auto';
                }
                $stored_faqs = get_post_meta($post->ID, '_aiseo_faqs', true);
                $faq_rows = array();
                if (is_array($stored_faqs)) {
                    foreach ($stored_faqs as $faq_item) {
                        if (!is_array($faq_item)) {
                            continue;
                        }
                        $faq_q = isset($faq_item['question']) ? $faq_item['question'] : '';
                        $faq_a = isset($faq_item['answer']) ? $faq_item['answer'] : '';
                        if ($faq_q !== '' || $faq_a !== '') {
                            $faq_rows[] = array('question' => $faq_q, 'answer' => $faq_a);
                        }
                    }
                }
                while (count($faq_rows) < 3) {
                    $faq_rows[] = array('question' => '', 'answer' => '');
                }
                $faq_rows = array_slice($faq_rows, 0, 8);
                ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_schema_type"><?php esc_html_e('Schema type', 'aiseo'); ?></label>
                    <select id="aiseo_schema_type" name="aiseo_schema_type">
                        <?php foreach ($schema_choices as $schema_value => $schema_label) : ?>
                            <option value="<?php echo esc_attr($schema_value); ?>" <?php selected($schema_type, $schema_value); ?>><?php echo esc_html($schema_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Automatic follows the post type. Product needs WooCommerce. How-to and Recipe use lists in the content. Event and Job posting use the fields below.', 'aiseo'); ?></p>
                </div>
                <?php
                $event_start = get_post_meta($post->ID, '_aiseo_event_start', true);
                $event_location = get_post_meta($post->ID, '_aiseo_event_location', true);
                ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_event_start"><?php esc_html_e('Event start', 'aiseo'); ?></label>
                    <input type="datetime-local" id="aiseo_event_start" name="aiseo_event_start" value="<?php echo esc_attr(is_string($event_start) ? $event_start : ''); ?>" />
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_event_location"><?php esc_html_e('Event location', 'aiseo'); ?></label>
                    <input type="text" id="aiseo_event_location" name="aiseo_event_location" class="large-text" value="<?php echo esc_attr(is_string($event_location) ? $event_location : ''); ?>" />
                    <p class="description"><?php esc_html_e('Used when schema type is Event. Leave blank to use the organisation address.', 'aiseo'); ?></p>
                </div>
                <?php
                $job_location = get_post_meta($post->ID, '_aiseo_job_location', true);
                $job_type = get_post_meta($post->ID, '_aiseo_job_type', true);
                $job_through = get_post_meta($post->ID, '_aiseo_job_through', true);
                if (!is_string($job_type) || $job_type === '') {
                    $job_type = 'FULL_TIME';
                }
                ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_job_location"><?php esc_html_e('Job location', 'aiseo'); ?></label>
                    <input type="text" id="aiseo_job_location" name="aiseo_job_location" class="large-text" value="<?php echo esc_attr(is_string($job_location) ? $job_location : ''); ?>" />
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_job_type"><?php esc_html_e('Employment type', 'aiseo'); ?></label>
                    <select id="aiseo_job_type" name="aiseo_job_type">
                        <?php
                        $job_types = array(
                            'FULL_TIME' => __('Full time', 'aiseo'),
                            'PART_TIME' => __('Part time', 'aiseo'),
                            'CONTRACTOR' => __('Contractor', 'aiseo'),
                            'TEMPORARY' => __('Temporary', 'aiseo'),
                            'INTERN' => __('Intern', 'aiseo'),
                            'VOLUNTEER' => __('Volunteer', 'aiseo'),
                            'OTHER' => __('Other', 'aiseo'),
                        );
                        foreach ($job_types as $job_value => $job_label) :
                            ?>
                            <option value="<?php echo esc_attr($job_value); ?>" <?php selected($job_type, $job_value); ?>><?php echo esc_html($job_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_job_through"><?php esc_html_e('Application closes', 'aiseo'); ?></label>
                    <input type="datetime-local" id="aiseo_job_through" name="aiseo_job_through" value="<?php echo esc_attr(is_string($job_through) ? $job_through : ''); ?>" />
                </div>
                <fieldset class="aiseo-metabox-field">
                    <legend><?php esc_html_e('FAQ', 'aiseo'); ?></legend>
                    <p class="description"><?php esc_html_e('Included in the JSON-LD graph. Leave a pair blank to skip it.', 'aiseo'); ?></p>
                    <?php foreach ($faq_rows as $faq_index => $faq_row) : ?>
                        <div class="aiseo-faq-row">
                            <label class="screen-reader-text" for="<?php echo esc_attr('aiseo_faq_question_' . $faq_index); ?>"><?php esc_html_e('Question', 'aiseo'); ?></label>
                            <input type="text" id="<?php echo esc_attr('aiseo_faq_question_' . $faq_index); ?>" name="aiseo_faq_question[]" class="large-text" value="<?php echo esc_attr((string) $faq_row['question']); ?>" placeholder="<?php esc_attr_e('Question', 'aiseo'); ?>" />
                            <label class="screen-reader-text" for="<?php echo esc_attr('aiseo_faq_answer_' . $faq_index); ?>"><?php esc_html_e('Answer', 'aiseo'); ?></label>
                            <textarea id="<?php echo esc_attr('aiseo_faq_answer_' . $faq_index); ?>" name="aiseo_faq_answer[]" class="large-text" rows="2" placeholder="<?php esc_attr_e('Answer', 'aiseo'); ?>"><?php echo esc_textarea((string) $faq_row['answer']); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
            </div>

            <div class="aiseo-metabox-panel" id="aiseo-panel-advanced" role="tabpanel" aria-labelledby="aiseo-tab-advanced" hidden>
                <?php
                $breadcrumb_title = get_post_meta($post->ID, '_aiseo_breadcrumb_title', true);
                $is_cornerstone = in_array(get_post_meta($post->ID, '_aiseo_cornerstone', true), array(true, 1, '1'), true);
                $exclude_sitemap = in_array(get_post_meta($post->ID, '_aiseo_exclude_sitemap', true), array(true, 1, '1'), true);
                ?>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_canonical_url"><?php esc_html_e('Canonical URL', 'aiseo'); ?></label>
                    <input type="url" id="aiseo_canonical_url" name="aiseo_canonical_url" value="<?php echo esc_attr($canonical_url); ?>" class="large-text" placeholder="<?php echo esc_attr(get_permalink($post->ID)); ?>" />
                    <p class="description"><?php esc_html_e('Leave empty to use the default permalink.', 'aiseo'); ?></p>
                </div>
                <div class="aiseo-metabox-field">
                    <label for="aiseo_breadcrumb_title"><?php esc_html_e('Breadcrumb title', 'aiseo'); ?></label>
                    <input type="text" id="aiseo_breadcrumb_title" name="aiseo_breadcrumb_title" value="<?php echo esc_attr(is_string($breadcrumb_title) ? $breadcrumb_title : ''); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Leave empty to use the post title in the breadcrumb trail.', 'aiseo'); ?></p>
                </div>
                <fieldset class="aiseo-metabox-field">
                    <legend><?php esc_html_e('Robots', 'aiseo'); ?></legend>
                    <label>
                        <input type="checkbox" name="aiseo_noindex" value="1" <?php checked(in_array($noindex, array(true, 1, '1'), true)); ?> />
                        <?php esc_html_e('No index', 'aiseo'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="aiseo_nofollow" value="1" <?php checked(in_array($nofollow, array(true, 1, '1'), true)); ?> />
                        <?php esc_html_e('No follow', 'aiseo'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="aiseo_noarchive" value="1" <?php checked(in_array($noarchive, array(true, 1, '1'), true)); ?> />
                        <?php esc_html_e('No archive', 'aiseo'); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="aiseo_nosnippet" value="1" <?php checked(in_array($nosnippet, array(true, 1, '1'), true)); ?> />
                        <?php esc_html_e('No snippet', 'aiseo'); ?>
                    </label>
                </fieldset>
                <div class="aiseo-metabox-field">
                    <label>
                        <input type="checkbox" name="aiseo_cornerstone" value="1" <?php checked($is_cornerstone); ?> />
                        <?php esc_html_e('Cornerstone content', 'aiseo'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Raises sitemap priority and prefers this URL in internal link suggestions.', 'aiseo'); ?></p>
                </div>
                <div class="aiseo-metabox-field">
                    <label>
                        <input type="checkbox" name="aiseo_exclude_sitemap" value="1" <?php checked($exclude_sitemap); ?> />
                        <?php esc_html_e('Exclude from the XML sitemap', 'aiseo'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Noindex already removes the URL from the sitemap. Use this when the page should stay indexable but not listed.', 'aiseo'); ?></p>
                </div>
                <?php if (current_user_can('aiseo_manage_redirects')) :
                    $redirect_to = get_post_meta($post->ID, '_aiseo_redirect_to', true);
                    $redirect_type = get_post_meta($post->ID, '_aiseo_redirect_type', true);
                    if (!in_array($redirect_type, array('301', '302', '307', '308', '410'), true)) {
                        $redirect_type = '';
                    }
                    ?>
                    <div class="aiseo-metabox-field">
                        <label for="aiseo_redirect_to"><?php esc_html_e('Redirect this URL', 'aiseo'); ?></label>
                        <input type="text" id="aiseo_redirect_to" name="aiseo_redirect_to" class="large-text" value="<?php echo esc_attr(is_string($redirect_to) ? $redirect_to : ''); ?>" placeholder="<?php esc_attr_e('/new-page or https://example.com/page', 'aiseo'); ?>" />
                        <select id="aiseo_redirect_type" name="aiseo_redirect_type">
                            <option value="" <?php selected($redirect_type, ''); ?>><?php esc_html_e('No redirect', 'aiseo'); ?></option>
                            <option value="301" <?php selected($redirect_type, '301'); ?>>301</option>
                            <option value="302" <?php selected($redirect_type, '302'); ?>>302</option>
                            <option value="307" <?php selected($redirect_type, '307'); ?>>307</option>
                            <option value="308" <?php selected($redirect_type, '308'); ?>>308</option>
                            <option value="410" <?php selected($redirect_type, '410'); ?>>410</option>
                        </select>
                        <p class="description"><?php esc_html_e('Sends visitors away from this permalink. Leave empty for no redirect.', 'aiseo'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php
    }
    
    /**
     * Save metabox data
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function save_metabox($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['aiseo_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiseo_metabox_nonce'])), 'aiseo_metabox_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save focus keyword
        if (isset($_POST['aiseo_focus_keyword'])) {
            update_post_meta($post_id, '_aiseo_focus_keyword', sanitize_text_field(wp_unslash($_POST['aiseo_focus_keyword'])));
        }
        if (isset($_POST['aiseo_additional_keywords'])) {
            $extra = AISEO_Helpers::parse_additional_keywords(
                wp_unslash($_POST['aiseo_additional_keywords']),
                isset($_POST['aiseo_focus_keyword']) ? sanitize_text_field(wp_unslash($_POST['aiseo_focus_keyword'])) : ''
            );
            if ($extra) {
                update_post_meta($post_id, '_aiseo_additional_keywords', implode(', ', $extra));
            } else {
                delete_post_meta($post_id, '_aiseo_additional_keywords');
            }
        }
        if (isset($_POST['aiseo_primary_category']) && is_object_in_taxonomy($post->post_type, 'category')) {
            $primary_category = absint(wp_unslash($_POST['aiseo_primary_category']));
            $posted_categories = isset($_POST['post_category']) ? array_map('absint', (array) wp_unslash($_POST['post_category'])) : array();
            if ($primary_category && in_array($primary_category, $posted_categories, true)) {
                update_post_meta($post_id, '_aiseo_primary_category', $primary_category);
            } else {
                delete_post_meta($post_id, '_aiseo_primary_category');
            }
        }
        
        // Save meta title
        if (isset($_POST['aiseo_meta_title'])) {
            update_post_meta($post_id, '_aiseo_meta_title', sanitize_text_field(wp_unslash($_POST['aiseo_meta_title'])));
        }
        
        // Save meta description
        if (isset($_POST['aiseo_meta_description'])) {
            update_post_meta($post_id, '_aiseo_meta_description', sanitize_textarea_field(wp_unslash($_POST['aiseo_meta_description'])));
        }

        if (class_exists('AISEO_Title_Templates') && $post instanceof WP_Post) {
            $templates = new AISEO_Title_Templates();
            $saved_title = get_post_meta($post_id, '_aiseo_meta_title', true);
            if ((string) $saved_title === '' && get_option('aiseo_auto_generate_title') === '1') {
                $generated_title = $templates->format_post_title($post);
                if ($generated_title !== '') {
                    update_post_meta($post_id, '_aiseo_meta_title', $generated_title);
                }
            }
            $saved_description = get_post_meta($post_id, '_aiseo_meta_description', true);
            if ((string) $saved_description === '' && get_option('aiseo_auto_generate_description') === '1') {
                $generated_description = $templates->format_post_description($post);
                if ($generated_description !== '') {
                    update_post_meta($post_id, '_aiseo_meta_description', $generated_description);
                }
            }
        }
        
        // Save canonical URL
        if (isset($_POST['aiseo_canonical_url'])) {
            update_post_meta($post_id, '_aiseo_canonical_url', esc_url_raw(wp_unslash($_POST['aiseo_canonical_url'])));
        }
        
        // Save robots meta
        update_post_meta($post_id, '_aiseo_noindex', isset($_POST['aiseo_noindex']) ? '1' : '0');
        update_post_meta($post_id, '_aiseo_nofollow', isset($_POST['aiseo_nofollow']) ? '1' : '0');
        update_post_meta($post_id, '_aiseo_noarchive', isset($_POST['aiseo_noarchive']) ? '1' : '0');
        update_post_meta($post_id, '_aiseo_nosnippet', isset($_POST['aiseo_nosnippet']) ? '1' : '0');

        if (isset($_POST['aiseo_og_title'])) {
            $og_title = sanitize_text_field(wp_unslash($_POST['aiseo_og_title']));
            if ($og_title === '') {
                delete_post_meta($post_id, '_aiseo_og_title');
            } else {
                update_post_meta($post_id, '_aiseo_og_title', $og_title);
            }
        }
        if (isset($_POST['aiseo_og_description'])) {
            $og_description = sanitize_textarea_field(wp_unslash($_POST['aiseo_og_description']));
            if ($og_description === '') {
                delete_post_meta($post_id, '_aiseo_og_description');
            } else {
                update_post_meta($post_id, '_aiseo_og_description', $og_description);
            }
        }
        if (isset($_POST['aiseo_og_image'])) {
            $og_image = absint(wp_unslash($_POST['aiseo_og_image']));
            if ($og_image && wp_attachment_is_image($og_image)) {
                update_post_meta($post_id, '_aiseo_og_image', $og_image);
            } else {
                delete_post_meta($post_id, '_aiseo_og_image');
            }
        }

        $schema_type = isset($_POST['aiseo_schema_type']) ? sanitize_key(wp_unslash($_POST['aiseo_schema_type'])) : 'auto';
        $allowed_schema = array('auto', 'article', 'blogposting', 'webpage', 'newsarticle', 'product', 'howto', 'recipe', 'event', 'course', 'jobposting');
        if (!in_array($schema_type, $allowed_schema, true)) {
            $schema_type = 'auto';
        }
        if ($schema_type === 'auto') {
            delete_post_meta($post_id, '_aiseo_schema_type');
        } else {
            update_post_meta($post_id, '_aiseo_schema_type', $schema_type);
        }

        if (isset($_POST['aiseo_event_start'])) {
            $event_start = sanitize_text_field(wp_unslash($_POST['aiseo_event_start']));
            if ($event_start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $event_start)) {
                delete_post_meta($post_id, '_aiseo_event_start');
            } else {
                update_post_meta($post_id, '_aiseo_event_start', $event_start);
            }
        }
        if (isset($_POST['aiseo_event_location'])) {
            $event_location = sanitize_text_field(wp_unslash($_POST['aiseo_event_location']));
            if ($event_location === '') {
                delete_post_meta($post_id, '_aiseo_event_location');
            } else {
                update_post_meta($post_id, '_aiseo_event_location', $event_location);
            }
        }
        if (isset($_POST['aiseo_job_location'])) {
            $job_location = sanitize_text_field(wp_unslash($_POST['aiseo_job_location']));
            if ($job_location === '') {
                delete_post_meta($post_id, '_aiseo_job_location');
            } else {
                update_post_meta($post_id, '_aiseo_job_location', $job_location);
            }
        }
        if (isset($_POST['aiseo_job_type'])) {
            $job_type = sanitize_key(wp_unslash($_POST['aiseo_job_type']));
            $allowed_jobs = array('FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'OTHER');
            $job_type = strtoupper($job_type);
            if (in_array($job_type, $allowed_jobs, true)) {
                update_post_meta($post_id, '_aiseo_job_type', $job_type);
            } else {
                delete_post_meta($post_id, '_aiseo_job_type');
            }
        }
        if (isset($_POST['aiseo_job_through'])) {
            $job_through = sanitize_text_field(wp_unslash($_POST['aiseo_job_through']));
            if ($job_through === '' || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $job_through)) {
                delete_post_meta($post_id, '_aiseo_job_through');
            } else {
                update_post_meta($post_id, '_aiseo_job_through', $job_through);
            }
        }

        if (isset($_POST['aiseo_breadcrumb_title'])) {
            $breadcrumb_title = sanitize_text_field(wp_unslash($_POST['aiseo_breadcrumb_title']));
            if ($breadcrumb_title === '') {
                delete_post_meta($post_id, '_aiseo_breadcrumb_title');
            } else {
                update_post_meta($post_id, '_aiseo_breadcrumb_title', $breadcrumb_title);
            }
        }

        update_post_meta($post_id, '_aiseo_cornerstone', isset($_POST['aiseo_cornerstone']) ? '1' : '0');
        update_post_meta($post_id, '_aiseo_exclude_sitemap', isset($_POST['aiseo_exclude_sitemap']) ? '1' : '0');

        if (current_user_can('aiseo_manage_redirects') && isset($_POST['aiseo_redirect_type'])) {
            $redirect_type = sanitize_key(wp_unslash($_POST['aiseo_redirect_type']));
            $redirect_to = isset($_POST['aiseo_redirect_to']) ? wp_unslash($_POST['aiseo_redirect_to']) : '';
            $redirect_to = class_exists('AISEO_Redirects') ? AISEO_Redirects::sanitize_target($redirect_to) : '';
            if (!in_array($redirect_type, array('301', '302', '307', '308', '410'), true) || ($redirect_type !== '410' && $redirect_to === '')) {
                delete_post_meta($post_id, '_aiseo_redirect_to');
                delete_post_meta($post_id, '_aiseo_redirect_type');
            } else {
                update_post_meta($post_id, '_aiseo_redirect_type', $redirect_type);
                if ($redirect_type === '410') {
                    delete_post_meta($post_id, '_aiseo_redirect_to');
                } else {
                    update_post_meta($post_id, '_aiseo_redirect_to', $redirect_to);
                }
            }
        }

        if (isset($_POST['aiseo_twitter_card_type'])) {
            $twitter_card = sanitize_key(wp_unslash($_POST['aiseo_twitter_card_type']));
            if (in_array($twitter_card, array('summary', 'summary_large_image'), true)) {
                update_post_meta($post_id, '_aiseo_twitter_card_type', $twitter_card);
            } else {
                delete_post_meta($post_id, '_aiseo_twitter_card_type');
            }
        }

        $faqs = array();
        if (isset($_POST['aiseo_faq_question']) && is_array($_POST['aiseo_faq_question'])) {
            $questions = array_map('sanitize_text_field', wp_unslash($_POST['aiseo_faq_question']));
            $answers = array();
            if (isset($_POST['aiseo_faq_answer']) && is_array($_POST['aiseo_faq_answer'])) {
                $answers = array_map('sanitize_textarea_field', wp_unslash($_POST['aiseo_faq_answer']));
            }
            foreach ($questions as $index => $question) {
                $answer = isset($answers[$index]) ? $answers[$index] : '';
                if ($question !== '' && $answer !== '') {
                    $faqs[] = array(
                        'question' => $question,
                        'answer' => $answer,
                    );
                }
                if (count($faqs) >= 8) {
                    break;
                }
            }
        }
        if ($faqs) {
            update_post_meta($post_id, '_aiseo_faqs', $faqs);
        } else {
            delete_post_meta($post_id, '_aiseo_faqs');
        }
        delete_transient('aiseo_dashboard_counts');
        delete_transient('aiseo_dashboard_audit');
    }
    
    /**
     * Enqueue metabox scripts
     */
    public function enqueue_metabox_scripts($hook) {
        // Only load on post editor
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        // Enqueue WordPress media library
        wp_enqueue_media();
        
        // Enqueue metabox JavaScript (external file instead of inline)
        wp_enqueue_style(
            'aiseo-admin',
            AISEO_PLUGIN_URL . 'admin/css/aiseo-admin.css',
            array(),
            AISEO_VERSION
        );
        wp_enqueue_script(
            'aiseo-metabox',
            AISEO_PLUGIN_URL . 'js/aiseo-metabox.js',
            array('jquery', 'wp-data'),
            AISEO_VERSION,
            true
        );
    }
    
    /**
     * Local SEO checks shown beside the editor, in the style of a Yoast analysis list.
     *
     * @param array  $score_data       Result from AISEO_Analysis::analyze_post().
     * @param string $meta_title       SEO title field.
     * @param string $meta_description Meta description field.
     * @param string $focus_keyword    Focus keyword field.
     */
    private function render_checklist($score_data, $meta_title, $meta_description, $focus_keyword) {
        $checks = array();
        $title_length = strlen($meta_title);
        $description_length = strlen($meta_description);

        $checks[] = array(
            'status' => ($title_length >= 50 && $title_length <= 60) ? 'good' : 'warning',
            'message' => sprintf(
                /* translators: %d: character count */
                __('SEO title length is %d characters. Aim for 50 to 60.', 'aiseo'),
                $title_length
            ),
        );
        $checks[] = array(
            'status' => ($description_length >= 120 && $description_length <= 160) ? 'good' : 'warning',
            'message' => sprintf(
                /* translators: %d: character count */
                __('Meta description length is %d characters. Aim for 120 to 160.', 'aiseo'),
                $description_length
            ),
        );
        if ($focus_keyword !== '') {
            $in_title = stripos($meta_title, $focus_keyword) !== false;
            $checks[] = array(
                'status' => $in_title ? 'good' : 'poor',
                'message' => $in_title
                    ? __('The focus keyword appears in the SEO title.', 'aiseo')
                    : __('The focus keyword does not appear in the SEO title.', 'aiseo'),
            );
        }

        $labels = array(
            'keyword_density' => __('Keyword density', 'aiseo'),
            'additional_keywords' => __('Extra keywords', 'aiseo'),
            'readability' => __('Readability', 'aiseo'),
            'sentence_length' => __('Sentence length', 'aiseo'),
            'paragraph_structure' => __('Paragraphs', 'aiseo'),
            'content_length' => __('Content length', 'aiseo'),
            'keyword_in_title' => __('Keyword in the post title', 'aiseo'),
            'keyword_in_headings' => __('Keyword in headings', 'aiseo'),
            'keyword_in_intro' => __('Keyword in the introduction', 'aiseo'),
            'internal_links' => __('Internal links', 'aiseo'),
            'external_links' => __('External links', 'aiseo'),
            'images' => __('Images', 'aiseo'),
        );
        if (!empty($score_data['analyses']) && is_array($score_data['analyses'])) {
            foreach ($labels as $key => $label) {
                if (empty($score_data['analyses'][$key]['message'])) {
                    continue;
                }
                $checks[] = array(
                    'status' => isset($score_data['analyses'][$key]['status']) ? $score_data['analyses'][$key]['status'] : 'warning',
                    'message' => $label . ': ' . $score_data['analyses'][$key]['message'],
                );
            }
        }

        echo '<ul class="aiseo-seo-checks aiseo-metabox-checks" id="aiseo-live-checks">';
        foreach ($checks as $check) {
            $status = in_array($check['status'], array('good', 'ok'), true) ? 'good' : ($check['status'] === 'poor' ? 'poor' : 'warning');
            $icon = $status === 'good' ? 'yes-alt' : ($status === 'poor' ? 'dismiss' : 'warning');
            $prefix = $status === 'good' ? __('Good', 'aiseo') : ($status === 'poor' ? __('Problem', 'aiseo') : __('Needs improvement', 'aiseo'));
            echo '<li class="aiseo-check-' . esc_attr($status) . '">';
            echo '<span class="dashicons dashicons-' . esc_attr($icon) . '" aria-hidden="true"></span> ';
            echo '<span class="screen-reader-text">' . esc_html($prefix) . ': </span>';
            echo esc_html($check['message']);
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * AJAX handler for generating keyword
     */
    public function ajax_generate_keyword() {
        check_ajax_referer('aiseo_metabox_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        $post = get_post($post_id);
        
        if (!$post || empty($post->post_content)) {
            wp_send_json_error('Post content is required to generate keywords');
        }
        
        // Use AI to extract main keyword from content
        $api = new AISEO_API();
        
        $prompt = "Analyze this content and suggest the single most important SEO keyword or key phrase (2-4 words maximum) that best represents the main topic. Return only the keyword phrase, nothing else.\n\nContent:\n" . wp_strip_all_tags($post->post_content);
        
        $keyword = $api->make_request($prompt, array(
            'max_tokens' => 20,
            'temperature' => 0.3,
        ));
        
        if (is_wp_error($keyword)) {
            wp_send_json_error($keyword->get_error_message());
        }
        
        // Clean up the keyword
        $keyword = trim($keyword);
        $keyword = strtolower($keyword);
        
        wp_send_json_success($keyword);
    }
    
    /**
     * AJAX handler for generating title
     */
    public function ajax_generate_title() {
        check_ajax_referer('aiseo_metabox_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        $post = get_post($post_id);
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        $api = new AISEO_API();
        $title = $api->generate_title($post->post_content, $keyword);
        
        if (is_wp_error($title)) {
            wp_send_json_error($title->get_error_message());
        }
        
        wp_send_json_success($title);
    }
    
    /**
     * AJAX handler for generating description
     */
    public function ajax_generate_description() {
        check_ajax_referer('aiseo_metabox_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        $post = get_post($post_id);
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        $api = new AISEO_API();
        $description = $api->generate_meta_description($post->post_content, $keyword);
        
        if (is_wp_error($description)) {
            wp_send_json_error($description->get_error_message());
        }
        
        wp_send_json_success($description);
    }
    
    /**
     * AJAX handler for analyzing content
     */
    public function ajax_analyze_content() {
        check_ajax_referer('aiseo_metabox_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        $analysis = new AISEO_Analysis();
        $results = $analysis->analyze_post($post_id, $keyword);
        
        if (is_wp_error($results)) {
            wp_send_json_error($results->get_error_message());
        }
        
        wp_send_json_success($results);
    }
}
