<?php
/**
 * Module Name: Payments
 * Module Slug: op
 * Description: Centralized online payment processor with invoicing, PDF generation, and third-party payment APIs (PayPal, PayMaya)
 * Version: 1.0.2
 * Author: Your Name
 * Icon: 💳
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_OP_PATH', dirname(__FILE__) . '/');
define('BNTM_OP_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 */
function bntm_op_get_pages() {
    return [
        'Online Payments' => '[op_dashboard]',
        'Invoice' => '[op_invoice]',
        'Payment' => '[op_payment_page]',
        'Accounts Receivable' => '[op_receivables]',
        'Customer Statement' => '[op_customer_statement]',
        'Transactions' => '[op_payable_transactions]',
        'Payment Settings' => '[op_payment_settings]'
    ];
}

/**
 * Get module database tables
 */
function bntm_op_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'op_invoices' => "CREATE TABLE {$prefix}op_invoices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            reference_type VARCHAR(50) NOT NULL,
            reference_id BIGINT UNSIGNED NOT NULL,
            reference_number VARCHAR(100),
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            customer_phone VARCHAR(20),
            customer_address TEXT,
            description LONGTEXT,
            amount DECIMAL(12,2) NOT NULL,
            tax DECIMAL(12,2) DEFAULT 0,
            total DECIMAL(12,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            status VARCHAR(50) DEFAULT 'draft',
            payment_status VARCHAR(50) DEFAULT 'unpaid',
            payment_method VARCHAR(50),
            payment_reference VARCHAR(255),
            payment_account_name VARCHAR(255),
            payment_bank VARCHAR(255),
            pdf_url VARCHAR(500),
            pdf_generated_at DATETIME,
            due_date DATE,
            paid_at DATETIME,
            notes LONGTEXT,
            metadata JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_payment_status (payment_status),
            INDEX idx_reference (reference_type, reference_id),
            INDEX idx_rand_id (rand_id)
        ) {$charset};",
        
        'op_payments' => "CREATE TABLE {$prefix}op_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            invoice_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_gateway VARCHAR(50),
            transaction_id VARCHAR(255),
            external_reference VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            response_data JSON,
            error_message TEXT,
            attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            INDEX idx_invoice (invoice_id),
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_transaction (transaction_id),
            FOREIGN KEY (invoice_id) REFERENCES {$prefix}op_invoices(id) ON DELETE CASCADE
        ) {$charset};",
        
         'op_invoice_products' => "CREATE TABLE {$prefix}op_invoice_products (
             id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
             invoice_id BIGINT UNSIGNED NOT NULL,
             product_id BIGINT UNSIGNED NOT NULL,
             product_name VARCHAR(255) NOT NULL,
             quantity INT NOT NULL DEFAULT 1,
             unit_price DECIMAL(12,2) NOT NULL,
             total DECIMAL(12,2) NOT NULL,
             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
             INDEX idx_invoice (invoice_id),
             INDEX idx_product (product_id),
             FOREIGN KEY (invoice_id) REFERENCES {$prefix}op_invoices(id) ON DELETE CASCADE
         ) {$charset};",
         
         'op_imported_products' => "CREATE TABLE {$prefix}op_imported_products (
             id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
             product_id BIGINT UNSIGNED NOT NULL,
             product_name VARCHAR(255) NOT NULL,
             sku VARCHAR(100),
             price DECIMAL(12,2) NOT NULL,
             stock INT DEFAULT 0,
             imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
             INDEX idx_product (product_id)
         ) {$charset};",
        
        'op_payment_methods' => "CREATE TABLE {$prefix}op_payment_methods (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(50) NOT NULL,
            gateway VARCHAR(50),
            mode VARCHAR(20) DEFAULT 'sandbox',
            config JSON,
            is_active BOOLEAN DEFAULT 1,
            priority INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_gateway (gateway),
            INDEX idx_active (is_active)
        ) {$charset};",
        
        'op_email_settings' => "CREATE TABLE {$prefix}op_email_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            sender_name VARCHAR(255) DEFAULT 'Payment System',
            sender_email VARCHAR(255) NOT NULL,
            smtp_enabled BOOLEAN DEFAULT 0,
            smtp_host VARCHAR(255),
            smtp_port INT DEFAULT 587,
            smtp_username VARCHAR(255),
            smtp_password VARCHAR(255),
            enable_statement_email BOOLEAN DEFAULT 1,
            statement_email_template LONGTEXT,
            enable_auto_send BOOLEAN DEFAULT 0,
            auto_send_frequency VARCHAR(50) DEFAULT 'weekly',
            auto_send_day_of_week INT DEFAULT 1,
            auto_send_time TIME DEFAULT '08:00:00',
            last_auto_send DATETIME,
            next_auto_send DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 */
function bntm_op_get_shortcodes() {
    return [
        'op_dashboard' => 'bntm_shortcode_op_dashboard',
        'op_invoice' => 'bntm_shortcode_op_invoice',
        'op_payment_page' => 'bntm_shortcode_op_payment_page',
        'op_receivables' => 'bntm_op_shortcode_receivables',
        'op_customer_statement' => 'bntm_op_shortcode_customer_statement',
        'op_payable_transactions' => 'bntm_op_shortcode_payable_transactions',
        'op_payment_settings' => 'bntm_op_shortcode_settings'
    ];
}

/**
 * Create module tables
 */
function bntm_op_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_op_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_op_create_invoice', 'bntm_ajax_op_create_invoice');
add_action('wp_ajax_op_update_invoice', 'bntm_ajax_op_update_invoice');
add_action('wp_ajax_op_update_invoice_status', 'bntm_ajax_op_update_invoice_status');
add_action('wp_ajax_op_process_payment', 'bntm_ajax_op_process_payment');
add_action('wp_ajax_nopriv_op_process_payment', 'bntm_ajax_op_process_payment');
add_action('wp_ajax_op_confirm_manual_payment', 'bntm_ajax_op_confirm_manual_payment');
add_action('wp_ajax_nopriv_op_confirm_manual_payment', 'bntm_ajax_op_confirm_manual_payment');
add_action('wp_ajax_op_webhook_paypal', 'bntm_ajax_op_webhook_paypal');
add_action('wp_ajax_nopriv_op_webhook_paypal', 'bntm_ajax_op_webhook_paypal');
add_action('wp_ajax_op_webhook_paymaya', 'bntm_ajax_op_webhook_paymaya');
add_action('wp_ajax_nopriv_op_webhook_paymaya', 'bntm_ajax_op_webhook_paymaya');
add_action('wp_ajax_op_setup_payment_methods', 'bntm_ajax_op_setup_payment_methods');
add_action('wp_ajax_op_delete_payment_method', 'bntm_ajax_op_delete_payment_method');
add_action('wp_ajax_op_get_payment_method', 'bntm_ajax_op_get_payment_method');
add_action('wp_ajax_op_import_invoice', 'bntm_ajax_op_import_invoice');
add_action('wp_ajax_op_revert_invoice', 'bntm_ajax_op_revert_invoice');
add_action('wp_ajax_op_save_settings', 'bntm_ajax_op_save_settings');

/* ---------- DASHBOARD ---------- */
function bntm_shortcode_op_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Payments dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    
    <style>
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-badge.status-draft {
        background: #e5e7eb;
        color: #6b7280;
    }
    .status-badge.status-sent {
        background: #bfdbfe;
        color: #1e40af;
    }
    .status-badge.status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge.status-paid {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.status-unpaid {
        background: #fee2e2;
        color: #991b1b;
    }
    .status-badge.status-overdue, .status-badge.status-cancelled {
        background: #fecaca;
        color: #7f1d1d;
    }
    .status-badge.status-pending_verification, .status-badge.status-verifying {
        background: #dbeafe;
        color: #1e40af;
    }
    .status-badge.status-refunded {
        background: #fed7aa;
        color: #9a3412;
    }
    
    .bntm-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        overflow-y: auto;
    }
    .bntm-modal-content {
        background-color: #fff;
        margin: 50px auto;
        padding: 0;
        border-radius: 8px;
        max-width: 600px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .bntm-modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .bntm-modal-header h2 {
        margin: 0;
        font-size: 20px;
    }
    .bntm-modal-close {
        color: #6b7280;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    .bntm-modal-close:hover {
        color: #1f2937;
    }
    .bntm-modal .bntm-form {
        padding: 20px;
    }
    .bntm-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }
    .modal-cancel {
        background: #6b7280;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
    }
    .modal-cancel:hover {
        background: #4b5563;
    }
    </style>
    <div class="bntm-ecommerce-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=invoices" class="bntm-tab <?php echo $active_tab === 'invoices' ? 'active' : ''; ?>">Invoices</a>
            <a href="?tab=import-products" class="bntm-tab <?php echo $active_tab === 'import-products' ? 'active' : ''; ?>">Import Products</a>
            <a href="?tab=payments" class="bntm-tab <?php echo $active_tab === 'payments' ? 'active' : ''; ?>">Payments</a>
            <a href="?tab=receivables" class="bntm-tab <?php echo $active_tab === 'receivables' ? 'active' : ''; ?>">Customer Payables</a>
            <a href="?tab=payable-transactions" class="bntm-tab <?php echo $active_tab === 'payable-transactions' ? 'active' : ''; ?>">Payable Transactions</a>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import" class="bntm-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">Import Finance</a>
            <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo op_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'invoices'): ?>
                <?php echo op_invoices_tab($business_id); ?>
            <?php elseif ($active_tab === 'import-products'): ?>
                <?php echo op_import_products_tab($business_id); ?>
            <?php elseif ($active_tab === 'payments'): ?>
                <?php echo op_payments_tab($business_id); ?>
            <?php elseif ($active_tab === 'receivables'): ?>
                <?php echo op_customer_payables_tab($business_id); ?>
            <?php elseif ($active_tab === 'payable-transactions'): ?>
                <?php echo op_payable_transactions_tab($business_id); ?>
            <?php elseif ($active_tab === 'import'): ?>
                <?php echo op_import_finance_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo op_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Payments', $content);
}
function op_overview_tab($business_id) {
    $stats = op_get_dashboard_stats($business_id);
    
    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="op-dashboard-stats">
        <div class="op-stat-card">
            <div class="op-stat-icon op-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
            </div>
            <div class="op-stat-content">
                <h3>Total Invoices</h3>
                <p class="op-stat-number"><?php echo esc_html($stats['total_invoices']); ?></p>
            </div>
        </div>
        
        <div class="op-stat-card">
            <div class="op-stat-icon op-stat-icon-warning">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="op-stat-content">
                <h3>Unpaid Invoices</h3>
                <p class="op-stat-number"><?php echo esc_html($stats['unpaid_invoices']); ?></p>
            </div>
        </div>
        
        <div class="op-stat-card">
            <div class="op-stat-icon op-stat-icon-success">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="op-stat-content">
                <h3>Total Revenue</h3>
                <p class="op-stat-number"><?php echo op_format_price($stats['total_revenue']); ?></p>
            </div>
        </div>
        
        <div class="op-stat-card">
            <div class="op-stat-icon op-stat-icon-primary">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div class="op-stat-content">
                <h3>Monthly Revenue</h3>
                <p class="op-stat-number"><?php echo op_format_price($stats['monthly_revenue']); ?></p>
            </div>
        </div>
        
        <div class="op-stat-card">
            <div class="op-stat-icon op-stat-icon-info">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div class="op-stat-content">
                <h3>Pending Payments</h3>
                <p class="op-stat-number"><?php echo esc_html($stats['pending_payments']); ?></p>
            </div>
        </div>
    </div>

    <div class="op-charts-grid">
        <div class="op-chart-card op-chart-large">
            <h3>Revenue Overview</h3>
            <canvas id="revenueChart"></canvas>
        </div>
        
    </div>

    <div class="op-recent-invoices-section">
        <h3>Recent Invoices</h3>
        <?php echo op_render_recent_invoices($business_id, 5); ?>
    </div>

    <style>
    .op-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .op-stat-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .op-stat-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .op-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .op-stat-icon-primary {
        background: var(--bntm-primary, #374151);
        color: #ffffff;
    }
    
    .op-stat-icon-warning {
        background: #f59e0b;
        color: #ffffff;
    }
    
    .op-stat-icon-success {
        background: #10b981;
        color: #ffffff;
    }
    
    .op-stat-icon-info {
        background: #3b82f6;
        color: #ffffff;
    }
    
    .op-stat-content {
        flex: 1;
    }
    
    .op-stat-content h3 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .op-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        line-height: 1;
    }
    
    .op-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .op-chart-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .op-chart-large {
        grid-column: 1 / -1;
    }
    
    .op-chart-card h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .op-chart-card canvas {
        max-height: 300px;
    }
    
    .op-recent-invoices-section {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .op-recent-invoices-section h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    @media (max-width: 768px) {
        .op-chart-card {
            grid-column: 1 / -1;
        }
    }
    </style>
    
    <script>
    (function() {
        // Chart.js configuration
        const primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--bntm-primary').trim() || '#374151';
        
        // Revenue Overview Chart (Line Chart)
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['monthly_revenue_data'], 'month')); ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?php echo json_encode(array_column($stats['monthly_revenue_data'], 'total')); ?>,
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
                                    return 'Revenue: <?php echo get_option('op_currency_symbol', '$'); ?>' + context.parsed.y.toFixed(2);
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

function op_get_dashboard_stats($business_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $payments_table = $wpdb->prefix . 'op_payments';
    
    // Total invoices
    $total_invoices = $wpdb->get_var("SELECT COUNT(*) FROM $invoices_table");
    
    // Unpaid invoices
    $unpaid_invoices = $wpdb->get_var(
        "SELECT COUNT(*) FROM $invoices_table WHERE payment_status = 'unpaid'"
    );
    
    // Total revenue
    $total_revenue = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM $invoices_table WHERE payment_status = 'paid'"
    );
    
    // Monthly revenue
    $monthly_revenue = $wpdb->get_var(
        "SELECT COALESCE(SUM(total), 0) FROM $invoices_table 
         WHERE payment_status = 'paid'
         AND MONTH(paid_at) = MONTH(CURRENT_DATE()) 
         AND YEAR(paid_at) = YEAR(CURRENT_DATE())"
    );
    
    // Pending payments
    $pending_payments = $wpdb->get_var(
        "SELECT COUNT(*) FROM $payments_table WHERE status = 'pending'"
    );
    
    // Monthly revenue data (last 6 months)
    $monthly_revenue_data = $wpdb->get_results(
        "SELECT DATE_FORMAT(paid_at, '%b %Y') as month, COALESCE(SUM(total), 0) as total
        FROM $invoices_table
        WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        AND payment_status = 'paid'
        GROUP BY YEAR(paid_at), MONTH(paid_at)
        ORDER BY YEAR(paid_at), MONTH(paid_at)",
        ARRAY_A
    );
    
    // If no data, create empty months
    if (empty($monthly_revenue_data)) {
        $monthly_revenue_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly_revenue_data[] = [
                'month' => date('M Y', strtotime("-$i months")),
                'total' => 0
            ];
        }
    }
    
    
    return [
        'total_invoices' => intval($total_invoices),
        'unpaid_invoices' => intval($unpaid_invoices),
        'total_revenue' => floatval($total_revenue),
        'monthly_revenue' => floatval($monthly_revenue),
        'pending_payments' => intval($pending_payments),
        'monthly_revenue_data' => $monthly_revenue_data
    ];
}

