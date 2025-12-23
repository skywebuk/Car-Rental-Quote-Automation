<?php
/**
 * Rewrite Rules and Template Module for Car Rental Quote Automation
 * 
 * This file handles URL rewriting and template redirection for quotes
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/rewrite-rules.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add rewrite rules for quote pages
 */
add_action('init', 'crqa_add_rewrite_rules');
function crqa_add_rewrite_rules() {
    add_rewrite_rule('^quote/([a-zA-Z0-9]+)/?', 'index.php?quote_hash=$matches[1]', 'top');
}

/**
 * Add query vars
 */
add_filter('query_vars', 'crqa_add_query_vars');
function crqa_add_query_vars($vars) {
    $vars[] = 'quote_hash';
    return $vars;
}

/**
 * Handle quote page display
 */
add_action('template_redirect', 'crqa_quote_page_template');
function crqa_quote_page_template() {
    $quote_hash = get_query_var('quote_hash');
    
    if (!$quote_hash) return;
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE quote_hash = %s", $quote_hash));
    
    if (!$quote) {
        wp_die('Quote not found');
    }
    
    // Store quote data for shortcodes
    $GLOBALS['crqa_current_quote'] = $quote;
    
    // Always redirect to the custom page with quote hash as parameter
    $quote_page_id = get_option('crqa_quote_page_id', '');
    
    if ($quote_page_id) {
        // Redirect to the custom page with quote hash as parameter
        wp_redirect(add_query_arg('quote', $quote_hash, get_permalink($quote_page_id)));
        exit;
    } else {
        wp_die('Quote page not configured. Please set up a quote page in the plugin settings.');
    }
}

/**
 * Flush rewrite rules on activation
 */
function crqa_flush_rewrite_rules() {
    crqa_add_rewrite_rules();
    flush_rewrite_rules();
}