<?php
/**
 * Quote List Management for Car Rental Quote Automation
 * 
 * This file handles the main quotes listing page
 * File location: /wp-content/plugins/car-rental-quote-automation/includes/quotes-list.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include shared functions if not already loaded
if (!function_exists('crqa_format_price')) {
    require_once CRQA_PLUGIN_PATH . 'includes/quote-shared-functions.php';
}

/**
 * Main quotes management page - CLEANED UP
 */
function crqa_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';

    // Validate table name contains only allowed characters (alphanumeric and underscore)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        wp_die('Invalid table name configuration.');
    }
    
    // Check if soft delete columns exist
    $soft_delete_supported = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = 'is_deleted'",
        DB_NAME,
        $table_name
    )) > 0;
    
    // Get current view (all, trash)
    $current_view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'all';
    
    // Handle actions
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    $quote_id = isset($_GET['quote_id']) ? intval($_GET['quote_id']) : 0;
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['quote_ids'])) {
        crqa_handle_bulk_actions();
    }
    
    // Single quote actions
    switch ($action) {
        case 'edit':
            // Load the edit page from separate file
            if (file_exists(CRQA_PLUGIN_PATH . 'includes/quotes-edit.php')) {
                require_once CRQA_PLUGIN_PATH . 'includes/quotes-edit.php';
                crqa_edit_quote_page($quote_id);
            } else {
                wp_die('Edit page file not found. Please check your installation.');
            }
            return;
            
        case 'trash':
            if ($soft_delete_supported && wp_verify_nonce($_GET['_wpnonce'], 'trash_quote_' . $quote_id)) {
                $wpdb->update($table_name, array('is_deleted' => 1, 'deleted_at' => current_time('mysql')), array('id' => $quote_id));
                wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=trashed'));
                exit;
            }
            break;
            
        case 'restore':
            if ($soft_delete_supported && wp_verify_nonce($_GET['_wpnonce'], 'restore_quote_' . $quote_id)) {
                $wpdb->update($table_name, array('is_deleted' => 0, 'deleted_at' => null), array('id' => $quote_id));
                wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=restored'));
                exit;
            }
            break;
            
        case 'delete':
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_quote_' . $quote_id)) {
                $wpdb->delete($table_name, array('id' => $quote_id));
                $redirect_url = ($soft_delete_supported && $current_view === 'trash') ? 
                    admin_url('admin.php?page=car-rental-quotes&view=trash&message=deleted') : 
                    admin_url('admin.php?page=car-rental-quotes&message=deleted');
                wp_redirect($redirect_url);
                exit;
            }
            break;
            
        case 'resend':
            if (wp_verify_nonce($_GET['_wpnonce'], 'resend_quote_' . $quote_id)) {
                if (function_exists('crqa_send_quote_email')) {
                    crqa_send_quote_email($quote_id);
                } else {
                    error_log('CRQA Error: crqa_send_quote_email function not found');
                }
                wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=resent'));
                exit;
            }
            break;
            
        case 'duplicate':
            if (wp_verify_nonce($_GET['_wpnonce'], 'duplicate_quote_' . $quote_id)) {
                crqa_duplicate_quote($quote_id);
                wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=duplicated'));
                exit;
            }
            break;
    }
    
    // Display messages
    if (isset($_GET['message'])) {
        crqa_display_admin_notice($_GET['message']);
    }
    
    // Get filter parameters
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Build query
    $where_clauses = array();
    $where_values = array();
    
    // Add soft delete filter only if columns exist
    if ($soft_delete_supported) {
        if ($current_view === 'trash') {
            $where_clauses[] = "is_deleted = 1";
        } else {
            $where_clauses[] = "(is_deleted = 0 OR is_deleted IS NULL)";
        }
    } else {
        $where_clauses[] = "1=1";
    }
    
    if ($search) {
        // Remove # symbol and leading zeros if searching for quote ID
        $cleaned_search = ltrim(str_replace('#', '', $search), '0');

        // Properly escape the search term for LIKE queries
        $escaped_search = $wpdb->esc_like($search);
        $search_term = '%' . $escaped_search . '%';

        // Check if search term is numeric or starts with # (quote ID)
        if (is_numeric($cleaned_search) || strpos($search, '#') === 0) {
            // Search by quote ID - use PHP formatting instead of SQL LPAD for security
            $numeric_id = intval($cleaned_search);
            $padded_id = str_pad($numeric_id, 5, '0', STR_PAD_LEFT);
            $padded_search_term = '%' . $wpdb->esc_like($padded_id) . '%';

            // Search by ID (exact match) or padded ID pattern, plus text fields
            $where_clauses[] = "(id = %d OR customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR vehicle_name LIKE %s)";
            $where_values[] = $numeric_id;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        } else {
            // Search in all text fields
            $where_clauses[] = "(customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s OR vehicle_name LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
    }
    
    if ($status_filter && $current_view !== 'trash') {
        $where_clauses[] = "quote_status = %s";
        $where_values[] = $status_filter;
    }
    
    if ($date_from) {
        $where_clauses[] = "DATE(created_at) >= %s";
        $where_values[] = $date_from;
    }
    
    if ($date_to) {
        $where_clauses[] = "DATE(created_at) <= %s";
        $where_values[] = $date_to;
    }
    
    $where_sql = implode(' AND ', $where_clauses);

    // Sanitize table name for use in queries (already validated above)
    $safe_table_name = esc_sql($table_name);

    // Get counts for views using prepared statements
    if ($soft_delete_supported) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $all_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_table_name}` WHERE (is_deleted = 0 OR is_deleted IS NULL)");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $trash_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_table_name}` WHERE is_deleted = 1");
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $all_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$safe_table_name}`");
        $trash_count = 0;
    }

    // Get total count with prepared statement
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated, where_sql uses placeholders
    $count_query = "SELECT COUNT(*) FROM `{$safe_table_name}` WHERE {$where_sql}";
    if (!empty($where_values)) {
        $count_query = $wpdb->prepare($count_query, $where_values);
    }
    $total_items = $wpdb->get_var($count_query);

    // Get quotes with prepared statement
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated, where_sql uses placeholders
    $query = "SELECT * FROM `{$safe_table_name}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $query_args = array_merge($where_values, array($per_page, $offset));

    // Always use prepare() for the main query
    $quotes = $wpdb->get_results($wpdb->prepare($query, $query_args));
    
    // Calculate pagination
    $total_pages = ceil($total_items / $per_page);
    
    ?>
    <div class="wrap crqa-wrap">
        <h1 class="wp-heading-inline">Car Rental Quotes</h1>
        
        <hr class="wp-header-end">
        
        <!-- Views -->
        <?php if ($soft_delete_supported): ?>
        <ul class="subsubsub">
            <li class="all">
                <a href="<?php echo admin_url('admin.php?page=car-rental-quotes'); ?>" 
                   class="<?php echo $current_view === 'all' ? 'current' : ''; ?>">
                    All <span class="count">(<?php echo $all_count; ?>)</span>
                </a> |
            </li>
            <li class="trash">
                <a href="<?php echo admin_url('admin.php?page=car-rental-quotes&view=trash'); ?>" 
                   class="<?php echo $current_view === 'trash' ? 'current' : ''; ?>">
                    Trash <span class="count">(<?php echo $trash_count; ?>)</span>
                </a>
            </li>
        </ul>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" action="">
                <input type="hidden" name="page" value="car-rental-quotes">
                <?php if ($soft_delete_supported && $current_view === 'trash'): ?>
                <input type="hidden" name="view" value="trash">
                <?php endif; ?>
                
                <div class="alignleft actions">
                    <?php if ($current_view !== 'trash'): ?>
                    <select name="status" id="filter-by-status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                        <option value="quoted" <?php selected($status_filter, 'quoted'); ?>>Quoted</option>
                        <option value="paid" <?php selected($status_filter, 'paid'); ?>>Paid</option>
                    </select>
                    <?php endif; ?>
                    
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">
                    
                    <input type="submit" class="button" value="Filter">
                    
                    <?php if ($search || $status_filter || $date_from || $date_to): ?>
                        <a href="<?php echo admin_url('admin.php?page=car-rental-quotes' . ($current_view === 'trash' ? '&view=trash' : '')); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </div>
                
                <div class="alignright">
                    <p class="search-box">
                        <label class="screen-reader-text" for="quote-search-input">Search Quotes:</label>
                        <input type="search" id="quote-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search quotes...">
                        <input type="submit" id="search-submit" class="button" value="Search Quotes">
                    </p>
                </div>
            </form>
        
        <!-- Mobile Quote Cards -->
        <div class="crqa-mobile-quotes" style="display: none;">
            <?php if (empty($quotes)): ?>
                <div class="crqa-empty-state">
                    <h3>No quotes found</h3>
                    <p><?php if ($search || $status_filter): ?>Try adjusting your filters to see more results.<?php else: ?>New quotes will appear here once submitted.<?php endif; ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($quotes as $quote): ?>
                    <div class="crqa-quote-card">
                        <input type="checkbox" name="quote_ids[]" value="<?php echo $quote->id; ?>" class="crqa-quote-checkbox">
                        
                        <div class="crqa-quote-header">
                            <div class="crqa-quote-id">#<?php echo str_pad($quote->id, 5, '0', STR_PAD_LEFT); ?></div>
                            <div class="crqa-quote-status status-<?php echo $quote->quote_status; ?>"><?php echo ucfirst($quote->quote_status); ?></div>
                        </div>
                        
                        <div class="crqa-quote-customer"><?php echo esc_html($quote->customer_name); ?></div>
                        
                        <div class="crqa-quote-details">
                            <div class="crqa-quote-detail-row">
                                <span class="crqa-quote-detail-label">Email:</span>
                                <span class="crqa-quote-detail-value">
                                    <a href="mailto:<?php echo esc_attr($quote->customer_email); ?>"><?php echo esc_html($quote->customer_email); ?></a>
                                </span>
                            </div>
                            
                            <?php if ($quote->customer_phone): ?>
                            <div class="crqa-quote-detail-row">
                                <span class="crqa-quote-detail-label">Phone:</span>
                                <span class="crqa-quote-detail-value"><?php echo esc_html($quote->customer_phone); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="crqa-quote-detail-row">
                                <span class="crqa-quote-detail-label">Vehicle:</span>
                                <span class="crqa-quote-detail-value"><?php echo strip_tags(crqa_get_vehicle_display($quote)); ?></span>
                            </div>
                            
                            <?php if ($quote->rental_dates): ?>
                            <div class="crqa-quote-detail-row">
                                <span class="crqa-quote-detail-label">Dates:</span>
                                <span class="crqa-quote-detail-value"><?php echo esc_html($quote->rental_dates); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($quote->rental_price): ?>
                            <div class="crqa-quote-detail-row">
                                <span class="crqa-quote-detail-label">Price:</span>
                                <span class="crqa-quote-detail-value crqa-quote-price">
                                    <?php echo crqa_format_price($quote->rental_price); ?>
                                    <?php if ($quote->deposit_amount): ?>
                                        <br><small style="color: #666; font-weight: normal;">+ <?php echo crqa_format_price($quote->deposit_amount); ?> deposit</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="crqa-quote-detail-row">
                                <span class="crqa-quote-detail-label">Created:</span>
                                <span class="crqa-quote-detail-value"><?php echo date('M j, Y', strtotime($quote->created_at)); ?></span>
                            </div>
                        </div>
                        
                        <div class="crqa-quote-actions <?php echo ($quote->quote_status == 'quoted') ? 'has-three-buttons' : ''; ?>">
                            <?php if ($current_view === 'trash'): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=restore&quote_id=' . $quote->id), 'restore_quote_' . $quote->id); ?>" class="button button-primary">
                                    <i class="fas fa-undo"></i> Restore
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=delete&quote_id=' . $quote->id), 'delete_quote_' . $quote->id); ?>" class="button" onclick="return confirm('Are you sure you want to permanently delete this quote?');">
                                    <i class="fas fa-trash"></i> Delete Permanently
                                </a>
                            <?php else: ?>
                                <a href="<?php echo admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote->id); ?>" class="button button-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="<?php echo home_url('/quote/' . $quote->quote_hash); ?>" target="_blank" class="button">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($quote->quote_status == 'quoted'): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=resend&quote_id=' . $quote->id), 'resend_quote_' . $quote->id); ?>" class="button">
                                        <i class="fas fa-redo"></i> Resend
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('crqa_bulk_action', 'crqa_bulk_action_nonce'); ?>
            <table class="wp-list-table widefat fixed striped crqa-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all">
                        </td>
                        <th class="manage-column column-primary">Quote ID</th>
                        <th class="manage-column">Customer</th>
                        <th class="manage-column">Vehicle</th>
                        <th class="manage-column">Dates</th>
                        <th class="manage-column">Status</th>
                        <th class="manage-column">Price</th>
                        <th class="manage-column column-created">Created</th>
                        <th class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quotes)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                No quotes found. <?php if ($search || $status_filter): ?>Try adjusting your filters.<?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="quote_ids[]" value="<?php echo $quote->id; ?>">
                            </th>
                            <td class="column-primary" data-label="Quote ID">
                                <strong>#<?php echo str_pad($quote->id, 5, '0', STR_PAD_LEFT); ?></strong>
                                <button type="button" class="toggle-row">
                                    <span class="screen-reader-text">Show more details</span>
                                </button>
                            </td>
                            <td data-label="Customer">
                                <div class="crqa-customer-info">
                                    <div class="name"><?php echo esc_html($quote->customer_name); ?></div>
                                    <div class="email"><a href="mailto:<?php echo esc_attr($quote->customer_email); ?>"><?php echo esc_html($quote->customer_email); ?></a></div>
                                    <?php if ($quote->customer_phone): ?>
                                        <div class="phone"><?php echo esc_html($quote->customer_phone); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Vehicle">
                                <?php echo crqa_get_vehicle_display($quote); ?>
                            </td>
                            <td data-label="Dates"><?php echo esc_html($quote->rental_dates); ?></td>
                            <td data-label="Status">
                                <?php echo crqa_get_status_badge($quote->quote_status); ?>
                            </td>
                            <td data-label="Price">
                                <?php if ($quote->rental_price): ?>
                                    <strong><?php echo crqa_format_price($quote->rental_price); ?></strong>
                                    <?php if ($quote->deposit_amount): ?>
                                        <br><span class="description">+ <?php echo crqa_format_price($quote->deposit_amount); ?> deposit</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="description">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-created" data-label="Created">
                                <?php echo date('M j, Y', strtotime($quote->created_at)); ?><br>
                                <span class="description"><?php echo date('g:i a', strtotime($quote->created_at)); ?></span>
                            </td>
                            <td data-label="Actions">
                                <div class="row-actions">
                                    <?php echo crqa_get_quote_actions($quote, $current_view, $soft_delete_supported); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-2">
                        </td>
                        <th class="manage-column">Quote ID</th>
                        <th class="manage-column">Customer</th>
                        <th class="manage-column">Vehicle</th>
                        <th class="manage-column">Dates</th>
                        <th class="manage-column">Status</th>
                        <th class="manage-column">Price</th>
                        <th class="manage-column">Created</th>
                        <th class="manage-column">Actions</th>
                    </tr>
                </tfoot>
            </table>
            
            <!-- Bulk Actions -->
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="bulk_action" id="bulk-action-selector">
                        <option value="">Bulk Actions</option>
                        <?php if ($soft_delete_supported && $current_view === 'trash'): ?>
                            <option value="restore">Restore</option>
                            <option value="delete">Delete Permanently</option>
                        <?php else: ?>
                            <?php if ($soft_delete_supported): ?>
                                <option value="trash">Move to Trash</option>
                            <?php else: ?>
                                <option value="delete">Delete</option>
                            <?php endif; ?>
                            <option value="mark_pending">Mark as Pending</option>
                            <option value="mark_quoted">Mark as Quoted</option>
                            <option value="mark_paid">Mark as Paid</option>
                            <option value="export">Export to CSV</option>
                        <?php endif; ?>
                    </select>
                    <input type="submit" class="button action" value="Apply">
                </div>
                
                <!-- Improved Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo $total_items; ?> items</span>
                    
                    <span class="pagination-links">
                        <?php if ($current_page > 1): ?>
                            <a class="first-page button" href="<?php echo esc_url(remove_query_arg('paged')); ?>">
                                <span class="screen-reader-text">First page</span>
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                            <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>">
                                <span class="screen-reader-text">Previous page</span>
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        <?php else: ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;&laquo;</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                        <?php endif; ?>
                        
                        <span class="paging-input">
                            <label for="current-page-selector" class="screen-reader-text">Current Page</label>
                            <span class="current-page"><?php echo $current_page; ?></span>
                            <span class="tablenav-paging-text"> of 
                                <span class="total-pages"><?php echo $total_pages; ?></span>
                            </span>
                        </span>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>">
                                <span class="screen-reader-text">Next page</span>
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                            <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages)); ?>">
                                <span class="screen-reader-text">Last page</span>
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        <?php else: ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;&raquo;</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Handle bulk actions
 */
