<?php
/**
 * Google Search Console OAuth and Search Analytics.
 *
 * Shows Google's numbers only. Does not invent ranks.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Search_Console {

    const TRANSIENT = 'aiseo_gsc_summary';
    const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    /**
     * Register admin-post handlers.
     */
    public function init() {
        add_action('admin_post_aiseo_gsc_connect', array($this, 'handle_connect'));
        add_action('admin_post_aiseo_gsc_callback', array($this, 'handle_callback'));
        add_action('admin_post_aiseo_gsc_disconnect', array($this, 'handle_disconnect'));
        add_action('admin_post_aiseo_gsc_refresh', array($this, 'handle_refresh'));
    }

    /**
     * @return bool
     */
    public function is_connected() {
        $token = $this->refresh_token();
        return $token !== '';
    }

    /**
     * @return string
     */
    public function redirect_uri() {
        return admin_url('admin-post.php?action=aiseo_gsc_callback');
    }

    /**
     * @return string
     */
    public function client_id() {
        $id = get_option('aiseo_gsc_client_id', '');
        return is_string($id) ? trim($id) : '';
    }

    /**
     * @return string
     */
    public function property_url() {
        $url = get_option('aiseo_gsc_property', '');
        if (is_string($url) && $url !== '') {
            return $url;
        }
        return home_url('/');
    }

    /**
     * Totals from Google Search Analytics, or a WP_Error.
     *
     * @return array|WP_Error
     */
    public function summary() {
        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached) && isset($cached['clicks'], $cached['impressions'], $cached['queries'], $cached['pages'])) {
            return $cached;
        }
        $fetched = $this->fetch_summary();
        if (is_wp_error($fetched)) {
            return $fetched;
        }
        set_transient(self::TRANSIENT, $fetched, 6 * HOUR_IN_SECONDS);
        return $fetched;
    }

    /**
     * Start OAuth.
     */
    public function handle_connect() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'aiseo'));
        }
        check_admin_referer('aiseo_gsc_connect');
        $client_id = $this->client_id();
        if ($client_id === '' || $this->client_secret() === '') {
            wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=missing_client'));
            exit;
        }
        $state = wp_create_nonce('aiseo_gsc_oauth');
        set_transient('aiseo_gsc_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);
        $url = add_query_arg(
            array(
                'client_id' => $client_id,
                'redirect_uri' => $this->redirect_uri(),
                'response_type' => 'code',
                'scope' => self::SCOPE,
                'access_type' => 'offline',
                'prompt' => 'consent',
                'state' => $state,
            ),
            'https://accounts.google.com/o/oauth2/v2/auth'
        );
        wp_redirect($url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google accounts host.
        exit;
    }

    /**
     * Exchange the code for tokens.
     */
    public function handle_callback() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'aiseo'));
        }
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $expected = get_transient('aiseo_gsc_oauth_state_' . get_current_user_id());
        delete_transient('aiseo_gsc_oauth_state_' . get_current_user_id());
        if (!$state || !is_string($expected) || !hash_equals($expected, $state)) {
            wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=bad_state'));
            exit;
        }
        if (!empty($_GET['error'])) {
            wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=denied'));
            exit;
        }
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        if ($code === '') {
            wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=missing_code'));
            exit;
        }
        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'timeout' => 20,
                'body' => array(
                    'code' => $code,
                    'client_id' => $this->client_id(),
                    'client_secret' => $this->client_secret(),
                    'redirect_uri' => $this->redirect_uri(),
                    'grant_type' => 'authorization_code',
                ),
            )
        );
        if (is_wp_error($response)) {
            wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=token_error'));
            exit;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['refresh_token'])) {
            wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=no_refresh'));
            exit;
        }
        update_option('aiseo_gsc_refresh_token', AISEO_Helpers::encrypt_api_key(sanitize_text_field($body['refresh_token'])), false);
        if (!empty($body['access_token'])) {
            $expires = isset($body['expires_in']) ? absint($body['expires_in']) : 3500;
            set_transient('aiseo_gsc_access_token', sanitize_text_field($body['access_token']), max(60, $expires - 60));
        }
        delete_transient(self::TRANSIENT);
        wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=connected'));
        exit;
    }

    /**
     * Drop tokens.
     */
    public function handle_disconnect() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'aiseo'));
        }
        check_admin_referer('aiseo_gsc_disconnect');
        delete_option('aiseo_gsc_refresh_token');
        delete_transient('aiseo_gsc_access_token');
        delete_transient(self::TRANSIENT);
        wp_safe_redirect(admin_url('admin.php?page=aiseo&tab=settings&aiseo_gsc=disconnected'));
        exit;
    }

    /**
     * Clear cached Search Analytics.
     */
    public function handle_refresh() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'aiseo'));
        }
        check_admin_referer('aiseo_gsc_refresh');
        delete_transient(self::TRANSIENT);
        wp_safe_redirect(admin_url('admin.php?page=aiseo&aiseo_gsc=refreshed'));
        exit;
    }

    /**
     * @return array|WP_Error
     */
    private function fetch_summary() {
        if (!$this->is_connected()) {
            return new WP_Error('aiseo_gsc_disconnected', __('Google Search Console is not connected.', 'aiseo'));
        }
        $access = $this->access_token();
        if (is_wp_error($access)) {
            return $access;
        }
        $end = gmdate('Y-m-d', strtotime('-2 days'));
        $start = gmdate('Y-m-d', strtotime($end . ' -27 days'));
        $totals = $this->query_search_analytics($access, $start, $end, array(), 1);
        if (is_wp_error($totals)) {
            return $totals;
        }
        $queries = $this->query_search_analytics($access, $start, $end, array('query'), 10);
        if (is_wp_error($queries)) {
            $queries = array();
        }
        $pages = $this->query_search_analytics($access, $start, $end, array('page'), 10);
        if (is_wp_error($pages)) {
            $pages = array();
        }
        $row = isset($totals[0]) && is_array($totals[0]) ? $totals[0] : array();
        return array(
            'clicks' => isset($row['clicks']) ? (int) round($row['clicks']) : 0,
            'impressions' => isset($row['impressions']) ? (int) round($row['impressions']) : 0,
            'ctr' => isset($row['ctr']) ? (float) $row['ctr'] : 0,
            'position' => isset($row['position']) ? (float) $row['position'] : 0,
            'start' => $start,
            'end' => $end,
            'property' => $this->property_url(),
            'queries' => $this->map_analytics_rows($queries, 'query'),
            'pages' => $this->map_analytics_rows($pages, 'page'),
        );
    }

    /**
     * @param string   $access Bearer token.
     * @param string   $start  Start date.
     * @param string   $end    End date.
     * @param string[] $dimensions Search Analytics dimensions.
     * @param int      $row_limit  Row cap.
     * @return array|WP_Error
     */
    private function query_search_analytics($access, $start, $end, $dimensions, $row_limit) {
        $site = rawurlencode($this->property_url());
        $body = array(
            'startDate' => $start,
            'endDate' => $end,
            'rowLimit' => absint($row_limit),
        );
        if ($dimensions) {
            $body['dimensions'] = $dimensions;
        }
        $response = wp_remote_post(
            'https://www.googleapis.com/webmasters/v3/sites/' . $site . '/searchAnalytics/query',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($body),
            )
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300 || !is_array($decoded)) {
            $message = isset($decoded['error']['message']) ? $decoded['error']['message'] : __('Google Search Console did not return analytics.', 'aiseo');
            return new WP_Error('aiseo_gsc_api', sanitize_text_field($message));
        }
        return isset($decoded['rows']) && is_array($decoded['rows']) ? $decoded['rows'] : array();
    }

    /**
     * @param array  $rows Raw Google rows.
     * @param string $key  query or page.
     * @return array<int,array{label:string,clicks:int,impressions:int,position:float}>
     */
    private function map_analytics_rows($rows, $key) {
        $out = array();
        if (!is_array($rows)) {
            return $out;
        }
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['keys'][0])) {
                continue;
            }
            $out[] = array(
                'label' => sanitize_text_field((string) $row['keys'][0]),
                'clicks' => isset($row['clicks']) ? (int) round($row['clicks']) : 0,
                'impressions' => isset($row['impressions']) ? (int) round($row['impressions']) : 0,
                'position' => isset($row['position']) ? (float) $row['position'] : 0,
            );
        }
        unset($key);
        return $out;
    }

    /**
     * @return string
     */
    private function client_secret() {
        $stored = get_option('aiseo_gsc_client_secret', '');
        if (!is_string($stored) || $stored === '') {
            return '';
        }
        return AISEO_Helpers::decrypt_api_key($stored);
    }

    /**
     * @return string
     */
    private function refresh_token() {
        $stored = get_option('aiseo_gsc_refresh_token', '');
        if (!is_string($stored) || $stored === '') {
            return '';
        }
        return AISEO_Helpers::decrypt_api_key($stored);
    }

    /**
     * @return string|WP_Error
     */
    private function access_token() {
        $cached = get_transient('aiseo_gsc_access_token');
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $refresh = $this->refresh_token();
        if ($refresh === '') {
            return new WP_Error('aiseo_gsc_disconnected', __('Google Search Console is not connected.', 'aiseo'));
        }
        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'timeout' => 20,
                'body' => array(
                    'client_id' => $this->client_id(),
                    'client_secret' => $this->client_secret(),
                    'refresh_token' => $refresh,
                    'grant_type' => 'refresh_token',
                ),
            )
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['access_token'])) {
            return new WP_Error('aiseo_gsc_refresh', __('Could not refresh the Search Console token.', 'aiseo'));
        }
        $token = sanitize_text_field($body['access_token']);
        $expires = isset($body['expires_in']) ? absint($body['expires_in']) : 3500;
        set_transient('aiseo_gsc_access_token', $token, max(60, $expires - 60));
        return $token;
    }
}
