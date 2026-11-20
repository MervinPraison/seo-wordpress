<?php
/**
 * AISEO Internal Linking Suggestions
 *
 * AI-powered internal linking recommendations and analysis
 *
 * @package AISEO
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Internal Linking Suggestions Class
 */
class AISEO_Internal_Linking {
    
    /**
     * Cache duration in seconds (24 hours)
     */
    const CACHE_DURATION = 86400;
    
    /**
     * Maximum posts to analyze at once
     */
    const MAX_POSTS_TO_ANALYZE = 20;
    
    /**
     * Get cache key for suggestions
     */
    private function get_cache_key($post_id, $type = 'suggestions') {
        return 'aiseo_linking_' . $type . '_' . $post_id;
    }
    
    /**
     * Clear cache for a post
     */
    public function clear_cache($post_id) {
        delete_transient($this->get_cache_key($post_id, 'suggestions'));
        delete_transient($this->get_cache_key($post_id, 'distribution'));
        delete_transient($this->get_cache_key($post_id, 'opportunities'));
    }
    
    /**
     * Clear all internal linking caches
     */
    public function clear_all_caches() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Clearing transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_aiseo_linking_') . '%'
        ));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('_transient_timeout_aiseo_linking_') . '%'
        ));
    }
    
    /**
     * Get AI-powered internal link suggestions for a post
     *
     * @param int $post_id Post ID
     * @param array $options Options (limit, exclude_ids, context)
     * @return array|WP_Error Link suggestions or error
     */
    public function get_suggestions($post_id, $options = []) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post ID');
        }
        
        $defaults = [
            'limit' => 5,
            'exclude_ids' => [],
            'context' => 'auto', // auto, related, orphan
            'min_relevance' => 0.5,
            'use_cache' => true,
            'force_refresh' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Check cache first (unless force refresh)
        if ($options['use_cache'] && !$options['force_refresh']) {
            $cached = get_transient($this->get_cache_key($post_id, 'suggestions'));
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Get post content and metadata
        $content = $post->post_content;
        $title = $post->post_title;
        $excerpt = $post->post_excerpt ?: wp_trim_words($content, 55);
        
        // Find related posts using AI
        $related_posts = $this->find_related_posts($post_id, $content, $title, $options);
        
        if (is_wp_error($related_posts)) {
            return $related_posts;
        }
        
        // Generate anchor text suggestions for each related post
        $suggestions = [];
        foreach ($related_posts as $related_post) {
            $anchor_suggestions = $this->suggest_anchor_text($content, $related_post);
            
            $suggestions[] = [
                'post_id' => $related_post->ID,
                'post_title' => $related_post->post_title,
                'post_url' => get_permalink($related_post->ID),
                'post_type' => $related_post->post_type,
                'relevance_score' => $related_post->relevance_score ?? 0.8,
                'anchor_suggestions' => $anchor_suggestions,
                'context' => $this->extract_context($content, $related_post->post_title),
                'reason' => $related_post->reason ?? 'Related content',
                'cornerstone' => get_post_meta($related_post->ID, '_aiseo_cornerstone', true) === '1',
            ];
        }
        
        $result = [
            'post_id' => $post_id,
            'post_title' => $title,
            'suggestions' => $suggestions,
            'total' => count($suggestions),
            'generated_at' => current_time('mysql'),
            'cached' => false
        ];
        
        // Cache the results
        if ($options['use_cache']) {
            set_transient($this->get_cache_key($post_id, 'suggestions'), $result, self::CACHE_DURATION);
        }
        
        return $result;
    }
    
    /**
     * Find related posts using AI
     *
     * @param int $post_id Current post ID
     * @param string $content Post content
     * @param string $title Post title
     * @param array $options Options
     * @return array|WP_Error Related posts or error
     */
    private function find_related_posts($post_id, $content, $title, $options) {
        // Get all published posts (excluding current post)
        // Limit to recent posts for performance on large sites
        $posts_per_page = min(self::MAX_POSTS_TO_ANALYZE, 50);
        
        $post_types = get_option('aiseo_sitemap_post_types', null);
        if (!is_array($post_types) || !$post_types) {
            $post_types = get_post_types(array('public' => true), 'names');
            unset($post_types['attachment']);
            $post_types = array_values($post_types);
        }
        if (!$post_types) {
            $post_types = array('post');
        }

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $posts_per_page,
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Necessary to exclude current post
            'post__not_in' => array_merge([$post_id], $options['exclude_ids']),
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true, // Performance optimization
            'update_post_meta_cache' => false, // Don't load meta
            'update_post_term_cache' => false // Don't load terms
        ];
        
        $posts = get_posts($args);
        $cornerstones = get_posts(array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'post__not_in' => array_merge(array($post_id), $options['exclude_ids']),
            'meta_query' => array(
                array(
                    'key' => '_aiseo_cornerstone',
                    'value' => '1',
                ),
            ),
            'no_found_rows' => true,
        ));
        $by_id = array();
        foreach (array_merge($cornerstones, $posts) as $candidate) {
            $by_id[$candidate->ID] = $candidate;
        }
        $posts = array_values($by_id);
        
        if (empty($posts)) {
            return [];
        }

        if (!AISEO_Helpers::get_api_key()) {
            return $this->fallback_related_posts($post_id, $content, $posts, $options['limit']);
        }
        
        // Use AI to analyze relevance
        $api = new AISEO_API();
        
        // Create a concise summary of the current post
        $current_summary = wp_trim_words($content, 100);
        
        // Build prompt for AI to find related posts
        $posts_data = [];
        foreach ($posts as $post) {
            $posts_data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 50)
            ];
        }
        
        $prompt = "Analyze the following post and identify the most related posts for internal linking.\n\n";
        $prompt .= "Current Post:\n";
        $prompt .= "Title: {$title}\n";
        $prompt .= "Content: {$current_summary}\n\n";
        $prompt .= "Available Posts:\n";
        $prompt .= json_encode($posts_data, JSON_PRETTY_PRINT) . "\n\n";
        $prompt .= "Return a JSON array of the top {$options['limit']} most related posts with relevance scores (0-1) and reasons.\n";
        $prompt .= "Format: [{\"id\": 123, \"relevance_score\": 0.9, \"reason\": \"Both discuss SEO optimization\"}]\n";
        $prompt .= "Only return the JSON array, no other text.";
        
        $response = $api->make_request($prompt, [
            'temperature' => 0.3,
            'max_tokens' => 500
        ]);
        
        if (is_wp_error($response)) {
            return $this->fallback_related_posts($post_id, $content, $posts, $options['limit']);
        }
        
        // Parse AI response
        $related_data = json_decode($response, true);
        
        if (!is_array($related_data)) {
            // Fallback to simple keyword matching
            return $this->fallback_related_posts($post_id, $content, $posts, $options['limit']);
        }
        
        // Get full post objects with AI data
        $related_posts = [];
        foreach ($related_data as $item) {
            if (isset($item['id'])) {
                $post = get_post($item['id']);
                if ($post) {
                    $post->relevance_score = $item['relevance_score'] ?? 0.7;
                    $post->reason = $item['reason'] ?? 'Related content';
                    if (get_post_meta($post->ID, '_aiseo_cornerstone', true) === '1') {
                        $post->relevance_score = min(1, (float) $post->relevance_score + 0.15);
                        $post->reason = $post->reason . ' (cornerstone)';
                    }
                    
                    // Filter by minimum relevance
                    if ($post->relevance_score >= $options['min_relevance']) {
                        $related_posts[] = $post;
                    }
                }
            }
        }
        
        return array_slice($related_posts, 0, $options['limit']);
    }
    
    /**
     * Fallback method to find related posts using keyword matching
     *
     * @param int $post_id Current post ID
     * @param string $content Post content
     * @param array $posts Available posts
     * @param int $limit Limit
     * @return array Related posts
     */
    private function fallback_related_posts($post_id, $content, $posts, $limit) {
        // Extract keywords from content
        $keywords = $this->extract_keywords($content);
        
        $scored_posts = [];
        foreach ($posts as $post) {
            $score = 0;
            $post_content = strtolower($post->post_title . ' ' . $post->post_content);
            
            foreach ($keywords as $keyword) {
                if (stripos($post_content, $keyword) !== false) {
                    $score += 0.2;
                }
            }
            
            if (get_post_meta($post->ID, '_aiseo_cornerstone', true) === '1') {
                $score += 0.3;
            }
            if ($score > 0) {
                $post->relevance_score = min($score, 1.0);
                $post->reason = get_post_meta($post->ID, '_aiseo_cornerstone', true) === '1' ? 'Cornerstone content' : 'Keyword match';
                $scored_posts[] = $post;
            }
        }
        
        // Sort by score
        usort($scored_posts, function($a, $b) {
            return $b->relevance_score <=> $a->relevance_score;
        });
        
        return array_slice($scored_posts, 0, $limit);
    }
    
    /**
     * Extract keywords from content
     *
     * @param string $content Content
     * @return array Keywords
     */
    private function extract_keywords($content) {
        // Remove HTML tags
        $text = wp_strip_all_tags($content);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove common stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'may', 'might', 'can', 'this', 'that', 'these', 'those'];
        
        // Split into words
        $words = preg_split('/\s+/', $text);
        
        // Filter and count
        $word_counts = [];
        foreach ($words as $word) {
            $word = trim($word, '.,!?;:');
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $word_counts[$word] = ($word_counts[$word] ?? 0) + 1;
            }
        }
        
        // Sort by frequency
        arsort($word_counts);
        
        // Return top 10 keywords
        return array_slice(array_keys($word_counts), 0, 10);
    }
    
    /**
     * Suggest anchor text for a related post
     *
     * @param string $content Current post content
     * @param WP_Post $related_post Related post
     * @return array Anchor text suggestions
     */
    private function suggest_anchor_text($content, $related_post) {
        if (!AISEO_Helpers::get_api_key()) {
            return array(
                $related_post->post_title,
                __('Read more', 'aiseo'),
            );
        }
        $api = new AISEO_API();
        
        $prompt = "Suggest 3 natural anchor text variations for linking to this article:\n\n";
        $prompt .= "Target Article Title: {$related_post->post_title}\n";
        $prompt .= "Target Article Excerpt: " . wp_trim_words($related_post->post_content, 30) . "\n\n";
        $prompt .= "Context (current article): " . wp_trim_words($content, 50) . "\n\n";
        $prompt .= "Provide 3 anchor text suggestions that are:\n";
        $prompt .= "1. Natural and contextual\n";
        $prompt .= "2. Descriptive but concise (2-5 words)\n";
        $prompt .= "3. SEO-friendly\n\n";
        $prompt .= "Return only a JSON array of strings: [\"anchor 1\", \"anchor 2\", \"anchor 3\"]";
        
        $response = $api->make_request($prompt, [
            'temperature' => 0.5,
            'max_tokens' => 100
        ]);
        
        if (is_wp_error($response)) {
            // Fallback suggestions
            return [
                $related_post->post_title,
                'Read more about ' . strtolower($related_post->post_title),
                'Learn more'
            ];
        }
        
        $suggestions = json_decode($response, true);
        
        if (!is_array($suggestions) || empty($suggestions)) {
            return [
                $related_post->post_title,
                'Read more',
                'Learn more'
            ];
        }
        
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Extract context around a keyword in content
     *
     * @param string $content Content
     * @param string $keyword Keyword
     * @return string Context snippet
     */
    private function extract_context($content, $keyword) {
        $text = wp_strip_all_tags($content);
        $pos = stripos($text, $keyword);
        
        if ($pos === false) {
            return wp_trim_words($text, 20);
        }
        
        // Extract 50 characters before and after
        $start = max(0, $pos - 50);
        $length = min(strlen($text) - $start, 150);
        $context = substr($text, $start, $length);
        
        return '...' . trim($context) . '...';
    }
    
    /**
     * Batch process internal linking suggestions
     * For large sites - process in chunks to avoid memory issues
     *
     * @param array $options Batch options
     * @return array Results
     */
    public function batch_process_suggestions($options = []) {
        $defaults = [
            'post_type' => 'post',
            'batch_size' => 10,
            'offset' => 0,
            'total_limit' => 100,
            'force_refresh' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        $args = [
            'post_type' => $options['post_type'],
            'post_status' => 'publish',
            'posts_per_page' => $options['batch_size'],
            'offset' => $options['offset'],
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids', // Only get IDs for performance
            'no_found_rows' => false // We need total count
        ];
        
        $query = new WP_Query($args);
        $results = [
            'processed' => 0,
            'total_posts' => $query->found_posts,
            'batch_size' => $options['batch_size'],
            'offset' => $options['offset'],
            'has_more' => false,
            'posts' => []
        ];
        
        if (!empty($query->posts)) {
            foreach ($query->posts as $post_id) {
                // Get suggestions for this post
                $suggestions = $this->get_suggestions($post_id, [
                    'limit' => 5,
                    'use_cache' => true,
                    'force_refresh' => $options['force_refresh']
                ]);
                
                if (!is_wp_error($suggestions)) {
                    $post = get_post($post_id);
                    $results['posts'][] = [
                        'post_id' => $post_id,
                        'post_title' => $post ? $post->post_title : '',
                        'suggestions_count' => $suggestions['total'],
                        'cached' => isset($suggestions['cached']) ? $suggestions['cached'] : false
                    ];
                    $results['processed']++;
                }
            }
        }
        
        // Check if there are more posts to process
        $next_offset = $options['offset'] + $options['batch_size'];
        $results['has_more'] = ($next_offset < $query->found_posts && $next_offset < $options['total_limit']);
        $results['next_offset'] = $results['has_more'] ? $next_offset : null;
        
        return $results;
    }
    
    /**
     * Detect orphan pages (pages with no internal links)
     * Optimized for large sites with caching
     *
     * @param array $options Options (post_type, limit)
     * @return array Orphan pages
     */
    public function detect_orphans($options = []) {
        global $wpdb;
        
        $defaults = [
            'post_type' => 'post',
            'limit' => 50,
            'offset' => 0,
            'use_cache' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Check cache first
        $cache_key = 'aiseo_orphans_' . md5(serialize($options));
        if ($options['use_cache']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Get all published posts (optimized for performance)
        $args = [
            'post_type' => $options['post_type'],
            'post_status' => 'publish',
            'posts_per_page' => $options['limit'],
            'offset' => $options['offset'],
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true, // Performance optimization
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];
        
        $posts = get_posts($args);
        $orphans = [];
        
        foreach ($posts as $post) {
            $permalink = get_permalink($post->ID);
            
            // Check if any other post links to this post
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP equivalent
            $link_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_status = 'publish' 
                AND post_content LIKE %s 
                AND ID != %d",
                '%' . $wpdb->esc_like($permalink) . '%',
                $post->ID
            ));
            
            if ($link_count == 0) {
                $orphans[] = [
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_url' => $permalink,
                    'post_type' => $post->post_type,
                    'post_date' => $post->post_date,
                    'internal_links' => 0
                ];
            }
        }
        
        $result = [
            'orphans' => $orphans,
            'total' => count($orphans),
            'checked' => count($posts),
            'offset' => $options['offset'],
            'limit' => $options['limit'],
            'post_type' => $options['post_type']
        ];
        
        // Cache the results for 1 hour (orphan detection is expensive)
        if ($options['use_cache']) {
            set_transient($cache_key, $result, 3600);
        }
        
        return $result;
    }
    
    /**
     * Analyze internal link distribution
     *
     * @param int $post_id Post ID
     * @return array Link distribution analysis
     */
    public function analyze_distribution($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post ID');
        }
        
        $content = $post->post_content;
        
        // Extract all internal links
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches);
        
        $site_url = get_site_url();
        $internal_links = [];
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $url = $matches[1][$i];
            $anchor = wp_strip_all_tags($matches[2][$i]);
            
            // Check if it's an internal link
            if (strpos($url, $site_url) !== false || strpos($url, '/') === 0) {
                $internal_links[] = [
                    'url' => $url,
                    'anchor_text' => $anchor,
                    'anchor_length' => strlen($anchor)
                ];
            }
        }
        
        // Analyze distribution
        $total_links = count($internal_links);
        $unique_urls = count(array_unique(array_column($internal_links, 'url')));
        $avg_anchor_length = $total_links > 0 ? array_sum(array_column($internal_links, 'anchor_length')) / $total_links : 0;
        
        return [
            'post_id' => $post_id,
            'post_title' => $post->post_title,
            'total_internal_links' => $total_links,
            'unique_destinations' => $unique_urls,
            'average_anchor_length' => round($avg_anchor_length, 1),
            'links' => $internal_links,
            'recommendations' => $this->get_distribution_recommendations($total_links, $unique_urls)
        ];
    }
    
    /**
     * Get recommendations based on link distribution
     *
     * @param int $total_links Total internal links
     * @param int $unique_urls Unique URLs
     * @return array Recommendations
     */
    private function get_distribution_recommendations($total_links, $unique_urls) {
        $recommendations = [];
        
        if ($total_links < 3) {
            $recommendations[] = 'Add more internal links (aim for 3-5 per post)';
        } elseif ($total_links > 10) {
            $recommendations[] = 'Consider reducing internal links (too many may dilute value)';
        }
        
        if ($unique_urls < $total_links * 0.7) {
            $recommendations[] = 'Link to more diverse pages (avoid linking to same pages repeatedly)';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Internal linking distribution looks good!';
        }
        
        return $recommendations;
    }
    
    /**
     * Get link opportunities (places where links could be added)
     *
     * @param int $post_id Post ID
     * @return array Link opportunities
     */
    public function get_opportunities($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post ID');
        }
        
        // Get suggestions
        $suggestions = $this->get_suggestions($post_id, ['limit' => 10]);
        
        if (is_wp_error($suggestions)) {
            return $suggestions;
        }
        
        // Analyze current content for link placement opportunities
        $content = $post->post_content;
        $opportunities = [];
        
        foreach ($suggestions['suggestions'] as $suggestion) {
            // Find potential placement locations in content
            $target_title = strtolower($suggestion['post_title']);
            $content_lower = strtolower($content);
            
            // Check if target title appears in content (good opportunity)
            if (stripos($content_lower, $target_title) !== false) {
                $opportunities[] = [
                    'type' => 'exact_match',
                    'target_post' => $suggestion['post_id'],
                    'target_title' => $suggestion['post_title'],
                    'target_url' => $suggestion['post_url'],
                    'opportunity' => "The phrase '{$suggestion['post_title']}' appears in your content - perfect for a link",
                    'priority' => 'high'
                ];
            } else {
                $opportunities[] = [
                    'type' => 'contextual',
                    'target_post' => $suggestion['post_id'],
                    'target_title' => $suggestion['post_title'],
                    'target_url' => $suggestion['post_url'],
                    'opportunity' => "Related content - consider adding a link in relevant section",
                    'priority' => 'medium',
                    'suggested_anchors' => $suggestion['anchor_suggestions']
                ];
            }
        }
        
        return [
            'post_id' => $post_id,
            'opportunities' => $opportunities,
            'total' => count($opportunities)
        ];
    }
}
