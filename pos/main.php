<?php
/**
 * Module Name: Point of Sale
 * Module Slug: pos
 * Description: Complete POS system with sales, inventory, and staff management
 * Version: 1.0.3
 * Author: Your Name
 * Icon: 🏪
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_POS_PATH', dirname(__FILE__) . '/');
define('BNTM_POS_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_pos_get_pages() {
    return [
        'Point of Sale' => '[pos_dashboard]',
        'POS' => '[pos_cashier]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_pos_get_tables() {
    global $wpdb;
    $prefix = $wpdb->prefix;
    $charset = "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci";
    
    return [
        'pos_products' => "CREATE TABLE {$prefix}pos_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            barcode VARCHAR(100) DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            stock INT NOT NULL DEFAULT 0,
            reorder_level INT DEFAULT 10,
            category VARCHAR(100) DEFAULT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_sku (sku),
            INDEX idx_barcode (barcode),
            INDEX idx_status (status)
        ) {$charset};",
        
        'pos_transactions' => "CREATE TABLE {$prefix}pos_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            transaction_number VARCHAR(50) UNIQUE NOT NULL,
            staff_id BIGINT UNSIGNED DEFAULT NULL,
            staff_name VARCHAR(255) DEFAULT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0.00,
            tax DECIMAL(10,2) DEFAULT 0.00,
            total DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) DEFAULT 'cash',
            amount_paid DECIMAL(10,2) NOT NULL,
            change_amount DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(50) DEFAULT 'completed',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_staff (staff_id),
            INDEX idx_transaction_number (transaction_number),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) {$charset};",
        
        'pos_transaction_items' => "CREATE TABLE {$prefix}pos_transaction_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            transaction_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            sku VARCHAR(100) DEFAULT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            discount DECIMAL(10,2) DEFAULT 0.00,
            subtotal DECIMAL(10,2) NOT NULL,
            INDEX idx_transaction (transaction_id),
            INDEX idx_product (product_id)
        ) {$charset};",
        
        'pos_staff' => "CREATE TABLE {$prefix}pos_staff (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            pin_code VARCHAR(10) DEFAULT NULL,
            role VARCHAR(50) DEFAULT 'cashier',
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_pin (pin_code),
            INDEX idx_status (status)
        ) {$charset};"
    ];
}


/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_pos_get_shortcodes() {
    return [
        'pos_cashier' => 'bntm_pos_shortcode_cashier',
        'pos_dashboard' => 'bntm_pos_shortcode_dashboard'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_pos_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_pos_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// Register shortcodes
//add_shortcode('pos_cashier', 'bntm_pos_shortcode_cashier');
//add_shortcode('pos_dashboard', 'bntm_pos_shortcode_dashboard');

function bntm_pos_shortcode_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the POS dashboard.</div>';
    }
    
    $active_tab = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-ecommerce-container">
        <div class="bntm-tabs">
            <a href="?type=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?type=products" class="bntm-tab <?php echo $active_tab === 'products' ? 'active' : ''; ?>">Products</a>
            <a href="?type=finance" class="bntm-tab <?php echo $active_tab === 'finance' ? 'active' : ''; ?>">Finance</a>
            <a href="?type=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo pos_overview_tab(); ?>
            <?php elseif ($active_tab === 'products'): ?>
                <?php echo pos_products_tab(); ?>
            <?php elseif ($active_tab === 'finance'): ?>
                <?php echo pos_finance_tab(); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo pos_settings_tab(); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('POS Dashboard', $content);
}
/* ---------- OVERVIEW TAB ---------- */
function pos_overview_tab() {
    $stats = pos_get_dashboard_stats();
    $pos_page = get_page_by_path('pos');
    $pos_url = $pos_page ? get_permalink($pos_page) : '';
    
    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <?php if ($pos_url): ?>
    <div class="pos-cashier-card">
        <div class="pos-cashier-header">
            <div>
                <h3>POS Cashier Page</h3>
                <p class="pos-cashier-subtitle">Access your point of sale system</p>
            </div>
            <span class="pos-status-badge pos-status-active">Active</span>
        </div>
        <div class="pos-cashier-actions">
            <input type="text" id="pos-url" value="<?php echo esc_url($pos_url); ?>" readonly class="pos-url-input">
            <button class="bntm-btn-secondary" id="copy-pos-url">Copy Link</button>
            <a href="<?php echo esc_url($pos_url); ?>" target="_blank" class="bntm-btn-primary">Open POS</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="pos-dashboard-stats">
        <div class="pos-stat-card">
            <div class="pos-stat-icon pos-stat-icon-success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="pos-stat-content">
                <h3>Sales Today</h3>
                <p class="pos-stat-number"><?php echo pos_format_price($stats['sales_today']); ?></p>
                <span class="pos-stat-change pos-stat-positive">
                    <?php echo $stats['sales_today_change']; ?>% vs yesterday
                </span>
            </div>
        </div>
        
        <div class="pos-stat-card">
            <div class="pos-stat-icon pos-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                </svg>
            </div>
            <div class="pos-stat-content">
                <h3>Transactions Today</h3>
                <p class="pos-stat-number"><?php echo esc_html($stats['transactions_today']); ?></p>
                <span class="pos-stat-change pos-stat-neutral">
                    <?php echo esc_html($stats['avg_transaction_value']); ?> avg. value
                </span>
            </div>
        </div>
        
        <div class="pos-stat-card">
            <div class="pos-stat-icon pos-stat-icon-info">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div class="pos-stat-content">
                <h3>Sales This Month</h3>
                <p class="pos-stat-number"><?php echo pos_format_price($stats['sales_month']); ?></p>
                <span class="pos-stat-change pos-stat-neutral">
                    <?php echo esc_html($stats['transactions_month']); ?> transactions
                </span>
            </div>
        </div>
        
        <div class="pos-stat-card">
            <div class="pos-stat-icon <?php echo $stats['low_stock'] > 0 ? 'pos-stat-icon-warning' : 'pos-stat-icon-success'; ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
            </div>
            <div class="pos-stat-content">
                <h3>Low Stock Items</h3>
                <p class="pos-stat-number" style="color: <?php echo $stats['low_stock'] > 0 ? '#f59e0b' : '#10b981'; ?>">
                    <?php echo esc_html($stats['low_stock']); ?>
                </p>
                <span class="pos-stat-change pos-stat-neutral">
                    <?php echo esc_html($stats['total_products']); ?> total products
                </span>
            </div>
        </div>
    </div>

    <div class="pos-charts-grid">
        <div class="pos-chart-card pos-chart-large">
            <h3>Sales Trend (Last 7 Days)</h3>
            <canvas id="salesTrendChart"></canvas>
        </div>
        
        <div class="pos-chart-card">
            <h3>Payment Methods</h3>
            <canvas id="paymentMethodsChart"></canvas>
        </div>
        
        <div class="pos-chart-card">
            <h3>Hourly Sales Today</h3>
            <canvas id="hourlySalesChart"></canvas>
        </div>
    </div>

    <div class="pos-insights-grid">
        <div class="pos-insight-card">
            <div class="pos-insight-header">
                <h4>Top Selling Products</h4>
                <span class="pos-insight-badge">Today</span>
            </div>
            <div class="pos-insight-list">
                <?php foreach ($stats['top_products'] as $product): ?>
                <div class="pos-insight-item">
                    <div class="pos-insight-item-info">
                        <span class="pos-insight-item-name"><?php echo esc_html($product['name']); ?></span>
                        <span class="pos-insight-item-meta"><?php echo esc_html($product['quantity']); ?> sold</span>
                    </div>
                    <span class="pos-insight-item-value"><?php echo pos_format_price($product['total']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="pos-insight-card">
            <div class="pos-insight-header">
                <h4>Peak Hours</h4>
                <span class="pos-insight-badge">This Week</span>
            </div>
            <div class="pos-insight-list">
                <?php foreach ($stats['peak_hours'] as $hour): ?>
                <div class="pos-insight-item">
                    <div class="pos-insight-item-info">
                        <span class="pos-insight-item-name"><?php echo esc_html($hour['hour']); ?></span>
                        <span class="pos-insight-item-meta"><?php echo esc_html($hour['transactions']); ?> transactions</span>
                    </div>
                    <div class="pos-insight-progress">
                        <div class="pos-insight-progress-bar" style="width: <?php echo esc_attr($hour['percentage']); ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="pos-recent-transactions-section">
        <h3>Recent Transactions</h3>
        <?php echo pos_render_recent_transactions(); ?>
    </div>
<style>
.pos-cashier-card {
    background: #f8f9fa;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 30px;
    border: 1px solid #e5e7eb;
}

.pos-cashier-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.pos-cashier-header h3 {
    margin: 0;
    color: #111827;
    font-size: 18px;
    font-weight: 600;
}

.pos-status-badge {
    background: #10b981;
    color: #ffffff;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
}

.pos-cashier-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.pos-url-input {
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

.pos-url-input:focus {
    outline: none;
    border-color: #9ca3af;
}

.pos-dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.pos-stat-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    border: 1px solid #e5e7eb;
    transition: all 0.2s ease;
}

.pos-stat-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.pos-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pos-stat-icon-primary {
    background: var(--bntm-primary, #374151);
    color: #ffffff;
}

.pos-stat-icon-success {
    background: #10b981;
    color: #ffffff;
}

.pos-stat-icon-info {
    background: #3b82f6;
    color: #ffffff;
}

.pos-stat-icon-warning {
    background: #f59e0b;
    color: #ffffff;
}

.pos-stat-content {
    flex: 1;
}

.pos-stat-content h3 {
    margin: 0 0 8px 0;
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pos-stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    line-height: 1;
}

.pos-stat-change {
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
    margin-top: 4px;
}

.pos-stat-positive {
    color: #10b981;
}

.pos-stat-negative {
    color: #ef4444;
}

.pos-stat-neutral {
    color: #6b7280;
}

.pos-charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.pos-chart-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.pos-chart-large {
    grid-column: 1 / -1;
}

.pos-chart-card h3 {
    margin: 0 0 20px 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.pos-chart-card canvas {
    max-height: 300px;
}

.pos-insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.pos-insight-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.pos-insight-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.pos-insight-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.pos-insight-badge {
    background: #f3f4f6;
    color: #6b7280;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pos-insight-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.pos-insight-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.pos-insight-item-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.pos-insight-item-name {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
}

.pos-insight-item-meta {
    font-size: 12px;
    color: #6b7280;
}

.pos-insight-item-value {
    font-size: 15px;
    font-weight: 700;
    color: #111827;
}

.pos-insight-progress {
    flex: 1;
    height: 8px;
    background: #f3f4f6;
    border-radius: 4px;
    overflow: hidden;
}

.pos-insight-progress-bar {
    height: 100%;
    background: var(--bntm-primary, #374151);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.pos-recent-transactions-section {
    background: #ffffff;
    padding: 24px;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.pos-recent-transactions-section h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

    /* Pagination styles */
    .pos-pagination {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        flex-wrap: wrap;
    }
    .pos-page-btn {
        padding: 8px 16px;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        transition: all 0.2s;
    }
    .pos-page-btn:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    .pos-page-numbers {
        display: flex;
        gap: 5px;
        flex: 1;
        justify-content: center;
    }
    .pos-page-num {
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
    .pos-page-num:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    .pos-page-num.active {
        background: #3b82f6;
        color: #fff;
        border-color: #3b82f6;
        font-weight: 600;
    }
    .pos-page-dots {
        padding: 8px 4px;
        color: #6b7280;
    }
    .pos-page-info {
        color: #6b7280;
        font-size: 14px;
        margin-left: auto;
    }
@media (max-width: 768px) {
    .pos-chart-card {
        grid-column: 1 / -1;
    }
    .pos-insight-card {
        grid-column: 1 / -1;
    }
}
</style>
    
    <script>
    (function() {
        // Copy URL functionality
        const copyBtn = document.getElementById('copy-pos-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const urlInput = document.getElementById('pos-url');
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
            .getPropertyValue('--bntm-primary').trim() || '#667eea';
        
        // Sales Trend Chart (Line Chart - 7 Days)
        const salesTrendCtx = document.getElementById('salesTrendChart');
        if (salesTrendCtx) {
            new Chart(salesTrendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['daily_sales_data'], 'date')); ?>,
                    datasets: [{
                        label: 'Sales',
                        data: <?php echo json_encode(array_column($stats['daily_sales_data'], 'total')); ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#667eea',
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
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'Sales: ₱' + context.parsed.y.toFixed(2);
                                }
                            }
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
                                font: { size: 12 }
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
        
        // Payment Methods Chart (Doughnut Chart)
        const paymentMethodsCtx = document.getElementById('paymentMethodsChart');
        if (paymentMethodsCtx) {
            new Chart(paymentMethodsCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($stats['payment_methods_data'], 'method')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['payment_methods_data'], 'total')); ?>,
                        backgroundColor: [
                            '#667eea',
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
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    return label + ': ₱' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Hourly Sales Chart (Bar Chart)
        const hourlySalesCtx = document.getElementById('hourlySalesChart');
        if (hourlySalesCtx) {
            new Chart(hourlySalesCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($stats['hourly_sales_data'], 'hour')); ?>,
                    datasets: [{
                        label: 'Sales',
                        data: <?php echo json_encode(array_column($stats['hourly_sales_data'], 'total')); ?>,
                        backgroundColor: '#764ba2',
                        borderRadius: 6,
                        borderSkipped: false
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
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'Sales: ₱' + context.parsed.y.toFixed(2);
                                }
                            }
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
                                font: { size: 12 }
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
    })();
    </script>
    <?php
    return ob_get_clean();
}

function pos_get_dashboard_stats() {
    global $wpdb;
    $trans_table = $wpdb->prefix . 'pos_transactions';
    $items_table = $wpdb->prefix . 'pos_transaction_items';
    $prod_table = $wpdb->prefix . 'pos_products';
    
    // Sales today
    $sales_today = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM {$trans_table} 
        WHERE DATE(created_at) = CURDATE() AND status = 'completed'"
    );
    
    // Sales yesterday
    $sales_yesterday = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM {$trans_table} 
        WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status = 'completed'"
    );
    
    // Calculate percentage change
    $sales_today_change = $sales_yesterday > 0 
        ? round((($sales_today - $sales_yesterday) / $sales_yesterday) * 100, 1) 
        : 0;
    
    // Transactions today
    $transactions_today = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$trans_table} 
        WHERE DATE(created_at) = CURDATE() AND status = 'completed'"
    );
    
    // Average transaction value
    $avg_transaction_value = $transactions_today > 0 
        ? pos_format_price($sales_today / $transactions_today) 
        : pos_format_price(0);
    
    // Sales this month
    $sales_month = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM {$trans_table} 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE()) 
        AND status = 'completed'"
    );
    
    // Transactions this month
    $transactions_month = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$trans_table} 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE()) 
        AND status = 'completed'"
    );
    
    // Low stock count
    $low_stock = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prod_table} 
        WHERE stock <= reorder_level AND status = 'active'"
    );
    
    // Total products
    $total_products = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$prod_table} WHERE status = 'active'"
    );
    
    // Daily sales data (last 7 days)
    $daily_sales_data = $wpdb->get_results(
        "SELECT DATE_FORMAT(created_at, '%a') as date, COALESCE(SUM(total), 0) as total
        FROM {$trans_table}
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)",
        ARRAY_A
    );
    
    // If no data, create empty days
    if (empty($daily_sales_data)) {
        $daily_sales_data = [];
        for ($i = 6; $i >= 0; $i--) {
            $daily_sales_data[] = [
                'date' => date('D', strtotime("-$i days")),
                'total' => 0
            ];
        }
    }
    
    // Payment methods data
    $payment_methods_data = $wpdb->get_results(
        "SELECT payment_method as method, COALESCE(SUM(total), 0) as total
        FROM {$trans_table}
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status = 'completed'
        GROUP BY payment_method",
        ARRAY_A
    );
    
    // Hourly sales today
    $hourly_sales_data = $wpdb->get_results(
        "SELECT DATE_FORMAT(created_at, '%h %p') as hour, COALESCE(SUM(total), 0) as total
        FROM {$trans_table}
        WHERE DATE(created_at) = CURDATE()
        AND status = 'completed'
        GROUP BY HOUR(created_at)
        ORDER BY HOUR(created_at)",
        ARRAY_A
    );
    
    // Top products today
    $top_products = $wpdb->get_results(
        "SELECT p.name, SUM(ti.quantity) as quantity, SUM(ti.quantity * ti.price) as total
        FROM {$items_table} ti
        JOIN {$prod_table} p ON ti.product_id = p.id
        JOIN {$trans_table} t ON ti.transaction_id = t.id
        WHERE DATE(t.created_at) = CURDATE()
        AND t.status = 'completed'
        GROUP BY p.id, p.name
        ORDER BY total DESC
        LIMIT 5",
        ARRAY_A
    );
    
    // Peak hours this week
    $peak_hours = $wpdb->get_results(
        "SELECT 
            DATE_FORMAT(created_at, '%h %p') as hour,
            COUNT(*) as transactions,
            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM {$trans_table} 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                AND status = 'completed')) as percentage
        FROM {$trans_table}
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status = 'completed'
        GROUP BY HOUR(created_at)
        ORDER BY transactions DESC
        LIMIT 5",
        ARRAY_A
    );
    
    return [
        'sales_today' => floatval($sales_today),
        'sales_today_change' => $sales_today_change,
        'transactions_today' => intval($transactions_today),
        'avg_transaction_value' => $avg_transaction_value,
        'sales_month' => floatval($sales_month),
        'transactions_month' => intval($transactions_month),
        'low_stock' => intval($low_stock),
        'total_products' => intval($total_products),
        'daily_sales_data' => $daily_sales_data,
        'payment_methods_data' => $payment_methods_data ?: [],
        'hourly_sales_data' => $hourly_sales_data ?: [],
        'top_products' => $top_products ?: [],
        'peak_hours' => $peak_hours ?: []
    ];
}

