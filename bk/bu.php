<?php
/**
 * Module Name: Booking
 * Module Slug: bk
 * Description: Appointment booking system with calendar, time slots, and payment integration
 * Version: 1.0.0
 * Author: Your Name
 * Icon: 📅
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_BK_PATH', dirname(__FILE__) . '/');
define('BNTM_BK_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 */
function bntm_bk_get_pages() {
    return [
        'Booking' => '[bk_dashboard]',
        'Book Appointment' => '[bk_calendar]',
        'Booking Transaction' => '[bk_transaction]'
    ];
}

/**
 * Get module database tables
 */
function bntm_bk_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'bk_services' => "CREATE TABLE {$prefix}bk_services (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT,
            duration INT NOT NULL DEFAULT 60,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",
        
        'bk_bookings' => "CREATE TABLE {$prefix}bk_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            service_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED,
            booking_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20),
            customer_notes LONGTEXT,
            status VARCHAR(50) DEFAULT 'pending',
            payment_status VARCHAR(50) DEFAULT 'unpaid',
            amount DECIMAL(10,2) NOT NULL,
            tax DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_service (service_id),
            INDEX idx_customer (customer_id),
            INDEX idx_date (booking_date),
            INDEX idx_status (status),
            INDEX idx_payment_status (payment_status)
        ) {$charset};",
        
        'bk_operating_hours' => "CREATE TABLE {$prefix}bk_operating_hours (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            day_of_week INT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_open BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_day (business_id, day_of_week),
            INDEX idx_business (business_id)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 */
function bntm_bk_get_shortcodes() {
    return [
        'bk_calendar' => 'bntm_shortcode_bk_calendar',
        'bk_dashboard' => 'bntm_shortcode_bk_dashboard',
        'bk_transaction' => 'bntm_shortcode_bk_transaction'
    ];
}

/**
 * Create module tables
 */
function bntm_bk_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_bk_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Initialize default operating hours (9 AM - 5 PM, Monday to Friday)
    bntm_bk_initialize_operating_hours();
    
    return count($tables);
}

/**
 * Initialize default operating hours
 */
function bntm_bk_initialize_operating_hours() {
    global $wpdb;
    $business_id = get_current_user_id();
    $table = $wpdb->prefix . 'bk_operating_hours';
    
    // Check if already initialized
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE business_id = %d",
        $business_id
    ));
    
    if ($existing > 0) return;
    
    // Default: 9 AM - 5 PM, Monday to Friday
    for ($day = 1; $day <= 5; $day++) {
        $wpdb->insert($table, [
            'rand_id' => bntm_rand_id(),
            'business_id' => $business_id,
            'day_of_week' => $day,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_open' => 1
        ], ['%s', '%d', '%d', '%s', '%s', '%d']);
    }
    
    // Weekend closed by default
    for ($day = 6; $day <= 7; $day++) {
        $wpdb->insert($table, [
            'rand_id' => bntm_rand_id(),
            'business_id' => $business_id,
            'day_of_week' => $day,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_open' => 0
        ], ['%s', '%d', '%d', '%s', '%s', '%d']);
    }
}

// AJAX handlers
add_action('wp_ajax_bk_get_available_slots', 'bntm_ajax_bk_get_available_slots');
add_action('wp_ajax_nopriv_bk_get_available_slots', 'bntm_ajax_bk_get_available_slots');
add_action('wp_ajax_bk_book_appointment', 'bntm_ajax_bk_book_appointment');
add_action('wp_ajax_nopriv_bk_book_appointment', 'bntm_ajax_bk_book_appointment');
add_action('wp_ajax_bk_update_booking_status', 'bntm_ajax_bk_update_booking_status');

/* ---------- MAIN DASHBOARD SHORTCODE ---------- */
function bntm_shortcode_bk_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Booking dashboard.</div>';
    }
    
    $business_id = get_current_user_id();
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-booking-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=services" class="bntm-tab <?php echo $active_tab === 'services' ? 'active' : ''; ?>">Services</a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">Bookings</a>
            <a href="?tab=hours" class="bntm-tab <?php echo $active_tab === 'hours' ? 'active' : ''; ?>">Operating Hours</a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo bk_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'services'): ?>
                <?php echo bk_services_tab($business_id); ?>
            <?php elseif ($active_tab === 'bookings'): ?>
                <?php echo bk_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'hours'): ?>
                <?php echo bk_operating_hours_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo bk_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    
    $content = ob_get_clean();
    return bntm_universal_container('Booking', $content);
}

/* ---------- TAB FUNCTIONS ---------- */

