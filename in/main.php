<?php
/**
 * Module Name: Inventory Management
 * Module Slug: in
 * Description: Complete inventory management solution with products, batches, and cost tracking
 * Version: 1.0.1
 * Author: Your Name
 * Icon: 📦
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_IN_PATH', dirname(__FILE__) . '/');
define('BNTM_IN_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_in_get_pages() {
    return [
        'Inventory' => '[in_dashboard]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_in_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'in_products' => "CREATE TABLE {$prefix}in_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(50),
            barcode VARCHAR(100) UNIQUE,
            inventory_type VARCHAR(50) DEFAULT 'Product',
            cost_per_unit DECIMAL(10,2) DEFAULT 0,
            selling_price DECIMAL(10,2) NOT NULL,
            stock_quantity INT DEFAULT 0,
            reorder_level INT DEFAULT 10,
            description TEXT,
            image VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_sku (sku),
            INDEX idx_barcode (barcode),
            INDEX idx_stock (stock_quantity),
            INDEX idx_type (inventory_type)
        ) {$charset};",
        
        'in_batches' => "CREATE TABLE {$prefix}in_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NOT NULL,
            batch_code VARCHAR(100) NOT NULL,
            type ENUM('stock_in', 'stock_out') DEFAULT 'stock_in',
            quantity INT NOT NULL,
            cost_per_unit DECIMAL(10,2) DEFAULT 0,
            total_cost DECIMAL(10,2) NOT NULL,
            reference_number VARCHAR(100),
            manufacture_date DATE,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_product (product_id),
            INDEX idx_batch_code (batch_code),
            INDEX idx_type (type),
            INDEX idx_date (manufacture_date)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_in_get_shortcodes() {
    return [
        'in_dashboard' => 'bntm_in_shortcode_dashboard'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_in_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_in_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_in_add_product', 'bntm_ajax_in_add_product');
add_action('wp_ajax_in_update_product', 'bntm_ajax_in_update_product');
add_action('wp_ajax_in_delete_product', 'bntm_ajax_in_delete_product');
add_action('wp_ajax_in_add_batch', 'bntm_ajax_in_add_batch');
add_action('wp_ajax_in_delete_batch', 'bntm_ajax_in_delete_batch');
add_action('wp_ajax_in_import_batch_expense', 'bntm_ajax_in_import_batch_expense');
add_action('wp_ajax_in_revert_batch_expense', 'bntm_ajax_in_revert_batch_expense');

/* ---------- MAIN INVENTORY SHORTCODE ---------- */
function bntm_in_shortcode_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Inventory dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-inventory-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=products" class="bntm-tab <?php echo $active_tab === 'products' ? 'active' : ''; ?>">Products</a>
            <a href="?tab=batches" class="bntm-tab <?php echo $active_tab === 'batches' ? 'active' : ''; ?>">Batches</a>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import" class="bntm-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">Import to Finance</a>
            <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo in_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'products'): ?>
                <?php echo in_products_tab($business_id); ?>
            <?php elseif ($active_tab === 'batches'): ?>
                <?php echo in_batches_tab($business_id); ?>
            <?php elseif ($active_tab === 'import'): ?>
                <?php echo in_import_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo in_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Inventory Management', $content);
}

