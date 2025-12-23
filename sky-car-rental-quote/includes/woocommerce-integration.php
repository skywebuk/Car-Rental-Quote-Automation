<?php
/**
 * WooCommerce Integration Module for Car Rental Quote Automation
 * 
 * This file handles all WooCommerce-related functionality including cart, checkout, and product creation
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/woocommerce-integration.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create rental product on activation
 */
function crqa_create_rental_product() {
    // Check if WooCommerce is active
    if (!class_exists('WC_Product_Simple')) {
        return;
    }
    
    // Check if product already exists
    $product_id = get_option('crqa_rental_product_id');
    
    if ($product_id && get_post($product_id)) {
        return; // Product already exists
    }
    
    // Create new product
    $product = new WC_Product_Simple();
    $product->set_name('Car Rental Booking');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden'); // Hide from shop
    $product->set_price(0); // Default price
    $product->set_regular_price(0);
    $product->set_manage_stock(false);
    $product->set_sold_individually(true);
    $product->set_virtual(true); // No shipping needed
    
    // Add a custom SKU
    $product->set_sku('CAR-RENTAL-BOOKING');
    
    // Set description
    $product->set_description('This product is used for car rental bookings. Price will be set based on the quote.');
    
    $product_id = $product->save();
    
    // Save product ID for future use
    update_option('crqa_rental_product_id', $product_id);
}

/**
 * Handle product creation
 */
add_action('admin_init', 'crqa_handle_create_product');
function crqa_handle_create_product() {
    if (isset($_GET['page']) && $_GET['page'] == 'crqa-settings' && isset($_GET['action']) && $_GET['action'] == 'create_product') {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'crqa_create_product')) {
            wp_die('Security check failed');
        }
        
        crqa_create_rental_product();
        wp_redirect(admin_url('admin.php?page=crqa-settings&product_created=1'));
        exit;
    }
}

/**
 * Add rental product to cart with custom price
 */
add_action('wp_ajax_crqa_add_to_cart', 'crqa_add_to_cart');
add_action('wp_ajax_nopriv_crqa_add_to_cart', 'crqa_add_to_cart');
function crqa_add_to_cart() {
    if (!isset($_POST['quote_id']) || !isset($_POST['nonce'])) {
        wp_die('Invalid request');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'crqa_quote_' . $_POST['quote_id'])) {
        wp_die('Security check failed');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $quote_id = intval($_POST['quote_id']);
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
    
    if (!$quote) {
        wp_die('Quote not found');
    }
    
    // Get the rental product
    $product_id = get_option('crqa_rental_product_id');
    
    if (!$product_id || !get_post($product_id)) {
        wp_send_json_error(array('message' => 'Rental product not found. Please contact administrator.'));
        return;
    }
    
    // Clear cart
    WC()->cart->empty_cart();
    
    // Add custom data to cart item
    $cart_item_data = array(
        'crqa_quote_id' => $quote_id,
        'crqa_rental_price' => $quote->rental_price,
        'crqa_deposit_amount' => $quote->deposit_amount,
        'crqa_customer_name' => $quote->customer_name,
        'crqa_customer_email' => $quote->customer_email,
        'crqa_vehicle_name' => $quote->vehicle_name,
        'crqa_rental_dates' => $quote->rental_dates,
        'crqa_form_type' => $quote->form_type
    );
    
    // Add to cart
    WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
    
    wp_send_json_success(array(
        'redirect' => wc_get_checkout_url()
    ));
}

/**
 * Set custom price for rental product in cart
 */
add_action('woocommerce_before_calculate_totals', 'crqa_set_custom_price', 99);
function crqa_set_custom_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['crqa_quote_id'])) {
            $cart_item['data']->set_price($cart_item['crqa_rental_price']);
        }
    }
}

/**
 * Add deposit as fee
 */
add_action('woocommerce_cart_calculate_fees', 'crqa_add_deposit_fee');
function crqa_add_deposit_fee() {
    if (is_admin() && !defined('DOING_AJAX')) return;
    
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['crqa_deposit_amount']) && $cart_item['crqa_deposit_amount'] > 0) {
            WC()->cart->add_fee('Security Deposit', $cart_item['crqa_deposit_amount']);
            break; // Only one quote expected
        }
    }
}

/**
 * Display quote info in cart
 */
