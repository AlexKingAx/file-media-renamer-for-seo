jQuery(document).ready(function($) {
    
    // Show modal when bulk rename is triggered
    if (typeof fmrseoBulkRenameIds !== 'undefined' && fmrseoBulkRenameIds.length > 0) {
        $('#fmrseo-bulk-rename-modal').show();
    }
    
    // Close modal
    $('.fmrseo-close, #fmrseo-cancel-bulk').on('click', function() {
        $('#fmrseo-bulk-rename-modal').hide();
        // Remove query parameter and reload
        var url = window.location.href;
        url = url.replace(/[?&]fmrseo_bulk_rename=1/, '');
        window.history.replaceState({}, document.title, url);
    });
    
    // Start bulk rename process
    $('#fmrseo-start-bulk').on('click', function() {
        var baseName = $('#fmrseo-bulk-name').val().trim();
        
        if (!baseName) {
            alert('Please enter a base name for the files.');
            $('#fmrseo-bulk-name').focus();
            return;
        }
        
        // Validate base name (basic validation)
        if (!/^[a-zA-Z0-9\-_]+$/.test(baseName)) {
            alert('Base name can only contain letters, numbers, hyphens and underscores.');
            $('#fmrseo-bulk-name').focus();
            return;
        }
        
        // Confirm action
        if (!confirm('Are you sure you want to rename ' + fmrseoBulkRenameIds.length + ' files?')) {
            return;
        }
        
        // Disable button and show progress
        $(this).prop('disabled', true);
        $('#fmrseo-cancel-bulk').prop('disabled', true);
        $('.fmrseo-progress').show();
        $('.fmrseo-results').empty().show();
        
        // Start processing
        processBulkRename(fmrseoBulkRenameIds, baseName);
    });
    
    function processBulkRename(postIds, baseName) {
        $.ajax({
            url: fmrseoBulkRename.ajax_url,
            type: 'POST',
            data: {
                action: 'fmrseo_bulk_rename',
                post_ids: postIds,
                base_name: baseName,
                nonce: fmrseoBulkRename.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                    updateProgress(100);
                    $('.fmrseo-progress-text').text(fmrseoBulkRename.strings.completed);
                } else {
                    displayError(response.data.message);
                }
            },
            error: function() {
                displayError(fmrseoBulkRename.strings.error);
            },
            complete: function() {
                // Re-enable buttons
                $('#fmrseo-start-bulk').prop('disabled', false);
                $('#fmrseo-cancel-bulk').prop('disabled', false);
                
                // Add close button to reload page
                $('.fmrseo-modal-footer').append(
                    '<button type="button" class="button button-primary" onclick="window.location.reload()">' +
                    'Close and Reload' +
                    '</button>'
                );
            }
        });
    }
    
    function updateProgress(percentage) {
        $('.fmrseo-progress-fill').css('width', percentage + '%');
        $('.fmrseo-progress-text').text(percentage + '%');
    }
    
    function displayResults(results) {
        var html = '<h4>Results:</h4><ul>';
        
        results.forEach(function(result) {
            var statusClass = result.success ? 'success' : 'error';
            var statusIcon = result.success ? '✓' : '✗';
            
            html += '<li class="fmrseo-result-' + statusClass + '">';
            html += '<span class="fmrseo-status-icon">' + statusIcon + '</span> ';
            
            if (result.success) {
                html += '<strong>' + result.old_name + '</strong> → <strong>' + result.new_name + '</strong>';
            } else {
                html += 'ID: ' + result.post_id + ' - ' + result.message;
            }
            
            html += '</li>';
        });
        
        html += '</ul>';
        $('.fmrseo-results').html(html);
    }
    
    function displayError(message) {
        $('.fmrseo-results').html('<div class="fmrseo-error">Error: ' + message + '</div>');
        updateProgress(0);
    }
    
    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if (event.target.id === 'fmrseo-bulk-rename-modal') {
            $('#fmrseo-bulk-rename-modal').hide();
        }
    });
    
});