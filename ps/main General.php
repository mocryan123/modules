<?php
/**
 * Module Name: Pawnshop & Loan Management
 * Module Slug: ps
 * Description: Complete pawnshop management system with per-transaction ticket numbering, collateral management,
 *              customer photo capture, interest computation with grace period, renewals, redemptions, forfeitures,
 *              and professional document generation with auto-print modal.
 * Version: 2.0.0
 * Author: BNTM
 */

if (!defined('ABSPATH')) exit;

define('BNTM_PS_PATH', dirname(__FILE__) . '/');
define('BNTM_PS_URL', plugin_dir_url(__FILE__));
define('BNTM_PS_PHOTO_DIR', WP_CONTENT_DIR . '/ps-customer-photos/');
define('BNTM_PS_PHOTO_URL', WP_CONTENT_URL . '/ps-customer-photos/');

// Ensure photo directory exists
if (!file_exists(BNTM_PS_PHOTO_DIR)) {
    wp_mkdir_p(BNTM_PS_PHOTO_DIR);
    file_put_contents(BNTM_PS_PHOTO_DIR . '.htaccess', "Options -Indexes\n");
}

// ============================================================
// MODULE CONFIGURATION
// ============================================================

function bntm_ps_get_pages() {
    return ['Pawnshop Dashboard' => '[ps_dashboard]'];
}

function bntm_ps_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;
    return [
        'ps_customers' => "CREATE TABLE {$prefix}ps_customers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            first_name VARCHAR(100) NOT NULL DEFAULT '',
            last_name VARCHAR(100) NOT NULL DEFAULT '',
            middle_name VARCHAR(100) NOT NULL DEFAULT '',
            address TEXT NOT NULL DEFAULT '',
            contact_number VARCHAR(30) NOT NULL DEFAULT '',
            email VARCHAR(150) NOT NULL DEFAULT '',
            id_type VARCHAR(50) NOT NULL DEFAULT '',
            id_number VARCHAR(100) NOT NULL DEFAULT '',
            photo_path VARCHAR(500) NOT NULL DEFAULT '',
            customer_flag ENUM('normal','vip','delinquent','blacklisted') NOT NULL DEFAULT 'normal',
            notes TEXT NOT NULL DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",

        'ps_collaterals' => "CREATE TABLE {$prefix}ps_collaterals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            category ENUM('jewelry','electronics','watches','bags','documents','others') NOT NULL DEFAULT 'others',
            description TEXT NOT NULL DEFAULT '',
            brand VARCHAR(100) NOT NULL DEFAULT '',
            model VARCHAR(100) NOT NULL DEFAULT '',
            serial_number VARCHAR(100) NOT NULL DEFAULT '',
            metal_type VARCHAR(50) NOT NULL DEFAULT '',
            weight_grams DECIMAL(10,4) NOT NULL DEFAULT 0,
            karat VARCHAR(10) NOT NULL DEFAULT '',
            item_condition ENUM('excellent','good','fair','poor') NOT NULL DEFAULT 'good',
            appraised_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            photo_url VARCHAR(500) NOT NULL DEFAULT '',
            status ENUM('pawned','redeemed','forfeited','for_sale','for_auction','sold') NOT NULL DEFAULT 'pawned',
            notes TEXT NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_loan (loan_id)
        ) {$charset};",

        'ps_loans' => "CREATE TABLE {$prefix}ps_loans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            root_ticket VARCHAR(30) NOT NULL DEFAULT '',
            ticket_number VARCHAR(30) UNIQUE NOT NULL,
            parent_loan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            collateral_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            principal DECIMAL(12,2) NOT NULL DEFAULT 0,
            interest_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
            service_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
            penalty_rate DECIMAL(8,4) NOT NULL DEFAULT 1,
            loan_date DATE NOT NULL,
            due_date DATE NOT NULL,
            grace_days INT NOT NULL DEFAULT 0,
            term_months INT NOT NULL DEFAULT 1,
            transaction_type ENUM('new','renewal','partial_payment','additional') NOT NULL DEFAULT 'new',
            additional_to_principal DECIMAL(12,2) NOT NULL DEFAULT 0,
            reduction_from_principal DECIMAL(12,2) NOT NULL DEFAULT 0,
            accrued_interest_carried DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('active','renewed','redeemed','overdue','forfeited') NOT NULL DEFAULT 'active',
            payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
            notes TEXT NOT NULL DEFAULT '',
            redeemed_at DATETIME DEFAULT NULL,
            forfeited_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_root (root_ticket),
            INDEX idx_parent (parent_loan_id)
        ) {$charset};",

        'ps_payments' => "CREATE TABLE {$prefix}ps_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            payment_type ENUM('interest','renewal','redemption','penalty','service_fee','partial_principal') NOT NULL DEFAULT 'interest',
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            interest_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            principal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            service_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
            days_accrued INT NOT NULL DEFAULT 0,
            payment_method VARCHAR(50) NOT NULL DEFAULT 'cash',
            reference_number VARCHAR(100) NOT NULL DEFAULT '',
            processed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT NOT NULL DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_loan (loan_id)
        ) {$charset};",

        'ps_ticket_history' => "CREATE TABLE {$prefix}ps_ticket_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            root_ticket VARCHAR(30) NOT NULL DEFAULT '',
            loan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ticket_number VARCHAR(30) NOT NULL DEFAULT '',
            event_type ENUM('created','renewed','payment','redeemed','forfeited','partial_payment','additional_principal','reduced_principal') NOT NULL DEFAULT 'created',
            principal_before DECIMAL(12,2) NOT NULL DEFAULT 0,
            principal_after DECIMAL(12,2) NOT NULL DEFAULT 0,
            interest_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
            amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
            due_date DATE NOT NULL,
            notes TEXT NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_root (root_ticket),
            INDEX idx_loan (loan_id),
            INDEX idx_business (business_id)
        ) {$charset};",

        'ps_document_log' => "CREATE TABLE {$prefix}ps_document_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            document_type VARCHAR(100) NOT NULL DEFAULT '',
            printed_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            copies INT NOT NULL DEFAULT 1,
            notes TEXT NOT NULL DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'printed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_loan (loan_id)
        ) {$charset};",
    ];
}

function bntm_ps_get_shortcodes() {
    return ['ps_dashboard' => 'bntm_shortcode_ps'];
}

function bntm_ps_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_ps_get_tables();
    foreach ($tables as $sql) { dbDelta($sql); }
    return count($tables);
}

// ============================================================
// AJAX ACTION HOOKS
// ============================================================

add_action('wp_ajax_ps_create_loan',              'bntm_ajax_ps_create_loan');
add_action('wp_ajax_ps_renew_loan',               'bntm_ajax_ps_renew_loan');
add_action('wp_ajax_ps_redeem_loan',              'bntm_ajax_ps_redeem_loan');
add_action('wp_ajax_ps_forfeit_loan',             'bntm_ajax_ps_forfeit_loan');
add_action('wp_ajax_ps_get_loan_detail',          'bntm_ajax_ps_get_loan_detail');
add_action('wp_ajax_ps_search_loans',             'bntm_ajax_ps_search_loans');
add_action('wp_ajax_ps_add_customer',             'bntm_ajax_ps_add_customer');
add_action('wp_ajax_ps_edit_customer',            'bntm_ajax_ps_edit_customer');
add_action('wp_ajax_ps_get_customer_profile',     'bntm_ajax_ps_get_customer_profile');
add_action('wp_ajax_ps_flag_customer',            'bntm_ajax_ps_flag_customer');
add_action('wp_ajax_ps_search_customers',         'bntm_ajax_ps_search_customers');
add_action('wp_ajax_ps_get_customers_list',       'bntm_ajax_ps_get_customers_list');
add_action('wp_ajax_ps_save_customer_photo',      'bntm_ajax_ps_save_customer_photo');
add_action('wp_ajax_ps_add_collateral',           'bntm_ajax_ps_add_collateral');
add_action('wp_ajax_ps_update_collateral_status', 'bntm_ajax_ps_update_collateral_status');
add_action('wp_ajax_ps_get_collateral_detail',    'bntm_ajax_ps_get_collateral_detail');
add_action('wp_ajax_ps_record_payment',           'bntm_ajax_ps_record_payment');
add_action('wp_ajax_ps_compute_interest',         'bntm_ajax_ps_compute_interest');
add_action('wp_ajax_ps_get_payment_history',      'bntm_ajax_ps_get_payment_history');
add_action('wp_ajax_ps_generate_document',        'bntm_ajax_ps_generate_document');
add_action('wp_ajax_ps_log_print',                'bntm_ajax_ps_log_print');
add_action('wp_ajax_ps_get_document_history',     'bntm_ajax_ps_get_document_history');
add_action('wp_ajax_ps_save_settings',            'bntm_ajax_ps_save_settings');
add_action('wp_ajax_ps_add_payment_method',       'bntm_ajax_ps_add_payment_method');
add_action('wp_ajax_ps_remove_payment_method',    'bntm_ajax_ps_remove_payment_method');
add_action('wp_ajax_ps_fn_export_transaction',    'bntm_ajax_ps_fn_export_transaction');
add_action('wp_ajax_ps_fn_revert_transaction',    'bntm_ajax_ps_fn_revert_transaction');
add_action('wp_ajax_ps_quick_update_status',      'bntm_ajax_ps_quick_update_status');
add_action('wp_ajax_ps_bulk_mark_overdue',        'bntm_ajax_ps_bulk_mark_overdue');
add_action('wp_ajax_ps_delete_loan',              'bntm_ajax_ps_delete_loan');
add_action('wp_ajax_ps_delete_customer',          'bntm_ajax_ps_delete_customer');
add_action('wp_ajax_ps_generate_auction_notice',  'bntm_ajax_ps_generate_auction_notice');
add_action('wp_ajax_ps_get_loan_compute',         'bntm_ajax_ps_get_loan_compute');
add_action('wp_ajax_ps_get_ticket_history',       'bntm_ajax_ps_get_ticket_history');


// ============================================================
// HELPERS
// ============================================================

function ps_auto_mark_overdue($business_id) {
    global $wpdb;
    $grace = (int)bntm_get_setting('ps_grace_period', '0');
    $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}ps_loans SET status='overdue'
         WHERE business_id=%d AND status IN ('active','renewed')
         AND DATEDIFF(CURDATE(), due_date) > %d",
        $business_id, $grace
    ));
}

/**
 * Compute daily accrued interest for a loan.
 * Within grace period: per-day interest (principal × rate/30/100 × days).
 * After grace period: grace days free, then daily interest applies.
 */
function ps_compute_interest_breakdown($loan) {
    $today      = time();
    $loan_date  = strtotime($loan->loan_date);
    $due_date   = strtotime($loan->due_date);

    $grace_days = (int)($loan->grace_days ?? bntm_get_setting('ps_grace_period', '0'));
    $rate       = (float)$loan->interest_rate; // % per month

    $days_elapsed  = max(0, floor(($today - $loan_date) / 86400));
    $days_past_due = max(0, floor(($today - $due_date) / 86400));

    // --- NEW: Split into months and remaining days ---
    $months_elapsed   = floor($days_elapsed / 30);
    $remaining_days   = $days_elapsed % 30;

    // --- Rates ---
    $monthly_rate = $rate / 100;
    $daily_rate   = $monthly_rate / 30;

    // --- Regular Interest ---
    $monthly_interest  = $loan->principal * $monthly_rate * $months_elapsed;
    $daily_interest    = $loan->principal * $daily_rate * $remaining_days;

    $regular_interest  = $monthly_interest + $daily_interest;

    // --- Grace logic ---
    $effective_overdue_days = max(0, $days_past_due - $grace_days);

    // --- Penalty (also hybrid if needed, but usually daily is fine) ---
    $penalty_rate  = (float)$loan->penalty_rate; // % per month
    $daily_penalty = ($penalty_rate / 100) / 30;

    $penalty_interest = $loan->principal * $daily_penalty * $effective_overdue_days;

    // --- Carried interest ---
    $carried = (float)($loan->accrued_interest_carried ?? 0);

    return [
        'days_elapsed'        => $days_elapsed,
        'months_elapsed'      => $months_elapsed,
        'remaining_days'      => $remaining_days,
        'days_past_due'       => $days_past_due,
        'effective_overdue'   => $effective_overdue_days,
        'grace_days'          => $grace_days,

        'monthly_interest'    => round($monthly_interest, 2),
        'daily_interest'      => round($daily_interest, 2),

        'regular_interest'    => round($regular_interest, 2),
        'penalty_interest'    => round($penalty_interest, 2),
        'carried_interest'    => round($carried, 2),

        'total_interest'      => round($regular_interest + $penalty_interest + $carried, 2),
        'total_due'           => round(
            $loan->principal + $regular_interest + $penalty_interest + $carried + $loan->service_fee,
            2
        ),

        'principal'           => (float)$loan->principal,
        'service_fee'         => (float)$loan->service_fee,
    ];
}

function ps_generate_ticket_number($business_id, $root_ticket = '') {
    global $wpdb;
    $prefix = strtoupper(trim(bntm_get_setting('ps_ticket_prefix', 'PT'))) ?: 'PT';
    $mode   = bntm_get_setting('ps_ticket_mode', 'sequential');
    $start  = max(1, (int)bntm_get_setting('ps_ticket_start', '1'));

    if ($mode === 'random') {
        // Generate unique random 6-digit alphanumeric
        do {
            $rand = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ps_loans WHERE ticket_number=%s",
                $prefix . '-' . $rand
            ));
        } while ($exists > 0);
        return $prefix . '-' . $rand;
    } elseif ($mode === 'sequential_global') {
        // Global sequential - never resets
        $last = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(ticket_number,'-',-1) AS UNSIGNED)) FROM {$wpdb->prefix}ps_loans WHERE ticket_number LIKE %s",
            $prefix . '-%'
        ));
        $next = max($start, $last + 1);
        return sprintf('%s-%05d', $prefix, $next);
    } else {
        // Monthly reset, while staying globally unique across all businesses.
        $ym   = date('Ym');
        $like = $wpdb->esc_like($prefix . '-' . $ym . '-') . '%';
        $last = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(ticket_number,'-',-1) AS UNSIGNED)) FROM {$wpdb->prefix}ps_loans WHERE ticket_number LIKE %s",
            $like
        ));
        $next = max($start, $last + 1);
        return sprintf('%s-%s-%04d', $prefix, $ym, $next);
    }
}
function ps_log_ticket_event($data) {
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ps_ticket_history', array_merge(
        ['rand_id' => bntm_rand_id()],
        $data
    ));
}

// ============================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================

