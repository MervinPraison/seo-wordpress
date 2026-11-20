<?php
/**
 * First-run setup wizard, matching Yoast, Rank Math and AIOSEO.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Setup {

    /**
     * Register admin UI and activation redirect.
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_page'), 20);
        add_action('admin_init', array($this, 'maybe_redirect'));
        add_action('admin_init', array($this, 'save'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    }

    /**
     * @return bool
     */
    public function is_complete() {
        $value = get_option('aiseo_setup_complete', '');
        return $value === '1' || $value === 'skipped';
    }

    /**
     * Hidden under the AISEO menu.
     */
    public function add_page() {
        add_submenu_page(
            'aiseo',
            __('Setup wizard', 'aiseo'),
            __('Setup wizard', 'aiseo'),
            'aiseo_manage_settings',
            'aiseo-setup',
            array($this, 'render')
        );
    }

    /**
     * Send a fresh install to the wizard once.
     */
    public function maybe_redirect() {
        if (!get_transient('aiseo_activation_redirect')) {
            return;
        }
        delete_transient('aiseo_activation_redirect');
        if (!current_user_can('aiseo_manage_settings')) {
            return;
        }
        if (isset($_GET['activate-multi'])) {
            return;
        }
        if (wp_doing_ajax() || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=aiseo-setup'));
        exit;
    }

    /**
     * @param string $hook Admin page.
     */
    public function enqueue($hook) {
        if ($hook !== 'aiseo_page_aiseo-setup') {
            return;
        }
        wp_enqueue_style(
            'aiseo-admin',
            AISEO_PLUGIN_URL . 'admin/css/aiseo-admin.css',
            array(),
            AISEO_VERSION
        );
    }

    /**
     * Persist one wizard step.
     */
    public function save() {
        if (!isset($_POST['aiseo_setup_nonce']) || !current_user_can('aiseo_manage_settings')) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['aiseo_setup_nonce'])), 'aiseo_setup')) {
            return;
        }
        $action = isset($_POST['aiseo_setup_action']) ? sanitize_key(wp_unslash($_POST['aiseo_setup_action'])) : '';
        if ($action === 'skip') {
            update_option('aiseo_setup_complete', 'skipped');
            wp_safe_redirect(admin_url('admin.php?page=aiseo'));
            exit;
        }
        $step = isset($_POST['aiseo_setup_step']) ? absint($_POST['aiseo_setup_step']) : 1;
        if ($action === 'save_representation') {
            $represents = isset($_POST['aiseo_site_represents']) ? sanitize_key(wp_unslash($_POST['aiseo_site_represents'])) : 'organization';
            if (!in_array($represents, array('organization', 'person'), true)) {
                $represents = 'organization';
            }
            update_option('aiseo_site_represents', $represents);
            $details = get_option('aiseo_organization_details', array());
            if (!is_array($details)) {
                $details = array();
            }
            $details['name'] = isset($_POST['aiseo_org_name']) ? sanitize_text_field(wp_unslash($_POST['aiseo_org_name'])) : '';
            update_option('aiseo_organization_details', $details);
            $step = 3;
        } elseif ($action === 'save_features') {
            update_option('aiseo_enable_schema', isset($_POST['aiseo_enable_schema']) ? '1' : '0');
            update_option('aiseo_enable_sitemap', isset($_POST['aiseo_enable_sitemap']) ? '1' : '0');
            update_option('aiseo_enable_llms', isset($_POST['aiseo_enable_llms']) ? '1' : '0');
            update_option('aiseo_enable_indexnow', isset($_POST['aiseo_enable_indexnow']) ? '1' : '0');
            $step = 4;
        } elseif ($action === 'save_homepage') {
            $home = new AISEO_Homepage_SEO();
            $home->update_settings(array(
                'home_title' => isset($_POST['aiseo_home_title']) ? sanitize_text_field(wp_unslash($_POST['aiseo_home_title'])) : '',
                'home_description' => isset($_POST['aiseo_home_description']) ? sanitize_textarea_field(wp_unslash($_POST['aiseo_home_description'])) : '',
            ));
            $step = 5;
        } elseif ($action === 'finish') {
            update_option('aiseo_setup_complete', '1');
            wp_safe_redirect(admin_url('admin.php?page=aiseo'));
            exit;
        } elseif ($action === 'next') {
            $step = min(5, $step + 1);
        }
        wp_safe_redirect(admin_url('admin.php?page=aiseo-setup&step=' . $step));
        exit;
    }

    /**
     * Wizard screen.
     */
    public function render() {
        if (!current_user_can('aiseo_manage_settings')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiseo'));
        }
        $step = isset($_GET['step']) ? absint($_GET['step']) : 1;
        if ($step < 1 || $step > 5) {
            $step = 1;
        }
        $view = AISEO_PLUGIN_DIR . 'admin/views/setup.php';
        if (file_exists($view)) {
            require $view;
        }
    }
}
