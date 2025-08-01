<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Performance Optimizer Class
 * 
 * Handles performance optimization for AI operations including
 * database query optimization, caching, and resource management.
 */
class FMR_Performance_Optimizer {

    /**
     * @var string Cache prefix for AI results
     */
    private $cache_prefix = 'fmr_ai_cache_';

    /**
     * @var int Default cache expiration time (1 hour)
     */
    private $default_cache_expiration = 3600;

    /**
     * @var array Query cache for database optimization
     */
    private $query_cache = array();

    /**
     * Optimize database queries for context extraction
     *
     * @param int $post_id Media post ID
     * @return array Optimized query results
     */
    public function optimize_context_queries($post_id) {
        // Check if results are already cached in memory
        $memory_cache_key = 'context_' . $post_id;
        if (isset($this->query_cache[$memory_cache_key])) {
            return $this->query_cache[$memory_cache_key];
        }

        // Check WordPress transient cache
        $cache_key = $this->cache_prefix . 'context_' . $post_id;
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            $this->query_cache[$memory_cache_key] = $cached_result;
            return $cached_result;
        }

        // Perform optimized database queries
        $results = $this->execute_optimized_context_queries($post_id);
        
        // Cache the results
        set_transient($cache_key, $results, $this->get_cache_expiration('context'));
        $this->query_cache[$memory_cache_key] = $results;
        
