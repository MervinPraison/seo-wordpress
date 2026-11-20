<?php
/**
 * AISEO Backlink Monitoring Class
 * 
 * Track and analyze backlinks for SEO monitoring
 *
 * @package AISEO
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Backlink {
    
    /**
     * Add a backlink to track
     *
     * @param string $source_url Source URL (where the link is from)
     * @param string $target_url Target URL (where the link points to)
     * @param array $options Additional options
     * @return array|WP_Error Backlink data or error
     */
    public function add_backlink($source_url, $target_url, $options = []) {
        if (empty($source_url) || empty($target_url)) {
            return new WP_Error('invalid_urls', 'Source and target URLs are required');
        }
        
        // Validate URLs
        if (!filter_var($source_url, FILTER_VALIDATE_URL) || !filter_var($target_url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url_format', 'Invalid URL format');
        }
        
        $backlinks = get_option('aiseo_backlinks', []);
        
        // Generate unique ID
        $backlink_id = 'bl_' . substr(md5($source_url . $target_url . time()), 0, 10);
        
        // Check for duplicates
        foreach ($backlinks as $bl) {
            if ($bl['source_url'] === $source_url && $bl['target_url'] === $target_url) {
                return new WP_Error('duplicate_backlink', 'This backlink already exists', ['id' => $bl['id']]);
            }
        }
        
        $backlink = [
            'id' => $backlink_id,
            'source_url' => esc_url_raw($source_url),
            'target_url' => esc_url_raw($target_url),
            'anchor_text' => isset($options['anchor_text']) ? sanitize_text_field($options['anchor_text']) : '',
            'discovered_at' => current_time('mysql'),
            'last_checked' => current_time('mysql'),
            'status' => 'active', // active, lost, broken
            'follow' => isset($options['follow']) ? (bool) $options['follow'] : true,
            'domain_authority' => null,
            'page_authority' => null,
            'spam_score' => null,
            'notes' => isset($options['notes']) ? sanitize_textarea_field($options['notes']) : ''
        ];
        
        $backlinks[$backlink_id] = $backlink;
        update_option('aiseo_backlinks', $backlinks);
        
        return $backlink;
    }
    
    /**
     * Get all backlinks
     *
     * @param array $filters Filter options
     * @return array List of backlinks
     */
    public function get_backlinks($filters = []) {
        $backlinks = get_option('aiseo_backlinks', []);
        
        if (empty($backlinks)) {
            return [];
        }
        
        // Apply filters
        if (!empty($filters['status'])) {
            $backlinks = array_filter($backlinks, function($bl) use ($filters) {
                return $bl['status'] === $filters['status'];
            });
        }
        
        if (!empty($filters['target_url'])) {
            $backlinks = array_filter($backlinks, function($bl) use ($filters) {
                return strpos($bl['target_url'], $filters['target_url']) !== false;
            });
        }
        
        return array_values($backlinks);
    }
    
    /**
     * Get a specific backlink
     *
     * @param string $backlink_id Backlink ID
     * @return array|WP_Error Backlink data or error
     */
    public function get_backlink($backlink_id) {
        $backlinks = get_option('aiseo_backlinks', []);
        
        if (!isset($backlinks[$backlink_id])) {
            return new WP_Error('not_found', 'Backlink not found');
        }
        
        return $backlinks[$backlink_id];
    }
    
    /**
     * Remove a backlink
     *
     * @param string $backlink_id Backlink ID
     * @return bool|WP_Error True on success, error on failure
     */
    public function remove_backlink($backlink_id) {
        $backlinks = get_option('aiseo_backlinks', []);
        
        if (!isset($backlinks[$backlink_id])) {
            return new WP_Error('not_found', 'Backlink not found');
        }
        
        unset($backlinks[$backlink_id]);
        update_option('aiseo_backlinks', $backlinks);
        
        return true;
    }
    
    /**
     * Check backlink status (verify if link still exists)
     *
     * @param string $backlink_id Backlink ID
     * @return array|WP_Error Status data or error
     */
    public function check_backlink_status($backlink_id) {
        $backlinks = get_option('aiseo_backlinks', []);
        
        if (!isset($backlinks[$backlink_id])) {
            return new WP_Error('not_found', 'Backlink not found');
        }
        
        $backlink = $backlinks[$backlink_id];
        
        // Fetch source page
        $response = wp_remote_get($backlink['source_url'], [
            'timeout' => 15,
            'sslverify' => true,
            'user-agent' => 'AISEO Backlink Checker/1.0'
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_failed', 'Failed to fetch source page: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Check if target URL exists in the page
        $link_exists = strpos($body, $backlink['target_url']) !== false;
        
        // Update backlink status
        $new_status = 'active';
        if ($status_code >= 400) {
            $new_status = 'broken';
        } elseif (!$link_exists) {
            $new_status = 'lost';
        }
        
        $backlinks[$backlink_id]['status'] = $new_status;
        $backlinks[$backlink_id]['last_checked'] = current_time('mysql');
        $backlinks[$backlink_id]['http_status'] = $status_code;
        
        update_option('aiseo_backlinks', $backlinks);
        
        return [
            'backlink_id' => $backlink_id,
            'status' => $new_status,
            'http_status' => $status_code,
            'link_exists' => $link_exists,
            'checked_at' => current_time('mysql')
        ];
    }
    
    /**
     * Analyze backlink quality using AI
     *
     * @param string $backlink_id Backlink ID
     * @return array|WP_Error Quality analysis or error
     */
    public function analyze_backlink_quality($backlink_id) {
        $backlinks = get_option('aiseo_backlinks', []);
        
        if (!isset($backlinks[$backlink_id])) {
            return new WP_Error('not_found', 'Backlink not found');
        }
        
        $backlink = $backlinks[$backlink_id];
        
        $api = new AISEO_API();
        
        $prompt = "Analyze the quality of this backlink for SEO:
        Source URL: {$backlink['source_url']}
        Target URL: {$backlink['target_url']}
        Anchor Text: {$backlink['anchor_text']}
        Follow: " . ($backlink['follow'] ? 'Yes' : 'No') . "
        
        Provide analysis as JSON:
        {
            \"quality_score\": 0-100,
            \"quality_level\": \"excellent|good|average|poor|spam\",
            \"factors\": [\"factor1\", \"factor2\"],
            \"recommendations\": [\"rec1\", \"rec2\"],
            \"risks\": [\"risk1\", \"risk2\"]
        }";
        
        $response = $api->make_request([
            'model' => get_option('aiseo_api_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO backlink quality analysis expert.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.5,
            'max_tokens' => 800
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (empty($matches[0])) {
            return new WP_Error('parse_error', 'Failed to parse quality analysis');
        }
        
        $analysis = json_decode($matches[0], true);
        
        if (!is_array($analysis)) {
            return new WP_Error('invalid_response', 'Invalid analysis data');
        }
        
        // Update backlink with quality scores
        $backlinks[$backlink_id]['quality_score'] = $analysis['quality_score'] ?? 0;
        $backlinks[$backlink_id]['quality_level'] = $analysis['quality_level'] ?? 'average';
        $backlinks[$backlink_id]['last_analyzed'] = current_time('mysql');
        
        update_option('aiseo_backlinks', $backlinks);
        
        return $analysis;
    }
    
    /**
     * Get new backlinks (discovered recently)
     *
     * @param int $days Number of days to look back
     * @return array List of new backlinks
     */
    public function get_new_backlinks($days = 7) {
        $backlinks = get_option('aiseo_backlinks', []);
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $new_backlinks = array_filter($backlinks, function($bl) use ($cutoff_date) {
            return $bl['discovered_at'] >= $cutoff_date;
        });
        
        return array_values($new_backlinks);
    }
    
    /**
     * Get lost backlinks
     *
     * @return array List of lost backlinks
     */
    public function get_lost_backlinks() {
        return $this->get_backlinks(['status' => 'lost']);
    }
    
    /**
     * Get broken backlinks
     *
     * @return array List of broken backlinks
     */
    public function get_broken_backlinks() {
        return $this->get_backlinks(['status' => 'broken']);
    }
    
    /**
     * Generate disavow file
     *
     * @param array $backlink_ids Array of backlink IDs to disavow
     * @return string|WP_Error Disavow file content or error
     */
    public function generate_disavow_file($backlink_ids) {
        if (empty($backlink_ids)) {
            return new WP_Error('empty_list', 'No backlinks provided');
        }
        
        $backlinks = get_option('aiseo_backlinks', []);
        $disavow_content = "# Disavow file generated by AISEO on " . current_time('mysql') . "\n";
        $disavow_content .= "# Total links: " . count($backlink_ids) . "\n\n";
        
        foreach ($backlink_ids as $id) {
            if (isset($backlinks[$id])) {
                $source_url = $backlinks[$id]['source_url'];
                $domain = wp_parse_url($source_url, PHP_URL_HOST);
                
                // Add domain to disavow file
                $disavow_content .= "domain:{$domain}\n";
            }
        }
        
        return $disavow_content;
    }
    
    /**
     * Bulk check all backlinks
     *
     * @param array $options Options for bulk check
     * @return array Results summary
     */
    public function bulk_check_backlinks($options = []) {
        $backlinks = get_option('aiseo_backlinks', []);
        
        if (empty($backlinks)) {
            return [
                'total' => 0,
                'checked' => 0,
                'active' => 0,
                'lost' => 0,
                'broken' => 0,
                'errors' => 0
            ];
        }
        
        $results = [
            'total' => count($backlinks),
            'checked' => 0,
            'active' => 0,
            'lost' => 0,
            'broken' => 0,
            'errors' => 0
        ];
        
        foreach ($backlinks as $id => $backlink) {
            // Rate limiting: 1 second delay between checks
            if ($results['checked'] > 0) {
                sleep(1);
            }
            
            $check_result = $this->check_backlink_status($id);
            
            if (is_wp_error($check_result)) {
                $results['errors']++;
            } else {
                $results['checked']++;
                $results[$check_result['status']]++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get backlink summary statistics
     *
     * @return array Summary statistics
     */
    public function get_summary() {
        $backlinks = get_option('aiseo_backlinks', []);
        
        $summary = [
            'total_backlinks' => count($backlinks),
            'active' => 0,
            'lost' => 0,
            'broken' => 0,
            'new_last_7_days' => 0,
            'average_quality_score' => 0,
            'follow_links' => 0,
            'nofollow_links' => 0
        ];
        
        if (empty($backlinks)) {
            return $summary;
        }
        
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        $total_quality = 0;
        $quality_count = 0;
        
        foreach ($backlinks as $bl) {
            // Count by status
            if (isset($bl['status'])) {
                $summary[$bl['status']]++;
            }
            
            // Count new backlinks
            if ($bl['discovered_at'] >= $cutoff_date) {
                $summary['new_last_7_days']++;
            }
            
            // Count follow/nofollow
            if ($bl['follow']) {
                $summary['follow_links']++;
            } else {
                $summary['nofollow_links']++;
            }
            
            // Calculate average quality
            if (isset($bl['quality_score']) && $bl['quality_score'] > 0) {
                $total_quality += $bl['quality_score'];
                $quality_count++;
            }
        }
        
        if ($quality_count > 0) {
            $summary['average_quality_score'] = round($total_quality / $quality_count, 1);
        }
        
        return $summary;
    }
}
