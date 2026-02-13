<?php
/**
 * Module Name: CRM
 * Module Slug: crm
 * Description: Customer Relationship Management with sales pipeline, quotations, and automated billing
 * Version: 1.0.0
 * Author: Your Name
 * Icon: 👥
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_CRM_PATH', dirname(__FILE__) . '/');
define('BNTM_CRM_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 */
function bntm_crm_get_pages() {
    return [
        'CRM Dashboard' => '[crm_dashboard]',
        'Quotation' => '[crm_quotation_view]'
    ];
}

/**
 * Get module database tables
 */
function bntm_crm_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'crm_customers' => "CREATE TABLE {$prefix}crm_customers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type ENUM('customer', 'lead') DEFAULT 'lead',
            name VARCHAR(255) NOT NULL,
            contact_number VARCHAR(50),
            email VARCHAR(255),
            company VARCHAR(255),
            address TEXT,
            birthday DATE,
            anniversary DATE,
            source VARCHAR(100),
            tags TEXT,
            status VARCHAR(50) DEFAULT 'active',
            notes TEXT,
            assigned_to BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (type),
            INDEX idx_status (status),
            INDEX idx_assigned (assigned_to),
            INDEX idx_email (email),
            INDEX idx_contact (contact_number)
        ) {$charset};",
        
        'crm_deals' => "CREATE TABLE {$prefix}crm_deals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            deal_name VARCHAR(255) NOT NULL,
            deal_value DECIMAL(12,2) DEFAULT 0,
            stage VARCHAR(50) DEFAULT 'lead',
            probability INT DEFAULT 0,
            expected_close_date DATE,
            actual_close_date DATE,
            products TEXT,
            notes TEXT,
            assigned_to BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_stage (stage),
            INDEX idx_assigned (assigned_to),
            INDEX idx_close_date (expected_close_date),
            FOREIGN KEY (customer_id) REFERENCES {$prefix}crm_customers(id) ON DELETE CASCADE
        ) {$charset};",
        
        'crm_pipeline_stages' => "CREATE TABLE {$prefix}crm_pipeline_stages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            stage_name VARCHAR(100) NOT NULL,
            stage_order INT DEFAULT 0,
            color VARCHAR(7) DEFAULT '#3b82f6',
            is_active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_order (stage_order)
        ) {$charset};",
        
        'crm_quotations' => "CREATE TABLE {$prefix}crm_quotations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            deal_id BIGINT UNSIGNED,
            quotation_number VARCHAR(50) UNIQUE,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            amount DECIMAL(12,2) NOT NULL,
            tax DECIMAL(12,2) DEFAULT 0,
            discount DECIMAL(12,2) DEFAULT 0,
            total DECIMAL(12,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            valid_until DATE,
            status VARCHAR(50) DEFAULT 'draft',
            pdf_url VARCHAR(500),
            pdf_generated_at DATETIME,
            notes TEXT,
            terms_conditions TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_deal (deal_id),
            INDEX idx_status (status),
            FOREIGN KEY (customer_id) REFERENCES {$prefix}crm_customers(id) ON DELETE CASCADE
        ) {$charset};",
        
        'crm_quotation_items' => "CREATE TABLE {$prefix}crm_quotation_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            quotation_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED,
            item_name VARCHAR(255) NOT NULL,
            description TEXT,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(12,2) NOT NULL,
            total DECIMAL(12,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_quotation (quotation_id),
            INDEX idx_product (product_id),
            FOREIGN KEY (quotation_id) REFERENCES {$prefix}crm_quotations(id) ON DELETE CASCADE
        ) {$charset};",
        
        'crm_billing_schedules' => "CREATE TABLE {$prefix}crm_billing_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            quotation_id BIGINT UNSIGNED,
            schedule_type VARCHAR(50) NOT NULL,
            interval_value INT,
            interval_unit VARCHAR(20),
            specific_dates TEXT,
            amount_per_invoice DECIMAL(12,2) NOT NULL,
            total_invoices INT,
            invoices_generated INT DEFAULT 0,
            start_date DATE NOT NULL,
            end_date DATE,
            next_invoice_date DATE,
            status VARCHAR(50) DEFAULT 'active',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_status (status),
            INDEX idx_next_date (next_invoice_date),
            FOREIGN KEY (customer_id) REFERENCES {$prefix}crm_customers(id) ON DELETE CASCADE
        ) {$charset};",
        
        'crm_activities' => "CREATE TABLE {$prefix}crm_activities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            deal_id BIGINT UNSIGNED,
            activity_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            due_date DATETIME,
            completed BOOLEAN DEFAULT 0,
            completed_at DATETIME,
            assigned_to BIGINT UNSIGNED,
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_deal (deal_id),
            INDEX idx_due (due_date),
            INDEX idx_assigned (assigned_to),
            FOREIGN KEY (customer_id) REFERENCES {$prefix}crm_customers(id) ON DELETE CASCADE
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 */
function bntm_crm_get_shortcodes() {
    return [
        'crm_dashboard' => 'bntm_shortcode_crm_dashboard',
        'crm_quotation_view' => 'bntm_shortcode_crm_quotation_view'
    ];
}

/**
 * Create module tables
 */
function bntm_crm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_crm_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Insert default pipeline stages
    crm_insert_default_pipeline_stages();
    
    return count($tables);
}

/**
 * Insert default pipeline stages
 */
function crm_insert_default_pipeline_stages() {
    global $wpdb;
    $table = $wpdb->prefix . 'crm_pipeline_stages';
    $business_id = get_current_user_id();
    
    // Check if stages already exist
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE business_id = $business_id");
    
    if ($exists > 0) return;
    
    $default_stages = [
        ['stage_name' => 'Lead', 'stage_order' => 1, 'color' => '#6b7280'],
        ['stage_name' => 'Contacted', 'stage_order' => 2, 'color' => '#3b82f6'],
        ['stage_name' => 'Qualified', 'stage_order' => 3, 'color' => '#8b5cf6'],
        ['stage_name' => 'Quotation Sent', 'stage_order' => 4, 'color' => '#f59e0b'],
        ['stage_name' => 'Negotiation', 'stage_order' => 5, 'color' => '#ef4444'],
        ['stage_name' => 'Won', 'stage_order' => 6, 'color' => '#10b981'],
        ['stage_name' => 'Lost', 'stage_order' => 7, 'color' => '#dc2626']
    ];
    
    foreach ($default_stages as $stage) {
        $wpdb->insert($table, array_merge($stage, ['business_id' => $business_id]));
    }
}

// AJAX handlers
add_action('wp_ajax_crm_create_customer', 'bntm_ajax_crm_create_customer');
add_action('wp_ajax_crm_update_customer', 'bntm_ajax_crm_update_customer');
add_action('wp_ajax_crm_delete_customer', 'bntm_ajax_crm_delete_customer');
add_action('wp_ajax_crm_convert_to_customer', 'bntm_ajax_crm_convert_to_customer');
add_action('wp_ajax_crm_create_deal', 'bntm_ajax_crm_create_deal');
add_action('wp_ajax_crm_update_deal_stage', 'bntm_ajax_crm_update_deal_stage');
add_action('wp_ajax_crm_create_quotation', 'bntm_ajax_crm_create_quotation');
add_action('wp_ajax_crm_update_quotation', 'bntm_ajax_crm_update_quotation');
add_action('wp_ajax_crm_delete_quotation', 'bntm_ajax_crm_delete_quotation');
add_action('wp_ajax_crm_create_billing_schedule', 'bntm_ajax_crm_create_billing_schedule');
add_action('wp_ajax_crm_generate_scheduled_invoice', 'bntm_ajax_crm_generate_scheduled_invoice');
add_action('wp_ajax_crm_save_pipeline_stages', 'bntm_ajax_crm_save_pipeline_stages');
add_action('wp_ajax_crm_get_customer', 'bntm_ajax_crm_get_customer');
add_action('wp_ajax_crm_get_quotation', 'bntm_ajax_crm_get_quotation');

// Cron job for auto-generating invoices
add_action('init', 'crm_schedule_invoice_generation');
add_action('crm_generate_scheduled_invoices_hook', 'crm_process_scheduled_invoices');

function crm_schedule_invoice_generation() {
    if (!wp_next_scheduled('crm_generate_scheduled_invoices_hook')) {
        wp_schedule_event(time(), 'daily', 'crm_generate_scheduled_invoices_hook');
    }
}