function bk_overview_tab($business_id) {
    $stats = bk_get_dashboard_stats($business_id);
    $booking_page = get_page_by_path('book-appointment');
    $booking_url = $booking_page ? get_permalink($booking_page) : '';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <?php if ($booking_url): ?>
    <div class="bntm-form-section" style="background: #eff6ff; border-left: 4px solid #3b82f6;">
        <h3>Your Booking Page</h3>
        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
            <input type="text" id="booking-url" value="<?php echo esc_url($booking_url); ?>" readonly style="flex: 1; min-width: 300px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
            <button class="bntm-btn-secondary" id="copy-booking-url">Copy Link</button>
            <a href="<?php echo esc_url($booking_url); ?>" target="_blank" class="bntm-btn-primary">View Booking</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Services</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['total_services']); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Bookings</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['total_bookings']); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Revenue (This Month)</h3>
            <p class="bntm-stat-number"><?php echo bk_format_price($stats['monthly_revenue']); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Pending Bookings</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['pending_bookings']); ?></p>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Recent Bookings</h3>
        <?php echo bk_render_recent_bookings($business_id, 5); ?>
    </div>

    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    </style>
    
    <script>
    (function() {
        const copyBtn = document.getElementById('copy-booking-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const urlInput = document.getElementById('booking-url');
                urlInput.select();
                document.execCommand('copy');
                
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = 'Copy Link';
                }, 2000);
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bk_services_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE business_id = %d ORDER BY name ASC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('bk_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Services (<?php echo count($services); ?>)</h3>
        
        <?php if (empty($services)): ?>
            <p>No services yet. Add your first service to get started.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Duration (min)</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr data-service-id="<?php echo $service->id; ?>">
                            <td><?php echo esc_html($service->name); ?></td>
                            <td><?php echo esc_html($service->duration); ?></td>
                            <td><?php echo bk_format_price($service->price); ?></td>
                            <td>
                                <label class="bk-toggle">
                                    <input type="checkbox" 
                                           class="bk-toggle-status" 
                                           data-id="<?php echo $service->id; ?>"
                                           data-nonce="<?php echo $nonce; ?>"
                                           <?php checked($service->status, 'active'); ?>>
                                    <span class="bk-toggle-slider"></span>
                                </label>
                                <span class="status-text"><?php echo ucfirst($service->status); ?></span>
                            </td>
                            <td>
                                <button class="bntm-btn-small bk-edit-service" data-id="<?php echo $service->id; ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger bk-delete-service" data-id="<?php echo $service->id; ?>" data-nonce="<?php echo $nonce; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="bntm-form-section" style="background: #f9fafb; border-left: 4px solid #3b82f6;">
        <h3>Add New Service</h3>
        <form id="add-service-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Service Name *</label>
                <input type="text" name="service_name" placeholder="e.g., Hair Cut, Massage" required>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Duration (minutes) *</label>
                    <input type="number" name="duration" value="60" min="15" step="15" required>
                    <small>Must be in 15-minute intervals</small>
                </div>
                <div class="bntm-form-group">
                    <label>Price *</label>
                    <input type="number" name="price" value="0" min="0" step="0.01" required>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Service description"></textarea>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Add Service</button>
            <div id="service-message"></div>
        </form>
    </div>

    <style>
    .bk-toggle {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        margin-right: 10px;
    }
    .bk-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .bk-toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }
    .bk-toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    .bk-toggle input:checked + .bk-toggle-slider {
        background-color: #059669;
    }
    .bk-toggle input:checked + .bk-toggle-slider:before {
        transform: translateX(26px);
    }
    .status-text {
        font-size: 14px;
        color: #6b7280;
    }
    </style>
    
    <script>
    (function() {
        // Add service
        document.getElementById('add-service-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bk_add_service');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('service-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                if (json.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Add Service';
                }
            });
        });
        
        // Toggle service status
        document.querySelectorAll('.bk-toggle-status').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const formData = new FormData();
                formData.append('action', 'bk_toggle_service_status');
                formData.append('service_id', this.dataset.id);
                formData.append('status', this.checked ? 'active' : 'inactive');
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (!json.success) {
                        alert(json.data.message);
                        this.checked = !this.checked;
                    }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bk_bookings_tab($business_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, s.name as service_name 
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id = s.id
         WHERE b.business_id = %d
         ORDER BY b.booking_date DESC, b.start_time DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('bk_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>All Bookings (<?php echo count($bookings); ?>)</h3>
        <?php if (empty($bookings)): ?>
            <p>No bookings yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        $view_url = add_query_arg('id', $booking->rand_id, get_permalink(get_page_by_path('booking-transaction')));
                    ?>
                        <tr>
                            <td><?php echo esc_html($booking->service_name); ?></td>
                            <td>
                                <strong><?php echo esc_html($booking->customer_name); ?></strong><br>
                                <small><?php echo esc_html($booking->customer_email); ?></small>
                            </td>
                            <td>
                                <?php echo date('M d, Y', strtotime($booking->booking_date)); ?><br>
                                <small><?php echo date('H:i', strtotime($booking->start_time)); ?> - <?php echo date('H:i', strtotime($booking->end_time)); ?></small>
                            </td>
                            <td><?php echo bk_format_price($booking->total); ?></td>
                            <td>
                                <select class="bk-booking-status" data-booking-id="<?php echo esc_attr($booking->rand_id); ?>" data-nonce="<?php echo $nonce; ?>">
                                    <option value="pending" <?php selected($booking->status, 'pending'); ?>>Pending</option>
                                    <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                                    <option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>
                                    <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                                </select>
                            </td>
                            <td>
                                <span class="payment-badge payment-<?php echo esc_attr($booking->payment_status); ?>">
                                    <?php echo ucfirst($booking->payment_status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($view_url); ?>" class="bntm-btn-small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <style>
    .payment-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .payment-badge.payment-paid {
        background: #d1fae5;
        color: #065f46;
    }
    .payment-badge.payment-unpaid {
        background: #fef3c7;
        color: #92400e;
    }
    .payment-badge.payment-verified {
        background: #d1fae5;
        color: #065f46;
    }
    </style>
    
    <script>
    (function() {
        document.querySelectorAll('.bk-booking-status').forEach(select => {
            select.addEventListener('change', function() {
                const bookingId = this.getAttribute('data-booking-id');
                const newStatus = this.value;
                const nonce = this.getAttribute('data-nonce');
                
                const formData = new FormData();
                formData.append('action', 'bk_update_booking_status');
                formData.append('booking_id', bookingId);
                formData.append('status', newStatus);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Booking status updated!');
                    } else {
                        alert('Failed to update status');
                    }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bk_operating_hours_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bk_operating_hours';
    $hours = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE business_id = %d ORDER BY day_of_week ASC",
        $business_id
    ));
    
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $nonce = wp_create_nonce('bk_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Operating Hours</h3>
        <p>Set your business operating hours for each day of the week.</p>
        
        <table class="bntm-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Day</th>
                    <th>Open</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hours as $hour): 
                    $day_name = $days[$hour->day_of_week % 7];
                ?>
                    <tr>
                        <td><strong><?php echo $day_name; ?></strong></td>
                        <td>
                            <label class="bk-toggle">
                                <input type="checkbox" 
                                       class="bk-is-open" 
                                       data-id="<?php echo $hour->id; ?>"
                                       data-nonce="<?php echo $nonce; ?>"
                                       <?php checked($hour->is_open, 1); ?>>
                                <span class="bk-toggle-slider"></span>
                            </label>
                        </td>
                        <td>
                            <input type="time" 
                                   class="bk-start-time" 
                                   data-id="<?php echo $hour->id; ?>"
                                   data-nonce="<?php echo $nonce; ?>"
                                   value="<?php echo esc_attr($hour->start_time); ?>"
                                   style="padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                        </td>
                        <td>
                            <input type="time" 
                                   class="bk-end-time" 
                                   data-id="<?php echo $hour->id; ?>"
                                   data-nonce="<?php echo $nonce; ?>"
                                   value="<?php echo esc_attr($hour->end_time); ?>"
                                   style="padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                        </td>
                        <td>
                            <span class="open-status">
                                <?php echo $hour->is_open ? '✓ Open' : '✗ Closed'; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bntm-form-section" style="background: #f9fafb;">
        <h3>Booking Settings</h3>
        <form id="bk-booking-settings" class="bntm-form">
            <div class="bntm-form-group">
                <label>Time Slot Interval (minutes) *</label>
                <select name="slot_interval">
                    <option value="15" <?php selected(bntm_get_setting('bk_slot_interval', '30'), '15'); ?>>15 minutes</option>
                    <option value="30" <?php selected(bntm_get_setting('bk_slot_interval', '30'), '30'); ?>>30 minutes</option>
                    <option value="60" <?php selected(bntm_get_setting('bk_slot_interval', '30'), '60'); ?>>60 minutes</option>
                </select>
                <small>Minimum time between available slots</small>
            </div>
            <div class="bntm-form-group">
                <label>Days to Block Future Bookings *</label>
                <input type="number" name="advance_booking_days" value="<?php echo esc_attr(bntm_get_setting('bk_advance_booking_days', '30')); ?>" min="1">
                <small>Customers can book up to this many days in advance</small>
            </div>

            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="USD" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'USD'); ?>>USD - US Dollar</option>
                    <option value="EUR" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'EUR'); ?>>EUR - Euro</option>
                    <option value="GBP" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'GBP'); ?>>GBP - British Pound</option>
                    <option value="PHP" <?php selected(bntm_get_setting('bk_currency', 'USD'), 'PHP'); ?>>PHP - Philippine Peso</option>
                </select>
            </div>

            <div class="bntm-form-group">
                <label>Tax Rate (%)</label>
                <input type="number" name="tax_rate" step="0.01" value="<?php echo esc_attr(bntm_get_setting('bk_tax_rate', '0')); ?>">
            </div>

            <button type="submit" class="bntm-btn-primary">Save Booking Settings</button>
            <div id="booking-settings-message"></div>
        </form>
    </div>

    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    (function() {
        // Toggle is_open
        document.querySelectorAll('.bk-is-open').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateOperatingHour(this.dataset.id, 'is_open', this.checked ? 1 : 0, this.dataset.nonce);
                const openStatus = this.closest('tr').querySelector('.open-status');
                openStatus.textContent = this.checked ? '✓ Open' : '✗ Closed';
            });
        });

        // Update start time
        document.querySelectorAll('.bk-start-time').forEach(input => {
            input.addEventListener('change', function() {
                updateOperatingHour(this.dataset.id, 'start_time', this.value, this.dataset.nonce);
            });
        });

        // Update end time
        document.querySelectorAll('.bk-end-time').forEach(input => {
            input.addEventListener('change', function() {
                updateOperatingHour(this.dataset.id, 'end_time', this.value, this.dataset.nonce);
            });
        });

        function updateOperatingHour(hourId, field, value, nonce) {
            const formData = new FormData();
            formData.append('action', 'bk_update_operating_hour');
            formData.append('hour_id', hourId);
            formData.append('field', field);
            formData.append('value', value);
            formData.append('nonce', nonce);

            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (!json.success) {
                    alert(json.data.message || 'Failed to update');
                }
            });
        }

        // Save booking settings
        document.getElementById('bk-booking-settings').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'bk_save_settings');

            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('booking-settings-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Booking Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bk_settings_tab($business_id) {
    $payment_source = bntm_get_setting('bk_payment_source', 'bk');
    $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) {
        $payment_methods = [];
    }
    
    $nonce = wp_create_nonce('bk_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Payment Configuration</h3>
        <p>Choose where to manage payment methods for your booking checkout.</p>
        
        <div class="bntm-form-group">
            <select id="payment-source-select" name="payment_source">
                <option value="bk" <?php selected($payment_source, 'bk'); ?>>MANUAL</option>
                <?php if (bntm_is_module_enabled('op') && bntm_is_module_visible('op')): ?>
                    <option value="op" <?php selected($payment_source, 'op'); ?>>AUTOMATIC - ONLINE PAYMENT API</option>
                <?php else: ?>
                    <option value="op" disabled>AUTOMATIC - ONLINE PAYMENT API - Need OP Module</option>
                <?php endif; ?>
            </select>
            <small>If you're using the Online Payment module, you can share those payment methods here.</small>
        </div>
        
        <button type="button" id="save-payment-source-btn" class="bntm-btn-primary">Save Payment Source</button>
        <div id="payment-source-message"></div>
    </div>

    <div class="bntm-form-section" id="bk-payment-methods-section" style="<?php echo $payment_source === 'op' ? 'display: none;' : ''; ?>">
        <h3>Booking Payment Methods</h3>
        <p>Configure manual payment methods for your bookings.</p>
        
        <div id="payment-methods-list">
            <?php if (empty($payment_methods)): ?>
                <p style="color: #6b7280;">No payment methods configured yet.</p>
            <?php else: ?>
                <?php foreach ($payment_methods as $index => $method): ?>
                    <div class="payment-method-item" data-index="<?php echo $index; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($method['name']); ?></strong>
                                <span style="color: #6b7280; margin-left: 10px;"><?php echo esc_html($method['type']); ?></span>
                            </div>
                            <button class="bntm-btn-small bntm-btn-danger remove-payment-method" data-index="<?php echo $index; ?>">Remove</button>
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
        
        <div style="margin-top: 20px; padding: 20px; background: #f9fafb; border-radius: 8px;">
            <h4 style="margin-top: 0;">Add Payment Method</h4>
            <form id="add-payment-method-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Payment Type *</label>
                    <select name="payment_type" id="payment-type-select" required>
                        <option value="">Select Type</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="gcash">GCash</option>
                        <option value="manual">Cash/Check</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Display Name *</label>
                    <input type="text" name="payment_name" placeholder="e.g., BDO Bank Transfer" required>
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
                    <textarea name="payment_description" rows="3" placeholder="Payment instructions for customers"></textarea>
                </div>
                
                <button type="submit" class="bntm-btn-primary">Add Payment Method</button>
            </form>
        </div>
        <div id="payment-method-message"></div>
    </div>

    <style>
    .payment-method-item {
        padding: 15px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    </style>

    <script>
    (function() {
        const paymentSourceSelect = document.getElementById('payment-source-select');
        const bkPaymentSection = document.getElementById('bk-payment-methods-section');
        const paymentSourceMessage = document.getElementById('payment-source-message');
        
        paymentSourceSelect.addEventListener('change', function() {
            if (this.value === 'op') {
                bkPaymentSection.style.display = 'none';
            } else {
                bkPaymentSection.style.display = 'block';
            }
        });
        
        document.getElementById('save-payment-source-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'bk_save_payment_source');
            formData.append('payment_source', paymentSourceSelect.value);
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                paymentSourceMessage.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Payment Source';
            });
        });
        
        // Add payment method
        document.getElementById('add-payment-method-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bk_add_payment_method');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('payment-method-message').innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    document.getElementById('payment-method-message').innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Add Payment Method';
                }
            });
        });
        
        // Remove payment method
        document.querySelectorAll('.remove-payment-method').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this payment method?')) return;
                
                const index = this.getAttribute('data-index');
                const formData = new FormData();
                formData.append('action', 'bk_remove_payment_method');
                formData.append('index', index);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) location.reload();
                    else alert(json.data.message);
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- CALENDAR & BOOKING PAGE ---------- */
function bntm_shortcode_bk_calendar() {
    global $wpdb;
    $services_table = $wpdb->prefix . 'bk_services';
    $services = $wpdb->get_results("SELECT * FROM $services_table WHERE status = 'active' ORDER BY name ASC");
    
    if (empty($services)) {
        return '<div class="bntm-container"><p>No services available for booking.</p></div>';
    }
    
    $nonce = wp_create_nonce('bk_calendar_nonce');
    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+' . bntm_get_setting('bk_advance_booking_days', '30') . ' days'));
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bk-calendar-container">
        <div class="bk-calendar-wrapper">
            <h1>Book an Appointment</h1>
            
            <div class="bk-calendar-content">
                <!-- Calendar Section -->
                <div class="bk-calendar-section">
                    <h2>Select Date</h2>
                    <input type="date" 
                           id="bk-date-picker" 
                           min="<?php echo $min_date; ?>" 
                           max="<?php echo $max_date; ?>"
                           class="bk-date-input">
                </div>
                
                <!-- Services Section -->
                <div class="bk-services-section">
                    <h2>Select Service</h2>
                    <div class="bk-services-list">
                        <?php foreach ($services as $service): ?>
                            <div class="bk-service-card" data-service-id="<?php echo $service->id; ?>">
                                <h3><?php echo esc_html($service->name); ?></h3>
                                <p class="bk-service-duration">⏱ <?php echo $service->duration; ?> min</p>
                                <p class="bk-service-price"><?php echo bk_format_price($service->price); ?></p>
                                <?php if ($service->description): ?>
                                    <p class="bk-service-desc"><?php echo esc_html($service->description); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Time Slots Section -->
                <div class="bk-slots-section" id="bk-slots-container" style="display: none;">
                    <h2>Select Time Slot</h2>
                    <div id="bk-slots-loading" style="text-align: center; padding: 20px; display: none;">
                        <p>Loading available slots...</p>
                    </div>
                    <div class="bk-slots-grid" id="bk-slots-grid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="booking-modal" class="bk-modal">
        <div class="bk-modal-overlay"></div>
        <div class="bk-modal-content">
            <button class="bk-modal-close">&times;</button>
            <h2>Complete Your Booking</h2>
            
            <form id="booking-form" class="bntm-form">
                <div class="bk-booking-summary">
                    <p><strong>Service:</strong> <span id="modal-service-name"></span></p>
                    <p><strong>Date & Time:</strong> <span id="modal-booking-datetime"></span></p>
                    <p><strong>Duration:</strong> <span id="modal-duration"></span> minutes</p>
                    <hr>
                    <p style="font-size: 18px;"><strong>Price:</strong> <span id="modal-price" style="color: #059669;"></span></p>
                </div>
                
                <div class="bntm-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="customer_name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Email *</label>
                    <input type="email" name="customer_email" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Phone *</label>
                    <input type="tel" name="customer_phone" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Additional Notes</label>
                    <textarea name="customer_notes" rows="3" placeholder="Any special requests or notes..."></textarea>
                </div>
                
                <h3>Payment Method</h3>
                <div class="bk-payment-methods" id="bk-payment-methods">
                    <!-- Payment methods will be loaded here -->
                </div>
                
                <input type="hidden" name="service_id" id="hidden-service-id">
                <input type="hidden" name="booking_date" id="hidden-booking-date">
                <input type="hidden" name="start_time" id="hidden-start-time">
                <input type="hidden" name="end_time" id="hidden-end-time">
                <input type="hidden" name="amount" id="hidden-amount">
                
                <button type="submit" class="bntm-btn-primary bntm-btn-large" style="width: 100%; margin-top: 20px;">
                    Complete Booking
                </button>
                <div id="booking-message"></div>
            </form>
        </div>
    </div>

    <style>
    .bk-calendar-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .bk-calendar-wrapper {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .bk-calendar-wrapper h1 {
        text-align: center;
        margin-bottom: 40px;
        font-size: 32px;
        color: #1f2937;
    }
    
    .bk-calendar-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 30px;
    }
    
    .bk-calendar-section h2,
    .bk-services-section h2,
    .bk-slots-section h2 {
        font-size: 20px;
        margin-bottom: 15px;
        color: #1f2937;
    }
    
    .bk-date-input {
        width: 100%;
        padding: 12px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 16px;
        cursor: pointer;
    }
    
    .bk-date-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .bk-services-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .bk-service-card {
        padding: 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .bk-service-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }
    
    .bk-service-card.selected {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .bk-service-card h3 {
        margin: 0 0 8px 0;
        font-size: 16px;
        color: #1f2937;
    }
    
    .bk-service-duration {
        margin: 4px 0;
        font-size: 14px;
        color: #6b7280;
    }
    
    .bk-service-price {
        margin: 8px 0 0 0;
        font-size: 18px;
        font-weight: 700;
        color: #059669;
    }
    
    .bk-service-desc {
        margin: 8px 0 0 0;
        font-size: 13px;
        color: #6b7280;
    }
    
    .bk-slots-section {
        grid-column: 1 / -1;
    }
    
    .bk-slots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 12px;
    }
    
    .bk-slot-button {
        padding: 12px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
        text-align: center;
    }
    
    .bk-slot-button:hover:not(:disabled) {
        border-color: #3b82f6;
        color: #3b82f6;
    }
    
    .bk-slot-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f3f4f6;
    }
    
    .bk-slot-button.selected {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    /* Modal Styles */
    .bk-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        animation: fadeIn 0.3s ease;
    }
    
    .bk-modal.active {
        display: block;
    }
    
    .bk-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }
    
    .bk-modal-content {
        position: relative;
        max-width: 600px;
        margin: 40px auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideUp 0.3s ease;
        max-height: calc(100vh - 80px);
        overflow-y: auto;
        padding: 30px;
    }
    
    .bk-modal-close {
        position: absolute;
        top: 15px;
        right: 15px;
        background: #f3f4f6;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        color: #6b7280;
    }
    
    .bk-modal-close:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    
    .bk-modal-content h2 {
        margin-top: 0;
        color: #1f2937;
    }
    
    .bk-booking-summary {
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    
    .bk-booking-summary p {
        margin: 8px 0;
    }
    
    .bk-booking-summary hr {
        margin: 12px 0;
        border: none;
        border-top: 1px solid #e5e7eb;
    }
    
    .bk-payment-methods {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .bk-payment-option {
        cursor: pointer;
    }
    
    .bk-payment-option input[type="radio"] {
        display: none;
    }
    
    .bk-payment-card {
        padding: 15px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        transition: all 0.2s;
        background: white;
    }
    
    .bk-payment-option input[type="radio"]:checked + .bk-payment-card {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .bk-payment-card:hover {
        border-color: #3b82f6;
    }
    
    .bk-payment-card strong {
        display: block;
        margin-bottom: 8px;
        color: #1f2937;
    }
    
    .bk-payment-card small {
        display: block;
        color: #6b7280;
        font-size: 12px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { 
            opacity: 0;
            transform: translateY(30px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @media (max-width: 768px) {
        .bk-calendar-content {
            grid-template-columns: 1fr;
        }
        
        .bk-modal-content {
            margin: 20px;
            max-height: calc(100vh - 40px);
        }
    }
    </style>

    <script>
    (function() {
        let selectedService = null;
        let selectedDate = null;
        let selectedTime = null;
        let selectedEndTime = null;
        const modal = document.getElementById('booking-modal');
        const modalOverlay = modal.querySelector('.bk-modal-overlay');
        const modalClose = modal.querySelector('.bk-modal-close');
        const datePicker = document.getElementById('bk-date-picker');
        const slotsContainer = document.getElementById('bk-slots-container');
        const slotsGrid = document.getElementById('bk-slots-grid');
        const slotsLoading = document.getElementById('bk-slots-loading');
        
        // Service selection
        document.querySelectorAll('.bk-service-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.bk-service-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedService = {
                    id: this.dataset.serviceId,
                    name: this.querySelector('h3').textContent,
                    price: parseFloat(this.querySelector('.bk-service-price').textContent.replace(/[^\d.]/g, '')),
                    duration: parseInt(this.querySelector('.bk-service-duration').textContent.match(/\d+/)[0])
                };
                
                // Load slots if date is selected
                if (selectedDate) {
                    loadSlots();
                }
            });
        });
        
        // Date selection
        datePicker.addEventListener('change', function() {
            selectedDate = this.value;
            if (selectedService) {
                loadSlots();
            }
        });
        
        function loadSlots() {
            if (!selectedDate || !selectedService) return;
            
            slotsLoading.style.display = 'block';
            slotsGrid.innerHTML = '';
            
            const formData = new FormData();
            formData.append('action', 'bk_get_available_slots');
            formData.append('date', selectedDate);
            formData.append('service_id', selectedService.id);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                slotsLoading.style.display = 'none';
                
                if (json.success && json.data.slots && json.data.slots.length > 0) {
                    slotsContainer.style.display = 'block';
                    json.data.slots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'bk-slot-button';
                        btn.textContent = slot.start_time;
                        btn.addEventListener('click', function() {
                            document.querySelectorAll('.bk-slot-button').forEach(b => b.classList.remove('selected'));
                            btn.classList.add('selected');
                            selectedTime = slot.start_time;
                            selectedEndTime = slot.end_time;
                        });
                        slotsGrid.appendChild(btn);
                    });
                } else {
                    slotsContainer.style.display = 'block';
                    slotsGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #6b7280;">No available slots for this date.</p>';
                }
            })
            .catch(err => {
                slotsLoading.style.display = 'none';
                slotsGrid.innerHTML = '<p style="grid-column: 1/-1; color: #dc2626;">Error loading slots</p>';
            });
        }
        
        // Slot button click opens modal
        slotsGrid.addEventListener('click', function(e) {
            if (e.target.classList.contains('bk-slot-button') && !e.target.disabled) {
                if (selectedTime && selectedService && selectedDate) {
                    openBookingModal();
                }
            }
        });
        
        function openBookingModal() {
            // Populate modal with booking details
            document.getElementById('modal-service-name').textContent = selectedService.name;
            document.getElementById('modal-duration').textContent = selectedService.duration;
            
            const dateObj = new Date(selectedDate + 'T00:00:00');
            const dateStr = dateObj.toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
            document.getElementById('modal-booking-datetime').textContent = dateStr + ' at ' + selectedTime;
            
            document.getElementById('modal-price').textContent = formatPrice(selectedService.price);
            
            // Hidden inputs
            document.getElementById('hidden-service-id').value = selectedService.id;
            document.getElementById('hidden-booking-date').value = selectedDate;
            document.getElementById('hidden-start-time').value = selectedTime;
            document.getElementById('hidden-end-time').value = selectedEndTime;
            document.getElementById('hidden-amount').value = selectedService.price;
            
            // Load payment methods
            loadPaymentMethods();
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function loadPaymentMethods() {
            const container = document.getElementById('bk-payment-methods');
            container.innerHTML = '';
            
            const formData = new FormData();
            formData.append('action', 'bk_get_payment_methods');
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data.methods) {
                    json.data.methods.forEach((method, index) => {
                        const label = document.createElement('label');
                        label.className = 'bk-payment-option';
                        
                        const radio = document.createElement('input');
                        radio.type = 'radio';
                        radio.name = 'payment_method';
                        radio.value = index;
                        if (index === 0) radio.checked = true;
                        
                        const card = document.createElement('div');
                        card.className = 'bk-payment-card';
                        card.innerHTML = '<strong>' + method.name + '</strong>';
                        if (method.description) {
                            card.innerHTML += '<small>' + method.description + '</small>';
                        }
                        
                        label.appendChild(radio);
                        label.appendChild(card);
                        container.appendChild(label);
                    });
                }
            });
        }
        
        function formatPrice(amount) {
            return '<?php echo bntm_get_setting('bk_currency', 'USD') === 'PHP' ? '₱' : '$'; ?>' + parseFloat(amount).toFixed(2);
        }
        
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            selectedTime = null;
            selectedEndTime = null;
        }
        
        modalClose.addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', closeModal);
        
        // Booking form submit
        document.getElementById('booking-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedTime) {
                alert('Please select a time slot');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('action', 'bk_book_appointment');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => {
                        window.location.href = json.data.redirect;
                    }, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Complete Booking';
                }
            })
            .catch(err => {
                document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Complete Booking';
            });
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Book Appointment', $content);
}

