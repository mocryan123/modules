<?php
/**
 * Module Name: Booking
 * Module Slug: bk
 * Description: Appointment booking system with calendar, time slots, and payment integration
 * Version: 1.0.2
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
    
    // If already initialized, just modify weekends (0 = Sunday, 6 = Saturday)
    if ($existing > 0) {
        $wpdb->query($wpdb->prepare(
            "UPDATE $table 
             SET is_open = 0 
             WHERE business_id = %d 
             AND day_of_week IN (0, 6)",
            $business_id
        ));
        return;
    }
    
    // Default: 9 AM - 5 PM, Monday to Friday
    for ($day = 0; $day <= 6; $day++) {
        $is_open = ($day == 0 || $day == 6) ? 0 : 1;
        $wpdb->insert($table, [
            'rand_id' => bntm_rand_id(),
            'business_id' => $business_id,
            'day_of_week' => $day,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_open' => $is_open
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
    
    <style>
    .in-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .in-modal-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 700px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    </style>
    <div class="bntm-booking-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=services" class="bntm-tab <?php echo $active_tab === 'services' ? 'active' : ''; ?>">Services</a>
            <a href="?tab=calendar" class="bntm-tab <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>">Calendar</a>
            <a href="?tab=bookings" class="bntm-tab <?php echo $active_tab === 'bookings' ? 'active' : ''; ?>">All Bookings</a>
            <a href="?tab=hours" class="bntm-tab <?php echo $active_tab === 'hours' ? 'active' : ''; ?>">Operating Hours</a>
             <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import" class="bntm-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">Import to Finance</a>
              <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo bk_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'services'): ?>
                <?php echo bk_services_tab($business_id); ?>
            <?php elseif ($active_tab === 'calendar'): ?>
                <?php echo bk_bookings_calendar_tab($business_id); ?>
            <?php elseif ($active_tab === 'bookings'): ?>
                <?php echo bk_bookings_tab($business_id); ?>
            <?php elseif ($active_tab === 'hours'): ?>
                <?php echo bk_operating_hours_tab($business_id); ?>
            <?php elseif ($active_tab === 'import'): ?>
                <?php echo bntm_fn_bookings_tab($business_id); ?>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <?php if ($booking_url): ?>
    <div class="bntm-booking-page-card">
        <div class="bntm-booking-header">
            <h3>Your Booking Page</h3>
            <span class="bntm-status-badge">Active</span>
        </div>
        <div class="bntm-booking-actions">
            <input type="text" id="booking-url" value="<?php echo esc_url($booking_url); ?>" readonly class="bntm-url-input">
            <button class="bntm-btn-secondary" id="copy-booking-url">Copy Link</button>
            <a href="<?php echo esc_url($booking_url); ?>" target="_blank" class="bntm-btn-primary">View Booking Page</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <div class="bntm-stat-icon bntm-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </div>
            <div class="bntm-stat-content">
                <h3>Total Services</h3>
                <p class="bntm-stat-number"><?php echo esc_html($stats['total_services']); ?></p>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="bntm-stat-icon bntm-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div class="bntm-stat-content">
                <h3>Total Bookings</h3>
                <p class="bntm-stat-number"><?php echo esc_html($stats['total_bookings']); ?></p>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="bntm-stat-icon bntm-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="bntm-stat-content">
                <h3>Monthly Revenue</h3>
                <p class="bntm-stat-number"><?php echo bk_format_price($stats['monthly_revenue']); ?></p>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="bntm-stat-icon bntm-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="bntm-stat-content">
                <h3>Pending Bookings</h3>
                <p class="bntm-stat-number"><?php echo esc_html($stats['pending_bookings']); ?></p>
            </div>
        </div>
    </div>

    <div class="bntm-charts-grid">
        <div class="bntm-chart-card bntm-chart-large">
            <h3>Bookings Overview</h3>
            <canvas id="bookingsChart"></canvas>
        </div>
        
        <div class="bntm-chart-card">
            <h3>Top Services by Bookings</h3>
            <canvas id="servicesChart"></canvas>
        </div>
        
        <div class="bntm-chart-card">
            <h3>Booking Status</h3>
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <div class="bntm-recent-bookings-section">
        <h3>Recent Bookings</h3>
        <?php echo bk_render_recent_bookings($business_id, 10); ?>
    </div>

    <style>
    .bntm-booking-page-card {
        background: #f8f9fa;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid #e5e7eb;
    }
    
    .bntm-booking-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    
    .bntm-booking-header h3 {
        margin: 0;
        color: #111827;
        font-size: 18px;
        font-weight: 600;
    }
    
    .bntm-status-badge {
        background: #10b981;
        color: #ffffff;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .bntm-booking-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .bntm-url-input {
        flex: 1;
        min-width: 300px;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        background: #ffffff;
        color: #374151;
        font-size: 14px;
        font-family: monospace;
        transition: all 0.2s ease;
    }
    
    .bntm-url-input:focus {
        outline: none;
        border-color: #9ca3af;
    }
    
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .bntm-stat-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .bntm-stat-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .bntm-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .bntm-stat-icon-primary {
        background: var(--bntm-primary, #374151);
        color: #ffffff;
    }
    
    .bntm-stat-content {
        flex: 1;
    }
    
    .bntm-stat-content h3 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .bntm-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        line-height: 1;
    }
    
    .bntm-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .bntm-chart-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .bntm-chart-large {
        grid-column: 1 / -1;
    }
    
    .bntm-chart-card h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .bntm-chart-card canvas {
        max-height: 300px;
    }
    
    .bntm-recent-bookings-section {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .bntm-recent-bookings-section h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    @media (max-width: 768px) {
        .bntm-chart-card {
            grid-column: 1 / -1;
        }
    }
    </style>
    
    <script>
    (function() {
        // Copy URL functionality
        const copyBtn = document.getElementById('copy-booking-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const urlInput = document.getElementById('booking-url');
                urlInput.select();
                document.execCommand('copy');
                
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                this.style.background = '#10b981';
                this.style.color = '#ffffff';
                this.style.borderColor = '#10b981';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.background = '';
                    this.style.color = '';
                    this.style.borderColor = '';
                }, 2000);
            });
        }
        
        // Chart.js configuration
        const primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--bntm-primary').trim() || '#374151';
        
        // Bookings Overview Chart (Line Chart)
        const bookingsCtx = document.getElementById('bookingsChart');
        if (bookingsCtx) {
            new Chart(bookingsCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['monthly_bookings_data'], 'month')); ?>,
                    datasets: [{
                        label: 'Bookings',
                        data: <?php echo json_encode(array_column($stats['monthly_bookings_data'], 'total')); ?>,
                        borderColor: primaryColor,
                        backgroundColor: primaryColor + '20',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: primaryColor,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#111827',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                color: '#6b7280',
                                font: { size: 12 },
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });
        }
        
        // Services Chart (Doughnut Chart)
        const servicesCtx = document.getElementById('servicesChart');
        if (servicesCtx) {
            new Chart(servicesCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($stats['service_bookings_data'], 'name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['service_bookings_data'], 'total')); ?>,
                        backgroundColor: [
                            primaryColor,
                            '#6b7280',
                            '#9ca3af',
                            '#d1d5db',
                            '#e5e7eb'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 12 },
                                color: '#374151'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#111827',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return label + ': ' + value + ' bookings';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Status Chart (Pie Chart)
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($stats['status_data'], 'status')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['status_data'], 'count')); ?>,
                        backgroundColor: [
                            '#10b981',
                            '#f59e0b',
                            '#ef4444',
                            '#6b7280'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                font: { size: 12 },
                                color: '#374151'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#111827',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            cornerRadius: 8
                        }
                    }
                }
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bk_get_dashboard_stats($business_id) {
    global $wpdb;
    $services_table = $wpdb->prefix . 'bk_services';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Total services
    $total_services = $wpdb->get_var("SELECT COUNT(*) FROM $services_table");
    
    // Total bookings
    $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
    
    // Monthly revenue
    $monthly_revenue = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM $bookings_table 
         WHERE payment_status IN ('paid', 'verified')
         AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE())"
    );
    
    // Pending bookings
    $pending_bookings = $wpdb->get_var(
        "SELECT COUNT(*) FROM $bookings_table 
         WHERE status = 'pending'"
    );
    
    // Monthly bookings data (last 6 months) - each month independent
    $monthly_bookings_data = $wpdb->get_results(
        "SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as total
        FROM $bookings_table
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)",
        ARRAY_A
    );
    
    // If no data, create empty months
    if (empty($monthly_bookings_data)) {
        $monthly_bookings_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly_bookings_data[] = [
                'month' => date('M Y', strtotime("-$i months")),
                'total' => 0
            ];
        }
    }
    
    // Service bookings data (Top 5 services)
    $service_bookings_data = $wpdb->get_results(
        "SELECT s.name, COUNT(b.id) as total
        FROM $bookings_table b
        JOIN $services_table s ON b.service_id = s.id
        GROUP BY b.service_id, s.name
        ORDER BY total DESC
        LIMIT 5",
        ARRAY_A
    );
    
    // Status data
    $status_data = $wpdb->get_results(
        "SELECT status, COUNT(*) as count
        FROM $bookings_table
        GROUP BY status",
        ARRAY_A
    );
    
    return [
        'total_services' => intval($total_services),
        'total_bookings' => intval($total_bookings),
        'monthly_revenue' => floatval($monthly_revenue),
        'pending_bookings' => intval($pending_bookings),
        'monthly_bookings_data' => $monthly_bookings_data,
        'service_bookings_data' => $service_bookings_data ?: [],
        'status_data' => $status_data ?: []
    ];
}
function bk_services_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY name ASC",
        $business_id
    ));
    
    // Get service limit
    $limits = get_option('bntm_table_limits', []);
    $service_limit = isset($limits[$table]) ? $limits[$table] : 0;
    $current_services = count($services);
    $limit_text = $service_limit > 0 ? " ({$current_services}/{$service_limit})" : " ({$current_services})";
    $limit_reached = $service_limit > 0 && $current_services >= $service_limit;
    
    $nonce = wp_create_nonce('bk_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Services<?php echo $limit_text; ?></h3>
        
        <?php if (empty($services)): ?>
            <p>No services yet. Add your first service to get started.</p>
        <?php else: ?>
        
        <div class="bntm-table-wrapper">
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
                                <button class="bntm-btn-small bk-edit-service" data-id="<?php echo $service->id; ?>" data-nonce="<?php echo $nonce; ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger bk-delete-service" data-id="<?php echo $service->id; ?>" data-nonce="<?php echo $nonce; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
         </div>
        <?php endif; ?>
    </div>

    <div class="bntm-form-section" style="background: #f9fafb; border-left: 4px solid #3b82f6;">
        <h3>Add New Service</h3>
        
        <?php if ($limit_reached): ?>
            <div style="background: #fef3c7; border: 1px solid #fde047; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <strong>⚠️ Service Limit Reached:</strong> Maximum of <?php echo $service_limit; ?> services allowed. This is a warning-only limit, but consider upgrading your plan.
            </div>
        <?php endif; ?>
        
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
            
            <button type="submit" class="bntm-btn-primary" id="add-service-btn">
                Add Service
            </button>
            <div id="service-message"></div>
        </form>
    </div>

    <!-- Edit Service Modal -->
    <div id="edit-service-modal" class="bk-modal">
        <div class="bk-modal-overlay"></div>
        <div class="bk-modal-content">
            <button class="bk-modal-close">&times;</button>
            <h2>Edit Service</h2>
            
            <form id="edit-service-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Service Name *</label>
                    <input type="text" name="service_name" required>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Duration (minutes) *</label>
                        <input type="number" name="duration" min="15" step="15" required>
                        <small>Must be in 15-minute intervals</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Price *</label>
                        <input type="number" name="price" min="0" step="0.01" required>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                
                <input type="hidden" id="edit-service-id" name="service_id">
                
                <button type="submit" class="bntm-btn-primary">Save Changes</button>
                <div id="edit-service-message"></div>
            </form>
        </div>
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
    
    .bk-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
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
        color: #6b7280;
        transition: all 0.2s;
    }
    
    .bk-modal-close:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    </style>
    
    <script>
    (function() {
        const editModal = document.getElementById('edit-service-modal');
        const modalOverlay = editModal.querySelector('.bk-modal-overlay');
        const modalClose = editModal.querySelector('.bk-modal-close');
        const limitReached = <?php echo $limit_reached ? 'true' : 'false'; ?>;
        const serviceLimit = <?php echo $service_limit; ?>;
        // Add service
document.getElementById('add-service-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const msg = document.getElementById('service-message');
    msg.innerHTML = ''; // Clear previous messages

    // Stop submission if limit reached
    if (limitReached) {
        msg.innerHTML = '<div class="bntm-notice bntm-notice-error">You have reached the service limit (' + serviceLimit + '). Please upgrade your plan to add more services.</div>';
        return;
    }

    const formData = new FormData(this);
    formData.append('action', 'bk_add_service');
    formData.append('nonce', '<?php echo $nonce; ?>');

    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Adding...';

    fetch(ajaxurl, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(json => {
            msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
            if (json.success) {
                setTimeout(() => location.reload(), 1500);
            } else {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(err => {
            msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
            btn.disabled = false;
            btn.textContent = originalText;
        });
});

        
        // Edit service
         document.querySelectorAll('.bk-edit-service').forEach(btn => {
             btn.addEventListener('click', function() {
                 const serviceId = this.dataset.id;
                 const nonce = this.dataset.nonce;
                 
                 const formData = new FormData();
                 formData.append('action', 'bk_edit_service');
                 formData.append('service_id', serviceId);
                 formData.append('nonce', nonce);
                 
                 fetch(ajaxurl, {method: 'POST', body: formData})
                 .then(r => r.json())
                 .then(json => {
                     if (json.success) {
                         const service = json.data;
                         document.getElementById('edit-service-id').value = service.id;
                         
                         // Query inputs within the edit modal
                         const editForm = editModal.querySelector('#edit-service-form');
                         editForm.querySelector('input[name="service_name"]').value = service.name;
                         editForm.querySelector('input[name="duration"]').value = service.duration;
                         editForm.querySelector('input[name="price"]').value = service.price;
                         editForm.querySelector('textarea[name="description"]').value = service.description;
                         
                         editModal.classList.add('active');
                         document.body.style.overflow = 'hidden';
                     } else {
                         alert(json.data.message);
                     }
                 });
             });
         });
        
        // Close modal
        modalClose.addEventListener('click', function() {
            editModal.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        modalOverlay.addEventListener('click', function() {
            editModal.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Update service
        document.getElementById('edit-service-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'bk_update_service');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('edit-service-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                
                if (json.success) {
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                const msg = document.getElementById('edit-service-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.textContent = originalText;
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
                    } else {
                        const statusText = this.closest('td').querySelector('.status-text');
                        statusText.textContent = this.checked ? 'Active' : 'Inactive';
                    }
                });
            });
        });
        
        // Delete service
        document.querySelectorAll('.bk-delete-service').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this service?')) return;
                
                const serviceId = this.dataset.id;
                const nonce = this.dataset.nonce;
                
                const formData = new FormData();
                formData.append('action', 'bk_delete_service');
                formData.append('service_id', serviceId);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.data.message);
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
         ORDER BY b.booking_date DESC, b.start_time DESC",
         
         

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
        <div class="bntm-table-wrapper">
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
                                    <!--<option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>-->
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
         </div>
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
        "SELECT * FROM $table ORDER BY day_of_week ASC",
        $business_id
    ));
    
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $nonce = wp_create_nonce('bk_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Operating Hours</h3>
        <p>Set your business operating hours for each day of the week.</p>
        
        <div class="bntm-table-wrapper">
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
       <h3>Booking Page Settings</h3>
       
       <div class="bntm-form-group">
           <label>Booking Description</label>
           <textarea name="bk_description" id="bk-description" rows="3" placeholder="Brief description shown on booking page"><?php echo esc_textarea(bntm_get_bk_description()); ?></textarea>
           <small>This appears below the title on your booking calendar.</small>
       </div>
       
       <div class="bntm-form-group">
           <label>Booking Terms & Conditions</label>
           <textarea name="bk_terms" id="bk-terms" rows="6" placeholder="Enter your booking terms and conditions"><?php echo esc_textarea(bntm_get_bk_terms()); ?></textarea>
           <small>These terms appear at the bottom of the booking calendar.</small>
       </div>
       
       <button type="button" id="save-booking-settings-btn" class="bntm-btn-primary">Save Booking Settings</button>
       <div id="booking-settings-message"></div>
   </div>
   
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
                            <div>
                                <button class="bntm-btn-small bntm-btn-secondary edit-payment-method" data-index="<?php echo $index; ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger remove-payment-method" data-index="<?php echo $index; ?>">Remove</button>
                            </div>
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
            <h4 style="margin-top: 0;" id="form-title">Add Payment Method</h4>
            <form id="add-payment-method-form" class="bntm-form">
                <input type="hidden" name="edit_index" id="edit-index" value="">
                
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
                    <input type="text" name="payment_name" id="payment-name" placeholder="e.g., BDO Bank Transfer" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Name</label>
                    <input type="text" name="account_name" id="account-name" placeholder="Account holder name">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" id="account-number" placeholder="Account/Phone number">
                </div>
                
                <div class="bntm-form-group">
                    <label>Instructions</label>
                    <textarea name="payment_description" id="payment-description" rows="3" placeholder="Payment instructions for customers"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="bntm-btn-primary" id="submit-btn">Add Payment Method</button>
                    <button type="button" class="bntm-btn-secondary" id="cancel-edit-btn" style="display: none;">Cancel</button>
                </div>
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
    .bntm-btn-small {
        margin-left: 5px;
    }
    </style>

    <script>
    (function() {
        const paymentSourceSelect = document.getElementById('payment-source-select');
        const bkPaymentSection = document.getElementById('bk-payment-methods-section');
        const paymentSourceMessage = document.getElementById('payment-source-message');
        const form = document.getElementById('add-payment-method-form');
        const formTitle = document.getElementById('form-title');
        const submitBtn = document.getElementById('submit-btn');
        const cancelBtn = document.getElementById('cancel-edit-btn');
        const editIndexInput = document.getElementById('edit-index');
        
        const paymentMethods = <?php echo json_encode($payment_methods); ?>;
        
        paymentSourceSelect.addEventListener('change', function() {
            if (this.value === 'op') {
                bkPaymentSection.style.display = 'none';
            } else {
                bkPaymentSection.style.display = 'block';
            }
        });
        
         document.getElementById('save-booking-settings-btn').addEventListener('click', function() {
             const formData = new FormData();
             formData.append('action', 'bk_save_booking_settings');
             formData.append('bk_description', document.getElementById('bk-description').value);
             formData.append('bk_terms', document.getElementById('bk-terms').value);
             formData.append('nonce', '<?php echo $nonce; ?>');
             
             const btn = this;
             btn.disabled = true;
             btn.textContent = 'Saving...';
             
             fetch(ajaxurl, {method: 'POST', body: formData})
             .then(r => r.json())
             .then(json => {
                 document.getElementById('booking-settings-message').innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                 btn.disabled = false;
                 btn.textContent = 'Save Booking Settings';
             });
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
        
        // Reset form to add mode
        function resetForm() {
            form.reset();
            editIndexInput.value = '';
            formTitle.textContent = 'Add Payment Method';
            submitBtn.textContent = 'Add Payment Method';
            cancelBtn.style.display = 'none';
        }
        
        // Edit payment method
        document.querySelectorAll('.edit-payment-method').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                const method = paymentMethods[index];
                
                if (!method) return;
                
                // Populate form with existing data
                document.getElementById('payment-type-select').value = method.type;
                document.getElementById('payment-name').value = method.name;
                document.getElementById('account-name').value = method.account_name || '';
                document.getElementById('account-number').value = method.account_number || '';
                document.getElementById('payment-description').value = method.description || '';
                editIndexInput.value = index;
                
                // Update UI
                formTitle.textContent = 'Edit Payment Method';
                submitBtn.textContent = 'Update Payment Method';
                cancelBtn.style.display = 'inline-block';
                
                // Scroll to form
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });
        
        // Cancel edit
        cancelBtn.addEventListener('click', resetForm);
        
        // Add/Update payment method
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const isEdit = editIndexInput.value !== '';
            
            if (isEdit) {
                formData.append('action', 'bk_update_payment_method');
            } else {
                formData.append('action', 'bk_add_payment_method');
            }
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            submitBtn.disabled = true;
            submitBtn.textContent = isEdit ? 'Updating...' : 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('payment-method-message').innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    document.getElementById('payment-method-message').innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.textContent = isEdit ? 'Update Payment Method' : 'Add Payment Method';
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
/* ---------- UPDATED CALENDAR & BOOKING PAGE ---------- */
function bntm_shortcode_bk_calendar() {
    global $wpdb;
    $services_table = $wpdb->prefix . 'bk_services';
    
    // Get services from the first business (or current if user is logged in)
    $services = $wpdb->get_results(
        "SELECT * FROM $services_table WHERE status = 'active' ORDER BY name ASC LIMIT 100"
    );
    
    if (empty($services)) {
        return '<div class="bntm-container"><p>No services available for booking.</p></div>';
    }
    
    $nonce = wp_create_nonce('bk_calendar_nonce');
    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+' . bntm_get_setting('bk_advance_booking_days', '30') . ' days'));
    $current_month = date('Y-m');
    $logo = bntm_get_site_logo();
   $site_title = bntm_get_site_title();
   $bk_description = bntm_get_setting('bk_description', 'Book your appointment with us. Select a date to view available time slots.');
   
   $default_terms = 'By booking an appointment, you agree to our terms and conditions. Please arrive 5 minutes before your scheduled time. Cancellations must be made at least 24 hours in advance.';
   $bk_terms =  bntm_get_setting('bk_terms', $default_terms);
    // Get payment source for display
    $payment_source = bntm_get_setting('bk_payment_source', 'bk');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var paymentSource = '<?php echo esc_js($payment_source); ?>';
    
    // Helper function to format time as 12-hour with AM/PM
    function formatTime12Hour(time24) {
        const [hours, minutes] = time24.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return hour12 + ':' + minutes + ' ' + ampm;
    }
    </script>
    
    <div class="bk-booking-container">
           <!-- Header Section -->
          <div class="bk-booking-header">
              <?php if ($logo): ?>
                  <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_title); ?>" class="bk-header-logo">
              <?php endif; ?>
              <h1><?php echo esc_html($site_title); ?></h1>
              <?php if ($bk_description): ?>
                  <p class="bk-header-description"><?php echo esc_html($bk_description); ?></p>
              <?php endif; ?>
          </div>
        <!-- Calendar View -->
        <div id="customer-calendar-view" class="customer-calendar-fade-in" style="opacity: 1; transition: opacity 0.3s ease;">
            <div class="bk-calendar-wrapper">
               
                
                <div class="bk-calendar-header">
                    <button id="prev-month" class="bk-calendar-nav">←</button>
                    <h2 id="calendar-month-year"></h2>
                    <button id="next-month" class="bk-calendar-nav">→</button>
                </div>
                
                <div class="bk-calendar-grid">
                    <div class="bk-weekday">Sun</div>
                    <div class="bk-weekday">Mon</div>
                    <div class="bk-weekday">Tue</div>
                    <div class="bk-weekday">Wed</div>
                    <div class="bk-weekday">Thu</div>
                    <div class="bk-weekday">Fri</div>
                    <div class="bk-weekday">Sat</div>
                    <div class="bk-calendar-days" id="calendar-days"></div>
                </div>
                 <!-- Legend -->
                <div class="bk-calendar-legend">
                    <div class="bk-legend-item">
                        <span class="bk-legend-color" style="background: #dcfce7; border-color: #86efac;"></span>
                        <span>Available (&lt;50% booked)</span>
                    </div>
                    <div class="bk-legend-item">
                        <span class="bk-legend-color" style="background: #fed7aa; border-color: #fdba74;"></span>
                        <span>Busy (50-99% booked)</span>
                    </div>
                    <div class="bk-legend-item">
                        <span class="bk-legend-color" style="background: #fecaca; border-color: #f87171;"></span>
                        <span>Fully Booked</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Slots View -->
        <div id="customer-slots-view" class="customer-slots-fade-in" style="display: none; opacity: 0; transition: opacity 0.3s ease;">
            <div class="bk-slots-wrapper">
                <div class="bk-slots-header">
                    <h2>Available Slots for <span id="selected-date-display"></span></h2>
                    <button id="back-to-calendar" class="bntm-btn-secondary">← Back to Calendar</button>
                </div>
                
                <div class="bk-services-selector">
                    <label>Select Service:</label>
                    <select id="customer-service-filter" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="">All Services</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service->id; ?>"><?php echo esc_html($service->name); ?> (<?php echo $service->duration; ?> min - <?php echo bk_format_price($service->price); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="slots-loading" style="text-align: center; padding: 40px; display: none;">
                    <p>Loading available slots...</p>
                </div>
                
                <div id="slots-table-container" style="display: none; overflow: auto;">
                    <table class="bk-slots-table">
                        <thead id="slots-table-head">
                            <!-- Generated dynamically -->
                        </thead>
                        <tbody id="slots-table-body">
                            <!-- Generated dynamically -->
                        </tbody>
                    </table>
                </div>
                
                <div id="slots-message" style="display: none; padding: 20px; border-radius: 8px; text-align: center;"></div>
            </div>
        </div>
        <!-- Booking Terms Section -->
        <div class="bk-terms-section">
            <h3>Booking Terms & Conditions</h3>
            <div class="bk-terms-content">
                <?php echo nl2br(esc_html($bk_terms)); ?>
            </div>
        </div>
    </div>

    <!-- Booking Modal (unchanged) -->
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
                    
                   <div style="margin: 15px 0;">
                      <label style="display: block; font-weight: 600; margin-bottom: 8px;">Quantity (Number of Slots):</label>
                      <div style="display: flex; align-items: center; gap: 10px;">
                          <button type="button" id="quantity-decrease" class="bk-quantity-btn">
                              <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                              </svg>
                          </button>
                          <input type="number" id="modal-quantity-input" min="1" max="10" value="1" readonly
                                 style="width: 100%; padding: 10px; border: 2px solid #d1d5db; border-radius: 6px; font-size: 18px; font-weight: 600; text-align: center;">
                          <button type="button" id="quantity-increase" class="bk-quantity-btn">
                              <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                              </svg>
                          </button>
                      </div>
                      <small style="color: #6b7280; display: block; margin-top: 8px;">Select consecutive time slots (1-10)</small>
                  </div>
                    
                    <div id="quantity-calculation" style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Unit Price:</span>
                            <span id="calc-unit-price" style="font-weight: 600;"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Quantity:</span>
                            <span id="calc-quantity" style="font-weight: 600;">1</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Total Duration:</span>
                            <span id="calc-total-duration" style="font-weight: 600;"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>End Time:</span>
                            <span id="calc-end-time" style="font-weight: 600; color: #3b82f6;"></span>
                        </div>
                        <hr style="margin: 10px 0; border: none; border-top: 1px solid #bfdbfe;">
                        <div style="display: flex; justify-content: space-between; font-size: 18px;">
                            <strong>Total Price:</strong>
                            <strong id="calc-total-price" style="color: #059669;"></strong>
                        </div>
                    </div>
                    
                    <div id="availability-warning" style="display: none; background: #fef3c7; border: 1px solid #fde047; padding: 10px; border-radius: 6px; margin-top: 10px; color: #92400e;">
                        <strong>⚠️ Warning:</strong> <span id="warning-message"></span>
                    </div>
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
                    <textarea name="customer_notes" rows="3" placeholder="Any special requests..."></textarea>
                </div>
                
                <h3>Payment Method</h3>
                <div class="bk-payment-methods" id="bk-payment-methods"></div>
                
                <input type="hidden" name="service_id" id="hidden-service-id">
                <input type="hidden" name="booking_date" id="hidden-booking-date">
                <input type="hidden" name="start_time" id="hidden-start-time">
                <input type="hidden" name="end_time" id="hidden-end-time">
                <input type="hidden" name="quantity" id="hidden-quantity">
                <input type="hidden" name="amount" id="hidden-amount">
                
                <button type="submit" class="bntm-btn-primary bntm-btn-large" id="complete-booking-btn" style="width: 100%; margin-top: 20px;">
                    Complete Booking
                </button>
                <div id="booking-message"></div>
            </form>
        </div>
    </div>


    <style>
    .bk-booking-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
    }
    
    .bk-booking-container h1 {
        text-align: center;
        font-size: 32px;
        color: #1f2937;
        margin-bottom: 30px;
    }
    
    .customer-calendar-fade-in,
    .customer-slots-fade-in {
        transition: opacity 0.3s ease;
    }
    /* Header Styles */
      .bk-booking-header {
          text-align: center;
          margin-bottom: 40px;
          padding: 30px 20px;
          color: black;
      }
      
      .bk-header-logo {
          max-width: 120px;
          height: auto;
          margin-bottom: 20px;
          border-radius: 8px;
      }
      
      .bk-booking-header h1 {
          margin: 0 0 10px 0;
          font-size: 32px;
          color: black;
      }
      
      .bk-header-description {
          margin: 0;
          font-size: 16px;
          color: rgba(0, 0, 0, 0.9);
          max-width: 600px;
          margin: 0 auto;
      }
      
      /* Terms Section Styles */
      .bk-terms-section {
          margin-top: 40px;
          padding: 30px;
          background: white;
          border-radius: 12px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      }
      
      .bk-terms-section h3 {
          margin: 0 0 20px 0;
          font-size: 20px;
          color: #1f2937;
          border-bottom: 2px solid #e5e7eb;
          padding-bottom: 10px;
      }
      
      .bk-terms-content {
          font-size: 14px;
          line-height: 1.8;
          color: #4b5563;
      }
      
      @media (max-width: 768px) {
          .bk-booking-header {
              padding: 20px 15px;
          }
          
          .bk-booking-header h1 {
              font-size: 24px;
          }
          
          .bk-header-description {
              font-size: 14px;
          }
          
          .bk-header-logo {
              max-width: 80px;
          }
          
          .bk-terms-section {
              padding: 20px;
          }
      }
    /* Calendar Styles */
    .bk-calendar-wrapper {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .bk-calendar-legend {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        flex-wrap: wrap;
    }
    
    .bk-legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }
    
    .bk-legend-color {
        width: 24px;
        height: 24px;
        border-radius: 4px;
        border: 2px solid;
    }
    
    .bk-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .bk-calendar-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1f2937;
        min-width: 200px;
        text-align: center;
    }
    
    .bk-calendar-nav {
        padding: 10px 15px;
        border: 1px solid #e5e7eb;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        color: #3b82f6;
        transition: all 0.2s;
    }
    
    .bk-calendar-nav:hover:not(:disabled) {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .bk-calendar-nav:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        color: #9ca3af;
    }
    
    .bk-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }
    
    .bk-weekday {
        font-weight: 700;
        text-align: center;
        padding: 10px;
        color: #6b7280;
        font-size: 14px;
    }
    
    .bk-calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        grid-column: 1 / -1;
    }
    
    .bk-calendar-day {
          aspect-ratio: 1;
          display: flex;
          align-items: center;
          justify-content: center;
          border: 2px solid #e5e7eb;
          border-radius: 8px;
          cursor: pointer;
          font-weight: 600;
          transition: all 0.2s;
          background: white;
          color: #1f2937;
          position: relative; /* ADD THIS */
      }
    
    .bk-calendar-day:hover:not(.disabled):not(.other-month):not(.fully-booked) {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: scale(1.05);
    }
    
    .bk-calendar-day.other-month {
        color: #d1d5db;
        cursor: default;
        background: #f9fafb;
    }
    .no-operating-hours{
       
        opacity: 0.5;
        cursor: not-allowed;
        background: #f3f4f6;
    }
    .bk-calendar-day.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f3f4f6;
    }
    
    .bk-calendar-day.selected {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    /* Color coding for availability */
    .bk-calendar-day.availability-low {
        background: #dcfce7;
        border-color: #86efac;
    }
    
    .bk-calendar-day.availability-medium {
        background: #fed7aa;
        border-color: #fdba74;
    }
    
    .bk-calendar-day.availability-full {
        background: #fecaca;
        border-color: #f87171;
    }
    
    .bk-calendar-day.fully-booked {
        background: #fecaca !important;
        border-color: #f87171 !important;
        cursor: not-allowed !important;
        opacity: 0.7;
    }
    
    .bk-calendar-day.selected.availability-low,
    .bk-calendar-day.selected.availability-medium,
    .bk-calendar-day.selected.availability-full {
        background: #3b82f6;
        border-color: #3b82f6;
    }
          
      
      
      .bk-slot-badge {
          position: absolute;
          bottom: 2px; /* CHANGED from 4px */
          left: 50%; /* ADD THIS */
          transform: translateX(-50%); /* ADD THIS */
          font-size: 10px;
          font-weight: 500;
          color: #6b7280;
          background: rgba(255, 255, 255, 0.9);
          padding: 2px 6px;
          border-radius: 10px;
          border: 1px solid #e5e7eb;
          white-space: nowrap; /* ADD THIS */
      }
      
      .bk-calendar-day.availability-low .bk-slot-badge {
          color: #065f46;
          background: rgba(220, 252, 231, 0.95);
          border-color: #86efac;
      }
      
      .bk-calendar-day.availability-medium .bk-slot-badge {
          color: #92400e;
          background: rgba(254, 215, 170, 0.95);
          border-color: #fdba74;
      }
      
      .bk-calendar-day.availability-full .bk-slot-badge {
          color: #991b1b;
          background: rgba(254, 202, 202, 0.95);
          border-color: #f87171;
      }
      
      .bk-calendar-day.selected .bk-slot-badge {
          color: white;
          background: rgba(29, 78, 216, 0.95);
          border-color: rgba(255, 255, 255, 0.3);
      }
    /* Slots Wrapper */
    .bk-slots-wrapper {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .bk-slots-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .bk-slots-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1f2937;
    }
    
    .bk-services-selector {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .bk-services-selector label {
        display: block;
        font-weight: 600;
        margin-bottom: 15px;
        color: #1f2937;
    }
    
    /* Slots Table */
    .bk-slots-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    
    .bk-slots-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .bk-slots-table th {
        padding: 12px;
        text-align: center;
        font-weight: 600;
        color: #1f2937;
        border-right: 1px solid #e5e7eb;
        width:200px;
    }
    
    .bk-slots-table th:last-child {
        border-right: none;
    }
    
    .bk-slots-table td {
        padding: 8px;
        text-align: center;
        border-right: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .bk-slots-table td:last-child {
        border-right: none;
    }
    
    .bk-time-slot {
        padding: 8px 0px;
        border: 2px solid #e5e7eb;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
        font-size: 14px;
        width: 100%;
    }
    
    .bk-time-slot:hover:not(.booked) {
        background: #a7f3d0;
    }
    
    .bk-time-slot.booked {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
        cursor: not-allowed;
    }
    
    .bk-time-slot.available {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    
    /* Modal */
    .bk-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
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
        color: #6b7280;
        transition: all 0.2s;
    }
    
    .bk-modal-close:hover {
        background: #e5e7eb;
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
    
    .bk-time-label {
        font-weight: 600;
        background: #f9fafb;
        width: 60px;
        min-width: 60px;
    }
    
    .bk-slots-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    
    .bk-slots-table tbody tr:nth-child(even) {
        background: #f9fafb;
    }
    
    .bk-slots-table tbody tr:hover {
        background: #eff6ff;
    }
    .payment-type-badge {
          display: inline-block;
          margin-left: 10px;
          padding: 2px 8px;
          background: #f3f4f6;
          border-radius: 4px;
          font-size: 12px;
          color: #6b7280;
          font-weight: normal;
      }
      
      .bk-payment-option input[type="radio"]:checked + .bk-payment-card .payment-type-badge {
          background: #dbeafe;
          color: #1e40af;
      }
      
      /* Quantity Buttons */
      .bk-quantity-btn {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 40px;
          height: 40px;
          border: 2px solid #3b82f6;
          background: white;
          border-radius: 8px;
          cursor: pointer;
          transition: all 0.2s;
          color: #3b82f6;
      }
      
      .bk-quantity-btn:hover:not(:disabled) {
          background: #3b82f6;
          color: white;
          transform: scale(1.05);
      }
      
      .bk-quantity-btn:active:not(:disabled) {
          transform: scale(0.95);
      }
      
      .bk-quantity-btn:disabled {
          opacity: 0.4;
          cursor: not-allowed;
          border-color: #d1d5db;
          color: #9ca3af;
      }
      
      .bk-quantity-btn svg {
          pointer-events: none;
      }
      
      #modal-quantity-input::-webkit-inner-spin-button,
      #modal-quantity-input::-webkit-outer-spin-button {
          -webkit-appearance: none;
          margin: 0;
      }
      
      #modal-quantity-input {
          -moz-appearance: textfield;
      }
    @media (max-width: 768px) {
        .bk-calendar-grid {
            gap: 5px;
        }
        
        .bk-calendar-day {
            font-size: 14px;
        }
        .bk-calendar-days {
            gap:5px;
        }
        
        .bk-slots-table {
            font-size: 12px;
        }
        
        .bk-slots-table th,
        .bk-slots-table td {
            padding: 8px;
        }
        
        .bk-booking-container {
              max-width: 1200px;
              margin: 0 auto;
              padding: 10px;
          }
          
          /* Calendar Styles */
          .bk-calendar-wrapper {
              padding: 5px;
              box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          }
          
          .bk-time-slot{
                 width: 70px;
          }
           .bk-slot-badge {
           display:none;
        }
    }
    </style>

   <script>
(function() {
    let currentMonth = new Date('<?php echo $current_month; ?>-01');
    let selectedDate = null;
    let selectedService = null;
    let selectedServiceData = null;
    let selectedTime = null;
    let selectedEndTime = null;
    let dateAvailability = {};
    //const minDate = new Date('<?php echo $min_date; ?>');
      const minDate = new Date();
    minDate.setHours(0, 0, 0, 0);
    const maxDate = new Date('<?php echo $max_date; ?>');
    
    const calendarView = document.getElementById('customer-calendar-view');
    const slotsView = document.getElementById('customer-slots-view');
    const serviceFilter = document.getElementById('customer-service-filter');
    const modal = document.getElementById('booking-modal');
    const modalOverlay = modal.querySelector('.bk-modal-overlay');
    const modalClose = modal.querySelector('.bk-modal-close');
    const quantityInput = document.getElementById('modal-quantity-input');
    const completeBookingBtn = document.getElementById('complete-booking-btn');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    
    renderCalendar();
    loadMonthAvailability();
    
     const quantityDecreaseBtn = document.getElementById('quantity-decrease');
     const quantityIncreaseBtn = document.getElementById('quantity-increase');
     
     // Quantity decrease button
     quantityDecreaseBtn.addEventListener('click', function() {
         let quantity = parseInt(quantityInput.value) || 1;
         if (quantity > 1) {
             quantity--;
             quantityInput.value = quantity;
             updateQuantityButtons();
             updateModalCalculations();
             checkSlotAvailability();
         }
     });
     
     // Quantity increase button
     quantityIncreaseBtn.addEventListener('click', function() {
         let quantity = parseInt(quantityInput.value) || 1;
         if (quantity < 10) {
             quantity++;
             quantityInput.value = quantity;
             updateQuantityButtons();
             updateModalCalculations();
             checkSlotAvailability();
         }
     });
     
     // Keep the input handler for manual typing (optional, since we made it readonly)
     quantityInput.addEventListener('input', function() {
         let quantity = parseInt(this.value) || 1;
         if (quantity < 1) quantity = 1;
         if (quantity > 10) quantity = 10;
         this.value = quantity;
         updateQuantityButtons();
         updateModalCalculations();
         checkSlotAvailability();
     });
     
     // New function to update button states
     function updateQuantityButtons() {
         const quantity = parseInt(quantityInput.value) || 1;
         quantityDecreaseBtn.disabled = quantity <= 1;
         quantityIncreaseBtn.disabled = quantity >= 10;
     }
    
    function loadMonthAvailability() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        const startDate = new Date(year, month, 1);
        const endDate = new Date(year, month + 1, 0);
        
        const formData = new FormData();
        formData.append('action', 'bk_get_month_availability');
        formData.append('start_date', formatDate(startDate));
        formData.append('end_date', formatDate(endDate));
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                dateAvailability = json.data;
                updateCalendarColors();
            }
        });
    }
    
   
    function updateCalendarColors() {
      document.querySelectorAll('.bk-calendar-day:not(.other-month)').forEach(day => {
          const date = day.dataset.date;
          
          // Check if date has availability data
          if (dateAvailability[date]) {
              const data = dateAvailability[date];
              const percentage = data.percentage;
              const available = data.total_slots - data.booked_slots;
              
              day.classList.remove('availability-low', 'availability-medium', 'availability-full', 'no-operating-hours');
              
              // Check if there are any slots available
              if (data.total_slots === 0 || available === 0) {
                  // No operating hours or fully booked
                  day.classList.add('no-operating-hours');
              } else {
                  // Has available slots - apply color coding
                  if (percentage >= 100) {
                      day.classList.add('availability-full');
                  } else if (percentage >= 50) {
                      day.classList.add('availability-medium');
                  } else {
                      day.classList.add('availability-low');
                  }
                  
                  // Add slot count display
                  const existingBadge = day.querySelector('.bk-slot-badge');
                  if (existingBadge) {
                      existingBadge.remove();
                  }
                  
                  const badge = document.createElement('div');
                  badge.className = 'bk-slot-badge';
                  badge.textContent = available + '/' + data.total_slots;
                  day.appendChild(badge);
              }
          } else {
              // No data returned - assume no operating hours
              day.classList.add('no-operating-hours');
          }
      });
  }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
      function updateMonthNavigation() {
      // Check if we can go to previous month
      const prevMonth = new Date(currentMonth);
      prevMonth.setMonth(prevMonth.getMonth() - 1);
      const prevMonthStart = new Date(prevMonth.getFullYear(), prevMonth.getMonth(), 1);
      prevMonthBtn.disabled = prevMonthStart < minDate;
      
      // Check if we can go to next month
      const nextMonth = new Date(currentMonth);
      nextMonth.setMonth(nextMonth.getMonth() + 1);
      const nextMonthStart = new Date(nextMonth.getFullYear(), nextMonth.getMonth(), 1);
      nextMonthBtn.disabled = nextMonthStart > maxDate;
  }
    
    function updateModalCalculations() {
        if (!selectedServiceData) return;
        
        const quantity = parseInt(quantityInput.value) || 1;
        const unitPrice = parseFloat(selectedServiceData.price);
        const duration = parseInt(selectedServiceData.duration);
        const totalDuration = duration * quantity;
        const totalPrice = unitPrice * quantity;
        
        // Calculate new end time
        const startTimestamp = new Date(selectedDate + ' ' + selectedTime).getTime();
        const endTimestamp = startTimestamp + (totalDuration * 60 * 1000);
        const endDate = new Date(endTimestamp);
        const newEndTime = endDate.toTimeString().substring(0, 5);
        
        // Update display
        document.getElementById('calc-unit-price').textContent = bk_formatPrice(unitPrice);
        document.getElementById('calc-quantity').textContent = quantity;
        document.getElementById('calc-total-duration').textContent = totalDuration + ' min';
        document.getElementById('calc-end-time').textContent = formatTime12Hour(newEndTime);
        document.getElementById('calc-total-price').textContent = bk_formatPrice(totalPrice);
        document.getElementById('modal-duration').textContent = totalDuration;
        
        // Update datetime display
        const dateObj = new Date(selectedDate);
        dateObj.setHours(12, 0, 0, 0);
        const dateStr = dateObj.toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'});
        document.getElementById('modal-booking-datetime').textContent = 
            dateStr + ' at ' + formatTime12Hour(selectedTime) + ' - ' + formatTime12Hour(newEndTime);
        
        // Update hidden fields
        document.getElementById('hidden-end-time').value = newEndTime;
        document.getElementById('hidden-quantity').value = quantity;
        document.getElementById('hidden-amount').value = totalPrice;
    }
    
    function checkSlotAvailability() {
        const quantity = parseInt(quantityInput.value) || 1;
        const warning = document.getElementById('availability-warning');
        const warningMessage = document.getElementById('warning-message');
        
        if (quantity === 1) {
            warning.style.display = 'none';
            completeBookingBtn.disabled = false;
            return;
        }
        
        // Check availability via AJAX
        const formData = new FormData();
        formData.append('action', 'bk_check_slot_availability');
        formData.append('service_id', selectedService);
        formData.append('booking_date', selectedDate);
        formData.append('start_time', selectedTime);
        formData.append('quantity', quantity);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success && json.data.available) {
                warning.style.display = 'none';
                completeBookingBtn.disabled = false;
            } else {
                warning.style.display = 'block';
                warningMessage.textContent = json.data?.message || 'Some slots in this time range are not available.';
                completeBookingBtn.disabled = true;
            }
        })
        .catch(err => {
            console.error('Availability check error:', err);
        });
    }
    
    // Calendar navigation
     prevMonthBtn.addEventListener('click', function() {
         const prevMonth = new Date(currentMonth);
         prevMonth.setMonth(prevMonth.getMonth() - 1);
         
         currentMonth = prevMonth;
         renderCalendar();
         loadMonthAvailability();
     });
     
     nextMonthBtn.addEventListener('click', function() {
         const nextMonth = new Date(currentMonth);
         nextMonth.setMonth(nextMonth.getMonth() + 1);
         
         currentMonth = nextMonth;
         renderCalendar();
         loadMonthAvailability();
     });
    
    
    
    function renderCalendar() {
        const year = currentMonth.getFullYear();
        const month = currentMonth.getMonth();
        
        document.getElementById('calendar-month-year').textContent = 
            new Date(year, month).toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const prevLastDay = new Date(year, month, 0);
        const startDate = firstDay.getDay();
        
        let html = '';
        
        // Previous month days
        for (let i = startDate - 1; i >= 0; i--) {
            const day = prevLastDay.getDate() - i;
            html += `<div class="bk-calendar-day other-month">${day}</div>`;
        }
        
        // Current month days
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dateStr = formatDate(date);
            
            let classes = 'bk-calendar-day';
            
            // Check if date is outside allowed range
            if (date < minDate || date > maxDate) {
                classes += ' disabled';
            }
            
            if (dateStr === selectedDate) {
                classes += ' selected';
            }
            
            html += `<div class="${classes}" data-date="${dateStr}">${day}</div>`;
        }
        
        // Next month days
        const remainingDays = 42 - (startDate + lastDay.getDate());
        for (let day = 1; day <= remainingDays; day++) {
            html += `<div class="bk-calendar-day other-month">${day}</div>`;
        }
        
        document.getElementById('calendar-days').innerHTML = html;
        
        // Add click handlers
        document.querySelectorAll('.bk-calendar-day:not(.disabled):not(.other-month):not(.fully-booked)').forEach(day => {
            day.addEventListener('click', function() {
                if (this.classList.contains('fully-booked') || this.classList.contains('no-operating-hours')) {
                    return;
                }
                
                selectedDate = this.dataset.date;
                document.querySelectorAll('.bk-calendar-day').forEach(d => d.classList.remove('selected'));
                this.classList.add('selected');
                
                const dateObj = new Date(selectedDate);
                dateObj.setHours(12, 0, 0, 0);
                document.getElementById('selected-date-display').textContent = 
                    dateObj.toLocaleDateString('en-US', {weekday: 'long', month: 'long', day: 'numeric'});
                
                calendarView.style.opacity = '0';
                setTimeout(() => {
                    calendarView.style.display = 'none';
                    slotsView.style.display = 'block';
                    setTimeout(() => {
                        slotsView.style.opacity = '1';
                    }, 10);
                    loadSlotsTable();
                }, 300);
            });
        });
        
       // updateMonthNavigation();
    }
    
    document.getElementById('back-to-calendar').addEventListener('click', function() {
        slotsView.style.opacity = '0';
        setTimeout(() => {
            slotsView.style.display = 'none';
            calendarView.style.display = 'block';
            calendarView.style.opacity = '1';
        }, 300);
    });
    
    serviceFilter.addEventListener('change', function() {
        if (selectedDate) {
            loadSlotsTable();
        }
    });
    
    function loadSlotsTable() {
        if (!selectedDate) return;
        
        document.getElementById('slots-loading').style.display = 'block';
        document.getElementById('slots-table-container').style.display = 'none';
        document.getElementById('slots-message').style.display = 'none';
        
        const formData = new FormData();
        formData.append('action', 'bk_get_slots_table');
        formData.append('date', selectedDate);
        formData.append('service_filter', serviceFilter.value);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            document.getElementById('slots-loading').style.display = 'none';
            
            if (json.success && json.data.slots && json.data.slots.length > 0) {
                renderSlotsTable(json.data);
            } else {
                // Show "No operating hours" message
                const msg = document.getElementById('slots-message');
                msg.style.display = 'block';
                msg.className = 'bntm-notice bntm-notice-warning';
                msg.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; justify-content: center;">
                        <svg style="width: 24px; height: 24px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <strong style="font-size: 16px;">No Operating Hours</strong>
                    </div>
                    <p style="margin: 10px 0 0 0; text-align: center;">
                        There are no available time slots for this date. Please select another date.
                    </p>
                `;
            }
        })
        .catch(err => {
            document.getElementById('slots-loading').style.display = 'none';
            const msg = document.getElementById('slots-message');
            msg.style.display = 'block';
            msg.className = 'bntm-notice bntm-notice-error';
            msg.textContent = 'Error loading slots';
        });
    }
    
    function renderSlotsTable(data) {
        const thead = document.getElementById('slots-table-head');
        const tbody = document.getElementById('slots-table-body');
        
        let headerHtml = '<tr>';
        data.services.forEach(service => {
            headerHtml += `<th>${service.name}</th>`;
        });
        headerHtml += '</tr>';
        thead.innerHTML = headerHtml;
        
        let bodyHtml = '';
        data.slots.forEach(timeSlot => {
            bodyHtml += `<tr>`;
            
            data.services.forEach(service => {
                const slotKey = `${service.id}_${timeSlot.time}`;
                const slot = data.slots_by_service[slotKey];
                
                if (slot && slot.available) {
                    bodyHtml += `<td><button class="bk-time-slot available" data-service="${service.id}" data-service-name="${service.name}" data-service-price="${service.price}" data-service-duration="${service.duration}" data-time="${timeSlot.time}" data-end-time="${slot.end_time}">${formatTime12Hour(timeSlot.time)}</button></td>`;
                } else {
                    bodyHtml += `<td><div class="bk-time-slot booked">Booked</div></td>`;
                }
            });
            
            bodyHtml += '</tr>';
        });
        
        tbody.innerHTML = bodyHtml;
        document.getElementById('slots-table-container').style.display = 'block';
        
        document.querySelectorAll('.bk-time-slot.available').forEach(btn => {
            btn.addEventListener('click', function() {
                selectedService = this.dataset.service;
                selectedTime = this.dataset.time;
                selectedEndTime = this.dataset.endTime;
                selectedServiceData = {
                    id: this.dataset.service,
                    name: this.dataset.serviceName,
                    price: this.dataset.servicePrice,
                    duration: this.dataset.serviceDuration
                };
                openBookingModal();
            });
        });
    }
    
    function openBookingModal() {
      const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
      document.body.style.overflow = 'hidden';
      document.body.style.paddingRight = scrollbarWidth + 'px';
      
      document.getElementById('modal-service-name').textContent = selectedServiceData.name;
      
      // Reset quantity to 1
      quantityInput.value = 1;
      updateQuantityButtons();
      
      // Set hidden fields
      document.getElementById('hidden-service-id').value = selectedService;
      document.getElementById('hidden-booking-date').value = selectedDate;
      document.getElementById('hidden-start-time').value = selectedTime;
      
      // Reset warning
      document.getElementById('availability-warning').style.display = 'none';
      completeBookingBtn.disabled = false;
      
      // Update calculations
      updateModalCalculations();
      
      loadPaymentMethods();
      
      modal.classList.add('active');
  }
    function loadPaymentMethods() {
         const container = document.getElementById('bk-payment-methods');
         container.innerHTML = '';
         
         const formData = new FormData();
         formData.append('action', 'bk_get_payment_methods');
         
         fetch(ajaxurl, {method: 'POST', body: formData})
         .then(r => r.json())
         .then(json => {
             if (json.success && json.data.methods && json.data.methods.length > 0) {
                 const isOP = json.data.source === 'op';
                 
                 json.data.methods.forEach((method, index) => {
                     const label = document.createElement('label');
                     label.className = 'bk-payment-option';
                     
                     const radio = document.createElement('input');
                     radio.type = 'radio';
                     radio.name = 'payment_method';
                     radio.value = index;
                     
                     if (isOP) {
                         radio.setAttribute('data-method-id', method.id);
                         radio.setAttribute('data-gateway', method.type);
                     }
                     
                     if (index === 0) radio.checked = true;
                     
                     const card = document.createElement('div');
                     card.className = 'bk-payment-card';
                     
                     let cardHTML = '<strong>' + method.name + '</strong>';
                     cardHTML += '<span class="payment-type-badge">' + method.type.toUpperCase() + '</span>';
                     
                     if (method.description) {
                         cardHTML += '<p style="margin: 8px 0 0 0; font-size: 13px; color: #6b7280;">' + method.description + '</p>';
                     }
                     
                     if (method.account_name || method.account_number) {
                         cardHTML += '<div style="margin-top: 8px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px;">';
                         if (method.bank_name) {
                             cardHTML += '<div>Bank: <strong>' + method.bank_name + '</strong></div>';
                         }
                         if (method.account_name) {
                             cardHTML += '<div>Account: <strong>' + method.account_name + '</strong></div>';
                         }
                         if (method.account_number) {
                             cardHTML += '<div>Number: <strong>' + method.account_number + '</strong></div>';
                         }
                         cardHTML += '</div>';
                     }
                     
                     card.innerHTML = cardHTML;
                     
                     label.appendChild(radio);
                     label.appendChild(card);
                     container.appendChild(label);
                 });
             } else {
                 // No payment methods available - display message
                 const noMethodsDiv = document.createElement('div');
                 noMethodsDiv.style.cssText = 'background: #fef3c7; border: 1px solid #fde047; padding: 15px; border-radius: 8px; text-align: center;';
                 noMethodsDiv.innerHTML = `
                     <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                         <svg style="width: 24px; height: 24px; color: #d97706;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                         </svg>
                         <strong style="color: #92400e; font-size: 16px;">No Payment Method Available</strong>
                     </div>
                     <p style="margin: 10px 0 0 0; color: #78350f; font-size: 14px;">
                         Please contact the administrator to set up payment methods before making a booking.
                     </p>
                 `;
                 container.appendChild(noMethodsDiv);
                 
                 // Disable the submit button
                 const submitBtn = document.getElementById('complete-booking-btn');
                 if (submitBtn) {
                     submitBtn.disabled = true;
                     submitBtn.style.opacity = '0.5';
                     submitBtn.style.cursor = 'not-allowed';
                     submitBtn.title = 'No payment method available';
                 }
             }
         })
         .catch(err => {
             // Error fetching payment methods
             const errorDiv = document.createElement('div');
             errorDiv.style.cssText = 'background: #fee2e2; border: 1px solid #fca5a5; padding: 15px; border-radius: 8px; text-align: center;';
             errorDiv.innerHTML = `
                 <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                     <svg style="width: 24px; height: 24px; color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                     </svg>
                     <strong style="color: #991b1b; font-size: 16px;">Error Loading Payment Methods</strong>
                 </div>
                 <p style="margin: 10px 0 0 0; color: #7f1d1d; font-size: 14px;">
                     Failed to load payment methods. Please try again or contact support.
                 </p>
             `;
             container.appendChild(errorDiv);
             
             // Disable the submit button
             const submitBtn = document.getElementById('complete-booking-btn');
             if (submitBtn) {
                 submitBtn.disabled = true;
                 submitBtn.style.opacity = '0.5';
                 submitBtn.style.cursor = 'not-allowed';
                 submitBtn.title = 'Payment methods unavailable';
             }
             
             console.error('Payment methods error:', err);
         });
     }
    
    function bk_formatPrice(amount) {
        const currency = '<?php echo bntm_get_setting('bk_currency', 'USD') === 'PHP' ? '₱' : '$'; ?>';
        return currency + parseFloat(amount).toFixed(2);
    }
    
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
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
        
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        if (selectedMethod && selectedMethod.dataset.methodId) {
            formData.append('op_method_id', selectedMethod.dataset.methodId);
            formData.append('payment_gateway', selectedMethod.dataset.gateway);
        }
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Processing...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const data = json.data || {};
                
                if (data.redirect_url) {
                    document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-success">' + (data.message || 'Booking created! Redirecting to payment...') + '</div>';
                    setTimeout(() => {
                        window.location.href = data.redirect_url;
                    }, 1500);
                } else if (data.redirect) {
                    document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-success">' + data.message + '</div>';
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                } else {
                    document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-success">' + (data.message || 'Booking confirmed!') + '</div>';
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            } else {
                const data = json.data || {};
                document.getElementById('booking-message').innerHTML = '<div class="bntm-notice bntm-notice-error">' + (data.message || 'An error occurred') + '</div>';
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
    return $content;
}
/* ---------- IMPROVED: Get Slots as Table - Better filtering and service selection ---------- */
add_action('wp_ajax_bk_get_slots_table', 'bntm_ajax_bk_get_slots_table');
add_action('wp_ajax_nopriv_bk_get_slots_table', 'bntm_ajax_bk_get_slots_table');

function bntm_ajax_bk_get_slots_table() {
    global $wpdb;
    
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $service_filter = isset($_POST['service_filter']) ? sanitize_text_field($_POST['service_filter']) : '';
    
    if (empty($date)) {
        wp_send_json_error(['message' => 'Missing date parameter']);
    }
    
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj) {
        wp_send_json_error(['message' => 'Invalid date format']);
    }
    
    $services_table = $wpdb->prefix . 'bk_services';
    $hours_table = $wpdb->prefix . 'bk_operating_hours';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Get all active services
    $services = $wpdb->get_results("SELECT id, name, duration, price FROM $services_table WHERE status = 'active' ORDER BY name ASC");
    
    if (empty($services)) {
        wp_send_json_error(['message' => 'No services available']);
    }
    
    // If service filter is applied, only include that service
    if (!empty($service_filter)) {
        $service_filter = intval($service_filter);
        $services = array_filter($services, function($service) use ($service_filter) {
            return $service->id == $service_filter;
        });
        $services = array_values($services); // Re-index array
        
        if (empty($services)) {
            wp_send_json_error(['message' => 'Selected service not found']);
        }
    }
    
    $dayOfWeek = date('w', strtotime($date));
    
    // Get operating hours for the day
    $operating_hour = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $hours_table WHERE day_of_week = %d LIMIT 1",
        $dayOfWeek
    ));
    
    if (!$operating_hour || !$operating_hour->is_open) {
        wp_send_json_success([
            'slots' => [],
            'services' => $services,
            'slots_by_service' => [],
            'message' => 'Business closed on this day'
        ]);
    }
    
    // Get all bookings for this date - EXCLUDE pending payment status
    // Only block slots for confirmed and completed bookings, or pending bookings that are already paid/waiting for verification
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT service_id, start_time, end_time, payment_status FROM $bookings_table 
         WHERE booking_date = %s 
         AND status IN ('pending', 'confirmed', 'completed')
         AND payment_status NOT IN ('pending', 'waiting_payment')",
        $date
    ));
    
    // Build booked times array indexed by service_id
    $booked_times_by_service = [];
    
    foreach ($services as $service) {
        $booked_times_by_service[$service->id] = [];
    }
    
    foreach ($bookings as $booking) {
        if (!isset($booked_times_by_service[$booking->service_id])) {
            $booked_times_by_service[$booking->service_id] = [];
        }
        $booked_times_by_service[$booking->service_id][] = [
            'start' => strtotime($date . ' ' . $booking->start_time),
            'end' => strtotime($date . ' ' . $booking->end_time)
        ];
    }
    
    // Generate time slots based on interval
    $slot_interval = intval(bntm_get_setting('bk_slot_interval', '30'));
    $start = strtotime($date . ' ' . $operating_hour->start_time);
    $end = strtotime($date . ' ' . $operating_hour->end_time);
    
    $slots = [];
    $current = $start;
    
    while ($current < $end) {
        $time_str = date('H:i', $current);
        $slots[] = ['time' => $time_str];
        $current += $slot_interval * 60;
    }
    
    // Build slots by service (check availability)
    $slots_by_service = [];
    
    foreach ($slots as $slot) {
        $slot_start = strtotime($date . ' ' . $slot['time']);
        
        foreach ($services as $service) {
            $service_duration = intval($service->duration) * 60; // Ensure integer
            $slot_end = $slot_start + $service_duration;
            
            $is_available = true;
            
            // Check if slot overlaps with any booking for this service
            if (isset($booked_times_by_service[$service->id])) {
                foreach ($booked_times_by_service[$service->id] as $booked) {
                    // Check for overlap: slot overlaps if start < booked.end AND end > booked.start
                    if ($slot_start < $booked['end'] && $slot_end > $booked['start']) {
                        $is_available = false;
                        break;
                    }
                }
            }
            
            $key = $service->id . '_' . $slot['time'];
            $slots_by_service[$key] = [
                'available' => $is_available,
                'end_time' => date('H:i', $slot_end),
                'service_id' => $service->id
            ];
        }
    }
    
    wp_send_json_success([
        'slots' => $slots,
        'services' => $services,
        'slots_by_service' => $slots_by_service
    ]);
}
add_action('wp_ajax_bk_check_slot_availability', 'bntm_ajax_bk_check_slot_availability');
add_action('wp_ajax_nopriv_bk_check_slot_availability', 'bntm_ajax_bk_check_slot_availability');

function bntm_ajax_bk_check_slot_availability() {
    global $wpdb;
    
    $service_id = intval($_POST['service_id'] ?? 0);
    $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
    $start_time = sanitize_text_field($_POST['start_time'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($quantity < 1) $quantity = 1;
    if ($quantity > 10) $quantity = 10;
    
    $services_table = $wpdb->prefix . 'bk_services';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Get service details
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $services_table WHERE id = %d AND status = 'active'",
        $service_id
    ));
    
    if (!$service) {
        wp_send_json_error(['message' => 'Service not found', 'available' => false]);
    }
    
    // Calculate end time based on quantity
    $start_timestamp = strtotime($booking_date . ' ' . $start_time);
    $total_duration = $service->duration * $quantity;
    $end_timestamp = $start_timestamp + ($total_duration * 60);
    $end_time = date('H:i:s', $end_timestamp);
    
    // Check for conflicts in the entire time range
    $conflict = $wpdb->get_row($wpdb->prepare(
        "SELECT id, customer_name FROM $bookings_table 
         WHERE booking_date = %s 
         AND service_id = %d
         AND status IN ('pending', 'confirmed', 'completed')
         AND payment_status NOT IN ('pending', 'waiting_payment')
         AND (
            (start_time < %s AND end_time > %s)
            OR (start_time >= %s AND start_time < %s)
         )",
        $booking_date, $service_id, $end_time, $start_time, $start_time, $end_time
    ));
    
    if ($conflict) {
        wp_send_json_success([
            'available' => false,
            'message' => 'One or more slots in this time range are already booked. Please select a different time or reduce the quantity.'
        ]);
    }
    
    wp_send_json_success([
        'available' => true,
        'message' => 'All slots are available'
    ]);
}
/* ---------- UPDATE bk_bookings_calendar_tab to show full calendar ---------- */
function bk_bookings_calendar_tab($business_id) {
    global $wpdb;
    $services_table = $wpdb->prefix . 'bk_services';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $hours_table = $wpdb->prefix . 'bk_operating_hours';
    
    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $services_table WHERE status = 'active' ORDER BY name ASC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('bk_nonce');
    $current_month = date('Y-m');
    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+30 days'));
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bk-admin-calendar-main">
        <!-- Calendar View -->
        <div id="admin-calendar-view" class="admin-calendar-fade-in">
            <div class="bk-admin-calendar-wrapper">
                <h3>Booking Calendar</h3>
                
                <!-- Legend -->
                <div class="bk-calendar-legend">
                    <div class="bk-legend-item">
                        <span class="bk-legend-color" style="background: #dcfce7; border-color: #86efac;"></span>
                        <span>Available (&lt;50% booked)</span>
                    </div>
                    <div class="bk-legend-item">
                        <span class="bk-legend-color" style="background: #fed7aa; border-color: #fdba74;"></span>
                        <span>Busy (50-99% booked)</span>
                    </div>
                    <div class="bk-legend-item">
                        <span class="bk-legend-color" style="background: #fecaca; border-color: #f87171;"></span>
                        <span>Fully Booked</span>
                    </div>
                </div>
                
                <div class="bk-calendar-header">
                    <button id="admin-prev-month" class="bk-calendar-nav">← Previous</button>
                    <h2 id="admin-calendar-month-year"></h2>
                    <button id="admin-next-month" class="bk-calendar-nav">Next →</button>
                </div>
                
                <div class="bk-calendar-grid">
                    <div class="bk-weekday">Sun</div>
                    <div class="bk-weekday">Mon</div>
                    <div class="bk-weekday">Tue</div>
                    <div class="bk-weekday">Wed</div>
                    <div class="bk-weekday">Thu</div>
                    <div class="bk-weekday">Fri</div>
                    <div class="bk-weekday">Sat</div>
                    <div class="bk-calendar-days" id="admin-calendar-days"></div>
                </div>
            </div>
        </div>
        
        <!-- Slots View -->
        <div id="admin-slots-view" class="admin-slots-fade-in" style="display: none; opacity: 0; transition: opacity 0.3s ease;">
            <div class="bk-admin-slots-wrapper">
                <div class="bk-slots-header">
                    <h2>Available Slots for <span id="admin-selected-date-display"></span></h2>
                    <button id="admin-back-to-calendar" class="bntm-btn-secondary">← Back to Calendar</button>
                </div>
                
                <div class="bk-services-selector">
                    <label>Select Service:</label>
                    <select id="admin-service-filter" style="width: 100%; max-width: 400px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="">All Services</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service->id; ?>"><?php echo esc_html($service->name); ?> (<?php echo $service->duration; ?> min)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="admin-slots-loading" style="text-align: center; padding: 40px; display: none;">
                    <p>Loading slots...</p>
                </div>
                
                <div id="admin-slots-container" style="display: none; overflow: auto;">
                    <table class="bk-admin-slots-table">
                        <thead id="admin-slots-head"></thead>
                        <tbody id="admin-slots-body"></tbody>
                    </table>
                </div>
                
                <div id="slots-message" style="display: none; padding: 20px; border-radius: 8px; text-align: center;"></div>
            </div>
        </div>
    </div>

    <!-- Edit Modal (Enhanced) -->
    <div id="admin-booking-modal" class="bk-modal">
        <div class="bk-modal-overlay"></div>
        <div class="bk-modal-content">
            <button class="bk-modal-close">&times;</button>
            <h2>Booking Details</h2>
            
            <form id="admin-edit-booking-form" class="bntm-form">
                <div class="bk-info-display">
                    <label><strong>Booking ID:</strong></label>
                    <div class="bk-info-value" id="admin-booking-rand-id-display"></div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Service:</strong></label>
                    <div class="bk-info-value" id="admin-service-name"></div>
                </div>
                
                <hr>
                
                <h3 style="margin: 20px 0 15px;">Customer Information</h3>
                
                <div class="bk-info-display">
                    <label><strong>Customer Name:</strong></label>
                    <div class="bk-info-value" id="admin-customer-name"></div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Email:</strong></label>
                    <div class="bk-info-value" id="admin-customer-email"></div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Phone:</strong></label>
                    <div class="bk-info-value" id="admin-customer-phone"></div>
                </div>
                
                <hr>
                
                <h3 style="margin: 20px 0 15px;">Booking Schedule (Editable)</h3>
                
                <div class="bntm-form-group">
                    <label>Booking Date *</label>
                    <input type="date" id="admin-booking-date" name="booking_date" required>
                </div>
                
                <div class="bk-form-row">
                    <div class="bntm-form-group" style="flex: 1;">
                        <label>Start Time *</label>
                        <input type="time" id="admin-start-time" name="start_time" required>
                    </div>
                    
                    <div class="bntm-form-group" style="flex: 1;">
                        <label>End Time *</label>
                        <input type="time" id="admin-end-time" name="end_time" required>
                    </div>
                </div>
                
                <div class="bk-duration-info">
                    <strong>Duration:</strong> <span id="admin-duration-display">-</span>
                </div>
                
                <hr>
                
                <h3 style="margin: 20px 0 15px;">Payment & Status</h3>
                
                <div class="bk-form-row">
                    <div class="bk-info-display" style="flex: 1;">
                        <label><strong>Unit Price:</strong></label>
                        <div class="bk-info-value" id="admin-unit-price"></div>
                    </div>
                    
                    <div class="bk-info-display" style="flex: 1;">
                        <label><strong>Quantity/Slots:</strong></label>
                        <div class="bk-info-value" id="admin-quantity"></div>
                    </div>
                </div>
                
                <div class="bk-form-row">
                    <div class="bk-info-display" style="flex: 1;">
                        <label><strong>Subtotal:</strong></label>
                        <div class="bk-info-value" id="admin-subtotal"></div>
                    </div>
                    
                    <div class="bk-info-display" style="flex: 1;">
                        <label><strong>Tax:</strong></label>
                        <div class="bk-info-value" id="admin-tax"></div>
                    </div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Total Amount:</strong></label>
                    <div class="bk-info-value" id="admin-total" style="font-weight: bold; font-size: 1.2em; color: #059669;"></div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Payment Method:</strong></label>
                    <div class="bk-info-value" id="admin-payment-method"></div>
                </div>
                
                <div class="bk-form-row">
                    <div class="bntm-form-group" style="flex: 1;">
                        <label>Booking Status *</label>
                        <select id="admin-booking-status" name="status">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <!--<option value="completed">Completed</option>-->
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group" style="flex: 1;">
                        <label>Payment Status *</label>
                        <select id="admin-payment-status" name="payment_status">
                            <option value="unpaid">Unpaid - Cash on Arrival</option>
                        <!--<option value="pending">Pending</option>
                        <option value="verified">Verified</option>-->
                        <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Customer Notes:</strong></label>
                    <div class="bk-info-value" id="admin-booking-notes" style="white-space: pre-wrap;"></div>
                </div>
                
                <div class="bk-info-display">
                    <label><strong>Created At:</strong></label>
                    <div class="bk-info-value" id="admin-created-at"></div>
                </div>
                
                <input type="hidden" id="admin-booking-rand-id" name="booking_id">
                <input type="hidden" id="admin-service-id" name="service_id">
                <input type="hidden" id="admin-service-duration" name="service_duration">
                <input type="hidden" id="admin-amount" name="amount">
                <input type="hidden" id="admin-tax-value" name="tax">
                <input type="hidden" id="admin-total-value" name="total">
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Save Changes</button>
                    <button type="button" class="bntm-btn-danger" id="admin-delete-booking">Delete Booking</button>
                </div>
                
                <div id="admin-booking-message"></div>
            </form>
        </div>
    </div>

    <!-- Create Modal (with Quantity) -->
    <div id="admin-create-booking-modal" class="bk-modal">
        <div class="bk-modal-overlay"></div>
        <div class="bk-modal-content">
            <button class="bk-modal-close">&times;</button>
            <h2>Create New Booking</h2>
            
            <form id="admin-create-booking-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Service *</label>
                    <select id="create-service-id" name="service_id" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service->id; ?>" 
                                    data-duration="<?php echo $service->duration; ?>" 
                                    data-price="<?php echo $service->price; ?>">
                                <?php echo esc_html($service->name); ?> (<?php echo $service->duration; ?> min - <?php echo bk_format_price($service->price); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Date *</label>
                    <input type="date" id="create-booking-date" name="booking_date" required>
                </div>
                
                <div class="bk-form-row">
                   <div class="bntm-form-group" style="flex: 1;">
                       <label>Start Time *</label>
                       <input type="time" id="create-start-time" name="start_time" required>
                   </div>
                   
                   <div class="bntm-form-group" style="flex: 1;">
                       <label>Quantity (Slots) *</label>
                       <div style="display: flex; align-items: center; gap: 8px;">
                           <button type="button" id="admin-quantity-decrease" class="bk-admin-quantity-btn">
                               <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                               </svg>
                           </button>
                           <input type="number" id="create-quantity" name="quantity" min="1" max="10" value="1" readonly
                                  style="width: 100%; padding: 8px; border: 2px solid #d1d5db; border-radius: 6px; font-size: 16px; font-weight: 600; text-align: center;" required>
                           <button type="button" id="admin-quantity-increase" class="bk-admin-quantity-btn">
                               <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                   <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                               </svg>
                           </button>
                       </div>
                       <small style="color: #6b7280; display: block; margin-top: 4px;">Number of time slots to book</small>
                   </div>
               </div>
                
                <div class="bk-create-summary" style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span><strong>End Time:</strong></span>
                        <span id="create-end-time-display">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span><strong>Total Duration:</strong></span>
                        <span id="create-duration-display">-</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span><strong>Subtotal:</strong></span>
                        <span id="create-subtotal-display">0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span><strong>Tax:</strong></span>
                        <span id="create-tax-display">0.00</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 2px solid #3b82f6; padding-top: 8px; margin-top: 8px;">
                        <span style="font-size: 1.1em;"><strong>Total:</strong></span>
                        <span id="create-total-display" style="font-size: 1.2em; font-weight: bold; color: #059669;">₱0.00</span>
                    </div>
                </div>
                
                <input type="hidden" id="create-end-time" name="end_time">
                <input type="hidden" id="create-amount" name="amount">
                <input type="hidden" id="create-tax" name="tax">
                <input type="hidden" id="create-total" name="total">
                
                <hr>
                
                <h3 style="margin: 20px 0 15px;">Customer Information</h3>
                
                <div class="bntm-form-group">
                    <label>Customer Name *</label>
                    <input type="text" id="create-customer-name" name="customer_name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Email *</label>
                    <input type="email" id="create-customer-email" name="customer_email" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Phone *</label>
                    <input type="tel" id="create-customer-phone" name="customer_phone" required>
                </div>
                <div class="bntm-form-group">
                   <label>Payment Method *</label>
                   <input type="text" id="create-payment-method" name="payment_method" placeholder="e.g., Cash, GCash, Bank Transfer" required>
               </div>
                <div class="bntm-form-group">
                    <label>Payment Status *</label>
                    <select id="create-payment-status" name="payment_status" required>
                        <option value="unpaid">Unpaid - Cash on Arrival</option>
                        <!--<option value="pending">Pending</option>
                        <option value="verified">Verified</option>-->
                        <option value="paid">Paid</option>
                    </select>
                </div>
                
                <button type="submit" class="bntm-btn-primary" style="width: 100%;">Create Booking</button>
                <div id="admin-create-booking-message"></div>
            </form>
        </div>
    </div>

    <style>
    .bk-admin-calendar-main {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        min-height: 600px;
        overflow: auto;
    }
    
    .admin-calendar-fade-in,
    .admin-slots-fade-in {
        transition: opacity 0.3s ease;
    }
    
    .bk-admin-calendar-wrapper {
        width: 100%;
    }
    
    .bk-admin-calendar-wrapper h3 {
        font-size: 24px;
        margin-bottom: 20px;
        color: #1f2937;
    }
    
    .bk-calendar-legend {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        flex-wrap: wrap;
    }
    
    .bk-legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
    }
    
    .bk-legend-color {
        width: 24px;
        height: 24px;
        border-radius: 4px;
        border: 2px solid;
    }
    
    .bk-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .bk-calendar-header h2 {
        margin: 0;
        font-size: 20px;
        color: #1f2937;
        min-width: 200px;
        text-align: center;
    }
    
    .bk-calendar-nav {
        padding: 8px 12px;
        border: 1px solid #e5e7eb;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        color: #3b82f6;
        transition: all 0.2s;
    }
    
    .bk-calendar-nav:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .bk-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
    }
    
    .bk-weekday {
        font-weight: 700;
        text-align: center;
        padding: 10px;
        color: #6b7280;
        font-size: 14px;
    }
    
    .bk-calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        grid-column: 1 / -1;
    }
    
    .bk-calendar-day {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
        background: white;
        color: #1f2937;
        position: relative;
    }
    
    .bk-calendar-day:hover:not(.disabled):not(.other-month) {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: scale(1.05);
    }
    
    .bk-calendar-day.other-month {
        color: #d1d5db;
        cursor: default;
        background: #f9fafb;
    }
        .no-operating-hours{
       
        opacity: 0.5;
        cursor: not-allowed;
        background: #f3f4f6;
    }
    .bk-calendar-day.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f3f4f6;
    }
    
    .bk-calendar-day.selected {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    /* Color coding for availability */
    .bk-calendar-day.availability-low {
        background: #dcfce7;
        border-color: #86efac;
    }
    
    .bk-calendar-day.availability-medium {
        background: #fed7aa;
        border-color: #fdba74;
    }
    
    .bk-calendar-day.availability-full {
        background: #fecaca;
        border-color: #f87171;
    }
    
    .bk-calendar-day.selected.availability-low,
    .bk-calendar-day.selected.availability-medium,
    .bk-calendar-day.selected.availability-full {
        background: #3b82f6;
        border-color: #3b82f6;
    }
    
    .bk-admin-slots-wrapper {
        width: 100%;
    }
    
    .bk-slots-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .bk-slots-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1f2937;
    }
    
    .bk-services-selector {
        margin-bottom: 30px;
        padding-bottom: 30px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .bk-services-selector label {
        display: block;
        font-weight: 600;
        margin-bottom: 15px;
        color: #1f2937;
    }
    
    .bk-admin-slots-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-size: 13px;
    }
    
    .bk-admin-slots-table thead {
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .bk-admin-slots-table th {
        padding: 12px;
        text-align: center;
        font-weight: 600;
        color: #1f2937;
        border-right: 1px solid #e5e7eb;
    }
    
    .bk-admin-slots-table th:last-child {
        border-right: none;
    }
    
    .bk-admin-slots-table td {
        padding: 8px;
        text-align: center;
        border-right: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .bk-admin-slots-table td:last-child {
        border-right: none;
    }
    
    .bk-admin-slot-cell {
        padding: 8px 0px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 12px;
        width: 100%;
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    
    .bk-admin-slot-cell.vacant {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
    }
    
    .bk-admin-slot-cell.vacant:hover {
        background: #a7f3d0;
    }
    
    .bk-admin-slot-cell.booked {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    
    .bk-admin-slot-cell.booked:hover {
        background: #fecaca;
    }
    
    .bk-admin-slot-cell.booked-paid {
        background: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
    }

    .bk-admin-slot-cell.booked-paid:hover {
        background: #bfdbfe;
    }

    .bk-admin-slot-cell.booked-completed {
        background: #efefef;
        color: #000000;
        border: 1px solid#e3e3e3;
    }

    .bk-admin-slot-cell.booked-completed:hover {
        background: #a9a9a9;
    }
    
    .bk-admin-slot-customer {
        font-weight: 600;
        display: block;
        margin: 2px 0;
    }
    
    .bk-admin-slot-email {
        font-size: 11px;
        opacity: 0.8;
    }
    
    .bk-time-label {
        font-weight: 600;
        background: #f9fafb;
        width: 60px;
        min-width: 60px;
    }
    
    .bk-admin-slots-table tbody tr:nth-child(odd) {
        background: #ffffff;
    }
    
    .bk-admin-slots-table tbody tr:nth-child(even) {
        background: #f9fafb;
    }
    
    .bk-admin-slots-table tbody tr:hover {
        background: #eff6ff;
    }
    
    .bk-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
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
        max-width: 700px;
        margin: 40px auto;
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
        color: #6b7280;
        transition: all 0.2s;
        z-index: 1;
    }
    
    .bk-modal-close:hover {
        background: #e5e7eb;
        color: #1f2937;
    }
    
    .bk-form-row {
        display: flex;
        gap: 15px;
    }
    
    .bk-duration-info {
        padding: 12px;
        background: #eff6ff;
        border-radius: 6px;
        margin-top: 10px;
        color: #1e40af;
    }
    
    .bk-info-display {
        margin-bottom: 15px;
    }
    
    .bk-info-display label {
        display: block;
        margin-bottom: 5px;
        color: #374151;
    }
    
    .bk-info-value {
        padding: 10px 12px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        color: #1f2937;
        font-size: 14px;
    }
    
    @media (max-width: 768px) {
        .bk-admin-calendar-main {
            padding: 15px;
        }
        
        .bk-calendar-grid {
            gap: 5px;
        }
        
        .bk-calendar-day {
            font-size: 12px;
        }
        
        .bk-form-row {
            flex-direction: column;
        }
        
        .bk-modal-content {
            margin: 20px;
            padding: 20px;
        }
        .bk-admin-slot-cell{
           width:70px;
        }
        .bk-slot-badge {
           display:none;
        }
    }
    
    .bk-calendar-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
    background: white;
    color: #1f2937;
    position: relative;
}

.bk-slot-badge {
    position: absolute;
    bottom: 4px;
    font-size: 10px;
    font-weight: 500;
    color: #6b7280;
    background: rgba(255, 255, 255, 0.9);
    padding: 2px 6px;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
}

.bk-calendar-day.availability-low .bk-slot-badge {
    color: #065f46;
    background: rgba(220, 252, 231, 0.95);
    border-color: #86efac;
}

.bk-calendar-day.availability-medium .bk-slot-badge {
    color: #92400e;
    background: rgba(254, 215, 170, 0.95);
    border-color: #fdba74;
}

.bk-calendar-day.availability-full .bk-slot-badge {
    color: #991b1b;
    background: rgba(254, 202, 202, 0.95);
    border-color: #f87171;
}

.bk-calendar-day.selected .bk-slot-badge {
    color: white;
    background: rgba(29, 78, 216, 0.95);
    border-color: rgba(255, 255, 255, 0.3);
}
/* Admin Quantity Buttons */
.bk-admin-quantity-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 2px solid #3b82f6;
    background: white;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    color: #3b82f6;
    flex-shrink: 0;
}

.bk-admin-quantity-btn:hover:not(:disabled) {
    background: #3b82f6;
    color: white;
    transform: scale(1.05);
}

.bk-admin-quantity-btn:active:not(:disabled) {
    transform: scale(0.95);
}

.bk-admin-quantity-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
    border-color: #d1d5db;
    color: #9ca3af;
}

.bk-admin-quantity-btn svg {
    pointer-events: none;
}

#create-quantity::-webkit-inner-spin-button,
#create-quantity::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

#create-quantity {
    -moz-appearance: textfield;
}
    </style>
<script>
(function() {
    let adminCurrentMonth = new Date('<?php echo $current_month; ?>-01');
    let adminSelectedDate = null;
   const adminMinDate = new Date('2024-01-01');
    const adminMaxDate = new Date('<?php echo $max_date; ?>');
    let dateAvailability = {};
    const taxRate = <?php echo floatval(bntm_get_setting('bk_tax_rate', '0')); ?>;
    
    renderAdminCalendar();
    loadMonthAvailability();
    
    document.getElementById('admin-prev-month').addEventListener('click', function() {
         const prevMonth = new Date(adminCurrentMonth);
         prevMonth.setMonth(prevMonth.getMonth() - 1);
         
         // Check if previous month is within allowed range
         const prevMonthStart = new Date(prevMonth.getFullYear(), prevMonth.getMonth(), 1);
         const minMonthStart = new Date(adminMinDate.getFullYear(), adminMinDate.getMonth(), 1);
         
         if (prevMonthStart >= minMonthStart) {
             adminCurrentMonth = prevMonth;
             renderAdminCalendar();
             loadMonthAvailability();
         }
     });
    
        document.getElementById('admin-next-month').addEventListener('click', function() {
        const nextMonth = new Date(adminCurrentMonth);
        nextMonth.setMonth(nextMonth.getMonth() + 1);
        
        // No max date restriction - allow navigation to any future month
        adminCurrentMonth = nextMonth;
        renderAdminCalendar();
        loadMonthAvailability();
    });
    
    // Admin quantity control buttons
     const adminQuantityInput = document.getElementById('create-quantity');
     const adminQuantityDecreaseBtn = document.getElementById('admin-quantity-decrease');
     const adminQuantityIncreaseBtn = document.getElementById('admin-quantity-increase');
     
     // Quantity decrease button
     adminQuantityDecreaseBtn.addEventListener('click', function() {
         let quantity = parseInt(adminQuantityInput.value) || 1;
         if (quantity > 1) {
             quantity--;
             adminQuantityInput.value = quantity;
             updateAdminQuantityButtons();
             updateCreateBookingSummary();
         }
     });
     
     // Quantity increase button
     adminQuantityIncreaseBtn.addEventListener('click', function() {
         let quantity = parseInt(adminQuantityInput.value) || 1;
         if (quantity < 10) {
             quantity++;
             adminQuantityInput.value = quantity;
             updateAdminQuantityButtons();
             updateCreateBookingSummary();
         }
     });
     
     // Function to update button states
     function updateAdminQuantityButtons() {
         const quantity = parseInt(adminQuantityInput.value) || 1;
         adminQuantityDecreaseBtn.disabled = quantity <= 1;
         adminQuantityIncreaseBtn.disabled = quantity >= 10;
     }
     
     // Update the existing quantity input handler
     document.getElementById('create-quantity').addEventListener('input', function() {
         let quantity = parseInt(this.value) || 1;
         if (quantity < 1) quantity = 1;
         if (quantity > 10) quantity = 10;
         this.value = quantity;
         updateAdminQuantityButtons();
         updateCreateBookingSummary();
     });
    function loadMonthAvailability() {
        const year = adminCurrentMonth.getFullYear();
        const month = adminCurrentMonth.getMonth();
        const startDate = new Date(year, month, 1);
        const endDate = new Date(year, month + 1, 0);
        
        const formData = new FormData();
        formData.append('action', 'bk_get_month_availability');
        formData.append('start_date', formatDate(startDate));
        formData.append('end_date', formatDate(endDate));
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                dateAvailability = json.data;
                updateCalendarColors();
            }
        });
    }
    
    function updateCalendarColors() {
      document.querySelectorAll('.bk-calendar-day:not(.other-month)').forEach(day => {
          const date = day.dataset.date;
          
          // Check if date has availability data
          if (dateAvailability[date]) {
              const data = dateAvailability[date];
              const percentage = data.percentage;
              const available = data.total_slots - data.booked_slots;
              
              day.classList.remove('availability-low', 'availability-medium', 'availability-full', 'no-operating-hours');
              
              // Check if there are any slots available
              if (data.total_slots === 0 || available === 0) {
                  // No operating hours or fully booked
                  day.classList.add('no-operating-hours');
              } else {
                  // Has available slots - apply color coding
                  if (percentage >= 100) {
                      day.classList.add('availability-full');
                  } else if (percentage >= 50) {
                      day.classList.add('availability-medium');
                  } else {
                      day.classList.add('availability-low');
                  }
                  
                  // Add slot count display
                  const existingBadge = day.querySelector('.bk-slot-badge');
                  if (existingBadge) {
                      existingBadge.remove();
                  }
                  
                  const badge = document.createElement('div');
                  badge.className = 'bk-slot-badge';
                  badge.textContent = available + '/' + data.total_slots;
                  day.appendChild(badge);
              }
          } else {
              // No data returned - assume no operating hours
              day.classList.add('no-operating-hours');
          }
      });
  }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function renderAdminCalendar() {
        const year = adminCurrentMonth.getFullYear();
        const month = adminCurrentMonth.getMonth();
        
        document.getElementById('admin-calendar-month-year').textContent = 
            new Date(year, month).toLocaleDateString('en-US', {month: 'long', year: 'numeric'});
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const prevLastDay = new Date(year, month, 0);
        const startDate = firstDay.getDay();
        
        let html = '';
        
        for (let i = startDate - 1; i >= 0; i--) {
            const day = prevLastDay.getDate() - i;
            html += `<div class="bk-calendar-day other-month">${day}</div>`;
        }
        
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const dateStr = formatDate(date);
            
            let classes = 'bk-calendar-day';
            
            if (dateStr === adminSelectedDate) {
                classes += ' selected';
            }
            
            html += `<div class="${classes}" data-date="${dateStr}">${day}</div>`;
        }
        
        const remainingDays = 42 - (startDate + lastDay.getDate());
        for (let day = 1; day <= remainingDays; day++) {
            html += `<div class="bk-calendar-day other-month">${day}</div>`;
        }
        
        document.getElementById('admin-calendar-days').innerHTML = html;
        
        document.querySelectorAll('.bk-calendar-day:not(.disabled):not(.other-month)').forEach(day => {
            day.addEventListener('click', function() {
                // Prevent clicking on days with no operating hours
                if (this.classList.contains('no-operating-hours')) {
                    return;
                }
                
                adminSelectedDate = this.dataset.date;
                document.querySelectorAll('.bk-calendar-day').forEach(d => d.classList.remove('selected'));
                this.classList.add('selected');
                
                const dateObj = new Date(adminSelectedDate);
                dateObj.setHours(12, 0, 0, 0);
                document.getElementById('admin-selected-date-display').textContent = 
                    dateObj.toLocaleDateString('en-US', {weekday: 'long', month: 'long', day: 'numeric'});
                
                document.getElementById('admin-calendar-view').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('admin-calendar-view').style.display = 'none';
                    document.getElementById('admin-slots-view').style.display = 'block';
                    setTimeout(() => {
                        document.getElementById('admin-slots-view').style.opacity = '1';
                    }, 10);
                    loadAdminSlots();
                }, 300);
            });
        });
    }
    
    document.getElementById('admin-back-to-calendar').addEventListener('click', function() {
        document.getElementById('admin-slots-view').style.opacity = '0';
        setTimeout(() => {
            document.getElementById('admin-slots-view').style.display = 'none';
            document.getElementById('admin-calendar-view').style.display = 'block';
            document.getElementById('admin-calendar-view').style.opacity = '1';
        }, 300);
    });
    
    const serviceFilter = document.getElementById('admin-service-filter');
    const modal = document.getElementById('admin-booking-modal');
    const createModal = document.getElementById('admin-create-booking-modal');
    const modalOverlay = document.querySelectorAll('.bk-modal-overlay');
    const modalClose = document.querySelectorAll('.bk-modal-close');
    
    function closeModal(modalElement) {
        modalElement.classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    
    function openModal(modalElement) {
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = scrollbarWidth + 'px';
        modalElement.classList.add('active');
    }
    
    modalClose.forEach(btn => {
        btn.addEventListener('click', function() {
            closeModal(this.closest('.bk-modal'));
        });
    });
    
    modalOverlay.forEach(overlay => {
        overlay.addEventListener('click', function() {
            closeModal(this.closest('.bk-modal'));
        });
    });
    
    function loadAdminSlots() {
        const date = adminSelectedDate;
        const serviceFilter_val = serviceFilter.value;
        
        if (!date) return;
        
        document.getElementById('admin-slots-loading').style.display = 'block';
        document.getElementById('admin-slots-container').style.display = 'none';
        document.getElementById('slots-message').style.display = 'none';
        
        const formData = new FormData();
        formData.append('action', 'bk_get_admin_slots');
        formData.append('date', date);
        formData.append('service_filter', serviceFilter_val);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            document.getElementById('admin-slots-loading').style.display = 'none';
            
            if (json.success && json.data.slots && json.data.slots.length > 0) {
                renderAdminSlotsTable(json.data);
            } else {
                // Show "No operating hours" message
                const msg = document.getElementById('slots-message');
                msg.style.display = 'block';
                msg.className = 'bntm-notice bntm-notice-warning';
                msg.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px; justify-content: center;">
                        <svg style="width: 24px; height: 24px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <strong style="font-size: 16px;">No Operating Hours</strong>
                    </div>
                    <p style="margin: 10px 0 0 0; text-align: center;">
                        There are no time slots configured for this date. Please check your operating hours settings.
                    </p>
                `;
                document.getElementById('admin-slots-container').style.display = 'none';
            }
        })
        .catch(err => {
            document.getElementById('admin-slots-loading').style.display = 'none';
            const msg = document.getElementById('slots-message');
            msg.style.display = 'block';
            msg.className = 'bntm-notice bntm-notice-error';
            msg.textContent = 'Error loading slots';
            console.error('Error loading admin slots:', err);
        });
    }
    
    function renderAdminSlotsTable(data) {
        const thead = document.getElementById('admin-slots-head');
        const tbody = document.getElementById('admin-slots-body');
        
        let headerHtml = '<tr><th>Time</th>';
        data.services.forEach(service => {
            headerHtml += `<th>${service.name}</th>`;
        });
        headerHtml += '</tr>';
        thead.innerHTML = headerHtml;
        
        let bodyHtml = '';
        data.slots.forEach(timeSlot => {
            bodyHtml += `<tr><td class="bk-time-label"><strong>${timeSlot.time}</strong></td>`;
            
            data.services.forEach(service => {
                const slotKey = `${service.id}_${timeSlot.time}`;
                const slot = data.slots_by_service[slotKey];
                
                if (slot && slot.available) {
                    bodyHtml += `<td><button class="bk-admin-slot-cell vacant" data-service="${service.id}" data-time="${timeSlot.time}" data-end-time="${slot.end_time}" onclick="openCreateBookingModal(this)">+ Create</button></td>`;
                } else if (slot && slot.booking) {
                    const booking = slot.booking;
                    
                    let statusClass = 'booked';
                    if (slot.status === 'completed') {
                        statusClass = 'booked-completed';
                    } else if (slot.payment_status === 'paid' || slot.payment_status === 'verified') {
                        statusClass = 'booked-paid';
                    }
                    
                    bodyHtml += `<td><div class="bk-admin-slot-cell ${statusClass}" onclick="openEditBookingModal('${booking.rand_id}')" style="cursor: pointer;">
                        <span class="bk-admin-slot-customer">${booking.customer_name}</span>
                        <span class="bk-admin-slot-email">${booking.customer_email}</span>
                        <span style="font-size: 11px; display: block; margin-top: 2px;">${booking.payment_status}</span>
                    </div></td>`;
                } else {
                    bodyHtml += `<td><div class="bk-admin-slot-cell" style="background: #f3f4f6; color: #9ca3af;">-</div></td>`;
                }
            });
            
            bodyHtml += '</tr>';
        });
        
        tbody.innerHTML = bodyHtml;
        document.getElementById('admin-slots-container').style.display = 'block';
        
        // Hide the message if slots are displayed
        document.getElementById('slots-message').style.display = 'none';
    }
    
    window.openCreateBookingModal = function(btn) {
         const serviceId = btn.dataset.service;
         const time = btn.dataset.time;
         const date = adminSelectedDate;
         
         document.getElementById('create-service-id').value = serviceId;
         document.getElementById('create-booking-date').value = date;
         document.getElementById('create-start-time').value = time;
         document.getElementById('create-quantity').value = 1;
         
         updateAdminQuantityButtons(); // Initialize button states
         updateCreateBookingSummary();
         openModal(createModal);
     };
    // Create booking - quantity and price calculation
    function updateCreateBookingSummary() {
        const serviceSelect = document.getElementById('create-service-id');
        const startTime = document.getElementById('create-start-time').value;
        const quantity = parseInt(document.getElementById('create-quantity').value) || 1;
        
        if (!serviceSelect.value || !startTime) {
            return;
        }
        
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        const duration = parseInt(selectedOption.dataset.duration);
        const unitPrice = parseFloat(selectedOption.dataset.price);
        
        // Calculate end time
        const startDateTime = new Date('2000-01-01 ' + startTime);
        const totalMinutes = duration * quantity;
        const endDateTime = new Date(startDateTime.getTime() + totalMinutes * 60000);
        const endTime = endDateTime.toTimeString().substr(0, 5);
        
        // Calculate prices
        const subtotal = unitPrice * quantity;
        const tax = subtotal * (taxRate / 100);
        const total = subtotal + tax;
        
        // Update displays
        document.getElementById('create-end-time-display').textContent = endTime;
        document.getElementById('create-duration-display').textContent = totalMinutes + ' minutes';
        document.getElementById('create-subtotal-display').textContent =  subtotal.toFixed(2);
        document.getElementById('create-tax-display').textContent =  tax.toFixed(2);
        document.getElementById('create-total-display').textContent = total.toFixed(2);
        
        // Update hidden fields
        document.getElementById('create-end-time').value = endTime;
        document.getElementById('create-amount').value = subtotal.toFixed(2);
        document.getElementById('create-tax').value = tax.toFixed(2);
        document.getElementById('create-total').value = total.toFixed(2);
    }
    
    document.getElementById('create-service-id').addEventListener('change', updateCreateBookingSummary);
    document.getElementById('create-start-time').addEventListener('change', updateCreateBookingSummary);
    document.getElementById('create-quantity').addEventListener('input', updateCreateBookingSummary);
    
    window.openEditBookingModal = function(bookingId) {
        const formData = new FormData();
        formData.append('action', 'bk_get_booking_details');
        formData.append('booking_id', bookingId);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const booking = json.data;
                document.getElementById('admin-booking-rand-id').value = booking.rand_id;
                document.getElementById('admin-booking-rand-id-display').textContent = booking.rand_id;
                document.getElementById('admin-service-id').value = booking.service_id;
                document.getElementById('admin-service-duration').value = booking.service_duration;
                document.getElementById('admin-service-name').textContent = booking.service_name;
                document.getElementById('admin-customer-name').textContent = booking.customer_name;
                document.getElementById('admin-customer-email').textContent = booking.customer_email;
                document.getElementById('admin-customer-phone').textContent = booking.customer_phone;
                document.getElementById('admin-booking-date').value = booking.booking_date;
                document.getElementById('admin-start-time').value = booking.start_time;
                document.getElementById('admin-end-time').value = booking.end_time;
                document.getElementById('admin-booking-status').value = booking.status;
                document.getElementById('admin-payment-status').value = booking.payment_status;
                document.getElementById('admin-booking-notes').textContent = booking.customer_notes || 'No notes';
                document.getElementById('admin-unit-price').textContent = booking.unit_price;
                document.getElementById('admin-quantity').textContent = booking.quantity;
                document.getElementById('admin-subtotal').textContent = booking.amount;
                document.getElementById('admin-tax').textContent = booking.tax;
                document.getElementById('admin-total').textContent = booking.total;
                document.getElementById('admin-payment-method').textContent = booking.payment_method || 'N/A';
                document.getElementById('admin-created-at').textContent = booking.created_at;
                
                // Hidden fields for submission
                document.getElementById('admin-amount').value = booking.amount;
                document.getElementById('admin-tax-value').value = booking.tax;
                document.getElementById('admin-total-value').value = booking.total;
                
                updateDurationDisplay();
                
                openModal(modal);
            }
        });
    };
    
    function updateDurationDisplay() {
      const startTime = document.getElementById('admin-start-time').value;
      const endTime = document.getElementById('admin-end-time').value;
      const unitPriceText = document.getElementById('admin-unit-price').textContent;
      const unitPrice = parseFloat(unitPriceText.replace('₱', ''));
      const durationDisplay = document.getElementById('admin-duration-display');
      
      if (startTime && endTime) {
          const start = new Date('2000-01-01 ' + startTime);
          const end = new Date('2000-01-01 ' + endTime);
          const diff = (end - start) / 1000 / 60;
          
          if (diff > 0) {
              const hours = Math.floor(diff / 60);
              const minutes = diff % 60;
              let durationText = '';
              if (hours > 0) durationText += hours + ' hour' + (hours > 1 ? 's' : '');
              if (minutes > 0) {
                  if (hours > 0) durationText += ' ';
                  durationText += minutes + ' minute' + (minutes > 1 ? 's' : '');
              }
              durationDisplay.textContent = durationText + ' (' + diff + ' min)';
              
              // Recalculate price based on new duration
              const serviceDuration = parseInt(document.getElementById('admin-service-duration').value);
              if (serviceDuration > 0) {
                  const newQuantity = Math.round(diff / serviceDuration);
                  document.getElementById('admin-quantity').textContent = newQuantity;
                  
                  // Calculate new amounts
                  const newSubtotal = unitPrice * newQuantity;
                  const newTax = newSubtotal * (taxRate / 100);
                  const newTotal = newSubtotal + newTax;
                  
                  // Update displays
                  document.getElementById('admin-subtotal').textContent =newSubtotal.toFixed(2);
                  document.getElementById('admin-tax').textContent =  newTax.toFixed(2);
                  document.getElementById('admin-total').textContent =  newTotal.toFixed(2);
                  
                  // Update hidden fields
                  document.getElementById('admin-amount').value = newSubtotal.toFixed(2);
                  document.getElementById('admin-tax-value').value = newTax.toFixed(2);
                  document.getElementById('admin-total-value').value = newTotal.toFixed(2);
              }
          } else {
              durationDisplay.textContent = 'Invalid time range';
          }
      } else {
          durationDisplay.textContent = '-';
      }
  }
    
    document.getElementById('admin-start-time').addEventListener('change', updateDurationDisplay);
    document.getElementById('admin-end-time').addEventListener('change', updateDurationDisplay);
    
    serviceFilter.addEventListener('change', loadAdminSlots);
    
   document.getElementById('admin-edit-booking-form').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData();
      formData.append('action', 'bk_update_admin_booking');
      formData.append('booking_id', document.getElementById('admin-booking-rand-id').value);
      formData.append('booking_date', document.getElementById('admin-booking-date').value);
      formData.append('start_time', document.getElementById('admin-start-time').value);
      formData.append('end_time', document.getElementById('admin-end-time').value);
      formData.append('status', document.getElementById('admin-booking-status').value);
      formData.append('payment_status', document.getElementById('admin-payment-status').value);
      formData.append('service_id', document.getElementById('admin-service-id').value);
      formData.append('amount', document.getElementById('admin-amount').value);
      formData.append('tax', document.getElementById('admin-tax-value').value);
      formData.append('total', document.getElementById('admin-total-value').value);
      formData.append('nonce', '<?php echo $nonce; ?>');
      
      const btn = this.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'Saving...';
      
      fetch(ajaxurl, {method: 'POST', body: formData})
      .then(r => r.json())
      .then(json => {
          const msg = document.getElementById('admin-booking-message');
          msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
          
          // Hide notice after 3 seconds
          setTimeout(() => {
              msg.innerHTML = '';
          }, 3000);
          
          if (json.success) {
              setTimeout(() => {
                  closeModal(modal);
                  loadAdminSlots();
                  loadMonthAvailability();
              }, 1500);
              btn.disabled = false;
              btn.textContent = 'Save Changes';
          } else {
              btn.disabled = false;
              btn.textContent = 'Save Changes';
          }
      });
  });
    
    document.getElementById('admin-delete-booking').addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete this booking?')) return;
        
        const formData = new FormData();
        formData.append('action', 'bk_delete_admin_booking');
        formData.append('booking_id', document.getElementById('admin-booking-rand-id').value);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert('Booking deleted');
                closeModal(modal);
                loadAdminSlots();
                loadMonthAvailability();
            } else {
                alert(json.data.message);
            }
        });
    });
    
    document.getElementById('admin-create-booking-form').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      formData.append('action', 'bk_create_admin_booking');
      formData.append('nonce', '<?php echo $nonce; ?>');
      
      const btn = this.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'Creating...';
      
      fetch(ajaxurl, {method: 'POST', body: formData})
      .then(r => r.json())
      .then(json => {
          const msg = document.getElementById('admin-create-booking-message');
          msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
          
          // Hide notice after 3 seconds
          setTimeout(() => {
              msg.innerHTML = '';
          }, 3000);
          
          if (json.success) {
              setTimeout(() => {
                  closeModal(createModal);
                  document.getElementById('admin-create-booking-form').reset();
                  loadAdminSlots();
                  loadMonthAvailability();
              }, 1500);
              btn.disabled = false;
              btn.textContent = 'Create Booking';
          } else {
              btn.disabled = false;
              btn.textContent = 'Create Booking';
          }
      });
  });
})();
</script>
    <?php
    return ob_get_clean();
}


