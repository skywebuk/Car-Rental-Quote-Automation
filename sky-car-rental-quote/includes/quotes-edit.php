<?php
/**
 * Edit Quote Page for Car Rental Quote Automation - WITH ACTIVITY TRACKING
 * 
 * This file handles the quote editing functionality with customer activity tracking
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/quotes-edit.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define CRQA_PLUGIN_PATH if not defined
if (!defined('CRQA_PLUGIN_PATH')) {
    define('CRQA_PLUGIN_PATH', plugin_dir_path(dirname(__FILE__)));
}

// Include ALL necessary functions inline to ensure they exist
if (!function_exists('crqa_format_price')) {
    function crqa_format_price($price) {
        if (function_exists('wc_price')) {
            return strip_tags(wc_price($price));
        } else if (function_exists('get_woocommerce_currency_symbol')) {
            $currency_pos = get_option('woocommerce_currency_pos', 'left');
            $symbol = get_woocommerce_currency_symbol();
            $formatted = number_format($price, 0); // No decimals
            
            switch ($currency_pos) {
                case 'right':
                    return $formatted . $symbol;
                case 'right_space':
                    return $formatted . ' ' . $symbol;
                case 'left_space':
                    return $symbol . ' ' . $formatted;
                default:
                    return $symbol . $formatted;
            }
        } else {
            return '$' . number_format($price, 0); // No decimals
        }
    }
}

if (!function_exists('crqa_get_price_per_day')) {
    function crqa_get_price_per_day($product_id) {
        if (!$product_id || !function_exists('wc_get_product')) {
            return 0;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }
        
        // Try to get price_per_day from taxonomy attribute
        $price_terms = wp_get_post_terms($product_id, 'pa_price_per_day');
        if (!is_wp_error($price_terms) && !empty($price_terms)) {
            $price_value = $price_terms[0]->name;
            $price_numeric = preg_replace('/[^0-9.]/', '', $price_value);
            return floatval($price_numeric);
        }
        
        // Fallback to custom meta field
        $price_meta = get_post_meta($product_id, 'price_per_day', true);
        if ($price_meta) {
            $price_numeric = preg_replace('/[^0-9.]/', '', $price_meta);
            return floatval($price_numeric);
        }
        
        return 0;
    }
}

if (!function_exists('crqa_calculate_rental_days')) {
    function crqa_calculate_rental_days($rental_dates) {
        if (empty($rental_dates)) {
            return 1;
        }
        
        // Pattern 1: DD/MM/YYYY to DD/MM/YYYY format (UK format)
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*(?:to|[-–])\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $rental_dates, $matches)) {
            $start_date = DateTime::createFromFormat('d/m/Y', $matches[1] . '/' . $matches[2] . '/' . $matches[3]);
            $end_date = DateTime::createFromFormat('d/m/Y', $matches[4] . '/' . $matches[5] . '/' . $matches[6]);
            
            if ($start_date && $end_date) {
                $interval = $start_date->diff($end_date);
                $days = $interval->days;
                return max(1, $days);
            }
        }
        
        // Pattern 2: "Month DD-DD, YYYY"
        if (preg_match('/(\w+)\s+(\d+)-(\d+),\s*(\d{4})/', $rental_dates, $matches)) {
            $start_day = intval($matches[2]);
            $end_day = intval($matches[3]);
            $days = $end_day - $start_day;
            return max(1, $days);
        }
        
        // Pattern 3: Look for explicit day count in text
        if (preg_match('/(\d+)\s*(?:days?|nights?)/', strtolower($rental_dates), $matches)) {
            $days = intval($matches[1]);
            return max(1, $days);
        }
        
        return 1;
    }
}

if (!function_exists('crqa_clean_phone_number')) {
    function crqa_clean_phone_number($phone) {
        if (empty($phone)) {
            return '';
        }
        
        // Remove all non-numeric characters
        $clean = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading zeros
        $clean = ltrim($clean, '0');
        
        // If it doesn't start with country code, assume UK (+44)
        if (strlen($clean) == 10 && substr($clean, 0, 1) == '7') {
            $clean = '44' . $clean;
        } elseif (strlen($clean) == 11 && substr($clean, 0, 2) == '07') {
            $clean = '44' . substr($clean, 1);
        }
        
        return $clean;
    }
}

if (!function_exists('crqa_get_product_attribute')) {
    function crqa_get_product_attribute($product_id, $attribute_name) {
        if (!function_exists('wc_get_product')) {
            return '';
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }
        
        // Try taxonomy attribute first
        $taxonomy_name = 'pa_' . $attribute_name;
        $terms = wp_get_post_terms($product_id, $taxonomy_name);
        
        if (!is_wp_error($terms) && !empty($terms)) {
            return $terms[0]->name;
        }
        
        // Try custom meta
        $meta_value = get_post_meta($product_id, $attribute_name, true);
        if ($meta_value) {
            return $meta_value;
        }
        
        return '';
    }
}

/**
 * Get customer activity for a quote
 */
