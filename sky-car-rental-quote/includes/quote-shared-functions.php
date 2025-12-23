<?php
/**
 * Shared Functions for Quote Management
 * 
 * This file contains functions used by both quotes-list.php and quotes-edit.php
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/quote-shared-functions.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get formatted price with WooCommerce currency
 */
function crqa_format_price($price) {
    if (function_exists('wc_price')) {
        return strip_tags(wc_price($price));
    } else if (function_exists('get_woocommerce_currency_symbol')) {
        $currency_pos = get_option('woocommerce_currency_pos', 'left');
        $symbol = get_woocommerce_currency_symbol();
        $formatted = number_format($price, 2);
        
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
        // Fallback if WooCommerce is not active
        return '$' . number_format($price, 2);
    }
}

/**
 * Get vehicle display with product subtitle from taxonomy attributes - UPDATED to link to frontend
 */
function crqa_get_vehicle_display($quote) {
    $vehicle_name = esc_html($quote->vehicle_name);
    $output = $vehicle_name;
    
    // If we have a linked product, try to get the car_subtitle taxonomy attribute
    if (!empty($quote->product_id)) {
        $product = wc_get_product($quote->product_id);
        
        if ($product) {
            // Get car_subtitle from product taxonomy attribute (pa_car_subtitle)
            $subtitle_terms = wp_get_post_terms($quote->product_id, 'pa_car_subtitle');
            
            if (!is_wp_error($subtitle_terms) && !empty($subtitle_terms)) {
                $subtitle = $subtitle_terms[0]->name; // Get the first term name
                $output .= ' - ' . esc_html($subtitle);
            }
            
            // Add product link to FRONTEND page instead of backend
            $product_url = get_permalink($quote->product_id); // Frontend product page
            $title_text = 'View Product: ' . $product->get_name();
            $output = '<a href="' . esc_url($product_url) . '" target="_blank" title="' . esc_attr($title_text) . '" style="text-decoration: none;">' . $output . ' <i class="fas fa-external-link-alt" style="font-size: 12px; color: #666; margin-left: 5px;"></i></a>';
        }
    } else {
        // No product linked - show a warning for admin
        $output .= ' <span style="color: #d63638; font-size: 11px;">(No Product Linked)</span>';
    }
    
    return $output;
}

/**
 * Get price per day from product attributes
 */
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
        // Remove any currency symbols or text, extract numeric value
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

/**
 * Get deposit amount from product attributes
 */
function crqa_get_deposit_amount($product_id) {
    if (!$product_id || !function_exists('wc_get_product')) {
        return 5000; // Default deposit
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        return 5000;
    }
    
    // Try to get deposit from taxonomy attribute
    $deposit_terms = wp_get_post_terms($product_id, 'pa_deposit');
    if (!is_wp_error($deposit_terms) && !empty($deposit_terms)) {
        $deposit_value = $deposit_terms[0]->name;
        // Remove any currency symbols or text, extract numeric value
        $deposit_numeric = preg_replace('/[^0-9.]/', '', $deposit_value);
        return floatval($deposit_numeric);
    }
    
    // Fallback to custom meta field
    $deposit_meta = get_post_meta($product_id, 'deposit', true);
    if ($deposit_meta) {
        $deposit_numeric = preg_replace('/[^0-9.]/', '', $deposit_meta);
        return floatval($deposit_numeric);
    }
    
    return 5000; // Default fallback
}

/**
 * Calculate rental duration in days from rental dates string
 */
