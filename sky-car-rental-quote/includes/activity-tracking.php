<?php
/**
 * Customer Activity Tracking Module for Car Rental Quote Automation
 * 
 * This file handles tracking customer interactions with quotes
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/activity-tracking.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize activity tracking
 */
add_action('init', 'crqa_init_activity_tracking');
function crqa_init_activity_tracking() {
    // Track quote page views
    add_action('template_redirect', 'crqa_track_quote_view');
    
    // Track checkout initiation
    add_action('woocommerce_add_to_cart', 'crqa_track_checkout_start', 10, 6);
    
    // Track payment completion
    add_action('woocommerce_order_status_completed', 'crqa_track_payment_completion');
    add_action('woocommerce_order_status_processing', 'crqa_track_payment_completion');
    
    // Create tracking table on activation
    register_activation_hook(__FILE__, 'crqa_create_activity_tracking_table');
}

/**
 * Create activity tracking table
 */
function crqa_create_activity_tracking_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        quote_id mediumint(9) NOT NULL,
        activity_type varchar(50) NOT NULL,
        activity_time datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45),
        user_agent text,
        referrer_url text,
        extra_data text,
        PRIMARY KEY (id),
        KEY quote_id (quote_id),
        KEY activity_type (activity_type),
        KEY activity_time (activity_time)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Track quote page view
 */
function crqa_track_quote_view() {
    // Check if we're on a quote page
    $quote_hash = '';
    if (isset($_GET['quote'])) {
        $quote_hash = sanitize_text_field($_GET['quote']);
    } elseif (get_query_var('quote_hash')) {
        $quote_hash = sanitize_text_field(get_query_var('quote_hash'));
    }
    
    if (!$quote_hash) {
        return;
    }
    
    // Get quote ID from hash
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';
    $quote_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE quote_hash = %s",
        $quote_hash
    ));
    
    if (!$quote_id) {
        return;
    }
    
    // Check if this is a unique view (not refreshing)
    // Use IP + User Agent hash for better uniqueness
    $visitor_hash = md5(crqa_get_user_ip() . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
    $last_view_key = 'crqa_viewed_' . $quote_id . '_' . $visitor_hash;
    $last_view = get_transient($last_view_key);

    if (!$last_view) {
        // Record the view only if it's unique within the time window
        crqa_record_activity($quote_id, 'quote_viewed', array(
            'page_url' => crqa_get_current_url(),
            'is_unique' => true
        ));

        // Set transient to prevent duplicate tracking for 30 minutes
        set_transient($last_view_key, true, 30 * MINUTE_IN_SECONDS);
    }
    // Don't record repeat views within the time window to prevent database bloat
}

/**
 * Track checkout start
 */
function crqa_track_checkout_start($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Check if this is a quote-related cart addition
    if (isset($cart_item_data['crqa_quote_id'])) {
        $quote_id = intval($cart_item_data['crqa_quote_id']);
        
        crqa_record_activity($quote_id, 'checkout_started', array(
            'payment_option' => $cart_item_data['crqa_payment_option'] ?? '',
            'payment_amount' => $cart_item_data['crqa_payment_amount'] ?? 0
        ));
    }
}

/**
 * Track payment completion
 */
function crqa_track_payment_completion($order_id) {
    $order = wc_get_order($order_id);
    
    foreach ($order->get_items() as $item) {
        $quote_id = $item->get_meta('_crqa_quote_id');
        
        if ($quote_id) {
            crqa_record_activity($quote_id, 'payment_completed', array(
                'order_id' => $order_id,
                'order_total' => $order->get_total(),
                'payment_method' => $order->get_payment_method()
            ));
            break;
        }
    }
}

/**
 * Record activity in database
 */
