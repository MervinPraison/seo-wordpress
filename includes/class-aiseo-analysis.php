<?php
/**
 * AISEO Content Analysis Engine
 *
 * Real-time SEO analysis of content
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Analysis {
    
    /**
     * Analyze content and generate SEO score
     *
     * @param int $post_id Post ID
     * @param string $keyword Focus keyword (optional)
     * @return array Analysis results
     */
    public function analyze_post($post_id, $keyword = '') {
        $post = get_post($post_id);
        
        if (!$post) {
            return array(
                'error' => 'Post not found',
                'overall_score' => 0,
            );
        }
        
        $content = $post->post_content;
        $title = $post->post_title;
        
        // Get focus keyword if not provided
        if (empty($keyword)) {
            $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        }
        
        // Perform individual analyses
        $analyses = array(
            'keyword_density' => $this->analyze_keyword_density($content, $keyword),
            'readability' => $this->analyze_readability($content),
            'paragraph_structure' => $this->analyze_paragraph_structure($content),
            'sentence_length' => $this->analyze_sentence_length($content),
            'content_length' => $this->analyze_content_length($content),
            'keyword_in_title' => $this->check_keyword_in_title($title, $keyword),
            'keyword_in_headings' => $this->check_keyword_in_headings($content, $keyword),
            'keyword_in_intro' => $this->check_keyword_in_intro($content, $keyword),
            'internal_links' => $this->check_internal_links($content),
            'external_links' => $this->check_external_links($content),
            'images' => $this->analyze_images($content),
            'additional_keywords' => $this->analyze_additional_keywords($content, $post_id, $keyword),
        );
        
        // Calculate overall score
        $overall_score = $this->generate_seo_score($analyses);
        
        return array(
            'overall_score' => $overall_score,
            'status' => $this->get_score_status($overall_score),
            'analyses' => $analyses,
            'timestamp' => current_time('mysql'),
        );
    }
    
    /**
     * Analyze keyword density
     *
     * @param string $content Content to analyze
     * @param string $keyword Focus keyword
     * @return array Analysis result
     */
    public function analyze_keyword_density($content, $keyword) {
        if (empty($keyword)) {
            return array(
                'score' => 0,
                'status' => 'warning',
                'message' => 'No focus keyword set',
                'density' => 0,
            );
        }
        
        $content = AISEO_Helpers::strip_shortcodes_and_tags($content);
        $content_lower = strtolower($content);
        $keyword_lower = strtolower($keyword);
        
        // Count keyword occurrences
        $keyword_count = substr_count($content_lower, $keyword_lower);
        
        // Count total words
        $word_count = str_word_count($content);
        
        if ($word_count === 0) {
            return array(
                'score' => 0,
                'status' => 'poor',
                'message' => 'No content to analyze',
                'density' => 0,
                'count' => 0,
            );
        }
        
        // Calculate density percentage
        $density = ($keyword_count / $word_count) * 100;
        
        // Optimal density: 0.5% - 2.5%
        if ($density >= 0.5 && $density <= 2.5) {
            $score = 100;
            $status = 'good';
            $message = sprintf('Keyword density is optimal (%.2f%%)', $density);
        } elseif ($density < 0.5) {
            $score = max(0, 50 - (0.5 - $density) * 50);
            $status = 'warning';
            $message = sprintf('Keyword density is too low (%.2f%%). Try to use the keyword more naturally.', $density);
        } else {
            $score = max(0, 100 - ($density - 2.5) * 20);
            $status = 'warning';
            $message = sprintf('Keyword density is too high (%.2f%%). Avoid keyword stuffing.', $density);
        }
        
        return array(
            'score' => round($score),
            'status' => $status,
            'message' => $message,
            'density' => round($density, 2),
            'count' => $keyword_count,
            'word_count' => $word_count,
        );
    }
    
    /**
     * Analyze readability
     *
     * @param string $content Content to analyze
     * @return array Analysis result
     */
    public function analyze_readability($content) {
        $content = AISEO_Helpers::strip_shortcodes_and_tags($content);
        
        if (empty($content)) {
            return array(
                'score' => 0,
                'status' => 'poor',
                'message' => 'No content to analyze',
                'flesch_kincaid' => 0,
            );
        }
        
        $flesch_score = AISEO_Helpers::calculate_flesch_kincaid($content);
        
        // Flesch Reading Ease score interpretation
        // 90-100: Very easy (5th grade)
        // 80-89: Easy (6th grade)
        // 70-79: Fairly easy (7th grade)
        // 60-69: Standard (8th-9th grade) - OPTIMAL
        // 50-59: Fairly difficult (10th-12th grade)
        // 30-49: Difficult (College)
        // 0-29: Very difficult (College graduate)
        
        if ($flesch_score >= 60 && $flesch_score <= 80) {
            $score = 100;
            $status = 'good';
            $message = sprintf('Content is easy to read (Flesch score: %.1f)', $flesch_score);
        } elseif ($flesch_score >= 50 && $flesch_score < 60) {
            $score = 80;
            $status = 'ok';
            $message = sprintf('Content readability is acceptable (Flesch score: %.1f)', $flesch_score);
        } elseif ($flesch_score >= 80) {
            $score = 90;
            $status = 'good';
            $message = sprintf('Content is very easy to read (Flesch score: %.1f)', $flesch_score);
        } else {
            $score = max(0, $flesch_score);
            $status = 'warning';
            $message = sprintf('Content is difficult to read (Flesch score: %.1f). Try shorter sentences and simpler words.', $flesch_score);
        }
        
        return array(
            'score' => round($score),
            'status' => $status,
            'message' => $message,
            'flesch_kincaid' => round($flesch_score, 1),
            'reading_level' => $this->get_reading_level($flesch_score),
        );
    }

    /**
     * Whether extra focus keywords appear in the content.
     *
     * @param string $content Content.
     * @param int    $post_id Post ID.
     * @param string $primary Primary keyword.
     * @return array
     */
    public function analyze_additional_keywords($content, $post_id, $primary) {
        $keywords = AISEO_Helpers::get_additional_keywords($post_id);
        if (!$keywords) {
            return array(
                'score' => 100,
                'status' => 'ok',
                'message' => __('No extra keywords set.', 'aiseo'),
                'missing' => array(),
            );
        }
        $haystack = strtolower(AISEO_Helpers::strip_shortcodes_and_tags($content . ' ' . $primary));
        $missing = array();
        foreach ($keywords as $keyword) {
            if (strpos($haystack, strtolower($keyword)) === false) {
                $missing[] = $keyword;
            }
        }
        if (!$missing) {
            return array(
                'score' => 100,
                'status' => 'good',
                'message' => __('All extra keywords appear in the content.', 'aiseo'),
                'missing' => array(),
            );
        }
        return array(
            'score' => 50,
            'status' => 'warning',
            'message' => sprintf(
                /* translators: %s: comma-separated keywords */
                __('Extra keywords missing from the content: %s.', 'aiseo'),
                implode(', ', $missing)
            ),
            'missing' => $missing,
        );
    }
    
    /**
     * Analyze paragraph structure
     *
     * @param string $content Content to analyze
     * @return array Analysis result
     */
    public function analyze_paragraph_structure($content) {
        $content = AISEO_Helpers::strip_shortcodes_and_tags($content);
        
        // Split into paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $paragraph_count = count($paragraphs);
        
        if ($paragraph_count === 0) {
            return array(
                'score' => 0,
                'status' => 'poor',
                'message' => 'No paragraphs found',
                'count' => 0,
            );
        }
        
        // Check for long paragraphs (> 150 words)
        $long_paragraphs = 0;
        $max_words = 0;
        
        foreach ($paragraphs as $paragraph) {
            $word_count = str_word_count($paragraph);
            $max_words = max($max_words, $word_count);
            
            if ($word_count > 150) {
                $long_paragraphs++;
            }
        }
        
        $long_paragraph_ratio = $long_paragraphs / $paragraph_count;
        
        if ($long_paragraph_ratio === 0) {
            $score = 100;
            $status = 'good';
            $message = 'All paragraphs are well-structured';
        } elseif ($long_paragraph_ratio < 0.3) {
            $score = 80;
            $status = 'ok';
            $message = sprintf('%d paragraph(s) are too long. Consider breaking them up.', $long_paragraphs);
        } else {
            $score = max(0, 100 - ($long_paragraph_ratio * 100));
            $status = 'warning';
            $message = sprintf('%d paragraph(s) are too long (max %d words). Break them into shorter paragraphs.', $long_paragraphs, $max_words);
        }
        
        return array(
            'score' => round($score),
            'status' => $status,
            'message' => $message,
            'total_paragraphs' => $paragraph_count,
            'long_paragraphs' => $long_paragraphs,
            'max_words' => $max_words,
        );
    }
    
    /**
     * Analyze sentence length
     *
     * @param string $content Content to analyze
     * @return array Analysis result
     */
    public function analyze_sentence_length($content) {
        $content = AISEO_Helpers::strip_shortcodes_and_tags($content);
        
        // Split into sentences
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        if ($sentence_count === 0) {
            return array(
                'score' => 0,
                'status' => 'poor',
                'message' => 'No sentences found',
                'count' => 0,
            );
        }
        
        // Check for long sentences (> 20 words)
        $long_sentences = 0;
        $max_words = 0;
        
        foreach ($sentences as $sentence) {
            $word_count = str_word_count($sentence);
            $max_words = max($max_words, $word_count);
            
            if ($word_count > 20) {
                $long_sentences++;
            }
        }
        
        $long_sentence_ratio = $long_sentences / $sentence_count;
        
        if ($long_sentence_ratio === 0) {
            $score = 100;
            $status = 'good';
            $message = 'All sentences are concise and easy to read';
        } elseif ($long_sentence_ratio < 0.25) {
            $score = 85;
            $status = 'ok';
            $message = sprintf('%d sentence(s) are a bit long. Consider shortening them.', $long_sentences);
        } else {
            $score = max(0, 100 - ($long_sentence_ratio * 100));
            $status = 'warning';
            $message = sprintf('%d sentence(s) are too long (max %d words). Break them into shorter sentences.', $long_sentences, $max_words);
        }
        
        return array(
            'score' => round($score),
            'status' => $status,
            'message' => $message,
            'total_sentences' => $sentence_count,
            'long_sentences' => $long_sentences,
            'max_words' => $max_words,
        );
    }
    
    /**
     * Analyze content length
     *
     * @param string $content Content to analyze
     * @return array Analysis result
     */
    public function analyze_content_length($content) {
        $content = AISEO_Helpers::strip_shortcodes_and_tags($content);
        $word_count = str_word_count($content);
        
        // Optimal: 300+ words for blog posts
        if ($word_count >= 300) {
            $score = min(100, 70 + ($word_count / 30)); // Bonus for longer content
            $status = 'good';
            $message = sprintf('Content length is good (%d words)', $word_count);
        } elseif ($word_count >= 150) {
            $score = 60 + (($word_count - 150) / 150 * 20);
            $status = 'ok';
            $message = sprintf('Content is a bit short (%d words). Aim for at least 300 words.', $word_count);
        } else {
            $score = max(0, ($word_count / 150) * 60);
            $status = 'poor';
            $message = sprintf('Content is too short (%d words). Add more valuable content.', $word_count);
        }
        
        return array(
            'score' => round($score),
            'status' => $status,
            'message' => $message,
            'word_count' => $word_count,
            'reading_time' => AISEO_Helpers::calculate_reading_time($content),
        );
    }
    
    /**
     * Check if keyword is in title
     *
     * @param string $title Post title
     * @param string $keyword Focus keyword
     * @return array Analysis result
     */
    public function check_keyword_in_title($title, $keyword) {
        if (empty($keyword)) {
            return array(
                'score' => 0,
                'status' => 'warning',
                'message' => 'No focus keyword set',
                'found' => false,
            );
        }
        
        $title_lower = strtolower($title);
        $keyword_lower = strtolower($keyword);
        
        if (strpos($title_lower, $keyword_lower) !== false) {
            return array(
                'score' => 100,
                'status' => 'good',
                'message' => 'Focus keyword found in title',
                'found' => true,
            );
        }
        
        return array(
            'score' => 0,
            'status' => 'poor',
            'message' => 'Focus keyword not found in title. Add it for better SEO.',
            'found' => false,
        );
    }
    
    /**
     * Check if keyword is in headings
     *
     * @param string $content Post content
     * @param string $keyword Focus keyword
     * @return array Analysis result
     */
    public function check_keyword_in_headings($content, $keyword) {
        if (empty($keyword)) {
            return array(
                'score' => 0,
                'status' => 'warning',
                'message' => 'No focus keyword set',
                'found' => false,
            );
        }
        
        // Extract headings (H2, H3)
        preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/i', $content, $matches);
        $headings = $matches[1];
        
        if (empty($headings)) {
            return array(
                'score' => 50,
                'status' => 'warning',
                'message' => 'No headings found. Add H2/H3 headings to structure your content.',
                'found' => false,
                'heading_count' => 0,
            );
        }
        
        $keyword_lower = strtolower($keyword);
        $found = false;
        
        foreach ($headings as $heading) {
            $heading_text = wp_strip_all_tags($heading);
            if (strpos(strtolower($heading_text), $keyword_lower) !== false) {
                $found = true;
                break;
            }
        }
        
        if ($found) {
            return array(
                'score' => 100,
                'status' => 'good',
                'message' => 'Focus keyword found in headings',
                'found' => true,
                'heading_count' => count($headings),
            );
        }
        
        return array(
            'score' => 50,
            'status' => 'warning',
            'message' => 'Focus keyword not found in any headings. Add it to at least one H2 or H3.',
            'found' => false,
            'heading_count' => count($headings),
        );
    }
    
    /**
     * Check if keyword is in introduction
     *
     * @param string $content Post content
     * @param string $keyword Focus keyword
     * @return array Analysis result
     */
    public function check_keyword_in_intro($content, $keyword) {
        if (empty($keyword)) {
            return array(
                'score' => 0,
                'status' => 'warning',
                'message' => 'No focus keyword set',
                'found' => false,
            );
        }
        
        $content = AISEO_Helpers::strip_shortcodes_and_tags($content);
        
        // Get first 150 words as introduction
        $words = str_word_count($content, 1);
        $intro_words = array_slice($words, 0, min(150, count($words)));
        $intro = implode(' ', $intro_words);
        
        $keyword_lower = strtolower($keyword);
        
        if (strpos(strtolower($intro), $keyword_lower) !== false) {
            return array(
                'score' => 100,
                'status' => 'good',
                'message' => 'Focus keyword found in introduction',
                'found' => true,
            );
        }
        
        return array(
            'score' => 50,
            'status' => 'warning',
            'message' => 'Focus keyword not found in the first paragraph. Add it early in your content.',
            'found' => false,
        );
    }
    
    /**
     * Check internal links
     *
     * @param string $content Post content
     * @return array Analysis result
     */
    public function check_internal_links($content) {
        $site_url = get_site_url();
        
        // Count internal links
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        $all_links = $matches[1];
        
        $internal_count = 0;
        foreach ($all_links as $link) {
            if (strpos($link, $site_url) !== false || strpos($link, '/') === 0) {
                $internal_count++;
            }
        }
        
        // Optimal: 2-5 internal links
        if ($internal_count >= 2 && $internal_count <= 5) {
            $score = 100;
            $status = 'good';
            $message = sprintf('Good number of internal links (%d)', $internal_count);
        } elseif ($internal_count === 1) {
            $score = 70;
            $status = 'ok';
            $message = 'Add more internal links to related content';
        } elseif ($internal_count === 0) {
            $score = 30;
            $status = 'poor';
            $message = 'No internal links found. Link to related content on your site.';
        } else {
            $score = 85;
            $status = 'ok';
            $message = sprintf('Many internal links (%d). Make sure they add value.', $internal_count);
        }
        
        return array(
            'score' => $score,
            'status' => $status,
            'message' => $message,
            'count' => $internal_count,
        );
    }
    
    /**
     * Check external links
     *
     * @param string $content Post content
     * @return array Analysis result
     */
    public function check_external_links($content) {
        $site_url = get_site_url();
        
        // Count external links
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        $all_links = $matches[1];
        
        $external_count = 0;
        foreach ($all_links as $link) {
            if (strpos($link, 'http') === 0 && strpos($link, $site_url) === false) {
                $external_count++;
            }
        }
        
        // Optimal: 1-3 external links to authoritative sources
        if ($external_count >= 1 && $external_count <= 3) {
            $score = 100;
            $status = 'good';
            $message = sprintf('Good number of external links (%d)', $external_count);
        } elseif ($external_count === 0) {
            $score = 70;
            $status = 'ok';
            $message = 'Consider adding 1-2 links to authoritative sources';
        } else {
            $score = 80;
            $status = 'ok';
            $message = sprintf('Many external links (%d). Ensure they are to quality sources.', $external_count);
        }
        
        return array(
            'score' => $score,
            'status' => $status,
            'message' => $message,
            'count' => $external_count,
        );
    }
    
    /**
     * Analyze images
     *
     * @param string $content Post content
     * @return array Analysis result
     */
    public function analyze_images($content) {
        // Count images
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        $image_count = count($matches[0]);
        
        // Count images with alt text
        preg_match_all('/<img[^>]+alt=["\'][^"\']*["\'][^>]*>/i', $content, $alt_matches);
        $images_with_alt = count($alt_matches[0]);
        
        if ($image_count === 0) {
            return array(
                'score' => 70,
                'status' => 'ok',
                'message' => 'No images found. Consider adding relevant images.',
                'total' => 0,
                'with_alt' => 0,
            );
        }
        
        $alt_ratio = $images_with_alt / $image_count;
        
        if ($alt_ratio === 1.0) {
            $score = 100;
            $status = 'good';
            $message = sprintf('All %d image(s) have alt text', $image_count);
        } elseif ($alt_ratio >= 0.5) {
            $score = 70 + ($alt_ratio * 30);
            $status = 'ok';
            $message = sprintf('%d of %d images have alt text. Add alt text to all images.', $images_with_alt, $image_count);
        } else {
            $score = max(0, $alt_ratio * 70);
            $status = 'poor';
            $message = sprintf('Only %d of %d images have alt text. Add descriptive alt text to all images.', $images_with_alt, $image_count);
        }
        
        return array(
            'score' => round($score),
            'status' => $status,
            'message' => $message,
            'total' => $image_count,
            'with_alt' => $images_with_alt,
        );
    }
    
    /**
     * Generate overall SEO score
     *
     * @param array $analyses Individual analysis results
     * @return int Overall score (0-100)
     */
    public function generate_seo_score($analyses) {
        // Weight each analysis component
        $weights = array(
            'keyword_density' => 0.15,
            'readability' => 0.15,
            'content_length' => 0.15,
            'keyword_in_title' => 0.10,
            'keyword_in_headings' => 0.10,
            'keyword_in_intro' => 0.10,
            'paragraph_structure' => 0.08,
            'sentence_length' => 0.07,
            'internal_links' => 0.05,
            'external_links' => 0.03,
            'images' => 0.02,
        );
        
        $total_score = 0;
        
        foreach ($weights as $key => $weight) {
            if (isset($analyses[$key]['score'])) {
                $total_score += $analyses[$key]['score'] * $weight;
            }
        }
        
        return round($total_score);
    }
    
    /**
     * Get score status label
     *
     * @param int $score SEO score
     * @return string Status label
     */
    private function get_score_status($score) {
        if ($score >= 80) {
            return 'good';
        } elseif ($score >= 50) {
            return 'ok';
        } else {
            return 'poor';
        }
    }
    
    /**
     * Get reading level from Flesch score
     *
     * @param float $flesch_score Flesch-Kincaid score
     * @return string Reading level
     */
    private function get_reading_level($flesch_score) {
        if ($flesch_score >= 90) {
            return '5th grade';
        } elseif ($flesch_score >= 80) {
            return '6th grade';
        } elseif ($flesch_score >= 70) {
            return '7th grade';
        } elseif ($flesch_score >= 60) {
            return '8th-9th grade';
        } elseif ($flesch_score >= 50) {
            return '10th-12th grade';
        } elseif ($flesch_score >= 30) {
            return 'College';
        } else {
            return 'College graduate';
        }
    }
}