function bntm_shortcode_ps() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Pawnshop Management System.</div>';
    }
    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    ps_auto_mark_overdue($business_id);

    $nonces = [
        'create'   => wp_create_nonce('ps_create_nonce'),
        'renew'    => wp_create_nonce('ps_renew_nonce'),
        'redeem'   => wp_create_nonce('ps_redeem_nonce'),
        'forfeit'  => wp_create_nonce('ps_forfeit_nonce'),
        'loan'     => wp_create_nonce('ps_loan_nonce'),
        'customer' => wp_create_nonce('ps_customer_nonce'),
        'search'   => wp_create_nonce('ps_search_nonce'),
        'collat'   => wp_create_nonce('ps_collat_nonce'),
        'payment'  => wp_create_nonce('ps_payment_nonce'),
        'doc'      => wp_create_nonce('ps_doc_nonce'),
        'settings' => wp_create_nonce('ps_settings_nonce'),
        'fn'       => wp_create_nonce('ps_fn_action'),
    ];

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var PS_NONCES = <?php echo json_encode($nonces); ?>;
    var PS_PHOTO_URL = '<?php echo esc_js(BNTM_PS_PHOTO_URL); ?>';
    var psLostTicketFee = <?php echo (float)bntm_get_setting('ps_lost_ticket_fee','100.00'); ?>;
    </script>
    <div class="bntm-ps-container">
        <?php /* ======= TABS ======= */ ?>
        <div class="bntm-tabs">
            <?php
            $tabs = [
                'overview'    => ['icon'=>'grid','label'=>'Overview'],
                'loans'       => ['icon'=>'file-text','label'=>'Pawn Tickets'],
                'collaterals' => ['icon'=>'star','label'=>'Collaterals'],
                'customers'   => ['icon'=>'users','label'=>'Customers'],
                'payments'    => ['icon'=>'credit-card','label'=>'Payments'],
                'documents'   => ['icon'=>'printer','label'=>'Documents'],
                'reports'     => ['icon'=>'bar-chart','label'=>'Reports'],
                'settings'    => ['icon'=>'settings','label'=>'Settings'],
            ];
            $icons = [
                'grid'        => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1" stroke-width="2"/><rect x="14" y="3" width="7" height="7" rx="1" stroke-width="2"/><rect x="3" y="14" width="7" height="7" rx="1" stroke-width="2"/><rect x="14" y="14" width="7" height="7" rx="1" stroke-width="2"/></svg>',
                'file-text'   => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'star'        => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>',
                'users'       => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
                'credit-card' => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" stroke-width="2"/><line x1="1" y1="10" x2="23" y2="10" stroke-width="2"/></svg>',
                'printer'     => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline stroke-width="2" points="6 9 6 2 18 2 18 9"/><path stroke-width="2" d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8" stroke-width="2"/></svg>',
                'bar-chart'   => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10" stroke-width="2"/><line x1="12" y1="20" x2="12" y2="4" stroke-width="2"/><line x1="6" y1="20" x2="6" y2="14" stroke-width="2"/><line x1="2" y1="20" x2="22" y2="20" stroke-width="2"/></svg>',
                'settings'    => '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-width="2" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
            ];
            foreach ($tabs as $key => $tab) {
                if ($key === 'reports' && !function_exists('bntm_is_module_enabled')) continue;
                $act = $active_tab === $key ? 'active' : '';
                echo '<a href="?tab='.$key.'" class="bntm-tab '.$act.'">'.$icons[$tab['icon']].' '.$tab['label'].'</a>';
            }
            ?>
        </div>
        <div class="bntm-tab-content">
            <?php
            switch ($active_tab) {
                case 'overview':    echo ps_overview_tab($business_id);    break;
                case 'loans':       echo ps_loans_tab($business_id);       break;
                case 'collaterals': echo ps_collaterals_tab($business_id); break;
                case 'customers':   echo ps_customers_tab($business_id);   break;
                case 'payments':    echo ps_payments_tab($business_id);    break;
                case 'finance':     echo ps_finance_tab($business_id);     break;
                case 'documents':   echo ps_documents_tab($business_id);   break;
                case 'reports':     echo ps_reports_tab($business_id);     break;
                case 'settings':    echo ps_settings_tab($business_id);    break;
            }
            ?>
        </div>
    </div>

    <?php echo ps_render_modals(); ?>

    <style>
    :root{--ps-primary:#1e40af;--ps-primary-h:#1d3a9d;--ps-green:#059669;--ps-red:#dc2626;--ps-amber:#d97706;--ps-purple:#7c3aed;--ps-cyan:#0891b2;}
    .bntm-ps-container{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
    .ps-status-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;gap:4px;}
    .ps-status-active{background:#dcfce7;color:#166534;}.ps-status-renewed{background:#dbeafe;color:#1e40af;}
    .ps-status-redeemed{background:#f3f4f6;color:#374151;}.ps-status-overdue{background:#fef3c7;color:#92400e;}
    .ps-status-forfeited{background:#fee2e2;color:#991b1b;}
    .ps-flag-normal{background:#f3f4f6;color:#374151;}.ps-flag-vip{background:#fef9c3;color:#854d0e;}
    .ps-flag-delinquent{background:#fef3c7;color:#92400e;}.ps-flag-blacklisted{background:#fee2e2;color:#991b1b;}
    .ps-collateral-cat{display:inline-block;padding:2px 7px;border-radius:4px;font-size:11px;background:#e0e7ff;color:#3730a3;}
    .ps-action-btn{border:none;cursor:pointer;border-radius:6px;padding:5px 9px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:3px;transition:opacity .15s;}
    .ps-action-btn:hover{opacity:.8;}
    .ps-btn-view{background:#eff6ff;color:#1d4ed8;}.ps-btn-renew{background:#f0fdf4;color:#15803d;}
    .ps-btn-redeem{background:#ecfdf5;color:#059669;}.ps-btn-forfeit{background:#fef2f2;color:#dc2626;}
    .ps-btn-print{background:#f5f3ff;color:#7c3aed;}
    .bntm-form-group{margin-bottom:12px;}
    .bntm-form-group label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;}
    .bntm-form-group input,.bntm-form-group select,.bntm-form-group textarea{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;color:#111827;background:#fff;box-sizing:border-box;transition:border-color .15s;}
    .bntm-form-group input:focus,.bntm-form-group select:focus,.bntm-form-group textarea:focus{outline:none;border-color:var(--bntm-primary,#1e40af);box-shadow:0 0 0 3px rgba(30,64,175,.1);}
    .bntm-form-group textarea{resize:vertical;}
    .bntm-form-group input[readonly]{background:#f9fafb;cursor:not-allowed;}
    .ps-search-bar{display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap;}
    .ps-search-bar input,.ps-search-bar select{padding:7px 10px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;}
    /* Print doc styles */
    .ps-doc-preview{padding:24px 28px;font-family:'Arial',sans-serif;font-size:12px;line-height:1.5;color:#000;}
    .ps-doc-header{text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:12px;}
    .ps-doc-title{font-size:18px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:2px;}
    .ps-doc-subtitle{font-size:11px;color:#333;}
    .ps-doc-section-title{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid #000;padding-bottom:2px;margin:10px 0 6px;color:#000;}
    .ps-doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;}
    .ps-doc-field{display:flex;gap:6px;font-size:11px;padding:1px 0;}
    .ps-doc-field-label{font-weight:700;color:#000;min-width:110px;flex-shrink:0;}
    .ps-doc-amounts{width:100%;border-collapse:collapse;margin:6px 0;font-size:11px;}
    .ps-doc-amounts th{background:#000;color:#fff;padding:4px 7px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.3px;}
    .ps-doc-amounts td{padding:4px 7px;border-bottom:1px solid #ddd;}
    .ps-doc-amounts tr:last-child td{border-bottom:2px solid #000;font-weight:700;background:#f0f0f0;}
    .ps-doc-signatures{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:28px;}
    .ps-doc-sig-block{text-align:center;}
    .ps-doc-sig-line{border-top:1px solid #000;padding-top:4px;font-size:10px;color:#333;margin-top:24px;}
    /* Camera styles */
    #ps-camera-preview{width:100%;border-radius:8px;background:#000;aspect-ratio:4/3;object-fit:cover;}
    #ps-camera-canvas{display:none;}
    .ps-photo-thumb{width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb;}
    /* Quick action bar */
    .ps-quick-bar{display:flex;gap:8px;flex-wrap:wrap;padding:10px 14px;background:var(--bntm-primary,#1e40af);border-radius:8px;margin-bottom:16px;align-items:center;}
    .ps-quick-bar .ps-qbar-label{color:rgba(255,255,255,.55);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-right:4px;white-space:nowrap;}
    .ps-quick-bar .ps-qbtn{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.22);border-radius:5px;padding:5px 11px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:background .15s;white-space:nowrap;}
    .ps-quick-bar .ps-qbtn:hover{background:rgba(255,255,255,.26);}
    .ps-quick-bar .ps-qbtn.primary{background:#fff;color:var(--bntm-primary,#1e40af);}
    .ps-quick-bar .ps-qbtn.primary:hover{background:#f0f9ff;}
    /* Ticket chain badge */
    .ps-ticket-chain{font-size:10px;color:#6366f1;font-weight:700;font-family:monospace;background:#eef2ff;padding:1px 6px;border-radius:10px;display:inline-block;margin-top:2px;}
    .ps-shortcut-key {
        display: inline-flex;
        align-items: center;
        background: rgba(255,255,255,.15);
        color: rgba(255,255,255,.85);
        border: 1px solid rgba(255,255,255,.28);
        border-bottom-width: 2px;
        border-radius: 4px;
        padding: 1px 6px;
        font-size: 10px;
        font-family: 'Courier New', monospace;
        font-weight: 600;
        line-height: 1.6;
        margin-left: 3px;
        letter-spacing: .2px;
        flex-shrink: 0;
    }
    .ps-qbtn.primary .ps-shortcut-key {
        background: rgba(30,64,175,.15);
        color: var(--ps-primary, #1e40af);
        border-color: rgba(30,64,175,.25);
    }
    </style>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Pawnshop Management', $content);
}

// ============================================================
// RENDER ALL MODALS
// ============================================================
function ps_render_modals() {
    ob_start();
    ?>

    <!-- ======== PRINT MODAL ======== -->
    <div id="ps-print-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;width:900px;max-width:95vw;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,.4);">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
                <div>
                    <h3 style="margin:0;font-size:16px;font-weight:700;color:#111;" id="print-modal-title">Document Preview</h3>
                    <p style="margin:3px 0 0;font-size:12px;color:#6b7280;" id="print-modal-subtitle">Review before printing</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;">
                    <div style="display:flex;align-items:center;gap:6px;padding:6px 10px;background:#fff;border:1px solid #d1d5db;border-radius:7px;">
                        <label style="font-size:12px;color:#374151;">Copies:</label>
                        <input type="number" id="print-copies" value="1" min="1" max="10" style="width:44px;border:1px solid #d1d5db;border-radius:5px;padding:2px 4px;font-size:12px;">
                    </div>
                    <button onclick="psPrintDocument()" style="background:var(--bntm-primary,#1e40af);color:#fff;border:none;border-radius:6px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;">Print Document</button>
                    <button onclick="psDismissPrint()" style="background:#fff;color:#374151;border:1px solid #d1d5db;border-radius:8px;padding:8px 14px;font-size:13px;cursor:pointer;">Close</button>
                </div>
            </div>
            <div id="print-modal-body" style="flex:1;overflow-y:auto;padding:20px;background:#f3f4f6;">
                <div id="ps-print-preview" style="background:#fff;max-width:720px;margin:0 auto;box-shadow:0 4px 20px rgba(0,0,0,.12);border-radius:3px;min-height:400px;"></div>
            </div>
        </div>
    </div>

    <!-- ======== LOAN DETAIL MODAL ======== -->
    <div id="ps-loan-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;width:780px;max-width:95vw;max-height:92vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.35);">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:#fff;z-index:1;">
                <h3 style="margin:0;font-size:16px;font-weight:700;">Loan Details</h3>
                <button onclick="document.getElementById('ps-loan-detail-modal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:20px;">✕</button>
            </div>
            <div id="ps-loan-detail-body" style="padding:24px;"><div style="text-align:center;padding:40px;color:#9ca3af;">Loading...</div></div>
        </div>
    </div>

    <!-- ======== CREATE LOAN MODAL ======== -->
    <div id="ps-create-loan-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px 0;">
        <div style="background:#fff;border-radius:12px;width:820px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.35);margin:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #e5e7eb;background:#f8fafc;">
                <h3 style="margin:0;font-size:16px;font-weight:700;">New Pawn Transaction</h3>
                <button onclick="document.getElementById('ps-create-loan-modal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:20px;">✕</button>
            </div>

            <!-- STEP 1: Customer Selection -->
            <div id="cl-step-1" style="padding:24px;">
                <div style="font-size:13px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;">Step 1 — Select or Add Customer</div>
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <input type="text" id="cl-customer-search" placeholder="Search by name, ID number, contact..." style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
                    <button onclick="psOpenCustomerModal(null,'create-loan')" style="background:#1e40af;color:#fff;border:none;border-radius:8px;padding:0 16px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">+ New Customer</button>
                </div>
                <div id="cl-customer-results" style="display:none;border:1px solid #d1d5db;border-radius:8px;max-height:280px;overflow-y:auto;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                <!-- Selected Customer Card -->
                <div id="cl-selected-customer" style="display:none;background:#f0f9ff;border:2px solid #bfdbfe;border-radius:10px;padding:16px;margin-top:12px;">
                    <div style="display:flex;align-items:center;gap:14px;">
                        <img id="cl-cust-photo" src="" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid #3b82f6;display:none;" />
                        <div id="cl-cust-avatar" style="width:64px;height:64px;border-radius:50%;background:#1e40af;display:flex;align-items:center;justify-content:center;color:#fff;font-size:22px;font-weight:800;flex-shrink:0;">?</div>
                        <div style="flex:1;">
                            <div style="font-size:16px;font-weight:800;" id="cl-cust-name">—</div>
                            <div style="font-size:12px;color:#3b82f6;" id="cl-cust-contact">—</div>
                            <div style="font-size:12px;color:#6b7280;" id="cl-cust-id">—</div>
                            <div id="cl-cust-flag-badge"></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:11px;color:#6b7280;">Active Loans</div>
                            <div style="font-size:20px;font-weight:800;color:#1e40af;" id="cl-cust-active-loans">—</div>
                            <button onclick="psClearCustomerSelection()" style="font-size:11px;color:#dc2626;background:none;border:none;cursor:pointer;margin-top:4px;">Change</button>
                        </div>
                    </div>
                    <div id="cl-blacklist-warning" style="display:none;margin-top:10px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#dc2626;font-weight:600;"></div>
                </div>
                <input type="hidden" id="cl-customer-id-val" value="">
                <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                    <button onclick="psCreateLoanStep2()" id="cl-next-btn" disabled style="background:#1e40af;color:#fff;border:none;border-radius:8px;padding:9px 24px;font-size:13px;font-weight:600;cursor:pointer;opacity:.4;">Next: Collateral Details →</button>
                </div>
            </div>

            <!-- STEP 2: Collateral + Loan Terms -->
            <div id="cl-step-2" style="display:none;padding:24px;">
                <div style="font-size:13px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;">Step 2 — Collateral &amp; Loan Terms</div>
                <form id="ps-create-loan-form">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ps_create_nonce'); ?>">
                    <input type="hidden" name="customer_id" id="cl-form-customer-id">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div style="grid-column:1/-1;background:#f8fafc;border-radius:8px;padding:12px 14px;border-left:4px solid #1e40af;">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#1e40af;margin-bottom:4px;">Collateral</div>
                        </div>
                        <div class="bntm-form-group">
                            <label>Category <span style="color:#ef4444;">*</span></label>
                            <select name="collateral_category" required>
                                <option value="">Select category</option>
                                <option value="jewelry">Jewelry</option><option value="electronics">Electronics</option>
                                <option value="watches">Watches</option><option value="bags">Bags</option>
                                <option value="documents">Documents</option><option value="others">Others</option>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Condition <span style="color:#ef4444;">*</span></label>
                            <select name="collateral_condition" required>
                                <option value="excellent">Excellent</option><option value="good" selected>Good</option>
                                <option value="fair">Fair</option><option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="bntm-form-group" style="grid-column:1/-1;">
                            <label>Item Description <span style="color:#ef4444;">*</span></label>
                            <textarea name="collateral_description" rows="2" required placeholder="e.g. 18k Gold Ring with Diamond, 3.5g, Ladies ring"></textarea>
                        </div>
                        <div class="bntm-form-group"><label>Brand</label><input type="text" name="collateral_brand" placeholder="e.g. Rolex, Apple"></div>
                        <div class="bntm-form-group"><label>Model</label><input type="text" name="collateral_model" placeholder="Model name or number"></div>
                        <div class="bntm-form-group"><label>Serial Number</label><input type="text" name="collateral_serial"></div>
                        <div class="bntm-form-group"><label>Metal Type</label><input type="text" name="collateral_metal" placeholder="Gold, Silver, Platinum"></div>
                        <div class="bntm-form-group"><label>Weight (grams)</label><input type="number" name="collateral_weight" step=".01" placeholder="0.00"></div>
                        <div class="bntm-form-group">
                            <label>Karat</label>
                            <select name="collateral_karat"><option value="">N/A</option><option value="10k">10k</option><option value="14k">14k</option><option value="18k">18k</option><option value="21k">21k</option><option value="22k">22k</option><option value="24k">24k</option></select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Appraised Value <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="collateral_appraised_value" step=".01" placeholder="0.00" required id="appraised-val-input">
                        </div>
                        <div class="bntm-form-group">
                            <label>LTV Percentage (%)</label>
                            <input type="number" name="ltv_percentage" step=".01" placeholder="0.00" value="<?php echo esc_attr(bntm_get_setting('ps_ltv_ratio','80')); ?>" id="loan-ltv-input">
                        </div>

                        <div style="grid-column:1/-1;background:#f8fafc;border-radius:8px;padding:12px 14px;border-left:4px solid #059669;margin-top:4px;">
                            <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#059669;margin-bottom:4px;">Loan Terms</div>
                        </div>
                        <div class="bntm-form-group">
                            <label>Principal Amount <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="principal" step=".01" placeholder="0.00" required id="loan-principal-input">
                            <div style="font-size:11px;color:#6b7280;margin-top:4px;">Auto-calculated from appraised value and LTV percentage.</div>
                        </div>
                        <div class="bntm-form-group">
                            <label>Interest Rate (%/month)</label>
                            <input type="number" name="interest_rate" step=".01" value="<?php echo esc_attr(bntm_get_setting('ps_interest_rate','3.00')); ?>" required id="loan-interest-input">
                        </div>
                        <div class="bntm-form-group">
                            <label>Term (months)</label>
                            <select name="term_months" id="loan-term-select" required>
                                <option value="1">1 Month</option><option value="2">2 Months</option>
                                <option value="3">3 Months</option><option value="6">6 Months</option><option value="10">10 Months</option>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Service Fee (₱)</label>
                            <input type="number" name="service_fee" step=".01" placeholder="0.00" value="<?php echo esc_attr(bntm_get_setting('ps_service_fee','0.00')); ?>" id="loan-fee-input">
                        </div>
                        <div class="bntm-form-group">
                            <label>Loan Date</label>
                            <input type="date" name="loan_date" value="<?php echo date('Y-m-d'); ?>" required id="loan-date-input">
                        </div>
                        <div class="bntm-form-group">
                            <label>Due Date (auto)</label>
                            <input type="date" name="due_date" id="loan-due-date-input" readonly>
                        </div>
                        <div class="bntm-form-group">
                            <label>Payment Method</label>
                            <select name="payment_method"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank_transfer">Bank Transfer</option></select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Notes</label>
                            <input type="text" name="notes" placeholder="Internal notes...">
                        </div>

                        <!-- Loan Summary -->
                        <div style="grid-column:1/-1;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;">
                            <div style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Loan Summary</div>
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;">
                                <div><div style="font-size:10px;color:#6b7280;text-transform:uppercase;">Principal</div><div style="font-size:16px;font-weight:800;color:#111;" id="sum-principal">₱0.00</div></div>
                                <div><div style="font-size:10px;color:#6b7280;text-transform:uppercase;">Total Interest</div><div style="font-size:16px;font-weight:800;color:#dc2626;" id="sum-interest">₱0.00</div></div>
                                <div><div style="font-size:10px;color:#6b7280;text-transform:uppercase;">Service Fee</div><div style="font-size:16px;font-weight:800;color:#f59e0b;" id="sum-fee">₱0.00</div></div>
                                <div><div style="font-size:10px;color:#6b7280;text-transform:uppercase;">Total to Redeem</div><div style="font-size:16px;font-weight:800;color:#059669;" id="sum-total">₱0.00</div></div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:10px;margin-top:18px;padding-top:16px;border-top:1px solid #f3f4f6;">
                        <button type="button" onclick="psCreateLoanBack()" style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:9px 18px;font-size:13px;cursor:pointer;">← Back</button>
                        <button type="submit" id="create-loan-submit-btn" style="flex:1;background:var(--bntm-primary,#1e40af);color:#fff;border:none;border-radius:6px;padding:9px;font-size:14px;font-weight:700;cursor:pointer;">Create Pawn Ticket &amp; Print</button>
                    </div>
                    <div id="create-loan-message" style="margin-top:10px;"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- ======== RENEW/PAYMENT MODAL ======== -->
    <div id="ps-renew-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;width:580px;max-width:95vw;max-height:92vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.35);">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #e5e7eb;background:#f8fafc;">
                <h3 style="margin:0;font-size:16px;font-weight:700;">Renewal / Payment</h3>
                <button onclick="document.getElementById('ps-renew-modal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:20px;">✕</button>
            </div>
            <div style="padding:24px;">
                <div id="renew-loan-info" style="background:#f9fafb;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;"></div>
                <form id="ps-renew-form">
                    <input type="hidden" name="loan_id" id="renew-loan-id">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ps_renew_nonce'); ?>">

                    <!-- Transaction Type -->
                    <div class="bntm-form-group">
                        <label style="font-weight:700;font-size:13px;">Transaction Type</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;" id="renew-type-grid">
                            <button type="button" class="ps-type-btn active" data-type="interest_payment" onclick="psSetRenewType('interest_payment')" style="padding:10px;border:2px solid #1e40af;border-radius:6px;background:#eff6ff;color:#1e40af;font-weight:700;cursor:pointer;font-size:12px;">Pay Interest Only</button>
                            <button type="button" class="ps-type-btn" data-type="renewal" onclick="psSetRenewType('renewal')" style="padding:10px;border:2px solid #e5e7eb;border-radius:6px;background:#fff;color:#374151;font-weight:700;cursor:pointer;font-size:12px;">Renew &amp; Extend</button>
                            <button type="button" class="ps-type-btn" data-type="add_principal" onclick="psSetRenewType('add_principal')" style="padding:10px;border:2px solid #e5e7eb;border-radius:6px;background:#fff;color:#374151;font-weight:700;cursor:pointer;font-size:12px;">Add to Principal</button>
                            <button type="button" class="ps-type-btn" data-type="reduce_principal" onclick="psSetRenewType('reduce_principal')" style="padding:10px;border:2px solid #e5e7eb;border-radius:6px;background:#fff;color:#374151;font-weight:700;cursor:pointer;font-size:12px;">Reduce Principal</button>
                        </div>
                        <input type="hidden" name="transaction_type" id="renew-transaction-type" value="interest_payment">
                    </div>

                    <!-- Interest computation display -->
                    <div id="renew-interest-box" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:14px;font-size:13px;">
                        <div style="font-weight:700;margin-bottom:8px;color:#1e40af;">Interest Computation</div>
                        <div id="renew-interest-detail">Loading...</div>
                    </div>

                    <!-- Renewal extension (shown for renewal type) -->
                    <div id="renew-extend-section" style="display:none;">
                        <div class="bntm-form-group">
                            <label>Extend by (months) <span style="color:#ef4444;">*</span></label>
                            <select name="additional_months" id="renew-months">
                                <option value="1">1 Month</option><option value="2">2 Months</option><option value="3">3 Months</option>
                            </select>
                        </div>
                        <div class="bntm-form-group">
                            <label>Renewal Fee (₱)</label>
                            <input type="number" name="renewal_fee" step=".01" placeholder="0.00" value="0">
                        </div>
                    </div>

                    <!-- Principal adjustment (shown for add/reduce types) -->
                    <div id="renew-principal-section" style="display:none;">
                        <div class="bntm-form-group">
                            <label id="renew-principal-label">Amount to Add/Reduce (₱)</label>
                            <input type="number" name="principal_adjustment" step=".01" placeholder="0.00" id="renew-principal-adj">
                        </div>
                        <div id="renew-new-principal-preview" style="font-size:13px;color:#6b7280;margin-top:-8px;margin-bottom:10px;"></div>
                    </div>

                    <!-- Extra Fees -->
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <label style="font-size:12px;font-weight:700;color:#374151;">Additional Fees</label>
                            <button type="button" onclick="psAddExtraFee('renew')" style="background:#f0f9ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:5px;padding:3px 10px;font-size:11px;font-weight:600;cursor:pointer;">+ Add Fee</button>
                        </div>
                        <div id="renew-extra-fees"></div>
                    </div>

                    <!-- Lost Ticket -->
                    <div style="margin-bottom:14px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" id="renew-lost-ticket" name="is_lost_ticket" value="1" onchange="psToggleLostTicket('renew')">
                            <span style="font-weight:600;">Lost Ticket</span>
                            <span id="renew-lost-fee-label" style="font-size:11px;color:#dc2626;display:none;"></span>
                        </label>
                        <div style="font-size:11px;color:#6b7280;margin-top:3px;margin-left:22px;">Adds affidavit of loss fee and marks ticket as lost. A duplicate replacement ticket will be noted on the receipt.</div>
                    </div>

                    <!-- Payment summary -->
                    <div id="renew-payment-summary" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:14px;">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#166534;margin-bottom:8px;">Payment Summary</div>
                        <table style="width:100%;font-size:13px;" id="renew-summary-table">
                            <tr><td style="color:#6b7280;padding:2px 0;">Accrued Interest</td><td style="text-align:right;font-weight:600;" id="rsum-interest">₱0.00</td></tr>
                            <tr><td style="color:#6b7280;padding:2px 0;">Penalty</td><td style="text-align:right;font-weight:600;color:#dc2626;" id="rsum-penalty">₱0.00</td></tr>
                            <tr><td style="color:#6b7280;padding:2px 0;">Renewal Fee</td><td style="text-align:right;font-weight:600;" id="rsum-fee">₱0.00</td></tr>
                            <tr id="rsum-lost-row" style="display:none;"><td style="color:#dc2626;padding:2px 0;">Affidavit of Loss Fee</td><td style="text-align:right;font-weight:600;color:#dc2626;" id="rsum-lost">₱0.00</td></tr>
                            <tbody id="rsum-extra-rows"></tbody>
                            <tr style="border-top:1px solid #bbf7d0;"><td style="font-weight:700;padding-top:6px;">Total Due Today</td><td style="text-align:right;font-weight:800;font-size:15px;padding-top:6px;" id="rsum-total">₱0.00</td></tr>
                        </table>
                    </div>

                    <div class="bntm-form-group">
                        <label>Payment Method</label>
                        <select name="payment_method"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank_transfer">Bank Transfer</option></select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Reference / OR No.</label>
                        <input type="text" name="reference_number" placeholder="Optional reference number">
                    </div>
                    <div class="bntm-form-group">
                        <label>Notes</label>
                        <textarea name="notes" rows="2" placeholder="Internal notes..."></textarea>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" id="renew-submit-btn" style="flex:1;background:#1e40af;color:#fff;border:none;border-radius:8px;padding:10px;font-size:14px;font-weight:700;cursor:pointer;">Confirm &amp; Print</button>
                        <button type="button" onclick="document.getElementById('ps-renew-modal').style.display='none'" style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:10px 18px;font-size:13px;cursor:pointer;">Cancel</button>
                    </div>
                    <div id="renew-message" style="margin-top:8px;"></div>
                </form>
            </div>
        </div>
    </div>

    <!-- ======== REDEEM MODAL ======== -->
    <div id="ps-redeem-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;width:500px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.35);">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #e5e7eb;background:#f0fdf4;">
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#059669;">Redeem Collateral</h3>
                <button onclick="document.getElementById('ps-redeem-modal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:20px;">✕</button>
            </div>
            <div style="padding:24px;">
                <div id="redeem-loan-info" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:14px;font-size:13px;"></div>
                <form id="ps-redeem-form">
                    <input type="hidden" name="loan_id" id="redeem-loan-id">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ps_redeem_nonce'); ?>">

                    <!-- Extra Fees -->
                    <div style="margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <label style="font-size:12px;font-weight:700;color:#374151;">Additional Fees</label>
                            <button type="button" onclick="psAddExtraFee('redeem')" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#059669;border-radius:5px;padding:3px 10px;font-size:11px;font-weight:600;cursor:pointer;">+ Add Fee</button>
                        </div>
                        <div id="redeem-extra-fees"></div>
                    </div>

                    <!-- Lost Ticket -->
                    <div style="margin-bottom:14px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                            <input type="checkbox" id="redeem-lost-ticket" name="is_lost_ticket" value="1" onchange="psToggleLostTicket('redeem')">
                            <span style="font-weight:600;">Lost Ticket</span>
                            <span id="redeem-lost-fee-label" style="font-size:11px;color:#dc2626;display:none;"></span>
                        </label>
                        <div style="font-size:11px;color:#6b7280;margin-top:3px;margin-left:22px;">Adds affidavit of loss fee and marks ticket as lost.</div>
                    </div>

                    <!-- Summary box for redeem -->
                    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:14px;">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#166534;margin-bottom:8px;">Redemption Summary</div>
                        <table style="width:100%;font-size:13px;">
                            <tr><td style="color:#6b7280;padding:2px 0;">Principal</td><td style="text-align:right;font-weight:600;" id="rdsum-principal">₱0.00</td></tr>
                            <tr><td style="color:#6b7280;padding:2px 0;">Accrued Interest</td><td style="text-align:right;font-weight:600;" id="rdsum-interest">₱0.00</td></tr>
                            <tr><td style="color:#6b7280;padding:2px 0;">Penalty</td><td style="text-align:right;font-weight:600;color:#dc2626;" id="rdsum-penalty">₱0.00</td></tr>
                            <tr id="rdsum-lost-row" style="display:none;"><td style="color:#dc2626;padding:2px 0;">Affidavit of Loss Fee</td><td style="text-align:right;font-weight:600;color:#dc2626;" id="rdsum-lost">₱0.00</td></tr>
                            <tbody id="rdsum-extra-rows"></tbody>
                            <tr style="border-top:1px solid #bbf7d0;"><td style="font-weight:700;padding-top:6px;">Total Due</td><td style="text-align:right;font-weight:800;font-size:15px;padding-top:6px;" id="rdsum-total">₱0.00</td></tr>
                        </table>
                    </div>

                    <div class="bntm-form-group">
                        <label>Amount Tendered (₱)</label>
                        <input type="number" name="amount_tendered" step=".01" placeholder="0.00" id="redeem-tendered" style="font-size:18px;font-weight:700;" oninput="psUpdateRedeemChange()">
                    </div>
                    <div id="redeem-change" style="font-size:14px;font-weight:700;margin-bottom:12px;min-height:20px;"></div>
                    <div class="bntm-form-group">
                        <label>Payment Method</label>
                        <select name="payment_method"><option value="cash">Cash</option><option value="gcash">GCash</option><option value="bank_transfer">Bank Transfer</option></select>
                    </div>
                    <div class="bntm-form-group"><label>Notes</label><textarea name="notes" rows="2" placeholder="Optional notes..."></textarea></div>
                    <div style="display:flex;gap:10px;">
                        <button type="submit" style="flex:1;background:#059669;color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:700;cursor:pointer;">Confirm Redemption &amp; Print</button>
                        <button type="button" onclick="document.getElementById('ps-redeem-modal').style.display='none'" style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:11px 18px;cursor:pointer;">Cancel</button>
                    </div>
                    <div id="redeem-message" style="margin-top:8px;"></div>
                </form>
            </div>
        </div>
    </div>
    <span id="redeem-modal-due" style="display:none;"></span>

    <!-- ======== ADD/EDIT CUSTOMER MODAL ======== -->
    <div id="ps-customer-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px 0;">
        <div style="background:#fff;border-radius:12px;width:680px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.35);margin:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #e5e7eb;background:#f8fafc;">
                <h3 id="customer-modal-title" style="margin:0;font-size:16px;font-weight:700;">Add Customer</h3>
                <button onclick="document.getElementById('ps-customer-modal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:20px;">✕</button>
            </div>
            <div style="padding:24px;">
                <!-- Photo capture section -->
                <div style="display:grid;grid-template-columns:200px 1fr;gap:20px;margin-bottom:18px;align-items:start;">
                    <div>
                        <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px;">Customer Photo</div>
                        <div style="position:relative;border-radius:10px;overflow:hidden;background:#000;aspect-ratio:4/3;">
                            <video id="ps-camera-preview" autoplay playsinline style="width:100%;display:block;"></video>
                            <img id="ps-captured-preview" style="width:100%;display:none;border-radius:10px;" />
                        </div>
                        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
                            <button type="button" id="ps-camera-start-btn" onclick="psCameraStart()" style="flex:1;background:#1e40af;color:#fff;border:none;border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;">📷 Camera</button>
                            <button type="button" id="ps-camera-capture-btn" onclick="psCameraCapture()" style="display:none;flex:1;background:#059669;color:#fff;border:none;border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;">📸 Capture</button>
                            <button type="button" id="ps-camera-retake-btn" onclick="psCameraRetake()" style="display:none;flex:1;background:#f59e0b;color:#fff;border:none;border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;">🔄 Retake</button>
                            <label style="flex:1;background:#6b7280;color:#fff;border:none;border-radius:6px;padding:6px 8px;font-size:11px;cursor:pointer;text-align:center;display:flex;align-items:center;justify-content:center;gap:4px;">
                                📁 Upload<input type="file" id="ps-photo-file" accept="image/*" style="display:none;" onchange="psLoadPhotoFile(this)">
                            </label>
                        </div>
                        <canvas id="ps-camera-canvas" style="display:none;"></canvas>
                        <input type="hidden" id="ps-photo-data" name="photo_data">
                        <div id="ps-photo-status" style="font-size:11px;color:#6b7280;margin-top:4px;text-align:center;min-height:16px;"></div>
                    </div>
                    <div>
                        <form id="ps-customer-form">
                            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ps_customer_nonce'); ?>">
                            <input type="hidden" name="customer_id" id="edit-customer-id" value="">
                            <input type="hidden" name="callback_context" id="customer-modal-context" value="">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div class="bntm-form-group"><label>First Name <span style="color:#ef4444;">*</span></label><input type="text" name="first_name" required placeholder="First name"></div>
                                <div class="bntm-form-group"><label>Last Name <span style="color:#ef4444;">*</span></label><input type="text" name="last_name" required placeholder="Last name"></div>
                                <div class="bntm-form-group" style="grid-column:1/-1;"><label>Middle Name</label><input type="text" name="middle_name" placeholder="Middle name"></div>
                                <div class="bntm-form-group" style="grid-column:1/-1;"><label>Address <span style="color:#ef4444;">*</span></label><textarea name="address" rows="2" required placeholder="Complete address"></textarea></div>
                                <div class="bntm-form-group"><label>Contact <span style="color:#ef4444;">*</span></label><input type="text" name="contact_number" required placeholder="09XX XXX XXXX"></div>
                                <div class="bntm-form-group"><label>Email</label><input type="email" name="email" placeholder="email@example.com"></div>
                                <div class="bntm-form-group">
                                    <label>ID Type <span style="color:#ef4444;">*</span></label>
                                    <select name="id_type" required>
                                        <option value="">Select ID</option>
                                        <option>National ID</option><option>Driver's License</option><option>Passport</option>
                                        <option>SSS ID</option><option>PhilHealth ID</option><option>UMID</option>
                                        <option>Voter's ID</option><option>Barangay ID</option><option>Other</option>
                                    </select>
                                </div>
                                <div class="bntm-form-group"><label>ID Number <span style="color:#ef4444;">*</span></label><input type="text" name="id_number" required placeholder="ID number"></div>
                                <div class="bntm-form-group">
                                    <label>Customer Flag</label>
                                    <select name="customer_flag">
                                        <option value="normal">Normal</option><option value="vip">VIP ⭐</option>
                                        <option value="delinquent">Delinquent</option><option value="blacklisted">Blacklisted</option>
                                    </select>
                                </div>
                                <div class="bntm-form-group" style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" rows="2" placeholder="Internal notes..."></textarea></div>
                            </div>
                            <div style="display:flex;gap:10px;margin-top:14px;">
                                <button type="submit" id="customer-submit-btn" style="flex:1;background:#1e40af;color:#fff;border:none;border-radius:8px;padding:10px;font-size:14px;font-weight:700;cursor:pointer;">Save Customer</button>
                                <button type="button" onclick="document.getElementById('ps-customer-modal').style.display='none'" style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;padding:10px 18px;cursor:pointer;">Cancel</button>
                            </div>
                            <div id="customer-message" style="margin-top:8px;"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ======== CUSTOMER PROFILE MODAL ======== -->
    <div id="ps-profile-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px 0;">
        <div style="background:#fff;border-radius:12px;width:780px;max-width:95vw;box-shadow:0 25px 60px rgba(0,0,0,.35);margin:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid #e5e7eb;position:sticky;top:0;background:#fff;z-index:1;">
                <h3 style="margin:0;font-size:16px;font-weight:700;">Customer Profile</h3>
                <button onclick="document.getElementById('ps-profile-modal').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:20px;">✕</button>
            </div>
            <div id="ps-profile-body" style="padding:24px;"><div style="text-align:center;padding:40px;color:#9ca3af;">Loading...</div></div>
        </div>
    </div>
    <?php echo ps_modal_focus_js(); ?>
    <?php echo ps_render_js(); ?>
    <?php
    return ob_get_clean();
}


// ============================================================
// JAVASCRIPT ENGINE
// ============================================================
function ps_render_js() {
    ob_start(); ?>
    <script>
    (function(){
    'use strict';

    // ---- Camera ----
    let cameraStream = null;
    window.psCameraStart = function() {
        if (!navigator.mediaDevices) { alert('Camera not supported.'); return; }
        navigator.mediaDevices.getUserMedia({ video: { width:640, height:480, facingMode:'user' } })
            .then(stream => {
                cameraStream = stream;
                const vid = document.getElementById('ps-camera-preview');
                vid.srcObject = stream;
                vid.style.display = 'block';
                document.getElementById('ps-captured-preview').style.display = 'none';
                document.getElementById('ps-camera-start-btn').style.display = 'none';
                document.getElementById('ps-camera-capture-btn').style.display = 'flex';
                document.getElementById('ps-camera-retake-btn').style.display = 'none';
                document.getElementById('ps-photo-status').textContent = 'Camera ready — position face and capture.';
            })
            .catch(() => alert('Cannot access camera. Please allow camera permissions.'));
    };
    window.psCameraCapture = function() {
        const vid = document.getElementById('ps-camera-preview');
        const canvas = document.getElementById('ps-camera-canvas');
        // Resize to max 800px while keeping aspect ratio
        const maxDim = 800;
        let w = vid.videoWidth || 640, h = vid.videoHeight || 480;
        if (w > maxDim || h > maxDim) {
            const ratio = Math.min(maxDim / w, maxDim / h);
            w = Math.round(w * ratio); h = Math.round(h * ratio);
        }
        canvas.width = w; canvas.height = h;
        canvas.getContext('2d').drawImage(vid, 0, 0, w, h);
        // Compress: target ~300KB (base64 is ~4/3 of binary, so 300KB binary = ~400KB base64)
        const targetB64 = 400000; // 400KB base64 string ≈ 300KB file
        let quality = 0.82;
        let dataURL = canvas.toDataURL('image/jpeg', quality);
        while (dataURL.length > targetB64 && quality > 0.25) {
            quality = Math.max(0.25, quality - 0.1);
            dataURL = canvas.toDataURL('image/jpeg', quality);
        }
        const kb = Math.round(dataURL.length * 0.75 / 1024); // approx file size
        document.getElementById('ps-photo-data').value = dataURL;
        document.getElementById('ps-captured-preview').src = dataURL;
        document.getElementById('ps-captured-preview').style.display = 'block';
        vid.style.display = 'none';
        document.getElementById('ps-camera-capture-btn').style.display = 'none';
        document.getElementById('ps-camera-retake-btn').style.display = 'flex';
        document.getElementById('ps-photo-status').textContent = 'Photo captured (~' + kb + ' KB) — ready to save';
        if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    };
    window.psCameraRetake = function() {
        document.getElementById('ps-photo-data').value = '';
        document.getElementById('ps-captured-preview').style.display = 'none';
        document.getElementById('ps-captured-preview').src = '';
        document.getElementById('ps-camera-preview').style.display = 'block';
        document.getElementById('ps-camera-start-btn').style.display = 'flex';
        document.getElementById('ps-camera-capture-btn').style.display = 'none';
        document.getElementById('ps-camera-retake-btn').style.display = 'none';
        document.getElementById('ps-photo-status').textContent = 'Take a new photo';
    };
    window.psLoadPhotoFile = function(input) {
        const file = input.files[0];
        if (!file) return;
        if (!file.type.startsWith('image/')) { alert('Please select an image file.'); return; }
        const maxBytes = 10 * 1024 * 1024; // 10MB raw limit before resize
        if (file.size > maxBytes) { alert('Image file too large (max 10MB before resize).'); return; }
        const reader = new FileReader();
        reader.onload = function(ev) {
            const img = new Image();
            img.onload = function() {
                // Resize to max 800px
                const canvas = document.getElementById('ps-camera-canvas');
                const maxDim = 800;
                let w = img.width, h = img.height;
                if (w > maxDim || h > maxDim) {
                    const ratio = Math.min(maxDim / w, maxDim / h);
                    w = Math.round(w * ratio); h = Math.round(h * ratio);
                }
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                // Compress to ~300KB file
                const targetB64 = 400000;
                let quality = 0.82;
                let dataURL = canvas.toDataURL('image/jpeg', quality);
                while (dataURL.length > targetB64 && quality > 0.25) {
                    quality = Math.max(0.25, quality - 0.1);
                    dataURL = canvas.toDataURL('image/jpeg', quality);
                }
                const kb = Math.round(dataURL.length * 0.75 / 1024);
                document.getElementById('ps-photo-data').value = dataURL;
                document.getElementById('ps-captured-preview').src = dataURL;
                document.getElementById('ps-captured-preview').style.display = 'block';
                document.getElementById('ps-camera-preview').style.display = 'none';
                document.getElementById('ps-camera-start-btn').style.display = 'none';
                document.getElementById('ps-camera-capture-btn').style.display = 'none';
                document.getElementById('ps-camera-retake-btn').style.display = 'flex';
                document.getElementById('ps-photo-status').textContent = 'Photo loaded (~' + kb + ' KB) — ready to save';
                stopCamera();
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
        // Reset file input so same file can be re-selected
        input.value = '';
    };
    function stopCamera() {
        if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null; }
    }

    // ---- Customer Modal ----
    window.psOpenCustomerModal = function(customerId, context, existingPhoto) {
        stopCamera();
        const modal = document.getElementById('ps-customer-modal');
        const form = document.getElementById('ps-customer-form');
        document.getElementById('customer-modal-title').textContent = customerId ? 'Edit Customer' : 'Add Customer';
        document.getElementById('edit-customer-id').value = customerId || '';
        document.getElementById('customer-modal-context').value = context || '';
        document.getElementById('ps-photo-data').value = '';
        if (!customerId) {
            // New customer: blank camera view
            form.reset();
            document.getElementById('ps-captured-preview').style.display = 'none';
            document.getElementById('ps-captured-preview').src = '';
            document.getElementById('ps-camera-preview').style.display = 'block';
            document.getElementById('ps-camera-start-btn').style.display = 'flex';
            document.getElementById('ps-camera-capture-btn').style.display = 'none';
            document.getElementById('ps-camera-retake-btn').style.display = 'none';
            document.getElementById('ps-photo-status').textContent = '';
        } else {
            // Editing: show existing photo if provided, else blank camera
            if (existingPhoto) {
                const prev = document.getElementById('ps-captured-preview');
                prev.src = PS_PHOTO_URL + existingPhoto;
                prev.style.display = 'block';
                document.getElementById('ps-camera-preview').style.display = 'none';
                document.getElementById('ps-camera-start-btn').style.display = 'none';
                document.getElementById('ps-camera-capture-btn').style.display = 'none';
                document.getElementById('ps-camera-retake-btn').style.display = 'flex';
                document.getElementById('ps-photo-status').textContent = 'Current photo — retake to update';
            } else {
                document.getElementById('ps-captured-preview').style.display = 'none';
                document.getElementById('ps-captured-preview').src = '';
                document.getElementById('ps-camera-preview').style.display = 'block';
                document.getElementById('ps-camera-start-btn').style.display = 'flex';
                document.getElementById('ps-camera-capture-btn').style.display = 'none';
                document.getElementById('ps-camera-retake-btn').style.display = 'none';
                document.getElementById('ps-photo-status').textContent = 'No photo on file — capture new';
            }
        }
        modal.style.display = 'flex';
    };

    // ---- Create Loan Steps ----
    let clSelectedCustomer = null;
    let clSearchTimer = null;

    window.psOpenCreateLoan = function() {
        clSelectedCustomer = null;
        document.getElementById('cl-customer-id-val').value = '';
        document.getElementById('cl-next-btn').disabled = true;
        document.getElementById('cl-next-btn').style.opacity = '.4';
        document.getElementById('cl-selected-customer').style.display = 'none';
        document.getElementById('cl-customer-results').style.display = 'none';
        document.getElementById('cl-customer-search').value = '';
        document.getElementById('cl-step-1').style.display = 'block';
        document.getElementById('cl-step-2').style.display = 'none';
        document.getElementById('ps-create-loan-modal').style.display = 'flex';
        psUpdateLoanSummary();
    };

    document.getElementById('cl-customer-search')?.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(clSearchTimer);
        if (q.length < 2) { document.getElementById('cl-customer-results').style.display = 'none'; return; }
        clSearchTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('action', 'ps_search_customers');
            fd.append('q', q);
            fd.append('nonce', PS_NONCES.search);
            fetch(ajaxurl, { method:'POST', body:fd }).then(r=>r.json()).then(json => {
                const res = document.getElementById('cl-customer-results');
                if (json.success && json.data.customers.length > 0) {
                    res.innerHTML = json.data.customers.map(c => {
                        const flag = c.customer_flag;
                        const flagColors = { normal:'#374151', vip:'#854d0e', delinquent:'#92400e', blacklisted:'#991b1b' };
                        const photoHtml = c.photo_path
                            ? `<img src="${PS_PHOTO_URL}${c.photo_path}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex-shrink:0;" />`
                            : `<div style="width:40px;height:40px;border-radius:50%;background:#1e40af;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:800;flex-shrink:0;">${(c.last_name||'?').charAt(0)}</div>`;
                        return `<div style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px;" onmousedown="psSelectCustomerForLoan(${JSON.stringify(c).replace(/"/g,'&quot;')})">
                            ${photoHtml}
                            <div>
                                <div style="font-weight:700;font-size:13px;">${c.last_name}, ${c.first_name} ${c.middle_name||''}</div>
                                <div style="font-size:11px;color:#6b7280;">${c.contact_number} &bull; ${c.id_type}: ${c.id_number}</div>
                                <span style="font-size:10px;font-weight:700;color:${flagColors[flag]||'#374151'};text-transform:uppercase;">${flag}</span>
                            </div>
                        </div>`;
                    }).join('');
                    res.style.display = 'block';
                } else {
                    res.innerHTML = '<div style="padding:12px 14px;color:#9ca3af;font-size:13px;">No customers found</div>';
                    res.style.display = 'block';
                }
            });
        }, 280);
    });

    window.psSelectCustomerForLoan = function(c) {
        if (typeof c === 'string') c = JSON.parse(c);
        clSelectedCustomer = c;
        document.getElementById('cl-customer-id-val').value = c.id;
        document.getElementById('cl-customer-results').style.display = 'none';
        document.getElementById('cl-customer-search').value = '';
        // Show card
        const card = document.getElementById('cl-selected-customer');
        card.style.display = 'block';
        document.getElementById('cl-cust-name').textContent = `${c.last_name}, ${c.first_name} ${c.middle_name||''}`;
        document.getElementById('cl-cust-contact').textContent = c.contact_number;
        document.getElementById('cl-cust-id').textContent = `${c.id_type}: ${c.id_number}`;
        const flagMap = {normal:'ps-flag-normal',vip:'ps-flag-vip',delinquent:'ps-flag-delinquent',blacklisted:'ps-flag-blacklisted'};
        document.getElementById('cl-cust-flag-badge').innerHTML = `<span class="ps-status-badge ${flagMap[c.customer_flag]||''}" style="margin-top:4px;">${c.customer_flag}</span>`;
        document.getElementById('cl-cust-avatar').textContent = (c.last_name||'?').charAt(0);
        const photo = document.getElementById('cl-cust-photo');
        const avatar = document.getElementById('cl-cust-avatar');
        if (c.photo_path) {
            photo.src = PS_PHOTO_URL + c.photo_path;
            photo.style.display = 'block';
            avatar.style.display = 'none';
        } else {
            photo.style.display = 'none';
            avatar.style.display = 'flex';
        }
        document.getElementById('cl-cust-active-loans').textContent = c.active_loans || 0;
        // Check blacklist
        const warn = document.getElementById('cl-blacklist-warning');
        const nextBtn = document.getElementById('cl-next-btn');
        if (c.customer_flag === 'blacklisted') {
            warn.style.display = 'block';
            warn.textContent = 'BLACKLISTED customer — new loans cannot be processed.';
            nextBtn.disabled = true;
            nextBtn.style.opacity = '.4';
        } else {
            warn.style.display = 'none';
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
        }
    };

    window.psClearCustomerSelection = function() {
        clSelectedCustomer = null;
        document.getElementById('cl-customer-id-val').value = '';
        document.getElementById('cl-selected-customer').style.display = 'none';
        document.getElementById('cl-next-btn').disabled = true;
        document.getElementById('cl-next-btn').style.opacity = '.4';
    };

    window.psCreateLoanStep2 = function() {
        if (!document.getElementById('cl-customer-id-val').value) return;
        document.getElementById('cl-form-customer-id').value = document.getElementById('cl-customer-id-val').value;
        document.getElementById('cl-step-1').style.display = 'none';
        document.getElementById('cl-step-2').style.display = 'block';
        psCalculatePrincipalFromLtv();
        psUpdateLoanSummary();
    };

    window.psCreateLoanBack = function() {
        document.getElementById('cl-step-2').style.display = 'none';
        document.getElementById('cl-step-1').style.display = 'block';
    };

    function psCalculatePrincipalFromLtv() {
        const appraisal = parseFloat(document.getElementById('appraised-val-input')?.value) || 0;
        const ltv = parseFloat(document.getElementById('loan-ltv-input')?.value) || 0;
        const principalInput = document.getElementById('loan-principal-input');
        if (!principalInput) return 0;
        const principal = appraisal > 0 && ltv > 0 ? (appraisal * ltv) / 100 : 0;
        principalInput.value = principal ? principal.toFixed(2) : '';
        return principal;
    }

    // ---- Loan Summary ----
    function psUpdateLoanSummary() {
        const p = parseFloat(document.getElementById('loan-principal-input')?.value) || 0;
        const r = parseFloat(document.getElementById('loan-interest-input')?.value) || 0;
        const t = parseInt(document.getElementById('loan-term-select')?.value) || 1;
        const f = parseFloat(document.getElementById('loan-fee-input')?.value) || 0;
        const ld = document.getElementById('loan-date-input')?.value;
        const daily = p * (r / 100 / 30);
        const int = daily * t * 30;
        if (document.getElementById('sum-principal')) document.getElementById('sum-principal').textContent = '₱' + p.toLocaleString('en-PH', {minimumFractionDigits:2});
        if (document.getElementById('sum-interest')) document.getElementById('sum-interest').textContent = '₱' + int.toLocaleString('en-PH', {minimumFractionDigits:2});
        if (document.getElementById('sum-fee')) document.getElementById('sum-fee').textContent = '₱' + f.toLocaleString('en-PH', {minimumFractionDigits:2});
        if (document.getElementById('sum-total')) document.getElementById('sum-total').textContent = '₱' + (p + int + f).toLocaleString('en-PH', {minimumFractionDigits:2});
        if (ld && t) {
            const d = new Date(ld); d.setMonth(d.getMonth() + t);
            const dd = d.toISOString().split('T')[0];
            const ddi = document.getElementById('loan-due-date-input');
            if (ddi) ddi.value = dd;
        }
    }
    document.addEventListener('input', e => {
        if (['appraised-val-input','loan-ltv-input'].includes(e.target.id)) {
            psCalculatePrincipalFromLtv();
            psUpdateLoanSummary();
        }
        if (['loan-principal-input','loan-interest-input','loan-fee-input'].includes(e.target.id)) psUpdateLoanSummary();
    });
    document.addEventListener('change', e => { if (['loan-term-select','loan-date-input'].includes(e.target.id)) psUpdateLoanSummary(); });

    // ---- Redeem change display ----
    document.addEventListener('input', e => {
        if (e.target.id === 'redeem-tendered') {
            const tend = parseFloat(e.target.value) || 0;
            const due = parseFloat(document.getElementById('redeem-modal-due').dataset.due) || 0;
            const chg = tend - due;
            const el = document.getElementById('redeem-change');
            if (el && tend > 0) {
                el.textContent = chg >= 0 ? 'Change: ₱' + Math.abs(chg).toLocaleString('en-PH',{minimumFractionDigits:2}) : 'Short by: ₱' + Math.abs(chg).toLocaleString('en-PH',{minimumFractionDigits:2});
                el.style.color = chg >= 0 ? '#059669' : '#dc2626';
            } else if (el) { el.textContent = ''; }
        }
    });

    // ---- Renew Modal Type ----
    let renewCurrentData = {};
    window.psSetRenewType = function(type) {
        document.querySelectorAll('.ps-type-btn').forEach(b => {
            const active = b.dataset.type === type;
            b.style.borderColor = active ? '#1e40af' : '#e5e7eb';
            b.style.background = active ? '#eff6ff' : '#fff';
            b.style.color = active ? '#1e40af' : '#374151';
        });
        document.getElementById('renew-transaction-type').value = type;
        document.getElementById('renew-extend-section').style.display = type === 'renewal' ? 'block' : 'none';
        document.getElementById('renew-principal-section').style.display = ['add_principal','reduce_principal'].includes(type) ? 'block' : 'none';
        if (type === 'add_principal') document.getElementById('renew-principal-label').textContent = 'Amount to Add to Principal (₱)';
        if (type === 'reduce_principal') document.getElementById('renew-principal-label').textContent = 'Amount to Reduce from Principal (₱)';
        psUpdateRenewSummary();
    };

    window.psUpdateRenewSummary = function() {
        const d = renewCurrentData;
        if (!d) return;
        const type = document.getElementById('renew-transaction-type').value;
        const fee = parseFloat(document.querySelector('[name="renewal_fee"]')?.value) || 0;
        const adj = parseFloat(document.getElementById('renew-principal-adj')?.value) || 0;
        const lostFee = document.getElementById('renew-lost-ticket')?.checked ? (psLostTicketFee||0) : 0;
        document.getElementById('rsum-interest').textContent = '₱' + (d.interest || 0).toLocaleString('en-PH', {minimumFractionDigits:2});
        document.getElementById('rsum-penalty').textContent = '₱' + (d.penalty || 0).toLocaleString('en-PH', {minimumFractionDigits:2});
        document.getElementById('rsum-fee').textContent = '₱' + fee.toLocaleString('en-PH', {minimumFractionDigits:2});
        // Lost ticket row
        const lostRow = document.getElementById('rsum-lost-row');
        if (lostFee > 0) { lostRow.style.display=''; document.getElementById('rsum-lost').textContent='₱'+lostFee.toLocaleString('en-PH',{minimumFractionDigits:2}); }
        else { lostRow.style.display='none'; }
        // Extra fees rows
        const extraEl = document.getElementById('rsum-extra-rows');
        let extraTotal = 0;
        extraEl.innerHTML = '';
        document.querySelectorAll('.ps-extra-fee-row[data-modal="renew"]').forEach(row => {
            const desc = row.querySelector('.ef-desc')?.value || 'Extra Fee';
            const amt = parseFloat(row.querySelector('.ef-amt')?.value) || 0;
            extraTotal += amt;
            if (amt > 0) extraEl.innerHTML += `<tr><td style="color:#6b7280;padding:2px 0;">${desc}</td><td style="text-align:right;font-weight:600;">₱${amt.toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>`;
        });
        const total = (d.interest || 0) + (d.penalty || 0) + fee + lostFee + extraTotal;
        document.getElementById('rsum-total').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits:2});
        if (type === 'add_principal' && adj > 0) {
            const np = d.principal + adj;
            document.getElementById('renew-new-principal-preview').textContent = `New principal will be: ₱${np.toLocaleString('en-PH',{minimumFractionDigits:2})}`;
        } else if (type === 'reduce_principal' && adj > 0) {
            const np = Math.max(0, d.principal - adj);
            document.getElementById('renew-new-principal-preview').textContent = `New principal will be: ₱${np.toLocaleString('en-PH',{minimumFractionDigits:2})}`;
        }
    };

    window.psUpdateRedeemChange = function() {
        const tendered = parseFloat(document.getElementById('redeem-tendered')?.value) || 0;
        const totalEl = document.getElementById('rdsum-total');
        const total = parseFloat(totalEl?.textContent?.replace(/[^0-9.]/g,'')) || 0;
        const changeEl = document.getElementById('redeem-change');
        if (tendered > 0 && total > 0) {
            const chg = tendered - total;
            changeEl.style.color = chg >= 0 ? '#059669' : '#dc2626';
            changeEl.textContent = chg >= 0 ? `Change: ₱${chg.toLocaleString('en-PH',{minimumFractionDigits:2})}` : `Short by: ₱${Math.abs(chg).toLocaleString('en-PH',{minimumFractionDigits:2})}`;
        } else { changeEl.textContent = ''; }
    };

    window.psUpdateRedeemSummary = function() {
        const d = redeemCurrentData;
        if (!d) return;
        const lostFee = document.getElementById('redeem-lost-ticket')?.checked ? (psLostTicketFee||0) : 0;
        document.getElementById('rdsum-principal').textContent = '₱' + (d.principal||0).toLocaleString('en-PH',{minimumFractionDigits:2});
        document.getElementById('rdsum-interest').textContent = '₱' + (d.interest||0).toLocaleString('en-PH',{minimumFractionDigits:2});
        document.getElementById('rdsum-penalty').textContent = '₱' + (d.penalty||0).toLocaleString('en-PH',{minimumFractionDigits:2});
        const lostRow = document.getElementById('rdsum-lost-row');
        if (lostFee > 0) { lostRow.style.display=''; document.getElementById('rdsum-lost').textContent='₱'+lostFee.toLocaleString('en-PH',{minimumFractionDigits:2}); }
        else { lostRow.style.display='none'; }
        const extraEl = document.getElementById('rdsum-extra-rows');
        let extraTotal = 0;
        extraEl.innerHTML = '';
        document.querySelectorAll('.ps-extra-fee-row[data-modal="redeem"]').forEach(row => {
            const desc = row.querySelector('.ef-desc')?.value || 'Extra Fee';
            const amt = parseFloat(row.querySelector('.ef-amt')?.value) || 0;
            extraTotal += amt;
            if (amt > 0) extraEl.innerHTML += `<tr><td style="color:#6b7280;padding:2px 0;">${desc}</td><td style="text-align:right;font-weight:600;">₱${amt.toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>`;
        });
        const total = (d.principal||0) + (d.interest||0) + (d.penalty||0) + lostFee + extraTotal;
        document.getElementById('rdsum-total').textContent = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
        psUpdateRedeemChange();
    };

    window.psAddExtraFee = function(modal) {
        const container = document.getElementById(modal+'-extra-fees');
        const idx = container.children.length;
        const div = document.createElement('div');
        div.className = 'ps-extra-fee-row';
        div.setAttribute('data-modal', modal);
        div.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;';
        div.innerHTML = `<input type="text" class="ef-desc" placeholder="Fee description" style="flex:2;padding:6px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;" oninput="${modal==='renew'?'psUpdateRenewSummary':'psUpdateRedeemSummary'}()">
            <input type="number" class="ef-amt" placeholder="0.00" step="0.01" min="0" style="flex:1;padding:6px 8px;border:1px solid #d1d5db;border-radius:5px;font-size:12px;" oninput="${modal==='renew'?'psUpdateRenewSummary':'psUpdateRedeemSummary'}()">
            <button type="button" onclick="this.parentElement.remove();${modal==='renew'?'psUpdateRenewSummary':'psUpdateRedeemSummary'}()" style="background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:12px;font-weight:700;">✕</button>`;
        container.appendChild(div);
    };

    window.psToggleLostTicket = function(modal) {
        const checked = document.getElementById(modal+'-lost-ticket').checked;
        const label = document.getElementById(modal+'-lost-fee-label');
        if (checked) { label.style.display='inline'; label.textContent=`+ ₱${(psLostTicketFee||0).toLocaleString('en-PH',{minimumFractionDigits:2})} affidavit fee`; }
        else { label.style.display='none'; }
        if (modal==='renew') psUpdateRenewSummary(); else psUpdateRedeemSummary();
    };

    // Shared extra-fee input listener
    document.addEventListener('input', e => {
        if (['renew-principal-adj'].includes(e.target.id) || e.target.name === 'renewal_fee') psUpdateRenewSummary();
        if (e.target.id === 'redeem-tendered') psUpdateRedeemChange();
    });

    window.psOpenRenewModal = function(loanId) {
        document.getElementById('renew-loan-id').value = loanId;
        document.getElementById('renew-interest-detail').textContent = 'Computing...';
        document.getElementById('renew-message').innerHTML = '';
        document.getElementById('renew-lost-ticket').checked = false;
        document.getElementById('renew-lost-fee-label').style.display = 'none';
        document.getElementById('renew-extra-fees').innerHTML = '';
        document.getElementById('rsum-extra-rows').innerHTML = '';
        document.getElementById('rsum-lost-row').style.display = 'none';
        psSetRenewType('interest_payment');
        document.getElementById('ps-renew-modal').style.display = 'flex';
        // Fetch interest computation
        const fd = new FormData();
        fd.append('action', 'ps_compute_interest');
        fd.append('loan_id', loanId);
        fd.append('nonce', PS_NONCES.payment);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                const d = json.data;
                renewCurrentData = d;
                document.getElementById('renew-loan-info').innerHTML = `
                    <strong>Ticket #${d.ticket_number}</strong> — ${d.customer_name}<br>
                    Principal: <strong>₱${d.principal.toLocaleString('en-PH',{minimumFractionDigits:2})}</strong> |
                    Rate: ${d.interest_rate}%/mo |
                    Due: <strong style="color:${d.is_overdue?'#dc2626':'#059669'}">${d.due_date}</strong>
                    ${d.is_overdue ? `<span style="color:#dc2626;font-weight:700;margin-left:8px;">[!] ${d.days_past_due}d overdue (${d.effective_overdue}d after grace)</span>` : ''}
                `;
                document.getElementById('renew-interest-detail').innerHTML = `
                    <table style="width:100%;font-size:12px;">
                        <tr><td style="color:#6b7280;">Days Elapsed</td><td style="text-align:right;">${d.days_elapsed} days</td></tr>
                        <tr><td style="color:#6b7280;">Daily Interest Rate</td><td style="text-align:right;">${(d.interest_rate/30).toFixed(4)}%/day</td></tr>
                        <tr><td style="color:#6b7280;">Accrued Regular Interest</td><td style="text-align:right;font-weight:600;">₱${d.regular_interest.toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>
                        ${d.carried_interest > 0 ? `<tr><td style="color:#6b7280;">Carried Interest</td><td style="text-align:right;color:#7c3aed;">₱${d.carried_interest.toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>` : ''}
                        ${d.penalty_interest > 0 ? `<tr><td style="color:#dc2626;">Penalty Interest (${d.effective_overdue}d × ${d.penalty_rate}%/mo)</td><td style="text-align:right;font-weight:600;color:#dc2626;">₱${d.penalty_interest.toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>` : ''}
                        ${d.grace_days > 0 ? `<tr><td style="color:#6b7280;font-size:11px;">Grace period: ${d.grace_days} days (no penalty)</td><td></td></tr>` : ''}
                        <tr style="border-top:1px solid #bfdbfe;"><td style="font-weight:700;padding-top:5px;">Total Interest Due</td><td style="text-align:right;font-weight:800;font-size:14px;padding-top:5px;">₱${d.total_interest.toLocaleString('en-PH',{minimumFractionDigits:2})}</td></tr>
                    </table>
                `;
                psUpdateRenewSummary();
            }
        });
    };

    let redeemCurrentData = {};
    window.psOpenRedeemModal = function(loanId) {
        document.getElementById('redeem-loan-id').value = loanId;
        document.getElementById('redeem-tendered').value = '';
        document.getElementById('redeem-change').textContent = '';
        document.getElementById('redeem-message').innerHTML = '';
        document.getElementById('redeem-lost-ticket').checked = false;
        document.getElementById('redeem-lost-fee-label').style.display = 'none';
        document.getElementById('redeem-extra-fees').innerHTML = '';
        document.getElementById('rdsum-extra-rows').innerHTML = '';
        redeemCurrentData = {};
        // Fetch computation
        const fd = new FormData();
        fd.append('action', 'ps_compute_interest');
        fd.append('loan_id', loanId);
        fd.append('nonce', PS_NONCES.payment);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                const d = json.data;
                redeemCurrentData = { principal: d.principal, interest: d.total_interest, penalty: d.penalty_interest };
                document.getElementById('redeem-loan-info').innerHTML = `
                    <strong>Ticket #${d.ticket_number}</strong> — ${d.customer_name}<br>
                    <span style="font-size:12px;color:#6b7280;">${d.days_elapsed} days accrued · Due: ${d.due_date}</span>
                `;
                document.getElementById('redeem-modal-due').dataset.due = d.total_due;
                psUpdateRedeemSummary();
                document.getElementById('redeem-tendered').value = '';
            }
        });
        document.getElementById('ps-redeem-modal').style.display = 'flex';
    };

    // ---- Form Submissions ----
    document.getElementById('ps-create-loan-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('create-loan-submit-btn');
        btn.disabled = true; btn.textContent = 'Processing...';
        const fd = new FormData(this);
        fd.append('action', 'ps_create_loan');
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            const msg = document.getElementById('create-loan-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                document.getElementById('ps-create-loan-modal').style.display = 'none';
                psShowPrintModal(json.data.loan_id, 'pawn_ticket');
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false; btn.textContent = 'Create Pawn Ticket & Print';
            }
        });
    });

    document.getElementById('ps-renew-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('renew-submit-btn');
        btn.disabled = true; btn.textContent = 'Processing...';
        const fd = new FormData(this);
        fd.append('action', 'ps_renew_loan');
        const extraFees = [];
        document.querySelectorAll('.ps-extra-fee-row[data-modal="renew"]').forEach(row => {
            const desc = row.querySelector('.ef-desc')?.value || '';
            const amt  = parseFloat(row.querySelector('.ef-amt')?.value) || 0;
            if (desc && amt > 0) extraFees.push({desc, amt});
        });
        fd.append('extra_fees', JSON.stringify(extraFees));
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                document.getElementById('ps-renew-modal').style.display = 'none';
                psShowPrintModal(json.data.loan_id, 'renewal_notice');
                // Removed: setTimeout(() => location.reload(), 2500);
            } else {
                document.getElementById('renew-message').innerHTML =
                    '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false; btn.textContent = 'Confirm & Print';
            }
        });
    });

    document.getElementById('ps-redeem-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true; btn.textContent = 'Processing...';
        const fd = new FormData(this);
        fd.append('action', 'ps_redeem_loan');
        // Collect extra fees
        const extraFees = [];
        document.querySelectorAll('.ps-extra-fee-row[data-modal="redeem"]').forEach(row => {
            const desc = row.querySelector('.ef-desc')?.value || '';
            const amt = parseFloat(row.querySelector('.ef-amt')?.value) || 0;
            if (desc && amt > 0) extraFees.push({desc, amt});
        });
        fd.append('extra_fees', JSON.stringify(extraFees));
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                document.getElementById('ps-redeem-modal').style.display = 'none';
                psShowPrintModal(json.data.loan_id, 'redemption_receipt');
            } else {
                document.getElementById('redeem-message').innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false; btn.textContent = 'Confirm Redemption & Print';
            }
        });
    });

    document.getElementById('ps-customer-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('customer-submit-btn');
        btn.disabled = true; btn.textContent = 'Saving...';
        const cid = document.getElementById('edit-customer-id').value;
        const context = document.getElementById('customer-modal-context').value;
        const fd = new FormData(this);
        fd.append('action', cid ? 'ps_edit_customer' : 'ps_add_customer');
        // Ensure photo_data is explicitly set (FormData may pick up stale/empty value)
        const photoVal = document.getElementById('ps-photo-data').value;
        if (photoVal && photoVal.startsWith('data:image')) {
            fd.set('photo_data', photoVal);
        } else {
            fd.delete('photo_data'); // no new photo — don't overwrite existing
        }
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            const msg = document.getElementById('customer-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                stopCamera();
                setTimeout(() => {
                    document.getElementById('ps-customer-modal').style.display = 'none';
                    if (context === 'create-loan' && json.data.customer) {
                        psSelectCustomerForLoan(json.data.customer);
                    } else {
                        location.reload();
                    }
                }, 1000);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false; btn.textContent = 'Save Customer';
            }
        });
    });

    // ---- Print Modal ----
    window.psShowPrintModal = function(loanId, docType, options) {
        options = options || {};
        const autoPrint = options.autoPrint !== false;
        const modal = document.getElementById('ps-print-modal');
        const preview = document.getElementById('ps-print-preview');
        const titles = { pawn_ticket:'Pawn Ticket', renewal_notice:'Renewal Notice', redemption_receipt:'Redemption Receipt', forfeiture_notice:'Forfeiture Notice', customer_statement:'Customer Statement', payment_receipt:'Payment Receipt', daily_summary:'Daily Summary' };
        document.getElementById('print-modal-title').textContent = titles[docType] || 'Document';
        document.getElementById('print-modal-subtitle').textContent = 'Loan ID: ' + loanId + ' — Review before printing';
        document.getElementById('print-modal-subtitle').textContent = autoPrint
            ? 'Loan ID: ' + loanId + ' - Preparing print...'
            : 'Loan ID: ' + loanId + ' - Review before printing';
        preview.innerHTML = '<div style="padding:40px;text-align:center;color:#9ca3af;">Generating document...</div>';
        modal.style.display = 'flex';
        const fd = new FormData();
        fd.append('action', 'ps_generate_document');
        fd.append('loan_id', loanId);
        fd.append('doc_type', docType);
        fd.append('nonce', PS_NONCES.doc);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                preview.innerHTML = json.data.html;
                modal.dataset.loanId = loanId;
                modal.dataset.docType = docType;
                if (autoPrint) {
                    const didOpen = psPrintDocument({ closeModal: true });
                    if (!didOpen) {
                        document.getElementById('print-modal-subtitle').textContent = 'Popup blocked - use Print Document to continue';
                    }
                }
            }
            else { preview.innerHTML = '<div style="padding:20px;color:#dc2626;">Error: ' + json.data.message + '</div>'; }
        });
    };

    window.psPrintDocument = function(options) {
        options = options || {};
        const closeModal = options.closeModal !== false;
        const copies = parseInt(document.getElementById('print-copies').value) || 1;
        const preview = document.getElementById('ps-print-preview').innerHTML;
        const modal = document.getElementById('ps-print-modal');
        if (!preview || !preview.trim()) return false;
        const fd = new FormData();
        fd.append('action','ps_log_print');
        fd.append('loan_id', modal.dataset.loanId || 0);
        fd.append('doc_type', modal.dataset.docType || '');
        fd.append('copies', copies);
        fd.append('nonce', PS_NONCES.doc);
        fetch(ajaxurl, {method:'POST', body:fd});
        const win = window.open('', '_blank', 'width=860,height=1100');
        if (!win) return false;
        let content = '';
        for (let i = 0; i < copies; i++) { content += preview; if (i < copies-1) content += '<div style="page-break-after:always;"></div>'; }
        win.document.write(`<!DOCTYPE html><html><head><title>Pawnshop Document</title><style>
            body{font-family:'Arial',sans-serif;margin:0;padding:0;color:#000;}
            .ps-doc-preview{padding:24px 28px;}
            .ps-doc-header{text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:12px;}
            .ps-doc-title{font-size:18px;font-weight:900;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:2px;}
            .ps-doc-subtitle{font-size:11px;color:#333;}
            .ps-doc-section-title{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid #000;padding-bottom:2px;margin:10px 0 6px;}
            .ps-doc-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px 14px;}
            .ps-doc-field{display:flex;gap:6px;font-size:11px;padding:1px 0;}
            .ps-doc-field-label{font-weight:700;min-width:110px;flex-shrink:0;}
            .ps-doc-amounts{width:100%;border-collapse:collapse;margin:6px 0;font-size:11px;}
            .ps-doc-amounts th{background:#000;color:#fff;padding:4px 7px;text-align:left;font-size:10px;text-transform:uppercase;}
            .ps-doc-amounts td{padding:4px 7px;border-bottom:1px solid #ddd;}
            .ps-doc-amounts tr:last-child td{border-bottom:2px solid #000;font-weight:700;background:#f0f0f0;}
            .ps-doc-signatures{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:28px;}
            .ps-doc-sig-block{text-align:center;}
            .ps-doc-sig-line{border-top:1px solid #000;padding-top:4px;font-size:10px;color:#333;margin-top:24px;}
            @media print{@page{margin:.5in;size:letter;}}
        </style></head><body onload="window.print();setTimeout(()=>window.close(),600);">`);
        win.document.write(content);
        win.document.write('</body></html>');
        win.document.close();
        if (closeModal) psDismissPrint();
        return true;
    };

    window.psDismissPrint = function() {
        document.getElementById('ps-print-modal').style.display = 'none';
    };

    // ---- Loan Detail ----
    window.psViewLoanDetail = function(loanId) {
        const modal = document.getElementById('ps-loan-detail-modal');
        const body = document.getElementById('ps-loan-detail-body');
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#9ca3af;">Loading...</div>';
        modal.style.display = 'flex';
        const fd = new FormData();
        fd.append('action','ps_get_loan_detail');
        fd.append('loan_id', loanId);
        fd.append('nonce', PS_NONCES.loan);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) body.innerHTML = json.data.html;
            else body.innerHTML = '<div style="color:#dc2626;padding:20px;">Error loading loan details.</div>';
        });
    };

    // ---- Quick Ticket Search ----
    let qsearchTimer = null;
    window.psOpenTicketSearch = function() {
        const bar = document.getElementById('ps-ticket-search-bar');
        if (bar.style.display === 'none') {
            bar.style.display = 'block';
            document.getElementById('ps-qsearch-input').focus();
        } else {
            bar.style.display = 'none';
        }
    };
    window.psQuickSearchTicket = function(val) {
        clearTimeout(qsearchTimer);
        const res = document.getElementById('ps-qsearch-results');
        if (val.length < 2) { res.innerHTML = ''; return; }
        res.innerHTML = '<div style="color:#9ca3af;font-size:13px;">Searching...</div>';
        qsearchTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('action','ps_search_loans');
            fd.append('query', val);
            fd.append('nonce', PS_NONCES.search);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json => {
                if (!json.success || !json.data.loans?.length) {
                    res.innerHTML = '<div style="color:#9ca3af;font-size:13px;padding:8px;">No tickets found.</div>'; return;
                }
                res.innerHTML = json.data.loans.map(l => {
                    const statusColors = {active:'#059669',renewed:'#7c3aed',overdue:'#dc2626',redeemed:'#6b7280',forfeited:'#9ca3af'};
                    const col = statusColors[l.status]||'#374151';
                    const canAct = ['active','overdue'].includes(l.status);
                    return `<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:6px;background:#fff;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-family:monospace;font-weight:700;font-size:13px;">${l.ticket_number}</div>
                            <div style="font-size:12px;color:#374151;margin-top:1px;">${l.customer_name} &mdash; ${l.collateral_desc||'—'}</div>
                            <div style="font-size:11px;color:#6b7280;">Principal: ₱${parseFloat(l.principal).toLocaleString('en-PH',{minimumFractionDigits:2})} &nbsp;|&nbsp; Due: ${l.due_date}</div>
                        </div>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;flex-shrink:0;">
                            <span style="font-size:11px;font-weight:700;color:${col};text-transform:uppercase;padding:2px 7px;background:${col}18;border-radius:4px;">${l.status}</span>
                            <div style="display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;">
                                <button class="ps-action-btn ps-btn-view" onclick="psViewLoanDetail(${l.id})">View</button>
                                ${canAct ? `<button class="ps-action-btn ps-btn-renew" onclick="psOpenRenewModal(${l.id})">Renew</button>` : ''}
                                ${canAct ? `<button class="ps-action-btn ps-btn-redeem" onclick="psOpenRedeemModal(${l.id})">Redeem</button>` : ''}
                                <button class="ps-action-btn ps-btn-print" onclick="psShowPrintModal(${l.id},'pawn_ticket')">Print</button>
                            </div>
                        </div>
                    </div>`;
                }).join('');
            });
        }, 350);
    };

    window.psConfirmForfeit = function(loanId, ticketNo) {
        if (!confirm('Mark Ticket #' + ticketNo + ' as FORFEITED?\n\nThis cannot be undone.')) return;
        const fd = new FormData();
        fd.append('action', 'ps_forfeit_loan');
        fd.append('loan_id', loanId);
        fd.append('nonce', PS_NONCES.forfeit);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) { psShowPrintModal(loanId, 'forfeiture_notice'); setTimeout(() => location.reload(), 2500); }
            else alert('Error: ' + json.data.message);
        });
    };

    // ---- Customer Profile ----
    window.psViewCustomerProfile = function(id) {
        const modal = document.getElementById('ps-profile-modal');
        const body = document.getElementById('ps-profile-body');
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#9ca3af;">Loading...</div>';
        modal.style.display = 'flex';
        const fd = new FormData();
        fd.append('action', 'ps_get_customer_profile');
        fd.append('customer_id', id);
        fd.append('nonce', PS_NONCES.customer);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) body.innerHTML = json.data.html;
            else body.innerHTML = '<div style="color:#dc2626;padding:20px;">Error.</div>';
        });
    };

    window.psEditCustomer = function(id, fn, ln, mn, addr, con, email, idt, idn, flag, notes, photo) {
        psOpenCustomerModal(id, '', photo || '');
        // Populate form fields immediately
        const f = document.getElementById('ps-customer-form');
        f.first_name.value = fn; f.last_name.value = ln; f.middle_name.value = mn;
        f.address.value = addr; f.contact_number.value = con; f.email.value = email;
        f.id_type.value = idt; f.id_number.value = idn; f.customer_flag.value = flag; f.notes.value = notes;
    };

    // ---- Ticket History ----
    window.psViewTicketHistory = function(rootTicket) {
        const fd = new FormData();
        fd.append('action', 'ps_get_ticket_history');
        fd.append('root_ticket', rootTicket);
        fd.append('nonce', PS_NONCES.loan);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                const body = document.getElementById('ps-loan-detail-body');
                body.insertAdjacentHTML('beforeend', json.data.html);
            }
        });
    };

    // ---- Finance Export ----
    window.psFnExport = function(pid, amount) {
        if (!confirm('Export ₱' + parseFloat(amount).toLocaleString('en-PH',{minimumFractionDigits:2}) + ' to Finance module?')) return;
        const fd = new FormData();
        fd.append('action','ps_fn_export_transaction');
        fd.append('payment_id', pid);
        fd.append('amount', amount);
        fd.append('nonce', PS_NONCES.fn);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => { alert(json.success ? json.data : json.data); if (json.success) location.reload(); });
    };
    window.psFnRevert = function(pid) {
        if (!confirm('Revert from Finance module?')) return;
        const fd = new FormData();
        fd.append('action','ps_fn_revert_transaction');
        fd.append('payment_id', pid);
        fd.append('nonce', PS_NONCES.fn);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => { alert(json.success ? json.data : json.data); if (json.success) location.reload(); });
    };
    window.psMarkForSale = function(id) {
        if (!confirm('Mark as FOR AUCTION?')) return;
        const fd = new FormData();
        fd.append('action','ps_update_collateral_status');
        fd.append('collateral_id', id);
        fd.append('new_status','for_auction');
        fd.append('nonce', PS_NONCES.collat);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) location.reload();
            else alert((json.data && json.data.message) ? json.data.message : 'Error updating status.');
        });
    };
    window.psMarkSold = function(id) {
        if (!confirm('Mark this item as SOLD?')) return;
        const fd = new FormData();
        fd.append('action','ps_update_collateral_status');
        fd.append('collateral_id', id);
        fd.append('new_status','sold');
        fd.append('nonce', PS_NONCES.collat);
        fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(json => {
            if (json.success) location.reload();
            else alert((json.data && json.data.message) ? json.data.message : 'Error updating status.');
        });
    };
    window.psMarkBulkAuction = function() {
        const boxes = document.querySelectorAll('.collat-auction-cb:checked');
        if (!boxes.length) { alert('Select at least one item.'); return; }
        const ids = Array.from(boxes).map(b => b.value);
        if (!confirm('Mark ' + ids.length + ' item(s) as FOR AUCTION?')) return;
        Promise.all(ids.map(id => {
            const fd = new FormData();
            fd.append('action','ps_update_collateral_status');
            fd.append('collateral_id', id);
            fd.append('new_status','for_auction');
            fd.append('nonce', PS_NONCES.collat);
            return fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json());
        })).then(() => location.reload());
    };
    window.psGenerateAuctionNotice = function() {
        // Collect selected or all for_auction items and open print window
        const boxes = document.querySelectorAll('.collat-auction-cb:checked');
        if (!boxes.length) { alert('Select items to generate auction notice.'); return; }
        const ids = Array.from(boxes).map(b => b.value).join(',');
        const fd = new FormData();
        fd.append('action','ps_generate_auction_notice');
        fd.append('collateral_ids', ids);
        fd.append('nonce', PS_NONCES.collat);
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json => {
            if (!json.success) { alert('Error: ' + ((json.data && json.data.message)||'Failed.')); return; }
            const w = window.open('','_blank');
            w.document.write(`<!DOCTYPE html><html><head><title>Auction Notice</title>
            <style>body{font-family:Arial,sans-serif;margin:0;padding:0;}@media print{.no-print{display:none!important;}}</style>
            </head><body>
            <div class="no-print" style="padding:12px;background:#1e40af;color:#fff;display:flex;gap:10px;align-items:center;">
                <strong>Auction Notice — Ready to Print</strong>
                <button onclick="window.print()" style="background:#fff;color:#1e40af;border:none;border-radius:6px;padding:6px 16px;font-weight:700;cursor:pointer;">Print</button>
                <button onclick="window.close()" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;padding:6px 12px;cursor:pointer;">Close</button>
            </div>
            ${json.data.html}
            </body></html>`);
            w.document.close();
        });
    };
    window.psOpenBulkOverdue = function() {
        if (!confirm('Mark all past-due loans as OVERDUE?')) return;
        const fd = new FormData();
        fd.append('action','ps_bulk_mark_overdue');
        fd.append('nonce', PS_NONCES.loan);
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json => {
            alert((json.data && json.data.message) ? json.data.message : (json.success ? 'Done.' : 'Failed.'));
            if (json.success) location.reload();
        });
    };
    window.psGenerateDailySummaryFromOverview = function() {
        const dt = new Date().toISOString().slice(0,10);
        const modal = document.getElementById('ps-print-modal');
        const preview = document.getElementById('ps-print-preview');
        document.getElementById('print-modal-title').textContent = 'Daily Summary Report';
        document.getElementById('print-modal-subtitle').textContent = 'Date: ' + dt + ' - Preparing print...';
        preview.innerHTML = '<div style="padding:40px;text-align:center;color:#9ca3af;">Generating...</div>';
        modal.style.display = 'flex';
        const fd = new FormData();
        fd.append('action','ps_generate_document');
        fd.append('doc_type','daily_summary');
        fd.append('report_date', dt);
        fd.append('nonce', PS_NONCES.doc);
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json => {
            if (json.success) {
                preview.innerHTML = json.data.html;
                modal.dataset.docType='daily_summary';
                modal.dataset.loanId = 0;
                const didOpen = psPrintDocument({ closeModal: true });
                if (!didOpen) {
                    document.getElementById('print-modal-subtitle').textContent = 'Popup blocked - use Print Document to continue';
                }
            }
            else preview.innerHTML = '<div style="color:#dc2626;padding:20px;">Error: ' + (json.data?.message||'Failed') + '</div>';
        });
    };

    // Backdrop close
    document.querySelectorAll(['#ps-loan-detail-modal','#ps-create-loan-modal','#ps-renew-modal','#ps-redeem-modal','#ps-customer-modal','#ps-profile-modal'].join(',')).forEach(el => {
        el.addEventListener('click', function(e) { if (e.target === this) { stopCamera(); this.style.display = 'none'; } });
    });
    // Print modal: close AND reload to refresh all modal data
    const printModalEl = document.getElementById('ps-print-modal');
    if (printModalEl) {
        printModalEl.addEventListener('click', function(e) { if (e.target === this) psDismissPrint(); });
    }

    })();
    </script>
    <?php
    return ob_get_clean();
}
function ps_modal_focus_js() { ob_start(); ?>
<script>
(function(){
'use strict';
 
// ─────────────────────────────────────────────
//  KEYBOARD SHORTCUTS  (Alt + key)
// ─────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if (!e.altKey || e.ctrlKey || e.metaKey) return;
 
    switch (e.key.toLowerCase()) {
        case 'n':
            e.preventDefault();
            if (typeof psOpenCreateLoan === 'function') psOpenCreateLoan();
            break;
        case 'c':
            e.preventDefault();
            if (typeof psOpenCustomerModal === 'function') psOpenCustomerModal(null, 'overview');
            break;
        case 's':
            e.preventDefault();
            if (typeof psOpenTicketSearch === 'function') {
                psOpenTicketSearch();
                setTimeout(function() {
                    var si = document.getElementById('ps-qsearch-input');
                    if (si) si.focus();
                }, 80);
            }
            break;
        case 'o':
            e.preventDefault();
            if (typeof psOpenBulkOverdue === 'function') psOpenBulkOverdue();
            break;
        case 'd':
            e.preventDefault();
            if (typeof psGenerateDailySummaryFromOverview === 'function') psGenerateDailySummaryFromOverview();
            break;
        case 't':
            e.preventDefault();
            if (typeof psToggleTabNav === 'function') psToggleTabNav();
            break;
    }
});
 
// ─────────────────────────────────────────────
//  TAB DROPDOWN TOGGLE
// ─────────────────────────────────────────────
window.psToggleTabNav = function(e) {
    if (e) e.stopPropagation();
    var trigger = document.getElementById('ps-tab-nav-trigger');
    var panel   = document.getElementById('ps-tab-nav-panel');
    if (!trigger || !panel) return;
    var isOpen = panel.classList.contains('open');
    panel.classList.toggle('open', !isOpen);
    trigger.classList.toggle('open', !isOpen);
    trigger.setAttribute('aria-expanded', String(!isOpen));
};
 
// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    var wrap = document.getElementById('ps-tab-nav-wrap');
    if (wrap && !wrap.contains(e.target)) {
        var panel   = document.getElementById('ps-tab-nav-panel');
        var trigger = document.getElementById('ps-tab-nav-trigger');
        if (panel)   panel.classList.remove('open');
        if (trigger) { trigger.classList.remove('open'); trigger.setAttribute('aria-expanded','false'); }
    }
});
 
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var panel   = document.getElementById('ps-tab-nav-panel');
        var trigger = document.getElementById('ps-tab-nav-trigger');
        if (panel && panel.classList.contains('open')) {
            panel.classList.remove('open');
            if (trigger) { trigger.classList.remove('open'); trigger.setAttribute('aria-expanded','false'); trigger.focus(); }
        }
    }
});
 