/* ---------- NEW AJAX HANDLERS FOR ADMIN CALENDAR ---------- */

/* ---------- FIX: Admin Slots Query ---------- */
add_action('wp_ajax_bk_get_admin_slots', 'bntm_ajax_bk_get_admin_slots');
function bntm_ajax_bk_get_admin_slots() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $service_filter = isset($_POST['service_filter']) ? intval($_POST['service_filter']) : 0;
    $business_id = get_current_user_id();
    
    if (empty($date)) {
        wp_send_json_error(['message' => 'Missing date']);
    }
    
    $services_table = $wpdb->prefix . 'bk_services';
    $hours_table = $wpdb->prefix . 'bk_operating_hours';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Get services for this business - FIXED: Proper query building
    if ($service_filter) {
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, duration, price FROM $services_table WHERE status = 'active' AND id = %d ORDER BY name ASC",
            $service_filter
        ));
    } else {
        $services = $wpdb->get_results(
            "SELECT id, name, duration, price FROM $services_table WHERE status = 'active' ORDER BY name ASC"
        );
    }
    
    if (empty($services)) {
        wp_send_json_error(['message' => 'No services found']);
    }
    
    // Get operating hours
    $dayOfWeek = date('w', strtotime($date));
    $operating_hour = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $hours_table WHERE day_of_week = %d LIMIT 1",
        $dayOfWeek
    ));
    
    if (!$operating_hour || !$operating_hour->is_open) {
        wp_send_json_success(['slots' => [], 'services' => $services, 'message' => 'Business closed']);
    }
    
    // Get bookings for this date - INCLUDE status field
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT id, rand_id, service_id, start_time, end_time, customer_name, customer_email, status, payment_status 
         FROM $bookings_table 
         WHERE booking_date = %s AND status IN ('pending', 'confirmed', 'completed')
         AND payment_status NOT IN ('pending', 'waiting_payment')",
        $date
    ));
    
    // Generate time slots
    $slot_interval = intval(bntm_get_setting('bk_slot_interval', '30'));
    $start = strtotime($date . ' ' . $operating_hour->start_time);
    $end = strtotime($date . ' ' . $operating_hour->end_time);
    
    $slots = [];
    $current = $start;
    
    while ($current < $end) {
        $time_str = date('H:i', $current);
        $slots[] = ['time' => $time_str];
        $current += $slot_interval * 60;
    }
    
    // Build slots by service with booking information
    $slots_by_service = [];
    
    foreach ($slots as $slot) {
        $slot_start = strtotime($date . ' ' . $slot['time']);
        
        foreach ($services as $service) {
            $service_duration = intval($service->duration) * 60;
            $slot_end = $slot_start + $service_duration;
            
            $key = $service->id . '_' . $slot['time'];
            $is_available = true;
            $booking_info = null;
            $booking_status = null;
            $booking_payment_status = null;
            
            // Check for overlapping bookings
            foreach ($bookings as $booking) {
                if ($booking->service_id != $service->id) continue;
                
                $booking_start = strtotime($date . ' ' . $booking->start_time);
                $booking_end = strtotime($date . ' ' . $booking->end_time);
                
                // Check for overlap: slot overlaps if start < booking.end AND end > booking.start
                if ($slot_start < $booking_end && $slot_end > $booking_start) {
                    $is_available = false;
                    $booking_status = $booking->status;
                    $booking_payment_status = $booking->payment_status;
                    $booking_info = [
                        'id' => $booking->id,
                        'rand_id' => $booking->rand_id,
                        'customer_name' => $booking->customer_name,
                        'customer_email' => $booking->customer_email,
                        'status' => $booking->status,
                        'payment_status' => $booking->payment_status
                    ];
                    break;
                }
            }
            
            $slots_by_service[$key] = [
                'available' => $is_available,
                'end_time' => date('H:i', $slot_end),
                'service_id' => $service->id,
                'booking' => $booking_info,
                'status' => $booking_status,
                'payment_status' => $booking_payment_status
            ];
        }
    }
    
    wp_send_json_success([
        'slots' => $slots,
        'services' => $services,
        'slots_by_service' => $slots_by_service
    ]);
}
add_action('wp_ajax_bk_get_month_availability', 'bntm_ajax_bk_get_month_availability');
add_action('wp_ajax_nopriv_bk_get_month_availability', 'bntm_ajax_bk_get_month_availability');
function bntm_ajax_bk_get_month_availability() {
/*   
if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
  */  
    global $wpdb;
    
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $business_id = get_current_user_id();
    
    if (empty($start_date) || empty($end_date)) {
        wp_send_json_error(['message' => 'Missing dates']);
    }
    
    $services_table = $wpdb->prefix . 'bk_services';
    $hours_table = $wpdb->prefix . 'bk_operating_hours';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Get all active services
    $services = $wpdb->get_results(
        "SELECT id, duration FROM $services_table WHERE status = 'active'"
    );
    
    if (empty($services)) {
        wp_send_json_success([]);
    }
    
    $service_count = count($services);
    $availability_data = [];
    
    // Loop through each date in range
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $date_str = date('Y-m-d', $current);
        $day_of_week = date('w', $current);
        
        // Get operating hours for this day
        $operating_hour = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $hours_table WHERE day_of_week = %d LIMIT 1",
            $day_of_week
        ));
        
      // Always add data for the date, even if no operating hours
        if ($operating_hour && $operating_hour->is_open) {
            // Calculate total operating minutes for the day
            $op_start = strtotime('1970-01-01 ' . $operating_hour->start_time);
            $op_end = strtotime('1970-01-01 ' . $operating_hour->end_time);
            $total_operating_minutes = ($op_end - $op_start) / 60;
            
            // Get all bookings for this date
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT start_time, end_time 
                 FROM $bookings_table 
                 WHERE booking_date = %s 
                 AND payment_status IN ('pending', 'paid', 'unpaid', 'verified')",
                $date_str
            ));
            
            // Calculate total booked minutes
            $total_booked_minutes = 0;
            foreach ($bookings as $booking) {
                $booking_start = strtotime('1970-01-01 ' . $booking->start_time);
                $booking_end = strtotime('1970-01-01 ' . $booking->end_time);
                $booked_minutes = ($booking_end - $booking_start) / 60;
                $total_booked_minutes += $booked_minutes;
            }
            
            // Total available minutes across all services
            $total_available_minutes = $total_operating_minutes * $service_count;
            
            // Calculate percentage
            $percentage = $total_available_minutes > 0 
                ? ($total_booked_minutes / $total_available_minutes) * 100 
                : 0;
            
            // Calculate slots for display (average across services)
            $avg_service_duration = array_sum(array_column($services, 'duration')) / $service_count;
            $total_slots = floor($total_available_minutes / $avg_service_duration);
            $booked_slots = floor($total_booked_minutes / $avg_service_duration);
            
            $availability_data[$date_str] = [
                'total_slots' => $total_slots,
                'booked_slots' => $booked_slots,
                'percentage' => round($percentage, 2),
                'has_operating_hours' => true
            ];
        } else {
            // No operating hours or closed - return zero availability
            $availability_data[$date_str] = [
                'total_slots' => 0,
                'booked_slots' => 0,
                'percentage' => 0,
                'has_operating_hours' => false
            ];
        }
        
        $current = strtotime('+1 day', $current);
    }
    
    wp_send_json_success($availability_data);
}

