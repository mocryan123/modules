<?php
/**
 * Module Name: Ticket Raffle
 * Module Slug: raffle
 * Description: Manage ticket raffles, sell tickets online or manually, and track winners
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: 🎟️
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_RAFFLE_PATH', dirname(__FILE__) . '/');
define('BNTM_RAFFLE_URL', plugin_dir_url(__FILE__));

// ============================================================================
// CORE MODULE FUNCTIONS (Required by Framework)
// ============================================================================
/**
 * Define module pages
 */
function bntm_raffle_get_pages() {
    return [
        'Raffle Dashboard' => '[raffle_dashboard]',
        'Buy Raffle Ticket' => '[raffle_buy_form]',
        'Payment Result' => '[raffle_payment_result]', // Add this
        'Draw Winner' => '[raffle_wheel]', // Add this
    ];
}

/**
 * Register shortcodes
 */
function bntm_raffle_get_shortcodes() {
    return [
        'raffle_dashboard' => 'bntm_shortcode_raffle_dashboard',
        'raffle_buy_form' => 'bntm_shortcode_raffle_buy_form',
        'raffle_wheel' => 'bntm_shortcode_raffle_wheel',
        'raffle_payment_result' => 'bntm_shortcode_raffle_payment_result', // Add this
    ];
}

/**
 * Define database tables
 */
function bntm_raffle_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'raffle_raffles' => "CREATE TABLE {$prefix}raffle_raffles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            ticket_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_tickets INT UNSIGNED NOT NULL DEFAULT 0,
            tickets_sold INT UNSIGNED NOT NULL DEFAULT 0,
            draw_date DATETIME NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            terms TEXT,
            instructions TEXT,
            header_image VARCHAR(500) NULL,
            winner_ticket_number VARCHAR(50) NULL,
            winner_name VARCHAR(255) NULL,
            winner_email VARCHAR(255) NULL,
            winner_phone VARCHAR(50) NULL,
            drawn_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_rand_id (rand_id)
        ) {$charset};",
        
        'raffle_tickets' => "CREATE TABLE {$prefix}raffle_tickets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            raffle_id BIGINT UNSIGNED NOT NULL,
            raffle_rand_id VARCHAR(20) NOT NULL,
            ticket_number VARCHAR(50) NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255),
            customer_phone VARCHAR(50),
            customer_address TEXT,
            purchase_reference VARCHAR(100),
            payment_method VARCHAR(50),
            payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_gateway VARCHAR(50),
            payment_transaction_id VARCHAR(255),
            amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            is_winner TINYINT(1) NOT NULL DEFAULT 0,
            purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_raffle (raffle_id),
            INDEX idx_raffle_rand (raffle_rand_id),
            INDEX idx_business (business_id),
            INDEX idx_ticket_number (ticket_number),
            INDEX idx_payment_status (payment_status),
            INDEX idx_rand_id (rand_id)
        ) {$charset};",
    ];
}

/**
 * Create module tables
 */
function bntm_raffle_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_raffle_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Set default settings
    if (!bntm_get_setting('raffle_default_terms')) {
        bntm_set_setting('raffle_default_terms', 'By purchasing this raffle ticket, you agree to the following terms and conditions:

1. One entry per ticket purchased.
2. Winner will be selected randomly on the draw date.
3. Winner will be notified via email and phone.
4. Prizes are non-transferable and non-refundable.
5. Organizer reserves the right to verify winner\'s identity.
6. Taxes and other fees are the responsibility of the winner.
7. By participating, you consent to the use of your name and photo for promotional purposes.');
    }
    
    if (!bntm_get_setting('raffle_default_instructions')) {
        bntm_set_setting('raffle_default_instructions', 'How to Purchase Raffle Tickets:

1. Select the number of tickets you wish to purchase.
2. Fill in your complete contact information.
3. Choose your payment method.
4. Complete the payment process.
5. You will receive a confirmation email with your ticket numbers.
6. Keep your ticket numbers safe for verification.
7. Winners will be announced on the draw date.

For questions or concerns, please contact us through our official channels.');
    }
    
    return count($tables);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

add_action('wp_ajax_raffle_create_raffle', 'bntm_ajax_raffle_create_raffle');
add_action('wp_ajax_raffle_update_raffle', 'bntm_ajax_raffle_update_raffle');
add_action('wp_ajax_raffle_delete_raffle', 'bntm_ajax_raffle_delete_raffle');
add_action('wp_ajax_raffle_draw_winner', 'bntm_ajax_raffle_draw_winner');
add_action('wp_ajax_raffle_save_payment_source', 'bntm_ajax_raffle_save_payment_source');
add_action('wp_ajax_raffle_add_payment_method', 'bntm_ajax_raffle_add_payment_method');
add_action('wp_ajax_raffle_remove_payment_method', 'bntm_ajax_raffle_remove_payment_method');
add_action('wp_ajax_raffle_save_settings', 'bntm_ajax_raffle_save_settings');
add_action('wp_ajax_raffle_get_tickets', 'bntm_ajax_raffle_get_tickets');
add_action('wp_ajax_raffle_update_ticket_status', 'bntm_ajax_raffle_update_ticket_status');
add_action('wp_ajax_raffle_fn_export_sales', 'bntm_ajax_raffle_fn_export_sales');
add_action('wp_ajax_raffle_fn_revert_sale', 'bntm_ajax_raffle_fn_revert_sale');
add_action('wp_ajax_raffle_generate_soa', 'raffle_ajax_generate_soa');

// Public AJAX handlers
add_action('wp_ajax_raffle_purchase_tickets', 'bntm_ajax_raffle_purchase_tickets');
add_action('wp_ajax_nopriv_raffle_purchase_tickets', 'bntm_ajax_raffle_purchase_tickets');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_raffle_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the raffle dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <style>
    :root {
        --raffle-bg: #f4f7fb;
        --raffle-card: #ffffff;
        --raffle-border: #dbe4f0;
        --raffle-border-strong: #bfd0e4;
        --raffle-text: #132238;
        --raffle-muted: #607085;
        --raffle-primary: #1d4ed8;
        --raffle-primary-soft: #e8f0ff;
        --raffle-success-soft: #dcfce7;
        --raffle-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }
    .bntm-raffle-container {
        background:
            radial-gradient(circle at top left, rgba(29, 78, 216, 0.08), transparent 28%),
            linear-gradient(180deg, #f8fbff 0%, var(--raffle-bg) 100%);
        border: 1px solid var(--raffle-border);
        border-radius: 24px;
        padding: 24px;
        box-shadow: var(--raffle-shadow);
    }
    .bntm-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 22px;
        padding: 10px;
        background: rgba(255,255,255,0.78);
        border: 1px solid var(--raffle-border);
        border-radius: 18px;
        backdrop-filter: blur(10px);
    }
    .bntm-tab {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        padding: 0 16px;
        border-radius: 12px;
        color: var(--raffle-muted);
        text-decoration: none;
        font-weight: 600;
        transition: 0.2s ease;
    }
    .bntm-tab:hover {
        background: #eef4fb;
        color: var(--raffle-text);
    }
    .bntm-tab.active {
        background: linear-gradient(135deg, var(--raffle-primary), #2563eb);
        color: #fff;
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.28);
    }
    .bntm-tab-content {
        display: grid;
        gap: 20px;
    }
    .bntm-form-section {
        background: var(--raffle-card);
        border: 1px solid var(--raffle-border);
        border-radius: 20px;
        padding: 22px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }
    .bntm-form-section h3,
    .bntm-form-section h4 {
        margin-top: 0;
        color: var(--raffle-text);
    }
    .bntm-form-section p {
        color: var(--raffle-muted);
    }
    .raffle-section-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        margin-bottom: 18px;
        padding-bottom: 16px;
        border-bottom: 1px solid #edf2f7;
    }
    .raffle-section-head h3 {
        margin: 0;
    }
    .raffle-section-tools {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }
    .raffle-table-shell {
        overflow-x: auto;
        border: 1px solid #e8edf5;
        border-radius: 16px;
        background: #fff;
    }
    .bntm-table {
        width: 100%;
        min-width: 760px;
        border-collapse: separate;
        border-spacing: 0;
    }
    .bntm-table thead th {
        background: #f8fbff;
        color: #516174;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 14px 16px;
        border-bottom: 1px solid #e8edf5;
    }
    .bntm-table tbody td {
        padding: 14px 16px;
        border-bottom: 1px solid #eef2f7;
        color: var(--raffle-text);
        vertical-align: top;
        background: #fff;
    }
    .bntm-table tbody tr:hover td {
        background: #fbfdff;
    }
    .bntm-table tbody tr:last-child td {
        border-bottom: 0;
    }
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 16px;
    }
    .bntm-stat-card {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid var(--raffle-border);
        border-radius: 18px;
        padding: 20px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px;
        color: var(--raffle-muted);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .bntm-stat-number {
        margin: 0;
        font-size: 30px;
        line-height: 1.1;
        font-weight: 800;
        color: var(--raffle-text);
    }
    .bntm-stat-success {
        color: #047857;
    }
    .bntm-stat-income {
        color: #0f766e;
        font-weight: 700;
    }
    .bntm-input,
    .bntm-form-section input[type="text"],
    .bntm-form-section input[type="email"],
    .bntm-form-section input[type="tel"],
    .bntm-form-section input[type="number"],
    .bntm-form-section input[type="datetime-local"],
    .bntm-form-section select,
    .bntm-form-section textarea {
        width: 100%;
        border: 1px solid var(--raffle-border-strong);
        border-radius: 12px;
        padding: 11px 13px;
        background: #fff;
        color: var(--raffle-text);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .bntm-input:focus,
    .bntm-form-section input:focus,
    .bntm-form-section select:focus,
    .bntm-form-section textarea:focus {
        outline: none;
        border-color: #7aa2ff;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
    }
    .bntm-form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--raffle-text);
        font-weight: 600;
    }
    .bntm-btn-primary,
    .bntm-btn-secondary,
    .bntm-btn-small,
    .bntm-btn-success,
    .bntm-btn-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 14px;
        border-radius: 12px;
        border: 1px solid transparent;
        text-decoration: none;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s ease;
    }
    .bntm-btn-primary,
    .bntm-btn-success {
        background: linear-gradient(135deg, var(--raffle-primary), #2563eb);
        color: #fff;
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.2);
    }
    .bntm-btn-secondary,
    .bntm-btn-small {
        background: #fff;
        color: var(--raffle-text);
        border-color: var(--raffle-border-strong);
    }
    .bntm-btn-danger {
        background: #fff5f5;
        color: #b91c1c;
        border-color: #fecaca;
    }
    .bntm-btn-primary:hover,
    .bntm-btn-secondary:hover,
    .bntm-btn-small:hover,
    .bntm-btn-success:hover,
    .bntm-btn-danger:hover {
        transform: translateY(-1px);
    }
    .bntm-notice {
        border-radius: 14px;
        padding: 14px 16px;
        border: 1px solid var(--raffle-border);
        background: #fff;
    }
    .bntm-notice-success {
        background: #ecfdf5;
        border-color: #bbf7d0;
        color: #166534;
    }
    .bntm-notice-error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }
    /* Modal Styles */
    .raffle-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    
    .raffle-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border-radius: 18px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
    }
    
    .raffle-modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .raffle-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }
    
    .raffle-modal-close {
        font-size: 28px;
        font-weight: bold;
        color: #9ca3af;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
        width: 30px;
        height: 30px;
        line-height: 1;
    }
    
    .raffle-modal-close:hover {
        color: #374151;
    }
    
    .raffle-modal-body {
        padding: 20px;
    }
    
    .raffle-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    @media (max-width: 768px) {
        .bntm-raffle-container {
            padding: 16px;
            border-radius: 18px;
        }
        .raffle-section-head {
            flex-direction: column;
            align-items: stretch;
        }
        .raffle-section-tools {
            width: 100%;
        }
        .summary-grid,
        .detail-grid,
        .bntm-form-row {
            grid-template-columns: 1fr !important;
        }
        .bntm-table {
            min-width: 680px;
        }
    }
    </style>
    
    <div class="bntm-raffle-container">
        <!-- Tab Navigation -->
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=raffles" class="bntm-tab <?php echo $active_tab === 'raffles' ? 'active' : ''; ?>">
                Raffles
            </a>
            <a href="?tab=tickets" class="bntm-tab <?php echo $active_tab === 'tickets' ? 'active' : ''; ?>">
                Tickets
            </a>
            <a href="?tab=receipts" class="bntm-tab <?php echo $active_tab === 'receipts' ? 'active' : ''; ?>">
                Send Receipts
            </a>
            <a href="?tab=payment" class="bntm-tab <?php echo $active_tab === 'payment' ? 'active' : ''; ?>">
                Payment Settings
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
        
        <!-- Tab Content -->
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo raffle_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'raffles'): ?>
                <?php echo raffle_raffles_tab($business_id); ?>
            <?php elseif ($active_tab === 'tickets'): ?>
                <?php echo raffle_tickets_tab($business_id); ?>
            <?php elseif ($active_tab === 'payment'): ?>
                <?php echo raffle_payment_tab($business_id); ?>
            <?php elseif ($active_tab === 'receipts'): ?>
                <?php echo raffle_receipts_tab($business_id); ?>
            <?php elseif ($active_tab === 'finance'): ?>
                <?php echo raffle_finance_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo raffle_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    // Modal functions
    function openRaffleModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }
    
    function closeRaffleModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('raffle-modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Raffle Management', $content);
}

// ============================================================================
// TAB RENDERING FUNCTIONS
// ============================================================================

/**
 * Overview Tab
 */
function raffle_overview_tab($business_id) {
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    // Get statistics
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(DISTINCT r.id) as total_raffles,
            COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.id END) as active_raffles,
            COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_raffles,
            COUNT(t.id) as total_tickets_sold,
            COALESCE(SUM(t.amount_paid), 0) as total_revenue
        FROM {$raffles_table} r
        LEFT JOIN {$tickets_table} t ON r.id = t.raffle_id AND t.payment_status = 'paid'
    ", $business_id));
    
    // Get recent raffles
    $recent_raffles = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, 
            COUNT(t.id) as sold_count,
            COALESCE(SUM(t.amount_paid), 0) as revenue
        FROM {$raffles_table} r
        LEFT JOIN {$tickets_table} t ON r.id = t.raffle_id AND t.payment_status = 'paid'
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ", $business_id));
    
    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Raffles</h3>
            <p class="bntm-stat-number"><?php echo number_format($stats->total_raffles); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Active Raffles</h3>
            <p class="bntm-stat-number bntm-stat-success"><?php echo number_format($stats->active_raffles); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Tickets Sold</h3>
            <p class="bntm-stat-number"><?php echo number_format($stats->total_tickets_sold); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Revenue</h3>
            <p class="bntm-stat-number bntm-stat-income">₱<?php echo number_format($stats->total_revenue, 2); ?></p>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Recent Raffles</h3>
        <?php if (empty($recent_raffles)): ?>
            <p style="color: #6b7280; text-align: center; padding: 40px 0;">
                No raffles created yet. Create your first raffle in the Raffles tab.
            </p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Ticket Price</th>
                        <th>Sold / Total</th>
                        <th>Revenue</th>
                        <th>Draw Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_raffles as $raffle): ?>
                    <tr>
                        <td><strong><?php echo esc_html($raffle->title); ?></strong></td>
                        <td>₱<?php echo number_format($raffle->ticket_price, 2); ?></td>
                        <td><?php echo $raffle->sold_count; ?> / <?php echo $raffle->total_tickets; ?></td>
                        <td class="bntm-stat-income">₱<?php echo number_format($raffle->revenue, 2); ?></td>
                        <td><?php echo $raffle->draw_date ? date('M d, Y', strtotime($raffle->draw_date)) : 'Not set'; ?></td>
                        <td>
                            <span class="bntm-badge bntm-badge-<?php echo $raffle->status; ?>">
                                <?php echo ucfirst($raffle->status); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
    .bntm-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .bntm-badge-active {
        background: #d1fae5;
        color: #065f46;
    }
    .bntm-badge-completed {
        background: #dbeafe;
        color: #1e40af;
    }
    .bntm-badge-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Raffles Management Tab
 */
