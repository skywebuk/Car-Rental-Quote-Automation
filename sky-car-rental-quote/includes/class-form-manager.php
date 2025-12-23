<?php
/**
 * Form Manager for Car Rental Quote Automation - WPForms Only
 * 
 * This file manages WPForms handler and provides central access
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/class-form-manager.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Manager Class
 */
class CRQA_Form_Manager {
    
    /**
     * Singleton instance
     * @var CRQA_Form_Manager
     */
    private static $instance = null;
    
    /**
     * Registered form handlers
     * @var array
     */
    private $handlers = array();
    
    /**
     * Get singleton instance
     * @return CRQA_Form_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_handlers();
        $this->init_hooks();
    }
    
    /**
     * Load WPForms handler
     */
    private function load_handlers() {
        // Load base interface first
        if (file_exists(CRQA_PLUGIN_PATH . 'includes/form-handlers/class-form-handler-interface.php')) {
            require_once CRQA_PLUGIN_PATH . 'includes/form-handlers/class-form-handler-interface.php';
        }
        
        // Load WPForms handler
        $wpforms_handler_file = CRQA_PLUGIN_PATH . 'includes/form-handlers/class-wpforms-handler.php';
        if (file_exists($wpforms_handler_file)) {
            require_once $wpforms_handler_file;
            error_log('CRQA Form Manager: Loaded WPForms handler file');
        } else {
            error_log('CRQA Form Manager: WPForms handler file not found ' . $wpforms_handler_file);
        }
        
        // Register WPForms handler if class exists
        if (class_exists('CRQA_WPForms_Handler')) {
            $this->register_handler(new CRQA_WPForms_Handler());
            error_log('CRQA Form Manager: Registered WPForms handler');
        } else {
            error_log('CRQA Form Manager: CRQA_WPForms_Handler class not found');
        }
        
        // Allow other plugins to register handlers
        do_action('crqa_register_form_handlers', $this);
        
        error_log('CRQA Form Manager: Total handlers registered: ' . count($this->handlers));
    }
    
    /**
     * Initialize hooks for all active handlers
     */
    private function init_hooks() {
        $forms_config = get_option('crqa_forms_config', array());
        
        error_log('CRQA Form Manager: Initializing hooks for ' . count($forms_config) . ' configured forms');
        
        foreach ($forms_config as $index => $config) {
            if (!empty($config['enabled']) && !empty($config['form_handler'])) {
                $handler = $this->get_handler($config['form_handler']);
                if ($handler && $handler->is_active()) {
                    $handler->hook_submission($config);
                    error_log('CRQA Form Manager: Hooked submission for form ' . $config['form_id'] . ' with handler ' . $config['form_handler']);
                } else {
                    error_log('CRQA Form Manager: Handler not found or not active for ' . $config['form_handler']);
                }
            } else {
                error_log('CRQA Form Manager: Form config ' . $index . ' not enabled or missing handler');
            }
        }
        
        // Re-initialize hooks when configuration is updated
        add_action('crqa_forms_config_updated', array($this, 'reinit_hooks'));
    }
    
    /**
     * Re-initialize hooks after configuration update
     * @param array $forms_config Updated configuration
     */
    public function reinit_hooks($forms_config) {
        error_log('CRQA Form Manager: Re-initializing hooks after config update');
        
        foreach ($forms_config as $config) {
            if (!empty($config['enabled']) && !empty($config['form_handler'])) {
                $handler = $this->get_handler($config['form_handler']);
                if ($handler && $handler->is_active()) {
                    $handler->hook_submission($config);
                    error_log('CRQA Form Manager: Re-hooked submission for form ' . $config['form_id'] . ' with handler ' . $config['form_handler']);
                }
            }
        }
    }
    
    /**
     * Register a form handler
     * @param CRQA_Form_Handler_Interface $handler
     */
    public function register_handler(CRQA_Form_Handler_Interface $handler) {
        $this->handlers[$handler->get_id()] = $handler;
        error_log('CRQA Form Manager: Handler registered - ' . $handler->get_id() . ' (' . $handler->get_name() . ')');
    }
    
    /**
     * Get a specific handler
     * @param string $handler_id
     * @return CRQA_Form_Handler_Interface|null
     */
    public function get_handler($handler_id) {
        return isset($this->handlers[$handler_id]) ? $this->handlers[$handler_id] : null;
    }
    
    /**
     * Get all registered handlers
     * @return array
     */
    public function get_handlers() {
        return $this->handlers;
    }
    
    /**
     * Get all active handlers
     * @return array
     */
    public function get_active_handlers() {
        $active = array();
        foreach ($this->handlers as $id => $handler) {
            if ($handler->is_active()) {
                $active[$id] = $handler;
            }
        }
        return $active;
    }
    
    /**
     * Get all forms from all active handlers
     * @return array
     */
    public function get_all_forms() {
        $all_forms = array();
        
        foreach ($this->get_active_handlers() as $handler_id => $handler) {
            $forms = $handler->get_forms();
            foreach ($forms as $form) {
                $all_forms[] = array(
                    'handler_id' => $handler_id,
                    'handler_name' => $handler->get_name(),
                    'form_id' => $form['id'],
                    'form_title' => $form['title']
                );
            }
        }
        
        return $all_forms;
    }
    
    /**
     * Debug method to get information about the manager state
     * @return array
     */
    public function get_debug_info() {
        $debug = array(
            'total_handlers' => count($this->handlers),
            'active_handlers' => count($this->get_active_handlers()),
            'configured_forms' => count(get_option('crqa_forms_config', array())),
            'handlers' => array()
        );
        
        foreach ($this->handlers as $id => $handler) {
            $debug['handlers'][$id] = array(
                'name' => $handler->get_name(),
                'active' => $handler->is_active(),
                'forms_count' => count($handler->get_forms())
            );
        }
        
        return $debug;
    }
}

/**
 * Get the form manager instance
 * @return CRQA_Form_Manager
 */
function crqa_form_manager() {
    return CRQA_Form_Manager::get_instance();
}

/**
 * Debug function to display form manager information
 * Only available for administrators
 */
function crqa_debug_form_manager() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $manager = crqa_form_manager();
    $debug_info = $manager->get_debug_info();
    
    echo '<div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border: 1px solid #ccc;">';
    echo '<h4>CRQA Form Manager Debug Info</h4>';
    echo '<pre>' . print_r($debug_info, true) . '</pre>';
    echo '</div>';
}

// Add debug info to admin pages if debug is enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_footer', function() {
        if (isset($_GET['crqa_debug']) && current_user_can('manage_options')) {
            crqa_debug_form_manager();
        }
    });
}

?>