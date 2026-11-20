<?php
/**
 * AISEO Core Plugin Class
 *
 * Central initialization and coordination
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Core {
    
    /**
     * Plugin components
     */
    private $api;
    private $rest;
    private $meta;
    private $analysis;
    private $schema;
    private $sitemap;
    private $redirects;
    private $social;
    private $admin;
    private $metabox;
    private $admin_columns;
    private $image_seo;
    private $homepage_seo;
    private $taxonomy_seo;
    private $webmaster;
    private $analytics;
    private $title_templates;
    private $robots;
    private $llms;
    private $indexnow;
    private $html_sitemap;
    private $multilingual;
    private $breadcrumbs;
    private $rss;
    private $search_console;
    private $roles;
    private $setup;
    private $abilities;
    private $blocks;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Components will be loaded on init
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        $this->load_dependencies();
        $this->init_hooks();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        // Core components are autoloaded
        // Initialize them here
        $this->api = new AISEO_API();
        $this->rest = new AISEO_REST();
        $this->meta = new AISEO_Meta();
        $this->analysis = new AISEO_Analysis();
        $this->social = new AISEO_Social();
        $this->sitemap = new AISEO_Sitemap();
        $this->schema = new AISEO_Schema();
        
        // Initialize meta tags handler
        $this->meta->init();
        
        if (!in_array(get_option('aiseo_enable_social_tags', '1'), array(false, 0, '0', ''), true)) {
            $this->social->init();
        }
        
        // Initialize sitemap generator
        $this->sitemap->init();

        // JSON-LD on public singular pages when schema is enabled.
        $this->schema->init();

        $this->redirects = new AISEO_Redirects();
        $this->redirects->init();
        
        // Initialize homepage SEO
        $this->homepage_seo = new AISEO_Homepage_SEO();
        $this->homepage_seo->init();
        
        // Initialize taxonomy SEO
        $this->taxonomy_seo = new AISEO_Taxonomy_SEO();
        $this->taxonomy_seo->init();
        
        // Initialize webmaster verification
        $this->webmaster = new AISEO_Webmaster();
        $this->webmaster->init();
        
        // Initialize analytics
        $this->analytics = new AISEO_Analytics();
        $this->analytics->init();
        
        // Initialize title templates
        $this->title_templates = new AISEO_Title_Templates();
        $this->title_templates->init();
        
        // Initialize robots settings
        $this->robots = new AISEO_Robots();
        $this->robots->init();

        $this->llms = new AISEO_Llms();
        $this->llms->init();

        $this->indexnow = new AISEO_IndexNow();
        $this->indexnow->init();

        $this->html_sitemap = new AISEO_Html_Sitemap();
        $this->html_sitemap->init();

        $this->multilingual = new AISEO_Multilingual();
        $this->multilingual->init();
        
        // Initialize breadcrumbs
        $this->breadcrumbs = new AISEO_Breadcrumbs();
        $this->breadcrumbs->init();
        
        // Initialize RSS customization
        $this->rss = new AISEO_RSS();
        $this->rss->init();

        $this->search_console = new AISEO_Search_Console();
        $this->search_console->init();

        $this->roles = new AISEO_Roles();
        $this->roles->init();

        $this->setup = new AISEO_Setup();
        $this->setup->init();

        $this->abilities = new AISEO_Abilities();
        $this->abilities->init();

        $this->blocks = new AISEO_Blocks();
        $this->blocks->init();
        
        // Initialize metabox and image SEO (only in admin)
        if (is_admin()) {
            $this->metabox = new AISEO_Metabox();
            $this->metabox->init();

            $this->admin_columns = new AISEO_Admin_Columns();
            $this->admin_columns->init();
            
            $this->image_seo = new AISEO_Image_SEO();
        }
        
        // Other components will be initialized as needed
    }
    
    /**
     * Register WordPress hooks
     */
    private function init_hooks() {
        // Schedule cron jobs
        add_action('init', array($this, 'schedule_cron_jobs'));
        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Register REST API routes
        add_action('rest_api_init', array($this->rest, 'register_routes'));

        add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 95);
    }

    /**
     * Admin bar shortcuts, matching the Yoast-style front-end SEO menu.
     *
     * @param WP_Admin_Bar $wp_admin_bar Toolbar.
     */
    public function admin_bar_menu($wp_admin_bar) {
        if (!is_object($wp_admin_bar) || !current_user_can('edit_posts')) {
            return;
        }

        $parent_title = __('AISEO', 'aiseo');
        if (!is_admin() && is_singular()) {
            $bar_post = get_queried_object_id();
            if ($bar_post && class_exists('AISEO_Meta') && AISEO_Meta::post_is_noindex($bar_post)) {
                $parent_title = __('AISEO: noindex', 'aiseo');
            }
        }
        $wp_admin_bar->add_node(array(
            'id' => 'aiseo',
            'title' => $parent_title,
            'href' => admin_url('admin.php?page=aiseo'),
        ));

        if (current_user_can('aiseo_manage_settings')) {
            $wp_admin_bar->add_node(array(
                'parent' => 'aiseo',
                'id' => 'aiseo-settings',
                'title' => __('Settings', 'aiseo'),
                'href' => admin_url('admin.php?page=aiseo&tab=settings'),
            ));
            $wp_admin_bar->add_node(array(
                'parent' => 'aiseo',
                'id' => 'aiseo-setup',
                'title' => __('Setup wizard', 'aiseo'),
                'href' => admin_url('admin.php?page=aiseo-setup'),
            ));
            if (!in_array(get_option('aiseo_enable_sitemap', '1'), array(false, 0, '0', ''), true)) {
                $wp_admin_bar->add_node(array(
                    'parent' => 'aiseo',
                    'id' => 'aiseo-sitemap',
                    'title' => __('XML sitemap', 'aiseo'),
                    'href' => home_url('/sitemap_index.xml'),
                    'meta' => array('target' => '_blank', 'rel' => 'noopener noreferrer'),
                ));
            }
            if (!in_array(get_option('aiseo_enable_llms', '1'), array(false, 0, '0', ''), true)) {
                $wp_admin_bar->add_node(array(
                    'parent' => 'aiseo',
                    'id' => 'aiseo-llms',
                    'title' => __('llms.txt', 'aiseo'),
                    'href' => home_url('/llms.txt'),
                    'meta' => array('target' => '_blank', 'rel' => 'noopener noreferrer'),
                ));
            }
            if (class_exists('AISEO_Schema') && (new AISEO_Schema())->aggregation_is_enabled()) {
                $wp_admin_bar->add_node(array(
                    'parent' => 'aiseo',
                    'id' => 'aiseo-schema-map',
                    'title' => __('Schema map', 'aiseo'),
                    'href' => home_url('/aiseo-schema.json'),
                    'meta' => array('target' => '_blank', 'rel' => 'noopener noreferrer'),
                ));
            }
        }

        if (!is_admin() && is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id && current_user_can('edit_post', $post_id)) {
                $edit_link = get_edit_post_link($post_id, 'raw');
                if ($edit_link) {
                    $wp_admin_bar->add_node(array(
                        'parent' => 'aiseo',
                        'id' => 'aiseo-edit',
                        'title' => __('Edit SEO', 'aiseo'),
                        'href' => $edit_link,
                    ));
                }
            }
        }
    }
    
    /**
     * Define admin-specific hooks
     */
    private function define_admin_hooks() {
        if (!is_admin()) {
            return;
        }
        
        // Admin hooks will be added here
    }
    
    /**
     * Define public-facing hooks
     */
    private function define_public_hooks() {
        // Public hooks will be added here
    }
    
    /**
     * Schedule cron jobs
     */
    public function schedule_cron_jobs() {
        // Cache warming - daily at 3 AM
        if (!wp_next_scheduled('aiseo_cache_warming')) {
            wp_schedule_event(strtotime('tomorrow 03:00:00'), 'daily', 'aiseo_cache_warming');
        }
        
        // Process request queue - every 5 minutes
        if (!wp_next_scheduled('aiseo_process_queue')) {
            wp_schedule_event(time(), 'aiseo_five_minutes', 'aiseo_process_queue');
        }
        
        // Cleanup old logs - weekly
        if (!wp_next_scheduled('aiseo_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'aiseo_cleanup_logs');
        }
    }
    
    /**
     * Add custom cron intervals
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_intervals($schedules) {
        $schedules['aiseo_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'aiseo'),
        );
        
        return $schedules;
    }
    
    /**
     * Get API instance
     *
     * @return AISEO_API
     */
    public function get_api() {
        return $this->api;
    }
}
