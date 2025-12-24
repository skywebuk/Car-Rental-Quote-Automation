<?php
/**
 * Payment Options Shortcode for Car Rental Quote Automation - DARK MODE VERSION
 * 
 * This file handles the payment options dropdown shortcode with dark mode styling
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/payment-options-shortcode.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the payment options shortcode
 */
add_shortcode('crqa_payment_options', 'crqa_payment_options_shortcode');

/**
 * Payment options dropdown shortcode
 */
function crqa_payment_options_shortcode($atts) {
    // Get current quote
    $quote = crqa_get_current_quote();
    if (!$quote) {
        return '<p>No quote found.</p>';
    }
    
    // Check if rental price is set
    if (empty($quote->rental_price) || $quote->rental_price <= 0) {
        return '<p>Price not yet available. Please wait for your personalized quote.</p>';
    }
    
    // Parse attributes
    $atts = shortcode_atts(array(
        'button_text' => 'Proceed to Payment',
        'button_class' => 'crqa-payment-proceed-btn',
        'show_total' => 'yes',
        'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '£'
    ), $atts);
    
    // Get enabled payment options from settings
    $payment_options_enabled = get_option('crqa_payment_options', array(
        'rental_only' => 1,
        'rental_deposit' => 1,
        'booking_fee' => 1
    ));

    // Check if at least one option is enabled
    $has_enabled_options = !empty($payment_options_enabled['rental_only']) ||
                           !empty($payment_options_enabled['rental_deposit']) ||
                           !empty($payment_options_enabled['booking_fee']);

    if (!$has_enabled_options) {
        return '<p>Payment options are currently unavailable. Please contact us.</p>';
    }

    // Calculate amounts - REMOVE DECIMALS
    $rental_price = intval($quote->rental_price);
    // Get deposit from quote or product
    $deposit_amount = intval($quote->deposit_amount);
    if ($deposit_amount == 0 && !empty($quote->product_id)) {
        $deposit_amount = intval(crqa_get_deposit_amount($quote->product_id));
    }
    if ($deposit_amount == 0) {
        $deposit_amount = 5000; // Final fallback
    }
    $total_amount = $rental_price + $deposit_amount;
    $booking_fee = intval(get_option('crqa_booking_fee_amount', 500)); // Configurable booking fee
    
    // Generate unique ID for this instance
    $unique_id = 'crqa-payment-' . uniqid();
    
    // Enqueue styles and scripts
    crqa_enqueue_payment_options_assets();
    
    // Start output buffering
    ob_start();
    ?>
    
    <div class="crqa-payment-options-container" id="<?php echo esc_attr($unique_id); ?>">
        <div class="crqa-payment-dropdown-wrapper">
            <label for="payment-option-<?php echo esc_attr($unique_id); ?>" class="crqa-payment-label">
                Select Payment Option
            </label>
            
            <div class="crqa-custom-dropdown">
                <div class="crqa-dropdown-selected" data-value="">
                    <span class="crqa-selected-text">Choose a payment option...</span>
                    <svg class="crqa-dropdown-arrow" width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                
                <div class="crqa-dropdown-options">
                    <?php if (!empty($payment_options_enabled['rental_only'])): ?>
                    <div class="crqa-dropdown-option" data-value="rental_only" data-amount="<?php echo esc_attr($rental_price); ?>">
                        <div class="crqa-option-content">
                            <div class="crqa-option-title">Rental Price Only</div>
                            <div class="crqa-option-description">Pay now: <span class="crqa-currency-symbol"><?php echo $atts['currency_symbol']; ?></span><?php echo number_format($rental_price, 0, '', ','); ?></div>
                            <div class="crqa-option-note">Deposit required on the day of delivery</div>
                        </div>
                        <div class="crqa-option-check">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M16.6667 5L7.5 14.1667L3.33333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($payment_options_enabled['rental_deposit'])): ?>
                    <div class="crqa-dropdown-option" data-value="rental_deposit" data-amount="<?php echo esc_attr($total_amount); ?>">
                        <div class="crqa-option-content">
                            <div class="crqa-option-title">Rental + Deposit</div>
                            <div class="crqa-option-description">Pay now: <span class="crqa-currency-symbol"><?php echo $atts['currency_symbol']; ?></span><?php echo number_format($total_amount, 0, '', ','); ?></div>
                            <div class="crqa-option-note">Full payment including refundable deposit</div>
                        </div>
                        <div class="crqa-option-check">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M16.6667 5L7.5 14.1667L3.33333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($payment_options_enabled['booking_fee'])): ?>
                    <div class="crqa-dropdown-option" data-value="booking_fee" data-amount="<?php echo esc_attr($booking_fee); ?>">
                        <div class="crqa-option-content">
                            <div class="crqa-option-title">Secure Booking Only</div>
                            <div class="crqa-option-description">Pay now: <span class="crqa-currency-symbol"><?php echo $atts['currency_symbol']; ?></span><?php echo number_format($booking_fee, 0, '', ','); ?></div>
                            <div class="crqa-option-note">Reserve your dates, balance due later</div>
                        </div>
                        <div class="crqa-option-check">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M16.6667 5L7.5 14.1667L3.33333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <input type="hidden" id="payment-option-<?php echo esc_attr($unique_id); ?>" name="payment_option" value="">
            <input type="hidden" id="payment-amount-<?php echo esc_attr($unique_id); ?>" name="payment_amount" value="">
        </div>
        
        <?php if ($atts['show_total'] === 'yes'): ?>
        <div class="crqa-payment-summary" style="display: none;">
            <div class="crqa-summary-row">
                <span class="crqa-summary-label">Selected Option:</span>
                <span class="crqa-summary-value" id="selected-option-text-<?php echo esc_attr($unique_id); ?>">-</span>
            </div>
            <div class="crqa-summary-row crqa-total-row">
                <span class="crqa-summary-label">Amount to Pay:</span>
                <span class="crqa-summary-value crqa-total-amount" id="total-amount-<?php echo esc_attr($unique_id); ?>">-</span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="crqa-payment-button-wrapper">
            <button type="button" 
                    id="payment-proceed-<?php echo esc_attr($unique_id); ?>" 
                    class="<?php echo esc_attr($atts['button_class']); ?>" 
                    disabled>
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
    </div>
    
    <script>
    (function() {
        const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
        const dropdown = container.querySelector('.crqa-custom-dropdown');
        const selected = dropdown.querySelector('.crqa-dropdown-selected');
        const selectedText = selected.querySelector('.crqa-selected-text');
        const optionsContainer = dropdown.querySelector('.crqa-dropdown-options');
        const options = dropdown.querySelectorAll('.crqa-dropdown-option');
        const hiddenInput = container.querySelector('input[name="payment_option"]');
        const amountInput = container.querySelector('input[name="payment_amount"]');
        const proceedButton = container.querySelector('#payment-proceed-<?php echo esc_js($unique_id); ?>');
        const paymentSummary = container.querySelector('.crqa-payment-summary');
        const selectedOptionText = container.querySelector('#selected-option-text-<?php echo esc_js($unique_id); ?>');
        const totalAmountText = container.querySelector('#total-amount-<?php echo esc_js($unique_id); ?>');
        
        const quoteId = <?php echo intval($quote->id); ?>;
        
        // Create currency symbol element
        function getCurrencyHtml() {
            return '<span class="crqa-currency-symbol"><?php echo $atts['currency_symbol']; ?></span>';
        }
        
        // Toggle dropdown
        selected.addEventListener('click', function() {
            dropdown.classList.toggle('open');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
        
        // Handle option selection
        options.forEach(function(option) {
            option.addEventListener('click', function() {
                const value = this.dataset.value;
                const amount = parseInt(this.dataset.amount);
                const title = this.querySelector('.crqa-option-title').textContent;
                
                // Update selected display
                selectedText.textContent = title;
                selected.dataset.value = value;
                
                // Update hidden inputs
                hiddenInput.value = value;
                amountInput.value = amount;
                
                // Update active state
                options.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                // Update summary if visible
                if (paymentSummary) {
                    paymentSummary.style.display = 'block';
                    if (selectedOptionText) selectedOptionText.textContent = title;
                    if (totalAmountText) {
                        // Format amount with no decimals
                        totalAmountText.innerHTML = getCurrencyHtml() + amount.toLocaleString('en-GB', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        });
                    }
                }
                
                // Enable proceed button
                proceedButton.disabled = false;
                
                // Close dropdown
                dropdown.classList.remove('open');
            });
        });
        
        // Handle proceed button click
        proceedButton.addEventListener('click', function() {
            const selectedOption = hiddenInput.value;
            const selectedAmount = amountInput.value;
            
            if (!selectedOption) {
                alert('Please select a payment option');
                return;
            }
            
            // Disable button and show loading state
            this.disabled = true;
            this.textContent = 'Processing...';
            
            // AJAX request to add to cart with selected option
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'crqa_add_to_cart_with_option',
                    quote_id: quoteId,
                    payment_option: selectedOption,
                    payment_amount: selectedAmount,
                    nonce: '<?php echo wp_create_nonce('crqa_payment_' . $quote->id); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert(response.data.message || 'Error processing payment. Please try again.');
                        proceedButton.disabled = false;
                        proceedButton.textContent = '<?php echo esc_js($atts['button_text']); ?>';
                    }
                },
                error: function() {
                    alert('Error processing payment. Please try again.');
                    proceedButton.disabled = false;
                    proceedButton.textContent = '<?php echo esc_js($atts['button_text']); ?>';
                }
            });
        });
    })();
    </script>
    
    <?php
    return ob_get_clean();
}

