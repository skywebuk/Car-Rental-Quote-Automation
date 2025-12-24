<?php
/**
 * Shortcodes Module for Car Rental Quote Automation - NO DECIMALS VERSION
 * 
 * This file handles all shortcode registrations with whole number price displays
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/shortcodes.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all shortcodes
 */
add_action('init', 'crqa_register_shortcodes');
function crqa_register_shortcodes() {
    // Data shortcodes
    add_shortcode('crqa_quote_id', 'crqa_shortcode_quote_id');
    add_shortcode('crqa_customer_name', 'crqa_shortcode_customer_name');
    add_shortcode('crqa_customer_email', 'crqa_shortcode_customer_email');
    add_shortcode('crqa_customer_phone', 'crqa_shortcode_customer_phone');
    add_shortcode('crqa_vehicle_name', 'crqa_shortcode_vehicle_name_enhanced');
    add_shortcode('crqa_vehicle_details', 'crqa_shortcode_vehicle_details');
    add_shortcode('crqa_vehicle_subtitle', 'crqa_shortcode_vehicle_subtitle');
    add_shortcode('crqa_vehicle_attribute', 'crqa_shortcode_vehicle_attribute');
    add_shortcode('crqa_prepaid_miles', 'crqa_shortcode_prepaid_miles');
    add_shortcode('crqa_debug_attributes', 'crqa_shortcode_debug_attributes');
    add_shortcode('crqa_rental_dates', 'crqa_shortcode_rental_dates');
    
    // Price shortcodes
    add_shortcode('crqa_quote_price', 'crqa_shortcode_quote_price_new');
    add_shortcode('crqa_rental_price', 'crqa_shortcode_quote_price_new'); // Backward compatibility
    
    add_shortcode('crqa_deposit_amount', 'crqa_shortcode_deposit_amount');
    add_shortcode('crqa_total_amount', 'crqa_shortcode_total_amount');
    add_shortcode('crqa_mileage_allowance', 'crqa_shortcode_mileage_allowance');
    add_shortcode('crqa_delivery_option', 'crqa_shortcode_delivery_option');
    add_shortcode('crqa_additional_notes', 'crqa_shortcode_additional_notes');
    add_shortcode('crqa_quote_status', 'crqa_shortcode_quote_status');
    add_shortcode('crqa_quote_date', 'crqa_shortcode_quote_date');
    add_shortcode('crqa_form_type', 'crqa_shortcode_form_type');
    add_shortcode('crqa_customer_age', 'crqa_shortcode_customer_age');
    add_shortcode('crqa_contact_preference', 'crqa_shortcode_contact_preference');
    
    // Action shortcodes
    add_shortcode('crqa_payment_button', 'crqa_shortcode_payment_button');
    add_shortcode('crqa_payment_link', 'crqa_shortcode_payment_link');
    
    // Conditional shortcodes
    add_shortcode('crqa_if_quoted', 'crqa_shortcode_if_quoted');
    add_shortcode('crqa_if_pending', 'crqa_shortcode_if_pending');
    add_shortcode('crqa_if_paid', 'crqa_shortcode_if_paid');
}

/**
 * Helper function to get current quote
 */
if (!function_exists('crqa_get_current_quote')) {
    function crqa_get_current_quote() {
        // Check if quote is already loaded in globals (for emails)
        if (isset($GLOBALS['crqa_current_quote']) && is_object($GLOBALS['crqa_current_quote'])) {
            return $GLOBALS['crqa_current_quote'];
        }
        
        // Try to get quote from URL parameter
        $quote_hash = '';
        if (isset($_GET['quote'])) {
            $quote_hash = sanitize_text_field($_GET['quote']);
        } elseif (get_query_var('quote_hash')) {
            $quote_hash = sanitize_text_field(get_query_var('quote_hash'));
        }
        
        if ($quote_hash) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'car_rental_quotes';
            
            $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE quote_hash = %s", $quote_hash));
            
            if ($quote) {
                $GLOBALS['crqa_current_quote'] = $quote;
                return $quote;
            }
        }
        
        return null;
    }
}