// ─────────────────────────────────────────────
//  MODAL FIRST-FIELD FOCUS
// ─────────────────────────────────────────────
var PS_MODAL_IDS = [
    'ps-create-loan-modal',
    'ps-renew-modal',
    'ps-redeem-modal',
    'ps-customer-modal',
    'ps-profile-modal',
    'ps-loan-detail-modal',
    'ps-print-modal'
];
 
var PS_FOCUSABLE_SEL = [
    'input:not([type=hidden]):not([readonly]):not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'button:not([disabled])'
].join(',');
 
/**
 * Focus + highlight the first focusable field inside a modal.
 * Uses the existing blue focus style from the stylesheet;
 * NO new color is introduced.
 */
function psFocusFirstField(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    requestAnimationFrame(function() {
        var el = modal.querySelector(PS_FOCUSABLE_SEL);
        if (el) {
            el.focus();
            // Trigger the existing :focus style by momentarily
            // dispatching a focus event — the browser handles the ring.
        }
    });
}
 
/**
 * Watch each modal for display changes.
 * When it becomes visible → focus first field.
 */
function psWatchModalVisibility(modalId) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.type !== 'attributes' || m.attributeName !== 'style') return;
            var vis = modal.style.display !== 'none' && modal.style.display !== '';
            if (vis) psFocusFirstField(modalId);
        });
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['style'] });
}
 
PS_MODAL_IDS.forEach(psWatchModalVisibility);
 
/**
 * When Tab is pressed and focus is OUTSIDE an open modal,
 * redirect to the first field of that modal.
 */
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    var openModal = null;
    PS_MODAL_IDS.forEach(function(id) {
        var m = document.getElementById(id);
        if (m && m.style.display !== 'none' && m.style.display !== '') openModal = m;
    });
    if (!openModal) return;
    if (!openModal.contains(document.activeElement)) {
        e.preventDefault();
        var first = openModal.querySelector(PS_FOCUSABLE_SEL);
        if (first) first.focus();
    }
});
 
/**
 * Patch the open-modal functions so focus fires even when
 * the observer fires slightly before the element is painted.
 */
var _modalPatchMap = {
    'psOpenCreateLoan'    : 'ps-create-loan-modal',
    'psOpenRenewModal'    : 'ps-renew-modal',
    'psOpenRedeemModal'   : 'ps-redeem-modal',
    'psOpenCustomerModal' : 'ps-customer-modal',
};
Object.keys(_modalPatchMap).forEach(function(fnName) {
    var original = window[fnName];
    if (typeof original !== 'function') return;
    var targetId = _modalPatchMap[fnName];
    window[fnName] = function() {
        original.apply(this, arguments);
        setTimeout(function() { psFocusFirstField(targetId); }, 130);
    };
});
 
})();
</script>
<?php return ob_get_clean();
}
 
// ============================================================
// TAB: OVERVIEW
// ============================================================

function ps_overview_tab($business_id) {
    global $wpdb;
    $lt  = $wpdb->prefix.'ps_loans';
    $pt  = $wpdb->prefix.'ps_payments';
    $ct  = $wpdb->prefix.'ps_customers';
    $colt= $wpdb->prefix.'ps_collaterals';

    $total_active   = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lt} WHERE business_id=%d AND status IN ('active','renewed')", $business_id));
    $total_overdue  = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lt} WHERE business_id=%d AND status='overdue'", $business_id));
    $total_custs    = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$ct} WHERE business_id=%d AND status='active'", $business_id));
    $due_today      = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$lt} WHERE business_id=%d AND due_date=CURDATE() AND status IN ('active','renewed')", $business_id));
    $monthly_income = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(interest_amount+service_fee),0) FROM {$pt} WHERE business_id=%d AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())", $business_id));
    $portfolio      = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(principal),0) FROM {$lt} WHERE business_id=%d AND status IN ('active','renewed','overdue')", $business_id));

    $grace = (int)bntm_get_setting('ps_grace_period', '0');
    $due_loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS customer_name,c.photo_path,col.description AS collateral_desc
         FROM {$lt} l JOIN {$ct} c ON c.id=l.customer_id JOIN {$colt} col ON col.id=l.collateral_id
         WHERE l.business_id=%d AND l.due_date=CURDATE() AND l.status IN ('active','renewed')
         ORDER BY l.ticket_number DESC", $business_id));

    $overdue_loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS customer_name,c.photo_path,col.description AS collateral_desc,
                DATEDIFF(CURDATE(),l.due_date) AS days_overdue
         FROM {$lt} l JOIN {$ct} c ON c.id=l.customer_id JOIN {$colt} col ON col.id=l.collateral_id
         WHERE l.business_id=%d AND l.status='overdue' ORDER BY l.due_date ASC LIMIT 10", $business_id));

    $recent_pay = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*,l.ticket_number,l.root_ticket,CONCAT(c.last_name,', ',c.first_name) AS customer_name
         FROM {$pt} p JOIN {$lt} l ON l.id=p.loan_id JOIN {$ct} c ON c.id=l.customer_id
         WHERE p.business_id=%d ORDER BY p.created_at DESC LIMIT 8", $business_id));

    ob_start();
    ?>
  <!-- Quick Action Bar with keyboard shortcuts — original colors preserved -->
    <div class="ps-quick-bar" role="toolbar" aria-label="Quick Actions" style="gap:10px;padding:12px 16px;">
 
        <button class="ps-qbtn primary"
                onclick="psOpenCreateLoan()"
                title="New Pawn Ticket (Alt+N)"
                style="padding:9px 18px;font-size:13px;border-radius:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-width="2.5" d="M12 5v14M5 12h14"/>
            </svg>
            New Pawn Ticket
            <kbd class="ps-shortcut-key">Alt+N</kbd>
        </button>
 
        <button class="ps-qbtn"
                onclick="psOpenCustomerModal(null,'overview')"
                title="Add Customer (Alt+C)"
                style="padding:9px 16px;font-size:13px;border-radius:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-width="2" d="M16 11c1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 3-1.34 3-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
            Add Customer
            <kbd class="ps-shortcut-key">Alt+C</kbd>
        </button>
 
        <div style="width:1px;background:rgba(255,255,255,.25);height:28px;margin:0 2px;"></div>
 
        <button class="ps-qbtn"
                onclick="psOpenTicketSearch()"
                id="qb-search-btn"
                title="Search Ticket (Alt+S)"
                style="padding:9px 16px;font-size:13px;border-radius:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <circle cx="11" cy="11" r="8" stroke-width="2"/>
                <path stroke-width="2" d="M21 21l-4.35-4.35"/>
            </svg>
            Search Ticket
            <kbd class="ps-shortcut-key">Alt+S</kbd>
        </button>
 
        <button class="ps-qbtn"
                onclick="psOpenBulkOverdue()"
                title="Mark Overdue (Alt+O)"
                style="padding:9px 16px;font-size:13px;border-radius:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Mark Overdue
            <kbd class="ps-shortcut-key">Alt+O</kbd>
        </button>
 
        <button class="ps-qbtn"
                onclick="psGenerateDailySummaryFromOverview()"
                title="Daily Summary (Alt+D)"
                style="padding:9px 16px;font-size:13px;border-radius:8px;">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                <path stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Daily Summary
            <kbd class="ps-shortcut-key">Alt+D</kbd>
        </button>
    </div>
 
    <!-- Shortcut hint strip — sits below the quick bar -->

   
    <!-- Ticket Search Bar (hidden by default) -->
    <div id="ps-ticket-search-bar" style="display:none;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:14px;margin-bottom:12px;">
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="ps-qsearch-input" placeholder="Enter ticket number or customer name..." style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;" oninput="psQuickSearchTicket(this.value)">
            <button onclick="document.getElementById('ps-ticket-search-bar').style.display='none'" style="background:none;border:none;cursor:pointer;color:#6b7280;font-size:18px;padding:4px 8px;">✕</button>
        </div>
        <div id="ps-qsearch-results" style="margin-top:10px;"></div>
    </div>

    <!-- Stats -->
    <div class="bntm-stats-row">
        <div class="bntm-stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#1e40af,#1d3a9d);"><svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div><div class="stat-content"><h3>Active Loans</h3><p class="stat-number"><?php echo number_format($total_active); ?></p><span class="stat-label">Currently active</span></div></div>
        <div class="bntm-stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626);"><svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div class="stat-content"><h3>Overdue</h3><p class="stat-number"><?php echo number_format($total_overdue); ?></p><span class="stat-label">Past due date</span></div></div>
        <div class="bntm-stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div><div class="stat-content"><h3>Due Today</h3><p class="stat-number"><?php echo number_format($due_today); ?></p><span class="stat-label">Tickets due</span></div></div>
        <div class="bntm-stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#059669);"><svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23" stroke-width="2"/><path stroke-width="2" d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div><div class="stat-content"><h3>Monthly Income</h3><p class="stat-number">&#8369;<?php echo number_format($monthly_income,2); ?></p><span class="stat-label">Interest earned</span></div></div>
        <div class="bntm-stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);"><svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-width="2" d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg></div><div class="stat-content"><h3>Portfolio</h3><p class="stat-number">&#8369;<?php echo number_format($portfolio,2); ?></p><span class="stat-label">Outstanding principal</span></div></div>
        <div class="bntm-stat-card"><div class="stat-icon" style="background:linear-gradient(135deg,#06b6d4,#0891b2);"><svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z"/></svg></div><div class="stat-content"><h3>Customers</h3><p class="stat-number"><?php echo number_format($total_custs); ?></p><span class="stat-label">Registered</span></div></div>
    </div>

    <?php if (!empty($due_loans)): ?>
    <div class="bntm-form-section">
        <h3 style="color:#92400e;display:flex;align-items:center;gap:6px;margin-top:0;">Due Today (<?php echo count($due_loans); ?>)</h3>
        <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Ticket #</th><th>Customer</th><th>Collateral</th><th>Principal</th><th>Rate</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($due_loans as $loan): ?>
        <tr>
            <td><strong style="font-family:monospace;"><?php echo esc_html($loan->ticket_number); ?></strong>
                <?php if ($loan->root_ticket !== $loan->ticket_number): ?><div class="ps-ticket-chain">chain: <?php echo esc_html($loan->root_ticket); ?></div><?php endif; ?>
            </td>
            <td><?php echo esc_html($loan->customer_name); ?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($loan->collateral_desc); ?></td>
            <td>&#8369;<?php echo number_format($loan->principal,2); ?></td>
            <td><?php echo $loan->interest_rate; ?>%/mo</td>
            <td><div style="display:flex;gap:4px;">
                <button class="ps-action-btn ps-btn-view" onclick="psViewLoanDetail(<?php echo $loan->id; ?>)">View</button>
                <button class="ps-action-btn ps-btn-renew" onclick="psOpenRenewModal(<?php echo $loan->id; ?>)">Renew</button>
                <button class="ps-action-btn ps-btn-redeem" onclick="psOpenRedeemModal(<?php echo $loan->id; ?>)">Redeem</button>
                <button class="ps-action-btn ps-btn-print" onclick="psShowPrintModal(<?php echo $loan->id; ?>,'pawn_ticket')" title="Print">Print</button>
            </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($overdue_loans)): ?>
    <div class="bntm-form-section">
        <h3 style="color:#dc2626;margin-top:0;">Overdue Loans <?php echo $grace > 0 ? "(Grace: {$grace}d)" : ''; ?></h3>
        <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Ticket #</th><th>Customer</th><th>Principal</th><th>Due Date</th><th>Days Overdue</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($overdue_loans as $loan): ?>
        <tr style="background:#fff9f9;">
            <td><strong style="font-family:monospace;"><?php echo esc_html($loan->ticket_number); ?></strong></td>
            <td><?php echo esc_html($loan->customer_name); ?></td>
            <td>&#8369;<?php echo number_format($loan->principal,2); ?></td>
            <td><?php echo date('M d, Y',strtotime($loan->due_date)); ?></td>
            <td><span style="color:#dc2626;font-weight:700;"><?php echo $loan->days_overdue; ?>d</span><?php if ($grace > 0 && $loan->days_overdue <= $grace): ?><span style="color:#059669;font-size:11px;margin-left:4px;">(grace)</span><?php endif; ?></td>
            <td><div style="display:flex;gap:4px;">
                <button class="ps-action-btn ps-btn-renew" onclick="psOpenRenewModal(<?php echo $loan->id; ?>)">Renew</button>
                <button class="ps-action-btn ps-btn-redeem" onclick="psOpenRedeemModal(<?php echo $loan->id; ?>)">Redeem</button>
                <button class="ps-action-btn ps-btn-view" onclick="psViewLoanDetail(<?php echo $loan->id; ?>)">View</button>
            </div></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
    <?php endif; ?>

    <div class="bntm-form-section"><h3 style="margin-top:0;">Recent Transactions</h3>
    <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Date</th><th>Ticket #</th><th>Root Ticket</th><th>Customer</th><th>Type</th><th>Amount</th><th>Method</th></tr></thead><tbody>
    <?php if (empty($recent_pay)): ?><tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:30px;">No transactions yet</td></tr>
    <?php else: foreach ($recent_pay as $pay):
        $tc = ['interest'=>'#1d4ed8','renewal'=>'#7c3aed','redemption'=>'#059669','penalty'=>'#dc2626','service_fee'=>'#f59e0b','partial_principal'=>'#0891b2'][$pay->payment_type] ?? '#374151'; ?>
    <tr>
        <td style="font-size:12px;"><?php echo date('M d, Y H:i',strtotime($pay->created_at)); ?></td>
        <td><strong style="font-family:monospace;"><?php echo esc_html($pay->ticket_number); ?></strong></td>
        <td><span class="ps-ticket-chain"><?php echo esc_html($pay->root_ticket); ?></span></td>
        <td><?php echo esc_html($pay->customer_name); ?></td>
        <td><span style="color:<?php echo $tc; ?>;font-weight:600;font-size:12px;text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_type); ?></span></td>
        <td style="font-weight:700;">&#8369;<?php echo number_format($pay->amount,2); ?></td>
        <td style="font-size:12px;text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_method); ?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div></div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: LOANS
// ============================================================