/* ---------- TAB FUNCTIONS ---------- */function in_overview_tab($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    
    // Get statistics
    $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
    
    $total_stock = $wpdb->get_var("SELECT SUM(stock_quantity) FROM $products_table");
    
    $low_stock_items = $wpdb->get_var(
        "SELECT COUNT(*) FROM $products_table WHERE stock_quantity <= reorder_level AND stock_quantity > 0"
    );
    
    $out_of_stock = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE stock_quantity = 0");
    
    $total_inventory_value = $wpdb->get_var("SELECT SUM(stock_quantity * cost_per_unit) FROM $products_table");
    
    $potential_revenue = $wpdb->get_var("SELECT SUM(stock_quantity * selling_price) FROM $products_table");
    
    $stock_in_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM $batches_table WHERE type = 'stock_in' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    $stock_out_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM $batches_table WHERE type = 'stock_out' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    // Get top products by current stock
    $top_products = $wpdb->get_results(
        "SELECT name, stock_quantity, reorder_level 
        FROM $products_table 
        WHERE stock_quantity > 0
        ORDER BY stock_quantity DESC 
        LIMIT 10",
        ARRAY_A
    );
    
    // If no products, create empty array
    if (empty($top_products)) {
        $top_products = [];
    }
    
    // Inventory by type
    $inventory_by_type = $wpdb->get_results(
        "SELECT inventory_type, COUNT(*) as count, SUM(stock_quantity) as total_stock
        FROM $products_table 
        GROUP BY inventory_type",
        ARRAY_A
    );
    
    // Stock status distribution
    $stock_status = $wpdb->get_results("
        SELECT 
            CASE 
                WHEN stock_quantity = 0 THEN 'Out of Stock'
                WHEN stock_quantity <= reorder_level THEN 'Low Stock'
                WHEN stock_quantity > reorder_level THEN 'In Stock'
            END as status,
            COUNT(*) as count
        FROM $products_table
        GROUP BY status
    ", ARRAY_A);
    
    // Recent transactions
    $recent_transactions = $wpdb->get_results(
        "SELECT b.*, p.name as product_name 
        FROM $batches_table b
        LEFT JOIN $products_table p ON b.product_id = p.id
        ORDER BY b.created_at DESC
        LIMIT 10"
    );
    
    // Low stock products
    $low_stock_products = $wpdb->get_results(
        "SELECT * FROM $products_table 
        WHERE stock_quantity <= reorder_level
        ORDER BY stock_quantity ASC
        LIMIT 5"
    );

    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <div class="in-dashboard-stats">
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Total Products</h3>
                <p class="in-stat-number"><?php echo esc_html($total_products); ?></p>
                <small>Unique items tracked</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Total Stock Units</h3>
                <p class="in-stat-number"><?php echo esc_html($total_stock ?: 0); ?></p>
                <small>Items in inventory</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Inventory Value</h3>
                <p class="in-stat-number">₱<?php echo number_format($total_inventory_value ?: 0, 2); ?></p>
                <small>Total cost value</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Potential Revenue</h3>
                <p class="in-stat-number">₱<?php echo number_format($potential_revenue ?: 0, 2); ?></p>
                <small>If all stock sold</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Low Stock Alerts</h3>
                <p class="in-stat-number" style="color: <?php echo $low_stock_items > 0 ? '#dc2626' : '#059669'; ?>">
                    <?php echo esc_html($low_stock_items); ?>
                </p>
                <small>At or below reorder level</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Out of Stock</h3>
                <p class="in-stat-number" style="color: <?php echo $out_of_stock > 0 ? '#991b1b' : '#059669'; ?>">
                    <?php echo esc_html($out_of_stock); ?>
                </p>
                <small>Items with 0 stock</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <polyline points="19 12 12 19 5 12"></polyline>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Stock In (30 days)</h3>
                <p class="in-stat-number" style="color: #059669;"><?php echo esc_html($stock_in_count); ?></p>
                <small>Incoming transactions</small>
            </div>
        </div>
        
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-danger">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="19" x2="12" y2="5"></line>
                    <polyline points="5 12 12 5 19 12"></polyline>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3>Stock Out (30 days)</h3>
                <p class="in-stat-number" style="color: #dc2626;"><?php echo esc_html($stock_out_count); ?></p>
                <small>Outgoing transactions</small>
            </div>
        </div>
    </div>

    <div class="in-charts-grid">
        <div class="in-chart-card in-chart-large">
            <h3>Top 10 Products by Stock Level</h3>
            <canvas id="productStockChart"></canvas>
        </div>
        
        <div class="in-chart-card">
            <h3>Inventory by Type</h3>
            <canvas id="inventoryTypeChart"></canvas>
        </div>
        
        <div class="in-chart-card">
            <h3>Stock Status</h3>
            <canvas id="stockStatusChart"></canvas>
        </div>
    </div>

    <?php if (!empty($low_stock_products)): ?>
    <div class="in-alert-section">
        <h3>Low Stock Alerts</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Type</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product->name); ?></td>
                        <td><?php echo esc_html($product->inventory_type); ?></td>
                        <td><?php echo esc_html($product->stock_quantity); ?></td>
                        <td><?php echo esc_html($product->reorder_level); ?></td>
                        <td>
                            <?php if ($product->stock_quantity == 0): ?>
                                <span style="color: #991b1b; font-weight: 500;">Out of Stock</span>
                            <?php else: ?>
                                <span style="color: #dc2626; font-weight: 500;">Low Stock</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="in-recent-section">
        <h3>Recent Transactions</h3>
        <?php if (empty($recent_transactions)): ?>
            <p>No transactions recorded yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $txn): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($txn->created_at)); ?></td>
                            <td>
                                <?php if ($txn->type === 'stock_in'): ?>
                                    <span style="color: #059669; font-weight: 500;">Stock In</span>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 500;">Stock Out</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($txn->reference_number ?: $txn->batch_code); ?></td>
                            <td><?php echo esc_html($txn->product_name); ?></td>
                            <td><?php echo esc_html($txn->quantity); ?></td>
                            <td>₱<?php echo number_format($txn->total_cost, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
    .in-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .in-stat-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .in-stat-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .in-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .in-stat-icon-primary {
        background: var(--bntm-primary, #374151);
        color: #ffffff;
    }
    
    .in-stat-icon-success {
        background: #10b981;
        color: #ffffff;
    }
    
    .in-stat-icon-warning {
        background: #f59e0b;
        color: #ffffff;
    }
    
    .in-stat-icon-danger {
        background: #ef4444;
        color: #ffffff;
    }
    
    .in-stat-content {
        flex: 1;
    }
    
    .in-stat-content h3 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .in-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        line-height: 1;
    }
    
    .in-stat-content small {
        color: #9ca3af;
        font-size: 12px;
    }
    
    .in-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .in-chart-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .in-chart-large {
        grid-column: 1 / -1;
    }
    
    .in-chart-card h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .in-chart-card canvas {
        max-height: 300px;
    }
    
    .in-alert-section,
    .in-recent-section {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        margin-bottom: 20px;
    }
    
    .in-alert-section h3,
    .in-recent-section h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    @media (max-width: 768px) {
        .in-chart-card {
            grid-column: 1 / -1;
        }
    }
    </style>
    
    <script>
    (function() {
        const topProducts = <?php echo json_encode($top_products); ?>;
        
        // Generate distinct colors for each product
        const colors = [
            '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
            '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#14b8a6'
        ];
        
        // Product Stock Chart (Horizontal Bar Chart)
        const productStockCtx = document.getElementById('productStockChart');
        if (productStockCtx && topProducts.length > 0) {
            const productNames = topProducts.map(p => p.name);
            const stockQuantities = topProducts.map(p => parseInt(p.stock_quantity));
            const reorderLevels = topProducts.map(p => parseInt(p.reorder_level));
            
            new Chart(productStockCtx, {
                type: 'bar',
                data: {
                    labels: productNames,
                    datasets: [
                        {
                            label: 'Current Stock',
                            data: stockQuantities,
                            backgroundColor: colors.slice(0, topProducts.length),
                            borderColor: colors.slice(0, topProducts.length),
                            borderWidth: 1
                        },
                        {
                            label: 'Reorder Level',
                            data: reorderLevels,
                            backgroundColor: '#f59e0b40',
                            borderColor: '#f59e0b',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            type: 'line',
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                font: { size: 12 },
                                color: '#374151',
                                usePointStyle: true
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
                                    return context.dataset.label + ': ' + context.parsed.x + ' units';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: '#f3f4f6'
                            },
                            ticks: {
                                color: '#6b7280',
                                font: { size: 12 },
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: 'Stock Quantity',
                                color: '#374151',
                                font: { size: 12, weight: '600' }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#6b7280',
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });
        } else if (productStockCtx) {
            // Show message if no data
            const ctx = productStockCtx.getContext('2d');
            ctx.font = '14px sans-serif';
            ctx.fillStyle = '#6b7280';
            ctx.textAlign = 'center';
            ctx.fillText('No product data available', productStockCtx.width / 2, productStockCtx.height / 2);
        }
        
        // Inventory by Type Chart (Doughnut)
        const inventoryTypeCtx = document.getElementById('inventoryTypeChart');
        if (inventoryTypeCtx) {
            const inventoryTypes = <?php echo json_encode($inventory_by_type); ?>;
            const primaryColor = getComputedStyle(document.documentElement)
                .getPropertyValue('--bntm-primary').trim() || '#374151';
            
            if (inventoryTypes.length > 0) {
                new Chart(inventoryTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: inventoryTypes.map(t => t.inventory_type),
                        datasets: [{
                            data: inventoryTypes.map(t => parseInt(t.total_stock)),
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
                                        return label + ': ' + value + ' units';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Stock Status Chart (Pie)
        const stockStatusCtx = document.getElementById('stockStatusChart');
        if (stockStatusCtx) {
            const stockStatus = <?php echo json_encode($stock_status); ?>;
            
            if (stockStatus.length > 0) {
                new Chart(stockStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: stockStatus.map(s => s.status),
                        datasets: [{
                            data: stockStatus.map(s => parseInt(s.count)),
                            backgroundColor: [
                                '#10b981',
                                '#f59e0b',
                                '#ef4444'
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
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
function in_products_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY id DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('in_nonce');
    $upload_nonce = wp_create_nonce('in_upload_image');
    
    $current_products = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $limits = get_option('bntm_table_limits', []);
    $product_limit = isset($limits[$table]) ? $limits[$table] : 0;
    $limit_text = $product_limit > 0 ? " ({$current_products}/{$product_limit})" : " ({$current_products})";
    $limit_reached = $product_limit > 0 && $current_products >= $product_limit;

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
       <button id="open-add-product-modal" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled' : ''; ?>>
           + Add New Product/Item<?php echo $limit_text; ?>
       </button>
       <?php if ($limit_reached): ?>
           <div style="background: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: 4px; margin-top: 10px;">
               <strong>⚠️ Product Limit Reached:</strong> Maximum of <?php echo $product_limit; ?> products allowed.
           </div>
       <?php endif; ?>
   </div>

    <!-- Add Product Modal -->
    <div id="add-product-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Product/Item</h3>
            <form id="in-add-product-form" class="bntm-form">
                <!-- Product Image Upload -->
                <div class="bntm-form-group">
                    <label>Product Image</label>
                    
                    <div class="in-product-image-preview" id="product-image-preview" style="display: none;">
                        <img src="" alt="Product Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-product-image">✕</button>
                    </div>
                    
                    <div class="bntm-upload-area" id="product-upload-area">
                        <input type="file" id="product-image-upload" accept="image/*" style="display: none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="product-upload-btn">
                            Choose Image
                        </button>
                        <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    
                    <input type="hidden" id="product_image" name="product_image" value="">
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Inventory Type *</label>
                        <select name="inventory_type" required>
                            <option value="Product">Product</option>
                            <option value="Raw Material">Raw Material</option>
                            <option value="Finished Goods">Finished Goods</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" placeholder="Auto-generated if blank">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Barcode</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" id="product-barcode" name="barcode" placeholder="e.g., 8901234567890" style="flex: 1;">
                            <button type="button" class="bntm-btn bntm-btn-secondary" id="open-barcode-scanner-btn" style="white-space: nowrap;display:none;">
                                📱 Scan
                            </button>
                        </div>
                        <small>Optional - Click Scan to capture with device camera (Coming Soon)</small>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Product/Item Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Cost Per Unit</label>
                        <input type="number" name="cost_per_unit" step="0.01" value="0" min="0">
                        <small>Your cost to acquire/produce this item</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Selling Price *</label>
                        <input type="number" name="selling_price" step="0.01" required min="0">
                        <small>Price you sell this item for</small>
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Initial Stock Quantity</label>
                        <input type="number" name="initial_stock" value="0" min="0">
                        <small>Starting inventory (optional)</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Reorder Level *</label>
                        <input type="number" name="reorder_level" value="10" required min="0">
                        <small>Alert when stock falls below this</small>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Product description..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Product</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Barcode Scanner Modal -->
    <div id="barcode-scanner-modal" class="in-modal" style="display: none;z-index:1001;">
        <div class="in-modal-content" style="max-width: 500px;">
            <h3>Scan Barcode</h3>
            <div id="scanner-container" style="text-align: center;">
                <video id="barcode-video" style="width: 100%; max-width: 400px; border-radius: 8px; background: #000; display: none;"></video>
                <canvas id="barcode-canvas" style="display: none;"></canvas>
                <div id="scanner-status" style="padding: 20px; text-align: center; color: #6b7280;">
                    <p>Initializing camera...</p>
                </div>
            </div>
            <div style="margin-top: 15px; text-align: center;">
                <input type="text" id="manual-barcode-input" placeholder="Or enter barcode manually" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 4px; margin-bottom: 10px;">
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="button" class="bntm-btn-primary" id="confirm-barcode-btn" style="flex: 1;">Confirm</button>
                <button type="button" class="bntm-btn-secondary close-barcode-scanner" style="flex: 1;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="edit-product-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Product/Item</h3>
            <form id="in-edit-product-form" class="bntm-form">
                <input type="hidden" id="edit-product-id" name="product_id">
                
                <!-- Product Image Upload -->
                <div class="bntm-form-group">
                    <label>Product Image</label>
                    
                    <div class="in-product-image-preview" id="edit-product-image-preview" style="display: none;">
                        <img src="" alt="Product Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-edit-product-image">✕</button>
                    </div>
                    
                    <div class="bntm-upload-area" id="edit-product-upload-area">
                        <input type="file" id="edit-product-image-upload" accept="image/*" style="display: none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="edit-product-upload-btn">
                            Choose Image
                        </button>
                        <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    
                    <input type="hidden" id="edit-product_image" name="product_image" value="">
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Inventory Type *</label>
                        <select id="edit-inventory-type" name="inventory_type" required>
                            <option value="Product">Product</option>
                            <option value="Raw Material">Raw Material</option>
                            <option value="Finished Goods">Finished Goods</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>SKU</label>
                        <input type="text" id="edit-sku" name="sku">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Barcode</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="text" id="edit-barcode" name="barcode" style="flex: 1;">
                            <button type="button" class="bntm-btn bntm-btn-secondary" id="edit-open-barcode-scanner-btn" style="white-space: nowrap;display:none;">
                                📱 Scan
                            </button>
                        </div>
                        <small>Optional - Click Scan to capture with device camera (Coming Soon)</small>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Product/Item Name *</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Cost Per Unit</label>
                        <input type="number" id="edit-cost-per-unit" name="cost_per_unit" step="0.01" min="0">
                    </div>
                    <div class="bntm-form-group">
                        <label>Selling Price *</label>
                        <input type="number" id="edit-selling-price" name="selling_price" step="0.01" required min="0">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Current Stock</label>
                        <input type="number" id="edit-current-stock" readonly style="background: #f3f4f6;">
                        <small>Use Stock In/Out to adjust</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Stock Adjustment</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="number" id="edit-stock-adjustment" name="stock_adjustment" value="0" style="flex: 1;">
                            <select id="edit-adjustment-type" name="adjustment_type" style="width: 100px;">
                                <option value="add">Add</option>
                                <option value="subtract">Subtract</option>
                                <option value="set">Set To</option>
                            </select>
                        </div>
                        <small>Manually adjust stock quantity</small>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Reorder Level *</label>
                    <input type="number" id="edit-reorder-level" name="reorder_level" required min="0">
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-description" name="description" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Product</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>All Products (<?php echo count($products); ?>)</h3>
        <?php if (empty($products)): ?>
            <p>No products found. Add your first product above.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Cost</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr data-product-id="<?php echo $product->id; ?>">
                            <td>
                                <?php if ($product->image): ?>
                                    <img src="<?php echo esc_url($product->image); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #e5e7eb; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #9ca3af;">📦</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($product->inventory_type); ?></td>
                            <td><?php echo esc_html($product->name); ?></td>
                            <td><?php echo esc_html($product->sku); ?></td>
                            <td><?php echo esc_html($product->barcode ?: 'N/A'); ?></td>
                            <td>₱<?php echo number_format($product->cost_per_unit, 2); ?></td>
                            <td>₱<?php echo number_format($product->selling_price, 2); ?></td>
                            <td><?php echo esc_html($product->stock_quantity); ?></td>
                            <td><?php echo esc_html($product->reorder_level); ?></td>
                            <td>
                                <?php if ($product->stock_quantity == 0): ?>
                                    <span style="color: #991b1b; font-weight: 500;">Out of Stock</span>
                                <?php elseif ($product->stock_quantity <= $product->reorder_level): ?>
                                    <span style="color: #dc2626; font-weight: 500;">Low Stock</span>
                                <?php else: ?>
                                    <span style="color: #059669; font-weight: 500;">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="bntm-btn-small in-edit-product" 
                                    data-id="<?php echo $product->id; ?>"
                                    data-name="<?php echo esc_attr($product->name); ?>"
                                    data-sku="<?php echo esc_attr($product->sku); ?>"
                                    data-barcode="<?php echo esc_attr($product->barcode); ?>"
                                    data-type="<?php echo esc_attr($product->inventory_type); ?>"
                                    data-cost="<?php echo $product->cost_per_unit; ?>"
                                    data-price="<?php echo $product->selling_price; ?>"
                                    data-stock="<?php echo $product->stock_quantity; ?>"
                                    data-reorder="<?php echo $product->reorder_level; ?>"
                                    data-description="<?php echo esc_attr($product->description); ?>"
                                    data-image="<?php echo esc_attr($product->image); ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger in-delete-product" data-id="<?php echo esc_attr($product->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

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
    .bntm-upload-area {
        border: 2px dashed #d1d5db;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s;
        background: #f9fafb;
    }
    .bntm-upload-area.dragover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .in-product-image-preview {
        position: relative;
        display: inline-block;
        margin-bottom: 15px;
        padding: 10px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }
    .in-product-image-preview img {
        max-width: 200px;
        max-height: 200px;
        display: block;
    }
    .bntm-btn-remove-logo {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .bntm-btn-remove-logo:hover {
        background: #dc2626;
    }
    </style>
<script>
(function() {
    var uploadNonce = '<?php echo $upload_nonce; ?>';
    var barcodeScannerActive = false;
    var barcodeScannerStream = null;
    var currentScannerTarget = 'product';
    
    // ========== IMAGE UPLOAD SETUP ==========
    function setupImageUpload(prefix) {
        const uploadArea = document.getElementById(prefix + 'product-upload-area');
        const uploadBtn = document.getElementById(prefix + 'product-upload-btn');
        const fileInput = document.getElementById(prefix + 'product-image-upload');
        const imagePreview = document.getElementById(prefix + 'product-image-preview');
        const removeBtn = document.getElementById('remove-' + prefix + 'product-image');
        const hiddenInput = document.getElementById(prefix + 'product_image');
        
        if (!uploadBtn) return;
        
        uploadBtn.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                uploadProductImage(this.files[0], prefix);
            }
        });
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                uploadProductImage(e.dataTransfer.files[0], prefix);
            }
        });
        
        removeBtn.addEventListener('click', function() {
            imagePreview.style.display = 'none';
            uploadArea.style.display = 'block';
            hiddenInput.value = '';
        });
    }
    
    function uploadProductImage(file, prefix) {
        if (!file.type.match('image.*')) {
            alert('Please select an image file');
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'in_upload_product_image');
        formData.append('image', file);
        formData.append('_ajax_nonce', uploadNonce);
        
        const uploadBtn = document.getElementById(prefix + 'product-upload-btn');
        const uploadArea = document.getElementById(prefix + 'product-upload-area');
        const imagePreview = document.getElementById(prefix + 'product-image-preview');
        const hiddenInput = document.getElementById(prefix + 'product_image');
        
        uploadBtn.disabled = true;
        uploadBtn.textContent = 'Uploading...';
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(json => {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Choose Image';
            
            if (json.success) {
                imagePreview.querySelector('img').src = json.data.url;
                imagePreview.style.display = 'inline-block';
                uploadArea.style.display = 'none';
                hiddenInput.value = json.data.url;
            } else {
                alert('Upload failed: ' + json.data);
            }
        })
        .catch(err => {
            uploadBtn.disabled = false;
            uploadBtn.textContent = 'Choose Image';
            alert('Upload error: ' + err.message);
        });
    }
    
    setupImageUpload('');
    setupImageUpload('edit-');
    
    // ========== BARCODE SCANNER SETUP ==========
    async function initBarcodeScanner() {
        const video = document.getElementById('barcode-video');
        const status = document.getElementById('scanner-status');
        
        console.log('Initializing barcode scanner...');
        
        try {
            // Request camera access
            const constraints = {
                video: {
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
            
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            console.log('Camera stream acquired successfully');
            
            video.srcObject = stream;
            barcodeScannerStream = stream;
            barcodeScannerActive = true;
            
            // Wait for video to be ready
            video.onloadedmetadata = function() {
                console.log('Video metadata loaded');
                video.play();
                video.style.display = 'block';
                status.style.display = 'none';
                startBarcodeDetection(video);
            };
            
        } catch (err) {
            console.error('Camera error:', err);
            let errorMsg = 'Camera access error.';
            
            if (err.name === 'NotAllowedError') {
                errorMsg = 'Camera permission denied. Please allow camera access in your browser settings.';
            } else if (err.name === 'NotFoundError') {
                errorMsg = 'No camera found on this device.';
            } else if (err.name === 'NotReadableError') {
                errorMsg = 'Camera is already in use by another application.';
            }
            
            status.innerHTML = '<p style="color: #dc2626;">' + errorMsg + '</p><p style="margin-top: 10px;">Use manual entry below.</p>';
            status.style.display = 'block';
            video.style.display = 'none';
        }
    }
    
    async function startBarcodeDetection(video) {
        const canvas = document.getElementById('barcode-canvas');
        const ctx = canvas.getContext('2d');
        const manualInput = document.getElementById('manual-barcode-input');
        
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        
        let detectionActive = true;
        
        const detect = setInterval(async () => {
            if (!barcodeScannerActive || !detectionActive) {
                clearInterval(detect);
                return;
            }
            
            try {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    // Draw current frame to canvas
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    // Try to detect barcode
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    
                    // Simple barcode detection (you can enhance this)
                    // For now, we'll just show the camera and let user use manual entry
                    // The ZbarWasm library can be complex to set up
                }
            } catch (e) {
                console.error('Detection error:', e);
            }
        }, 300);
    }
    
    function stopBarcodeScanner() {
        console.log('Stopping barcode scanner');
        barcodeScannerActive = false;
        
        if (barcodeScannerStream) {
            barcodeScannerStream.getTracks().forEach(track => {
                track.stop();
                console.log('Stream track stopped');
            });
            barcodeScannerStream = null;
        }
        
        const video = document.getElementById('barcode-video');
        video.style.display = 'none';
    }
    
    // ========== BARCODE SCANNER MODAL CONTROLS ==========
    document.getElementById('open-barcode-scanner-btn').addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Opening barcode scanner for product form');
        currentScannerTarget = 'product';
        document.getElementById('manual-barcode-input').value = '';
        document.getElementById('barcode-scanner-modal').style.display = 'flex';
        document.getElementById('scanner-status').innerHTML = '<p>Requesting camera access...</p>';
        document.getElementById('barcode-video').style.display = 'none';
        
        // Small delay to ensure modal is visible
        setTimeout(() => {
            initBarcodeScanner();
        }, 100);
    });
    
    document.getElementById('edit-open-barcode-scanner-btn').addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Opening barcode scanner for edit form');
        currentScannerTarget = 'edit';
        document.getElementById('manual-barcode-input').value = '';
        document.getElementById('barcode-scanner-modal').style.display = 'flex';
        document.getElementById('scanner-status').innerHTML = '<p>Requesting camera access...</p>';
        document.getElementById('barcode-video').style.display = 'none';
        
        setTimeout(() => {
            initBarcodeScanner();
        }, 100);
    });
    
    document.getElementById('confirm-barcode-btn').addEventListener('click', function() {
        const barcode = document.getElementById('manual-barcode-input').value.trim();
        
        if (!barcode) {
            alert('Please scan or enter a barcode');
            return;
        }
        
        if (currentScannerTarget === 'product') {
            document.getElementById('product-barcode').value = barcode;
        } else if (currentScannerTarget === 'edit') {
            document.getElementById('edit-barcode').value = barcode;
        }
        
        stopBarcodeScanner();
        document.getElementById('barcode-scanner-modal').style.display = 'none';
    });
    
    document.querySelectorAll('.close-barcode-scanner').forEach(btn => {
        btn.addEventListener('click', function() {
            stopBarcodeScanner();
            document.getElementById('barcode-scanner-modal').style.display = 'none';
        });
    });
    
    // Close scanner modal when clicking outside
    document.getElementById('barcode-scanner-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            stopBarcodeScanner();
            this.style.display = 'none';
        }
    });
    
    // ========== MODAL CONTROLS ==========
    const addModal = document.getElementById('add-product-modal');
    const editModal = document.getElementById('edit-product-modal');
    
    document.getElementById('open-add-product-modal').addEventListener('click', () => {
        addModal.style.display = 'flex';
    });
    
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.in-modal').style.display = 'none';
        });
    });
    
    // ========== ADD PRODUCT FORM ==========
    document.getElementById('in-add-product-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'in_add_product');
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
                alert(json.data.message);
                location.reload();
            } else {
                alert(json.data.message);
                btn.disabled = false;
                btn.textContent = 'Add Product';
            }
        });
    });
    
    // ========== EDIT PRODUCT ==========
    document.querySelectorAll('.in-edit-product').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = this.dataset;
            
            document.getElementById('edit-product-id').value = data.id;
            document.getElementById('edit-name').value = data.name;
            document.getElementById('edit-sku').value = data.sku;
            document.getElementById('edit-barcode').value = data.barcode || '';
            document.getElementById('edit-inventory-type').value = data.type;
            document.getElementById('edit-cost-per-unit').value = data.cost;
            document.getElementById('edit-selling-price').value = data.price;
            document.getElementById('edit-current-stock').value = data.stock;
            document.getElementById('edit-reorder-level').value = data.reorder;
            document.getElementById('edit-description').value = data.description;
            
            // Reset adjustment fields
            document.getElementById('edit-stock-adjustment').value = '0';
            document.getElementById('edit-adjustment-type').value = 'add';
            
            // Load image
            const editImagePreview = document.getElementById('edit-product-image-preview');
            const editUploadArea = document.getElementById('edit-product-upload-area');
            const editImageInput = document.getElementById('edit-product_image');
            
            if (data.image) {
                editImagePreview.querySelector('img').src = data.image;
                editImagePreview.style.display = 'inline-block';
                editUploadArea.style.display = 'none';
                editImageInput.value = data.image;
            } else {
                editImagePreview.style.display = 'none';
                editUploadArea.style.display = 'block';
                editImageInput.value = '';
            }
            
            editModal.style.display = 'flex';
        });
    });
    
    document.getElementById('in-edit-product-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'in_update_product');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Updating...';
        
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
                btn.disabled = false;
                btn.textContent = 'Update Product';
            }
        });
    });
    
    // ========== DELETE PRODUCT ==========
    document.querySelectorAll('.in-delete-product').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete this product?')) return;
            
            const productId = this.getAttribute('data-id');
            const formData = new FormData();
            formData.append('action', 'in_delete_product');
            formData.append('product_id', productId);
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
})();
</script>