// RENTAL PRICE SHORTCODE - NO DECIMALS
function crqa_shortcode_quote_price_new($atts) {
    // Parse attributes with defaults
    $atts = shortcode_atts(array(
        'format' => 'currency',  // 'currency', 'number', 'raw'
        'decimals' => '0',       // CHANGED TO 0 FOR NO DECIMALS
        'show_zero' => 'no',     
        'default' => 'TBC'       
    ), $atts);
    
    // Get the current quote
    $quote = crqa_get_current_quote();
    
    if (!$quote) {
        return '';
    }
    
    // Get rental price and convert to integer
    $rental_price = 0;
    
    if (isset($quote->rental_price)) {
        $rental_price = intval($quote->rental_price);
    }
    
    // If still zero, try to refresh from database
    if ($rental_price == 0 && isset($quote->id)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'car_rental_quotes';
        
        $fresh_quote = $wpdb->get_row($wpdb->prepare(
            "SELECT rental_price FROM $table_name WHERE id = %d", 
            intval($quote->id)
        ));
        
        if ($fresh_quote && isset($fresh_quote->rental_price)) {
            $rental_price = intval($fresh_quote->rental_price);
        }
    }
    
    // Handle zero values
    if ($rental_price == 0) {
        if ($atts['show_zero'] === 'yes') {
            if ($atts['format'] === 'currency') {
                return crqa_format_currency_value(0, 0);
            } else {
                return '0';
            }
        } else {
            return esc_html($atts['default']);
        }
    }
    
    // Format the price based on the format attribute
    switch ($atts['format']) {
        case 'currency':
            return crqa_format_currency_value($rental_price, 0); // No decimals
            
        case 'number':
            return number_format($rental_price, 0); // No decimals
            
        case 'raw':
            return strval($rental_price);
            
        default:
            return crqa_format_currency_value($rental_price, 0); // No decimals
    }
}

/**
 * Helper function to format currency values - NO DECIMALS VERSION
 */
function crqa_format_currency_value($amount, $decimals = 0) {
    // Get currency symbol - check WooCommerce first, then plugin setting, then default
    if (function_exists('get_woocommerce_currency_symbol')) {
        $currency_symbol = get_woocommerce_currency_symbol();
    } else {
        // Use plugin setting if available, otherwise default to pound (UK-focused plugin)
        $currency_symbol = get_option('crqa_currency_symbol', '£');
    }

    // Allow filtering of currency symbol for customization
    $currency_symbol = apply_filters('crqa_currency_symbol', $currency_symbol);

    // Get currency position from WooCommerce if available
    $currency_pos = 'left';
    if (function_exists('get_option')) {
        $currency_pos = get_option('woocommerce_currency_pos', 'left');
    }
    
    // Format the number as integer (no decimals)
    $formatted_amount = number_format(intval($amount), 0);
    
    // Apply currency position
    switch ($currency_pos) {
        case 'right':
            return $formatted_amount . $currency_symbol;
        case 'right_space':
            return $formatted_amount . ' ' . $currency_symbol;
        case 'left_space':
            return $currency_symbol . ' ' . $formatted_amount;
        default: // 'left'
            return $currency_symbol . $formatted_amount;
    }
}

