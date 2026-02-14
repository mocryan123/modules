<?php
/**
 * Module Name: Loan Management
 * Module Slug: lm
 * Description: Simple and comprehensive loan management system with borrower records, repayment schedules, and tracking
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: 💰
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_LM_PATH', dirname(__FILE__) . '/');
define('BNTM_LM_URL', plugin_dir_url(__FILE__));

/**
 * Get module pages configuration
 */
function bntm_lm_get_pages() {
    return [
        'Loan Management Dashboard' => '[loan_management_dashboard]',
        'Borrower Application' => '[loan_borrower_application]',
    ];
}

/**
 * Get database tables configuration
 */
function bntm_lm_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'lm_borrowers' => "CREATE TABLE {$prefix}lm_borrowers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            borrower_name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50),
            email VARCHAR(100),
            address TEXT,
            id_type VARCHAR(50),
            id_number VARCHAR(100),
            id_file_url TEXT,
            notes TEXT,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",
        
        'lm_loans' => "CREATE TABLE {$prefix}lm_loans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            borrower_id BIGINT UNSIGNED NOT NULL,
            loan_number VARCHAR(50) UNIQUE NOT NULL,
            loan_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            interest_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            interest_type VARCHAR(20) DEFAULT 'flat',
            compound_frequency VARCHAR(20) DEFAULT 'monthly',
            is_diminishing TINYINT(1) DEFAULT 0,
            loan_term INT NOT NULL,
            term_unit VARCHAR(20) DEFAULT 'months',
            processing_fee DECIMAL(15,2) DEFAULT 0.00,
            total_interest DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            installment_amount DECIMAL(15,2) DEFAULT 0.00,
            start_date DATE NOT NULL,
            maturity_date DATE,
            status VARCHAR(20) DEFAULT 'pending',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_borrower (borrower_id),
            INDEX idx_status (status),
            INDEX idx_loan_number (loan_number)
        ) {$charset};",
        
        'lm_installments' => "CREATE TABLE {$prefix}lm_installments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loan_id BIGINT UNSIGNED NOT NULL,
            installment_number INT NOT NULL,
            due_date DATE NOT NULL,
            principal_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            interest_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            installment_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            paid_amount DECIMAL(15,2) DEFAULT 0.00,
            balance DECIMAL(15,2) DEFAULT 0.00,
            penalty_amount DECIMAL(15,2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'pending',
            paid_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_loan (loan_id),
            INDEX idx_status (status),
            INDEX idx_due_date (due_date)
        ) {$charset};",
        
        'lm_payments' => "CREATE TABLE {$prefix}lm_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loan_id BIGINT UNSIGNED NOT NULL,
            installment_id BIGINT UNSIGNED,
            payment_date DATE NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            payment_method VARCHAR(50) DEFAULT 'cash',
            reference_number VARCHAR(100),
            notes TEXT,
            recorded_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_loan (loan_id),
            INDEX idx_installment (installment_id)
        ) {$charset};",
        
        'lm_penalties' => "CREATE TABLE {$prefix}lm_penalties (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loan_id BIGINT UNSIGNED NOT NULL,
            installment_id BIGINT UNSIGNED NOT NULL,
            penalty_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            reason TEXT,
            applied_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_loan (loan_id),
            INDEX idx_installment (installment_id)
        ) {$charset};"
    ];
}

/**
 * Get shortcodes registration
 */
function bntm_lm_get_shortcodes() {
    return [
        'loan_management_dashboard' => 'bntm_shortcode_lm_dashboard',
        'loan_borrower_application' => 'bntm_shortcode_lm_borrower_application',
    ];
}

/**
 * Create tables function
 */
function bntm_lm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_lm_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// ========================================
// AJAX ACTIONS REGISTRATION
// ========================================

// Borrower Management
add_action('wp_ajax_lm_add_borrower', 'bntm_ajax_lm_add_borrower');
add_action('wp_ajax_lm_update_borrower', 'bntm_ajax_lm_update_borrower');
add_action('wp_ajax_lm_delete_borrower', 'bntm_ajax_lm_delete_borrower');
add_action('wp_ajax_lm_get_borrower', 'bntm_ajax_lm_get_borrower');

// Loan Management
add_action('wp_ajax_lm_create_loan', 'bntm_ajax_lm_create_loan');
add_action('wp_ajax_lm_update_loan', 'bntm_ajax_lm_update_loan');
add_action('wp_ajax_lm_approve_loan', 'bntm_ajax_lm_approve_loan');
add_action('wp_ajax_lm_cancel_loan', 'bntm_ajax_lm_cancel_loan');
add_action('wp_ajax_lm_get_loan_details', 'bntm_ajax_lm_get_loan_details');

// Payment Management
add_action('wp_ajax_lm_record_payment', 'bntm_ajax_lm_record_payment');
add_action('wp_ajax_lm_delete_payment', 'bntm_ajax_lm_delete_payment');

// Penalty Management
add_action('wp_ajax_lm_apply_penalty', 'bntm_ajax_lm_apply_penalty');
add_action('wp_ajax_lm_check_overdue', 'bntm_ajax_lm_check_overdue');

// Settings
add_action('wp_ajax_lm_save_settings', 'bntm_ajax_lm_save_settings');

// Finance Export
add_action('wp_ajax_lm_fn_export_payment', 'bntm_ajax_lm_fn_export_payment');
add_action('wp_ajax_lm_fn_revert_payment', 'bntm_ajax_lm_fn_revert_payment');

// ========================================
// MAIN DASHBOARD SHORTCODE
// ========================================

