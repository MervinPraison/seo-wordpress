<?php
/**
 * Gutenberg blocks: FAQ (schema-backed) and related editor assets.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Blocks {

    /**
     * Register blocks.
     */
    public function init() {
        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor'));
    }

    /**
     * FAQ block used for FAQPage schema, like Yoast and Rank Math FAQ blocks.
     */
    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            return;
        }
        register_block_type('aiseo/faq', array(
            'api_version' => 2,
            'title' => __('AISEO FAQ', 'aiseo'),
            'description' => __('Questions and answers that Praison AI SEO can mark up as FAQ schema.', 'aiseo'),
            'category' => 'widgets',
            'icon' => 'editor-help',
            'attributes' => array(
                'questions' => array(
                    'type' => 'array',
                    'default' => array(),
                ),
            ),
            'render_callback' => array($this, 'render_faq'),
        ));
    }

    /**
     * Editor script for the FAQ block.
     */
    public function enqueue_editor() {
        wp_enqueue_script(
            'aiseo-faq-block',
            AISEO_PLUGIN_URL . 'admin/js/aiseo-faq-block.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
            AISEO_VERSION,
            true
        );
        wp_localize_script('aiseo-faq-block', 'aiseoFaqBlock', array(
            'i18n' => array(
                'title' => __('AISEO FAQ', 'aiseo'),
                'description' => __('Questions and answers for FAQ schema.', 'aiseo'),
                'question' => __('Question', 'aiseo'),
                'answer' => __('Answer', 'aiseo'),
                'add' => __('Add question', 'aiseo'),
                'remove' => __('Remove', 'aiseo'),
                'empty' => __('Add a question to show an FAQ on this page.', 'aiseo'),
            ),
        ));
    }

    /**
     * @param array $attributes Block attributes.
     * @return string
     */
    public function render_faq($attributes) {
        $questions = isset($attributes['questions']) && is_array($attributes['questions']) ? $attributes['questions'] : array();
        $items = array();
        foreach ($questions as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = isset($item['question']) ? sanitize_text_field($item['question']) : '';
            $answer = isset($item['answer']) ? wp_kses_post($item['answer']) : '';
            if ($question === '' || $answer === '') {
                continue;
            }
            $items[] = array(
                'question' => $question,
                'answer' => $answer,
            );
        }
        if (!$items) {
            return '';
        }
        $html = '<div class="aiseo-faq">';
        foreach ($items as $item) {
            $html .= '<div class="aiseo-faq-item">';
            $html .= '<h3 class="aiseo-faq-question">' . esc_html($item['question']) . '</h3>';
            $html .= '<div class="aiseo-faq-answer">' . wp_kses_post(wpautop($item['answer'])) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