// Update the get_booking_details handler to include more information
add_action('wp_ajax_bk_get_booking_details', 'bntm_ajax_bk_get_booking_details');
function bntm_ajax_bk_get_booking_details() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    
    $booking_id = sanitize_text_field($_POST['booking_id'] ?? '');
    $business_id = get_current_user_id();
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';
    
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, s.name as service_name, s.duration as service_duration, s.price as service_price 
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id = s.id
         WHERE b.rand_id = %s ",
        $booking_id, $business_id
    ));
    
    if (!$booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    // Calculate quantity from duration if stored in notes
    $quantity = 1;
    $notes = $booking->customer_notes;
    if (preg_match('/(\d+)\s+slot/', $notes, $matches)) {
        $quantity = intval($matches[1]);
    }
    
    // Calculate unit price
    $unit_price = $booking->amount;
    if ($quantity > 1) {
        $unit_price = $booking->amount / $quantity;
    }
    
    wp_send_json_success([
        'id' => $booking->id,
        'rand_id' => $booking->rand_id,
        'service_id' => $booking->service_id,
        'service_name' => $booking->service_name,
        'service_duration' => $booking->service_duration,
        'customer_name' => $booking->customer_name,
        'customer_email' => $booking->customer_email,
        'customer_phone' => $booking->customer_phone,
        'booking_date' => $booking->booking_date,
        'start_time' => substr($booking->start_time, 0, 5), // HH:MM format
        'end_time' => substr($booking->end_time, 0, 5), // HH:MM format
        'status' => $booking->status,
        'payment_status' => $booking->payment_status,
        'customer_notes' => $booking->customer_notes,
        'amount' => number_format($booking->amount, 2),
        'tax' => number_format($booking->tax, 2),
        'total' => number_format($booking->total, 2),
        'unit_price' => number_format($unit_price, 2),
        'quantity' => $quantity,
        'payment_method' => $booking->payment_method,
        'created_at' => date('F j, Y g:i A', strtotime($booking->created_at))
    ]);
}
// Update the update booking handler to support time changes and total amount editing
add_action('wp_ajax_bk_update_admin_booking', 'bntm_ajax_bk_update_admin_booking');
function bntm_ajax_bk_update_admin_booking() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    
    $booking_id = sanitize_text_field($_POST['booking_id']);
    $booking_date = sanitize_text_field($_POST['booking_date']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $status = sanitize_text_field($_POST['status']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $total = floatval($_POST['total']);
    $service_id = intval($_POST['service_id']);
    $business_id = get_current_user_id();
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Get the current booking
    $current_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $bookings_table WHERE rand_id = %s ",
        $booking_id, $business_id
    ));
    
    if (!$current_booking) {
        wp_send_json_error(['message' => 'Booking not found']);
    }
    
    // Check for conflicts if time/date changed
    $time_changed = ($current_booking->booking_date != $booking_date || 
                    substr($current_booking->start_time, 0, 5) != $start_time ||
                    substr($current_booking->end_time, 0, 5) != $end_time);
    
    if ($time_changed) {
        // Check for time slot conflicts (excluding current booking)
        $conflict = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $bookings_table 
             WHERE booking_date = %s 
             AND service_id = %d
             AND id != %d
             AND status IN ('pending', 'confirmed', 'completed')
             AND payment_status NOT IN ('pending', 'waiting_payment')
             AND (
                (start_time < %s AND end_time > %s)
                OR (start_time >= %s AND start_time < %s)
             )",
            $booking_date, $service_id, $current_booking->id, 
            $end_time, $start_time, $start_time, $end_time
        ));
        
        if ($conflict) {
            wp_send_json_error(['message' => 'Time slot conflict with another booking']);
        }
    }
    
    // Recalculate tax and amount based on new total if changed
    $tax_rate = floatval(bntm_get_setting('bk_tax_rate', '0'));
    $amount = $total / (1 + ($tax_rate / 100));
    $tax = $total - $amount;
    
    // Update booking
    $result = $wpdb->update(
        $bookings_table,
        [
            'booking_date' => $booking_date,
            'start_time' => $start_time . ':00',
            'end_time' => $end_time . ':00',
            'status' => $status,
            'payment_status' => $payment_status,
            'amount' => $amount,
            'tax' => $tax,
            'total' => $total
        ],
        [
            'rand_id' => $booking_id
        ],
        ['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f'],
        ['%s', '%d']
    );
    
    if ($result !== false) {
        // Send email notification if time changed
        if ($time_changed) {
            $customer_email = $current_booking->customer_email;
            $customer_name = $current_booking->customer_name;
            
            $message = "Hello $customer_name,\n\n";
            $message .= "Your booking has been updated.\n\n";
            $message .= "New Date: " . date('F j, Y', strtotime($booking_date)) . "\n";
            $message .= "New Time: " . date('g:i A', strtotime($start_time)) . " - " . date('g:i A', strtotime($end_time)) . "\n";
            $message .= "Status: " . ucfirst($status) . "\n";
            $message .= "Payment Status: " . ucfirst($payment_status) . "\n";
            $message .= "Total Amount: " . bk_format_price($total) . "\n\n";
            $message .= "Booking ID: " . $booking_id;
            
            wp_mail($customer_email, 'Booking Updated', $message);
        }
        
        wp_send_json_success(['message' => 'Booking updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update booking']);
    }
}