function ps_loans_tab($business_id) {
    global $wpdb;
    $lt = $wpdb->prefix.'ps_loans';
    $fs = isset($_GET['ls']) ? sanitize_text_field($_GET['ls']) : '';
    $fq = isset($_GET['lq']) ? sanitize_text_field($_GET['lq']) : '';
    $fr = isset($_GET['lr']) ? sanitize_text_field($_GET['lr']) : ''; // filter by root

    $w = "WHERE l.business_id={$business_id}";
    if ($fs) $w .= $wpdb->prepare(" AND l.status=%s", $fs);
    if ($fq) $w .= $wpdb->prepare(" AND (l.ticket_number LIKE %s OR l.root_ticket LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)", "%{$fq}%", "%{$fq}%", "%{$fq}%", "%{$fq}%");

    $loans = $wpdb->get_results(
        "SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS customer_name,c.contact_number,c.photo_path,
                col.description AS collateral_desc,col.category AS collateral_cat,
                DATEDIFF(CURDATE(),l.due_date) AS days_past_due
         FROM {$lt} l
         JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         JOIN {$wpdb->prefix}ps_collaterals col ON col.id=l.collateral_id
         {$w}
         ORDER BY FIELD(l.status,'overdue','active','renewed','redeemed','forfeited'),l.due_date ASC LIMIT 300"
    );

    ob_start();
    ?>
    <!-- Quick Bar -->
    <div class="ps-search-bar"><form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <input type="hidden" name="tab" value="loans">
        <input type="text" name="lq" value="<?php echo esc_attr($fq); ?>" placeholder="Search by Ticket #, root ticket, or customer...">
        <select name="ls">
            <option value="">All Statuses</option>
            <?php foreach (['active','renewed','overdue','redeemed','forfeited'] as $st): ?>
            <option value="<?php echo $st; ?>" <?php selected($fs,$st); ?>><?php echo ucfirst($st); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bntm-btn-primary" style="padding:7px 14px;">Search</button>
        <?php if ($fs||$fq): ?><a href="?tab=loans" class="bntm-btn-secondary" style="text-decoration:none;padding:7px 12px;">Clear</a><?php endif; ?>
    </form></div>

    <div class="bntm-table-wrapper"><table class="bntm-table">
    <thead><tr><th>Ticket #</th><th>Root Ticket</th><th>Customer</th><th>Collateral</th><th>Principal</th><th>Rate</th><th>Loan Date</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($loans)): ?><tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:40px;">No pawn tickets found</td></tr>
    <?php else: foreach ($loans as $loan):
        $is_od = in_array($loan->status, ['active','renewed']) && strtotime($loan->due_date) < strtotime('today');
        $grace = (int)bntm_get_setting('ps_grace_period', '0');
        $is_new_ticket = $loan->root_ticket === $loan->ticket_number;
        ?>
    <tr style="<?php echo $is_od ? 'background:#fff9f9;' : ''; ?>">
        <td>
            <strong style="font-family:monospace;"><?php echo esc_html($loan->ticket_number); ?></strong>
            <?php if (!$is_new_ticket): ?>
            <div style="font-size:10px;color:#7c3aed;margin-top:2px;">renewal ticket</div>
            <?php endif; ?>
        </td>
        <td>
            <?php if (!$is_new_ticket): ?>
            <span class="ps-ticket-chain" style="cursor:pointer;" onclick="psViewLoanDetail(<?php echo $loan->id; ?>)" title="View chain"><?php echo esc_html($loan->root_ticket); ?></span>
            <?php else: ?>
            <span style="color:#9ca3af;font-size:11px;">—</span>
            <?php endif; ?>
        </td>
        <td>
            <div style="display:flex;align-items:center;gap:7px;">
                <?php if ($loan->photo_path): ?>
                <img src="<?php echo esc_url(BNTM_PS_PHOTO_URL.$loan->photo_path); ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;" />
                <?php else: ?>
                <div style="width:28px;height:28px;border-radius:50%;background:#1e40af;display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;flex-shrink:0;"><?php echo strtoupper(substr($loan->customer_name,0,1)); ?></div>
                <?php endif; ?>
                <div>
                    <div style="font-weight:600;font-size:12px;"><?php echo esc_html($loan->customer_name); ?></div>
                    <div style="font-size:11px;color:#9ca3af;"><?php echo esc_html($loan->contact_number); ?></div>
                </div>
            </div>
        </td>
        <td><span class="ps-collateral-cat"><?php echo ucfirst($loan->collateral_cat); ?></span><div style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($loan->collateral_desc); ?></div></td>
        <td style="font-weight:700;">&#8369;<?php echo number_format($loan->principal,2); ?>
            <?php if ($loan->accrued_interest_carried > 0): ?><div style="font-size:10px;color:#7c3aed;">+₱<?php echo number_format($loan->accrued_interest_carried,2); ?> carried</div><?php endif; ?>
        </td>
        <td><?php echo $loan->interest_rate; ?>%/mo</td>
        <td style="font-size:12px;"><?php echo date('M d, Y',strtotime($loan->loan_date)); ?></td>
        <td style="font-size:12px;"><?php echo date('M d, Y',strtotime($loan->due_date)); ?>
            <?php if ($loan->days_past_due > 0 && !in_array($loan->status,['redeemed','forfeited'])): ?>
            <div style="font-size:11px;font-weight:600;color:<?php echo $loan->days_past_due <= $grace ? '#059669' : '#dc2626'; ?>;">
                <?php echo $loan->days_past_due; ?>d <?php echo $loan->days_past_due <= $grace ? '(grace)' : 'overdue'; ?>
            </div>
            <?php endif; ?>
        </td>
        <td><span class="ps-status-badge ps-status-<?php echo $loan->status; ?>"><?php echo ucfirst($loan->status); ?></span></td>
        <td><div style="display:flex;gap:3px;flex-wrap:wrap;">
            <button class="ps-action-btn ps-btn-view" onclick="psViewLoanDetail(<?php echo $loan->id; ?>)" title="Details">View</button>
            <?php if ($loan->status === 'active' || $loan->status === 'overdue'): ?>
            <button class="ps-action-btn ps-btn-renew" onclick="psOpenRenewModal(<?php echo $loan->id; ?>)">Renew</button>
            <button class="ps-action-btn ps-btn-redeem" onclick="psOpenRedeemModal(<?php echo $loan->id; ?>)">Redeem</button>
            <button class="ps-action-btn ps-btn-forfeit" onclick="psConfirmForfeit(<?php echo $loan->id; ?>,'<?php echo esc_js($loan->ticket_number); ?>')">Forfeit</button>
            <?php elseif ($loan->status === 'renewed'): ?>
            <span style="font-size:11px;color:#9ca3af;padding:4px 6px;background:#f9fafb;border-radius:4px;border:1px solid #e5e7eb;">Superseded</span>
            <?php endif; ?>
            <button class="ps-action-btn ps-btn-print" onclick="psShowPrintModal(<?php echo $loan->id; ?>,'pawn_ticket')" title="Print">Print</button>
        </div></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: COLLATERALS
// ============================================================

function ps_collaterals_tab($business_id) {
    global $wpdb;
    $colt = $wpdb->prefix.'ps_collaterals';
    $fc = isset($_GET['cc']) ? sanitize_text_field($_GET['cc']) : '';
    $fst= isset($_GET['cst'])? sanitize_text_field($_GET['cst']): '';
    $fq = isset($_GET['cq']) ? sanitize_text_field($_GET['cq']) : '';

    $w = "WHERE col.business_id={$business_id}";
    if ($fc)  $w .= $wpdb->prepare(" AND col.category=%s", $fc);
    if ($fst) $w .= $wpdb->prepare(" AND col.status=%s", $fst);
    if ($fq)  $w .= $wpdb->prepare(" AND (col.description LIKE %s OR col.brand LIKE %s OR col.serial_number LIKE %s)", "%{$fq}%", "%{$fq}%", "%{$fq}%");

    $collaterals = $wpdb->get_results(
        "SELECT col.*,l.ticket_number,l.root_ticket,CONCAT(c.last_name,', ',c.first_name) AS customer_name
         FROM {$colt} col
         LEFT JOIN {$wpdb->prefix}ps_loans l ON l.id=col.loan_id
         LEFT JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         {$w} ORDER BY col.created_at DESC LIMIT 200"
    );
    $tp  = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$colt} WHERE business_id=%d AND status='pawned'", $business_id));
    $ta  = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(appraised_value),0) FROM {$colt} WHERE business_id=%d AND status='pawned'", $business_id));
    $tfs = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$colt} WHERE business_id=%d AND status IN ('for_sale','for_auction','sold')", $business_id));

    ob_start();
    ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px;">
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:26px;font-weight:800;color:#1d4ed8;"><?php echo $tp; ?></div><div style="font-size:12px;color:#3b82f6;font-weight:600;">Currently Pawned</div></div>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:800;color:#059669;">&#8369;<?php echo number_format($ta,2); ?></div><div style="font-size:12px;color:#10b981;font-weight:600;">Total Appraised Value</div></div>
        <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:26px;font-weight:800;color:#d97706;"><?php echo $tfs; ?></div><div style="font-size:12px;color:#f59e0b;font-weight:600;">For Auction / For Sale</div></div>
    </div>
    <div class="ps-search-bar"><form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="collaterals">
        <input type="text" name="cq" value="<?php echo esc_attr($fq); ?>" placeholder="Search by descriptionription, brand, serial...">
        <select name="cc"><option value="">All Categories</option>
            <?php foreach (['jewelry','electronics','watches','bags','documents','others'] as $cat): ?>
            <option value="<?php echo $cat; ?>" <?php selected($fc,$cat); ?>><?php echo ucfirst($cat); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="cst"><option value="">All Statuses</option>
            <?php foreach (['pawned','redeemed','forfeited','for_auction','sold'] as $st): ?>
            <option value="<?php echo $st; ?>" <?php selected($fst,$st); ?>><?php echo ucfirst(str_replace('_',' ',$st)); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bntm-btn-primary" style="padding:7px 14px;">Filter</button>
        <?php if ($fc||$fst||$fq): ?><a href="?tab=collaterals" class="bntm-btn-secondary" style="text-decoration:none;padding:7px 12px;">Clear</a><?php endif; ?>
    </form></div>

    <!-- Bulk action bar for forfeited/auction items -->
    <div id="collat-bulk-bar" style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer;">
            <input type="checkbox" id="collat-select-all" onchange="document.querySelectorAll('.collat-auction-cb').forEach(cb=>cb.checked=this.checked)"> Select All Auctionable
        </label>
        <div style="width:1px;background:#fde68a;height:20px;"></div>
        <button onclick="psMarkBulkAuction()" style="background:#d97706;color:#fff;border:none;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;">Mark Selected for Auction</button>
        <button onclick="psGenerateAuctionNotice()" style="background:#1e40af;color:#fff;border:none;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:700;cursor:pointer;">Generate Auction Notice PDF</button>
        <span style="font-size:11px;color:#92400e;">Check items below to bulk-mark or generate notices</span>
    </div>

    <div class="bntm-table-wrapper"><table class="bntm-table">
    <thead><tr><th style="width:32px;"></th><th>Category</th><th>Description</th><th>Brand/Model</th><th>Condition</th><th>Appraised Value</th><th>Ticket / Root</th><th>Customer</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($collaterals)): ?><tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:40px;">No collateral records found</td></tr>
    <?php else: foreach ($collaterals as $col):
        $cc = ['excellent'=>'#059669','good'=>'#0284c7','fair'=>'#d97706','poor'=>'#dc2626'][$col->item_condition] ?? '#374151';
        $sm = ['pawned'=>'ps-status-active','redeemed'=>'ps-status-redeemed','forfeited'=>'ps-status-forfeited','for_sale'=>'ps-status-overdue','for_auction'=>'ps-status-forfeited','sold'=>'ps-status-redeemed'][$col->status] ?? '';
        $sl = ['pawned'=>'Pawned','redeemed'=>'Redeemed','forfeited'=>'Forfeited','for_sale'=>'For Sale','for_auction'=>'For Auction','sold'=>'Sold'][$col->status] ?? ucfirst($col->status);
        $can_auction = in_array($col->status, ['forfeited','for_sale']);
        $can_auction_notice = in_array($col->status, ['forfeited','for_sale','for_auction','sold']);
        ?>
    <tr>
        <td style="text-align:center;">
            <?php if ($can_auction_notice): ?>
            <input type="checkbox" class="collat-auction-cb" value="<?php echo $col->id; ?>" style="width:16px;height:16px;cursor:pointer;">
            <?php endif; ?>
        </td>
        <td><span class="ps-collateral-cat"><?php echo ucfirst($col->category); ?></span></td>
        <td><div style="font-weight:600;font-size:13px;max-width:180px;"><?php echo esc_html($col->description); ?></div>
            <?php if ($col->karat||$col->weight_grams>0): ?><div style="font-size:11px;color:#9ca3af;"><?php echo $col->karat; ?> <?php echo $col->weight_grams>0?$col->weight_grams.'g':''; ?></div><?php endif; ?>
        </td>
        <td><?php echo $col->brand ? esc_html($col->brand) : '<span style="color:#d1d5db;">—</span>'; ?><?php if ($col->model): ?><div style="font-size:11px;color:#9ca3af;"><?php echo esc_html($col->model); ?></div><?php endif; ?></td>
        <td><span style="color:<?php echo $cc; ?>;font-weight:600;font-size:12px;text-transform:capitalize;"><?php echo $col->item_condition; ?></span></td>
        <td style="font-weight:700;">&#8369;<?php echo number_format($col->appraised_value,2); ?></td>
        <td><?php if ($col->ticket_number): ?>
            <div style="font-family:monospace;font-size:12px;font-weight:600;"><?php echo esc_html($col->ticket_number); ?></div>
            <?php if ($col->root_ticket && $col->root_ticket !== $col->ticket_number): ?><span class="ps-ticket-chain"><?php echo esc_html($col->root_ticket); ?></span><?php endif; ?>
        <?php else: ?><span style="color:#d1d5db;">—</span><?php endif; ?></td>
        <td style="font-size:12px;"><?php echo esc_html($col->customer_name ?? '—'); ?></td>
        <?php
            $badge_style = '';
            if ($col->status === 'for_auction') $badge_style = 'background:#d97706;color:#fff;border-color:#d97706;';
            elseif ($col->status === 'sold') $badge_style = 'background:#059669;color:#fff;border-color:#059669;';
            elseif ($col->status === 'for_sale') $badge_style = 'background:#f59e0b;color:#fff;border-color:#f59e0b;';
        ?>
        <td><span class="ps-status-badge <?php echo $sm; ?>" style="<?php echo $badge_style; ?>"><?php echo $sl; ?></span></td>
        <td><div style="display:flex;gap:4px;flex-wrap:wrap;">
            <?php if ($can_auction): ?>
                <button class="ps-action-btn" style="background:#fef9c3;color:#92400e;font-size:11px;border:1px solid #fde68a;" onclick="psMarkForSale(<?php echo $col->id; ?>)">For Auction</button>
            <?php endif; ?>
            <?php if ($col->status === 'for_auction'): ?>
                <button class="ps-action-btn" style="background:#dcfce7;color:#166534;font-size:11px;border:1px solid #bbf7d0;" onclick="psMarkSold(<?php echo $col->id; ?>)">Mark Sold</button>
            <?php endif; ?>
            <?php if ($col->status === 'sold'): ?>
                <span style="font-size:10px;color:#059669;font-weight:700;background:#dcfce7;padding:3px 8px;border-radius:4px;border:1px solid #bbf7d0;">Sold</span>
            <?php endif; ?>
            <?php if ($col->loan_id): ?><button class="ps-action-btn ps-btn-view" onclick="psViewLoanDetail(<?php echo $col->loan_id; ?>)">View</button><?php endif; ?>
        </div></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: CUSTOMERS
// ============================================================

function ps_customers_tab($business_id) {
    global $wpdb;
    $ct = $wpdb->prefix.'ps_customers';
    $lt = $wpdb->prefix.'ps_loans';
    $fq = isset($_GET['custq']) ? sanitize_text_field($_GET['custq']) : '';
    $ff = isset($_GET['custf']) ? sanitize_text_field($_GET['custf']) : '';

    $w = "WHERE c.business_id={$business_id}";
    if ($fq) $w .= $wpdb->prepare(" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.contact_number LIKE %s OR c.id_number LIKE %s)", "%{$fq}%", "%{$fq}%", "%{$fq}%", "%{$fq}%");
    if ($ff) $w .= $wpdb->prepare(" AND c.customer_flag=%s", $ff);

    $customers = $wpdb->get_results(
        "SELECT c.*,
            (SELECT COUNT(*) FROM {$lt} WHERE customer_id=c.id) AS total_loans,
            (SELECT COUNT(*) FROM {$lt} WHERE customer_id=c.id AND status IN ('active','renewed','overdue')) AS active_loans,
            (SELECT COALESCE(SUM(principal),0) FROM {$lt} WHERE customer_id=c.id AND status IN ('active','renewed','overdue')) AS outstanding
         FROM {$ct} c {$w} ORDER BY c.last_name ASC LIMIT 200"
    );

    ob_start();
    ?>
    <div class="ps-search-bar"><form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="customers">
        <input type="text" name="custq" value="<?php echo esc_attr($fq); ?>" placeholder="Search by nameame, contact, ID...">
        <select name="custf"><option value="">All Customers</option>
            <?php foreach (['normal','vip','delinquent','blacklisted'] as $fl): ?>
            <option value="<?php echo $fl; ?>" <?php selected($ff,$fl); ?>><?php echo ucfirst($fl); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bntm-btn-primary" style="padding:7px 14px;">Filter</button>
        <?php if ($fq||$ff): ?><a href="?tab=customers" class="bntm-btn-secondary" style="text-decoration:none;padding:7px 12px;">Clear</a><?php endif; ?>
    </form></div>

    <div class="bntm-table-wrapper"><table class="bntm-table">
    <thead><tr><th>Photo</th><th>Name</th><th>Contact</th><th>Government ID</th><th>Total Loans</th><th>Active</th><th>Outstanding</th><th>Flag</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($customers)): ?><tr><td colspan="9" style="text-align:center;color:#9ca3af;padding:40px;">No customers found</td></tr>
    <?php else: foreach ($customers as $c):
        $fm = ['normal'=>'ps-flag-normal','vip'=>'ps-flag-vip','delinquent'=>'ps-flag-delinquent','blacklisted'=>'ps-flag-blacklisted'][$c->customer_flag] ?? 'ps-flag-normal'; ?>
    <tr>
        <td>
            <?php if ($c->photo_path): ?>
            <img src="<?php echo esc_url(BNTM_PS_PHOTO_URL.$c->photo_path); ?>" class="ps-photo-thumb"
                 onerror="this.style.display='none';" />
            <?php else: ?>
            <div style="width:44px;height:44px;border-radius:50%;background:#1e40af;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;font-weight:800;"><?php echo strtoupper(substr($c->last_name,0,1)); ?></div>
            <?php endif; ?>
        </td>
        <td><div style="font-weight:600;"><?php echo esc_html($c->last_name.', '.$c->first_name.' '.$c->middle_name); ?></div><div style="font-size:11px;color:#9ca3af;"><?php echo esc_html($c->email); ?></div></td>
        <td><?php echo esc_html($c->contact_number); ?></td>
        <td><div style="font-size:12px;"><?php echo esc_html($c->id_type); ?></div><div style="font-family:monospace;font-size:12px;"><?php echo esc_html($c->id_number); ?></div></td>
        <td style="text-align:center;"><?php echo $c->total_loans; ?></td>
        <td style="text-align:center;font-weight:<?php echo $c->active_loans>0?'700':'400'; ?>;color:<?php echo $c->active_loans>0?'#059669':'inherit'; ?>;"><?php echo $c->active_loans; ?></td>
        <td style="font-weight:700;">&#8369;<?php echo number_format($c->outstanding,2); ?></td>
        <td><span class="ps-status-badge <?php echo $fm; ?>"><?php echo ucfirst($c->customer_flag); ?></span></td>
        <td><div style="display:flex;gap:4px;">
            <button class="ps-action-btn ps-btn-view" onclick="psViewCustomerProfile(<?php echo $c->id; ?>)">Profile</button>
            <button class="ps-action-btn" style="background:#f3f4f6;color:#374151;" onclick="psEditCustomer(<?php echo $c->id; ?>,'<?php echo esc_js($c->first_name); ?>','<?php echo esc_js($c->last_name); ?>','<?php echo esc_js($c->middle_name); ?>','<?php echo esc_js($c->address); ?>','<?php echo esc_js($c->contact_number); ?>','<?php echo esc_js($c->email); ?>','<?php echo esc_js($c->id_type); ?>','<?php echo esc_js($c->id_number); ?>','<?php echo $c->customer_flag; ?>','<?php echo esc_js($c->notes); ?>','<?php echo esc_js($c->photo_path); ?>')">Edit</button>
            <?php if ($c->active_loans == 0): ?><button class="ps-action-btn ps-btn-forfeit" onclick="if(confirm('Delete customer?')){const fd=new FormData();fd.append('action','ps_delete_customer');fd.append('customer_id',<?php echo $c->id; ?>);fd.append('nonce','<?php echo wp_create_nonce('ps_customer_nonce'); ?>');fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{if(j.success)location.reload();else alert(j.data.message);});}">Del</button><?php endif; ?>
        </div></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
    <?php
    return ob_get_clean();
}


// ============================================================
// TAB: PAYMENTS
// ============================================================

function ps_payments_tab($business_id) {
    global $wpdb;
    $pt = $wpdb->prefix.'ps_payments';
    $lt = $wpdb->prefix.'ps_loans';
    $ct = $wpdb->prefix.'ps_customers';

    $fq    = isset($_GET['pq'])    ? sanitize_text_field($_GET['pq'])    : '';
    $ftype = isset($_GET['ptype']) ? sanitize_text_field($_GET['ptype']) : '';
    $fdate = isset($_GET['pdate']) ? sanitize_text_field($_GET['pdate']) : '';

    $w = "WHERE p.business_id={$business_id}";
    if ($fq)    $w .= $wpdb->prepare(" AND (l.ticket_number LIKE %s OR l.root_ticket LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)", "%{$fq}%", "%{$fq}%", "%{$fq}%", "%{$fq}%");
    if ($ftype) $w .= $wpdb->prepare(" AND p.payment_type=%s", $ftype);
    if ($fdate) $w .= $wpdb->prepare(" AND DATE(p.created_at)=%s", $fdate);

    $payments = $wpdb->get_results(
        "SELECT p.*,l.ticket_number,l.root_ticket,l.transaction_type AS loan_tx_type,CONCAT(c.last_name,', ',c.first_name) AS customer_name
         FROM {$pt} p JOIN {$lt} l ON l.id=p.loan_id JOIN {$ct} c ON c.id=l.customer_id
         {$w} ORDER BY p.created_at DESC LIMIT 300"
    );

    $today_col  = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$pt} WHERE business_id=%d AND DATE(created_at)=CURDATE()", $business_id));
    $month_col  = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$pt} WHERE business_id=%d AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())", $business_id));
    $month_int  = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(interest_amount),0) FROM {$pt} WHERE business_id=%d AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())", $business_id));

    ob_start();
    ?>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:18px;">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:800;color:#059669;">&#8369;<?php echo number_format($today_col,2); ?></div><div style="font-size:12px;color:#10b981;font-weight:600;">Today's Collections</div></div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:800;color:#1d4ed8;">&#8369;<?php echo number_format($month_col,2); ?></div><div style="font-size:12px;color:#3b82f6;font-weight:600;">Monthly Collections</div></div>
        <div style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:800;color:#7c3aed;">&#8369;<?php echo number_format($month_int,2); ?></div><div style="font-size:12px;color:#8b5cf6;font-weight:600;">Monthly Interest Income</div></div>
    </div>
    <div class="ps-search-bar"><form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="hidden" name="tab" value="payments">
        <input type="text" name="pq" value="<?php echo esc_attr($fq); ?>" placeholder="Search by Ticket # or customer...">
        <select name="ptype"><option value="">All Types</option>
            <?php foreach (['interest','renewal','redemption','penalty','service_fee','partial_principal'] as $t): ?>
            <option value="<?php echo $t; ?>" <?php selected($ftype,$t); ?>><?php echo ucfirst(str_replace('_',' ',$t)); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="pdate" value="<?php echo esc_attr($fdate); ?>">
        <button type="submit" class="bntm-btn-primary" style="padding:7px 14px;">Filter</button>
        <?php if ($fq||$ftype||$fdate): ?><a href="?tab=payments" class="bntm-btn-secondary" style="text-decoration:none;padding:7px 12px;">Clear</a><?php endif; ?>
    </form></div>

    <div class="bntm-table-wrapper"><table class="bntm-table">
    <thead><tr><th>Date/Time</th><th>Ticket #</th><th>Root Ticket</th><th>Customer</th><th>Type</th><th>Amount</th><th>Interest</th><th>Penalty</th><th>Method</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($payments)): ?><tr><td colspan="10" style="text-align:center;color:#9ca3af;padding:40px;">No payments found</td></tr>
    <?php else: foreach ($payments as $pay):
        $tc = ['interest'=>'#1d4ed8','renewal'=>'#7c3aed','redemption'=>'#059669','penalty'=>'#dc2626','service_fee'=>'#f59e0b','partial_principal'=>'#0891b2'][$pay->payment_type] ?? '#374151'; ?>
    <tr>
        <td style="font-size:12px;white-space:nowrap;"><?php echo date('M d, Y H:i',strtotime($pay->created_at)); ?></td>
        <td><strong style="font-family:monospace;"><?php echo esc_html($pay->ticket_number); ?></strong></td>
        <td><span class="ps-ticket-chain"><?php echo esc_html($pay->root_ticket); ?></span></td>
        <td><?php echo esc_html($pay->customer_name); ?></td>
        <td><span style="color:<?php echo $tc; ?>;font-weight:700;font-size:12px;text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_type); ?></span></td>
        <td style="font-weight:700;">&#8369;<?php echo number_format($pay->amount,2); ?></td>
        <td>&#8369;<?php echo number_format($pay->interest_amount,2); ?></td>
        <td>&#8369;<?php echo number_format($pay->penalty_amount,2); ?></td>
        <td style="font-size:12px;text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_method); ?></td>
        <td><button class="ps-action-btn ps-btn-print" onclick="psShowPrintModal(<?php echo $pay->loan_id; ?>,'payment_receipt')" title="Receipt">Print</button></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: FINANCE
// ============================================================

function ps_finance_tab($business_id) {
    global $wpdb;
    $pt = $wpdb->prefix.'ps_payments';
    $lt = $wpdb->prefix.'ps_loans';
    $ct = $wpdb->prefix.'ps_customers';
    $fn = $wpdb->prefix.'fn_transactions';

    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT p.*,l.ticket_number,l.root_ticket,CONCAT(c.last_name,', ',c.first_name) AS customer_name,
                (SELECT id FROM {$fn} WHERE reference_type='ps_payment' AND reference_id=p.id LIMIT 1) AS fn_id
         FROM {$pt} p JOIN {$lt} l ON l.id=p.loan_id JOIN {$ct} c ON c.id=l.customer_id
         WHERE p.business_id=%d AND p.payment_type IN ('interest','service_fee','penalty')
         ORDER BY p.created_at DESC LIMIT 200", $business_id
    ));

    $exported      = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT reference_id) FROM {$fn} WHERE business_id=%d AND reference_type='ps_payment'", $business_id));
    $total_exported= (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$fn} WHERE business_id=%d AND reference_type='ps_payment'", $business_id));

    ob_start(); ?>
    <h3 style="margin:0 0 14px;">Finance Module Export</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:26px;font-weight:800;color:#059669;"><?php echo $exported; ?></div><div style="font-size:12px;color:#10b981;font-weight:600;">Entries Exported</div></div>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:22px;font-weight:800;color:#1d4ed8;">&#8369;<?php echo number_format($total_exported,2); ?></div><div style="font-size:12px;color:#3b82f6;font-weight:600;">Total Exported</div></div>
    </div>
    <div class="bntm-table-wrapper"><table class="bntm-table">
    <thead><tr><th>Date</th><th>Ticket #</th><th>Root</th><th>Customer</th><th>Type</th><th>Amount</th><th>Finance Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (empty($payments)): ?><tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:40px;">No exportable payments found</td></tr>
    <?php else: foreach ($payments as $pay): $is_exported = !empty($pay->fn_id); ?>
    <tr>
        <td style="font-size:12px;"><?php echo date('M d, Y',strtotime($pay->created_at)); ?></td>
        <td><strong style="font-family:monospace;"><?php echo esc_html($pay->ticket_number); ?></strong></td>
        <td><span class="ps-ticket-chain"><?php echo esc_html($pay->root_ticket); ?></span></td>
        <td><?php echo esc_html($pay->customer_name); ?></td>
        <td style="text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_type); ?></td>
        <td style="font-weight:700;">&#8369;<?php echo number_format($pay->amount,2); ?></td>
        <td><?php if ($is_exported): ?><span class="ps-status-badge ps-status-active">Exported</span><?php else: ?><span class="ps-status-badge" style="background:#f3f4f6;color:#6b7280;">Pending</span><?php endif; ?></td>
        <td><?php if (!$is_exported): ?><button class="ps-action-btn ps-btn-view" onclick="psFnExport(<?php echo $pay->id; ?>,<?php echo $pay->amount; ?>)">Export</button><?php else: ?><button class="ps-action-btn" style="background:#fef2f2;color:#dc2626;" onclick="psFnRevert(<?php echo $pay->id; ?>)">Revert</button><?php endif; ?></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: DOCUMENTS
// ============================================================
function ps_documents_tab( $business_id ) {
    global $wpdb;
    $doc_logs = $wpdb->get_results( $wpdb->prepare(
        "SELECT dl.*, l.ticket_number
         FROM {$wpdb->prefix}ps_document_log dl
         LEFT JOIN {$wpdb->prefix}ps_loans l ON l.id = dl.loan_id
         WHERE dl.business_id = %d ORDER BY dl.created_at DESC LIMIT 40",
        $business_id
    ) );
 
    ob_start(); ?>
    <h3 style="margin:0 0 16px;">Documents &amp; Print Center</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div class="bntm-form-section">
            <h4 style="margin:0 0 12px;font-size:14px;font-weight:700;">Generate Document</h4>
 
            <div class="bntm-form-group">
                <label>Search Ticket / Customer</label>
                <div style="position:relative;">
                    <input type="text" id="doc-loan-search" placeholder="Type ticket # or customer name…" autocomplete="off"
                        style="width:100%;box-sizing:border-box;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;"
                        oninput="psDocSearchTicket(this.value)">
                    <div id="doc-loan-results" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.12);max-height:220px;overflow-y:auto;z-index:999;margin-top:2px;"></div>
                </div>
                <div id="doc-selected-loan" style="display:none;margin-top:8px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 12px;font-size:12px;">
                    <span id="doc-selected-label"></span>
                    <button type="button" onclick="psClearDocLoan()" style="background:none;border:none;color:#6b7280;cursor:pointer;float:right;font-size:14px;line-height:1;">✕</button>
                </div>
                <input type="hidden" id="doc-loan-id-val">
            </div>
 
            <div class="bntm-form-group">
                <label>Document Type</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    <?php
                    $doc_types = [
                        'pawn_ticket'        => 'Pawn Ticket',
                        'renewal_notice'     => 'Renewal Notice',
                        'redemption_receipt' => 'Redemption Receipt',
                        'forfeiture_notice'  => 'Forfeiture Notice',
                        'payment_receipt'    => 'Payment Receipt',
                        'customer_statement' => 'Customer Statement',
                        'ticket_chain'       => 'Ticket Chain History',
                    ];
                    $first = true;
                    foreach ($doc_types as $dv => $dl): ?>
                    <button type="button" class="ps-doctype-btn <?php echo $first ? 'active' : ''; ?>"
                        data-type="<?php echo $dv; ?>" onclick="psSelectDocType('<?php echo $dv; ?>')"
                        style="padding:7px 10px;border:2px solid <?php echo $first ? '#1e40af' : '#e5e7eb'; ?>;border-radius:6px;background:<?php echo $first ? '#eff6ff' : '#fff'; ?>;color:<?php echo $first ? '#1e40af' : '#374151'; ?>;font-size:11px;font-weight:600;cursor:pointer;text-align:left;transition:all .1s;">
                        <?php echo $dl; ?>
                    </button>
                    <?php $first = false; endforeach; ?>
                </div>
                <input type="hidden" id="doc-type-hidden" value="pawn_ticket">
            </div>
 
            <button onclick="psGenerateSelectedDoc()" class="bntm-btn-primary" style="width:100%;">Preview &amp; Print</button>
        </div>
 
        <div class="bntm-form-section">
            <h4 style="margin:0 0 12px;font-size:14px;font-weight:700;">Recent Print Activity</h4>
            <?php if (empty($doc_logs)): ?>
            <p style="color:#9ca3af;font-size:13px;">No print activity yet.</p>
            <?php else: ?>
            <div style="max-height:420px;overflow-y:auto;">
                <?php foreach ($doc_logs as $log): ?>
                <div style="border-bottom:1px solid #f3f4f6;padding:8px 0;font-size:12px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-weight:600;text-transform:capitalize;"><?php echo str_replace('_', ' ', $log->document_type); ?></div>
                        <div style="color:#6b7280;"><?php echo $log->ticket_number ? esc_html($log->ticket_number) : 'N/A'; ?> &bull; <?php echo date('M d, Y H:i', strtotime($log->created_at)); ?></div>
                    </div>
                    <span style="font-size:11px;background:#f3f4f6;padding:2px 8px;border-radius:10px;"><?php echo $log->copies; ?> cop<?php echo $log->copies > 1 ? 'ies' : 'y'; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
 
    <script>(function(){
    let docSearchTimer = null;
    window.psDocSearchTicket = function(val) {
        clearTimeout(docSearchTimer);
        const res = document.getElementById('doc-loan-results');
        if (val.length < 2) { res.style.display='none'; return; }
        res.style.display='block';
        res.innerHTML='<div style="padding:10px;color:#9ca3af;font-size:13px;">Searching…</div>';
        docSearchTimer = setTimeout(() => {
            const fd = new FormData();
            fd.append('action','ps_search_loans');
            fd.append('query', val);
            fd.append('nonce', PS_NONCES.search);
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json => {
                if (!json.success || !json.data.loans?.length) {
                    res.innerHTML='<div style="padding:10px;color:#9ca3af;font-size:13px;">No tickets found.</div>'; return;
                }
                const sc={'active':'#059669','renewed':'#7c3aed','overdue':'#dc2626','redeemed':'#6b7280','forfeited':'#9ca3af'};
                res.innerHTML=json.data.loans.map(l=>`
                    <div style="padding:9px 12px;cursor:pointer;border-bottom:1px solid #f3f4f6;font-size:13px;display:flex;justify-content:space-between;align-items:center;"
                         onclick="psSelectDocLoan(${l.id},'${l.ticket_number}','${l.customer_name}','${l.status}')"
                         onmouseenter="this.style.background='#f0f9ff'" onmouseleave="this.style.background=''">
                        <div>
                            <span style="font-family:monospace;font-weight:700;">${l.ticket_number}</span>
                            <span style="color:#6b7280;"> — ${l.customer_name}</span>
                            <div style="font-size:11px;color:#9ca3af;">Principal: ₱${parseFloat(l.principal).toLocaleString('en-PH',{minimumFractionDigits:2})} &nbsp; Due: ${l.due_date}</div>
                        </div>
                        <span style="font-size:11px;font-weight:700;color:${sc[l.status]||'#374151'};text-transform:uppercase;padding:2px 7px;background:${sc[l.status]||'#374151'}18;border-radius:4px;">${l.status}</span>
                    </div>`).join('');
            });
        }, 300);
    };
    window.psSelectDocLoan = function(id,ticket,customer,status) {
        document.getElementById('doc-loan-id-val').value=id;
        document.getElementById('doc-loan-search').value='';
        document.getElementById('doc-loan-results').style.display='none';
        const sel=document.getElementById('doc-selected-loan');
        sel.style.display='block';
        document.getElementById('doc-selected-label').innerHTML=
            `<strong style="font-family:monospace;">${ticket}</strong> — ${customer} <span style="text-transform:uppercase;font-size:11px;color:#6b7280;">(${status})</span>`;
    };
    window.psClearDocLoan = function() {
        document.getElementById('doc-loan-id-val').value='';
        document.getElementById('doc-selected-loan').style.display='none';
        document.getElementById('doc-loan-search').value='';
    };
    window.psSelectDocType = function(type) {
        document.getElementById('doc-type-hidden').value=type;
        document.querySelectorAll('.ps-doctype-btn').forEach(b=>{
            const a=b.dataset.type===type;
            b.style.borderColor=a?'#1e40af':'#e5e7eb';
            b.style.background=a?'#eff6ff':'#fff';
            b.style.color=a?'#1e40af':'#374151';
            b.classList.toggle('active',a);
        });
    };
    window.psGenerateSelectedDoc = function() {
        const lid=document.getElementById('doc-loan-id-val').value;
        const dt=document.getElementById('doc-type-hidden').value;
        if (!lid) { alert('Please search and select a loan ticket first.'); return; }
        psShowPrintModal(lid, dt);
    };
    document.addEventListener('click', e=>{
        if (!e.target.closest('#doc-loan-search') && !e.target.closest('#doc-loan-results')) {
            const r=document.getElementById('doc-loan-results');
            if (r) r.style.display='none';
        }
    });
    })();</script>
    <?php return ob_get_clean();
}
 
 
// ============================================================
// REPORTS TAB UI
// ============================================================
function ps_reports_tab( $business_id ) {
    $rtype   = isset($_GET['report']) ? sanitize_text_field($_GET['report']) : 'summary';
    $rfrom   = isset($_GET['rfrom'])  ? sanitize_text_field($_GET['rfrom'])  : date('Y-m-01');
    $rto     = isset($_GET['rto'])    ? sanitize_text_field($_GET['rto'])    : date('Y-m-d');
    $rdate   = isset($_GET['rdate'])  ? sanitize_text_field($_GET['rdate'])  : date('Y-m-d');
    $rstatus = isset($_GET['rstatus'])? sanitize_text_field($_GET['rstatus']): 'all';
 
    $reports = [
        'summary'           => ['label' => 'Summary (All Statuses)', 'date' => 'range'],
        'list_loans'        => ['label' => 'List of Loans Granted',  'date' => 'range'],
        'list_payments'     => ['label' => 'List of Payments',       'date' => 'range'],
        'daily_summary'     => ['label' => 'Daily Summary',          'date' => 'single'],
        'tickets_by_status' => ['label' => 'Tickets by Status',      'date' => 'range'],
    ];
 
    $date_mode = $reports[$rtype]['date'] ?? 'range';
    $b         = ps_biz();
 
    ob_start(); ?>
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
        <input type="hidden" name="tab" value="reports">
 
        <select name="report" onchange="this.form.submit()"
                style="padding:7px 11px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;">
            <?php foreach ($reports as $val => $meta): ?>
            <option value="<?php echo $val; ?>" <?php selected($rtype,$val); ?>><?php echo esc_html($meta['label']); ?></option>
            <?php endforeach; ?>
        </select>
 
        <?php if ($date_mode === 'range'): ?>
            <input type="date" name="rfrom" value="<?php echo esc_attr($rfrom); ?>"
                   style="padding:7px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;">
            <span style="font-size:12px;color:#6b7280;">to</span>
            <input type="date" name="rto"   value="<?php echo esc_attr($rto);   ?>"
                   style="padding:7px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;">
        <?php elseif ($date_mode === 'single'): ?>
            <input type="date" name="rdate" value="<?php echo esc_attr($rdate); ?>"
                   style="padding:7px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;">
        <?php endif; ?>
 
        <?php if ($rtype === 'tickets_by_status'): ?>
        <select name="rstatus" style="padding:7px 11px;border:1px solid #d1d5db;border-radius:7px;font-size:13px;">
            <?php foreach (['all'=>'All Statuses','active'=>'Active','renewed'=>'Renewed','overdue'=>'Overdue','redeemed'=>'Redeemed','forfeited'=>'Forfeited'] as $sv => $sl): ?>
            <option value="<?php echo $sv; ?>" <?php selected($rstatus,$sv); ?>><?php echo $sl; ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
 
        <button type="submit" class="bntm-btn-primary" style="padding:7px 16px;">Generate</button>
        <button type="button" onclick="psReportOpenModal()" class="bntm-btn-secondary" style="padding:7px 14px;">Print</button>
    </form>
 
    <div id="ps-report-output" class="bntm-form-section" style="padding:16px;">
    <?php
    switch ($rtype) {
        case 'summary':           echo ps_rpt_summary($business_id, $rfrom, $rto, $b);                      break;
        case 'list_loans':        echo ps_rpt_list_loans($business_id, $rfrom, $rto, $b);                   break;
        case 'list_payments':     echo ps_rpt_list_payments($business_id, $rfrom, $rto, $b);                break;
        case 'daily_summary':     echo ps_rpt_daily_summary($business_id, $rdate, $b);                      break;
        case 'tickets_by_status': echo ps_rpt_tickets_by_status($business_id, $rfrom, $rto, $rstatus, $b); break;
        default:                  echo ps_rpt_summary($business_id, $rfrom, $rto, $b);
    }
    ?>
    </div>
 
    <script>
    var psReportParams = {
        rtype  : <?php echo json_encode($rtype);   ?>,
        rfrom  : <?php echo json_encode($rfrom);   ?>,
        rto    : <?php echo json_encode($rto);     ?>,
        rdate  : <?php echo json_encode($rdate);   ?>,
        rstatus: <?php echo json_encode($rstatus); ?>
    };
    window.psReportOpenModal = function() {
        var p = psReportParams;
        var modal   = document.getElementById('ps-print-modal');
        var preview = document.getElementById('ps-print-preview');
        document.getElementById('print-modal-title').textContent    = 'Report Print Preview';
        document.getElementById('print-modal-subtitle').textContent = p.rtype.replace(/_/g,' ');
        preview.innerHTML = '<div style="padding:40px;text-align:center;color:#9ca3af;">Generating…</div>';
        modal.style.display = 'flex';
        var fd = new FormData();
        fd.append('action',      'ps_generate_document');
        fd.append('doc_type',    p.rtype);
        fd.append('loan_id',     0);
        fd.append('report_date', p.rdate);
        fd.append('rfrom',       p.rfrom);
        fd.append('rto',         p.rto);
        fd.append('rstatus',     p.rstatus);
        fd.append('nonce',       PS_NONCES.doc);
        fetch(ajaxurl, {method:'POST', body:fd})
            .then(r=>r.json())
            .then(json => {
                if (json.success) {
                    preview.innerHTML     = json.data.html;
                    modal.dataset.loanId  = 0;
                    modal.dataset.docType = p.rtype;
                } else {
                    preview.innerHTML = '<div style="padding:20px;color:#dc2626;">'
                        + (json.data?.message || 'Error generating report') + '</div>';
                }
            });
    };
    </script>
    <?php return ob_get_clean();
}
 
