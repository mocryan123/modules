<?php
/**
 * Module Name: Booking Management
 * Module Slug: bm
 * Description: Hotel booking system with separated yacht/car rentals, individual quotation generation, and PayMaya integration
 * Version: 2.0.0 - SEPARATED FORMS
 * Author: Your Name
 * Icon: 🏨
 * 
 * CHANGELOG v2.0.0:
 * - Separated Hotel, Yacht, and Car rental into individual booking forms
 * - Created separate quotation pages for each booking type
 * - Removed Quick Book functionality
 * - Simplified booking flow
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
        'Hotel Booking Form' => '[bm_hotel_form]',
        'Yacht Booking Form' => '[bm_yacht_form]',
        'Car Booking Form' => '[bm_car_form]',
        'Hotel Quotation' => '[bm_hotel_quotation]',
        'Yacht Quotation' => '[bm_yacht_quotation]',
        'Car Quotation' => '[bm_car_quotation]',
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
        'bm_hotel_bookings' => "CREATE TABLE {$prefix}bm_hotel_bookings (
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
        
        'bm_hotel_rooms' => "CREATE TABLE {$prefix}bm_hotel_rooms (
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
        
        'bm_yacht_bookings' => "CREATE TABLE {$prefix}bm_yacht_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_address TEXT,
            yacht_type VARCHAR(100) NOT NULL,
            yacht_name VARCHAR(255) NOT NULL,
            rental_date DATE NOT NULL,
            rental_duration INT NOT NULL,
            duration_unit VARCHAR(20) NOT NULL DEFAULT 'hours',
            num_guests INT NOT NULL DEFAULT 1,
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
            INDEX idx_date (rental_date),
            INDEX idx_status (booking_status)
        ) {$charset};",
        
        'bm_car_bookings' => "CREATE TABLE {$prefix}bm_car_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            customer_address TEXT,
            car_type VARCHAR(100) NOT NULL,
            car_name VARCHAR(255) NOT NULL,
            pickup_date DATE NOT NULL,
            return_date DATE NOT NULL,
            num_days INT NOT NULL,
            with_driver TINYINT(1) DEFAULT 0,
            price_per_day DECIMAL(10,2) NOT NULL,
            driver_fee_per_day DECIMAL(10,2) NOT NULL DEFAULT 0,
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
            INDEX idx_dates (pickup_date, return_date),
            INDEX idx_status (booking_status)
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
        'bm_hotel_form' => 'bntm_shortcode_bm_hotel_form',
        'bm_yacht_form' => 'bntm_shortcode_bm_yacht_form',
        'bm_car_form' => 'bntm_shortcode_bm_car_form',
        'bm_hotel_quotation' => 'bntm_shortcode_bm_hotel_quotation',
        'bm_yacht_quotation' => 'bntm_shortcode_bm_yacht_quotation',
        'bm_car_quotation' => 'bntm_shortcode_bm_car_quotation',
    ];
}

// Register shortcodes with WordPress
add_shortcode('bm_dashboard', 'bntm_shortcode_bm_dashboard');
add_shortcode('bm_hotel_form', 'bntm_shortcode_bm_hotel_form');
add_shortcode('bm_yacht_form', 'bntm_shortcode_bm_yacht_form');
add_shortcode('bm_car_form', 'bntm_shortcode_bm_car_form');
add_shortcode('bm_hotel_quotation', 'bntm_shortcode_bm_hotel_quotation');
add_shortcode('bm_yacht_quotation', 'bntm_shortcode_bm_yacht_quotation');
add_shortcode('bm_car_quotation', 'bntm_shortcode_bm_car_quotation');

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

// Booking status updates
add_action('wp_ajax_bm_update_hotel_status', 'bntm_ajax_bm_update_hotel_status');
add_action('wp_ajax_bm_update_yacht_status', 'bntm_ajax_bm_update_yacht_status');
add_action('wp_ajax_bm_update_car_status', 'bntm_ajax_bm_update_car_status');

// Phone confirmations
add_action('wp_ajax_bm_confirm_hotel_phone', 'bntm_ajax_bm_confirm_hotel_phone');
add_action('wp_ajax_bm_confirm_yacht_phone', 'bntm_ajax_bm_confirm_yacht_phone');
add_action('wp_ajax_bm_confirm_car_phone', 'bntm_ajax_bm_confirm_car_phone');

// Resend quotations
add_action('wp_ajax_bm_resend_hotel_quotation', 'bntm_ajax_bm_resend_hotel_quotation');
add_action('wp_ajax_bm_resend_yacht_quotation', 'bntm_ajax_bm_resend_yacht_quotation');
add_action('wp_ajax_bm_resend_car_quotation', 'bntm_ajax_bm_resend_car_quotation');

// Booking submissions (both logged in and public)
add_action('wp_ajax_bm_submit_hotel_booking', 'bntm_ajax_bm_submit_hotel_booking');
add_action('wp_ajax_nopriv_bm_submit_hotel_booking', 'bntm_ajax_bm_submit_hotel_booking');
add_action('wp_ajax_bm_submit_yacht_booking', 'bntm_ajax_bm_submit_yacht_booking');
add_action('wp_ajax_nopriv_bm_submit_yacht_booking', 'bntm_ajax_bm_submit_yacht_booking');
add_action('wp_ajax_bm_submit_car_booking', 'bntm_ajax_bm_submit_car_booking');
add_action('wp_ajax_nopriv_bm_submit_car_booking', 'bntm_ajax_bm_submit_car_booking');

// Payment processing
add_action('wp_ajax_bm_process_hotel_payment', 'bntm_ajax_bm_process_hotel_payment');
add_action('wp_ajax_nopriv_bm_process_hotel_payment', 'bntm_ajax_bm_process_hotel_payment');
add_action('wp_ajax_bm_process_yacht_payment', 'bntm_ajax_bm_process_yacht_payment');
add_action('wp_ajax_nopriv_bm_process_yacht_payment', 'bntm_ajax_bm_process_yacht_payment');
add_action('wp_ajax_bm_process_car_payment', 'bntm_ajax_bm_process_car_payment');
add_action('wp_ajax_nopriv_bm_process_car_payment', 'bntm_ajax_bm_process_car_payment');

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
            <a href="?tab=hotel_bookings" class="bntm-tab <?php echo $active_tab === 'hotel_bookings' ? 'active' : ''; ?>">
                Hotel Bookings
            </a>
            <a href="?tab=yacht_bookings" class="bntm-tab <?php echo $active_tab === 'yacht_bookings' ? 'active' : ''; ?>">
                Yacht Bookings
            </a>
            <a href="?tab=car_bookings" class="bntm-tab <?php echo $active_tab === 'car_bookings' ? 'active' : ''; ?>">
                Car Bookings
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
            <?php elseif ($active_tab === 'hotel_bookings'): ?>
                <?php echo bm_hotel_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'yacht_bookings'): ?>
                <?php echo bm_yacht_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'car_bookings'): ?>
                <?php echo bm_car_bookings_tab($business_id); ?>
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
    
    <style>
    .bntm-bm-container {
        max-width: 1400px;
        margin: 20px auto;
    }
    .bntm-tabs {
        display: flex;
        gap: 5px;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }
    .bntm-tab {
        padding: 12px 20px;
        background: #f3f4f6;
        border: none;
        cursor: pointer;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        border-radius: 6px 6px 0 0;
        transition: all 0.2s;
    }
    .bntm-tab:hover {
        background: #e5e7eb;
    }
    .bntm-tab.active {
        background: #3b82f6;
        color: white;
    }
    </style>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Booking Management Dashboard', $content);
}

// ============================================================================
// DASHBOARD TAB FUNCTIONS
// ============================================================================

/**
 * Overview Tab with Combined Statistics
 */
