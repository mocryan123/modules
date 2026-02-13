<?php
/**
 * Module Name: Booking Management
 * Module Slug: bm
 * Description: Hotel booking system with customer portal, yacht/car rentals, quotation generation, and PayMaya integration
 * Version: 1.0.1 - FIXED
 * Author: Your Name
 * Icon: 🏨
 * 
 * CHANGELOG v1.0.1:
 * - Fixed syntax errors in wpdb prepare statements
 * - Added missing AJAX price lookup functions
 * - Fixed email template syntax using heredoc
 * - Added PayMaya webhook handler
 * - Added error logging
 * - Added missing helper functions
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_BM_PATH', dirname(__FILE__) . '/');
define('BNTM_BM_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================================

/**
 * Define module pages
 */
function bntm_bm_get_pages() {
    return [
        'Booking Dashboard' => '[bm_dashboard]',
        'Book Hotel' => '[bm_book_hotel]',
        'Book Yacht' => '[bm_book_yacht]',
        'Book Car' => '[bm_book_car]',
        'View Quotation' => '[bm_view_quotation]',
    ];
}

/**
 * Define database tables
 */
function bntm_bm_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'bm_bookings' => "CREATE TABLE {$prefix}bm_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_address TEXT,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            num_adults INT NOT NULL DEFAULT 1,
            num_children INT NOT NULL DEFAULT 0,
            special_requests TEXT,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            booking_status VARCHAR(50) NOT NULL DEFAULT 'pending',
            quotation_sent_at DATETIME NULL,
            confirmed_at DATETIME NULL,
            provider_phone_confirmed TINYINT(1) DEFAULT 0,
            provider_phone_confirmed_at DATETIME NULL,
            payment_method VARCHAR(50) NULL,
            payment_gateway VARCHAR(50) NULL,
            payment_transaction_id VARCHAR(255) NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_rand_id (rand_id),
            INDEX idx_email (customer_email),
            INDEX idx_dates (check_in_date, check_out_date),
            INDEX idx_status (booking_status)
        ) {$charset};",
        
        'bm_rooms' => "CREATE TABLE {$prefix}bm_rooms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            booking_id BIGINT UNSIGNED NOT NULL,
            room_type VARCHAR(100) NOT NULL,
            room_name VARCHAR(255) NOT NULL,
            num_rooms INT NOT NULL DEFAULT 1,
            price_per_night DECIMAL(10,2) NOT NULL,
            num_nights INT NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (booking_id)
        ) {$charset};",
        
        'bm_yacht_rentals' => "CREATE TABLE {$prefix}bm_yacht_rentals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            booking_id BIGINT UNSIGNED NOT NULL,
            yacht_type VARCHAR(100) NOT NULL,
            rental_date DATE NOT NULL,
            rental_duration INT NOT NULL,
            duration_unit VARCHAR(20) NOT NULL DEFAULT 'hours',
            num_guests INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL,
            additional_services TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (booking_id)
        ) {$charset};",
        
        'bm_car_rentals' => "CREATE TABLE {$prefix}bm_car_rentals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            booking_id BIGINT UNSIGNED NOT NULL,
            car_type VARCHAR(100) NOT NULL,
            pickup_date DATE NOT NULL,
            return_date DATE NOT NULL,
            num_days INT NOT NULL,
            with_driver TINYINT(1) DEFAULT 0,
            price_per_day DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_booking (booking_id)
        ) {$charset};",
        
        'bm_room_types' => "CREATE TABLE {$prefix}bm_room_types (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            room_type VARCHAR(100) NOT NULL,
            description TEXT,
            price_per_night DECIMAL(10,2) NOT NULL,
            max_occupancy INT NOT NULL DEFAULT 2,
            amenities TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_active (is_active)
        ) {$charset};",
        
        'bm_yacht_types' => "CREATE TABLE {$prefix}bm_yacht_types (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            yacht_name VARCHAR(255) NOT NULL,
            description TEXT,
            price_per_hour DECIMAL(10,2) NOT NULL,
            price_per_day DECIMAL(10,2) NOT NULL,
            max_guests INT NOT NULL DEFAULT 10,
            features TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_active (is_active)
        ) {$charset};",
        
        'bm_car_types' => "CREATE TABLE {$prefix}bm_car_types (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            car_name VARCHAR(255) NOT NULL,
            description TEXT,
            price_per_day DECIMAL(10,2) NOT NULL,
            driver_fee DECIMAL(10,2) DEFAULT 0,
            max_passengers INT NOT NULL DEFAULT 4,
            features TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_active (is_active)
        ) {$charset};",
    ];
}

/**
 * Register shortcodes
 */
function bntm_bm_get_shortcodes() {
    return [
        'bm_dashboard' => 'bntm_shortcode_bm_dashboard',
        'bm_book_hotel' => 'bntm_shortcode_bm_book_hotel',
        'bm_book_yacht' => 'bntm_shortcode_bm_book_yacht',
        'bm_book_car' => 'bntm_shortcode_bm_book_car',
        'bm_view_quotation' => 'bntm_shortcode_bm_view_quotation',
    ];
}

// Register shortcodes with WordPress
add_shortcode('bm_dashboard', 'bntm_shortcode_bm_dashboard');
add_shortcode('bm_book_hotel', 'bntm_shortcode_bm_book_hotel');
add_shortcode('bm_book_yacht', 'bntm_shortcode_bm_book_yacht');
add_shortcode('bm_book_car', 'bntm_shortcode_bm_book_car');
add_shortcode('bm_view_quotation', 'bntm_shortcode_bm_view_quotation');