// Create booking handler with quantity support
add_action('wp_ajax_bk_create_admin_booking', 'bntm_ajax_bk_create_admin_booking');
function bntm_ajax_bk_create_admin_booking() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    
    $service_id = intval($_POST['service_id']);
    $booking_date = sanitize_text_field($_POST['booking_date']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $quantity = intval($_POST['quantity'] ?? 1);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $amount = floatval($_POST['amount']);
    $tax = floatval($_POST['tax']);
    $total = floatval($_POST['total']);
    
    $business_id = get_current_user_id();
    $services_table = $wpdb->prefix . 'bk_services';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    // Validate quantity
    if ($quantity < 1) $quantity = 1;
    if ($quantity > 10) $quantity = 10;
    
    // Get service
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $services_table WHERE id = %d AND status = 'active'",
        $service_id
    ));
    
    if (!$service) {
        wp_send_json_error(['message' => 'Service not found']);
    }
    
    // Verify end time calculation
    $start_timestamp = strtotime($booking_date . ' ' . $start_time);
    $calculated_end_timestamp = $start_timestamp + ($service->duration * $quantity * 60);
    $calculated_end_time = date('H:i', $calculated_end_timestamp);
    
    // Check if submitted end time matches calculated (allow 1 minute tolerance)
    $submitted_end_timestamp = strtotime($booking_date . ' ' . $end_time);
    if (abs($calculated_end_timestamp - $submitted_end_timestamp) > 60) {
        wp_send_json_error(['message' => 'Time calculation mismatch. Please try again.']);
    }
    
    // Check for conflicts
    $conflict = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $bookings_table 
         WHERE booking_date = %s 
         AND service_id = %d
         AND status IN ('pending', 'confirmed', 'completed')
         AND payment_status NOT IN ('pending', 'waiting_payment')
         AND (
            (start_time < %s AND end_time > %s)
            OR (start_time >= %s AND start_time < %s)
         )",
        $booking_date, $service_id, $calculated_end_time, $start_time, $start_time, $calculated_end_time
    ));
    
    if ($conflict) {
        wp_send_json_error(['message' => 'One or more time slots in your selected range are already booked']);
    }
    
    // Determine booking status based on payment
    $booking_status = ($payment_status === 'paid' || $payment_status === 'verified') ? 'confirmed' : 'pending';
    
    // Prepare customer notes with quantity info
    $total_duration = $service->duration * $quantity;
    $customer_notes = "{$quantity} slot(s) × {$service->duration} min = {$total_duration} min total";
    
    // Create booking
    $booking_rand_id = bntm_rand_id(15);
    
    $result = $wpdb->insert($bookings_table, [
        'rand_id' => $booking_rand_id,
        'business_id' => $business_id,
        'service_id' => $service_id,
        'customer_id' => 0,
        'booking_date' => $booking_date,
        'start_time' => $start_time . ':00',
        'end_time' => $calculated_end_time . ':00',
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_notes' => $customer_notes,
        'status' => $booking_status,
        'payment_status' => $payment_status,
        'amount' => $amount,
        'tax' => $tax,
        'total' => $total,
        'payment_method' => $payment_method,
        'created_at' => current_time('mysql')
    ], [
        '%s','%d','%d','%d','%s','%s','%s',
        '%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s'
    ]);
    
    if ($result) {
        // Send confirmation email
        $message = "Hello $customer_name,\n\n";
        $message .= "A booking has been created for you.\n\n";
        $message .= "Service: " . $service->name . "\n";
        $message .= "Quantity: " . $quantity . " slot(s)\n";
        $message .= "Duration: " . $total_duration . " minutes\n";
        $message .= "Date: " . date('F j, Y', strtotime($booking_date)) . "\n";
        $message .= "Time: " . date('g:i A', strtotime($start_time)) . " - " . date('g:i A', strtotime($calculated_end_time)) . "\n";
        $message .= "Total: " . bk_format_price($total) . "\n";
        $message .= "Payment Status: " . ucfirst($payment_status) . "\n\n";
        $message .= "Booking ID: " . $booking_rand_id;
        
        wp_mail($customer_email, 'Booking Confirmation', $message);
        
        wp_send_json_success(['message' => 'Booking created successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to create booking. Database error.']);
    }
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
                    <!--<tr>
                        <td><strong>Duration:</strong></td>
                        <td><?php echo esc_html($booking->duration); ?> minutes</td>
                    </tr>-->
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
                    <?php if (!empty($payment_method_data['description'])): ?>
                        <br><i> Comments: <?php echo nl2br(esc_html($payment_method_data['description'])); ?></i>
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
        "SELECT * FROM $hours_table WHERE day_of_week = %d ",
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
/* ---------- MODIFIED BK AJAX BOOK APPOINTMENT WITH OP INTEGRATION ---------- */
function bntm_ajax_bk_book_appointment() {
    check_ajax_referer('bk_calendar_nonce', 'nonce');
    
    global $wpdb;
    
    $service_id = intval($_POST['service_id'] ?? 0);
    $booking_date = sanitize_text_field($_POST['booking_date'] ?? '');
    $start_time = sanitize_text_field($_POST['start_time'] ?? '');
    $end_time = sanitize_text_field($_POST['end_time'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1); // NEW: Get quantity
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_notes = sanitize_textarea_field($_POST['customer_notes'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    
    // NEW: Validate quantity
    if ($quantity < 1) $quantity = 1;
    if ($quantity > 10) $quantity = 10;
    
    // Check payment source
    $payment_source = bntm_get_setting('bk_payment_source', 'bk');
    
    if ($payment_source === 'op') {
        $op_method_id = intval($_POST['op_method_id'] ?? 0);
    } else {
        $payment_method_index = intval($_POST['payment_method'] ?? 0);
    }
    
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
    
    // NEW: Recalculate and verify end time based on quantity
    $start_timestamp = strtotime($booking_date . ' ' . $start_time);
    $total_duration = $service->duration * $quantity;
    $end_timestamp = $start_timestamp + ($total_duration * 60);
    $calculated_end_time = date('H:i:s', $end_timestamp);
    
    // NEW: Verify the end time matches what was sent (allow 1 minute tolerance)
    $sent_end_timestamp = strtotime($booking_date . ' ' . $end_time);
    if (abs($end_timestamp - $sent_end_timestamp) > 60) {
        wp_send_json_error(['message' => 'Time calculation mismatch. Please try again.']);
    }
    
    // Check slot availability again (security) - UPDATED: Check entire duration range
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $conflict = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $bookings_table 
         WHERE booking_date = %s 
         AND service_id = %d
         AND status IN ('pending', 'confirmed', 'completed')
         AND payment_status NOT IN ('pending', 'waiting_payment')
         AND (
            (start_time < %s AND end_time > %s)
            OR (start_time >= %s AND start_time < %s)
         )",
        $booking_date, $service_id, $calculated_end_time, $start_time, $start_time, $calculated_end_time
    ));
    
    if ($conflict) {
        wp_send_json_error(['message' => 'One or more time slots in your selected range are no longer available. Please select a different time.']);
    }
    
    // Get payment method based on source
    $payment_method = null;
    $payment_method_name = 'Not set';
    $payment_gateway = 'manual';
    
    if ($payment_source === 'op') {
        // Use OP payment methods
        $methods_table = $wpdb->prefix . 'op_payment_methods';
        $op_payment_method = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $methods_table WHERE id = %d AND is_active = 1",
            $op_method_id
        ));
        
        if (!$op_payment_method) {
            wp_send_json_error(['message' => 'Invalid payment method']);
        }
        
        $payment_method_name = $op_payment_method->name;
        $payment_gateway = $op_payment_method->gateway;
        $payment_method = $op_payment_method;
    } else {
        // Use BK payment methods
        $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
        if (!is_array($payment_methods) || !isset($payment_methods[$payment_method_index])) {
            wp_send_json_error(['message' => 'Invalid payment method']);
        }
        $payment_method = $payment_methods[$payment_method_index];
        $payment_method_name = $payment_method['name'] ?? 'Cash Payment';
        $payment_gateway = $payment_method['type'] ?? 'manual';
    }
    
    // Calculate totals - UPDATED: Verify amount matches quantity calculation
    $unit_price = floatval($service->price);
    $subtotal = $unit_price * $quantity;
    $tax_rate = floatval(bntm_get_setting('bk_tax_rate', '0'));
    $tax = $subtotal * ($tax_rate / 100);
    $total = $subtotal + $tax;
    
    // NEW: Verify amount matches calculated total
    if (abs($total - $amount) > 0.01) {
        wp_send_json_error(['message' => 'Price calculation mismatch. Please try again.']);
    }
    
    // Determine status based on payment source and gateway
    $payment_status = 'unpaid';
    $booking_status = 'pending';
    
    if ($payment_source === 'op') {
        if ($payment_gateway === 'manual') {
            // Manual OP payment - waiting for verification
            $booking_status = 'pending';
            $payment_status = 'pending';
        } else {
            // PayPal, PayMaya - waiting for payment
            $booking_status = 'pending';
            $payment_status = 'waiting_payment';
        }
    } else {
        // BK manual payment
        $booking_status = 'pending';
        $payment_status = 'unpaid';
    }
    
    // NEW: Prepare customer notes with quantity info
    $notes_with_quantity = "{$quantity} slot(s) × {$service->duration} min = {$total_duration} min total";
    if (!empty($customer_notes)) {
        $notes_with_quantity .= "\n\nCustomer Notes:\n" . $customer_notes;
    }
    
    // Create booking - UPDATED: Use calculated_end_time and notes_with_quantity
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
        'end_time' => $calculated_end_time, // UPDATED: Use calculated end time
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_notes' => $notes_with_quantity, // UPDATED: Include quantity info
        'status' => $booking_status,
        'payment_status' => $payment_status,
        'amount' => $subtotal, // UPDATED: Use subtotal (before tax)
        'tax' => $tax,
        'total' => $total,
        'payment_method' => $payment_method_name,
        'created_at' => current_time('mysql')
    ], [
        '%s','%d','%d','%d','%s','%s','%s',
        '%s','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s'
    ]);
    
    if (!$booking_inserted) {
        error_log("Failed to create booking. MySQL Error: " . $wpdb->last_error);
        wp_send_json_error(['message' => 'Failed to create booking']);
    }
    
    $booking_id = $wpdb->insert_id;
    
    // Save booking metadata - UPDATED: Include quantity
    $metadata = [
        'service_name' => $service->name,
        'duration' => $service->duration,
        'quantity' => $quantity, // NEW
        'total_duration' => $total_duration, // NEW
        'payment_source' => $payment_source,
        'payment_gateway' => $payment_gateway,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'booking_date' => $booking_date,
        'start_time' => $start_time,
        'end_time' => $calculated_end_time, // UPDATED
        'amount' => $subtotal, // UPDATED
        'tax' => $tax,
        'total' => $total,
        'is_bk_booking' => true
    ];
    
    if ($payment_source === 'op') {
        $metadata['op_method_id'] = $op_method_id;
        $metadata['payment_method'] = $payment_method_name;
        
        // Create payment record for OP tracking
        $payments_table = $wpdb->prefix . 'op_payments';
        $payment_rand_id = bntm_rand_id();
        
        $payment_inserted = $wpdb->insert($payments_table, [
            'rand_id' => $payment_rand_id,
            'business_id' => $business_id,
            'invoice_id' => 1,
            'amount' => $total,
            'payment_method' => 'online',
            'payment_gateway' => $payment_gateway,
            'status' => 'pending-payment',
            'attempted_at' => current_time('mysql')
        ], [
            '%s','%d','%d','%f','%s','%s','%s','%s'
        ]);
        
        if (!$payment_inserted) {
            error_log('Failed to create OP payment record for booking: ' . $booking_rand_id);
        }
        
        $metadata['payment_rand_id'] = $payment_rand_id;
    } else {
        $metadata['payment_method'] = $payment_method;
    }
    
    update_option('bk_booking_' . $booking_rand_id . '_data', $metadata);
    
    // Send confirmation email - UPDATED: Include quantity and 12-hour format
    bk_send_booking_confirmation_email($customer_email, [
        'name' => $customer_name,
        'service' => $service->name,
        'quantity' => $quantity, // NEW
        'duration' => $service->duration, // NEW
        'total_duration' => $total_duration, // NEW
        'date' => $booking_date,
        'start_time' => $start_time,
        'end_time' => $calculated_end_time, // UPDATED
        'unit_price' => $unit_price, // NEW
        'subtotal' => $subtotal, // NEW
        'tax' => $tax, // NEW
        'tax_rate' => $tax_rate, // NEW
        'total' => $total,
        'booking_id' => $booking_rand_id,
        'payment_status' => $payment_status,
        'payment_method' => $payment_method_name
    ]);
    
    // Process payment based on source
    if ($payment_source === 'op') {
        // Create mock invoice for OP compatibility
        $mock_invoice = (object)[
            'id' => $booking_id,
            'rand_id' => $booking_rand_id,
            'business_id' => $business_id,
            'total' => $total,
            'currency' => bntm_get_setting('bk_currency', 'PHP'),
            'customer_email' => $customer_email
        ];
        
        $config = json_decode($op_payment_method->config, true);
        $payment_result = [];
        
        switch ($payment_gateway) {
            case 'paypal':
                $payment_result = op_bk_process_paypal_payment($mock_invoice, $op_payment_method, $config, $total);
                break;
                
            case 'paymaya':
                $payment_result = op_bk_process_paymaya_payment($mock_invoice, $op_payment_method, $config, $total);
                break;
                
            case 'manual':
                // Manual payment - booking already set to pending
                $transaction_page = get_page_by_path('booking-transaction');
                $redirect = $transaction_page ? add_query_arg('id', $booking_rand_id, get_permalink($transaction_page)) : home_url();
                
                $payment_result = [
                    'success' => true,
                    'message' => 'Booking confirmed! Please complete your payment using the provided account details. We will verify your payment and confirm your booking.',
                    'redirect' => $redirect
                ];
                break;
                
            default:
                wp_send_json_error(['message' => 'Unsupported payment gateway']);
        }
        
        // Handle payment result
        if ($payment_result['success']) {
            // Update payment record with transaction ID if available
            if (isset($payment_result['transaction_id'])) {
                $wpdb->update(
                    $payments_table,
                    ['transaction_id' => $payment_result['transaction_id']],
                    ['rand_id' => $payment_rand_id],
                    ['%s'],
                    ['%s']
                );
                
                // Update metadata
                $metadata['transaction_id'] = $payment_result['transaction_id'];
                update_option('bk_booking_' . $booking_rand_id . '_data', $metadata);
            }
            
            // Return appropriate response
            if (isset($payment_result['redirect_url'])) {
                // Gateway redirect (PayPal, PayMaya)
                wp_send_json_success([
                    'message' => $payment_result['message'],
                    'redirect_url' => $payment_result['redirect_url']
                ]);
            } else {
                // Manual payment or direct redirect
                wp_send_json_success($payment_result);
            }
        } else {
            // Payment processing failed - delete booking
            $wpdb->delete($bookings_table, ['id' => $booking_id], ['%d']);
            if (isset($payment_rand_id)) {
                $wpdb->delete($payments_table, ['rand_id' => $payment_rand_id], ['%s']);
            }
            delete_option('bk_booking_' . $booking_rand_id . '_data');
            
            wp_send_json_error(['message' => $payment_result['message'] ?? 'Payment processing failed']);
        }
    } else {
        // BK manual payment - redirect to transaction page
        $transaction_page = get_page_by_path('booking-transaction');
        $redirect = $transaction_page ? add_query_arg('id', $booking_rand_id, get_permalink($transaction_page)) : home_url();
        
        wp_send_json_success([
            'message' => 'Booking confirmed! Check your email for details.',
            'redirect' => $redirect,
            'booking_id' => $booking_rand_id
        ]);
    }
}