<!-- Remove these CDN scripts from the head section -->
<!-- <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@undecaf/zbar-wasm@0.10.1/index.js"></script> -->
    <?php
    return ob_get_clean();
}
function bntm_ajax_in_add_product() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();
    
    // Check table limit
    $limits = get_option('bntm_table_limits', []);
    if (isset($limits[$table]) && $limits[$table] > 0) {
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($current_count >= $limits[$table]) {
            wp_send_json_error(['message' => "Product limit reached. Maximum {$limits[$table]} products allowed."]);
        }
    }

    $name = sanitize_text_field($_POST['name']);
    $sku = sanitize_text_field($_POST['sku']);
    $barcode = sanitize_text_field($_POST['barcode']);
    $inventory_type = sanitize_text_field($_POST['inventory_type']);
    $cost_per_unit = floatval($_POST['cost_per_unit']);
    $selling_price = floatval($_POST['selling_price']);
    $initial_stock = intval($_POST['initial_stock']);
    $reorder_level = intval($_POST['reorder_level']);
    $description = sanitize_textarea_field($_POST['description']);
    $product_image = isset($_POST['product_image']) ? esc_url_raw($_POST['product_image']) : '';
    
    // Generate SKU if empty
    if (empty($sku)) {
        $sku = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
    }
      // Generate temporary barcode if blank
    if (empty($barcode)) {
        $barcode = 'TMP-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    // Check if barcode already exists
    if (!empty($barcode)) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE barcode = %s",
            $barcode
        ));
        
        if ($exists) {
            wp_send_json_error(['message' => 'Barcode already exists. Please use a unique barcode.']);
        }
    }

    $result = $wpdb->insert($table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'name' => $name,
        'sku' => $sku,
        'barcode' => $barcode,
        'inventory_type' => $inventory_type,
        'cost_per_unit' => $cost_per_unit,
        'selling_price' => $selling_price,
        'stock_quantity' => $initial_stock,
        'reorder_level' => $reorder_level,
        'description' => $description,
        'image' => $product_image
    ], ['%s', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s']);

    if ($result) {
        wp_send_json_success(['message' => 'Product added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add product.']);
    }
}

