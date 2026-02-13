<?php
/**
 * Module Name: E-Commerce
 * Module Slug: ec
 * Description: Complete e-commerce solution with products, orders, and checkout
 * Version: 1.0.1
 * Author: Your Name
 * Icon: 🛒
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_EC_PATH', dirname(__FILE__) . '/');
define('BNTM_EC_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_ec_get_pages() {
    return [
        'E-Commerce' => '[ec_dashboard]',
        'Shop' => '[ec_shop]',
        'Checkout' => '[ec_checkout]',
        'Transaction' => '[ec_transaction]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_ec_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'ec_products' => "CREATE TABLE {$prefix}ec_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(255) UNIQUE NOT NULL,
            description LONGTEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock INT NOT NULL DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",
        
        'ec_orders' => "CREATE TABLE {$prefix}ec_orders (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            tax DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            payment_method VARCHAR(50),
            payment_status VARCHAR(50) DEFAULT 'unpaid',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_order_number (order_number)
        ) {$charset};",
        
        'ec_order_items' => "CREATE TABLE {$prefix}ec_order_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            product_name VARCHAR(255),
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            INDEX idx_order (order_id),
            INDEX idx_product (product_id)
        ) {$charset};"
    ];
}


/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_ec_get_shortcodes() {
    return [
       
        'ec_checkout' => 'bntm_shortcode_ec_checkout',
        'ec_dashboard' => 'bntm_shortcode_ec',
        'ec_shop' => 'bntm_shortcode_ec_shop'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_ec_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_ec_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}


// AJAX handlers
add_action('wp_ajax_ec_update_order_status', 'bntm_ajax_ec_update_order_status');
add_action('wp_ajax_ec_add_to_cart', 'bntm_ajax_ec_add_to_cart');
add_action('wp_ajax_nopriv_ec_add_to_cart', 'bntm_ajax_ec_add_to_cart');
add_action('wp_ajax_ec_process_checkout', 'bntm_ajax_ec_process_checkout');
add_action('wp_ajax_nopriv_ec_process_checkout', 'bntm_ajax_ec_process_checkout');
add_action('wp_ajax_ec_process_checkout_op', 'bntm_ajax_ec_process_checkout_op');
add_action('wp_ajax_nopriv_ec_process_checkout_op', 'bntm_ajax_ec_process_checkout_op');


/* ---------- MAIN E-COMMERCE SHORTCODE ---------- */
function bntm_shortcode_ec() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the E-Commerce dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-ecommerce-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=products" class="bntm-tab <?php echo $active_tab === 'products' ? 'active' : ''; ?>">Products</a>
            <a href="?tab=orders" class="bntm-tab <?php echo $active_tab === 'orders' ? 'active' : ''; ?>">Orders</a>
             <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import" class="bntm-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">Import Finance</a>
              <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo ec_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'products'): ?>
               <?php echo ec_products_tab($business_id); ?>
            <?php elseif ($active_tab === 'orders'): ?>
                <?php echo ec_orders_tab($business_id); ?>
            <?php elseif ($active_tab === 'import'): ?>
                <?php echo bntm_fn_orders_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo ec_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('E-Commerce', $content);
}
function ec_overview_tab($business_id) {
    $stats = ec_get_dashboard_stats($business_id);
    $shop_page = get_page_by_path('shop');
    $shop_url = $shop_page ? get_permalink($shop_page) : '';
    
    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <?php if ($shop_url): ?>
    <div class="ec-shop-page-card">
        <div class="ec-shop-header">
            <h3>Your Shop Page</h3>
            <span class="ec-status-badge">Active</span>
        </div>
        <div class="ec-shop-actions">
            <input type="text" id="shop-url" value="<?php echo esc_url($shop_url); ?>" readonly class="ec-url-input">
            <button class="bntm-btn-secondary" id="copy-shop-url">Copy Link</button>
            <a href="<?php echo esc_url($shop_url); ?>" target="_blank" class="bntm-btn-primary">View Shop</a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="ec-dashboard-stats">
        <div class="ec-stat-card">
            <div class="ec-stat-icon ec-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
            </div>
            <div class="ec-stat-content">
                <h3>Total Products</h3>
                <p class="ec-stat-number"><?php echo esc_html($stats['total_products']); ?></p>
            </div>
        </div>
        
        <div class="ec-stat-card">
            <div class="ec-stat-icon ec-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                </svg>
            </div>
            <div class="ec-stat-content">
                <h3>Total Orders</h3>
                <p class="ec-stat-number"><?php echo esc_html($stats['total_orders']); ?></p>
            </div>
        </div>
        
        <div class="ec-stat-card">
            <div class="ec-stat-icon ec-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="ec-stat-content">
                <h3>Monthly Revenue</h3>
                <p class="ec-stat-number"><?php echo ec_format_price($stats['monthly_revenue']); ?></p>
            </div>
        </div>
        
        <div class="ec-stat-card">
            <div class="ec-stat-icon ec-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
            </div>
            <div class="ec-stat-content">
                <h3>Low Stock Items</h3>
                <p class="ec-stat-number"><?php echo esc_html($stats['low_stock']); ?></p>
            </div>
        </div>
    </div>

    <div class="ec-charts-grid">
        <div class="ec-chart-card ec-chart-large">
            <h3>Sales Overview</h3>
            <canvas id="salesChart"></canvas>
        </div>
        
        <div class="ec-chart-card">
            <h3>Top Products by Sales</h3>
            <canvas id="productChart"></canvas>
        </div>
        
        <div class="ec-chart-card">
            <h3>Order Status</h3>
            <canvas id="orderStatusChart"></canvas>
        </div>
    </div>

    <div class="ec-recent-orders-section">
        <h3>Recent Orders</h3>
        <?php echo ec_render_recent_orders($business_id, 5); ?>
    </div>

    <style>
    .ec-shop-page-card {
        background: #f8f9fa;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 30px;
        border: 1px solid #e5e7eb;
    }
    
    .ec-shop-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }
    
    .ec-shop-header h3 {
        margin: 0;
        color: #111827;
        font-size: 18px;
        font-weight: 600;
    }
    
    .ec-status-badge {
        background: #10b981;
        color: #ffffff;
        padding: 4px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .ec-shop-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .ec-url-input {
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
    
    .ec-url-input:focus {
        outline: none;
        border-color: #9ca3af;
    }
    
    .ec-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .ec-stat-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .ec-stat-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .ec-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .ec-stat-icon-primary {
        background: var(--bntm-primary, #374151);
        color: #ffffff;
    }
    
    .ec-stat-content {
        flex: 1;
    }
    
    .ec-stat-content h3 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .ec-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        line-height: 1;
    }
    
    .ec-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .ec-chart-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .ec-chart-large {
        grid-column: 1 / -1;
    }
    
    .ec-chart-card h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .ec-chart-card canvas {
        max-height: 300px;
    }
    
    .ec-recent-orders-section {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .ec-recent-orders-section h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    @media (max-width: 768px) {
        .ec-chart-card {
            grid-column: 1 / -1;
        }
    }
    </style>
    
    <script>
    (function() {
        // Copy URL functionality
        const copyBtn = document.getElementById('copy-shop-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const urlInput = document.getElementById('shop-url');
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
        
        // Sales Overview Chart (Line Chart)
        const salesCtx = document.getElementById('salesChart');
        if (salesCtx) {
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['monthly_sales_data'], 'month')); ?>,
                    datasets: [{
                        label: 'Sales',
                        data: <?php echo json_encode(array_column($stats['monthly_sales_data'], 'total')); ?>,
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
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'Sales: <?php echo get_option('ec_currency_symbol', '$'); ?>' + context.parsed.y.toFixed(2);
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
        
        // Product Sales Chart (Doughnut Chart)
        const productCtx = document.getElementById('productChart');
        if (productCtx) {
            new Chart(productCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($stats['product_sales_data'], 'name')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['product_sales_data'], 'total')); ?>,
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
                                    return label + ': <?php echo get_option('ec_currency_symbol', '$'); ?>' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Order Status Chart (Pie Chart)
        const orderStatusCtx = document.getElementById('orderStatusChart');
        if (orderStatusCtx) {
            new Chart(orderStatusCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($stats['order_status_data'], 'status')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['order_status_data'], 'count')); ?>,
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

function ec_get_dashboard_stats($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'ec_products';
    $orders_table = $wpdb->prefix . 'ec_orders';
    $items_table = $wpdb->prefix . 'ec_order_items';
    
    // Total products
    $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
    
    // Total orders
    $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM $orders_table");
    
    // Monthly revenue
    $monthly_revenue = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM $orders_table 
         WHERE status != 'cancelled'
         AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(created_at) = YEAR(CURRENT_DATE())"
    );
    
    // Low stock items
    $low_stock = $wpdb->get_var(
        "SELECT COUNT(*) FROM $products_table 
         WHERE stock <= 5 AND stock > 0"
    );
    
    // Monthly sales data (last 6 months) - each month independent
    $monthly_sales_data = $wpdb->get_results(
        "SELECT DATE_FORMAT(created_at, '%b %Y') as month, COALESCE(SUM(total), 0) as total
        FROM $orders_table
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND status != 'cancelled'
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)",
        ARRAY_A
    );
    
    // If no data, create empty months
    if (empty($monthly_sales_data)) {
        $monthly_sales_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly_sales_data[] = [
                'month' => date('M Y', strtotime("-$i months")),
                'total' => 0
            ];
        }
    }
    
    // Product sales data (Top 5 products)
    $product_sales_data = $wpdb->get_results(
        "SELECT p.name, SUM(oi.quantity * oi.price) as total
        FROM $items_table oi
        JOIN $products_table p ON oi.product_id = p.id
        JOIN $orders_table o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY p.id, p.name
        ORDER BY total DESC
        LIMIT 5",
        ARRAY_A
    );
    
    // Order status data
    $order_status_data = $wpdb->get_results(
        "SELECT status, COUNT(*) as count
        FROM $orders_table
        GROUP BY status",
        ARRAY_A
    );
    
    return [
        'total_products' => intval($total_products),
        'total_orders' => intval($total_orders),
        'monthly_revenue' => floatval($monthly_revenue),
        'low_stock' => intval($low_stock),
        'monthly_sales_data' => $monthly_sales_data,
        'product_sales_data' => $product_sales_data ?: [],
        'order_status_data' => $order_status_data ?: []
    ];
}
function ec_orders_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'ec_orders';
    $orders = $wpdb->get_results(
        "SELECT * FROM $table  ORDER BY created_at DESC"
    );
    
    $nonce = wp_create_nonce('ec_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>All Orders (<?php echo count($orders); ?>)</h3>
        <?php if (empty($orders)): ?>
            <p>No orders yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $customer_data = get_option('ec_order_' . $order->rand_id . '_customer', []);
                        $customer_name = !empty($customer_data['name']) ? $customer_data['name'] : ($order->customer_id ? 'User #' . $order->customer_id : 'Guest');
                    ?>
                        <tr>
                            <td>#<?php echo esc_html($order->rand_id); ?></td>
                            <td><?php echo esc_html($customer_name); ?></td>
                            <td><?php echo ec_format_price($order->total); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($order->status); ?>">
                                    <?php echo esc_html(ucfirst($order->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(date('M d, Y H:i', strtotime($order->created_at))); ?></td>
                            <td>
                                <button class="bntm-btn-small view-order-details" data-order-id="<?php echo esc_attr($order->rand_id); ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal -->
    <div id="order-details-modal" class="order-modal" style="display: none;">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Order Details</h2>
                <button class="order-modal-close" id="close-order-modal">&times;</button>
            </div>
            <div class="order-modal-body" id="order-details-body">
                <div style="text-align: center; padding: 40px;">
                    <p>Loading order details...</p>
                </div>
            </div>
        </div>
    </div>

    <style>
    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;
        display: inline-block;
    }
    .status-badge.status-paid {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.status-processing {
        background: #dbeafe;
        color: #1e40af;
    }
    .status-badge.status-completed {
        background: #d1fae5;
        color: #065f46;
    }
    
    .order-modal {
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
    
    .order-modal-content {
        background: white;
        border-radius: 12px;
        max-width: 800px;
        width: 100%;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    .order-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .order-modal-header h2 {
        margin: 0;
        font-size: 24px;
        color: #1f2937;
    }
    
    .order-modal-close {
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
    
    .order-modal-close:hover {
        background: #f3f4f6;
        color: #1f2937;
    }
    
    .order-modal-body {
        padding: 30px;
        overflow-y: auto;
    }
    
    .order-info-section {
        margin-bottom: 30px;
        padding: 20px;
        background: #f9fafb;
        border-radius: 8px;
    }
    
    .order-info-section h3 {
        margin: 0 0 15px 0;
        font-size: 18px;
        color: #1f2937;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 10px;
    }
    
    .order-info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .order-info-row:last-child {
        border-bottom: none;
    }
    
    .order-info-label {
        font-weight: 600;
        color: #6b7280;
    }
    
    .order-info-value {
        color: #1f2937;
        text-align: right;
    }
    
    .order-products-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    
    .order-products-table th,
    .order-products-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .order-products-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #1f2937;
    }
    
    .order-status-update {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 15px;
    }
    
    .order-status-update select {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }
    </style>

    <script>
    (function() {
        const modal = document.getElementById('order-details-modal');
        const modalBody = document.getElementById('order-details-body');
        const closeBtn = document.getElementById('close-order-modal');
        
        // View order details
        document.querySelectorAll('.view-order-details').forEach(btn => {
            btn.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                loadOrderDetails(orderId);
            });
        });
        
        // Close modal
        closeBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Load order details
        function loadOrderDetails(orderId) {
            modal.style.display = 'flex';
            modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><p>Loading order details...</p></div>';
            
            const formData = new FormData();
            formData.append('action', 'ec_get_order_details');
            formData.append('order_id', orderId);
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    displayOrderDetails(json.data);
                } else {
                    modalBody.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626;"><p>Failed to load order details.</p></div>';
                }
            })
            .catch(err => {
                modalBody.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626;"><p>Error: ' + err.message + '</p></div>';
            });
        }
        
        // Display order details
        function displayOrderDetails(data) {
            const order = data.order;
            const customer = data.customer;
            const products = data.products;
            
            let html = `
                <div class="order-info-section">
                    <h3>Order Information</h3>
                    <div class="order-info-row">
                        <span class="order-info-label">Order ID:</span>
                        <span class="order-info-value">#${order.rand_id}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Order Date:</span>
                        <span class="order-info-value">${order.created_at}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Status:</span>
                        <span class="order-info-value">
                            <span class="status-badge status-${order.status}">${order.status_label}</span>
                        </span>
                    </div>
                </div>
                
                <div class="order-info-section">
                    <h3>Customer Details</h3>
                    <div class="order-info-row">
                        <span class="order-info-label">Name:</span>
                        <span class="order-info-value">${customer.name || 'N/A'}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Email:</span>
                        <span class="order-info-value">${customer.email || 'N/A'}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Phone:</span>
                        <span class="order-info-value">${customer.phone || 'N/A'}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Address:</span>
                        <span class="order-info-value">${customer.address || 'N/A'}</span>
                    </div>
                </div>
                
                <div class="order-info-section">
                    <h3>Order Items</h3>
                    <table class="order-products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>`;
            
            products.forEach(product => {
                html += `
                    <tr>
                        <td>${product.name}</td>
                        <td>${product.price}</td>
                        <td>${product.quantity}</td>
                        <td>${product.subtotal}</td>
                    </tr>`;
            });
            
            html += `
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right; font-weight: 600;">Subtotal:</td>
                                <td style="font-weight: 600;">${customer.subtotal || order.total}</td>
                            </tr>`;
            
            if (customer.tax && parseFloat(customer.tax) > 0) {
                html += `
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: 600;">Tax:</td>
                        <td style="font-weight: 600;">${customer.tax}</td>
                    </tr>`;
            }
            
            html += `
                            <tr style="font-size: 16px; background: #f9fafb;">
                                <td colspan="3" style="text-align: right; font-weight: 700;">Total:</td>
                                <td style="font-weight: 700; color: #059669;">${order.total}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="order-info-section">
                    <h3>Payment Information</h3>
                    <div class="order-info-row">
                        <span class="order-info-label">Payment Method:</span>
                        <span class="order-info-value">${customer.payment_method || 'N/A'}</span>
                    </div>
                    <div class="order-info-row">
                        <span class="order-info-label">Transaction ID:</span>
                        <span class="order-info-value">${customer.transaction_id || 'N/A'}</span>
                    </div>
                </div>
                
                <div class="order-info-section">
                    <h3>Update Order Status</h3>
                    <div class="order-status-update">
                        <select id="update-order-status" class="order-status-select">
                            <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="paid" ${order.status === 'paid' ? 'selected' : ''}>Paid</option>
                            <option value="processing" ${order.status === 'processing' ? 'selected' : ''}>Processing</option>
                            <option value="completed" ${order.status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                        </select>
                        <button class="bntm-btn-primary" id="save-order-status" data-order-id="${order.rand_id}">
                            Update Status
                        </button>
                    </div>
                </div>
            `;
            
            modalBody.innerHTML = html;
            
            // Attach update status handler
            document.getElementById('save-order-status').addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const newStatus = document.getElementById('update-order-status').value;
                updateOrderStatus(orderId, newStatus);
            });
        }
        
        // Update order status
        function updateOrderStatus(orderId, newStatus) {
            const btn = document.getElementById('save-order-status');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Updating...';
            
            const formData = new FormData();
            formData.append('action', 'ec_update_order_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus);
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert('Order status updated successfully!');
                    modal.style.display = 'none';
                    location.reload();
                } else {
                    alert('Failed to update order status.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.disabled = false;
                btn.textContent = originalText;
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bntm_ajax_ec_get_order_details() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $orders_table = $wpdb->prefix . 'ec_orders';
    $order_items_table = $wpdb->prefix . 'ec_order_items';
    $order_id = sanitize_text_field($_POST['order_id']);
    
    // Get order
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE rand_id = %s",
        $order_id
    ));
    
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }
    
    // Get customer data
    $customer_data = get_option('ec_order_' . $order_id . '_customer', []);
    
    // Get order items from ec_order_items table
    $order_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $order_items_table WHERE order_id = %d ORDER BY id ASC",
        $order->id
    ));
    
    $products = [];
    $items_subtotal = 0;
    
    if ($order_items && !empty($order_items)) {
        // Items exist in ec_order_items table
        foreach ($order_items as $item) {
            $item_subtotal = floatval($item->price) * intval($item->quantity);
            $items_subtotal += $item_subtotal;
            
            $products[] = [
                'name' => $item->product_name,
                'price' => ec_format_price($item->price),
                'quantity' => $item->quantity,
                'subtotal' => ec_format_price($item_subtotal)
            ];
        }
    } else {
        // Fallback: Try to get from saved cart data (for older orders)
        $cart_data = get_option('ec_order_' . $order_id . '_cart', []);
        
        if (!empty($cart_data)) {
            $products_table = $wpdb->prefix . 'ec_products';
            foreach ($cart_data as $product_id => $quantity) {
                $product = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $products_table WHERE rand_id = %s",
                    $product_id
                ));
                
                if ($product) {
                    $item_subtotal = floatval($product->price) * intval($quantity);
                    $items_subtotal += $item_subtotal;
                    
                    $products[] = [
                        'name' => $product->name,
                        'price' => ec_format_price($product->price),
                        'quantity' => $quantity,
                        'subtotal' => ec_format_price($item_subtotal)
                    ];
                }
            }
        }
    }
    
    // If no products found in either table, return error
    if (empty($products)) {
        wp_send_json_error(['message' => 'No order items found']);
    }
    
    // Use order table values for subtotal and tax if available
    $display_subtotal = isset($order->subtotal) && $order->subtotal > 0 
        ? $order->subtotal 
        : (isset($customer_data['subtotal']) ? $customer_data['subtotal'] : $items_subtotal);
    
    $display_tax = isset($order->tax) && $order->tax > 0 
        ? $order->tax 
        : (isset($customer_data['tax']) ? $customer_data['tax'] : 0);
    
    wp_send_json_success([
        'order' => [
            'id' => $order->id,
            'rand_id' => $order->rand_id,
            'order_number' => $order->order_number ?? 'N/A',
            'status' => $order->status,
            'status_label' => ucfirst($order->status),
            'payment_status' => isset($order->payment_status) ? ucfirst($order->payment_status) : 'N/A',
            'total' => ec_format_price($order->total),
            'created_at' => date('F j, Y, g:i a', strtotime($order->created_at))
        ],
        'customer' => [
            'name' => $customer_data['name'] ?? '',
            'email' => $customer_data['email'] ?? '',
            'phone' => $customer_data['phone'] ?? '',
            'address' => $customer_data['address'] ?? '',
            'payment_method' => $customer_data['payment_method'] ?? ($order->payment_method ?? 'N/A'),
            'transaction_id' => $customer_data['transaction_id'] ?? 'N/A',
            'subtotal' => ec_format_price($display_subtotal),
            'tax' => ec_format_price($display_tax)
        ],
        'products' => $products
    ]);
}
add_action('wp_ajax_ec_get_order_details', 'bntm_ajax_ec_get_order_details');

