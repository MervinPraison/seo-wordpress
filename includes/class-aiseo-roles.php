<?php
/**
 * Role manager for AISEO capabilities, matching Rank Math's free role module.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Roles {

    /**
     * Register capabilities and save handler.
     */
    public function init() {
        $this->ensure_administrator_caps();
        add_action('admin_init', array($this, 'ensure_administrator_caps'));
        add_action('admin_init', array($this, 'save'));
    }

    /**
     * @return string[]
     */
    public function capabilities() {
        return array(
            'aiseo_manage_settings' => __('Manage AISEO settings', 'aiseo'),
            'aiseo_manage_redirects' => __('Manage redirects', 'aiseo'),
        );
    }

    /**
     * Administrators always keep access.
     */
    public function ensure_administrator_caps() {
        $admin = get_role('administrator');
        if (!$admin) {
            return;
        }
        foreach (array_keys($this->capabilities()) as $cap) {
            if (!$admin->has_cap($cap)) {
                $admin->add_cap($cap);
            }
        }
    }

    /**
     * Save role checkboxes from Advanced.
     */
    public function save() {
        if (!isset($_POST['aiseo_roles_submit']) || !current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('aiseo_roles_settings');
        $roles = wp_roles();
        if (!$roles) {
            return;
        }
        $posted = array();
        if (isset($_POST['aiseo_role_caps']) && is_array($_POST['aiseo_role_caps'])) {
            foreach (wp_unslash($_POST['aiseo_role_caps']) as $role_key => $caps) {
                $role_key = sanitize_key($role_key);
                if (!is_array($caps)) {
                    continue;
                }
                $posted[$role_key] = array();
                foreach ($caps as $cap) {
                    $cap = sanitize_key($cap);
                    if (isset($this->capabilities()[$cap])) {
                        $posted[$role_key][$cap] = true;
                    }
                }
            }
        }
        foreach ($roles->role_objects as $role_key => $role) {
            if ($role_key === 'administrator') {
                continue;
            }
            foreach (array_keys($this->capabilities()) as $cap) {
                $want = !empty($posted[$role_key][$cap]);
                if ($want && !$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
                if (!$want && $role->has_cap($cap)) {
                    $role->remove_cap($cap);
                }
            }
        }
        add_settings_error('aiseo_roles', 'aiseo_roles_saved', __('Role access saved.', 'aiseo'), 'updated');
    }
}
