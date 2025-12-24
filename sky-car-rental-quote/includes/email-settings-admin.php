<?php
/**
 * Admin Email Notifications Module for Car Rental Quote Automation
 * 
 * This file handles admin email notifications and related functionality
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/email-settings-admin.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Note: crqa_clean_phone_number() is defined in quote-shared-functions.php

/**
 * Build enhanced admin email HTML template - UPDATED with Quick Send button
 */
function crqa_build_admin_email_html($quote, $phone_number, $whatsapp_url, $call_url, $quote_edit_url, $quick_send_url, $settings) {
    // Get location data if available
    $user_location = '';
    if (!empty($quote->vehicle_details) && preg_match('/Location:\s*(.+)/', $quote->vehicle_details, $matches)) {
        $user_location = trim($matches[1]);
    }
    
    // Check if price is already calculated
    $has_calculated_price = !empty($quote->rental_price) && $quote->rental_price > 0;
    
    $message = '<!DOCTYPE html>
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .email-wrapper { background-color: #f4f4f4; padding: 20px 0; }
            .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
            .email-header { padding: 25px 30px; border-bottom: 1px solid #e0e0e0; }
            .header-content { display: table; width: 100%; }
            .logo-section { display: table-cell; vertical-align: middle; }
            .action-section { display: table-cell; vertical-align: middle; text-align: right; }
            .email-body { padding: 30px 40px; }
            .email-footer { background-color: #f8f9fa; padding: 30px 40px; border-top: 1px solid #e0e0e0; }
            .alert-section { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 20px; margin-bottom: 25px; border-radius: 4px; }
            .quote-details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .quote-details h3 { margin-top: 0; color: #333; }
            .quote-details p { margin: 8px 0; }
            .action-buttons { margin: 25px 0; text-align: center; }
            .action-button { display: inline-block; padding: 16px 30px; margin: 8px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 16px; text-align: center; transition: all 0.3s ease; min-width: 160px; }
            .btn-whatsapp { background-color: #25D366; color: white !important; }
            .btn-whatsapp:hover { background-color: #1ebe57; }
            .btn-call { background-color: #007bff; color: white !important; }
            .btn-call:hover { background-color: #0056b3; }
            .btn-quote { background-color: #dca725; color: #000000 !important; }
            .btn-quote:hover { background-color: #c49620; }
            .btn-quick-send { background-color: #28a745; color: white !important; }
            .btn-quick-send:hover { background-color: #218838; }
            .customer-section { background: white; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 15px 0; }
            .location-info { background: #17a2b8; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; display: inline-block; }
            .next-steps { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 25px; border-left: 4px solid #6c757d; }
            .price-info { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .price-info h4 { margin: 0 0 10px 0; color: #155724; }
            .price-note { background: #ffeaa7; border: 1px solid #fdcb6e; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .price-note p { margin: 0; color: #856404; }
            .copyright { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
            
            @media only screen and (max-width: 600px) {
                .header-content { width: 100%; }
                .logo-section { display: inline-block !important; width: 50%; text-align: left !important; }
                .action-section { display: inline-block !important; width: 50%; text-align: right !important; }
                .logo-section img { max-width: 80px !important; }
                .email-body { padding: 20px; }
                .email-footer { padding: 20px; }
                .action-button { display: block; margin: 10px 0; font-size: 15px; }
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <!-- Header -->
                <div class="email-header">
                    <table class="header-content" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td class="logo-section" style="text-align: left;">';
    
    if ($settings['email_logo']) {
        $message .= '
                                <img src="' . esc_url($settings['email_logo']) . '" alt="' . esc_attr($settings['company_name']) . '" style="max-width: 100px; height: auto;">';
    }
    
    $message .= '
                            </td>
                            <td class="action-section" style="text-align: right;">
                                <div style="color: #666; font-size: 14px; margin-bottom: 5px;">New Quote Request</div>
                                <div style="font-size: 18px; font-weight: 600; color: #333;">Quote #' . str_pad($quote->id, 5, '0', STR_PAD_LEFT) . '</div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Body -->
                <div class="email-body">
                    <div class="alert-section">
                        <h3 style="margin: 0 0 10px 0; color: #1976d2;">Action Required</h3>
                        <p style="margin: 0;">A new car rental quote request has been submitted and requires your attention. Review the details below and take action.</p>
                    </div>
                    
                    <!-- Customer Information -->
<div class="customer-section">
    <h3 style="margin-top: 0; color: #495057;">Customer Information</h3>
    <p><strong>Name:</strong> ' . esc_html($quote->customer_name) . '</p>
    <p><strong>Email:</strong> <a href="mailto:' . esc_attr($quote->customer_email) . '">' . esc_html($quote->customer_email) . '</a></p>
    <p><strong>Age:</strong> ' . (!empty($quote->customer_age) ? esc_html($quote->customer_age) : 'Not provided') . '</p>
    <p><strong>Contact Preference:</strong> ' . (!empty($quote->contact_preference) ? esc_html($quote->contact_preference) : 'Not specified') . '</p>';
                        
    
    if ($quote->customer_phone) {
        $message .= '
                        <p><strong>Phone:</strong> ' . esc_html($quote->customer_phone) . '</p>';
    }
    
    if ($user_location) {
        $message .= '
                        <p><strong>Location:</strong> <span class="location-info">' . esc_html($user_location) . '</span></p>';
    }
    
    $message .= '
                    </div>
                    
                    <!-- Quote Details -->
                    <!-- Quote Details -->
<div class="quote-details">
    <h3>Rental Details</h3>
    <p><strong>Vehicle:</strong> ' . esc_html($quote->vehicle_name);

// Get car subtitle if product is linked
if (!empty($quote->product_id)) {
    $subtitle_terms = wp_get_post_terms($quote->product_id, 'pa_car_subtitle');
    if (!is_wp_error($subtitle_terms) && !empty($subtitle_terms)) {
        $message .= ' - ' . esc_html($subtitle_terms[0]->name);
    }
}

$message .= '</p>
    <p><strong>Rental Period:</strong> ' . esc_html($quote->rental_dates ?: 'Not specified') . '</p>
                        <p><strong>Form Type:</strong> ' . ucfirst($quote->form_type ?: 'Standard') . '</p>
                        <p><strong>Status:</strong> <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Pending</span></p>
                        <p><strong>Submitted:</strong> ' . date('F j, Y g:i A', strtotime($quote->created_at)) . '</p>
                    </div>';
    
    // Show calculated price if available - UPDATED to show only rental price
    if ($has_calculated_price) {
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
        
        $message .= '
                    <div class="price-info">
                        <h4>Auto-Calculated Pricing</h4>
                        <p><strong>Rental Price:</strong> <span style="font-size: 18px; color: #155724;">' . $currency_symbol . number_format($quote->rental_price, 2) . '</span></p>
                    </div>
                    
                    <div class="price-note">
                        <p><strong>Note:</strong> If the price is correct, click "Quick Send Quote" to send it to the customer. If not, click "Review & Edit Quote" to amend the details before sending.</p>
                    </div>';
    }
    
    $message .= '
                    <!-- Action Buttons -->
                    <div class="action-buttons">';
    
    // WhatsApp Button
    if ($whatsapp_url) {
        $message .= '
                        <a href="' . esc_url($whatsapp_url) . '" class="action-button btn-whatsapp" target="_blank">
                            WhatsApp Customer
                        </a>';
    }
    
    // Call Button  
    if ($call_url) {
        $message .= '
                        <a href="' . esc_url($call_url) . '" class="action-button btn-call">
                            Call Customer
                        </a>';
    }
    
    // Quick Send Button (only if price is calculated)
    if ($has_calculated_price && $quick_send_url) {
        $message .= '
                        <a href="' . esc_url($quick_send_url) . '" class="action-button btn-quick-send" target="_blank">
                            Quick Send Quote
                        </a>';
    }
    
    // Quote Management Button
    $message .= '
                        <a href="' . esc_url($quote_edit_url) . '" class="action-button btn-quote" target="_blank">
                            ' . ($has_calculated_price ? 'Review & Edit Quote' : 'Set Price & Send Quote') . '
                        </a>
                    </div>
                    
                    <!-- Next Steps -->
                    <div class="next-steps">
                        <h4 style="margin-top: 0; color: #495057;">Next Steps</h4>';
    
    if ($has_calculated_price) {
        $message .= '
                        <p style="margin: 10px 0;"><strong>Option 1 - Quick Send:</strong> Click "Quick Send Quote" to immediately send this quote to the customer with the auto-calculated price.</p>
                        <p style="margin: 10px 0;"><strong>Option 2 - Review First:</strong> Click "Review & Edit Quote" to review details or adjust pricing before sending.</p>
                        <p style="margin: 10px 0;">You can also contact the customer via WhatsApp or phone first.</p>';
    } else {
        $message .= '
                        <p style="margin: 10px 0;">1. Contact the customer via WhatsApp or phone call</p>
                        <p style="margin: 10px 0;">2. Click "Set Price & Send Quote" to configure pricing</p>
                        <p style="margin: 10px 0;">3. Send the finalized quote to the customer</p>';
    }
    
    $message .= '
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="email-footer">
                    <p>This is an automated notification from your Car Rental Quote System</p>
                    <div class="copyright">
                        <p>Direct quote management: <a href="' . esc_url($quote_edit_url) . '" target="_blank">Edit Quote #' . str_pad($quote->id, 5, '0', STR_PAD_LEFT) . '</a></p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $message;
}

/**
 * Send enhanced admin notification email - MAIN FUNCTION - UPDATED
 */
function crqa_send_enhanced_admin_notification($quote_id, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    // Get the full quote record
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
    
    if (!$quote) {
        return false;
    }
    
    $admin_emails = get_option('crqa_admin_emails', array());
    if (empty($admin_emails)) {
        $admin_emails = array(get_option('admin_email'));
    }
    
    $company_name = get_option('crqa_company_name', 'Your Car Rental Company');
    $company_email = get_option('crqa_company_email', get_option('admin_email'));
    $email_logo = get_option('crqa_email_logo', '');

    // Sanitize company name for email header to prevent header injection
    // Remove newlines, carriage returns, and other control characters
    $safe_company_name = preg_replace('/[\r\n\t]/', '', $company_name);
    $safe_company_name = sanitize_text_field($safe_company_name);

    // Validate and sanitize email
    $safe_company_email = sanitize_email($company_email);
    if (!is_email($safe_company_email)) {
        $safe_company_email = get_option('admin_email');
    }

    $subject = sprintf('[%s] New Car Rental Quote Request #%s', $safe_company_name, str_pad($quote_id, 5, '0', STR_PAD_LEFT));
    
    // Clean and format phone number for WhatsApp/Call
    $phone_number = crqa_clean_phone_number($quote->customer_phone);
    $whatsapp_url = '';
    $call_url = '';
    
    if ($phone_number) {
        // WhatsApp URL with pre-filled message
        $whatsapp_message = sprintf(
            "Hello %s, thank you for your quote request #%s for %s. We'll prepare your quote shortly and send it to you.",
            $quote->customer_name,
            str_pad($quote_id, 5, '0', STR_PAD_LEFT),
            $quote->vehicle_name
        );
        $whatsapp_url = 'https://wa.me/' . $phone_number . '?text=' . urlencode($whatsapp_message);
        
        // Call URL (tel: protocol)
        $call_url = 'tel:+' . $phone_number;
    }
    
    // Quote edit URL
    $quote_edit_url = admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote_id);
    
    // Quick send URL - UPDATED to use a more secure approach
    $quick_send_url = '';
    if (!empty($quote->rental_price) && $quote->rental_price > 0) {
        // Generate secure quick send URL with longer-lasting token
        $quick_send_token = md5($quote_id . $quote->quote_hash . SECURE_AUTH_KEY);
        $quick_send_url = add_query_arg(array(
            'crqa_action' => 'quick_send',
            'quote_id' => $quote_id,
            'token' => $quick_send_token
        ), home_url('/'));
    }
    
    // Build enhanced HTML email
    $message = crqa_build_admin_email_html($quote, $phone_number, $whatsapp_url, $call_url, $quote_edit_url, $quick_send_url, array(
        'company_name' => $company_name,
        'email_logo' => $email_logo
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $safe_company_name . ' <' . $safe_company_email . '>'
    );
    
    // Send to all admin emails
    foreach ($admin_emails as $admin_email) {
        if (is_email($admin_email)) {
            wp_mail($admin_email, $subject, $message, $headers);
        }
    }
}

/**
 * Handle quick send quote via public URL - UPDATED with one-time use
 */
add_action('init', 'crqa_handle_public_quick_send');
function crqa_handle_public_quick_send() {
    // Check if this is a quick send request
    if (!isset($_GET['crqa_action']) || $_GET['crqa_action'] !== 'quick_send') {
        return;
    }
    
    // Verify required parameters
    if (!isset($_GET['quote_id']) || !isset($_GET['token'])) {
        wp_die('Invalid request. Missing required parameters.');
    }
    
    $quote_id = intval($_GET['quote_id']);
    $token = sanitize_text_field($_GET['token']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    // Get quote
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
    
    if (!$quote) {
        wp_die('Quote not found. This quote may have been deleted.');
    }
    
    // Verify token using the same method as generation
    $expected_token = md5($quote_id . $quote->quote_hash . SECURE_AUTH_KEY);
    
    if ($token !== $expected_token) {
        wp_die('Security check failed. This link may be invalid.');
    }
    
    // Check if price is set
    if (empty($quote->rental_price) || $quote->rental_price <= 0) {
        wp_die('Cannot send quote: Rental price not set. Please set the price first.');
    }
    
    // Check if this link has been used before
    $quick_send_used = get_post_meta($quote_id, '_crqa_quick_send_used', true);
    
    // Handle resend action
    if (isset($_GET['resend']) && $_GET['resend'] === '1') {
        // Send the quote email
        if (function_exists('crqa_send_quote_email')) {
            crqa_send_quote_email($quote_id);
            
            // Update status to quoted if it was pending
            if ($quote->quote_status == 'pending') {
                $wpdb->update($table_name, array('quote_status' => 'quoted'), array('id' => $quote_id));
            }
            
            // Show success message
            crqa_show_quick_send_success($quote, true);
        } else {
            wp_die('Error: Email sending function not available.');
        }
        exit;
    }
    
    // If link has been used before, show resend option
    if ($quick_send_used) {
        crqa_show_resend_option($quote, $token);
        exit;
    }
    
    // First time using the link - send the quote
    if (function_exists('crqa_send_quote_email')) {
        crqa_send_quote_email($quote_id);
        
        // Mark the quick send link as used
        update_post_meta($quote_id, '_crqa_quick_send_used', true);
        update_post_meta($quote_id, '_crqa_quick_send_date', current_time('mysql'));
        
        // Update status to quoted if it was pending
        if ($quote->quote_status == 'pending') {
            $wpdb->update($table_name, array('quote_status' => 'quoted'), array('id' => $quote_id));
        }
        
        // Show success message
        crqa_show_quick_send_success($quote);
    } else {
        wp_die('Error: Email sending function not available.');
    }
    
    exit;
}

/**
 * Show success page after quick send - UPDATED WITH WHATSAPP AND CALL BUTTONS
 */
function crqa_show_quick_send_success($quote, $is_resend = false) {
    // Get phone number and prepare contact URLs
    $phone_number = crqa_clean_phone_number($quote->customer_phone);
    $whatsapp_url = '';
    $call_url = '';
    $quote_url = home_url('/quote/' . $quote->quote_hash);
    
    if ($phone_number) {
        // WhatsApp URL with pre-filled message including the quote link
        $whatsapp_message = sprintf(
            "Hi %s! Your car rental quote #%s for %s has been sent to your email. You can also view it here: %s",
            $quote->customer_name,
            str_pad($quote->id, 5, '0', STR_PAD_LEFT),
            $quote->vehicle_name,
            $quote_url
        );
        $whatsapp_url = 'https://wa.me/' . $phone_number . '?text=' . urlencode($whatsapp_message);
        
        // Call URL
        $call_url = 'tel:+' . $phone_number;
    }
    
    $success_title = $is_resend ? 'Quote Resent Successfully!' : 'Quote Sent Successfully!';
    $success_message = $is_resend ? 'The quote has been resent to' : 'The quote has been sent to';
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Quote Sent Successfully</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f4f4f4;
                margin: 0;
                padding: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .success-container {
                background: white;
                padding: 40px 25px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 600px;
                width: 100%;
                box-sizing: border-box;
            }
            .success-icon {
                color: #28a745;
                font-size: 60px;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
            }
            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            .button {
                display: inline-block;
                padding: 12px 24px;
                background: #007cba;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 5px;
            }
            .button:hover {
                background: #005a87;
            }
            .details {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
                text-align: left;
            }
            .contact-section {
                background: #e8f4fd;
                border: 1px solid #bee5eb;
                padding: 25px 20px;
                border-radius: 8px;
                margin: 30px 0;
                overflow: hidden;
            }
            .contact-section h3 {
                margin-top: 0;
                color: #004085;
            }
            .contact-buttons {
                margin: 20px 0;
                padding: 0;
            }
            .contact-button {
                display: block;
                width: calc(100% - 0px);
                padding: 18px 30px;
                margin: 10px 0;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 18px;
                text-align: center;
                transition: all 0.2s ease;
                border: none;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                box-sizing: border-box;
            }
            .whatsapp-button {
                background-color: #25D366;
                color: white !important;
            }
            .whatsapp-button:hover {
                background-color: #1ebe57;
                transform: translateY(-1px);
                box-shadow: 0 4px 10px rgba(37, 211, 102, 0.3);
            }
            .call-button {
                background-color: #007bff;
                color: white !important;
            }
            .call-button:hover {
                background-color: #0056b3;
                transform: translateY(-1px);
                box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
            }
            .icon {
                margin-right: 8px;
            }
            @media only screen and (max-width: 600px) {
                .success-container {
                    padding: 25px;
                }
                .contact-button {
                    display: block;
                    width: 100%;
                    margin: 10px 0;
                    padding: 18px 30px;
                    font-size: 18px;
                }
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">✓</div>
            <h1><?php echo $success_title; ?></h1>
            <p><?php echo $success_message; ?> <strong><?php echo esc_html($quote->customer_email); ?></strong></p>
            
            <div class="details">
                <strong>Quote Details:</strong><br>
                Quote ID: #<?php echo str_pad($quote->id, 5, '0', STR_PAD_LEFT); ?><br>
                Customer: <?php echo esc_html($quote->customer_name); ?><br>
                Vehicle: <?php echo esc_html($quote->vehicle_name); ?><br>
                Rental Price: <?php 
                    $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
                    echo $currency . number_format($quote->rental_price, 2);
                ?>
            </div>
            
            <p>The customer will receive an email with a link to view and pay for the quote.</p>
            
            <?php if ($phone_number): ?>
            <div class="contact-section">
                <h3>📞 Contact Customer</h3>
                <p>Follow up with <?php echo esc_html($quote->customer_name); ?> to ensure they received the quote and answer any questions.</p>
                
                <div class="contact-buttons">
                    <a href="<?php echo esc_url($whatsapp_url); ?>" class="contact-button whatsapp-button" target="_blank">
                        <span class="icon">💬</span> WhatsApp with Quote Link
                    </a>
                    <a href="<?php echo esc_url($call_url); ?>" class="contact-button call-button">
                        <span class="icon">📱</span> Call Customer
                    </a>
                </div>
                
                <p style="font-size: 14px; color: #666; margin-top: 15px; margin-bottom: 0;">
                    <strong>Phone:</strong> <?php echo esc_html($quote->customer_phone); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <?php if (current_user_can('manage_options')): ?>
                <div style="margin-top: 30px;">
                    <a href="<?php echo admin_url('admin.php?page=car-rental-quotes'); ?>" class="button">View All Quotes</a>
                    <a href="<?php echo admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote->id); ?>" class="button">View This Quote</a>
                </div>
            <?php else: ?>
                <p style="margin-top: 30px;">You can close this window now.</p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show resend option page when quick send link has already been used
 */
function crqa_show_resend_option($quote, $token) {
    $quote_id = $quote->id;
    $first_send_date = get_post_meta($quote_id, '_crqa_quick_send_date', true);
    $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    
    // Build resend URL
    $resend_url = add_query_arg(array(
        'crqa_action' => 'quick_send',
        'quote_id' => $quote_id,
        'token' => $token,
        'resend' => '1'
    ), home_url('/'));
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Quote Already Sent</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f4f4f4;
                margin: 0;
                padding: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .container {
                background: white;
                padding: 40px 25px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 600px;
                width: 100%;
                box-sizing: border-box;
            }
            .warning-icon {
                color: #ffc107;
                font-size: 60px;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            p {
                color: #666;
                line-height: 1.6;
                margin-bottom: 20px;
            }
            .info-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: left;
                border: 1px solid #dee2e6;
            }
            .info-box strong {
                color: #333;
            }
            .resend-button {
                display: block;
                width: calc(100% - 40px);
                margin: 20px auto;
                padding: 18px 30px;
                background-color: #28a745;
                color: white !important;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                font-size: 18px;
                text-align: center;
                transition: all 0.2s ease;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                box-sizing: border-box;
            }
            .resend-button:hover {
                background-color: #218838;
                transform: translateY(-1px);
                box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
            }
            .secondary-button {
                display: inline-block;
                padding: 12px 24px;
                background: #007cba;
                color: white;
                text-decoration: none;
                border-radius: 4px;
                margin: 5px;
                font-size: 16px;
            }
            .secondary-button:hover {
                background: #005a87;
            }
            .timestamp {
                font-size: 14px;
                color: #999;
                margin-top: 10px;
            }
            @media only screen and (max-width: 600px) {
                h1 {
                    font-size: 24px;
                }
                .resend-button {
                    display: block;
                    width: calc(100% - 20px);
                    margin: 20px auto;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="warning-icon">⚠️</div>
            <h1>Quote Already Sent</h1>
            <p>This quick send link has already been used to send the quote to the customer.</p>
            
            <div class="info-box">
                <strong>Quote Details:</strong><br>
                Quote ID: #<?php echo str_pad($quote->id, 5, '0', STR_PAD_LEFT); ?><br>
                Customer: <?php echo esc_html($quote->customer_name); ?><br>
                Email: <?php echo esc_html($quote->customer_email); ?><br>
                Vehicle: <?php echo esc_html($quote->vehicle_name); ?><br>
                Rental Price: <?php echo $currency . number_format($quote->rental_price, 2); ?>
                
                <?php if ($first_send_date): ?>
                    <div class="timestamp">
                        First sent: <?php echo date('F j, Y g:i A', strtotime($first_send_date)); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <p>Would you like to resend the quote to the customer?</p>
            
            <a href="<?php echo esc_url($resend_url); ?>" class="resend-button">
                📧 Resend to Customer
            </a>
            
            <?php if (current_user_can('manage_options')): ?>
                <div style="margin-top: 30px;">
                    <a href="<?php echo admin_url('admin.php?page=car-rental-quotes'); ?>" class="secondary-button">View All Quotes</a>
                    <a href="<?php echo admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote->id); ?>" class="secondary-button">Edit This Quote</a>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}

/**
 * JavaScript for phone number handling (add to admin pages)
 */
function crqa_add_phone_handling_script() {
    ?>
    <script>
    // Enhanced phone number cleaning for different formats
    function cleanPhoneForWhatsApp(phone) {
        if (!phone) return '';
        
        // Remove all non-numeric characters
        let clean = phone.replace(/[^0-9]/g, '');
        
        // Remove leading zeros
        clean = clean.replace(/^0+/, '');
        
        // Handle UK numbers
        if (clean.length === 10 && clean.startsWith('7')) {
            // Mobile number without country code
            clean = '44' + clean;
        } else if (clean.length === 11 && clean.startsWith('07')) {
            // Mobile number with leading 0
            clean = '44' + clean.substring(1);
        } else if (clean.length === 13 && clean.startsWith('447')) {
            // Already has UK country code
            return clean;
        }
        
        // Add other country codes as needed
        // Example for US numbers:
        // if (clean.length === 10) {
        //     clean = '1' + clean;
        // }
        
        return clean;
    }
    
    // Handle WhatsApp links dynamically
    document.addEventListener('DOMContentLoaded', function() {
        const whatsappLinks = document.querySelectorAll('a[href*="wa.me"]');
        
        whatsappLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const phoneMatch = href.match(/wa\.me\/([0-9]+)/);
                
                if (phoneMatch) {
                    const originalPhone = phoneMatch[1];
                    const cleanedPhone = cleanPhoneForWhatsApp(originalPhone);
                    
                    if (cleanedPhone !== originalPhone) {
                        const newHref = href.replace(/wa\.me\/[0-9]+/, 'wa.me/' + cleanedPhone);
                        this.setAttribute('href', newHref);
                    }
                }
            });
        });
        
        // Handle tel: links
        const telLinks = document.querySelectorAll('a[href*="tel:"]');
        
        telLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                const phoneMatch = href.match(/tel:\+?([0-9]+)/);
                
                if (phoneMatch) {
                    const originalPhone = phoneMatch[1];
                    const cleanedPhone = cleanPhoneForWhatsApp(originalPhone);
                    const newHref = 'tel:+' + cleanedPhone;
                    this.setAttribute('href', newHref);
                }
            });
        });
    });
    </script>
    <?php
}

// Add the script to admin pages
add_action('admin_footer', 'crqa_add_phone_handling_script');

?>