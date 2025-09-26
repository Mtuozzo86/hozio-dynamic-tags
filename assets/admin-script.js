jQuery(document).ready(function($) {
    'use strict';

    // Color Picker Synchronization
    $('.hozio-color-picker-wrapper').each(function() {
        const $wrapper = $(this);
        const $textInput = $wrapper.find('.hozio-color-picker');
        const $colorInput = $wrapper.find('.hozio-color-input');

        // Sync text input to color input
        $textInput.on('input', function() {
            const value = $(this).val();
            if (/^#[0-9A-F]{6}$/i.test(value)) {
                $colorInput.val(value);
            }
        });

        // Sync color input to text input
        $colorInput.on('input', function() {
            $textInput.val($(this).val());
        });
    });

    // Start Year Auto-calculation
    $('#hozio_start_year').on('input', function() {
        const startYear = parseInt($(this).val());
        const currentYear = new Date().getFullYear();
        
        if (startYear && startYear >= 1900 && startYear <= currentYear) {
            const yearsOfExperience = currentYear - startYear;
            $('.hozio-calculated-value .highlight').text(yearsOfExperience + ' years');
        } else {
            $('.hozio-calculated-value .highlight').text('0 years');
        }
    });

    // Reset Button Functionality
    $('.hozio-reset-btn').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to reset all fields to default? This action cannot be undone.')) {
            $('.hozio-input, .hozio-textarea, .hozio-input-number, .hozio-color-picker').val('');
            $('.hozio-color-input').val('#000000');
            $('.hozio-calculated-value .highlight').text('0 years');
            
            // Show confirmation message
            showNotification('All fields have been reset to default values. Remember to click "Save All Settings" to keep these changes.', 'success');
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
        }
    });

    // Form Validation
    $('.hozio-form').on('submit', function(e) {
        let isValid = true;
        let errorMessage = '';

        // Validate email fields
        $('input[id*="email"]').each(function() {
            const email = $(this).val().trim();
            if (email && !isValidEmail(email)) {
                isValid = false;
                $(this).addClass('error-field');
                errorMessage += 'Please enter a valid email address.\n';
            } else {
                $(this).removeClass('error-field');
            }
        });

        // Validate URL fields
        $('input[id*="_url"], input[id*="_link"]').each(function() {
            const url = $(this).val().trim();
            if (url && !isValidURL(url)) {
                isValid = false;
                $(this).addClass('error-field');
                errorMessage += 'Please enter a valid URL (must start with http:// or https://).\n';
            } else {
                $(this).removeClass('error-field');
            }
        });

        // Validate phone numbers
        $('input[id*="phone"]').each(function() {
            const phone = $(this).val().trim();
            if (phone && !isValidPhone(phone)) {
                isValid = false;
                $(this).addClass('error-field');
                errorMessage += 'Please enter a valid phone number.\n';
            } else {
                $(this).removeClass('error-field');
            }
        });

        if (!isValid) {
            e.preventDefault();
            showNotification(errorMessage, 'error');
        } else {
            showNotification('Saving settings...', 'success');
        }
    });

    // Add error styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .error-field {
                border-color: #ef4444 !important;
                animation: shake 0.3s;
            }
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        `)
        .appendTo('head');

    // Smooth scroll to sections
    $('.hozio-section-header').on('click', function() {
        $(this).closest('.hozio-section').toggleClass('collapsed');
    });

    // Auto-save indicator (optional)
    let saveTimeout;
    $('.hozio-input, .hozio-textarea, .hozio-input-number').on('input', function() {
        clearTimeout(saveTimeout);
        const $field = $(this);
        
        saveTimeout = setTimeout(function() {
            $field.addClass('auto-saved');
            setTimeout(function() {
                $field.removeClass('auto-saved');
            }, 1000);
        }, 500);
    });

    // Add auto-save styling
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .auto-saved {
                border-color: #10b981 !important;
                transition: border-color 0.3s ease;
            }
        `)
        .appendTo('head');

    // Helper Functions
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidURL(url) {
        const urlRegex = /^https?:\/\/.+/i;
        return urlRegex.test(url);
    }

    function isValidPhone(phone) {
        // Basic phone validation (accepts various formats)
        const phoneRegex = /^[\d\s\-\(\)\+\.]+$/;
        return phone.length >= 10 && phoneRegex.test(phone);
    }

    function showNotification(message, type) {
        const typeClass = type === 'error' ? 'error' : (type === 'success' ? 'updated' : 'notice');
        const $notice = $('<div>')
            .addClass('notice ' + typeClass + ' is-dismissible hozio-notification')
            .html('<p>' + message + '</p>')
            .css({
                'position': 'fixed',
                'top': '32px',
                'right': '20px',
                'z-index': '99999',
                'max-width': '400px',
                'animation': 'slideInRight 0.3s ease'
            });

        $('body').append($notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Add slide-in animation
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `)
        .appendTo('head');

    // URL Preview for social media fields
    $('input[id*="_url"], input[id*="_link"]').each(function() {
        const $input = $(this);
        const $wrapper = $input.closest('.hozio-field-wrapper');
        
        if ($input.val()) {
            addPreviewLink($input, $wrapper);
        }

        $input.on('blur', function() {
            $wrapper.find('.hozio-url-preview').remove();
            if ($(this).val() && isValidURL($(this).val())) {
                addPreviewLink($(this), $wrapper);
            }
        });
    });

    function addPreviewLink($input, $wrapper) {
        const url = $input.val();
        const $preview = $('<a>')
            .addClass('hozio-url-preview')
            .attr('href', url)
            .attr('target', '_blank')
            .attr('rel', 'noopener noreferrer')
            .html('<span class="dashicons dashicons-external"></span> Preview Link')
            .css({
                'display': 'inline-flex',
                'align-items': 'center',
                'gap': '4px',
                'margin-top': '8px',
                'color': '#6366f1',
                'text-decoration': 'none',
                'font-size': '13px',
                'transition': 'color 0.2s ease'
            });

        $preview.hover(
            function() { $(this).css('color', '#4f46e5'); },
            function() { $(this).css('color', '#6366f1'); }
        );

        $wrapper.append($preview);
    }

    // Character counter for textarea fields
    $('.hozio-textarea').each(function() {
        const $textarea = $(this);
        const maxLength = 1000; // Set a reasonable max length
        
        const $counter = $('<div>')
            .addClass('hozio-char-counter')
            .css({
                'text-align': 'right',
                'font-size': '12px',
                'color': '#6b7280',
                'margin-top': '4px'
            });

        $textarea.after($counter);
        updateCounter($textarea, $counter, maxLength);

        $textarea.on('input', function() {
            updateCounter($(this), $counter, maxLength);
        });
    });

    function updateCounter($textarea, $counter, maxLength) {
        const currentLength = $textarea.val().length;
        $counter.text(currentLength + ' / ' + maxLength + ' characters');
        
        if (currentLength > maxLength * 0.9) {
            $counter.css('color', '#ef4444');
        } else {
            $counter.css('color', '#6b7280');
        }
    }

    // Add tooltips for better UX (optional)
    $('.hozio-field label').each(function() {
        const $label = $(this);
        const fieldId = $label.closest('.hozio-field').find('input, textarea').attr('id');
        
        const tooltips = {
            'hozio_company_phone_1': 'Primary contact phone number for your business',
            'hozio_sms_phone': 'Phone number that can receive SMS messages',
            'hozio_to_email_contact_form': 'Separate multiple emails with commas',
            'hozio_start_year': 'The year your business was established'
        };

        if (tooltips[fieldId]) {
            $label.append(
                $('<span>')
                    .addClass('dashicons dashicons-info')
                    .attr('title', tooltips[fieldId])
                    .css({
                        'font-size': '16px',
                        'color': '#6b7280',
                        'cursor': 'help',
                        'margin-left': '6px'
                    })
            );
        }
    });
});