add_filter('woocommerce_get_item_data', 'crqa_display_cart_item_data', 10, 2);
function crqa_display_cart_item_data($item_data, $cart_item) {
    if (isset($cart_item['crqa_quote_id'])) {
        $item_data[] = array(
            'name' => 'Quote ID',
            'value' => '#' . str_pad($cart_item['crqa_quote_id'], 5, '0', STR_PAD_LEFT)
        );
        $item_data[] = array(
            'name' => 'Customer',
            'value' => $cart_item['crqa_customer_name']
        );
        $item_data[] = array(
            'name' => 'Vehicle',
            'value' => $cart_item['crqa_vehicle_name']
        );
        $item_data[] = array(
            'name' => 'Rental Dates',
            'value' => $cart_item['crqa_rental_dates']
        );
        
        // Show payment option description
        if (!empty($cart_item['crqa_payment_description'])) {
            $item_data[] = array(
                'name' => 'Payment Type',
                'value' => $cart_item['crqa_payment_description']
            );
        }
        
        // Show original prices if this is a booking fee
        if (!empty($cart_item['crqa_is_booking_fee'])) {
            $currency_symbol = get_woocommerce_currency_symbol();
            $item_data[] = array(
                'name' => 'Total Rental Price',
                'value' => $currency_symbol . number_format($cart_item['crqa_original_rental_price'], 2) . ' (Balance due later)'
            );
        }
        
        if (!empty($cart_item['crqa_form_type']) && $cart_item['crqa_form_type'] != 'standard') {
            $form_types = array(
                'premium' => 'Premium Vehicle',
                'long-term' => 'Long-term Rental',
                'corporate' => 'Corporate',
                'event' => 'Event Rental'
            );
            $item_data[] = array(
                'name' => 'Quote Type',
                'value' => $form_types[$cart_item['crqa_form_type']] ?? ucfirst($cart_item['crqa_form_type'])
            );
        }
    }
    return $item_data;
}

/**
 * Save quote data to order
 */
add_action('woocommerce_checkout_create_order_line_item', 'crqa_save_order_item_meta', 10, 4);
function crqa_save_order_item_meta($item, $cart_item_key, $values, $order) {
    if (isset($values['crqa_quote_id'])) {
        $item->add_meta_data('_crqa_quote_id', $values['crqa_quote_id']);
        $item->add_meta_data('Quote ID', '#' . str_pad($values['crqa_quote_id'], 5, '0', STR_PAD_LEFT));
        $item->add_meta_data('Customer Name', $values['crqa_customer_name']);
        $item->add_meta_data('Customer Email', $values['crqa_customer_email']);
        $item->add_meta_data('Vehicle', $values['crqa_vehicle_name']);
        $item->add_meta_data('Rental Dates', $values['crqa_rental_dates']);
        
        // Save payment option details
        if (!empty($values['crqa_payment_option'])) {
            $item->add_meta_data('Payment Option', $values['crqa_payment_option']);
        }
        
        if (!empty($values['crqa_payment_description'])) {
            $item->add_meta_data('Payment Type', $values['crqa_payment_description']);
        }
        
        // Save original prices for reference
        if (!empty($values['crqa_original_rental_price'])) {
            $item->add_meta_data('_original_rental_price', $values['crqa_original_rental_price']);
        }
        
        if (!empty($values['crqa_original_deposit_amount'])) {
            $item->add_meta_data('_original_deposit_amount', $values['crqa_original_deposit_amount']);
        }
        
        // For booking fee payments, show the balance due
        if (!empty($values['crqa_is_booking_fee'])) {
            $balance_due = floatval($values['crqa_original_rental_price']) + floatval($values['crqa_original_deposit_amount'] ?: 5000) - 200;
            $item->add_meta_data('Balance Due', get_woocommerce_currency_symbol() . number_format($balance_due, 2));
        }
        
        $item->add_meta_data('Rental Price', get_woocommerce_currency_symbol() . number_format($values['crqa_rental_price'], 2));
        if ($values['crqa_deposit_amount'] > 0) {
            $item->add_meta_data('Deposit Amount', get_woocommerce_currency_symbol() . number_format($values['crqa_deposit_amount'], 2));
        }
        if (!empty($values['crqa_form_type']) && $values['crqa_form_type'] != 'standard') {
            $form_types = array(
                'premium' => 'Premium Vehicle',
                'long-term' => 'Long-term Rental',
                'corporate' => 'Corporate',
                'event' => 'Event Rental'
            );
            $item->add_meta_data('Quote Type', $form_types[$values['crqa_form_type']] ?? ucfirst($values['crqa_form_type']));
        }
    }
}

/**
 * Update quote status after payment
 */
add_action('woocommerce_order_status_completed', 'crqa_update_quote_status');
add_action('woocommerce_order_status_processing', 'crqa_update_quote_status');
function crqa_update_quote_status($order_id) {
    $order = wc_get_order($order_id);
    
    foreach ($order->get_items() as $item) {
        $quote_id = $item->get_meta('_crqa_quote_id');
        
        if ($quote_id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'car_rental_quotes';
            
            $wpdb->update(
                $table_name,
                array('quote_status' => 'paid'),
                array('id' => $quote_id)
            );
            
            break;
        }
    }
}

/**
 * Auto-fill checkout fields with quote customer information
 */
