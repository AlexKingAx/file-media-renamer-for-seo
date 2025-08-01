<?php

/**
 * AI Help System for File Media Renamer for SEO
 * 
 * Provides contextual help and documentation following WordPress standards
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class FMR_AI_Help_System {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('current_screen', array($this, 'add_help_tabs'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_help_assets'));
    }

    /**
     * Add help tabs to relevant admin pages
     */
    public function add_help_tabs() {
        $screen = get_current_screen();
        
        // Add help to settings page
        if ($screen->id === 'media_page_fmrseo') {
            $this->add_settings_help_tabs($screen);
        }
        
        // Add help to statistics page
        if ($screen->id === 'media_page_fmrseo-ai-statistics') {
            $this->add_statistics_help_tabs($screen);
        }
        
        // Add help to media library pages
        if (in_array($screen->id, array('upload', 'attachment'))) {
            $this->add_media_help_tabs($screen);
        }
    }

    /**
     * Add help tabs to settings page
     */
    private function add_settings_help_tabs($screen) {
        // Overview tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-settings-overview',
            'title' => __('Overview', 'fmrseo'),
            'content' => $this->get_settings_overview_help()
        ));

        // AI Configuration tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-ai-configuration',
            'title' => __('AI Configuration', 'fmrseo'),
            'content' => $this->get_ai_configuration_help()
        ));

        // Credit System tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-credit-system',
            'title' => __('Credit System', 'fmrseo'),
            'content' => $this->get_credit_system_help()
        ));

        // Troubleshooting tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-troubleshooting',
            'title' => __('Troubleshooting', 'fmrseo'),
            'content' => $this->get_troubleshooting_help()
        ));

        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar());
    }

    /**
     * Add help tabs to statistics page
     */
    private function add_statistics_help_tabs($screen) {
        // Understanding Statistics tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-understanding-stats',
            'title' => __('Understanding Statistics', 'fmrseo'),
            'content' => $this->get_understanding_stats_help()
        ));

        // Performance Metrics tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-performance-metrics',
            'title' => __('Performance Metrics', 'fmrseo'),
            'content' => $this->get_performance_metrics_help()
        ));

        // Exporting Data tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-exporting-data',
            'title' => __('Exporting Data', 'fmrseo'),
            'content' => $this->get_exporting_data_help()
        ));

        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar());
    }

    /**
     * Add help tabs to media library pages
     */
    private function add_media_help_tabs($screen) {
        // AI Renaming tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-ai-renaming',
            'title' => __('AI Renaming', 'fmrseo'),
            'content' => $this->get_ai_renaming_help()
        ));

        // Bulk Operations tab
        $screen->add_help_tab(array(
            'id' => 'fmrseo-bulk-operations',
            'title' => __('Bulk Operations', 'fmrseo'),
            'content' => $this->get_bulk_operations_help()
        ));

        // Set help sidebar
        $screen->set_help_sidebar($this->get_help_sidebar());
    }

    /**
     * Get settings overview help content
     */
    private function get_settings_overview_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Plugin Overview', 'fmrseo') . '</h3>
            <p>' . __('File Media Renamer for SEO helps you optimize your media files for better search engine visibility by automatically renaming them with SEO-friendly names.', 'fmrseo') . '</p>
            
            <h4>' . __('Key Features:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('AI-Powered Renaming:', 'fmrseo') . '</strong> ' . __('Automatically generates SEO-optimized names based on file content and page context.', 'fmrseo') . '</li>
                <li><strong>' . __('Manual Renaming:', 'fmrseo') . '</strong> ' . __('Traditional manual renaming with SEO optimization.', 'fmrseo') . '</li>
                <li><strong>' . __('Bulk Operations:', 'fmrseo') . '</strong> ' . __('Process multiple files at once for efficiency.', 'fmrseo') . '</li>
                <li><strong>' . __('History Tracking:', 'fmrseo') . '</strong> ' . __('Keep track of all rename operations with detailed history.', 'fmrseo') . '</li>
                <li><strong>' . __('Automatic Redirects:', 'fmrseo') . '</strong> ' . __('Prevents broken links by creating automatic redirects.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Getting Started:', 'fmrseo') . '</h4>
            <ol>
                <li>' . __('Configure your basic settings (rename title, alt text options).', 'fmrseo') . '</li>
                <li>' . __('If you want AI functionality, enable it and add your API key.', 'fmrseo') . '</li>
                <li>' . __('Go to your Media Library and start renaming files.', 'fmrseo') . '</li>
                <li>' . __('Monitor your usage in the AI Statistics page.', 'fmrseo') . '</li>
            </ol>
        </div>';
    }

    /**
     * Get AI configuration help content
     */
    private function get_ai_configuration_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('AI Configuration', 'fmrseo') . '</h3>
            <p>' . __('The AI functionality requires proper configuration to work effectively. Follow these steps to set up AI-powered renaming.', 'fmrseo') . '</p>
            
            <h4>' . __('API Key Setup:', 'fmrseo') . '</h4>
            <ol>
                <li>' . __('Obtain an API key from your AI service provider.', 'fmrseo') . '</li>
                <li>' . __('Enter the API key in the "API Key" field.', 'fmrseo') . '</li>
                <li>' . __('The system will automatically validate your key.', 'fmrseo') . '</li>
                <li>' . __('Enable the "Enable AI Renaming" option.', 'fmrseo') . '</li>
            </ol>

            <h4>' . __('Configuration Options:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('API Timeout:', 'fmrseo') . '</strong> ' . __('How long to wait for AI responses (10-120 seconds). Default: 30 seconds.', 'fmrseo') . '</li>
                <li><strong>' . __('Max Retries:', 'fmrseo') . '</strong> ' . __('Number of retry attempts for failed requests (0-5). Default: 2.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Supported File Types:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Images:', 'fmrseo') . '</strong> ' . __('JPG, PNG, GIF, WebP - Uses OCR and image recognition.', 'fmrseo') . '</li>
                <li><strong>' . __('PDFs:', 'fmrseo') . '</strong> ' . __('Extracts text content for analysis.', 'fmrseo') . '</li>
                <li><strong>' . __('Office Documents:', 'fmrseo') . '</strong> ' . __('DOCX, XLSX, PPTX - Extracts document content.', 'fmrseo') . '</li>
                <li><strong>' . __('Other Files:', 'fmrseo') . '</strong> ' . __('Uses WordPress metadata and filename analysis.', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-note">
                <p><strong>' . __('Note:', 'fmrseo') . '</strong> ' . __('AI functionality requires an active internet connection and sufficient credits. The system will automatically fall back to manual renaming if AI is unavailable.', 'fmrseo') . '</p>
            </div>
        </div>';
    }

    /**
     * Get credit system help content
     */
    private function get_credit_system_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Credit System', 'fmrseo') . '</h3>
            <p>' . __('The AI functionality uses a credit-based system to manage usage and costs. Each successful AI operation consumes one credit.', 'fmrseo') . '</p>
            
            <h4>' . __('How Credits Work:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('1 Credit = 1 AI Operation:', 'fmrseo') . '</strong> ' . __('Each successful rename uses exactly one credit.', 'fmrseo') . '</li>
                <li><strong>' . __('No Charge for Failures:', 'fmrseo') . '</strong> ' . __('Failed operations or fallbacks don\'t consume credits.', 'fmrseo') . '</li>
                <li><strong>' . __('Free Credits:', 'fmrseo') . '</strong> ' . __('New users receive 5 free credits to try the service.', 'fmrseo') . '</li>
                <li><strong>' . __('Bulk Operations:', 'fmrseo') . '</strong> ' . __('Each file in a bulk operation consumes one credit if successful.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Credit Balance:', 'fmrseo') . '</h4>
            <p>' . __('Your current credit balance is displayed in the settings page and dashboard widget. The balance is color-coded:', 'fmrseo') . '</p>
            <ul>
                <li><span style="color: #00a32a;">●</span> <strong>' . __('Green:', 'fmrseo') . '</strong> ' . __('More than 20 credits (healthy balance)', 'fmrseo') . '</li>
                <li><span style="color: #dba617;">●</span> <strong>' . __('Yellow:', 'fmrseo') . '</strong> ' . __('5-20 credits (consider purchasing more)', 'fmrseo') . '</li>
                <li><span style="color: #d63638;">●</span> <strong>' . __('Red:', 'fmrseo') . '</strong> ' . __('0-5 credits (low balance, purchase needed)', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('What Happens When Credits Run Out:', 'fmrseo') . '</h4>
            <ul>
                <li>' . __('AI renaming options will be disabled.', 'fmrseo') . '</li>
                <li>' . __('Manual renaming continues to work normally.', 'fmrseo') . '</li>
                <li>' . __('You\'ll see a warning message in the admin area.', 'fmrseo') . '</li>
                <li>' . __('Purchase more credits to re-enable AI functionality.', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-tip">
                <p><strong>' . __('Tip:', 'fmrseo') . '</strong> ' . __('Monitor your credit usage in the AI Statistics page to better understand your consumption patterns and plan accordingly.', 'fmrseo') . '</p>
            </div>
        </div>';
    }

    /**
     * Get troubleshooting help content
     */
    private function get_troubleshooting_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Troubleshooting', 'fmrseo') . '</h3>
            <p>' . __('Common issues and their solutions:', 'fmrseo') . '</p>
            
            <h4>' . __('AI Not Working:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Check API Key:', 'fmrseo') . '</strong> ' . __('Ensure your API key is valid and properly entered.', 'fmrseo') . '</li>
                <li><strong>' . __('Verify Credits:', 'fmrseo') . '</strong> ' . __('Make sure you have sufficient credits available.', 'fmrseo') . '</li>
                <li><strong>' . __('Internet Connection:', 'fmrseo') . '</strong> ' . __('AI requires an active internet connection.', 'fmrseo') . '</li>
                <li><strong>' . __('Enable AI:', 'fmrseo') . '</strong> ' . __('Confirm that "Enable AI Renaming" is checked.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Slow Performance:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Large Files:', 'fmrseo') . '</strong> ' . __('Large files take longer to analyze. Consider optimizing file sizes.', 'fmrseo') . '</li>
                <li><strong>' . __('Timeout Settings:', 'fmrseo') . '</strong> ' . __('Increase the API timeout if operations are timing out.', 'fmrseo') . '</li>
                <li><strong>' . __('Server Resources:', 'fmrseo') . '</strong> ' . __('Ensure your server has adequate resources for file processing.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Bulk Operations Failing:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Process Smaller Batches:', 'fmrseo') . '</strong> ' . __('Try processing fewer files at once.', 'fmrseo') . '</li>
                <li><strong>' . __('Check Individual Files:', 'fmrseo') . '</strong> ' . __('Some files might be corrupted or unsupported.', 'fmrseo') . '</li>
                <li><strong>' . __('Memory Limits:', 'fmrseo') . '</strong> ' . __('Increase PHP memory limit if needed.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Credit Issues:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Balance Not Updating:', 'fmrseo') . '</strong> ' . __('Use the refresh button in settings or wait a few minutes.', 'fmrseo') . '</li>
                <li><strong>' . __('Unexpected Deductions:', 'fmrseo') . '</strong> ' . __('Check the transaction history in AI Statistics.', 'fmrseo') . '</li>
                <li><strong>' . __('Free Credits Missing:', 'fmrseo') . '</strong> ' . __('Free credits are only provided once per API key.', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-support">
                <h4>' . __('Still Need Help?', 'fmrseo') . '</h4>
                <p>' . __('If you\'re still experiencing issues:', 'fmrseo') . '</p>
                <ul>
                    <li>' . __('Check the AI Statistics page for error details.', 'fmrseo') . '</li>
                    <li>' . __('Review your server error logs.', 'fmrseo') . '</li>
                    <li>' . __('Contact support with specific error messages.', 'fmrseo') . '</li>
                </ul>
            </div>
        </div>';
    }

    /**
     * Get understanding statistics help content
     */
    private function get_understanding_stats_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Understanding Statistics', 'fmrseo') . '</h3>
            <p>' . __('The AI Statistics page provides comprehensive insights into your AI usage patterns and system performance.', 'fmrseo') . '</p>
            
            <h4>' . __('Overview Cards:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Credits Remaining:', 'fmrseo') . '</strong> ' . __('Your current available credit balance.', 'fmrseo') . '</li>
                <li><strong>' . __('Total Credits Used:', 'fmrseo') . '</strong> ' . __('Lifetime total of credits consumed.', 'fmrseo') . '</li>
                <li><strong>' . __('Success Rate:', 'fmrseo') . '</strong> ' . __('Percentage of AI operations that completed successfully.', 'fmrseo') . '</li>
                <li><strong>' . __('Avg Processing Time:', 'fmrseo') . '</strong> ' . __('Average time taken for AI operations to complete.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Usage Trends Chart:', 'fmrseo') . '</h4>
            <p>' . __('The chart shows your AI usage over time with three metrics:', 'fmrseo') . '</p>
            <ul>
                <li><strong>' . __('AI Operations (Blue):', 'fmrseo') . '</strong> ' . __('Number of AI rename attempts per day.', 'fmrseo') . '</li>
                <li><strong>' . __('Credits Used (Red):', 'fmrseo') . '</strong> ' . __('Actual credits consumed (successful operations only).', 'fmrseo') . '</li>
                <li><strong>' . __('Success Rate (Green):', 'fmrseo') . '</strong> ' . __('Daily success percentage (right axis).', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Performance Metrics:', 'fmrseo') . '</h4>
            <p>' . __('Detailed breakdown of system performance:', 'fmrseo') . '</p>
            <ul>
                <li><strong>' . __('Total/Successful/Failed Operations:', 'fmrseo') . '</strong> ' . __('Complete operation counts.', 'fmrseo') . '</li>
                <li><strong>' . __('Fallback Operations:', 'fmrseo') . '</strong> ' . __('Times the system used manual renaming due to AI failure.', 'fmrseo') . '</li>
                <li><strong>' . __('Response Times:', 'fmrseo') . '</strong> ' . __('Fastest, slowest, and average AI response times.', 'fmrseo') . '</li>
                <li><strong>' . __('Credits per Day:', 'fmrseo') . '</strong> ' . __('Average daily credit consumption.', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-tip">
                <p><strong>' . __('Tip:', 'fmrseo') . '</strong> ' . __('Use the period selector to view trends for different time ranges (7, 30, or 90 days).', 'fmrseo') . '</p>
            </div>
        </div>';
    }

    /**
     * Get performance metrics help content
     */
    private function get_performance_metrics_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Performance Metrics', 'fmrseo') . '</h3>
            <p>' . __('Understanding and optimizing your AI performance metrics.', 'fmrseo') . '</p>
            
            <h4>' . __('File Type Analysis:', 'fmrseo') . '</h4>
            <p>' . __('Shows how different file types perform with AI processing:', 'fmrseo') . '</p>
            <ul>
                <li><strong>' . __('Processing Count:', 'fmrseo') . '</strong> ' . __('How many files of each type you\'ve processed.', 'fmrseo') . '</li>
                <li><strong>' . __('Percentage:', 'fmrseo') . '</strong> ' . __('What portion of your total operations each file type represents.', 'fmrseo') . '</li>
                <li><strong>' . __('Average Time:', 'fmrseo') . '</strong> ' . __('How long each file type typically takes to process.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('System Health Indicators:', 'fmrseo') . '</h4>
            <ul>
                <li><span style="color: #00a32a;">●</span> <strong>' . __('Good:', 'fmrseo') . '</strong> ' . __('System is operating optimally.', 'fmrseo') . '</li>
                <li><span style="color: #dba617;">●</span> <strong>' . __('Warning:', 'fmrseo') . '</strong> ' . __('Minor issues that should be addressed.', 'fmrseo') . '</li>
                <li><span style="color: #d63638;">●</span> <strong>' . __('Error:', 'fmrseo') . '</strong> ' . __('Critical issues requiring immediate attention.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Optimizing Performance:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Success Rate < 90%:', 'fmrseo') . '</strong> ' . __('Check API key validity and internet connection.', 'fmrseo') . '</li>
                <li><strong>' . __('Slow Processing:', 'fmrseo') . '</strong> ' . __('Consider increasing timeout settings or optimizing file sizes.', 'fmrseo') . '</li>
                <li><strong>' . __('High Fallback Rate:', 'fmrseo') . '</strong> ' . __('Investigate recurring AI failures.', 'fmrseo') . '</li>
                <li><strong>' . __('Uneven File Type Performance:', 'fmrseo') . '</strong> ' . __('Some file types may need special handling.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Recent Activity Table:', 'fmrseo') . '</h4>
            <p>' . __('The recent activity table shows:', 'fmrseo') . '</p>
            <ul>
                <li><strong>' . __('File Information:', 'fmrseo') . '</strong> ' . __('Filename and file type processed.', 'fmrseo') . '</li>
                <li><strong>' . __('Operation Type:', 'fmrseo') . '</strong> ' . __('Single or bulk operation.', 'fmrseo') . '</li>
                <li><strong>' . __('Status:', 'fmrseo') . '</strong> ' . __('Success, failed, or fallback result.', 'fmrseo') . '</li>
                <li><strong>' . __('Credits:', 'fmrseo') . '</strong> ' . __('Credits consumed (only for successful operations).', 'fmrseo') . '</li>
                <li><strong>' . __('Processing Time:', 'fmrseo') . '</strong> ' . __('How long the operation took.', 'fmrseo') . '</li>
            </ul>
        </div>';
    }

    /**
     * Get exporting data help content
     */
    private function get_exporting_data_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Exporting Data', 'fmrseo') . '</h3>
            <p>' . __('Export your AI usage statistics for analysis, reporting, or backup purposes.', 'fmrseo') . '</p>
            
            <h4>' . __('Export Format:', 'fmrseo') . '</h4>
            <p>' . __('Data is exported in CSV (Comma-Separated Values) format, which can be opened in:', 'fmrseo') . '</p>
            <ul>
                <li>' . __('Microsoft Excel', 'fmrseo') . '</li>
                <li>' . __('Google Sheets', 'fmrseo') . '</li>
                <li>' . __('LibreOffice Calc', 'fmrseo') . '</li>
                <li>' . __('Any text editor', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Exported Data Includes:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('User Statistics:', 'fmrseo') . '</strong> ' . __('Your personal credit usage and operation counts.', 'fmrseo') . '</li>
                <li><strong>' . __('Global Statistics:', 'fmrseo') . '</strong> ' . __('Overall system performance metrics.', 'fmrseo') . '</li>
                <li><strong>' . __('Transaction History:', 'fmrseo') . '</strong> ' . __('Detailed log of all your AI operations.', 'fmrseo') . '</li>
                <li><strong>' . __('Export Metadata:', 'fmrseo') . '</strong> ' . __('Export date, time, and site information.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Using Exported Data:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Usage Analysis:', 'fmrseo') . '</strong> ' . __('Analyze your AI usage patterns over time.', 'fmrseo') . '</li>
                <li><strong>' . __('Budget Planning:', 'fmrseo') . '</strong> ' . __('Plan future credit purchases based on usage trends.', 'fmrseo') . '</li>
                <li><strong>' . __('Performance Tracking:', 'fmrseo') . '</strong> ' . __('Monitor success rates and processing times.', 'fmrseo') . '</li>
                <li><strong>' . __('Reporting:', 'fmrseo') . '</strong> ' . __('Create reports for stakeholders or clients.', 'fmrseo') . '</li>
                <li><strong>' . __('Backup:', 'fmrseo') . '</strong> ' . __('Keep a backup of your usage history.', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-warning">
                <p><strong>' . __('Privacy Note:', 'fmrseo') . '</strong> ' . __('Exported data may contain filenames and usage patterns. Handle exported files securely and in accordance with your privacy policies.', 'fmrseo') . '</p>
            </div>
        </div>';
    }

    /**
     * Get AI renaming help content
     */
    private function get_ai_renaming_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('AI Renaming in Media Library', 'fmrseo') . '</h3>
            <p>' . __('Use AI-powered renaming directly from your WordPress Media Library.', 'fmrseo') . '</p>
            
            <h4>' . __('Single File Renaming:', 'fmrseo') . '</h4>
            <ol>
                <li>' . __('Go to Media Library and click on any file to edit it.', 'fmrseo') . '</li>
                <li>' . __('Look for the "Rename with AI" button next to the SEO Name field.', 'fmrseo') . '</li>
                <li>' . __('Click the button to analyze the file and generate suggestions.', 'fmrseo') . '</li>
                <li>' . __('Choose from 1-3 AI-generated name suggestions.', 'fmrseo') . '</li>
                <li>' . __('The file will be renamed and 1 credit will be deducted.', 'fmrseo') . '</li>
            </ol>

            <h4>' . __('What AI Analyzes:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('File Content:', 'fmrseo') . '</strong> ' . __('Text in images (OCR), PDF content, document text.', 'fmrseo') . '</li>
                <li><strong>' . __('Page Context:', 'fmrseo') . '</strong> ' . __('Where the file is used, page titles, headings.', 'fmrseo') . '</li>
                <li><strong>' . __('SEO Data:', 'fmrseo') . '</strong> ' . __('Keywords from SEO plugins like Yoast or Rank Math.', 'fmrseo') . '</li>
                <li><strong>' . __('WordPress Metadata:', 'fmrseo') . '</strong> ' . __('Existing title, alt text, and descriptions.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('AI Suggestions:', 'fmrseo') . '</h4>
            <p>' . __('AI generates names that are:', 'fmrseo') . '</p>
            <ul>
                <li>' . __('SEO-optimized with relevant keywords', 'fmrseo') . '</li>
                <li>' . __('Descriptive of the file content', 'fmrseo') . '</li>
                <li>' . __('Contextually relevant to your pages', 'fmrseo') . '</li>
                <li>' . __('Following SEO best practices (lowercase, hyphens)', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-tip">
                <p><strong>' . __('Tip:', 'fmrseo') . '</strong> ' . __('If AI is unavailable or fails, the system automatically falls back to manual renaming, so you can always rename your files.', 'fmrseo') . '</p>
            </div>
        </div>';
    }

    /**
     * Get bulk operations help content
     */
    private function get_bulk_operations_help() {
        return '<div class="fmrseo-help-content">
            <h3>' . __('Bulk AI Operations', 'fmrseo') . '</h3>
            <p>' . __('Process multiple files at once with AI-powered renaming for maximum efficiency.', 'fmrseo') . '</p>
            
            <h4>' . __('How to Use Bulk AI Renaming:', 'fmrseo') . '</h4>
            <ol>
                <li>' . __('Go to Media Library (list view works best).', 'fmrseo') . '</li>
                <li>' . __('Select multiple files using checkboxes.', 'fmrseo') . '</li>
                <li>' . __('Choose "Rename with AI" from the Bulk Actions dropdown.', 'fmrseo') . '</li>
                <li>' . __('Click "Apply" to start the bulk operation.', 'fmrseo') . '</li>
                <li>' . __('Monitor progress as each file is processed individually.', 'fmrseo') . '</li>
            </ol>

            <h4>' . __('Bulk Operation Behavior:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Individual Processing:', 'fmrseo') . '</strong> ' . __('Each file is analyzed separately for optimal results.', 'fmrseo') . '</li>
                <li><strong>' . __('Credit Usage:', 'fmrseo') . '</strong> ' . __('1 credit per successfully renamed file.', 'fmrseo') . '</li>
                <li><strong>' . __('Error Handling:', 'fmrseo') . '</strong> ' . __('Failed files don\'t consume credits and don\'t stop the process.', 'fmrseo') . '</li>
                <li><strong>' . __('Progress Tracking:', 'fmrseo') . '</strong> ' . __('Real-time updates on processing status.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Best Practices:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Batch Size:', 'fmrseo') . '</strong> ' . __('Process 10-20 files at a time for optimal performance.', 'fmrseo') . '</li>
                <li><strong>' . __('File Types:', 'fmrseo') . '</strong> ' . __('Mix different file types in the same batch - each is handled appropriately.', 'fmrseo') . '</li>
                <li><strong>' . __('Credit Planning:', 'fmrseo') . '</strong> ' . __('Ensure you have enough credits before starting large batches.', 'fmrseo') . '</li>
                <li><strong>' . __('Review Results:', 'fmrseo') . '</strong> ' . __('Check the results summary after bulk operations complete.', 'fmrseo') . '</li>
            </ul>

            <h4>' . __('Troubleshooting Bulk Operations:', 'fmrseo') . '</h4>
            <ul>
                <li><strong>' . __('Operation Stops:', 'fmrseo') . '</strong> ' . __('Check your internet connection and credit balance.', 'fmrseo') . '</li>
                <li><strong>' . __('Some Files Fail:', 'fmrseo') . '</strong> ' . __('Normal behavior - corrupted or unsupported files will be skipped.', 'fmrseo') . '</li>
                <li><strong>' . __('Slow Processing:', 'fmrseo') . '</strong> ' . __('Large files or complex content takes longer to analyze.', 'fmrseo') . '</li>
            </ul>

            <div class="fmrseo-help-warning">
                <p><strong>' . __('Important:', 'fmrseo') . '</strong> ' . __('Bulk operations cannot be undone as a group. Each file rename is individual and permanent. Always backup your media files before large bulk operations.', 'fmrseo') . '</p>
            </div>
        </div>';
    }

    /**
     * Get help sidebar content
     */
    private function get_help_sidebar() {
        return '<div class="fmrseo-help-sidebar">
            <h4>' . __('Quick Links', 'fmrseo') . '</h4>
            <ul>
                <li><a href="' . admin_url('upload.php?page=fmrseo') . '">' . __('Plugin Settings', 'fmrseo') . '</a></li>
                <li><a href="' . admin_url('upload.php?page=fmrseo-ai-statistics') . '">' . __('AI Statistics', 'fmrseo') . '</a></li>
                <li><a href="' . admin_url('upload.php') . '">' . __('Media Library', 'fmrseo') . '</a></li>
            </ul>

            <h4>' . __('Support', 'fmrseo') . '</h4>
            <p>' . __('Need additional help?', 'fmrseo') . '</p>
            <ul>
                <li><a href="#" target="_blank">' . __('Documentation', 'fmrseo') . '</a></li>
                <li><a href="#" target="_blank">' . __('Support Forum', 'fmrseo') . '</a></li>
                <li><a href="#" target="_blank">' . __('Contact Support', 'fmrseo') . '</a></li>
            </ul>

            <h4>' . __('System Info', 'fmrseo') . '</h4>
            <p><strong>' . __('Plugin Version:', 'fmrseo') . '</strong> 0.7.0</p>
            <p><strong>' . __('WordPress Version:', 'fmrseo') . '</strong> ' . get_bloginfo('version') . '</p>
            <p><strong>' . __('PHP Version:', 'fmrseo') . '</strong> ' . PHP_VERSION . '</p>
        </div>';
    }

    /**
     * Enqueue help system assets
     */
    public function enqueue_help_assets($hook) {
        // Only load on pages with our help tabs
        $help_pages = array(
            'media_page_fmrseo',
            'media_page_fmrseo-ai-statistics',
            'upload',
            'attachment'
        );

        if (!in_array($hook, $help_pages)) {
            return;
        }

        wp_enqueue_style(
            'fmrseo-help-system',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/help-system.css',
            array(),
            '1.0.0'
        );
    }
}