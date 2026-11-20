<?php
/**
 * AISEO Social Media Tags Handler
 *
 * Generates Open Graph and Twitter Card meta tags for social media sharing
 *
 * @package AISEO
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AISEO_Social {
    
    /**
     * Initialize social media tags handler
     */
    public function init() {
        // Hook into wp_head to inject social media tags
        add_action('wp_head', array($this, 'inject_social_tags'), 5);
    }
    
    /**
     * Inject all social media tags into head
     */
    public function inject_social_tags() {
        if (!is_singular()) {
            $this->output_archive_social_tags();
            return;
        }
        
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Output Open Graph tags
        $this->output_open_graph_tags($post);
        
        // Output Twitter Card tags
        $this->output_twitter_card_tags($post);
    }

    /**
     * Share tags for category, tag, and author archives.
     */
    private function output_archive_social_tags() {
        if ((is_front_page() && !is_singular()) || (is_home() && !is_front_page())) {
            $title = wp_get_document_title();
            if (is_front_page()) {
                $description = get_option('aiseo_home_description', '');
                $url = home_url('/');
            } else {
                $description = get_option('aiseo_blog_description', '');
                $posts_page = (int) get_option('page_for_posts');
                $url = $posts_page ? get_permalink($posts_page) : '';
            }
            if (!is_string($description) || trim(wp_strip_all_tags($description)) === '') {
                $description = get_bloginfo('description');
            }
            $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
            if ($paged > 1) {
                $url = get_pagenum_link($paged);
            }
            $this->output_website_social_tags($title, $description, $url);
            return;
        }

        if (!is_category() && !is_tag() && !is_tax() && !is_author() && !is_post_type_archive()) {
            return;
        }

        $title = wp_get_document_title();
        $description = '';
        $url = '';

        if (is_post_type_archive()) {
            $post_type = get_queried_object();
            if ($post_type instanceof WP_Post_Type) {
                $description = $post_type->description;
                $archive = get_post_type_archive_link($post_type->name);
                $url = is_string($archive) ? $archive : '';
            }
        } elseif (is_author()) {
            $user = get_queried_object();
            if ($user instanceof WP_User) {
                $description = get_user_meta($user->ID, 'description', true);
                $url = get_author_posts_url($user->ID);
            }
        } else {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                $meta = array();
                if (class_exists('AISEO_Taxonomy_SEO')) {
                    $meta = (new AISEO_Taxonomy_SEO())->get_term_meta($term->term_id, $term->taxonomy);
                }
                $description = !empty($meta['description']) ? $meta['description'] : $term->description;
                $term_link = get_term_link($term);
                $url = is_wp_error($term_link) ? '' : $term_link;
            }
        }

        $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
        if ($paged > 1 && $url !== '') {
            $url = get_pagenum_link($paged);
        }
        $this->output_website_social_tags($title, $description, $url);
    }

    /**
     * Open Graph and Twitter tags for a website-type URL.
     *
     * @param string $title       Share title.
     * @param string $description Share description.
     * @param string $url         Canonical URL.
     */
    private function output_website_social_tags($title, $description, $url) {
        $description = is_string($description) ? AISEO_Helpers::truncate_text(wp_strip_all_tags($description), 160) : '';
        $url = esc_url($url);
        if ($title === '' || $url === '') {
            return;
        }

        echo "\n<!-- Archive social tags -->\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
        if ($description !== '') {
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
        $image = $this->get_site_logo_image();
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image['url']) . '">' . "\n";
            if (!empty($image['width'])) {
                echo '<meta property="og:image:width" content="' . esc_attr($image['width']) . '">' . "\n";
            }
            if (!empty($image['height'])) {
                echo '<meta property="og:image:height" content="' . esc_attr($image['height']) . '">' . "\n";
            }
            if (!empty($image['alt'])) {
                echo '<meta property="og:image:alt" content="' . esc_attr($image['alt']) . '">' . "\n";
            }
        }
        echo '<meta name="twitter:card" content="' . esc_attr($image ? 'summary_large_image' : 'summary') . '">' . "\n";
        $twitter_site = get_option('aiseo_twitter_site');
        if (is_string($twitter_site) && preg_match('/^@[A-Za-z0-9_]{1,15}$/', $twitter_site)) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '">' . "\n";
        }
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image['url']) . '">' . "\n";
        }
        if ($description !== '') {
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }
    }
    
    /**
     * Output Open Graph tags
     *
     * @param WP_Post $post Post object
     */
    private function output_open_graph_tags($post) {
        echo "\n<!-- Open Graph Meta Tags -->\n";
        
        // og:type
        $og_type = $this->get_og_type($post);
        echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
        
        // og:title
        $og_title = $this->get_og_title($post);
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";
        
        // og:description
        $og_description = $this->get_og_description($post);
        if (!empty($og_description)) {
            echo '<meta property="og:description" content="' . esc_attr($og_description) . '">' . "\n";
        }
        
        $og_url = class_exists('AISEO_Meta') ? AISEO_Meta::canonical_url($post) : get_permalink($post->ID);
        echo '<meta property="og:url" content="' . esc_url($og_url) . '">' . "\n";
        
        // og:site_name
        $site_name = get_bloginfo('name');
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
        
        // og:image
        $og_image = $this->get_og_image($post);
        if ($og_image) {
            echo '<meta property="og:image" content="' . esc_url($og_image['url']) . '">' . "\n";
            
            if (isset($og_image['width'])) {
                echo '<meta property="og:image:width" content="' . esc_attr($og_image['width']) . '">' . "\n";
            }
            
            if (isset($og_image['height'])) {
                echo '<meta property="og:image:height" content="' . esc_attr($og_image['height']) . '">' . "\n";
            }
            
            if (isset($og_image['alt'])) {
                echo '<meta property="og:image:alt" content="' . esc_attr($og_image['alt']) . '">' . "\n";
            }
        }
        
        // og:locale
        $locale = get_locale();
        echo '<meta property="og:locale" content="' . esc_attr($locale) . '">' . "\n";
        
        // Article-specific tags
        if ($og_type === 'article') {
            // article:published_time
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c', $post->ID)) . '">' . "\n";
            
            // article:modified_time
            echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c', $post->ID)) . '">' . "\n";
            
            // article:author
            $author_id = $post->post_author;
            $author = get_userdata($author_id);
            if ($author) {
                echo '<meta property="article:author" content="' . esc_attr($author->display_name) . '">' . "\n";
            }
            
            $category = class_exists('AISEO_Breadcrumbs') ? AISEO_Breadcrumbs::primary_category($post->ID) : null;
            if ($category) {
                echo '<meta property="article:section" content="' . esc_attr($category->name) . '">' . "\n";
            }
            
            // article:tag (tags)
            $tags = get_the_tags($post->ID);
            if ($tags) {
                foreach (array_slice($tags, 0, 5) as $tag) {
                    echo '<meta property="article:tag" content="' . esc_attr($tag->name) . '">' . "\n";
                }
            }
        }

        if ($og_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post->ID);
            if ($product) {
                $price = $product->get_price();
                if ($price !== '' && $price !== null && is_numeric($price)) {
                    echo '<meta property="product:price:amount" content="' . esc_attr((string) $price) . '">' . "\n";
                }
                $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
                if (is_string($currency) && $currency !== '') {
                    echo '<meta property="product:price:currency" content="' . esc_attr($currency) . '">' . "\n";
                }
                $availability = $product->is_in_stock() ? 'instock' : 'oos';
                echo '<meta property="product:availability" content="' . esc_attr($availability) . '">' . "\n";
                $sku = $product->get_sku();
                if (is_string($sku) && $sku !== '') {
                    echo '<meta property="product:retailer_item_id" content="' . esc_attr($sku) . '">' . "\n";
                }
            }
        }
    }
    
    /**
     * Output Twitter Card tags
     *
     * @param WP_Post $post Post object
     */
    private function output_twitter_card_tags($post) {
        echo "\n<!-- Twitter Card Meta Tags -->\n";
        
        // twitter:card
        $card_type = $this->get_twitter_card_type($post);
        echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '">' . "\n";
        
        // twitter:title
        $twitter_title = $this->get_twitter_title($post);
        echo '<meta name="twitter:title" content="' . esc_attr($twitter_title) . '">' . "\n";
        
        // twitter:description
        $twitter_description = $this->get_twitter_description($post);
        if (!empty($twitter_description)) {
            echo '<meta name="twitter:description" content="' . esc_attr($twitter_description) . '">' . "\n";
        }
        
        // twitter:image
        $twitter_image = $this->get_twitter_image($post);
        if ($twitter_image) {
            echo '<meta name="twitter:image" content="' . esc_url($twitter_image['url']) . '">' . "\n";
            
            if (isset($twitter_image['alt'])) {
                echo '<meta name="twitter:image:alt" content="' . esc_attr($twitter_image['alt']) . '">' . "\n";
            }
        }
        
        // twitter:site (optional - site's Twitter handle)
        $twitter_site = get_option('aiseo_twitter_site');
        if (!empty($twitter_site)) {
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '">' . "\n";
        }
        
        // twitter:creator (author's Twitter handle)
        $author_id = $post->post_author;
        $twitter_creator = get_user_meta($author_id, 'twitter', true);
        if (!empty($twitter_creator)) {
            // Ensure it starts with @
            if (strpos($twitter_creator, '@') !== 0) {
                $twitter_creator = '@' . $twitter_creator;
            }
            echo '<meta name="twitter:creator" content="' . esc_attr($twitter_creator) . '">' . "\n";
        }
    }
    
    /**
     * Get Open Graph type
     *
     * @param WP_Post $post Post object
     * @return string OG type
     */
    private function get_og_type($post) {
        // Check for custom OG type
        $custom_type = get_post_meta($post->ID, '_aiseo_og_type', true);
        
        if (!empty($custom_type)) {
            return $custom_type;
        }
        
        // Default based on post type
        if ($post->post_type === 'page') {
            return 'website';
        }

        if ($post->post_type === 'product') {
            return 'product';
        }
        
        return 'article';
    }
    
    /**
     * Get Open Graph title
     *
     * @param WP_Post $post Post object
     * @return string OG title
     */
    private function get_og_title($post) {
        // Check for custom OG title
        $custom_title = get_post_meta($post->ID, '_aiseo_og_title', true);
        
        if (!empty($custom_title)) {
            return $custom_title;
        }
        
        // Use SEO title if available
        $seo_title = get_post_meta($post->ID, '_aiseo_meta_title', true);
        
        if (!empty($seo_title)) {
            return $seo_title;
        }

        if (class_exists('AISEO_Title_Templates')) {
            $formatted = (new AISEO_Title_Templates())->format_post_title($post);
            if ($formatted !== '') {
                return $formatted;
            }
        }
        
        return $post->post_title;
    }
    
    /**
     * Get Open Graph description
     *
     * @param WP_Post $post Post object
     * @return string OG description
     */
    private function get_og_description($post) {
        // Check for custom OG description
        $custom_description = get_post_meta($post->ID, '_aiseo_og_description', true);
        
        if (!empty($custom_description)) {
            return $custom_description;
        }
        
        // Use SEO description if available
        $seo_description = get_post_meta($post->ID, '_aiseo_meta_description', true);
        
        if (!empty($seo_description)) {
            return $seo_description;
        }

        if (class_exists('AISEO_Title_Templates')) {
            $formatted = (new AISEO_Title_Templates())->format_post_description($post);
            if ($formatted !== '') {
                return $formatted;
            }
        }
        
        // Fallback to excerpt or content
        if (!empty($post->post_excerpt)) {
            return AISEO_Helpers::truncate_text($post->post_excerpt, 200);
        }
        
        return AISEO_Helpers::truncate_text($post->post_content, 200);
    }
    
    /**
     * Get Open Graph image
     *
     * @param WP_Post $post Post object
     * @return array|false Image data or false
     */
    private function get_og_image($post) {
        // Check for custom OG image
        $custom_image_id = get_post_meta($post->ID, '_aiseo_og_image', true);
        
        if ($custom_image_id) {
            return $this->get_image_data($custom_image_id);
        }
        
        // Use featured image
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        
        if ($thumbnail_id) {
            return $this->get_image_data($thumbnail_id);
        }
        
        // Fallback to first image in content
        $first_image = $this->get_first_content_image($post->post_content);
        
        if ($first_image) {
            return $first_image;
        }
        
        // Fallback to default site image
        $default_image_id = get_option('aiseo_default_og_image');
        
        if ($default_image_id) {
            return $this->get_image_data($default_image_id);
        }

        return $this->get_site_logo_image();
    }

    /**
     * Site logo, or the default social image, for pages without their own image.
     *
     * @return array|false
     */
    private function get_site_logo_image() {
        $logo_id = (int) get_theme_mod('custom_logo');
        if (!$logo_id) {
            $logo_id = (int) get_option('aiseo_default_og_image');
        }
        if (!$logo_id) {
            return false;
        }
        return $this->get_image_data($logo_id);
    }
    
    /**
     * Get Twitter Card type
     *
     * @param WP_Post $post Post object
     * @return string Card type
     */
    private function get_twitter_card_type($post) {
        // Check for custom card type
        $custom_type = get_post_meta($post->ID, '_aiseo_twitter_card_type', true);
        
        if (!empty($custom_type)) {
            return $custom_type;
        }

        $site_default = get_option('aiseo_twitter_card_default', '');
        if (in_array($site_default, array('summary', 'summary_large_image'), true)) {
            return $site_default;
        }
        
        // Default to summary_large_image if there's a featured image
        if (has_post_thumbnail($post->ID)) {
            return 'summary_large_image';
        }
        
        return 'summary';
    }
    
    /**
     * Get Twitter title
     *
     * @param WP_Post $post Post object
     * @return string Twitter title
     */
    private function get_twitter_title($post) {
        // Check for custom Twitter title
        $custom_title = get_post_meta($post->ID, '_aiseo_twitter_title', true);
        
        if (!empty($custom_title)) {
            return $custom_title;
        }
        
        // Use OG title
        return $this->get_og_title($post);
    }
    
    /**
     * Get Twitter description
     *
     * @param WP_Post $post Post object
     * @return string Twitter description
     */
    private function get_twitter_description($post) {
        // Check for custom Twitter description
        $custom_description = get_post_meta($post->ID, '_aiseo_twitter_description', true);
        
        if (!empty($custom_description)) {
            return $custom_description;
        }
        
        // Use OG description
        return $this->get_og_description($post);
    }
    
    /**
     * Get Twitter image
     *
     * @param WP_Post $post Post object
     * @return array|false Image data or false
     */
    private function get_twitter_image($post) {
        // Check for custom Twitter image
        $custom_image_id = get_post_meta($post->ID, '_aiseo_twitter_image', true);
        
        if ($custom_image_id) {
            return $this->get_image_data($custom_image_id);
        }
        
        // Use OG image
        return $this->get_og_image($post);
    }
    
    /**
     * Get image data from attachment ID
     *
     * @param int $attachment_id Attachment ID
     * @return array|false Image data or false
     */
    private function get_image_data($attachment_id) {
        $image = wp_get_attachment_image_src($attachment_id, 'full');
        
        if (!$image) {
            return false;
        }
        
        $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        
        return array(
            'url' => $image[0],
            'width' => $image[1],
            'height' => $image[2],
            'alt' => $alt_text ?: '',
        );
    }
    
    /**
     * Get first image from post content
     *
     * @param string $content Post content
     * @return array|false Image data or false
     */
    private function get_first_content_image($content) {
        // Match first img tag
        preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        if (empty($matches[1])) {
            return false;
        }
        
        // Get alt text if available
        preg_match('/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', $content, $alt_matches);
        $alt_text = !empty($alt_matches[1]) ? $alt_matches[1] : '';
        
        return array(
            'url' => $matches[1],
            'alt' => $alt_text,
        );
    }
    
    /**
     * Get all social media tags for a post (for testing/display)
     *
     * @param int $post_id Post ID
     * @return array Social media tags
     */
    public function get_social_tags($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return array();
        }
        
        $og_image = $this->get_og_image($post);
        $twitter_image = $this->get_twitter_image($post);
        
        return array(
            'open_graph' => array(
                'og:type' => $this->get_og_type($post),
                'og:title' => $this->get_og_title($post),
                'og:description' => $this->get_og_description($post),
                'og:url' => get_permalink($post_id),
                'og:site_name' => get_bloginfo('name'),
                'og:image' => $og_image ? $og_image['url'] : '',
                'og:locale' => get_locale(),
            ),
            'twitter' => array(
                'twitter:card' => $this->get_twitter_card_type($post),
                'twitter:title' => $this->get_twitter_title($post),
                'twitter:description' => $this->get_twitter_description($post),
                'twitter:image' => $twitter_image ? $twitter_image['url'] : '',
                'twitter:site' => get_option('aiseo_twitter_site', ''),
            ),
        );
    }
}