/* ---------- TRANSACTION PAGE ---------- */
function bntm_shortcode_bk_transaction() {
    $booking_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($booking_id)) {
        return '<div class="bntm-container"><p>Invalid booking ID.</p></div>';
    }

    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, s.name as service_name, s.duration 
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id = s.id
         WHERE b.rand_id = %s",
        $booking_id
    ));

    if (!$booking) {
        return '<div class="bntm-container"><p>Booking not found.</p></div>';
    }
    
    $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
    $payment_method_data = null;
    
    if (is_array($payment_methods) && !empty($booking->payment_method)) {
        foreach ($payment_methods as $method) {
            if ($method['name'] === $booking->payment_method) {
                $payment_method_data = $method;
                break;
            }
        }
    }

    ob_start();
    ?>
    <div class="bntm-container">
        <div class="bntm-content">
            <div class="bk-transaction-banner status-<?php echo esc_attr($booking->status); ?>">
                <h2>Booking Status: <?php echo esc_html(ucfirst($booking->status)); ?></h2>
                <p class="booking-id">Booking ID: #<?php echo esc_html($booking->rand_id); ?></p>
            </div>

            <div class="bntm-form-section">
                <h3>Booking Information</h3>
                <table class="bk-transaction-table">
                    <tr>
                        <td><strong>Service:</strong></td>
                        <td><?php echo esc_html($booking->service_name); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date:</strong></td>
                        <td><?php echo date('F j, Y', strtotime($booking->booking_date)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Time:</strong></td>
                        <td><?php echo date('g:i A', strtotime($booking->start_time)); ?> - <?php echo date('g:i A', strtotime($booking->end_time)); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Duration:</strong></td>
                        <td><?php echo esc_html($booking->duration); ?> minutes</td>
                    </tr>
                    <tr>
                        <td><strong>Booking Status:</strong></td>
                        <td><span class="bk-status-badge status-<?php echo esc_attr($booking->status); ?>">
                            <?php echo esc_html(ucfirst($booking->status)); ?>
                        </span></td>
                    </tr>
                </table>
            </div>

            <div class="bntm-form-section">
                <h3>Customer Details</h3>
                <table class="bk-transaction-table">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo esc_html($booking->customer_name); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo esc_html($booking->customer_email); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo esc_html($booking->customer_phone); ?></td>
                    </tr>
                    <?php if (!empty($booking->customer_notes)): ?>
                    <tr>
                        <td><strong>Notes:</strong></td>
                        <td><?php echo esc_html($booking->customer_notes); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="bntm-form-section">
                <h3>Payment Information</h3>
                <table class="bk-transaction-table">
                    <tr>
                        <td><strong>Amount:</strong></td>
                        <td><?php echo bk_format_price($booking->amount); ?></td>
                    </tr>
                    <?php if ($booking->tax > 0): ?>
                    <tr>
                        <td><strong>Tax:</strong></td>
                        <td><?php echo bk_format_price($booking->tax); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="font-size: 18px;">
                        <td><strong>Total:</strong></td>
                        <td style="color: #059669; font-weight: 700;"><?php echo bk_format_price($booking->total); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td><?php echo esc_html($booking->payment_method); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment Status:</strong></td>
                        <td>
                            <span class="bk-payment-badge payment-<?php echo esc_attr($booking->payment_status); ?>">
                                <?php echo ucfirst($booking->payment_status); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if (!empty($booking->transaction_id)): ?>
                    <tr>
                        <td><strong>Transaction ID:</strong></td>
                        <td><?php echo esc_html($booking->transaction_id); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php if ($payment_method_data && $booking->payment_status === 'unpaid'): ?>
                <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                    <strong>Payment Details:</strong><br>
                    <?php if (!empty($payment_method_data['account_name'])): ?>
                        Account Name: <strong><?php echo esc_html($payment_method_data['account_name']); ?></strong><br>
                    <?php endif; ?>
                    <?php if (!empty($payment_method_data['account_number'])): ?>
                        Account Number: <strong><?php echo esc_html($payment_method_data['account_number']); ?></strong><br>
                    <?php endif; ?>
                    <?php if (!empty($payment_method_data['payment_description'])): ?>
                        <br><?php echo nl2br(esc_html($payment_method_data['payment_description'])); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <a href="<?php echo get_permalink(get_page_by_path('book-appointment')); ?>" class="bntm-btn bntm-btn-primary">
                    Book Another Appointment
                </a>
            </div>
        </div>
    </div>

    <style>
    .bk-transaction-banner {
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 30px;
    }
    
    .bk-transaction-banner.status-pending {
        background: #fef3c7;
        border: 2px solid #f59e0b;
    }
    
    .bk-transaction-banner.status-confirmed,
    .bk-transaction-banner.status-completed {
        background: #d1fae5;
        border: 2px solid #059669;
    }
    
    .bk-transaction-banner.status-cancelled {
        background: #fee2e2;
        border: 2px solid #dc2626;
    }
    
    .bk-transaction-banner h2 {
        margin: 0 0 10px 0;
        color: #1f2937;
    }
    
    .booking-id {
        font-size: 18px;
        color: #6b7280;
        margin: 0;
    }
    
    .bk-transaction-table {
        width: 100%;
    }
    
    .bk-transaction-table td {
        padding: 12px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .bk-transaction-table td:first-child {
        width: 200px;
        color: #6b7280;
    }
    
    .bk-status-badge,
    .bk-payment-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .bk-status-badge.status-pending,
    .bk-payment-badge.payment-unpaid {
        background: #fef3c7;
        color: #92400e;
    }
    
    .bk-status-badge.status-confirmed,
    .bk-status-badge.status-completed,
    .bk-payment-badge.payment-paid,
    .bk-payment-badge.payment-verified {
        background: #d1fae5;
        color: #065f46;
    }
    
    .bk-status-badge.status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    </style>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Booking Transaction', $content);
}

add_shortcode('bk_transaction', 'bntm_shortcode_bk_transaction');

/* ---------- AJAX HANDLERS ---------- */

/**
 * Get available time slots for a date and service
 */
function bntm_ajax_bk_get_available_slots() {
    global $wpdb;
    
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    
    if (empty($date) || empty($service_id)) {
        wp_send_json_error(['message' => 'Missing parameters']);
    }
    
    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        wp_send_json_error(['message' => 'Invalid date format']);
    }
    
    // Get service
    $services_table = $wpdb->prefix . 'bk_services';
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $services_table WHERE id = %d AND status = 'active'",
        $service_id
    ));
    
    if (!$service) {
        wp_send_json_error(['message' => 'Service not found']);
    }
    
    // Get operating hours for the day
    $hours_table = $wpdb->prefix . 'bk_operating_hours';
    $dayOfWeek = date('w', strtotime($date)); // 0=Sunday, 6=Saturday
    
    $operating_hour = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $hours_table WHERE day_of_week = %d AND business_id = %d",
        $dayOfWeek, get_current_user_id()
    ));
    
    if (!$operating_hour || !$operating_hour->is_open) {
        wp_send_json_success(['slots' => [], 'message' => 'Business closed on this day']);
    }
    
    // Get all bookings for this date
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT start_time, end_time FROM $bookings_table 
         WHERE booking_date = %s AND status IN ('pending', 'confirmed') AND service_id = %d",
        $date, $service_id
    ));
    
    // Build booked times array
    $booked_times = [];
    foreach ($bookings as $booking) {
        $booked_times[] = [
            'start' => strtotime($booking->start_time),
            'end' => strtotime($booking->end_time)
        ];
    }
    
    // Generate available slots
    $slot_interval = intval(bntm_get_setting('bk_slot_interval', '30'));
    $start = strtotime($operating_hour->start_time);
    $end = strtotime($operating_hour->end_time);
    $service_duration = $service->duration * 60; // Convert to seconds
    
    $slots = [];
    $current = $start;
    
    while ($current + $service_duration <= $end) {
        $slot_end = $current + $service_duration;
        $is_available = true;
        
        // Check if slot overlaps with any booking
        foreach ($booked_times as $booked) {
            if (!($slot_end <= $booked['start'] || $current >= $booked['end'])) {
                $is_available = false;
                break;
            }
        }
        
        if ($is_available) {
            $slots[] = [
                'start_time' => date('H:i', $current),
                'end_time' => date('H:i', $slot_end)
            ];
        }
        
        $current += $slot_interval * 60; // Move to next slot
    }
    
    wp_send_json_success(['slots' => $slots]);
}