function bntm_shortcode_lm_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Loan Management system.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-lm-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=loans" class="bntm-tab <?php echo $active_tab === 'loans' ? 'active' : ''; ?>">
                Loans
            </a>
            <a href="?tab=borrowers" class="bntm-tab <?php echo $active_tab === 'borrowers' ? 'active' : ''; ?>">
                Borrowers
            </a>
            <a href="?tab=payments" class="bntm-tab <?php echo $active_tab === 'payments' ? 'active' : ''; ?>">
                Payments
            </a>
            <a href="?tab=reports" class="bntm-tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">
                Reports
            </a>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=finance" class="bntm-tab <?php echo $active_tab === 'finance' ? 'active' : ''; ?>">
                Finance Export
            </a>
            <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo lm_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'loans'): ?>
                <?php echo lm_loans_tab($business_id); ?>
            <?php elseif ($active_tab === 'borrowers'): ?>
                <?php echo lm_borrowers_tab($business_id); ?>
            <?php elseif ($active_tab === 'payments'): ?>
                <?php echo lm_payments_tab($business_id); ?>
            <?php elseif ($active_tab === 'reports'): ?>
                <?php echo lm_reports_tab($business_id); ?>
            <?php elseif ($active_tab === 'finance'): ?>
                <?php echo lm_finance_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo lm_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    /* Modal Styles */
    .lm-modal {
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
    
    .lm-modal-content {
        background-color: #fff;
        margin: 50px auto;
        padding: 0;
        border-radius: 8px;
        max-width: 700px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .lm-modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .lm-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }
    
    .lm-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #6b7280;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }
    
    .lm-modal-close:hover {
        background-color: #f3f4f6;
        color: #1f2937;
    }
    
    .lm-modal-body {
        padding: 25px;
    }
    
    .lm-modal-footer {
        padding: 15px 25px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    /* Status Badges */
    .lm-status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .lm-status-pending {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .lm-status-active {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .lm-status-completed {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .lm-status-overdue {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .lm-status-cancelled {
        background-color: #f3f4f6;
        color: #4b5563;
    }
    
    /* Info Cards */
    .lm-info-card {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .lm-info-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .lm-info-row:last-child {
        border-bottom: none;
    }
    
    .lm-info-label {
        color: #6b7280;
        font-weight: 500;
    }
    
    .lm-info-value {
        color: #1f2937;
        font-weight: 600;
    }
    
    /* Action Buttons */
    .lm-action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .lm-btn-icon {
        padding: 6px 12px;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    </style>
    <style>
/* Add to existing styles */
.payment-option-box {
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    padding: 15px;
    margin: 10px 0;
    transition: all 0.3s ease;
}

.payment-option-box.active {
    border-color: #3b82f6;
    background: #eff6ff;
}

#reduce_principal_info {
    animation: slideDown 0.3s ease;
}

#normal_payment_info {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
    <script>
    (function() {
        // Modal Functions
        window.lmOpenModal = function(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        };
        
        window.lmCloseModal = function(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        };
        
        // Close modal on outside click
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('lm-modal')) {
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        // Format currency
        window.lmFormatCurrency = function(amount) {
            return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        };
        
// Calculate loan totals
window.lmCalculateLoanTotals = function() {
    const loanAmount = parseFloat(document.getElementById('loan_amount')?.value || 0);
    const interestRate = parseFloat(document.getElementById('interest_rate')?.value || 0);
    const loanTerm = parseInt(document.getElementById('loan_term')?.value || 0);
    const processingFee = parseFloat(document.getElementById('processing_fee')?.value || 0);
    const interestType = document.getElementById('interest_type')?.value || 'flat';
    const compoundFreq = document.getElementById('compound_frequency')?.value || 'monthly';
    const termUnit = document.querySelector('select[name="term_unit"]')?.value || 'months';
    const isDiminishing = document.getElementById('is_diminishing')?.checked || false;
    
    let totalInterest = 0;
    let totalAmount = 0;
    let installmentAmount = 0;
    
    // Show/hide diminishing info
    const diminishingInfo = document.getElementById('diminishing_info');
    if (diminishingInfo) {
        diminishingInfo.style.display = isDiminishing ? 'block' : 'none';
    }
    
    if (loanAmount > 0 && interestRate > 0 && loanTerm > 0) {
        const rate = interestRate / 100;
        
        if (isDiminishing) {
            // DIMINISHING BALANCE CALCULATION
            // Calculate periodic interest rate
            let periodicRate = rate / 12; // Default to monthly
            if (termUnit === 'weeks') {
                periodicRate = rate / 52;
            }
            
            // Calculate using amortization formula
            // M = P * [r(1+r)^n] / [(1+r)^n - 1]
            const numerator = periodicRate * Math.pow(1 + periodicRate, loanTerm);
            const denominator = Math.pow(1 + periodicRate, loanTerm) - 1;
            
            if (denominator > 0) {
                installmentAmount = loanAmount * (numerator / denominator);
            } else {
                // Fallback for edge cases
                installmentAmount = loanAmount / loanTerm;
            }
            
            // Total amount to be paid
            totalAmount = (installmentAmount * loanTerm) + processingFee;
            totalInterest = totalAmount - loanAmount - processingFee;
            
        } else {
            // STANDARD CALCULATIONS (Non-Diminishing)
            if (interestType === 'flat') {
                // Flat rate: Interest = Principal × Rate × Time
                totalInterest = (loanAmount * interestRate * loanTerm) / 100;
                totalAmount = loanAmount + totalInterest + processingFee;
                installmentAmount = totalAmount / loanTerm;
                
            } else if (interestType === 'simple') {
                // Simple interest: I = P × r × t
                totalInterest = (loanAmount * interestRate * loanTerm) / 100;
                totalAmount = loanAmount + totalInterest + processingFee;
                installmentAmount = totalAmount / loanTerm;
                
            } else if (interestType === 'compound') {
                // Compound interest: A = P(1 + r/n)^(nt)
                const compoundPeriodsPerYear = {
                    'daily': 365,
                    'weekly': 52,
                    'biweekly': 26,
                    'monthly': 12,
                    'quarterly': 4,
                    'semiannually': 2,
                    'annually': 1
                };
                
                const n = compoundPeriodsPerYear[compoundFreq] || 12;
                
                // Convert loan term to years
                let timeInYears = loanTerm;
                if (termUnit === 'weeks') {
                    timeInYears = loanTerm / 52;
                } else if (termUnit === 'months') {
                    timeInYears = loanTerm / 12;
                }
                
                // A = P(1 + r/n)^(nt)
                const futureValue = loanAmount * Math.pow((1 + rate / n), (n * timeInYears));
                totalInterest = futureValue - loanAmount;
                totalAmount = loanAmount + totalInterest + processingFee;
                installmentAmount = totalAmount / loanTerm;
            }
        }
    }
    
    if (document.getElementById('total_interest_display')) {
        document.getElementById('total_interest_display').textContent = lmFormatCurrency(totalInterest);
    }
    if (document.getElementById('total_amount_display')) {
        document.getElementById('total_amount_display').textContent = lmFormatCurrency(totalAmount);
    }
    if (document.getElementById('installment_amount_display')) {
        document.getElementById('installment_amount_display').textContent = lmFormatCurrency(installmentAmount);
    }
};

// Toggle interest type options
window.toggleInterestOptions = function() {
    const interestType = document.getElementById('interest_type').value;
    const compoundGroup = document.getElementById('compound_frequency_group');
    const rateNote = document.getElementById('interest_rate_note');
    
    // Hide compound group by default
    compoundGroup.style.display = 'none';
    
    if (interestType === 'compound') {
        compoundGroup.style.display = 'block';
        rateNote.textContent = 'Annual percentage rate (compounded)';
    } else if (interestType === 'flat') {
        rateNote.textContent = 'Annual percentage rate (flat)';
    } else if (interestType === 'simple') {
        rateNote.textContent = 'Annual percentage rate (simple)';
    }
    
    lmCalculateLoanTotals();
};

// Preview payment impact (optional enhancement)
window.previewPaymentImpact = function() {
    const loanSelect = document.getElementById('payment_loan_id');
    const amount = parseFloat(document.getElementById('payment_amount').value || 0);
    const reducePrincipal = document.getElementById('reduce_principal')?.checked;
    const selectedOption = loanSelect.options[loanSelect.selectedIndex];
    const isDiminishing = selectedOption.getAttribute('data-diminishing') == '1';
    
    if (amount > 0 && isDiminishing && reducePrincipal) {
        // Could add a preview calculation here showing estimated savings
        console.log('Payment will reduce principal and recalculate future installments');
    }
};

// Attach to amount input
document.getElementById('payment_amount')?.addEventListener('change', previewPaymentImpact);
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Loan Management System', $content);
}

// ========================================
// TAB FUNCTIONS
// ========================================

/**
 * Overview Tab
 */
function lm_overview_tab($business_id) {
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $payments_table = $wpdb->prefix . 'lm_payments';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    $installments_table = $wpdb->prefix . 'lm_installments';
    
    // Get statistics
    $total_borrowers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $borrowers_table WHERE business_id = %d AND status = 'active'",
        $business_id
    ));
    
    $total_loans = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $loans_table WHERE business_id = %d",
        $business_id
    ));
    
    $active_loans = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $loans_table WHERE business_id = %d AND status = 'active'",
        $business_id
    ));
    
    $total_disbursed = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(loan_amount) FROM $loans_table WHERE business_id = %d AND status IN ('active', 'completed')",
        $business_id
    )) ?: 0;
    
    $total_outstanding = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(i.balance) FROM $installments_table i
         INNER JOIN $loans_table l ON i.loan_id = l.id
         WHERE l.business_id = %d AND i.status != 'paid'",
        $business_id
    )) ?: 0;
    
    $total_collected = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM $payments_table WHERE business_id = %d",
        $business_id
    )) ?: 0;
    
    $overdue_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT i.loan_id) FROM $installments_table i
         INNER JOIN $loans_table l ON i.loan_id = l.id
         WHERE l.business_id = %d AND i.status = 'overdue'",
        $business_id
    )) ?: 0;
    
    // Get recent loans
    $recent_loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, b.borrower_name 
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.business_id = %d
         ORDER BY l.created_at DESC
         LIMIT 5",
        $business_id
    ));
    
    // Get upcoming due installments
    $upcoming_due = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, l.loan_number, b.borrower_name
         FROM $installments_table i
         INNER JOIN $loans_table l ON i.loan_id = l.id
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.business_id = %d 
         AND i.status = 'pending'
         AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
         ORDER BY i.due_date ASC
         LIMIT 5",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="bntm-stats-row">
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Borrowers</h3>
                <p class="stat-number"><?php echo number_format($total_borrowers); ?></p>
                <span class="stat-label">Active borrowers</span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Loans</h3>
                <p class="stat-number"><?php echo number_format($total_loans); ?></p>
                <span class="stat-label"><?php echo number_format($active_loans); ?> active</span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Disbursed</h3>
                <p class="stat-number">₱<?php echo number_format($total_disbursed, 2); ?></p>
                <span class="stat-label">Loan principal</span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Outstanding Balance</h3>
                <p class="stat-number">₱<?php echo number_format($total_outstanding, 2); ?></p>
                <span class="stat-label">To be collected</span>
            </div>
        </div>
    </div>
    
    <div class="bntm-stats-row" style="margin-top: 20px;">
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Total Collected</h3>
                <p class="stat-number">₱<?php echo number_format($total_collected, 2); ?></p>
                <span class="stat-label">All payments</span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Overdue Loans</h3>
                <p class="stat-number"><?php echo number_format($overdue_count); ?></p>
                <span class="stat-label">Require attention</span>
            </div>
        </div>
        
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Collection Rate</h3>
                <p class="stat-number"><?php 
                    $total_expected = $total_disbursed + ($total_disbursed * 0.1); // Rough estimate
                    $rate = $total_expected > 0 ? ($total_collected / $total_expected) * 100 : 0;
                    echo number_format($rate, 1); 
                ?>%</p>
                <span class="stat-label">Recovery rate</span>
            </div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
        <div class="bntm-form-section">
            <h3>Recent Loans</h3>
            <?php if (empty($recent_loans)): ?>
                <p style="color: #6b7280;">No loans created yet.</p>
            <?php else: ?>
                <table class="bntm-table">
                    <thead>
                        <tr>
                            <th>Loan #</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_loans as $loan): ?>
                        <tr>
                            <td><?php echo esc_html($loan->loan_number); ?></td>
                            <td><?php echo esc_html($loan->borrower_name); ?></td>
                            <td>₱<?php echo number_format($loan->loan_amount, 2); ?></td>
                            <td><span class="lm-status-badge lm-status-<?php echo $loan->status; ?>"><?php echo ucfirst($loan->status); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="bntm-form-section">
            <h3>Upcoming Due (Next 7 Days)</h3>
            <?php if (empty($upcoming_due)): ?>
                <p style="color: #6b7280;">No upcoming installments.</p>
            <?php else: ?>
                <table class="bntm-table">
                    <thead>
                        <tr>
                            <th>Due Date</th>
                            <th>Loan #</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcoming_due as $inst): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($inst->due_date)); ?></td>
                            <td><?php echo esc_html($inst->loan_number); ?></td>
                            <td><?php echo esc_html($inst->borrower_name); ?></td>
                            <td>₱<?php echo number_format($inst->installment_amount, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bntm-form-section" style="margin-top: 30px;">
        <h3>Quick Actions</h3>
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <button onclick="window.location.href='?tab=loans'" class="bntm-btn-primary">
                Create New Loan
            </button>
            <button onclick="window.location.href='?tab=borrowers'" class="bntm-btn-secondary">
                Add Borrower
            </button>
            <button onclick="window.location.href='?tab=payments'" class="bntm-btn-secondary">
                Record Payment
            </button>
            <button onclick="window.location.href='?tab=reports'" class="bntm-btn-secondary">
                View Reports
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Loans Tab
 */
function lm_loans_tab($business_id) {
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    
    // Get loans with borrower info
    $loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, b.borrower_name, b.contact_number,
        (SELECT COUNT(*) FROM {$wpdb->prefix}lm_installments WHERE loan_id = l.id AND status = 'paid') as paid_installments,
        (SELECT COUNT(*) FROM {$wpdb->prefix}lm_installments WHERE loan_id = l.id) as total_installments
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.business_id = %d
         ORDER BY l.created_at DESC",
        $business_id
    ));
    
    // Get active borrowers for loan creation
    $borrowers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $borrowers_table WHERE business_id = %d AND status = 'active' ORDER BY borrower_name",
        $business_id
    ));
    
    $nonce = wp_create_nonce('lm_loan_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Loan Management</h3>
            <button onclick="lmOpenModal('createLoanModal')" class="bntm-btn-primary">
                Create New Loan
            </button>
        </div>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="loan-search" placeholder="Search loans..." class="bntm-input" style="max-width: 300px;">
        </div>
        
        <table class="bntm-table" id="loans-table">
            <thead>
                <tr>
                    <th>Loan #</th>
                    <th>Borrower</th>
                    <th>Amount</th>
                    <th>Term</th>
                    <th>Start Date</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loans)): ?>
                <tr><td colspan="8" style="text-align:center;">No loans found.</td></tr>
                <?php else: foreach ($loans as $loan): ?>
                <tr data-search="<?php echo esc_attr(strtolower($loan->loan_number . ' ' . $loan->borrower_name)); ?>">
                    <td><strong><?php echo esc_html($loan->loan_number); ?></strong></td>
                    <td>
                        <?php echo esc_html($loan->borrower_name); ?>
                        <?php if ($loan->contact_number): ?>
                        <br><small style="color: #6b7280;"><?php echo esc_html($loan->contact_number); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>₱<?php echo number_format($loan->loan_amount, 2); ?></strong>
                        <br><small style="color: #6b7280;">Total: ₱<?php echo number_format($loan->total_amount, 2); ?></small>
                    </td>
                    <td><?php echo $loan->loan_term . ' ' . $loan->term_unit; ?></td>
                    <td><?php echo date('M d, Y', strtotime($loan->start_date)); ?></td>
                    <td>
                        <div style="font-size: 13px; color: #6b7280;">
                            <?php echo $loan->paid_installments; ?> / <?php echo $loan->total_installments; ?> paid
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 4px;">
                            <div style="background: #10b981; height: 100%; width: <?php echo $loan->total_installments > 0 ? ($loan->paid_installments / $loan->total_installments * 100) : 0; ?>%;"></div>
                        </div>
                    </td>
                    <td><span class="lm-status-badge lm-status-<?php echo $loan->status; ?>"><?php echo ucfirst($loan->status); ?></span></td>
                    <td>
                        <div class="lm-action-buttons">
                            <button onclick="viewLoanDetails(<?php echo $loan->id; ?>)" class="bntm-btn-small bntm-btn-secondary lm-btn-icon" title="View Details">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            <button onclick="generateLoanPDF(<?php echo $loan->id; ?>)" class="bntm-btn-small bntm-btn-secondary lm-btn-icon" title="Generate PDF">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                            </button>
                            <?php if ($loan->status === 'pending'): ?>
                            <button onclick="approveLoan(<?php echo $loan->id; ?>)" class="bntm-btn-small bntm-btn-success lm-btn-icon" title="Approve">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                            <?php if (in_array($loan->status, ['pending', 'active'])): ?>
                            <button onclick="cancelLoan(<?php echo $loan->id; ?>)" class="bntm-btn-small bntm-btn-danger lm-btn-icon" title="Cancel">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Create Loan Modal -->
    <div id="createLoanModal" class="lm-modal">
        <div class="lm-modal-content">
            <div class="lm-modal-header">
                <h3>Create New Loan</h3>
                <button class="lm-modal-close" onclick="lmCloseModal('createLoanModal')">&times;</button>
            </div>
            <div class="lm-modal-body">
                <form id="createLoanForm" class="bntm-form">
                    <div class="bntm-form-group">
                        <label>Select Borrower *</label>
                        <select name="borrower_id" required class="bntm-input">
                            <option value="">-- Select Borrower --</option>
                            <?php foreach ($borrowers as $borrower): ?>
                            <option value="<?php echo $borrower->id; ?>">
                                <?php echo esc_html($borrower->borrower_name); ?>
                                <?php if ($borrower->contact_number): ?>
                                - <?php echo esc_html($borrower->contact_number); ?>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Don't see the borrower? <a href="?tab=borrowers">Add new borrower</a></small>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Loan Amount (₱) *</label>
                            <input type="number" name="loan_amount" id="loan_amount" step="0.01" required class="bntm-input" onchange="lmCalculateLoanTotals()">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Processing Fee (₱)</label>
                            <input type="number" name="processing_fee" id="processing_fee" step="0.01" value="0" class="bntm-input" onchange="lmCalculateLoanTotals()">
                        </div>
                    </div>
                    <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Interest Type *</label>
                        <select name="interest_type" id="interest_type" required class="bntm-input" onchange="toggleInterestOptions()">
                            <option value="flat">Flat Rate</option>
                            <option value="simple">Simple Interest</option>
                            <option value="compound">Compound Interest</option>
                            <option value="diminishing">Diminishing Balance</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Interest Rate (%) *</label>
                        <input type="number" name="interest_rate" id="interest_rate" step="0.01" required class="bntm-input" onchange="lmCalculateLoanTotals()">
                        <small id="interest_rate_note">Annual percentage rate</small>
                    </div>
                </div>
                
                <!-- Compound Frequency (only shown for compound interest) -->
                <div class="bntm-form-group" id="compound_frequency_group" style="display: none;">
                    <label>Compounding Frequency *</label>
                    <select name="compound_frequency" id="compound_frequency" class="bntm-input" onchange="lmCalculateLoanTotals()">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="biweekly">Bi-Weekly (Every 2 weeks)</option>
                        <option value="monthly" selected>Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="semiannually">Semi-Annually</option>
                        <option value="annually">Annually</option>
                    </select>
                    <small>How often interest is calculated and added to principal</small>
                </div>
                

                <!-- Diminishing Balance Toggle -->
                <div class="bntm-form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="is_diminishing" id="is_diminishing" value="1" onchange="lmCalculateLoanTotals()">
                        <strong>Use Diminishing Balance Method</strong>
                    </label>
                    <small>When enabled, interest is calculated on the remaining principal balance each period. Earlier payments have more interest, later payments have more principal.</small>
                </div>
                
                <!-- Diminishing Balance Info -->
                <div class="bntm-form-group" id="diminishing_info" style="display: none;">
                    <div style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: 12px; border-radius: 4px;">
                        <strong>Diminishing Balance Active:</strong>
                        <ul style="margin: 5px 0 0 20px; padding: 0;">
                            <li>Interest calculated on remaining balance each period</li>
                            <li>Fixed installment amount, but interest/principal ratio changes</li>
                            <li>Total interest is typically lower than flat rate</li>
                        </ul>
                    </div>
                </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Loan Term *</label>
                            <input type="number" name="loan_term" id="loan_term" required class="bntm-input" onchange="lmCalculateLoanTotals()">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Term Unit *</label>
                            <select name="term_unit" required class="bntm-input">
                                <option value="weeks">Weeks</option>
                                <option value="months" selected>Months</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required class="bntm-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="lm-info-card">
                        <h4 style="margin-top: 0; margin-bottom: 15px; font-size: 15px;">Loan Summary</h4>
                        <div class="lm-info-row">
                            <span class="lm-info-label">Total Interest:</span>
                            <span class="lm-info-value" id="total_interest_display">₱0.00</span>
                        </div>
                        <div class="lm-info-row">
                            <span class="lm-info-label">Total Amount to Repay:</span>
                            <span class="lm-info-value" id="total_amount_display">₱0.00</span>
                        </div>
                        <div class="lm-info-row">
                            <span class="lm-info-label">Installment Amount:</span>
                            <span class="lm-info-value" id="installment_amount_display">₱0.00</span>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="3" class="bntm-input" placeholder="Additional notes or remarks"></textarea>
                    </div>
                </form>
            </div>
            <div class="lm-modal-footer">
                <button type="button" class="bntm-btn-secondary" onclick="lmCloseModal('createLoanModal')">Cancel</button>
                <button type="button" class="bntm-btn-primary" onclick="submitCreateLoan()">Create Loan</button>
            </div>
        </div>
    </div>
    
    <!-- Loan Details Modal -->
    <div id="loanDetailsModal" class="lm-modal">
        <div class="lm-modal-content" style="max-width: 900px;">
            <div class="lm-modal-header">
                <h3>Loan Details</h3>
                <button class="lm-modal-close" onclick="lmCloseModal('loanDetailsModal')">&times;</button>
            </div>
            <div class="lm-modal-body" id="loanDetailsContent">
                Loading...
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        // Add to existing modal scripts
        window.toggleCompoundOptions = function() {
            const interestType = document.getElementById('interest_type').value;
            const compoundGroup = document.getElementById('compound_frequency_group');
            
            if (interestType === 'compound') {
                compoundGroup.style.display = 'block';
            } else {
                compoundGroup.style.display = 'none';
            }
            
            lmCalculateLoanTotals();
        };
        // Search functionality
        document.getElementById('loan-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#loans-table tbody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Submit create loan
        window.submitCreateLoan = function() {
            const form = document.getElementById('createLoanForm');
            const formData = new FormData(form);
            formData.append('action', 'lm_create_loan');
            formData.append('_ajax_nonce', nonce);
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Creating...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Create Loan';
                }
            })
            .catch(err => {
                alert('Error creating loan');
                btn.disabled = false;
                btn.textContent = 'Create Loan';
            });
        };
        
        // View loan details
        window.viewLoanDetails = function(loanId) {
            const formData = new FormData();
            formData.append('action', 'lm_get_loan_details');
            formData.append('loan_id', loanId);
            formData.append('_ajax_nonce', nonce);
            
            document.getElementById('loanDetailsContent').innerHTML = 'Loading...';
            lmOpenModal('loanDetailsModal');
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('loanDetailsContent').innerHTML = json.data.html;
                } else {
                    document.getElementById('loanDetailsContent').innerHTML = '<p>Error loading details</p>';
                }
            });
        };
        
        // Approve loan
        window.approveLoan = function(loanId) {
            if (!confirm('Approve this loan and activate the repayment schedule?')) return;
            
            const formData = new FormData();
            formData.append('action', 'lm_approve_loan');
            formData.append('loan_id', loanId);
            formData.append('_ajax_nonce', nonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
            });
        };
        
        // Cancel loan
        window.cancelLoan = function(loanId) {
            if (!confirm('Cancel this loan? This action cannot be undone.')) return;
            
            const formData = new FormData();
            formData.append('action', 'lm_cancel_loan');
            formData.append('loan_id', loanId);
            formData.append('_ajax_nonce', nonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
            });
        };
        
        window.generateLoanPDF = function(loanId) {
            // Open PDF in new window
            window.open(ajaxurl + '?action=lm_generate_pdf&loan_id=' + loanId + '&_ajax_nonce=' + '<?php echo wp_create_nonce('lm_pdf_nonce'); ?>', '_blank');
        };
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Borrowers Tab
 */
