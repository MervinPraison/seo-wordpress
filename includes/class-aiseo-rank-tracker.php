<?php
/**
 * AISEO Rank Tracking Class
 * 
 * Track keyword rankings and monitor SERP positions
 *
 * @package AISEO
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Rank_Tracker {
    
    /**
     * Track keyword rank
     *
     * @param string $keyword Keyword to track
     * @param int $post_id Post ID (optional)
     * @param string $location Location code (e.g., 'US', 'UK')
     * @return array|WP_Error Tracking result or error
     */
    public function track_keyword($keyword, $post_id = 0, $location = 'US') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        if (empty($keyword)) {
            return new WP_Error('invalid_keyword', 'Keyword is required');
        }
        
        // Get site URL
        $site_url = get_site_url();
        
        $rank_data = $this->check_serp_position($keyword, $site_url, $location);
        
        if (is_wp_error($rank_data)) {
            return $rank_data;
        }
        
        // Insert tracking record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $result = $wpdb->insert(
            $table_name,
            [
                'post_id' => absint($post_id),
                'keyword' => sanitize_text_field($keyword),
                'position' => $rank_data['position'],
                'url' => esc_url_raw($rank_data['url']),
                'date' => current_time('mysql', true),
                'location' => sanitize_text_field($location),
                'serp_features' => maybe_serialize($rank_data['serp_features'])
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to save rank tracking data');
        }
        
        return [
            'id' => $wpdb->insert_id,
            'keyword' => $keyword,
            'position' => $rank_data['position'],
            'url' => $rank_data['url'],
            'location' => $location,
            'serp_features' => $rank_data['serp_features'],
            'tracked_at' => current_time('mysql')
        ];
    }
    
    /**
     * Search position. Returns an error until a real provider is connected.
     *
     * @param string $keyword Keyword to check
     * @param string $site_url Site URL to find
     * @param string $location Location code
     * @return array|WP_Error Position data or error
     */
    private function check_serp_position($keyword, $site_url, $location) {
        return new WP_Error(
            'aiseo_rank_unavailable',
            __('A search position is not available. Connect a search results provider before tracking ranks.', 'aiseo')
        );
    }
    
    /**
     * Get position history for a keyword
     *
     * @param string $keyword Keyword to get history for
     * @param int $days Number of days to look back
     * @return array Position history
     */
    public function get_position_history($keyword, $days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        $date_from = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT position, url, date, location, serp_features 
             FROM {$table_name} 
             WHERE keyword = %s 
             AND DATE(date) >= %s 
             ORDER BY date ASC",
            $keyword,
            $date_from
        ), ARRAY_A);
        
        if (empty($results)) {
            return [];
        }
        
        // Unserialize SERP features
        foreach ($results as &$result) {
            $result['serp_features'] = maybe_unserialize($result['serp_features']);
        }
        
        return $results;
    }
    
    /**
     * Get ranking keywords for a post
     *
     * @param int $post_id Post ID
     * @return array List of keywords
     */
    public function get_ranking_keywords($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT keyword, 
                    (SELECT position FROM {$table_name} rt2 
                     WHERE rt2.keyword = rt1.keyword 
                     AND rt2.post_id = %d 
                     ORDER BY date DESC LIMIT 1) as current_position,
                    (SELECT date FROM {$table_name} rt3 
                     WHERE rt3.keyword = rt1.keyword 
                     AND rt3.post_id = %d 
                     ORDER BY date DESC LIMIT 1) as last_checked
             FROM {$table_name} rt1 
             WHERE post_id = %d 
             GROUP BY keyword",
            $post_id,
            $post_id,
            $post_id
        ), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Compare ranking with competitor
     *
     * @param string $keyword Keyword to compare
     * @param string $competitor_url Competitor URL
     * @return array|WP_Error Comparison data or error
     */
    public function compare_with_competitor($keyword, $competitor_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        if (empty($keyword) || empty($competitor_url)) {
            return new WP_Error('invalid_params', 'Keyword and competitor URL are required');
        }
        
        // Get our latest position
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is prefixed, query uses $wpdb->prepare()
        $our_position = $wpdb->get_var($wpdb->prepare(
            "SELECT position FROM {$table_name} 
             WHERE keyword = %s 
             ORDER BY date DESC LIMIT 1",
            $keyword
        ));
        
        // Simulate competitor position check (in production, use real SERP API)
        $competitor_position = $this->check_competitor_position($keyword, $competitor_url);
        
        if ($competitor_position === null) {
            return [
                'keyword' => $keyword,
                'our_position' => $our_position ? absint($our_position) : null,
                'competitor_position' => null,
                'competitor_url' => $competitor_url,
                'difference' => null,
                'status' => 'unavailable',
            ];
        }
    }
    
    /**
     * Competitor position. Null until a real provider is connected.
     *
     * @param string $keyword Keyword
     * @param string $competitor_url Competitor URL
     * @return int Position
     */
    private function check_competitor_position($keyword, $competitor_url) {
        return null;
    }
    
    /**
     * Detect SERP features for a keyword
     *
     * @param string $keyword Keyword to analyze
     * @return array|WP_Error SERP features or error
     */
    public function detect_serp_features($keyword) {
        if (empty($keyword)) {
            return new WP_Error('invalid_keyword', 'Keyword is required');
        }

        return new WP_Error(
            'aiseo_serp_unavailable',
            __('Search result features are not available. Connect a search results provider before requesting them.', 'aiseo')
        );
    }
    
    /**
     * Get all tracked keywords
     *
     * @param array $filters Filter options
     * @return array List of keywords
     */
    public function get_all_keywords($filters = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        $where = [];
        $params = [];
        
        if (!empty($filters['post_id'])) {
            $where[] = 'post_id = %d';
            $params[] = absint($filters['post_id']);
        }
        
        if (!empty($filters['location'])) {
            $where[] = 'location = %s';
            $params[] = sanitize_text_field($filters['location']);
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $query = "SELECT DISTINCT keyword, 
                  (SELECT position FROM {$table_name} rt2 
                   WHERE rt2.keyword = rt1.keyword " . 
                   (!empty($filters['post_id']) ? "AND rt2.post_id = " . absint($filters['post_id']) : "") .
                   " ORDER BY date DESC LIMIT 1) as current_position,
                  (SELECT date FROM {$table_name} rt3 
                   WHERE rt3.keyword = rt1.keyword " .
                   (!empty($filters['post_id']) ? "AND rt3.post_id = " . absint($filters['post_id']) : "") .
                   " ORDER BY date DESC LIMIT 1) as last_checked,
                  COUNT(*) as tracking_count
                  FROM {$table_name} rt1 
                  {$where_clause}
                  GROUP BY keyword
                  ORDER BY last_checked DESC";
        
        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with $wpdb->prepare() and params
            $results = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Query has no user input, table name is prefixed
            $results = $wpdb->get_results($query, ARRAY_A);
        }
        
        return $results ?: [];
    }
    
    /**
     * Delete tracking data for a keyword
     *
     * @param string $keyword Keyword to delete
     * @param int $post_id Optional post ID filter
     * @return bool|WP_Error True on success, error on failure
     */
    public function delete_keyword($keyword, $post_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        if (empty($keyword)) {
            return new WP_Error('invalid_keyword', 'Keyword is required');
        }
        
        $where = ['keyword' => $keyword];
        $where_format = ['%s'];
        
        if ($post_id > 0) {
            $where['post_id'] = $post_id;
            $where_format[] = '%d';
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $result = $wpdb->delete($table_name, $where, $where_format);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete tracking data');
        }
        
        return true;
    }
    
    /**
     * Get rank tracking summary
     *
     * @return array Summary statistics
     */
    public function get_summary() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aiseo_rank_tracking';
        
        $summary = [
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table statistics
            'total_keywords' => 0,
            'total_tracking_records' => 0,
            'average_position' => 0,
            'top_10_keywords' => 0,
            'top_3_keywords' => 0,
            'position_1_keywords' => 0,
            'tracked_posts' => 0,
            'locations' => []
        ];
        
        // Total tracking records
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $summary['total_tracking_records'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Total unique keywords
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $summary['total_keywords'] = $wpdb->get_var("SELECT COUNT(DISTINCT keyword) FROM {$table_name}");
        
        // Tracked posts
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $summary['tracked_posts'] = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$table_name} WHERE post_id > 0");
        
        // Get latest positions for all keywords
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, complex subquery
        $latest_positions = $wpdb->get_results(
            "SELECT DISTINCT keyword, 
             (SELECT position FROM {$table_name} rt2 
              WHERE rt2.keyword = rt1.keyword 
              ORDER BY date DESC LIMIT 1) as position
             FROM {$table_name} rt1",
            ARRAY_A
        );
        
        if (!empty($latest_positions)) {
            $total_position = 0;
            foreach ($latest_positions as $kw) {
                $position = absint($kw['position']);
                $total_position += $position;
                
                if ($position <= 10) {
                    $summary['top_10_keywords']++;
                }
                if ($position <= 3) {
                    $summary['top_3_keywords']++;
                }
                if ($position === 1) {
                    $summary['position_1_keywords']++;
                }
            }
            
            $summary['average_position'] = round($total_position / count($latest_positions), 1);
        }
        
        // Get locations
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, no WP equivalent
        $locations = $wpdb->get_col("SELECT DISTINCT location FROM {$table_name}");
        $summary['locations'] = $locations ?: [];
        
        return $summary;
    }
}
