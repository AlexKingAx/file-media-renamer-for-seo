<?php

/**
 * Unit Tests for Content Analyzers
 * 
 * Tests all content analyzer classes including image, PDF, and Office document analyzers.
 */

use PHPUnit\Framework\TestCase;

class ContentAnalyzerTest extends TestCase
{

    private $image_analyzer;
    private $pdf_analyzer;
    private $office_analyzer;
    private $test_file_path;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize analyzers
        $this->image_analyzer = new FMR_Image_Analyzer();
        $this->pdf_analyzer = new FMR_PDF_Analyzer();
        $this->office_analyzer = new FMR_Office_Analyzer();

        // Set up test file path
        $this->test_file_path = __DIR__ . '/fixtures/';

        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }

    private function mockWordPressFunctions()
    {
        if (!function_exists('wp_get_attachment_metadata')) {
            function wp_get_attachment_metadata($attachment_id)
            {
                return [
                    'width' => 1920,
                    'height' => 1080,
                    'file' => 'uploads/2024/01/test-image.jpg',
                    'image_meta' => [
                        'camera' => 'Canon EOS R5',
                        'keywords' => ['nature', 'landscape']
                    ]
                ];
            }
        }

        if (!function_exists('get_attached_file')) {
            function get_attached_file($attachment_id)
            {
                return '/path/to/uploads/test-file.jpg';
            }
        }

        if (!function_exists('wp_check_filetype')) {
            function wp_check_filetype($filename)
            {
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                return [
                    'ext' => $ext,
                    'type' => 'image/jpeg'
                ];
            }
        }
    }

    /**
     * Test image analyzer initialization
     */
    public function testImageAnalyzerInitialization()
    {
        $this->assertInstanceOf('FMR_Image_Analyzer', $this->image_analyzer);
        $this->assertTrue(method_exists($this->image_analyzer, 'analyze'));
        $this->assertTrue(method_exists($this->image_analyzer, 'extract_text'));
    }

    /**
     * Test image content analysis
     */
    public function testImageContentAnalysis()
    {
        $attachment_id = 123;
        $result = $this->image_analyzer->analyze($attachment_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('file_info', $result);
    }

    /**
     * Test image OCR text extraction
     */
    public function testImageOCRTextExtraction()
    {
        $file_path = $this->test_file_path . 'test-image.jpg';

        // Mock OCR response
        $mock_ocr_response = [
            'text' => 'Sample text extracted from image',
            'confidence' => 0.95
        ];

        $result = $this->image_analyzer->extract_text($file_path);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    /**
     * Test PDF analyzer initialization
     */
    public function testPDFAnalyzerInitialization()
    {
        $this->assertInstanceOf('FMR_PDF_Analyzer', $this->pdf_analyzer);
        $this->assertTrue(method_exists($this->pdf_analyzer, 'analyze'));
        $this->assertTrue(method_exists($this->pdf_analyzer, 'extract_text'));
    }

    /**
     * Test PDF content extraction
     */
    public function testPDFContentExtraction()
    {
        $attachment_id = 124;
        $result = $this->pdf_analyzer->analyze($attachment_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('page_count', $result);
    }

    /**
     * Test PDF text extraction
     */
    public function testPDFTextExtraction()
    {
        $file_path = $this->test_file_path . 'test-document.pdf';

        $result = $this->pdf_analyzer->extract_text($file_path);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test Office analyzer initialization
     */
    public function testOfficeAnalyzerInitialization()
    {
        $this->assertInstanceOf('FMR_Office_Analyzer', $this->office_analyzer);
        $this->assertTrue(method_exists($this->office_analyzer, 'analyze'));
        $this->assertTrue(method_exists($this->office_analyzer, 'extract_text'));
    }

    /**
     * Test Office document content extraction
     */
    public function testOfficeDocumentExtraction()
    {
        $attachment_id = 125;
        $result = $this->office_analyzer->analyze($attachment_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertArrayHasKey('document_type', $result);
    }

    /**
     * Test Word document text extraction
     */
    public function testWordDocumentTextExtraction()
    {
        $file_path = $this->test_file_path . 'test-document.docx';

        $result = $this->office_analyzer->extract_text($file_path);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test Excel document data extraction
     */
    public function testExcelDocumentDataExtraction()
    {
        $file_path = $this->test_file_path . 'test-spreadsheet.xlsx';

        $result = $this->office_analyzer->extract_data($file_path);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sheets', $result);
        $this->assertArrayHasKey('data', $result);
    }

    /**
     * Test PowerPoint presentation extraction
     */
    public function testPowerPointExtraction()
    {
        $file_path = $this->test_file_path . 'test-presentation.pptx';

        $result = $this->office_analyzer->extract_text($file_path);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
    /**

     * Test error handling for unsupported file types
     */
    public function testUnsupportedFileTypeHandling()
    {
        $attachment_id = 126; // Assume this is an unsupported file type

        $result = $this->image_analyzer->analyze($attachment_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('unsupported', strtolower($result['error']));
    }

    /**
     * Test error handling for corrupted files
     */
    public function testCorruptedFileHandling()
    {
        $file_path = $this->test_file_path . 'corrupted-file.pdf';

        $result = $this->pdf_analyzer->extract_text($file_path);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test analyzer performance with large files
     */
    public function testAnalyzerPerformanceWithLargeFiles()
    {
        $start_time = microtime(true);

        // Simulate large file analysis
        $attachment_id = 127;
        $result = $this->pdf_analyzer->analyze($attachment_id);

        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;

        // Should complete within reasonable time
        $this->assertLessThan(5.0, $execution_time, 'Large file analysis should complete within 5 seconds');
    }

    /**
     * Test content filtering and sanitization
     */
    public function testContentFilteringAndSanitization()
    {
        $raw_content = "Normal text <script>alert('xss')</script> more text";

        $filtered_content = $this->image_analyzer->sanitize_content($raw_content);

        $this->assertIsString($filtered_content);
        $this->assertStringNotContainsString('<script>', $filtered_content);
        $this->assertStringContainsString('Normal text', $filtered_content);
    }

    /**
     * Test metadata extraction consistency
     */
    public function testMetadataExtractionConsistency()
    {
        $attachment_id = 128;

        // Extract metadata multiple times
        $metadata1 = $this->image_analyzer->extract_metadata($attachment_id);
        $metadata2 = $this->image_analyzer->extract_metadata($attachment_id);

        $this->assertEquals($metadata1, $metadata2);
    }

    /**
     * Test analyzer factory pattern
     */
    public function testAnalyzerFactory()
    {
        $factory = new FMR_Analyzer_Factory();

        $image_analyzer = $factory->create_analyzer('image/jpeg');
        $pdf_analyzer = $factory->create_analyzer('application/pdf');
        $word_analyzer = $factory->create_analyzer('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $this->assertInstanceOf('FMR_Image_Analyzer', $image_analyzer);
        $this->assertInstanceOf('FMR_PDF_Analyzer', $pdf_analyzer);
        $this->assertInstanceOf('FMR_Office_Analyzer', $word_analyzer);
    }

    /**
     * Test analyzer caching mechanism
     */
    public function testAnalyzerCaching()
    {
        $attachment_id = 129;

        // First analysis
        $result1 = $this->image_analyzer->analyze($attachment_id);

        // Second analysis (should use cache)
        $result2 = $this->image_analyzer->analyze($attachment_id);

        $this->assertEquals($result1, $result2);
        $this->assertTrue($this->image_analyzer->was_cache_used());
    }

    /**
     * Test batch processing capability
     */
    public function testBatchProcessing()
    {
        $attachment_ids = [130, 131, 132];

        $results = $this->image_analyzer->analyze_batch($attachment_ids);

        $this->assertIsArray($results);
        $this->assertCount(3, $results);

        foreach ($results as $result) {
            $this->assertArrayHasKey('content', $result);
            $this->assertArrayHasKey('metadata', $result);
        }
    }
}
