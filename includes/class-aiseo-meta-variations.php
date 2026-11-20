<?php
/**
 * AISEO Meta Description Variations
 *
 * @package AISEO
 * @since 1.15.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Meta Description Variations Class
 */
class AISEO_Meta_Variations {
    
    private $api;
    
    public function __construct() {
        $this->api = new AISEO_API();
    }
    
    /**
     * Title options derived from the post, the focus keyword, and the site name.
     *
     * @param WP_Post $post    Post.
     * @param string  $keyword Focus keyword.
     * @return array
     */
    public function title_options($post, $keyword = '') {
        $title = trim($post->post_title);
        $keyword = trim($keyword);
        $site = trim(get_bloginfo('name'));
        $candidates = array();
        if ($title !== '') {
            $candidates[] = $title;
        }
        if ($keyword !== '' && stripos($title, $keyword) === false) {
            $candidates[] = $keyword . ': ' . $title;
        }
        if ($site !== '' && stripos($title, $site) === false) {
            $candidates[] = $title . ' | ' . $site;
        }
        $options = array();
        foreach (array_unique($candidates) as $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $options[] = array(
                'text' => $text,
                'length' => strlen($text),
            );
        }
        return $options;
    }

    /**
     * Generate meta description variations
     *
     * @param string $content Post content
     * @param string $keyword Focus keyword
     * @param int $count Number of variations
     * @return array|WP_Error Variations
     */
    public function generate($content, $keyword = '', $count = 5) {
        $prompt = "Generate {$count} different meta description variations for the following content. Each should be 150-160 characters, compelling, and include a call-to-action.\n\n";
        
        if (!empty($keyword)) {
            $prompt .= "Focus keyword: {$keyword}\n\n";
        }
        
        $prompt .= "Content:\n" . wp_trim_words($content, 300);
        $prompt .= "\n\nFormat as JSON array with 'description', 'length', and 'cta_type' keys.";
        
        $response = $this->api->make_request($prompt, [
            'max_tokens' => 800,
            'temperature' => 0.8
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $variations = $this->parse_variations_response($response);
        
        return [
            'variations' => $variations,
            'best' => $this->select_best_variation($variations, $keyword),
            'keyword' => $keyword
        ];
    }
    
    /**
     * Parse variations response
     */
    private function parse_variations_response($response) {
        // Try JSON first
        $json = json_decode($response, true);
        if ($json && is_array($json)) {
            return array_map(function($item) {
                return [
                    'description' => $item['description'] ?? $item,
                    'length' => isset($item['description']) ? strlen($item['description']) : strlen($item),
                    'cta_type' => $item['cta_type'] ?? 'generic',
                    'score' => $this->score_variation($item['description'] ?? $item)
                ];
            }, $json);
        }
        
        // Parse text format
        $variations = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Remove numbering
            $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
            
            if (strlen($line) > 50 && strlen($line) <= 200) {
                $variations[] = [
                    'description' => $line,
                    'length' => strlen($line),
                    'cta_type' => $this->detect_cta_type($line),
                    'score' => $this->score_variation($line)
                ];
            }
        }
        
        return $variations;
    }
    
    /**
     * Detect CTA type
     */
    private function detect_cta_type($description) {
        $cta_patterns = [
            'learn' => '/learn|discover|find out|explore/i',
            'action' => '/get|start|try|download|sign up/i',
            'question' => '/\?$/',
            'benefit' => '/save|improve|boost|increase|reduce/i'
        ];
        
        foreach ($cta_patterns as $type => $pattern) {
            if (preg_match($pattern, $description)) {
                return $type;
            }
        }
        
        return 'generic';
    }
    
    /**
     * Score variation
     */
    private function score_variation($description) {
        $score = 100;
        $length = strlen($description);
        
        // Length score
        if ($length < 120 || $length > 160) {
            $score -= 20;
        } elseif ($length < 140 || $length > 160) {
            $score -= 10;
        }
        
        // Has CTA
        if (!preg_match('/(learn|discover|get|start|try|find)/i', $description)) {
            $score -= 15;
        }
        
        // Has numbers
        if (preg_match('/\d+/', $description)) {
            $score += 5;
        }
        
        // Has power words
        $power_words = ['proven', 'essential', 'ultimate', 'complete', 'expert', 'professional'];
        foreach ($power_words as $word) {
            if (stripos($description, $word) !== false) {
                $score += 5;
                break;
            }
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Select best variation
     */
    private function select_best_variation($variations, $keyword = '') {
        if (empty($variations)) {
            return null;
        }
        
        $best = $variations[0];
        $best_score = $best['score'];
        
        foreach ($variations as $variation) {
            $score = $variation['score'];
            
            // Bonus for keyword inclusion
            if (!empty($keyword) && stripos($variation['description'], $keyword) !== false) {
                $score += 10;
            }
            
            if ($score > $best_score) {
                $best = $variation;
                $best_score = $score;
            }
        }
        
        return $best;
    }
    
    /**
     * A/B test variations
     */
    public function ab_test($post_id, $variations) {
        $test_data = [
            'post_id' => $post_id,
            'variations' => $variations,
            'started_at' => current_time('mysql'),
            'impressions' => array_fill(0, count($variations), 0),
            'clicks' => array_fill(0, count($variations), 0),
            'ctr' => array_fill(0, count($variations), 0)
        ];
        
        update_post_meta($post_id, '_aiseo_meta_ab_test', $test_data);
        
        return $test_data;
    }
    
    /**
     * Save variations to post
     */
    public function save_to_post($post_id, $variations) {
        update_post_meta($post_id, '_aiseo_meta_variations', $variations);
        return true;
    }
    
    /**
     * Get variations from post
     */
    public function get_from_post($post_id) {
        return get_post_meta($post_id, '_aiseo_meta_variations', true);
    }
}