function bm_overview_tab($business_id) {
    global $wpdb;
    
    // Get statistics for all booking types
    $hotel_table = $wpdb->prefix . 'bm_hotel_bookings';
    $yacht_table = $wpdb->prefix . 'bm_yacht_bookings';
    $car_table = $wpdb->prefix . 'bm_car_bookings';
    
    $hotel_stats = [
        'total' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$hotel_table} WHERE business_id = %d", $business_id)),
        'pending' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$hotel_table} WHERE business_id = %d AND booking_status = 'pending'", $business_id)),
        'confirmed' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$hotel_table} WHERE business_id = %d AND booking_status IN ('confirmed', 'checked_in')", $business_id)),
        'revenue' => $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$hotel_table} WHERE business_id = %d AND payment_status = 'paid'", $business_id)) ?: 0
    ];
    
    $yacht_stats = [
        'total' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$yacht_table} WHERE business_id = %d", $business_id)),
        'pending' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$yacht_table} WHERE business_id = %d AND booking_status = 'pending'", $business_id)),
        'confirmed' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$yacht_table} WHERE business_id = %d AND booking_status = 'confirmed'", $business_id)),
        'revenue' => $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$yacht_table} WHERE business_id = %d AND payment_status = 'paid'", $business_id)) ?: 0
    ];
    
    $car_stats = [
        'total' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$car_table} WHERE business_id = %d", $business_id)),
        'pending' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$car_table} WHERE business_id = %d AND booking_status = 'pending'", $business_id)),
        'confirmed' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$car_table} WHERE business_id = %d AND booking_status = 'confirmed'", $business_id)),
        'revenue' => $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$car_table} WHERE business_id = %d AND payment_status = 'paid'", $business_id)) ?: 0
    ];
    
    $total_bookings = $hotel_stats['total'] + $yacht_stats['total'] + $car_stats['total'];
    $total_pending = $hotel_stats['pending'] + $yacht_stats['pending'] + $car_stats['pending'];
    $total_confirmed = $hotel_stats['confirmed'] + $yacht_stats['confirmed'] + $car_stats['confirmed'];
    $total_revenue = $hotel_stats['revenue'] + $yacht_stats['revenue'] + $car_stats['revenue'];
    
    ob_start();
    ?>
    <div class="bm-overview-grid">
        <!-- Overall Statistics -->
        <div class="bm-stat-section">
            <h3>Overall Statistics</h3>
            <div class="bm-stat-cards">
                <div class="bm-stat-card">
                    <div class="bm-stat-icon">📊</div>
                    <h4>Total Bookings</h4>
                    <p class="bm-stat-number"><?php echo $total_bookings; ?></p>
                </div>
                <div class="bm-stat-card pending">
                    <div class="bm-stat-icon">⏳</div>
                    <h4>Pending</h4>
                    <p class="bm-stat-number"><?php echo $total_pending; ?></p>
                </div>
                <div class="bm-stat-card confirmed">
                    <div class="bm-stat-icon">✓</div>
                    <h4>Confirmed</h4>
                    <p class="bm-stat-number"><?php echo $total_confirmed; ?></p>
                </div>
                <div class="bm-stat-card revenue">
                    <div class="bm-stat-icon">₱</div>
                    <h4>Total Revenue</h4>
                    <p class="bm-stat-number">₱<?php echo number_format($total_revenue, 2); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Hotel Bookings -->
        <div class="bm-stat-section">
            <h3>🏨 Hotel Bookings</h3>
            <div class="bm-stat-cards">
                <div class="bm-stat-card small">
                    <h5>Total</h5>
                    <p><?php echo $hotel_stats['total']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Pending</h5>
                    <p><?php echo $hotel_stats['pending']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Confirmed</h5>
                    <p><?php echo $hotel_stats['confirmed']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Revenue</h5>
                    <p>₱<?php echo number_format($hotel_stats['revenue'], 2); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Yacht Rentals -->
        <div class="bm-stat-section">
            <h3>⛵ Yacht Rentals</h3>
            <div class="bm-stat-cards">
                <div class="bm-stat-card small">
                    <h5>Total</h5>
                    <p><?php echo $yacht_stats['total']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Pending</h5>
                    <p><?php echo $yacht_stats['pending']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Confirmed</h5>
                    <p><?php echo $yacht_stats['confirmed']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Revenue</h5>
                    <p>₱<?php echo number_format($yacht_stats['revenue'], 2); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Car Rentals -->
        <div class="bm-stat-section">
            <h3>🚗 Car Rentals</h3>
            <div class="bm-stat-cards">
                <div class="bm-stat-card small">
                    <h5>Total</h5>
                    <p><?php echo $car_stats['total']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Pending</h5>
                    <p><?php echo $car_stats['pending']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Confirmed</h5>
                    <p><?php echo $car_stats['confirmed']; ?></p>
                </div>
                <div class="bm-stat-card small">
                    <h5>Revenue</h5>
                    <p>₱<?php echo number_format($car_stats['revenue'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bm-quick-links">
        <h3>Quick Links</h3>
        <div class="bm-link-grid">
            <a href="?tab=hotel_bookings" class="bm-quick-link hotel">
                <span class="bm-link-icon">🏨</span>
                <span class="bm-link-text">Manage Hotel Bookings</span>
            </a>
            <a href="?tab=yacht_bookings" class="bm-quick-link yacht">
                <span class="bm-link-icon">⛵</span>
                <span class="bm-link-text">Manage Yacht Rentals</span> 
            </a>
            <a href="?tab=car_bookings" class="bm-quick-link car">
                <span class="bm-link-icon">🚗</span>
                <span class="bm-link-text">Manage Car Rentals</span>
            </a>
            <a href="?tab=rooms" class="bm-quick-link rooms">
                <span class="bm-link-icon">🛏️</span>
                <span class="bm-link-text">Manage Room Types</span>
            </a>
            <a href="?tab=yachts" class="bm-quick-link yachts">
                <span class="bm-link-icon">🛥️</span>
                <span class="bm-link-text">Manage Yacht Fleet</span>

            </a>
            <a href="?tab=cars" class="bm-quick-link cars">
                <span class="bm-link-icon">🚙</span>
                <span class="bm-link-text">Manage Car Fleet</span>
            </a>
            <a href="?tab=settings" class="bm-quick-link settings">
                <span class="bm-link-icon">⚙️</span>
                <span class="bm-link-text">Settings</span>
            </a>
        </div>
    </div>

        /**
 * BOOKING MANAGEMENT - PART 2
 * Customer Booking Forms & Quotation Pages
 * 
 * This continues from Part 1 and includes:
 * - Separate Hotel, Yacht, and Car booking forms
 * - Individual quotation pages for each booking type
 * - AJAX handlers for each booking type
 */

// ============================================================================
// HOTEL BOOKING FORM SHORTCODE
// ============================================================================

function bntm_shortcode_bm_hotel_form() {
    global $wpdb;
    $rooms_table = $wpdb->prefix . 'bm_room_types';
    $business_id = get_option('bntm_primary_business_id', 1);
    
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$rooms_table} WHERE business_id = %d AND is_active = 1 ORDER BY room_type ASC",
        $business_id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel Booking System');
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <div class="bm-booking-container">
        <div class="bm-booking-header">
            <h1>🏨 Hotel Booking</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
        </div>
        
        <form id="hotel-booking-form" class="bm-booking-form">
            <!-- Customer Information -->
            <div class="bm-form-section">
                <h2>Customer Information</h2>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="customer_name" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                </div>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Address</label>
                        <input type="text" name="customer_address">
                    </div>
                </div>
            </div>
            
            <!-- Booking Details -->
            <div class="bm-form-section">
                <h2>Booking Details</h2>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Check-In Date *</label>
                        <input type="date" name="check_in_date" id="check-in" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Check-Out Date *</label>
                        <input type="date" name="check_out_date" id="check-out" required>
                    </div>
                </div>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Adults *</label>
                        <input type="number" name="num_adults" min="1" value="1" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Children</label>
                        <input type="number" name="num_children" min="0" value="0">
                    </div>
                </div>
            </div>
            
            <!-- Room Selection -->
            <div class="bm-form-section">
                <h2>Room Selection</h2>
                <div id="rooms-container">
                    <div class="bm-room-item">
                        <div class="bm-form-row">
                            <div class="bm-form-group">
                                <label>Room Type *</label>
                                <select name="rooms[0][room_id]" class="room-select" data-index="0" required>
                                    <option value="">Select Room</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room->id; ?>" 
                                                data-price="<?php echo $room->price_per_night; ?>"
                                                data-name="<?php echo esc_attr($room->room_type); ?>">
                                            <?php echo esc_html($room->room_type); ?> - 
                                            ₱<?php echo number_format($room->price_per_night, 2); ?>/night
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="bm-form-group">
                                <label>Quantity *</label>
                                <input type="number" name="rooms[0][quantity]" class="room-qty" 
                                       data-index="0" min="1" value="1" required>
                            </div>
                            <div class="bm-form-group">
                                <label>Subtotal</label>
                                <input type="text" class="room-subtotal" data-index="0" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" id="add-room" class="bm-btn-secondary">+ Add Room</button>
            </div>
            
            <!-- Special Requests -->
            <div class="bm-form-section">
                <h2>Special Requests</h2>
                <textarea name="special_requests" rows="4" placeholder="Any special requirements..."></textarea>
            </div>
            
            <!-- Summary -->
            <div class="bm-summary">
                <h3>Booking Summary</h3>
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong id="total-amount">₱0.00</strong>
                </div>
            </div>
            
            <button type="submit" class="bm-btn-primary bm-btn-large">Submit Hotel Booking</button>
            <div id="booking-message"></div>
        </form>
    </div>
    
    <?php echo bm_get_booking_styles(); ?>
    
    <script>
    (function() {
        let roomIndex = 1;
        const form = document.getElementById('hotel-booking-form');
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('check-in').min = today;
        document.getElementById('check-out').min = today;
        
        function calculateNights() {
            const checkIn = document.querySelector('[name="check_in_date"]').value;
            const checkOut = document.querySelector('[name="check_out_date"]').value;
            if (!checkIn || !checkOut) return 0;
            const diff = new Date(checkOut) - new Date(checkIn);
            return Math.ceil(diff / (1000 * 60 * 60 * 24));
        }
        
        function updateTotal() {
            const nights = calculateNights();
            let total = 0;
            document.querySelectorAll('.room-select').forEach(select => {
                if (select.value) {
                    const index = select.dataset.index;
                    const price = parseFloat(select.options[select.selectedIndex].dataset.price);
                    const qty = parseInt(document.querySelector(`.room-qty[data-index="${index}"]`).value) || 1;
                    const subtotal = price * qty * nights;
                    document.querySelector(`.room-subtotal[data-index="${index}"]`).value = '₱' + subtotal.toFixed(2);
                    total += subtotal;
                }
            });
            document.getElementById('total-amount').textContent = '₱' + total.toFixed(2);
        }
        
        document.querySelector('[name="check_in_date"]').addEventListener('change', updateTotal);
        document.querySelector('[name="check_out_date"]').addEventListener('change', updateTotal);
        document.querySelectorAll('.room-select, .room-qty').forEach(el => {
            el.addEventListener('change', updateTotal);
        });
        
        document.getElementById('add-room').addEventListener('click', function() {
            const container = document.getElementById('rooms-container');
            const newRoom = container.querySelector('.bm-room-item').cloneNode(true);
            newRoom.querySelector('.room-select').dataset.index = roomIndex;
            newRoom.querySelector('.room-select').name = `rooms[${roomIndex}][room_id]`;
            newRoom.querySelector('.room-qty').dataset.index = roomIndex;
            newRoom.querySelector('.room-qty').name = `rooms[${roomIndex}][quantity]`;
            newRoom.querySelector('.room-subtotal').dataset.index = roomIndex;
            container.appendChild(newRoom);
            roomIndex++;
        });
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_hotel_booking');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bm-success">' + json.data.message + '</div>';
                    setTimeout(() => window.location.href = json.data.redirect_url, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bm-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Hotel Booking';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// YACHT BOOKING FORM SHORTCODE
// ============================================================================

function bntm_shortcode_bm_yacht_form() {
    global $wpdb;
    $yachts_table = $wpdb->prefix . 'bm_yacht_types';
    $business_id = get_option('bntm_primary_business_id', 1);
    
    $yachts = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$yachts_table} WHERE business_id = %d AND is_active = 1 ORDER BY yacht_name ASC",
        $business_id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Yacht Rental Service');
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <div class="bm-booking-container">
        <div class="bm-booking-header">
            <h1>⛵ Yacht Rental Booking</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
        </div>
        
        <form id="yacht-booking-form" class="bm-booking-form">
            <!-- Customer Information -->
            <div class="bm-form-section">
                <h2>Customer Information</h2>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="customer_name" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                </div>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Address</label>
                        <input type="text" name="customer_address">
                    </div>
                </div>
            </div>
            
            <!-- Yacht Selection -->
            <div class="bm-form-section">
                <h2>Yacht Selection</h2>
                <div class="bm-form-group">
                    <label>Select Yacht *</label>
                    <select name="yacht_id" id="yacht-select" required>
                        <option value="">Choose a yacht</option>
                        <?php foreach ($yachts as $yacht): ?>
                            <option value="<?php echo $yacht->id; ?>"
                                    data-price-hour="<?php echo $yacht->price_per_hour; ?>"
                                    data-price-day="<?php echo $yacht->price_per_day; ?>"
                                    data-name="<?php echo esc_attr($yacht->yacht_name); ?>">
                                <?php echo esc_html($yacht->yacht_name); ?> - 
                                ₱<?php echo number_format($yacht->price_per_hour, 2); ?>/hr or 
                                ₱<?php echo number_format($yacht->price_per_day, 2); ?>/day
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Rental Details -->
            <div class="bm-form-section">
                <h2>Rental Details</h2>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Rental Date *</label>
                        <input type="date" name="rental_date" id="rental-date" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Number of Guests *</label>
                        <input type="number" name="num_guests" min="1" value="1" required>
                    </div>
                </div>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Duration *</label>
                        <input type="number" name="duration" id="duration" min="1" value="4" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Unit *</label>
                        <select name="duration_unit" id="duration-unit" required>
                            <option value="hours">Hours</option>
                            <option value="days">Days</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="bm-form-section">
                <h2>Special Requests</h2>
                <textarea name="special_requests" rows="4" placeholder="Any special requirements..."></textarea>
            </div>
            
            <!-- Summary -->
            <div class="bm-summary">
                <h3>Rental Summary</h3>
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong id="total-amount">₱0.00</strong>
                </div>
            </div>
            
            <button type="submit" class="bm-btn-primary bm-btn-large">Submit Yacht Booking</button>
            <div id="booking-message"></div>
        </form>
    </div>
    
    <?php echo bm_get_booking_styles(); ?>
    
    <script>
    (function() {
        const form = document.getElementById('yacht-booking-form');
        const yachtSelect = document.getElementById('yacht-select');
        const durationInput = document.getElementById('duration');
        const unitSelect = document.getElementById('duration-unit');
        const totalEl = document.getElementById('total-amount');
        
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('rental-date').min = today;
        
        function updateTotal() {
            if (!yachtSelect.value) {
                totalEl.textContent = '₱0.00';
                return;
            }
            const option = yachtSelect.options[yachtSelect.selectedIndex];
            const priceHour = parseFloat(option.dataset.priceHour);
            const priceDay = parseFloat(option.dataset.priceDay);
            const duration = parseInt(durationInput.value) || 1;
            const unit = unitSelect.value;
            
            const price = unit === 'hours' ? priceHour : priceDay;
            const total = price * duration;
            totalEl.textContent = '₱' + total.toFixed(2);
        }
        
        yachtSelect.addEventListener('change', updateTotal);
        durationInput.addEventListener('input', updateTotal);
        unitSelect.addEventListener('change', updateTotal);
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_yacht_booking');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bm-success">' + json.data.message + '</div>';
                    setTimeout(() => window.location.href = json.data.redirect_url, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bm-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Yacht Booking';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// CAR BOOKING FORM SHORTCODE
// ============================================================================

function bntm_shortcode_bm_car_form() {
    global $wpdb;
    $cars_table = $wpdb->prefix . 'bm_car_types';
    $business_id = get_option('bntm_primary_business_id', 1);
    
    $cars = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$cars_table} WHERE business_id = %d AND is_active = 1 ORDER BY car_name ASC",
        $business_id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Car Rental Service');
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <div class="bm-booking-container">
        <div class="bm-booking-header">
            <h1>🚗 Car Rental Booking</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
        </div>
        
        <form id="car-booking-form" class="bm-booking-form">
            <!-- Customer Information -->
            <div class="bm-form-section">
                <h2>Customer Information</h2>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="customer_name" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Email *</label>
                        <input type="email" name="customer_email" required>
                    </div>
                </div>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Address</label>
                        <input type="text" name="customer_address">
                    </div>
                </div>
            </div>
            
            <!-- Car Selection -->
            <div class="bm-form-section">
                <h2>Car Selection</h2>
                <div class="bm-form-group">
                    <label>Select Car *</label>
                    <select name="car_id" id="car-select" required>
                        <option value="">Choose a car</option>
                        <?php foreach ($cars as $car): ?>
                            <option value="<?php echo $car->id; ?>"
                                    data-price="<?php echo $car->price_per_day; ?>"
                                    data-driver-fee="<?php echo $car->driver_fee; ?>"
                                    data-name="<?php echo esc_attr($car->car_name); ?>">
                                <?php echo esc_html($car->car_name); ?> - 
                                ₱<?php echo number_format($car->price_per_day, 2); ?>/day
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Rental Details -->
            <div class="bm-form-section">
                <h2>Rental Details</h2>
                <div class="bm-form-row">
                    <div class="bm-form-group">
                        <label>Pickup Date *</label>
                        <input type="date" name="pickup_date" id="pickup-date" required>
                    </div>
                    <div class="bm-form-group">
                        <label>Return Date *</label>
                        <input type="date" name="return_date" id="return-date" required>
                    </div>
                </div>
                <div class="bm-form-group">
                    <label>
                        <input type="checkbox" name="with_driver" id="with-driver">
                        Include Driver <span id="driver-fee-text"></span>
                    </label>
                </div>
            </div>
            
            <!-- Special Requests -->
            <div class="bm-form-section">
                <h2>Special Requests</h2>
                <textarea name="special_requests" rows="4" placeholder="Any special requirements..."></textarea>
            </div>
            
            <!-- Summary -->
            <div class="bm-summary">
                <h3>Rental Summary</h3>
                <div class="bm-summary-row">
                    <span>Car Rental:</span>
                    <span id="car-cost">₱0.00</span>
                </div>
                <div class="bm-summary-row" id="driver-cost-row" style="display:none;">
                    <span>Driver Fee:</span>
                    <span id="driver-cost">₱0.00</span>
                </div>
                <div class="bm-summary-row bm-total">
                    <span>Total Amount:</span>
                    <strong id="total-amount">₱0.00</strong>
                </div>
            </div>
            
            <button type="submit" class="bm-btn-primary bm-btn-large">Submit Car Booking</button>
            <div id="booking-message"></div>
        </form>
    </div>
    
    <?php echo bm_get_booking_styles(); ?>
    
    <script>
    (function() {
        const form = document.getElementById('car-booking-form');
        const carSelect = document.getElementById('car-select');
        const pickupDate = document.getElementById('pickup-date');
        const returnDate = document.getElementById('return-date');
        const withDriver = document.getElementById('with-driver');
        
        const today = new Date().toISOString().split('T')[0];
        pickupDate.min = today;
        returnDate.min = today;
        
        function calculateDays() {
            if (!pickupDate.value || !returnDate.value) return 0;
            const diff = new Date(returnDate.value) - new Date(pickupDate.value);
            return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
        }
        
        function updateTotal() {
            if (!carSelect.value) {
                document.getElementById('total-amount').textContent = '₱0.00';
                return;
            }
            
            const option = carSelect.options[carSelect.selectedIndex];
            const pricePerDay = parseFloat(option.dataset.price);
            const driverFee = parseFloat(option.dataset.driverFee);
            const days = calculateDays();
            
            if (days === 0) {
                document.getElementById('total-amount').textContent = '₱0.00';
                return;
            }
            
            const carCost = pricePerDay * days;
            const driverCost = withDriver.checked ? driverFee * days : 0;
            const total = carCost + driverCost;
            
            document.getElementById('car-cost').textContent = '₱' + carCost.toFixed(2);
            document.getElementById('driver-cost').textContent = '₱' + driverCost.toFixed(2);
            document.getElementById('total-amount').textContent = '₱' + total.toFixed(2);
            document.getElementById('driver-cost-row').style.display = withDriver.checked ? 'flex' : 'none';
            
            if (driverFee > 0) {
                document.getElementById('driver-fee-text').textContent = 
                    '(+₱' + driverFee.toFixed(2) + '/day)';
            }
        }
        
        carSelect.addEventListener('change', updateTotal);
        pickupDate.addEventListener('change', function() {
            returnDate.min = this.value;
            updateTotal();
        });
        returnDate.addEventListener('change', updateTotal);
        withDriver.addEventListener('change', updateTotal);
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (calculateDays() === 0) {
                alert('Return date must be after pickup date');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'bm_submit_car_booking');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bm-success">' + json.data.message + '</div>';
                    setTimeout(() => window.location.href = json.data.redirect_url, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = 
                        '<div class="bm-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Car Booking';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// SHARED BOOKING STYLES
// ============================================================================

function bm_get_booking_styles() {
    return <<<STYLES
    <style>
    .bm-booking-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 20px;
    }
    .bm-booking-header {
        text-align: center;
        margin-bottom: 40px;
    }
    .bm-booking-header h1 {
        margin: 0 0 10px 0;
        color: #111827;
    }
    .bm-booking-form {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .bm-form-section {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e5e7eb;
    }
    .bm-form-section:last-of-type {
        border-bottom: none;
    }
    .bm-form-section h2 {
        margin: 0 0 20px 0;
        color: #111827;
        font-size: 18px;
    }
    .bm-form-group {
        margin-bottom: 15px;
    }
    .bm-form-group label {
        display: block;
        margin-bottom: 5px;
        color: #374151;
        font-weight: 500;
    }
    .bm-form-group input,
    .bm-form-group select,
    .bm-form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    .bm-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .bm-room-item {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .bm-summary {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        margin: 30px 0;
    }
    .bm-summary h3 {
        margin: 0 0 15px 0;
        color: #111827;
    }
    .bm-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .bm-summary-row.bm-total {
        border-top: 2px solid #3b82f6;
        border-bottom: none;
        font-size: 18px;
        padding-top: 15px;
        margin-top: 10px;
    }
    .bm-btn-primary,
    .bm-btn-secondary {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0;.2s ease;
    }
    .bm-btn-primary {
        background: #3b82f6;
        color: #fff;
    }
    /**
 * BOOKING MANAGEMENT - PART 3
 * Quotation Pages & AJAX Handlers
 * 
 * Includes:
 * - Hotel, Yacht, and Car quotation pages
 * - AJAX submission handlers
 * - Payment processing
 * - Email functions
 */

// ============================================================================
// HOTEL QUOTATION SHORTCODE
// ============================================================================

function bntm_shortcode_bm_hotel_quotation() {
    if (!isset($_GET['booking_id'])) {
        return '<div class="bm-error">Invalid booking reference.</div>';
    }
    
    global $wpdb;
    $booking_id = sanitize_text_field($_GET['booking_id']);
    $bookings_table = $wpdb->prefix . 'bm_hotel_bookings';
    $rooms_table = $wpdb->prefix . 'bm_hotel_rooms';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE rand_id = %s", $booking_id
    ));
    
    if (!$booking) {
        return '<div class="bm-error">Booking not found.</div>';
    }
    
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$rooms_table} WHERE booking_id = %d", $booking->id
    ));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel');
    $nights = (new DateTime($booking->check_in_date))->diff(new DateTime($booking->check_out_date))->days;
    
    ob_start();
    ?>
    <div class="bm-quotation">
        <div class="bm-quot-header">
            <h1>🏨 Hotel Booking Quotation</h1>
            <p><?php echo esc_html($hotel_name); ?></p>
            <p class="bm-booking-id">Booking #<?php echo esc_html($booking->rand_id); ?></p>
        </div>
        
        <div class="bm-quot-section">
            <h2>Customer Information</h2>
            <div class="bm-info-grid">
                <div><strong>Name:</strong> <?php echo esc_html($booking->customer_name); ?></div>
                <div><strong>Email:</strong> <?php echo esc_html($booking->customer_email); ?></div>
                <div><strong>Phone:</strong> <?php echo esc_html($booking->customer_phone); ?></div>
                <div><strong>Address:</strong> <?php echo esc_html($booking->customer_address ?: 'N/A'); ?></div>
            </div>
        </div>
        
        <div class="bm-quot-section">
            <h2>Booking Details</h2>
            <div class="bm-info-grid">
                <div><strong>Check-In:</strong> <?php echo date('M d, Y', strtotime($booking->check_in_date)); ?></div>
                <div><strong>Check-Out:</strong> <?php echo date('M d, Y', strtotime($booking->check_out_date)); ?></div>
                <div><strong>Nights:</strong> <?php echo $nights; ?></div>
                <div><strong>Guests:</strong> <?php echo $booking->num_adults; ?> Adults<?php echo $booking->num_children > 0 ? ', ' . $booking->num_children . ' Children' : ''; ?></div>
            </div>
        </div>
        
        <div class="bm-quot-section">
            <h2>Room Selection</h2>
            <table class="bm-table">
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
                        <td><?php echo $room->num_rooms; ?></td>
                        <td>₱<?php echo number_format($room->price_per_night, 2); ?></td>
                        <td><?php echo $room->num_nights; ?></td>
                        <td><strong>₱<?php echo number_format($room->subtotal, 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($booking->special_requests): ?>
        <div class="bm-quot-section">
            <h2>Special Requests</h2>
            <p><?php echo nl2br(esc_html($booking->special_requests)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bm-quot-section bm-quot-summary">
            <h2>Payment Summary</h2>
            <div class="bm-summary-grid">
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row">
                    <span>Deposit Required:</span>
                    <strong>₱<?php echo number_format($booking->deposit_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row bm-total">
                    <span>Status:</span>
                    <strong class="bm-status-<?php echo $booking->booking_status; ?>">
                        <?php echo ucfirst($booking->booking_status); ?>
                    </strong>
                </div>
            </div>
        </div>
        
        <?php if ($booking->payment_status !== 'paid'): ?>
        <div class="bm-quot-actions">
            <button onclick="window.print()" class="bm-btn-secondary">Print Quotation</button>
            <?php if (bntm_get_setting('bm_enable_paymaya', '0') == '1'): ?>
            <button class="bm-btn-primary" id="pay-now-btn" 
                    data-booking-id="<?php echo $booking->id; ?>"
                    data-amount="<?php echo $booking->deposit_amount; ?>">
                Pay Deposit (₱<?php echo number_format($booking->deposit_amount, 2); ?>)
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bm-quot-section bm-paid">
            <h2>✓ Payment Received</h2>
            <p>Your booking is confirmed. Thank you!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php echo bm_get_quotation_styles(); ?>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const payBtn = document.getElementById('pay-now-btn');
    if (payBtn) {
        payBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'bm_process_hotel_payment');
            formData.append('booking_id', this.dataset.bookingId);
            formData.append('amount', this.dataset.amount);
            
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
                    this.textContent = 'Pay Deposit';
                }
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// YACHT QUOTATION SHORTCODE
// ============================================================================

function bntm_shortcode_bm_yacht_quotation() {
    if (!isset($_GET['booking_id'])) {
        return '<div class="bm-error">Invalid booking reference.</div>';
    }
    
    global $wpdb;
    $booking_id = sanitize_text_field($_GET['booking_id']);
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE rand_id = %s", $booking_id
    ));
    
    if (!$booking) {
        return '<div class="bm-error">Booking not found.</div>';
    }
    
    $company_name = bntm_get_setting('bm_hotel_name', 'Yacht Rentals');
    
    ob_start();
    ?>
    <div class="bm-quotation">
        <div class="bm-quot-header">
            <h1>⛵ Yacht Rental Quotation</h1>
            <p><?php echo esc_html($company_name); ?></p>
            <p class="bm-booking-id">Booking #<?php echo esc_html($booking->rand_id); ?></p>
        </div>
        
        <div class="bm-quot-section">
            <h2>Customer Information</h2>
            <div class="bm-info-grid">
                <div><strong>Name:</strong> <?php echo esc_html($booking->customer_name); ?></div>
                <div><strong>Email:</strong> <?php echo esc_html($booking->customer_email); ?></div>
                <div><strong>Phone:</strong> <?php echo esc_html($booking->customer_phone); ?></div>
                <div><strong>Address:</strong> <?php echo esc_html($booking->customer_address ?: 'N/A'); ?></div>
            </div>
        </div>
        
        <div class="bm-quot-section">
            <h2>Rental Details</h2>
            <table class="bm-table">
                <thead>
                    <tr>
                        <th>Yacht</th>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>Guests</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html($booking->yacht_name); ?></td>
                        <td><?php echo date('M d, Y', strtotime($booking->rental_date)); ?></td>
                        <td><?php echo $booking->rental_duration; ?> <?php echo $booking->duration_unit; ?></td>
                        <td><?php echo $booking->num_guests; ?> guests</td>
                        <td><strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <?php if ($booking->special_requests): ?>
        <div class="bm-quot-section">
            <h2>Special Requests</h2>
            <p><?php echo nl2br(esc_html($booking->special_requests)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bm-quot-section bm-quot-summary">
            <h2>Payment Summary</h2>
            <div class="bm-summary-grid">
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row">
                    <span>Deposit Required:</span>
                    <strong>₱<?php echo number_format($booking->deposit_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row bm-total">
                    <span>Status:</span>
                    <strong class="bm-status-<?php echo $booking->booking_status; ?>">
                        <?php echo ucfirst($booking->booking_status); ?>
                    </strong>
                </div>
            </div>
        </div>
        
        <?php if ($booking->payment_status !== 'paid'): ?>
        <div class="bm-quot-actions">
            <button onclick="window.print()" class="bm-btn-secondary">Print Quotation</button>
            <?php if (bntm_get_setting('bm_enable_paymaya', '0') == '1'): ?>
            <button class="bm-btn-primary" id="pay-now-btn" 
                    data-booking-id="<?php echo $booking->id; ?>"
                    data-amount="<?php echo $booking->deposit_amount; ?>">
                Pay Deposit (₱<?php echo number_format($booking->deposit_amount, 2); ?>)
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bm-quot-section bm-paid">
            <h2>✓ Payment Received</h2>
            <p>Your yacht rental is confirmed. Thank you!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php echo bm_get_quotation_styles(); ?>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const payBtn = document.getElementById('pay-now-btn');
    if (payBtn) {
        payBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'bm_process_yacht_payment');
            formData.append('booking_id', this.dataset.bookingId);
            formData.append('amount', this.dataset.amount);
            
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
                    this.textContent = 'Pay Deposit';
                }
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// CAR QUOTATION SHORTCODE
// ============================================================================

function bntm_shortcode_bm_car_quotation() {
    if (!isset($_GET['booking_id'])) {
        return '<div class="bm-error">Invalid booking reference.</div>';
    }
    
    global $wpdb;
    $booking_id = sanitize_text_field($_GET['booking_id']);
    $table = $wpdb->prefix . 'bm_car_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE rand_id = %s", $booking_id
    ));
    
    if (!$booking) {
        return '<div class="bm-error">Booking not found.</div>';
    }
    
    $company_name = bntm_get_setting('bm_hotel_name', 'Car Rentals');
    
    ob_start();
    ?>
    <div class="bm-quotation">
        <div class="bm-quot-header">
            <h1>🚗 Car Rental Quotation</h1>
            <p><?php echo esc_html($company_name); ?></p>
            <p class="bm-booking-id">Booking #<?php echo esc_html($booking->rand_id); ?></p>
        </div>
        
        <div class="bm-quot-section">
            <h2>Customer Information</h2>
            <div class="bm-info-grid">
                <div><strong>Name:</strong> <?php echo esc_html($booking->customer_name); ?></div>
                <div><strong>Email:</strong> <?php echo esc_html($booking->customer_email); ?></div>
                <div><strong>Phone:</strong> <?php echo esc_html($booking->customer_phone); ?></div>
                <div><strong>Address:</strong> <?php echo esc_html($booking->customer_address ?: 'N/A'); ?></div>
            </div>
        </div>
        
        <div class="bm-quot-section">
            <h2>Rental Details</h2>
            <table class="bm-table">
                <thead>
                    <tr>
                        <th>Car</th>
                        <th>Pickup Date</th>
                        <th>Return Date</th>
                        <th>Days</th>
                        <th>Driver</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?php echo esc_html($booking->car_name); ?></td>
                        <td><?php echo date('M d, Y', strtotime($booking->pickup_date)); ?></td>
                        <td><?php echo date('M d, Y', strtotime($booking->return_date)); ?></td>
                        <td><?php echo $booking->num_days; ?> days</td>
                        <td><?php echo $booking->with_driver ? 'Yes' : 'No'; ?></td>
                        <td><strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <?php if ($booking->with_driver && $booking->driver_fee_per_day > 0): ?>
            <p style="margin-top: 10px; color: #6b7280; font-size: 14px;">
                * Includes driver fee of ₱<?php echo number_format($booking->driver_fee_per_day, 2); ?>/day
            </p>
            <?php endif; ?>
        </div>
        
        <?php if ($booking->special_requests): ?>
        <div class="bm-quot-section">
            <h2>Special Requests</h2>
            <p><?php echo nl2br(esc_html($booking->special_requests)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bm-quot-section bm-quot-summary">
            <h2>Payment Summary</h2>
            <div class="bm-summary-grid">
                <div class="bm-summary-row">
                    <span>Total Amount:</span>
                    <strong>₱<?php echo number_format($booking->total_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row">
                    <span>Deposit Required:</span>
                    <strong>₱<?php echo number_format($booking->deposit_amount, 2); ?></strong>
                </div>
                <div class="bm-summary-row bm-total">
                    <span>Status:</span>
                    <strong class="bm-status-<?php echo $booking->booking_status; ?>">
                        <?php echo ucfirst($booking->booking_status); ?>
                    </strong>
                </div>
            </div>
        </div>
        
        <?php if ($booking->payment_status !== 'paid'): ?>
        <div class="bm-quot-actions">
            <button onclick="window.print()" class="bm-btn-secondary">Print Quotation</button>
            <?php if (bntm_get_setting('bm_enable_paymaya', '0') == '1'): ?>
            <button class="bm-btn-primary" id="pay-now-btn" 
                    data-booking-id="<?php echo $booking->id; ?>"
                    data-amount="<?php echo $booking->deposit_amount; ?>">
                Pay Deposit (₱<?php echo number_format($booking->deposit_amount, 2); ?>)
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="bm-quot-section bm-paid">
            <h2>✓ Payment Received</h2>
            <p>Your car rental is confirmed. Thank you!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php echo bm_get_quotation_styles(); ?>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const payBtn = document.getElementById('pay-now-btn');
    if (payBtn) {
        payBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'bm_process_car_payment');
            formData.append('booking_id', this.dataset.bookingId);
            formData.append('amount', this.dataset.amount);
            
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
                    this.textContent = 'Pay Deposit';
                }
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// QUOTATION STYLES
// ============================================================================

function bm_get_quotation_styles() {
    return <<<STYLES
    <style>
    .bm-quotation {
        max-width: 900px;
        margin: 40px auto;
        background: #fff;
        padding: 40px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .bm-quot-header {
        text-align: center;
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }
    .bm-quot-header h1 {
        margin: 0 0 10px 0;
        color: #111827;
    }
    .bm-booking-id {
        color: #6b7280;
        font-size: 14px;
    }
    .bm-quot-section {
        margin-bottom: 30px;
    }
    .bm-quot-section h2 {
        margin: 0 0 15px 0;
        color: #111827;
        font-size: 18px;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 10px;
    }
    .bm-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    .bm-info-grid div {
        padding: 10px;
        background: #f9fafb;
        border-radius: 6px;
    }
    .bm-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .bm-table th,
    .bm-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    .bm-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #111827;
    }
    .bm-quot-summary {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
    }
    .bm-summary-grid {
        max-width: 400px;
        margin-left: auto;
    }
    .bm-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .bm-summary-row.bm-total {
        border-top: 2px solid #3b82f6;
        border-bottom: none;
        padding-top: 15px;
        margin-top: 10px;
        font-size: 18px;
    }
    .bm-quot-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
    }
    .bm-btn-primary,
    .bm-btn-secondary {
        padding: 12px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    .bm-btn-primary {
        background: #3b82f6;
        color: white;
    }
    .bm-btn-secondary {
        background: #e5e7eb;
        color: #374151;
    }
    .bm-paid {
        background: #d1fae5;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        color: #065f46;
    }
    .bm-status-pending { color: #f59e0b; }
    .bm-status-confirmed { color: #10b981; }
    .bm-status-paid { color: #10b981; }
    .bm-error {
        padding: 20px;
        background: #fee2e2;
        color: #991b1b;
        border-radius: 6px;
        text-align: center;
    }
    @media print {
        .bm-quot-actions { display: none; }
    }
    </style>
STYLES;
}

// ============================================================================
// AJAX BOOKING SUBMISSION HANDLERS
// ============================================================================

/**
 * Submit hotel booking
 */
function bntm_ajax_bm_submit_hotel_booking() {
    global $wpdb;
    $business_id = get_option('bntm_primary_business_id', 1);
    
    try {
        // Validate
        $required = ['customer_name', 'customer_email', 'customer_phone', 'check_in_date', 'check_out_date'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Please fill all required fields');
            }
        }
        
        // Calculate nights
        $check_in = sanitize_text_field($_POST['check_in_date']);
        $check_out = sanitize_text_field($_POST['check_out_date']);
        $nights = (new DateTime($check_in))->diff(new DateTime($check_out))->days;
        
        if ($nights <= 0) {
            throw new Exception('Invalid dates');
        }
        
        // Calculate total
        $total = 0;
        $rooms_data = [];
        if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
            $room_types_table = $wpdb->prefix . 'bm_room_types';
            foreach ($_POST['rooms'] as $room) {
                if (empty($room['room_id'])) continue;
                $room_type = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$room_types_table} WHERE id = %d", intval($room['room_id'])
                ));
                if ($room_type) {
                    $qty = intval($room['quantity']);
                    $subtotal = $room_type->price_per_night * $qty * $nights;
                    $total += $subtotal;
                    $rooms_data[] = [
                        'room_type' => $room_type->room_type,
                        'num_rooms' => $qty,
                        'price_per_night' => $room_type->price_per_night,
                        'num_nights' => $nights,
                        'subtotal' => $subtotal
                    ];
                }
            }
        }
        
        if (empty($rooms_data)) {
            throw new Exception('Please select at least one room');
        }
        
        $deposit_pct = floatval(bntm_get_setting('bm_deposit_percentage', '30'));
        $deposit = ($total * $deposit_pct) / 100;
        
        // Insert booking
        $rand_id = 'HTL' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $bookings_table = $wpdb->prefix . 'bm_hotel_bookings';
        
        $wpdb->insert($bookings_table, [
            'rand_id' => $rand_id,
            'business_id' => $business_id,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_address' => sanitize_text_field($_POST['customer_address'] ?? ''),
            'check_in_date' => $check_in,
            'check_out_date' => $check_out,
            'num_adults' => intval($_POST['num_adults']),
            'num_children' => intval($_POST['num_children']),
            'special_requests' => sanitize_textarea_field($_POST['special_requests'] ?? ''),
            'total_amount' => $total,
            'deposit_amount' => $deposit,
            'created_at' => current_time('mysql')
        ]);
        
        $booking_id = $wpdb->insert_id;
        
        // Insert rooms
        $rooms_table = $wpdb->prefix . 'bm_hotel_rooms';
        foreach ($rooms_data as $room) {
            $wpdb->insert($rooms_table, [
                'rand_id' => 'RM' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'business_id' => $business_id,
                'booking_id' => $booking_id,
                'room_type' => $room['room_type'],
                'room_name' => $room['room_type'],
                'num_rooms' => $room['num_rooms'],
                'price_per_night' => $room['price_per_night'],
                'num_nights' => $room['num_nights'],
                'subtotal' => $room['subtotal'],
                'created_at' => current_time('mysql')
            ]);
        }
        /**
 * BOOKING MANAGEMENT - PART 4 (FINAL)
 * Complete AJAX Handlers & Helper Functions
 */

// Continue from hotel booking submission...
        foreach ($rooms_data as $room) {
            $wpdb->insert($rooms_table, [
                'rand_id' => 'RM' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'business_id' => $business_id,
                'booking_id' => $booking_id,
                'room_type' => $room['room_type'],
                'room_name' => $room['room_type'],
                'num_rooms' => $room['num_rooms'],
                'price_per_night' => $room['price_per_night'],
                'num_nights' => $room['num_nights'],
                'subtotal' => $room['subtotal']
            ]);
        }
        
        // Send email
        bm_send_hotel_quotation_email($booking_id);
        
        $quotation_url = get_permalink(get_page_by_path('hotel-quotation')) . '?booking_id=' . $rand_id;
        
        wp_send_json_success([
            'message' => 'Hotel booking submitted! Check your email for quotation.',
            'redirect_url' => $quotation_url
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Submit yacht booking
 */
function bntm_ajax_bm_submit_yacht_booking() {
    global $wpdb;
    $business_id = get_option('bntm_primary_business_id', 1);
    
    try {
        $required = ['customer_name', 'customer_email', 'customer_phone', 'yacht_id', 'rental_date', 'duration'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Please fill all required fields');
            }
        }
        
        // Get yacht type
        $yacht_types_table = $wpdb->prefix . 'bm_yacht_types';
        $yacht = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$yacht_types_table} WHERE id = %d", intval($_POST['yacht_id'])
        ));
        
        if (!$yacht) {
            throw new Exception('Invalid yacht selected');
        }
        
        $duration = intval($_POST['duration']);
        $unit = sanitize_text_field($_POST['duration_unit']);
        $price = $unit === 'hours' ? $yacht->price_per_hour : $yacht->price_per_day;
        $total = $price * $duration;
        
        $deposit_pct = floatval(bntm_get_setting('bm_deposit_percentage', '30'));
        $deposit = ($total * $deposit_pct) / 100;
        
        // Insert booking
        $rand_id = 'YCH' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $table = $wpdb->prefix . 'bm_yacht_bookings';
        
        $wpdb->insert($table, [
            'rand_id' => $rand_id,
            'business_id' => $business_id,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_address' => sanitize_text_field($_POST['customer_address'] ?? ''),
            'yacht_type' => $yacht->yacht_name,
            'yacht_name' => $yacht->yacht_name,
            'rental_date' => sanitize_text_field($_POST['rental_date']),
            'rental_duration' => $duration,
            'duration_unit' => $unit,
            'num_guests' => intval($_POST['num_guests']),
            'special_requests' => sanitize_textarea_field($_POST['special_requests'] ?? ''),
            'total_amount' => $total,
            'deposit_amount' => $deposit,
            'created_at' => current_time('mysql')
        ]);
        
        $booking_id = $wpdb->insert_id;
        
        // Send email
        bm_send_yacht_quotation_email($booking_id);
        
        $quotation_url = get_permalink(get_page_by_path('yacht-quotation')) . '?booking_id=' . $rand_id;
        
        wp_send_json_success([
            'message' => 'Yacht booking submitted! Check your email for quotation.',
            'redirect_url' => $quotation_url
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Submit car booking
 */
function bntm_ajax_bm_submit_car_booking() {
    global $wpdb;
    $business_id = get_option('bntm_primary_business_id', 1);
    
    try {
        $required = ['customer_name', 'customer_email', 'customer_phone', 'car_id', 'pickup_date', 'return_date'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception('Please fill all required fields');
            }
        }
        
        // Get car type
        $car_types_table = $wpdb->prefix . 'bm_car_types';
        $car = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$car_types_table} WHERE id = %d", intval($_POST['car_id'])
        ));
        
        if (!$car) {
            throw new Exception('Invalid car selected');
        }
        
        $pickup = sanitize_text_field($_POST['pickup_date']);
        $return = sanitize_text_field($_POST['return_date']);
        $days = (new DateTime($pickup))->diff(new DateTime($return))->days;
        
        if ($days <= 0) {
            throw new Exception('Invalid rental period');
        }
        
        $with_driver = isset($_POST['with_driver']) ? 1 : 0;
        $total = $car->price_per_day * $days;
        if ($with_driver) {
            $total += $car->driver_fee * $days;
        }
        
        $deposit_pct = floatval(bntm_get_setting('bm_deposit_percentage', '30'));
        $deposit = ($total * $deposit_pct) / 100;
        
        // Insert booking
        $rand_id = 'CAR' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $table = $wpdb->prefix . 'bm_car_bookings';
        
        $wpdb->insert($table, [
            'rand_id' => $rand_id,
            'business_id' => $business_id,
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'customer_phone' => sanitize_text_field($_POST['customer_phone']),
            'customer_address' => sanitize_text_field($_POST['customer_address'] ?? ''),
            'car_type' => $car->car_name,
            'car_name' => $car->car_name,
            'pickup_date' => $pickup,
            'return_date' => $return,
            'num_days' => $days,
            'with_driver' => $with_driver,
            'price_per_day' => $car->price_per_day,
            'driver_fee_per_day' => $car->driver_fee,
            'special_requests' => sanitize_textarea_field($_POST['special_requests'] ?? ''),
            'total_amount' => $total,
            'deposit_amount' => $deposit,
            'created_at' => current_time('mysql')
        ]);
        
        $booking_id = $wpdb->insert_id;
        
        // Send email
        bm_send_car_quotation_email($booking_id);
        
        $quotation_url = get_permalink(get_page_by_path('car-quotation')) . '?booking_id=' . $rand_id;
        
        wp_send_json_success([
            'message' => 'Car booking submitted! Check your email for quotation.',
            'redirect_url' => $quotation_url
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

// ============================================================================
// PAYMENT PROCESSING HANDLERS
// ============================================================================

/**
 * Process hotel payment
 */
function bntm_ajax_bm_process_hotel_payment() {
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    $amount = floatval($_POST['amount']);
    
    $table = $wpdb->prefix . 'bm_hotel_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    $result = bm_process_paymaya_payment($booking, $amount, 'hotel');
    
    if ($result['success']) {
        $wpdb->update($table, [
            'payment_gateway' => 'paymaya',
            'payment_transaction_id' => $result['transaction_id']
        ], ['id' => $booking_id]);
        
        wp_send_json_success(['redirect_url' => $result['redirect_url']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

/**
 * Process yacht payment
 */
function bntm_ajax_bm_process_yacht_payment() {
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    $amount = floatval($_POST['amount']);
    
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    $result = bm_process_paymaya_payment($booking, $amount, 'yacht');
    
    if ($result['success']) {
        $wpdb->update($table, [
            'payment_gateway' => 'paymaya',
            'payment_transaction_id' => $result['transaction_id']
        ], ['id' => $booking_id]);
        
        wp_send_json_success(['redirect_url' => $result['redirect_url']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

/**
 * Process car payment
 */
function bntm_ajax_bm_process_car_payment() {
    global $wpdb;
    $booking_id = intval($_POST['booking_id']);
    $amount = floatval($_POST['amount']);
    
    $table = $wpdb->prefix . 'bm_car_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    $result = bm_process_paymaya_payment($booking, $amount, 'car');
    
    if ($result['success']) {
        $wpdb->update($table, [
            'payment_gateway' => 'paymaya',
            'payment_transaction_id' => $result['transaction_id']
        ], ['id' => $booking_id]);
        
        wp_send_json_success(['redirect_url' => $result['redirect_url']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

/**
 * Process PayMaya payment (unified)
 */
function bm_process_paymaya_payment($booking, $amount, $type) {
    $paymaya_mode = bntm_get_setting('bm_paymaya_mode', 'sandbox');
    $paymaya_public_key = bntm_get_setting('bm_paymaya_public_key', '');
    
    if (empty($paymaya_public_key)) {
        return ['success' => false, 'message' => 'PayMaya not configured'];
    }
    
    $base_url = $paymaya_mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';
    
    $quotation_page = $type . '-quotation';
    $success_url = get_permalink(get_page_by_path($quotation_page)) . 
                   '?booking_id=' . $booking->rand_id . '&payment_success=1';
    $failure_url = get_permalink(get_page_by_path($quotation_page)) . 
                   '?booking_id=' . $booking->rand_id . '&payment_failed=1';
    
    $item_name = ucfirst($type) . ' Booking Deposit - #' . $booking->rand_id;
    
    $checkout_data = [
        'totalAmount' => ['value' => floatval($amount), 'currency' => 'PHP'],
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
                'name' => $item_name,
                'quantity' => 1,
                'amount' => ['value' => floatval($amount)],
                'totalAmount' => ['value' => floatval($amount)]
            ]
        ],
        'redirectUrl' => [
            'success' => $success_url,
            'failure' => $failure_url,
            'cancel' => $failure_url
        ],
        'requestReferenceNumber' => $booking->rand_id,
        'metadata' => [
            'booking_id' => $booking->id,
            'booking_type' => $type
        ]
    ];
    
    $response = wp_remote_post($base_url, [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($paymaya_public_key . ':'),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($checkout_data),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return ['success' => false, 'message' => 'Payment gateway error'];
    }
    
    $response_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!isset($response_data['checkoutId'])) {
        return ['success' => false, 'message' => 'Invalid payment response'];
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
    
    if (!isset($_GET['booking_id'])) {
        return;
    }
    
    $booking_id = sanitize_text_field($_GET['booking_id']);
    global $wpdb;
    
    // Determine booking type from prefix
    $type = '';
    if (strpos($booking_id, 'HTL') === 0) {
        $type = 'hotel';
        $table = $wpdb->prefix . 'bm_hotel_bookings';
    } elseif (strpos($booking_id, 'YCH') === 0) {
        $type = 'yacht';
        $table = $wpdb->prefix . 'bm_yacht_bookings';
    } elseif (strpos($booking_id, 'CAR') === 0) {
        $type = 'car';
        $table = $wpdb->prefix . 'bm_car_bookings';
    } else {
        return;
    }
    
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE rand_id = %s", $booking_id));
    
    if (!$booking || $booking->payment_status === 'paid') {
        return;
    }
    
    $wpdb->update($table, [
        'payment_status' => 'paid',
        'booking_status' => 'confirmed',
        'confirmed_at' => current_time('mysql')
    ], ['id' => $booking->id]);
    
    // Send confirmation email based on type
    if ($type === 'hotel') {
        bm_send_hotel_confirmation_email($booking->id);
    } elseif ($type === 'yacht') {
        bm_send_yacht_confirmation_email($booking->id);
    } elseif ($type === 'car') {
        bm_send_car_confirmation_email($booking->id);
    }
}

// ============================================================================
// EMAIL FUNCTIONS
// ============================================================================

/**
 * Send hotel quotation email
 */
function bm_send_hotel_quotation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_hotel_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel');
    $hotel_email = bntm_get_setting('bm_hotel_email', get_option('admin_email'));
    $quotation_url = get_permalink(get_page_by_path('hotel-quotation')) . '?booking_id=' . $booking->rand_id;
    
    $subject = "Hotel Booking Quotation - {$hotel_name}";
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Thank you for your hotel booking request!\n\n";
    $message .= "Booking ID: {$booking->rand_id}\n";
    $message .= "Total Amount: ₱" . number_format($booking->total_amount, 2) . "\n";
    $message .= "Deposit Required: ₱" . number_format($booking->deposit_amount, 2) . "\n\n";
    $message .= "View your complete quotation: {$quotation_url}\n\n";
    $message .= "Best regards,\n{$hotel_name}";
    
    wp_mail($booking->customer_email, $subject, $message, ['From: ' . $hotel_name . ' <' . $hotel_email . '>']);
}

/**
 * Send yacht quotation email
 */
function bm_send_yacht_quotation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    $company_name = bntm_get_setting('bm_hotel_name', 'Yacht Rentals');
    $company_email = bntm_get_setting('bm_hotel_email', get_option('admin_email'));
    $quotation_url = get_permalink(get_page_by_path('yacht-quotation')) . '?booking_id=' . $booking->rand_id;
    
    $subject = "Yacht Rental Quotation - {$company_name}";
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Thank you for your yacht rental request!\n\n";
    $message .= "Booking ID: {$booking->rand_id}\n";
    $message .= "Total Amount: ₱" . number_format($booking->total_amount, 2) . "\n";
    $message .= "Deposit Required: ₱" . number_format($booking->deposit_amount, 2) . "\n\n";
    $message .= "View your complete quotation: {$quotation_url}\n\n";
    $message .= "Best regards,\n{$company_name}";
    
    wp_mail($booking->customer_email, $subject, $message, ['From: ' . $company_name . ' <' . $company_email . '>']);
}

/**
 * Send car quotation email
 */
function bm_send_car_quotation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_car_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    $company_name = bntm_get_setting('bm_hotel_name', 'Car Rentals');
    $company_email = bntm_get_setting('bm_hotel_email', get_option('admin_email'));
    $quotation_url = get_permalink(get_page_by_path('car-quotation')) . '?booking_id=' . $booking->rand_id;
    
    $subject = "Car Rental Quotation - {$company_name}";
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Thank you for your car rental request!\n\n";
    $message .= "Booking ID: {$booking->rand_id}\n";
    $message .= "Total Amount: ₱" . number_format($booking->total_amount, 2) . "\n";
    $message .= "Deposit Required: ₱" . number_format($booking->deposit_amount, 2) . "\n\n";
    $message .= "View your complete quotation: {$quotation_url}\n\n";
    $message .= "Best regards,\n{$company_name}";
    
    wp_mail($booking->customer_email, $subject, $message, ['From: ' . $company_name . ' <' . $company_email . '>']);
}

/**
 * Send confirmation emails (simplified versions)
 */
function bm_send_hotel_confirmation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_hotel_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    $hotel_name = bntm_get_setting('bm_hotel_name', 'Hotel');
    $subject = "Booking Confirmed - {$hotel_name}";
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Your hotel booking #{$booking->rand_id} is confirmed!\n\n";
    $message .= "We look forward to welcoming you.\n\n";
    $message .= "Best regards,\n{$hotel_name}";
    
    wp_mail($booking->customer_email, $subject, $message);
}

function bm_send_yacht_confirmation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    $company_name = bntm_get_setting('bm_hotel_name', 'Yacht Rentals');
    $subject = "Yacht Rental Confirmed - {$company_name}";
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Your yacht rental #{$booking->rand_id} is confirmed!\n\n";
    $message .= "Get ready for an amazing experience.\n\n";
    $message .= "Best regards,\n{$company_name}";
    
    wp_mail($booking->customer_email, $subject, $message);
}

function bm_send_car_confirmation_email($booking_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bm_car_bookings';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    
    $company_name = bntm_get_setting('bm_hotel_name', 'Car Rentals');
    $subject = "Car Rental Confirmed - {$company_name}";
    $message = "Dear {$booking->customer_name},\n\n";
    $message .= "Your car rental #{$booking->rand_id} is confirmed!\n\n";
    $message .= "Your vehicle will be ready for pickup.\n\n";
    $message .= "Best regards,\n{$company_name}";
    
    wp_mail($booking->customer_email, $subject, $message);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

if (!function_exists('bntm_get_setting')) {
    function bntm_get_setting($key, $default = '') {
        return get_option($key, $default);
    }
}

if (!function_exists('bntm_set_setting')) {
    function bntm_set_setting($key, $value) {
        return update_option($key, $value);
    }
}

if (!function_exists('bntm_universal_container')) {
    function bntm_universal_container($title, $content) {
        return '<div class="bntm-container"><h1>' . esc_html($title) . '</h1>' . $content . '</div>';
    }
}

// Settings AJAX handlers (reuse from original code)
function bntm_ajax_bm_save_hotel_info() {
    check_ajax_referer('bm_settings_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    bntm_set_setting('bm_hotel_name', sanitize_text_field($_POST['hotel_name']));
    bntm_set_setting('bm_hotel_email', sanitize_email($_POST['hotel_email']));
    bntm_set_setting('bm_hotel_phone', sanitize_text_field($_POST['hotel_phone']));
    bntm_set_setting('bm_hotel_address', sanitize_textarea_field($_POST['hotel_address']));
    bntm_set_setting('bm_deposit_percentage', intval($_POST['deposit_percentage']));
    
    wp_send_json_success(['message' => 'Settings saved!']);
}

function bntm_ajax_bm_save_paymaya_settings() {
    check_ajax_referer('bm_settings_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    bntm_set_setting('bm_enable_paymaya', isset($_POST['enable_paymaya']) ? '1' : '0');
    bntm_set_setting('bm_paymaya_mode', sanitize_text_field($_POST['paymaya_mode']));
    bntm_set_setting('bm_paymaya_public_key', sanitize_text_field($_POST['paymaya_public_key']));
    bntm_set_setting('bm_paymaya_secret_key', sanitize_text_field($_POST['paymaya_secret_key']));
    
    wp_send_json_success(['message' => 'PayMaya settings saved!']);
}

// ============================================================================
// FLEET MANAGEMENT AJAX HANDLERS
// ============================================================================

/**
 * Save Room Type
 */
function bntm_ajax_bm_save_room_type() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $business_id = wp_get_current_user()->ID;
    $table = $wpdb->prefix . 'bm_room_types';
    
    $room_type = sanitize_text_field($_POST['room_type']);
    $price = floatval($_POST['price_per_night']);
    $occupancy = intval($_POST['max_occupancy']);
    $amenities = sanitize_textarea_field($_POST['amenities']);
    
    if (empty($room_type) || $price <= 0) {
        wp_send_json_error(['message' => 'Invalid data']);
    }
    
    $rand_id = 'RM' . strtoupper(substr(md5(uniqid()), 0, 8));
    
    $wpdb->insert($table, [
        'rand_id' => $rand_id,
        'business_id' => $business_id,
        'room_type' => $room_type,
        'price_per_night' => $price,
        'max_occupancy' => $occupancy,
        'amenities' => $amenities,
        'is_active' => 1,
        'created_at' => current_time('mysql')
    ]);
    
    wp_send_json_success(['message' => 'Room type saved!']);
}

/**
 * Delete Room Type
 */
function bntm_ajax_bm_delete_room_type() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $id = intval($_POST['id']);
    $table = $wpdb->prefix . 'bm_room_types';
    
    $wpdb->delete($table, ['id' => $id], ['%d']);
    
    wp_send_json_success(['message' => 'Room type deleted!']);
}

/**
 * Save Yacht Type
 */
function bntm_ajax_bm_save_yacht_type() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $business_id = wp_get_current_user()->ID;
    $table = $wpdb->prefix . 'bm_yacht_types';
    
    $yacht_name = sanitize_text_field($_POST['yacht_name']);
    $price_hour = floatval($_POST['price_per_hour']);
    $price_day = floatval($_POST['price_per_day']);
    $max_guests = intval($_POST['max_guests']);
    $features = sanitize_textarea_field($_POST['features']);
    
    if (empty($yacht_name) || $price_hour <= 0 || $price_day <= 0) {
        wp_send_json_error(['message' => 'Invalid data']);
    }
    
    $rand_id = 'YCH' . strtoupper(substr(md5(uniqid()), 0, 8));
    
    $wpdb->insert($table, [
        'rand_id' => $rand_id,
        'business_id' => $business_id,
        'yacht_name' => $yacht_name,
        'price_per_hour' => $price_hour,
        'price_per_day' => $price_day,
        'max_guests' => $max_guests,
        'features' => $features,
        'is_active' => 1,
        'created_at' => current_time('mysql')
    ]);
    
    wp_send_json_success(['message' => 'Yacht added!']);
}

/**
 * Delete Yacht Type
 */
function bntm_ajax_bm_delete_yacht_type() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $id = intval($_POST['id']);
    $table = $wpdb->prefix . 'bm_yacht_types';
    
    $wpdb->delete($table, ['id' => $id], ['%d']);
    
    wp_send_json_success(['message' => 'Yacht deleted!']);
}

/**
 * Save Car Type
 */
function bntm_ajax_bm_save_car_type() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $business_id = wp_get_current_user()->ID;
    $table = $wpdb->prefix . 'bm_car_types';
    
    $car_name = sanitize_text_field($_POST['car_name']);
    $price_day = floatval($_POST['price_per_day']);
    $driver_fee = floatval($_POST['driver_fee']);
    $max_pass = intval($_POST['max_passengers']);
    $features = sanitize_textarea_field($_POST['features']);
    
    if (empty($car_name) || $price_day <= 0) {
        wp_send_json_error(['message' => 'Invalid data']);
    }
    
    $rand_id = 'CAR' . strtoupper(substr(md5(uniqid()), 0, 8));
    
    $wpdb->insert($table, [
        'rand_id' => $rand_id,
        'business_id' => $business_id,
        'car_name' => $car_name,
        'price_per_day' => $price_day,
        'driver_fee' => $driver_fee,
        'max_passengers' => $max_pass,
        'features' => $features,
        'is_active' => 1,
        'created_at' => current_time('mysql')
    ]);
    
    wp_send_json_success(['message' => 'Car added!']);
}

/**
 * Delete Car Type
 */
function bntm_ajax_bm_delete_car_type() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $id = intval($_POST['id']);
    $table = $wpdb->prefix . 'bm_car_types';
    
    $wpdb->delete($table, ['id' => $id], ['%d']);
    
    wp_send_json_success(['message' => 'Car deleted!']);
}

// ============================================================================
// BOOKING STATUS MANAGEMENT AJAX HANDLERS
// ============================================================================

/**
 * Update Hotel Booking Status
 */
function bntm_ajax_bm_update_hotel_status() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    $table = $wpdb->prefix . 'bm_hotel_bookings';
    
    $valid_statuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        wp_send_json_error(['message' => 'Invalid status']);
    }
    
    $wpdb->update($table, [
        'booking_status' => $status,
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    if ($status === 'confirmed') {
        bm_send_hotel_confirmation_email($booking_id);
    }
    
    wp_send_json_success(['message' => 'Status updated!']);
}

/**
 * Update Yacht Booking Status
 */
function bntm_ajax_bm_update_yacht_status() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    
    $valid_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        wp_send_json_error(['message' => 'Invalid status']);
    }
    
    $wpdb->update($table, [
        'booking_status' => $status,
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    if ($status === 'confirmed') {
        bm_send_yacht_confirmation_email($booking_id);
    }
    
    wp_send_json_success(['message' => 'Status updated!']);
}

/**
 * Update Car Booking Status
 */
function bntm_ajax_bm_update_car_status() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    $table = $wpdb->prefix . 'bm_car_bookings';
    
    $valid_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        wp_send_json_error(['message' => 'Invalid status']);
    }
    
    $wpdb->update($table, [
        'booking_status' => $status,
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    if ($status === 'confirmed') {
        bm_send_car_confirmation_email($booking_id);
    }
    
    wp_send_json_success(['message' => 'Status updated!']);
}

// ============================================================================
// PHONE CONFIRMATION AJAX HANDLERS
// ============================================================================

/**
 * Confirm Hotel Provider Phone
 */
function bntm_ajax_bm_confirm_hotel_phone() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $table = $wpdb->prefix . 'bm_hotel_bookings';
    
    $wpdb->update($table, [
        'provider_phone_confirmed' => 1,
        'provider_phone_confirmed_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    wp_send_json_success(['message' => 'Phone confirmed!']);
}

/**
 * Confirm Yacht Provider Phone
 */
function bntm_ajax_bm_confirm_yacht_phone() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    
    $wpdb->update($table, [
        'provider_phone_confirmed' => 1,
        'provider_phone_confirmed_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    wp_send_json_success(['message' => 'Phone confirmed!']);
}

/**
 * Confirm Car Provider Phone
 */
function bntm_ajax_bm_confirm_car_phone() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $table = $wpdb->prefix . 'bm_car_bookings';
    
    $wpdb->update($table, [
        'provider_phone_confirmed' => 1,
        'provider_phone_confirmed_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    wp_send_json_success(['message' => 'Phone confirmed!']);
}

// ============================================================================
// RESEND QUOTATION AJAX HANDLERS
// ============================================================================

/**
 * Resend Hotel Quotation Email
 */
function bntm_ajax_bm_resend_hotel_quotation() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $table = $wpdb->prefix . 'bm_hotel_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    bm_send_hotel_quotation_email($booking_id);
    
    $wpdb->update($table, [
        'quotation_sent_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    wp_send_json_success(['message' => 'Quotation re-sent!']);
}

/**
 * Resend Yacht Quotation Email
 */
function bntm_ajax_bm_resend_yacht_quotation() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $table = $wpdb->prefix . 'bm_yacht_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    bm_send_yacht_quotation_email($booking_id);
    
    $wpdb->update($table, [
        'quotation_sent_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    wp_send_json_success(['message' => 'Quotation re-sent!']);
}

/**
 * Resend Car Quotation Email
 */
function bntm_ajax_bm_resend_car_quotation() {
    global $wpdb;
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    $booking_id = intval($_POST['booking_id']);
    $table = $wpdb->prefix . 'bm_car_bookings';
    
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    bm_send_car_quotation_email($booking_id);
    
    $wpdb->update($table, [
        'quotation_sent_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ], ['id' => $booking_id]);
    
    wp_send_json_success(['message' => 'Quotation re-sent!']);
}

// ============================================================================
// PAYMENT & WEBHOOK HANDLERS
// ============================================================================

/**
 * Handle PayMaya Payment Success Redirect
 */
function bm_handle_payment_success() {
    if (!isset($_GET['booking_id']) || !isset($_GET['payment_success'])) {
        return;
    }
    
    $booking_id = sanitize_text_field($_GET['booking_id']);
    
    // Payment verification would happen here in production
    // This is a simple implementation
    
    if (isset($_GET['checkoutId'])) {
        $checkout_id = sanitize_text_field($_GET['checkoutId']);
        // Store transaction ID if needed
    }
}

/**
 * Handle PayMaya Webhook
 */
function bm_handle_paymaya_webhook() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    
    if (empty($data) || empty($data['id'])) {
        return rest_ensure_response(['error' => 'Invalid webhook']);
    }
    
    global $wpdb;
    
    // Update payment status based on webhook
    if (!empty($data['status'])) {
        $status = $data['status'];
        
        if ($status === 'SUCCESS') {
            // Find booking by transaction ID
            $all_tables = [
                $wpdb->prefix . 'bm_hotel_bookings',
                $wpdb->prefix . 'bm_yacht_bookings',
                $wpdb->prefix . 'bm_car_bookings'
            ];
            
            foreach ($all_tables as $table) {
                $wpdb->update($table, [
                    'payment_status' => 'paid',
                    'payment_transaction_id' => $data['id'],
                    'updated_at' => current_time('mysql')
                ], ['payment_transaction_id' => $data['id']]);
            }
        }
    }
    
    return rest_ensure_response(['success' => true]);
}

// Room/Yacht/Car type management AJAX (reuse from original, just reference the correct tables)
// Copy these from the original file: bntm_ajax_bm_save_room_type, etc.

// END OF MODULE