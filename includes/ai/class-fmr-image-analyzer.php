<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Image Content Analyzer Class
 * 
 * Analyzes image files using OCR/Vision API with fallback to WordPress metadata
 * when external services fail.
 */
class FMR_Image_Analyzer implements FMR_File_Analyzer_Interface {

    /**
     * Supported image MIME types
     *
     * @var array
     */
    private $supported_types = array(
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/tiff'
    );

    /**
     * OCR/Vision API endpoint
     *
     * @var string
     */
    private $api_endpoint;

    /**
     * API timeout in seconds
     *
     * @var int
     */
    private $api_timeout;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('fmrseo_options', array());
        $this->api_endpoint = $options['ai_ocr_endpoint'] ?? '';
        $this->api_timeout = intval($options['ai_timeout'] ?? 30);
    }

    /**
     * Check if this analyzer supports the given file type
     *
     * @param string $mime_type File MIME type
     * @return bool True if supported
     */
    public function supports($mime_type) {
        return in_array(strtolower($mime_type), $this->supported_types);
    }

    /**
     * Analyze image content
     *
     * @param string $file_path Full path to the file
     * @param int $post_id Media post ID
     * @return array Analysis results
     */
    public function analyze($file_path, $post_id) {
        $result = array(
            'extracted_text' => '',
            'detected_objects' => array(),
            'analysis_method' => 'image_metadata_only',
            'ocr_attempted' => false,
            'ocr_success' => false
        );

        // First, try OCR/Vision API if configured
        if (!empty($this->api_endpoint)) {
            try {
                $ocr_result = $this->perform_ocr_analysis($file_path, $post_id);
                if ($ocr_result['success']) {
                    $result = array_merge($result, $ocr_result);
                    $result['analysis_method'] = 'ocr_vision_api';
                    $result['ocr_attempted'] = true;
                    $result['ocr_success'] = true;
                } else {
                    $result['ocr_attempted'] = true;
                    $result['ocr_error'] = $ocr_result['error'] ?? 'Unknown OCR error';
                }
            } catch (Exception $e) {
                $result['ocr_attempted'] = true;
                $result['ocr_error'] = $e->getMessage();
                error_log('FMR Image Analyzer OCR error: ' . $e->getMessage());
            }
        }

        // Fallback to WordPress image metadata analysis
        if (!$result['ocr_success']) {
            $metadata_result = $this->analyze_image_metadata($file_path, $post_id);
            $result = array_merge($result, $metadata_result);
        }

        // Extract basic image information
        $image_info = $this->get_image_info($file_path);
        $result['image_info'] = $image_info;

        return $result;
    }

    /**
     * Perform OCR/Vision analysis using external API
     *
     * @param string $file_path
     * @param int $post_id
     * @return array
     */
    private function perform_ocr_analysis($file_path, $post_id) {
        // Validate file size (limit to reasonable size for API)
        $max_file_size = 5 * 1024 * 1024; // 5MB
        if (filesize($file_path) > $max_file_size) {
            return array(
                'success' => false,
                'error' => 'File too large for OCR analysis'
            );
        }

        // Prepare image data for API
        $image_data = $this->prepare_image_for_api($file_path);
        if (!$image_data) {
            return array(
                'success' => false,
                'error' => 'Failed to prepare image for API'
            );
        }

        // Make API request
        $api_response = $this->make_ocr_api_request($image_data, $post_id);
        
        if ($api_response['success']) {
            return array(
                'success' => true,
                'extracted_text' => sanitize_textarea_field($api_response['text'] ?? ''),
                'detected_objects' => $this->sanitize_detected_objects($api_response['objects'] ?? array()),
                'confidence_score' => floatval($api_response['confidence'] ?? 0)
            );
        }

        return array(
            'success' => false,
            'error' => $api_response['error'] ?? 'API request failed'
        );
    }

    /**
     * Prepare image for API submission
     *
     * @param string $file_path
     * @return array|false
     */
    private function prepare_image_for_api($file_path) {
        // Read file contents
        $file_contents = file_get_contents($file_path);
        if ($file_contents === false) {
            return false;
        }

        // Get image info
        $image_info = getimagesize($file_path);
        if ($image_info === false) {
            return false;
        }

        return array(
            'image_data' => base64_encode($file_contents),
            'mime_type' => $image_info['mime'],
            'width' => $image_info[0],
            'height' => $image_info[1]
        );
    }

    /**
     * Make OCR API request using WordPress HTTP API
     *
     * @param array $image_data
     * @param int $post_id
     * @return array
     */
    private function make_ocr_api_request($image_data, $post_id) {
        $options = get_option('fmrseo_options', array());
        $api_key = $options['ai_api_key'] ?? '';

        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'API key not configured'
            );
        }

        // Prepare request data
        $request_data = array(
            'image' => $image_data['image_data'],
            'mime_type' => $image_data['mime_type'],
            'features' => array('text_detection', 'object_detection'),
            'post_id' => $post_id
        );

        // Prepare request arguments
        $args = array(
            'method' => 'POST',
            'timeout' => $this->api_timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'User-Agent' => 'WordPress-FMR-SEO/' . get_bloginfo('version')
            ),
            'body' => wp_json_encode($request_data),
            'sslverify' => true
        );

        // Make the request
        $response = wp_remote_request($this->api_endpoint, $args);

        // Handle WordPress HTTP API errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'HTTP request failed: ' . $response->get_error_message()
            );
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return array(
                'success' => false,
                'error' => 'API returned error code: ' . $response_code
            );
        }

        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        $parsed_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'Invalid JSON response from API'
            );
        }

        // Validate response structure
        if (!isset($parsed_response['success'])) {
            return array(
                'success' => false,
                'error' => 'Invalid response format from API'
            );
        }

        return $parsed_response;
    }

    /**
     * Analyze image using WordPress metadata and basic image functions
     *
     * @param string $file_path
     * @param int $post_id
     * @return array
     */
    private function analyze_image_metadata($file_path, $post_id) {
        $result = array(
            'extracted_text' => '',
            'detected_objects' => array()
        );

        // Try to extract text from WordPress metadata
        $post = get_post($post_id);
        if ($post) {
            $text_sources = array(
                $post->post_title,
                $post->post_content,
                $post->post_excerpt,
                get_post_meta($post_id, '_wp_attachment_image_alt', true)
            );

            $extracted_text = implode(' ', array_filter($text_sources));
            $result['extracted_text'] = sanitize_textarea_field($extracted_text);
        }

        // Try to detect objects based on filename and metadata
        $filename = basename($file_path, '.' . pathinfo($file_path, PATHINFO_EXTENSION));
        $detected_objects = $this->detect_objects_from_filename($filename);
        
        // Add objects from EXIF keywords if available
        $wp_metadata = wp_get_attachment_metadata($post_id);
        if (isset($wp_metadata['image_meta']['keywords']) && is_array($wp_metadata['image_meta']['keywords'])) {
            $detected_objects = array_merge($detected_objects, $wp_metadata['image_meta']['keywords']);
        }

        $result['detected_objects'] = array_unique(array_map('sanitize_text_field', $detected_objects));

        return $result;
    }

    /**
     * Get basic image information
     *
     * @param string $file_path
     * @return array
     */
    private function get_image_info($file_path) {
        $image_info = getimagesize($file_path);
        
        if ($image_info === false) {
            return array();
        }

        return array(
            'width' => $image_info[0],
            'height' => $image_info[1],
            'mime_type' => $image_info['mime'],
            'aspect_ratio' => round($image_info[0] / $image_info[1], 2),
            'orientation' => $image_info[0] > $image_info[1] ? 'landscape' : ($image_info[0] < $image_info[1] ? 'portrait' : 'square')
        );
    }

    /**
     * Detect objects from filename patterns
     *
     * @param string $filename
     * @return array
     */
    private function detect_objects_from_filename($filename) {
        $objects = array();
        $filename_lower = strtolower($filename);

        // Common object patterns in filenames
        $patterns = array(
            'logo' => array('logo', 'brand', 'company'),
            'person' => array('person', 'people', 'man', 'woman', 'portrait'),
            'product' => array('product', 'item', 'goods'),
            'building' => array('building', 'house', 'office', 'architecture'),
            'nature' => array('tree', 'flower', 'landscape', 'nature', 'outdoor'),
            'technology' => array('computer', 'phone', 'tech', 'device'),
            'food' => array('food', 'meal', 'restaurant', 'cooking'),
            'vehicle' => array('car', 'truck', 'vehicle', 'transport'),
            'document' => array('document', 'paper', 'form', 'certificate')
        );

        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($filename_lower, $keyword) !== false) {
                    $objects[] = $category;
                    break;
                }
            }
        }

        return array_unique($objects);
    }

    /**
     * Sanitize detected objects array
     *
     * @param array $objects
     * @return array
     */
    private function sanitize_detected_objects($objects) {
        if (!is_array($objects)) {
            return array();
        }

        $sanitized = array();
        foreach ($objects as $object) {
            if (is_string($object) || is_numeric($object)) {
                $sanitized[] = sanitize_text_field($object);
            } elseif (is_array($object) && isset($object['name'])) {
                $sanitized[] = sanitize_text_field($object['name']);
            }
        }

        return array_unique(array_filter($sanitized));
    }
}