function pos_format_price($amount) {
    return '₱' . number_format(floatval($amount), 2);
}
/* ---------- RECENT TRANSACTIONS WITH PAGINATION ---------- */
function pos_render_recent_transactions($limit = 10) {
    global $wpdb;
    $trans_table = $wpdb->prefix . 'pos_transactions';
    
    // Get current page from URL parameter
    $current_page = isset($_GET['pos_page']) ? max(1, intval($_GET['pos_page'])) : 1;
    $offset = ($current_page - 1) * $limit;
    
    // Get total count for pagination
    $total_transactions = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$trans_table}"
    );
    
    $total_pages = ceil($total_transactions / $limit);
    
    // Get transactions for current page
    $recent_transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$trans_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ));
    
    if (empty($recent_transactions) && $current_page == 1) {
        return '<p>No transactions yet.</p>';
    }
    
    ob_start();
    ?>
    
   <div class="bntm-table-wrapper">
    <table class="bntm-table">
        <thead>
            <tr>
                <th>Transaction #</th>
                <th>Staff</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Date/Time</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($recent_transactions)): ?>
                <?php foreach ($recent_transactions as $trans): ?>
                    <tr>
                        <td>#<?php echo esc_html($trans->transaction_number); ?></td>
                        <td><?php echo esc_html($trans->staff_name ?: 'N/A'); ?></td>
                        <td style="color: #059669; font-weight: 600;">₱<?php echo number_format($trans->total, 2); ?></td>
                        <td><?php echo esc_html(ucfirst($trans->payment_method)); ?></td>
                        <td>
                            <span class="pos-status-badge pos-status-<?php echo esc_attr($trans->status); ?>">
                                <?php echo esc_html(ucfirst($trans->status)); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($trans->created_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No transactions found on this page.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
   </div> 
    <?php if ($total_pages > 1): ?>
        <div class="pos-pagination">
            <?php
            $base_url = remove_query_arg('pos_page');
            
            // Previous button
            if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('pos_page', $current_page - 1, $base_url)); ?>" class="pos-page-btn">
                    &laquo; Previous
                </a>
            <?php endif; ?>
            
            <!-- Page numbers -->
            <div class="pos-page-numbers">
                <?php
                // Show first page
                if ($current_page > 3) {
                    echo '<a href="' . esc_url(add_query_arg('pos_page', 1, $base_url)) . '" class="pos-page-num">1</a>';
                    if ($current_page > 4) {
                        echo '<span class="pos-page-dots">...</span>';
                    }
                }
                
                // Show pages around current page
                for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                    $active_class = ($i == $current_page) ? ' active' : '';
                    echo '<a href="' . esc_url(add_query_arg('pos_page', $i, $base_url)) . '" class="pos-page-num' . $active_class . '">' . $i . '</a>';
                }
                
                // Show last page
                if ($current_page < $total_pages - 2) {
                    if ($current_page < $total_pages - 3) {
                        echo '<span class="pos-page-dots">...</span>';
                    }
                    echo '<a href="' . esc_url(add_query_arg('pos_page', $total_pages, $base_url)) . '" class="pos-page-num">' . $total_pages . '</a>';
                }
                ?>
            </div>
            
            <!-- Next button -->
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('pos_page', $current_page + 1, $base_url)); ?>" class="pos-page-btn">
                    Next &raquo;
                </a>
            <?php endif; ?>
            
            <div class="pos-page-info">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                (<?php echo $total_transactions; ?> total transactions)
            </div>
        </div>
    <?php endif; ?>
    

    <?php
    return ob_get_clean();
}

