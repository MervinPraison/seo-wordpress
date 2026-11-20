<?php
/**
 * Bulk Operations Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

function aiseo_render_bulk_operations_view() {

// Get all posts for bulk selection
$bulk_args = array(
    'numberposts' => 100,
    'post_type' => array('post', 'page'),
    'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
);
if (!current_user_can('edit_others_posts')) {
    $bulk_args['author'] = get_current_user_id();
}
$all_posts = array_values(array_filter(get_posts($bulk_args), function ($post) {
    return current_user_can('edit_post', $post->ID);
}));
?>

<div class="aiseo-bulk-operations">
    
    <!-- Bulk Editor Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Bulk Editor', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Select Posts for Bulk Editing', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <div class="aiseo-form-group">
                    <label>
                        <input type="checkbox" id="aiseo-select-all-posts"> 
                        <?php esc_html_e('Select All Posts', 'aiseo'); ?>
                    </label>
                </div>
                
                <div class="aiseo-scroll-box">
                    <?php foreach ($all_posts as $post): ?>
                        <label class="aiseo-checkbox-stack">
                            <input type="checkbox" class="aiseo-bulk-post" value="<?php echo esc_attr($post->ID); ?>">
                            <?php echo esc_html($post->post_title); ?>
                            <span class="aiseo-muted">(<?php echo esc_html($post->post_type); ?> - <?php echo esc_html($post->post_status); ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary" id="aiseo-bulk-generate-titles">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Generate Titles for Selected', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="aiseo-bulk-generate-descriptions">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Generate Descriptions for Selected', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="aiseo-bulk-analyze">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e('Analyze Selected Posts', 'aiseo'); ?>
                    </button>
                </div>
                
                <div id="aiseo-bulk-progress" class="aiseo-hidden-block">
                    <h4><?php esc_html_e('Progress:', 'aiseo'); ?></h4>
                    <div class="aiseo-progress-bar">
                        <div class="aiseo-progress-fill" style="width: 0%;">0%</div>
                    </div>
                    <p id="aiseo-bulk-status"></p>
                </div>
                
                <div id="aiseo-bulk-results" class="aiseo-hidden-block">
                    <h4><?php esc_html_e('Review Generated Content:', 'aiseo'); ?></h4>
                    <div class="aiseo-button-group aiseo-mb-15">
                        <button type="button" class="button" id="aiseo-select-all-results"><?php esc_html_e('Select All', 'aiseo'); ?></button>
                        <button type="button" class="button" id="aiseo-clear-all-results"><?php esc_html_e('Clear All', 'aiseo'); ?></button>
                        <button type="button" class="button button-primary" id="aiseo-save-approved">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Save Approved Changes', 'aiseo'); ?>
                        </button>
                    </div>
                    <div id="aiseo-results-list" class="aiseo-results-list"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Import/Export Section -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Import / Export', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Import from Other Plugins', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Import SEO data from:', 'aiseo'); ?></p>
                <div class="aiseo-button-group">
                    <button type="button" class="button button-secondary aiseo-import-btn" data-source="yoast">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Import from Yoast SEO', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary aiseo-import-btn" data-source="rankmath">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Import from Rank Math', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary aiseo-import-btn" data-source="aioseo">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Import from All in One SEO', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary aiseo-import-btn" data-source="seopress">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Import from SEOPress', 'aiseo'); ?>
                    </button>
                </div>
                <div id="aiseo-import-result" class="aiseo-result-box aiseo-hidden-block">
                    <h4><?php esc_html_e('Import Results:', 'aiseo'); ?></h4>
                    <div class="aiseo-result-content"></div>
                </div>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Command:', 'aiseo'); ?></strong><br>
                <code>wp aiseo import --from=yoast</code></p>
            </div>
        </div>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Export Data', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Export your SEO data:', 'aiseo'); ?></p>
                <div class="aiseo-button-group">
                    <button type="button" class="button button-secondary aiseo-export-btn" data-format="json">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Export to JSON', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary aiseo-export-btn" data-format="csv">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Export to CSV', 'aiseo'); ?>
                    </button>
                </div>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo export --format=json > export.json</code><br>
                <code>wp aiseo export --format=csv > export.csv</code></p>
            </div>
        </div>
    </div>
    
    <!-- Bulk Statistics -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Bulk Statistics', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-stat-grid">
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html((string) count($all_posts)); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Total Posts', 'aiseo'); ?></div>
                </div>
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html((string) count(array_filter($all_posts, function($p) { return $p->post_status === 'publish'; }))); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Published', 'aiseo'); ?></div>
                </div>
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html((string) count(array_filter($all_posts, function($p) { return $p->post_type === 'post'; }))); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Posts', 'aiseo'); ?></div>
                </div>
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html((string) count(array_filter($all_posts, function($p) { return $p->post_type === 'page'; }))); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Pages', 'aiseo'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php
}
aiseo_render_bulk_operations_view();