function crqa_record_activity($quote_id, $activity_type, $extra_data = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    // Ensure table exists
    crqa_create_activity_tracking_table();
    
    $data = array(
        'quote_id' => intval($quote_id),
        'activity_type' => sanitize_text_field($activity_type),
        'activity_time' => current_time('mysql'),
        'ip_address' => crqa_get_user_ip(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
        'extra_data' => !empty($extra_data) ? json_encode($extra_data) : null
    );
    
    $wpdb->insert($table_name, $data);
}

/**
 * Get user IP address
 */
function crqa_get_user_ip() {
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
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                return $ip;
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
}

/**
 * Get current URL
 */
function crqa_get_current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return $protocol . '://' . $host . $uri;
}

/**
 * AJAX endpoint to track activities from JavaScript
 */
add_action('wp_ajax_crqa_track_activity', 'crqa_ajax_track_activity');
add_action('wp_ajax_nopriv_crqa_track_activity', 'crqa_ajax_track_activity');
function crqa_ajax_track_activity() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'crqa_activity_tracking')) {
        wp_die('Security check failed');
    }
    
    $quote_id = intval($_POST['quote_id']);
    $activity_type = sanitize_text_field($_POST['activity_type']);
    $extra_data = isset($_POST['extra_data']) ? $_POST['extra_data'] : array();
    
    if ($quote_id && $activity_type) {
        crqa_record_activity($quote_id, $activity_type, $extra_data);
        wp_send_json_success();
    } else {
        wp_send_json_error('Invalid data');
    }
}

/**
 * Add tracking JavaScript to quote pages
 */
add_action('wp_footer', 'crqa_add_tracking_script');
function crqa_add_tracking_script() {
    // Only add on quote pages
    $quote = crqa_get_current_quote();
    if (!$quote) {
        return;
    }
    
    ?>
    <script>
    (function() {
        // Track time on page
        var startTime = new Date().getTime();
        var quote_id = <?php echo intval($quote->id); ?>;
        
        // Track when user leaves the page
        window.addEventListener('beforeunload', function() {
            var timeOnPage = Math.round((new Date().getTime() - startTime) / 1000);
            
            // Send tracking data
            if (navigator.sendBeacon) {
                var formData = new FormData();
                formData.append('action', 'crqa_track_activity');
                formData.append('quote_id', quote_id);
                formData.append('activity_type', 'page_exit');
                formData.append('extra_data[time_on_page]', timeOnPage);
                formData.append('nonce', '<?php echo wp_create_nonce('crqa_activity_tracking'); ?>');
                
                navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', formData);
            }
        });
        
        // Track scroll depth
        var maxScroll = 0;
        var documentHeight = document.documentElement.scrollHeight - window.innerHeight;
        
        window.addEventListener('scroll', function() {
            var scrollPercent = Math.round((window.scrollY / documentHeight) * 100);
            if (scrollPercent > maxScroll) {
                maxScroll = scrollPercent;
            }
        });
        
        // Send scroll depth when leaving
        window.addEventListener('beforeunload', function() {
            if (maxScroll > 0 && navigator.sendBeacon) {
                var formData = new FormData();
                formData.append('action', 'crqa_track_activity');
                formData.append('quote_id', quote_id);
                formData.append('activity_type', 'scroll_tracking');
                formData.append('extra_data[max_scroll_depth]', maxScroll);
                formData.append('nonce', '<?php echo wp_create_nonce('crqa_activity_tracking'); ?>');
                
                navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', formData);
            }
        });
    })();
    </script>
    <?php
}

/**
 * Get activity analytics for a quote
 */