/* ---------- DASHBOARD ---------- */
function bntm_shortcode_crm_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the CRM dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pipeline';
    
    ob_start();
    ?>
    
    <style>
    .crm-pipeline {
        display: flex;
        gap: 20px;
        overflow-x: auto;
        padding: 20px 0;
    }
    .pipeline-column {
        min-width: 280px;
        background: #f9fafb;
        border-radius: 8px;
        padding: 15px;
    }
    .pipeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
    }
    .pipeline-title {
        font-weight: 600;
        font-size: 14px;
        text-transform: uppercase;
    }
    .pipeline-count {
        background: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .deal-card {
        background: white;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 10px;
        border-left: 4px solid #3b82f6;
        cursor: pointer;
        transition: all 0.2s;
    }
    .deal-card:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .deal-card-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #1f2937;
    }
    .deal-card-customer {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 8px;
    }
    .deal-card-value {
        font-size: 16px;
        font-weight: 700;
        color: #059669;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-lead { background: #e5e7eb; color: #6b7280; }
    .status-customer { background: #d1fae5; color: #065f46; }
    .status-draft { background: #e5e7eb; color: #6b7280; }
    .status-sent { background: #bfdbfe; color: #1e40af; }
    .status-accepted { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-active { background: #d1fae5; color: #065f46; }
    .status-paused { background: #fef3c7; color: #92400e; }
    .status-completed { background: #e5e7eb; color: #6b7280; }
    
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
        max-width: 700px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        max-height: 90vh;
        overflow-y: auto;
    }
    .bntm-modal-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        background: white;
        z-index: 10;
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
    </style>
    
    <div class="bntm-ecommerce-container">
        <div class="bntm-tabs">
            <a href="?tab=pipeline" class="bntm-tab <?php echo $active_tab === 'pipeline' ? 'active' : ''; ?>">Sales Pipeline</a>
            <a href="?tab=customers" class="bntm-tab <?php echo $active_tab === 'customers' ? 'active' : ''; ?>">Customers</a>
            <a href="?tab=quotations" class="bntm-tab <?php echo $active_tab === 'quotations' ? 'active' : ''; ?>">Quotations</a>
            <a href="?tab=billing" class="bntm-tab <?php echo $active_tab === 'billing' ? 'active' : ''; ?>">Billing Schedules</a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'pipeline'): ?>
                <?php echo crm_pipeline_tab($business_id); ?>
            <?php elseif ($active_tab === 'customers'): ?>
                <?php echo crm_customers_tab($business_id); ?>
            <?php elseif ($active_tab === 'quotations'): ?>
                <?php echo crm_quotations_tab($business_id); ?>
            <?php elseif ($active_tab === 'billing'): ?>
                <?php echo crm_billing_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo crm_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('CRM', $content);
}

function crm_pipeline_tab($business_id) {
    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $stages_table = $wpdb->prefix . 'crm_pipeline_stages';
    
    // Get pipeline stages
    $stages = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $stages_table WHERE business_id = %d AND is_active = 1 ORDER BY stage_order ASC",
        $business_id
    ));
    
    // Get deals with customer info
    $deals = $wpdb->get_results($wpdb->prepare(
        "SELECT d.*, c.name as customer_name, c.email as customer_email 
         FROM $deals_table d
         LEFT JOIN $customers_table c ON d.customer_id = c.id
         WHERE d.business_id = %d
         ORDER BY d.created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var crmNonce = '<?php echo $nonce; ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Sales Pipeline</h3>
            <button class="bntm-btn-primary" id="create-deal-btn">+ Create Deal</button>
        </div>
        
        <div class="crm-pipeline">
            <?php foreach ($stages as $stage): 
                $stage_deals = array_filter($deals, function($deal) use ($stage) {
                    return $deal->stage === $stage->stage_name;
                });
                $deal_count = count($stage_deals);
                $total_value = array_sum(array_column($stage_deals, 'deal_value'));
            ?>
            <div class="pipeline-column" data-stage="<?php echo esc_attr($stage->stage_name); ?>">
                <div class="pipeline-header">
                    <div>
                        <div class="pipeline-title" style="color: <?php echo esc_attr($stage->color); ?>">
                            <?php echo esc_html($stage->stage_name); ?>
                        </div>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                            <?php echo crm_format_price($total_value); ?>
                        </div>
                    </div>
                    <span class="pipeline-count"><?php echo $deal_count; ?></span>
                </div>
                
                <div class="pipeline-deals">
                    <?php if (empty($stage_deals)): ?>
                        <p style="text-align: center; color: #9ca3af; font-size: 13px; padding: 20px;">No deals</p>
                    <?php else: ?>
                        <?php foreach ($stage_deals as $deal): ?>
                        <div class="deal-card" data-id="<?php echo $deal->id; ?>" style="border-left-color: <?php echo esc_attr($stage->color); ?>">
                            <div class="deal-card-title"><?php echo esc_html($deal->deal_name); ?></div>
                            <div class="deal-card-customer">👤 <?php echo esc_html($deal->customer_name); ?></div>
                            <div class="deal-card-value"><?php echo crm_format_price($deal->deal_value); ?></div>
                            <?php if ($deal->expected_close_date): ?>
                            <div style="font-size: 11px; color: #6b7280; margin-top: 6px;">
                                📅 <?php echo date('M d, Y', strtotime($deal->expected_close_date)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Create/Edit Deal Modal -->
    <div id="deal-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2 id="deal-modal-title">Create Deal</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="deal-form" class="bntm-form">
                <input type="hidden" name="deal_id" id="deal_id">
                
                <div class="bntm-form-group">
                    <label>Customer / Lead *</label>
                    <select name="customer_id" id="deal_customer_id" required>
                        <option value="">Select Customer/Lead</option>
                        <?php
                        $all_customers = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM $customers_table WHERE business_id = %d ORDER BY name ASC",
                            $business_id
                        ));
                        foreach ($all_customers as $customer):
                        ?>
                        <option value="<?php echo $customer->id; ?>">
                            <?php echo esc_html($customer->name); ?> (<?php echo ucfirst($customer->type); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Deal Name *</label>
                    <input type="text" name="deal_name" id="deal_name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Deal Value</label>
                    <input type="number" name="deal_value" id="deal_value" step="0.01" min="0">
                </div>
                
                <div class="bntm-form-group">
                    <label>Pipeline Stage</label>
                    <select name="stage" id="deal_stage">
                        <?php foreach ($stages as $stage): ?>
                        <option value="<?php echo esc_attr($stage->stage_name); ?>">
                            <?php echo esc_html($stage->stage_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Expected Close Date</label>
                    <input type="date" name="expected_close_date" id="expected_close_date">
                </div>
                
                <div class="bntm-form-group">
                    <label>Probability (%)</label>
                    <input type="number" name="probability" id="deal_probability" min="0" max="100" value="50">
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="deal_notes" rows="3"></textarea>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Save Deal</button>
                </div>
                <div id="deal-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <!-- Deal Details Modal -->
    <div id="deal-details-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2>Deal Details</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <div id="deal-details-content" style="padding: 20px;">
                <!-- Will be populated via JavaScript -->
            </div>
        </div>
    </div>

    <script>
    (function() {
        const dealModal = document.getElementById('deal-modal');
        const detailsModal = document.getElementById('deal-details-modal');
        const dealForm = document.getElementById('deal-form');
        
        // Open create deal modal
        document.getElementById('create-deal-btn').addEventListener('click', function() {
            document.getElementById('deal-modal-title').textContent = 'Create Deal';
            dealForm.reset();
            document.getElementById('deal_id').value = '';
            dealModal.style.display = 'block';
        });
        
        // Close modals
        document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
            el.addEventListener('click', function() {
                dealModal.style.display = 'none';
                detailsModal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === dealModal) dealModal.style.display = 'none';
            if (e.target === detailsModal) detailsModal.style.display = 'none';
        });
        
        // Submit deal form
        dealForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = formData.get('deal_id') ? 'crm_update_deal' : 'crm_create_deal';
            formData.append('action', action);
            formData.append('nonce', crmNonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('deal-form-message');
                if (json.success) {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Save Deal';
                }
            });
        });
        
        // Deal card click - show details
        document.querySelectorAll('.deal-card').forEach(card => {
            card.addEventListener('click', function() {
                const dealId = this.dataset.id;
                showDealDetails(dealId);
            });
        });
        
        function showDealDetails(dealId) {
    const formData = new FormData();
    formData.append('action', 'crm_get_deal');
    formData.append('deal_id', dealId);
    formData.append('nonce', crmNonce);
    
    fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const deal = json.data;
                const content = document.getElementById('deal-details-content');
                content.innerHTML = `
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin: 0 0 10px 0;">${deal.deal_name}</h3>
                        <div style="font-size: 24px; font-weight: 700; color: #059669; margin-bottom: 15px;">
                            ${deal.deal_value_formatted}
                        </div>
                        <div><strong>Customer:</strong> ${deal.customer_name}</div>
                        <div><strong>Stage:</strong> <span class="status-badge">${deal.stage}</span></div>
                        <div><strong>Probability:</strong> ${deal.probability}%</div>
                        ${deal.expected_close_date ? '<div><strong>Expected Close:</strong> ' + deal.expected_close_date + '</div>' : ''}
                        ${deal.notes ? '<div style="margin-top: 10px;"><strong>Notes:</strong><br>' + deal.notes + '</div>' : ''}
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button class="bntm-btn-primary" onclick="location.href='?tab=quotations&deal_id=${deal.id}'">
                            Create Quotation
                        </button>
                        <button class="bntm-btn-secondary" onclick="location.href='?tab=customers&customer_id=${deal.customer_id}'">
                            View Customer
                        </button>
                    </div>
                `;
                detailsModal.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error fetching deal details:', error);
        });
}
    // Drag and drop functionality for pipeline
    let draggedDeal = null;
    
    document.querySelectorAll('.deal-card').forEach(card => {
        card.setAttribute('draggable', 'true');
        
        card.addEventListener('dragstart', function(e) {
            draggedDeal = this;
            this.style.opacity = '0.5';
        });
        
        card.addEventListener('dragend', function() {
            this.style.opacity = '1';
        });
    });
    
    document.querySelectorAll('.pipeline-column').forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = '#e5e7eb';
        });
        
        column.addEventListener('dragleave', function() {
            this.style.background = '#f9fafb';
        });
        
        column.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '#f9fafb';
            
            if (draggedDeal) {
                const dealId = draggedDeal.dataset.id;
                const newStage = this.dataset.stage;
                
                updateDealStage(dealId, newStage);
            }
        });
    });
    
    function updateDealStage(dealId, stage) {
        const formData = new FormData();
        formData.append('action', 'crm_update_deal_stage');
        formData.append('deal_id', dealId);
        formData.append('stage', stage);
        formData.append('nonce', crmNonce);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            } else {
                alert('Failed to update deal stage');
            }
        });
    }
})();
</script>
<?php
return ob_get_clean();
}
function crm_customers_tab($business_id) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE business_id = %d ORDER BY created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var crmNonce = '<?php echo $nonce; ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Customers & Leads (<?php echo count($customers); ?>)</h3>
            <button class="bntm-btn-primary" id="create-customer-btn">+ Add Customer/Lead</button>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="margin-right: 15px;">
                <input type="radio" name="customer_filter" value="all" checked> All
            </label>
            <label style="margin-right: 15px;">
                <input type="radio" name="customer_filter" value="lead"> Leads Only
            </label>
            <label>
                <input type="radio" name="customer_filter" value="customer"> Customers Only
            </label>
        </div>
        
        <?php if (empty($customers)): ?>
            <p>No customers or leads yet.</p>
        <?php else: ?>
            <table class="bntm-table" id="customers-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr data-type="<?php echo esc_attr($customer->type); ?>">
                            <td><strong><?php echo esc_html($customer->name); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($customer->type); ?>">
                                    <?php echo ucfirst($customer->type); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($customer->contact_number); ?></td>
                            <td><?php echo esc_html($customer->email); ?></td>
                            <td><?php echo esc_html($customer->company); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($customer->status); ?>">
                                    <?php echo ucfirst($customer->status); ?>
                                </span>
                            </td>
                            <td>
                                <button class="bntm-btn-small edit-customer-btn" data-id="<?php echo $customer->id; ?>">Edit</button>
                                <?php if ($customer->type === 'lead'): ?>
                                <button class="bntm-btn-small convert-customer-btn" data-id="<?php echo $customer->id; ?>" 
                                        style="background: #059669;">Convert</button>
                                <?php endif; ?>
                                <button class="bntm-btn-small delete-customer-btn" data-id="<?php echo $customer->id; ?>" 
                                        style="background: #dc2626;">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Customer Modal -->
    <div id="customer-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2 id="customer-modal-title">Add Customer/Lead</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="customer-form" class="bntm-form">
                <input type="hidden" name="customer_id" id="customer_id">
                
                <div class="bntm-form-group">
                    <label>Type *</label>
                    <select name="type" id="customer_type" required>
                        <option value="lead">Lead</option>
                        <option value="customer">Customer</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" id="customer_name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="customer_email">
                </div>
                
                <div class="bntm-form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" id="customer_contact">
                </div>
                
                <div class="bntm-form-group">
                    <label>Company</label>
                    <input type="text" name="company" id="customer_company">
                </div>
                
                <div class="bntm-form-group">
                    <label>Address</label>
                    <textarea name="address" id="customer_address" rows="2"></textarea>
                </div>
                
                <div class="bntm-form-group">
                    <label>Source</label>
                    <input type="text" name="source" id="customer_source" placeholder="e.g., Website, Referral, Social Media">
                </div>
                
                <div class="bntm-form-group">
                    <label>Tags (comma-separated)</label>
                    <input type="text" name="tags" id="customer_tags" placeholder="e.g., VIP, Enterprise">
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="customer_notes" rows="3"></textarea>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Save</button>
                </div>
                <div id="customer-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        const customerModal = document.getElementById('customer-modal');
        const customerForm = document.getElementById('customer-form');
        
        // Open create customer modal
        document.getElementById('create-customer-btn').addEventListener('click', function() {
            document.getElementById('customer-modal-title').textContent = 'Add Customer/Lead';
            customerForm.reset();
            document.getElementById('customer_id').value = '';
            customerModal.style.display = 'block';
        });
        
        // Close modal
        document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
            el.addEventListener('click', function() {
                customerModal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === customerModal) customerModal.style.display = 'none';
        });
        
        // Filter customers
        document.querySelectorAll('input[name="customer_filter"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const filter = this.value;
                const rows = document.querySelectorAll('#customers-table tbody tr');
                
                rows.forEach(row => {
                    if (filter === 'all') {
                        row.style.display = '';
                    } else {
                        row.style.display = row.dataset.type === filter ? '' : 'none';
                    }
                });
            });
        });
        
        // Submit customer form
        customerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = formData.get('customer_id') ? 'crm_update_customer' : 'crm_create_customer';
            formData.append('action', action);
            formData.append('nonce', crmNonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('customer-form-message');
                if (json.success) {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Save';
                }
            });
        });
        
        // Edit customer
        document.querySelectorAll('.edit-customer-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const customerId = this.dataset.id;
                
                const formData = new FormData();
                formData.append('action', 'crm_get_customer');
                formData.append('customer_id', customerId);
                formData.append('nonce', crmNonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const customer = json.data;
                        document.getElementById('customer-modal-title').textContent = 'Edit Customer/Lead';
                        document.getElementById('customer_id').value = customer.id;
                        document.getElementById('customer_type').value = customer.type;
                        document.getElementById('customer_name').value = customer.name;
                        document.getElementById('customer_email').value = customer.email || '';
                        document.getElementById('customer_contact').value = customer.contact_number || '';
                        document.getElementById('customer_company').value = customer.company || '';
                        document.getElementById('customer_address').value = customer.address || '';
                        document.getElementById('customer_source').value = customer.source || '';
                        document.getElementById('customer_tags').value = customer.tags || '';
                        document.getElementById('customer_notes').value = customer.notes || '';
                        customerModal.style.display = 'block';
                    }
                });
            });
        });
        
        // Convert to customer
        document.querySelectorAll('.convert-customer-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Convert this lead to a customer?')) return;
                
                const customerId = this.dataset.id;
                const formData = new FormData();
                formData.append('action', 'crm_convert_to_customer');
                formData.append('customer_id', customerId);
                formData.append('nonce', crmNonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert(json.data.message);
                        location.reload();
                    } else {
                        alert(json.data.message);
                    }
                });
            });
        });
        
        // Delete customer
        document.querySelectorAll('.delete-customer-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this customer/lead? This will also delete all related deals, quotations, and billing schedules.')) return;
                
                const customerId = this.dataset.id;
                const formData = new FormData();
                formData.append('action', 'crm_delete_customer');
                formData.append('customer_id', customerId);
                formData.append('nonce', crmNonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert(json.data.message);
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

function crm_quotations_tab($business_id) {
    global $wpdb;
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $customers_table = $wpdb->prefix . 'crm_customers';
    
    $quotations = $wpdb->get_results($wpdb->prepare(
        "SELECT q.*, c.name as customer_name 
         FROM $quotations_table q
         LEFT JOIN $customers_table c ON q.customer_id = c.id
         WHERE q.business_id = %d
         ORDER BY q.created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var crmNonce = '<?php echo $nonce; ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Quotations (<?php echo count($quotations); ?>)</h3>
            <button class="bntm-btn-primary" id="create-quotation-btn">+ Create Quotation</button>
        </div>
        
        <?php if (empty($quotations)): ?>
            <p>No quotations yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Quotation #</th>
                        <th>Customer</th>
                        <th>Title</th>
                        <th>Total</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $quotation): ?>
                        <tr>
                            <td><strong><?php echo esc_html($quotation->quotation_number); ?></strong></td>
                            <td><?php echo esc_html($quotation->customer_name); ?></td>
                            <td><?php echo esc_html($quotation->title); ?></td>
                            <td><?php echo crm_format_price($quotation->total); ?></td>
                            <td><?php echo $quotation->valid_until ? date('M d, Y', strtotime($quotation->valid_until)) : 'N/A'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($quotation->status); ?>">
                                    <?php echo ucfirst($quotation->status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo get_page_link(get_page_by_path('quotation')) . '?id=' . esc_attr($quotation->rand_id); ?>" 
                                   class="bntm-btn-small" target="_blank">View</a>
                                <button class="bntm-btn-small edit-quotation-btn" data-id="<?php echo $quotation->id; ?>">Edit</button>
                                <button class="bntm-btn-small delete-quotation-btn" data-id="<?php echo $quotation->id; ?>" 
                                        style="background: #dc2626;">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Create/Edit Quotation Modal -->
    <div id="quotation-modal" class="bntm-modal">
        <div class="bntm-modal-content" style="max-width: 800px;">
            <div class="bntm-modal-header">
                <h2 id="quotation-modal-title">Create Quotation</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="quotation-form" class="bntm-form">
                <input type="hidden" name="quotation_id" id="quotation_id">
                
                <div class="bntm-form-group">
                    <label>Customer *</label>
                    <select name="customer_id" id="quotation_customer_id" required>
                        <option value="">Select Customer</option>
                        <?php
                        $all_customers = $wpdb->get_results($wpdb->prepare(
                            "SELECT * FROM $customers_table WHERE business_id = %d ORDER BY name ASC",
                            $business_id
                        ));
                        foreach ($all_customers as $customer):
                        ?>
                        <option value="<?php echo $customer->id; ?>">
                            <?php echo esc_html($customer->name); ?> (<?php echo ucfirst($customer->type); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Quotation Title *</label>
                    <input type="text" name="title" id="quotation_title" required placeholder="e.g., Website Development Quote">
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" id="quotation_description" rows="2" placeholder="Brief description of the quotation"></textarea>
                </div>
                
                <div class="bntm-form-group">
                    <label>Quotation Type</label>
                    <select name="quotation_type" id="quotation_type">
                        <option value="manual">Manual Entry</option>
                        <option value="products">From Products</option>
                    </select>
                </div>
                
                <div id="manual-items" class="bntm-form-group">
                    <label>Items/Services</label>
                    <div id="manual-items-list">
                        <div class="manual-item-row">
                            <input type="text" name="manual_items[0][name]" placeholder="Item name" style="width: 35%;">
                            <textarea name="manual_items[0][description]" placeholder="Description" rows="1" style="width: 30%;"></textarea>
                            <input type="number" name="manual_items[0][quantity]" placeholder="Qty" min="1" value="1" style="width: 10%;">
                            <input type="number" name="manual_items[0][price]" placeholder="Price" step="0.01" min="0" style="width: 15%;">
                            <button type="button" class="bntm-btn-small remove-item-btn" style="display:none;">×</button>
                        </div>
                    </div>
                    <button type="button" id="add-manual-item-btn" class="bntm-btn-secondary" style="margin-top: 10px;">+ Add Item</button>
                </div>
                
                <div id="products-section" style="display: none;">
                    <div class="bntm-form-group">
                        <label>Select Products</label>
                        <button type="button" id="add-product-quotation-btn" class="bntm-btn-secondary">+ Add Product</button>
                        <div id="selected-products-quotation-list" style="margin-top: 10px;"></div>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Discount</label>
                    <input type="number" name="discount" id="quotation_discount" step="0.01" min="0" value="0">
                </div>
                
                <div class="bntm-form-group">
                    <label>Valid Until</label>
                    <input type="date" name="valid_until" id="quotation_valid_until">
                </div>
                
                <div class="bntm-form-group">
                    <label>Terms & Conditions</label>
                    <textarea name="terms_conditions" id="quotation_terms" rows="3" placeholder="Payment terms, delivery conditions, etc."></textarea>
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="quotation_notes" rows="2"></textarea>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Save Quotation</button>
                </div>
                <div id="quotation-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <!-- Product Select Modal for Quotations -->
    <div id="product-select-quotation-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2>Select Product</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <div class="bntm-form" style="padding: 20px;">
                <div id="product-list-quotation-container">
                    <?php
                    if (function_exists('op_get_imported_products')) {
                        $imported_products = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}op_imported_products ORDER BY product_name ASC");
                    } else {
                        $imported_products = [];
                    }
                    
                    if (empty($imported_products)):
                    ?>
                        <p>No products available. Import products from Inventory in the Payments module first.</p>
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
                                        <span class="product-price"><?php echo crm_format_price($product->price); ?></span>
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
    .manual-item-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: flex-start;
    }
    .manual-item-row input,
    .manual-item-row textarea {
        flex-shrink: 0;
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
    </style>

    <script>
    (function() {
        const quotationModal = document.getElementById('quotation-modal');
        const quotationForm = document.getElementById('quotation-form');
        const quotationType = document.getElementById('quotation_type');
        const manualItems = document.getElementById('manual-items');
        const productsSection = document.getElementById('products-section');
        const productSelectModal = document.getElementById('product-select-quotation-modal');
        
        let manualItemCounter = 1;
        let selectedQuotationProducts = [];
        
        // Toggle quotation type
        quotationType.addEventListener('change', function() {
            if (this.value === 'products') {
                manualItems.style.display = 'none';
                productsSection.style.display = 'block';
            } else {
                manualItems.style.display = 'block';
                productsSection.style.display = 'none';
            }
        });
        
        // Add manual item
        document.getElementById('add-manual-item-btn').addEventListener('click', function() {
            const itemsList = document.getElementById('manual-items-list');
            const newRow = document.createElement('div');
            newRow.className = 'manual-item-row';
            newRow.innerHTML = `
                <input type="text" name="manual_items[${manualItemCounter}][name]" placeholder="Item name" style="width: 35%;">
                <textarea name="manual_items[${manualItemCounter}][description]" placeholder="Description" rows="1" style="width: 30%;"></textarea>
                <input type="number" name="manual_items[${manualItemCounter}][quantity]" placeholder="Qty" min="1" value="1" style="width: 10%;">
                <input type="number" name="manual_items[${manualItemCounter}][price]" placeholder="Price" step="0.01" min="0" style="width: 15%;">
                <button type="button" class="bntm-btn-small remove-item-btn">×</button>
            `;
            itemsList.appendChild(newRow);
            manualItemCounter++;
            
            // Add remove handler
            newRow.querySelector('.remove-item-btn').addEventListener('click', function() {
                newRow.remove();
            });
        });
        
        // Handle remove buttons for existing items
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item-btn')) {
                e.target.closest('.manual-item-row').remove();
            }
        });
        
        // Open create quotation modal
        document.getElementById('create-quotation-btn').addEventListener('click', function() {
            document.getElementById('quotation-modal-title').textContent = 'Create Quotation';
            quotationForm.reset();
            document.getElementById('quotation_id').value = '';
            selectedQuotationProducts = [];
            renderSelectedQuotationProducts();
            
            // Reset to manual mode
            quotationType.value = 'manual';
            manualItems.style.display = 'block';
            productsSection.style.display = 'none';
            
            // Reset manual items to one row
            document.getElementById('manual-items-list').innerHTML = `
                <div class="manual-item-row">
                    <input type="text" name="manual_items[0][name]" placeholder="Item name" style="width: 35%;">
                    <textarea name="manual_items[0][description]" placeholder="Description" rows="1" style="width: 30%;"></textarea>
                    <input type="number" name="manual_items[0][quantity]" placeholder="Qty" min="1" value="1" style="width: 10%;">
                    <input type="number" name="manual_items[0][price]" placeholder="Price" step="0.01" min="0" style="width: 15%;">
                    <button type="button" class="bntm-btn-small remove-item-btn" style="display:none;">×</button>
                </div>
            `;
            manualItemCounter = 1;
            
            quotationModal.style.display = 'block';
        });
        
        // Product selection for quotations
        const addProductQuotationBtn = document.getElementById('add-product-quotation-btn');
        if (addProductQuotationBtn) {
            addProductQuotationBtn.addEventListener('click', function() {
                productSelectModal.style.display = 'block';
            });
        }
        
        document.querySelectorAll('#product-list-quotation-container .product-select-item').forEach(item => {
            item.addEventListener('click', function() {
                const productData = {
                    id: this.dataset.id,
                    name: this.dataset.name,
                    price: parseFloat(this.dataset.price),
                    quantity: 1
                };
                
                addSelectedQuotationProduct(productData);
                productSelectModal.style.display = 'none';
            });
        });
        
        function addSelectedQuotationProduct(product) {
            const existingIndex = selectedQuotationProducts.findIndex(p => p.id === product.id);
            if (existingIndex !== -1) {
                selectedQuotationProducts[existingIndex].quantity++;
            } else {
                selectedQuotationProducts.push(product);
            }
            renderSelectedQuotationProducts();
        }
        
        function renderSelectedQuotationProducts() {
            const container = document.getElementById('selected-products-quotation-list');
            if (!container) return;
            
            if (selectedQuotationProducts.length === 0) {
                container.innerHTML = '<p style="color: #6b7280; font-size: 14px;">No products added yet.</p>';
                return;
        }
        
        let html = '';
        selectedQuotationProducts.forEach((product, index) => {
            const lineTotal = product.price * product.quantity;
            html += `
                <div class="selected-product-row">
                    <div style="flex: 1;">
                        <strong>${escapeHtml(product.name)}</strong>
                        <div style="font-size: 12px; color: #6b7280;">${crm_format_price(product.price)} each</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="bntm-btn-small" onclick="updateQuotationQuantity(${index}, -1)">-</button>
                        <input type="number" value="${product.quantity}" min="1" 
                               onchange="updateQuotationQuantityInput(${index}, this.value)" 
                               style="width: 60px; text-align: center;">
                        <button type="button" class="bntm-btn-small" onclick="updateQuotationQuantity(${index}, 1)">+</button>
                    </div>
                    <div style="min-width: 100px; text-align: right; font-weight: 600;">
                        ${crm_format_price(lineTotal)}
                    </div>
                    <button type="button" class="bntm-btn-small" onclick="removeQuotationProduct(${index})" style="background: #dc2626;">×</button>
                </div>
            `;
        });
        container.innerHTML = html;
    }
    
    window.updateQuotationQuantity = function(index, change) {
        if (selectedQuotationProducts[index]) {
            selectedQuotationProducts[index].quantity = Math.max(1, selectedQuotationProducts[index].quantity + change);
            renderSelectedQuotationProducts();
        }
    };
    
    window.updateQuotationQuantityInput = function(index, value) {
        if (selectedQuotationProducts[index]) {
            const newQty = parseInt(value) || 1;
            selectedQuotationProducts[index].quantity = Math.max(1, newQty);
            renderSelectedQuotationProducts();
        }
    };
    
    window.removeQuotationProduct = function(index) {
        if (confirm('Remove this product?')) {
            selectedQuotationProducts.splice(index, 1);
            renderSelectedQuotationProducts();
        }
    };
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function crm_format_price(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    // Close modals
    document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
        el.addEventListener('click', function() {
            quotationModal.style.display = 'none';
            productSelectModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(e) {
        if (e.target === quotationModal) quotationModal.style.display = 'none';
        if (e.target === productSelectModal) productSelectModal.style.display = 'none';
    });
    
    // Submit quotation form
    quotationForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const type = formData.get('quotation_type');
        
        // Add products if product-based quotation
        if (type === 'products') {
            if (selectedQuotationProducts.length === 0) {
                alert('Please add at least one product');
                return;
            }
            formData.append('products', JSON.stringify(selectedQuotationProducts));
        }
        
        const action = formData.get('quotation_id') ? 'crm_update_quotation' : 'crm_create_quotation';
        formData.append('action', action);
        formData.append('nonce', crmNonce);
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('quotation-form-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Quotation';
            }
        });
    });
    
    // Edit quotation
    document.querySelectorAll('.edit-quotation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const quotationId = this.dataset.id;
            
            const formData = new FormData();
            formData.append('action', 'crm_get_quotation');
            formData.append('quotation_id', quotationId);
            formData.append('nonce', crmNonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const quotation = json.data;
                    document.getElementById('quotation-modal-title').textContent = 'Edit Quotation';
                    document.getElementById('quotation_id').value = quotation.id;
                    document.getElementById('quotation_customer_id').value = quotation.customer_id;
                    document.getElementById('quotation_title').value = quotation.title;
                    document.getElementById('quotation_description').value = quotation.description || '';
                    document.getElementById('quotation_discount').value = quotation.discount;
                    document.getElementById('quotation_valid_until').value = quotation.valid_until || '';
                    document.getElementById('quotation_terms').value = quotation.terms_conditions || '';
                    document.getElementById('quotation_notes').value = quotation.notes || '';
                    
                    // Load items if available
                    if (quotation.items && quotation.items.length > 0) {
                        // Populate items based on type
                        // This would require additional logic to determine if its manual or product-based
                    }
                    
                    quotationModal.style.display = 'block';
                }
            });
        });
    });
    
    // Delete quotation
    document.querySelectorAll('.delete-quotation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete this quotation?')) return;
            
            const quotationId = this.dataset.id;
            const formData = new FormData();
            formData.append('action', 'crm_delete_quotation');
            formData.append('quotation_id', quotationId);
            formData.append('nonce', crmNonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
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
function crm_billing_tab($business_id) {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'crm_billing_schedules';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, c.name as customer_name, q.quotation_number, q.title as quotation_title
         FROM $schedules_table s
         LEFT JOIN $customers_table c ON s.customer_id = c.id
         LEFT JOIN $quotations_table q ON s.quotation_id = q.id
         WHERE s.business_id = %d
         ORDER BY s.created_at DESC",
        $business_id
    ));

    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var crmNonce = '<?php echo $nonce; ?>';
    </script>

    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Billing Schedules (<?php echo count($schedules); ?>)</h3>
            <button class="bntm-btn-primary" id="create-schedule-btn">+ Create Schedule</button>
        </div>
        
        <?php if (empty($schedules)): ?>
            <p>No billing schedules yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Quotation</th>
                        <th>Customer</th>
                        <th>Schedule Type</th>
                        <th>Amount</th>
                        <th>Progress</th>
                        <th>Next Invoice</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($schedule->quotation_number); ?></strong>
                                <?php if ($schedule->quotation_title): ?>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo esc_html($schedule->quotation_title); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($schedule->customer_name); ?></strong></td>
                            <td>
                                <?php 
                                if ($schedule->schedule_type === 'interval') {
                                    echo "Every {$schedule->interval_value} " . ucfirst($schedule->interval_unit);
                                } else {
                                    echo "Specific Dates";
                                }
                                ?>
                            </td>
                            <td><?php echo crm_format_price($schedule->amount_per_invoice); ?></td>
                            <td>
                                <?php 
                                $progress = $schedule->total_invoices > 0 
                                    ? round(($schedule->invoices_generated / $schedule->total_invoices) * 100) 
                                    : 0;
                                echo "{$schedule->invoices_generated}";
                                if ($schedule->total_invoices > 0) {
                                    echo " / {$schedule->total_invoices}";
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                echo $schedule->next_invoice_date 
                                    ? date('M d, Y', strtotime($schedule->next_invoice_date)) 
                                    : 'N/A'; 
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($schedule->status); ?>">
                                    <?php echo ucfirst($schedule->status); ?>
                                </span>
                            </td>
                            <td>
                                <button class="bntm-btn-small generate-invoice-btn" data-id="<?php echo $schedule->id; ?>"
                                        <?php echo $schedule->status !== 'active' ? 'disabled' : ''; ?>>
                                    Generate Now
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Create Billing Schedule Modal -->
    <div id="schedule-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2>Create Billing Schedule from Quotation</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <form id="schedule-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Select Quotation *</label>
                    <select name="quotation_id" id="schedule_quotation_id" required>
                        <option value="">Select Quotation</option>
                        <?php
                        $quotations = $wpdb->get_results($wpdb->prepare(
                            "SELECT q.*, c.name as customer_name 
                             FROM $quotations_table q
                             LEFT JOIN $customers_table c ON q.customer_id = c.id
                             ORDER BY q.created_at DESC",
                            $business_id
                        ));
                        foreach ($quotations as $quotation):
                        ?>
                        <option value="<?php echo $quotation->id; ?>" 
                                data-customer-id="<?php echo $quotation->customer_id; ?>"
                                data-customer-name="<?php echo esc_attr($quotation->customer_name); ?>"
                                data-total="<?php echo $quotation->total; ?>"
                                data-description="<?php echo esc_attr($quotation->description); ?>"
                                data-quotation-number="<?php echo esc_attr($quotation->quotation_number); ?>">
                            <?php echo esc_html($quotation->quotation_number); ?> - 
                            <?php echo esc_html($quotation->customer_name); ?> - 
                            <?php echo crm_format_price($quotation->total); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="quotation-preview" style="display: none; background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div class="info-label">Customer</div>
                            <div id="preview-customer" style="font-weight: 600;"></div>
                        </div>
                        <div>
                            <div class="info-label">Total Amount</div>
                            <div id="preview-total" style="font-weight: 600; color: #059669; font-size: 18px;"></div>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <div class="info-label">Description</div>
                        <div id="preview-description" style="font-size: 14px; color: #4b5563;"></div>
                    </div>
                    <div style="margin-top: 10px;">
                        <div class="info-label">Items/Services</div>
                        <div id="preview-items"></div>
                    </div>
                </div>
                
                <input type="hidden" name="customer_id" id="schedule_customer_id">
                
                <div class="bntm-form-group">
                    <label>Schedule Type *</label>
                    <select name="schedule_type" id="schedule_type" required>
                        <option value="interval">Recurring Interval</option>
                        <option value="specific_dates">Specific Dates</option>
                    </select>
                </div>
                
                <div id="interval-fields">
                    <div class="bntm-form-group">
                        <label>Interval Value *</label>
                        <input type="number" name="interval_value" id="interval_value" min="1" placeholder="e.g., 1, 2, 3">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Interval Unit *</label>
                        <select name="interval_unit" id="interval_unit">
                            <option value="days">Days</option>
                            <option value="weeks">Weeks</option>
                            <option value="months">Months</option>
                            <option value="years">Years</option>
                        </select>
                    </div>
                </div>
                
                <div id="specific-dates-fields" style="display: none;">
                    <div class="bntm-form-group">
                        <label>Invoice Dates (comma-separated) *</label>
                        <textarea name="specific_dates" id="specific_dates" rows="3" 
                                  placeholder="Format: YYYY-MM-DD, YYYY-MM-DD, YYYY-MM-DD"></textarea>
                        <small>Example: 2025-01-15, 2025-02-15, 2025-03-15</small>
                    </div>
                </div>
                
                
                <div class="bntm-form-group">
                    <label>Total Number of Invoices *</label>
                    <input type="number" name="total_invoices" id="total_invoices" min="1" required
                        placeholder="e.g., 3, 6, 12">
                    <small>How many invoices should the quotation amount be divided into?</small>
                </div>

                <div class="bntm-form-group">
                    <label>Amount Per Invoice</label>
                    <input type="number" name="amount_per_invoice" id="amount_per_invoice" step="0.01" min="0" required readonly 
                        style="background: #f9fafb; cursor: not-allowed;">
                    <div id="amount-calculation-info" style="margin-top: 5px;"></div>
                    <small>Auto-calculated: Total Quotation Amount ÷ Number of Invoices</small>
                </div>
                
                <div class="bntm-form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" id="start_date" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>End Date (optional)</label>
                    <input type="date" name="end_date" id="end_date">
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="schedule_notes" rows="2"></textarea>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary modal-cancel">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Create Schedule</button>
                </div>
                <div id="schedule-form-message" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>

    <style>
    .info-label {
        font-size: 11px;
        text-transform: uppercase;
        color: #6b7280;
        font-weight: 600;
        margin-bottom: 4px;
    }
    #preview-items {
        background: white;
        padding: 10px;
        border-radius: 4px;
        margin-top: 5px;
        font-size: 13px;
    }
    .preview-item {
        padding: 6px 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .preview-item:last-child {
        border-bottom: none;
    }
    </style>

    <script>
    (function() {
        const scheduleModal = document.getElementById('schedule-modal');
        const scheduleForm = document.getElementById('schedule-form');
        const scheduleType = document.getElementById('schedule_type');
        const quotationSelect = document.getElementById('schedule_quotation_id');
        
        // Toggle schedule type fields
        scheduleType.addEventListener('change', function() {
            const intervalFields = document.getElementById('interval-fields');
            const specificFields = document.getElementById('specific-dates-fields');
            
            if (this.value === 'specific_dates') {
                intervalFields.style.display = 'none';
                specificFields.style.display = 'block';
                document.getElementById('interval_value').removeAttribute('required');
                document.getElementById('specific_dates').setAttribute('required', 'required');
            } else {
                intervalFields.style.display = 'block';
                specificFields.style.display = 'none';
                document.getElementById('interval_value').setAttribute('required', 'required');
                document.getElementById('specific_dates').removeAttribute('required');
            }
        });
        
          const totalInvoicesInput = document.getElementById('total_invoices');
            const amountPerInvoiceInput = document.getElementById('amount_per_invoice');
            let quotationTotalAmount = 0;
            
            // Handle quotation selection
            quotationSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const quotationPreview = document.getElementById('quotation-preview');
                
                if (this.value) {
                    const customerId = selectedOption.dataset.customerId;
                    const customerName = selectedOption.dataset.customerName;
                    const total = selectedOption.dataset.total;
                    const description = selectedOption.dataset.description;
                    const quotationNumber = selectedOption.dataset.quotationNumber;
                    const quotationId = this.value;
                    
                    // Store total amount
                    quotationTotalAmount = parseFloat(total);
                    
                    // Set customer ID
                    document.getElementById('schedule_customer_id').value = customerId;
                    
                    // Calculate amount per invoice if total invoices is set
                    calculateAmountPerInvoice();
                    
                    // Show preview
                    document.getElementById('preview-customer').textContent = customerName;
                    document.getElementById('preview-total').textContent = '₱' + parseFloat(total).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                    document.getElementById('preview-description').textContent = description || 'No description';
                    
                    // Fetch quotation items
                    fetchQuotationItems(quotationId);
                    
                    quotationPreview.style.display = 'block';
                } else {
                    quotationPreview.style.display = 'none';
                    document.getElementById('schedule_customer_id').value = '';
                    document.getElementById('amount_per_invoice').value = '';
                    quotationTotalAmount = 0;
                }
            });
            
            // Calculate amount per invoice when total invoices changes
            totalInvoicesInput.addEventListener('input', function() {
                calculateAmountPerInvoice();
            });
            
            function calculateAmountPerInvoice() {
                const totalInvoices = parseInt(totalInvoicesInput.value) || 0;
                
                if (quotationTotalAmount > 0 && totalInvoices > 0) {
                    const amountPerInvoice = quotationTotalAmount / totalInvoices;
                    amountPerInvoiceInput.value = amountPerInvoice.toFixed(2);
                    
                    // Show calculation info
                    const calcInfo = document.getElementById('amount-calculation-info');
                    if (calcInfo) {
                        calcInfo.innerHTML = `<small style="color: #059669;">₱${quotationTotalAmount.toFixed(2)} ÷ ${totalInvoices} invoices = ₱${amountPerInvoice.toFixed(2)} per invoice</small>`;
                    }
                } else if (quotationTotalAmount > 0 && totalInvoices === 0) {
                    // If no total invoices specified, use full amount
                    amountPerInvoiceInput.value = quotationTotalAmount.toFixed(2);
                    
                    const calcInfo = document.getElementById('amount-calculation-info');
                    if (calcInfo) {
                        calcInfo.innerHTML = `<small style="color: #6b7280;">Full amount per invoice (no limit set)</small>`;
                    }
                } else {
                    amountPerInvoiceInput.value = '';
                }
            }
        
        function fetchQuotationItems(quotationId) {
            const formData = new FormData();
            formData.append('action', 'crm_get_quotation_items');
            formData.append('quotation_id', quotationId);
            formData.append('nonce', crmNonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success && json.data.items) {
                        const itemsContainer = document.getElementById('preview-items');
                        let itemsHtml = '';
                        
                        json.data.items.forEach(item => {
                            itemsHtml += `
                                <div class="preview-item">
                                    <strong>${item.item_name}</strong>
                                    ${item.description ? '<div style="color: #6b7280; font-size: 12px;">' + item.description + '</div>' : ''}
                                    <div style="margin-top: 4px;">
                                        Qty: ${item.quantity} × ₱${parseFloat(item.unit_price).toFixed(2)} = 
                                        <strong>₱${parseFloat(item.total).toFixed(2)}</strong>
                                    </div>
                                </div>
                            `;
                        });
                        
                        itemsContainer.innerHTML = itemsHtml || '<div style="color: #6b7280;">No items found</div>';
                    }
                })
                .catch(error => console.error('Error fetching items:', error));
        }
        
        // Open create schedule modal
        document.getElementById('create-schedule-btn').addEventListener('click', function() {
            scheduleForm.reset();
            document.getElementById('quotation-preview').style.display = 'none';
            scheduleModal.style.display = 'block';
            
            // Reset to interval mode
            scheduleType.value = 'interval';
            scheduleType.dispatchEvent(new Event('change'));
        });
        
        // Close modal
        document.querySelectorAll('.bntm-modal-close, .modal-cancel').forEach(el => {
            el.addEventListener('click', function() {
                scheduleModal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target === scheduleModal) scheduleModal.style.display = 'none';
        });
        
        // Submit schedule form
        scheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_create_billing_schedule');
            formData.append('nonce', crmNonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Creating...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('schedule-form-message');
                if (json.success) {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = 'Create Schedule';
                }
            });
        });
        
        // Generate invoice now
        document.querySelectorAll('.generate-invoice-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Generate invoice now for this schedule?')) return;
                
                const scheduleId = this.dataset.id;
                const formData = new FormData();
                formData.append('action', 'crm_generate_scheduled_invoice');
                formData.append('schedule_id', scheduleId);
                formData.append('nonce', crmNonce);
                
                this.disabled = true;
                this.textContent = 'Generating...';
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert(json.data.message);
                        location.reload();
                    } else {
                        alert(json.data.message);
                        this.disabled = false;
                        this.textContent = 'Generate Now';
                    }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}
