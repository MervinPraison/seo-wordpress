<?php
/**
 * Monitoring Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

function aiseo_render_monitoring_view() {

$monitoring = array(
    'redirects' => 0,
    'hits' => 0,
    'errors' => 0,
    'top_404s' => array(),
);
if (class_exists('AISEO_Redirects')) {
    $redirects = new AISEO_Redirects();
    $redirects->ensure_tables();
    $live = $redirects->get_statistics();
    $monitoring['redirects'] = (int) $live['total_redirects'];
    $monitoring['hits'] = (int) $live['total_redirect_hits'];
    $monitoring['errors'] = (int) $live['total_404_errors'];
    $monitoring['top_404s'] = is_array($live['top_404s']) ? $live['top_404s'] : array();
}
?>

<div class="aiseo-monitoring">
    
    <!-- Rank Tracking -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Rank Tracking', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Track Keyword Rankings', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Search positions are not guessed. Connect a search results provider before tracking ranks. Stored history is only shown when a provider has written real checks.', 'aiseo'); ?></p>
                <div class="aiseo-alert aiseo-alert-info aiseo-mt-20">
                    <?php esc_html_e('No Google Search Console, Bing, or commercial rank API is connected in this plugin yet. CLI rank commands return an error until that exists.', 'aiseo'); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Backlink Monitor -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Backlink Monitoring', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Monitor Backlinks', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('You can record a source URL and target URL, then re-fetch the source page to see whether the link is still there. Domain authority, spam score, and “new backlinks across the web” are not invented here.', 'aiseo'); ?></p>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI:', 'aiseo'); ?></strong><br>
                <code>wp aiseo backlink add --source="https://example.com" --target="<?php echo esc_html(home_url('/')); ?>"</code><br>
                <code>wp aiseo backlink check --id=ID</code></p>
            </div>
        </div>
    </div>
    
    <!-- 404 Monitor -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('404 Error Monitor', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('404 Error Log', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('404s recorded on the site are listed below. Add a 301 redirect here, or manage the full list on Technical SEO.', 'aiseo'); ?></p>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI Commands:', 'aiseo'); ?></strong><br>
                <code>wp aiseo 404 list</code><br>
                <code>wp aiseo 404 suggest-redirects</code></p>
                <?php if (!empty($monitoring['top_404s'])) : ?>
                    <table class="aiseo-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('URL', 'aiseo'); ?></th>
                                <th scope="col"><?php esc_html_e('Hits', 'aiseo'); ?></th>
                                <th scope="col"><?php esc_html_e('Last seen', 'aiseo'); ?></th>
                                <?php if (current_user_can('aiseo_manage_redirects')) : ?>
                                    <th scope="col"><?php esc_html_e('Redirect to', 'aiseo'); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($monitoring['top_404s'] as $error) :
                                $error_url = isset($error['url']) ? (string) $error['url'] : '';
                                $last_hit = isset($error['last_hit']) ? (string) $error['last_hit'] : '';
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($error_url); ?></code></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $error['hits'])); ?></td>
                                    <td><?php echo $last_hit !== '' ? esc_html($last_hit) : '—'; ?></td>
                                    <?php if (current_user_can('aiseo_manage_redirects')) : ?>
                                        <td>
                                            <label class="screen-reader-text" for="<?php echo esc_attr('aiseo-404-to-' . md5($error_url)); ?>"><?php esc_html_e('Redirect target', 'aiseo'); ?></label>
                                            <input type="text" class="regular-text aiseo-404-target" id="<?php echo esc_attr('aiseo-404-to-' . md5($error_url)); ?>" placeholder="<?php esc_attr_e('/new-page', 'aiseo'); ?>" />
                                            <button type="button" class="button button-small aiseo-404-add-redirect" data-from="<?php echo esc_attr($error_url); ?>"><?php esc_html_e('Add 301', 'aiseo'); ?></button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e('No 404s have been recorded yet.', 'aiseo'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Competitor Analysis -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Competitor Analysis', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-card-header">
                <h3 class="aiseo-card-title"><?php esc_html_e('Analyze Competitors', 'aiseo'); ?></h3>
            </div>
            <div class="aiseo-card-body">
                <p><?php esc_html_e('Competitor analysis fetches a public URL and compares on-page SEO (title, meta, headings). It does not estimate traffic, ranks, or backlink counts.', 'aiseo'); ?></p>
                <p class="aiseo-mt-20"><strong><?php esc_html_e('CLI:', 'aiseo'); ?></strong><br>
                <code>wp aiseo competitor analyze "https://example.com"</code></p>
            </div>
        </div>
    </div>
    
    <!-- Statistics Overview -->
    <div class="aiseo-form-section">
        <h2 class="aiseo-section-title"><?php esc_html_e('Monitoring Statistics', 'aiseo'); ?></h2>
        
        <div class="aiseo-card">
            <div class="aiseo-stat-grid">
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n($monitoring['redirects'])); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Redirects', 'aiseo'); ?></div>
                </div>
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n($monitoring['hits'])); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('Redirect hits', 'aiseo'); ?></div>
                </div>
                <div class="aiseo-stat-item">
                    <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n($monitoring['errors'])); ?></div>
                    <div class="aiseo-stat-label"><?php esc_html_e('404 errors', 'aiseo'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
</div>
<?php
}
aiseo_render_monitoring_view();