// Generate Booking pages (Dashboard, Customer Form, View Quotation)
add_action('wp_ajax_bntm_bm_generate_pages', 'bntm_ajax_bm_generate_pages');
function bntm_ajax_bm_generate_pages() {
    check_ajax_referer('bntm_bm_action');

    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }

    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);

    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error('Unauthorized.');
    }

    // Dashboard page
    $dashboard = get_page_by_title('Dashboard');
    if (!$dashboard) {
        wp_insert_post([
            'post_title'   => 'Dashboard',
            'post_content' => '[bm_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);
    }

    // Book Hotel page
    $book_hotel = get_page_by_title('Book Hotel');
    if (!$book_hotel) {
        wp_insert_post([
            'post_title'   => 'Book Hotel',
            'post_content' => '[bm_book_hotel]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);
    }

    // Book Yacht page
    $book_yacht = get_page_by_title('Book Yacht');
    if (!$book_yacht) {
        wp_insert_post([
            'post_title'   => 'Book Yacht',
            'post_content' => '[bm_book_yacht]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);
    }

    // Book Car page
    $book_car = get_page_by_title('Book Car');
    if (!$book_car) {
        wp_insert_post([
            'post_title'   => 'Book Car',
            'post_content' => '[bm_book_car]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);
    }

    // View Quotation page
    $view_quote = get_page_by_title('View Quotation');
    if (!$view_quote) {
        wp_insert_post([
            'post_title'   => 'View Quotation',
            'post_content' => '[bm_view_quotation]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => get_current_user_id()
        ]);
    }

    // Ensure tables exist
    bntm_bm_create_tables();

    wp_send_json_success('Booking pages and tables created successfully!');
}

/**
 * Create tables
 */
function bntm_bm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_bm_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

// Dashboard AJAX
add_action('wp_ajax_bm_save_room_type', 'bntm_ajax_bm_save_room_type');
add_action('wp_ajax_bm_delete_room_type', 'bntm_ajax_bm_delete_room_type');
add_action('wp_ajax_bm_save_yacht_type', 'bntm_ajax_bm_save_yacht_type');
add_action('wp_ajax_bm_delete_yacht_type', 'bntm_ajax_bm_delete_yacht_type');
add_action('wp_ajax_bm_save_car_type', 'bntm_ajax_bm_save_car_type');
add_action('wp_ajax_bm_delete_car_type', 'bntm_ajax_bm_delete_car_type');
add_action('wp_ajax_bm_update_booking_status', 'bntm_ajax_bm_update_booking_status');
add_action('wp_ajax_bm_confirm_provider_phone', 'bntm_ajax_bm_confirm_provider_phone');
add_action('wp_ajax_bm_resend_quotation', 'bntm_ajax_bm_resend_quotation');

// Customer form AJAX (both logged in and public)
add_action('wp_ajax_bm_submit_booking', 'bntm_ajax_bm_submit_booking');
add_action('wp_ajax_nopriv_bm_submit_booking', 'bntm_ajax_bm_submit_booking');
add_action('wp_ajax_bm_get_room_price', 'bntm_ajax_bm_get_room_price');
add_action('wp_ajax_nopriv_bm_get_room_price', 'bntm_ajax_bm_get_room_price');
add_action('wp_ajax_bm_get_yacht_price', 'bntm_ajax_bm_get_yacht_price');
add_action('wp_ajax_nopriv_bm_get_yacht_price', 'bntm_ajax_bm_get_yacht_price');
add_action('wp_ajax_bm_get_car_price', 'bntm_ajax_bm_get_car_price');
add_action('wp_ajax_nopriv_bm_get_car_price', 'bntm_ajax_bm_get_car_price');

// Payment AJAX
add_action('wp_ajax_bm_process_payment', 'bntm_ajax_bm_process_payment');
add_action('wp_ajax_nopriv_bm_process_payment', 'bntm_ajax_bm_process_payment');

// Settings AJAX
add_action('wp_ajax_bm_save_hotel_info', 'bntm_ajax_bm_save_hotel_info');
add_action('wp_ajax_bm_save_paymaya_settings', 'bntm_ajax_bm_save_paymaya_settings');

// Payment success redirect
add_action('template_redirect', 'bm_handle_payment_success', 5);

// PayMaya webhook
add_action('rest_api_init', function() {
    register_rest_route('bm/v1', '/paymaya-webhook', [
        'methods' => 'POST',
        'callback' => 'bm_handle_paymaya_webhook',
        'permission_callback' => '__return_true'
    ]);
});

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_bm_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the booking dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-bm-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                Bookings
            </a>
            <a href="?tab=rooms" class="bntm-tab <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>">
                Room Types
            </a>
            <a href="?tab=yachts" class="bntm-tab <?php echo $active_tab === 'yachts' ? 'active' : ''; ?>">
                Yacht Fleet
            </a>
            <a href="?tab=cars" class="bntm-tab <?php echo $active_tab === 'cars' ? 'active' : ''; ?>">
                Car Fleet
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo bm_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'bookings'): ?>
                <?php echo bm_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'rooms'): ?>
                <?php echo bm_rooms_tab($business_id); ?>
            <?php elseif ($active_tab === 'yachts'): ?>
                <?php echo bm_yachts_tab($business_id); ?>
            <?php elseif ($active_tab === 'cars'): ?>
                <?php echo bm_cars_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo bm_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Booking Management', $content);
}

// ============================================================================
// TAB RENDERING FUNCTIONS
// ============================================================================

/**
 * Overview Tab
 */
function bm_overview_tab($business_id) {
    $stats = bm_get_stats($business_id);
    
    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Bookings</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['total_bookings']); ?></p>
            <span class="bntm-stat-label">All Time</span>
        </div>
        <div class="bntm-stat-card">
            <h3>Pending Bookings</h3>
            <p class="bntm-stat-number bntm-stat-warning"><?php echo esc_html($stats['pending_bookings']); ?></p>
            <span class="bntm-stat-label">Awaiting Confirmation</span>
        </div>
        <div class="bntm-stat-card">
            <h3>Confirmed Bookings</h3>
            <p class="bntm-stat-number bntm-stat-success"><?php echo esc_html($stats['confirmed_bookings']); ?></p>
            <span class="bntm-stat-label">Active</span>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Revenue</h3>
            <p class="bntm-stat-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></p>
            <span class="bntm-stat-label">Lifetime</span>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Recent Bookings</h3>
        <?php echo bm_render_recent_bookings($business_id, 10); ?>
    </div>
    
    <div class="bntm-form-section">
        <h3>Quick Actions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <a href="?tab=bookings" class="bntm-btn-primary" style="text-align: center; padding: 15px;">
                View All Bookings
            </a>
            <a href="?tab=rooms" class="bntm-btn-secondary" style="text-align: center; padding: 15px;">
                Manage Rooms
            </a>
            <a href="?tab=yachts" class="bntm-btn-secondary" style="text-align: center; padding: 15px;">
                Manage Yachts
            </a>
            <a href="?tab=cars" class="bntm-btn-secondary" style="text-align: center; padding: 15px;">
                Manage Cars
            </a>
        </div>
    </div>
    
    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #ffffff;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #3b82f6;
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        margin: 0;
        font-size: 32px;
        font-weight: 700;
        color: #111827;
    }
    .bntm-stat-number.bntm-stat-warning {
        color: #f59e0b;
    }
    .bntm-stat-number.bntm-stat-success {
        color: #10b981;
    }
    .bntm-stat-label {
        font-size: 12px;
        color: #9ca3af;
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Bookings Tab
 */
function bm_bookings_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bm_bookings';
    
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    
    $where = "WHERE business_id = %d";
    $params = [$business_id];
    
    if ($filter_status !== 'all') {
        $where .= " AND booking_status = %s";
        $params[] = $filter_status;
    }
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$bookings_table} {$where} ORDER BY created_at DESC",
        ...$params
    ));
    
    $nonce = wp_create_nonce('bm_booking_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">All Bookings</h3>
            <div>
                <select id="filter-status" onchange="window.location.href='?tab=bookings&filter_status='+this.value">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>All Status</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                    <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmed</option>
                    <option value="checked_in" <?php selected($filter_status, 'checked_in'); ?>>Checked In</option>
                    <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
                    <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Cancelled</option>
                </select>
            </div>
        </div>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Booking ID</th>
                    <th>Customer</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th>Total Amount</th>
                    <th>Payment Status</th>
                    <th>Booking Status</th>
                    <th>Phone Confirmed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                <tr><td colspan="9" style="text-align:center;">No bookings found</td></tr>
                <?php else: foreach ($bookings as $booking): ?>
                <tr>
                    <td><strong>#<?php echo esc_html($booking->rand_id); ?></strong></td>
                    <td>
                        <?php echo esc_html($booking->customer_name); ?><br>
                        <small style="color: #6b7280;"><?php echo esc_html($booking->customer_email); ?></small>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($booking->check_in_date)); ?></td>
                    <td><?php echo date('M d, Y', strtotime($booking->check_out_date)); ?></td>
                    <td><strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong></td>
                    <td>
                        <span class="bm-badge bm-badge-<?php echo $booking->payment_status; ?>">
                            <?php echo ucfirst($booking->payment_status); ?>
                        </span>
                    </td>
                    <td>
                        <select class="booking-status-select" data-id="<?php echo $booking->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            <option value="pending" <?php selected($booking->booking_status, 'pending'); ?>>Pending</option>
                            <option value="confirmed" <?php selected($booking->booking_status, 'confirmed'); ?>>Confirmed</option>
                            <option value="checked_in" <?php selected($booking->booking_status, 'checked_in'); ?>>Checked In</option>
                            <option value="completed" <?php selected($booking->booking_status, 'completed'); ?>>Completed</option>
                            <option value="cancelled" <?php selected($booking->booking_status, 'cancelled'); ?>>Cancelled</option>
                        </select>
                    </td>
                    <td>
                        <?php if ($booking->provider_phone_confirmed): ?>
                            <span style="color: #10b981;">✓ Confirmed</span><br>
                            <small><?php echo date('M d, H:i', strtotime($booking->provider_phone_confirmed_at)); ?></small>
                        <?php else: ?>
                            <button class="bntm-btn-small bntm-btn-primary confirm-phone-btn" 
                                    data-id="<?php echo $booking->id; ?>" 
                                    data-phone="<?php echo esc_attr($booking->customer_phone); ?>"
                                    data-nonce="<?php echo $nonce; ?>">
                                Confirm Call
                            </button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo home_url('/view-quotation/?booking_id=' . esc_attr($booking->rand_id)); ?>" 
                           class="bntm-btn-small" target="_blank">
                            View Details
                        </a>
                        <button class="bntm-btn-small bntm-btn-secondary resend-quotation-btn" 
                                data-id="<?php echo $booking->id; ?>" 
                                data-nonce="<?php echo $nonce; ?>">
                            Resend Quote
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <style>
    .bm-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .bm-badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .bm-badge-paid {
        background: #d1fae5;
        color: #065f46;
    }
    .bm-badge-partial {
        background: #dbeafe;
        color: #1e40af;
    }
    .bm-badge-refunded {
        background: #e5e7eb;
        color: #374151;
    }
    .booking-status-select {
        padding: 6px 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    </style>
    
    <script>
    (function() {
        // Update booking status
        document.querySelectorAll('.booking-status-select').forEach(select => {
            select.addEventListener('change', function() {
                const formData = new FormData();
                formData.append('action', 'bm_update_booking_status');
                formData.append('booking_id', this.dataset.id);
                formData.append('status', this.value);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Booking status updated!');
                    } else {
                        alert('Failed to update status');
                        location.reload();
                    }
                });
            });
        });
        
        // Confirm phone
        document.querySelectorAll('.confirm-phone-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const phone = this.dataset.phone;
                if (!confirm(`Confirm that you called ${phone}?`)) return;
                
                const formData = new FormData();
                formData.append('action', 'bm_confirm_provider_phone');
                formData.append('booking_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                this.disabled = true;
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Phone confirmation recorded!');
                        location.reload();
                    } else {
                        alert('Failed to confirm');
                        this.disabled = false;
                    }
                });
            });
        });
        
        // Resend quotation
        document.querySelectorAll('.resend-quotation-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Resend quotation email to customer?')) return;
                
                const formData = new FormData();
                formData.append('action', 'bm_resend_quotation');
                formData.append('booking_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                this.disabled = true;
                this.textContent = 'Sending...';
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    this.disabled = false;
                    this.textContent = 'Resend Quote';
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Room Types Tab
 */
function bm_rooms_tab($business_id) {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'bm_room_types';
    
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$rooms_table} WHERE business_id = %d ORDER BY created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('bm_room_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Room Types</h3>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Room Type</th>
                    <th>Description</th>
                    <th>Price/Night</th>
                    <th>Max Occupancy</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                <tr><td colspan="6" style="text-align:center;">No room types configured</td></tr>
                <?php else: foreach ($rooms as $room): ?>
                <tr>
                    <td><strong><?php echo esc_html($room->room_type); ?></strong></td>
                    <td><?php echo esc_html(substr($room->description, 0, 50)); ?>...</td>
                    <td><strong>₱<?php echo number_format($room->price_per_night, 2); ?></strong></td>
                    <td><?php echo esc_html($room->max_occupancy); ?> guests</td>
                    <td>
                        <span class="bm-badge bm-badge-<?php echo $room->is_active ? 'paid' : 'pending'; ?>">
                            <?php echo $room->is_active ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small edit-room-btn" data-room='<?php echo json_encode($room); ?>'>
                            Edit
                        </button>
                        <button class="bntm-btn-small bntm-btn-danger delete-room-btn" 
                                data-id="<?php echo $room->id; ?>" 
                                data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bntm-form-section">
        <h3 id="room-form-title">Add Room Type</h3>
        <form id="room-form" class="bntm-form">
            <input type="hidden" name="room_id" id="room-id">
            
            <div class="bntm-form-group">
                <label>Room Type Name *</label>
                <input type="text" name="room_type" id="room-type" required>
            </div>
            
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea name="description" id="room-description" rows="3"></textarea>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Price Per Night *</label>
                    <input type="number" name="price_per_night" id="room-price" step="0.01" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Max Occupancy *</label>
                    <input type="number" name="max_occupancy" id="room-occupancy" required>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Amenities (comma-separated)</label>
                <input type="text" name="amenities" id="room-amenities" placeholder="WiFi, TV, Air Conditioning, Mini Bar">
            </div>
            
            <div class="bntm-form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="room-active" checked>
                    <span style="margin-left: 8px;">Active (Available for booking)</span>
                </label>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Room Type</button>
            <button type="button" class="bntm-btn-secondary" id="cancel-edit-room">Cancel</button>
        </form>
    </div>

    <script>
    (function() {
        const form = document.getElementById('room-form');
        const formTitle = document.getElementById('room-form-title');
        const cancelBtn = document.getElementById('cancel-edit-room');
        
        // Edit room
        document.querySelectorAll('.edit-room-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const room = JSON.parse(this.dataset.room);
                formTitle.textContent = 'Edit Room Type';
                document.getElementById('room-id').value = room.id;
                document.getElementById('room-type').value = room.room_type;
                document.getElementById('room-description').value = room.description || '';
                document.getElementById('room-price').value = room.price_per_night;
                document.getElementById('room-occupancy').value = room.max_occupancy;
                document.getElementById('room-amenities').value = room.amenities || '';
                document.getElementById('room-active').checked = room.is_active == 1;
                form.scrollIntoView({behavior: 'smooth'});
            });
        });
        
        // Cancel edit
        cancelBtn.addEventListener('click', function() {
            form.reset();
            document.getElementById('room-id').value = '';
            formTitle.textContent = 'Add Room Type';
        });
        
        // Save room
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bm_save_room_type');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
                else {
                    btn.disabled = false;
                    btn.textContent = 'Save Room Type';
                }
            });
        });
        
        // Delete room
        document.querySelectorAll('.delete-room-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this room type? This cannot be undone.')) return;
                
                const formData = new FormData();
                formData.append('action', 'bm_delete_room_type');
                formData.append('room_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Yacht Fleet Tab
 * FIXED: Added $ before wpdb->prepare
 */
function bm_yachts_tab($business_id) {
    global $wpdb;
    $yachts_table = $wpdb->prefix . 'bm_yacht_types';
    
    // FIXED: Was missing $ before wpdb
    $yachts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$yachts_table} WHERE business_id = %d ORDER BY created_at DESC",
        $business_id
    ));

    $nonce = wp_create_nonce('bm_yacht_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Yacht Fleet</h3>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Yacht Name</th>
                    <th>Price/Hour</th>
                    <th>Price/Day</th>
                    <th>Max Guests</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($yachts)): ?>
                <tr><td colspan="6" style="text-align:center;">No yachts configured</td></tr>
                <?php else: foreach ($yachts as $yacht): ?>
                <tr>
                    <td><strong><?php echo esc_html($yacht->yacht_name); ?></strong></td>
                    <td>₱<?php echo number_format($yacht->price_per_hour, 2); ?></td>
                    <td>₱<?php echo number_format($yacht->price_per_day, 2); ?></td>
                    <td><?php echo esc_html($yacht->max_guests); ?> guests</td>
                    <td>
                        <span class="bm-badge bm-badge-<?php echo $yacht->is_active ? 'paid' : 'pending'; ?>">
                            <?php echo $yacht->is_active ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small edit-yacht-btn" data-yacht='<?php echo json_encode($yacht); ?>'>
                            Edit
                        </button>
                        <button class="bntm-btn-small bntm-btn-danger delete-yacht-btn" 
                                data-id="<?php echo $yacht->id; ?>" 
                                data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="bntm-form-section">
        <h3 id="yacht-form-title">Add Yacht</h3>
        <form id="yacht-form" class="bntm-form">
            <input type="hidden" name="yacht_id" id="yacht-id">
            
            <div class="bntm-form-group">
                <label>Yacht Name *</label>
                <input type="text" name="yacht_name" id="yacht-name" required>
            </div>
            
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea name="description" id="yacht-description" rows="3"></textarea>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Price Per Hour *</label>
                    <input type="number" name="price_per_hour" id="yacht-price-hour" step="0.01" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Price Per Day *</label>
                    <input type="number" name="price_per_day" id="yacht-price-day" step="0.01" required>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Maximum Guests *</label>
                <input type="number" name="max_guests" id="yacht-guests" required>
            </div>
            
            <div class="bntm-form-group">
                <label>Features (comma-separated)</label>
                <input type="text" name="features" id="yacht-features" placeholder="Sound System, Diving Equipment, BBQ Grill">
            </div>
            
            <div class="bntm-form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="yacht-active" checked>
                    <span style="margin-left: 8px;">Active (Available for rental)</span>
                </label>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Yacht</button>
            <button type="button" class="bntm-btn-secondary" id="cancel-edit-yacht">Cancel</button>
        </form>
    </div>
    
    <script>
    (function() {
        const form = document.getElementById('yacht-form');
        const formTitle = document.getElementById('yacht-form-title');
        const cancelBtn = document.getElementById('cancel-edit-yacht');
        
        // Edit yacht
        document.querySelectorAll('.edit-yacht-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const yacht = JSON.parse(this.dataset.yacht);
                formTitle.textContent = 'Edit Yacht';
                document.getElementById('yacht-id').value = yacht.id;
                document.getElementById('yacht-name').value = yacht.yacht_name;
                document.getElementById('yacht-description').value = yacht.description || '';
                document.getElementById('yacht-price-hour').value = yacht.price_per_hour;
                document.getElementById('yacht-price-day').value = yacht.price_per_day;
                document.getElementById('yacht-guests').value = yacht.max_guests;
                document.getElementById('yacht-features').value = yacht.features || '';
                document.getElementById('yacht-active').checked = yacht.is_active == 1;
                form.scrollIntoView({behavior: 'smooth'});
            });
        });
        
        // Cancel edit
        cancelBtn.addEventListener('click', function() {
            form.reset();
            document.getElementById('yacht-id').value = '';
            formTitle.textContent = 'Add Yacht';
        });
        
        // Save yacht
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bm_save_yacht_type');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
                else {
                    btn.disabled = false;
                    btn.textContent = 'Save Yacht';
                }
            });
        });
        
        // Delete yacht
        document.querySelectorAll('.delete-yacht-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this yacht? This cannot be undone.')) return;
                
                const formData = new FormData();
                formData.append('action', 'bm_delete_yacht_type');
                formData.append('yacht_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Car Fleet Tab
 * FIXED: Added $ before wpdb->prepare
 */
function bm_cars_tab($business_id) {
    global $wpdb;
    $cars_table = $wpdb->prefix . 'bm_car_types';
    
    // FIXED: Was missing $ before wpdb
    $cars = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$cars_table} WHERE business_id = %d ORDER BY created_at DESC",
        $business_id
    ));

    $nonce = wp_create_nonce('bm_car_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Car Fleet</h3>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Car Name</th>
                    <th>Price/Day</th>
                    <th>Driver Fee</th>
                    <th>Max Passengers</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cars)): ?>
                <tr><td colspan="6" style="text-align:center;">No cars configured</td></tr>
                <?php else: foreach ($cars as $car): ?>
                <tr>
                    <td><strong><?php echo esc_html($car->car_name); ?></strong></td>
                    <td>₱<?php echo number_format($car->price_per_day, 2); ?></td>
                    <td>₱<?php echo number_format($car->driver_fee, 2); ?></td>
                    <td><?php echo esc_html($car->max_passengers); ?> passengers</td>
                    <td>
                        <span class="bm-badge bm-badge-<?php echo $car->is_active ? 'paid' : 'pending'; ?>">
                            <?php echo $car->is_active ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small edit-car-btn" data-car='<?php echo json_encode($car); ?>'>
                            Edit
                        </button>
                        <button class="bntm-btn-small bntm-btn-danger delete-car-btn" 
                                data-id="<?php echo $car->id; ?>" 
                                data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="bntm-form-section">
        <h3 id="car-form-title">Add Car</h3>
        <form id="car-form" class="bntm-form">
            <input type="hidden" name="car_id" id="car-id">
            
            <div class="bntm-form-group">
                <label>Car Name *</label>
                <input type="text" name="car_name" id="car-name" required>
            </div>
            
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea name="description" id="car-description" rows="3"></textarea>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Price Per Day *</label>
                    <input type="number" name="price_per_day" id="car-price" step="0.01" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Driver Fee (per day)</label>
                    <input type="number" name="driver_fee" id="car-driver-fee" step="0.01" value="0">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Maximum Passengers *</label>
                <input type="number" name="max_passengers" id="car-passengers" required>
            </div>
            
            <div class="bntm-form-group">
                <label>Features (comma-separated)</label>
                <input type="text" name="features" id="car-features" placeholder="Air Conditioning, GPS, Child Seat">
            </div>
            
            <div class="bntm-form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="car-active" checked>
                    <span style="margin-left: 8px;">Active (Available for rental)</span>
                </label>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Car</button>
            <button type="button" class="bntm-btn-secondary" id="cancel-edit-car">Cancel</button>
        </form>
    </div>
    
    <script>
    (function() {
        const form = document.getElementById('car-form');
        const formTitle = document.getElementById('car-form-title');
        const cancelBtn = document.getElementById('cancel-edit-car');
        
        // Edit car
        document.querySelectorAll('.edit-car-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const car = JSON.parse(this.dataset.car);
                formTitle.textContent = 'Edit Car';
                document.getElementById('car-id').value = car.id;
                document.getElementById('car-name').value = car.car_name;
                document.getElementById('car-description').value = car.description || '';
                document.getElementById('car-price').value = car.price_per_day;
                document.getElementById('car-driver-fee').value = car.driver_fee;
                document.getElementById('car-passengers').value = car.max_passengers;
                document.getElementById('car-features').value = car.features || '';
                document.getElementById('car-active').checked = car.is_active == 1;
                form.scrollIntoView({behavior: 'smooth'});
            });
        });
        
        // Cancel edit
        cancelBtn.addEventListener('click', function() {
            form.reset();
            document.getElementById('car-id').value = '';
            formTitle.textContent = 'Add Car';
        });
        
        // Save car
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bm_save_car_type');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
                else {
                    btn.disabled = false;
                    btn.textContent = 'Save Car';
                }
            });
        });
        
        // Delete car
        document.querySelectorAll('.delete-car-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this car? This cannot be undone.')) return;
                
                const formData = new FormData();
                formData.append('action', 'bm_delete_car_type');
                formData.append('car_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Settings Tab
 */
function bm_settings_tab($business_id) {
    // Get current settings
    $hotel_name = bntm_get_setting('bm_hotel_name', '');
    $hotel_email = bntm_get_setting('bm_hotel_email', '');
    $hotel_phone = bntm_get_setting('bm_hotel_phone', '');
    $hotel_address = bntm_get_setting('bm_hotel_address', '');
    $deposit_percentage = bntm_get_setting('bm_deposit_percentage', '30');
    $paymaya_mode = bntm_get_setting('bm_paymaya_mode', 'sandbox');
    $paymaya_public_key = bntm_get_setting('bm_paymaya_public_key', '');
    $paymaya_secret_key = bntm_get_setting('bm_paymaya_secret_key', '');
    $enable_paymaya = bntm_get_setting('bm_enable_paymaya', '0');
    
    $nonce = wp_create_nonce('bm_settings_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Hotel Information</h3>
        <form id="hotel-info-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Hotel Name *</label>
                <input type="text" name="hotel_name" value="<?php echo esc_attr($hotel_name); ?>" required>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Email Address *</label>
                    <input type="email" name="hotel_email" value="<?php echo esc_attr($hotel_email); ?>" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Phone Number *</label>
                    <input type="text" name="hotel_phone" value="<?php echo esc_attr($hotel_phone); ?>" required>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Address</label>
                <textarea name="hotel_address" rows="3"><?php echo esc_textarea($hotel_address); ?></textarea>
            </div>
            
            <div class="bntm-form-group">
                <label>Deposit Percentage (%) *</label>
                <input type="number" name="deposit_percentage" value="<?php echo esc_attr($deposit_percentage); ?>" 
                       min="0" max="100" step="1" required>
                <small>Default deposit required for bookings</small>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Hotel Information</button>
        </form>
        <div id="hotel-info-message"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>PayMaya Payment Gateway</h3>
        <form id="paymaya-form" class="bntm-form">
            <div class="bntm-form-group">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="enable_paymaya" id="enable-paymaya" 
                           <?php checked($enable_paymaya, '1'); ?>>
                    <span style="margin-left: 8px;"><strong>Enable PayMaya Payments</strong></span>
                </label>
            </div>
            
            <div id="paymaya-config" style="<?php echo $enable_paymaya == '1' ? '' : 'display:none;'; ?>">
                <div class="bntm-form-group">
                    <label>Mode</label>
                    <select name="paymaya_mode">
                        <option value="sandbox" <?php selected($paymaya_mode, 'sandbox'); ?>>Sandbox (Testing)</option>
                        <option value="live" <?php selected($paymaya_mode, 'live'); ?>>Live (Production)</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Public API Key *</label>
                    <input type="text" name="paymaya_public_key" 
                           value="<?php echo esc_attr($paymaya_public_key); ?>" 
                           placeholder="pk-...">
                    <small>Get this from your PayMaya Developer Dashboard</small>
                </div>
                
                <div class="bntm-form-group">
                    <label>Secret API Key *</label>
                    <input type="password" name="paymaya_secret_key" 
                           value="<?php echo esc_attr($paymaya_secret_key); ?>" 
                           placeholder="sk-...">
                    <small>Keep this secure - never share publicly</small>
                </div>
                
                <div style="padding: 15px; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
                    <strong>PayMaya Sandbox Test Credentials:</strong><br>
                    <small>
                        Public Key: pk-Z0OSzLvIcOI2UIvDhdTGVVfRSSeiGStnceqwUE7n0Ah<br>
                        Secret Key: sk-X8qolYjy62kIzEbr0QRK1h4b4KDVHaNcbSs1nCHSKNP<br>
                        Test Card: 5123450000000008, CVV: 123, Exp: 12/25
                    </small>
                </div>
            </div>
            
            <button type="submit" class="bntm-btn-primary" style="margin-top: 15px;">
                Save PayMaya Settings
            </button>
        </form>
        <div id="paymaya-message"></div>
    </div>
    
    <!-- NEW: Separate Booking Links Section -->
    <div class="bntm-form-section">
        <h3>📌 Customer Booking Links</h3>
        <p style="color: #6b7280; margin-bottom: 20px;">Share these links with your customers based on what they want to book:</p>
        
        <!-- Hotel Booking Link -->
        <div style="margin-bottom: 25px;">
            <h4 style="color: #111827; margin-bottom: 8px;">🏨 Hotel Booking</h4>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">For customers who want to book hotel rooms</p>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php 
                    $hotel_page = get_page_by_title('Book Hotel');
                    $hotel_url = $hotel_page ? get_permalink($hotel_page) : add_query_arg('page', 'book-hotel', home_url());
                ?>
                <input type="text" id="hotel-booking-link" readonly 
                       value="<?php echo esc_url($hotel_url); ?>" 
                       style="flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;">
                <button class="bntm-btn-secondary" onclick="copyLink('hotel-booking-link')">Copy Link</button>
            </div>
        </div>
        
        <!-- Yacht Booking Link -->
        <div style="margin-bottom: 25px;">
            <h4 style="color: #111827; margin-bottom: 8px;">⛵ Yacht Rental</h4>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">For customers who want to rent a yacht</p>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php 
                    $yacht_page = get_page_by_title('Book Yacht');
                    $yacht_url = $yacht_page ? get_permalink($yacht_page) : add_query_arg('page', 'book-yacht', home_url());
                ?>
                <input type="text" id="yacht-booking-link" readonly 
                       value="<?php echo esc_url($yacht_url); ?>" 
                       style="flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;">
                <button class="bntm-btn-secondary" onclick="copyLink('yacht-booking-link')">Copy Link</button>
            </div>
        </div>
        
        <!-- Car Booking Link -->
        <div style="margin-bottom: 25px;">
            <h4 style="color: #111827; margin-bottom: 8px;">🚗 Car Rental</h4>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 10px;">For customers who want to rent a car</p>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php 
                    $car_page = get_page_by_title('Book Car');
                    $car_url = $car_page ? get_permalink($car_page) : add_query_arg('page', 'book-car', home_url());
                ?>
                <input type="text" id="car-booking-link" readonly 
                       value="<?php echo esc_url($car_url); ?>" 
                       style="flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #f9fafb;">
                <button class="bntm-btn-secondary" onclick="copyLink('car-booking-link')">Copy Link</button>
            </div>
        </div>
        
        <div style="padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px; margin-top: 20px;">
            <p style="margin: 0; color: #1e40af; font-size: 14px;">
                <strong>💡 Tip:</strong> You can create buttons or links on your website that point to these specific booking pages to make it easier for customers to find what they're looking for!
            </p>
        </div>
    </div>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    // Copy link function
    function copyLink(elementId) {
        const input = document.getElementById(elementId);
        input.select();
        input.setSelectionRange(0, 99999); // For mobile devices
        document.execCommand('copy');
        
        // Show feedback
        const btn = input.nextElementSibling;
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        btn.style.background = '#10b981';
        btn.style.color = 'white';
        
        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = '';
            btn.style.color = '';
        }, 2000);
    }
    
    // Toggle PayMaya config
    document.getElementById('enable-paymaya').addEventListener('change', function() {
        document.getElementById('paymaya-config').style.display = this.checked ? 'block' : 'none';
    });
    
    // Save hotel info
    document.getElementById('hotel-info-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'bm_save_hotel_info');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            document.getElementById('hotel-info-message').innerHTML = 
                '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                json.data.message + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save Hotel Information';
        });
    });
    
    // Save PayMaya settings
    document.getElementById('paymaya-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'bm_save_paymaya_settings');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            document.getElementById('paymaya-message').innerHTML = 
                '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                json.data.message + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save PayMaya Settings';
        });
    });
    </script>
    <?php
    return ob_get_clean();
} 

