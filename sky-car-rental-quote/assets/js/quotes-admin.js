/**
 * Car Rental Quotes Admin Scripts - Updated with Mobile Enhancements
 * Mobile enhancements and responsive functionality
 */

jQuery(document).ready(function($) {
    // Mobile detection
    var isMobile = window.matchMedia("(max-width: 782px)").matches;
    var isTablet = window.matchMedia("(min-width: 601px) and (max-width: 782px)").matches;
    
    // Initialize edit page enhancements
    initEditPageEnhancements();
    
    // Make Quote ID clickable on desktop (for main quotes page)
    if (!isMobile && $('.column-primary strong').length) {
        $('.column-primary strong').css('cursor', 'pointer').on('click', function(e) {
            e.preventDefault();
            var $row = $(this).closest('tr');
            var quoteId = $row.find('input[type="checkbox"]').val();
            if (quoteId) {
                window.location.href = 'admin.php?page=car-rental-quotes&action=edit&quote_id=' + quoteId;
            }
        });
    }
    
    // Initialize mobile enhancements for quotes list
    if ((isMobile || isTablet) && $('.wp-list-table').length) {
        initMobileEnhancements();
    }
    
    // Select all checkboxes
    $('#cb-select-all, #cb-select-all-2').on('click', function() {
        var checked = $(this).prop('checked');
        $('tbody input[type="checkbox"]').prop('checked', checked);
    });
    
    // Confirm bulk delete
    $('form').on('submit', function(e) {
        var action = $('#bulk-action-selector').val();
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete the selected quotes?')) {
                e.preventDefault();
            }
        }
    });
    
    // Confirm single delete
    $(document).on('click', '.delete', function(e) {
        if (!confirm('Are you sure you want to delete this quote?')) {
            e.preventDefault();
        }
    });
    
    // Handle window resize
    var resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            var newIsMobile = window.matchMedia("(max-width: 782px)").matches;
            var newIsTablet = window.matchMedia("(min-width: 601px) and (max-width: 782px)").matches;
            
            if ((newIsMobile || newIsTablet) && !$('body').hasClass('crqa-mobile')) {
                location.reload(); // Reload to properly initialize mobile view
            } else if (!newIsMobile && !newIsTablet && $('body').hasClass('crqa-mobile')) {
                location.reload(); // Reload to properly initialize desktop view
            }
        }, 250);
    });
    
    /**
     * Initialize edit page specific enhancements
     */
    function initEditPageEnhancements() {
        // Only run on edit pages
        if (!$('.crqa-edit-quote').length) {
            return;
        }
        
        // Auto-calculate price functionality
        if ($('#auto-calculate-price').length) {
            $('#auto-calculate-price').on('click', function() {
                var $button = $(this);
                var pricePerDay = parseFloat($button.data('price-per-day')) || 0;
                var rentalDays = parseFloat($button.data('rental-days')) || 1;
                var suggestedPrice = pricePerDay * rentalDays;
                
                if (suggestedPrice > 0) {
                    if (confirm('Auto-fill rental price with ' + suggestedPrice.toFixed(2) + '?')) {
                        $('input[name="rental_price"]').val(suggestedPrice.toFixed(2));
                        updateTotal();
                        
                        // Add visual feedback
                        $('input[name="rental_price"]').addClass('crqa-updated');
                        setTimeout(function() {
                            $('input[name="rental_price"]').removeClass('crqa-updated');
                        }, 1000);
                    }
                }
            });
        }
        
        // Enhanced total calculation
        $('input[name="rental_price"], input[name="deposit_amount"]').on('input change', function() {
            updateTotal();
        });
        
        // Phone number formatting
        $('input[name="customer_phone"]').on('input', function() {
            var value = $(this).val();
            // Simple UK phone number formatting
            if (value.length === 11 && value.startsWith('07')) {
                $(this).val('+44' + value.substring(1));
            }
        });
        
        // Form validation enhancements
        $('#quote-edit-form').on('submit', function(e) {
            var errors = [];
            
            // Check required fields
            if (!$('input[name="customer_name"]').val().trim()) {
                errors.push('Customer name is required');
            }
            
            if (!$('input[name="customer_email"]').val().trim()) {
                errors.push('Customer email is required');
            }
            
            if (!$('input[name="vehicle_name"]').val().trim()) {
                errors.push('Vehicle name is required');
            }
            
            // Validate email format
            var email = $('input[name="customer_email"]').val();
            if (email && !isValidEmail(email)) {
                errors.push('Please enter a valid email address');
            }
            
            // Validate price fields
            var rentalPrice = parseFloat($('input[name="rental_price"]').val()) || 0;
            var depositAmount = parseFloat($('input[name="deposit_amount"]').val()) || 0;
            
            if (rentalPrice < 0) {
                errors.push('Rental price cannot be negative');
            }
            
            if (depositAmount < 0) {
                errors.push('Deposit amount cannot be negative');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
        });
        
        // Auto-save functionality (optional)
        if (typeof crqaAutoSave !== 'undefined' && crqaAutoSave) {
            var autoSaveTimer;
            $('#quote-edit-form input, #quote-edit-form select, #quote-edit-form textarea').on('change', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    // Auto-save logic here
                    console.log('Auto-saving quote...');
                }, 5000);
            });
        }
    }
    
    /**
     * Update total amount display
     */
    function updateTotal() {
        var rentalPrice = parseFloat($('input[name="rental_price"]').val()) || 0;
        var depositAmount = parseFloat($('input[name="deposit_amount"]').val()) || 0;
        var total = rentalPrice + depositAmount;
        var currencySymbol = $('#total-amount').data('currency') || '$';
        
        $('#total-amount').text(currencySymbol + total.toFixed(2));
        
        // Add animation effect
        $('#total-display').addClass('crqa-updated');
        setTimeout(function() {
            $('#total-display').removeClass('crqa-updated');
        }, 300);
    }
    
    /**
     * Validate email format
     */
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * Mobile enhancements for quotes list page
     */
    function initMobileEnhancements() {
        // Add mobile class to body
        $('body').addClass('crqa-mobile');
        
        // Add customer names to mobile headers
        $('.wp-list-table tbody tr').each(function() {
            var $row = $(this);
            var customerName = $row.find('td:nth-child(3) .crqa-customer-info .name').text();
            if (customerName && $row.find('.mobile-customer-name').length === 0) {
                $row.find('.column-primary strong').after('<span class="mobile-customer-name">' + customerName + '</span>');
            }
        });
        
        // Handle expand/collapse on mobile
        $('.wp-list-table .column-primary').on('click', function(e) {
            // Don't trigger if clicking on checkbox
            if ($(e.target).is('input[type="checkbox"]')) {
                return;
            }
            
            var $row = $(this).closest('tr');
            
            // Toggle expanded class
            $row.toggleClass('expanded');
            
            // Close other expanded rows (accordion behavior)
            $('.wp-list-table tbody tr').not($row).removeClass('expanded');
            
            // Update aria-expanded for accessibility
            var isExpanded = $row.hasClass('expanded');
            $(this).attr('aria-expanded', isExpanded ? 'true' : 'false');
        });
        
        // Prevent checkbox clicks from toggling expansion
        $('.check-column input').on('click', function(e) {
            e.stopPropagation();
        });
        
        // Enhance touch interactions
        $('.row-actions a').on('touchstart', function() {
            $(this).addClass('touch-active');
        }).on('touchend', function() {
            $(this).removeClass('touch-active');
        });
        
        // Improve filter UX on mobile
        enhanceMobileFilters();
    }
    
    /**
     * Enhance mobile filters
     */
    function enhanceMobileFilters() {
        // Create mobile filter toggle if it doesn't exist
        if (!$('.crqa-filter-toggle').length) {
            var $filterToggle = $('<button class="button crqa-filter-toggle">Filter Quotes</button>');
            $('.wrap h1').after($filterToggle);
        }
        
        var $filterToggle = $('.crqa-filter-toggle');
        
        // Hide filters by default on mobile
        $('.tablenav.top').removeClass('mobile-visible').addClass('mobile-hidden');
        
        // Toggle filters
        $filterToggle.on('click', function(e) {
            e.preventDefault();
            var $filters = $('.tablenav.top');
            
            if ($filters.hasClass('mobile-visible')) {
                $filters.removeClass('mobile-visible').addClass('mobile-hidden');
                $(this).removeClass('active').text('Filter Quotes');
            } else {
                $filters.removeClass('mobile-hidden').addClass('mobile-visible');
                $(this).addClass('active').text('Hide Filters');
            }
        });
        
        // Auto-hide filters after submit
        $('.tablenav.top form').on('submit', function() {
            setTimeout(function() {
                $('.tablenav.top').removeClass('mobile-visible').addClass('mobile-hidden');
                $filterToggle.removeClass('active').text('Filter Quotes');
            }, 100);
        });
    }
    
    // Add tooltips for desktop
    if (!isMobile) {
        $('.column-primary strong').attr('title', 'Click to edit this quote');
    }
    
    // Smooth scroll for mobile pagination
    if (isMobile) {
        $('.page-numbers').on('click', function(e) {
            if (!$(this).hasClass('current')) {
                $('html, body').animate({
                    scrollTop: $('.wrap').offset().top - 50
                }, 300);
            }
        });
    }
    
    // Accessibility improvements
    $('.status-badge').attr('role', 'status');
    $('.row-actions').attr('role', 'navigation');
    $('.row-actions').attr('aria-label', 'Quote actions');
    
    // Make mobile cards accessible
    if (isMobile) {
        $('.column-primary').attr('role', 'button').attr('aria-expanded', 'false');
        $('.column-primary').on('click', function() {
            var isExpanded = $(this).closest('tr').hasClass('expanded');
            $(this).attr('aria-expanded', isExpanded ? 'true' : 'false');
        });
    }
    
    // Enhanced keyboard navigation
    $(document).on('keydown', function(e) {
        // Escape key to close expanded mobile rows
        if (e.key === 'Escape' && isMobile) {
            $('.wp-list-table tbody tr.expanded').removeClass('expanded');
            $('.column-primary[aria-expanded="true"]').attr('aria-expanded', 'false');
        }
    });
    
    // Call/WhatsApp button enhancements
    $('.crqa-call-btn, .crqa-whatsapp-btn').on('click', function(e) {
        var $btn = $(this);
        var action = $btn.hasClass('crqa-call-btn') ? 'call' : 'whatsapp';
        
        // Add visual feedback
        $btn.addClass('crqa-action-clicked');
        setTimeout(function() {
            $btn.removeClass('crqa-action-clicked');
        }, 1000);
        
        // Track interaction (if analytics is available)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'contact_customer', {
                'event_category': 'quote_management',
                'event_label': action,
                'transport_type': 'beacon'
            });
        }
    });
    
    // Add loading states for form submissions
    $('#quote-edit-form').on('submit', function() {
        var $submitBtn = $(this).find('input[type="submit"], button[type="submit"]');
        $submitBtn.prop('disabled', true);
        
        if ($submitBtn.is('input')) {
            $submitBtn.data('original-value', $submitBtn.val());
            $submitBtn.val('Saving...');
        } else {
            $submitBtn.data('original-html', $submitBtn.html());
            $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        }
    });
    
    // Restore button states if form validation fails
    $(window).on('beforeunload', function() {
        $('#quote-edit-form input[type="submit"], #quote-edit-form button[type="submit"]').each(function() {
            var $btn = $(this);
            $btn.prop('disabled', false);
            
            if ($btn.is('input') && $btn.data('original-value')) {
                $btn.val($btn.data('original-value'));
            } else if ($btn.data('original-html')) {
                $btn.html($btn.data('original-html'));
            }
        });
    });
});

// Additional CSS classes for animations
jQuery(document).ready(function($) {
    // Add CSS for updated field animation
    if (!$('#crqa-dynamic-styles').length) {
        $('<style id="crqa-dynamic-styles">')
            .text(`
                .crqa-updated {
                    animation: crqa-highlight 0.6s ease-in-out;
                }
                
                @keyframes crqa-highlight {
                    0% { background-color: transparent; }
                    50% { background-color: #e7f3ff; }
                    100% { background-color: transparent; }
                }
                
                .crqa-action-clicked {
                    transform: scale(0.95);
                    transition: transform 0.1s ease-in-out;
                }
                
                .touch-active {
                    background-color: rgba(0,0,0,0.1) !important;
                }
                
                .crqa-calculating {
                    pointer-events: none;
                    opacity: 0.6;
                    position: relative;
                }
            `)
            .appendTo('head');
    }
});