function bntm_fn_orders_tab() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'ec_orders';
    $txn_table = $wpdb->prefix . 'fn_transactions';
    
$orders = $wpdb->get_results("
    SELECT o.*, 
    (SELECT COUNT(*) FROM {$txn_table} WHERE reference_type='order' AND reference_id=o.id) as is_imported
    FROM {$orders_table} o
    WHERE o.status NOT IN ('pending', 'cancelled')
    ORDER BY o.created_at DESC
");
    
    $nonce = wp_create_nonce('bntm_fn_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>E-Commerce Orders</h3>
        <p>Import completed orders as income transactions</p>
        
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
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th width="40"></th>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Import Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="6" style="text-align:center;">No orders found</td></tr>
                <?php else: foreach ($orders as $order): ?>
                <tr>
                    <td>
                        <input type="checkbox" 
                               class="order-checkbox <?php echo $order->is_imported ? 'imported-order' : 'not-imported-order'; ?>" 
                               data-id="<?php echo $order->id; ?>"
                               data-amount="<?php echo $order->total; ?>"
                               data-imported="<?php echo $order->is_imported ? '1' : '0'; ?>">
                    </td>
                    <td>#<?php echo $order->id; ?></td>
                    <td><?php echo date('M d, Y', strtotime($order->created_at)); ?></td>
                    <td class="bntm-stat-income">₱<?php echo number_format($order->total, 2); ?></td>
                    <td><?php echo esc_html($order->status); ?></td>
                    <td>
                        <?php if ($order->is_imported): ?>
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
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.order-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        // Select all not imported
        document.getElementById('select-all-not-imported').addEventListener('change', function() {
            document.querySelectorAll('.not-imported-order').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Select all imported
        document.getElementById('select-all-imported').addEventListener('change', function() {
            document.querySelectorAll('.imported-order').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-not-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Update count on individual checkbox change
        document.querySelectorAll('.order-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Bulk Import
        document.getElementById('bulk-import-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.order-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '0');
            
            if (selected.length === 0) {
                alert('Please select at least one order that is not imported');
                return;
            }
            
            // Calculate total amount
            const totalAmount = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
            
            if (!confirm(`Import ${selected.length} order(s) as income?\n\nTotal Amount: ₱${totalAmount.toFixed(2)}`)) return;
            
            this.disabled = true;
            this.textContent = 'Importing...';
            
            // Import one by one using existing AJAX
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'bntm_fn_import_order');
                data.append('order_id', cb.dataset.id);
                data.append('amount', cb.dataset.amount);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully imported ${total} order(s)`);
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
            const selected = Array.from(document.querySelectorAll('.order-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '1');
            
            if (selected.length === 0) {
                alert('Please select at least one imported order');
                return;
            }
            
            if (!confirm(`Remove ${selected.length} order(s) from Finance transactions?`)) return;
            
            this.disabled = true;
            this.textContent = 'Reverting...';
            
            // Revert one by one using existing AJAX
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'bntm_fn_revert_order');
                data.append('order_id', cb.dataset.id);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully reverted ${total} order(s)`);
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
function bntm_ajax_fn_import_order() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $order_id = intval($_POST['order_id']);
    $rand_id = bntm_rand_id();
    $amount = floatval($_POST['amount']);
    
    // Check if already imported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$txn_table} WHERE reference_type='order' AND reference_id=%d",
        $order_id
    ));
    
    if ($exists) {
        wp_send_json_error('Order already imported.');
    }
    
    $data = [
        'rand_id' => $rand_id,
        'business_id' => 0,
        'type' => 'income',
        'amount' => $amount,
        'category' => 'Sales',
        'notes' => 'E-Commerce Order #' . $rand_id,
        'reference_type' => 'order',
        'reference_id' => $order_id
    ];
    
    $result = $wpdb->insert($txn_table, $data);
    
    if ($result) {
        bntm_fn_update_cashflow_summary();
        wp_send_json_success('Order imported successfully!');
    } else {
        wp_send_json_error('Failed to import order.');
    }
}

function bntm_ajax_fn_revert_order() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $order_id = intval($_POST['order_id']);
    
    $result = $wpdb->delete($table, [
        'reference_type' => 'order',
        'reference_id' => $order_id
    ]);
    
    if ($result) {
        bntm_fn_update_cashflow_summary();
        wp_send_json_success('Order reverted from transactions.');
    } else {
        wp_send_json_error('Failed to revert order.');
    }
}
function ec_products_tab($business_id) {
    global $wpdb;
    $ec_table = $wpdb->prefix . 'ec_products';
    $in_table = $wpdb->prefix . 'in_products';
    
    // Get all e-commerce products
    $products = $wpdb->get_results("SELECT * FROM {$ec_table}");

    // Get available imports (SKU-based, not already imported)
    $available_imports = $wpdb->get_results($wpdb->prepare("
        SELECT inprod.*
        FROM {$in_table} AS inprod
        LEFT JOIN {$ec_table} AS ecprod 
            ON inprod.sku = ecprod.sku AND inprod.sku IS NOT NULL AND inprod.sku != ''
        WHERE ecprod.sku IS NULL
          AND inprod.inventory_type = %s
          AND inprod.sku IS NOT NULL 
          AND inprod.sku != ''
        ORDER BY inprod.name ASC
    ", 'Product'));
    
    $nonce = wp_create_nonce('ec_products_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>E-Commerce Products (<?php echo count($products); ?>)</h3>
        
        <?php if (empty($products)): ?>
            <p>No products yet. Import products from your inventory to get started.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr data-product-id="<?php echo $product->id; ?>" class="product-row <?php echo $product->status === 'inactive' ? 'product-hidden' : ''; ?>">
                            <td><?php echo esc_html($product->name); ?></td>
                            <td><?php echo esc_html($product->sku ?: '-'); ?></td>
                            <td><?php echo ec_format_price($product->price); ?></td>
                            <td><?php echo esc_html($product->stock); ?></td>
                            <td>
                                <label class="ec-toggle">
                                    <input type="checkbox" 
                                           class="ec-toggle-status" 
                                           data-id="<?php echo $product->id; ?>"
                                           data-nonce="<?php echo $nonce; ?>"
                                           <?php checked($product->status, 'active'); ?>>
                                    <span class="ec-toggle-slider"></span>
                                </label>
                                <span class="status-text"><?php echo ucfirst($product->status); ?></span>
                            </td>
                            <td>
                                <button class="bntm-btn-small ec-sync-product" 
                                        data-id="<?php echo $product->id; ?>"
                                        data-nonce="<?php echo $nonce; ?>"
                                        title="Sync with inventory">
                                    Sync
                                </button>
                                <button class="bntm-btn-small bntm-btn-danger ec-delete-product" 
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
        <?php endif; ?>
    </div>

    <?php if (!empty($available_imports)): ?>
    <div class="bntm-form-section" style="background: #eff6ff; border-left: 4px solid #3b82f6;">
        <h3>Import Products from Inventory</h3>
        <p>Select products to import (<?php echo count($available_imports); ?> available)</p>
        
        <div style="margin: 15px 0;">
            <label style="cursor: pointer;">
                <input type="checkbox" id="select-all-imports"> 
                <strong>Select All</strong>
            </label>
        </div>
        
        <div class="import-products-list">
            <?php foreach ($available_imports as $product): ?>
                <label class="import-product-item">
                    <input type="checkbox" name="import_products[]" value="<?php echo $product->id; ?>">
                    <div class="import-product-info">
                        <strong><?php echo esc_html($product->name); ?></strong>
                        <?php if ($product->sku): ?>
                            <span class="product-sku">SKU: <?php echo esc_html($product->sku); ?></span>
                        <?php endif; ?>
                        <div class="product-details">
                            <span class="product-price">₱<?php echo number_format($product->selling_price, 2); ?></span>
                            <span class="product-stock"><?php echo $product->stock_quantity; ?> in stock</span>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
        
        <button id="ec-import-selected" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>" style="margin-top: 15px;">
            Import Selected Products
        </button>
        <div id="import-message"></div>
    </div>
    <?php endif; ?>
    
    <style>
    .import-products-list {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px;
        background: white;
    }
    .import-product-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #f3f4f6;
        cursor: pointer;
        transition: background 0.2s;
    }
    .import-product-item:hover {
        background: #f9fafb;
    }
    .import-product-item:last-child {
        border-bottom: none;
    }
    .import-product-item input[type="checkbox"] {
        margin-right: 12px;
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .import-product-info {
        flex: 1;
    }
    .import-product-info strong {
        display: block;
        color: #1f2937;
        margin-bottom: 4px;
    }
    .product-sku {
        display: inline-block;
        font-size: 12px;
        color: #6b7280;
        background: #f3f4f6;
        padding: 2px 8px;
        border-radius: 4px;
        margin-left: 8px;
    }
    .product-details {
        display: flex;
        gap: 15px;
        margin-top: 6px;
        font-size: 14px;
    }
    .product-price {
        color: #059669;
        font-weight: 600;
    }
    .product-stock {
        color: #6b7280;
    }
    .ec-toggle {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        margin-right: 10px;
    }
    .ec-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .ec-toggle-slider {
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
    .ec-toggle-slider:before {
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
    .ec-toggle input:checked + .ec-toggle-slider {
        background-color: #059669;
    }
    .ec-toggle input:checked + .ec-toggle-slider:before {
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
        // Select all checkbox
        const selectAllBtn = document.getElementById('select-all-imports');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('change', function() {
                document.querySelectorAll('input[name="import_products[]"]').forEach(cb => {
                    cb.checked = this.checked;
                });
            });
        }
        
        // Import selected products
        const importBtn = document.getElementById('ec-import-selected');
        if (importBtn) {
            importBtn.addEventListener('click', function() {
                const selected = Array.from(document.querySelectorAll('input[name="import_products[]"]:checked'))
                    .map(cb => cb.value);
                
                if (selected.length === 0) {
                    alert('Please select at least one product to import');
                    return;
                }
                
                if (!confirm(`Import ${selected.length} product(s)?`)) return;
                
                this.disabled = true;
                this.textContent = 'Importing...';
                
                const formData = new FormData();
                formData.append('action', 'ec_import_selected_products');
                formData.append('product_ids', JSON.stringify(selected));
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    const msg = document.getElementById('import-message');
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                    if (json.success) {
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        this.disabled = false;
                        this.textContent = 'Import Selected Products';
                    }
                });
            });
        }
        
        // Toggle product status
        document.querySelectorAll('.ec-toggle-status').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const formData = new FormData();
                formData.append('action', 'ec_toggle_product_status');
                formData.append('product_id', this.dataset.id);
                formData.append('status', this.checked ? 'active' : 'inactive');
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const statusText = this.closest('td').querySelector('.status-text');
                        statusText.textContent = this.checked ? 'Active' : 'Inactive';
                    } else {
                        alert(json.data.message);
                        this.checked = !this.checked;
                    }
                });
            });
        });
        
        // Sync product
        document.querySelectorAll('.ec-sync-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Sync this product with inventory? Product will be deleted if not found.')) return;
                
                this.disabled = true;
                this.textContent = '⏳';
                
                const formData = new FormData();
                formData.append('action', 'ec_sync_product');
                formData.append('product_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    location.reload();
                });
            });
        });
        
        // Delete product
        document.querySelectorAll('.ec-delete-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) return;
                
                this.disabled = true;
                this.textContent = '⏳';
                
                const formData = new FormData();
                formData.append('action', 'ec_delete_product');
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

/* ---------- AJAX HANDLERS ---------- */

function bntm_ajax_ec_import_selected_products() {
    check_ajax_referer('ec_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $ec_table = $wpdb->prefix . 'ec_products';
    $in_table = $wpdb->prefix . 'in_products';
    
    $product_ids = json_decode(stripslashes($_POST['product_ids']), true);
    
    if (empty($product_ids) || !is_array($product_ids)) {
        wp_send_json_error(['message' => 'No products selected']);
    }
    
    $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
    
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$in_table} WHERE id IN ($placeholders)",
        ...$product_ids
    ));
    
    if (empty($products)) {
        wp_send_json_error(['message' => 'No products found']);
    }
    
    $imported = 0;
    $skipped = 0;
    
    foreach ($products as $product) {
        // Skip products without SKU
        if (empty($product->sku)) {
            $skipped++;
            continue;
        }
        
        // Check if already exists by SKU
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$ec_table} WHERE sku = %s",
            $product->sku
        ));
        
        if ($exists) {
            $skipped++;
            continue;
        }
        
        $result = $wpdb->insert($ec_table, [
            'rand_id' => $product->rand_id,
            'business_id' => 0,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'price' => $product->selling_price,
            'stock' => $product->stock_quantity,
            'status' => 'active'
        ], ['%s', '%d', '%s', '%s', '%s', '%f', '%d', '%s']);
        
        if ($result) $imported++;
    }
    
    $message = "Successfully imported {$imported} product(s)!";
    if ($skipped > 0) {
        $message .= " ({$skipped} skipped - already exists or missing SKU)";
    }
    
    wp_send_json_success(['message' => $message]);
}
add_action('wp_ajax_ec_import_selected_products', 'bntm_ajax_ec_import_selected_products');