function crm_settings_tab($business_id) {
global $wpdb;
$stages_table = $wpdb->prefix . 'crm_pipeline_stages';
$stages = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $stages_table WHERE business_id = %d ORDER BY stage_order ASC",
    $business_id
));

$nonce = wp_create_nonce('crm_nonce');

ob_start();
?>
<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var crmNonce = '<?php echo $nonce; ?>';
</script>

<div class="bntm-form-section">
    <h3>Pipeline Stages</h3>
    <p>Customize your sales pipeline stages</p>
    
    <form id="pipeline-stages-form">
        <div id="stages-list">
            <?php foreach ($stages as $index => $stage): ?>
            <div class="stage-row" data-id="<?php echo $stage->id; ?>">
                <span class="stage-handle" style="cursor: move;">☰</span>
                <input type="text" name="stages[<?php echo $stage->id; ?>][name]" 
                       value="<?php echo esc_attr($stage->stage_name); ?>" 
                       placeholder="Stage name" required>
                <input type="color" name="stages[<?php echo $stage->id; ?>][color]" 
                       value="<?php echo esc_attr($stage->color); ?>">
                <label>
                    <input type="checkbox" name="stages[<?php echo $stage->id; ?>][active]" 
                           value="1" <?php checked($stage->is_active, 1); ?>>
                    Active
                </label>
                <button type="button" class="bntm-btn-small remove-stage-btn">Remove</button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <button type="button" id="add-stage-btn" class="bntm-btn-secondary" style="margin-top: 15px;">
            + Add Stage
        </button>
        
        <div style="margin-top: 20px;">
            <button type="submit" class="bntm-btn-primary">Save Pipeline Stages</button>
        </div>
        <div id="pipeline-message" style="margin-top: 15px;"></div>
    </form>
