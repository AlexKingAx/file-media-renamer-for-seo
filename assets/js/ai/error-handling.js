/**
 * Error Handling JavaScript for FMR SEO AI functionality
 * Provides client-side error handling, retry logic, and user feedback
 */

(function($) {
    'use strict';

    // Error Handler Class
    window.FMRErrorHandler = {
        
        // Configuration
        config: {
            maxRetries: 3,
            retryDelay: 2000,
            errorDisplayDuration: 5000,
            debugMode: false
        },

        // Error counters
        errorCounts: {
            ai_service: 0,
            credit: 0,
            content_analysis: 0,
            configuration: 0,
            system: 0
        },

        // Initialize error handler
        init: function() {
            this.bindEvents();
            this.checkAIAvailability();
            this.initErrorToggles();
            this.initRecoveryActions();
            
            // Set debug mode from WordPress localized data
            if (typeof fmrErrorHandler !== 'undefined' && fmrErrorHandler.debug) {
                this.config.debugMode = true;
            }
        },

        // Bind event handlers
        bindEvents: function() {
            var self = this;

            // Handle AJAX errors globally
            $(document).ajaxError(function(event, xhr, settings, error) {
                if (settings.url && settings.url.indexOf('fmr') !== -1) {
                    self.handleAjaxError(xhr, settings, error);
                }
            });

            // Handle retry buttons
            $(document).on('click', '.fmrseo-retry-button', function(e) {
                e.preventDefault();
                self.handleRetry($(this));
            });

            // Handle error detail toggles
            $(document).on('click', '.fmrseo-error-toggle', function(e) {
                e.preventDefault();
                self.toggleErrorDetails($(this));
            });

            // Handle recovery actions
            $(document).on('click', '.fmrseo-recovery-button', function(e) {
                e.preventDefault();
                self.handleRecoveryAction($(this));
            });

            // Auto-hide dismissible notices
            setTimeout(function() {
                $('.fmrseo-auto-dismiss').fadeOut();
            }, this.config.errorDisplayDuration);
        },

        // Check AI availability status
        checkAIAvailability: function() {
            var self = this;
            
            if (typeof fmrAI === 'undefined') {
                return;
            }

            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'fmr_check_ai_availability',
                    nonce: fmrAI.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateAIStatus(response.data);
                    } else {
                        self.updateAIStatus({
                            available: false,
                            errors: [response.data.message || 'Unknown error']
                        });
                    }
                },
                error: function() {
                    self.updateAIStatus({
                        available: false,
                        errors: ['Unable to check AI availability']
                    });
                }
            });
        },

        // Update AI status display
        updateAIStatus: function(status) {
            var $statusElements = $('.fmrseo-ai-status');
            
            if (status.available) {
                $statusElements
                    .removeClass('unavailable warning')
                    .addClass('available')
                    .text('Available');
                    
                // Enable AI buttons
                $('.ai-rename-button, #ai-rename-button').prop('disabled', false);
                
            } else {
                $statusElements
                    .removeClass('available')
                    .addClass('unavailable')
                    .text('Unavailable');
                    
                // Disable AI buttons
                $('.ai-rename-button, #ai-rename-button').prop('disabled', true);
                
                // Show error details if available
                if (status.errors && status.errors.length > 0) {
                    this.showErrorMessage(status.errors.join(', '), 'error');
                }
            }
        },

        // Handle AJAX errors
        handleAjaxError: function(xhr, settings, error) {
            var errorData = {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                url: settings.url,
                error: error
            };

            if (this.config.debugMode) {
                console.error('FMR AJAX Error:', errorData);
            }

            // Try to parse error response
            var errorMessage = 'An unexpected error occurred';
            var errorType = 'system';
            
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.data && response.data.message) {
                    errorMessage = response.data.message;
                    errorType = response.data.error_code || 'system';
                }
            } catch (e) {
                // Use default error message
            }

            // Update error counts
            this.errorCounts[errorType] = (this.errorCounts[errorType] || 0) + 1;

            // Show error to user
            this.showErrorMessage(errorMessage, 'error');

            // Check if we should suggest fallback
            if (this.shouldSuggestFallback(errorType)) {
                this.suggestFallback();
            }
        },

        // Show error message to user
        showErrorMessage: function(message, type, duration) {
            type = type || 'error';
            duration = duration || this.config.errorDisplayDuration;

            var $notice = $('<div>')
                .addClass('fmrseo-' + type + '-notice')
                .addClass('fmrseo-auto-dismiss')
                .attr('role', 'alert')
                .html('<p>' + this.escapeHtml(message) + '</p>');

            // Add to appropriate container
            var $container = $('.fmrseo-error-container');
            if ($container.length === 0) {
                $container = $('<div class="fmrseo-error-container">').prependTo('.wrap');
            }

            $container.append($notice);

            // Auto-hide after duration
            if (duration > 0) {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, duration);
            }
        },

        // Handle retry logic
        handleRetry: function($button) {
            var self = this;
            var retryData = $button.data();
            var currentRetries = parseInt($button.data('retries') || 0);

            if (currentRetries >= this.config.maxRetries) {
                this.showErrorMessage('Maximum retry attempts reached', 'error');
                return;
            }

            // Update retry count
            $button.data('retries', currentRetries + 1);
            
            // Show loading state
            $button.prop('disabled', true).text('Retrying...');

            // Wait before retry
            setTimeout(function() {
                self.executeRetry(retryData, $button);
            }, this.config.retryDelay);
        },

        // Execute retry operation
        executeRetry: function(retryData, $button) {
            var self = this;

            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: retryData.action || 'fmr_ai_rename_single',
                    post_id: retryData.postId,
                    nonce: fmrAI.nonce,
                    _ajax_nonce: fmrAI.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showErrorMessage('Operation completed successfully', 'success');
                        
                        // Update UI with success
                        if (retryData.successCallback) {
                            window[retryData.successCallback](response.data);
                        }
                        
                        // Remove retry button
                        $button.closest('.fmrseo-recovery-actions').fadeOut();
                        
                    } else {
                        self.handleRetryFailure($button, response.data.message);
                    }
                },
                error: function() {
                    self.handleRetryFailure($button, 'Retry failed due to network error');
                }
            });
        },

        // Handle retry failure
        handleRetryFailure: function($button, message) {
            var currentRetries = parseInt($button.data('retries') || 0);
            
            $button.prop('disabled', false).text('Retry (' + (this.config.maxRetries - currentRetries) + ' left)');
            
            if (currentRetries >= this.config.maxRetries) {
                $button.prop('disabled', true).text('Max retries reached');
                this.suggestFallback();
            }
            
            this.showErrorMessage(message, 'error');
        },

        // Toggle error details
        toggleErrorDetails: function($toggle) {
            var $details = $toggle.siblings('.fmrseo-error-details');
            
            if ($details.hasClass('expanded')) {
                $details.removeClass('expanded').slideUp();
                $toggle.text('Show details');
            } else {
                $details.addClass('expanded').slideDown();
                $toggle.text('Hide details');
            }
        },

        // Initialize error detail toggles
        initErrorToggles: function() {
            $('.fmrseo-error-toggle').each(function() {
                var $toggle = $(this);
                var $details = $toggle.siblings('.fmrseo-error-details');
                
                if ($details.length > 0) {
                    $toggle.show();
                }
            });
        },

        // Initialize recovery actions
        initRecoveryActions: function() {
            var self = this;
            
            // Add recovery actions to error notices
            $('.fmrseo-error-notice').each(function() {
                var $notice = $(this);
                var errorType = $notice.data('error-type');
                
                if (errorType && self.getRecoveryActions(errorType).length > 0) {
                    var $actions = self.createRecoveryActions(errorType);
                    $notice.append($actions);
                }
            });
        },

        // Get recovery actions for error type
        getRecoveryActions: function(errorType) {
            var actions = [];
            
            switch (errorType) {
                case 'credit_error':
                    actions.push({
                        text: 'Check Credit Balance',
                        action: 'check_credits',
                        class: 'secondary'
                    });
                    break;
                    
                case 'configuration_error':
                    actions.push({
                        text: 'Check Settings',
                        action: 'check_settings',
                        class: 'secondary'
                    });
                    actions.push({
                        text: 'Re-enable AI',
                        action: 'reenable_ai',
                        class: 'primary'
                    });
                    break;
                    
                case 'ai_service_error':
                    actions.push({
                        text: 'Test Connection',
                        action: 'test_connection',
                        class: 'secondary'
                    });
                    actions.push({
                        text: 'Use Fallback',
                        action: 'use_fallback',
                        class: 'primary'
                    });
                    break;
            }
            
            return actions;
        },

        // Create recovery action buttons
        createRecoveryActions: function(errorType) {
            var self = this;
            var actions = this.getRecoveryActions(errorType);
            
            if (actions.length === 0) {
                return $();
            }
            
            var $container = $('<div class="fmrseo-recovery-actions">');
            $container.append('<h4>Recovery Options:</h4>');
            
            actions.forEach(function(action) {
                var $button = $('<button>')
                    .addClass('fmrseo-recovery-button')
                    .addClass('button')
                    .addClass(action.class === 'primary' ? 'button-primary' : 'button-secondary')
                    .text(action.text)
                    .data('action', action.action);
                    
                $container.append($button);
            });
            
            return $container;
        },

        // Handle recovery actions
        handleRecoveryAction: function($button) {
            var action = $button.data('action');
            var self = this;
            
            $button.prop('disabled', true).addClass('fmrseo-loading');
            
            switch (action) {
                case 'check_credits':
                    this.checkCredits($button);
                    break;
                    
                case 'check_settings':
                    window.location.href = 'upload.php?page=fmrseo';
                    break;
                    
                case 'reenable_ai':
                    this.reenableAI($button);
                    break;
                    
                case 'test_connection':
                    this.testConnection($button);
                    break;
                    
                case 'use_fallback':
                    this.useFallback($button);
                    break;
                    
                default:
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    this.showErrorMessage('Unknown recovery action', 'error');
            }
        },

        // Check credits
        checkCredits: function($button) {
            var self = this;
            
            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'fmr_check_credits',
                    nonce: fmrAI.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    
                    if (response.success) {
                        var balance = response.data.balance;
                        self.showErrorMessage('Current credit balance: ' + balance, 'info');
                        
                        if (balance > 0) {
                            self.checkAIAvailability();
                        }
                    } else {
                        self.showErrorMessage('Unable to check credit balance', 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    self.showErrorMessage('Error checking credits', 'error');
                }
            });
        },

        // Re-enable AI
        reenableAI: function($button) {
            var self = this;
            
            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'fmr_reenable_ai',
                    nonce: fmrAI.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    
                    if (response.success) {
                        self.showErrorMessage('AI functionality has been re-enabled', 'success');
                        self.checkAIAvailability();
                        
                        // Hide recovery actions
                        $button.closest('.fmrseo-recovery-actions').fadeOut();
                    } else {
                        self.showErrorMessage(response.data.message || 'Failed to re-enable AI', 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    self.showErrorMessage('Error re-enabling AI', 'error');
                }
            });
        },

        // Test connection
        testConnection: function($button) {
            var self = this;
            
            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'fmr_test_ai_connection',
                    nonce: fmrAI.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    
                    if (response.success) {
                        self.showErrorMessage('AI service connection successful', 'success');
                        self.checkAIAvailability();
                    } else {
                        self.showErrorMessage('Connection test failed: ' + (response.data.message || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).removeClass('fmrseo-loading');
                    self.showErrorMessage('Connection test failed', 'error');
                }
            });
        },

        // Use fallback
        useFallback: function($button) {
            var postId = $button.closest('[data-post-id]').data('post-id');
            
            if (!postId) {
                this.showErrorMessage('No media ID found for fallback', 'error');
                $button.prop('disabled', false).removeClass('fmrseo-loading');
                return;
            }
            
            // Trigger manual rename instead
            var $manualButton = $('#save-seo-name[media-id="' + postId + '"]');
            if ($manualButton.length > 0) {
                $manualButton.click();
                this.showErrorMessage('Switched to manual rename', 'info');
            } else {
                this.showErrorMessage('Manual rename not available', 'error');
            }
            
            $button.prop('disabled', false).removeClass('fmrseo-loading');
        },

        // Check if should suggest fallback
        shouldSuggestFallback: function(errorType) {
            var threshold = 2;
            return this.errorCounts[errorType] >= threshold;
        },

        // Suggest fallback
        suggestFallback: function() {
            var message = 'AI functionality is experiencing issues. Would you like to use manual rename instead?';
            
            if (confirm(message)) {
                // Hide AI buttons and show manual rename
                $('.ai-rename-button, #ai-rename-button').hide();
                $('#save-seo-name').show().focus();
                
                this.showErrorMessage('Switched to manual rename mode', 'info');
            }
        },

        // Escape HTML
        escapeHtml: function(text) {
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        // Get error statistics
        getErrorStats: function() {
            return {
                counts: this.errorCounts,
                total: Object.values(this.errorCounts).reduce((a, b) => a + b, 0)
            };
        },

        // Reset error counts
        resetErrorCounts: function() {
            for (var key in this.errorCounts) {
                this.errorCounts[key] = 0;
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FMRErrorHandler.init();
    });

})(jQuery);