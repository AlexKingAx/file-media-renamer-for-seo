# Integration Test Documentation

## Overview

This document describes the comprehensive integration test suite for the FMR AI Media Renaming feature. The tests verify compatibility across different WordPress versions, SEO plugins, themes, and page builders as specified in requirements 4.3, 6.1, and 6.2.

## Test Structure

### Test Files

- `IntegrationTest.php` - PHPUnit-based integration tests
- `standalone-integration-test.php` - Standalone test runner (no PHPUnit dependency)
- `integration-config.php` - Configuration for test scenarios and compatibility matrix
- `run-integration-tests.php` - PHPUnit test runner
- `INTEGRATION_TEST_DOCUMENTATION.md` - This documentation file

### Test Categories

#### 1. WordPress Version Compatibility (Requirement 6.1)

Tests AI functionality across WordPress versions 5.8 through 6.4:

- **WordPress 5.8**: Basic media library, classic editor compatibility
- **WordPress 5.9**: Enhanced media library, improved block editor
- **WordPress 6.0**: Full site editing, theme.json v2 support
- **WordPress 6.1**: Enhanced performance, improved accessibility
- **WordPress 6.2**: Design tools, enhanced navigation
- **WordPress 6.3**: Command palette, enhanced patterns
- **WordPress 6.4**: Latest features, performance improvements

**Test Coverage:**
- Core AI functionality works across all versions
- Media library integration maintains compatibility
- AJAX endpoints function correctly
- UI elements render properly
- Fallback mechanisms work as expected

#### 2. SEO Plugin Compatibility (Requirement 4.3)

Tests integration with popular SEO plugins:

- **Rank Math**: Focus keyword extraction, meta title integration, description analysis
- **Yoast SEO**: Focus keyword extraction, readability score integration, SEO score consideration
- **All in One SEO**: Title template integration, schema markup consideration, social meta integration
- **SEOPress**: Content analysis integration, breadcrumb consideration, local SEO integration

**Test Coverage:**
- SEO data extraction from plugin meta fields
- Context analysis incorporates SEO keywords
- AI suggestions reflect SEO optimization goals
- Plugin-specific hooks and filters work correctly

#### 3. Theme Compatibility (Requirement 6.2)

Tests media library integration across different theme types:

- **Default Themes**: Twenty Twenty-One, Twenty Twenty-Two, Twenty Twenty-Three
- **Multipurpose Themes**: Astra, OceanWP
- **Lightweight Themes**: GeneratePress

**Test Coverage:**
- AI UI elements display correctly in different themes
- JavaScript functionality works without conflicts
- CSS styling integrates properly
- Bulk rename functionality maintains compatibility
- AJAX requests work across theme environments

#### 4. Page Builder Compatibility (Requirement 4.3)

Tests content extraction from major page builders:

- **Gutenberg**: Block content extraction, media block analysis, gallery block context
- **Elementor**: Widget content extraction, image widget analysis, text widget context
- **Beaver Builder**: Module content extraction, photo module analysis, text module context
- **Divi**: Module shortcode extraction, image module analysis, text module context
- **Visual Composer**: Element content extraction, single image analysis, text block context

**Test Coverage:**
- Content extraction from page builder data structures
- Context analysis includes page builder content
- AI suggestions incorporate page builder context
- Performance impact remains within acceptable limits

#### 5. Multisite Compatibility (Requirement 6.1)

Tests WordPress multisite environment support:

- Main site functionality
- Sub-site functionality
- Cross-site media access restrictions
- Network admin integration

**Test Coverage:**
- AI functionality works on main and sub-sites
- Credit management respects site boundaries
- Media library integration works per site
- Network admin settings function correctly

#### 6. Performance Benchmarks

Tests performance across different scenarios:

- Single rename: Maximum 30 seconds
- Bulk rename: Maximum 35 seconds per file
- Content analysis: Maximum 15 seconds
- Context extraction: Maximum 10 seconds
- Memory usage: Maximum 256MB

## Running the Tests

### Prerequisites

- PHP 7.4 or higher
- WordPress test environment
- Access to test media files

### Running Standalone Tests (Recommended)

```bash
php tests/standalone-integration-test.php
```

This runs all integration tests without requiring PHPUnit installation.

### Running PHPUnit Tests

