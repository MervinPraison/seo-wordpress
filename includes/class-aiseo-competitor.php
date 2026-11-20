<?php
/**
 * AISEO Competitor Analysis Class
 * 
 * Analyze competitor websites and compare SEO metrics
 *
 * @package AISEO
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Competitor {
    
    /**
     * Add a competitor
     *
     * @param string $url Competitor URL
     * @param string $name Competitor name
     * @return int|WP_Error Competitor ID or error
     */
    public function add_competitor($url, $name = '') {
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided');
        }
        
        // Parse URL to get domain
        $parsed = wp_parse_url($url);
        $domain = $parsed['host'] ?? '';
        
        if (empty($name)) {
            $name = $domain;
        }
        
        // Check if competitor already exists
        $competitors = $this->get_competitors();
        foreach ($competitors as $competitor) {
            if ($competitor['domain'] === $domain) {
                return new WP_Error('duplicate', 'Competitor already exists');
            }
        }
        
        // Add competitor
        $competitor = [
            'id' => uniqid('comp_'),
            'name' => $name,
            'url' => $url,
            'domain' => $domain,
            'added_date' => current_time('mysql'),
            'last_analyzed' => null,
            'status' => 'active'
        ];
        
        $competitors[] = $competitor;
        update_option('aiseo_competitors', $competitors);
        
        return $competitor['id'];
    }
    
    /**
     * Get all competitors
     *
     * @return array Competitors list
     */
    public function get_competitors() {
        return get_option('aiseo_competitors', []);
    }
    
    /**
     * Get competitor by ID
     *
     * @param string $id Competitor ID
     * @return array|null Competitor data or null
     */
    public function get_competitor($id) {
        $competitors = $this->get_competitors();
        
        foreach ($competitors as $competitor) {
            if ($competitor['id'] === $id) {
                return $competitor;
            }
        }
        
        return null;
    }
    
    /**
     * Remove a competitor
     *
     * @param string $id Competitor ID
     * @return bool Success
     */
    public function remove_competitor($id) {
        $competitors = $this->get_competitors();
        $found = false;
        
        foreach ($competitors as $key => $competitor) {
            if ($competitor['id'] === $id) {
                unset($competitors[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            update_option('aiseo_competitors', array_values($competitors));
            // Also remove analysis data
            delete_option('aiseo_competitor_analysis_' . $id);
            return true;
        }
        
        return false;
    }
    
    /**
     * Analyze competitor website
     *
     * @param string $id Competitor ID
     * @return array|WP_Error Analysis results or error
     */
    public function analyze_competitor($id) {
        $competitor = $this->get_competitor($id);
        
        if (!$competitor) {
            return new WP_Error('not_found', 'Competitor not found');
        }
        
        // Fetch competitor page
        $response = wp_remote_get($competitor['url'], [
            'timeout' => 30,
            'user-agent' => 'AISEO Competitor Analyzer/1.0'
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $html = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);
        
        // Parse HTML
        $analysis = $this->parse_html($html, $competitor['url']);
        $analysis['status_code'] = $status_code;
        $analysis['analyzed_at'] = current_time('mysql');
        $analysis['competitor_id'] = $id;
        
        // Save analysis
        update_option('aiseo_competitor_analysis_' . $id, $analysis);
        
        // Update competitor last_analyzed
        $competitors = $this->get_competitors();
        foreach ($competitors as $key => $comp) {
            if ($comp['id'] === $id) {
                $competitors[$key]['last_analyzed'] = current_time('mysql');
                break;
            }
        }
        update_option('aiseo_competitors', $competitors);
        
        return $analysis;
    }
    
    /**
     * Parse HTML and extract SEO data
     *
     * @param string $html HTML content
     * @param string $url Page URL
     * @return array Parsed data
     */
    private function parse_html($html, $url) {
        $data = [
            'title' => '',
            'meta_description' => '',
            'h1_tags' => [],
            'h2_tags' => [],
            'meta_keywords' => '',
            'canonical' => '',
            'og_title' => '',
            'og_description' => '',
            'og_image' => '',
            'twitter_card' => '',
            'twitter_title' => '',
            'twitter_description' => '',
            'robots' => '',
            'word_count' => 0,
            'image_count' => 0,
            'link_count' => 0,
            'schema_types' => []
        ];
        
        // Use DOMDocument to parse HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();
        
        // Title
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $data['title'] = trim($titles->item(0)->textContent);
        }
        
        // Meta tags
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $name = $meta->getAttribute('name');
            $property = $meta->getAttribute('property');
            $content = $meta->getAttribute('content');
            
            if ($name === 'description') {
                $data['meta_description'] = $content;
            } elseif ($name === 'keywords') {
                $data['meta_keywords'] = $content;
            } elseif ($name === 'robots') {
                $data['robots'] = $content;
            } elseif ($property === 'og:title') {
                $data['og_title'] = $content;
            } elseif ($property === 'og:description') {
                $data['og_description'] = $content;
            } elseif ($property === 'og:image') {
                $data['og_image'] = $content;
            } elseif ($name === 'twitter:card') {
                $data['twitter_card'] = $content;
            } elseif ($name === 'twitter:title') {
                $data['twitter_title'] = $content;
            } elseif ($name === 'twitter:description') {
                $data['twitter_description'] = $content;
            }
        }
        
        // Canonical
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            if ($link->getAttribute('rel') === 'canonical') {
                $data['canonical'] = $link->getAttribute('href');
                break;
            }
        }
        
        // H1 tags
        $h1s = $dom->getElementsByTagName('h1');
        foreach ($h1s as $h1) {
            $data['h1_tags'][] = trim($h1->textContent);
        }
        
        // H2 tags (limit to 10)
        $h2s = $dom->getElementsByTagName('h2');
        $count = 0;
        foreach ($h2s as $h2) {
            if ($count >= 10) break;
            $data['h2_tags'][] = trim($h2->textContent);
            $count++;
        }
        
        // Word count (approximate from body text)
        $body = $dom->getElementsByTagName('body');
        if ($body->length > 0) {
            $text = wp_strip_all_tags($body->item(0)->textContent);
            $data['word_count'] = str_word_count($text);
        }
        
        // Image count
        $data['image_count'] = $dom->getElementsByTagName('img')->length;
        
        // Link count
        $data['link_count'] = $dom->getElementsByTagName('a')->length;
        
        // Schema.org structured data
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            if ($script->getAttribute('type') === 'application/ld+json') {
                $json = json_decode($script->textContent, true);
                if ($json && isset($json['@type'])) {
                    $data['schema_types'][] = $json['@type'];
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get competitor analysis
     *
     * @param string $id Competitor ID
     * @return array|null Analysis data or null
     */
    public function get_analysis($id) {
        return get_option('aiseo_competitor_analysis_' . $id, null);
    }
    
    /**
     * Compare with own site
     *
     * @param string $competitor_id Competitor ID
     * @param int $post_id Own post ID to compare
     * @return array Comparison results
     */
    public function compare_with_site($competitor_id, $post_id = null) {
        $competitor_analysis = $this->get_analysis($competitor_id);
        
        if (!$competitor_analysis) {
            return new WP_Error('no_analysis', 'No analysis data found for competitor');
        }
        
        // Get own site data
        if ($post_id) {
            $own_data = $this->get_post_seo_data($post_id);
        } else {
            $own_data = $this->get_homepage_seo_data();
        }
        
        // Compare metrics
        $comparison = [
            'competitor' => $competitor_analysis,
            'own_site' => $own_data,
            'differences' => [
                'title_length' => [
                    'competitor' => strlen($competitor_analysis['title']),
                    'own' => strlen($own_data['title']),
                    'diff' => strlen($own_data['title']) - strlen($competitor_analysis['title'])
                ],
                'description_length' => [
                    'competitor' => strlen($competitor_analysis['meta_description']),
                    'own' => strlen($own_data['meta_description']),
                    'diff' => strlen($own_data['meta_description']) - strlen($competitor_analysis['meta_description'])
                ],
                'word_count' => [
                    'competitor' => $competitor_analysis['word_count'],
                    'own' => $own_data['word_count'],
                    'diff' => $own_data['word_count'] - $competitor_analysis['word_count']
                ],
                'h1_count' => [
                    'competitor' => count($competitor_analysis['h1_tags']),
                    'own' => count($own_data['h1_tags']),
                    'diff' => count($own_data['h1_tags']) - count($competitor_analysis['h1_tags'])
                ],
                'image_count' => [
                    'competitor' => $competitor_analysis['image_count'],
                    'own' => $own_data['image_count'],
                    'diff' => $own_data['image_count'] - $competitor_analysis['image_count']
                ],
                'link_count' => [
                    'competitor' => $competitor_analysis['link_count'],
                    'own' => $own_data['link_count'],
                    'diff' => $own_data['link_count'] - $competitor_analysis['link_count']
                ]
            ],
            'recommendations' => []
        ];
        
        // Generate recommendations
        if (strlen($own_data['title']) < 30) {
            $comparison['recommendations'][] = 'Consider lengthening your title tag (currently ' . strlen($own_data['title']) . ' characters)';
        }
        
        if (strlen($own_data['meta_description']) < 120) {
            $comparison['recommendations'][] = 'Your meta description is shorter than recommended (currently ' . strlen($own_data['meta_description']) . ' characters)';
        }
        
        if ($own_data['word_count'] < $competitor_analysis['word_count']) {
            $comparison['recommendations'][] = 'Competitor has more content (' . $competitor_analysis['word_count'] . ' vs ' . $own_data['word_count'] . ' words)';
        }
        
        if (count($own_data['h1_tags']) === 0) {
            $comparison['recommendations'][] = 'Add an H1 heading to your page';
        }
        
        if (empty($own_data['meta_description'])) {
            $comparison['recommendations'][] = 'Add a meta description to your page';
        }
        
        return $comparison;
    }
    
    /**
     * Get SEO data for a post
     *
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function get_post_seo_data($post_id) {
        $post = get_post($post_id);
        
        return [
            'title' => get_post_meta($post_id, '_aiseo_meta_title', true) ?: $post->post_title,
            'meta_description' => get_post_meta($post_id, '_aiseo_meta_description', true) ?: '',
            'h1_tags' => $this->extract_h1_from_content($post->post_content),
            'h2_tags' => $this->extract_h2_from_content($post->post_content),
            'word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
            'image_count' => substr_count($post->post_content, '<img'),
            'link_count' => substr_count($post->post_content, '<a ')
        ];
    }
    
    /**
     * Get SEO data for homepage
     *
     * @return array SEO data
     */
    private function get_homepage_seo_data() {
        return [
            'title' => get_bloginfo('name') . ' - ' . get_bloginfo('description'),
            'meta_description' => get_bloginfo('description'),
            'h1_tags' => [get_bloginfo('name')],
            'h2_tags' => [],
            'word_count' => 0,
            'image_count' => 0,
            'link_count' => 0
        ];
    }
    
    /**
     * Extract H1 tags from content
     *
     * @param string $content Post content
     * @return array H1 tags
     */
    private function extract_h1_from_content($content) {
        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/i', $content, $matches);
        return array_map('wp_strip_all_tags', $matches[1] ?? []);
    }
    
    /**
     * Extract H2 tags from content
     *
     * @param string $content Post content
     * @return array H2 tags
     */
    private function extract_h2_from_content($content) {
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $content, $matches);
        return array_map('wp_strip_all_tags', array_slice($matches[1] ?? [], 0, 10));
    }
    
    /**
     * Get competitor summary statistics
     *
     * @return array Summary statistics
     */
    public function get_summary() {
        $competitors = $this->get_competitors();
        
        $summary = [
            'total_competitors' => count($competitors),
            'analyzed' => 0,
            'pending' => 0,
            'active' => 0,
            'competitors' => []
        ];
        
        foreach ($competitors as $competitor) {
            if ($competitor['last_analyzed']) {
                $summary['analyzed']++;
            } else {
                $summary['pending']++;
            }
            
            if ($competitor['status'] === 'active') {
                $summary['active']++;
            }
            
            $analysis = $this->get_analysis($competitor['id']);
            $summary['competitors'][] = [
                'id' => $competitor['id'],
                'name' => $competitor['name'],
                'domain' => $competitor['domain'],
                'last_analyzed' => $competitor['last_analyzed'],
                'has_analysis' => !empty($analysis)
            ];
        }
        
        return $summary;
    }
}