/* ---------- PRODUCTS TAB ---------- */
function pos_products_tab() {
    global $wpdb;
    $pos_table = $wpdb->prefix . 'pos_products';
    $in_table = $wpdb->prefix . 'in_products';
    
    // Get all POS products with real-time stock from IN module
    $products = $wpdb->get_results("
        SELECT pos.*, 
               COALESCE(inprod.stock_quantity, pos.stock) as current_stock,
               inprod.image AS in_image
        FROM {$pos_table} AS pos
        LEFT JOIN {$in_table} AS inprod ON pos.rand_id = inprod.rand_id
        ORDER BY pos.name ASC
    ");
    
    $nonce = wp_create_nonce('pos_products_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>POS Products (<?php echo count($products); ?>)</h3>
            <button id="add-new-pos-product-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
                + Add New Product
            </button>
        </div>
        
        <?php if (empty($products)): ?>
            <p>No products yet. Click "Add New Product" to get started.</p>
        <?php else: ?>
        
           <div class="bntm-table-wrapper">
               <table class="bntm-table">
                   <thead>
                       <tr>
                           <th>Name</th>
                           <th>SKU</th>
                           <th>Price</th>
                           <th>Stock (Live from Inventory)</th>
                           <th>Status</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($products as $product): ?>
                           <tr data-product-id="<?php echo $product->id; ?>" class="product-row <?php echo $product->status === 'inactive' ? 'product-hidden' : ''; ?>">
                               <td><?php echo esc_html($product->name); ?></td>
                               <td><?php echo esc_html($product->sku ?: '-'); ?></td>
                               <td>₱<?php echo number_format($product->price, 2); ?></td>
                               <td>
                                   <span class="stock-display"><?php echo esc_html($product->current_stock); ?></span>
                                   <?php if ($product->current_stock <= $product->reorder_level): ?>
                                       <span style="color: #dc2626; font-size: 12px;">⚠ Low</span>
                                   <?php endif; ?>
                                   <small style="color: #6b7280; display: block; font-size: 11px;">Auto-synced</small>
                               </td>
                               <td>
                                   <label class="pos-toggle">
                                       <input type="checkbox" 
                                              class="pos-toggle-status" 
                                              data-id="<?php echo $product->id; ?>"
                                              data-nonce="<?php echo $nonce; ?>"
                                              <?php checked($product->status, 'active'); ?>>
                                       <span class="pos-toggle-slider"></span>
                                   </label>
                                   <span class="status-text"><?php echo ucfirst($product->status); ?></span>
                               </td>
                               <td>
                                   <button class="bntm-btn-small pos-edit-product" 
                                           data-id="<?php echo $product->id; ?>"
                                           data-nonce="<?php echo $nonce; ?>"
                                           title="Edit product">
                                       Edit
                                   </button>
                                   <button class="bntm-btn-small bntm-btn-danger pos-delete-product" 
                                           data-id="<?php echo $product->id; ?>"
                                           data-nonce="<?php echo $nonce; ?>"
                                           title="Delete product"
                                           style="background: #dc2626; color: white; margin-left: 5px;">
                                       Delete
                                   </button>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="pos-product-modal" class="pos-modal" style="display: none;">
        <div class="pos-modal-content">
            <div class="pos-modal-header">
                <h2 id="pos-modal-title">Add New Product</h2>
                <button class="pos-modal-close">&times;</button>
            </div>
            <div class="pos-modal-body">
                <form id="pos-product-form" class="bntm-form">
                    <input type="hidden" id="pos-product-id" name="product_id" value="">
                    
                    <div class="bntm-form-group">
                        <label>Product Name *</label>
                        <input type="text" id="pos-product-name" name="name" required>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>SKU *</label>
                            <input type="text" id="pos-product-sku" name="sku" required>
                            <small>Unique product identifier</small>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Barcode</label>
                            <input type="text" id="pos-product-barcode" name="barcode">
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Price *</label>
                            <input type="number" id="pos-product-price" name="price" step="0.01" min="0" required>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Cost per Unit</label>
                            <input type="number" id="pos-product-cost" name="cost" step="0.01" min="0" value="0">
                            <small>For inventory tracking</small>
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Initial Stock *</label>
                            <input type="number" id="pos-product-stock" name="stock" min="0" required>
                            <small>Starting inventory quantity</small>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Reorder Level</label>
                            <input type="number" id="pos-product-reorder" name="reorder_level" min="0" value="10">
                            <small>Alert when stock falls below</small>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Description</label>
                        <textarea id="pos-product-description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="bntm-btn-primary" style="flex: 1;">
                            <span id="pos-submit-text">Save Product</span>
                        </button>
                        <button type="button" class="bntm-btn-secondary pos-cancel-modal" style="flex: 1;">
                            Cancel
                        </button>
                    </div>
                </form>
                <div id="pos-product-form-message"></div>
            </div>
        </div>
    </div>
    
    <style>
    .pos-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .pos-modal-content {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .pos-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .pos-modal-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1f2937;
    }
    
    .pos-modal-close {
        background: none;
        border: none;
        font-size: 32px;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .pos-modal-close:hover {
        background: #f3f4f6;
        color: #1f2937;
    }
    
    .pos-modal-body {
        padding: 30px;
        overflow-y: auto;
    }
    
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .bntm-btn-secondary:hover {
        background: #4b5563;
    }
    
    .stock-display {
        font-weight: 600;
        color: #059669;
    }
    
    .pos-toggle {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        margin-right: 10px;
    }
    .pos-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .pos-toggle-slider {
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
    .pos-toggle-slider:before {
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
    .pos-toggle input:checked + .pos-toggle-slider {
        background-color: #059669;
    }
    .pos-toggle input:checked + .pos-toggle-slider:before {
        transform: translateX(26px);
    }
    .product-hidden {
        opacity: 0.5;
        background: #f9fafb;
    }
    .status-text {
        font-size: 14px;
        color: #6b7280;
    }
    </style>
    
    <script>
    (function() {
        const modal = document.getElementById('pos-product-modal');
        const modalTitle = document.getElementById('pos-modal-title');
        const productForm = document.getElementById('pos-product-form');
        const message = document.getElementById('pos-product-form-message');
        
        // Open modal for new product
        document.getElementById('add-new-pos-product-btn').addEventListener('click', function() {
            modalTitle.textContent = 'Add New Product';
            productForm.reset();
            document.getElementById('pos-product-id').value = '';
            document.getElementById('pos-submit-text').textContent = 'Save Product';
            modal.style.display = 'flex';
        });
        
        // Open modal for edit
        document.querySelectorAll('.pos-edit-product').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.dataset.id;
                
                modalTitle.textContent = 'Edit Product';
                document.getElementById('pos-submit-text').textContent = 'Update Product';
                
                // Fetch product data
                const formData = new FormData();
                formData.append('action', 'pos_get_product_data');
                formData.append('product_id', productId);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const p = json.data;
                        document.getElementById('pos-product-id').value = p.id;
                        document.getElementById('pos-product-name').value = p.name;
                        document.getElementById('pos-product-sku').value = p.sku;
                        document.getElementById('pos-product-barcode').value = p.barcode || '';
                        document.getElementById('pos-product-price').value = p.selling_price || p.price;
                        document.getElementById('pos-product-stock').value = p.stock_quantity || p.stock;
                        document.getElementById('pos-product-cost').value = p.cost_per_unit || p.cost || 0;
                        document.getElementById('pos-product-reorder').value = p.reorder_level || 10;
                        document.getElementById('pos-product-description').value = p.description || '';
                        modal.style.display = 'flex';
                    } else {
                        alert('Failed to load product data');
                    }
                })
                .catch(err => {
                    alert('Error loading product: ' + err.message);
                });
            });
        });
        
        // Close modal
        document.querySelectorAll('.pos-modal-close, .pos-cancel-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Submit form
        productForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pos_save_product');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const submitText = document.getElementById('pos-submit-text');
            const originalText = submitText.textContent;
            
            submitBtn.disabled = true;
            submitText.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                message.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                if (json.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    submitBtn.disabled = false;
                    submitText.textContent = originalText;
                }
            });
        });
        
        // Toggle status
        document.querySelectorAll('.pos-toggle-status').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const formData = new FormData();
                formData.append('action', 'pos_toggle_product_status');
                formData.append('product_id', this.dataset.id);
                formData.append('status', this.checked ? 'active' : 'inactive');
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const statusText = this.closest('td').querySelector('.status-text');
                        statusText.textContent = this.checked ? 'Active' : 'Inactive';
                        
                        const row = this.closest('tr');
                        if (this.checked) {
                            row.classList.remove('product-hidden');
                        } else {
                            row.classList.add('product-hidden');
                        }
                    } else {
                        alert(json.data.message);
                        this.checked = !this.checked;
                    }
                });
            });
        });
        
        // Delete product
        document.querySelectorAll('.pos-delete-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this product? This will also remove it from inventory.')) return;
                
                this.disabled = true;
                this.textContent = '⏳';
                
                const formData = new FormData();
                formData.append('action', 'pos_delete_product');
                formData.append('product_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                    else {
                        this.disabled = false;
                        this.textContent = 'Delete';
                    }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- FINANCE TAB ---------- */
function pos_finance_tab() {
    global $wpdb;
    $trans_table = $wpdb->prefix . 'pos_transactions';
    $fn_table = $wpdb->prefix . 'fn_transactions';
    
    $transactions = $wpdb->get_results("
        SELECT t.*, 
        (SELECT COUNT(*) FROM {$fn_table} WHERE reference_type='pos_sale' AND reference_id=t.id) as is_imported
        FROM {$trans_table} t
        WHERE t.status = 'completed'
        ORDER BY t.created_at DESC
    ");
    
    $nonce = wp_create_nonce('pos_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>POS Transactions</h3>
        <p>Import completed sales as income transactions to Finance module</p>
        
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
                       <th>Transaction #</th>
                       <th>Date</th>
                       <th>Staff</th>
                       <th>Total</th>
                       <th>Payment</th>
                       <th>Status</th>
                   </tr>
               </thead>
               <tbody>
                   <?php if (empty($transactions)): ?>
                   <tr><td colspan="7" style="text-align:center;">No transactions found</td></tr>
                   <?php else: foreach ($transactions as $trans): ?>
                   <tr>
                       <td>
                           <input type="checkbox" 
                                  class="trans-checkbox <?php echo $trans->is_imported ? 'imported-trans' : 'not-imported-trans'; ?>" 
                                  data-id="<?php echo $trans->id; ?>"
                                  data-amount="<?php echo $trans->total; ?>"
                                  data-imported="<?php echo $trans->is_imported ? '1' : '0'; ?>">
                       </td>
                       <td>#<?php echo $trans->transaction_number; ?></td>
                       <td><?php echo date('M d, Y H:i', strtotime($trans->created_at)); ?></td>
                       <td><?php echo esc_html($trans->staff_name ?: 'N/A'); ?></td>
                       <td class="bntm-stat-income">₱<?php echo number_format($trans->total, 2); ?></td>
                       <td><?php echo esc_html(ucfirst($trans->payment_method)); ?></td>
                       <td>
                           <?php if ($trans->is_imported): ?>
                           <span style="color:#059669;">✓ Imported</span>
                           <?php else: ?>
                           <span style="color:#6b7280;">Not Imported</span>
                           <?php endif; ?>
                       </td>
                   </tr>
                   <?php endforeach; endif; ?>
               </tbody>
           </table>
        </div>
    </div>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.trans-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        // Select all not imported
        document.getElementById('select-all-not-imported').addEventListener('change', function() {
            document.querySelectorAll('.not-imported-trans').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Select all imported
        document.getElementById('select-all-imported').addEventListener('change', function() {
            document.querySelectorAll('.imported-trans').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-not-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Update count on individual checkbox change
        document.querySelectorAll('.trans-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Bulk Import
        document.getElementById('bulk-import-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.trans-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '0');
            
            if (selected.length === 0) {
                alert('Please select at least one transaction that is not imported');
                return;
            }
            
            if (!confirm(`Import ${selected.length} transaction(s) as income?`)) return;
            
            this.disabled = true;
            this.textContent = 'Importing...';
            
            // Import one by one using existing AJAX
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'pos_import_transaction');
                data.append('trans_id', cb.dataset.id);
                data.append('amount', cb.dataset.amount);
                data.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully imported ${total} transaction(s)`);
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Import error:', err);
                    completed++;
                    if (completed === total) {
                        alert('Import completed with some errors. Please check and try again.');
                        location.reload();
                    }
                });
            });
        });
        
        // Bulk Revert
        document.getElementById('bulk-revert-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.trans-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '1');
            
            if (selected.length === 0) {
                alert('Please select at least one imported transaction');
                return;
            }
            
            if (!confirm(`Remove ${selected.length} transaction(s) from finance?`)) return;
            
            this.disabled = true;
            this.textContent = 'Reverting...';
            
            // Revert one by one using existing AJAX
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'pos_revert_transaction');
                data.append('trans_id', cb.dataset.id);
                data.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully reverted ${total} transaction(s)`);
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Revert error:', err);
                    completed++;
                    if (completed === total) {
                        alert('Revert completed with some errors. Please check and try again.');
                        location.reload();
                    }
                });
            });
        });
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
    </style>
    <?php
    return ob_get_clean();
}

