<?php
/**
 * WPForms Handler for Car Rental Quote Automation
 * 
 * Handles WPForms integration with improved structure and error handling
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/form-handlers/class-wpforms-handler.php
 * 
 * @package CarRentalQuoteAutomation
 * @since 2.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPForms Handler Class
 */
class CRQA_WPForms_Handler extends CRQA_Form_Handler_Interface {
    
    /**
     * Cache for form data
     * @var array
     */
    private $forms_cache = array();
    
    /**
     * Cache for product matches
     * @var array
     */
    private $product_cache = array();
    
    /**
     * Logger instance
     * @var object
     */
    private $logger;
    
    /**
     * Initialize the handler
     */
    protected function init() {
        $this->handler_id = 'wpforms';
        $this->handler_name = 'WPForms';
        $this->logger = $this->get_logger();
    }
    
    /**
     * Get logger instance
     * @return object
     */
    private function get_logger() {
        // Simple logger implementation
        return new class {
            public function log($message, $level = 'info') {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[CRQA WPForms {$level}] {$message}");
                }
            }
        };
    }
    
    /**
     * Check if WPForms is active
     * @return bool
     */
    public function is_active() {
        return function_exists('wpforms') && class_exists('WPForms');
    }
    
    /**
     * Get all available forms with caching
     * @return array
     */
    public function get_forms() {
        if (!$this->is_active()) {
            error_log('CRQA WPForms: get_forms() - WPForms not active');
            return array();
        }

        // Check cache first
        $cache_key = 'wpforms_list';
        if (isset($this->forms_cache[$cache_key])) {
            return $this->forms_cache[$cache_key];
        }

        $form_list = array();

        // Try WPForms API first
        try {
            if (function_exists('wpforms') && is_object(wpforms()) && isset(wpforms()->form)) {
                $forms = wpforms()->form->get('', array('orderby' => 'title'));
                if (!empty($forms)) {
                    foreach ($forms as $form) {
                        $form_list[] = array(
                            'id' => $form->ID,
                            'title' => html_entity_decode($form->post_title, ENT_QUOTES)
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log('CRQA WPForms: API error - ' . $e->getMessage());
        }

        // Fallback: Query WPForms post type directly if API didn't work
        if (empty($form_list)) {
            error_log('CRQA WPForms: Trying direct post query fallback');
            $args = array(
                'post_type'      => 'wpforms',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            );

            $forms_query = new WP_Query($args);

            if ($forms_query->have_posts()) {
                foreach ($forms_query->posts as $form) {
                    $form_list[] = array(
                        'id' => $form->ID,
                        'title' => html_entity_decode($form->post_title, ENT_QUOTES)
                    );
                }
            }
            wp_reset_postdata();
        }

        error_log('CRQA WPForms: Found ' . count($form_list) . ' forms');

        // Cache the result
        $this->forms_cache[$cache_key] = $form_list;

        return $form_list;
    }
    
    /**
     * Get form fields with caching
     * @param int $form_id
     * @return array
     */
    public function get_form_fields($form_id) {
        if (!$this->is_active()) {
            return array();
        }
        
        // Check cache
        $cache_key = 'form_fields_' . $form_id;
        if (isset($this->forms_cache[$cache_key])) {
            return $this->forms_cache[$cache_key];
        }
        
        $form = wpforms()->form->get($form_id);
        if (!$form) {
            return array();
        }
        
        $form_data = wpforms_decode($form->post_content);
        $fields = array();
        
        if (!empty($form_data['fields'])) {
            foreach ($form_data['fields'] as $field) {
                $fields[] = array(
                    'id' => $field['id'],
                    'label' => !empty($field['label']) ? $field['label'] : 'Field #' . $field['id'],
                    'type' => $field['type']
                );
            }
        }
        
        // Cache the result
        $this->forms_cache[$cache_key] = $fields;
        
        return $fields;
    }
    
    /**
     * Hook into form submission
     * @param array $config
     */
    public function hook_submission($config) {
        add_action('wpforms_process_complete', array($this, 'handle_submission'), 10, 4);
    }
    
    /**
     * Handle WPForms submission
     * @param array $fields
     * @param array $entry
     * @param array $form_data
     * @param int $entry_id
     */
    public function handle_submission($fields, $entry, $form_data, $entry_id) {
        try {
            // Get all active form configurations
            $forms_config = get_option('crqa_forms_config', array());
            
            // Check if this form is configured
            foreach ($forms_config as $config) {
                if ($this->is_form_configured($config, $form_data['id'])) {
                    $result = $this->process_submission($fields, $config, array(
                        'form_data' => $form_data,
                        'entry_id' => $entry_id,
                        'entry' => $entry
                    ));
                    
                    if ($result) {
                        $this->logger->log("Quote created successfully from form {$form_data['id']}", 'info');
                    }
                    break;
                }
            }
        } catch (Exception $e) {
            $this->logger->log("Error handling submission: " . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Check if form is configured and enabled
     * @param array $config
     * @param int $form_id
     * @return bool
     */
    private function is_form_configured($config, $form_id) {
        return !empty($config['form_id']) && 
               $config['form_id'] == $form_id && 
               !empty($config['enabled']) && 
               !empty($config['form_handler']) && 
               $config['form_handler'] == $this->handler_id;
    }
    
    /**
     * Process form submission
     * @param array $fields
     * @param array $config
     * @param mixed $form_data
     * @return array|false
     */
    public function process_submission($fields, $config, $form_data) {
        try {
            $this->logger->log("Processing submission for form {$form_data['form_data']['id']}", 'info');
            
            // Map fields
            $mapped_data = $this->map_fields($fields, $config['mappings']);
            
            // Add form type
            if (!empty($config['form_type'])) {
                $mapped_data['form_type'] = $config['form_type'];
            }
            
            // Add tracking data
            $mapped_data = $this->add_tracking_data($mapped_data);
            
            // Process intelligent product matching
            $mapped_data = $this->process_product_matching($mapped_data);
            
            // Calculate pricing if possible
            $mapped_data = $this->calculate_pricing($mapped_data);
            
            // Build vehicle details
            $mapped_data['vehicle_details'] = $this->build_vehicle_details($mapped_data);
            
            // IMPORTANT: Add calculated price to main data for saving
            if (!empty($mapped_data['calculated_rental_price'])) {
                $mapped_data['rental_price'] = $mapped_data['calculated_rental_price'];
            }
            if (!empty($mapped_data['calculated_rental_price']) && empty($mapped_data['deposit_amount'])) {
                // Get deposit from product if available
                if (!empty($mapped_data['product_id']) && function_exists('crqa_get_deposit_amount')) {
                    $mapped_data['deposit_amount'] = crqa_get_deposit_amount($mapped_data['product_id']);
                } else {
                    $mapped_data['deposit_amount'] = 5000; // Default deposit
                }
            }
            if (!empty($mapped_data['calculated_prepaid_miles'])) {
                $mapped_data['mileage_allowance'] = $mapped_data['calculated_prepaid_miles'];
            }
            
            // Save quote with calculated price
            $quote_id = $this->save_quote($mapped_data, $form_data['entry_id']);
            
            if ($quote_id) {
                // Update with WPForms specific data
                $this->update_wpforms_data($quote_id, $form_data);
                
                return array(
                    'success' => true,
                    'quote_id' => $quote_id,
                    'data' => $mapped_data,
                    'matched_product' => $mapped_data['matched_product_name'] ?? null
                );
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->log("Error processing submission: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Save quote to database - UPDATED to include pricing
     * @param array $data Quote data
     * @param int $entry_id Optional entry ID from form plugin
     * @return int|false Quote ID or false on failure
     */
    protected function save_quote($data, $entry_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'car_rental_quotes';
        
        // Validate required fields
        if (empty($data['customer_name']) || empty($data['customer_email'])) {
            error_log('Car Rental Quote: Missing required fields - customer name or email');
            return false;
        }
        
        // Generate unique hash
        $quote_hash = md5(uniqid() . time() . $data['customer_email']);
        
        // Prepare data for insertion
        $insert_data = array(
            'customer_name' => sanitize_text_field($data['customer_name']),
            'customer_email' => sanitize_email($data['customer_email']),
            'customer_phone' => sanitize_text_field($data['customer_phone']),
            'vehicle_name' => sanitize_text_field($data['vehicle_name']),
            'vehicle_details' => sanitize_textarea_field($data['vehicle_details'] ?? ''),
            'customer_age' => sanitize_text_field($data['customer_age'] ?? ''),
            'contact_preference' => sanitize_text_field($data['contact_preference'] ?? ''),
            'rental_dates' => sanitize_text_field($data['rental_dates']),
            'additional_notes' => sanitize_textarea_field($data['additional_notes'] ?? ''),
            'quote_hash' => $quote_hash,
            'quote_status' => 'pending',
            'created_at' => current_time('mysql'),
            'product_id' => !empty($data['product_id']) ? intval($data['product_id']) : null,
            // IMPORTANT: Include calculated pricing
            'rental_price' => !empty($data['rental_price']) ? floatval($data['rental_price']) : null,
            'deposit_amount' => !empty($data['deposit_amount']) ? floatval($data['deposit_amount']) : null,
            'mileage_allowance' => !empty($data['mileage_allowance']) ? sanitize_text_field($data['mileage_allowance']) : null
        );
        
        // Add form handler info
        $insert_data['form_handler'] = $this->handler_id;
        if ($entry_id) {
            $insert_data['form_entry_id'] = $entry_id;
        }
        
        // Add form type if provided
        if (!empty($data['form_type'])) {
            $insert_data['form_type'] = sanitize_text_field($data['form_type']);
        }
        
        // Insert into database
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            error_log('Car Rental Quote: Failed to insert quote - ' . $wpdb->last_error);
            return false;
        }
        
        $quote_id = $wpdb->insert_id;
        
        // Send enhanced admin notification
        $this->send_admin_notification($quote_id, $data);
        
        return $quote_id;
    }
    
    /**
     * Enhanced map fields with smart tag support
     * @param array $fields Form fields
     * @param array $mappings Field mappings
     * @return array Mapped data
     */
    protected function map_fields($fields, $mappings) {
        $data = array(
            'customer_name' => '',
            'customer_email' => '',
            'customer_age' => '',           // ADD THIS LINE
            'contact_preference' => '',     // ADD THIS LINE
            'customer_phone' => '',
            'vehicle_name' => '',
            'rental_dates' => '',
            'product_id' => null,
            'additional_notes' => '',
            'vehicle_details' => ''
        );
        
        // Process regular field mappings
        foreach ($data as $quote_field => $default_value) {
            // Skip special handling fields
            if (in_array($quote_field, ['vehicle_name', 'product_id'])) {
                continue;
            }
            
            if (!empty($mappings[$quote_field]) && isset($fields[$mappings[$quote_field]])) {
                $data[$quote_field] = $this->get_field_value($fields[$mappings[$quote_field]]);
            }
        }
        
        // Handle vehicle name (field or smart tag)
        $data['vehicle_name'] = $this->process_vehicle_name($mappings, $fields);
        
        // Handle product ID (field or smart tag)
        $data['product_id'] = $this->process_product_id($mappings, $fields);
        
        // Auto-detect Age and Contact Preference fields
    foreach ($fields as $field_id => $field) {
        $label = isset($field['label']) ? strtolower($field['label']) : '';
        $value = $this->get_field_value($field);
        
        // Check for Age field
        if (empty($data['customer_age']) && strpos($label, 'age') !== false) {
            $data['customer_age'] = $value;
            $this->logger->log("Found age field: {$value}");
        }
        
        // Check for Contact Preference field
        if (empty($data['contact_preference']) && 
            (strpos($label, 'contact me by') !== false || 
             strpos($label, 'contact preference') !== false ||
             strpos($label, 'preferred contact') !== false)) {
            $data['contact_preference'] = $value;
            $this->logger->log("Found contact preference: {$value}");
        }
    }
    
    return $data;
}
        

    
    /**
     * Process vehicle name from mappings
     * @param array $mappings
     * @param array $fields
     * @return string
     */
    private function process_vehicle_name($mappings, $fields) {
        // Check for smart tag first
        if (!empty($mappings['vehicle_smart_tag'])) {
            return $this->process_smart_tags($mappings['vehicle_smart_tag'], $fields);
        }
        
        // Fall back to regular field mapping
        if (!empty($mappings['vehicle_name']) && isset($fields[$mappings['vehicle_name']])) {
            return $this->get_field_value($fields[$mappings['vehicle_name']]);
        }
        
        return '';
    }
    
    /**
     * Process product ID from mappings
     * @param array $mappings
     * @param array $fields
     * @return int|null
     */
    private function process_product_id($mappings, $fields) {
        $product_id = null;
        
        // Check for smart tag first
        if (!empty($mappings['product_id_smart_tag'])) {
            $value = $this->process_smart_tags($mappings['product_id_smart_tag'], $fields);
            $product_id = !empty($value) ? intval($value) : null;
        }
        // Fall back to regular field mapping
        elseif (!empty($mappings['product_id']) && isset($fields[$mappings['product_id']])) {
            $value = $this->get_field_value($fields[$mappings['product_id']]);
            $product_id = !empty($value) ? intval($value) : null;
        }
        
        // Validate product exists
        if ($product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if (!$product) {
                $this->logger->log("Product ID {$product_id} does not exist", 'warning');
                return null;
            }
        }
        
        return $product_id;
    }
    
    /**
     * Add tracking data to submission
     * @param array $data
     * @return array
     */
    private function add_tracking_data($data) {
        $data['ip_address'] = $this->get_user_ip();
        $data['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        $data['referrer_url'] = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '';
        $data['user_location'] = $this->get_user_location();
        
        return $data;
    }
    
    /**
     * Process intelligent product matching
     * @param array $data
     * @return array
     */
    private function process_product_matching($data) {
        // If no product ID but we have vehicle name, try to match
        if (empty($data['product_id']) && !empty($data['vehicle_name'])) {
            $this->logger->log("Attempting product match for: {$data['vehicle_name']}", 'info');
            
            $matched_product = $this->find_product_by_subtitle($data['vehicle_name']);
            
            if ($matched_product) {
                $data['product_id'] = $matched_product->get_id();
                $data['matched_product_name'] = $matched_product->get_name();
                $this->logger->log("Matched product: {$matched_product->get_name()} (ID: {$matched_product->get_id()})", 'info');
            }
        }
        
        return $data;
    }
    
    /**
     * Calculate pricing based on product and rental duration
     * @param array $data
     * @return array
     */
    private function calculate_pricing($data) {
        if (empty($data['product_id']) || empty($data['rental_dates'])) {
            return $data;
        }
        
        // Get price per day
        $price_per_day = $this->get_product_price_per_day($data['product_id']);
        
        if ($price_per_day > 0) {
            $rental_days = $this->calculate_rental_days($data['rental_dates']);
            $data['calculated_rental_price'] = $price_per_day * $rental_days;
            $data['rental_days'] = $rental_days;
            $data['price_per_day'] = $price_per_day;
            
            $this->logger->log("Calculated price: {$data['calculated_rental_price']} ({$price_per_day} × {$rental_days} days)", 'info');
        }
        
        // Get prepaid miles
        $prepaid_miles = $this->get_product_prepaid_miles($data['product_id']);
        if ($prepaid_miles) {
            $data['calculated_prepaid_miles'] = $prepaid_miles;
        }
        
        return $data;
    }
    
    /**
     * Build vehicle details information
     * @param array $data
     * @return string
     */
    private function build_vehicle_details($data) {
        $details = array();
        
        // Add form submission info
        $details[] = '=== Form Submission Details ===';
        $details[] = 'Submitted: ' . current_time('mysql');
        
        if (!empty($data['user_location'])) {
            $details[] = 'Location: ' . $data['user_location'];
        }
        
        // Add product matching info
        if (!empty($data['matched_product_name'])) {
            $details[] = '';
            $details[] = '=== Product Information ===';
            $details[] = 'Matched Product: ' . $data['matched_product_name'];
            
            if (!empty($data['calculated_rental_price'])) {
                $details[] = 'Calculated Price: ' . number_format($data['calculated_rental_price'], 2);
                $details[] = 'Price per Day: ' . number_format($data['price_per_day'], 2);
                $details[] = 'Rental Days: ' . $data['rental_days'];
            }
            
            if (!empty($data['calculated_prepaid_miles'])) {
                $details[] = 'Pre-paid Miles: ' . $data['calculated_prepaid_miles'];
            }
        }
        
        // Add any existing details
        if (!empty($data['vehicle_details'])) {
            $details[] = '';
            $details[] = '=== Additional Details ===';
            $details[] = $data['vehicle_details'];
        }
        
        return implode("\n", $details);
    }
    
    /**
     * Update WPForms specific data after quote creation
     * @param int $quote_id
     * @param array $form_data
     */
    private function update_wpforms_data($quote_id, $form_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'car_rental_quotes';
        
        $update_data = array(
            'wpforms_entry_id' => $form_data['entry_id'],
            'form_type' => $form_data['form_type'] ?? 'standard'
        );
        
        $wpdb->update($table_name, $update_data, array('id' => $quote_id));
    }
    
    /**
     * Find matching WooCommerce product by car subtitle
     * @param string $vehicle_name
     * @return WC_Product|null
     */
    protected function find_product_by_subtitle($vehicle_name) {
        if (!function_exists('wc_get_products') || empty($vehicle_name)) {
            return null;
        }
        
        // Check cache first
        $cache_key = md5('product_subtitle_' . $vehicle_name);
        if (isset($this->product_cache[$cache_key])) {
            return $this->product_cache[$cache_key];
        }
        
        $product = null;
        
        // Strategy 1: Exact taxonomy term match
        $product = $this->find_product_by_exact_term($vehicle_name);
        
        // Strategy 2: Fuzzy matching
        if (!$product) {
            $product = $this->find_product_by_fuzzy_match($vehicle_name);
        }
        
        // Cache the result
        $this->product_cache[$cache_key] = $product;
        
        return $product;
    }
    
    /**
     * Find product by exact taxonomy term
     * @param string $vehicle_name
     * @return WC_Product|null
     */
    private function find_product_by_exact_term($vehicle_name) {
        $terms = get_terms(array(
            'taxonomy' => 'pa_car_subtitle',
            'hide_empty' => false,
            'name__like' => $vehicle_name,
            'number' => 1
        ));
        
        if (!empty($terms) && !is_wp_error($terms)) {
            $products = wc_get_products(array(
                'limit' => 1,
                'status' => 'publish',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'pa_car_subtitle',
                        'field' => 'term_id',
                        'terms' => $terms[0]->term_id
                    )
                )
            ));
            
            if (!empty($products)) {
                return $products[0];
            }
        }
        
        return null;
    }
    
    /**
     * Find product by fuzzy matching
     * @param string $vehicle_name
     * @return WC_Product|null
     */
    private function find_product_by_fuzzy_match($vehicle_name) {
        $all_terms = get_terms(array(
            'taxonomy' => 'pa_car_subtitle',
            'hide_empty' => false
        ));

        if (empty($all_terms) || is_wp_error($all_terms)) {
            return null;
        }

        $vehicle_name_lower = strtolower(trim($vehicle_name));
        $best_match = null;
        $best_score = 0;

        // Minimum length to avoid matching very short strings
        if (strlen($vehicle_name_lower) < 3) {
            return null;
        }

        foreach ($all_terms as $term) {
            $term_name_lower = strtolower(trim($term->name));
            $score = 0;

            // Skip very short term names
            if (strlen($term_name_lower) < 3) {
                continue;
            }

            // Calculate match score
            if ($term_name_lower === $vehicle_name_lower) {
                $score = 100;
            } elseif (strpos($term_name_lower, $vehicle_name_lower) !== false) {
                $score = 85;
            } elseif (strpos($vehicle_name_lower, $term_name_lower) !== false) {
                $score = 80;
            } else {
                // Use similar_text for fuzzy matching
                similar_text($term_name_lower, $vehicle_name_lower, $percent);
                $score = $percent;
            }

            // Increased threshold to 75% for better accuracy
            if ($score > $best_score && $score >= 75) {
                $best_match = $term;
                $best_score = $score;
            }
        }
        
        if ($best_match) {
            $products = wc_get_products(array(
                'limit' => 1,
                'status' => 'publish',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'pa_car_subtitle',
                        'field' => 'term_id',
                        'terms' => $best_match->term_id
                    )
                )
            ));
            
            if (!empty($products)) {
                $this->logger->log("Fuzzy matched '{$vehicle_name}' to '{$best_match->name}' (score: {$best_score}%)", 'info');
                return $products[0];
            }
        }
        
        return null;
    }
    
    /**
     * Get price per day from product
     * @param int $product_id
     * @return float
     */
    protected function get_product_price_per_day($product_id) {
        if (!$product_id || !function_exists('wc_get_product')) {
            return 0;
        }
        
        // Check cache
        $cache_key = 'price_per_day_' . $product_id;
        if (isset($this->product_cache[$cache_key])) {
            return $this->product_cache[$cache_key];
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }
        
        $price = 0;
        
        // Try taxonomy attribute first
        $price_terms = wp_get_post_terms($product_id, 'pa_price_per_day');
        if (!is_wp_error($price_terms) && !empty($price_terms)) {
            $price_value = $price_terms[0]->name;
            $price = $this->parse_price_value($price_value);
        }

        // Fallback to meta field
        if ($price == 0) {
            $price_meta = get_post_meta($product_id, 'price_per_day', true);
            if ($price_meta) {
                $price = $this->parse_price_value($price_meta);
            }
        }
        
        // Cache the result
        $this->product_cache[$cache_key] = $price;

        return $price;
    }

    /**
     * Parse a price value string, handling multiple decimal points correctly
     * @param string $value The price value to parse
     * @return float The parsed price
     */
    protected function parse_price_value($value) {
        if (empty($value)) {
            return 0.0;
        }

        // Remove all non-numeric characters except dots and commas
        $cleaned = preg_replace('/[^0-9.,]/', '', $value);

        // Handle European format (comma as decimal separator)
        // If there's a comma after the last dot, treat comma as decimal
        if (preg_match('/\.\d{3},\d{2}$/', $cleaned)) {
            // European format: 1.234,56 -> 1234.56
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } else {
            // Standard format or UK format: remove commas (thousand separators)
            $cleaned = str_replace(',', '', $cleaned);
        }

        // If multiple dots remain, keep only the last one as decimal
        $parts = explode('.', $cleaned);
        if (count($parts) > 2) {
            $decimal = array_pop($parts);
            $cleaned = implode('', $parts) . '.' . $decimal;
        }

        return floatval($cleaned);
    }

    /**
     * Get pre-paid miles from product
     * @param int $product_id
     * @return string
     */
    protected function get_product_prepaid_miles($product_id) {
        if (!$product_id || !function_exists('wc_get_product')) {
            return '';
        }
        
        // Check cache
        $cache_key = 'prepaid_miles_' . $product_id;
        if (isset($this->product_cache[$cache_key])) {
            return $this->product_cache[$cache_key];
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }
        
        $miles = '';
        
        // Try taxonomy attribute
        $miles_terms = wp_get_post_terms($product_id, 'pa_pre_paid_miles');
        if (!is_wp_error($miles_terms) && !empty($miles_terms)) {
            $miles = $miles_terms[0]->name;
        }
        
        // Fallback to meta field
        if (empty($miles)) {
            $miles = get_post_meta($product_id, 'pre_paid_miles', true) ?: '';
        }
        
        // Cache the result
        $this->product_cache[$cache_key] = $miles;
        
        return $miles;
    }
    
    /**
     * Calculate rental duration in days - FIXED VERSION
     * @param string $rental_dates
     * @return int
     */
    protected function calculate_rental_days($rental_dates) {
        if (empty($rental_dates)) {
            return 1;
        }
        
        $this->logger->log("Calculating rental days for: {$rental_dates}", 'info');
        
        // Pattern for DD/MM/YYYY to DD/MM/YYYY format
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s*(?:to|[-–])\s*(\d{1,2})\/(\d{1,2})\/(\d{4})/', $rental_dates, $matches)) {
            $start_day = intval($matches[1]);
            $start_month = intval($matches[2]);
            $start_year = intval($matches[3]);
            $end_day = intval($matches[4]);
            $end_month = intval($matches[5]);
            $end_year = intval($matches[6]);
            
            // Determine if it's UK format (DD/MM/YYYY) or US format (MM/DD/YYYY)
            // If any day value is > 12, it must be UK format
            $is_uk_format = ($start_day > 12 || $end_day > 12);
            
            // If both days <= 12, check context or default to UK
            if (!$is_uk_format && $start_day <= 12 && $end_day <= 12) {
                // Default to UK format for this plugin
                $is_uk_format = true;
            }
            
            try {
                if ($is_uk_format) {
                    // Parse as UK format DD/MM/YYYY
                    $start = DateTime::createFromFormat('d/m/Y', sprintf('%02d/%02d/%04d', $start_day, $start_month, $start_year));
                    $end = DateTime::createFromFormat('d/m/Y', sprintf('%02d/%02d/%04d', $end_day, $end_month, $end_year));
                } else {
                    // Parse as US format MM/DD/YYYY
                    $start = DateTime::createFromFormat('m/d/Y', sprintf('%02d/%02d/%04d', $start_day, $start_month, $start_year));
                    $end = DateTime::createFromFormat('m/d/Y', sprintf('%02d/%02d/%04d', $end_day, $end_month, $end_year));
                }
                
                if ($start && $end && $start <= $end) {
                    $interval = $start->diff($end);
                    $days = $interval->days;
                    
                    $this->logger->log("Parsed dates - Start: {$start->format('Y-m-d')}, End: {$end->format('Y-m-d')}, Days: {$days}", 'info');
                    
                    // Sanity check - if more than a year, something might be wrong
                    if ($days > 365) {
                        $this->logger->log("Warning: Calculated {$days} days seems excessive, defaulting to 1", 'warning');
                        return 1;
                    }
                    
                    return max(1, $days);
                }
            } catch (Exception $e) {
                $this->logger->log("Date parsing error: " . $e->getMessage(), 'error');
            }
        }
        
        // Pattern for "Month DD-DD, YYYY" format
        if (preg_match('/(\w+)\s+(\d+)-(\d+),\s*(\d{4})/', $rental_dates, $matches)) {
            $start_day = intval($matches[2]);
            $end_day = intval($matches[3]);
            $days = $end_day - $start_day;
            $this->logger->log("Calculated {$days} days from month range format", 'info');
            return max(1, $days);
        }
        
        // Pattern for ISO format YYYY-MM-DD to YYYY-MM-DD
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})\s*(?:to|[-–])\s*(\d{4})-(\d{2})-(\d{2})/', $rental_dates, $matches)) {
            try {
                $start = DateTime::createFromFormat('Y-m-d', $matches[1] . '-' . $matches[2] . '-' . $matches[3]);
                $end = DateTime::createFromFormat('Y-m-d', $matches[4] . '-' . $matches[5] . '-' . $matches[6]);
                
                if ($start && $end && $start <= $end) {
                    $interval = $start->diff($end);
                    $days = $interval->days;
                    $this->logger->log("Calculated {$days} days from ISO format", 'info');
                    return max(1, $days);
                }
            } catch (Exception $e) {
                $this->logger->log("ISO date parsing error: " . $e->getMessage(), 'error');
            }
        }
        
        // Try to extract day count from text
        if (preg_match('/(\d+)\s*(?:days?|nights?)/i', $rental_dates, $matches)) {
            $days = intval($matches[1]);
            $this->logger->log("Found explicit day count: {$days}", 'info');
            return max(1, $days);
        }
        
        // Default to 1 day if we can't determine duration
        $this->logger->log("Could not parse rental dates, defaulting to 1 day", 'warning');
        return 1;
    }
    
    /**
     * Process WPForms smart tags
     * @param string $value
     * @param array $fields
     * @return string
     */
    protected function process_smart_tags($value, $fields) {
        // Handle field ID smart tags
        $value = preg_replace_callback(
            '/{field_id=["\']?(\d+)["\']?}/',
            function($matches) use ($fields) {
                $field_id = $matches[1];
                if (isset($fields[$field_id])) {
                    return $this->get_field_value($fields[$field_id]);
                }
                return '';
            },
            $value
        );
        
        // Get smart tag replacements
        $replacements = $this->get_smart_tag_replacements();
        
        // Replace all smart tags
        foreach ($replacements as $tag => $replacement) {
            $value = str_replace($tag, $replacement, $value);
        }
        
        return $value;
    }
    
    /**
     * Get smart tag replacements
     * @return array
     */
    private function get_smart_tag_replacements() {
        $replacements = array(
            '{page_title}' => $this->get_page_title(),
            '{page_url}' => $this->get_page_url(),
            '{page_id}' => get_the_ID() ?: '',
            '{date}' => date(get_option('date_format')),
            '{time}' => date(get_option('time_format')),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_site_url(),
            '{admin_email}' => get_option('admin_email'),
            '{user_ip}' => $this->get_user_ip(),
            '{user_location}' => $this->get_user_location(),
        );
        
        // Add user-related tags if logged in
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $replacements['{user_id}'] = $current_user->ID;
            $replacements['{user_email}'] = $current_user->user_email;
            $replacements['{user_login}'] = $current_user->user_login;
            $replacements['{user_first_name}'] = $current_user->first_name;
            $replacements['{user_last_name}'] = $current_user->last_name;
            $replacements['{user_display_name}'] = $current_user->display_name;
        }
        
        return $replacements;
    }
    
    /**
     * Get user IP address
     * @param bool $public_only If true, only return public IPs (for geolocation). If false, accept any valid IP.
     * @return string
     */
    protected function get_user_ip($public_only = false) {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',             // Proxy
            'HTTP_X_FORWARDED_FOR',       // Load balancer
            'HTTP_X_FORWARDED',           // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
            'HTTP_FORWARDED_FOR',         // Forwarded
            'HTTP_FORWARDED',             // Forwarded
            'REMOTE_ADDR'                 // Standard
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);

                // Validate IP format first
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    continue;
                }

                // If public_only, skip private/reserved ranges
                if ($public_only) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                } else {
                    // Accept any valid IP (including private ranges for logging)
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR with validation
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return 'Unknown';
    }
    
    /**
     * Get user location based on IP with caching
     * @return string
     */
    protected function get_user_location() {
        // Get public IP only for geolocation (private IPs can't be geolocated)
        $ip = $this->get_user_ip(true);

        if ($ip === 'Unknown') {
            return 'Unknown';
        }

        // Check cache first
        $location = get_transient('crqa_location_' . $ip);
        if ($location !== false) {
            return $location;
        }

        // Check if API is temporarily disabled (circuit breaker pattern)
        $api_failures = get_transient('crqa_geoip_failures');
        if ($api_failures && $api_failures >= 3) {
            // API has failed 3+ times recently, skip to avoid rate limiting
            return 'Unknown';
        }

        // Try to get location from IP with timeout
        $response = wp_safe_remote_get('http://ip-api.com/json/' . $ip . '?fields=status,city,regionName,country', array(
            'timeout' => 3,  // Reduced timeout
            'redirection' => 0
        ));

        if (is_wp_error($response)) {
            // Increment failure counter (circuit breaker)
            $failures = intval(get_transient('crqa_geoip_failures')) + 1;
            set_transient('crqa_geoip_failures', $failures, 5 * MINUTE_IN_SECONDS);
            return 'Unknown';
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            // Reset failure counter on success
            delete_transient('crqa_geoip_failures');

            $location_parts = array_filter(array(
                $data['city'] ?? '',
                $data['regionName'] ?? '',
                $data['country'] ?? ''
            ));
            $location = implode(', ', $location_parts) ?: 'Unknown';

            // Cache for 1 hour
            set_transient('crqa_location_' . $ip, $location, HOUR_IN_SECONDS);

            return $location;
        }

        // Cache "Unknown" briefly to avoid repeated failed lookups
        set_transient('crqa_location_' . $ip, 'Unknown', 15 * MINUTE_IN_SECONDS);
        return 'Unknown';
    }
    
    /**
     * Get the page title where form was submitted
     * @return string
     */
    protected function get_page_title() {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $page_id = url_to_postid($_SERVER['HTTP_REFERER']);
            if ($page_id) {
                return get_the_title($page_id);
            }
        }
        
        return is_singular() ? get_the_title() : 'Unknown Page';
    }
    
    /**
     * Get the page URL where form was submitted
     * @return string
     */
    protected function get_page_url() {
        return isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : get_permalink();
    }
    
    /**
     * Get field value from WPForms field
     * @param mixed $field
     * @return string
     */
    protected function get_field_value($field) {
        if (is_array($field)) {
            if (isset($field['value'])) {
                return is_array($field['value']) ? implode(', ', $field['value']) : $field['value'];
            }
            return implode(', ', $field);
        }
        
        return strval($field);
    }
}