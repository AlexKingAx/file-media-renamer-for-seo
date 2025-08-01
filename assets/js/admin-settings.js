/**
 * Admin Settings JavaScript
 * File Media Renamer for SEO
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        FMRSEOSettings.init();
    });

    // Main settings object
    window.FMRSEOSettings = {
        
        /**
         * Initialize the settings page
         */
        init: function() {
            this.bindEvents();
            this.initApiKeyToggle();
            this.initCreditRefresh();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // API key validation on blur
            $('#ai_api_key').on('blur', this.validateApiKey.bind(this));
            
            // AI enabled toggle
            $('#ai_enabled').on('change', this.toggleAiSettings.bind(this));
            
            // Credit refresh button
            $(document).on('click', '.fmrseo-refresh-credits', this.refreshCredits.bind(this));
            
            // Settings form submission
            $('form').on('submit', this.onFormSubmit.bind(this));
        },

        /**
         * Initialize API key show/hide toggle
         */
        initApiKeyToggle: function() {
            const apiKeyField = $('#ai_api_key');
            if (apiKeyField.length) {
                // Wrap the field in a container
                apiKeyField.wrap('<div class="fmrseo-api-key-field"></div>');
                
                // Add toggle button
                const toggleButton = $('<button type="button" class="fmrseo-api-key-toggle" title="Show/Hide API Key"><span class="dashicons dashicons-visibility"></span></button>');
                apiKeyField.after(toggleButton);
                
                // Handle toggle click
                toggleButton.on('click', function() {
                    const field = $(this).siblings('input');
                    const icon = $(this).find('.dashicons');
                    
                    if (field.attr('type') === 'password') {
                        field.attr('type', 'text');
                        icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                        $(this).attr('title', 'Hide API Key');
                    } else {
                        field.attr('type', 'password');
                        icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                        $(this).attr('title', 'Show API Key');
                    }
                });
            }
        },

        /**
         * Initialize credit refresh functionality
         */
        initCreditRefresh: function() {
            const creditBalance = $('.fmrseo-credit-balance');
            if (creditBalance.length && !creditBalance.hasClass('error')) {
                const refreshButton = $('<button type="button" class="button button-small fmrseo-refresh-credits" style="margin-left: 10px;"><span class="dashicons dashicons-update"></span> Refresh</button>');
                creditBalance.after(refreshButton);
            }
        },

        /**
         * Validate API key
         */
        validateApiKey: function(e) {
            const apiKey = $(e.target).val().trim();
            const statusContainer = $(e.target).siblings('.fmrseo-api-status');
            
            // Remove existing status
            statusContainer.remove();
            
            if (!apiKey) {
                return;
            }

            // Show loading
            const loadingStatus = $('<div class="fmrseo-api-status fmrseo-loading-status"><span class="dashicons dashicons-update"></span> Validating API key...</div>');
            $(e.target).after(loadingStatus);

            // Simulate API validation (in real implementation, this would be an AJAX call)
            setTimeout(() => {
                loadingStatus.remove();
                
                // Simulate validation result
                const isValid = apiKey.length >= 20; // Simple validation
                
                if (isValid) {
                    const successStatus = $('<div class="fmrseo-api-status fmrseo-success"><span class="dashicons dashicons-yes-alt"></span> API key is valid</div>');
                    $(e.target).after(successStatus);
                } else {
                    const errorStatus = $('<div class="fmrseo-api-status fmrseo-error"><span class="dashicons dashicons-dismiss"></span> Invalid API key format</div>');
                    $(e.target).after(errorStatus);
                }
            }, 1500);
        },

        /**
         * Toggle AI settings visibility
         */
        toggleAiSettings: function(e) {
            const isEnabled = $(e.target).is(':checked');
            const aiSection = $('#ai_enabled').closest('tr').nextAll('tr').slice(0, 4); // Next 4 rows are AI settings
            
            if (isEnabled) {
                aiSection.fadeIn();
                this.showNotice('AI functionality enabled. Configure your API key below.', 'info');
            } else {
                aiSection.fadeOut();
                this.showNotice('AI functionality disabled. Manual renaming will still work.', 'warning');
            }
        },

        /**
         * Refresh credit balance
         */
        refreshCredits: function(e) {
            e.preventDefault();
            
            const button = $(e.target).closest('.fmrseo-refresh-credits');
            const originalText = button.html();
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Refreshing...');
            
            // Simulate credit refresh (in real implementation, this would be an AJAX call)
            setTimeout(() => {
                button.prop('disabled', false).html(originalText);
                
                // Update credit display (simulate new balance)
                const creditBalance = $('.fmrseo-credit-balance');
                const currentBalance = parseInt(creditBalance.find('p').text().match(/\d+/)[0]);
                const newBalance = Math.max(0, currentBalance + Math.floor(Math.random() * 5) - 2);
                
                creditBalance.find('p').html('<strong>Available Credits: ' + newBalance + '</strong>');
                
                // Update class based on new balance
                creditBalance.removeClass('positive warning error');
                if (newBalance > 10) {
                    creditBalance.addClass('positive');
                } else if (newBalance > 0) {
                    creditBalance.addClass('warning');
                } else {
                    creditBalance.addClass('error');
                }
                
                this.showNotice('Credit balance refreshed successfully.', 'success');
            }, 1000);
        },

        /**
         * Handle form submission
         */
        onFormSubmit: function(e) {
            const form = $(e.target);
            const submitButton = form.find('input[type="submit"]');
            
            // Show loading state
            submitButton.prop('disabled', true);
            form.addClass('fmrseo-loading');
            
            // Validate required fields
            const apiKeyEnabled = $('#ai_enabled').is(':checked');
            const apiKey = $('#ai_api_key').val().trim();
            
            if (apiKeyEnabled && !apiKey) {
                e.preventDefault();
                form.removeClass('fmrseo-loading');
                submitButton.prop('disabled', false);
                this.showNotice('API key is required when AI functionality is enabled.', 'error');
                $('#ai_api_key').focus();
                return false;
            }
            
            // Form will submit normally, loading state will be cleared on page reload
        },

        /**
         * Show notice message
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            // Remove existing notices
            $('.fmrseo-settings-notice').remove();
            
            // Create notice element
            const notice = $('<div class="notice notice-' + type + ' is-dismissible fmrseo-settings-notice"><p>' + message + '</p></div>');
            
            // Insert notice after the page title
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut();
            }, 5000);
            
            // Scroll to top to show notice
            $('html, body').animate({ scrollTop: 0 }, 300);
        },

        /**
         * Show loading overlay
         */
        showLoading: function(container) {
            container = container || $('body');
            container.addClass('fmrseo-loading');
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function(container) {
            container = container || $('body');
            container.removeClass('fmrseo-loading');
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
    };

    // Utility functions for other scripts
    window.FMRSEOSettingsUtils = {
        showNotice: FMRSEOSettings.showNotice,
        showLoading: FMRSEOSettings.showLoading,
        hideLoading: FMRSEOSettings.hideLoading,
        formatNumber: FMRSEOSettings.formatNumber
    };

})(jQuery);