/**
 * ① SUMMARY — all tickets grouped by status (overview counts + table).
 */
function ps_rpt_summary( int $business_id, string $from, string $to, array $b ): string {
    global $wpdb;
 
    $loans = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.id, l.ticket_number, l.root_ticket, l.status, l.transaction_type,
                l.principal, l.interest_rate, l.loan_date, l.due_date,
                l.redeemed_at, l.forfeited_at,
                CONCAT(c.last_name,', ',c.first_name) AS cname,
                c.contact_number,
                col.description AS col_desc,
                DATEDIFF(CURDATE(), l.due_date) AS days_past
         FROM   {$wpdb->prefix}ps_loans       l
         JOIN   {$wpdb->prefix}ps_customers   c   ON c.id  = l.customer_id
         JOIN   {$wpdb->prefix}ps_collaterals col ON col.id = l.collateral_id
         WHERE  l.business_id = %d AND l.loan_date BETWEEN %s AND %s
         ORDER  BY FIELD(l.status,'overdue','active','renewed','redeemed','forfeited'), l.due_date ASC",
        $business_id, $from, $to
    ) );
 
    $by_status = [];
    foreach ( $loans as $l ) $by_status[$l->status][] = $l;
 
    $order  = ['active','renewed','overdue','redeemed','forfeited'];
    $labels = ['active'=>'Active','renewed'=>'Renewed','overdue'=>'Overdue','redeemed'=>'Redeemed','forfeited'=>'Forfeited'];
    $dlabel = date('M d, Y', strtotime($from)) . ' — ' . date('M d, Y', strtotime($to));
 
    ob_start();
    echo ps_corp_header( $b );
    echo ps_doc_title( 'SUMMARY REPORT — TICKETS BY STATUS', "Date Covered: {$dlabel}" );
    echo ps_divider();
    echo ps_reg_table_style();
 
    /* summary strip */
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;font-size:9px;">';
    foreach ( $order as $st ) {
        if ( empty($by_status[$st]) ) continue;
        $cnt   = count($by_status[$st]);
        $total = array_sum(array_column($by_status[$st],'principal'));
        echo "<span style='border:1px solid #000;padding:2px 7px;'><strong>" . strtoupper($st) . "</strong>: {$cnt} &nbsp; P&nbsp;" . number_format($total,2) . "</span>";
    }
    echo '</div>';
 
    foreach ( $order as $st ) {
        if ( empty($by_status[$st]) ) continue;
        $grp       = $by_status[$st];
        $grp_total = array_sum(array_column($grp,'principal'));
        ?>
        <div style="margin-bottom:10px;">
        <div style="background:#000;color:#fff;padding:3px 6px;font-size:9px;font-weight:700;display:flex;justify-content:space-between;">
            <span><?php echo strtoupper($labels[$st]); ?> — <?php echo count($grp); ?> ticket(s)</span>
            <span>P <?php echo number_format($grp_total,2); ?></span>
        </div>
        <table class="reg">
            <thead><tr>
                <th>TICKET NO.</th><th>CUSTOMER</th><th>COLLATERAL</th>
                <th>LOAN DATE</th><th>DUE DATE</th><th class="r">PRINCIPAL</th><th class="r">RATE%</th>
                <?php if ($st==='overdue'):   echo '<th class="r">DAYS PAST</th>'; endif; ?>
                <?php if ($st==='redeemed'):  echo '<th>REDEEMED</th>';            endif; ?>
                <?php if ($st==='forfeited'): echo '<th>FORFEITED</th>';           endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($grp as $ln): ?>
            <tr>
                <td style="font-weight:700;"><?php echo esc_html($ln->ticket_number); ?></td>
                <td style="text-transform:uppercase;"><?php echo esc_html($ln->cname); ?></td>
                <td style="max-width:90px;"><?php echo esc_html($ln->col_desc); ?></td>
                <td><?php echo date('M d, Y',strtotime($ln->loan_date)); ?></td>
                <td><?php echo date('M d, Y',strtotime($ln->due_date)); ?></td>
                <td class="r" style="font-weight:700;"><?php echo number_format($ln->principal,2); ?></td>
                <td class="r"><?php echo $ln->interest_rate; ?></td>
                <?php if ($st==='overdue'):   echo '<td class="r" style="font-weight:700;">' . max(0,(int)$ln->days_past) . 'd</td>'; endif; ?>
                <?php if ($st==='redeemed'):  echo '<td>' . ($ln->redeemed_at  ? date('M d, Y',strtotime($ln->redeemed_at))  : '—') . '</td>'; endif; ?>
                <?php if ($st==='forfeited'): echo '<td>' . ($ln->forfeited_at ? date('M d, Y',strtotime($ln->forfeited_at)) : '—') . '</td>'; endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td colspan="5" style="text-align:right;font-weight:700;">Subtotal:</td>
                <td class="r">P <?php echo number_format($grp_total,2); ?></td>
                <td colspan="<?php echo in_array($st,['overdue','redeemed','forfeited'])?'2':'1'; ?>"></td>
            </tr></tfoot>
        </table></div>
        <?php
    }
 
    $grand = array_sum(array_column($loans,'principal'));
    echo '<div style="font-size:10px;font-weight:700;border-top:2px solid #000;border-bottom:2px solid #000;'
       . 'padding:3px 6px;display:flex;justify-content:space-between;">'
       . '<span>GRAND TOTAL — ' . count($loans) . ' ticket(s)</span>'
       . '<span>P ' . number_format($grand,2) . '</span></div>';
 
    echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']);
    echo ps_footer_line($b['footer']);
 
    return ps_wrap_page(ob_get_clean());
}
 
 
/**
 * ② LIST OF LOANS GRANTED
 */
function ps_rpt_list_loans( int $business_id, string $from, string $to, array $b ): string {
    global $wpdb;
 
    $loans = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.*,
                CONCAT(c.last_name,', ',c.first_name) AS cname,
                c.address,
                col.description   AS col_desc,
                col.karat, col.weight_grams,
                col.appraised_value AS col_appraised
         FROM   {$wpdb->prefix}ps_loans       l
         JOIN   {$wpdb->prefix}ps_customers   c   ON c.id  = l.customer_id
         JOIN   {$wpdb->prefix}ps_collaterals col ON col.id = l.collateral_id
         WHERE  l.business_id = %d AND l.loan_date BETWEEN %s AND %s
         ORDER  BY l.ticket_number ASC",
        $business_id, $from, $to
    ) );
 
    $sum_principal = array_sum(array_column($loans,'principal'));
    $sum_net       = $sum_principal - array_sum(array_column($loans,'service_fee'));
    $sum_appraised = 0;
    foreach ($loans as $l) $sum_appraised += (float)$l->col_appraised;
 
    $dlabel = date('M d, Y',strtotime($from)) . ' — ' . date('M d, Y',strtotime($to));
 
    ob_start();
    echo ps_corp_header($b);
    echo ps_doc_title('LIST OF LOANS GRANTED (INCLUSIVE DATES)', "Date Covered: {$dlabel}");
    echo ps_divider();
    echo ps_reg_table_style();
    ?>
    <table class="reg">
        <thead><tr>
            <th>PAWN TKT NO.</th><th>TYPE</th><th>NAME OF CUSTOMER</th><th>ADDRESS</th>
            <th class="r">AMOUNT OF LOAN</th><th class="r">NET PROCEEDS</th><th class="r">APPRAISED VALUE</th>
            <th>PAWNED ITEMS</th><th>DUE DATE</th><th>STATUS</th>
        </tr></thead>
        <tbody>
        <?php foreach ($loans as $ln):
            $net  = (float)$ln->principal - (float)$ln->service_fee;
            $desc = strtoupper(trim($ln->col_desc
                . ($ln->karat ? ' (' . $ln->karat . ')' : '')
                . ($ln->weight_grams > 0 ? ' ' . $ln->weight_grams . 'g' : '')));
        ?>
        <tr>
            <td style="font-weight:700;"><?php echo esc_html($ln->ticket_number); ?></td>
            <td style="text-transform:capitalize;"><?php echo str_replace('_',' ',$ln->transaction_type); ?></td>
            <td style="text-transform:uppercase;"><?php echo esc_html($ln->cname); ?></td>
            <td style="max-width:70px;font-size:8.5px;"><?php echo esc_html($ln->address); ?></td>
            <td class="r"><?php echo number_format($ln->principal,2); ?> /</td>
            <td class="r"><?php echo number_format($net,2); ?></td>
            <td class="r"><?php echo number_format($ln->col_appraised,2); ?></td>
            <td style="max-width:90px;font-size:8.5px;"><?php echo esc_html($desc); ?></td>
            <td style="white-space:nowrap;"><?php echo date('M d, Y',strtotime($ln->due_date)); ?></td>
            <td style="font-weight:700;text-transform:uppercase;"><?php echo $ln->status; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="4" style="text-align:right;font-weight:700;">TOTAL (<?php echo count($loans); ?> records): P</td>
            <td class="r"><?php echo number_format($sum_principal,2); ?></td>
            <td class="r"><?php echo number_format($sum_net,2); ?></td>
            <td class="r"><?php echo number_format($sum_appraised,2); ?></td>
            <td colspan="3"></td>
        </tr></tfoot>
    </table>
    <?php
    echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']);
    echo ps_footer_line($b['footer']);
    return ps_wrap_page(ob_get_clean());
}
 
 
/**
 * ③ LIST OF PAYMENTS RECEIVED
 */
function ps_rpt_list_payments( int $business_id, string $from, string $to, array $b ): string {
    global $wpdb;
 
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.*,
                l.ticket_number, l.root_ticket,
                CONCAT(c.last_name,', ',c.first_name) AS cname
         FROM   {$wpdb->prefix}ps_payments   p
         JOIN   {$wpdb->prefix}ps_loans      l ON l.id  = p.loan_id
         JOIN   {$wpdb->prefix}ps_customers  c ON c.id  = l.customer_id
         WHERE  p.business_id = %d AND DATE(p.created_at) BETWEEN %s AND %s
         ORDER  BY l.ticket_number ASC, p.created_at ASC",
        $business_id, $from, $to
    ) );
 
    $s_cash = array_sum(array_column($rows,'amount'));
    $s_int  = array_sum(array_column($rows,'interest_amount'));
    $s_pen  = array_sum(array_column($rows,'penalty_amount'));
    $s_prin = array_sum(array_column($rows,'principal_amount'));
 
    $dlabel = date('M d, Y',strtotime($from)) . ' — ' . date('M d, Y',strtotime($to));
 
    ob_start();
    echo ps_corp_header($b);
    echo ps_doc_title('LIST OF PAYMENTS (O.R.) RECEIVED', "Date Covered: {$dlabel}");
    echo ps_divider();
    echo ps_reg_table_style();
    ?>
    <table class="reg">
        <thead><tr>
            <th>DATE</th><th>TICKET NO.</th><th>NAME OF CUSTOMER</th><th>OR NO.</th><th>TYPE</th>
            <th class="r">CASH</th><th class="r">PRINCIPAL</th><th class="r">INTEREST</th>
            <th class="r">ADDT'L INT.</th><th>METHOD</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
        <tr>
            <td style="white-space:nowrap;"><?php echo date('M d, Y',strtotime($p->created_at)); ?></td>
            <td style="font-weight:700;"><?php echo esc_html($p->ticket_number); ?></td>
            <td style="text-transform:uppercase;"><?php echo esc_html($p->cname); ?></td>
            <td><?php echo esc_html($p->reference_number ?: '—'); ?></td>
            <td style="text-transform:capitalize;"><?php echo str_replace('_',' ',$p->payment_type); ?></td>
            <td class="r"><?php echo number_format($p->amount,2); ?> /</td>
            <td class="r"><?php echo $p->principal_amount > 0 ? number_format($p->principal_amount,2) : '—'; ?></td>
            <td class="r"><?php echo number_format($p->interest_amount,2); ?></td>
            <td class="r"><?php echo $p->penalty_amount > 0 ? number_format($p->penalty_amount,2) : '0.00'; ?> /</td>
            <td style="text-transform:capitalize;"><?php echo str_replace('_',' ',$p->payment_method); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="5" style="text-align:right;font-weight:700;">TOTAL (<?php echo count($rows); ?> records): P</td>
            <td class="r"><?php echo number_format($s_cash,2); ?></td>
            <td class="r"><?php echo number_format($s_prin,2); ?></td>
            <td class="r"><?php echo number_format($s_int,2); ?></td>
            <td class="r"><?php echo number_format($s_pen,2); ?></td>
            <td></td>
        </tr></tfoot>
    </table>
    <?php
    echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']);
    echo ps_footer_line($b['footer']);
    return ps_wrap_page(ob_get_clean());
}
 
 
/**
 * ④ DAILY SUMMARY — loans granted + payments received for one day.
 */
function ps_rpt_daily_summary( int $business_id, string $report_date, array $b ): string {
    global $wpdb;
    $lt   = $wpdb->prefix . 'ps_loans';
    $pt   = $wpdb->prefix . 'ps_payments';
    $ct   = $wpdb->prefix . 'ps_customers';
    $colt = $wpdb->prefix . 'ps_collaterals';
    $rd   = $report_date;
    $dlabel = date('F d, Y', strtotime($rd));
 
    $loans_today = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.*, CONCAT(c.last_name,', ',c.first_name) AS cname, c.address,
                col.description AS col_desc, col.karat, col.weight_grams
         FROM {$lt} l JOIN {$ct} c ON c.id=l.customer_id JOIN {$colt} col ON col.id=l.collateral_id
         WHERE l.business_id=%d AND DATE(l.loan_date)=%s AND l.transaction_type='new'
         ORDER BY l.ticket_number ASC",
        $business_id, $rd
    ) );
 
    $payments_today = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.*, l.ticket_number, l.root_ticket, CONCAT(c.last_name,', ',c.first_name) AS cname
         FROM {$pt} p JOIN {$lt} l ON l.id=p.loan_id JOIN {$ct} c ON c.id=l.customer_id
         WHERE p.business_id=%d AND DATE(p.created_at)=%s ORDER BY l.ticket_number ASC",
        $business_id, $rd
    ) );
 
    $sum_loan = array_sum(array_column($loans_today,    'principal'));
    $sum_net  = $sum_loan - array_sum(array_column($loans_today, 'service_fee'));
    $sum_appr = array_sum(array_map(fn($l) => (float)$l->appraised_value ?? 0, $loans_today));
    $s_cash   = array_sum(array_column($payments_today, 'amount'));
    $s_prin   = array_sum(array_column($payments_today, 'principal_amount'));
    $s_int    = array_sum(array_column($payments_today, 'interest_amount'));
    $s_add    = array_sum(array_column($payments_today, 'penalty_amount'));
 
    ob_start();
    echo ps_reg_table_style();
    echo ps_corp_header($b);
    echo ps_doc_title('DAILY SUMMARY', "Date Covered: {$dlabel}");
    echo ps_divider();
    ?>
 
    <!-- (A) LIST OF LOANS GRANTED -->
    <div style="font-size:8.5px;font-weight:700;text-transform:uppercase;margin:4px 0 3px;text-decoration:underline;">Loans Granted</div>
    <table class="reg" style="margin-bottom:10px;">
        <thead><tr>
            <th>PAWN TKT NO.</th><th>NAME OF CUSTOMER</th><th>ADDRESS</th>
            <th class="r">AMOUNT OF LOAN</th><th class="r">NET PROCEEDS</th><th class="r">APPRAISED VALUE</th>
            <th>PAWNED ITEMS</th>
        </tr></thead>
        <tbody>
        <?php foreach ($loans_today as $ln):
            $net = $ln->principal - $ln->service_fee;
            $pw  = strtoupper(trim($ln->col_desc
                . ($ln->karat ? ' (' . $ln->karat . ')' : '')
                . ($ln->weight_grams > 0 ? ' ' . $ln->weight_grams . ' GMS.' : '')));
        ?>
        <tr>
            <td style="font-weight:700;"><?php echo esc_html($ln->ticket_number); ?> /</td>
            <td style="text-transform:uppercase;"><?php echo esc_html(strtoupper($ln->cname)); ?></td>
            <td style="max-width:70px;font-size:8.5px;"><?php echo esc_html(strtoupper($ln->address)); ?></td>
            <td class="r"><?php echo number_format($ln->principal,2); ?> /</td>
            <td class="r"><?php echo number_format($net,2); ?></td>
            <td class="r"><?php echo number_format($ln->appraised_value ?? 0,2); ?></td>
            <td style="max-width:90px;font-size:8.5px;"><?php echo esc_html($pw); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="3" style="text-align:right;font-weight:700;">TOTAL: P</td>
            <td class="r"><?php echo number_format($sum_loan,2); ?></td>
            <td class="r"><?php echo number_format($sum_net,2); ?></td>
            <td class="r"><?php echo number_format($sum_appr,2); ?></td>
            <td></td>
        </tr></tfoot>
    </table>
 
    <!-- (B) LIST OF PAYMENTS RECEIVED -->
    <div style="font-size:8.5px;font-weight:700;text-transform:uppercase;margin:4px 0 3px;text-decoration:underline;">Payments Received</div>
    <table class="reg">
        <thead><tr>
            <th>NAME OF CUSTOMER</th><th>TICKET NUMBER</th><th>OR NUMBER</th>
            <th class="r">CASH</th><th class="r">PRINCIPAL</th><th class="r">INTEREST</th>
            <th class="r">INT. DISC.</th><th class="r">ADDT'L INT.</th><th class="r">AFF. OF LOSS</th>
        </tr></thead>
        <tbody>
        <?php foreach ($payments_today as $p): ?>
        <tr>
            <td style="text-transform:uppercase;"><?php echo esc_html(strtoupper($p->cname)); ?></td>
            <td style="font-weight:700;"><?php echo esc_html($p->ticket_number); ?> / <?php echo esc_html($p->reference_number ?: ''); ?></td>
            <td><?php echo esc_html($p->reference_number ?: '—'); ?></td>
            <td class="r"><?php echo number_format($p->amount,2); ?> /</td>
            <td class="r"><?php echo $p->principal_amount > 0 ? number_format($p->principal_amount,2) : '—'; ?> /</td>
            <td class="r"><?php echo number_format($p->interest_amount,2); ?> /</td>
            <td class="r">0.00</td>
            <td class="r"><?php echo $p->penalty_amount > 0 ? number_format($p->penalty_amount,2) : '0.00'; ?> /</td>
            <td class="r">0.00</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="3" style="text-align:right;font-weight:700;">TOTAL: P</td>
            <td class="r"><?php echo number_format($s_cash,2); ?></td>
            <td class="r"><?php echo number_format($s_prin,2); ?></td>
            <td class="r"><?php echo number_format($s_int,2); ?></td>
            <td class="r">0.00</td>
            <td class="r"><?php echo number_format($s_add,2); ?></td>
            <td class="r">0.00</td>
        </tr></tfoot>
    </table>
 
    <?php
    echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']);
    echo ps_footer_line($b['footer']);
    return ps_wrap_page(ob_get_clean());
}
 
 
/**
 * ⑤ TICKETS BY STATUS — filtered by a specific status (or all).
 *    $filter_status = 'active' | 'renewed' | 'overdue' | 'redeemed' | 'forfeited' | 'all'
 */
function ps_rpt_tickets_by_status( int $business_id, string $from, string $to, string $filter_status, array $b ): string {
    global $wpdb;
 
    $status_clause = ($filter_status !== 'all')
        ? $wpdb->prepare("AND l.status = %s", $filter_status)
        : '';
 
    $loans = $wpdb->get_results( $wpdb->prepare(
        "SELECT l.id, l.ticket_number, l.root_ticket, l.status,
                l.transaction_type, l.principal, l.interest_rate,
                l.loan_date, l.due_date, l.redeemed_at, l.forfeited_at,
                CONCAT(c.last_name,', ',c.first_name) AS cname,
                c.contact_number,
                col.description AS col_desc, col.category,
                DATEDIFF(CURDATE(), l.due_date) AS days_past
         FROM   {$wpdb->prefix}ps_loans       l
         JOIN   {$wpdb->prefix}ps_customers   c   ON c.id  = l.customer_id
         JOIN   {$wpdb->prefix}ps_collaterals col ON col.id = l.collateral_id
         WHERE  l.business_id = %d AND l.loan_date BETWEEN %s AND %s
                {$status_clause}
         ORDER  BY FIELD(l.status,'overdue','active','renewed','redeemed','forfeited'), l.due_date ASC",
        $business_id, $from, $to
    ) );
 
    $by_status = [];
    foreach ($loans as $l) $by_status[$l->status][] = $l;
 
    $order  = ['active','renewed','overdue','redeemed','forfeited'];
    $labels = ['active'=>'Active','renewed'=>'Renewed','overdue'=>'Overdue','redeemed'=>'Redeemed','forfeited'=>'Forfeited'];
 
    $dlabel  = date('M d, Y',strtotime($from)) . ' — ' . date('M d, Y',strtotime($to));
    $st_label = $filter_status === 'all' ? 'ALL STATUSES' : strtoupper($filter_status);
 
    ob_start();
    echo ps_corp_header($b);
    echo ps_doc_title('TICKETS BY STATUS — ' . $st_label, "Loan Date Range: {$dlabel}");
    echo ps_divider();
    echo ps_reg_table_style();
 
    /* summary strip */
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;font-size:9px;">';
    foreach ($order as $st) {
        if (empty($by_status[$st])) continue;
        $cnt   = count($by_status[$st]);
        $total = array_sum(array_column($by_status[$st],'principal'));
        echo "<span style='border:1px solid #000;padding:2px 7px;'><strong>" . strtoupper($st) . "</strong>: {$cnt} &nbsp; P&nbsp;" . number_format($total,2) . "</span>";
    }
    echo '</div>';
 
    foreach ($order as $st) {
        if (empty($by_status[$st])) continue;
        $grp       = $by_status[$st];
        $grp_total = array_sum(array_column($grp,'principal'));
        $extra_col = in_array($st,['overdue','redeemed','forfeited']);
        ?>
        <div style="margin-bottom:10px;">
        <div style="background:#000;color:#fff;padding:3px 6px;font-size:9px;font-weight:700;display:flex;justify-content:space-between;">
            <span><?php echo strtoupper($labels[$st]); ?> — <?php echo count($grp); ?> ticket(s)</span>
            <span>P <?php echo number_format($grp_total,2); ?></span>
        </div>
        <table class="reg">
            <thead><tr>
                <th>TICKET NO.</th><th>ROOT</th><th>CUSTOMER</th><th>CONTACT</th><th>COLLATERAL</th>
                <th>LOAN DATE</th><th>DUE DATE</th><th class="r">PRINCIPAL</th><th class="r">RATE%</th>
                <?php if ($st==='overdue'):   echo '<th class="r">DAYS PAST</th>'; endif; ?>
                <?php if ($st==='redeemed'):  echo '<th>REDEEMED</th>';            endif; ?>
                <?php if ($st==='forfeited'): echo '<th>FORFEITED</th>';           endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($grp as $ln): ?>
            <tr>
                <td style="font-weight:700;"><?php echo esc_html($ln->ticket_number); ?></td>
                <td style="color:#555;"><?php echo $ln->root_ticket !== $ln->ticket_number ? esc_html($ln->root_ticket) : '—'; ?></td>
                <td style="text-transform:uppercase;"><?php echo esc_html($ln->cname); ?></td>
                <td><?php echo esc_html($ln->contact_number); ?></td>
                <td style="max-width:80px;"><?php echo esc_html($ln->col_desc); ?></td>
                <td style="white-space:nowrap;"><?php echo date('M d, Y',strtotime($ln->loan_date)); ?></td>
                <td style="white-space:nowrap;"><?php echo date('M d, Y',strtotime($ln->due_date)); ?></td>
                <td class="r" style="font-weight:700;"><?php echo number_format($ln->principal,2); ?></td>
                <td class="r"><?php echo $ln->interest_rate; ?></td>
                <?php if ($st==='overdue'):   echo '<td class="r" style="font-weight:700;">' . max(0,(int)$ln->days_past) . 'd</td>'; endif; ?>
                <?php if ($st==='redeemed'):  echo '<td>' . ($ln->redeemed_at  ? date('M d, Y',strtotime($ln->redeemed_at))  : '—') . '</td>'; endif; ?>
                <?php if ($st==='forfeited'): echo '<td>' . ($ln->forfeited_at ? date('M d, Y',strtotime($ln->forfeited_at)) : '—') . '</td>'; endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td colspan="7" style="text-align:right;font-weight:700;">Subtotal:</td>
                <td class="r">P <?php echo number_format($grp_total,2); ?></td>
                <td colspan="<?php echo $extra_col ? '2' : '1'; ?>"></td>
            </tr></tfoot>
        </table></div>
        <?php
    }
 
    $grand = array_sum(array_column($loans,'principal'));
    echo '<div style="font-size:10px;font-weight:700;border-top:2px solid #000;border-bottom:2px solid #000;'
       . 'padding:3px 6px;display:flex;justify-content:space-between;">'
       . '<span>GRAND TOTAL — ' . count($loans) . ' ticket(s)</span>'
       . '<span>P ' . number_format($grand,2) . '</span></div>';
 
    echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']);
    echo ps_footer_line($b['footer']);
    return ps_wrap_page(ob_get_clean());
}
 

// ============================================================
// TAB: SETTINGS
// ============================================================

function ps_settings_tab($business_id) {
    $pm = json_decode(bntm_get_setting('ps_payment_methods', '[]'), true);
    if (!is_array($pm)) $pm = [];
    ob_start();
    ?>
    <h3 style="margin:0 0 18px;">Pawnshop Settings</h3>
    <div id="settings-message"></div>
    <form id="ps-settings-form">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ps_settings_nonce'); ?>">
        <div class="bntm-form-section"><h4 style="margin:0 0 14px;font-size:14px;font-weight:700;">Business Information</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="bntm-form-group" style="grid-column:1/-1;"><label>Business Name</label><input type="text" name="ps_business_name" value="<?php echo esc_attr(bntm_get_setting('ps_business_name','')); ?>" placeholder="Your Pawnshop Name"></div>
                <div class="bntm-form-group" style="grid-column:1/-1;"><label>Business Address</label><textarea name="ps_business_address" rows="2" placeholder="Complete business address"><?php echo esc_textarea(bntm_get_setting('ps_business_address','')); ?></textarea></div>
                <div class="bntm-form-group"><label>Contact Number</label><input type="text" name="ps_business_contact" value="<?php echo esc_attr(bntm_get_setting('ps_business_contact','')); ?>"></div>
                <div class="bntm-form-group"><label>DTI/SEC Registration No.</label><input type="text" name="ps_business_license" value="<?php echo esc_attr(bntm_get_setting('ps_business_license','')); ?>"></div>
                <div class="bntm-form-group"><label>BSP Certificate of Registration</label><input type="text" name="ps_business_bsp" value="<?php echo esc_attr(bntm_get_setting('ps_business_bsp','')); ?>"></div>
                <div class="bntm-form-group" style="grid-column:1/-1;"><label>Document Footer Text</label><textarea name="ps_doc_footer" rows="2"><?php echo esc_textarea(bntm_get_setting('ps_doc_footer','This document is computer-generated and is valid without signature.')); ?></textarea></div>
            </div>
        </div>
        <div class="bntm-form-section"><h4 style="margin:0 0 14px;font-size:14px;font-weight:700;">Ticket Numbering</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="bntm-form-group">
                    <label>Ticket Prefix</label>
                    <input type="text" name="ps_ticket_prefix" value="<?php echo esc_attr(bntm_get_setting('ps_ticket_prefix','PT')); ?>" placeholder="PT" maxlength="6" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase();psPreviewTicket()">
                    <div style="font-size:11px;color:#6b7280;margin-top:3px;">Short prefix on ticket numbers (e.g. PT, TKT, PNS)</div>
                </div>
                <div class="bntm-form-group">
                    <label>Sequence Mode</label>
                    <select name="ps_ticket_mode" onchange="psPreviewTicket()">
                        <option value="sequential" <?php echo bntm_get_setting('ps_ticket_mode','sequential')==='sequential'?'selected':''; ?>>Sequential (monthly reset)</option>
                        <option value="sequential_global" <?php echo bntm_get_setting('ps_ticket_mode','')==='sequential_global'?'selected':''; ?>>Sequential (never reset)</option>
                        <option value="random" <?php echo bntm_get_setting('ps_ticket_mode','')==='random'?'selected':''; ?>>Random (6-char code)</option>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Starting Sequence No.</label>
                    <input type="number" name="ps_ticket_start" value="<?php echo esc_attr(bntm_get_setting('ps_ticket_start','1')); ?>" min="1" placeholder="1" oninput="psPreviewTicket()">
                    <div style="font-size:11px;color:#6b7280;margin-top:3px;">Next sequential number starts here (sequential modes only)</div>
                </div>
            </div>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;font-size:12px;margin-top:6px;display:flex;align-items:center;gap:12px;">
                <span>Preview: <strong id="ticket-preview-display" style="font-family:monospace;font-size:13px;"><?php
                    $pfx=$px=strtoupper(trim(bntm_get_setting('ps_ticket_prefix','PT')))?:'PT';
                    $md=bntm_get_setting('ps_ticket_mode','sequential');
                    $st=(int)bntm_get_setting('ps_ticket_start','1');
                    if($md==='random') echo esc_html($pfx.'-XXXXXX');
                    elseif($md==='sequential_global') echo esc_html(sprintf('%s-%05d',$pfx,$st));
                    else echo esc_html(sprintf('%s-%s-%04d',$pfx,date('Ym'),$st));
                ?></strong></span>
                <button type="button" onclick="psPreviewTicket()" style="background:#1e40af;color:#fff;border:none;border-radius:4px;padding:3px 10px;font-size:11px;cursor:pointer;">Refresh</button>
            </div>
        </div>

        <div class="bntm-form-section"><h4 style="margin:0 0 14px;font-size:14px;font-weight:700;">Lost Ticket Settings</h4>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="bntm-form-group">
                    <label>Affidavit of Loss Service Fee (₱)</label>
                    <input type="number" name="ps_lost_ticket_fee" step="0.01" value="<?php echo esc_attr(bntm_get_setting('ps_lost_ticket_fee','100.00')); ?>" placeholder="100.00">
                    <div style="font-size:11px;color:#6b7280;margin-top:3px;">Added to total when a ticket is reported lost</div>
                </div>
                <div class="bntm-form-group">
                    <label>Duplicate Ticket Watermark Text</label>
                    <input type="text" name="ps_lost_ticket_notice" value="<?php echo esc_attr(bntm_get_setting('ps_lost_ticket_notice','DUPLICATE — Original Ticket Reported Lost')); ?>">
                </div>
            </div>
        </div>

        <div class="bntm-form-section"><h4 style="margin:0 0 14px;font-size:14px;font-weight:700;">Interest &amp; Loan Settings</h4>
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;">
                <strong>Interest Computation Rule:</strong> Interest accrues daily from loan date (principal × rate/100/30 × days). Grace period applies after due date before penalty interest kicks in.
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                <div class="bntm-form-group"><label>Default Interest Rate (%/month)</label><input type="number" name="ps_interest_rate" step=".01" value="<?php echo esc_attr(bntm_get_setting('ps_interest_rate','3.00')); ?>"></div>
                <div class="bntm-form-group"><label>Penalty Rate (%/month)</label><input type="number" name="ps_penalty_rate" step=".01" value="<?php echo esc_attr(bntm_get_setting('ps_penalty_rate','1.00')); ?>"></div>
                <div class="bntm-form-group"><label>Default Service Fee (₱)</label><input type="number" name="ps_service_fee" step=".01" value="<?php echo esc_attr(bntm_get_setting('ps_service_fee','0.00')); ?>"></div>
                <div class="bntm-form-group">
                    <label>Grace Period (days after due date)</label>
                    <input type="number" name="ps_grace_period" value="<?php echo esc_attr(bntm_get_setting('ps_grace_period','0')); ?>" placeholder="0">
                    <div style="font-size:11px;color:#6b7280;margin-top:3px;">No penalty interest charged during grace period</div>
                </div>
                <div class="bntm-form-group"><label>Auto-Forfeiture After (days past due)</label><input type="number" name="ps_auto_forfeit_days" value="<?php echo esc_attr(bntm_get_setting('ps_auto_forfeit_days','30')); ?>"></div>
                <div class="bntm-form-group"><label>LTV Ratio (% of appraised value)</label><input type="number" name="ps_ltv_ratio" step=".01" value="<?php echo esc_attr(bntm_get_setting('ps_ltv_ratio','80')); ?>"></div>
            </div>
        </div>
        <div style="padding:0 0 14px;"><button type="submit" class="bntm-btn-primary" style="padding:10px 28px;">Save All Settings</button></div>
    </form>

    <div class="bntm-form-section"><h4 style="margin:0 0 14px;font-size:14px;font-weight:700;">Payment Methods</h4>
        <div id="ps-payment-methods-list">
        <?php if (empty($pm)): ?><p style="color:#9ca3af;font-size:13px;">No payment methods configured.</p>
        <?php else: foreach ($pm as $i => $m): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:8px;">
            <div><div style="font-weight:600;"><?php echo esc_html($m['name']); ?> <span style="font-size:11px;color:#6b7280;text-transform:capitalize;">(<?php echo $m['type']; ?>)</span></div><?php if ($m['account_name']): ?><div style="font-size:12px;color:#6b7280;"><?php echo esc_html($m['account_name'].' — '.$m['account_number']); ?></div><?php endif; ?></div>
            <button class="ps-action-btn ps-btn-forfeit" onclick="psRemovePaymentMethod(<?php echo $i; ?>)">Remove</button>
        </div>
        <?php endforeach; endif; ?>
        </div>
        <div style="border-top:1px solid #f3f4f6;margin-top:14px;padding-top:14px;">
            <div style="font-size:13px;font-weight:600;margin-bottom:10px;">Add Payment Method</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="bntm-form-group"><label>Type</label><select id="pm-type"><option value="cash">Cash</option><option value="digital">Digital Wallet</option><option value="bank">Bank Transfer</option><option value="other">Other</option></select></div>
                <div class="bntm-form-group"><label>Display Name</label><input type="text" id="pm-name" placeholder="e.g. GCash, BDO"></div>
                <div class="bntm-form-group"><label>Account Name</label><input type="text" id="pm-acct-name" placeholder="Account holder name"></div>
                <div class="bntm-form-group"><label>Account Number</label><input type="text" id="pm-acct-no" placeholder="Account or mobile number"></div>
            </div>
            <button onclick="psAddPaymentMethod()" class="bntm-btn-primary">Add Payment Method</button>
        </div>
    </div>
    <div class="bntm-form-section"><h4 style="margin:0 0 14px;font-size:14px;font-weight:700;">Ticket Number Maintenance</h4>
        <div style="font-size:13px;color:#6b7280;margin-bottom:14px;">Manually adjust the next ticket sequence number. Use this if you need to skip a range, reset after importing, or correct a numbering gap.</div>
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="bntm-form-group" style="margin:0;min-width:180px;">
                <label style="font-size:12px;">Set Next Sequence To</label>
                <input type="number" id="maint-seq-value" min="1" placeholder="e.g. 500" style="margin-top:4px;">
            </div>
            <button type="button" onclick="psMaintSetSequence()" style="background:#1e40af;color:#fff;border:none;border-radius:6px;padding:8px 18px;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:0;">Apply Sequence</button>
            <div id="maint-seq-msg" style="font-size:12px;padding:4px 0;"></div>
        </div>
        <div style="margin-top:12px;padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:12px;">
            <strong>Current next ticket preview:</strong> <span id="maint-ticket-preview" style="font-family:monospace;font-weight:700;"><?php
                $pfx=strtoupper(trim(bntm_get_setting('ps_ticket_prefix','PT')))?:'PT';
                $md=bntm_get_setting('ps_ticket_mode','sequential');
                $st=(int)bntm_get_setting('ps_ticket_start','1');
                if($md==='random') echo esc_html($pfx.'-XXXXXX (random)');
                elseif($md==='sequential_global') echo esc_html(sprintf('%s-%05d',$pfx,$st));
                else echo esc_html(sprintf('%s-%s-%04d',$pfx,date('Ym'),$st));
            ?></span>
        </div>
    </div>

    <script>(function(){
    document.getElementById('ps-settings-form')?.addEventListener('submit',function(e){
        e.preventDefault();
        const btn=this.querySelector('button[type="submit"]');btn.disabled=true;btn.textContent='Saving...';
        const fd=new FormData(this);fd.append('action','ps_save_settings');
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
            document.getElementById('settings-message').innerHTML='<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+json.data.message+'</div>';
            btn.disabled=false;btn.textContent='Save All Settings';
            setTimeout(()=>document.getElementById('settings-message').innerHTML='',4000);
        });
    });
    window.psPreviewTicket=function(){
        const prefix=(document.querySelector('[name="ps_ticket_prefix"]')?.value||'PT').toUpperCase();
        const mode=document.querySelector('[name="ps_ticket_mode"]')?.value||'sequential';
        const start=parseInt(document.querySelector('[name="ps_ticket_start"]')?.value)||1;
        let preview='';
        if(mode==='random') preview=prefix+'-XXXXXX';
        else if(mode==='sequential_global') preview=prefix+'-'+String(start).padStart(5,'0');
        else { const d=new Date(); const ym=d.getFullYear()+String(d.getMonth()+1).padStart(2,'0'); preview=prefix+'-'+ym+'-'+String(start).padStart(4,'0'); }
        const el=document.getElementById('ticket-preview-display');
        if(el) el.textContent=preview;
    };
    window.psMaintSetSequence=function(){
        const val=parseInt(document.getElementById('maint-seq-value').value);
        if(!val||val<1){document.getElementById('maint-seq-msg').innerHTML='<span style="color:#dc2626;">Enter a valid number.</span>';return;}
        const fd=new FormData();fd.append('action','ps_save_settings');fd.append('nonce','<?php echo wp_create_nonce('ps_settings_nonce'); ?>');
        fd.append('ps_ticket_start',val);
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{
            document.getElementById('maint-seq-msg').innerHTML=json.success?'<span style="color:#059669;">Sequence updated!</span>':'<span style="color:#dc2626;">Failed.</span>';
            setTimeout(()=>document.getElementById('maint-seq-msg').innerHTML='',3000);
        });
    };
    window.psAddPaymentMethod=function(){
        const fd=new FormData();fd.append('action','ps_add_payment_method');fd.append('nonce','<?php echo wp_create_nonce('ps_settings_nonce'); ?>');
        fd.append('payment_type',document.getElementById('pm-type').value);fd.append('payment_name',document.getElementById('pm-name').value);
        fd.append('account_name',document.getElementById('pm-acct-name').value);fd.append('account_number',document.getElementById('pm-acct-no').value);
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{alert(json.data.message);if(json.success)location.reload();});
    };
    window.psRemovePaymentMethod=function(idx){
        if(!confirm('Remove this payment method?'))return;
        const fd=new FormData();fd.append('action','ps_remove_payment_method');fd.append('nonce','<?php echo wp_create_nonce('ps_settings_nonce'); ?>');fd.append('index',idx);
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(json=>{if(json.success)location.reload();else alert(json.data.message);});
    };
    })();</script>
    <?php
    return ob_get_clean();
}


