<?php
/**
 * AI Content Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_render_ai_content_view() {

$api_key = '';
if (class_exists('AISEO_Helpers')) {
    $api_key = AISEO_Helpers::get_api_key();
}
?>

<div class="aiseo-ai-content">
    
    <?php if (empty($api_key)): ?>
        <div class="aiseo-alert aiseo-alert-warning">
            <strong><?php esc_html_e('API Key Required', 'aiseo'); ?></strong>
            <?php esc_html_e('Please configure your OpenAI API key in Settings to use AI features.', 'aiseo'); ?>
        </div>
    <?php endif; ?>
    
    <!-- AI Post Creator -->
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e('AI Post Creator', 'aiseo'); ?>
                <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <form id="aiseo-create-post-form" class="aiseo-form">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label">
                        <?php esc_html_e('Topic or Subject', 'aiseo'); ?>
                        <span class="required">*</span>
                    </label>
                    <textarea name="topic" rows="3" class="large-text" placeholder="<?php esc_attr_e('e.g., 10 Best SEO Practices for 2024', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>></textarea>
                    <span class="aiseo-form-description"><?php esc_html_e('Describe what you want the post to be about', 'aiseo'); ?></span>
                </div>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Focus Keyword (Optional)', 'aiseo'); ?></label>
                    <input type="text" name="keyword" class="regular-text" placeholder="<?php esc_attr_e('e.g., SEO best practices', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>>
                    <span class="aiseo-form-description"><?php esc_html_e('Main keyword to optimize for', 'aiseo'); ?></span>
                </div>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Post Type', 'aiseo'); ?></label>
                    <select name="post_type" class="regular-text" <?php disabled(empty($api_key)); ?>>
                        <option value="post" selected><?php esc_html_e('Post', 'aiseo'); ?></option>
                        <option value="page"><?php esc_html_e('Page', 'aiseo'); ?></option>
                    </select>
                    <span class="aiseo-form-description"><?php esc_html_e('Select the type of content to create', 'aiseo'); ?></span>
                </div>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Content Length', 'aiseo'); ?></label>
                    <select name="content_length" class="regular-text" <?php disabled(empty($api_key)); ?>>
                        <option value="tiny"><?php esc_html_e('Tiny (~100 words)', 'aiseo'); ?></option>
                        <option value="brief"><?php esc_html_e('Brief (~200 words)', 'aiseo'); ?></option>
                        <option value="short"><?php esc_html_e('Short (~500 words)', 'aiseo'); ?></option>
                        <option value="medium" selected><?php esc_html_e('Medium (~1000 words)', 'aiseo'); ?></option>
                        <option value="long"><?php esc_html_e('Long (~2000 words)', 'aiseo'); ?></option>
                    </select>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary button-large aiseo-generate-post" <?php disabled(empty($api_key)); ?>>
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Generate Post', 'aiseo'); ?>
                    </button>
                </div>
            </form>
            
            <div id="aiseo-post-creator-results" class="aiseo-result-box" style="display:none; margin-top:20px;">
                <h4><?php esc_html_e('Generated Content:', 'aiseo'); ?></h4>
                <div class="aiseo-result-content" style="background:#f9f9f9;padding:20px;border-radius:5px;max-height:400px;overflow-y:auto;"></div>
                <div class="aiseo-button-group" style="margin-top:15px;">
                    <select class="aiseo-post-type-select">
                        <option value="post"><?php esc_html_e('Post', 'aiseo'); ?></option>
                        <option value="page"><?php esc_html_e('Page', 'aiseo'); ?></option>
                    </select>
                    <button type="button" class="button button-primary aiseo-create-post-from-generator">
                        <span class="dashicons dashicons-plus"></span>
                        <?php esc_html_e('Create Post', 'aiseo'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content Rewriter -->
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Content Rewriter', 'aiseo'); ?>
                <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <form id="aiseo-rewrite-form" class="aiseo-form">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label">
                        <?php esc_html_e('Content to Rewrite', 'aiseo'); ?>
                        <span class="required">*</span>
                    </label>
                    <textarea name="content" rows="6" class="large-text" placeholder="<?php esc_attr_e('Paste your content here...', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>></textarea>
                </div>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Rewrite Mode', 'aiseo'); ?></label>
                    <select name="mode" class="regular-text" <?php disabled(empty($api_key)); ?>>
                        <option value="improve"><?php esc_html_e('Improve - Enhance quality and clarity', 'aiseo'); ?></option>
                        <option value="simplify"><?php esc_html_e('Simplify - Make it easier to understand', 'aiseo'); ?></option>
                        <option value="expand"><?php esc_html_e('Expand - Add more details and depth', 'aiseo'); ?></option>
                        <option value="shorten"><?php esc_html_e('Shorten - Make it more concise', 'aiseo'); ?></option>
                        <option value="professional"><?php esc_html_e('Professional - Formal business tone', 'aiseo'); ?></option>
                        <option value="casual"><?php esc_html_e('Casual - Friendly conversational tone', 'aiseo'); ?></option>
                    </select>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-rewrite-btn" <?php disabled(empty($api_key)); ?>>
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Rewrite Content', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-rewrite-result" class="aiseo-result-box" style="display:none;">
                    <h4><?php esc_html_e('Rewritten Content:', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content"></div>
                    <div class="aiseo-button-group aiseo-mt-20">
                        <button type="button" class="button aiseo-copy-result"><?php esc_html_e('Copy to Clipboard', 'aiseo'); ?></button>
                        <select class="aiseo-post-type-select">
                            <option value="post"><?php esc_html_e('Post', 'aiseo'); ?></option>
                            <option value="page"><?php esc_html_e('Page', 'aiseo'); ?></option>
                        </select>
                        <button type="button" class="button button-primary aiseo-create-post-from-result" data-source="rewrite">
                            <span class="dashicons dashicons-plus"></span>
                            <?php esc_html_e('Create Post', 'aiseo'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Content Suggestions -->
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-lightbulb"></span>
                <?php esc_html_e('Content Suggestions', 'aiseo'); ?>
                <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <form id="aiseo-suggestions-form" class="aiseo-form">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label">
                        <?php esc_html_e('Topic or Niche', 'aiseo'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text" name="topic" class="regular-text" placeholder="<?php esc_attr_e('e.g., Digital Marketing', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>>
                    <span class="aiseo-form-description"><?php esc_html_e('Get 5 AI-powered content ideas', 'aiseo'); ?></span>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-suggestions-btn" <?php disabled(empty($api_key)); ?>>
                        <span class="dashicons dashicons-lightbulb"></span>
                        <?php esc_html_e('Get Suggestions', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-suggestions-result" class="aiseo-result-box" style="display:none;">
                    <h4><?php esc_html_e('Content Ideas:', 'aiseo'); ?></h4>
                    <ul class="aiseo-result-list"></ul>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Outline Generator -->
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('Content Outline Generator', 'aiseo'); ?>
                <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <form id="aiseo-outline-form" class="aiseo-form">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label">
                        <?php esc_html_e('Topic', 'aiseo'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text" name="topic" class="regular-text" placeholder="<?php esc_attr_e('e.g., Complete Guide to WordPress SEO', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>>
                </div>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Focus Keyword (Optional)', 'aiseo'); ?></label>
                    <input type="text" name="keyword" class="regular-text" placeholder="<?php esc_attr_e('e.g., WordPress SEO', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-outline-btn" <?php disabled(empty($api_key)); ?>>
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e('Generate Outline', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-outline-result" class="aiseo-result-box" style="display:none;">
                    <h4><?php esc_html_e('Content Outline:', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content"></div>
                    <div class="aiseo-button-group aiseo-mt-20">
                        <button type="button" class="button aiseo-copy-result"><?php esc_html_e('Copy to Clipboard', 'aiseo'); ?></button>
                        <select class="aiseo-post-type-select">
                            <option value="post"><?php esc_html_e('Post', 'aiseo'); ?></option>
                            <option value="page"><?php esc_html_e('Page', 'aiseo'); ?></option>
                        </select>
                        <button type="button" class="button button-primary aiseo-create-post-from-result" data-source="outline">
                            <span class="dashicons dashicons-plus"></span>
                            <?php esc_html_e('Create Post', 'aiseo'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- FAQ Generator -->
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-editor-help"></span>
                <?php esc_html_e('FAQ Generator', 'aiseo'); ?>
                <span class="aiseo-badge aiseo-badge-ai"><?php esc_html_e('AI-Powered', 'aiseo'); ?></span>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <form id="aiseo-faq-form" class="aiseo-form">
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label">
                        <?php esc_html_e('Content', 'aiseo'); ?>
                        <span class="required">*</span>
                    </label>
                    <textarea name="content" rows="6" class="large-text" placeholder="<?php esc_attr_e('Paste your content to generate FAQs from...', 'aiseo'); ?>" <?php disabled(empty($api_key)); ?>></textarea>
                </div>
                
                <div class="aiseo-form-group">
                    <label class="aiseo-form-label"><?php esc_html_e('Number of FAQs', 'aiseo'); ?></label>
                    <select name="count" class="regular-text" <?php disabled(empty($api_key)); ?>>
                        <option value="3">3</option>
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                    </select>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary aiseo-faq-btn" <?php disabled(empty($api_key)); ?>>
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php esc_html_e('Generate FAQs', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-faq-result" class="aiseo-result-box" style="display:none;">
                    <h4><?php esc_html_e('Generated FAQs:', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content"></div>
                    <div class="aiseo-button-group aiseo-mt-20">
                        <button type="button" class="button aiseo-copy-result"><?php esc_html_e('Copy to Clipboard', 'aiseo'); ?></button>
                        <select class="aiseo-post-type-select">
                            <option value="post"><?php esc_html_e('Post', 'aiseo'); ?></option>
                            <option value="page"><?php esc_html_e('Page', 'aiseo'); ?></option>
                        </select>
                        <button type="button" class="button button-primary aiseo-create-post-from-result" data-source="faq">
                            <span class="dashicons dashicons-plus"></span>
                            <?php esc_html_e('Create Post', 'aiseo'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
</div>

<?php
}
aiseo_render_ai_content_view();