function lm_borrowers_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'lm_borrowers';
    
    $borrowers = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*,
        (SELECT COUNT(*) FROM {$wpdb->prefix}lm_loans WHERE borrower_id = b.id) as total_loans,
        (SELECT COUNT(*) FROM {$wpdb->prefix}lm_loans WHERE borrower_id = b.id AND status = 'active') as active_loans
         FROM $table b
         WHERE b.business_id = %d
         ORDER BY b.created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('lm_borrower_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Borrower Management</h3>
            <button onclick="lmOpenModal('addBorrowerModal')" class="bntm-btn-primary">
                Add New Borrower
            </button>
        </div>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="borrower-search" placeholder="Search borrowers..." class="bntm-input" style="max-width: 300px;">
        </div>
        
        <table class="bntm-table" id="borrowers-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>ID Type</th>
                    <th>Total Loans</th>
                    <th>Active Loans</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($borrowers)): ?>
                <tr><td colspan="7" style="text-align:center;">No borrowers found.</td></tr>
                <?php else: foreach ($borrowers as $borrower): ?>
                <tr data-search="<?php echo esc_attr(strtolower($borrower->borrower_name . ' ' . $borrower->contact_number . ' ' . $borrower->email)); ?>">
                    <td>
                        <strong><?php echo esc_html($borrower->borrower_name); ?></strong>
                        <?php if ($borrower->email): ?>
                        <br><small style="color: #6b7280;"><?php echo esc_html($borrower->email); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($borrower->contact_number ?: '-'); ?></td>
                    <td>
                        <?php if ($borrower->id_type): ?>
                        <?php echo esc_html($borrower->id_type); ?>
                        <?php if ($borrower->id_number): ?>
                        <br><small style="color: #6b7280;"><?php echo esc_html($borrower->id_number); ?></small>
                        <?php endif; ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($borrower->total_loans); ?></td>
                    <td><?php echo number_format($borrower->active_loans); ?></td>
                    <td><span class="lm-status-badge lm-status-<?php echo $borrower->status; ?>"><?php echo ucfirst($borrower->status); ?></span></td>
                    <td>
                        <div class="lm-action-buttons">
                            <button onclick="viewBorrower(<?php echo $borrower->id; ?>)" class="bntm-btn-small bntm-btn-secondary lm-btn-icon" title="View">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            <button onclick="editBorrower(<?php echo $borrower->id; ?>)" class="bntm-btn-small bntm-btn-secondary lm-btn-icon" title="Edit">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <?php if ($borrower->active_loans == 0): ?>
                            <button onclick="deleteBorrower(<?php echo $borrower->id; ?>)" class="bntm-btn-small bntm-btn-danger lm-btn-icon" title="Delete">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add/Edit Borrower Modal -->
    <div id="addBorrowerModal" class="lm-modal">
        <div class="lm-modal-content">
            <div class="lm-modal-header">
                <h3 id="borrowerModalTitle">Add New Borrower</h3>
                <button class="lm-modal-close" onclick="lmCloseModal('addBorrowerModal')">&times;</button>
            </div>
            <div class="lm-modal-body">
                <form id="borrowerForm" class="bntm-form">
                    <input type="hidden" name="borrower_id" id="borrower_id">
                    
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="borrower_name" id="borrower_name" required class="bntm-input">
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Contact Number</label>
                            <input type="text" name="contact_number" id="contact_number" class="bntm-input">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="email_borrower" class="bntm-input">
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Address</label>
                        <textarea name="address" id="address" rows="2" class="bntm-input"></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>ID Type</label>
                            <select name="id_type" id="id_type" class="bntm-input">
                                <option value="">-- Select --</option>
                                <option value="National ID">National ID</option>
                                <option value="Driver's License">Driver's License</option>
                                <option value="Passport">Passport</option>
                                <option value="SSS">SSS</option>
                                <option value="UMID">UMID</option>
                                <option value="Voter's ID">Voter's ID</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>ID Number</label>
                            <input type="text" name="id_number" id="id_number" class="bntm-input">
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Notes / Remarks</label>
                        <textarea name="notes" id="notes_borrower" rows="3" class="bntm-input"></textarea>
                    </div>
                </form>
            </div>
            <div class="lm-modal-footer">
                <button type="button" class="bntm-btn-secondary" onclick="lmCloseModal('addBorrowerModal')">Cancel</button>
                <button type="button" class="bntm-btn-primary" onclick="submitBorrower()">Save Borrower</button>
            </div>
        </div>
    </div>
    
    <!-- Borrower Details Modal -->
    <div id="borrowerDetailsModal" class="lm-modal">
        <div class="lm-modal-content">
            <div class="lm-modal-header">
                <h3>Borrower Details</h3>
                <button class="lm-modal-close" onclick="lmCloseModal('borrowerDetailsModal')">&times;</button>
            </div>
            <div class="lm-modal-body" id="borrowerDetailsContent">
                Loading...
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Search
        document.getElementById('borrower-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#borrowers-table tbody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Submit borrower
        window.submitBorrower = function() {
            const form = document.getElementById('borrowerForm');
            const formData = new FormData(form);
            const borrowerId = document.getElementById('borrower_id').value;
            
            formData.append('action', borrowerId ? 'lm_update_borrower' : 'lm_add_borrower');
            formData.append('_ajax_nonce', nonce);
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Save Borrower';
                }
            })
            .catch(err => {
                alert('Error saving borrower');
                btn.disabled = false;
                btn.textContent = 'Save Borrower';
            });
        };
        
        // Edit borrower
        window.editBorrower = function(borrowerId) {
            const formData = new FormData();
            formData.append('action', 'lm_get_borrower');
            formData.append('borrower_id', borrowerId);
            formData.append('_ajax_nonce', nonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const b = json.data.borrower;
                    document.getElementById('borrowerModalTitle').textContent = 'Edit Borrower';
                    document.getElementById('borrower_id').value = b.id;
                    document.getElementById('borrower_name').value = b.borrower_name;
                    document.getElementById('contact_number').value = b.contact_number || '';
                    document.getElementById('email_borrower').value = b.email || '';
                    document.getElementById('address').value = b.address || '';
                    document.getElementById('id_type').value = b.id_type || '';
                    document.getElementById('id_number').value = b.id_number || '';
                    document.getElementById('notes_borrower').value = b.notes || '';
                    lmOpenModal('addBorrowerModal');
                }
            });
        };
        
        // View borrower
        window.viewBorrower = function(borrowerId) {
            const formData = new FormData();
            formData.append('action', 'lm_get_borrower');
            formData.append('borrower_id', borrowerId);
            formData.append('view_mode', '1');
            formData.append('_ajax_nonce', nonce);
            
            document.getElementById('borrowerDetailsContent').innerHTML = 'Loading...';
            lmOpenModal('borrowerDetailsModal');
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('borrowerDetailsContent').innerHTML = json.data.html;
                }
            });
        };
        
        // Delete borrower
        window.deleteBorrower = function(borrowerId) {
            if (!confirm('Delete this borrower? This cannot be undone.')) return;
            
            const formData = new FormData();
            formData.append('action', 'lm_delete_borrower');
            formData.append('borrower_id', borrowerId);
            formData.append('_ajax_nonce', nonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
            });
        };
        
        // Reset form when opening add modal
        window.addEventListener('click', function(e) {
            if (e.target.textContent === 'Add New Borrower') {
                document.getElementById('borrowerModalTitle').textContent = 'Add New Borrower';
                document.getElementById('borrowerForm').reset();
                document.getElementById('borrower_id').value = '';
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Payments Tab
 */
function lm_payments_tab($business_id) {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'lm_payments';
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    $installments_table = $wpdb->prefix . 'lm_installments';
    
    // Get all payments
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, l.loan_number, b.borrower_name, u.display_name as recorded_by_name
         FROM $payments_table p
         INNER JOIN $loans_table l ON p.loan_id = l.id
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         LEFT JOIN {$wpdb->prefix}users u ON p.recorded_by = u.ID
         WHERE p.business_id = %d
         ORDER BY p.payment_date DESC, p.created_at DESC",
        $business_id
    ));
    
    // Get active loans for payment recording
    $active_loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, b.borrower_name,
         (SELECT SUM(balance) FROM $installments_table WHERE loan_id = l.id AND status != 'paid') as total_balance
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.business_id = %d AND l.status = 'active'
         ORDER BY b.borrower_name",
        $business_id
    ));
    
    $nonce = wp_create_nonce('lm_payment_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Payment Records</h3>
            <button onclick="lmOpenModal('recordPaymentModal')" class="bntm-btn-primary">
                Record Payment
            </button>
        </div>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="payment-search" placeholder="Search payments..." class="bntm-input" style="max-width: 300px;">
        </div>
        
        <table class="bntm-table" id="payments-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Loan #</th>
                    <th>Borrower</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Recorded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="8" style="text-align:center;">No payments recorded yet.</td></tr>
                <?php else: foreach ($payments as $payment): ?>
                <tr data-search="<?php echo esc_attr(strtolower($payment->loan_number . ' ' . $payment->borrower_name . ' ' . $payment->reference_number)); ?>">
                    <td><?php echo date('M d, Y', strtotime($payment->payment_date)); ?></td>
                    <td><strong><?php echo esc_html($payment->loan_number); ?></strong></td>
                    <td><?php echo esc_html($payment->borrower_name); ?></td>
                    <td><strong style="color: #059669;">₱<?php echo number_format($payment->amount, 2); ?></strong></td>
                    <td><?php echo esc_html(ucfirst($payment->payment_method)); ?></td>
                    <td><?php echo esc_html($payment->reference_number ?: '-'); ?></td>
                    <td><?php echo esc_html($payment->recorded_by_name ?: 'System'); ?></td>
                    <td>
                        <button onclick="deletePayment(<?php echo $payment->id; ?>)" class="bntm-btn-small bntm-btn-danger lm-btn-icon" title="Delete">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="lm-modal">
    <div class="lm-modal-content">
        <div class="lm-modal-header">
            <h3>Record Payment</h3>
            <button class="lm-modal-close" onclick="lmCloseModal('recordPaymentModal')">&times;</button>
        </div>
        <div class="lm-modal-body">
            <form id="recordPaymentForm" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Select Loan *</label>
                    <select name="loan_id" id="payment_loan_id" required class="bntm-input" onchange="loadLoanInstallments()">
                        <option value="">-- Select Loan --</option>
                        <?php foreach ($active_loans as $loan): ?>
                        <option value="<?php echo $loan->id; ?>" 
                                data-balance="<?php echo $loan->total_balance; ?>"
                                data-diminishing="<?php echo $loan->is_diminishing; ?>">
                            <?php echo esc_html($loan->loan_number); ?> - <?php echo esc_html($loan->borrower_name); ?>
                            (Balance: ₱<?php echo number_format($loan->total_balance, 2); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Diminishing Balance Options (only shown for diminishing loans) -->
                <div id="diminishing_payment_options" style="display: none;">
                    <div class="bntm-form-group">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="reduce_principal" id="reduce_principal" value="1" onchange="toggleReducePrincipalInfo()">
                            <strong>Reduce Principal & Recalculate Future Installments</strong>
                        </label>
                        <small>When enabled, this payment will reduce the principal and recalculate all future installments with lower interest.</small>
                    </div>
                    
                    <div id="reduce_principal_info" style="display: none; margin-top: 10px;">
                        <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 12px; border-radius: 4px;">
                            <strong>✅ Principal Reduction Active:</strong>
                            <ul style="margin: 5px 0 0 20px; padding: 0; font-size: 13px;">
                                <li>Your payment will reduce the loan principal</li>
                                <li>All future installments will be recalculated</li>
                                <li>You'll pay less interest on remaining installments</li>
                                <li>This helps you save money over the loan term!</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div id="normal_payment_info" style="display: none; margin-top: 10px;">
                        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; border-radius: 4px;">
                            <strong>ℹ️ Standard Payment:</strong>
                            <ul style="margin: 5px 0 0 20px; padding: 0; font-size: 13px;">
                                <li>Payment applied to current installments only</li>
                                <li>Future installments remain unchanged</li>
                                <li>Good for regular scheduled payments</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div id="installmentsSection" style="display: none;">
                    <div class="lm-info-card">
                        <h4 style="margin-top: 0; margin-bottom: 15px; font-size: 15px;">Unpaid Installments</h4>
                        <div id="installmentsList"></div>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Payment Date *</label>
                        <input type="date" name="payment_date" required class="bntm-input" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Amount (₱) *</label>
                        <input type="number" name="amount" id="payment_amount" step="0.01" required class="bntm-input">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required class="bntm-input">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="check">Check</option>
                            <option value="online">Online Payment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Reference Number</label>
                        <input type="text" name="reference_number" class="bntm-input" placeholder="Optional">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="2" class="bntm-input" placeholder="Optional notes"></textarea>
                </div>
            </form>
        </div>
        <div class="lm-modal-footer">
            <button type="button" class="bntm-btn-secondary" onclick="lmCloseModal('recordPaymentModal')">Cancel</button>
            <button type="button" class="bntm-btn-primary" onclick="submitPayment()">Record Payment</button>
        </div>
    </div>
</div>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Search
        document.getElementById('payment-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#payments-table tbody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
// Load loan installments
window.loadLoanInstallments = function() {
    const loanSelect = document.getElementById('payment_loan_id');
    const loanId = loanSelect.value;
    
    if (!loanId) {
        document.getElementById('installmentsSection').style.display = 'none';
        document.getElementById('diminishing_payment_options').style.display = 'none';
        return;
    }
    
    const selectedOption = loanSelect.options[loanSelect.selectedIndex];
    const isDiminishing = selectedOption.getAttribute('data-diminishing') == '1';
    
    // Show/hide diminishing options
    if (isDiminishing) {
        document.getElementById('diminishing_payment_options').style.display = 'block';
        // Reset and show normal payment info by default
        document.getElementById('reduce_principal').checked = false;
        toggleReducePrincipalInfo();
    } else {
        document.getElementById('diminishing_payment_options').style.display = 'none';
    }
    
    const formData = new FormData();
    formData.append('action', 'lm_get_loan_details');
    formData.append('loan_id', loanId);
    formData.append('_ajax_nonce', nonce);
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success && json.data.installments) {
            const unpaid = json.data.installments.filter(i => i.status !== 'paid');
            
            if (unpaid.length > 0) {
                let html = '<table class="bntm-table" style="font-size: 13px;"><thead><tr><th>#</th><th>Due Date</th><th>Principal</th><th>Interest</th><th>Amount</th><th>Balance</th></tr></thead><tbody>';
                unpaid.forEach(inst => {
                    html += `<tr>
                        <td>${inst.installment_number}</td>
                        <td>${inst.due_date_formatted}</td>
                        <td>₱${parseFloat(inst.principal_amount).toFixed(2)}</td>
                        <td>₱${parseFloat(inst.interest_amount).toFixed(2)}</td>
                        <td>₱${parseFloat(inst.installment_amount).toFixed(2)}</td>
                        <td><strong>₱${parseFloat(inst.balance).toFixed(2)}</strong></td>
                    </tr>`;
                });
                html += '</tbody></table>';
                document.getElementById('installmentsList').innerHTML = html;
                document.getElementById('installmentsSection').style.display = 'block';
            } else {
                document.getElementById('installmentsSection').style.display = 'none';
            }
        }
    });
};