        return $results;
    }

    /**
     * Execute optimized database queries for context extraction
     *
     * @param int $post_id Media post ID
     * @return array Query results
     */
    private function execute_optimized_context_queries($post_id) {
        global $wpdb;

        $file_url = wp_get_attachment_url($post_id);
        if (!$file_url) {
            return array('post_ids' => array(), 'metadata' => array());
        }

        // Get attachment metadata for additional URLs
        $attachment_urls = $this->get_attachment_urls_optimized($post_id);
        
        // Use a single optimized query to find all posts using any of the URLs
        $post_ids = $this->find_posts_using_media_optimized($attachment_urls);
        
        // Get metadata for found posts in batch
        $metadata = $this->get_posts_metadata_batch($post_ids);
        
        return array(
            'post_ids' => $post_ids,
            'metadata' => $metadata,
            'urls_searched' => $attachment_urls
        );
    }

    /**
     * Get attachment URLs with optimization
     *
     * @param int $post_id Media post ID
     * @return array Array of URLs
     */
    private function get_attachment_urls_optimized($post_id) {
        // Check cache first
        $cache_key = $this->cache_prefix . 'urls_' . $post_id;
        $cached_urls = get_transient($cache_key);
        
        if ($cached_urls !== false) {
            return $cached_urls;
        }

        $urls = array();
        
        // Main file URL
        $main_url = wp_get_attachment_url($post_id);
        if ($main_url) {
            $urls[] = $main_url;
        }

        // Get all thumbnail sizes efficiently
        $metadata = wp_get_attachment_metadata($post_id);
        if (!empty($metadata['sizes'])) {
            $base_url = trailingslashit(dirname($main_url));
            
            foreach ($metadata['sizes'] as $size => $size_data) {
                if (!empty($size_data['file'])) {
                    $urls[] = $base_url . $size_data['file'];
                }
            }
        }

        $urls = array_unique($urls);
        
        // Cache URLs for 1 hour
        set_transient($cache_key, $urls, 3600);
        
        return $urls;
    }

    /**
     * Find posts using media with optimized queries
     *
     * @param array $urls Array of URLs to search for
     * @return array Array of post IDs
     */
    private function find_posts_using_media_optimized($urls) {
        if (empty($urls)) {
            return array();
        }

        global $wpdb;
        
        // Create optimized query using UNION for better performance
        $url_conditions = array();
        $url_params = array();
        
        foreach ($urls as $url) {
            $url_conditions[] = "post_content LIKE %s";
            $url_params[] = '%' . $wpdb->esc_like($url) . '%';
        }
        
        // Single query to search all URLs in post content
        $content_query = "
            SELECT DISTINCT ID as post_id, 'content' as source
            FROM {$wpdb->posts} 
            WHERE (" . implode(' OR ', $url_conditions) . ")
            AND post_status = 'publish'
            AND post_type IN ('post', 'page', 'product')
        ";
        
        // Query for post meta
        $meta_conditions = array();
        foreach ($urls as $url) {
            $meta_conditions[] = "meta_value LIKE %s";
            $url_params[] = '%' . $wpdb->esc_like($url) . '%';
        }
        
        $meta_query = "
            SELECT DISTINCT pm.post_id, 'meta' as source
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE (" . implode(' OR ', $meta_conditions) . ")
            AND p.post_status = 'publish'
            AND p.post_type IN ('post', 'page', 'product')
        ";
        
        // Combine queries with UNION
        $combined_query = "
            ($content_query)
            UNION
            ($meta_query)
            ORDER BY post_id DESC
            LIMIT 100
        ";
        
        $prepared_query = $wpdb->prepare($combined_query, $url_params);
        $results = $wpdb->get_results($prepared_query);
        
        $post_ids = array();
        if ($results) {
            foreach ($results as $result) {
                $post_ids[] = intval($result->post_id);
            }
        }
        
        return array_unique($post_ids);
    }

    /**
     * Get posts metadata in batch for better performance
     *
     * @param array $post_ids Array of post IDs
     * @return array Metadata for posts
     */
    private function get_posts_metadata_batch($post_ids) {
        if (empty($post_ids)) {
            return array();
        }

        global $wpdb;
        
        // Limit to prevent memory issues
        $post_ids = array_slice($post_ids, 0, 50);
        $post_ids_str = implode(',', array_map('intval', $post_ids));
        
        // Single query to get all post data
        $posts_query = "
            SELECT ID, post_title, post_content, post_excerpt, post_type
            FROM {$wpdb->posts}
            WHERE ID IN ($post_ids_str)
            AND post_status = 'publish'
        ";
        
        $posts = $wpdb->get_results($posts_query, OBJECT_K);
        
        // Get all relevant meta data in batch
        $meta_keys = array(
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_metadesc',
            'rank_math_focus_keyword',
            'rank_math_description',
            '_aioseo_focus_keyphrase',
            '_aioseo_description',
            '_seopress_analysis_target_kw',
            '_seopress_titles_desc'
        );
        
        $meta_keys_str = "'" . implode("','", array_map('esc_sql', $meta_keys)) . "'";
        
        $meta_query = "
            SELECT post_id, meta_key, meta_value
            FROM {$wpdb->postmeta}
            WHERE post_id IN ($post_ids_str)
            AND meta_key IN ($meta_keys_str)
        ";
        
        $meta_results = $wpdb->get_results($meta_query);
        
        // Organize meta data by post ID
        $meta_data = array();
        foreach ($meta_results as $meta) {
            $meta_data[$meta->post_id][$meta->meta_key] = $meta->meta_value;
        }
        
        // Get categories and tags in batch
        $terms_query = "
            SELECT tr.object_id as post_id, t.name, tt.taxonomy
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tr.object_id IN ($post_ids_str)
            AND tt.taxonomy IN ('category', 'post_tag', 'product_cat', 'product_tag')
        ";
        
        $terms_results = $wpdb->get_results($terms_query);
        
        // Organize terms by post ID
        $terms_data = array();
        foreach ($terms_results as $term) {
            $terms_data[$term->post_id][$term->taxonomy][] = $term->name;
        }
        
        // Combine all data
        $metadata = array();
        foreach ($posts as $post_id => $post) {
            $metadata[$post_id] = array(
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'type' => $post->post_type,
                'meta' => isset($meta_data[$post_id]) ? $meta_data[$post_id] : array(),
                'terms' => isset($terms_data[$post_id]) ? $terms_data[$post_id] : array()
            );
        }
        
        return $metadata;
    }

    /**
     * Cache AI results for frequently accessed data
     *
     * @param string $cache_type Type of cache (e.g., 'suggestions', 'content_analysis')
     * @param string $cache_key Unique cache key
     * @param mixed $data Data to cache
     * @param int $expiration Cache expiration time (optional)
     * @return bool True if cached successfully
     */
    public function cache_ai_result($cache_type, $cache_key, $data, $expiration = null) {
        if (!$this->is_caching_enabled()) {
            return false;
        }

        if ($expiration === null) {
            $expiration = $this->get_cache_expiration($cache_type);
        }

        $full_cache_key = $this->cache_prefix . $cache_type . '_' . $cache_key;
        
        // Add metadata to cached data
        $cache_data = array(
            'data' => $data,
            'cached_at' => time(),
            'cache_type' => $cache_type,
            'expiration' => $expiration
        );
        
        return set_transient($full_cache_key, $cache_data, $expiration);
    }

    /**
     * Get cached AI result
     *
     * @param string $cache_type Type of cache
     * @param string $cache_key Unique cache key
     * @return mixed Cached data or false if not found
     */
    public function get_cached_ai_result($cache_type, $cache_key) {
        if (!$this->is_caching_enabled()) {
            return false;
        }

        $full_cache_key = $this->cache_prefix . $cache_type . '_' . $cache_key;
        $cached_data = get_transient($full_cache_key);
        
        if ($cached_data === false) {
            return false;
        }

        // Validate cache structure
        if (!is_array($cached_data) || !isset($cached_data['data'])) {
            // Invalid cache structure, delete it
            delete_transient($full_cache_key);
            return false;
        }

        return $cached_data['data'];
    }

    /**
     * Generate cache key for content analysis
     *
     * @param int $post_id Media post ID
     * @return string Cache key
     */
    public function generate_content_cache_key($post_id) {
        $file_path = get_attached_file($post_id);
        $file_modified = file_exists($file_path) ? filemtime($file_path) : 0;
        
        return md5($post_id . '_' . $file_modified . '_' . get_post_modified_time('U', true, $post_id));
    }

    /**
     * Generate cache key for context extraction
     *
     * @param int $post_id Media post ID
     * @return string Cache key
     */
    public function generate_context_cache_key($post_id) {
        // Include factors that might affect context
        $factors = array(
            $post_id,
            get_post_modified_time('U', true, $post_id),
            $this->get_site_content_hash() // Hash of recent site changes
        );
        
        return md5(implode('_', $factors));
    }

    /**
     * Get a hash representing recent site content changes
     *
     * @return string Content hash
     */
    private function get_site_content_hash() {
        // Check cache first
        $hash_cache_key = $this->cache_prefix . 'site_content_hash';
        $cached_hash = get_transient($hash_cache_key);
        
        if ($cached_hash !== false) {
            return $cached_hash;
        }

        global $wpdb;
        
        // Get hash based on recent post modifications
        $recent_posts_hash = $wpdb->get_var("
            SELECT MD5(GROUP_CONCAT(post_modified ORDER BY post_modified DESC))
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type IN ('post', 'page', 'product')
            AND post_modified > DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 100
        ");
        
        $hash = $recent_posts_hash ?: 'no_recent_changes';
        
        // Cache for 1 hour
        set_transient($hash_cache_key, $hash, 3600);
        
        return $hash;
    }

    /**
     * Clear cache for specific media file
     *
     * @param int $post_id Media post ID
     * @return bool True if cache was cleared
     */
    public function clear_media_cache($post_id) {
        $cache_types = array('content_analysis', 'context', 'suggestions', 'urls');
        $cleared = false;
        
        foreach ($cache_types as $type) {
            $cache_key = $this->cache_prefix . $type . '_' . $post_id;
            if (delete_transient($cache_key)) {
                $cleared = true;
            }
        }
        
        // Clear memory cache
        $memory_cache_key = 'context_' . $post_id;
        if (isset($this->query_cache[$memory_cache_key])) {
            unset($this->query_cache[$memory_cache_key]);
            $cleared = true;
        }
        
        return $cleared;
    }

    /**
     * Clear all AI-related cache
     *
     * @return int Number of cache entries cleared
     */
    public function clear_all_ai_cache() {
        global $wpdb;
        
        // Delete all transients with our prefix
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE %s
            OR option_name LIKE %s
        ", 
            '_transient_' . $this->cache_prefix . '%',
            '_transient_timeout_' . $this->cache_prefix . '%'
        ));
        
        // Clear memory cache
        $this->query_cache = array();
        
        return $deleted ? $deleted : 0;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        
        // Count cache entries
        $cache_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            AND option_name NOT LIKE %s
        ", 
            '_transient_' . $this->cache_prefix . '%',
            '_transient_timeout_%'
        ));
        
        // Get cache size (approximate)
        $cache_size = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(LENGTH(option_value))
            FROM {$wpdb->options}
            WHERE option_name LIKE %s
            AND option_name NOT LIKE %s
        ", 
            '_transient_' . $this->cache_prefix . '%',
            '_transient_timeout_%'
        ));
        
        return array(
            'total_entries' => intval($cache_count),
            'total_size_bytes' => intval($cache_size),
            'total_size_mb' => round($cache_size / 1024 / 1024, 2),
            'memory_cache_entries' => count($this->query_cache),
            'cache_enabled' => $this->is_caching_enabled()
        );
    }

    /**
     * Check if caching is enabled
     *
     * @return bool True if caching is enabled
     */
    private function is_caching_enabled() {
        $options = get_option('fmrseo_options', array());
        return !isset($options['ai_cache_enabled']) || $options['ai_cache_enabled'] !== false;
    }

    /**
     * Get cache expiration time for specific cache type
     *
     * @param string $cache_type Cache type
     * @return int Expiration time in seconds
     */
    private function get_cache_expiration($cache_type) {
        $options = get_option('fmrseo_options', array());
        
        $default_expirations = array(
            'content_analysis' => 7200,  // 2 hours
            'context' => 3600,           // 1 hour
            'suggestions' => 1800,       // 30 minutes
            'urls' => 3600               // 1 hour
        );
        
        $custom_key = 'ai_cache_expiration_' . $cache_type;
        
        if (isset($options[$custom_key])) {
            return intval($options[$custom_key]);
        }
        
        return isset($default_expirations[$cache_type]) 
            ? $default_expirations[$cache_type] 
            : $this->default_cache_expiration;
    }

    /**
     * Optimize memory usage during bulk operations
     *
     * @param array $post_ids Array of post IDs to process
     * @param int $batch_size Batch size for processing
     * @return array Batched post IDs
     */
    public function optimize_bulk_processing($post_ids, $batch_size = 10) {
        // Limit total number of items to prevent memory issues
        $max_items = 50;
        if (count($post_ids) > $max_items) {
            $post_ids = array_slice($post_ids, 0, $max_items);
        }
        
        // Split into batches
        $batches = array_chunk($post_ids, $batch_size);
        
        return $batches;
    }

    /**
     * Monitor and log performance metrics
     *
     * @param string $operation Operation name
     * @param float $start_time Start time (from microtime(true))
     * @param array $additional_data Additional performance data
     */
    public function log_performance_metrics($operation, $start_time, $additional_data = array()) {
        $execution_time = microtime(true) - $start_time;
        $memory_usage = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        $metrics = array(
            'operation' => $operation,
            'execution_time' => round($execution_time, 4),
            'memory_usage_mb' => round($memory_usage / 1024 / 1024, 2),
            'peak_memory_mb' => round($peak_memory / 1024 / 1024, 2),
            'timestamp' => time()
        );
        
        $metrics = array_merge($metrics, $additional_data);
        
        // Log performance data
        error_log('FMR Performance: ' . wp_json_encode($metrics));
        
        // Store in transient for dashboard display
        $perf_log_key = $this->cache_prefix . 'performance_log';
        $perf_log = get_transient($perf_log_key);
        
        if (!is_array($perf_log)) {
            $perf_log = array();
        }
        
        $perf_log[] = $metrics;
        
        // Keep only last 50 entries
        if (count($perf_log) > 50) {
            $perf_log = array_slice($perf_log, -50);
        }
        
        set_transient($perf_log_key, $perf_log, 3600); // 1 hour
    }

    /**
     * Get performance metrics
     *
     * @param int $limit Number of recent metrics to return
     * @return array Performance metrics
     */
    public function get_performance_metrics($limit = 20) {
        $perf_log_key = $this->cache_prefix . 'performance_log';
        $perf_log = get_transient($perf_log_key);
        
        if (!is_array($perf_log)) {
            return array();
        }
        
        // Sort by timestamp descending
        usort($perf_log, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return array_slice($perf_log, 0, $limit);
    }

    /**
     * Clean up expired cache entries and optimize database
     *
     * @return array Cleanup results
     */
    public function cleanup_and_optimize() {
        global $wpdb;
        
        $results = array(
            'expired_cache_cleared' => 0,
            'orphaned_timeouts_cleared' => 0,
            'performance_log_cleaned' => false
        );
        
        // Clean up expired transients
        $expired_deleted = $wpdb->query("
            DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
            WHERE a.option_name LIKE '_transient_%'
            AND a.option_name NOT LIKE '_transient_timeout_%'
            AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
            AND b.option_value < UNIX_TIMESTAMP()
        ");
        
        $results['expired_cache_cleared'] = $expired_deleted ? $expired_deleted : 0;
        
        // Clean up orphaned timeout entries
        $orphaned_deleted = $wpdb->query("
            DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_timeout_%'
            AND option_name NOT IN (
                SELECT CONCAT('_transient_timeout_', SUBSTRING(option_name, 12))
                FROM {$wpdb->options}
                WHERE option_name LIKE '_transient_%'
                AND option_name NOT LIKE '_transient_timeout_%'
            )
        ");
        
        $results['orphaned_timeouts_cleared'] = $orphaned_deleted ? $orphaned_deleted : 0;
        
        // Clean old performance logs
        $perf_log_key = $this->cache_prefix . 'performance_log';
        if (delete_transient($perf_log_key)) {
            $results['performance_log_cleaned'] = true;
        }
        
        return $results;
    }
}