// ============================================================
// AJAX: FORFEIT LOAN (complete)
// ============================================================

function bntm_ajax_ps_forfeit_loan() {
    check_ajax_referer('ps_forfeit_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_loans WHERE id=%d AND business_id=%d AND status IN ('active','renewed','overdue')",
        $loan_id, $business_id
    ));
    if (!$loan) { wp_send_json_error(['message'=>'Loan not found or cannot be forfeited.']); return; }

    $wpdb->update($wpdb->prefix.'ps_loans',
        ['status'=>'forfeited','forfeited_at'=>current_time('mysql')],
        ['id'=>$loan_id]
    );
    $wpdb->update($wpdb->prefix.'ps_collaterals',
        ['status'=>'forfeited'],
        ['loan_id'=>$loan_id,'business_id'=>$business_id]
    );

    ps_log_ticket_event([
        'business_id'     => $business_id,
        'root_ticket'     => $loan->root_ticket,
        'loan_id'         => $loan_id,
        'ticket_number'   => $loan->ticket_number,
        'event_type'      => 'forfeited',
        'principal_before'=> $loan->principal,
        'principal_after' => 0,
        'interest_paid'   => 0,
        'amount_paid'     => 0,
        'due_date'        => $loan->due_date,
        'notes'           => 'Loan forfeited. Principal: ₱'.number_format($loan->principal, 2),
    ]);

    wp_send_json_success(['message'=>'Loan #'.$loan->ticket_number.' has been forfeited.','loan_id'=>$loan_id]);
}

// ============================================================
// AJAX: COMPUTE INTEREST (live breakdown)
// ============================================================

function bntm_ajax_ps_compute_interest() {
    check_ajax_referer('ps_payment_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS customer_name
         FROM {$wpdb->prefix}ps_loans l
         JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         WHERE l.id=%d AND l.business_id=%d",
        $loan_id, $business_id
    ));
    if (!$loan) { wp_send_json_error(['message'=>'Loan not found.']); return; }

    $bd = ps_compute_interest_breakdown($loan);

    wp_send_json_success([
        'loan_id'         => $loan_id,
        'ticket_number'   => $loan->ticket_number,
        'root_ticket'     => $loan->root_ticket,
        'customer_name'   => $loan->customer_name,
        'principal'       => (float)$loan->principal,
        'interest_rate'   => (float)$loan->interest_rate,
        'penalty_rate'    => (float)$loan->penalty_rate,
        'loan_date'       => $loan->loan_date,
        'due_date'        => $loan->due_date,
        'service_fee'     => (float)$loan->service_fee,
        'is_overdue'      => $bd['days_past_due'] > 0,
        'days_elapsed'    => $bd['days_elapsed'],
        'days_past_due'   => $bd['days_past_due'],
        'effective_overdue'=> $bd['effective_overdue'],
        'grace_days'      => $bd['grace_days'],
        'regular_interest'=> $bd['regular_interest'],
        'penalty_interest'=> $bd['penalty_interest'],
        'carried_interest'=> $bd['carried_interest'],
        'total_interest'  => $bd['total_interest'],
        'interest'        => $bd['total_interest'],
        'penalty'         => $bd['penalty_interest'],
        'total_due'       => $bd['total_due'],
    ]);
}

// ============================================================
// AJAX: GET LOAN DETAIL (modal)
// ============================================================

function bntm_ajax_ps_get_loan_detail() {
    check_ajax_referer('ps_loan_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT l.*,CONCAT(c.last_name,', ',c.first_name,' ',COALESCE(c.middle_name,'')) AS customer_name,
                c.contact_number,c.address,c.id_type,c.id_number,c.photo_path,c.customer_flag,
                col.description AS collateral_desc,col.category AS collateral_cat,col.brand,col.model,
                col.item_condition,col.appraised_value,col.karat,col.weight_grams,col.serial_number
         FROM {$wpdb->prefix}ps_loans l
         JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         JOIN {$wpdb->prefix}ps_collaterals col ON col.id=l.collateral_id
         WHERE l.id=%d AND l.business_id=%d",
        $loan_id, $business_id
    ));
    if (!$loan) { wp_send_json_error(['message'=>'Loan not found.']); return; }

    $bd = ps_compute_interest_breakdown($loan);

    // Ticket chain
    $chain = $wpdb->get_results($wpdb->prepare(
        "SELECT id,ticket_number,transaction_type,loan_date,due_date,principal,status,accrued_interest_carried
         FROM {$wpdb->prefix}ps_loans
         WHERE root_ticket=%s AND business_id=%d
         ORDER BY id ASC",
        $loan->root_ticket, $business_id
    ));

    // Payments on this loan
    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_payments WHERE loan_id=%d ORDER BY created_at DESC LIMIT 20",
        $loan_id
    ));

    ob_start();
    $status_class = 'ps-status-'.$loan->status;
    $flag_class   = 'ps-flag-'.($loan->customer_flag ?? 'normal');
    ?>
    <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <?php if ($loan->photo_path): ?>
        <div style="position:relative;width:72px;height:72px;flex-shrink:0;">
            <img src="<?php echo esc_url(BNTM_PS_PHOTO_URL.$loan->photo_path); ?>"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #e0e7ff;display:block;"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
            <div style="display:none;width:72px;height:72px;border-radius:50%;background:#1e40af;align-items:center;justify-content:center;color:#fff;font-size:26px;font-weight:800;position:absolute;top:0;left:0;"><?php echo strtoupper(substr($loan->customer_name,0,1)); ?></div>
        </div>
        <?php else: ?>
        <div style="width:72px;height:72px;border-radius:50%;background:#1e40af;display:flex;align-items:center;justify-content:center;color:#fff;font-size:26px;font-weight:800;flex-shrink:0;"><?php echo strtoupper(substr($loan->customer_name,0,1)); ?></div>
        <?php endif; ?>
        <div style="flex:1;min-width:200px;">
            <div style="font-size:17px;font-weight:800;margin-bottom:3px;"><?php echo esc_html(trim($loan->customer_name)); ?></div>
            <div style="font-size:12px;color:#6b7280;margin-bottom:4px;"><?php echo esc_html($loan->contact_number); ?> &bull; <?php echo esc_html($loan->id_type.': '.$loan->id_number); ?></div>
            <span class="ps-status-badge <?php echo $flag_class; ?>"><?php echo ucfirst($loan->customer_flag); ?></span>
        </div>
        <div style="text-align:right;">
            <div style="font-family:monospace;font-size:18px;font-weight:800;color:#1e40af;"><?php echo esc_html($loan->ticket_number); ?></div>
            <?php if ($loan->root_ticket !== $loan->ticket_number): ?>
            <div style="font-size:11px;color:#7c3aed;">Root: <?php echo esc_html($loan->root_ticket); ?></div>
            <?php endif; ?>
            <span class="ps-status-badge <?php echo $status_class; ?>" style="margin-top:4px;"><?php echo ucfirst($loan->status); ?></span>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
        <div class="bntm-form-section" style="margin:0;padding:14px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:10px;">Collateral</div>
            <div style="font-weight:700;font-size:14px;margin-bottom:4px;"><?php echo esc_html($loan->collateral_desc); ?></div>
            <div style="font-size:12px;color:#6b7280;">
                <?php echo ucfirst($loan->collateral_cat); ?>
                <?php if ($loan->brand): ?> &bull; <?php echo esc_html($loan->brand); ?><?php endif; ?>
                <?php if ($loan->model): ?> <?php echo esc_html($loan->model); ?><?php endif; ?>
            </div>
            <?php if ($loan->karat || $loan->weight_grams > 0): ?>
            <div style="font-size:12px;color:#6b7280;"><?php echo $loan->karat; ?> <?php echo $loan->weight_grams > 0 ? $loan->weight_grams.'g' : ''; ?></div>
            <?php endif; ?>
            <div style="font-size:12px;margin-top:6px;">Condition: <strong style="text-transform:capitalize;"><?php echo $loan->item_condition; ?></strong></div>
            <div style="font-size:12px;">Appraised: <strong>₱<?php echo number_format($loan->appraised_value,2); ?></strong></div>
        </div>
        <div class="bntm-form-section" style="margin:0;padding:14px;">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:10px;">Loan Details</div>
            <table style="width:100%;font-size:12px;">
                <tr><td style="color:#6b7280;">Principal</td><td style="text-align:right;font-weight:700;">₱<?php echo number_format($loan->principal,2); ?></td></tr>
                <tr><td style="color:#6b7280;">Interest Rate</td><td style="text-align:right;"><?php echo $loan->interest_rate; ?>%/mo</td></tr>
                <tr><td style="color:#6b7280;">Loan Date</td><td style="text-align:right;"><?php echo date('M d, Y',strtotime($loan->loan_date)); ?></td></tr>
                <tr><td style="color:#6b7280;">Due Date</td><td style="text-align:right;<?php echo strtotime($loan->due_date)<strtotime('today')&&!in_array($loan->status,['redeemed','forfeited'])?'color:#dc2626;font-weight:700;':''; ?>"><?php echo date('M d, Y',strtotime($loan->due_date)); ?></td></tr>
                <tr><td style="color:#6b7280;">Days Elapsed</td><td style="text-align:right;"><?php echo $bd['days_elapsed']; ?> days</td></tr>
                <?php if ($bd['days_past_due'] > 0 && !in_array($loan->status,['redeemed','forfeited'])): ?>
                <tr><td style="color:#dc2626;">Days Overdue</td><td style="text-align:right;color:#dc2626;font-weight:700;"><?php echo $bd['days_past_due']; ?>d <?php echo $bd['effective_overdue']<$bd['days_past_due']?'(grace applies)':''; ?></td></tr>
                <?php endif; ?>
                <?php if (!in_array($loan->status,['redeemed','forfeited'])): ?>
                <tr style="border-top:1px solid #bfdbfe;"><td style="font-weight:700;padding-top:6px;">Interest Due</td><td style="text-align:right;font-weight:800;padding-top:6px;color:#1e40af;">₱<?php echo number_format($bd['total_interest'],2); ?></td></tr>
                <tr><td style="font-weight:700;padding-bottom:4px;">Total to Redeem</td><td style="text-align:right;font-weight:800;font-size:15px;color:#059669;padding-bottom:4px;">₱<?php echo number_format($bd['total_due'],2); ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
        <?php if (in_array($loan->status,['active','overdue'])): ?>
        <button class="ps-action-btn ps-btn-renew" style="padding:8px 16px;font-size:13px;" onclick="document.getElementById('ps-loan-detail-modal').style.display='none';psOpenRenewModal(<?php echo $loan_id; ?>)">Renew / Pay Interest</button>
        <button class="ps-action-btn ps-btn-redeem" style="padding:8px 16px;font-size:13px;" onclick="document.getElementById('ps-loan-detail-modal').style.display='none';psOpenRedeemModal(<?php echo $loan_id; ?>)">Redeem</button>
        <button class="ps-action-btn ps-btn-forfeit" style="padding:8px 16px;font-size:13px;" onclick="document.getElementById('ps-loan-detail-modal').style.display='none';psConfirmForfeit(<?php echo $loan_id; ?>,'<?php echo esc_js($loan->ticket_number); ?>')">Forfeit</button>
        <?php elseif ($loan->status === 'renewed'): ?>
        <div style="background:#f0f9ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 16px;font-size:13px;color:#1e40af;display:flex;align-items:center;gap:8px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            This ticket has been renewed. Actions are available on the latest ticket in the chain.
        </div>
        <?php elseif (in_array($loan->status,['redeemed','forfeited'])): ?>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 16px;font-size:13px;color:#6b7280;display:flex;align-items:center;gap:8px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            This ticket is <?php echo ucfirst($loan->status); ?>. No further actions available.
        </div>
        <?php endif; ?>
        <button class="ps-action-btn ps-btn-print" style="padding:8px 16px;font-size:13px;" onclick="psShowPrintModal(<?php echo $loan_id; ?>,'pawn_ticket')">Print Ticket</button>
    </div>

    <?php if (count($chain) > 1): ?>
    <div class="bntm-form-section" style="margin:0 0 14px;padding:14px;">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:10px;">Ticket Chain (Root: <?php echo esc_html($loan->root_ticket); ?>)</div>
        <div style="overflow-x:auto;">
        <table style="width:100%;font-size:12px;border-collapse:collapse;">
            <thead><tr style="background:#f3f4f6;">
                <th style="padding:6px 8px;text-align:left;font-weight:700;">Ticket #</th>
                <th style="padding:6px 8px;text-align:left;">Type</th>
                <th style="padding:6px 8px;text-align:left;">Date</th>
                <th style="padding:6px 8px;text-align:right;">Principal</th>
                <th style="padding:6px 8px;text-align:left;">Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($chain as $c_loan):
                $is_current = $c_loan->id == $loan_id;
                $tx_label = ['new'=>'Original','renewal'=>'Renewal','additional'=>'+ Principal','partial_payment'=>'- Principal'][$c_loan->transaction_type] ?? ucfirst($c_loan->transaction_type);
            ?>
            <tr style="<?php echo $is_current ? 'background:#eff6ff;font-weight:700;' : ''; ?>">
                <td style="padding:5px 8px;font-family:monospace;"><?php echo esc_html($c_loan->ticket_number); ?></td>
                <td style="padding:5px 8px;"><span style="font-size:11px;background:#f3f4f6;padding:2px 6px;border-radius:4px;"><?php echo $tx_label; ?></span></td>
                <td style="padding:5px 8px;"><?php echo date('M d, Y',strtotime($c_loan->loan_date)); ?></td>
                <td style="padding:5px 8px;text-align:right;">₱<?php echo number_format($c_loan->principal,2); ?></td>
                <td style="padding:5px 8px;"><span class="ps-status-badge ps-status-<?php echo $c_loan->status; ?>"><?php echo ucfirst($c_loan->status); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($payments)): ?>
    <div class="bntm-form-section" style="margin:0;padding:14px;">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:10px;">Payment History</div>
        <table style="width:100%;font-size:12px;border-collapse:collapse;">
            <thead><tr style="background:#f3f4f6;">
                <th style="padding:5px 8px;text-align:left;">Date</th>
                <th style="padding:5px 8px;text-align:left;">Type</th>
                <th style="padding:5px 8px;text-align:right;">Amount</th>
                <th style="padding:5px 8px;text-align:right;">Interest</th>
                <th style="padding:5px 8px;text-align:left;">Method</th>
            </tr></thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:4px 8px;"><?php echo date('M d, Y H:i',strtotime($pay->created_at)); ?></td>
                <td style="padding:4px 8px;text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_type); ?></td>
                <td style="padding:4px 8px;text-align:right;font-weight:700;">₱<?php echo number_format($pay->amount,2); ?></td>
                <td style="padding:4px 8px;text-align:right;color:#1e40af;">₱<?php echo number_format($pay->interest_amount,2); ?></td>
                <td style="padding:4px 8px;text-transform:capitalize;"><?php echo str_replace('_',' ',$pay->payment_method); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php
    wp_send_json_success(['html' => ob_get_clean()]);
}

// ============================================================
// AJAX: GET TICKET HISTORY
// ============================================================

function bntm_ajax_ps_get_ticket_history() {
    check_ajax_referer('ps_loan_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $root_ticket = sanitize_text_field($_POST['root_ticket'] ?? '');

    $events = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_ticket_history WHERE root_ticket=%s AND business_id=%d ORDER BY created_at ASC",
        $root_ticket, $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section" style="margin-top:14px;padding:14px;">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:10px;">Event Log — Root: <?php echo esc_html($root_ticket); ?></div>
        <table style="width:100%;font-size:11px;border-collapse:collapse;">
            <thead><tr style="background:#f3f4f6;">
                <th style="padding:5px 8px;text-align:left;">Date</th>
                <th style="padding:5px 8px;text-align:left;">Ticket #</th>
                <th style="padding:5px 8px;text-align:left;">Event</th>
                <th style="padding:5px 8px;text-align:right;">Principal Before</th>
                <th style="padding:5px 8px;text-align:right;">Principal After</th>
                <th style="padding:5px 8px;text-align:right;">Interest Paid</th>
                <th style="padding:5px 8px;text-align:right;">Total Paid</th>
                <th style="padding:5px 8px;text-align:left;">Notes</th>
            </tr></thead>
            <tbody>
            <?php foreach ($events as $ev):
                $ec = ['created'=>'#059669','renewed'=>'#7c3aed','payment'=>'#1d4ed8','redeemed'=>'#059669','forfeited'=>'#dc2626','partial_payment'=>'#0891b2','additional_principal'=>'#d97706','reduced_principal'=>'#0891b2'][$ev->event_type] ?? '#374151';
            ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:4px 8px;"><?php echo date('M d, Y',strtotime($ev->created_at)); ?></td>
                <td style="padding:4px 8px;font-family:monospace;"><?php echo esc_html($ev->ticket_number); ?></td>
                <td style="padding:4px 8px;"><span style="color:<?php echo $ec; ?>;font-weight:700;text-transform:capitalize;"><?php echo str_replace('_',' ',$ev->event_type); ?></span></td>
                <td style="padding:4px 8px;text-align:right;"><?php echo $ev->principal_before > 0 ? '₱'.number_format($ev->principal_before,2) : '—'; ?></td>
                <td style="padding:4px 8px;text-align:right;font-weight:700;"><?php echo $ev->principal_after > 0 ? '₱'.number_format($ev->principal_after,2) : '—'; ?></td>
                <td style="padding:4px 8px;text-align:right;color:#1e40af;"><?php echo $ev->interest_paid > 0 ? '₱'.number_format($ev->interest_paid,2) : '—'; ?></td>
                <td style="padding:4px 8px;text-align:right;font-weight:700;"><?php echo $ev->amount_paid > 0 ? '₱'.number_format($ev->amount_paid,2) : '—'; ?></td>
                <td style="padding:4px 8px;color:#6b7280;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($ev->notes); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    wp_send_json_success(['html' => ob_get_clean()]);
}

// ============================================================
// AJAX: SEARCH CUSTOMERS
// ============================================================

function bntm_ajax_ps_search_customers() {
    check_ajax_referer('ps_search_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $q = sanitize_text_field($_POST['q'] ?? '');

    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*,
            (SELECT COUNT(*) FROM {$wpdb->prefix}ps_loans WHERE customer_id=c.id AND status IN ('active','renewed','overdue')) AS active_loans
         FROM {$wpdb->prefix}ps_customers c
         WHERE c.business_id=%d AND c.status='active'
           AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.contact_number LIKE %s OR c.id_number LIKE %s)
         ORDER BY c.last_name ASC LIMIT 15",
        $business_id, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%"
    ));

    wp_send_json_success(['customers' => $customers]);
}

// ============================================================
// AJAX: ADD CUSTOMER
// ============================================================

function bntm_ajax_ps_add_customer() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();

    $fn   = sanitize_text_field($_POST['first_name'] ?? '');
    $ln   = sanitize_text_field($_POST['last_name'] ?? '');
    $mn   = sanitize_text_field($_POST['middle_name'] ?? '');
    $addr = sanitize_textarea_field($_POST['address'] ?? '');
    $con  = sanitize_text_field($_POST['contact_number'] ?? '');
    $email= sanitize_email($_POST['email'] ?? '');
    $idt  = sanitize_text_field($_POST['id_type'] ?? '');
    $idn  = sanitize_text_field($_POST['id_number'] ?? '');
    $flag = sanitize_text_field($_POST['customer_flag'] ?? 'normal');
    $notes= sanitize_textarea_field($_POST['notes'] ?? '');
    $photo_data = $_POST['photo_data'] ?? '';

    if (!$fn || !$ln) { wp_send_json_error(['message'=>'First and last name are required.']); return; }

    // Save photo
    $photo_path = '';
    if (!empty($photo_data) && strpos($photo_data, 'data:image') === 0) {
        $photo_path = ps_save_customer_photo($photo_data, $business_id, 0);
    }

    $r = $wpdb->insert($wpdb->prefix.'ps_customers', [
        'rand_id'       => bntm_rand_id(),
        'business_id'   => $business_id,
        'first_name'    => $fn,
        'last_name'     => $ln,
        'middle_name'   => $mn,
        'address'       => $addr,
        'contact_number'=> $con,
        'email'         => $email,
        'id_type'       => $idt,
        'id_number'     => $idn,
        'customer_flag' => $flag,
        'photo_path'    => $photo_path,
        'notes'         => $notes,
        'status'        => 'active',
    ]);

    if (!$r) { wp_send_json_error(['message'=>'Failed to add customer.']); return; }
    $cid = $wpdb->insert_id;

    // Update photo with real customer ID
    if ($photo_path && $photo_path !== '') {
        $new_path = ps_save_customer_photo($photo_data, $business_id, $cid);
        if ($new_path) {
            $wpdb->update($wpdb->prefix.'ps_customers', ['photo_path'=>$new_path], ['id'=>$cid]);
            $photo_path = $new_path;
        }
    }

    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT *, 0 AS active_loans FROM {$wpdb->prefix}ps_customers WHERE id=%d", $cid
    ));

    wp_send_json_success(['message'=>"Customer {$ln}, {$fn} added!", 'customer'=>$customer]);
}

// ============================================================
// AJAX: EDIT CUSTOMER
// ============================================================

function bntm_ajax_ps_edit_customer() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $cid = intval($_POST['customer_id'] ?? 0);

    if (!$cid) { wp_send_json_error(['message'=>'Customer ID missing.']); return; }

    $data = [
        'first_name'    => sanitize_text_field($_POST['first_name'] ?? ''),
        'last_name'     => sanitize_text_field($_POST['last_name'] ?? ''),
        'middle_name'   => sanitize_text_field($_POST['middle_name'] ?? ''),
        'address'       => sanitize_textarea_field($_POST['address'] ?? ''),
        'contact_number'=> sanitize_text_field($_POST['contact_number'] ?? ''),
        'email'         => sanitize_email($_POST['email'] ?? ''),
        'id_type'       => sanitize_text_field($_POST['id_type'] ?? ''),
        'id_number'     => sanitize_text_field($_POST['id_number'] ?? ''),
        'customer_flag' => sanitize_text_field($_POST['customer_flag'] ?? 'normal'),
        'notes'         => sanitize_textarea_field($_POST['notes'] ?? ''),
    ];

    $photo_data = $_POST['photo_data'] ?? '';
    if (!empty($photo_data) && strpos($photo_data, 'data:image') === 0) {
        $photo_path = ps_save_customer_photo($photo_data, $business_id, $cid);
        if ($photo_path) $data['photo_path'] = $photo_path;
    }

    $r = $wpdb->update($wpdb->prefix.'ps_customers', $data, ['id'=>$cid,'business_id'=>$business_id]);
    $updated_customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ps_customers WHERE id=%d", $cid));
    wp_send_json_success(['message'=>'Customer updated!', 'customer'=>$updated_customer, 'photo_path'=>$updated_customer->photo_path ?? '']);
}

// ============================================================
// AJAX: DELETE CUSTOMER
// ============================================================

function bntm_ajax_ps_delete_customer() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $cid = intval($_POST['customer_id'] ?? 0);

    $active = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ps_loans WHERE customer_id=%d AND status IN ('active','renewed','overdue')",
        $cid
    ));
    if ($active > 0) { wp_send_json_error(['message'=>'Cannot delete customer with active loans.']); return; }

    $wpdb->update($wpdb->prefix.'ps_customers', ['status'=>'deleted'], ['id'=>$cid,'business_id'=>$business_id]);
    wp_send_json_success(['message'=>'Customer deleted.']);
}

// ============================================================
// AJAX: GET CUSTOMER PROFILE
// ============================================================

function bntm_ajax_ps_get_customer_profile() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $cid = intval($_POST['customer_id'] ?? 0);

    $c = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_customers WHERE id=%d AND business_id=%d",
        $cid, $business_id
    ));
    if (!$c) { wp_send_json_error(['message'=>'Customer not found.']); return; }

    $loans = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*,col.description AS collateral_desc FROM {$wpdb->prefix}ps_loans l
         JOIN {$wpdb->prefix}ps_collaterals col ON col.id=l.collateral_id
         WHERE l.customer_id=%d ORDER BY l.id DESC LIMIT 30",
        $cid
    ));

    $total_loans  = count($loans);
    $active_loans = count(array_filter($loans, fn($l) => in_array($l->status, ['active','renewed','overdue'])));
    $total_paid   = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(p.amount),0) FROM {$wpdb->prefix}ps_payments p
         JOIN {$wpdb->prefix}ps_loans l ON l.id=p.loan_id
         WHERE l.customer_id=%d", $cid
    ));
    $outstanding  = (float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(principal),0) FROM {$wpdb->prefix}ps_loans WHERE customer_id=%d AND status IN ('active','renewed','overdue')", $cid
    ));

    $flag_class = ['normal'=>'ps-flag-normal','vip'=>'ps-flag-vip','delinquent'=>'ps-flag-delinquent','blacklisted'=>'ps-flag-blacklisted'][$c->customer_flag] ?? 'ps-flag-normal';

    ob_start(); ?>
    <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:18px;flex-wrap:wrap;">
        <?php if ($c->photo_path): ?>
        <div style="position:relative;width:88px;height:88px;flex-shrink:0;">
            <img src="<?php echo esc_url(BNTM_PS_PHOTO_URL.$c->photo_path); ?>"
                 style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid #e0e7ff;display:block;"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
            <div style="display:none;width:88px;height:88px;border-radius:50%;background:#1e40af;align-items:center;justify-content:center;color:#fff;font-size:30px;font-weight:800;position:absolute;top:0;left:0;"><?php echo strtoupper(substr($c->last_name,0,1)); ?></div>
        </div>
        <?php else: ?>
        <div style="width:88px;height:88px;border-radius:50%;background:#1e40af;display:flex;align-items:center;justify-content:center;color:#fff;font-size:30px;font-weight:800;flex-shrink:0;"><?php echo strtoupper(substr($c->last_name,0,1)); ?></div>
        <?php endif; ?>
        <div style="flex:1;min-width:200px;">
            <div style="font-size:19px;font-weight:800;margin-bottom:3px;"><?php echo esc_html($c->last_name.', '.$c->first_name.' '.$c->middle_name); ?></div>
            <div style="font-size:12px;color:#6b7280;"><?php echo esc_html($c->contact_number); ?></div>
            <div style="font-size:12px;color:#6b7280;"><?php echo esc_html($c->email); ?></div>
            <div style="font-size:12px;color:#6b7280;margin-top:2px;"><?php echo esc_html($c->id_type.': '.$c->id_number); ?></div>
            <span class="ps-status-badge <?php echo $flag_class; ?>" style="margin-top:6px;"><?php echo ucfirst($c->customer_flag); ?></span>
        </div>
        <div>
            <button class="bntm-btn-secondary" style="font-size:12px;padding:6px 12px;"
                onclick="psEditCustomer(<?php echo $c->id; ?>,'<?php echo esc_js($c->first_name); ?>','<?php echo esc_js($c->last_name); ?>','<?php echo esc_js($c->middle_name); ?>','<?php echo esc_js($c->address); ?>','<?php echo esc_js($c->contact_number); ?>','<?php echo esc_js($c->email); ?>','<?php echo esc_js($c->id_type); ?>','<?php echo esc_js($c->id_number); ?>','<?php echo $c->customer_flag; ?>','<?php echo esc_js($c->notes); ?>','<?php echo esc_js($c->photo_path); ?>')">✏️ Edit</button>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px;">
        <div style="background:#eff6ff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:20px;font-weight:800;color:#1e40af;"><?php echo $total_loans; ?></div><div style="font-size:11px;color:#3b82f6;font-weight:600;">Total Loans</div></div>
        <div style="background:#f0fdf4;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:20px;font-weight:800;color:#059669;"><?php echo $active_loans; ?></div><div style="font-size:11px;color:#10b981;font-weight:600;">Active</div></div>
        <div style="background:#faf5ff;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:16px;font-weight:800;color:#7c3aed;">₱<?php echo number_format($outstanding,2); ?></div><div style="font-size:11px;color:#8b5cf6;font-weight:600;">Outstanding</div></div>
        <div style="background:#f3f4f6;border-radius:8px;padding:10px;text-align:center;"><div style="font-size:16px;font-weight:800;color:#374151;">₱<?php echo number_format($total_paid,2); ?></div><div style="font-size:11px;color:#6b7280;font-weight:600;">Total Paid</div></div>
    </div>

    <?php if ($c->address): ?>
    <div style="font-size:12px;color:#6b7280;margin-bottom:14px;"><?php echo esc_html($c->address); ?></div>
    <?php endif; ?>

    <?php if (!empty($loans)): ?>
    <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#6b7280;margin-bottom:8px;">Loan History</div>
    <div class="bntm-table-wrapper"><table class="bntm-table">
        <thead><tr><th>Ticket #</th><th>Root</th><th>Collateral</th><th>Principal</th><th>Date</th><th>Due</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($loans as $l): ?>
        <tr>
            <td style="font-family:monospace;font-size:12px;font-weight:700;"><?php echo esc_html($l->ticket_number); ?></td>
            <td><?php if ($l->root_ticket !== $l->ticket_number): ?><span class="ps-ticket-chain"><?php echo esc_html($l->root_ticket); ?></span><?php else: ?><span style="color:#9ca3af;font-size:11px;">—</span><?php endif; ?></td>
            <td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html($l->collateral_desc); ?></td>
            <td style="font-weight:700;">₱<?php echo number_format($l->principal,2); ?></td>
            <td style="font-size:12px;"><?php echo date('M d, Y',strtotime($l->loan_date)); ?></td>
            <td style="font-size:12px;"><?php echo date('M d, Y',strtotime($l->due_date)); ?></td>
            <td><span class="ps-status-badge ps-status-<?php echo $l->status; ?>"><?php echo ucfirst($l->status); ?></span></td>
            <td><button class="ps-action-btn ps-btn-view" onclick="psViewLoanDetail(<?php echo $l->id; ?>)">View</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <?php
    wp_send_json_success(['html' => ob_get_clean()]);
}

// ============================================================
// AJAX: UPDATE COLLATERAL STATUS
// ============================================================

function bntm_ajax_ps_update_collateral_status() {
    check_ajax_referer('ps_collat_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id    = get_current_user_id();
    $collateral_id  = intval($_POST['collateral_id'] ?? 0);
    $new_status     = sanitize_text_field($_POST['new_status'] ?? '');
    $allowed        = ['for_auction','for_sale','pawned','sold','forfeited'];

    if (!in_array($new_status, $allowed)) { wp_send_json_error(['message'=>'Invalid status.']); return; }
    $wpdb->update($wpdb->prefix.'ps_collaterals', ['status'=>$new_status], ['id'=>$collateral_id,'business_id'=>$business_id]);
    wp_send_json_success(['message'=>'Collateral status updated.']);
}

// ============================================================
// AJAX: GENERATE DOCUMENT
// ============================================================

/**
 * DOCUMENT GENERATION — COMPLETE REWRITE v2
 * Matches Agencia Ranaw Pawnshop reference document formats exactly.
 *
 * PAWN TICKET: Renders the blank form as a faithful reproduction,
 *              then overlays dynamic data onto the blank lines —
 *              giving the appearance of a pre-printed form filled in.
 *
 * ALL OTHER DOCS: Corporate typewriter/register style matching the
 *                 physical printouts from the reference PDF.
 *
 * Paper / orientation map:
 *  pawn_ticket        → Half A4  (148mm × 210mm)  Portrait
 *  payment_receipt    → Half A4  (148mm × 210mm)  Portrait
 *  forfeiture_notice  → Short (8.5in × 11in)      Portrait
 *  redemption_receipt → Short (8.5in × 11in)      Portrait
 *  renewal_notice     → Short (8.5in × 11in)      Portrait
 *  customer_statement → Short (8.5in × 11in)      Portrait
 *  daily_summary      → Short (8.5in × 11in)      Portrait
 *  cash_flow_summary  → Short (8.5in × 11in)      Portrait
 *  loan_list          → Long  (13in × 11in)        Landscape
 *  payments_list      → Long  (13in × 11in)        Landscape
 *  ticket_chain       → Long  (13in × 11in)        Landscape
 */

// ============================================================
// MAIN AJAX HANDLER
// ============================================================
function bntm_ajax_ps_generate_document() {
    check_ajax_referer( 'ps_doc_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( ['message' => 'Unauthorized'] );
    }
 
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval( $_POST['loan_id']     ?? 0 );
    $doc_type    = sanitize_text_field( $_POST['doc_type']    ?? '' );
    $report_date = sanitize_text_field( $_POST['report_date'] ?? date('Y-m-d') );
    $rfrom       = sanitize_text_field( $_POST['rfrom']       ?? date('Y-m-01') );
    $rto         = sanitize_text_field( $_POST['rto']         ?? date('Y-m-d') );
    $rstatus     = sanitize_text_field( $_POST['rstatus']     ?? 'all' );
 
    /* ── shared business info ── */
    $b = ps_biz();
 
    /* ── report types (no loan required) ── */
    $report_types = ['summary', 'list_loans', 'list_payments', 'daily_summary', 'tickets_by_status'];
 
    if ( in_array( $doc_type, $report_types ) ) {
        switch ( $doc_type ) {
            case 'summary':
                $html = ps_rpt_summary( $business_id, $rfrom, $rto, $b );
                break;
            case 'list_loans':
                $html = ps_rpt_list_loans( $business_id, $rfrom, $rto, $b );
                break;
            case 'list_payments':
                $html = ps_rpt_list_payments( $business_id, $rfrom, $rto, $b );
                break;
            case 'daily_summary':
                $html = ps_rpt_daily_summary( $business_id, $report_date, $b );
                break;
            case 'tickets_by_status':
                $html = ps_rpt_tickets_by_status( $business_id, $rfrom, $rto, $rstatus, $b );
                break;
            default:
                wp_send_json_error( ['message' => 'Unknown report type.'] );
                return;
        }
        wp_send_json_success( ['html' => ps_wrap_page( $html )] );
        return;
    }
 
    /* ── document types (loan required) ── */
    if ( ! $loan_id ) {
        wp_send_json_error( ['message' => 'Loan ID required.'] );
        return;
    }
 
    $loan = $wpdb->get_row( $wpdb->prepare(
        "SELECT l.*,
                CONCAT(c.last_name,', ',c.first_name,' ',COALESCE(c.middle_name,'')) AS customer_name,
                c.contact_number, c.address, c.id_type, c.id_number, c.photo_path,
                col.description  AS collateral_desc,
                col.category, col.brand, col.model,
                col.appraised_value, col.item_condition,
                col.karat, col.weight_grams, col.serial_number
         FROM   {$wpdb->prefix}ps_loans        l
         JOIN   {$wpdb->prefix}ps_customers    c   ON c.id  = l.customer_id
         JOIN   {$wpdb->prefix}ps_collaterals  col ON col.id = l.collateral_id
         WHERE  l.id = %d AND l.business_id = %d",
        $loan_id, $business_id
    ) );
 
    if ( ! $loan ) {
        wp_send_json_error( ['message' => 'Loan not found.'] );
        return;
    }
 
    $bd = ps_compute_interest_breakdown( $loan );
 
    switch ( $doc_type ) {
        case 'pawn_ticket':
            $html = ps_doc_pawn_ticket( $loan, $bd, $b );
            break;
        case 'renewal_notice':
            $html = ps_doc_renewal_notice( $loan, $bd, $b );
            break;
        case 'redemption_receipt':
            $html = ps_doc_redemption_receipt( $loan, $bd, $b );
            break;
        case 'forfeiture_notice':
            $html = ps_doc_forfeiture_notice( $loan, $b );
            break;
        case 'payment_receipt':
            $html = ps_doc_payment_receipt( $loan, $b );
            break;
        case 'customer_statement':
            $html = ps_doc_customer_statement( $loan, $b );
            break;
        case 'ticket_chain':
            $html = ps_doc_ticket_chain( $loan, $b );
            break;
        default:
            wp_send_json_error( ['message' => 'Unknown document type: ' . esc_html( $doc_type ) ] );
            return;
    }
 
    wp_send_json_success( ['html' => $html] );
}
add_action( 'wp_ajax_ps_generate_document',    'bntm_ajax_ps_generate_document' );
// ============================================================
// SHARED HELPERS
// ============================================================
/** Fetch all business settings in one call. */
function ps_biz(): array {
    return [
        'name'    => bntm_get_setting('ps_business_name',    'PAWNSHOP'),
        'addr'    => bntm_get_setting('ps_business_address', ''),
        'con'     => bntm_get_setting('ps_business_contact', ''),
        'tin'     => bntm_get_setting('ps_business_tin',     ''),
        'licens'  => bntm_get_setting('ps_business_license', ''),
        'bsp'     => bntm_get_setting('ps_business_bsp',     ''),
        'footer'  => bntm_get_setting('ps_doc_footer', 'This document is computer-generated and valid without signature.'),
    ];
}
 
/** Wrap report HTML in a printable page shell. */
function ps_wrap_page( string $inner ): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
         . ps_page_style()
         . ps_reg_table_style()
         . '</head><body><div style="padding:8px;">'
         . $inner
         . '</div></body></html>';
}
 
