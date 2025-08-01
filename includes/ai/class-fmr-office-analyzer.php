<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Office Document Content Analyzer Class
 * 
 * Analyzes Microsoft Office documents (docx, xlsx, pptx) using phpoffice libraries
 * with WordPress file system API integration and proper error handling.
 */
class FMR_Office_Analyzer implements FMR_File_Analyzer_Interface {

    /**
     * Supported Office document MIME types
     *
     * @var array
     */
    private $supported_types = array(
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',       // .xlsx
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
        'application/msword',                                                       // .doc (legacy)
        'application/vnd.ms-excel',                                                // .xls (legacy)
        'application/vnd.ms-powerpoint'                                            // .ppt (legacy)
    );

    /**
     * Maximum file size for Office document processing (in bytes)
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
     * WordPress filesystem instance
     *
     * @var WP_Filesystem_Base
     */
    private $filesystem;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('fmrseo_options', array());
        $this->max_file_size = intval($options['office_max_file_size'] ?? 20 * 1024 * 1024); // 20MB default
        $this->max_text_length = intval($options['office_max_text_length'] ?? 10000); // 10000 chars default
        
        $this->init_filesystem();
    }

    /**
     * Initialize WordPress filesystem
     */
    private function init_filesystem() {
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        if (!WP_Filesystem()) {
            $this->filesystem = null;
        } else {
            global $wp_filesystem;
            $this->filesystem = $wp_filesystem;
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
     * Analyze Office document content
     *
     * @param string $file_path Full path to the file
     * @param int $post_id Media post ID
     * @return array Analysis results
     */
    public function analyze($file_path, $post_id) {
        $result = array(
            'extracted_text' => '',
            'detected_objects' => array(),
            'analysis_method' => 'office_metadata_only',
            'office_parsing_attempted' => false,
            'office_parsing_success' => false,
            'document_info' => array()
        );

        // Validate file
        $validation_result = $this->validate_office_file($file_path);
        if (!$validation_result['valid']) {
            $result['error'] = $validation_result['error'];
            return $result;
        }

        // Determine document type
        $mime_type = wp_check_filetype($file_path)['type'];
        $document_type = $this->get_document_type($mime_type);

        // Try Office document parsing if libraries are available
        if ($this->is_phpoffice_available($document_type)) {
            try {
                $parsing_result = $this->parse_office_document($file_path, $document_type);
                $result = array_merge($result, $parsing_result);
                $result['office_parsing_attempted'] = true;
                
                if ($parsing_result['success']) {
                    $result['analysis_method'] = 'phpoffice_' . $document_type;
                    $result['office_parsing_success'] = true;
                }
            } catch (Exception $e) {
                $result['office_parsing_attempted'] = true;
                $result['office_parsing_error'] = $e->getMessage();
                error_log('FMR Office Analyzer parsing error: ' . $e->getMessage());
            }
        }

        // Fallback to WordPress metadata analysis
        if (!$result['office_parsing_success']) {
            $metadata_result = $this->analyze_office_metadata($file_path, $post_id);
            $result = array_merge($result, $metadata_result);
        }

        // Get basic document information
        $document_info = $this->get_document_info($file_path, $document_type);
        $result['document_info'] = $document_info;

        return $result;
    }

    /**
     * Validate Office document file before processing
     *
     * @param string $file_path
     * @return array
     */
    private function validate_office_file($file_path) {
        // Check if file exists and is readable
        if (!$this->file_exists($file_path) || !$this->is_file_readable($file_path)) {
            return array(
                'valid' => false,
                'error' => 'Office document not found or not readable'
            );
        }

        // Check file size
        $file_size = $this->get_file_size($file_path);
        if ($file_size === false || $file_size > $this->max_file_size) {
            return array(
                'valid' => false,
                'error' => 'Office document too large for processing'
            );
        }

        return array('valid' => true);
    }

    /**
     * Get document type from MIME type
     *
     * @param string $mime_type
     * @return string
     */
    private function get_document_type($mime_type) {
        $type_mapping = array(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word',
            'application/msword' => 'word',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'powerpoint',
            'application/vnd.ms-powerpoint' => 'powerpoint'
        );

        return $type_mapping[$mime_type] ?? 'unknown';
    }

    /**
     * Check if PHPOffice libraries are available for the document type
     *
     * @param string $document_type
     * @return bool
     */
    private function is_phpoffice_available($document_type) {
        switch ($document_type) {
            case 'word':
                return class_exists('\PhpOffice\PhpWord\IOFactory');
            case 'excel':
                return class_exists('\PhpOffice\PhpSpreadsheet\IOFactory');
            case 'powerpoint':
                return class_exists('\PhpOffice\PhpPresentation\IOFactory');
            default:
                return false;
        }
    }

    /**
     * Parse Office document using appropriate PHPOffice library
     *
     * @param string $file_path
     * @param string $document_type
     * @return array
     */
    private function parse_office_document($file_path, $document_type) {
        switch ($document_type) {
            case 'word':
                return $this->parse_word_document($file_path);
            case 'excel':
                return $this->parse_excel_document($file_path);
            case 'powerpoint':
                return $this->parse_powerpoint_document($file_path);
            default:
                return array(
                    'success' => false,
                    'error' => 'Unsupported document type: ' . $document_type
                );
        }
    }

    /**
     * Parse Word document using PhpWord
     *
     * @param string $file_path
     * @return array
     */
    private function parse_word_document($file_path) {
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_path);
            
            $text = '';
            $detected_objects = array();

            // Extract text from all sections
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $element_text = $this->extract_text_from_element($element);
                    $text .= $element_text . ' ';
                }
            }

            // Clean and limit text
            $text = $this->clean_extracted_text($text);
            if (strlen($text) > $this->max_text_length) {
                $text = substr($text, 0, $this->max_text_length) . '...';
            }

            // Extract objects/keywords from text
            $detected_objects = $this->extract_objects_from_text($text, 'word');

            // Get document properties
            $properties = $phpWord->getDocInfo();
            $document_metadata = array(
                'title' => $properties->getTitle(),
                'subject' => $properties->getSubject(),
                'description' => $properties->getDescription(),
                'keywords' => $properties->getKeywords(),
                'creator' => $properties->getCreator(),
                'last_modified_by' => $properties->getLastModifiedBy()
            );

            return array(
                'success' => true,
                'extracted_text' => sanitize_textarea_field($text),
                'detected_objects' => $detected_objects,
                'document_metadata' => $this->sanitize_document_metadata($document_metadata)
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Word document parsing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Parse Excel document using PhpSpreadsheet
     *
     * @param string $file_path
     * @return array
     */
    private function parse_excel_document($file_path) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            
            $text = '';
            $detected_objects = array('spreadsheet', 'data', 'table');

            // Extract text from all worksheets
            foreach ($spreadsheet->getAllSheets() as $worksheet) {
                $sheet_title = $worksheet->getTitle();
                $text .= $sheet_title . ' ';

                // Get highest row and column
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();

                // Limit to reasonable range to avoid memory issues
                $maxRows = min($highestRow, 100);
                $maxCols = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn), 20);

                // Extract cell values
                for ($row = 1; $row <= $maxRows; $row++) {
                    for ($col = 1; $col <= $maxCols; $col++) {
                        $cell = $worksheet->getCellByColumnAndRow($col, $row);
                        $value = $cell->getCalculatedValue();
                        if (!empty($value) && is_string($value)) {
                            $text .= $value . ' ';
                        }
                    }
                }
            }

            // Clean and limit text
            $text = $this->clean_extracted_text($text);
            if (strlen($text) > $this->max_text_length) {
                $text = substr($text, 0, $this->max_text_length) . '...';
            }

            // Extract additional objects from text
            $text_objects = $this->extract_objects_from_text($text, 'excel');
            $detected_objects = array_merge($detected_objects, $text_objects);

            // Get document properties
            $properties = $spreadsheet->getProperties();
            $document_metadata = array(
                'title' => $properties->getTitle(),
                'subject' => $properties->getSubject(),
                'description' => $properties->getDescription(),
                'keywords' => $properties->getKeywords(),
                'creator' => $properties->getCreator(),
                'last_modified_by' => $properties->getLastModifiedBy()
            );

            return array(
                'success' => true,
                'extracted_text' => sanitize_textarea_field($text),
                'detected_objects' => array_unique($detected_objects),
                'document_metadata' => $this->sanitize_document_metadata($document_metadata),
                'sheet_count' => $spreadsheet->getSheetCount()
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Excel document parsing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Parse PowerPoint document using PhpPresentation
     *
     * @param string $file_path
     * @return array
     */
    private function parse_powerpoint_document($file_path) {
        try {
            $presentation = \PhpOffice\PhpPresentation\IOFactory::load($file_path);
            
            $text = '';
            $detected_objects = array('presentation', 'slides');

            // Extract text from all slides
            foreach ($presentation->getAllSlides() as $slide) {
                foreach ($slide->getShapeCollection() as $shape) {
                    if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                        $shape_text = $shape->getPlainText();
                        $text .= $shape_text . ' ';
                    }
                }
            }

            // Clean and limit text
            $text = $this->clean_extracted_text($text);
            if (strlen($text) > $this->max_text_length) {
                $text = substr($text, 0, $this->max_text_length) . '...';
            }

            // Extract additional objects from text
            $text_objects = $this->extract_objects_from_text($text, 'powerpoint');
            $detected_objects = array_merge($detected_objects, $text_objects);

            // Get document properties
            $properties = $presentation->getDocumentProperties();
            $document_metadata = array(
                'title' => $properties->getTitle(),
                'subject' => $properties->getSubject(),
                'description' => $properties->getDescription(),
                'keywords' => $properties->getKeywords(),
                'creator' => $properties->getCreator(),
                'last_modified_by' => $properties->getLastModifiedBy()
            );

            return array(
                'success' => true,
                'extracted_text' => sanitize_textarea_field($text),
                'detected_objects' => array_unique($detected_objects),
                'document_metadata' => $this->sanitize_document_metadata($document_metadata),
                'slide_count' => $presentation->getSlideCount()
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'PowerPoint document parsing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Extract text from PhpWord element
     *
     * @param mixed $element
     * @return string
     */
    private function extract_text_from_element($element) {
        $text = '';
        
        if (method_exists($element, 'getText')) {
            $text = $element->getText();
        } elseif (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child_element) {
                $text .= $this->extract_text_from_element($child_element);
            }
        }
        
        return $text;
    }

    /**
     * Clean extracted text from Office documents
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
     * Extract objects/keywords from text content based on document type
     *
     * @param string $text
     * @param string $document_type
     * @return array
     */
    private function extract_objects_from_text($text, $document_type) {
        $objects = array();
        $text_lower = strtolower($text);

        // Common patterns based on document type
        $patterns = array();
        
        switch ($document_type) {
            case 'word':
                $patterns = array(
                    'report' => array('report', 'analysis', 'summary'),
                    'letter' => array('dear', 'sincerely', 'regards'),
                    'manual' => array('manual', 'guide', 'instructions'),
                    'contract' => array('contract', 'agreement', 'terms'),
                    'proposal' => array('proposal', 'recommendation')
                );
                break;
                
            case 'excel':
                $patterns = array(
                    'budget' => array('budget', 'cost', 'expense'),
                    'inventory' => array('inventory', 'stock', 'quantity'),
                    'sales' => array('sales', 'revenue', 'profit'),
                    'schedule' => array('schedule', 'timeline', 'date'),
                    'analysis' => array('analysis', 'data', 'statistics')
                );
                break;
                
            case 'powerpoint':
                $patterns = array(
                    'training' => array('training', 'course', 'learning'),
                    'marketing' => array('marketing', 'campaign', 'promotion'),
                    'business' => array('business', 'strategy', 'plan'),
                    'overview' => array('overview', 'introduction', 'summary'),
                    'results' => array('results', 'performance', 'metrics')
                );
                break;
        }

        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $objects[] = $category;
                    break;
                }
            }
        }

        return array_unique(array_map('sanitize_text_field', $objects));
    }

    /**
     * Sanitize document metadata
     *
     * @param array $metadata
     * @return array
     */
    private function sanitize_document_metadata($metadata) {
        $sanitized = array();
        
        foreach ($metadata as $key => $value) {
            if (is_string($value) && !empty($value)) {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }

        return $sanitized;
    }

    /**
     * Analyze Office document using WordPress metadata when libraries are not available
     *
     * @param string $file_path
     * @param int $post_id
     * @return array
     */
    private function analyze_office_metadata($file_path, $post_id) {
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
        $filename = basename($file_path);
        $detected_objects = $this->detect_objects_from_filename($filename);
        $result['detected_objects'] = array_unique(array_map('sanitize_text_field', $detected_objects));

        return $result;
    }

    /**
     * Get basic document information
     *
     * @param string $file_path
     * @param string $document_type
     * @return array
     */
    private function get_document_info($file_path, $document_type) {
        return array(
            'document_type' => $document_type,
            'file_size' => $this->get_file_size($file_path),
            'file_size_formatted' => size_format($this->get_file_size($file_path)),
            'file_extension' => pathinfo($file_path, PATHINFO_EXTENSION)
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

        // Common Office document patterns in filenames
        $patterns = array(
            'report' => array('report', 'analysis', 'summary'),
            'presentation' => array('presentation', 'slides', 'ppt'),
            'spreadsheet' => array('spreadsheet', 'data', 'excel'),
            'template' => array('template', 'form', 'blank'),
            'manual' => array('manual', 'guide', 'documentation'),
            'proposal' => array('proposal', 'quote', 'estimate'),
            'budget' => array('budget', 'financial', 'cost'),
            'schedule' => array('schedule', 'timeline', 'calendar'),
            'invoice' => array('invoice', 'bill', 'receipt'),
            'contract' => array('contract', 'agreement', 'terms')
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
     * WordPress filesystem helper methods
     */
    private function file_exists($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->exists($file_path);
        }
        return file_exists($file_path);
    }

    private function is_file_readable($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->is_readable($file_path);
        }
        return is_readable($file_path);
    }

    private function get_file_size($file_path) {
        if ($this->filesystem) {
            return $this->filesystem->size($file_path);
        }
        return filesize($file_path);
    }

    /**
     * Check if PHPOffice libraries are available
     *
     * @return array
     */
    public static function get_library_status() {
        return array(
            'phpword' => class_exists('\PhpOffice\PhpWord\IOFactory'),
            'phpspreadsheet' => class_exists('\PhpOffice\PhpSpreadsheet\IOFactory'),
            'phppresentation' => class_exists('\PhpOffice\PhpPresentation\IOFactory')
        );
    }

    /**
     * Get installation instructions for PHPOffice libraries
     *
     * @return array
     */
    public static function get_installation_instructions() {
        return array(
            'phpword' => 'composer require phpoffice/phpword',
            'phpspreadsheet' => 'composer require phpoffice/phpspreadsheet',
            'phppresentation' => 'composer require phpoffice/phppresentation'
        );
    }
}