</div>

<div class="bntm-form-section">
    <h3>General Settings</h3>
    <form id="general-settings-form">
        <div class="bntm-form-group">
            <label>Default Quotation Validity (days)</label>
            <input type="number" name="quotation_validity_days" 
                   value="<?php echo esc_attr(bntm_get_setting('crm_quotation_validity', '30')); ?>" min="1">
        </div>
        
        <div class="bntm-form-group">
            <label>Currency</label>
            <select name="currency">
                <option value="USD" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'USD'); ?>>USD</option>
                <option value="EUR" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'EUR'); ?>>EUR</option>
                <option value="GBP" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'GBP'); ?>>GBP</option>
                <option value="PHP" <?php selected(bntm_get_setting('crm_currency', 'PHP'), 'PHP'); ?>>PHP</option>
            </select>
        </div>
        
        <button type="submit" class="bntm-btn-primary">Save General Settings</button>
        <div id="general-settings-message" style="margin-top: 15px;"></div>
    </form>
</div>

<style>
.stage-row {
    display: flex;
    gap: 10px;
    align-items: center;
    padding: 10px;
    background: #f9fafb;
    border-radius: 4px;
    margin-bottom: 10px;
}
.stage-handle {
    font-size: 20px;
    color: #9ca3af;
}
.stage-row input[type="text"] {
    flex: 1;
}
.stage-row input[type="color"] {
    width: 60px;
}
</style>

