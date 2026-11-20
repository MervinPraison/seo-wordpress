<?php
/**
 * AISEO Import/Export Class
 * 
 * Import from Yoast, Rank Math, AIOSEO, SEOPress and export AISEO metadata
 *
 * @package AISEO
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Import_Export {
    
    /**
     * Export AISEO metadata to JSON
     *
     * @param array $args Export arguments
     * @return array|WP_Error Export data or error
     */
    public function export_to_json($args = []) {
        $defaults = [
            'post_type' => 'post',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'include_posts' => []
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $query_args = [
            'post_type' => $args['post_type'],
            'post_status' => $args['post_status'],
            'posts_per_page' => $args['posts_per_page'],
            'fields' => 'ids'
        ];
        
        if (!empty($args['include_posts'])) {
            $query_args['post__in'] = $args['include_posts'];
        }
        
        $post_ids = get_posts($query_args);
        
        if (empty($post_ids)) {
            return new WP_Error('no_posts', __('No posts found to export', 'aiseo'));
        }
        
        $export_data = [
            'version' => '1.2.0',
            'exported_at' => current_time('mysql'),
            'post_count' => count($post_ids),
            'posts' => []
        ];
        
        foreach ($post_ids as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                continue;
            }

            $post = get_post($post_id);
            if (!$post) {
                continue;
            }
            
            $export_data['posts'][] = [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'metadata' => [
                    'meta_title' => get_post_meta($post_id, '_aiseo_meta_title', true),
                    'meta_description' => get_post_meta($post_id, '_aiseo_meta_description', true),
                    'focus_keyword' => get_post_meta($post_id, '_aiseo_focus_keyword', true),
                    'canonical_url' => get_post_meta($post_id, '_aiseo_canonical_url', true),
                    'robots_index' => AISEO_Meta::post_is_noindex($post_id) ? 'noindex' : 'index',
                    'robots_follow' => AISEO_Meta::post_is_nofollow($post_id) ? 'nofollow' : 'follow',
                    'og_title' => get_post_meta($post_id, '_aiseo_og_title', true),
                    'og_description' => get_post_meta($post_id, '_aiseo_og_description', true),
                    'twitter_title' => get_post_meta($post_id, '_aiseo_twitter_title', true),
                    'twitter_description' => get_post_meta($post_id, '_aiseo_twitter_description', true),
                ]
            ];
        }

        if (empty($export_data['posts'])) {
            return new WP_Error('no_posts', __('No posts found to export', 'aiseo'));
        }

        $export_data['post_count'] = count($export_data['posts']);
        
        return $export_data;
    }
    
    /**
     * Export AISEO metadata to CSV
     *
     * @param array $args Export arguments
     * @return string|WP_Error CSV data or error
     */
    public function export_to_csv($args = []) {
        $json_data = $this->export_to_json($args);
        
        if (is_wp_error($json_data)) {
            return $json_data;
        }
        
        $csv_data = "Post ID,Post Title,Post Type,Meta Title,Meta Description,Focus Keyword,Canonical URL,Robots Index,Robots Follow\n";
        
        foreach ($json_data['posts'] as $post) {
            $csv_data .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $post['post_id'],
                str_replace('"', '""', $post['post_title']),
                $post['post_type'],
                str_replace('"', '""', $post['metadata']['meta_title']),
                str_replace('"', '""', $post['metadata']['meta_description']),
                str_replace('"', '""', $post['metadata']['focus_keyword']),
                str_replace('"', '""', $post['metadata']['canonical_url']),
                $post['metadata']['robots_index'],
                $post['metadata']['robots_follow']
            );
        }
        
        return $csv_data;
    }
    
    /**
     * Import AISEO metadata from JSON
     *
     * @param array $import_data Import data
     * @param array $options Import options
     * @return array Import results
     */
    public function import_from_json($import_data, $options = []) {
        $defaults = [
            'overwrite' => false,
            'skip_existing' => false
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        if (empty($import_data['posts']) || !is_array($import_data['posts'])) {
            return new WP_Error('invalid_data', __('Invalid import data format', 'aiseo'));
        }
        
        $results = [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($import_data['posts'] as $post_data) {
            if (!isset($post_data['post_id']) || !isset($post_data['metadata'])) {
                $results['failed']++;
                $results['errors'][] = 'Missing post_id or metadata in import data';
                continue;
            }
            
            $post_id = absint($post_data['post_id']);
            $post = get_post($post_id);
            
            if (!$post || !current_user_can('edit_post', $post_id)) {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    /* translators: %d: post ID */
                    __('You are not allowed to edit post %d.', 'aiseo'),
                    $post_id
                );
                continue;
            }
            
            $metadata = $post_data['metadata'];
            $updated = false;
            
            foreach ($metadata as $key => $value) {
                $meta_key = $this->allowed_import_meta_key($key);
                if (!$meta_key) {
                    continue;
                }

                $value = $this->sanitize_import_meta_value($key, $value);
                if ($value === '' && !$options['overwrite']) {
                    continue;
                }
                
                $existing = get_post_meta($post_id, $meta_key, true);
                
                if (!empty($existing) && $options['skip_existing']) {
                    continue;
                }
                
                update_post_meta($post_id, $meta_key, $value);
                $updated = true;
            }
            
            if ($updated) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Import from Yoast SEO
     *
     * @param array $options Import options
     * @return array Import results
     */
    public function import_from_yoast($options = []) {
        $defaults = [
            'post_type' => 'post',
            'limit' => -1,
            'overwrite' => false
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $posts = get_posts([
            'post_type' => $options['post_type'],
            'posts_per_page' => $options['limit'],
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        if (empty($posts)) {
            return new WP_Error('no_posts', __('No posts found', 'aiseo'));
        }
        
        $results = [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'imported_fields' => []
        ];
        
        foreach ($posts as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                $results['failed']++;
                continue;
            }

            $imported = false;
            
            // Import meta title
            $yoast_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
            if (!empty($yoast_title)) {
                $existing = get_post_meta($post_id, '_aiseo_meta_title', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_title', $yoast_title);
                    $imported = true;
                }
            }
            
            // Import meta description
            $yoast_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            if (!empty($yoast_desc)) {
                $existing = get_post_meta($post_id, '_aiseo_meta_description', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_description', $yoast_desc);
                    $imported = true;
                }
            }
            
            // Import focus keyword
            $yoast_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            if (!empty($yoast_keyword)) {
                $existing = get_post_meta($post_id, '_aiseo_focus_keyword', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_focus_keyword', $yoast_keyword);
                    $imported = true;
                }
            }
            
            // Import canonical URL
            $yoast_canonical = get_post_meta($post_id, '_yoast_wpseo_canonical', true);
            if (!empty($yoast_canonical)) {
                $existing = get_post_meta($post_id, '_aiseo_canonical_url', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_canonical_url', $yoast_canonical);
                    $imported = true;
                }
            }
            
            $yoast_noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
            $yoast_nofollow = get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);
            if ($this->import_robot_flags($post_id, $yoast_noindex === '1', $yoast_nofollow === '1', $options['overwrite'])) {
                $imported = true;
            }
            
            if ($imported) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Import from Rank Math
     *
     * @param array $options Import options
     * @return array Import results
     */
    public function import_from_rankmath($options = []) {
        $defaults = [
            'post_type' => 'post',
            'limit' => -1,
            'overwrite' => false
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $posts = get_posts([
            'post_type' => $options['post_type'],
            'posts_per_page' => $options['limit'],
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        if (empty($posts)) {
            return new WP_Error('no_posts', __('No posts found', 'aiseo'));
        }
        
        $results = [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0
        ];
        
        foreach ($posts as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                $results['failed']++;
                continue;
            }

            $imported = false;
            
            // Import meta title
            $rm_title = get_post_meta($post_id, 'rank_math_title', true);
            if (!empty($rm_title)) {
                $existing = get_post_meta($post_id, '_aiseo_meta_title', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_title', $rm_title);
                    $imported = true;
                }
            }
            
            // Import meta description
            $rm_desc = get_post_meta($post_id, 'rank_math_description', true);
            if (!empty($rm_desc)) {
                $existing = get_post_meta($post_id, '_aiseo_meta_description', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_description', $rm_desc);
                    $imported = true;
                }
            }
            
            // Import focus keyword
            $rm_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
            if (!empty($rm_keyword)) {
                $existing = get_post_meta($post_id, '_aiseo_focus_keyword', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_focus_keyword', $rm_keyword);
                    $imported = true;
                }
            }
            
            // Import canonical URL
            $rm_canonical = get_post_meta($post_id, 'rank_math_canonical_url', true);
            if (!empty($rm_canonical)) {
                $existing = get_post_meta($post_id, '_aiseo_canonical_url', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_canonical_url', $rm_canonical);
                    $imported = true;
                }
            }
            
            $rm_robots = get_post_meta($post_id, 'rank_math_robots', true);
            if (is_array($rm_robots) && $this->import_robot_flags(
                $post_id,
                in_array('noindex', $rm_robots, true),
                in_array('nofollow', $rm_robots, true),
                $options['overwrite']
            )) {
                $imported = true;
            }
            
            if ($imported) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Import from All in One SEO (AIOSEO)
     *
     * @param array $options Import options
     * @return array Import results
     */
    public function import_from_aioseo($options = []) {
        $defaults = [
            'post_type' => 'post',
            'limit' => -1,
            'overwrite' => false
        ];
        
        $options = wp_parse_args($options, $defaults);
        
        $posts = get_posts([
            'post_type' => $options['post_type'],
            'posts_per_page' => $options['limit'],
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        if (empty($posts)) {
            return new WP_Error('no_posts', __('No posts found', 'aiseo'));
        }
        
        $results = [
            'success' => 0,
            'skipped' => 0,
            'failed' => 0
        ];
        
        foreach ($posts as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                $results['failed']++;
                continue;
            }

            $imported = false;
            
            // AIOSEO stores data in _aioseo_* meta keys
            $aioseo_data = get_post_meta($post_id, '_aioseo_title', true);
            if (!empty($aioseo_data)) {
                $existing = get_post_meta($post_id, '_aiseo_meta_title', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_title', $aioseo_data);
                    $imported = true;
                }
            }
            
            $aioseo_desc = get_post_meta($post_id, '_aioseo_description', true);
            if (!empty($aioseo_desc)) {
                $existing = get_post_meta($post_id, '_aiseo_meta_description', true);
                if (empty($existing) || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_description', $aioseo_desc);
                    $imported = true;
                }
            }
            
            $aioseo_keywords = get_post_meta($post_id, '_aioseo_keywords', true);
            if (!empty($aioseo_keywords)) {
                $existing = get_post_meta($post_id, '_aiseo_focus_keyword', true);
                if (empty($existing) || $options['overwrite']) {
                    // AIOSEO stores keywords as comma-separated, take first one
                    $keywords_array = explode(',', $aioseo_keywords);
                    $focus_keyword = trim($keywords_array[0]);
                    update_post_meta($post_id, '_aiseo_focus_keyword', $focus_keyword);
                    $imported = true;
                }
            }
            
            if ($imported) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }
        }
        
        return $results;
    }

    /**
     * Dispatch by plugin slug used in Bulk Operations.
     *
     * @param string $source yoast|rankmath|aioseo|seopress
     * @param array  $options Import options.
     * @return array|WP_Error
     */
    public function import_from($source, $options = array()) {
        $source = sanitize_key($source);
        if ($source === 'yoast') {
            $result = $this->import_from_yoast($options);
        } elseif ($source === 'rankmath') {
            $result = $this->import_from_rankmath($options);
        } elseif ($source === 'aioseo') {
            $result = $this->import_from_aioseo($options);
        } elseif ($source === 'seopress') {
            $result = $this->import_from_seopress($options);
        } else {
            return new WP_Error('aiseo_import_source', __('Unknown import source.', 'aiseo'));
        }
        if (is_array($result) && !isset($result['count']) && isset($result['success'])) {
            $result['count'] = $result['success'];
        }
        return $result;
    }

    /**
     * Import from SEOPress post meta.
     *
     * @param array $options Import options.
     * @return array|WP_Error
     */
    public function import_from_seopress($options = array()) {
        $defaults = array(
            'post_type' => 'post',
            'limit' => -1,
            'overwrite' => false,
        );
        $options = wp_parse_args($options, $defaults);
        $posts = get_posts(array(
            'post_type' => $options['post_type'],
            'posts_per_page' => $options['limit'],
            'post_status' => 'any',
            'fields' => 'ids',
        ));
        if (empty($posts)) {
            return new WP_Error('no_posts', __('No posts found', 'aiseo'));
        }
        $results = array(
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'count' => 0,
        );
        foreach ($posts as $post_id) {
            if (!current_user_can('edit_post', $post_id)) {
                $results['failed']++;
                continue;
            }
            $imported = false;
            $title = get_post_meta($post_id, '_seopress_titles_title', true);
            if (is_string($title) && $title !== '') {
                $existing = get_post_meta($post_id, '_aiseo_meta_title', true);
                if ($existing === '' || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_title', sanitize_text_field($title));
                    $imported = true;
                }
            }
            $desc = get_post_meta($post_id, '_seopress_titles_desc', true);
            if (is_string($desc) && $desc !== '') {
                $existing = get_post_meta($post_id, '_aiseo_meta_description', true);
                if ($existing === '' || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_meta_description', sanitize_textarea_field($desc));
                    $imported = true;
                }
            }
            $keyword = get_post_meta($post_id, '_seopress_analysis_target_kw', true);
            if (is_string($keyword) && $keyword !== '') {
                $existing = get_post_meta($post_id, '_aiseo_focus_keyword', true);
                if ($existing === '' || $options['overwrite']) {
                    $first = trim(explode(',', $keyword)[0]);
                    update_post_meta($post_id, '_aiseo_focus_keyword', sanitize_text_field($first));
                    $imported = true;
                }
            }
            $canonical = get_post_meta($post_id, '_seopress_robots_canonical', true);
            if (is_string($canonical) && $canonical !== '') {
                $existing = get_post_meta($post_id, '_aiseo_canonical_url', true);
                if ($existing === '' || $options['overwrite']) {
                    update_post_meta($post_id, '_aiseo_canonical_url', esc_url_raw($canonical));
                    $imported = true;
                }
            }
            $noindex = get_post_meta($post_id, '_seopress_robots_index', true) === 'yes';
            $nofollow = get_post_meta($post_id, '_seopress_robots_follow', true) === 'yes';
            if ($this->import_robot_flags($post_id, $noindex, $nofollow, $options['overwrite'])) {
                $imported = true;
            }
            if ($imported) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }
        }
        $results['count'] = $results['success'];
        return $results;
    }
    
    /**
     * Create backup of all AISEO metadata
     *
     * @return array|WP_Error Backup data or error
     */
    public function create_backup() {
        return $this->export_to_json([
            'post_type' => 'any',
            'post_status' => 'any',
            'posts_per_page' => -1
        ]);
    }
    
    /**
     * Restore from backup
     *
     * @param array $backup_data Backup data
     * @return array Restore results
     */
    public function restore_backup($backup_data) {
        return $this->import_from_json($backup_data, [
            'overwrite' => true,
            'skip_existing' => false
        ]);
    }

    /**
     * Map an import field name to an allowlisted AISEO meta key.
     *
     * @param string $key Field name from the import payload.
     * @return string Meta key, or an empty string when the field is not allowlisted.
     */
    private function allowed_import_meta_key($key) {
        $allowed = array(
            'meta_title' => '_aiseo_meta_title',
            'meta_description' => '_aiseo_meta_description',
            'focus_keyword' => '_aiseo_focus_keyword',
            'canonical_url' => '_aiseo_canonical_url',
            'robots_index' => '_aiseo_noindex',
            'robots_follow' => '_aiseo_nofollow',
            'og_title' => '_aiseo_og_title',
            'og_description' => '_aiseo_og_description',
            'twitter_title' => '_aiseo_twitter_title',
            'twitter_description' => '_aiseo_twitter_description',
        );

        $key = sanitize_key($key);

        return isset($allowed[$key]) ? $allowed[$key] : '';
    }

    /**
     * Sanitise one allowlisted import value.
     *
     * @param string $key Field name from the import payload.
     * @param mixed  $value Raw value.
     * @return string
     */
    private function sanitize_import_meta_value($key, $value) {
        $key = sanitize_key($key);
        $value = is_scalar($value) ? (string) $value : '';

        if ($key === 'canonical_url') {
            return esc_url_raw($value);
        }

        if ($key === 'robots_index') {
            return in_array($value, array('noindex', '1', 'true'), true) ? '1' : '0';
        }

        if ($key === 'robots_follow') {
            return in_array($value, array('nofollow', '1', 'true'), true) ? '1' : '0';
        }

        if ($key === 'meta_description' || $key === 'og_description' || $key === 'twitter_description') {
            return sanitize_textarea_field($value);
        }

        return sanitize_text_field($value);
    }

    /**
     * Write runtime noindex/nofollow keys used by meta tags and sitemaps.
     *
     * @param int  $post_id   Post ID.
     * @param bool $noindex   Whether the source plugin marked the post noindex.
     * @param bool $nofollow  Whether the source plugin marked the post nofollow.
     * @param bool $overwrite Replace existing AISEO robots flags.
     * @return bool True when at least one flag was written.
     */
    private function import_robot_flags($post_id, $noindex, $nofollow, $overwrite) {
        $imported = false;
        if ($noindex) {
            $existing = get_post_meta($post_id, '_aiseo_noindex', true);
            if ($overwrite || !in_array($existing, array(true, 1, '1'), true)) {
                update_post_meta($post_id, '_aiseo_noindex', '1');
                $imported = true;
            }
        }
        if ($nofollow) {
            $existing = get_post_meta($post_id, '_aiseo_nofollow', true);
            if ($overwrite || !in_array($existing, array(true, 1, '1'), true)) {
                update_post_meta($post_id, '_aiseo_nofollow', '1');
                $imported = true;
            }
        }
        return $imported;
    }
}
