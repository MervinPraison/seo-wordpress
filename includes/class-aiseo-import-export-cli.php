<?php
/**
 * AISEO Import/Export WP-CLI Commands
 *
 * @package AISEO
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Import/Export WP-CLI Commands
 */
class AISEO_Import_Export_CLI {
    
    /**
     * Export AISEO metadata to JSON
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to export
     * ---
     * default: post
     * ---
     *
     * [--output=<file>]
     * : Output file path (optional, prints to stdout if not specified)
     *
     * ## EXAMPLES
     *
     *     wp aiseo export json
     *     wp aiseo export json --post-type=page
     *     wp aiseo export json --output=aiseo-export.json
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function json($args, $assoc_args) {
        $post_type = isset($assoc_args['post-type']) ? $assoc_args['post-type'] : 'post';
        $output_file = isset($assoc_args['output']) ? $assoc_args['output'] : null;
        
        WP_CLI::log(sprintf('Exporting %s metadata to JSON...', $post_type));
        
        $import_export = new AISEO_Import_Export();
        $result = $import_export->export_to_json(['post_type' => $post_type]);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        $json = json_encode($result, JSON_PRETTY_PRINT);
        
        if ($output_file) {
            file_put_contents($output_file, $json);
            WP_CLI::success(sprintf('Exported %d posts to %s', $result['post_count'], $output_file));
        } else {
            WP_CLI::log($json);
            WP_CLI::success(sprintf('Exported %d posts', $result['post_count']));
        }
    }
    
    /**
     * Export AISEO metadata to CSV
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to export
     * ---
     * default: post
     * ---
     *
     * [--output=<file>]
     * : Output file path (optional, prints to stdout if not specified)
     *
     * ## EXAMPLES
     *
     *     wp aiseo export csv
     *     wp aiseo export csv --post-type=page
     *     wp aiseo export csv --output=aiseo-export.csv
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function csv($args, $assoc_args) {
        $post_type = isset($assoc_args['post-type']) ? $assoc_args['post-type'] : 'post';
        $output_file = isset($assoc_args['output']) ? $assoc_args['output'] : null;
        
        WP_CLI::log(sprintf('Exporting %s metadata to CSV...', $post_type));
        
        $import_export = new AISEO_Import_Export();
        $result = $import_export->export_to_csv(['post_type' => $post_type]);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        if ($output_file) {
            file_put_contents($output_file, $result);
            WP_CLI::success(sprintf('Exported to %s', $output_file));
        } else {
            WP_CLI::log($result);
        }
    }
    
    /**
     * Import AISEO metadata from JSON file
     *
     * ## OPTIONS
     *
     * <file>
     * : JSON file to import
     *
     * [--overwrite]
     * : Overwrite existing metadata
     *
     * ## EXAMPLES
     *
     *     wp aiseo import json aiseo-export.json
     *     wp aiseo import json aiseo-export.json --overwrite
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function import_json($args, $assoc_args) {
        $file = $args[0];
        $overwrite = isset($assoc_args['overwrite']);
        
        if (!file_exists($file)) {
            WP_CLI::error(sprintf('File not found: %s', $file));
        }
        
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        
        if (!$data) {
            WP_CLI::error('Invalid JSON file');
        }
        
        WP_CLI::log(sprintf('Importing from %s...', $file));
        WP_CLI::log(sprintf('Posts to import: %d', count($data['posts'])));
        WP_CLI::log(sprintf('Overwrite: %s', $overwrite ? 'Yes' : 'No'));
        WP_CLI::log('');
        
        $import_export = new AISEO_Import_Export();
        $result = $import_export->import_from_json($data, ['overwrite' => $overwrite]);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success(sprintf(
            'Import complete. Success: %d, Skipped: %d, Failed: %d',
            $result['success'],
            $result['skipped'],
            $result['failed']
        ));
        
        if (!empty($result['errors'])) {
            WP_CLI::warning('Errors encountered:');
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                WP_CLI::log('  - ' . $error);
            }
        }
    }
    
    /**
     * Import from Yoast SEO
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to import
     * ---
     * default: post
     * ---
     *
     * [--limit=<number>]
     * : Limit number of posts
     * ---
     * default: -1
     * ---
     *
     * [--overwrite]
     * : Overwrite existing AISEO metadata
     *
     * ## EXAMPLES
     *
     *     wp aiseo import yoast
     *     wp aiseo import yoast --post-type=page
     *     wp aiseo import yoast --limit=100 --overwrite
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function yoast($args, $assoc_args) {
        $post_type = isset($assoc_args['post-type']) ? $assoc_args['post-type'] : 'post';
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : -1;
        $overwrite = isset($assoc_args['overwrite']);
        
        WP_CLI::log('Importing from Yoast SEO...');
        WP_CLI::log(sprintf('Post type: %s', $post_type));
        WP_CLI::log(sprintf('Overwrite: %s', $overwrite ? 'Yes' : 'No'));
        WP_CLI::log('');
        
        $import_export = new AISEO_Import_Export();
        $result = $import_export->import_from_yoast([
            'post_type' => $post_type,
            'limit' => $limit,
            'overwrite' => $overwrite
        ]);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success(sprintf(
            'Yoast import complete. Success: %d, Skipped: %d',
            $result['success'],
            $result['skipped']
        ));
    }
    
    /**
     * Import from Rank Math
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to import
     * ---
     * default: post
     * ---
     *
     * [--limit=<number>]
     * : Limit number of posts
     * ---
     * default: -1
     * ---
     *
     * [--overwrite]
     * : Overwrite existing AISEO metadata
     *
     * ## EXAMPLES
     *
     *     wp aiseo import rankmath
     *     wp aiseo import rankmath --post-type=page
     *     wp aiseo import rankmath --limit=100 --overwrite
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function rankmath($args, $assoc_args) {
        $post_type = isset($assoc_args['post-type']) ? $assoc_args['post-type'] : 'post';
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : -1;
        $overwrite = isset($assoc_args['overwrite']);
        
        WP_CLI::log('Importing from Rank Math...');
        WP_CLI::log(sprintf('Post type: %s', $post_type));
        WP_CLI::log(sprintf('Overwrite: %s', $overwrite ? 'Yes' : 'No'));
        WP_CLI::log('');
        
        $import_export = new AISEO_Import_Export();
        $result = $import_export->import_from_rankmath([
            'post_type' => $post_type,
            'limit' => $limit,
            'overwrite' => $overwrite
        ]);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success(sprintf(
            'Rank Math import complete. Success: %d, Skipped: %d',
            $result['success'],
            $result['skipped']
        ));
    }
    
    /**
     * Import from All in One SEO (AIOSEO)
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to import
     * ---
     * default: post
     * ---
     *
     * [--limit=<number>]
     * : Limit number of posts
     * ---
     * default: -1
     * ---
     *
     * [--overwrite]
     * : Overwrite existing AISEO metadata
     *
     * ## EXAMPLES
     *
     *     wp aiseo import aioseo
     *     wp aiseo import aioseo --post-type=page
     *     wp aiseo import aioseo --limit=100 --overwrite
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function aioseo($args, $assoc_args) {
        $post_type = isset($assoc_args['post-type']) ? $assoc_args['post-type'] : 'post';
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : -1;
        $overwrite = isset($assoc_args['overwrite']);
        
        WP_CLI::log('Importing from All in One SEO...');
        WP_CLI::log(sprintf('Post type: %s', $post_type));
        WP_CLI::log(sprintf('Overwrite: %s', $overwrite ? 'Yes' : 'No'));
        WP_CLI::log('');
        
        $import_export = new AISEO_Import_Export();
        $result = $import_export->import_from_aioseo([
            'post_type' => $post_type,
            'limit' => $limit,
            'overwrite' => $overwrite
        ]);
        
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        
        WP_CLI::success(sprintf(
            'AIOSEO import complete. Success: %d, Skipped: %d',
            $result['success'],
            $result['skipped']
        ));
    }

    /**
     * Import from SEOPress
     *
     * ## OPTIONS
     *
     * [--post-type=<type>]
     * : Post type to import
     * ---
     * default: post
     * ---
     *
     * [--limit=<number>]
     * : Limit number of posts
     * ---
     * default: -1
     * ---
     *
     * [--overwrite]
     * : Overwrite existing AISEO metadata
     *
     * ## EXAMPLES
     *
     *     wp aiseo import seopress
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function seopress($args, $assoc_args) {
        $post_type = isset($assoc_args['post-type']) ? $assoc_args['post-type'] : 'post';
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : -1;
        $overwrite = isset($assoc_args['overwrite']);

        WP_CLI::log('Importing from SEOPress...');
        WP_CLI::log(sprintf('Post type: %s', $post_type));
        WP_CLI::log(sprintf('Overwrite: %s', $overwrite ? 'Yes' : 'No'));
        WP_CLI::log('');

        $import_export = new AISEO_Import_Export();
        $result = $import_export->import_from_seopress([
            'post_type' => $post_type,
            'limit' => $limit,
            'overwrite' => $overwrite
        ]);

        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }

        WP_CLI::success(sprintf(
            'SEOPress import complete. Success: %d, Skipped: %d',
            $result['success'],
            $result['skipped']
        ));
    }
}

// Register WP-CLI commands
WP_CLI::add_command('aiseo export', 'AISEO_Import_Export_CLI');
WP_CLI::add_command('aiseo import json', ['AISEO_Import_Export_CLI', 'import_json']);
WP_CLI::add_command('aiseo import yoast', ['AISEO_Import_Export_CLI', 'yoast']);
WP_CLI::add_command('aiseo import rankmath', ['AISEO_Import_Export_CLI', 'rankmath']);
WP_CLI::add_command('aiseo import aioseo', ['AISEO_Import_Export_CLI', 'aioseo']);
WP_CLI::add_command('aiseo import seopress', ['AISEO_Import_Export_CLI', 'seopress']);