/**
 * Book an appointment
 */
function bntm_ajax_bk_book_appointment() {
    check_ajax_referer('bk_calendar_nonce', 'nonce');
    
    global $wpdb;
    
    $service_id = intval($_POST['service_id'] ?? 0);
    $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
    $start_time = sanitize_text_field($_POST['start_time'] ?? '');
    $end_time = sanitize_text_field($_POST['end_time'] ?? '');
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_notes = sanitize_textarea_field($_POST['customer_notes'] ?? '');
    $payment_method_index = intval($_POST['payment_method'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    
    // Validation
    if (empty($service_id) || empty($booking_date) || empty($start_time) || empty($customer_name) || empty($customer_email)) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
    }
    
    // Get service
    $services_table = $wpdb->prefix . 'bk_services';
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $services_table WHERE id = %d AND status = 'active'",
        $service_id
    ));
    
    if (!$service) {
        wp_send_json_error(['message' => 'Service not found']);
    }
    
    // Check slot availability again (security)
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $conflict = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $bookings_table 
         WHERE booking_date = %s 
         AND service_id = %d
         AND status IN ('pending', 'confirmed')
         AND (
            (start_time < %s AND end_time > %s)
            OR (start_time >= %s AND start_time < %s)
         )",
        $booking_date, $service_id, $end_time, $start_time, $start_time, $end_time
    ));
    
    if ($conflict) {
        wp_send_json_error(['message' => 'This time slot is no longer available. Please select another.']);
    }
    
    // Get payment source
    $payment_source = bntm_get_setting('bk_payment_source', 'bk');
    $payment_method = null;
    
    if ($payment_source === 'bk') {
        $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
        if (!is_array($payment_methods) || !isset($payment_methods[$payment_method_index])) {
            wp_send_json_error(['message' => 'Invalid payment method']);
        }
        $payment_method = $payment_methods[$payment_method_index];
    }
    
    // Calculate totals
    $tax_rate = floatval(bntm_get_setting('bk_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax;
    
    // Determine status based on payment method
    $payment_status = 'unpaid';
    $booking_status = 'pending';
    
    if ($payment_source === 'op') {
        // Will be handled by OP integration
        $booking_status = 'pending';
        $payment_status = 'unpaid';
    } else {
        // Manual payment - pending verification
        $booking_status = 'pending';
        $payment_status = 'unpaid';
    }
    
    // Create booking
    $booking_rand_id = bntm_rand_id(15);
    $business_id = get_current_user_id();
    $customer_id = is_user_logged_in() ? get_current_user_id() : 0;
    
    $booking_inserted = $wpdb->insert($bookings_table, [
        'rand_id' => $booking_rand_id,
        'business_id' => $business_id,
        'service_id' => $service_id,
        'customer_id' => $customer_id,
        'booking_date' => $booking_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_notes' => $customer_notes,
        'status' => $booking_status,
        'payment_status' => $payment_status,
        'amount' => $amount,
        'tax' => $tax,
        'total' => $total,
        'payment_method' => $payment_method ? $payment_method['name'] : 'Not set',
        'created_at' => current_time('mysql')
    ], [
        '%s','%d','%d','%d','%s','%s','%s',
        '%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s'
    ]);
    
    if (!$booking_inserted) {
        wp_send_json_error(['message' => 'Failed to create booking']);
    }
    
    // Save booking metadata
    update_option('bk_booking_' . $booking_rand_id . '_data', [
        'service_name' => $service->name,
        'duration' => $service->duration,
        'payment_method' => $payment_method,
        'payment_source' => $payment_source
    ]);
    
    // Send confirmation email
    bk_send_booking_confirmation_email($customer_email, [
        'name' => $customer_name,
        'service' => $service->name,
        'date' => $booking_date,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'total' => $total,
        'booking_id' => $booking_rand_id,
        'payment_status' => $payment_status
    ]);
    
    $transaction_page = get_page_by_path('booking-transaction');
    $redirect = $transaction_page ? add_query_arg('id', $booking_rand_id, get_permalink($transaction_page)) : home_url();
    
    wp_send_json_success([
        'message' => 'Booking confirmed! Check your email for details.',
        'redirect' => $redirect,
        'booking_id' => $booking_rand_id
    ]);
}

/**
 * Get payment methods
 */
add_action('wp_ajax_bk_get_payment_methods', 'bntm_ajax_bk_get_payment_methods');
add_action('wp_ajax_nopriv_bk_get_payment_methods', 'bntm_ajax_bk_get_payment_methods');

function bntm_ajax_bk_get_payment_methods() {
    $payment_source = bntm_get_setting('bk_payment_source', 'bk');
    
    if ($payment_source === 'op') {
        // Get OP payment methods
        global $wpdb;
        $methods_table = $wpdb->prefix . 'op_payment_methods';
        $business_id = get_current_user_id();
        $op_methods = $wpdb->get_results(
            "SELECT * FROM $methods_table WHERE business_id = $business_id AND is_active = 1 ORDER BY priority ASC"
        );
        
        $methods = [];
        if ($op_methods) {
            foreach ($op_methods as $method) {
                $config = json_decode($method->config, true);
                $methods[] = [
                    'id' => $method->id,
                    'type' => $method->gateway,
                    'name' => $method->name,
                    'description' => $config['instructions'] ?? ''
                ];
            }
        }
    } else {
        // Get booking payment methods
        $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
        $methods = is_array($payment_methods) ? $payment_methods : [];
    }
    
    if (empty($methods)) {
        $methods = [['type' => 'manual', 'name' => 'Default Payment', 'description' => 'Contact for payment details']];
    }
    
    wp_send_json_success(['methods' => $methods]);
}

/**
 * Update operating hour
 */
add_action('wp_ajax_bk_update_operating_hour', 'bntm_ajax_bk_update_operating_hour');

function bntm_ajax_bk_update_operating_hour() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_operating_hours';
    $hour_id = intval($_POST['hour_id']);
    $field = sanitize_text_field($_POST['field']);
    $value = sanitize_text_field($_POST['value']);
    
    if (!in_array($field, ['start_time', 'end_time', 'is_open'])) {
        wp_send_json_error(['message' => 'Invalid field']);
    }
    
    $update_data = [$field => $value];
    $update_format = ['%s'];
    
    if ($field === 'is_open') {
        $update_data[$field] = $value ? 1 : 0;
        $update_format = ['%d'];
    }
    
    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => $hour_id],
        $update_format,
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update']);
    }
}

