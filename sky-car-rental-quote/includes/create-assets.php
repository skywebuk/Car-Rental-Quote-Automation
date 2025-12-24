<?php
/**
 * Asset Creation Module for Car Rental Quote Automation
 * 
 * This file handles the creation of CSS and JS files
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/create-assets.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create asset files if they don't exist
 */
function crqa_create_asset_files() {
    // Ensure directories exist
    $css_dir = CRQA_PLUGIN_PATH . 'assets/css/';
    $js_dir = CRQA_PLUGIN_PATH . 'assets/js/';
    
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    
    if (!file_exists($js_dir)) {
        wp_mkdir_p($js_dir);
    }
    
    // Create empty files if they don't exist
    $css_file = $css_dir . 'quotes-admin.css';
    $js_file = $js_dir . 'quotes-admin.js';
    
    if (!file_exists($css_file)) {
        // Create empty CSS file
        $result = @file_put_contents($css_file, '/* Car Rental Quotes Admin Styles */');
        if ($result === false) {
            error_log('Car Rental Quote Automation: Failed to create CSS file: ' . $css_file);
        }
    }

    if (!file_exists($js_file)) {
        // Create empty JS file
        $result = @file_put_contents($js_file, '/* Car Rental Quotes Admin Scripts */');
        if ($result === false) {
            error_log('Car Rental Quote Automation: Failed to create JS file: ' . $js_file);
        }
    }
}