/* ---------- SETTINGS TAB ---------- */
function pos_settings_tab() {
    // Get all users with 'pos_cashier' role
    $staff_members = get_users(['role' => 'pos_cashier']);
    
    // Get user limit
    $user_limit = get_option('bntm_user_limit', 0);
    $current_users = count(get_users(['exclude' => [1]]));
    $limit_text = $user_limit > 0 ? " ({$current_users}/{$user_limit})" : " ({$current_users})";
    $limit_reached = $user_limit > 0 && $current_users >= $user_limit;
    
    $nonce = wp_create_nonce('pos_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Staff Management</h3>
        <p>Assign staff members who can use the POS system</p>
        
        <div style="margin: 20px 0;">
            <button id="show-add-staff" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled' : ''; ?>>
                + Add Staff<?php echo $limit_text; ?>
            </button>
        </div>
        
        <?php if ($limit_reached): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <strong>⚠️ User Limit Reached:</strong> Maximum of <?php echo $user_limit; ?> users allowed.
            </div>
        <?php endif; ?>
        
        <div id="add-staff-form" style="display:none; background:#f9fafb; padding:20px; border-radius:8px; margin-bottom:20px;">
            <h4 style="margin-top:0;">Add New Staff</h4>
            <form id="staff-form" class="bntm-form">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Name *</label>
                        <input type="text" name="staff_name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Username *</label>
                        <input type="text" name="staff_username" required>
                        <small>Unique username for login</small>
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Email *</label>
                        <input type="email" name="staff_email" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Password *</label>
                        <input type="password" name="staff_password" required minlength="6">
                        <small>Minimum 6 characters</small>
                    </div>
                </div>
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Phone</label>
                        <input type="tel" name="staff_phone">
                    </div>
                    <div class="bntm-form-group">
                        <label>PIN Code (4-6 digits)</label>
                        <input type="text" name="staff_pin" pattern="[0-9]{4,6}" maxlength="6">
                        <small>Optional PIN for quick POS login</small>
                    </div>
                </div>
                <button type="submit" class="bntm-btn-primary">Add Staff</button>
                <button type="button" id="cancel-add-staff" class="bntm-btn-secondary">Cancel</button>
                <div id="staff-message"></div>
            </form>
        </div>
        
        <div class="bntm-table-wrapper">
           <table class="bntm-table">
               <thead>
                   <tr>
                       <th>Name</th>
                       <th>Username</th>
                       <th>Email</th>
                       <th>Phone</th>
                       <th>PIN</th>
                       <th>Status</th>
                       <th>Actions</th>
                   </tr>
               </thead>
               <tbody>
                   <?php if (empty($staff_members)): ?>
                   <tr><td colspan="7" style="text-align:center;">No staff members yet</td></tr>
                   <?php else: foreach ($staff_members as $staff): 
                       $phone = get_user_meta($staff->ID, 'pos_phone', true);
                       $pin = get_user_meta($staff->ID, 'pos_pin', true);
                       $status = get_user_meta($staff->ID, 'pos_status', true) ?: 'active';
                   ?>
                   <tr>
                       <td><?php echo esc_html($staff->display_name); ?></td>
                       <td><?php echo esc_html($staff->user_login); ?></td>
                       <td><?php echo esc_html($staff->user_email); ?></td>
                       <td><?php echo esc_html($phone ?: '-'); ?></td>
                       <td><?php echo $pin ? $pin : '-'; ?></td>
                       <td>
                           <span style="color: <?php echo $status === 'active' ? '#059669' : '#dc2626'; ?>">
                               <?php echo ucfirst($status); ?>
                           </span>
                       </td>
                       <td>
                           <?php if ($status === 'active'): ?>
                           <button class="bntm-btn-small bntm-btn-danger toggle-staff-status" 
                                   data-id="<?php echo $staff->ID; ?>"
                                   data-status="inactive"
                                   data-nonce="<?php echo $nonce; ?>">
                               Deactivate
                           </button>
                           <?php else: ?>
                           <button class="bntm-btn-small bntm-btn-success toggle-staff-status" 
                                   data-id="<?php echo $staff->ID; ?>"
                                   data-status="active"
                                   data-nonce="<?php echo $nonce; ?>">
                               Activate
                           </button>
                           <?php endif; ?>
                       </td>
                   </tr>
                   <?php endforeach; endif; ?>
               </tbody>
           </table>
       </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>POS Settings</h3>
        <form id="pos-settings-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Tax Rate (%)</label>
                <input type="number" name="tax_rate" step="0.01" value="<?php echo esc_attr(bntm_get_setting('pos_tax_rate', '0')); ?>">
                <small>Applied to all sales</small>
            </div>
            <div class="bntm-form-group">
                <label>Receipt Header</label>
                <textarea name="receipt_header" rows="3"><?php echo esc_textarea(bntm_get_setting('pos_receipt_header', '')); ?></textarea>
                <small>Store name, address, contact info</small>
            </div>
            <div class="bntm-form-group">
                <label>Receipt Footer</label>
                <textarea name="receipt_footer" rows="2"><?php echo esc_textarea(bntm_get_setting('pos_receipt_footer', 'Thank you for your purchase!')); ?></textarea>
            </div>
            <button type="submit" class="bntm-btn-primary">Save Settings</button>
            <div id="settings-message"></div>
        </form>
    </div>
    
    <script>
    (function() {
        // Show/hide add staff form
        document.getElementById('show-add-staff').addEventListener('click', function() {
            document.getElementById('add-staff-form').style.display = 'block';
        });
        
        document.getElementById('cancel-add-staff').addEventListener('click', function() {
            document.getElementById('add-staff-form').style.display = 'none';
            document.getElementById('staff-form').reset();
        });
        
        // Add staff
        document.getElementById('staff-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pos_add_staff');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method:'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('staff-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                
                if (json.success) {
                    setTimeout(() => location.reload(), 1500);
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Add Staff';
                }
            });
        });
        
        // Toggle staff status
        document.querySelectorAll('.toggle-staff-status').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.status === 'active' ? 'activate' : 'deactivate';
                if (!confirm('Are you sure you want to ' + action + ' this staff member?')) return;
                
                const formData = new FormData();
                formData.append('action', 'pos_toggle_staff_status');
                formData.append('staff_id', this.dataset.id);
                formData.append('status', this.dataset.status);
                formData.append('nonce', this.dataset.nonce);
                
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
        
        // Save settings
        document.getElementById('pos-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pos_save_settings');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('settings-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                
                btn.disabled = false;
                btn.textContent = 'Save Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLERS ---------- */
// Get product data for editing
function bntm_ajax_pos_get_product_data() {
    check_ajax_referer('pos_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $pos_table = $wpdb->prefix . 'pos_products';
    $in_table = $wpdb->prefix . 'in_products';
    $product_id = intval($_POST['product_id']);
    
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT pos.*, 
                inprod.cost_per_unit,
                inprod.selling_price as in_selling_price,
                inprod.stock_quantity
         FROM $pos_table pos
         LEFT JOIN $in_table inprod ON pos.rand_id = inprod.rand_id
         WHERE pos.id = %d",
        $product_id
    ));
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found']);
    }
    
    wp_send_json_success($product);
}
add_action('wp_ajax_pos_get_product_data', 'bntm_ajax_pos_get_product_data');

function bntm_ajax_pos_save_product() {
    check_ajax_referer('pos_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $pos_table = $wpdb->prefix . 'pos_products';
    $in_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    
    $product_id = intval($_POST['product_id'] ?? 0);
    $name = sanitize_text_field($_POST['name']);
    $sku = sanitize_text_field($_POST['sku']);
    $barcode = sanitize_text_field($_POST['barcode'] ?? '');
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $cost = floatval($_POST['cost'] ?? 0);
    $reorder_level = intval($_POST['reorder_level'] ?? 10);
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    
    // Generate SKU if empty
    if (empty($sku)) {
        $sku = 'POS-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }
    
    // Generate barcode if empty
    if (empty($barcode)) {
        $barcode = 'POS-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    if (empty($name)) {
        wp_send_json_error(['message' => 'Product name is required']);
    }
    
    $wpdb->show_errors();
    $wpdb->query('START TRANSACTION');
    
    try {
        if ($product_id > 0) {
            // UPDATE existing product
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $pos_table WHERE id = %d",
                $product_id
            ));
            
            if (!$existing) {
                throw new Exception('Product not found');
            }
            
            // Check if SKU changed and conflicts
            if ($sku !== $existing->sku) {
                $sku_exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $pos_table WHERE sku = %s AND id != %d",
                    $sku, $product_id
                ));
                
                if ($sku_exists) {
                    throw new Exception('SKU already exists');
                }
            }
            
            // Update POS product
            $wpdb->update(
                $pos_table,
                [
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'price' => $price,
                    'description' => $description,
                    'reorder_level' => $reorder_level
                ],
                ['id' => $product_id],
                ['%s', '%s', '%s', '%f', '%s', '%d'],
                ['%d']
            );
            
            // Update IN product
            $wpdb->update(
                $in_table,
                [
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'selling_price' => $price,
                    'cost_per_unit' => $cost,
                    'description' => $description,
                    'reorder_level' => $reorder_level
                ],
                ['rand_id' => $existing->rand_id],
                ['%s', '%s', '%s', '%f', '%f', '%s', '%d'],
                ['%s']
            );
            
            $message = 'Product updated successfully!';
            
        } else {
            // CREATE new product
            $sku_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $pos_table WHERE sku = %s",
                $sku
            ));
            
            if ($sku_exists) {
                throw new Exception('SKU already exists');
            }
            
            $rand_id = bntm_rand_id();
            $business_id = get_current_user_id();
            
            // Insert into POS
            $wpdb->insert($pos_table, [
                'rand_id' => $rand_id,
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'price' => $price,
                'cost' => $cost,
                'stock' => $stock,
                'reorder_level' => $reorder_level,
                'description' => $description,
                'status' => 'active'
            ], ['%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s']);
            
            if (!$wpdb->insert_id) {
                throw new Exception('Failed to create POS product. Error: ' . $wpdb->last_error);
            }
            
            // Insert into IN
            $wpdb->insert($in_table, [
                'rand_id' => $rand_id,
                'business_id' => $business_id,
                'name' => $name,
                'sku' => $sku,
                'barcode' => $barcode,
                'inventory_type' => 'Product',
                'cost_per_unit' => $cost,
                'selling_price' => $price,
                'stock_quantity' => $stock,
                'reorder_level' => $reorder_level,
                'description' => $description
            ], ['%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s']);
            
            $in_id = $wpdb->insert_id;
            
            if (!$in_id) {
                throw new Exception('Failed to create IN product. Error: ' . $wpdb->last_error);
            }
            
            // Log initial stock batch if stock > 0
            if ($stock > 0) {
                $wpdb->insert($batches_table, [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'product_id' => $in_id,
                    'batch_code' => 'INITIAL-POS-' . $sku,
                    'type' => 'stock_in',
                    'quantity' => $stock,
                    'cost_per_unit' => $cost,
                    'total_cost' => $stock * $cost,
                    'reference_number' => 'INITIAL',
                    'notes' => 'Initial stock from POS product creation',
                    'created_at' => current_time('mysql')
                ], ['%s', '%d', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s']);
            }
            
            $message = 'Product created successfully in both POS and Inventory!';
        }
        
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => $message]);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
add_action('wp_ajax_pos_save_product', 'bntm_ajax_pos_save_product');

function bntm_ajax_pos_delete_product() {
    check_ajax_referer('pos_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $pos_table = $wpdb->prefix . 'pos_products';
    $in_table = $wpdb->prefix . 'in_products';
    $product_id = intval($_POST['product_id']);
    
    // Get product
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $pos_table WHERE id = %d",
        $product_id
    ));
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found']);
    }
    
    $wpdb->query('START TRANSACTION');
    
    try {
        // Delete from IN module
        $wpdb->delete(
            $in_table,
            ['rand_id' => $product->rand_id],
            ['%s']
        );
        
        // Delete from POS module
        $wpdb->delete(
            $pos_table,
            ['id' => $product_id],
            ['%d']
        );
        
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Product deleted from both POS and Inventory']);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Failed to delete product']);
    }
}
add_action('wp_ajax_pos_delete_product', 'bntm_ajax_pos_delete_product');

function bntm_ajax_pos_toggle_product_status() {
    check_ajax_referer('pos_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'pos_products';
    $product_id = intval($_POST['product_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $result = $wpdb->update(
        $table,
        ['status' => $status],
        ['id' => $product_id],
        ['%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Status updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}
add_action('wp_ajax_pos_toggle_product_status', 'bntm_ajax_pos_toggle_product_status');
/* ---------- AJAX HANDLERS ---------- */

// Create POS Cashier role on activation
function pos_create_cashier_role() {
    if (!get_role('pos_cashier')) {
        add_role(
            'pos_cashier',
            'POS Cashier',
            [
                'read' => true,
                'pos_access' => true  // Custom capability
            ]
        );
    }
}
// Hook this to your plugin/module activation
register_activation_hook(__FILE__, 'pos_create_cashier_role');
// Modified POS add staff function
function bntm_ajax_pos_add_staff() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    // Check user limit
    $user_limit = get_option('bntm_user_limit', 0);
    if ($user_limit > 0) {
        $current_count = count(get_users(['exclude' => [1]]));
        if ($current_count >= $user_limit) {
            wp_send_json_error(['message' => "User limit reached. Maximum {$user_limit} users allowed."]);
        }
    }
    
    // Ensure role exists
    if (!get_role('pos_cashier')) {
        pos_create_cashier_role();
    }
    
    $name = sanitize_text_field($_POST['staff_name']);
    $username = sanitize_user($_POST['staff_username']);
    $email = sanitize_email($_POST['staff_email']);
    $password = $_POST['staff_password'];
    $phone = sanitize_text_field($_POST['staff_phone']);
    $pin = sanitize_text_field($_POST['staff_pin']);
    
    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        wp_send_json_error(['message' => 'Name, username, email, and password are required']);
    }
    
    if (username_exists($username)) {
        wp_send_json_error(['message' => 'Username already exists']);
    }
    
    if (email_exists($email)) {
        wp_send_json_error(['message' => 'Email already exists']);
    }
    
    // Check if PIN already exists
    if (!empty($pin)) {
        $existing_pin = get_users([
            'meta_key' => 'pos_pin',
            'meta_value' => $pin,
            'number' => 1
        ]);
        
        if (!empty($existing_pin)) {
            wp_send_json_error(['message' => 'PIN code already in use']);
        }
    }
    
    // Create user
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => 'Failed to create user: ' . $user_id->get_error_message()]);
    }
    
    // Set user role to pos_cashier (WordPress role)
    $user = new WP_User($user_id);
    $user->set_role('pos_cashier');
    
    // Update user meta
    wp_update_user([
        'ID' => $user_id,
        'display_name' => $name,
        'first_name' => $name
    ]);
    
    // Add custom meta for POS
    update_user_meta($user_id, 'pos_phone', $phone);
    update_user_meta($user_id, 'pos_pin', $pin);
    update_user_meta($user_id, 'pos_status', 'active');
    
    // Add HR role meta if HR module exists
    if (function_exists('bntm_get_hr_roles')) {
        update_user_meta($user_id, 'bntm_role', 'pos_cashier');
        update_user_meta($user_id, 'bntm_status', 'active');
    }
    
    wp_send_json_success(['message' => 'Staff member added successfully!']);
}
add_action('wp_ajax_pos_add_staff', 'bntm_ajax_pos_add_staff');
add_action('wp_ajax_pos_add_staff', 'bntm_ajax_pos_add_staff');