function crqa_handle_bulk_actions() {
    if (!isset($_POST['quote_ids']) || empty($_POST['quote_ids'])) {
        return;
    }

    // Verify nonce for CSRF protection
    if (!isset($_POST['crqa_bulk_action_nonce']) || !wp_verify_nonce($_POST['crqa_bulk_action_nonce'], 'crqa_bulk_action')) {
        wp_die('Security check failed. Please try again.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';

    // Validate table name contains only allowed characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        wp_die('Invalid table name configuration.');
    }

    $action = sanitize_text_field($_POST['bulk_action']);
    $quote_ids = array_map('intval', $_POST['quote_ids']);
    
    // Check if soft delete is supported
    $soft_delete_supported = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = %s 
        AND TABLE_NAME = %s 
        AND COLUMN_NAME = 'is_deleted'",
        DB_NAME,
        $table_name
    )) > 0;
    
    switch ($action) {
        case 'trash':
            if ($soft_delete_supported) {
                foreach ($quote_ids as $id) {
                    $wpdb->update($table_name, array('is_deleted' => 1, 'deleted_at' => current_time('mysql')), array('id' => $id));
                }
                wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=bulk_trashed'));
                exit;
            }
            break;
            
        case 'restore':
            if ($soft_delete_supported) {
                foreach ($quote_ids as $id) {
                    $wpdb->update($table_name, array('is_deleted' => 0, 'deleted_at' => null), array('id' => $id));
                }
                wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=bulk_restored'));
                exit;
            }
            break;
            
        case 'delete':
            foreach ($quote_ids as $id) {
                $wpdb->delete($table_name, array('id' => $id));
            }
            $redirect = $soft_delete_supported ? 
                admin_url('admin.php?page=car-rental-quotes&view=trash&message=bulk_deleted') : 
                admin_url('admin.php?page=car-rental-quotes&message=bulk_deleted');
            wp_redirect($redirect);
            exit;
            
        case 'mark_pending':
        case 'mark_quoted':
        case 'mark_paid':
            $status = str_replace('mark_', '', $action);
            foreach ($quote_ids as $id) {
                $wpdb->update($table_name, array('quote_status' => $status), array('id' => $id));
            }
            wp_redirect(admin_url('admin.php?page=car-rental-quotes&message=bulk_updated'));
            exit;
            
        case 'export':
            crqa_export_quotes($quote_ids);
            exit;
    }
}

