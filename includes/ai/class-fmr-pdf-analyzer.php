<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * PDF Content Analyzer Class
 * 
 * Analyzes PDF files using smalot/pdfparser library with WordPress-compatible
 * error handling and file system integration.
 */
class FMR_PDF_Analyzer implements FMR_File_Analyzer_Interface {

    /**
     * Supported PDF MIME types
     *
     * @var array
     */
    private $supported_types = array(
        'application/pdf'
    );

    /**
     * Maximum file size for PDF processing (in bytes)
     *
     * @var int
     */
    private $max_file_size;

    /**
     * Maximum text length to extract
     *
     * @var int
     */
    private $max_text_length;

    /**
     * PDF parser instance
     *
     * @var \Smalot\PdfParser\Parser|null
     */
    private $parser;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('fmrseo_options', array());
        $this->max_file_size = intval($options['pdf_max_file_size'] ?? 10 * 1024 * 1024); // 10MB default
        $this->max_text_length = intval($options['pdf_max_text_length'] ?? 5000); // 5000 chars default
        
        $this->init_pdf_parser();
    }

    /**
     * Initialize PDF parser
     */
    private function init_pdf_parser() {
        // Check if smalot/pdfparser is available
        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $this->parser = new \Smalot\PdfParser\Parser();
            } catch (Exception $e) {
                error_log('FMR PDF Analyzer: Failed to initialize PDF parser - ' . $e->getMessage());
                $this->parser = null;
            }
        } else {
            error_log('FMR PDF Analyzer: smalot/pdfparser library not found. Please install it via Composer.');
            $this->parser = null;
        }
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
     * Analyze PDF content
     *
     * @param string $file_path Full path to the file
     * @param int $post_id Media post ID
     * @return array Analysis results
     */
    public function analyze($file_path, $post_id) {
        $result = array(
            'extracted_text' => '',
            'detected_objects' => array(),
            'analysis_method' => 'pdf_metadata_only',
            'pdf_parsing_attempted' => false,
            'pdf_parsing_success' => false,
            'pdf_info' => array()
        );

        // Validate file
        $validation_result = $this->validate_pdf_file($file_path);
        if (!$validation_result['valid']) {
            $result['error'] = $validation_result['error'];
            return $result;
        }

        // Try PDF parsing if parser is available
        if ($this->parser) {
            try {
                $parsing_result = $this->parse_pdf_content($file_path);
                $result = array_merge($result, $parsing_result);
                $result['pdf_parsing_attempted'] = true;
                
                if ($parsing_result['success']) {
                    $result['analysis_method'] = 'pdf_parser';
                    $result['pdf_parsing_success'] = true;
                }
            } catch (Exception $e) {
                $result['pdf_parsing_attempted'] = true;
                $result['pdf_parsing_error'] = $e->getMessage();
                error_log('FMR PDF Analyzer parsing error: ' . $e->getMessage());
            }
        }

        // Fallback to WordPress metadata analysis
        if (!$result['pdf_parsing_success']) {
            $metadata_result = $this->analyze_pdf_metadata($file_path, $post_id);
            $result = array_merge($result, $metadata_result);
        }

        // Get basic PDF information
        $pdf_info = $this->get_pdf_info($file_path);
        $result['pdf_info'] = $pdf_info;

        return $result;
    }

    /**
     * Validate PDF file before processing
     *
     * @param string $file_path
     * @return array
     */
    private function validate_pdf_file($file_path) {
        // Check if file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array(
                'valid' => false,
                'error' => 'PDF file not found or not readable'
            );
        }

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size === false || $file_size > $this->max_file_size) {
            return array(
                'valid' => false,
                'error' => 'PDF file too large for processing'
            );
        }

        // Basic PDF header check
        $file_handle = fopen($file_path, 'rb');
        if ($file_handle === false) {
            return array(
                'valid' => false,
                'error' => 'Cannot open PDF file'
            );
        }

        $header = fread($file_handle, 8);
        fclose($file_handle);

        if (strpos($header, '%PDF-') !== 0) {
            return array(
                'valid' => false,
                'error' => 'Invalid PDF file format'
            );
        }

        return array('valid' => true);
    }

    /**
     * Parse PDF content using smalot/pdfparser
     *
     * @param string $file_path
     * @return array
     */
    private function parse_pdf_content($file_path) {
        try {
            // Parse the PDF
            $pdf = $this->parser->parseFile($file_path);
            
            // Extract text content
            $text = $pdf->getText();
            $text = $this->clean_extracted_text($text);
            
            // Limit text length
            if (strlen($text) > $this->max_text_length) {
                $text = substr($text, 0, $this->max_text_length) . '...';
            }

            // Extract metadata
            $details = $pdf->getDetails();
            
            // Extract objects/keywords from text
            $detected_objects = $this->extract_objects_from_text($text);

            return array(
                'success' => true,
                'extracted_text' => sanitize_textarea_field($text),
                'detected_objects' => $detected_objects,
                'pdf_metadata' => $this->sanitize_pdf_metadata($details),
                'page_count' => count($pdf->getPages())
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'PDF parsing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Clean extracted text from PDF
     *
     * @param string $text
     * @return string
     */
    private function clean_extracted_text($text) {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove control characters
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }

    /**
     * Extract objects/keywords from text content
     *
     * @param string $text
     * @return array
     */
    private function extract_objects_from_text($text) {
        $objects = array();
        $text_lower = strtolower($text);

        // Common document types and keywords
        $patterns = array(
            'invoice' => array('invoice', 'bill', 'payment', 'amount due'),
            'contract' => array('contract', 'agreement', 'terms', 'conditions'),
            'report' => array('report', 'analysis', 'summary', 'findings'),
            'manual' => array('manual', 'guide', 'instructions', 'how to'),
            'certificate' => array('certificate', 'certification', 'diploma'),
            'presentation' => array('presentation', 'slides', 'overview'),
            'brochure' => array('brochure', 'flyer', 'marketing'),
            'specification' => array('specification', 'specs', 'requirements'),
            'proposal' => array('proposal', 'quote', 'estimate'),
            'policy' => array('policy', 'procedure', 'guidelines')
        );

        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $objects[] = $category;
                    break;
                }
            }
        }

        // Extract potential company names (words in all caps)
        preg_match_all('/\b[A-Z]{2,}\b/', $text, $matches);
        if (!empty($matches[0])) {
            $company_names = array_slice(array_unique($matches[0]), 0, 3); // Limit to 3
            $objects = array_merge($objects, array_map('strtolower', $company_names));
        }

        return array_unique(array_map('sanitize_text_field', $objects));
    }

    /**
     * Sanitize PDF metadata
     *
     * @param array $metadata
     * @return array
     */
    private function sanitize_pdf_metadata($metadata) {
        $sanitized = array();
        
        $allowed_fields = array(
            'Title', 'Author', 'Subject', 'Keywords', 'Creator', 
            'Producer', 'CreationDate', 'ModDate'
        );

        foreach ($allowed_fields as $field) {
            if (isset($metadata[$field])) {
                $value = $metadata[$field];
                if (is_string($value)) {
                    $sanitized[strtolower($field)] = sanitize_text_field($value);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Analyze PDF using WordPress metadata when parser is not available
     *
     * @param string $file_path
     * @param int $post_id
     * @return array
     */
    private function analyze_pdf_metadata($file_path, $post_id) {
        $result = array(
            'extracted_text' => '',
            'detected_objects' => array()
        );

        // Extract text from WordPress metadata
        $post = get_post($post_id);
        if ($post) {
            $text_sources = array(
                $post->post_title,
                $post->post_content,
                $post->post_excerpt
            );

            $extracted_text = implode(' ', array_filter($text_sources));
            $result['extracted_text'] = sanitize_textarea_field($extracted_text);
        }

        // Try to detect document type from filename
        $filename = basename($file_path, '.pdf');
        $detected_objects = $this->detect_objects_from_filename($filename);
        $result['detected_objects'] = array_unique(array_map('sanitize_text_field', $detected_objects));

        return $result;
    }

    /**
     * Get basic PDF information
     *
     * @param string $file_path
     * @return array
     */
    private function get_pdf_info($file_path) {
        $info = array(
            'file_size' => filesize($file_path),
            'file_size_formatted' => size_format(filesize($file_path))
        );

        // Try to get PDF version from header
        $file_handle = fopen($file_path, 'rb');
        if ($file_handle !== false) {
            $header = fread($file_handle, 20);
            fclose($file_handle);
            
            if (preg_match('/%PDF-(\d\.\d)/', $header, $matches)) {
                $info['pdf_version'] = $matches[1];
            }
        }

        return $info;
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

        // Common PDF document patterns in filenames
        $patterns = array(
            'invoice' => array('invoice', 'bill', 'receipt'),
            'contract' => array('contract', 'agreement', 'terms'),
            'report' => array('report', 'analysis', 'summary'),
            'manual' => array('manual', 'guide', 'documentation'),
            'certificate' => array('certificate', 'cert', 'diploma'),
            'presentation' => array('presentation', 'slides', 'ppt'),
            'brochure' => array('brochure', 'flyer', 'leaflet'),
            'specification' => array('spec', 'specification', 'requirements'),
            'proposal' => array('proposal', 'quote', 'estimate'),
            'datasheet' => array('datasheet', 'specs', 'technical')
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
     * Check if PDF parser library is available
     *
     * @return bool
     */
    public static function is_parser_available() {
        return class_exists('\Smalot\PdfParser\Parser');
    }

    /**
     * Get installation instructions for PDF parser
     *
     * @return string
     */
    public static function get_installation_instructions() {
        return 'To enable PDF content extraction, install smalot/pdfparser via Composer: composer require smalot/pdfparser';
    }
}