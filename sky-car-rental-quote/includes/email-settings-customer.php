<?php
/**
 * Customer Email Settings Module for Car Rental Quote Automation
 * 
 * This file handles customer email templates and settings
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/email-settings-customer.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register email settings tab and handle form submission
 */
function crqa_email_settings_tab($active_tab) {
    if ($active_tab != 'email') {
        return;
    }

    // Handle form submission
    if (isset($_POST['submit'])) {
        crqa_save_email_settings();
        echo '<div class="notice notice-success"><p>Email settings saved!</p></div>';
    }

    // Display the email settings form
    crqa_display_email_settings_form();
}

/**
 * Save email settings - UPDATED with quote details template
 */
function crqa_save_email_settings() {
    // Save email logo
    update_option('crqa_email_logo', esc_url_raw($_POST['email_logo']));
    
    // Save WhatsApp settings
    update_option('crqa_whatsapp_number', sanitize_text_field($_POST['whatsapp_number']));
    update_option('crqa_whatsapp_message', sanitize_text_field($_POST['whatsapp_message']));
    update_option('crqa_whatsapp_button_text', sanitize_text_field($_POST['whatsapp_button_text']));
    
    // Save phone settings
    update_option('crqa_phone_number', sanitize_text_field($_POST['phone_number']));
    update_option('crqa_call_button_text', sanitize_text_field($_POST['call_button_text']));
    
    // Save email template settings
    update_option('crqa_email_header_text', sanitize_text_field($_POST['email_header_text']));
    update_option('crqa_email_main_content', wp_kses_post($_POST['email_main_content']));
    update_option('crqa_email_button_text', sanitize_text_field($_POST['email_button_text']));
    update_option('crqa_email_footer_text', wp_kses_post($_POST['email_footer_text']));
    
    // Save quote details template settings
    update_option('crqa_email_quote_details_enabled', isset($_POST['quote_details_enabled']) ? 1 : 0);
    update_option('crqa_email_quote_details_title', sanitize_text_field($_POST['quote_details_title']));
    update_option('crqa_email_quote_details_template', wp_kses_post($_POST['quote_details_template']));
    
    // Save social media settings
    update_option('crqa_social_facebook', esc_url_raw($_POST['social_facebook']));
    update_option('crqa_social_whatsapp', esc_url_raw($_POST['social_whatsapp']));
    update_option('crqa_social_instagram', esc_url_raw($_POST['social_instagram']));
    update_option('crqa_social_youtube', esc_url_raw($_POST['social_youtube']));
    update_option('crqa_social_trustpilot', esc_url_raw($_POST['social_trustpilot']));
    update_option('crqa_social_tiktok', esc_url_raw($_POST['social_tiktok']));
    
    // Footer links
    update_option('crqa_footer_links', array(
        'faqs' => array(
            'text' => sanitize_text_field($_POST['footer_faqs']),
            'url' => esc_url_raw($_POST['footer_faqs_url'])
        ),
        'terms' => array(
            'text' => sanitize_text_field($_POST['footer_terms']),
            'url' => esc_url_raw($_POST['footer_terms_url'])
        ),
        'privacy' => array(
            'text' => sanitize_text_field($_POST['footer_privacy']),
            'url' => esc_url_raw($_POST['footer_privacy_url'])
        ),
        'support' => array(
            'text' => sanitize_text_field($_POST['footer_support']),
            'url' => esc_url_raw($_POST['footer_support_url'])
        )
    ));
    
    update_option('crqa_email_copyright', sanitize_text_field($_POST['email_copyright']));
}

/**
 * Display email settings form - UPDATED with Phone settings
 */
