<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Context Extractor Class
 * 
 * Extracts context information from pages where media files are used
 * to provide better context for AI-powered filename generation.
 * 
 * This is a placeholder implementation that will be expanded in later tasks.
 */
class FMR_Context_Extractor {

    /**
     * Extract context for a media file
     *
     * @param int $post_id Media post ID
     * @return array Context data
     */
    public function extract_context($post_id) {
        // Use performance optimizer if available
        if (class_exists('FMR_Performance_Optimizer')) {
            $performance_optimizer = new FMR_Performance_Optimizer();
            $optimized_results = $performance_optimizer->optimize_context_queries($post_id);
            
            if (!empty($optimized_results['post_ids'])) {
                return $this->process_optimized_context_data($optimized_results);
            }
        }

        // Fallback to original method
        $context = array(
            'page_titles' => array(),
            'seo_keywords' => array(),
            'headings' => array(),
            'categories' => array(),
            'page_builder_content' => array(),
            'posts_using_media' => $this->find_posts_using_media($post_id)
        );

        // Extract basic context from posts using this media
        if (!empty($context['posts_using_media'])) {
            $context = $this->extract_basic_context($context['posts_using_media'], $context);
            
            // Extract page builder content
            $page_builder_data = $this->extract_page_builder_content($context['posts_using_media']);
            if (!empty($page_builder_data)) {
                $context['page_builder_content'] = $page_builder_data;
                
                // Extract additional keywords from page builder content
                foreach ($page_builder_data as $content) {
                    if (!empty($content['text'])) {
                        $pb_keywords = $this->extract_keywords_from_text($content['text']);
                        $context['seo_keywords'] = array_merge($context['seo_keywords'], $pb_keywords);
                    }
                    if (!empty($content['headings'])) {
                        $context['headings'] = array_merge($context['headings'], $content['headings']);
                    }
                }
            }
        }

        // Clean up and deduplicate all arrays
        $context = $this->cleanup_context_data($context);

        return $context;
    }

    /**
     * Process optimized context data from performance optimizer
     *
     * @param array $optimized_results Results from performance optimizer
     * @return array Processed context data
     */
    private function process_optimized_context_data($optimized_results) {
        $context = array(
            'page_titles' => array(),
            'seo_keywords' => array(),
            'headings' => array(),
            'categories' => array(),
            'page_builder_content' => array(),
            'posts_using_media' => $optimized_results['post_ids']
        );

        // Process metadata from optimized results
        if (!empty($optimized_results['metadata'])) {
            foreach ($optimized_results['metadata'] as $post_id => $metadata) {
                // Add page title
                if (!empty($metadata['title'])) {
                    $context['page_titles'][] = $metadata['title'];
                }

                // Process terms (categories/tags)
                if (!empty($metadata['terms'])) {
                    foreach ($metadata['terms'] as $taxonomy => $terms) {
                        if ($taxonomy === 'category' || $taxonomy === 'product_cat') {
                            $context['categories'] = array_merge($context['categories'], $terms);
                        } else {
                            $context['seo_keywords'] = array_merge($context['seo_keywords'], $terms);
                        }
                    }
                }

                // Process SEO meta data
                if (!empty($metadata['meta'])) {
                    $seo_data = $this->extract_seo_keywords_from_meta($metadata['meta']);
                    $context['seo_keywords'] = array_merge($context['seo_keywords'], $seo_data);
                }

                // Extract headings from content
                if (!empty($metadata['content'])) {
                    $content_headings = $this->extract_headings_from_content($metadata['content']);
                    $context['headings'] = array_merge($context['headings'], $content_headings);
                }
            }
        }

        // Clean up and deduplicate
        $context = $this->cleanup_context_data($context);

        return $context;
    }

