<?php
/**
 * IndexNow submission when public content is published.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_IndexNow {

    const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * Serve the key file and queue submissions.
     */
    public function init() {
        add_action('template_redirect', array($this, 'maybe_serve_key'), 0);
        add_action('transition_post_status', array($this, 'queue_submission'), 10, 3);
        add_action('aiseo_indexnow_submit', array($this, 'submit_url'));
    }

    /**
     * @return bool
     */
    public function is_enabled() {
        $enabled = get_option('aiseo_enable_indexnow', '1');
        return !in_array($enabled, array(false, 0, '0', ''), true);
    }

    /**
     * Stable site key. Generated once.
     *
     * @return string
     */
    public function key() {
        $key = get_option('aiseo_indexnow_key', '');
        if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/', $key)) {
            $key = bin2hex(random_bytes(16));
            update_option('aiseo_indexnow_key', $key, false);
        }
        return $key;
    }

    /**
     * The key file IndexNow fetches to prove ownership.
     */
    public function maybe_serve_key() {
        if (!$this->is_enabled()) {
            return;
        }
        $requested = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = untrailingslashit((string) wp_parse_url($requested, PHP_URL_PATH));
        if ($path !== '/' . $this->key() . '.txt') {
            return;
        }
        status_header(200);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo $this->key(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the key is hex.
        exit;
    }

    /**
     * Queue one URL after a post or page becomes public.
     *
     * @param string  $new_status New status.
     * @param string  $old_status Old status.
     * @param WP_Post $post       Post.
     */
    public function queue_submission($new_status, $old_status, $post) {
        if (!$this->is_enabled() || $new_status !== 'publish' || !($post instanceof WP_Post)) {
            return;
        }
        $public_types = get_post_types(array('public' => true), 'names');
        unset($public_types['attachment']);
        if (!isset($public_types[$post->post_type]) || wp_is_post_revision($post->ID) || $post->post_password !== '') {
            return;
        }
        if (class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post->ID)) {
            return;
        }
        $url = get_permalink($post);
        if (!$url) {
            return;
        }
        if (!wp_next_scheduled('aiseo_indexnow_submit', array($url))) {
            wp_schedule_single_event(time() + 15, 'aiseo_indexnow_submit', array($url));
        }
    }

    /**
     * Tell IndexNow about one URL.
     *
     * @param string $url Public URL.
     * @return array|WP_Error
     */
    public function submit_url($url) {
        if (!$this->is_enabled()) {
            return new WP_Error('aiseo_indexnow_disabled', 'IndexNow is disabled.');
        }
        $url = esc_url_raw($url);
        $post_id = url_to_postid($url);
        if ($post_id) {
            $post = get_post($post_id);
            $noindex = class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post_id);
            if (($post instanceof WP_Post && $post->post_password !== '') || $noindex) {
                return new WP_Error('aiseo_indexnow_hidden', 'This URL is not indexable.');
            }
        }
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($url === '' || !$host) {
            return new WP_Error('aiseo_indexnow_invalid', 'IndexNow URL is invalid.');
        }
        $key = $this->key();
        return wp_remote_post(self::ENDPOINT, array(
            'timeout' => 5,
            'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
            'body' => wp_json_encode(array(
                'host' => $host,
                'key' => $key,
                'keyLocation' => home_url('/' . $key . '.txt'),
                'urlList' => array($url),
            )),
        ));
    }
}