function op_invoices_tab($business_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $invoices_table ORDER BY created_at DESC",
        $business_id
    ));
    
    // Get table limit
    $limits = get_option('bntm_table_limits', []);
    $invoice_limit = isset($limits[$invoices_table]) ? $limits[$invoices_table] : 0;
    $current_invoices = count($invoices);
    $limit_text = $invoice_limit > 0 ? " ({$current_invoices}/{$invoice_limit})" : " ({$current_invoices})";
    $limit_reached = $invoice_limit > 0 && $current_invoices >= $invoice_limit;
    
    $nonce = wp_create_nonce('op_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Invoices<?php echo $limit_text; ?></h3>
            <button class="bntm-btn-primary" id="create-invoice-btn" <?php echo $limit_reached ? 'disabled' : ''; ?>>
                + Create Invoice
            </button>
        </div>
        
        <?php if ($limit_reached): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <strong>⚠️ Invoice Limit Reached:</strong> Maximum of <?php echo $invoice_limit; ?> invoices allowed.
            </div>
        <?php endif; ?>
        
        <?php if (empty($invoices)): ?>
            <p>No invoices yet.</p>
        <?php else: ?>
        
           <div class="bntm-table-wrapper">
               <table class="bntm-table">
                   <thead>
                       <tr>
                           <th>Invoice ID</th>
                           <th>Customer</th>
                           <th>Amount</th>
                           <th>Status</th>
                           <th>Payment</th>
                           <th>Date</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($invoices as $invoice): ?>
                           <tr>
                               <td>#<?php echo esc_html($invoice->rand_id); ?></td>
                               <td><?php echo esc_html($invoice->customer_name); ?></td>
                               <td><?php echo op_format_price($invoice->total); ?></td>
                               <td>
                                   <span class="status-badge status-<?php echo esc_attr($invoice->status); ?>">
                                       <?php echo ucfirst($invoice->status); ?>
                                   </span>
                               </td>
                               <td>
                                   <span class="status-badge status-<?php echo esc_attr($invoice->payment_status); ?>">
                                       <?php echo ucfirst(str_replace('_', ' ', $invoice->payment_status)); ?>
                                   </span>
                               </td>
                               <td><?php echo date('M d, Y', strtotime($invoice->created_at)); ?></td>
                               <td>
                                   <a href="<?php echo get_page_link(get_page_by_path('invoice')) . '?id=' . esc_attr($invoice->rand_id); ?>" 
                                      class="bntm-btn-small" target="_blank">View</a>
                                   <button class="bntm-btn-small update-status-btn" 
                                           data-id="<?php echo esc_attr($invoice->rand_id); ?>"
                                           data-status="<?php echo esc_attr($invoice->status); ?>"
                                           data-payment="<?php echo esc_attr($invoice->payment_status); ?>">
                                       Update
                                   </button>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Invoice Modal -->
    <div id="invoice-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2 id="modal-title">Create Invoice</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="invoice-form" class="bntm-form">
                <input type="hidden" name="invoice_id" id="invoice_id">
                
                <div class="bntm-form-group">
                    <label>Customer Name *</label>
                    <input type="text" name="customer_name" id="customer_name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Customer Email *</label>
                    <input type="email" name="customer_email" id="customer_email" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Customer Phone</label>
                    <input type="text" name="customer_phone" id="customer_phone">
                </div>
                
                <div class="bntm-form-group">
                    <label>Customer Address</label>
                    <textarea name="customer_address" id="customer_address" rows="2"></textarea>
                </div>
                
               <div class="bntm-form-group">
                   <label>Invoice Type</label>
                   <select name="invoice_type" id="invoice_type">
                       <option value="manual">Manual Entry</option>
                       <option value="products">From Products</option>
                   </select>
               </div>
               
               <div id="manual-description" class="bntm-form-group">
                   <label>Description *</label>
                   <textarea name="description" id="description" rows="3" placeholder="Invoice description or itemized list"></textarea>
               </div>
               
               <div id="products-section" style="display: none;">
                   <div class="bntm-form-group">
                       <label>Select Products</label>
                       <button type="button" id="add-product-btn" class="bntm-btn-secondary">+ Add Product</button>
                       <div id="selected-products-list" style="margin-top: 10px;"></div>
                   </div>
               </div>
               
               <div class="bntm-form-group" id="amount-field">
                   <label>Amount *</label>
                   <input type="number" name="amount" id="amount" step="0.01" min="0" required>
               </div>
                
                
                <div class="bntm-form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" id="due_date">
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Additional notes or payment instructions"></textarea>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Create Invoice</button>
                </div>
                <div id="invoice-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="status-modal" class="bntm-modal">
        <div class="bntm-modal-content" style="max-width: 500px;">
            <div class="bntm-modal-header">
                <h2>Update Status</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="status-form" class="bntm-form">
                <input type="hidden" name="invoice_id" id="status_invoice_id">
                
                <div class="bntm-form-group">
                    <label>Invoice Status</label>
                    <select name="invoice_status" id="invoice_status">
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Payment Status</label>
                    <select name="payment_status" id="payment_status">
                        <option value="unpaid">Unpaid</option>
                        <option value="pending_verification">Pending Verification</option>
                        <option value="verifying">Verifying Payment</option>
                        <option value="paid">Paid</option>
                        <option value="refunded">Refunded</option>
                    </select>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Update Status</button>
                </div>
                <div id="status-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>
   <div id="product-select-modal" class="bntm-modal">
          <div class="bntm-modal-content">
              <div class="bntm-modal-header">
                  <h2>Select Product</h2>
                  <span class="bntm-modal-close">&times;</span>
              </div>
              <div class="bntm-form" style="padding: 20px;">
                  <div id="product-list-container">
                      <?php
                      global $wpdb;
                      $imported_products = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}op_imported_products ORDER BY product_name ASC");
                      if (empty($imported_products)):
                      ?>
                          <p>No products imported yet. Go to Import Products tab to import products.</p>
                      <?php else: ?>
                          <div class="product-selection-list">
                              <?php foreach ($imported_products as $product): ?>
                                  <div class="product-select-item" data-id="<?php echo $product->id; ?>" 
                                       data-name="<?php echo esc_attr($product->product_name); ?>"
                                       data-price="<?php echo $product->price; ?>">
                                      <strong><?php echo esc_html($product->product_name); ?></strong>
                                      <?php if ($product->sku): ?>
                                          <span class="product-sku">SKU: <?php echo esc_html($product->sku); ?></span>
                                      <?php endif; ?>
                                      <div class="product-details">
                                          <span class="product-price">₱<?php echo number_format($product->price, 2); ?></span>
                                          <span class="product-stock"><?php echo $product->stock; ?> in stock</span>
                                      </div>
                                  </div>
                              <?php endforeach; ?>
                          </div>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
      </div>

      <style>
      .product-selection-list {
          max-height: 400px;
          overflow-y: auto;
      }
      .product-select-item {
          padding: 12px;
          border: 1px solid #e5e7eb;
          border-radius: 6px;
          margin-bottom: 8px;
          cursor: pointer;
          transition: all 0.2s;
      }
      .product-select-item:hover {
          background: #eff6ff;
          border-color: #3b82f6;
      }
      .selected-product-row {
          display: flex;
          justify-content: space-between;
          align-items: center;
          padding: 10px;
          background: #f9fafb;
          border: 1px solid #e5e7eb;
          border-radius: 4px;
          margin-bottom: 8px;
      }
      .selected-product-info {
          flex: 1;
      }
      .selected-product-quantity {
          display: flex;
          align-items: center;
          gap: 8px;
          margin: 0 15px;
      }
      .selected-product-quantity input {
          width: 60px;
          text-align: center;
      }
      </style>
  <script>