// Toggle reduce principal info
window.toggleReducePrincipalInfo = function() {
    const isChecked = document.getElementById('reduce_principal').checked;
    const reducePrincipalInfo = document.getElementById('reduce_principal_info');
    const normalPaymentInfo = document.getElementById('normal_payment_info');
    
    if (isChecked) {
        reducePrincipalInfo.style.display = 'block';
        normalPaymentInfo.style.display = 'none';
    } else {
        reducePrincipalInfo.style.display = 'none';
        normalPaymentInfo.style.display = 'block';
    }
};
        
        // Submit payment
        window.submitPayment = function() {
            const form = document.getElementById('recordPaymentForm');
            const formData = new FormData(form);
            formData.append('action', 'lm_record_payment');
            formData.append('_ajax_nonce', nonce);
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Recording...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Record Payment';
                }
            })
            .catch(err => {
                alert('Error recording payment');
                btn.disabled = false;
                btn.textContent = 'Record Payment';
            });
        };
        
        // Delete payment
        window.deletePayment = function(paymentId) {
            if (!confirm('Delete this payment record? This will restore the loan balance.')) return;
            
            const formData = new FormData();
            formData.append('action', 'lm_delete_payment');
            formData.append('payment_id', paymentId);
            formData.append('_ajax_nonce', nonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
            });
        };
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Reports Tab
 */
function lm_reports_tab($business_id) {
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    $installments_table = $wpdb->prefix . 'lm_installments';
    $payments_table = $wpdb->prefix . 'lm_payments';
    
    // Active Loans Report
    $active_loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, b.borrower_name,
         (SELECT SUM(balance) FROM $installments_table WHERE loan_id = l.id AND status != 'paid') as outstanding_balance,
         (SELECT SUM(paid_amount) FROM $installments_table WHERE loan_id = l.id) as total_paid
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.business_id = %d AND l.status = 'active'
         ORDER BY l.start_date DESC",
        $business_id
    ));
    
    // Overdue Loans Report
    $overdue_loans = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT l.*, b.borrower_name, b.contact_number,
         (SELECT MIN(due_date) FROM $installments_table WHERE loan_id = l.id AND status = 'overdue') as earliest_overdue,
         (SELECT COUNT(*) FROM $installments_table WHERE loan_id = l.id AND status = 'overdue') as overdue_count,
         (SELECT SUM(installment_amount + penalty_amount) FROM $installments_table WHERE loan_id = l.id AND status = 'overdue') as overdue_amount
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         INNER JOIN $installments_table i ON l.id = i.loan_id
         WHERE l.business_id = %d AND l.status = 'active' AND i.status = 'overdue'
         ORDER BY earliest_overdue ASC",
        $business_id
    ));
    
    // Collection Summary
    $today = date('Y-m-d');
    $this_month_start = date('Y-m-01');
    $this_month_end = date('Y-m-t');
    
    $today_collection = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM $payments_table WHERE business_id = %d AND payment_date = %s",
        $business_id, $today
    ));
    
    $month_collection = $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM $payments_table WHERE business_id = %d AND payment_date BETWEEN %s AND %s",
        $business_id, $this_month_start, $this_month_end
    ));
    
    // Due Today
    $due_today = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, l.loan_number, b.borrower_name, b.contact_number
         FROM $installments_table i
         INNER JOIN $loans_table l ON i.loan_id = l.id
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.business_id = %d AND i.status = 'pending' AND i.due_date = %s
         ORDER BY i.installment_number",
        $business_id, $today
    ));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Reports Dashboard</h3>
        
        <div class="bntm-stats-row" style="margin-bottom: 30px;">
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>Today's Collection</h3>
                    <p class="stat-number">₱<?php echo number_format($today_collection, 2); ?></p>
                    <span class="stat-label"><?php echo date('F d, Y'); ?></span>
                </div>
            </div>
            
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>This Month</h3>
                    <p class="stat-number">₱<?php echo number_format($month_collection, 2); ?></p>
                    <span class="stat-label"><?php echo date('F Y'); ?></span>
                </div>
            </div>
            
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>Due Today</h3>
                    <p class="stat-number"><?php echo count($due_today); ?></p>
                    <span class="stat-label">Installments</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Active Loans Summary</h3>
        <?php if (empty($active_loans)): ?>
            <p style="color: #6b7280;">No active loans.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Borrower</th>
                        <th>Principal</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_principal = 0;
                    $total_amount = 0;
                    $total_paid = 0;
                    $total_outstanding = 0;
                    
                    foreach ($active_loans as $loan): 
                        $total_principal += $loan->loan_amount;
                        $total_amount += $loan->total_amount;
                        $total_paid += $loan->total_paid;
                        $total_outstanding += $loan->outstanding_balance;
                        $progress = $loan->total_amount > 0 ? ($loan->total_paid / $loan->total_amount * 100) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($loan->loan_number); ?></strong></td>
                        <td><?php echo esc_html($loan->borrower_name); ?></td>
                        <td>₱<?php echo number_format($loan->loan_amount, 2); ?></td>
                        <td>₱<?php echo number_format($loan->total_amount, 2); ?></td>
                        <td style="color: #059669;">₱<?php echo number_format($loan->total_paid, 2); ?></td>
                        <td style="color: #dc2626;">₱<?php echo number_format($loan->outstanding_balance, 2); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="flex: 1; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: #10b981; height: 100%; width: <?php echo $progress; ?>%;"></div>
                                </div>
                                <span style="font-size: 12px; color: #6b7280; min-width: 40px;"><?php echo number_format($progress, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background: #f9fafb; font-weight: 600;">
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td>₱<?php echo number_format($total_principal, 2); ?></td>
                        <td>₱<?php echo number_format($total_amount, 2); ?></td>
                        <td style="color: #059669;">₱<?php echo number_format($total_paid, 2); ?></td>
                        <td style="color: #dc2626;">₱<?php echo number_format($total_outstanding, 2); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="bntm-form-section">
        <h3 style="color: #dc2626;">Overdue Loans</h3>
        <?php if (empty($overdue_loans)): ?>
            <p style="color: #059669;">No overdue loans. Great!</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Borrower</th>
                        <th>Contact</th>
                        <th>Earliest Overdue</th>
                        <th>Overdue Count</th>
                        <th>Overdue Amount</th>
                        <th>Days Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdue_loans as $loan): 
                        $days_overdue = (strtotime($today) - strtotime($loan->earliest_overdue)) / 86400;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($loan->loan_number); ?></strong></td>
                        <td><?php echo esc_html($loan->borrower_name); ?></td>
                        <td><?php echo esc_html($loan->contact_number ?: '-'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($loan->earliest_overdue)); ?></td>
                        <td><span class="lm-status-badge lm-status-overdue"><?php echo $loan->overdue_count; ?></span></td>
                        <td style="color: #dc2626; font-weight: 600;">₱<?php echo number_format($loan->overdue_amount, 2); ?></td>
                        <td><?php echo floor($days_overdue); ?> days</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($due_today)): ?>
    <div class="bntm-form-section">
        <h3>Due Today (<?php echo date('F d, Y'); ?>)</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Loan #</th>
                    <th>Borrower</th>
                    <th>Contact</th>
                    <th>Installment #</th>
                    <th>Amount Due</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($due_today as $inst): ?>
                <tr>
                    <td><strong><?php echo esc_html($inst->loan_number); ?></strong></td>
                    <td><?php echo esc_html($inst->borrower_name); ?></td>
                    <td><?php echo esc_html($inst->contact_number ?: '-'); ?></td>
                    <td><?php echo $inst->installment_number; ?></td>
                    <td style="font-weight: 600;">₱<?php echo number_format($inst->installment_amount, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

/**
 * Finance Export Tab
 */
function lm_finance_tab($business_id) {
    global $wpdb;
    $payments_table = $wpdb->prefix . 'lm_payments';
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    $fn_table = $wpdb->prefix . 'fn_transactions';
    
    // Get all payments with export status
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*, l.loan_number, b.borrower_name,
        (SELECT COUNT(*) FROM $fn_table WHERE reference_type='lm_payment' AND reference_id=p.id) as is_exported
         FROM $payments_table p
         INNER JOIN $loans_table l ON p.loan_id = l.id
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE p.business_id = %d
         ORDER BY p.payment_date DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('lm_fn_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Export Payments to Finance Module</h3>
        <p>Export loan payments as income records to the Finance module for comprehensive financial tracking.</p>
        
        <div style="margin-bottom: 15px;">
            <label style="cursor: pointer; margin-right: 20px;">
                <input type="checkbox" id="select-all-not-exported">
                <strong>Select All (Not Exported)</strong>
            </label>
            <label style="cursor: pointer;">
                <input type="checkbox" id="select-all-exported">
                <strong>Select All (Exported)</strong>
            </label>
        </div>
        
        <div style="margin-bottom: 15px;">
            <button id="bulk-export-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
                Export Selected
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
                    <th>Loan #</th>
                    <th>Borrower</th>
                    <th>Amount</th>
                    <th>Export Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="6" style="text-align:center;">No payments found</td></tr>
                <?php else: foreach ($payments as $payment): ?>
                <tr>
                    <td>
                        <input type="checkbox" 
                               class="payment-checkbox <?php echo $payment->is_exported ? 'exported-payment' : 'not-exported-payment'; ?>"
                               data-id="<?php echo $payment->id; ?>"
                               data-amount="<?php echo $payment->amount; ?>"
                               data-exported="<?php echo $payment->is_exported ? '1' : '0'; ?>">
                    </td>
                    <td><?php echo date('M d, Y', strtotime($payment->payment_date)); ?></td>
                    <td><?php echo esc_html($payment->loan_number); ?></td>
                    <td><?php echo esc_html($payment->borrower_name); ?></td>
                    <td style="color: #059669; font-weight: 600;">₱<?php echo number_format($payment->amount, 2); ?></td>
                    <td>
                        <?php if ($payment->is_exported): ?>
                        <span style="color: #059669;">✓ Exported</span>
                        <?php else: ?>
                        <span style="color: #6b7280;">Not Exported</span>
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
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.payment-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        document.getElementById('select-all-not-exported').addEventListener('change', function() {
            document.querySelectorAll('.not-exported-payment').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-exported').checked = false;
            }
            updateSelectedCount();
        });
        
        document.getElementById('select-all-exported').addEventListener('change', function() {
            document.querySelectorAll('.exported-payment').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-not-exported').checked = false;
            }
            updateSelectedCount();
        });
        
        document.querySelectorAll('.payment-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        document.getElementById('bulk-export-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.payment-checkbox:checked'))
                .filter(cb => cb.dataset.exported === '0');
            
            if (selected.length === 0) {
                alert('Please select at least one payment that is not exported');
                return;
            }
            
            const totalAmount = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
            
            if (!confirm(`Export ${selected.length} payment(s)?\n\nTotal Amount: ₱${totalAmount.toFixed(2)}`)) return;
            
            this.disabled = true;
            this.textContent = 'Exporting...';
            
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'lm_fn_export_payment');
                data.append('payment_id', cb.dataset.id);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully exported ${total} payment(s)`);
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Export error:', err);
                    completed++;
                    if (completed === total) {
                        alert('Export completed with some errors. Please check and try again.');
                        location.reload();
                    }
                });
            });
        });
        
        document.getElementById('bulk-revert-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.payment-checkbox:checked'))
                .filter(cb => cb.dataset.exported === '1');
            
            if (selected.length === 0) {
                alert('Please select at least one exported payment');
                return;
            }
            
            if (!confirm(`Remove ${selected.length} payment(s) from Finance?`)) return;
            
            this.disabled = true;
            this.textContent = 'Reverting...';
            
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'lm_fn_revert_payment');
                data.append('payment_id', cb.dataset.id);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully reverted ${total} payment(s)`);
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Revert error:', err);
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
    <?php
    return ob_get_clean();
}

/**
 * Settings Tab
 */