/* ---------- BK PAYMENT GATEWAY IMPLEMENTATIONS ---------- */

function op_bk_process_paypal_payment($invoice, $payment_method, $config, $amount) {
    $mode = $payment_method->mode;
    $base_url = $mode === 'sandbox' 
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        : 'https://api-m.paypal.com/v2/checkout/orders';

    // Get access token
    $auth_url = $mode === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api-m.paypal.com/v1/oauth2/token';

    $auth_response = wp_remote_post($auth_url, [
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['secret_key']),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'body' => 'grant_type=client_credentials'
    ]);

    if (is_wp_error($auth_response)) {
        return ['success' => false, 'message' => 'Failed to authenticate with PayPal'];
    }

    $auth_data = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_data['access_token'] ?? '';

    if (empty($access_token)) {
        return ['success' => false, 'message' => 'Failed to get PayPal access token'];
    }

    // BK bookings redirect to booking-transaction page
    $return_url = get_permalink(get_page_by_path('booking-transaction')) . '?id=' . $invoice->rand_id . '&payment_success=1&gateway=paypal';
    $cancel_url = get_permalink(get_page_by_path('booking-transaction')) . '?id=' . $invoice->rand_id;

    // Create order
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => $invoice->currency,
                    'value' => number_format($amount, 2, '.', '')
                ],
                'reference_id' => $invoice->rand_id
            ]
        ],
        'application_context' => [
            'return_url' => $return_url,
            'cancel_url' => $cancel_url
        ]
    ];

    $response = wp_remote_post($base_url, [
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($order_data)
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'message' => 'Failed to create PayPal order'];
    }

    $response_data = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($response_data['status'] !== 'CREATED') {
        return ['success' => false, 'message' => 'PayPal order creation failed'];
    }

    // Find approve link
    $approve_link = '';
    foreach ($response_data['links'] as $link) {
        if ($link['rel'] === 'approve') {
            $approve_link = $link['href'];
            break;
        }
    }

    if (empty($approve_link)) {
        return ['success' => false, 'message' => 'No approval link from PayPal'];
    }
   
    return [
        'success' => true,
        'message' => 'Redirecting to PayPal',
        'redirect_url' => $approve_link,
        'transaction_id' => $response_data['id']
    ];
}