function bntm_shortcode_bm_book_hotel() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'bm_room_types';
    $yachts_table = $wpdb->prefix . 'bm_yacht_types';
    $cars_table = $wpdb->prefix . 'bm_car_types';
    
    // Get business_id
    $business_id = get_option('bntm_primary_business_id', 1);
    
    // Get all service types
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$rooms_table} WHERE business_id = %d AND is_active = 1 ORDER BY room_type ASC",
        $business_id
    ));
    
    $yachts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$yachts_table} WHERE business_id = %d AND is_active = 1 ORDER BY yacht_name ASC",
        $business_id
    ));
    
    $cars = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$cars_table} WHERE business_id = %d AND is_active = 1 ORDER BY car_name ASC",
        $business_id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel Booking System');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bm-customer-booking-container">
        <div class="bm-booking-header">
            <h1>🌟 Book Your Travel Experience</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
            <p>Select the service you'd like to book first</p>
        </div>
        
        <!-- Service Selection Screen -->
        <div id="service-selection-screen" class="bm-service-selection">
            <h2>What service do you want to book first?</h2>
            <p>Choose your primary service and we'll suggest complementary options</p>
            
            <div class="bm-service-cards">
                <div class="bm-service-card" data-service="hotel">
                    <div class="bm-service-icon">🏨</div>
                    <h3>Hotel Room</h3>
                    <p>Book a comfortable place to stay</p>
                    <button type="button" class="bm-btn-primary bm-service-btn" data-service="hotel">
                        Start with Hotel
                    </button>
                </div>
                
                <div class="bm-service-card" data-service="car">
                    <div class="bm-service-icon">🚗</div>
                    <h3>Car Rental</h3>
                    <p>Get around comfortably</p>
                    <button type="button" class="bm-btn-primary bm-service-btn" data-service="car">
                        Start with Car
                    </button>
                </div>
                
                <div class="bm-service-card" data-service="yacht">
                    <div class="bm-service-icon">⛵</div>
                    <h3>Yacht Cruise</h3>
                    <p>Explore the island by sea</p>
                    <button type="button" class="bm-btn-primary bm-service-btn" data-service="yacht">
                        Start with Yacht
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Main Booking Form -->
        <form id="hotel-booking-form" class="bm-booking-form" style="display: none;">
            <input type="hidden" name="booking_type" value="hotel">
            <input type="hidden" name="selected_services" id="selected-services" value="">
            
            <!-- Back Button -->
            <div style="margin-bottom: 20px; text-align: center;">
                <button type="button" id="back-to-services-btn" class="bm-btn-secondary">
                    ← Back to Service Selection
                </button>
            </div>
            
            <!-- Customer Information -->
            <div class="bm-form-section">
                <h2>1. Your Information</h2>
                
                <div class="bm-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                </div>
                
                <div class="bm-form-group">
                    <label>Address</label>
                    <textarea name="customer_address" rows="2"></textarea>
                </div>
            </div>
            
            <!-- HOTEL SECTION -->
            <div class="bm-form-section bm-service-section" id="hotel-section">
                <h2>2. Hotel Accommodation Details</h2>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Check-In Date *</label>
                        <input type="date" name="check_in_date" id="check-in-date">
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Check-Out Date *</label>
                        <input type="date" name="check_out_date" id="check-out-date">
                    </div>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Number of Adults *</label>
                        <input type="number" name="num_adults" min="1" value="1">
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Number of Children</label>
                        <input type="number" name="num_children" min="0" value="0">
                    </div>
                </div>
                
                <!-- Room Selection -->
                <div class="bm-form-group">
                    <h3>Select Rooms</h3>
                    <div id="rooms-container">
                        <div class="room-selection-item">
                            <div class="bm-form-row">
                                <div class="bm-form-group">
                                    <label>Room Type</label>
                                    <select name="rooms[0][room_type]" class="room-type-select" data-index="0">
                                        <option value="">Select Room Type</option>
                                        <?php foreach ($rooms as $room): ?>
                                            <option value="<?php echo $room->id; ?>" 
                                                    data-price="<?php echo $room->price_per_night; ?>"
                                                    data-name="<?php echo esc_attr($room->room_type); ?>">
                                                <?php echo esc_html($room->room_type); ?> - 
                                                ₱<?php echo number_format($room->price_per_night, 2); ?>/night
                                                (Max: <?php echo $room->max_occupancy; ?> guests)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="bm-form-group">
                                    <label>Number of Rooms</label>
                                    <input type="number" name="rooms[0][num_rooms]" class="room-quantity" 
                                           data-index="0" min="1" value="1">
                                </div>
                                
                                <div class="bm-form-group">
                                    <label>Subtotal</label>
                                    <input type="text" class="room-subtotal" data-index="0" readonly 
                                           style="background: #f3f4f6;">
                                </div>
                            </div>
                            <button type="button" class="bm-btn-remove remove-room-btn" style="display:none;">
                                Remove Room
                            </button>
                        </div>
                    </div>
                    
                    <button type="button" id="add-room-btn" class="bm-btn-secondary">
                        + Add Another Room
                    </button>
                </div>
            </div>
            
            <!-- CAR RENTAL SECTION -->
            <div class="bm-form-section bm-service-section" id="car-section">
                <h2>Car Rental Details</h2>
                
                <div class="bm-form-group">
                    <label>Choose Your Car</label>
                    <select name="car_type" id="car-type">
                        <option value="">Select Car</option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?php echo $car->id; ?>"
                                    data-price-day="<?php echo $car->price_per_day; ?>"
                                    data-driver-fee="<?php echo $car->driver_fee; ?>"
                                    data-name="<?php echo esc_attr($car->car_name); ?>">
                                <?php echo esc_html($car->car_name); ?> - 
                                ₱<?php echo number_format($car->price_per_day, 2); ?>/day
                                (Max: <?php echo $car->max_passengers; ?> passengers)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Pickup Date</label>
                        <input type="date" name="car_pickup_date" id="car-pickup-date">
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Return Date</label>
                        <input type="date" name="car_return_date" id="car-return-date">
                    </div>
                </div>
                
                <div class="bm-form-group">
                    <label>
                        <input type="checkbox" name="car_with_driver" id="car-with-driver">
                        Include Driver (Additional Fee)
                    </label>
                </div>
                
                <div class="bm-form-group">
                    <label>Price Preview</label>
                    <input type="text" id="car-subtotal" readonly style="background: #f3f4f6; font-weight: bold;">
                </div>
            </div>
            
            <!-- YACHT SECTION -->
            <div class="bm-form-section bm-service-section" id="yacht-section">
                <h2>Yacht Cruise Details</h2>
                
                <div class="bm-form-group">
                    <label>Choose Your Yacht</label>
                    <select name="yacht_type" id="yacht-type">
                        <option value="">Select Yacht</option>
                        <?php foreach ($yachts as $yacht): ?>
                            <option value="<?php echo $yacht->id; ?>"
                                    data-price-hour="<?php echo $yacht->price_per_hour; ?>"
                                    data-price-day="<?php echo $yacht->price_per_day; ?>"
                                    data-max-guests="<?php echo $yacht->max_guests; ?>"
                                    data-name="<?php echo esc_attr($yacht->yacht_name); ?>">
                                <?php echo esc_html($yacht->yacht_name); ?> - 
                                ₱<?php echo number_format($yacht->price_per_hour, 2); ?>/hr or 
                                ₱<?php echo number_format($yacht->price_per_day, 2); ?>/day
                                (Max: <?php echo $yacht->max_guests; ?> guests)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Rental Date</label>
                        <input type="date" name="yacht_rental_date" id="yacht-rental-date">
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Number of Guests</label>
                        <input type="number" name="yacht_num_guests" id="yacht-num-guests" min="1" value="1">
                    </div>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Duration</label>
                        <input type="number" name="yacht_duration" id="yacht-duration" min="1" value="4">
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Unit</label>
                        <select name="yacht_duration_unit" id="yacht-duration-unit">
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                        </select>
                    </div>
                </div>
                
                <div class="bm-form-group">
                    <label>Price Preview</label>
                    <input type="text" id="yacht-subtotal" readonly style="background: #f3f4f6; font-weight: bold;">
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="bm-form-section">
                <h2>Special Requests (Optional)</h2>
                
                <div class="bm-form-group">
                    <label>Additional Notes or Requests</label>
                    <textarea name="special_requests" rows="4" 
                              placeholder="Any special requirements, dietary restrictions, accessibility needs, etc."></textarea>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="bm-form-section bm-summary-section">
                <h2>Booking Summary</h2>
                <div id="summary-details">
                    <div class="bm-summary-row">
                        <span>Total Amount:</span>
                        <strong id="summary-total">₱0.00</strong>
                    </div>
                    <div class="bm-summary-row">
                        <span>Deposit Required:</span>
                        <strong id="summary-deposit">₱0.00</strong>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="bm-btn-primary bm-btn-large">
                    Submit Booking Request
                </button>
            </div>
            
            <div id="booking-message"></div>
        </form>
    </div>
    
    <!-- Upsell Modal -->
    <div id="upsell-modal" class="bm-modal" style="display: none;">
        <div class="bm-modal-content">
            <h3 id="upsell-question"></h3>
            <p id="upsell-description"></p>
            <div class="bm-modal-buttons">
                <button type="button" class="bm-btn-primary" id="upsell-yes">
                    Yes, Add It
                </button>
                <button type="button" class="bm-btn-secondary" id="upsell-no">
                    No, Continue
                </button>
            </div>
        </div>
    </div>
    
    <?php echo bm_get_booking_styles(); ?>
    <?php echo bm_get_sequential_booking_script(); ?>
    
    <?php
    return ob_get_clean();
}

/**
 * Sequential Booking Script
 */