function bntm_ajax_ec_toggle_product_status() {
    check_ajax_referer('ec_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ec_products';
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
add_action('wp_ajax_ec_toggle_product_status', 'bntm_ajax_ec_toggle_product_status');

function bntm_ajax_ec_sync_product() {
    check_ajax_referer('ec_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $ec_table = $wpdb->prefix . 'ec_products';
    $in_table = $wpdb->prefix . 'in_products';
    $product_id = intval($_POST['product_id']);
    
    // Get EC product
    $ec_product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$ec_table} WHERE id = %d",
        $product_id
    ));
    
    if (!$ec_product) {
        wp_send_json_error(['message' => 'Product not found']);
    }
    
    // Check if product has SKU
    if (empty($ec_product->sku)) {
        wp_send_json_error(['message' => 'Product has no SKU. Cannot sync.']);
    }
    
    // Find product in inventory by SKU
    $in_product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$in_table} WHERE sku = %s",
        $ec_product->sku
    ));
    
    // If product not found in inventory, delete from EC
    if (!$in_product) {
        $deleted = $wpdb->delete(
            $ec_table,
            ['id' => $product_id],
            ['%d']
        );
        
        if ($deleted) {
            wp_send_json_success(['message' => 'Product not found in inventory. Deleted from e-commerce.']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete product']);
        }
        return;
    }
    
    // Update product with inventory data
    $result = $wpdb->update(
        $ec_table,
        [
            'name' => $in_product->name,
            'price' => $in_product->selling_price,
            'stock' => $in_product->stock_quantity,
            'description' => $in_product->description,
            'rand_id' => $in_product->rand_id
        ],
        ['id' => $product_id],
        ['%s', '%f', '%d', '%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Product synced successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to sync product']);
    }
}
add_action('wp_ajax_ec_sync_product', 'bntm_ajax_ec_sync_product');

function bntm_ajax_ec_delete_product() {
    check_ajax_referer('ec_products_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'ec_products';
    $product_id = intval($_POST['product_id']);
    
    $result = $wpdb->delete(
        $table,
        ['id' => $product_id],
        ['%d']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Product deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete product']);
    }
}
add_action('wp_ajax_ec_delete_product', 'bntm_ajax_ec_delete_product');
function ec_settings_tab($business_id) {
    $shop_page = get_page_by_path('shop');
    $checkout_page = get_page_by_path('checkout');
    $transaction = get_page_by_path('transaction');
    $nonce = wp_create_nonce('ec_nonce');
    
    // Get existing payment methods
    $payment_methods = json_decode(bntm_get_setting('ec_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) {
        $payment_methods = [];
    }
    // Get payment source setting (ec or op)
    $payment_source = bntm_get_setting('ec_payment_source', 'ec');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Payment Configuration</h3>
        <p>Choose where to manage payment methods for your eCommerce checkout.</p>
        
        <div class="bntm-form-group">
            <select id="payment-source-select" name="payment_source">
                <option value="ec" <?php selected($payment_source, 'ec'); ?>>MANUAL</option>
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
    <div class="bntm-form-section" id="ec-payment-methods-section" style="<?php echo $payment_source === 'op' ? 'display: none;' : ''; ?>">
        <h3>eCommerce Payment Methods</h3>
        <p>Configure manual payment methods for your store. Online payment gateways are available in the Online Payment module.</p>
        
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
                        <option value="manual">Manual/Cash on Delivery</option>
                    </select>
                    <small>For online payment gateways (PayPal, PayMaya, Stripe), use the Online Payment module.</small>
                </div>
                
                <div class="bntm-form-group">
                    <label>Display Name *</label>
                    <input type="text" name="payment_name" placeholder="e.g., BDO Bank Transfer, Cash on Delivery" required>
                    <small>This will be shown to customers at checkout</small>
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
                    <label>Description/Instructions</label>
                    <textarea name="payment_description" rows="3" placeholder="Payment instructions for customers"></textarea>
                </div>
                
                <button type="submit" class="bntm-btn-primary">Add Payment Method</button>
            </form>
        </div>
        <div id="payment-method-message"></div>
    </div>
   <!-- SHOP PAGE CUSTOMIZATION SECTION -->
   <div class="bntm-form-section">
       <h3>Shop Page Customization</h3>
       <p>Customize the appearance and content of your shop page to make it more appealing to customers.</p>
       
       <form id="ec-shop-appearance-form" class="bntm-form">
           <div class="bntm-form-group">
               <label>Shop Header Title</label>
               <input type="text" name="shop_header_title" value="<?php echo esc_attr(bntm_get_setting('ec_shop_header_title', 'Welcome to Our Shop')); ?>" placeholder="e.g., Welcome to Our Shop">
               <small>Main heading displayed at the top of your shop page</small>
           </div>
           
           <div class="bntm-form-group">
               <label>Shop Subtitle</label>
               <input type="text" name="shop_subtitle" value="<?php echo esc_attr(bntm_get_setting('ec_shop_subtitle', 'Discover amazing products at great prices')); ?>" placeholder="e.g., Discover amazing products at great prices">
               <small>Subtitle text below the main heading</small>
           </div>
           
           <div class="bntm-form-group">
               <label>Shop Description</label>
               <textarea name="shop_description" rows="4" placeholder="Tell customers about your store..."><?php echo esc_textarea(bntm_get_setting('ec_shop_description', '')); ?></textarea>
               <small>A brief description of your store (optional)</small>
           </div>
           
           <div class="bntm-form-group">
                <label>Header Background Color</label>
                <input type="color" name="shop_header_bg_color" value="<?php echo esc_attr(bntm_get_setting('ec_shop_header_bg_color', bntm_get_setting('bntm_primary_color', '#3b82f6'))); ?>">
                <small>Background color for the shop header section (uses your brand primary color by default)</small>
            </div>
           
           <div class="bntm-form-group">
               <label>Header Text Color</label>
               <input type="color" name="shop_header_text_color" value="<?php echo esc_attr(bntm_get_setting('ec_shop_header_text_color', '#ffffff')); ?>">
               <small>Text color for the shop header</small>
           </div>
           
           <div class="bntm-form-group">
               <label>Banner Image URL (Optional)</label>
               <input type="url" name="shop_banner_image" value="<?php echo esc_attr(bntm_get_setting('ec_shop_banner_image', '')); ?>" placeholder="https://example.com/banner.jpg">
               <small>Add a banner image to the top of your shop page</small>
           </div>
           
           <div class="bntm-form-group">
               <label>Show Header Section</label>
               <select name="shop_show_header">
                   <option value="yes" <?php selected(bntm_get_setting('ec_shop_show_header', 'yes'), 'yes'); ?>>Yes</option>
                   <option value="no" <?php selected(bntm_get_setting('ec_shop_show_header', 'yes'), 'no'); ?>>No</option>
               </select>
               <small>Toggle visibility of the shop header section</small>
           </div>
           
           <button type="submit" class="bntm-btn-primary">Save Shop Appearance</button>
           <div id="shop-appearance-message"></div>
       </form>
   </div>
    <div class="bntm-form-section">
        <h3>Store Settings</h3>
        <form id="ec-store-settings" class="bntm-form">
            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="USD" <?php selected(bntm_get_setting('ec_currency', 'USD'), 'USD'); ?>>USD - US Dollar</option>
                    <option value="EUR" <?php selected(bntm_get_setting('ec_currency', 'USD'), 'EUR'); ?>>EUR - Euro</option>
                    <option value="GBP" <?php selected(bntm_get_setting('ec_currency', 'USD'), 'GBP'); ?>>GBP - British Pound</option>
                    <option value="PHP" <?php selected(bntm_get_setting('ec_currency', 'USD'), 'PHP'); ?>>PHP - Philippine Peso</option>
                </select>
            </div>
            <div class="bntm-form-group">
                <label>Low Stock Threshold</label>
                <input type="number" name="low_stock_threshold" value="<?php echo esc_attr(bntm_get_setting('ec_low_stock_threshold', '5')); ?>">
                <small>Alert when stock falls below this number</small>
            </div>
            <div class="bntm-form-group">
                <label>Tax Rate (%)</label>
                <input type="number" name="tax_rate" step="0.01" value="<?php echo esc_attr(bntm_get_setting('ec_tax_rate', '0')); ?>">
            </div>
            <button type="submit" class="bntm-btn-primary">Save Store Settings</button>
            <div id="store-settings-message"></div>
        </form>
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
        // Payment source toggle
        const paymentSourceSelect = document.getElementById('payment-source-select');
        const ecPaymentSection = document.getElementById('ec-payment-methods-section');
        const paymentSourceMessage = document.getElementById('payment-source-message');
        
        paymentSourceSelect.addEventListener('change', function() {
            if (this.value === 'op') {
                ecPaymentSection.style.display = 'none';
            } else {
                ecPaymentSection.style.display = 'block';
            }
        });
        
        document.getElementById('save-payment-source-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'ec_save_payment_source');
            formData.append('payment_source', paymentSourceSelect.value);
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                paymentSourceMessage.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Payment Source';
            });
        });
        
        // Add payment method
        const addPaymentForm = document.getElementById('add-payment-method-form');
        const paymentMessage = document.getElementById('payment-method-message');
        
        addPaymentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'ec_add_payment_method');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    paymentMessage.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    paymentMessage.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
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
                formData.append('action', 'ec_remove_payment_method');
                formData.append('index', index);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
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
        
        // Save store settings
        const storeForm = document.getElementById('ec-store-settings');
        const storeMessage = document.getElementById('store-settings-message');
        
        storeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'ec_save_store_settings');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                storeMessage.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Store Settings';
            });
        });
        // Save shop appearance settings
         const shopAppearanceForm = document.getElementById('ec-shop-appearance-form');
         const shopAppearanceMessage = document.getElementById('shop-appearance-message');
         
         shopAppearanceForm.addEventListener('submit', function(e) {
             e.preventDefault();
             
             const formData = new FormData(this);
             formData.append('action', 'ec_save_shop_appearance');
             formData.append('nonce', '<?php echo $nonce; ?>');
             
             const btn = this.querySelector('button[type="submit"]');
             btn.disabled = true;
             btn.textContent = 'Saving...';
             
             fetch(ajaxurl, {
                 method: 'POST',
                 body: formData
             })
             .then(r => r.json())
             .then(json => {
                 shopAppearanceMessage.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                 btn.disabled = false;
                 btn.textContent = 'Save Shop Appearance';
             });
         });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// Add new AJAX handler for payment source
function bntm_ajax_ec_save_payment_source() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $payment_source = sanitize_text_field($_POST['payment_source']);
    
    if (!in_array($payment_source, ['ec', 'op'])) {
        wp_send_json_error(['message' => 'Invalid payment source']);
    }

    bntm_set_setting('ec_payment_source', $payment_source);

    wp_send_json_success(['message' => 'Payment source saved successfully!']);
}
add_action('wp_ajax_ec_save_payment_source', 'bntm_ajax_ec_save_payment_source');

// Modified add payment method (remove API options)
function bntm_ajax_ec_add_payment_method() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $payment_methods = json_decode(bntm_get_setting('ec_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) {
        $payment_methods = [];
    }

    $type = sanitize_text_field($_POST['payment_type']);

    // Only allow manual types in EC
    if (!in_array($type, ['bank', 'manual'])) {
        wp_send_json_error(['message' => 'Invalid payment type. Use Online Payment module for gateway payments.']);
    }

    $new_method = [
        'type' => $type,
        'name' => sanitize_text_field($_POST['payment_name']),
        'description' => sanitize_textarea_field($_POST['payment_description']),
        'account_name' => sanitize_text_field($_POST['account_name']),
        'account_number' => sanitize_text_field($_POST['account_number'])
    ];

    $payment_methods[] = $new_method;
    
    bntm_set_setting('ec_payment_methods', json_encode($payment_methods));

    wp_send_json_success(['message' => 'Payment method added successfully!']);
}
add_action('wp_ajax_ec_add_payment_method', 'bntm_ajax_ec_add_payment_method');

function bntm_ajax_ec_remove_payment_method() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $index = intval($_POST['index']);
    $payment_methods = json_decode(bntm_get_setting('ec_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods)) {
        wp_send_json_error(['message' => 'Invalid data']);
    }

    if (isset($payment_methods[$index])) {
        array_splice($payment_methods, $index, 1);
        bntm_set_setting('ec_payment_methods', json_encode($payment_methods));
        wp_send_json_success(['message' => 'Payment method removed']);
    } else {
        wp_send_json_error(['message' => 'Payment method not found']);
    }
}
add_action('wp_ajax_ec_remove_payment_method', 'bntm_ajax_ec_remove_payment_method');
// AJAX handler for shop appearance settings
function bntm_ajax_ec_save_shop_appearance() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('ec_shop_header_title', sanitize_text_field($_POST['shop_header_title']));
    bntm_set_setting('ec_shop_subtitle', sanitize_text_field($_POST['shop_subtitle']));
    bntm_set_setting('ec_shop_description', sanitize_textarea_field($_POST['shop_description']));
    bntm_set_setting('ec_shop_header_bg_color', sanitize_hex_color($_POST['shop_header_bg_color']));
    bntm_set_setting('ec_shop_header_text_color', sanitize_hex_color($_POST['shop_header_text_color']));
    bntm_set_setting('ec_shop_banner_image', esc_url_raw($_POST['shop_banner_image']));
    bntm_set_setting('ec_shop_show_header', sanitize_text_field($_POST['shop_show_header']));

    wp_send_json_success(['message' => 'Shop appearance saved successfully!']);
}
add_action('wp_ajax_ec_save_shop_appearance', 'bntm_ajax_ec_save_shop_appearance');
function bntm_ajax_ec_save_store_settings() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('ec_currency', sanitize_text_field($_POST['currency']));
    bntm_set_setting('ec_low_stock_threshold', intval($_POST['low_stock_threshold']));
    bntm_set_setting('ec_tax_rate', floatval($_POST['tax_rate']));

    wp_send_json_success(['message' => 'Store settings saved successfully!']);
}
add_action('wp_ajax_ec_save_store_settings', 'bntm_ajax_ec_save_store_settings');