// Toggle staff status (activate/deactivate)
function bntm_ajax_pos_toggle_staff_status() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $staff_id = intval($_POST['staff_id']);
    $status = sanitize_text_field($_POST['status']);
    
    if (!in_array($status, ['active', 'inactive'])) {
        wp_send_json_error(['message' => 'Invalid status']);
    }
    
    $user = get_user_by('ID', $staff_id);
    
    if (!$user || !in_array('pos_cashier', $user->roles)) {
        wp_send_json_error(['message' => 'Invalid staff member']);
    }
    
    update_user_meta($staff_id, 'pos_status', $status);
    
    $message = $status === 'active' ? 'Staff member activated' : 'Staff member deactivated';
    wp_send_json_success(['message' => $message]);
}
add_action('wp_ajax_pos_toggle_staff_status', 'bntm_ajax_pos_toggle_staff_status');


/* ---------- HELPER FUNCTIONS ---------- */
// Check if user can access POS
function pos_user_can_access($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    // Get user object
    $user = get_userdata($user_id);
    
    if (!$user) {
        return false;
    }
    
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($user_id);
    $can_manage = $is_wp_admin || in_array($current_role, ['owner', 'manager']);
    
    // Admins can always access
    if ($can_manage) {
        return true;
    }
    
    // Check if user has pos_cashier role or cashier HR role and is active
    $user_roles = $user->roles ?? [];
    
    if (in_array('pos_cashier', $user_roles) || in_array($current_role, ['pos_cashier', 'cashier'])) {
        $status = get_user_meta($user_id, 'pos_status', true);
        return $status === 'active' || empty($status); // Active by default
    }
    
    return false;
}

// Get staff member by PIN (from previous artifact)
function pos_get_staff_by_pin($pin) {
    if (empty($pin)) {
        return null;
    }
    
    $users = get_users([
        'role' => 'pos_cashier',
        'meta_key' => 'pos_pin',
        'meta_value' => $pin,
        'number' => 1
    ]);
    
    if (!empty($users)) {
        $user = $users[0];
        $status = get_user_meta($user->ID, 'pos_status', true);
        
        // Only return if active
        if ($status === 'active' || empty($status)) {
            return $user;
        }
    }
    
    return null;
}
function bntm_ajax_pos_save_settings() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    bntm_set_setting('pos_tax_rate', floatval($_POST['tax_rate']));
    bntm_set_setting('pos_receipt_header', sanitize_textarea_field($_POST['receipt_header']));
    bntm_set_setting('pos_receipt_footer', sanitize_textarea_field($_POST['receipt_footer']));
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}
add_action('wp_ajax_pos_save_settings', 'bntm_ajax_pos_save_settings');

function bntm_ajax_pos_import_transaction() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $trans_table = $wpdb->prefix . 'pos_transactions';
    
    $trans_id = intval($_POST['trans_id']);
    $amount = floatval($_POST['amount']);
    
    // Check if already imported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$fn_table} WHERE reference_type='pos_sale' AND reference_id=%d",
        $trans_id
    ));
    
    if ($exists) {
        wp_send_json_error(['message' => 'Transaction already imported']);
    }
    
    // Get transaction details
    $trans = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$trans_table} WHERE id = %d",
        $trans_id
    ));
    
    if (!$trans) {
        wp_send_json_error(['message' => 'Transaction not found']);
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => 0,
        'type' => 'income',
        'amount' => $amount,
        'category' => 'Sales',
        'notes' => 'POS Sale #' . $trans->transaction_number . ' - ' . $trans->staff_name,
        'reference_type' => 'pos_sale',
        'reference_id' => $trans_id,
        'created_at' => $trans->created_at
    ];
    
    $result = $wpdb->insert($fn_table, $data);
    
    if ($result) {
        // Update cashflow summary if function exists
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Transaction imported successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to import transaction']);
    }
}
add_action('wp_ajax_pos_import_transaction', 'bntm_ajax_pos_import_transaction');

function bntm_ajax_pos_revert_transaction() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $trans_id = intval($_POST['trans_id']);
    
    $result = $wpdb->delete($table, [
        'reference_type' => 'pos_sale',
        'reference_id' => $trans_id
    ]);
    
    if ($result) {
        // Update cashflow summary if function exists
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Transaction reverted from finance']);
    } else {
        wp_send_json_error(['message' => 'Failed to revert transaction']);
    }
}
add_action('wp_ajax_pos_revert_transaction', 'bntm_ajax_pos_revert_transaction');


