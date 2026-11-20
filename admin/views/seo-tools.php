<?php
/**
 * SEO Tools Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_render_seo_tools_view() {

// Get recent posts for selection
$recent_posts = wp_get_recent_posts(array(
    'numberposts' => 20,
    'post_status' => 'publish,draft',
));
?>

<div class="aiseo-seo-tools">
    
    <!-- Meta Generation Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Meta Generation', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Generate SEO Metadata', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Select Post', 'aiseo'); ?></label>
                    <select id="aiseo-meta-post-select" class="regular-text">
                        <option value=""><?php esc_html_e('-- Select a post --', 'aiseo'); ?></option>
                        <?php foreach ($recent_posts as $post): ?>
                            <option value="<?php echo esc_attr($post['ID']); ?>">
                                <?php echo esc_html($post['post_title']); ?> (<?php echo esc_html($post['post_status']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-generate-meta" data-field="title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Generate Title', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-primary aiseo-generate-meta" data-field="description">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Generate Description', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary aiseo-generate-meta" data-field="keyword">
                        <span class="dashicons dashicons-tag"></span>
                        <?php esc_html_e('Generate Keyword', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-meta-results" class="aiseo-mt-20" style="display:none;">
                    <h4><?php esc_html_e('Generated Content:', 'aiseo'); ?></h4>
                    <div class="aiseo-alert aiseo-alert-success">
                        <div id="aiseo-meta-output"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content Analysis Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Content Analysis', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Analyze Post SEO', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Select Post to Analyze', 'aiseo'); ?></label>
                    <select id="aiseo-analyze-post-select" class="regular-text">
                        <option value=""><?php esc_html_e('-- Select a post --', 'aiseo'); ?></option>
                        <?php foreach ($recent_posts as $post): ?>
                            <option value="<?php echo esc_attr($post['ID']); ?>">
                                <?php echo esc_html($post['post_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="button" class="button button-primary aiseo-analyze-content">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e('Analyze Content', 'aiseo'); ?>
                </button>
                
                <div class="aiseo-analysis-results" style="display:none; margin-top:20px;"></div>
            </div>
        </div>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Analysis Features', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><?php esc_html_e('11 SEO metrics analyzed', 'aiseo'); ?></li>
                    <li><?php esc_html_e('6 readability metrics (Flesch, Gunning Fog, SMOG, etc.)', 'aiseo'); ?></li>
                    <li><?php esc_html_e('40+ advanced SEO factors', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Keyword density analysis', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Content length optimization', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Heading structure analysis', 'aiseo'); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Internal Linking Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Internal Linking', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('Internal Linking Suggestions', 'aiseo'); ?>
                    <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
                </h3>
            </div>
            <div class="aiseo-card-body">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Select Post', 'aiseo'); ?></label>
                    <select id="aiseo-linking-post-select" class="regular-text">
                        <option value=""><?php esc_html_e('-- Select a post --', 'aiseo'); ?></option>
                        <?php foreach ($recent_posts as $post): ?>
                            <option value="<?php echo esc_attr($post['ID']); ?>"><?php echo esc_html($post['post_title']); ?> (<?php echo esc_html($post['post_status']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-get-linking">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e('Get Linking Suggestions', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-linking-results" class="aiseo-result-box" style="display:none;">
                    <h4><?php esc_html_e('Suggested Internal Links:', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Meta Variations Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Meta Variations', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title">
                    <span class="dashicons dashicons-admin-page"></span>
                    <?php esc_html_e('Generate Multiple Variations', 'aiseo'); ?>
                    <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
                </h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Generate 5 variations with AI scoring to pick the best one', 'aiseo'); ?></p>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Select Post', 'aiseo'); ?></label>
                    <select id="aiseo-variations-post-select" class="regular-text">
                        <option value=""><?php esc_html_e('-- Select a post --', 'aiseo'); ?></option>
                        <?php foreach ($recent_posts as $post): ?>
                            <option value="<?php echo esc_attr($post['ID']); ?>"><?php echo esc_html($post['post_title']); ?> (<?php echo esc_html($post['post_status']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-get-title-variations">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Generate Title Variations', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button aiseo-get-desc-variations">
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e('Generate Description Variations', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-variations-results" class="aiseo-result-box" style="display:none;">
                    <h4><?php esc_html_e('Variations', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Schema & Tags Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Schema & Social Tags', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Schema Markup', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Automatic JSON-LD schema markup for:', 'aiseo'); ?></p>
                <div class="aiseo-stat-grid">
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('Article', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('BlogPosting', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('WebPage', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('FAQ', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('HowTo', 'aiseo'); ?></div>
                    </div>
                </div>
                <p class="aiseo-mt-20">
                    <em><?php esc_html_e('Schema is automatically added to all posts and pages', 'aiseo'); ?></em>
                </p>
            </div>
        </div>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Social Media Tags', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Automatic Open Graph and Twitter Card tags:', 'aiseo'); ?></p>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li><?php esc_html_e('og:title, og:description, og:image', 'aiseo'); ?></li>
                    <li><?php esc_html_e('twitter:card, twitter:title, twitter:description', 'aiseo'); ?></li>
                    <li><?php esc_html_e('Automatic image optimization', 'aiseo'); ?></li>
                </ul>
            </div>
        </div>
    </div>

<?php
}
aiseo_render_seo_tools_view();