function lm_settings_tab($business_id) {
    // Get current settings
    $penalty_enabled = bntm_get_setting('lm_penalty_enabled', '0');
    $penalty_type = bntm_get_setting('lm_penalty_type', 'fixed');
    $penalty_amount = bntm_get_setting('lm_penalty_amount', '0');
    $penalty_rate = bntm_get_setting('lm_penalty_rate', '0');
    $auto_penalty = bntm_get_setting('lm_auto_penalty', '0');
    $grace_period = bntm_get_setting('lm_grace_period', '0');
    $currency = bntm_get_setting('lm_currency', 'PHP');
    
    $nonce = wp_create_nonce('lm_settings_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Loan Management Settings</h3>
        
        <form id="settingsForm" class="bntm-form">
            <h4>Penalty Configuration</h4>
            
            <div class="bntm-form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="penalty_enabled" value="1" <?php checked($penalty_enabled, '1'); ?>>
                    <strong>Enable Penalty Charges</strong>
                </label>
                <small>Charge penalties for late/overdue payments</small>
            </div>
            
            <div id="penaltySettings" style="<?php echo $penalty_enabled === '1' ? '' : 'display: none;'; ?>">
                <div class="bntm-form-group">
                    <label>Penalty Type *</label>
                    <select name="penalty_type" class="bntm-input">
                        <option value="fixed" <?php selected($penalty_type, 'fixed'); ?>>Fixed Amount per Missed Installment</option>
                        <option value="percentage" <?php selected($penalty_type, 'percentage'); ?>>Percentage of Installment Amount</option>
                    </select>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group" id="fixedPenaltyGroup" style="<?php echo $penalty_type === 'fixed' ? '' : 'display: none;'; ?>">
                        <label>Fixed Penalty Amount (₱)</label>
                        <input type="number" name="penalty_amount" step="0.01" value="<?php echo esc_attr($penalty_amount); ?>" class="bntm-input">
                        <small>Fixed amount to charge per overdue installment</small>
                    </div>
                    
                    <div class="bntm-form-group" id="percentagePenaltyGroup" style="<?php echo $penalty_type === 'percentage' ? '' : 'display: none;'; ?>">
                        <label>Penalty Rate (%)</label>
                        <input type="number" name="penalty_rate" step="0.01" value="<?php echo esc_attr($penalty_rate); ?>" class="bntm-input">
                        <small>Percentage of installment amount</small>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Grace Period (Days)</label>
                        <input type="number" name="grace_period" value="<?php echo esc_attr($grace_period); ?>" class="bntm-input">
                        <small>Days after due date before penalty applies</small>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="auto_penalty" value="1" <?php checked($auto_penalty, '1'); ?>>
                        <strong>Automatically Apply Penalties</strong>
                    </label>
                    <small>System will automatically add penalties for overdue installments</small>
                </div>
            </div>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;">
            
            <h4>General Settings</h4>
            
            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency" class="bntm-input">
                    <option value="PHP" <?php selected($currency, 'PHP'); ?>>PHP (₱) - Philippine Peso</option>
                    <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($) - US Dollar</option>
                    <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€) - Euro</option>
                    <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£) - British Pound</option>
                </select>
            </div>
            
            <div style="margin-top: 30px;">
                <button type="button" class="bntm-btn-primary" onclick="saveSettings()">
                    Save Settings
                </button>
                <div id="settings-message" style="margin-top: 15px;"></div>
            </div>
        </form>
    </div>
    
    <div class="bntm-form-section">
        <h3>System Actions</h3>
        
        <div style="display: flex; gap: 15px; flex-wrap: wrap;">
            <button onclick="checkOverdue()" class="bntm-btn-secondary">
                Check & Mark Overdue Installments
            </button>
            <?php if ($auto_penalty === '1'): ?>
            <button onclick="applyPenalties()" class="bntm-btn-secondary">
                Apply Automatic Penalties
            </button>
            <?php endif; ?>
        </div>
        
        <div id="action-message" style="margin-top: 15px;"></div>
    </div>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Toggle penalty settings
        document.querySelector('input[name="penalty_enabled"]').addEventListener('change', function() {
            document.getElementById('penaltySettings').style.display = this.checked ? 'block' : 'none';
        });
        
        // Toggle penalty type fields
        document.querySelector('select[name="penalty_type"]').addEventListener('change', function() {
            const isFixed = this.value === 'fixed';
            document.getElementById('fixedPenaltyGroup').style.display = isFixed ? 'block' : 'none';
            document.getElementById('percentagePenaltyGroup').style.display = isFixed ? 'none' : 'block';
        });
        
        // Save settings
        window.saveSettings = function() {
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            formData.append('action', 'lm_save_settings');
            formData.append('_ajax_nonce', nonce);
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                document.getElementById('settings-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' +
                    json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Settings';
            });
        };
        
        // Check overdue
        window.checkOverdue = function() {
            if (!confirm('Check and mark overdue installments?')) return;
            
            const formData = new FormData();
            formData.append('action', 'lm_check_overdue');
            formData.append('_ajax_nonce', nonce);
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Checking...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                document.getElementById('action-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' +
                    json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Check & Mark Overdue Installments';
            });
        };
        
        // Apply penalties
        window.applyPenalties = function() {
            if (!confirm('Apply automatic penalties to overdue installments?')) return;
            
            const formData = new FormData();
            formData.append('action', 'lm_apply_penalty');
            formData.append('auto', '1');
            formData.append('_ajax_nonce', nonce);
            
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = 'Applying...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                document.getElementById('action-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' +
                    json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Apply Automatic Penalties';
            });
        };
    })();
    </script>
    <?php
    return ob_get_clean();
}
// ========================================
// AJAX HANDLERS
// ========================================

/**
 * Add Borrower
 */
function bntm_ajax_lm_add_borrower() {
    check_ajax_referer('lm_borrower_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'lm_borrowers';
    $business_id = get_current_user_id();
    
    $borrower_name = sanitize_text_field($_POST['borrower_name']);
    
    if (empty($borrower_name)) {
        wp_send_json_error(['message' => 'Borrower name is required']);
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'borrower_name' => $borrower_name,
        'contact_number' => sanitize_text_field($_POST['contact_number'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'address' => sanitize_textarea_field($_POST['address'] ?? ''),
        'id_type' => sanitize_text_field($_POST['id_type'] ?? ''),
        'id_number' => sanitize_text_field($_POST['id_number'] ?? ''),
        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
        'status' => 'active'
    ];
    
    $result = $wpdb->insert($table, $data, [
        '%s','%d','%s','%s','%s','%s','%s','%s','%s','%s'
    ]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Borrower added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add borrower']);
    }
}

/**
 * Update Borrower
 */
function bntm_ajax_lm_update_borrower() {
    check_ajax_referer('lm_borrower_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'lm_borrowers';
    $borrower_id = intval($_POST['borrower_id']);
    $business_id = get_current_user_id();
    
    // Verify ownership
    $borrower = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND business_id = %d",
        $borrower_id, $business_id
    ));
    
    if (!$borrower) {
        wp_send_json_error(['message' => 'Borrower not found']);
    }
    
    $data = [
        'borrower_name' => sanitize_text_field($_POST['borrower_name']),
        'contact_number' => sanitize_text_field($_POST['contact_number'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'address' => sanitize_textarea_field($_POST['address'] ?? ''),
        'id_type' => sanitize_text_field($_POST['id_type'] ?? ''),
        'id_number' => sanitize_text_field($_POST['id_number'] ?? ''),
        'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
    ];
    
    $result = $wpdb->update($table, $data, ['id' => $borrower_id], [
        '%s','%s','%s','%s','%s','%s','%s'
    ], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Borrower updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update borrower']);
    }
}

/**
 * Get Borrower
 */
function bntm_ajax_lm_get_borrower() {
    check_ajax_referer('lm_borrower_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'lm_borrowers';
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrower_id = intval($_POST['borrower_id']);
    $business_id = get_current_user_id();
    $view_mode = isset($_POST['view_mode']);
    
    $borrower = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND business_id = %d",
        $borrower_id, $business_id
    ));
    
    if (!$borrower) {
        wp_send_json_error(['message' => 'Borrower not found']);
    }
    
    if ($view_mode) {
        // Get borrower's loans
        $loans = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $loans_table WHERE borrower_id = %d ORDER BY created_at DESC",
            $borrower_id
        ));
        
        ob_start();
        ?>
        <div class="lm-info-card">
            <div class="lm-info-row">
                <span class="lm-info-label">Full Name:</span>
                <span class="lm-info-value"><?php echo esc_html($borrower->borrower_name); ?></span>
            </div>
            <?php if ($borrower->contact_number): ?>
            <div class="lm-info-row">
                <span class="lm-info-label">Contact:</span>
                <span class="lm-info-value"><?php echo esc_html($borrower->contact_number); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($borrower->email): ?>
            <div class="lm-info-row">
                <span class="lm-info-label">Email:</span>
                <span class="lm-info-value"><?php echo esc_html($borrower->email); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($borrower->address): ?>
            <div class="lm-info-row">
                <span class="lm-info-label">Address:</span>
                <span class="lm-info-value"><?php echo esc_html($borrower->address); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($borrower->id_type): ?>
            <div class="lm-info-row">
                <span class="lm-info-label">ID Type:</span>
                <span class="lm-info-value"><?php echo esc_html($borrower->id_type); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($borrower->id_number): ?>
            <div class="lm-info-row">
                <span class="lm-info-label">ID Number:</span>
                <span class="lm-info-value"><?php echo esc_html($borrower->id_number); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($borrower->notes): ?>
            <div class="lm-info-row">
                <span class="lm-info-label">Notes:</span>
                <span class="lm-info-value"><?php echo nl2br(esc_html($borrower->notes)); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <h4 style="margin-top: 20px;">Loan History</h4>
        <?php if (empty($loans)): ?>
            <p style="color: #6b7280;">No loans yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Loan #</th>
                        <th>Amount</th>
                        <th>Start Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td><?php echo esc_html($loan->loan_number); ?></td>
                        <td>₱<?php echo number_format($loan->loan_amount, 2); ?></td>
                        <td><?php echo date('M d, Y', strtotime($loan->start_date)); ?></td>
                        <td><span class="lm-status-badge lm-status-<?php echo $loan->status; ?>"><?php echo ucfirst($loan->status); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
        $html = ob_get_clean();
        wp_send_json_success(['borrower' => $borrower, 'html' => $html]);
    } else {
        wp_send_json_success(['borrower' => $borrower]);
    }
}

/**
 * Delete Borrower
 */
function bntm_ajax_lm_delete_borrower() {
    check_ajax_referer('lm_borrower_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'lm_borrowers';
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrower_id = intval($_POST['borrower_id']);
    $business_id = get_current_user_id();
    
    // Check for active loans
    $active_loans = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $loans_table WHERE borrower_id = %d AND status = 'active'",
        $borrower_id
    ));
    
    if ($active_loans > 0) {
        wp_send_json_error(['message' => 'Cannot delete borrower with active loans']);
    }
    
    $result = $wpdb->delete($table, [
        'id' => $borrower_id,
        'business_id' => $business_id
    ], ['%d', '%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Borrower deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete borrower']);
    }
}

/**
 * Create Loan
 */
