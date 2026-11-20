<?php
/**
 * Advanced Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

function aiseo_render_advanced_view() {

if (isset($_POST['aiseo_cpt_form']) && current_user_can('aiseo_manage_settings') && check_admin_referer('aiseo_cpt_settings')) {
    $allowed = get_post_types(array('public' => true), 'names');
    unset($allowed['attachment']);
    $saved_types = array();
    $posted_types = isset($_POST['aiseo_cpt']) ? wp_unslash($_POST['aiseo_cpt']) : array();
    if (is_array($posted_types)) {
        foreach ($posted_types as $posted_type) {
            $posted_type = sanitize_key($posted_type);
            if (isset($allowed[$posted_type])) {
                $saved_types[] = $posted_type;
            }
        }
    }
    update_option('aiseo_sitemap_post_types', $saved_types);
    update_option('aiseo_enabled_post_types', $saved_types);

    $allowed_taxes = get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'names');
    unset($allowed_taxes['post_format']);
    $saved_taxes = array();
    $posted_taxes = isset($_POST['aiseo_sitemap_taxonomies']) ? wp_unslash($_POST['aiseo_sitemap_taxonomies']) : array();
    if (is_array($posted_taxes)) {
        foreach ($posted_taxes as $posted_tax) {
            $posted_tax = sanitize_key($posted_tax);
            if (isset($allowed_taxes[$posted_tax])) {
                $saved_taxes[] = $posted_tax;
            }
        }
    }
    update_option('aiseo_sitemap_taxonomies', $saved_taxes);
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Sitemap inclusion settings saved.', 'aiseo') . '</p></div>';
}

$post_types = get_post_types(array('public' => true), 'objects');
unset($post_types['attachment']);
$enabled_types = get_option('aiseo_sitemap_post_types', null);
if (!is_array($enabled_types)) {
    $enabled_types = array_keys($post_types);
}
$taxonomy_objects = get_taxonomies(array('public' => true, 'publicly_queryable' => true), 'objects');
unset($taxonomy_objects['post_format']);
$enabled_taxes = get_option('aiseo_sitemap_taxonomies', null);
if (!is_array($enabled_taxes)) {
    $enabled_taxes = array_keys($taxonomy_objects);
}
?>

<div class="aiseo-advanced">
    
    <!-- Custom Post Types -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Custom Post Types', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Enable SEO for Custom Post Types', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Select which post types appear in the XML sitemap and get the SEO metabox:', 'aiseo'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('aiseo_cpt_settings'); ?>
                    <input type="hidden" name="aiseo_cpt_form" value="1" />
                    <?php foreach ($post_types as $post_type): ?>
                        <div class="aiseo-form-group">
                            <label>
                                <input type="checkbox" name="aiseo_cpt[]" value="<?php echo esc_attr($post_type->name); ?>" <?php checked(in_array($post_type->name, $enabled_types, true)); ?>>
                                <strong><?php echo esc_html($post_type->label); ?></strong>
                                <span class="aiseo-muted">(<?php echo esc_html($post_type->name); ?>)</span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <h4><?php esc_html_e('Taxonomies in the XML sitemap', 'aiseo'); ?></h4>
                    <p><?php esc_html_e('Uncheck a taxonomy to omit its archive URLs from the sitemap. Noindex settings still take priority.', 'aiseo'); ?></p>
                    <?php foreach ($taxonomy_objects as $taxonomy) : ?>
                        <div class="aiseo-form-group">
                            <label>
                                <input type="checkbox" name="aiseo_sitemap_taxonomies[]" value="<?php echo esc_attr($taxonomy->name); ?>" <?php checked(in_array($taxonomy->name, $enabled_taxes, true)); ?>>
                                <strong><?php echo esc_html($taxonomy->label); ?></strong>
                                <span class="aiseo-muted">(<?php echo esc_html($taxonomy->name); ?>)</span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="button button-primary aiseo-mt-20">
                        <?php esc_html_e('Save sitemap inclusion', 'aiseo'); ?>
                    </button>
                </form>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo cpt list</code><br>
                <code>wp aiseo cpt generate --post-type=product --all</code></p>
            </div>
        </div>
    </div>
    
    <!-- Multilingual Support -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Multilingual SEO', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Multilingual Plugin Support', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('AISEO automatically detects and supports:', 'aiseo'); ?></p>
                <div class="aiseo-stat-grid">
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('WPML', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('Polylang', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('TranslatePress', 'aiseo'); ?></div>
                    </div>
                </div>
                <ul class="aiseo-list-compact aiseo-mt-20">
                    <li><?php esc_html_e('Automatic language detection', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Per-language meta tags', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Hreflang tag generation', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Hreflang on posts, pages, and translated archives', 'aiseo'); ?></li>
                </ul>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo multilingual status</code><br>
                <code>wp aiseo multilingual sync</code></p>
            </div>
        </div>
    </div>
    
    <!-- Unified Reports -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Unified Reports', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Generate SEO Reports', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Generate comprehensive SEO reports combining:', 'aiseo'); ?></p>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><?php esc_html_e('Content analysis results', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Readability scores', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Advanced SEO factors', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Historical data', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Recommendations', 'aiseo'); ?></li>
                </ul>
                <div class="aiseo-button-group aiseo-mt-20">
                    <button type="button" class="button button-primary" id="aiseo-generate-report">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Generate Full Report', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="aiseo-download-pdf">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Report (HTML)', 'aiseo'); ?>
                    </button>
                </div>
                <p style="margin-top:10px;color:#666;font-size:12px;">
                    <span class="dashicons dashicons-info" style="font-size:14px;"></span>
                    <?php esc_html_e('Note: HTML export is available. For PDF export, use WP-CLI:', 'aiseo'); ?> <code>wp aiseo report export report.pdf</code>
                </p>
                <div id="aiseo-report-results" style="margin-top:20px;"></div>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo report generate --format=json</code><br>
                <code>wp aiseo report export report.pdf</code></p>
            </div>
        </div>
    </div>
    
    <!-- Keyword Research -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Keyword Research', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Research Keywords', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('AI-powered keyword research and suggestions:', 'aiseo'); ?></p>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><?php esc_html_e('Keyword suggestions from your AI provider', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Search volume when a data provider is connected', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Difficulty scores', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Related keywords', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Long-tail variations', 'aiseo'); ?></li>
                </ul>
                <div class="aiseo-form-group aiseo-mt-20">
                    <input type="text" id="aiseo-keyword-input" class="large-text" placeholder="<?php esc_attr_e('Enter a keyword...', 'aiseo'); ?>">
                    <button type="button" class="button button-primary" id="aiseo-research-keyword" style="margin-top: 10px;">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Research Keyword', 'aiseo'); ?>
                    </button>
                </div>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo keyword research "wordpress seo"</code><br>
                <code>wp aiseo keyword suggestions "content marketing"</code></p>
                <div class="aiseo-alert aiseo-alert-info aiseo-mt-20">
                    <?php esc_html_e('Note: Requires third-party API integration for volume/difficulty data', 'aiseo'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Role manager', 'aiseo'); ?></h2>
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Who can change AISEO settings', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Administrators always have access. Grant other roles the same settings and redirect tools Rank Math exposes in its free role manager.', 'aiseo'); ?></p>
                <?php
                settings_errors('aiseo_roles');
                $aiseo_roles = class_exists('AISEO_Roles') ? new AISEO_Roles() : null;
                $wp_roles = wp_roles();
                ?>
                <?php if ($aiseo_roles && $wp_roles) : ?>
                    <form method="post">
                        <?php wp_nonce_field('aiseo_roles_settings'); ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e('Role', 'aiseo'); ?></th>
                                    <?php foreach ($aiseo_roles->capabilities() as $cap_label) : ?>
                                        <th scope="col"><?php echo esc_html($cap_label); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wp_roles->role_objects as $role_key => $role) : ?>
                                    <tr>
                                        <th scope="row"><?php echo esc_html($wp_roles->role_names[$role_key]); ?></th>
                                        <?php foreach (array_keys($aiseo_roles->capabilities()) as $cap) : ?>
                                            <td>
                                                <?php if ($role_key === 'administrator') : ?>
                                                    <input type="checkbox" checked disabled />
                                                <?php else : ?>
                                                    <input type="checkbox" name="<?php echo esc_attr('aiseo_role_caps[' . $role_key . '][]'); ?>" value="<?php echo esc_attr($cap); ?>" <?php checked($role->has_cap($cap)); ?> />
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <button type="submit" name="aiseo_roles_submit" class="button button-primary aiseo-mt-20"><?php esc_html_e('Save role access', 'aiseo'); ?></button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Content Briefs -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Content Briefs', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('AI Content Briefs', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Generate detailed content briefs with AI including target keywords, word count, structure, and key topics.', 'aiseo'); ?></p>
                
                <form id="aiseo-brief-form" class="aiseo-form" style="margin-top:20px;">
                    <div class="aiseo-form-group">
                        <label class="aiseo-form-label">
                            <?php esc_html_e('Topic or Subject', 'aiseo'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" name="topic" class="regular-text" placeholder="<?php esc_attr_e('e.g., WordPress SEO Best Practices', 'aiseo'); ?>" required>
                        <span class="aiseo-form-description"><?php esc_html_e('Enter the main topic for your content brief', 'aiseo'); ?></span>
                    </div>
                    
                    <div class="aiseo-form-group">
                        <label class="aiseo-form-label"><?php esc_html_e('Target Keyword (Optional)', 'aiseo'); ?></label>
                        <input type="text" name="keyword" class="regular-text" placeholder="<?php esc_attr_e('e.g., wordpress seo', 'aiseo'); ?>">
                        <span class="aiseo-form-description"><?php esc_html_e('Primary keyword to optimize for', 'aiseo'); ?></span>
                    </div>
                    
                    <div class="aiseo-button-group">
                        <button type="button" class="button button-primary" id="aiseo-generate-brief">
                            <span class="dashicons dashicons-edit-page"></span>
                            <?php esc_html_e('Generate Content Brief', 'aiseo'); ?>
                        </button>
                    </div>
                </form>
                
                <div id="aiseo-brief-results" class="aiseo-result-box" style="display:none; margin-top:20px;">
                    <h4><?php esc_html_e('Generated Content Brief:', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content" style="background:#f9f9f9;padding:20px;border-radius:5px;"></div>
                    <div class="aiseo-button-group" style="margin-top:15px;">
                        <button type="button" class="button button-secondary aiseo-create-post-from-brief" style="display:none;">
                            <span class="dashicons dashicons-plus"></span>
                            <?php esc_html_e('Create Post from Brief', 'aiseo'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php
}
aiseo_render_advanced_view();