function crqa_get_quote_activity($quote_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    // Create table if it doesn't exist
    crqa_create_activity_table();
    
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE quote_id = %d ORDER BY activity_time DESC",
        $quote_id
    ));
    
    return $activities;
}

/**
 * Create activity tracking table
 */
function crqa_create_activity_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        quote_id mediumint(9) NOT NULL,
        activity_type varchar(50) NOT NULL,
        activity_time datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45),
        user_agent text,
        referrer_url text,
        extra_data text,
        PRIMARY KEY (id),
        KEY quote_id (quote_id),
        KEY activity_type (activity_type),
        KEY activity_time (activity_time)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Get activity summary for a quote
 */
function crqa_get_activity_summary($quote_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    $summary = array(
        'first_view' => null,
        'last_view' => null,
        'total_views' => 0,
        'unique_views' => 0,
        'checkout_attempts' => 0,
        'last_checkout_attempt' => null,
        'payment_completed' => false
    );
    
    // Get first and last view
    $view_data = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            MIN(activity_time) as first_view,
            MAX(activity_time) as last_view,
            COUNT(*) as total_views,
            COUNT(DISTINCT ip_address) as unique_views
        FROM {$table_name} 
        WHERE quote_id = %d AND activity_type = 'quote_viewed'",
        $quote_id
    ));
    
    if ($view_data) {
        $summary['first_view'] = $view_data->first_view;
        $summary['last_view'] = $view_data->last_view;
        $summary['total_views'] = intval($view_data->total_views);
        $summary['unique_views'] = intval($view_data->unique_views);
    }
    
    // Get checkout attempts
    $checkout_data = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as checkout_attempts,
            MAX(activity_time) as last_checkout
        FROM {$table_name} 
        WHERE quote_id = %d AND activity_type = 'checkout_started'",
        $quote_id
    ));
    
    if ($checkout_data) {
        $summary['checkout_attempts'] = intval($checkout_data->checkout_attempts);
        $summary['last_checkout_attempt'] = $checkout_data->last_checkout;
    }
    
    // Check if payment was completed
    $payment_completed = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} 
        WHERE quote_id = %d AND activity_type = 'payment_completed'",
        $quote_id
    ));
    
    $summary['payment_completed'] = $payment_completed > 0;
    
    return $summary;
}

/**
 * Edit quote page - MAIN FUNCTION
 */
function crqa_edit_quote_page($quote_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    // Validate quote ID
    $quote_id = intval($quote_id);
    if (!$quote_id) {
        wp_die('Invalid quote ID');
    }
    
    // Handle form submission
    if (isset($_POST['submit'])) {
        // Verify nonce before processing (also verified inside crqa_update_quote as defense-in-depth)
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'edit_quote_' . $quote_id)) {
            crqa_update_quote($quote_id);
            // Instead of redirecting, set a flag for showing success message
            $update_success = true;
        }
    }
    
    // Handle send quote action
    if (isset($_POST['send_quote'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'edit_quote_' . $quote_id)) {
            // Check if rental price is set
            $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
            
            if (!$quote || empty($quote->rental_price) || $quote->rental_price <= 0) {
                echo '<div class="notice notice-error"><p>Please set a rental price before sending the quote.</p></div>';
            } else {
                // Send the email
                $email_sent = false;
                
                // Try to send email using the function if it exists
                if (function_exists('crqa_send_quote_email')) {
                    crqa_send_quote_email($quote_id);
                    $email_sent = true;
                } else {
                    // Fallback: Try to load and send
                    $email_file = CRQA_PLUGIN_PATH . 'includes/email-settings.php';
                    if (file_exists($email_file)) {
                        require_once $email_file;
                        if (function_exists('crqa_send_quote_email')) {
                            crqa_send_quote_email($quote_id);
                            $email_sent = true;
                        }
                    }
                }
                
                if ($email_sent) {
                    // Update status to quoted if it was pending
                    if ($quote->quote_status == 'pending') {
                        $wpdb->update($table_name, array('quote_status' => 'quoted'), array('id' => $quote_id));
                    }
                    wp_redirect(admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote_id . '&message=sent'));
                    exit;
                } else {
                    echo '<div class="notice notice-error"><p>Email function not available. Please check email settings module.</p></div>';
                }
            }
        }
    }
    
    // Load quote data
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
    
    if (!$quote) {
        wp_die('Quote not found');
    }
    
    // Get activity data
    $activity_summary = crqa_get_activity_summary($quote_id);
    $recent_activities = crqa_get_quote_activity($quote_id);
    
    // Display message if quote was just updated