add_filter('woocommerce_checkout_get_value', 'crqa_autofill_checkout_fields', 10, 2);
function crqa_autofill_checkout_fields($value, $input) {
    // Only autofill if cart has a quote item
    if (!WC()->cart || WC()->cart->is_empty()) {
        return $value;
    }
    
    // Get quote data from cart
    $quote_data = null;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['crqa_quote_id'])) {
            $quote_data = $cart_item;
            break;
        }
    }
    
    if (!$quote_data) {
        return $value;
    }
    
    // If user is logged in and has saved data, don't override
    if (is_user_logged_in() && $value) {
        return $value;
    }
    
    // Auto-fill fields based on quote data
    switch ($input) {
        case 'billing_first_name':
        case 'shipping_first_name':
            if (!empty($quote_data['crqa_customer_name'])) {
                $name_parts = explode(' ', $quote_data['crqa_customer_name']);
                return $name_parts[0];
            }
            break;
            
        case 'billing_last_name':
        case 'shipping_last_name':
            if (!empty($quote_data['crqa_customer_name'])) {
                $name_parts = explode(' ', $quote_data['crqa_customer_name']);
                if (count($name_parts) > 1) {
                    array_shift($name_parts); // Remove first name
                    return implode(' ', $name_parts);
                }
            }
            break;
            
        case 'billing_email':
            if (!empty($quote_data['crqa_customer_email'])) {
                return $quote_data['crqa_customer_email'];
            }
            break;
            
        case 'billing_phone':
            if (!empty($quote_data['crqa_customer_phone'])) {
                return $quote_data['crqa_customer_phone'];
            }
            break;
    }
    
    return $value;
}

/**
 * Auto-fill checkout fields using JavaScript for better compatibility
 */
add_action('woocommerce_after_checkout_form', 'crqa_autofill_checkout_js');
function crqa_autofill_checkout_js() {
    // Get quote data from cart
    $quote_data = null;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['crqa_quote_id'])) {
            $quote_data = $cart_item;
            break;
        }
    }
    
    if (!$quote_data) {
        return;
    }
    
    // Prepare customer data
    $customer_name = esc_js($quote_data['crqa_customer_name'] ?? '');
    $customer_email = esc_js($quote_data['crqa_customer_email'] ?? '');
    $customer_phone = esc_js($quote_data['crqa_customer_phone'] ?? '');
    
    // Split name into first and last
    $name_parts = explode(' ', $customer_name);
    $first_name = esc_js($name_parts[0] ?? '');
    $last_name = '';
    if (count($name_parts) > 1) {
        array_shift($name_parts);
        $last_name = esc_js(implode(' ', $name_parts));
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Auto-fill checkout fields if they're empty
        function autoFillCheckoutFields() {
            // First name
            var firstNameField = $('#billing_first_name');
            if (firstNameField.length && !firstNameField.val()) {
                firstNameField.val('<?php echo $first_name; ?>').trigger('change');
            }
            
            // Last name
            var lastNameField = $('#billing_last_name');
            if (lastNameField.length && !lastNameField.val()) {
                lastNameField.val('<?php echo $last_name; ?>').trigger('change');
            }
            
            // Email
            var emailField = $('#billing_email');
            if (emailField.length && !emailField.val()) {
                emailField.val('<?php echo $customer_email; ?>').trigger('change');
            }
            
            // Phone
            var phoneField = $('#billing_phone');
            if (phoneField.length && !phoneField.val()) {
                phoneField.val('<?php echo $customer_phone; ?>').trigger('change');
            }
            
            // Also fill shipping fields if same as billing is checked
            var shipToDifferent = $('#ship-to-different-address-checkbox');
            if (!shipToDifferent.is(':checked')) {
                $('#shipping_first_name').val('<?php echo $first_name; ?>').trigger('change');
                $('#shipping_last_name').val('<?php echo $last_name; ?>').trigger('change');
            }
        }
        
        // Run on page load
        autoFillCheckoutFields();
        
        // Also run after checkout updates (in case fields are dynamically loaded)
        $(document.body).on('updated_checkout', function() {
            setTimeout(autoFillCheckoutFields, 100);
        });
    
    </script>
    <?php
}

/**
 * Store quote location data if available for postcode autofill
 */
add_action('woocommerce_checkout_process', 'crqa_process_quote_location');
function crqa_process_quote_location() {
    // Get quote data from cart
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['crqa_quote_id'])) {
            // Get the full quote data if we need location info
            global $wpdb;
            $table_name = $wpdb->prefix . 'car_rental_quotes';
            $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_item['crqa_quote_id']));
            
            if ($quote && !empty($quote->vehicle_details)) {
                // Extract location from vehicle details if available
                if (preg_match('/Location:\s*(.+)/', $quote->vehicle_details, $matches)) {
                    $location = trim($matches[1]);
                    // Store in session for potential postcode lookup
                    WC()->session->set('crqa_customer_location', $location);
                }
            }
            break;
        }
    }
}