/**
 * Display admin notices
 */
function crqa_display_admin_notice($message) {
    $messages = array(
        'deleted' => 'Quote deleted successfully.',
        'trashed' => 'Quote moved to trash.',
        'restored' => 'Quote restored.',
        'resent' => 'Quote email sent successfully.',
        'duplicated' => 'Quote duplicated successfully.',
        'updated' => 'Quote updated successfully.',
        'bulk_deleted' => 'Selected quotes deleted successfully.',
        'bulk_trashed' => 'Selected quotes moved to trash.',
        'bulk_restored' => 'Selected quotes restored.',
        'bulk_updated' => 'Selected quotes updated successfully.'
    );
    
    if (isset($messages[$message])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $messages[$message] . '</p></div>';
    }
}

/**
 * Get quote action links
 */
function crqa_get_quote_actions($quote, $view = 'all', $soft_delete_supported = false) {
    $actions = array();
    
    if ($view === 'trash' && $soft_delete_supported) {
        // Restore
        $actions[] = '<a href="' . wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=restore&quote_id=' . $quote->id), 'restore_quote_' . $quote->id) . '" class="restore">Restore</a>';
        
        // Delete Permanently
        $actions[] = '<a href="' . wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=delete&quote_id=' . $quote->id), 'delete_quote_' . $quote->id) . '" class="delete" onclick="return confirm(\'Are you sure you want to permanently delete this quote?\');">Delete Permanently</a>';
    } else {
        // Edit
        $actions[] = '<a href="' . admin_url('admin.php?page=car-rental-quotes&action=edit&quote_id=' . $quote->id) . '" class="edit">Edit</a>';
        
        // View
        $actions[] = '<a href="' . home_url('/quote/' . $quote->quote_hash) . '" target="_blank" class="view">View</a>';
        
        // Resend (only for quoted status)
        if ($quote->quote_status == 'quoted') {
            $actions[] = '<a href="' . wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=resend&quote_id=' . $quote->id), 'resend_quote_' . $quote->id) . '" class="resend">Resend</a>';
        }
        
        // Delete or Trash
        if ($soft_delete_supported) {
            $actions[] = '<a href="' . wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=trash&quote_id=' . $quote->id), 'trash_quote_' . $quote->id) . '" class="trash">Trash</a>';
        } else {
            $actions[] = '<a href="' . wp_nonce_url(admin_url('admin.php?page=car-rental-quotes&action=delete&quote_id=' . $quote->id), 'delete_quote_' . $quote->id) . '" class="delete">Delete</a>';
        }
    }
    
    return implode(' ', $actions);
}

/**
 * Duplicate quote
 */
function crqa_duplicate_quote($quote_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';

    // Validate table name contains only allowed characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        return false;
    }
    $safe_table_name = esc_sql($table_name);

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
    $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$safe_table_name}` WHERE id = %d", $quote_id), ARRAY_A);
    
    if ($quote) {
        unset($quote['id']);
        $quote['quote_hash'] = md5(uniqid() . time());
        $quote['created_at'] = current_time('mysql');
        $quote['quote_status'] = 'pending';
        
        $wpdb->insert($table_name, $quote);
    }
}

/**
 * Export quotes to CSV
 */
function crqa_export_quotes($quote_ids = array()) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';

    // Validate table name contains only allowed characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        wp_die('Invalid table name configuration.');
    }
    $safe_table_name = esc_sql($table_name);

    // Get quotes
    if (!empty($quote_ids)) {
        $placeholders = implode(',', array_fill(0, count($quote_ids), '%d'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated, placeholders are safe
        $quotes = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$safe_table_name}` WHERE id IN ($placeholders)", $quote_ids));
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is validated and escaped
        $quotes = $wpdb->get_results("SELECT * FROM `{$safe_table_name}`");
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="car-rental-quotes-' . date('Y-m-d') . '.csv"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, array(
        'Quote ID',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Vehicle Name',
        'Vehicle Details',
        'Rental Dates',
        'Rental Price',
        'Deposit Amount',
        'Total Amount',
        'Mileage Allowance',
        'Delivery Option',
        'Additional Notes',
        'Status',
        'Created Date',
        'Quote URL'
    ));
    
    // Add data rows
    foreach ($quotes as $quote) {
        fputcsv($output, array(
            $quote->id,
            $quote->customer_name,
            $quote->customer_email,
            $quote->customer_phone,
            $quote->vehicle_name,
            $quote->vehicle_details,
            $quote->rental_dates,
            $quote->rental_price,
            $quote->deposit_amount,
            $quote->rental_price + $quote->deposit_amount,
            $quote->mileage_allowance,
            $quote->delivery_option,
            $quote->additional_notes,
            $quote->quote_status,
            $quote->created_at,
            home_url('/quote/' . $quote->quote_hash)
        ));
    }
    
    fclose($output);
    exit;
}

