<?php
/**
 * Plugin Name: Car Rental Quote Automation
 * Plugin URI: https://skywebdesign.co.uk/
 * Description: Automates car rental quote workflow with multiple form support, email notifications, and WooCommerce integration
 * Version: 2.3.0
 * Author: Sky Web Design
 * License: GPL v2 or later
 * Text Domain: car-rental-quote-automation
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 9.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
final class CarRentalQuoteAutomation {
    
    /**
     * Plugin version
     * @var string
     */
    const VERSION = '2.3.0';
    
    /**
     * Database version
     * @var string
     */
    const DB_VERSION = '1.2';
    
    /**
     * Minimum PHP version required
     * @var string
     */
    const MIN_PHP_VERSION = '7.2';
    
    /**
     * Minimum WordPress version required
     * @var string
     */
    const MIN_WP_VERSION = '5.0';
    
    /**
     * Plugin instance
     * @var CarRentalQuoteAutomation
     */
    private static $instance = null;
    
    /**
     * Plugin path
     * @var string
     */
    private $plugin_path;
    
    /**
     * Plugin URL
     * @var string
     */
    private $plugin_url;
    
    /**
     * Plugin basename
     * @var string
     */
    private $plugin_basename;
    
    /**
     * Loaded modules
     * @var array
     */
    private $loaded_modules = array();
    
    /**
     * Get plugin instance
     * @return CarRentalQuoteAutomation
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->check_requirements();
        $this->init_hooks();
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_basename = plugin_basename(__FILE__);
        
        // Define constants for backward compatibility
        define('CRQA_VERSION', self::VERSION);
        define('CRQA_PLUGIN_PATH', $this->plugin_path);
        define('CRQA_PLUGIN_URL', $this->plugin_url);
        define('CRQA_PLUGIN_BASENAME', $this->plugin_basename);
    }
    
    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], self::MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
        
        // Plugin loaded hook
        add_action('plugins_loaded', array($this, 'load_plugin'));
        
        // Initialize hook
        add_action('init', array($this, 'init'));
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'check_database_updates'));
        }
        
        // Plugin action links
        add_filter('plugin_action_links_' . $this->plugin_basename, array($this, 'add_action_links'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements
        if (!$this->check_requirements()) {
            wp_die('Plugin requirements not met. Please check PHP and WordPress versions.');
        }
        
        // Create directories
        $this->create_directories();
        
        // Create database tables
        $this->create_database_tables();
        
        // Create default product if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $this->load_module('woocommerce-integration');
            if (function_exists('crqa_create_rental_product')) {
                crqa_create_rental_product();
            }
        }
        
        // Set default options
        $this->set_default_options();
        
        // Create asset files
        $this->create_asset_files();
        
        // Flush rewrite rules
        $this->load_module('rewrite-rules');
        if (function_exists('crqa_flush_rewrite_rules')) {
            crqa_flush_rewrite_rules();
        }
        
        // Set database version
        update_option('crqa_db_version', self::DB_VERSION);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear scheduled events
        $this->clear_scheduled_events();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        // Only run if explicitly uninstalling
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Get option to check if data should be deleted
        $delete_data = get_option('crqa_delete_data_on_uninstall', false);
        
        if ($delete_data) {
            // Delete database tables
            global $wpdb;
            $table_name = $wpdb->prefix . 'car_rental_quotes';
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
            
            // Delete options
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'crqa_%'");
            
            // Delete rental product
            $product_id = get_option('crqa_rental_product_id');
            if ($product_id) {
                wp_delete_post($product_id, true);
            }
        }
    }
    
    /**
     * Load plugin
     */
    public function load_plugin() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_notice'));
        }
        
        // Load core modules
        $this->load_core_modules();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize form manager if available
        if (function_exists('crqa_form_manager')) {
            crqa_form_manager();
        }
    }
    
    /**
     * Load core modules
     */
    private function load_core_modules() {
    $core_modules = array(
        'quote-helpers',
        'quote-shared-functions',        // Load shared functions early (before modules that use them)
        'shortcodes',
        'woocommerce-integration',
        'rewrite-rules',
        'class-form-manager',
        'email-settings-admin',
        'email-settings-customer',
        'quotes-list',
        'quotes-edit',
        'settings-enhanced',
        'payment-options-shortcode',
        'activity-tracking'
    );
        
        foreach ($core_modules as $module) {
            $this->load_module($module);
        }
        
        // Load form handlers
        $this->load_form_handlers();
    }
    
    /**
     * Load a specific module
     * @param string $module Module name
     * @return bool
     */
    private function load_module($module) {
        // Check if already loaded
        if (in_array($module, $this->loaded_modules)) {
            return true;
        }
        
        $file_path = $this->plugin_path . 'includes/' . $module . '.php';
        
        if (file_exists($file_path)) {
            try {
                require_once $file_path;
                $this->loaded_modules[] = $module;
                return true;
            } catch (Exception $e) {
                error_log('Car Rental Quote Automation: Failed to load module: ' . $module . ' - ' . $e->getMessage());
                return false;
            }
        } else {
            error_log('Car Rental Quote Automation: Module file not found: ' . $module);
            return false;
        }
    }
    
    /**
     * Load form handlers
     */
    private function load_form_handlers() {
        $handlers_dir = $this->plugin_path . 'includes/form-handlers/';
        
        // Load interface first
        if (file_exists($handlers_dir . 'class-form-handler-interface.php')) {
            require_once $handlers_dir . 'class-form-handler-interface.php';
        }
        
        // Load all handler files
        $handler_files = glob($handlers_dir . 'class-*-handler.php');
        if ($handler_files) {
            foreach ($handler_files as $file) {
                require_once $file;
            }
        }
    }
    
    /**
     * Create required directories
     */
    private function create_directories() {
        $directories = array(
            $this->plugin_path . 'assets',
            $this->plugin_path . 'assets/css',
            $this->plugin_path . 'assets/js',
            $this->plugin_path . 'assets/img',
            $this->plugin_path . 'includes',
            $this->plugin_path . 'includes/form-handlers'
        );
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'car_rental_quotes';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(50),
            vehicle_name varchar(255) NOT NULL,
            vehicle_details text,
            product_id int,
            rental_dates varchar(255),
            rental_price decimal(10,2),
            mileage_allowance varchar(100),
            deposit_amount decimal(10,2),
            delivery_option varchar(255),
            additional_notes text,
            quote_status varchar(50) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            quote_hash varchar(32) UNIQUE,
            form_handler varchar(50),
            form_entry_id int,
            form_type varchar(50) DEFAULT 'standard',
            wpforms_entry_id int,
            ip_address varchar(45),
            user_agent text,
            referrer_url text,
            PRIMARY KEY  (id),
            KEY customer_email (customer_email),
            KEY quote_status (quote_status),
            KEY form_handler (form_handler),
            KEY form_type (form_type),
            KEY product_id (product_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Check and run database updates
     */
    public function check_database_updates() {
        $current_db_version = get_option('crqa_db_version', '1.0');
        
        if (version_compare($current_db_version, self::DB_VERSION, '<')) {
            // Run update migrations
            $this->run_database_migrations($current_db_version);
            update_option('crqa_db_version', self::DB_VERSION);
        }
    }
    
    /**
     * Run database migrations
     * @param string $from_version
     */
    private function run_database_migrations($from_version) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'car_rental_quotes';

        error_log('Car Rental Quote Automation: Running migrations from version ' . $from_version);

        // Version 1.1 - Add product_id column
        if (version_compare($from_version, '1.1', '<')) {
            error_log('Car Rental Quote Automation: Running migration for version 1.1');
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'product_id'");
            if (empty($column_exists)) {
                $result = $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN product_id INT NULL AFTER vehicle_details");
                if ($result === false) {
                    error_log('Car Rental Quote Automation: Failed to add product_id column - ' . $wpdb->last_error);
                }
                // Check if index exists before adding
                $index_exists = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = 'idx_product_id'");
                if (empty($index_exists)) {
                    $wpdb->query("ALTER TABLE {$table_name} ADD INDEX idx_product_id (product_id)");
                }
            }
        }

        // Version 1.2 - Add tracking columns
        if (version_compare($from_version, '1.2', '<')) {
            error_log('Car Rental Quote Automation: Running migration for version 1.2');
            $columns_to_add = array(
                'updated_at' => "ALTER TABLE {$table_name} ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
                'ip_address' => "ALTER TABLE {$table_name} ADD COLUMN ip_address varchar(45) NULL",
                'user_agent' => "ALTER TABLE {$table_name} ADD COLUMN user_agent text NULL",
                'referrer_url' => "ALTER TABLE {$table_name} ADD COLUMN referrer_url text NULL"
            );

            foreach ($columns_to_add as $column => $query) {
                $column_exists = $wpdb->get_results($wpdb->prepare(
                    "SHOW COLUMNS FROM {$table_name} LIKE %s",
                    $column
                ));
                if (empty($column_exists)) {
                    $result = $wpdb->query($query);
                    if ($result === false) {
                        error_log('Car Rental Quote Automation: Failed to add column ' . $column . ' - ' . $wpdb->last_error);
                    }
                }
            }

            // Add indexes only if they don't exist
            $indexes_to_add = array(
                'idx_customer_email' => 'customer_email',
                'idx_created_at' => 'created_at'
            );

            foreach ($indexes_to_add as $index_name => $column_name) {
                $index_exists = $wpdb->get_results($wpdb->prepare(
                    "SHOW INDEX FROM {$table_name} WHERE Key_name = %s",
                    $index_name
                ));
                if (empty($index_exists)) {
                    $result = $wpdb->query("ALTER TABLE {$table_name} ADD INDEX {$index_name} ({$column_name})");
                    if ($result === false) {
                        error_log('Car Rental Quote Automation: Failed to add index ' . $index_name . ' - ' . $wpdb->last_error);
                    }
                }
            }
        }

        error_log('Car Rental Quote Automation: Migrations completed');
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'crqa_company_name' => get_bloginfo('name'),
            'crqa_company_email' => get_option('admin_email'),
            'crqa_admin_emails' => array(get_option('admin_email')),
            'crqa_email_from_name' => get_bloginfo('name'),
            'crqa_email_from_address' => get_option('admin_email')
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
    
    /**
     * Create asset files if they don't exist
     */
    private function create_asset_files() {
        // CSS files
        $css_files = array(
            'assets/css/quotes-admin.css' => '/* Car Rental Quotes Admin Styles */',
            'assets/css/quotes-admin-custom.css' => '/* Car Rental Quotes Custom Admin Styles */',
            'assets/css/quotes-frontend.css' => '/* Car Rental Quotes Frontend Styles */'
        );

        foreach ($css_files as $file => $content) {
            $file_path = $this->plugin_path . $file;
            if (!file_exists($file_path)) {
                $result = @file_put_contents($file_path, $content);
                if ($result === false) {
                    error_log('Car Rental Quote Automation: Failed to create CSS file: ' . $file_path);
                }
            }
        }

        // JS files
        $js_files = array(
            'assets/js/quotes-admin.js' => '/* Car Rental Quotes Admin Scripts */',
            'assets/js/forms-settings.js' => '/* Car Rental Quotes Form Settings Scripts */',
            'assets/js/quotes-frontend.js' => '/* Car Rental Quotes Frontend Scripts */'
        );

        foreach ($js_files as $file => $content) {
            $file_path = $this->plugin_path . $file;
            if (!file_exists($file_path)) {
                $result = @file_put_contents($file_path, $content);
                if ($result === false) {
                    error_log('Car Rental Quote Automation: Failed to create JS file: ' . $file_path);
                }
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Car Rental Quotes', 'car-rental-quote-automation'),
            __('Rental Quotes', 'car-rental-quote-automation'),
            'manage_options',
            'car-rental-quotes',
            array($this, 'admin_page'),
            'dashicons-car',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'car-rental-quotes',
            __('Settings', 'car-rental-quote-automation'),
            __('Settings', 'car-rental-quote-automation'),
            'manage_options',
            'crqa-settings',
            array($this, 'settings_page')
        );
        
        // Tools submenu
        add_submenu_page(
            'car-rental-quotes',
            __('Tools', 'car-rental-quote-automation'),
            __('Tools', 'car-rental-quote-automation'),
            'manage_options',
            'crqa-tools',
            array($this, 'tools_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        if (function_exists('crqa_admin_page')) {
            crqa_admin_page();
        } else {
            echo '<div class="wrap"><h1>Error loading admin page</h1></div>';
        }
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        if (function_exists('crqa_settings_page_enhanced')) {
            crqa_settings_page_enhanced();
        } else {
            echo '<div class="wrap"><h1>Error loading settings page</h1></div>';
        }
    }
    
    /**
     * Tools page
     */
    public function tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Car Rental Quote Tools', 'car-rental-quote-automation'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Export/Import', 'car-rental-quote-automation'); ?></h2>
                <p><?php _e('Export quotes to CSV or import from file.', 'car-rental-quote-automation'); ?></p>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=crqa-tools&action=export'), 'crqa_export'); ?>" class="button">
                        <?php _e('Export All Quotes', 'car-rental-quote-automation'); ?>
                    </a>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('Database Maintenance', 'car-rental-quote-automation'); ?></h2>
                <p><?php _e('Optimize database tables and clean up old data.', 'car-rental-quote-automation'); ?></p>
                <p>
                    <button class="button" onclick="if(confirm('<?php esc_attr_e('Are you sure?', 'car-rental-quote-automation'); ?>')) window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=crqa-tools&action=optimize'), 'crqa_optimize'); ?>'">
                        <?php _e('Optimize Database', 'car-rental-quote-automation'); ?>
                    </button>
                </p>
            </div>
            
            <div class="card">
                <h2><?php _e('System Information', 'car-rental-quote-automation'); ?></h2>
                <p><strong><?php _e('Plugin Version:', 'car-rental-quote-automation'); ?></strong> <?php echo self::VERSION; ?></p>
                <p><strong><?php _e('Database Version:', 'car-rental-quote-automation'); ?></strong> <?php echo get_option('crqa_db_version', '1.0'); ?></p>
                <p><strong><?php _e('PHP Version:', 'car-rental-quote-automation'); ?></strong> <?php echo PHP_VERSION; ?></p>
                <p><strong><?php _e('WordPress Version:', 'car-rental-quote-automation'); ?></strong> <?php echo $GLOBALS['wp_version']; ?></p>
                <p><strong><?php _e('WooCommerce:', 'car-rental-quote-automation'); ?></strong> <?php echo class_exists('WooCommerce') ? 'Active' : 'Not Active'; ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add plugin action links
     * @param array $links
     * @return array
     */
    public function add_action_links($links) {
        $action_links = array(
            '<a href="' . admin_url('admin.php?page=crqa-settings') . '">' . __('Settings', 'car-rental-quote-automation') . '</a>',
            '<a href="' . admin_url('admin.php?page=car-rental-quotes') . '">' . __('Quotes', 'car-rental-quote-automation') . '</a>'
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Clear scheduled events
     */
    private function clear_scheduled_events() {
        wp_clear_scheduled_hook('crqa_daily_maintenance');
        wp_clear_scheduled_hook('crqa_hourly_checks');
    }
    
    /**
     * Admin notices
     */
    public function php_version_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php printf(__('Car Rental Quote Automation requires PHP %s or higher. Your version is %s.', 'car-rental-quote-automation'), self::MIN_PHP_VERSION, PHP_VERSION); ?></p>
        </div>
        <?php
    }
    
    public function wp_version_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php printf(__('Car Rental Quote Automation requires WordPress %s or higher. Your version is %s.', 'car-rental-quote-automation'), self::MIN_WP_VERSION, $GLOBALS['wp_version']); ?></p>
        </div>
        <?php
    }
    
    public function woocommerce_notice() {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Car Rental Quote Automation works best with WooCommerce. Some features may be limited without it.', 'car-rental-quote-automation'); ?></p>
            <p><a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>" class="button button-primary"><?php _e('Install WooCommerce', 'car-rental-quote-automation'); ?></a></p>
        </div>
        <?php
    }
}

/**
 * Declare WooCommerce HPOS and Blocks compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare HPOS (High-Performance Order Storage) compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );

        // Declare Cart and Checkout Blocks compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
});

// Initialize plugin
CarRentalQuoteAutomation::get_instance();