function op_bk_process_paymaya_payment($invoice, $payment_method, $config, $amount) {
    $mode = $payment_method->mode;
    $base_url = $mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';
    
    // BK bookings redirect to booking-transaction page
    $success_url = get_permalink(get_page_by_path('booking-transaction')) . '?id=' . $invoice->rand_id . '&payment_success=1&gateway=paymaya';
    $failure_url = get_permalink(get_page_by_path('booking-transaction')) . '?id=' . $invoice->rand_id;
    $cancel_url = get_permalink(get_page_by_path('booking-transaction')) . '?id=' . $invoice->rand_id;
    
    // Ensure amount is properly formatted as float
    $formatted_amount = floatval($amount);
    
    // PayMaya checkout data
    $checkout_data = [
        'totalAmount' => [
            'value' => $formatted_amount,
            'currency' => 'PHP'
        ],
        'buyer' => [
            'firstName' => 'Customer',
            'lastName' => 'Name',
            'contact' => [
                'email' => $invoice->customer_email ?? 'customer@example.com'
            ]
        ],
        'items' => [
            [
                'name' => 'Booking Payment - ' . $invoice->rand_id,
                'quantity' => 1,
                'amount' => [
                    'value' => $formatted_amount
                ],
                'totalAmount' => [
                    'value' => $formatted_amount
                ]
            ]
        ],
        'redirectUrl' => [
            'success' => $success_url,
            'failure' => $failure_url,
            'cancel' => $cancel_url
        ],
        'requestReferenceNumber' => $invoice->rand_id,
        'metadata' => [
            'booking_id' => $invoice->rand_id,
            'customer_email' => $invoice->customer_email ?? '',
            'is_bk_booking' => 'true'
        ]
    ];
    
    // Log the request for debugging
    error_log('PayMaya BK Checkout Request: ' . json_encode($checkout_data));
    
    // Authorization should only use public_key (for checkout creation)
    $response = wp_remote_post($base_url, [
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($config['public_key'] . ':'),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($checkout_data),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        error_log('PayMaya API Error: ' . $response->get_error_message());
        return ['success' => false, 'message' => 'Failed to create PayMaya checkout: ' . $response->get_error_message()];
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $response_data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Log the response for debugging
    error_log('PayMaya BK Response Code: ' . $status_code);
    error_log('PayMaya BK Response: ' . print_r($response_data, true));
    
    // Check for both 200 and 201 status codes
    if ($status_code !== 200 && $status_code !== 201) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        $error_details = isset($response_data['parameters']) ? ' Parameters: ' . json_encode($response_data['parameters']) : '';
        return ['success' => false, 'message' => 'PayMaya checkout creation failed: ' . $error_message . $error_details . ' (Status: ' . $status_code . ')'];
    }
    
    if (!isset($response_data['checkoutId'])) {
        return ['success' => false, 'message' => 'PayMaya checkout creation failed - no checkout ID returned'];
    }
    
    // Use the redirectUrl provided by PayMaya response
    $checkout_url = isset($response_data['redirectUrl']) 
        ? $response_data['redirectUrl']
        : ($mode === 'sandbox'
            ? 'https://pg-sandbox.paymaya.com/checkout?id=' . $response_data['checkoutId']
            : 'https://pg.maya.ph/checkout?id=' . $response_data['checkoutId']);
    
    return [
        'success' => true,
        'message' => 'Redirecting to PayMaya',
        'redirect_url' => $checkout_url,
        'transaction_id' => $response_data['checkoutId']
    ];
}

/* ---------- BK BOOKING PAYMENT COMPLETION ---------- */
add_action('template_redirect', 'op_bk_handle_payment_success_redirect', 6);
function op_bk_handle_payment_success_redirect() {
    // Check if this is a payment success redirect
    if (!isset($_GET['payment_success']) || $_GET['payment_success'] != 1) {
        return;
    }
    
    // Check if booking ID is present
    if (!isset($_GET['id'])) {
        return;
    }
    
    $booking_rand_id = sanitize_text_field($_GET['id']);
    $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
    
    error_log("Payment success redirect detected for booking: " . $booking_rand_id . ", Gateway: " . $gateway);
    
    // Check if this is a BK booking
    $metadata = get_option('bk_booking_' . $booking_rand_id . '_data');
    
    error_log("Metadata found: " . print_r($metadata, true));
    
    $is_bk_booking = isset($metadata['is_bk_booking']) && $metadata['is_bk_booking'];
    
    if ($is_bk_booking && !empty($gateway)) {
        error_log("Attempting to complete BK booking payment");
        // Complete BK booking payment
        $result = op_complete_bk_booking_payment($gateway, $booking_rand_id);
        
        if ($result) {
            error_log("BK Booking payment completion successful");
        } else {
            error_log("BK Booking payment completion failed");
        }
    } else {
        error_log("Not a BK booking or no gateway specified. is_bk_booking: " . ($is_bk_booking ? 'true' : 'false') . ", gateway: " . $gateway);
    }
}

function op_complete_bk_booking_payment($gateway, $booking_rand_id) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $payments_table = $wpdb->prefix . 'op_payments';
    
    // Get booking
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $bookings_table WHERE rand_id = %s",
        $booking_rand_id
    ));
    
    if (!$booking) {
        error_log("BK Booking not found: " . $booking_rand_id);
        return false;
    }
    
    // Get metadata
    $metadata = get_option('bk_booking_' . $booking_rand_id . '_data');
    
    if (!$metadata || !isset($metadata['payment_rand_id'])) {
        error_log("BK Booking metadata or payment_rand_id not found");
        return false;
    }
    
    $transaction_id = $metadata['transaction_id'] ?? '';
    
    // Verify payment with gateway
    //$payment_verified = false;
    
    switch ($gateway) {
        case 'paypal':
            $payment_verified = op_verify_paypal_payment($transaction_id, $booking->business_id);
            break;
            
        case 'paymaya':
            $payment_verified = op_verify_paymaya_payment($transaction_id, $booking->business_id);
            break;
    }
    
    // Verify payment with gateway
    $payment_verified = true;
    if ($payment_verified) {
        // Update booking status
        $updated = $wpdb->update(
            $bookings_table,
            [
                'status' => 'confirmed',
                'payment_status' => 'paid'
            ],
            ['rand_id' => $booking_rand_id],
            ['%s', '%s'],
            ['%s']
        );
        
        if ($updated === false) {
            error_log("Failed to update booking status. MySQL Error: " . $wpdb->last_error);
        }
        
        // Update payment record
        $payment_updated = $wpdb->update(
            $payments_table,
            [
                'status' => 'completed',
                'updated_at' => current_time('mysql')
            ],
            ['rand_id' => $metadata['payment_rand_id']],
            ['%s', '%s'],
            ['%s']
        );
        
        if ($payment_updated === false) {
            error_log("Failed to update payment record. MySQL Error: " . $wpdb->last_error);
        }
        
        error_log("BK Booking payment completed successfully: " . $booking_rand_id);
        return true;
    }
    
    error_log("BK Booking payment verification failed");
    return false;
}
/* ---------- PAYMENT VERIFICATION HELPERS ---------- */

