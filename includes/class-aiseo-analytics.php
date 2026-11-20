<?php
/**
 * AISEO Google Analytics Integration
 *
 * Handles Google Analytics (GA4 and Universal Analytics) tracking code injection
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Analytics {
    
    /**
     * Option keys
     */
    const OPTION_TRACKING_ID = 'aiseo_ga_tracking_id';
    const OPTION_ENABLED = 'aiseo_ga_enabled';
    const OPTION_ANONYMIZE_IP = 'aiseo_ga_anonymize_ip';
    const OPTION_TRACK_ADMIN = 'aiseo_ga_track_admin';
    const OPTION_TRACK_LOGGED_IN = 'aiseo_ga_track_logged_in';
    
    /**
     * Initialize analytics
     */
    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_code'), 1);
        add_filter('script_loader_tag', array($this, 'async_tracking_script'), 10, 3);
    }
    
    /**
     * Get analytics settings
     *
     * @return array Settings
     */
    public function get_settings() {
        return array(
            'tracking_id' => get_option(self::OPTION_TRACKING_ID, ''),
            'enabled' => get_option(self::OPTION_ENABLED, '0') === '1',
            'anonymize_ip' => get_option(self::OPTION_ANONYMIZE_IP, '1') === '1',
            'track_admin' => get_option(self::OPTION_TRACK_ADMIN, '0') === '1',
            'track_logged_in' => get_option(self::OPTION_TRACK_LOGGED_IN, '1') === '1',
        );
    }
    
    /**
     * Update analytics settings
     *
     * @param array $settings Settings to update
     * @return bool Success
     */
    public function update_settings($settings) {
        $updated = false;
        
        if (isset($settings['tracking_id'])) {
            $tracking_id = $this->sanitize_tracking_id($settings['tracking_id']);
            update_option(self::OPTION_TRACKING_ID, $tracking_id);
            $updated = true;
        }
        
        if (isset($settings['enabled'])) {
            update_option(self::OPTION_ENABLED, $settings['enabled'] ? '1' : '0');
            $updated = true;
        }
        
        if (isset($settings['anonymize_ip'])) {
            update_option(self::OPTION_ANONYMIZE_IP, $settings['anonymize_ip'] ? '1' : '0');
            $updated = true;
        }
        
        if (isset($settings['track_admin'])) {
            update_option(self::OPTION_TRACK_ADMIN, $settings['track_admin'] ? '1' : '0');
            $updated = true;
        }
        
        if (isset($settings['track_logged_in'])) {
            update_option(self::OPTION_TRACK_LOGGED_IN, $settings['track_logged_in'] ? '1' : '0');
            $updated = true;
        }
        
        return $updated;
    }
    
    /**
     * Sanitize tracking ID
     *
     * @param string $id Tracking ID
     * @return string Sanitized ID
     */
    private function sanitize_tracking_id($id) {
        $id = trim($id);
        $id = strtoupper($id);
        
        // Remove any spaces
        $id = preg_replace('/\s+/', '', $id);
        
        return sanitize_text_field($id);
    }
    
    /**
     * Check if tracking ID is GA4 format
     *
     * @param string $id Tracking ID
     * @return bool
     */
    private function is_ga4($id) {
        return preg_match('/^G-[A-Z0-9]+$/', $id);
    }
    
    /**
     * Check if tracking ID is Universal Analytics format
     *
     * @param string $id Tracking ID
     * @return bool
     */
    private function is_universal($id) {
        return preg_match('/^UA-\d+-\d+$/', $id);
    }
    
    /**
     * Should track current user
     *
     * @return bool
     */
    private function should_track() {
        $settings = $this->get_settings();
        
        // Check if enabled
        if (!$settings['enabled']) {
            return false;
        }
        
        // Check if tracking ID is set
        if (empty($settings['tracking_id'])) {
            return false;
        }
        
        // Check admin pages
        if (is_admin() && !$settings['track_admin']) {
            return false;
        }
        
        // Check logged in users
        if (is_user_logged_in() && !$settings['track_logged_in']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Enqueue Google Analytics scripts.
     */
    public function enqueue_tracking_code() {
        if (!$this->should_track()) {
            return;
        }

        $settings = $this->get_settings();
        $tracking_id = $settings['tracking_id'];

        if ($this->is_ga4($tracking_id)) {
            $this->enqueue_ga4_code($tracking_id, $settings);
        } elseif ($this->is_universal($tracking_id)) {
            $this->enqueue_universal_code($tracking_id, $settings);
        }
    }

    /**
     * Mark tracking scripts as async.
     *
     * @param string $tag    Script tag.
     * @param string $handle Script handle.
     * @param string $src    Script URL.
     * @return string
     */
    public function async_tracking_script($tag, $handle, $src) {
        unset($src);
        if ($handle !== 'aiseo-gtag' && $handle !== 'aiseo-ga-ua') {
            return $tag;
        }
        if (strpos($tag, ' async') !== false) {
            return $tag;
        }
        return str_replace(' src=', ' async src=', $tag);
    }

    /**
     * Enqueue GA4 tracking.
     *
     * @param string $tracking_id Tracking ID.
     * @param array  $settings    Settings.
     */
    private function enqueue_ga4_code($tracking_id, $settings) {
        $src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($tracking_id);
        wp_enqueue_script('aiseo-gtag', $src, array(), null, false);
        $config = array();
        if (!empty($settings['anonymize_ip'])) {
            $config['anonymize_ip'] = true;
        }
        $inline = "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\ngtag('config', " . wp_json_encode($tracking_id);
        if ($config) {
            $inline .= ', ' . wp_json_encode($config);
        }
        $inline .= ');';
        wp_add_inline_script('aiseo-gtag', $inline);
    }

    /**
     * Enqueue Universal Analytics tracking.
     *
     * @param string $tracking_id Tracking ID.
     * @param array  $settings    Settings.
     */
    private function enqueue_universal_code($tracking_id, $settings) {
        wp_enqueue_script('aiseo-ga-ua', 'https://www.google-analytics.com/analytics.js', array(), null, false);
        $inline = "window.ga=window.ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date();ga('create', " . wp_json_encode($tracking_id) . ", 'auto');";
        if (!empty($settings['anonymize_ip'])) {
            $inline .= "ga('set', 'anonymizeIp', true);";
        }
        $inline .= "ga('send', 'pageview');";
        wp_add_inline_script('aiseo-ga-ua', $inline);
    }
}