/**
 * Add service
 */
add_action('wp_ajax_bk_add_service', 'bntm_ajax_bk_add_service');

function bntm_ajax_bk_add_service() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $business_id = get_current_user_id();
    
    $service_name = sanitize_text_field($_POST['service_name'] ?? '');
    $duration = intval($_POST['duration'] ?? 60);
    $price = floatval($_POST['price'] ?? 0);
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    
    if (empty($service_name)) {
        wp_send_json_error(['message' => 'Service name is required']);
    }
    
    if ($duration < 15 || $duration % 15 !== 0) {
        wp_send_json_error(['message' => 'Duration must be in 15-minute intervals']);
    }
    
    $result = $wpdb->insert($table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'name' => $service_name,
        'duration' => $duration,
        'price' => $price,
        'description' => $description,
        'status' => 'active',
        'created_at' => current_time('mysql')
    ], ['%s', '%d', '%s', '%d', '%f', '%s', '%s', '%s']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Service added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add service']);
    }
}

/**
 * Toggle service status
 */
add_action('wp_ajax_bk_toggle_service_status', 'bntm_ajax_bk_toggle_service_status');

function bntm_ajax_bk_toggle_service_status() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $service_id = intval($_POST['service_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $result = $wpdb->update(
        $table,
        ['status' => $status],
        ['id' => $service_id],
        ['%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Service updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update']);
    }
}

