<?php
/**
 * Quote Helper Functions for Car Rental Quote Automation
 * 
 * This file contains helper functions for quotes and products
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/quote-helpers.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to get current quote
 * Uses static variable to prevent global variable pollution from other plugins
 */
function crqa_get_current_quote() {
    // Use static variable instead of $GLOBALS to prevent pollution from other plugins
    static $cached_quote = null;
    static $cache_initialized = false;

    // Check if quote is already loaded in static cache
    if ($cache_initialized) {
        return $cached_quote;
    }

    // Try to get quote from URL parameter
    $quote_hash = isset($_GET['quote']) ? sanitize_text_field($_GET['quote']) : '';

    if (!$quote_hash) {
        $cache_initialized = true;
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';

    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE quote_hash = %s", $quote_hash));

    // Cache the result
    $cached_quote = $quote;
    $cache_initialized = true;

    // Also set global for backwards compatibility with any code that reads it directly
    if ($quote) {
        $GLOBALS['crqa_current_quote'] = $quote;
    }

    return $quote;
}

/**
 * Helper function to find WooCommerce product by vehicle name
 */
function crqa_find_matching_product($vehicle_name) {
    if (!function_exists('wc_get_products')) {
        return null;
    }
    
    // Strategy 1: Direct title match
    $products = wc_get_products(array(
        'name' => $vehicle_name,
        'limit' => 1,
        'status' => 'publish'
    ));
    
    if (!empty($products)) {
        return $products[0];
    }
    
    // Strategy 2: Partial title match
    $products = wc_get_products(array(
        'limit' => -1,
        'status' => 'publish'
    ));
    
    foreach ($products as $product) {
        $product_title = $product->get_name();
        
        // Check if vehicle name contains product title or vice versa
        if (stripos($vehicle_name, $product_title) !== false || 
            stripos($product_title, $vehicle_name) !== false) {
            return $product;
        }
    }
    
    // Strategy 3: Look in product meta or custom fields
    $products = wc_get_products(array(
        'meta_query' => array(
            array(
                'key' => 'vehicle_name',
                'value' => $vehicle_name,
                'compare' => 'LIKE'
            )
        ),
        'limit' => 1,
        'status' => 'publish'
    ));
    
    if (!empty($products)) {
        return $products[0];
    }
    
    return null;
}

/**
 * Helper function to get product attribute value
 */
function crqa_get_product_attribute($product_id, $attribute_name) {
    if (!function_exists('wc_get_product')) {
        return '';
    }
    
    $product = wc_get_product($product_id);
    if (!$product) {
        return '';
    }
    
    // Method 1: Try to get as product attribute (this gets the actual value, not slug)
    $attributes = $product->get_attributes();
    
    // Check for exact match first
    if (isset($attributes[$attribute_name])) {
        $attribute = $attributes[$attribute_name];
        if ($attribute->is_taxonomy()) {
            // For taxonomy attributes, get the term names
            $terms = wp_get_post_terms($product_id, $attribute->get_name());
            $values = array();
            foreach ($terms as $term) {
                $values[] = $term->name;
            }
            return implode(', ', $values);
        } else {
            // For custom attributes, get the options
            $options = $attribute->get_options();
            return is_array($options) ? implode(', ', $options) : $options;
        }
    }
    
    // Method 2: Try with pa_ prefix for taxonomy attributes
    $taxonomy_name = 'pa_' . $attribute_name;
    if (isset($attributes[$taxonomy_name])) {
        $terms = wp_get_post_terms($product_id, $taxonomy_name);
        $values = array();
        foreach ($terms as $term) {
            $values[] = $term->name;
        }
        return implode(', ', $values);
    }
    
    // Method 3: Check for attribute with underscores converted to hyphens
    $hyphenated_name = str_replace('_', '-', $attribute_name);
    $taxonomy_hyphenated = 'pa_' . $hyphenated_name;
    
    if (isset($attributes[$taxonomy_hyphenated])) {
        $terms = wp_get_post_terms($product_id, $taxonomy_hyphenated);
        $values = array();
        foreach ($terms as $term) {
            $values[] = $term->name;
        }
        return implode(', ', $values);
    }
    
    // Method 4: Try to get as custom field/meta
    $meta_value = get_post_meta($product_id, $attribute_name, true);
    if ($meta_value) {
        return $meta_value;
    }
    
    // Method 5: Try with underscore prefix (common in WooCommerce)
    $meta_value = get_post_meta($product_id, '_' . $attribute_name, true);
    if ($meta_value) {
        return $meta_value;
    }
    
    // Method 6: Search through all attributes to find a match (case-insensitive)
    foreach ($attributes as $attr_key => $attribute) {
        // Remove pa_ prefix and normalize
        $clean_key = str_replace('pa_', '', $attr_key);
        $clean_key = str_replace(['-', '_'], '', $clean_key);
        $clean_search = str_replace(['-', '_'], '', $attribute_name);
        
        if (strtolower($clean_key) === strtolower($clean_search)) {
            if ($attribute->is_taxonomy()) {
                $terms = wp_get_post_terms($product_id, $attribute->get_name());
                $values = array();
                foreach ($terms as $term) {
                    $values[] = $term->name;
                }
                return implode(', ', $values);
            } else {
                $options = $attribute->get_options();
                return is_array($options) ? implode(', ', $options) : $options;
            }
        }
    }
    
    return '';
}

/**
 * Backfill existing quotes with product IDs
 */
function crqa_backfill_product_ids() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $quotes = $wpdb->get_results("SELECT id, vehicle_name FROM {$table_name} WHERE product_id IS NULL");
    
    foreach ($quotes as $quote) {
        $product = crqa_find_matching_product($quote->vehicle_name);
        
        if ($product) {
            $wpdb->update(
                $table_name,
                array('product_id' => $product->get_id()),
                array('id' => $quote->id)
            );
        }
    }
}