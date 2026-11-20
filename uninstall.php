<?php
/**
 * Uninstall Praison AI SEO.
 *
 * @package AISEO
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Drop plugin tables and options.
 */
function aiseo_uninstall_plugin() {
    global $wpdb;

    $aiseo_tables = array(
        'aiseo_logs',
        'aiseo_failed_requests',
        'aiseo_usage_stats',
        'aiseo_request_queue',
        'aiseo_rank_tracking',
        'aiseo_404_log',
        'aiseo_redirects',
    );
    foreach ($aiseo_tables as $aiseo_table) {
        $aiseo_full = $wpdb->prefix . $aiseo_table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall drop of plugin tables.
        $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($aiseo_full) . '`');
    }

    $aiseo_option_like = $wpdb->esc_like('aiseo_') . '%';
    $aiseo_transient_like = $wpdb->esc_like('_transient_aiseo_') . '%';
    $aiseo_timeout_like = $wpdb->esc_like('_transient_timeout_aiseo_') . '%';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin options.
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $aiseo_option_like,
        $aiseo_transient_like,
        $aiseo_timeout_like
    ));

    $aiseo_post_like = $wpdb->esc_like('_aiseo_') . '%';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin post meta.
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $aiseo_post_like));
    if (!empty($wpdb->termmeta)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin term meta.
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $aiseo_post_like));
    }
    $aiseo_user_like = $wpdb->esc_like('aiseo_') . '%';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup of plugin user meta.
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
        $aiseo_post_like,
        $aiseo_user_like
    ));

    wp_clear_scheduled_hook('aiseo_cache_warming');
    wp_clear_scheduled_hook('aiseo_process_queue');
    wp_clear_scheduled_hook('aiseo_cleanup_logs');

    $aiseo_admin = get_role('administrator');
    if ($aiseo_admin) {
        foreach (array('aiseo_manage_settings', 'aiseo_manage_redirects') as $aiseo_cap) {
            $aiseo_admin->remove_cap($aiseo_cap);
        }
    }
}

aiseo_uninstall_plugin();