(function() {
    const nonce = '<?php echo $nonce; ?>';
    const modal = document.getElementById('invoice-modal');
    const statusModal = document.getElementById('status-modal');
    const form = document.getElementById('invoice-form');
    const statusForm = document.getElementById('status-form');
    const limitReached = <?php echo $limit_reached ? 'true' : 'false'; ?>;
    const invoiceLimit = <?php echo $invoice_limit; ?>;
    
    // Product selection variables
    const invoiceTypeSelect = document.getElementById('invoice_type');
    const manualDescription = document.getElementById('manual-description');
    const productsSection = document.getElementById('products-section');
    const amountField = document.getElementById('amount-field');
    const productSelectModal = document.getElementById('product-select-modal');
    let selectedProducts = [];
    
    // Invoice type toggle
    if (invoiceTypeSelect) {
        invoiceTypeSelect.addEventListener('change', function() {
            if (this.value === 'products') {
                manualDescription.style.display = 'none';
                productsSection.style.display = 'block';
                amountField.style.display = 'none';
                document.getElementById('description').removeAttribute('required');
                document.getElementById('amount').removeAttribute('required');
                // Clear manual fields
                document.getElementById('description').value = '';
                document.getElementById('amount').value = '';
            } else {
                manualDescription.style.display = 'block';
                productsSection.style.display = 'none';
                amountField.style.display = 'block';
                document.getElementById('description').setAttribute('required', 'required');
                document.getElementById('amount').setAttribute('required', 'required');
                // Clear products
                selectedProducts = [];
                renderSelectedProducts();
            }
        });
    }
    
    // Add product button
    const addProductBtn = document.getElementById('add-product-btn');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', function() {
            productSelectModal.style.display = 'block';
        });
    }
    
    // Product selection from modal
    document.querySelectorAll('.product-select-item').forEach(item => {
        item.addEventListener('click', function() {
            const productData = {
                id: this.dataset.id,
                name: this.dataset.name,
                price: parseFloat(this.dataset.price),
                quantity: 1
            };
            
            addSelectedProduct(productData);
            productSelectModal.style.display = 'none';
        });
    });
    
    // Add product to selection
    function addSelectedProduct(product) {
        const existingIndex = selectedProducts.findIndex(p => p.id === product.id);
        if (existingIndex !== -1) {
            selectedProducts[existingIndex].quantity++;
        } else {
            selectedProducts.push(product);
        }
        renderSelectedProducts();
        updateTotalAmount();
    }
    
    // Render selected products list
    function renderSelectedProducts() {
        const container = document.getElementById('selected-products-list');
        if (!container) return;
        
        if (selectedProducts.length === 0) {
            container.innerHTML = '<p style="color: #6b7280; font-size: 14px; margin: 10px 0;">No products added yet. Click "Add Product" to select products.</p>';
            return;
        }
        
        let html = '';
        let total = 0;
        
        selectedProducts.forEach((product, index) => {
            const lineTotal = product.price * product.quantity;
            total += lineTotal;
            
            html += `
                <div class="selected-product-row">
                    <div class="selected-product-info">
                        <strong>${escapeHtml(product.name)}</strong>
                        <div style="font-size: 12px; color: #6b7280;">₱${product.price.toFixed(2)} each</div>
                    </div>
                    <div class="selected-product-quantity">
                        <button type="button" class="bntm-btn-small qty-btn" onclick="updateQuantity(${index}, -1)">-</button>
                        <input type="number" value="${product.quantity}" min="1" 
                               onchange="updateQuantityInput(${index}, this.value)" 
                               class="qty-input">
                        <button type="button" class="bntm-btn-small qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
                    </div>
                    <div style="min-width: 100px; text-align: right; font-weight: 600;">
                        ₱${lineTotal.toFixed(2)}
                    </div>
                    <button type="button" class="bntm-btn-small bntm-btn-danger" onclick="removeProduct(${index})" title="Remove product">×</button>
                </div>
            `;
        });
        
        // Add total summary
        html += `
            <div style="text-align: right; padding: 15px 10px; background: #eff6ff; border-radius: 4px; margin-top: 10px; border: 1px solid #bfdbfe;">
                <div style="font-size: 14px; color: #6b7280; margin-bottom: 5px;">Subtotal</div>
                <div style="font-size: 20px; font-weight: 700; color: #1f2937;">₱${total.toFixed(2)}</div>
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    // Update total amount in hidden field
    function updateTotalAmount() {
        let total = 0;
        selectedProducts.forEach(product => {
            total += product.price * product.quantity;
        });
        
        // Set the amount field value (even though it's hidden)
        const amountInput = document.getElementById('amount');
        if (amountInput) {
            amountInput.value = total.toFixed(2);
        }
        
        return total;
    }
    
    // Update quantity (increment/decrement)
    window.updateQuantity = function(index, change) {
        if (selectedProducts[index]) {
            selectedProducts[index].quantity = Math.max(1, selectedProducts[index].quantity + change);
            renderSelectedProducts();
            updateTotalAmount();
        }
    };
    
    // Update quantity from input
    window.updateQuantityInput = function(index, value) {
        if (selectedProducts[index]) {
            const newQty = parseInt(value) || 1;
            selectedProducts[index].quantity = Math.max(1, newQty);
            renderSelectedProducts();
            updateTotalAmount();
        }
    };
    
    // Remove product
    window.removeProduct = function(index) {
        if (confirm('Remove this product from the invoice?')) {
            selectedProducts.splice(index, 1);
            renderSelectedProducts();
            updateTotalAmount();
        }
    };
    
    // HTML escape helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Open create modal
    document.getElementById('create-invoice-btn').addEventListener('click', function() {
        if (limitReached) {
            alert('Invoice limit has been reached (' + invoiceLimit + ' maximum). Please contact your administrator to increase the limit.');
            return;
        }
        
        document.getElementById('modal-title').textContent = 'Create Invoice';
        form.reset();
        document.getElementById('invoice_id').value = '';
        selectedProducts = [];
        renderSelectedProducts();
        
        // Reset to manual mode by default
        if (invoiceTypeSelect) {
            invoiceTypeSelect.value = 'manual';
            manualDescription.style.display = 'block';
            productsSection.style.display = 'none';
            amountField.style.display = 'block';
            document.getElementById('description').setAttribute('required', 'required');
            document.getElementById('amount').setAttribute('required', 'required');
        }
        
        modal.style.display = 'block';
    });
    
    // Close modals
    document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
        el.addEventListener('click', function() {
            modal.style.display = 'none';
            statusModal.style.display = 'none';
            if (productSelectModal) {
                productSelectModal.style.display = 'none';
            }
            // Clear messages
            document.getElementById('invoice-form-message').innerHTML = '';
            document.getElementById('status-form-message').innerHTML = '';
        });
    });
    
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.getElementById('invoice-form-message').innerHTML = '';
        }
        if (e.target === statusModal) {
            statusModal.style.display = 'none';
            document.getElementById('status-form-message').innerHTML = '';
        }
        if (productSelectModal && e.target === productSelectModal) {
            productSelectModal.style.display = 'none';
        }
    });
    
    // Submit invoice form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const invoiceType = document.getElementById('invoice_type').value;
        
        // Handle product-based invoice
        if (invoiceType === 'products') {
            if (selectedProducts.length === 0) {
                const msg = document.getElementById('invoice-form-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Please add at least one product to the invoice.</div>';
                return;
            }
            
            // Add products data to form
            formData.append('products', JSON.stringify(selectedProducts));
            
            // Calculate and set amount
            const total = updateTotalAmount();
            formData.set('amount', total.toFixed(2));
            
            // Set description as empty or product summary
            let descriptionSummary = selectedProducts.map(p => 
                `${p.name} x${p.quantity}`
            ).join(', ');
            formData.set('description', descriptionSummary);
        }
        
        const action = formData.get('invoice_id') ? 'op_update_invoice' : 'op_create_invoice';
        formData.append('action', action);
        formData.append('nonce', nonce);
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('invoice-form-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(err => {
            const msg = document.getElementById('invoice-form-message');
            msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
    
    // Update status buttons
    document.querySelectorAll('.update-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const invoiceId = this.dataset.id;
            const status = this.dataset.status;
            const paymentStatus = this.dataset.payment;
            
            document.getElementById('status_invoice_id').value = invoiceId;
            document.getElementById('invoice_status').value = status;
            document.getElementById('payment_status').value = paymentStatus;
            
            statusModal.style.display = 'block';
        });
    });
    
    // Submit status form
    statusForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'op_update_invoice_status');
        formData.append('nonce', nonce);
        
        const btn = this.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Updating...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('status-form-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(err => {
            const msg = document.getElementById('status-form-message');
            msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
})();
</script>

    <?php
    return ob_get_clean();
}
// Add these action hooks
add_action('wp_ajax_op_import_selected_products', 'bntm_ajax_op_import_selected_products');
add_action('wp_ajax_op_sync_product', 'bntm_ajax_op_sync_product');
add_action('wp_ajax_op_remove_imported_product', 'bntm_ajax_op_remove_imported_product');

function bntm_ajax_op_import_selected_products() {
    check_ajax_referer('op_import_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $in_table = $wpdb->prefix . 'in_products';
    $op_products_table = $wpdb->prefix . 'op_imported_products';
    
    $product_ids = json_decode(stripslashes($_POST['product_ids']), true);
    
    if (empty($product_ids)) {
        wp_send_json_error(['message' => 'No products selected']);
    }
    
    $imported = 0;
    $skipped = 0;
    
    foreach ($product_ids as $product_id) {
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $in_table WHERE id = %d",
            $product_id
        ));
        
        if (!$product) continue;
        
        // Check if already imported
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $op_products_table WHERE product_id = %d",
            $product_id
        ));
        
        if (!$exists) {
            $result = $wpdb->insert($op_products_table, [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku ?: '',
                'price' => $product->selling_price,
                'stock' => $product->stock_quantity,
                'imported_at' => current_time('mysql')
            ]);
            
            if ($result) {
                $imported++;
            }
        } else {
            $skipped++;
        }
    }
    
    $message = "Successfully imported $imported product(s)!";
    if ($skipped > 0) {
        $message .= " ($skipped already imported)";
    }
    
    wp_send_json_success(['message' => $message]);
}

function bntm_ajax_op_sync_product() {
    check_ajax_referer('op_import_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $in_table = $wpdb->prefix . 'in_products';
    $op_products_table = $wpdb->prefix . 'op_imported_products';
    
    $product_id = intval($_POST['product_id'] ?? 0);
    
    // Get imported product
    $imported_product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $op_products_table WHERE id = %d",
        $product_id
    ));
    
    if (!$imported_product) {
        wp_send_json_error(['message' => 'Product not found']);
    }
    
    // Get current inventory data
    $inventory_product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $in_table WHERE id = %d",
        $imported_product->product_id
    ));
    
    if (!$inventory_product) {
        wp_send_json_error(['message' => 'Inventory product not found']);
    }
    
    // Update imported product with latest inventory data
    $result = $wpdb->update(
        $op_products_table,
        [
            'product_name' => $inventory_product->name,
            'sku' => $inventory_product->sku ?: '',
            'price' => $inventory_product->selling_price,
            'stock' => $inventory_product->stock_quantity
        ],
        ['id' => $product_id],
        ['%s', '%s', '%f', '%d'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Product synced successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to sync product']);
    }
}

function bntm_ajax_op_remove_imported_product() {
    check_ajax_referer('op_import_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $op_products_table = $wpdb->prefix . 'op_imported_products';
    
    $product_id = intval($_POST['product_id'] ?? 0);
    
    $result = $wpdb->delete(
        $op_products_table,
        ['id' => $product_id],
        ['%d']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Product removed successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to remove product']);
    }
}
function op_payments_tab($business_id) {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'op_payments';
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, i.rand_id as invoice_id, i.total, i.customer_name 
         FROM $payments_table p
         LEFT JOIN $invoices_table i ON p.invoice_id = i.id
         ORDER BY p.attempted_at DESC",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Payment History (<?php echo count($payments); ?>)</h3>
        
        <?php if (empty($payments)): ?>
            <p>No payment history yet.</p>
        <?php else: ?>
        
           <div class="bntm-table-wrapper">
               <table class="bntm-table">
                   <thead>
                       <tr>
                           <th>Invoice</th>
                           <th>Customer</th>
                           <th>Amount</th>
                           <th>Method</th>
                           <th>Gateway</th>
                           <th>Status</th>
                           <th>Date</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($payments as $payment): ?>
                           <tr>
                               <td>#<?php echo esc_html($payment->invoice_id); ?></td>
                               <td><?php echo esc_html($payment->customer_name); ?></td>
                               <td><?php echo op_format_price($payment->amount); ?></td>
                               <td><?php echo esc_html(ucfirst($payment->payment_method)); ?></td>
                               <td><?php echo esc_html(ucfirst($payment->payment_gateway ?? 'N/A')); ?></td>
                               <td>
                                   <span class="status-badge status-<?php echo esc_attr($payment->status); ?>">
                                       <?php echo ucfirst($payment->status); ?>
                                   </span>
                               </td>
                               <td><?php echo date('M d, Y H:i', strtotime($payment->attempted_at)); ?></td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function op_import_finance_tab($business_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $txn_table = $wpdb->prefix . 'fn_transactions';
    
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, 
        (SELECT COUNT(*) FROM {$txn_table} WHERE reference_type='invoice' AND reference_id=i.id) as is_imported
        FROM {$invoices_table} i
        WHERE i.payment_status = 'paid'
        ORDER BY i.paid_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('bntm_fn_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Paid Invoices for Import</h3>
        <p>Import paid invoices as income transactions to Finance module</p>
        
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
                       <th>Invoice ID</th>
                       <th>Customer</th>
                       <th>Date</th>
                       <th>Total</th>
                       <th>Import Status</th>
                   </tr>
               </thead>
               <tbody>
                   <?php if (empty($invoices)): ?>
                   <tr><td colspan="6" style="text-align:center;">No paid invoices found</td></tr>
                   <?php else: foreach ($invoices as $invoice): ?>
                   <tr>
                       <td>
                           <input type="checkbox" 
                                  class="invoice-checkbox <?php echo $invoice->is_imported ? 'imported-invoice' : 'not-imported-invoice'; ?>" 
                                  data-id="<?php echo $invoice->id; ?>"
                                  data-amount="<?php echo $invoice->total; ?>"
                                  data-imported="<?php echo $invoice->is_imported ? '1' : '0'; ?>">
                       </td>
                       <td>#<?php echo $invoice->rand_id; ?></td>
                       <td><?php echo esc_html($invoice->customer_name); ?></td>
                       <td><?php echo date('M d, Y', strtotime($invoice->paid_at)); ?></td>
                       <td class="bntm-stat-income">₱<?php echo number_format($invoice->total, 2); ?></td>
                       <td>
                           <?php if ($invoice->is_imported): ?>
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
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.invoice-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        document.getElementById('select-all-not-imported').addEventListener('change', function() {
            document.querySelectorAll('.not-imported-invoice').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        document.getElementById('select-all-imported').addEventListener('change', function() {
            document.querySelectorAll('.imported-invoice').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-not-imported').checked = false;
            }
            updateSelectedCount();
        });
        
        document.querySelectorAll('.invoice-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        document.getElementById('bulk-import-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '0');
            
            if (selected.length === 0) {
                alert('Please select at least one invoice that is not imported');
                return;
            }
            
            const totalAmount = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
            
            if (!confirm(`Import ${selected.length} invoice(s) as income?\n\nTotal Amount: ₱${totalAmount.toFixed(2)}`)) return;
            
            this.disabled = true;
            this.textContent = 'Importing...';
            
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'op_import_invoice');
                data.append('invoice_id', cb.dataset.id);
                data.append('amount', cb.dataset.amount);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully imported ${total} invoice(s)`);
                        location.reload();
                    }
                })
                .catch(err => {
                    completed++;
                    if (completed === total) {
                        alert('Import completed with some errors.');
                        location.reload();
                    }
                });
            });
        });
        
        document.getElementById('bulk-revert-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.invoice-checkbox:checked'))
                .filter(cb => cb.dataset.imported === '1');
            
            if (selected.length === 0) {
                alert('Please select at least one imported invoice');
                return;
            }
            
            if (!confirm(`Remove ${selected.length} invoice(s) from Finance transactions?`)) return;
            
            this.disabled = true;
            this.textContent = 'Reverting...';
            
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'op_revert_invoice');
                data.append('invoice_id', cb.dataset.id);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully reverted ${total} invoice(s)`);
                        location.reload();
                    }
                })
                .catch(err => {
                    completed++;
                    if (completed === total) {
                        alert('Revert completed with some errors.');
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
    }
    .bntm-btn-secondary:hover {
        background: #4b5563;
    }
    </style>
    <?php
    return ob_get_clean();
}
function op_import_products_tab($business_id) {
    global $wpdb;
    $in_table = $wpdb->prefix . 'in_products';
    $op_products_table = $wpdb->prefix . 'op_imported_products';
    
    // Get all imported products
    $imported_products = $wpdb->get_results("SELECT * FROM {$op_products_table} ORDER BY imported_at DESC");
    
    // Get all inventory products available for import
    // Only show products NOT yet imported
    $available_imports = $wpdb->get_results("
        SELECT inprod.*
        FROM {$in_table} AS inprod
        LEFT JOIN {$op_products_table} AS opprod 
            ON inprod.id = opprod.product_id
        WHERE opprod.product_id IS NULL
        ORDER BY inprod.name ASC
    ");
    
    $nonce = wp_create_nonce('op_import_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Imported Products (<?php echo count($imported_products); ?>)</h3>
        
        <?php if (empty($imported_products)): ?>
            <p>No products imported yet. Import products from your inventory to get started.</p>
        <?php else: ?>
        
           <div class="bntm-table-wrapper">
               <table class="bntm-table">
                   <thead>
                       <tr>
                           <th>Name</th>
                           <th>SKU</th>
                           <th>Price</th>
                           <th>Stock</th>
                           <th>Imported</th>
                           <th>Actions</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($imported_products as $product): ?>
                           <tr data-product-id="<?php echo $product->id; ?>">
                               <td><strong><?php echo esc_html($product->product_name); ?></strong></td>
                               <td>
                                   <?php if ($product->sku): ?>
                                       <span class="product-sku"><?php echo esc_html($product->sku); ?></span>
                                   <?php else: ?>
                                       <span style="color: #9ca3af;">N/A</span>
                                   <?php endif; ?>
                               </td>
                               <td><?php echo op_format_price($product->price); ?></td>
                               <td>
                                   <?php echo esc_html($product->stock); ?>
                                   <?php
                                   // Get current inventory stock
                                   $current_stock = $wpdb->get_var($wpdb->prepare(
                                       "SELECT stock_quantity FROM {$in_table} WHERE id = %d",
                                       $product->product_id
                                   ));
                                   if ($current_stock !== null && $current_stock != $product->stock):
                                   ?>
                                       <span style="color: #f59e0b; font-size: 12px;">
                                           (Inventory: <?php echo $current_stock; ?>)
                                       </span>
                                   <?php endif; ?>
                               </td>
                               <td style="font-size: 12px; color: #6b7280;">
                                   <?php echo date('M d, Y', strtotime($product->imported_at)); ?>
                               </td>
                               <td>
                                   <button class="bntm-btn-small op-sync-product" 
                                           data-id="<?php echo $product->id; ?>"
                                           data-nonce="<?php echo $nonce; ?>"
                                           title="Sync with inventory">
                                       🔄 Sync
                                   </button>
                                   <button class="bntm-btn-small bntm-btn-danger op-remove-product" 
                                           data-id="<?php echo $product->id; ?>"
                                           data-nonce="<?php echo $nonce; ?>"
                                           title="Remove from imported products">
                                       Remove
                                   </button>
                               </td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($available_imports)): ?>
    <div class="bntm-form-section" style="background: #eff6ff; border-left: 4px solid #3b82f6;">
        <h3>Import Products from Inventory</h3>
        <p>Select products to import (<?php echo count($available_imports); ?> available)</p>
        
        <div style="margin: 15px 0;">
            <label style="cursor: pointer;">
                <input type="checkbox" id="select-all-products"> 
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
        
        <button id="op-import-selected" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>" style="margin-top: 15px;">
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
    .bntm-btn-danger {
        background: #dc2626;
        color: white;
    }
    .bntm-btn-danger:hover {
        background: #b91c1c;
    }
    </style>
    
    <script>
    (function() {
        // Select all checkbox
        const selectAllBtn = document.getElementById('select-all-products');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('change', function() {
                document.querySelectorAll('input[name="import_products[]"]').forEach(cb => {
                    cb.checked = this.checked;
                });
            });
        }
        
        // Import selected products
        const importBtn = document.getElementById('op-import-selected');
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
                formData.append('action', 'op_import_selected_products');
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
        
        // Sync product
        document.querySelectorAll('.op-sync-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Sync this product with inventory data?')) return;
                
                this.disabled = true;
                this.textContent = '⏳';
                
                const formData = new FormData();
                formData.append('action', 'op_sync_product');
                formData.append('product_id', this.dataset.id);
                formData.append('nonce', this.dataset.nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                    else {
                        this.disabled = false;
                        this.textContent = '🔄 Sync';
                    }
                });
            });
        });
        
        // Remove product
        document.querySelectorAll('.op-remove-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this product from imports? This will not delete it from inventory.')) return;
                
                const formData = new FormData();
                formData.append('action', 'op_remove_imported_product');
                formData.append('product_id', this.dataset.id);
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
    </script>
    <?php
    return ob_get_clean();
}
function op_settings_tab($business_id) {
    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    
    $payment_methods = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $methods_table ORDER BY priority ASC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('op_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Payment Methods Configuration</h3>
        <p>Setup your payment gateway credentials for PayPal and PayMaya</p>
        
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="margin: 0;">Active Payment Methods</h4>
                <button id="add-payment-method-btn" class="bntm-btn-primary">+ Add Payment Method</button>
            </div>
            
            <div id="payment-methods-list">
                <?php if (empty($payment_methods)): ?>
                    <p style="color: #6b7280;">No payment methods configured yet.</p>
                <?php else: ?>
                
                  <div class="bntm-table-wrapper">
                       <table class="bntm-table">
                           <thead>
                               <tr>
                                   <th>Name</th>
                                   <th>Gateway</th>
                                   <th>Mode</th>
                                   <th>Status</th>
                                   <th>Actions</th>
                               </tr>
                           </thead>
                           <tbody>
                               <?php foreach ($payment_methods as $method): ?>
                               <tr>
                                   <td><strong><?php echo esc_html($method->name); ?></strong></td>
                                   <td><?php echo esc_html(ucfirst($method->gateway)); ?></td>
                                   <td>
                                       <span class="status-badge status-<?php echo $method->mode === 'live' ? 'paid' : 'draft'; ?>">
                                           <?php echo ucfirst($method->mode); ?>
                                       </span>
                                   </td>
                                   <td>
                                       <?php if ($method->is_active): ?>
                                           <span style="color: #059669;">● Active</span>
                                       <?php else: ?>
                                           <span style="color: #6b7280;">● Inactive</span>
                                       <?php endif; ?>
                                   </td>
                                   <td>
                                       <button class="bntm-btn-small edit-method-btn" data-id="<?php echo $method->id; ?>">Edit</button>
                                       <button class="bntm-btn-small delete-method-btn" data-id="<?php echo $method->id; ?>" 
                                               style="background: #dc2626;">Delete</button>
                                   </td>
                               </tr>
                               <?php endforeach; ?>
                           </tbody>
                       </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div id="payment-method-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2 id="payment-modal-title">Add Payment Method</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="add-payment-method-form" class="bntm-form">
                <input type="hidden" name="method_id" id="method_id">
                
                <div class="bntm-form-group">
                    <label>Gateway Type *</label>
                    <select name="gateway_type" id="gateway-type-select" required>
                        <option value="">Select Gateway</option>
                        <option value="paypal">PayPal Business</option>
                        <option value="paymaya">PayMaya Business</option>
                        <option value="manual">Manual Payment</option>
                    </select>
                </div>

                <div class="bntm-form-group">
                    <label>Display Name *</label>
                    <input type="text" name="display_name" id="display_name" placeholder="e.g., PayPal, PayMaya" required>
                </div>

                <div class="bntm-form-group">
                    <label>Mode *</label>
                    <select name="mode" id="mode" required>
                        <option value="sandbox">Sandbox (Testing)</option>
                        <option value="live">Live (Production)</option>
                    </select>
                </div>

                <div class="bntm-form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                        Active
                    </label>
                </div>

                <!-- PayPal Fields -->
                <div class="gateway-fields" id="paypal-fields" style="display: none;">
                    <div class="bntm-form-group">
                        <label>Client ID *</label>
                        <input type="text" name="paypal_client_id" id="paypal_client_id" placeholder="Your PayPal Client ID">
                    </div>
                    <div class="bntm-form-group">
                        <label>Secret Key *</label>
                        <input type="password" name="paypal_secret_key" id="paypal_secret_key" placeholder="Your PayPal Secret Key">
                    </div>
                    <small style="display: block; margin-top: 10px; color: #6b7280;">
                        Get your credentials from <a href="https://developer.paypal.com" target="_blank">PayPal Developer Dashboard</a>
                    </small>
                </div>

                <!-- PayMaya Fields -->
                <div class="gateway-fields" id="paymaya-fields" style="display: none;">
                    <div class="bntm-form-group">
                        <label>Public Key *</label>
                        <input type="text" name="paymaya_public_key" id="paymaya_public_key" placeholder="Your PayMaya Public Key">
                    </div>
                    <div class="bntm-form-group">
                        <label>Secret Key *</label>
                        <input type="password" name="paymaya_secret_key" id="paymaya_secret_key" placeholder="Your PayMaya Secret Key">
                    </div>
                    <small style="display: block; margin-top: 10px; color: #6b7280;">
                        Get your credentials from <a href="https://merchant.paymaya.com" target="_blank">PayMaya Merchant Dashboard</a>
                    </small>
                </div>

                <!-- Manual Payment Fields -->
                <div class="gateway-fields" id="manual-fields" style="display: none;">
                    <div class="bntm-form-group">
                        <label>Account Name</label>
                        <input type="text" name="manual_account_name" id="manual_account_name" placeholder="e.g., Bank Account Holder Name">
                    </div>
                    <div class="bntm-form-group">
                        <label>Account Number</label>
                        <input type="text" name="manual_account_number" id="manual_account_number" placeholder="e.g., Bank Account Number">
                    </div>
                    <div class="bntm-form-group">
                        <label>Bank Name</label>
                        <input type="text" name="manual_bank_name" id="manual_bank_name" placeholder="e.g., BDO, BPI, Metrobank">
                    </div>
                    <div class="bntm-form-group">
                        <label>Instructions</label>
                        <textarea name="manual_instructions" id="manual_instructions" rows="3" placeholder="Payment instructions for customers"></textarea>
                    </div>
                </div>

                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Save Payment Method</button>
                </div>
                <div id="payment-method-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Invoice Settings</h3>
        <form id="invoice-settings-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="USD" <?php selected(bntm_get_setting('op_currency', 'USD'), 'USD'); ?>>USD - US Dollar</option>
                    <option value="EUR" <?php selected(bntm_get_setting('op_currency', 'USD'), 'EUR'); ?>>EUR - Euro</option>
                    <option value="GBP" <?php selected(bntm_get_setting('op_currency', 'USD'), 'GBP'); ?>>GBP - British Pound</option>
                    <option value="PHP" <?php selected(bntm_get_setting('op_currency', 'USD'), 'PHP'); ?>>PHP - Philippine Peso</option>
                </select>
            </div>
            <div class="bntm-form-group">
                <label>Tax Rate (%)</label>
                <input type="number" name="tax_rate" step="0.01" value="<?php echo esc_attr(bntm_get_setting('op_tax_rate', '0')); ?>">
            </div>
            <div class="bntm-form-group">
                <label>Default Payment Terms (Days)</label>
                <input type="number" name="payment_terms" value="<?php echo esc_attr(bntm_get_setting('op_payment_terms', '30')); ?>">
            </div>
            <div class="bntm-form-group">
                <label>Company Name</label>
                <input type="text" name="company_name" value="<?php echo esc_attr(bntm_get_setting('op_company_name', '')); ?>" placeholder="Your Company Name">
            </div>
            <div class="bntm-form-group">
                <label>Company Address</label>
                <textarea name="company_address" rows="3" placeholder="Your Company Address"><?php echo esc_textarea(bntm_get_setting('op_company_address', '')); ?></textarea>
            </div>
            <button type="submit" class="bntm-btn-primary">Save Invoice Settings</button>
            <div id="invoice-settings-message" style="margin-top: 15px;"></div>
        </form>
    </div>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        const modal = document.getElementById('payment-method-modal');
        const gatewaySelect = document.getElementById('gateway-type-select');
        const paymentMethodMessage = document.getElementById('payment-method-message');
        
        // Open add modal
        document.getElementById('add-payment-method-btn').addEventListener('click', function() {
            document.getElementById('payment-modal-title').textContent = 'Add Payment Method';
            document.getElementById('add-payment-method-form').reset();
            document.getElementById('method_id').value = '';
            document.querySelectorAll('.gateway-fields').forEach(field => field.style.display = 'none');
            modal.style.display = 'block';
        });
        
        // Close modal
        document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
            el.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
        
        // Toggle gateway fields
        gatewaySelect.addEventListener('change', function() {
            document.querySelectorAll('.gateway-fields').forEach(field => {
                field.style.display = 'none';
            });
            
            if (this.value === 'paypal') {
                document.getElementById('paypal-fields').style.display = 'block';
            } else if (this.value === 'paymaya') {
                document.getElementById('paymaya-fields').style.display = 'block';
            } else if (this.value === 'manual') {
                document.getElementById('manual-fields').style.display = 'block';
            }
        });
        
        // Edit payment method
        document.querySelectorAll('.edit-method-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const methodId = this.dataset.id;
                
                const formData = new FormData();
                formData.append('action', 'op_get_payment_method');
                formData.append('method_id', methodId);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const method = json.data;
                        document.getElementById('payment-modal-title').textContent = 'Edit Payment Method';
                        document.getElementById('method_id').value = method.id;
                        document.getElementById('gateway-type-select').value = method.gateway;
                        document.getElementById('display_name').value = method.name;
                        document.getElementById('mode').value = method.mode;
                        document.getElementById('is_active').checked = method.is_active == 1;
                        
                        // Trigger gateway change
                        gatewaySelect.dispatchEvent(new Event('change'));
                        
                        // Fill gateway-specific fields
                        const config = JSON.parse(method.config || '{}');
                        if (method.gateway === 'paypal') {
                            document.getElementById('paypal_client_id').value = config.client_id || '';
                            document.getElementById('paypal_secret_key').value = config.secret_key || '';
                        } else if (method.gateway === 'paymaya') {
                            document.getElementById('paymaya_public_key').value = config.public_key || '';
                            document.getElementById('paymaya_secret_key').value = config.secret_key || '';
                        } else if (method.gateway === 'manual') {
                            document.getElementById('manual_account_name').value = config.account_name || '';
                            document.getElementById('manual_account_number').value = config.account_number || '';
                            document.getElementById('manual_bank_name').value = config.bank_name || '';
                            document.getElementById('manual_instructions').value = config.instructions || '';
                        }
                        
                        modal.style.display = 'block';
                    }
                });
            });
        });
        
        // Delete payment method
        document.querySelectorAll('.delete-method-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this payment method?')) return;
                
                const methodId = this.dataset.id;
                const formData = new FormData();
                formData.append('action', 'op_delete_payment_method');
                formData.append('method_id', methodId);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Payment method deleted successfully');
                        location.reload();
                    } else {
                        alert(json.data.message);
                    }
                });
            });
        });
        
        // Add/Update payment method
        document.getElementById('add-payment-method-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'op_setup_payment_methods');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    paymentMethodMessage.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    paymentMethodMessage.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Save Payment Method';
                }
            });
        });
        
        // Save invoice settings
        document.getElementById('invoice-settings-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'op_save_settings');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('invoice-settings-message');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Invoice Settings';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- INVOICE PAGE ---------- */
/* ---------- INVOICE PAGE ---------- */
function bntm_shortcode_op_invoice() {
    $invoice_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($invoice_id)) {
        return '<div class="bntm-container"><p>Invalid invoice ID.</p></div>';
    }

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE rand_id = %s",
        $invoice_id
    ));

    if (!$invoice) {
        return '<div class="bntm-container"><p>Invoice not found.</p></div>';
    }
    
    $products_table = $wpdb->prefix . 'op_invoice_products';
    $invoice_products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $products_table WHERE invoice_id = %d",
        $invoice->id
    ));
    $has_products = !empty($invoice_products);
    
    $logo = bntm_get_site_logo();
    $site_title = bntm_get_site_title();
    $show_payment_btn = $invoice->payment_status === 'unpaid' || $invoice->payment_status === 'pending_verification';

    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    .invoice-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; background: white; font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.4; color: #000; }
    .invoice-header { display: flex; justify-content: space-between; padding-bottom: 15px; margin-bottom: 20px; border-bottom: 2px solid #000; }
    .company-name { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
    .invoice-title { font-size: 28pt; font-weight: bold; text-align: right; }
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
    .subtotal-row td { border-bottom: 1px solid #ddd; }
    .total-row { font-size: 12pt; }
    .total-row td { padding: 10px 5px; border-top: 2px solid #000; border-bottom: 2px solid #000; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .item-description { font-size: 9pt; color: #666; margin-top: 3px; }
    .info-box { margin: 15px 0; padding: 12px; border: 1px solid #000; }
    .info-box-title { font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid #ddd; }
    .info-box p { margin: 5px 0; font-size: 10pt; }
    .invoice-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #000; text-align: center; font-size: 10pt; }
    .invoice-footer p { margin: 5px 0; }
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
            <button onclick="window.print()" class="bntm-btn bntm-btn-secondary" style="margin-left: 10px;">Download PDF</button>
        </div>

        <div class="invoice-header">
            <div>
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 120px; max-height: 50px; margin-bottom: 10px;">
                <?php endif; ?>
                <div class="company-name"><?php echo esc_html($site_title ?: bntm_get_setting('op_company_name', 'Your Company')); ?></div>
                <div style="font-size: 10pt; color: #333;"><?php echo esc_html(bntm_get_setting('op_company_address', '')); ?></div>
            </div>
            <div>
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number">#<?php echo esc_html($invoice->rand_id); ?></div>
            </div>
        </div>

        <div class="invoice-info">
            <div>
                <div class="info-label">BILL TO</div>
                <div class="customer-name"><?php echo esc_html($invoice->customer_name); ?></div>
                <?php if ($invoice->customer_email): ?>
                <div><?php echo esc_html($invoice->customer_email); ?></div>
                <?php endif; ?>
                <?php if ($invoice->customer_phone): ?>
                <div><?php echo esc_html($invoice->customer_phone); ?></div>
                <?php endif; ?>
                <?php if ($invoice->customer_address): ?>
                <div><?php echo esc_html($invoice->customer_address); ?></div>
                <?php endif; ?>
            </div>
            <div>
                <table class="info-table">
                    <tr>
                        <td><strong>Invoice Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($invoice->created_at)); ?></td>
                    </tr>
                    <?php if ($invoice->due_date): ?>
                    <tr>
                        <td><strong>Due Date:</strong></td>
                        <td><?php echo date('M d, Y', strtotime($invoice->due_date)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td><?php echo ucfirst($invoice->status); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Payment:</strong></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $invoice->payment_status)); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <?php if ($has_products): ?>
        <table>
            <thead>
                <tr>
                    <th>PRODUCT</th>
                    <th class="text-center">QTY</th>
                    <th class="text-right">UNIT PRICE</th>
                    <th class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice_products as $product): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($product->product_name); ?></strong>
                        <?php if (!empty($invoice->description)): ?>
                        <div class="item-description"><?php echo nl2br(esc_html($invoice->description)); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo intval($product->quantity); ?></td>
                    <td class="text-right"><?php echo op_format_price($product->unit_price); ?></td>
                    <td class="text-right"><strong><?php echo op_format_price($product->total); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="3" class="text-right"><strong>Subtotal</strong></td>
                    <td class="text-right"><strong><?php echo op_format_price($invoice->amount); ?></strong></td>
                </tr>
                <?php if ($invoice->tax > 0): ?>
                <tr class="subtotal-row">
                    <td colspan="3" class="text-right"><strong>Tax</strong></td>
                    <td class="text-right"><strong><?php echo op_format_price($invoice->tax); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
                    <td class="text-right"><strong><?php echo op_format_price($invoice->total); ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>DESCRIPTION</th>
                    <th class="text-right">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="white-space: pre-line;"><?php echo nl2br(esc_html($invoice->description)); ?></td>
                    <td class="text-right"><?php echo op_format_price($invoice->amount); ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td><strong>Subtotal</strong></td>
                    <td class="text-right"><strong><?php echo op_format_price($invoice->amount); ?></strong></td>
                </tr>
                <?php if ($invoice->tax > 0): ?>
                <tr class="subtotal-row">
                    <td><strong>Tax</strong></td>
                    <td class="text-right"><strong><?php echo op_format_price($invoice->tax); ?></strong></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td class="text-right"><strong><?php echo op_format_price($invoice->total); ?></strong></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>

        <?php if ($invoice->payment_status === 'verifying' && $invoice->payment_reference): ?>
        <div class="info-box">
            <div class="info-box-title">PAYMENT INFORMATION</div>
            <p><strong>Reference:</strong> <?php echo esc_html($invoice->payment_reference); ?></p>
            <?php if ($invoice->payment_account_name): ?>
            <p><strong>Account:</strong> <?php echo esc_html($invoice->payment_account_name); ?></p>
            <?php endif; ?>
            <?php if ($invoice->payment_bank): ?>
            <p><strong>Bank:</strong> <?php echo esc_html($invoice->payment_bank); ?></p>
            <?php endif; ?>
            <p style="margin-top: 10px;"><em>Payment is being verified.</em></p>
        </div>
        <?php endif; ?>

        <?php if ($invoice->notes): ?>
        <div class="info-box">
            <div class="info-box-title">NOTES</div>
            <p><?php echo nl2br(esc_html($invoice->notes)); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($show_payment_btn): ?>
        <div style="margin-top: 25px; text-align: center;" class="no-print">
            <a href="<?php echo get_page_link(get_page_by_path('payment')) . '?invoice=' . esc_attr($invoice->rand_id); ?>" 
               class="bntm-btn bntm-btn-primary" style="padding: 12px 30px; font-size: 16px;">
                <?php echo $invoice->payment_status === 'pending_verification' ? 'Complete Payment' : 'Pay Now'; ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="invoice-footer">
            <p>Thank you for your business!</p>
            <?php if (bntm_get_setting('op_company_name')): ?>
            <p><?php echo esc_html(bntm_get_setting('op_company_name')); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return $content;
}
/* ---------- PAYMENT PAGE ---------- */
function bntm_shortcode_op_payment_page() {
    $invoice_id = isset($_GET['invoice']) ? sanitize_text_field($_GET['invoice']) : '';
    
    if (empty($invoice_id)) {
        return '<div class="bntm-container"><p>Invalid invoice.</p></div>';
    }

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE rand_id = %s",
        $invoice_id
    ));

    if (!$invoice) {
        return '<div class="bntm-container"><p>Invoice not found.</p></div>';
    }

    if ($invoice->payment_status === 'paid') {
        return '<div class="bntm-container"><p style="color: #059669; font-size: 18px;"><strong>✓ This invoice has already been paid.</strong></p></div>';
    }

    // Get active payment methods
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    $payment_methods = $wpdb->get_results(
        "SELECT * FROM $methods_table WHERE is_active = 1 ORDER BY priority ASC"
    );

    if (empty($payment_methods)) {
        return '<div class="bntm-container"><p>No payment methods available. Please contact support.</p></div>';
    }

    $nonce = wp_create_nonce('op_payment_nonce');
    $is_verification = $invoice->payment_status === 'pending_verification';

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-container" style="max-width: 600px; margin: 0 auto;">
        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h1><?php echo $is_verification ? 'Confirm Payment' : 'Payment'; ?> for Invoice #<?php echo esc_html($invoice->rand_id); ?></h1>
            
            <div style="background: #eff6ff; padding: 20px; border-left: 4px solid #3b82f6; border-radius: 4px; margin: 20px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="margin: 0; color: #6b7280;">Amount Due</p>
                        <h2 style="margin: 5px 0 0 0; font-size: 32px; color: #1f2937;"><?php echo op_format_price($invoice->total); ?></h2>
                    </div>
                    <div style="text-align: right;">
                        <p style="margin: 0; color: #6b7280;">Invoice #<?php echo esc_html($invoice->rand_id); ?></p>
                        <p style="margin: 5px 0 0 0; color: #6b7280;"><?php echo date('M d, Y', strtotime($invoice->created_at)); ?></p>
                    </div>
                </div>
            </div>

            <?php if (!$is_verification): ?>
            <form id="payment-form" class="bntm-form">
                <h3>Select Payment Method</h3>
                <div class="payment-methods-grid">
                    <?php foreach ($payment_methods as $method): 
                        $config = json_decode($method->config, true);
                    ?>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="<?php echo esc_attr($method->id); ?>" data-gateway="<?php echo esc_attr($method->gateway); ?>" <?php echo isset($payment_methods[0]) && $payment_methods[0]->id === $method->id ? 'checked' : ''; ?> required>
                            <div class="payment-method-card">
                                <strong><?php echo esc_html($method->name); ?></strong>
                                <span class="method-badge"><?php echo ucfirst($method->gateway); ?></span>
                                <?php if (!empty($config['instructions'])): ?>
                                <p style="margin: 8px 0 0 0; font-size: 13px; color: #6b7280;">
                                    <?php echo esc_html($config['instructions']); ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($method->gateway === 'manual' && !empty($config['account_name'])): ?>
                                <div style="margin-top: 10px; padding: 10px; background: #f9fafb; border-radius: 4px; font-size: 12px;">
                                    <?php if (!empty($config['bank_name'])): ?>
                                    <p style="margin: 2px 0;"><strong>Bank:</strong> <?php echo esc_html($config['bank_name']); ?></p>
                                    <?php endif; ?>
                                    <p style="margin: 2px 0;"><strong>Account Name:</strong> <?php echo esc_html($config['account_name']); ?></p>
                                    <p style="margin: 2px 0;"><strong>Account Number:</strong> <?php echo esc_html($config['account_number']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice->rand_id); ?>">
                <input type="hidden" name="amount" value="<?php echo esc_attr($invoice->total); ?>">
                
                <button type="submit" class="bntm-btn-primary" id="pay-btn" style="width: 100%; padding: 12px; font-size: 16px; margin-top: 20px;">
                    Proceed to Payment
                </button>
                <div id="payment-message" style="margin-top: 15px;"></div>
            </form>
            <?php else: ?>
            <!-- Confirm Manual Payment Form -->
            <form id="confirm-payment-form" class="bntm-form">
                <h3>Confirm Your Payment</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Please provide your payment details for verification.</p>
                
                <div class="bntm-form-group">
                    <label>Payment Reference Number *</label>
                    <input type="text" name="payment_reference" required placeholder="e.g., Transaction ID, Reference Number">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Name Used *</label>
                    <input type="text" name="account_name" required placeholder="Name on the account">
                </div>
                
                <div class="bntm-form-group">
                    <label>Bank/Payment Provider *</label>
                    <input type="text" name="bank_name" required placeholder="e.g., BDO, BPI, GCash">
                </div>
                
                <div class="bntm-form-group">
                    <label>Additional Notes (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="Any additional information"></textarea>
                </div>
                
                <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice->rand_id); ?>">
                
                <button type="submit" class="bntm-btn-primary" style="width: 100%; padding: 12px; font-size: 16px; margin-top: 20px;">
                    Submit Payment Confirmation
                </button>
                <div id="confirm-message" style="margin-top: 15px;"></div>
            </form>
            <?php endif; ?>
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
    .method-badge {
        display: inline-block;
        margin-left: 10px;
        padding: 2px 8px;
        background: #f3f4f6;
        border-radius: 4px;
        font-size: 12px;
        color: #6b7280;
    }
    </style>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        <?php if (!$is_verification): ?>
        // Process payment form
        const form = document.getElementById('payment-form');
        const btn = document.getElementById('pay-btn');
        const msg = document.getElementById('payment-message');
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            const gateway = selectedMethod.dataset.gateway;
            
            const formData = new FormData(this);
            formData.append('action', 'op_process_payment');
            formData.append('nonce', nonce);
            
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    if (json.data.redirect_url) {
                        window.location.href = json.data.redirect_url;
                    } else {
                        msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                        setTimeout(() => {
                            window.location.href = '<?php echo get_page_link(get_page_by_path('invoice')) . '?id=' . esc_attr($invoice->rand_id); ?>';
                        }, 2000);
                    }
                } else {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Proceed to Payment';
                }
            })
            .catch(err => {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Proceed to Payment';
            });
        });
        <?php else: ?>
        // Confirm manual payment form
        const confirmForm = document.getElementById('confirm-payment-form');
        const confirmMsg = document.getElementById('confirm-message');
        
        confirmForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'op_confirm_manual_payment');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Submitting...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    confirmMsg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => {
                        window.location.href = '<?php echo get_page_link(get_page_by_path('invoice')) . '?id=' . esc_attr($invoice->rand_id); ?>';
                    }, 2000);
                } else {
                    confirmMsg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Submit Payment Confirmation';
                }
            });
        });
        <?php endif; ?>
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Payment', $content);
}

/* ---------- AJAX HANDLERS ---------- */

function bntm_ajax_op_create_invoice() {
    check_ajax_referer('op_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $business_id = get_current_user_id();
     
    // Check table limit
    $limits = get_option('bntm_table_limits', []);
    if (isset($limits[$invoices_table]) && $limits[$invoices_table] > 0) {
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM $invoices_table");
        if ($current_count >= $limits[$invoices_table]) {
            wp_send_json_error(['message' => "Invoice limit reached. Maximum {$limits[$invoices_table]} invoices allowed."]);
        }
    }

    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_address = sanitize_textarea_field($_POST['customer_address'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $due_date = sanitize_text_field($_POST['due_date'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($customer_name) || empty($customer_email) || $amount <= 0 || empty($description)) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
    }
        $invoice_type = sanitize_text_field($_POST['invoice_type'] ?? 'manual');
       $description = '';
       $amount = 0;
       
       if ($invoice_type === 'products') {
           $products = json_decode(stripslashes($_POST['products'] ?? '[]'), true);
           
           if (empty($products)) {
               wp_send_json_error(['message' => 'Please add at least one product']);
           }
           
           $description_lines = [];
           foreach ($products as $product) {
               $line_total = floatval($product['price']) * intval($product['quantity']);
               $amount += $line_total;
               $description_lines[] = sprintf(
                   "%s x%d @ ₱%s = ₱%s",
                   $product['name'],
                   $product['quantity'],
                   number_format($product['price'], 2),
                   number_format($line_total, 2)
               );
           }
           $description = implode("\n", $description_lines);
       } else {
           $description = sanitize_textarea_field($_POST['description'] ?? '');
           $amount = floatval($_POST['amount'] ?? 0);
       }
    $tax_rate = floatval(bntm_get_setting('op_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax;
    
    if (empty($due_date)) {
        $payment_terms = intval(bntm_get_setting('op_payment_terms', '30'));
        $due_date = date('Y-m-d', strtotime("+$payment_terms days"));
    }

    $result = $wpdb->insert($invoices_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'reference_type' => 'manual',
        'reference_id' => 0,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_address' => $customer_address,
        'description' => $description,
        'amount' => $amount,
        'tax' => $tax,
        'total' => $total,
        'currency' => bntm_get_setting('op_currency', 'USD'),
        'status' => 'draft',
        'payment_status' => 'unpaid',
        'due_date' => $due_date,
        'notes' => $notes,
        'created_at' => current_time('mysql')
    ], [
        '%s','%d','%s','%d','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%s','%s','%s'
    ]);
    // After invoice is created, save products if applicable
    if ($invoice_type === 'products' && !empty($products)) {
        $invoice_id = $wpdb->insert_id;
        $products_table = $wpdb->prefix . 'op_invoice_products';
        
        foreach ($products as $product) {
            $wpdb->insert($products_table, [
                'invoice_id' => $invoice_id,
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'quantity' => $product['quantity'],
                'unit_price' => $product['price'],
                'total' => floatval($product['price']) * intval($product['quantity'])
            ]);
        }
    }
    if ($result) {
        wp_send_json_success(['message' => 'Invoice created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create invoice.']);
    }
}

function bntm_ajax_op_update_invoice_status() {
    check_ajax_referer('op_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $business_id = get_current_user_id();
    
    $invoice_id = sanitize_text_field($_POST['invoice_id'] ?? '');
    $invoice_status = sanitize_text_field($_POST['invoice_status'] ?? '');
    $payment_status = sanitize_text_field($_POST['payment_status'] ?? '');
    
    $update_data = [
        'status' => $invoice_status,
        'payment_status' => $payment_status
    ];
    
    // If marking as paid, set paid_at timestamp
    if ($payment_status === 'paid') {
        $update_data['paid_at'] = current_time('mysql');
        // Also update invoice status to paid
        $update_data['status'] = 'paid';
    }
    
    $result = $wpdb->update(
        $invoices_table,
        $update_data,
        [
            'rand_id' => $invoice_id,
            'business_id' => $business_id
        ],
        array_fill(0, count($update_data), '%s'),
        ['%s', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Status updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status.']);
    }
}

function bntm_ajax_op_confirm_manual_payment() {
    check_ajax_referer('op_payment_nonce', 'nonce');
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $invoice_id = sanitize_text_field($_POST['invoice_id'] ?? '');
    $payment_reference = sanitize_text_field($_POST['payment_reference'] ?? '');
    $account_name = sanitize_text_field($_POST['account_name'] ?? '');
    $bank_name = sanitize_text_field($_POST['bank_name'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($invoice_id) || empty($payment_reference) || empty($account_name) || empty($bank_name)) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
    }
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE rand_id = %s",
        $invoice_id
    ));
    
    if (!$invoice) {
        wp_send_json_error(['message' => 'Invoice not found']);
    }
    
    // Update invoice with payment details and set status to verifying
    $result = $wpdb->update(
        $invoices_table,
        [
            'payment_status' => 'verifying',
            'payment_reference' => $payment_reference,
            'payment_account_name' => $account_name,
            'payment_bank' => $bank_name,
            'notes' => empty($invoice->notes) ? $notes : $invoice->notes . "\n\nCustomer Payment Info: " . $notes
        ],
        ['id' => $invoice->id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Payment confirmation submitted! The merchant will verify your payment.']);
    } else {
        wp_send_json_error(['message' => 'Failed to submit payment confirmation.']);
    }
}

function bntm_ajax_op_process_payment() {
    check_ajax_referer('op_payment_nonce', 'nonce');
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    $payments_table = $wpdb->prefix . 'op_payments';
    
    $invoice_id = sanitize_text_field($_POST['invoice_id'] ?? '');
    $method_id = intval($_POST['payment_method'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    
    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE rand_id = %s",
        $invoice_id
    ));

    if (!$invoice || $invoice->payment_status === 'paid') {
        wp_send_json_error(['message' => 'Invalid or already paid invoice']);
    }

    $payment_method = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $methods_table WHERE id = %d AND is_active = 1",
        $method_id
    ));

    if (!$payment_method) {
        wp_send_json_error(['message' => 'Invalid payment method']);
    }

    $config = json_decode($payment_method->config, true);
    
    // Create payment record
    $payment_rand_id = bntm_rand_id();
    $wpdb->insert($payments_table, [
        'rand_id' => $payment_rand_id,
        'business_id' => $invoice->business_id,
        'invoice_id' => $invoice->id,
        'amount' => $amount,
        'payment_method' => 'online',
        'payment_gateway' => $payment_method->gateway,
        'status' => 'pending',
        'attempted_at' => current_time('mysql')
    ], [
        '%s','%d','%d','%f','%s','%s','%s','%s'
    ]);

    // Process based on gateway
    $result = [];
    switch ($payment_method->gateway) {
        case 'paypal':
            $result = op_process_paypal_payment($invoice, $payment_method, $config, $amount);
            break;
        case 'paymaya':
            $result = op_process_paymaya_payment($invoice, $payment_method, $config, $amount);
            break;
        case 'manual':
            $result = op_process_manual_payment($invoice, $config);
            break;
        default:
            wp_send_json_error(['message' => 'Unsupported payment gateway']);
    }

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

function bntm_ajax_op_setup_payment_methods() {
    check_ajax_referer('op_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    $business_id = get_current_user_id();
    
    $method_id = intval($_POST['method_id'] ?? 0);
    $gateway_type = sanitize_text_field($_POST['gateway_type'] ?? '');
    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    $mode = sanitize_text_field($_POST['mode'] ?? 'sandbox');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($gateway_type) || empty($display_name)) {
        wp_send_json_error(['message' => 'Missing required fields']);
    }

    $config = [];
    
    // Build config based on gateway type
    switch ($gateway_type) {
        case 'paypal':
            $client_id = sanitize_text_field($_POST['paypal_client_id'] ?? '');
            $secret_key = sanitize_text_field($_POST['paypal_secret_key'] ?? '');
            
            if (empty($client_id) || empty($secret_key)) {
                wp_send_json_error(['message' => 'PayPal credentials are required']);
            }
            
            $config = [
                'client_id' => $client_id,
                'secret_key' => $secret_key
            ];
            break;
            
        case 'paymaya':
            $public_key = sanitize_text_field($_POST['paymaya_public_key'] ?? '');
            $secret_key = sanitize_text_field($_POST['paymaya_secret_key'] ?? '');
            
            if (empty($public_key) || empty($secret_key)) {
                wp_send_json_error(['message' => 'PayMaya credentials are required']);
            }
            
            $config = [
                'public_key' => $public_key,
                'secret_key' => $secret_key
            ];
            break;
            
        case 'manual':
            $account_name = sanitize_text_field($_POST['manual_account_name'] ?? '');
            $account_number = sanitize_text_field($_POST['manual_account_number'] ?? '');
            $bank_name = sanitize_text_field($_POST['manual_bank_name'] ?? '');
            $instructions = sanitize_textarea_field($_POST['manual_instructions'] ?? '');
            
            $config = [
                'account_name' => $account_name,
                'account_number' => $account_number,
                'bank_name' => $bank_name,
                'instructions' => $instructions
            ];
            break;
    }

    $data = [
        'name' => $display_name,
        'type' => 'online',
        'gateway' => $gateway_type,
        'mode' => $mode,
        'config' => json_encode($config),
        'is_active' => $is_active
    ];

    if ($method_id > 0) {
        // Update existing
        $result = $wpdb->update(
            $methods_table,
            $data,
            ['id' => $method_id, 'business_id' => $business_id],
            ['%s', '%s', '%s', '%s', '%s', '%d'],
            ['%d', '%d']
        );
        $message = 'Payment method updated successfully!';
    } else {
        // Insert new
        $data['rand_id'] = bntm_rand_id();
        $data['business_id'] = $business_id;
        $data['priority'] = 0;
        $data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $methods_table,
            $data,
            ['%s','%d','%s','%s','%s','%s','%s','%d','%d','%s']
        );
        $message = 'Payment method configured successfully!';
    }

    if ($result !== false) {
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to save payment method']);
    }
}

function bntm_ajax_op_get_payment_method() {
    check_ajax_referer('op_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    $business_id = get_current_user_id();
    $method_id = intval($_POST['method_id'] ?? 0);
    
    $method = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $methods_table WHERE id = %d ",
        $method_id, $business_id
    ));

    if ($method) {
        wp_send_json_success((array)$method);
    } else {
        wp_send_json_error(['message' => 'Payment method not found']);
    }
}

function bntm_ajax_op_delete_payment_method() {
    check_ajax_referer('op_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    $business_id = get_current_user_id();
    $method_id = intval($_POST['method_id'] ?? 0);
    
    $result = $wpdb->delete(
        $methods_table,
        ['id' => $method_id, 'business_id' => $business_id],
        ['%d', '%d']
    );

    if ($result) {
        wp_send_json_success(['message' => 'Payment method deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete payment method']);
    }
}


function bntm_ajax_op_import_invoice() {
    check_ajax_referer('bntm_fn_action', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $invoice_id = intval($_POST['invoice_id']);
    $amount = floatval($_POST['amount']);
    $rand_id = bntm_rand_id();

    $invoice = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $invoices_table WHERE id = %d",
        $invoice_id
    ));

    if (!$invoice) {
        wp_send_json_error(['message' => 'Invoice not found']);
    }

    // Check if already imported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$txn_table} WHERE reference_type='invoice' AND reference_id=%d",
        $invoice_id
    ));

    if ($exists) {
        wp_send_json_error(['message' => 'Invoice already imported']);
    }

    $data = [
        'rand_id' => $rand_id,
        'business_id' => $invoice->business_id,
        'type' => 'income',
        'amount' => $amount,
        'category' => 'Payment',
        'notes' => 'Invoice Payment #' . $invoice->rand_id,
        'reference_type' => 'invoice',
        'reference_id' => $invoice_id
    ];

    $result = $wpdb->insert($txn_table, $data);

    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Invoice imported successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to import invoice']);
    }
}

function bntm_ajax_op_revert_invoice() {
    check_ajax_referer('bntm_fn_action', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $invoice_id = intval($_POST['invoice_id']);

    $result = $wpdb->delete($table, [
        'reference_type' => 'invoice',
        'reference_id' => $invoice_id
    ]);

    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Invoice reverted from transactions']);
    } else {
        wp_send_json_error(['message' => 'Failed to revert invoice']);
    }
}

function bntm_ajax_op_save_settings() {
    check_ajax_referer('op_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('op_currency', sanitize_text_field($_POST['currency'] ?? 'USD'));
    bntm_set_setting('op_tax_rate', floatval($_POST['tax_rate'] ?? 0));
    bntm_set_setting('op_payment_terms', intval($_POST['payment_terms'] ?? 30));
    bntm_set_setting('op_company_name', sanitize_text_field($_POST['company_name'] ?? ''));
    bntm_set_setting('op_company_address', sanitize_textarea_field($_POST['company_address'] ?? ''));

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

/* ---------- WEBHOOK HANDLERS ---------- */

function bntm_ajax_op_webhook_paypal() {
    $body = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '';
    $transmission_id = $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '';
    
    $data = json_decode($body, true);
    
    if ($data['event_type'] === 'CHECKOUT.ORDER.COMPLETED') {
        $order_id = $data['resource']['id'];
        op_complete_invoice_payment('paypal', $order_id);
    }

    wp_send_json_success();
}



function bntm_ajax_op_webhook_paymaya() {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    
    // Log the webhook for debugging
    error_log('PayMaya Webhook Received: ' . print_r($data, true));
    
    // PayMaya webhook sends different event types
    $event_type = isset($data['name']) ? $data['name'] : '';
    $status = isset($data['status']) ? $data['status'] : '';
    
    // Handle different PayMaya webhook formats
    if ($event_type === 'PAYMENT_SUCCESS' || $event_type === 'CHECKOUT_SUCCESS' || $status === 'COMPLETED') {
        // Get reference number (this should match your invoice rand_id)
        $reference_id = '';
        
        if (isset($data['requestReferenceNumber'])) {
            $reference_id = $data['requestReferenceNumber'];
        } elseif (isset($data['reference'])) {
            $reference_id = $data['reference'];
        } elseif (isset($data['metadata']['invoice_id'])) {
            $reference_id = $data['metadata']['invoice_id'];
        }
        
        if (!empty($reference_id)) {
            op_complete_invoice_payment('paymaya', $reference_id);
        } else {
            error_log('PayMaya Webhook: No reference ID found');
        }
    }

    wp_send_json_success();
}
/* ---------- PAYMENT GATEWAY IMPLEMENTATIONS ---------- */

function op_process_paypal_payment($invoice, $payment_method, $config, $amount) {
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
            'return_url' => get_page_link(get_page_by_path('invoice')) . '?id=' . $invoice->rand_id . '&payment_success=1',
            'cancel_url' => get_page_link(get_page_by_path('payment')) . '?invoice=' . $invoice->rand_id
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

function op_process_paymaya_payment($invoice, $payment_method, $config, $amount) {
    $mode = $payment_method->mode;
    $base_url = $mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';

    // PayMaya expects amount in decimal format, not cents
    // Added required buyer and items information
    $checkout_data = [
        'totalAmount' => [
            'value' => floatval($amount),
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
                'name' => 'Invoice Payment - ' . $invoice->rand_id,
                'quantity' => 1,
                'amount' => [
                    'value' => floatval($amount)
                ],
                'totalAmount' => [
                    'value' => floatval($amount)
                ]
            ]
        ],
        'redirectUrl' => [
            'success' => get_page_link(get_page_by_path('invoice')) . '?id=' . $invoice->rand_id . '&payment_success=1&gateway=paymaya',
            'failure' => get_page_link(get_page_by_path('payment')) . '?invoice=' . $invoice->rand_id,
            'cancel' => get_page_link(get_page_by_path('payment')) . '?invoice=' . $invoice->rand_id
        ],
        'requestReferenceNumber' => $invoice->rand_id,
        'metadata' => [
            'invoice_id' => $invoice->rand_id,
            'customer_email' => $invoice->customer_email ?? ''
        ]
    ];

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
    error_log('PayMaya Response Code: ' . $status_code);
    error_log('PayMaya Response: ' . print_r($response_data, true));

    // Check for both 200 and 201 status codes (PayMaya returns 201 for created)
    if ($status_code !== 200 && $status_code !== 201) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return ['success' => false, 'message' => 'PayMaya checkout creation failed: ' . $error_message . ' (Status: ' . $status_code . ')'];
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

// Add this function to handle success redirects
add_action('template_redirect', 'op_handle_payment_success_redirect');
function op_handle_payment_success_redirect() {
    // Check if this is a payment success redirect
    if (!isset($_GET['payment_success']) || $_GET['payment_success'] != 1) {
        return;
    }
    
    // Check if invoice ID is present
    if (!isset($_GET['id'])) {
        return;
    }
    
    $invoice_rand_id = sanitize_text_field($_GET['id']);
    $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
    
    // Handle PayMaya success
    if ($gateway === 'paymaya') {
        // Complete the payment
        op_complete_invoice_payment('paymaya', $invoice_rand_id);
    }
    
    // Handle PayPal success (you can verify the order here if needed)
    if (isset($_GET['token'])) {
        // This is a PayPal return
        // Optionally verify the PayPal order status before completing
        op_complete_invoice_payment('paypal', $invoice_rand_id);
    }
}
function op_complete_invoice_payment($gateway, $transaction_id) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $payments_table = $wpdb->prefix . 'op_payments';
    
    // Try to find payment record by transaction_id first
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $payments_table WHERE payment_gateway = %s AND transaction_id = %s",
        $gateway, $transaction_id
    ));
    
    // If not found, maybe transaction_id is actually the invoice rand_id
    // Try to find by invoice rand_id
    if (!$payment) {
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $invoices_table WHERE rand_id = %s",
            $transaction_id
        ));
        
        if ($invoice) {
            // Found invoice, now get the payment record
            $payment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $payments_table WHERE invoice_id = %d AND payment_gateway = %s ORDER BY id DESC LIMIT 1",
                $invoice->id, $gateway
            ));
        }
    }
    
    if (!$payment) {
        error_log("Payment not found for gateway: $gateway, identifier: $transaction_id");
        return false;
    }
    
    // Check if already completed
    if ($payment->status === 'completed') {
        error_log("Payment already completed: " . $payment->id);
        return true; // Already done, return success
    }
    
    // Update invoice to paid
    $wpdb->update(
        $invoices_table,
        [
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => $gateway,
            'payment_reference' => $transaction_id,
            'paid_at' => current_time('mysql')
        ],
        ['id' => $payment->invoice_id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );
    
    // Update payment record
    $wpdb->update(
        $payments_table,
        [
            'status' => 'completed',
            'completed_at' => current_time('mysql')
        ],
        ['id' => $payment->id],
        ['%s', '%s'],
        ['%d']
    );
    
    error_log("Payment completed successfully: Invoice #{$payment->invoice_id}, Payment #{$payment->id}, Gateway: $gateway");
    
    return true;
}
/* ---------- HELPER FUNCTIONS ---------- */


function op_render_recent_invoices($business_id, $limit = 5) {
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $invoices = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $invoices_table ORDER BY created_at DESC LIMIT %d",
        $business_id, $limit
    ));

    if (empty($invoices)) {
        return '<p>No recent invoices.</p>';
    }

    ob_start();
    ?>
    
        <div class="bntm-table-wrapper">
          <table class="bntm-table">
              <thead>
                  <tr>
                      <th>Invoice</th>
                      <th>Customer</th>
                      <th>Amount</th>
                      <th>Status</th>
                      <th>Date</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($invoices as $invoice): ?>
                      <tr>
                          <td>#<?php echo esc_html($invoice->rand_id); ?></td>
                          <td><?php echo esc_html($invoice->customer_name); ?></td>
                          <td><?php echo op_format_price($invoice->total); ?></td>
                          <td>
                              <span class="status-badge status-<?php echo esc_attr($invoice->payment_status); ?>">
                                  <?php echo ucfirst(str_replace('_', ' ', $invoice->payment_status)); ?>
                              </span>
                          </td>
                          <td><?php echo date('M d, Y', strtotime($invoice->created_at)); ?></td>
                      </tr>
                  <?php endforeach; ?>
              </tbody>
          </table>
       </div>
    <?php
    return ob_get_clean();
}

function op_format_price($amount = '') {
    $currency = bntm_get_setting('op_currency', 'USD');
    
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'PHP' => '₱'
    ];
    
    $symbol = isset($symbols[$currency]) ? $symbols[$currency] :'' ;
    
    return $symbol . number_format($amount, 2);
}

function op_get_pos_receivables_summary() {
    if (function_exists('pos_get_customer_receivables_summary')) {
        return pos_get_customer_receivables_summary();
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pos_transactions';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

    if (!$exists) {
        return [];
    }

    return $wpdb->get_results(
        "SELECT customer_id,
                COALESCE(NULLIF(customer_name, ''), 'Walk-in Customer') AS customer_name,
                MAX(customer_email) AS customer_email,
                MAX(customer_contact) AS customer_contact,
                COUNT(*) AS total_transactions,
                COALESCE(SUM(payable_amount), 0) AS total_payables,
                MAX(created_at) AS last_transaction_at
         FROM {$table}
         WHERE payment_status IN ('unpaid', 'partial')
         GROUP BY customer_id, customer_name
         ORDER BY total_payables DESC, last_transaction_at DESC"
    );
}

function op_get_pos_customer_statement_data($customer_id, $month = '') {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $customer_id = (int) $customer_id;

    if ($customer_id <= 0) {
        return null;
    }

    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$customers_table} WHERE id = %d", $customer_id));
    if (!$customer) {
        return null;
    }

    $rows = function_exists('pos_get_customer_statement_rows')
        ? pos_get_customer_statement_rows($customer_id, $month)
        : [];

    $summary = [
        'month' => preg_match('/^\d{4}-\d{2}$/', $month) ? $month : current_time('Y-m'),
        'total_sales' => 0,
        'total_paid' => 0,
        'total_payables' => 0,
        'transactions' => count($rows),
    ];

    foreach ($rows as $row) {
        $summary['total_sales'] += (float) $row->total;
        $summary['total_paid'] += (float) $row->paid_amount;
        $summary['total_payables'] += (float) $row->payable_amount;
    }

    return [
        'customer' => $customer,
        'rows' => $rows,
        'summary' => $summary,
    ];
}

function op_get_pos_payable_transactions($customer_id = 0, $month = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'pos_transactions';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));

    if (!$exists) {
        return [];
    }

    $where = ["payment_status IN ('unpaid', 'partial')"];
    $params = [];
    $customer_id = (int) $customer_id;

    if ($customer_id > 0) {
        $where[] = "customer_id = %d";
        $params[] = $customer_id;
    }

    if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
        $where[] = "DATE_FORMAT(created_at, '%Y-%m') = %s";
        $params[] = $month;
    }

    $sql = "SELECT id, transaction_number, created_at, customer_id, customer_name, customer_email, customer_contact, total, payment_type, payment_method, payment_status, payable_amount, paid_amount
            FROM {$table}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC";

    return empty($params)
        ? $wpdb->get_results($sql)
        : $wpdb->get_results($wpdb->prepare($sql, ...$params));
}

function op_render_pos_customer_statement($customer_id, $month = '', $back_url = '') {
    $data = op_get_pos_customer_statement_data($customer_id, $month);

    if (!$data) {
        return '<div class="bntm-notice bntm-notice-error">Customer statement not found.</div>';
    }

    $customer = $data['customer'];
    $rows = $data['rows'];
    $summary = $data['summary'];
    $month_label = date_i18n('F Y', strtotime($summary['month'] . '-01'));
    $back_url = $back_url ?: add_query_arg(['tab' => 'receivables'], remove_query_arg(['customer_id', 'month']));
    $statement_link = op_get_customer_statement_url([
        'customer_id' => (int) $customer_id,
        'month' => $summary['month']
    ]);
    $payment_methods = op_get_active_payment_methods((int) ($customer->business_id ?? 0));

    ob_start();
    ?>
    <div class="bntm-statement-container" style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px;">
        <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px;">
            <div>
                <h3 style="margin:0 0 8px 0;">Customer Statement</h3>
                <div style="color:#6b7280;"><?php echo esc_html($customer->name); ?></div>
            </div>
            <a href="<?php echo esc_url($back_url); ?>" class="bntm-btn-secondary">Back to Customers</a>
        </div>

        <div style="margin-bottom:20px; padding:16px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px;">
            <div style="font-size:12px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:.08em; margin-bottom:8px;">Statement Link</div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <input type="text" value="<?php echo esc_attr($statement_link); ?>" readonly onclick="this.select();" style="flex:1; min-width:260px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; background:#fff;">
                <button type="button" class="op-copy-statement-link bntm-btn-secondary" data-link="<?php echo esc_attr($statement_link); ?>">Copy Link</button>
            </div>
            <div style="margin-top:8px; font-size:13px; color:#6b7280;">Share this customer statement link directly with the customer.</div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:20px;">
            <div style="padding:14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px;">
                <div style="font-size:12px; color:#1d4ed8; font-weight:600;">Month</div>
                <div style="font-size:20px; font-weight:700;"><?php echo esc_html($month_label); ?></div>
            </div>
            <div style="padding:14px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px;">
                <div style="font-size:12px; color:#047857; font-weight:600;">Total Sales</div>
                <div style="font-size:20px; font-weight:700;"><?php echo esc_html(op_format_price($summary['total_sales'])); ?></div>
            </div>
            <div style="padding:14px; background:#fef3c7; border:1px solid #fde68a; border-radius:10px;">
                <div style="font-size:12px; color:#b45309; font-weight:600;">Total Paid</div>
                <div style="font-size:20px; font-weight:700;"><?php echo esc_html(op_format_price($summary['total_paid'])); ?></div>
            </div>
            <div style="padding:14px; background:#fee2e2; border:1px solid #fecaca; border-radius:10px;">
                <div style="font-size:12px; color:#b91c1c; font-weight:600;">Outstanding</div>
                <div style="font-size:20px; font-weight:700;"><?php echo esc_html(op_format_price($summary['total_payables'])); ?></div>
            </div>
        </div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction #</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="6" style="text-align:center;">No transactions found for this month.</td></tr>
                    <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo esc_html(date('M d, Y h:i A', strtotime($row->created_at))); ?></td>
                        <td>#<?php echo esc_html($row->transaction_number); ?></td>
                        <td><?php echo esc_html(op_format_price($row->total)); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_type)) . ' / ' . ucfirst(str_replace('_', ' ', $row->payment_method))); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_status))); ?></td>
                        <td><?php echo esc_html(op_format_price($row->payable_amount)); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
            <div style="padding:16px 18px; background:#f8fafc; border-bottom:1px solid #e5e7eb; font-weight:700; color:#0f172a;">Payment Methods</div>
            <div style="padding:18px;">
                <?php if (empty($payment_methods)): ?>
                <div style="color:#6b7280;">No payment methods configured yet.</div>
                <?php else: foreach ($payment_methods as $method): ?>
                <?php $config = json_decode($method->config ?? '{}', true); if (!is_array($config)) { $config = []; } ?>
                <div style="padding:14px 0; border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
                        <strong><?php echo esc_html($method->name); ?></strong>
                        <span style="font-size:12px; font-weight:700; text-transform:uppercase; color:#475569;"><?php echo esc_html($method->gateway); ?></span>
                    </div>
                    <?php if (!empty($config['instructions'])): ?>
                    <div style="color:#475569; margin-bottom:6px;"><?php echo nl2br(esc_html($config['instructions'])); ?></div>
                    <?php endif; ?>
                    <?php if ($method->gateway === 'manual'): ?>
                    <?php if (!empty($config['bank_name'])): ?>
                    <div><strong>Bank:</strong> <?php echo esc_html($config['bank_name']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($config['account_name'])): ?>
                    <div><strong>Account Name:</strong> <?php echo esc_html($config['account_name']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($config['account_number'])): ?>
                    <div><strong>Account Number:</strong> <?php echo esc_html($config['account_number']); ?></div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <script>
    jQuery(function($) {
        $('.op-copy-statement-link').on('click', function() {
            const link = $(this).data('link') || '';
            if (!link) {
                return;
            }

            navigator.clipboard.writeText(link).then(function() {
                alert('Statement link copied.');
            }).catch(function() {
                alert('Unable to copy the statement link.');
            });
        });
    });
    </script>
    <?php

    return ob_get_clean();
}

function op_customer_payables_tab($business_id) {
    $selected_month = sanitize_text_field($_GET['month'] ?? current_time('Y-m'));
    $receivables = op_get_pos_receivables_summary();
    $statement_email_nonce = wp_create_nonce('op_statement_email');
    $total_outstanding = 0;

    foreach ($receivables as $row) {
        $total_outstanding += (float) $row->total_payables;
    }

    ob_start();
    ?>
    <style>
    .op-table-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }

    .op-table-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 118px;
        padding: 8px 12px;
        border: 1px solid transparent;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.2;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }

    .op-table-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.12);
    }

    .op-table-action-btn:disabled {
        opacity: 0.7;
        cursor: wait;
        transform: none;
        box-shadow: none;
    }

    .op-table-action-btn-primary {
        background: #2563eb;
        color: #fff;
    }

    .op-table-action-btn-success {
        background: #059669;
        color: #fff;
    }

    .op-table-action-btn-neutral {
        background: #334155;
        color: #fff;
    }

    @media (max-width: 768px) {
        .op-table-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .op-table-action-btn {
            width: 100%;
            min-width: 0;
        }
    }
    </style>
    <div class="bntm-form-section">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
            <div>
                <h3 style="margin:0;">Customer Payables</h3>
                <p style="margin:6px 0 0; color:#6b7280;">CRM customers with open payables from POS sales.</p>
            </div>
            <div style="font-weight:700; color:#b91c1c;">Total Outstanding: <?php echo esc_html(op_format_price($total_outstanding)); ?></div>
        </div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Open Transactions</th>
                        <th>Total Payables</th>
                        <th>Last Sale</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receivables)): ?>
                    <tr><td colspan="6" style="text-align:center;">No outstanding customer payables found.</td></tr>
                    <?php else: foreach ($receivables as $row): ?>
                    <tr>
                        <td><strong><?php echo esc_html($row->customer_name); ?></strong></td>
                        <td>
                            <?php echo esc_html($row->customer_email ?: '-'); ?>
                            <?php if (!empty($row->customer_contact)): ?>
                            <div style="font-size:12px; color:#6b7280;"><?php echo esc_html($row->customer_contact); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo intval($row->total_transactions); ?></td>
                        <td><strong style="color:#b91c1c;"><?php echo esc_html(op_format_price($row->total_payables)); ?></strong></td>
                        <td><?php echo esc_html(date('M d, Y h:i A', strtotime($row->last_transaction_at))); ?></td>
                        <td>
                            <?php if (!empty($row->customer_id)): ?>
                            <div class="op-table-actions">
                                <a class="op-table-action-btn op-table-action-btn-primary" href="<?php echo esc_url(op_get_customer_statement_url(['customer_id' => intval($row->customer_id), 'month' => $selected_month])); ?>">View Statement</a>
                                <button type="button" class="op-table-action-btn op-table-action-btn-neutral op-copy-statement-link-btn" data-link="<?php echo esc_attr(op_get_customer_statement_url(['customer_id' => intval($row->customer_id), 'month' => $selected_month])); ?>">Copy Link</button>
                                <button type="button" class="op-table-action-btn op-table-action-btn-success op-send-statement-btn" data-customer-id="<?php echo esc_attr(intval($row->customer_id)); ?>" data-customer-name="<?php echo esc_attr($row->customer_name); ?>" data-customer-email="<?php echo esc_attr($row->customer_email); ?>">Send Email</button>
                                <a class="op-table-action-btn op-table-action-btn-neutral" href="<?php echo esc_url(op_get_payable_transactions_url(['customer_id' => intval($row->customer_id), 'month' => $selected_month])); ?>">View Payables</a>
                            </div>
                            <?php else: ?>
                            <span style="color:#9ca3af;">No linked CRM record</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    jQuery(function($) {
        const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

        $('.op-copy-statement-link-btn').on('click', function() {
            const link = $(this).data('link') || '';

            if (!link) {
                alert('Statement link not available.');
                return;
            }

            navigator.clipboard.writeText(link).then(function() {
                alert('Statement link copied.');
            }).catch(function() {
                alert('Unable to copy statement link.');
            });
        });

        $('.op-send-statement-btn').on('click', function() {
            const button = $(this);
            const customerId = parseInt(button.data('customer-id'), 10) || 0;
            const customerName = button.data('customer-name') || 'Customer';
            const customerEmail = button.data('customer-email') || '';

            if (!customerId) {
                alert('Customer record not found.');
                return;
            }

            if (!customerEmail) {
                alert('This customer does not have an email address on file.');
                return;
            }

            if (!confirm('Send statement email to ' + customerName + '?')) {
                return;
            }

            const originalText = button.text();
            button.prop('disabled', true).text('Sending...');

            $.post(ajaxUrl, {
                action: 'op_send_statement_email',
                nonce: '<?php echo esc_js($statement_email_nonce); ?>',
                customer_id: customerId,
                month: '<?php echo esc_js($selected_month); ?>'
            }).done(function(response) {
                alert(response && response.data && response.data.message ? response.data.message : 'Statement email sent.');
            }).fail(function() {
                alert('Failed to send statement email.');
            }).always(function() {
                button.prop('disabled', false).text(originalText);
            });
        });
    });
    </script>
    <?php

    return ob_get_clean();
}

function op_payable_transactions_tab($business_id) {
    $selected_customer_id = intval($_GET['customer_id'] ?? 0);
    $selected_month = sanitize_text_field($_GET['month'] ?? '');
    $transactions = op_get_pos_payable_transactions($selected_customer_id, $selected_month);

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap; margin-bottom:18px;">
            <div>
                <h3 style="margin:0;">Transaction of All Payables</h3>
                <p style="margin:6px 0 0; color:#6b7280;">All POS transactions that still have unpaid or partial customer balances.</p>
            </div>
            <form method="get" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="tab" value="payable-transactions">
                <?php if ($selected_customer_id > 0): ?>
                <input type="hidden" name="customer_id" value="<?php echo esc_attr($selected_customer_id); ?>">
                <?php endif; ?>
                <div>
                    <label style="display:block; margin-bottom:6px;">Month</label>
                    <input type="month" name="month" value="<?php echo esc_attr($selected_month); ?>">
                </div>
                <button type="submit" class="bntm-btn-primary">Filter</button>
            </form>
        </div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Payment Type</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr><td colspan="8" style="text-align:center;">No payable transactions found.</td></tr>
                    <?php else: foreach ($transactions as $row): ?>
                    <tr>
                        <td><?php echo esc_html(date('M d, Y h:i A', strtotime($row->created_at))); ?></td>
                        <td>#<?php echo esc_html($row->transaction_number); ?></td>
                        <td>
                            <strong><?php echo esc_html($row->customer_name ?: 'Walk-in Customer'); ?></strong>
                            <?php if (!empty($row->customer_email)): ?>
                            <div style="font-size:12px; color:#6b7280;"><?php echo esc_html($row->customer_email); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(op_format_price($row->total)); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_type))); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_method))); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_status))); ?></td>
                        <td><strong style="color:#b91c1c;"><?php echo esc_html(op_format_price($row->payable_amount)); ?></strong></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

function bntm_op_shortcode_payable_transactions() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to view transactions.</div>';
    }

    return op_payable_transactions_tab(get_current_user_id());
}

function op_get_statement_email_settings($business_id) {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'op_email_settings';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$settings_table} WHERE business_id = %d LIMIT 1",
        $business_id
    ));
}

function op_get_customer_receivable_record($customer_id, $business_id = 0) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $transactions_table = $wpdb->prefix . 'pos_transactions';

    $customer_id = (int) $customer_id;
    $business_id = (int) $business_id;

    if ($customer_id <= 0) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT c.id AS customer_id,
                c.business_id,
                c.name AS customer_name,
                c.email AS customer_email,
                c.contact_number AS customer_contact,
                COUNT(p.id) AS total_transactions,
                COALESCE(SUM(p.payable_amount), 0) AS total_payables,
                MAX(p.created_at) AS last_transaction_at
         FROM {$customers_table} c
         LEFT JOIN {$transactions_table} p
           ON p.customer_id = c.id
          AND p.payment_status IN ('unpaid', 'partial')
         WHERE c.id = %d
           AND (%d = 0 OR c.business_id = %d)
         GROUP BY c.id, c.business_id, c.name, c.email, c.contact_number
         LIMIT 1",
        $customer_id,
        $business_id,
        $business_id
    ));
}

function op_get_customers_for_auto_send($business_id) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $transactions_table = $wpdb->prefix . 'pos_transactions';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT c.id AS customer_id,
                c.name AS customer_name,
                c.email AS customer_email,
                c.contact_number AS customer_contact,
                COUNT(p.id) AS total_transactions,
                COALESCE(SUM(p.payable_amount), 0) AS total_payables,
                MAX(p.created_at) AS last_transaction_at
         FROM {$customers_table} c
         INNER JOIN {$transactions_table} p
           ON p.customer_id = c.id
          AND p.payment_status IN ('unpaid', 'partial')
         WHERE c.business_id = %d
           AND c.email IS NOT NULL
           AND c.email != ''
         GROUP BY c.id, c.name, c.email, c.contact_number
         ORDER BY c.name ASC",
        $business_id
    ));
}

function op_build_customer_statement_email($customer_id, $month = '', $business_id = 0) {
    $record = op_get_customer_receivable_record($customer_id, $business_id);

    if (!$record) {
        return new WP_Error('not_found', 'Customer not found.');
    }

    if (empty($record->customer_email)) {
        return new WP_Error('missing_email', 'Customer has no email address on file.');
    }

    $data = op_get_pos_customer_statement_data($customer_id, $month);
    if (!$data) {
        return new WP_Error('statement_missing', 'Customer statement not found.');
    }

    $month_label = $month !== ''
        ? date_i18n('F Y', strtotime($data['summary']['month'] . '-01'))
        : 'All Outstanding Payables';
    $rows = $data['rows'];
    $summary = $data['summary'];

    $subject = 'Statement of Account - ' . $month_label;
    $statement_link = op_get_customer_statement_url([
        'customer_id' => (int) $customer_id,
        'month' => $summary['month']
    ]);
    $body = '<h2>Statement of Account</h2>';
    $body .= '<p>Dear ' . esc_html($record->customer_name) . ',</p>';
    $body .= '<p>Here is your customer payable statement for <strong>' . esc_html($month_label) . '</strong>.</p>';
    $body .= '<p><strong>Total Outstanding:</strong> ' . esc_html(op_format_price($summary['total_payables'])) . '</p>';
    $body .= '<p><strong>Statement Link:</strong> <a href="' . esc_url($statement_link) . '">' . esc_html($statement_link) . '</a></p>';
    $body .= '<table style="width:100%; border-collapse:collapse;">';
    $body .= '<tr style="background:#f3f4f6;">';
    $body .= '<th style="padding:10px; border:1px solid #d1d5db; text-align:left;">Date</th>';
    $body .= '<th style="padding:10px; border:1px solid #d1d5db; text-align:left;">Transaction #</th>';
    $body .= '<th style="padding:10px; border:1px solid #d1d5db; text-align:right;">Total</th>';
    $body .= '<th style="padding:10px; border:1px solid #d1d5db; text-align:left;">Payment</th>';
    $body .= '<th style="padding:10px; border:1px solid #d1d5db; text-align:left;">Status</th>';
    $body .= '<th style="padding:10px; border:1px solid #d1d5db; text-align:right;">Outstanding</th>';
    $body .= '</tr>';

    if (empty($rows)) {
        $body .= '<tr><td colspan="6" style="padding:12px; border:1px solid #d1d5db; text-align:center;">No payable transactions for this period.</td></tr>';
    } else {
        foreach ($rows as $row) {
            $body .= '<tr>';
            $body .= '<td style="padding:10px; border:1px solid #d1d5db;">' . esc_html(date('M d, Y h:i A', strtotime($row->created_at))) . '</td>';
            $body .= '<td style="padding:10px; border:1px solid #d1d5db;">#' . esc_html($row->transaction_number) . '</td>';
            $body .= '<td style="padding:10px; border:1px solid #d1d5db; text-align:right;">' . esc_html(op_format_price($row->total)) . '</td>';
            $body .= '<td style="padding:10px; border:1px solid #d1d5db;">' . esc_html(ucfirst(str_replace('_', ' ', $row->payment_type)) . ' / ' . ucfirst(str_replace('_', ' ', $row->payment_method))) . '</td>';
            $body .= '<td style="padding:10px; border:1px solid #d1d5db;">' . esc_html(ucfirst(str_replace('_', ' ', $row->payment_status))) . '</td>';
            $body .= '<td style="padding:10px; border:1px solid #d1d5db; text-align:right;">' . esc_html(op_format_price($row->payable_amount)) . '</td>';
            $body .= '</tr>';
        }
    }

    $body .= '</table>';
    $body .= '<p style="margin-top:20px;">Please settle your outstanding balance at your earliest convenience.</p>';

    return [
        'customer' => $record,
        'subject' => $subject,
        'body' => $body,
        'summary' => $summary,
    ];
}

function op_send_customer_statement_email($customer_id, $month = '', $business_id = 0) {
    $business_id = (int) $business_id;
    $email_data = op_build_customer_statement_email($customer_id, $month, $business_id);

    if (is_wp_error($email_data)) {
        return $email_data;
    }

    $settings = op_get_statement_email_settings($business_id);
    $sender_name = $settings->sender_name ?? get_option('blogname', 'Business');
    $sender_email = $settings->sender_email ?? get_option('admin_email');

    if (empty($sender_email)) {
        return new WP_Error('missing_sender', 'Email sender not configured. Please check Payment Settings.');
    }

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $sender_name . ' <' . $sender_email . '>'
    ];

    $sent = wp_mail($email_data['customer']->customer_email, $email_data['subject'], $email_data['body'], $headers);

    if (!$sent) {
        return new WP_Error('send_failed', 'Failed to send email. Please check server mail configuration.');
    }

    return [
        'message' => 'Statement email sent to ' . $email_data['customer']->customer_email,
        'customer' => $email_data['customer'],
    ];
}

function op_get_payable_transactions_url($args = []) {
    $transactions_page = get_page_by_path('transactions');

    if ($transactions_page) {
        return add_query_arg($args, get_page_link($transactions_page));
    }

    return add_query_arg(array_merge(['tab' => 'payable-transactions'], $args));
}

function op_get_customer_statement_url($args = []) {
    $statement_page = get_page_by_path('customer-statement');

    if (!empty($args['customer_id'])) {
        $args['token'] = op_get_customer_statement_token((int) $args['customer_id']);
    }

    if ($statement_page) {
        return add_query_arg($args, get_page_link($statement_page));
    }

    return add_query_arg(array_merge(['tab' => 'receivables'], $args));
}

function op_get_customer_statement_token($customer_id) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $customer_id = (int) $customer_id;

    if ($customer_id <= 0) {
        return '';
    }

    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT id, business_id, email FROM {$customers_table} WHERE id = %d LIMIT 1",
        $customer_id
    ));

    if (!$customer) {
        return '';
    }

    return hash_hmac('sha256', $customer->id . '|' . $customer->business_id . '|' . strtolower((string) $customer->email), wp_salt('auth'));
}

function op_is_valid_customer_statement_token($customer_id, $token) {
    $expected = op_get_customer_statement_token($customer_id);

    if ($expected === '' || $token === '') {
        return false;
    }

    return hash_equals($expected, (string) $token);
}

function op_get_active_payment_methods($business_id) {
    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$methods_table}
         WHERE business_id = %d AND is_active = 1
         ORDER BY priority ASC, id ASC",
        (int) $business_id
    ));
}

/* ---------- RECEIVABLES SHORTCODE ---------- */
function bntm_op_shortcode_receivables() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to view receivables.</div>';
    }

    return op_customer_payables_tab(get_current_user_id());
}

/* ---------- CUSTOMER STATEMENT SHORTCODE ---------- */
function bntm_op_shortcode_customer_statement() {
    $customer_id = intval($_GET['customer_id'] ?? 0);
    $selected_month = sanitize_text_field($_GET['month'] ?? current_time('Y-m'));
    $token = sanitize_text_field($_GET['token'] ?? '');

    if ($customer_id <= 0) {
        return '<div class="bntm-notice">No customer selected.</div>';
    }

    if (!is_user_logged_in() && !op_is_valid_customer_statement_token($customer_id, $token)) {
        return '<div class="bntm-notice">Please log in to view statements.</div>';
    }

    return op_render_pos_customer_statement($customer_id, $selected_month, wp_get_referer());

    $user_id = get_current_user_id();
    $business_id = (int) get_option('bntm_primary_business_id', 0) ?: $user_id;
    $customer_name = isset($_GET['customer_name']) ? sanitize_text_field($_GET['customer_name']) : '';
    $selected_month = isset($_GET['month']) ? sanitize_text_field($_GET['month']) : date('Y-m');
    
    if (!$customer_name) {
        return '<div class="bntm-notice">No customer selected.</div>';
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    // Get all invoices for this customer
    $all_invoices = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$invoices_table}
        WHERE business_id = %d AND customer_name = %s
        ORDER BY created_at DESC
    ", $business_id, $customer_name));
    
    // Get invoices for selected month
    $month_invoices = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$invoices_table}
        WHERE business_id = %d AND customer_name = %s AND DATE_FORMAT(created_at, '%Y-%m') = %s
        ORDER BY created_at DESC
    ", $business_id, $customer_name, $selected_month));
    
    // Calculate totals
    $total_paid = 0;
    $total_unpaid = 0;
    foreach ($all_invoices as $invoice) {
        if ($invoice->payment_status === 'paid') {
            $total_paid += $invoice->total;
        } else {
            $total_unpaid += $invoice->total;
        }
    }
    
    ob_start();
    ?>
    <div class="bntm-statement-container" style="max-width: 900px; margin: 20px auto; padding: 20px;">
        <div style="margin-bottom: 30px; padding: 20px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <h2 style="margin: 0 0 10px 0;">Statement of Account</h2>
                    <p style="margin: 0; color: #6b7280;">Customer: <strong><?php echo esc_html($customer_name); ?></strong></p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0; font-size: 12px; color: #9ca3af;">Generated: <?php echo date('F d, Y H:i'); ?></p>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 30px;">
            <div style="padding: 15px; background: #dcfce7; border: 1px solid #86efac; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: #16a34a; font-weight: 600;">Total Paid</p>
                <p style="margin: 0; font-size: 24px; font-weight: 700; color: #059669;"><?php echo bntm_format_currency($total_paid, 'PHP'); ?></p>
            </div>
            <div style="padding: 15px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: #991b1b; font-weight: 600;">Outstanding Balance</p>
                <p style="margin: 0; font-size: 24px; font-weight: 700; color: #dc2626;"><?php echo bntm_format_currency($total_unpaid, 'PHP'); ?></p>
            </div>
            <div style="padding: 15px; background: #dbeafe; border: 1px solid #bfdbfe; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 12px; color: #1e40af; font-weight: 600;">Total Invoices</p>
                <p style="margin: 0; font-size: 24px; font-weight: 700; color: #2563eb;"><?php echo count($all_invoices); ?></p>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 10px; font-weight: 600;">Filter by Month:</label>
            <input type="month" value="<?php echo esc_attr($selected_month); ?>" onchange="window.location = window.location.pathname + '?customer_name=<?php echo urlencode($customer_name); ?>&month=' + this.value" style="padding: 10px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 16px;">
        </div>
        
        <table class="bntm-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f3f4f6; border-bottom: 2px solid #e5e7eb;">
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Invoice #</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Date</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600;">Amount</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Status</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($month_invoices): ?>
                    <?php foreach ($month_invoices as $invoice): ?>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 12px;"><strong><?php echo esc_html($invoice->reference_number ?: '#INV-' . $invoice->id); ?></strong></td>
                            <td style="padding: 12px;"><?php echo date('M d, Y H:i', strtotime($invoice->created_at)); ?></td>
                            <td style="padding: 12px; text-align: right;"><strong><?php echo bntm_format_currency($invoice->total, 'PHP'); ?></strong></td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; <?php echo $invoice->payment_status === 'paid' ? 'background: #dcfce7; color: #16a34a;' : 'background: #fee2e2; color: #991b1b;'; ?>">
                                    <?php echo ucfirst($invoice->payment_status); ?>
                                </span>
                            </td>
                            <td style="padding: 12px; text-align: right;">
                                <?php if ($invoice->payment_status === 'unpaid'): ?>
                                    <strong style="color: #dc2626;"><?php echo bntm_format_currency($invoice->total, 'PHP'); ?></strong>
                                <?php else: ?>
                                    <span style="color: #6b7280;">0.00</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center; color: #9ca3af;">No invoices for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 30px;">
            <a href="<?php echo wp_get_referer(); ?>" class="bntm-btn-secondary" style="padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">← Back to Receivables</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ---------- PAYMENT SETTINGS SHORTCODE ---------- */
function bntm_op_shortcode_settings() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access settings.</div>';
    }

    $user_id = get_current_user_id();
    $business_id = (int) get_option('bntm_primary_business_id', 0) ?: $user_id;
    
    global $wpdb;
    $settings_table = $wpdb->prefix . 'op_email_settings';
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['op_email_save'])) {
        check_admin_referer('op_email_settings', 'op_nonce');
        
        // Get or create settings
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM {$settings_table}
            WHERE business_id = %d
            LIMIT 1
        ", $business_id));
        
        $sender_name = sanitize_text_field($_POST['sender_name'] ?? 'Payment System');
        $sender_email = sanitize_email($_POST['sender_email'] ?? '');
        $enable_auto_send = isset($_POST['enable_auto_send']) ? 1 : 0;
        $auto_send_frequency = sanitize_text_field($_POST['auto_send_frequency'] ?? 'weekly');
        $auto_send_day_of_week = intval($_POST['auto_send_day_of_week'] ?? 1);
        $auto_send_day_of_month = intval($_POST['auto_send_day_of_month'] ?? 1);
        $auto_send_day_value = $auto_send_frequency === 'monthly'
            ? max(1, min(28, $auto_send_day_of_month))
            : max(0, min(6, $auto_send_day_of_week));
        $auto_send_time = sanitize_text_field($_POST['auto_send_time'] ?? '08:00:00');
        
        if (empty($sender_email)) {
            $sender_email = get_option('admin_email');
        }
        
        $data = [
            'sender_name' => $sender_name,
            'sender_email' => $sender_email,
            'enable_statement_email' => isset($_POST['enable_statement_email']) ? 1 : 0,
            'enable_auto_send' => $enable_auto_send,
            'auto_send_frequency' => $auto_send_frequency,
            'auto_send_day_of_week' => $auto_send_day_value,
            'auto_send_time' => $auto_send_time,
            'updated_at' => current_time('mysql')
        ];
        
        if ($existing) {
            $wpdb->update($settings_table, $data, ['id' => $existing->id]);
        } else {
            $data['business_id'] = $business_id;
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($settings_table, $data);
        }
        
        // Schedule next auto-send if enabled
        if ($enable_auto_send) {
            bntm_schedule_auto_send($business_id, $auto_send_frequency, $auto_send_day_value, $auto_send_time);
        }
        
        echo '<div class="bntm-notice" style="background: #dcfce7; border: 1px solid #86efac; color: #16a34a; padding: 12px; border-radius: 6px; margin-bottom: 20px;">Settings saved successfully!</div>';
    }
    
    // Get current settings
    $settings = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$settings_table}
        WHERE business_id = %d
        LIMIT 1
    ", $business_id));
    
    $sender_name = $settings->sender_name ?? 'Payment System';
    $sender_email = $settings->sender_email ?? get_option('admin_email');
    $enable_statement_email = $settings->enable_statement_email ?? 1;
    $enable_auto_send = $settings->enable_auto_send ?? 0;
    $auto_send_frequency = $settings->auto_send_frequency ?? 'weekly';
    $auto_send_day_of_week = $settings->auto_send_day_of_week ?? 1;
    $auto_send_day_of_month = max(1, min(28, intval($settings->auto_send_day_of_week ?? 1)));
    $auto_send_time = $settings->auto_send_time ?? '08:00:00';
    $next_auto_send = $settings->next_auto_send ?? 'Not scheduled';
    
    ob_start();
    ?>
    <div class="bntm-settings-container" style="max-width: 700px; margin: 20px auto; padding: 20px;">
        <h2>Payment Email Settings</h2>
        
        <form method="POST" style="background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
            <?php wp_nonce_field('op_email_settings', 'op_nonce'); ?>
            
            <!-- MANUAL EMAIL SECTION -->
            <div style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
                <h3 style="margin-top: 0;">Manual Email Settings</h3>
                
                <div class="bntm-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email Sender Name</label>
                    <input type="text" name="sender_name" value="<?php echo esc_attr($sender_name); ?>" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;" placeholder="Your Company Name">
                    <small style="color: #6b7280; display: block; margin-top: 5px;">This name will appear in the email "From" field</small>
                </div>
                
                <div class="bntm-form-group" style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email Address</label>
                    <input type="email" name="sender_email" value="<?php echo esc_attr($sender_email); ?>" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;" required>
                    <small style="color: #6b7280; display: block; margin-top: 5px;">The email address used to send statements and notifications</small>
                </div>
                
                <div class="bntm-form-group" style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" name="enable_statement_email" value="1" <?php checked($enable_statement_email, 1); ?> style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 600;">Enable Manual Statement Email</span>
                    </label>
                    <small style="color: #6b7280; display: block; margin-top: 5px;">Allow sending statement emails manually from the Receivables section</small>
                </div>
            </div>
            
            <!-- AUTOMATED EMAIL SECTION -->
            <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;">
                <h3 style="margin-top: 0;">Automated Email Scheduling</h3>
                
                <div class="bntm-form-group" style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="enable_auto_send" name="enable_auto_send" value="1" <?php checked($enable_auto_send, 1); ?> style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 600;">Enable Automatic Customer Statement Emails</span>
                    </label>
                    <small style="color: #6b7280; display: block; margin-top: 5px;">Automatically send statements to customers with outstanding balances</small>
                </div>
                
                <div id="auto_send_options" style="<?php echo $enable_auto_send ? '' : 'display: none;'; ?> background: #f0fdf4; padding: 15px; border-radius: 6px; border: 1px solid #86efac;">
                    <div class="bntm-form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Send Frequency</label>
                        <select name="auto_send_frequency" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                            <option value="daily" <?php selected($auto_send_frequency, 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($auto_send_frequency, 'weekly'); ?>>Weekly</option>
                            <option value="monthly" <?php selected($auto_send_frequency, 'monthly'); ?>>Monthly</option>
                        </select>
                    </div>
                    
                    <div id="weekly_options" style="<?php echo $auto_send_frequency === 'weekly' ? '' : 'display: none;'; ?>" class="bntm-form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Day of Week</label>
                        <select name="auto_send_day_of_week" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                            <option value="0" <?php selected($auto_send_day_of_week, 0); ?>>Sunday</option>
                            <option value="1" <?php selected($auto_send_day_of_week, 1); ?>>Monday</option>
                            <option value="2" <?php selected($auto_send_day_of_week, 2); ?>>Tuesday</option>
                            <option value="3" <?php selected($auto_send_day_of_week, 3); ?>>Wednesday</option>
                            <option value="4" <?php selected($auto_send_day_of_week, 4); ?>>Thursday</option>
                            <option value="5" <?php selected($auto_send_day_of_week, 5); ?>>Friday</option>
                            <option value="6" <?php selected($auto_send_day_of_week, 6); ?>>Saturday</option>
                        </select>
                    </div>

                    <div id="monthly_options" style="<?php echo $auto_send_frequency === 'monthly' ? '' : 'display: none;'; ?>" class="bntm-form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Day of Month</label>
                        <input type="number" name="auto_send_day_of_month" min="1" max="28" value="<?php echo esc_attr($auto_send_day_of_month); ?>" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                        <small style="color: #6b7280; display: block; margin-top: 5px;">Choose 1 to 28 so the schedule works every month.</small>
                    </div>
                    
                    <div class="bntm-form-group" style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px;">Send Time</label>
                        <input type="time" name="auto_send_time" value="<?php echo esc_attr($auto_send_time); ?>" style="width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px;">
                        <small style="color: #6b7280; display: block; margin-top: 5px;">Emails will be sent at this time (server timezone)</small>
                    </div>
                    
                    <div style="background: white; padding: 12px; border-radius: 6px; margin-top: 15px;">
                        <p style="margin: 0; color: #059669; font-weight: 600;">Next scheduled send:</p>
                        <p style="margin: 5px 0 0 0; font-size: 14px; color: #111827;"><?php echo $next_auto_send instanceof DateTime ? $next_auto_send->format('M d, Y @ h:i A') : $next_auto_send; ?></p>
                    </div>
                </div>
                
                <div style="background: #fef3c7; border: 1px solid #fde047; padding: 12px; border-radius: 6px; margin-top: 15px;">
                    <p style="margin: 0; color: #92400e; font-weight: 600;">ℹ️ How it works</p>
                    <ul style="margin: 5px 0 0 0; padding-left: 20px; color: #b45309; font-size: 13px;">
                        <li>Each scheduled run sends to all customers with open payables</li>
                        <li>Each customer receives one email regardless of invoice count</li>
                        <li>Emails contain the customer's payable transactions and statement summary</li>
                        <li>Requires WordPress to run (schedule needs WP-Cron or similar)</li>
                    </ul>
                </div>
            </div>
            
            <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p style="margin: 0; color: #16a34a; font-weight: 600;">✓ Email Features</p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #059669; font-size: 14px;">
                    <li>Statements sent as HTML formatted emails</li>
                    <li>Includes all outstanding payable transactions</li>
                    <li>Manual send from Receivables section anytime</li>
                    <li>Automatic scheduled sends (when enabled)</li>
                </ul>
            </div>
            
            <button type="submit" name="op_email_save" class="bntm-btn-primary" style="background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; width: 100%; font-size: 16px;">
                Save Settings
            </button>
        </form>
        
        <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border: 1px solid #fde047; border-radius: 6px;">
            <p style="margin: 0; color: #92400e; font-weight: 600;">ℹ️ Note</p>
            <p style="margin: 5px 0 0 0; color: #b45309; font-size: 14px;">WordPress uses your server's mail configuration to send emails. Ensure your email settings are properly configured.</p>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        function syncAutoSendOptions() {
            const frequency = $('select[name="auto_send_frequency"]').val();
            $('#weekly_options').toggle(frequency === 'weekly');
            $('#monthly_options').toggle(frequency === 'monthly');
        }

        $('#enable_auto_send').on('change', function() {
            $('#auto_send_options').toggle(this.checked);
        });
        
        $('select[name="auto_send_frequency"]').on('change', function() {
            syncAutoSendOptions();
        });

        syncAutoSendOptions();
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX: SEND STATEMENT EMAIL ---------- */
function bntm_ajax_op_send_statement_email() {
    check_ajax_referer('op_statement_email', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $month = sanitize_text_field($_POST['month'] ?? current_time('Y-m'));
    $user_id = get_current_user_id();
    $business_id = (int) get_option('bntm_primary_business_id', 0) ?: $user_id;

    if ($customer_id > 0) {
        $result = op_send_customer_statement_email($customer_id, $month, $business_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => $result['message']]);
    }
    
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    
    if (!$customer_name) {
        wp_send_json_error(['message' => 'Invalid customer name']);
    }
    
    global $wpdb;
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $settings_table = $wpdb->prefix . 'op_email_settings';
    
    // Get customer info and invoices
    $customer_invoices = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$invoices_table}
        WHERE business_id = %d AND customer_name = %s AND payment_status = 'unpaid'
        ORDER BY created_at DESC
    ", $business_id, $customer_name));
    
    if (empty($customer_invoices)) {
        wp_send_json_error(['message' => 'No unpaid invoices found for this customer']);
    }
    
    $customer_email = $customer_invoices[0]->customer_email;
    
    if (empty($customer_email)) {
        wp_send_json_error(['message' => 'Customer email not found']);
    }
    
    // Get email settings
    $settings = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$settings_table}
        WHERE business_id = %d
        LIMIT 1
    ", $business_id));
    
    $sender_name = $settings->sender_name ?? get_option('blogname', 'Business');
    $sender_email = $settings->sender_email ?? get_option('admin_email');
    
    if (empty($sender_email)) {
        wp_send_json_error(['message' => 'Email sender not configured. Please check Payment Settings.']);
    }
    
    $total_payable = 0;
    foreach ($customer_invoices as $invoice) {
        $total_payable += $invoice->total;
    }
    
    // Format currency helper
    $currency_symbol = '₱';
    
    // Build email content
    $email_subject = 'Your Statement of Account - ' . $customer_name;
    $email_body = "<h2>Statement of Account</h2>";
    $email_body .= "<p>Dear " . esc_html($customer_name) . ",</p>";
    $email_body .= "<p>Please find below your outstanding account balances:</p>";
    $email_body .= "<h3>Outstanding Balance: " . $currency_symbol . number_format($total_payable, 2) . "</h3>";
    $email_body .= "<table style='width: 100%; border-collapse: collapse;'>";
    $email_body .= "<tr style='background: #f0f0f0;'><th style='padding: 10px; border: 1px solid #ddd;'>Invoice #</th><th style='padding: 10px; border: 1px solid #ddd;'>Date</th><th style='padding: 10px; border: 1px solid #ddd;'>Amount</th><th style='padding: 10px; border: 1px solid #ddd;'>Balance</th></tr>";
    
    foreach ($customer_invoices as $invoice) {
        $email_body .= "<tr>";
        $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>" . esc_html($invoice->reference_number ?: '#INV-' . $invoice->id) . "</td>";
        $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>" . date('M d, Y', strtotime($invoice->created_at)) . "</td>";
        $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>" . $currency_symbol . number_format((float)$invoice->total, 2) . "</td>";
        $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>" . $currency_symbol . number_format((float)$invoice->total, 2) . "</td>";
        $email_body .= "</tr>";
    }
    
    $email_body .= "</table>";
    $email_body .= "<p style='margin-top: 20px;'>Please settle your outstanding balance at your earliest convenience.</p>";
    $email_body .= "<p>Thank you for your business!</p>";
    
    // Set headers for HTML email with proper From address
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $sender_name . ' <' . $sender_email . '>'
    );
    
    // Send email with error logging
    $sent = wp_mail($customer_email, $email_subject, $email_body, $headers);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Email sent successfully to ' . $customer_email]);
    } else {
        error_log("BNTM Payment Email Failed - Customer: $customer_name, Email: $customer_email, Sender: $sender_email");
        wp_send_json_error(['message' => 'Failed to send email. Please check server mail configuration or contact administrator.']);
    }
}
add_action('wp_ajax_op_send_statement_email', 'bntm_ajax_op_send_statement_email');
add_action('wp_ajax_nopriv_op_send_statement_email', 'bntm_ajax_op_send_statement_email');

/* ---------- AUTOMATED EMAIL SCHEDULING ---------- */
function bntm_schedule_auto_send($business_id, $frequency = 'weekly', $day_of_week = 1, $time = '08:00:00') {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'op_email_settings';
    
    $next_send = bntm_calculate_next_send_time($frequency, $day_of_week, $time);
    
    $wpdb->update(
        $settings_table,
        ['next_auto_send' => $next_send->format('Y-m-d H:i:s')],
        ['business_id' => $business_id],
        ['%s'],
        ['%d']
    );
    
    // Schedule WP-Cron event if not already scheduled
    if (!wp_next_scheduled('bntm_send_auto_statements')) {
        wp_schedule_event(time(), 'hourly', 'bntm_send_auto_statements');
    }
}

function bntm_calculate_next_send_time($frequency, $day_of_week = 1, $time = '08:00:00') {
    $time_parts = explode(':', $time);
    $hour = intval($time_parts[0] ?? 8);
    $minute = intval($time_parts[1] ?? 0);
    $second = intval($time_parts[2] ?? 0);
    
    $now = new DateTime('now', wp_timezone_get());
    $next = new DateTime('now', wp_timezone_get());
    $next->setTime($hour, $minute, $second);
    
    if ($frequency === 'daily') {
        if ($next <= $now) {
            $next->modify('+1 day');
        }
    } elseif ($frequency === 'weekly') {
        $current_day = intval($now->format('w'));
        $days_ahead = $day_of_week - $current_day;
        
        if ($days_ahead <= 0) {
            $days_ahead += 7;
        }
        
        $next->modify("+$days_ahead days");
        
        if ($days_ahead === 0 && $next <= $now) {
            $next->modify('+7 days');
        }
    } elseif ($frequency === 'monthly') {
        $target_day = max(1, min(28, intval($day_of_week)));
        $next->setDate((int) $now->format('Y'), (int) $now->format('n'), $target_day);

        if ($next <= $now) {
            $next->modify('first day of next month');
            $next->setDate((int) $next->format('Y'), (int) $next->format('n'), $target_day);
        }
    }
    
    return $next;
}

function bntm_send_auto_statements() {
    global $wpdb;
    $settings_table = $wpdb->prefix . 'op_email_settings';
    $invoices_table = $wpdb->prefix . 'op_invoices';

    $now = current_time('mysql');
    $settings_list = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$settings_table}
        WHERE enable_auto_send = 1 AND next_auto_send <= %s
        ORDER BY next_auto_send ASC",
        $now
    ));

    if (empty($settings_list)) {
        return;
    }

    foreach ($settings_list as $settings) {
        $sender_email = $settings->sender_email ?? get_option('admin_email');

        if (empty($sender_email)) {
            error_log("BNTM Auto-Send: Skipping business {$settings->business_id} - no sender email configured");
            bntm_reschedule_auto_send($settings);
            continue;
        }

        $sent_count = 0;
        $failed_count = 0;
        $customers = op_get_customers_for_auto_send((int) $settings->business_id);

        foreach ($customers as $customer) {
            $result = op_send_customer_statement_email((int) $customer->customer_id, '', (int) $settings->business_id);

            if (is_wp_error($result)) {
                $failed_count++;
                error_log("BNTM Auto-Send Failed: Customer {$customer->customer_name} ({$customer->customer_email})");
            } else {
                $sent_count++;
            }
        }

        error_log("BNTM Auto-Send completed for business {$settings->business_id}: $sent_count sent, $failed_count failed");
        bntm_reschedule_auto_send($settings);
    }

    return;
    
    // Get all enabled auto-send settings ready to run
    $now = current_time('mysql');
    $settings_list = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$settings_table}
        WHERE enable_auto_send = 1 AND next_auto_send <= %s
        ORDER BY next_auto_send ASC",
        $now
    ));
    
    if (empty($settings_list)) {
        return;
    }
    
    foreach ($settings_list as $settings) {
        // Get all customers with unpaid invoices
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT customer_name, customer_email
            FROM {$invoices_table}
            WHERE business_id = %d AND payment_status = 'unpaid' AND customer_email IS NOT NULL AND customer_email != ''
            ORDER BY customer_name",
            $settings->business_id
        ));
        
        $sender_name = $settings->sender_name ?? get_option('blogname', 'Business');
        $sender_email = $settings->sender_email ?? get_option('admin_email');
        
        if (empty($sender_email)) {
            error_log("BNTM Auto-Send: Skipping business {$settings->business_id} - no sender email configured");
            bntm_reschedule_auto_send($settings);
            continue;
        }
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($customers as $customer) {
            // Get unpaid invoices for this customer
            $invoices = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$invoices_table}
                WHERE business_id = %d AND customer_name = %s AND payment_status = 'unpaid'
                ORDER BY created_at DESC",
                $settings->business_id,
                $customer->customer_name
            ));
            
            if (empty($invoices)) {
                continue;
            }
            
            // Calculate total payable
            $total_payable = array_sum(array_column($invoices, 'total'));
            
            // Build email
            $email_subject = 'Your Statement of Account - ' . $customer->customer_name;
            $email_body = "<h2>Statement of Account</h2>";
            $email_body .= "<p>Dear " . esc_html($customer->customer_name) . ",</p>";
            $email_body .= "<p>Please find below your outstanding account balances:</p>";
            $email_body .= "<h3>Outstanding Balance: ₱" . number_format($total_payable, 2) . "</h3>";
            $email_body .= "<table style='width: 100%; border-collapse: collapse;'>";
            $email_body .= "<tr style='background: #f0f0f0;'><th style='padding: 10px; border: 1px solid #ddd;'>Invoice #</th><th style='padding: 10px; border: 1px solid #ddd;'>Date</th><th style='padding: 10px; border: 1px solid #ddd;'>Amount</th></tr>";
            
            foreach ($invoices as $invoice) {
                $email_body .= "<tr>";
                $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>" . esc_html($invoice->reference_number ?: '#INV-' . $invoice->id) . "</td>";
                $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>" . date('M d, Y', strtotime($invoice->created_at)) . "</td>";
                $email_body .= "<td style='padding: 10px; border: 1px solid #ddd;'>₱" . number_format($invoice->total, 2) . "</td>";
                $email_body .= "</tr>";
            }
            
            $email_body .= "</table>";
            $email_body .= "<p style='margin-top: 20px;'>Please settle your outstanding balance at your earliest convenience.</p>";
            $email_body .= "<p>Thank you for your business!</p>";
            
            // Send email
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $sender_name . ' <' . $sender_email . '>'
            );
            
            if (wp_mail($customer->customer_email, $email_subject, $email_body, $headers)) {
                $sent_count++;
            } else {
                $failed_count++;
                error_log("BNTM Auto-Send Failed: Customer {$customer->customer_name} ({$customer->customer_email})");
            }
        }
        
        // Log the send
        error_log("BNTM Auto-Send completed for business {$settings->business_id}: $sent_count sent, $failed_count failed");
        
        // Reschedule next send
        bntm_reschedule_auto_send($settings);
    }
}

function bntm_reschedule_auto_send($settings) {
    $next_send = bntm_calculate_next_send_time(
        $settings->auto_send_frequency ?? 'weekly',
        $settings->auto_send_day_of_week ?? 1,
        $settings->auto_send_time ?? '08:00:00'
    );
    
    global $wpdb;
    $settings_table = $wpdb->prefix . 'op_email_settings';
    
    $wpdb->update(
        $settings_table,
        [
            'last_auto_send' => current_time('mysql'),
            'next_auto_send' => $next_send->format('Y-m-d H:i:s')
        ],
        ['id' => $settings->id],
        ['%s', '%s'],
        ['%d']
    );
}

add_action('bntm_send_auto_statements', 'bntm_send_auto_statements');

?>
