<?php
/**
 * Settings Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_render_settings_view() {

// Handle form submission
if (isset($_POST['aiseo_settings_submit']) && current_user_can('aiseo_manage_settings') && check_admin_referer('aiseo_settings_nonce', 'aiseo_settings_nonce')) {
    // API Settings
    if (isset($_POST['aiseo_api_key']) && !empty($_POST['aiseo_api_key'])) {
        $api_key_input = sanitize_text_field(wp_unslash($_POST['aiseo_api_key']));
        // Only save if it's not the masked value
        if (strpos($api_key_input, '*') === false && !empty($api_key_input)) {
            AISEO_Helpers::save_api_key($api_key_input);
        }
    }
    if (isset($_POST['aiseo_model'])) {
        $model = sanitize_text_field(wp_unslash($_POST['aiseo_model']));
        update_option('aiseo_model', $model);
        update_option('aiseo_api_model', $model);
    }
    if (isset($_POST['aiseo_max_tokens'])) {
        update_option('aiseo_max_tokens', absint($_POST['aiseo_max_tokens']));
    }
    if (isset($_POST['aiseo_temperature'])) {
        update_option('aiseo_temperature', floatval($_POST['aiseo_temperature']));
    }
    
    // Performance Settings
    if (isset($_POST['aiseo_cache_ttl'])) {
        update_option('aiseo_cache_ttl', absint($_POST['aiseo_cache_ttl']));
    }
    if (isset($_POST['aiseo_rate_limit'])) {
        update_option('aiseo_rate_limit', absint($_POST['aiseo_rate_limit']));
    }
    
    // Feature Toggles
    update_option('aiseo_auto_generate_title', isset($_POST['aiseo_auto_generate_title']) ? '1' : '0');
    update_option('aiseo_auto_generate_description', isset($_POST['aiseo_auto_generate_description']) ? '1' : '0');
    update_option('aiseo_enable_schema', isset($_POST['aiseo_enable_schema']) ? '1' : '0');
    update_option('aiseo_enable_schema_aggregation', isset($_POST['aiseo_enable_schema_aggregation']) ? '1' : '0');
    update_option('aiseo_enable_social_tags', isset($_POST['aiseo_enable_social_tags']) ? '1' : '0');
    update_option('aiseo_enable_sitemap', isset($_POST['aiseo_enable_sitemap']) ? '1' : '0');
    update_option('aiseo_enable_llms', isset($_POST['aiseo_enable_llms']) ? '1' : '0');
    update_option('aiseo_enable_llms_full', isset($_POST['aiseo_enable_llms_full']) ? '1' : '0');
    update_option('aiseo_enable_indexnow', isset($_POST['aiseo_enable_indexnow']) ? '1' : '0');

    $llms_types = array();
    $allowed_llms_types = get_post_types(array('public' => true), 'names');
    unset($allowed_llms_types['attachment']);
    if (isset($_POST['aiseo_llms_types']) && is_array($_POST['aiseo_llms_types'])) {
        foreach ($_POST['aiseo_llms_types'] as $llms_type) {
            $llms_type = sanitize_key($llms_type);
            if (isset($allowed_llms_types[$llms_type])) {
                $llms_types[] = $llms_type;
            }
        }
    }
    update_option('aiseo_llms_types', $llms_types);
    $llms_limit = isset($_POST['aiseo_llms_limit']) ? absint($_POST['aiseo_llms_limit']) : 100;
    $llms_limit = max(1, min(500, $llms_limit));
    update_option('aiseo_llms_limit', $llms_limit);
    $llms_extra = isset($_POST['aiseo_llms_extra']) ? sanitize_textarea_field(wp_unslash($_POST['aiseo_llms_extra'])) : '';
    update_option('aiseo_llms_extra', $llms_extra);

    $site_represents = isset($_POST['aiseo_site_represents']) ? sanitize_key(wp_unslash($_POST['aiseo_site_represents'])) : 'organization';
    if (!in_array($site_represents, array('organization', 'person'), true)) {
        $site_represents = 'organization';
    }
    update_option('aiseo_site_represents', $site_represents);
    update_option('aiseo_schema_person_id', isset($_POST['aiseo_schema_person_id']) ? absint($_POST['aiseo_schema_person_id']) : 0);
    
    // Logging
    update_option('aiseo_enable_logging', isset($_POST['aiseo_enable_logging']) ? '1' : '0');
    $log_level = isset($_POST['aiseo_log_level']) ? sanitize_text_field(wp_unslash($_POST['aiseo_log_level'])) : 'error';
    if (!in_array($log_level, array('error', 'warning', 'info', 'debug'), true)) {
        $log_level = 'error';
    }
    update_option('aiseo_log_level', $log_level);
    
    // Homepage SEO Settings
    $homepage_seo = new AISEO_Homepage_SEO();
    $homepage_settings = array();
    if (isset($_POST['aiseo_home_title'])) {
        $homepage_settings['home_title'] = sanitize_text_field(wp_unslash($_POST['aiseo_home_title']));
    }
    if (isset($_POST['aiseo_home_description'])) {
        $homepage_settings['home_description'] = sanitize_textarea_field(wp_unslash($_POST['aiseo_home_description']));
    }
    if (isset($_POST['aiseo_home_keywords'])) {
        $homepage_settings['home_keywords'] = sanitize_text_field(wp_unslash($_POST['aiseo_home_keywords']));
    }
    if (isset($_POST['aiseo_blog_title'])) {
        $homepage_settings['blog_title'] = sanitize_text_field(wp_unslash($_POST['aiseo_blog_title']));
    }
    if (isset($_POST['aiseo_blog_description'])) {
        $homepage_settings['blog_description'] = sanitize_textarea_field(wp_unslash($_POST['aiseo_blog_description']));
    }
    if (isset($_POST['aiseo_blog_keywords'])) {
        $homepage_settings['blog_keywords'] = sanitize_text_field(wp_unslash($_POST['aiseo_blog_keywords']));
    }
    $homepage_seo->update_settings($homepage_settings);
    
    // Webmaster Verification Codes
    $webmaster = new AISEO_Webmaster();
    $verification = array();
    if (isset($_POST['aiseo_verification_google'])) {
        $verification['google'] = sanitize_text_field(wp_unslash($_POST['aiseo_verification_google']));
    }
    if (isset($_POST['aiseo_verification_bing'])) {
        $verification['bing'] = sanitize_text_field(wp_unslash($_POST['aiseo_verification_bing']));
    }
    if (isset($_POST['aiseo_verification_yandex'])) {
        $verification['yandex'] = sanitize_text_field(wp_unslash($_POST['aiseo_verification_yandex']));
    }
    if (isset($_POST['aiseo_verification_pinterest'])) {
        $verification['pinterest'] = sanitize_text_field(wp_unslash($_POST['aiseo_verification_pinterest']));
    }
    if (isset($_POST['aiseo_verification_baidu'])) {
        $verification['baidu'] = sanitize_text_field(wp_unslash($_POST['aiseo_verification_baidu']));
    }
    if (isset($_POST['aiseo_gsc_client_id'])) {
        update_option('aiseo_gsc_client_id', sanitize_text_field(wp_unslash($_POST['aiseo_gsc_client_id'])));
    }
    if (isset($_POST['aiseo_gsc_client_secret']) && sanitize_text_field(wp_unslash($_POST['aiseo_gsc_client_secret'])) !== '') {
        $gsc_secret = sanitize_text_field(wp_unslash($_POST['aiseo_gsc_client_secret']));
        if (strpos($gsc_secret, '*') === false) {
            update_option('aiseo_gsc_client_secret', AISEO_Helpers::encrypt_api_key($gsc_secret), false);
        }
    }
    if (isset($_POST['aiseo_gsc_property'])) {
        $raw_property = sanitize_text_field(wp_unslash($_POST['aiseo_gsc_property']));
        if ($raw_property === '') {
            delete_option('aiseo_gsc_property');
        } elseif (strpos($raw_property, 'sc-domain:') === 0) {
            update_option('aiseo_gsc_property', $raw_property);
        } else {
            update_option('aiseo_gsc_property', esc_url_raw($raw_property));
        }
        delete_transient('aiseo_gsc_summary');
    }
    $webmaster->update_verification_codes($verification);
    
    // Analytics Settings
    $analytics = new AISEO_Analytics();
    $analytics_data = array();
    if (isset($_POST['aiseo_ga_tracking_id'])) {
        $analytics_data['tracking_id'] = sanitize_text_field(wp_unslash($_POST['aiseo_ga_tracking_id']));
    }
    $analytics_data['enabled'] = isset($_POST['aiseo_ga_enabled']);
    $analytics_data['anonymize_ip'] = isset($_POST['aiseo_ga_anonymize_ip']);
    $analytics_data['track_admin'] = isset($_POST['aiseo_ga_track_admin']);
    $analytics_data['track_logged_in'] = isset($_POST['aiseo_ga_track_logged_in']);
    $analytics->update_settings($analytics_data);

    if (isset($_POST['aiseo_organization_profiles'])) {
        $profiles = array();
        foreach (array('facebook', 'instagram', 'linkedin', 'youtube', 'wikipedia') as $profile_key) {
            if (!isset($_POST['aiseo_profile_' . $profile_key])) {
                continue;
            }
            $profile_url = esc_url_raw(wp_unslash($_POST['aiseo_profile_' . $profile_key]));
            if (is_string($profile_url) && wp_http_validate_url($profile_url)) {
                $profiles[$profile_key] = $profile_url;
            }
        }
        update_option('aiseo_organization_sameas', $profiles);
        $org_details = array(
            'name' => isset($_POST['aiseo_org_name']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_name'])) : '',
            'logo_id' => isset($_POST['aiseo_org_logo_id']) ? absint($_POST['aiseo_org_logo_id']) : 0,
            'legal_name' => isset($_POST['aiseo_org_legal_name']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_legal_name'])) : '',
            'phone' => isset($_POST['aiseo_org_phone']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_phone'])) : '',
            'email' => isset($_POST['aiseo_org_email']) ? sanitize_email(wp_unslash($_POST['aiseo_org_email'])) : '',
            'street' => isset($_POST['aiseo_org_street']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_street'])) : '',
            'locality' => isset($_POST['aiseo_org_locality']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_locality'])) : '',
            'region' => isset($_POST['aiseo_org_region']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_region'])) : '',
            'postal' => isset($_POST['aiseo_org_postal']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_postal'])) : '',
            'country' => isset($_POST['aiseo_org_country']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_country'])) : '',
        );
        update_option('aiseo_organization_details', $org_details);
        update_option('aiseo_default_og_image', isset($_POST['aiseo_default_og_image']) ? absint($_POST['aiseo_default_og_image']) : 0);
        $twitter_handle = isset($_POST['aiseo_twitter_site']) ? sanitize_text_field(wp_unslash($_POST['aiseo_twitter_site'])) : '';
        $twitter_handle = ltrim($twitter_handle, '@');
        if ($twitter_handle !== '' && preg_match('/^[A-Za-z0-9_]{1,15}$/', $twitter_handle)) {
            update_option('aiseo_twitter_site', '@' . $twitter_handle);
        } else {
            update_option('aiseo_twitter_site', '');
        }
        $card_default = isset($_POST['aiseo_twitter_card_default']) ? sanitize_key(wp_unslash($_POST['aiseo_twitter_card_default'])) : '';
        if (in_array($card_default, array('summary', 'summary_large_image'), true)) {
            update_option('aiseo_twitter_card_default', $card_default);
        } else {
            delete_option('aiseo_twitter_card_default');
        }
    }

    $news_name = isset($_POST['aiseo_news_publication']) ? sanitize_text_field(wp_unslash($_POST['aiseo_news_publication'])) : '';
    if ($news_name === '') {
        delete_option('aiseo_news_publication');
    } else {
        update_option('aiseo_news_publication', $news_name);
    }
    $news_lang = isset($_POST['aiseo_news_language']) ? sanitize_text_field(wp_unslash($_POST['aiseo_news_language'])) : '';
    if (!preg_match('/^[A-Za-z]{2,3}(-[A-Za-z]{2,3})?$/', $news_lang)) {
        $news_lang = '';
    }
    if ($news_lang === '') {
        delete_option('aiseo_news_language');
    } else {
        update_option('aiseo_news_language', $news_lang);
    }

    if (isset($_POST['aiseo_breadcrumbs_form'])) {
        $breadcrumb_settings = new AISEO_Breadcrumbs();
        $breadcrumb_update = array(
            'show_in_content' => isset($_POST['aiseo_breadcrumbs_in_content']),
        );
        if (isset($_POST['aiseo_breadcrumb_separator'])) {
            $breadcrumb_separator = sanitize_text_field(wp_unslash($_POST['aiseo_breadcrumb_separator']));
            if ($breadcrumb_separator !== '') {
                $breadcrumb_update['separator'] = $breadcrumb_separator;
            }
        }
        if (isset($_POST['aiseo_breadcrumb_home'])) {
            $breadcrumb_home = sanitize_text_field(wp_unslash($_POST['aiseo_breadcrumb_home']));
            if ($breadcrumb_home !== '') {
                $breadcrumb_update['home_text'] = $breadcrumb_home;
            }
        }
        $breadcrumb_settings->update_settings($breadcrumb_update);
    }

    if (isset($_POST['aiseo_title_templates_form'])) {
        $title_templates = new AISEO_Title_Templates();
        $title_update = array();
        if (isset($_POST['aiseo_title_separator'])) {
            $separator = sanitize_text_field(wp_unslash($_POST['aiseo_title_separator']));
            if ($separator !== '') {
                $title_update['separator'] = $separator;
            }
        }
        foreach (array('post_title', 'page_title', 'category_title', 'tag_title', 'taxonomy_title', 'author_title', 'search_title', 'archive_title', 'date_title', '404_title', 'post_description', 'page_description', 'category_description', 'tag_description', 'taxonomy_description', 'author_description') as $template_key) {
            if (isset($_POST['aiseo_template_' . $template_key])) {
                $title_update[$template_key] = sanitize_text_field(wp_unslash($_POST['aiseo_template_' . $template_key]));
            }
        }
        $title_templates->update_templates($title_update);
    }

    if (isset($_POST['aiseo_rss_form'])) {
        $rss = new AISEO_RSS();
        $rss->update_settings(array(
            'enabled' => isset($_POST['aiseo_rss_enabled']),
            'before_content' => isset($_POST['aiseo_rss_before']) ? wp_kses_post(wp_unslash($_POST['aiseo_rss_before'])) : '',
            'after_content' => isset($_POST['aiseo_rss_after']) ? wp_kses_post(wp_unslash($_POST['aiseo_rss_after'])) : '',
        ));
    }

    if (isset($_POST['aiseo_robots_form'])) {
        $robots_settings = new AISEO_Robots();
        $robots_update = array();
        foreach (array(
            'noindex_search_results',
            'noindex_date_archives',
            'noindex_author_archives',
            'noindex_categories',
            'noindex_tags',
            'noindex_attachment_pages',
            'noindex_empty_taxonomies',
            'noindex_paginated',
            'nofollow_external_links',
            'block_ai_crawlers',
            'noindex_out_of_stock',
        ) as $robots_key) {
            $robots_update[$robots_key] = isset($_POST['aiseo_robots_' . $robots_key]);
        }
        $robots_settings->update_settings($robots_update);
        $posted_agents = isset($_POST['aiseo_robots_ai_agents']) ? wp_unslash($_POST['aiseo_robots_ai_agents']) : array();
        $robots_settings->save_blocked_ai_crawlers(is_array($posted_agents) ? $posted_agents : array());
        $extra_robots = isset($_POST['aiseo_robots_extra']) ? wp_unslash($_POST['aiseo_robots_extra']) : '';
        $extra_robots = is_string($extra_robots) ? $robots_settings->sanitize_extra_rules($extra_robots) : '';
        if ($extra_robots === '') {
            delete_option('aiseo_robots_extra');
        } else {
            update_option('aiseo_robots_extra', $extra_robots);
        }
    }
    
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'aiseo') . '</p></div>';
}

// Get current settings
$api_key = AISEO_Helpers::get_api_key();
$model = get_option('aiseo_api_model', get_option('aiseo_model', 'gpt-4o-mini'));
$max_tokens = get_option('aiseo_max_tokens', 1000);
$temperature = get_option('aiseo_temperature', 0.7);
$cache_ttl = get_option('aiseo_cache_ttl', 86400);
$rate_limit = get_option('aiseo_rate_limit', 10);
$auto_title = get_option('aiseo_auto_generate_title', '0');
$auto_desc = get_option('aiseo_auto_generate_description', '0');
$enable_schema = get_option('aiseo_enable_schema', '1');
$schema_enabled = ! in_array($enable_schema, array(false, 0, '0', ''), true);
$enable_sitemap = get_option('aiseo_enable_sitemap', '1');
$llms_enabled = !in_array(get_option('aiseo_enable_llms', '1'), array(false, 0, '0', ''), true);
$llms_full_enabled = !in_array(get_option('aiseo_enable_llms_full', '0'), array(false, 0, '0', ''), true);
$schema_aggregation_enabled = !in_array(get_option('aiseo_enable_schema_aggregation', '0'), array(false, 0, '0', ''), true);
$social_tags_enabled = !in_array(get_option('aiseo_enable_social_tags', '1'), array(false, 0, '0', ''), true);
$indexnow_enabled = !in_array(get_option('aiseo_enable_indexnow', '1'), array(false, 0, '0', ''), true);
$enable_logging = get_option('aiseo_enable_logging', '0');
$log_level = get_option('aiseo_log_level', 'error');

// Get homepage SEO settings
$homepage_seo = new AISEO_Homepage_SEO();
$homepage_settings = $homepage_seo->get_settings();

// Get webmaster verification codes
$webmaster = new AISEO_Webmaster();
$verification_codes = $webmaster->get_verification_codes();

// Get analytics settings
$analytics = new AISEO_Analytics();
$analytics_settings = $analytics->get_settings();
?>

<div class="aiseo-settings">
    <?php
    $aiseo_settings_nav = array(
        'aiseo-settings-api' => __('General', 'aiseo'),
        'aiseo-settings-appearance' => __('Search appearance', 'aiseo'),
        'aiseo-settings-organisation' => __('Social & schema', 'aiseo'),
        'aiseo-settings-gsc' => __('Connections', 'aiseo'),
        'aiseo-settings-security' => __('Security', 'aiseo'),
    );
    ?>
    <nav class="aiseo-settings-nav" aria-label="<?php esc_attr_e('Settings sections', 'aiseo'); ?>">
        <?php foreach ($aiseo_settings_nav as $aiseo_nav_id => $aiseo_nav_label) : ?>
            <a href="<?php echo esc_url('#' . $aiseo_nav_id); ?>"><?php echo esc_html($aiseo_nav_label); ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="" class="aiseo-settings-form">
        <?php wp_nonce_field('aiseo_settings_nonce', 'aiseo_settings_nonce'); ?>
        
        <!-- API Settings -->
        <div class="aiseo-form-section" id="aiseo-settings-api">
            <h2 class="aiseo-section-title"><?php esc_html_e('API Settings', 'aiseo'); ?></h2>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_api_key">
                    <?php esc_html_e('OpenAI API Key', 'aiseo'); ?>
                    <?php if (!(class_exists('AISEO_WP_AI') && AISEO_WP_AI::is_ready())) : ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
                <input type="password" 
                       id="aiseo_api_key"
                       name="aiseo_api_key" 
                       value="<?php echo esc_attr($api_key ? str_repeat('*', 20) : ''); ?>" 
                       class="large-text"
                       placeholder="sk-..." />
                <span class="aiseo-form-description">
                    <?php if (class_exists('AISEO_WP_AI') && AISEO_WP_AI::is_ready()) : ?>
                        <?php esc_html_e('WordPress 7.0 Connectors are active, so this key is optional. Generation uses Settings → Connectors first.', 'aiseo'); ?>
                    <?php else : ?>
                        <?php esc_html_e('Get your API key from', 'aiseo'); ?>
                        <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.
                        <?php esc_html_e('On WordPress 7.0 or later you can use Settings → Connectors instead.', 'aiseo'); ?>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_model"><?php esc_html_e('AI Model', 'aiseo'); ?></label>
                <select id="aiseo_model" name="aiseo_model" class="regular-text">
                    <option value="gpt-4o-mini" <?php selected($model, 'gpt-4o-mini'); ?>>GPT-4o Mini (Recommended)</option>
                    <option value="gpt-4o" <?php selected($model, 'gpt-4o'); ?>>GPT-4o</option>
                    <option value="gpt-4" <?php selected($model, 'gpt-4'); ?>>GPT-4</option>
                    <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                </select>
                <span class="aiseo-form-description"><?php esc_html_e('GPT-4o-mini offers the best balance of speed and quality', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_max_tokens"><?php esc_html_e('Max Tokens', 'aiseo'); ?></label>
                <input type="number" 
                       id="aiseo_max_tokens"
                       name="aiseo_max_tokens" 
                       value="<?php echo esc_attr($max_tokens); ?>" 
                       class="small-text"
                       min="100" 
                       max="4000" />
                <span class="aiseo-form-description"><?php esc_html_e('Maximum tokens per API request (100-4000)', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_temperature"><?php esc_html_e('Temperature', 'aiseo'); ?></label>
                <input type="number" 
                       id="aiseo_temperature"
                       name="aiseo_temperature" 
                       value="<?php echo esc_attr($temperature); ?>" 
                       class="small-text"
                       min="0" 
                       max="2" 
                       step="0.1" />
                <span class="aiseo-form-description"><?php esc_html_e('Controls randomness: 0 = focused, 2 = creative (0-2)', 'aiseo'); ?></span>
            </div>
        </div>
        
        <!-- Performance Settings -->
        <div class="aiseo-form-section" id="aiseo-settings-performance">
            <h2 class="aiseo-section-title"><?php esc_html_e('Performance', 'aiseo'); ?></h2>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_cache_ttl"><?php esc_html_e('Cache TTL (seconds)', 'aiseo'); ?></label>
                <input type="number" 
                       id="aiseo_cache_ttl"
                       name="aiseo_cache_ttl" 
                       value="<?php echo esc_attr($cache_ttl); ?>" 
                       class="small-text"
                       min="3600" />
                <span class="aiseo-form-description"><?php esc_html_e('How long to cache AI responses (default: 86400 = 24 hours)', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_rate_limit"><?php esc_html_e('Rate Limit (requests/minute)', 'aiseo'); ?></label>
                <input type="number" 
                       id="aiseo_rate_limit"
                       name="aiseo_rate_limit" 
                       value="<?php echo esc_attr($rate_limit); ?>" 
                       class="small-text"
                       min="1" 
                       max="60" />
                <span class="aiseo-form-description"><?php esc_html_e('Maximum API requests per minute (default: 10)', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <button type="button" class="button button-secondary" onclick="if(confirm('Clear all AISEO caches?')) { location.href='<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings&action=clear_cache')); ?>'; }">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear All Caches', 'aiseo'); ?>
                </button>
            </div>
        </div>
        
        <!-- Feature Toggles -->
        <div class="aiseo-form-section" id="aiseo-settings-features">
            <h2 class="aiseo-section-title"><?php esc_html_e('Features', 'aiseo'); ?></h2>
            
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_auto_generate_title" value="1" <?php checked($auto_title, '1'); ?> />
                    <?php esc_html_e('Fill empty SEO titles from the title format when a post is saved', 'aiseo'); ?>
                </label>
            </div>
            
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_auto_generate_description" value="1" <?php checked($auto_desc, '1'); ?> />
                    <?php esc_html_e('Fill empty meta descriptions from the description format when a post is saved', 'aiseo'); ?>
                </label>
            </div>
            
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_schema" value="1" <?php checked($schema_enabled); ?> />
                    <?php esc_html_e('Enable Schema Markup (JSON-LD)', 'aiseo'); ?>
                </label>
            </div>
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_schema_aggregation" value="1" <?php checked($schema_aggregation_enabled); ?> />
                    <?php esc_html_e('Publish a site-wide schema map at /aiseo-schema.json for AI systems', 'aiseo'); ?>
                </label>
                <p class="description"><?php esc_html_e('Off by default. Only public, indexable posts are included, with Organisation and WebSite nodes deduplicated.', 'aiseo'); ?></p>
            </div>
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_social_tags" value="1" <?php checked($social_tags_enabled); ?> />
                    <?php esc_html_e('Output Open Graph and X card tags', 'aiseo'); ?>
                </label>
                <p class="description"><?php esc_html_e('Turn this off if another plugin already prints social tags, to avoid duplicates.', 'aiseo'); ?></p>
            </div>
            
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_sitemap" value="1" <?php checked($enable_sitemap, '1'); ?> />
                    <?php esc_html_e('Enable XML Sitemap', 'aiseo'); ?>
                </label>
                <p class="description"><?php esc_html_e('Add an HTML sitemap to a page with the shortcode [aiseo_html_sitemap].', 'aiseo'); ?></p>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_news_publication"><?php esc_html_e('Google News publication name', 'aiseo'); ?></label>
                <input type="text" name="aiseo_news_publication" id="aiseo_news_publication" class="regular-text" value="<?php echo esc_attr((string) get_option('aiseo_news_publication', '')); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_news_language"><?php esc_html_e('Google News language', 'aiseo'); ?></label>
                <input type="text" name="aiseo_news_language" id="aiseo_news_language" class="small-text" value="<?php echo esc_attr((string) get_option('aiseo_news_language', '')); ?>" placeholder="<?php echo esc_attr((string) get_bloginfo('language')); ?>" />
                <p class="description"><?php esc_html_e('ISO language such as en or en-GB. Leave empty to use the site language.', 'aiseo'); ?></p>
            </div>

            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_llms" value="1" <?php checked($llms_enabled); ?> />
                    <?php esc_html_e('Publish llms.txt for AI assistants', 'aiseo'); ?>
                </label>
            </div>
            <?php
            $llms_types_saved = get_option('aiseo_llms_types', null);
            $llms_type_objects = get_post_types(array('public' => true), 'objects');
            unset($llms_type_objects['attachment']);
            $llms_limit = absint(get_option('aiseo_llms_limit', 100));
            if ($llms_limit < 1) {
                $llms_limit = 100;
            }
            $llms_extra = get_option('aiseo_llms_extra', '');
            if (!is_string($llms_extra)) {
                $llms_extra = '';
            }
            ?>
            <div class="aiseo-form-group aiseo-llms-options">
                <p class="aiseo-form-label"><?php esc_html_e('Include in llms.txt', 'aiseo'); ?></p>
                <?php foreach ($llms_type_objects as $llms_type => $llms_object) : ?>
                    <label class="aiseo-checkbox-stack">
                        <input type="checkbox" name="aiseo_llms_types[]" value="<?php echo esc_attr($llms_type); ?>" <?php checked(!is_array($llms_types_saved) || in_array($llms_type, $llms_types_saved, true)); ?> />
                        <?php echo esc_html($llms_object->labels->name); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_llms_limit"><?php esc_html_e('Maximum items per type', 'aiseo'); ?></label>
                <input type="number" name="aiseo_llms_limit" id="aiseo_llms_limit" value="<?php echo esc_attr((string) $llms_limit); ?>" min="1" max="500" class="small-text" />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_llms_extra"><?php esc_html_e('Extra text at the top of llms.txt', 'aiseo'); ?></label>
                <textarea name="aiseo_llms_extra" id="aiseo_llms_extra" class="large-text" rows="4"><?php echo esc_textarea($llms_extra); ?></textarea>
            </div>
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_llms_full" value="1" <?php checked($llms_full_enabled); ?> />
                    <?php esc_html_e('Also publish llms-full.txt with longer excerpts', 'aiseo'); ?>
                </label>
            </div>

            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_indexnow" value="1" <?php checked($indexnow_enabled); ?> />
                    <?php esc_html_e('Notify IndexNow when a post or page is published', 'aiseo'); ?>
                </label>
            </div>
            <div class="aiseo-form-group">
                <p class="aiseo-form-label"><?php esc_html_e('AI assistants (MCP)', 'aiseo'); ?></p>
                <?php if (class_exists('AISEO_Abilities') && AISEO_Abilities::is_supported()) : ?>
                    <p class="description">
                        <?php esc_html_e('This site is on WordPress 6.9 or later. Praison AI SEO registers abilities for site SEO status, listing, updating, and generating post SEO fields, IndexNow, plus sitemap and llms.txt resources. Assistants use the signed-in user’s capabilities. They do not invent search rankings.', 'aiseo'); ?>
                    </p>
                    <p class="description">
                        <code><?php echo esc_html(AISEO_Abilities::rest_list_url()); ?></code>
                    </p>
                    <?php if (AISEO_Abilities::adapter_is_active()) : ?>
                        <p class="description">
                            <?php esc_html_e('Official MCP Adapter is active. Point Claude, Cursor, or ChatGPT at:', 'aiseo'); ?>
                            <code><?php echo esc_html(AISEO_Abilities::adapter_endpoint()); ?></code>
                        </p>
                    <?php else : ?>
                        <p class="description">
                            <?php esc_html_e('Install the official WordPress MCP Adapter plugin to expose these abilities as MCP tools on /wp-json/mcp/mcp-adapter-default-server.', 'aiseo'); ?>
                        </p>
                    <?php endif; ?>
                <?php else : ?>
                    <p class="description">
                        <?php esc_html_e('WordPress 6.9 or later is required for MCP abilities. This site will register them automatically after you update WordPress.', 'aiseo'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- RSS -->
        <?php $rss_settings = (new AISEO_RSS())->get_settings(); ?>
        <div class="aiseo-form-section" id="aiseo-settings-rss">
            <h2 class="aiseo-section-title"><?php esc_html_e('RSS feed', 'aiseo'); ?></h2>
            <input type="hidden" name="aiseo_rss_form" value="1" />
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_rss_enabled" value="1" <?php checked(!empty($rss_settings['enabled'])); ?> />
                    <?php esc_html_e('Add text before or after each post in the RSS feed', 'aiseo'); ?>
                </label>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_rss_before"><?php esc_html_e('Before each post', 'aiseo'); ?></label>
                <textarea name="aiseo_rss_before" id="aiseo_rss_before" class="large-text" rows="3"><?php echo esc_textarea($rss_settings['before_content']); ?></textarea>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_rss_after"><?php esc_html_e('After each post', 'aiseo'); ?></label>
                <textarea name="aiseo_rss_after" id="aiseo_rss_after" class="large-text" rows="3"><?php echo esc_textarea($rss_settings['after_content']); ?></textarea>
                <p class="description"><?php echo esc_html__('Placeholders:', 'aiseo'); ?> %post_title%, %post_url%, %post_author%, %site_name%, %site_url%. <?php esc_html_e('The feed is unchanged until this is switched on.', 'aiseo'); ?></p>
            </div>
        </div>

        <!-- Search appearance -->
        <?php
        $robots_controller = new AISEO_Robots();
        $robots_settings = $robots_controller->get_settings();
        ?>
        <div class="aiseo-form-section" id="aiseo-settings-appearance">
            <h2 class="aiseo-section-title"><?php esc_html_e('Search appearance', 'aiseo'); ?></h2>
            <input type="hidden" name="aiseo_robots_form" value="1" />
            <p class="description"><?php esc_html_e('Search results and date archives stay out of the index unless you change that here. A category or tag can still override this on its own edit screen.', 'aiseo'); ?></p>
            <?php
            $robots_choices = array(
                'noindex_search_results' => __('Noindex search results', 'aiseo'),
                'noindex_date_archives' => __('Noindex date archives', 'aiseo'),
                'noindex_author_archives' => __('Noindex author archives', 'aiseo'),
                'noindex_categories' => __('Noindex category archives', 'aiseo'),
                'noindex_tags' => __('Noindex tag archives', 'aiseo'),
                'noindex_attachment_pages' => __('Redirect attachment pages to the parent post, or to the file', 'aiseo'),
                'noindex_empty_taxonomies' => __('Noindex empty categories and tags', 'aiseo'),
                'noindex_paginated' => __('Noindex paginated archives', 'aiseo'),
                'nofollow_external_links' => __('Add nofollow to external links in content', 'aiseo'),
                'block_ai_crawlers' => __('Block known AI training crawlers in robots.txt', 'aiseo'),
                'noindex_out_of_stock' => __('Noindex out-of-stock WooCommerce products (also omits them from the XML sitemap)', 'aiseo'),
            );
            foreach ($robots_choices as $robots_key => $robots_label) :
                ?>
                <div class="aiseo-form-group">
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr('aiseo_robots_' . $robots_key); ?>" value="1" <?php checked(!empty($robots_settings[$robots_key])); ?> />
                        <?php echo esc_html($robots_label); ?>
                    </label>
                </div>
            <?php endforeach; ?>
            <div class="aiseo-form-group aiseo-ai-crawler-list">
                <p class="aiseo-form-label"><?php esc_html_e('AI crawlers to block', 'aiseo'); ?></p>
                <p class="description"><?php esc_html_e('Used when the option above is on. Leave all ticked to block the full list, like Yoast’s AI bot blocker.', 'aiseo'); ?></p>
                <?php
                $blocked_agents = $robots_controller->get_blocked_ai_crawlers();
                foreach ($robots_controller->get_known_ai_crawlers() as $agent_name) :
                    ?>
                    <label class="aiseo-inline-check">
                        <input type="checkbox" name="aiseo_robots_ai_agents[]" value="<?php echo esc_attr($agent_name); ?>" <?php checked(in_array($agent_name, $blocked_agents, true)); ?> />
                        <?php echo esc_html($agent_name); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_robots_extra"><?php esc_html_e('Extra robots.txt rules', 'aiseo'); ?></label>
                <textarea name="aiseo_robots_extra" id="aiseo_robots_extra" class="large-text" rows="6"><?php echo esc_textarea($robots_controller->get_extra_rules()); ?></textarea>
                <p class="description"><?php esc_html_e('One directive per line: User-agent, Disallow, Allow, Crawl-delay, Sitemap or Host. WordPress still respects “Discourage search engines” in Reading settings. Valet local sites may not serve /robots.txt over HTTP.', 'aiseo'); ?></p>
            </div>
        </div>

        <!-- Security & Logging -->
        <div class="aiseo-form-section" id="aiseo-settings-security">
            <h2 class="aiseo-section-title"><?php esc_html_e('Security & Logging', 'aiseo'); ?></h2>
            
            <div class="aiseo-card aiseo-callout">
                <p>
                    <strong><?php esc_html_e('Security Status:', 'aiseo'); ?></strong>
                    <?php esc_html_e('API keys are encrypted with AES-256-CBC', 'aiseo'); ?>
                </p>
            </div>
            
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_enable_logging" value="1" <?php checked($enable_logging, '1'); ?> />
                    <?php esc_html_e('Enable Error Logging', 'aiseo'); ?>
                </label>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_log_level"><?php esc_html_e('Log Level', 'aiseo'); ?></label>
                <select name="aiseo_log_level" id="aiseo_log_level" class="regular-text">
                    <option value="error" <?php selected($log_level, 'error'); ?>>Error Only</option>
                    <option value="warning" <?php selected($log_level, 'warning'); ?>>Warning & Error</option>
                    <option value="info" <?php selected($log_level, 'info'); ?>>Info, Warning & Error</option>
                    <option value="debug" <?php selected($log_level, 'debug'); ?>>Debug (All)</option>
                </select>
            </div>
        </div>
        
        <!-- Homepage SEO Settings -->
        <div class="aiseo-form-section" id="aiseo-settings-homepage">
            <h2 class="aiseo-section-title"><?php esc_html_e('Homepage SEO', 'aiseo'); ?></h2>
            
            <?php
            $frontpage_id = get_option('page_on_front');
            $blog_id = get_option('page_for_posts');
            $show_on_front = get_option('show_on_front');
            
            if ($show_on_front === 'page' && ($frontpage_id || $blog_id)) {
                echo '<div class="aiseo-card aiseo-callout">';
                echo '<p><strong>' . esc_html__('Note:', 'aiseo') . '</strong> ';
                echo esc_html__('You are using a static front page. You can also edit SEO settings directly on:', 'aiseo') . ' ';
                if ($frontpage_id) {
                    echo '<a href="' . esc_url(get_edit_post_link($frontpage_id)) . '">' . esc_html__('Front Page', 'aiseo') . '</a>';
                }
                if ($frontpage_id && $blog_id) {
                    echo ' | ';
                }
                if ($blog_id) {
                    echo '<a href="' . esc_url(get_edit_post_link($blog_id)) . '">' . esc_html__('Blog Page', 'aiseo') . '</a>';
                }
                echo '</p></div>';
            }
            ?>
            
            <h3><?php esc_html_e('Homepage Settings', 'aiseo'); ?></h3>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_home_title"><?php esc_html_e('Homepage Title', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_home_title" 
                       id="aiseo_home_title" 
                       value="<?php echo esc_attr($homepage_settings['home_title']); ?>" 
                       class="large-text"
                       placeholder="<?php echo esc_attr(get_bloginfo('name') . ' | ' . get_bloginfo('description')); ?>" />
                <span class="aiseo-form-description"><?php esc_html_e('Custom title for your homepage. Leave empty to use default.', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_home_description"><?php esc_html_e('Homepage Meta Description', 'aiseo'); ?></label>
                <textarea name="aiseo_home_description" 
                          id="aiseo_home_description" 
                          class="large-text" 
                          rows="3"
                          placeholder="<?php esc_attr_e('Enter a compelling description for your homepage (155-160 characters recommended)', 'aiseo'); ?>"><?php echo esc_textarea($homepage_settings['home_description']); ?></textarea>
                <span class="aiseo-form-description">
                    <?php esc_html_e('Meta description for your homepage. Recommended: 155-160 characters.', 'aiseo'); ?>
                    <span class="aiseo-char-count" data-target="aiseo_home_description"><?php echo esc_html((string) strlen($homepage_settings['home_description'])); ?>/160</span>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_home_keywords"><?php esc_html_e('Homepage Meta Keywords', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_home_keywords" 
                       id="aiseo_home_keywords" 
                       value="<?php echo esc_attr($homepage_settings['home_keywords']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('keyword1, keyword2, keyword3', 'aiseo'); ?>" />
                <span class="aiseo-form-description"><?php esc_html_e('Comma-separated keywords for your homepage.', 'aiseo'); ?></span>
            </div>
            
            <h3><?php esc_html_e('Blog Page Settings', 'aiseo'); ?></h3>
            <p class="description"><?php esc_html_e('These settings apply when you have a separate blog page (Settings → Reading → Posts page).', 'aiseo'); ?></p>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_blog_title"><?php esc_html_e('Blog Page Title', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_blog_title" 
                       id="aiseo_blog_title" 
                       value="<?php echo esc_attr($homepage_settings['blog_title']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('Blog | Your Site Name', 'aiseo'); ?>" />
                <span class="aiseo-form-description"><?php esc_html_e('Custom title for your blog page.', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_blog_description"><?php esc_html_e('Blog Page Meta Description', 'aiseo'); ?></label>
                <textarea name="aiseo_blog_description" 
                          id="aiseo_blog_description" 
                          class="large-text" 
                          rows="3"
                          placeholder="<?php esc_attr_e('Enter a description for your blog page', 'aiseo'); ?>"><?php echo esc_textarea($homepage_settings['blog_description']); ?></textarea>
                <span class="aiseo-form-description"><?php esc_html_e('Meta description for your blog page.', 'aiseo'); ?></span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_blog_keywords"><?php esc_html_e('Blog Page Meta Keywords', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_blog_keywords" 
                       id="aiseo_blog_keywords" 
                       value="<?php echo esc_attr($homepage_settings['blog_keywords']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('blog, articles, news', 'aiseo'); ?>" />
                <span class="aiseo-form-description"><?php esc_html_e('Comma-separated keywords for your blog page.', 'aiseo'); ?></span>
            </div>
        </div>
        
        <!-- Title formats -->
        <?php
        $title_templates = (new AISEO_Title_Templates())->get_templates();
        $title_fields = array(
            'post_title' => __('Posts', 'aiseo'),
            'page_title' => __('Pages', 'aiseo'),
            'category_title' => __('Categories', 'aiseo'),
            'tag_title' => __('Tags', 'aiseo'),
            'taxonomy_title' => __('Custom taxonomies', 'aiseo'),
            'author_title' => __('Authors', 'aiseo'),
            'search_title' => __('Search results', 'aiseo'),
            'archive_title' => __('Archives', 'aiseo'),
            'date_title' => __('Date archives', 'aiseo'),
            '404_title' => __('404 pages', 'aiseo'),
        );
        ?>
        <div class="aiseo-form-section" id="aiseo-settings-titles">
            <h2 class="aiseo-section-title"><?php esc_html_e('Title formats', 'aiseo'); ?></h2>
            <input type="hidden" name="aiseo_title_templates_form" value="1" />
            <p class="description"><?php echo esc_html__('Placeholders:', 'aiseo'); ?> %title%, %term%, %author%, %search%, %sitename%, %sep%, %page%, %excerpt%. <?php esc_html_e('A SEO title saved on a post or category replaces that format. These formats also fill Open Graph titles when a post has no custom SEO title.', 'aiseo'); ?></p>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_title_separator"><?php esc_html_e('Separator', 'aiseo'); ?></label>
                <input type="text" name="aiseo_title_separator" id="aiseo_title_separator" value="<?php echo esc_attr($title_templates['separator']); ?>" class="small-text" />
            </div>
            <?php foreach ($title_fields as $template_key => $template_label) : ?>
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label" for="<?php echo esc_attr('aiseo_template_' . $template_key); ?>"><?php echo esc_html($template_label); ?></label>
                    <input type="text" class="large-text" name="<?php echo esc_attr('aiseo_template_' . $template_key); ?>" id="<?php echo esc_attr('aiseo_template_' . $template_key); ?>" value="<?php echo esc_attr($title_templates[$template_key]); ?>" />
                </div>
            <?php endforeach; ?>
            <?php
            $description_fields = array(
                'post_description' => __('Post descriptions', 'aiseo'),
                'page_description' => __('Page descriptions', 'aiseo'),
                'category_description' => __('Category descriptions', 'aiseo'),
                'tag_description' => __('Tag descriptions', 'aiseo'),
                'taxonomy_description' => __('Custom taxonomy descriptions', 'aiseo'),
            );
            ?>
            <p class="description"><?php esc_html_e('Used when a post or archive has no saved meta description.', 'aiseo'); ?> <?php echo esc_html__('Placeholders:', 'aiseo'); ?> %excerpt%, %content%, %term%, %term_description%, %title%, %sitename%.</p>
            <?php foreach ($description_fields as $template_key => $template_label) : ?>
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label" for="<?php echo esc_attr('aiseo_template_' . $template_key); ?>"><?php echo esc_html($template_label); ?></label>
                    <input type="text" class="large-text" name="<?php echo esc_attr('aiseo_template_' . $template_key); ?>" id="<?php echo esc_attr('aiseo_template_' . $template_key); ?>" value="<?php echo esc_attr($title_templates[$template_key]); ?>" />
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Breadcrumbs -->
        <?php $breadcrumb_settings = (new AISEO_Breadcrumbs())->get_settings(); ?>
        <div class="aiseo-form-section" id="aiseo-settings-breadcrumbs">
            <h2 class="aiseo-section-title"><?php esc_html_e('Breadcrumbs', 'aiseo'); ?></h2>
            <input type="hidden" name="aiseo_breadcrumbs_form" value="1" />
            <div class="aiseo-form-group">
                <label>
                    <input type="checkbox" name="aiseo_breadcrumbs_in_content" value="1" <?php checked(!empty($breadcrumb_settings['show_in_content'])); ?> />
                    <?php esc_html_e('Show the breadcrumb trail at the start of each post and page', 'aiseo'); ?>
                </label>
                <p class="description"><?php esc_html_e('Leave this off if the theme already prints breadcrumbs. The shortcode [aiseo_breadcrumbs] still works either way.', 'aiseo'); ?></p>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_breadcrumb_separator"><?php esc_html_e('Separator', 'aiseo'); ?></label>
                <input type="text" name="aiseo_breadcrumb_separator" id="aiseo_breadcrumb_separator" value="<?php echo esc_attr($breadcrumb_settings['separator']); ?>" class="small-text" />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_breadcrumb_home"><?php esc_html_e('Home label', 'aiseo'); ?></label>
                <input type="text" name="aiseo_breadcrumb_home" id="aiseo_breadcrumb_home" value="<?php echo esc_attr($breadcrumb_settings['home_text']); ?>" class="regular-text" />
            </div>
        </div>

        <!-- Organisation profiles -->
        <?php
        $organization_profiles = get_option('aiseo_organization_sameas', array());
        if (!is_array($organization_profiles)) {
            $organization_profiles = array();
        }
        $profile_fields = array(
            'facebook' => __('Facebook page', 'aiseo'),
            'instagram' => __('Instagram', 'aiseo'),
            'linkedin' => __('LinkedIn', 'aiseo'),
            'youtube' => __('YouTube', 'aiseo'),
            'wikipedia' => __('Wikipedia', 'aiseo'),
        );
        $twitter_site = get_option('aiseo_twitter_site', '');
        ?>
        <div class="aiseo-form-section" id="aiseo-settings-organisation">
            <h2 class="aiseo-section-title"><?php esc_html_e('Organisation profiles', 'aiseo'); ?></h2>
            <input type="hidden" name="aiseo_organization_profiles" value="1" />
            <?php
            $site_represents = get_option('aiseo_site_represents', 'organization');
            $schema_person_id = absint(get_option('aiseo_schema_person_id', 0));
            $schema_people = get_users(array(
                'capability' => 'edit_posts',
                'orderby' => 'display_name',
                'number' => 100,
            ));
            ?>
            <div class="aiseo-form-group">
                <p class="aiseo-form-label"><?php esc_html_e('This site represents', 'aiseo'); ?></p>
                <label>
                    <input type="radio" name="aiseo_site_represents" value="organization" <?php checked($site_represents, 'organization'); ?> />
                    <?php esc_html_e('An organisation', 'aiseo'); ?>
                </label>
                <br>
                <label>
                    <input type="radio" name="aiseo_site_represents" value="person" <?php checked($site_represents, 'person'); ?> />
                    <?php esc_html_e('A person', 'aiseo'); ?>
                </label>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_schema_person_id"><?php esc_html_e('Person in schema', 'aiseo'); ?></label>
                <select name="aiseo_schema_person_id" id="aiseo_schema_person_id">
                    <option value="0"><?php esc_html_e('Select a user', 'aiseo'); ?></option>
                    <?php foreach ($schema_people as $schema_person) : ?>
                        <option value="<?php echo esc_attr((string) $schema_person->ID); ?>" <?php selected($schema_person_id, (int) $schema_person->ID); ?>><?php echo esc_html($schema_person->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Used when the site represents a person. Organisation details are used until you pick someone.', 'aiseo'); ?></p>
            </div>
            <p class="description"><?php esc_html_e('These addresses are added to the organisation or person schema. Only normal web addresses are saved.', 'aiseo'); ?></p>
            <?php
            $org_details = get_option('aiseo_organization_details', array());
            if (!is_array($org_details)) {
                $org_details = array();
            }
            $org_fields = array(
                'name' => array('aiseo_org_name', __('Organisation name', 'aiseo')),
                'legal_name' => array('aiseo_org_legal_name', __('Legal name', 'aiseo')),
                'phone' => array('aiseo_org_phone', __('Phone', 'aiseo')),
                'email' => array('aiseo_org_email', __('Email', 'aiseo')),
                'street' => array('aiseo_org_street', __('Street address', 'aiseo')),
                'locality' => array('aiseo_org_locality', __('City', 'aiseo')),
                'region' => array('aiseo_org_region', __('Region', 'aiseo')),
                'postal' => array('aiseo_org_postal', __('Postcode', 'aiseo')),
                'country' => array('aiseo_org_country', __('Country', 'aiseo')),
            );
            foreach ($org_fields as $org_key => $org_field) :
                $org_id = $org_field[0];
                $org_label = $org_field[1];
                $org_type = ($org_key === 'email') ? 'email' : 'text';
                ?>
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label" for="<?php echo esc_attr($org_id); ?>"><?php echo esc_html($org_label); ?></label>
                    <input type="<?php echo esc_attr($org_type); ?>" name="<?php echo esc_attr($org_id); ?>" id="<?php echo esc_attr($org_id); ?>" value="<?php echo esc_attr(isset($org_details[$org_key]) ? $org_details[$org_key] : ''); ?>" class="regular-text" />
                </div>
            <?php endforeach; ?>
            <p class="description"><?php esc_html_e('Street and city together also mark the organisation as a LocalBusiness in schema.', 'aiseo'); ?></p>
            <?php
            $org_logo_id = isset($org_details['logo_id']) ? absint($org_details['logo_id']) : 0;
            $org_logo_src = $org_logo_id ? wp_get_attachment_image_url($org_logo_id, 'medium') : '';
            $default_og_id = absint(get_option('aiseo_default_og_image', 0));
            $default_og_src = $default_og_id ? wp_get_attachment_image_url($default_og_id, 'medium') : '';
            ?>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_org_logo_id"><?php esc_html_e('Organisation logo', 'aiseo'); ?></label>
                <input type="hidden" name="aiseo_org_logo_id" id="aiseo_org_logo_id" value="<?php echo esc_attr((string) $org_logo_id); ?>" />
                <p>
                    <button type="button" class="button aiseo-media-select" data-target="aiseo_org_logo_id" data-preview="aiseo_org_logo_preview"><?php esc_html_e('Choose logo', 'aiseo'); ?></button>
                    <button type="button" class="button aiseo-media-clear" data-target="aiseo_org_logo_id" data-preview="aiseo_org_logo_preview"><?php esc_html_e('Remove', 'aiseo'); ?></button>
                </p>
                <img id="aiseo_org_logo_preview" class="aiseo-media-preview" src="<?php echo esc_url($org_logo_src ? $org_logo_src : ''); ?>" alt=""<?php echo $org_logo_src ? '' : ' hidden'; ?> />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_default_og_image"><?php esc_html_e('Default social image', 'aiseo'); ?></label>
                <input type="hidden" name="aiseo_default_og_image" id="aiseo_default_og_image" value="<?php echo esc_attr((string) $default_og_id); ?>" />
                <p>
                    <button type="button" class="button aiseo-media-select" data-target="aiseo_default_og_image" data-preview="aiseo_default_og_preview"><?php esc_html_e('Choose image', 'aiseo'); ?></button>
                    <button type="button" class="button aiseo-media-clear" data-target="aiseo_default_og_image" data-preview="aiseo_default_og_preview"><?php esc_html_e('Remove', 'aiseo'); ?></button>
                </p>
                <p class="description"><?php esc_html_e('Used when a post has no featured image and no social image of its own.', 'aiseo'); ?></p>
                <img id="aiseo_default_og_preview" class="aiseo-media-preview" src="<?php echo esc_url($default_og_src ? $default_og_src : ''); ?>" alt=""<?php echo $default_og_src ? '' : ' hidden'; ?> />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_twitter_site"><?php esc_html_e('X username', 'aiseo'); ?></label>
                <input type="text" name="aiseo_twitter_site" id="aiseo_twitter_site" value="<?php echo esc_attr($twitter_site); ?>" class="regular-text" placeholder="@name" />
            </div>
            <?php $twitter_card_default = get_option('aiseo_twitter_card_default', ''); ?>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_twitter_card_default"><?php esc_html_e('Default X card type', 'aiseo'); ?></label>
                <select name="aiseo_twitter_card_default" id="aiseo_twitter_card_default">
                    <option value="" <?php selected($twitter_card_default, ''); ?>><?php esc_html_e('Automatic (large image when a featured or social image is set)', 'aiseo'); ?></option>
                    <option value="summary" <?php selected($twitter_card_default, 'summary'); ?>><?php esc_html_e('Summary', 'aiseo'); ?></option>
                    <option value="summary_large_image" <?php selected($twitter_card_default, 'summary_large_image'); ?>><?php esc_html_e('Summary with large image', 'aiseo'); ?></option>
                </select>
            </div>
            <?php foreach ($profile_fields as $profile_key => $profile_label) : ?>
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label" for="<?php echo esc_attr('aiseo_profile_' . $profile_key); ?>"><?php echo esc_html($profile_label); ?></label>
                    <input type="url" name="<?php echo esc_attr('aiseo_profile_' . $profile_key); ?>" id="<?php echo esc_attr('aiseo_profile_' . $profile_key); ?>" value="<?php echo esc_attr(isset($organization_profiles[$profile_key]) ? $organization_profiles[$profile_key] : ''); ?>" class="regular-text" placeholder="https://" />
                </div>
            <?php endforeach; ?>
        </div>

        <div class="aiseo-form-section" id="aiseo-settings-gsc">
            <h2 class="aiseo-section-title"><?php esc_html_e('Google Search Console', 'aiseo'); ?></h2>
            <p class="description"><?php esc_html_e('Connect your own Google Cloud OAuth client to show clicks and impressions from Search Analytics. This plugin does not invent ranking numbers.', 'aiseo'); ?></p>
            <?php
            $gsc = class_exists('AISEO_Search_Console') ? new AISEO_Search_Console() : null;
            $gsc_notice = isset($_GET['aiseo_gsc']) ? sanitize_key(wp_unslash($_GET['aiseo_gsc'])) : '';
            if ($gsc_notice === 'connected') {
                echo '<div class="notice notice-success inline"><p>' . esc_html__('Search Console connected.', 'aiseo') . '</p></div>';
            } elseif ($gsc_notice === 'disconnected') {
                echo '<div class="notice notice-success inline"><p>' . esc_html__('Search Console disconnected.', 'aiseo') . '</p></div>';
            } elseif ($gsc_notice !== '') {
                echo '<div class="notice notice-error inline"><p>' . esc_html__('Search Console connection failed. Check the client ID, secret, redirect URI, and that the property matches Search Console.', 'aiseo') . '</p></div>';
            }
            $gsc_client_id = get_option('aiseo_gsc_client_id', '');
            $gsc_secret_saved = get_option('aiseo_gsc_client_secret', '') !== '';
            $gsc_property = get_option('aiseo_gsc_property', '');
            ?>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_gsc_client_id"><?php esc_html_e('OAuth client ID', 'aiseo'); ?></label>
                <input type="text" name="aiseo_gsc_client_id" id="aiseo_gsc_client_id" value="<?php echo esc_attr(is_string($gsc_client_id) ? $gsc_client_id : ''); ?>" class="large-text" autocomplete="off" />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_gsc_client_secret"><?php esc_html_e('OAuth client secret', 'aiseo'); ?></label>
                <input type="password" name="aiseo_gsc_client_secret" id="aiseo_gsc_client_secret" value="" class="large-text" autocomplete="new-password" placeholder="<?php echo esc_attr($gsc_secret_saved ? __('Saved. Enter a new secret to replace it.', 'aiseo') : ''); ?>" />
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label" for="aiseo_gsc_property"><?php esc_html_e('Search Console property', 'aiseo'); ?></label>
                <input type="text" name="aiseo_gsc_property" id="aiseo_gsc_property" value="<?php echo esc_attr(is_string($gsc_property) && $gsc_property !== '' ? $gsc_property : home_url('/')); ?>" class="large-text" />
                <span class="aiseo-form-description"><?php esc_html_e('Must match the property in Search Console, for example https://example.com/ or sc-domain:example.com', 'aiseo'); ?></span>
            </div>
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Redirect URI', 'aiseo'); ?></label>
                <code><?php echo esc_html($gsc ? $gsc->redirect_uri() : ''); ?></code>
                <span class="aiseo-form-description"><?php esc_html_e('Add this exact URI to the Google Cloud OAuth client.', 'aiseo'); ?></span>
            </div>
            <?php if ($gsc) : ?>
                <p>
                    <?php if ($gsc->is_connected()) : ?>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=aiseo_gsc_disconnect'), 'aiseo_gsc_disconnect')); ?>"><?php esc_html_e('Disconnect Search Console', 'aiseo'); ?></a>
                    <?php else : ?>
                        <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=aiseo_gsc_connect'), 'aiseo_gsc_connect')); ?>"><?php esc_html_e('Connect Search Console', 'aiseo'); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Webmaster Verification -->
        <div class="aiseo-form-section" id="aiseo-settings-webmaster">
            <h2 class="aiseo-section-title"><?php esc_html_e('Webmaster Verification', 'aiseo'); ?></h2>
            <p class="description"><?php esc_html_e('Enter verification codes from search engines and webmaster tools. You can paste the full meta tag or just the verification code.', 'aiseo'); ?></p>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Google Search Console', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_verification_google" 
                       value="<?php echo esc_attr($verification_codes['google']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('Enter Google verification code', 'aiseo'); ?>" />
                <span class="aiseo-form-description">
                    <?php esc_html_e('Get your code from', 'aiseo'); ?> 
                    <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Bing Webmaster Tools', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_verification_bing" 
                       value="<?php echo esc_attr($verification_codes['bing']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('Enter Bing verification code', 'aiseo'); ?>" />
                <span class="aiseo-form-description">
                    <?php esc_html_e('Get your code from', 'aiseo'); ?> 
                    <a href="https://www.bing.com/webmasters" target="_blank">Bing Webmaster Tools</a>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Yandex Webmaster', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_verification_yandex" 
                       value="<?php echo esc_attr($verification_codes['yandex']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('Enter Yandex verification code', 'aiseo'); ?>" />
                <span class="aiseo-form-description">
                    <?php esc_html_e('Get your code from', 'aiseo'); ?> 
                    <a href="https://webmaster.yandex.com" target="_blank">Yandex Webmaster</a>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Pinterest', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_verification_pinterest" 
                       value="<?php echo esc_attr($verification_codes['pinterest']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('Enter Pinterest verification code', 'aiseo'); ?>" />
                <span class="aiseo-form-description">
                    <?php esc_html_e('Get your code from', 'aiseo'); ?> 
                    <a href="https://pinterest.com/settings/claim" target="_blank">Pinterest Settings</a>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Baidu Webmaster', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_verification_baidu" 
                       value="<?php echo esc_attr($verification_codes['baidu']); ?>" 
                       class="large-text"
                       placeholder="<?php esc_attr_e('Enter Baidu verification code', 'aiseo'); ?>" />
                <span class="aiseo-form-description">
                    <?php esc_html_e('Get your code from', 'aiseo'); ?> 
                    <a href="https://ziyuan.baidu.com" target="_blank">Baidu Webmaster</a>
                </span>
            </div>
        </div>
        
        <!-- Google Analytics -->
        <div class="aiseo-form-section" id="aiseo-settings-analytics">
            <h2 class="aiseo-section-title"><?php esc_html_e('Google Analytics', 'aiseo'); ?></h2>
            
            <div class="aiseo-form-group">
                <label class="aiseo-toggle">
                    <input type="checkbox" 
                           name="aiseo_ga_enabled" 
                           value="1" 
                           <?php checked($analytics_settings['enabled'], true); ?> />
                    <span class="aiseo-toggle-slider"></span>
                    <?php esc_html_e('Enable Google Analytics', 'aiseo'); ?>
                </label>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-form-label"><?php esc_html_e('Tracking ID', 'aiseo'); ?></label>
                <input type="text" 
                       name="aiseo_ga_tracking_id" 
                       value="<?php echo esc_attr($analytics_settings['tracking_id']); ?>" 
                       class="regular-text"
                       placeholder="<?php esc_attr_e('G-XXXXXXXXXX or UA-XXXXX-X', 'aiseo'); ?>" />
                <span class="aiseo-form-description">
                    <?php esc_html_e('Enter your GA4 (G-XXXXXXXXXX) or Universal Analytics (UA-XXXXX-X) tracking ID.', 'aiseo'); ?>
                </span>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-toggle">
                    <input type="checkbox" 
                           name="aiseo_ga_anonymize_ip" 
                           value="1" 
                           <?php checked($analytics_settings['anonymize_ip'], true); ?> />
                    <span class="aiseo-toggle-slider"></span>
                    <?php esc_html_e('Anonymize IP addresses (recommended for GDPR compliance)', 'aiseo'); ?>
                </label>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-toggle">
                    <input type="checkbox" 
                           name="aiseo_ga_track_logged_in" 
                           value="1" 
                           <?php checked($analytics_settings['track_logged_in'], true); ?> />
                    <span class="aiseo-toggle-slider"></span>
                    <?php esc_html_e('Track logged-in users', 'aiseo'); ?>
                </label>
            </div>
            
            <div class="aiseo-form-group">
                <label class="aiseo-toggle">
                    <input type="checkbox" 
                           name="aiseo_ga_track_admin" 
                           value="1" 
                           <?php checked($analytics_settings['track_admin'], true); ?> />
                    <span class="aiseo-toggle-slider"></span>
                    <?php esc_html_e('Track admin pages', 'aiseo'); ?>
                </label>
            </div>
        </div>
        
        <!-- Submit Button -->
        <div class="aiseo-settings-save">
            <button type="submit" name="aiseo_settings_submit" class="button button-primary button-large">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e('Save Settings', 'aiseo'); ?>
            </button>
        </div>
    </form>
</div>
<?php
}
aiseo_render_settings_view();