function crqa_display_email_settings_form() {
    // Enqueue media uploader for logo
    wp_enqueue_media();
    
    $company_name = get_option('crqa_company_name', 'Your Car Rental Company');
    $email_logo = get_option('crqa_email_logo', '');
    
    // WhatsApp settings
    $whatsapp_number = get_option('crqa_whatsapp_number', '');
    $whatsapp_message = get_option('crqa_whatsapp_message', 'Hello, I\'m interested in quote [crqa_quote_id]');
    $whatsapp_button_text = get_option('crqa_whatsapp_button_text', 'WhatsApp Us');
    
    // Phone settings
    $phone_number = get_option('crqa_phone_number', '');
    $call_button_text = get_option('crqa_call_button_text', 'Call Us');
    
    // Get email template settings with shortcode examples
    $email_header_text = get_option('crqa_email_header_text', 'Dear [crqa_customer_name],');
    $email_main_content = get_option('crqa_email_main_content', 'Thank you for your interest in hiring one of our premium vehicles.

Please find attached your personalised quotation, which includes all relevant details such as rental cost, mileage allowance, delivery options, and deposit information.

Vehicle: [crqa_vehicle_name]
Quote ID: [crqa_quote_id]
Rental Period: [crqa_rental_dates]');
    $email_button_text = get_option('crqa_email_button_text', 'View Quote');
    $email_footer_text = get_option('crqa_email_footer_text', 'If you have any questions or would like to proceed with the booking, feel free to reply to this email or contact us directly.

We look forward to assisting you.

Kind regards,
' . $company_name);
    
    // Quote Details Template settings
    $quote_details_enabled = get_option('crqa_email_quote_details_enabled', 1);
    $quote_details_title = get_option('crqa_email_quote_details_title', 'Quote Details');
    $quote_details_template = get_option('crqa_email_quote_details_template', 
        'Quote ID: [crqa_quote_id]' . "\n" .
        'Vehicle: [crqa_vehicle_name]' . "\n" .
        'Rental Period: [crqa_rental_dates]' . "\n" .
        'Status: [crqa_quote_status]'
    );
    
    // Social media
    $social_facebook = get_option('crqa_social_facebook', '');
    $social_whatsapp = get_option('crqa_social_whatsapp', '');
    $social_instagram = get_option('crqa_social_instagram', '');
    $social_youtube = get_option('crqa_social_youtube', '');
    $social_trustpilot = get_option('crqa_social_trustpilot', '');
    $social_tiktok = get_option('crqa_social_tiktok', '');
    
    // Footer links
    $footer_links = get_option('crqa_footer_links', array(
        'faqs' => array('text' => 'FAQs', 'url' => '/faqs'),
        'terms' => array('text' => 'Terms of use', 'url' => '/terms'),
        'privacy' => array('text' => 'Privacy policy', 'url' => '/privacy'),
        'support' => array('text' => 'Support', 'url' => '/support')
    ));
    
    $email_copyright = get_option('crqa_email_copyright', 'Copyright ' . date('Y') . ' ' . $company_name . '. All rights reserved.');
    ?>
    
    <h2>Email Template Settings</h2>
    <p>Customize the email template that customers receive with their quotes. You can use shortcodes like <code>[crqa_customer_name]</code> and <code>[crqa_vehicle_name]</code> in your email content.</p>
    
    <div style="background: #f0f8ff; border: 1px solid #0073aa; padding: 15px; margin: 20px 0; border-radius: 5px;">
        <h4 style="margin-top: 0;">Available Shortcodes for Emails</h4>
        <div style="columns: 2; column-gap: 30px;">
            <p><strong>Customer Info:</strong><br>
<code>[crqa_customer_name]</code><br>
<code>[crqa_customer_email]</code><br>
<code>[crqa_customer_phone]</code><br>
<code>[crqa_customer_age]</code> - Customer age<br>
<code>[crqa_contact_preference]</code> - Contact preference</p>
            
            <p><strong>Vehicle Info:</strong><br>
            <code>[crqa_vehicle_name]</code> (includes subtitle)<br>
            <code>[crqa_vehicle_subtitle]</code><br>
            <code>[crqa_prepaid_miles]</code></p>
            
            <p><strong>Quote Details:</strong><br>
            <code>[crqa_quote_id]</code><br>
            <code>[crqa_rental_dates]</code><br>
            <code>[crqa_rental_price]</code><br>
            <code>[crqa_deposit_amount]</code><br>
            <code>[crqa_total_amount]</code></p>
            
            <p><strong>Other:</strong><br>
            <code>[crqa_quote_status]</code><br>
            <code>[crqa_quote_date]</code><br>
            <code>[crqa_form_type]</code></p>
        </div>
    </div>
    
    <table class="form-table">
        <tr>
            <th scope="row">Email Logo</th>
            <td>
                <input type="url" name="email_logo" id="email_logo" value="<?php echo esc_attr($email_logo); ?>" class="regular-text" />
                <button type="button" class="button" id="upload-email-logo">Upload Logo</button>
                <p class="description">Logo that appears at the top of quote emails (will be displayed small and left-aligned)</p>
                <?php if ($email_logo): ?>
                    <div style="margin-top: 10px;">
                        <img src="<?php echo esc_url($email_logo); ?>" style="max-width: 100px; max-height: 50px; border: 1px solid #ddd; padding: 5px; background: #fff;">
                        <br>
                        <button type="button" class="button button-link-delete" id="remove-email-logo" style="margin-top: 5px; color: #a00;">Remove Logo</button>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    
    <h3>Contact Integration</h3>
    <table class="form-table">
        <tr>
            <th scope="row">WhatsApp Number</th>
            <td>
                <input type="text" name="whatsapp_number" value="<?php echo esc_attr($whatsapp_number); ?>" class="regular-text" placeholder="e.g., 447123456789" />
                <p class="description">Enter your WhatsApp number with country code (no + or spaces)</p>
            </td>
        </tr>
        <tr>
            <th scope="row">WhatsApp Button Text</th>
            <td>
                <input type="text" name="whatsapp_button_text" value="<?php echo esc_attr($whatsapp_button_text); ?>" class="regular-text" />
                <p class="description">Text for the WhatsApp button in the email</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Default WhatsApp Message</th>
            <td>
                <input type="text" name="whatsapp_message" value="<?php echo esc_attr($whatsapp_message); ?>" class="large-text" />
                <p class="description">Pre-filled message when customer clicks WhatsApp. Use shortcodes like <code>[crqa_quote_id]</code>.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Phone Number</th>
            <td>
                <input type="text" name="phone_number" value="<?php echo esc_attr($phone_number); ?>" class="regular-text" placeholder="e.g., +447123456789" />
                <p class="description">Enter your phone number with country code for the Call Us button</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Call Button Text</th>
            <td>
                <input type="text" name="call_button_text" value="<?php echo esc_attr($call_button_text); ?>" class="regular-text" />
                <p class="description">Text for the Call button in the email</p>
            </td>
        </tr>
    </table>
    
    <h3>Email Content</h3>
    <table class="form-table">
        <tr>
            <th scope="row">Header Text</th>
            <td>
                <input type="text" name="email_header_text" value="<?php echo esc_attr($email_header_text); ?>" class="large-text" />
                <p class="description">The greeting text (use <code>[crqa_customer_name]</code> for personalization)</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Main Content</th>
            <td>
                <textarea name="email_main_content" rows="12" cols="60" class="large-text"><?php echo esc_textarea($email_main_content); ?></textarea>
                <p class="description">The main body of the email. Use shortcodes like <code>[crqa_vehicle_name]</code>, <code>[crqa_rental_dates]</code>, etc.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Button Text</th>
            <td>
                <input type="text" name="email_button_text" value="<?php echo esc_attr($email_button_text); ?>" class="regular-text" />
                <p class="description">Text for the call-to-action button</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Footer Text</th>
            <td>
                <textarea name="email_footer_text" rows="6" cols="60" class="large-text"><?php echo esc_textarea($email_footer_text); ?></textarea>
                <p class="description">Closing message and signature</p>
            </td>
        </tr>
    </table>
    
    <!-- Quote Details Template Section -->
    <h3>Quote Details Template</h3>
    <p>Customize the quote details box that appears in the email. This replaces the hardcoded quote details and gives you full control over what information is displayed and how it's formatted.</p>
    
    <table class="form-table">
        <tr>
            <th scope="row">Enable Quote Details Box</th>
            <td>
                <label>
                    <input type="checkbox" name="quote_details_enabled" value="1" <?php checked($quote_details_enabled, 1); ?>>
                    Show quote details box in emails
                </label>
                <p class="description">Uncheck to remove the quote details section entirely from emails</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Quote Details Title</th>
            <td>
                <input type="text" name="quote_details_title" value="<?php echo esc_attr($quote_details_title); ?>" class="regular-text" />
                <p class="description">The heading text for the quote details box</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Quote Details Template</th>
            <td>
                <textarea name="quote_details_template" rows="8" cols="60" class="large-text" style="font-family: monospace;"><?php echo esc_textarea($quote_details_template); ?></textarea>
                <p class="description">
                    <strong>Customize what appears in the quote details box.</strong> Use any shortcodes from the list above. Each line creates a new detail row.<br>
                    <strong>Examples:</strong><br>
                    • <code>Quote ID: [crqa_quote_id]</code><br>
                    • <code>Vehicle: [crqa_vehicle_name]</code> (includes subtitle)<br>
                    • <code>Vehicle: [crqa_vehicle_name show_subtitle="false"]</code> (name only)<br>
                    • <code>Car Subtitle: [crqa_vehicle_subtitle]</code> (subtitle only)<br>
                    • <code>Pre-paid Miles: [crqa_prepaid_miles]</code><br>
                    • <code>Rental Price: [crqa_rental_price]</code><br>
                    • <code>Deposit: [crqa_deposit_amount]</code><br>
                    • <code>Total: [crqa_total_amount]</code>
                </p>
                
                <!-- Template Examples -->
                <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                    <h4 style="margin-top: 0;">Quick Templates:</h4>
                    <button type="button" class="button" onclick="setQuoteTemplate('basic')">Basic Template</button>
                    <button type="button" class="button" onclick="setQuoteTemplate('detailed')">Detailed Template</button>
                    <button type="button" class="button" onclick="setQuoteTemplate('pricing')">Pricing Focused</button>
                    
                    <script>
                    function setQuoteTemplate(type) {
                        var textarea = document.querySelector('textarea[name="quote_details_template"]');
                        var templates = {
                            'basic': 'Quote ID: [crqa_quote_id]\nVehicle: [crqa_vehicle_name]\nRental Period: [crqa_rental_dates]\nStatus: [crqa_quote_status]',
                            'detailed': 'Quote ID: [crqa_quote_id]\nVehicle: [crqa_vehicle_name]\nCar Subtitle: [crqa_vehicle_subtitle]\nRental Period: [crqa_rental_dates]\nPre-paid Miles: [crqa_prepaid_miles]\nRental Price: [crqa_rental_price]\nDeposit: [crqa_deposit_amount]\nTotal Amount: [crqa_total_amount]\nStatus: [crqa_quote_status]',
                            'pricing': 'Quote ID: [crqa_quote_id]\nVehicle: [crqa_vehicle_name]\nRental Period: [crqa_rental_dates]\nRental Price: [crqa_rental_price]\nSecurity Deposit: [crqa_deposit_amount]\nTotal Amount: [crqa_total_amount]'
                        };
                        
                        if (templates[type]) {
                            textarea.value = templates[type];
                        }
                    }
                    </script>
                </div>
            </td>
        </tr>
    </table>
    
    <h3>Social Media Links</h3>
    <table class="form-table">
        <tr>
            <th scope="row">Facebook</th>
            <td>
                <input type="url" name="social_facebook" value="<?php echo esc_attr($social_facebook); ?>" class="regular-text" placeholder="https://facebook.com/yourpage" />
            </td>
        </tr>
        <tr>
            <th scope="row">WhatsApp</th>
            <td>
                <input type="url" name="social_whatsapp" value="<?php echo esc_attr($social_whatsapp); ?>" class="regular-text" placeholder="https://wa.me/1234567890" />
            </td>
        </tr>
        <tr>
            <th scope="row">Instagram</th>
            <td>
                <input type="url" name="social_instagram" value="<?php echo esc_attr($social_instagram); ?>" class="regular-text" placeholder="https://instagram.com/yourhandle" />
            </td>
        </tr>
        <tr>
            <th scope="row">YouTube</th>
            <td>
                <input type="url" name="social_youtube" value="<?php echo esc_attr($social_youtube); ?>" class="regular-text" placeholder="https://youtube.com/yourchannel" />
            </td>
        </tr>
        <tr>
            <th scope="row">Trustpilot</th>
            <td>
                <input type="url" name="social_trustpilot" value="<?php echo esc_attr($social_trustpilot); ?>" class="regular-text" placeholder="https://trustpilot.com/review/yourcompany.com" />
            </td>
        </tr>
        <tr>
            <th scope="row">TikTok</th>
            <td>
                <input type="url" name="social_tiktok" value="<?php echo esc_attr($social_tiktok); ?>" class="regular-text" placeholder="https://tiktok.com/@yourhandle" />
            </td>
        </tr>
    </table>
    
    <h3>Footer Links</h3>
    <table class="form-table">
        <tr>
            <th scope="row">FAQs</th>
            <td>
                <input type="text" name="footer_faqs" value="<?php echo esc_attr($footer_links['faqs']['text'] ?? 'FAQs'); ?>" class="regular-text" placeholder="Link text" />
                <input type="url" name="footer_faqs_url" value="<?php echo esc_attr($footer_links['faqs']['url'] ?? '/faqs'); ?>" class="regular-text" placeholder="https://example.com/faqs" />
                <p class="description">Enter the full URL including https:// for external links, or just the path (e.g., /faqs) for internal links</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Terms of Use</th>
            <td>
                <input type="text" name="footer_terms" value="<?php echo esc_attr($footer_links['terms']['text'] ?? 'Terms of use'); ?>" class="regular-text" placeholder="Link text" />
                <input type="url" name="footer_terms_url" value="<?php echo esc_attr($footer_links['terms']['url'] ?? '/terms'); ?>" class="regular-text" placeholder="https://example.com/terms" />
            </td>
        </tr>
        <tr>
            <th scope="row">Privacy Policy</th>
            <td>
                <input type="text" name="footer_privacy" value="<?php echo esc_attr($footer_links['privacy']['text'] ?? 'Privacy policy'); ?>" class="regular-text" placeholder="Link text" />
                <input type="url" name="footer_privacy_url" value="<?php echo esc_attr($footer_links['privacy']['url'] ?? '/privacy'); ?>" class="regular-text" placeholder="https://example.com/privacy" />
            </td>
        </tr>
        <tr>
            <th scope="row">Support</th>
            <td>
                <input type="text" name="footer_support" value="<?php echo esc_attr($footer_links['support']['text'] ?? 'Support'); ?>" class="regular-text" placeholder="Link text" />
                <input type="url" name="footer_support_url" value="<?php echo esc_attr($footer_links['support']['url'] ?? '/support'); ?>" class="regular-text" placeholder="https://example.com/support" />
            </td>
        </tr>
    </table>
    
    <h3>Copyright</h3>
    <table class="form-table">
        <tr>
            <th scope="row">Copyright Text</th>
            <td>
                <input type="text" name="email_copyright" value="<?php echo esc_attr($email_copyright); ?>" class="large-text" />
                <p class="description">Copyright notice at the bottom of the email</p>
            </td>
        </tr>
    </table>
    
    <?php submit_button('Save Email Settings'); ?>
    
    <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
        <h4>Preview & Test</h4>
        <p>Save your settings first, then you can send a test email to see how it looks.</p>
        <p><button type="button" id="send-test-email" class="button">Send Test Email</button></p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Media uploader for email logo
        $('#upload-email-logo').click(function(e) {
            e.preventDefault();
            var mediaUploader = wp.media({
                title: 'Choose Email Logo',
                button: {
                    text: 'Use this logo'
                },
                multiple: false
            }).on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#email_logo').val(attachment.url);
                $('#email_logo').trigger('change');
            }).open();
        });
        
        // Remove logo
        $('#remove-email-logo').click(function(e) {
            e.preventDefault();
            if (confirm('Remove the email logo?')) {
                $('#email_logo').val('');
                $(this).parent().remove();
            }
        });
        
        // Update logo preview on URL change
        $('#email_logo').on('change keyup', function() {
            var url = $(this).val();
            if (url) {
                if (!$('#email-logo-preview').length) {
                    $(this).closest('td').append('<div id="email-logo-preview" style="margin-top: 10px;"><img src="" style="max-width: 150px; max-height: 60px; border: 1px solid #ddd; padding: 5px; background: #fff;"><br><button type="button" class="button button-link-delete" id="remove-email-logo" style="margin-top: 5px; color: #a00;">Remove Logo</button></div>');
                }
                $('#email-logo-preview img').attr('src', url);
            }
        });
        
        // Send test email
        $('#send-test-email').click(function() {
            var adminEmails = <?php 
                $admin_emails = get_option('crqa_admin_emails', array());
                if (empty($admin_emails)) {
                    $admin_emails = array(get_option('admin_email'));
                }
                echo json_encode($admin_emails[0]);
            ?>;
            
            if (confirm('Send a test email to ' + adminEmails + '?')) {
                var $button = $(this);
                $button.prop('disabled', true).text('Sending...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'crqa_send_test_email',
                        nonce: '<?php echo wp_create_nonce('crqa_test_email'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                        } else {
                            alert('Failed to send test email. Please try again.');
                        }
                        $button.prop('disabled', false).text('Send Test Email');
                    },
                    error: function() {
                        alert('Error sending test email. Please try again.');
                        $button.prop('disabled', false).text('Send Test Email');
                    }
                });
            }
        });
        
        // Toggle quote details template based on checkbox
        $('input[name="quote_details_enabled"]').on('change', function() {
            var $rows = $(this).closest('tr').nextAll('tr').slice(0, 2);
            if ($(this).is(':checked')) {
                $rows.show();
            } else {
                $rows.hide();
            }
        }).trigger('change');
    });
    </script>
    
    <?php
}

