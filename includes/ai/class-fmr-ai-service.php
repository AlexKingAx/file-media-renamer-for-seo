<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * AI Service Class
 * 
 * Handles communication with external AI API for generating
 * SEO-optimized media file names.
 */
class FMR_AI_Service {

    /**
     * @var string Default API endpoint
     */
    private $default_endpoint = 'https://api.example.com/v1/generate-names';

    /**
     * @var int Default timeout in seconds
     */
    private $default_timeout = 30;

    /**
     * @var int Default max retries
     */
    private $default_max_retries = 2;

    /**
     * Generate SEO-optimized names using AI
     *
     * @param array $content Content analysis data
     * @param array $context Context extraction data
     * @param int $count Number of names to generate
     * @return array Array of suggested names
     * @throws Exception
     */
    public function generate_names($content, $context, $count = 3) {
        // Validate inputs
        if (empty($content) && empty($context)) {
            throw new Exception(__('No content or context available for AI processing.', 'fmrseo'));
        }

        // Build prompt
        $prompt = $this->build_prompt($content, $context);

        // Make API request
        $response = $this->make_api_request($prompt, $count);

        // Parse and validate response
        $names = $this->parse_response($response);

        if (empty($names)) {
            throw new Exception(__('AI service returned no valid suggestions.', 'fmrseo'));
        }

        return array_slice($names, 0, $count);
    }