/* ---------- IMPROVED AJAX: Add to Cart (No Refresh) ---------- */
function bntm_ajax_ec_add_to_cart() {
    if (!isset($_SESSION)) {
        session_start();
    }

    $product_id = sanitize_text_field($_POST['product_id']);

    // Verify product exists and has stock
    global $wpdb;
    $table = $wpdb->prefix . 'ec_products';
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE rand_id = %s AND status = 'active' AND stock > 0",
        $product_id
    ));

    if (!$product) {
        wp_send_json_error(['message' => 'Product not available.']);
    }

    if (!isset($_SESSION['ec_cart'])) {
        $_SESSION['ec_cart'] = [];
    }

    // Check if adding one more would exceed stock
    $current_in_cart = isset($_SESSION['ec_cart'][$product_id]) ? $_SESSION['ec_cart'][$product_id] : 0;
    if ($current_in_cart >= $product->stock) {
        wp_send_json_error(['message' => 'Cannot add more. Stock limit reached.']);
    }

    if (isset($_SESSION['ec_cart'][$product_id])) {
        $_SESSION['ec_cart'][$product_id]++;
    } else {
        $_SESSION['ec_cart'][$product_id] = 1;
    }

    $cart_count = array_sum($_SESSION['ec_cart']);
    
    wp_send_json_success([
        'message' => 'Product added to cart!',
        'cart_count' => $cart_count,
        'product_id' => $product_id,
        'quantity_in_cart' => $_SESSION['ec_cart'][$product_id]
    ]);
}
add_action('wp_ajax_ec_add_to_cart', 'bntm_ajax_ec_add_to_cart');
add_action('wp_ajax_nopriv_ec_add_to_cart', 'bntm_ajax_ec_add_to_cart');

/* ---------- NEW AJAX: Update Cart Quantity ---------- */
function bntm_ajax_ec_update_cart() {
    if (!isset($_SESSION)) {
        session_start();
    }

    $product_id = sanitize_text_field($_POST['product_id']);
    $quantity = intval($_POST['quantity']);

    if ($quantity < 1) {
        wp_send_json_error(['message' => 'Quantity must be at least 1.']);
    }

    // Check stock availability
    global $wpdb;
    $table = $wpdb->prefix . 'ec_products';
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE rand_id = %s AND status = 'active'",
        $product_id
    ));

    if (!$product) {
        wp_send_json_error(['message' => 'Product not found.']);
    }

    if ($quantity > $product->stock) {
        wp_send_json_error(['message' => 'Only ' . $product->stock . ' items available in stock.']);
    }

    $_SESSION['ec_cart'][$product_id] = $quantity;

    // Recalculate totals
    $subtotal = 0;
    $cart_items = [];
    foreach ($_SESSION['ec_cart'] as $pid => $qty) {
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE rand_id = %s", $pid));
        if ($p) {
            $item_subtotal = $p->price * $qty;
            $subtotal += $item_subtotal;
            $cart_items[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'price' => $p->price,
                'subtotal' => $item_subtotal
            ];
        }
    }

    $tax_rate = floatval(bntm_get_setting('ec_tax_rate', '0'));
    $tax_amount = $subtotal * ($tax_rate / 100);
    $total = $subtotal + $tax_amount;

    wp_send_json_success([
        'message' => 'Cart updated!',
        'cart_items' => $cart_items,
        'subtotal' => $subtotal,
        'tax' => $tax_amount,
        'total' => $total,
        'cart_count' => array_sum($_SESSION['ec_cart'])
    ]);
}
add_action('wp_ajax_ec_update_cart', 'bntm_ajax_ec_update_cart');
add_action('wp_ajax_nopriv_ec_update_cart', 'bntm_ajax_ec_update_cart');

/* ---------- NEW AJAX: Remove from Cart ---------- */
function bntm_ajax_ec_remove_from_cart() {
    if (!isset($_SESSION)) {
        session_start();
    }

    $product_id = sanitize_text_field($_POST['product_id']);

    if (isset($_SESSION['ec_cart'][$product_id])) {
        unset($_SESSION['ec_cart'][$product_id]);
    }

    // Recalculate totals
    global $wpdb;
    $table = $wpdb->prefix . 'ec_products';
    $subtotal = 0;
    $cart_items = [];
    
    foreach ($_SESSION['ec_cart'] as $pid => $qty) {
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE rand_id = %s", $pid));
        if ($p) {
            $item_subtotal = $p->price * $qty;
            $subtotal += $item_subtotal;
            $cart_items[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'price' => $p->price,
                'subtotal' => $item_subtotal
            ];
        }
    }

    $tax_rate = floatval(bntm_get_setting('ec_tax_rate', '0'));
    $tax_amount = $subtotal * ($tax_rate / 100);
    $total = $subtotal + $tax_amount;

    wp_send_json_success([
        'message' => 'Item removed from cart.',
        'cart_items' => $cart_items,
        'subtotal' => $subtotal,
        'tax' => $tax_amount,
        'total' => $total,
        'cart_count' => array_sum($_SESSION['ec_cart']),
        'cart_empty' => empty($_SESSION['ec_cart'])
    ]);
}
add_action('wp_ajax_ec_remove_from_cart', 'bntm_ajax_ec_remove_from_cart');
add_action('wp_ajax_nopriv_ec_remove_from_cart', 'bntm_ajax_ec_remove_from_cart');

/* ---------- NEW AJAX: Clear Cart ---------- */
function bntm_ajax_ec_clear_cart() {
    if (!isset($_SESSION)) {
        session_start();
    }

    $_SESSION['ec_cart'] = [];

    wp_send_json_success([
        'message' => 'Cart cleared.',
        'cart_empty' => true
    ]);
}
add_action('wp_ajax_ec_clear_cart', 'bntm_ajax_ec_clear_cart');
add_action('wp_ajax_nopriv_ec_clear_cart', 'bntm_ajax_ec_clear_cart');

/* ---------- SHOP PAGE (No Refresh) ---------- */
/* ---------- SHOP PAGE (No Refresh) ---------- */
/* ---------- SHOP PAGE (No Refresh) ---------- */
function bntm_shortcode_ec_shop() {
    if (!isset($_SESSION)) {
        session_start();
    }

    global $wpdb;
    $table_ec = $wpdb->prefix . 'ec_products';
    $table_in = $wpdb->prefix . 'in_products';

    $products = $wpdb->get_results("
        SELECT ec.*, inprod.image AS in_image, inprod.description, inprod.sku
        FROM {$table_ec} AS ec
        LEFT JOIN {$table_in} AS inprod 
            ON ec.rand_id = inprod.rand_id
        WHERE ec.status = 'active' AND ec.stock > 0
        ORDER BY ec.name ASC
    ");

    $cart = isset($_SESSION['ec_cart']) ? $_SESSION['ec_cart'] : [];
    $cart_count = array_sum($cart);

    // Get shop customization settings
    $show_header = bntm_get_setting('ec_shop_show_header', 'yes');
    $header_title = bntm_get_setting('ec_shop_header_title', 'Welcome to Our Shop');
    $subtitle = bntm_get_setting('ec_shop_subtitle', 'Discover amazing products at great prices');
    $description = bntm_get_setting('ec_shop_description', '');
    $header_bg_color = bntm_get_setting('ec_shop_header_bg_color', bntm_get_setting('bntm_primary_color', '#3b82f6'));
    $header_text_color = bntm_get_setting('ec_shop_header_text_color', '#ffffff');
    $banner_image = bntm_get_setting('ec_shop_banner_image', '');
    
    // Get site branding
    $logo = bntm_get_site_logo();
    $site_title = bntm_get_site_title();

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="ec-shop-container">
        <?php if ($show_header === 'yes'): ?>
        <div class="ec-shop-header" style="background-color: <?php echo esc_attr($header_bg_color); ?>; color: <?php echo esc_attr($header_text_color); ?>; <?php echo !empty($banner_image) ? 'background-image: url(' . esc_url($banner_image) . ');' : ''; ?>">
            <div class="ec-shop-header-overlay">
                <!-- Site Branding -->
                <div class="ec-shop-branding">
                    <?php if (!empty($logo)): ?>
                        <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($site_title); ?>" class="ec-shop-logo">
                    <?php endif; ?>
                   
                </div>
                
                <!-- Shop Header Content -->
                <div class="ec-shop-header-content">
                    <?php if (!empty($site_title)): ?>
                        <div class="ec-shop-site-title"><?php echo esc_html($site_title); ?></div>
                    <?php endif; ?>
                    <h1 class="ec-shop-title"><?php echo esc_html($header_title); ?></h1>
                    <?php if (!empty($subtitle)): ?>
                        <p class="ec-shop-subtitle"><?php echo esc_html($subtitle); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($description)): ?>
                        <p class="ec-shop-description"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="ec-shop-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h2 style="margin: 0; font-size: 24px; color: #1f2937;">Our Products</h2>
                <a href="<?php echo get_permalink(get_page_by_path('checkout')); ?>" class="bntm-btn bntm-btn-primary" id="checkout-btn" style="<?php echo $cart_count > 0 ? '' : 'display:none;'; ?>">
                    🛒 Checkout (<span id="cart-count"><?php echo $cart_count; ?></span> items)
                </a>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="ec-no-products">
                    <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <h3>No Products Available</h3>
                    <p>Check back soon for new items!</p>
                </div>
            <?php else: ?>
                <div class="ec-products-grid">
                    <?php foreach ($products as $product): 
                        $image_url = $product->in_image ? $product->in_image : '';
                        $in_cart = isset($cart[$product->rand_id]) ? $cart[$product->rand_id] : 0;
                        $description = !empty($product->description) ? $product->description : 'No description available.';
                        $category = !empty($product->category) ? $product->category : 'Uncategorized';
                        $sku = !empty($product->sku) ? $product->sku : 'N/A';
                    ?>
                        <div class="ec-product-card" data-product-id="<?php echo esc_attr($product->rand_id); ?>">
                            <div class="ec-product-image" style="position: relative;">
                                <?php if (!empty($image_url)): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->name); ?>">
                                <?php else: ?>
                                    <div class="ec-no-image">
                                        <svg width="48" height="48" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>No Image</span>
                                    </div>
                                <?php endif; ?>
                                <button class="view-details-btn" 
                                        data-product-id="<?php echo esc_attr($product->rand_id); ?>"
                                        data-name="<?php echo esc_attr($product->name); ?>"
                                        data-price="<?php echo esc_attr($product->price); ?>"
                                        data-stock="<?php echo esc_attr($product->stock); ?>"
                                        data-image="<?php echo esc_attr($image_url); ?>"
                                        data-description="<?php echo esc_attr($description); ?>"
                                        data-category="<?php echo esc_attr($category); ?>"
                                        data-sku="<?php echo esc_attr($sku); ?>"
                                        data-in-cart="<?php echo esc_attr($in_cart); ?>">
                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                                    </svg>
                                    View Details
                                </button>
                            </div>
                            <div class="ec-product-details">
                                <h3><?php echo esc_html($product->name); ?></h3>
                                <p class="ec-product-price"><?php echo ec_format_price($product->price); ?></p>
                                <p class="ec-product-stock">
                                    <span class="stock-available"><?php echo esc_html($product->stock); ?></span> in stock
                                    <?php if ($in_cart > 0): ?>
                                        <span class="in-cart-badge"><?php echo $in_cart; ?> in cart</span>
                                    <?php endif; ?>
                                </p>
                                <button class="bntm-btn ec-add-to-cart" data-id="<?php echo esc_attr($product->rand_id); ?>">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div id="product-modal" class="product-modal">
        <div class="product-modal-overlay"></div>
        <div class="product-modal-content">
            <button class="product-modal-close">&times;</button>
            <div class="product-modal-body">
                <div class="product-modal-image">
                    <img id="modal-image" src="" alt="">
                </div>
                <div class="product-modal-info">
                    <div class="product-modal-header">
                        <h2 id="modal-name"></h2>
                        <span id="modal-category" class="product-category-badge"></span>
                    </div>
                    <p class="product-modal-sku">SKU: <strong id="modal-sku"></strong></p>
                    <p class="product-modal-price" id="modal-price"></p>
                    <p class="product-modal-stock">
                        <span id="modal-stock"></span> in stock
                        <span id="modal-in-cart-badge" class="in-cart-badge" style="display: none;"></span>
                    </p>
                    
                    <div class="product-modal-description">
                        <h3>Description</h3>
                        <p id="modal-description"></p>
                    </div>
                    
                    <div class="product-modal-actions">
                        <div class="quantity-selector">
                            <label>Quantity:</label>
                            <div class="quantity-controls">
                                <button type="button" class="qty-btn qty-minus">−</button>
                                <input type="number" id="modal-quantity" value="1" min="1" max="1">
                                <button type="button" class="qty-btn qty-plus">+</button>
                            </div>
                        </div>
                        <button class="bntm-btn bntm-btn-primary bntm-btn-large" id="modal-add-to-cart">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<style>
.bntm-header { display: none; }
   /* Container - Full Width */
   .ec-shop-container {
       width: 100%;
       
       padding: 40px 20px;
   }
   
   /* Main Section - Max Width 1200px */
   .ec-shop-main {
       max-width: 1200px;
       margin: 0 auto;
       padding: 40px 20px;
   }
    /* Shop Header */
    .ec-shop-header {
        margin: -40px -20px 40px -20px;
        padding: 0;
        background-color: var(--bntm-primary, #3b82f6);
        background-size: cover;
        background-position: center;
        position: relative;
        overflow: hidden;
    }
    .ec-shop-header-overlay {
        position: relative;
        z-index: 1;
        background: rgba(0, 0, 0, 0.2);
        backdrop-filter: blur(4px);
    }
    
    /* Site Branding - Minimalist */
    .ec-shop-branding {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
        padding: 30px 20px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    }
    .ec-shop-logo {
        height: 48px;
        width: auto;
        object-fit: contain;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }
    .ec-shop-site-title {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: -0.5px;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    /* Header Content */
    .ec-shop-header-content {
        max-width: 800px;
        margin: 0 auto;
        text-align: center;
        padding: 40px 20px 50px;
    }
    .ec-shop-title {
        font-size: 38px;
        font-weight: 700;
        margin: 0 0 12px 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        line-height: 1.2;
        letter-spacing: -0.5px;
    }
    .ec-shop-subtitle,
    .ec-shop-description {
        opacity: 0.95;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
        margin: 0;
    }
    .ec-shop-subtitle {
        font-size: 18px;
        margin-bottom: 8px;
        font-weight: 400;
    }
    .ec-shop-description {
        font-size: 15px;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
        opacity: 0.9;
        font-weight: 300;
    }


/* No Products */
.ec-no-products {
    text-align: center;
    padding: 80px 20px;
    color: #6b7280;
}
.ec-no-products svg {
    margin: 0 auto 20px;
    color: #d1d5db;
}
.ec-no-products h3 {
    font-size: 24px;
    color: #1f2937;
    margin: 0 0 10px 0;
}
.ec-no-products p {
    font-size: 16px;
    margin: 0;
}

/* Product Grid */
.ec-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 24px;
    margin-top: 30px;
}
.ec-product-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.2s;
}
.ec-product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.12);
}

