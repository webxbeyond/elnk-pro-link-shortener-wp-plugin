jQuery(document).ready(function($) {
    // Toggle between single and bulk mode
    $('#is_bulk').change(function() {
        if ($(this).is(':checked')) {
            $('#single_url_row').hide();
            $('#multiple_urls_row').show();
            $('#destination_url').removeAttr('required');
            $('#multiple_urls').attr('required', 'required');
        } else {
            $('#single_url_row').show();
            $('#multiple_urls_row').hide();
            $('#destination_url').attr('required', 'required');
            $('#multiple_urls').removeAttr('required');
        }
    });
    
    // Form submission handling
    $('.elnk-pro-form').submit(function(e) {
        var form = $(this);
        var submitButton = form.find('input[type="submit"]:focus');
        
        // Add loading state
        form.addClass('loading');
        submitButton.prop('disabled', true);
        
        // Basic validation
        var isValid = true;
        var errorMessage = '';
        
        // Check if it's URL creation form
        if (form.find('#destination_url').length > 0) {
            if ($('#is_bulk').is(':checked')) {
                if (!$('#multiple_urls').val().trim()) {
                    isValid = false;
                    errorMessage = 'Multiple URLs field is required for bulk mode.';
                }
            } else {
                if (!$('#destination_url').val().trim()) {
                    isValid = false;
                    errorMessage = 'Destination URL is required.';
                }
            }
        }
        
        // Check API key for settings form
        if (form.find('#api_key').length > 0 && !$('#api_key').val().trim()) {
            isValid = false;
            errorMessage = 'API Key is required.';
        }
        
        if (!isValid) {
            alert(errorMessage);
            form.removeClass('loading');
            submitButton.prop('disabled', false);
            return false;
        }
        
        return true;
    });
    
    // Auto-select text in short URL inputs when clicked
    $(document).on('click', '.short-url-input', function() {
        $(this).select();
    });
    
    // Copy button functionality
    $(document).on('click', '.copy-btn', function(e) {
        e.preventDefault();
        var url = $(this).data('url');
        var input = $(this).siblings('.short-url-input');
        
        input.select();
        document.execCommand('copy');
        
        var button = $(this);
        var originalText = button.text();
        button.text('Copied!').addClass('copied');
        
        setTimeout(function() {
            button.text(originalText).removeClass('copied');
        }, 2000);
    });
    
    // URL validation for single mode
    $('#destination_url').on('blur', function() {
        var url = $(this).val();
        if (url && !isValidUrl(url)) {
            alert('Please enter a valid URL (including http:// or https://)');
            $(this).focus();
        }
    });
    
    // URL validation for bulk mode
    $('#multiple_urls').on('blur', function() {
        var urls = $(this).val().split('\n');
        var invalidUrls = [];
        
        urls.forEach(function(url, index) {
            url = url.trim();
            if (url && !isValidUrl(url)) {
                invalidUrls.push('Line ' + (index + 1) + ': ' + url);
            }
        });
        
        if (invalidUrls.length > 0) {
            alert('Invalid URLs found:\n' + invalidUrls.join('\n') + '\n\nPlease ensure all URLs include http:// or https://');
        }
    });
    
    // Helper function to validate URLs
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }
    
    // Show/hide API key
    if ($('#api_key').length > 0) {
        var toggleApiKey = $('<button type="button" class="button" style="margin-left: 10px;">Hide</button>');
        $('#api_key').after(toggleApiKey);
        
        // Start with text field hidden (password-like)
        $('#api_key').attr('type', 'password');
        toggleApiKey.text('Show');
        
        toggleApiKey.click(function() {
            var apiKeyField = $('#api_key');
            if (apiKeyField.attr('type') === 'password') {
                apiKeyField.attr('type', 'text');
                $(this).text('Hide');
            } else {
                apiKeyField.attr('type', 'password');
                $(this).text('Show');
            }
        });
    }
    
    // Delete URL functionality
    $('.delete-url-btn').click(function() {
        var button = $(this);
        var urlId = button.data('url-id');
        var row = button.closest('tr');
        
        if (confirm('Are you sure you want to delete this URL? This action cannot be undone.')) {
            button.prop('disabled', true);
            button.html('<span class="dashicons dashicons-update-alt"></span>');
            
            $.ajax({
                url: elnkProAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'elnk_pro_delete_url',
                    url_id: urlId,
                    nonce: elnkProAjax.deleteNonce
                },
                success: function(response) {
                    if (response.success) {
                        row.fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Show success message
                        var notice = $('<div class="notice notice-success is-dismissible"><p>URL deleted successfully!</p></div>');
                        $('.wrap h1').after(notice);
                        
                        // Auto-hide notice after 3 seconds
                        setTimeout(function() {
                            notice.fadeOut();
                        }, 3000);
                    } else {
                        // If API deletion failed, offer to delete from WP database only
                        if (confirm('Failed to delete from elnk.pro: ' + response.data + '\n\nWould you like to delete it from your WordPress database only? (The link will remain active on elnk.pro)')) {
                            // Try database-only deletion
                            $.ajax({
                                url: elnkProAjax.ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'elnk_pro_delete_url',
                                    url_id: urlId,
                                    database_only: true,
                                    nonce: elnkProAjax.deleteNonce
                                },
                                success: function(dbResponse) {
                                    if (dbResponse.success) {
                                        row.fadeOut(300, function() {
                                            $(this).remove();
                                        });
                                        
                                        // Show success message
                                        var notice = $('<div class="notice notice-success is-dismissible"><p>URL removed from your WordPress database successfully!</p></div>');
                                        $('.wrap h1').after(notice);
                                        
                                        // Auto-hide notice after 3 seconds
                                        setTimeout(function() {
                                            notice.fadeOut();
                                        }, 3000);
                                    } else {
                                        alert('Error deleting from database: ' + dbResponse.data);
                                    }
                                    button.prop('disabled', false);
                                    button.html('<span class="dashicons dashicons-trash"></span>');
                                },
                                error: function() {
                                    alert('An error occurred while deleting from database.');
                                    button.prop('disabled', false);
                                    button.html('<span class="dashicons dashicons-trash"></span>');
                                }
                            });
                        } else {
                            button.prop('disabled', false);
                            button.html('<span class="dashicons dashicons-trash"></span>');
                        }
                    }
                },
                error: function() {
                    // Network error - offer database-only deletion
                    if (confirm('Network error occurred. Would you like to delete from your WordPress database only? (The link will remain active on elnk.pro)')) {
                        $.ajax({
                            url: elnkProAjax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'elnk_pro_delete_url',
                                url_id: urlId,
                                database_only: true,
                                nonce: elnkProAjax.deleteNonce
                            },
                            success: function(dbResponse) {
                                if (dbResponse.success) {
                                    row.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                    
                                    // Show success message
                                    var notice = $('<div class="notice notice-success is-dismissible"><p>URL removed from your WordPress database successfully!</p></div>');
                                    $('.wrap h1').after(notice);
                                    
                                    // Auto-hide notice after 3 seconds
                                    setTimeout(function() {
                                        notice.fadeOut();
                                    }, 3000);
                                } else {
                                    alert('Error deleting from database: ' + dbResponse.data);
                                }
                                button.prop('disabled', false);
                                button.html('<span class="dashicons dashicons-trash"></span>');
                            },
                            error: function() {
                                alert('An error occurred while deleting from database.');
                                button.prop('disabled', false);
                                button.html('<span class="dashicons dashicons-trash"></span>');
                            }
                        });
                    } else {
                        button.prop('disabled', false);
                        button.html('<span class="dashicons dashicons-trash"></span>');
                    }
                }
            });
        }
    });
});