// DEPOSIT AMOUNT SHORTCODE - NO DECIMALS
function crqa_shortcode_deposit_amount($atts) {
    $atts = shortcode_atts(array(
        'format' => 'currency',
        'decimals' => '0',  // No decimals
        'show_zero' => 'no',
        'default' => 'TBC'
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote) return '';
    
    $deposit_amount = intval($quote->deposit_amount ?: 0);
    
    if ($deposit_amount == 0 && $atts['show_zero'] === 'no') {
        return esc_html($atts['default']);
    }
    
    if ($atts['format'] === 'currency') {
        return crqa_format_currency_value($deposit_amount, 0);
    } elseif ($atts['format'] === 'number') {
        return number_format($deposit_amount, 0);
    } else {
        return strval($deposit_amount);
    }
}

// TOTAL AMOUNT SHORTCODE - NO DECIMALS
function crqa_shortcode_total_amount($atts) {
    $atts = shortcode_atts(array(
        'format' => 'currency',
        'decimals' => '0',  // No decimals
        'show_zero' => 'no',
        'default' => 'TBC'
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote) return '';

    // Use round() before intval() to avoid truncation errors
    // e.g., 99.7 should become 100, not 99
    $rental_price = intval(round(floatval($quote->rental_price ?: 0)));
    $deposit_amount = intval(round(floatval($quote->deposit_amount ?: 0)));
    $total = $rental_price + $deposit_amount;
    
    if ($total == 0 && $atts['show_zero'] === 'no') {
        return esc_html($atts['default']);
    }
    
    if ($atts['format'] === 'currency') {
        return crqa_format_currency_value($total, 0);
    } elseif ($atts['format'] === 'number') {
        return number_format($total, 0);
    } else {
        return strval($total);
    }
}

// Keep all other existing shortcode functions unchanged below...

// Shortcode implementations
function crqa_shortcode_quote_id($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? str_pad($quote->id, 5, '0', STR_PAD_LEFT) : '';
}

function crqa_shortcode_customer_name($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? esc_html($quote->customer_name) : '';
}

function crqa_shortcode_customer_email($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? esc_html($quote->customer_email) : '';
}

function crqa_shortcode_customer_phone($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? esc_html($quote->customer_phone) : '';
}

// Customer Age Shortcode
function crqa_shortcode_customer_age($atts) {
    $quote = crqa_get_current_quote();
    return $quote && !empty($quote->customer_age) ? esc_html($quote->customer_age) : '';
}

// Contact Preference Shortcode
function crqa_shortcode_contact_preference($atts) {
    $quote = crqa_get_current_quote();
    return $quote && !empty($quote->contact_preference) ? esc_html($quote->contact_preference) : '';
}

// Enhanced vehicle name shortcode with product attributes
function crqa_shortcode_vehicle_name_enhanced($atts) {
    $atts = shortcode_atts(array(
        'show_subtitle' => 'true',
        'separator' => ' - ',
        'format' => 'inline' // inline, block, detailed
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote) return '';
    
    $vehicle_name = esc_html($quote->vehicle_name);
    $output = $vehicle_name;
    
    if ($atts['show_subtitle'] === 'true' && !empty($quote->product_id) && function_exists('crqa_get_product_attribute')) {
        $subtitle = crqa_get_product_attribute($quote->product_id, 'car_subtitle');
        
        if ($subtitle) {
            if ($atts['format'] === 'block') {
                $output .= '<br><small class="vehicle-subtitle">' . esc_html($subtitle) . '</small>';
            } else {
                $output .= esc_html($atts['separator']) . '<span class="vehicle-subtitle">' . esc_html($subtitle) . '</span>';
            }
        }
    }
    
    return $output;
}

// Vehicle subtitle shortcode
function crqa_shortcode_vehicle_subtitle($atts) {
    $atts = shortcode_atts(array(
        'wrapper' => 'span',
        'class' => 'vehicle-subtitle'
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote || empty($quote->product_id)) return '';
    
    if (function_exists('crqa_get_product_attribute')) {
        $subtitle = crqa_get_product_attribute($quote->product_id, 'car_subtitle');
        
        if ($subtitle) {
            return '<' . esc_attr($atts['wrapper']) . ' class="' . esc_attr($atts['class']) . '">' . esc_html($subtitle) . '</' . esc_attr($atts['wrapper']) . '>';
        }
    }
    
    return '';
}

// Generic vehicle attribute shortcode
function crqa_shortcode_vehicle_attribute($atts) {
    $atts = shortcode_atts(array(
        'field' => 'car_subtitle',
        'wrapper' => 'span',
        'class' => 'vehicle-attribute',
        'default' => ''
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote || empty($quote->product_id)) return esc_html($atts['default']);
    
    if (function_exists('crqa_get_product_attribute')) {
        $attribute_value = crqa_get_product_attribute($quote->product_id, $atts['field']);
        
        if ($attribute_value) {
            return '<' . esc_attr($atts['wrapper']) . ' class="' . esc_attr($atts['class']) . '">' . esc_html($attribute_value) . '</' . esc_attr($atts['wrapper']) . '>';
        }
    }
    
    return esc_html($atts['default']);
}

// Prepaid miles shortcode
function crqa_shortcode_prepaid_miles($atts) {
    $atts = shortcode_atts(array(
        'wrapper' => 'span',
        'class' => 'prepaid-miles',
        'default' => 'Not specified'
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote) return esc_html($atts['default']);
    
    if (!empty($quote->mileage_allowance)) {
        return '<' . esc_attr($atts['wrapper']) . ' class="' . esc_attr($atts['class']) . '">' . esc_html($quote->mileage_allowance) . '</' . esc_attr($atts['wrapper']) . '>';
    }
    
    if (!empty($quote->product_id) && function_exists('crqa_get_product_attribute')) {
        $prepaid_miles = crqa_get_product_attribute($quote->product_id, 'pre_paid_miles');
        if ($prepaid_miles) {
            return '<' . esc_attr($atts['wrapper']) . ' class="' . esc_attr($atts['class']) . '">' . esc_html($prepaid_miles) . '</' . esc_attr($atts['wrapper']) . '>';
        }
    }
    
    return esc_html($atts['default']);
}

// Debug attributes shortcode
function crqa_shortcode_debug_attributes($atts) {
    $quote = crqa_get_current_quote();
    if (!$quote || empty($quote->product_id)) {
        return '<p>No product linked to this quote.</p>';
    }
    
    if (!function_exists('wc_get_product')) {
        return '<p>WooCommerce not active.</p>';
    }
    
    $product = wc_get_product($quote->product_id);
    if (!$product) {
        return '<p>Product not found.</p>';
    }
    
    $output = '<div style="background: #f0f0f0; padding: 15px; border: 1px solid #ccc; margin: 10px 0;">';
    $output .= '<h4>Debug: Product Attributes for ' . esc_html($product->get_name()) . '</h4>';
    
    $attributes = $product->get_attributes();
    
    if (empty($attributes)) {
        $output .= '<p>No attributes found.</p>';
    } else {
        $output .= '<ul>';
        foreach ($attributes as $attribute_key => $attribute) {
            $output .= '<li><strong>' . esc_html($attribute_key) . ':</strong> ';
            
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                $values = array();
                foreach ($terms as $term) {
                    $values[] = $term->name;
                }
                $output .= esc_html(implode(', ', $values));
            } else {
                $options = $attribute->get_options();
                $value = is_array($options) ? implode(', ', $options) : $options;
                $output .= esc_html($value);
            }
            $output .= '</li>';
        }
        $output .= '</ul>';
    }
    
    $custom_fields = get_post_meta($quote->product_id);
    if (!empty($custom_fields)) {
        $output .= '<h5>Custom Fields:</h5><ul>';
        foreach ($custom_fields as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $output .= '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html(is_array($value) ? implode(', ', $value) : $value) . '</li>';
            }
        }
        $output .= '</ul>';
    }
    
    $output .= '</div>';
    return $output;
}

function crqa_shortcode_vehicle_details($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? nl2br(esc_html($quote->vehicle_details)) : '';
}

function crqa_shortcode_rental_dates($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? esc_html($quote->rental_dates) : '';
}

function crqa_shortcode_mileage_allowance($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? esc_html($quote->mileage_allowance ?: 'Unlimited') : '';
}

function crqa_shortcode_delivery_option($atts) {
    $quote = crqa_get_current_quote();
    if (!$quote) return '';
    
    $delivery_options = array(
        'pickup' => 'Customer Pickup',
        'delivery' => 'We Deliver',
        'both' => 'Pickup or Delivery Available'
    );
    
    return esc_html($delivery_options[$quote->delivery_option] ?? $quote->delivery_option);
}

function crqa_shortcode_additional_notes($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? nl2br(esc_html($quote->additional_notes)) : '';
}

function crqa_shortcode_quote_status($atts) {
    $quote = crqa_get_current_quote();
    return $quote ? ucfirst($quote->quote_status) : '';
}

function crqa_shortcode_quote_date($atts) {
    $atts = shortcode_atts(array(
        'format' => 'F j, Y'
    ), $atts);
    
    $quote = crqa_get_current_quote();
    return $quote ? date($atts['format'], strtotime($quote->created_at)) : '';
}

function crqa_shortcode_form_type($atts) {
    $quote = crqa_get_current_quote();
    if (!$quote) return '';
    
    $form_types = array(
        'standard' => 'Standard Quote',
        'premium' => 'Premium Vehicle Quote',
        'long-term' => 'Long-term Rental Quote',
        'corporate' => 'Corporate Quote',
        'event' => 'Event Rental Quote'
    );
    
    return esc_html($form_types[$quote->form_type] ?? ucfirst($quote->form_type));
}

function crqa_shortcode_payment_button($atts) {
    $atts = shortcode_atts(array(
        'text' => 'Pay for Quote',
        'class' => 'crqa-payment-button'
    ), $atts);
    
    $quote = crqa_get_current_quote();
    if (!$quote || $quote->quote_status != 'quoted') return '';
    
    wp_enqueue_script('jquery');
    
    $button_html = '<button id="crqa-pay-button" class="' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</button>';
    
    $button_html .= '
    <script>
    jQuery(document).ready(function($) {
        $("#crqa-pay-button").on("click", function(e) {
            e.preventDefault();
            
            var button = $(this);
            button.prop("disabled", true).text("Processing...");
            
            $.ajax({
                url: "' . admin_url('admin-ajax.php') . '",
                type: "POST",
                data: {
                    action: "crqa_add_to_cart",
                    quote_id: ' . $quote->id . ',
                    nonce: "' . wp_create_nonce('crqa_quote_' . $quote->id) . '"
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert("Error processing payment. Please try again.");
                        button.prop("disabled", false).text("' . esc_js($atts['text']) . '");
                    }
                },
                error: function() {
                    alert("Error processing payment. Please try again.");
                    button.prop("disabled", false).text("' . esc_js($atts['text']) . '");
                }
            });
        });
    });
    </script>';
    
    return $button_html;
}

function crqa_shortcode_payment_link($atts) {
    $quote = crqa_get_current_quote();
    if (!$quote || $quote->quote_status != 'quoted') return '';
    
    return '#payment-link';
}

// Conditional shortcodes
function crqa_shortcode_if_quoted($atts, $content = null) {
    $quote = crqa_get_current_quote();
    if ($quote && $quote->quote_status == 'quoted') {
        return do_shortcode($content);
    }
    return '';
}

function crqa_shortcode_if_pending($atts, $content = null) {
    $quote = crqa_get_current_quote();
    if ($quote && $quote->quote_status == 'pending') {
        return do_shortcode($content);
    }
    return '';
}

function crqa_shortcode_if_paid($atts, $content = null) {
    $quote = crqa_get_current_quote();
    if ($quote && $quote->quote_status == 'paid') {
        return do_shortcode($content);
    }
    return '';
}