function crqa_get_quote_analytics($quote_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    $analytics = array(
        'engagement_score' => 0,
        'average_time_on_page' => 0,
        'average_scroll_depth' => 0,
        'device_breakdown' => array(),
        'traffic_sources' => array()
    );
    
    // Get all activities for this quote
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE quote_id = %d",
        $quote_id
    ));
    
    if (!$activities) {
        return $analytics;
    }
    
    // Calculate engagement score
    $score = 0;
    $time_on_page_total = 0;
    $time_on_page_count = 0;
    $scroll_depth_total = 0;
    $scroll_depth_count = 0;
    
    foreach ($activities as $activity) {
        // Base score for different activities
        switch ($activity->activity_type) {
            case 'quote_viewed':
                $score += 10;
                break;
            case 'checkout_started':
                $score += 50;
                break;
            case 'payment_completed':
                $score += 100;
                break;
        }
        
        // Parse extra data
        if ($activity->extra_data) {
            $extra = json_decode($activity->extra_data, true);
            
            if (isset($extra['time_on_page'])) {
                $time_on_page_total += intval($extra['time_on_page']);
                $time_on_page_count++;
            }
            
            if (isset($extra['max_scroll_depth'])) {
                $scroll_depth_total += intval($extra['max_scroll_depth']);
                $scroll_depth_count++;
            }
        }
        
        // Device breakdown
        if ($activity->user_agent) {
            $device_type = crqa_detect_device_type($activity->user_agent);
            if (!isset($analytics['device_breakdown'][$device_type])) {
                $analytics['device_breakdown'][$device_type] = 0;
            }
            $analytics['device_breakdown'][$device_type]++;
        }
        
        // Traffic sources
        if ($activity->referrer_url) {
            $source = crqa_get_traffic_source($activity->referrer_url);
            if (!isset($analytics['traffic_sources'][$source])) {
                $analytics['traffic_sources'][$source] = 0;
            }
            $analytics['traffic_sources'][$source]++;
        }
    }
    
    $analytics['engagement_score'] = min(100, $score);
    $analytics['average_time_on_page'] = $time_on_page_count > 0 ? round($time_on_page_total / $time_on_page_count) : 0;
    $analytics['average_scroll_depth'] = $scroll_depth_count > 0 ? round($scroll_depth_total / $scroll_depth_count) : 0;
    
    return $analytics;
}

/**
 * Detect device type from user agent
 */
function crqa_detect_device_type($user_agent) {
    $user_agent = strtolower($user_agent);
    
    if (strpos($user_agent, 'mobile') !== false) {
        return 'Mobile';
    } elseif (strpos($user_agent, 'tablet') !== false || strpos($user_agent, 'ipad') !== false) {
        return 'Tablet';
    } else {
        return 'Desktop';
    }
}

/**
 * Get traffic source from referrer
 */
function crqa_get_traffic_source($referrer_url) {
    if (empty($referrer_url)) {
        return 'Direct';
    }
    
    $domain = parse_url($referrer_url, PHP_URL_HOST);
    
    // Check for known social media
    $social_domains = array(
        'facebook.com' => 'Facebook',
        'instagram.com' => 'Instagram',
        'twitter.com' => 'Twitter',
        'linkedin.com' => 'LinkedIn',
        'youtube.com' => 'YouTube',
        't.co' => 'Twitter'
    );
    
    foreach ($social_domains as $social_domain => $social_name) {
        if (strpos($domain, $social_domain) !== false) {
            return $social_name;
        }
    }
    
    // Check for search engines
    $search_domains = array(
        'google.' => 'Google',
        'bing.' => 'Bing',
        'yahoo.' => 'Yahoo',
        'duckduckgo.' => 'DuckDuckGo'
    );
    
    foreach ($search_domains as $search_domain => $search_name) {
        if (strpos($domain, $search_domain) !== false) {
            return $search_name . ' Search';
        }
    }
    
    // Check if it's from the same site
    $site_domain = parse_url(home_url(), PHP_URL_HOST);
    if ($domain === $site_domain) {
        return 'Internal';
    }
    
    // Otherwise, return the domain
    return $domain ?: 'Unknown';
}

/**
 * Clean up old activity data (optional, run via cron)
 */
function crqa_cleanup_old_activities() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quote_activity';
    
    // Delete activities older than 90 days
    $wpdb->query(
        "DELETE FROM {$table_name} 
        WHERE activity_time < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );
}

// Schedule cleanup
add_action('wp', 'crqa_schedule_activity_cleanup');
function crqa_schedule_activity_cleanup() {
    if (!wp_next_scheduled('crqa_cleanup_activities')) {
        wp_schedule_event(time(), 'daily', 'crqa_cleanup_activities');
    }
}
add_action('crqa_cleanup_activities', 'crqa_cleanup_old_activities');