/** @page rule — Half A4 portrait for all outputs. */
function ps_page_style(): string {
    return '<style>
        @page { size: 148mm 210mm portrait; margin: 6mm 8mm; }
        * { box-sizing: border-box; }
        body { margin:0; padding:0; font-family:"Courier New",Courier,monospace; font-size:10px; color:#000; background:#fff; }
        @media print { html,body { width:100%; } .no-print { display:none !important; } }
    </style>';
}
 
/** Centred corporate header — business name, address, tel, TIN. */
function ps_corp_header( array $b, string $sub = '' ): string {
    $h  = "<div style='text-align:center;margin-bottom:6px;'>";
    $h .= "<div style='font-size:14px;font-weight:900;font-family:\"Times New Roman\",serif;"
        . "text-transform:uppercase;letter-spacing:1px;line-height:1.2;'>" . esc_html( $b['name'] ) . "</div>";
    if ( $b['addr'] ) $h .= "<div style='font-size:9px;margin-top:1px;'>" . esc_html( $b['addr'] ) . "</div>";
    if ( $b['con']  ) $h .= "<div style='font-size:9px;'>Tel. No. " . esc_html( $b['con'] ) . "</div>";
    if ( $b['tin']  ) $h .= "<div style='font-size:9px;'>Non-VAT Reg. - TIN " . esc_html( $b['tin'] ) . "</div>";
    if ( $b['licens'] || $b['bsp'] ) {
        $h .= "<div style='font-size:9px;'>";
        if ( $b['licens'] ) $h .= "DTI/SEC: " . esc_html( $b['licens'] );
        if ( $b['bsp']    ) $h .= " &nbsp; BSP: " . esc_html( $b['bsp'] );
        $h .= "</div>";
    }
    if ( $sub ) $h .= $sub;
    $h .= "</div>";
    return $h;
}
 
/** Underlined centred document title + optional subtitle. */
function ps_doc_title( string $title, string $sub = '' ): string {
    $h  = "<div style='text-align:center;margin-bottom:4px;'>";
    $h .= "<div style='font-size:11px;font-weight:700;font-family:\"Times New Roman\",serif;"
        . "text-decoration:underline;text-transform:uppercase;letter-spacing:.3px;'>" . esc_html( $title ) . "</div>";
    if ( $sub ) $h .= "<div style='font-size:9px;margin-top:2px;'>" . esc_html( $sub ) . "</div>";
    $h .= "</div>";
    return $h;
}
 
function ps_divider(): string {
    return "<div style='border-bottom:1px solid #000;margin:3px 0;'></div>";
}
 
function ps_footer_line( string $text ): string {
    return "<div style='margin-top:12px;padding-top:4px;border-top:1px solid #999;"
         . "font-size:8px;color:#555;text-align:center;'>" . esc_html( $text ) . "</div>";
}
 
/** Register-style table CSS — black/white Courier. */
function ps_reg_table_style(): string {
    return '<style>
    table.reg { width:100%;border-collapse:collapse;font-size:9px;font-family:"Courier New",monospace; }
    table.reg thead tr th {
        font-size:8.5px;font-weight:700;text-transform:uppercase;
        border-top:1px solid #000;border-bottom:1px solid #000;
        padding:3px 4px;text-align:left;white-space:nowrap;background:#fff;
    }
    table.reg tbody tr td { font-size:9px;padding:2px 4px;vertical-align:top; }
    table.reg tfoot tr td {
        font-size:9px;font-weight:700;
        border-top:1px solid #000;border-bottom:1px solid #000;padding:3px 4px;
    }
    table.reg .r { text-align:right; }
    table.reg .c { text-align:center; }
    </style>';
}
 
/** Generic n-column signatory footer. */
function ps_sig_footer( array $labels, int $margin_top = 28 ): string {
    $n   = count( $labels );
    $h   = "<div style='display:grid;grid-template-columns:" . implode(' ', array_fill(0, $n, '1fr') ) . ";gap:14px;margin-top:{$margin_top}px;'>";
    foreach ( $labels as $lbl ) {
        $h .= "<div style='text-align:center;'>"
            . "<div style='border-top:1px solid #000;margin-top:24px;padding-top:2px;font-size:8.5px;'>"
            . esc_html( $lbl ) . "</div></div>";
    }
    $h .= "</div>";
    return $h;
}
 
/** Pawner info block used in several documents. */
function ps_pawner_block( $loan ): string {
    $fields = [
        'Name of Customer' => trim( $loan->customer_name ),
        'Address'          => $loan->address,
        'Contact Number'   => $loan->contact_number,
    ];
    $h = "<div style='font-size:9.5px;margin-bottom:7px;line-height:1.8;'>";
    foreach ( $fields as $label => $value ) {
        $h .= "<div style='display:flex;gap:4px;'>"
            . "<span style='min-width:130px;'>" . esc_html( $label ) . "</span>"
            . "<span>:</span>"
            . "<span><strong>" . esc_html( $value ) . "</strong></span>"
            . "</div>";
    }
    $h .= "</div>";
    return $h;
}
 
/** Single cf-style line: label ⟶ right-aligned value. */
function ps_cf_line( string $label, string $value, bool $bold = false, string $extra_style = '' ): string {
    $bw = $bold ? 'font-weight:700;' : '';
    return "<div style='display:flex;justify-content:space-between;font-size:9.5px;padding:1.5px 0;{$extra_style}'>"
         . "<span>{$label}</span>"
         . "<span style='min-width:90px;text-align:right;{$bw}'>{$value}</span>"
         . "</div>";
}
 
/** Double-rule total line. */
function ps_cf_total( string $label, string $value ): string {
    return "<div style='display:flex;justify-content:space-between;font-size:9.5px;font-weight:700;"
         . "border-top:1px solid #000;border-bottom:2px solid #000;padding:2px 0;margin-top:2px;'>"
         . "<span>{$label}</span>"
         . "<span style='min-width:90px;text-align:right;'>{$value}</span>"
         . "</div>";
}
 
/** Convert float to PESOS words (used on pawn ticket). */
function ps_number_to_words( float $number ): string {
    $ones = ['','ONE','TWO','THREE','FOUR','FIVE','SIX','SEVEN','EIGHT','NINE',
             'TEN','ELEVEN','TWELVE','THIRTEEN','FOURTEEN','FIFTEEN','SIXTEEN',
             'SEVENTEEN','EIGHTEEN','NINETEEN'];
    $tens = ['','','TWENTY','THIRTY','FORTY','FIFTY','SIXTY','SEVENTY','EIGHTY','NINETY'];
    $fn   = function( int $n ) use ( &$fn, $ones, $tens ): string {
        if ( $n === 0 ) return '';
        if ( $n < 20  ) return $ones[$n] . ' ';
        if ( $n < 100 ) return $tens[(int)($n/10)] . ' ' . ($n%10 ? $ones[$n%10].' ' : '');
        if ( $n < 1000     ) return $ones[(int)($n/100)]   . ' HUNDRED '  . $fn($n%100);
        if ( $n < 1000000  ) return $fn((int)($n/1000))    . 'THOUSAND '  . $fn($n%1000);
        return $fn((int)($n/1000000)) . 'MILLION ' . $fn($n%1000000);
    };
    $int   = (int) floor($number);
    $dec   = round(($number - $int) * 100);
    $words = $int === 0 ? 'ZERO ' : $fn($int);
    $words .= 'PESOS';
    if ( $dec > 0 ) $words .= ' AND ' . sprintf('%02d', $dec) . '/100';
    return trim($words);
}
 
 
// ============================================================
// ① PAWN TICKET
// ============================================================
function ps_doc_pawn_ticket( $loan, $bd, array $b ): string {
    $net_proceeds  = $loan->principal - $loan->service_fee;
    $grace_days    = (int) bntm_get_setting('ps_grace_period', '0');
    $expiry_date   = date('M d, Y', strtotime($loan->due_date . ' +' . $grace_days . ' days'));
    $loan_date_fmt = date('M d, Y', strtotime($loan->loan_date));
    $due_date_fmt  = date('M d, Y', strtotime($loan->due_date));
    $rate_pct      = number_format((float)$loan->interest_rate, 0) . '%';
    $term_display  = $loan->term_months > 1 ? $loan->term_months : '30';
 
    $karat_weight = trim( $loan->karat . ($loan->weight_grams > 0 ? ' / ' . $loan->weight_grams . ' GMS.' : '') );
    $desc_parts   = array_values( array_filter([
        $loan->collateral_desc,
        $loan->brand ? ($loan->brand . ($loan->model ? ' ' . $loan->model : '')) : '',
        $karat_weight,
        $loan->serial_number ? 'S/N: ' . $loan->serial_number : '',
    ]));
    while ( count($desc_parts) < 4 ) { $desc_parts[] = ''; }
 
    $chain_note = ($loan->root_ticket !== $loan->ticket_number)
        ? '<span style="font-size:8px;color:#555;">(Chain: Root ' . esc_html($loan->root_ticket) . ')</span>'
        : '';
 
    ob_start(); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<?php echo ps_page_style(); ?>
<style>
body { font-family:'Times New Roman',Times,serif; font-size:10px; }
.tk-bname  { font-size:15px;font-weight:900;font-family:'Times New Roman',serif;text-transform:uppercase;letter-spacing:1px;color:#8B0000; }
.tk-bsub   { font-size:8.5px;font-family:'Times New Roman',serif;line-height:1.5; }
.tk-row    { display:flex;align-items:flex-end;gap:4px;margin-bottom:4px;font-size:9.5px; }
.tk-lbl    { white-space:nowrap;flex-shrink:0; }
.tk-val    { flex:1;border-bottom:1px solid #666;font-weight:700;font-size:9.5px;padding-bottom:1px; }
.tk-narr   { font-size:9px;line-height:1.75;margin-bottom:3px; }
.tk-ul     { border-bottom:1px solid #555;padding-bottom:1px;font-weight:700; }
.tk-desc-title { font-size:8.5px;text-align:center;margin-bottom:3px; }
.tk-desc-line  { border-bottom:1px solid #555;margin-bottom:4px;height:14px;font-size:9.5px;font-weight:700;padding-left:2px; }
.tk-amt-lbl  { flex:1;padding-right:4px;font-size:9px; }
.tk-amt-val  { border-bottom:1px solid #555;min-width:70px;text-align:right;font-weight:700;padding-bottom:1px;font-size:9.5px; }
.tk-sig-line { border-top:1px solid #000;padding-top:2px;font-size:8px; }
.tk-notice   { font-size:8px;font-weight:900;text-align:center;text-transform:uppercase;
               letter-spacing:.3px;border-top:1px solid #000;padding-top:3px;margin-top:6px; }
</style>
</head><body>
<div>
    <div style="text-align:center;margin-bottom:5px;">
        <div class="tk-bname"><?php echo esc_html($b['name']); ?></div>
        <?php if ($b['addr'])   echo "<div class='tk-bsub'>" . esc_html($b['addr'])   . "</div>"; ?>
        <?php if ($b['con'])    echo "<div class='tk-bsub'>Tel No.: " . esc_html($b['con']) . "</div>"; ?>
        <?php if ($b['tin'])    echo "<div class='tk-bsub'>Non-VAT Reg. - TIN " . esc_html($b['tin']) . "</div>"; ?>
        <?php if ($b['licens'] || $b['bsp']): ?>
        <div class="tk-bsub">
            <?php if ($b['licens']) echo 'DTI/SEC: ' . esc_html($b['licens']); ?>
            <?php if ($b['bsp'])    echo ' &nbsp; BSP: ' . esc_html($b['bsp']); ?>
        </div>
        <?php endif; ?>
    </div>
 
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:2px;">
        <div>
            <span style="font-size:10px;font-weight:700;">Serial No.</span>
            <span style="font-size:20px;font-weight:900;font-family:'Times New Roman',serif;letter-spacing:1px;"><?php echo esc_html($loan->ticket_number); ?></span>
            <?php echo $chain_note; ?>
        </div>
        <div style="font-size:11px;font-style:italic;">Original</div>
    </div>
 
    <hr style="border:none;border-top:1px solid #999;margin:2px 0 4px;">
 
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:4px;">
        <div class="tk-row"><span class="tk-lbl">Date Loan Granted</span><span class="tk-val"><?php echo $loan_date_fmt; ?></span></div>
        <div class="tk-row"><span class="tk-lbl">Maturity Date</span><span class="tk-val"><?php echo $due_date_fmt; ?></span></div>
    </div>
    <div class="tk-row"><span class="tk-lbl">Expiry Date of Redemption:</span><span class="tk-val"><?php echo $expiry_date; ?></span></div>
 
    <div class="tk-row" style="margin-top:4px;">
        <span class="tk-lbl">Mr./Ms.</span>
        <span class="tk-val" style="max-width:120px;"><?php echo esc_html(trim($loan->customer_name)); ?></span>
        <span class="tk-lbl" style="padding-left:4px;">a resident of</span>
        <span class="tk-val" style="font-size:8.5px;"><?php echo esc_html($loan->address); ?></span>
    </div>
    <div style="font-size:8px;font-style:italic;text-align:right;margin-bottom:3px;color:#333;">(No. Street Barangay/Town or City/Province)</div>
 
    <div class="tk-narr">for a loan of PESOS <span class="tk-ul"><?php echo esc_html(ps_number_to_words($loan->principal)); ?></span>
        (P <span class="tk-ul"><?php echo number_format($loan->principal,2); ?></span>)
        with an interest of <span class="tk-ul"><?php echo $rate_pct; ?></span> percent (<span class="tk-ul"><?php echo $rate_pct; ?></span>)
    </div>
    <div class="tk-narr">for (<span class="tk-ul"><?php echo $term_display; ?></span> days/month), has pledged to this Pawnee as security for the loan, article(s) described below appraised at PESOS.</div>
    <div class="tk-narr">(P&nbsp;<span class="tk-ul"><?php echo number_format($loan->appraised_value,2); ?></span>) subject to the terms and conditions stated on the reverse side hereof.&nbsp; Penalty interest, if any <span class="tk-ul"><?php echo number_format($loan->penalty_rate,2); ?>%</span></div>
 
    <div style="display:grid;grid-template-columns:52% 48%;gap:6px;margin-top:4px;">
        <div>
            <div class="tk-desc-title">Description of the Pawn</div>
            <?php foreach ($desc_parts as $dl): ?>
            <div class="tk-desc-line"><?php echo esc_html($dl); ?></div>
            <?php endforeach; ?>
        </div>
        <div style="padding-left:6px;">
            <?php
            $amt_rows = [
                ['Principal',                     'P ' . number_format($loan->principal,2)],
                ['Interest in absolute amount 1/', number_format($bd['regular_interest'],2)],
                ['Service Charge',                 number_format($loan->service_fee,2)],
            ];
            foreach ($amt_rows as $ar): ?>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:4px;">
                <span class="tk-amt-lbl"><?php echo $ar[0]; ?></span>
                <span class="tk-amt-val"><?php echo $ar[1]; ?></span>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:4px;">
                <span class="tk-amt-lbl">Net Proceeds</span>
                <span class="tk-amt-val" style="border-bottom:2px solid #000;">P <?php echo number_format($net_proceeds,2); ?></span>
            </div>
            <div style="font-size:8px;font-style:italic;margin-top:3px;">Effective Interest Rate in Percent _____%</div>
            <div style="font-size:8.5px;margin-top:3px;">Please check:</div>
            <div style="font-size:8.5px;">Per annum[ ] &nbsp; Per Month [<?php echo $loan->interest_rate > 0 ? ' ✓ ' : '   '; ?>] &nbsp; (Others)[ ]</div>
            <div style="font-size:8px;font-style:italic;margin-top:3px;"><sup>1/</sup>Formula (Principal × Rate × Time)</div>
        </div>
    </div>
 
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:5px;">
        <div class="tk-row" style="margin-bottom:0;"><span class="tk-lbl">ID Presented</span><span class="tk-val" style="font-size:8.5px;"><?php echo esc_html($loan->id_type . ': ' . $loan->id_number); ?></span></div>
        <div class="tk-row" style="margin-bottom:0;"><span class="tk-lbl">Contact No.:</span><span class="tk-val" style="font-size:8.5px;"><?php echo esc_html($loan->contact_number); ?></span></div>
    </div>
 
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:18px;text-align:center;">
        <div><div style="border-bottom:1px solid #000;height:18px;"></div><div class="tk-sig-line">(Signature or Thumbmark of Pawner)</div></div>
        <div><div style="border-bottom:1px solid #000;height:18px;"></div><div class="tk-sig-line">(Signature or Pawnshop's Authorized Representative)</div></div>
    </div>
 
    <div class="tk-notice">PAWNER IS ADVISED TO READ AND UNDERSTAND THE TERMS AND CONDITIONS ON THE REVERSE SIDE HEREOF</div>
    <?php echo ps_footer_line( $b['footer'] ); ?>
</div>
</body></html>
<?php return ob_get_clean();
}
 
 
// ============================================================
// ② RENEWAL NOTICE
// ============================================================
function ps_doc_renewal_notice( $loan, $bd, array $b ): string {
    global $wpdb;
    $prev = $loan->parent_loan_id
        ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}ps_loans WHERE id=%d", $loan->parent_loan_id) )
        : null;
 
    ob_start();
    echo ps_wrap_page(
        ps_corp_header( $b )
        . ps_doc_title( 'RENEWAL / PAYMENT NOTICE', 'Date: ' . date('F d, Y') )
        . ps_divider()
        . "<div style='display:flex;justify-content:space-between;margin-bottom:6px;font-size:9.5px;'>"
        . "<span>Ticket No.: <strong>" . esc_html($loan->ticket_number) . "</strong>"
        . ($prev ? " &nbsp; Previous: " . esc_html($prev->ticket_number) : '')
        . " &nbsp; Root: " . esc_html($loan->root_ticket) . "</span>"
        . "<span>Date: " . date('F d, Y') . "</span></div>"
        . ps_pawner_block( $loan )
        . "<div style='font-size:9.5px;margin-bottom:4px;'><strong>Collateral:</strong> " . esc_html($loan->collateral_desc) . "</div>"
        . ps_divider()
        . ps_cf_line('Principal', 'P ' . number_format($loan->principal, 2))
        . ps_cf_line('Transaction Type', ucfirst(str_replace('_', ' ', $loan->transaction_type)))
        . ps_cf_line('Interest Rate', $loan->interest_rate . '%/month')
        . ps_cf_line('Days Accrued', $bd['days_elapsed'] . ' days')
        . ps_cf_line('Accrued Interest', number_format($bd['regular_interest'], 2))
        . ($bd['penalty_interest'] > 0 ? ps_cf_line('Additional Interest (' . $bd['effective_overdue'] . 'd × ' . $loan->penalty_rate . '%/mo)', number_format($bd['penalty_interest'], 2)) : '')
        . ($loan->service_fee > 0      ? ps_cf_line('Service Fee', number_format($loan->service_fee, 2)) : '')
        . ps_divider()
        . ps_cf_line('New Due Date', date('F d, Y', strtotime($loan->due_date)))
        . ps_cf_total('ESTIMATED NEXT REDEMPTION', 'P ' . number_format($bd['total_due'], 2))
        . ps_sig_footer(["Pawner's Signature", 'Teller / Cashier', 'Manager / Authorized Signatory'])
        . ps_footer_line($b['footer'])
    );
    return ob_get_clean();
}
 
 
// ============================================================
// ③ REDEMPTION RECEIPT
// ============================================================
function ps_doc_redemption_receipt( $loan, $bd, array $b ): string {
    global $wpdb;
    $pay = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_payments WHERE loan_id=%d AND payment_type='redemption' ORDER BY created_at DESC LIMIT 1",
        $loan->id
    ) );
 
    $col_line = esc_html($loan->collateral_desc)
        . ($loan->karat ? ' — ' . esc_html($loan->karat) : '')
        . ($loan->weight_grams > 0 ? ' / ' . $loan->weight_grams . ' GMS.' : '');
 
    $body  = ps_corp_header( $b )
           . ps_doc_title('REDEMPTION RECEIPT', 'Date: ' . date('F d, Y H:i'))
           . ps_divider()
           . "<div style='display:flex;justify-content:space-between;margin-bottom:5px;font-size:9.5px;'>"
           . "<span>Ticket No.: <strong>" . esc_html($loan->ticket_number) . "</strong> &nbsp; Root: " . esc_html($loan->root_ticket) . "</span>"
           . "<span>Status: <strong>REDEEMED</strong></span></div>"
           . ps_pawner_block( $loan )
           . "<div style='font-size:9.5px;margin-bottom:5px;'><strong>Collateral Returned:</strong> {$col_line}</div>"
           . ps_divider();
 
    if ( $pay ) {
        $body .= ps_cf_line('Principal', 'P ' . number_format($loan->principal, 2))
              .  ps_cf_line('Accrued Interest (' . $bd['days_elapsed'] . ' days)', number_format($pay->interest_amount, 2))
              .  ($pay->penalty_amount > 0 ? ps_cf_line('Additional Interest', number_format($pay->penalty_amount, 2)) : '')
              .  ($pay->service_fee > 0    ? ps_cf_line('Service Fee', number_format($pay->service_fee, 2)) : '')
              .  ps_cf_total('TOTAL AMOUNT PAID', 'P ' . number_format($pay->amount, 2))
              .  ps_cf_line('Payment Method', ucfirst(str_replace('_', ' ', $pay->payment_method)), false, 'margin-top:4px;')
              .  ps_cf_line('Date &amp; Time', date('F d, Y H:i', strtotime($pay->created_at)));
    } else {
        $body .= "<div style='text-align:center;padding:8px;font-size:9.5px;'>No redemption payment record found.</div>";
    }
 
    $body .= ps_divider()
           . "<div style='font-size:10px;font-weight:700;text-align:center;margin-top:6px;letter-spacing:.5px;'>* COLLATERAL RELEASED — LOAN FULLY SETTLED *</div>"
           . ps_sig_footer(["Pawner's Signature", 'Teller / Cashier', 'Manager / Authorized Signatory'])
           . ps_footer_line($b['footer']);
 
    return ps_wrap_page( $body );
}
 
 
// ============================================================
// ④ FORFEITURE NOTICE
// ============================================================
function ps_doc_forfeiture_notice( $loan, array $b ): string {
    $body  = ps_corp_header( $b )
           . ps_doc_title('FORFEITURE NOTICE', 'Date: ' . date('F d, Y'))
           . ps_divider()
           . "<div style='display:flex;justify-content:space-between;margin-bottom:5px;font-size:9.5px;'>"
           . "<span>Ticket No.: <strong>" . esc_html($loan->ticket_number) . "</strong></span>"
           . "<span>Status: <strong>FORFEITED</strong></span></div>"
           . ps_pawner_block( $loan )
           . ps_divider()
           . "<div style='font-size:9px;font-weight:700;text-transform:uppercase;margin:4px 0;text-decoration:underline;'>Forfeited Collateral</div>"
           . ps_cf_line('Description', esc_html($loan->collateral_desc))
           . ps_cf_line('Category', ucfirst(esc_html($loan->category)))
           . ($loan->karat ? ps_cf_line('Karat / Weight', esc_html($loan->karat . ' / ' . $loan->weight_grams . ' GMS.')) : '')
           . ps_cf_line('Condition', ucfirst(esc_html($loan->item_condition)))
           . ps_divider()
           . ps_cf_line('Appraised Value',   'P ' . number_format($loan->appraised_value, 2))
           . ps_cf_line('Principal Amount',  'P ' . number_format($loan->principal, 2))
           . ps_cf_line('Loan Date',         date('F d, Y', strtotime($loan->loan_date)))
           . ps_cf_line('Original Due Date', date('F d, Y', strtotime($loan->due_date)))
           . ps_divider()
           . "<div style='font-size:9px;line-height:1.7;margin-top:6px;border-left:3px solid #000;padding-left:7px;'>"
           . "<strong>NOTICE:</strong> The above-described collateral has been declared forfeited due to non-redemption "
           . "past the grace period as stipulated in the Pawn Ticket. The pawner was duly notified per terms of the "
           . "pawn agreement. This document serves as the official notice of forfeiture per BSP regulations.</div>"
           . ps_sig_footer(['Authorized Signatory', 'Witness', 'Date'])
           . ps_footer_line($b['footer']);
 
    return ps_wrap_page( $body );
}
 
 
// ============================================================
// ⑤ PAYMENT RECEIPT
// ============================================================
function ps_doc_payment_receipt( $loan, array $b ): string {
    global $wpdb;
    $pay = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_payments WHERE loan_id=%d ORDER BY created_at DESC LIMIT 1",
        $loan->id
    ) );
 
    $body  = ps_corp_header( $b )
           . ps_doc_title('PAYMENT RECEIPT', date('F d, Y H:i'))
           . ps_divider()
           . "<div style='font-size:9.5px;margin-bottom:5px;line-height:1.7;'>"
           . "<div>Ticket No. : <strong>" . esc_html($loan->ticket_number) . "</strong></div>"
           . "<div>Customer &nbsp;: <strong>" . esc_html(trim($loan->customer_name)) . "</strong></div>"
           . "<div>Collateral : " . esc_html($loan->collateral_desc) . "</div>"
           . "</div>"
           . ps_divider();
 
    if ( $pay ) {
        $body .= ps_cf_line('Payment Type', ucfirst(str_replace('_', ' ', $pay->payment_type)))
              .  ps_cf_line('Principal',    'P ' . number_format($pay->principal_amount, 2))
              .  ps_cf_line('Interest Paid', number_format($pay->interest_amount, 2))
              .  ($pay->penalty_amount > 0 ? ps_cf_line('Additional Interest', number_format($pay->penalty_amount, 2)) : '')
              .  ($pay->service_fee > 0    ? ps_cf_line('Service Fee', number_format($pay->service_fee, 2)) : '')
              .  ps_cf_total('AMOUNT PAID', 'P ' . number_format($pay->amount, 2))
              .  ps_cf_line('Payment Method', ucfirst(str_replace('_', ' ', $pay->payment_method)), false, 'margin-top:4px;')
              .  ps_cf_line('Date &amp; Time', date('F d, Y H:i', strtotime($pay->created_at)))
              .  ($pay->reference_number ? ps_cf_line('OR Number', esc_html($pay->reference_number)) : '');
    } else {
        $body .= "<div style='text-align:center;padding:8px;font-size:9.5px;'>No payment record found.</div>";
    }
 
    $body .= ps_sig_footer(["Pawner's Signature", 'Teller / Cashier'])
           . ps_footer_line($b['footer']);
 
    return ps_wrap_page( $body );
}
 
 
// ============================================================
// ⑥ CUSTOMER STATEMENT
// ============================================================
function ps_doc_customer_statement( $loan, array $b ): string {
    global $wpdb;
    $all_loans = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_loans WHERE root_ticket=%s AND business_id=%d ORDER BY id ASC",
        $loan->root_ticket, get_current_user_id()
    ) );
    $all_pays  = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.*,l.ticket_number FROM {$wpdb->prefix}ps_payments p
         JOIN {$wpdb->prefix}ps_loans l ON l.id=p.loan_id
         WHERE l.root_ticket=%s AND p.business_id=%d ORDER BY p.created_at ASC",
        $loan->root_ticket, get_current_user_id()
    ) );
    $total_paid = array_sum( array_column($all_pays, 'amount') );
 
    ob_start(); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<?php echo ps_page_style(); echo ps_reg_table_style(); ?>
</head><body><div style="padding:8px;">
    <?php echo ps_corp_header($b); ?>
    <?php echo ps_doc_title('CUSTOMER STATEMENT', 'Date: ' . date('F d, Y')); ?>
    <?php echo ps_divider(); ?>
 
    <div style="font-size:9.5px;margin-bottom:7px;line-height:1.8;">
        <div>Name of Customer : <strong><?php echo esc_html(trim($loan->customer_name)); ?></strong></div>
        <div>Root Ticket No.  : <strong><?php echo esc_html($loan->root_ticket); ?></strong></div>
        <div>Collateral       : <?php echo esc_html($loan->collateral_desc); ?></div>
    </div>
    <?php echo ps_divider(); ?>
 
    <div style="font-size:8.5px;font-weight:700;text-transform:uppercase;margin:3px 0;text-decoration:underline;">Ticket Chain</div>
    <table class="reg" style="margin-bottom:10px;">
        <thead><tr><th>Pawn Tkt No.</th><th>Type</th><th>Loan Date</th><th class="r">Principal</th><th>Due Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($all_loans as $l): ?>
        <tr>
            <td><?php echo esc_html($l->ticket_number); ?></td>
            <td><?php echo ucfirst(str_replace('_',' ',$l->transaction_type)); ?></td>
            <td><?php echo date('M d, Y',strtotime($l->loan_date)); ?></td>
            <td class="r"><?php echo number_format($l->principal,2); ?></td>
            <td><?php echo date('M d, Y',strtotime($l->due_date)); ?></td>
            <td><?php echo ucfirst($l->status); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
 
    <div style="font-size:8.5px;font-weight:700;text-transform:uppercase;margin:3px 0;text-decoration:underline;">Payment History</div>
    <table class="reg">
        <thead><tr><th>Date</th><th>Ticket No.</th><th>OR No.</th><th>Type</th><th class="r">Cash</th><th class="r">Interest</th><th class="r">Addt'l Int.</th><th>Method</th></tr></thead>
        <tbody>
        <?php foreach ($all_pays as $p): ?>
        <tr>
            <td><?php echo date('M d, Y',strtotime($p->created_at)); ?></td>
            <td><?php echo esc_html($p->ticket_number); ?></td>
            <td><?php echo esc_html($p->reference_number ?: '—'); ?></td>
            <td><?php echo ucfirst(str_replace('_',' ',$p->payment_type)); ?></td>
            <td class="r"><?php echo number_format($p->amount,2); ?></td>
            <td class="r"><?php echo number_format($p->interest_amount,2); ?></td>
            <td class="r"><?php echo $p->penalty_amount > 0 ? number_format($p->penalty_amount,2) : '0.00'; ?></td>
            <td><?php echo ucfirst(str_replace('_',' ',$p->payment_method)); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;font-weight:700;">TOTAL: P</td>
                <td class="r"><?php echo number_format($total_paid,2); ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
 
    <?php echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']); ?>
    <?php echo ps_footer_line($b['footer']); ?>
</div></body></html>
<?php return ob_get_clean();
}
 
 
// ============================================================
// ⑦ TICKET CHAIN HISTORY
// ============================================================
function ps_doc_ticket_chain( $loan, array $b ): string {
    global $wpdb;
    $events = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_ticket_history WHERE root_ticket=%s AND business_id=%d ORDER BY created_at ASC",
        $loan->root_ticket, get_current_user_id()
    ) );
 
    ob_start(); ?>
<!DOCTYPE html><html><head><meta charset="UTF-8">
<?php echo ps_page_style(); echo ps_reg_table_style(); ?>
</head><body><div style="padding:8px;">
    <?php echo ps_corp_header($b); ?>
    <?php echo ps_doc_title('TICKET CHAIN HISTORY', 'Root Ticket: ' . esc_html($loan->root_ticket) . '  |  Date: ' . date('F d, Y')); ?>
    <?php echo ps_divider(); ?>
 
    <div style="font-size:9.5px;margin-bottom:6px;">
        Customer: <strong><?php echo esc_html(trim($loan->customer_name)); ?></strong>
        &nbsp;&nbsp; Collateral: <?php echo esc_html($loan->collateral_desc); ?>
    </div>
 
    <table class="reg">
        <thead>
            <tr>
                <th>Date</th><th>Ticket No.</th><th>Event</th>
                <th class="r">Principal Before</th><th class="r">Principal After</th>
                <th class="r">Interest Paid</th><th class="r">Amount Paid</th>
                <th>Due Date</th><th>Notes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
        <tr>
            <td><?php echo date('M d, Y',strtotime($ev->created_at)); ?></td>
            <td><?php echo esc_html($ev->ticket_number); ?></td>
            <td><?php echo ucfirst(str_replace('_',' ',$ev->event_type)); ?></td>
            <td class="r"><?php echo $ev->principal_before > 0 ? number_format($ev->principal_before,2) : '—'; ?></td>
            <td class="r"><?php echo $ev->principal_after  > 0 ? number_format($ev->principal_after, 2) : '—'; ?></td>
            <td class="r"><?php echo $ev->interest_paid    > 0 ? number_format($ev->interest_paid,   2) : '—'; ?></td>
            <td class="r"><?php echo $ev->amount_paid      > 0 ? number_format($ev->amount_paid,     2) : '—'; ?></td>
            <td><?php echo date('M d, Y',strtotime($ev->due_date)); ?></td>
            <td style="max-width:100px;"><?php echo esc_html($ev->notes); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
 
    <?php echo ps_sig_footer(['Prepared by','Checked by','Noted by','Approved by']); ?>
    <?php echo ps_footer_line($b['footer']); ?>
</div></body></html>
<?php return ob_get_clean();
}

// ============================================================
// AJAX: LOG PRINT
// ============================================================

function bntm_ajax_ps_log_print() {
    check_ajax_referer('ps_doc_nonce', 'nonce');
    if (!is_user_logged_in()) { return; }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);
    $doc_type    = sanitize_text_field($_POST['doc_type'] ?? '');
    $copies      = intval($_POST['copies'] ?? 1);

    $wpdb->insert($wpdb->prefix.'ps_document_log', [
        'rand_id'      => bntm_rand_id(),
        'business_id'  => $business_id,
        'loan_id'      => $loan_id ?: null,
        'document_type'=> $doc_type,
        'copies'       => $copies,
        'printed_by'   => $business_id,
    ]);
    wp_send_json_success(['message'=>'Print logged.']);
}

// ============================================================
// AJAX: SAVE SETTINGS
// ============================================================