/**
 * Enqueue payment options assets - DARK MODE VERSION
 */
function crqa_enqueue_payment_options_assets() {
    // Add inline styles for DARK MODE
    add_action('wp_footer', function() {
        ?>
        <style>
        /* Payment Options Container - Dark Mode */
        .crqa-payment-options-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: transparent;
        }
        
        /* Currency Symbol */
        .crqa-currency-symbol {
            /* Ensure currency symbol renders properly */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        /* Custom Dropdown Styles - Dark Mode */
        .crqa-payment-dropdown-wrapper {
            margin-bottom: 20px;
        }
        
        .crqa-payment-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #a0a0a0;
            font-size: 16px;
        }
        
        .crqa-custom-dropdown {
            position: relative;
            width: 100%;
        }
        
        .crqa-dropdown-selected {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: #1a1a1a;
            border: 2px solid #333;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .crqa-dropdown-selected:hover {
            border-color: #dca725;
            background: #222;
        }
        
        .crqa-custom-dropdown.open .crqa-dropdown-selected {
            border-color: #dca725;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            background: #222;
        }
        
        .crqa-selected-text {
            font-size: 16px;
            color: #fff;
        }
        
        .crqa-dropdown-arrow {
            transition: transform 0.3s ease;
            color: #a0a0a0;
        }
        
        .crqa-custom-dropdown.open .crqa-dropdown-arrow {
            transform: rotate(180deg);
        }
        
        .crqa-dropdown-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1a1a1a;
            border: 2px solid #dca725;
            border-top: none;
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        
        .crqa-custom-dropdown.open .crqa-dropdown-options {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .crqa-dropdown-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            cursor: pointer;
            transition: background 0.2s ease;
            border-bottom: 1px solid #333;
        }
        
        .crqa-dropdown-option:last-child {
            border-bottom: none;
        }
        
        .crqa-dropdown-option:hover {
            background: #222;
        }
        
        .crqa-dropdown-option.active {
            background: #2a2a2a;
        }
        
        .crqa-option-content {
            flex: 1;
        }
        
        .crqa-option-title {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .crqa-option-description {
            font-size: 18px;
            color: #dca725;
            font-weight: 700;
            margin-bottom: 3px;
        }
        
        .crqa-option-note {
            font-size: 13px;
            color: #888;
        }
        
        .crqa-option-check {
            width: 24px;
            height: 24px;
            border: 2px solid #dca725;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 20px;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .crqa-dropdown-option.active .crqa-option-check {
            opacity: 1;
            background: #dca725;
        }
        
        .crqa-option-check svg {
            color: #000;
        }
        
        /* Payment Summary - Dark Mode */
        .crqa-payment-summary {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .crqa-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .crqa-summary-row:last-child {
            margin-bottom: 0;
        }
        
        .crqa-total-row {
            padding-top: 10px;
            border-top: 2px solid #333;
            margin-top: 10px;
        }
        
        .crqa-summary-label {
            font-size: 14px;
            color: #888;
        }
        
        .crqa-total-row .crqa-summary-label {
            font-size: 16px;
            font-weight: 600;
            color: #a0a0a0;
        }
        
        .crqa-summary-value {
            font-size: 14px;
            color: #fff;
            font-weight: 500;
        }
        
        .crqa-total-amount {
            font-size: 24px;
            font-weight: 700;
            color: #dca725;
        }
        
        /* Payment Button - Dark Mode */
        .crqa-payment-button-wrapper {
            text-align: center;
            margin-top: 30px;
        }
        
        .crqa-payment-proceed-btn {
            background: #dca725;
            color: #000;
            border: none;
            padding: 18px 50px;
            font-size: 18px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 250px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .crqa-payment-proceed-btn:hover:not(:disabled) {
            background: #c49620;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 167, 37, 0.3);
        }
        
        .crqa-payment-proceed-btn:disabled {
            background: #333;
            color: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Mobile Responsive */
        @media (max-width: 600px) {
            .crqa-payment-options-container {
                padding: 15px;
            }
            
            .crqa-dropdown-option {
                padding: 15px;
            }
            
            .crqa-option-title {
                font-size: 15px;
            }
            
            .crqa-option-description {
                font-size: 16px;
            }
            
            .crqa-option-note {
                font-size: 12px;
            }
            
            .crqa-payment-proceed-btn {
                width: 100%;
                min-width: auto;
                padding: 16px 30px;
                font-size: 16px;
            }
        }
        </style>
        <?php
    }, 99);
    
    // Ensure jQuery is loaded
    wp_enqueue_script('jquery');
}

/**
 * AJAX handler for adding to cart with payment option
 */
add_action('wp_ajax_crqa_add_to_cart_with_option', 'crqa_add_to_cart_with_option');
add_action('wp_ajax_nopriv_crqa_add_to_cart_with_option', 'crqa_add_to_cart_with_option');
function crqa_add_to_cart_with_option() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'crqa_payment_' . $_POST['quote_id'])) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Get quote data
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $quote_id = intval($_POST['quote_id']);
    $payment_option = sanitize_text_field($_POST['payment_option']);
    $payment_amount = floatval($_POST['payment_amount']);
    
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $quote_id));
    
    if (!$quote) {
        wp_send_json_error(array('message' => 'Quote not found'));
    }
    
    // Get the rental product
    $product_id = get_option('crqa_rental_product_id');
    
    if (!$product_id || !get_post($product_id)) {
        wp_send_json_error(array('message' => 'Rental product not found. Please contact administrator.'));
    }
    
    // Clear cart
    WC()->cart->empty_cart();
    
    // Prepare cart item data based on payment option
    $cart_item_data = array(
        'crqa_quote_id' => $quote_id,
        'crqa_customer_name' => $quote->customer_name,
        'crqa_customer_email' => $quote->customer_email,
        'crqa_vehicle_name' => $quote->vehicle_name,
        'crqa_rental_dates' => $quote->rental_dates,
        'crqa_form_type' => $quote->form_type,
        'crqa_payment_option' => $payment_option,
        'crqa_payment_amount' => $payment_amount,
        'crqa_original_rental_price' => $quote->rental_price,
        'crqa_original_deposit_amount' => $quote->deposit_amount
    );
    
    // Set the rental price based on payment option
    switch ($payment_option) {
        case 'rental_only':
            $cart_item_data['crqa_rental_price'] = $quote->rental_price;
            $cart_item_data['crqa_deposit_amount'] = 0;
            $cart_item_data['crqa_payment_description'] = 'Rental Price Only (Deposit due on collection)';
            break;
            
        case 'rental_deposit':
            $cart_item_data['crqa_rental_price'] = $quote->rental_price;
            $cart_item_data['crqa_deposit_amount'] = $quote->deposit_amount ?: 5000;
            $cart_item_data['crqa_payment_description'] = 'Rental Price + Refundable Deposit';
            break;
            
        case 'booking_fee':
            $booking_fee_amount = intval(get_option('crqa_booking_fee_amount', 500));
            $cart_item_data['crqa_rental_price'] = $booking_fee_amount;
            $cart_item_data['crqa_deposit_amount'] = 0;
            $cart_item_data['crqa_payment_description'] = 'Booking Fee (Balance due later)';
            $cart_item_data['crqa_is_booking_fee'] = true;
            break;
    }
    
    // Add to cart
    WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
    
    wp_send_json_success(array(
        'redirect' => wc_get_checkout_url()
    ));
}

/**
 * Initialize payment options shortcode
 * This function should be called from the main plugin file
 */
function crqa_init_payment_options_shortcode() {
    // File is already loaded, shortcode is registered
    return true;
}