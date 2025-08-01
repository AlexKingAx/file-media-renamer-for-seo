<?php
/**
 * Test Suite Runner for FMR AI Components
 * 
 * Runs all unit tests for the AI media renaming functionality.
 */

use PHPUnit\Framework\TestSuite;

class FMRAITestSuite {
    
    /**
     * Create and return the complete test suite
     */
    public static function suite() {
        $suite = new TestSuite('FMR AI Media Renaming Test Suite');
        
        // Add all test classes
        $suite->addTestSuite('ContextExtractorTest');
        $suite->addTestSuite('ContentAnalyzerTest');
        $suite->addTestSuite('CreditManagerTest');
        $suite->addTestSuite('AIServiceTest');
        $suite->addTestSuite('IntegrationTest');
        
        return $suite;
    }
    
    /**
     * Run all tests and return results
     */
    public static function runAllTests() {
        $results = [];
        
        // Include test files
        $test_files = [
            'ContextExtractorTest' => __DIR__ . '/ContextExtractorTest.php',
            'ContentAnalyzerTest' => __DIR__ . '/ContentAnalyzerTest.php',
            'CreditManagerTest' => __DIR__ . '/CreditManagerTest.php',
            'AIServiceTest' => __DIR__ . '/AIServiceTest.php',
            'IntegrationTest' => __DIR__ . '/IntegrationTest.php'
        ];
        
        foreach ($test_files as $class_name => $file_path) {
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Run Context Extractor Tests
        echo "Running Context Extractor Tests...\n";
        $results['context_extractor'] = self::runTestClass('ContextExtractorTest');
        
        // Run Content Analyzer Tests
        echo "Running Content Analyzer Tests...\n";
        $results['content_analyzer'] = self::runTestClass('ContentAnalyzerTest');
        
        // Run Credit Manager Tests
        echo "Running Credit Manager Tests...\n";
        $results['credit_manager'] = self::runTestClass('CreditManagerTest');
        
        // Run AI Service Tests
        echo "Running AI Service Tests...\n";
        $results['ai_service'] = self::runTestClass('AIServiceTest');
        
        // Run Integration Tests
        echo "Running Integration Tests...\n";
        $results['integration'] = self::runTestClass('IntegrationTest');
        
        return $results;
    }
    
    /**
     * Run a specific test class
     */
    private static function runTestClass($className) {
        try {
            $testClass = new $className();
            $reflection = new ReflectionClass($testClass);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            
            $results = [
                'total' => 0,
                'passed' => 0,
                'failed' => 0,
                'errors' => []
            ];
            
            foreach ($methods as $method) {
                if (strpos($method->getName(), 'test') === 0) {
                    $results['total']++;
                    
                    try {
                        $testClass->setUp();
                        $method->invoke($testClass);
                        $testClass->tearDown();
                        $results['passed']++;
                        echo "  ✓ " . $method->getName() . "\n";
                    } catch (Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'method' => $method->getName(),
                            'error' => $e->getMessage()
                        ];
                        echo "  ✗ " . $method->getName() . " - " . $e->getMessage() . "\n";
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return [
                'total' => 0,
                'passed' => 0,
                'failed' => 1,
                'errors' => [['method' => 'Class Loading', 'error' => $e->getMessage()]]
            ];
        }
    }
    
    /**
     * Generate test coverage report
     */
    public static function generateCoverageReport($results) {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "TEST COVERAGE REPORT\n";
        echo str_repeat("=", 60) . "\n";
        
        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        
        foreach ($results as $component => $result) {
            $totalTests += $result['total'];
            $totalPassed += $result['passed'];
            $totalFailed += $result['failed'];
            
            $percentage = $result['total'] > 0 ? round(($result['passed'] / $result['total']) * 100, 2) : 0;
            
            echo sprintf(
                "%-20s: %d/%d tests passed (%.2f%%)\n",
                ucwords(str_replace('_', ' ', $component)),
                $result['passed'],
                $result['total'],
                $percentage
            );
            
            if (!empty($result['errors'])) {
                echo "  Errors:\n";
                foreach ($result['errors'] as $error) {
                    echo "    - " . $error['method'] . ": " . $error['error'] . "\n";
                }
            }
        }
        
        echo str_repeat("-", 60) . "\n";
        $overallPercentage = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 2) : 0;
        echo sprintf(
            "OVERALL: %d/%d tests passed (%.2f%%)\n",
            $totalPassed,
            $totalTests,
            $overallPercentage
        );
        echo str_repeat("=", 60) . "\n";
        
        return [
            'total_tests' => $totalTests,
            'total_passed' => $totalPassed,
            'total_failed' => $totalFailed,
            'coverage_percentage' => $overallPercentage
        ];
    }
}

// If running directly, execute all tests
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    echo "FMR AI Media Renaming - Test Suite\n";
    echo str_repeat("=", 40) . "\n\n";
    
    $results = FMRAITestSuite::runAllTests();
    $coverage = FMRAITestSuite::generateCoverageReport($results);
    
    // Exit with appropriate code
    exit($coverage['total_failed'] > 0 ? 1 : 0);
}