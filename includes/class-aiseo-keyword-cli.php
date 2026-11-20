<?php
/**
 * AISEO Keyword Research WP-CLI Commands
 *
 * @package AISEO
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Keyword Research WP-CLI Commands
 */
class AISEO_Keyword_CLI {
    
    /**
     * Get keyword suggestions
     *
     * ## OPTIONS
     *
     * <seed_keyword>
     * : Seed keyword for suggestions
     *
     * [--limit=<limit>]
     * : Number of suggestions
     * ---
     * default: 20
     * ---
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword suggestions "wordpress seo"
     *     wp aiseo keyword suggestions "content marketing" --limit=30
     *     wp aiseo keyword suggestions "seo tips" --format=json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function suggestions($args, $assoc_args) {
        $seed_keyword = $args[0];
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 20;
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        if (empty($seed_keyword)) {
            WP_CLI::error('Seed keyword is required');
        }
        
        WP_CLI::log(sprintf('Generating keyword suggestions for: "%s"', $seed_keyword));
        
        $keyword_research = new AISEO_Keyword_Research();
        $result = $keyword_research->get_keyword_suggestions($seed_keyword, $limit);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success(sprintf('Found %d keyword suggestions!', count($result)));
        WP_CLI::log('');
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $display_data = [];
            foreach ($result as $kw) {
                $display_data[] = [
                    'keyword' => $kw['keyword'],
                    'type' => $kw['type'],
                    'intent' => $kw['intent'],
                    'difficulty' => $kw['difficulty'],
                    'difficulty_source' => $kw['difficulty_source'] ?? 'ai_estimate',
                    'search_volume' => 'n/a',
                    'competition' => $kw['competition']
                ];
            }
            
            WP_CLI\Utils\format_items($format, $display_data, 
                ['keyword', 'type', 'intent', 'difficulty', 'difficulty_source', 'search_volume', 'competition']);
        }
    }
    
    /**
     * Get related keywords
     *
     * ## OPTIONS
     *
     * <keyword>
     * : Main keyword
     *
     * [--limit=<limit>]
     * : Number of related keywords
     * ---
     * default: 10
     * ---
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword related "wordpress seo"
     *     wp aiseo keyword related "content marketing" --limit=20
     *     wp aiseo keyword related "seo" --format=json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function related($args, $assoc_args) {
        $keyword = $args[0];
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 10;
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        if (empty($keyword)) {
            WP_CLI::error('Keyword is required');
        }
        
        WP_CLI::log(sprintf('Finding related keywords for: "%s"', $keyword));
        
        $keyword_research = new AISEO_Keyword_Research();
        $result = $keyword_research->get_related_keywords($keyword, $limit);
        
        if (empty($result)) {
            WP_CLI::warning('No related keywords found');
            return;
        }
        
        if ($format === 'count') {
            WP_CLI::log(count($result));
            return;
        }
        
        WP_CLI::success(sprintf('Found %d related keywords!', count($result)));
        WP_CLI::log('');
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $display_data = [];
            foreach ($result as $index => $kw) {
                $display_data[] = [
                    'index' => $index + 1,
                    'keyword' => $kw
                ];
            }
            
            WP_CLI\Utils\format_items($format, $display_data, ['index', 'keyword']);
        }
    }
    
    /**
     * Analyze keyword difficulty
     *
     * ## OPTIONS
     *
     * <keyword>
     * : Keyword to analyze
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword difficulty "wordpress seo"
     *     wp aiseo keyword difficulty "content marketing" --format=json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function difficulty($args, $assoc_args) {
        $keyword = $args[0];
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        if (empty($keyword)) {
            WP_CLI::error('Keyword is required');
        }
        
        WP_CLI::log(sprintf('Analyzing difficulty for: "%s"', $keyword));
        
        $keyword_research = new AISEO_Keyword_Research();
        $result = $keyword_research->analyze_keyword_difficulty($keyword);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success('Analysis complete!');
        WP_CLI::log('');
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            WP_CLI::log('Difficulty Analysis (AI estimate, not a SERP score):');
            WP_CLI::log(sprintf('  Level: %s', $result['difficulty_level'] ?? 'unknown'));
            WP_CLI::log(sprintf('  Source: %s', $result['source'] ?? 'ai_estimate'));
            WP_CLI::log(sprintf('  Type: %s', $result['keyword_type'] ?? ''));
            WP_CLI::log(sprintf('  Length: %d words', $result['keyword_length'] ?? 0));
            
            if (!empty($result['factors'])) {
                WP_CLI::log('');
                WP_CLI::log('Key Factors:');
                foreach ($result['factors'] as $factor) {
                    WP_CLI::log('  • ' . $factor);
                }
            }
            
            if (!empty($result['recommendations'])) {
                WP_CLI::log('');
                WP_CLI::log('Recommendations:');
                foreach ($result['recommendations'] as $rec) {
                    WP_CLI::log('  • ' . $rec);
                }
            }
        }
    }
    
    /**
     * Get question-based keywords
     *
     * ## OPTIONS
     *
     * <topic>
     * : Topic or seed keyword
     *
     * [--limit=<limit>]
     * : Number of questions
     * ---
     * default: 15
     * ---
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword questions "wordpress seo"
     *     wp aiseo keyword questions "content marketing" --limit=20
     *     wp aiseo keyword questions "seo" --format=json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function questions($args, $assoc_args) {
        $topic = $args[0];
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 15;
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        if (empty($topic)) {
            WP_CLI::error('Topic is required');
        }
        
        WP_CLI::log(sprintf('Generating question keywords for: "%s"', $topic));
        
        $keyword_research = new AISEO_Keyword_Research();
        $result = $keyword_research->get_question_keywords($topic, $limit);
        
        if (empty($result)) {
            WP_CLI::warning('No question keywords found');
            return;
        }
        
        WP_CLI::success(sprintf('Found %d question keywords!', count($result)));
        WP_CLI::log('');
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $display_data = [];
            foreach ($result as $q) {
                $display_data[] = [
                    'question' => $q['question'],
                    'type' => $q['question_type'],
                    'intent' => $q['search_intent']
                ];
            }
            
            WP_CLI\Utils\format_items($format, $display_data, ['question', 'type', 'intent']);
        }
    }
    
    /**
     * Analyze keyword trends
     *
     * ## OPTIONS
     *
     * <keyword>
     * : Keyword to analyze
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword trends "wordpress seo"
     *     wp aiseo keyword trends "content marketing" --format=json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function trends($args, $assoc_args) {
        $keyword = $args[0];
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        if (empty($keyword)) {
            WP_CLI::error('Keyword is required');
        }
        
        WP_CLI::log(sprintf('Analyzing trends for: "%s"', $keyword));
        
        $keyword_research = new AISEO_Keyword_Research();
        $result = $keyword_research->analyze_keyword_trends($keyword);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success('Trend analysis complete!');
        WP_CLI::log('');
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            WP_CLI::log('Trend Analysis:');
            WP_CLI::log(sprintf('  Trend: %s', $result['trend']));
            WP_CLI::log(sprintf('  Seasonality: %s', $result['seasonality']));
            
            if (!empty($result['peak_months'])) {
                WP_CLI::log(sprintf('  Peak Months: %s', implode(', ', $result['peak_months'])));
            }
            
            if (!empty($result['insights'])) {
                WP_CLI::log('');
                WP_CLI::log('Insights:');
                foreach ($result['insights'] as $insight) {
                    WP_CLI::log('  • ' . $insight);
                }
            }
        }
    }
    
    /**
     * Get keyword research summary
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword summary
     *     wp aiseo keyword summary --format=json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function summary($args, $assoc_args) {
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        $keyword_research = new AISEO_Keyword_Research();
        $summary = $keyword_research->get_summary();
        
        if ($format === 'json') {
            WP_CLI::log(json_encode($summary, JSON_PRETTY_PRINT));
        } else {
            WP_CLI::log('Keyword Research Summary:');
            WP_CLI::log('');
            WP_CLI::log(sprintf('Cached Suggestions: %d', $summary['cached_suggestions']));
            WP_CLI::log(sprintf('Cached Related: %d', $summary['cached_related']));
            WP_CLI::log(sprintf('Cached Difficulty: %d', $summary['cached_difficulty']));
            WP_CLI::log(sprintf('Total Cached: %d', $summary['total_cached']));
            WP_CLI::log(sprintf('Cache Duration: %s', $summary['cache_duration']));
        }
    }
    
    /**
     * Clear keyword research cache
     *
     * ## EXAMPLES
     *
     *     wp aiseo keyword clear-cache
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function clear_cache($args, $assoc_args) {
        WP_CLI::log('Clearing keyword research cache...');
        
        $keyword_research = new AISEO_Keyword_Research();
        $deleted = $keyword_research->clear_cache();
        
        WP_CLI::success(sprintf('Cleared %d cache entries', $deleted));
    }
}

// Register WP-CLI commands
WP_CLI::add_command('aiseo keyword suggestions', ['AISEO_Keyword_CLI', 'suggestions']);
WP_CLI::add_command('aiseo keyword related', ['AISEO_Keyword_CLI', 'related']);
WP_CLI::add_command('aiseo keyword difficulty', ['AISEO_Keyword_CLI', 'difficulty']);
WP_CLI::add_command('aiseo keyword questions', ['AISEO_Keyword_CLI', 'questions']);
WP_CLI::add_command('aiseo keyword trends', ['AISEO_Keyword_CLI', 'trends']);
WP_CLI::add_command('aiseo keyword summary', ['AISEO_Keyword_CLI', 'summary']);
WP_CLI::add_command('aiseo keyword clear-cache', ['AISEO_Keyword_CLI', 'clear_cache']);