function bntm_ajax_lm_create_loan() {
    check_ajax_referer('lm_loan_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $business_id = get_current_user_id();
    
    $borrower_id = intval($_POST['borrower_id']);
    $loan_amount = floatval($_POST['loan_amount']);
    $interest_rate = floatval($_POST['interest_rate']);
    $interest_type = sanitize_text_field($_POST['interest_type']);
    $compound_frequency = sanitize_text_field($_POST['compound_frequency'] ?? 'monthly');
    $is_diminishing = isset($_POST['is_diminishing']) ? 1 : 0;
    $loan_term = intval($_POST['loan_term']);
    $term_unit = sanitize_text_field($_POST['term_unit']);
    $processing_fee = floatval($_POST['processing_fee'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date']);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($borrower_id) || empty($loan_amount) || empty($loan_term)) {
        wp_send_json_error(['message' => 'Please fill all required fields']);
    }
    
    // Calculate loan details based on interest type and diminishing flag
    $calculation = lm_calculate_loan_details(
        $loan_amount, 
        $interest_rate, 
        $loan_term, 
        $term_unit, 
        $interest_type,
        $compound_frequency,
        $processing_fee,
        $is_diminishing
    );
    
    $total_interest = $calculation['total_interest'];
    $total_amount = $calculation['total_amount'];
    $installment_amount = $calculation['installment_amount'];
    
    // Calculate maturity date
    $start_datetime = new DateTime($start_date);
    if ($term_unit === 'weeks') {
        $start_datetime->modify("+{$loan_term} weeks");
    } else {
        $start_datetime->modify("+{$loan_term} months");
    }
    $maturity_date = $start_datetime->format('Y-m-d');
    
    // Generate loan number
    $loan_number = 'LN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    while ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $loans_table WHERE loan_number = %s", $loan_number)) > 0) {
        $loan_number = 'LN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'borrower_id' => $borrower_id,
        'loan_number' => $loan_number,
        'loan_amount' => $loan_amount,
        'interest_rate' => $interest_rate,
        'interest_type' => $interest_type,
        'compound_frequency' => $compound_frequency,
        'is_diminishing' => $is_diminishing,
        'loan_term' => $loan_term,
        'term_unit' => $term_unit,
        'processing_fee' => $processing_fee,
        'total_interest' => $total_interest,
        'total_amount' => $total_amount,
        'installment_amount' => $installment_amount,
        'start_date' => $start_date,
        'maturity_date' => $maturity_date,
        'status' => 'pending',
        'notes' => $notes
    ];
    
    $result = $wpdb->insert($loans_table, $data, [
        '%s','%d','%d','%s','%f','%f','%s','%s','%d','%d','%s','%f','%f','%f','%f','%s','%s','%s','%s'
    ]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Loan created successfully! Loan #: ' . $loan_number]);
    } else {
        wp_send_json_error(['message' => 'Failed to create loan']);
    }
}

/**
 * Approve Loan
 */
function bntm_ajax_lm_approve_loan() {
    check_ajax_referer('lm_loan_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $installments_table = $wpdb->prefix . 'lm_installments';
    $loan_id = intval($_POST['loan_id']);
    $business_id = get_current_user_id();
    
    // Get loan
    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $loans_table WHERE id = %d AND business_id = %d AND status = 'pending'",
        $loan_id, $business_id
    ));
    
    if (!$loan) {
        wp_send_json_error(['message' => 'Loan not found or already approved']);
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Update loan status
        $wpdb->update($loans_table, ['status' => 'active'], ['id' => $loan_id], ['%s'], ['%d']);
        
        // Generate installment schedule based on diminishing flag
        if ($loan->is_diminishing == 1) {
            // DIMINISHING BALANCE SCHEDULE
            $remaining_principal = $loan->loan_amount;
            $periodic_rate = ($loan->interest_rate / 100) / 12; // Monthly rate
            if ($loan->term_unit === 'weeks') {
                $periodic_rate = ($loan->interest_rate / 100) / 52;
            }
            
            $current_date = new DateTime($loan->start_date);
            
            for ($i = 1; $i <= $loan->loan_term; $i++) {
                // Calculate due date
                if ($loan->term_unit === 'weeks') {
                    $current_date->modify('+1 week');
                } else {
                    $current_date->modify('+1 month');
                }
                
                $due_date = $current_date->format('Y-m-d');
                
                // Calculate interest on remaining balance
                $interest_amount = $remaining_principal * $periodic_rate;
                $principal_amount = $loan->installment_amount - $interest_amount;
                
                // Ensure we don't have negative principal on last installment
                if ($i == $loan->loan_term) {
                    $principal_amount = $remaining_principal;
                    $installment_total = $principal_amount + $interest_amount;
                } else {
                    $installment_total = $loan->installment_amount;
                }
                
                $installment_data = [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'loan_id' => $loan_id,
                    'installment_number' => $i,
                    'due_date' => $due_date,
                    'principal_amount' => $principal_amount,
                    'interest_amount' => $interest_amount,
                    'installment_amount' => $installment_total,
                    'paid_amount' => 0,
                    'balance' => $installment_total,
                    'penalty_amount' => 0,
                    'status' => 'pending'
                ];
                
                $result = $wpdb->insert($installments_table, $installment_data, [
                    '%s','%d','%d','%d','%s','%f','%f','%f','%f','%f','%f','%s'
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to create installment #' . $i);
                }
                
                // Reduce remaining principal
                $remaining_principal -= $principal_amount;
            }
            
        } else {
            // REGULAR SCHEDULE (flat, simple, compound - non-diminishing)
            $principal_per_installment = $loan->loan_amount / $loan->loan_term;
            $interest_per_installment = $loan->total_interest / $loan->loan_term;
            
            $current_date = new DateTime($loan->start_date);
            
            for ($i = 1; $i <= $loan->loan_term; $i++) {
                // Calculate due date
                if ($loan->term_unit === 'weeks') {
                    $current_date->modify('+1 week');
                } else {
                    $current_date->modify('+1 month');
                }
                
                $due_date = $current_date->format('Y-m-d');
                
                $installment_data = [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'loan_id' => $loan_id,
                    'installment_number' => $i,
                    'due_date' => $due_date,
                    'principal_amount' => $principal_per_installment,
                    'interest_amount' => $interest_per_installment,
                    'installment_amount' => $loan->installment_amount,
                    'paid_amount' => 0,
                    'balance' => $loan->installment_amount,
                    'penalty_amount' => 0,
                    'status' => 'pending'
                ];
                
                $result = $wpdb->insert($installments_table, $installment_data, [
                    '%s','%d','%d','%d','%s','%f','%f','%f','%f','%f','%f','%s'
                ]);
                
                if (!$result) {
                    throw new Exception('Failed to create installment #' . $i);
                }
            }
        }
        
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Loan approved and schedule generated successfully!']);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Cancel Loan
 */
function bntm_ajax_lm_cancel_loan() {
    check_ajax_referer('lm_loan_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $payments_table = $wpdb->prefix . 'lm_payments';
    $loan_id = intval($_POST['loan_id']);
    $business_id = get_current_user_id();
    
    // Check if loan has payments
    $has_payments = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $payments_table WHERE loan_id = %d",
        $loan_id
    ));
    
    if ($has_payments > 0) {
        wp_send_json_error(['message' => 'Cannot cancel loan with existing payments']);
    }
    
    $result = $wpdb->update(
        $loans_table,
        ['status' => 'cancelled'],
        ['id' => $loan_id, 'business_id' => $business_id],
        ['%s'],
        ['%d', '%d']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Loan cancelled successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to cancel loan']);
    }
}

/**
 * Get Loan Details
 */
function bntm_ajax_lm_get_loan_details() {
    check_ajax_referer('lm_loan_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    $installments_table = $wpdb->prefix . 'lm_installments';
    $payments_table = $wpdb->prefix . 'lm_payments';
    $loan_id = intval($_POST['loan_id']);
    $business_id = get_current_user_id();
    
    // Get loan with borrower info
    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT l.*, b.borrower_name, b.contact_number, b.email, b.address
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.id = %d AND l.business_id = %d",
        $loan_id, $business_id
    ));
    
    if (!$loan) {
        wp_send_json_error(['message' => 'Loan not found']);
    }
    
    // Get installments
    $installments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $installments_table WHERE loan_id = %d ORDER BY installment_number",
        $loan_id
    ));
    
    // Get payments
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $payments_table WHERE loan_id = %d ORDER BY payment_date DESC",
        $loan_id
    ));
    
    // Format installments for response
    foreach ($installments as &$inst) {
        $inst->due_date_formatted = date('M d, Y', strtotime($inst->due_date));
        $inst->is_overdue = $inst->status === 'overdue';
    }
    
    ob_start();
    ?>
    <div class="lm-info-card">
        <h4 style="margin-top: 0; margin-bottom: 15px; font-size: 15px;">Loan Information</h4>
        <div class="lm-info-row">
            <span class="lm-info-label">Loan Number:</span>
            <span class="lm-info-value"><?php echo esc_html($loan->loan_number); ?></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Borrower:</span>
            <span class="lm-info-value"><?php echo esc_html($loan->borrower_name); ?></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Status:</span>
            <span class="lm-info-value"><span class="lm-status-badge lm-status-<?php echo $loan->status; ?>"><?php echo ucfirst($loan->status); ?></span></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Principal Amount:</span>
            <span class="lm-info-value">₱<?php echo number_format($loan->loan_amount, 2); ?></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Interest Rate:</span>
            <span class="lm-info-value"><?php echo $loan->interest_rate; ?>% (<?php echo ucfirst(str_replace('_', ' ', $loan->interest_type)); ?>)</span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Interest Type:</span>
            <span class="lm-info-value">
                <?php echo $loan->interest_rate; ?>% (<?php echo ucfirst(str_replace('_', ' ', $loan->interest_type)); ?>)
                <?php if ($loan->is_diminishing == 1): ?>
                <span style="background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 5px;">
                    DIMINISHING
                </span>
                <?php endif; ?>
            </span>
        </div>
        <?php if ($loan->interest_type === 'compound'): ?>
        <div class="lm-info-row">
            <span class="lm-info-label">Compounding:</span>
            <span class="lm-info-value"><?php echo ucfirst(str_replace('_', ' ', $loan->compound_frequency)); ?></span>
        </div>
        <?php endif; ?>
        <div class="lm-info-row">
            <span class="lm-info-label">Total Interest:</span>
            <span class="lm-info-value">₱<?php echo number_format($loan->total_interest, 2); ?></span>
        </div>
        <?php if ($loan->processing_fee > 0): ?>
        <div class="lm-info-row">
            <span class="lm-info-label">Processing Fee:</span>
            <span class="lm-info-value">₱<?php echo number_format($loan->processing_fee, 2); ?></span>
        </div>
        <?php endif; ?>
        <div class="lm-info-row">
            <span class="lm-info-label">Total Amount:</span>
            <span class="lm-info-value"><strong>₱<?php echo number_format($loan->total_amount, 2); ?></strong></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Loan Term:</span>
            <span class="lm-info-value"><?php echo $loan->loan_term; ?> <?php echo $loan->term_unit; ?></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Installment Amount:</span>
            <span class="lm-info-value">₱<?php echo number_format($loan->installment_amount, 2); ?></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Start Date:</span>
            <span class="lm-info-value"><?php echo date('M d, Y', strtotime($loan->start_date)); ?></span>
        </div>
        <div class="lm-info-row">
            <span class="lm-info-label">Maturity Date:</span>
            <span class="lm-info-value"><?php echo date('M d, Y', strtotime($loan->maturity_date)); ?></span>
        </div>
    </div>
    
    <h4 style="margin-top: 20px;">Installment Schedule</h4>
    <?php if (empty($installments)): ?>
        <p style="color: #6b7280;">No installment schedule generated yet. Approve the loan to generate schedule.</p>
    <?php else: ?>
        <table class="bntm-table" style="font-size: 13px;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Due Date</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installments as $inst): ?>
                <tr style="<?php echo $inst->status === 'overdue' ? 'background-color: #fee2e2;' : ''; ?>">
                    <td><?php echo $inst->installment_number; ?></td>
                    <td><?php echo date('M d, Y', strtotime($inst->due_date)); ?></td>
                    <td>₱<?php echo number_format($inst->principal_amount, 2); ?></td>
                    <td>₱<?php echo number_format($inst->interest_amount, 2); ?></td>
                    <td>₱<?php echo number_format($inst->installment_amount, 2); ?></td>
                    <td style="color: #059669;">₱<?php echo number_format($inst->paid_amount, 2); ?></td>
                    <td><strong>₱<?php echo number_format($inst->balance, 2); ?></strong></td>
                    <td><span class="lm-status-badge lm-status-<?php echo $inst->status; ?>"><?php echo ucfirst($inst->status); ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <?php if (!empty($payments)): ?>
    <h4 style="margin-top: 20px;">Payment History</h4>
    <table class="bntm-table" style="font-size: 13px;">
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($payment->payment_date)); ?></td>
                <td style="color: #059669; font-weight: 600;">₱<?php echo number_format($payment->amount, 2); ?></td>
                <td><?php echo esc_html(ucfirst($payment->payment_method)); ?></td>
                <td><?php echo esc_html($payment->reference_number ?: '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(['loan' => $loan, 'installments' => $installments, 'payments' => $payments, 'html' => $html]);
}

/**
 * Record Payment
 */
/**
 * Record Payment
 */
function bntm_ajax_lm_record_payment() {
    check_ajax_referer('lm_payment_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $installments_table = $wpdb->prefix . 'lm_installments';
    $payments_table = $wpdb->prefix . 'lm_payments';
    $business_id = get_current_user_id();
    
    $loan_id = intval($_POST['loan_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = sanitize_text_field($_POST['payment_date']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $reference_number = sanitize_text_field($_POST['reference_number'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $reduce_principal = isset($_POST['reduce_principal']) && $_POST['reduce_principal'] == '1';
    
    if (empty($loan_id) || empty($amount) || empty($payment_date)) {
        wp_send_json_error(['message' => 'Please fill all required fields']);
    }
    
    // Get loan
    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $loans_table WHERE id = %d AND business_id = %d",
        $loan_id, $business_id
    ));
    
    if (!$loan || $loan->status !== 'active') {
        wp_send_json_error(['message' => 'Loan not found or not active']);
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Record payment
        $payment_data = [
            'rand_id' => bntm_rand_id(),
            'business_id' => $business_id,
            'loan_id' => $loan_id,
            'payment_date' => $payment_date,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'notes' => $notes,
            'recorded_by' => $business_id
        ];
        
        $result = $wpdb->insert($payments_table, $payment_data, [
            '%s','%d','%d','%s','%f','%s','%s','%s','%d'
        ]);
        
        if (!$result) {
            throw new Exception('Failed to record payment');
        }
        
        // Check if this is a diminishing balance loan AND reduce_principal is checked
        if ($loan->is_diminishing == 1 && $reduce_principal) {
            // DIMINISHING BALANCE WITH PRINCIPAL REDUCTION
            lm_process_diminishing_payment($loan, $amount, $payment_date);
            $message = 'Payment recorded successfully! Future installments have been recalculated with reduced principal.';
        } else {
            // REGULAR PAYMENT PROCESSING (FIFO - First unpaid installment first)
            $remaining_amount = $amount;
            
            $installments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $installments_table 
                 WHERE loan_id = %d AND balance > 0 
                 ORDER BY installment_number ASC",
                $loan_id
            ));
            
            foreach ($installments as $installment) {
                if ($remaining_amount <= 0) break;
                
                $to_pay = min($remaining_amount, $installment->balance);
                $new_paid = $installment->paid_amount + $to_pay;
                $new_balance = $installment->balance - $to_pay;
                $new_status = $new_balance == 0 ? 'paid' : $installment->status;
                
                $wpdb->update(
                    $installments_table,
                    [
                        'paid_amount' => $new_paid,
                        'balance' => $new_balance,
                        'status' => $new_status,
                        'paid_date' => $new_balance == 0 ? $payment_date : null
                    ],
                    ['id' => $installment->id],
                    ['%f', '%f', '%s', '%s'],
                    ['%d']
                );
                
                $remaining_amount -= $to_pay;
            }
            
            $message = 'Payment recorded successfully!';
        }
        
        // Check if loan is fully paid
        $total_balance = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(balance) FROM $installments_table WHERE loan_id = %d",
            $loan_id
        ));
        
        if ($total_balance == 0) {
            $wpdb->update(
                $loans_table,
                ['status' => 'completed'],
                ['id' => $loan_id],
                ['%s'],
                ['%d']
            );
        }
        
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => $message]);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Delete Payment
 */
/**
 * Delete Payment
 */
function bntm_ajax_lm_delete_payment() {
    check_ajax_referer('lm_payment_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $payments_table = $wpdb->prefix . 'lm_payments';
    $installments_table = $wpdb->prefix . 'lm_installments';
    $loans_table = $wpdb->prefix . 'lm_loans';
    $payment_id = intval($_POST['payment_id']);
    $business_id = get_current_user_id();
    
    // Get payment
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $payments_table WHERE id = %d AND business_id = %d",
        $payment_id, $business_id
    ));
    
    if (!$payment) {
        wp_send_json_error(['message' => 'Payment not found']);
    }
    
    // Get loan
    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $loans_table WHERE id = %d",
        $payment->loan_id
    ));
    
    if (!$loan) {
        wp_send_json_error(['message' => 'Loan not found']);
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Delete payment first
        $wpdb->delete($payments_table, ['id' => $payment_id], ['%d']);
        
        if ($loan->is_diminishing == 1) {
            // For diminishing balance, we need to regenerate the entire schedule
            // Delete all installments
            $wpdb->delete($installments_table, ['loan_id' => $loan->id], ['%d']);
            
            // Regenerate schedule from scratch
            lm_regenerate_diminishing_schedule($loan);
            
            // Reapply all remaining payments
            $remaining_payments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $payments_table 
                 WHERE loan_id = %d 
                 ORDER BY payment_date ASC, created_at ASC",
                $loan->id
            ));
            
            foreach ($remaining_payments as $p) {
                lm_process_diminishing_payment($loan, $p->amount, $p->payment_date);
            }
            
        } else {
            // Regular loan - reverse payment from installments
            $remaining_amount = $payment->amount;
            
            $installments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $installments_table 
                 WHERE loan_id = %d AND paid_amount > 0 
                 ORDER BY installment_number DESC",
                $payment->loan_id
            ));
            
            foreach ($installments as $installment) {
                if ($remaining_amount <= 0) break;
                
                $to_reverse = min($remaining_amount, $installment->paid_amount);
                $new_paid = $installment->paid_amount - $to_reverse;
                $new_balance = $installment->balance + $to_reverse;
                $new_status = $new_balance > 0 ? 'pending' : $installment->status;
                
                $wpdb->update(
                    $installments_table,
                    [
                        'paid_amount' => $new_paid,
                        'balance' => $new_balance,
                        'status' => $new_status,
                        'paid_date' => null
                    ],
                    ['id' => $installment->id],
                    ['%f', '%f', '%s', '%s'],
                    ['%d']
                );
                
                $remaining_amount -= $to_reverse;
            }
        }
        
        // Update loan status back to active if it was completed
        $wpdb->update(
            $loans_table,
            ['status' => 'active'],
            ['id' => $payment->loan_id, 'status' => 'completed'],
            ['%s'],
            ['%d', '%s']
        );
        
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Payment deleted and schedule recalculated']);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Regenerate diminishing balance schedule from scratch
 */