function raffle_raffles_tab($business_id) {
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $raffles = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, 
            COUNT(t.id) as sold_count,
            COALESCE(SUM(CASE WHEN t.payment_status = 'paid' THEN t.amount_paid ELSE 0 END), 0) as revenue
        FROM {$raffles_table} r
        LEFT JOIN {$tickets_table} t ON r.id = t.raffle_id
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ", $business_id));
    
    $default_terms = bntm_get_setting('raffle_default_terms', '');
    $default_instructions = bntm_get_setting('raffle_default_instructions', '');
    
    $nonce = wp_create_nonce('raffle_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="raffle-section-head">
            <h3>All Raffles</h3>
            <div class="raffle-section-tools">
                <button class="bntm-btn-primary" onclick="openRaffleModal('createRaffleModal')">
                    Create New Raffle
                </button>
            </div>
        </div>
        
        <?php if (empty($raffles)): ?>
            <p style="color: #6b7280; text-align: center; padding: 40px 0;">
                No raffles created yet. Click "Create New Raffle" to get started.
            </p>
        <?php else: ?>
            <div class="raffle-table-shell">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Raffle Details</th>
                        <th>Price</th>
                        <th>Tickets</th>
                        <th>Revenue</th>
                        <th>Draw Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($raffles as $raffle): 
                        $sold_percentage = $raffle->total_tickets > 0 
                            ? ($raffle->sold_count / $raffle->total_tickets) * 100 
                            : 0;
                        $buy_form_url = get_permalink(get_page_by_path('buy-raffle-ticket')) . '?id=' . $raffle->rand_id;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($raffle->title); ?></strong><br>
                            <small style="color: #6b7280;">ID: <?php echo $raffle->rand_id; ?></small>
                        </td>
                        <td>₱<?php echo number_format($raffle->ticket_price, 2); ?></td>
                        <td>
                            <div style="margin-bottom: 4px;">
                                <strong><?php echo $raffle->sold_count; ?></strong> / <?php echo $raffle->total_tickets; ?>
                            </div>
                            <div style="background: #e5e7eb; height: 6px; border-radius: 3px; overflow: hidden;">
                                <div style="background: #3b82f6; height: 100%; width: <?php echo $sold_percentage; ?>%;"></div>
                            </div>
                        </td>
                        <td class="bntm-stat-income">₱<?php echo number_format($raffle->revenue, 2); ?></td>
                        <td>
                            <?php if ($raffle->draw_date): ?>
                                <?php echo date('M d, Y', strtotime($raffle->draw_date)); ?><br>
                                <small><?php echo date('h:i A', strtotime($raffle->draw_date)); ?></small>
                            <?php else: ?>
                                <small style="color: #6b7280;">Not set</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="bntm-badge bntm-badge-<?php echo $raffle->status; ?>">
                                <?php echo ucfirst($raffle->status); ?>
                            </span>
                            <?php if ($raffle->winner_name): ?>
                                <br><small style="color: #059669;">Winner Drawn</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="bntm-btn-small bntm-btn-secondary" 
                                    onclick="editRaffle(<?php echo htmlspecialchars(json_encode($raffle), ENT_QUOTES); ?>)">
                                Edit
                            </button>
                            <button class="bntm-btn-small"
                                    onclick="generateRaffleSOA(<?php echo $raffle->id; ?>)">
                                SOA
                            </button>
                            <?php if ($raffle->status === 'active' && !$raffle->winner_name): ?>
                            <button class="bntm-btn-small bntm-btn-success" 
                                    onclick="drawWinner(<?php echo $raffle->id; ?>, '<?php echo $raffle->title; ?>')">
                                Draw Winner
                            </button>
                            <?php endif; ?>
                            <button class="bntm-btn-small" 
                                    onclick="copyToClipboard('<?php echo esc_js($buy_form_url); ?>')">
                                Copy Form Link
                            </button>
                            <button class="bntm-btn-small bntm-btn-danger" 
                                    onclick="deleteRaffle(<?php echo $raffle->id; ?>, '<?php echo esc_js($raffle->title); ?>')">
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
    
    <!-- Create Raffle Modal -->
    <div id="createRaffleModal" class="raffle-modal">
        <div class="raffle-modal-content">
            <div class="raffle-modal-header">
                <h3>Create New Raffle</h3>
                <button class="raffle-modal-close" onclick="closeRaffleModal('createRaffleModal')">&times;</button>
            </div>
            <div class="raffle-modal-body">
                <form id="createRaffleForm" class="bntm-form">
                    <div class="bntm-form-group">
                        <label>Header Image</label>
                        <input type="file" id="create_header_image" accept="image/*" onchange="previewCreateImage(event)">
                        <input type="hidden" name="header_image" id="create_header_image_data">
                        <div id="create_image_preview" style="margin-top: 10px; display: none;">
                            <img id="create_preview_img" style="max-width: 100%; height: auto; border-radius: 8px;">
                            <button type="button" class="bntm-btn-small bntm-btn-danger" onclick="removeCreateImage()" style="margin-top: 10px;">Remove Image</button>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Raffle Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Summer Grand Raffle 2026">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3" placeholder="Describe the raffle and prizes"></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Ticket Price (₱) *</label>
                            <input type="number" name="ticket_price" step="0.01" min="0" required>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Total Tickets *</label>
                            <input type="number" name="total_tickets" min="1" required>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Draw Date & Time</label>
                        <input type="datetime-local" name="draw_date">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Terms & Conditions</label>
                        <textarea name="terms" rows="6"><?php echo esc_textarea($default_terms); ?></textarea>
                        <small>Default terms can be modified in Settings tab</small>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Purchase Instructions</label>
                        <textarea name="instructions" rows="6"><?php echo esc_textarea($default_instructions); ?></textarea>
                        <small>Default instructions can be modified in Settings tab</small>
                    </div>
                </form>
            </div>
            <div class="raffle-modal-footer">
                <button class="bntm-btn-secondary" onclick="closeRaffleModal('createRaffleModal')">Cancel</button>
                <button class="bntm-btn-primary" onclick="submitCreateRaffle()">Create Raffle</button>
            </div>
        </div>
    </div>
    <!-- Edit Raffle Modal -->
    <div id="editRaffleModal" class="raffle-modal">
        <div class="raffle-modal-content">
            <div class="raffle-modal-header">
                <h3>Edit Raffle</h3>
                <button class="raffle-modal-close" onclick="closeRaffleModal('editRaffleModal')">&times;</button>
            </div>
            <div class="raffle-modal-body">
                <form id="editRaffleForm" class="bntm-form">
                    <input type="hidden" name="raffle_id" id="edit_raffle_id">
                    
                    <div class="bntm-form-group">
                        <label>Header Image</label>
                        <input type="file" id="edit_header_image" accept="image/*" onchange="previewEditImage(event)">
                        <input type="hidden" name="header_image" id="edit_header_image_data">
                        <div id="edit_image_preview" style="margin-top: 10px; display: none;">
                            <img id="edit_preview_img" style="max-width: 100%; height: auto; border-radius: 8px;">
                            <button type="button" class="bntm-btn-small bntm-btn-danger" onclick="removeEditImage()" style="margin-top: 10px;">Remove Image</button>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Raffle Title *</label>
                        <input type="text" name="title" id="edit_title" required>
                    </div>
                    
                    <!-- Rest of the form fields remain the same -->
                    <div class="bntm-form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Ticket Price (₱) *</label>
                            <input type="number" name="ticket_price" id="edit_ticket_price" step="0.01" min="0" required>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Total Tickets *</label>
                            <input type="number" name="total_tickets" id="edit_total_tickets" min="1" required>
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Draw Date & Time</label>
                        <input type="datetime-local" name="draw_date" id="edit_draw_date">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Terms & Conditions</label>
                        <textarea name="terms" id="edit_terms" rows="6"></textarea>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Purchase Instructions</label>
                        <textarea name="instructions" id="edit_instructions" rows="6"></textarea>
                    </div>
                </form>
            </div>
            <div class="raffle-modal-footer">
                <button class="bntm-btn-secondary" onclick="closeRaffleModal('editRaffleModal')">Cancel</button>
                <button class="bntm-btn-primary" onclick="submitEditRaffle()">Update Raffle</button>
            </div>
        </div>
    </div>
    
    <style>
    .bntm-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    </style>
    
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Form link copied to clipboard!\n\n' + text);
        });
    }
    
    function submitCreateRaffle() {
        const form = document.getElementById('createRaffleForm');
        const formData = new FormData(form);
        formData.append('action', 'raffle_create_raffle');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
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
                btn.textContent = 'Create Raffle';
            }
        });
    }
    
    function editRaffle(raffle) {
        document.getElementById('edit_raffle_id').value = raffle.id;
        document.getElementById('edit_title').value = raffle.title;
        document.getElementById('edit_description').value = raffle.description || '';
        document.getElementById('edit_ticket_price').value = raffle.ticket_price;
        document.getElementById('edit_total_tickets').value = raffle.total_tickets;
        document.getElementById('edit_status').value = raffle.status;
        document.getElementById('edit_terms').value = raffle.terms || '';
        document.getElementById('edit_instructions').value = raffle.instructions || '';
        
        // Handle header image
        if (raffle.header_image) {
            document.getElementById('edit_preview_img').src = raffle.header_image;
            document.getElementById('edit_header_image_data').value = raffle.header_image;
            document.getElementById('edit_image_preview').style.display = 'block';
        } else {
            document.getElementById('edit_image_preview').style.display = 'none';
        }
        
        if (raffle.draw_date) {
            const date = new Date(raffle.draw_date);
            const formatted = date.getFullYear() + '-' + 
                            String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                            String(date.getDate()).padStart(2, '0') + 'T' + 
                            String(date.getHours()).padStart(2, '0') + ':' + 
                            String(date.getMinutes()).padStart(2, '0');
            document.getElementById('edit_draw_date').value = formatted;
        }
        
        openRaffleModal('editRaffleModal');
    }
    
    function submitEditRaffle() {
        const form = document.getElementById('editRaffleForm');
        const formData = new FormData(form);
        formData.append('action', 'raffle_update_raffle');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Updating...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert(json.data.message);
                location.reload();
            } else {
                alert('Error: ' + json.data.message);
                btn.disabled = false;
                btn.textContent = 'Update Raffle';
            }
        });
    }
    
    function deleteRaffle(id, title) {
        if (!confirm('Delete raffle "' + title + '"?\n\nThis will also delete all associated tickets. This action cannot be undone.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'raffle_delete_raffle');
        formData.append('raffle_id', id);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            if (json.success) location.reload();
        });
    }
    
    function drawWinner(raffleId, title) {
        if (!confirm('Draw winner for "' + title + '"?\n\nThis will randomly select a winner from all paid tickets.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'raffle_draw_winner');
        formData.append('raffle_id', raffleId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert('Winner Drawn!\n\n' + 
                      'Ticket Number: ' + json.data.ticket_number + '\n' +
                      'Winner: ' + json.data.winner_name + '\n' +
                      'Email: ' + json.data.winner_email);
                location.reload();
            } else {
                alert('Error: ' + json.data.message);
            }
        });
    }

    function generateRaffleSOA(raffleId) {
        const formData = new FormData();
        formData.append('action', 'raffle_generate_soa');
        formData.append('raffle_id', raffleId);
        formData.append('nonce', '<?php echo $nonce; ?>');

        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (!json.success) {
                alert(json.data && json.data.message ? json.data.message : 'Failed to generate SOA');
                return;
            }

            const printWindow = window.open('', '_blank', 'width=1100,height=800');
            if (!printWindow) {
                alert('Please allow pop-ups to view the SOA.');
                return;
            }

            printWindow.document.open();
            printWindow.document.write(json.data.html);
            printWindow.document.close();
        })
        .catch(() => {
            alert('Failed to generate SOA. Please try again.');
        });
    }

    function previewCreateImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('create_preview_img').src = e.target.result;
                document.getElementById('create_header_image_data').value = e.target.result;
                document.getElementById('create_image_preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }
    
    function removeCreateImage() {
        document.getElementById('create_header_image').value = '';
        document.getElementById('create_header_image_data').value = '';
        document.getElementById('create_image_preview').style.display = 'none';
    }
    
    function previewEditImage(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('edit_preview_img').src = e.target.result;
                document.getElementById('edit_header_image_data').value = e.target.result;
                document.getElementById('edit_image_preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    }
    
    function removeEditImage() {
        document.getElementById('edit_header_image').value = '';
        document.getElementById('edit_header_image_data').value = '';
        document.getElementById('edit_image_preview').style.display = 'none';
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Tickets Management Tab - Modified to list by raffle reference
 */
function raffle_tickets_tab($business_id) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    // Get filters
    $filter_raffle = isset($_GET['raffle']) ? sanitize_text_field($_GET['raffle']) : '';
    $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
    
    // Get raffles for filter
    $raffles = $wpdb->get_results("
        SELECT id, rand_id, title 
        FROM {$raffles_table} 
        ORDER BY created_at DESC
    ");
    
    // Build query - Group by purchase_reference
    $where_clauses = [];
    $params = [];
    
    if ($filter_raffle) {
        $where_clauses[] = "r.rand_id = %s";
        $params[] = $filter_raffle;
    }
    
    if ($filter_status === 'paid') {
        $where_clauses[] = "t.payment_status = 'paid'";
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Get grouped purchases by reference
    $purchases = $wpdb->get_results($wpdb->prepare("
        SELECT 
            t.purchase_reference,
            t.raffle_id,
            r.title as raffle_title,
            r.ticket_price,
            t.customer_name,
            t.customer_email,
            t.customer_phone,
            t.payment_status,
            t.payment_method,
            t.payment_gateway,
            MIN(t.purchased_at) as purchased_at,
            COUNT(t.id) as ticket_count,
            SUM(t.amount_paid) as total_amount,
            GROUP_CONCAT(t.ticket_number ORDER BY t.ticket_number SEPARATOR ', ') as ticket_numbers,
            MAX(t.is_winner) as has_winner
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        {$where_sql}
        GROUP BY t.purchase_reference
        ORDER BY MIN(t.purchased_at) DESC
    ", $params));
    
    $nonce = wp_create_nonce('raffle_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="raffle-section-head">
            <h3>All Purchases</h3>
            <div class="raffle-section-tools">
                <select id="statusFilter" onchange="filterTickets()" class="bntm-input" style="width: 150px;">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>All Status</option>
                    <option value="paid" <?php selected($filter_status, 'paid'); ?>>Paid Only</option>
                </select>
                <select id="raffleFilter" onchange="filterTickets()" class="bntm-input" style="width: 250px;">
                    <option value="">All Raffles</option>
                    <?php foreach ($raffles as $raffle): ?>
                        <option value="<?php echo $raffle->rand_id; ?>" <?php selected($filter_raffle, $raffle->rand_id); ?>>
                            <?php echo esc_html($raffle->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if (empty($purchases)): ?>
            <p style="color: #6b7280; text-align: center; padding: 40px 0;">
                No purchases found.
            </p>
        <?php else: ?>
            <div class="raffle-table-shell">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Raffle</th>
                        <th>Customer</th>
                        <th>Contact</th>
                        <th>Tickets</th>
                        <th>Total Amount</th>
                        <th>Payment Status</th>
                        <th>Payment Method</th>
                        <th>Purchased</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): ?>
                    <tr style="<?php echo $purchase->has_winner ? 'background: #fef3c7;' : ''; ?>">
                        <td>
                            <strong><?php echo esc_html($purchase->purchase_reference); ?></strong>
                            <?php if ($purchase->has_winner): ?>
                                <br><span style="color: #d97706; font-weight: 600;">HAS WINNER</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($purchase->raffle_title); ?></td>
                        <td><strong><?php echo esc_html($purchase->customer_name); ?></strong></td>
                        <td>
                            <?php if ($purchase->customer_email): ?>
                                <?php echo esc_html($purchase->customer_email); ?><br>
                            <?php endif; ?>
                            <?php if ($purchase->customer_phone): ?>
                                <small><?php echo esc_html($purchase->customer_phone); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $purchase->ticket_count; ?></strong> ticket(s)
                        </td>
                        <td class="bntm-stat-income">₱<?php echo number_format($purchase->total_amount, 2); ?></td>
                        <td>
                            <span class="bntm-badge bntm-badge-<?php echo $purchase->payment_status; ?>">
                                <?php echo ucfirst($purchase->payment_status); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $purchase->payment_method ? esc_html(ucfirst($purchase->payment_method)) : '-'; ?>
                            <?php if ($purchase->payment_gateway): ?>
                                <br><small style="color: #6b7280;"><?php echo esc_html($purchase->payment_gateway); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($purchase->purchased_at)); ?><br>
                            <small><?php echo date('h:i A', strtotime($purchase->purchased_at)); ?></small>
                        </td>
                        <td>
                            <button class="bntm-btn-small" 
                                    onclick="viewPurchaseDetails('<?php echo esc_js($purchase->purchase_reference); ?>')">
                                View Details
                            </button>
                            <?php if ($purchase->payment_status === 'pending'): ?>
                            <button class="bntm-btn-small bntm-btn-success" 
                                    onclick="updatePurchaseStatus('<?php echo esc_js($purchase->purchase_reference); ?>', 'paid')">
                                Mark Paid
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Purchase Details Modal -->
    <div id="purchaseDetailsModal" class="raffle-modal">
        <div class="raffle-modal-content" style="max-width: 800px;">
            <div class="raffle-modal-header">
                <h3>Purchase Details</h3>
                <button class="raffle-modal-close" onclick="closeRaffleModal('purchaseDetailsModal')">&times;</button>
            </div>
            <div class="raffle-modal-body" id="purchaseDetailsContent">
                <!-- Details will be inserted here -->
            </div>
            <div class="raffle-modal-footer">
                <button class="bntm-btn-secondary" onclick="closeRaffleModal('purchaseDetailsModal')">Close</button>
            </div>
        </div>
    </div>
    
    <style>
    .bntm-badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    .bntm-badge-paid {
        background: #d1fae5;
        color: #065f46;
    }
    .bntm-badge-failed {
        background: #fee2e2;
        color: #991b1b;
    }
    </style>
    
    <script>
    function filterTickets() {
        const raffleId = document.getElementById('raffleFilter').value;
        const status = document.getElementById('statusFilter').value;
        const url = new URL(window.location.href);
        
        if (raffleId) {
            url.searchParams.set('raffle', raffleId);
        } else {
            url.searchParams.delete('raffle');
        }
        
        if (status !== 'all') {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        
        window.location.href = url.toString();
    }
    
    function viewPurchaseDetails(reference) {
        const formData = new FormData();
        formData.append('action', 'raffle_get_purchase_details');
        formData.append('reference', reference);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                displayPurchaseDetails(json.data);
            } else {
                alert('Error loading details');
            }
        });
    }
    
    function displayPurchaseDetails(data) {
        const ticketsList = data.tickets.map(t => `
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">
                    <strong>${t.ticket_number}</strong>
                   <!--${t.is_winner ? '<span style="color: #d97706; margin-left: 10px;">WINNER</span>' : ''}-->
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">₱${parseFloat(t.amount_paid).toFixed(2)}</td>
            </tr>
        `).join('');
        
        const content = `
            <div style="padding: 10px;">
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; color: #374151;">Purchase Information</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600; width: 180px;">Reference:</td>
                            <td style="padding: 8px 0;">${data.reference}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Raffle:</td>
                            <td style="padding: 8px 0;">${data.raffle_title}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Total Tickets:</td>
                            <td style="padding: 8px 0;">${data.tickets.length}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Total Amount:</td>
                            <td style="padding: 8px 0; color: #059669; font-weight: 600;">₱${parseFloat(data.total_amount).toFixed(2)}</td>
                        </tr>
                    </table>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; color: #374151;">Customer Information</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600; width: 180px;">Name:</td>
                            <td style="padding: 8px 0;">${data.customer_name}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Email:</td>
                            <td style="padding: 8px 0;">${data.customer_email || 'N/A'}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Phone:</td>
                            <td style="padding: 8px 0;">${data.customer_phone || 'N/A'}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Address:</td>
                            <td style="padding: 8px 0;">${data.customer_address || 'N/A'}</td>
                        </tr>
                    </table>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h4 style="margin-bottom: 10px; color: #374151;">Payment Information</h4>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600; width: 180px;">Status:</td>
                            <td style="padding: 8px 0;">${data.payment_status.toUpperCase()}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Method:</td>
                            <td style="padding: 8px 0;">${data.payment_method || 'N/A'}</td>
                        </tr>
                        ${data.payment_gateway ? `
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <td style="padding: 8px 0; font-weight: 600;">Gateway:</td>
                            <td style="padding: 8px 0;">${data.payment_gateway}</td>
                        </tr>` : ''}
                        <tr>
                            <td style="padding: 8px 0; font-weight: 600;">Purchased:</td>
                            <td style="padding: 8px 0;">${new Date(data.purchased_at).toLocaleString()}</td>
                        </tr>
                    </table>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 10px; color: #374151;">Tickets (${data.tickets.length})</h4>
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb;">
                        <thead>
                            <tr style="background: #f9fafb;">
                                <th style="padding: 8px; text-align: left; border-bottom: 2px solid #e5e7eb;">Ticket Number</th>
                                <th style="padding: 8px; text-align: left; border-bottom: 2px solid #e5e7eb;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${ticketsList}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        document.getElementById('purchaseDetailsContent').innerHTML = content;
        openRaffleModal('purchaseDetailsModal');
    }
    
    function updatePurchaseStatus(reference, status) {
        if (!confirm('Mark all tickets in this purchase as paid?')) return;
        
        const formData = new FormData();
        formData.append('action', 'raffle_update_purchase_status');
        formData.append('reference', reference);
        formData.append('status', status);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            if (json.success) location.reload();
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Receipt Sending Tab - NEW
 *//**
 * Receipt Sending Tab - with Bulk Send
 */
function raffle_receipts_tab($business_id) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    // Get filter
    $filter_raffle = isset($_GET['raffle']) ? sanitize_text_field($_GET['raffle']) : '';
    
    // Get raffles for filter
    $raffles = $wpdb->get_results("
        SELECT id, rand_id, title 
        FROM {$raffles_table} 
        ORDER BY created_at DESC
    ");
    
    // Build query - Only paid purchases
    $where_clauses = ["t.payment_status = 'paid'"];
    $params = [];
    
    if ($filter_raffle) {
        $where_clauses[] = "r.rand_id = %s";
        $params[] = $filter_raffle;
    }
    
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    
    // Get paid purchases
    $purchases = $wpdb->get_results($wpdb->prepare("
        SELECT 
            t.purchase_reference,
            t.raffle_id,
            r.title as raffle_title,
            r.rand_id as raffle_rand_id,
            t.customer_name,
            t.customer_email,
            t.customer_phone,
            t.payment_gateway,
            MIN(t.purchased_at) as purchased_at,
            COUNT(t.id) as ticket_count,
            SUM(t.amount_paid) as total_amount,
            GROUP_CONCAT(t.ticket_number ORDER BY t.ticket_number SEPARATOR ', ') as ticket_numbers
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        {$where_sql}
        GROUP BY t.purchase_reference
        ORDER BY MIN(t.purchased_at) DESC
    ", $params));
    
    $nonce = wp_create_nonce('raffle_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="raffle-section-head">
            <h3>Send Receipts (Paid Purchases Only)</h3>
            <div class="raffle-section-tools">
                <button class="bntm-btn-secondary" onclick="testEmailReceipt()">
                    Test Email
                </button>
                <select id="raffleFilterReceipt" onchange="filterReceipts()" class="bntm-input" style="width: 250px;">
                    <option value="">All Raffles</option>
                    <?php foreach ($raffles as $raffle): ?>
                        <option value="<?php echo $raffle->rand_id; ?>" <?php selected($filter_raffle, $raffle->rand_id); ?>>
                            <?php echo esc_html($raffle->title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <?php if (empty($purchases)): ?>
            <p style="color: #6b7280; text-align: center; padding: 40px 0;">
                No paid purchases found.
            </p>
        <?php else: ?>
            <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f9fafb; border-radius: 6px;">
                <div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="selectAllReceipts" onchange="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
                        <strong>Select All</strong>
                    </label>
                    <small style="color: #6b7280; margin-left: 26px;">
                        <span id="selectedCount">0</span> selected
                    </small>
                </div>
                <button class="bntm-btn-primary" onclick="sendBulkReceipts()" id="bulkSendBtn" disabled>
                    Send Selected Receipts
                </button>
            </div>
            
            <div class="raffle-table-shell">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" disabled style="opacity: 0;">
                        </th>
                        <th>Reference</th>
                        <th>Raffle</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Tickets</th>
                        <th>Total Amount</th>
                        <th>Gateway</th>
                        <th>Purchased</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): 
                        $result_url = get_permalink(get_page_by_path('payment-result')) .
                            '?raffle_payment_success=1&ref=' . $purchase->purchase_reference . 
                            '&gateway=' . ($purchase->payment_gateway ?: 'manual');
                        $has_email = !empty($purchase->customer_email);
                    ?>
                    <tr>
                        <td>
                            <?php if ($has_email): ?>
                                <input type="checkbox" 
                                       class="receipt-checkbox" 
                                       value="<?php echo esc_attr($purchase->purchase_reference); ?>"
                                       data-email="<?php echo esc_attr($purchase->customer_email); ?>"
                                       onchange="updateSelectedCount()"
                                       style="width: 18px; height: 18px; cursor: pointer;">
                            <?php else: ?>
                                <span style="color: #9ca3af;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html($purchase->purchase_reference); ?></strong></td>
                        <td><?php echo esc_html($purchase->raffle_title); ?></td>
                        <td><strong><?php echo esc_html($purchase->customer_name); ?></strong></td>
                        <td>
                            <?php if ($has_email): ?>
                                <?php echo esc_html($purchase->customer_email); ?>
                            <?php else: ?>
                                <span style="color: #ef4444;">No email</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo $purchase->ticket_count; ?></strong> ticket(s)<br>
                            <small style="color: #6b7280;"><?php echo esc_html($purchase->ticket_numbers); ?></small>
                        </td>
                        <td class="bntm-stat-income">₱<?php echo number_format($purchase->total_amount, 2); ?></td>
                        <td><?php echo $purchase->payment_gateway ? esc_html($purchase->payment_gateway) : 'Manual'; ?></td>
                        <td>
                            <?php echo date('M d, Y', strtotime($purchase->purchased_at)); ?><br>
                            <small><?php echo date('h:i A', strtotime($purchase->purchased_at)); ?></small>
                        </td>
                        <td>
                            <button class="bntm-btn-small" 
                                    onclick="copyToClipboard('<?php echo esc_js($result_url); ?>')">
                                Copy Link
                            </button>
                            <?php if ($has_email): ?>
                            <button class="bntm-btn-small bntm-btn-primary" 
                                    onclick="sendReceiptEmail('<?php echo esc_js($purchase->purchase_reference); ?>', '<?php echo esc_js($purchase->customer_email); ?>')">
                                Send Email
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bulk Send Progress Modal -->
    <div id="bulkSendModal" class="raffle-modal">
        <div class="raffle-modal-content" style="max-width: 500px;">
            <div class="raffle-modal-header">
                <h3>Sending Receipts</h3>
            </div>
            <div class="raffle-modal-body">
                <div id="bulkSendProgress">
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Progress:</span>
                            <span id="progressText">0 / 0</span>
                        </div>
                        <div style="background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div id="progressBar" style="background: #3b82f6; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                    </div>
                    <div id="progressLog" style="max-height: 300px; overflow-y: auto; background: #f9fafb; padding: 10px; border-radius: 6px; font-size: 13px;">
                        <!-- Progress messages will appear here -->
                    </div>
                </div>
            </div>
            <div class="raffle-modal-footer">
                <button class="bntm-btn-secondary" onclick="closeBulkSendModal()" id="closeBulkBtn" disabled>
                    Close
                </button>
            </div>
        </div>
    </div>
    
    <style>
    .receipt-checkbox:checked {
        accent-color: #3b82f6;
    }
    #selectAllReceipts {
        accent-color: #3b82f6;
    }
    </style>
    
    <script>
    function filterReceipts() {
        const raffleId = document.getElementById('raffleFilterReceipt').value;
        const url = new URL(window.location.href);
        if (raffleId) {
            url.searchParams.set('raffle', raffleId);
        } else {
            url.searchParams.delete('raffle');
        }
        window.location.href = url.toString();
    }
    
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAllReceipts');
        const checkboxes = document.querySelectorAll('.receipt-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = selectAll.checked;
        });
        updateSelectedCount();
    }
    
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.receipt-checkbox:checked');
        const count = checkboxes.length;
        document.getElementById('selectedCount').textContent = count;
        document.getElementById('bulkSendBtn').disabled = count === 0;
        
        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.receipt-checkbox');
        const selectAll = document.getElementById('selectAllReceipts');
        if (allCheckboxes.length > 0) {
            selectAll.checked = count === allCheckboxes.length;
            selectAll.indeterminate = count > 0 && count < allCheckboxes.length;
        }
    }
    
    function testEmailReceipt() {
        if (!confirm('Send a test receipt email to mocorroprint@gmail.com?')) return;
        
        const formData = new FormData();
        formData.append('action', 'raffle_send_test_receipt');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Sending...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            btn.disabled = false;
            btn.textContent = 'Test Email (mocorroprint@gmail.com)';
        });
    }
    
    function sendReceiptEmail(reference, email) {
        if (!confirm('Send receipt email to ' + email + '?')) return;
        
        const formData = new FormData();
        formData.append('action', 'raffle_send_receipt_email');
        formData.append('reference', reference);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = event.target;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Sending...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            alert(json.data.message);
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }
    
    async function sendBulkReceipts() {
        const checkboxes = document.querySelectorAll('.receipt-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one receipt to send');
            return;
        }
        
        const count = checkboxes.length;
        if (!confirm(`Send ${count} receipt email${count > 1 ? 's' : ''}?`)) {
            return;
        }
        
        // Show modal
        document.getElementById('bulkSendModal').style.display = 'block';
        document.getElementById('closeBulkBtn').disabled = true;
        document.getElementById('progressLog').innerHTML = '';
        
        const references = Array.from(checkboxes).map(cb => ({
            reference: cb.value,
            email: cb.dataset.email
        }));
        
        let completed = 0;
        let successful = 0;
        let failed = 0;
        
        for (const item of references) {
            try {
                const formData = new FormData();
                formData.append('action', 'raffle_send_receipt_email');
                formData.append('reference', item.reference);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                const response = await fetch(ajaxurl, {method: 'POST', body: formData});
                const json = await response.json();
                
                completed++;
                
                if (json.success) {
                    successful++;
                    logProgress(`✓ Sent to ${item.email} (${item.reference})`, 'success');
                } else {
                    failed++;
                    logProgress(`✗ Failed: ${item.email} (${item.reference}) - ${json.data.message}`, 'error');
                }
                
                updateProgress(completed, references.length);
                
                // Small delay to prevent overwhelming the server
                await new Promise(resolve => setTimeout(resolve, 500));
                
            } catch (error) {
                completed++;
                failed++;
                logProgress(`✗ Error: ${item.email} (${item.reference})`, 'error');
                updateProgress(completed, references.length);
            }
        }
        
        // Show summary
        logProgress(`\n═══════════════════════════`, 'summary');
        logProgress(`Summary: ${successful} sent, ${failed} failed`, 'summary');
        
        document.getElementById('closeBulkBtn').disabled = false;
        
        // Uncheck all after completion
        setTimeout(() => {
            document.querySelectorAll('.receipt-checkbox:checked').forEach(cb => cb.checked = false);
            document.getElementById('selectAllReceipts').checked = false;
            updateSelectedCount();
        }, 1000);
    }
    
    function updateProgress(current, total) {
        const percentage = (current / total) * 100;
        document.getElementById('progressBar').style.width = percentage + '%';
        document.getElementById('progressText').textContent = `${current} / ${total}`;
    }
    
    function logProgress(message, type = 'info') {
        const log = document.getElementById('progressLog');
        const entry = document.createElement('div');
        entry.style.marginBottom = '5px';
        entry.style.padding = '5px';
        entry.style.borderRadius = '3px';
        
        if (type === 'success') {
            entry.style.color = '#065f46';
            entry.style.background = '#d1fae5';
        } else if (type === 'error') {
            entry.style.color = '#991b1b';
            entry.style.background = '#fee2e2';
        } else if (type === 'summary') {
            entry.style.fontWeight = 'bold';
            entry.style.color = '#1f2937';
        }
        
        entry.textContent = message;
        log.appendChild(entry);
        log.scrollTop = log.scrollHeight;
    }
    
    function closeBulkSendModal() {
        document.getElementById('bulkSendModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('bulkSendModal');
        if (event.target === modal && !document.getElementById('closeBulkBtn').disabled) {
            closeBulkSendModal();
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Payment Settings Tab
 */
function raffle_payment_tab($business_id) {
    $payment_source = bntm_get_setting('raffle_payment_source', 'manual');
    $manual_methods = json_decode(bntm_get_setting('raffle_payment_methods', '[]'), true);
    if (!is_array($manual_methods)) {
        $manual_methods = [];
    }
    
    $nonce = wp_create_nonce('raffle_payment_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Payment Configuration</h3>
        <p>Choose payment processing method</p>
        
        <div class="bntm-form-group">
            <label>Payment Source</label>
            <select id="payment-source-select" name="payment_source" class="bntm-input">
                <option value="manual" <?php selected($payment_source, 'manual'); ?>>
                    MANUAL - Configure payment methods here
                </option>
                <?php if (bntm_is_module_enabled('op') && bntm_is_module_visible('op')): ?>
                    <option value="op" <?php selected($payment_source, 'op'); ?>>
                        AUTOMATIC - Online Payment Module (PayPal, PayMaya, etc.)
                    </option>
                <?php else: ?>
                    <option value="op" disabled>
                        AUTOMATIC - Online Payment Module (Requires OP Module)
                    </option>
                <?php endif; ?>
            </select>
            <small>
                Choose 'Manual' to configure payment methods here, or 'Automatic' to use 
                configured payment gateways from the Online Payment module
            </small>
        </div>
        
        <button type="button" id="save-payment-source-btn" class="bntm-btn-primary">
            Save Payment Source
        </button>
        <div id="payment-source-message"></div>
    </div>
    
    <!-- Manual Payment Methods Section -->
    <div class="bntm-form-section" id="manual-payment-section" 
         style="<?php echo $payment_source === 'op' ? 'display: none;' : ''; ?>">
        <h3>Manual Payment Methods</h3>
        <p>Configure manual payment methods (Bank Transfer, Cash, GCash, etc.)</p>
        
        <div id="payment-methods-list" style="margin-bottom: 20px;">
            <?php if (empty($manual_methods)): ?>
                <p style="color: #6b7280;">No payment methods configured yet.</p>
            <?php else: ?>
                <?php foreach ($manual_methods as $index => $method): ?>
                    <div class="payment-method-item" style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($method['name']); ?></strong>
                                <span style="color: #6b7280; margin-left: 10px;">
                                    <?php echo esc_html($method['type']); ?>
                                </span>
                            </div>
                            <button class="bntm-btn-small bntm-btn-danger remove-payment-method" 
                                    data-index="<?php echo $index; ?>">
                                Remove
                            </button>
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
                        <?php if (!empty($method['description'])): ?>
                            <div style="margin-top: 8px; font-size: 13px; color: #6b7280;">
                                <?php echo nl2br(esc_html($method['description'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="padding: 20px; background: #f9fafb; border-radius: 8px;">
            <h4>Add Payment Method</h4>
            <form id="add-payment-method-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Payment Type *</label>
                    <select name="payment_type" required class="bntm-input">
                        <option value="">Select Type</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="cash">Cash Payment</option>
                        <option value="gcash">GCash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Display Name *</label>
                    <input type="text" name="payment_name" class="bntm-input" placeholder="e.g., BDO Bank Transfer" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Name</label>
                    <input type="text" name="account_name" class="bntm-input" placeholder="Account holder name">
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Number</label>
                    <input type="text" name="account_number" class="bntm-input" placeholder="Account/Phone number">
                </div>
                
                <div class="bntm-form-group">
                    <label>Instructions</label>
                    <textarea name="payment_description" rows="3" class="bntm-input" 
                              placeholder="Payment instructions for customers"></textarea>
                </div>
                
                <button type="submit" class="bntm-btn-primary">Add Payment Method</button>
            </form>
        </div>
        <div id="payment-method-message"></div>
    </div>
    
    <script>
    (function() {
        const paymentSourceSelect = document.getElementById('payment-source-select');
        const manualSection = document.getElementById('manual-payment-section');
        
        // Toggle manual payment section
        paymentSourceSelect.addEventListener('change', function() {
            if (this.value === 'op') {
                manualSection.style.display = 'none';
            } else {
                manualSection.style.display = 'block';
            }
        });
        
        // Save payment source
        document.getElementById('save-payment-source-btn').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'raffle_save_payment_source');
            formData.append('payment_source', paymentSourceSelect.value);
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                document.getElementById('payment-source-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + 
                    json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Payment Source';
            });
        });
        
        // Add payment method
        document.getElementById('add-payment-method-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'raffle_add_payment_method');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    document.getElementById('payment-method-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    document.getElementById('payment-method-message').innerHTML = 
                        '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Add Payment Method';
                }
            });
        });
        
        // Remove payment method
        document.querySelectorAll('.remove-payment-method').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this payment method?')) return;
                
                const formData = new FormData();
                formData.append('action', 'raffle_remove_payment_method');
                formData.append('index', this.dataset.index);
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

/**
 * Finance Export Tab
 */
function raffle_finance_tab($business_id) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $fn_table = $wpdb->prefix . 'fn_transactions';
    
    // Get paid tickets with export status
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, r.title as raffle_title,
        (SELECT COUNT(*) FROM {$fn_table} WHERE reference_type='raffle_ticket' AND reference_id=t.id) as is_exported
        FROM {$tickets_table} t
        LEFT JOIN {$wpdb->prefix}raffle_raffles r ON t.raffle_id = r.id
        WHERE t.payment_status = 'paid'
        ORDER BY t.purchased_at DESC
    ", $business_id));
    
    $nonce = wp_create_nonce('raffle_fn_action');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Export to Finance Module</h3>
        <p>Export ticket sales as income records to the Finance module</p>
        
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
            <span id="selected-count" style="margin-left: 15px;"></span>
        </div>
        
        <div class="raffle-table-shell">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th width="40"></th>
                    <th>Ticket Number</th>
                    <th>Raffle</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Export Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="7" style="text-align:center;">No paid tickets found</td></tr>
                <?php else: foreach ($tickets as $ticket): ?>
                <tr>
                    <td>
                        <input type="checkbox" 
                               class="ticket-checkbox <?php echo $ticket->is_exported ? 'exported-ticket' : 'not-exported-ticket'; ?>" 
                               data-id="<?php echo $ticket->id; ?>"
                               data-amount="<?php echo $ticket->amount_paid; ?>"
                               data-exported="<?php echo $ticket->is_exported ? '1' : '0'; ?>">
                    </td>
                    <td><strong><?php echo $ticket->ticket_number; ?></strong></td>
                    <td><?php echo esc_html($ticket->raffle_title); ?></td>
                    <td><?php echo esc_html($ticket->customer_name); ?></td>
                    <td><?php echo date('M d, Y', strtotime($ticket->purchased_at)); ?></td>
                    <td class="bntm-stat-income">
                        ₱<?php echo number_format($ticket->amount_paid, 2); ?>
                    </td>
                    <td>
                        <?php if ($ticket->is_exported): ?>
                        <span style="color:#059669;">Exported</span>
                        <?php else: ?>
                        <span style="color:#6b7280;">Not Exported</span>
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
            const selected = document.querySelectorAll('.ticket-checkbox:checked').length;
            document.getElementById('selected-count').textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        // Select all not exported
        document.getElementById('select-all-not-exported').addEventListener('change', function() {
            document.querySelectorAll('.not-exported-ticket').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-exported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Select all exported
        document.getElementById('select-all-exported').addEventListener('change', function() {
            document.querySelectorAll('.exported-ticket').forEach(cb => {
                cb.checked = this.checked;
            });
            if (this.checked) {
                document.getElementById('select-all-not-exported').checked = false;
            }
            updateSelectedCount();
        });
        
        // Update count on checkbox change
        document.querySelectorAll('.ticket-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Bulk Export
        document.getElementById('bulk-export-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.ticket-checkbox:checked'))
                .filter(cb => cb.dataset.exported === '0');
            
            if (selected.length === 0) {
                alert('Please select at least one ticket that is not exported');
                return;
            }
            
            const totalAmount = selected.reduce((sum, cb) => sum + parseFloat(cb.dataset.amount), 0);
            
            if (!confirm(`Export ${selected.length} ticket sale(s)?\n\nTotal Amount: ₱${totalAmount.toFixed(2)}`)) return;
            
            this.disabled = true;
            this.textContent = 'Exporting...';
            
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'raffle_fn_export_sales');
                data.append('ticket_id', cb.dataset.id);
                data.append('amount', cb.dataset.amount);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully exported ${total} ticket sale(s)`);
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
        
        // Bulk Revert
        document.getElementById('bulk-revert-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.ticket-checkbox:checked'))
                .filter(cb => cb.dataset.exported === '1');
            
            if (selected.length === 0) {
                alert('Please select at least one exported ticket');
                return;
            }
            
            if (!confirm(`Remove ${selected.length} ticket sale(s) from Finance?`)) return;
            
            this.disabled = true;
            this.textContent = 'Reverting...';
            
            let completed = 0;
            const total = selected.length;
            
            selected.forEach(cb => {
                const data = new FormData();
                data.append('action', 'raffle_fn_revert_sale');
                data.append('ticket_id', cb.dataset.id);
                data.append('_ajax_nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    completed++;
                    if (completed === total) {
                        alert(`Successfully reverted ${total} ticket sale(s)`);
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
function raffle_settings_tab($business_id) {
    $default_terms = bntm_get_setting('raffle_default_terms', '');
    $default_instructions = bntm_get_setting('raffle_default_instructions', '');
    
    $nonce = wp_create_nonce('raffle_settings_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Default Terms & Instructions</h3>
        <p>Set default terms and conditions and purchase instructions for new raffles</p>
        
        <form id="settingsForm" class="bntm-form">
            <div class="bntm-form-group">
                <label>Default Terms & Conditions</label>
                <textarea name="default_terms" rows="10" class="bntm-input"><?php echo esc_textarea($default_terms); ?></textarea>
                <small>These will be used as default when creating new raffles</small>
            </div>
            
            <div class="bntm-form-group">
                <label>Default Purchase Instructions</label>
                <textarea name="default_instructions" rows="10" class="bntm-input"><?php echo esc_textarea($default_instructions); ?></textarea>
                <small>These will be shown to customers on the purchase form</small>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Settings</button>
        </form>
        <div id="settings-message"></div>
    </div>
    
    <script>
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'raffle_save_settings');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = this.querySelector('button[type="submit"]');
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
    });
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLER FUNCTIONS
// ============================================================================

/**
 * Create Raffle
 */
function bntm_ajax_raffle_create_raffle() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'raffle_raffles';
    
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $ticket_price = floatval($_POST['ticket_price']);
    $total_tickets = intval($_POST['total_tickets']);
    $draw_date = !empty($_POST['draw_date']) ? sanitize_text_field($_POST['draw_date']) : null;
    $terms = sanitize_textarea_field($_POST['terms']);
    $instructions = sanitize_textarea_field($_POST['instructions']);
    $header_image = !empty($_POST['header_image']) ? bntm_process_raffle_image($_POST['header_image']) : null;
    
    if (empty($title) || $ticket_price < 0 || $total_tickets < 1) {
        wp_send_json_error(['message' => 'Please fill in all required fields correctly']);
    }
    
    $business_id = get_current_user_id();
    
    $result = $wpdb->insert($table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'title' => $title,
        'description' => $description,
        'ticket_price' => $ticket_price,
        'total_tickets' => $total_tickets,
        'draw_date' => $draw_date,
        'terms' => $terms,
        'instructions' => $instructions,
        'header_image' => $header_image,
        'status' => 'active'
    ], [
        '%s','%d','%s','%s','%f','%d','%s','%s','%s','%s','%s'
    ]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Raffle created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create raffle'.$wpdb->last_error]);
    }
}

/**
 * Update Raffle
 */
function bntm_ajax_raffle_update_raffle() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'raffle_raffles';
    
    $raffle_id = intval($_POST['raffle_id']);
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $ticket_price = floatval($_POST['ticket_price']);
    $total_tickets = intval($_POST['total_tickets']);
    $draw_date = !empty($_POST['draw_date']) ? sanitize_text_field($_POST['draw_date']) : null;
    $status = sanitize_text_field($_POST['status']);
    $terms = sanitize_textarea_field($_POST['terms']);
    $instructions = sanitize_textarea_field($_POST['instructions']);
    $header_image = !empty($_POST['header_image']) ? bntm_process_raffle_image($_POST['header_image']) : null;
    
    $result = $wpdb->update(
        $table,
        [
            'title' => $title,
            'description' => $description,
            'ticket_price' => $ticket_price,
            'total_tickets' => $total_tickets,
            'draw_date' => $draw_date,
            'status' => $status,
            'terms' => $terms,
            'instructions' => $instructions,
            'header_image' => $header_image
        ],
        ['id' => $raffle_id],
        ['%s','%s','%f','%d','%s','%s','%s','%s','%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Raffle updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update raffle']);
    }
}

/**
 * Delete Raffle
 */
function bntm_ajax_raffle_delete_raffle() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $raffle_id = intval($_POST['raffle_id']);
    
    // Delete tickets first
    $wpdb->delete($tickets_table, ['raffle_id' => $raffle_id], ['%d']);
    
    // Delete raffle
    $result = $wpdb->delete($raffles_table, ['id' => $raffle_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Raffle deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete raffle']);
    }
}

/**
 * Draw Winner
 */
function bntm_ajax_raffle_draw_winner() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $raffle_id = intval($_POST['raffle_id']);
    
    // Get all paid tickets for this raffle
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$tickets_table} 
        WHERE raffle_id = %d AND payment_status = 'paid'
        ORDER BY RAND()
        LIMIT 1
    ", $raffle_id));
    
    if (empty($tickets)) {
        wp_send_json_error(['message' => 'No paid tickets found for this raffle']);
    }
    
    $winner = $tickets[0];
    
    // Update raffle with winner
    $wpdb->update(
        $raffles_table,
        [
            'winner_ticket_number' => $winner->ticket_number,
            'winner_name' => $winner->customer_name,
            'winner_email' => $winner->customer_email,
            'winner_phone' => $winner->customer_phone,
            'drawn_at' => current_time('mysql'),
            'status' => 'completed'
        ],
        ['id' => $raffle_id],
        ['%s','%s','%s','%s','%s','%s'],
        ['%d']
    );
    
    // Mark ticket as winner
    $wpdb->update(
        $tickets_table,
        ['is_winner' => 1],
        ['id' => $winner->id],
        ['%d'],
        ['%d']
    );
    
    wp_send_json_success([
        'message' => 'Winner drawn successfully!',
        'ticket_number' => $winner->ticket_number,
        'winner_name' => $winner->customer_name,
        'winner_email' => $winner->customer_email
    ]);
}

/**
 * Save Payment Source
 */
function bntm_ajax_raffle_save_payment_source() {
    check_ajax_referer('raffle_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $payment_source = sanitize_text_field($_POST['payment_source']);
    
    if (!in_array($payment_source, ['manual', 'op'])) {
        wp_send_json_error(['message' => 'Invalid payment source']);
    }
    
    bntm_set_setting('raffle_payment_source', $payment_source);
    wp_send_json_success(['message' => 'Payment source saved successfully!']);
}

/**
 * Add Payment Method
 */
function bntm_ajax_raffle_add_payment_method() {
    check_ajax_referer('raffle_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $payment_methods = json_decode(bntm_get_setting('raffle_payment_methods', '[]'), true);
    if (!is_array($payment_methods)) {
        $payment_methods = [];
    }
    
    $new_method = [
        'type' => sanitize_text_field($_POST['payment_type']),
        'name' => sanitize_text_field($_POST['payment_name']),
        'description' => sanitize_textarea_field($_POST['payment_description']),
        'account_name' => sanitize_text_field($_POST['account_name']),
        'account_number' => sanitize_text_field($_POST['account_number'])
    ];
    
    $payment_methods[] = $new_method;
    bntm_set_setting('raffle_payment_methods', json_encode($payment_methods));
    
    wp_send_json_success(['message' => 'Payment method added successfully!']);
}

/**
 * Remove Payment Method
 */
function bntm_ajax_raffle_remove_payment_method() {
    check_ajax_referer('raffle_payment_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $index = intval($_POST['index']);
    $payment_methods = json_decode(bntm_get_setting('raffle_payment_methods', '[]'), true);
    
    if (!is_array($payment_methods) || !isset($payment_methods[$index])) {
        wp_send_json_error(['message' => 'Payment method not found']);
    }
    
    array_splice($payment_methods, $index, 1);
    bntm_set_setting('raffle_payment_methods', json_encode($payment_methods));
    
    wp_send_json_success(['message' => 'Payment method removed']);
}

/**
 * Save Settings
 */
function bntm_ajax_raffle_save_settings() {
    check_ajax_referer('raffle_settings_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $default_terms = sanitize_textarea_field($_POST['default_terms']);
    $default_instructions = sanitize_textarea_field($_POST['default_instructions']);
    
    bntm_set_setting('raffle_default_terms', $default_terms);
    bntm_set_setting('raffle_default_instructions', $default_instructions);
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

/**
 * Update Ticket Status
 */
function bntm_ajax_raffle_update_ticket_status() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'raffle_tickets';
    
    $ticket_id = intval($_POST['ticket_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $result = $wpdb->update(
        $table,
        ['payment_status' => $status],
        ['id' => $ticket_id],
        ['%s'],
        ['%d']
    );
    
    if ($result !== false) {
        // Update tickets_sold count in raffle
        $ticket = $wpdb->get_row($wpdb->prepare("SELECT raffle_id FROM {$table} WHERE id = %d", $ticket_id));
        if ($ticket) {
            raffle_update_tickets_sold($ticket->raffle_id);
        }
        
        wp_send_json_success(['message' => 'Ticket status updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update ticket status']);
    }
}
// AJAX: Get purchase details
add_action('wp_ajax_raffle_get_purchase_details', 'raffle_ajax_get_purchase_details');
function raffle_ajax_get_purchase_details() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    $reference = sanitize_text_field($_POST['reference']);
    
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, r.title as raffle_title
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        WHERE t.purchase_reference = %s
        ORDER BY t.ticket_number
    ", $reference));
    
    if (empty($tickets)) {
        wp_send_json_error(['message' => 'Purchase not found']);
    }
    
    $first = $tickets[0];
    $total = array_sum(array_column($tickets, 'amount_paid'));
    
    wp_send_json_success([
        'reference' => $reference,
        'raffle_title' => $first->raffle_title,
        'customer_name' => $first->customer_name,
        'customer_email' => $first->customer_email,
        'customer_phone' => $first->customer_phone,
        'customer_address' => $first->customer_address,
        'payment_status' => $first->payment_status,
        'payment_method' => $first->payment_method,
        'payment_gateway' => $first->payment_gateway,
        'purchased_at' => $first->purchased_at,
        'total_amount' => $total,
        'tickets' => $tickets
    ]);
}

// AJAX: Update purchase status (all tickets in a purchase)
add_action('wp_ajax_raffle_update_purchase_status', 'raffle_ajax_update_purchase_status');
function raffle_ajax_update_purchase_status() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $reference = sanitize_text_field($_POST['reference']);
    $status = sanitize_text_field($_POST['status']);
    
    $updated = $wpdb->update(
        $tickets_table,
        ['payment_status' => $status],
        ['purchase_reference' => $reference],
        ['%s'],
        ['%s']
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Failed to update purchase']);
    }
    
    wp_send_json_success(['message' => 'Purchase status updated successfully']);
}

// AJAX: Send receipt email
add_action('wp_ajax_raffle_send_receipt_email', 'raffle_ajax_send_receipt_email');
function raffle_ajax_send_receipt_email() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    $reference = sanitize_text_field($_POST['reference']);
    
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, r.title as raffle_title, r.rand_id as raffle_rand_id
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        WHERE t.purchase_reference = %s
        ORDER BY t.ticket_number
    ", $reference));
    
    if (empty($tickets)) {
        wp_send_json_error(['message' => 'Purchase not found']);
    }
    
    $first = $tickets[0];
    $gateway = $first->payment_gateway ?: 'manual';
    $result_url = get_permalink(get_page_by_path('payment-result')) .
        '?raffle_payment_success=1&ref=' . $reference . '&gateway=' . $gateway;
    
    $ticket_list = '';
    foreach ($tickets as $ticket) {
        $ticket_list .= '• Ticket #' . $ticket->ticket_number . ' - ₱' . number_format($ticket->amount_paid, 2) . "\n";
    }
    
    $to = $first->customer_email;
    $subject = $first->raffle_title . ' - Raffle Tickets' ;
    $message = "Hello " . $first->customer_name . ",\n\n";
    $message .= "Thank you for your purchase!\n\n";
    $message .= "Raffle: " . $first->raffle_title . "\n";
    $message .= "Reference: " . $reference . "\n\n";
    $message .= "Your Tickets:\n" . $ticket_list . "\n";
    $message .= "Total: ₱" . number_format(array_sum(array_column($tickets, 'amount_paid')), 2) . "\n\n";
    $message .= "View your receipt and tickets here:\n" . $result_url . "\n\n";
    $message .= "Good luck!";
    
    $sent = wp_mail($to, $subject, $message);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Receipt email sent to ' . $to]);
    } else {
        wp_send_json_error(['message' => 'Failed to send email']);
    }
}

// AJAX: Send test receipt
add_action('wp_ajax_raffle_send_test_receipt', 'raffle_ajax_send_test_receipt');
function raffle_ajax_send_test_receipt() {
    check_ajax_referer('raffle_nonce', 'nonce');
    
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    // Get a random paid purchase
    $random_purchase = $wpdb->get_results("
        SELECT t.purchase_reference
        FROM {$tickets_table} t
        WHERE t.payment_status = 'paid'
        GROUP BY t.purchase_reference
        ORDER BY RAND()
        LIMIT 1
    ");
    
    if (empty($random_purchase)) {
        wp_send_json_error(['message' => 'No paid purchases found for testing']);
    }
    
    $reference = $random_purchase[0]->purchase_reference;
    
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, r.title as raffle_title, r.rand_id as raffle_rand_id
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        WHERE t.purchase_reference = %s
        ORDER BY t.ticket_number
    ", $reference));
    
    $first = $tickets[0];
    $gateway = $first->payment_gateway ?: 'manual';
    $result_url = get_permalink(get_page_by_path('payment-result')) .
        '?raffle_payment_success=1&ref=' . $reference . '&gateway=' . $gateway;
    
    $ticket_list = '';
    foreach ($tickets as $ticket) {
        $ticket_list .= '• Ticket #' . $ticket->ticket_number . ' - ₱' . number_format($ticket->amount_paid, 2) . "\n";
    }
    
    $to = 'mocorroprint@gmail.com';
    $subject = '[TEST] Your Raffle Tickets - ' . $first->raffle_title;
    $message = "*** THIS IS A TEST EMAIL ***\n\n";
    $message .= "Hello " . $first->customer_name . ",\n\n";
    $message .= "Thank you for your purchase!\n\n";
    $message .= "Raffle: " . $first->raffle_title . "\n";
    $message .= "Reference: " . $reference . "\n\n";
    $message .= "Your Tickets:\n" . $ticket_list . "\n";
    $message .= "Total: ₱" . number_format(array_sum(array_column($tickets, 'amount_paid')), 2) . "\n\n";
    $message .= "View your receipt and tickets here:\n" . $result_url . "\n\n";
    $message .= "Good luck!\n\n";
    $message .= "---\nOriginal customer email: " . $first->customer_email;
    
    $sent = wp_mail($to, $subject, $message);
    
    if ($sent) {
        wp_send_json_success(['message' => 'Test email sent to mocorroprint@gmail.com with reference: ' . $reference]);
    } else {
        wp_send_json_error(['message' => 'Failed to send test email']);
    }
}

// AJAX: Generate raffle statement of account
function raffle_ajax_generate_soa() {
    check_ajax_referer('raffle_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';

    $raffle_id = intval($_POST['raffle_id']);
    $business_id = get_current_user_id();

    if ($raffle_id < 1) {
        wp_send_json_error(['message' => 'Invalid raffle selected.']);
    }

    $raffle = $wpdb->get_row($wpdb->prepare("
        SELECT *
        FROM {$raffles_table}
        WHERE id = %d AND business_id = %d
        LIMIT 1
    ", $raffle_id, $business_id));

    if (!$raffle) {
        wp_send_json_error(['message' => 'Raffle not found.']);
    }

    $summary = $wpdb->get_row($wpdb->prepare("
        SELECT
            COUNT(*) AS paid_tickets,
            COALESCE(SUM(amount_paid), 0) AS paid_amount
        FROM {$tickets_table}
        WHERE raffle_id = %d
          AND payment_status = 'paid'
    ", $raffle_id));

    $purchases = $wpdb->get_results($wpdb->prepare("
        SELECT
            COALESCE(NULLIF(t.purchase_reference, ''), CONCAT('TICKET-', MIN(t.id))) AS reference_code,
            MIN(t.purchased_at) AS purchased_at,
            MAX(t.customer_name) AS customer_name,
            MAX(t.customer_email) AS customer_email,
            MAX(t.customer_phone) AS customer_phone,
            MAX(t.payment_status) AS payment_status,
            MAX(t.payment_gateway) AS payment_gateway,
            MAX(t.payment_method) AS payment_method,
            COUNT(t.id) AS ticket_count,
            COALESCE(SUM(t.amount_paid), 0) AS total_amount,
            GROUP_CONCAT(t.ticket_number ORDER BY t.ticket_number SEPARATOR ', ') AS ticket_numbers
        FROM {$tickets_table} t
        WHERE t.raffle_id = %d
          AND t.payment_status = 'paid'
        GROUP BY COALESCE(NULLIF(t.purchase_reference, ''), CONCAT('TICKET-', t.id))
        ORDER BY MIN(t.purchased_at) DESC
    ", $raffle_id));

    wp_send_json_success([
        'html' => raffle_render_soa_document(
            $raffle,
            $summary,
            $purchases,
            raffle_get_business_profile()
        ),
    ]);
}
/**
 * Finance Export - Export Sales
 */
function bntm_ajax_raffle_fn_export_sales() {
    check_ajax_referer('raffle_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $ticket_id = intval($_POST['ticket_id']);
    $amount = floatval($_POST['amount']);
    
    // Check if already exported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$fn_table} WHERE reference_type='raffle_ticket' AND reference_id=%d",
        $ticket_id
    ));
    
    if ($exists) {
        wp_send_json_error('Ticket already exported.');
    }
    
    // Get ticket details
    $ticket = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, r.title FROM {$wpdb->prefix}raffle_tickets t 
         LEFT JOIN {$wpdb->prefix}raffle_raffles r ON t.raffle_id = r.id 
         WHERE t.id = %d",
        $ticket_id
    ));
    
    if (!$ticket) {
        wp_send_json_error('Ticket not found.');
    }
    
    // Insert into finance module
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'type' => 'income',
        'amount' => $amount,
        'category' => 'Raffle Sales',
        'notes' => 'Raffle: ' . $ticket->title . ' - Ticket: ' . $ticket->ticket_number,
        'reference_type' => 'raffle_ticket',
        'reference_id' => $ticket_id,
        'created_at' => current_time('mysql')
    ];
    
    $result = $wpdb->insert($fn_table, $data, [
        '%s','%d','%s','%f','%s','%s','%s','%d','%s'
    ]);
    
    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success('Ticket sale exported successfully!');
    } else {
        wp_send_json_error('Failed to export ticket sale.');
    }
}

/**
 * Finance Export - Revert Sale
 */
function bntm_ajax_raffle_fn_revert_sale() {
    check_ajax_referer('raffle_fn_action');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in.');
    }
    
    global $wpdb;
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $ticket_id = intval($_POST['ticket_id']);
    
    $result = $wpdb->delete($fn_table, [
        'reference_type' => 'raffle_ticket',
        'reference_id' => $ticket_id
    ], ['%s', '%d']);
    
    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success('Ticket sale reverted from Finance.');
    } else {
        wp_send_json_error('Failed to revert ticket sale.');
    }
}

// ============================================================================
// FRONTEND SHORTCODE - BUY FORM
// ============================================================================

/**
 * Raffle Buy Form Shortcode
 */
function bntm_shortcode_raffle_buy_form() {
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    // Get raffle ID from URL
    $raffle_rand_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($raffle_rand_id)) {
        return '<div class="bntm-notice bntm-notice-error">No raffle specified. Please use a valid raffle link.</div>';
    }
    
    // Get raffle details
    $raffle = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$raffles_table} WHERE rand_id = %s
    ", $raffle_rand_id));
    
    if (!$raffle) {
        return '<div class="bntm-notice bntm-notice-error">Raffle not found.</div>';
    }
    
    if ($raffle->status !== 'active') {
        return '<div class="bntm-notice bntm-notice-error">This raffle is no longer active.</div>';
    }
    
    // Get payment configuration
    $payment_source = bntm_get_setting('raffle_payment_source', 'manual');
    $manual_methods = [];
    $op_methods = [];
    
    if ($payment_source === 'manual') {
        $manual_methods = json_decode(bntm_get_setting('raffle_payment_methods', '[]'), true);
        if (!is_array($manual_methods)) {
            $manual_methods = [];
        }
    } else {
        // Get OP methods from Online Payment module
        if (bntm_is_module_enabled('op')) {
            $op_methods_table = $wpdb->prefix . 'op_payment_methods';
            // Get ALL active payment methods regardless of business_id for public raffle form
            $op_methods = $wpdb->get_results("
                SELECT * FROM {$op_methods_table} 
                WHERE is_active = 1
                ORDER BY gateway ASC
            ");
        }
    }
    
    $tickets_remaining = $raffle->total_tickets - $raffle->tickets_sold;
    
    ob_start();
    ?>
    <style>
    .raffle-buy-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .raffle-header {
        background: var(--bntm-primary, #3b82f6);
        color: white;
        padding: 40px 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        text-align: center;
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .raffle-header h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
    }
    .raffle-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 16px;
    }
    .raffle-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .raffle-info-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }
    .raffle-info-label {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 8px;
    }
    .raffle-info-value {
        font-size: 24px;
        font-weight: 600;
        color: #111827;
    }
    .raffle-form-section {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .raffle-form-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        color: #111827;
    }
    .payment-method-option {
        border: 2px solid #e5e7eb;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .payment-method-option:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .payment-method-option input[type="radio"] {
        margin-right: 10px;
    }
    .payment-method-option.selected {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .payment-details {
        background: #f9fafb;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
        display: none;
    }
    .payment-details.active {
        display: block;
    }
    .ticket-quantity-selector {
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 20px 0;
    }
    .quantity-btn {
        width: 40px;
        height: 40px;
        border: 2px solid #e5e7eb;
        background: white;
        border-radius: 8px;
        font-size: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .quantity-btn:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .quantity-input {
        width: 80px;
        height: 40px;
        text-align: center;
        font-size: 18px;
        font-weight: 600;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
    }
    .total-amount {
        background: #eff6ff;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        margin: 20px 0;
    }
    .total-amount-label {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 8px;
    }
    .total-amount-value {
        font-size: 32px;
        font-weight: 700;
        color: #3b82f6;
    }
    .submit-btn {
        width: 100%;
        padding: 16px;
        font-size: 18px;
        font-weight: 600;
        background: var(--bntm-primary, #3b82f6);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .submit-btn:hover {
        background: var(--bntm-primary-hover, var(--bntm-primary, #3b82f6));
        transform: translateY(-2px);
    }
    .submit-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .terms-section {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        font-size: 14px;
        color: #6b7280;
        max-height: 300px;
        overflow-y: auto;
    }
    </style>
    
    <div class="raffle-buy-container">
        <div class="raffle-header" <?php if ($raffle->header_image): ?>
            style="background: url('<?php echo esc_url($raffle->header_image); ?>') center/cover; position: relative;"
            <?php endif; ?>>
            <?php if ($raffle->header_image): ?>
            <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.5); border-radius: 12px;"></div>
            <?php endif; ?>
            <div style="position: relative; z-index: 1;">
                <h1><?php echo esc_html($raffle->title); ?></h1>
                <?php if ($raffle->description): ?>
                    <p><?php echo esc_html($raffle->description); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="raffle-info-grid">
            <div class="raffle-info-card">
                <div class="raffle-info-label">Ticket Price</div>
                <div class="raffle-info-value">₱<?php echo number_format($raffle->ticket_price, 2); ?></div>
            </div>
            
            <!--<div class="raffle-info-card">-->
            <!--    <div class="raffle-info-label">Tickets Remaining</div>-->
            <!--    <div class="raffle-info-value"><?php echo number_format($tickets_remaining); ?></div>-->
            <!--</div>-->
            
            <?php if ($raffle->draw_date): ?>
            <div class="raffle-info-card">
                <div class="raffle-info-label">Draw Date</div>
                <div class="raffle-info-value" style="font-size: 16px;">
                    <?php echo date('M d, Y', strtotime($raffle->draw_date)); ?><br>
                    <small><?php echo date('h:i A', strtotime($raffle->draw_date)); ?></small>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($tickets_remaining <= 0): ?>
            <div class="bntm-notice bntm-notice-error">
                Sorry, all tickets have been sold out!
            </div>
        <?php else: ?>
            
            <?php if ($raffle->instructions): ?>
            <div class="raffle-form-section">
                <h3>How to Purchase</h3>
                <div style="white-space: pre-line; color: #6b7280;">
                    <?php echo esc_html($raffle->instructions); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <form id="raffleBuyForm">
                <input type="hidden" name="raffle_rand_id" value="<?php echo esc_attr($raffle->rand_id); ?>">
                
                <div class="raffle-form-section">
                    <h3>Select Number of Tickets</h3>
                    <div class="ticket-quantity-selector">
                        <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                        <input type="number" id="ticketQuantity" name="quantity" class="quantity-input" 
                               value="1" min="1" max="<?php echo $tickets_remaining; ?>" readonly>
                        <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                        <!--<span style="color: #6b7280;">Max: <?php echo $tickets_remaining; ?></span>-->
                    </div>
                    
                    <div class="total-amount">
                        <div class="total-amount-label">Total Amount</div>
                        <div class="total-amount-value" id="totalAmount">₱<?php echo number_format($raffle->ticket_price, 2); ?></div>
                    </div>
                </div>
                
                <div class="raffle-form-section">
                    <h3>Your Information</h3>
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="customer_name" class="bntm-input" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Email Address *</label>
                        <input type="email" name="customer_email" class="bntm-input" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="customer_phone" class="bntm-input" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Address</label>
                        <textarea name="customer_address" class="bntm-input" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="raffle-form-section">
                    <h3>Payment Method</h3>
                    
                    <?php if ($payment_source === 'manual' && !empty($manual_methods)): ?>
                        <?php foreach ($manual_methods as $index => $method): ?>
                            <div class="payment-method-option" onclick="selectPaymentMethod('manual_<?php echo $index; ?>')">
                                <label style="cursor: pointer; display: block;">
                                    <input type="radio" name="payment_method" value="manual_<?php echo $index; ?>" 
                                           data-type="manual" required>
                                    <strong><?php echo esc_html($method['name']); ?></strong>
                                    <span style="color: #6b7280; margin-left: 10px;">
                                        (<?php echo esc_html(ucfirst($method['type'])); ?>)
                                    </span>
                                </label>
                                <div class="payment-details" id="details_manual_<?php echo $index; ?>">
                                    <?php if (!empty($method['account_name'])): ?>
                                        <strong>Account Name:</strong> <?php echo esc_html($method['account_name']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($method['account_number'])): ?>
                                        <strong>Account Number:</strong> <?php echo esc_html($method['account_number']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($method['description'])): ?>
                                        <div style="margin-top: 10px; color: #6b7280;">
                                            <?php echo nl2br(esc_html($method['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($payment_source === 'op' && !empty($op_methods)): ?>
                        <?php foreach ($op_methods as $method): ?>
                            <div class="payment-method-option" onclick="selectPaymentMethod('op_<?php echo $method->id; ?>')">
                                <label style="cursor: pointer; display: block;">
                                    <input type="radio" name="payment_method" value="op_<?php echo $method->id; ?>" 
                                           data-type="op" data-gateway="<?php echo $method->gateway; ?>" required>
                                    <strong><?php echo $method->name; ?></strong>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($payment_source === 'op' && !bntm_is_module_enabled('op')): ?>
                        <p style="color: #ef4444;"><strong>Error:</strong> The Online Payment module is not enabled. Please enable it in the module settings.</p>
                    <?php elseif ($payment_source === 'op' && empty($op_methods)): ?>
                        <p style="color: #ef4444;"><strong>Error:</strong> No payment methods configured in the Online Payment module. Please set up payment methods (PayPal, PayMaya, etc.) in the module settings.</p>
                    <?php else: ?>
                        <p style="color: #ef4444;">No payment methods configured. Please contact the organizer.</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($raffle->terms): ?>
                <div class="raffle-form-section">
                    <h3>Terms & Conditions</h3>
                    <div class="terms-section">
                        <?php echo nl2br(esc_html($raffle->terms)); ?>
                    </div>
                    <div style="margin-top: 15px;">
                        <label style="cursor: pointer;">
                            <input type="checkbox" name="agree_terms" required>
                            I agree to the terms and conditions
                        </label>
                    </div>
                </div>
                
                <?php endif; ?>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Purchase Tickets
                </button>
                
                <div id="formMessage" style="margin-top: 20px;"></div>
                <div class="raffle-form-section" style="text-align:center">
                    <p>Powered by:</p>
                        <img src="https://xucoe.bentamo.site/wp-content/uploads/2026/02/BNTM-Hub-ICONS-1-e1770864031121-1.png" width="150">
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const ticketPrice = <?php echo $raffle->ticket_price; ?>;
    const maxTickets = <?php echo $tickets_remaining; ?>;
    
    function updateTotal() {
        const quantity = parseInt(document.getElementById('ticketQuantity').value);
        const total = quantity * ticketPrice;
        document.getElementById('totalAmount').textContent = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    function increaseQuantity() {
        const input = document.getElementById('ticketQuantity');
        const current = parseInt(input.value);
        if (current < maxTickets) {
            input.value = current + 1;
            updateTotal();
        }
    }
    
    function decreaseQuantity() {
        const input = document.getElementById('ticketQuantity');
        const current = parseInt(input.value);
        if (current > 1) {
            input.value = current - 1;
            updateTotal();
        }
    }
    
    function selectPaymentMethod(methodId) {
        // Remove all selected states
        document.querySelectorAll('.payment-method-option').forEach(el => {
            el.classList.remove('selected');
        });
        document.querySelectorAll('.payment-details').forEach(el => {
            el.classList.remove('active');
        });
        
        // Add selected state
        event.currentTarget.classList.add('selected');
        const radio = event.currentTarget.querySelector('input[type="radio"]');
        radio.checked = true;
        
        // Show details if manual payment
        const details = document.getElementById('details_' + methodId);
        if (details) {
            details.classList.add('active');
        }
    }
    
    document.getElementById('raffleBuyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'raffle_purchase_tickets');
        
        const btn = document.getElementById('submitBtn');
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                if (json.data.redirect_url) {
                    // Redirect to payment gateway
                    window.location.href = json.data.redirect_url;
                } else {
                    // Show success message
                    document.getElementById('formMessage').innerHTML = 
                        '<div class="bntm-notice bntm-notice-success">' + 
                        '<strong>Success!</strong><br>' + json.data.message + 
                        (json.data.ticket_numbers ? '<br><br><strong>Your Ticket Numbers:</strong><br>' + 
                        json.data.ticket_numbers.join(', ') : '') +
                        '</div>';
                    
                    // Reset form
                    this.reset();
                    document.getElementById('ticketQuantity').value = 1;
                    updateTotal();
                    
                    // Scroll to message
                    document.getElementById('formMessage').scrollIntoView({behavior: 'smooth'});
                }
            } else {
                document.getElementById('formMessage').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(err => {
            console.error('Error:', err);
            document.getElementById('formMessage').innerHTML = 
                '<div class="bntm-notice bntm-notice-error">An error occurred. Please try again.</div>';
            btn.disabled = false;
            btn.textContent = originalText;
        });
    });
    </script>
    <?php
    return ob_get_clean();
    }/**
 * Purchase Tickets AJAX Handler
 */
/**
 * Purchase Tickets AJAX Handler - Modified to send confirmation email
 */
function bntm_ajax_raffle_purchase_tickets() {
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $raffle_rand_id = sanitize_text_field($_POST['raffle_rand_id']);
    $quantity = intval($_POST['quantity']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $customer_address = sanitize_textarea_field($_POST['customer_address']);
    $payment_method = sanitize_text_field($_POST['payment_method']);

    // Validate
    if (empty($raffle_rand_id) || $quantity < 1 || empty($customer_name) || empty($customer_email)) {
        wp_send_json_error(['message' => 'Please fill in all required fields']);
    }

    // Get raffle
    $raffle = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$raffles_table} WHERE rand_id = %s AND status = 'active'
    ", $raffle_rand_id));

    if (!$raffle) {
        wp_send_json_error(['message' => 'Raffle not found or no longer active']);
    }
    
    // Check availability
    $tickets_remaining = $raffle->total_tickets - $raffle->tickets_sold;
    if ($quantity > $tickets_remaining) {
        wp_send_json_error(['message' => 'Not enough tickets available']);
    }
    
    $total_amount = $quantity * $raffle->ticket_price;
    
    // Determine payment type
    $payment_type = '';
    $payment_gateway = '';
    $op_method_id = 0;
    
    if (strpos($payment_method, 'manual_') === 0) {
        $payment_type = 'manual';
        $manual_methods = json_decode(bntm_get_setting('raffle_payment_methods', '[]'), true);
        $method_index = intval(str_replace('manual_', '', $payment_method));
        if (isset($manual_methods[$method_index])) {
            $payment_gateway = $manual_methods[$method_index]['type'];
        }
    } else if (strpos($payment_method, 'op_') === 0) {
        $payment_type = 'op';
        $op_method_id = intval(str_replace('op_', '', $payment_method));
    }

    // Generate purchase reference
    $purchase_reference = 'RAFFLE-' . strtoupper(substr($raffle_rand_id, 0, 6)) . '-' . time();

    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        $ticket_numbers = [];
        
        // Create tickets
        for ($i = 0; $i < $quantity; $i++) {
            // Generate unique ticket number
            $ticket_number = raffle_generate_ticket_number($raffle->id);
            $ticket_numbers[] = $ticket_number;
            
            $ticket_data = [
                'rand_id' => bntm_rand_id(),
                'business_id' => $raffle->business_id,
                'raffle_id' => $raffle->id,
                'raffle_rand_id' => $raffle->rand_id,
                'ticket_number' => $ticket_number,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'customer_address' => $customer_address,
                'purchase_reference' => $purchase_reference,
                'payment_method' => $payment_type,
                'payment_status' => $payment_type === 'manual' ? 'pending' : 'pending',
                'payment_gateway' => $payment_gateway,
                'amount_paid' => $raffle->ticket_price
            ];
            
            $result = $wpdb->insert($tickets_table, $ticket_data, [
                '%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%f'
            ]);
            
            if (!$result) {
                throw new Exception('Failed to create ticket');
            }
        }
        
        // Process payment
        if ($payment_type === 'op') {
            $payment_result = raffle_process_op_payment(
                $purchase_reference, 
                $total_amount, 
                $op_method_id,
                [
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'raffle_title' => $raffle->title,
                    'ticket_numbers' => $ticket_numbers
                ]
            );
            
            if (!$payment_result['success']) {
                throw new Exception($payment_result['message']);
            }
            
            // Update tickets with transaction ID
            if (isset($payment_result['transaction_id'])) {
                $wpdb->query($wpdb->prepare("
                    UPDATE {$tickets_table} 
                    SET payment_transaction_id = %s 
                    WHERE purchase_reference = %s
                ", $payment_result['transaction_id'], $purchase_reference));
            }
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success([
                'message' => 'Redirecting to payment...',
                'redirect_url' => $payment_result['redirect_url'],
                'ticket_numbers' => $ticket_numbers
            ]);
        } else {
            // Manual payment - commit first
            $wpdb->query('COMMIT');
            
            // Send confirmation email using the raffle receipt email function
            raffle_send_purchase_confirmation($purchase_reference);
            
            // Redirect to payment result page for manual payments too
            $result_url = get_permalink(get_page_by_path('payment-result')) .
                '?raffle_payment_success=1&ref=' . $purchase_reference . '&gateway=manual';
            
            wp_send_json_success([
                'message' => 'Redirecting to confirmation...',
                'redirect_url' => $result_url,
                'ticket_numbers' => $ticket_numbers
            ]);
        }
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}

/**
 * Helper function to send purchase confirmation email
 */
function raffle_send_purchase_confirmation($purchase_reference) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, r.title as raffle_title, r.rand_id as raffle_rand_id
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        WHERE t.purchase_reference = %s
        ORDER BY t.ticket_number
    ", $purchase_reference));
    
    if (empty($tickets)) {
        return false;
    }
    
    $first = $tickets[0];
    $gateway = $first->payment_gateway ?: 'manual';
    $result_url = get_permalink(get_page_by_path('payment-result')) .
        '?raffle_payment_success=1&ref=' . $purchase_reference . '&gateway=' . $gateway;
    
    $ticket_list = '';
    foreach ($tickets as $ticket) {
        $ticket_list .= '• Ticket #' . $ticket->ticket_number . ' - ₱' . number_format($ticket->amount_paid, 2) . "\n";
    }
    
    $to = $first->customer_email;
    $subject = 'Your Raffle Purchase Confirmation - ' . $first->raffle_title;
    $message = "Hello " . $first->customer_name . ",\n\n";
    $message .= "Thank you for your raffle ticket purchase!\n\n";
    $message .= "Raffle: " . $first->raffle_title . "\n";
    $message .= "Reference: " . $purchase_reference . "\n";
    $message .= "Payment Status: " . strtoupper($first->payment_status) . "\n\n";
    $message .= "Your Tickets:\n" . $ticket_list . "\n";
    $message .= "Total: ₱" . number_format(array_sum(array_column($tickets, 'amount_paid')), 2) . "\n\n";
    
    if ($first->payment_status === 'pending') {
        $message .= "Please complete your payment to confirm your tickets.\n\n";
    }
    
    $message .= "View your receipt and tickets here:\n" . $result_url . "\n\n";
    $message .= "Good luck!\n\n";
    $message .= "---\n";
    $message .= "If you have any questions, please contact us.";
    
    return wp_mail($to, $subject, $message);
}

/**
 * Raffle Wheel Shortcode for Drawing Winners
 */
function bntm_shortcode_raffle_wheel() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the raffle wheel.</div>';
    }
    
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    // Get raffle ID from URL
    $raffle_id = isset($_GET['raffle_id']) ? intval($_GET['raffle_id']) : 0;
    
    if (!$raffle_id) {
        // Show raffle selection
        $raffles = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, 
                COUNT(t.id) as total_tickets,
                COUNT(CASE WHEN t.payment_status = 'paid' THEN 1 END) as paid_tickets
            FROM {$raffles_table} r
            LEFT JOIN {$tickets_table} t ON r.id = t.raffle_id
            WHERE r.status = 'active' AND r.winner_name IS NULL
            GROUP BY r.id
            ORDER BY r.created_at DESC
        ", $business_id));
        
        ob_start();
        ?>
        <div class="bntm-container">
            <h2>Select a Raffle to Draw Winner</h2>
            
            <?php if (empty($raffles)): ?>
                <p style="color: #6b7280; text-align: center; padding: 40px 0;">
                    No active raffles available for drawing.
                </p>
            <?php else: ?>
                <div style="display: grid; gap: 20px; max-width: 800px; margin: 0 auto;">
                    <?php foreach ($raffles as $raffle): ?>
                        <div style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; background: white;">
                            <h3 style="margin-top: 0;"><?php echo esc_html($raffle->title); ?></h3>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 15px 0;">
                                <div>
                                    <small style="color: #6b7280;">Ticket Price</small><br>
                                    <strong>₱<?php echo number_format($raffle->ticket_price, 2); ?></strong>
                                </div>
                                <div>
                                    <small style="color: #6b7280;">Total Tickets</small><br>
                                    <strong><?php echo $raffle->total_tickets; ?></strong>
                                </div>
                                <div>
                                    <small style="color: #6b7280;">Paid Tickets</small><br>
                                    <strong style="color: #059669;"><?php echo $raffle->paid_tickets; ?></strong>
                                </div>
                            </div>
                            <?php if ($raffle->paid_tickets > 0): ?>
                                <a href="?raffle_id=<?php echo $raffle->id; ?>" class="bntm-btn-primary">
                                    Draw Winner
                                </a>
                            <?php else: ?>
                                <button class="bntm-btn-secondary" disabled>
                                    No Paid Tickets
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Get raffle details
    $raffle = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$raffles_table} 
        WHERE id = %d 
    ", $raffle_id, $business_id));
    
    if (!$raffle) {
        return '<div class="bntm-notice bntm-notice-error">Raffle not found.</div>';
    }
    
    if ($raffle->winner_name) {
        return '<div class="bntm-notice bntm-notice-error">A winner has already been drawn for this raffle.</div>';
    }
    
    // Get all paid tickets
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$tickets_table}
        WHERE raffle_id = %d AND payment_status = 'paid'
        ORDER BY ticket_number
    ", $raffle_id));
    
    if (empty($tickets)) {
        return '<div class="bntm-notice bntm-notice-error">No paid tickets available for drawing.</div>';
    }
    
    $nonce = wp_create_nonce('raffle_wheel_nonce');
    
    ob_start();
    ?>
    <style>
    .wheel-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
        text-align: center;
    }
    .wheel-header {
        margin-bottom: 30px;
    }
    .wheel-header h1 {
        margin: 0 0 10px 0;
        color: #111827;
    }
    .wheel-stats {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin: 20px 0;
    }
    .wheel-stat {
        background: #f9fafb;
        padding: 15px 25px;
        border-radius: 8px;
    }
    .wheel-stat-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 5px;
    }
    .wheel-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    .wheel-canvas-wrapper {
        position: relative;
        width: 500px;
        height: 500px;
        margin: 40px auto;
    }
    #raffleWheel {
        border-radius: 50%;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    }
    #raffleWheel.idle {
    animation: idleRotate 20s linear infinite;
}
@keyframes idleRotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
    .wheel-pointer {
        position: absolute;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 20px solid transparent;
        border-right: 20px solid transparent;
        border-top: 40px solid #ef4444;
        z-index: 10;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
    }
    .wheel-center {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 50%;
        border: 5px solid #3b82f6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #3b82f6;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 5;
    }
    .wheel-controls {
        margin: 40px 0;
    }
    .spin-btn {
        padding: 20px 60px;
        font-size: 24px;
        font-weight: 700;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
        transition: all 0.3s;
        text-transform: uppercase;
    }
    .spin-btn:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(59, 130, 246, 0.6);
    }
    .spin-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .winner-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        animation: fadeIn 0.3s;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .winner-content {
        background: white;
        margin: 10% auto;
        padding: 0;
        width: 90%;
        max-width: 600px;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideIn 0.5s;
    }
    @keyframes slideIn {
        from {
            transform: translateY(-100px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    .winner-header {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: white;
        padding: 30px;
        border-radius: 16px 16px 0 0;
        text-align: center;
    }
    .winner-header h2 {
        margin: 0;
        font-size: 36px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        background: #fbbf24;
        animation: confetti-fall 3s linear infinite;
    }
    @keyframes confetti-fall {
        to {
            transform: translateY(100vh) rotate(360deg);
        }
    }
    .winner-body {
        padding: 40px;
        text-align: center;
    }
    .winner-ticket {
        background: #eff6ff;
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        border: 3px dashed #3b82f6;
    }
    .winner-ticket-label {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 8px;
    }
    .winner-ticket-number {
        font-size: 48px;
        font-weight: 900;
        color: #3b82f6;
        font-family: 'Courier New', monospace;
    }
    .winner-details {
        margin: 30px 0;
        text-align: left;
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
    }
    .winner-detail {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .winner-detail:last-child {
        border-bottom: none;
    }
    .winner-detail-label {
        color: #6b7280;
        font-weight: 600;
    }
    .winner-detail-value {
        color: #111827;
        font-weight: 700;
    }
    .winner-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    .confirm-btn {
        flex: 1;
        padding: 15px;
        font-size: 18px;
        font-weight: 600;
        background: #059669;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .confirm-btn:hover {
        background: #047857;
        transform: translateY(-2px);
    }
    .cancel-btn {
        flex: 1;
        padding: 15px;
        font-size: 18px;
        font-weight: 600;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .cancel-btn:hover {
        background: #dc2626;
        transform: translateY(-2px);
    }
    </style>
    
    <div class="wheel-container">
        <div class="wheel-header">
            <a href="?" style="color: #3b82f6; text-decoration: none; margin-bottom: 10px; display: inline-block;">
                ← Back to Raffle List
            </a>
            <h1><?php echo esc_html($raffle->title); ?></h1>
            
            <p>Powered by:</p>
            <img src="https://xucoe.bentamo.site/wp-content/uploads/2026/02/BNTM-Hub-ICONS-1-e1770864031121-1.png" width="150">
        </div>
        
        <div class="wheel-stats" style=" display: none;"s>
            <div class="wheel-stat">
                <div class="wheel-stat-label">Total Tickets</div>
                <div class="wheel-stat-value"><?php echo count($tickets); ?></div>
            </div>
            <div class="wheel-stat">
                <div class="wheel-stat-label">Ticket Price</div>
                <div class="wheel-stat-value">₱<?php echo number_format($raffle->ticket_price, 2); ?></div>
            </div>
        </div>
        
        <div class="wheel-canvas-wrapper">
            <div class="wheel-pointer"></div>
            <canvas id="raffleWheel" width="500" height="500"></canvas>
            <div class="wheel-center">SPIN</div>
        </div>
        
        <div class="wheel-controls">
            <button class="spin-btn" id="spinBtn" onclick="spinWheel()">
                SPIN THE WHEEL
            </button>
            
        </div>
        <div class="wheel-header">
</div>
    </div>
    
    <!-- Winner Modal -->
    <div id="winnerModal" class="winner-modal">
        <div class="winner-content">
            <div class="winner-header">
                <h2>WE HAVE A WINNER!</h2>
            </div>
            <div class="winner-body">
                <div class="winner-ticket">
                    <div class="winner-ticket-label">Winning Ticket Number</div>
                    <div class="winner-ticket-number" id="winningTicketNumber"></div>
                </div>
                
                <div class="winner-details" id="winnerDetails">
                    <!-- Details will be inserted here -->
                </div>
                <div class="winner-actions" style="display: flex; flex-direction: column; gap: 10px;">
                    <button class="confirm-btn" onclick="confirmWinner()" style="width: 100%;display:none">
                        Confirm Winner & Save to Database
                    </button>
                    <div style="display: flex; gap: 10px;">
                        <button class="cancel-btn" onclick="removeWinnerAndRespin()" style="background: #6b7280;">
                            Remove & Spin Again
                        </button>
                        <button class="cancel-btn" onclick="cancelWinner()">
                            Just Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
  <script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
const raffleId = <?php echo $raffle_id; ?>;
const tickets = <?php echo json_encode(array_map(function($t) {
    return [
        'id' => $t->id,
        'ticket_number' => $t->ticket_number,
        'customer_name' => $t->customer_name,
        'customer_email' => $t->customer_email,
        'customer_phone' => $t->customer_phone
    ];
}, $tickets)); ?>;

let isSpinning = false;
let selectedWinner = null;
const canvas = document.getElementById('raffleWheel');
const ctx = canvas.getContext('2d');
const colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#ef4444'];
// Use a let variable for tickets so we can modify it
let activeTickets = [...tickets]; 
let currentWinnerIndex = -1;

// Update drawWheel to use activeTickets instead of the static tickets array
function drawWheel(rotation = 0) {
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = 240;
    const sliceAngle = (2 * Math.PI) / activeTickets.length;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    for (let i = 0; i < activeTickets.length; i++) {
        const startAngle = (i * sliceAngle) + rotation;
        const endAngle = startAngle + sliceAngle;
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, startAngle, endAngle);
        ctx.lineTo(centerX, centerY);
        ctx.fillStyle = colors[i % colors.length];
        ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.3)';
        ctx.stroke();
        
        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.rotate(startAngle + sliceAngle / 2);
        ctx.textAlign = 'right';
        ctx.fillStyle = 'white';
        ctx.font = activeTickets.length > 30 ? 'bold 10px Arial' : 'bold 14px Arial';
        ctx.fillText(activeTickets[i].ticket_number, radius - 15, 5);
        ctx.restore();
    }
}

function removeWinnerAndRespin() {
    if (currentWinnerIndex === -1) return;

    // 1. Close Modal
    document.getElementById('winnerModal').style.display = 'none';

    // 2. Remove the winning ticket from the active list
    activeTickets.splice(currentWinnerIndex, 1);

    // 3. Reset state
    isSpinning = false;
    currentWinnerIndex = -1;
    selectedWinner = null;

    // 4. Redraw wheel with new slices
    if (activeTickets.length > 0) {
        drawWheel(0);
        // 5. Trigger spin again automatically
        setTimeout(spinWheel, 500);
    } else {
        alert("No more tickets left in the raffle!");
        location.reload();
    }
}

function spinWheel() {
    if (isSpinning || activeTickets.length === 0) return;
    
    isSpinning = true;
    canvas.classList.remove('idle');
    
    const btn = document.getElementById('spinBtn');
    btn.disabled = true;
    btn.textContent = 'SPINNING...';
    
    // Pick winner from the UPDATED activeTickets list
    currentWinnerIndex = Math.floor(Math.random() * activeTickets.length);
    selectedWinner = activeTickets[currentWinnerIndex];
    
    const randomSliceOffset = 0.2 + (Math.random() * 0.6);
    const sliceAngle = (2 * Math.PI) / activeTickets.length;
    const pointerPosition = 1.5 * Math.PI; 
    
    const targetFinalAngle = pointerPosition - (currentWinnerIndex * sliceAngle) - (sliceAngle * randomSliceOffset);
    const extraSpins = 8 + Math.floor(Math.random() * 4); 
    const totalRotationTarget = (extraSpins * 2 * Math.PI) + targetFinalAngle;
    
    let startTimestamp = null;
    const duration = 8000; 

    function animate(timestamp) {
        if (!startTimestamp) startTimestamp = timestamp;
        const elapsed = timestamp - startTimestamp;
        const progress = Math.min(elapsed / duration, 1);

        let easing = progress < 0.2 
            ? Math.pow(progress / 0.2, 3) * 0.1 
            : 0.1 + (0.9 * (1 - Math.pow(1 - (progress - 0.2) / 0.8, 6)));
        
        drawWheel(totalRotationTarget * easing);

        if (progress < 1) {
            window.requestAnimationFrame(animate);
        } else {
            setTimeout(showWinner, 800);
        }
    }
    window.requestAnimationFrame(animate);
}

// Initial Call
window.onload = () => drawWheel(0);
    
    function showWinner() {
        document.getElementById('winningTicketNumber').textContent = selectedWinner.ticket_number;
        document.getElementById('winnerDetails').innerHTML = `
            <div class="winner-detail">
                <span class="winner-detail-label">Name:</span>
                <span class="winner-detail-value">${selectedWinner.customer_name}</span>
            </div>
            <div class="winner-detail">
                <span class="winner-detail-label">Email:</span>
                <span class="winner-detail-value">${selectedWinner.customer_email}</span>
            </div>
            <div class="winner-detail">
                <span class="winner-detail-label">Phone:</span>
                <span class="winner-detail-value">${selectedWinner.customer_phone || 'N/A'}</span>
            </div>
        `;
        
        document.getElementById('winnerModal').style.display = 'block';
    }
    
    function cancelWinner() {
        document.getElementById('winnerModal').style.display = 'none';
        isSpinning = false;
        selectedWinner = null;
        document.getElementById('spinBtn').disabled = false;
        document.getElementById('spinBtn').textContent = 'SPIN THE WHEEL';
    }
    
    function confirmWinner() {
        if (!selectedWinner) return;
        
        const formData = new FormData();
        formData.append('action', 'raffle_confirm_winner');
        formData.append('raffle_id', raffleId);
        formData.append('ticket_id', selectedWinner.id);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        document.querySelector('.confirm-btn').disabled = true;
        document.querySelector('.confirm-btn').textContent = 'Confirming...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                alert('Winner confirmed!\n\nTicket: ' + selectedWinner.ticket_number + '\nWinner: ' + selectedWinner.customer_name);
                window.location.href = '?';
            } else {
                alert('Error: ' + json.data.message);
                document.querySelector('.confirm-btn').disabled = false;
                document.querySelector('.confirm-btn').textContent = 'Confirm Winner';
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * AJAX: Confirm Winner
 */
add_action('wp_ajax_raffle_confirm_winner', 'raffle_ajax_confirm_winner');
function raffle_ajax_confirm_winner() {
    check_ajax_referer('raffle_wheel_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $raffle_id = intval($_POST['raffle_id']);
    $ticket_id = intval($_POST['ticket_id']);
    $business_id = get_current_user_id();
    
    // Verify raffle belongs to user
    $raffle = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$raffles_table}
        WHERE id = %d AND business_id = %d
    ", $raffle_id, $business_id));
    
    if (!$raffle) {
        wp_send_json_error(['message' => 'Raffle not found']);
    }
    
    if ($raffle->winner_name) {
        wp_send_json_error(['message' => 'Winner already confirmed']);
    }
    
    // Get winning ticket
    $ticket = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$tickets_table}
        WHERE id = %d AND raffle_id = %d
    ", $ticket_id, $raffle_id));
    
    if (!$ticket) {
        wp_send_json_error(['message' => 'Ticket not found']);
    }
    
    // Update raffle with winner
    $wpdb->update(
        $raffles_table,
        [
            'winner_ticket_number' => $ticket->ticket_number,
            'winner_name' => $ticket->customer_name,
            'winner_email' => $ticket->customer_email,
            'winner_phone' => $ticket->customer_phone,
            'winner_drawn_at' => current_time('mysql')
        ],
        ['id' => $raffle_id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );
    
    // Mark ticket as winner
    $wpdb->update(
        $tickets_table,
        ['is_winner' => 1],
        ['id' => $ticket_id],
        ['%d'],
        ['%d']
    );
    
    wp_send_json_success(['message' => 'Winner confirmed successfully']);
}

// Handle payment success redirect
add_action('template_redirect', 'raffle_handle_payment_success', 5);
function raffle_handle_payment_success() {
    if (!isset($_GET['raffle_payment_success']) || $_GET['raffle_payment_success'] != 1) {
        return;
    }
    
    if (!isset($_GET['ref'])) {
        return;
    }
    
    $purchase_reference = sanitize_text_field($_GET['ref']);
    $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
    
    if (empty($gateway)) {
        return;
    }
    
    raffle_complete_payment($purchase_reference, $gateway);
}

// ============================================================================
// PAYMENT RESULT SHORTCODE
// ============================================================================

/**
 * Payment Result Page Shortcode
 */
function bntm_shortcode_raffle_payment_result() {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    
    // Get parameters
    $reference = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
    $gateway = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
    $status = isset($_GET['raffle_payment_success']) && $_GET['raffle_payment_success'] == 1 ? 'success' : 'failure';
    
    if (empty($reference)) {
        return '<div class="bntm-notice bntm-notice-error">Invalid payment reference.</div>';
    }
    
    // Get tickets and raffle details
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, r.title as raffle_title, r.draw_date, r.ticket_price,r.terms,r.instructions,r.header_image
        FROM {$tickets_table} t
        LEFT JOIN {$raffles_table} r ON t.raffle_id = r.id
        WHERE t.purchase_reference = %s
        ORDER BY t.ticket_number ASC
    ", $reference));
    
    if (empty($tickets)) {
        return '<div class="bntm-notice bntm-notice-error">Payment reference not found.</div>';
    }
    
    $first_ticket = $tickets[0];
    $total_amount = count($tickets) * $first_ticket->ticket_price;
    $ticket_numbers = array_column($tickets, 'ticket_number');
    
    ob_start();
    ?>
    <style>
    .payment-result-container {
        max-width: 700px;
        margin: 40px auto;
        padding: 20px;
    }
    .result-header {
        text-align: center;
        padding: 40px 20px;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    .result-header.success {
        background: var(--bntm-primary-hover, var(--bntm-primary, #3b82f6));
        color: white;
    }
    .result-header.failure {
        background: var(--bntm-primary-hover, var(--bntm-primary, #3b82f6));
        color: white;
    }
    .result-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }
    .result-header h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
    }
    .result-header p {
        margin: 0;
        font-size: 16px;
        opacity: 0.9;
    }
    .receipt-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        padding: 30px;
        margin-bottom: 20px;
    }
    .receipt-header {
        text-align: center;
        padding-bottom: 20px;
        border-bottom: 2px dashed #e5e7eb;
        margin-bottom: 20px;
    }
    .receipt-header h2 {
        margin: 0 0 5px 0;
        color: #111827;
    }
    .receipt-header .reference {
        color: #6b7280;
        font-size: 14px;
        font-family: monospace;
    }
    .receipt-section {
        margin-bottom: 25px;
    }
    .receipt-section h3 {
        margin: 0 0 15px 0;
        color: #374151;
        font-size: 16px;
        font-weight: 600;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 8px;
    }
    .receipt-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    .receipt-row:last-child {
        border-bottom: none;
    }
    .receipt-label {
        color: #6b7280;
        font-size: 14px;
    }
    .receipt-value {
        color: #111827;
        font-weight: 500;
        font-size: 14px;
        text-align: right;
    }
    .ticket-numbers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }
    .ticket-number-badge {
        background: #eff6ff;
        color: #1e40af;
        padding: 10px;
        border-radius: 6px;
        text-align: center;
        font-weight: 600;
        font-family: monospace;
        font-size: 13px;
        border: 2px solid #dbeafe;
    }
    .total-section {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
    }
    .total-label {
        font-size: 18px;
        font-weight: 600;
        color: #374151;
    }
    .total-amount {
        font-size: 28px;
        font-weight: 700;
        color: #10b981;
    }
    .receipt-footer {
        text-align: center;
        padding-top: 20px;
        border-top: 2px dashed #e5e7eb;
        margin-top: 20px;
        color: #6b7280;
        font-size: 14px;
    }
    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
    }
    .btn-print {
        padding: 12px 30px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-print:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }
    .btn-secondary {
        padding: 12px 30px;
        background: white;
        color: #374151;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-block;
    }
    .btn-secondary:hover {
        border-color: #d1d5db;
        background: #f9fafb;
    }
    @media print {
        .action-buttons, .btn-print, .btn-secondary {
            display: none;
        }
        .payment-result-container {
            max-width: 100%;
        }
    }
    .status-badge {
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        margin-top: 10px;
    }
    .status-badge.success {
        background: #d1fae5;
        color: #065f46;
    }
    .status-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }
    .status-badge.failed {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .raffle-header {
        background: var(--bntm-primary, #3b82f6);
        color: white;
        padding: 40px 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        text-align: center;
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .raffle-header h1 {
        margin: 0 0 10px 0;
        font-size: 32px;
    }
    .raffle-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 16px;
    }
    </style>
    
    <div class="payment-result-container">
        
        <?php if ($status === 'success'): ?>
            <div class="result-header success">
                <div class="result-icon">✓</div>
                <h1>Payment Successful!</h1>
                <p>Your raffle tickets have been confirmed</p>
            </div>
        <?php else: ?>
            <div class="result-header failure">
                <div class="result-icon">✗</div>
                <h1>Payment Failed</h1>
                <p>There was an issue processing your payment</p>
            </div>
        <?php endif; ?>
        
        <div class="receipt-container">
            <div class="receipt-header">
                <h2> Raffle Ticket Receipt</h2>
                <div class="reference">Reference: <?php echo esc_html($reference); ?></div>
            </div>
            
            <!-- Raffle Information -->
            <div class="receipt-section">
                <h3>Raffle Details</h3>
                <div class="receipt-row">
                    <span class="receipt-label">Raffle Name:</span>
                    <span class="receipt-value"><?php echo esc_html($first_ticket->raffle_title); ?></span>
                </div>
                <?php if ($first_ticket->draw_date): ?>
                <div class="receipt-row">
                    <span class="receipt-label">Draw Date:</span>
                    <span class="receipt-value">
                        <?php echo date('F d, Y - h:i A', strtotime($first_ticket->draw_date)); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Customer Information -->
            <div class="receipt-section">
                <h3>Customer Information</h3>
                <div class="receipt-row">
                    <span class="receipt-label">Name:</span>
                    <span class="receipt-value"><?php echo esc_html($first_ticket->customer_name); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Email:</span>
                    <span class="receipt-value"><?php echo esc_html($first_ticket->customer_email); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Phone:</span>
                    <span class="receipt-value"><?php echo esc_html($first_ticket->customer_phone); ?></span>
                </div>
                <?php if ($first_ticket->customer_address): ?>
                <div class="receipt-row">
                    <span class="receipt-label">Address:</span>
                    <span class="receipt-value"><?php echo esc_html($first_ticket->customer_address); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Ticket Numbers -->
            <div class="receipt-section">
                <h3>Your Ticket Numbers (<?php echo count($tickets); ?> ticket<?php echo count($tickets) > 1 ? 's' : ''; ?>)</h3>
                <div class="ticket-numbers-grid">
                    <?php foreach ($ticket_numbers as $ticket_num): ?>
                        <div class="ticket-number-badge"><?php echo esc_html($ticket_num); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="receipt-section">
                <h3>Payment Information</h3>
                <div class="receipt-row">
                    <span class="receipt-label">Payment Method:</span>
                    <span class="receipt-value"><?php echo esc_html(ucfirst($gateway)); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Payment Status:</span>
                    <span class="receipt-value">
                        <span class="status-badge <?php echo $first_ticket->payment_status; ?>">
                            <?php 
                            if ($gateway === 'manual' && $first_ticket->payment_status === 'pending') {
                                echo 'Pending Verification';
                            } else {
                                echo esc_html(ucfirst($first_ticket->payment_status)); 
                            }
                            ?>
                        </span>
                    </span>
                </div>
                <?php if ($first_ticket->payment_transaction_id): ?>
                <div class="receipt-row">
                    <span class="receipt-label">Transaction ID:</span>
                    <span class="receipt-value" style="font-family: monospace; font-size: 12px;">
                        <?php echo esc_html($first_ticket->payment_transaction_id); ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="receipt-row">
                    <span class="receipt-label">Purchase Date:</span>
                    <span class="receipt-value">
                        <?php echo date('F d, Y - h:i A', strtotime($first_ticket->purchased_at)); ?>
                    </span>
                </div>
            </div>

            <?php if ($gateway === 'manual'): ?>
                <?php 
                // Get the manual payment method details
                $manual_methods = json_decode(bntm_get_setting('raffle_payment_methods', '[]'), true);
                $payment_method_index = str_replace('manual_', '', $first_ticket->payment_method);
                if (isset($manual_methods[$payment_method_index])):
                    $method = $manual_methods[$payment_method_index];
                ?>
                <!-- Payment Instructions -->
                <div class="receipt-section" style="background: #fef3c7; padding: 20px; border-radius: 8px; border-left: 4px solid #f59e0b;">
                    <h3 style="color: #92400e; margin-top: 0;">⚠️ Payment Instructions</h3>
                    <div style="color: #78350f; margin-bottom: 15px;">
                        <strong>Please complete your payment to:</strong>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Payment Method:</span>
                        <span class="receipt-value"><?php echo esc_html($method['name']); ?></span>
                    </div>
                    <?php if (!empty($method['account_name'])): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Account Name:</span>
                        <span class="receipt-value"><?php echo esc_html($method['account_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($method['account_number'])): ?>
                    <div class="receipt-row">
                        <span class="receipt-label">Account Number:</span>
                        <span class="receipt-value" style="font-family: monospace; font-weight: 700;">
                            <?php echo esc_html($method['account_number']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($method['description'])): ?>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #fcd34d;">
                        <div style="color: #78350f; white-space: pre-line;">
                            <?php echo esc_html($method['description']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div style="margin-top: 15px; padding: 12px; background: white; border-radius: 6px;">
                        <strong style="color: #92400e;">Reference Number to include:</strong><br>
                        <span style="font-family: monospace; font-size: 16px; color: #000;">
                            <?php echo esc_html($reference); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            <!-- Total -->
            <div class="total-section">
                <div class="total-row">
                    <span class="total-label">Total Amount Paid:</span>
                    <span class="total-amount">₱<?php echo number_format($total_amount, 2); ?></span>
                </div>
            </div>
            <div class="receipt-footer">
                <?php if ($status === 'success'): ?>
                    <?php if ($gateway === 'manual'): ?>
                        <p><strong>Important:</strong> Your tickets are reserved pending payment verification.</p>
                        <p>Please complete your payment using the provided details and contact the organizer.</p>
                        <p>Keep your ticket numbers and reference number safe!</p><br>
                    <p><strong>Instructions:</strong></p>
                    <p>  <?php echo nl2br(esc_html($first_ticket->instructions)); ?></p>
                    <?php else: ?>
                        <p><strong>Important:</strong> Please save this receipt for your records.</p>
                        <p>Keep your ticket numbers safe. You will be notified via email if you win!</p><br>
                    <p><strong>Instructions:</strong></p>
                    <p>  <?php echo nl2br(esc_html($first_ticket->instructions)); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong>Note:</strong> Your payment was not completed successfully.</p>
                    <p>Your tickets are still reserved. Please contact support or try again.</p>
                <?php endif; ?>
            </div>
           
        </div>
        
        <div class="action-buttons">
            <button onclick="window.print()" class="btn-print">🖨️ Print Receipt</button>
            <a href="<?php echo home_url(); ?>" class="btn-secondary">← Back to Home</a>
        </div>
    </div>
    
    <script>
    // Auto-mark payment as complete on success
    <?php if ($status === 'success' && $first_ticket->payment_status !== 'paid'): ?>
    (function() {
        // Trigger payment completion via AJAX
        const formData = new FormData();
        formData.append('action', 'raffle_complete_payment_ajax');
        formData.append('reference', '<?php echo esc_js($reference); ?>');
        formData.append('gateway', '<?php echo esc_js($gateway); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        }).then(r => r.json()).then(json => {
            if (json.success) {
                console.log('Payment marked as complete');
            }
        });
    })();
    <?php endif; ?>
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLER FOR PAYMENT COMPLETION
// ============================================================================

add_action('wp_ajax_raffle_complete_payment_ajax', 'bntm_ajax_raffle_complete_payment');
add_action('wp_ajax_nopriv_raffle_complete_payment_ajax', 'bntm_ajax_raffle_complete_payment');

function bntm_ajax_raffle_complete_payment() {
    $reference = sanitize_text_field($_POST['reference']);
    $gateway = sanitize_text_field($_POST['gateway']);
    
    $result = raffle_complete_payment($reference, $gateway);
    
    if ($result) {
        wp_send_json_success(['message' => 'Payment completed']);
    } else {
        wp_send_json_error(['message' => 'Failed to complete payment']);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function raffle_get_business_profile() {
    return [
        'name' => bntm_get_setting('ps_business_name', bntm_get_setting('site_title', get_bloginfo('name'))),
        'address' => bntm_get_setting('ps_business_address', ''),
        'contact' => bntm_get_setting('ps_business_contact', ''),
        'email' => bntm_get_setting('admin_email', get_option('admin_email')),
        'footer' => bntm_get_setting('ps_doc_footer', 'This document is computer-generated and valid without signature.'),
    ];
}

function raffle_render_soa_document($raffle, $summary, $purchases, array $business) {
    $paid_tickets = isset($summary->paid_tickets) ? (int) $summary->paid_tickets : 0;
    $paid_amount = isset($summary->paid_amount) ? (float) $summary->paid_amount : 0.0;

    $total_capacity = (int) $raffle->total_tickets;
    $available_tickets = max(0, $total_capacity - $paid_tickets);
    $potential_revenue = $total_capacity * (float) $raffle->ticket_price;
    $unsold_tickets = max(0, $total_capacity - $paid_tickets);
    $unsold_value = $unsold_tickets * (float) $raffle->ticket_price;

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo esc_html($raffle->title); ?> - SOA</title>
        <style>
            @page { size: A4 portrait; margin: 14mm; }
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, sans-serif; color: #111827; background: #f3f4f6; }
            .page { max-width: 960px; margin: 0 auto; background: #fff; padding: 32px; }
            .toolbar { max-width: 960px; margin: 20px auto 0; display: flex; gap: 10px; justify-content: flex-end; }
            .toolbar button { border: 0; border-radius: 8px; padding: 10px 18px; cursor: pointer; font-weight: 600; }
            .toolbar .print-btn { background: #2563eb; color: #fff; }
            .toolbar .close-btn { background: #e5e7eb; color: #111827; }
            .header { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #111827; padding-bottom: 18px; margin-bottom: 24px; }
            .company h1 { margin: 0 0 6px; font-size: 28px; }
            .company p, .meta p { margin: 4px 0; color: #4b5563; }
            .doc-title { text-align: right; }
            .doc-title h2 { margin: 0 0 6px; font-size: 24px; letter-spacing: 0.04em; }
            .section-title { margin: 28px 0 12px; font-size: 16px; font-weight: 700; color: #111827; }
            .detail-grid, .summary-grid { display: grid; gap: 12px; }
            .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .summary-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 16px; background: #fff; }
            .card .label { font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 8px; letter-spacing: 0.04em; }
            .card .value { font-size: 20px; font-weight: 700; color: #111827; }
            .card .sub { margin-top: 6px; font-size: 12px; color: #6b7280; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 13px; vertical-align: top; text-align: left; }
            th { background: #f9fafb; text-transform: uppercase; font-size: 11px; letter-spacing: 0.04em; color: #374151; }
            .amount { text-align: right; white-space: nowrap; }
            .muted { color: #6b7280; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
            .badge-paid { background: #dcfce7; color: #166534; }
            .badge-pending { background: #fef3c7; color: #92400e; }
            .badge-other { background: #e5e7eb; color: #374151; }
            .footer { margin-top: 28px; padding-top: 14px; border-top: 1px solid #d1d5db; font-size: 12px; color: #6b7280; }
            @media print {
                body { background: #fff; }
                .toolbar { display: none; }
                .page { max-width: none; margin: 0; padding: 0; }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <button class="print-btn" onclick="window.print()">Print SOA</button>
            <button class="close-btn" onclick="window.close()">Close</button>
        </div>
        <div class="page">
            <div class="header">
                <div class="company">
                    <h1><?php echo esc_html($business['name']); ?></h1>
                    <?php if (!empty($business['address'])): ?><p><?php echo esc_html($business['address']); ?></p><?php endif; ?>
                    <?php if (!empty($business['contact'])): ?><p>Contact: <?php echo esc_html($business['contact']); ?></p><?php endif; ?>
                    <?php if (!empty($business['email'])): ?><p>Email: <?php echo esc_html($business['email']); ?></p><?php endif; ?>
                </div>
                <div class="doc-title meta">
                    <h2>Statement of Account</h2>
                    <p>Generated: <?php echo esc_html(date_i18n('F d, Y h:i A')); ?></p>
                    <p>Raffle ID: <?php echo esc_html($raffle->rand_id); ?></p>
                    <p>Status: <?php echo esc_html(ucfirst($raffle->status)); ?></p>
                </div>
            </div>

            <div class="section-title">Raffle Details</div>
            <div class="detail-grid">
                <div class="card">
                    <div class="label">Raffle Title</div>
                    <div class="value"><?php echo esc_html($raffle->title); ?></div>
                    <?php if (!empty($raffle->description)): ?><div class="sub"><?php echo nl2br(esc_html($raffle->description)); ?></div><?php endif; ?>
                </div>
                <div class="card">
                    <div class="label">Schedule and Winner</div>
                    <div class="sub">Draw Date: <?php echo $raffle->draw_date ? esc_html(date_i18n('F d, Y h:i A', strtotime($raffle->draw_date))) : 'Not set'; ?></div>
                    <div class="sub">Winner: <?php echo !empty($raffle->winner_name) ? esc_html($raffle->winner_name) . ' (' . esc_html($raffle->winner_ticket_number) . ')' : 'Not drawn yet'; ?></div>
                    <div class="sub">Ticket Price: PHP <?php echo number_format((float) $raffle->ticket_price, 2); ?></div>
                </div>
            </div>

            <div class="section-title">Account Summary</div>
            <div class="summary-grid">
                <div class="card">
                    <div class="label">Potential Revenue</div>
                    <div class="value">PHP <?php echo number_format($potential_revenue, 2); ?></div>
                    <div class="sub"><?php echo number_format($total_capacity); ?> total tickets</div>
                </div>
                <div class="card">
                    <div class="label">Collected</div>
                    <div class="value">PHP <?php echo number_format($paid_amount, 2); ?></div>
                    <div class="sub"><?php echo number_format($paid_tickets); ?> paid tickets</div>
                </div>
                <div class="card">
                    <div class="label">Unsold Value</div>
                    <div class="value">PHP <?php echo number_format($unsold_value, 2); ?></div>
                    <div class="sub"><?php echo number_format($unsold_tickets); ?> unsold tickets</div>
                </div>
                <div class="card">
                    <div class="label">Available Inventory</div>
                    <div class="value"><?php echo number_format($available_tickets); ?></div>
                    <div class="sub"><?php echo number_format($paid_tickets); ?> paid tickets</div>
                </div>
                <div class="card">
                    <div class="label">Paid Purchases</div>
                    <div class="value"><?php echo number_format(count($purchases)); ?></div>
                    <div class="sub">Included in this SOA</div>
                </div>
            </div>

            <div class="section-title">Paid Sales Breakdown</div>
            <div class="detail-grid">
                <div class="card">
                    <div class="label">Paid</div>
                    <div class="value"><?php echo number_format($paid_tickets); ?></div>
                    <div class="sub">PHP <?php echo number_format($paid_amount, 2); ?></div>
                </div>
                <div class="card">
                    <div class="label">Average Ticket Price</div>
                    <div class="value">PHP <?php echo number_format((float) $raffle->ticket_price, 2); ?></div>
                    <div class="sub">Configured raffle ticket rate</div>
                </div>
                <div class="card">
                    <div class="label">Paid Purchases</div>
                    <div class="value"><?php echo number_format(count($purchases)); ?></div>
                    <div class="sub">Grouped by purchase reference</div>
                </div>
                <div class="card">
                    <div class="label">Paid Sales Total</div>
                    <div class="value">PHP <?php echo number_format($paid_amount, 2); ?></div>
                    <div class="sub">Paid transactions only</div>
                </div>
            </div>

            <div class="section-title">Paid Purchase Ledger</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Customer</th>
                        <th>Tickets</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr>
                            <td colspan="7" class="muted">No purchases found for this raffle.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $purchase): ?>
                            <?php
                            $status = strtolower((string) $purchase->payment_status);
                            $badge_class = $status === 'paid' ? 'badge-paid' : ($status === 'pending' ? 'badge-pending' : 'badge-other');
                            $payment_label = !empty($purchase->payment_gateway) ? $purchase->payment_gateway : $purchase->payment_method;
                            ?>
                            <tr>
                                <td><?php echo esc_html(date_i18n('M d, Y h:i A', strtotime($purchase->purchased_at))); ?></td>
                                <td><strong><?php echo esc_html($purchase->reference_code); ?></strong></td>
                                <td>
                                    <strong><?php echo esc_html($purchase->customer_name); ?></strong><br>
                                    <?php if (!empty($purchase->customer_email)): ?><span class="muted"><?php echo esc_html($purchase->customer_email); ?></span><br><?php endif; ?>
                                    <?php if (!empty($purchase->customer_phone)): ?><span class="muted"><?php echo esc_html($purchase->customer_phone); ?></span><?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format((int) $purchase->ticket_count); ?></strong><br>
                                    <span class="muted"><?php echo esc_html($purchase->ticket_numbers); ?></span>
                                </td>
                                <td><span class="badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html(ucfirst($status ?: 'unknown')); ?></span></td>
                                <td><?php echo esc_html($payment_label ?: 'Manual'); ?></td>
                                <td class="amount">PHP <?php echo number_format((float) $purchase->total_amount, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="footer">
                <div><?php echo esc_html($business['footer']); ?></div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Helper: Handle Base64 Image Upload, Compress to <500KB, Save to wp-content/headers
 */
function bntm_process_raffle_image($data_string) {
    // 1. If it's already a URL (existing image), return it cleaned
    if (filter_var($data_string, FILTER_VALIDATE_URL) || strpos($data_string, 'http') === 0) {
        return sanitize_text_field($data_string);
    }

    // 2. Check if it's Base64 data
    if (preg_match('/^data:image\/(\w+);base64,/', $data_string, $type)) {
        $data = substr($data_string, strpos($data_string, ',') + 1);
        $decoded_data = base64_decode($data);

        if ($decoded_data === false) return null;

        // Create Image Resource
        $image = imagecreatefromstring($decoded_data);
        if (!$image) return null;

        // Setup Directory: wp-content/headers
        $upload_dir = WP_CONTENT_DIR . '/headers';
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        // Generate Filename
        $filename = 'raffle_' . time() . '_' . uniqid() . '.jpg';
        $file_path = $upload_dir . '/' . $filename;
        $file_url = content_url('headers/' . $filename);

        // Compression Loop: Reduce quality until < 500KB
        $quality = 90;
        $max_size = 500 * 1024; // 500KB in bytes
        
        do {
            ob_start();
            imagejpeg($image, null, $quality); // Convert to JPG for compression
            $content = ob_get_clean();
            $size = strlen($content);
            $quality -= 5; // Reduce quality by 5% each step
        } while ($size > $max_size && $quality > 10);

        // Save file
        file_put_contents($file_path, $content);
        imagedestroy($image);

        return $file_url; // Return the short URL to store in DB
    }

    return null;
}
/**
 * Generate unique ticket number
 */
function raffle_generate_ticket_number($raffle_id) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    do {
        $ticket_number = 'T' . str_pad($raffle_id, 4, '0', STR_PAD_LEFT) . '-' .
                        strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tickets_table} WHERE ticket_number = %s",
            $ticket_number
        ));
    } while ($exists);
    
    return $ticket_number;
}

/**
 * Update tickets sold count
 */
function raffle_update_tickets_sold($raffle_id) {
    global $wpdb;
    $raffles_table = $wpdb->prefix . 'raffle_raffles';
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    $sold_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$tickets_table}
        WHERE raffle_id = %d AND payment_status = 'paid'
    ", $raffle_id));

    $wpdb->update(
        $raffles_table,
        ['tickets_sold' => $sold_count],
        ['id' => $raffle_id],
        ['%d'],
        ['%d']
    );
}

/**
 * Process OP payment
 */
function raffle_process_op_payment($purchase_reference, $amount, $op_method_id, $customer_data) {
    global $wpdb;
    $methods_table = $wpdb->prefix . 'op_payment_methods';
    
    $payment_method = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$methods_table} WHERE id = %d AND is_active = 1",
        $op_method_id
    ));

    if (!$payment_method) {
        return [
            'success' => false,
            'message' => 'Invalid payment method'
        ];
    }
    
    $config = json_decode($payment_method->config, true);

    switch ($payment_method->gateway) {
        case 'paypal':
            return raffle_process_paypal($purchase_reference, $amount, $payment_method, $config, $customer_data);
        case 'paymaya':
            return raffle_process_paymaya($purchase_reference, $amount, $payment_method, $config, $customer_data);
        default:
            return [
                'success' => false,
                'message' => 'Unsupported payment gateway'
            ];
    }
}

/**
 * Process PayPal payment
 */
function raffle_process_paypal($reference, $amount, $payment_method, $config, $customer_data) {
    $mode = $payment_method->mode;
    $base_url = $mode === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        : 'https://api-m.paypal.com/v2/checkout/orders';
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
        return ['success' => false, 'message' => 'PayPal authentication failed'];
    }
    
    $auth_data = json_decode(wp_remote_retrieve_body($auth_response), true);
    $access_token = $auth_data['access_token'] ?? '';

    if (empty($access_token)) {
        return ['success' => false, 'message' => 'Failed to get PayPal access token'];
    }
    
    $return_url = get_permalink(get_page_by_path('buy-raffle-ticket')) .
        '?raffle_payment_success=1&ref=' . $reference . '&gateway=paypal';
    $cancel_url = get_permalink(get_page_by_path('buy-raffle-ticket'));
    
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'amount' => [
                    'currency_code' => 'PHP',
                    'value' => number_format($amount, 2, '.', '')
                ],
                'reference_id' => $reference,
                'description' => 'Raffle: ' . $customer_data['raffle_title']
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

/**
 * Process PayMaya payment
 */
function raffle_process_paymaya($reference, $amount, $payment_method, $config, $customer_data) {
    $mode = $payment_method->mode;
    $base_url = $mode === 'sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';
    
    $success_url = get_permalink(get_page_by_path('buy-raffle-ticket')) .
        '?raffle_payment_success=1&ref=' . $reference . '&gateway=paymaya';
    $failure_url = get_permalink(get_page_by_path('buy-raffle-ticket'));
    $cancel_url = get_permalink(get_page_by_path('buy-raffle-ticket'));
    
    $success_url = get_permalink(get_page_by_path('payment-result')) .
        '?raffle_payment_success=1&ref=' . $reference . '&gateway=paymaya';
    $failure_url = get_permalink(get_page_by_path('payment-result')) .
        '?raffle_payment_success=0&ref=' . $reference . '&gateway=paymaya';
    $cancel_url = get_permalink(get_page_by_path('payment-result')) .
        '?raffle_payment_success=0&ref=' . $reference . '&gateway=paymaya';
    $checkout_data = [
        'totalAmount' => [
            'value' => floatval($amount),
            'currency' => 'PHP'
        ],
        'buyer' => [
            'firstName' => $customer_data['customer_name'],
            'lastName' => '',
            'contact' => [
                'email' => $customer_data['customer_email']
            ]
        ],
        'items' => [
            [
                'name' => 'Raffle Tickets - ' . $customer_data['raffle_title'],
                'quantity' => count($customer_data['ticket_numbers']),
                'amount' => ['value' => floatval($amount) / count($customer_data['ticket_numbers'])],
                'totalAmount' => ['value' => floatval($amount)]
            ]
        ],
        'redirectUrl' => [
            'success' => $success_url,
            'failure' => $failure_url,
            'cancel' => $cancel_url
        ],
        'requestReferenceNumber' => $reference,
        'metadata' => [
            'reference' => $reference,
            'module' => 'raffle'
        ]
    ];

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
        return [
            'success' => false,
            'message' => 'Failed to create PayMaya checkout: ' . $response->get_error_message()
        ];
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $response_data = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code !== 200 && $status_code !== 201) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error';
        return [
            'success' => false,
            'message' => 'PayMaya checkout creation failed: ' . $error_message
        ];
    }

    if (!isset($response_data['checkoutId'])) {
        return [
            'success' => false,
            'message' => 'PayMaya checkout creation failed - no checkout ID'
        ];
    }
    
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

/**
 * Complete payment
 */
function raffle_complete_payment($purchase_reference, $gateway) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'raffle_tickets';
    
    // Get tickets
    $tickets = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$tickets_table} WHERE purchase_reference = %s
    ", $purchase_reference));

    if (empty($tickets)) {
        error_log("[Raffle] Tickets not found: " . $purchase_reference);
        return false;
    }
    
    // Check if already paid
    if ($tickets[0]->payment_status === 'paid') {
        return true;
    }
    
    // Update tickets status
    $wpdb->query($wpdb->prepare("
        UPDATE {$tickets_table}
        SET payment_status = 'paid'
        WHERE purchase_reference = %s
    ", $purchase_reference));

    // Update raffle tickets_sold count
    raffle_update_tickets_sold($tickets[0]->raffle_id);
    
    error_log("[Raffle] Payment completed: Reference {$purchase_reference}, Gateway: $gateway");
    return true;
}