/**
 * Update booking status
 */
function bntm_ajax_bk_update_booking_status() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_bookings';
    $booking_id = sanitize_text_field($_POST['booking_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $result = $wpdb->update(
        $table,
        ['status' => $status],
        ['rand_id' => $booking_id],
        ['%s'],
        ['%s']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Booking status updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update booking']);
    }
}

/**
 * Save payment source
 */
add_action('wp_ajax_bk_save_payment_source', 'bntm_ajax_bk_save_payment_source');

function bntm_ajax_bk_save_payment_source() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $payment_source = sanitize_text_field($_POST['payment_source']);
    
    if (!in_array($payment_source, ['bk', 'op'])) {
        wp_send_json_error(['message' => 'Invalid payment source']);
    }
    
    bntm_set_setting('bk_payment_source', $payment_source);
    wp_send_json_success(['message' => 'Payment source saved successfully!']);
}

/**
 * Add payment method
 */
add_action('wp_ajax_bk_add_payment_method', 'bntm_ajax_bk_add_payment_method');

function bntm_ajax_bk_add_payment_method() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) {
        $payment_methods = [];
    }
    
    $type = sanitize_text_field($_POST['payment_type']);
    
    if (!in_array($type, ['bank', 'gcash', 'manual'])) {
        wp_send_json_error(['message' => 'Invalid payment type']);
    }
    
    $new_method = [
        'type' => $type,
        'name' => sanitize_text_field($_POST['payment_name']),
        'description' => sanitize_textarea_field($_POST['payment_description']),
        'account_name' => sanitize_text_field($_POST['account_name']),
        'account_number' => sanitize_text_field($_POST['account_number'])
    ];
    
    $payment_methods[] = $new_method;
    bntm_set_setting('bk_payment_methods', json_encode($payment_methods));
    
    wp_send_json_success(['message' => 'Payment method added successfully!']);
}