/* Product Image */
.ec-product-image {
    position: relative;
    background: #f9fafb;
}
.ec-product-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
}
.ec-no-image {
    width: 100%;
    height: 200px;
    background: #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    gap: 10px;
}
.ec-no-image span {
    font-size: 14px;
    font-weight: 500;
}

/* View Details Button */
.view-details-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: var(--bntm-primary, #3b82f6);
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.view-details-btn:hover {
    background: white;
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    color: var(--bntm-primary-hover, var(--bntm-primary, #3b82f6));
}

/* Product Details */
.ec-product-details {
    padding: 16px;
}
.ec-product-details h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    color: #1f2937;
    font-weight: 600;
}
.ec-product-price {
    font-size: 24px;
    font-weight: 700;
    color: #059669;
    margin: 8px 0;
}
.ec-product-stock {
    font-size: 14px;
    color: #6b7280;
    margin: 4px 0 12px 0;
}
.stock-available {
    font-weight: 600;
    color: #059669;
}
.in-cart-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    background: var(--bntm-primary, #3b82f6);
    color: white;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

/* Modal */
.product-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    animation: fadeIn 0.3s ease;
}
.product-modal.active {
    display: flex;
    align-items: center;
}
.product-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
}
.product-modal-content {
    position: relative;
    max-width: 900px;
    margin: 40px auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
    max-height: calc(100vh - 80px);
    overflow-y: auto;
}
.product-modal-close {
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
    z-index: 10;
    color: #6b7280;
}
.product-modal-close:hover {
    background: #e5e7eb;
    color: #1f2937;
    transform: rotate(90deg);
}
.product-modal-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding: 30px;
}
.product-modal-image {
    position: sticky;
    top: 0;
}
.product-modal-image img {
    width: 100%;
    height: 400px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.product-modal-image .no-image {
    width: 400px;
    height: 400px;
    background: #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
    border-radius: 8px;
    font-size: 18px;
}
.product-modal-info {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.product-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.product-modal-header h2 {
    margin: 0;
    font-size: 28px;
    color: #1f2937;
    flex: 1;
}
.product-category-badge {
    padding: 6px 12px;
    background: #eff6ff;
    color: var(--bntm-primary, #3b82f6);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
}
.product-modal-sku {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
}
.product-modal-price {
    font-size: 32px;
    font-weight: 700;
    color: #059669;
    margin: 0;
}
.product-modal-stock {
    font-size: 15px;
    color: #6b7280;
    margin: 0;
}
.product-modal-description {
    margin-top: 10px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}
.product-modal-description h3 {
    font-size: 16px;
    color: #1f2937;
    margin: 0 0 10px 0;
    font-weight: 600;
}
.product-modal-description p {
    font-size: 15px;
    line-height: 1.6;
    color: #4b5563;
    margin: 0;
}
.product-modal-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Quantity Controls */
.quantity-selector {
    display: flex;
    align-items: center;
    gap: 12px;
}
.quantity-selector label {
    font-size: 15px;
    font-weight: 600;
    color: #1f2937;
}
.quantity-controls {
    display: flex;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}
.qty-btn {
    background: #f9fafb;
    border: none;
    width: 40px;
    height: 40px;
    font-size: 20px;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s;
}
.qty-btn:hover {
    background: #e5e7eb;
    color: #1f2937;
}
.qty-btn:active {
    background: #d1d5db;
}
#modal-quantity {
    width: 60px;
    height: 40px;
    text-align: center;
    border: none;
    border-left: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb;
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
}
#modal-quantity:focus {
    outline: none;
    background: #f9fafb;
}
.bntm-btn-large {
    padding: 14px 28px;
    font-size: 16px;
    font-weight: 600;
}

/* Animations */
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

/* Responsive */
@media (max-width: 768px) {
   .ec-shop-container {
        padding: 0; 
   }
   .product-modal-image .no-image {
       width: 100%;
   }
    .ec-shop-header {
        margin: -40px -20px 30px -20px;
        padding: 0;
    }
    .ec-shop-header-overlay {
        padding: 30px 20px;
    }
    .ec-shop-title {
        font-size: 32px;
    }
    .ec-shop-subtitle {
        font-size: 18px;
    }
    .ec-shop-description {
        font-size: 15px;
    }
    .ec-products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }
    .product-modal-content {
        margin: 20px;
        max-height: calc(100vh - 40px);
        width: 100%;
    }
    .product-modal-body {
        grid-template-columns: 1fr;
        padding: 20px;
        gap: 20px;
    }
    .product-modal-image {
        position: relative;
    }
    .product-modal-image img {
        height: 300px;
    }
    .product-modal-header h2 {
        font-size: 22px;
    }
    .product-modal-price {
        font-size: 26px;
    }
}
</style>
<script>
(function() {
    let currentProductId = null;
    const modal = document.getElementById('product-modal');
    const modalOverlay = modal.querySelector('.product-modal-overlay');
    const modalClose = modal.querySelector('.product-modal-close');
    const qtyInput = document.getElementById('modal-quantity');
    const qtyMinus = modal.querySelector('.qty-minus');
    const qtyPlus = modal.querySelector('.qty-plus');

    // Function to open modal with product data
    function openProductModal(productData) {
        currentProductId = productData.productId;

        // Populate modal
        document.getElementById('modal-name').textContent = productData.name;
        document.getElementById('modal-category').textContent = productData.category;
        document.getElementById('modal-sku').textContent = productData.sku;
        document.getElementById('modal-price').textContent = '₱' + parseFloat(productData.price).toFixed(2);
        document.getElementById('modal-stock').textContent = productData.stock;
        document.getElementById('modal-description').textContent = productData.description;
        
        const modalImage = document.getElementById('modal-image');
        const modalImageContainer = modal.querySelector('.product-modal-image');
        
        if (productData.image) {
            modalImage.src = productData.image;
            modalImage.alt = productData.name;
            modalImage.style.display = 'block';
            modalImageContainer.querySelector('.no-image')?.remove();
        } else {
            modalImage.style.display = 'none';
            if (!modalImageContainer.querySelector('.no-image')) {
                const noImage = document.createElement('div');
                noImage.className = 'no-image';
                noImage.textContent = 'No Image Available';
                modalImageContainer.appendChild(noImage);
            }
        }

        // Set quantity limits
        qtyInput.max = productData.stock;
        qtyInput.value = 1;

        // Show in-cart badge if applicable
        const inCartBadge = document.getElementById('modal-in-cart-badge');
        if (productData.inCart > 0) {
            inCartBadge.textContent = productData.inCart + ' in cart';
            inCartBadge.style.display = 'inline-block';
        } else {
            inCartBadge.style.display = 'none';
        }

        // Show modal
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    // Open modal from view details button
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            
            const productData = {
                productId: this.dataset.productId,
                name: this.dataset.name,
                price: this.dataset.price,
                stock: parseInt(this.dataset.stock),
                image: this.dataset.image,
                description: this.dataset.description,
                category: this.dataset.category,
                sku: this.dataset.sku,
                inCart: parseInt(this.dataset.inCart) || 0
            };

            openProductModal(productData);
        });
    });

    // Open modal when clicking product card
    document.querySelectorAll('.ec-product-card').forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't open modal if clicking the "Add to Cart" button
            if (e.target.closest('.ec-add-to-cart')) {
                return;
            }

            const btn = this.querySelector('.view-details-btn');
            if (btn) {
                const productData = {
                    productId: btn.dataset.productId,
                    name: btn.dataset.name,
                    price: btn.dataset.price,
                    stock: parseInt(btn.dataset.stock),
                    image: btn.dataset.image,
                    description: btn.dataset.description,
                    category: btn.dataset.category,
                    sku: btn.dataset.sku,
                    inCart: parseInt(btn.dataset.inCart) || 0
                };

                openProductModal(productData);
            }
        });

        // Add hover effect to show it's clickable
        card.style.cursor = 'pointer';
    });

    // Close modal
    function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        currentProductId = null;
    }

    modalClose.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', closeModal);

    // Quantity controls
    qtyMinus.addEventListener('click', function() {
        const current = parseInt(qtyInput.value);
        if (current > 1) {
            qtyInput.value = current - 1;
        }
    });

    qtyPlus.addEventListener('click', function() {
        const current = parseInt(qtyInput.value);
        const max = parseInt(qtyInput.max);
        if (current < max) {
            qtyInput.value = current + 1;
        }
    });

    qtyInput.addEventListener('change', function() {
        const value = parseInt(this.value);
        const max = parseInt(this.max);
        if (value < 1) this.value = 1;
        if (value > max) this.value = max;
    });

    // Add to cart from modal
    document.getElementById('modal-add-to-cart').addEventListener('click', function() {
        const quantity = parseInt(qtyInput.value);
        const btn = this;
        
        btn.textContent = 'Adding...';
        btn.disabled = true;
        
        // Add items to cart (one by one to respect stock checks)
        let addedCount = 0;
        
        function addOne() {
            if (addedCount >= quantity) {
                // All added successfully
                btn.textContent = 'Added to Cart!';
                btn.style.background = '#059669';
                
                setTimeout(() => {
                    closeModal();
                    location.reload();
                }, 1000);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'ec_add_to_cart');
            formData.append('product_id', currentProductId);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    addedCount++;
                    addOne(); // Add next
                } else {
                    alert(json.data.message);
                    btn.textContent = 'Add to Cart';
                    btn.style.background = '';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.textContent = 'Add to Cart';
                btn.style.background = '';
                btn.disabled = false;
            });
        }
        
        addOne();
    });

    // Regular add to cart buttons (grid view)
    document.querySelectorAll('.ec-add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card click event
            
            const productId = this.getAttribute('data-id');
            const btn = this;
            const card = btn.closest('.ec-product-card');
            
            btn.textContent = 'Adding...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'ec_add_to_cart');
            formData.append('product_id', productId);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    btn.textContent = 'Added!';
                    btn.style.background = '#059669';
                    
                    // Update cart count
                    document.getElementById('cart-count').textContent = json.data.cart_count;
                    document.getElementById('checkout-btn').style.display = '';
                    
                    // Update stock display and in-cart badge
                    const stockSpan = card.querySelector('.stock-available');
                    const stockP = card.querySelector('.ec-product-stock');
                    let badge = stockP.querySelector('.in-cart-badge');
                    
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'in-cart-badge';
                        stockP.appendChild(badge);
                    }
                    badge.textContent = json.data.quantity_in_cart + ' in cart';
                    
                    setTimeout(() => {
                        btn.textContent = 'Add to Cart';
                        btn.style.background = '';
                        btn.disabled = false;
                    }, 1000);
                } else {
                    alert(json.data.message);
                    btn.textContent = 'Add to Cart';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.textContent = 'Add to Cart';
                btn.disabled = false;
            });
        });
    });

    // Close modal on ESC key
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
/* ---------- CHECKOUT PAGE (With Cart Management) ---------- */
function bntm_shortcode_ec_checkout() {
    if (!isset($_SESSION)) {
        session_start();
    }

    $cart = isset($_SESSION['ec_cart']) ? $_SESSION['ec_cart'] : [];
    
    if (empty($cart)) {
        return '<div class="bntm-container"><p>Your cart is empty. <a href="' . get_permalink(get_page_by_path('shop')) . '">Continue shopping</a></p></div>';
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ec_products';
    $subtotal = 0;
    
    $tax_rate = floatval(bntm_get_setting('ec_tax_rate', '0'));
    $payment_source = bntm_get_setting('ec_payment_source', 'ec');
    
    // Get payment methods based on source
    $payment_methods = [];
    $has_payment_methods = false;
    
    if ($payment_source === 'op') {
        // Use OP payment methods
        $methods_table = $wpdb->prefix . 'op_payment_methods';
        $business_id = get_current_user_id();
        $op_methods = $wpdb->get_results(
            "SELECT * FROM $methods_table WHERE is_active = 1 ORDER BY priority ASC"
        );
        
        if ($op_methods) {
            foreach ($op_methods as $method) {
                $config = json_decode($method->config, true);
                $payment_methods[] = [
                    'id' => $method->id,
                    'type' => $method->gateway,
                    'name' => $method->name,
                    'description' => $config['instructions'] ?? '',
                    'account_name' => $config['account_name'] ?? '',
                    'account_number' => $config['account_number'] ?? '',
                    'bank_name' => $config['bank_name'] ?? ''
                ];
            }
            $has_payment_methods = !empty($payment_methods);
        }
    } else {
        // Use EC payment methods
        $ec_methods = json_decode(bntm_get_setting('ec_payment_methods', '[]'), true);
        if (is_array($ec_methods) && !empty($ec_methods)) {
            $payment_methods = $ec_methods;
            $has_payment_methods = true;
        }
    }

    $nonce = wp_create_nonce('ec_checkout_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var paymentSource = '<?php echo esc_js($payment_source); ?>';
    </script>
    
    <div class="bntm-container">
        <div class="bntm-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Order Summary</h3>
                <button type="button" class="bntm-btn" id="clear-cart-btn" style="background: #ef4444;">
                    Clear Cart
                </button>
            </div>
            
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="cart-items">
                    <?php foreach ($cart as $product_id => $quantity): 
                        $product = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $table WHERE rand_id = %s", $product_id
                        ));
                        if (!$product) continue;
                        $item_subtotal = $product->price * $quantity;
                        $subtotal += $item_subtotal;
                    ?>
                        <tr data-product-id="<?php echo esc_attr($product_id); ?>">
                            <td><?php echo esc_html($product->name); ?></td>
                            <td class="item-price" data-price="<?php echo esc_attr($product->price); ?>">
                                <?php echo ec_format_price($product->price); ?>
                            </td>
                            <td>
                                <input type="number" 
                                       class="quantity-input" 
                                       value="<?php echo esc_attr($quantity); ?>" 
                                       min="1" 
                                       max="<?php echo esc_attr($product->stock); ?>"
                                       data-product-id="<?php echo esc_attr($product_id); ?>"
                                       style="width: 80px; padding: 4px 8px;">
                                <small style="display: block; color: #6b7280; margin-top: 4px;">
                                    Max: <?php echo esc_html($product->stock); ?>
                                </small>
                            </td>
                            <td class="item-subtotal"><?php echo ec_format_price($item_subtotal); ?></td>
                            <td>
                                <button type="button" 
                                        class="bntm-btn remove-item-btn" 
                                        data-product-id="<?php echo esc_attr($product_id); ?>"
                                        style="background: #ef4444; padding: 4px 12px;">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot id="cart-totals">
                    <tr>
                        <td colspan="3"><strong>Subtotal</strong></td>
                        <td colspan="2"><strong id="subtotal-amount"><?php echo ec_format_price($subtotal); ?></strong></td>
                    </tr>
                    <?php if ($tax_rate > 0): 
                        $tax_amount = $subtotal * ($tax_rate / 100);
                        $total = $subtotal + $tax_amount;
                    ?>
                    <tr>
                        <td colspan="3"><strong>Tax (<?php echo number_format($tax_rate, 2); ?>%)</strong></td>
                        <td colspan="2"><strong id="tax-amount"><?php echo ec_format_price($tax_amount); ?></strong></td>
                    </tr>
                    <tr style="font-size: 18px; background: #f9fafb;">
                        <td colspan="3"><strong>Total</strong></td>
                        <td colspan="2"><strong id="total-amount"><?php echo ec_format_price($total); ?></strong></td>
                    </tr>
                    <?php else: 
                        $total = $subtotal;
                    ?>
                    <tr style="font-size: 18px; background: #f9fafb;">
                        <td colspan="3"><strong>Total</strong></td>
                        <td colspan="2"><strong id="total-amount"><?php echo ec_format_price($total); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>

            <form id="ec-checkout-form" class="bntm-form" style="margin-top:30px;">
                <h3>Customer Information</h3>
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
                        <label>Phone *</label>
                        <input type="tel" name="customer_phone" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Address *</label>
                        <input type="text" name="customer_address" required>
                    </div>
                </div>

                <h3>Payment Method 
                    <?php if ($payment_source === 'op'): ?>
                        <small style="color: #6b7280; font-size: 14px;">(From Online Payment Module)</small>
                    <?php endif; ?>
                </h3>
                
                <?php if (!$has_payment_methods): ?>
                    <div class="bntm-notice bntm-notice-error" style="margin-bottom: 20px;">
                        <strong>No payment methods configured.</strong>
                        <p style="margin: 8px 0 0 0;">
                            <?php if (current_user_can('manage_options')): ?>
                                Please configure payment methods in the 
                                <?php if ($payment_source === 'op'): ?>
                                    <a href="<?php echo admin_url('admin.php?page=online-payment'); ?>">Online Payment settings</a>.
                                <?php else: ?>
                                    <a href="<?php echo get_permalink(get_page_by_path('ecommerce')) . '?tab=settings'; ?>">E-Commerce settings</a>.
                                <?php endif; ?>
                            <?php else: ?>
                                Please contact the store administrator to set up payment methods.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="payment-methods-grid">
                        <?php foreach ($payment_methods as $index => $method): ?>
                            <label class="payment-method-option">
                                <input type="radio" 
                                       name="payment_method" 
                                       value="<?php echo esc_attr($index); ?>" 
                                       <?php if ($payment_source === 'op' && isset($method['id'])): ?>
                                       data-method-id="<?php echo esc_attr($method['id']); ?>"
                                       data-gateway="<?php echo esc_attr($method['type']); ?>"
                                       <?php endif; ?>
                                       <?php echo $index === 0 ? 'checked' : ''; ?> 
                                       required>
                                <div class="payment-method-card">
                                    <strong><?php echo esc_html($method['name']); ?></strong>
                                    <span class="payment-type-badge"><?php echo esc_html(ucfirst($method['type'])); ?></span>
                                    <?php if (!empty($method['description'])): ?>
                                        <p style="margin: 8px 0 0 0; font-size: 13px; color: #6b7280;">
                                            <?php echo esc_html($method['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($method['account_name']) || !empty($method['account_number'])): ?>
                                        <div style="margin-top: 8px; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 8px;">
                                            <?php if (!empty($method['bank_name'])): ?>
                                                <div>Bank: <strong><?php echo esc_html($method['bank_name']); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($method['account_name'])): ?>
                                                <div>Account: <strong><?php echo esc_html($method['account_name']); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($method['account_number'])): ?>
                                                <div>Number: <strong><?php echo esc_html($method['account_number']); ?></strong></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="order_subtotal" id="hidden-subtotal" value="<?php echo esc_attr($subtotal); ?>">
                <input type="hidden" name="order_tax" id="hidden-tax" value="<?php echo esc_attr($tax_rate > 0 ? $tax_amount : 0); ?>">
                <input type="hidden" name="order_total" id="hidden-total" value="<?php echo esc_attr($total); ?>">
                
                <button type="submit" 
                        class="bntm-btn-primary" 
                        id="place-order-btn"
                        <?php echo !$has_payment_methods ? 'disabled' : ''; ?>
                        <?php echo !$has_payment_methods ? 'style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                    <?php if ($has_payment_methods): ?>
                        Place Order - <span id="button-total"><?php echo ec_format_price($total); ?></span>
                    <?php else: ?>
                        Payment Methods Required
                    <?php endif; ?>
                </button>
                <div id="ec-checkout-message"></div>
            </form>
        </div>
    </div>

    <style>
    .payment-methods-grid {
        display: grid;
        gap: 15px;
        margin-bottom: 20px;
    }
    .payment-method-option {
        cursor: pointer;
    }
    .payment-method-option input[type="radio"] {
        display: none;
    }
    .payment-method-card {
        padding: 15px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        transition: all 0.2s;
        background: white;
    }
    .payment-method-option input[type="radio"]:checked + .payment-method-card {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .payment-method-card:hover {
        border-color: #3b82f6;
    }
    .payment-type-badge {
        display: inline-block;
        margin-left: 10px;
        padding: 2px 8px;
        background: #f3f4f6;
        border-radius: 4px;
        font-size: 12px;
        color: #6b7280;
    }
    .quantity-input {
        border: 1px solid #d1d5db;
        border-radius: 4px;
    }
    </style>

    <script>
(function() {
    const taxRate = <?php echo $tax_rate; ?>;
    const hasPaymentMethods = <?php echo $has_payment_methods ? 'true' : 'false'; ?>;
    
    // Format price helper
    function formatPrice(amount) {
        const currency = '<?php echo esc_js(bntm_get_setting('ec_currency', 'USD')); ?>';
        const symbols = {
            'USD': '$',
            'EUR': '€',
            'GBP': '£',
            'PHP': '₱'
        };
        const symbol = symbols[currency] || '$';
        return symbol + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    // Update totals
    function updateTotals() {
        let subtotal = 0;
        document.querySelectorAll('#cart-items tr').forEach(row => {
            const priceEl = row.querySelector('.item-price');
            const qtyInput = row.querySelector('.quantity-input');
            if (priceEl && qtyInput) {
                const price = parseFloat(priceEl.dataset.price);
                const qty = parseInt(qtyInput.value);
                const itemSubtotal = price * qty;
                subtotal += itemSubtotal;
                row.querySelector('.item-subtotal').textContent = formatPrice(itemSubtotal);
            }
        });
        
        const tax = subtotal * (taxRate / 100);
        const total = subtotal + tax;
        
        document.getElementById('subtotal-amount').textContent = formatPrice(subtotal);
        if (document.getElementById('tax-amount')) {
            document.getElementById('tax-amount').textContent = formatPrice(tax);
        }
        document.getElementById('total-amount').textContent = formatPrice(total);
        
        const buttonTotal = document.getElementById('button-total');
        if (buttonTotal) {
            buttonTotal.textContent = formatPrice(total);
        }
        
        document.getElementById('hidden-subtotal').value = subtotal;
        document.getElementById('hidden-tax').value = tax;
        document.getElementById('hidden-total').value = total;
    }
    
    // Quantity change
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const productId = this.dataset.productId;
            const quantity = parseInt(this.value);
            const max = parseInt(this.max);
            
            if (quantity < 1) {
                this.value = 1;
                return;
            }
            
            if (quantity > max) {
                alert('Only ' + max + ' items available in stock.');
                this.value = max;
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'ec_update_cart');
            formData.append('product_id', productId);
            formData.append('quantity', this.value);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    updateTotals();
                } else {
                    alert(json.data ? json.data.message : 'Failed to update cart');
                    location.reload();
                }
            })
            .catch(err => {
                alert('Error updating cart: ' + err.message);
                location.reload();
            });
        });
    });
    
    // Remove item
    document.querySelectorAll('.remove-item-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Remove this item from cart?')) return;
            
            const productId = this.dataset.productId;
            const formData = new FormData();
            formData.append('action', 'ec_remove_from_cart');
            formData.append('product_id', productId);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    if (json.data && json.data.cart_empty) {
                        location.reload();
                    } else {
                        document.querySelector('tr[data-product-id="' + productId + '"]').remove();
                        updateTotals();
                    }
                }
            })
            .catch(err => {
                alert('Error removing item: ' + err.message);
            });
        });
    });
    
    // Clear cart
    document.getElementById('clear-cart-btn').addEventListener('click', function() {
        if (!confirm('Are you sure you want to clear your entire cart?')) return;
        
        const formData = new FormData();
        formData.append('action', 'ec_clear_cart');
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            }
        })
        .catch(err => {
            alert('Error clearing cart: ' + err.message);
        });
    });
    
    // Checkout form - only if payment methods exist
    if (hasPaymentMethods) {
        const checkoutForm = document.getElementById('ec-checkout-form');
        const message = document.getElementById('ec-checkout-message');
        
        checkoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Use different AJAX action based on payment source
            if (paymentSource === 'op') {
                formData.append('action', 'ec_process_checkout_op');
                
                // Get selected payment method ID for OP
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
                if (selectedMethod && selectedMethod.dataset.methodId) {
                    formData.append('op_method_id', selectedMethod.dataset.methodId);
                }
            } else {
                formData.append('action', 'ec_process_checkout');
            }
            
            formData.append('nonce', '<?php echo $nonce; ?>');
            formData.append('payment_source', paymentSource);
            
            const btn = document.getElementById('place-order-btn');
            btn.disabled = true;
            btn.innerHTML = 'Processing...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json && json.success) {
                    const data = json.data || {};
                    
                    // Handle OP redirect_url vs EC redirect
                    if (data.redirect_url) {
                        window.location.href = data.redirect_url;
                    } else if (data.redirect) {
                        message.innerHTML = '<div class="bntm-notice bntm-notice-success">' + (data.message || 'Order placed successfully!') + '</div>';
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    } else {
                        message.innerHTML = '<div class="bntm-notice bntm-notice-success">' + (data.message || 'Order placed successfully!') + '</div>';
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    }
                } else {
                    const data = json ? (json.data || {}) : {};
                    const errorMessage = data.message || 'An error occurred. Please try again.';
                    message.innerHTML = '<div class="bntm-notice bntm-notice-error">' + errorMessage + '</div>';
                    
                    if (data.refresh_page) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = 'Place Order - <span id="button-total">' + formatPrice(document.getElementById('hidden-total').value) + '</span>';
                    }
                }
            })
            .catch(err => {
                message.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.innerHTML = 'Place Order - <span id="button-total">' + formatPrice(document.getElementById('hidden-total').value) + '</span>';
            });
        });
    }
})();
</script>
    <?php
    
    $content = ob_get_clean();
    return $content;
}
/* ---------- FIXED CHECKOUT AJAX (Proper Inventory Batch Logging with 0 Cost) ---------- */
/* ---------- FIXED CHECKOUT AJAX (Proper Inventory Batch Logging with 0 Cost) ---------- */
function bntm_ajax_ec_process_checkout() {
    check_ajax_referer('ec_checkout_nonce', 'nonce');

    if (!isset($_SESSION)) {
        session_start();
    }

    if (empty($_SESSION['ec_cart'])) {
        wp_send_json_error(['message' => 'Cart is empty.']);
    }

    global $wpdb;
    $products_table     = $wpdb->prefix . 'ec_products';
    $orders_table       = $wpdb->prefix . 'ec_orders';
    $order_items_table  = $wpdb->prefix . 'ec_order_items';
    $inventory_table    = $wpdb->prefix . 'in_products';
    $batches_table      = $wpdb->prefix . 'in_batches';

    $customer_name    = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email   = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone   = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_address = sanitize_text_field($_POST['customer_address'] ?? '');
    $payment_method_index = intval($_POST['payment_method'] ?? 0);
    $order_subtotal   = floatval($_POST['order_subtotal'] ?? 0);
    $order_tax        = floatval($_POST['order_tax'] ?? 0);
    $order_total      = floatval($_POST['order_total'] ?? 0);

    // Validate inputs
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($customer_address)) {
        wp_send_json_error(['message' => 'Please fill in all customer information.']);
    }

    $payment_method = ec_get_payment_method($payment_method_index);
    if (!$payment_method) {
        wp_send_json_error(['message' => 'Invalid payment method selected.']);
    }

    $customer_id = is_user_logged_in() ? get_current_user_id() : 0;
    $order_rand_id = bntm_rand_id(15);
    $order_number = 'ORD-' . date('Ymd') . '-' . mt_rand(1000, 9999);

    // Determine business_id from first product in cart
    $first_product_rand = array_key_first($_SESSION['ec_cart']);
    $business_id = 0;
    if ($first_product_rand) {
        $business_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT business_id FROM {$products_table} WHERE rand_id = %s LIMIT 1",
            $first_product_rand
        ));
    }

    // **VALIDATE STOCK AVAILABILITY AND SYNC FIRST (Outside transaction)**
    $stock_issues = [];
    
    foreach ($_SESSION['ec_cart'] as $product_rand => $qty) {
        $qty = max(1, intval($qty));

        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$products_table} WHERE rand_id = %s LIMIT 1",
            $product_rand
        ));

        if (!$product) {
            unset($_SESSION['ec_cart'][$product_rand]);
            $stock_issues[] = "Product not found";
            continue;
        }

        // Check inventory stock first
        $inventory_product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$inventory_table} WHERE rand_id = %s LIMIT 1",
            $product->rand_id
        ));
        
        if ($inventory_product) {
            // Sync EC product stock with inventory stock
            $wpdb->update(
                $products_table,
                [
                    'stock' => $inventory_product->stock_quantity,
                    'status' => $inventory_product->stock_quantity == 0 ? 'inactive' : 'active'
                ],
                ['rand_id' => $product->rand_id],
                ['%d', '%s'],
                ['%s']
            );
            
            // Check if there's insufficient stock after sync
            if ($inventory_product->stock_quantity < $qty) {
                // Remove from cart
                unset($_SESSION['ec_cart'][$product_rand]);
                $stock_issues[] = $product->name . " (Available: {$inventory_product->stock_quantity}, Requested: {$qty})";
            }
        } else {
            // No inventory link - check EC stock only
            if ($product->stock < $qty) {
                unset($_SESSION['ec_cart'][$product_rand]);
                $stock_issues[] = $product->name . " (Available: {$product->stock}, Requested: {$qty})";
            }
        }
    }
    
    // If there were stock issues, notify and return with refresh flag
    if (!empty($stock_issues)) {
        $message = 'Some items were removed from your cart due to insufficient stock: ' . implode(', ', $stock_issues) . '. Page will refresh to update your cart.';
        
        wp_send_json_error([
            'message' => $message,
            'refresh_page' => true
        ]);
    }
    
    // Check if cart is now empty after removals
    if (empty($_SESSION['ec_cart'])) {
        wp_send_json_error([
            'message' => 'All items were removed from cart due to stock issues. Cart is now empty.',
            'refresh_page' => true
        ]);
    }

    // Start DB transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Process payment
        $payment_result = ec_process_payment($payment_method_index, $order_total, $order_rand_id);
        if (!$payment_result['success']) {
            throw new Exception($payment_result['message']);
        }

        $is_manual = in_array($payment_method['type'], ['gcash', 'bank', 'manual']);
        $order_status = $is_manual ? 'pending' : 'paid';
        $payment_status = $payment_result['status'] ?? ($order_status === 'paid' ? 'paid' : 'unpaid');
        $payment_method_name = $payment_method['name'] ?? $payment_method['type'];

        // Insert order
        $order_inserted = $wpdb->insert($orders_table, [
            'rand_id'        => $order_rand_id,
            'business_id'    => $business_id,
            'customer_id'    => $customer_id,
            'order_number'   => $order_number,
            'subtotal'       => $order_subtotal,
            'tax'            => $order_tax,
            'total'          => $order_total,
            'status'         => $order_status,
            'payment_method' => $payment_method_name,
            'payment_status' => $payment_status,
            'created_at'     => current_time('mysql')
        ], [
            '%s','%d','%d','%s','%f','%f','%f','%s','%s','%s','%s'
        ]);

        if (!$order_inserted) {
            throw new Exception('Failed to create order.');
        }

        $order_id = $wpdb->insert_id;

        // Process each cart item
        foreach ($_SESSION['ec_cart'] as $product_rand => $qty) {
            $qty = max(1, intval($qty));

            // Get EC product
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$products_table} WHERE rand_id = %s LIMIT 1",
                $product_rand
            ));

            $item_subtotal = floatval($product->price) * $qty;

            // Insert order item
            $insert_item = $wpdb->insert($order_items_table, [
                'rand_id'      => bntm_rand_id(),
                'order_id'     => $order_id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'quantity'     => $qty,
                'price'        => $product->price,
                'subtotal'     => $item_subtotal
            ], ['%s','%d','%d','%s','%d','%f','%f']);

            if (!$insert_item) {
                throw new Exception('Failed to insert order items.');
            }

            // **UPDATE BOTH INVENTORY TABLES & LOG BATCH WITH 0 COST**
            $inventory_product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$inventory_table} WHERE rand_id = %s LIMIT 1",
                $product->rand_id
            ));

            if ($inventory_product) {
                // Calculate new stock
                $new_stock = max(0, $inventory_product->stock_quantity - $qty);
                
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
                
                // Update ec_products stock and status
                $update_data = ['stock' => $new_stock];
                $update_format = ['%d'];
                
                // If stock is 0, set status to inactive
                if ($new_stock == 0) {
                    $update_data['status'] = 'inactive';
                    $update_format[] = '%s';
                }
                
                $ec_stock_updated = $wpdb->update(
                    $products_table,
                    $update_data,
                    ['rand_id' => $product->rand_id],
                    $update_format,
                    ['%s']
                );
                
                if ($ec_stock_updated === false) {
                    throw new Exception("Failed to update EC product stock for {$product->name}. MySQL Error: " . $wpdb->last_error);
                }
                
                // Log batch as stock_out with 0 cost
                $batch_inserted = $wpdb->insert($batches_table, [
                    'rand_id'          => bntm_rand_id(),
                    'business_id'      => $business_id,
                    'product_id'       => $inventory_product->id,
                    'batch_code'       => 'ECOM-' . $order_number,
                    'type'             => 'stock_out',
                    'reference_number' => $order_number,
                    'quantity'         => $qty,
                    'cost_per_unit'    => 0.00,
                    'total_cost'       => 0.00,
                    'notes'            => "E-Commerce Sale - Order: {$order_number}, Customer: {$customer_name}",
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
                // Inventory product not found - still update EC stock
                error_log("Warning: Inventory product not found for rand_id: {$product->rand_id}. Updating EC stock only.");
                
                $ec_stock_updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$products_table} SET stock = stock - %d WHERE rand_id = %s AND stock >= %d",
                    $qty, $product->rand_id, $qty
                ));
                
                if ($ec_stock_updated === false || $ec_stock_updated === 0) {
                    throw new Exception("Failed to update EC product stock for {$product->name}");
                }
                
                // Check if stock is now 0 and set inactive
                $current_stock = $wpdb->get_var($wpdb->prepare(
                    "SELECT stock FROM {$products_table} WHERE rand_id = %s",
                    $product->rand_id
                ));
                
                if ($current_stock == 0) {
                    $wpdb->update(
                        $products_table,
                        ['status' => 'inactive'],
                        ['rand_id' => $product->rand_id],
                        ['%s'],
                        ['%s']
                    );
                }
            }
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        // Save customer/payment metadata
        update_option('ec_order_' . $order_rand_id . '_customer', [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone' => $customer_phone,
            'address' => $customer_address,
            'payment_method' => $payment_method_name,
            'payment_type' => $payment_method['type'],
            'transaction_id' => $payment_result['transaction_id'] ?? '',
            'subtotal' => $order_subtotal,
            'tax' => $order_tax,
            'total' => $order_total
        ]);

        // Clear cart
        unset($_SESSION['ec_cart']);

        // Redirect to transaction page
        $transaction_page = get_page_by_path('transaction');
        $redirect_url = $transaction_page 
            ? add_query_arg('id', $order_rand_id, get_permalink($transaction_page)) 
            : get_permalink(get_page_by_path('shop'));

        wp_send_json_success([
            'message' => 'Order placed successfully! Order #' . $order_number,
            'redirect' => $redirect_url
        ]);

    } catch (Exception $e) {
        // Rollback on any error
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
add_action('wp_ajax_ec_process_checkout', 'bntm_ajax_ec_process_checkout');
add_action('wp_ajax_nopriv_ec_process_checkout', 'bntm_ajax_ec_process_checkout');
/**
 * Get configured payment method by index
 */
function ec_get_payment_method($index) {
    $payment_methods = json_decode(bntm_get_setting('ec_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods) || !isset($payment_methods[$index])) {
        return null;
    }
    
    return $payment_methods[$index];
}

/**
 * Process Stripe payment
 */
function ec_process_stripe_payment($method, $amount, $order_id) {
    if (empty($method['api_secret_key'])) {
        return ['success' => false, 'message' => 'Stripe API key not configured'];
    }
    
    // TODO: Integrate with actual Stripe API
    // For now, simulate successful payment
    // In production, use Stripe PHP SDK:
    // \Stripe\Stripe::setApiKey($method['api_secret_key']);
    // $charge = \Stripe\Charge::create([...]);
    
    return [
        'success' => true,
        'message' => 'Payment processed via Stripe',
        'transaction_id' => 'stripe_' . time()
    ];
}



/**
 * Process manual payment (GCash, Bank, Cash)
 */
function ec_process_manual_payment($method, $amount, $order_id) {
    // Manual payments are set to pending and require confirmation
    return [
        'success' => true,
        'message' => 'Order placed. Please complete payment using the provided details.',
        'transaction_id' => 'manual_' . time(),
        'status' => 'pending'
    ];
}

/**
 * Main payment processing function
 */
function ec_process_payment($payment_method_index, $amount, $order_id) {
    $method = ec_get_payment_method($payment_method_index);
    
    if (!$method) {
        return ['success' => false, 'message' => 'Invalid payment method'];
    }
    
    switch ($method['type']) {
        case 'stripe':
            return ec_process_stripe_payment($method, $amount, $order_id);
        
        case 'paypal':
            return ec_process_paypal_payment($method, $amount, $order_id);
        
        case 'paymaya':
            return ec_process_paymaya_payment($method, $amount, $order_id);
        
        case 'gcash':
        case 'bank':
        case 'manual':
            return ec_process_manual_payment($method, $amount, $order_id);
        
        default:
            return ['success' => false, 'message' => 'Unsupported payment method'];
    }
}

/* ---------- EC CHECKOUT WITH OP PAYMENT PROCESSING ---------- */
function bntm_ajax_ec_process_checkout_op() {
    check_ajax_referer('ec_checkout_nonce', 'nonce');
    
    if (!isset($_SESSION)) {
        session_start();
    }

    $cart = isset($_SESSION['ec_cart']) ? $_SESSION['ec_cart'] : [];
    
    if (empty($cart)) {
        wp_send_json_error(['message' => 'Cart is empty']);
    }

    global $wpdb;
    $products_table = $wpdb->prefix . 'ec_products';
    $orders_table = $wpdb->prefix . 'ec_orders';
    $order_items_table = $wpdb->prefix . 'ec_order_items';
    $customers_table = $wpdb->prefix . 'ec_customers';
    $inventory_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    $payments_table = $wpdb->prefix . 'op_payments';
    
    // Get form data
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_address = sanitize_textarea_field($_POST['customer_address'] ?? '');
    $op_method_id = intval($_POST['op_method_id'] ?? 0);
    $order_subtotal = floatval($_POST['order_subtotal'] ?? 0);
    $order_tax = floatval($_POST['order_tax'] ?? 0);
    $order_total = floatval($_POST['order_total'] ?? 0);

    if (empty($customer_name) || empty($customer_email) || empty($customer_phone) || empty($customer_address)) {
        wp_send_json_error(['message' => 'Please fill in all customer information']);
    }

    // Get OP payment method
    $business_id = get_current_user_id();
    $payment_method = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $methods_table WHERE id = %d AND is_active = 1",
        $op_method_id, $business_id
    ));

    if (!$payment_method) {
        wp_send_json_error(['message' => 'Invalid payment method']);
    }

    $config = json_decode($payment_method->config, true);

    // Start transaction
    $wpdb->query('START TRANSACTION');

    try {
        // Validate stock
        foreach ($cart as $product_rand => $qty) {
            $qty = max(1, intval($qty));
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $products_table WHERE rand_id = %s LIMIT 1",
                $product_rand
            ));
            
            if (!$product) {
                throw new Exception('Product not found: ' . $product_rand);
            }
            
            if ($product->stock < $qty) {
                throw new Exception($product->name . ' has insufficient stock. Only ' . $product->stock . ' available.');
            }
        }

        
            $customer_id = "";

        // Generate order number
        $order_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $orders_table",
            $business_id
        ));
        $order_number = 'ORD-' . str_pad($order_count + 1, 5, '0', STR_PAD_LEFT);
        $order_rand_id = bntm_rand_id();

        // Determine order and payment status based on gateway
        $payment_method_name = $payment_method->name;
        
        if ($payment_method->gateway === 'manual') {
            $order_status = 'processing';
            $payment_status = 'pending';
        } else {
            // PayPal, PayMaya - awaiting payment completion
            $order_status = 'pending';
            $payment_status = 'pending';
        }

        // Insert order
        $order_inserted = $wpdb->insert($orders_table, [
            'rand_id'        => $order_rand_id,
            'business_id'    => $business_id,
            'customer_id'    => $customer_id,
            'order_number'   => $order_number,
            'subtotal'       => $order_subtotal,
            'tax'            => $order_tax,
            'total'          => $order_total,
            'status'         => $order_status,
            'payment_method' => $payment_method_name,
            'payment_status' => $payment_status,
            'created_at'     => current_time('mysql')
        ], [
            '%s','%d','%d','%s','%f','%f','%f','%s','%s','%s','%s'
        ]);

        if (!$order_inserted) {
            throw new Exception('Failed to create order.');
        }

        $order_id = $wpdb->insert_id;

        // Process each cart item
        foreach ($cart as $product_rand => $qty) {
            $qty = max(1, intval($qty));

            // Get EC product
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $products_table WHERE rand_id = %s LIMIT 1",
                $product_rand
            ));

            $item_subtotal = floatval($product->price) * $qty;

            // Insert order item
            $insert_item = $wpdb->insert($order_items_table, [
                'rand_id'      => bntm_rand_id(),
                'order_id'     => $order_id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'quantity'     => $qty,
                'price'        => $product->price,
                'subtotal'     => $item_subtotal
            ], ['%s','%d','%d','%s','%d','%f','%f']);

            if (!$insert_item) {
                throw new Exception('Failed to insert order items.');
            }

            // **UPDATE BOTH INVENTORY TABLES & LOG BATCH WITH 0 COST**
            $inventory_product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $inventory_table WHERE rand_id = %s LIMIT 1",
                $product->rand_id
            ));

            if ($inventory_product) {
                // Calculate new stock
                $new_stock = max(0, $inventory_product->stock_quantity - $qty);
                
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
                
                // Update ec_products stock and status
                $update_data = ['stock' => $new_stock];
                $update_format = ['%d'];
                
                // If stock is 0, set status to inactive
                if ($new_stock == 0) {
                    $update_data['status'] = 'inactive';
                    $update_format[] = '%s';
                }
                
                $ec_stock_updated = $wpdb->update(
                    $products_table,
                    $update_data,
                    ['rand_id' => $product->rand_id],
                    $update_format,
                    ['%s']
                );
                
                if ($ec_stock_updated === false) {
                    throw new Exception("Failed to update EC product stock for {$product->name}. MySQL Error: " . $wpdb->last_error);
                }
                
                // Log batch as stock_out with 0 cost
                $batch_inserted = $wpdb->insert($batches_table, [
                    'rand_id'          => bntm_rand_id(),
                    'business_id'      => $business_id,
                    'product_id'       => $inventory_product->id,
                    'batch_code'       => 'ECOM-OP-' . $order_number,
                    'type'             => 'stock_out',
                    'reference_number' => $order_number,
                    'quantity'         => $qty,
                    'cost_per_unit'    => 0.00,
                    'total_cost'       => 0.00,
                    'notes'            => "E-Commerce Sale (OP) - Order: {$order_number}, Customer: {$customer_name}, Payment: {$payment_method_name}",
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
                // Inventory product not found - still update EC stock
                error_log("Warning: Inventory product not found for rand_id: {$product->rand_id}. Updating EC stock only.");
                
                $ec_stock_updated = $wpdb->query($wpdb->prepare(
                    "UPDATE $products_table SET stock = stock - %d WHERE rand_id = %s AND stock >= %d",
                    $qty, $product->rand_id, $qty
                ));
                
                if ($ec_stock_updated === false || $ec_stock_updated === 0) {
                    throw new Exception("Failed to update EC product stock for {$product->name}");
                }
                
                // Check if stock is now 0 and set inactive
                $current_stock = $wpdb->get_var($wpdb->prepare(
                    "SELECT stock FROM $products_table WHERE rand_id = %s",
                    $product->rand_id
                ));
                
                if ($current_stock == 0) {
                    $wpdb->update(
                        $products_table,
                        ['status' => 'inactive'],
                        ['rand_id' => $product->rand_id],
                        ['%s'],
                        ['%s']
                    );
                }
            }
        }

        // Create payment record for OP tracking
        $payment_rand_id = bntm_rand_id();
        $payment_inserted = $wpdb->insert($payments_table, [
            'rand_id' => $payment_rand_id,
            'business_id' => $business_id,
            'invoice_id' => 1, // Store EC order_id for reference
            'amount' => $order_total,
            'payment_method' => 'online',
            'payment_gateway' => $payment_method->gateway,
            'status' => 'pending',
            'attempted_at' => current_time('mysql')
        ], [
            '%s','%d','%d','%f','%s','%s','%s','%s'
        ]);

        if (!$payment_inserted) {
            throw new Exception('Failed to create payment record.'. $wpdb->last_error);
        }

        // Commit transaction
        $wpdb->query('COMMIT');

        // Save customer/payment metadata with EC order flag
        update_option('ec_order_' . $order_rand_id . '_customer', [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone' => $customer_phone,
            'address' => $customer_address,
            'payment_method' => $payment_method_name,
            'payment_type' => $payment_method->gateway,
            'payment_source' => 'op',
            'op_method_id' => $op_method_id,
            'transaction_id' => '',
            'subtotal' => $order_subtotal,
            'tax' => $order_tax,
            'total' => $order_total,
            'is_ec_order' => true // Flag to identify EC orders
        ]);

        // Clear cart after successful order creation
        unset($_SESSION['ec_cart']);

        // Process payment based on gateway using EC-specific functions
        // Create a mock invoice object for compatibility
        $mock_invoice = (object)[
            'id' => $order_id,
            'rand_id' => $order_rand_id,
            'business_id' => $business_id,
            'total' => $order_total,
            'currency' => 'PHP',
            'customer_email' => $customer_email
        ];

        $payment_result = [];
        
        switch ($payment_method->gateway) {
            case 'paypal':
                $payment_result = op_ec_process_paypal_payment($mock_invoice, $payment_method, $config, $order_total);
                break;
                
            case 'paymaya':
                $payment_result = op_ec_process_paymaya_payment($mock_invoice, $payment_method, $config, $order_total);
                break;
                
            case 'manual':
                // For manual payment, order is already set to processing/pending
                $payment_result = [
                    'success' => true,
                    'message' => 'Order placed successfully! Please complete your payment using the provided account details. We will verify your payment and process your order.',
                    'redirect' => get_permalink(get_page_by_path('orders')) . '?id=' . $order_rand_id
                ];
                break;
                
            default:
                throw new Exception('Unsupported payment gateway');
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
                $metadata = get_option('ec_order_' . $order_rand_id . '_customer');
                $metadata['transaction_id'] = $payment_result['transaction_id'];
                update_option('ec_order_' . $order_rand_id . '_customer', $metadata);
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
            throw new Exception($payment_result['message'] ?? 'Payment processing failed');
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        $wpdb->query('ROLLBACK');
        
        error_log('EC Checkout OP Error: ' . $e->getMessage());
        
        wp_send_json_error([
            'message' => $e->getMessage(),
            'refresh_page' => strpos($e->getMessage(), 'stock') !== false
        ]);
    }
}

