<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * File Type Analyzer Interface
 * 
 * Interface for different file type analyzers to ensure consistent implementation
 */
interface FMR_File_Analyzer_Interface {
    
    /**
     * Analyze file content
     *
     * @param string $file_path Full path to the file
     * @param int $post_id Media post ID
     * @return array Analysis results
     */
    public function analyze($file_path, $post_id);
    
    /**
     * Check if this analyzer supports the given file type
     *
     * @param string $mime_type File MIME type
     * @return bool True if supported
     */
    public function supports($mime_type);
}

/**
 * Content Analyzer Base Class
 * 
 * Analyzes media file content to extract relevant information
 * for AI-powered filename generation with WordPress-compatible file handling.
 */
class FMR_Content_Analyzer {

    /**
     * Registered file analyzers
     *
     * @var array
     */
    private $analyzers = array();

    /**
     * WordPress filesystem instance
     *
     * @var WP_Filesystem_Base
     */
    private $filesystem;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_filesystem();
        $this->register_default_analyzers();
    }

    /**
     * Initialize WordPress filesystem
     */
    private function init_filesystem() {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        if (!WP_Filesystem()) {
            // Fallback to direct filesystem access if WP_Filesystem fails
            $this->filesystem = null;
        } else {
            global $wp_filesystem;
            $this->filesystem = $wp_filesystem;
        }
    }

    /**
     * Register default file analyzers
     */
    private function register_default_analyzers() {
        // Register image analyzer
        if (class_exists('FMR_Image_Analyzer')) {
            $this->register_analyzer(new FMR_Image_Analyzer());
        }
        
        // Register PDF analyzer
        if (class_exists('FMR_PDF_Analyzer')) {
            $this->register_analyzer(new FMR_PDF_Analyzer());
        }
        
        // Register Office document analyzer
        if (class_exists('FMR_Office_Analyzer')) {
            $this->register_analyzer(new FMR_Office_Analyzer());
        }
    }

    /**
     * Register a file analyzer
     *
     * @param FMR_File_Analyzer_Interface $analyzer
     */
    public function register_analyzer(FMR_File_Analyzer_Interface $analyzer) {
        $this->analyzers[] = $analyzer;
    }

    /**
     * Analyze media file content
     *
     * @param int $post_id Media post ID
     * @return array Content analysis data
     */
    public function analyze_media($post_id) {
        // Validate post ID
        if (!$this->is_valid_media_post($post_id)) {
            return $this->get_error_result('Invalid media post ID');
        }

        // Get file information
        $file_path = get_attached_file($post_id);
        if (!$file_path || !$this->file_exists($file_path)) {
            return $this->get_error_result('File not found or inaccessible');
        }

        // Validate file access and permissions
        if (!$this->is_file_readable($file_path)) {
            return $this->get_error_result('File is not readable');
        }

        // Get file type information
        $file_type = wp_check_filetype($file_path);
        $mime_type = $file_type['type'];

        // Initialize content analysis result
        $content = array(
            'success' => true,
            'file_type' => $mime_type,
            'file_extension' => $file_type['ext'],
            'file_path' => $file_path,
            'file_size' => $this->get_file_size($file_path),
            'metadata' => $this->extract_wordpress_metadata($post_id),
            'extracted_text' => '',
            'detected_objects' => array(),
            'analysis_method' => 'metadata_only'
        );

        // Try to find a suitable analyzer for this file type
        $analyzer = $this->find_analyzer_for_type($mime_type);
        if ($analyzer) {
            try {
                $analysis_result = $analyzer->analyze($file_path, $post_id);
                if (is_array($analysis_result)) {
                    $content = array_merge($content, $analysis_result);
                    $content['analysis_method'] = get_class($analyzer);
                }
            } catch (Exception $e) {
                // Log error but continue with metadata-only analysis
                error_log('FMR Content Analyzer error: ' . $e->getMessage());
                $content['analysis_error'] = $e->getMessage();
            }
        }

        return $content;
    }

    /**
     * Validate if the post is a valid media attachment
     *
     * @param int $post_id
     * @return bool
     */
    private function is_valid_media_post($post_id) {
        if (!is_numeric($post_id) || $post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        return $post && $post->post_type === 'attachment';
    }

    /**
     * Check if file exists using WordPress filesystem or fallback
     *
     * @param string $file_path
     * @return bool
     */
    private function file_exists($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->exists($file_path);
        }
        return file_exists($file_path);
    }

    /**
     * Check if file is readable
     *
     * @param string $file_path
     * @return bool
     */
    private function is_file_readable($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->is_readable($file_path);
        }
        return is_readable($file_path);
    }

    /**
     * Get file size
     *
     * @param string $file_path
     * @return int File size in bytes
     */
    private function get_file_size($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->size($file_path);
        }
        return filesize($file_path);
    }

    /**
     * Find analyzer for specific MIME type
     *
     * @param string $mime_type
     * @return FMR_File_Analyzer_Interface|null
     */
    private function find_analyzer_for_type($mime_type) {
        foreach ($this->analyzers as $analyzer) {
            if ($analyzer->supports($mime_type)) {
                return $analyzer;
            }
        }
        return null;
    }

    /**
     * Extract comprehensive WordPress metadata for the media file
     *
     * @param int $post_id Media post ID
     * @return array WordPress metadata
     */
    private function extract_wordpress_metadata($post_id) {
        $post = get_post($post_id);
        $metadata = array();

        if ($post) {
            // Basic post data
            $metadata['title'] = sanitize_text_field($post->post_title);
            $metadata['description'] = sanitize_textarea_field($post->post_content);
            $metadata['alt_text'] = sanitize_text_field(get_post_meta($post_id, '_wp_attachment_image_alt', true));
            $metadata['caption'] = sanitize_textarea_field($post->post_excerpt);
            $metadata['post_name'] = sanitize_title($post->post_name);
            $metadata['post_date'] = $post->post_date;
            $metadata['post_modified'] = $post->post_modified;
        }

        // Get attachment metadata
        $wp_metadata = wp_get_attachment_metadata($post_id);
        if ($wp_metadata && is_array($wp_metadata)) {
            // Image-specific metadata
            if (isset($wp_metadata['width']) && isset($wp_metadata['height'])) {
                $metadata['dimensions'] = array(
                    'width' => intval($wp_metadata['width']),
                    'height' => intval($wp_metadata['height'])
                );
            }

            // EXIF data for images
            if (isset($wp_metadata['image_meta']) && is_array($wp_metadata['image_meta'])) {
                $exif = $wp_metadata['image_meta'];
                $metadata['exif'] = array(
                    'camera' => sanitize_text_field($exif['camera'] ?? ''),
                    'created_timestamp' => sanitize_text_field($exif['created_timestamp'] ?? ''),
                    'copyright' => sanitize_text_field($exif['copyright'] ?? ''),
                    'credit' => sanitize_text_field($exif['credit'] ?? ''),
                    'title' => sanitize_text_field($exif['title'] ?? ''),
                    'keywords' => is_array($exif['keywords'] ?? null) ? array_map('sanitize_text_field', $exif['keywords']) : array()
                );
            }

            // File-specific metadata
            if (isset($wp_metadata['filesize'])) {
                $metadata['filesize'] = intval($wp_metadata['filesize']);
            }
        }

        // Get MIME type
        $metadata['mime_type'] = get_post_mime_type($post_id);

        // Get file URL
        $metadata['file_url'] = wp_get_attachment_url($post_id);

        // Get thumbnail URLs if available
        $thumbnail_sizes = get_intermediate_image_sizes();
        $metadata['thumbnails'] = array();
        foreach ($thumbnail_sizes as $size) {
            $thumbnail = wp_get_attachment_image_src($post_id, $size);
            if ($thumbnail) {
                $metadata['thumbnails'][$size] = array(
                    'url' => $thumbnail[0],
                    'width' => $thumbnail[1],
                    'height' => $thumbnail[2]
                );
            }
        }

        // Get custom fields (excluding private ones)
        $custom_fields = get_post_meta($post_id);
        if (is_array($custom_fields)) {
            $metadata['custom_fields'] = array();
            foreach ($custom_fields as $key => $value) {
                // Skip private meta fields (starting with _)
                if (strpos($key, '_') !== 0) {
                    $metadata['custom_fields'][$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
                }
            }
        }

        return $metadata;
    }

    /**
     * Get error result structure
     *
     * @param string $error_message
     * @return array
     */
    private function get_error_result($error_message) {
        return array(
            'success' => false,
            'error' => sanitize_text_field($error_message),
            'file_type' => '',
            'file_extension' => '',
            'metadata' => array(),
            'extracted_text' => '',
            'detected_objects' => array(),
            'analysis_method' => 'error'
        );
    }

    /**
     * Read file contents safely using WordPress filesystem
     *
     * @param string $file_path
     * @return string|false File contents or false on failure
     */
    protected function read_file($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->get_contents($file_path);
        }
        return file_get_contents($file_path);
    }

    /**
     * Get supported file types by all registered analyzers
     *
     * @return array Array of supported MIME types
     */
    public function get_supported_types() {
        $supported_types = array();
        foreach ($this->analyzers as $analyzer) {
            // This would need to be implemented by each analyzer
            // For now, return common types
        }
        
        // Return basic supported types
        return array(
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        );
    }
}