<script>
(function() {
    let stageCounter = 1000;
    
    // Add new stage
    document.getElementById('add-stage-btn').addEventListener('click', function() {
        const stagesList = document.getElementById('stages-list');
        const newStage = document.createElement('div');
        newStage.className = 'stage-row';
        newStage.dataset.id = 'new_' + stageCounter;
        newStage.innerHTML = `
            <span class="stage-handle" style="cursor: move;">☰</span>
            <input type="text" name="stages[new_${stageCounter}][name]" placeholder="Stage name" required>
            <input type="color" name="stages[new_${stageCounter}][color]" value="#3b82f6">
            <label>
                <input type="checkbox" name="stages[new_${stageCounter}][active]" value="1" checked>
                Active
            </label>
            <button type="button" class="bntm-btn-small remove-stage-btn">Remove</button>
        `;
        stagesList.appendChild(newStage);
        stageCounter++;
        
        // Add remove handler
        newStage.querySelector('.remove-stage-btn').addEventListener('click', function() {
            if (confirm('Remove this stage?')) {
                newStage.remove();
            }
        });
    });
    
    // Remove stage handlers
    document.querySelectorAll('.remove-stage-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Remove this stage? Existing deals in this stage will need to be moved.')) {
                this.closest('.stage-row').remove();
            }
        });
    });
    
    // Submit pipeline stages
    document.getElementById('pipeline-stages-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'crm_save_pipeline_stages');
        formData.append('nonce', crmNonce);
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('pipeline-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Save Pipeline Stages';
            }
        });
    });
    
    // Submit general settings
    document.getElementById('general-settings-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'crm_save_general_settings');
        formData.append('nonce', crmNonce);
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('general-settings-message');
            msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save General Settings';
        });
    });
})();
</script>
<?php
return ob_get_clean();
}
/* ---------- AJAX HANDLERS ---------- */