function lm_regenerate_diminishing_schedule($loan) {
    global $wpdb;
    $installments_table = $wpdb->prefix . 'lm_installments';
    
    $remaining_principal = $loan->loan_amount;
    $periodic_rate = ($loan->interest_rate / 100) / 12; // Monthly rate
    if ($loan->term_unit === 'weeks') {
        $periodic_rate = ($loan->interest_rate / 100) / 52;
    }
    
    $current_date = new DateTime($loan->start_date);
    
    for ($i = 1; $i <= $loan->loan_term; $i++) {
        // Calculate due date
        if ($loan->term_unit === 'weeks') {
            $current_date->modify('+1 week');
        } else {
            $current_date->modify('+1 month');
        }
        
        $due_date = $current_date->format('Y-m-d');
        
        // Calculate interest on remaining balance
        $interest_amount = $remaining_principal * $periodic_rate;
        $principal_amount = $loan->installment_amount - $interest_amount;
        
        // Ensure we don't have negative principal on last installment
        if ($i == $loan->loan_term) {
            $principal_amount = $remaining_principal;
            $installment_total = $principal_amount + $interest_amount;
        } else {
            $installment_total = $loan->installment_amount;
        }
        
        $installment_data = [
            'rand_id' => bntm_rand_id(),
            'business_id' => $loan->business_id,
            'loan_id' => $loan->id,
            'installment_number' => $i,
            'due_date' => $due_date,
            'principal_amount' => $principal_amount,
            'interest_amount' => $interest_amount,
            'installment_amount' => $installment_total,
            'paid_amount' => 0,
            'balance' => $installment_total,
            'penalty_amount' => 0,
            'status' => 'pending'
        ];
        
        $wpdb->insert($installments_table, $installment_data, [
            '%s','%d','%d','%d','%s','%f','%f','%f','%f','%f','%f','%s'
        ]);
        
        // Reduce remaining principal
        $remaining_principal -= $principal_amount;
    }
}

/**
 * Check Overdue Installments
 */
function bntm_ajax_lm_check_overdue() {
    check_ajax_referer('lm_settings_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $installments_table = $wpdb->prefix . 'lm_installments';
    $business_id = get_current_user_id();
    
    $grace_period = intval(bntm_get_setting('lm_grace_period', '0'));
    $today = date('Y-m-d');
    $overdue_date = date('Y-m-d', strtotime("-{$grace_period} days"));
    
    $result = $wpdb->query($wpdb->prepare(
        "UPDATE $installments_table i
         INNER JOIN {$wpdb->prefix}lm_loans l ON i.loan_id = l.id
         SET i.status = 'overdue'
         WHERE l.business_id = %d
         AND i.status = 'pending'
         AND i.due_date < %s",
        $business_id, $overdue_date
    ));
    
    wp_send_json_success(['message' => "Marked {$result} installment(s) as overdue"]);
}

/**
 * Apply Penalty
 */
function bntm_ajax_lm_apply_penalty() {
    check_ajax_referer('lm_settings_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $installments_table = $wpdb->prefix . 'lm_installments';
    $penalties_table = $wpdb->prefix . 'lm_penalties';
    $business_id = get_current_user_id();
    
    $penalty_enabled = bntm_get_setting('lm_penalty_enabled', '0');
    
    if ($penalty_enabled !== '1') {
        wp_send_json_error(['message' => 'Penalty system is not enabled']);
    }
    
    $penalty_type = bntm_get_setting('lm_penalty_type', 'fixed');
    $penalty_amount = floatval(bntm_get_setting('lm_penalty_amount', '0'));
    $penalty_rate = floatval(bntm_get_setting('lm_penalty_rate', '0'));
    
    // Get overdue installments without penalty
    $installments = $wpdb->get_results($wpdb->prepare(
        "SELECT i.* FROM $installments_table i
         INNER JOIN {$wpdb->prefix}lm_loans l ON i.loan_id = l.id
         WHERE l.business_id = %d
         AND i.status = 'overdue'
         AND i.penalty_amount = 0",
        $business_id
    ));
    
    $count = 0;
    
    foreach ($installments as $inst) {
        $penalty = 0;
        
        if ($penalty_type === 'fixed') {
            $penalty = $penalty_amount;
        } else {
            $penalty = ($inst->installment_amount * $penalty_rate) / 100;
        }
        
        if ($penalty > 0) {
            // Update installment
            $wpdb->update(
                $installments_table,
                [
                    'penalty_amount' => $penalty,
                    'balance' => $inst->balance + $penalty
                ],
                ['id' => $inst->id],
                ['%f', '%f'],
                ['%d']
            );
            
            // Record penalty
            $wpdb->insert($penalties_table, [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'loan_id' => $inst->loan_id,
                'installment_id' => $inst->id,
                'penalty_amount' => $penalty,
                'reason' => 'Overdue payment',
                'applied_date' => date('Y-m-d'),
                'status' => 'active'
            ], ['%s','%d','%d','%d','%f','%s','%s','%s']);
            
            $count++;
        }
    }
    
    wp_send_json_success(['message' => "Applied penalties to {$count} installment(s)"]);
}

/**
 * Save Settings
 */
function bntm_ajax_lm_save_settings() {
    check_ajax_referer('lm_settings_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $penalty_enabled = isset($_POST['penalty_enabled']) ? '1' : '0';
    $penalty_type = sanitize_text_field($_POST['penalty_type'] ?? 'fixed');
    $penalty_amount = floatval($_POST['penalty_amount'] ?? 0);
    $penalty_rate = floatval($_POST['penalty_rate'] ?? 0);
    $grace_period = intval($_POST['grace_period'] ?? 0);
    $auto_penalty = isset($_POST['auto_penalty']) ? '1' : '0';
    $currency = sanitize_text_field($_POST['currency'] ?? 'PHP');
    
    bntm_set_setting('lm_penalty_enabled', $penalty_enabled);
    bntm_set_setting('lm_penalty_type', $penalty_type);
    bntm_set_setting('lm_penalty_amount', $penalty_amount);
    bntm_set_setting('lm_penalty_rate', $penalty_rate);
    bntm_set_setting('lm_grace_period', $grace_period);
    bntm_set_setting('lm_auto_penalty', $auto_penalty);
    bntm_set_setting('lm_currency', $currency);
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

/**
 * Export Payment to Finance
 */
function bntm_ajax_lm_fn_export_payment() {
    check_ajax_referer('lm_fn_action', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $payments_table = $wpdb->prefix . 'lm_payments';
    $loans_table = $wpdb->prefix . 'lm_loans';
    $payment_id = intval($_POST['payment_id']);
    $business_id = get_current_user_id();
    
    // Get payment with loan info
    $payment = $wpdb->get_row($wpdb->prepare(
        "SELECT p.*, l.loan_number 
         FROM $payments_table p
         INNER JOIN $loans_table l ON p.loan_id = l.id
         WHERE p.id = %d AND p.business_id = %d",
        $payment_id, $business_id
    ));
    
    if (!$payment) {
        wp_send_json_error(['message' => 'Payment not found']);
    }
    
    // Check if already exported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $fn_table WHERE reference_type='lm_payment' AND reference_id=%d",
        $payment_id
    ));
    
    if ($exists) {
        wp_send_json_error(['message' => 'Payment already exported']);
    }
    
    // Insert into finance module
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'type' => 'income',
        'amount' => $payment->amount,
        'category' => 'Loan Payment',
        'notes' => 'Loan Payment - ' . $payment->loan_number . ' (' . date('M d, Y', strtotime($payment->payment_date)) . ')',
        'reference_type' => 'lm_payment',
        'reference_id' => $payment_id,
        'created_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($fn_table, $data, [
        '%s','%d','%s','%f','%s','%s','%s','%d','%s'
    ]);
    
    if ($result) {
        // Update finance summary if function exists
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Payment exported successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to export payment']);
    }
}

/**
 * Revert Payment from Finance
 */
function bntm_ajax_lm_fn_revert_payment() {
    check_ajax_referer('lm_fn_action', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $payment_id = intval($_POST['payment_id']);
    
    $result = $wpdb->delete($fn_table, [
        'reference_type' => 'lm_payment',
        'reference_id' => $payment_id
    ], ['%s', '%d']);
    
    if ($result) {
        // Update finance summary
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Payment reverted from Finance']);
    } else {
        wp_send_json_error(['message' => 'Failed to revert payment']);
    }
}

add_action('wp_ajax_lm_generate_pdf', 'bntm_ajax_lm_generate_pdf');

function bntm_ajax_lm_generate_pdf() {
    check_ajax_referer('lm_pdf_nonce', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        die('Unauthorized');
    }
    
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    $borrowers_table = $wpdb->prefix . 'lm_borrowers';
    $installments_table = $wpdb->prefix . 'lm_installments';
    $loan_id = intval($_GET['loan_id']);
    $business_id = get_current_user_id();
    
    // Get loan with borrower info
    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT l.*, b.borrower_name, b.contact_number, b.email, b.address, b.id_type, b.id_number
         FROM $loans_table l
         INNER JOIN $borrowers_table b ON l.borrower_id = b.id
         WHERE l.id = %d AND l.business_id = %d",
        $loan_id, $business_id
    ));
    
    if (!$loan) {
        die('Loan not found');
    }
    
    // Get installments
    $installments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $installments_table WHERE loan_id = %d ORDER BY installment_number",
        $loan_id
    ));
    
    // Get business info
    $business_name = get_bloginfo('name');
    $current_user = wp_get_current_user();
    
    // Set headers for PDF download
    header('Content-Type: text/html; charset=utf-8');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Loan Agreement - <?php echo esc_html($loan->loan_number); ?></title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.6;
                color: #000;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
            }
            
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: bold;
            }
            
            .header h2 {
                margin: 5px 0 0 0;
                font-size: 18px;
                font-weight: normal;
            }
            
            .section {
                margin-bottom: 20px;
            }
            
            .section-title {
                font-size: 14px;
                font-weight: bold;
                margin-bottom: 10px;
                border-bottom: 1px solid #000;
                padding-bottom: 5px;
            }
            
            .info-table {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .info-table td {
                padding: 5px;
                vertical-align: top;
            }
            
            .info-table td:first-child {
                width: 200px;
                font-weight: bold;
            }
            
            .schedule-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            
            .schedule-table th,
            .schedule-table td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }
            
            .schedule-table th {
                background-color: #f0f0f0;
                font-weight: bold;
            }
            
            .schedule-table td.amount {
                text-align: right;
            }
            
            .schedule-table tfoot td {
                font-weight: bold;
                background-color: #f0f0f0;
            }
            
            .signature-section {
                margin-top: 50px;
            }
            
            .signature-box {
                display: inline-block;
                width: 45%;
                margin-top: 30px;
                border-top: 1px solid #000;
                padding-top: 5px;
                text-align: center;
            }
            
            .signature-box.right {
                float: right;
            }
            
            .footer {
                margin-top: 30px;
                padding-top: 10px;
                border-top: 1px solid #000;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
            
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 10px 20px;
                background: #4f46e5;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .print-button:hover {
                background: #4338ca;
            }
        </style>
    </head>
    <body>
        <button onclick="window.print()" class="print-button no-print">Print / Save as PDF</button>
        
        <div class="header">
            <h1><?php echo esc_html($business_name); ?></h1>
            <h2>LOAN AGREEMENT</h2>
            <p>Loan Number: <strong><?php echo esc_html($loan->loan_number); ?></strong></p>
            <p>Date Issued: <?php echo date('F d, Y'); ?></p>
        </div>
        
        <div class="section">
            <div class="section-title">BORROWER INFORMATION</div>
            <table class="info-table">
                <tr>
                    <td>Full Name:</td>
                    <td><?php echo esc_html($loan->borrower_name); ?></td>
                </tr>
                <?php if ($loan->contact_number): ?>
                <tr>
                    <td>Contact Number:</td>
                    <td><?php echo esc_html($loan->contact_number); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($loan->email): ?>
                <tr>
                    <td>Email:</td>
                    <td><?php echo esc_html($loan->email); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($loan->address): ?>
                <tr>
                    <td>Address:</td>
                    <td><?php echo esc_html($loan->address); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($loan->id_type && $loan->id_number): ?>
                <tr>
                    <td>Valid ID:</td>
                    <td><?php echo esc_html($loan->id_type); ?> - <?php echo esc_html($loan->id_number); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="section">
            <div class="section-title">LOAN DETAILS</div>
            <table class="info-table">
                <tr>
                    <td>Loan Amount (Principal):</td>
                    <td><strong>₱<?php echo number_format($loan->loan_amount, 2); ?></strong></td>
                </tr>
                <tr>
                    <td>Interest Rate:</td>
                    <td><?php echo $loan->interest_rate; ?>% per annum (<?php echo ucfirst(str_replace('_', ' ', $loan->interest_type)); ?>)</td>
                </tr>
                <?php if ($loan->interest_type === 'compound'): ?>
                <tr>
                    <td>Compounding Frequency:</td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $loan->compound_frequency)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Total Interest:</td>
                    <td>₱<?php echo number_format($loan->total_interest, 2); ?></td>
                </tr>
                <?php if ($loan->processing_fee > 0): ?>
                <tr>
                    <td>Processing Fee:</td>
                    <td>₱<?php echo number_format($loan->processing_fee, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>Total Amount to Repay:</td>
                    <td><strong>₱<?php echo number_format($loan->total_amount, 2); ?></strong></td>
                </tr>
                <tr>
                    <td>Loan Term:</td>
                    <td><?php echo $loan->loan_term; ?> <?php echo $loan->term_unit; ?></td>
                </tr>
                <tr>
                    <td>Installment Amount:</td>
                    <td><strong>₱<?php echo number_format($loan->installment_amount, 2); ?></strong></td>
                </tr>
                <tr>
                    <td>Start Date:</td>
                    <td><?php echo date('F d, Y', strtotime($loan->start_date)); ?></td>
                </tr>
                <tr>
                    <td>Maturity Date:</td>
                    <td><?php echo date('F d, Y', strtotime($loan->maturity_date)); ?></td>
                </tr>
                <tr>
                    <td>Status:</td>
                    <td><?php echo ucfirst($loan->status); ?></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($installments)): ?>
        <div class="section">
            <div class="section-title">REPAYMENT SCHEDULE</div>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Due Date</th>
                        <th class="amount">Principal</th>
                        <th class="amount">Interest</th>
                        <th class="amount">Installment</th>
                        <th class="amount">Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_principal = 0;
                    $total_interest = 0;
                    $total_installment = 0;
                    
                    foreach ($installments as $inst): 
                        $total_principal += $inst->principal_amount;
                        $total_interest += $inst->interest_amount;
                        $total_installment += $inst->installment_amount;
                    ?>
                    <tr>
                        <td><?php echo $inst->installment_number; ?></td>
                        <td><?php echo date('M d, Y', strtotime($inst->due_date)); ?></td>
                        <td class="amount">₱<?php echo number_format($inst->principal_amount, 2); ?></td>
                        <td class="amount">₱<?php echo number_format($inst->interest_amount, 2); ?></td>
                        <td class="amount">₱<?php echo number_format($inst->installment_amount, 2); ?></td>
                        <td class="amount">₱<?php echo number_format($inst->balance, 2); ?></td>
                        <td><?php echo ucfirst($inst->status); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2">TOTAL</td>
                        <td class="amount">₱<?php echo number_format($total_principal, 2); ?></td>
                        <td class="amount">₱<?php echo number_format($total_interest, 2); ?></td>
                        <td class="amount">₱<?php echo number_format($total_installment, 2); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <div class="section-title">TERMS AND CONDITIONS</div>
            <ol style="padding-left: 20px;">
                <li>The borrower agrees to repay the loan amount plus interest as per the repayment schedule above.</li>
                <li>Payment must be made on or before the due date of each installment.</li>
                <li>Late payments may incur additional penalty charges as per company policy.</li>
                <li>The borrower has the right to prepay the loan in full or in part at any time.</li>
                <li>In case of default, the lender reserves the right to take appropriate legal action.</li>
                <li>This agreement is binding upon both parties and their respective heirs and assigns.</li>
            </ol>
        </div>
        
        <?php if ($loan->notes): ?>
        <div class="section">
            <div class="section-title">ADDITIONAL NOTES</div>
            <p><?php echo nl2br(esc_html($loan->notes)); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="signature-section">
            <div class="signature-box">
                <strong><?php echo esc_html($loan->borrower_name); ?></strong><br>
                Borrower Signature / Date
            </div>
            
            <div class="signature-box right">
                <strong><?php echo esc_html($business_name); ?></strong><br>
                Lender Signature / Date
            </div>
            <div style="clear: both;"></div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated document. Generated on <?php echo date('F d, Y h:i A'); ?></p>
            <p><?php echo esc_html($business_name); ?> | Loan Management System</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