/**
 * Build HTML email template for customers - UPDATED with contact buttons and header
 */
function crqa_build_email_html($quote, $quote_url, $settings) {
    // Contact settings
    $whatsapp_number = get_option('crqa_whatsapp_number', '');
    $whatsapp_message = get_option('crqa_whatsapp_message', 'Hello, I\'m interested in quote [crqa_quote_id]');
    $whatsapp_button_text = get_option('crqa_whatsapp_button_text', 'WhatsApp Us');
    $phone_number = get_option('crqa_phone_number', '');
    $call_button_text = get_option('crqa_call_button_text', 'Call Us');
    
    // Process shortcodes in WhatsApp message
    $whatsapp_message = crqa_process_email_shortcodes($whatsapp_message, $quote);
    
    $whatsapp_url = '';
    if ($whatsapp_number) {
        $whatsapp_url = 'https://wa.me/' . $whatsapp_number . '?text=' . urlencode($whatsapp_message);
    }
    
    $call_url = '';
if ($phone_number) {
    $clean_phone = preg_replace('/[^0-9+]/', '', $phone_number);
    // Add + if missing
    if (!empty($clean_phone) && strpos($clean_phone, '+') !== 0) {
        $clean_phone = '+' . $clean_phone;
    }
    if (!empty($clean_phone)) {
        $call_url = 'tel:' . $clean_phone;
    }
}
    
    // Social media
    $social_facebook = get_option('crqa_social_facebook', '');
    $social_whatsapp = get_option('crqa_social_whatsapp', '');
    $social_instagram = get_option('crqa_social_instagram', '');
    $social_youtube = get_option('crqa_social_youtube', '');
    $social_trustpilot = get_option('crqa_social_trustpilot', '');
    $social_tiktok = get_option('crqa_social_tiktok', '');
    
    // Footer links
    $footer_links = get_option('crqa_footer_links', array(
        'faqs' => array('text' => 'FAQs', 'url' => '/faqs'),
        'terms' => array('text' => 'Terms of use', 'url' => '/terms'),
        'privacy' => array('text' => 'Privacy policy', 'url' => '/privacy'),
        'support' => array('text' => 'Support', 'url' => '/support')
    ));
    
    $email_copyright = get_option('crqa_email_copyright', 'Copyright ' . date('Y') . ' ' . $settings['company_name'] . '. All rights reserved.');
    
    // Quote Details Template settings
    $quote_details_enabled = get_option('crqa_email_quote_details_enabled', 1);
    $quote_details_title = get_option('crqa_email_quote_details_title', 'Quote Details');
    $quote_details_template = get_option('crqa_email_quote_details_template', 
        'Quote ID: [crqa_quote_id]' . "\n" .
        'Vehicle: [crqa_vehicle_name]' . "\n" .
        'Rental Period: [crqa_rental_dates]' . "\n" .
        'Status: [crqa_quote_status]'
    );
    
    // Build HTML email
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
    .header-text-section { display: table-cell; vertical-align: middle; text-align: right; }
.email-body { 
    padding: 30px 40px; 
    box-sizing: border-box;
}    .email-footer { background-color: #f8f9fa; padding: 30px 40px; border-top: 1px solid #e0e0e0; }
    .contact-button { 
    display: inline-block; 
    flex: 1 1 0;
    max-width: 280px;
    padding: 20px 30px; 
    margin: 0; 
    text-decoration: none !important; 
    border-radius: 12px; 
    font-weight: 600; 
    font-size: 18px; 
    text-align: center; 
    box-sizing: border-box; 
    transition: all 0.2s ease; 
    border: none;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: inherit;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}
    .contact-button { 
    display: inline-block; 
    flex: 1;
    padding: 20px 30px; 
    margin: 0; 
    text-decoration: none !important; 
    border-radius: 12px; 
    font-weight: 600; 
    font-size: 18px; 
    text-align: center; 
    box-sizing: border-box; 
    transition: all 0.2s ease; 
    border: none;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: inherit;
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}
    .whatsapp-button { 
        background-color: #25D366; 
        color: white !important; 
        text-decoration: none !important;
    }
    .whatsapp-button:hover { background-color: #1ebe57; }
    .call-button { 
        background-color: #007bff; 
        color: white !important;
        text-decoration: none !important;
        -webkit-appearance: none;
        appearance: none;
    }
    .call-button:hover { background-color: #0056b3; }
    .quote-button { 
    background-color: #dca725; 
    color: #000000 !important;
    text-decoration: none !important;
}
.quote-button:hover { 
    background-color: #c49620; 
}
    .social-links { margin: 25px 0; text-align: center; }
    .social-links a { display: inline-block; margin: 0 8px; text-decoration: none; }
    .social-icon { display: inline-block; width: 40px; height: 40px; border-radius: 50%; text-align: center; vertical-align: middle; }
    .social-icon img { width: 40px; height: 40px; border-radius: 50%; }
    .footer-links { text-align: center; margin: 20px 0; }
    .footer-links a { color: #666; text-decoration: none; margin: 0 10px; font-size: 14px; display: inline-block; }
    .footer-links a:hover { text-decoration: underline; }
    .copyright { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
    .quote-details { background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; }
    .quote-details h3 { margin-top: 0; color: #333; }
    .quote-details p { margin: 8px 0; }
    
    @media only screen and (max-width: 600px) {
        .header-content { width: 100%; }
        .logo-section { display: inline-block !important; width: 50%; text-align: left !important; }
        .header-text-section { display: inline-block !important; width: 50%; text-align: right !important; }
        .logo-section img { max-width: 80px !important; }
        .email-body { 
    padding: 20px; 
    box-sizing: border-box;
}
        .email-footer { padding: 20px; }
        .footer-links { white-space: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .footer-links a { margin: 0 8px; white-space: nowrap; }
        .social-links a { margin: 0 5px; }
        .button { font-size: 15px; }
        .contact-buttons { padding: 0 20px; }
        .contact-buttons {
    flex-direction: row !important;
    gap: 12px !important;
    padding: 0 15px !important;
    margin: 25px 0 !important;
}
.contact-button { 
    display: inline-block !important; 
    flex: 1 !important;
    min-width: 0 !important;
    margin: 0 !important; 
    padding: 18px 15px !important; 
    font-size: 16px !important; 
    font-weight: 600 !important;
    border-radius: 10px !important;
    -webkit-appearance: none !important;
    appearance: none !important;
    touch-action: manipulation !important;
    text-decoration: none !important;
    position: relative !important;
    z-index: 1 !important;
}
        
        /* Specific mobile fixes for call button */
        .call-button {
            -webkit-touch-callout: default !important;
            -webkit-user-select: auto !important;
            user-select: auto !important;
        }
    }
    
    @media only screen and (max-width: 480px) {
        .contact-buttons { padding: 0 10px; }
        .contact-button { 
            padding: 14px 25px !important; 
            font-size: 16px !important;
        }
    }
</style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">';
    
    // Header with logo and quote info
    $message .= '
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
                            <td class="header-text-section" style="text-align: right;">
                                <div style="color: #666; font-size: 14px; margin-bottom: 5px;">New Quote Request</div>
                                <div style="font-size: 18px; font-weight: 600; color: #333;">Quote #' . str_pad($quote->id, 5, '0', STR_PAD_LEFT) . '</div>
                            </td>
                        </tr>
                    </table>
                </div>';
    
    // Email body
    $message .= '
                <div class="email-body">
                    <p style="font-size: 16px; margin-bottom: 10px;">' . nl2br(esc_html($settings['header_text'])) . '</p>
                    
                    <div style="font-size: 16px; margin-bottom: 20px;">' . nl2br(esc_html($settings['main_content'])) . '</div>';
    
    // Customizable Quote Details Template
    if ($quote_details_enabled) {
        // Process the quote details template with shortcodes
        $processed_quote_details = crqa_process_email_shortcodes($quote_details_template, $quote);
        
        $message .= '
                    <div class="quote-details">
                        <h3>' . esc_html($quote_details_title) . '</h3>';
        
        // Split template into lines and create paragraph for each
        $detail_lines = explode("\n", $processed_quote_details);
        foreach ($detail_lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $message .= '<p><strong>' . $line . '</strong></p>';
            }
        }
        
        $message .= '
                    </div>';
    }
    
    // Combined buttons section using table for better email compatibility
$message .= '
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 30px 0;">
                        <tr>
                            <td style="padding: 0;">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>';

// View Quote button
$message .= '
                                        <td style="padding-right: 10px; width: 50%;">
                                            <a href="' . esc_url($quote_url) . '" class="contact-button quote-button" style="display: block; width: 100%; box-sizing: border-box;">' . esc_html($settings['button_text']) . '</a>
                                        </td>';

// WhatsApp button
if ($whatsapp_number) {
    $message .= '
                                        <td style="padding-left: 10px; width: 50%;">
                                            <a href="' . esc_url($whatsapp_url) . '" class="contact-button whatsapp-button" target="_blank" style="display: block; width: 100%; box-sizing: border-box;">' . esc_html($whatsapp_button_text) . '</a>
                                        </td>';
}

$message .= '
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>';
    
    $message .= '
                    <div style="font-size: 15px; margin-top: 30px;">' . nl2br(esc_html($settings['footer_text'])) . '</div>
                </div>
                
                <div class="email-footer">';
    // Get social media icons
    $icon_facebook = CRQA_PLUGIN_URL . 'assets/img/facebook.png';
    $icon_whatsapp = CRQA_PLUGIN_URL . 'assets/img/whatsapp.png';
    $icon_instagram = CRQA_PLUGIN_URL . 'assets/img/instagram.png';
    $icon_youtube = CRQA_PLUGIN_URL . 'assets/img/youtube.png';
    $icon_trustpilot = CRQA_PLUGIN_URL . 'assets/img/Trustpilot.png';
    $icon_tiktok = CRQA_PLUGIN_URL . 'assets/img/tik-tok.png';
    
    // Social media links with bordered style
    $has_social = ($social_facebook || $social_whatsapp || $social_instagram || $social_youtube || $social_trustpilot || $social_tiktok);
    if ($has_social) {
        $message .= '
                    <div class="social-links">
                        <table align="center" border="0" cellpadding="0" cellspacing="0">
                            <tr>';
        
        if ($social_facebook && $icon_facebook) {
            $message .= '
                                <td style="padding: 0 4px;">
                                    <a href="' . esc_url($social_facebook) . '" style="display: block; width: 32px; height: 32px; border: 1px solid #c8c8c8; border-radius: 4px; text-align: center; line-height: 32px; text-decoration: none; background-color: #ffffff;">
                                        <img src="' . esc_url($icon_facebook) . '" alt="Facebook" width="20" height="20" style="display: inline-block; vertical-align: middle; border: 0;">
                                    </a>
                                </td>';
        }
        
        if ($social_whatsapp && $icon_whatsapp) {
            $message .= '
                                <td style="padding: 0 4px;">
                                    <a href="' . esc_url($social_whatsapp) . '" style="display: block; width: 32px; height: 32px; border: 1px solid #c8c8c8; border-radius: 4px; text-align: center; line-height: 32px; text-decoration: none; background-color: #ffffff;">
                                        <img src="' . esc_url($icon_whatsapp) . '" alt="WhatsApp" width="20" height="20" style="display: inline-block; vertical-align: middle; border: 0;">
                                    </a>
                                </td>';
        }
        
        if ($social_instagram && $icon_instagram) {
            $message .= '
                                <td style="padding: 0 4px;">
                                    <a href="' . esc_url($social_instagram) . '" style="display: block; width: 32px; height: 32px; border: 1px solid #c8c8c8; border-radius: 4px; text-align: center; line-height: 32px; text-decoration: none; background-color: #ffffff;">
                                        <img src="' . esc_url($icon_instagram) . '" alt="Instagram" width="20" height="20" style="display: inline-block; vertical-align: middle; border: 0;">
                                    </a>
                                </td>';
        }
        
        if ($social_youtube && $icon_youtube) {
            $message .= '
                                <td style="padding: 0 4px;">
                                    <a href="' . esc_url($social_youtube) . '" style="display: block; width: 32px; height: 32px; border: 1px solid #c8c8c8; border-radius: 4px; text-align: center; line-height: 32px; text-decoration: none; background-color: #ffffff;">
                                        <img src="' . esc_url($icon_youtube) . '" alt="YouTube" width="20" height="20" style="display: inline-block; vertical-align: middle; border: 0;">
                                    </a>
                                </td>';
        }
        
        if ($social_trustpilot && $icon_trustpilot) {
            $message .= '
                                <td style="padding: 0 4px;">
                                    <a href="' . esc_url($social_trustpilot) . '" style="display: block; width: 32px; height: 32px; border: 1px solid #c8c8c8; border-radius: 4px; text-align: center; line-height: 32px; text-decoration: none; background-color: #ffffff;">
                                        <img src="' . esc_url($icon_trustpilot) . '" alt="Trustpilot" width="20" height="20" style="display: inline-block; vertical-align: middle; border: 0;">
                                    </a>
                                </td>';
        }
        
        if ($social_tiktok && $icon_tiktok) {
            $message .= '
                                <td style="padding: 0 4px;">
                                    <a href="' . esc_url($social_tiktok) . '" style="display: block; width: 32px; height: 32px; border: 1px solid #c8c8c8; border-radius: 4px; text-align: center; line-height: 32px; text-decoration: none; background-color: #ffffff;">
                                        <img src="' . esc_url($icon_tiktok) . '" alt="TikTok" width="20" height="20" style="display: inline-block; vertical-align: middle; border: 0;">
                                    </a>
                                </td>';
        }
        
        $message .= '
                            </tr>
                        </table>
                    </div>';
    }
    
    // Footer links
    // Footer links
$message .= '
            <div class="footer-links">';

// FAQs
$faqs_url = $footer_links['faqs']['url'] ?? '/faqs';
$faqs_url = (strpos($faqs_url, 'http') === 0) ? $faqs_url : home_url($faqs_url);
$message .= '<a href="' . esc_url($faqs_url) . '">' . esc_html($footer_links['faqs']['text'] ?? 'FAQs') . '</a>';

// Terms
$terms_url = $footer_links['terms']['url'] ?? '/terms';
$terms_url = (strpos($terms_url, 'http') === 0) ? $terms_url : home_url($terms_url);
$message .= '<a href="' . esc_url($terms_url) . '">' . esc_html($footer_links['terms']['text'] ?? 'Terms of use') . '</a>';

// Privacy
$privacy_url = $footer_links['privacy']['url'] ?? '/privacy';
$privacy_url = (strpos($privacy_url, 'http') === 0) ? $privacy_url : home_url($privacy_url);
$message .= '<a href="' . esc_url($privacy_url) . '">' . esc_html($footer_links['privacy']['text'] ?? 'Privacy policy') . '</a>';

// Support
$support_url = $footer_links['support']['url'] ?? '/support';
$support_url = (strpos($support_url, 'http') === 0) ? $support_url : home_url($support_url);
$message .= '<a href="' . esc_url($support_url) . '">' . esc_html($footer_links['support']['text'] ?? 'Support') . '</a>';

$message .= '
            </div>
                    
                    <div class="copyright">
                        <p>' . esc_html($email_copyright) . '</p>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $message;
}

/**
 * Process shortcodes in email content - UPDATED to fix prepaid miles
 */
function crqa_process_email_shortcodes($content, $quote) {
    // Store the quote in global for shortcodes to access
    $GLOBALS['crqa_current_quote'] = $quote;
    
    // Process all shortcodes
    $processed_content = do_shortcode($content);
    
    // Clean up
    unset($GLOBALS['crqa_current_quote']);
    
    return $processed_content;
}

/**
 * Send quote email to customer - UPDATED with shortcode support
 */
function crqa_send_quote_email($quote_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
    
    if (!$quote) return;
    // FIX: Check if mileage_allowance contains slug and replace with actual value
    if ($quote->mileage_allowance === 'pre-paid-miles' && !empty($quote->product_id)) {
        // Include the shared functions file if not already loaded
        if (!function_exists('crqa_get_product_attribute')) {
            require_once CRQA_PLUGIN_PATH . 'includes/quote-shared-functions.php';
        }
        
        $actual_miles = crqa_get_product_attribute($quote->product_id, 'pre_paid_miles');
        if ($actual_miles && $actual_miles !== 'pre-paid-miles') {
            $quote->mileage_allowance = $actual_miles;
            
            // Update database so future emails don't need this fix
            $wpdb->update(
                $table_name,
                array('mileage_allowance' => $actual_miles),
                array('id' => $quote_id)
            );
        }
    }
    
    $company_name = get_option('crqa_company_name', 'Your Car Rental Company');
    $company_email = get_option('crqa_company_email', get_option('admin_email'));
    $email_logo = get_option('crqa_email_logo', '');

    // Sanitize company name for email header to prevent header injection
    $safe_company_name = preg_replace('/[\r\n\t]/', '', $company_name);
    $safe_company_name = sanitize_text_field($safe_company_name);

    // Validate and sanitize email
    $safe_company_email = sanitize_email($company_email);
    if (!is_email($safe_company_email)) {
        $safe_company_email = get_option('admin_email');
    }

    $quote_url = home_url('/quote/' . $quote->quote_hash);

    $subject = 'Your Car Rental Quote from ' . $safe_company_name;
    
    // Get email template settings
    $email_header_text = get_option('crqa_email_header_text', 'Dear [crqa_customer_name],');
    $email_main_content = get_option('crqa_email_main_content', 'Thank you for your interest in hiring one of our premium vehicles.

Please find attached your personalised quotation, which includes all relevant details such as rental cost, mileage allowance, delivery options, and deposit information.

Vehicle: [crqa_vehicle_name]
Quote ID: [crqa_quote_id]
Rental Period: [crqa_rental_dates]');
    $email_button_text = get_option('crqa_email_button_text', 'View Quote');
    $email_footer_text = get_option('crqa_email_footer_text', 'If you have any questions or would like to proceed with the booking, feel free to reply to this email or contact us directly.

We look forward to assisting you.

Kind regards,
' . $company_name);
    
    // Process shortcodes in all email content
    $email_header_text = crqa_process_email_shortcodes($email_header_text, $quote);
    $email_main_content = crqa_process_email_shortcodes($email_main_content, $quote);
    $email_footer_text = crqa_process_email_shortcodes($email_footer_text, $quote);
    
    // Build the email HTML
    $message = crqa_build_email_html($quote, $quote_url, array(
        'company_name' => $company_name,
        'email_logo' => $email_logo,
        'header_text' => $email_header_text,
        'main_content' => $email_main_content,
        'button_text' => $email_button_text,
        'footer_text' => $email_footer_text
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $safe_company_name . ' <' . $safe_company_email . '>'
    );

    wp_mail($quote->customer_email, $subject, $message, $headers);
}

/**
 * AJAX handler for sending test email
 */
add_action('wp_ajax_crqa_send_test_email', 'crqa_ajax_send_test_email');
function crqa_ajax_send_test_email() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'crqa_test_email')) {
        wp_die('Security check failed');
    }
    
    // Create a dummy quote for testing
    $test_quote = (object) array(
        'id' => 999,
        'customer_name' => 'Test Customer',
        'customer_email' => get_option('crqa_company_email', get_option('admin_email')),
        'customer_phone' => '+447123456789',
        'vehicle_name' => 'Lamborghini - Urus Tiffany',
        'rental_dates' => 'January 1-7, 2025',
        'rental_price' => 500.00,
        'deposit_amount' => 200.00,
        'quote_status' => 'quoted',
        'quote_hash' => 'test123',
        'product_id' => 18193,
        'mileage_allowance' => '1000 miles included'
    );
    
    // Send the test email
    crqa_send_quote_email_test($test_quote);
    
    wp_send_json_success(array('message' => 'Test email sent successfully!'));
}

/**
 * Send test email (similar to regular email but with test data)
 */
function crqa_send_quote_email_test($quote) {
    $company_name = get_option('crqa_company_name', 'Your Car Rental Company');
    $company_email = get_option('crqa_company_email', get_option('admin_email'));
    $email_logo = get_option('crqa_email_logo', '');

    // Sanitize company name for email header to prevent header injection
    $safe_company_name = preg_replace('/[\r\n\t]/', '', $company_name);
    $safe_company_name = sanitize_text_field($safe_company_name);

    // Validate and sanitize email
    $safe_company_email = sanitize_email($company_email);
    if (!is_email($safe_company_email)) {
        $safe_company_email = get_option('admin_email');
    }

    $quote_url = home_url('/quote/test-preview');

    $subject = '[TEST] Your Car Rental Quote from ' . $safe_company_name;
    
    // Get email template settings
    $email_header_text = get_option('crqa_email_header_text', 'Dear [crqa_customer_name],');
    $email_main_content = get_option('crqa_email_main_content', 'Thank you for your interest in hiring one of our premium vehicles.

Please find attached your personalised quotation, which includes all relevant details such as rental cost, mileage allowance, delivery options, and deposit information.

Vehicle: [crqa_vehicle_name]
Quote ID: [crqa_quote_id]
Rental Period: [crqa_rental_dates]');
    $email_button_text = get_option('crqa_email_button_text', 'View Quote');
    $email_footer_text = get_option('crqa_email_footer_text', 'If you have any questions or would like to proceed with the booking, feel free to reply to this email or contact us directly.

We look forward to assisting you.

Kind regards,
' . $company_name);
    
    // Process shortcodes in test email
    $email_header_text = crqa_process_email_shortcodes($email_header_text, $quote);
    $email_main_content = crqa_process_email_shortcodes($email_main_content, $quote);
    $email_footer_text = crqa_process_email_shortcodes($email_footer_text, $quote);
    
    // Build the email HTML
    $message = crqa_build_email_html($quote, $quote_url, array(
        'company_name' => $company_name,
        'email_logo' => $email_logo,
        'header_text' => $email_header_text,
        'main_content' => $email_main_content,
        'button_text' => $email_button_text,
        'footer_text' => $email_footer_text
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $safe_company_name . ' <' . $safe_company_email . '>'
    );

    wp_mail($safe_company_email, $subject, $message, $headers);
}

?>