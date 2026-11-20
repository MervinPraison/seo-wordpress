<?php
/**
 * AISEO Content Suggestions
 *
 * AI-powered content topic suggestions and optimization tips
 *
 * @package AISEO
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Content Suggestions Class
 */
class AISEO_Content_Suggestions {
    
    /**
     * Cache duration in seconds (24 hours)
     */
    const CACHE_DURATION = 86400;
    
    /**
     * Get cache key
     */
    private function get_cache_key($type, $identifier) {
        return 'aiseo_content_' . $type . '_' . md5($identifier);
    }
    
    /**
     * Get AI-powered topic suggestions
     *
     * @param array $options Options (niche, keywords, count)
     * @return array|WP_Error Topic suggestions
     */
    public function get_topic_suggestions($options = []) {
        $defaults = [
            'niche' => '',
            'keywords' => [],
            'count' => 10,
            'use_cache' => true,
            'force_refresh' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Validate input
        if (empty($options['niche']) && empty($options['keywords'])) {
            return new WP_Error('invalid_input', 'Please provide a niche or keywords');
        }
        
        // Check cache
        $cache_key = $this->get_cache_key('topics', serialize($options));
        if ($options['use_cache'] && !$options['force_refresh']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Build AI prompt
        $niche = $options['niche'];
        $keywords = !empty($options['keywords']) ? implode(', ', $options['keywords']) : '';
        $count = $options['count'];
        
        $prompt = "Generate {$count} SEO-optimized blog post topic ideas";
        if (!empty($niche)) {
            $prompt .= " for the {$niche} niche";
        }
        if (!empty($keywords)) {
            $prompt .= " related to these keywords: {$keywords}";
        }
        $prompt .= ". For each topic, provide:
1. Title (compelling and SEO-friendly)
2. Target keyword
3. Search intent (informational/commercial/transactional)
4. Estimated difficulty (easy/medium/hard)
5. Brief description (1-2 sentences)

Format as JSON array with keys: title, keyword, intent, difficulty, description";
        
        // Call OpenAI API
        $api = new AISEO_API();
        $response = $api->make_request($prompt, [
            'max_tokens' => 2000,
            'temperature' => 0.8 // Higher creativity for topics
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse response
        $topics = $this->parse_topic_response($response);
        
        $result = [
            'topics' => $topics,
            'total' => count($topics),
            'niche' => $niche,
            'keywords' => $options['keywords'],
            'generated_at' => current_time('mysql')
        ];
        
        // Cache results
        if ($options['use_cache']) {
            set_transient($cache_key, $result, self::CACHE_DURATION);
        }
        
        return $result;
    }
    
    /**
     * Get content optimization tips for a post
     *
     * @param int $post_id Post ID
     * @param array $options Options
     * @return array|WP_Error Optimization tips
     */
    public function get_optimization_tips($post_id, $options = []) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post ID');
        }
        
        $defaults = [
            'focus_keyword' => '',
            'use_cache' => true,
            'force_refresh' => false
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Check cache
        $cache_key = $this->get_cache_key('tips', $post_id);
        if ($options['use_cache'] && !$options['force_refresh']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Get post content and metadata
        $content = $post->post_content;
        $title = $post->post_title;
        $focus_keyword = $options['focus_keyword'] ?: get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        // Analyze current content
        $analysis = new AISEO_Analysis();
        $current_analysis = $analysis->analyze_post($post_id, $focus_keyword);
        
        // Build AI prompt for optimization tips
        $prompt = "Analyze this blog post and provide 5-10 specific, actionable SEO optimization tips.

Title: {$title}
Focus Keyword: {$focus_keyword}
Content Length: " . str_word_count(wp_strip_all_tags($content)) . " words

Current SEO Issues:
" . $this->format_analysis_for_prompt($current_analysis) . "

Provide tips in these categories:
1. Content Quality (keyword usage, depth, value)
2. Readability (sentence structure, paragraphs)
3. Technical SEO (headings, links, images)
4. User Experience (formatting, engagement)
5. SERP Optimization (title, meta, featured snippets)

Format as JSON array with keys: category, priority (high/medium/low), tip, impact";
        
        // Call OpenAI API
        $api = new AISEO_API();
        $response = $api->make_request($prompt, [
            'max_tokens' => 1500,
            'temperature' => 0.7
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse response
        $tips = $this->parse_tips_response($response);
        
        $result = [
            'post_id' => $post_id,
            'post_title' => $title,
            'tips' => $tips,
            'total' => count($tips),
            'current_score' => $current_analysis['overall_score'] ?? 0,
            'generated_at' => current_time('mysql')
        ];
        
        // Cache results
        if ($options['use_cache']) {
            set_transient($cache_key, $result, self::CACHE_DURATION);
        }
        
        return $result;
    }
    
    /**
     * Get trending topics in a niche
     *
     * @param string $niche Niche/industry
     * @param array $options Options
     * @return array|WP_Error Trending topics
     */
    public function get_trending_topics($niche, $options = []) {
        $defaults = [
            'count' => 10,
            'timeframe' => 'week', // week, month, year
            'use_cache' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Check cache
        $cache_key = $this->get_cache_key('trending', $niche . '_' . $options['timeframe']);
        if ($options['use_cache']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Build AI prompt
        $timeframe_text = [
            'week' => 'this week',
            'month' => 'this month',
            'year' => 'this year'
        ];
        
        $prompt = "What are the top {$options['count']} trending topics in the {$niche} industry " . 
                  $timeframe_text[$options['timeframe']] . "? 

For each topic, provide:
1. Topic name
2. Why it's trending
3. Content angle suggestions
4. Target audience
5. Editorial interest band (high/medium/low). Do not invent search volume, CPC, or ranking positions.

Format as JSON array with keys: topic, reason, angles (array), audience, interest";
        
        // Call OpenAI API
        $api = new AISEO_API();
        $response = $api->make_request($prompt, [
            'max_tokens' => 1500,
            'temperature' => 0.7
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse response
        $topics = $this->parse_trending_response($response);
        
        $result = [
            'niche' => $niche,
            'timeframe' => $options['timeframe'],
            'topics' => $topics,
            'total' => count($topics),
            'generated_at' => current_time('mysql')
        ];
        
        // Cache for 6 hours (trending data changes frequently)
        if ($options['use_cache']) {
            set_transient($cache_key, $result, 21600);
        }
        
        return $result;
    }
    
    /**
     * Generate content brief for a topic
     *
     * @param string $topic Topic/title
     * @param array $options Options
     * @return array|WP_Error Content brief
     */
    public function generate_content_brief($topic, $options = []) {
        $defaults = [
            'focus_keyword' => '',
            'target_audience' => '',
            'word_count' => 1500,
            'use_cache' => true
        ];
        $options = wp_parse_args($options, $defaults);
        
        // Check cache
        $cache_key = $this->get_cache_key('brief', md5($topic . serialize($options)));
        if ($options['use_cache']) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        // Build AI prompt
        $prompt = "Create a comprehensive SEO content brief for this topic: '{$topic}'

";
        if (!empty($options['focus_keyword'])) {
            $prompt .= "Focus Keyword: {$options['focus_keyword']}\n";
        }
        if (!empty($options['target_audience'])) {
            $prompt .= "Target Audience: {$options['target_audience']}\n";
        }
        $prompt .= "Target Word Count: {$options['word_count']} words

Provide:
1. SEO Title (60 chars max)
2. Meta Description (160 chars max)
3. Primary Keywords (5-7 keywords)
4. Secondary Keywords (5-7 LSI keywords)
5. Content Structure (H2 and H3 headings outline)
6. Key Points to Cover (bullet points)
7. Internal Linking Opportunities
8. External Resources to Reference
9. Call-to-Action Suggestions

Format as JSON with keys: title, meta_description, primary_keywords, secondary_keywords, structure (array), key_points (array), internal_links (array), external_resources (array), cta_suggestions (array)";
        
        // Call OpenAI API
        $api = new AISEO_API();
        $response = $api->make_request($prompt, [
            'max_tokens' => 2000,
            'temperature' => 0.7
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse response
        $brief = $this->parse_brief_response($response);
        
        $result = [
            'topic' => $topic,
            'brief' => $brief,
            'options' => $options,
            'generated_at' => current_time('mysql')
        ];
        
        // Cache results
        if ($options['use_cache']) {
            set_transient($cache_key, $result, self::CACHE_DURATION);
        }
        
        return $result;
    }
    
    /**
     * Get content gap analysis
     *
     * @param array $existing_topics Existing post titles/topics
     * @param string $niche Niche
     * @return array|WP_Error Content gaps
     */
    public function analyze_content_gaps($existing_topics, $niche) {
        if (empty($existing_topics) || empty($niche)) {
            return new WP_Error('invalid_input', 'Please provide existing topics and niche');
        }
        
        // Build AI prompt
        $topics_list = implode("\n- ", $existing_topics);
        
        $prompt = "Analyze these existing blog topics for the {$niche} niche and identify content gaps:

Existing Topics:
- {$topics_list}

Identify:
1. Missing fundamental topics (beginner content)
2. Missing advanced topics (expert content)
3. Underserved subtopics
4. Trending topics not covered
5. Competitor topics we're missing

For each gap, provide:
- Topic suggestion
- Priority (high/medium/low)
- Reason why it's important
- Estimated traffic potential

Format as JSON array with keys: topic, priority, reason, traffic_potential";
        
        // Call OpenAI API
        $api = new AISEO_API();
        $response = $api->make_request($prompt, [
            'max_tokens' => 1500,
            'temperature' => 0.7
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse response
        $gaps = $this->parse_gaps_response($response);
        
        return [
            'niche' => $niche,
            'existing_count' => count($existing_topics),
            'gaps' => $gaps,
            'total_gaps' => count($gaps),
            'generated_at' => current_time('mysql')
        ];
    }
    
    /**
     * Parse topic suggestions response
     */
    private function parse_topic_response($response) {
        // Try to extract JSON from response
        $json_match = [];
        if (preg_match('/\[[\s\S]*\]/', $response, $json_match)) {
            $topics = json_decode($json_match[0], true);
            if (is_array($topics)) {
                return $topics;
            }
        }
        
        // Fallback: parse as plain text
        return $this->parse_plain_text_topics($response);
    }
    
    /**
     * Parse optimization tips response
     */
    private function parse_tips_response($response) {
        $json_match = [];
        if (preg_match('/\[[\s\S]*\]/', $response, $json_match)) {
            $tips = json_decode($json_match[0], true);
            if (is_array($tips)) {
                return $tips;
            }
        }
        
        return $this->parse_plain_text_tips($response);
    }
    
    /**
     * Parse trending topics response
     */
    private function parse_trending_response($response) {
        $json_match = [];
        if (preg_match('/\[[\s\S]*\]/', $response, $json_match)) {
            $topics = json_decode($json_match[0], true);
            if (is_array($topics)) {
                foreach ($topics as &$topic) {
                    if (!is_array($topic)) {
                        continue;
                    }
                    unset($topic['search_volume'], $topic['volume'], $topic['cpc']);
                    if (empty($topic['interest'])) {
                        $topic['interest'] = 'medium';
                    }
                }
                unset($topic);
                return $topics;
            }
        }
        
        return [];
    }
    
    /**
     * Parse content brief response
     */
    private function parse_brief_response($response) {
        $json_match = [];
        if (preg_match('/\{[\s\S]*\}/', $response, $json_match)) {
            $brief = json_decode($json_match[0], true);
            if (is_array($brief)) {
                return $brief;
            }
        }
        
        return [];
    }
    
    /**
     * Parse content gaps response
     */
    private function parse_gaps_response($response) {
        $json_match = [];
        if (preg_match('/\[[\s\S]*\]/', $response, $json_match)) {
            $gaps = json_decode($json_match[0], true);
            if (is_array($gaps)) {
                return $gaps;
            }
        }
        
        return [];
    }
    
    /**
     * Parse plain text topics (fallback)
     */
    private function parse_plain_text_topics($text) {
        $topics = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 10) {
                continue;
            }
            
            // Extract title from numbered list or bullet points
            $title = preg_replace('/^[\d\.\-\*\+]\s*/', '', $line);
            
            if (!empty($title)) {
                $topics[] = [
                    'title' => $title,
                    'keyword' => '',
                    'intent' => 'informational',
                    'difficulty' => 'medium',
                    'description' => ''
                ];
            }
        }
        
        return $topics;
    }
    
    /**
     * Parse plain text tips (fallback)
     */
    private function parse_plain_text_tips($text) {
        $tips = [];
        $lines = explode("\n", $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strlen($line) < 15) {
                continue;
            }
            
            $tip = preg_replace('/^[\d\.\-\*\+]\s*/', '', $line);
            
            if (!empty($tip)) {
                $tips[] = [
                    'category' => 'General',
                    'priority' => 'medium',
                    'tip' => $tip,
                    'impact' => 'Improves SEO'
                ];
            }
        }
        
        return $tips;
    }
    
    /**
     * Format analysis for AI prompt
     */
    private function format_analysis_for_prompt($analysis) {
        $issues = [];
        
        foreach ($analysis as $key => $data) {
            if ($key === 'overall_score') {
                continue;
            }
            
            if (isset($data['status']) && $data['status'] !== 'good') {
                $issues[] = "- {$key}: {$data['message']}";
            }
        }
        
        return empty($issues) ? "No major issues detected" : implode("\n", $issues);
    }
    
    /**
     * Clear cache
     */
    public function clear_cache($type = 'all') {
        global $wpdb;
        
        if ($type === 'all') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Clearing transients
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_aiseo_content_') . '%'
            ));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing transients
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_timeout_aiseo_content_') . '%'
            ));
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Clearing transients
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_aiseo_content_' . $type . '_') . '%'
            ));
        }
    }
    
    /**
     * Get content suggestions (wrapper for AJAX compatibility)
     *
     * @param string $topic Topic or niche
     * @param int $count Number of suggestions
     * @return array|WP_Error Suggestions array
     */
    public function get_suggestions($topic, $count = 5) {
        if (empty($topic)) {
            return new WP_Error('empty_topic', 'Topic is required');
        }
        
        // Use the existing get_topic_suggestions method
        $result = $this->get_topic_suggestions([
            'niche' => $topic,
            'count' => $count,
            'use_cache' => true
        ]);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Return just the topics array for simpler AJAX response
        return isset($result['topics']) ? $result['topics'] : $result;
    }
}