// ========================================
// FRONTEND SHORTCODES
// ========================================

/**
 * Borrower Application Form (Public)
 */
function bntm_shortcode_lm_borrower_application() {
    $nonce = wp_create_nonce('lm_public_application');
    
    ob_start();
    ?>
    <div class="lm-public-application">
        <h2>Loan Application</h2>
        <p>Please fill out the form below to apply for a loan.</p>
        
        <form id="publicApplicationForm" class="bntm-form">
            <div class="bntm-form-group">
                <label>Full Name *</label>
                <input type="text" name="borrower_name" required class="bntm-input">
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Contact Number *</label>
                    <input type="text" name="contact_number" required class="bntm-input">
                </div>
                
                <div class="bntm-form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="bntm-input">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Address *</label>
                <textarea name="address" rows="2" required class="bntm-input"></textarea>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>ID Type *</label>
                    <select name="id_type" required class="bntm-input">
                        <option value="">-- Select --</option>
                        <option value="National ID">National ID</option>
                        <option value="Driver's License">Driver's License</option>
                        <option value="Passport">Passport</option>
                        <option value="SSS">SSS</option>
                        <option value="UMID">UMID</option>
                        <option value="Voter's ID">Voter's ID</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>ID Number *</label>
                    <input type="text" name="id_number" required class="bntm-input">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Additional Information</label>
                <textarea name="notes" rows="3" class="bntm-input" placeholder="Tell us more about yourself and your loan purpose"></textarea>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="bntm-btn-primary">Submit Application</button>
            </div>
            
            <div id="application-message" style="margin-top: 15px;"></div>
        </form>
    </div>
    
    <style>
    .lm-public-application {
        max-width: 700px;
        margin: 0 auto;
        padding: 30px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .lm-public-application h2 {
        margin-top: 0;
        color: #1f2937;
    }
    </style>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    document.getElementById('publicApplicationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'lm_add_borrower');
        formData.append('_ajax_nonce', '<?php echo $nonce; ?>');
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                document.getElementById('application-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-success">' +
                    'Application submitted successfully! We will contact you soon.' +
                    '</div>';
                this.reset();
            } else {
                document.getElementById('application-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
            }
            btn.disabled = false;
            btn.textContent = 'Submit Application';
        })
        .catch(err => {
            document.getElementById('application-message').innerHTML = 
                '<div class="bntm-notice bntm-notice-error">Error submitting application</div>';
            btn.disabled = false;
            btn.textContent = 'Submit Application';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Format currency
 */
function lm_format_currency($amount) {
    $currency = bntm_get_setting('lm_currency', 'PHP');
    
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'PHP' => '₱'
    ];
    
    $symbol = isset($symbols[$currency]) ? $symbols[$currency] : '₱';
    
    return $symbol . number_format($amount, 2);
}

/**
 * Get loan statistics
 */
function lm_get_loan_stats($business_id) {
    global $wpdb;
    $loans_table = $wpdb->prefix . 'lm_loans';
    
    return [
        'total_loans' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $loans_table WHERE business_id = %d",
            $business_id
        )),
        'active_loans' => $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $loans_table WHERE business_id = %d AND status = 'active'",
            $business_id
        )),
        'total_disbursed' => $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(loan_amount) FROM $loans_table WHERE business_id = %d AND status IN ('active', 'completed')",
            $business_id
        )) ?: 0
    ];
}
/**
 * Calculate loan details based on interest type
 */
/**
 * Calculate loan details based on interest type and diminishing flag
 */
function lm_calculate_loan_details($principal, $rate, $term, $term_unit, $interest_type, $compound_frequency = 'monthly', $processing_fee = 0, $is_diminishing = false) {
    $rate_decimal = $rate / 100;
    $total_interest = 0;
    $installment_amount = 0;
    
    if ($is_diminishing) {
        // DIMINISHING BALANCE CALCULATION
        // Calculate periodic interest rate
        $periodic_rate = $rate_decimal / 12; // Default to monthly
        if ($term_unit === 'weeks') {
            $periodic_rate = $rate_decimal / 52;
        }
        
        // Calculate using amortization formula
        // M = P * [r(1+r)^n] / [(1+r)^n - 1]
        $numerator = $periodic_rate * pow(1 + $periodic_rate, $term);
        $denominator = pow(1 + $periodic_rate, $term) - 1;
        
        if ($denominator > 0) {
            $installment_amount = $principal * ($numerator / $denominator);
        } else {
            // Fallback for edge cases (like 0% interest)
            $installment_amount = $principal / $term;
        }
        
        // Total amount to be paid
        $total_amount = ($installment_amount * $term) + $processing_fee;
        $total_interest = $total_amount - $principal - $processing_fee;
        
    } else {
        // STANDARD CALCULATIONS (Non-Diminishing)
        if ($interest_type === 'flat') {
            // Flat rate: Interest = Principal × Rate × Time
            $total_interest = ($principal * $rate * $term) / 100;
            $total_amount = $principal + $total_interest + $processing_fee;
            $installment_amount = $total_amount / $term;
            
        } elseif ($interest_type === 'simple') {
            // Simple interest: I = P × r × t
            $total_interest = ($principal * $rate * $term) / 100;
            $total_amount = $principal + $total_interest + $processing_fee;
            $installment_amount = $total_amount / $term;
            
        } elseif ($interest_type === 'compound') {
            // Compound interest: A = P(1 + r/n)^(nt)
            $compound_periods = [
                'daily' => 365,
                'weekly' => 52,
                'biweekly' => 26,
                'monthly' => 12,
                'quarterly' => 4,
                'semiannually' => 2,
                'annually' => 1
            ];
            
            $n = isset($compound_periods[$compound_frequency]) ? $compound_periods[$compound_frequency] : 12;
            
            // Convert term to years
            $time_in_years = $term;
            if ($term_unit === 'weeks') {
                $time_in_years = $term / 52;
            } elseif ($term_unit === 'months') {
                $time_in_years = $term / 12;
            }
            
            // Calculate future value: A = P(1 + r/n)^(nt)
            $future_value = $principal * pow((1 + $rate_decimal / $n), ($n * $time_in_years));
            $total_interest = $future_value - $principal;
            $total_amount = $principal + $total_interest + $processing_fee;
            $installment_amount = $total_amount / $term;
        }
    }
    
    return [
        'total_interest' => $total_interest,
        'total_amount' => $total_amount,
        'installment_amount' => $installment_amount
    ];
}
function lm_calculate_interest($principal, $rate, $term, $term_unit, $interest_type, $compound_frequency = 'monthly') {
    $rate_decimal = $rate / 100;
    
    if ($interest_type === 'flat') {
        // Flat rate: Interest = Principal × Rate × Time
        return ($principal * $rate * $term) / 100;
        
    } elseif ($interest_type === 'simple') {
        // Simple interest: I = P × r × t
        return ($principal * $rate * $term) / 100;
        
    } elseif ($interest_type === 'compound') {
        // Compound interest: A = P(1 + r/n)^(nt), where I = A - P
        
        // Compounding periods per year
        $compound_periods = [
            'daily' => 365,
            'weekly' => 52,
            'biweekly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
            'semiannually' => 2,
            'annually' => 1
        ];
        
        $n = isset($compound_periods[$compound_frequency]) ? $compound_periods[$compound_frequency] : 12;
        
        // Convert term to years
        $time_in_years = $term;
        if ($term_unit === 'weeks') {
            $time_in_years = $term / 52;
        } elseif ($term_unit === 'months') {
            $time_in_years = $term / 12;
        }
        
        // Calculate future value: A = P(1 + r/n)^(nt)
        $future_value = $principal * pow((1 + $rate_decimal / $n), ($n * $time_in_years));
        
        // Interest = Future Value - Principal
        return $future_value - $principal;
    }
    
    return 0;
}
function lm_process_diminishing_payment($loan, $payment_amount, $payment_date) {
    global $wpdb;
    $installments_table = $wpdb->prefix . 'lm_installments';
    
    // Get all installments
    $installments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $installments_table 
         WHERE loan_id = %d 
         ORDER BY installment_number ASC",
        $loan->id
    ));
    
    $remaining_payment = $payment_amount;
    $recalculate_from = null;
    
    // First, apply payment to unpaid installments in order
    foreach ($installments as $installment) {
        if ($remaining_payment <= 0) break;
        
        if ($installment->balance > 0) {
            $to_pay = min($remaining_payment, $installment->balance);
            $new_paid = $installment->paid_amount + $to_pay;
            $new_balance = $installment->balance - $to_pay;
            $new_status = $new_balance == 0 ? 'paid' : $installment->status;
            
            $wpdb->update(
                $installments_table,
                [
                    'paid_amount' => $new_paid,
                    'balance' => $new_balance,
                    'status' => $new_status,
                    'paid_date' => $new_balance == 0 ? $payment_date : null
                ],
                ['id' => $installment->id],
                ['%f', '%f', '%s', '%s'],
                ['%d']
            );
            
            $remaining_payment -= $to_pay;
            
            // Mark where to start recalculation if this installment is fully paid
            if ($new_balance == 0 && $recalculate_from === null) {
                $recalculate_from = $installment->installment_number + 1;
            }
        }
    }
    
    // If there are future installments to recalculate
    if ($recalculate_from !== null && $recalculate_from <= $loan->loan_term) {
        // Calculate remaining principal
        $total_paid_principal = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(principal_amount) FROM $installments_table 
             WHERE loan_id = %d AND status = 'paid'",
            $loan->id
        )) ?: 0;
        
        $remaining_principal = $loan->loan_amount - $total_paid_principal;
        
        // Calculate remaining term
        $remaining_term = $loan->loan_term - ($recalculate_from - 1);
        
        if ($remaining_term > 0 && $remaining_principal > 0) {
            // Calculate new installment amount for remaining installments
            $periodic_rate = ($loan->interest_rate / 100) / 12; // Monthly rate
            if ($loan->term_unit === 'weeks') {
                $periodic_rate = ($loan->interest_rate / 100) / 52;
            }
            
            // Calculate new installment using amortization formula
            $numerator = $periodic_rate * pow(1 + $periodic_rate, $remaining_term);
            $denominator = pow(1 + $periodic_rate, $remaining_term) - 1;
            
            if ($denominator > 0) {
                $new_installment_amount = $remaining_principal * ($numerator / $denominator);
            } else {
                $new_installment_amount = $remaining_principal / $remaining_term;
            }
            
            // Recalculate future installments
            $current_principal = $remaining_principal;
            
            for ($i = $recalculate_from; $i <= $loan->loan_term; $i++) {
                $installment_index = $i - $recalculate_from;
                
                // Calculate interest on current balance
                $interest_amount = $current_principal * $periodic_rate;
                $principal_amount = $new_installment_amount - $interest_amount;
                
                // Last installment adjustment
                if ($i == $loan->loan_term) {
                    $principal_amount = $current_principal;
                    $installment_total = $principal_amount + $interest_amount;
                } else {
                    $installment_total = $new_installment_amount;
                }
                
                // Update the installment
                $wpdb->update(
                    $installments_table,
                    [
                        'principal_amount' => $principal_amount,
                        'interest_amount' => $interest_amount,
                        'installment_amount' => $installment_total,
                        'balance' => $installment_total // Reset balance to new amount
                    ],
                    [
                        'loan_id' => $loan->id,
                        'installment_number' => $i
                    ],
                    ['%f', '%f', '%f', '%f'],
                    ['%d', '%d']
                );
                
                // Reduce principal for next iteration
                $current_principal -= $principal_amount;
            }
        }
    }
}