function bntm_ajax_ps_save_settings() {
    check_ajax_referer('ps_settings_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }

    $settings = [
        'ps_business_name', 'ps_business_address', 'ps_business_contact',
        'ps_business_license', 'ps_business_bsp', 'ps_doc_footer',
        'ps_interest_rate', 'ps_penalty_rate', 'ps_service_fee',
        'ps_grace_period', 'ps_auto_forfeit_days', 'ps_ltv_ratio',
        'ps_ticket_prefix', 'ps_ticket_mode', 'ps_ticket_start',
        'ps_lost_ticket_fee', 'ps_lost_ticket_notice',
    ];
    foreach ($settings as $key) {
        if (isset($_POST[$key])) {
            bntm_save_setting($key, sanitize_text_field($_POST[$key]));
        }
    }
    wp_send_json_success(['message'=>'Settings saved successfully!']);
}

// ============================================================
// AJAX: ADD PAYMENT METHOD
// ============================================================

function bntm_ajax_ps_add_payment_method() {
    check_ajax_referer('ps_settings_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    $name    = sanitize_text_field($_POST['payment_name'] ?? '');
    $type    = sanitize_text_field($_POST['payment_type'] ?? 'cash');
    $acct_n  = sanitize_text_field($_POST['account_name'] ?? '');
    $acct_no = sanitize_text_field($_POST['account_number'] ?? '');

    if (!$name) { wp_send_json_error(['message'=>'Payment method name is required.']); return; }

    $methods = json_decode(bntm_get_setting('ps_payment_methods','[]'), true);
    if (!is_array($methods)) $methods = [];
    $methods[] = ['name'=>$name,'type'=>$type,'account_name'=>$acct_n,'account_number'=>$acct_no];
    bntm_save_setting('ps_payment_methods', json_encode($methods));
    wp_send_json_success(['message'=>'Payment method added!']);
}

// ============================================================
// AJAX: REMOVE PAYMENT METHOD
// ============================================================

function bntm_ajax_ps_remove_payment_method() {
    check_ajax_referer('ps_settings_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    $idx = intval($_POST['index'] ?? -1);

    $methods = json_decode(bntm_get_setting('ps_payment_methods','[]'), true);
    if (!is_array($methods) || !isset($methods[$idx])) {
        wp_send_json_error(['message'=>'Method not found.']); return;
    }
    array_splice($methods, $idx, 1);
    bntm_save_setting('ps_payment_methods', json_encode(array_values($methods)));
    wp_send_json_success(['message'=>'Payment method removed.']);
}

// ============================================================
// AJAX: FINANCE EXPORT
// ============================================================

function bntm_ajax_ps_fn_export_transaction() {
    check_ajax_referer('ps_fn_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $payment_id  = intval($_POST['payment_id'] ?? 0);
    $amount      = floatval($_POST['amount'] ?? 0);

    $fn_table = $wpdb->prefix.'fn_transactions';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$fn_table}'") !== $fn_table) {
        wp_send_json_error('Finance module not available.');
        return;
    }

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$fn_table} WHERE reference_type='ps_payment' AND reference_id=%d", $payment_id));
    if ($exists) { wp_send_json_error('Already exported.'); return; }

    $wpdb->insert($fn_table, [
        'rand_id'        => bntm_rand_id(),
        'business_id'    => $business_id,
        'reference_type' => 'ps_payment',
        'reference_id'   => $payment_id,
        'amount'         => $amount,
        'transaction_type'=> 'income',
        'description'    => 'Pawnshop payment #'.$payment_id,
    ]);
    wp_send_json_success('Exported to Finance module successfully.');
}

function bntm_ajax_ps_fn_revert_transaction() {
    check_ajax_referer('ps_fn_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $fn_table   = $wpdb->prefix.'fn_transactions';

    $wpdb->delete($fn_table, ['reference_type'=>'ps_payment','reference_id'=>$payment_id]);
    wp_send_json_success('Finance entry reverted.');
}

// ============================================================
// HELPER: GENERATE DAILY SUMMARY HTML
// ============================================================

function ps_generate_daily_summary_html($business_id, $date, $bname, $baddr, $bfooter) {
    global $wpdb;
    $lt = $wpdb->prefix.'ps_loans';
    $pt = $wpdb->prefix.'ps_payments';
    $ct = $wpdb->prefix.'ps_customers';

    $new_loans   = $wpdb->get_results($wpdb->prepare("SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS cname FROM {$lt} l JOIN {$ct} c ON c.id=l.customer_id WHERE l.business_id=%d AND l.loan_date=%s AND l.transaction_type='new' ORDER BY l.id ASC", $business_id, $date));
    $renewals    = $wpdb->get_results($wpdb->prepare("SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS cname FROM {$lt} l JOIN {$ct} c ON c.id=l.customer_id WHERE l.business_id=%d AND l.loan_date=%s AND l.transaction_type='renewal' ORDER BY l.id ASC", $business_id, $date));
    $payments    = $wpdb->get_results($wpdb->prepare("SELECT p.*,l.ticket_number,CONCAT(c.last_name,', ',c.first_name) AS cname FROM {$pt} p JOIN {$lt} l ON l.id=p.loan_id JOIN {$ct} c ON c.id=l.customer_id WHERE p.business_id=%d AND DATE(p.created_at)=%s ORDER BY p.created_at ASC", $business_id, $date));
    $redeemed    = $wpdb->get_results($wpdb->prepare("SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS cname FROM {$lt} l JOIN {$ct} c ON c.id=l.customer_id WHERE l.business_id=%d AND DATE(l.redeemed_at)=%s ORDER BY l.redeemed_at ASC", $business_id, $date));

    $total_collections = array_sum(array_map(fn($p)=>$p->amount, $payments));
    $total_interest    = array_sum(array_map(fn($p)=>$p->interest_amount, $payments));
    $total_new_principal = array_sum(array_map(fn($l)=>$l->principal, $new_loans));

    ob_start(); ?>
    <div class="ps-doc-preview">
    <div class="ps-doc-header">
        <div class="ps-doc-title"><?php echo esc_html($bname); ?></div>
        <div class="ps-doc-subtitle"><?php echo esc_html($baddr); ?></div>
        <div class="ps-doc-title" style="font-size:14px;margin-top:8px;">DAILY SUMMARY REPORT</div>
        <div class="ps-doc-subtitle"><?php echo date('F d, Y', strtotime($date)); ?></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px;">
        <div style="text-align:center;padding:10px;border:1px solid #ccc;border-radius:3px;"><div style="font-size:20px;font-weight:900;color:#000;"><?php echo count($new_loans); ?></div><div style="font-size:10px;font-weight:700;color:#555;">NEW LOANS</div></div>
        <div style="text-align:center;padding:10px;border:1px solid #ccc;border-radius:3px;"><div style="font-size:20px;font-weight:900;color:#000;"><?php echo count($renewals); ?></div><div style="font-size:10px;font-weight:700;color:#555;">RENEWALS</div></div>
        <div style="text-align:center;padding:10px;border:1px solid #ccc;border-radius:3px;"><div style="font-size:20px;font-weight:900;color:#000;"><?php echo count($redeemed); ?></div><div style="font-size:10px;font-weight:700;color:#555;">REDEEMED</div></div>
        <div style="text-align:center;padding:10px;border:1px solid #ccc;border-radius:3px;"><div style="font-size:18px;font-weight:900;color:#059669;">₱<?php echo number_format($total_collections,2); ?></div><div style="font-size:10px;font-weight:700;color:#555;">TOTAL COLLECTED</div></div>
    </div>
    <?php if (!empty($new_loans)): ?>
    <div class="ps-doc-section-title">New Pawn Tickets (<?php echo count($new_loans); ?>) — Total Principal: ₱<?php echo number_format($total_new_principal,2); ?></div>
    <table class="ps-doc-amounts"><tr><th>Ticket #</th><th>Customer</th><th>Principal</th><th>Rate</th><th>Due Date</th></tr>
    <?php foreach ($new_loans as $l): ?><tr><td style="font-family:monospace;"><?php echo esc_html($l->ticket_number); ?></td><td><?php echo esc_html($l->cname); ?></td><td>₱<?php echo number_format($l->principal,2); ?></td><td><?php echo $l->interest_rate; ?>%</td><td><?php echo date('M d, Y',strtotime($l->due_date)); ?></td></tr><?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php if (!empty($payments)): ?>
    <div class="ps-doc-section-title">Collections (<?php echo count($payments); ?>) — Interest: ₱<?php echo number_format($total_interest,2); ?></div>
    <table class="ps-doc-amounts"><tr><th>Time</th><th>Ticket #</th><th>Customer</th><th>Type</th><th>Amount</th></tr>
    <?php foreach ($payments as $p): ?><tr><td><?php echo date('H:i',strtotime($p->created_at)); ?></td><td style="font-family:monospace;"><?php echo esc_html($p->ticket_number); ?></td><td><?php echo esc_html($p->cname); ?></td><td style="text-transform:capitalize;"><?php echo str_replace('_',' ',$p->payment_type); ?></td><td>₱<?php echo number_format($p->amount,2); ?></td></tr><?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php if (!empty($redeemed)): ?>
    <div class="ps-doc-section-title">Redeemed (<?php echo count($redeemed); ?>)</div>
    <table class="ps-doc-amounts"><tr><th>Ticket #</th><th>Customer</th><th>Principal</th></tr>
    <?php foreach ($redeemed as $l): ?><tr><td style="font-family:monospace;"><?php echo esc_html($l->ticket_number); ?></td><td><?php echo esc_html($l->cname); ?></td><td>₱<?php echo number_format($l->principal,2); ?></td></tr><?php endforeach; ?>
    </table>
    <?php endif; ?>
    <div style="margin-top:16px;padding-top:10px;border-top:1px solid #ccc;font-size:10px;color:#666;text-align:center;"><?php echo esc_html($bfooter); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// HELPER: SAVE CUSTOMER PHOTO
// ============================================================

function ps_save_customer_photo($base64_data, $business_id, $customer_id) {
    if (empty($base64_data)) return '';

    // Handle URL-encoded base64 (FormData sometimes encodes the + as space or %2B)
    if (strpos($base64_data, 'data:image') !== 0) {
        $base64_data = urldecode($base64_data);
    }
    if (strpos($base64_data, 'data:image') !== 0) return '';

    $upload_dir = WP_CONTENT_DIR . '/ps-customer-photos/';
    if (!file_exists($upload_dir)) {
        wp_mkdir_p($upload_dir);
    }
    // Always rewrite .htaccess to ensure images are accessible
    $htaccess = $upload_dir . '.htaccess';
    $htcontent = "Options -Indexes
# Allow image files
<FilesMatch \.(jpg|jpeg|png|gif|webp)$>
  Allow from all
</FilesMatch>
# Deny PHP execution
<FilesMatch \.php$>
  Deny from all
</FilesMatch>
";
    file_put_contents($htaccess, $htcontent);

    // Strip data URI prefix
    $comma = strpos($base64_data, ',');
    if ($comma === false) return '';
    $raw_b64 = substr($base64_data, $comma + 1);
    // Restore spaces->+ that FormData may have mangled
    $raw_b64 = str_replace(' ', '+', $raw_b64);
    $image_data = base64_decode($raw_b64, true);
    if (!$image_data || strlen($image_data) < 100) return '';

    $max_bytes = 400 * 1024; // 400 KB hard limit

    // Use GD to re-compress and enforce size/dimensions
    if (function_exists('imagecreatefromstring')) {
        $src = @imagecreatefromstring($image_data);
        if ($src) {
            $ow = imagesx($src); $oh = imagesy($src);
            // Resize to max 800x800 keeping aspect ratio
            $max_dim = 800;
            if ($ow > $max_dim || $oh > $max_dim) {
                $ratio = min($max_dim / $ow, $max_dim / $oh);
                $nw = intval($ow * $ratio); $nh = intval($oh * $ratio);
                $dst = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $ow, $oh);
                imagedestroy($src); $src = $dst;
            }
            // Re-encode at quality that fits within 400KB (try 85, 70, 55, 40)
            $qualities = [85, 70, 55, 40];
            $image_data = null;
            foreach ($qualities as $q) {
                ob_start();
                imagejpeg($src, null, $q);
                $candidate = ob_get_clean();
                if (strlen($candidate) <= $max_bytes) { $image_data = $candidate; break; }
            }
            if (!$image_data) { ob_start(); imagejpeg($src, null, 40); $image_data = ob_get_clean(); }
            imagedestroy($src);
        }
    }

    // Final size guard
    if (strlen($image_data) > $max_bytes) {
        // Truncate is wrong — just reject very large images
        return '';
    }

    $filename = 'cust_' . absint($business_id) . '_' . absint($customer_id) . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    $written  = file_put_contents($filepath, $image_data);
    if ($written === false || $written === 0) return '';

    // Delete old photos for same customer (keep storage clean)
    if ($customer_id > 0) {
        $old_files = glob($upload_dir . 'cust_' . absint($business_id) . '_' . absint($customer_id) . '_*.jpg');
        if ($old_files) {
            foreach ($old_files as $old) {
                if (basename($old) !== $filename) @unlink($old);
            }
        }
    }

    return $filename;
}

// ============================================================
// END OF PART 6
// ============================================================

// ============================================================
// AJAX: CREATE LOAN
// ============================================================

function bntm_ajax_ps_create_loan() {
    check_ajax_referer('ps_create_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();

    $customer_id   = intval($_POST['customer_id'] ?? 0);
    $principal     = floatval($_POST['principal'] ?? 0);
    $appraised_value = floatval($_POST['collateral_appraised_value'] ?? 0);
    $ltv_percentage  = floatval($_POST['ltv_percentage'] ?? bntm_get_setting('ps_ltv_ratio', '80'));
    $interest_rate = floatval($_POST['interest_rate'] ?? 0);
    $service_fee   = floatval($_POST['service_fee'] ?? 0);
    $term_months   = intval($_POST['term_months'] ?? 1);
    $loan_date     = sanitize_text_field($_POST['loan_date'] ?? date('Y-m-d'));
    $due_date      = sanitize_text_field($_POST['due_date'] ?? '');
    $payment_method= sanitize_text_field($_POST['payment_method'] ?? 'cash');
    $notes         = sanitize_textarea_field($_POST['notes'] ?? '');
    $grace_days    = (int)bntm_get_setting('ps_grace_period', '0');

    if ($principal <= 0 && $appraised_value > 0 && $ltv_percentage > 0) {
        $principal = round(($appraised_value * $ltv_percentage) / 100, 2);
    }

    if (!$customer_id || $principal <= 0 || !$due_date) {
        wp_send_json_error(['message' => 'Required fields missing.']); return;
    }

    $flag = $wpdb->get_var($wpdb->prepare(
        "SELECT customer_flag FROM {$wpdb->prefix}ps_customers WHERE id=%d AND business_id=%d",
        $customer_id, $business_id
    ));
    if ($flag === 'blacklisted') {
        wp_send_json_error(['message' => 'Blacklisted customer cannot create new loans.']); return;
    }

    $ticket_number = ps_generate_ticket_number($business_id);
    $root_ticket   = $ticket_number;

    $col_data = [
        'rand_id'        => bntm_rand_id(),
        'business_id'    => $business_id,
        'loan_id'        => 0,
        'category'       => sanitize_text_field($_POST['collateral_category'] ?? 'others'),
        'description'    => sanitize_textarea_field($_POST['collateral_description'] ?? ''),
        'brand'          => sanitize_text_field($_POST['collateral_brand'] ?? ''),
        'model'          => sanitize_text_field($_POST['collateral_model'] ?? ''),
        'serial_number'  => sanitize_text_field($_POST['collateral_serial'] ?? ''),
        'metal_type'     => sanitize_text_field($_POST['collateral_metal'] ?? ''),
        'weight_grams'   => floatval($_POST['collateral_weight'] ?? 0),
        'karat'          => sanitize_text_field($_POST['collateral_karat'] ?? ''),
        'item_condition' => sanitize_text_field($_POST['collateral_condition'] ?? 'good'),
        'appraised_value'=> $appraised_value,
        'status'         => 'pawned',
    ];

    $wpdb->query('START TRANSACTION');

    $cr = $wpdb->insert($wpdb->prefix.'ps_collaterals', $col_data);
    if (!$cr) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>'Failed to save collateral.']); return; }
    $collateral_id = $wpdb->insert_id;

    $penalty_rate = floatval(bntm_get_setting('ps_penalty_rate', '1.00'));
    $lr = $wpdb->insert($wpdb->prefix.'ps_loans', [
        'rand_id'          => bntm_rand_id(),
        'business_id'      => $business_id,
        'root_ticket'      => $root_ticket,
        'ticket_number'    => $ticket_number,
        'parent_loan_id'   => 0,
        'customer_id'      => $customer_id,
        'collateral_id'    => $collateral_id,
        'principal'        => $principal,
        'interest_rate'    => $interest_rate,
        'service_fee'      => $service_fee,
        'penalty_rate'     => $penalty_rate,
        'loan_date'        => $loan_date,
        'due_date'         => $due_date,
        'grace_days'       => $grace_days,
        'term_months'      => $term_months,
        'transaction_type' => 'new',
        'status'           => 'active',
        'payment_method'   => $payment_method,
        'notes'            => $notes,
    ]);

    if (!$lr) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>'Failed to create loan.']); return; }
    $loan_id = $wpdb->insert_id;

    $wpdb->update($wpdb->prefix.'ps_collaterals', ['loan_id'=>$loan_id], ['id'=>$collateral_id]);

    if ($service_fee > 0) {
        $wpdb->insert($wpdb->prefix.'ps_payments', [
            'rand_id'=>bntm_rand_id(),'business_id'=>$business_id,'loan_id'=>$loan_id,
            'payment_type'=>'service_fee','amount'=>$service_fee,'service_fee'=>$service_fee,
            'payment_method'=>$payment_method,'processed_by'=>$business_id,
        ]);
    }

    ps_log_ticket_event([
        'business_id'     => $business_id,
        'root_ticket'     => $root_ticket,
        'loan_id'         => $loan_id,
        'ticket_number'   => $ticket_number,
        'event_type'      => 'created',
        'principal_before'=> 0,
        'principal_after' => $principal,
        'due_date'        => $due_date,
        'notes'           => 'New pawn loan created',
    ]);

    $wpdb->query('COMMIT');
    wp_send_json_success(['message'=>"Pawn ticket {$ticket_number} created!",'loan_id'=>$loan_id,'ticket_number'=>$ticket_number]);
}

// ============================================================
// AJAX: RENEW LOAN
// ============================================================

function bntm_ajax_ps_renew_loan() {
    check_ajax_referer('ps_renew_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id      = get_current_user_id();
    $loan_id          = intval($_POST['loan_id'] ?? 0);
    $transaction_type = sanitize_text_field($_POST['transaction_type'] ?? 'interest_payment');
    $add_months       = intval($_POST['additional_months'] ?? 1);
    $renewal_fee      = floatval($_POST['renewal_fee'] ?? 0);
    $principal_adj    = floatval($_POST['principal_adjustment'] ?? 0);
    $payment_method   = sanitize_text_field($_POST['payment_method'] ?? 'cash');
    $reference_number = sanitize_text_field($_POST['reference_number'] ?? '');
    $notes            = sanitize_textarea_field($_POST['notes'] ?? '');
    $is_lost_ticket   = !empty($_POST['is_lost_ticket']);
    $extra_fees_raw   = sanitize_text_field($_POST['extra_fees'] ?? '[]');
    $extra_fees       = json_decode($extra_fees_raw, true) ?: [];
    $lost_ticket_fee  = $is_lost_ticket ? (float)bntm_get_setting('ps_lost_ticket_fee','100.00') : 0;
    $extra_total      = array_sum(array_column($extra_fees, 'amt'));

    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_loans WHERE id=%d AND business_id=%d AND status IN ('active','renewed','overdue')",
        $loan_id, $business_id
    ));
    if (!$loan) { wp_send_json_error(['message'=>'Loan not found or cannot be processed.']); return; }

    $breakdown  = ps_compute_interest_breakdown($loan);
    $interest_due = $breakdown['total_interest'];
    $penalty_due  = $breakdown['penalty_interest'];

    $new_principal = (float)$loan->principal;
    $add_to_principal = 0;
    $reduce_principal = 0;
    if ($transaction_type === 'add_principal') {
        $add_to_principal = $principal_adj;
        $new_principal += $principal_adj;
    } elseif ($transaction_type === 'reduce_principal') {
        $reduce_principal = min($principal_adj, $new_principal);
        $new_principal = max(0, $new_principal - $reduce_principal);
    }

    $base_due = ($transaction_type === 'renewal')
        ? date('Y-m-d', strtotime($loan->due_date." +{$add_months} months"))
        : $loan->due_date;

    $total_paid = 0; $event_type = 'payment'; $pay_type = 'interest';
    if ($transaction_type === 'interest_payment') {
        $total_paid = round($interest_due, 2); $pay_type = 'interest'; $event_type = 'payment';
    } elseif ($transaction_type === 'renewal') {
        $total_paid = round($interest_due + $renewal_fee, 2); $pay_type = 'renewal'; $event_type = 'renewed';
    } elseif ($transaction_type === 'add_principal') {
        $total_paid = round($interest_due, 2); $pay_type = 'interest'; $event_type = 'additional_principal';
    } elseif ($transaction_type === 'reduce_principal') {
        $total_paid = round($interest_due + $reduce_principal, 2); $pay_type = 'partial_principal'; $event_type = 'reduced_principal';
    }

    $grace_days = (int)bntm_get_setting('ps_grace_period', '0');
    $wpdb->query('START TRANSACTION');

    $wpdb->insert($wpdb->prefix.'ps_payments', [
        'rand_id'=>bntm_rand_id(),'business_id'=>$business_id,'loan_id'=>$loan_id,
        'payment_type'=>$pay_type,'amount'=>$total_paid,'interest_amount'=>round($interest_due,2),
        'penalty_amount'=>round($penalty_due,2),'principal_amount'=>round($reduce_principal,2),
        'service_fee'=>round($renewal_fee,2),'days_accrued'=>$breakdown['days_elapsed'],
        'payment_method'=>$payment_method,'reference_number'=>$reference_number,'processed_by'=>$business_id,'notes'=>$notes,
    ]);

    $wpdb->update($wpdb->prefix.'ps_loans', ['status'=>'renewed'], ['id'=>$loan_id]);
    $new_ticket = ps_generate_ticket_number($business_id, $loan->root_ticket);

    $new_lr = $wpdb->insert($wpdb->prefix.'ps_loans', [
        'rand_id'=>bntm_rand_id(),'business_id'=>$business_id,'root_ticket'=>$loan->root_ticket,
        'ticket_number'=>$new_ticket,'parent_loan_id'=>$loan_id,'customer_id'=>$loan->customer_id,
        'collateral_id'=>$loan->collateral_id,'principal'=>round($new_principal,2),
        'interest_rate'=>$loan->interest_rate,'service_fee'=>$transaction_type==='renewal'?$renewal_fee:0,
        'penalty_rate'=>$loan->penalty_rate,'loan_date'=>date('Y-m-d'),'due_date'=>$base_due,
        'grace_days'=>$grace_days,'term_months'=>$transaction_type==='renewal'?$add_months:$loan->term_months,
        'transaction_type'=>$transaction_type==='renewal'?'renewal':($transaction_type==='add_principal'?'additional':'partial_payment'),
        'additional_to_principal'=>round($add_to_principal,2),'reduction_from_principal'=>round($reduce_principal,2),
        'accrued_interest_carried'=>0,'status'=>'active','payment_method'=>$payment_method,'notes'=>$notes,
    ]);

    if (!$new_lr) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>'Failed to create new ticket.']); return; }
    $new_loan_id = $wpdb->insert_id;

    ps_log_ticket_event([
        'business_id'=>$business_id,'root_ticket'=>$loan->root_ticket,'loan_id'=>$new_loan_id,
        'ticket_number'=>$new_ticket,'event_type'=>$event_type,'principal_before'=>$loan->principal,
        'principal_after'=>$new_principal,'interest_paid'=>round($interest_due,2),'amount_paid'=>$total_paid,
        'due_date'=>$base_due,'notes'=>ucwords(str_replace('_',' ',$transaction_type)).'. Paid: ₱'.number_format($total_paid,2),
    ]);

    // Log extra fees as separate payment notes
    if (!empty($extra_fees) || $lost_ticket_fee > 0) {
        $extra_notes = [];
        if ($lost_ticket_fee > 0) $extra_notes[] = 'Affidavit of Loss: ₱'.number_format($lost_ticket_fee,2);
        foreach ($extra_fees as $ef) { if (!empty($ef['desc']) && $ef['amt'] > 0) $extra_notes[] = $ef['desc'].': ₱'.number_format($ef['amt'],2); }
        if ($extra_notes) {
            $wpdb->insert($wpdb->prefix.'ps_payments',[
                'rand_id'=>bntm_rand_id(),'business_id'=>$business_id,'loan_id'=>$new_loan_id,
                'payment_type'=>'service_fee','amount'=>$lost_ticket_fee+$extra_total,
                'service_fee'=>$lost_ticket_fee,'interest_amount'=>0,'principal_amount'=>0,'penalty_amount'=>0,
                'days_accrued'=>0,'payment_method'=>$payment_method,'reference_number'=>$reference_number,
                'processed_by'=>$business_id,'notes'=>implode('; ',$extra_notes),'status'=>'completed',
            ]);
        }
    }
    // Mark loan as lost if applicable
    if ($is_lost_ticket) {
        $wpdb->update($wpdb->prefix.'ps_loans',['notes'=>trim($loan->notes.' [LOST TICKET]')],['id'=>$new_loan_id]);
    }

    $wpdb->query('COMMIT');
    wp_send_json_success([
        'message'=>"New ticket {$new_ticket} issued. Paid: ₱".number_format($total_paid,2),
        'loan_id'=>$new_loan_id,'ticket_number'=>$new_ticket,'is_lost'=>$is_lost_ticket,
    ]);
}

// ============================================================
// AJAX: REDEEM LOAN
// ============================================================

function bntm_ajax_ps_redeem_loan() {
    check_ajax_referer('ps_redeem_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id      = get_current_user_id();
    $loan_id          = intval($_POST['loan_id'] ?? 0);
    $payment_method   = sanitize_text_field($_POST['payment_method'] ?? 'cash');
    $notes            = sanitize_textarea_field($_POST['notes'] ?? '');
    $is_lost_ticket   = !empty($_POST['is_lost_ticket']);
    $extra_fees_raw   = sanitize_text_field($_POST['extra_fees'] ?? '[]');
    $extra_fees       = json_decode($extra_fees_raw, true) ?: [];
    $lost_ticket_fee  = $is_lost_ticket ? (float)bntm_get_setting('ps_lost_ticket_fee','100.00') : 0;
    $extra_total      = array_sum(array_column($extra_fees, 'amt'));

    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_loans WHERE id=%d AND business_id=%d AND status IN ('active','renewed','overdue')",
        $loan_id, $business_id
    ));
    if (!$loan) { wp_send_json_error(['message'=>'Loan not found or cannot be redeemed.']); return; }

    $breakdown = ps_compute_interest_breakdown($loan);
    $total_due = $breakdown['total_due'] + $lost_ticket_fee + $extra_total;
    $interest  = $breakdown['total_interest'];
    $penalty   = $breakdown['penalty_interest'];

    $wpdb->query('START TRANSACTION');

    $wpdb->insert($wpdb->prefix.'ps_payments', [
        'rand_id'=>bntm_rand_id(),'business_id'=>$business_id,'loan_id'=>$loan_id,
        'payment_type'=>'redemption','amount'=>round($total_due,2),'interest_amount'=>round($interest,2),
        'principal_amount'=>(float)$loan->principal,'penalty_amount'=>round($penalty,2),
        'service_fee'=>(float)$loan->service_fee + $lost_ticket_fee + $extra_total,
        'days_accrued'=>$breakdown['days_elapsed'],'payment_method'=>$payment_method,
        'processed_by'=>$business_id,'notes'=>$notes,'status'=>'completed',
    ]);

    // Extra fees/lost ticket note
    if (!empty($extra_fees) || $lost_ticket_fee > 0) {
        $extra_notes = [];
        if ($lost_ticket_fee > 0) $extra_notes[] = 'Affidavit of Loss: ₱'.number_format($lost_ticket_fee,2);
        foreach ($extra_fees as $ef) { if (!empty($ef['desc']) && $ef['amt'] > 0) $extra_notes[] = $ef['desc'].': ₱'.number_format($ef['amt'],2); }
    }

    $loan_notes = $is_lost_ticket ? trim($loan->notes.' [LOST TICKET]') : $loan->notes;
    $wpdb->update($wpdb->prefix.'ps_loans', ['status'=>'redeemed','redeemed_at'=>current_time('mysql'),'notes'=>$loan_notes], ['id'=>$loan_id]);
    $wpdb->update($wpdb->prefix.'ps_collaterals', ['status'=>'redeemed'], ['loan_id'=>$loan_id,'business_id'=>$business_id]);

    ps_log_ticket_event([
        'business_id'=>$business_id,'root_ticket'=>$loan->root_ticket,'loan_id'=>$loan_id,
        'ticket_number'=>$loan->ticket_number,'event_type'=>'redeemed','principal_before'=>$loan->principal,
        'principal_after'=>0,'interest_paid'=>round($interest,2),'amount_paid'=>round($total_due,2),
        'due_date'=>$loan->due_date,'notes'=>'Loan redeemed. Total paid: ₱'.number_format($total_due,2).($is_lost_ticket?' [LOST TICKET]':''),
    ]);

    $wpdb->query('COMMIT');
    wp_send_json_success(['message'=>'Loan redeemed. Total: ₱'.number_format($total_due,2),'loan_id'=>$loan_id,'is_lost'=>$is_lost_ticket]);
}

// ============================================================
// AJAX: GENERATE AUCTION NOTICE
// ============================================================

function bntm_ajax_ps_generate_auction_notice() {
    check_ajax_referer('ps_collat_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $ids_raw = sanitize_text_field($_POST['collateral_ids'] ?? '');
    $ids = array_filter(array_map('intval', explode(',', $ids_raw)));
    if (empty($ids)) { wp_send_json_error(['message'=>'No items selected.']); return; }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $collaterals = $wpdb->get_results($wpdb->prepare(
        "SELECT col.*,l.ticket_number,l.root_ticket,l.loan_date,l.due_date,l.forfeited_at,
                CONCAT(c.last_name,', ',c.first_name) AS customer_name, c.address AS customer_address, c.contact_number
         FROM {$wpdb->prefix}ps_collaterals col
         LEFT JOIN {$wpdb->prefix}ps_loans l ON l.id=col.loan_id
         LEFT JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         WHERE col.id IN ($placeholders) AND col.business_id=%d
         ORDER BY col.category, col.description",
        array_merge($ids, [$business_id])
    ));
    if (empty($collaterals)) { wp_send_json_error(['message'=>'No matching items found.']); return; }

    $bname=$b=bntm_get_setting('ps_business_name','PAWNSHOP');
    $baddr=bntm_get_setting('ps_business_address','');
    $bcon=bntm_get_setting('ps_business_contact','');
    $blicens=bntm_get_setting('ps_business_license','');
    $bbsp=bntm_get_setting('ps_business_bsp','');
    $bfooter=bntm_get_setting('ps_doc_footer','This document is computer-generated and valid without signature.');
    $today=date('F d, Y');
    ob_start(); ?>
<style>
body{font-family:Arial,sans-serif;font-size:12px;color:#000;margin:0;padding:0;}
.page{padding:32px 40px;max-width:750px;margin:0 auto;}
.doc-header{border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:flex-start;}
.biz-name{font-size:18px;font-weight:800;text-transform:uppercase;letter-spacing:1px;}
.biz-sub{font-size:10px;color:#444;margin-top:2px;}
.doc-title{font-size:15px;font-weight:800;text-align:center;text-transform:uppercase;letter-spacing:2px;margin:18px 0 4px;}
.doc-subtitle{font-size:11px;text-align:center;color:#444;margin-bottom:18px;}
table{width:100%;border-collapse:collapse;margin-bottom:14px;}
th{background:#000;color:#fff;padding:6px 8px;text-align:left;font-size:10px;text-transform:uppercase;}
td{padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:11px;vertical-align:top;}
.doc-footer{font-size:10px;color:#666;border-top:1px solid #ccc;padding-top:8px;margin-top:20px;text-align:center;}
.notice-body{font-size:12px;line-height:1.7;margin-bottom:16px;}
</style>
<?php foreach ($collaterals as $i => $col): ?>
<div class="page" style="<?php echo $i>0?'page-break-before:always;':''; ?>">
    <div class="doc-header">
        <div>
            <div class="biz-name"><?php echo esc_html($bname); ?></div>
            <div class="biz-sub"><?php echo esc_html($baddr); ?></div>
            <div class="biz-sub">Tel: <?php echo esc_html($bcon); ?><?php if ($blicens): ?> &nbsp;|&nbsp; DTI/SEC: <?php echo esc_html($blicens); ?><?php endif; ?></div>
            <?php if ($bbsp): ?><div class="biz-sub">BSP Cert: <?php echo esc_html($bbsp); ?></div><?php endif; ?>
        </div>
        <div style="text-align:right;">
            <div style="font-size:11px;">Date: <?php echo $today; ?></div>
            <div style="font-size:10px;color:#888;margin-top:3px;">Ref: <?php echo esc_html($col->ticket_number ?? 'N/A'); ?></div>
        </div>
    </div>
    <div class="doc-title">Notice of Public Auction</div>
    <div class="doc-subtitle">Pursuant to the Pawnshop Regulation Act (PD 114) and BSP Regulations</div>
    <div class="notice-body">Notice is hereby given that <strong><?php echo esc_html($bname); ?></strong> will offer the following forfeited collateral item for public auction. The item has been forfeited in accordance with the terms of the pawn ticket and applicable regulations.</div>
    <table>
        <thead><tr><th>Description</th><th>Category</th><th>Condition</th><th>Details</th><th>Appraised Value</th></tr></thead>
        <tbody>
            <tr>
                <td style="font-weight:700;"><?php echo esc_html($col->description); ?><?php if ($col->brand): ?><br><span style="font-weight:400;color:#444;"><?php echo esc_html($col->brand.($col->model?' '.$col->model:'')); ?></span><?php endif; ?></td>
                <td style="text-transform:capitalize;"><?php echo esc_html($col->category); ?></td>
                <td style="text-transform:capitalize;"><?php echo esc_html($col->item_condition); ?></td>
                <td><?php echo esc_html(implode(' ', array_filter([$col->karat, $col->weight_grams>0?$col->weight_grams.'g':'', $col->serial_number]))); ?></td>
                <td style="font-weight:700;">&#8369;<?php echo number_format($col->appraised_value,2); ?></td>
            </tr>
        </tbody>
    </table>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;font-size:11px;">
        <div><div style="font-weight:700;font-size:10px;text-transform:uppercase;color:#666;margin-bottom:6px;">Pledgor</div>
            <div><strong>Name:</strong> <?php echo esc_html($col->customer_name ?? 'N/A'); ?></div>
            <div><strong>Address:</strong> <?php echo esc_html($col->customer_address ?? 'N/A'); ?></div>
            <div><strong>Contact:</strong> <?php echo esc_html($col->contact_number ?? 'N/A'); ?></div>
        </div>
        <div><div style="font-weight:700;font-size:10px;text-transform:uppercase;color:#666;margin-bottom:6px;">Pawn Details</div>
            <div><strong>Ticket #:</strong> <?php echo esc_html($col->ticket_number ?? 'N/A'); ?></div>
            <div><strong>Loan Date:</strong> <?php echo $col->loan_date ? date('M d, Y',strtotime($col->loan_date)) : 'N/A'; ?></div>
            <div><strong>Maturity:</strong> <?php echo $col->due_date ? date('M d, Y',strtotime($col->due_date)) : 'N/A'; ?></div>
            <div><strong>Forfeited:</strong> <?php echo $col->forfeited_at ? date('M d, Y',strtotime($col->forfeited_at)) : 'N/A'; ?></div>
        </div>
    </div>
    <div style="font-size:11px;line-height:1.7;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:12px;margin-bottom:20px;">
        The pledgor may redeem this item by settling all outstanding obligations <strong>before the auction date</strong>. Failure to do so will result in transfer of ownership to the highest bidder. Contact <?php echo esc_html($bname); ?> at <?php echo esc_html($bcon); ?> for inquiries.
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:24px;">
        <div style="border-top:1px solid #000;padding-top:6px;font-size:11px;"><div>Authorized Signatory</div><div style="color:#666;"><?php echo esc_html($bname); ?></div></div>
        <div style="border-top:1px solid #000;padding-top:6px;font-size:11px;"><div>Acknowledged by / Date</div><div style="color:#666;">___________________________</div></div>
    </div>
    <div class="doc-footer"><?php echo esc_html($bfooter); ?></div>
</div>
<?php endforeach; ?>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(['html'=>$html]);
}

// ============================================================
// AJAX: SEARCH LOANS
// ============================================================

function bntm_ajax_ps_search_loans() {
    check_ajax_referer('ps_search_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $q      = sanitize_text_field($_POST['query'] ?? $_POST['q'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? '');

    $where = "WHERE l.business_id={$business_id}";
    if ($status) $where .= $wpdb->prepare(" AND l.status=%s", $status);
    if ($q) $where .= $wpdb->prepare(
        " AND (l.ticket_number LIKE %s OR l.root_ticket LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s OR col.description LIKE %s)",
        "%{$q}%","%{$q}%","%{$q}%","%{$q}%","%{$q}%");

    $loans = $wpdb->get_results(
        "SELECT l.id,l.ticket_number,l.root_ticket,l.principal,l.status,l.due_date,
                CONCAT(c.last_name,', ',c.first_name) AS customer_name,
                col.description AS collateral_desc, col.category AS collateral_cat
         FROM {$wpdb->prefix}ps_loans l
         JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         LEFT JOIN {$wpdb->prefix}ps_collaterals col ON col.loan_id=l.id
         {$where} ORDER BY FIELD(l.status,'overdue','active','renewed','redeemed','forfeited'),l.id DESC LIMIT 20"
    );
    wp_send_json_success(['loans'=>$loans]);
}

// ============================================================
// AJAX: FLAG CUSTOMER
// ============================================================

function bntm_ajax_ps_flag_customer() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id  = get_current_user_id();
    $customer_id  = intval($_POST['customer_id'] ?? 0);
    $flag         = sanitize_text_field($_POST['flag'] ?? 'normal');
    $valid_flags  = ['normal','vip','delinquent','blacklisted'];
    if (!in_array($flag, $valid_flags)) { wp_send_json_error(['message'=>'Invalid flag.']); return; }

    $r = $wpdb->update($wpdb->prefix.'ps_customers', ['customer_flag'=>$flag], ['id'=>$customer_id,'business_id'=>$business_id]);
    if ($r !== false) wp_send_json_success(['message'=>'Customer flag updated.']);
    else wp_send_json_error(['message'=>'Update failed.']);
}

// ============================================================
// AJAX: GET CUSTOMERS LIST
// ============================================================

function bntm_ajax_ps_get_customers_list() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $q = sanitize_text_field($_POST['q'] ?? '');

    $where = "WHERE c.business_id={$business_id} AND c.status='active'";
    if ($q) $where .= $wpdb->prepare(" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.contact_number LIKE %s)",
        "%{$q}%","%{$q}%","%{$q}%");

    $customers = $wpdb->get_results(
        "SELECT c.*,COUNT(l.id) AS active_loans
         FROM {$wpdb->prefix}ps_customers c
         LEFT JOIN {$wpdb->prefix}ps_loans l ON l.customer_id=c.id AND l.status IN ('active','renewed','overdue')
         {$where} GROUP BY c.id ORDER BY c.last_name,c.first_name LIMIT 100"
    );
    wp_send_json_success(['customers'=>$customers]);
}

// ============================================================
// AJAX: SAVE CUSTOMER PHOTO
// ============================================================

function bntm_ajax_ps_save_customer_photo() {
    check_ajax_referer('ps_customer_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $photo_data  = $_POST['photo_data'] ?? ''; // raw: base64 data URI — validated by ps_save_customer_photo

    if (!$customer_id || !$photo_data) { wp_send_json_error(['message'=>'Missing data.']); return; }
    $filename = ps_save_customer_photo($photo_data, $business_id, $customer_id);
    if (!$filename) { wp_send_json_error(['message'=>'Failed to save photo.']); return; }

    global $wpdb;
    $wpdb->update($wpdb->prefix.'ps_customers', ['photo_path'=>$filename], ['id'=>$customer_id]);
    wp_send_json_success(['filename'=>$filename]);
}

// ============================================================
// AJAX: ADD COLLATERAL
// ============================================================

function bntm_ajax_ps_add_collateral() {
    check_ajax_referer('ps_collat_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    if (!$loan_id) { wp_send_json_error(['message'=>'Loan ID required.']); return; }

    $r = $wpdb->insert($wpdb->prefix.'ps_collaterals', [
        'rand_id'        => bntm_rand_id(),
        'business_id'    => $business_id,
        'loan_id'        => $loan_id,
        'category'       => sanitize_text_field($_POST['category'] ?? 'others'),
        'description'    => sanitize_textarea_field($_POST['description'] ?? ''),
        'brand'          => sanitize_text_field($_POST['brand'] ?? ''),
        'model'          => sanitize_text_field($_POST['model'] ?? ''),
        'serial_number'  => sanitize_text_field($_POST['serial_number'] ?? ''),
        'item_condition' => sanitize_text_field($_POST['item_condition'] ?? 'good'),
        'appraised_value'=> floatval($_POST['appraised_value'] ?? 0),
        'status'         => 'pawned',
    ]);
    if ($r) wp_send_json_success(['message'=>'Collateral added.','id'=>$wpdb->insert_id]);
    else wp_send_json_error(['message'=>'Failed to add collateral.']);
}

// ============================================================
// AJAX: GET COLLATERAL DETAIL
// ============================================================

function bntm_ajax_ps_get_collateral_detail() {
    check_ajax_referer('ps_collat_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id   = get_current_user_id();
    $collateral_id = intval($_POST['collateral_id'] ?? 0);

    $col = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_collaterals WHERE id=%d AND business_id=%d",
        $collateral_id, $business_id
    ));
    if (!$col) { wp_send_json_error(['message'=>'Not found.']); return; }
    wp_send_json_success(['collateral'=>$col]);
}

// ============================================================
// AJAX: RECORD PAYMENT
// ============================================================

function bntm_ajax_ps_record_payment() {
    check_ajax_referer('ps_payment_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id    = get_current_user_id();
    $loan_id        = intval($_POST['loan_id'] ?? 0);
    $amount         = floatval($_POST['amount'] ?? 0);
    $payment_type   = sanitize_text_field($_POST['payment_type'] ?? 'interest');
    $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'cash');
    $notes          = sanitize_textarea_field($_POST['notes'] ?? '');

    if (!$loan_id || $amount <= 0) { wp_send_json_error(['message'=>'Invalid payment data.']); return; }

    $r = $wpdb->insert($wpdb->prefix.'ps_payments', [
        'rand_id'=>bntm_rand_id(),'business_id'=>$business_id,'loan_id'=>$loan_id,
        'payment_type'=>$payment_type,'amount'=>$amount,'payment_method'=>$payment_method,
        'processed_by'=>$business_id,'notes'=>$notes,
    ]);
    if ($r) wp_send_json_success(['message'=>'Payment recorded.']);
    else wp_send_json_error(['message'=>'Failed to record payment.']);
}

// ============================================================
// AJAX: GET PAYMENT HISTORY
// ============================================================

function bntm_ajax_ps_get_payment_history() {
    check_ajax_referer('ps_payment_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    $payments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ps_payments WHERE loan_id=%d AND business_id=%d ORDER BY created_at DESC",
        $loan_id, $business_id
    ));
    wp_send_json_success(['payments'=>$payments]);
}

// ============================================================
// AJAX: GET DOCUMENT HISTORY
// ============================================================

function bntm_ajax_ps_get_document_history() {
    check_ajax_referer('ps_doc_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $limit = intval($_POST['limit'] ?? 20);

    $docs = $wpdb->get_results($wpdb->prepare(
        "SELECT d.*,l.ticket_number FROM {$wpdb->prefix}ps_document_log d
         LEFT JOIN {$wpdb->prefix}ps_loans l ON l.id=d.loan_id
         WHERE d.business_id=%d ORDER BY d.created_at DESC LIMIT %d",
        $business_id, $limit
    ));
    wp_send_json_success(['documents'=>$docs]);
}

// ============================================================
// AJAX: QUICK UPDATE STATUS
// ============================================================

function bntm_ajax_ps_quick_update_status() {
    check_ajax_referer('ps_loan_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);
    $new_status  = sanitize_text_field($_POST['status'] ?? '');
    $valid = ['active','overdue','renewed','redeemed','forfeited'];

    if (!$loan_id || !in_array($new_status, $valid)) { wp_send_json_error(['message'=>'Invalid.']); return; }

    $r = $wpdb->update($wpdb->prefix.'ps_loans', ['status'=>$new_status], ['id'=>$loan_id,'business_id'=>$business_id]);
    if ($r !== false) wp_send_json_success(['message'=>'Status updated.']);
    else wp_send_json_error(['message'=>'Update failed.']);
}

// ============================================================
// AJAX: BULK MARK OVERDUE
// ============================================================

function bntm_ajax_ps_bulk_mark_overdue() {
    check_ajax_referer('ps_loan_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();

    $count = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->prefix}ps_loans SET status='overdue'
         WHERE business_id=%d AND status IN ('active','renewed') AND due_date < CURDATE()",
        $business_id
    ));
    wp_send_json_success(['message'=>"Marked {$count} loan(s) as overdue.",'count'=>$count]);
}

// ============================================================
// AJAX: DELETE LOAN
// ============================================================

function bntm_ajax_ps_delete_loan() {
    check_ajax_referer('ps_loan_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    // Only allow deleting forfeited/test loans with no payment chain
    $pay_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ps_payments WHERE loan_id=%d", $loan_id
    ));
    if ($pay_count > 0) { wp_send_json_error(['message'=>'Cannot delete loan with payment history.']); return; }

    $wpdb->delete($wpdb->prefix.'ps_loans', ['id'=>$loan_id,'business_id'=>$business_id]);
    wp_send_json_success(['message'=>'Loan deleted.']);
}

// ============================================================
// AJAX: GET LOAN COMPUTE (live interest for display)
// ============================================================

function bntm_ajax_ps_get_loan_compute() {
    check_ajax_referer('ps_payment_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); }
    global $wpdb;
    $business_id = get_current_user_id();
    $loan_id     = intval($_POST['loan_id'] ?? 0);

    $loan = $wpdb->get_row($wpdb->prepare(
        "SELECT l.*,CONCAT(c.last_name,', ',c.first_name) AS customer_name
         FROM {$wpdb->prefix}ps_loans l
         JOIN {$wpdb->prefix}ps_customers c ON c.id=l.customer_id
         WHERE l.id=%d AND l.business_id=%d",
        $loan_id, $business_id
    ));
    if (!$loan) { wp_send_json_error(['message'=>'Loan not found.']); return; }

    $bd = ps_compute_interest_breakdown($loan);
    wp_send_json_success(array_merge($bd, [
        'ticket_number' => $loan->ticket_number,
        'customer_name' => $loan->customer_name,
        'interest_rate' => $loan->interest_rate,
        'due_date'      => $loan->due_date,
        'is_overdue'    => $bd['days_past_due'] > 0,
    ]));
}

// ============================================================
// END OF MODULE
// ============================================================