/**
 * AJAX handler for quick edit
 */
add_action('wp_ajax_crqa_quick_edit', 'crqa_ajax_quick_edit');
function crqa_ajax_quick_edit() {
    // Verify user capability first
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    // Verify nonce for CSRF protection
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'crqa_quick_edit')) {
        wp_send_json_error('Security check failed');
        return;
    }

    // Validate required fields exist before processing
    if (!isset($_POST['quote_id']) || !isset($_POST['field']) || !isset($_POST['value'])) {
        wp_send_json_error('Missing required fields');
        return;
    }

    // Define allowed fields BEFORE checking input
    $allowed_fields = array('rental_price', 'deposit_amount', 'quote_status');
    $field = sanitize_text_field($_POST['field']);

    // Validate field is allowed before processing further
    if (!in_array($field, $allowed_fields, true)) {
        wp_send_json_error('Invalid field');
        return;
    }

    $quote_id = intval($_POST['quote_id']);
    $value = sanitize_text_field($_POST['value']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'car_rental_quotes';

    // Validate table name contains only allowed characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        wp_send_json_error('Configuration error');
        return;
    }

    $wpdb->update(
        $table_name,
        array($field => $value),
        array('id' => $quote_id),
        array('%s'),
        array('%d')
    );

    wp_send_json_success();
}