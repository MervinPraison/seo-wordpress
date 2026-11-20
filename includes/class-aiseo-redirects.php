<?php
/**
 * AISEO 404 Monitor & Redirection Manager
 *
 * @package AISEO
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 404 Monitor & Redirection Manager Class
 */
class AISEO_Redirects {

    /**
     * Apply stored redirects on the front end and record 404s.
     */
    public function init() {
        $this->ensure_tables();
        add_action('template_redirect', array($this, 'maybe_redirect'), 0);
        add_action('template_redirect', array($this, 'maybe_log_404'), 99);
        add_action('post_updated', array($this, 'redirect_changed_slug'), 10, 3);
    }

    /**
     * Create the redirect tables when an older install does not have them.
     */
    public function ensure_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'aiseo_redirects';
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($found !== $table && function_exists('aiseo_create_tables')) {
            aiseo_create_tables();
        }
    }

    /**
     * Path-only form of a redirect source, with a leading slash.
     *
     * @param string $url Path or URL.
     * @return string
     */
    public static function normalize_path($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $url;
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        return $path === '/' ? '/' : untrailingslashit($path);
    }

    /**
     * Delimit a stored redirect pattern for preg_match.
     *
     * @param string $source Pattern.
     * @return string
     */
    public static function regex_pattern($source) {
        $source = (string) $source;
        if ($source === '') {
            return '';
        }
        return '~' . str_replace('~', '\~', $source) . '~';
    }

    /**
     * Allow a local path or an http(s) URL.
     *
     * @param string $target Target entered by an administrator.
     * @return string
     */
    public static function sanitize_target($target) {
        $target = trim((string) $target);
        if ($target === '') {
            return '';
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $target) && !preg_match('#^https?://#i', $target)) {
            return '';
        }
        if (strpos($target, '//') === 0) {
            return '';
        }
        if ($target[0] === '/') {
            return self::normalize_path($target);
        }
        return esc_url_raw($target);
    }

    /**
     * Redirect the current request when a rule matches.
     */
    public function maybe_redirect() {
        if (is_admin()) {
            return;
        }
        if (is_singular()) {
            $this->maybe_redirect_singular();
        }
        $requested = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = self::normalize_path($requested);
        if ($path === '') {
            return;
        }
        $this->process_redirect($path);
    }

    /**
     * Per-post redirect from the editor Advanced tab.
     */
    public function maybe_redirect_singular() {
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }
        $type = get_post_meta($post_id, '_aiseo_redirect_type', true);
        if (!in_array($type, array('301', '302', '307', '308', '410'), true)) {
            return;
        }
        if ($type === '410') {
            status_header(410);
            nocache_headers();
            $template = get_query_template('404');
            if (is_string($template) && $template !== '' && is_readable($template)) {
                include $template;
            }
            exit;
        }
        $target = get_post_meta($post_id, '_aiseo_redirect_to', true);
        $target = is_string($target) ? self::sanitize_target($target) : '';
        if ($target === '') {
            return;
        }
        if (isset($target[0]) && $target[0] === '/') {
            $target = home_url($target);
        }
        $current = get_permalink($post_id);
        if (is_string($current) && untrailingslashit($current) === untrailingslashit($target)) {
            return;
        }
        wp_safe_redirect($target, (int) $type);
        exit;
    }

    /**
     * Record a front-end 404 after redirects have had a chance to run.
     */
    public function maybe_log_404() {
        if (is_admin() || !is_404()) {
            return;
        }
        $requested = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
        $path = self::normalize_path($requested);
        if ($path !== '') {
            $this->log_404_error($path);
        }
    }
    
    /**
     * Log 404 error
     *
     * @param string $url Requested URL
     * @param array $options Additional options
     * @return bool Success status
     */
    public function log_404_error($url, $options = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aiseo_404_log';
        
        // Get request details
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $ip_address = $this->get_client_ip();
        
        // Insert log entry
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $result = $wpdb->insert(
            $table_name,
            [
                'url' => esc_url_raw($url),
                'referrer' => $referrer,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'timestamp' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }
        return substr(hash('sha256', $ip . wp_salt('auth')), 0, 45);
    }
    
    /**
     * Get 404 errors
     *
     * @param array $args Query arguments
     * @return array 404 errors
     */
    public function get_404_errors($args = []) {
        global $wpdb;
        
        $defaults = [
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'timestamp',
            'order' => 'DESC',
            'date_from' => '',
            'date_to' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'aiseo_404_log';
        
        // Build query
        $where = '1=1';
        
        if (!empty($args['date_from'])) {
            $where .= $wpdb->prepare(' AND timestamp >= %s', $args['date_from']);
        }
        
        if (!empty($args['date_to'])) {
            $where .= $wpdb->prepare(' AND timestamp <= %s', $args['date_to']);
        }
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and dynamic WHERE/ORDER BY are safe, values are prepared
        $query = $wpdb->prepare(
            "SELECT url, COUNT(*) as hits, MAX(timestamp) as last_hit, referrer
             FROM {$table_name}
             WHERE {$where}
             GROUP BY url
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is prepared above with $wpdb->prepare()
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return [
            'errors' => $results,
            'total' => count($results)
        ];
    }
    
    /**
     * Suggest redirect based on URL similarity
     *
     * @param string $url 404 URL
     * @return array|WP_Error Redirect suggestions
     */
    public function suggest_redirect($url) {
        // Get existing posts/pages
        $posts = get_posts([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        if (empty($posts)) {
            return new WP_Error('no_posts', 'No published posts or pages found');
        }
        
        // Extract slug from URL
        $url_slug = basename(wp_parse_url($url, PHP_URL_PATH));
        
        // Find similar URLs based on slug matching
        $matches = [];
        foreach ($posts as $post) {
            $post_slug = $post->post_name;
            $post_url = get_permalink($post->ID);
            
            // Calculate similarity
            similar_text($url_slug, $post_slug, $percent);
            
            if ($percent > 30) { // At least 30% similar
                $matches[] = [
                    'url' => $post_url,
                    'title' => $post->post_title,
                    'slug' => $post_slug,
                    'similarity' => $percent
                ];
            }
        }
        
        // Sort by similarity
        usort($matches, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        if (empty($matches)) {
            return new WP_Error('no_matches', 'No similar URLs found');
        }
        
        // Build suggestion response
        $best_match = $matches[0];
        $alternatives = array_slice(array_column($matches, 'url'), 1, 3);
        
        return [
            'suggested_url' => $best_match['url'],
            'confidence' => $best_match['similarity'] > 70 ? 'high' : ($best_match['similarity'] > 50 ? 'medium' : 'low'),
            'reason' => sprintf(
                'URL slug "%s" is %.1f%% similar to "%s"',
                $url_slug,
                $best_match['similarity'],
                $best_match['slug']
            ),
            'alternatives' => $alternatives
        ];
    }
    
    /**
     * Parse AI response
     *
     * @param string $response AI response
     * @return array|false Parsed suggestion
     */
    private function parse_ai_response($response) {
        // Try to extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }
        
        return false;
    }

    /**
     * Send a stored redirect without using an unsanitised Location header.
     *
     * @param string $target Stored target.
     * @param int    $status HTTP status.
     */
    private function send_redirect($target, $status) {
        if (!in_array($status, array(301, 302, 307, 308, 410), true)) {
            $status = 301;
        }
        if ($status === 410) {
            status_header(410);
            nocache_headers();
            return;
        }
        if (is_string($target) && isset($target[0]) && $target[0] === '/') {
            wp_safe_redirect(home_url($target), $status);
            return;
        }
        if (!is_string($target) || !wp_http_validate_url($target)) {
            return;
        }
        $local = wp_validate_redirect($target, false);
        if ($local) {
            wp_safe_redirect($local, $status);
            return;
        }
        if (preg_match('#^https?://#i', $target)) {
            wp_redirect(esc_url_raw($target), $status);
        }
    }

    /**
     * Keep a 301 when a published slug changes.
     *
     * @param int     $post_id     Post ID.
     * @param WP_Post $post_after  Post after save.
     * @param WP_Post $post_before Post before save.
     */
    public function redirect_changed_slug($post_id, $post_after, $post_before) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        if (!$post_after instanceof WP_Post || !$post_before instanceof WP_Post) {
            return;
        }
        if ($post_after->post_name === $post_before->post_name || $post_before->post_name === '' || $post_after->post_status !== 'publish') {
            return;
        }
        $old_path = wp_parse_url(get_permalink($post_before), PHP_URL_PATH);
        $new_url = get_permalink($post_after);
        if (!is_string($old_path) || $old_path === '' || !is_string($new_url) || $new_url === '') {
            return;
        }
        $this->ensure_tables();
        $this->create_redirect($old_path, $new_url, '301');
    }
    
    /**
     * Create redirect
     *
     * @param string $source Source URL
     * @param string $target Target URL
     * @param string $type Redirect type (301, 302, 307)
     * @param array $options Additional options
     * @return int|WP_Error Redirect ID or error
     */
    public function create_redirect($source, $target, $type = '301', $options = []) {
        global $wpdb;
        
        // Validate redirect type
        if (!in_array($type, array('301', '302', '307', '308', '410'), true)) {
            return new WP_Error('invalid_type', 'Invalid redirect type');
        }
        
        $is_regex = !empty($options['is_regex']);
        if ($is_regex) {
            $source = trim((string) $source);
            if ($source === '' || strlen($source) > 200) {
                return new WP_Error('invalid_regex', 'Regular expression must be 1 to 200 characters');
            }
            $pattern = self::regex_pattern($source);
            if (@preg_match($pattern, '') === false) {
                return new WP_Error('invalid_regex', 'Invalid regular expression');
            }
        } else {
            $source = self::normalize_path($source);
        }
        $target = ($type === '410') ? '' : self::sanitize_target($target);
        if ($source === '' || ($type !== '410' && ($target === '' || (!$is_regex && $source === self::normalize_path($target))))) {
            return new WP_Error('invalid_urls', 'Source and target URLs are required');
        }
        $type = (string) $type;
        
        $table_name = $wpdb->prefix . 'aiseo_redirects';
        
        // Check if redirect already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE source_url = %s",
            $source
        ));
        
        if ($existing) {
            return new WP_Error('redirect_exists', 'Redirect already exists for this URL');
        }
        
        // Insert redirect
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $result = $wpdb->insert(
            $table_name,
            [
                'source_url' => $source,
                'target_url' => $target,
                'redirect_type' => $type,
                'is_regex' => $is_regex ? 1 : 0,
                'hits' => 0,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create redirect');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get redirects
     *
     * @param array $args Query arguments
     * @return array Redirects
     */
    public function get_redirects($args = []) {
        global $wpdb;
        
        $defaults = [
            'limit' => 100,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $table_name = $wpdb->prefix . 'aiseo_redirects';
        
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and ORDER BY are safe, values are prepared
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name}
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query is prepared above with $wpdb->prepare()
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return [
            'redirects' => $results,
            'total' => count($results)
        ];
    }
    
    /**
     * Delete redirect
     *
     * @param int $redirect_id Redirect ID
     * @return bool Success status
     */
    public function delete_redirect($redirect_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aiseo_redirects';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $result = $wpdb->delete(
            $table_name,
            ['id' => absint($redirect_id)],
            ['%d']
        );
        
        return $result > 0;
    }
    
    /**
     * Bulk import redirects from CSV
     *
     * @param string $csv_data CSV data
     * @return array Import results
     */
    public function bulk_import_redirects($csv_data) {
        $lines = explode("\n", $csv_data);
        $imported = 0;
        $errors = [];
        
        foreach ($lines as $index => $line) {
            $data = str_getcsv($line, ',', '"', '\\');
            if ($index === 0 && isset($data[0]) && preg_match('/source/i', (string) $data[0])) {
                continue;
            }
            
            if (count($data) < 2) {
                continue;
            }
            
            $source = trim($data[0]);
            $target = trim($data[1]);
            $type = isset($data[2]) ? trim($data[2]) : '301';
            
            $result = $this->create_redirect($source, $target, $type);
            
            if (is_wp_error($result)) {
                $errors[] = "Line {$index}: " . $result->get_error_message();
            } else {
                $imported++;
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_lines' => count($lines) - 1
        ];
    }
    
    /**
     * Export redirects to CSV
     *
     * @return string CSV data
     */
    public function export_redirects() {
        $redirects = $this->get_redirects(['limit' => 9999]);
        
        $csv = "Source URL,Target URL,Type,Hits,Created\n";
        
        foreach ($redirects['redirects'] as $redirect) {
            $csv .= sprintf(
                '"%s","%s","%s",%d,"%s"' . "\n",
                $redirect['source_url'],
                $redirect['target_url'],
                $redirect['redirect_type'],
                $redirect['hits'],
                $redirect['created_at']
            );
        }
        
        return $csv;
    }
    
    /**
     * Process redirect
     *
     * @param string $requested_url Requested URL
     * @return bool Whether redirect was processed
     */
    public function process_redirect($requested_url) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'aiseo_redirects';
        
        // Check for exact match
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query
        $redirect = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE source_url = %s AND is_regex = 0",
            $requested_url
        ), ARRAY_A);
        
        // Check for regex match if no exact match
        if (!$redirect) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query
            $regex_redirects = $wpdb->get_results(
                "SELECT * FROM {$table_name} WHERE is_regex = 1",
                ARRAY_A
            );
            
            foreach ($regex_redirects as $regex_redirect) {
                $pattern = self::regex_pattern($regex_redirect['source_url']);
                if ($pattern !== '' && @preg_match($pattern, $requested_url)) {
                    $redirect = $regex_redirect;
                    break;
                }
            }
        }
        
        if ($redirect) {
            // Increment hit counter
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update
            $wpdb->update(
                $table_name,
                ['hits' => $redirect['hits'] + 1],
                ['id' => $redirect['id']],
                ['%d'],
                ['%d']
            );
            
            $this->send_redirect($redirect['target_url'], (int) $redirect['redirect_type']);
            exit;
        }
        
        return false;
    }
    
    /**
     * Get redirect statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        global $wpdb;
        
        $redirects_table = $wpdb->prefix . 'aiseo_redirects';
        $errors_table = $wpdb->prefix . 'aiseo_404_log';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $total_redirects = $wpdb->get_var("SELECT COUNT(*) FROM {$redirects_table}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared , WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $total_404s = $wpdb->get_var("SELECT COUNT(*) FROM {$errors_table}");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table statistics
        $total_hits = $wpdb->get_var("SELECT SUM(hits) FROM {$redirects_table}");
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table statistics
        $top_404s = $wpdb->get_results(
            "SELECT url, COUNT(*) as hits, MAX(timestamp) as last_hit
             FROM {$errors_table} 
             GROUP BY url 
             ORDER BY hits DESC 
             LIMIT 10",
            ARRAY_A
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table statistics
        $top_redirects = $wpdb->get_results(
            "SELECT source_url, target_url, hits 
             FROM {$redirects_table} 
             ORDER BY hits DESC 
             LIMIT 10",
            ARRAY_A
        );
        
        return [
            'total_redirects' => (int) $total_redirects,
            'total_404_errors' => (int) $total_404s,
            'total_redirect_hits' => (int) $total_hits,
            'top_404s' => $top_404s,
            'top_redirects' => $top_redirects
        ];
    }
    
    /**
     * Clear cache
     *
     * @param string $type Cache type to clear
     * @return bool Success status
     */
    public function clear_cache($type = 'all') {
        delete_transient('aiseo_redirect_stats');
        delete_transient('aiseo_404_summary');
        
        return true;
    }
}
