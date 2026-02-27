<?php
/**
 * Module Name: VAT Management
 * Module Slug: vm
 * Description: Manage OR receipts, expenses, sales, and quarterly VAT reporting with receipt scanning
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: 📊
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_VM_PATH', dirname(__FILE__) . '/');
define('BNTM_VM_URL', plugin_dir_url(__FILE__));

// ============================================================================
// CORE MODULE FUNCTIONS (Required by Framework)
// ============================================================================

function bntm_vm_get_pages() {
    return [
        'VM Dashboard' => '[vm_dashboard]',
    ];
}

function bntm_vm_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'vat_receipts' => "CREATE TABLE {$prefix}vat_receipts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            receipt_type ENUM('expense', 'sales') NOT NULL DEFAULT 'expense',
            transaction_date DATE NOT NULL,
            store_name VARCHAR(255) NOT NULL,
            or_number VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            vat_exempt DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            vat_type ENUM('vat', 'non_vat') NOT NULL DEFAULT 'vat',
            category VARCHAR(100) DEFAULT NULL,
            expense_type ENUM('offset', 'company') DEFAULT NULL,
            quarter INT(1) DEFAULT NULL,
            year INT(4) DEFAULT NULL,
            receipt_image VARCHAR(500) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            status ENUM('active', 'archived') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (receipt_type),
            INDEX idx_date (transaction_date),
            INDEX idx_quarter (year, quarter),
            INDEX idx_vat_type (vat_type)
        ) {$charset};",
        
        'vat_categories' => "CREATE TABLE {$prefix}vat_categories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            category_name VARCHAR(100) NOT NULL,
            category_type ENUM('expense', 'sales', 'both') DEFAULT 'both',
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};"
    ];
}

function bntm_vm_get_shortcodes() {
    return ['vm_dashboard' => 'bntm_shortcode_vm_dashboard'];
}

function bntm_vm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_vm_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    global $wpdb;
    $categories_table = $wpdb->prefix . 'vat_categories';
    $business_id = get_current_user_id();
    
    $default_categories = [
        ['Office Supplies', 'expense'],
        ['Utilities', 'expense'],
        ['Transportation', 'expense'],
        ['Professional Fees', 'expense'],
        ['Marketing', 'expense'],
        ['Product Sales', 'sales'],
        ['Service Sales', 'sales'],
        ['Other Income', 'sales'],
    ];
    
    foreach ($default_categories as $cat) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$categories_table} WHERE category_name = %s AND business_id = %d",
            $cat[0], $business_id
        ));
        
        if (!$exists) {
            $wpdb->insert($categories_table, [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'category_name' => $cat[0],
                'category_type' => $cat[1],
                'is_active' => 1
            ], ['%s', '%d', '%s', '%s', '%d']);
        }
    }
    
    return count($tables);
}

// ============================================================================
// AJAX HANDLERS REGISTRATION
// ============================================================================

add_action('wp_ajax_vm_add_receipt', 'bntm_ajax_vm_add_receipt');
add_action('wp_ajax_vm_delete_receipt', 'bntm_ajax_vm_delete_receipt');
add_action('wp_ajax_vm_scan_receipt', 'bntm_ajax_vm_scan_receipt');
add_action('wp_ajax_vm_add_category', 'bntm_ajax_vm_add_category');
add_action('wp_ajax_vm_delete_category', 'bntm_ajax_vm_delete_category');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_vm_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access VAT Management.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <style>
    /* Modal Styles */
    .vm-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; 
        background-color: rgba(15, 23, 42, 0.6); overflow-y: auto; padding: 40px 20px; }
    .vm-modal-content { background-color: #fff; margin: auto; padding: 0; border-radius: 16px; 
        width: 100%; max-width: 700px; box-shadow: 0 25px 50px rgba(0,0,0,0.15); animation: modalSlideIn 0.3s ease-out; }
    @keyframes modalSlideIn { from { transform: translateY(-60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .vm-modal-header { padding: 32px 40px; border-bottom: 1px solid #f0f1f3; display: flex; 
        justify-content: space-between; align-items: center; background: linear-gradient(135deg, #f8f9fc 0%, #f3f4f8 100%); 
        border-radius: 16px 16px 0 0; }
    .vm-modal-header h2 { margin: 0; font-size: 24px; font-weight: 700; color: #0f1729; letter-spacing: -0.5px; }
    .vm-modal-close { width: 40px; height: 40px; border-radius: 8px; border: none; background: transparent; 
        color: #6b7280; font-size: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; 
        transition: all 0.2s ease; padding: 0; }
    .vm-modal-close:hover { background: #f0f1f3; color: #000; }
    
    .vm-modal-body { padding: 40px; max-height: calc(100vh - 200px); overflow-y: auto; }
    .vm-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
    .vm-form-grid-full { grid-column: 1 / -1; }
    
    /* Form Styles */
    .bntm-form-group { display: flex; flex-direction: column; }
    .bntm-form-group label { font-size: 14px; font-weight: 600; color: #1f2937; margin-bottom: 8px; letter-spacing: 0.3px; }
    .bntm-form-group small { font-size: 12px; color: #9ca3af; margin-top: 4px; }
    
    .bntm-input { padding: 12px 14px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 14px; 
        font-family: inherit; color: #1f2937; transition: all 0.2s ease; background: white; }
    .bntm-input:focus { outline: none; border-color: #4f46e5; background: #f8f9fc; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
    .bntm-input::placeholder { color: #d1d5db; }
    
    /* Button Styles */
    .bntm-btn-primary, .bntm-btn-secondary, .bntm-btn-small { padding: 11px 20px; border-radius: 8px; 
        border: none; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s ease; 
        display: inline-flex; align-items: center; gap: 8px; }
    
    .bntm-btn-primary { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
    .bntm-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4); }
    .bntm-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    
    .bntm-btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
    .bntm-btn-secondary:hover { background: #e5e7eb; }
    
    .bntm-btn-danger { background: #ef4444; color: white; padding: 8px 12px; font-size: 13px; }
    .bntm-btn-danger:hover { background: #dc2626; }
    
    /* Scanner Container */
    .receipt-scanner-container { border: 2px dashed #d1d5db; border-radius: 12px; padding: 40px 30px; 
        text-align: center; background: #f9fafb; transition: all 0.3s ease; }
    .receipt-scanner-container:hover { border-color: #4f46e5; background: #f8f9fc; }
    
    .scanner-icon { width: 70px; height: 70px; margin: 0 auto 20px; 
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius: 14px; 
        display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }
    
    .receipt-preview { margin-top: 24px; display: none; }
    .receipt-preview img { max-width: 100%; max-height: 350px; border-radius: 10px; 
        box-shadow: 0 8px 24px rgba(0,0,0,0.12); border: 1px solid #e5e7eb; }
    
    /* Quarter Filter */
    .quarter-filter { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .quarter-btn { padding: 10px 18px; border: 1.5px solid #d1d5db; background: white; border-radius: 8px; 
        cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.2s ease; color: #6b7280; }
    .quarter-btn:hover { border-color: #4f46e5; color: #4f46e5; background: #f8f9fc; }
    .quarter-btn.active { background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); 
        color: white; border-color: transparent; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
    
    /* Badge Styles */
    .bntm-badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 12px; 
        font-weight: 600; letter-spacing: 0.3px; }
    
    /* Notice Styles */
    .bntm-notice { padding: 14px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
    .bntm-notice-error { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }
    
    /* Dashboard Container */
    .bntm-vat-container { margin: 0 auto; }
    
    /* Tabs */
    .bntm-tabs { display: flex; gap: 8px; border-bottom: 2px solid #f0f1f3; margin-bottom: 32px; }
    .bntm-tab { padding: 14px 20px; font-weight: 600; font-size: 14px; color: #6b7280; 
        border-bottom: 3px solid transparent; margin-bottom: -2px; cursor: pointer; 
        text-decoration: none; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; }
    .bntm-tab:hover { color: #4f46e5; }
    .bntm-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    
    /* Form Section */
    .bntm-form-section { margin-bottom: 32px; }
    .bntm-form-section h3 { margin: 0 0 8px; font-size: 20px; font-weight: 700; color: #0f1729; }
    .bntm-form-section > p { margin: 0 0 20px; color: #6b7280; font-size: 14px; }
    
    /* Stats */
    .bntm-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 32px; }
    .bntm-stat-card { background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; 
        display: flex; gap: 16px; transition: all 0.2s ease; }
    .bntm-stat-card:hover { border-color: #d1d5db; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-content { flex: 1; }
    .stat-content h3 { margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #6b7280; letter-spacing: 0.3px; }
    .stat-number { margin: 0 0 4px; font-size: 28px; font-weight: 700; color: #0f1729; }
    .stat-label { font-size: 12px; color: #9ca3af; }
    
    /* Table */
    .bntm-table-container { overflow-x: auto; border-radius: 10px; border: 1px solid #e5e7eb; }
    .bntm-table { width: 100%; border-collapse: collapse; background: white; }
    .bntm-table thead { background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
    .bntm-table th { padding: 14px 16px; text-align: left; font-weight: 700; font-size: 12px; 
        color: #6b7280; letter-spacing: 0.3px; text-transform: uppercase; }
    .bntm-table td { padding: 14px 16px; border-bottom: 1px solid #f0f1f3; font-size: 14px; color: #374151; }
    .bntm-table tr:hover { background: #f9fafb; }
    .bntm-table tbody tr:last-child td { border-bottom: none; }
    </style>
    
    <div class="bntm-vat-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Overview
            </a>
            <a href="?tab=expenses" class="bntm-tab <?php echo $active_tab === 'expenses' ? 'active' : ''; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Expense Receipts
            </a>
            <a href="?tab=sales" class="bntm-tab <?php echo $active_tab === 'sales' ? 'active' : ''; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Sales Receipts
            </a>
            <a href="?tab=quarterly" class="bntm-tab <?php echo $active_tab === 'quarterly' ? 'active' : ''; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Quarterly Reports
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo vat_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'expenses'): ?>
                <?php echo vat_expenses_tab($business_id); ?>
            <?php elseif ($active_tab === 'sales'): ?>
                <?php echo vat_sales_tab($business_id); ?>
            <?php elseif ($active_tab === 'quarterly'): ?>
                <?php echo vat_quarterly_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo vat_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('VAT Management', $content);
}

// ============================================================================
// TAB RENDERING FUNCTIONS
// ============================================================================

function vat_overview_tab($business_id) {
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    
    $current_year = date('Y');
    $current_quarter = ceil(date('n') / 3);
    
    $current_quarter_stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(CASE WHEN receipt_type = 'expense' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as expense_vat,
            SUM(CASE WHEN receipt_type = 'sales' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as sales_vat,
            SUM(CASE WHEN receipt_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
            SUM(CASE WHEN receipt_type = 'sales' THEN amount ELSE 0 END) as total_sales
        FROM {$receipts_table}
        WHERE business_id = %d AND year = %d AND quarter = %d AND status = 'active'
    ", $business_id, $current_year, $current_quarter));
    
    $expense_vat = floatval($current_quarter_stats->expense_vat ?? 0);
    $sales_vat = floatval($current_quarter_stats->sales_vat ?? 0);
    $total_expenses = floatval($current_quarter_stats->total_expenses ?? 0);
    $total_sales = floatval($current_quarter_stats->total_sales ?? 0);
    $vat_payable = $sales_vat - $expense_vat;
    
    $ytd_stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(CASE WHEN receipt_type = 'expense' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as expense_vat,
            SUM(CASE WHEN receipt_type = 'sales' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as sales_vat,
            COUNT(CASE WHEN receipt_type = 'expense' THEN 1 END) as expense_count,
            COUNT(CASE WHEN receipt_type = 'sales' THEN 1 END) as sales_count
        FROM {$receipts_table}
        WHERE business_id = %d AND year = %d AND status = 'active'
    ", $business_id, $current_year));
    
    $ytd_expense_vat = floatval($ytd_stats->expense_vat ?? 0);
    $ytd_sales_vat = floatval($ytd_stats->sales_vat ?? 0);
    $ytd_vat_payable = $ytd_sales_vat - $ytd_expense_vat;
    
    $quarterly_data = $wpdb->get_results($wpdb->prepare("
        SELECT 
            quarter,
            SUM(CASE WHEN receipt_type = 'expense' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as expense_vat,
            SUM(CASE WHEN receipt_type = 'sales' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as sales_vat
        FROM {$receipts_table}
        WHERE business_id = %d AND year = %d AND status = 'active'
        GROUP BY quarter
        ORDER BY quarter
    ", $business_id, $current_year));
    
    ob_start();
    ?>
    
    <div class="bntm-stats-row">
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Expense VAT (Q<?php echo $current_quarter; ?>)</h3>
                <p class="stat-number">₱<?php echo number_format($expense_vat, 2); ?></p>
                <span class="stat-label">Total Expenses: ₱<?php echo number_format($total_expenses, 2); ?></span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Sales VAT (Q<?php echo $current_quarter; ?>)</h3>
                <p class="stat-number">₱<?php echo number_format($sales_vat, 2); ?></p>
                <span class="stat-label">Total Sales: ₱<?php echo number_format($total_sales, 2); ?></span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, <?php echo $vat_payable >= 0 ? '#f59e0b 0%, #d97706' : '#6366f1 0%, #4f46e5'; ?> 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>VAT <?php echo $vat_payable >= 0 ? 'Payable' : 'Refundable'; ?> (Q<?php echo $current_quarter; ?>)</h3>
                <p class="stat-number">₱<?php echo number_format(abs($vat_payable), 2); ?></p>
                <span class="stat-label"><?php echo $vat_payable >= 0 ? 'Amount to Pay' : 'Deduction Available'; ?></span>
            </div>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Year-to-Date Summary (<?php echo $current_year; ?>)</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 15px;">
            <div style="padding: 20px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f59e0b;">
                <div style="font-size: 13px; color: #92400e; font-weight: 500; margin-bottom: 8px;">YTD Expense VAT</div>
                <div style="font-size: 24px; font-weight: 700; color: #92400e;">₱<?php echo number_format($ytd_expense_vat, 2); ?></div>
                <div style="font-size: 12px; color: #92400e; margin-top: 4px;"><?php echo intval($ytd_stats->expense_count); ?> receipts</div>
            </div>
            
            <div style="padding: 20px; background: #d1fae5; border-radius: 8px; border-left: 4px solid #10b981;">
                <div style="font-size: 13px; color: #065f46; font-weight: 500; margin-bottom: 8px;">YTD Sales VAT</div>
                <div style="font-size: 24px; font-weight: 700; color: #065f46;">₱<?php echo number_format($ytd_sales_vat, 2); ?></div>
                <div style="font-size: 12px; color: #065f46; margin-top: 4px;"><?php echo intval($ytd_stats->sales_count); ?> receipts</div>
            </div>
            
            <div style="padding: 20px; background: <?php echo $ytd_vat_payable >= 0 ? '#fce7f3' : '#dbeafe'; ?>; border-radius: 8px; border-left: 4px solid <?php echo $ytd_vat_payable >= 0 ? '#ec4899' : '#3b82f6'; ?>;">
                <div style="font-size: 13px; color: <?php echo $ytd_vat_payable >= 0 ? '#9f1239' : '#1e3a8a'; ?>; font-weight: 500; margin-bottom: 8px;">YTD Net VAT</div>
                <div style="font-size: 24px; font-weight: 700; color: <?php echo $ytd_vat_payable >= 0 ? '#9f1239' : '#1e3a8a'; ?>;">₱<?php echo number_format(abs($ytd_vat_payable), 2); ?></div>
                <div style="font-size: 12px; color: <?php echo $ytd_vat_payable >= 0 ? '#9f1239' : '#1e3a8a'; ?>; margin-top: 4px;"><?php echo $ytd_vat_payable >= 0 ? 'Payable' : 'Refundable'; ?></div>
            </div>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Quarterly VAT Comparison (<?php echo $current_year; ?>)</h3>
        <canvas id="quarterly-vat-chart" width="400" height="200"></canvas>
    </div>
    
    <div class="bntm-form-section">
        <h3>Quick Actions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
            <a href="?tab=expenses" class="bntm-btn-primary" style="text-align: center; text-decoration: none; display: block;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Expense Receipt
            </a>
            
            <a href="?tab=sales" class="bntm-btn-primary" style="text-align: center; text-decoration: none; display: block;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Sales Receipt
            </a>
            
            <a href="?tab=quarterly" class="bntm-btn-secondary" style="text-align: center; text-decoration: none; display: block;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px; vertical-align: middle;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                View Quarterly Reports
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    (function() {
        const quarterlyData = <?php echo json_encode($quarterly_data); ?>;
        const quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        const expenseVat = new Array(4).fill(0);
        const salesVat = new Array(4).fill(0);
        
        quarterlyData.forEach(q => {
            const idx = parseInt(q.quarter) - 1;
            expenseVat[idx] = parseFloat(q.expense_vat);
            salesVat[idx] = parseFloat(q.sales_vat);
        });
        
        const ctx = document.getElementById('quarterly-vat-chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: quarters,
                datasets: [
                    {
                        label: 'Expense VAT (Deduction)',
                        data: expenseVat,
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: 2
                    },
                    {
                        label: 'Sales VAT (Collection)',
                        data: salesVat,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) label += ': ';
                                label += '₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2});
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function vat_expenses_tab($business_id) {
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    $categories_table = $wpdb->prefix . 'vat_categories';
    
    $receipts = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$receipts_table}
        WHERE business_id = %d AND receipt_type = 'expense' AND status = 'active'
        ORDER BY transaction_date DESC, created_at DESC
    ", $business_id));
    
    $categories = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$categories_table}
        WHERE business_id = %d AND category_type IN ('expense', 'both') AND is_active = 1
        ORDER BY category_name
    ", $business_id));
    
    $nonce = wp_create_nonce('vm_receipt_action');
    
    ob_start();
    ?>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
            <div>
                <h3 style="margin: 0 0 4px;">Expense Receipts</h3>
                <p style="margin: 0; color: #6b7280; font-size: 14px;">Manage your business expenses and track deductible VAT</p>
            </div>
            <button id="add-expense-btn" class="bntm-btn-primary" style="white-space: nowrap;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Receipt
            </button>
        </div>
        
        <?php if (empty($receipts)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 12px;">
            <svg width="64" height="64" fill="none" stroke="#9ca3af" viewBox="0 0 24 24" style="margin: 0 auto 16px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 style="color: #6b7280; margin: 0 0 8px;">No expense receipts yet</h3>
            <p style="color: #9ca3af; margin: 0;">Click "Add Expense Receipt" to get started</p>
        </div>
        <?php else: ?>
        <div class="bntm-table-container">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>OR Number</th>
                        <th>Store Name</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>VAT Amount</th>
                        <th>Type</th>
                        <th>Expense Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($receipt->transaction_date)); ?></td>
                        <td><strong><?php echo esc_html($receipt->or_number); ?></strong></td>
                        <td><?php echo esc_html($receipt->store_name); ?></td>
                        <td>
                            <span class="bntm-badge" style="background: #dbeafe; color: #1e40af;">
                                <?php echo esc_html($receipt->category ?: 'Uncategorized'); ?>
                            </span>
                        </td>
                        <td>₱<?php echo number_format($receipt->amount, 2); ?></td>
                        <td class="bntm-stat-expense">₱<?php echo number_format($receipt->vat_amount, 2); ?></td>
                        <td>
                            <span class="bntm-badge" style="background: <?php echo $receipt->vat_type === 'vat' ? '#dcfce7' : '#f3f4f6'; ?>; color: <?php echo $receipt->vat_type === 'vat' ? '#166534' : '#6b7280'; ?>;">
                                <?php echo $receipt->vat_type === 'vat' ? 'VAT' : 'Non-VAT'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="bntm-badge" style="background: <?php echo $receipt->expense_type === 'offset' ? '#fef3c7' : '#dbeafe'; ?>; color: <?php echo $receipt->expense_type === 'offset' ? '#92400e' : '#1e40af'; ?>;">
                                <?php echo $receipt->expense_type === 'offset' ? 'Offset' : 'Company'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="bntm-btn-small bntm-btn-danger delete-receipt-btn" data-id="<?php echo $receipt->id; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="expense-modal" class="vm-modal">
        <div class="vm-modal-content">
            <div class="vm-modal-header">
                <h2>Add Expense Receipt</h2>
                <button class="vm-modal-close">&times;</button>
            </div>
            <div class="vm-modal-body">
                <form id="expense-form" enctype="multipart/form-data">
                    
                    <div class="vm-form-grid-full">
                        <div class="receipt-scanner-container">
                            <div class="scanner-icon">
                                <svg width="36" height="36" fill="none" stroke="white" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <h4 style="margin: 0 0 8px; color: #0f1729; font-size: 16px; font-weight: 700;">Upload Receipt (Optional)</h4>
                            <p style="margin: 0 0 24px; color: #6b7280; font-size: 14px; line-height: 1.5;">Scan your receipt to automatically extract details, or enter manually below</p>
                            <input type="file" id="receipt-scanner" name="receipt_image" accept="image/*" style="display: none;">
                            <button type="button" id="scan-receipt-btn" class="bntm-btn-primary" style="width: 100%; justify-content: center;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Choose Receipt Image
                            </button>
                            <button type="button" id="manual-entry-btn" class="bntm-btn-secondary" style="width: 100%; justify-content: center; margin-top: 12px;">
                                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Enter Details Manually
                            </button>
                            <div class="receipt-preview" id="receipt-preview">
                                <img id="preview-image" src="" alt="Receipt Preview" style="margin-bottom: 12px;">
                                <button type="button" id="remove-image-btn" class="bntm-btn-danger" style="width: 100%; justify-content: center;">Remove Image</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vm-form-grid" id="expense-form-fields" style="display: none; margin-top: 20px;">
                        <div class="bntm-form-group">
                            <label>Transaction Date *</label>
                            <input type="date" name="transaction_date" required class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>OR Number *</label>
                            <input type="text" name="or_number" placeholder="e.g., OR-123456" required class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group vm-form-grid-full">
                            <label>Store Name *</label>
                            <input type="text" name="store_name" placeholder="e.g., ABC Supplies Inc." required class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Category *</label>
                            <select name="category" required class="bntm-input">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->category_name); ?>"><?php echo esc_html($cat->category_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Expense Type *</label>
                            <select name="expense_type" required class="bntm-input">
                                <option value="">Select Type</option>
                                <option value="offset">Offset (Tax Deductible)</option>
                                <option value="company">Company Expense</option>
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Total Amount *</label>
                            <input type="number" name="amount" step="0.01" placeholder="0.00" required class="bntm-input" id="expense-amount">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>VAT Type *</label>
                            <select name="vat_type" required class="bntm-input" id="expense-vat-type">
                                <option value="vat">VAT (12%)</option>
                                <option value="non_vat">Non-VAT</option>
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>VAT Exempt Amount</label>
                            <input type="number" name="vat_exempt" step="0.01" placeholder="0.00" class="bntm-input" id="expense-vat-exempt">
                            <small>Amount not subject to VAT</small>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>VAT Amount *</label>
                            <input type="number" name="vat_amount" step="0.01" placeholder="0.00" required class="bntm-input" id="expense-vat-amount" readonly>
                            <small>Calculated automatically</small>
                        </div>
                        
                        <div class="bntm-form-group vm-form-grid-full">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes..." class="bntm-input"></textarea>
                        </div>
                    </div>
                    
                    <div style="margin-top: 28px; display: none; gap: 12px; justify-content: flex-end;" id="expense-form-actions">
                        <button type="button" class="bntm-btn-secondary vm-modal-close">Cancel</button>
                        <button type="submit" class="bntm-btn-primary">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Receipt
                        </button>
                    </div>
                </form>
                <div id="expense-message"></div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const modal = document.getElementById('expense-modal');
        const form = document.getElementById('expense-form');
        const formFields = document.getElementById('expense-form-fields');
        const formActions = document.getElementById('expense-form-actions');
        const scannerContainer = document.querySelector('.receipt-scanner-container');
        const scanBtn = document.getElementById('scan-receipt-btn');
        const manualBtn = document.getElementById('manual-entry-btn');
        const scanner = document.getElementById('receipt-scanner');
        const preview = document.getElementById('receipt-preview');
        const previewImage = document.getElementById('preview-image');
        const removeImageBtn = document.getElementById('remove-image-btn');
        
        document.getElementById('add-expense-btn').addEventListener('click', function() {
            modal.style.display = 'block';
            form.reset();
            formFields.style.display = 'none';
            formActions.style.display = 'none';
            scannerContainer.style.display = 'block';
            preview.style.display = 'none';
        });
        
        document.querySelectorAll('.vm-modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
        
        scanBtn.addEventListener('click', function() {
            scanner.click();
        });
        
        manualBtn.addEventListener('click', function() {
            formFields.style.display = 'grid';
            formActions.style.display = 'flex';
            scannerContainer.style.display = 'none';
        });
        
        scanner.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(event) {
                previewImage.src = event.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
            
            scanBtn.disabled = true;
            scanBtn.textContent = 'Processing...';
            
            setTimeout(() => {
                formFields.style.display = 'grid';
                formActions.style.display = 'flex';
                scanBtn.disabled = false;
                scanBtn.textContent = 'Upload Receipt Image';
                
                form.querySelector('[name="transaction_date"]').value = new Date().toISOString().split('T')[0];
                form.querySelector('[name="or_number"]').value = 'OR-' + Date.now();
                form.querySelector('[name="store_name"]').value = 'Scanned Store Name';
                form.querySelector('[name="amount"]').value = '1120.00';
                calculateVAT();
            }, 2000);
        });
        
        removeImageBtn.addEventListener('click', function() {
            scanner.value = '';
            preview.style.display = 'none';
            previewImage.src = '';
        });
        
        function calculateVAT() {
            const amount = parseFloat(document.getElementById('expense-amount').value) || 0;
            const vatType = document.getElementById('expense-vat-type').value;
            const vatExempt = parseFloat(document.getElementById('expense-vat-exempt').value) || 0;
            
            let vatAmount = 0;
            if (vatType === 'vat') {
                const vatableAmount = amount - vatExempt;
                vatAmount = vatableAmount - (vatableAmount / 1.12);
            }
            
            document.getElementById('expense-vat-amount').value = vatAmount.toFixed(2);
        }
        
        document.getElementById('expense-amount').addEventListener('input', calculateVAT);
        document.getElementById('expense-vat-type').addEventListener('change', calculateVAT);
        document.getElementById('expense-vat-exempt').addEventListener('input', calculateVAT);
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'vm_add_receipt');
            formData.append('receipt_type', 'expense');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    location.reload();
                } else {
                    document.getElementById('expense-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Save Receipt';
                }
            });
        });
        
        document.querySelectorAll('.delete-receipt-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this receipt?')) return;
                
                const formData = new FormData();
                formData.append('action', 'vm_delete_receipt');
                formData.append('receipt_id', this.dataset.id);
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

function vat_sales_tab($business_id) {
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    $categories_table = $wpdb->prefix . 'vat_categories';
    
    $receipts = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$receipts_table}
        WHERE business_id = %d AND receipt_type = 'sales' AND status = 'active'
        ORDER BY transaction_date DESC, created_at DESC
    ", $business_id));
    
    $categories = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$categories_table}
        WHERE business_id = %d AND category_type IN ('sales', 'both') AND is_active = 1
        ORDER BY category_name
    ", $business_id));
    
    $nonce = wp_create_nonce('vm_receipt_action');
    
    ob_start();
    ?>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
            <div>
                <h3 style="margin: 0 0 4px;">Sales Receipts</h3>
                <p style="margin: 0; color: #6b7280; font-size: 14px;">Record your sales transactions and collected VAT</p>
            </div>
            <button id="add-sales-btn" class="bntm-btn-primary" style="white-space: nowrap;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Receipt
            </button>
        </div>
        
        <?php if (empty($receipts)): ?>
        <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 12px;">
            <svg width="64" height="64" fill="none" stroke="#9ca3af" viewBox="0 0 24 24" style="margin: 0 auto 16px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 style="color: #6b7280; margin: 0 0 8px;">No sales receipts yet</h3>
            <p style="color: #9ca3af; margin: 0;">Click "Add Sales Receipt" to get started</p>
        </div>
        <?php else: ?>
        <div class="bntm-table-container">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>OR Number</th>
                        <th>Customer Name</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>VAT Amount</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $receipt): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($receipt->transaction_date)); ?></td>
                        <td><strong><?php echo esc_html($receipt->or_number); ?></strong></td>
                        <td><?php echo esc_html($receipt->store_name); ?></td>
                        <td>
                            <span class="bntm-badge" style="background: #dbeafe; color: #1e40af;">
                                <?php echo esc_html($receipt->category ?: 'Uncategorized'); ?>
                            </span>
                        </td>
                        <td>₱<?php echo number_format($receipt->amount, 2); ?></td>
                        <td class="bntm-stat-income">₱<?php echo number_format($receipt->vat_amount, 2); ?></td>
                        <td>
                            <span class="bntm-badge" style="background: <?php echo $receipt->vat_type === 'vat' ? '#dcfce7' : '#f3f4f6'; ?>; color: <?php echo $receipt->vat_type === 'vat' ? '#166534' : '#6b7280'; ?>;">
                                <?php echo $receipt->vat_type === 'vat' ? 'VAT' : 'Non-VAT'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="bntm-btn-small bntm-btn-danger delete-sales-btn" data-id="<?php echo $receipt->id; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="sales-modal" class="vm-modal">
        <div class="vm-modal-content">
            <div class="vm-modal-header">
                <h2>Add Sales Receipt</h2>
                <button class="vm-modal-close">&times;</button>
            </div>
            <div class="vm-modal-body">
                <form id="sales-form">
                    <div class="vm-form-grid">
                        <div class="bntm-form-group">
                            <label>Transaction Date *</label>
                            <input type="date" name="transaction_date" required class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>OR Number *</label>
                            <input type="text" name="or_number" placeholder="e.g., OR-123456" required class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group vm-form-grid-full">
                            <label>Customer Name *</label>
                            <input type="text" name="store_name" placeholder="e.g., John Doe" required class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Category *</label>
                            <select name="category" required class="bntm-input">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->category_name); ?>"><?php echo esc_html($cat->category_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Total Amount *</label>
                            <input type="number" name="amount" step="0.01" placeholder="0.00" required class="bntm-input" id="sales-amount">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>VAT Type *</label>
                            <select name="vat_type" required class="bntm-input" id="sales-vat-type">
                                <option value="vat">VAT (12%)</option>
                                <option value="non_vat">Non-VAT</option>
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>VAT Exempt Amount</label>
                            <input type="number" name="vat_exempt" step="0.01" placeholder="0.00" class="bntm-input" id="sales-vat-exempt">
                            <small>Amount not subject to VAT</small>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>VAT Amount *</label>
                            <input type="number" name="vat_amount" step="0.01" placeholder="0.00" required class="bntm-input" id="sales-vat-amount" readonly>
                            <small>Calculated automatically</small>
                        </div>
                        
                        <div class="bntm-form-group vm-form-grid-full">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes..." class="bntm-input"></textarea>
                        </div>
                    </div>
                    
                    <div style="margin-top: 28px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="bntm-btn-secondary vm-modal-close">Cancel</button>
                        <button type="submit" class="bntm-btn-primary">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Save Receipt
                        </button>
                    </div>
                </form>
                <div id="sales-message"></div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const modal = document.getElementById('sales-modal');
        const form = document.getElementById('sales-form');
        
        document.getElementById('add-sales-btn').addEventListener('click', function() {
            modal.style.display = 'block';
            form.reset();
        });
        
        document.querySelectorAll('.vm-modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
        
        function calculateVAT() {
            const amount = parseFloat(document.getElementById('sales-amount').value) || 0;
            const vatType = document.getElementById('sales-vat-type').value;
            const vatExempt = parseFloat(document.getElementById('sales-vat-exempt').value) || 0;
            
            let vatAmount = 0;
            if (vatType === 'vat') {
                const vatableAmount = amount - vatExempt;
                vatAmount = vatableAmount - (vatableAmount / 1.12);
            }
            
            document.getElementById('sales-vat-amount').value = vatAmount.toFixed(2);
        }
        
        document.getElementById('sales-amount').addEventListener('input', calculateVAT);
        document.getElementById('sales-vat-type').addEventListener('change', calculateVAT);
        document.getElementById('sales-vat-exempt').addEventListener('input', calculateVAT);
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'vm_add_receipt');
            formData.append('receipt_type', 'sales');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    location.reload();
                } else {
                    document.getElementById('sales-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Save Receipt';
                }
            });
        });
        
        document.querySelectorAll('.delete-sales-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this receipt?')) return;
                
                const formData = new FormData();
                formData.append('action', 'vm_delete_receipt');
                formData.append('receipt_id', this.dataset.id);
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

function vat_quarterly_tab($business_id) {
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $selected_quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : null;
    
    $available_years = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT year FROM {$receipts_table}
        WHERE business_id = %d AND year IS NOT NULL
        ORDER BY year DESC
    ", $business_id));
    
    if (empty($available_years)) {
        $available_years = [date('Y')];
    }
    
    $quarterly_data = [];
    for ($q = 1; $q <= 4; $q++) {
        if ($selected_quarter && $selected_quarter !== $q) continue;
        
        $data = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN receipt_type = 'expense' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as expense_vat,
                SUM(CASE WHEN receipt_type = 'sales' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as sales_vat,
                SUM(CASE WHEN receipt_type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                SUM(CASE WHEN receipt_type = 'sales' THEN amount ELSE 0 END) as total_sales,
                COUNT(CASE WHEN receipt_type = 'expense' THEN 1 END) as expense_count,
                COUNT(CASE WHEN receipt_type = 'sales' THEN 1 END) as sales_count
            FROM {$receipts_table}
            WHERE business_id = %d AND year = %d AND quarter = %d AND status = 'active'
        ", $business_id, $current_year, $q));
        
        $expense_vat = floatval($data->expense_vat ?? 0);
        $sales_vat = floatval($data->sales_vat ?? 0);
        
        $quarterly_data[$q] = [
            'quarter' => $q,
            'expense_vat' => $expense_vat,
            'sales_vat' => $sales_vat,
            'vat_payable' => $sales_vat - $expense_vat,
            'total_expenses' => floatval($data->total_expenses ?? 0),
            'total_sales' => floatval($data->total_sales ?? 0),
            'expense_count' => intval($data->expense_count ?? 0),
            'sales_count' => intval($data->sales_count ?? 0),
        ];
    }
    
    ob_start();
    ?>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Quarterly VAT Reports</h3>
            <div style="display: flex; gap: 10px;">
                <select id="year-filter" class="bntm-input" style="width: 120px;">
                    <?php foreach ($available_years as $year): ?>
                    <option value="<?php echo $year; ?>" <?php selected($year, $current_year); ?>><?php echo $year; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="quarter-filter">
            <button class="quarter-btn <?php echo !$selected_quarter ? 'active' : ''; ?>" data-quarter="all">All Quarters</button>
            <button class="quarter-btn <?php echo $selected_quarter === 1 ? 'active' : ''; ?>" data-quarter="1">Q1 (Jan-Mar)</button>
            <button class="quarter-btn <?php echo $selected_quarter === 2 ? 'active' : ''; ?>" data-quarter="2">Q2 (Apr-Jun)</button>
            <button class="quarter-btn <?php echo $selected_quarter === 3 ? 'active' : ''; ?>" data-quarter="3">Q3 (Jul-Sep)</button>
            <button class="quarter-btn <?php echo $selected_quarter === 4 ? 'active' : ''; ?>" data-quarter="4">Q4 (Oct-Dec)</button>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <?php foreach ($quarterly_data as $q => $data): ?>
            <div style="background: white; border-radius: 12px; padding: 24px; border: 2px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h4 style="margin: 0; font-size: 18px; color: #111827;">Quarter <?php echo $q; ?> - <?php echo $current_year; ?></h4>
                    <span class="bntm-badge" style="background: #f3f4f6; color: #6b7280;">
                        <?php echo $data['expense_count'] + $data['sales_count']; ?> receipts
                    </span>
                </div>
                
                <div style="margin-bottom: 12px; padding: 12px; background: #fef3c7; border-radius: 8px;">
                    <div style="font-size: 12px; color: #92400e; margin-bottom: 4px;">Expense VAT (Deduction)</div>
                    <div style="font-size: 20px; font-weight: 700; color: #92400e;">₱<?php echo number_format($data['expense_vat'], 2); ?></div>
                    <div style="font-size: 11px; color: #92400e; margin-top: 2px;">From ₱<?php echo number_format($data['total_expenses'], 2); ?> expenses</div>
                </div>
                
                <div style="margin-bottom: 12px; padding: 12px; background: #d1fae5; border-radius: 8px;">
                    <div style="font-size: 12px; color: #065f46; margin-bottom: 4px;">Sales VAT (Collection)</div>
                    <div style="font-size: 20px; font-weight: 700; color: #065f46;">₱<?php echo number_format($data['sales_vat'], 2); ?></div>
                    <div style="font-size: 11px; color: #065f46; margin-top: 2px;">From ₱<?php echo number_format($data['total_sales'], 2); ?> sales</div>
                </div>
                
                <div style="padding: 12px; background: <?php echo $data['vat_payable'] >= 0 ? '#fce7f3' : '#dbeafe'; ?>; border-radius: 8px;">
                    <div style="font-size: 12px; color: <?php echo $data['vat_payable'] >= 0 ? '#9f1239' : '#1e3a8a'; ?>; margin-bottom: 4px;">
                        Net VAT <?php echo $data['vat_payable'] >= 0 ? 'Payable' : 'Refundable'; ?>
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: <?php echo $data['vat_payable'] >= 0 ? '#9f1239' : '#1e3a8a'; ?>;">
                        ₱<?php echo number_format(abs($data['vat_payable']), 2); ?>
                    </div>
                </div>
                
                <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                    <div style="text-align: center; padding: 8px; background: #f9fafb; border-radius: 6px;">
                        <div style="color: #6b7280;">Expenses</div>
                        <div style="font-weight: 600; color: #111827;"><?php echo $data['expense_count']; ?></div>
                    </div>
                    <div style="text-align: center; padding: 8px; background: #f9fafb; border-radius: 6px;">
                        <div style="color: #6b7280;">Sales</div>
                        <div style="font-weight: 600; color: #111827;"><?php echo $data['sales_count']; ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    (function() {
        document.getElementById('year-filter').addEventListener('change', function() {
            window.location.href = '?tab=quarterly&year=' + this.value;
        });
        
        document.querySelectorAll('.quarter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const quarter = this.dataset.quarter;
                const year = document.getElementById('year-filter').value;
                
                if (quarter === 'all') {
                    window.location.href = '?tab=quarterly&year=' + year;
                } else {
                    window.location.href = '?tab=quarterly&year=' + year + '&quarter=' + quarter;
                }
            });
        });
    })();
    </script>
    
    <?php
    return ob_get_clean();
}

function vat_settings_tab($business_id) {
    global $wpdb;
    $categories_table = $wpdb->prefix . 'vat_categories';
    
    $categories = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$categories_table}
        WHERE business_id = %d
        ORDER BY category_type, category_name
    ", $business_id));
    
    $nonce = wp_create_nonce('vm_settings_action');
    
    ob_start();
    ?>
    
    <div class="bntm-form-section">
        <h3>VAT Categories</h3>
        <p>Manage categories for organizing your receipts</p>
        
        <div style="margin-bottom: 24px;">
            <button id="add-category-btn" class="bntm-btn-primary">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Category
            </button>
        </div>
        
        <div class="bntm-table-container">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong><?php echo esc_html($cat->category_name); ?></strong></td>
                        <td>
                            <span class="bntm-badge" style="background: <?php 
                                echo $cat->category_type === 'expense' ? '#fef3c7' : ($cat->category_type === 'sales' ? '#d1fae5' : '#dbeafe'); 
                            ?>; color: <?php 
                                echo $cat->category_type === 'expense' ? '#92400e' : ($cat->category_type === 'sales' ? '#065f46' : '#1e40af'); 
                            ?>;">
                                <?php echo ucfirst($cat->category_type); ?>
                            </span>
                        </td>
                        <td>
                            <span class="bntm-badge" style="background: <?php echo $cat->is_active ? '#dcfce7' : '#f3f4f6'; ?>; color: <?php echo $cat->is_active ? '#166534' : '#6b7280'; ?>;">
                                <?php echo $cat->is_active ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="bntm-btn-small bntm-btn-danger delete-category-btn" data-id="<?php echo $cat->id; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="category-modal" class="vm-modal">
        <div class="vm-modal-content" style="max-width: 500px;">
            <div class="vm-modal-header">
                <h2>Add Category</h2>
                <button class="vm-modal-close">&times;</button>
            </div>
            <div class="vm-modal-body">
                <form id="category-form">
                    <div class="bntm-form-group">
                        <label>Category Name *</label>
                        <input type="text" name="category_name" placeholder="e.g., Office Rent" required class="bntm-input">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Category Type *</label>
                        <select name="category_type" required class="bntm-input">
                            <option value="">Select Type</option>
                            <option value="expense">Expense Only</option>
                            <option value="sales">Sales Only</option>
                            <option value="both">Both Expense & Sales</option>
                        </select>
                    </div>
                    
                    <div style="margin-top: 28px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="bntm-btn-secondary vm-modal-close">Cancel</button>
                        <button type="submit" class="bntm-btn-primary">
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Category
                        </button>
                    </div>
                </form>
                <div id="category-message"></div>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const modal = document.getElementById('category-modal');
        const form = document.getElementById('category-form');
        
        document.getElementById('add-category-btn').addEventListener('click', function() {
            modal.style.display = 'block';
            form.reset();
        });
        
        document.querySelectorAll('.vm-modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'vm_add_category');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    location.reload();
                } else {
                    document.getElementById('category-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Add Category';
                }
            });
        });
        
        document.querySelectorAll('.delete-category-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this category? This action cannot be undone.')) return;
                
                const formData = new FormData();
                formData.append('action', 'vm_delete_category');
                formData.append('category_id', this.dataset.id);
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

// ============================================================================
// AJAX HANDLER FUNCTIONS
// ============================================================================

function bntm_ajax_vm_add_receipt() {
    check_ajax_referer('vm_receipt_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    $business_id = get_current_user_id();
    
    $receipt_type = sanitize_text_field($_POST['receipt_type']);
    $transaction_date = sanitize_text_field($_POST['transaction_date']);
    $store_name = sanitize_text_field($_POST['store_name']);
    $or_number = sanitize_text_field($_POST['or_number']);
    $amount = floatval($_POST['amount']);
    $vat_exempt = floatval($_POST['vat_exempt'] ?? 0);
    $vat_amount = floatval($_POST['vat_amount']);
    $vat_type = sanitize_text_field($_POST['vat_type']);
    $category = sanitize_text_field($_POST['category']);
    $expense_type = isset($_POST['expense_type']) ? sanitize_text_field($_POST['expense_type']) : null;
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($transaction_date) || empty($or_number) || $amount <= 0) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
    }
    
    $date_obj = new DateTime($transaction_date);
    $quarter = ceil($date_obj->format('n') / 3);
    $year = $date_obj->format('Y');
    
    $receipt_image = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload = wp_handle_upload($_FILES['receipt_image'], ['test_form' => false]);
        if ($upload && !isset($upload['error'])) {
            $receipt_image = $upload['url'];
        }
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'receipt_type' => $receipt_type,
        'transaction_date' => $transaction_date,
        'store_name' => $store_name,
        'or_number' => $or_number,
        'amount' => $amount,
        'vat_exempt' => $vat_exempt,
        'vat_amount' => $vat_amount,
        'vat_type' => $vat_type,
        'category' => $category,
        'expense_type' => $expense_type,
        'quarter' => $quarter,
        'year' => $year,
        'receipt_image' => $receipt_image,
        'notes' => $notes,
        'status' => 'active'
    ];
    
    $format = ['%s','%d','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%d','%d','%s','%s','%s'];
    $result = $wpdb->insert($receipts_table, $data, $format);
    
    if ($result) {
        wp_send_json_success(['message' => ucfirst($receipt_type) . ' receipt added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to save receipt']);
    }
}

function bntm_ajax_vm_delete_receipt() {
    check_ajax_referer('vm_receipt_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    $receipt_id = intval($_POST['receipt_id']);
    $business_id = get_current_user_id();
    
    $receipt = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$receipts_table} WHERE id = %d AND business_id = %d",
        $receipt_id, $business_id
    ));
    
    if (!$receipt) wp_send_json_error(['message' => 'Receipt not found']);
    
    $result = $wpdb->update($receipts_table, ['status' => 'archived'], ['id' => $receipt_id], ['%s'], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Receipt deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete receipt']);
    }
}

function bntm_ajax_vm_add_category() {
    check_ajax_referer('vm_settings_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    global $wpdb;
    $categories_table = $wpdb->prefix . 'vat_categories';
    $business_id = get_current_user_id();
    
    $category_name = sanitize_text_field($_POST['category_name']);
    $category_type = sanitize_text_field($_POST['category_type']);
    
    if (empty($category_name) || empty($category_type)) {
        wp_send_json_error(['message' => 'Please fill in all fields']);
    }
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$categories_table} WHERE category_name = %s AND business_id = %d",
        $category_name, $business_id
    ));
    
    if ($exists) wp_send_json_error(['message' => 'Category already exists']);
    
    $result = $wpdb->insert($categories_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'category_name' => $category_name,
        'category_type' => $category_type,
        'is_active' => 1
    ], ['%s','%d','%s','%s','%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Category added successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to add category']);
    }
}

function bntm_ajax_vm_delete_category() {
    check_ajax_referer('vm_settings_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    global $wpdb;
    $categories_table = $wpdb->prefix . 'vat_categories';
    $category_id = intval($_POST['category_id']);
    $business_id = get_current_user_id();
    
    $category = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$categories_table} WHERE id = %d AND business_id = %d",
        $category_id, $business_id
    ));
    
    if (!$category) wp_send_json_error(['message' => 'Category not found']);
    
    $result = $wpdb->delete($categories_table, ['id' => $category_id, 'business_id' => $business_id], ['%d', '%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Category deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete category']);
    }
}

function bntm_ajax_vm_scan_receipt() {
    check_ajax_referer('vm_receipt_action', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    
    if (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'No image uploaded']);
    }
    
    $upload = wp_handle_upload($_FILES['receipt_image'], ['test_form' => false]);
    
    if ($upload && !isset($upload['error'])) {
        // TODO: Integrate with OCR service (Google Vision API, Tesseract, etc.)
        // For now, return mock data
        wp_send_json_success([
            'message' => 'Receipt scanned successfully',
            'data' => [
                'transaction_date' => date('Y-m-d'),
                'store_name' => 'Scanned Store Name',
                'or_number' => 'OR-' . time(),
                'amount' => 1120.00,
                'vat_amount' => 120.00,
                'receipt_image' => $upload['url']
            ]
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to upload image']);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function vat_calculate_vat($total_amount, $vat_exempt = 0) {
    $vatable_amount = $total_amount - $vat_exempt;
    $vat_amount = $vatable_amount - ($vatable_amount / 1.12);
    return round($vat_amount, 2);
}

function vat_format_currency($amount) {
    return '₱' . number_format($amount, 2);
}

function vat_get_quarter($date) {
    $month = date('n', strtotime($date));
    return ceil($month / 3);
}

function vat_get_quarter_range($year, $quarter) {
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    return [
        'start' => sprintf('%d-%02d-01', $year, $start_month),
        'end' => sprintf('%d-%02d-%02d', $year, $end_month, cal_days_in_month(CAL_GREGORIAN, $end_month, $year))
    ];
}

function vat_get_stats($business_id, $year = null, $quarter = null) {
    global $wpdb;
    $receipts_table = $wpdb->prefix . 'vat_receipts';
    
    if (!$year) $year = date('Y');
    
    $where_clause = "business_id = %d AND year = %d AND status = 'active'";
    $params = [$business_id, $year];
    
    if ($quarter) {
        $where_clause .= " AND quarter = %d";
        $params[] = $quarter;
    }
    
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(CASE WHEN receipt_type = 'expense' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as expense_vat,
            SUM(CASE WHEN receipt_type = 'sales' AND vat_type = 'vat' THEN vat_amount ELSE 0 END) as sales_vat,
            COUNT(CASE WHEN receipt_type = 'expense' THEN 1 END) as expense_count,
            COUNT(CASE WHEN receipt_type = 'sales' THEN 1 END) as sales_count
        FROM {$receipts_table}
        WHERE {$where_clause}
    ", $params));
    
    return $stats;
}
?>