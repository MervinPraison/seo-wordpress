<?php
/**
 * WordPress 7.0 AI Client and official AI plugin SEO hooks.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_WP_AI {

    /**
     * Hook the official AI plugin and expose helper status.
     */
    public function init() {
        add_filter('wpai_meta_description_seo_plugins', array($this, 'register_meta_description_plugin'));
    }

    /**
     * WordPress 7.0+ AI Client with at least one working text connector.
     *
     * @return bool
     */
    public static function is_ready() {
        if (!function_exists('wp_ai_client_prompt') || !class_exists('WP_AI_Client_Prompt_Builder')) {
            return false;
        }
        $builder = wp_ai_client_prompt('Praison AI SEO');
        if (!is_object($builder) || !method_exists($builder, 'is_supported_for_text_generation')) {
            return true;
        }
        $supported = $builder->is_supported_for_text_generation();
        return $supported === true;
    }

    /**
     * Generate text through core Connectors when available.
     *
     * @param string $prompt  Prompt.
     * @param array  $options max_tokens, temperature.
     * @return string|WP_Error
     */
    public static function generate_text($prompt, $options = array()) {
        if (!function_exists('wp_ai_client_prompt')) {
            return new WP_Error('aiseo_no_core_ai', __('WordPress AI Client is not available.', 'aiseo'));
        }
        $builder = wp_ai_client_prompt((string) $prompt);
        if (!is_object($builder) || !method_exists($builder, 'generate_text')) {
            return new WP_Error('aiseo_no_core_ai', __('WordPress AI Client is not available.', 'aiseo'));
        }
        if (method_exists($builder, 'using_system_instruction')) {
            $builder = $builder->using_system_instruction('You are an SEO expert assistant. Provide concise, accurate, and helpful SEO content.');
        }
        if (isset($options['temperature']) && method_exists($builder, 'using_temperature')) {
            $builder = $builder->using_temperature((float) $options['temperature']);
        }
        if (isset($options['max_tokens']) && method_exists($builder, 'using_max_tokens')) {
            $builder = $builder->using_max_tokens((int) $options['max_tokens']);
        }
        if (method_exists($builder, 'is_supported_for_text_generation') && $builder->is_supported_for_text_generation() !== true) {
            return new WP_Error('aiseo_no_core_ai', __('No WordPress AI connector is configured for text generation.', 'aiseo'));
        }
        $result = $builder->generate_text();
        if (is_wp_error($result)) {
            return $result;
        }
        if (!is_string($result) || $result === '') {
            return new WP_Error('aiseo_core_ai_empty', __('The WordPress AI Client returned an empty response.', 'aiseo'));
        }
        return $result;
    }

    /**
     * Tell the official AI plugin to write generated descriptions into this plugin.
     *
     * @param array $plugins Plugin map.
     * @return array
     */
    public function register_meta_description_plugin($plugins) {
        if (!is_array($plugins)) {
            $plugins = array();
        }
        $plugins['aiseo'] = array(
            'file' => self::plugin_file(),
            'meta_key' => '_aiseo_meta_description',
        );
        return $plugins;
    }

    /**
     * Active bootstrap file for is_plugin_active().
     *
     * @return string
     */
    public static function plugin_file() {
        $file = defined('AISEO_PLUGIN_FILE') ? plugin_basename(AISEO_PLUGIN_FILE) : 'aiseo/aiseo.php';
        $candidates = array(
            $file,
            'seo-wordpress/seo-wordpress.php',
            'aiseo/aiseo.php',
        );
        if (!function_exists('is_plugin_active')) {
            return $file;
        }
        foreach ($candidates as $candidate) {
            if (is_plugin_active($candidate)) {
                return $candidate;
            }
        }
        return $file;
    }
}