```bash
# If PHPUnit is installed
php tests/run-integration-tests.php

# Or using PHPUnit directly
phpunit --configuration tests/phpunit.xml tests/IntegrationTest.php
```

### Running Specific Test Categories

The standalone test runner automatically runs all categories, but you can modify the script to run specific tests by commenting out unwanted test function calls.

## Test Results Interpretation

### Success Criteria

- All WordPress versions (5.8-6.4) must pass compatibility tests
- All major SEO plugins must integrate correctly
- All tested themes must display AI functionality properly
- All page builders must allow content extraction
- Multisite functionality must work correctly
- Performance benchmarks must be met

### Expected Output

```
============================================================
INTEGRATION TEST RESULTS SUMMARY
============================================================
Wordpress Compatibility       : ✓ PASSED
Seo Plugin Compatibility      : ✓ PASSED
Theme Compatibility           : ✓ PASSED
Page Builder Compatibility    : ✓ PASSED
Multisite Compatibility       : ✓ PASSED
Performance Benchmarks        : ✓ PASSED
------------------------------------------------------------
Total Assertions: 6 | Passed: 6 | Failed: 0
Success Rate: 100.00%
```

### Failure Handling

If any tests fail:

1. Review the specific error messages
2. Check the compatibility matrix in `integration-config.php`
3. Verify that the required plugins/themes are properly simulated
4. Ensure all mock functions return expected values
5. Check for version-specific functionality differences

## Test Configuration

### Compatibility Matrix

The `integration-config.php` file defines which combinations are supported:

```php
'compatibility_matrix' => [
    'wordpress_5.8' => [
        'supported_themes' => ['twentytwentyone', 'astra', 'generatepress'],
        'supported_seo_plugins' => ['yoast', 'rank_math'],
        'supported_page_builders' => ['gutenberg', 'elementor']
    ],
    // ... more versions
]
```

### Performance Benchmarks

```php
'performance_benchmarks' => [
    'single_rename_max_time' => 30, // seconds
    'bulk_rename_max_time_per_file' => 35, // seconds
    'content_analysis_max_time' => 15, // seconds
    'context_extraction_max_time' => 10, // seconds
    'max_memory_usage' => '256M',
    'max_database_queries' => 50
]
```

## Extending the Tests

### Adding New WordPress Versions

1. Add version configuration to `integration-config.php`
2. Update compatibility matrix
3. Add version-specific test scenarios if needed

### Adding New SEO Plugins

1. Define plugin configuration in `integration-config.php`
2. Add plugin simulation functions
3. Update compatibility matrix

### Adding New Themes

1. Add theme configuration to `integration-config.php`
2. Define theme-specific test scenarios
3. Update compatibility matrix

### Adding New Page Builders

1. Define page builder configuration
2. Add content extraction test scenarios
3. Update compatibility matrix

## Continuous Integration

These tests can be integrated into CI/CD pipelines:

```bash
# In CI script
php tests/standalone-integration-test.php
if [ $? -eq 0 ]; then
    echo "Integration tests passed"
else
    echo "Integration tests failed"
    exit 1
fi
```

## Troubleshooting

### Common Issues

1. **Class not found errors**: Ensure all mock classes are properly defined
2. **Memory limit exceeded**: Increase PHP memory limit for testing
3. **Timeout errors**: Adjust performance benchmark limits if needed
4. **Mock function conflicts**: Check for function redefinition issues

### Debug Mode

Enable debug output by modifying the test runner:

```php
// Add at the beginning of test files
define('FMR_TEST_DEBUG', true);

// Use in test functions
if (defined('FMR_TEST_DEBUG') && FMR_TEST_DEBUG) {
    echo "Debug: Testing {$test_name}\n";
}
```

## Requirements Traceability

| Requirement | Test Coverage | Status |
|-------------|---------------|--------|
| 4.3 - SEO plugin integration | SEO Plugin Compatibility Tests | ✅ |
| 4.3 - Page builder integration | Page Builder Compatibility Tests | ✅ |
| 6.1 - WordPress compatibility | WordPress Version Compatibility Tests | ✅ |
| 6.1 - Multisite support | Multisite Compatibility Tests | ✅ |
| 6.2 - Theme compatibility | Theme Compatibility Tests | ✅ |

All specified requirements are covered by the integration test suite.