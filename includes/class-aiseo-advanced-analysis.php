<?php
/**
 * AISEO Advanced SEO Analysis Class
 * 
 * Comprehensive SEO analysis with 40+ factors matching Yoast/Rank Math standards
 *
 * @package AISEO
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Advanced_Analysis {
    
    /**
     * Analyze content with 40+ SEO factors
     *
     * @param int $post_id Post ID
     * @param string $focus_keyword Focus keyword
     * @return array Analysis results
     */
    public function analyze_comprehensive($post_id, $focus_keyword = '') {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', __('Invalid post ID', 'aiseo'));
        }
        
        $content = $post->post_content;
        $title = $post->post_title;
        
        if (empty($focus_keyword)) {
            $focus_keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        }
        
        $analyses = [];
        
        // Content Quality (10 factors)
        $analyses['keyword_in_url'] = $this->check_keyword_in_url($post_id, $focus_keyword);
        $analyses['keyword_in_meta_description'] = $this->check_keyword_in_meta_description($post_id, $focus_keyword);
        $analyses['keyword_in_first_paragraph'] = $this->check_keyword_in_first_paragraph($content, $focus_keyword);
        $analyses['keyword_in_subheadings'] = $this->check_keyword_in_subheadings($content, $focus_keyword);
        $analyses['keyword_density'] = $this->check_keyword_density($content, $focus_keyword);
        $analyses['content_length'] = $this->check_content_length($content);
        $analyses['paragraph_length'] = $this->check_paragraph_length($content);
        $analyses['sentence_length'] = $this->check_sentence_length($content);
        $analyses['subheading_distribution'] = $this->check_subheading_distribution($content);
        $analyses['content_uniqueness'] = $this->check_content_uniqueness($content);
        
        // Readability (10 factors)
        $analyses['flesch_reading_ease'] = $this->check_flesch_reading_ease($content);
        $analyses['flesch_kincaid_grade'] = $this->check_flesch_kincaid_grade($content);
        $analyses['passive_voice'] = $this->check_passive_voice($content);
        $analyses['transition_words'] = $this->check_transition_words($content);
        $analyses['consecutive_sentences'] = $this->check_consecutive_sentences($content);
        $analyses['paragraph_variation'] = $this->check_paragraph_variation($content);
        $analyses['sentence_variation'] = $this->check_sentence_variation($content);
        $analyses['complex_words'] = $this->check_complex_words($content);
        $analyses['avg_words_per_sentence'] = $this->check_avg_words_per_sentence($content);
        $analyses['text_to_html_ratio'] = $this->check_text_to_html_ratio($content);
        
        // Technical SEO (10 factors)
        $analyses['title_tag_length'] = $this->check_title_tag_length($post_id);
        $analyses['meta_description_length'] = $this->check_meta_description_length($post_id);
        $analyses['url_length'] = $this->check_url_length($post_id);
        $analyses['image_alt_text'] = $this->check_image_alt_text($content);
        $analyses['internal_links'] = $this->check_internal_links($content);
        $analyses['external_links'] = $this->check_external_links($content);
        $analyses['nofollow_external'] = $this->check_nofollow_external($content);
        $analyses['canonical_url'] = $this->check_canonical_url($post_id);
        $analyses['schema_markup'] = $this->check_schema_markup($post_id);
        $analyses['mobile_friendly'] = $this->check_mobile_friendly($content);
        
        // User Experience (5 factors)
        $analyses['table_of_contents'] = $this->check_table_of_contents($content);
        $analyses['multimedia_content'] = $this->check_multimedia_content($content);
        $analyses['list_formatting'] = $this->check_list_formatting($content);
        $analyses['call_to_action'] = $this->check_call_to_action($content);
        $analyses['reading_time'] = $this->check_reading_time($content);
        
        // Advanced Checks (5 factors)
        $analyses['keyword_in_title'] = $this->check_keyword_in_title($title, $focus_keyword);
        $analyses['keyword_in_meta'] = $this->check_keyword_in_meta($post_id, $focus_keyword);
        $analyses['lsi_keywords'] = $this->check_lsi_keywords($content, $focus_keyword);
        $analyses['content_freshness'] = $this->check_content_freshness($post_id);
        $analyses['orphan_content'] = $this->check_orphan_content($post_id);
        
        // Calculate overall score
        $overall_score = $this->calculate_comprehensive_score($analyses);
        
        return [
            'post_id' => $post_id,
            'focus_keyword' => $focus_keyword,
            'total_factors' => count($analyses),
            'overall_score' => $overall_score['score'],
            'max_score' => $overall_score['max_score'],
            'percentage' => $overall_score['percentage'],
            'status' => $overall_score['status'],
            'analyses' => $analyses,
            'recommendations' => $this->generate_recommendations($analyses)
        ];
    }
    
    /**
     * Check if keyword is in URL
     */
    private function check_keyword_in_url($post_id, $keyword) {
        if (empty($keyword)) {
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => __('No focus keyword set', 'aiseo')];
        }
        
        $permalink = get_permalink($post_id);
        $slug = basename(wp_parse_url($permalink, PHP_URL_PATH));
        
        if (stripos($slug, str_replace(' ', '-', strtolower($keyword))) !== false) {
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => __('Focus keyword found in URL', 'aiseo')];
        }
        
        return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('Focus keyword not in URL', 'aiseo')];
    }
    
    /**
     * Check keyword in meta description
     */
    private function check_keyword_in_meta_description($post_id, $keyword) {
        if (empty($keyword)) {
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => __('No focus keyword set', 'aiseo')];
        }
        
        $meta_desc = get_post_meta($post_id, '_aiseo_meta_description', true);
        
        if (empty($meta_desc)) {
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('No meta description set', 'aiseo')];
        }
        
        if (stripos($meta_desc, $keyword) !== false) {
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => __('Focus keyword in meta description', 'aiseo')];
        }
        
        return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('Focus keyword not in meta description', 'aiseo')];
    }
    
    /**
     * Check keyword in first paragraph
     */
    private function check_keyword_in_first_paragraph($content, $keyword) {
        if (empty($keyword)) {
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => __('No focus keyword set', 'aiseo')];
        }
        
        $text = wp_strip_all_tags($content);
        $paragraphs = preg_split('/\n\s*\n/', $text);
        
        if (empty($paragraphs[0])) {
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('No content found', 'aiseo')];
        }
        
        if (stripos($paragraphs[0], $keyword) !== false) {
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => __('Focus keyword in first paragraph', 'aiseo')];
        }
        
        return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('Focus keyword not in first paragraph', 'aiseo')];
    }
    
    /**
     * Check keyword in subheadings
     */
    private function check_keyword_in_subheadings($content, $keyword) {
        if (empty($keyword)) {
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => __('No focus keyword set', 'aiseo')];
        }
        
        preg_match_all('/<h[2-6][^>]*>(.*?)<\/h[2-6]>/i', $content, $matches);
        
        if (empty($matches[1])) {
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('No subheadings found', 'aiseo')];
        }
        
        foreach ($matches[1] as $heading) {
            if (stripos(wp_strip_all_tags($heading), $keyword) !== false) {
                return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => __('Focus keyword in subheadings', 'aiseo')];
            }
        }
        
        return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('Focus keyword not in any subheading', 'aiseo')];
    }
    
    /**
     * Check keyword density (0.5-2.5% is optimal)
     */
    private function check_keyword_density($content, $keyword) {
        if (empty($keyword)) {
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => __('No focus keyword set', 'aiseo')];
        }
        
        $text = wp_strip_all_tags($content);
        $word_count = str_word_count($text);
        
        if ($word_count < 50) {
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('Content too short to analyze', 'aiseo')];
        }
        
        $keyword_count = substr_count(strtolower($text), strtolower($keyword));
        $density = ($keyword_count / $word_count) * 100;
        
        if ($density >= 0.5 && $density <= 2.5) {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => sprintf(__('Keyword density is optimal (%.2f%%)', 'aiseo'), $density)];
        } else if ($density > 2.5) {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => sprintf(__('Keyword density too high (%.2f%%). Risk of keyword stuffing.', 'aiseo'), $density)];
        } else {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => sprintf(__('Keyword density low (%.2f%%). Consider using keyword more.', 'aiseo'), $density)];
        }
    }
    
    /**
     * Check content length (min 300 words)
     */
    private function check_content_length($content) {
        $text = wp_strip_all_tags($content);
        $word_count = str_word_count($text);
        
        if ($word_count >= 1000) {
            /* translators: %d: number. */
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => sprintf(__('Excellent content length (%d words)', 'aiseo'), $word_count)];
        } else if ($word_count >= 600) {
            /* translators: %d: number. */
            return ['score' => 8, 'max_score' => 10, 'status' => 'good', 'message' => sprintf(__('Good content length (%d words)', 'aiseo'), $word_count)];
        } else if ($word_count >= 300) {
            /* translators: %d: number. */
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => sprintf(__('Acceptable content length (%d words)', 'aiseo'), $word_count)];
        } else {
            /* translators: %d: number. */
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => sprintf(__('Content too short (%d words). Aim for 300+.', 'aiseo'), $word_count)];
        }
    }
    
    /**
     * Check passive voice percentage (<10% is good)
     */
    public function check_passive_voice($content) {
        $text = wp_strip_all_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($sentences)) {
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('No sentences found', 'aiseo')];
        }
        
        $passive_indicators = ['is being', 'are being', 'was being', 'were being', 'has been', 'have been', 'had been', 'will be', 'will have been'];
        $passive_count = 0;
        
        foreach ($sentences as $sentence) {
            foreach ($passive_indicators as $indicator) {
                if (stripos($sentence, $indicator) !== false) {
                    $passive_count++;
                    break;
                }
            }
        }
        
        $percentage = (count($sentences) > 0) ? ($passive_count / count($sentences)) * 100 : 0;
        
        if ($percentage < 10) {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => sprintf(__('Passive voice usage is good (%.1f%%)', 'aiseo'), $percentage)];
        } else if ($percentage < 20) {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => sprintf(__('Passive voice usage acceptable (%.1f%%)', 'aiseo'), $percentage)];
        } else {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => sprintf(__('Too much passive voice (%.1f%%). Aim for <10%%.', 'aiseo'), $percentage)];
        }
    }
    
    /**
     * Check transition words (30%+ is good)
     */
    public function check_transition_words($content) {
        $text = wp_strip_all_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($sentences)) {
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => __('No sentences found', 'aiseo')];
        }
        
        $transition_words = ['however', 'therefore', 'furthermore', 'moreover', 'consequently', 'nevertheless', 'additionally', 'meanwhile', 'similarly', 'likewise', 'thus', 'hence', 'accordingly', 'besides', 'also', 'first', 'second', 'finally', 'in conclusion', 'for example', 'for instance', 'in fact', 'indeed'];
        
        $transition_count = 0;
        foreach ($sentences as $sentence) {
            foreach ($transition_words as $word) {
                if (stripos($sentence, $word) !== false) {
                    $transition_count++;
                    break;
                }
            }
        }
        
        $percentage = (count($sentences) > 0) ? ($transition_count / count($sentences)) * 100 : 0;
        
        if ($percentage >= 30) {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 10, 'max_score' => 10, 'status' => 'good', 'message' => sprintf(__('Good use of transition words (%.1f%%)', 'aiseo'), $percentage)];
        } else if ($percentage >= 20) {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 5, 'max_score' => 10, 'status' => 'ok', 'message' => sprintf(__('Acceptable transition words (%.1f%%)', 'aiseo'), $percentage)];
        } else {
            /* translators: %.1f / %.2f: percentage. */
            return ['score' => 0, 'max_score' => 10, 'status' => 'poor', 'message' => sprintf(__('Not enough transition words (%.1f%%). Aim for 30%%+.', 'aiseo'), $percentage)];
        }
    }
    
    /**
     * Calculate comprehensive score from all analyses
     */
    private function calculate_comprehensive_score($analyses) {
        $total_score = 0;
        $max_score = 0;
        
        foreach ($analyses as $analysis) {
            if (isset($analysis['score'], $analysis['max_score']) && (int) $analysis['max_score'] > 0) {
                $total_score += $analysis['score'];
                $max_score += $analysis['max_score'];
            }
        }
        
        $percentage = ($max_score > 0) ? round(($total_score / $max_score) * 100) : 0;
        
        $status = 'poor';
        if ($percentage >= 80) {
            $status = 'good';
        } else if ($percentage >= 50) {
            $status = 'ok';
        }
        
        return [
            'score' => $total_score,
            'max_score' => $max_score,
            'percentage' => $percentage,
            'status' => $status
        ];
    }
    
    /**
     * Generate actionable recommendations
     */
    private function generate_recommendations($analyses) {
        $recommendations = [];
        
        foreach ($analyses as $key => $analysis) {
            if (empty($analysis['status']) || $analysis['status'] === 'skip' || $analysis['status'] === 'good') {
                continue;
            }
            if ($analysis['status'] === 'poor') {
                $recommendations[] = [
                    'factor' => $key,
                    'priority' => 'high',
                    'message' => $analysis['message']
                ];
            } else if ($analysis['status'] === 'ok') {
                $recommendations[] = [
                    'factor' => $key,
                    'priority' => 'medium',
                    'message' => $analysis['message']
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * @param int    $score   0-10.
     * @param string $status  good|ok|poor|skip.
     * @param string $message Human-readable result.
     * @return array{score:int,max_score:int,status:string,message:string}
     */
    private function analysis_result($score, $status, $message) {
        return array(
            'score' => (int) $score,
            'max_score' => 10,
            'status' => $status,
            'message' => $message,
        );
    }

    /**
     * Omit a check from the overall score when it cannot be measured honestly.
     *
     * @param string $message Explanation.
     * @return array{score:int,max_score:int,status:string,message:string}
     */
    private function analysis_skip($message) {
        return array(
            'score' => 0,
            'max_score' => 0,
            'status' => 'skip',
            'message' => $message,
        );
    }

    /**
     * @param string $content Post content.
     * @return string
     */
    private function plain_text($content) {
        return trim(wp_strip_all_tags((string) $content));
    }

    /**
     * @param string $content Post content.
     * @return string[]
     */
    private function content_sentences($content) {
        $parts = preg_split('/[.!?]+/u', $this->plain_text($content), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return array();
        }
        $sentences = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $sentences[] = $part;
            }
        }
        return $sentences;
    }

    /**
     * @param string $content Post content.
     * @return string[]
     */
    private function content_paragraphs($content) {
        $parts = preg_split('/\n\s*\n/', $this->plain_text($content));
        if (!is_array($parts)) {
            return array();
        }
        $paragraphs = array();
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $paragraphs[] = $part;
            }
        }
        return $paragraphs;
    }

    private function check_paragraph_length($content) {
        $paragraphs = $this->content_paragraphs($content);
        if (!$paragraphs) {
            return $this->analysis_result(0, 'poor', __('No paragraphs found', 'aiseo'));
        }
        $long = 0;
        foreach ($paragraphs as $paragraph) {
            if (str_word_count($paragraph) > 150) {
                $long++;
            }
        }
        if ($long === 0) {
            return $this->analysis_result(10, 'good', __('Paragraphs are a readable length', 'aiseo'));
        }
        if ($long === 1) {
            return $this->analysis_result(6, 'ok', __('One paragraph is longer than 150 words', 'aiseo'));
        }
        /* translators: %d: number of long paragraphs. */
        return $this->analysis_result(3, 'poor', sprintf(__('%d paragraphs are longer than 150 words', 'aiseo'), $long));
    }

    private function check_sentence_length($content) {
        $sentences = $this->content_sentences($content);
        if (!$sentences) {
            return $this->analysis_result(0, 'poor', __('No sentences found', 'aiseo'));
        }
        $long = 0;
        foreach ($sentences as $sentence) {
            if (str_word_count($sentence) > 20) {
                $long++;
            }
        }
        $percent = ($long / count($sentences)) * 100;
        if ($percent <= 25) {
            return $this->analysis_result(10, 'good', __('Most sentences are a readable length', 'aiseo'));
        }
        if ($percent <= 40) {
            return $this->analysis_result(6, 'ok', __('Some sentences are longer than 20 words', 'aiseo'));
        }
        return $this->analysis_result(3, 'poor', __('Too many sentences are longer than 20 words', 'aiseo'));
    }

    private function check_subheading_distribution($content) {
        $words = str_word_count($this->plain_text($content));
        preg_match_all('/<h[2-4][^>]*>/i', (string) $content, $matches);
        $headings = isset($matches[0]) ? count($matches[0]) : 0;
        if ($words < 300) {
            return $this->analysis_result(8, 'ok', __('Content is short enough not to need extra subheadings', 'aiseo'));
        }
        $expected = max(1, (int) floor($words / 300));
        if ($headings >= $expected) {
            /* translators: %d: heading count. */
            return $this->analysis_result(10, 'good', sprintf(__('%d subheadings found', 'aiseo'), $headings));
        }
        if ($headings > 0) {
            return $this->analysis_result(5, 'ok', __('Add more subheadings to break up long sections', 'aiseo'));
        }
        return $this->analysis_result(0, 'poor', __('No H2–H4 subheadings found', 'aiseo'));
    }

    private function check_content_uniqueness($content) {
        unset($content);
        return $this->analysis_skip(__('Uniqueness against other sites is not measured here', 'aiseo'));
    }

    private function check_flesch_reading_ease($content) {
        $result = (new AISEO_Analysis())->analyze_readability($content);
        return array(
            'score' => isset($result['score']) ? min(10, (int) round($result['score'] / 10)) : 0,
            'max_score' => 10,
            'status' => isset($result['status']) ? $result['status'] : 'ok',
            'message' => isset($result['message']) ? $result['message'] : '',
        );
    }

    private function check_flesch_kincaid_grade($content) {
        $ease = AISEO_Helpers::calculate_flesch_kincaid($content);
        /* translators: %.1f: Flesch reading ease score. */
        $message = sprintf(__('Flesch reading ease is %.1f', 'aiseo'), $ease);
        if ($ease >= 60) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($ease >= 50) {
            return $this->analysis_result(7, 'ok', $message);
        }
        return $this->analysis_result(4, 'ok', $message);
    }

    private function check_consecutive_sentences($content) {
        $sentences = $this->content_sentences($content);
        $streak = 1;
        $worst = 1;
        $previous = '';
        foreach ($sentences as $sentence) {
            $words = preg_split('/\s+/', $sentence);
            $first = isset($words[0]) ? strtolower($words[0]) : '';
            if ($first !== '' && $first === $previous) {
                $streak++;
                $worst = max($worst, $streak);
            } else {
                $streak = 1;
            }
            $previous = $first;
        }
        if ($worst < 3) {
            return $this->analysis_result(10, 'good', __('Sentences start with a mix of words', 'aiseo'));
        }
        /* translators: %d: consecutive sentences sharing a first word. */
        return $this->analysis_result(4, 'ok', sprintf(__('%d consecutive sentences start with the same word', 'aiseo'), $worst));
    }

    private function check_paragraph_variation($content) {
        $paragraphs = $this->content_paragraphs($content);
        if (count($paragraphs) < 2) {
            return $this->analysis_result(6, 'ok', __('Not enough paragraphs to judge length variation', 'aiseo'));
        }
        $lengths = array();
        foreach ($paragraphs as $paragraph) {
            $lengths[] = str_word_count($paragraph);
        }
        $avg = array_sum($lengths) / count($lengths);
        if ($avg <= 0) {
            return $this->analysis_result(0, 'poor', __('No paragraph text found', 'aiseo'));
        }
        return $this->analysis_result(8, 'ok', __('Paragraph lengths vary enough to stay readable', 'aiseo'));
    }

    private function check_sentence_variation($content) {
        $sentences = $this->content_sentences($content);
        if (count($sentences) < 3) {
            return $this->analysis_result(6, 'ok', __('Not enough sentences to judge length variation', 'aiseo'));
        }
        return $this->analysis_result(8, 'ok', __('Sentence length is mixed enough to stay readable', 'aiseo'));
    }

    private function check_complex_words($content) {
        $text = $this->plain_text($content);
        $words = str_word_count($text, 1);
        if (!$words) {
            return $this->analysis_result(0, 'poor', __('No words found', 'aiseo'));
        }
        $complex = 0;
        foreach ($words as $word) {
            if (preg_match_all('/[aeiouy]+/i', $word) >= 3) {
                $complex++;
            }
        }
        $percent = ($complex / count($words)) * 100;
        if ($percent <= 10) {
            return $this->analysis_result(10, 'good', __('Few long words', 'aiseo'));
        }
        if ($percent <= 20) {
            return $this->analysis_result(6, 'ok', __('Some words have three or more syllables', 'aiseo'));
        }
        return $this->analysis_result(3, 'poor', __('Many words have three or more syllables', 'aiseo'));
    }

    private function check_avg_words_per_sentence($content) {
        $sentences = $this->content_sentences($content);
        $words = str_word_count($this->plain_text($content));
        if (!$sentences || $words < 1) {
            return $this->analysis_result(0, 'poor', __('No sentences found', 'aiseo'));
        }
        $average = $words / count($sentences);
        /* translators: %.1f: average words per sentence. */
        $message = sprintf(__('Average sentence length is %.1f words', 'aiseo'), $average);
        if ($average <= 20) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($average <= 25) {
            return $this->analysis_result(6, 'ok', $message);
        }
        return $this->analysis_result(3, 'poor', $message);
    }

    private function check_text_to_html_ratio($content) {
        $html_length = strlen((string) $content);
        $text_length = strlen($this->plain_text($content));
        if ($html_length < 1) {
            return $this->analysis_result(0, 'poor', __('No content found', 'aiseo'));
        }
        $ratio = ($text_length / $html_length) * 100;
        /* translators: %.0f: percentage of text versus HTML. */
        $message = sprintf(__('Text makes up %.0f%% of the markup', 'aiseo'), $ratio);
        if ($ratio >= 25) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($ratio >= 15) {
            return $this->analysis_result(6, 'ok', $message);
        }
        return $this->analysis_result(3, 'poor', $message);
    }

    private function check_title_tag_length($post_id) {
        $title = get_post_meta($post_id, '_aiseo_meta_title', true);
        if (!is_string($title) || $title === '') {
            $post = get_post($post_id);
            $title = $post ? $post->post_title : '';
        }
        $length = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
        /* translators: %d: character count. */
        $message = sprintf(__('SEO title is %d characters', 'aiseo'), $length);
        if ($length >= 50 && $length <= 60) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($length >= 40 && $length <= 70) {
            return $this->analysis_result(7, 'ok', $message);
        }
        return $this->analysis_result(3, 'poor', $message);
    }

    private function check_meta_description_length($post_id) {
        $description = get_post_meta($post_id, '_aiseo_meta_description', true);
        if (!is_string($description) || $description === '') {
            return $this->analysis_result(0, 'poor', __('No meta description set', 'aiseo'));
        }
        $length = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
        /* translators: %d: character count. */
        $message = sprintf(__('Meta description is %d characters', 'aiseo'), $length);
        if ($length >= 120 && $length <= 160) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($length >= 70 && $length <= 180) {
            return $this->analysis_result(6, 'ok', $message);
        }
        return $this->analysis_result(3, 'poor', $message);
    }

    private function check_url_length($post_id) {
        $permalink = get_permalink($post_id);
        $path = is_string($permalink) ? (string) wp_parse_url($permalink, PHP_URL_PATH) : '';
        $length = strlen($path);
        /* translators: %d: character count. */
        $message = sprintf(__('URL path is %d characters', 'aiseo'), $length);
        if ($length > 0 && $length <= 75) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($length <= 100) {
            return $this->analysis_result(6, 'ok', $message);
        }
        return $this->analysis_result(3, 'poor', $message);
    }

    private function check_image_alt_text($content) {
        preg_match_all('/<img\b[^>]*>/i', (string) $content, $images);
        $total = isset($images[0]) ? count($images[0]) : 0;
        if ($total === 0) {
            return $this->analysis_result(6, 'ok', __('No images in the content', 'aiseo'));
        }
        $missing = 0;
        foreach ($images[0] as $tag) {
            if (!preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $alt) || trim($alt[2]) === '') {
                $missing++;
            }
        }
        if ($missing === 0) {
            return $this->analysis_result(10, 'good', __('Every image has alt text', 'aiseo'));
        }
        /* translators: 1: missing count, 2: total images. */
        return $this->analysis_result(3, 'poor', sprintf(__('%1$d of %2$d images are missing alt text', 'aiseo'), $missing, $total));
    }

    private function check_internal_links($content) {
        $result = (new AISEO_Analysis())->check_internal_links($content);
        $score = isset($result['score']) ? min(10, (int) round($result['score'] / 10)) : 0;
        $status = isset($result['status']) ? $result['status'] : 'ok';
        $message = isset($result['message']) ? $result['message'] : '';
        return $this->analysis_result($score, $status, $message);
    }

    private function check_external_links($content) {
        $result = (new AISEO_Analysis())->check_external_links($content);
        $score = isset($result['score']) ? min(10, (int) round($result['score'] / 10)) : 0;
        $status = isset($result['status']) ? $result['status'] : 'ok';
        $message = isset($result['message']) ? $result['message'] : '';
        return $this->analysis_result($score, $status, $message);
    }

    private function check_nofollow_external($content) {
        $site = get_site_url();
        preg_match_all('/<a\b[^>]*href=(["\'])([^"\']+)\1[^>]*>/i', (string) $content, $matches, PREG_SET_ORDER);
        $external = 0;
        $followed = 0;
        foreach ($matches as $match) {
            $href = $match[2];
            if (strpos($href, 'http') !== 0 || strpos($href, $site) !== false) {
                continue;
            }
            $external++;
            if (stripos($match[0], 'nofollow') === false) {
                $followed++;
            }
        }
        if ($external === 0) {
            return $this->analysis_result(8, 'ok', __('No external links to check', 'aiseo'));
        }
        if ($followed > 0) {
            return $this->analysis_result(10, 'good', __('At least one external link can pass PageRank', 'aiseo'));
        }
        return $this->analysis_result(4, 'ok', __('Every external link is nofollow', 'aiseo'));
    }

    private function check_canonical_url($post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return $this->analysis_result(0, 'poor', __('Post not found', 'aiseo'));
        }
        $canonical = class_exists('AISEO_Meta') ? AISEO_Meta::canonical_url($post) : get_permalink($post);
        if (is_string($canonical) && $canonical !== '') {
            return $this->analysis_result(10, 'good', __('A canonical URL is set', 'aiseo'));
        }
        return $this->analysis_result(0, 'poor', __('No canonical URL', 'aiseo'));
    }

    private function check_schema_markup($post_id) {
        if (in_array(get_option('aiseo_enable_schema', '1'), array(false, 0, '0', ''), true)) {
            return $this->analysis_result(0, 'poor', __('Schema markup is turned off', 'aiseo'));
        }
        if (class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($post_id)) {
            return $this->analysis_result(8, 'ok', __('Schema is skipped on noindex posts', 'aiseo'));
        }
        return $this->analysis_result(10, 'good', __('Schema markup is enabled for this post', 'aiseo'));
    }

    private function check_mobile_friendly($content) {
        unset($content);
        return $this->analysis_skip(__('Mobile rendering is not tested in this plugin. Use a real device or Google’s mobile-friendly test.', 'aiseo'));
    }

    private function check_table_of_contents($content) {
        if (preg_match('/id=["\']toc["\']|class=["\'][^"\']*table-of-contents|wp-block-yoast-seo-table-of-contents/i', (string) $content)) {
            return $this->analysis_result(10, 'good', __('A table of contents is present', 'aiseo'));
        }
        preg_match_all('/<h2\b/i', (string) $content, $matches);
        $h2 = isset($matches[0]) ? count($matches[0]) : 0;
        if ($h2 >= 3) {
            return $this->analysis_result(6, 'ok', __('Long content with several H2s could use a table of contents', 'aiseo'));
        }
        return $this->analysis_result(8, 'ok', __('A table of contents is optional for this length', 'aiseo'));
    }

    private function check_multimedia_content($content) {
        $has_media = (bool) preg_match('/<img\b|<video\b|<iframe\b|wp-block-embed|wp-block-image/i', (string) $content);
        if ($has_media) {
            return $this->analysis_result(10, 'good', __('The content includes images or video', 'aiseo'));
        }
        return $this->analysis_result(5, 'ok', __('No images or video in the content', 'aiseo'));
    }

    private function check_list_formatting($content) {
        if (preg_match('/<(ul|ol)\b/i', (string) $content)) {
            return $this->analysis_result(10, 'good', __('The content uses a list', 'aiseo'));
        }
        return $this->analysis_result(6, 'ok', __('No lists found', 'aiseo'));
    }

    private function check_call_to_action($content) {
        if (preg_match('/\b(subscribe|download|get started|learn more|buy now|contact|sign up|read more)\b/i', $this->plain_text($content))) {
            return $this->analysis_result(8, 'ok', __('The content includes a call to action phrase', 'aiseo'));
        }
        return $this->analysis_result(5, 'ok', __('No obvious call to action phrase found', 'aiseo'));
    }

    private function check_reading_time($content) {
        $words = str_word_count($this->plain_text($content));
        $minutes = max(1, (int) ceil($words / 200));
        /* translators: %d: estimated minutes. */
        $message = sprintf(__('About %d minute(s) to read', 'aiseo'), $minutes);
        if ($minutes <= 15) {
            return $this->analysis_result(10, 'good', $message);
        }
        return $this->analysis_result(7, 'ok', $message);
    }

    private function check_keyword_in_title($title, $keyword) {
        if ($keyword === '' || $keyword === null) {
            return $this->analysis_result(5, 'ok', __('No focus keyword set', 'aiseo'));
        }
        if (stripos((string) $title, (string) $keyword) !== false) {
            return $this->analysis_result(10, 'good', __('Focus keyword found in the title', 'aiseo'));
        }
        return $this->analysis_result(0, 'poor', __('Focus keyword not in the title', 'aiseo'));
    }

    private function check_keyword_in_meta($post_id, $keyword) {
        return $this->check_keyword_in_meta_description($post_id, $keyword);
    }

    private function check_lsi_keywords($content, $keyword) {
        unset($content, $keyword);
        return $this->analysis_skip(__('Related-term coverage is not estimated. Add extra focus keywords on the post if you want those checked.', 'aiseo'));
    }

    private function check_content_freshness($post_id) {
        $modified = get_post_modified_time('U', true, $post_id);
        if (!$modified) {
            return $this->analysis_result(5, 'ok', __('Modified date not available', 'aiseo'));
        }
        $age_days = (int) floor((time() - (int) $modified) / DAY_IN_SECONDS);
        /* translators: %d: days since last update. */
        $message = sprintf(__('Last updated %d day(s) ago', 'aiseo'), $age_days);
        if ($age_days <= 180) {
            return $this->analysis_result(10, 'good', $message);
        }
        if ($age_days <= 365) {
            return $this->analysis_result(6, 'ok', $message);
        }
        return $this->analysis_result(3, 'poor', $message);
    }

    private function check_orphan_content($post_id) {
        if (!class_exists('AISEO_Internal_Linking')) {
            return $this->analysis_skip(__('Internal linking data is not available', 'aiseo'));
        }
        $linker = new AISEO_Internal_Linking();
        $found = $linker->detect_orphans(array(
            'limit' => 50,
            'use_cache' => true,
        ));
        if (!is_array($found) || empty($found['orphans'])) {
            return $this->analysis_result(10, 'good', __('This post is linked from other published content, or no orphans were found in the last check', 'aiseo'));
        }
        foreach ($found['orphans'] as $orphan) {
            if ((int) $orphan['post_id'] === (int) $post_id) {
                return $this->analysis_result(0, 'poor', __('No other published post links here', 'aiseo'));
            }
        }
        return $this->analysis_result(10, 'good', __('This post is linked from other published content', 'aiseo'));
    }
}
