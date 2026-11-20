<?php
/**
 * Dashboard Tab View
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Duplicate titles and missing descriptions from published posts.
 *
 * @param string[] $post_types Public post types.
 * @return array{checked:int,duplicates:array<int,array{title:string,ids:int[]}>,missing_description:int[],missing_keyword:int[]}
 */
function aiseo_collect_dashboard_audit($post_types) {
    $cached = get_transient('aiseo_dashboard_audit');
    if (is_array($cached) && isset($cached['checked'], $cached['duplicates'], $cached['missing_description'], $cached['missing_keyword'])) {
        return $cached;
    }
    $posts = get_posts(array(
        'post_type' => $post_types ? $post_types : array('post'),
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'orderby' => 'modified',
        'order' => 'DESC',
        'fields' => 'ids',
        'no_found_rows' => true,
        'has_password' => false,
    ));
    $titles = array();
    $missing_description = array();
    $missing_keyword = array();
    foreach ($posts as $post_id) {
        $post_id = absint($post_id);
        if (!$post_id) {
            continue;
        }
        $title = get_post_meta($post_id, '_aiseo_meta_title', true);
        if (!is_string($title) || $title === '') {
            $title = get_the_title($post_id);
        }
        $key = strtolower(trim(wp_strip_all_tags((string) $title)));
        if ($key !== '') {
            if (!isset($titles[$key])) {
                $titles[$key] = array();
            }
            $titles[$key][] = $post_id;
        }
        $description = get_post_meta($post_id, '_aiseo_meta_description', true);
        if (!is_string($description) || $description === '') {
            $missing_description[] = $post_id;
        }
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        if (!is_string($keyword) || $keyword === '') {
            $missing_keyword[] = $post_id;
        }
    }
    $duplicates = array();
    foreach ($titles as $title_key => $ids) {
        if (count($ids) > 1) {
            $duplicates[] = array(
                'title' => $title_key,
                'ids' => $ids,
            );
        }
    }
    $audit = array(
        'checked' => count($posts),
        'duplicates' => $duplicates,
        'missing_description' => $missing_description,
        'missing_keyword' => $missing_keyword,
    );
    set_transient('aiseo_dashboard_audit', $audit, 5 * MINUTE_IN_SECONDS);
    return $audit;
}