/* ---------- EC PAYMENT GATEWAY IMPLEMENTATIONS ---------- */

function op_ec_process_paypal_payment($invoice, $payment_method, $config, $amount) {
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

    // EC orders redirect to orders page
    $return_url = get_permalink(get_page_by_path('orders')) . '?id=' . $invoice->rand_id . '&payment_success=1&gateway=paypal';
    $cancel_url = get_permalink(get_page_by_path('orders')) . '?id=' . $invoice->rand_id;

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
function op_ec_process_paymaya_payment($invoice, $payment_method, $config, $amount) {
    $mode = $payment_method->mode;
    $base_url = $mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';
    
    // EC orders redirect to orders page
    $success_url = get_permalink(get_page_by_path('transaction')) . '?id=' . $invoice->rand_id . '&payment_success=1&gateway=paymaya';
    $failure_url = get_permalink(get_page_by_path('transaction')) . '?id=' . $invoice->rand_id;
    $cancel_url = get_permalink(get_page_by_path('transaction')) . '?id=' . $invoice->rand_id;
    
    // Ensure amount is properly formatted as float
    $formatted_amount = floatval($amount);
    
    // PayMaya expects amount in decimal format, not cents
    // Added required buyer and items information matching OP format
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
                'name' => 'Order Payment - ' . $invoice->rand_id,
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
            'order_id' => $invoice->rand_id,
            'customer_email' => $invoice->customer_email ?? '',
            'is_ec_order' => 'true'
        ]
    ];
    
    // Log the request for debugging
    error_log('PayMaya EC Checkout Request: ' . json_encode($checkout_data));
    
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
    error_log('PayMaya EC Response Code: ' . $status_code);
    error_log('PayMaya EC Response: ' . print_r($response_data, true));
    
    // Check for both 200 and 201 status codes (PayMaya returns 201 for created)
    if ($status_code !== 200 && $status_code !== 201) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        $error_details = isset($response_data['parameters']) ? ' Parameters: ' . json_encode($response_data['parameters']) : '';
        return ['success' => false, 'message' => 'PayMaya checkout creation failed: ' . $error_message . $error_details . ' (Status: ' . $status_code . ')'];
    }
    
    if (!isset($response_data['checkoutId'])) {
        return ['success' => false, 'message' => 'PayMaya checkout creation failed - no checkout ID returned'];
    }
    
    // Use the redirectUrl provided by PayMaya response instead of constructing it
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

