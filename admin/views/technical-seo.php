<?php
/**
 * Technical SEO Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

function aiseo_render_technical_seo_view() {
?>

<div class="aiseo-technical-seo">
    
    <!-- Redirects Manager -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Redirects Manager', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Create New Redirect', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <form id="aiseo-redirect-form">
                    <div class="aiseo-form-group">
                        <label class="aiseo-form-label" for="aiseo_from_url"><?php esc_html_e('From URL', 'aiseo'); ?></label>
                        <input type="text" id="aiseo_from_url" name="from_url" class="large-text" placeholder="/old-page" required>
                    </div>
                    <div class="aiseo-form-group">
                        <label class="aiseo-form-label" for="aiseo_to_url"><?php esc_html_e('To URL', 'aiseo'); ?></label>
                        <input type="text" id="aiseo_to_url" name="to_url" class="large-text" placeholder="/new-page">
                    </div>
                    <div class="aiseo-form-group">
                        <label class="aiseo-form-label" for="aiseo_redirect_type"><?php esc_html_e('Type', 'aiseo'); ?></label>
                        <select id="aiseo_redirect_type" name="redirect_type" class="regular-text">
                            <option value="301">301 (Permanent)</option>
                            <option value="302">302 (Temporary)</option>
                            <option value="307">307 (Temporary Redirect)</option>
                            <option value="308">308 (Permanent Redirect)</option>
                            <option value="410">410 (Gone)</option>
                        </select>
                    </div>
                    <div class="aiseo-form-group">
                        <label>
                            <input type="checkbox" name="is_regex" id="aiseo_is_regex" value="1" />
                            <?php esc_html_e('From URL is a regular expression', 'aiseo'); ?>
                        </label>
                    </div>
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php esc_html_e('Add Redirect', 'aiseo'); ?>
                    </button>
                </form>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo redirects add /old-page /new-page --type=301</code><br>
                <code>wp aiseo redirects list</code></p>
            </div>
        </div>

        <?php
        if (isset($_GET['aiseo_redirects'])) {
            $redirect_notice = sanitize_key(wp_unslash($_GET['aiseo_redirects']));
            if ($redirect_notice === 'imported') {
                $imported_count = isset($_GET['aiseo_imported']) ? absint($_GET['aiseo_imported']) : 0;
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(
                    /* translators: %d: number of redirects imported */
                    _n('%d redirect imported.', '%d redirects imported.', $imported_count, 'aiseo'),
                    $imported_count
                )) . '</p></div>';
            } elseif ($redirect_notice === 'missing') {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Choose a CSV file to import.', 'aiseo') . '</p></div>';
            } elseif ($redirect_notice === 'too_large') {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('The CSV file is larger than 512 KB.', 'aiseo') . '</p></div>';
            }
        }
        ?>
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('CSV import and export', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p class="description"><?php esc_html_e('Columns: Source URL, Target URL, Type. Type may be 301, 302, 307, 308, or 410.', 'aiseo'); ?></p>
                <p>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=aiseo_export_redirects'), 'aiseo_export_redirects')); ?>">
                        <?php esc_html_e('Export redirects', 'aiseo'); ?>
                    </a>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('aiseo_import_redirects'); ?>
                    <input type="hidden" name="action" value="aiseo_import_redirects" />
                    <div class="aiseo-form-group">
                        <label class="aiseo-form-label" for="aiseo_redirects_csv"><?php esc_html_e('Import CSV', 'aiseo'); ?></label>
                        <input type="file" name="aiseo_redirects_csv" id="aiseo_redirects_csv" accept=".csv,text/csv" />
                    </div>
                    <button type="submit" class="button"><?php esc_html_e('Import redirects', 'aiseo'); ?></button>
                </form>
            </div>
        </div>
        
        <!-- Existing Redirects List -->
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title">
                    <?php esc_html_e('Existing Redirects', 'aiseo'); ?>
                    <button type="button" class="button button-small" id="aiseo-refresh-redirects" style="margin-left: 10px;">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'aiseo'); ?>
                    </button>
                </h3>
            </div>
            <div class="aiseo-card-body">
                <div id="aiseo-redirects-list">
                    <p><?php esc_html_e('Loading redirects...', 'aiseo'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- XML Sitemap -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('XML Sitemap', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Sitemap Settings', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Your sitemap is automatically generated at:', 'aiseo'); ?></p>
                <p><a href="<?php echo esc_url(home_url('/sitemap_index.xml')); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_url(home_url('/sitemap_index.xml')); ?>
                </a></p>
                <p class="description"><?php esc_html_e('/sitemap.xml also works as an alias.', 'aiseo'); ?></p>
                
                <div class="aiseo-stat-grid aiseo-mt-20">
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('Auto-generated', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('Cached 24hrs', 'aiseo'); ?></div>
                    </div>
                    <div class="aiseo-stat-item">
                        <div class="aiseo-stat-label"><?php esc_html_e('All post types', 'aiseo'); ?></div>
                    </div>
                </div>
                
                <div class="aiseo-mt-20">
                    <button type="button" class="button button-secondary" id="aiseo-regenerate-sitemap">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Regenerate Sitemap', 'aiseo'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image SEO -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Image SEO', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Bulk Alt Text Generation', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Generate AI-powered alt text for all images:', 'aiseo'); ?></p>
                <div class="aiseo-button-group">
                    <button type="button" class="button button-primary" id="aiseo-generate-all-alt">
                        <span class="dashicons dashicons-images-alt2"></span>
                        <?php esc_html_e('Generate Alt Text for All Images', 'aiseo'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="aiseo-find-missing-alt">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e('Find Images Missing Alt Text', 'aiseo'); ?>
                    </button>
                </div>
                <div id="aiseo-image-results" style="margin-top:20px;"></div>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo image generate-alt --all</code><br>
                <code>wp aiseo image missing-alt</code></p>
            </div>
        </div>
    </div>
    
    <!-- Canonical URLs -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Canonical URLs', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Canonical URLs are automatically added to all posts and pages to prevent duplicate content issues.', 'aiseo'); ?></p>
                <div class="aiseo-alert aiseo-alert-success aiseo-mt-20">
                    <strong><?php esc_html_e('Status:', 'aiseo'); ?></strong> <?php esc_html_e('Enabled and working automatically', 'aiseo'); ?>
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php
}
aiseo_render_technical_seo_view();
