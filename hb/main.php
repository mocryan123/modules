<?php
/**
 * Module Name: Hotel & Villa Booking Management
 * Module Slug: hb
 * Description: Complete booking system for hotels and villas with quotation generation, calendar management, and email notifications
 * Version: 1.0.0
 * Author: BNTM Framework
 */

if (!defined('ABSPATH')) exit;

define('BNTM_HB_PATH', dirname(__FILE__) . '/');
define('BNTM_HB_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION
// ============================================================================

function bntm_hb_get_pages() {
    return [
        'Hotel & Villa Dashboard' => '[hb_dashboard]',
        'Browse Rooms & Villas' => '[hb_browse]',
        'Booking Form' => '[hb_booking_form]',
        'View Quotation' => '[hb_view_quotation]',
    ];
}

function bntm_hb_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'hb_properties' => "CREATE TABLE {$prefix}hb_properties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            type ENUM('room', 'villa') NOT NULL DEFAULT 'room',
            description TEXT,
            short_description VARCHAR(500),
            capacity INT NOT NULL DEFAULT 2,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
            amenities TEXT,
            images TEXT,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (type),
            INDEX idx_status (status)
        ) {$charset};",
        
        'hb_bookings' => "CREATE TABLE {$prefix}hb_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            quotation_number VARCHAR(50) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            property_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            num_guests INT NOT NULL DEFAULT 1,
            num_nights INT NOT NULL DEFAULT 1,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending', 'quoted', 'payment_pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'quoted',
            payment_status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
            notes TEXT,
            admin_notes TEXT,
            email_sent TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_property (property_id),
            INDEX idx_status (status),
            INDEX idx_dates (check_in, check_out),
            INDEX idx_quotation (quotation_number)
        ) {$charset};",
        
        'hb_payments' => "CREATE TABLE {$prefix}hb_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            booking_id BIGINT UNSIGNED NOT NULL,
            transaction_id VARCHAR(100) UNIQUE,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'maya',
            amount DECIMAL(10,2) NOT NULL,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
            payment_url TEXT,
            reference_number VARCHAR(100),
            response_data LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_booking (booking_id),
            INDEX idx_transaction (transaction_id),
            INDEX idx_status (status)
        ) {$charset};"
    ];
}

function bntm_hb_get_shortcodes() {
    return [
        'hb_dashboard' => 'bntm_shortcode_hb_dashboard',
        'hb_browse' => 'bntm_shortcode_hb_browse',
        'hb_booking_form' => 'bntm_shortcode_hb_booking_form',
        'hb_view_quotation' => 'bntm_shortcode_hb_view_quotation',
    ];
}

function bntm_hb_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_hb_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// ============================================================================
// AJAX HOOKS
// ============================================================================