function bntm_ajax_crm_create_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    
    $type = sanitize_text_field($_POST['type'] ?? 'lead');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $contact_number = sanitize_text_field($_POST['contact_number'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $address = sanitize_textarea_field($_POST['address'] ?? '');
    $source = sanitize_text_field($_POST['source'] ?? '');
    $tags = sanitize_text_field($_POST['tags'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($name)) {
        wp_send_json_error(['message' => 'Name is required']);
    }

    $result = $wpdb->insert($customers_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'type' => $type,
        'name' => $name,
        'email' => $email,
        'contact_number' => $contact_number,
        'company' => $company,
        'address' => $address,
        'source' => $source,
        'tags' => $tags,
        'status' => 'active',
        'notes' => $notes,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        wp_send_json_success(['message' => ucfirst($type) . ' created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create customer/lead']);
    }
}

function bntm_ajax_crm_update_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $type = sanitize_text_field($_POST['type'] ?? 'lead');
    $name = sanitize_text_field($_POST['name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $contact_number = sanitize_text_field($_POST['contact_number'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $address = sanitize_textarea_field($_POST['address'] ?? '');
    $source = sanitize_text_field($_POST['source'] ?? '');
    $tags = sanitize_text_field($_POST['tags'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($name)) {
        wp_send_json_error(['message' => 'Name is required']);
    }

    $result = $wpdb->update(
        $customers_table,
        [
            'type' => $type,
            'name' => $name,
            'email' => $email,
            'contact_number' => $contact_number,
            'company' => $company,
            'address' => $address,
            'source' => $source,
            'tags' => $tags,
            'notes' => $notes
        ],
        [
            'id' => $customer_id,
            'business_id' => $business_id
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Customer/Lead updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update customer/lead']);
    }
}

function bntm_ajax_crm_delete_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);
    
    $result = $wpdb->delete(
        $customers_table,
        ['id' => $customer_id, 'business_id' => $business_id],
        ['%d', '%d']
    );

    if ($result) {
        wp_send_json_success(['message' => 'Customer/Lead deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete customer/lead']);
    }
}

function bntm_ajax_crm_get_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);
    
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE id = %d AND business_id = %d",
        $customer_id, $business_id
    ));

    if ($customer) {
        wp_send_json_success((array)$customer);
    } else {
        wp_send_json_error(['message' => 'Customer not found']);
    }
}

function bntm_ajax_crm_convert_to_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $customer_id = intval($_POST['customer_id'] ?? 0);
    
    $result = $wpdb->update(
        $customers_table,
        ['type' => 'customer'],
        ['id' => $customer_id, 'business_id' => $business_id],
        ['%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Lead converted to customer successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to convert lead']);
    }
}

function bntm_ajax_crm_create_deal() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();
    
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $deal_name = sanitize_text_field($_POST['deal_name'] ?? '');
    $deal_value = floatval($_POST['deal_value'] ?? 0);
    $stage = sanitize_text_field($_POST['stage'] ?? 'Lead');
    $probability = intval($_POST['probability'] ?? 50);
    $expected_close_date = sanitize_text_field($_POST['expected_close_date'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($deal_name) || $customer_id <= 0) {
        wp_send_json_error(['message' => 'Deal name and customer are required']);
    }

    $result = $wpdb->insert($deals_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => $customer_id,
        'deal_name' => $deal_name,
        'deal_value' => $deal_value,
        'stage' => $stage,
        'probability' => $probability,
        'expected_close_date' => $expected_close_date ?: null,
        'notes' => $notes,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Deal created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create deal']);
    }
}

function bntm_ajax_crm_update_deal_stage() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();
    
    $deal_id = intval($_POST['deal_id'] ?? 0);
    $stage = sanitize_text_field($_POST['stage'] ?? '');
    
    $update_data = ['stage' => $stage];
    
    // If stage is "Won", set actual_close_date
    if ($stage === 'Won') {
        $update_data['actual_close_date'] = current_time('mysql');
    }
    
    $result = $wpdb->update(
        $deals_table,
        $update_data,
        ['id' => $deal_id, 'business_id' => $business_id],
        array_fill(0, count($update_data), '%s'),
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Deal stage updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update deal stage']);
    }
}

function bntm_ajax_crm_get_deal() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
    $deal_id = intval($_POST['deal_id'] ?? 0);
    
    $deal = $wpdb->get_row($wpdb->prepare(
        "SELECT d.*, c.name as customer_name 
         FROM $deals_table d
         LEFT JOIN $customers_table c ON d.customer_id = c.id
         WHERE d.id = %d AND d.business_id = %d",
        $deal_id, $business_id
    ));

    if ($deal) {
        $deal->deal_value_formatted = crm_format_price($deal->deal_value);
        $deal->expected_close_date = $deal->expected_close_date 
            ? date('M d, Y', strtotime($deal->expected_close_date)) 
            : null;
        wp_send_json_success((array)$deal);
    } else {
        wp_send_json_error(['message' => 'Deal not found']);
    }
}

