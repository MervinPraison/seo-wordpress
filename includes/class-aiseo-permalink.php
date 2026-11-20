<?php
/**
 * AISEO Permalink Optimization
 *
 * @package AISEO
 * @since 1.10.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Permalink Optimization Class
 */
class AISEO_Permalink {
    
    /**
     * Stop words to remove from URLs
     */
    private $stop_words = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
        'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'should', 'could', 'may', 'might', 'must', 'can', 'this', 'that'
    ];
    
    /**
     * Replace a post slug when the optimiser changes it, and keep a 301 from the old path.
     *
     * @param int $post_id Post ID.
     * @return bool Whether the slug changed.
     */
    public function optimize_post_slug($post_id) {
        $post = get_post($post_id);
        if (!$post || !current_user_can('edit_post', $post_id)) {
            return false;
        }

        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        $result = $this->optimize_permalink($post->post_name, (string) $keyword);
        $optimized = isset($result['optimized']) ? $result['optimized'] : '';
        if ($optimized === '' || $optimized === $post->post_name) {
            return false;
        }

        $optimized = wp_unique_post_slug($optimized, $post->ID, $post->post_status, $post->post_type, $post->post_parent);
        if ($optimized === $post->post_name) {
            return false;
        }

        $old_path = wp_parse_url(get_permalink($post), PHP_URL_PATH);
        $updated = wp_update_post(array(
            'ID' => $post->ID,
            'post_name' => $optimized,
        ), true);
        if (is_wp_error($updated) || !$updated) {
            return false;
        }

        if (is_string($old_path) && $old_path !== '' && class_exists('AISEO_Redirects')) {
            $redirects = new AISEO_Redirects();
            $redirects->ensure_tables();
            $redirects->create_redirect($old_path, get_permalink($post->ID), '301');
        }

        return true;
    }

    public function optimize_permalink($slug, $keyword = '') {
        $original = $slug;
        
        // Remove stop words
        $optimized = $this->remove_stop_words($slug);
        
        // Ensure keyword is in slug
        if (!empty($keyword)) {
            $optimized = $this->ensure_keyword_in_slug($optimized, $keyword);
        }
        
        // Limit length
        $optimized = $this->limit_slug_length($optimized);
        
        return [
            'original' => $original,
            'optimized' => $optimized,
            'removed_words' => $this->get_removed_words($original, $optimized),
            'score' => $this->calculate_slug_score($optimized, $keyword),
            'suggestions' => $this->generate_suggestions($slug, $keyword)
        ];
    }
    
    /**
     * Remove stop words from slug
     */
    private function remove_stop_words($slug) {
        $words = explode('-', $slug);
        $filtered = [];
        
        foreach ($words as $word) {
            if (!in_array(strtolower($word), $this->stop_words)) {
                $filtered[] = $word;
            }
        }
        
        return implode('-', $filtered);
    }
    
    /**
     * Ensure keyword is in slug
     */
    private function ensure_keyword_in_slug($slug, $keyword) {
        $keyword_slug = sanitize_title($keyword);
        $keyword_words = explode('-', $keyword_slug);
        
        // Check if all keyword words are in slug
        $slug_words = explode('-', $slug);
        $missing_words = array_diff($keyword_words, $slug_words);
        
        if (!empty($missing_words)) {
            // Prepend missing keyword words
            $slug = $keyword_slug . '-' . $slug;
        }
        
        return $slug;
    }
    
    /**
     * Limit slug length
     */
    private function limit_slug_length($slug, $max_length = 60) {
        if (strlen($slug) <= $max_length) {
            return $slug;
        }
        
        $words = explode('-', $slug);
        $result = [];
        $length = 0;
        
        foreach ($words as $word) {
            if ($length + strlen($word) + 1 <= $max_length) {
                $result[] = $word;
                $length += strlen($word) + 1;
            } else {
                break;
            }
        }
        
        return implode('-', $result);
    }
    
    /**
     * Get removed words
     */
    private function get_removed_words($original, $optimized) {
        $original_words = explode('-', $original);
        $optimized_words = explode('-', $optimized);
        
        return array_diff($original_words, $optimized_words);
    }
    
    /**
     * Calculate slug score
     */
    private function calculate_slug_score($slug, $keyword) {
        $score = 100;
        
        // Length check
        $length = strlen($slug);
        if ($length > 60) {
            $score -= 20;
        } elseif ($length > 50) {
            $score -= 10;
        }
        
        // Keyword check
        if (!empty($keyword)) {
            $keyword_slug = sanitize_title($keyword);
            if (strpos($slug, $keyword_slug) === false) {
                $score -= 30;
            }
        }
        
        // Stop words check
        $words = explode('-', $slug);
        $stop_word_count = 0;
        foreach ($words as $word) {
            if (in_array(strtolower($word), $this->stop_words)) {
                $stop_word_count++;
            }
        }
        if ($stop_word_count > 0) {
            $score -= ($stop_word_count * 5);
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Generate alternative suggestions
     */
    private function generate_suggestions($slug, $keyword) {
        $suggestions = [];
        
        // Suggestion 1: Remove all stop words
        $suggestions[] = $this->remove_stop_words($slug);
        
        // Suggestion 2: Keyword first
        if (!empty($keyword)) {
            $keyword_slug = sanitize_title($keyword);
            $clean_slug = $this->remove_stop_words($slug);
            $suggestions[] = $keyword_slug . '-' . str_replace($keyword_slug, '', $clean_slug);
        }
        
        // Suggestion 3: Short version
        $words = explode('-', $this->remove_stop_words($slug));
        if (count($words) > 4) {
            $suggestions[] = implode('-', array_slice($words, 0, 4));
        }
        
        return array_unique(array_filter($suggestions));
    }
    
    /**
     * Bulk optimize permalinks
     */
    public function bulk_optimize($post_ids, $options = []) {
        $results = [];
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            
            $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
            $optimization = $this->optimize_permalink($post->post_name, $keyword);
            
            $results[$post_id] = $optimization;
            
            // Auto-update if requested
            if (!empty($options['auto_update']) && $optimization['score'] < 80) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_name' => $optimization['optimized']
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Analyze site-wide permalink structure
     */
    public function analyze_site_structure() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necessary for permalink structure check
        $posts = $wpdb->get_results(
            "SELECT ID, post_name FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_type IN ('post', 'page')
             LIMIT 1000"
        );
        
        $stats = [
            'total' => count($posts),
            'with_stop_words' => 0,
            'too_long' => 0,
            'missing_keyword' => 0,
            'avg_length' => 0,
            'avg_score' => 0
        ];
        
        $total_length = 0;
        $total_score = 0;
        
        foreach ($posts as $post) {
            $keyword = get_post_meta($post->ID, '_aiseo_focus_keyword', true);
            $analysis = $this->optimize_permalink($post->post_name, $keyword);
            
            $total_length += strlen($post->post_name);
            $total_score += $analysis['score'];
            
            if (count($analysis['removed_words']) > 0) {
                $stats['with_stop_words']++;
            }
            
            if (strlen($post->post_name) > 60) {
                $stats['too_long']++;
            }
            
            if (!empty($keyword) && $analysis['score'] < 70) {
                $stats['missing_keyword']++;
            }
        }
        
        $stats['avg_length'] = $stats['total'] > 0 ? round($total_length / $stats['total']) : 0;
        $stats['avg_score'] = $stats['total'] > 0 ? round($total_score / $stats['total']) : 0;
        
        return $stats;
    }
}