add_action('wp_ajax_hb_add_property', 'bntm_ajax_hb_add_property');
add_action('wp_ajax_hb_update_property', 'bntm_ajax_hb_update_property');
add_action('wp_ajax_hb_delete_property', 'bntm_ajax_hb_delete_property');
add_action('wp_ajax_hb_update_booking_status', 'bntm_ajax_hb_update_booking_status');
add_action('wp_ajax_hb_save_tax_rate', 'bntm_ajax_hb_save_tax_rate');
add_action('wp_ajax_hb_delete_booking', 'bntm_ajax_hb_delete_booking');
add_action('wp_ajax_hb_resend_quotation', 'bntm_ajax_hb_resend_quotation');
add_action('wp_ajax_hb_submit_booking', 'bntm_ajax_hb_submit_booking');
add_action('wp_ajax_nopriv_hb_submit_booking', 'bntm_ajax_hb_submit_booking');
add_action('wp_ajax_hb_process_payment', 'bntm_ajax_hb_process_payment');
add_action('wp_ajax_nopriv_hb_process_payment', 'bntm_ajax_hb_process_payment');
add_action('wp_ajax_hb_confirm_booking', 'bntm_ajax_hb_confirm_booking');
add_action('wp_ajax_nopriv_hb_payment_callback', 'bntm_ajax_hb_payment_callback');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_hb_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the booking dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calendar';
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <div class="bntm-hb-container">
        <div class="bntm-tabs">
            <a href="?tab=calendar" class="bntm-tab <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                Calendar
            </a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                Bookings
            </a>
            <a href="?tab=properties" class="bntm-tab <?php echo $active_tab === 'properties' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                Properties
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6m5.66-13.66l-4.24 4.24m-2.82 2.82l-4.24 4.24M1 12h6m6 0h6m-13.66 5.66l4.24-4.24m2.82-2.82l4.24-4.24"></path>
                </svg>
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php 
            if ($active_tab === 'calendar') echo hb_calendar_tab($business_id);
            elseif ($active_tab === 'bookings') echo hb_bookings_tab($business_id);
            elseif ($active_tab === 'properties') echo hb_properties_tab($business_id);
            elseif ($active_tab === 'settings') echo hb_settings_tab($business_id);
            ?>
        </div>
    </div>
    
    <style>
    .hb-modal {display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(4px);}
    .hb-modal-content {background-color: #fff; margin: 5% auto; padding: 0; border-radius: 16px; width: 90%; max-width: 600px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid rgba(0,0,0,0.05);}
    .hb-modal-header {padding: 28px 32px; border-bottom: 1px solid #f0f1f3; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);}
    .hb-modal-header h3 {margin: 0; font-size: 20px; font-weight: 700; color: #1a202c;}
    .hb-modal-close {background: none; border: none; font-size: 28px; cursor: pointer; color: #a0aec0; padding: 0; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.2s;}
    .hb-modal-close:hover {background-color: #f0f1f3; color: #2d3748;}
    .hb-modal-body {padding: 32px; max-height: 70vh; overflow-y: auto;}
    .bntm-tabs {display: flex; gap: 8px; border-bottom: 2px solid #e5e7eb; background: white; padding: 0; margin: 0; flex-wrap: wrap;}
    .bntm-tab {display: flex; align-items: center; gap: 8px; padding: 16px 24px; background: none; border: none; cursor: pointer; color: #718096; font-weight: 600; font-size: 15px; text-decoration: none; transition: all 0.2s; border-bottom: 3px solid transparent; margin-bottom: -2px; position: relative;}
    .bntm-tab:hover {color: #4a5568; background: #f7fafc;}
    .bntm-tab.active {color: #667eea; border-bottom-color: #667eea; background: linear-gradient(to bottom, #f7fafc, white);}
    .bntm-tab-content {padding: 32px;}
    .bntm-dashboard-stats {display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 32px;}
    .bntm-stat-card {background: white; border-radius: 14px; padding: 28px; border: 1px solid #edf2f7; box-shadow: 0 1px 3px rgba(0,0,0,0.05); transition: all 0.3s; position: relative; overflow: hidden;}
    .bntm-stat-card:before {content: ''; position: absolute; top: 0; right: 0; width: 100px; height: 100px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); opacity: 0.05; border-radius: 50%;}
    .bntm-stat-card:hover {transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.12);}
    .bntm-stat-card h3 {margin: 0 0 12px 0; font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700;}
    .bntm-stat-number {font-size: 36px; font-weight: 800; color: #1a202c; margin: 0 0 8px 0;}
    .bntm-stat-label {font-size: 14px; color: #667eea; margin: 0; font-weight: 700;}
    .bntm-form-section {background: white; border-radius: 14px; padding: 32px; margin-bottom: 32px; border: 1px solid #edf2f7; box-shadow: 0 1px 3px rgba(0,0,0,0.05);}
    .bntm-form-section h3 {margin: 0 0 16px 0; font-size: 20px; font-weight: 700; color: #1a202c;}
    .bntm-form-section p {margin: 0 0 20px 0; color: #718096; font-size: 15px;}
    .bntm-form-group {margin-bottom: 20px;}
    .bntm-form-group label {display: block; font-weight: 600; color: #2d3748; margin-bottom: 8px; font-size: 14px;}
    .bntm-input {width: 100%; padding: 12px 16px; border: 1px solid #cbd5e0; border-radius: 10px; font-size: 15px; transition: all 0.2s; font-family: inherit;}
    .bntm-input:focus {outline: none; border-color: #667eea; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);}
    .bntm-form-row {display: grid; grid-template-columns: 1fr 1fr; gap: 20px;}
    .bntm-btn-primary {padding: 11px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);}
    .bntm-btn-primary:hover {transform: translateY(-2px); box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);}
    .bntm-btn-secondary {padding: 11px 24px; background: #f7fafc; color: #2d3748; border: 1px solid #cbd5e0; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s;}
    .bntm-btn-secondary:hover {background: #edf2f7; border-color: #667eea; color: #667eea;}
    .bntm-btn-small {padding: 7px 14px; background: #f7fafc; color: #2d3748; border: 1px solid #cbd5e0; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s;}
    .bntm-btn-small:hover {background: #edf2f7; border-color: #667eea; color: #667eea;}
    .bntm-btn-danger {color: #c53030; border-color: #fc8181;}
    .bntm-btn-danger:hover {background: #fff5f5; border-color: #f56565; color: #f56565;}
    .bntm-notice {padding: 16px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; border-left: 4px solid;}
    .bntm-notice-success {background: #f0fdf4; color: #166534; border-color: #22c55e;}
    .bntm-notice-error {background: #fef2f2; color: #991b1b; border-color: #f87171;}
    .bntm-table-container {overflow-x: auto; border: 1px solid #edf2f7; border-radius: 12px;}
    .bntm-table {width: 100%; border-collapse: collapse; font-size: 14px;}
    .bntm-table thead {background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);}
    .bntm-table th {padding: 16px 20px; text-align: left; font-weight: 700; color: #2d3748; border-bottom: 2px solid #cbd5e0;}
    .bntm-table td {padding: 16px 20px; border-bottom: 1px solid #edf2f7;}
    .bntm-table tbody tr:hover {background: #f7fafc;}
    </style>
    
    <script>
    function openModal(id) {document.getElementById(id).style.display = 'block';}
    function closeModal(id) {document.getElementById(id).style.display = 'none';}
    window.onclick = function(event) {if (event.target.classList.contains('hb-modal')) {event.target.style.display = 'none';}}
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Hotel & Villa Booking Management', $content);
}

// ============================================================================
// TAB FUNCTIONS
// ============================================================================

function hb_calendar_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $current_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
    $month_start = $current_month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    
    $bookings = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as property_name, p.type as property_type
        FROM {$bookings_table} b
        LEFT JOIN {$properties_table} p ON b.property_id = p.id
        WHERE b.business_id = %d
        AND b.status != 'cancelled'
        AND (
            (b.check_in BETWEEN %s AND %s)
            OR (b.check_out BETWEEN %s AND %s)
            OR (b.check_in <= %s AND b.check_out >= %s)
        )
        ORDER BY b.check_in ASC
    ", $business_id, $month_start, $month_end, $month_start, $month_end, $month_start, $month_end));
    
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'quoted' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_count,
            COALESCE(SUM(CASE WHEN status = 'confirmed' THEN grand_total ELSE 0 END), 0) as confirmed_revenue,
            COALESCE(SUM(CASE WHEN status = 'quoted' THEN grand_total ELSE 0 END), 0) as pending_revenue
        FROM {$bookings_table}
        WHERE business_id = %d
        AND check_in BETWEEN %s AND %s
    ", $business_id, $month_start, $month_end));
    
    $prev_month = date('Y-m', strtotime($month_start . ' -1 month'));
    $next_month = date('Y-m', strtotime($month_start . ' +1 month'));
    
    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Pending Bookings</h3>
            <p class="bntm-stat-number"><?php echo intval($stats->pending_count); ?></p>
            <p class="bntm-stat-label">₱<?php echo number_format($stats->pending_revenue, 2); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Confirmed Bookings</h3>
            <p class="bntm-stat-number"><?php echo intval($stats->confirmed_count); ?></p>
            <p class="bntm-stat-label">₱<?php echo number_format($stats->confirmed_revenue, 2); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Revenue (Month)</h3>
            <p class="bntm-stat-number">₱<?php echo number_format($stats->confirmed_revenue, 2); ?></p>
            <p class="bntm-stat-label"><?php echo date('F Y', strtotime($month_start)); ?></p>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Calendar View - <?php echo date('F Y', strtotime($month_start)); ?></h3>
            <div style="display: flex; gap: 10px;">
                <a href="?tab=calendar&month=<?php echo $prev_month; ?>" class="bntm-btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Previous
                </a>
                <a href="?tab=calendar&month=<?php echo date('Y-m'); ?>" class="bntm-btn-secondary">Today</a>
                <a href="?tab=calendar&month=<?php echo $next_month; ?>" class="bntm-btn-secondary">
                    Next
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </a>
            </div>
        </div>
        
        <div class="hb-calendar-grid">
            <?php
            $first_day = date('N', strtotime($month_start));
            $days_in_month = date('t', strtotime($month_start));
            
            echo '<div class="hb-calendar-header">Mon</div>';
            echo '<div class="hb-calendar-header">Tue</div>';
            echo '<div class="hb-calendar-header">Wed</div>';
            echo '<div class="hb-calendar-header">Thu</div>';
            echo '<div class="hb-calendar-header">Fri</div>';
            echo '<div class="hb-calendar-header">Sat</div>';
            echo '<div class="hb-calendar-header">Sun</div>';
            
            for ($i = 1; $i < $first_day; $i++) {
                echo '<div class="hb-calendar-day hb-calendar-empty"></div>';
            }
            
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = $current_month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                $is_today = $date === date('Y-m-d');
                
                $day_bookings = array_filter($bookings, function($b) use ($date) {
                    return $date >= $b->check_in && $date < $b->check_out;
                });
                
                echo '<div class="hb-calendar-day' . ($is_today ? ' hb-calendar-today' : '') . '">';
                echo '<div class="hb-calendar-date">' . $day . '</div>';
                
                if (!empty($day_bookings)) {
                    echo '<div class="hb-calendar-bookings">';
                    foreach ($day_bookings as $booking) {
                        $status_class = 'hb-booking-' . $booking->status;
                        echo '<div class="hb-calendar-booking ' . $status_class . '" title="' . 
                             esc_attr($booking->property_name . ' - ' . $booking->customer_name) . '">';
                        echo '<small>' . esc_html(substr($booking->property_name, 0, 20)) . '</small>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
            }
            ?>
        </div>
        
        <div style="margin-top: 20px; display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 16px; height: 16px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 4px;"></div>
                <span>Pending</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 16px; height: 16px; background: #dbeafe; border: 1px solid #3b82f6; border-radius: 4px;"></div>
                <span>Quoted</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 16px; height: 16px; background: #d1fae5; border: 1px solid #10b981; border-radius: 4px;"></div>
                <span>Confirmed</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 16px; height: 16px; background: #e0e7ff; border: 1px solid #6366f1; border-radius: 4px;"></div>
                <span>Completed</span>
            </div>
        </div>
    </div>
    
    <style>
    .hb-calendar-grid {display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e5e7eb; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.07);}
    .hb-calendar-header {background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 14px; text-align: center; font-weight: 700; font-size: 12px; color: white; text-transform: uppercase; letter-spacing: 0.5px;}
    .hb-calendar-day {background: white; min-height: 110px; padding: 10px; position: relative; transition: background 0.2s;}
    .hb-calendar-day:hover {background: #f8f9fc;}
    .hb-calendar-empty {background: #f9fafb;}
    .hb-calendar-today {background: linear-gradient(135deg, #fef3c7 0%, #fef08a 100%); border: 2px solid #fbbf24;}
    .hb-calendar-date {font-weight: 700; font-size: 15px; margin-bottom: 6px; color: #1a202c;}
    .hb-calendar-bookings {display: flex; flex-direction: column; gap: 4px;}
    .hb-calendar-booking {padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 500; border-left: 3px solid; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
    .hb-booking-pending {background: #fef3c7; border-color: #fbbf24; color: #78350f;}
    .hb-booking-quoted {background: #dbeafe; border-color: #0ea5e9; color: #0c2d6b;}
    .hb-booking-confirmed {background: #d1fae5; border-color: #10b981; color: #065f46;}
    .hb-booking-completed {background: #e0e7ff; border-color: #6366f1; color: #3730a3;}
    </style>
    <?php
    return ob_get_clean();
}

function hb_bookings_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    
    $where_clause = "b.business_id = %d";
    $params = [$business_id];
    
    if ($filter_status !== 'all') {
        $where_clause .= " AND b.status = %s";
        $params[] = $filter_status;
    }
    
    $bookings = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as property_name, p.type as property_type
        FROM {$bookings_table} b
        LEFT JOIN {$properties_table} p ON b.property_id = p.id
        WHERE {$where_clause}
        ORDER BY b.created_at DESC
        LIMIT 50
    ", $params));
    
    $nonce = wp_create_nonce('hb_bookings_nonce');
    
    ob_start();
    ?>
    <div class="hb-bookings-header">
        <h3>Bookings</h3>
        <select id="status-filter" onchange="window.location.href='?tab=bookings&status='+this.value" class="hb-filter-select">
            <option value="all" <?php selected($filter_status, 'all'); ?>>All Bookings</option>
            <option value="quoted" <?php selected($filter_status, 'quoted'); ?>>Awaiting Payment</option>
            <option value="payment_pending" <?php selected($filter_status, 'payment_pending'); ?>>Payment Received</option>
            <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmed</option>
            <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
            <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
        </select>
    </div>
    
    <?php if (empty($bookings)): ?>
        <div class="hb-empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
            <h4>No bookings found</h4>
            <p>No bookings match your current filter</p>
        </div>
    <?php else: ?>
        <div class="hb-bookings-list">
            <?php foreach ($bookings as $booking): 
                $deposit_percent = floatval(bntm_get_setting('hb_maya_deposit_percentage', '30'));
                $deposit_amount = ($booking->grand_total * $deposit_percent) / 100;
            ?>
                <div class="hb-booking-card">
                    <div class="hb-booking-main">
                        <div class="hb-booking-info">
                            <div class="hb-booking-id">
                                <strong><?php echo esc_html($booking->quotation_number); ?></strong>
                                <span class="hb-status-dot hb-status-<?php echo $booking->status; ?>"></span>
                            </div>
                            <div class="hb-booking-guest">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <?php echo esc_html($booking->customer_name); ?>
                            </div>
                            <div class="hb-booking-property">
                                <?php echo esc_html($booking->property_name); ?>
                            </div>
                        </div>
                        
                        <div class="hb-booking-dates">
                            <div class="hb-date-item">
                                <small>Check-in</small>
                                <strong><?php echo date('M d, Y', strtotime($booking->check_in)); ?></strong>
                            </div>
                            <div class="hb-date-arrow">→</div>
                            <div class="hb-date-item">
                                <small>Check-out</small>
                                <strong><?php echo date('M d, Y', strtotime($booking->check_out)); ?></strong>
                            </div>
                            <div class="hb-date-nights">
                                <?php echo $booking->num_nights; ?> night<?php echo $booking->num_nights > 1 ? 's' : ''; ?>
                            </div>
                        </div>
                        
                        <div class="hb-booking-amount">
                            <div class="hb-amount-total">
                                <small>Total</small>
                                <strong>₱<?php echo number_format($booking->grand_total, 2); ?></strong>
                            </div>
                            <div class="hb-payment-badge hb-payment-<?php echo $booking->payment_status; ?>">
                                <?php 
                                $payment_labels = ['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid'];
                                echo $payment_labels[$booking->payment_status] ?? ucfirst($booking->payment_status);
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hb-booking-actions">
                        <select class="hb-status-select" 
                                data-booking-id="<?php echo $booking->id; ?>"
                                data-nonce="<?php echo $nonce; ?>">
                            <option value="quoted" <?php selected($booking->status, 'quoted'); ?>>Awaiting Payment</option>
                            <option value="payment_pending" <?php selected($booking->status, 'payment_pending'); ?>>Payment Received</option>
                            <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                            <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                            <option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>
                        </select>
                        
                        <div class="hb-action-buttons">
                            <a href="<?php echo get_permalink(get_page_by_path('view-quotation')) . '?q=' . $booking->quotation_number; ?>" 
                               class="hb-action-btn" target="_blank" title="View">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </a>
                            
                            <?php if ($booking->status === 'payment_pending'): ?>
                            <button class="hb-action-btn hb-action-confirm" 
                                    onclick="confirmBooking(<?php echo $booking->id; ?>, '<?php echo $nonce; ?>')"
                                    title="Confirm Booking">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </button>
                            <?php endif; ?>
                            
                            <button class="hb-action-btn resend-email-btn" 
                                    data-booking-id="<?php echo $booking->id; ?>"
                                    data-nonce="<?php echo $nonce; ?>"
                                    title="Resend Email">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </button>
                            
                            <button class="hb-action-btn hb-action-delete delete-booking-btn" 
                                    data-booking-id="<?php echo $booking->id; ?>"
                                    data-nonce="<?php echo $nonce; ?>"
                                    title="Delete">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <style>
    .hb-bookings-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;}
    .hb-bookings-header h3 {margin: 0; font-size: 20px; font-weight: 700; color: #1a202c;}
    .hb-filter-select {padding: 10px 16px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 14px; font-weight: 500; color: #2d3748; background: white; cursor: pointer;}
    .hb-filter-select:focus {outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);}
    
    .hb-empty-state {text-align: center; padding: 80px 20px; color: #9ca3af;}
    .hb-empty-state svg {margin: 0 auto 20px; opacity: 0.5;}
    .hb-empty-state h4 {margin: 0 0 8px 0; font-size: 18px; font-weight: 600; color: #4b5563;}
    .hb-empty-state p {margin: 0; font-size: 14px;}
    
    .hb-bookings-list {display: flex; flex-direction: column; gap: 16px;}
    .hb-booking-card {background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; transition: all 0.2s;}
    .hb-booking-card:hover {box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-color: #cbd5e0;}
    
    .hb-booking-main {display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 24px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f3f4f6;}
    
    .hb-booking-info {display: flex; flex-direction: column; gap: 8px;}
    .hb-booking-id {display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 700; color: #1a202c;}
    .hb-booking-guest {display: flex; align-items: center; gap: 6px; font-size: 14px; color: #4b5563;}
    .hb-booking-guest svg {color: #9ca3af; width: 16px; height: 16px;}
    .hb-booking-property {font-size: 13px; color: #6b7280;}
    
    .hb-status-dot {width: 8px; height: 8px; border-radius: 50%;}
    .hb-status-dot.hb-status-quoted {background: #3b82f6;}
    .hb-status-dot.hb-status-payment_pending {background: #f59e0b;}
    .hb-status-dot.hb-status-confirmed {background: #10b981;}
    .hb-status-dot.hb-status-completed {background: #6366f1;}
    .hb-status-dot.hb-status-cancelled {background: #ef4444;}
    
    .hb-booking-dates {display: flex; align-items: center; gap: 12px;}
    .hb-date-item {display: flex; flex-direction: column; gap: 4px;}
    .hb-date-item small {font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;}
    .hb-date-item strong {font-size: 14px; color: #1a202c;}
    .hb-date-arrow {color: #cbd5e0; font-size: 18px;}
    .hb-date-nights {padding: 4px 10px; background: #f3f4f6; border-radius: 6px; font-size: 12px; font-weight: 600; color: #6b7280;}
    
    .hb-booking-amount {display: flex; flex-direction: column; gap: 8px; align-items: flex-end;}
    .hb-amount-total {display: flex; flex-direction: column; gap: 2px; align-items: flex-end;}
    .hb-amount-total small {font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px;}
    .hb-amount-total strong {font-size: 18px; font-weight: 700; color: #1a202c;}
    
    .hb-payment-badge {padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;}
    .hb-payment-badge.hb-payment-unpaid {background: #fee2e2; color: #991b1b;}
    .hb-payment-badge.hb-payment-partial {background: #fef3c7; color: #92400e;}
    .hb-payment-badge.hb-payment-paid {background: #d1fae5; color: #065f46;}
    
    .hb-booking-actions {display: flex; justify-content: space-between; align-items: center; gap: 12px;}
    .hb-status-select {flex: 1; padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 13px; background: white; cursor: pointer;}
    .hb-status-select:focus {outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);}
    
    .hb-action-buttons {display: flex; gap: 6px;}
    .hb-action-btn {display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 1px solid #cbd5e0; border-radius: 8px; background: white; cursor: pointer; transition: all 0.2s; text-decoration: none; color: #4b5563;}
    .hb-action-btn:hover {background: #f3f4f6; border-color: #9ca3af;}
    .hb-action-btn svg {width: 16px; height: 16px;}
    .hb-action-confirm {border-color: #10b981; color: #10b981;}
    .hb-action-confirm:hover {background: #d1fae5; border-color: #059669;}
    .hb-action-delete {border-color: #ef4444; color: #ef4444;}
    .hb-action-delete:hover {background: #fee2e2; border-color: #dc2626;}
    
    @media (max-width: 1024px) {
        .hb-booking-main {grid-template-columns: 1fr; gap: 16px;}
        .hb-booking-amount {align-items: flex-start;}
        .hb-booking-actions {flex-direction: column;}
        .hb-status-select, .hb-action-buttons {width: 100%;}
        .hb-action-buttons {justify-content: space-between;}
    }
    </style>
    
    <script>
    function confirmBooking(bookingId, nonce) {
        if (!confirm('Confirm this booking? Customer will be notified.')) return;
        
        const formData = new FormData();
        formData.append('action', 'hb_confirm_booking');
        formData.append('booking_id', bookingId);
        formData.append('nonce', nonce);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            if (json.success) location.reload();
        });
    }
    
    (function() {
        document.querySelectorAll('.hb-status-select').forEach(select => {
            select.dataset.originalValue = select.value;
            
            select.addEventListener('change', function() {
                const bookingId = this.dataset.bookingId;
                const newStatus = this.value;
                const nonce = this.dataset.nonce;
                
                if (!confirm('Update booking status?')) {
                    this.value = this.dataset.originalValue;
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'hb_update_booking_status');
                formData.append('booking_id', bookingId);
                formData.append('status', newStatus);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        this.dataset.originalValue = newStatus;
                        location.reload();
                    } else {
                        alert(json.data.message);
                        this.value = this.dataset.originalValue;
                    }
                });
            });
        });
        
        document.querySelectorAll('.resend-email-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Resend quotation email?')) return;
                
                this.disabled = true;
                const originalHTML = this.innerHTML;
                this.innerHTML = '...';
                
                const formData = new FormData();
                formData.append('action', 'hb_resend_quotation');
                formData.append('booking_id', this.dataset.bookingId);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                });
            });
        });
        
        document.querySelectorAll('.delete-booking-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this booking? This cannot be undone.')) return;
                
                this.disabled = true;
                
                const formData = new FormData();
                formData.append('action', 'hb_delete_booking');
                formData.append('booking_id', this.dataset.bookingId);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                    else this.disabled = false;
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function hb_properties_tab($business_id) {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $properties = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$properties_table}
        WHERE business_id = %d
        ORDER BY sort_order ASC, name ASC
    ", $business_id));
    
    $nonce = wp_create_nonce('hb_properties_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Hotel Rooms Management</h3>
            <button onclick="openAddPropertyModal()" class="bntm-btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Room
            </button>
        </div>
        
        <div class="hb-properties-grid">
            <?php if (empty($properties)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #6b7280;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 16px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <p>No rooms yet. Click "Add Room" to get started.</p>
                </div>
            <?php else: foreach ($properties as $prop): 
                $images = !empty($prop->images) ? json_decode($prop->images, true) : [];
                $first_image = !empty($images) ? $images[0] : '';
            ?>
                <div class="hb-property-card">
                    <?php if ($first_image): ?>
                        <div class="hb-property-image">
                            <img src="<?php echo esc_url($first_image); ?>" alt="<?php echo esc_attr($prop->name); ?>">
                            <span class="hb-property-type"><?php echo ucfirst($prop->type); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="hb-property-image hb-property-no-image">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                            <span class="hb-property-type"><?php echo ucfirst($prop->type); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="hb-property-details">
                        <h4><?php echo esc_html($prop->name); ?></h4>
                        <?php if ($prop->short_description): ?>
                            <p class="hb-property-desc"><?php echo esc_html($prop->short_description); ?></p>
                        <?php endif; ?>
                        
                        <div class="hb-property-info">
                            <div class="hb-property-info-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span><?php echo $prop->capacity; ?> guests</span>
                            </div>
                            <div class="hb-property-info-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                </svg>
                                <span>₱<?php echo number_format($prop->base_price, 2); ?>/night</span>
                            </div>
                        </div>
                        
                        <div class="hb-property-status">
                            <span class="hb-status-badge hb-status-<?php echo $prop->status; ?>">
                                <?php echo ucfirst($prop->status); ?>
                            </span>
                        </div>
                        
                        <div class="hb-property-actions">
                            <button onclick='editProperty(<?php echo json_encode($prop); ?>)' class="bntm-btn-small">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                                Edit
                            </button>
                            <button onclick="deleteProperty(<?php echo $prop->id; ?>, '<?php echo esc_js($prop->name); ?>')" 
                                    class="bntm-btn-small bntm-btn-danger">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    
    <div id="propertyModal" class="hb-modal">
        <div class="hb-modal-content">
            <div class="hb-modal-header">
                <h3 id="modalTitle">Add Room</h3>
                <button class="hb-modal-close" onclick="closeModal('propertyModal')">&times;</button>
            </div>
            <div class="hb-modal-body">
                <form id="propertyForm" class="bntm-form">
                    <input type="hidden" id="property_id" name="property_id" value="">
                    
                    <div class="bntm-form-group">
                        <label>Room Type *</label>
                        <select name="type" id="property_type" required class="bntm-input">
                            <option value="room">Room</option>
                            <option value="villa">Villa</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Room Name *</label>
                        <input type="text" name="name" id="property_name" required class="bntm-input" 
                               placeholder="e.g., Deluxe Ocean View Room">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Short Description</label>
                        <input type="text" name="short_description" id="property_short_desc" class="bntm-input" 
                               maxlength="500" placeholder="Brief description (max 500 characters)">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Full Description</label>
                        <textarea name="description" id="property_description" rows="4" class="bntm-input"
                                  placeholder="Detailed description of the property..."></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Capacity (Guests) *</label>
                            <input type="number" name="capacity" id="property_capacity" required class="bntm-input" 
                                   min="1" value="2">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Base Price per Night *</label>
                            <input type="number" name="base_price" id="property_price" required class="bntm-input" 
                                   min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Status</label>
                        <select name="status" id="property_status" class="bntm-input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Amenities (one per line)</label>
                        <textarea name="amenities" id="property_amenities" rows="4" class="bntm-input"
                                  placeholder="WiFi&#10;Air Conditioning&#10;Mini Bar&#10;Ocean View"></textarea>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Property Images (URLs, one per line)</label>
                        <textarea name="images" id="property_images" rows="3" class="bntm-input"
                                  placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"></textarea>
                        <small>Enter image URLs, one per line. First image will be the main image.</small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="closeModal('propertyModal')" class="bntm-btn-secondary">
                            Cancel
                        </button>
                        <button type="submit" class="bntm-btn-primary">
                            Save Room
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
    .hb-properties-grid {display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px;}
    .hb-property-card {border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; background: white; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 1px 3px rgba(0,0,0,0.08);}
    .hb-property-card:hover {transform: translateY(-8px); box-shadow: 0 20px 25px rgba(0,0,0,0.12); border-color: #667eea;}
    .hb-property-image {position: relative; width: 100%; height: 220px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);}
    .hb-property-image img {width: 100%; height: 100%; object-fit: cover;}
    .hb-property-no-image {display: flex; align-items: center; justify-content: center; color: #a0aec0;}
    .hb-property-type {position: absolute; top: 12px; right: 12px; background: rgba(0,0,0,0.8); color: white; padding: 6px 14px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; backdrop-filter: blur(10px);}
    .hb-property-details {padding: 24px;}
    .hb-property-details h4 {margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: #1a202c;}
    .hb-property-desc {font-size: 14px; color: #718096; margin: 0 0 16px 0; line-height: 1.6;}
    .hb-property-info {display: flex; gap: 16px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #edf2f7;}
    .hb-property-info-item {display: flex; align-items: center; gap: 6px; font-size: 13px; color: #4a5568; font-weight: 500;}
    .hb-property-info-item svg {color: #667eea; width: 18px; height: 18px;}
    .hb-property-status {margin-bottom: 16px;}
    .hb-status-badge {display: inline-block; padding: 6px 14px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;}
    .hb-status-active {background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46;}
    .hb-status-inactive {background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b;}
    .hb-status-maintenance {background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #92400e;}
    .hb-property-actions {display: flex; gap: 8px; padding-top: 16px; border-top: 1px solid #edf2f7;}
    </style>
    
    <script>
    function openAddPropertyModal() {
        document.getElementById('modalTitle').textContent = 'Add Room';
        document.getElementById('propertyForm').reset();
        document.getElementById('property_id').value = '';
        openModal('propertyModal');
    }
    
    function editProperty(property) {
        document.getElementById('modalTitle').textContent = 'Edit Room';
        document.getElementById('property_id').value = property.id;
        document.getElementById('property_type').value = property.type;
        document.getElementById('property_name').value = property.name;
        document.getElementById('property_short_desc').value = property.short_description || '';
        document.getElementById('property_description').value = property.description || '';
        document.getElementById('property_capacity').value = property.capacity;
        document.getElementById('property_price').value = property.base_price;
        document.getElementById('property_status').value = property.status;
        document.getElementById('property_amenities').value = property.amenities || '';
        
        if (property.images) {
            try {
                const images = JSON.parse(property.images);
                document.getElementById('property_images').value = images.join('\n');
            } catch (e) {
                document.getElementById('property_images').value = '';
            }
        } else {
            document.getElementById('property_images').value = '';
        }
        
        openModal('propertyModal');
    }
    
    function deleteProperty(id, name) {
        if (!confirm('Delete room "' + name + '"? This cannot be undone.')) return;
        
        const formData = new FormData();
        formData.append('action', 'hb_delete_property');
        formData.append('property_id', id);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            if (json.success) location.reload();
        });
    }
    
    document.getElementById('propertyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const propertyId = document.getElementById('property_id').value;
        
        formData.append('action', propertyId ? 'hb_update_property' : 'hb_add_property');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            if (json.success) {
                closeModal('propertyModal');
                location.reload();
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Property';
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function hb_settings_tab($business_id) {
    $tax_rate = floatval(bntm_get_setting('hb_tax_rate', '12.00'));
    $nonce = wp_create_nonce('hb_settings_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Tax Configuration</h3>
        <p>Set the tax rate to be applied to all bookings</p>
        
        <form id="taxRateForm" class="bntm-form" style="max-width: 400px;">
            <div class="bntm-form-group">
                <label>Tax Rate (%)</label>
                <input type="number" name="tax_rate" id="tax_rate" 
                       class="bntm-input" 
                       min="0" max="100" step="0.01" 
                       value="<?php echo $tax_rate; ?>" 
                       required>
                <small>Default: 12.00% (Philippine VAT)</small>
            </div>
            
            <button type="submit" class="bntm-btn-primary">
                Save Tax Rate
            </button>
        </form>
        <div id="tax-rate-message" style="margin-top: 15px;"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Email Settings</h3>
        <p>Email notifications are automatically sent to customers when they submit a booking.</p>
        <div style="padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 8px;">
            <strong>Email Template:</strong><br>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Subject: "Your Booking Quotation - [Quotation Number]"</li>
                <li>Content: Confirmation message with quotation link</li>
                <li>No price details in email body (security)</li>
            </ul>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Booking Pages</h3>
        <p>Public pages for customer booking:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
            <li><strong>Browse Properties:</strong> <?php echo get_permalink(get_page_by_path('browse-rooms-villas')); ?></li>
            <li><strong>Booking Form:</strong> <?php echo get_permalink(get_page_by_path('booking-form')); ?></li>
            <li><strong>View Quotation:</strong> <?php echo get_permalink(get_page_by_path('view-quotation')); ?></li>
        </ul>
    </div>
    
    <script>
    document.getElementById('taxRateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'hb_save_tax_rate');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            document.getElementById('tax-rate-message').innerHTML = 
                '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                json.data.message + '</div>';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Tax Rate';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}


// ============================================================================
// PUBLIC SHORTCODES
// ============================================================================

function bntm_shortcode_hb_browse() {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $properties = $wpdb->get_results("
        SELECT * FROM {$properties_table}
        WHERE status = 'active'
        ORDER BY sort_order ASC, name ASC
    ");
    
    $booking_page_url = get_permalink(get_page_by_path('booking-form'));
    
    ob_start();
    ?>
    <div class="hb-browse-container">
        <div class="hb-browse-header">
            <h2>Available Rooms & Villas</h2>
            <p>Discover our premium accommodations</p>
        </div>
        
        <?php if (empty($properties)): ?>
            <div class="hb-empty-state">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <h3>No rooms available at this time</h3>
                <p>Please check back later</p>
            </div>
        <?php else: ?>
            <div class="hb-browse-grid">
                <?php foreach ($properties as $prop): 
                    $images = !empty($prop->images) ? json_decode($prop->images, true) : [];
                    $first_image = !empty($images) ? $images[0] : '';
                    $amenities = !empty($prop->amenities) ? explode("\n", trim($prop->amenities)) : [];
                ?>
                    <div class="hb-browse-card">
                        <?php if ($first_image): ?>
                            <div class="hb-browse-image">
                                <img src="<?php echo esc_url($first_image); ?>" alt="<?php echo esc_attr($prop->name); ?>">
                                <span class="hb-browse-type"><?php echo ucfirst($prop->type); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="hb-browse-image hb-browse-no-image">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span class="hb-browse-type"><?php echo ucfirst($prop->type); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="hb-browse-content">
                            <h3><?php echo esc_html($prop->name); ?></h3>
                            <?php if ($prop->short_description): ?>
                                <p class="hb-browse-desc"><?php echo esc_html($prop->short_description); ?></p>
                            <?php endif; ?>
                            
                            <div class="hb-browse-info">
                                <div class="hb-browse-info-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span>Up to <?php echo $prop->capacity; ?> guests</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($amenities)): ?>
                                <div class="hb-browse-amenities">
                                    <?php foreach (array_slice($amenities, 0, 4) as $amenity): ?>
                                        <span class="hb-amenity-badge"><?php echo esc_html(trim($amenity)); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($amenities) > 4): ?>
                                        <span class="hb-amenity-badge">+<?php echo count($amenities) - 4; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="hb-browse-footer">
                                <div class="hb-browse-price">
                                    <span class="hb-price-label">Starting from</span>
                                    <span class="hb-price-amount">₱<?php echo number_format($prop->base_price, 2); ?></span>
                                    <span class="hb-price-unit">per night</span>
                                </div>
                                <a href="<?php echo $booking_page_url . '?property=' . $prop->rand_id; ?>" 
                                   class="hb-book-btn">
                                    Book Now
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .hb-browse-container {max-width: 1200px; margin: 0 auto; padding: 32px 20px;}
    .hb-browse-header {text-align: center; margin-bottom: 48px;}
    .hb-browse-header h2 {font-size: 42px; font-weight: 800; color: #1a202c; margin: 0 0 12px 0; letter-spacing: -1px;}
    .hb-browse-header p {font-size: 18px; color: #718096; margin: 0;}
    .hb-empty-state {text-align: center; padding: 80px 20px; color: #718096;}
    .hb-empty-state svg {margin: 0 auto 24px; color: #cbd5e0; opacity: 0.8;}
    .hb-empty-state h3 {font-size: 22px; font-weight: 700; color: #4a5568; margin: 0 0 8px 0;}
    .hb-browse-grid {display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 32px;}
    .hb-browse-card {background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid #edf2f7;}
    .hb-browse-card:hover {transform: translateY(-12px); box-shadow: 0 25px 40px rgba(0,0,0,0.15);}
    .hb-browse-image {position: relative; width: 100%; height: 280px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);}
    .hb-browse-image img {width: 100%; height: 100%; object-fit: cover;}
    .hb-browse-no-image {display: flex; align-items: center; justify-content: center; color: #cbd5e0;}
    .hb-browse-type {position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.85); color: white; padding: 8px 16px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; backdrop-filter: blur(10px);}
    .hb-browse-content {padding: 28px;}
    .hb-browse-content h3 {font-size: 22px; font-weight: 700; color: #1a202c; margin: 0 0 12px 0;}
    .hb-browse-desc {font-size: 15px; color: #718096; line-height: 1.6; margin: 0 0 18px 0;}
    .hb-browse-info {margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid #edf2f7;}
    .hb-browse-info-item {display: flex; align-items: center; gap: 10px; font-size: 15px; color: #4a5568; font-weight: 500;}
    .hb-browse-info-item svg {color: #667eea; width: 20px; height: 20px;}
    .hb-browse-amenities {display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px;}
    .hb-amenity-badge {display: inline-block; padding: 6px 12px; background: #f7fafc; color: #4a5568; font-size: 12px; font-weight: 600; border-radius: 8px; border: 1px solid #e2e8f0;}
    .hb-browse-footer {display: flex; justify-content: space-between; align-items: flex-end; padding-top: 20px; border-top: 1px solid #edf2f7;}
    .hb-browse-price {display: flex; flex-direction: column;}
    .hb-price-label {font-size: 12px; color: #a0aec0; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 600;}
    .hb-price-amount {font-size: 28px; font-weight: 800; color: #1a202c;}
    .hb-price-unit {font-size: 12px; color: #718096;}
    .hb-book-btn {display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 14px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);}
    .hb-book-btn:hover {transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);}
    @media (max-width: 768px) {
        .hb-browse-grid {grid-template-columns: 1fr;}
        .hb-browse-header h2 {font-size: 32px;}
        .hb-browse-footer {flex-direction: column; gap: 16px; align-items: stretch;}
        .hb-book-btn {justify-content: center;}
    }
    </style>
    <?php
    return ob_get_clean();
}

function bntm_shortcode_hb_booking_form() {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $property_rand_id = isset($_GET['property']) ? sanitize_text_field($_GET['property']) : '';
    
    if ($property_rand_id) {
        $property = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$properties_table}
            WHERE rand_id = %s AND status = 'active'
        ", $property_rand_id));
        
        if (!$property) {
            return '<div class="bntm-notice bntm-notice-error">Property not found or unavailable.</div>';
        }
    }
    
    $all_properties = $wpdb->get_results("
        SELECT id, rand_id, name, type, base_price, capacity
        FROM {$properties_table}
        WHERE status = 'active'
        ORDER BY name ASC
    ");
    
    if (empty($all_properties)) {
        return '<div class="bntm-notice bntm-notice-error">No rooms available for booking at this time.</div>';
    }
    
    $tax_rate = floatval(bntm_get_setting('hb_tax_rate', '12.00'));
    
    ob_start();
    ?>
    <div class="hb-booking-container">
        <div class="hb-booking-header">
            <h2>Book Your Stay</h2>
            <p>Complete the form below to request a quotation</p>
        </div>
        
        <form id="bookingForm" class="hb-booking-form">
            <div class="hb-form-section">
                <h3>Select Property</h3>
                <div class="hb-form-group">
                    <label>Select Room *</label>
                    <select name="property_rand_id" id="property_select" required class="hb-input">
                        <option value="">-- Select a room --</option>
                        <?php foreach ($all_properties as $prop): ?>
                            <option value="<?php echo $prop->rand_id; ?>" 
                                    data-price="<?php echo $prop->base_price; ?>"
                                    data-capacity="<?php echo $prop->capacity; ?>"
                                    <?php selected($property_rand_id, $prop->rand_id); ?>>
                                <?php echo esc_html($prop->name); ?> - 
                                <?php echo ucfirst($prop->type); ?> 
                                (₱<?php echo number_format($prop->base_price, 2); ?>/night)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="hb-form-section">
                <h3>Booking Details</h3>
                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label>Check-in Date *</label>
                        <input type="date" name="check_in" id="check_in" required class="hb-input"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="hb-form-group">
                        <label>Check-out Date *</label>
                        <input type="date" name="check_out" id="check_out" required class="hb-input"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                </div>
                
                <div class="hb-form-group">
                    <label>Number of Guests *</label>
                    <input type="number" name="num_guests" id="num_guests" required class="hb-input"
                           min="1" value="2">
                    <small id="capacity-warning" style="color: #dc2626; display: none;">
                        This property can accommodate a maximum of <span id="max-capacity"></span> guests.
                    </small>
                </div>
            </div>
            
            <div class="hb-form-section">
                <h3>Your Information</h3>
                <div class="hb-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required class="hb-input"
                           placeholder="John Doe">
                </div>
                
                <div class="hb-form-row">
                    <div class="hb-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" required class="hb-input"
                               placeholder="john@example.com">
                    </div>
                    <div class="hb-form-group">
                        <label>Contact Number *</label>
                        <input type="tel" name="customer_phone" required class="hb-input"
                               placeholder="+63 912 345 6789">
                    </div>
                </div>
                
                <div class="hb-form-group">
                    <label>Special Requests (Optional)</label>
                    <textarea name="notes" class="hb-input" rows="3"
                              placeholder="Any special requests or requirements..."></textarea>
                </div>
            </div>
            
            <div class="hb-form-section hb-summary-section" id="booking_summary" style="display: none;">
                <h3>Booking Summary</h3>
                <div class="hb-summary-grid">
                    <div class="hb-summary-row">
                        <span>Property:</span>
                        <span id="summary_property">-</span>
                    </div>
                    <div class="hb-summary-row">
                        <span>Check-in:</span>
                        <span id="summary_checkin">-</span>
                    </div>
                    <div class="hb-summary-row">
                        <span>Check-out:</span>
                        <span id="summary_checkout">-</span>
                    </div>
                    <div class="hb-summary-row">
                        <span>Number of Nights:</span>
                        <span id="summary_nights">0</span>
                    </div>
                    <div class="hb-summary-row">
                        <span>Guests:</span>
                        <span id="summary_guests">0</span>
                    </div>
                    <div class="hb-summary-divider"></div>
                    <div class="hb-summary-row">
                        <span>Price per Night:</span>
                        <span id="summary_rate">₱0.00</span>
                    </div>
                    <div class="hb-summary-row">
                        <span>Subtotal:</span>
                        <span id="summary_subtotal">₱0.00</span>
                    </div>
                    <div class="hb-summary-row">
                        <span>Tax (<?php echo $tax_rate; ?>%):</span>
                        <span id="summary_tax">₱0.00</span>
                    </div>
                    <div class="hb-summary-divider"></div>
                    <div class="hb-summary-row hb-summary-total">
                        <span>Grand Total:</span>
                        <span id="summary_total">₱0.00</span>
                    </div>
                </div>
            </div>
            
            <div class="hb-form-actions">
                <button type="submit" class="hb-submit-btn" id="submit_btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    Submit Booking Request
                </button>
            </div>
            
            <div id="booking_message"></div>
        </form>
    </div>
    
    <style>
    .hb-booking-container {max-width: 850px; margin: 0 auto; padding: 32px 20px;}
    .hb-booking-header {text-align: center; margin-bottom: 48px;}
    .hb-booking-header h2 {font-size: 42px; font-weight: 800; color: #1a202c; margin: 0 0 12px 0; letter-spacing: -1px;}
    .hb-booking-header p {font-size: 18px; color: #718096; margin: 0;}
    .hb-booking-form {background: white; border-radius: 18px; padding: 40px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #edf2f7;}
    .hb-form-section {margin-bottom: 40px;}
    .hb-form-section h3 {font-size: 18px; font-weight: 700; color: #1a202c; margin: 0 0 24px 0; padding-bottom: 16px; border-bottom: 2px solid #667eea; position: relative;}
    .hb-form-group {margin-bottom: 24px;}
    .hb-form-group label {display: block; font-weight: 600; color: #2d3748; margin-bottom: 8px; font-size: 14px; text-transform: capitalize;}
    .hb-input {width: 100%; padding: 14px 18px; border: 1px solid #cbd5e0; border-radius: 10px; font-size: 15px; transition: all 0.2s; font-family: inherit;}
    .hb-input:focus {outline: none; border-color: #667eea; box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);}
    .hb-form-row {display: grid; grid-template-columns: 1fr 1fr; gap: 20px;}
    .hb-summary-section {background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); padding: 32px; border-radius: 14px; border: 1px solid #cbd5e0;}
    .hb-summary-grid {display: flex; flex-direction: column; gap: 16px;}
    .hb-summary-row {display: flex; justify-content: space-between; font-size: 15px;}
    .hb-summary-row span:first-child {color: #718096; font-weight: 500;}
    .hb-summary-row span:last-child {font-weight: 700; color: #1a202c;}
    .hb-summary-divider {height: 2px; background: #cbd5e0; margin: 8px 0;}
    .hb-summary-total {font-size: 18px; padding-top: 12px;}
    .hb-summary-total span {font-weight: 800; color: #1a202c;}
    .hb-form-actions {margin-top: 40px;}
    .hb-submit-btn {width: 100%; padding: 16px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);}
    .hb-submit-btn:hover:not(:disabled) {transform: translateY(-2px); box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);}
    .hb-submit-btn:active:not(:disabled) {transform: translateY(0);}
    .hb-submit-btn:disabled {background: #cbd5e0; cursor: not-allowed;}
    @media (max-width: 768px) {
        .hb-booking-form {padding: 28px;}
        .hb-form-row {grid-template-columns: 1fr;}
    }
    </style>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const taxRate = <?php echo $tax_rate; ?>;
    
    (function() {
        const form = document.getElementById('bookingForm');
        const propertySelect = document.getElementById('property_select');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const numGuestsInput = document.getElementById('num_guests');
        const summarySection = document.getElementById('booking_summary');
        
        function updateSummary() {
            const selectedOption = propertySelect.options[propertySelect.selectedIndex];
            if (!selectedOption.value || !checkInInput.value || !checkOutInput.value) {
                summarySection.style.display = 'none';
                return;
            }
            
            const pricePerNight = parseFloat(selectedOption.dataset.price);
            const maxCapacity = parseInt(selectedOption.dataset.capacity);
            const numGuests = parseInt(numGuestsInput.value);
            const checkIn = new Date(checkInInput.value);
            const checkOut = new Date(checkOutInput.value);
            
            if (checkOut <= checkIn) {
                summarySection.style.display = 'none';
                return;
            }
            
            const capacityWarning = document.getElementById('capacity-warning');
            if (numGuests > maxCapacity) {
                capacityWarning.style.display = 'block';
                document.getElementById('max-capacity').textContent = maxCapacity;
            } else {
                capacityWarning.style.display = 'none';
            }
            
            const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
            const subtotal = pricePerNight * nights;
            const taxAmount = subtotal * (taxRate / 100);
            const grandTotal = subtotal + taxAmount;
            
            document.getElementById('summary_property').textContent = selectedOption.text.split(' - ')[0];
            document.getElementById('summary_checkin').textContent = new Date(checkInInput.value).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
            document.getElementById('summary_checkout').textContent = new Date(checkOutInput.value).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
            document.getElementById('summary_nights').textContent = nights;
            document.getElementById('summary_guests').textContent = numGuests;
            document.getElementById('summary_rate').textContent = '₱' + pricePerNight.toFixed(2);
            document.getElementById('summary_subtotal').textContent = '₱' + subtotal.toFixed(2);
            document.getElementById('summary_tax').textContent = '₱' + taxAmount.toFixed(2);
            document.getElementById('summary_total').textContent = '₱' + grandTotal.toFixed(2);
            
            summarySection.style.display = 'block';
        }
        
        propertySelect.addEventListener('change', updateSummary);
        checkInInput.addEventListener('change', updateSummary);
        checkOutInput.addEventListener('change', updateSummary);
        numGuestsInput.addEventListener('input', updateSummary);
        
        checkInInput.addEventListener('change', function() {
            const checkIn = new Date(this.value);
            checkIn.setDate(checkIn.getDate() + 1);
            checkOutInput.min = checkIn.toISOString().split('T')[0];
        });
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'hb_submit_booking');
            
            const submitBtn = document.getElementById('submit_btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msgDiv = document.getElementById('booking_message');
                
                if (json.success) {
                    msgDiv.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    form.reset();
                    summarySection.style.display = 'none';
                    
                    if (json.data.quotation_url) {
                        setTimeout(function() {
                            window.location.href = json.data.quotation_url;
                        }, 2000);
                    }
                } else {
                    msgDiv.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Submit Booking Request';
                }
            })
            .catch(err => {
                console.error('Error:', err);
                const msgDiv = document.getElementById('booking_message');
                msgDiv.innerHTML = '<div class="bntm-notice bntm-notice-error">An error occurred. Please try again.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Submit Booking Request';
            });
        });
        
        updateSummary();
    })();
    </script>
    <?php
    return ob_get_clean();
}


function bntm_shortcode_hb_view_quotation() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    $properties_table = $wpdb->prefix . 'hb_properties';
    
    $quotation_number = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    
    if (empty($quotation_number)) {
        return '<div class="bntm-notice bntm-notice-error">Invalid quotation link.</div>';
    }
    
    $booking = $wpdb->get_row($wpdb->prepare("
        SELECT b.*, p.name as property_name, p.type as property_type, p.description, p.amenities, p.images
        FROM {$bookings_table} b
        LEFT JOIN {$properties_table} p ON b.property_id = p.id
        WHERE b.quotation_number = %s
    ", $quotation_number));
    
    if (!$booking) {
        return '<div class="bntm-notice bntm-notice-error">Quotation not found.</div>';
    }
    
    $images = !empty($booking->images) ? json_decode($booking->images, true) : [];
    $amenities = !empty($booking->amenities) ? explode("\n", trim($booking->amenities)) : [];
    
    ob_start();
    ?>
    <div class="hb-quotation-container">
        <div class="hb-quotation-header">
            <div class="hb-quotation-logo">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <h1>Booking Quotation</h1>
            <p class="hb-quotation-number">Quotation #: <?php echo esc_html($booking->quotation_number); ?></p>
            <p class="hb-quotation-date">Generated: <?php echo date('F d, Y', strtotime($booking->created_at)); ?></p>
        </div>
        
        <div class="hb-quotation-content">
            <?php if (!empty($images)): ?>
            <div class="hb-quotation-image">
                <img src="<?php echo esc_url($images[0]); ?>" alt="<?php echo esc_attr($booking->property_name); ?>">
            </div>
            <?php endif; ?>
            
            <div class="hb-quotation-section">
                <h2>Property Details</h2>
                <div class="hb-quotation-grid">
                    <div class="hb-quotation-item">
                        <strong>Property:</strong>
                        <span><?php echo esc_html($booking->property_name); ?></span>
                    </div>
                    <div class="hb-quotation-item">
                        <strong>Type:</strong>
                        <span><?php echo ucfirst($booking->property_type); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($amenities)): ?>
                <div style="margin-top: 16px;">
                    <strong>Amenities:</strong>
                    <div class="hb-quotation-amenities">
                        <?php foreach ($amenities as $amenity): ?>
                            <span class="hb-amenity-tag"><?php echo esc_html(trim($amenity)); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="hb-quotation-section">
                <h2>Booking Information</h2>
                <div class="hb-quotation-grid">
                    <div class="hb-quotation-item">
                        <strong>Check-in:</strong>
                        <span><?php echo date('l, F d, Y', strtotime($booking->check_in)); ?></span>
                    </div>
                    <div class="hb-quotation-item">
                        <strong>Check-out:</strong>
                        <span><?php echo date('l, F d, Y', strtotime($booking->check_out)); ?></span>
                    </div>
                    <div class="hb-quotation-item">
                        <strong>Number of Nights:</strong>
                        <span><?php echo $booking->num_nights; ?></span>
                    </div>
                    <div class="hb-quotation-item">
                        <strong>Number of Guests:</strong>
                        <span><?php echo $booking->num_guests; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="hb-quotation-section">
                <h2>Guest Information</h2>
                <div class="hb-quotation-grid">
                    <div class="hb-quotation-item">
                        <strong>Name:</strong>
                        <span><?php echo esc_html($booking->customer_name); ?></span>
                    </div>
                    <div class="hb-quotation-item">
                        <strong>Email:</strong>
                        <span><?php echo esc_html($booking->customer_email); ?></span>
                    </div>
                    <div class="hb-quotation-item">
                        <strong>Phone:</strong>
                        <span><?php echo esc_html($booking->customer_phone); ?></span>
                    </div>
                </div>
                
                <?php if ($booking->notes): ?>
                <div style="margin-top: 16px;">
                    <strong>Special Requests:</strong>
                    <p style="margin: 8px 0 0 0; color: #6b7280;"><?php echo esc_html($booking->notes); ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="hb-quotation-section hb-quotation-pricing">
                <h2>Price Breakdown</h2>
                <div class="hb-quotation-price-grid">
                    <div class="hb-quotation-price-row">
                        <span>Price per Night:</span>
                        <span>₱<?php echo number_format($booking->subtotal / $booking->num_nights, 2); ?></span>
                    </div>
                    <div class="hb-quotation-price-row">
                        <span><?php echo $booking->num_nights; ?> Night<?php echo $booking->num_nights > 1 ? 's' : ''; ?> x ₱<?php echo number_format($booking->subtotal / $booking->num_nights, 2); ?></span>
                        <span>₱<?php echo number_format($booking->subtotal, 2); ?></span>
                    </div>
                    <div class="hb-quotation-price-row">
                        <span>Tax (<?php echo number_format($booking->tax_rate, 2); ?>%):</span>
                        <span>₱<?php echo number_format($booking->tax_amount, 2); ?></span>
                    </div>
                    <div class="hb-quotation-divider"></div>
                    <div class="hb-quotation-price-row hb-quotation-total">
                        <span>Grand Total:</span>
                        <span>₱<?php echo number_format($booking->grand_total, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="hb-quotation-section">
                <h2>Booking Status</h2>
                <div class="hb-quotation-status-badge hb-status-<?php echo $booking->status; ?>">
                    <?php 
                    $status_labels = [
                        'quoted' => 'Quotation Sent',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed'
                    ];
                    echo esc_html($status_labels[$booking->status] ?? ucfirst($booking->status));
                    ?>
                </div>
                <p style="margin-top: 16px; color: #6b7280; font-size: 14px;">
                    <?php if ($booking->status === 'quoted'): ?>
                        Your quotation has been generated. Please contact us to confirm your booking.
                    <?php elseif ($booking->status === 'confirmed'): ?>
                        Your booking is confirmed! We look forward to hosting you.
                    <?php elseif ($booking->status === 'cancelled'): ?>
                        This booking has been cancelled.
                    <?php elseif ($booking->status === 'completed'): ?>
                        Thank you for staying with us!
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="hb-quotation-footer">
                <p>For inquiries or to confirm your booking, please contact us.</p>
                <div class="hb-quotation-actions">
                    <button onclick="window.print()" class="hb-quotation-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        Print Quotation
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .hb-quotation-container {max-width: 950px; margin: 0 auto; padding: 32px 20px;}
    .hb-quotation-header {text-align: center; padding: 48px 20px; border-bottom: 2px solid #edf2f7; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 18px 18px 0 0;}
    .hb-quotation-logo {margin: 0 auto 24px; display: flex; justify-content: center;}
    .hb-quotation-logo svg {color: #667eea; width: 56px; height: 56px;}
    .hb-quotation-header h1 {font-size: 40px; font-weight: 800; color: #1a202c; margin: 0 0 16px 0; letter-spacing: -1px;}
    .hb-quotation-number {font-size: 18px; font-weight: 700; color: #667eea; margin: 0 0 6px 0;}
    .hb-quotation-date {font-size: 14px; color: #718096; margin: 0;}
    .hb-quotation-content {background: white; padding: 48px; margin-top: 0; border-radius: 0 0 18px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #edf2f7; border-top: none;}
    .hb-quotation-image {width: 100%; height: 340px; overflow: hidden; border-radius: 14px; margin-bottom: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);}
    .hb-quotation-image img {width: 100%; height: 100%; object-fit: cover;}
    .hb-quotation-section {margin-bottom: 40px; padding-bottom: 40px; border-bottom: 1px solid #edf2f7;}
    .hb-quotation-section:last-child {border-bottom: none; margin-bottom: 0; padding-bottom: 0;}
    .hb-quotation-section h2 {font-size: 20px; font-weight: 700; color: #1a202c; margin: 0 0 24px 0; position: relative; padding-bottom: 12px;}
    .hb-quotation-section h2:after {content: ''; position: absolute; bottom: 0; left: 0; width: 40px; height: 3px; background: #667eea; border-radius: 2px;}
    .hb-quotation-grid {display: grid; grid-template-columns: 1fr 1fr; gap: 24px;}
    .hb-quotation-item {display: flex; flex-direction: column; gap: 6px;}
    .hb-quotation-item strong {font-weight: 700; color: #2d3748; font-size: 13px; text-transform: uppercase; letter-spacing: 0.3px; color: #718096;}
    .hb-quotation-item span {color: #1a202c; font-size: 16px; font-weight: 500;}
    .hb-quotation-amenities {display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px;}
    .hb-amenity-tag {display: inline-block; padding: 8px 14px; background: #f7fafc; color: #4a5568; font-size: 13px; font-weight: 600; border-radius: 8px; border: 1px solid #cbd5e0;}
    .hb-quotation-pricing {background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); padding: 32px; border-radius: 14px; border: 1px solid #cbd5e0;}
    .hb-quotation-price-grid {display: flex; flex-direction: column; gap: 18px;}
    .hb-quotation-price-row {display: flex; justify-content: space-between; font-size: 15px;}
    .hb-quotation-price-row span:first-child {color: #718096; font-weight: 500;}
    .hb-quotation-price-row span:last-child {font-weight: 700; color: #1a202c;}
    .hb-quotation-divider {height: 2px; background: #cbd5e0; margin: 12px 0;}
    .hb-quotation-total {font-size: 20px; padding-top: 12px;}
    .hb-quotation-total span {font-weight: 800; color: #1a202c;}
    .hb-quotation-status-badge {display: inline-block; padding: 12px 24px; border-radius: 10px; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;}
    .hb-quotation-status-badge.hb-status-quoted {background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #0c2d6b;}
    .hb-quotation-status-badge.hb-status-confirmed {background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46;}
    .hb-quotation-status-badge.hb-status-cancelled {background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b;}
    .hb-quotation-status-badge.hb-status-completed {background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #3730a3;}
    .hb-quotation-footer {text-align: center; padding-top: 40px; border-top: 2px solid #edf2f7;}
    .hb-quotation-footer p {color: #718096; margin: 0 0 24px 0;}
    .hb-quotation-actions {display: flex; justify-content: center;}
    .hb-quotation-btn {display: inline-flex; align-items: center; gap: 10px; padding: 12px 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);}
    .hb-quotation-btn:hover {transform: translateY(-2px); box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);}
    @media print {
        .hb-quotation-actions {display: none;}
        .hb-quotation-container {padding: 0;}
    }
    @media (max-width: 768px) {
        .hb-quotation-grid {grid-template-columns: 1fr;}
        .hb-quotation-content {padding: 28px;}
    }
    </style>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

function bntm_ajax_hb_add_property() {
    check_ajax_referer('hb_properties_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_properties';
    
    $images_raw = sanitize_textarea_field($_POST['images']);
    $images = array_filter(array_map('trim', explode("\n", $images_raw)));
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'name' => sanitize_text_field($_POST['name']),
        'type' => sanitize_text_field($_POST['type']),
        'short_description' => sanitize_text_field($_POST['short_description']),
        'description' => sanitize_textarea_field($_POST['description']),
        'capacity' => intval($_POST['capacity']),
        'base_price' => floatval($_POST['base_price']),
        'status' => sanitize_text_field($_POST['status']),
        'amenities' => sanitize_textarea_field($_POST['amenities']),
        'images' => json_encode($images)
    ];
    
    $result = $wpdb->insert($table, $data, [
        '%s','%d','%s','%s','%s','%s','%d','%f','%s','%s','%s'
    ]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Property added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add property']);
    }
}

function bntm_ajax_hb_update_property() {
    check_ajax_referer('hb_properties_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_properties';
    
    $property_id = intval($_POST['property_id']);
    $images_raw = sanitize_textarea_field($_POST['images']);
    $images = array_filter(array_map('trim', explode("\n", $images_raw)));
    
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'type' => sanitize_text_field($_POST['type']),
        'short_description' => sanitize_text_field($_POST['short_description']),
        'description' => sanitize_textarea_field($_POST['description']),
        'capacity' => intval($_POST['capacity']),
        'base_price' => floatval($_POST['base_price']),
        'status' => sanitize_text_field($_POST['status']),
        'amenities' => sanitize_textarea_field($_POST['amenities']),
        'images' => json_encode($images)
    ];
    
    $result = $wpdb->update($table, $data, ['id' => $property_id], [
        '%s','%s','%s','%s','%d','%f','%s','%s','%s'
    ], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Property updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update property']);
    }
}

function bntm_ajax_hb_delete_property() {
    check_ajax_referer('hb_properties_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_properties';
    $property_id = intval($_POST['property_id']);
    
    $result = $wpdb->delete($table, ['id' => $property_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Property deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete property']);
    }
}

function bntm_ajax_hb_update_booking_status() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_bookings';
    
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $result = $wpdb->update($table, ['status' => $status], ['id' => $booking_id], ['%s'], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Booking status updated!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}

function bntm_ajax_hb_save_tax_rate() {
    check_ajax_referer('hb_settings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $tax_rate = floatval($_POST['tax_rate']);
    bntm_set_setting('hb_tax_rate', $tax_rate);
    
    wp_send_json_success(['message' => 'Tax rate saved successfully!']);
}

function bntm_ajax_hb_delete_booking() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'hb_bookings';
    $booking_id = intval($_POST['booking_id']);
    
    $result = $wpdb->delete($table, ['id' => $booking_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Booking deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete booking']);
    }
}

function bntm_ajax_hb_resend_quotation() {
    check_ajax_referer('hb_bookings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $booking_id = intval($_POST['booking_id']);
    $sent = hb_send_quotation_email($booking_id);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Quotation email sent!']);
    } else {
        wp_send_json_error(['message' => 'Failed to send email']);
    }
}

function bntm_ajax_hb_submit_booking() {
    global $wpdb;
    $properties_table = $wpdb->prefix . 'hb_properties';
    $bookings_table = $wpdb->prefix . 'hb_bookings';
    
    $property_rand_id = sanitize_text_field($_POST['property_rand_id']);
    $check_in = sanitize_text_field($_POST['check_in']);
    $check_out = sanitize_text_field($_POST['check_out']);
    $num_guests = intval($_POST['num_guests']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $notes = sanitize_textarea_field($_POST['notes']);
    
    $property = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$properties_table} WHERE rand_id = %s AND status = 'active'",
        $property_rand_id
    ));
    
    if (!$property) {
        wp_send_json_error(['message' => 'Property not found']);
    }
    
    $check_in_dt = new DateTime($check_in);
    $check_out_dt = new DateTime($check_out);
    $nights = $check_in_dt->diff($check_out_dt)->days;
    
    if ($nights < 1) {
        wp_send_json_error(['message' => 'Invalid dates']);
    }
    
    $tax_rate = floatval(bntm_get_setting('hb_tax_rate', '12.00'));
    $subtotal = $property->base_price * $nights;
    $tax_amount = $subtotal * ($tax_rate / 100);
    $grand_total = $subtotal + $tax_amount;
    
    $quotation_number = hb_generate_quotation_number();
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'quotation_number' => $quotation_number,
        'business_id' => $property->business_id,
        'property_id' => $property->id,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'num_guests' => $num_guests,
        'num_nights' => $nights,
        'subtotal' => $subtotal,
        'tax_rate' => $tax_rate,
        'tax_amount' => $tax_amount,
        'grand_total' => $grand_total,
        'status' => 'quoted',
        'notes' => $notes
    ];
    
    $result = $wpdb->insert($bookings_table, $data, [
        '%s','%s','%d','%d','%s','%s','%s','%s','%s','%d','%d','%f','%f','%f','%f','%s','%s'
    ]);
    
    if ($result) {
        $booking_id = $wpdb->insert_id;
        hb_send_quotation_email($booking_id);
        
        $quotation_url = get_permalink(get_page_by_path('view-quotation')) . '?q=' . $quotation_number;
        
        wp_send_json_success([
            'message' => 'Booking request submitted! Check your email for the quotation link.',
            'quotation_url' => $quotation_url
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to submit booking']);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function hb_generate_quotation_number() {
    return 'QT-' . strtoupper(substr(md5(time() . rand()), 0, 10));
}

function hb_send_quotation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'hb_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d",
        $booking_id
    ));
    
    if (!$booking) return false;
    
    $quotation_url = get_permalink(get_page_by_path('view-quotation')) . '?q=' . $booking->quotation_number;
    
    $subject = 'Your Booking Quotation - ' . $booking->quotation_number;
    
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Thank you for your booking request!\n\n";
    $message .= "Your quotation has been generated and is ready for review.\n\n";
    $message .= "View Your Quotation:\n{$quotation_url}\n\n";
    $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
    $message .= "Best regards,\nHotel & Villa Management";
    
    $sent = wp_mail($booking->customer_email, $subject, $message);
    
    if ($sent) {
        $wpdb->update($table, ['email_sent' => 1], ['id' => $booking_id], ['%d'], ['%d']);
    }
    
    return $sent;
}

?>