    /**
     * Extract SEO keywords from meta data
     *
     * @param array $meta_data Meta data array
     * @return array SEO keywords
     */
    private function extract_seo_keywords_from_meta($meta_data) {
        $keywords = array();

        // Focus keywords from various SEO plugins
        $focus_keyword_fields = array(
            '_yoast_wpseo_focuskw',
            'rank_math_focus_keyword',
            '_aioseo_focus_keyphrase',
            '_seopress_analysis_target_kw'
        );

        foreach ($focus_keyword_fields as $field) {
            if (!empty($meta_data[$field])) {
                $keywords[] = $meta_data[$field];
            }
        }

        // Extract keywords from meta descriptions
        $description_fields = array(
            '_yoast_wpseo_metadesc',
            'rank_math_description',
            '_aioseo_description',
            '_seopress_titles_desc'
        );

        foreach ($description_fields as $field) {
            if (!empty($meta_data[$field])) {
                $desc_keywords = $this->extract_keywords_from_text($meta_data[$field]);
                $keywords = array_merge($keywords, $desc_keywords);
            }
        }

        return $keywords;
    }

    /**
     * Extract headings from content
     *
     * @param string $content Post content
     * @return array Headings
     */
    private function extract_headings_from_content($content) {
        $headings = array();

        // Extract H1-H6 tags
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $heading) {
                $clean_heading = wp_strip_all_tags($heading);
                if (!empty($clean_heading) && strlen($clean_heading) > 2) {
                    $headings[] = $clean_heading;
                }
            }
        }

        return $headings;
    }

    /**
     * Find posts that use this media file
     * Uses WordPress database API with caching for performance
     *
     * @param int $post_id Media post ID
     * @return array Array of post IDs
     */
    private function find_posts_using_media($post_id) {
        // Check cache first
        $cache_key = 'fmr_posts_using_media_' . $post_id;
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }

        global $wpdb;

        $file_url = wp_get_attachment_url($post_id);
        if (!$file_url) {
            return array();
        }

        // Get attachment metadata for additional URLs to search
        $attachment_urls = $this->get_attachment_urls($post_id);
        
        $post_ids = array();

        // Search in post content for each URL
        foreach ($attachment_urls as $url) {
            $content_posts = $this->find_posts_by_content($url);
            $post_ids = array_merge($post_ids, $content_posts);
        }

        // Search in post meta for each URL
        foreach ($attachment_urls as $url) {
            $meta_posts = $this->find_posts_by_meta($url);
            $post_ids = array_merge($post_ids, $meta_posts);
        }

        // Search for posts that have this attachment as featured image
        $featured_posts = $this->find_posts_with_featured_image($post_id);
        $post_ids = array_merge($post_ids, $featured_posts);

        // Remove duplicates and convert to integers
        $post_ids = array_unique(array_map('intval', $post_ids));
        
        // Filter out invalid post IDs and ensure posts are published
        $post_ids = $this->filter_valid_posts($post_ids);

        // Cache the result for 1 hour
        set_transient($cache_key, $post_ids, HOUR_IN_SECONDS);

        return $post_ids;
    }

    /**
     * Get all URLs associated with an attachment (including thumbnails)
     *
     * @param int $post_id Media post ID
     * @return array Array of URLs
     */
    private function get_attachment_urls($post_id) {
        $urls = array();
        
        // Main file URL
        $main_url = wp_get_attachment_url($post_id);
        if ($main_url) {
            $urls[] = $main_url;
        }

        // Get all thumbnail sizes
        $metadata = wp_get_attachment_metadata($post_id);
        if (!empty($metadata['sizes'])) {
            $upload_dir = wp_upload_dir();
            $base_url = trailingslashit(dirname($main_url));
            
            foreach ($metadata['sizes'] as $size => $size_data) {
                if (!empty($size_data['file'])) {
                    $urls[] = $base_url . $size_data['file'];
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Find posts containing URL in post_content
     *
     * @param string $url URL to search for
     * @return array Array of post IDs
     */
    private function find_posts_by_content($url) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT DISTINCT ID
            FROM {$wpdb->posts} 
            WHERE post_content LIKE %s
            AND post_status = 'publish'
            AND post_type IN ('post', 'page', 'product')
            LIMIT 100
        ", '%' . $wpdb->esc_like($url) . '%');

        $results = $wpdb->get_col($query);
        
        return $results ? $results : array();
    }

    /**
     * Find posts containing URL in post meta
     *
     * @param string $url URL to search for
     * @return array Array of post IDs
     */
    private function find_posts_by_meta($url) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_value LIKE %s
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_status = 'publish' 
                AND post_type IN ('post', 'page', 'product')
            )
            LIMIT 100
        ", '%' . $wpdb->esc_like($url) . '%');

        $results = $wpdb->get_col($query);
        
        return $results ? $results : array();
    }

    /**
     * Find posts that have this attachment as featured image
     *
     * @param int $post_id Media post ID
     * @return array Array of post IDs
     */
    private function find_posts_with_featured_image($post_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_thumbnail_id' 
            AND meta_value = %d
            AND post_id IN (
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_status = 'publish' 
                AND post_type IN ('post', 'page', 'product')
            )
        ", $post_id);

        $results = $wpdb->get_col($query);
        
        return $results ? $results : array();
    }

    /**
     * Filter and validate post IDs
     *
     * @param array $post_ids Array of post IDs
     * @return array Filtered array of valid post IDs
     */
    private function filter_valid_posts($post_ids) {
        if (empty($post_ids)) {
            return array();
        }

        global $wpdb;

        // Convert to comma-separated string for IN clause
        $post_ids_str = implode(',', array_map('intval', $post_ids));

        $query = "
            SELECT DISTINCT ID
            FROM {$wpdb->posts} 
            WHERE ID IN ({$post_ids_str})
            AND post_status = 'publish'
            AND post_type IN ('post', 'page', 'product')
            ORDER BY post_date DESC
            LIMIT 50
        ";

        $results = $wpdb->get_col($query);
        
        return $results ? array_map('intval', $results) : array();
    }

    /**
     * Clear cache for media relationships
     *
     * @param int $post_id Media post ID
     */
    public function clear_media_cache($post_id) {
        $cache_key = 'fmr_posts_using_media_' . $post_id;
        delete_transient($cache_key);
    }

    /**
     * Extract basic context from posts
     *
     * @param array $post_ids Array of post IDs
     * @param array $context Existing context array
     * @return array Updated context array
     */
    private function extract_basic_context($post_ids, $context) {
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            // Add page title
            $context['page_titles'][] = $post->post_title;

            // Extract categories/tags
            $categories = get_the_category($post_id);
            foreach ($categories as $category) {
                $context['categories'][] = $category->name;
            }

            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $context['seo_keywords'][] = $tag->name;
                }
            }

            // Extract SEO data from plugins
            $seo_data = $this->extract_seo_data(array($post_id));
            if (!empty($seo_data['keywords'])) {
                $context['seo_keywords'] = array_merge($context['seo_keywords'], $seo_data['keywords']);
            }
            if (!empty($seo_data['descriptions'])) {
                $context['meta_descriptions'] = isset($context['meta_descriptions']) 
                    ? array_merge($context['meta_descriptions'], $seo_data['descriptions'])
                    : $seo_data['descriptions'];
            }
            if (!empty($seo_data['focus_keywords'])) {
                $context['focus_keywords'] = isset($context['focus_keywords']) 
                    ? array_merge($context['focus_keywords'], $seo_data['focus_keywords'])
                    : $seo_data['focus_keywords'];
            }
        }

        // Remove duplicates and limit results
        $context['page_titles'] = array_unique($context['page_titles']);
        $context['categories'] = array_unique($context['categories']);
        $context['seo_keywords'] = array_unique($context['seo_keywords']);
        
        if (isset($context['meta_descriptions'])) {
            $context['meta_descriptions'] = array_unique($context['meta_descriptions']);
        }
        if (isset($context['focus_keywords'])) {
            $context['focus_keywords'] = array_unique($context['focus_keywords']);
        }

        return $context;
    }

    /**
     * Extract SEO data from various SEO plugins
     *
     * @param array $post_ids Array of post IDs
     * @return array SEO data
     */
    private function extract_seo_data($post_ids) {
        $seo_data = array(
            'keywords' => array(),
            'descriptions' => array(),
            'focus_keywords' => array()
        );

        foreach ($post_ids as $post_id) {
            // Rank Math integration
            $rank_math_data = $this->extract_rank_math_data($post_id);
            $seo_data = $this->merge_seo_data($seo_data, $rank_math_data);

            // Yoast SEO integration
            $yoast_data = $this->extract_yoast_data($post_id);
            $seo_data = $this->merge_seo_data($seo_data, $yoast_data);

            // All in One SEO integration
            $aioseo_data = $this->extract_aioseo_data($post_id);
            $seo_data = $this->merge_seo_data($seo_data, $aioseo_data);

            // SEOPress integration
            $seopress_data = $this->extract_seopress_data($post_id);
            $seo_data = $this->merge_seo_data($seo_data, $seopress_data);

            // Generic SEO meta extraction
            $generic_data = $this->extract_generic_seo_data($post_id);
            $seo_data = $this->merge_seo_data($seo_data, $generic_data);
        }

        return $seo_data;
    }

    /**
     * Extract SEO data from Rank Math plugin
     *
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function extract_rank_math_data($post_id) {
        $data = array(
            'keywords' => array(),
            'descriptions' => array(),
            'focus_keywords' => array()
        );

        // Check if Rank Math is active
        if (!class_exists('RankMath')) {
            return $data;
        }

        // Get focus keyword
        $focus_keyword = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!empty($focus_keyword)) {
            $data['focus_keywords'][] = $focus_keyword;
            $data['keywords'][] = $focus_keyword;
        }

        // Get meta description
        $meta_description = get_post_meta($post_id, 'rank_math_description', true);
        if (!empty($meta_description)) {
            $data['descriptions'][] = $meta_description;
            // Extract keywords from description
            $desc_keywords = $this->extract_keywords_from_text($meta_description);
            $data['keywords'] = array_merge($data['keywords'], $desc_keywords);
        }

        // Get additional keywords from Rank Math
        $pillar_content = get_post_meta($post_id, 'rank_math_pillar_content', true);
        if (!empty($pillar_content)) {
            $data['keywords'][] = $pillar_content;
        }

        return $data;
    }

    /**
     * Extract SEO data from Yoast SEO plugin
     *
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function extract_yoast_data($post_id) {
        $data = array(
            'keywords' => array(),
            'descriptions' => array(),
            'focus_keywords' => array()
        );

        // Check if Yoast is active
        if (!class_exists('WPSEO_Options')) {
            return $data;
        }

        // Get focus keyword
        $focus_keyword = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!empty($focus_keyword)) {
            $data['focus_keywords'][] = $focus_keyword;
            $data['keywords'][] = $focus_keyword;
        }

        // Get meta description
        $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!empty($meta_description)) {
            $data['descriptions'][] = $meta_description;
            // Extract keywords from description
            $desc_keywords = $this->extract_keywords_from_text($meta_description);
            $data['keywords'] = array_merge($data['keywords'], $desc_keywords);
        }

        // Get additional keywords
        $keyphrase_synonyms = get_post_meta($post_id, '_yoast_wpseo_keyphrase_synonyms', true);
        if (!empty($keyphrase_synonyms)) {
            $synonyms = explode(',', $keyphrase_synonyms);
            foreach ($synonyms as $synonym) {
                $synonym = trim($synonym);
                if (!empty($synonym)) {
                    $data['keywords'][] = $synonym;
                }
            }
        }

        return $data;
    }

    /**
     * Extract SEO data from All in One SEO plugin
     *
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function extract_aioseo_data($post_id) {
        $data = array(
            'keywords' => array(),
            'descriptions' => array(),
            'focus_keywords' => array()
        );

        // Check if All in One SEO is active
        if (!class_exists('AIOSEO\\Plugin\\AIOSEO')) {
            return $data;
        }

        // Get focus keywords
        $focus_keyphrase = get_post_meta($post_id, '_aioseo_focus_keyphrase', true);
        if (!empty($focus_keyphrase)) {
            $data['focus_keywords'][] = $focus_keyphrase;
            $data['keywords'][] = $focus_keyphrase;
        }

        // Get meta description
        $meta_description = get_post_meta($post_id, '_aioseo_description', true);
        if (!empty($meta_description)) {
            $data['descriptions'][] = $meta_description;
            // Extract keywords from description
            $desc_keywords = $this->extract_keywords_from_text($meta_description);
            $data['keywords'] = array_merge($data['keywords'], $desc_keywords);
        }

        // Get keywords
        $keywords = get_post_meta($post_id, '_aioseo_keywords', true);
        if (!empty($keywords)) {
            $keyword_array = explode(',', $keywords);
            foreach ($keyword_array as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $data['keywords'][] = $keyword;
                }
            }
        }

        return $data;
    }

    /**
     * Extract SEO data from SEOPress plugin
     *
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function extract_seopress_data($post_id) {
        $data = array(
            'keywords' => array(),
            'descriptions' => array(),
            'focus_keywords' => array()
        );

        // Check if SEOPress is active
        if (!function_exists('seopress_get_option')) {
            return $data;
        }

        // Get target keywords
        $target_keywords = get_post_meta($post_id, '_seopress_analysis_target_kw', true);
        if (!empty($target_keywords)) {
            $data['focus_keywords'][] = $target_keywords;
            $data['keywords'][] = $target_keywords;
        }

        // Get meta description
        $meta_description = get_post_meta($post_id, '_seopress_titles_desc', true);
        if (!empty($meta_description)) {
            $data['descriptions'][] = $meta_description;
            // Extract keywords from description
            $desc_keywords = $this->extract_keywords_from_text($meta_description);
            $data['keywords'] = array_merge($data['keywords'], $desc_keywords);
        }

        return $data;
    }

    /**
     * Extract generic SEO data from common meta fields
     *
     * @param int $post_id Post ID
     * @return array SEO data
     */
    private function extract_generic_seo_data($post_id) {
        $data = array(
            'keywords' => array(),
            'descriptions' => array(),
            'focus_keywords' => array()
        );

        // Common meta description fields
        $meta_desc_fields = array(
            'meta_description',
            'description',
            'seo_description',
            '_meta_description'
        );

        foreach ($meta_desc_fields as $field) {
            $meta_desc = get_post_meta($post_id, $field, true);
            if (!empty($meta_desc)) {
                $data['descriptions'][] = $meta_desc;
                // Extract keywords from description
                $desc_keywords = $this->extract_keywords_from_text($meta_desc);
                $data['keywords'] = array_merge($data['keywords'], $desc_keywords);
                break; // Use first found description
            }
        }

        // Common keyword fields
        $keyword_fields = array(
            'meta_keywords',
            'keywords',
            'seo_keywords',
            '_meta_keywords'
        );

        foreach ($keyword_fields as $field) {
            $keywords = get_post_meta($post_id, $field, true);
            if (!empty($keywords)) {
                if (is_string($keywords)) {
                    $keyword_array = explode(',', $keywords);
                    foreach ($keyword_array as $keyword) {
                        $keyword = trim($keyword);
                        if (!empty($keyword)) {
                            $data['keywords'][] = $keyword;
                        }
                    }
                }
                break; // Use first found keywords
            }
        }

        return $data;
    }

    /**
     * Extract keywords from text using simple word frequency analysis
     *
     * @param string $text Text to analyze
     * @param int $min_length Minimum word length
     * @return array Array of keywords
     */
    private function extract_keywords_from_text($text, $min_length = 4) {
        if (empty($text)) {
            return array();
        }

        // Remove HTML tags and normalize text
        $text = wp_strip_all_tags($text);
        $text = strtolower($text);
        
        // Remove common stop words
        $stop_words = array(
            'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by',
            'this', 'that', 'these', 'those', 'is', 'are', 'was', 'were', 'be', 'been',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'must', 'can', 'shall', 'from', 'up', 'out', 'down', 'off',
            'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when',
            'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 'most',
            'other', 'some', 'such', 'only', 'own', 'same', 'so', 'than', 'too', 'very'
        );

        // Extract words
        preg_match_all('/\b[a-z]{' . $min_length . ',}\b/', $text, $matches);
        $words = $matches[0];

        // Filter out stop words and get unique words
        $keywords = array();
        foreach ($words as $word) {
            if (!in_array($word, $stop_words) && strlen($word) >= $min_length) {
                $keywords[] = $word;
            }
        }

        // Return unique keywords, limited to top 10
        return array_slice(array_unique($keywords), 0, 10);
    }

    /**
     * Merge SEO data arrays
     *
     * @param array $existing Existing SEO data
     * @param array $new New SEO data to merge
     * @return array Merged SEO data
     */
    private function merge_seo_data($existing, $new) {
        foreach ($new as $key => $values) {
            if (!empty($values)) {
                $existing[$key] = array_merge($existing[$key], $values);
            }
        }
        return $existing;
    }

    /**
     * Extract content from various page builders
     *
     * @param array $post_ids Array of post IDs
     * @return array Page builder content data
     */
    private function extract_page_builder_content($post_ids) {
        $content_data = array();

        foreach ($post_ids as $post_id) {
            // Elementor content extraction
            $elementor_content = $this->extract_elementor_content($post_id);
            if (!empty($elementor_content)) {
                $content_data[] = array(
                    'post_id' => $post_id,
                    'builder' => 'elementor',
                    'text' => $elementor_content['text'],
                    'headings' => $elementor_content['headings']
                );
            }

            // Gutenberg blocks extraction
            $gutenberg_content = $this->extract_gutenberg_content($post_id);
            if (!empty($gutenberg_content)) {
                $content_data[] = array(
                    'post_id' => $post_id,
                    'builder' => 'gutenberg',
                    'text' => $gutenberg_content['text'],
                    'headings' => $gutenberg_content['headings']
                );
            }

            // Divi Builder content extraction
            $divi_content = $this->extract_divi_content($post_id);
            if (!empty($divi_content)) {
                $content_data[] = array(
                    'post_id' => $post_id,
                    'builder' => 'divi',
                    'text' => $divi_content['text'],
                    'headings' => $divi_content['headings']
                );
            }

            // Beaver Builder content extraction
            $beaver_content = $this->extract_beaver_builder_content($post_id);
            if (!empty($beaver_content)) {
                $content_data[] = array(
                    'post_id' => $post_id,
                    'builder' => 'beaver_builder',
                    'text' => $beaver_content['text'],
                    'headings' => $beaver_content['headings']
                );
            }

            // Visual Composer content extraction
            $vc_content = $this->extract_visual_composer_content($post_id);
            if (!empty($vc_content)) {
                $content_data[] = array(
                    'post_id' => $post_id,
                    'builder' => 'visual_composer',
                    'text' => $vc_content['text'],
                    'headings' => $vc_content['headings']
                );
            }

            // Generic post meta scanner
            $generic_content = $this->extract_generic_post_meta_content($post_id);
            if (!empty($generic_content)) {
                $content_data[] = array(
                    'post_id' => $post_id,
                    'builder' => 'generic_meta',
                    'text' => $generic_content['text'],
                    'headings' => $generic_content['headings']
                );
            }
        }

        return $content_data;
    }

    /**
     * Extract content from Elementor
     *
     * @param int $post_id Post ID
     * @return array Content data
     */
    private function extract_elementor_content($post_id) {
        $content = array('text' => '', 'headings' => array());

        // Check if Elementor is active and this post uses Elementor
        if (!class_exists('\\Elementor\\Plugin')) {
            return $content;
        }

        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            return $content;
        }

        // Parse Elementor data (it's stored as JSON)
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return $content;
        }

        $content = $this->parse_elementor_elements($data, $content);

        return $content;
    }

    /**
     * Recursively parse Elementor elements
     *
     * @param array $elements Elementor elements
     * @param array $content Content accumulator
     * @return array Updated content
     */
    private function parse_elementor_elements($elements, $content) {
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            // Extract text content from various Elementor widgets
            if (isset($element['widgetType'])) {
                switch ($element['widgetType']) {
                    case 'text-editor':
                    case 'heading':
                        if (isset($element['settings']['editor'])) {
                            $text = wp_strip_all_tags($element['settings']['editor']);
                            $content['text'] .= ' ' . $text;
                            
                            if ($element['widgetType'] === 'heading') {
                                $content['headings'][] = trim($text);
                            }
                        }
                        if (isset($element['settings']['title'])) {
                            $title = wp_strip_all_tags($element['settings']['title']);
                            $content['text'] .= ' ' . $title;
                            $content['headings'][] = trim($title);
                        }
                        break;
                    case 'button':
                        if (isset($element['settings']['text'])) {
                            $content['text'] .= ' ' . wp_strip_all_tags($element['settings']['text']);
                        }
                        break;
                }
            }

            // Recursively process child elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $content = $this->parse_elementor_elements($element['elements'], $content);
            }
        }

        return $content;
    }

    /**
     * Extract content from Gutenberg blocks
     *
     * @param int $post_id Post ID
     * @return array Content data
     */
    private function extract_gutenberg_content($post_id) {
        $content = array('text' => '', 'headings' => array());

        $post = get_post($post_id);
        if (!$post || !has_blocks($post->post_content)) {
            return $content;
        }

        $blocks = parse_blocks($post->post_content);
        $content = $this->parse_gutenberg_blocks($blocks, $content);

        return $content;
    }

    /**
     * Recursively parse Gutenberg blocks
     *
     * @param array $blocks Gutenberg blocks
     * @param array $content Content accumulator
     * @return array Updated content
     */
    private function parse_gutenberg_blocks($blocks, $content) {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            // Extract content based on block type
            switch ($block['blockName']) {
                case 'core/paragraph':
                case 'core/quote':
                case 'core/list':
                    if (!empty($block['innerHTML'])) {
                        $text = wp_strip_all_tags($block['innerHTML']);
                        $content['text'] .= ' ' . $text;
                    }
                    break;
                case 'core/heading':
                    if (!empty($block['innerHTML'])) {
                        $heading = wp_strip_all_tags($block['innerHTML']);
                        $content['headings'][] = trim($heading);
                        $content['text'] .= ' ' . $heading;
                    }
                    break;
                case 'core/button':
                    if (isset($block['attrs']['text'])) {
                        $content['text'] .= ' ' . wp_strip_all_tags($block['attrs']['text']);
                    }
                    break;
            }

            // Process inner blocks
            if (!empty($block['innerBlocks'])) {
                $content = $this->parse_gutenberg_blocks($block['innerBlocks'], $content);
            }
        }

        return $content;
    }

    /**
     * Extract content from Divi Builder
     *
     * @param int $post_id Post ID
     * @return array Content data
     */
    private function extract_divi_content($post_id) {
        $content = array('text' => '', 'headings' => array());

        // Check if Divi is active
        if (!function_exists('et_pb_is_pagebuilder_used')) {
            return $content;
        }

        if (!et_pb_is_pagebuilder_used($post_id)) {
            return $content;
        }

        // Get Divi content from post meta
        $divi_content = get_post_meta($post_id, '_et_pb_page_layout', true);
        if (empty($divi_content)) {
            return $content;
        }

        // Parse Divi shortcodes to extract text
        $text = wp_strip_all_tags($divi_content);
        $content['text'] = $text;

        // Extract headings using regex (Divi uses specific patterns)
        preg_match_all('/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/s', $divi_content, $text_matches);
        if (!empty($text_matches[1])) {
            foreach ($text_matches[1] as $text_content) {
                preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $text_content, $heading_matches);
                if (!empty($heading_matches[1])) {
                    foreach ($heading_matches[1] as $heading) {
                        $content['headings'][] = wp_strip_all_tags($heading);
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Extract content from Beaver Builder
     *
     * @param int $post_id Post ID
     * @return array Content data
     */
    private function extract_beaver_builder_content($post_id) {
        $content = array('text' => '', 'headings' => array());

        // Check if Beaver Builder is active
        if (!class_exists('FLBuilder')) {
            return $content;
        }

        $bb_enabled = get_post_meta($post_id, '_fl_builder_enabled', true);
        if (!$bb_enabled) {
            return $content;
        }

        $bb_data = get_post_meta($post_id, '_fl_builder_data', true);
        if (empty($bb_data) || !is_array($bb_data)) {
            return $content;
        }

        // Parse Beaver Builder data
        foreach ($bb_data as $node) {
            if (!is_object($node) || !isset($node->type)) {
                continue;
            }

            if ($node->type === 'module' && isset($node->settings)) {
                // Extract text from various module types
                if (isset($node->settings->text)) {
                    $text = wp_strip_all_tags($node->settings->text);
                    $content['text'] .= ' ' . $text;
                }
                if (isset($node->settings->heading)) {
                    $heading = wp_strip_all_tags($node->settings->heading);
                    $content['headings'][] = trim($heading);
                    $content['text'] .= ' ' . $heading;
                }
            }
        }

        return $content;
    }

    /**
     * Extract content from Visual Composer
     *
     * @param int $post_id Post ID
     * @return array Content data
     */
    private function extract_visual_composer_content($post_id) {
        $content = array('text' => '', 'headings' => array());

        // Check if Visual Composer is active
        if (!function_exists('vc_is_page_editable')) {
            return $content;
        }

        $post = get_post($post_id);
        if (!$post) {
            return $content;
        }

        // Check if post uses Visual Composer
        if (strpos($post->post_content, '[vc_') === false) {
            return $content;
        }

        // Extract content from VC shortcodes
        $vc_content = $post->post_content;
        
        // Remove shortcode tags but keep content
        $text = preg_replace('/\[vc_[^\]]*\]/', '', $vc_content);
        $text = preg_replace('/\[\/vc_[^\]]*\]/', '', $text);
        $text = wp_strip_all_tags($text);
        $content['text'] = $text;

        // Extract headings from VC heading shortcodes
        preg_match_all('/\[vc_custom_heading[^\]]*text="([^"]*)"[^\]]*\]/', $vc_content, $heading_matches);
        if (!empty($heading_matches[1])) {
            foreach ($heading_matches[1] as $heading) {
                $content['headings'][] = wp_strip_all_tags($heading);
            }
        }

        return $content;
    }

    /**
     * Generic post meta content scanner
     *
     * @param int $post_id Post ID
     * @return array Content data
     */
    private function extract_generic_post_meta_content($post_id) {
        $content = array('text' => '', 'headings' => array());

        // Get all post meta
        $all_meta = get_post_meta($post_id);
        if (empty($all_meta)) {
            return $content;
        }

        // Common meta keys that might contain content
        $content_meta_keys = array(
            '_page_builder_content',
            '_builder_content',
            '_custom_content',
            '_page_content',
            '_layout_content',
            '_content_data'
        );

        foreach ($content_meta_keys as $meta_key) {
            if (isset($all_meta[$meta_key])) {
                $meta_value = $all_meta[$meta_key][0];
                
                // Try to extract text content
                if (is_string($meta_value)) {
                    // If it's serialized data, try to unserialize
                    if (is_serialized($meta_value)) {
                        $unserialized = maybe_unserialize($meta_value);
                        if (is_array($unserialized)) {
                            $text = $this->extract_text_from_array($unserialized);
                            $content['text'] .= ' ' . $text;
                        }
                    } else {
                        // Extract text directly
                        $text = wp_strip_all_tags($meta_value);
                        $content['text'] .= ' ' . $text;
                        
                        // Look for headings in HTML
                        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $meta_value, $heading_matches);
                        if (!empty($heading_matches[1])) {
                            foreach ($heading_matches[1] as $heading) {
                                $content['headings'][] = wp_strip_all_tags($heading);
                            }
                        }
                    }
                }
            }
        }

        return $content;
    }

    /**
     * Recursively extract text from array structures
     *
     * @param array $data Array data
     * @return string Extracted text
     */
    private function extract_text_from_array($data) {
        $text = '';
        
        if (!is_array($data)) {
            return is_string($data) ? wp_strip_all_tags($data) : '';
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $text .= ' ' . $this->extract_text_from_array($value);
            } elseif (is_string($value)) {
                // Look for text-like content (avoid URLs, IDs, etc.)
                if (strlen($value) > 10 && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $text .= ' ' . wp_strip_all_tags($value);
                }
            }
        }

        return $text;
    }

    /**
     * Clean up and deduplicate context data
     *
     * @param array $context Context data
     * @return array Cleaned context data
     */
    private function cleanup_context_data($context) {
        // Remove duplicates and empty values from all arrays
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $context[$key] = array_values(array_unique(array_filter($value, function($item) {
                    return !empty(trim($item));
                })));
                
                // Limit array sizes to prevent overwhelming the AI
                if (count($context[$key]) > 20) {
                    $context[$key] = array_slice($context[$key], 0, 20);
                }
            }
        }

        return $context;
    }
}