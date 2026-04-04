<?php
/**
 * Module Name: Car Rental Booking
 * Module Slug: cr
 * Description: Manage Car rental packages and bookings
 * Version: 1.0.0
 * Author: BNTM
 * Icon: 🚗
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_CR_PATH', dirname(__FILE__) . '/');
define('BNTM_CR_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================================

function bntm_cr_get_pages() {
    return [
        'Car Rental Booking Dashboard' => '[car_rental_booking_dashboard]',
        'Car Book Now' => '[car_rental_booking_form]',
        'Car Book Now Embed' => '[car_rental_booking_form_embed]',
        'Car Booking Invoice' => '[car_rental_booking_invoice]', // Add this
    ];
}

function bntm_cr_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'cr_packages' => "CREATE TABLE {$prefix}cr_packages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            package_name VARCHAR(255) NOT NULL,
            city VARCHAR(50) NOT NULL DEFAULT 'cebu',
            vehicle_category VARCHAR(50) NOT NULL DEFAULT 'car',
            boat_type VARCHAR(100) NOT NULL,
            daily_rate DECIMAL(10,2) NOT NULL,
            hourly_surcharge DECIMAL(10,2) DEFAULT 0,
            max_pax INT NOT NULL,
            photo_url VARCHAR(500) DEFAULT NULL,
            description TEXT,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",
        
        'cr_bookings' => "CREATE TABLE {$prefix}cr_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            package_id BIGINT UNSIGNED NOT NULL,
            city VARCHAR(50) NOT NULL DEFAULT 'cebu',
            vehicle_category VARCHAR(50) NOT NULL DEFAULT 'car',
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            check_in_time DATETIME NULL,
            check_out_time DATETIME NULL,
            base_point VARCHAR(255) DEFAULT '',
            destination VARCHAR(255) DEFAULT '',
            distance_km DECIMAL(10,2) DEFAULT 0,
            total_hours DECIMAL(10,2) DEFAULT 0,
            number_of_days INT NOT NULL,
            number_of_pax INT NOT NULL,
            base_fee DECIMAL(10,2) DEFAULT 0,
            rate_per_km DECIMAL(10,2) DEFAULT 0,
            overtime_rate DECIMAL(10,2) DEFAULT 0,
            daily_rate DECIMAL(10,2) NOT NULL,
            package_amount DECIMAL(10,2) NOT NULL,
            excess_hours DECIMAL(10,2) DEFAULT 0,
            surcharge_amount DECIMAL(10,2) DEFAULT 0,
            pricing_type VARCHAR(20) DEFAULT 'formula',
            other_fees JSON DEFAULT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT 'contacted',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_date (start_date)
        ) {$charset};"
    ];
}


// Then add this new shortcode handler
function bntm_shortcode_cr_form_embed() {
    // Force remove headers for clean iframe embed
    if (!defined('IFRAME_REQUEST')) {
        define('IFRAME_REQUEST', true);
    }
    
    // Same form but without any wrapper
    return bntm_shortcode_cr_form();
}

// Update shortcodes array
function bntm_cr_get_shortcodes() {
    return [
        'car_rental_booking_dashboard' => 'bntm_shortcode_cr_dashboard',
        'car_rental_booking_form' => 'bntm_shortcode_cr_form',
        'car_rental_booking_form_embed' => 'bntm_shortcode_cr_form_embed',
        'car_rental_booking_invoice' => 'bntm_shortcode_cr_invoice', // Add this
    ];
}
function bntm_cr_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_cr_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    bntm_cr_seed_defaults();
    return count($tables);
}

function bntm_cr_get_city_choices() {
    return [
        'cebu' => 'Cebu',
        'cdo'  => 'CDO',
    ];
}

function bntm_cr_get_vehicle_category_labels() {
    $labels = [];
    foreach (bntm_cr_get_pricing_rules() as $slug => $rule) {
        $labels[$slug] = $rule['label'] ?? ucfirst(str_replace('_', ' ', $slug));
    }
    return $labels;
}

function bntm_cr_get_default_pricing_rules() {
    return [
        'car' => [
            'label' => 'Car',
            'base_fee' => 2500,
            'rate_per_km' => 12,
            'overtime_rate' => 150,
        ],
        'van_innova' => [
            'label' => 'Van / Innova',
            'base_fee' => 2800,
            'rate_per_km' => 15,
            'overtime_rate' => 250,
        ],
        'suv_grandia' => [
            'label' => 'SUV / Grandia',
            'base_fee' => 3200,
            'rate_per_km' => 18,
            'overtime_rate' => 300,
        ],
    ];
}

function bntm_cr_get_default_vehicle_category_slug() {
    $rules = bntm_cr_get_pricing_rules();
    $keys = array_keys($rules);
    return !empty($keys) ? $keys[0] : 'car';
}

function bntm_cr_get_default_routes() {
    return [
        [
            'city' => 'cebu',
            'base_point' => 'Mactan',
            'destination' => 'Simala',
            'distance_km' => 114,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cebu',
            'base_point' => 'Mactan',
            'destination' => 'Oslob',
            'distance_km' => 127,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cebu',
            'base_point' => 'Cebu City',
            'destination' => 'Temple of Leah',
            'distance_km' => 12,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cdo',
            'base_point' => 'Cagayan de Oro City',
            'destination' => 'Dahilayan',
            'distance_km' => 42,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
        [
            'city' => 'cdo',
            'base_point' => 'Cagayan de Oro City',
            'destination' => 'Camiguin Port',
            'distance_km' => 91,
            'fixed_rates' => ['car' => 0, 'van_innova' => 0, 'suv_grandia' => 0],
        ],
    ];
}

function bntm_cr_seed_defaults() {
    if (!bntm_get_setting('cr_pricing_rules')) {
        bntm_set_setting('cr_pricing_rules', wp_json_encode(bntm_cr_get_default_pricing_rules()));
    }
    if (!bntm_get_setting('cr_route_points')) {
        bntm_set_setting('cr_route_points', wp_json_encode(bntm_cr_get_default_routes()));
    }
}

function bntm_cr_get_pricing_rules() {
    $defaults = bntm_cr_get_default_pricing_rules();
    $saved = json_decode((string) bntm_get_setting('cr_pricing_rules', ''), true);
    if (!is_array($saved)) {
        return $defaults;
    }

    $normalized = [];
    foreach ($saved as $slug => $saved_rule) {
        if (!is_array($saved_rule)) {
            continue;
        }

        $slug = sanitize_key(is_string($slug) ? $slug : ($saved_rule['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $default_rule = $defaults[$slug] ?? [
            'label' => $saved_rule['label'] ?? ucfirst(str_replace('_', ' ', $slug)),
            'base_fee' => 0,
            'rate_per_km' => 0,
            'overtime_rate' => 0,
        ];

        $normalized[$slug] = [
            'label' => sanitize_text_field($saved_rule['label'] ?? $default_rule['label']),
            'base_fee' => floatval($saved_rule['base_fee'] ?? $default_rule['base_fee']),
            'rate_per_km' => floatval($saved_rule['rate_per_km'] ?? $default_rule['rate_per_km']),
            'overtime_rate' => floatval($saved_rule['overtime_rate'] ?? $default_rule['overtime_rate']),
        ];
    }

    return !empty($normalized) ? $normalized : $defaults;
}

function bntm_cr_normalize_city($city) {
    $city = strtolower(trim((string) $city));
    return array_key_exists($city, bntm_cr_get_city_choices()) ? $city : 'cebu';
}

function bntm_cr_parse_route_definitions($text) {
    $routes = [];
    $lines = preg_split('/\r\n|\r|\n/', (string) $text);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 4) {
            continue;
        }

        $routes[] = [
            'city' => bntm_cr_normalize_city($parts[0]),
            'base_point' => sanitize_text_field($parts[1]),
            'destination' => sanitize_text_field($parts[2]),
            'distance_km' => floatval($parts[3]),
            'fixed_rates' => [
                'car' => floatval($parts[4] ?? 0),
                'van_innova' => floatval($parts[5] ?? 0),
                'suv_grandia' => floatval($parts[6] ?? 0),
            ],
        ];
    }

    return $routes;
}

function bntm_cr_routes_to_text($routes) {
    $lines = [];
    if (!is_array($routes)) {
        return '';
    }

    foreach ($routes as $route) {
        $fixed = is_array($route['fixed_rates'] ?? null) ? $route['fixed_rates'] : [];
        $lines[] = implode('|', [
            $route['city'] ?? 'cebu',
            $route['base_point'] ?? '',
            $route['destination'] ?? '',
            floatval($route['distance_km'] ?? 0),
            floatval($fixed['car'] ?? 0),
            floatval($fixed['van_innova'] ?? 0),
            floatval($fixed['suv_grandia'] ?? 0),
        ]);
    }

    return implode("\n", $lines);
}

function bntm_cr_get_routes() {
    $saved = json_decode((string) bntm_get_setting('cr_route_points', ''), true);
    if (!is_array($saved) || empty($saved)) {
        return bntm_cr_get_default_routes();
    }

    $routes = [];
    foreach ($saved as $route) {
        if (!is_array($route)) {
            continue;
        }
        $routes[] = [
            'city' => bntm_cr_normalize_city($route['city'] ?? 'cebu'),
            'base_point' => sanitize_text_field($route['base_point'] ?? ''),
            'destination' => sanitize_text_field($route['destination'] ?? ''),
            'distance_km' => floatval($route['distance_km'] ?? 0),
            'fixed_rates' => [
                'car' => floatval($route['fixed_rates']['car'] ?? 0),
                'van_innova' => floatval($route['fixed_rates']['van_innova'] ?? 0),
                'suv_grandia' => floatval($route['fixed_rates']['suv_grandia'] ?? 0),
            ],
        ];
    }

    return !empty($routes) ? $routes : bntm_cr_get_default_routes();
}

function bntm_cr_find_route($city, $destination, $base_point = '') {
    $city = bntm_cr_normalize_city($city);
    $destination = trim((string) $destination);
    $base_point = trim((string) $base_point);

    foreach (bntm_cr_get_routes() as $route) {
        if ($route['city'] !== $city) {
            continue;
        }
        if (strcasecmp((string) $route['destination'], $destination) !== 0) {
            continue;
        }
        if ($base_point !== '' && strcasecmp((string) $route['base_point'], $base_point) !== 0) {
            continue;
        }
        return $route;
    }

    return null;
}

function bntm_cr_calculate_total($vehicle_category, $distance_km, $total_hours, $route = null) {
    $rules = bntm_cr_get_pricing_rules();
    $vehicle_category = array_key_exists($vehicle_category, $rules) ? $vehicle_category : bntm_cr_get_default_vehicle_category_slug();
    $rule = $rules[$vehicle_category];

    $distance_km = max(0, floatval($distance_km));
    $total_hours = max(0, floatval($total_hours));

    $base_fee = floatval($rule['base_fee']);
    $rate_per_km = floatval($rule['rate_per_km']);
    $overtime_rate = floatval($rule['overtime_rate']);
    $distance_charge = $distance_km * $rate_per_km;
    $base_rate = $base_fee + $distance_charge;
    $overtime_hours = max(0, $total_hours - 10);
    $overtime_charge = $overtime_hours * $overtime_rate;
    $pricing_type = 'formula';
    $fixed_rate = 0;

    if (is_array($route)) {
        $fixed_rates = is_array($route['fixed_rates'] ?? null) ? $route['fixed_rates'] : [];
        $fixed_rate = floatval($fixed_rates[$vehicle_category] ?? 0);
        if ($fixed_rate > 0) {
            $base_rate = $fixed_rate;
            $pricing_type = 'fixed';
        }
    }

    $total_cost = $base_rate + $overtime_charge;

    return [
        'vehicle_category' => $vehicle_category,
        'base_fee' => round($base_fee, 2),
        'rate_per_km' => round($rate_per_km, 2),
        'overtime_rate' => round($overtime_rate, 2),
        'distance_km' => round($distance_km, 2),
        'distance_charge' => round($distance_charge, 2),
        'base_rate' => round($base_rate, 2),
        'total_hours' => round($total_hours, 2),
        'overtime_hours' => round($overtime_hours, 2),
        'overtime_charge' => round($overtime_charge, 2),
        'pricing_type' => $pricing_type,
        'fixed_rate' => round($fixed_rate, 2),
        'total_cost' => round($total_cost, 2),
    ];
}

add_action('init', 'bntm_cr_maybe_upgrade_schema');
function bntm_cr_maybe_upgrade_schema() {
    $schema_version = '1.1.0';
    if (get_option('bntm_cr_schema_version') === $schema_version) {
        return;
    }

    bntm_cr_create_tables();
    update_option('bntm_cr_schema_version', $schema_version, false);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

add_action('wp_ajax_cr_add_package', 'bntm_ajax_cr_add_package');
add_action('wp_ajax_cr_update_package', 'bntm_ajax_cr_update_package');


add_action('wp_ajax_cr_update_package_full', 'bntm_ajax_cr_update_package_full');
add_action('wp_ajax_cr_delete_package', 'bntm_ajax_cr_delete_package');
add_action('wp_ajax_cr_update_booking_status', 'bntm_ajax_cr_update_booking_status');
add_action('wp_ajax_cr_delete_booking', 'bntm_ajax_cr_delete_booking');

add_action('wp_ajax_cr_edit_booking', 'bntm_ajax_cr_edit_booking');
add_action('wp_ajax_cr_submit_booking', 'bntm_ajax_cr_submit_booking');
add_action('wp_ajax_nopriv_cr_submit_booking', 'bntm_ajax_cr_submit_booking');

add_action('wp_ajax_cr_save_payment_source', 'bntm_ajax_cr_save_payment_source');
add_action('wp_ajax_cr_add_payment_method', 'bntm_ajax_cr_add_payment_method');
add_action('wp_ajax_cr_remove_payment_method', 'bntm_ajax_cr_remove_payment_method');

add_action('wp_ajax_cr_save_booking_settings', 'bntm_ajax_cr_save_booking_settings');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_cr_dashboard() {
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        return '<p>You do not have permission to access this page.</p>';
    }
    
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
      <style>
            .bntm-modal {display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: auto;}
    .bntm-modal-content {background-color: #fff; margin: 50px auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);}
    .bntm-modal-header {display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e5e7eb;}
    .bntm-modal-close {font-size: 28px; font-weight: bold; color: #6b7280; cursor: pointer; border: none; background: none;}
    .bntm-modal-close:hover {color: #000;}
    </style>
        <script>
    function openModal(id) {document.getElementById(id).style.display = 'block';}
    function closeModal(id) {document.getElementById(id).style.display = 'none';}
    window.onclick = function(event) {if (event.target.classList.contains('bntm-modal')) {event.target.style.display = 'none';}}
    </script>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-cr-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=packages" class="bntm-tab <?php echo $active_tab === 'packages' ? 'active' : ''; ?>">
                Packages
            </a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">
                Bookings
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo cr_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'packages'): ?>
                <?php echo cr_packages_tab($business_id); ?>
            <?php elseif ($active_tab === 'bookings'): ?>
                <?php echo cr_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo cr_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Car Rental Booking', $content);
}

// ============================================================================
// TAB FUNCTIONS
// ============================================================================
function cr_overview_tab($business_id) {
    global $wpdb;
    $packages_table = $wpdb->prefix . 'cr_packages';
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    
    $total_packages = $wpdb->get_var("SELECT COUNT(*) FROM $packages_table WHERE status='active'");
    $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
    $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status IN ('contacted','paid')");
    
    // Get all bookings for calendar - include start_date and end_date
    $all_bookings = $wpdb->get_results("
        SELECT b.*, p.package_name 
        FROM $bookings_table b
        LEFT JOIN $packages_table p ON b.package_id = p.id
        ORDER BY b.start_date ASC
    ");
    
    // Get booking form URL
    $booking_page = get_page_by_path('car-book-now-embed');
    $booking_url = $booking_page ? get_permalink($booking_page->ID) : '';
    
    ob_start();
    ?>
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
    .bntm-calendar-container {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .bntm-calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
        margin-top: 15px;
    }
    .bntm-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .bntm-calendar-nav {
        display: flex;
        gap: 10px;
    }
    .bntm-calendar-day {
        text-align: center;
        padding: 10px 5px;
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        border-bottom: 2px solid #e5e7eb;
    }
    .bntm-calendar-date {
        text-align: center;
        padding: 8px 5px;
        font-size: 14px;
        border-radius: 4px;
        cursor: pointer;
        position: relative;
        min-height: 70px;
        transition: background 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
    }
    .bntm-calendar-date:hover {
        background: #f3f4f6;
    }
    .bntm-calendar-date.other-month {
        color: #d1d5db;
    }
    .bntm-calendar-date.today {
        border: 2px solid var(--bntm-primary);
        background: #fef3c7;
    }
    .date-number {
        font-weight: 600;
        margin-bottom: 4px;
    }
    .booking-bars {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin-top: 2px;
    }
    .booking-bar {
        height: 6px;
        border-radius: 3px;
        position: relative;
        cursor: pointer;
        transition: all 0.2s;
    }
    .booking-bar:hover {
        height: 8px;
        opacity: 0.9;
    }
    .booking-bar.start {
        border-top-left-radius: 10px;
        border-bottom-left-radius: 10px;
    }
    .booking-bar.end {
        border-top-right-radius: 10px;
        border-bottom-right-radius: 10px;
    }
    .booking-bar.single {
        border-radius: 10px;
    }
    /* Different colors for different bookings */
    .booking-bar.color-0 { background: #3b82f6; }
    .booking-bar.color-1 { background: #10b981; }
    .booking-bar.color-2 { background: #8b5cf6; }
    .booking-bar.color-3 { background: #f59e0b; }
    .booking-bar.color-4 { background: #ec4899; }
    .booking-bar.color-5 { background: #14b8a6; }
    .booking-bar.color-6 { background: #f97316; }
    .booking-bar.color-7 { background: #06b6d4; }
    .booking-bar.color-8 { background: #84cc16; }
    .booking-bar.color-9 { background: #a855f7; }
    
    /* Faded for cancelled bookings */
    .booking-bar.cancelled {
        opacity: 0.5;
        background: #9ca3af !important;
    }
    
    .bntm-booking-list {
        margin-top: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        max-height: 400px;
        overflow-y: auto;
    }
    .bntm-booking-item {
        padding: 12px;
        background: white;
        border-radius: 4px;
        margin-bottom: 8px;
        border-left: 4px solid;
        cursor: pointer;
        transition: transform 0.2s;
    }
    .bntm-booking-item:hover {
        transform: translateX(4px);
    }
    .bntm-booking-item.color-0 { border-left-color: #3b82f6; }
    .bntm-booking-item.color-1 { border-left-color: #10b981; }
    .bntm-booking-item.color-2 { border-left-color: #8b5cf6; }
    .bntm-booking-item.color-3 { border-left-color: #f59e0b; }
    .bntm-booking-item.color-4 { border-left-color: #ec4899; }
    .bntm-booking-item.color-5 { border-left-color: #14b8a6; }
    .bntm-booking-item.color-6 { border-left-color: #f97316; }
    .bntm-booking-item.color-7 { border-left-color: #06b6d4; }
    .bntm-booking-item.color-8 { border-left-color: #84cc16; }
    .bntm-booking-item.color-9 { border-left-color: #a855f7; }
    
    .calendar-legend {
        display: flex;
        gap: 15px;
        margin-top: 15px;
        padding: 10px;
        background: #f9fafb;
        border-radius: 4px;
        font-size: 12px;
        flex-wrap: wrap;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .legend-bar {
        width: 30px;
        height: 6px;
        border-radius: 3px;
    }
    .status-badge {
        font-size: 11px;
        padding: 2px 8px;
        background: #f3f4f6;
        border-radius: 3px;
        text-transform: uppercase;
    }
    </style>
    
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Active Packages</h3>
            <p class="bntm-stat-number"><?php echo $total_packages; ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Bookings</h3>
            <p class="bntm-stat-number"><?php echo $total_bookings; ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Pending Bookings</h3>
            <p class="bntm-stat-number"><?php echo $pending_bookings; ?></p>
        </div>
    </div>
    
    <div class="bntm-form-section bntm-calendar-container">
        <div class="bntm-calendar-header">
            <h3 style="margin: 0;">Bookings Calendar</h3>
            <div class="bntm-calendar-nav">
                <button class="bntm-btn-small" id="prev-month">◀ Prev</button>
                <button class="bntm-btn-small" id="next-month">Next ▶</button>
            </div>
        </div>
        <h4 id="current-month" style="text-align: center; margin-bottom: 15px;"></h4>
        
        <div class="calendar-legend">
            <div class="legend-item">
                <div class="legend-bar" style="background: #3b82f6; border-top-left-radius: 10px; border-bottom-left-radius: 10px;"></div>
                <span>Booking Start</span>
            </div>
            <div class="legend-item">
                <div class="legend-bar" style="background: #3b82f6;"></div>
                <span>In Progress</span>
            </div>
            <div class="legend-item">
                <div class="legend-bar" style="background: #3b82f6; border-top-right-radius: 10px; border-bottom-right-radius: 10px;"></div>
                <span>Booking End</span>
            </div>
            <div class="legend-item">
                <div class="legend-bar" style="background: #fef3c7; border: 2px solid var(--bntm-primary);"></div>
                <span>Today</span>
            </div>
        </div>
        
        <div class="bntm-calendar" id="calendar"></div>
        <div id="selected-bookings" class="bntm-booking-list" style="display: none;"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Booking Form Embed Code</h3>
        <p>Copy and paste this code to embed the booking form on any page:</p>
        <textarea readonly onclick="this.select()" style="width: 100%; height: 100px; font-family: monospace; padding: 10px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 4px;"><?php echo esc_html('<iframe src="' . $booking_url . '" width="100%" height="800" frameborder="0"></iframe>'); ?></textarea>
        <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value); alert('Copied!');" class="bntm-btn-primary" style="margin-top: 10px;">
            Copy Code
        </button>
    </div>
    
    <div class="bntm-form-section">
        <h3>Direct Booking Link</h3>
        <p>Share this link with customers:</p>
        <div style="display: flex; gap: 10px;">
            <input type="text" readonly value="<?php echo esc_attr($booking_url); ?>" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
            <button onclick="navigator.clipboard.writeText('<?php echo esc_js($booking_url); ?>'); alert('Link copied!');" class="bntm-btn-primary">
                Copy Link
            </button>
        </div>
    </div>
    
    <script>
    const bookingsData = <?php echo json_encode($all_bookings); ?>;
    let currentDate = new Date();
    
    // Assign unique colors to bookings
    const bookingColors = {};
    bookingsData.forEach((booking, index) => {
        bookingColors[booking.id] = index % 10; // Cycle through 10 colors
    });
    
    // Function to get all dates between start and end
    function getDateRange(startDate, endDate) {
        const dates = [];
        const current = new Date(startDate + 'T00:00:00');
        const end = new Date(endDate + 'T00:00:00');
        
        while (current <= end) {
            dates.push(current.toISOString().split('T')[0]);
            current.setDate(current.getDate() + 1);
        }
        
        return dates;
    }
    
    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        
        const monthNames = ["January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"];
        
        document.getElementById('current-month').textContent = `${monthNames[month]} ${year}`;
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Group bookings by date with position info
        const bookingsByDate = {};
        
        bookingsData.forEach(booking => {
            if (!booking.start_date || !booking.end_date) return;
            
            const startDate = booking.start_date;
            const endDate = booking.end_date;
            const dateRange = getDateRange(startDate, endDate);
            
            dateRange.forEach((date) => {
                if (!bookingsByDate[date]) {
                    bookingsByDate[date] = [];
                }
                
                const position = date === startDate ? 'start' : 
                               date === endDate ? 'end' : 'middle';
                const isSingle = startDate === endDate;
                
                bookingsByDate[date].push({
                    ...booking,
                    position: isSingle ? 'single' : position,
                    colorIndex: bookingColors[booking.id]
                });
            });
        });
        
        let html = '';
        
        // Day headers
        const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        days.forEach(day => {
            html += `<div class="bntm-calendar-day">${day}</div>`;
        });
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            html += `<div class="bntm-calendar-date other-month">
                <span class="date-number">${daysInPrevMonth - i}</span>
            </div>`;
        }
        
        // Current month days
        const today = new Date();
        const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;
        
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayBookings = bookingsByDate[dateStr] || [];
            const isToday = isCurrentMonth && today.getDate() === day;
            
            let classes = 'bntm-calendar-date';
            if (isToday) classes += ' today';
            
            let barsHtml = '';

            if (dayBookings.length > 0) {
                barsHtml = '<div class="booking-bars">';
                dayBookings.forEach(booking => {
                    const isCancelled = booking.status === 'cancelled';
                    const colorClass = isCancelled ? '' : `color-${booking.colorIndex}`;
                    const cancelledClass = isCancelled ? 'cancelled' : '';
                    barsHtml += `<div class="booking-bar ${booking.position} ${colorClass} ${cancelledClass}" 
                                     data-booking-id="${booking.id}" 
                                     data-date="${dateStr}"
                                     title="${booking.customer_name} - ${booking.package_name}"></div>`;
                });
                barsHtml += '</div>';
            }
            
            html += `<div class="${classes}" data-date="${dateStr}">
                <span class="date-number">${day}</span>
                ${barsHtml}
            </div>`;
        }
        
        // Next month days
        const remainingCells = 42 - (firstDay + daysInMonth);
        for (let i = 1; i <= remainingCells; i++) {
            html += `<div class="bntm-calendar-date other-month">
                <span class="date-number">${i}</span>
            </div>`;
        }
        
        document.getElementById('calendar').innerHTML = html;
        
        // Add click events to booking bars
        document.querySelectorAll('.booking-bar').forEach(el => {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                const bookingId = parseInt(this.dataset.bookingId);
                const date = this.dataset.date;
                showBooking(bookingId, date);
            });
        });
        
        // Add click events to calendar dates
        document.querySelectorAll('.bntm-calendar-date').forEach(el => {
            el.addEventListener('click', function() {
                const date = this.dataset.date;
                if (date) {
                    showBookings(date);
                }
            });
        });
    }
    
    function showBooking(bookingId, highlightDate) {
        const booking = bookingsData.find(b => b.id === bookingId);
        if (!booking) return;
        
        showBookings(highlightDate, bookingId);
    }
    
    function showBookings(date, highlightBookingId = null) {
        const bookings = bookingsData.filter(b => {
            if (!b.start_date || !b.end_date) return false;
            const dateRange = getDateRange(b.start_date, b.end_date);
            return dateRange.includes(date);
        });
        
        const container = document.getElementById('selected-bookings');
        
        if (bookings.length === 0) {
            container.style.display = 'none';
            return;
        }
        
        const dateObj = new Date(date + 'T00:00:00');
        let html = `<h4 style="margin-top: 0;">Bookings on ${dateObj.toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'})}</h4>`;
        
        bookings.forEach(booking => {
            const startDate = new Date(booking.start_date + 'T00:00:00');
            const endDate = new Date(booking.end_date + 'T00:00:00');
            const isStart = booking.start_date === date;
            const isEnd = booking.end_date === date;
            const colorIndex = bookingColors[booking.id];
            const isHighlighted = highlightBookingId === booking.id;
            
            let dateLabel = '';
            if (isStart && isEnd) {
                dateLabel = '<span style="color: #8b5cf6; font-weight: bold;">⬤ Single Day</span>';
            } else if (isStart) {
                dateLabel = '<span style="color: #10b981; font-weight: bold;">▶ Check-in</span>';
            } else if (isEnd) {
                dateLabel = '<span style="color: #ef4444; font-weight: bold;">⬛ Check-out</span>';
            } else {
                dateLabel = '<span style="color: #3b82f6; font-weight: bold;">━ In Progress</span>';
            }
            
            const statusColors = {
                'contacted': '#f59e0b',
                'paid': '#3b82f6',
                'booked': '#10b981',
                'checked_in': '#8b5cf6',
                'checked_out': '#6b7280',
                'completed': '#059669',
                'cancelled': '#ef4444'
            };
            
            const statusColor = statusColors[booking.status] || '#6b7280';
            const highlightStyle = isHighlighted ? 'box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); transform: scale(1.02);' : '';
            
            html += `
                <div class="bntm-booking-item color-${colorIndex}" style="${highlightStyle}">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                        <strong>${booking.customer_name}</strong>
                        ${dateLabel}
                    </div>
                    <div style="margin-bottom: 5px;">
                        ${booking.package_name}
                        <span style="color: #6b7280;"> | ${booking.number_of_pax} pax</span>
                    </div>
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">
                        ${startDate.toLocaleDateString('en-US', {month: 'short', day: 'numeric'})} - 
                        ${endDate.toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'})}
                        (${booking.number_of_days} day${booking.number_of_days > 1 ? 's' : ''})
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: bold;">₱${parseFloat(booking.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        <span class="status-badge" style="background: ${statusColor}; color: white;">
                            ${booking.status.replace('_', ' ')}
                        </span>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        container.style.display = 'block';
        
        // Scroll to bookings list
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    document.getElementById('prev-month').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
        document.getElementById('selected-bookings').style.display = 'none';
    });
    
    document.getElementById('next-month').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
        document.getElementById('selected-bookings').style.display = 'none';
    });
    
    renderCalendar();
    </script>
    <?php
    return ob_get_clean();
}

function cr_packages_tab($business_id) {
    global $wpdb;
    $packages_table = $wpdb->prefix . 'cr_packages';
    
    $packages = $wpdb->get_results("SELECT * FROM $packages_table ORDER BY created_at DESC");
    $nonce = wp_create_nonce('cr_package_nonce');
    $city_choices = bntm_cr_get_city_choices();
    $vehicle_categories = bntm_cr_get_pricing_rules();
    $vehicle_labels = bntm_cr_get_vehicle_category_labels();
    
    ob_start();
    ?>
    <style>
    .package-photo-thumb {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
    }
    .bntm-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow: auto;
    }
    .bntm-modal-content {
        background-color: #fff;
        margin: 50px auto;
        padding: 30px;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .bntm-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e5e7eb;
    }
    .bntm-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #6b7280;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
        width: 30px;
        height: 30px;
        line-height: 1;
    }
    .bntm-modal-close:hover {
        color: #000;
    }
    </style>
    
    <!-- Edit Package Modal -->
    <div id="edit-package-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h3 style="margin: 0;">Edit Package</h3>
                <button class="bntm-modal-close" onclick="closeEditPackageModal()">&times;</button>
            </div>
            <form id="edit-package-form" class="bntm-form" enctype="multipart/form-data">
                <input type="hidden" name="package_id" id="edit-package-id-input">
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Package Name *</label>
                        <input type="text" name="package_name" id="edit-package-name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Vehicle Type / Model *</label>
                        <input type="text" name="boat_type" id="edit-boat-type" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>City *</label>
                        <select name="city" id="edit-city" required>
                            <?php foreach ($city_choices as $city_key => $city_label): ?>
                                <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Vehicle Category *</label>
                        <select name="vehicle_category" id="edit-vehicle-category" required>
                            <?php foreach ($vehicle_labels as $category_key => $category_label): ?>
                                <option value="<?php echo esc_attr($category_key); ?>"><?php echo esc_html($category_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Base Price (₱)</label>
                        <input type="number" name="daily_rate" id="edit-daily-rate" required step="0.01" min="0" readonly>
                        <small>Auto-filled from category settings.</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Overtime Rate Per Hour (₱)</label>
                        <input type="number" name="hourly_surcharge" id="edit-hourly-surcharge" step="0.01" min="0" readonly>
                        <small>Applied after the 10-hour allowance.</small>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Max Passengers *</label>
                    <input type="number" name="max_pax" id="edit-max-pax" required min="1">
                </div>
                
                <div class="bntm-form-group">
                    <label>Car Photo</label>
                    <div id="edit-current-photo" style="margin-bottom: 10px;"></div>
                    <input type="file" name="package_photo" accept="image/*" id="edit-package-photo">
                    <small>Upload new photo (leave empty to keep current)</small>
                    <div id="edit-photo-preview" style="margin-top: 10px; display: none;">
                        <img id="edit-preview-image" style="max-width: 200px; border-radius: 8px;">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit-description" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary" style="flex: 1;">Update Package</button>
                    <button type="button" onclick="closeEditPackageModal()" class="bntm-btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Add New Package</h3>
        <form id="add-package-form" class="bntm-form" enctype="multipart/form-data">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Package Name *</label>
                    <input type="text" name="package_name" required placeholder="e.g., Toyota Vios">
                </div>
                <div class="bntm-form-group">
                    <label>Vehicle Type / Model *</label>
                    <input type="text" name="boat_type" required placeholder="e.g., Sedan">
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>City *</label>
                    <select name="city" id="add-city" required>
                        <?php foreach ($city_choices as $city_key => $city_label): ?>
                            <option value="<?php echo esc_attr($city_key); ?>"><?php echo esc_html($city_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Vehicle Category *</label>
                    <select name="vehicle_category" id="add-vehicle-category" required>
                        <?php foreach ($vehicle_labels as $category_key => $category_label): ?>
                            <option value="<?php echo esc_attr($category_key); ?>"><?php echo esc_html($category_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Base Price (₱)</label>
                    <input type="number" name="daily_rate" id="add-daily-rate" required step="0.01" min="0" readonly>
                    <small>Auto-filled from category pricing.</small>
                </div>
                <div class="bntm-form-group">
                    <label>Overtime Rate Per Hour (₱)</label>
                    <input type="number" name="hourly_surcharge" id="add-hourly-surcharge" step="0.01" min="0" readonly>
                    <small>Applied after the 10-hour allowance.</small>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Max Passengers *</label>
                <input type="number" name="max_pax" required min="1" placeholder="e.g., 5">
            </div>
            
            <div class="bntm-form-group">
                <label>Car Photo</label>
                <input type="file" name="package_photo" accept="image/*" id="package-photo-input">
                <small>Upload a photo of the car (JPG, PNG, max 2MB)</small>
                <div id="photo-preview" style="margin-top: 10px; display: none;">
                    <img id="preview-image" style="max-width: 200px; border-radius: 8px;">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Car features and details"></textarea>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Add Package</button>
        </form>
        <div id="package-message"></div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Existing Packages</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Package Name</th>
                    <th>City</th>
                    <th>Category</th>
                    <th>Vehicle Type</th>
                    <th>Base Price</th>
                    <th>Overtime Rate</th>
                    <th>Max Pax</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($packages)): ?>
                <tr><td colspan="10" style="text-align:center;">No packages yet</td></tr>
                <?php else: foreach ($packages as $pkg): ?>
                <tr>
                    <td>
                        <?php if ($pkg->photo_url): ?>
                            <img src="<?php echo esc_url($pkg->photo_url); ?>" class="package-photo-thumb" alt="Car photo">
                        <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #f3f4f6; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 24px;">🚗</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($pkg->package_name); ?></td>
                    <td><?php echo esc_html($city_choices[bntm_cr_normalize_city($pkg->city ?? 'cebu')] ?? 'Cebu'); ?></td>
                    <td><?php echo esc_html($vehicle_labels[$pkg->vehicle_category ?? 'car'] ?? 'Car'); ?></td>
                    <td><?php echo esc_html($pkg->boat_type); ?></td>
                    <td>₱<?php echo number_format($pkg->daily_rate, 2); ?></td>
                    <td>₱<?php echo number_format($pkg->hourly_surcharge, 2); ?>/hr</td>
                    <td><?php echo $pkg->max_pax; ?> pax</td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $pkg->status; ?>">
                            <?php echo ucfirst($pkg->status); ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small edit-package-btn" 
                                data-id="<?php echo $pkg->id; ?>"
                                data-name="<?php echo esc_attr($pkg->package_name); ?>"
                                data-city="<?php echo esc_attr($pkg->city ?? 'cebu'); ?>"
                                data-category="<?php echo esc_attr($pkg->vehicle_category ?? 'car'); ?>"
                                data-type="<?php echo esc_attr($pkg->boat_type); ?>"
                                data-rate="<?php echo $pkg->daily_rate; ?>"
                                data-surcharge="<?php echo $pkg->hourly_surcharge; ?>"
                                data-pax="<?php echo $pkg->max_pax; ?>"
                                data-photo="<?php echo esc_attr($pkg->photo_url); ?>"
                                data-description="<?php echo esc_attr($pkg->description); ?>"
                                data-status="<?php echo $pkg->status; ?>">
                            Edit
                        </button>
                        <button class="bntm-btn-small toggle-status-btn" data-id="<?php echo $pkg->id; ?>" data-status="<?php echo $pkg->status; ?>" data-nonce="<?php echo $nonce; ?>">
                            <?php echo $pkg->status === 'active' ? 'Deactivate' : 'Activate'; ?>
                        </button>
                        <button class="bntm-btn-small bntm-btn-danger delete-package-btn" data-id="<?php echo $pkg->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    (function() {
        const vehicleCategories = <?php echo wp_json_encode($vehicle_categories); ?>;

        function syncPackagePricing(categoryFieldId, rateFieldId, overtimeFieldId) {
            const select = document.getElementById(categoryFieldId);
            const rateField = document.getElementById(rateFieldId);
            const overtimeField = document.getElementById(overtimeFieldId);
            if (!select || !rateField || !overtimeField) return;

            const rule = vehicleCategories[select.value] || vehicleCategories.car;
            rateField.value = parseFloat(rule.base_fee || 0).toFixed(2);
            overtimeField.value = parseFloat(rule.overtime_rate || 0).toFixed(2);
        }

        document.getElementById('add-vehicle-category').addEventListener('change', function() {
            syncPackagePricing('add-vehicle-category', 'add-daily-rate', 'add-hourly-surcharge');
        });

        document.getElementById('edit-vehicle-category').addEventListener('change', function() {
            syncPackagePricing('edit-vehicle-category', 'edit-daily-rate', 'edit-hourly-surcharge');
        });

        syncPackagePricing('add-vehicle-category', 'add-daily-rate', 'add-hourly-surcharge');

        // Photo preview for add form
        document.getElementById('package-photo-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-image').src = e.target.result;
                    document.getElementById('photo-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Photo preview for edit form
        document.getElementById('edit-package-photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('edit-preview-image').src = e.target.result;
                    document.getElementById('edit-photo-preview').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Edit package button
        document.querySelectorAll('.edit-package-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('edit-package-id-input').value = this.dataset.id;
                document.getElementById('edit-package-name').value = this.dataset.name;
                document.getElementById('edit-city').value = this.dataset.city || 'cebu';
                document.getElementById('edit-vehicle-category').value = this.dataset.category || 'car';
                document.getElementById('edit-boat-type').value = this.dataset.type;
                syncPackagePricing('edit-vehicle-category', 'edit-daily-rate', 'edit-hourly-surcharge');
                document.getElementById('edit-max-pax').value = this.dataset.pax;
                document.getElementById('edit-description').value = this.dataset.description;
                
                // Show current photo
                const currentPhotoDiv = document.getElementById('edit-current-photo');
                if (this.dataset.photo) {
                    currentPhotoDiv.innerHTML = `
                        <div style="margin-bottom: 10px;">
                            <strong>Current Photo:</strong><br>
                            <img src="${this.dataset.photo}" style="max-width: 200px; border-radius: 8px; margin-top: 5px;">
                        </div>
                    `;
                } else {
                    currentPhotoDiv.innerHTML = '<p style="color: #6b7280;">No photo uploaded</p>';
                }
                
                document.getElementById('edit-photo-preview').style.display = 'none';
                document.getElementById('edit-package-modal').style.display = 'block';
            });
        });
        
        // Submit edit form
        document.getElementById('edit-package-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'cr_update_package_full');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Updating...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert('Package updated successfully!');
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Update Package';
                }
            });
        });
        
        // Add package
        document.getElementById('add-package-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'cr_add_package');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                document.getElementById('package-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                if (json.success) {
                    setTimeout(() => location.reload(), 1000);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Add Package';
                }
            });
        });
        
        // Toggle status
        document.querySelectorAll('.toggle-status-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const newStatus = this.dataset.status === 'active' ? 'inactive' : 'active';
                const formData = new FormData();
                formData.append('action', 'cr_update_package');
                formData.append('package_id', this.dataset.id);
                formData.append('status', newStatus);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
        
        // Delete package
        document.querySelectorAll('.delete-package-btn').forEach(btn => {
            btn.addEventListener('click', function() {  
                if (!confirm('Delete this package?')) return;
                
                const formData = new FormData();
                formData.append('action', 'cr_delete_package');
                formData.append('package_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
    })();
    
    function closeEditPackageModal() {
        document.getElementById('edit-package-modal').style.display = 'none';
    }
    
    window.onclick = function(event) {
        const modal = document.getElementById('edit-package-modal');
        if (event.target == modal) {
            closeEditPackageModal();
        }
    }
    </script>
    <?php
    return ob_get_clean();
}

function cr_bookings_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';

    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';

    $where  = 'b.business_id = %d';
    $params = [$business_id];
    if ($filter_status !== 'all') {
        $where   .= ' AND b.status = %s';
        $params[] = $filter_status;
    }

    $bookings = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.package_name, p.boat_type, p.daily_rate as pkg_daily_rate,
               p.hourly_surcharge as pkg_hourly_surcharge,
               COALESCE(b.package_amount, b.total_amount) as package_amount
        FROM {$bookings_table} b
        LEFT JOIN {$packages_table} p ON b.package_id = p.id
        WHERE {$where}
        ORDER BY b.created_at DESC
        LIMIT 50
    ", $params));

    $packages = $wpdb->get_results("SELECT * FROM {$packages_table} WHERE status='active' ORDER BY package_name");
    $nonce    = wp_create_nonce('cr_booking_nonce');

    ob_start();
    ?>
    <style>
    .cr-status-badge{display:inline-block;padding:3px 10px;border-radius:4px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.3px;}
    .cr-status-contacted  {background:#fefce8;color:#854d0e;border:1px solid #fde68a;}
    .cr-status-paid       {background:#f0fdf4;color:#166534;border:1px solid #86efac;}
    .cr-status-booked     {background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;}
    .cr-status-checked_in {background:#f5f3ff;color:#5b21b6;border:1px solid #ddd6fe;}
    .cr-status-checked_out{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
    .cr-status-cancelled  {background:#f9fafb;color:#6b7280;border:1px solid #d1d5db;}
    .cr-status-completed  {background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;}
    .cr-filter-select{padding:8px 12px;border:1px solid #d1d5db;border-radius:4px;font-size:14px;}
    </style>

    <!-- ── Edit Modal ─────────────────────────────────────────────────────── -->
    <div id="crEditModal" class="bntm-modal">
        <div class="bntm-modal-content" style="max-width:660px;">
            <div class="bntm-modal-header">
                <h3 style="margin:0;">Edit Booking</h3>
                <button class="bntm-modal-close" onclick="closeModal('crEditModal')">&times;</button>
            </div>

            <div style="max-height:75vh;overflow-y:auto;padding-right:4px;">

                <!-- Package -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Package</p>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Package *</label>
                    <select id="cre-package-id" class="bntm-input">
                        <?php foreach ($packages as $pkg): ?>
                        <option value="<?php echo $pkg->id; ?>"
                                data-daily-rate="<?php echo $pkg->daily_rate; ?>"
                                data-hourly-surcharge="<?php echo $pkg->hourly_surcharge; ?>"
                                data-max-pax="<?php echo $pkg->max_pax; ?>">
                            <?php echo esc_html($pkg->package_name); ?> — <?php echo esc_html($pkg->boat_type); ?>
                            (₱<?php echo number_format($pkg->daily_rate, 2); ?>/day)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Customer -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Customer</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Full Name</label>
                        <input type="text" id="cre-customer-name" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Email</label>
                        <input type="email" id="cre-customer-email" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Phone</label>
                        <input type="tel" id="cre-customer-phone" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Number of Pax</label>
                        <input type="number" id="cre-num-pax" class="bntm-input" min="1">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Dates -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Rental Dates</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Start Date</label>
                        <input type="date" id="cre-start-date" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>End Date</label>
                        <input type="date" id="cre-end-date" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Number of Days</label>
                        <input type="text" id="cre-num-days" class="bntm-input"
                               readonly style="background:#f9fafb;color:#6b7280;">
                    </div>
                    <div class="bntm-form-group" style="margin:0;"></div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Check-in Time</label>
                        <input type="datetime-local" id="cre-checkin" class="bntm-input">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Check-out Time</label>
                        <input type="datetime-local" id="cre-checkout" class="bntm-input">
                    </div>
                </div>

                <!-- Surcharge display -->
                <div id="cre-surcharge-wrap" style="display:none;margin-bottom:16px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="bntm-form-group" style="margin:0;">
                            <label>Excess Hours</label>
                            <input type="text" id="cre-excess-hours" class="bntm-input"
                                   readonly style="background:#fefce8;color:#854d0e;">
                        </div>
                        <div class="bntm-form-group" style="margin:0;">
                            <label>Surcharge Amount (₱)</label>
                            <input type="text" id="cre-surcharge-amt" class="bntm-input"
                                   readonly style="background:#fefce8;color:#854d0e;">
                        </div>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Pricing -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Pricing</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Package Amount (₱) <small style="font-weight:400;color:#9ca3af;">rate × days</small></label>
                        <input type="text" id="cre-package-amount" class="bntm-input"
                               readonly style="background:#f9fafb;color:#6b7280;">
                    </div>
                    <div class="bntm-form-group" style="margin:0;">
                        <label>Grand Total (₱)</label>
                        <input type="text" id="cre-grand-total" class="bntm-input"
                               readonly style="background:#f9fafb;color:#166534;font-weight:700;">
                    </div>
                </div>

                <!-- Other Fees -->
                <div style="margin-bottom:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0;">Other Fees</p>
                        <button type="button" onclick="creAddFeeRow()"
                                style="padding:4px 12px;background:var(--bntm-primary);color:#fff;border:none;border-radius:4px;font-size:12px;font-weight:600;cursor:pointer;">
                            + Add Fee
                        </button>
                    </div>
                    <div id="cre-fees-list" style="margin-bottom:8px;"></div>
                    <div style="display:flex;justify-content:flex-end;gap:16px;padding:8px 10px;background:#f9fafb;border-radius:6px;font-size:13px;">
                        <span style="color:#6b7280;">Fees Total:</span>
                        <strong id="cre-fees-total" style="color:var(--bntm-primary);">₱0.00</strong>
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Status -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Status</p>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Booking Status</label>
                    <select id="cre-status" class="bntm-input">
                        <option value="contacted">Contacted</option>
                        <option value="paid">Paid – Down Payment</option>
                        <option value="booked">Booked</option>
                        <option value="checked_in">Checked In</option>
                        <option value="checked_out">Checked Out</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 16px;">

                <!-- Notes -->
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#9ca3af;margin:0 0 10px;">Notes</p>
                <div class="bntm-form-group" style="margin-bottom:16px;">
                    <label>Notes</label>
                    <textarea id="cre-notes" rows="3" class="bntm-input"></textarea>
                </div>

                <div id="cr-edit-msg" style="margin-bottom:10px;"></div>

                <div style="display:flex;gap:10px;">
                    <button type="button" class="bntm-btn-secondary" style="flex:1;"
                            onclick="closeModal('crEditModal')">Cancel</button>
                    <button type="button" class="bntm-btn-primary" style="flex:1;"
                            id="cr-edit-save-btn" onclick="creSave()">Save Changes</button>
                </div>

            </div><!-- scroll wrapper -->
        </div>
    </div>

    <!-- ── Filter + Table ─────────────────────────────────────────────────── -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <h3 style="margin:0;">All Bookings</h3>
        <select onchange="window.location.href='?tab=bookings&status='+this.value" class="cr-filter-select">
            <option value="all"         <?php selected($filter_status,'all'); ?>>All Bookings</option>
            <option value="contacted"   <?php selected($filter_status,'contacted'); ?>>Contacted</option>
            <option value="paid"        <?php selected($filter_status,'paid'); ?>>Paid</option>
            <option value="booked"      <?php selected($filter_status,'booked'); ?>>Booked</option>
            <option value="checked_in"  <?php selected($filter_status,'checked_in'); ?>>Checked In</option>
            <option value="checked_out" <?php selected($filter_status,'checked_out'); ?>>Checked Out</option>
            <option value="completed"   <?php selected($filter_status,'completed'); ?>>Completed</option>
            <option value="cancelled"   <?php selected($filter_status,'cancelled'); ?>>Cancelled</option>
        </select>
    </div>

    <?php if (empty($bookings)): ?>
        <div style="text-align:center;padding:40px;color:#6b7280;"><p>No bookings found.</p></div>
    <?php else: ?>
    <table class="bntm-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Package</th>
                <th>Rental Period</th>
                <th>Days</th>
                <th>Pax</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td>#<?php echo $b->id; ?></td>
                <td>
                    <?php echo esc_html($b->customer_name); ?><br>
                    <small style="color:#6b7280;"><?php echo esc_html($b->customer_email); ?></small>
                </td>
                <td>
                    <?php echo esc_html($b->package_name); ?><br>
                    <small style="color:#6b7280;"><?php echo esc_html($b->boat_type); ?></small>
                </td>
                <td>
                    <?php echo date('M d, Y', strtotime($b->start_date)); ?><br>
                    <small style="color:#6b7280;">to <?php echo date('M d, Y', strtotime($b->end_date)); ?></small>
                </td>
                <td><?php echo $b->number_of_days; ?> day<?php echo $b->number_of_days > 1 ? 's' : ''; ?></td>
                <td><?php echo $b->number_of_pax; ?></td>
                <td>₱<?php echo number_format($b->total_amount, 2); ?></td>
                <td>
                    <span class="cr-status-badge cr-status-<?php echo esc_attr($b->status); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $b->status)); ?>
                    </span>
                </td>
                <td>
                    <a href="<?php echo get_permalink(get_page_by_path('car-booking-invoice')) . '?id=' . $b->rand_id; ?>"
                       class="bntm-btn-small" target="_blank">View</a>
                    <?php if (in_array($b->status, ['contacted', 'paid'], true)): ?>
                    <button class="bntm-btn-small cr-quick-status-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-status="booked"
                            data-label="confirm"
                            data-nonce="<?php echo $nonce; ?>">
                        Confirm
                    </button>
                    <?php endif; ?>
                    <?php if ($b->status !== 'cancelled' && $b->status !== 'completed'): ?>
                    <button class="bntm-btn-small bntm-btn-danger cr-quick-status-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-status="cancelled"
                            data-label="cancel"
                            data-nonce="<?php echo $nonce; ?>">
                        Cancel
                    </button>
                    <?php endif; ?>
                    <button class="bntm-btn-small cr-edit-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-package-id="<?php echo $b->package_id; ?>"
                            data-name="<?php echo esc_attr($b->customer_name); ?>"
                            data-email="<?php echo esc_attr($b->customer_email); ?>"
                            data-phone="<?php echo esc_attr($b->customer_phone); ?>"
                            data-pax="<?php echo $b->number_of_pax; ?>"
                            data-start-date="<?php echo esc_attr($b->start_date); ?>"
                            data-end-date="<?php echo esc_attr($b->end_date); ?>"
                            data-days="<?php echo intval($b->number_of_days); ?>"
                            data-checkin="<?php echo esc_attr($b->check_in_time ?? ''); ?>"
                            data-checkout="<?php echo esc_attr($b->check_out_time ?? ''); ?>"
                            data-excess-hours="<?php echo floatval($b->excess_hours ?? 0); ?>"
                            data-surcharge="<?php echo floatval($b->surcharge_amount ?? 0); ?>"
                            data-daily-rate="<?php echo floatval($b->daily_rate ?? $b->pkg_daily_rate ?? 0); ?>"
                            data-hourly-surcharge="<?php echo floatval($b->pkg_hourly_surcharge ?? 0); ?>"
                            data-package-amount="<?php echo floatval($b->package_amount); ?>"
                            data-total="<?php echo floatval($b->total_amount); ?>"
                            data-status="<?php echo esc_attr($b->status); ?>"
                            data-notes="<?php echo esc_attr($b->notes ?? ''); ?>"
                            data-other-fees='<?php echo esc_attr($b->other_fees ?? '[]'); ?>'
                            data-nonce="<?php echo $nonce; ?>">Edit</button>
                    <button class="bntm-btn-small bntm-btn-danger cr-delete-btn"
                            data-id="<?php echo $b->id; ?>"
                            data-nonce="<?php echo $nonce; ?>">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <script>
    let creBookingId = null;
    let creNonce     = null;
    let creDailyRate = 0;
    let creHourlySurcharge = 0;

    // ── Open modal ──────────────────────────────────────────────────────────
    document.querySelectorAll('.cr-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;
            creBookingId      = d.id;
            creNonce          = d.nonce;
            creDailyRate      = parseFloat(d.dailyRate      ?? 0);
            creHourlySurcharge= parseFloat(d.hourlySurcharge ?? 0);

            // Package
            document.getElementById('cre-package-id').value = d.packageId;

            // Customer
            document.getElementById('cre-customer-name').value  = d.name  ?? '';
            document.getElementById('cre-customer-email').value = d.email ?? '';
            document.getElementById('cre-customer-phone').value = d.phone ?? '';
            document.getElementById('cre-num-pax').value        = d.pax   ?? 1;

            // Dates
            document.getElementById('cre-start-date').value = d.startDate ?? '';
            document.getElementById('cre-end-date').value   = d.endDate   ?? '';
            document.getElementById('cre-num-days').value   = d.days + ' day' + (d.days > 1 ? 's' : '');

            const ci = d.checkin  ?? '';
            const co = d.checkout ?? '';
            document.getElementById('cre-checkin').value  = ci && ci !== '0000-00-00 00:00:00'
                ? ci.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('cre-checkout').value = co && co !== '0000-00-00 00:00:00'
                ? co.replace(' ', 'T').substring(0, 16) : '';

            // Pricing
            document.getElementById('cre-package-amount').value =
                parseFloat(d.packageAmount ?? 0).toFixed(2);

            // Existing surcharge
            const exH  = parseFloat(d.excessHours ?? 0);
            const exS  = parseFloat(d.surcharge   ?? 0);
            const wrap = document.getElementById('cre-surcharge-wrap');
            if (exH > 0 && exS > 0) {
                document.getElementById('cre-excess-hours').value = exH.toFixed(2) + ' hrs';
                document.getElementById('cre-surcharge-amt').value = exS.toFixed(2);
                wrap.style.display = 'block';
            } else {
                wrap.style.display = 'none';
                document.getElementById('cre-excess-hours').value  = '';
                document.getElementById('cre-surcharge-amt').value = '0';
            }

            // Fees
            document.getElementById('cre-fees-list').innerHTML = '';
            try {
                const fees = JSON.parse(d.otherFees || '[]');
                (Array.isArray(fees) ? fees : []).forEach(f => creAddFeeRow(f.description, f.amount));
            } catch(e) {}

            // Status / notes
            document.getElementById('cre-status').value = d.status ?? 'contacted';
            document.getElementById('cre-notes').value  = d.notes  ?? '';

            // Reset UI
            document.getElementById('cr-edit-msg').innerHTML       = '';
            document.getElementById('cr-edit-save-btn').disabled   = false;
            document.getElementById('cr-edit-save-btn').textContent = 'Save Changes';

            creRecalcTotal();
            openModal('crEditModal');
        });
    });

    // ── Package change ──────────────────────────────────────────────────────
    document.getElementById('cre-package-id').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        creDailyRate       = parseFloat(opt.dataset.dailyRate       ?? 0);
        creHourlySurcharge = parseFloat(opt.dataset.hourlyS ?? opt.dataset.hourlySurcharge ?? 0);
        creRecalcDays();
    });

    // ── Date changes ────────────────────────────────────────────────────────
    ['cre-start-date','cre-end-date'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => {
            // Enforce min on end date
            const s = document.getElementById('cre-start-date').value;
            const eEl = document.getElementById('cre-end-date');
            if (s) { eEl.min = s; if (eEl.value && eEl.value < s) eEl.value = s; }
            creRecalcDays();
        })
    );

    function creRecalcDays() {
        const s = document.getElementById('cre-start-date').value;
        const e = document.getElementById('cre-end-date').value;
        if (!s || !e) return;
        const diff = Math.ceil((new Date(e) - new Date(s)) / 86400000) + 1;
        const days = Math.max(1, diff);
        document.getElementById('cre-num-days').value = days + ' day' + (days > 1 ? 's' : '');
        document.getElementById('cre-package-amount').value =
            (creDailyRate * days).toFixed(2);
        creRecalcSurcharge();
        creRecalcTotal();
    }

    // ── Surcharge ───────────────────────────────────────────────────────────
    ['cre-checkin','cre-checkout'].forEach(id =>
        document.getElementById(id).addEventListener('change', creRecalcSurcharge)
    );

    function creRecalcSurcharge() {
        const ci   = document.getElementById('cre-checkin').value;
        const co   = document.getElementById('cre-checkout').value;
        const wrap = document.getElementById('cre-surcharge-wrap');

        if (!ci || !co) { wrap.style.display = 'none'; creRecalcTotal(); return; }

        const diffH = (new Date(co) - new Date(ci)) / 3600000;
        // 24 hrs allowed per calendar day
        const daysStr = document.getElementById('cre-num-days').value;
        const days    = parseInt(daysStr) || 1;
        const allowed = 24 * days;
        const excess  = Math.max(0, diffH - allowed);

        if (excess > 0 && creHourlySurcharge > 0) {
            const surcharge = Math.ceil(excess) * creHourlySurcharge;
            document.getElementById('cre-excess-hours').value  = excess.toFixed(2) + ' hrs';
            document.getElementById('cre-surcharge-amt').value = surcharge.toFixed(2);
            wrap.style.display = 'block';
        } else {
            document.getElementById('cre-excess-hours').value  = '';
            document.getElementById('cre-surcharge-amt').value = '0';
            wrap.style.display = 'none';
        }
        creRecalcTotal();
    }

    // ── Fee rows ────────────────────────────────────────────────────────────
    function creAddFeeRow(desc, amt) {
        desc = desc ?? ''; amt = amt ?? '';
        const list = document.getElementById('cre-fees-list');
        const row  = document.createElement('div');
        row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 32px;gap:8px;margin-bottom:8px;align-items:center;';
        row.innerHTML = `
            <input type="text"   class="bntm-input cre-fee-desc" placeholder="Description"
                   value="${String(desc).replace(/"/g,'&quot;')}" style="font-size:13px;padding:7px 9px;">
            <input type="number" class="bntm-input cre-fee-amt"  placeholder="Amount"
                   value="${parseFloat(amt)||''}" min="0" step="0.01" style="font-size:13px;padding:7px 9px;">
            <button type="button"
                    onclick="this.closest('div').remove(); creRecalcTotal();"
                    style="width:32px;height:32px;border:1px solid #fca5a5;background:#fff5f5;color:#ef4444;border-radius:4px;font-size:16px;cursor:pointer;">×</button>
        `;
        row.querySelector('.cre-fee-amt').addEventListener('input', creRecalcTotal);
        list.appendChild(row);
        creRecalcTotal();
    }
    window.creAddFeeRow = creAddFeeRow;

    function creRecalcTotal() {
        let fees = 0;
        document.querySelectorAll('.cre-fee-amt').forEach(i => fees += parseFloat(i.value) || 0);
        document.getElementById('cre-fees-total').textContent =
            '₱' + fees.toLocaleString('en-US', {minimumFractionDigits:2});

        const pkg      = parseFloat(document.getElementById('cre-package-amount').value) || 0;
        const surcharge= parseFloat(document.getElementById('cre-surcharge-amt')?.value) || 0;
        document.getElementById('cre-grand-total').value =
            (pkg + fees + surcharge).toFixed(2);
    }

    // ── Save ────────────────────────────────────────────────────────────────
    function creSave() {
        const btn = document.getElementById('cr-edit-save-btn');
        const msg = document.getElementById('cr-edit-msg');
        btn.disabled = true; btn.textContent = 'Saving…'; msg.innerHTML = '';

        // Collect fees
        const fees = [];
        document.querySelectorAll('#cre-fees-list > div').forEach(row => {
            const desc = row.querySelector('.cre-fee-desc').value.trim();
            const amt  = parseFloat(row.querySelector('.cre-fee-amt').value) || 0;
            if (desc) fees.push({description: desc, amount: amt});
        });

        // Parse days from display string "3 days"
        const daysStr = document.getElementById('cre-num-days').value;
        const days    = parseInt(daysStr) || 1;

        // Parse excess hours
        const exHStr  = document.getElementById('cre-excess-hours').value;
        const exH     = parseFloat(exHStr) || 0;
        const exS     = parseFloat(document.getElementById('cre-surcharge-amt').value) || 0;

        const fd = new FormData();
        fd.append('action',          'cr_edit_booking');
        fd.append('nonce',           creNonce);
        fd.append('booking_id',      creBookingId);
        fd.append('package_id',      document.getElementById('cre-package-id').value);
        fd.append('customer_name',   document.getElementById('cre-customer-name').value.trim());
        fd.append('customer_email',  document.getElementById('cre-customer-email').value.trim());
        fd.append('customer_phone',  document.getElementById('cre-customer-phone').value.trim());
        fd.append('number_of_pax',   document.getElementById('cre-num-pax').value);
        fd.append('start_date',      document.getElementById('cre-start-date').value);
        fd.append('end_date',        document.getElementById('cre-end-date').value);
        fd.append('number_of_days',  days);
        fd.append('check_in_time',   document.getElementById('cre-checkin').value);
        fd.append('check_out_time',  document.getElementById('cre-checkout').value);
        fd.append('excess_hours',    exH);
        fd.append('surcharge_amount',exS);
        fd.append('package_amount',  document.getElementById('cre-package-amount').value);
        fd.append('total_amount',    document.getElementById('cre-grand-total').value);
        fd.append('other_fees',      JSON.stringify(fees));
        fd.append('status',          document.getElementById('cre-status').value);
        fd.append('notes',           document.getElementById('cre-notes').value);

        fetch(ajaxurl, {method:'POST', body:fd})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';

                    // Update table row inline
                    const row = document.querySelector(`.cr-edit-btn[data-id="${creBookingId}"]`)?.closest('tr');
                    if (row) {
                        const newStatus = document.getElementById('cre-status').value;
                        const labels = {
                            contacted:'Contacted', paid:'Paid', booked:'Booked',
                            checked_in:'Checked In', checked_out:'Checked Out',
                            cancelled:'Cancelled', completed:'Completed'
                        };
                        const badge = row.querySelector('.cr-status-badge');
                        if (badge) {
                            badge.textContent = labels[newStatus] ?? newStatus;
                            badge.className   = 'cr-status-badge cr-status-' + newStatus;
                        }
                        const cells = row.querySelectorAll('td');
                        if (cells[1]) cells[1].innerHTML =
                            document.getElementById('cre-customer-name').value +
                            '<br><small style="color:#6b7280;">' +
                            document.getElementById('cre-customer-email').value + '</small>';
                        if (cells[6]) cells[6].textContent =
                            '₱' + parseFloat(document.getElementById('cre-grand-total').value)
                                    .toLocaleString('en-US', {minimumFractionDigits:2});

                        // Sync data-attrs for re-opening
                        const eb = row.querySelector('.cr-edit-btn');
                        if (eb) {
                            eb.dataset.status        = newStatus;
                            eb.dataset.name          = document.getElementById('cre-customer-name').value;
                            eb.dataset.email         = document.getElementById('cre-customer-email').value;
                            eb.dataset.phone         = document.getElementById('cre-customer-phone').value;
                            eb.dataset.pax           = document.getElementById('cre-num-pax').value;
                            eb.dataset.startDate     = document.getElementById('cre-start-date').value;
                            eb.dataset.endDate       = document.getElementById('cre-end-date').value;
                            eb.dataset.days          = days;
                            eb.dataset.packageAmount = document.getElementById('cre-package-amount').value;
                            eb.dataset.total         = document.getElementById('cre-grand-total').value;
                            eb.dataset.notes         = document.getElementById('cre-notes').value;
                            eb.dataset.otherFees     = JSON.stringify(fees);
                            eb.dataset.excessHours   = exH;
                            eb.dataset.surcharge     = exS;
                        }
                    }
                    btn.disabled = false; btn.textContent = 'Save Changes';
                } else {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + (json.data?.message ?? 'Error') + '</div>';
                    btn.disabled = false; btn.textContent = 'Save Changes';
                }
            })
            .catch(() => {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Network error. Please try again.</div>';
                btn.disabled = false; btn.textContent = 'Save Changes';
            });
    }
    window.creSave = creSave;

    // ── Delete ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.cr-delete-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this booking? This cannot be undone.')) return;
            const fd = new FormData();
            fd.append('action',     'cr_delete_booking');
            fd.append('booking_id', this.dataset.id);
            fd.append('nonce',      this.dataset.nonce);
            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(json => { alert(json.data.message); if (json.success) location.reload(); });
        });
    });

    document.querySelectorAll('.cr-quick-status-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const isCancel = this.dataset.status === 'cancelled';
            const actionText = isCancel ? 'cancel' : 'confirm';
            if (!confirm(`Are you sure you want to ${actionText} this booking?`)) return;

            const fd = new FormData();
            fd.append('action', 'cr_update_booking_status');
            fd.append('booking_id', this.dataset.id);
            fd.append('status', this.dataset.status);
            fd.append('nonce', this.dataset.nonce);

            fetch(ajaxurl, {method:'POST', body:fd})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.data.message || 'Failed to update booking status.');
                    }
                })
                .catch(() => {
                    alert('Network error. Please try again.');
                });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

function cr_settings_tab($business_id) {
    $payment_source = bntm_get_setting('cr_payment_source', 'manual');
    $manual_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
    if (!is_array($manual_methods)) $manual_methods = [];
    $pricing_rules = bntm_cr_get_pricing_rules();
    $route_lines = bntm_cr_routes_to_text(bntm_cr_get_routes());
    $routes_data = bntm_cr_get_routes();
    
    $nonce = wp_create_nonce('cr_payment_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Booking Settings</h3>

        <div style="margin-bottom: 20px;">
            <h4 style="margin-bottom: 12px;">Vehicle Pricing Rules</h4>
            <input type="hidden" id="pricing-rules-json" value="">
            <div style="overflow-x:auto;">
                <table class="bntm-table" style="margin-bottom:12px;">
                    <thead>
                        <tr>
                            <th>Slug</th>
                            <th>Label</th>
                            <th>Base Fee</th>
                            <th>Rate / KM</th>
                            <th>Overtime / Hour</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cr-pricing-rules-body">
                        <?php foreach ($pricing_rules as $rule_slug => $rule): ?>
                        <tr>
                            <td><input type="text" class="cr-pricing-slug" value="<?php echo esc_attr($rule_slug); ?>"></td>
                            <td><input type="text" class="cr-pricing-label" value="<?php echo esc_attr($rule['label']); ?>"></td>
                            <td><input type="number" class="cr-pricing-base-fee" min="0" step="0.01" value="<?php echo esc_attr($rule['base_fee']); ?>"></td>
                            <td><input type="number" class="cr-pricing-rate-km" min="0" step="0.01" value="<?php echo esc_attr($rule['rate_per_km']); ?>"></td>
                            <td><input type="number" class="cr-pricing-overtime" min="0" step="0.01" value="<?php echo esc_attr($rule['overtime_rate']); ?>"></td>
                            <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-pricing-row">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="cr-add-pricing-row" class="bntm-btn-secondary">Add Pricing Rule</button>
            <small>Add or remove vehicle pricing rules here. Slug should be unique, like <code>sedan</code> or <code>luxury_van</code>.</small>
        </div>

        <div class="bntm-form-group">
            <label>Base Point Routes</label>
            <input type="hidden" id="route-definitions" value="<?php echo esc_attr($route_lines); ?>">
            <div style="overflow-x:auto;">
                <table class="bntm-table" style="margin-bottom:12px;">
                    <thead>
                        <tr>
                            <th>City</th>
                            <th>Base Point</th>
                            <th>Destination</th>
                            <th>KM</th>
                            <th>Car Fixed</th>
                            <th>Van Fixed</th>
                            <th>SUV Fixed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="cr-routes-table-body">
                        <?php foreach ($routes_data as $route): ?>
                        <tr>
                            <td>
                                <select class="cr-route-city">
                                    <option value="cebu" <?php selected($route['city'], 'cebu'); ?>>Cebu</option>
                                    <option value="cdo" <?php selected($route['city'], 'cdo'); ?>>CDO</option>
                                </select>
                            </td>
                            <td><input type="text" class="cr-route-base" value="<?php echo esc_attr($route['base_point']); ?>"></td>
                            <td><input type="text" class="cr-route-destination" value="<?php echo esc_attr($route['destination']); ?>"></td>
                            <td><input type="number" class="cr-route-distance" min="0" step="0.01" value="<?php echo esc_attr($route['distance_km']); ?>"></td>
                            <td><input type="number" class="cr-route-car-fixed" min="0" step="0.01" value="<?php echo esc_attr($route['fixed_rates']['car'] ?? 0); ?>"></td>
                            <td><input type="number" class="cr-route-van-fixed" min="0" step="0.01" value="<?php echo esc_attr($route['fixed_rates']['van_innova'] ?? 0); ?>"></td>
                            <td><input type="number" class="cr-route-suv-fixed" min="0" step="0.01" value="<?php echo esc_attr($route['fixed_rates']['suv_grandia'] ?? 0); ?>"></td>
                            <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-route-row">Remove</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="cr-add-route-row" class="bntm-btn-secondary">Add Route Row</button>
            <small>Manage routes here instead of typing route lines manually. The KM in this table will be used for pricing.</small>
        </div>
        
        <div class="bntm-form-group">
            <label>Down Payment Percentage (%)</label>
            <input type="number" 
                   id="downpayment-percentage" 
                   min="0" 
                   max="100" 
                   step="1"
                   value="<?php echo esc_attr(bntm_get_setting('cr_downpayment_percentage', '50')); ?>"
                   placeholder="e.g., 50">
            <small>Set the required down payment percentage (0-100%). Leave 0 for full payment only.</small>
        </div>
        
        <div class="bntm-form-group">
            <label>Terms & Conditions</label>
            <textarea id="booking-terms" 
                      rows="8" 
                      placeholder="Enter terms and conditions to display on invoice"><?php echo esc_textarea(bntm_get_setting('cr_terms', '')); ?></textarea>
            <small>These terms will be displayed at the bottom of booking invoices.</small>
        </div>
        
        <button type="button" id="save-booking-settings-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
            Save Booking Settings
        </button>
        <div id="booking-settings-message"></div>
    </div>
    <div class="bntm-form-section">
        <h3>Payment Configuration</h3>
        
        <div class="bntm-form-group">
            <label>Payment Source</label>
            <select id="payment-source-select">
                <option value="manual" <?php selected($payment_source, 'manual'); ?>>
                    Manual Payment Methods
                </option>
                <?php if (bntm_is_module_enabled('op') && bntm_is_module_visible('op')): ?>
                    <option value="op" <?php selected($payment_source, 'op'); ?>>
                        Online Payment Module (PayPal, PayMaya, etc.)
                    </option>
                <?php else: ?>
                    <option value="op" disabled>
                        Online Payment Module (Requires OP Module)
                    </option>
                <?php endif; ?>
            </select>
        </div>
        
        <button type="button" id="save-payment-source-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
            Save Payment Source
        </button>
        <div id="payment-source-message"></div>
    </div>
    
    <div class="bntm-form-section" id="manual-payment-section" style="<?php echo $payment_source === 'op' ? 'display: none;' : ''; ?>">
        <h3>Manual Payment Methods</h3>
        
        <div id="payment-methods-list" style="margin-bottom: 20px;">
            <?php if (empty($manual_methods)): ?>
                <p style="color: #6b7280;">No payment methods configured.</p>
            <?php else: ?>
                <?php foreach ($manual_methods as $index => $method): ?>
                    <div style="padding: 15px; background: #f9fafb; border-radius: 8px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($method['name']); ?></strong>
                                <span style="color: #6b7280; margin-left: 10px;">
                                    <?php echo esc_html($method['type']); ?>
                                </span>
                            </div>
                            <button class="bntm-btn-small bntm-btn-danger remove-payment-method" 
                                    data-index="<?php echo $index; ?>" 
                                    data-nonce="<?php echo $nonce; ?>">
                                Remove
                            </button>
                        </div>
                        <?php if (!empty($method['account_name']) || !empty($method['account_number'])): ?>
                            <div style="margin-top: 8px; font-size: 13px; color: #6b7280;">
                                <?php if (!empty($method['account_name'])): ?>
                                    Account: <?php echo esc_html($method['account_name']); ?><br>
                                <?php endif; ?>
                                <?php if (!empty($method['account_number'])): ?>
                                    Number: <?php echo esc_html($method['account_number']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="padding: 20px; background: #f9fafb; border-radius: 8px;">
            <h4>Add Payment Method</h4>
            <form id="add-payment-method-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Payment Type *</label>
                    <select name="payment_type" required>
                        <option value="">Select Type</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="cash">Cash Payment</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Display Name *</label>
                    <input type="text" name="payment_name" required placeholder="e.g., BDO Bank Transfer">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Name</label>
                    <input type="text" name="account_name" placeholder="Account holder name">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" placeholder="Account/Phone number">
                </div>
                
                <div class="bntm-form-group">
                    <label>Instructions</label>
                    <textarea name="payment_description" rows="3" placeholder="Payment instructions"></textarea>
                </div>
                
                <button type="submit" class="bntm-btn-primary">Add Payment Method</button>
            </form>
        </div>
        <div id="payment-method-message"></div>
    </div>
    
    <script>
    (function() {
        const paymentSourceSelect = document.getElementById('payment-source-select');
        const manualSection = document.getElementById('manual-payment-section');
        const routesTableBody = document.getElementById('cr-routes-table-body');
        const pricingRulesBody = document.getElementById('cr-pricing-rules-body');

        function crSlugify(value) {
            return String(value || '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function crSyncPricingRules() {
            const rules = {};
            pricingRulesBody.querySelectorAll('tr').forEach(row => {
                const rawSlug = row.querySelector('.cr-pricing-slug')?.value || '';
                const slug = crSlugify(rawSlug);
                const label = row.querySelector('.cr-pricing-label')?.value.trim() || '';
                if (!slug || !label) return;

                row.querySelector('.cr-pricing-slug').value = slug;
                rules[slug] = {
                    label,
                    base_fee: parseFloat(row.querySelector('.cr-pricing-base-fee')?.value || 0),
                    rate_per_km: parseFloat(row.querySelector('.cr-pricing-rate-km')?.value || 0),
                    overtime_rate: parseFloat(row.querySelector('.cr-pricing-overtime')?.value || 0),
                };
            });
            document.getElementById('pricing-rules-json').value = JSON.stringify(rules);
        }

        function crBindPricingRow(row) {
            row.querySelectorAll('input').forEach(field => {
                field.addEventListener('input', crSyncPricingRules);
                field.addEventListener('change', crSyncPricingRules);
            });
            row.querySelector('.cr-remove-pricing-row')?.addEventListener('click', function() {
                row.remove();
                crSyncPricingRules();
            });
        }

        document.getElementById('cr-add-pricing-row').addEventListener('click', function() {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="cr-pricing-slug" placeholder="vehicle_slug"></td>
                <td><input type="text" class="cr-pricing-label" placeholder="Vehicle Label"></td>
                <td><input type="number" class="cr-pricing-base-fee" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-pricing-rate-km" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-pricing-overtime" min="0" step="0.01" value="0"></td>
                <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-pricing-row">Remove</button></td>
            `;
            pricingRulesBody.appendChild(row);
            crBindPricingRow(row);
            crSyncPricingRules();
        });

        pricingRulesBody.querySelectorAll('tr').forEach(crBindPricingRow);
        crSyncPricingRules();

        function crSyncRouteDefinitions() {
            const lines = [];
            routesTableBody.querySelectorAll('tr').forEach(row => {
                const city = row.querySelector('.cr-route-city')?.value || 'cebu';
                const base = row.querySelector('.cr-route-base')?.value.trim() || '';
                const destination = row.querySelector('.cr-route-destination')?.value.trim() || '';
                const distance = row.querySelector('.cr-route-distance')?.value || 0;
                const carFixed = row.querySelector('.cr-route-car-fixed')?.value || 0;
                const vanFixed = row.querySelector('.cr-route-van-fixed')?.value || 0;
                const suvFixed = row.querySelector('.cr-route-suv-fixed')?.value || 0;

                if (!base || !destination) return;
                lines.push([city, base, destination, distance, carFixed, vanFixed, suvFixed].join('|'));
            });
            document.getElementById('route-definitions').value = lines.join('\n');
        }

        function crBindRouteRow(row) {
            row.querySelectorAll('input, select').forEach(field => {
                field.addEventListener('input', crSyncRouteDefinitions);
                field.addEventListener('change', crSyncRouteDefinitions);
            });
            row.querySelector('.cr-remove-route-row')?.addEventListener('click', function() {
                row.remove();
                crSyncRouteDefinitions();
            });
        }

        document.getElementById('cr-add-route-row').addEventListener('click', function() {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select class="cr-route-city">
                        <option value="cebu">Cebu</option>
                        <option value="cdo">CDO</option>
                    </select>
                </td>
                <td><input type="text" class="cr-route-base"></td>
                <td><input type="text" class="cr-route-destination"></td>
                <td><input type="number" class="cr-route-distance" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-route-car-fixed" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-route-van-fixed" min="0" step="0.01" value="0"></td>
                <td><input type="number" class="cr-route-suv-fixed" min="0" step="0.01" value="0"></td>
                <td><button type="button" class="bntm-btn-small bntm-btn-danger cr-remove-route-row">Remove</button></td>
            `;
            routesTableBody.appendChild(row);
            crBindRouteRow(row);
            crSyncRouteDefinitions();
        });

        routesTableBody.querySelectorAll('tr').forEach(crBindRouteRow);
        crSyncRouteDefinitions();
        
        paymentSourceSelect.addEventListener('change', function() {
            manualSection.style.display = this.value === 'op' ? 'none' : 'block';
        });
        
        document.getElementById('save-payment-source-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'cr_save_payment_source');
            formData.append('payment_source', paymentSourceSelect.value);
            formData.append('nonce', this.dataset.nonce);
            
            this.disabled = true;
            this.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r) { return r.json(); })
            .then(function(json) {
                document.getElementById('payment-source-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                document.getElementById('save-payment-source-btn').disabled = false;
                document.getElementById('save-payment-source-btn').textContent = 'Save Payment Source';
            });
        });
        
        document.getElementById('add-payment-method-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'cr_add_payment_method');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (json.success) {
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Add Payment Method';
                }
                document.getElementById('payment-method-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
            });
        });
        
        document.querySelectorAll('.remove-payment-method').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this payment method?')) return;
                
                const formData = new FormData();
                formData.append('action', 'cr_remove_payment_method');
                formData.append('index', this.dataset.index);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
        document.getElementById('save-booking-settings-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'cr_save_booking_settings');
            crSyncPricingRules();
            formData.append('pricing_rules_json', document.getElementById('pricing-rules-json').value);
            crSyncRouteDefinitions();
            formData.append('route_definitions', document.getElementById('route-definitions').value);
            formData.append('downpayment_percentage', document.getElementById('downpayment-percentage').value);
            formData.append('terms', document.getElementById('booking-terms').value);
            formData.append('nonce', this.dataset.nonce);
            
            this.disabled = true;
            this.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(function(r) { return r.json(); })
            .then(function(json) {
                document.getElementById('booking-settings-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                document.getElementById('save-booking-settings-btn').disabled = false;
                document.getElementById('save-booking-settings-btn').textContent = 'Save Booking Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// BOOKING FORM SHORTCODE (Public)
// ============================================================================

function bntm_shortcode_cr_form() {
    global $wpdb;
    $packages_table = $wpdb->prefix . 'cr_packages';
    
    $packages = $wpdb->get_results("SELECT * FROM $packages_table WHERE status='active' ORDER BY package_name");
    $nonce = wp_create_nonce('cr_form_nonce');
    $routes = bntm_cr_get_routes();
    $city_choices = bntm_cr_get_city_choices();
    $vehicle_labels = bntm_cr_get_vehicle_category_labels();
    $pricing_rules = bntm_cr_get_pricing_rules();
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <style>
    .catalog-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    .city-choice-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(160px, 1fr));
        gap: 12px;
        max-width: 460px;
        margin: 20px auto 10px;
    }
    .city-choice-btn {
        border: 2px solid #d1d5db;
        background: #fff;
        padding: 16px 18px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        transition: all .2s ease;
    }
    .city-choice-btn.active {
        border-color: var(--bntm-primary);
        background: #f8fafc;
        color: var(--bntm-primary);
    }
    .catalog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .car-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .car-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .car-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
        background: #f3f4f6;
    }
    .car-details {
        padding: 20px;
    }
    .car-name {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .car-type {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 10px;
    }
    .car-price {
        font-size: 24px;
        font-weight: bold;
        color: var(--bntm-primary);
        margin-bottom: 10px;
    }
    .car-info {
        display: flex;
        gap: 15px;
        font-size: 14px;
        color: #6b7280;
        margin-top: 10px;
    }
    .booking-form-wrapper {
        display: none;
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .back-to-catalog {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: var(--bntm-primary);
        cursor: pointer;
        margin-bottom: 20px;
        font-weight: 500;
    }
    .back-to-catalog:hover {
        text-decoration: underline;
    }
    .pricing-breakdown {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }
    .pricing-breakdown > div {
        background: #f9fafb;
        border-radius: 8px;
        padding: 12px;
    }
    </style>
    
    <div class="catalog-container" id="catalog-view">
        <h2 style="text-align: center;">Choose City First</h2>
        <div class="city-choice-grid">
            <?php foreach ($city_choices as $city_key => $city_label): ?>
                <button type="button" class="city-choice-btn" data-city="<?php echo esc_attr($city_key); ?>">
                    <?php echo esc_html($city_label); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <p id="city-helper" style="text-align:center;color:#6b7280;margin:0 0 12px;">Select a city to see available vehicles.</p>
        <div class="catalog-grid">
            <?php foreach ($packages as $pkg): ?>
            <div class="car-card" data-city="<?php echo esc_attr($pkg->city ?? 'cebu'); ?>" onclick="selectCar(<?php echo $pkg->id; ?>)" style="display:none;">
                <?php if ($pkg->photo_url): ?>
                    <img src="<?php echo esc_url($pkg->photo_url); ?>" class="car-image" alt="<?php echo esc_attr($pkg->package_name); ?>">
                <?php else: ?>
                    <div class="car-image" style="display: flex; align-items: center; justify-content: center; font-size: 48px;">🚗</div>
                <?php endif; ?>
                <div class="car-details">
                    <div class="car-name"><?php echo esc_html($pkg->package_name); ?></div>
                    <div class="car-type"><?php echo esc_html($pkg->boat_type); ?> • <?php echo esc_html($vehicle_labels[$pkg->vehicle_category ?? 'car'] ?? 'Car'); ?></div>
                    <div class="car-price">Base fee ₱<?php echo number_format($pkg->daily_rate, 2); ?></div>
            
                    <div class="car-info">
                        <span><?php echo esc_html($city_choices[bntm_cr_normalize_city($pkg->city ?? 'cebu')] ?? 'Cebu'); ?></span>
                        <span>Max person <?php echo $pkg->max_pax; ?> pax</span>
                        <span>OT ₱<?php echo number_format($pkg->hourly_surcharge, 2); ?>/hr</span>
                    </div>
                    <?php if ($pkg->description): ?>
                    <p style="margin-top: 10px; font-size: 13px; color: #6b7280;">
                        <?php echo esc_html(substr($pkg->description, 0, 80)); ?><?php echo strlen($pkg->description) > 80 ? '...' : ''; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="booking-form-wrapper" id="booking-form-view">
        <div class="back-to-catalog" onclick="backToCatalog()">
            ← Back to Catalog
        </div>
        
        <h2 style="text-align: center; margin-bottom: 30px;">Complete Your Booking</h2>
        
        <form id="car-booking-form" class="bntm-form">
            <input type="hidden" name="package_id" id="selected-package-id">
            <input type="hidden" name="city" id="selected-city">
            
            <div id="selected-car-info" style="padding: 20px; background: #f9fafb; border-radius: 8px; margin-bottom: 20px;">
                <!-- Selected car info will be shown here -->
            </div>

            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Destination *</label>
                    <select name="destination" id="destination-select" required>
                        <option value="">Select destination</option>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Base Point</label>
                    <input type="text" name="base_point" id="base-point" style="background: #f3f4f6;">
                </div>
            </div>

            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Distance (KM)</label>
                    <input type="number" name="distance_km" id="distance-km" readonly style="background: #f3f4f6;">
                    <small id="distance-source-note" style="color:#6b7280;">Uses the KM saved in your base point route table.</small>
                </div>
                <div class="bntm-form-group">
                    <label>Total Hours *</label>
                    <input type="number" name="total_hours" id="total-hours" required min="1" step="0.5" placeholder="e.g., 8">
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" id="start-date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="bntm-form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" id="end-date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Number of Days</label>
                <input type="number" id="number-of-days" readonly style="background: #f3f4f6; font-weight: bold;">
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required>
                </div>
                <div class="bntm-form-group">
                    <label>Email *</label>
                    <input type="email" name="customer_email" required>
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="customer_phone" required>
                </div>
                <div class="bntm-form-group">
                    <label>Number of Passengers *</label>
                    <input type="number" name="number_of_pax" id="pax-input" required min="1">
                    <small id="pax-warning" style="color: #dc2626; display: none;"></small>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Additional Notes</label>
                <textarea name="notes" rows="4" placeholder="Any special requests?"></textarea>
            </div>
            
            <div style="padding: 20px; border: 2px solid var(--bntm-primary); border-radius: 8px; margin-bottom: 20px;">
                <div class="pricing-breakdown">
                    <span id="display-daily-rate" style="display:none;"></span>
                    <div>
                        <small style="color: #6b7280;">Base Fee</small>
                        <div style="font-size: 18px; font-weight: bold;">₱<span id="display-base-fee">0.00</span></div>
                    </div>
                    <div>
                        <small style="color: #6b7280;">Distance Charge</small>
                        <div style="font-size: 18px; font-weight: bold;">₱<span id="display-distance-charge">0.00</span></div>
                    </div>
                    <div>
                        <small style="color: #6b7280;">Base Rate</small>
                        <div style="font-size: 18px; font-weight: bold;">₱<span id="display-base-rate">0.00</span></div>
                    </div>
                    <div>
                        <small style="color: #6b7280;">Overtime Charge</small>
                        <div style="font-size: 18px; font-weight: bold;">₱<span id="display-overtime-charge">0.00</span></div>
                    </div>
                    <div>
                        <small style="color: #6b7280;">Number of Days</small>
                        <div style="font-size: 18px; font-weight: bold;"><span id="display-days">0</span> days</div>
                    </div>
                </div>
                <div id="pricing-note" style="font-size: 13px; color: #6b7280; margin-bottom: 10px;"></div>
                <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 15px 0;">
                <h3 style="margin: 0; color: var(--bntm-primary);">Total Amount: <span id="total-amount">₱0.00</span></h3>
            </div>
            
            <button type="submit" class="bntm-btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
                Submit Booking Request
            </button>
        </form>
        
        <div id="booking-message" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    const packages = <?php echo json_encode($packages); ?>;
    let selectedPackage = null;
    
    function selectCar(packageId) {
        selectedPackage = packages.find(p => p.id == packageId);
        if (!selectedPackage) return;
        
        document.getElementById('selected-package-id').value = packageId;
        document.getElementById('catalog-view').style.display = 'none';
        document.getElementById('booking-form-view').style.display = 'block';
        
        document.getElementById('selected-car-info').innerHTML = `
            <div style="display: flex; gap: 20px; align-items: center;">
                ${selectedPackage.photo_url ? 
                    `<img src="${selectedPackage.photo_url}" style="width: 150px; height: 100px; object-fit: cover; border-radius: 8px;">` :
                    `<div style="width: 150px; height: 100px; background: #e5e7eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 36px;">🚗</div>`
                }
                <div>
                    <h3 style="margin: 0 0 5px 0;">${selectedPackage.package_name}</h3>
                    <p style="margin: 0; color: #6b7280;">${selectedPackage.boat_type}</p>
                    <p style="margin: 5px 0 0 0; font-size: 20px; font-weight: bold; color: var(--bntm-primary);">
                        ₱${parseFloat(selectedPackage.daily_rate).toLocaleString()}/day
                    </p>
                </div>
            </div>
        `;
        
        document.getElementById('display-daily-rate').textContent = parseFloat(selectedPackage.daily_rate).toLocaleString();
        document.getElementById('pax-input').max = selectedPackage.max_pax;
        calculateTotal();
    }
    
    function backToCatalog() {
        document.getElementById('catalog-view').style.display = 'block';
        document.getElementById('booking-form-view').style.display = 'none';
        document.getElementById('car-booking-form').reset();
    }
    
    function calculateDays() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = end - start;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays >= 0) {
                document.getElementById('number-of-days').value = diffDays;
                document.getElementById('display-days').textContent = diffDays;
                calculateTotal();
                return diffDays;
            }
        }
        return 0;
    }
    
    function calculateTotal() {
        if (!selectedPackage) return;
        
        const days = calculateDays();
        const total = days * parseFloat(selectedPackage.daily_rate);
        
        document.getElementById('total-amount').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    document.getElementById('start-date').addEventListener('change', function() {
        const endDateInput = document.getElementById('end-date');
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
        calculateDays();
    });
    
    document.getElementById('end-date').addEventListener('change', calculateDays);
    
    document.getElementById('pax-input').addEventListener('input', function() {
        if (selectedPackage && parseInt(this.value) > selectedPackage.max_pax) {
            document.getElementById('pax-warning').textContent = `Maximum ${selectedPackage.max_pax} passengers allowed`;
            document.getElementById('pax-warning').style.display = 'block';
            this.value = selectedPackage.max_pax;
        } else {
            document.getElementById('pax-warning').style.display = 'none';
        }
    });
    
    document.getElementById('car-booking-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const days = parseInt(document.getElementById('number-of-days').value);
        if (days <= 0) {
            alert('Please select valid dates');
            return;
        }
        
        const formData = new FormData(this);
        formData.append('action', 'cr_submit_booking');
        formData.append('nonce', '<?php echo $nonce; ?>');
        formData.append('number_of_days', days);
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msgDiv = document.getElementById('booking-message');
            msgDiv.innerHTML = '<div style="padding: 15px; border-radius: 8px; ' + 
                (json.success ? 'background: #d1fae5; border: 1px solid #059669; color: #065f46;' : 
                                'background: #fee2e2; border: 1px solid #dc2626; color: #991b1b;') + 
                '">' + json.data.message + '</div>';
        
            if (json.success) {
                setTimeout(() => location.reload(), 3000);
            } else {
                btn.disabled = false;
                btn.textContent = 'Submit Booking Request';
            }
        });
    });
    </script>
    <script>
    const crCityChoices = <?php echo wp_json_encode($city_choices); ?>;
    const crRoutes = <?php echo wp_json_encode($routes); ?>;
    const crPricingRules = <?php echo wp_json_encode($pricing_rules); ?>;
    const crVehicleLabels = <?php echo wp_json_encode($vehicle_labels); ?>;
    const crDefaultCategory = <?php echo wp_json_encode(bntm_cr_get_default_vehicle_category_slug()); ?>;
    let crSelectedCity = 'cebu';

    function crRenderCatalog(city) {
        crSelectedCity = city;
        const cityInput = document.getElementById('selected-city');
        if (cityInput) cityInput.value = city;

        document.querySelectorAll('.city-choice-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.city === city);
        });

        let visible = 0;
        document.querySelectorAll('.car-card').forEach(card => {
            const show = card.dataset.city === city;
            card.style.display = show ? 'block' : 'none';
            if (show) visible += 1;
        });

        const helper = document.getElementById('city-helper');
        if (helper) {
            helper.textContent = visible
                ? `Showing ${visible} vehicle(s) in ${crCityChoices[city] || city.toUpperCase()}.`
                : `No vehicles found in ${crCityChoices[city] || city.toUpperCase()}.`;
        }
    }

    function crPopulateDestinations(city) {
        const select = document.getElementById('destination-select');
        if (!select) return;

        select.innerHTML = '<option value="">Select destination</option>';
        crRoutes.filter(route => route.city === city).forEach(route => {
            const option = document.createElement('option');
            option.value = route.destination;
            option.textContent = `${route.base_point} -> ${route.destination} (${route.distance_km} KM)`;
            option.dataset.basePoint = route.base_point;
            option.dataset.distance = route.distance_km;
            option.dataset.fixedCar = route.fixed_rates?.car || 0;
            option.dataset.fixedVan = route.fixed_rates?.van_innova || 0;
            option.dataset.fixedSuv = route.fixed_rates?.suv_grandia || 0;
            select.appendChild(option);
        });
        crHandleDestinationChange();
    }

    function crHandleDestinationChange() {
        const select = document.getElementById('destination-select');
        const option = select?.options?.[select.selectedIndex];
        document.getElementById('base-point').value = option?.dataset?.basePoint || '';
        document.getElementById('distance-km').value = option?.dataset?.distance || '';
        document.getElementById('distance-source-note').textContent = 'Uses the KM saved in your base point route table.';
        calculateTotal();
    }

    function crGetPricing() {
        if (!selectedPackage) return null;

        const category = selectedPackage.vehicle_category || crDefaultCategory;
        const rule = crPricingRules[category] || crPricingRules[crDefaultCategory];
        const option = document.getElementById('destination-select')?.options?.[document.getElementById('destination-select').selectedIndex];
        const distanceKm = parseFloat(option?.dataset?.distance || 0);
        const totalHours = parseFloat(document.getElementById('total-hours')?.value || 0);
        const baseFee = parseFloat(rule.base_fee || 0);
        const ratePerKm = parseFloat(rule.rate_per_km || 0);
        const overtimeRate = parseFloat(rule.overtime_rate || 0);
        const distanceCharge = distanceKm * ratePerKm;
        const overtimeCharge = Math.max(0, totalHours - 10) * overtimeRate;
        const fixedRates = {
            car: parseFloat(option?.dataset?.fixedCar || 0),
            van_innova: parseFloat(option?.dataset?.fixedVan || 0),
            suv_grandia: parseFloat(option?.dataset?.fixedSuv || 0),
        };

        let baseRate = baseFee + distanceCharge;
        let pricingType = 'formula';
        if (fixedRates[category] > 0) {
            baseRate = fixedRates[category];
            pricingType = 'fixed';
        }

        return { category, baseFee, ratePerKm, overtimeRate, distanceKm, distanceCharge, baseRate, overtimeCharge, total: baseRate + overtimeCharge, pricingType };
    }

    backToCatalog = function() {
        document.getElementById('catalog-view').style.display = 'block';
        document.getElementById('booking-form-view').style.display = 'none';
        document.getElementById('car-booking-form').reset();
        document.getElementById('base-point').value = '';
        document.getElementById('distance-km').value = '';
        document.getElementById('pricing-note').textContent = '';
    };

    calculateDays = function() {
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;

        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            if (diffDays > 0) {
                document.getElementById('number-of-days').value = diffDays;
                document.getElementById('display-days').textContent = diffDays;
                return diffDays;
            }
        }

        document.getElementById('number-of-days').value = '';
        document.getElementById('display-days').textContent = '0';
        return 0;
    };

    calculateTotal = function() {
        if (!selectedPackage) return;

        calculateDays();
        const pricing = crGetPricing();
        if (!pricing) return;

        document.getElementById('display-base-fee').textContent = pricing.baseFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('display-distance-charge').textContent = pricing.distanceCharge.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('display-base-rate').textContent = pricing.baseRate.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('display-overtime-charge').textContent = pricing.overtimeCharge.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('pricing-note').textContent = pricing.pricingType === 'fixed'
            ? `Fixed destination rate applied for ${crVehicleLabels[pricing.category] || pricing.category}. Overtime starts after 10 hours.`
            : `Base rate = base fee + (${pricing.distanceKm} KM × ₱${pricing.ratePerKm}/KM). Overtime starts after 10 hours at ₱${pricing.overtimeRate}/hr.`;
        document.getElementById('total-amount').textContent = '₱' + pricing.total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    };

    selectCar = function(packageId) {
        selectedPackage = packages.find(p => p.id == packageId);
        if (!selectedPackage) return;

        crSelectedCity = selectedPackage.city || crSelectedCity || 'cebu';
        document.getElementById('selected-package-id').value = packageId;
        document.getElementById('selected-city').value = crSelectedCity;
        document.getElementById('catalog-view').style.display = 'none';
        document.getElementById('booking-form-view').style.display = 'block';
        crPopulateDestinations(crSelectedCity);

        document.getElementById('selected-car-info').innerHTML = `
            <div style="display: flex; gap: 20px; align-items: center;">
                ${selectedPackage.photo_url
                    ? `<img src="${selectedPackage.photo_url}" style="width: 150px; height: 100px; object-fit: cover; border-radius: 8px;">`
                    : `<div style="width: 150px; height: 100px; background: #e5e7eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 36px;">Car</div>`}
                <div>
                    <h3 style="margin: 0 0 5px 0;">${selectedPackage.package_name}</h3>
                    <p style="margin: 0; color: #6b7280;">${selectedPackage.boat_type} • ${crVehicleLabels[selectedPackage.vehicle_category] || 'Car'} • ${crCityChoices[crSelectedCity] || crSelectedCity}</p>
                    <p style="margin: 5px 0 0 0; font-size: 20px; font-weight: bold; color: var(--bntm-primary);">
                        Base fee ₱${parseFloat(selectedPackage.daily_rate).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                    </p>
                </div>
            </div>
        `;

        document.getElementById('pax-input').max = selectedPackage.max_pax;
        calculateTotal();
    };

    document.getElementById('destination-select')?.addEventListener('change', crHandleDestinationChange);
    document.getElementById('total-hours')?.addEventListener('input', calculateTotal);

    document.querySelectorAll('.city-choice-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            crRenderCatalog(this.dataset.city);
        });
    });

    crRenderCatalog('cebu');
    </script>
    <?php
    return ob_get_clean();
}
function bntm_shortcode_cr_invoice() {
    $booking_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($booking_id)) {
        return '<div class="bntm-container"><p>Invalid booking ID.</p></div>';
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';
    
   $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, p.package_name, p.boat_type as package_type, p.hourly_surcharge as package_hourly_surcharge, p.description, p.photo_url
         FROM $bookings_table b
         LEFT JOIN $packages_table p ON b.package_id = p.id
         WHERE b.rand_id = %s",
        $booking_id
    ));

    if (!$booking) {
        return '<div class="bntm-container"><p>Booking not found. ID: ' . esc_html($booking_id) . '</p></div>';
    }
    
    $logo = bntm_get_site_logo();
    $site_title = bntm_get_site_title();
    $show_payment_btn = in_array($booking->status, ['contacted', 'paid']);
    
    // Get payment methods
    $payment_source = bntm_get_setting('cr_payment_source', 'manual');
    $payment_methods = [];
    
    if ($payment_source === 'manual') {
        $payment_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
        if (!is_array($payment_methods)) $payment_methods = [];
    }
    
    // Calculate totals
    $downpayment_percent = intval(bntm_get_setting('cr_downpayment_percentage', '50'));
    $terms = bntm_get_setting('cr_terms', '');
    
    // Get package amount from booking
    $package_amount = floatval($booking->package_amount);
    
    // Calculate other fees total
    $other_fees_total = 0;
    $other_fees = json_decode($booking->other_fees, true);
    if (is_array($other_fees) && !empty($other_fees)) {
        foreach ($other_fees as $fee) {
            $other_fees_total += floatval($fee['amount']);
        }
    }
    
    // Get surcharge from database (already calculated and saved)
    $excess_hours = floatval($booking->excess_hours ?? 0);
    $surcharge_amount = floatval($booking->surcharge_amount ?? 0);
    
    // Calculate base total (package + other fees, excluding surcharge)
    $base_total = $package_amount + $other_fees_total;
    
    // Calculate downpayment from base total only (excluding surcharge)
    $downpayment_amount = $downpayment_percent > 0 ? ($base_total * $downpayment_percent / 100) : 0;
    
    // Calculate final total (base + surcharge)
    $final_total = $base_total + $surcharge_amount;
    
    // Calculate remaining balance (final total minus downpayment)
    $balance = $final_total - $downpayment_amount;
    
    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    .invoice-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; background: white; font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; color: #000; }
    .invoice-header { display: flex; justify-content: space-between; padding-bottom: 15px; margin-bottom: 20px; border-bottom: 2px solid #000; }
    .company-name { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
    .invoice-title { font-size: 22pt; font-weight: bold; text-align: right; }
    .invoice-number { font-size: 14pt; text-align: right; margin-top: 5px; }
    .invoice-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 20px; }
    .info-label { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 5px; }
    .customer-name { font-weight: bold; font-size: 11pt; margin-bottom: 3px; }
    .info-table { width: 100%; font-size: 10pt; }
    .info-table td { padding: 3px 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th { text-align: left; font-weight: bold; padding: 8px 5px; border-bottom: 2px solid #000; font-size: 9pt; text-transform: uppercase; }
    td { padding: 8px 5px; border-bottom: 1px solid #ddd; font-size: 10pt; vertical-align: top; }
    tbody tr:last-child td { border-bottom: 1px solid #000; }
    tfoot td { padding: 6px 5px; border-bottom: none; }
    .total-row { font-size: 12pt; }
    .total-row td { padding: 10px 5px; border-top: 2px solid #000; border-bottom: 2px solid #000; }
    .text-right { text-align: right; }
    .info-box { margin: 15px 0; padding: 12px; border: 1px solid #000; }
    .info-box-title { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
    .info-box p { margin: 5px 0; font-size: 10pt; }
    .payment-method-item { padding: 10px; background: #f9fafb; border-radius: 4px; margin-bottom: 8px; }
    .car-photo-invoice { max-width: 150px; border-radius: 8px; margin-top: 5px; }
    .invoice-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #000; text-align: center; font-size: 10pt; }
    .invoice-footer p { margin: 5px 0; }
    .highlight-row { background: #fff3cd; }
    @media print {
        .invoice-container { padding: 20px; }
        .no-print { display: none !important; }
    }
    @media screen and (max-width: 768px) {
        .invoice-header { flex-direction: column; gap: 15px; }
        .invoice-title, .invoice-number { text-align: left; }
        .invoice-info { grid-template-columns: 1fr; gap: 15px; }
    }
    </style>
    
    <div class="invoice-container">
        <div style="text-align: center; margin-bottom: 20px;" class="no-print">
            <button onclick="window.print()" class="bntm-btn bntm-btn-secondary">Print Invoice</button>
        </div>

        <div class="invoice-header">
            <div>
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 120px; max-height: 50px; margin-bottom: 10px;">
                <?php endif; ?>
                <div class="company-name"><?php echo esc_html($site_title ?: 'Car Rental Booking'); ?></div>
            </div>
            <div>
                <div class="invoice-title">CAR RENTAL BOOKING</div>
                <div class="invoice-number">#<?php echo esc_html($booking->rand_id); ?></div>
            </div>
        </div>

        <div class="invoice-info">
            <div>
                <div class="info-label">CUSTOMER DETAILS</div>
                <div class="customer-name"><?php echo esc_html($booking->customer_name); ?></div>
                <div><?php echo esc_html($booking->customer_email); ?></div>
                <div><?php echo esc_html($booking->customer_phone); ?></div>
            </div>
            <div>
                <table class="info-table">
                    <tr>
                        <td><strong>Rental Period:</strong></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($booking->start_date)); ?> - 
                            <?php echo date('M d, Y', strtotime($booking->end_date)); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td><?php echo $booking->number_of_days; ?> day<?php echo $booking->number_of_days > 1 ? 's' : ''; ?></td>
                    </tr>
                    <?php if (!empty($booking->destination)): ?>
                    <tr>
                        <td><strong>Destination:</strong></td>
                        <td><?php echo esc_html($booking->destination); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->distance_km)): ?>
                    <tr>
                        <td><strong>Distance:</strong></td>
                        <td><?php echo number_format($booking->distance_km, 2); ?> KM</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->total_hours)): ?>
                    <tr>
                        <td><strong>Total Hours:</strong></td>
                        <td><?php echo number_format($booking->total_hours, 2); ?> hours</td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($booking->check_in_time) && !empty($booking->check_out_time)): ?>
                    <tr>
                        <td><strong>Check-in:</strong></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($booking->check_in_time)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Check-out:</strong></td>
                        <td><?php echo date('M d, Y g:i A', strtotime($booking->check_out_time)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Booking Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($booking->created_at)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><strong><?php echo ucfirst(str_replace('_', ' ', $booking->status)); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th class="text-right">RATE</th>
                    <th class="text-right">DAYS</th>
                    <th class="text-right">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo esc_html($booking->package_name); ?></strong><br>
                        <span style="font-size: 9pt; color: #666;">
                            <?php echo esc_html($booking->package_type); ?>
                            <?php if (!empty($booking->city)): ?>
                                <br>City: <?php echo esc_html(strtoupper($booking->city)); ?>
                            <?php endif; ?>
                            <?php if (!empty($booking->base_point) || !empty($booking->destination)): ?>
                                <br>Route: <?php echo esc_html($booking->base_point); ?> -> <?php echo esc_html($booking->destination); ?>
                            <?php endif; ?>
                            <?php if (!empty($booking->distance_km)): ?>
                                <br>Distance: <?php echo number_format($booking->distance_km, 2); ?> KM
                            <?php endif; ?>
                            <?php if (!empty($booking->total_hours)): ?>
                                <br>Total Hours: <?php echo number_format($booking->total_hours, 2); ?> hours
                            <?php endif; ?>
                            <?php if (($booking->overtime_rate ?? 0) > 0): ?>
                                <br>Overtime Rate: ₱<?php echo number_format($booking->overtime_rate, 2); ?>/hr
                            <?php endif; ?>
                        </span>
                       
                        <?php if ($booking->description): ?>
                            <br><span style="font-size: 9pt; color: #666;">
                                <?php echo nl2br(esc_html($booking->description)); ?>
                            </span>
                        <?php endif; ?>
                        <br><span style="font-size: 9pt; color: #666;">
                            Passengers: <?php echo $booking->number_of_pax; ?> pax
                        </span>
                    </td>
                    <td class="text-right">₱<?php echo number_format($booking->base_fee ?? $booking->daily_rate, 2); ?></td>
                    <td class="text-right"><?php echo $booking->number_of_days; ?></td>
                    <td class="text-right">₱<?php echo number_format($package_amount, 2); ?></td>
                </tr>
                <?php 
                if (is_array($other_fees) && !empty($other_fees)):
                    foreach ($other_fees as $fee):
                ?>
                <tr>
                    <td colspan="3"><?php echo esc_html($fee['description']); ?></td>
                    <td class="text-right">₱<?php echo number_format($fee['amount'], 2); ?></td>
                </tr>
                <?php 
                    endforeach;
                endif;
                ?>
                <?php if ($surcharge_amount > 0): ?>
                <tr style="background: #fef3c7;">
                    <td colspan="3">
                        <strong>Excess Time Surcharge</strong><br>
                        <span style="font-size: 9pt; color: #666;">
                            <?php echo number_format($excess_hours, 2); ?> excess hours @ 
                            ₱<?php echo number_format($booking->overtime_rate ?? 0, 2); ?>/hr
                        </span>
                        <br><span style="font-size: 9pt; color: #666;">Included time allowance: 10 hours</span>
                    </td>
                    <td class="text-right"><strong>₱<?php echo number_format($surcharge_amount, 2); ?></strong></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <?php if ($downpayment_percent > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">Package & Fees Subtotal</td>
                        <td class="text-right">₱<?php echo number_format($base_total, 2); ?></td>
                    </tr>
                    <?php if ($surcharge_amount > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">
                            Excess Time Surcharge
                            <br><small style="font-weight: normal; color: #666;">
                                (<?php echo number_format($excess_hours, 2); ?> excess hours)
                            </small>
                        </td>
                        <td class="text-right">₱<?php echo number_format($surcharge_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="3" class="text-right">
                            <strong>Grand Total</strong>
                        </td>
                        <td class="text-right">
                            <strong>₱<?php echo number_format($final_total, 2); ?></strong>
                        </td>
                    </tr>
                    <tr style="background: #f9fafb;">
                        <td colspan="3" class="text-right">
                            <strong>Down Payment (<?php echo $downpayment_percent; ?>%)</strong>
                            <br><small style="font-weight: normal; color: #666;">Based on package & fees only</small>
                        </td>
                        <td class="text-right">
                            <strong>₱<?php echo number_format($downpayment_amount, 2); ?></strong>
                        </td>
                    </tr>
                    <tr class="highlight-row">
                        <td colspan="3" class="text-right">
                            <strong>Remaining Balance</strong>
                            <?php if ($surcharge_amount > 0): ?>
                            <br><small style="font-weight: normal; color: #856404;">Includes ₱<?php echo number_format($surcharge_amount, 2); ?> surcharge fee</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <strong style="color: #dc2626;">₱<?php echo number_format($balance, 2); ?></strong>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php if ($surcharge_amount > 0): ?>
                    <tr>
                        <td colspan="3" class="text-right">Subtotal</td>
                        <td class="text-right">₱<?php echo number_format($base_total, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-right">
                            Excess Time Surcharge
                            <br><small style="font-weight: normal; color: #666;">
                                (<?php echo number_format($excess_hours, 2); ?> excess hours)
                            </small>
                        </td>
                        <td class="text-right">₱<?php echo number_format($surcharge_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>TOTAL AMOUNT DUE</strong></td>
                    <td class="text-right"><strong>₱<?php echo number_format($downpayment_percent > 0 ? $balance : $final_total, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <?php if ($booking->notes): ?>
        <div class="info-box">
            <div class="info-box-title">SPECIAL REQUESTS / NOTES</div>
            <p><?php echo nl2br(esc_html($booking->notes)); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($payment_methods)): ?>
        <div class="info-box">
            <div class="info-box-title">PAYMENT METHODS</div>
            <?php foreach ($payment_methods as $method): ?>
                <div class="payment-method-item">
                    <strong><?php echo esc_html($method['name']); ?></strong>
                    <?php if (!empty($method['account_name'])): ?>
                        <br><span style="font-size: 9pt;">Account Name: <?php echo esc_html($method['account_name']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($method['account_number'])): ?>
                        <br><span style="font-size: 9pt;">Account Number: <?php echo esc_html($method['account_number']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($method['description'])): ?>
                        <br><span style="font-size: 9pt; color: #666;"><?php echo esc_html($method['description']); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($show_payment_btn && $payment_source === 'op' && bntm_is_module_enabled('op')): ?>
        <div style="margin-top: 25px; text-align: center;" class="no-print">
            <a href="<?php echo get_permalink(get_page_by_path('payment')) . '?booking=' . esc_attr($booking->rand_id); ?>" 
               class="bntm-btn bntm-btn-primary" style="padding: 12px 30px; font-size: 16px;">
                Pay Now
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($terms)): ?>
        <div class="info-box">
            <div class="info-box-title">TERMS & CONDITIONS</div>
            <p style="white-space: pre-line; font-size: 9pt;"><?php echo nl2br(esc_html($terms)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="invoice-footer">
            <p>Thank you for choosing our car rental services!</p>
            <?php if ($site_title): ?>
            <p><?php echo esc_html($site_title); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
// ============================================================================
// AJAX HANDLERS
// ============================================================================

function bntm_ajax_cr_add_package() {
    check_ajax_referer('cr_package_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cr_packages';
    $pricing_rules = bntm_cr_get_pricing_rules();
    $vehicle_category = sanitize_text_field($_POST['vehicle_category'] ?? bntm_cr_get_default_vehicle_category_slug());
    if (!isset($pricing_rules[$vehicle_category])) {
        $vehicle_category = bntm_cr_get_default_vehicle_category_slug();
    }
    $rule = $pricing_rules[$vehicle_category];
    
    // Handle file upload
    $photo_url = null;
    if (!empty($_FILES['package_photo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['package_photo'], ['test_form' => false]);
        if (!isset($uploaded['error'])) {
            $photo_url = $uploaded['url'];
        }
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'package_name' => sanitize_text_field($_POST['package_name']),
        'city' => bntm_cr_normalize_city($_POST['city'] ?? 'cebu'),
        'vehicle_category' => $vehicle_category,
        'boat_type' => sanitize_text_field($_POST['boat_type']),
        'daily_rate' => floatval($rule['base_fee']),
        'hourly_surcharge' => floatval($rule['overtime_rate']),
        'max_pax' => intval($_POST['max_pax']),
        'photo_url' => $photo_url,
        'description' => sanitize_textarea_field($_POST['description']),
        'status' => 'active'
    ];
    
    $result = $wpdb->insert($table, $data, ['%s','%d','%s','%s','%s','%s','%f','%f','%d','%s','%s','%s']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Package added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add package']);
    }
}


function bntm_ajax_cr_update_package() {
check_ajax_referer('cr_package_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_packages';

$result = $wpdb->update(
    $table,
    ['status' => sanitize_text_field($_POST['status'])],
    ['id' => intval($_POST['package_id'])],
    ['%s'],
    ['%d']
);

if ($result !== false) {
    wp_send_json_success(['message' => 'Package updated']);
} else {
    wp_send_json_error(['message' => 'Failed to update package']);
}
}


function bntm_ajax_cr_update_package_full() {
    check_ajax_referer('cr_package_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cr_packages';
    $pricing_rules = bntm_cr_get_pricing_rules();
    $vehicle_category = sanitize_text_field($_POST['vehicle_category'] ?? bntm_cr_get_default_vehicle_category_slug());
    if (!isset($pricing_rules[$vehicle_category])) {
        $vehicle_category = bntm_cr_get_default_vehicle_category_slug();
    }
    $rule = $pricing_rules[$vehicle_category];
    
    $package_id = intval($_POST['package_id']);
    
    // Handle file upload if new photo provided
    $photo_url = null;
    if (!empty($_FILES['package_photo']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['package_photo'], ['test_form' => false]);
        if (!isset($uploaded['error'])) {
            $photo_url = $uploaded['url'];
        }
    }
    
    $data = [
        'package_name' => sanitize_text_field($_POST['package_name']),
        'city' => bntm_cr_normalize_city($_POST['city'] ?? 'cebu'),
        'vehicle_category' => $vehicle_category,
        'boat_type' => sanitize_text_field($_POST['boat_type']),
        'daily_rate' => floatval($rule['base_fee']),
        'hourly_surcharge' => floatval($rule['overtime_rate']),
        'max_pax' => intval($_POST['max_pax']),
        'description' => sanitize_textarea_field($_POST['description'])
    ];
    
    $formats = ['%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s'];
    
    // Only update photo if new one was uploaded
    if ($photo_url) {
        $data['photo_url'] = $photo_url;
        $formats[] = '%s';
    }
    
    $result = $wpdb->update(
        $table,
        $data,
        ['id' => $package_id],
        $formats,
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Package updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update package']);
    }
}

function bntm_ajax_cr_delete_package() {
check_ajax_referer('cr_package_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_packages';

$result = $wpdb->delete($table, ['id' => intval($_POST['package_id'])], ['%d']);

if ($result) {
    wp_send_json_success(['message' => 'Package deleted']);
} else {
    wp_send_json_error(['message' => 'Failed to delete package']);
}
}

function bntm_ajax_cr_update_booking_status() {
check_ajax_referer('cr_booking_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_bookings';

$result = $wpdb->update(
    $table,
    ['status' => sanitize_text_field($_POST['status'])],
    ['id' => intval($_POST['booking_id'])],
    ['%s'],
    ['%d']
);

if ($result !== false) {
    wp_send_json_success(['message' => 'Status updated']);
} else {
    wp_send_json_error(['message' => 'Failed to update status']);
}
}

function bntm_ajax_cr_edit_booking() {
    check_ajax_referer('cr_booking_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'cr_bookings';
    $packages_table = $wpdb->prefix . 'cr_packages';
    
    $other_fees = isset($_POST['other_fees']) ? stripslashes($_POST['other_fees']) : '[]';
    
    // Extract excess hours from the string (e.g., "2.50 hours" -> "2.50")
    $excess_hours_raw = isset($_POST['excess_hours']) ? $_POST['excess_hours'] : '0';
    $excess_hours = 0;
    if (is_string($excess_hours_raw) && strpos($excess_hours_raw, 'hours') !== false) {
        $excess_hours = floatval(str_replace(' hours', '', $excess_hours_raw));
    } else {
        $excess_hours = floatval($excess_hours_raw);
    }
    
    $data = [
        'package_id' => intval($_POST['package_id']),
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'customer_phone' => sanitize_text_field($_POST['customer_phone']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'number_of_days' => intval($_POST['number_of_days']),
        'check_in_time' => !empty($_POST['check_in_time']) ? sanitize_text_field($_POST['check_in_time']) : null,
        'check_out_time' => !empty($_POST['check_out_time']) ? sanitize_text_field($_POST['check_out_time']) : null,
        'excess_hours' => $excess_hours,
        'surcharge_amount' => floatval($_POST['surcharge_amount']),
        'number_of_pax' => intval($_POST['number_of_pax']),
        'package_amount' => floatval($_POST['package_amount']),
        'other_fees' => $other_fees,
        'total_amount' => floatval($_POST['total_amount']),
        'status' => sanitize_text_field($_POST['status']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    $existing_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", intval($_POST['booking_id'])));
    $selected_package = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$packages_table} WHERE id = %d", intval($_POST['package_id'])));

    if ($existing_booking && $selected_package && !empty($existing_booking->destination)) {
        $route = bntm_cr_find_route($existing_booking->city ?? ($selected_package->city ?? 'cebu'), $existing_booking->destination, $existing_booking->base_point ?? '');
        $fees_total = 0;
        $decoded_fees = json_decode($other_fees, true);
        if (is_array($decoded_fees)) {
            foreach ($decoded_fees as $fee) {
                $fees_total += floatval($fee['amount'] ?? 0);
            }
        }

        if ($route) {
            $calc = bntm_cr_calculate_total(
                $selected_package->vehicle_category ?? bntm_cr_get_default_vehicle_category_slug(),
                $route['distance_km'],
                floatval($existing_booking->total_hours ?? 0),
                $route
            );

            $data['package_amount'] = $calc['base_rate'];
            $data['excess_hours'] = $calc['overtime_hours'];
            $data['surcharge_amount'] = $calc['overtime_charge'];
            $data['total_amount'] = $calc['base_rate'] + $calc['overtime_charge'] + $fees_total;
        }
    }
    
    $format = [
        '%d',  // package_id
        '%s',  // customer_name
        '%s',  // customer_email
        '%s',  // customer_phone
        '%s',  // start_date
        '%s',  // end_date
        '%d',  // number_of_days
        '%s',  // check_in_time
        '%s',  // check_out_time
        '%f',  // excess_hours
        '%f',  // surcharge_amount
        '%d',  // number_of_pax
        '%f',  // package_amount
        '%s',  // other_fees
        '%f',  // total_amount
        '%s',  // status
        '%s'   // notes
    ];
    
    $result = $wpdb->update(
        $table,
        $data,
        ['id' => intval($_POST['booking_id'])],
        $format,
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Booking updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update booking: ' . $wpdb->last_error]);
    }
}
function bntm_ajax_cr_delete_booking() {
check_ajax_referer('cr_booking_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$table = $wpdb->prefix . 'cr_bookings';

$result = $wpdb->delete($table, ['id' => intval($_POST['booking_id'])], ['%d']);

if ($result) {
    wp_send_json_success(['message' => 'Booking deleted']);
} else {
    wp_send_json_error(['message' => 'Failed to delete booking']);
}
}

function bntm_ajax_cr_submit_booking() {
    check_ajax_referer('cr_form_nonce', 'nonce');

    global $wpdb;
    $packages_table = $wpdb->prefix . 'cr_packages';
    $bookings_table = $wpdb->prefix . 'cr_bookings';

    $package_id = intval($_POST['package_id']);
    $package = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $packages_table WHERE id = %d AND status='active'",
        $package_id
    ));

    if (!$package) {
        wp_send_json_error(['message' => 'Invalid package selected']);
    }

    $pax = intval($_POST['number_of_pax']);
    if ($pax > $package->max_pax) {
        wp_send_json_error(['message' => 'Number of passengers exceeds package limit']);
    }
    
    $days = intval($_POST['number_of_days']);
    $city = bntm_cr_normalize_city($_POST['city'] ?? ($package->city ?? 'cebu'));
    $destination = sanitize_text_field($_POST['destination'] ?? '');
    $base_point = sanitize_text_field($_POST['base_point'] ?? '');
    $total_hours = floatval($_POST['total_hours'] ?? 0);

    $route = bntm_cr_find_route($city, $destination, $base_point);
    if (!$route) {
        wp_send_json_error(['message' => 'Please select a valid destination route.']);
    }
    $distance_km = floatval($route['distance_km']);
    $calc = bntm_cr_calculate_total($package->vehicle_category ?? bntm_cr_get_default_vehicle_category_slug(), $distance_km, $total_hours, $route);
    $package_amount = $calc['base_rate'];

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $package->business_id,
        'package_id' => $package_id,
        'city' => $city,
        'vehicle_category' => sanitize_text_field($package->vehicle_category ?? bntm_cr_get_default_vehicle_category_slug()),
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'customer_phone' => sanitize_text_field($_POST['customer_phone']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'base_point' => $route['base_point'],
        'destination' => $route['destination'],
        'distance_km' => $calc['distance_km'],
        'total_hours' => $calc['total_hours'],
        'number_of_days' => $days,
        'number_of_pax' => $pax,
        'base_fee' => $calc['base_fee'],
        'rate_per_km' => $calc['rate_per_km'],
        'overtime_rate' => $calc['overtime_rate'],
        'daily_rate' => $calc['base_fee'],
        'package_amount' => $package_amount,
        'excess_hours' => $calc['total_hours'] > 10 ? $calc['total_hours'] - 10 : 0,
        'surcharge_amount' => $calc['overtime_charge'],
        'pricing_type' => $calc['pricing_type'],
        'other_fees' => '[]',
        'total_amount' => $calc['total_cost'],
        'notes' => sanitize_textarea_field($_POST['notes']),
        'status' => 'contacted'
    ];

    $result = $wpdb->insert($bookings_table, $data, [
        '%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f','%f','%d','%d','%f','%f','%f','%f','%f','%f','%f','%s','%s','%f','%s','%s'
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Booking request submitted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to submit booking.']);
    }
}

function bntm_ajax_cr_save_payment_source() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $payment_source = sanitize_text_field($_POST['payment_source']);
    
    if (!in_array($payment_source, ['manual', 'op'])) {
        wp_send_json_error(['message' => 'Invalid payment source']);
    }

    bntm_set_setting('cr_payment_source', $payment_source);
    wp_send_json_success(['message' => 'Payment source saved!']);
}

function bntm_ajax_cr_add_payment_method() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $payment_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) $payment_methods = [];

    $payment_methods[] = [
        'type' => sanitize_text_field($_POST['payment_type']),
        'name' => sanitize_text_field($_POST['payment_name']),
        'description' => sanitize_textarea_field($_POST['payment_description']),
        'account_name' => sanitize_text_field($_POST['account_name']),
        'account_number' => sanitize_text_field($_POST['account_number'])
    ];

    bntm_set_setting('cr_payment_methods', json_encode($payment_methods));
    wp_send_json_success(['message' => 'Payment method added!']);
}

function bntm_ajax_cr_remove_payment_method() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $index = intval($_POST['index']);
    $payment_methods = json_decode(bntm_get_setting('cr_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods) || !isset($payment_methods[$index])) {
        wp_send_json_error(['message' => 'Payment method not found']);
    }

    array_splice($payment_methods, $index, 1);
    bntm_set_setting('cr_payment_methods', json_encode($payment_methods));
    
    wp_send_json_success(['message' => 'Payment method removed']);
}

function bntm_ajax_cr_save_booking_settings() {
    check_ajax_referer('cr_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $downpayment = intval($_POST['downpayment_percentage']);
    if ($downpayment < 0 || $downpayment > 100) {
        wp_send_json_error(['message' => 'Down payment must be between 0-100%']);
    }

    $pricing_rules = [];
    $pricing_rules_raw = json_decode(wp_unslash($_POST['pricing_rules_json'] ?? ''), true);
    if (is_array($pricing_rules_raw)) {
        foreach ($pricing_rules_raw as $slug => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $slug = sanitize_key($slug);
            $label = sanitize_text_field($rule['label'] ?? '');
            if ($slug === '' || $label === '') {
                continue;
            }

            $pricing_rules[$slug] = [
                'label' => $label,
                'base_fee' => floatval($rule['base_fee'] ?? 0),
                'rate_per_km' => floatval($rule['rate_per_km'] ?? 0),
                'overtime_rate' => floatval($rule['overtime_rate'] ?? 0),
            ];
        }
    }

    if (empty($pricing_rules)) {
        $pricing_rules = bntm_cr_get_default_pricing_rules();
    }

    $routes = bntm_cr_parse_route_definitions(wp_unslash($_POST['route_definitions'] ?? ''));
    if (empty($routes)) {
        $routes = bntm_cr_get_default_routes();
    }

    bntm_set_setting('cr_pricing_rules', wp_json_encode($pricing_rules));
    bntm_set_setting('cr_route_points', wp_json_encode($routes));
    bntm_set_setting('cr_downpayment_percentage', $downpayment);
    bntm_set_setting('cr_terms', sanitize_textarea_field($_POST['terms']));
    
    wp_send_json_success(['message' => 'Booking settings saved!']);
}

// Allow booking form to be embedded in iframe - use send_headers action
add_action('send_headers', 'bntm_cr_allow_iframe_embed');
function bntm_cr_allow_iframe_embed() {
    global $post;
    
    // Check if we're on a page (avoid errors on non-page requests)
    if (is_page() && isset($post->post_content)) {
        // Check if current page has the booking form shortcode
        if (has_shortcode($post->post_content, 'car_rental_booking_form') || 
            has_shortcode($post->post_content, 'car_rental_booking_form_embed')) {
            // Remove X-Frame-Options header to allow iframe embedding
            header_remove('X-Frame-Options');
            // Allow embedding from any origin
            header('Content-Security-Policy: frame-ancestors *');
            // Also set alternative header for older browsers
            header('X-Frame-Options: ALLOWALL');
        }
    }
}