if (isset($update_success) && $update_success === true) {
    echo '<div class="notice notice-success is-dismissible"><p>Quote updated successfully!</p></div>';
}
    
    // Get currency symbol
    $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    
    // Parse location from vehicle details
    $user_location = '';
    if (!empty($quote->vehicle_details)) {
        if (preg_match('/Location:\s*(.+)/', $quote->vehicle_details, $matches)) {
            $user_location = trim($matches[1]);
        }
    }
    
    // Get pre-paid miles from product
    $prepaid_miles_from_product = '';
    if (!empty($quote->product_id)) {
        $prepaid_miles_terms = wp_get_post_terms($quote->product_id, 'pa_pre_paid_miles');
        if (!is_wp_error($prepaid_miles_terms) && !empty($prepaid_miles_terms)) {
            $prepaid_miles_from_product = $prepaid_miles_terms[0]->name;
        } else {
            $prepaid_miles_from_product = crqa_get_product_attribute($quote->product_id, 'pre_paid_miles');
        }
    }
    
    // Get car subtitle from product
    $car_subtitle = '';
    if (!empty($quote->product_id)) {
        $subtitle_terms = wp_get_post_terms($quote->product_id, 'pa_car_subtitle');
        if (!is_wp_error($subtitle_terms) && !empty($subtitle_terms)) {
            $car_subtitle = $subtitle_terms[0]->name;
        }
    }
    
    // Calculate pricing
    $calculated_rental_price = $quote->rental_price;
    $price_per_day = 0;
    $rental_days = 1;
    
    if (!empty($quote->product_id)) {
        $price_per_day = crqa_get_price_per_day($quote->product_id);
        
        if ($price_per_day > 0 && !empty($quote->rental_dates)) {
            $rental_days = crqa_calculate_rental_days($quote->rental_dates);
            $suggested_price = $price_per_day * $rental_days;
            
            if (empty($quote->rental_price) || $quote->rental_price == 0) {
                $calculated_rental_price = $suggested_price;
            }
        }
    }
    
// Get deposit from product
$product_deposit = 5000; // Default
if (!empty($quote->product_id)) {
    $product_deposit = crqa_get_deposit_amount($quote->product_id);
}

