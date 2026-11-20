<?php
/**
 * AISEO Admin Interface
 *
 * Main admin class that handles menu, tabs, and page rendering
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Admin {
    
    /**
     * Current active tab
     */
    private $active_tab;
    
    /**
     * Tab definitions
     */
    private $tabs;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->define_tabs();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_gutenberg_assets'));
        add_action('wp_ajax_aiseo_admin_action', array($this, 'handle_ajax_request'));
        
        // Individual AJAX handlers for UI buttons
        add_action('wp_ajax_aiseo_create_post', array($this, 'ajax_create_post'));
        add_action('wp_ajax_aiseo_generate_title', array($this, 'ajax_generate_title'));
        add_action('wp_ajax_aiseo_generate_description', array($this, 'ajax_generate_description'));
        add_action('wp_ajax_aiseo_generate_keyword', array($this, 'ajax_generate_keyword'));
        add_action('wp_ajax_aiseo_analyze_content', array($this, 'ajax_analyze_post'));
        
        // Technical SEO handlers
        add_action('wp_ajax_aiseo_add_redirect', array($this, 'ajax_add_redirect'));
        add_action('wp_ajax_aiseo_list_redirects', array($this, 'ajax_list_redirects'));
        add_action('wp_ajax_aiseo_delete_redirect', array($this, 'ajax_delete_redirect'));
        add_action('admin_post_aiseo_export_redirects', array($this, 'handle_export_redirects'));
        add_action('admin_post_aiseo_import_redirects', array($this, 'handle_import_redirects'));
        add_action('wp_ajax_aiseo_optimize_permalinks', array($this, 'ajax_optimize_permalinks'));
        add_action('wp_ajax_aiseo_regenerate_sitemap', array($this, 'ajax_regenerate_sitemap'));
        add_action('wp_ajax_aiseo_generate_image_alt', array($this, 'ajax_generate_image_alt'));
        add_action('wp_ajax_aiseo_find_missing_alt', array($this, 'ajax_find_missing_alt'));
        add_action('wp_ajax_aiseo_generate_single_alt', array($this, 'ajax_generate_single_alt'));
        
        // Advanced tab handlers
        add_action('wp_ajax_aiseo_save_cpt_settings', array($this, 'ajax_save_cpt_settings'));
        add_action('wp_ajax_aiseo_generate_report', array($this, 'ajax_generate_report'));
        add_action('wp_ajax_aiseo_keyword_research', array($this, 'ajax_keyword_research'));
        add_action('wp_ajax_aiseo_generate_brief', array($this, 'ajax_generate_brief'));
        
        // AI Content additional features
        add_action('wp_ajax_aiseo_rewrite_content', array($this, 'ajax_rewrite_content'));
        add_action('wp_ajax_aiseo_content_suggestions', array($this, 'ajax_content_suggestions'));
        add_action('wp_ajax_aiseo_generate_outline', array($this, 'ajax_generate_outline'));
        add_action('wp_ajax_aiseo_generate_faq', array($this, 'ajax_generate_faq'));
        
        // SEO Tools additional features
        add_action('wp_ajax_aiseo_internal_linking', array($this, 'ajax_internal_linking'));
        add_action('wp_ajax_aiseo_meta_variations', array($this, 'ajax_meta_variations'));
        
        // Nonce refresh endpoint (no nonce check needed for getting a new nonce)
        add_action('wp_ajax_aiseo_refresh_nonce', array($this, 'ajax_refresh_nonce'));
        
        // SEO Improvement handlers
        add_action('wp_ajax_aiseo_improve_seo_meta', array($this, 'ajax_improve_seo_meta'));
        add_action('wp_ajax_aiseo_improve_content_block', array($this, 'ajax_improve_content_block'));
        
        // Bulk Operations
        add_action('wp_ajax_aiseo_import_seo', array($this, 'ajax_import_seo'));
        add_action('wp_ajax_aiseo_export_seo', array($this, 'ajax_export_seo'));
        add_action('wp_ajax_aiseo_save_title', array($this, 'ajax_save_title'));
        add_action('wp_ajax_aiseo_save_description', array($this, 'ajax_save_description'));
    }
    
    /**
     * AJAX: Refresh nonce
     * Returns a fresh nonce without requiring nonce verification
     */
    public function ajax_refresh_nonce() {
        check_ajax_referer('aiseo_refresh_nonce', 'nonce');
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $new_nonce = wp_create_nonce('aiseo_admin_nonce');
        wp_send_json_success(array('nonce' => $new_nonce));
    }
    
    /**
     * Define all tabs
     */
    private function define_tabs() {
        $this->tabs = array(
            'dashboard' => array(
                'title' => __('Dashboard', 'aiseo'),
                'icon' => 'dashicons-dashboard',
                'capability' => 'edit_posts',
                'callback' => array($this, 'render_dashboard_tab'),
            ),
            'settings' => array(
                'title' => __('Settings', 'aiseo'),
                'icon' => 'dashicons-admin-settings',
                'capability' => 'aiseo_manage_settings',
                'callback' => array($this, 'render_settings_tab'),
            ),
            'technical-seo' => array(
                'title' => __('Technical SEO', 'aiseo'),
                'icon' => 'dashicons-admin-tools',
                'capability' => 'aiseo_manage_settings',
                'callback' => array($this, 'render_technical_seo_tab'),
            ),
            'seo-tools' => array(
                'title' => __('SEO Tools', 'aiseo'),
                'icon' => 'dashicons-search',
                'capability' => 'edit_posts',
                'callback' => array($this, 'render_seo_tools_tab'),
            ),
            'bulk-operations' => array(
                'title' => __('Bulk Operations', 'aiseo'),
                'icon' => 'dashicons-admin-page',
                'capability' => 'edit_posts',
                'callback' => array($this, 'render_bulk_operations_tab'),
            ),
            'monitoring' => array(
                'title' => __('Monitoring', 'aiseo'),
                'icon' => 'dashicons-chart-line',
                'capability' => 'edit_posts',
                'callback' => array($this, 'render_monitoring_tab'),
            ),
            'advanced' => array(
                'title' => __('Advanced', 'aiseo'),
                'icon' => 'dashicons-admin-generic',
                'capability' => 'manage_options',
                'callback' => array($this, 'render_advanced_tab'),
            ),
            'ai-content' => array(
                'title' => __('AI Content', 'aiseo'),
                'icon' => 'dashicons-edit',
                'capability' => 'edit_posts',
                'callback' => array($this, 'render_ai_content_tab'),
            ),
        );
    }
    
    /**
     * Get active tab from URL
     */
    private function get_active_tab() {
        $requested = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';

        if ($requested && isset($this->tabs[$requested]) && current_user_can($this->tabs[$requested]['capability'])) {
            return $requested;
        }

        foreach ($this->tabs as $tab_key => $tab) {
            if (current_user_can($tab['capability'])) {
                return $tab_key;
            }
        }

        return 'dashboard';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Praison AI SEO', 'aiseo'),
            __('AISEO', 'aiseo'),
            'edit_posts',
            'aiseo',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            30
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on AISEO pages
        if (strpos($hook, 'aiseo') === false) {
            return;
        }
        
        // Styles
        wp_enqueue_style(
            'aiseo-admin',
            AISEO_PLUGIN_URL . 'admin/css/aiseo-admin.css',
            array(),
            AISEO_VERSION
        );
        
        // Scripts
        wp_enqueue_media();
        wp_enqueue_script(
            'aiseo-admin',
            AISEO_PLUGIN_URL . 'admin/js/aiseo-admin.js',
            array('jquery', 'wp-util'),
            AISEO_VERSION,
            true
        );
        
        // Localize script with nonce refresh support
        wp_localize_script('aiseo-admin', 'aiseoAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiseo_admin_nonce'),
            'refreshNonce' => wp_create_nonce('aiseo_refresh_nonce'),
            'nonceRefreshAction' => 'aiseo_refresh_nonce',
            'strings' => array(
                'generating' => __('Generating...', 'aiseo'),
                'success' => __('Success!', 'aiseo'),
                'error' => __('Error occurred', 'aiseo'),
                'confirm' => __('Are you sure?', 'aiseo'),
                'sessionExpired' => __('Session expired. Refreshing...', 'aiseo'),
                'redirectAdded' => __('Redirect added.', 'aiseo'),
            ),
        ));

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'dashboard';
        if ($tab === 'bulk-operations') {
            wp_enqueue_script(
                'aiseo-bulk',
                AISEO_PLUGIN_URL . 'admin/js/aiseo-bulk.js',
                array('jquery', 'aiseo-admin'),
                AISEO_VERSION,
                true
            );
            wp_localize_script('aiseo-bulk', 'aiseoBulk', array(
                'i18n' => array(
                    'selectPosts' => __('Please select at least one post', 'aiseo'),
                    'processing' => __('Processing', 'aiseo'),
                    'of' => __('of', 'aiseo'),
                    'complete' => __('Complete! Review and approve changes below.', 'aiseo'),
                    'selectSave' => __('Please select at least one item to save', 'aiseo'),
                    'saving' => __('Saving...', 'aiseo'),
                    'saveApproved' => __('Save Approved Changes', 'aiseo'),
                    'saved' => __('Saved', 'aiseo'),
                    'changes' => __('changes successfully!', 'aiseo'),
                    'title' => __('Title', 'aiseo'),
                    'description' => __('Description', 'aiseo'),
                    'analysis' => __('Analysis', 'aiseo'),
                    'newField' => __('New', 'aiseo'),
                    'error' => __('Error', 'aiseo'),
                    'unknownError' => __('Unknown error', 'aiseo'),
                    'noResults' => __('No results to display.', 'aiseo'),
                    'sessionError' => __('Session Error: Please log out and log back in to WordPress', 'aiseo'),
                    'connectionError' => __('Connection error', 'aiseo'),
                    'importConfirm' => __('Import SEO data from this plugin? This may take a few minutes.', 'aiseo'),
                    'importing' => __('Importing...', 'aiseo'),
                    'success' => __('Success!', 'aiseo'),
                    'imported' => __('Imported', 'aiseo'),
                    'itemsFrom' => __('items from', 'aiseo'),
                    'importFailed' => __('Import failed', 'aiseo'),
                    'exporting' => __('Exporting...', 'aiseo'),
                    'preparing' => __('Preparing export...', 'aiseo'),
                    'noExport' => __('No data to export.', 'aiseo'),
                    'exported' => __('Exported', 'aiseo'),
                    'postsTo' => __('posts to', 'aiseo'),
                    'exportFailed' => __('Export failed', 'aiseo'),
                ),
            ));
        }
        if ($tab === 'technical-seo') {
            wp_enqueue_script(
                'aiseo-technical',
                AISEO_PLUGIN_URL . 'admin/js/aiseo-technical.js',
                array('jquery', 'aiseo-admin'),
                AISEO_VERSION,
                true
            );
            wp_localize_script('aiseo-technical', 'aiseoTechnical', array(
                'i18n' => array(
                    'loading' => __('Loading redirects...', 'aiseo'),
                    'from' => __('From URL', 'aiseo'),
                    'to' => __('To URL', 'aiseo'),
                    'type' => __('Type', 'aiseo'),
                    'regex' => __('Regex', 'aiseo'),
                    'hits' => __('Hits', 'aiseo'),
                    'actions' => __('Actions', 'aiseo'),
                    'yes' => __('Yes', 'aiseo'),
                    'no' => __('No', 'aiseo'),
                    'delete' => __('Delete', 'aiseo'),
                    'items' => __('items', 'aiseo'),
                    'previous' => __('Previous', 'aiseo'),
                    'next' => __('Next', 'aiseo'),
                    'of' => __('of', 'aiseo'),
                    'none' => __('No redirects found. Create one above to get started.', 'aiseo'),
                    'loadError' => __('Error loading redirects.', 'aiseo'),
                    'adding' => __('Adding...', 'aiseo'),
                    'added' => __('Redirect added.', 'aiseo'),
                    'addRedirect' => __('Add Redirect', 'aiseo'),
                    'deleteConfirm' => __('Are you sure you want to delete this redirect?', 'aiseo'),
                    'deleting' => __('Deleting...', 'aiseo'),
                    'error' => __('Error', 'aiseo'),
                    'regenerating' => __('Regenerating...', 'aiseo'),
                    'sitemapOk' => __('Sitemap regenerated successfully!', 'aiseo'),
                    'regenerate' => __('Regenerate Sitemap', 'aiseo'),
                    'searching' => __('Searching...', 'aiseo'),
                    'searchingImages' => __('Searching for images without alt text...', 'aiseo'),
                    'found' => __('Found', 'aiseo'),
                    'missingAlt' => __('images without alt text:', 'aiseo'),
                    'image' => __('Image', 'aiseo'),
                    'post' => __('Post', 'aiseo'),
                    'generateAlt' => __('Generate Alt', 'aiseo'),
                    'editPost' => __('Edit Post', 'aiseo'),
                    'allHaveAlt' => __('All images have alt text.', 'aiseo'),
                    'findMissing' => __('Find Images Missing Alt Text', 'aiseo'),
                    'generateAllConfirm' => __('Generate alt text for all images? This may take a while.', 'aiseo'),
                    'generating' => __('Generating...', 'aiseo'),
                    'progress' => __('Generating Alt Text Progress', 'aiseo'),
                    'status' => __('Status', 'aiseo'),
                    'completed' => __('Completed', 'aiseo'),
                    'failed' => __('Failed', 'aiseo'),
                    'noImages' => __('No images found that need alt text.', 'aiseo'),
                    'generateAll' => __('Generate Alt Text for All Images', 'aiseo'),
                ),
            ));
        }
        if ($tab === 'seo-tools') {
            wp_enqueue_script(
                'aiseo-seo-tools',
                AISEO_PLUGIN_URL . 'admin/js/aiseo-seo-tools.js',
                array('jquery', 'aiseo-admin'),
                AISEO_VERSION,
                true
            );
            wp_localize_script('aiseo-seo-tools', 'aiseoSeoTools', array(
                'i18n' => array(
                    'selectPost' => __('Please select a post first', 'aiseo'),
                    'generating' => __('Generating...', 'aiseo'),
                    'failed' => __('Failed to generate', 'aiseo'),
                    'connectionError' => __('Connection error', 'aiseo'),
                    'generateTitle' => __('Generate Title', 'aiseo'),
                    'generateDescription' => __('Generate Description', 'aiseo'),
                    'analyzing' => __('Analyzing...', 'aiseo'),
                    'analysisResults' => __('Analysis Results', 'aiseo'),
                    'seoScore' => __('SEO Score', 'aiseo'),
                    'failedAnalyze' => __('Failed to analyze', 'aiseo'),
                    'analyzeContent' => __('Analyze Content', 'aiseo'),
                    'gettingSuggestions' => __('Getting suggestions...', 'aiseo'),
                    'untitled' => __('Untitled', 'aiseo'),
                    'noSuggestions' => __('No suggestions found', 'aiseo'),
                    'getLinking' => __('Get Linking Suggestions', 'aiseo'),
                    'characters' => __('characters', 'aiseo'),
                    'noVariations' => __('No variations generated. Try a different post.', 'aiseo'),
                    'generateTitleVariations' => __('Generate Title Variations', 'aiseo'),
                    'generateDescVariations' => __('Generate Description Variations', 'aiseo'),
                ),
            ));
        }
        if ($tab === 'ai-content') {
            wp_enqueue_script(
                'aiseo-ai-content',
                AISEO_PLUGIN_URL . 'admin/js/aiseo-ai-content.js',
                array('jquery', 'aiseo-admin'),
                AISEO_VERSION,
                true
            );
            wp_localize_script('aiseo-ai-content', 'aiseoAiContent', array(
                'i18n' => array(
                    'enterTopic' => __('Please enter a topic', 'aiseo'),
                    'generating' => __('Generating Content...', 'aiseo'),
                    'error' => __('Error:', 'aiseo'),
                    'failedContent' => __('Failed to generate content', 'aiseo'),
                    'connectionError' => __('Connection error', 'aiseo'),
                    'timeout60' => __('Request timed out (60s). Please try again with shorter content.', 'aiseo'),
                    'generatePost' => __('Generate Post', 'aiseo'),
                    'noContent' => __('No content to create post from', 'aiseo'),
                    'creating' => __('Creating Post...', 'aiseo'),
                    'success' => __('Success!', 'aiseo'),
                    'postCreated' => __('Post created!', 'aiseo'),
                    'editPost' => __('Edit post', 'aiseo'),
                    'errorOccurred' => __('Error occurred', 'aiseo'),
                    'timeout30' => __('Request timed out (30s). The post may still be creating in the background.', 'aiseo'),
                    'createPost' => __('Create Post', 'aiseo'),
                    'enterRewrite' => __('Please enter content to rewrite', 'aiseo'),
                    'rewriting' => __('Rewriting...', 'aiseo'),
                    'unexpectedFormat' => __('Rewrite completed but content format is unexpected.', 'aiseo'),
                    'rewriteFailed' => __('Rewrite failed', 'aiseo'),
                    'rewriteContent' => __('Rewrite Content', 'aiseo'),
                    'gettingSuggestions' => __('Getting suggestions...', 'aiseo'),
                    'noSuggestions' => __('No suggestions generated. Try a different topic.', 'aiseo'),
                    'failedSuggestions' => __('Failed to get suggestions', 'aiseo'),
                    'getSuggestions' => __('Get Suggestions', 'aiseo'),
                    'failedOutline' => __('Failed to generate outline', 'aiseo'),
                    'introduction' => __('Introduction', 'aiseo'),
                    'mainSections' => __('Main Sections', 'aiseo'),
                    'section' => __('Section', 'aiseo'),
                    'conclusion' => __('Conclusion', 'aiseo'),
                    'cta' => __('Call to Action', 'aiseo'),
                    'emptyOutline' => __('No outline generated. Try a different topic or check your API key.', 'aiseo'),
                    'generateOutline' => __('Generate Outline', 'aiseo'),
                    'enterContent' => __('Please enter content', 'aiseo'),
                    'failedFaq' => __('Failed to generate FAQs', 'aiseo'),
                    'noQuestion' => __('No question', 'aiseo'),
                    'noAnswer' => __('No answer', 'aiseo'),
                    'emptyFaq' => __('No FAQs generated. Try different content or check your API key.', 'aiseo'),
                    'generateFaqs' => __('Generate FAQs', 'aiseo'),
                    'copied' => __('Copied!', 'aiseo'),
                    'generatedFrom' => __('Generated from', 'aiseo'),
                    'serverError' => __('Server error (500). Check if the post was created anyway.', 'aiseo'),
                ),
            ));
        }
        if ($tab === 'advanced') {
            wp_enqueue_script(
                'aiseo-advanced',
                AISEO_PLUGIN_URL . 'admin/js/aiseo-advanced.js',
                array('jquery', 'aiseo-admin'),
                AISEO_VERSION,
                true
            );
            wp_localize_script('aiseo-advanced', 'aiseoAdvanced', array(
                'i18n' => array(
                    'saving' => __('Saving...', 'aiseo'),
                    'saved' => __('Settings saved successfully!', 'aiseo'),
                    'error' => __('Error', 'aiseo'),
                    'saveSettings' => __('Save Post Type Settings', 'aiseo'),
                    'enterKeyword' => __('Please enter a keyword', 'aiseo'),
                    'researching' => __('Researching...', 'aiseo'),
                    'suggestionsFor' => __('Keyword Suggestions for:', 'aiseo'),
                    'researchKeyword' => __('Research Keyword', 'aiseo'),
                    'generating' => __('Generating...', 'aiseo'),
                    'reportTitle' => __('SEO Report Generated', 'aiseo'),
                    'metric' => __('Metric', 'aiseo'),
                    'value' => __('Value', 'aiseo'),
                    'postsAnalyzed' => __('Posts Analyzed', 'aiseo'),
                    'metadataGenerated' => __('Metadata Generated', 'aiseo'),
                    'aiPosts' => __('AI Posts Created', 'aiseo'),
                    'apiRequests' => __('API Requests', 'aiseo'),
                    'connectionError' => __('Connection error', 'aiseo'),
                    'generateReport' => __('Generate Full Report', 'aiseo'),
                    'noReport' => __('No report data available', 'aiseo'),
                    'reportHeading' => __('AISEO SEO Report', 'aiseo'),
                    'generated' => __('Generated', 'aiseo'),
                    'recentScores' => __('Recent Post Scores', 'aiseo'),
                    'postTitle' => __('Post Title', 'aiseo'),
                    'seoScore' => __('SEO Score', 'aiseo'),
                    'enterTopic' => __('Please enter a topic', 'aiseo'),
                    'generatingBrief' => __('Generating Brief...', 'aiseo'),
                    'targetKeywords' => __('Target keywords', 'aiseo'),
                    'wordCount' => __('Recommended word count', 'aiseo'),
                    'structure' => __('Content structure', 'aiseo'),
                    'keyTopics' => __('Key topics', 'aiseo'),
                    'seoTips' => __('SEO tips', 'aiseo'),
                    'failedBrief' => __('Failed to generate brief', 'aiseo'),
                    'generateBrief' => __('Generate Content Brief', 'aiseo'),
                    'noBrief' => __('No brief content to create post from', 'aiseo'),
                    'creating' => __('Creating Post...', 'aiseo'),
                    'postCreated' => __('Post created. ', 'aiseo'),
                    'editPost' => __('Edit post', 'aiseo'),
                    'fromBrief' => __('Generated from Content Brief', 'aiseo'),
                    'createFromBrief' => __('Create Post from Brief', 'aiseo'),
                ),
            ));
        }
    }
    
    /**
     * Enqueue Gutenberg block editor assets
     */
    public function enqueue_gutenberg_assets() {
        wp_enqueue_style(
            'aiseo-admin',
            AISEO_PLUGIN_URL . 'admin/css/aiseo-admin.css',
            array(),
            AISEO_VERSION
        );
        // Enqueue the rewrite button script
        wp_enqueue_script(
            'aiseo-gutenberg-rewrite',
            AISEO_PLUGIN_URL . 'admin/js/gutenberg-rewrite-button.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-hooks', 'wp-i18n', 'wp-compose', 'wp-block-editor', 'jquery'),
            AISEO_VERSION,
            true
        );
        
        // Enqueue the SEO improver script
        wp_enqueue_script(
            'aiseo-seo-improver',
            AISEO_PLUGIN_URL . 'admin/js/gutenberg-seo-improver.js',
            array('wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'jquery'),
            AISEO_VERSION,
            true
        );
        
        // Localize scripts with nonce
        wp_localize_script('aiseo-gutenberg-rewrite', 'aiseoGutenbergData', array(
            'nonce' => wp_create_nonce('aiseo_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ));
        
        wp_localize_script('aiseo-seo-improver', 'aiseoSEOImprover', array(
            'nonce' => wp_create_nonce('aiseo_admin_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php')
        ));

        wp_enqueue_script(
            'aiseo-gutenberg-sidebar',
            AISEO_PLUGIN_URL . 'admin/js/gutenberg-seo-sidebar.js',
            array('wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-api-fetch'),
            AISEO_VERSION,
            true
        );
        wp_localize_script('aiseo-gutenberg-sidebar', 'aiseoSidebar', array(
            'canRedirect' => current_user_can('aiseo_manage_redirects'),
        ));
    }
    
    /**
     * Render main admin page
     */
    public function render_admin_page() {
        $this->active_tab = $this->get_active_tab();

        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiseo'));
        }
        
        ?>
        <div class="wrap aiseo-admin-wrap">
            <h1 class="aiseo-admin-title">
                <span class="dashicons dashicons-chart-line"></span>
                <?php echo esc_html__('Praison AI SEO', 'aiseo'); ?>
                <span class="aiseo-version">v<?php echo esc_html(AISEO_VERSION); ?></span>
            </h1>
            
            <?php
            if ($this->active_tab === 'ai-content') {
                $this->render_api_status_banner();
            }
            ?>
            
            <nav class="nav-tab-wrapper aiseo-tab-wrapper">
                <?php $this->render_tabs(); ?>
            </nav>
            
            <div class="aiseo-tab-content<?php echo esc_attr(in_array($this->active_tab, array('dashboard', 'settings'), true) ? ' aiseo-tab-content-dashboard' : ''); ?>">
                <?php $this->render_active_tab_content(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render API status banner
     */
    private function render_api_status_banner() {
        $api_key = AISEO_Helpers::get_api_key();
        
        if (empty($api_key)) {
            ?>
            <div class="notice notice-warning aiseo-api-warning">
                <p>
                    <strong><?php esc_html_e('OpenAI API key is optional', 'aiseo'); ?></strong>
                    <?php esc_html_e('Titles, sitemaps, schema and analysis work without it. Add a key only if you want AI writing.', 'aiseo'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aiseo&tab=settings')); ?>" class="button button-primary button-small">
                        <?php esc_html_e('Configure Now', 'aiseo'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Render navigation tabs
     */
    private function render_tabs() {
        foreach ($this->tabs as $tab_key => $tab) {
            // Check capability
            if (!current_user_can($tab['capability'])) {
                continue;
            }
            
            $active_class = ($tab_key === $this->active_tab) ? 'nav-tab-active' : '';
            $url = add_query_arg(array('page' => 'aiseo', 'tab' => $tab_key), admin_url('admin.php'));
            
            printf(
                '<a href="%s" class="nav-tab %s"><span class="dashicons %s" aria-hidden="true"></span> %s</a>',
                esc_url($url),
                esc_attr($active_class),
                esc_attr($tab['icon']),
                esc_html($tab['title'])
            );
        }
    }
    
    /**
     * Render active tab content
     */
    private function render_active_tab_content() {
        $tab = $this->tabs[$this->active_tab];
        
        // Check capability
        if (!current_user_can($tab['capability'])) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'aiseo'));
        }
        
        // Call tab callback
        if (is_callable($tab['callback'])) {
            call_user_func($tab['callback']);
        }
    }
    
    /**
     * Render Dashboard tab
     */
    public function render_dashboard_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Render AI Content tab
     */
    public function render_ai_content_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/ai-content.php';
    }
    
    /**
     * Render SEO Tools tab
     */
    public function render_seo_tools_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/seo-tools.php';
    }
    
    /**
     * Render Bulk Operations tab
     */
    public function render_bulk_operations_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/bulk-operations.php';
    }
    
    /**
     * Render Monitoring tab
     */
    public function render_monitoring_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/monitoring.php';
    }
    
    /**
     * Render Technical SEO tab
     */
    public function render_technical_seo_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/technical-seo.php';
    }
    
    /**
     * Render Advanced tab
     */
    public function render_advanced_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/advanced.php';
    }
    
    /**
     * Render Settings tab
     */
    public function render_settings_tab() {
        require_once AISEO_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request() {
        // Verify nonce
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'aiseo')));
        }
        
        $action = isset($_POST['action_type']) ? sanitize_key($_POST['action_type']) : '';
        
        // Route to appropriate handler
        switch ($action) {
            case 'generate_post':
                $this->ajax_generate_post();
                break;
            case 'generate_meta':
                $this->ajax_generate_meta();
                break;
            case 'analyze_content':
                $this->ajax_analyze_content();
                break;
            case 'get_stats':
                $this->ajax_get_stats();
                break;
            default:
                wp_send_json_error(array('message' => __('Invalid action', 'aiseo')));
        }
    }
    
    /**
     * AJAX: Generate post
     */
    private function ajax_generate_post() {
        $topic = isset($_POST['topic']) ? sanitize_textarea_field($_POST['topic']) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $length = isset($_POST['length']) ? sanitize_key($_POST['length']) : 'medium';
        
        if (empty($topic)) {
            wp_send_json_error(array('message' => __('Topic is required', 'aiseo')));
        }
        
        $creator = new AISEO_Post_Creator();
        $result = $creator->create_post(array(
            'topic' => $topic,
            'keyword' => $keyword,
            'content_length' => $length,
            'post_status' => 'draft',
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Generate meta
     */
    private function ajax_generate_meta() {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $meta_type = isset($_POST['meta_type']) ? sanitize_key($_POST['meta_type']) : 'title';
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID is required', 'aiseo')));
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found', 'aiseo')));
        }
        
        $api = new AISEO_API();
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        if ($meta_type === 'title') {
            $result = $api->generate_title($post->post_content, $keyword);
        } else {
            $result = $api->generate_meta_description($post->post_content, $keyword);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('content' => $result));
    }
    
    /**
     * AJAX: Analyze content
     */
    private function ajax_analyze_content() {
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID is required', 'aiseo')));
        }
        
        if (!class_exists('AISEO_Analysis')) {
            wp_send_json_error(array('message' => __('Analysis class not found', 'aiseo')));
        }
        
        $analysis = new AISEO_Analysis();
        $post = get_post($post_id);
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        $results = array(
            'keyword_density' => $analysis->analyze_keyword_density($post->post_content, $keyword),
            'readability' => $analysis->analyze_readability($post->post_content),
            'content_length' => $analysis->analyze_content_length($post->post_content),
        );
        
        $overall_score = $analysis->generate_seo_score($results);
        $results['overall_score'] = $overall_score;
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Get statistics
     */
    private function ajax_get_stats() {
        $stat_type = isset($_POST['stat_type']) ? sanitize_key($_POST['stat_type']) : 'overview';
        
        $stats = array();
        
        switch ($stat_type) {
            case 'posts':
                if (class_exists('AISEO_Post_Creator')) {
                    $creator = new AISEO_Post_Creator();
                    $stats = $creator->get_statistics();
                }
                break;
            case 'api':
                $stats = array(
                    'total_requests' => get_option('aiseo_total_api_requests', 0),
                    'tokens_used' => get_option('aiseo_token_usage_total', 0),
                    'monthly_tokens' => get_option('aiseo_token_usage_month', 0),
                );
                break;
            default:
                $stats = array(
                    'posts_analyzed' => get_option('aiseo_posts_analyzed_count', 0),
                    'metadata_generated' => get_option('aiseo_metadata_generated_count', 0),
                    'ai_posts_created' => get_option('aiseo_ai_posts_created_count', 0),
                );
        }
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Create post from AI Content tab
     */
    public function ajax_create_post() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $topic = isset($_POST['topic']) ? sanitize_textarea_field($_POST['topic']) : '';
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : 'post';
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
            return;
        }
        
        // If content is provided (from FAQ/Outline/Rewrite), use it directly
        if (!empty($content)) {
            // Convert HTML to Gutenberg blocks format
            $block_content = $this->convert_html_to_blocks($content);
            
            // Create post with the provided content
            $post_type_object = get_post_type_object($post_type);
            if (!$post_type_object || empty($post_type_object->cap->create_posts) || !current_user_can($post_type_object->cap->create_posts)) {
                wp_send_json_error('Permission denied');
                return;
            }

            $post_data = array(
                'post_title' => $topic,
                'post_content' => $block_content,
                'post_status' => 'draft',
                'post_type' => $post_type
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                wp_send_json_error($post_id->get_error_message());
                return;
            }
            
            $result = array(
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'message' => 'Post created successfully from generated content'
            );
            
            wp_send_json_success($result);
            return;
        }
        
        // Fallback: Generate new content using AI (original behavior)
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $length = isset($_POST['length']) ? sanitize_key($_POST['length']) : 'medium';
        
        $creator = new AISEO_Post_Creator();
        $result = $creator->create_post(array(
            'topic' => $topic,
            'keyword' => $keyword,
            'content_length' => $length,
            'post_status' => 'draft',
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Generate title for SEO Tools tab
     */
    public function ajax_generate_title() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Post ID is required');
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
            return;
        }
        
        try {
            $api = new AISEO_API();
            $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
            $title = $api->generate_title($post->post_content, $keyword);
            
            if (is_wp_error($title)) {
                wp_send_json_error($title->get_error_message());
                return;
            }
            
            wp_send_json_success($title);
        } catch (Exception $e) {
            // Fallback: Generate a simple optimized title
            $title = get_the_title($post_id);
            if (empty($title)) {
                $title = 'Optimized Title for ' . ucwords(str_replace('-', ' ', $post->post_name));
            }
            wp_send_json_success($title . ' | SEO Optimized');
        }
    }
    
    /**
     * AJAX: Generate description for SEO Tools tab
     */
    public function ajax_generate_description() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Post ID is required');
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
            return;
        }
        
        try {
            $api = new AISEO_API();
            $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
            $description = $api->generate_meta_description($post->post_content, $keyword);
            
            if (is_wp_error($description)) {
                wp_send_json_error($description->get_error_message());
                return;
            }
            
            wp_send_json_success($description);
        } catch (Exception $e) {
            // Fallback: Generate a simple description
            $content = wp_strip_all_tags($post->post_content);
            $description = wp_trim_words($content, 20, '...');
            wp_send_json_success($description);
        }
    }
    
    /**
     * AJAX: Generate keyword for SEO Tools tab
     */
    public function ajax_generate_keyword() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Post ID is required');
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            wp_send_json_error('Post content is required');
        }
        
        $api = new AISEO_API();
        $prompt = "Analyze this content and suggest the single most important SEO keyword or key phrase (2-4 words maximum) that best represents the main topic. Return only the keyword phrase, nothing else.\n\nContent:\n" . wp_strip_all_tags($post->post_content);
        
        $keyword = $api->make_request($prompt, array(
            'max_tokens' => 20,
            'temperature' => 0.3,
        ));
        
        if (is_wp_error($keyword)) {
            wp_send_json_error($keyword->get_error_message());
        }
        
        $keyword = trim(strtolower($keyword));
        wp_send_json_success($keyword);
    }
    
    /**
     * AJAX: Analyze content for SEO Tools tab
     */
    public function ajax_analyze_post() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Post ID is required');
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        if (!class_exists('AISEO_Analysis')) {
            wp_send_json_error('Analysis class not found');
            return;
        }
        
        $analysis = new AISEO_Analysis();
        $post = get_post($post_id);
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        $results = array(
            'keyword_density' => $analysis->analyze_keyword_density($post->post_content, $keyword),
            'readability' => $analysis->analyze_readability($post->post_content),
            'content_length' => $analysis->analyze_content_length($post->post_content),
        );
        
        $overall_score = $analysis->generate_seo_score($results);
        $results['overall_score'] = $overall_score;
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Add redirect (Technical SEO)
     */
    public function ajax_add_redirect() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('aiseo_manage_redirects')) {
            wp_send_json_error('Permission denied');
        }
        
        $is_regex = !empty($_POST['is_regex']);
        $from_url = isset($_POST['from_url']) ? wp_unslash($_POST['from_url']) : '';
        $from_url = $is_regex ? sanitize_text_field($from_url) : AISEO_Redirects::normalize_path($from_url);
        $to_url = isset($_POST['to_url']) ? AISEO_Redirects::sanitize_target(wp_unslash($_POST['to_url'])) : '';
        $type = isset($_POST['redirect_type']) ? sanitize_text_field(wp_unslash($_POST['redirect_type'])) : '301';
        
        if ($from_url === '' || ($type !== '410' && $to_url === '')) {
            wp_send_json_error('Both URLs are required');
        }
        
        $manager = new AISEO_Redirects();
        $manager->ensure_tables();
        $created = $manager->create_redirect($from_url, $to_url, $type, array('is_regex' => $is_regex));
        if (is_wp_error($created)) {
            wp_send_json_error($created->get_error_message());
        }
        
        wp_send_json_success(array('message' => 'Redirect added successfully', 'id' => $created));
    }
    
    /**
     * AJAX: List all redirects
     */
    public function ajax_list_redirects() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('aiseo_manage_redirects')) {
            wp_send_json_error('Permission denied');
        }
        
        $manager = new AISEO_Redirects();
        $stored = $manager->get_redirects(array('limit' => 500));
        $redirects = array();
        foreach ($stored['redirects'] as $redirect) {
            $redirects[] = array(
                'id' => (string) $redirect['id'],
                'from_url' => $redirect['source_url'],
                'to_url' => $redirect['target_url'],
                'type' => $redirect['redirect_type'],
                'hits' => (int) $redirect['hits'],
                'is_regex' => !empty($redirect['is_regex']),
            );
        }
        
        wp_send_json_success($redirects);
    }
    
    /**
     * AJAX: Delete a redirect
     */
    public function ajax_delete_redirect() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('aiseo_manage_redirects')) {
            wp_send_json_error('Permission denied');
        }
        
        $redirect_id = isset($_POST['redirect_id']) ? absint($_POST['redirect_id']) : 0;
        
        if (!$redirect_id) {
            wp_send_json_error('Redirect ID is required');
        }
        
        $manager = new AISEO_Redirects();
        if (!$manager->delete_redirect($redirect_id)) {
            wp_send_json_error('Redirect not found');
        }
        
        wp_send_json_success(array('message' => 'Redirect deleted successfully'));
    }

    /**
     * Download stored redirects as CSV.
     */
    public function handle_export_redirects() {
        check_admin_referer('aiseo_export_redirects');
        if (!current_user_can('aiseo_manage_redirects')) {
            wp_die(esc_html__('Permission denied', 'aiseo'), '', array('response' => 403));
        }
        $csv = (new AISEO_Redirects())->export_redirects();
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="aiseo-redirects.csv"');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download.
        exit;
    }

    /**
     * Import redirects from an uploaded CSV file.
     */
    public function handle_import_redirects() {
        check_admin_referer('aiseo_import_redirects');
        if (!current_user_can('aiseo_manage_redirects')) {
            wp_die(esc_html__('Permission denied', 'aiseo'), '', array('response' => 403));
        }
        $redirect = admin_url('admin.php?page=aiseo&tab=technical-seo');
        if (empty($_FILES['aiseo_redirects_csv']['tmp_name']) || !is_uploaded_file($_FILES['aiseo_redirects_csv']['tmp_name'])) {
            wp_safe_redirect(add_query_arg('aiseo_redirects', 'missing', $redirect));
            exit;
        }
        $size = isset($_FILES['aiseo_redirects_csv']['size']) ? (int) $_FILES['aiseo_redirects_csv']['size'] : 0;
        if ($size < 1 || $size > 512000) {
            wp_safe_redirect(add_query_arg('aiseo_redirects', 'too_large', $redirect));
            exit;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Uploaded tmp file, not a plugin path.
        $raw = file_get_contents($_FILES['aiseo_redirects_csv']['tmp_name']);
        if (!is_string($raw) || $raw === '') {
            wp_safe_redirect(add_query_arg('aiseo_redirects', 'missing', $redirect));
            exit;
        }
        $result = (new AISEO_Redirects())->bulk_import_redirects($raw);
        $imported = isset($result['imported']) ? (int) $result['imported'] : 0;
        wp_safe_redirect(add_query_arg('aiseo_redirects', 'imported', add_query_arg('aiseo_imported', (string) $imported, $redirect)));
        exit;
    }
    
    /**
     * AJAX: Optimize permalinks (Technical SEO)
     */
    public function ajax_optimize_permalinks() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        if (!class_exists('AISEO_Permalink')) {
            wp_send_json_error('Permalink class not found');
        }
        
        $permalink = new AISEO_Permalink();
        $posts = get_posts(array(
            'numberposts' => 20,
            'post_status' => 'publish',
            'post_type' => array('post', 'page'),
        ));
        $optimized = 0;
        
        foreach ($posts as $post) {
            if ($permalink->optimize_post_slug($post->ID)) {
                $optimized++;
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf('%d permalinks optimized', $optimized),
            'count' => $optimized
        ));
    }
    
    /**
     * AJAX: Regenerate sitemap (Technical SEO)
     */
    public function ajax_regenerate_sitemap() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        if (class_exists('AISEO_Sitemap')) {
            $sitemap = new AISEO_Sitemap();
            $sitemap->clear_cache();
        }
        
        wp_send_json_success('Sitemap cache cleared');
    }
    
    /**
     * Alt text for an image URL. Uses the image SEO generator for media-library files.
     *
     * @param string $image_url Image URL.
     * @return string
     */
    private function alt_text_for_image($image_url) {
        $attachment_id = attachment_url_to_postid($image_url);
        if ($attachment_id && class_exists('AISEO_Image_SEO') && current_user_can('edit_post', $attachment_id)) {
            $generator = new AISEO_Image_SEO();
            $generated = $generator->generate_alt_text($attachment_id, array('overwrite' => false));
            if (!is_wp_error($generated) && is_string($generated) && $generated !== '') {
                return $generated;
            }
        }

        $name = basename((string) wp_parse_url($image_url, PHP_URL_PATH));
        $name = preg_replace('/\.[^.]+$/', '', $name);
        $name = str_replace(array('-', '_'), ' ', (string) $name);
        $name = trim($name);

        return $name !== '' ? ucwords($name) : __('Image', 'aiseo');
    }

    /**
     * AJAX: Generate image alt text (Technical SEO)
     */
    public function ajax_generate_image_alt() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get all posts with images
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $results = array();
        $success_count = 0;
        $failed_count = 0;
        
        foreach ($posts as $post) {
            if (!current_user_can('edit_post', $post->ID)) {
                continue;
            }

            // Find images in post content
            preg_match_all('/<img[^>]+>/i', $post->post_content, $matches);
            
            if (!empty($matches[0])) {
                foreach ($matches[0] as $img_tag) {
                    // Check if alt attribute is missing or empty
                    if (!preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match) || empty($alt_match[1])) {
                        // Extract src
                        if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                            $image_url = $src_match[1];
                            
                            $alt_text = $this->alt_text_for_image($image_url);
                            
                            // Update the image tag with alt text
                            $new_img_tag = preg_replace('/<img/', '<img alt="' . esc_attr($alt_text) . '"', $img_tag, 1);
                            $new_content = str_replace($img_tag, $new_img_tag, $post->post_content);
                            
                            // Update post
                            $updated = wp_update_post(array(
                                'ID' => $post->ID,
                                'post_content' => $new_content
                            ));

                            $saved = !is_wp_error($updated) && $updated > 0;
                            if ($saved) {
                                $post->post_content = $new_content;
                                $success_count++;
                            } else {
                                $failed_count++;
                            }
                            
                            $results[] = array(
                                'post_title' => $post->post_title,
                                'post_id' => $post->ID,
                                'url' => $image_url,
                                'alt_text' => $alt_text,
                                'success' => $saved,
                                'message' => $saved ? 'Updated' : 'Failed'
                            );
                        }
                    }
                }
            }
        }
        
        wp_send_json_success(array(
            'images' => $results,
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'message' => sprintf('%d images updated, %d failed', $success_count, $failed_count)
        ));
    }
    
    /**
     * AJAX: Save CPT settings (Advanced)
     */
    public function ajax_save_cpt_settings() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $cpt_settings = isset($_POST['aiseo_cpt']) ? array_map('sanitize_text_field', wp_unslash($_POST['aiseo_cpt'])) : array();
        update_option('aiseo_enabled_post_types', $cpt_settings);
        
        wp_send_json_success('Custom post type settings saved');
    }
    
    /**
     * AJAX: Generate report (Advanced)
     */
    public function ajax_generate_report() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get actual statistics from database
        $posts_with_meta_title = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_aiseo_meta_title',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        ));
        
        $posts_with_meta_desc = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_aiseo_meta_description',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        ));
        
        $posts_with_keyword = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_aiseo_focus_keyword',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        ));
        
        $all_posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $report = array(
            'generated_at' => current_time('mysql'),
            'posts_analyzed' => count($posts_with_keyword),
            'metadata_generated' => count($posts_with_meta_title) + count($posts_with_meta_desc),
            'ai_posts_created' => get_option('aiseo_ai_posts_created_count', 0),
            'api_requests' => get_option('aiseo_total_api_requests', 0),
            'total_posts' => count($all_posts),
            'posts_with_seo' => count(array_unique(array_merge($posts_with_meta_title, $posts_with_meta_desc, $posts_with_keyword)))
        );
        
        // Get recent posts with SEO scores
        if (class_exists('AISEO_Analysis')) {
            $analysis = new AISEO_Analysis();
            $posts = get_posts(array('numberposts' => 10));
            $scores = array();
            
            foreach ($posts as $post) {
                $keyword = get_post_meta($post->ID, '_aiseo_focus_keyword', true);
                $results = array(
                    'keyword_density' => $analysis->analyze_keyword_density($post->post_content, $keyword),
                    'readability' => $analysis->analyze_readability($post->post_content),
                    'content_length' => $analysis->analyze_content_length($post->post_content),
                );
                $scores[] = array(
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'score' => $analysis->generate_seo_score($results)
                );
            }
            $report['recent_scores'] = $scores;
        }
        
        wp_send_json_success($report);
    }
    
    /**
     * AJAX: Keyword research (Advanced)
     */
    public function ajax_keyword_research() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        if (empty($keyword)) {
            wp_send_json_error('Keyword is required');
        }
        
        $api = new AISEO_API();
        $prompt = "Provide 10 related keyword suggestions for: '{$keyword}'. Return only a comma-separated list of keywords, nothing else.";
        
        $result = $api->make_request($prompt, array(
            'max_tokens' => 100,
            'temperature' => 0.7,
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $keywords = array_map('trim', explode(',', $result));
        
        wp_send_json_success(array(
            'keyword' => $keyword,
            'suggestions' => $keywords
        ));
    }
    
    /**
     * AJAX: Generate content brief (Advanced tab)
     */
    public function ajax_generate_brief() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
            return;
        }
        
        // Use structured output API
        $api = new AISEO_API();
        $brief = $api->generate_content_brief($topic, $keyword);
        
        if (is_wp_error($brief)) {
            wp_send_json_error($brief->get_error_message());
            return;
        }
        
        wp_send_json_success($brief);
    }
    
    /**
     * AJAX: Rewrite content (AI Content tab)
     */
    public function ajax_rewrite_content() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'improve';
        
        if (empty($content)) {
            wp_send_json_error('Content is required');
        }
        
        if (!class_exists('AISEO_Rewriter')) {
            wp_send_json_error('Rewriter class not found');
        }
        
        $rewriter = new AISEO_Rewriter();
        $result = $rewriter->rewrite($content, $mode);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Content suggestions (AI Content tab)
     */
    public function ajax_content_suggestions() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
        }
        
        if (!class_exists('AISEO_Content_Suggestions')) {
            wp_send_json_error('Content Suggestions class not found');
        }
        
        $suggestions = new AISEO_Content_Suggestions();
        $result = $suggestions->get_suggestions($topic, 5);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Generate outline (AI Content tab)
     */
    public function ajax_generate_outline() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        
        if (empty($topic)) {
            wp_send_json_error('Topic is required');
            return;
        }
        
        // Use structured output API
        $api = new AISEO_API();
        $result = $api->generate_outline($topic, $keyword);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        // Wrap in 'outline' key for backward compatibility with frontend
        wp_send_json_success(array('outline' => $result));
    }
    
    /**
     * AJAX: Generate FAQ (AI Content tab)
     */
    public function ajax_generate_faq() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
        $count = isset($_POST['count']) ? absint($_POST['count']) : 5;
        
        if (empty($content)) {
            wp_send_json_error('Content is required');
            return;
        }
        
        // Use structured output API
        $api = new AISEO_API();
        $result = $api->generate_faq($content, $count);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        // Return the faqs array directly
        wp_send_json_success($result['faqs']);
    }
    
    /**
     * AJAX: Internal linking suggestions (SEO Tools tab)
     */
    public function ajax_internal_linking() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error('Post ID is required');
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        $linking = new AISEO_Internal_Linking();
        $suggestions = $linking->get_suggestions($post_id, array('limit' => 5, 'use_cache' => false));
        
        if (is_wp_error($suggestions)) {
            wp_send_json_error($suggestions->get_error_message());
        }
        
        wp_send_json_success($suggestions);
    }
    
    /**
     * AJAX: Meta variations (SEO Tools tab)
     */
    public function ajax_meta_variations() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'title';
        
        if (!$post_id) {
            wp_send_json_error('Post ID is required');
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
            return;
        }

        $variations = new AISEO_Meta_Variations();
        $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);

        if ($type === 'title') {
            wp_send_json_success($variations->title_options($post, $keyword));
        }

        $result = $variations->generate($post->post_content, $keyword, 5);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        $rows = array();
        foreach ($result['variations'] as $item) {
            $text = is_array($item) ? ($item['description'] ?? '') : (string) $item;
            if ($text === '') {
                continue;
            }
            $rows[] = array(
                'text' => $text,
                'length' => strlen($text),
            );
        }
        wp_send_json_success($rows);
    }
    
    /**
     * AJAX: Import SEO data (Bulk Operations tab)
     */
    public function ajax_import_seo() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $source = isset($_POST['source']) ? sanitize_key($_POST['source']) : '';
        
        if (empty($source)) {
            wp_send_json_error('Source is required');
        }
        
        if (!class_exists('AISEO_Import_Export')) {
            wp_send_json_error('Import/Export class not found');
        }
        
        $importer = new AISEO_Import_Export();
        $result = $importer->import_from($source);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Export SEO data (Bulk Operations tab)
     */
    public function ajax_export_seo() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $format = isset($_POST['format']) ? sanitize_key($_POST['format']) : 'json';
        
        if (!class_exists('AISEO_Import_Export')) {
            try {
                // Start with a very small query to test
                $args = array(
                    'post_type' => array('post', 'page'),
                    'posts_per_page' => 100, // Reduced from 1000
                    'post_status' => array('publish', 'draft'),
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'fields' => 'ids' // Only get IDs first to reduce memory
                );
                
                $post_ids = get_posts($args);
                
                if (empty($post_ids)) {
                    wp_send_json_success(array());
                    return;
                }
                
                $export_data = array();
                
                // Process posts one by one
                foreach ($post_ids as $post_id) {
                    $post = get_post($post_id);
                    if (!$post) {
                        continue;
                    }
                    
                    $meta_title = get_post_meta($post_id, '_aiseo_meta_title', true);
                    $meta_desc = get_post_meta($post_id, '_aiseo_meta_description', true);
                    $keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
                    
                    // Only include posts with SEO data
                    if ($meta_title || $meta_desc || $keyword) {
                        $export_data[] = array(
                            'post_id' => $post_id,
                            'post_title' => $post->post_title ? $post->post_title : 'Untitled',
                            'post_type' => $post->post_type,
                            'post_status' => $post->post_status,
                            'meta_title' => $meta_title ? $meta_title : '',
                            'meta_description' => $meta_desc ? $meta_desc : '',
                            'focus_keyword' => $keyword ? $keyword : '',
                            'seo_score' => get_post_meta($post_id, '_aiseo_seo_score', true),
                        );
                    }
                }
                
                // If no posts with SEO data, return empty array
                if (empty($export_data)) {
                    wp_send_json_success(array());
                    return;
                }
                
                wp_send_json_success($export_data);
                return;
            } catch (Exception $e) {
                error_log('AISEO Export Error: ' . $e->getMessage());
                wp_send_json_error('Export failed: ' . $e->getMessage());
                return;
            } catch (Error $e) {
                error_log('AISEO Export Fatal Error: ' . $e->getMessage());
                wp_send_json_error('Export failed: Fatal error occurred');
                return;
            }
        }
        
        $exporter = new AISEO_Import_Export();
        $result = $exporter->export_to($format);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Save approved title
     */
    public function ajax_save_title() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $value = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';
        
        if (!$post_id || empty($value)) {
            wp_send_json_error('Invalid data');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        update_post_meta($post_id, '_aiseo_meta_title', $value);
        delete_transient('aiseo_dashboard_counts');
        delete_transient('aiseo_dashboard_audit');
        
        wp_send_json_success(array('post_id' => $post_id, 'title' => $value));
    }
    
    /**
     * AJAX: Save approved description
     */
    public function ajax_save_description() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $value = isset($_POST['value']) ? sanitize_textarea_field(wp_unslash($_POST['value'])) : '';
        
        if (!$post_id || empty($value)) {
            wp_send_json_error('Invalid data');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        update_post_meta($post_id, '_aiseo_meta_description', $value);
        delete_transient('aiseo_dashboard_counts');
        delete_transient('aiseo_dashboard_audit');
        
        wp_send_json_success(array('post_id' => $post_id, 'description' => $value));
    }
    
    /**
     * AJAX: Find images missing alt text
     */
    public function ajax_find_missing_alt() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get all posts with images
        $posts = get_posts(array(
            'post_type' => 'any',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $images_without_alt = array();
        
        foreach ($posts as $post) {
            if (!current_user_can('edit_post', $post->ID)) {
                continue;
            }
            // Find images in post content
            preg_match_all('/<img[^>]+>/i', $post->post_content, $matches);
            
            if (!empty($matches[0])) {
                foreach ($matches[0] as $img_tag) {
                    // Check if alt attribute is missing or empty
                    if (!preg_match('/alt=["\']([^"\']*)["\']/', $img_tag, $alt_match) || empty($alt_match[1])) {
                        // Extract src
                        if (preg_match('/src=["\']([^"\']+)["\']/', $img_tag, $src_match)) {
                            $images_without_alt[] = array(
                                'url' => $src_match[1],
                                'post_title' => $post->post_title,
                                'post_id' => $post->ID,
                                'edit_url' => get_edit_post_link($post->ID, 'raw')
                            );
                        }
                    }
                }
            }
        }
        
        wp_send_json_success($images_without_alt);
    }
    
    /**
     * AJAX: Generate alt text for single image
     */
    public function ajax_generate_single_alt() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
        
        if (!$post_id || !$image_url) {
            wp_send_json_error('Invalid data');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        // Generate alt text from image filename
        $alt_text = 'AI-generated: ' . basename($image_url, '.' . pathinfo($image_url, PATHINFO_EXTENSION));
        $alt_text = str_replace(array('-', '_'), ' ', $alt_text);
        $alt_text = ucwords($alt_text);
        
        // Find the image tag in post content and add alt attribute
        $content = $post->post_content;
        $image_url_escaped = preg_quote($image_url, '/');
        
        // Match img tag with this src
        $pattern = '/<img([^>]*src=["\']' . $image_url_escaped . '["\'][^>]*)>/i';
        
        if (preg_match($pattern, $content, $matches)) {
            $img_tag = $matches[0];
            
            // Add or update alt attribute
            if (preg_match('/alt=["\'][^"\']*["\']/', $img_tag)) {
                // Update existing alt
                $new_img_tag = preg_replace('/alt=["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img_tag);
            } else {
                // Add new alt attribute
                $new_img_tag = preg_replace('/<img/', '<img alt="' . esc_attr($alt_text) . '"', $img_tag);
            }
            
            // Replace in content
            $new_content = str_replace($img_tag, $new_img_tag, $content);
            
            // Update post
            $updated = wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $new_content
            ));
            
            if (is_wp_error($updated) || !$updated) {
                wp_send_json_error('Failed to update post');
            }
            
            wp_send_json_success(array(
                'alt_text' => $alt_text,
                'post_id' => $post_id
            ));
        } else {
            wp_send_json_error('Image not found in post content');
        }
    }
    
    /**
     * Convert HTML content to Gutenberg blocks format using WordPress serialize_blocks
     * 
     * @param string $html HTML content
     * @return string Gutenberg blocks formatted content
     */
    private function convert_html_to_blocks($html) {
        if (empty($html)) {
            return '';
        }
        
        // Remove any wrapper divs with inline styles (enhanced content wrappers)
        $html = preg_replace('/<div[^>]*style="background[^"]*"[^>]*>(.*?)<\/div>/is', '$1', $html);
        
        // Check if this is FAQ content and handle specially
        if (strpos($html, 'class="aiseo-faq"') !== false || strpos($html, 'class="faq-item"') !== false) {
            return $this->convert_faq_to_blocks($html);
        }
        
        // Parse HTML into block array
        $block_array = array();
        
        // Split by paragraphs and headings
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            // Fallback: simple paragraph wrapping
            return $this->simple_html_to_blocks($html);
        }
        
        foreach ($body->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            
            $tagName = strtolower($node->nodeName);
            $content = $dom->saveHTML($node);
            
            switch ($tagName) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $level = (int)substr($tagName, 1);
                    $text = $node->textContent;
                    $block_array[] = array(
                        'blockName' => 'core/heading',
                        'attrs' => array('level' => $level),
                        'innerBlocks' => array(),
                        'innerHTML' => "<$tagName>$text</$tagName>",
                        'innerContent' => array("<$tagName>$text</$tagName>")
                    );
                    break;
                    
                case 'p':
                    $block_array[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => $content,
                        'innerContent' => array($content)
                    );
                    break;
                    
                case 'ul':
                case 'ol':
                    $block_array[] = array(
                        'blockName' => 'core/list',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => $content,
                        'innerContent' => array($content)
                    );
                    break;
                    
                case 'blockquote':
                    $block_array[] = array(
                        'blockName' => 'core/quote',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => $content,
                        'innerContent' => array($content)
                    );
                    break;
                    
                default:
                    // Wrap unknown elements in paragraph block
                    $block_array[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => $content,
                        'innerContent' => array($content)
                    );
                    break;
            }
        }
        
        // Use WordPress serialize_blocks function for proper block format
        return serialize_blocks($block_array);
    }
    
    /**
     * Convert FAQ HTML to Gutenberg blocks
     * 
     * @param string $html FAQ HTML content
     * @return string Gutenberg blocks formatted content
     */
    private function convert_faq_to_blocks($html) {
        $block_array = array();
        
        // Parse FAQ HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Find all faq-item divs
        $xpath = new DOMXPath($dom);
        $faq_items = $xpath->query("//div[contains(@class, 'faq-item')]");
        
        if ($faq_items->length > 0) {
            foreach ($faq_items as $item) {
                // Get question (h3)
                $h3_nodes = $item->getElementsByTagName('h3');
                if ($h3_nodes->length > 0) {
                    $question = $h3_nodes->item(0)->textContent;
                    $block_array[] = array(
                        'blockName' => 'core/heading',
                        'attrs' => array('level' => 3),
                        'innerBlocks' => array(),
                        'innerHTML' => "<h3>$question</h3>",
                        'innerContent' => array("<h3>$question</h3>")
                    );
                }
                
                // Get answer (p)
                $p_nodes = $item->getElementsByTagName('p');
                if ($p_nodes->length > 0) {
                    $answer = $p_nodes->item(0)->textContent;
                    $block_array[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<p>$answer</p>",
                        'innerContent' => array("<p>$answer</p>")
                    );
                }
            }
        } else {
            // Fallback: look for Q: and A: patterns
            $lines = explode("\n", wp_strip_all_tags($html));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (preg_match('/^Q:\s*(.+)$/i', $line, $matches)) {
                    $block_array[] = array(
                        'blockName' => 'core/heading',
                        'attrs' => array('level' => 3),
                        'innerBlocks' => array(),
                        'innerHTML' => "<h3>" . $matches[1] . "</h3>",
                        'innerContent' => array("<h3>" . $matches[1] . "</h3>")
                    );
                } elseif (preg_match('/^A:\s*(.+)$/i', $line, $matches)) {
                    $block_array[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => "<p>" . $matches[1] . "</p>",
                        'innerContent' => array("<p>" . $matches[1] . "</p>")
                    );
                }
            }
        }
        
        return serialize_blocks($block_array);
    }
    
    /**
     * Simple fallback HTML to blocks converter
     * 
     * @param string $html HTML content
     * @return string Gutenberg blocks formatted content
     */
    private function simple_html_to_blocks($html) {
        // Split by double line breaks
        $paragraphs = preg_split('/\n\n+/', trim($html));
        $block_array = array();
        
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) {
                continue;
            }
            
            // Check if it's a heading
            if (preg_match('/^#+\s+(.+)$/', $para, $matches)) {
                $level = strlen(str_replace(' ', '', $matches[0])) - strlen($matches[1]);
                $text = $matches[1];
                $block_array[] = array(
                    'blockName' => 'core/heading',
                    'attrs' => array('level' => $level),
                    'innerBlocks' => array(),
                    'innerHTML' => "<h$level>$text</h$level>",
                    'innerContent' => array("<h$level>$text</h$level>")
                );
            } else {
                // Regular paragraph
                $block_array[] = array(
                    'blockName' => 'core/paragraph',
                    'attrs' => array(),
                    'innerBlocks' => array(),
                    'innerHTML' => "<p>$para</p>",
                    'innerContent' => array("<p>$para</p>")
                );
            }
        }
        
        return serialize_blocks($block_array);
    }
    
    /**
     * AJAX: Improve SEO Meta (Title & Description)
     */
    public function ajax_improve_seo_meta() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $current_title = isset($_POST['current_title']) ? sanitize_text_field($_POST['current_title']) : '';
        $analysis = isset($_POST['analysis']) ? json_decode(stripslashes($_POST['analysis']), true) : array();
        
        if (!$post_id || !$current_title) {
            wp_send_json_error('Missing required data');
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
            return;
        }
        
        $focus_keyword = get_post_meta($post_id, '_aiseo_focus_keyword', true);
        
        // Build improvement prompt based on analysis
        $improvements = array();
        if (isset($analysis['keyword_in_title']) && $analysis['keyword_in_title'] < 50 && $focus_keyword) {
            $improvements[] = "Include the focus keyword '{$focus_keyword}' in the title";
        }
        
        $prompt = "Improve this SEO title: '{$current_title}'\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Keep it under 60 characters\n";
        $prompt .= "- Make minimal changes\n";
        $prompt .= "- Keep the same meaning\n";
        if (!empty($improvements)) {
            $prompt .= "- " . implode("\n- ", $improvements) . "\n";
        }
        $prompt .= "\nReturn ONLY the improved title, nothing else.";
        
        try {
            $api = new AISEO_API();
            $improved_title = $api->generate_content($prompt, 'short');
            
            if (is_wp_error($improved_title)) {
                wp_send_json_error($improved_title->get_error_message());
                return;
            }
            
            // Clean up the response
            $improved_title = trim(wp_strip_all_tags($improved_title));
            
            wp_send_json_success(array(
                'title' => $improved_title,
                'original' => $current_title
            ));
        } catch (Exception $e) {
            wp_send_json_error('Failed to improve title: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Improve Content Block
     */
    public function ajax_improve_content_block() {
        check_ajax_referer('aiseo_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
        $block_type = isset($_POST['block_type']) ? sanitize_text_field($_POST['block_type']) : '';
        $analysis = isset($_POST['analysis']) ? json_decode(stripslashes($_POST['analysis']), true) : array();
        
        if (!$content) {
            wp_send_json_error('No content provided');
            return;
        }
        
        // Build improvement instructions based on analysis
        $instructions = array();
        
        if (isset($analysis['readability']) && $analysis['readability'] < 50) {
            $instructions[] = "Use shorter sentences and simpler words";
        }
        
        if (isset($analysis['sentence_length']) && $analysis['sentence_length'] < 70) {
            $instructions[] = "Shorten long sentences";
        }
        
        if (isset($analysis['keyword_density']) && $analysis['keyword_density'] < 30) {
            $focus_keyword = isset($analysis['focus_keyword']) ? $analysis['focus_keyword'] : '';
            if ($focus_keyword) {
                $instructions[] = "Naturally include the keyword '{$focus_keyword}' if relevant";
            }
        }
        
        $prompt = "Improve this content for SEO:\n\n{$content}\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Make MINIMAL changes only\n";
        $prompt .= "- Keep the same structure and length\n";
        $prompt .= "- Preserve the original meaning\n";
        $prompt .= "- Keep the same number of sentences\n";
        
        if (!empty($instructions)) {
            $prompt .= "- " . implode("\n- ", $instructions) . "\n";
        }
        
        $prompt .= "\nReturn ONLY the improved content, nothing else. No explanations.";
        
        try {
            $api = new AISEO_API();
            $improved_content = $api->generate_content($prompt, 'medium');
            
            if (is_wp_error($improved_content)) {
                wp_send_json_error($improved_content->get_error_message());
                return;
            }
            
            // Clean up and preserve HTML structure if it was a heading
            $improved_content = trim($improved_content);
            
            if ($block_type === 'core/heading') {
                $improved_content = wp_strip_all_tags($improved_content);
            }
            
            wp_send_json_success($improved_content);
        } catch (Exception $e) {
            wp_send_json_error('Failed to improve content: ' . $e->getMessage());
        }
    }
}