/**
 * Remove payment method
 */
add_action('wp_ajax_bk_remove_payment_method', 'bntm_ajax_bk_remove_payment_method');

function bntm_ajax_bk_remove_payment_method() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $index = intval($_POST['index']);
    $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods)) {
        wp_send_json_error(['message' => 'Invalid data']);
    }
    
    if (isset($payment_methods[$index])) {
        array_splice($payment_methods, $index, 1);
        bntm_set_setting('bk_payment_methods', json_encode($payment_methods));
        wp_send_json_success(['message' => 'Payment method removed']);
    } else {
        wp_send_json_error(['message' => 'Payment method not found']);
    }
}

/**
 * Save booking settings
 */
add_action('wp_ajax_bk_save_settings', 'bntm_ajax_bk_save_settings');

function bntm_ajax_bk_save_settings() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    bntm_set_setting('bk_slot_interval', intval($_POST['slot_interval']));
    bntm_set_setting('bk_advance_booking_days', intval($_POST['advance_booking_days']));
    bntm_set_setting('bk_currency', sanitize_text_field($_POST['currency']));
    bntm_set_setting('bk_tax_rate', floatval($_POST['tax_rate']));
    
    wp_send_json_success(['message' => 'Booking settings saved successfully!']);
}

/* ---------- HELPER FUNCTIONS ---------- */