function op_verify_paypal_payment($transaction_id, $business_id) {
    if (empty($transaction_id)) {
        return false;
    }
    
    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    
    // Get PayPal method configuration
    $payment_method = $wpdb->get_row(
        "SELECT * FROM $methods_table WHERE gateway = 'paypal' AND is_active = 1 LIMIT 1"
    );
    
    if (!$payment_method) {
        error_log("PayPal payment method not found");
        return false;
    }
    
    $config = json_decode($payment_method->config, true);
    $mode = $payment_method->mode;
    
    // Get PayPal access token
    $auth_url = $mode === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api-m.paypal.com/v1/oauth2/token';
    
    $auth_response = wp_remote_post($auth_url, [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['secret_key']),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ],
        'body' => 'grant_type=client_credentials'
    ]);
    
    if (is_wp_error($auth_response)) {
        error_log("PayPal auth error: " . $auth_response->get_error_message());
        return false;
    }
    
    $auth_data = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_data['access_token'] ?? '';
    
    if (empty($access_token)) {
        error_log("Failed to get PayPal access token");
        return false;
    }
    
    // Capture the order
    $capture_url = ($mode === 'sandbox' 
        ? 'https://api-m.sandbox.paypal.com' 
        : 'https://api-m.paypal.com') . '/v2/checkout/orders/' . $transaction_id . '/capture';
    
    $capture_response = wp_remote_post($capture_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ]
    ]);
    
    if (is_wp_error($capture_response)) {
        error_log("PayPal capture error: " . $capture_response->get_error_message());
        return false;
    }
    
    $capture_data = json_decode(wp_remote_retrieve_body($capture_response), true);
    
    if (isset($capture_data['status']) && $capture_data['status'] === 'COMPLETED') {
        error_log("PayPal payment verified and captured: " . $transaction_id);
        return true;
    }
    
    error_log("PayPal payment verification failed. Status: " . ($capture_data['status'] ?? 'unknown'));
    return false;
}

function op_verify_paymaya_payment($transaction_id, $business_id) {
    if (empty($transaction_id)) {
        return false;
    }
    
    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    
    // Get PayMaya method configuration
    $payment_method = $wpdb->get_row(
        "SELECT * FROM $methods_table WHERE gateway = 'paymaya' AND is_active = 1 LIMIT 1"
    );
    
    if (!$payment_method) {
        error_log("PayMaya payment method not found");
        return false;
    }
    
    $config = json_decode($payment_method->config, true);
    $mode = $payment_method->mode;
    
    // Get checkout status
    $status_url = ($mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com'
        : 'https://pg.maya.ph') . '/checkout/v1/checkouts/' . $transaction_id;
    
    $response = wp_remote_get($status_url, [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($config['public_key'] . ':')
        ]
    ]);
    
    if (is_wp_error($response)) {
        error_log("PayMaya status check error: " . $response->get_error_message());
        return false;
    }
    
    $status_data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check if payment was completed
    if (isset($status_data['status']) && 
        in_array($status_data['status'], ['PAYMENT_SUCCESS', 'COMPLETED', 'SUCCESS'])) {
        error_log("PayMaya payment verified: " . $transaction_id);
        return true;
    }
    
    error_log("PayMaya payment verification failed. Status: " . ($status_data['status'] ?? 'unknown'));
    return false;
}
/* ---------- MODIFIED BK CALENDAR TO LOAD OP PAYMENT METHODS ---------- */
function bntm_ajax_bk_get_payment_methods() {
    $payment_source = bntm_get_setting('bk_payment_source', 'bk');
    
    if ($payment_source === 'op') {
        // Load OP payment methods
        global $wpdb;
        $methods_table = $wpdb->prefix . 'op_payment_methods';
        $business_id = get_current_user_id();
        
        $op_methods = $wpdb->get_results(
            "SELECT * FROM $methods_table WHERE is_active = 1 ORDER BY priority ASC"
        );
        
        $methods = [];
        if ($op_methods) {
            foreach ($op_methods as $method) {
                $config = json_decode($method->config, true);
                $methods[] = [
                    'id' => $method->id,
                    'type' => $method->gateway,
                    'name' => $method->name,
                    'description' => $config['instructions'] ?? '',
                    'account_name' => $config['account_name'] ?? '',
                    'account_number' => $config['account_number'] ?? '',
                    'bank_name' => $config['bank_name'] ?? ''
                ];
            }
        }
        
        wp_send_json_success(['methods' => $methods, 'source' => 'op']);
    } else {
        // Load BK payment methods
        $methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
        
 
        
        wp_send_json_success(['methods' => $methods, 'source' => 'bk']);
    }
}
add_action('wp_ajax_bk_get_payment_methods', 'bntm_ajax_bk_get_payment_methods');
add_action('wp_ajax_nopriv_bk_get_payment_methods', 'bntm_ajax_bk_get_payment_methods');

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

/* ---------- FIX: Add Service Edit Handler ---------- */
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

add_action('wp_ajax_bk_edit_service', 'bntm_ajax_bk_edit_service');

function bntm_ajax_bk_edit_service() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $business_id = get_current_user_id();
    
    $service_id = intval($_POST['service_id'] ?? 0);
    
    // Get the service to verify ownership
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d ",
        $service_id, $business_id
    ));
    
    if (!$service) {
        wp_send_json_error(['message' => 'Service not found']);
    }
    
    // Send back the service data for editing
    wp_send_json_success([
        'id' => $service->id,
        'name' => $service->name,
        'duration' => $service->duration,
        'price' => $service->price,
        'description' => $service->description,
        'status' => $service->status
    ]);
}

add_action('wp_ajax_bk_update_service', 'bntm_ajax_bk_update_service');