function bntm_ajax_in_delete_product() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $product_id = intval($_POST['product_id']);

    $result = $wpdb->delete($table, [
        'id' => $product_id
    ], ['%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Product deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete product.']);
    }
}

function bntm_ajax_in_update_product() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $product_id = intval($_POST['product_id']);
    $barcode = sanitize_text_field($_POST['barcode']);
    $stock_adjustment = intval($_POST['stock_adjustment']);
    $adjustment_type = sanitize_text_field($_POST['adjustment_type']);
    
    // Get current product data
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d ",
        $product_id, $business_id
    ));
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found.']);
    }
    
    if (empty($barcode)) {
        $barcode = 'TMP-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    // Check if new barcode already exists (and is different from current)
    if (!empty($barcode) && $barcode !== $product->barcode) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE barcode = %s",
            $barcode
        ));
        
        if ($exists) {
            wp_send_json_error(['message' => 'Barcode already exists. Please use a unique barcode.']);
        }
    }
    
    // Calculate new stock
    $current_stock = $product->stock_quantity;
    $new_stock = $current_stock;
    
    if ($stock_adjustment != 0) {
        if ($adjustment_type === 'add') {
            $new_stock = $current_stock + $stock_adjustment;
        } elseif ($adjustment_type === 'subtract') {
            $new_stock = max(0, $current_stock - $stock_adjustment);
        } elseif ($adjustment_type === 'set') {
            $new_stock = max(0, $stock_adjustment);
        }
    }
    
    $update_data = [
        'name' => sanitize_text_field($_POST['name']),
        'sku' => sanitize_text_field($_POST['sku']),
        'barcode' => $barcode,
        'inventory_type' => sanitize_text_field($_POST['inventory_type']),
        'cost_per_unit' => floatval($_POST['cost_per_unit']),
        'selling_price' => floatval($_POST['selling_price']),
        'stock_quantity' => $new_stock,
        'reorder_level' => intval($_POST['reorder_level']),
        'description' => sanitize_textarea_field($_POST['description']),
        'image' => isset($_POST['product_image']) ? esc_url_raw($_POST['product_image']) : ''
    ];

    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => $product_id],
        ['%s', '%s', '%s', '%s', '%f', '%f', '%d', '%d', '%s', '%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Product updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update product.']);
    }
}
function bntm_ajax_in_upload_product_image() {
    check_ajax_referer('in_upload_image', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
    }

    if (!isset($_FILES['image'])) {
        wp_send_json_error('No file uploaded');
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $file = $_FILES['image'];
    $upload = wp_handle_upload($file, ['test_form' => false]);

    if (isset($upload['error'])) {
        wp_send_json_error($upload['error']);
    }

    wp_send_json_success(['url' => $upload['url']]);
}
add_action('wp_ajax_in_upload_product_image', 'bntm_ajax_in_upload_product_image');
/* ========================================
   FUNCTION: in_batches_tab() - COMPLETE
   ======================================== */
function in_batches_tab($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $products_table ORDER BY name ASC",
        $business_id
    ));
    
    $batches = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, p.name as product_name, p.cost_per_unit as product_cost
        FROM $batches_table b
        LEFT JOIN $products_table p ON b.product_id = p.id
        ORDER BY b.created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('in_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Stock Movement</h3>
        <form id="in-add-batch-form" class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Product *</label>
                    <select name="product_id" id="batch-product-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product->id; ?>" 
                                    data-cost="<?php echo $product->cost_per_unit; ?>">
                                <?php echo esc_html($product->name); ?> (Stock: <?php echo $product->stock_quantity; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Transaction Type *</label>
                    <select name="type" id="batch-type-select" required>
                        <option value="stock_in">Stock In (Receive/Produce)</option>
                        <option value="stock_out">Stock Out (Sell/Use)</option>
                    </select>
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Reference Number</label>
                    <input type="text" name="reference_number" placeholder="e.g., PO-001, INV-001">
                    <small>Purchase Order, Invoice, or Batch Code</small>
                </div>
                <div class="bntm-form-group">
                    <label>Date</label>
                    <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" id="batch-quantity" min="1" required>
                </div>
                <div class="bntm-form-group">
                    <label>Cost Per Unit</label>
                    <input type="number" name="cost_per_unit" id="batch-cost-per-unit" step="0.01" min="0">
                    <small id="cost-hint">Product default cost will be used</small>
                </div>
            </div>

            <div style="background: #e0f2fe; padding: 15px; border-radius: 6px; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: #0c4a6e;">Total Cost: ₱<span id="total-cost-display">0.00</span></h3>
                        <p style="margin: 5px 0 0 0; color: #075985; font-size: 13px;">Quantity × Cost Per Unit</p>
                    </div>
                </div>
            </div>

            <div class="bntm-form-group" style="margin-top: 15px;">
                <label>Notes</label>
                <textarea name="notes" rows="3" placeholder="Additional information about this transaction"></textarea>
            </div>

            <button type="submit" class="bntm-btn-primary">Record Transaction</button>
        </form>
        <div id="batch-message"></div>
    </div>

    <div class="bntm-form-section">
        <h3>Stock Movement History (<?php echo count($batches); ?>)</h3>
        <?php if (empty($batches)): ?>
            <p>No stock movements recorded yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Cost/Unit</th>
                        <th>Total Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($batch->manufacture_date ?: $batch->created_at)); ?></td>
                            <td>
                                <?php if ($batch->type === 'stock_in'): ?>
                                    <span style="color: #059669; font-weight: 500;">▲ Stock In</span>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 500;">▼ Stock Out</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($batch->reference_number ?: $batch->batch_code); ?></td>
                            <td><?php echo esc_html($batch->product_name); ?></td>
                            <td><?php echo esc_html($batch->quantity); ?></td>
                            <td>₱<?php echo number_format($batch->cost_per_unit, 2); ?></td>
                            <td>₱<?php echo number_format($batch->total_cost, 2); ?></td>
                            <td>
                                <button class="bntm-btn-small view-batch-details" data-details='<?php echo esc_attr(json_encode([
                                    'type' => $batch->type,
                                    'reference' => $batch->reference_number ?: $batch->batch_code,
                                    'product' => $batch->product_name,
                                    'quantity' => $batch->quantity,
                                    'cost_per_unit' => $batch->cost_per_unit,
                                    'total_cost' => $batch->total_cost,
                                    'notes' => $batch->notes,
                                    'date' => $batch->manufacture_date ?: $batch->created_at,
                                    'created_at' => $batch->created_at
                                ])); ?>'>View</button>
                                <button class="bntm-btn-small bntm-btn-danger in-delete-batch" data-id="<?php echo $batch->id; ?>" data-type="<?php echo $batch->type; ?>" data-qty="<?php echo $batch->quantity; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Batch Details Modal -->
    <div id="batch-details-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Transaction Details</h3>
            <div id="batch-details-content"></div>
            <button class="bntm-btn-secondary close-batch-modal" style="margin-top: 20px;">Close</button>
        </div>
    </div>

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

    <script>
    (function() {
        // ========== LOAD PRODUCT COST ON SELECT ==========
        const productSelect = document.getElementById('batch-product-select');
        const costInput = document.getElementById('batch-cost-per-unit');
        const costHint = document.getElementById('cost-hint');
        
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const productCost = selectedOption.getAttribute('data-cost') || '0';
            
            costInput.value = productCost;
            costHint.textContent = 'Using product default cost: ₱' + parseFloat(productCost).toFixed(2);
            
            updateTotalCost();
        });
        
        // ========== UPDATE TOTAL COST ON INPUT ==========
        document.getElementById('batch-quantity').addEventListener('input', updateTotalCost);
        costInput.addEventListener('input', updateTotalCost);
        
        function updateTotalCost() {
            const quantity = parseInt(document.getElementById('batch-quantity').value) || 0;
            const costPerUnit = parseFloat(costInput.value) || 0;
            const totalCost = quantity * costPerUnit;
            
            document.getElementById('total-cost-display').textContent = totalCost.toFixed(2);
        }
        
        // ========== ADD BATCH FORM SUBMIT ==========
        document.getElementById('in-add-batch-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'in_add_batch');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                const message = document.getElementById('batch-message');
                if (json.success) {
                    message.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    message.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                const message = document.getElementById('batch-message');
                message.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
        
        // ========== VIEW BATCH DETAILS ==========
        document.querySelectorAll('.view-batch-details').forEach(btn => {
            btn.addEventListener('click', function() {
                const details = JSON.parse(this.getAttribute('data-details'));
                
                const typeLabel = details.type === 'stock_in' ? 
                    '<span style="color: #059669; font-weight: 500;">▲ Stock In</span>' : 
                    '<span style="color: #dc2626; font-weight: 500;">▼ Stock Out</span>';
                
                const content = `
                    <div style="background: #f9fafb; padding: 20px; border-radius: 6px;">
                        <p><strong>Type:</strong> ${typeLabel}</p>
                        <p><strong>Reference Number:</strong> ${details.reference}</p>
                        <p><strong>Product:</strong> ${details.product}</p>
                        <p><strong>Quantity:</strong> ${details.quantity} units</p>
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                        <p><strong>Cost Per Unit:</strong> ₱${parseFloat(details.cost_per_unit).toFixed(2)}</p>
                        <p style="font-size: 16px;"><strong>Total Cost:</strong> ₱${parseFloat(details.total_cost).toFixed(2)}</p>
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                        <p><strong>Transaction Date:</strong> ${new Date(details.date).toLocaleDateString()}</p>
                        <p><strong>Recorded:</strong> ${new Date(details.created_at).toLocaleDateString()}</p>
                        ${details.notes ? `<p style="margin-top: 15px;"><strong>Notes:</strong><br>${details.notes}</p>` : ''}
                    </div>
                `;
                
                document.getElementById('batch-details-content').innerHTML = content;
                document.getElementById('batch-details-modal').style.display = 'flex';
            });
        });
        
        // ========== CLOSE BATCH DETAILS MODAL ==========
        document.querySelector('.close-batch-modal').addEventListener('click', function() {
            document.getElementById('batch-details-modal').style.display = 'none';
        });
        
        document.getElementById('batch-details-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        
        // ========== DELETE BATCH ==========
        document.querySelectorAll('.in-delete-batch').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const qty = this.getAttribute('data-qty');
                const action = type === 'stock_in' ? 'reduce' : 'increase';
                
                if (!confirm(`Are you sure you want to delete this transaction?\n\nThis will ${action} the product stock by ${qty} units.`)) return;
                
                const batchId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'in_delete_batch');
                formData.append('batch_id', batchId);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                this.disabled = true;
                this.textContent = 'Deleting...';
                
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
                        this.disabled = false;
                        this.textContent = 'Delete';
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    this.disabled = false;
                    this.textContent = 'Delete';
                });
            });
        });
        
        // Initialize on page load
        updateTotalCost();
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ========================================
   SUPPORTING AJAX FUNCTION 1: Add Batch
   ======================================== */