// Set default deposit from existing quote or product
$default_deposit = !empty($quote->deposit_amount) ? $quote->deposit_amount : $product_deposit;    
    // Generate WhatsApp URL
    $phone_number = '';
    $whatsapp_url = '';
    $call_url = '';
    
    if (!empty($quote->customer_phone)) {
    $phone_number = crqa_clean_phone_number($quote->customer_phone);
    
    if ($phone_number) {
        // Generate the quote link
        $quote_link = home_url('/quote/' . $quote->quote_hash);
        
        // Updated WhatsApp message to include the quote link
        $whatsapp_message = sprintf(
            "Hello %s, thank you for your quote request #%s for %s. You can view your quote here: %s",
            $quote->customer_name,
            str_pad($quote_id, 5, '0', STR_PAD_LEFT),
            $quote->vehicle_name,
            $quote_link
        );
        $whatsapp_url = 'https://wa.me/' . $phone_number . '?text=' . urlencode($whatsapp_message);
        $call_url = 'tel:+' . $phone_number;
    }
}
    
    ?>
    <div class="wrap crqa-wrap">
        <div class="crqa-edit-quote">
            <div class="crqa-edit-header">
                <h1>
                    <i class="fas fa-edit"></i> Edit Quote #<?php echo str_pad($quote->id, 5, '0', STR_PAD_LEFT); ?>
                </h1>
                <div class="crqa-header-actions">
                    <?php if (!empty($quote->customer_phone) && $phone_number): ?>
                        <a href="<?php echo esc_url($call_url); ?>" class="crqa-header-btn crqa-call-btn">
                            <i class="fas fa-phone"></i> Call Customer
                        </a>
                        <a href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" class="crqa-header-btn crqa-whatsapp-btn">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('edit_quote_' . $quote_id); ?>
                
                <!-- Customer Activity Section - NEW -->
                <div class="crqa-activity-section">
                    <h3><i class="fas fa-chart-line"></i> Customer Activity</h3>
                    <div class="crqa-activity-summary">
                        <div class="activity-stat">
                            <div class="stat-icon"><i class="fas fa-eye"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $activity_summary['total_views']; ?></div>
                                <div class="stat-label">Total Views</div>
                                <?php if ($activity_summary['unique_views'] > 0): ?>
                                    <div class="stat-sublabel"><?php echo $activity_summary['unique_views']; ?> unique</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="activity-stat">
                            <div class="stat-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-content">
                                <div class="stat-value">
                                    <?php 
                                    if ($activity_summary['last_view']) {
                                        echo human_time_diff(strtotime($activity_summary['last_view']), current_time('timestamp')) . ' ago';
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </div>
                                <div class="stat-label">Last Viewed</div>
                                <?php if ($activity_summary['first_view']): ?>
                                    <div class="stat-sublabel">First: <?php echo date('M j, g:i a', strtotime($activity_summary['first_view'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="activity-stat">
                            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $activity_summary['checkout_attempts']; ?></div>
                                <div class="stat-label">Checkout Attempts</div>
                                <?php if ($activity_summary['last_checkout_attempt']): ?>
                                    <div class="stat-sublabel">Last: <?php echo human_time_diff(strtotime($activity_summary['last_checkout_attempt']), current_time('timestamp')) . ' ago'; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="activity-stat">
                            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-content">
                                <div class="stat-value <?php echo $activity_summary['payment_completed'] ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo $activity_summary['payment_completed'] ? 'Yes' : 'No'; ?>
                                </div>
                                <div class="stat-label">Payment Completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($recent_activities)): ?>
                    <div class="crqa-activity-timeline">
                        <h4>Recent Activity</h4>
                        <div class="timeline-container">
                            <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon">
                                        <?php
                                        switch($activity->activity_type) {
                                            case 'quote_viewed':
                                                echo '<i class="fas fa-eye"></i>';
                                                break;
                                            case 'checkout_started':
                                                echo '<i class="fas fa-shopping-cart"></i>';
                                                break;
                                            case 'payment_completed':
                                                echo '<i class="fas fa-check-circle"></i>';
                                                break;
                                            default:
                                                echo '<i class="fas fa-info-circle"></i>';
                                        }
                                        ?>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title">
                                            <?php
                                            switch($activity->activity_type) {
                                                case 'quote_viewed':
                                                    echo 'Quote viewed';
                                                    break;
                                                case 'checkout_started':
                                                    echo 'Started checkout';
                                                    break;
                                                case 'payment_completed':
                                                    echo 'Completed payment';
                                                    break;
                                                default:
                                                    echo ucwords(str_replace('_', ' ', $activity->activity_type));
                                            }
                                            ?>
                                        </div>
                                        <div class="timeline-meta">
                                            <span class="timeline-time"><?php echo date('M j, g:i a', strtotime($activity->activity_time)); ?></span>
                                            <?php if ($activity->ip_address): ?>
                                                <span class="timeline-ip">IP: <?php echo esc_html($activity->ip_address); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="crqa-form-grid">
                    <!-- Updated Customer Information Card for quotes-edit.php -->
<div class="crqa-form-card">
    <h3><i class="fas fa-user"></i> Customer Information</h3>
    <div class="crqa-form-field">
        <label>Full Name</label>
        <input type="text" name="customer_name" value="<?php echo esc_attr($quote->customer_name); ?>" readonly required>
    </div>
    <div class="crqa-form-field">
        <label>Email Address</label>
        <input type="email" name="customer_email" value="<?php echo esc_attr($quote->customer_email); ?>" readonly required>
    </div>
    <div class="crqa-form-field">
        <label>Phone Number</label>
        <input type="text" name="customer_phone" value="<?php echo esc_attr($quote->customer_phone); ?>" readonly placeholder="Optional">
    </div>
    <div class="crqa-form-field">
        <label>Customer Age</label>
        <input type="text" name="customer_age" value="<?php echo esc_attr($quote->customer_age ?? ''); ?>" readonly placeholder="Not provided">
    </div>
    <div class="crqa-form-field">
        <label>Contact Preference</label>
        <input type="text" name="contact_preference" value="<?php echo esc_attr($quote->contact_preference ?? ''); ?>" readonly placeholder="Not specified">
    </div>
</div>
                    
                    <!-- Vehicle & Rental Card -->
                    <div class="crqa-form-card">
                        <h3><i class="fas fa-car"></i> Vehicle & Rental</h3>
                        <div class="crqa-form-field">
                            <label>Vehicle Name/Type</label>
                            <div class="crqa-vehicle-field">
                                <?php 
                                $full_vehicle_name = $quote->vehicle_name;
                                if ($car_subtitle) {
                                    $full_vehicle_name .= ' - ' . $car_subtitle;
                                }
                                ?>
                                <input type="text" name="vehicle_display" value="<?php echo esc_attr($full_vehicle_name); ?>" readonly>
                                <input type="hidden" name="vehicle_name" value="<?php echo esc_attr($quote->vehicle_name); ?>">
                                
                                <?php if (!empty($quote->product_id) && get_post($quote->product_id)): ?>
                                    <a href="<?php echo get_permalink($quote->product_id); ?>" target="_blank" class="button button-secondary" title="View Car Page">
                                        <i class="fas fa-external-link-alt"></i> View Car
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="crqa-form-field">
                            <label>Rental Period</label>
                            <input type="text" name="rental_dates" value="<?php echo esc_attr($quote->rental_dates); ?>" readonly placeholder="e.g., January 1-7, 2025">
                        </div>
                        <div class="crqa-form-field">
                            <label>Pre-paid Miles</label>
                            <?php if ($prepaid_miles_from_product): ?>
                                <div class="product-attribute-display" style="padding: 10px; background: #f0f0f1; border-radius: 4px; border: 1px solid #ddd;">
                                    <strong><?php echo esc_html($prepaid_miles_from_product); ?></strong>
                                    <input type="hidden" name="mileage_allowance" value="<?php echo esc_attr($prepaid_miles_from_product); ?>">
                                </div>
                            <?php else: ?>
                                <input type="text" name="mileage_allowance" value="<?php echo esc_attr($quote->mileage_allowance); ?>" placeholder="Enter mileage allowance" class="regular-text">
                                <p class="description">No product linked or pre_paid_miles attribute not set</p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($user_location): ?>
                        <div class="crqa-location-info">
                            <div class="crqa-info-grid">
                                <div>
                                    <strong><i class="fas fa-map-marker-alt"></i> Location:</strong>
                                    <span class="country-flag">
                                        <?php echo esc_html($user_location); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pricing Card -->
                    <div class="crqa-form-card crqa-full-width">
                        <h3><i class="fas fa-pound-sign"></i> Pricing</h3>
                        
                        <?php if ($price_per_day > 0): ?>
                        <div class="pricing-info" style="background: #e7f3ff; border: 1px solid #2196f3; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                            <h4 style="margin: 0 0 10px 0; color: #1976d2;"><i class="fas fa-calculator"></i> Auto-Calculate Pricing</h4>
                            <p style="margin: 5px 0;"><strong>Price per day:</strong> <?php echo crqa_format_price($price_per_day); ?></p>
                            <?php if ($rental_days > 1): ?>
                                <p style="margin: 5px 0;"><strong>Rental duration:</strong> <?php echo $rental_days; ?> days</p>
                                <p style="margin: 5px 0;"><strong>Suggested total:</strong> <?php echo crqa_format_price($price_per_day * $rental_days); ?></p>
                            <?php endif; ?>
                            <?php if ($product_deposit && $product_deposit != 5000): ?>
    <p style="margin: 5px 0;"><strong>Product deposit:</strong> <?php echo crqa_format_price($product_deposit); ?></p>
<?php endif; ?>
                            <button type="button" id="auto-calculate-price" class="button button-small" style="margin-top: 10px;">
                                <i class="fas fa-magic"></i> Auto-Fill Price
                            </button>
                        </div>
                        
                        <script>
                        document.getElementById('auto-calculate-price').addEventListener('click', function() {
                            var pricePerDay = <?php echo $price_per_day; ?>;
                            var rentalDays = <?php echo $rental_days; ?>;
                            var suggestedPrice = pricePerDay * rentalDays;
                            
                            if (confirm('Auto-fill rental price with ' + suggestedPrice.toFixed(0) + '?')) {
                                document.querySelector('input[name="rental_price"]').value = suggestedPrice.toFixed(0);
                                updateTotal();
                            }
                        });
                        </script>
                        <?php endif; ?>
                        
                        <div class="crqa-price-grid">
                            <div class="crqa-form-field">
                                <label>Rental Price (<?php echo esc_html($currency_symbol); ?>)</label>
                                <input type="number" name="rental_price" value="<?php echo esc_attr(intval($calculated_rental_price)); ?>" step="1" placeholder="0" oninput="updateTotal()">
                            </div>
                            <div class="crqa-form-field">
                                <label>Security Deposit (<?php echo esc_html($currency_symbol); ?>)</label>
                                <input type="number" name="deposit_amount" value="<?php echo esc_attr(intval($default_deposit)); ?>" step="1" placeholder="5000" oninput="updateTotal()">
                            </div>
                        </div>
                        
                        <div class="crqa-total-display" id="total-display">
                            <span class="crqa-total-label">Total Amount:</span>
                            <span class="crqa-total-amount" id="total-amount">
                                <?php 
                                $total = intval($calculated_rental_price ?: 0) + intval($default_deposit ?: 0);
                                echo esc_html($currency_symbol) . number_format($total, 0); 
                                ?>
                            </span>
                        </div>
                        
                        <script>
                        function updateTotal() {
                            var rentalPrice = parseInt(document.querySelector('input[name="rental_price"]').value) || 0;
                            var depositAmount = parseInt(document.querySelector('input[name="deposit_amount"]').value) || 0;
                            var total = rentalPrice + depositAmount;
                            
                            document.getElementById('total-amount').textContent = '<?php echo esc_js($currency_symbol); ?>' + total.toLocaleString();
                        }
                        </script>
                    </div>
                </div>
                
                <!-- Submit Section - MOVED HERE AFTER PRICING -->
                <div class="crqa-submit-section">
                    <div>
                        <div class="meta-info">
                            <i class="fas fa-clock"></i> <strong>Created:</strong> <?php echo date('F j, Y g:i a', strtotime($quote->created_at)); ?>
                        </div>
                        <div class="button-group">
                            <input type="submit" name="submit" class="button button-primary button-large" value="Update Quote">
                            <a href="<?php echo admin_url('admin.php?page=car-rental-quotes'); ?>" class="button button-large">Cancel</a>
                        </div>
                    </div>
                </div>
                
                <!-- Status Section -->
                <div class="crqa-status-section">
                    <div class="crqa-status-grid">
                        <div class="crqa-status-select-wrapper">
                            <select name="quote_status" class="regular-text quote-status-select" style="font-size: 16px; padding: 10px;">
                                <option value="pending" <?php selected($quote->quote_status, 'pending'); ?>>Pending</option>
                                <option value="quoted" <?php selected($quote->quote_status, 'quoted'); ?>>Quoted</option>
                                <option value="paid" <?php selected($quote->quote_status, 'paid'); ?>>Paid</option>
                            </select>
                        </div>
                        <div class="crqa-action-buttons">
                            <a href="<?php echo home_url('/quote/' . $quote->quote_hash); ?>" target="_blank" class="crqa-view-quote-btn">
                                <i class="fas fa-eye"></i> View Quote as Customer
                            </a>
                            <?php if ($quote->quote_status == 'pending'): ?>
                                <button type="submit" name="send_quote" class="button button-primary crqa-send-quote-btn">
                                    <i class="fas fa-paper-plane"></i> Send Quote to Customer
                                </button>
                            <?php else: ?>
                                <button type="submit" name="send_quote" class="button crqa-resend-quote-btn">
                                    <i class="fas fa-redo"></i> Resend Quote
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden fields -->
                <?php
                $cleaned_vehicle_details = '';
                if (!empty($quote->vehicle_details)) {
                    if (preg_match('/Location:\s*(.+)/', $quote->vehicle_details, $matches)) {
                        $cleaned_vehicle_details = 'Location: ' . trim($matches[1]);
                    }
                }
                ?>
                <input type="hidden" name="vehicle_details" value="<?php echo esc_attr($cleaned_vehicle_details); ?>">
                <input type="hidden" name="delivery_option" value="<?php echo esc_attr($quote->delivery_option); ?>">
                <input type="hidden" name="additional_notes" value="<?php echo esc_attr($quote->additional_notes); ?>">
                <input type="hidden" name="product_id" value="<?php echo esc_attr($quote->product_id); ?>">
            </form>
        </div>
    </div>
    
    <style>
    /* Add Font Awesome if not loaded */
    @import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
    
    /* Activity Section Styles */
    .crqa-activity-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .crqa-activity-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #1d2327;
    }
    
    .crqa-activity-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .activity-stat {
        display: flex;
        align-items: center;
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        transition: transform 0.2s;
    }
    
    .activity-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .stat-icon {
        font-size: 32px;
        margin-right: 15px;
        color: #2271b1;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 600;
        color: #1d2327;
        line-height: 1;
    }
    
    .stat-value.status-completed {
        color: #46b450;
    }
    
    .stat-value.status-pending {
        color: #999;
    }
    
    .stat-label {
        font-size: 14px;
        color: #666;
        margin-top: 5px;
    }
    
    .stat-sublabel {
        font-size: 12px;
        color: #999;
        margin-top: 3px;
    }
    
    /* Timeline Styles */
    .crqa-activity-timeline {
        margin-top: 30px;
    }
    
    .crqa-activity-timeline h4 {
        margin-bottom: 15px;
        color: #1d2327;
    }
    
    .timeline-container {
        position: relative;
        padding-left: 40px;
    }
    
    .timeline-container::before {
        content: '';
        position: absolute;
        left: 14px;
        top: 20px;
        bottom: 0;
        width: 2px;
        background: #e0e0e0;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
        display: flex;
        align-items: start;
    }
    
    .timeline-icon {
        position: absolute;
        left: -40px;
        width: 30px;
        height: 30px;
        background: #fff;
        border: 2px solid #2271b1;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: #2271b1;
    }
    
    .timeline-content {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 12px 15px;
        flex: 1;
    }
    
    .timeline-title {
        font-weight: 600;
        color: #1d2327;
        margin-bottom: 5px;
    }
    
    .timeline-meta {
        font-size: 12px;
        color: #666;
    }
    
    .timeline-meta span {
        margin-right: 15px;
    }
    
    .timeline-ip {
        color: #999;
    }
    
    @media (max-width: 768px) {
        .crqa-activity-summary {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
}

/**
 * Update quote
 */
function crqa_update_quote($quote_id) {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'edit_quote_' . $quote_id)) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $data = array(
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'customer_phone' => sanitize_text_field($_POST['customer_phone']),
        'vehicle_name' => sanitize_text_field($_POST['vehicle_name']),
        'vehicle_details' => sanitize_textarea_field($_POST['vehicle_details']),
        'rental_dates' => sanitize_text_field($_POST['rental_dates']),
        'rental_price' => intval($_POST['rental_price']), // No decimals
        'deposit_amount' => intval($_POST['deposit_amount']), // No decimals
        'mileage_allowance' => sanitize_text_field($_POST['mileage_allowance']),
        'delivery_option' => sanitize_text_field($_POST['delivery_option']),
        'additional_notes' => sanitize_textarea_field($_POST['additional_notes']),
        'quote_status' => sanitize_text_field($_POST['quote_status']),
        'product_id' => !empty($_POST['product_id']) ? intval($_POST['product_id']) : null
    );
    
    $wpdb->update($table_name, $data, array('id' => $quote_id));
}

// Add debug logging
add_action('init', function() {
    if (isset($_GET['debug_crqa']) && current_user_can('manage_options')) {
        error_log('CRQA Debug: quotes-edit.php loaded');
        error_log('CRQA Debug: crqa_send_quote_email exists: ' . (function_exists('crqa_send_quote_email') ? 'yes' : 'no'));
        error_log('CRQA Debug: POST data: ' . print_r($_POST, true));
    }
});
?>