function bm_get_sequential_booking_script() {
    ob_start();
    ?>
    <script>
    (function() {
        const form = document.getElementById('hotel-booking-form');
        const serviceSelectionScreen = document.getElementById('service-selection-screen');
        const upsellModal = document.getElementById('upsell-modal');
        const selectedServicesInput = document.getElementById('selected-services');
        
        let selectedServices = [];
        let currentServiceIndex = 0;
        let roomIndex = 1;
        
        // Service flow based on initial choice
        const serviceFlows = {
            hotel: ['hotel', 'car', 'yacht'],
            car: ['car', 'hotel', 'yacht'],
            yacht: ['yacht', 'car', 'hotel']
        };
        
        const serviceText = {
            car: {
                question: 'Would you like to rent a car for your trip?',
                description: 'Get comfortable transportation during your stay'
            },
            hotel: {
                question: 'Need a place to stay?',
                description: 'Book a comfortable hotel room for your stay'
            },
            yacht: {
                question: 'Would you like to cruise the island?',
                description: 'Experience the beautiful waters with our yacht cruise'
            }
        };
        
        // Service selection buttons
        document.querySelectorAll('.bm-service-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const service = this.dataset.service;
                selectInitialService(service);
            });
        });
        
        function selectInitialService(service) {
            selectedServices = [service];
            currentServiceIndex = 0;
            updateSelectedServices();
            showFormForCurrentServices();
            serviceSelectionScreen.style.display = 'none';
            form.style.display = 'block';
            
            // Ask for the next service
            askForNextService();
        }
        
        function askForNextService() {
            const flow = serviceFlows[selectedServices[0]];
            const nextIndex = currentServiceIndex + 1;
            
            if (nextIndex < flow.length) {
                const nextService = flow[nextIndex];
                currentServiceIndex = nextIndex;
                showUpsellModal(nextService);
            }
        }
        
        function showUpsellModal(service) {
            const text = serviceText[service];
            document.getElementById('upsell-question').textContent = text.question;
            document.getElementById('upsell-description').textContent = text.description;
            
            document.getElementById('upsell-yes').onclick = function() {
                selectedServices.push(service);
                updateSelectedServices();
                showFormForCurrentServices();
                upsellModal.style.display = 'none';
                askForNextService();
            };
            
            document.getElementById('upsell-no').onclick = function() {
                upsellModal.style.display = 'none';
                askForNextService();
            };
            
            upsellModal.style.display = 'flex';
        }
        
        function updateSelectedServices() {
            selectedServicesInput.value = selectedServices.join(',');
        }
        
        function showFormForCurrentServices() {
            // Hide all service sections
            document.getElementById('hotel-section').style.display = 'none';
            document.getElementById('car-section').style.display = 'none';
            document.getElementById('yacht-section').style.display = 'none';
            
            // Show selected service sections
            selectedServices.forEach(service => {
                const sectionId = service + '-section';
                const section = document.getElementById(sectionId);
                if (section) {
                    section.style.display = 'block';
                    
                    // Add required attributes only if service is selected
                    if (service === 'hotel') {
                        makeHotelRequired(true);
                    } else if (service === 'car') {
                        makeCarRequired(true);
                    } else if (service === 'yacht') {
                        makeYachtRequired(true);
                    }
                }
            });
            
            // Remove required from unselected services
            if (!selectedServices.includes('hotel')) {
                makeHotelRequired(false);
            }
            if (!selectedServices.includes('car')) {
                makeCarRequired(false);
            }
            if (!selectedServices.includes('yacht')) {
                makeYachtRequired(false);
            }
        }
        
        function makeHotelRequired(required) {
            const checkInInput = document.getElementById('check-in-date');
            const checkOutInput = document.getElementById('check-out-date');
            
            if (required) {
                checkInInput.required = true;
                checkOutInput.required = true;
            } else {
                checkInInput.required = false;
                checkOutInput.required = false;
                checkInInput.value = '';
                checkOutInput.value = '';
            }
        }
        
        function makeCarRequired(required) {
            const carType = document.getElementById('car-type');
            const carPickupDate = document.getElementById('car-pickup-date');
            const carReturnDate = document.getElementById('car-return-date');
            
            if (required) {
                carType.required = true;
                carPickupDate.required = true;
                carReturnDate.required = true;
            } else {
                carType.required = false;
                carPickupDate.required = false;
                carReturnDate.required = false;
                carType.value = '';
                carPickupDate.value = '';
                carReturnDate.value = '';
            }
        }
        
        function makeYachtRequired(required) {
            const yachtType = document.getElementById('yacht-type');
            const yachtDate = document.getElementById('yacht-rental-date');
            
            if (required) {
                yachtType.required = true;
                yachtDate.required = true;
            } else {
                yachtType.required = false;
                yachtDate.required = false;
                yachtType.value = '';
                yachtDate.value = '';
            }
        }
        
        // Back to service selection
        document.getElementById('back-to-services-btn').addEventListener('click', function(e) {
            e.preventDefault();
            selectedServices = [];
            currentServiceIndex = 0;
            updateSelectedServices();
            form.style.display = 'none';
            serviceSelectionScreen.style.display = 'block';
            upsellModal.style.display = 'none';
        });
        
        // Set minimum dates to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('check-in-date').min = today;
        document.getElementById('check-out-date').min = today;
        document.getElementById('car-pickup-date').min = today;
        document.getElementById('car-return-date').min = today;
        document.getElementById('yacht-rental-date').min = today;
        
        // Hotel room calculations
        function calculateNights() {
            const checkIn = document.getElementById('check-in-date').value;
            const checkOut = document.getElementById('check-out-date').value;
            
            if (checkIn && checkOut) {
                const date1 = new Date(checkIn);
                const date2 = new Date(checkOut);
                const diffTime = date2 - date1;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return diffDays > 0 ? diffDays : 0;
            }
            return 0;
        }
        
        function calculateRoomSubtotals() {
            const nights = calculateNights();
            let total = 0;
            
            document.querySelectorAll('.room-type-select').forEach(select => {
                if (select.value) {
                    const index = select.dataset.index;
                    const option = select.options[select.selectedIndex];
                    const price = parseFloat(option.dataset.price);
                    const quantity = parseInt(document.querySelector(`.room-quantity[data-index="${index}"]`).value) || 1;
                    const subtotal = price * quantity * nights;
                    
                    document.querySelector(`.room-subtotal[data-index="${index}"]`).value = '₱' + subtotal.toFixed(2);
                    total += subtotal;
                }
            });
            
            return total;
        }
        
        function calculateCarSubtotal() {
            const carSelect = document.getElementById('car-type');
            const pickupDate = document.getElementById('car-pickup-date').value;
            const returnDate = document.getElementById('car-return-date').value;
            
            if (carSelect.value && pickupDate && returnDate) {
                const pickup = new Date(pickupDate);
                const returnD = new Date(returnDate);
                const days = Math.ceil((returnD - pickup) / (1000 * 60 * 60 * 24));
                
                if (days > 0) {
                    const option = carSelect.options[carSelect.selectedIndex];
                    const pricePerDay = parseFloat(option.dataset.priceDay);
                    let total = pricePerDay * days;
                    
                    if (document.getElementById('car-with-driver').checked) {
                        const driverFee = parseFloat(option.dataset.driverFee);
                        total += driverFee * days;
                    }
                    
                    document.getElementById('car-subtotal').value = '₱' + total.toFixed(2);
                    return total;
                }
            }
            
            document.getElementById('car-subtotal').value = '';
            return 0;
        }
        
        function calculateYachtSubtotal() {
            const yachtSelect = document.getElementById('yacht-type');
            const duration = parseInt(document.getElementById('yacht-duration').value) || 0;
            const unit = document.getElementById('yacht-duration-unit').value;
            
            if (yachtSelect.value && duration > 0) {
                const option = yachtSelect.options[yachtSelect.selectedIndex];
                const price = unit === 'hours' ? parseFloat(option.dataset.priceHour) : parseFloat(option.dataset.priceDay);
                const total = price * duration;
                
                document.getElementById('yacht-subtotal').value = '₱' + total.toFixed(2);
                return total;
            }
            
            document.getElementById('yacht-subtotal').value = '';
            return 0;
        }
        
        function calculateTotalAndDeposit() {
            let total = 0;
            
            if (selectedServices.includes('hotel')) {
                total += calculateRoomSubtotals();
            }
            if (selectedServices.includes('car')) {
                total += calculateCarSubtotal();
            }
            if (selectedServices.includes('yacht')) {
                total += calculateYachtSubtotal();
            }
            
            const depositPercentage = 30;
            const deposit = (total * depositPercentage) / 100;
            
            document.getElementById('summary-total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('summary-deposit').textContent = '₱' + deposit.toLocaleString('en-US', {minimumFractionDigits: 2});
        }
        
        // Add room button
        document.getElementById('add-room-btn').addEventListener('click', function(e) {
            e.preventDefault();
            const container = document.getElementById('rooms-container');
            const newRoom = document.querySelector('.room-selection-item').cloneNode(true);
            
            newRoom.querySelector('.room-type-select').dataset.index = roomIndex;
            newRoom.querySelector('.room-type-select').name = `rooms[${roomIndex}][room_type]`;
            newRoom.querySelector('.room-type-select').value = '';
            
            newRoom.querySelector('.room-quantity').dataset.index = roomIndex;
            newRoom.querySelector('.room-quantity').name = `rooms[${roomIndex}][num_rooms]`;
            newRoom.querySelector('.room-quantity').value = 1;
            
            newRoom.querySelector('.room-subtotal').dataset.index = roomIndex;
            newRoom.querySelector('.room-subtotal').value = '';
            
            newRoom.querySelector('.remove-room-btn').style.display = 'block';
            
            container.appendChild(newRoom);
            roomIndex++;
            
            attachRoomListeners(newRoom);
        });
        
        function attachRoomListeners(roomElement) {
            roomElement.querySelector('.room-type-select').addEventListener('change', calculateTotalAndDeposit);
            roomElement.querySelector('.room-quantity').addEventListener('input', calculateTotalAndDeposit);
            
            const removeBtn = roomElement.querySelector('.remove-room-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    roomElement.remove();
                    calculateTotalAndDeposit();
                });
            }
        }
        
        // Attach listeners
        attachRoomListeners(document.querySelector('.room-selection-item'));
        
        document.getElementById('check-in-date').addEventListener('change', function() {
            document.getElementById('check-out-date').min = this.value;
            calculateTotalAndDeposit();
        });
        document.getElementById('check-out-date').addEventListener('change', calculateTotalAndDeposit);
        
        document.getElementById('car-type').addEventListener('change', calculateTotalAndDeposit);
        document.getElementById('car-pickup-date').addEventListener('change', calculateTotalAndDeposit);
        document.getElementById('car-return-date').addEventListener('change', calculateTotalAndDeposit);
        document.getElementById('car-with-driver').addEventListener('change', calculateTotalAndDeposit);
        
        document.getElementById('yacht-type').addEventListener('change', calculateTotalAndDeposit);
        document.getElementById('yacht-duration').addEventListener('input', calculateTotalAndDeposit);
        document.getElementById('yacht-duration-unit').addEventListener('change', calculateTotalAndDeposit);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate at least one room is selected if hotel is included
            if (selectedServices.includes('hotel')) {
                const roomSelects = document.querySelectorAll('.room-type-select');
                let hasRoom = false;
                for (let i = 0; i < roomSelects.length; i++) {
                    if (roomSelects[i].value) {
                        hasRoom = true;
                        break;
                    }
                }
                if (!hasRoom) {
                    alert('Please select at least one room type');
                    return;
                }
            }
            
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_booking');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-success">' + 
                        json.data.message + 
                        '<br><br><strong>Redirecting to your quotation...</strong></div>';
                    
                    setTimeout(() => {
                        window.location.href = json.data.redirect_url;
                    }, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Booking Request';
                }
            })
            .catch(err => {
                console.error('Booking error:', err);
                document.getElementById('booking-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error">An error occurred. Please try again.</div>';
                btn.disabled = false;
                btn.textContent = 'Submit Booking Request';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
    
 

// ============================================================================
// BOOK YACHT SHORTCODE (Yacht Rental Only)
// ============================================================================

function bntm_shortcode_bm_book_yacht() {
    global $wpdb;
    $yachts_table = $wpdb->prefix . 'bm_yacht_types';
    
    $business_id = get_option('bntm_primary_business_id', 1);
    
    // Get active yachts
    $yachts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$yachts_table} WHERE business_id = %d AND is_active = 1 ORDER BY yacht_name ASC",
        $business_id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Yacht Rental Service');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bm-customer-booking-container">
        <div class="bm-booking-header">
            <h1>⛵ Book Yacht Rental</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
            <p>Select your yacht and complete the booking request</p>
        </div>
        
        <form id="yacht-booking-form" class="bm-booking-form">
            <input type="hidden" name="booking_type" value="yacht">
            
            <!-- Customer Information -->
            <div class="bm-form-section">
                <h2>1. Your Information</h2>
                
                <div class="bm-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                </div>
            </div>
            
            <!-- Yacht Selection -->
            <div class="bm-form-section">
                <h2>2. Select Yacht</h2>
                
                <div class="bm-form-group">
                    <label>Choose Your Yacht *</label>
                    <select name="yacht_type" id="yacht-type" required>
                        <option value="">Select Yacht</option>
                        <?php foreach ($yachts as $yacht): ?>
                            <option value="<?php echo $yacht->id; ?>"
                                    data-price-hour="<?php echo $yacht->price_per_hour; ?>"
                                    data-price-day="<?php echo $yacht->price_per_day; ?>"
                                    data-max-guests="<?php echo $yacht->max_guests; ?>"
                                    data-name="<?php echo esc_attr($yacht->yacht_name); ?>"
                                    data-description="<?php echo esc_attr($yacht->description); ?>">
                                <?php echo esc_html($yacht->yacht_name); ?> - 
                                ₱<?php echo number_format($yacht->price_per_hour, 2); ?>/hr or 
                                ₱<?php echo number_format($yacht->price_per_day, 2); ?>/day
                                (Max: <?php echo $yacht->max_guests; ?> guests)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="yacht-info" style="display: none; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px; margin-top: 15px;">
                    <h4 style="margin: 0 0 8px 0;">Yacht Details</h4>
                    <p id="yacht-description" style="margin: 0; color: #1e40af;"></p>
                </div>
            </div>
            
            <!-- Rental Details -->
            <div class="bm-form-section">
                <h2>3. Rental Details</h2>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Rental Date *</label>
                        <input type="date" name="rental_date" id="yacht-date" required>
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Number of Guests *</label>
                        <input type="number" name="num_guests" id="yacht-guests" min="1" value="1" required>
                        <small id="max-guests-note"></small>
                    </div>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Duration *</label>
                        <input type="number" name="duration" id="yacht-duration" min="1" value="4" required>
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Unit *</label>
                        <select name="duration_unit" id="yacht-unit" required>
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                        </select>
                    </div>
                </div>
                
                <div class="bm-form-group">
                    <label>Price Preview</label>
                    <input type="text" id="yacht-subtotal" readonly style="background: #f3f4f6; font-weight: bold; font-size: 18px;">
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="bm-form-section">
                <h2>4. Special Requests (Optional)</h2>
                
                <div class="bm-form-group">
                    <label>Additional Services or Requirements</label>
                    <textarea name="special_requests" rows="4" 
                              placeholder="Any special services, catering requests, celebration needs, etc."></textarea>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="bm-form-section bm-summary-section">
                <h2>Booking Summary</h2>
                
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong id="summary-total">₱0.00</strong>
                </div>
                <div class="bm-summary-row">
                    <span>Deposit Required:</span>
                    <strong id="summary-deposit">₱0.00</strong>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="bm-btn-primary bm-btn-large">
                    Submit Yacht Rental Request
                </button>
            </div>
            
            <div id="booking-message"></div>
        </form>
    </div>
    
    <?php echo bm_get_booking_styles(); ?>
    <?php echo bm_get_yacht_booking_script(); ?>
    
    <?php
    return ob_get_clean();
}

// ============================================================================
// BOOK CAR SHORTCODE (Car Rental Only)
// ============================================================================

function bntm_shortcode_bm_book_car() {
    global $wpdb;
    $cars_table = $wpdb->prefix . 'bm_car_types';
    
    $business_id = get_option('bntm_primary_business_id', 1);
    
    // Get active cars
    $cars = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$cars_table} WHERE business_id = %d AND is_active = 1 ORDER BY car_name ASC",
        $business_id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Car Rental Service');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bm-customer-booking-container">
        <div class="bm-booking-header">
            <h1>🚗 Book Car Rental</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
            <p>Select your vehicle and complete the booking request</p>
        </div>
        
        <form id="car-booking-form" class="bm-booking-form">
            <input type="hidden" name="booking_type" value="car">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('bm_action'); ?>">
            
            <!-- Customer Information -->
            <div class="bm-form-section">
                <h2>1. Your Information</h2>
                
                <div class="bm-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required>
                </div>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                </div>
            </div>
            
            <!-- Car Selection -->
            <div class="bm-form-section">
                <h2>2. Select Vehicle</h2>
                
                <div class="bm-form-group">
                    <label>Choose Your Car *</label>
                    <select name="car_type" id="car-type" required>
                        <option value="">Select Car</option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?php echo $car->id; ?>"
                                    data-price="<?php echo $car->price_per_day; ?>"
                                    data-driver-fee="<?php echo $car->driver_fee; ?>"
                                    data-max-passengers="<?php echo $car->max_passengers; ?>"
                                    data-name="<?php echo esc_attr($car->car_name); ?>"
                                    data-description="<?php echo esc_attr($car->description); ?>">
                                <?php echo esc_html($car->car_name); ?> - 
                                ₱<?php echo number_format($car->price_per_day, 2); ?>/day
                                (<?php echo $car->max_passengers; ?> passengers)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="car-info" style="display: none; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px; margin-top: 15px;">
                    <h4 style="margin: 0 0 8px 0;">Vehicle Details</h4>
                    <p id="car-description" style="margin: 0; color: #1e40af;"></p>
                </div>
            </div>
            
            <!-- Rental Details -->
            <div class="bm-form-section">
                <h2>3. Rental Period</h2>
                
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Pickup Date *</label>
                        <input type="date" name="pickup_date" id="car-pickup" required>
                    </div>
                    
                    <div class="bm-form-group">
                        <label>Return Date *</label>
                        <input type="date" name="return_date" id="car-return" required>
                    </div>
                </div>
                
                <div class="bm-form-group">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="with_driver" id="car-with-driver">
                        <span style="margin-left: 8px;">Include Driver 
                            <span id="driver-fee-display" style="color: #6b7280;"></span>
                        </span>
                    </label>
                </div>
                
                <div class="bm-form-group">
                    <label>Price Preview</label>
                    <input type="text" id="car-subtotal" readonly style="background: #f3f4f6; font-weight: bold; font-size: 18px;">
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="bm-form-section">
                <h2>4. Special Requests (Optional)</h2>
                
                <div class="bm-form-group">
                    <label>Additional Requirements</label>
                    <textarea name="special_requests" rows="4" 
                              placeholder="Any special requirements, child seats, GPS, etc."></textarea>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="bm-form-section bm-summary-section">
                <h2>Booking Summary</h2>
                
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong id="summary-total">₱0.00</strong>
                </div>
                <div class="bm-summary-row">
                    <span>Deposit Required:</span>
                    <strong id="summary-deposit">₱0.00</strong>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="bm-btn-primary bm-btn-large">
                    Submit Car Rental Request
                </button>
            </div>
            
            <div id="booking-message"></div>
        </form>
    </div>
    
    <?php echo bm_get_booking_styles(); ?>
    <?php echo bm_get_car_booking_script(); ?>
    
    <?php
    return ob_get_clean();
}
function bm_get_booking_styles() {
    ob_start();
    ?>
    <style>
    /* ===== CONTAINER & LAYOUT ===== */
    .bm-customer-booking-container {
        max-width: 1000px;
        margin: 50px auto;
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* ===== HEADER ===== */
    .bm-booking-header {
        text-align: center;
        margin-bottom: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 50px 30px;
        border-radius: 20px;
        color: white;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .bm-booking-header h1 {
        margin: 0 0 15px 0;
        font-size: 42px;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    .bm-booking-header p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        margin: 5px 0;
        line-height: 1.6;
    }

    /* ===== FORM ===== */
    .bm-booking-form {
        background: #ffffff;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        border: 1px solid #f0f0f5;
    }

    /* ===== FORM SECTIONS ===== */
    .bm-form-section {
        margin-bottom: 45px;
        padding-bottom: 40px;
        border-bottom: 2px solid #f0f0f5;
    }

    .bm-form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .bm-form-section h2 {
        margin: 0 0 25px 0;
        color: #1a1a2e;
        font-size: 22px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* ===== FORM GROUPS ===== */
    .bm-form-group {
        margin-bottom: 25px;
    }

    .bm-form-group label {
        display: block;
        margin-bottom: 10px;
        color: #2d3748;
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bm-form-group input,
    .bm-form-group select,
    .bm-form-group textarea {
        width: 90%;
        padding: 14px 16px;
        border: 2px solid #e8eef5;
        border-radius: 12px;
        font-size: 15px;
        font-family: inherit;
        background: #f8f9fc;
        transition: all 0.3s ease;
    }

    .bm-form-group input::placeholder,
    .bm-form-group textarea::placeholder {
        color: #a0aec0;
    }

    .bm-form-group input:hover,
    .bm-form-group select:hover,
    .bm-form-group textarea:hover {
        border-color: #d4dce5;
        background: #ffffff;
    }

    .bm-form-group input:focus,
    .bm-form-group select:focus,
    .bm-form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .bm-form-group small {
        display: block;
        margin-top: 8px;
        color: #718096;
        font-size: 13px;
        font-style: italic;
    }

    /* ===== FORM ROWS ===== */
    .bm-form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 0;
    }

    /* ===== ROOM SELECTION ===== */
    .room-selection-item {
        background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 20px;
        border: 2px solid #e8eef5;
        transition: all 0.3s ease;
    }

    .room-selection-item:hover {
        border-color: #667eea;
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
    }

    /* ===== BUTTONS ===== */
    .bm-btn-primary,
    .bm-btn-secondary,
    .bm-btn-remove {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bm-btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .bm-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
    }

    .bm-btn-primary:active {
        transform: translateY(0);
    }

    .bm-btn-large {
        padding: 16px 50px;
        font-size: 16px;
        width: 100%;
    }

    .bm-btn-secondary {
        background: #e8eef5;
        color: #4a5568;
        border: 2px solid #e8eef5;
    }

    .bm-btn-secondary:hover {
        background: #d4dce5;
        border-color: #667eea;
        color: #667eea;
    }

    .bm-btn-remove {
        background: #fed7d7;
        color: #c53030;
        padding: 10px 18px;
        font-size: 13px;
    }

    .bm-btn-remove:hover {
        background: #fc8181;
        color: white;
    }

    /* ===== SUMMARY SECTION ===== */
    .bm-summary-section {
        background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        padding: 30px;
        border-radius: 15px;
        border: 2px solid #e8eef5;
        margin-top: 40px;
    }

    .bm-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid #e8eef5;
        font-size: 15px;
    }

    .bm-summary-row:last-child {
        border-bottom: none;
        font-size: 20px;
        font-weight: 700;
        color: #667eea;
        padding-top: 20px;
        padding-bottom: 0;
    }

    /* ===== NOTICES ===== */
    .bntm-notice {
        padding: 18px 20px;
        border-radius: 12px;
        margin-top: 25px;
        border-left: 5px solid;
        font-weight: 500;
    }

    .bntm-notice-success {
        background: #f0fdf4;
        color: #166534;
        border-left-color: #22c55e;
    }

    .bntm-notice-error {
        background: #fef2f2;
        color: #b91c1c;
        border-left-color: #ef4444;
    }

    /* ===== SERVICE SELECTION ===== */
    .bm-service-selection {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
    }

    .bm-service-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }

    .bm-service-card {
        background: linear-gradient(135deg, #f5f7fa 0%, #f8f9fc 100%);
        padding: 30px;
        border-radius: 20px;
        border: 2px solid #e8eef5;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .bm-service-card:hover {
        transform: translateY(-5px);
        border-color: #667eea;
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.15);
    }

    .bm-service-card-icon {
        font-size: 60px;
        margin-bottom: 15px;
        display: block;
    }

    .bm-service-card h3 {
        margin: 15px 0 10px 0;
        color: #1a1a2e;
        font-size: 22px;
        font-weight: 700;
    }

    .bm-service-card p {
        color: #718096;
        font-size: 14px;
        margin-bottom: 20px;
        line-height: 1.6;
    }

    .bm-service-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 28px;
        border: none;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bm-service-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    /* ===== MODAL ===== */
    .bm-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .bm-modal-content {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90%;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .bm-modal-content h3 {
        margin: 0 0 15px 0;
        color: #1a1a2e;
        font-size: 24px;
        font-weight: 700;
    }

    .bm-modal-content p {
        color: #718096;
        font-size: 16px;
        margin: 0 0 30px 0;
        line-height: 1.6;
    }

    .bm-modal-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .bm-modal-buttons .bm-btn-primary,
    .bm-modal-buttons .bm-btn-secondary {
        width: 100%;
        margin: 0;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .bm-booking-header {
            padding: 40px 20px;
        }

        .bm-booking-header h1 {
            font-size: 28px;
        }

        .bm-booking-form {
            padding: 25px;
        }

        .bm-form-row {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .bm-btn-large {
            padding: 14px 30px;
            font-size: 15px;
        }

        .bm-service-cards {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .bm-service-card {
            padding: 20px;
        }

        .bm-service-card-icon {
            font-size: 48px;
        }

        .bm-modal-content {
            width: 95%;
            padding: 25px;
        }

        .bm-modal-buttons {
            grid-template-columns: 1fr;
        }
    }
    </style>
    <?php
    return ob_get_clean();
}

// ============================================================================
// HOTEL BOOKING SCRIPT
// ============================================================================

function bm_get_hotel_booking_script() {
    ob_start();
    ?>
    <script>
    (function() {
        let roomIndex = 1;
        const form = document.getElementById('hotel-booking-form');
        
        // Set minimum dates to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('check-in-date').min = today;
        document.getElementById('check-out-date').min = today;
        
        // Calculate number of nights
        function calculateNights() {
            const checkIn = document.querySelector('input[name="check_in_date"]').value;
            const checkOut = document.querySelector('input[name="check_out_date"]').value;
            
            if (checkIn && checkOut) {
                const date1 = new Date(checkIn);
                const date2 = new Date(checkOut);
                const diffTime = date2 - date1;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return diffDays > 0 ? diffDays : 0;
            }
            return 0;
        }
        
        // Calculate room subtotals
        function calculateRoomSubtotals() {
            const nights = calculateNights();
            let total = 0;
            
            document.querySelectorAll('.room-type-select').forEach(select => {
                if (select.value) {
                    const index = select.dataset.index;
                    const option = select.options[select.selectedIndex];
                    const price = parseFloat(option.dataset.price);
                    const quantity = parseInt(document.querySelector(`.room-quantity[data-index="${index}"]`).value) || 1;
                    const subtotal = price * quantity * nights;
                    
                    document.querySelector(`.room-subtotal[data-index="${index}"]`).value = '₱' + subtotal.toFixed(2);
                    total += subtotal;
                }
            });
            
            const depositPercentage = 30; // Get from settings if needed
            const deposit = (total * depositPercentage) / 100;
            
            document.getElementById('summary-total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('summary-deposit').textContent = '₱' + deposit.toLocaleString('en-US', {minimumFractionDigits: 2});
            
            return total;
        }
        
        // Add room button
        document.getElementById('add-room-btn').addEventListener('click', function() {
            const container = document.getElementById('rooms-container');
            const newRoom = document.querySelector('.room-selection-item').cloneNode(true);
            
            // Update indices
            newRoom.querySelector('.room-type-select').dataset.index = roomIndex;
            newRoom.querySelector('.room-type-select').name = `rooms[${roomIndex}][room_type]`;
            newRoom.querySelector('.room-type-select').value = '';
            
            newRoom.querySelector('.room-quantity').dataset.index = roomIndex;
            newRoom.querySelector('.room-quantity').name = `rooms[${roomIndex}][num_rooms]`;
            newRoom.querySelector('.room-quantity').value = 1;
            
            newRoom.querySelector('.room-subtotal').dataset.index = roomIndex;
            newRoom.querySelector('.room-subtotal').value = '';
            
            // Show remove button
            newRoom.querySelector('.remove-room-btn').style.display = 'block';
            
            container.appendChild(newRoom);
            roomIndex++;
            
            // Add event listeners to new room
            attachRoomListeners(newRoom);
        });
        
        // Attach listeners to room elements
        function attachRoomListeners(roomElement) {
            roomElement.querySelector('.room-type-select').addEventListener('change', calculateRoomSubtotals);
            roomElement.querySelector('.room-quantity').addEventListener('input', calculateRoomSubtotals);
            
            const removeBtn = roomElement.querySelector('.remove-room-btn');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    roomElement.remove();
                    calculateRoomSubtotals();
                });
            }
        }
        
        // Initial listeners
        attachRoomListeners(document.querySelector('.room-selection-item'));
        
        // Date change listeners
        document.querySelector('input[name="check_in_date"]').addEventListener('change', function() {
            document.getElementById('check-out-date').min = this.value;
            calculateRoomSubtotals();
        });
        document.querySelector('input[name="check_out_date"]').addEventListener('change', calculateRoomSubtotals);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate at least one room is selected
            if (!document.querySelector('.room-type-select').value) {
                alert('Please select at least one room type');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_booking');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-success">' + 
                        json.data.message + 
                        '<br><br><strong>Redirecting to your quotation...</strong></div>';
                    
                    setTimeout(() => {
                        window.location.href = json.data.redirect_url;
                    }, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Hotel Booking Request';
                }
            })
            .catch(err => {
                console.error('Booking error:', err);
                document.getElementById('booking-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error">An error occurred. Please try again.</div>';
                btn.disabled = false;
                btn.textContent = 'Submit Hotel Booking Request';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// YACHT BOOKING SCRIPT
// ============================================================================

function bm_get_yacht_booking_script() {
    ob_start();
    ?>
    <script>
    (function() {
        const form = document.getElementById('yacht-booking-form');
        const yachtSelect = document.getElementById('yacht-type');
        const yachtInfo = document.getElementById('yacht-info');
        const yachtDescription = document.getElementById('yacht-description');
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('yacht-date').min = today;
        
        // Show yacht info when selected
        yachtSelect.addEventListener('change', function() {
            if (this.value) {
                const option = this.options[this.selectedIndex];
                yachtDescription.textContent = option.dataset.description;
                yachtInfo.style.display = 'block';
                
                const maxGuests = option.dataset.maxGuests;
                document.getElementById('max-guests-note').textContent = `Maximum ${maxGuests} guests`;
                
                calculateYachtPrice();
            } else {
                yachtInfo.style.display = 'none';
            }
        });
        
        // Calculate yacht price
        function calculateYachtPrice() {
            const yachtOption = yachtSelect.options[yachtSelect.selectedIndex];
            if (!yachtOption || !yachtOption.value) return;
            
            const duration = parseInt(document.getElementById('yacht-duration').value) || 0;
            const unit = document.getElementById('yacht-unit').value;
            
            const price = unit === 'hours' 
                ? parseFloat(yachtOption.dataset.priceHour) 
                : parseFloat(yachtOption.dataset.priceDay);
            
            const total = price * duration;
            const depositPercentage = 30;
            const deposit = (total * depositPercentage) / 100;
            
            document.getElementById('yacht-subtotal').value = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('summary-total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('summary-deposit').textContent = '₱' + deposit.toLocaleString('en-US', {minimumFractionDigits: 2});
        }
        
        // Price change listeners
        document.getElementById('yacht-duration').addEventListener('input', calculateYachtPrice);
        document.getElementById('yacht-unit').addEventListener('change', calculateYachtPrice);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_booking');
            formData.append('include_yacht', '1');
            
            // Set dummy hotel dates (required by backend)
            const rentalDate = formData.get('rental_date');
            formData.append('check_in_date', rentalDate);
            formData.append('check_out_date', rentalDate);
            formData.append('num_adults', '1');
            formData.append('num_children', '0');
            formData.append('yacht_rental_date', rentalDate);
            formData.append('yacht_duration', document.getElementById('yacht-duration').value);
            formData.append('yacht_duration_unit', document.getElementById('yacht-unit').value);
            formData.append('yacht_num_guests', document.getElementById('yacht-guests').value);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-success">' + 
                        json.data.message + 
                        '<br><br><strong>Redirecting to your quotation...</strong></div>';
                    
                    setTimeout(() => {
                        window.location.href = json.data.redirect_url;
                    }, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Yacht Rental Request';
                }
            })
            .catch(err => {
                console.error('Booking error:', err);
                document.getElementById('booking-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error">An error occurred. Please try again.</div>';
                btn.disabled = false;
                btn.textContent = 'Submit Yacht Rental Request';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// CAR BOOKING SCRIPT
// ============================================================================

function bm_get_car_booking_script() {
    ob_start();
    ?>
    <script>
    (function() {
        const form = document.getElementById('car-booking-form');
        const carSelect = document.getElementById('car-type');
        const carInfo = document.getElementById('car-info');
        const carDescription = document.getElementById('car-description');
        
        // Set minimum dates to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('car-pickup').min = today;
        document.getElementById('car-return').min = today;
        
        // Show car info when selected
        carSelect.addEventListener('change', function() {
            if (this.value) {
                const option = this.options[this.selectedIndex];
                carDescription.textContent = option.dataset.description;
                carInfo.style.display = 'block';
                
                const driverFee = parseFloat(option.dataset.driverFee);
                if (driverFee > 0) {
                    document.getElementById('driver-fee-display').textContent = 
                        `(+₱${driverFee.toFixed(2)}/day)`;
                }
                
                calculateCarPrice();
            } else {
                carInfo.style.display = 'none';
            }
        });
        
        // Calculate car price
        function calculateCarPrice() {
            const carOption = carSelect.options[carSelect.selectedIndex];
            if (!carOption || !carOption.value) return;
            
            const pickup = document.getElementById('car-pickup').value;
            const returnDate = document.getElementById('car-return').value;
            
            if (!pickup || !returnDate) return;
            
            const date1 = new Date(pickup);
            const date2 = new Date(returnDate);
            const diffTime = date2 - date1;
            const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (days <= 0) return;
            
            const pricePerDay = parseFloat(carOption.dataset.price);
            const driverFee = parseFloat(carOption.dataset.driverFee);
            const withDriver = document.getElementById('car-with-driver').checked;
            
            let total = pricePerDay * days;
            if (withDriver) {
                total += driverFee * days;
            }
            
            const depositPercentage = 30;
            const deposit = (total * depositPercentage) / 100;
            
            document.getElementById('car-subtotal').value = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('summary-total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('summary-deposit').textContent = '₱' + deposit.toLocaleString('en-US', {minimumFractionDigits: 2});
        }
        
        // Price change listeners
        document.getElementById('car-pickup').addEventListener('change', function() {
            document.getElementById('car-return').min = this.value;
            calculateCarPrice();
        });
        document.getElementById('car-return').addEventListener('change', calculateCarPrice);
        document.getElementById('car-with-driver').addEventListener('change', calculateCarPrice);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_booking');
            formData.append('include_car', '1');
            
            // Set dummy hotel dates (required by backend)
            const pickupDate = formData.get('pickup_date');
            const returnDate = formData.get('return_date');
            formData.append('check_in_date', pickupDate);
            formData.append('check_out_date', returnDate);
            formData.append('num_adults', '1');
            formData.append('num_children', '0');
            formData.append('car_pickup_date', pickupDate);
            formData.append('car_return_date', returnDate);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-success">' + 
                        json.data.message + 
                        '<br><br><strong>Redirecting to your quotation...</strong></div>';
                    
                    setTimeout(() => {
                        window.location.href = json.data.redirect_url;
                    }, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Car Rental Request';
                }
            })
            .catch(err => {
                console.error('Booking error:', err);
                document.getElementById('booking-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error">An error occurred. Please try again.</div>';
                btn.disabled = false;
                btn.textContent = 'Submit Car Rental Request';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}


// ============================================================================
// CUSTOMER BOOKING FORM SHORTCODE
// ============================================================================



/**
 * BOOKING MANAGEMENT MODULE - PART 2
 * AJAX Handlers and Helper Functions
 * 
 * This file contains all the AJAX handlers and helper functions.
 * Append this to the main file or include it.
 */

// ============================================================================
// QUOTATION VIEW SHORTCODE
// ============================================================================


function bntm_shortcode_bm_view_quotation() {
    if (!isset($_GET['booking_id'])) {
        return '<div class="bntm-notice bntm-notice-error">Invalid booking reference.</div>';
    }
    
    $booking_rand_id = sanitize_text_field($_GET['booking_id']);
    
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bm_bookings';
    $rooms_table = $wpdb->prefix . 'bm_rooms';
    $yachts_table = $wpdb->prefix . 'bm_yacht_rentals';
    $cars_table = $wpdb->prefix . 'bm_car_rentals';
    
    // Get booking
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE rand_id = %s",
        $booking_rand_id
    ));
    
    if (!$booking) {
        return '<div class="bntm-notice bntm-notice-error">Booking not found.</div>';
    }
    
    // Get rooms
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$rooms_table} WHERE booking_id = %d",
        $booking->id
    ));
    
    // Get yacht rental
    $yacht = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$yachts_table} WHERE booking_id = %d",
        $booking->id
    ));
    
    // Get car rental
    $car = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$cars_table} WHERE booking_id = %d",
        $booking->id
    ));
    
    // Get settings
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel Booking System');
    $hotel_email = bntm_get_setting('bm_hotel_email', '');
    $hotel_phone = bntm_get_setting('bm_hotel_phone', '');
    $hotel_address = bntm_get_setting('bm_hotel_address', '');
    $enable_paymaya = bntm_get_setting('bm_enable_paymaya', '0');
    
    $nonce = wp_create_nonce('bm_payment_action');
    
    // Calculate nights
    $checkin = new DateTime($booking->check_in_date);
    $checkout = new DateTime($booking->check_out_date);
    $nights = $checkin->diff($checkout)->days;
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <style>
    /* Formal Quotation Design Styles */
    :root {
        --ink: #1a1a2e;
        --ink-light: #3a3a4e;
        --ink-lighter: #6a6a7e;
        --paper: #fefefe;
        --accent: #c29958;
        --accent-dark: #a67c40;
        --border: #d4d4d8;
        --success: #2d5016;
        --success-bg: #f0f7ed;
        --shadow: rgba(26, 26, 46, 0.08);
    }
    
    .bm-quotation-container {
        max-width: 900px;
        margin: 2rem auto;
        background: var(--paper);
        box-shadow: 0 4px 6px var(--shadow), 0 12px 24px var(--shadow);
        border: 1px solid var(--border);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        color: var(--ink);
        line-height: 1.6;
    }
    
    .bm-quotation-container::before {
        content: '';
        display: block;
        height: 6px;
        background: linear-gradient(90deg, var(--accent-dark) 0%, var(--accent) 50%, var(--accent-dark) 100%);
    }
    
    .bm-quotation-header {
        padding: 3rem 3rem 2rem;
        border-bottom: 2px solid var(--border);
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 2rem;
        position: relative;
        background: var(--paper);
    }
    
    .bm-quotation-header::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 3rem;
        width: 120px;
        height: 2px;
        background: var(--accent);
    }
    
    .bm-quotation-logo h1 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -0.02em;
        margin: 0 0 0.5rem 0;
    }
    
    .bm-quotation-logo p {
        font-size: 0.9rem;
        color: var(--ink-lighter);
        font-weight: 300;
        line-height: 1.5;
        margin: 0.25rem 0;
    }
    
    .bm-quotation-info {
        text-align: right;
    }
    
    .bm-quotation-info h2 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--accent-dark);
        letter-spacing: 0.15em;
        text-transform: uppercase;
        margin: 0 0 0.75rem 0;
    }
    
    .bm-quotation-info p {
        font-size: 0.85rem;
        margin: 0.3rem 0;
        color: var(--ink-light);
    }
    
    .bm-quotation-info strong {
        font-weight: 600;
        color: var(--ink);
    }
    
    .bm-status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 2px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-left: 0.5rem;
    }
    
    .bm-status-confirmed {
        background: #e8f5e9;
        color: #2e7d32;
        border: 1px solid #a5d6a7;
    }
    
    .bm-status-pending_approval,
    .bm-status-pending {
        background: #fff3e0;
        color: #e65100;
        border: 1px solid #ffcc80;
    }
    
    .bm-status-paid,
    .bm-status-completed {
        background: var(--success-bg);
        color: var(--success);
        border: 1px solid #c3e6b8;
    }
    
    .bm-status-cancelled {
        background: #ffebee;
        color: #c62828;
        border: 1px solid #ef9a9a;
    }
    
    .bm-quotation-section {
        padding: 2.5rem 3rem;
        border-bottom: 1px solid var(--border);
    }
    
    .bm-quotation-section:last-child {
        border-bottom: none;
    }
    
    .bm-quotation-section h3 {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0 0 1.5rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border);
        position: relative;
    }
    
    .bm-quotation-section h3::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 60px;
        height: 1px;
        background: var(--accent);
    }
    
    .bm-quotation-section h4 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0 0 0.5rem 0;
    }
    
    .bm-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }
    
    .bm-info-grid > div {
        padding: 1rem;
        background: #fafafa;
        border-left: 3px solid var(--accent);
    }
    
    .bm-info-grid strong {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ink-lighter);
        margin-bottom: 0.3rem;
    }
    
    .bm-quotation-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 1.5rem;
        border: 1px solid var(--border);
        font-size: 0.9rem;
    }
    
    .bm-quotation-table thead {
        background: var(--ink);
        color: var(--paper);
    }
    
    .bm-quotation-table th {
        padding: 0.9rem 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border: none;
    }
    
    .bm-quotation-table th:last-child {
        text-align: right;
    }
    
    .bm-quotation-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background 0.2s ease;
    }
    
    .bm-quotation-table tbody tr:hover {
        background: #fafafa;
    }
    
    .bm-quotation-table tbody tr:last-child {
        border-bottom: none;
    }
    
    .bm-quotation-table td {
        padding: 1rem;
        color: var(--ink-light);
        border: none;
    }
    
    .bm-quotation-table td:first-child {
        font-weight: 500;
        color: var(--ink);
    }
    
    .bm-quotation-table td:last-child {
        text-align: right;
        font-weight: 600;
        color: var(--ink);
    }
    
    .bm-quotation-summary {
        background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
    }
    
    .bm-summary-grid {
        max-width: 400px;
        margin-left: auto;
    }
    
    .bm-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.95rem;
    }
    
    .bm-summary-row:last-child {
        border-bottom: none;
    }
    
    .bm-summary-row span:first-child {
        color: var(--ink-light);
        font-weight: 400;
    }
    
    .bm-summary-row strong {
        font-weight: 600;
        color: var(--ink);
    }
    
    .bm-summary-total {
        background: var(--ink);
        color: var(--paper);
        margin: 1rem -1rem -1rem;
        padding: 1.25rem 1rem;
        font-size: 1.1rem;
        border-radius: 0;
    }
    
    .bm-summary-total span,
    .bm-summary-total strong {
        color: var(--paper);
    }
    
    .bm-summary-total strong {
        font-size: 1.4rem;
        font-weight: 700;
    }
    
    .bm-payment-option {
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        background: #fafafa;
        border: 1px solid var(--border);
        border-radius: 2px;
        transition: all 0.3s ease;
    }
    
    .bm-payment-option:hover {
        border-color: var(--accent);
        box-shadow: 0 2px 8px var(--shadow);
    }
    
    .bm-payment-option:last-child {
        margin-bottom: 0;
    }
    
    .bm-payment-option h4 {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }
    
    .bm-payment-option p {
        font-size: 0.9rem;
        color: var(--ink-light);
        margin-bottom: 1rem;
    }
    
    .bm-bank-details {
        background: white;
        padding: 1.25rem;
        border-left: 3px solid var(--accent);
        margin-top: 1rem;
    }
    
    .bm-bank-details p {
        margin: 0.5rem 0;
        font-size: 0.9rem;
        color: var(--ink-light);
    }
    
    .bm-bank-details strong {
        color: var(--ink);
        font-weight: 600;
    }
    
    .bm-btn-primary,
    .bm-btn-secondary {
        display: inline-block;
        padding: 0.75rem 2rem;
        border: none;
        border-radius: 2px;
        font-size: 0.9rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: inherit;
        text-decoration: none;
    }
    
    .bm-btn-primary {
        background: var(--accent);
        color: white;
        border: 2px solid var(--accent);
    }
    
    .bm-btn-primary:hover {
        background: var(--accent-dark);
        border-color: var(--accent-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(194, 153, 88, 0.3);
    }
    
    .bm-btn-primary:active {
        transform: translateY(0);
    }
    
    .bm-btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
    
    .bm-btn-secondary {
        background: transparent;
        color: var(--ink);
        border: 2px solid var(--border);
    }
    
    .bm-btn-secondary:hover {
        border-color: var(--ink);
        background: var(--ink);
        color: var(--paper);
    }
    
    .bm-payment-success {
        background: var(--success-bg) !important;
        border: 2px solid #c3e6b8 !important;
        border-left: 4px solid var(--success) !important;
    }
    
    .bm-payment-success h3 {
        color: var(--success) !important;
        border-bottom: none !important;
        margin-bottom: 0.5rem !important;
    }
    
    .bm-payment-success h3::after {
        display: none !important;
    }
    
    .bm-payment-success p {
        color: var(--success) !important;
        font-size: 0.95rem;
        margin: 0;
    }
    
    .bm-quotation-footer {
        background: #fafafa;
        padding: 2.5rem 3rem;
        border-top: 1px solid var(--border);
    }
    
    .bm-quotation-footer > p {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--ink);
        margin: 0 0 1rem 0;
    }
    
    .bm-quotation-footer ul {
        list-style: none;
        padding: 0;
        margin: 0 0 2rem 0;
    }
    
    .bm-quotation-footer li {
        padding: 0.5rem 0;
        padding-left: 1.5rem;
        position: relative;
        color: var(--ink-light);
        font-size: 0.9rem;
    }
    
    .bm-quotation-footer li::before {
        content: '•';
        position: absolute;
        left: 0;
        color: var(--accent);
        font-size: 1.2rem;
        line-height: 1;
    }
    
    .bm-quotation-contact {
        background: white;
        padding: 1.5rem;
        border-left: 3px solid var(--accent);
        margin-bottom: 1.5rem;
    }
    
    .bm-quotation-contact p {
        font-size: 0.9rem;
        color: var(--ink-light);
        margin: 0;
    }
    
    .bm-quotation-contact strong {
        color: var(--ink);
        font-weight: 600;
    }
    
    .bm-quotation-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }
    
    .bm-quotation-section > p {
        color: var(--ink-light);
        line-height: 1.8;
    }
    
    @media print {
        .bm-quotation-container {
            box-shadow: none;
            border: none;
            margin: 0;
        }
        
        .bm-btn-primary,
        .bm-btn-secondary,
        #pay-paymaya-btn {
            display: none !important;
        }
        
        .bm-payment-option,
        .bm-quotation-section {
            page-break-inside: avoid;
        }
    }
    
    @media (max-width: 768px) {
        .bm-quotation-container {
            margin: 1rem;
        }
        
        .bm-quotation-header {
            grid-template-columns: 1fr;
            padding: 2rem 1.5rem 1.5rem;
        }
        
        .bm-quotation-header::after {
            left: 1.5rem;
        }
        
        .bm-quotation-info {
            text-align: left;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        
        .bm-quotation-section {
            padding: 2rem 1.5rem;
        }
        
        .bm-info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .bm-quotation-table {
            font-size: 0.8rem;
        }
        
        .bm-quotation-table th,
        .bm-quotation-table td {
            padding: 0.75rem 0.5rem;
        }
        
        .bm-quotation-table th {
            font-size: 0.7rem;
        }
        
        .bm-summary-grid {
            max-width: 100%;
        }
        
        .bm-quotation-actions {
            flex-direction: column;
        }
        
        .bm-btn-primary,
        .bm-btn-secondary {
            width: 100%;
            text-align: center;
        }
        
        .bm-quotation-footer {
            padding: 2rem 1.5rem;
        }
    }
    
    @media (max-width: 480px) {
        .bm-quotation-logo h1 {
            font-size: 1.5rem;
        }
        
        .bm-quotation-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
    }
    </style>
    
    <div class="bm-quotation-container">
        <div class="bm-quotation-header">
            <div class="bm-quotation-logo">
                <h1><?php echo esc_html($hotel_name); ?></h1>
                <p><?php echo esc_html($hotel_address); ?></p>
                <p>Email: <?php echo esc_html($hotel_email); ?> | Phone: <?php echo esc_html($hotel_phone); ?></p>
            </div>
            
            <div class="bm-quotation-info">
                <h2>BOOKING QUOTATION</h2>
                <p><strong>Booking ID:</strong> #<?php echo esc_html($booking->rand_id); ?></p>
                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($booking->created_at)); ?></p>
                <p>
                    <strong>Status:</strong> 
                    <span class="bm-status-badge bm-status-<?php echo $booking->booking_status; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $booking->booking_status)); ?>
                    </span>
                </p>
            </div>
        </div>
        
        <div class="bm-quotation-section">
            <h3>Customer Information</h3>
            <div class="bm-info-grid">
                <div>
                    <strong>Name:</strong><br>
                    <?php echo esc_html($booking->customer_name); ?>
                </div>
                <div>
                    <strong>Email:</strong><br>
                    <?php echo esc_html($booking->customer_email); ?>
                </div>
                <div>
                    <strong>Phone:</strong><br>
                    <?php echo esc_html($booking->customer_phone); ?>
                </div>
                <div>
                    <strong>Address:</strong><br>
                    <?php echo esc_html($booking->customer_address ?: 'Not provided'); ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($rooms)): ?>
        <div class="bm-quotation-section">
            <h3>Hotel Accommodation</h3>
            <div class="bm-info-grid">
                <div>
                    <strong>Check-In:</strong><br>
                    <?php echo date('F d, Y', strtotime($booking->check_in_date)); ?>
                </div>
                <div>
                    <strong>Check-Out:</strong><br>
                    <?php echo date('F d, Y', strtotime($booking->check_out_date)); ?>
                </div>
                <div>
                    <strong>Guests:</strong><br>
                    <?php echo $booking->num_adults; ?> Adults
                    <?php if ($booking->num_children > 0): ?>
                        , <?php echo $booking->num_children; ?> Children
                    <?php endif; ?>
                </div>
                <div>
                    <strong>Nights:</strong><br>
                    <?php echo $nights; ?>
                </div>
            </div>
            
            <table class="bm-quotation-table">
                <thead>
                    <tr>
                        <th>Room Type</th>
                        <th>Quantity</th>
                        <th>Price/Night</th>
                        <th>Nights</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?php echo esc_html($room->room_name); ?></td>
                        <td><?php echo $room->num_rooms; ?> room(s)</td>
                        <td>₱<?php echo number_format($room->price_per_night, 2); ?></td>
                        <td><?php echo $room->num_nights; ?></td>
                        <td><strong>₱<?php echo number_format($room->subtotal, 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($yacht): ?>
        <div class="bm-quotation-section">
            <h3>Yacht Rental</h3>
            <table class="bm-quotation-table">
                <thead>
                    <tr>
                        <th>Yacht Type</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Guests</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html($yacht->yacht_type); ?></td>
                        <td><?php echo date('F d, Y', strtotime($yacht->rental_date)); ?></td>
                        <td><?php echo $yacht->rental_duration; ?> <?php echo $yacht->duration_unit; ?></td>
                        <td><?php echo $yacht->num_guests; ?> guests</td>
                        <td><strong>₱<?php echo number_format($yacht->price, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($car): ?>
        <div class="bm-quotation-section">
            <h3>Car Rental</h3>
            <table class="bm-quotation-table">
                <thead>
                    <tr>
                        <th>Car Type</th>
                        <th>Pickup Date</th>
                        <th>Return Date</th>
                        <th>Days</th>
                        <th>With Driver</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html($car->car_type); ?></td>
                        <td><?php echo date('F d, Y', strtotime($car->pickup_date)); ?></td>
                        <td><?php echo date('F d, Y', strtotime($car->return_date)); ?></td>
                        <td><?php echo $car->num_days; ?> days</td>
                        <td><?php echo $car->with_driver ? 'Yes' : 'No'; ?></td>
                        <td><strong>₱<?php echo number_format($car->subtotal, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if ($booking->special_requests): ?>
        <div class="bm-quotation-section">
            <h3>Special Requests</h3>
            <p><?php echo nl2br(esc_html($booking->special_requests)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bm-quotation-section bm-quotation-summary">
            <h3>Payment Summary</h3>
            <div class="bm-summary-grid">
                <div class="bm-summary-row">
                    <span>Subtotal:</span>
                    <strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row">
                    <span>Deposit Required (<?php echo bntm_get_setting('bm_deposit_percentage', '30'); ?>%):</span>
                    <strong>₱<?php echo number_format($booking->deposit_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-total">
                    <div class="bm-summary-row">
                        <span>Total Amount:</span>
                        <strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($booking->payment_status !== 'paid'): ?>
        <div class="bm-quotation-section">
            <h3>Payment Options</h3>
            
            <?php if ($enable_paymaya == '1'): ?>
            <div class="bm-payment-option">
                <h4>Pay Online with PayMaya</h4>
                <p>Secure payment processing - Credit Card, Debit Card, or GCash</p>
                <button id="pay-paymaya-btn" class="bm-btn-primary" 
                        data-booking-id="<?php echo $booking->id; ?>"
                        data-amount="<?php echo $booking->deposit_amount; ?>"
                        data-nonce="<?php echo $nonce; ?>">
                    Pay Deposit (₱<?php echo number_format($booking->deposit_amount, 2); ?>) with PayMaya
                </button>
            </div>
            <?php endif; ?>
            
            <div class="bm-payment-option">
                <h4>Bank Transfer</h4>
                <p>Please transfer to the following account and send proof of payment to <?php echo esc_html($hotel_email); ?>:</p>
                <div class="bm-bank-details">
                    <p><strong>Bank Name:</strong> [Your Bank Name]</p>
                    <p><strong>Account Name:</strong> <?php echo esc_html($hotel_name); ?></p>
                    <p><strong>Account Number:</strong> [Your Account Number]</p>
                    <p><strong>Reference:</strong> <?php echo esc_html($booking->rand_id); ?></p>
                </div>
            </div>
            
            <div class="bm-payment-option">
                <h4>Pay at Hotel</h4>
                <p>You can also pay the deposit when you arrive at our hotel. Please note that your reservation is not confirmed until payment is received.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="bm-quotation-section bm-payment-success">
            <h3>Payment Received</h3>
            <p>Thank you! Your payment has been received and your booking is confirmed.</p>
        </div>
        <?php endif; ?>
        
        <div class="bm-quotation-footer">
            <p><strong>Terms & Conditions:</strong></p>
            <ul>
                <li>Deposit is required to confirm your booking</li>
                <li>Cancellation must be made 48 hours before check-in for full refund</li>
                <li>Check-in time: 2:00 PM | Check-out time: 12:00 PM</li>
                <li>Additional charges may apply for extra guests or services</li>
            </ul>
            
            <div class="bm-quotation-contact">
                <p><strong>Questions?</strong> Contact us at <?php echo esc_html($hotel_phone); ?> or <?php echo esc_html($hotel_email); ?></p>
            </div>
            
            <div class="bm-quotation-actions">
                <button onclick="window.print()" class="bm-btn-secondary">Print Quotation</button>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const payBtn = document.getElementById('pay-paymaya-btn');
        
        if (payBtn) {
            payBtn.addEventListener('click', function() {
                if (!confirm('Proceed to PayMaya payment gateway?')) return;
                
                const formData = new FormData();
                formData.append('action', 'bm_process_payment');
                formData.append('booking_id', this.dataset.bookingId);
                formData.append('amount', this.dataset.amount);
                formData.append('gateway', 'paymaya');
                formData.append('nonce', this.dataset.nonce);
                
                this.disabled = true;
                this.textContent = 'Processing...';
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success && json.data.redirect_url) {
                        window.location.href = json.data.redirect_url;
                    } else {
                        alert(json.data.message || 'Payment failed');
                        this.disabled = false;
                        this.textContent = 'Pay Deposit with PayMaya';
                    }
                })
                .catch(err => {
                    console.error('Payment error:', err);
                    alert('An error occurred. Please try again.');
                    this.disabled = false;
                    this.textContent = 'Pay Deposit with PayMaya';
                });
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLER FUNCTIONS
// ============================================================================

/**
 * Save room type
 */
function bntm_ajax_bm_save_room_type() {
    check_ajax_referer('bm_room_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_room_types';
    $business_id = get_current_user_id();
    
    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    $room_data = [
        'business_id' => $business_id,
        'room_type' => sanitize_text_field($_POST['room_type']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price_per_night' => floatval($_POST['price_per_night']),
        'max_occupancy' => intval($_POST['max_occupancy']),
        'amenities' => sanitize_text_field($_POST['amenities']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    $format = ['%d', '%s', '%s', '%f', '%d', '%s', '%d'];
    
    if ($room_id > 0) {
        // Update existing
        $result = $wpdb->update($table, $room_data, ['id' => $room_id], $format, ['%d']);
        $message = 'Room type updated successfully!';
    } else {
        // Insert new
        $room_data['rand_id'] = bntm_rand_id();
        array_unshift($format, '%s');
        $result = $wpdb->insert($table, $room_data, $format);
        $message = 'Room type added successfully!';
    }
    
    if ($result !== false) {
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to save room type']);
    }
}

/**
 * Delete room type
 */
function bntm_ajax_bm_delete_room_type() {
    check_ajax_referer('bm_room_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_room_types';
    $room_id = intval($_POST['room_id']);
    
    $result = $wpdb->delete($table, ['id' => $room_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Room type deleted']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete room type']);
    }
}

/**
 * Save yacht type
 */
function bntm_ajax_bm_save_yacht_type() {
    check_ajax_referer('bm_yacht_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_yacht_types';
    $business_id = get_current_user_id();
    
    $yacht_id = isset($_POST['yacht_id']) ? intval($_POST['yacht_id']) : 0;
    $yacht_data = [
        'business_id' => $business_id,
        'yacht_name' => sanitize_text_field($_POST['yacht_name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price_per_hour' => floatval($_POST['price_per_hour']),
        'price_per_day' => floatval($_POST['price_per_day']),
        'max_guests' => intval($_POST['max_guests']),
        'features' => sanitize_text_field($_POST['features']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    $format = ['%d', '%s', '%s', '%f', '%f', '%d', '%s', '%d'];
    
    if ($yacht_id > 0) {
        $result = $wpdb->update($table, $yacht_data, ['id' => $yacht_id], $format, ['%d']);
        $message = 'Yacht updated successfully!';
    } else {
        $yacht_data['rand_id'] = bntm_rand_id();
        array_unshift($format, '%s');
        $result = $wpdb->insert($table, $yacht_data, $format);
        $message = 'Yacht added successfully!';
    }
    
    if ($result !== false) {
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to save yacht']);
    }
}

/**
 * Delete yacht type
 */
function bntm_ajax_bm_delete_yacht_type() {
    check_ajax_referer('bm_yacht_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_yacht_types';
    $yacht_id = intval($_POST['yacht_id']);
    
    $result = $wpdb->delete($table, ['id' => $yacht_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Yacht deleted']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete yacht']);
    }
}

/**
 * Save car type
 */
function bntm_ajax_bm_save_car_type() {
    check_ajax_referer('bm_car_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_car_types';
    $business_id = get_current_user_id();
    
    $car_id = isset($_POST['car_id']) ? intval($_POST['car_id']) : 0;
    $car_data = [
        'business_id' => $business_id,
        'car_name' => sanitize_text_field($_POST['car_name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'price_per_day' => floatval($_POST['price_per_day']),
        'driver_fee' => floatval($_POST['driver_fee']),
        'max_passengers' => intval($_POST['max_passengers']),
        'features' => sanitize_text_field($_POST['features']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    $format = ['%d', '%s', '%s', '%f', '%f', '%d', '%s', '%d'];
    
    if ($car_id > 0) {
        $result = $wpdb->update($table, $car_data, ['id' => $car_id], $format, ['%d']);
        $message = 'Car updated successfully!';
    } else {
        $car_data['rand_id'] = bntm_rand_id();
        array_unshift($format, '%s');
        $result = $wpdb->insert($table, $car_data, $format);
        $message = 'Car added successfully!';
    }
    
    if ($result !== false) {
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to save car']);
    }
}

/**
 * Delete car type
 * FIXED: Corrected syntax error with $wpdb
 */
function bntm_ajax_bm_delete_car_type() {
    check_ajax_referer('bm_car_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_car_types';
    $car_id = intval($_POST['car_id']);
    
    // FIXED: Was missing $ and had weird syntax
    $result = $wpdb->delete($table, ['id' => $car_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Car deleted']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete car']);
    }
}

/**
 * Update booking status
 */
function bntm_ajax_bm_update_booking_status() {
    check_ajax_referer('bm_booking_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_bookings';
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $allowed_statuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled'];
    if (!in_array($status, $allowed_statuses)) {
        wp_send_json_error(['message' => 'Invalid status']);
    }
    
    $update_data = ['booking_status' => $status];
    
    if ($status === 'confirmed' && !$wpdb->get_var($wpdb->prepare(
        "SELECT confirmed_at FROM {$table} WHERE id = %d", $booking_id
    ))) {
        $update_data['confirmed_at'] = current_time('mysql');
    }
    
    $result = $wpdb->update($table, $update_data, ['id' => $booking_id], ['%s', '%s'], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Booking status updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}

/**
 * Confirm provider phone call
 */
function bntm_ajax_bm_confirm_provider_phone() {
    check_ajax_referer('bm_booking_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_bookings';
    $booking_id = intval($_POST['booking_id']);
    
    $result = $wpdb->update(
        $table,
        [
            'provider_phone_confirmed' => 1,
            'provider_phone_confirmed_at' => current_time('mysql')
        ],
        ['id' => $booking_id],
        ['%d', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Phone confirmation recorded']);
    } else {
        wp_send_json_error(['message' => 'Failed to confirm']);
    }
}

/**
 * Resend quotation email
 */
function bntm_ajax_bm_resend_quotation() {
    check_ajax_referer('bm_booking_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_bookings';
    $booking_id = intval($_POST['booking_id']);
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d", $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    $sent = bm_send_quotation_email($booking);
    
    if ($sent) {
        $wpdb->update($table, 
            ['quotation_sent_at' => current_time('mysql')], 
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
        wp_send_json_success(['message' => 'Quotation email sent successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to send email']);
    }
}

/**
 * AJAX: Get room price
 * NEWLY ADDED - Was missing from original
 */
function bntm_ajax_bm_get_room_price() {
    $room_id = intval($_POST['room_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_room_types';
    
    $room = $wpdb->get_row($wpdb->prepare(
        "SELECT price_per_night FROM {$table} WHERE id = %d AND is_active = 1",
        $room_id
    ));
    
    if ($room) {
        wp_send_json_success(['price' => $room->price_per_night]);
    } else {
        wp_send_json_error(['message' => 'Room not found']);
    }
}

/**
 * AJAX: Get yacht price
 * NEWLY ADDED - Was missing from original
 */
function bntm_ajax_bm_get_yacht_price() {
    $yacht_id = intval($_POST['yacht_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_yacht_types';
    
    $yacht = $wpdb->get_row($wpdb->prepare(
        "SELECT price_per_hour, price_per_day FROM {$table} WHERE id = %d AND is_active = 1",
        $yacht_id
    ));
    
    if ($yacht) {
        wp_send_json_success([
            'price_hour' => $yacht->price_per_hour,
            'price_day' => $yacht->price_per_day
        ]);
    } else {
        wp_send_json_error(['message' => 'Yacht not found']);
    }
}

/**
 * AJAX: Get car price
 * NEWLY ADDED - Was missing from original
 */
function bntm_ajax_bm_get_car_price() {
    $car_id = intval($_POST['car_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'bm_car_types';
    
    $car = $wpdb->get_row($wpdb->prepare(
        "SELECT price_per_day, driver_fee FROM {$table} WHERE id = %d AND is_active = 1",
        $car_id
    ));
    
    if ($car) {
        wp_send_json_success([
            'price_per_day' => $car->price_per_day,
            'driver_fee' => $car->driver_fee
        ]);
    } else {
        wp_send_json_error(['message' => 'Car not found']);
    }
}

/**
 * Save hotel information
 */
function bntm_ajax_bm_save_hotel_info() {
    check_ajax_referer('bm_settings_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    bntm_set_setting('bm_hotel_name', sanitize_text_field($_POST['hotel_name']));
    bntm_set_setting('bm_hotel_email', sanitize_email($_POST['hotel_email']));
    bntm_set_setting('bm_hotel_phone', sanitize_text_field($_POST['hotel_phone']));
    bntm_set_setting('bm_hotel_address', sanitize_textarea_field($_POST['hotel_address']));
    bntm_set_setting('bm_deposit_percentage', intval($_POST['deposit_percentage']));
    
    wp_send_json_success(['message' => 'Hotel information saved successfully!']);
}

/**
 * Save PayMaya settings
 */
function bntm_ajax_bm_save_paymaya_settings() {
    check_ajax_referer('bm_settings_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $enable_paymaya = isset($_POST['enable_paymaya']) ? '1' : '0';
    
    bntm_set_setting('bm_enable_paymaya', $enable_paymaya);
    bntm_set_setting('bm_paymaya_mode', sanitize_text_field($_POST['paymaya_mode']));
    bntm_set_setting('bm_paymaya_public_key', sanitize_text_field($_POST['paymaya_public_key']));
    bntm_set_setting('bm_paymaya_secret_key', sanitize_text_field($_POST['paymaya_secret_key']));
    
    wp_send_json_success(['message' => 'PayMaya settings saved successfully!']);
}


/**
 * BOOKING MANAGEMENT MODULE - PART 3
 * Helper Functions, Email Templates, Payment Processing
 * 
 * This file contains helper functions, email templates, and payment processing.
 * Append this to the main file or include it.
 */

// ==========================================================================================
// SUBMIT BOOKING HANDLER - COMPLETE WITH ALL FIXES
// ============================================================================

/**
 * Submit customer booking
 */
function bntm_ajax_bm_submit_booking() {
    global $wpdb;
    
    // Get selected services
    $selected_services = [];
    if (!empty($_POST['selected_services'])) {
        $selected_services = array_map('sanitize_text_field', explode(',', $_POST['selected_services']));
    }
    
    // Always required
    $required_fields = ['customer_name', 'customer_email', 'customer_phone'];
    
    // Add type-specific required fields based on selected services
    if (in_array('hotel', $selected_services)) {
        $required_fields = array_merge($required_fields, ['check_in_date', 'check_out_date']);
    }
    if (in_array('yacht', $selected_services)) {
        $required_fields = array_merge($required_fields, ['yacht_rental_date', 'yacht_duration']);
    }
    if (in_array('car', $selected_services)) {
        $required_fields = array_merge($required_fields, ['car_pickup_date', 'car_return_date']);
    }
    
    // Validate required fields
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error(['message' => 'Please fill in all required fields']);
        }
    }
    
    // Validate that at least one service was selected
    if (empty($selected_services)) {
        wp_send_json_error(['message' => 'Please select at least one service']);
    }
            
    $business_id = get_option('bntm_primary_business_id', 1);
    
    // Set dates for hotel if selected
    $nights = 0;
    if (in_array('hotel', $selected_services)) {
        $check_in = sanitize_text_field($_POST['check_in_date']);
        $check_out = sanitize_text_field($_POST['check_out_date']);
        
        $checkin_date = new DateTime($check_in);
        $checkout_date = new DateTime($check_out);
        $nights = $checkin_date->diff($checkout_date)->days;
        
        if ($nights <= 0) {
            wp_send_json_error(['message' => 'Check-out date must be after check-in date']);
        }
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Calculate totals
        $room_total = 0;
        $yacht_total = 0;
        $car_total = 0;
        
        // Process rooms only if hotel is selected
        $rooms_data = [];
        if (in_array('hotel', $selected_services) && isset($_POST['rooms']) && is_array($_POST['rooms'])) {
            $room_types_table = $wpdb->prefix . 'bm_room_types';
            
            foreach ($_POST['rooms'] as $room_input) {
                if (empty($room_input['room_type'])) continue;
                
                $room_type_id = intval($room_input['room_type']);
                $num_rooms = intval($room_input['num_rooms']);
                
                $room_type = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$room_types_table} WHERE id = %d AND is_active = 1",
                    $room_type_id
                ));
                
                if (!$room_type) {
                    throw new Exception('Invalid room type selected');
                }
                
                $subtotal = $room_type->price_per_night * $num_rooms * $nights;
                $room_total += $subtotal;
                
                $rooms_data[] = [
                    'room_type_id' => $room_type_id,
                    'room_name' => $room_type->room_type,
                    'num_rooms' => $num_rooms,
                    'price_per_night' => $room_type->price_per_night,
                    'num_nights' => $nights,
                    'subtotal' => $subtotal
                ];
            }
        }
        
        // Only require rooms for hotel bookings
        if (in_array('hotel', $selected_services) && empty($rooms_data)) {
            throw new Exception('Please select at least one room');
        }
        
        // Process yacht rental if selected
        $yacht_data = null;
        if (in_array('yacht', $selected_services) && !empty($_POST['yacht_type'])) {
            $yacht_types_table = $wpdb->prefix . 'bm_yacht_types';
            $yacht_type_id = intval($_POST['yacht_type']);
            
            $yacht_type = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$yacht_types_table} WHERE id = %d AND is_active = 1",
                $yacht_type_id
            ));
            
            if ($yacht_type) {
                $duration = intval($_POST['yacht_duration']);
                $unit = sanitize_text_field($_POST['yacht_duration_unit']);
                
                $price = $unit === 'hours' ? $yacht_type->price_per_hour : $yacht_type->price_per_day;
                $yacht_total = $price * $duration;
                
                $yacht_data = [
                    'yacht_type_id' => $yacht_type_id,
                    'yacht_name' => $yacht_type->yacht_name,
                    'rental_date' => sanitize_text_field($_POST['yacht_rental_date']),
                    'rental_duration' => $duration,
                    'duration_unit' => $unit,
                    'num_guests' => intval($_POST['yacht_num_guests']),
                    'price' => $yacht_total
                ];
            }
        }
        
        // Process car rental if selected
        $car_data = null;
        if (in_array('car', $selected_services) && !empty($_POST['car_type'])) {
            $car_types_table = $wpdb->prefix . 'bm_car_types';
            $car_type_id = intval($_POST['car_type']);
            
            $car_type = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$car_types_table} WHERE id = %d AND is_active = 1",
                $car_type_id
            ));
            
            if ($car_type) {
                $pickup = sanitize_text_field($_POST['car_pickup_date']);
                $return = sanitize_text_field($_POST['car_return_date']);
                
                $pickup_date = new DateTime($pickup);
                $return_date = new DateTime($return);
                $car_days = $pickup_date->diff($return_date)->days;
                
                if ($car_days > 0) {
                    $with_driver = isset($_POST['car_with_driver']) ? 1 : 0;
                    $car_total = $car_type->price_per_day * $car_days;
                    
                    if ($with_driver) {
                        $car_total += $car_type->driver_fee * $car_days;
                    }
                    
                    $car_data = [
                        'car_type_id' => $car_type_id,
                        'car_name' => $car_type->car_name,
                        'pickup_date' => $pickup,
                        'return_date' => $return,
                        'num_days' => $car_days,
                        'with_driver' => $with_driver,
                        'price_per_day' => $car_type->price_per_day,
                        'subtotal' => $car_total
                    ];
                }
            }
        }
        
        // Calculate total amount
        $total_amount = $room_total + $yacht_total + $car_total;
        $deposit_percentage = floatval(bntm_get_setting('bm_deposit_percentage', '30'));
        $deposit_amount = ($total_amount * $deposit_percentage) / 100;
        
        // Insert booking
        $bookings_table = $wpdb->prefix . 'bm_bookings';
        $booking_rand_id = bntm_rand_id();
        
        // Set default dates if hotel not selected
        if (!isset($check_in)) {
            $check_in = date('Y-m-d'); // Use today's date
        }
        if (!isset($check_out)) {
            $check_out = date('Y-m-d', strtotime('+1 day')); // Use tomorrow's date
        }
        
        $booking_insert = $wpdb->insert($bookings_table, [
            'rand_id' => $booking_rand_id,
            'business_id' => $business_id,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_address' => sanitize_textarea_field($_POST['customer_address'] ?? ''),
            'check_in_date' => $check_in,
            'check_out_date' => $check_out,
            'num_adults' => intval($_POST['num_adults'] ?? 1),
            'num_children' => intval($_POST['num_children'] ?? 0),
            'special_requests' => sanitize_textarea_field($_POST['special_requests'] ?? ''),
            'total_amount' => $total_amount,
            'deposit_amount' => $deposit_amount,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
            'created_at' => current_time('mysql')
        ], [
            '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s'
        ]);
        
        if (!$booking_insert) {
            throw new Exception('Failed to create booking');
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Insert rooms
        $rooms_table = $wpdb->prefix . 'bm_rooms';
        foreach ($rooms_data as $room) {
            $wpdb->insert($rooms_table, [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'booking_id' => $booking_id,
                'room_type' => $room['room_name'],
                'room_name' => $room['room_name'],
                'num_rooms' => $room['num_rooms'],
                'price_per_night' => $room['price_per_night'],
                'num_nights' => $room['num_nights'],
                'subtotal' => $room['subtotal']
            ], ['%s', '%d', '%d', '%s', '%s', '%d', '%f', '%d', '%f']);
        }
        
        // Insert yacht rental if exists
        if ($yacht_data) {
            $yacht_rentals_table = $wpdb->prefix . 'bm_yacht_rentals';
            $wpdb->insert($yacht_rentals_table, [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'booking_id' => $booking_id,
                'yacht_type' => $yacht_data['yacht_name'],
                'rental_date' => $yacht_data['rental_date'],
                'rental_duration' => $yacht_data['rental_duration'],
                'duration_unit' => $yacht_data['duration_unit'],
                'num_guests' => $yacht_data['num_guests'],
                'price' => $yacht_data['price']
            ], ['%s', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%f']);
        }
        
        // Insert car rental if exists
        if ($car_data) {
            $car_rentals_table = $wpdb->prefix . 'bm_car_rentals';
            $wpdb->insert($car_rentals_table, [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'booking_id' => $booking_id,
                'car_type' => $car_data['car_name'],
                'pickup_date' => $car_data['pickup_date'],
                'return_date' => $car_data['return_date'],
                'num_days' => $car_data['num_days'],
                'with_driver' => $car_data['with_driver'],
                'price_per_day' => $car_data['price_per_day'],
                'subtotal' => $car_data['subtotal']
            ], ['%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%f', '%f']);
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Get booking data
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bookings_table} WHERE id = %d", $booking_id
        ));
        
        // Send quotation email
        bm_send_quotation_email($booking);
        
        // Update quotation sent timestamp
        $wpdb->update($bookings_table, 
            ['quotation_sent_at' => current_time('mysql')], 
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
        
        // Generate quotation URL
        $quotation_url = home_url('/view-quotation/?booking_id=' . $booking_rand_id);
        
        wp_send_json_success([
            'message' => 'Booking request submitted successfully! Check your email for the quotation.',
            'redirect_url' => $quotation_url,
            'booking_id' => $booking_rand_id
        ]);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        // FIXED: Added error logging
        error_log('Booking creation failed: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ============================================================================
// PAYMENT PROCESSING
// ============================================================================

/**
 * Process payment
 */
function bntm_ajax_bm_process_payment() {
    global $wpdb;
    
    $booking_id = intval($_POST['booking_id']);
    $amount = floatval($_POST['amount']);
    $gateway = sanitize_text_field($_POST['gateway']);
    
    check_ajax_referer('bm_payment_action', 'nonce');
    
    $bookings_table = $wpdb->prefix . 'bm_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE id = %d", $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    if ($booking->payment_status === 'paid') {
        wp_send_json_error(['message' => 'This booking has already been paid']);
    }
    
    if ($gateway === 'paymaya') {
        $result = bm_process_paymaya_payment($booking, $amount);
        
        if ($result['success']) {
            // Update booking with payment info
            $wpdb->update($bookings_table, [
                'payment_method' => 'online',
                'payment_gateway' => 'paymaya',
                'payment_transaction_id' => $result['transaction_id']
            ], ['id' => $booking_id], ['%s', '%s', '%s'], ['%d']);
            
            wp_send_json_success([
                'message' => 'Redirecting to PayMaya...',
                'redirect_url' => $result['redirect_url']
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    } else {
        wp_send_json_error(['message' => 'Invalid payment gateway']);
    }
}

/**
 * Process PayMaya payment
 */
function bm_process_paymaya_payment($booking, $amount) {
    $paymaya_mode = bntm_get_setting('bm_paymaya_mode', 'sandbox');
    $paymaya_public_key = bntm_get_setting('bm_paymaya_public_key', '');
    
    if (empty($paymaya_public_key)) {
        return [
            'success' => false,
            'message' => 'PayMaya is not configured'
        ];
    }
    
    $base_url = $paymaya_mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';
    
    // Create return URLs
    $success_url = home_url('/view-quotation/?booking_id=' . $booking->rand_id . '&payment_success=1&gateway=paymaya');
    $failure_url = home_url('/view-quotation/?booking_id=' . $booking->rand_id . '&payment_failed=1');
    $cancel_url = home_url('/view-quotation/?booking_id=' . $booking->rand_id);
    
    $checkout_data = [
        'totalAmount' => [
            'value' => floatval($amount),
            'currency' => 'PHP'
        ],
        'buyer' => [
            'firstName' => explode(' ', $booking->customer_name)[0],
            'lastName' => explode(' ', $booking->customer_name)[1] ?? '',
            'contact' => [
                'phone' => $booking->customer_phone,
                'email' => $booking->customer_email
            ]
        ],
        'items' => [
            [
                'name' => 'Hotel Booking Deposit - #' . $booking->rand_id,
                'quantity' => 1,
                'amount' => ['value' => floatval($amount)],
                'totalAmount' => ['value' => floatval($amount)]
            ]
        ],
        'redirectUrl' => [
            'success' => $success_url,
            'failure' => $failure_url,
            'cancel' => $cancel_url
        ],
        'requestReferenceNumber' => $booking->rand_id,
        'metadata' => [
            'booking_id' => $booking->id,
            'booking_rand_id' => $booking->rand_id
        ]
    ];
    
    $response = wp_remote_post($base_url, [
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($paymaya_public_key . ':'),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($checkout_data),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => 'Failed to connect to PayMaya: ' . $response->get_error_message()
        ];
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $response_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($status_code !== 200 && $status_code !== 201) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return [
            'success' => false,
            'message' => 'PayMaya error: ' . $error_message
        ];
    }
    
    if (!isset($response_data['checkoutId'])) {
        return [
            'success' => false,
            'message' => 'Invalid response from PayMaya'
        ];
    }
    
    $checkout_url = isset($response_data['redirectUrl']) 
        ? $response_data['redirectUrl']
        : ($paymaya_mode === 'sandbox'
            ? 'https://pg-sandbox.paymaya.com/checkout?id=' . $response_data['checkoutId']
            : 'https://pg.maya.ph/checkout?id=' . $response_data['checkoutId']);
    
    return [
        'success' => true,
        'redirect_url' => $checkout_url,
        'transaction_id' => $response_data['checkoutId']
    ];
}

/**
 * Handle payment success redirect
 */
function bm_handle_payment_success() {
    if (!isset($_GET['payment_success']) || $_GET['payment_success'] != 1) {
        return;
    }
    
    if (!isset($_GET['booking_id']) || !isset($_GET['gateway'])) {
        return;
    }
    
    $booking_rand_id = sanitize_text_field($_GET['booking_id']);
    $gateway = sanitize_text_field($_GET['gateway']);
    
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bm_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE rand_id = %s", $booking_rand_id
    ));
    
    if (!$booking) {
        return;
    }
    
    // Check if already paid
    if ($booking->payment_status === 'paid') {
        return;
    }
    
    // Update payment status
    $wpdb->update($bookings_table, [
        'payment_status' => 'paid',
        'booking_status' => 'confirmed',
        'confirmed_at' => current_time('mysql')
    ], ['id' => $booking->id], ['%s', '%s', '%s'], ['%d']);
    
    // Send confirmation email
    bm_send_confirmation_email($booking);
    
    error_log("Booking #{$booking->rand_id} payment completed via {$gateway}");
}

/**
 * Handle PayMaya webhook
 * NEWLY ADDED - Secure payment verification
 */
function bm_handle_paymaya_webhook($request) {
    $payload = $request->get_json_params();
    
    error_log('PayMaya Webhook Received: ' . print_r($payload, true));
    
    // Extract payment data
    if (isset($payload['id']) && isset($payload['status'])) {
        $checkout_id = sanitize_text_field($payload['id']);
        $status = sanitize_text_field($payload['status']);
        
        // Find booking by transaction ID
        global $wpdb;
        $table = $wpdb->prefix . 'bm_bookings';
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE payment_transaction_id = %s",
            $checkout_id
        ));
        
        if ($booking && $status === 'PAYMENT_SUCCESS') {
            // Update booking
            $wpdb->update($table, [
                'payment_status' => 'paid',
                'booking_status' => 'confirmed',
                'confirmed_at' => current_time('mysql')
            ], ['id' => $booking->id], ['%s', '%s', '%s'], ['%d']);
            
            // Send confirmation email
            bm_send_confirmation_email($booking);
            
            error_log("Booking #{$booking->rand_id} confirmed via webhook");
            
            return new WP_REST_Response(['status' => 'success'], 200);
        }
    }
    
    return new WP_REST_Response(['status' => 'ignored'], 200);
}

// ============================================================================
// EMAIL FUNCTIONS (FIXED WITH HEREDOC SYNTAX)
// ============================================================================

/**
 * Send quotation email
 * FIXED: Using heredoc syntax to avoid parse errors
 */
function bm_send_quotation_email($booking) {
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel Booking System');
    $hotel_email = bntm_get_setting('bm_hotel_email', get_option('admin_email'));
    
    $quotation_url = home_url('/view-quotation/?booking_id=' . $booking->rand_id);
    
    $check_in_formatted = date('F d, Y', strtotime($booking->check_in_date));
    $check_out_formatted = date('F d, Y', strtotime($booking->check_out_date));
    $total_formatted = number_format($booking->total_amount, 2);
    $deposit_formatted = number_format($booking->deposit_amount, 2);
    $current_year = date('Y');
    
    $subject = "Your Booking Quotation - {$hotel_name}";
    
    $message = <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3b82f6; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .button { display: inline-block; padding: 12px 30px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>{$hotel_name}</h1>
            <p>Booking Quotation</p>
        </div>
        <div class='content'>
            <h2>Hello {$booking->customer_name},</h2>
            <p>Thank you for your booking request! We're pleased to provide you with a quotation for your stay.</p>
            
            <p><strong>Booking Details:</strong></p>
            <ul>
                <li>Booking ID: #{$booking->rand_id}</li>
                <li>Check-In: {$check_in_formatted}</li>
                <li>Check-Out: {$check_out_formatted}</li>
                <li>Total Amount: ₱{$total_formatted}</li>
                <li>Deposit Required: ₱{$deposit_formatted}</li>
            </ul>
            
            <p>Please review your complete quotation and proceed with payment to confirm your booking:</p>
            
            <a href='{$quotation_url}' class='button'>View Full Quotation</a>
            
            <p>If you have any questions, please don't hesitate to contact us.</p>
        </div>
        <div class='footer'>
            <p>&copy; {$current_year} {$hotel_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $hotel_name . ' <' . $hotel_email . '>'
    ];

    return wp_mail($booking->customer_email, $subject, $message, $headers);
}

/**
 * Send confirmation email
 * FIXED: Using heredoc syntax
 */
function bm_send_confirmation_email($booking) {
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel Booking System');
    $hotel_email = bntm_get_setting('bm_hotel_email', get_option('admin_email'));
    $hotel_phone = bntm_get_setting('bm_hotel_phone', '');
    
    $check_in_formatted = date('F d, Y - 2:00 PM', strtotime($booking->check_in_date));
    $check_out_formatted = date('F d, Y - 12:00 PM', strtotime($booking->check_out_date));
    $children_text = ($booking->num_children > 0) ? ", {$booking->num_children} Children" : "";
    $current_year = date('Y');
    
    $subject = "Booking Confirmed - {$hotel_name}";
    
    $message = <<<HTML
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .success-box { background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
        .footer { padding: 20px; text-align: center; color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✓ Booking Confirmed!</h1>
        </div>
        <div class='content'>
            <div class='success-box'>
                <h2>Thank you, {$booking->customer_name}!</h2>
                <p>Your booking has been confirmed. We look forward to welcoming you!</p>
            </div>
            
            <p><strong>Booking Confirmation:</strong></p>
            <ul>
                <li>Booking ID: #{$booking->rand_id}</li>
                <li>Check-In: {$check_in_formatted}</li>
                <li>Check-Out: {$check_out_formatted}</li>
                <li>Guests: {$booking->num_adults} Adults{$children_text}</li>
            </ul>
            
            <p><strong>Important Information:</strong></p>
            <ul>
                <li>Please bring a valid ID for check-in</li>
                <li>Early check-in may be available upon request</li>
                <li>Late check-out is subject to availability</li>
            </ul>
            
            <p>If you need to make any changes or have questions, please contact us at {$hotel_phone} or reply to this email.</p>
            
            <p>We can't wait to host you!</p>
        </div>
        <div class='footer'>
            <p>&copy; {$current_year} {$hotel_name}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $hotel_name . ' <' . $hotel_email . '>'
    ];

    return wp_mail($booking->customer_email, $subject, $message, $headers);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get booking statistics
 */
function bm_get_stats($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_bookings';
    
    $stats = [
        'total_bookings' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE business_id = %d", $business_id
        )),
        'pending_bookings' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND booking_status = 'pending'", $business_id
        )),
        'confirmed_bookings' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND booking_status IN ('confirmed', 'checked_in')", $business_id
        )),
        'total_revenue' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_amount) FROM {$table} WHERE business_id = %d AND payment_status = 'paid'", $business_id
        )) ?: 0
    ];
    
    return $stats;
}

/**
 * Render recent bookings
 */
function bm_render_recent_bookings($business_id, $limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_bookings';
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id = %d ORDER BY created_at DESC LIMIT %d",
        $business_id, $limit
    ));
    
    if (empty($bookings)) {
        return '<p style="color: #6b7280;">No recent bookings</p>';
    }
    
    ob_start();
    ?>
    <table class="bntm-table">
        <thead>
            <tr>
                <th>Booking ID</th>
                <th>Customer</th>
                <th>Check-In</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $booking): ?>
            <tr>
                <td><strong>#<?php echo esc_html($booking->rand_id); ?></strong></td>
                <td><?php echo esc_html($booking->customer_name); ?></td>
                <td><?php echo date('M d, Y', strtotime($booking->check_in_date)); ?></td>
                <td>₱<?php echo number_format($booking->total_amount, 2); ?></td>
                <td>
                    <span class="bm-badge bm-badge-<?php echo $booking->payment_status; ?>">
                        <?php echo ucfirst($booking->booking_status); ?>
                    </span>
                </td>
                <td><?php echo date('M d, Y', strtotime($booking->created_at)); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

/**
 * Generate random ID
 */
if (!function_exists('bntm_rand_id')) {
    function bntm_rand_id() {
        return 'BM' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
    }
}

/**
 * Get setting value
 */
if (!function_exists('bntm_get_setting')) {
    function bntm_get_setting($key, $default = '') {
        return get_option($key, $default);
    }
}

/**
 * Set setting value
 */
if (!function_exists('bntm_set_setting')) {
    function bntm_set_setting($key, $value) {
        return update_option($key, $value);
    }
}

/**
 * Universal container wrapper
 */
if (!function_exists('bntm_universal_container')) {
    function bntm_universal_container($title, $content) {
        ob_start();
        ?>
        <div class="bntm-universal-container">
            <div class="bntm-container-header">
                <h1><?php echo esc_html($title); ?></h1>
            </div>
            <div class="bntm-container-content">
                <?php echo $content; ?>
            </div>
        </div>
        <style>
        .bntm-universal-container {
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .bntm-container-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
        }
        .bntm-container-header h1 {
            margin: 0;
            font-size: 24px;
            color: #111827;
        }
        .bntm-container-content {
            padding: 30px;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}

// END OF FILE
// End of Booking Management Module