function aiseo_render_dashboard_view() {

$api_key = AISEO_Helpers::get_api_key();
$model = get_option('aiseo_api_model', '');
if ($model === '' || $model === false) {
    $model = get_option('aiseo_model', 'gpt-4o-mini');
}

$recent_types = get_post_types(array('public' => true), 'names');
unset($recent_types['attachment']);
$recent_types = array_values($recent_types);

$counts = get_transient('aiseo_dashboard_counts');
if (!is_array($counts) || !isset($counts['total'], $counts['missing_title'])) {
    $count_args = array(
        'post_type' => $recent_types ? $recent_types : array('post'),
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => false,
    );
    $total_query = new WP_Query($count_args);
    $metadata_query = new WP_Query(array_merge($count_args, array(
        'meta_query' => array(
            array(
                'key' => '_aiseo_meta_title',
                'compare' => 'EXISTS',
            ),
        ),
    )));
    $keyword_query = new WP_Query(array_merge($count_args, array(
        'meta_query' => array(
            array(
                'key' => '_aiseo_focus_keyword',
                'compare' => 'EXISTS',
            ),
        ),
    )));
    $total = (int) $total_query->found_posts;
    $with_title = (int) $metadata_query->found_posts;
    $counts = array(
        'total' => $total,
        'metadata' => $with_title,
        'analyzed' => (int) $keyword_query->found_posts,
        'missing_title' => max(0, $total - $with_title),
    );
    wp_reset_postdata();
    set_transient('aiseo_dashboard_counts', $counts, 5 * MINUTE_IN_SECONDS);
}

$ai_posts_created = 0;
if (class_exists('AISEO_Post_Creator')) {
    $creator = new AISEO_Post_Creator();
    $stats = $creator->get_statistics();
    $ai_posts_created = isset($stats['total_ai_posts']) ? (int) $stats['total_ai_posts'] : 0;
}

$recent_args = array(
    'post_type' => $recent_types,
    'posts_per_page' => 5,
    'orderby' => 'modified',
    'order' => 'DESC',
    'post_status' => current_user_can('edit_others_posts')
        ? array('publish', 'draft', 'pending', 'future', 'private')
        : array('publish', 'draft', 'pending'),
);
if (!current_user_can('edit_others_posts')) {
    $recent_args['author'] = get_current_user_id();
}
$recent_posts = get_posts($recent_args);

$badge_for_status = static function ($status) {
    if ($status === 'publish') {
        return 'success';
    }
    if ($status === 'trash') {
        return 'error';
    }
    if ($status === 'pending') {
        return 'warning';
    }
    return 'info';
};

$feature_on = static function ($option) {
    return !in_array(get_option($option, '1'), array(false, 0, '0', ''), true);
};
$features = array(
    array(
        'label' => __('Schema markup', 'aiseo'),
        'on' => $feature_on('aiseo_enable_schema'),
        'url' => '',
    ),
    array(
        'label' => __('XML sitemap', 'aiseo'),
        'on' => $feature_on('aiseo_enable_sitemap'),
        'url' => home_url('/sitemap_index.xml'),
    ),
    array(
        'label' => __('llms.txt', 'aiseo'),
        'on' => $feature_on('aiseo_enable_llms'),
        'url' => home_url('/llms.txt'),
    ),
    array(
        'label' => __('IndexNow', 'aiseo'),
        'on' => $feature_on('aiseo_enable_indexnow'),
        'url' => '',
    ),
    array(
        'label' => __('Schema map', 'aiseo'),
        'on' => $feature_on('aiseo_enable_schema') && !in_array(get_option('aiseo_enable_schema_aggregation', '0'), array(false, 0, '0', ''), true),
        'url' => home_url('/aiseo-schema.json'),
    ),
    array(
        'label' => __('llms-full.txt', 'aiseo'),
        'on' => $feature_on('aiseo_enable_llms') && !in_array(get_option('aiseo_enable_llms_full', '0'), array(false, 0, '0', ''), true),
        'url' => home_url('/llms-full.txt'),
    ),
    array(
        'label' => __('MCP abilities', 'aiseo'),
        'on' => class_exists('AISEO_Abilities') && AISEO_Abilities::is_supported(),
        'url' => admin_url('admin.php?page=aiseo&tab=settings'),
    ),
);
if (class_exists('WooCommerce')) {
    $features[] = array(
        'label' => __('WooCommerce product schema', 'aiseo'),
        'on' => $feature_on('aiseo_enable_schema'),
        'url' => '',
    );
}

$site_is_public = (get_option('blog_public') !== '0');
$setup_checks = array(
    array(
        'label' => __('Search engines allowed', 'aiseo'),
        'pass' => $site_is_public,
        'href' => admin_url('options-reading.php'),
        'cta' => __('Allow search engines in Reading settings', 'aiseo'),
    ),
    array(
        'label' => __('Schema markup', 'aiseo'),
        'pass' => $feature_on('aiseo_enable_schema'),
        'href' => admin_url('admin.php?page=aiseo&tab=settings'),
        'cta' => __('Turn on schema markup', 'aiseo'),
    ),
    array(
        'label' => __('XML sitemap', 'aiseo'),
        'pass' => $feature_on('aiseo_enable_sitemap'),
        'href' => admin_url('admin.php?page=aiseo&tab=settings'),
        'cta' => __('Turn on the XML sitemap', 'aiseo'),
    ),
    array(
        'label' => __('llms.txt', 'aiseo'),
        'pass' => $feature_on('aiseo_enable_llms'),
        'href' => admin_url('admin.php?page=aiseo&tab=settings'),
        'cta' => __('Turn on llms.txt', 'aiseo'),
    ),
    array(
        'label' => __('IndexNow', 'aiseo'),
        'pass' => $feature_on('aiseo_enable_indexnow'),
        'href' => admin_url('admin.php?page=aiseo&tab=settings'),
        'cta' => __('Turn on IndexNow', 'aiseo'),
    ),
    array(
        'label' => __('Search Console', 'aiseo'),
        'pass' => class_exists('AISEO_Search_Console') && (new AISEO_Search_Console())->is_connected(),
        'href' => admin_url('admin.php?page=aiseo&tab=settings'),
        'cta' => __('Connect Search Console', 'aiseo'),
    ),
);
$setup_pass = 0;
foreach ($setup_checks as $setup_check) {
    if (!empty($setup_check['pass'])) {
        $setup_pass++;
    }
}
$setup_score = (int) round(100 * $setup_pass / max(1, count($setup_checks)));
$site_audit = aiseo_collect_dashboard_audit($recent_types);
$duplicate_count = 0;
foreach ($site_audit['duplicates'] as $duplicate_group) {
    $duplicate_count += count($duplicate_group['ids']);
}
$missing_description_count = count($site_audit['missing_description']);
$missing_keyword_count = count($site_audit['missing_keyword']);
$gsc = class_exists('AISEO_Search_Console') ? new AISEO_Search_Console() : null;
$gsc_summary = null;
$gsc_error = '';
if ($gsc && $gsc->is_connected()) {
    $gsc_result = $gsc->summary();
    if (is_wp_error($gsc_result)) {
        $gsc_error = $gsc_result->get_error_message();
    } else {
        $gsc_summary = $gsc_result;
    }
}
?>

<div class="aiseo-dashboard">
    <?php if (!$site_is_public) : ?>
        <div class="notice notice-error inline aiseo-search-engines-notice">
            <p>
                <strong><?php esc_html_e('Search engines are discouraged.', 'aiseo'); ?></strong>
                <?php esc_html_e('WordPress is set not to let search engines index this site. Nothing this plugin publishes will change that until you allow them in Reading settings.', 'aiseo'); ?>
                <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>"><?php esc_html_e('Open Reading settings', 'aiseo'); ?></a>
            </p>
        </div>
    <?php endif; ?>

    <section class="aiseo-dashboard-hero">
        <div class="aiseo-hero-score" aria-label="<?php echo esc_attr(sprintf(/* translators: %d: percentage of setup checks that are on */ __('Setup score %d percent', 'aiseo'), $setup_score)); ?>">
            <div class="aiseo-hero-score-value"><?php echo esc_html((string) $setup_score); ?></div>
            <div class="aiseo-hero-score-label"><?php esc_html_e('Setup', 'aiseo'); ?></div>
        </div>
        <div class="aiseo-hero-copy">
            <h2><?php esc_html_e('Praison AI SEO', 'aiseo'); ?></h2>
            <p><?php echo esc_html(sprintf(
                /* translators: 1: passing checks, 2: total checks */
                __('%1$d of %2$d site setup checks are in place. This is configuration, not a search ranking.', 'aiseo'),
                $setup_pass,
                count($setup_checks)
            )); ?></p>
            <ul class="aiseo-hero-checks">
                <?php foreach ($setup_checks as $setup_check) : ?>
                    <li class="<?php echo esc_attr(!empty($setup_check['pass']) ? 'is-on' : 'is-off'); ?>">
                        <span class="dashicons <?php echo esc_attr(!empty($setup_check['pass']) ? 'dashicons-yes-alt' : 'dashicons-warning'); ?>" aria-hidden="true"></span>
                        <?php echo esc_html($setup_check['label']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php
            $setup_next = null;
            foreach ($setup_checks as $setup_check) {
                if (empty($setup_check['pass'])) {
                    $setup_next = $setup_check;
                    break;
                }
            }
            ?>
            <?php
            $wizard_done = class_exists('AISEO_Setup') ? (new AISEO_Setup())->is_complete() : true;
            if (!$wizard_done && current_user_can('aiseo_manage_settings')) :
                ?>
                <a class="button button-primary aiseo-hero-cta" href="<?php echo esc_url(admin_url('admin.php?page=aiseo-setup')); ?>"><?php esc_html_e('Open setup wizard', 'aiseo'); ?></a>
            <?php elseif ($setup_next) : ?>
                <a class="button button-primary aiseo-hero-cta" href="<?php echo esc_url($setup_next['href']); ?>"><?php echo esc_html($setup_next['cta']); ?></a>
            <?php else : ?>
                <a class="button aiseo-hero-cta" href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings')); ?>"><?php esc_html_e('Review settings', 'aiseo'); ?></a>
                <?php if (current_user_can('aiseo_manage_settings')) : ?>
                    <a class="button aiseo-hero-cta" href="<?php echo esc_url(admin_url('admin.php?page=aiseo-setup')); ?>"><?php esc_html_e('Run setup wizard', 'aiseo'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="aiseo-stat-grid aiseo-hero-stats">
            <div class="aiseo-stat-item">
                <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n($counts['analyzed'])); ?></div>
                <div class="aiseo-stat-label"><?php esc_html_e('Focus keyword', 'aiseo'); ?></div>
            </div>
            <div class="aiseo-stat-item">
                <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n($counts['metadata'])); ?></div>
                <div class="aiseo-stat-label"><?php esc_html_e('SEO title', 'aiseo'); ?></div>
            </div>
            <div class="aiseo-stat-item<?php echo esc_attr(!empty($counts['missing_title']) ? ' is-warn' : ''); ?>">
                <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n(isset($counts['missing_title']) ? $counts['missing_title'] : 0)); ?></div>
                <div class="aiseo-stat-label"><?php esc_html_e('Missing title', 'aiseo'); ?></div>
            </div>
            <div class="aiseo-stat-item<?php echo esc_attr($missing_description_count ? ' is-warn' : ''); ?>">
                <div class="aiseo-stat-value"><?php echo esc_html(number_format_i18n($missing_description_count)); ?></div>
                <div class="aiseo-stat-label"><?php esc_html_e('Missing description', 'aiseo'); ?></div>
            </div>
        </div>
    </section>

    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php esc_html_e('Site features', 'aiseo'); ?>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <ul class="aiseo-seo-checks">
                <?php foreach ($features as $feature) : ?>
                    <li>
                        <span class="aiseo-badge aiseo-badge-<?php echo esc_attr($feature['on'] ? 'success' : 'warning'); ?>">
                            <?php echo esc_html($feature['on'] ? __('On', 'aiseo') : __('Off', 'aiseo')); ?>
                        </span>
                        <?php if ($feature['on'] && $feature['url'] !== '') : ?>
                            <a href="<?php echo esc_url($feature['url']); ?>"><?php echo esc_html($feature['label']); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($feature['label']); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($feature_on('aiseo_enable_sitemap')) : ?>
                <?php
                $sitemap_links = array(
                    __('Index', 'aiseo') => home_url('/sitemap_index.xml'),
                );
                if (class_exists('AISEO_Sitemap')) {
                    foreach ((new AISEO_Sitemap())->get_populated_post_types() as $sitemap_type) {
                        $sitemap_object = get_post_type_object($sitemap_type);
                        $sitemap_label = ($sitemap_object && !empty($sitemap_object->labels->name)) ? $sitemap_object->labels->name : $sitemap_type;
                        $sitemap_links[$sitemap_label] = home_url('/' . $sitemap_type . '-sitemap.xml');
                    }
                }
                $sitemap_links[__('Categories', 'aiseo')] = home_url('/category-sitemap.xml');
                $sitemap_links[__('Tags', 'aiseo')] = home_url('/post_tag-sitemap.xml');
                $author_robots = class_exists('AISEO_Robots') ? (new AISEO_Robots())->get_settings() : array();
                if (empty($author_robots['noindex_author_archives'])) {
                    $sitemap_links[__('Authors', 'aiseo')] = home_url('/author-sitemap.xml');
                }
                if (class_exists('AISEO_Sitemap') && (new AISEO_Sitemap())->news_post_type() !== '') {
                    $sitemap_links[__('Google News', 'aiseo')] = home_url('/google-news-sitemap.xml');
                }
                $sitemap_links[__('Videos', 'aiseo')] = home_url('/video-sitemap.xml');
                $sitemap_links[__('Images', 'aiseo')] = home_url('/image-sitemap.xml');
                ?>
                <ul class="aiseo-sitemap-links">
                    <?php foreach ($sitemap_links as $sitemap_label => $sitemap_url) : ?>
                        <li><a href="<?php echo esc_url($sitemap_url); ?>"><?php echo esc_html($sitemap_label); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="description aiseo-card-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings')); ?>"><?php esc_html_e('Change these in Settings', 'aiseo'); ?></a>
            </p>
            <p class="description">
                <?php esc_html_e('Breadcrumbs:', 'aiseo'); ?>
                <code>[aiseo_breadcrumbs]</code>
                <?php esc_html_e('or the Gutenberg block “AISEO Breadcrumbs”.', 'aiseo'); ?>
            </p>
        </div>
    </div>

    <div class="aiseo-dashboard-grid">

        <?php
        $robots_settings = class_exists('AISEO_Robots') ? (new AISEO_Robots())->get_settings() : array();
        $breadcrumb_settings = class_exists('AISEO_Breadcrumbs') ? (new AISEO_Breadcrumbs())->get_settings() : array();
        $home_settings = class_exists('AISEO_Homepage_SEO') ? (new AISEO_Homepage_SEO())->get_settings() : array();
        $indexing_rows = array(
            array(
                'label' => __('Search engines', 'aiseo'),
                'value' => $site_is_public ? __('Allowed', 'aiseo') : __('Discouraged', 'aiseo'),
                'warn' => !$site_is_public,
            ),
            array(
                'label' => __('Search results', 'aiseo'),
                'value' => !empty($robots_settings['noindex_search_results']) ? __('Noindex', 'aiseo') : __('Index', 'aiseo'),
                'warn' => empty($robots_settings['noindex_search_results']),
            ),
            array(
                'label' => __('Date archives', 'aiseo'),
                'value' => !empty($robots_settings['noindex_date_archives']) ? __('Noindex', 'aiseo') : __('Index', 'aiseo'),
                'warn' => false,
            ),
            array(
                'label' => __('Attachment pages', 'aiseo'),
                'value' => !empty($robots_settings['noindex_attachment_pages']) ? __('Redirected', 'aiseo') : __('Left in place', 'aiseo'),
                'warn' => empty($robots_settings['noindex_attachment_pages']),
            ),
            array(
                'label' => __('AI crawlers', 'aiseo'),
                'value' => !empty($robots_settings['block_ai_crawlers']) ? __('Blocked', 'aiseo') : __('Allowed', 'aiseo'),
                'warn' => false,
            ),
            array(
                'label' => __('Breadcrumbs in posts', 'aiseo'),
                'value' => !empty($breadcrumb_settings['show_in_content']) ? __('Shown', 'aiseo') : __('Shortcode only', 'aiseo'),
                'warn' => false,
            ),
            array(
                'label' => __('Homepage description', 'aiseo'),
                'value' => !empty($home_settings['home_description']) ? __('Set', 'aiseo') : __('Site tagline', 'aiseo'),
                'warn' => empty($home_settings['home_description']),
            ),
            array(
                'label' => __('Social tags', 'aiseo'),
                'value' => $feature_on('aiseo_enable_social_tags') ? __('On', 'aiseo') : __('Off', 'aiseo'),
                'warn' => false,
            ),
        );
        ?>
        <div class="aiseo-card aiseo-widget">
            <h3 class="aiseo-widget-title">
                <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                <?php esc_html_e('Indexing', 'aiseo'); ?>
            </h3>
            <ul class="aiseo-index-list">
                <?php foreach ($indexing_rows as $indexing_row) : ?>
                    <li>
                        <span class="aiseo-index-label"><?php echo esc_html($indexing_row['label']); ?></span>
                        <span class="<?php echo esc_attr(!empty($indexing_row['warn']) ? 'aiseo-index-warn' : ''); ?>"><?php echo esc_html($indexing_row['value']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="description">
                <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>"><?php esc_html_e('Reading settings', 'aiseo'); ?></a>
                ·
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings')); ?>"><?php esc_html_e('Change indexing in Settings', 'aiseo'); ?></a>
            </p>
        </div>

        <div class="aiseo-card aiseo-widget">
            <h3 class="aiseo-widget-title">
                <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                <?php esc_html_e('AI writing', 'aiseo'); ?>
            </h3>
            <div class="aiseo-widget-body">
                <?php if (!empty($api_key)) : ?>
                    <p class="aiseo-alert aiseo-alert-success">
                        <strong><?php esc_html_e('OpenAI key saved', 'aiseo'); ?></strong><br>
                        <?php esc_html_e('Model:', 'aiseo'); ?> <code><?php echo esc_html($model); ?></code>
                    </p>
                <?php else : ?>
                    <p class="aiseo-alert aiseo-alert-warning">
                        <strong><?php esc_html_e('No OpenAI key', 'aiseo'); ?></strong><br>
                        <?php esc_html_e('Optional. Core SEO works without it. Add a key in Settings only for AI writing.', 'aiseo'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div class="aiseo-dashboard-split">
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-google" aria-hidden="true"></span>
                <?php esc_html_e('Search Console', 'aiseo'); ?>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <?php if (!$gsc || !$gsc->is_connected()) : ?>
                <p><?php esc_html_e('Connect Google Search Console in Settings to show clicks and impressions from your property. This plugin does not estimate rankings.', 'aiseo'); ?></p>
                <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings')); ?>"><?php esc_html_e('Open Settings', 'aiseo'); ?></a></p>
            <?php elseif ($gsc_error !== '') : ?>
                <p class="aiseo-muted"><?php echo esc_html($gsc_error); ?></p>
            <?php elseif (is_array($gsc_summary)) : ?>
                <p class="aiseo-muted">
                    <?php echo esc_html(sprintf(
                        /* translators: 1: start date, 2: end date */
                        __('Google Search Analytics from %1$s to %2$s. These figures come from Google, not from this plugin.', 'aiseo'),
                        $gsc_summary['start'],
                        $gsc_summary['end']
                    )); ?>
                </p>
                <div class="aiseo-audit-summary">
                    <div class="aiseo-audit-chip">
                        <strong><?php echo esc_html(number_format_i18n((int) $gsc_summary['clicks'])); ?></strong>
                        <span><?php esc_html_e('Clicks', 'aiseo'); ?></span>
                    </div>
                    <div class="aiseo-audit-chip">
                        <strong><?php echo esc_html(number_format_i18n((int) $gsc_summary['impressions'])); ?></strong>
                        <span><?php esc_html_e('Impressions', 'aiseo'); ?></span>
                    </div>
                    <div class="aiseo-audit-chip">
                        <strong><?php echo esc_html(number_format_i18n($gsc_summary['ctr'] * 100, 1)); ?>%</strong>
                        <span><?php esc_html_e('CTR', 'aiseo'); ?></span>
                    </div>
                    <div class="aiseo-audit-chip">
                        <strong><?php echo esc_html(number_format_i18n($gsc_summary['position'], 1)); ?></strong>
                        <span><?php esc_html_e('Average position', 'aiseo'); ?></span>
                    </div>
                </div>
                <?php
                $gsc_queries = isset($gsc_summary['queries']) && is_array($gsc_summary['queries']) ? $gsc_summary['queries'] : array();
                $gsc_pages = isset($gsc_summary['pages']) && is_array($gsc_summary['pages']) ? $gsc_summary['pages'] : array();
                if ($gsc_queries) :
                    ?>
                    <h4><?php esc_html_e('Top queries', 'aiseo'); ?></h4>
                    <table class="aiseo-gsc-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Query', 'aiseo'); ?></th>
                                <th><?php esc_html_e('Clicks', 'aiseo'); ?></th>
                                <th><?php esc_html_e('Impressions', 'aiseo'); ?></th>
                                <th><?php esc_html_e('Position', 'aiseo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gsc_queries as $gsc_row) : ?>
                                <tr>
                                    <td><?php echo esc_html($gsc_row['label']); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $gsc_row['clicks'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $gsc_row['impressions'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((float) $gsc_row['position'], 1)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php if ($gsc_pages) : ?>
                    <h4><?php esc_html_e('Top pages', 'aiseo'); ?></h4>
                    <table class="aiseo-gsc-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Page', 'aiseo'); ?></th>
                                <th><?php esc_html_e('Clicks', 'aiseo'); ?></th>
                                <th><?php esc_html_e('Impressions', 'aiseo'); ?></th>
                                <th><?php esc_html_e('Position', 'aiseo'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gsc_pages as $gsc_row) : ?>
                                <tr>
                                    <td><?php echo esc_html($gsc_row['label']); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $gsc_row['clicks'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $gsc_row['impressions'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((float) $gsc_row['position'], 1)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <p>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=aiseo_gsc_refresh'), 'aiseo_gsc_refresh')); ?>"><?php esc_html_e('Refresh from Google', 'aiseo'); ?></a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
                <?php esc_html_e('Site audit', 'aiseo'); ?>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <p class="aiseo-muted">
                <?php echo esc_html(sprintf(
                    /* translators: %d: number of published posts checked */
                    __('Checked the %d most recently updated published posts. Counts are from your content, not from search engines.', 'aiseo'),
                    (int) $site_audit['checked']
                )); ?>
            </p>
            <div class="aiseo-audit-summary">
                <div class="aiseo-audit-chip<?php echo $duplicate_count ? ' is-alert' : ''; ?>">
                    <strong><?php echo esc_html((string) $duplicate_count); ?></strong>
                    <span><?php esc_html_e('Duplicate titles', 'aiseo'); ?></span>
                </div>
                <div class="aiseo-audit-chip<?php echo $missing_description_count ? ' is-alert' : ''; ?>">
                    <strong><?php echo esc_html((string) $missing_description_count); ?></strong>
                    <span><?php esc_html_e('Missing descriptions', 'aiseo'); ?></span>
                </div>
                <div class="aiseo-audit-chip<?php echo $missing_keyword_count ? ' is-alert' : ''; ?>">
                    <strong><?php echo esc_html((string) $missing_keyword_count); ?></strong>
                    <span><?php esc_html_e('Missing keywords', 'aiseo'); ?></span>
                </div>
            </div>
            <?php if ($site_audit['duplicates']) : ?>
                <h4><?php esc_html_e('Duplicate titles', 'aiseo'); ?></h4>
                <ul class="aiseo-list-compact">
                    <?php foreach (array_slice($site_audit['duplicates'], 0, 5) as $duplicate_group) : ?>
                        <li>
                            <?php echo esc_html($duplicate_group['title']); ?>
                            <span class="aiseo-muted">(<?php echo esc_html((string) count($duplicate_group['ids'])); ?>)</span>
                            <?php
                            $shown = 0;
                            foreach ($duplicate_group['ids'] as $dup_id) {
                                if ($shown >= 3) {
                                    break;
                                }
                                if (!current_user_can('edit_post', $dup_id)) {
                                    continue;
                                }
                                $edit = get_edit_post_link($dup_id, 'raw');
                                if (!$edit) {
                                    continue;
                                }
                                $shown++;
                                echo ' <a href="' . esc_url($edit) . '">' . esc_html(get_the_title($dup_id)) . '</a>';
                            }
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($site_audit['missing_description']) : ?>
                <h4><?php esc_html_e('Missing descriptions', 'aiseo'); ?></h4>
                <ul class="aiseo-list-compact">
                    <?php foreach (array_slice($site_audit['missing_description'], 0, 5) as $missing_id) : ?>
                        <li>
                            <?php if (current_user_can('edit_post', $missing_id) && get_edit_post_link($missing_id, 'raw')) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($missing_id, 'raw')); ?>"><?php echo esc_html(get_the_title($missing_id)); ?></a>
                            <?php else : ?>
                                <?php echo esc_html(get_the_title($missing_id)); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    </div>
    <?php
    $orphan_report = array('orphans' => array(), 'checked' => 0);
    if (class_exists('AISEO_Internal_Linking')) {
        $linker = new AISEO_Internal_Linking();
        $found = $linker->detect_orphans(array(
            'limit' => 20,
            'use_cache' => true,
        ));
        if (is_array($found)) {
            $orphan_report = $found;
        }
    }
    $orphans = array_slice($orphan_report['orphans'], 0, 5);
    ?>
    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                <?php esc_html_e('Pages with no internal links', 'aiseo'); ?>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <?php if ($orphans) : ?>
                <ul class="aiseo-list-compact">
                    <?php foreach ($orphans as $orphan) : ?>
                        <li>
                            <?php if (current_user_can('edit_post', $orphan['post_id']) && get_edit_post_link($orphan['post_id'], 'raw')) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($orphan['post_id'], 'raw')); ?>"><?php echo esc_html($orphan['post_title'] !== '' ? $orphan['post_title'] : __('(no title)', 'aiseo')); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($orphan['post_title']); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e('Every checked post is linked from another published post.', 'aiseo'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
                <?php esc_html_e('Quick Actions', 'aiseo'); ?>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <div class="aiseo-button-group">
                <?php if (current_user_can('aiseo_manage_settings')) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings')); ?>" class="button button-primary">
                        <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                        <?php esc_html_e('Settings', 'aiseo'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=technical-seo')); ?>" class="button">
                        <span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
                        <?php esc_html_e('Redirects', 'aiseo'); ?>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=seo-tools')); ?>" class="button">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <?php esc_html_e('SEO Tools', 'aiseo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=bulk-operations')); ?>" class="button">
                    <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                    <?php esc_html_e('Bulk Operations', 'aiseo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=ai-content')); ?>" class="button">
                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    <?php esc_html_e('Create AI Post', 'aiseo'); ?>
                </a>
            </div>
        </div>
    </div>

    <div class="aiseo-card">
        <div class="aiseo-card-header">
            <h3 class="aiseo-card-title">
                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                <?php esc_html_e('Recent Posts', 'aiseo'); ?>
            </h3>
        </div>
        <div class="aiseo-card-body">
            <?php if (!empty($recent_posts)) : ?>
                <div class="aiseo-table-wrap">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('Title', 'aiseo'); ?></th>
                            <th scope="col"><?php esc_html_e('Type', 'aiseo'); ?></th>
                            <th scope="col"><?php esc_html_e('Status', 'aiseo'); ?></th>
                            <th scope="col"><?php esc_html_e('Focus keyword', 'aiseo'); ?></th>
                            <th scope="col"><?php esc_html_e('Date', 'aiseo'); ?></th>
                            <th scope="col"><?php esc_html_e('Actions', 'aiseo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_posts as $recent_post) : ?>
                            <?php
                            if (!current_user_can('edit_post', $recent_post->ID)) {
                                continue;
                            }
                            $edit_link = get_edit_post_link($recent_post->ID, 'raw');
                            $view_link = get_permalink($recent_post);
                            $title = $recent_post->post_title !== '' ? $recent_post->post_title : __('(no title)', 'aiseo');
                            $focus_keyword = get_post_meta($recent_post->ID, '_aiseo_focus_keyword', true);
                            $type_object = get_post_type_object($recent_post->post_type);
                            $type_label = ($type_object && !empty($type_object->labels->singular_name)) ? $type_object->labels->singular_name : $recent_post->post_type;
                            ?>
                            <tr>
                                <td>
                                    <?php if ($edit_link) : ?>
                                        <strong><a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($title); ?></a></strong>
                                    <?php else : ?>
                                        <strong><?php echo esc_html($title); ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($type_label); ?></td>
                                <td>
                                    <span class="aiseo-badge aiseo-badge-<?php echo esc_attr($badge_for_status($recent_post->post_status)); ?>">
                                        <?php echo esc_html(ucfirst($recent_post->post_status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (is_string($focus_keyword) && $focus_keyword !== '') : ?>
                                        <?php echo esc_html($focus_keyword); ?>
                                    <?php else : ?>
                                        <span class="aiseo-badge aiseo-badge-warning"><?php esc_html_e('Missing', 'aiseo'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(get_the_modified_date(get_option('date_format'), $recent_post)); ?></td>
                                <td class="aiseo-table-actions">
                                    <?php if ($edit_link) : ?>
                                        <a href="<?php echo esc_url($edit_link); ?>"><?php esc_html_e('Edit', 'aiseo'); ?></a>
                                    <?php endif; ?>
                                    <?php if ($view_link && $recent_post->post_status === 'publish') : ?>
                                        <?php if ($edit_link) : ?> | <?php endif; ?>
                                        <a href="<?php echo esc_url($view_link); ?>"><?php esc_html_e('View', 'aiseo'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else : ?>
                <div class="aiseo-empty-state">
                    <span class="dashicons dashicons-admin-post" aria-hidden="true"></span>
                    <h3><?php esc_html_e('No Recent Posts', 'aiseo'); ?></h3>
                    <p><?php esc_html_e('Create your first post to see it here.', 'aiseo'); ?></p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=ai-content')); ?>"><?php esc_html_e('Create AI Post', 'aiseo'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php
}
aiseo_render_dashboard_view();
