/**
 * AI Rename JavaScript for FMR SEO
 * AI rename functionality with suggestions modal
 */

(function($) {
    'use strict';

    // AI Rename functionality
    window.FMRAIRename = {
        
        currentPostId: null,
        currentButton: null,
        currentFileExtension: null,
        
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Handle AI rename button clicks - now shows modal instead of direct rename
            $(document).on('click', '#ai-rename-button, .ai-rename-button', function(e) {
                e.preventDefault();
                var postId = $(this).attr('media-id') || $(this).data('media-id');
                if (postId) {
                    self.showAISuggestionsModal(postId, $(this));
                }
            });

            // Modal event handlers
            $(document).on('click', '.fmrseo-ai-modal-close, .fmrseo-ai-modal-cancel', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            $(document).on('click', '.fmrseo-ai-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            $(document).on('click', '.fmrseo-ai-suggestion-item', function(e) {
                e.preventDefault();
                self.selectSuggestion($(this));
            });

            $(document).on('click', '.fmrseo-ai-modal-apply', function(e) {
                e.preventDefault();
                self.applySelectedSuggestion();
            });

            // Handle escape key
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $('.fmrseo-ai-modal').is(':visible')) {
                    self.closeModal();
                }
            });
        },



        showAISuggestionsModal: function(postId, $button) {
            var self = this;
            
            this.currentPostId = postId;
            this.currentButton = $button;
            
            // Get file extension from current filename
            var $filenameInput = $button.closest('.compat-field-image_seo_name').find('input[name*="image_seo_name"]');
            if ($filenameInput.length) {
                // Try to get extension from attachment data or assume common image extension
                this.currentFileExtension = this.getFileExtension(postId) || 'jpg';
            }

            // Show modal with loading state
            $('#fmrseo-ai-suggestions-modal').show();
            this.showLoadingState();

            // Fetch AI suggestions
            this.fetchAISuggestions(postId);
        },

        fetchAISuggestions: function(postId) {
            var self = this;

            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'fmr_ai_get_suggestions',
                    post_id: postId,
                    count: 3,
                    _ajax_nonce: fmrAI.nonce
                },
                success: function(response) {
                    if (response.success && response.data.suggestions) {
                        self.displaySuggestions(response.data.suggestions);
                    } else {
                        self.showError(response.data ? response.data.message : fmrAI.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Network error: ' + error);
                }
            });
        },

        displaySuggestions: function(suggestions) {
            var self = this;
            
            if (!suggestions || suggestions.length === 0) {
                this.showError(fmrAI.strings.no_suggestions || 'No suggestions available');
                return;
            }

            var bodyHtml = '<div class="fmrseo-ai-modal-description">' +
                fmrAI.strings.select_suggestion +
                '</div>' +
                '<ul class="fmrseo-ai-suggestions-list">';

            suggestions.forEach(function(suggestion, index) {
                var suggestionName = typeof suggestion === 'string' ? suggestion : suggestion.name;
                var suggestionId = 'suggestion-' + index;
                
                bodyHtml += '<li class="fmrseo-ai-suggestion-item" data-suggestion="' + suggestionName + '">' +
                    '<input type="radio" name="ai-suggestion" id="' + suggestionId + '" value="' + suggestionName + '">' +
                    '<label for="' + suggestionId + '">' +
                        '<div class="fmrseo-ai-suggestion-name">' + suggestionName + '</div>' +
                        '<div class="fmrseo-ai-suggestion-preview">' + suggestionName + '.' + self.currentFileExtension + '</div>' +
                    '</label>' +
                    '</li>';
            });

            bodyHtml += '</ul>' +
                '<div class="fmrseo-ai-preview-section" style="display: none;">' +
                    '<div class="fmrseo-ai-preview-label">' + fmrAI.strings.preview_label + '</div>' +
                    '<div class="fmrseo-ai-preview-filename"></div>' +
                '</div>';

            $('.fmrseo-ai-modal-body').html(bodyHtml);
            this.updateFooter();
        },

        showError: function(message) {
            var errorHtml = '<div class="fmrseo-ai-modal-error">' +
                    '<div class="fmrseo-ai-modal-error-message">' + fmrAI.strings.error + '</div>' +
                    '<div class="fmrseo-ai-modal-error-details">' + message + '</div>' +
                '</div>' +
                '<p>You can close this dialog and try manual rename instead.</p>';

            $('.fmrseo-ai-modal-body').html(errorHtml);
            this.updateFooter(true); // Show only close button
        },

        showLoadingState: function() {
            var loadingHtml = '<div class="fmrseo-ai-modal-loading">' +
                    '<span class="fmrseo-loading-spinner"></span>' +
                    fmrAI.strings.processing +
                '</div>';

            $('.fmrseo-ai-modal-body').html(loadingHtml);
            $('.fmrseo-ai-modal-footer').remove(); // Remove footer during loading
        },

        updateFooter: function(errorState) {
            // Remove existing footer
            $('.fmrseo-ai-modal-footer').remove();

            var footerHtml;
            if (errorState) {
                footerHtml = '<div class="fmrseo-ai-modal-footer">' +
                        '<button type="button" class="button fmrseo-ai-modal-cancel">' + fmrAI.strings.cancel + '</button>' +
                    '</div>';
            } else {
                footerHtml = '<div class="fmrseo-ai-modal-footer">' +
                        '<button type="button" class="button fmrseo-ai-modal-cancel">' + fmrAI.strings.cancel + '</button>' +
                        '<button type="button" class="button button-primary fmrseo-ai-modal-apply" disabled>' + fmrAI.strings.apply_rename + '</button>' +
                    '</div>';
            }

            $('.fmrseo-ai-modal-content').append(footerHtml);
        },

        selectSuggestion: function($item) {
            var suggestionName = $item.data('suggestion');
            
            // Update UI
            $('.fmrseo-ai-suggestion-item').removeClass('selected');
            $item.addClass('selected');
            $item.find('input[type="radio"]').prop('checked', true);

            // Show preview
            $('.fmrseo-ai-preview-section').show();
            $('.fmrseo-ai-preview-filename').text(suggestionName + '.' + this.currentFileExtension);

            // Enable apply button
            $('.fmrseo-ai-modal-apply').prop('disabled', false);
        },

        applySelectedSuggestion: function() {
            var selectedSuggestion = $('input[name="ai-suggestion"]:checked').val();
            
            if (!selectedSuggestion) {
                alert(fmrAI.strings.please_select);
                return;
            }

            var self = this;
            var $applyButton = $('.fmrseo-ai-modal-apply');
            var originalText = $applyButton.text();

            // Show applying state
            $applyButton.prop('disabled', true).text(fmrAI.strings.applying);

            // Perform the actual rename
            $.ajax({
                url: fmrAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'fmr_ai_rename_single',
                    post_id: this.currentPostId,
                    selected_name: selectedSuggestion,
                    _ajax_nonce: fmrAI.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.handleRenameSuccess(response.data);
                    } else {
                        self.handleRenameError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    self.handleRenameError({
                        message: 'Network error: ' + error
                    });
                },
                complete: function() {
                    $applyButton.prop('disabled', false).text(originalText);
                }
            });
        },

        handleRenameSuccess: function(data) {
            // Update filename display if available
            var $filenameDisplay = this.currentButton.closest('.compat-field-image_seo_name').find('input[name*="image_seo_name"]');
            if ($filenameDisplay.length && data.filename) {
                var nameWithoutExt = data.filename.replace(/\.[^/.]+$/, "");
                $filenameDisplay.val(nameWithoutExt);
            }

            // Close modal
            this.closeModal();

            // Show success message
            if (typeof FMRErrorHandler !== 'undefined') {
                FMRErrorHandler.showErrorMessage(data.message || fmrAI.strings.success, 'success');
            } else {
                alert(data.message || fmrAI.strings.success);
            }
        },

        handleRenameError: function(data) {
            var message = data.message || fmrAI.strings.error;
            
            // Close modal first
            this.closeModal();
            
            // Use error handler if available
            if (typeof FMRErrorHandler !== 'undefined') {
                FMRErrorHandler.showErrorMessage(message, 'error');
            } else {
                alert(message);
            }
        },

        closeModal: function() {
            $('#fmrseo-ai-suggestions-modal').hide();
            this.currentPostId = null;
            this.currentButton = null;
            this.currentFileExtension = null;
        },

        getFileExtension: function(postId) {
            // Try to get file extension from various sources
            // This is a simplified version - in a real implementation you might
            // want to make an AJAX call to get the actual file extension
            
            // Look for attachment data in the page
            var $attachmentData = $('[data-id="' + postId + '"]');
            if ($attachmentData.length) {
                var filename = $attachmentData.data('filename') || $attachmentData.find('.filename').text();
                if (filename) {
                    var ext = filename.split('.').pop();
                    if (ext && ext.length <= 4) {
                        return ext.toLowerCase();
                    }
                }
            }

            // Default to jpg for images
            return 'jpg';
        },

        // Legacy method for backward compatibility
        performAIRename: function(postId, $button) {
            // This method is kept for backward compatibility
            // but now redirects to the modal interface
            this.showAISuggestionsModal(postId, $button);
        },

        // Legacy methods for backward compatibility
        handleSuccess: function(data, $button) {
            this.handleRenameSuccess(data);
        },

        handleError: function(data, $button) {
            this.handleRenameError(data);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        FMRAIRename.init();
    });

})(jQuery);