function bk_get_dashboard_stats($business_id) {
    global $wpdb;
    $services_table = $wpdb->prefix . 'bk_services';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    $total_services = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $services_table WHERE business_id = %d",
        $business_id
    ));
    
    $total_bookings = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table WHERE business_id = %d",
        $business_id
    ));
    
    $monthly_revenue = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(total) FROM $bookings_table 
         WHERE business_id = %d AND payment_status IN ('paid', 'verified')
         AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE())",
        $business_id
    ));
    
    $pending_bookings = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $bookings_table 
         WHERE business_id = %d AND status = 'pending'",
        $business_id
    ));
    
    return [
        'total_services' => intval($total_services),
        'total_bookings' => intval($total_bookings),
        'monthly_revenue' => floatval($monthly_revenue),
        'pending_bookings' => intval($pending_bookings)
    ];
}

function bk_render_recent_bookings($business_id, $limit = 5) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';
    
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, s.name as service_name 
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id = s.id
         WHERE b.business_id = %d
         ORDER BY b.booking_date DESC, b.start_time DESC
         LIMIT %d",
        $business_id, $limit
    ));
    
    if (empty($bookings)) {
        return '<p>No recent bookings.</p>';
    }
    
    ob_start();
    ?>
    <table class="bntm-table">
        <thead>
            <tr>
                <th>Service</th>
                <th>Customer</th>
                <th>Date & Time</th>
                <th>Status</th>
                <th>Payment</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?php echo esc_html($booking->service_name); ?></td>
                    <td><?php echo esc_html($booking->customer_name); ?></td>
                    <td>
                        <?php echo date('M d, Y H:i', strtotime($booking->booking_date . ' ' . $booking->start_time)); ?>
                    </td>
                    <td>
                        <span class="bk-status-badge status-<?php echo esc_attr($booking->status); ?>">
                            <?php echo ucfirst($booking->status); ?>
                        </span>
                    </td>
                    <td>
                        <span class="bk-payment-badge payment-<?php echo esc_attr($booking->payment_status); ?>">
                            <?php echo ucfirst($booking->payment_status); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <style>
    .bk-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .bk-status-badge.status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .bk-status-badge.status-confirmed,
    .bk-status-badge.status-completed {
        background: #d1fae5;
        color: #065f46;
    }
    .bk-status-badge.status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    .bk-payment-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .bk-payment-badge.payment-unpaid {
        background: #fef3c7;
        color: #92400e;
    }
    .bk-payment-badge.payment-verified,
    .bk-payment-badge.payment-paid {
        background: #d1fae5;
        color: #065f46;
    }
    </style>
    <?php
    return ob_get_clean();
}

function bk_format_price($amount = '') {
    $currency = bntm_get_setting('bk_currency', 'USD');
    
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'PHP' => '₱'
    ];
    
    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';
    
    return $symbol . number_format($amount, 2);
}

/**
 * Send booking confirmation email
 */
function bk_send_booking_confirmation_email($email, $booking_data) {
    $subject = 'Booking Confirmation - ' . $booking_data['service'];
    
    $message = "Hello " . $booking_data['name'] . ",\n\n";
    $message .= "Thank you for booking with us!\n\n";
    $message .= "Booking Details:\n";
    $message .= "Service: " . $booking_data['service'] . "\n";
    $message .= "Date: " . date('F j, Y', strtotime($booking_data['date'])) . "\n";
    $message .= "Time: " . date('g:i A', strtotime($booking_data['start_time'])) . " - " . date('g:i A', strtotime($booking_data['end_time'])) . "\n";
    $message .= "Booking ID: " . $booking_data['booking_id'] . "\n";
    $message .= "Total Amount: " . bk_format_price($booking_data['total']) . "\n\n";
    
    if ($booking_data['payment_status'] === 'unpaid') {
        $message .= "Payment Status: Pending\n";
        $message .= "Please complete your payment to confirm your booking.\n\n";
    }
    
    $message .= "Thank you!\n";
    
    wp_mail($email, $subject, $message);
}

?>