<?php
/**
 * AISEO Keyword Research Class
 * 
 * AI-powered keyword research and suggestions
 *
 * @package AISEO
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Keyword_Research {
    
    /**
     * Get keyword suggestions based on seed keyword
     *
     * @param string $seed_keyword Seed keyword
     * @param int $limit Number of suggestions
     * @return array|WP_Error Keyword suggestions or error
     */
    public function get_keyword_suggestions($seed_keyword, $limit = 20) {
        if (empty($seed_keyword)) {
            return new WP_Error('empty_keyword', 'Seed keyword is required');
        }
        
        // Check cache first
        $cache_key = 'aiseo_keyword_suggestions_' . md5($seed_keyword . $limit);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Use AI to generate keyword suggestions
        $api = new AISEO_API();
        
        $prompt = "Generate {$limit} SEO keyword suggestions related to '{$seed_keyword}'. 
        Include variations, long-tail keywords, and question-based keywords.
        Return as a JSON array with this structure:
        [
            {
                \"keyword\": \"keyword phrase\",
                \"type\": \"short-tail|long-tail|question\",
                \"intent\": \"informational|commercial|transactional|navigational\",
                \"estimated_difficulty\": \"easy|medium|hard\"
            }
        ]";
        
        $response = $api->make_request([
            'model' => get_option('aiseo_api_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO keyword research expert. Provide accurate, relevant keyword suggestions.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1500
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse AI response
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Extract JSON from response
        preg_match('/\[.*\]/s', $content, $matches);
        
        if (empty($matches[0])) {
            return new WP_Error('parse_error', 'Failed to parse keyword suggestions');
        }
        
        $keywords = json_decode($matches[0], true);
        
        if (!is_array($keywords)) {
            return new WP_Error('invalid_response', 'Invalid keyword data received');
        }
        
        // Enhance with additional data
        $enhanced_keywords = [];
        foreach ($keywords as $kw) {
            $enhanced_keywords[] = [
                'keyword' => $kw['keyword'] ?? '',
                'type' => $kw['type'] ?? 'short-tail',
                'intent' => $kw['intent'] ?? 'informational',
                'difficulty' => $kw['estimated_difficulty'] ?? 'medium',
                'search_volume' => null,
                'cpc' => null,
                'volume_source' => 'unavailable',
                'difficulty_source' => 'ai_estimate',
                'competition' => $this->calculate_competition($kw['estimated_difficulty'] ?? 'medium')
            ];
        }
        
        // Cache for 7 days
        set_transient($cache_key, $enhanced_keywords, 7 * DAY_IN_SECONDS);
        
        return $enhanced_keywords;
    }
    
    /**
     * Get related keywords
     *
     * @param string $keyword Main keyword
     * @param int $limit Number of related keywords
     * @return array Related keywords
     */
    public function get_related_keywords($keyword, $limit = 10) {
        if (empty($keyword)) {
            return [];
        }
        
        // Check cache
        $cache_key = 'aiseo_related_keywords_' . md5($keyword . $limit);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api = new AISEO_API();
        
        $prompt = "Generate {$limit} semantically related keywords for '{$keyword}'.
        Include LSI keywords, synonyms, and related terms.
        Return as JSON array: [\"keyword1\", \"keyword2\", ...]";
        
        $response = $api->make_request([
            'model' => get_option('aiseo_api_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO expert specializing in semantic keyword research.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        preg_match('/\[.*\]/s', $content, $matches);
        
        if (empty($matches[0])) {
            return [];
        }
        
        $related = json_decode($matches[0], true);
        
        if (!is_array($related)) {
            return [];
        }
        
        // Cache for 7 days
        set_transient($cache_key, $related, 7 * DAY_IN_SECONDS);
        
        return $related;
    }
    
    /**
     * Analyze keyword difficulty
     *
     * @param string $keyword Keyword to analyze
     * @return array Difficulty analysis
     */
    public function analyze_keyword_difficulty($keyword) {
        if (empty($keyword)) {
            return new WP_Error('empty_keyword', 'Keyword is required');
        }
        
        // Check cache
        $cache_key = 'aiseo_keyword_difficulty_' . md5($keyword);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api = new AISEO_API();
        
        $prompt = "Give an editorial opinion of how competitive the keyword '{$keyword}' might be to write for, based only on the phrase itself. Do not invent search volume, CPC, or ranking positions.
        Return as JSON:
        {
            \"difficulty_level\": \"easy|medium|hard|very hard\",
            \"factors\": [\"factor1\", \"factor2\"],
            \"recommendations\": [\"tip1\", \"tip2\"]
        }";
        
        $response = $api->make_request([
            'model' => get_option('aiseo_api_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO difficulty analysis expert.'],
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
            return new WP_Error('parse_error', 'Failed to parse difficulty analysis');
        }
        
        $analysis = json_decode($matches[0], true);
        
        if (!is_array($analysis)) {
            return new WP_Error('invalid_response', 'Invalid analysis data');
        }
        
        // Add keyword length analysis
        $word_count = str_word_count($keyword);
        $analysis['keyword_length'] = $word_count;
        $analysis['keyword_type'] = $word_count <= 2 ? 'short-tail' : 'long-tail';
        $analysis['source'] = 'ai_estimate';
        unset($analysis['difficulty_score']);
        unset($analysis['search_volume']);
        unset($analysis['cpc']);
        
        // Cache for 14 days
        set_transient($cache_key, $analysis, 14 * DAY_IN_SECONDS);
        
        return $analysis;
    }
    
    /**
     * Get question-based keywords
     *
     * @param string $topic Topic or seed keyword
     * @param int $limit Number of questions
     * @return array Question keywords
     */
    public function get_question_keywords($topic, $limit = 15) {
        if (empty($topic)) {
            return [];
        }
        
        // Check cache
        $cache_key = 'aiseo_question_keywords_' . md5($topic . $limit);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api = new AISEO_API();
        
        $prompt = "Generate {$limit} question-based keywords related to '{$topic}'.
        Include: what, why, how, when, where, who questions.
        Return as JSON array with structure:
        [
            {
                \"question\": \"full question\",
                \"question_type\": \"what|why|how|when|where|who\",
                \"search_intent\": \"informational|commercial|transactional\"
            }
        ]";
        
        $response = $api->make_request([
            'model' => get_option('aiseo_api_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert at generating question-based search queries.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.8,
            'max_tokens' => 1000
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        preg_match('/\[.*\]/s', $content, $matches);
        
        if (empty($matches[0])) {
            return [];
        }
        
        $questions = json_decode($matches[0], true);
        
        if (!is_array($questions)) {
            return [];
        }
        
        // Cache for 7 days
        set_transient($cache_key, $questions, 7 * DAY_IN_SECONDS);
        
        return $questions;
    }
    
    /**
     * Get keyword trends and seasonality
     *
     * @param string $keyword Keyword to analyze
     * @return array Trend analysis
     */
    public function analyze_keyword_trends($keyword) {
        if (empty($keyword)) {
            return new WP_Error('empty_keyword', 'Keyword is required');
        }
        
        $api = new AISEO_API();
        
        $prompt = "Analyze search trends and seasonality for '{$keyword}'.
        Return as JSON:
        {
            \"trend\": \"rising|stable|declining\",
            \"seasonality\": \"high|medium|low|none\",
            \"peak_months\": [\"month1\", \"month2\"],
            \"insights\": [\"insight1\", \"insight2\"]
        }";
        
        $response = $api->make_request([
            'model' => get_option('aiseo_api_model', 'gpt-4o-mini'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a search trend analysis expert.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.5,
            'max_tokens' => 600
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        preg_match('/\{.*\}/s', $content, $matches);
        
        if (empty($matches[0])) {
            return new WP_Error('parse_error', 'Failed to parse trend analysis');
        }
        
        $trends = json_decode($matches[0], true);
        
        return is_array($trends) ? $trends : new WP_Error('invalid_response', 'Invalid trend data');
    }
    
    /**
     * Calculate competition level
     *
     * @param string $difficulty Difficulty level
     * @return string Competition level
     */
    private function calculate_competition($difficulty) {
        $map = [
            'easy' => 'Low',
            'medium' => 'Medium',
            'hard' => 'High',
            'very hard' => 'Very High'
        ];
        
        return $map[$difficulty] ?? 'Medium';
    }
    
    /**
     * Get keyword research summary
     *
     * @return array Summary statistics
     */
    public function get_summary() {
        global $wpdb;
        
        // Count cached keywords
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP equivalent
        $cached_suggestions = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_aiseo_keyword_suggestions_') . '%'
            )
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP equivalent
        $cached_related = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_aiseo_related_keywords_') . '%'
            )
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, no WP equivalent
        $cached_difficulty = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_aiseo_keyword_difficulty_') . '%'
            )
        );
        
        return [
            'cached_suggestions' => (int) $cached_suggestions,
            'cached_related' => (int) $cached_related,
            'cached_difficulty' => (int) $cached_difficulty,
            'total_cached' => (int) ($cached_suggestions + $cached_related + $cached_difficulty),
            'cache_duration' => '7-14 days'
        ];
    }
    
    /**
     * Clear keyword research cache
     *
     * @return int Number of items cleared
     */
    public function clear_cache() {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for keyword data
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_aiseo_keyword_') . '%',
                $wpdb->esc_like('_transient_timeout_aiseo_keyword_') . '%'
            )
        );
        
        return (int) $deleted;
    }
}