// AJAX handlers
add_action('wp_ajax_pos_search_product', 'bntm_ajax_pos_search_product');
add_action('wp_ajax_pos_complete_sale', 'bntm_ajax_pos_complete_sale');
add_action('wp_ajax_pos_import_products', 'bntm_ajax_pos_import_products');
add_action('wp_ajax_pos_sync_product', 'bntm_ajax_pos_sync_product');
add_action('wp_ajax_pos_toggle_product', 'bntm_ajax_pos_toggle_product');
add_action('wp_ajax_pos_add_staff', 'bntm_ajax_pos_add_staff');
add_action('wp_ajax_pos_remove_staff', 'bntm_ajax_pos_remove_staff');
add_action('wp_ajax_pos_save_settings', 'bntm_ajax_pos_save_settings');
/* ---------- POS CASHIER SHORTCODE ---------- */
/* ---------- POS CASHIER SHORTCODE ---------- */
function bntm_pos_shortcode_cashier() {
    $nonce = wp_create_nonce('pos_nonce');
    
    ob_start();
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var posNonce = '<?php echo $nonce; ?>';
    </script>
    
    <!-- PIN Login Screen -->
    <div id="pos-login-screen" class="pos-login-screen">
        <div class="pos-login-container">
            <div class="pos-login-header">
                <h2>POS Login</h2>
                <p>Enter your 6-digit PIN to access the POS system</p>
            </div>
            
            <div class="pos-pin-display">
                <div class="pos-pin-dots">
                    <span class="pos-pin-dot"></span>
                    <span class="pos-pin-dot"></span>
                    <span class="pos-pin-dot"></span>
                    <span class="pos-pin-dot"></span>
                    <span class="pos-pin-dot"></span>
                    <span class="pos-pin-dot"></span>
                </div>
            </div>
            
            <div class="pos-pin-pad">
                <button class="pos-pin-btn" data-value="1">1</button>
                <button class="pos-pin-btn" data-value="2">2</button>
                <button class="pos-pin-btn" data-value="3">3</button>
                <button class="pos-pin-btn" data-value="4">4</button>
                <button class="pos-pin-btn" data-value="5">5</button>
                <button class="pos-pin-btn" data-value="6">6</button>
                <button class="pos-pin-btn" data-value="7">7</button>
                <button class="pos-pin-btn" data-value="8">8</button>
                <button class="pos-pin-btn" data-value="9">9</button>
                <button class="pos-pin-btn pos-pin-clear" data-value="clear">Clear</button>
                <button class="pos-pin-btn" data-value="0">0</button>
                <button class="pos-pin-btn pos-pin-delete" data-value="delete">⌫</button>
            </div>
            
            <div id="pos-login-message" class="pos-login-message"></div>
            
            <div class="pos-login-alt">
                <small>Or <a href="<?php echo wp_login_url(get_permalink()); ?>">login with username</a></small>
            </div>
        </div>
    </div>
    
    <!-- Main POS Interface (hidden initially) -->
    <div id="pos-main-interface" class="pos-container" style="display: none;">
        <div class="pos-header">
            <h2>Point of Sale</h2>
            <div class="pos-header-info">
                <span id="pos-cashier-name">Cashier: Loading...</span>
                <span id="pos-time"></span>
                <button id="pos-logout-btn" class="bntm-btn-small bntm-btn-secondary">Logout</button>
            </div>
        </div>
        
        <div class="pos-main">
            <!-- Left: Product Search & Selection -->
            <div class="pos-products-section">
                <div class="pos-search-bar">
                    <input type="text" id="pos-search" placeholder="Search product by name, SKU, or barcode..." autocomplete="off">
                    <button id="pos-scan-btn" class="bntm-btn-secondary">🔍 Scan</button>
                </div>
                
                <div id="pos-search-results" class="pos-search-results"></div>
                
                <div class="pos-quick-products" id="pos-quick-products">
                    <!-- Quick access products will be loaded here -->
                </div>
            </div>
            
            <!-- Right: Cart & Payment -->
            <div class="pos-cart-section">
                <div class="pos-cart-header">
                    <h3>Current Sale</h3>
                    <button id="pos-clear-cart" class="bntm-btn-small bntm-btn-danger">Clear</button>
                </div>
                
                <div class="pos-cart-items" id="pos-cart-items">
                    <div class="pos-empty-cart">
                        <p>No items in cart</p>
                        <small>Search or scan products to add</small>
                    </div>
                </div>
                
                <div class="pos-cart-summary">
                    <div class="pos-summary-row">
                        <span>Subtotal:</span>
                        <span id="pos-subtotal">₱0.00</span>
                    </div>
                    <div class="pos-summary-row">
                        <span>Discount:</span>
                        <input type="number" id="pos-discount" value="0" min="0" step="0.01" style="width: 100px; text-align: right;">
                    </div>
                    <div class="pos-summary-row">
                        <span>Tax:</span>
                        <span id="pos-tax">₱0.00</span>
                    </div>
                    <div class="pos-summary-row pos-total">
                        <strong>TOTAL:</strong>
                        <strong id="pos-total">₱0.00</strong>
                    </div>
                </div>
                
                <div class="pos-payment-section">
                    <button id="pos-complete-sale" class="bntm-btn-primary bntm-btn-large" disabled>
                        Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="pos-payment-modal" class="pos-modal" style="display: none;">
        <div class="pos-modal-overlay"></div>
        <div class="pos-modal-content pos-login-container" style="max-width: 550px;">
            <div class="pos-modal-header">
                <h2>Complete Payment</h2>
                <button class="pos-modal-close" id="pos-modal-close">×</button>
            </div>
            
            <div class="pos-modal-body">
                <!-- Order Summary -->
                <div class="pos-payment-summary">
                    <div class="pos-summary-row">
                        <span>Subtotal:</span>
                        <strong id="modal-subtotal">₱0.00</strong>
                    </div>
                    <div class="pos-summary-row">
                        <span>Discount:</span>
                        <strong id="modal-discount">₱0.00</strong>
                    </div>
                    <div class="pos-summary-row">
                        <span>Tax:</span>
                        <strong id="modal-tax">₱0.00</strong>
                    </div>
                    <div class="pos-summary-row pos-total">
                        <strong>TOTAL:</strong>
                        <strong id="modal-total">₱0.00</strong>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="bntm-form-group" style="margin-top: 20px;">
                    <label>Payment Method</label>
                    <select id="modal-payment-method" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-size: 16px;">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="gcash">GCash</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                
                <!-- Amount Display & Denominators (only for cash) -->
                <div id="modal-cash-section">
                    <!-- Denomination Buttons -->
                    <div style="margin-top: 20px;">
                        <label style="display: block; margin-bottom: 10px; font-size: 14px; font-weight: 600; color: #6b7280;">Quick Amount</label>
                        <div class="pos-denominations">
                            <button class="pos-denomination-btn" data-value="1">₱1</button>
                            <button class="pos-denomination-btn" data-value="5">₱5</button>
                            <button class="pos-denomination-btn" data-value="10">₱10</button>
                            <button class="pos-denomination-btn" data-value="20">₱20</button>
                            <button class="pos-denomination-btn" data-value="50">₱50</button>
                            <button class="pos-denomination-btn" data-value="100">₱100</button>
                            <button class="pos-denomination-btn" data-value="500">₱500</button>
                            <button class="pos-denomination-btn" data-value="1000">₱1000</button>
                            <button class="pos-denomination-btn pos-denomination-exact" data-value="exact">Exact</button>
                            <button class="pos-denomination-btn pos-denomination-clear" data-value="clear">Clear</button>
                        </div>
                    </div>
                    
                    <!-- Amount Input -->
                    <div style="margin-top: 20px;">
                        <label style="display: block; margin-bottom: 10px; font-size: 14px; font-weight: 600; color: #6b7280;">Amount Received</label>
                        <input type="text" id="modal-amount-input" 
                               style="width: 100%; padding: 15px; font-size: 24px; font-weight: 700; text-align: center; border: 2px solid #e5e7eb; border-radius: 8px;"
                               placeholder="0.00">
                        <div id="modal-change-display" style="text-align: center; margin-top: 10px; font-size: 16px; font-weight: 600; min-height: 24px;"></div>
                    </div>
                    
                    <!-- Numpad Toggle Button -->
                    <div style="text-align: center; margin-top: 15px;">
                        <button id="toggle-numpad-btn" class="bntm-btn-secondary" style="padding: 8px 20px; font-size: 14px;">
                            Show Numpad
                        </button>
                    </div>
                    
                    <!-- Numpad (hidden initially) -->
                    <div id="modal-numpad-section" style="display: none; margin-top: 15px;">
                        <div class="pos-pin-pad">
                            <button class="pos-pin-btn modal-numpad-btn" data-value="1">1</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="2">2</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="3">3</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="4">4</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="5">5</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="6">6</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="7">7</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="8">8</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="9">9</button>
                            <button class="pos-pin-btn pos-pin-clear modal-numpad-btn" data-value="clear">Clear</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value="0">0</button>
                            <button class="pos-pin-btn modal-numpad-btn" data-value=".">.</button>
                        </div>
                    </div>
                </div>
                
                <!-- Non-cash payment message -->
                <div id="modal-noncash-section" style="display: none; margin-top: 20px; padding: 20px; background: #f0fdf4; border: 2px solid #86efac; border-radius: 8px; text-align: center;">
                    <p style="margin: 0; color: #059669; font-weight: 600;">
                        Exact amount will be charged
                    </p>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button id="pos-modal-cancel" class="bntm-btn-secondary" style="flex: 1; padding: 14px; font-size: 16px;">
                        Cancel
                    </button>
                    <button id="pos-modal-confirm" class="bntm-btn-primary" style="flex: 1; padding: 14px; font-size: 16px; font-weight: 600;">
                        Confirm Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    /* Login Screen Styles */
    .pos-login-screen {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--bntm-primary, #667eea) 0%, var(--bntm-primary-hover, var(--bntm-primary, #764ba2)) 100%);
    }
    .pos-login-container {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        max-width: 400px;
        width: 90%;
    }
    .pos-login-container.locked {
        opacity: 0.6;
        pointer-events: none;
    }
    .pos-login-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .pos-login-header h2 {
        margin: 0 0 10px 0;
        color: #1f2937;
        font-size: 28px;
    }
    .pos-login-header p {
        margin: 0;
        color: #6b7280;
    }
    .pos-pin-display {
        background: #f9fafb;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    .pos-pin-dots {
        display: flex;
        justify-content: center;
        gap: 12px;
    }
    .pos-pin-dot {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #d1d5db;
        background: white;
        transition: all 0.2s;
    }
    .pos-pin-dot.filled {
        background: var(--bntm-primary, #3b82f6);
        border-color: var(--bntm-primary, #3b82f6);
    }
    .pos-pin-pad {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .pos-pin-btn {
        padding: 20px;
        font-size: 24px;
        font-weight: 600;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .pos-pin-btn:hover {
        background: #f3f4f6;
        transform: translateY(-2px);
        border-color: var(--bntm-primary, #3b82f6);
    }
    .pos-pin-btn:active {
        transform: translateY(0);
    }
    .pos-pin-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .pos-pin-clear {
        background: #fef3c7;
        border-color: #fbbf24;
    }
    .pos-pin-clear:hover {
        background: #fde68a;
        border-color: #f59e0b;
    }
    .pos-pin-delete {
        background: #fee2e2;
        border-color: #ef4444;
    }
    .pos-pin-delete:hover {
        background: #fecaca;
        border-color: #dc2626;
    }
    .pos-login-message {
        min-height: 24px;
        text-align: center;
        font-size: 14px;
        margin-bottom: 10px;
    }
    .pos-login-message.error {
        color: #dc2626;
    }
    .pos-login-message.success {
        color: #059669;
    }
    .pos-login-alt {
        text-align: center;
        color: #6b7280;
    }
    .pos-login-alt a {
        color: #3b82f6;
        text-decoration: none;
    }
    
    /* Denomination Buttons */
    .pos-denominations {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
    }
    .pos-denomination-btn {
        padding: 12px 8px;
        font-size: 14px;
        font-weight: 600;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        color: #1f2937;
    }
    .pos-denomination-btn:hover {
        background: #f3f4f6;
        transform: translateY(-2px);
        border-color: var(--bntm-primary, #3b82f6);
    }
    .pos-denomination-btn:active {
        transform: translateY(0);
    }
    .pos-denomination-exact {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #1e40af;
    }
    .pos-denomination-exact:hover {
        background: #bfdbfe;
        border-color: #2563eb;
    }
    .pos-denomination-clear {
        background: #fee2e2;
        border-color: #ef4444;
        color: #991b1b;
    }
    .pos-denomination-clear:hover {
        background: #fecaca;
        border-color: #dc2626;
    }
    
    /* Modal Styles */
    .pos-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .pos-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
    }
    .pos-modal-content {
        position: relative;
        z-index: 10001;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .pos-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e5e7eb;
    }
    .pos-modal-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1f2937;
    }
    .pos-modal-close {
        background: none;
        border: none;
        font-size: 32px;
        color: #9ca3af;
        cursor: pointer;
        line-height: 1;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .pos-modal-close:hover {
        background: #f3f4f6;
        color: #1f2937;
    }
    .pos-payment-summary {
        background: #f9fafb;
        padding: 20px;
        border-radius: 12px;
        border: 2px solid #e5e7eb;
    }
    .pos-payment-summary .pos-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        font-size: 16px;
        color: #4b5563;
    }
    .pos-payment-summary .pos-summary-row.pos-total {
        margin-top: 10px;
        padding-top: 15px;
        border-top: 2px solid #d1d5db;
        font-size: 22px;
        color: #1f2937;
    }
    
    /* Main POS Styles */
    .pos-container {
        max-width: 100%;
        background: #f9fafb;
        min-height: 100vh;
    }
    .pos-header {
        background: white;
        padding: 20px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pos-header h2 {
        margin: 0;
    }
    .pos-header-info {
        display: flex;
        gap: 20px;
        align-items: center;
        color: #6b7280;
    }
    .pos-main {
        display: grid;
        grid-template-columns: 1fr 450px;
        gap: 0;
        height: calc(100vh - 80px);
    }
    .pos-products-section {
        padding: 20px;
        overflow-y: auto;
    }
    .pos-search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .pos-search-bar input {
        flex: 1;
        padding: 12px;
        font-size: 16px;
        border: 2px solid #e5e7eb;
        border-radius: 6px;
    }
    .pos-search-results {
        background: white;
        border-radius: 8px;
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 20px;
        display: none;
    }
    .pos-product-item {
        padding: 12px;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: background 0.2s;
    }
    .pos-product-item:hover {
        background: #f9fafb;
    }
    .pos-product-item:last-child {
        border-bottom: none;
    }
    .pos-product-name {
        font-weight: 600;
        color: #1f2937;
    }
    .pos-product-info {
        display: flex;
        justify-content: space-between;
        margin-top: 4px;
        font-size: 14px;
        color: #6b7280;
    }
    .pos-quick-products {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
    }
    .pos-quick-product {
        background: white;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        border: 2px solid transparent;
    }
    .pos-quick-product:hover {
        border-color: var(--bntm-primary, #3b82f6);
        transform: translateY(-2px);
    }
    .pos-quick-product-name {
        font-weight: 600;
        margin-bottom: 8px;
        color: #1f2937;
    }
    .pos-quick-product-price {
        color: var(--bntm-primary, #059669);
        font-size: 18px;
        font-weight: 700;
    }
    .pos-quick-product-stock {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    .pos-cart-section {
        background: white;
        border-left: 2px solid #e5e7eb;
        display: flex;
        flex-direction: column;
    }
    .pos-cart-header {
        padding: 20px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pos-cart-header h3 {
        margin: 0;
    }
    .pos-cart-items {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
    }
    .pos-empty-cart {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
    }
    .pos-cart-item {
        background: #f9fafb;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 10px;
    }
    .pos-cart-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    .pos-cart-item-name {
        font-weight: 600;
        color: #1f2937;
    }
    .pos-cart-item-remove {
        color: #dc2626;
        cursor: pointer;
        font-size: 18px;
    }
    .pos-cart-item-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pos-qty-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pos-qty-btn {
        width: 30px;
        height: 30px;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
    }
    .pos-qty-value {
        min-width: 40px;
        text-align: center;
        font-weight: 600;
    }
    .pos-cart-item-total {
        font-weight: 700;
        color: #059669;
    }
    .pos-cart-summary {
        padding: 20px;
        border-top: 2px solid #e5e7eb;
        border-bottom: 2px solid #e5e7eb;
    }
    .pos-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 15px;
    }
    .pos-summary-row.pos-total {
        font-size: 20px;
        color: #1f2937;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
        margin-top: 8px;
    }
    .pos-payment-section {
        padding: 20px;
    }
    .bntm-btn-large {
        width: 100%;
        padding: 16px;
        font-size: 18px;
        font-weight: 600;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let currentUserId = null;
        let currentUserName = '';
        let pinValue = '';
        let cart = [];
        let failedAttempts = 0;
        let isLocked = false;
        const MAX_ATTEMPTS = 5;
        const LOCKOUT_TIME = 300000; // 5 minutes in milliseconds
        const PIN_LENGTH = 6;
        const taxRate = parseFloat('<?php echo bntm_get_setting("pos_tax_rate", "0"); ?>');
        
        // Check if already logged in via WordPress
        $.post(ajaxurl, {
            action: 'pos_check_session',
            nonce: posNonce
        }, function(response) {
            if (response.success && response.data.logged_in) {
                currentUserId = response.data.user_id;
                currentUserName = response.data.user_name;
                showPOSInterface();
            }
        });
        
        // PIN pad functionality
        $('.pos-pin-btn').not('.modal-numpad-btn').on('click', function() {
            if (isLocked) return;
            
            const value = $(this).data('value');
            
            if (value === 'clear') {
                pinValue = '';
                updatePinDisplay();
                $('#pos-login-message').text('').removeClass('error success');
            } else if (value === 'delete') {
                pinValue = pinValue.slice(0, -1);
                updatePinDisplay();
            } else {
                // Only add digit if we haven't reached PIN_LENGTH
                if (pinValue.length < PIN_LENGTH) {
                    pinValue += value;
                    updatePinDisplay();
                    
                    // Auto-submit ONLY when PIN reaches exactly 6 digits
                    if (pinValue.length === PIN_LENGTH) {
                        setTimeout(function() {
                            verifyPin();
                        }, 200); // Small delay for UX
                    }
                }
            }
        });
        
        function updatePinDisplay() {
            $('.pos-pin-dot').each(function(index) {
                $(this).toggleClass('filled', index < pinValue.length);
            });
        }
        
        function lockSystem() {
            isLocked = true;
            pinValue = '';
            updatePinDisplay();
            $('.pos-login-container').addClass('locked');
            $('.pos-pin-btn').not('.modal-numpad-btn').prop('disabled', true);
            
            $('#pos-login-message')
                .text('Too many failed attempts. System locked for 5 minutes.')
                .removeClass('success').addClass('error');
            
            setTimeout(function() {
                unlockSystem();
            }, LOCKOUT_TIME);
        }
        
        function unlockSystem() {
            isLocked = false;
            failedAttempts = 0;
            $('.pos-login-container').removeClass('locked');
            $('.pos-pin-btn').not('.modal-numpad-btn').prop('disabled', false);
            $('#pos-login-message')
                .text('System unlocked. You may try again.')
                .removeClass('error').addClass('success');
            
            setTimeout(function() {
                $('#pos-login-message').text('').removeClass('success');
            }, 3000);
        }
        
        function verifyPin() {
            if (isLocked) return;
            
            // Double check PIN length before verifying
            if (pinValue.length !== PIN_LENGTH) {
                $('#pos-login-message')
                    .text('Please enter exactly 6 digits')
                    .removeClass('success').addClass('error');
                return;
            }
            
            $('#pos-login-message').text('Verifying...').removeClass('error').addClass('success');
            
            $.post(ajaxurl, {
                action: 'pos_verify_pin',
                nonce: posNonce,
                pin: pinValue
            }, function(response) {
                if (response.success) {
                    // Reset failed attempts on success
                    failedAttempts = 0;
                    currentUserId = response.data.user_id;
                    currentUserName = response.data.user_name;
                    $('#pos-login-message').text('Access granted!').removeClass('error').addClass('success');
                    setTimeout(showPOSInterface, 500);
                } else {
                    // Increment failed attempts
                    failedAttempts++;
                    const attemptsLeft = MAX_ATTEMPTS - failedAttempts;
                    
                    if (failedAttempts >= MAX_ATTEMPTS) {
                        lockSystem();
                    } else {
                        $('#pos-login-message')
                            .text(response.data.message + ' (' + attemptsLeft + ' attempt' + (attemptsLeft !== 1 ? 's' : '') + ' remaining)')
                            .removeClass('success').addClass('error');
                    }
                    
                    pinValue = '';
                    updatePinDisplay();
                }
            }).fail(function() {
                $('#pos-login-message')
                    .text('Connection error. Please try again.')
                    .removeClass('success').addClass('error');
                pinValue = '';
                updatePinDisplay();
            });
        }
        
        function showPOSInterface() {
            $('#pos-login-screen').fadeOut(300, function() {
                $('#pos-main-interface').fadeIn(300);
                $('#pos-cashier-name').text('Cashier: ' + currentUserName);
                loadQuickProducts();
                updateTime();
                setInterval(updateTime, 1000);
            });
        }
        
        // Logout
        $('#pos-logout-btn').on('click', function() {
            if (cart.length > 0) {
                if (!confirm('You have items in cart. Are you sure you want to logout?')) {
                    return;
                }
            }
            
            currentUserId = null;
            currentUserName = '';
            cart = [];
            pinValue = '';
            failedAttempts = 0;
            isLocked = false;
            updatePinDisplay();
            
            $('#pos-main-interface').fadeOut(300, function() {
                $('#pos-login-screen').fadeIn(300);
                $('#pos-login-message').text('').removeClass('error success');
                $('.pos-login-container').removeClass('locked');
                $('.pos-pin-btn').not('.modal-numpad-btn').prop('disabled', false);
            });
        });
        
        // Update time
        function updateTime() {
            const now = new Date();
            $('#pos-time').text(now.toLocaleTimeString());
        }
        
        // Load quick products
        function loadQuickProducts() {
            $.get(ajaxurl + '?action=pos_search_product&q=', function(products) {
                const container = $('#pos-quick-products');
                if (products.length === 0) {
                    container.html('<p style="text-align:center;color:#9ca3af;padding:20px;">No products available</p>');
                    return;
                }
                
                container.html(products.slice(0, 12).map(p => `
                    <div class="pos-quick-product" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}" data-stock="${p.stock}">
                        <div class="pos-quick-product-name">${p.name}</div>
                        <div class="pos-quick-product-price">₱${parseFloat(p.price).toFixed(2)}</div>
                        <div class="pos-quick-product-stock">${p.stock} in stock</div>
                    </div>
                `).join(''));
                
                $('.pos-quick-product').on('click', function() {
                    addToCart({
                        id: $(this).data('id'),
                        name: $(this).data('name'),
                        price: parseFloat($(this).data('price')),
                        stock: parseInt($(this).data('stock'))
                    });
                });
            });
        }
        
        // Product search
        let searchTimeout;
        $('#pos-search').on('input', function() {
            clearTimeout(searchTimeout);
            const query = $(this).val().trim();
            
            if (query.length < 2) {
                $('.pos-search-results').hide();
                return;
            }
            
            searchTimeout = setTimeout(() => {
                $.get(ajaxurl + '?action=pos_search_product&q=' + encodeURIComponent(query), function(products) {
                    const resultsDiv = $('.pos-search-results');
                    
                    if (products.length === 0) {
                        resultsDiv.html('<div style="padding:20px;text-align:center;color:#9ca3af;">No products found</div>');
                    } else {
                        resultsDiv.html(products.map(p => `
                            <div class="pos-product-item" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}" data-stock="${p.stock}">
                                <div class="pos-product-name">${p.name}</div>
                                <div class="pos-product-info">
                                    <span>₱${parseFloat(p.price).toFixed(2)}</span>
                                    <span>${p.stock} in stock</span>
                                </div>
                            </div>
                        `).join(''));
                        
                        $('.pos-product-item').on('click', function() {
                            addToCart({
                                id: $(this).data('id'),
                                name: $(this).data('name'),
                                price: parseFloat($(this).data('price')),
                                stock: parseInt($(this).data('stock'))
                            });
                            $('#pos-search').val('');
                            resultsDiv.hide();
                        });
                    }
                    resultsDiv.show();
                });
            }, 300);
        });
        
        // Add to cart
        function addToCart(product) {
            const existing = cart.find(item => item.id === product.id);
            
            if (existing) {
                if (existing.quantity >= product.stock) {
                    alert('Not enough stock available');
                    return;
                }
                existing.quantity++;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    quantity: 1,
                    stock: product.stock
                });
            }
            
            renderCart();
        }
        
        // Render cart
        function renderCart() {
            const container = $('#pos-cart-items');
            
            if (cart.length === 0) {
                container.html('<div class="pos-empty-cart"><p>No items in cart</p><small>Search or scan products to add</small></div>');
                updateSummary();
                return;
            }
            
            container.html(cart.map((item, index) => `
                <div class="pos-cart-item">
                    <div class="pos-cart-item-header">
                        <span class="pos-cart-item-name">${item.name}</span>
                        <span class="pos-cart-item-remove" data-index="${index}">×</span>
                    </div>
                    <div class="pos-cart-item-controls">
                        <div class="pos-qty-controls">
                            <button class="pos-qty-btn pos-qty-minus" data-index="${index}">−</button>
                            <span class="pos-qty-value">${item.quantity}</span>
                            <button class="pos-qty-btn pos-qty-plus" data-index="${index}">+</button>
                        </div>
                        <span class="pos-cart-item-total">₱${(item.price * item.quantity).toFixed(2)}</span>
                    </div>
                </div>
            `).join(''));
            
            $('.pos-cart-item-remove').on('click', function() {
                cart.splice(parseInt($(this).data('index')), 1);
                renderCart();
            });
            
            $('.pos-qty-minus').on('click', function() {
                const index = parseInt($(this).data('index'));
                if (cart[index].quantity > 1) {
                    cart[index].quantity--;
                    renderCart();
                }
            });
            
            $('.pos-qty-plus').on('click', function() {
                const index = parseInt($(this).data('index'));
                if (cart[index].quantity < cart[index].stock) {
                    cart[index].quantity++;
                    renderCart();
                } else {
                    alert('Not enough stock available');
                }
            });
            
            updateSummary();
        }
        
        // Update summary
        function updateSummary() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const discount = parseFloat($('#pos-discount').val()) || 0;
            const taxAmount = (subtotal - discount) * (taxRate / 100);
            const total = subtotal - discount + taxAmount;
            
            $('#pos-subtotal').text('₱' + subtotal.toFixed(2));
            $('#pos-tax').text('₱' + taxAmount.toFixed(2));
            $('#pos-total').text('₱' + total.toFixed(2));
            
            $('#pos-complete-sale').prop('disabled', cart.length === 0);
        }
        
        $('#pos-discount').on('input', updateSummary);
        
        // Clear cart
        $('#pos-clear-cart').on('click', function() {
            if (cart.length === 0) return;
            if (confirm('Clear all items from cart?')) {
                cart = [];
                renderCart();
            }
        });
        
        // Modal numpad functionality
        $('.modal-numpad-btn').on('click', function() {
            const value = $(this).data('value');
            const input = $('#modal-amount-input');
            let currentValue = input.val().replace(/[^0-9.]/g, '');
            
            if (value === 'clear') {
                input.val('');
                updateModalChange();
            } else if (value === '.') {
                // Only add decimal if not already present
                if (!currentValue.includes('.')) {
                    currentValue = currentValue || '0';
                    input.val(currentValue + '.');
                }
            } else {
                // Add digit
                if (currentValue === '' || currentValue === '0') {
                    currentValue = value;
                } else {
                    currentValue += value;
                }
                input.val(currentValue);
            }
            
            updateModalChange();
        });
        
        // Allow manual typing in amount input
        $('#modal-amount-input').on('input', function() {
            // Only allow numbers and one decimal point
            let value = $(this).val();
            value = value.replace(/[^0-9.]/g, '');
            
            // Ensure only one decimal point
            const parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }
            
            $(this).val(value);
            updateModalChange();
        });
        
        function updateModalChange() {
            const total = parseFloat($('#modal-total').text().replace('₱', ''));
            const paid = parseFloat($('#modal-amount-input').val()) || 0;
            const change = paid - total;
            
            const changeDisplay = $('#modal-change-display');
            if (paid > 0) {
                if (change >= 0) {
                    changeDisplay.text(`Change: ₱${change.toFixed(2)}`);
                    changeDisplay.css('color', '#059669');
                } else {
                    changeDisplay.text(`Short: ₱${Math.abs(change).toFixed(2)}`);
                    changeDisplay.css('color', '#dc2626');
                }
            } else {
                changeDisplay.text('');
            }
        }
        
        // Denomination buttons
        $('.pos-denomination-btn').on('click', function() {
            const value = $(this).data('value');
            const input = $('#modal-amount-input');
            const total = parseFloat($('#modal-total').text().replace('₱', ''));
            
            if (value === 'exact') {
                input.val(total.toFixed(2));
            } else if (value === 'clear') {
                input.val('');
            } else {
                const currentValue = parseFloat(input.val()) || 0;
                const newValue = currentValue + parseFloat(value);
                input.val(newValue.toFixed(2));
            }
            
            updateModalChange();
        });
        
        // Toggle numpad
        $('#toggle-numpad-btn').on('click', function() {
            const numpadSection = $('#modal-numpad-section');
            const isVisible = numpadSection.is(':visible');
            
            if (isVisible) {
                numpadSection.slideUp(300);
                $(this).text('Show Numpad');
            } else {
                numpadSection.slideDown(300);
                $(this).text('Hide Numpad');
            }
        });
        
        // Payment method change in modal
        $('#modal-payment-method').on('change', function() {
            const isCash = $(this).val() === 'cash';
            $('#modal-cash-section').toggle(isCash);
            $('#modal-noncash-section').toggle(!isCash);
            
            if (!isCash) {
                $('#modal-amount-input').val('');
                $('#modal-change-display').text('');
                $('#modal-numpad-section').hide();
                $('#toggle-numpad-btn').text('Show Numpad');
            }
        });
        
        // Open payment modal
        $('#pos-complete-sale').on('click', function() {
            if (cart.length === 0) return;
            
            const subtotal = parseFloat($('#pos-subtotal').text().replace('₱', ''));
            const discount = parseFloat($('#pos-discount').val()) || 0;
            const tax = parseFloat($('#pos-tax').text().replace('₱', ''));
            const total = parseFloat($('#pos-total').text().replace('₱', ''));
            
            // Populate modal
            $('#modal-subtotal').text('₱' + subtotal.toFixed(2));
            $('#modal-discount').text('₱' + discount.toFixed(2));
            $('#modal-tax').text('₱' + tax.toFixed(2));
            $('#modal-total').text('₱' + total.toFixed(2));
            
            // Reset modal
            $('#modal-amount-input').val('');
            $('#modal-change-display').text('');
            $('#modal-payment-method').val('cash');
            $('#modal-cash-section').show();
            $('#modal-noncash-section').hide();
            $('#modal-numpad-section').hide();
            $('#toggle-numpad-btn').text('Show Numpad');
            
            // Show modal
            $('#pos-payment-modal').fadeIn(300);
        });
        
        // Close modal
        function closeModal() {
            $('#pos-payment-modal').fadeOut(300);
        }
        
        $('#pos-modal-close, #pos-modal-cancel').on('click', closeModal);
        
        $('.pos-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Prevent closing when clicking inside modal content
        $('.pos-modal-content').on('click', function(e) {
            e.stopPropagation();
        });
        // Confirm payment
$('#pos-modal-confirm').on('click', function() {
    const paymentMethod = $('#modal-payment-method').val();
    const total = parseFloat($('#modal-total').text().replace('₱', ''));
    const amountPaid = paymentMethod === 'cash' ? 
        (parseFloat($('#modal-amount-input').val()) || 0) : total;
    
    if (paymentMethod === 'cash' && amountPaid < total) {
        alert('Insufficient payment amount');
        return;
    }
    
    if (!confirm('Confirm this payment?')) return;
    
    const $btn = $(this);
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Processing...');
    
    $.post(ajaxurl, {
        action: 'pos_complete_sale',
        nonce: posNonce,
        user_id: currentUserId,
        cart: JSON.stringify(cart),
        subtotal: $('#modal-subtotal').text().replace('₱', ''),
        discount: $('#pos-discount').val(),
        tax: $('#modal-tax').text().replace('₱', ''),
        total: total,
        payment_method: paymentMethod,
        amount_paid: amountPaid,
        change_amount: amountPaid - total
    })
    .done(function(response) {
        console.log('AJAX Response:', response); // ✅ Log full response
        if (response.success) {
            alert('Sale completed successfully!\nTransaction #' + response.data.transaction_number);
            cart = [];
            renderCart();
            $('#pos-discount').val(0);
            loadQuickProducts();
            closeModal();
        } else {
            console.error('Server Error:', response.data); // ✅ Log server-side error details
            alert('Error: ' + (response.data?.message || 'Unknown error occurred.'));
        }
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX Error:', {
            status: jqXHR.status,
            statusText: jqXHR.statusText,
            responseText: jqXHR.responseText,
            textStatus: textStatus,
            errorThrown: errorThrown
        }); // ✅ Log detailed error info

        alert('Connection error. Please check console for details.');
    })
    .always(function() {
        $btn.prop('disabled', false).text(originalText);
    });
});

    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLERS ---------- */
// Check if user is already logged in via WordPress
function bntm_ajax_pos_check_session() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        
        // Check if user can access POS
        if (pos_user_can_access($user->ID)) {
            wp_send_json_success([
                'logged_in' => true,
                'user_id' => $user->ID,
                'user_name' => $user->display_name
            ]);
        }
    }
    
    wp_send_json_success(['logged_in' => false]);
}
add_action('wp_ajax_pos_check_session', 'bntm_ajax_pos_check_session');
add_action('wp_ajax_nopriv_pos_check_session', 'bntm_ajax_pos_check_session');

// Verify PIN and return user info
function bntm_ajax_pos_verify_pin() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    $pin = sanitize_text_field($_POST['pin']);
    
    // PIN must be exactly 6 digits
    if (empty($pin) || strlen($pin) !== 6 || !ctype_digit($pin)) {
        wp_send_json_error(['message' => 'PIN must be exactly 6 digits']);
    }
    
    // Get user by PIN
    $user = pos_get_staff_by_pin($pin);
    
    if (!$user) {
        wp_send_json_error(['message' => 'Invalid PIN. Please try again.']);
    }
    
    // Check if user is active
    $status = get_user_meta($user->ID, 'pos_status', true);
    if ($status === 'inactive') {
        wp_send_json_error(['message' => 'Account is inactive. Please contact administrator.']);
    }
    
    wp_send_json_success([
        'user_id' => $user->ID,
        'user_name' => $user->display_name
    ]);
}
add_action('wp_ajax_pos_verify_pin', 'bntm_ajax_pos_verify_pin');
add_action('wp_ajax_nopriv_pos_verify_pin', 'bntm_ajax_pos_verify_pin');

// Search products
function bntm_ajax_pos_search_product() {
    global $wpdb;
    $table = $wpdb->prefix . 'pos_products';
    $query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    
    if (empty($query)) {
        // Return all active products
        $products = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' AND stock > 0 ORDER BY name ASC LIMIT 20"
        );
    } else {
        // Search products
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE status = 'active' AND stock > 0 
            AND (name LIKE %s OR sku LIKE %s OR barcode LIKE %s) 
            ORDER BY name ASC LIMIT 20",
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%',
            '%' . $wpdb->esc_like($query) . '%'
        ));
    }
    
    wp_send_json($products);
}
add_action('wp_ajax_pos_search_product', 'bntm_ajax_pos_search_product');
add_action('wp_ajax_nopriv_pos_search_product', 'bntm_ajax_pos_search_product');
// Complete sale
function bntm_ajax_pos_complete_sale() {
    check_ajax_referer('pos_nonce', 'nonce');
    
    // Get user ID from POST (from PIN login)
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error(['message' => 'Unauthorized - No user ID']);
    }
    
    // Verify user can access POS
    if (!pos_user_can_access($user_id)) {
        wp_send_json_error(['message' => 'Unauthorized - No POS access']);
    }
    
    global $wpdb;
    $trans_table = $wpdb->prefix . 'pos_transactions';
    $items_table = $wpdb->prefix . 'pos_transaction_items';
    $products_table = $wpdb->prefix . 'pos_products';
    $inventory_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    
    $cart = json_decode(stripslashes($_POST['cart']), true);
    $subtotal = floatval($_POST['subtotal']);
    $discount = floatval($_POST['discount']);
    $tax = floatval($_POST['tax']);
    $total = floatval($_POST['total']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $amount_paid = floatval($_POST['amount_paid']);
    $change_amount = floatval($_POST['change_amount']);
    
    if (empty($cart)) {
        wp_send_json_error(['message' => 'Cart is empty']);
    }
    
    // Generate transaction number
    $transaction_number = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Get user info
    $user = get_user_by('ID', $user_id);
    $staff_name = $user ? $user->display_name : 'Unknown';
    
    // Determine business_id from first product in cart
    $first_product_id = $cart[0]['id'] ?? 0;
    $business_id = 0;
    if ($first_product_id) {
        $pos_product = $wpdb->get_row($wpdb->prepare(
            "SELECT rand_id FROM {$products_table} WHERE id = %d LIMIT 1",
            $first_product_id
        ));
        
        if ($pos_product && $pos_product->rand_id) {
            $business_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT business_id FROM {$inventory_table} WHERE rand_id = %s LIMIT 1",
                $pos_product->rand_id
            ));
        }
    }
    
    // Start DB transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // **VALIDATE STOCK AVAILABILITY FIRST**
        foreach ($cart as $item) {
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$products_table} WHERE id = %d AND status = 'active' LIMIT 1",
                $item['id']
            ));
            
            if (!$product) {
                throw new Exception("Product ID {$item['id']} not found or inactive.");
            }
            
            if ($product->stock < $item['quantity']) {
                throw new Exception("Insufficient stock for {$product->name}. Available: {$product->stock}, Requested: {$item['quantity']}");
            }
        }
        
        // Insert transaction
        $result = $wpdb->insert($trans_table, [
            'rand_id' => bntm_rand_id(),
            'transaction_number' => $transaction_number,
            'staff_id' => $user_id,
            'staff_name' => $staff_name,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'payment_method' => $payment_method,
            'amount_paid' => $amount_paid,
            'change_amount' => $change_amount,
            'status' => 'completed',
            'created_at' => current_time('mysql')
        ], ['%s', '%s', '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%f', '%f', '%s', '%s']);
        
        if (!$result) {
            throw new Exception('Failed to create transaction');
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Process each cart item
        foreach ($cart as $item) {
            // Get POS product
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$products_table} WHERE id = %d LIMIT 1",
                $item['id']
            ));
            
            // Insert transaction item
            $item_inserted = $wpdb->insert($items_table, [
                'transaction_id' => $transaction_id,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'discount' => 0,
                'subtotal' => $item['price'] * $item['quantity']
            ], ['%d', '%d', '%s', '%d', '%f', '%f', '%f']);
            
            if (!$item_inserted) {
                throw new Exception('Failed to insert transaction items.');
            }
            
            // **UPDATE BOTH INVENTORY TABLES & LOG BATCH**
            $inventory_product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$inventory_table} WHERE rand_id = %s LIMIT 1",
                $product->rand_id
            ));
            
            if ($inventory_product) {
                // Verify sufficient stock in inventory
                if ($inventory_product->stock_quantity < $item['quantity']) {
                    throw new Exception("Insufficient inventory stock for {$product->name}. Available: {$inventory_product->stock_quantity}, Requested: {$item['quantity']}");
                }
                
                // Calculate new stock
                $new_stock = max(0, $inventory_product->stock_quantity - $item['quantity']);
                
                // Update in_products stock
                $in_stock_updated = $wpdb->update(
                    $inventory_table,
                    ['stock_quantity' => $new_stock],
                    ['id' => $inventory_product->id],
                    ['%d'],
                    ['%d']
                );
                
                if ($in_stock_updated === false) {
                    throw new Exception("Failed to update inventory stock for {$product->name}. MySQL Error: " . $wpdb->last_error);
                }
                
                // Update pos_products stock to match
                $pos_stock_updated = $wpdb->update(
                    $products_table,
                    ['stock' => $new_stock],
                    ['rand_id' => $product->rand_id],
                    ['%d'],
                    ['%s']
                );
                
                if ($pos_stock_updated === false) {
                    throw new Exception("Failed to update POS product stock for {$product->name}. MySQL Error: " . $wpdb->last_error);
                }
                
                // Log batch as stock_out with 0 cost
                $batch_inserted = $wpdb->insert($batches_table, [
                    'rand_id'          => bntm_rand_id(),
                    'business_id'      => $business_id,
                    'product_id'       => $inventory_product->id,
                    'batch_code'       => 'POS-' . $transaction_number,
                    'type'             => 'stock_out',
                    'reference_number' => $transaction_number,
                    'quantity'         => $item['quantity'],
                    'cost_per_unit'    => 0.00,
                    'total_cost'       => 0.00,
                    'notes'            => "POS Sale - Transaction: {$transaction_number}, Staff: {$staff_name}",
                    'created_at'       => current_time('mysql')
                ], [
                    '%s','%d','%d','%s','%s','%s','%d','%f','%f','%s','%s'
                ]);
                
                if (!$batch_inserted) {
                    error_log("Failed to insert batch for product: {$product->name}");
                    error_log("MySQL Error: " . $wpdb->last_error);
                    throw new Exception("Failed to log inventory batch for {$product->name}. MySQL Error: " . $wpdb->last_error);
                }
            } else {
                // Inventory product not found - still update POS stock
                error_log("Warning: Inventory product not found for rand_id: {$product->rand_id}. Updating POS stock only.");
                
                $pos_stock_updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$products_table} SET stock = stock - %d WHERE id = %d AND stock >= %d",
                    $item['quantity'], $product->id, $item['quantity']
                ));
                
                if ($pos_stock_updated === false || $pos_stock_updated === 0) {
                    throw new Exception("Failed to update POS product stock for {$product->name}");
                }
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        wp_send_json_success([
            'message' => 'Sale completed successfully!',
            'transaction_number' => $transaction_number,
            'transaction_id' => $transaction_id
        ]);
        
    } catch (Exception $e) {
        // Rollback on any error
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
add_action('wp_ajax_pos_complete_sale', 'bntm_ajax_pos_complete_sale');
add_action('wp_ajax_nopriv_pos_complete_sale', 'bntm_ajax_pos_complete_sale');
/* ---------- POS DASHBOARD SHORTCODE ---------- */