function bntm_ajax_crm_create_quotation() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $items_table = $wpdb->prefix . 'crm_quotation_items';
    $business_id = get_current_user_id();
    
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $title = sanitize_text_field($_POST['title'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0);
    $valid_until = sanitize_text_field($_POST['valid_until'] ?? '');
    $terms_conditions = sanitize_textarea_field($_POST['terms_conditions'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $quotation_type = sanitize_text_field($_POST['quotation_type'] ?? 'manual');
    
    if (empty($title) || $customer_id <= 0) {
        wp_send_json_error(['message' => 'Title and customer are required']);
    }

    // Generate quotation number
    $quotation_number = 'QT-' . date('Ymd') . '-' . strtoupper(substr(bntm_rand_id(), 0, 6));
    
    // Calculate totals
    $amount = 0;
    $items = [];
    
    if ($quotation_type === 'products') {
        $products = json_decode(stripslashes($_POST['products'] ?? '[]'), true);
        
        if (empty($products)) {
            wp_send_json_error(['message' => 'Please add at least one product']);
        }
        
        foreach ($products as $product) {
            $line_total = floatval($product['price']) * intval($product['quantity']);
            $amount += $line_total;
            
            $items[] = [
                'product_id' => $product['id'],
                'item_name' => $product['name'],
                'description' => '',
                'quantity' => $product['quantity'],
                'unit_price' => $product['price'],
                'total' => $line_total
            ];
        }
    } else {
        // Manual items
        $manual_items = $_POST['manual_items'] ?? [];
        
        foreach ($manual_items as $item) {
            if (empty($item['name'])) continue;
            
            $quantity = intval($item['quantity'] ?? 1);
            $unit_price = floatval($item['price'] ?? 0);
            $line_total = $quantity * $unit_price;
            $amount += $line_total;
            
            $items[] = [
                'product_id' => null,
                'item_name' => sanitize_text_field($item['name']),
                'description' => sanitize_textarea_field($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total' => $line_total
            ];
        }
    }
    
    if (empty($items)) {
        wp_send_json_error(['message' => 'Please add at least one item']);
    }
    
    $tax_rate = floatval(bntm_get_setting('crm_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax - $discount;
    
    // Set default valid_until if empty
    if (empty($valid_until)) {
        $validity_days = intval(bntm_get_setting('crm_quotation_validity', '30'));
        $valid_until = date('Y-m-d', strtotime("+$validity_days days"));
    }

    // Insert quotation
    $result = $wpdb->insert($quotations_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => $customer_id,
        'quotation_number' => $quotation_number,
        'title' => $title,
        'description' => $description,
        'amount' => $amount,
        'tax' => $tax,
        'discount' => $discount,
        'total' => $total,
        'currency' => bntm_get_setting('crm_currency', 'PHP'),
        'valid_until' => $valid_until,
        'status' => 'draft',
        'notes' => $notes,
        'terms_conditions' => $terms_conditions,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        $quotation_id = $wpdb->insert_id;
        
        // Insert items
        foreach ($items as $item) {
            $wpdb->insert($items_table, array_merge(['quotation_id' => $quotation_id], $item));
        }
        
        wp_send_json_success(['message' => 'Quotation created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create quotation']);
    }
}

function bntm_ajax_crm_update_quotation() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $items_table = $wpdb->prefix . 'crm_quotation_items';
    $business_id = get_current_user_id();
    
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $title = sanitize_text_field($_POST['title'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0);
    $valid_until = sanitize_text_field($_POST['valid_until'] ?? '');
    $terms_conditions = sanitize_textarea_field($_POST['terms_conditions'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $quotation_type = sanitize_text_field($_POST['quotation_type'] ?? 'manual');
    
    // Calculate totals (same logic as create)
    $amount = 0;
    $items = [];
    
    if ($quotation_type === 'products') {
        $products = json_decode(stripslashes($_POST['products'] ?? '[]'), true);
        
        foreach ($products as $product) {
            $line_total = floatval($product['price']) * intval($product['quantity']);
            $amount += $line_total;
            
            $items[] = [
                'product_id' => $product['id'],
                'item_name' => $product['name'],
                'description' => '',
                'quantity' => $product['quantity'],
                'unit_price' => $product['price'],
                'total' => $line_total
            ];
        }
    } else {
        $manual_items = $_POST['manual_items'] ?? [];
        
        foreach ($manual_items as $item) {
            if (empty($item['name'])) continue;
            
            $quantity = intval($item['quantity'] ?? 1);
            $unit_price = floatval($item['price'] ?? 0);
            $line_total = $quantity * $unit_price;
            $amount += $line_total;
            
            $items[] = [
                'product_id' => null,
                'item_name' => sanitize_text_field($item['name']),
                'description' => sanitize_textarea_field($item['description'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'total' => $line_total
            ];
        }
    }
    
    $tax_rate = floatval(bntm_get_setting('crm_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax - $discount;
    
    // Update quotation
    $result = $wpdb->update(
        $quotations_table,
        [
            'customer_id' => $customer_id,
            'title' => $title,
            'description' => $description,
            'amount' => $amount,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'valid_until' => $valid_until ?: null,
            'notes' => $notes,
            'terms_conditions' => $terms_conditions
        ],
        ['id' => $quotation_id, 'business_id' => $business_id],
        ['%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        // Delete old items and insert new ones
        $wpdb->delete($items_table, ['quotation_id' => $quotation_id], ['%d']);
        
        foreach ($items as $item) {
            $wpdb->insert($items_table, array_merge(['quotation_id' => $quotation_id], $item));
        }
        
        wp_send_json_success(['message' => 'Quotation updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update quotation']);
    }
}

function bntm_ajax_crm_delete_quotation() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $business_id = get_current_user_id();
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    
    $result = $wpdb->delete(
        $quotations_table,
        ['id' => $quotation_id, 'business_id' => $business_id],
        ['%d', '%d']
    );

    if ($result) {
        wp_send_json_success(['message' => 'Quotation deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete quotation']);
    }
}

function bntm_ajax_crm_get_quotation() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $items_table = $wpdb->prefix . 'crm_quotation_items';
    $business_id = get_current_user_id();
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    
    $quotation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $quotations_table WHERE id = %d AND business_id = %d",
        $quotation_id, $business_id
    ));

    if ($quotation) {
        // Get items
        $quotation->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $items_table WHERE quotation_id = %d",
            $quotation_id
        ));
        
        wp_send_json_success((array)$quotation);
    } else {
        wp_send_json_error(['message' => 'Quotation not found']);
    }
}
function bntm_ajax_crm_create_billing_schedule() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $schedules_table = $wpdb->prefix . 'crm_billing_schedules';
    $business_id = get_current_user_id();
    
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $schedule_type = sanitize_text_field($_POST['schedule_type'] ?? 'interval');
    $interval_value = intval($_POST['interval_value'] ?? 0);
    $interval_unit = sanitize_text_field($_POST['interval_unit'] ?? 'months');
    $specific_dates = sanitize_textarea_field($_POST['specific_dates'] ?? '');
    $amount_per_invoice = floatval($_POST['amount_per_invoice'] ?? 0);
    $total_invoices = intval($_POST['total_invoices'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if ($quotation_id <= 0 || $customer_id <= 0 || $amount_per_invoice <= 0 || empty($start_date)) {
        wp_send_json_error(['message' => 'Quotation, customer, amount, and start date are required']);
    }
    
    // Calculate next invoice date
    if ($schedule_type === 'interval') {
        $next_invoice_date = $start_date;
    } else {
        // Get first date from specific_dates
        $dates = array_map('trim', explode(',', $specific_dates));
        $next_invoice_date = !empty($dates) ? $dates[0] : $start_date;
    }

    $result = $wpdb->insert($schedules_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => $customer_id,
        'quotation_id' => $quotation_id,
        'schedule_type' => $schedule_type,
        'interval_value' => $interval_value ?: null,
        'interval_unit' => $interval_unit ?: null,
        'specific_dates' => $specific_dates ?: null,
        'amount_per_invoice' => $amount_per_invoice,
        'total_invoices' => $total_invoices ?: null,
        'invoices_generated' => 0,
        'start_date' => $start_date,
        'end_date' => $end_date ?: null,
        'next_invoice_date' => $next_invoice_date,
        'status' => 'active',
        'notes' => $notes,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Billing schedule created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create billing schedule']);
    }
}

function bntm_ajax_crm_generate_scheduled_invoice() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    
    $result = crm_generate_invoice_from_schedule($schedule_id);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}

function bntm_ajax_crm_save_pipeline_stages() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $stages_table = $wpdb->prefix . 'crm_pipeline_stages';
    $business_id = get_current_user_id();
    
    $stages = $_POST['stages'] ?? [];
    
    if (empty($stages)) {
        wp_send_json_error(['message' => 'No stages provided']);
    }
    
    $order = 1;
    foreach ($stages as $stage_id => $stage_data) {
        $name = sanitize_text_field($stage_data['name'] ?? '');
        $color = sanitize_text_field($stage_data['color'] ?? '#3b82f6');
        $active = isset($stage_data['active']) ? 1 : 0;
        
        if (empty($name)) continue;
        
        if (strpos($stage_id, 'new_') === 0) {
            // Insert new stage
            $wpdb->insert($stages_table, [
                'business_id' => $business_id,
                'stage_name' => $name,
                'stage_order' => $order,
                'color' => $color,
                'is_active' => $active
            ]);
        } else {
            // Update existing stage
            $wpdb->update(
                $stages_table,
                [
                    'stage_name' => $name,
                    'stage_order' => $order,
                    'color' => $color,
                    'is_active' => $active
                ],
                ['id' => intval($stage_id), 'business_id' => $business_id],
                ['%s', '%d', '%s', '%d'],
                ['%d', '%d']
            );
        }
        
        $order++;
    }

    wp_send_json_success(['message' => 'Pipeline stages saved successfully!']);
}

add_action('wp_ajax_crm_save_general_settings', 'bntm_ajax_crm_save_general_settings');
function bntm_ajax_crm_save_general_settings() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('crm_quotation_validity', intval($_POST['quotation_validity_days'] ?? 30));
    bntm_set_setting('crm_currency', sanitize_text_field($_POST['currency'] ?? 'PHP'));

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}

/* ---------- QUOTATION VIEW PAGE ---------- */
function bntm_shortcode_crm_quotation_view() {
    $quotation_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($quotation_id)) {
        return '<div class="bntm-container"><p>Invalid quotation ID.</p></div>';
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $items_table = $wpdb->prefix . 'crm_quotation_items';
    $customers_table = $wpdb->prefix . 'crm_customers';
    
    $quotation = $wpdb->get_row($wpdb->prepare(
        "SELECT q.*, c.name as customer_name, c.email as customer_email, 
               c.contact_number, c.company, c.address
         FROM $quotations_table q
         LEFT JOIN $customers_table c ON q.customer_id = c.id
         WHERE q.rand_id = %s
        ",
        $quotation_id
    ));
    
    if (!$quotation) {
        return '<div class="bntm-container"><p>Quotation not found.</p></div>';
    }
    
    // Get quotation items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $items_table WHERE quotation_id = %d",
        $quotation->id
    ));
    
    // Get business info
    $business_user = get_userdata($quotation->business_id);
    
    ob_start();
    ?>
    <style>
    .quotation-view {
        max-width: 800px;
        margin: 0 auto;
        padding: 40px 20px;
        background: white;
    }
    .quotation-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }
    .quotation-title {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 10px 0;
    }
    .quotation-number {
        font-size: 14px;
        color: #6b7280;
    }
    .quotation-status {
        text-align: right;
    }
    .company-info {
        margin-bottom: 30px;
    }
    .customer-info {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
    }
    .info-label {
        font-weight: 600;
        color: #6b7280;
        font-size: 12px;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    .info-value {
        color: #1f2937;
        margin-bottom: 15px;
    }
    .quotation-items-table {
        width: 100%;
        margin-bottom: 30px;
        border-collapse: collapse;
    }
    .quotation-items-table th {
        background: #f9fafb;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #e5e7eb;
    }
    .quotation-items-table td {
        padding: 12px;
        border-bottom: 1px solid #e5e7eb;
    }
    .quotation-items-table .text-right {
        text-align: right;
    }
    .quotation-totals {
        max-width: 400px;
        margin-left: auto;
    }
    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
    }
    .total-row.grand-total {
        border-top: 2px solid #1f2937;
        font-size: 20px;
        font-weight: 700;
        color: #1f2937;
        margin-top: 10px;
        padding-top: 15px;
    }
    .quotation-terms {
        margin-top: 40px;
        padding-top: 30px;
        border-top: 1px solid #e5e7eb;
    }
    .quotation-terms h4 {
        margin-bottom: 10px;
    }
    .quotation-actions {
        margin-top: 40px;
        text-align: center;
    }
    @media print {
        .quotation-actions {
            display: none;
        }
    }
    </style>
    
    <div class="quotation-view">
        <div class="quotation-header">
            <div>
                <h1 class="quotation-title">Quotation</h1>
                <div class="quotation-number"><?php echo esc_html($quotation->quotation_number); ?></div>
                <div style="margin-top: 10px; color: #6b7280;">
                    Date: <?php echo date('F d, Y', strtotime($quotation->created_at)); ?>
                </div>
                <?php if ($quotation->valid_until): ?>
                <div style="color: #6b7280;">
                    Valid Until: <?php echo date('F d, Y', strtotime($quotation->valid_until)); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="quotation-status">
                <span class="status-badge status-<?php echo esc_attr($quotation->status); ?>" style="font-size: 14px; padding: 6px 16px;">
                    <?php echo ucfirst($quotation->status); ?>
                </span>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 40px;">
            <div class="company-info">
                <div class="info-label">From</div>
                <div class="info-value">
                    <strong><?php echo esc_html($business_user->display_name); ?></strong><br>
                    <?php echo esc_html($business_user->user_email); ?>
                </div>
            </div>
            
            <div class="customer-info">
                <div class="info-label">Bill To</div>
                <div class="info-value">
                    <strong><?php echo esc_html($quotation->customer_name); ?></strong><br>
                    <?php if ($quotation->company): ?>
                        <?php echo esc_html($quotation->company); ?><br>
                    <?php endif; ?>
                    <?php if ($quotation->customer_email): ?>
                        <?php echo esc_html($quotation->customer_email); ?><br>
                    <?php endif; ?>
                    <?php if ($quotation->contact_number): ?>
                        <?php echo esc_html($quotation->contact_number); ?><br>
                    <?php endif; ?>
                    <?php if ($quotation->address): ?>
                        <?php echo nl2br(esc_html($quotation->address)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($quotation->description): ?>
        <div style="margin-bottom: 30px; padding: 15px; background: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
            <strong style="display: block; margin-bottom: 5px;"><?php echo esc_html($quotation->title); ?></strong>
            <div style="color: #1f2937;"><?php echo nl2br(esc_html($quotation->description)); ?></div>
        </div>
        <?php endif; ?>
        
        <table class="quotation-items-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Item</th>
                    <th class="text-right" style="width: 15%;">Quantity</th>
                    <th class="text-right" style="width: 17.5%;">Unit Price</th>
                    <th class="text-right" style="width: 17.5%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->item_name); ?></strong>
                        <?php if ($item->description): ?>
                            <div style="font-size: 13px; color: #6b7280; margin-top: 4px;">
                                <?php echo nl2br(esc_html($item->description)); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?php echo esc_html($item->quantity); ?></td>
                    <td class="text-right"><?php echo crm_format_price($item->unit_price); ?></td>
                    <td class="text-right"><strong><?php echo crm_format_price($item->total); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="quotation-totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span><?php echo crm_format_price($quotation->amount); ?></span>
            </div>
            
            <?php if ($quotation->tax > 0): ?>
            <div class="total-row">
                <span>Tax:</span>
                <span><?php echo crm_format_price($quotation->tax); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($quotation->discount > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-<?php echo crm_format_price($quotation->discount); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row grand-total">
                <span>Total:</span>
                <span><?php echo crm_format_price($quotation->total); ?></span>
            </div>
        </div>
        
        <?php if ($quotation->terms_conditions): ?>
        <div class="quotation-terms">
            <h4>Terms & Conditions</h4>
            <div style="color: #4b5563; font-size: 14px;">
                <?php echo nl2br(esc_html($quotation->terms_conditions)); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($quotation->notes): ?>
        <div class="quotation-terms">
            <h4>Notes</h4>
            <div style="color: #4b5563; font-size: 14px;">
                <?php echo nl2br(esc_html($quotation->notes)); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="quotation-actions">
            <button onclick="window.print()" class="bntm-btn-primary">Print / Download PDF</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_action('wp_ajax_crm_get_quotation_items', 'bntm_ajax_crm_get_quotation_items');

function bntm_ajax_crm_get_quotation_items() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $items_table = $wpdb->prefix . 'crm_quotation_items';
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $items_table WHERE quotation_id = %d",
        $quotation_id
    ));

    wp_send_json_success(['items' => $items]);
}
/* ---------- CRON PROCESSING ---------- */
function crm_process_scheduled_invoices() {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'crm_billing_schedules';
    
    $today = date('Y-m-d');
    
    // Get all active schedules that are due
    $due_schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $schedules_table 
         WHERE status = 'active' 
         AND next_invoice_date <= %s",
        $today
    ));
    
    foreach ($due_schedules as $schedule) {
        crm_generate_invoice_from_schedule($schedule->id);
    }
}
function crm_generate_invoice_from_schedule($schedule_id) {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'crm_billing_schedules';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $quotations_table = $wpdb->prefix . 'crm_quotations';
    $quotation_items_table = $wpdb->prefix . 'crm_quotation_items';
    $invoices_table = $wpdb->prefix . 'op_invoices';
    $invoice_products_table = $wpdb->prefix . 'op_invoice_products';
    
    $schedule = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $schedules_table WHERE id = %d",
        $schedule_id
    ));
    
    if (!$schedule || $schedule->status !== 'active') {
        return ['success' => false, 'message' => 'Schedule not found or inactive'];
    }
    
    // Check if we have reached the total invoices limit
    if ($schedule->total_invoices > 0 && $schedule->invoices_generated >= $schedule->total_invoices) {
        // Mark schedule as completed
        $wpdb->update($schedules_table, ['status' => 'completed'], ['id' => $schedule_id]);
        return ['success' => false, 'message' => 'Schedule completed - all invoices generated'];
    }
    
    // Get customer info
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $customers_table WHERE id = %d",
        $schedule->customer_id
    ));
    
    if (!$customer) {
        return ['success' => false, 'message' => 'Customer not found'];
    }
    
    // Get quotation info
    $quotation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $quotations_table WHERE id = %d",
        $schedule->quotation_id
    ));
    
    // Get quotation items
    $quotation_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $quotation_items_table WHERE quotation_id = %d",
        $schedule->quotation_id
    ));
    
    // Check if Payments module invoice table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$invoices_table'") === $invoices_table;
    
    if (!$table_exists) {
        return ['success' => false, 'message' => 'Payments module not available'];
    }
    
    // Build description from quotation items
    $description_lines = [];
    if (!empty($quotation_items)) {
        $description_lines[] = "Based on Quotation: " . $quotation->quotation_number;
        $description_lines[] = "Title: " . $quotation->title;
        $description_lines[] = "";
        $description_lines[] = "Items/Services:";
        
        foreach ($quotation_items as $item) {
            $line = "• " . $item->item_name;
            if ($item->description) {
                $line .= "\n  " . $item->description;
            }
            $line .= "\n  Qty: " . $item->quantity . " × ₱" . number_format($item->unit_price, 2) . " = ₱" . number_format($item->total, 2);
            $description_lines[] = $line;
        }
        
        $description_lines[] = "";
        $description_lines[] = "Invoice " . ($schedule->invoices_generated + 1);
        if ($schedule->total_invoices > 0) {
            $description_lines[] = "of " . $schedule->total_invoices;
        }
    } else {
        $description_lines[] = "Scheduled Invoice - " . date('F Y');
        if ($quotation && $quotation->description) {
            $description_lines[] = "";
            $description_lines[] = $quotation->description;
        }
    }
    
    if ($schedule->notes) {
        $description_lines[] = "";
        $description_lines[] = "Notes: " . $schedule->notes;
    }
    
    $description = implode("\n", $description_lines);
    
    // Calculate tax
    $amount = $schedule->amount_per_invoice;
    $tax_rate = floatval(bntm_get_setting('op_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax;
    
    // Set due date
    $payment_terms = intval(bntm_get_setting('op_payment_terms', '30'));
    $due_date = date('Y-m-d', strtotime("+$payment_terms days"));
    
    // Create invoice directly in database
    $result = $wpdb->insert($invoices_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $schedule->business_id,
        'reference_type' => 'crm_schedule',
        'reference_id' => $schedule_id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email ?: '',
        'customer_phone' => $customer->contact_number ?: '',
        'customer_address' => $customer->address ?: '',
        'description' => $description,
        'amount' => $amount,
        'tax' => $tax,
        'total' => $total,
        'currency' => bntm_get_setting('op_currency', 'PHP'),
        'status' => 'sent',
        'payment_status' => 'unpaid',
        'due_date' => $due_date,
        'notes' => 'Auto-generated from billing schedule #' . $schedule->rand_id,
        'created_at' => current_time('mysql')
    ], [
        '%s','%d','%s','%d','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%s','%s','%s'
    ]);
    
    if ($result) {
        $invoice_id = $wpdb->insert_id;
        
        // Insert invoice products if quotation items exist
        if (!empty($quotation_items)) {
            foreach ($quotation_items as $item) {
                // Calculate proportional amount for this installment
                $item_amount = $item->total;
                if ($schedule->total_invoices > 0) {
                    $item_amount = $item->total / $schedule->total_invoices;
                }
                
                $wpdb->insert($invoice_products_table, [
                    'invoice_id' => $invoice_id,
                    'product_id' => $item->product_id ?: 0,
                    'product_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item_amount
                ]);
            }
        }
        
        // Update schedule
        $invoices_generated = $schedule->invoices_generated + 1;
        $next_invoice_date = crm_calculate_next_invoice_date($schedule);
        
        // If no more dates, mark as completed
        $new_status = $next_invoice_date ? 'active' : 'completed';
        
        $wpdb->update(
            $schedules_table,
            [
                'invoices_generated' => $invoices_generated,
                'next_invoice_date' => $next_invoice_date,
                'status' => $new_status
            ],
            ['id' => $schedule_id]
        );
        
        return ['success' => true, 'message' => 'Invoice generated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to generate invoice'];
    }
}