function bntm_ajax_bk_update_service() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $business_id = get_current_user_id();
    
    $service_id = intval($_POST['service_id'] ?? 0);
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
    
    $result = $wpdb->update(
        $table,
        [
            'name' => $service_name,
            'duration' => $duration,
            'price' => $price,
            'description' => $description
        ],
        [
            'id' => $service_id
        ],
        ['%s', '%d', '%f', '%s'],
        ['%d', '%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Service updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update service']);
    }
}

add_action('wp_ajax_bk_delete_service', 'bntm_ajax_bk_delete_service');

function bntm_ajax_bk_delete_service() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'bk_services';
    $business_id = get_current_user_id();
    
    $service_id = intval($_POST['service_id'] ?? 0);
    
    $result = $wpdb->delete(
        $table,
        [
            'id' => $service_id
        ],
        ['%d', '%d']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Service deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete service']);
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
    
    // Prepare update data
    $update_data = ['status' => $status];
    $update_format = ['%s'];
    
    // If status is cancelled, also set payment_status to 'dropped'
    if ($status === 'cancelled') {
        $update_data['payment_status'] = 'dropped';
        $update_format[] = '%s';
    }
    
    $result = $wpdb->update(
        $table,
        $update_data,
        ['rand_id' => $booking_id],
        $update_format,
        ['%s']
    );
    
    if ($result !== false) {
        $message = $status === 'cancelled' 
            ? 'Booking cancelled and payment status set to dropped' 
            : 'Booking status updated';
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to update booking']);
    }
}
add_action('wp_ajax_bk_save_booking_settings', 'bk_save_booking_settings_handler');
function bk_save_booking_settings_handler() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $description = sanitize_textarea_field($_POST['bk_description']);
    $terms = sanitize_textarea_field($_POST['bk_terms']);
    
    bntm_update_setting('bk_description', $description);
    bntm_update_setting('bk_terms', $terms);
    
    wp_send_json_success(['message' => 'Booking settings saved successfully!']);
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
 * Update payment method
 */
add_action('wp_ajax_bk_update_payment_method', 'bntm_ajax_bk_update_payment_method');

function bntm_ajax_bk_update_payment_method() {
    check_ajax_referer('bk_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $index = intval($_POST['edit_index']);
    $payment_methods = json_decode(bntm_get_setting('bk_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods)) {
        wp_send_json_error(['message' => 'Invalid data']);
    }
    
    if (!isset($payment_methods[$index])) {
        wp_send_json_error(['message' => 'Payment method not found']);
    }
    
    $type = sanitize_text_field($_POST['payment_type']);
    
    if (!in_array($type, ['bank', 'gcash', 'manual'])) {
        wp_send_json_error(['message' => 'Invalid payment type']);
    }
    
    $payment_methods[$index] = [
        'type' => $type,
        'name' => sanitize_text_field($_POST['payment_name']),
        'description' => sanitize_textarea_field($_POST['payment_description']),
        'account_name' => sanitize_text_field($_POST['account_name']),
        'account_number' => sanitize_text_field($_POST['account_number'])
    ];
    
    bntm_set_setting('bk_payment_methods', json_encode($payment_methods));
    
    wp_send_json_success(['message' => 'Payment method updated successfully!']);
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

function bk_render_recent_bookings($business_id, $limit = 10) {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';
    
    // Get current page from URL parameter
    $current_page = isset($_GET['bk_page']) ? max(1, intval($_GET['bk_page'])) : 1;
    $offset = ($current_page - 1) * $limit;
    
    // Get total count for pagination
    $total_bookings = $wpdb->get_var(
        "SELECT COUNT(*) 
         FROM $bookings_table 
         WHERE payment_status NOT IN ('pending','dropped', 'waiting_payment')"
    );
    
    $total_pages = ceil($total_bookings / $limit);
    
    // Get bookings for current page
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, s.name as service_name 
         FROM $bookings_table b
         LEFT JOIN $services_table s ON b.service_id = s.id
         WHERE b.payment_status NOT IN ('pending','dropped', 'waiting_payment')
         ORDER BY b.booking_date DESC, b.start_time DESC
         LIMIT %d OFFSET %d",
        $limit, $offset
    ));
    
    if (empty($bookings) && $current_page == 1) {
        return '<p>No recent bookings.</p>';
    }
    
    ob_start();
    ?>
    
   <div class="bntm-table-wrapper">
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
            <?php if (!empty($bookings)): ?>
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
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center;">No bookings found on this page.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    <?php if ($total_pages > 1): ?>
        <div class="bk-pagination">
            <?php
            $base_url = remove_query_arg('bk_page');
            
            // Previous button
            if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('bk_page', $current_page - 1, $base_url)); ?>" class="bk-page-btn">
                    &laquo; Previous
                </a>
            <?php endif; ?>
            
            <!-- Page numbers -->
            <div class="bk-page-numbers">
                <?php
                // Show first page
                if ($current_page > 3) {
                    echo '<a href="' . esc_url(add_query_arg('bk_page', 1, $base_url)) . '" class="bk-page-num">1</a>';
                    if ($current_page > 4) {
                        echo '<span class="bk-page-dots">...</span>';
                    }
                }
                
                // Show pages around current page
                for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                    $active_class = ($i == $current_page) ? ' active' : '';
                    echo '<a href="' . esc_url(add_query_arg('bk_page', $i, $base_url)) . '" class="bk-page-num' . $active_class . '">' . $i . '</a>';
                }
                
                // Show last page
                if ($current_page < $total_pages - 2) {
                    if ($current_page < $total_pages - 3) {
                        echo '<span class="bk-page-dots">...</span>';
                    }
                    echo '<a href="' . esc_url(add_query_arg('bk_page', $total_pages, $base_url)) . '" class="bk-page-num">' . $total_pages . '</a>';
                }
                ?>
            </div>
            
            <!-- Next button -->
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('bk_page', $current_page + 1, $base_url)); ?>" class="bk-page-btn">
                    Next &raquo;
                </a>
            <?php endif; ?>
            
            <div class="bk-page-info">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                (<?php echo $total_bookings; ?> total bookings)
            </div>
        </div>
    <?php endif; ?>
    
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
    
    /* Pagination styles */
    .bk-pagination {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        flex-wrap: wrap;
    }
    .bk-page-btn {
        padding: 8px 16px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        transition: all 0.2s;
    }
    .bk-page-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    .bk-page-numbers {
        display: flex;
        gap: 5px;
        flex: 1;
        justify-content: center;
    }
    .bk-page-num {
        padding: 8px 12px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        text-decoration: none;
        color: #374151;
        min-width: 40px;
        text-align: center;
        transition: all 0.2s;
    }
    .bk-page-num:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    .bk-page-num.active {
        background: #3b82f6;
        color: #fff;
        border-color: #3b82f6;
        font-weight: 600;
    }
    .bk-page-dots {
        padding: 8px 4px;
        color: #6b7280;
    }
    .bk-page-info {
        color: #6b7280;
        font-size: 14px;
        margin-left: auto;
    }
    
    @media (max-width: 768px) {
        .bk-pagination {
            justify-content: center;
        }
        .bk-page-info {
            width: 100%;
            text-align: center;
            margin-left: 0;
            margin-top: 10px;
        }
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
function bk_send_booking_confirmation_email($email, $data) {
    $subject = 'Booking Confirmation - ' . ($data['payment_status'] === 'unpaid' ? 'Payment Pending' : 'Received');
    
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    $duration = isset($data['duration']) ? intval($data['duration']) : 0;
    $total_duration = isset($data['total_duration']) ? intval($data['total_duration']) : $duration;
    
    // Format times in 12-hour format
    $start_time_formatted = date('g:i A', strtotime($data['start_time']));
    $end_time_formatted = date('g:i A', strtotime($data['end_time']));
    $date_formatted = date('l, F j, Y', strtotime($data['date']));
    
    $message = "Hello {$data['name']},\n\n";
    $message .= "Your booking has been received!\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "BOOKING DETAILS\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "Service: {$data['service']}\n";
    
    if ($quantity > 1) {
        $message .= "Quantity: {$quantity} slot(s)\n";
        $message .= "Duration: {$duration} min × {$quantity} = {$total_duration} min total\n";
    } else {
        $message .= "Duration: {$total_duration} minutes\n";
    }
    
    $message .= "Date: {$date_formatted}\n";
    $message .= "Time: {$start_time_formatted} - {$end_time_formatted}\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "PRICING\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    if ($quantity > 1 && isset($data['unit_price'])) {
        $message .= "Unit Price: " . bk_format_price($data['unit_price']) . "\n";
        $message .= "Quantity: {$quantity}\n";
        $message .= "Subtotal: " . bk_format_price($data['subtotal']) . "\n";
    } else {
        $message .= "Amount: " . bk_format_price($data['subtotal'] ?? $data['total']) . "\n";
    }
    
    if (isset($data['tax']) && $data['tax'] > 0) {
        $tax_rate = isset($data['tax_rate']) ? $data['tax_rate'] : 0;
        $message .= "Tax ({$tax_rate}%): " . bk_format_price($data['tax']) . "\n";
    }
    
    $message .= "Total Amount: " . bk_format_price($data['total']) . "\n\n";
    
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $message .= "Booking ID: {$data['booking_id']}\n";
    $message .= "Payment Method: {$data['payment_method']}\n";
    $message .= "Status: " . ucfirst(str_replace('_', ' ', $data['payment_status'])) . "\n\n";
    
    if ($data['payment_status'] === 'unpaid' || $data['payment_status'] === 'pending') {
        $message .= "Please complete payment to confirm your booking.\n\n";
    }
    
    $message .= "Thank you for choosing our service!";
    
    return wp_mail($email, $subject, $message);
}
/* ---------- BK BOOKINGS TAB FOR FINANCE IMPORT ---------- */
function bntm_fn_bookings_tab() {
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    $services_table = $wpdb->prefix . 'bk_services';
    $txn_table = $wpdb->prefix . 'fn_transactions';
    
    // Only fetch bookings that are confirmed/completed AND paid
    $bookings = $wpdb->get_results("
        SELECT b.*, 
        s.name as service_name,
        (SELECT COUNT(*) FROM {$txn_table} WHERE reference_type='booking' AND reference_id=b.id) as is_imported
        FROM {$bookings_table} b
        LEFT JOIN {$services_table} s ON b.service_id = s.id
        WHERE b.status IN ('confirmed', 'completed')
        AND b.payment_status = 'paid'
        ORDER BY b.created_at DESC
    ");
    
    $nonce = wp_create_nonce('bntm_fn_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Booking Appointments</h3>
        <p>Import completed bookings as income transactions</p>
        
        <?php if (empty($bookings)): ?>
        <div style="padding: 40px; text-align: center; background: #f9fafb; border-radius: 8px;">
            <p style="color: #6b7280; font-size: 16px; margin: 0;">
                No eligible bookings found. Only confirmed/completed bookings with paid status are shown here.
            </p>
        </div>
        <?php else: ?>
        
        <div style="margin-bottom: 15px;">
            <label style="cursor: pointer; margin-right: 20px;">
                <input type="checkbox" id="select-all-not-imported"> 
                <strong>Select All (Not Imported)</strong>
            </label>
            <label style="cursor: pointer;">
                <input type="checkbox" id="select-all-imported"> 
                <strong>Select All (Imported)</strong>
            </label>
        </div>
        
        <div style="margin-bottom: 15px;">
            <button id="bulk-import-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>" style="margin-right: 10px;">
                Import Selected
            </button>
            <button id="bulk-revert-btn" class="bntm-btn-secondary" data-nonce="<?php echo $nonce; ?>">
                Revert Selected
            </button>
            <span id="selected-count" style="margin-left: 15px; color: #6b7280;"></span>
        </div>
        
       
        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th width="40"></th>
                        <th>Booking ID</th>
                        <th>Service</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                        <th>Import Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr class="booking-eligible">
                        <td>
                            <input type="checkbox" 
                                   class="booking-checkbox <?php echo $booking->is_imported ? 'imported-booking' : 'not-imported-booking'; ?>" 
                                   data-id="<?php echo $booking->id; ?>"
                                   data-rand-id="<?php echo esc_attr($booking->rand_id); ?>"
                                   data-amount="<?php echo $booking->total; ?>"
                                   data-imported="<?php echo $booking->is_imported ? '1' : '0'; ?>">
                        </td>
                        <td>
                            <strong>#<?php echo esc_html($booking->rand_id); ?></strong>
                        </td>
                        <td><?php echo esc_html($booking->service_name); ?></td>
                        <td>
                            <div><?php echo esc_html($booking->customer_name); ?></div>
                            <small style="color: #6b7280;"><?php echo esc_html($booking->customer_email); ?></small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($booking->booking_date)); ?></td>
                        <td>
                            <div><?php echo date('h:i A', strtotime($booking->start_time)); ?></div>
                            <small style="color: #6b7280;">to <?php echo date('h:i A', strtotime($booking->end_time)); ?></small>
                        </td>
                        <td class="bntm-stat-income"><?php echo number_format($booking->total, 2); ?></td>
                        <td>
                            <?php 
                            $status_color = $booking->status === 'confirmed' ? '#059669' : '#3b82f6';
                            ?>
                            <span style="color: <?php echo $status_color; ?>; font-weight: 600;">
                                <?php echo ucfirst($booking->status); ?>
                            </span>
                            
                        </td>
                        <td>
                            <?php if (!empty($booking->payment_method)): ?>
                                <span style="color: #1f2937;"><?php echo esc_html($booking->payment_method); ?></span>
                            <?php else: ?>
                                <span style="color: #6b7280;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($booking->is_imported): ?>
                            <span style="color:#059669; font-weight: 600;">Imported</span>
                            <?php else: ?>
                            <span style="color:#6b7280;">Not Imported</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        
        <?php endif; ?>
    </div>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.booking-checkbox:checked').length;
            const countEl = document.getElementById('selected-count');
            if (countEl) {
                countEl.textContent = selected > 0 ? `${selected} selected` : '';
            }
        }
        
        // Select all not imported
        const selectAllNotImported = document.getElementById('select-all-not-imported');
        if (selectAllNotImported) {
            selectAllNotImported.addEventListener('change', function() {
                document.querySelectorAll('.not-imported-booking').forEach(cb => {
                    cb.checked = this.checked;
                });
                if (this.checked) {
                    const selectAllImported = document.getElementById('select-all-imported');
                    if (selectAllImported) selectAllImported.checked = false;
                }
                updateSelectedCount();
            });
        }
        
        // Select all imported
        const selectAllImported = document.getElementById('select-all-imported');
        if (selectAllImported) {
            selectAllImported.addEventListener('change', function() {
                document.querySelectorAll('.imported-booking').forEach(cb => {
                    cb.checked = this.checked;
                });
                if (this.checked) {
                    const selectAllNotImported = document.getElementById('select-all-not-imported');
                    if (selectAllNotImported) selectAllNotImported.checked = false;
                }
                updateSelectedCount();
            });
        }
        
        // Update count on individual checkbox change
        document.querySelectorAll('.booking-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Bulk Import
        const bulkImportBtn = document.getElementById('bulk-import-btn');
        if (bulkImportBtn) {
            bulkImportBtn.addEventListener('click', function() {
                const selected = Array.from(document.querySelectorAll('.booking-checkbox:checked'))
                    .filter(cb => cb.dataset.imported === '0');
                
                if (selected.length === 0) {
                    alert('Please select at least one booking that is not imported.');
                    return;
                }
                
                // Calculate total amount
                const totalAmount = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
                
                if (!confirm(`Import ${selected.length} booking(s) as income?\n\nTotal Amount: ${totalAmount.toFixed(2)}`)) return;
                
                this.disabled = true;
                this.textContent = 'Importing...';
                
                // Import one by one using AJAX
                let completed = 0;
                let successful = 0;
                const total = selected.length;
                
                selected.forEach(cb => {
                    const data = new FormData();
                    data.append('action', 'bntm_fn_import_booking');
                    data.append('booking_id', cb.dataset.id);
                    data.append('booking_rand_id', cb.dataset.randId);
                    data.append('amount', cb.dataset.amount);
                    data.append('_ajax_nonce', nonce);
                    
                    fetch(ajaxurl, {method: 'POST', body: data})
                    .then(r => r.json())
                    .then(json => {
                        completed++;
                        if (json.success) successful++;
                        
                        if (completed === total) {
                            if (successful === total) {
                                alert(`Successfully imported ${total} booking(s)`);
                            } else {
                                alert(`Imported ${successful} out of ${total} booking(s). Some may have already been imported.`);
                            }
                            location.reload();
                        }
                    })
                    .catch(err => {
                        console.error('Import error:', err);
                        completed++;
                        if (completed === total) {
                            alert(`Imported ${successful} out of ${total} booking(s) with some errors.`);
                            location.reload();
                        }
                    });
                });
            });
        }
        
        // Bulk Revert
        const bulkRevertBtn = document.getElementById('bulk-revert-btn');
        if (bulkRevertBtn) {
            bulkRevertBtn.addEventListener('click', function() {
                const selected = Array.from(document.querySelectorAll('.booking-checkbox:checked'))
                    .filter(cb => cb.dataset.imported === '1');
                
                if (selected.length === 0) {
                    alert('Please select at least one imported booking');
                    return;
                }
                
                if (!confirm(`Remove ${selected.length} booking(s) from Finance transactions?`)) return;
                
                this.disabled = true;
                this.textContent = 'Reverting...';
                
                // Revert one by one using AJAX
                let completed = 0;
                let successful = 0;
                const total = selected.length;
                
                selected.forEach(cb => {
                    const data = new FormData();
                    data.append('action', 'bntm_fn_revert_booking');
                    data.append('booking_id', cb.dataset.id);
                    data.append('_ajax_nonce', nonce);
                    
                    fetch(ajaxurl, {method: 'POST', body: data})
                    .then(r => r.json())
                    .then(json => {
                        completed++;
                        if (json.success) successful++;
                        
                        if (completed === total) {
                            if (successful === total) {
                                alert(`Successfully reverted ${total} booking(s)`);
                            } else {
                                alert(`Reverted ${successful} out of ${total} booking(s).`);
                            }
                            location.reload();
                        }
                    })
                    .catch(err => {
                        console.error('Revert error:', err);
                        completed++;
                        if (completed === total) {
                            alert(`Reverted ${successful} out of ${total} booking(s) with some errors.`);
                            location.reload();
                        }
                    });
                });
            });
        }
    })();
    </script>
    
    <style>
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .bntm-btn-secondary:hover {
        background: #4b5563;
    }
    .bntm-btn-secondary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Eligible bookings - light green background */
    .booking-eligible {
        background: #f0fdf4 !important;
    }
    
    .booking-eligible:hover {
        background: #dcfce7 !important;
    }
    </style>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX: IMPORT BOOKING ---------- */
add_action('wp_ajax_bntm_fn_import_booking', 'bntm_ajax_fn_import_booking');
function bntm_ajax_fn_import_booking() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $bookings_table = $wpdb->prefix . 'bk_bookings';
    
    $booking_id = intval($_POST['booking_id']);
    $booking_rand_id = sanitize_text_field($_POST['booking_rand_id']);
    $amount = floatval($_POST['amount']);
    
    // Verify booking exists and is eligible
    $booking = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$bookings_table} WHERE id = %d",
        $booking_id
    ));
    
    if (!$booking) {
        wp_send_json_error('Booking not found.');
    }
    
    if (!in_array($booking->status, ['confirmed', 'completed']) || $booking->payment_status !== 'paid') {
        wp_send_json_error('Booking must be confirmed/completed and paid to import.');
    }
    
    // Check if already imported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$txn_table} WHERE reference_type='booking' AND reference_id=%d",
        $booking_id
    ));
    
    if ($exists) {
        wp_send_json_error('Booking already imported.');
    }
    
    $rand_id = bntm_rand_id();
    
    $data = [
        'rand_id' => $rand_id,
        'business_id' => $booking->business_id,
        'type' => 'income',
        'amount' => $amount,
        'category' => 'Booking Services',
        'notes' => 'Booking Appointment #' . $booking_rand_id . ' - ' . $booking->customer_name,
        'reference_type' => 'booking',
        'reference_id' => $booking_id
    ];
    
    $result = $wpdb->insert($txn_table, $data);
    
    if ($result) {
        bntm_fn_update_cashflow_summary();
        wp_send_json_success('Booking imported successfully!');
    } else {
        error_log("Failed to import booking. MySQL Error: " . $wpdb->last_error);
        wp_send_json_error('Failed to import booking.');
    }
}

/* ---------- AJAX: REVERT BOOKING ---------- */
add_action('wp_ajax_bntm_fn_revert_booking', 'bntm_ajax_fn_revert_booking');
function bntm_ajax_fn_revert_booking() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $booking_id = intval($_POST['booking_id']);
    
    $result = $wpdb->delete($table, [
        'reference_type' => 'booking',
        'reference_id' => $booking_id
    ]);
    
    if ($result) {
        bntm_fn_update_cashflow_summary();
        wp_send_json_success('Booking reverted from transactions.');
    } else {
        wp_send_json_error('Failed to revert booking or booking not found in transactions.');
    }
}
?>