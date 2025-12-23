<?php
/**
 * Complete Form Handler Interface for Car Rental Quote Automation
 * 
 * This file provides the base interface for form plugin integrations with enhanced admin notifications
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/form-handlers/class-form-handler-interface.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abstract class for form handler implementations
 */
abstract class CRQA_Form_Handler_Interface {
    
    /**
     * Form handler identifier
     * @var string
     */
    protected $handler_id;
    
    /**
     * Form handler display name
     * @var string
     */
    protected $handler_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the handler
     */
    abstract protected function init();
    
    /**
     * Check if the form plugin is active
     * @return bool
     */
    abstract public function is_active();
    
    /**
     * Get all available forms from the plugin
     * @return array Array of forms with id and title
     */
    abstract public function get_forms();
    
    /**
     * Get form fields for a specific form
     * @param int $form_id
     * @return array Array of fields with id, label, and type
     */
    abstract public function get_form_fields($form_id);
    
    /**
     * Hook into form submission
     * @param array $config Form configuration with mappings
     */
    abstract public function hook_submission($config);
    
    /**
     * Process form submission
     * @param array $fields Submitted fields
     * @param array $config Form configuration with mappings
     * @param mixed $form_data Additional form data
     * @return array|false Processed data or false on failure
     */
    abstract public function process_submission($fields, $config, $form_data);
    
    /**
     * Get handler ID
     * @return string
     */
    public function get_id() {
        return $this->handler_id;
    }
    
    /**
     * Get handler name
     * @return string
     */
    public function get_name() {
        return $this->handler_name;
    }
    
    /**
     * Map form fields to quote fields
     * @param array $fields Form fields
     * @param array $mappings Field mappings
     * @return array Mapped data
     */
    protected function map_fields($fields, $mappings) {
        $data = array(
            'customer_name' => '',
            'customer_email' => '',
            'customer_phone' => '',
            'vehicle_name' => '',
            'rental_dates' => ''
        );
        
        foreach ($mappings as $quote_field => $form_field_id) {
            if (!empty($form_field_id) && isset($fields[$form_field_id]) && isset($data[$quote_field])) {
                $data[$quote_field] = $this->get_field_value($fields[$form_field_id]);
            }
        }
        
        return $data;
    }
    
    /**
     * Get field value from various field formats
     * @param mixed $field
     * @return string
     */
    protected function get_field_value($field) {
        if (is_string($field)) {
            return $field;
        } elseif (is_array($field) && isset($field['value'])) {
            return $field['value'];
        } elseif (is_array($field)) {
            return implode(', ', $field);
        }
        return '';
    }
    
    /**
     * Save quote to database
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
            'rental_dates' => sanitize_text_field($data['rental_dates']),
            'additional_notes' => sanitize_textarea_field($data['additional_notes'] ?? ''),
            'quote_hash' => $quote_hash,
            'quote_status' => 'pending',
            'created_at' => current_time('mysql'),
            'product_id' => !empty($data['product_id']) ? intval($data['product_id']) : null
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
     * Send admin notification - UPDATED to use enhanced template
     * @param int $quote_id
     * @param array $data
     */
    protected function send_admin_notification($quote_id, $data) {
        // Use enhanced admin notification if available
        if (function_exists('crqa_send_enhanced_admin_notification')) {
            crqa_send_enhanced_admin_notification($quote_id, $data);
        } else {
            // Fallback to basic notification
            $this->send_basic_admin_notification($quote_id, $data);
        }
    }
    
    /**
     * Fallback basic admin notification
     * @param int $quote_id
     * @param array $data
     */
    protected function send_basic_admin_notification($quote_id, $data) {
        $admin_emails = get_option('crqa_admin_emails', array());
        if (empty($admin_emails)) {
            $admin_emails = array(get_option('admin_email'));
        }
        
        $company_name = get_option('crqa_company_name', 'Your Car Rental Company');
        
        $subject = sprintf('[%s] New Car Rental Quote Request #%s', $company_name, str_pad($quote_id, 5, '0', STR_PAD_LEFT));
        
        $message = "A new quote request has been submitted.\n\n";
        $message .= "Quote ID: #" . str_pad($quote_id, 5, '0', STR_PAD_LEFT) . "\n";
        $message .= "Customer: " . $data['customer_name'] . "\n";
        $message .= "Email: " . $data['customer_email'] . "\n";
        if (!empty($data['customer_phone'])) {
            $message .= "Phone: " . $data['customer_phone'] . "\n";
        }
        $message .= "Vehicle: " . $data['vehicle_name'] . "\n";
        if (!empty($data['rental_dates'])) {
            $message .= "Dates: " . $data['rental_dates'] . "\n";
        }
        $message .= "\nForm Type: " . $this->handler_name . "\n";
        $message .= "\nLogin to set the price and send quote: " . admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote_id);
        
        // Send to all admin emails
        foreach ($admin_emails as $admin_email) {
            if (is_email($admin_email)) {
                wp_mail($admin_email, $subject, $message);
            }
        }
    }
}

?>