function bntm_ajax_in_add_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $batches_table = $wpdb->prefix . 'in_batches';
    $products_table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $product_id = intval($_POST['product_id']);
    $type = sanitize_text_field($_POST['type']);
    $reference_number = sanitize_text_field($_POST['reference_number']);
    $quantity = intval($_POST['quantity']);
    $cost_per_unit = floatval($_POST['cost_per_unit']);
    $transaction_date = sanitize_text_field($_POST['transaction_date']);
    $notes = sanitize_textarea_field($_POST['notes']);
    
    // Validate product exists
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $products_table WHERE id = %d ",
        $product_id, $business_id
    ));
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found.']);
    }
    
    // Check stock for stock_out
    if ($type === 'stock_out' && $product->stock_quantity < $quantity) {
        wp_send_json_error(['message' => 'Insufficient stock. Available: ' . $product->stock_quantity]);
    }
    
    // Calculate total cost
    $total_cost = $quantity * $cost_per_unit;
    
    // Generate batch code if reference number is empty
    if (empty($reference_number)) {
        $reference_number = strtoupper($type) . '-' . date('Ymd') . '-' . substr(md5(uniqid()), 0, 6);
    }

    // Insert batch
    $result = $wpdb->insert($batches_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'product_id' => $product_id,
        'batch_code' => $reference_number,
        'type' => $type,
        'quantity' => $quantity,
        'cost_per_unit' => $cost_per_unit,
        'total_cost' => $total_cost,
        'reference_number' => $reference_number,
        'manufacture_date' => $transaction_date,
        'notes' => $notes
    ], ['%s', '%d', '%d', '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s']);

    if ($result) {
        // Update product stock
        if ($type === 'stock_in') {
            $wpdb->query($wpdb->prepare(
                "UPDATE $products_table SET stock_quantity = stock_quantity + %d WHERE id = %d",
                $quantity, $product_id
            ));
            $message = 'Stock added successfully! Inventory updated.';
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE $products_table SET stock_quantity = stock_quantity - %d WHERE id = %d",
                $quantity, $product_id
            ));
            $message = 'Stock removed successfully! Inventory updated.';
        }
        
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to record transaction. Please try again.']);
    }
}