/* ---------- EC ORDER PAYMENT COMPLETION ---------- */
add_action('template_redirect', 'op_ec_handle_payment_success_redirect', 5);
function op_ec_handle_payment_success_redirect() {
    // Check if this is a payment success redirect
    if (!isset($_GET['payment_success']) || $_GET['payment_success'] != 1) {
        return;
    }
    
    // Check if order ID is present
    if (!isset($_GET['id'])) {
        return;
    }
    
    $order_rand_id = sanitize_text_field($_GET['id']);
    $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
    
    error_log("Payment success redirect detected for order: " . $order_rand_id . ", Gateway: " . $gateway);
    
    // Check if this is an EC order
    $metadata = get_option('ec_order_' . $order_rand_id . '_customer');
    
    error_log("Metadata found: " . print_r($metadata, true));
    
    $is_ec_order = isset($metadata['is_ec_order']) && $metadata['is_ec_order'];
    
    if ($is_ec_order && !empty($gateway)) {
        error_log("Attempting to complete EC order payment");
        // Complete EC order payment
        $result = op_complete_ec_order_payment($gateway, $order_rand_id);
        
        if ($result) {
            error_log("EC Order payment completion successful");
        } else {
            error_log("EC Order payment completion failed");
        }
    } else {
        error_log("Not an EC order or no gateway specified. is_ec_order: " . ($is_ec_order ? 'true' : 'false') . ", gateway: " . $gateway);
    }
}
function op_complete_ec_order_payment($gateway, $order_rand_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'ec_orders';
    $payments_table = $wpdb->prefix . 'op_payments';
    
    error_log("Starting payment completion for order: " . $order_rand_id . ", Gateway: " . $gateway);
    
    // Get the order
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE rand_id = %s",
        $order_rand_id
    ));
    
    if (!$order) {
        error_log("EC Order not found: " . $order_rand_id);
        return false;
    }
    
    error_log("Order found - ID: {$order->id}, Current Status: {$order->status}, Payment Status: {$order->payment_status}");
    
    // Check if already paid
    if ($order->payment_status === 'paid') {
        error_log("EC Order already paid: " . $order->id);
        return true;
    }
    
    // Update order status to paid and payment_status to paid
    $order_update = $wpdb->update(
        $orders_table,
        [
            'status' => 'paid',
            'payment_status' => 'paid'
        ],
        ['rand_id' => $order_rand_id],
        ['%s', '%s'],
        ['%s']
    );
    
    if ($order_update === false) {
        error_log("Failed to update order. MySQL Error: " . $wpdb->last_error);
        return false;
    } else {
        error_log("Order updated successfully. Rows affected: " . $order_update);
    }
    
    // Get payment record
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $payments_table WHERE invoice_id = %d AND payment_gateway = %s ORDER BY id DESC LIMIT 1",
        $order->id, $gateway
    ));
    
    if ($payment) {
        error_log("Payment record found - ID: {$payment->id}, Current Status: {$payment->status}");
        
        // Update payment record to completed
        $payment_update = $wpdb->update(
            $payments_table,
            [
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ],
            ['id' => $payment->id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($payment_update === false) {
            error_log("Failed to update payment record. MySQL Error: " . $wpdb->last_error);
        } else {
            error_log("Payment record updated successfully. Rows affected: " . $payment_update);
        }
    } else {
        error_log("Payment record not found for order_id: {$order->id}, gateway: {$gateway}");
        
        // Log all payment records for this order
        $all_payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $payments_table WHERE invoice_id = %d",
            $order->id
        ));
        error_log("All payment records for order {$order->id}: " . print_r($all_payments, true));
    }
    
    error_log("EC Order payment completed: Order #{$order->id}, Status set to 'paid', Gateway: $gateway");
    
    return true;
}
/* ---------- TRANSACTION PAGE ---------- */
function bntm_shortcode_ec_transaction() {
    $order_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($order_id)) {
        return '<div class="bntm-container"><p>Invalid transaction ID.</p></div>';
    }

    global $wpdb;
    $orders_table = $wpdb->prefix . 'ec_orders';
    
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $orders_table WHERE rand_id = %s",
        $order_id
    ));

    if (!$order) {
        return '<div class="bntm-container"><p>Transaction not found.</p></div>';
    }

    $customer_data = get_option('ec_order_' . $order_id . '_customer', []);
    
    $payment_methods = json_decode(bntm_get_setting('ec_payment_methods', '[]'), true);
    $payment_method_data = null;
    
    if (is_array($payment_methods) && !empty($customer_data['payment_type'])) {
        foreach ($payment_methods as $method) {
            if ($method['type'] === $customer_data['payment_type']) {
                $payment_method_data = $method;
                break;
            }
        }
    }

    ob_start();
    ?>
    <div class="bntm-container">
        
            <h1>Transaction Details</h1>
        <div class="bntm-content">
            <div class="transaction-status-banner status-<?php echo esc_attr($order->status); ?>">
                <h2>Order Status: <?php echo esc_html(ucfirst($order->status)); ?></h2>
                <p class="order-id">Order ID: #<?php echo esc_html($order->rand_id); ?></p>
            </div>

            <div class="bntm-form-section">
                <h3>Order Information</h3>
                <table class="transaction-table">
                    <tr>
                        <td><strong>Order Date:</strong></td>
                        <td><?php echo date('F j, Y, g:i a', strtotime($order->created_at)); ?></td>
                    </tr>
                    <?php if (!empty($customer_data['subtotal'])): ?>
                    <tr>
                        <td><strong>Subtotal:</strong></td>
                        <td><?php echo ec_format_price($customer_data['subtotal']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($customer_data['tax']) && $customer_data['tax'] > 0): 
                        $tax_rate = bntm_get_setting('ec_tax_rate', '0');
                    ?>
                    <tr>
                        <td><strong>Tax (<?php echo number_format($tax_rate, 2); ?>%):</strong></td>
                        <td><?php echo ec_format_price($customer_data['tax']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Total Amount:</strong></td>
                        <td style="font-size: 20px; color: #059669; font-weight: 700;">
                            <?php echo ec_format_price($order->total); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><span class="status-badge status-<?php echo esc_attr($order->status); ?>">
                            <?php echo esc_html(ucfirst($order->status)); ?>
                        </span></td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($customer_data)): ?>
            <div class="bntm-form-section">
                <h3>Customer Details</h3>
                <table class="transaction-table">
                    <tr>
                        <td><strong>Name:</strong></td>
                        <td><?php echo esc_html($customer_data['name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong></td>
                        <td><?php echo esc_html($customer_data['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Phone:</strong></td>
                        <td><?php echo esc_html($customer_data['phone']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Address:</strong></td>
                        <td><?php echo esc_html($customer_data['address']); ?></td>
                    </tr>
                </table>
            </div>

            <div class="bntm-form-section">
                <h3>Payment Information</h3>
                <table class="transaction-table">
                    <tr>
                        <td><strong>Payment Method:</strong></td>
                        <td><?php echo esc_html($customer_data['payment_method']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Transaction ID:</strong></td>
                        <td><?php echo esc_html($customer_data['transaction_id']); ?></td>
                    </tr>
                    
                    <?php if ($payment_method_data): ?>
                        <?php if (!empty($payment_method_data['description'])): ?>
                        <tr>
                            <td><strong>Instructions:</strong></td>
                            <td><?php echo esc_html($payment_method_data['description']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($payment_method_data['account_name']) || !empty($payment_method_data['account_number'])): ?>
                        <tr>
                            <td colspan="2">
                                <div style="padding: 15px; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; margin-top: 10px;">
                                    <strong>Payment Details:</strong><br>
                                    <?php if (!empty($payment_method_data['account_name'])): ?>
                                        Account Name: <strong><?php echo esc_html($payment_method_data['account_name']); ?></strong><br>
                                    <?php endif; ?>
                                    <?php if (!empty($payment_method_data['account_number'])): ?>
                                        Account Number: <strong><?php echo esc_html($payment_method_data['account_number']); ?></strong>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if ($order->status === 'pending'): ?>
                    <tr>
                        <td colspan="2">
                            <div style="padding: 15px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px; margin-top: 10px;">
                                <strong>Payment Pending:</strong> Please complete your payment to process this order.
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>

            <div style="margin-top: 30px; text-align: center;">
                <a href="<?php echo get_permalink(get_page_by_path('shop')); ?>" class="bntm-btn bntm-btn-primary">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>

    <style>
    /* Keep existing styles */
    .transaction-status-banner {
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 30px;
    }
    .transaction-status-banner.status-pending {
        background: #fef3c7;
        border: 2px solid #f59e0b;
    }
    .transaction-status-banner.status-paid,
    .transaction-status-banner.status-completed {
        background: #d1fae5;
        border: 2px solid #059669;
    }
    .transaction-status-banner.status-cancelled {
        background: #fee2e2;
        border: 2px solid #dc2626;
    }
    .transaction-status-banner h2 {
        margin: 0 0 10px 0;
        color: #1f2937;
    }
    .order-id {
        font-size: 18px;
        color: #6b7280;
        margin: 0;
    }
    .transaction-table {
        width: 100%;
    }
    .transaction-table td {
        padding: 12px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .transaction-table td:first-child {
        width: 200px;
        color: #6b7280;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 500;
    }
    .status-badge.status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge.status-paid,
    .status-badge.status-completed {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    </style>
    <?php
    $content = ob_get_clean();
    return $content;
}
add_shortcode('ec_transaction', 'bntm_shortcode_ec_transaction');
/* ---------- AJAX HANDLERS ---------- */


function bntm_ajax_ec_update_order_status() {
    check_ajax_referer('ec_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'ec_orders';
    $business_id = get_current_user_id();

    $order_id = sanitize_text_field($_POST['order_id']);
    $status = sanitize_text_field($_POST['status']);

    // Get current order to check if it's being paid
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE rand_id = %s",
        $order_id, $business_id
    ));

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found.']);
    }

    $result = $wpdb->update(
        $table,
        ['status' => $status],
        ['rand_id' => $order_id],
        ['%s'],
        ['%s', '%d']
    );


    if ($result !== false) {
        wp_send_json_success(['message' => 'Order status updated!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update order status.']);
    }
}


/* ---------- HELPER FUNCTIONS ---------- */

function ec_render_recent_orders($business_id, $limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'ec_orders';
    
    // Get current page from URL parameter
    $current_page = isset($_GET['ec_page']) ? max(1, intval($_GET['ec_page'])) : 1;
    $offset = ($current_page - 1) * $limit;
    
    // Get total count for pagination
    $total_orders = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table"
    );
    
    $total_pages = ceil($total_orders / $limit);
    
    // Get orders for current page
    $orders = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ));
    
    if (empty($orders) && $current_page == 1) {
        return '<p>No recent orders.</p>';
    }
    
    ob_start();
    ?>
    <table class="bntm-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo esc_html($order->rand_id); ?></td>
                        <td><?php echo ec_format_price($order->total); ?></td>
                        <td>
                            <span class="bntm-status-<?php echo esc_attr($order->status); ?>">
                                <?php echo esc_html(ucfirst($order->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date('M d, Y', strtotime($order->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No orders found on this page.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="ec-pagination">
            <?php
            $base_url = remove_query_arg('ec_page');
            
            // Previous button
            if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('ec_page', $current_page - 1, $base_url)); ?>" class="ec-page-btn">
                    &laquo; Previous
                </a>
            <?php endif; ?>
            
            <!-- Page numbers -->
            <div class="ec-page-numbers">
                <?php
                // Show first page
                if ($current_page > 3) {
                    echo '<a href="' . esc_url(add_query_arg('ec_page', 1, $base_url)) . '" class="ec-page-num">1</a>';
                    if ($current_page > 4) {
                        echo '<span class="ec-page-dots">...</span>';
                    }
                }
                
                // Show pages around current page
                for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                    $active_class = ($i == $current_page) ? ' active' : '';
                    echo '<a href="' . esc_url(add_query_arg('ec_page', $i, $base_url)) . '" class="ec-page-num' . $active_class . '">' . $i . '</a>';
                }
                
                // Show last page
                if ($current_page < $total_pages - 2) {
                    if ($current_page < $total_pages - 3) {
                        echo '<span class="ec-page-dots">...</span>';
                    }
                    echo '<a href="' . esc_url(add_query_arg('ec_page', $total_pages, $base_url)) . '" class="ec-page-num">' . $total_pages . '</a>';
                }
                ?>
            </div>
            
            <!-- Next button -->
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('ec_page', $current_page + 1, $base_url)); ?>" class="ec-page-btn">
                    Next &raquo;
                </a>
            <?php endif; ?>
            
            <div class="ec-page-info">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                (<?php echo $total_orders; ?> total orders)
            </div>
        </div>
    <?php endif; ?>
    
    <style>
    .bntm-status-pending { color: #d97706; font-weight: 500; }
    .bntm-status-paid { color: #059669; font-weight: 500; }
    .bntm-status-processing { color: #2563eb; font-weight: 500; }
    .bntm-status-completed { color: #059669; font-weight: 500; }
    .bntm-status-cancelled { color: #dc2626; font-weight: 500; }
    
    .ec-pagination { display: flex; align-items: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    .ec-page-numbers { display: flex; gap: 5px; }
    .ec-page-btn, .ec-page-num { padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
    .ec-page-btn:hover, .ec-page-num:hover { background: var(--bntm-primary-hover); color: white; }
    .ec-page-num.active { background: var(--bntm-primary); color: white; border-color: var(--bntm-primary); }
    .ec-page-dots { padding: 6px; color: #999; }
    .ec-page-info { margin-left: auto; color: #666; font-size: 14px; }
    </style>
    <?php
    return ob_get_clean();
}
/**
 * Format price with currency symbol
 */
function ec_format_price($amount="") {
    $currency = bntm_get_setting('ec_currency', 'USD');
    
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'PHP' => '₱'
    ];
    
    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : '$';
    
    return $symbol . number_format($amount, 2);
}