function crqa_calculate_rental_days($rental_dates) {
    if (empty($rental_dates)) {
        return 1; // Default to 1 day
    }
    
    error_log('CRQA: Calculating rental days for: ' . $rental_dates);
    
    // Pattern 1: DD/MM/YYYY to DD/MM/YYYY format (UK format)
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*(?:to|[-–])\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $rental_dates, $matches)) {
        // Create DateTime objects for accurate calculation
        $start_date = DateTime::createFromFormat('d/m/Y', $matches[1] . '/' . $matches[2] . '/' . $matches[3]);
        $end_date = DateTime::createFromFormat('d/m/Y', $matches[4] . '/' . $matches[5] . '/' . $matches[6]);
        
        if ($start_date && $end_date) {
            $interval = $start_date->diff($end_date);
            $days = $interval->days; // Changed: removed + 1 to exclude return day
            error_log('CRQA: Calculated ' . $days . ' days from ' . $start_date->format('Y-m-d') . ' to ' . $end_date->format('Y-m-d'));
            return max(1, $days);
        }
    }
    
    // Pattern 2: MM/DD/YYYY to MM/DD/YYYY format (US format fallback)
    if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*(?:to|[-–])\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $rental_dates, $matches)) {
        // Try US format if UK format didn't work
        $start_date = DateTime::createFromFormat('m/d/Y', $matches[1] . '/' . $matches[2] . '/' . $matches[3]);
        $end_date = DateTime::createFromFormat('m/d/Y', $matches[4] . '/' . $matches[5] . '/' . $matches[6]);
        
        if ($start_date && $end_date) {
            $interval = $start_date->diff($end_date);
            $days = $interval->days + 1; // Add 1 to include both start and end dates
            error_log('CRQA: Calculated ' . $days . ' days (US format) from ' . $start_date->format('Y-m-d') . ' to ' . $end_date->format('Y-m-d'));
            return max(1, $days);
        }
    }
    
    // Pattern 3: "Month DD-DD, YYYY"
    if (preg_match('/(\w+)\s+(\d+)-(\d+),\s*(\d{4})/', $rental_dates, $matches)) {
        $start_day = intval($matches[2]);
        $end_day = intval($matches[3]);
        $days = $end_day - $start_day; // Changed: removed + 1 to exclude return day
        error_log('CRQA: Calculated ' . $days . ' days from month format');
        return max(1, $days);
    }
    
    // Pattern 4: YYYY-MM-DD to YYYY-MM-DD (ISO format)
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})\s*(?:to|[-–])\s*(\d{4})-(\d{2})-(\d{2})/', $rental_dates, $matches)) {
        $start_date = DateTime::createFromFormat('Y-m-d', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
        $end_date = DateTime::createFromFormat('Y-m-d', $matches[4] . '-' . $matches[5] . '-' . $matches[6]);
        
        if ($start_date && $end_date) {
            $interval = $start_date->diff($end_date);
            $days = $interval->days + 1; // Add 1 to include both start and end dates
            error_log('CRQA: Calculated ' . $days . ' days from ISO format');
            return max(1, $days);
        }
    }
    
    // Pattern 5: Look for explicit day count in text
    if (preg_match('/(\d+)\s*(?:days?|nights?)/', strtolower($rental_dates), $matches)) {
        $days = intval($matches[1]);
        error_log('CRQA: Found explicit day count: ' . $days);
        return max(1, $days);
    }
    
    // Default to 1 day if we can't determine duration
    error_log('CRQA: Could not parse rental dates, defaulting to 1 day');
    return 1;
}

/**
 * Clean and format phone number for international use
 * Note: This function is also declared in email-settings.php, so check if it exists
 */
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

/**
 * Get status badge HTML
 */
function crqa_get_status_badge($status) {
    $badges = array(
        'pending' => '<span class="status-badge status-pending">Pending</span>',
        'quoted' => '<span class="status-badge status-quoted">Quoted</span>',
        'paid' => '<span class="status-badge status-paid">Paid</span>'
    );
    
    return isset($badges[$status]) ? $badges[$status] : $status;
}

/**
 * Get quote statistics
 */
function crqa_get_quote_statistics() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $stats = array();
    
    // Total quotes
    $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    // By status
    $stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE quote_status = 'pending'");
    $stats['quoted'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE quote_status = 'quoted'");
    $stats['paid'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE quote_status = 'paid'");
    
    // Revenue
    $stats['total_quoted'] = $wpdb->get_var("SELECT SUM(rental_price + deposit_amount) FROM $table_name WHERE quote_status IN ('quoted', 'paid')") ?: 0;
    $stats['total_paid'] = $wpdb->get_var("SELECT SUM(rental_price + deposit_amount) FROM $table_name WHERE quote_status = 'paid'") ?: 0;
    
    // Conversion rate
    $stats['conversion_rate'] = $stats['quoted'] > 0 ? round(($stats['paid'] / $stats['quoted']) * 100, 1) : 0;
    
    // Average quote value
    $stats['avg_quote_value'] = $stats['quoted'] > 0 ? round($stats['total_quoted'] / $stats['quoted'], 2) : 0;
    
    return $stats;
}

/**
 * Enqueue admin styles and scripts
 */
if (!has_action('admin_enqueue_scripts', 'crqa_enqueue_admin_assets')) {
    add_action('admin_enqueue_scripts', 'crqa_enqueue_admin_assets');
    function crqa_enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'car-rental-quotes') === false) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'crqa-admin-styles',
            CRQA_PLUGIN_URL . 'assets/css/quotes-admin.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue custom CSS
        wp_enqueue_style(
            'crqa-admin-custom-styles',
            CRQA_PLUGIN_URL . 'assets/css/quotes-admin-custom.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
            array(),
            '6.0.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'crqa-admin-scripts',
            CRQA_PLUGIN_URL . 'assets/js/quotes-admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }
}