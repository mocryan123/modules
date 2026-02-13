<?php
/**
 * Module Name: Finance Management
 * Module Slug: fn
 * Description: Complete finance management with income/expense tracking, e-commerce integration, and financial reporting
 * Version: 1.0.1
 * Author: BNTM Hub
 * Icon: 💰
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_FN_PATH', dirname(__FILE__) . '/');
define('BNTM_FN_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_fn_get_pages() {
    return [
        'Finance' => '[fn_page]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_fn_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'fn_transactions' => "CREATE TABLE {$prefix}fn_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(10) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            category VARCHAR(100),
            notes TEXT,
            reference_type VARCHAR(50) NULL,
            reference_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (type),
            INDEX idx_category (category),
            INDEX idx_date (created_at),
            INDEX idx_reference (reference_type, reference_id)
        ) {$charset};",
        
        'fn_cashflow_summary' => "CREATE TABLE {$prefix}fn_cashflow_summary (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            period VARCHAR(20) NOT NULL,
            total_income DECIMAL(10,2) DEFAULT 0,
            total_expense DECIMAL(10,2) DEFAULT 0,
            balance DECIMAL(10,2) DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business_period (business_id, period)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_fn_get_shortcodes() {
    return [
        'fn_page' => 'bntm_shortcode_fn_page',
        'fn_dashboard' => 'bntm_shortcode_fn_page'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_fn_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_fn_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Set default categories if not exists
    if (!bntm_get_setting('fn_categories_income')) {
        bntm_set_setting('fn_categories_income', json_encode([
            'Sales',
            'Services',
            'Investment',
            'Other Income'
        ]));
    }
    
    if (!bntm_get_setting('fn_categories_expense')) {
        bntm_set_setting('fn_categories_expense', json_encode([
            'Rent',
            'Utilities',
            'Payroll',
            'Supplies',
            'Marketing',
            'Transportation',
            'Maintenance',
            'Other Expense'
        ]));
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_bntm_fn_save_transaction', 'bntm_ajax_fn_save_transaction');
add_action('wp_ajax_bntm_fn_delete_transaction', 'bntm_ajax_fn_delete_transaction');
add_action('wp_ajax_bntm_fn_import_order', 'bntm_ajax_fn_import_order');
add_action('wp_ajax_bntm_fn_revert_order', 'bntm_ajax_fn_revert_order');
add_action('wp_ajax_bntm_fn_save_categories', 'bntm_ajax_fn_save_categories');
add_action('wp_ajax_bntm_fn_export_csv', 'bntm_ajax_fn_export_csv');

// Create Finance tables on module activation
function bntm_fn_create_tables2() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $prefix = $wpdb->prefix;
    
    $tables = [
        'fn_transactions' => "CREATE TABLE {$prefix}fn_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type VARCHAR(10) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            category VARCHAR(100),
            notes TEXT,
            reference_type VARCHAR(50) NULL,
            reference_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (type),
            INDEX idx_category (category),
            INDEX idx_date (created_at),
            INDEX idx_reference (reference_type, reference_id)
        ) {$wpdb->get_charset_collate()};",
        
        'fn_cashflow_summary' => "CREATE TABLE {$prefix}fn_cashflow_summary (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            period VARCHAR(20) NOT NULL,
            total_income DECIMAL(10,2) DEFAULT 0,
            total_expense DECIMAL(10,2) DEFAULT 0,
            balance DECIMAL(10,2) DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business_period (business_id, period)
        ) {$wpdb->get_charset_collate()};"
    ];
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Set default categories
    if (!bntm_get_setting('fn_categories_income')) {
        bntm_set_setting('fn_categories_income', json_encode(['Sales', 'Services', 'Investment', 'Other Income']));
    }
    if (!bntm_get_setting('fn_categories_expense')) {
        bntm_set_setting('fn_categories_expense', json_encode(['Rent', 'Utilities', 'Payroll', 'Supplies', 'Marketing', 'Other Expense']));
    }
}

// Generate Finance pages
add_action('wp_ajax_bntm_fn_generate_pages', 'bntm_ajax_fn_generate_pages');
function bntm_ajax_fn_generate_pages() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error('Unauthorized.');
    }
    
    // Create Finance page if doesn't exist
    $finance_page = get_page_by_title('Finance');
    if (!$finance_page) {
        $page_id = wp_insert_post([
            'post_title' => 'Finance',
            'post_content' => '[fn_page]',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id()
        ]);
        
        if (is_wp_error($page_id)) {
            wp_send_json_error('Failed to create Finance page.');
        }
    }
    
    
    // Create tables
    bntm_fn_create_tables2();
    
    wp_send_json_success('Finance pages and tables created successfully!');
}

/* ---------- MAIN SHORTCODE ---------- */function bntm_shortcode_fn_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice bntm-notice-error">Please log in to access Finance.</div>';
    }
    
    $stats = bntm_fn_get_dashboard_stats();
    $currency = get_option('bntm_currency_symbol', '$');
    
    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <div class="bntm-fn-dashboard">
        <div class="bntm-fn-stats-grid">
            <div class="bntm-fn-stat-card">
                <div class="bntm-fn-stat-icon bntm-fn-stat-icon-income">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <polyline points="19 12 12 19 5 12"></polyline>
                    </svg>
                </div>
                <div class="bntm-fn-stat-content">
                    <h3>Total Income</h3>
                    <p class="bntm-fn-stat-number bntm-fn-stat-income"><?php echo $currency; ?><?php echo number_format($stats['total_income'], 2); ?></p>
                </div>
            </div>
            
            <div class="bntm-fn-stat-card">
                <div class="bntm-fn-stat-icon bntm-fn-stat-icon-expense">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="19" x2="12" y2="5"></line>
                        <polyline points="5 12 12 5 19 12"></polyline>
                    </svg>
                </div>
                <div class="bntm-fn-stat-content">
                    <h3>Total Expenses</h3>
                    <p class="bntm-fn-stat-number bntm-fn-stat-expense"><?php echo $currency; ?><?php echo number_format($stats['total_expense'], 2); ?></p>
                </div>
            </div>
            
            <div class="bntm-fn-stat-card">
                <div class="bntm-fn-stat-icon bntm-fn-stat-icon-balance">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                    </svg>
                </div>
                <div class="bntm-fn-stat-content">
                    <h3>Net Balance</h3>
                    <p class="bntm-fn-stat-number <?php echo $stats['balance'] >= 0 ? 'bntm-fn-stat-income' : 'bntm-fn-stat-expense'; ?>"><?php echo $currency; ?><?php echo number_format($stats['balance'], 2); ?></p>
                </div>
            </div>
            
            <div class="bntm-fn-stat-card">
                <div class="bntm-fn-stat-icon bntm-fn-stat-icon-primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
                <div class="bntm-fn-stat-content">
                    <h3>This Month</h3>
                    <p class="bntm-fn-stat-number"><?php echo $currency; ?><?php echo number_format($stats['month_balance'], 2); ?></p>
                </div>
            </div>
        </div>
        
        <div class="bntm-fn-charts-grid">
            <div class="bntm-fn-chart-card bntm-fn-chart-large">
                <h3>Cash Flow Overview</h3>
                <canvas id="cashFlowChart"></canvas>
            </div>
            
            <div class="bntm-fn-chart-card">
                <h3>Income by Category</h3>
                <canvas id="incomeCategoryChart"></canvas>
            </div>
            
            <div class="bntm-fn-chart-card">
                <h3>Expenses by Category</h3>
                <canvas id="expenseCategoryChart"></canvas>
            </div>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="<?php echo add_query_arg('type', 'transactions', get_permalink()); ?>" class="bntm-btn-primary">View All Transactions</a>
        </div>
    </div>
    
    <style>
    .bntm-fn-dashboard {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .bntm-fn-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .bntm-fn-stat-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
    }
    
    .bntm-fn-stat-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    
    .bntm-fn-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .bntm-fn-stat-icon-income {
        background: #10b981;
        color: #ffffff;
    }
    
    .bntm-fn-stat-icon-expense {
        background: #ef4444;
        color: #ffffff;
    }
    
    .bntm-fn-stat-icon-balance {
        background: #3b82f6;
        color: #ffffff;
    }
    
    .bntm-fn-stat-icon-primary {
        background: var(--bntm-primary, #374151);
        color: #ffffff;
    }
    
    .bntm-fn-stat-content {
        flex: 1;
    }
    
    .bntm-fn-stat-content h3 {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #6b7280;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .bntm-fn-stat-number {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin: 0;
        line-height: 1;
    }
    
    .bntm-fn-stat-income {
        color: #059669;
    }
    
    .bntm-fn-stat-expense {
        color: #dc2626;
    }
    
    .bntm-fn-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .bntm-fn-chart-card {
        background: #ffffff;
        padding: 24px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    
    .bntm-fn-chart-large {
        grid-column: 1 / -1;
    }
    
    .bntm-fn-chart-card h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .bntm-fn-chart-card canvas {
        max-height: 300px;
    }
    
    @media (max-width: 768px) {
        .bntm-fn-chart-card {
            grid-column: 1 / -1;
        }
    }
    </style>
    
    <script>
    (function() {
        const primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--bntm-primary').trim() || '#374151';
        
        // Cash Flow Overview Chart (Line Chart)
        const cashFlowCtx = document.getElementById('cashFlowChart');
        if (cashFlowCtx) {
            new Chart(cashFlowCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($stats['monthly_data'], 'month')); ?>,
                    datasets: [
                        {
                            label: 'Income',
                            data: <?php echo json_encode(array_column($stats['monthly_data'], 'income')); ?>,
                            borderColor: '#10b981',
                            backgroundColor: '#10b98120',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        },
                        {
                            label: 'Expenses',
                            data: <?php echo json_encode(array_column($stats['monthly_data'], 'expense')); ?>,
                            borderColor: '#ef4444',
                            backgroundColor: '#ef444420',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#ef4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }
                    ]
                },
                options: {
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
                                    return context.dataset.label + ': <?php echo $currency; ?>' + context.parsed.y.toFixed(2);
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
        
        // Income Category Chart (Doughnut Chart)
        const incomeCategoryCtx = document.getElementById('incomeCategoryChart');
        if (incomeCategoryCtx) {
            new Chart(incomeCategoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($stats['income_by_category'], 'category')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['income_by_category'], 'total')); ?>,
                        backgroundColor: [
                            '#10b981',
                            '#059669',
                            '#047857',
                            '#065f46',
                            '#064e3b'
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
                                    return label + ': <?php echo $currency; ?>' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Expense Category Chart (Doughnut Chart)
        const expenseCategoryCtx = document.getElementById('expenseCategoryChart');
        if (expenseCategoryCtx) {
            new Chart(expenseCategoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($stats['expense_by_category'], 'category')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($stats['expense_by_category'], 'total')); ?>,
                        backgroundColor: [
                            '#ef4444',
                            '#dc2626',
                            '#b91c1c',
                            '#991b1b',
                            '#7f1d1d'
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
                                    return label + ': <?php echo $currency; ?>' + value.toFixed(2);
                                }
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

function bntm_fn_get_dashboard_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    
    // Basic stats
    $stats = $wpdb->get_row("
        SELECT 
            SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as total_expense,
            SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as balance
        FROM {$table}
    ");
    
    $month_stats = $wpdb->get_row("
        SELECT 
            SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as month_balance
        FROM {$table}
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    
    // Monthly data (last 6 months)
    $monthly_data = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(created_at, '%b %Y') as month,
            SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense
        FROM {$table}
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)
    ", ARRAY_A);
    
    // If no data, create empty months
    if (empty($monthly_data)) {
        $monthly_data = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthly_data[] = [
                'month' => date('M Y', strtotime("-$i months")),
                'income' => 0,
                'expense' => 0
            ];
        }
    }
    
    // Income by category
    $income_by_category = $wpdb->get_results("
        SELECT category, SUM(amount) as total
        FROM {$table}
        WHERE type = 'income'
        GROUP BY category
        ORDER BY total DESC
        LIMIT 5
    ", ARRAY_A);
    
    // Expense by category
    $expense_by_category = $wpdb->get_results("
        SELECT category, SUM(amount) as total
        FROM {$table}
        WHERE type = 'expense'
        GROUP BY category
        ORDER BY total DESC
        LIMIT 5
    ", ARRAY_A);
    
    return [
        'total_income' => $stats->total_income ?? 0,
        'total_expense' => $stats->total_expense ?? 0,
        'balance' => $stats->balance ?? 0,
        'month_balance' => $month_stats->month_balance ?? 0,
        'monthly_data' => $monthly_data,
        'income_by_category' => $income_by_category ?: [],
        'expense_by_category' => $expense_by_category ?: []
    ];
}

function bntm_shortcode_fn_page() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice bntm-notice-error">Please log in to access Finance.</div>';
    }
    
    $currency = bntm_get_setting('ec_currency', 'PHP');
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'dashboard';
    
    ob_start();
    ?>
    <div class="bntm-tabs">
        <a href="<?php echo add_query_arg('type', 'dashboard', get_permalink()); ?>" class="bntm-tab <?php echo $type === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
        <a href="<?php echo add_query_arg('type', 'transactions', get_permalink()); ?>" class="bntm-tab <?php echo $type === 'transactions' ? 'active' : ''; ?>">Transactions</a>
       
        <a href="<?php echo add_query_arg('type', 'reports', get_permalink()); ?>" class="bntm-tab <?php echo $type === 'reports' ? 'active' : ''; ?>">Reports</a>
        <a href="<?php echo add_query_arg('type', 'export', get_permalink()); ?>" class="bntm-tab <?php echo $type === 'export' ? 'active' : ''; ?>">Export</a>
        <a href="<?php echo add_query_arg('type', 'settings', get_permalink()); ?>" class="bntm-tab <?php echo $type === 'settings' ? 'active' : ''; ?>">Settings</a>
    </div>
    
    <div class="bntm-tab-content" style="margin-top: 20px;">
        <?php
        switch ($type) {
            case 'dashboard':
                echo bntm_shortcode_fn_dashboard();
            case 'transactions':
                echo bntm_fn_transactions_tab();
                break;
            
            case 'reports':
                echo bntm_fn_reports_tab();
                break;
            case 'export':
                echo bntm_fn_export_tab();
                break;
            case 'settings':
                echo bntm_fn_settings_tab();
                break;
        }
        ?>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Finance', $content);
}
/* ---------- TAB CONTENT ---------- */
function bntm_fn_transactions_tab() {
    $currency = bntm_get_setting('ec_currency', 'PHP');
    $income_cats = json_decode(bntm_get_setting('fn_categories_income', '[]'), true);
    $expense_cats = json_decode(bntm_get_setting('fn_categories_expense', '[]'), true);
    $nonce = wp_create_nonce('bntm_fn_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Add New Transaction</h3>
        <form id="bntm-fn-transaction-form" class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label for="fn_type">Type</label>
                    <select id="fn_type" name="type" required>
                        <option value="income">Income</option>
                        <option value="expense">Expense</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label for="fn_amount">Amount (<?php echo $currency ?>)</label>
                    <input type="number" id="fn_amount" name="amount" step="0.01" min="0" required>
                </div>
                
                <div class="bntm-form-group">
                    <label for="fn_category">Category</label>
                    <select id="fn_category" name="category" required>
                        <optgroup label="Income" id="income_opts">
                            <?php foreach ($income_cats as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expense" id="expense_opts" style="display:none;">
                            <?php foreach ($expense_cats as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label for="fn_notes">Notes</label>
                <textarea id="fn_notes" name="notes" rows="2"></textarea>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Add Transaction</button>
            <div id="fn-transaction-message"></div>
        </form>
    </div>
    
    <div class="bntm-form-section" style="margin-top: 30px;">
        <h3>Transaction History</h3>
        <?php echo bntm_fn_render_transaction_history(); ?>
    </div>
    
    <style>
    .bntm-type-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .bntm-type-income {
        background: #d1fae5;
        color: #065f46;
    }
    .bntm-type-expense {
        background: #fee2e2;
        color: #991b1b;
    }
    </style>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    // Toggle categories based on type
    document.getElementById('fn_type').addEventListener('change', function() {
        const incomeOpts = document.getElementById('income_opts');
        const expenseOpts = document.getElementById('expense_opts');
        
        if (this.value === 'income') {
            incomeOpts.style.display = '';
            expenseOpts.style.display = 'none';
            document.querySelector('#income_opts option').selected = true;
        } else {
            incomeOpts.style.display = 'none';
            expenseOpts.style.display = '';
            document.querySelector('#expense_opts option').selected = true;
        }
    });
    
    // Submit transaction
    document.getElementById('bntm-fn-transaction-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const msg = document.getElementById('fn-transaction-message');
        const btn = form.querySelector('button[type="submit"]');
        
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        const data = new FormData();
        data.append('action', 'bntm_fn_save_transaction');
        data.append('type', form.type.value);
        data.append('amount', form.amount.value);
        data.append('category', form.category.value);
        data.append('notes', form.notes.value);
        data.append('_ajax_nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: data})
        .then(r => r.json())
        .then(json => {
            msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data + '</div>';
            btn.disabled = false;
            btn.textContent = 'Add Transaction';
            
            if (json.success) {
                form.reset();
                setTimeout(() => location.reload(), 1000);
            }
        });
    });
    
    // Delete transaction
    document.querySelectorAll('.bntm-delete-txn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this transaction?')) return;
            
            const data = new FormData();
            data.append('action', 'bntm_fn_delete_transaction');
            data.append('id', this.dataset.id);
            data.append('_ajax_nonce', this.dataset.nonce);
            
            fetch(ajaxurl, {method: 'POST', body: data})
            .then(r => r.json())
            .then(json => {
                alert(json.data);
                if (json.success) location.reload();
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- TRANSACTION HISTORY WITH PAGINATION ---------- */
function bntm_fn_render_transaction_history($limit = 10) {
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $currency = bntm_get_setting('ec_currency', 'PHP');
    $nonce = wp_create_nonce('bntm_fn_action');
    
    // Get current page from URL parameter
    $current_page = isset($_GET['fn_page']) ? max(1, intval($_GET['fn_page'])) : 1;
    $offset = ($current_page - 1) * $limit;
    
    // Get total count for pagination
    $total_transactions = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table"
    );
    
    $total_pages = ceil($total_transactions / $limit);
    
    // Get transactions for current page
    $transactions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ));
    
    if (empty($transactions) && $current_page == 1) {
        return '<p>No transactions yet.</p>';
    }
    
    ob_start();
    ?>
    <table class="bntm-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($transactions)): ?>
                <?php foreach ($transactions as $txn): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($txn->created_at)); ?></td>
                    <td><span class="bntm-type-badge bntm-type-<?php echo $txn->type; ?>"><?php echo ucfirst($txn->type); ?></span></td>
                    <td><?php echo esc_html($txn->category); ?></td>
                    <td class="<?php echo $txn->type === 'income' ? 'bntm-stat-income' : 'bntm-stat-expense'; ?>"><?php echo $currency ?><?php echo number_format($txn->amount, 2); ?></td>
                    <td><?php echo esc_html($txn->notes); ?></td>
                    <td>
                        <?php if (!$txn->reference_type): ?>
                        <button class="bntm-btn-small bntm-btn-danger bntm-delete-txn" data-id="<?php echo $txn->id; ?>" data-nonce="<?php echo $nonce; ?>">Delete</button>
                        <?php else: ?>
                        <span style="color:#999;">Imported</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No transactions found on this page.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($total_pages > 1): ?>
        <div class="fn-pagination">
            <?php
            $base_url = remove_query_arg('fn_page');
            
            // Previous button
            if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg('fn_page', $current_page - 1, $base_url)); ?>" class="fn-page-btn">
                    &laquo; Previous
                </a>
            <?php endif; ?>
            
            <!-- Page numbers -->
            <div class="fn-page-numbers">
                <?php
                // Show first page
                if ($current_page > 3) {
                    echo '<a href="' . esc_url(add_query_arg('fn_page', 1, $base_url)) . '" class="fn-page-num">1</a>';
                    if ($current_page > 4) {
                        echo '<span class="fn-page-dots">...</span>';
                    }
                }
                
                // Show pages around current page
                for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) {
                    $active_class = ($i == $current_page) ? ' active' : '';
                    echo '<a href="' . esc_url(add_query_arg('fn_page', $i, $base_url)) . '" class="fn-page-num' . $active_class . '">' . $i . '</a>';
                }
                
                // Show last page
                if ($current_page < $total_pages - 2) {
                    if ($current_page < $total_pages - 3) {
                        echo '<span class="fn-page-dots">...</span>';
                    }
                    echo '<a href="' . esc_url(add_query_arg('fn_page', $total_pages, $base_url)) . '" class="fn-page-num">' . $total_pages . '</a>';
                }
                ?>
            </div>
            
            <!-- Next button -->
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg('fn_page', $current_page + 1, $base_url)); ?>" class="fn-page-btn">
                    Next &raquo;
                </a>
            <?php endif; ?>
            
            <div class="fn-page-info">
                Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                (<?php echo $total_transactions; ?> total transactions)
            </div>
        </div>
    <?php endif; ?>
    
    <style>
    .fn-pagination { display: flex; align-items: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    .fn-page-numbers { display: flex; gap: 5px; }
    .fn-page-btn, .fn-page-num { padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333; }
    .fn-page-btn:hover, .fn-page-num:hover { background: var(--bntm-primary-hover); color: white; }
    .fn-page-num.active { background: var(--bntm-primary); color: white; border-color: var(--bntm-primary); }
    .fn-page-dots { padding: 6px; color: #999; }
    .fn-page-info { margin-left: auto; color: #666; font-size: 14px; }
    </style>
    <?php
    return ob_get_clean();
}
function bntm_fn_reports_tab() {
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
    
    $currency = bntm_get_setting('ec_currency', 'PHP');
    $monthly_data = bntm_fn_get_monthly_report($year, $month);
    $category_breakdown = bntm_fn_get_category_breakdown($year, $month);
    $nonce = wp_create_nonce('bntm_fn_action');
    
    // Calculate start and end dates for the selected month
    $start_date = date('Y-m-01', strtotime("$year-$month-01"));
    $end_date = date('Y-m-t', strtotime("$year-$month-01"));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="text-align: center; display: flex ; justify-content: space-between;">
           <h3>Financial Reports</h3>
            <button class="bntm-btn-primary" id="generate-pdf-report" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>" data-nonce="<?php echo $nonce; ?>">
                Generate PDF Financial Statement
            </button>
        </div>
        <div class="bntm-form-row" style="margin-bottom: 20px;">
            <div class="bntm-form-group">
                <label for="report_month">Month</label>
                <select id="report_month" onchange="updateReport()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="bntm-form-group">
                <label for="report_year">Year</label>
                <select id="report_year" onchange="updateReport()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        
        <div class="bntm-stats-grid">
            <div class="bntm-stat-card">
                <div class="bntm-stat-label">Total Income</div>
                <div class="bntm-stat-value bntm-stat-income"><?php echo $currency ?><?php echo number_format($monthly_data['income'], 2); ?></div>
            </div>
            <div class="bntm-stat-card">
                <div class="bntm-stat-label">Total Expenses</div>
                <div class="bntm-stat-value bntm-stat-expense"><?php echo $currency ?><?php echo number_format($monthly_data['expense'], 2); ?></div>
            </div>
            <div class="bntm-stat-card">
                <div class="bntm-stat-label">Net Profit/Loss</div>
                <div class="bntm-stat-value <?php echo $monthly_data['balance'] >= 0 ? 'bntm-stat-income' : 'bntm-stat-expense'; ?>"><?php echo $currency ?><?php echo number_format($monthly_data['balance'], 2); ?></div>
            </div>
        </div>
        
        
        
        <h4 style="margin-top: 30px;">Income Statement - <?php echo date('F Y', strtotime("$year-$month-01")); ?></h4>
        
        <div class="bntm-financial-statement">
            <table class="bntm-table bntm-financial-table">
                <thead>
                    <tr>
                        <th style="text-align: left; width: 70%;">Particulars</th>
                        <th style="text-align: right; width: 30%;">Amount (<?php echo $currency ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- REVENUE SECTION -->
                    <tr class="bntm-section-header">
                        <td colspan="2"><strong>REVENUE</strong></td>
                    </tr>
                    <?php 
                    $income_items = array_filter($category_breakdown, function($item) {
                        return $item->type === 'income';
                    });
                    
                    if (empty($income_items)): ?>
                    <tr>
                        <td style="padding-left: 20px;">No revenue recorded</td>
                        <td style="text-align: right;">0.00</td>
                    </tr>
                    <?php else:
                        foreach ($income_items as $item): ?>
                    <tr>
                        <td style="padding-left: 20px;"><?php echo esc_html($item->category); ?></td>
                        <td style="text-align: right;"><?php echo number_format($item->total, 2); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    
                    <tr class="bntm-subtotal">
                        <td style="padding-left: 20px;"><strong>Total Revenue</strong></td>
                        <td style="text-align: right;"><strong><?php echo number_format($monthly_data['income'], 2); ?></strong></td>
                    </tr>
                    
                    <!-- EXPENSES SECTION -->
                    <tr class="bntm-section-header">
                        <td colspan="2"><strong>EXPENSES</strong></td>
                    </tr>
                    <?php 
                    $expense_items = array_filter($category_breakdown, function($item) {
                        return $item->type === 'expense';
                    });
                    
                    if (empty($expense_items)): ?>
                    <tr>
                        <td style="padding-left: 20px;">No expenses recorded</td>
                        <td style="text-align: right;">0.00</td>
                    </tr>
                    <?php else:
                        foreach ($expense_items as $item): ?>
                    <tr>
                        <td style="padding-left: 20px;"><?php echo esc_html($item->category); ?></td>
                        <td style="text-align: right;"><?php echo number_format($item->total, 2); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    
                    <tr class="bntm-subtotal">
                        <td style="padding-left: 20px;"><strong>Total Expenses</strong></td>
                        <td style="text-align: right;"><strong><?php echo number_format($monthly_data['expense'], 2); ?></strong></td>
                    </tr>
                    
                    <!-- NET INCOME -->
                    <tr class="bntm-total">
                        <td><strong>NET INCOME (LOSS)</strong></td>
                        <td style="text-align: right; <?php echo $monthly_data['balance'] >= 0 ? 'color: #059669;' : 'color: #dc2626;'; ?>">
                            <strong><?php echo $monthly_data['balance'] >= 0 ? '' : '('; ?><?php echo $currency ?><?php echo number_format(abs($monthly_data['balance']), 2); ?><?php echo $monthly_data['balance'] >= 0 ? '' : ')'; ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <style>
    .bntm-financial-statement {
        margin-top: 20px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .bntm-financial-table {
        margin: 0;
    }
    
    .bntm-financial-table thead th {
        background: #f9fafb;
        padding: 16px 12px;
        font-weight: 700;
        border-bottom: 2px solid #d1d5db;
    }
    
    .bntm-financial-table tbody td {
        padding: 10px 12px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .bntm-section-header td {
        background: #f9fafb;
        padding: 12px !important;
        font-weight: 700;
        border-top: 2px solid #d1d5db;
        border-bottom: 1px solid #d1d5db;
    }
    
    .bntm-subtotal td {
        padding: 12px !important;
        border-top: 1px solid #d1d5db;
        background: #fefefe;
    }
    
    .bntm-total td {
        padding: 16px 12px !important;
        border-top: 3px double #1f2937;
        background: #f9fafb;
        font-size: 16px;
    }
    </style>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    function updateReport() {
        const month = document.getElementById('report_month').value;
        const year = document.getElementById('report_year').value;
        window.location.href = '?type=reports&month=' + month + '&year=' + year;
    }
    
    // Generate PDF Report
    document.getElementById('generate-pdf-report').addEventListener('click', function() {
        const btn = this;
        const month = btn.dataset.month;
        const year = btn.dataset.year;
        const nonce = btn.dataset.nonce;
        
        btn.disabled = true;
        btn.textContent = '⏳ Generating PDF...';
        
        const params = new URLSearchParams({
            action: 'bntm_fn_generate_pdf',
            month: month,
            year: year,
            _ajax_nonce: nonce
        });
        
        // Open PDF in new tab
        window.open(ajaxurl + '?' + params.toString(), '_blank');
        
        setTimeout(() => {
            btn.disabled = false;
            btn.textContent = 'Generate PDF Financial Statement';
        }, 2000);
    });
    </script>
    <?php
    return ob_get_clean();
}

// Add AJAX handler for PDF generation
add_action('wp_ajax_bntm_fn_generate_pdf', 'bntm_ajax_fn_generate_pdf');

function bntm_ajax_fn_generate_pdf() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_die('Unauthorized access');
    }
    $currency = bntm_get_setting('ec_currency', 'PHP');
    $year = intval($_GET['year']);
    $month = intval($_GET['month']);
    
    $monthly_data = bntm_fn_get_monthly_report($year, $month);
    $category_breakdown = bntm_fn_get_category_breakdown($year, $month);
    
    // Get company info
    $company_name = bntm_get_setting('site_title', get_bloginfo('name'));
    $admin_email = bntm_get_setting('admin_email', get_option('admin_email'));
    
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $period = "$month_name $year";
    
    // Generate HTML for PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                margin: 2cm;
            }
            body {
                font-family: 'Arial', sans-serif;
                font-size: 11pt;
                color: #000;
                line-height: 1.4;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 15px;
            }
            .header h1 {
                margin: 0 0 5px 0;
                font-size: 18pt;
                font-weight: bold;
            }
            .header h2 {
                margin: 0 0 5px 0;
                font-size: 14pt;
                font-weight: normal;
            }
            .header p {
                margin: 3px 0;
                font-size: 10pt;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th {
                background: #f0f0f0;
                padding: 10px;
                text-align: left;
                border-bottom: 2px solid #000;
                font-weight: bold;
            }
            th.amount {
                text-align: right;
            }
            td {
                padding: 8px 10px;
                border-bottom: 1px solid #ddd;
            }
            td.amount {
                text-align: right;
                font-family: 'Courier New', monospace;
            }
            .indent {
                padding-left: 30px;
            }
            .section-header {
                background: #f5f5f5;
                font-weight: bold;
                border-top: 2px solid #000;
                border-bottom: 1px solid #000;
            }
            .subtotal {
                background: #fafafa;
                font-weight: bold;
                border-top: 1px solid #000;
            }
            .total {
                background: #f0f0f0;
                font-weight: bold;
                font-size: 12pt;
                border-top: 3px double #000;
                border-bottom: 3px double #000;
            }
            .footer {
                margin-top: 50px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .signature-section {
                margin-top: 60px;
                display: table;
                width: 100%;
            }
            .signature-box {
                display: table-cell;
                width: 50%;
                text-align: center;
                padding: 0 20px;
            }
            .signature-line {
                border-top: 1px solid #000;
                margin-top: 50px;
                padding-top: 5px;
            }
            .notes {
                margin-top: 30px;
                font-size: 9pt;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo esc_html($company_name); ?></h1>
            <h2>INCOME STATEMENT</h2>
            <p>For the Month of <?php echo esc_html($period); ?></p>
            <?php 
                if (($currency ==='PHP')): ?>
                <p style="font-size: 9pt; color: #666;">
                   Prepared in accordance with Philippine Financial Reporting Standards (PFRS)<br>
                   All amounts are in Philippine Pesos (<?php echo $currency ?>)
               </p>
                <?php  endif; ?>
            
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 70%;">Particulars</th>
                    <th class="amount" style="width: 30%;">Amount (<?php echo $currency ?>)</th>
                </tr>
            </thead>
            <tbody>
                <!-- REVENUE SECTION -->
                <tr class="section-header">
                    <td colspan="2">REVENUE</td>
                </tr>
                
                <?php 
                $income_items = array_filter($category_breakdown, function($item) {
                    return $item->type === 'income';
                });
                
                if (empty($income_items)): ?>
                <tr>
                    <td class="indent">No revenue recorded</td>
                    <td class="amount">-</td>
                </tr>
                <?php else:
                    foreach ($income_items as $item): ?>
                <tr>
                    <td class="indent"><?php echo esc_html($item->category); ?></td>
                    <td class="amount"><?php echo number_format($item->total, 2); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                
                <tr class="subtotal">
                    <td class="indent">Total Revenue</td>
                    <td class="amount"><?php echo number_format($monthly_data['income'], 2); ?></td>
                </tr>
                
                <!-- EXPENSES SECTION -->
                <tr class="section-header">
                    <td colspan="2">EXPENSES</td>
                </tr>
                
                <?php 
                $expense_items = array_filter($category_breakdown, function($item) {
                    return $item->type === 'expense';
                });
                
                if (empty($expense_items)): ?>
                <tr>
                    <td class="indent">No expenses recorded</td>
                    <td class="amount">-</td>
                </tr>
                <?php else:
                    foreach ($expense_items as $item): ?>
                <tr>
                    <td class="indent"><?php echo esc_html($item->category); ?></td>
                    <td class="amount"><?php echo number_format($item->total, 2); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                
                <tr class="subtotal">
                    <td class="indent">Total Expenses</td>
                    <td class="amount"><?php echo number_format($monthly_data['expense'], 2); ?></td>
                </tr>
                
                <!-- NET INCOME -->
                <tr class="total">
                    <td>NET INCOME <?php echo $monthly_data['balance'] < 0 ? '(LOSS)' : ''; ?></td>
                    <td class="amount" style="<?php echo $monthly_data['balance'] >= 0 ? 'color: #000;' : 'color: #000;'; ?>">
                        <?php echo $monthly_data['balance'] >= 0 ? '' : '('; ?><?php echo number_format(abs($monthly_data['balance']), 2); ?><?php echo $monthly_data['balance'] >= 0 ? '' : ')'; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">
                    Prepared by
                </div>
            </div>
            <div class="signature-box">
                <div class="signature-line">
                    Approved by
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p style="font-size: 9pt; margin: 5px 0;"><strong>Report Details:</strong></p>
            <p style="font-size: 9pt; margin: 3px 0;">Generated: <?php echo date('F d, Y h:i A'); ?></p>
            <p style="font-size: 9pt; margin: 3px 0;">Contact: <?php echo esc_html($admin_email); ?></p>
        </div>
        
        <div class="notes">
            <p><strong>Notes:</strong></p>
            <p>1. This financial statement is prepared for internal management purposes.</p>
            <p>2. All figures are subject to audit and verification.</p>
            <p>3. This document is auto-generated by BNTM Hub Financial Management System.</p>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Check if we can use TCPDF or mPDF
    // For now, we'll use browser's print-to-PDF capability with proper formatting
    
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    
    // Add auto-print script
    echo '<script>window.print();</script>';
    exit;
}
function bntm_fn_export_tab() {
    $nonce = wp_create_nonce('bntm_fn_action');
    $currency = bntm_get_setting('ec_currency', 'PHP');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Export Transactions</h3>
        <p>Download transaction data in CSV format for accounting or BIR compliance</p>
        
        <form id="bntm-fn-export-form" class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label for="export_start">Start Date</label>
                    <input type="date" id="export_start" name="start_date" required>
                </div>
                <div class="bntm-form-group">
                    <label for="export_end">End Date</label>
                    <input type="date" id="export_end" name="end_date" required>
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label for="export_type">Transaction Type</label>
                <select id="export_type" name="export_type">
                    <option value="all">All Transactions</option>
                    <option value="income">Income Only</option>
                    <option value="expense">Expenses Only</option>
                </select>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Download CSV</button>
        </form>
    </div>
    
    <script>
    document.getElementById('bntm-fn-export-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const params = new URLSearchParams({
            action: 'bntm_fn_export_csv',
            start_date: this.start_date.value,
            end_date: this.end_date.value,
            export_type: this.export_type.value,
            _ajax_nonce: '<?php echo $nonce; ?>'
        });
        
        window.location.href = ajaxurl + '?' + params.toString();
    });
    </script>
    <?php
    return ob_get_clean();
}

function bntm_fn_settings_tab() {
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        return '<div class="bntm-notice bntm-notice-error">Only Admins, Owners, and Managers can access settings.</div>';
    }
    
    $currency = bntm_get_setting('ec_currency', 'PHP');
    $income_cats = json_decode(bntm_get_setting('fn_categories_income', '[]'), true);
    $expense_cats = json_decode(bntm_get_setting('fn_categories_expense', '[]'), true);
    $nonce = wp_create_nonce('bntm_fn_action');
    
    ob_start();
    ?>
    
    
    <div class="bntm-form-section" style="margin-top: 30px;">
        <h3>Transaction Categories</h3>
        
        <form id="bntm-fn-categories-form" class="bntm-form">
            <div class="bntm-form-group">
                <label for="income_categories">Income Categories (one per line)</label>
                <textarea id="income_categories" name="income_categories" rows="6"><?php echo implode("\n", $income_cats); ?></textarea>
            </div>
            
            <div class="bntm-form-group">
                <label for="expense_categories">Expense Categories (one per line)</label>
                <textarea id="expense_categories" name="expense_categories" rows="6"><?php echo implode("\n", $expense_cats); ?></textarea>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Categories</button>
            <div id="categories-message"></div>
        </form>
    </div>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    
    // Save categories
    document.getElementById('bntm-fn-categories-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const msg = document.getElementById('categories-message');
        const btn = form.querySelector('button[type="submit"]');
        
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        const data = new FormData();
        data.append('action', 'bntm_fn_save_categories');
        data.append('income_categories', form.income_categories.value);
        data.append('expense_categories', form.expense_categories.value);
        data.append('_ajax_nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: data})
        .then(r => r.json())
        .then(json => {
            msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save Categories';
            
            if (json.success) {
                setTimeout(() => location.reload(), 1500);
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- HELPER FUNCTIONS ---------- */

function bntm_fn_get_transactions($limit = 50) {
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
        $limit
    ));
}

function bntm_fn_get_monthly_report($year, $month) {
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense,
            SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as balance
        FROM {$table}
        WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d
    ", $year, $month));
    
    return [
        'income' => $stats->income ?? 0,
        'expense' => $stats->expense ?? 0,
        'balance' => $stats->balance ?? 0
    ];
}

function bntm_fn_get_category_breakdown($year, $month) {
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT category, type, SUM(amount) as total
        FROM {$table}
        WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d
        GROUP BY category, type
        ORDER BY total DESC
    ", $year, $month));
}

function bntm_fn_update_cashflow_summary() {
    global $wpdb;
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $summary_table = $wpdb->prefix . 'fn_cashflow_summary';
    
    // Get current month period
    $period = date('Y-m');
    
    $stats = $wpdb->get_row("
        SELECT 
            SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as total_expense,
            SUM(CASE WHEN type='income' THEN amount ELSE -amount END) as balance
        FROM {$txn_table}
        WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$period}'
    ");
    
    // Check if summary exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$summary_table} WHERE period = %s ",
        $period
    ));
    
    $data = [
        'period' => $period,
        'business_id' => 0,
        'total_income' => $stats->total_income ?? 0,
        'total_expense' => $stats->total_expense ?? 0,
        'balance' => $stats->balance ?? 0
    ];
    
    if ($exists) {
        $wpdb->update($summary_table, $data, ['id' => $exists]);
    } else {
        $data['rand_id'] = bntm_rand_id();
        $wpdb->insert($summary_table, $data);
    }
}

/* ---------- AJAX HANDLERS ---------- */
function bntm_ajax_fn_save_transaction() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => 0,
        'type' => sanitize_text_field($_POST['type']),
        'amount' => floatval($_POST['amount']),
        'category' => sanitize_text_field($_POST['category']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];
    
    $result = $wpdb->insert($table, $data);
    
    if ($result) {
        bntm_fn_update_cashflow_summary();
        wp_send_json_success('Transaction added successfully!');
    } else {
        wp_send_json_error('Failed to add transaction.');
    }
}

function bntm_ajax_fn_delete_transaction() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $id = intval($_POST['id']);
    
    // Don't allow deleting imported transactions
    $txn = $wpdb->get_row($wpdb->prepare("SELECT reference_type FROM {$table} WHERE id = %d", $id));
    if ($txn && $txn->reference_type) {
        wp_send_json_error('Cannot delete imported transactions. Use revert instead.');
    }
    
    $result = $wpdb->delete($table, ['id' => $id]);
    
    if ($result) {
        bntm_fn_update_cashflow_summary();
        wp_send_json_success('Transaction deleted.');
    } else {
        wp_send_json_error('Failed to delete transaction.');
    }
}


function bntm_ajax_fn_save_categories() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error('Unauthorized.');
    }
    
    $income_cats = array_filter(array_map('trim', explode("\n", $_POST['income_categories'])));
    $expense_cats = array_filter(array_map('trim', explode("\n", $_POST['expense_categories'])));
    
    bntm_set_setting('fn_categories_income', json_encode($income_cats));
    bntm_set_setting('fn_categories_expense', json_encode($expense_cats));
    
    wp_send_json_success('Categories saved successfully!');
}

function bntm_ajax_fn_export_csv() {
    check_ajax_referer('bntm_fn_action');
    
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    
    $start_date = sanitize_text_field($_GET['start_date']);
    $end_date = sanitize_text_field($_GET['end_date']);
    $export_type = sanitize_text_field($_GET['export_type']);
    
    $where = "created_at BETWEEN '{$start_date}' AND '{$end_date}'";
    
    if ($export_type === 'income') {
        $where .= " AND type='income'";
    } elseif ($export_type === 'expense') {
        $where .= " AND type='expense'";
    }
    
    $transactions = $wpdb->get_results("SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC");
    
    // Generate CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=finance_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, ['Date', 'Type', 'Category', 'Amount', 'Notes', 'Reference']);
    
    foreach ($transactions as $txn) {
        fputcsv($output, [
            date('Y-m-d H:i:s', strtotime($txn->created_at)),
            ucfirst($txn->type),
            $txn->category,
            number_format($txn->amount, 2),
            $txn->notes,
            $txn->reference_type ? $txn->reference_type . ' #' . $txn->reference_id : ''
        ]);
    }
    
    fclose($output);
    exit;
}


// Cron job to update cashflow summary (run nightly)
add_action('bntm_fn_update_summary_cron', 'bntm_fn_update_cashflow_summary');

// Schedule cron on activation
register_activation_hook(__FILE__, 'bntm_fn_activate');
function bntm_fn_activate() {
    if (!wp_next_scheduled('bntm_fn_update_summary_cron')) {
        wp_schedule_event(time(), 'daily', 'bntm_fn_update_summary_cron');
    }
}

// Clear cron on deactivation
register_deactivation_hook(__FILE__, 'bntm_fn_deactivate');
function bntm_fn_deactivate() {
    wp_clear_scheduled_hook('bntm_fn_update_summary_cron');
}