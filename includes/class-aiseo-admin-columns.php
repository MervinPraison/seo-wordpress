<?php
/**
 * SEO columns on post lists, matching Yoast and Rank Math.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Admin_Columns {

    /**
     * Register list-table columns for public post types.
     */
    public function init() {
        $types = get_post_types(array('public' => true), 'names');
        unset($types['attachment']);
        $enabled = get_option('aiseo_sitemap_post_types', null);
        if (is_array($enabled) && $enabled) {
            $types = array_intersect($types, $enabled);
        }
        foreach ($types as $type) {
            add_filter('manage_' . $type . '_posts_columns', array($this, 'columns'));
            add_action('manage_' . $type . '_posts_custom_column', array($this, 'column_content'), 10, 2);
            add_filter('bulk_actions-edit-' . $type, array($this, 'bulk_actions'));
            add_filter('handle_bulk_actions-edit-' . $type, array($this, 'handle_bulk_actions'), 10, 3);
        }
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
        add_action('quick_edit_custom_box', array($this, 'quick_edit_box'), 10, 2);
        add_action('save_post', array($this, 'save_quick_edit'), 10, 2);
    }

    /**
     * @param string $hook Admin page.
     */
    public function enqueue($hook) {
        if ($hook !== 'edit.php') {
            return;
        }
        wp_enqueue_style(
            'aiseo-admin',
            AISEO_PLUGIN_URL . 'admin/css/aiseo-admin.css',
            array(),
            AISEO_VERSION
        );
        wp_enqueue_script(
            'aiseo-quick-edit',
            AISEO_PLUGIN_URL . 'admin/js/aiseo-quick-edit.js',
            array('jquery', 'inline-edit-post'),
            AISEO_VERSION,
            true
        );
    }

    /**
     * @param array $columns Existing columns.
     * @return array
     */
    public function columns($columns) {
        $new = array();
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['aiseo_score'] = __('SEO', 'aiseo');
                $new['aiseo_title'] = __('SEO title', 'aiseo');
                $new['aiseo_keyword'] = __('Focus keyword', 'aiseo');
                $new['aiseo_index'] = __('Index', 'aiseo');
            }
        }
        if (!isset($new['aiseo_title'])) {
            $new['aiseo_score'] = __('SEO', 'aiseo');
            $new['aiseo_title'] = __('SEO title', 'aiseo');
            $new['aiseo_keyword'] = __('Focus keyword', 'aiseo');
            $new['aiseo_index'] = __('Index', 'aiseo');
        }
        return $new;
    }

    /**
     * @param string $column  Column key.
     * @param int    $post_id Post ID.
     */
    public function column_content($column, $post_id) {
        $post_id = absint($post_id);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            if (in_array($column, array('aiseo_score', 'aiseo_title', 'aiseo_keyword', 'aiseo_index'), true)) {
                echo '&mdash;';
            }
            return;
        }
        if ($column === 'aiseo_score') {
            $score = $this->field_score($post_id);
            $status = $score >= 70 ? 'good' : ($score >= 40 ? 'ok' : 'poor');
            echo '<span class="aiseo-col-score aiseo-col-score-' . esc_attr($status) . '" title="' . esc_attr__('From saved title, description and keyword. This is not a search ranking.', 'aiseo') . '">' . esc_html((string) $score) . '</span>';
            return;
        }
        if ($column === 'aiseo_title') {
            $title = get_post_meta($post_id, '_aiseo_meta_title', true);
            $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
            $noindex = class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post_id);
            echo '<span class="aiseo-qe-data" hidden data-title="' . esc_attr(is_string($title) ? $title : '') . '" data-keyword="' . esc_attr(is_string($keyword) ? $keyword : '') . '" data-noindex="' . esc_attr($noindex ? '1' : '0') . '"></span>';
            echo is_string($title) && $title !== '' ? esc_html(wp_trim_words($title, 12, '…')) : '<span class="aiseo-muted">&mdash;</span>';
            return;
        }
        if ($column === 'aiseo_keyword') {
            $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
            echo is_string($keyword) && $keyword !== '' ? esc_html($keyword) : '<span class="aiseo-muted">&mdash;</span>';
            return;
        }
        if ($column === 'aiseo_index') {
            if (class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post_id)) {
                echo '<span class="aiseo-col-noindex">' . esc_html__('noindex', 'aiseo') . '</span>';
            } else {
                echo '<span class="aiseo-col-index">' . esc_html__('index', 'aiseo') . '</span>';
            }
        }
    }

    /**
     * Quick Edit fields for list tables.
     *
     * @param string $column    Column key.
     * @param string $post_type Post type.
     */
    public function quick_edit_box($column, $post_type) {
        unset($post_type);
        if ($column !== 'aiseo_title') {
            return;
        }
        wp_nonce_field('aiseo_quick_edit', 'aiseo_quick_edit_nonce');
        ?>
        <fieldset class="inline-edit-col-left aiseo-quick-edit-fields">
            <div class="inline-edit-col">
                <legend class="inline-edit-legend"><?php esc_html_e('AISEO', 'aiseo'); ?></legend>
                <label>
                    <span class="title"><?php esc_html_e('SEO title', 'aiseo'); ?></span>
                    <span class="input-text-wrap">
                        <input type="text" name="aiseo_qe_title" value="" />
                    </span>
                </label>
                <label>
                    <span class="title"><?php esc_html_e('Keyword', 'aiseo'); ?></span>
                    <span class="input-text-wrap">
                        <input type="text" name="aiseo_qe_keyword" value="" />
                    </span>
                </label>
                <label>
                    <input type="checkbox" name="aiseo_qe_noindex" value="1" />
                    <?php esc_html_e('Noindex', 'aiseo'); ?>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post.
     */
    public function save_quick_edit($post_id, $post) {
        unset($post);
        $post_id = absint($post_id);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!isset($_POST['aiseo_quick_edit_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiseo_quick_edit_nonce'])), 'aiseo_quick_edit')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (isset($_POST['aiseo_qe_title'])) {
            update_post_meta($post_id, '_aiseo_meta_title', sanitize_text_field(wp_unslash($_POST['aiseo_qe_title'])));
        }
        if (isset($_POST['aiseo_qe_keyword'])) {
            update_post_meta($post_id, '_aiseo_focus_keyword', sanitize_text_field(wp_unslash($_POST['aiseo_qe_keyword'])));
        }
        if (!empty($_POST['aiseo_qe_noindex'])) {
            update_post_meta($post_id, '_aiseo_noindex', '1');
        } else {
            update_post_meta($post_id, '_aiseo_noindex', '0');
        }
        delete_transient('aiseo_dashboard_counts');
        delete_transient('aiseo_dashboard_audit');
    }

    /**
     * @param array $actions Bulk actions.
     * @return array
     */
    public function bulk_actions($actions) {
        $actions['aiseo_exclude_sitemap'] = __('Exclude from XML sitemap', 'aiseo');
        $actions['aiseo_include_sitemap'] = __('Include in XML sitemap', 'aiseo');
        return $actions;
    }

    /**
     * @param string $redirect Redirect URL.
     * @param string $action   Action.
     * @param array  $ids      Post IDs.
     * @return string
     */
    public function handle_bulk_actions($redirect, $action, $ids) {
        if (!in_array($action, array('aiseo_exclude_sitemap', 'aiseo_include_sitemap'), true)) {
            return $redirect;
        }
        $exclude = ($action === 'aiseo_exclude_sitemap') ? '1' : '0';
        foreach ((array) $ids as $post_id) {
            $post_id = absint($post_id);
            if ($post_id && current_user_can('edit_post', $post_id)) {
                update_post_meta($post_id, '_aiseo_exclude_sitemap', $exclude);
            }
        }
        return $redirect;
    }

    /**
     * Completeness of saved SEO fields. Not a search ranking.
     *
     * @param int $post_id Post ID.
     * @return int 0–100
     */
    private function field_score($post_id) {
        $title = get_post_meta($post_id, '_aiseo_meta_title', true);
        $description = get_post_meta($post_id, '_aiseo_meta_description', true);
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        $score = 0;
        if (is_string($title) && $title !== '') {
            $len = strlen($title);
            $score += ($len >= 50 && $len <= 60) ? 30 : 20;
        }
        if (is_string($description) && $description !== '') {
            $len = strlen($description);
            $score += ($len >= 120 && $len <= 160) ? 30 : 20;
        }
        if (is_string($keyword) && $keyword !== '') {
            $score += 25;
        }
        if (!(class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post_id))) {
            $score += 15;
        }
        if ($score > 100) {
            $score = 100;
        }
        return $score;
    }
}