function bntm_ajax_in_delete_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $batches_table = $wpdb->prefix . 'in_batches';
    $products_table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $batch_id = intval($_POST['batch_id']);
    
    // Get batch info
    $batch = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $batches_table WHERE id = %d",
        $batch_id, $business_id
    ));
    
    if (!$batch) {
        wp_send_json_error(['message' => 'Transaction not found.']);
    }

    // Delete batch
    $result = $wpdb->delete($batches_table, [
        'id' => $batch_id,
        'business_id' => $business_id
    ], ['%d', '%d']);

    if ($result) {
        // Reverse stock change
        if ($batch->type === 'stock_in') {
            // Was stock in, so reduce stock
            $wpdb->query($wpdb->prepare(
                "UPDATE $products_table SET stock_quantity = GREATEST(0, stock_quantity - %d) WHERE id = %d",
                $batch->quantity, $batch->product_id
            ));
        } else {
            // Was stock out, so add stock back
            $wpdb->query($wpdb->prepare(
                "UPDATE $products_table SET stock_quantity = stock_quantity + %d WHERE id = %d",
                $batch->quantity, $batch->product_id
            ));
        }
        
        wp_send_json_success(['message' => 'Transaction deleted successfully! Stock updated.']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete transaction. Please try again.']);
    }
}
function in_import_tab($business_id) {
    global $wpdb;
    $batches_table = $wpdb->prefix . 'in_batches';
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $products_table = $wpdb->prefix . 'in_products';
    
    // Only show stock_in transactions with cost > 0
    $batches = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as product_name,
        (SELECT COUNT(*) FROM {$txn_table} WHERE reference_type='inventory_batch' AND reference_id=b.id) as is_imported
        FROM {$batches_table} b
        LEFT JOIN {$products_table} p ON b.product_id = p.id
        WHERE b.type = 'stock_in' AND b.total_cost > 0
        ORDER BY b.created_at DESC
    ", $business_id));
    
    $nonce = wp_create_nonce('in_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Import Stock Purchases to Finance</h3>
        <p>Import stock-in transactions with costs as expense records in the Finance module.</p>
        
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
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Cost/Unit</th>
                    <th>Total Cost</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                <tr><td colspan="8" style="text-align:center;">No stock-in transactions with costs found</td></tr>
                <?php else: foreach ($batches as $batch): ?>
                <tr>
                    <td>
                        <input type="checkbox" 
                               class="batch-checkbox <?php echo $batch->is_imported ? 'imported-batch' : 'not-imported-batch'; ?>" 
                               data-id="<?php echo $batch->id; ?>"
                               data-amount="<?php echo $batch->total_cost; ?>"
                               data-ref="<?php echo esc_attr($batch->reference_number ?: $batch->batch_code); ?>"
                               data-product="<?php echo esc_attr($batch->product_name); ?>"
                               data-qty="<?php echo $batch->quantity; ?>"
                               data-cost="<?php echo $batch->cost_per_unit; ?>"
                               data-imported="<?php echo $batch->is_imported ? '1' : '0'; ?>">
                    </td>
                    <td><?php echo date('M d, Y', strtotime($batch->manufacture_date ?: $batch->created_at)); ?></td>
                    <td><?php echo esc_html($batch->reference_number ?: $batch->batch_code); ?></td>
                    <td><?php echo esc_html($batch->product_name); ?></td>
                    <td><?php echo esc_html($batch->quantity); ?> units</td>
                    <td>₱<?php echo number_format($batch->cost_per_unit, 2); ?></td>
                    <td class="bntm-stat-expense">₱<?php echo number_format($batch->total_cost, 2); ?></td>
                    <td>
                        <?php if ($batch->is_imported): ?>
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
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.batch-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        // Select all not imported
        document.getElementById('select-all-not-imported').addEventListener('change', function() {
            document.querySelectorAll('.not-imported-batch').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Select all imported
        document.getElementById('select-all-imported').addEventListener('change', function() {
            document.querySelectorAll('.imported-batch').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-not-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Update count on individual checkbox change
        document.querySelectorAll('.batch-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Bulk Import
        document.getElementById('bulk-import-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.batch-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '0');
            
            if (selected.length === 0) {
                alert('Please select at least one batch that is not imported');
                return;
            }
            
            // Calculate total amount
            const totalAmount = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
            
            if (!confirm(`Import ${selected.length} stock purchase(s) as expenses?\n\nTotal Amount: ₱${totalAmount.toFixed(2)}`)) return;
            
            this.disabled = true;
            this.textContent = 'Importing...';
            
            // Import one by one using existing AJAX
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'in_import_batch_expense');
                data.append('batch_id', cb.dataset.id);
                data.append('amount', cb.dataset.amount);
                data.append('reference', cb.dataset.ref);
                data.append('product', cb.dataset.product);
                data.append('quantity', cb.dataset.qty);
                data.append('cost_per_unit', cb.dataset.cost);
                data.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully imported ${total} stock purchase(s)`);
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
            const selected = Array.from(document.querySelectorAll('.batch-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '1');
            
            if (selected.length === 0) {
                alert('Please select at least one imported batch');
                return;
            }
            
            if (!confirm(`Remove ${selected.length} stock purchase(s) from Finance transactions?`)) return;
            
            this.disabled = true;
            this.textContent = 'Reverting...';
            
            // Revert one by one using existing AJAX
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'in_revert_batch_expense');
                data.append('batch_id', cb.dataset.id);
                data.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully reverted ${total} stock purchase(s)`);
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

function in_settings_tab($business_id) {
    $nonce = wp_create_nonce('in_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Inventory Settings</h3>
        <form id="in-settings-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Default Reorder Level</label>
                <input type="number" name="default_reorder_level" value="<?php echo esc_attr(bntm_get_setting('in_default_reorder_level', '10')); ?>">
                <small>Default minimum stock level for new products</small>
            </div>
            
            <div class="bntm-form-group">
                <label>Low Stock Alert Email</label>
                <input type="email" name="low_stock_email" value="<?php echo esc_attr(bntm_get_setting('in_low_stock_email', '')); ?>" placeholder="your@email.com">
                <small>Receive notifications when products reach low stock</small>
            </div>
            
            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="PHP" <?php selected(bntm_get_setting('in_currency', 'PHP'), 'PHP'); ?>>PHP - Philippine Peso</option>
                    <option value="USD" <?php selected(bntm_get_setting('in_currency', 'PHP'), 'USD'); ?>>USD - US Dollar</option>
                    <option value="EUR" <?php selected(bntm_get_setting('in_currency', 'PHP'), 'EUR'); ?>>EUR - Euro</option>
                </select>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Settings</button>
            <div id="settings-message"></div>
        </form>
    </div>

    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    document.getElementById('in-settings-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'in_save_settings');
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
            const message = document.getElementById('settings-message');
            message.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save Settings';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLERS ---------- */


function bntm_ajax_in_import_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }
    
    global $wpdb;
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $batch_id = intval($_POST['batch_id']);
    $amount = floatval($_POST['amount']);
    $reference = sanitize_text_field($_POST['reference']);
    $product = sanitize_text_field($_POST['product']);
    $quantity = sanitize_text_field($_POST['quantity']);
    $cost_per_unit = sanitize_text_field($_POST['cost_per_unit']);
    
    // Check if already imported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$txn_table} WHERE reference_type='inventory_batch' AND reference_id=%d",
        $batch_id
    ));
    
    if ($exists) {
        wp_send_json_error(['message' => 'This stock purchase has already been imported.']);
    }
    
    $notes = "Inventory Stock Purchase\nReference: {$reference}\nProduct: {$product}\nQuantity: {$quantity} units @ ₱{$cost_per_unit}";
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'type' => 'expense',
        'amount' => $amount,
        'category' => 'Inventory Purchase',
        'notes' => $notes,
        'reference_type' => 'inventory_batch',
        'reference_id' => $batch_id
    ];
    
    $result = $wpdb->insert($txn_table, $data);
    
    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Stock purchase imported to Finance successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to import. Please try again.']);
    }
}

function bntm_ajax_in_revert_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $batch_id = intval($_POST['batch_id']);
    
    $result = $wpdb->delete($table, [
        'reference_type' => 'inventory_batch',
        'reference_id' => $batch_id,
        'business_id' => get_current_user_id()
    ], ['%s', '%d', '%d']);
    
    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Stock purchase removed from Finance.']);
    } else {
        wp_send_json_error(['message' => 'Failed to revert. Please try again.']);
    }
}

function bntm_ajax_in_save_settings() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    bntm_update_setting('in_default_reorder_level', intval($_POST['default_reorder_level']));
    bntm_update_setting('in_low_stock_email', sanitize_email($_POST['low_stock_email']));
    bntm_update_setting('in_currency', sanitize_text_field($_POST['currency']));
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}
add_action('wp_ajax_in_save_settings', 'bntm_ajax_in_save_settings');