    /**
     * Test connection to AI service
     *
     * @return bool True if connection successful
     */
    public function test_connection() {
        try {
            $test_prompt = "Test connection";
            $response = $this->make_api_request($test_prompt, 1, true);
            return !empty($response);
        } catch (Exception $e) {
            error_log('FMR AI Service connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build AI prompt from content and context
     *
     * @param array $content Content analysis data
     * @param array $context Context extraction data
     * @return string Generated prompt
     */
    private function build_prompt($content, $context) {
        $template = $this->get_prompt_template();
        
        // Prepare content summary
        $content_summary = $this->summarize_content($content);
        
        // Prepare context summary
        $context_summary = $this->summarize_context($context);
        
        // Replace placeholders in template
        $prompt = str_replace(
            array('{content}', '{context}'),
            array($content_summary, $context_summary),
            $template
        );

        return $prompt;
    }

    /**
     * Make API request to AI service
     *
     * @param string $prompt The prompt to send
     * @param int $count Number of suggestions requested
     * @param bool $is_test Whether this is a connection test
     * @return array API response data
     * @throws Exception
     */
    private function make_api_request($prompt, $count = 3, $is_test = false) {
        $options = get_option('fmrseo_options', array());
        
        // Get API configuration
        $api_key = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        $endpoint = isset($options['ai_api_endpoint']) ? $options['ai_api_endpoint'] : $this->default_endpoint;
        $timeout = isset($options['ai_timeout']) ? intval($options['ai_timeout']) : $this->default_timeout;
        $max_retries = isset($options['ai_max_retries']) ? intval($options['ai_max_retries']) : $this->default_max_retries;

        if (empty($api_key)) {
            throw new Exception(__('AI API key not configured.', 'fmrseo'));
        }

        // Prepare request data
        $request_data = array(
            'prompt' => $prompt,
            'count' => $count,
            'max_length' => 50, // Reasonable filename length
            'format' => 'seo_filename'
        );

        // Prepare request arguments
        $args = array(
            'method' => 'POST',
            'timeout' => $timeout,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'WordPress-FMR-SEO/' . get_bloginfo('version')
            ),
            'body' => wp_json_encode($request_data)
        );

        // Attempt request with retries
        $last_error = null;
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            try {
                $response = wp_remote_request($endpoint, $args);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);

                if ($response_code !== 200) {
                    throw new Exception(sprintf(
                        __('API request failed with status %d: %s', 'fmrseo'),
                        $response_code,
                        $response_body
                    ));
                }

                $data = json_decode($response_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception(__('Invalid JSON response from AI service.', 'fmrseo'));
                }

                return $data;

            } catch (Exception $e) {
                $last_error = $e;
                
                // Log attempt
                error_log(sprintf(
                    'FMR AI Service attempt %d/%d failed: %s',
                    $attempt,
                    $max_retries,
                    $e->getMessage()
                ));

                // Wait before retry (exponential backoff)
                if ($attempt < $max_retries) {
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        // All attempts failed
        throw new Exception(sprintf(
            __('AI service failed after %d attempts. Last error: %s', 'fmrseo'),
            $max_retries,
            $last_error->getMessage()
        ));
    }

    /**
     * Parse API response and extract names
     *
     * @param array $response API response data
     * @return array Array of suggested names
     * @throws Exception
     */
    private function parse_response($response) {
        // Use security manager for response sanitization
        if (class_exists('FMR_Security_Manager')) {
            $security_manager = new FMR_Security_Manager();
            $sanitized_response = $security_manager->sanitize_api_response($response);
            
            if (isset($sanitized_response['suggestions'])) {
                return $sanitized_response['suggestions'];
            }
        }

        // Fallback to original method if security manager is not available
        if (!isset($response['suggestions']) || !is_array($response['suggestions'])) {
            throw new Exception(__('Invalid response format from AI service.', 'fmrseo'));
        }

        $names = array();
        foreach ($response['suggestions'] as $suggestion) {
            if (is_string($suggestion)) {
                $name = $suggestion;
            } elseif (is_array($suggestion) && isset($suggestion['name'])) {
                $name = $suggestion['name'];
            } else {
                continue;
            }

            // Sanitize and validate name
            $name = $this->sanitize_ai_name($name);
            if (!empty($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Sanitize AI-generated name for WordPress filename use
     *
     * @param string $name Raw AI-generated name
     * @return string Sanitized name
     */
    private function sanitize_ai_name($name) {
        // Remove any path separators and dangerous characters
        $name = basename($name);
        
        // Remove file extensions if present
        $name = pathinfo($name, PATHINFO_FILENAME);
        
        // Use WordPress sanitization
        $name = sanitize_file_name($name);
        
        // Additional cleanup for SEO-friendly names
        $name = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-');
        
        // Ensure reasonable length
        if (strlen($name) > 50) {
            $name = substr($name, 0, 50);
            $name = rtrim($name, '-');
        }
        
        // Ensure minimum length
        if (strlen($name) < 3) {
            return '';
        }

        return strtolower($name);
    }

    /**
     * Get default prompt template
     *
     * @return string Prompt template
     */
    private function get_prompt_template() {
        $options = get_option('fmrseo_options', array());
        
        if (!empty($options['ai_prompt_template'])) {
            return $options['ai_prompt_template'];
        }

        return $this->get_default_prompt_template();
    }

    /**
     * Get default prompt template
     *
     * @return string Default prompt template
     */
    private function get_default_prompt_template() {
        return "Generate SEO-optimized filename suggestions for a media file based on the following information:\n\n" .
               "Content Analysis: {content}\n\n" .
               "Page Context: {context}\n\n" .
               "Requirements:\n" .
               "- Generate 1-3 short, descriptive filenames\n" .
               "- Use hyphens to separate words\n" .
               "- Focus on SEO keywords and relevance\n" .
               "- Keep names under 50 characters\n" .
               "- Use only lowercase letters, numbers, and hyphens\n" .
               "- Make names descriptive and meaningful\n\n" .
               "Return only the filename suggestions without extensions.";
    }

    /**
     * Summarize content analysis data
     *
     * @param array $content Content data
     * @return string Content summary
     */
    private function summarize_content($content) {
        $summary_parts = array();

        if (!empty($content['file_type'])) {
            $summary_parts[] = "File type: " . $content['file_type'];
        }

        if (!empty($content['extracted_text'])) {
            $text = substr($content['extracted_text'], 0, 200);
            $summary_parts[] = "Extracted text: " . $text;
        }

        if (!empty($content['metadata']['title'])) {
            $summary_parts[] = "Title: " . $content['metadata']['title'];
        }

        if (!empty($content['metadata']['alt_text'])) {
            $summary_parts[] = "Alt text: " . $content['metadata']['alt_text'];
        }

        if (!empty($content['detected_objects'])) {
            $summary_parts[] = "Detected objects: " . implode(', ', $content['detected_objects']);
        }

        return empty($summary_parts) ? "No content analysis available" : implode(". ", $summary_parts);
    }

    /**
     * Summarize context extraction data
     *
     * @param array $context Context data
     * @return string Context summary
     */
    private function summarize_context($context) {
        $summary_parts = array();

        if (!empty($context['page_titles'])) {
            $titles = array_slice($context['page_titles'], 0, 3);
            $summary_parts[] = "Page titles: " . implode(', ', $titles);
        }

        if (!empty($context['seo_keywords'])) {
            $keywords = array_slice($context['seo_keywords'], 0, 5);
            $summary_parts[] = "SEO keywords: " . implode(', ', $keywords);
        }

        if (!empty($context['headings'])) {
            $headings = array_slice($context['headings'], 0, 3);
            $summary_parts[] = "Headings: " . implode(', ', $headings);
        }

        if (!empty($context['categories'])) {
            $categories = array_slice($context['categories'], 0, 3);
            $summary_parts[] = "Categories: " . implode(', ', $categories);
        }

        return empty($summary_parts) ? "No context available" : implode(". ", $summary_parts);
    }
}