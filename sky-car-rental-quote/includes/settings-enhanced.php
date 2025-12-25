<?php
/**
 * Enhanced Settings Module for Car Rental Quote Automation - WPForms Only
 * 
 * This file handles settings with WPForms support
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/settings-enhanced.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced settings page with WPForms support
 */
function crqa_settings_page_enhanced() {
    // Enqueue necessary scripts
    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');
    
    // Get current tab
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
    
    // Handle form submission
    if (isset($_POST['submit']) && isset($_POST['crqa_settings_nonce'])) {
        // Get the tab from POST data if available (in case URL doesn't have it)
        $save_tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : $active_tab;
        crqa_save_enhanced_settings($save_tab);
    }
    
    // Get saved options
    $company_name = get_option('crqa_company_name', 'Your Car Rental Company');
    $company_email = get_option('crqa_company_email', get_option('admin_email'));
    $admin_emails = get_option('crqa_admin_emails', array());
    if (empty($admin_emails)) {
        $admin_emails = array(get_option('admin_email'));
    }
    $admin_emails_text = implode("\n", $admin_emails);
    $quote_page_id = get_option('crqa_quote_page_id', '');
    $forms_config = get_option('crqa_forms_config', array());
    
    // Get all pages
    $pages = get_pages();
    
    // Get form manager
    $form_manager = crqa_form_manager();
    $active_handlers = $form_manager->get_active_handlers();
    
    ?>
    <div class="wrap">
        <h1>Car Rental Quote Settings</h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="?page=crqa-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">General Settings</a>
            <a href="?page=crqa-settings&tab=forms" class="nav-tab <?php echo $active_tab == 'forms' ? 'nav-tab-active' : ''; ?>">Form Configuration</a>
            <a href="?page=crqa-settings&tab=email" class="nav-tab <?php echo $active_tab == 'email' ? 'nav-tab-active' : ''; ?>">Email Template</a>
        </h2>
        
        <form method="post" action="<?php echo admin_url('admin.php?page=crqa-settings&tab=' . $active_tab); ?>">
            <?php wp_nonce_field('crqa_settings', 'crqa_settings_nonce'); ?>
            <input type="hidden" name="active_tab" value="<?php echo esc_attr($active_tab); ?>">
            
            <?php if ($active_tab == 'general'): ?>
                <?php crqa_display_general_settings_tab($company_name, $company_email, $admin_emails_text, $quote_page_id, $pages); ?>
            <?php elseif ($active_tab == 'forms'): ?>
                <?php crqa_display_forms_settings_tab($forms_config, $active_handlers); ?>
            <?php elseif ($active_tab == 'email'): ?>
                <?php if (function_exists('crqa_email_settings_tab')): ?>
                    <?php crqa_email_settings_tab($active_tab); ?>
                <?php else: ?>
                    <div class="notice notice-error">
                        <p>Email settings module not loaded. Please check that the email-settings.php file exists.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($active_tab != 'email'): ?>
                <?php submit_button(); ?>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

/**
 * Display general settings tab
 */
function crqa_display_general_settings_tab($company_name, $company_email, $admin_emails_text, $quote_page_id, $pages) {
    $product_id = get_option('crqa_rental_product_id');
    $product_exists = $product_id && get_post($product_id);
    ?>
    
    <?php if (!$product_exists): ?>
    <div class="notice notice-warning">
        <p>The rental product is missing. <a href="#" onclick="createRentalProduct(); return false;">Click here to create it</a>.</p>
    </div>
    <script>
    function createRentalProduct() {
        if (confirm('This will create a WooCommerce product for rental bookings. Continue?')) {
            window.location.href = '<?php echo wp_nonce_url(admin_url('admin.php?page=crqa-settings&action=create_product'), 'crqa_create_product'); ?>';
        }
    }
    </script>
    <?php endif; ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">Rental Product Status</th>
            <td>
                <?php if ($product_exists): ?>
                    <span style="color: green;">✓ Product exists (ID: <?php echo intval($product_id); ?>)</span>
                    <a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>" target="_blank" class="button button-small">Edit Product</a>
                <?php else: ?>
                    <span style="color: red;">✗ Product not found</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row">Company Name</th>
            <td>
                <input type="text" name="company_name" value="<?php echo esc_attr($company_name); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">Company Email</th>
            <td>
                <input type="email" name="company_email" value="<?php echo esc_attr($company_email); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">Admin Notification Emails</th>
            <td>
                <textarea name="admin_emails" rows="3" cols="40"><?php echo esc_textarea($admin_emails_text); ?></textarea>
                <p class="description">Enter one email address per line. These will receive new quote notifications.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Quote Page</th>
            <td>
                <select name="quote_page_id" class="regular-text" required>
                    <option value="">— Select a Page —</option>
                    <?php foreach ($pages as $page): ?>
                        <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($quote_page_id, $page->ID); ?>>
                            <?php echo esc_html($page->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Select the page where you'll display quotes using shortcodes</p>
            </td>
        </tr>
    </table>

    <h3>Payment Options</h3>
    <p>Control which payment options are available to customers on the quote page.</p>
    <table class="form-table">
        <?php
        $payment_options = get_option('crqa_payment_options', array(
            'rental_only' => 1,
            'rental_deposit' => 1,
            'booking_fee' => 1
        ));
        ?>
        <tr>
            <th scope="row">Rental Price Only</th>
            <td>
                <label>
                    <input type="checkbox" name="payment_options[rental_only]" value="1" <?php checked(!empty($payment_options['rental_only']), true); ?>>
                    Enable "Rental Price Only" option
                </label>
                <p class="description">Customer pays rental cost now, deposit due on collection day</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Rental + Deposit</th>
            <td>
                <label>
                    <input type="checkbox" name="payment_options[rental_deposit]" value="1" <?php checked(!empty($payment_options['rental_deposit']), true); ?>>
                    Enable "Rental + Deposit" option
                </label>
                <p class="description">Customer pays full amount including refundable deposit</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Booking Fee Only</th>
            <td>
                <label>
                    <input type="checkbox" name="payment_options[booking_fee]" value="1" <?php checked(!empty($payment_options['booking_fee']), true); ?>>
                    Enable "Booking Fee Only" option
                </label>
                <p class="description">Customer pays small booking fee to reserve, balance due later</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Booking Fee Amount</th>
            <td>
                <?php $booking_fee_amount = get_option('crqa_booking_fee_amount', 500); ?>
                <input type="number" name="booking_fee_amount" value="<?php echo intval($booking_fee_amount); ?>" min="0" step="1" class="small-text" />
                <p class="description">Amount for booking fee in pence/cents (e.g., 500 = £5.00)</p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Display forms configuration tab - WPForms only
 */
function crqa_display_forms_settings_tab($forms_config, $active_handlers) {
    // Add debug info if needed
    if (isset($_GET['debug'])) {
        echo '<div style="background: #f0f0f0; padding: 15px; margin-bottom: 20px;">';
        echo '<h4>Debug Info:</h4>';
        echo '<p>Saved config count: ' . count($forms_config) . '</p>';
        echo '<pre>' . print_r($forms_config, true) . '</pre>';
        echo '</div>';
    }
    ?>
    
    <h3>WPForms Configuration</h3>
    <p>Configure multiple WPForms to create car rental quotes. Each form can have its own field mappings and type.</p>
    
    <?php if (empty($active_handlers)): ?>
        <div class="notice notice-warning">
            <p><strong>WPForms not detected!</strong> Please install and activate WPForms to use this plugin.</p>
            <p>This plugin is specifically designed to work with WPForms. You can download WPForms from <a href="https://wpforms.com/" target="_blank">wpforms.com</a>.</p>
        </div>
    <?php else: ?>
        <?php 
        // Check if WPForms handler is available
        $wpforms_handler = null;
        foreach ($active_handlers as $handler) {
            if ($handler->get_id() === 'wpforms') {
                $wpforms_handler = $handler;
                break;
            }
        }
        ?>
        
        <?php if (!$wpforms_handler): ?>
            <div class="notice notice-error">
                <p>WPForms is installed but the handler is not working properly. Please check your installation.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-info">
                <p><strong>WPForms detected!</strong> You can now configure your forms below.</p>
            </div>
            
            <div id="forms-container">
                <?php if (!empty($forms_config)): ?>
                    <?php foreach ($forms_config as $index => $config): ?>
                        <?php crqa_display_form_config_item($index, $config, $active_handlers); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <p>
                <button type="button" id="add-form-config" class="button button-primary">Add New WPForm</button>
            </p>
            
            <?php
            // Prepare handlers for JavaScript (only WPForms)
            $handlers_js = array();
            foreach ($active_handlers as $id => $handler) {
                $handlers_js[$id] = $handler->get_name();
            }
            ?>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                console.log('WPForms configuration script loaded');
                
                var formConfigIndex = <?php echo count($forms_config); ?>;
                var handlers = <?php echo json_encode($handlers_js); ?>;
                var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                
                // Test that jQuery is working
                console.log('jQuery version:', $.fn.jquery);
                console.log('Number of existing forms:', formConfigIndex);
                console.log('Available handlers:', handlers);
                
                // Add new form configuration
                $('#add-form-config').on('click', function(e) {
                    e.preventDefault();
                    console.log('Add form button clicked');
                    
                    var newFormHtml = createNewFormHtml(formConfigIndex);
                    $('#forms-container').append(newFormHtml);
                    
                    // Fade in the new form
                    $('#forms-container .form-config-item:last').hide().fadeIn(300);
                    
                    formConfigIndex++;
                    
                    // Scroll to the new form
                    $('html, body').animate({
                        scrollTop: $('#forms-container .form-config-item:last').offset().top - 100
                    }, 500);
                });
                
                // Function to create new form HTML
                function createNewFormHtml(index) {
                    var html = '<div class="form-config-item" data-index="' + index + '" style="background: #fff; border: 1px solid #ccd0d4; margin-bottom: 20px; padding: 20px;">';
                    html += '<div class="form-config-header" style="margin-bottom: 15px;">';
                    html += '<h4 style="margin: 0; display: inline-block;">WPForm Configuration #' + (index + 1) + '</h4>';
                    html += '<button type="button" class="button button-link-delete remove-form-config" style="float: right; color: #a00;">Remove</button>';
                    html += '<div style="clear: both;"></div>';
                    html += '</div>';
                    
                    html += '<table class="form-table">';
                    
                    // Form Plugin (hardcode to WPForms)
                    html += '<tr>';
                    html += '<th scope="row">Form Plugin</th>';
                    html += '<td>';
                    html += '<select name="form_config[' + index + '][form_handler]" class="handler-selector regular-text">';
                    html += '<option value="wpforms" selected>WPForms</option>';
                    html += '</select>';
                    html += '</td>';
                    html += '</tr>';
                    
                    // Form Selection
                    html += '<tr>';
                    html += '<th scope="row">Select WPForm</th>';
                    html += '<td>';
                    html += '<select name="form_config[' + index + '][form_id]" class="form-selector regular-text">';
                    html += '<option value="">— Loading forms... —</option>';
                    html += '</select>';
                    html += '</td>';
                    html += '</tr>';
                    
                    // Enabled
                    html += '<tr>';
                    html += '<th scope="row">Enabled</th>';
                    html += '<td>';
                    html += '<label>';
                    html += '<input type="checkbox" name="form_config[' + index + '][enabled]" value="1" checked>';
                    html += ' Enable this form for quote submissions';
                    html += '</label>';
                    html += '</td>';
                    html += '</tr>';
                    
                    html += '</table>';
                    
                    html += '<div class="field-mappings"></div>';
                    html += '</div>';
                    
                    return html;
                }
                
                // Remove form configuration
                $(document).on('click', '.remove-form-config', function(e) {
                    e.preventDefault();
                    if (confirm('Are you sure you want to remove this form configuration?')) {
                        $(this).closest('.form-config-item').fadeOut(300, function() {
                            $(this).remove();
                            reindexForms();
                        });
                    }
                });

                // Load form fields when form is selected
                $(document).on('change', '.form-selector', function() {
                    var $this = $(this);
                    var $container = $this.closest('.form-config-item');
                    var formId = $this.val();
                    var handlerId = $container.find('.handler-selector').val();
                    var index = $container.data('index');
                    
                    console.log('Form selected:', formId, 'Handler:', handlerId, 'Index:', index);
                    
                    if (!formId || !handlerId) {
                        $container.find('.field-mappings').empty();
                        return;
                    }
                    
                    var $mappings = $container.find('.field-mappings');
                    $mappings.html('<p>Loading fields...</p>');
                    
                    // Get saved mappings
                    var savedMappings = {};
                    try {
                        savedMappings = JSON.parse($mappings.attr('data-saved-mappings') || '{}');
                    } catch(e) {
                        console.log('No saved mappings');
                    }
                    
                    // AJAX request to get fields
                    $.post(ajaxurl, {
                        action: 'crqa_get_form_fields',
                        handler_id: handlerId,
                        form_id: formId,
                        nonce: '<?php echo wp_create_nonce('crqa_get_fields'); ?>'
                    }, function(response) {
                        console.log('Fields loaded:', response);
                        
                        if (response.success && response.data.fields) {
                            displayFieldMappings($mappings, response.data.fields, index, savedMappings);
                        } else {
                            $mappings.html('<p style="color: red;">Error loading fields. Please try again.</p>');
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('Error loading fields:', error);
                        $mappings.html('<p style="color: red;">Error loading fields: ' + error + '</p>');
                    });
                });
                
                // Display field mappings - UPDATED WITH PRODUCT ID SUPPORT
                function displayFieldMappings($container, fields, index, savedMappings) {
                    var mappingFields = {
                        'customer_name': 'Customer Name *',
                        'customer_email': 'Customer Email *',
                        'customer_phone': 'Customer Phone',
                        'customer_age': 'Customer Age',              // ADD THIS LINE
                        'contact_preference': 'Contact Preference',  // ADD THIS LINE
                        'vehicle_name': 'Vehicle Name/Type',
                        'rental_dates': 'Rental Dates/Period',
                        'product_id': 'Product ID (if available)'
                    };
                    
                    var html = '<h4>Field Mappings</h4>';
                    html += '<table class="form-table">';
                    
                    $.each(mappingFields, function(fieldKey, fieldLabel) {
                        html += '<tr>';
                        html += '<th>' + fieldLabel + '</th>';
                        html += '<td>';
                        
                        if (fieldKey === 'vehicle_name') {
                            // Special handling for vehicle name - allow both field selection and smart tag input
                            html += '<div style="margin-bottom: 10px;">';
                            html += '<label style="display: block; margin-bottom: 5px;">';
                            html += '<input type="radio" name="form_config[' + index + '][vehicle_source]" value="field" ' + ((!savedMappings.vehicle_smart_tag || savedMappings.vehicle_source === 'field') ? 'checked' : '') + '> ';
                            html += 'Select from form field';
                            html += '</label>';
                            html += '<select name="form_config[' + index + '][mappings][' + fieldKey + ']" class="regular-text vehicle-field-select" style="margin-left: 20px;">';
                            html += '<option value="">— Not Mapped —</option>';
                            
                            $.each(fields, function(i, field) {
                                var selected = (savedMappings[fieldKey] == field.id && !savedMappings.vehicle_smart_tag) ? ' selected="selected"' : '';
                                html += '<option value="' + field.id + '"' + selected + '>' + field.label + ' (' + field.type + ')</option>';
                            });
                            
                            html += '</select>';
                            html += '</div>';
                            
                            html += '<div>';
                            html += '<label style="display: block; margin-bottom: 5px;">';
                            html += '<input type="radio" name="form_config[' + index + '][vehicle_source]" value="smart_tag" ' + (savedMappings.vehicle_smart_tag ? 'checked' : '') + '> ';
                            html += 'Use Smart Tag or Fixed Value';
                            html += '</label>';
                            html += '<input type="text" name="form_config[' + index + '][vehicle_smart_tag]" class="regular-text vehicle-smart-tag" style="margin-left: 20px;" placeholder="e.g., Luxury Car or {field_id=&quot;5&quot;}" value="' + (savedMappings.vehicle_smart_tag || '') + '">';
                            html += '<p class="description" style="margin-left: 20px;">Enter a fixed value like "Standard Car" or a WPForms Smart Tag</p>';
                            html += '</div>';
                        } else if (fieldKey === 'product_id') {
                            // Special handling for product ID - allow both field selection and smart tag input
                            html += '<div style="margin-bottom: 10px;">';
                            html += '<label style="display: block; margin-bottom: 5px;">';
                            html += '<input type="radio" name="form_config[' + index + '][product_id_source]" value="field" ' + ((!savedMappings.product_id_smart_tag || savedMappings.product_id_source === 'field') ? 'checked' : '') + '> ';
                            html += 'Select from form field';
                            html += '</label>';
                            html += '<select name="form_config[' + index + '][mappings][' + fieldKey + ']" class="regular-text product-id-field-select" style="margin-left: 20px;">';
                            html += '<option value="">— Not Mapped —</option>';
                            
                            $.each(fields, function(i, field) {
                                var selected = (savedMappings[fieldKey] == field.id && !savedMappings.product_id_smart_tag) ? ' selected="selected"' : '';
                                html += '<option value="' + field.id + '"' + selected + '>' + field.label + ' (' + field.type + ')</option>';
                            });
                            
                            html += '</select>';
                            html += '</div>';
                            
                            html += '<div>';
                            html += '<label style="display: block; margin-bottom: 5px;">';
                            html += '<input type="radio" name="form_config[' + index + '][product_id_source]" value="smart_tag" ' + (savedMappings.product_id_smart_tag ? 'checked' : '') + '> ';
                            html += 'Use Smart Tag';
                            html += '</label>';
                            html += '<input type="text" name="form_config[' + index + '][product_id_smart_tag]" class="regular-text product-id-smart-tag" style="margin-left: 20px;" placeholder="e.g., {page_id} or {field_id=&quot;3&quot;}" value="' + (savedMappings.product_id_smart_tag || '') + '">';
                            html += '<p class="description" style="margin-left: 20px;">Enter a WPForms Smart Tag like {page_id} that contains the product ID number</p>';
                            html += '</div>';
                        } else {
                            // Regular field mapping
                            html += '<select name="form_config[' + index + '][mappings][' + fieldKey + ']" class="regular-text">';
                            html += '<option value="">— Not Mapped —</option>';
                            
                            $.each(fields, function(i, field) {
                                var selected = (savedMappings[fieldKey] == field.id) ? ' selected="selected"' : '';
                                html += '<option value="' + field.id + '"' + selected + '>' + field.label + ' (' + field.type + ')</option>';
                            });
                            
                            html += '</select>';
                        }
                        
                        html += '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</table>';
                    $container.html(html);
                    
                    // Handle radio button changes for vehicle source
                    $container.find('input[name="form_config[' + index + '][vehicle_source]"]').on('change', function() {
                        var isSmartTag = $(this).val() === 'smart_tag';
                        $container.find('.vehicle-field-select').prop('disabled', isSmartTag);
                        $container.find('.vehicle-smart-tag').prop('disabled', !isSmartTag);
                    });
                    
                    // Handle radio button changes for product ID source
                    $container.find('input[name="form_config[' + index + '][product_id_source]"]').on('change', function() {
                        var isSmartTag = $(this).val() === 'smart_tag';
                        $container.find('.product-id-field-select').prop('disabled', isSmartTag);
                        $container.find('.product-id-smart-tag').prop('disabled', !isSmartTag);
                    });
                    
                    // Trigger initial state
                    $container.find('input[name="form_config[' + index + '][vehicle_source]"]:checked').trigger('change');
                    $container.find('input[name="form_config[' + index + '][product_id_source]"]:checked').trigger('change');
                }
                
                // Reindex forms after deletion
                function reindexForms() {
                    $('.form-config-item').each(function(index) {
                        var $item = $(this);
                        $item.data('index', index);
                        $item.find('.form-config-header h4').text('WPForm Configuration #' + (index + 1));
                        
                        // Update all field names
                        $item.find('input, select, textarea').each(function() {
                            var name = $(this).attr('name');
                            if (name) {
                                name = name.replace(/\[(\d+)\]/, '[' + index + ']');
                                $(this).attr('name', name);
                            }
                        });
                    });
                    
                    formConfigIndex = $('.form-config-item').length;
                }

                // Function to load forms for a handler selector
                function loadFormsForHandler($handlerSelector) {
                    var $container = $handlerSelector.closest('.form-config-item');
                    var handlerId = $handlerSelector.val();
                    var $formSelector = $container.find('.form-selector');
                    var savedFormId = $formSelector.attr('data-saved-value') || '';

                    console.log('CRQA: Loading forms for handler:', handlerId, 'Saved form:', savedFormId);
                    console.log('CRQA: AJAX URL:', ajaxurl);

                    if (!handlerId) {
                        $formSelector.html('<option value="">— Select WPForms first —</option>').prop('disabled', false);
                        return;
                    }

                    // Show loading state
                    $formSelector.html('<option value="">— Loading forms... —</option>').prop('disabled', true);

                    // Remove any existing error notices in this container
                    $container.find('.crqa-form-error').remove();

                    var requestData = {
                        action: 'crqa_get_handler_forms',
                        handler_id: handlerId,
                        nonce: '<?php echo wp_create_nonce('crqa_get_forms'); ?>'
                    };
                    console.log('CRQA: Sending AJAX request:', requestData);

                    // AJAX request to get forms with timeout
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: requestData,
                        timeout: 30000, // 30 second timeout
                        success: function(response) {
                            console.log('CRQA: Forms response:', response);

                            if (response.success) {
                                if (response.data.forms && response.data.forms.length > 0) {
                                    var options = '<option value="">— Select WPForm —</option>';
                                    $.each(response.data.forms, function(i, form) {
                                        var selected = (savedFormId && form.id == savedFormId) ? ' selected="selected"' : '';
                                        options += '<option value="' + form.id + '"' + selected + '>' + form.title + ' (ID: ' + form.id + ')</option>';
                                    });
                                    $formSelector.html(options).prop('disabled', false);

                                    // If saved form was selected, load its fields
                                    if (savedFormId && $formSelector.val() == savedFormId) {
                                        $formSelector.trigger('change');
                                    }
                                } else {
                                    // No forms found but WPForms is active
                                    var message = response.data.message || 'No WPForms found. Please create a form first.';
                                    $formSelector.html('<option value="">— ' + message + ' —</option>').prop('disabled', false);
                                    $container.find('.form-table').before('<div class="notice notice-warning crqa-form-error" style="margin: 10px 0;"><p>' + message + ' <a href="<?php echo admin_url('admin.php?page=wpforms-builder'); ?>" target="_blank">Create a form now</a></p></div>');
                                }
                            } else {
                                // Error response
                                var errorMsg = response.data && response.data.message ? response.data.message : 'Error loading forms';
                                console.error('CRQA Error:', errorMsg);
                                $formSelector.html('<option value="">— Error —</option>').prop('disabled', false);

                                // Show error notice
                                var notice = '<div class="notice notice-error crqa-form-error" style="margin: 10px 0;"><p><strong>Error:</strong> ' + errorMsg + '</p>';
                                if (response.data && response.data.install_url) {
                                    notice += '<p><a href="' + response.data.install_url + '" class="button button-primary">Install WPForms</a></p>';
                                }
                                if (response.data && response.data.plugins_url) {
                                    notice += '<p><a href="' + response.data.plugins_url + '" class="button">Go to Plugins</a></p>';
                                }
                                notice += '</div>';
                                $container.find('.form-table').before(notice);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('CRQA AJAX Error:', status, error);
                            console.error('CRQA XHR Response:', xhr.responseText);

                            var errorMessage = 'Connection error';
                            if (status === 'timeout') {
                                errorMessage = 'Request timed out';
                            } else if (xhr.status === 0) {
                                errorMessage = 'Network error - check your connection';
                            } else if (xhr.status === 403) {
                                errorMessage = 'Access denied - please refresh and try again';
                            } else if (xhr.status === 500) {
                                errorMessage = 'Server error - check PHP error logs';
                            }

                            $formSelector.html('<option value="">— ' + errorMessage + ' —</option>').prop('disabled', false);
                            $container.find('.form-table').before('<div class="notice notice-error crqa-form-error" style="margin: 10px 0;"><p><strong>' + errorMessage + ':</strong> ' + error + ' (Status: ' + xhr.status + ')</p><p>Check browser console (F12) for details.</p></div>');
                        }
                    });
                }

                // Initialize existing forms - load forms on page load
                console.log('Initializing existing forms...');
                $('.handler-selector').each(function() {
                    loadFormsForHandler($(this));
                });

                // Also trigger on change for future changes
                $(document).on('change', '.handler-selector', function() {
                    loadFormsForHandler($(this));
                });
                
                // Add form validation before submit
                $('form').on('submit', function(e) {
                    var hasValidForm = false;
                    $('.form-config-item').each(function() {
                        var handler = $(this).find('.handler-selector').val();
                        var form = $(this).find('.form-selector').val();
                        if (handler && form) {
                            hasValidForm = true;
                        }
                    });
                    
                    if ($('.form-config-item').length > 0 && !hasValidForm) {
                        alert('Please complete at least one WPForm configuration before saving.');
                        e.preventDefault();
                        return false;
                    }
                });
            });
            </script>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

/**
 * Display a single form configuration item - WPForms only
 */
function crqa_display_form_config_item($index, $config, $handlers, $is_template = false) {
    $item_class = 'form-config-item';
    if ($is_template) {
        $item_class .= ' template-item';
        // Hide template items
        echo '<div style="display: none;">';
    }
    $safe_index = is_numeric($index) ? intval($index) : esc_attr($index);
    ?>
    <div class="<?php echo esc_attr($item_class); ?>" data-index="<?php echo esc_attr($index); ?>" style="background: #fff; border: 1px solid #ccd0d4; margin-bottom: 20px; padding: 20px;">
        <div class="form-config-header" style="cursor: move; margin-bottom: 15px;">
            <h4 style="margin: 0; display: inline-block;">WPForm Configuration #<span class="form-number"><?php echo is_numeric($index) ? intval($index + 1) : '{{index_display}}'; ?></span></h4>
            <button type="button" class="button button-link-delete remove-form-config" style="float: right; color: #a00;">Remove</button>
            <div style="clear: both;"></div>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row">Form Plugin</th>
                <td>
                    <select name="form_config[<?php echo $safe_index; ?>][form_handler]" class="handler-selector regular-text" data-saved-form="<?php echo esc_attr($config['form_id'] ?? ''); ?>">
                        <option value="wpforms" <?php selected($config['form_handler'] ?? '', 'wpforms'); ?>>WPForms</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Select WPForm</th>
                <td>
                    <select name="form_config[<?php echo $safe_index; ?>][form_id]" class="form-selector regular-text" data-saved-value="<?php echo esc_attr($config['form_id'] ?? ''); ?>">
                        <option value="">— Select WPForm —</option>
                        <?php
                        // This will be populated via AJAX when handler loads
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Enabled</th>
                <td>
                    <label>
                        <input type="checkbox" name="form_config[<?php echo $safe_index; ?>][enabled]" value="1" <?php checked($config['enabled'] ?? 0, 1); ?>>
                        Enable this form for quote submissions
                    </label>
                </td>
            </tr>
        </table>
        
        <div class="field-mappings" data-saved-mappings="<?php echo esc_attr(json_encode($config['mappings'] ?? array())); ?>">
            <?php 
            // Field mappings will be loaded via AJAX when form is selected
            ?>
        </div>
    </div>
    <?php
    if ($is_template) {
        echo '</div>';
    }
}

/**
 * Save enhanced settings - UPDATED to handle product ID mappings and WPForms only
 */
function crqa_save_enhanced_settings($active_tab) {
    // Verify nonce
    if (!isset($_POST['crqa_settings_nonce']) || !wp_verify_nonce($_POST['crqa_settings_nonce'], 'crqa_settings')) {
        wp_die('Security check failed');
    }
    
    if ($active_tab == 'general') {
        // Save general settings
        $company_name = isset($_POST['company_name']) ? $_POST['company_name'] : '';
        $company_email = isset($_POST['company_email']) ? $_POST['company_email'] : '';
        $admin_emails = isset($_POST['admin_emails']) ? $_POST['admin_emails'] : '';
        $quote_page_id = isset($_POST['quote_page_id']) ? $_POST['quote_page_id'] : 0;
        
        update_option('crqa_company_name', sanitize_text_field($company_name));
        update_option('crqa_company_email', sanitize_email($company_email));
        
        // Handle multiple admin emails
        $admin_emails = sanitize_textarea_field($admin_emails);
        $admin_emails = array_map('trim', explode("\n", $admin_emails));
        $admin_emails = array_filter($admin_emails, 'is_email');
        update_option('crqa_admin_emails', $admin_emails);
        
        update_option('crqa_quote_page_id', intval($quote_page_id));

        // Save payment options
        $payment_options = array(
            'rental_only' => isset($_POST['payment_options']['rental_only']) ? 1 : 0,
            'rental_deposit' => isset($_POST['payment_options']['rental_deposit']) ? 1 : 0,
            'booking_fee' => isset($_POST['payment_options']['booking_fee']) ? 1 : 0
        );
        update_option('crqa_payment_options', $payment_options);

        // Save booking fee amount
        $booking_fee_amount = isset($_POST['booking_fee_amount']) ? intval($_POST['booking_fee_amount']) : 500;
        update_option('crqa_booking_fee_amount', $booking_fee_amount);

        echo '<div class="notice notice-success is-dismissible"><p>General settings saved!</p></div>';
        
    } elseif ($active_tab == 'forms') {
        // Debug: Log what we're receiving
        error_log('CRQA WPForms Save - POST data: ' . print_r($_POST, true));
        
        // Save forms configuration
        $forms_config = array();
        
        if (isset($_POST['form_config']) && is_array($_POST['form_config'])) {
            foreach ($_POST['form_config'] as $index => $config) {
                // Only save if form handler is wpforms and form ID is set
                if (!empty($config['form_handler']) && $config['form_handler'] === 'wpforms' && !empty($config['form_id'])) {
                    $form_data = array(
                        'form_handler' => 'wpforms', // Force to wpforms
                        'form_id' => intval($config['form_id']),
                        'enabled' => isset($config['enabled']) ? 1 : 0,
                        'mappings' => array()
                    );
                    
                    // Handle vehicle source (field or smart tag)
                    if (isset($config['vehicle_source']) && $config['vehicle_source'] === 'smart_tag') {
                        // Save smart tag value
                        if (isset($config['vehicle_smart_tag'])) {
                            $form_data['mappings']['vehicle_smart_tag'] = sanitize_text_field($config['vehicle_smart_tag']);
                            $form_data['mappings']['vehicle_source'] = 'smart_tag';
                        }
                    } else {
                        // Save regular field mapping for vehicle
                        if (!empty($config['mappings']['vehicle_name'])) {
                            $form_data['mappings']['vehicle_name'] = sanitize_text_field($config['mappings']['vehicle_name']);
                        }
                    }
                    
                    // Handle product ID source (field or smart tag)
                    if (isset($config['product_id_source']) && $config['product_id_source'] === 'smart_tag') {
                        // Save product ID smart tag value
                        if (isset($config['product_id_smart_tag'])) {
                            $form_data['mappings']['product_id_smart_tag'] = sanitize_text_field($config['product_id_smart_tag']);
                            $form_data['mappings']['product_id_source'] = 'smart_tag';
                        }
                    } else {
                        // Save regular field mapping for product ID
                        if (!empty($config['mappings']['product_id'])) {
                            $form_data['mappings']['product_id'] = sanitize_text_field($config['mappings']['product_id']);
                        }
                    }
                    
                    // Save other field mappings
                    $allowed_fields = array('customer_name', 'customer_email', 'customer_phone', 'customer_age', 'contact_preference', 'rental_dates');
                    foreach ($allowed_fields as $field_key) {
                        if (isset($config['mappings'][$field_key]) && !empty($config['mappings'][$field_key])) {
                            $form_data['mappings'][$field_key] = sanitize_text_field($config['mappings'][$field_key]);
                        }
                    }
                    
                    $forms_config[] = $form_data;
                }
            }
        }
        
        // Debug: Log what we're saving
        error_log('CRQA WPForms Save - Saving config: ' . print_r($forms_config, true));
        
        update_option('crqa_forms_config', $forms_config);
        
        // Reinitialize form manager hooks after saving
        if (function_exists('crqa_form_manager')) {
            $form_manager = crqa_form_manager();
            // This will re-hook the forms based on new configuration
            do_action('crqa_forms_config_updated', $forms_config);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>WPForms configurations saved! Total forms configured: ' . count($forms_config) . '</p></div>';
        
    } elseif ($active_tab == 'email') {
        // Handle email settings through the included module
        if (function_exists('crqa_save_email_settings')) {
            crqa_save_email_settings();
            echo '<div class="notice notice-success is-dismissible"><p>Email settings saved!</p></div>';
        }
    }
}

/**
 * AJAX handler to get form fields
 */
add_action('wp_ajax_crqa_get_form_fields', 'crqa_ajax_get_form_fields');
function crqa_ajax_get_form_fields() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'crqa_get_fields')) {
        wp_die('Security check failed');
    }
    
    $handler_id = sanitize_text_field($_POST['handler_id']);
    $form_id = intval($_POST['form_id']);
    
    // Only allow wpforms
    if ($handler_id !== 'wpforms') {
        wp_send_json_error('Only WPForms is supported');
    }
    
    $form_manager = crqa_form_manager();
    $handler = $form_manager->get_handler($handler_id);
    
    if (!$handler) {
        wp_send_json_error('Handler not found');
    }
    
    $fields = $handler->get_form_fields($form_id);
    
    wp_send_json_success(array('fields' => $fields));
}

/**
 * AJAX handler to get forms for WPForms
 */
add_action('wp_ajax_crqa_get_handler_forms', 'crqa_ajax_get_handler_forms');
function crqa_ajax_get_handler_forms() {
    error_log('CRQA AJAX: crqa_get_handler_forms called');

    // Check permissions
    if (!current_user_can('manage_options')) {
        error_log('CRQA AJAX: Unauthorized - user cannot manage_options');
        wp_send_json_error(array('message' => 'Unauthorized access'));
        return;
    }

    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'crqa_get_forms')) {
        error_log('CRQA AJAX: Nonce verification failed');
        wp_send_json_error(array('message' => 'Security check failed. Please refresh the page.'));
        return;
    }

    $handler_id = isset($_POST['handler_id']) ? sanitize_text_field($_POST['handler_id']) : '';
    error_log('CRQA AJAX: Handler ID = ' . $handler_id);

    // Only allow wpforms
    if ($handler_id !== 'wpforms') {
        wp_send_json_error(array('message' => 'Only WPForms is supported'));
        return;
    }

    // Check if WPForms is installed and active
    $wpforms_function_exists = function_exists('wpforms');
    $wpforms_class_exists = class_exists('WPForms');
    error_log('CRQA AJAX: wpforms() exists = ' . ($wpforms_function_exists ? 'yes' : 'no'));
    error_log('CRQA AJAX: WPForms class exists = ' . ($wpforms_class_exists ? 'yes' : 'no'));

    if (!$wpforms_function_exists || !$wpforms_class_exists) {
        wp_send_json_error(array(
            'message' => 'WPForms plugin is not installed or not active. Please install and activate WPForms first.',
            'install_url' => admin_url('plugin-install.php?s=wpforms&tab=search&type=term')
        ));
        return;
    }

    // Check if form_manager function exists
    if (!function_exists('crqa_form_manager')) {
        error_log('CRQA AJAX: crqa_form_manager function does not exist!');
        wp_send_json_error(array('message' => 'Plugin not fully loaded. Please refresh the page.'));
        return;
    }

    $form_manager = crqa_form_manager();
    $handler = $form_manager->get_handler($handler_id);

    if (!$handler) {
        error_log('CRQA AJAX: Handler not found for ' . $handler_id);
        wp_send_json_error(array('message' => 'WPForms handler not found. Please try deactivating and reactivating the plugin.'));
        return;
    }

    if (!$handler->is_active()) {
        error_log('CRQA AJAX: Handler is_active() returned false');
        wp_send_json_error(array(
            'message' => 'WPForms is installed but not active. Please activate WPForms.',
            'plugins_url' => admin_url('plugins.php')
        ));
        return;
    }

    error_log('CRQA AJAX: Calling get_forms()');
    $forms = $handler->get_forms();
    error_log('CRQA AJAX: Found ' . count($forms) . ' forms');

    if (empty($forms)) {
        wp_send_json_success(array(
            'forms' => array(),
            'message' => 'No WPForms found. Please create a form in WPForms first.'
        ));
        return;
    }

    wp_send_json_success(array('forms' => $forms));
}