function crm_calculate_next_invoice_date($schedule) {
    if ($schedule->schedule_type === 'interval') {
        $current_date = $schedule->next_invoice_date ?: $schedule->start_date;
        
        switch ($schedule->interval_unit) {
            case 'days':
                return date('Y-m-d', strtotime($current_date . ' +' . $schedule->interval_value . ' days'));
            case 'weeks':
                return date('Y-m-d', strtotime($current_date . ' +' . $schedule->interval_value . ' weeks'));
            case 'months':
                return date('Y-m-d', strtotime($current_date . ' +' . $schedule->interval_value . ' months'));
            case 'years':
                return date('Y-m-d', strtotime($current_date . ' +' . $schedule->interval_value . ' years'));
            default:
                return null;
        }
    } else if ($schedule->schedule_type === 'specific_dates') {
        $dates = array_map('trim', explode(',', $schedule->specific_dates));
        $current_index = array_search($schedule->next_invoice_date, $dates);
        
        if ($current_index !== false && isset($dates[$current_index + 1])) {
            return $dates[$current_index + 1];
        } else {
            return null; // No more dates
        }
    }
    
    return null;
}

/* ---------- HELPER FUNCTIONS ---------- */
function crm_format_price($amount) {
    return '₱' . number_format((float)$amount, 2);
}
