<?php
/**
 * Module Name: CRM
 * Module Slug: crm
 * Description: Customer Relationship Management with sales pipeline, quotations, and automated billing
 * Version: 1.0.2
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
        'CRM Dashboard' => '[crm_dashboard]'
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
    
    // Check if stages already exist - REMOVED business_id filter
    $exists = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    
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
add_action('wp_ajax_crm_save_pipeline_stages', 'bntm_ajax_crm_save_pipeline_stages');
add_action('wp_ajax_crm_get_customer', 'bntm_ajax_crm_get_customer');
add_action('wp_ajax_crm_get_deal', 'bntm_ajax_crm_get_deal');

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
       display: grid;
       grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
       gap: 20px;
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
    .pipeline-deals{
         overflow-y: auto;
         max-height:400px;
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
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'pipeline'): ?>
                <?php echo crm_pipeline_tab($business_id); ?>
            <?php elseif ($active_tab === 'customers'): ?>
                <?php echo crm_customers_tab($business_id); ?>
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
    
    // Get pipeline stages - REMOVED business_id filter
    $stages = $wpdb->get_results(
        "SELECT * FROM $stages_table WHERE is_active = 1 ORDER BY stage_order ASC"
    );
    
    // Get deals with customer info - REMOVED business_id filter
    $deals = $wpdb->get_results(
        "SELECT d.*, c.name as customer_name, c.email as customer_email 
         FROM $deals_table d
         LEFT JOIN $customers_table c ON d.customer_id = c.id
         ORDER BY d.created_at DESC"
    );
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
                            "SELECT * FROM $customers_table ORDER BY name ASC",
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
                        <button class="bntm-btn-primary" onclick="editDealFromDetails(${deal.id})">
                            Edit Deal
                        </button>
                        <button class="bntm-btn-secondary" onclick="location.href='quotation?tab=quotations&deal_id=${deal.id}'">
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

// Add function to edit deal from details modal
window.editDealFromDetails = function(dealId) {
    detailsModal.style.display = 'none';
    
    const formData = new FormData();
    formData.append('action', 'crm_get_deal');
    formData.append('deal_id', dealId);
    formData.append('nonce', crmNonce);
    
    fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const deal = json.data;
                document.getElementById('deal-modal-title').textContent = 'Edit Deal';
                document.getElementById('deal_id').value = deal.id;
                document.getElementById('deal_customer_id').value = deal.customer_id;
                document.getElementById('deal_name').value = deal.deal_name;
                document.getElementById('deal_value').value = deal.deal_value;
                document.getElementById('deal_stage').value = deal.stage;
                document.getElementById('deal_probability').value = deal.probability;
                document.getElementById('expected_close_date').value = deal.expected_close_date || '';
                document.getElementById('deal_notes').value = deal.notes || '';
                dealModal.style.display = 'block';
            }
        });
};
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
    $customers = $wpdb->get_results("SELECT * FROM {$customers_table} ORDER BY created_at DESC");
 
    
   // Get current customer count
    $current_customers = $wpdb->get_var("SELECT COUNT(*) FROM $customers_table");
    
    // Get customer limit from custom limits (set by tier + addons)
    $custom_limits = get_option('bntm_custom_limits', []);
    $customer_limit = isset($custom_limits['crm_customers']) ? intval($custom_limits['crm_customers']) : 0;
    
    // Fallback to table limits if custom limits not set
    if ($customer_limit == 0) {
        $table_limits = get_option('bntm_table_limits', []);
        $customer_limit = isset($table_limits[$customers_table]) ? intval($table_limits[$customers_table]) : 0;
    }
    
    $limit_text = $customer_limit > 0 ? " ({$current_customers}/{$customer_limit})" : " ({$current_customers})";
    $limit_reached = $customer_limit > 0 && $current_customers >= $customer_limit;
    
    $nonce = wp_create_nonce('crm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var crmNonce = '<?php echo $nonce; ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Customers & Leads<?php echo $limit_text; ?></h3>
            <button id="create-customer-btn" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled title="Customer limit reached"' : ''; ?>>
                + Add Customer/Lead
            </button>
        </div>
        
        <?php if ($limit_reached): ?>
        <div style="background: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <strong>⚠️ Customer Limit Reached:</strong> Maximum of <?php echo number_format($customer_limit); ?> customers/leads allowed. 
            <a href="<?php echo get_permalink(get_page_by_path('settings')); ?>?tab=billing" style="color: #dc2626; text-decoration: underline; font-weight: 600;">Upgrade your plan</a> to add more customers.
        </div>
        <?php endif; ?>
        
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
        
        <div class="bntm-table-wrapper">
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
         </div>
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

function crm_settings_tab($business_id) {
    global $wpdb;
    $stages_table = $wpdb->prefix . 'crm_pipeline_stages';
    
    // REMOVED business_id filter
    $stages = $wpdb->get_results(
        "SELECT * FROM $stages_table ORDER BY stage_order ASC"
    );

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
            'id' => $customer_id
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
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
        ['id' => $customer_id],
        ['%d']
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
        ['id' => $customer_id],
        ['%s'],
        ['%d']
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

add_action('wp_ajax_crm_update_deal', 'bntm_ajax_crm_update_deal');
function bntm_ajax_crm_update_deal() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();
    
    $deal_id = intval($_POST['deal_id'] ?? 0);
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

    $result = $wpdb->update(
        $deals_table,
        [
            'customer_id' => $customer_id,
            'deal_name' => $deal_name,
            'deal_value' => $deal_value,
            'stage' => $stage,
            'probability' => $probability,
            'expected_close_date' => $expected_close_date ?: null,
            'notes' => $notes
        ],
        [
            'id' => $deal_id,
            'business_id' => $business_id
        ],
        ['%d', '%s', '%f', '%s', '%d', '%s', '%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Deal updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update deal']);
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
         WHERE d.id = %d",
        $deal_id
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
        ['id' => $deal_id],
        array_fill(0, count($update_data), '%s'),
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Deal stage updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update deal stage']);
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
    
    // Get all existing stage IDs
    $existing_stages = $wpdb->get_col("SELECT id FROM $stages_table");
    $submitted_stage_ids = [];
    
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
            $stage_id_int = intval($stage_id);
            $submitted_stage_ids[] = $stage_id_int;
            
            $wpdb->update(
                $stages_table,
                [
                    'stage_name' => $name,
                    'stage_order' => $order,
                    'color' => $color,
                    'is_active' => $active
                ],
                ['id' => $stage_id_int],
                ['%s', '%d', '%s', '%d'],
                ['%d']
            );
        }
        
        $order++;
    }
    
    // Delete stages that were removed (not in submitted list)
    $stages_to_delete = array_diff($existing_stages, $submitted_stage_ids);
    if (!empty($stages_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($stages_to_delete), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $stages_table WHERE id IN ($placeholders)",
                ...$stages_to_delete
            )
        );
    }

    wp_send_json_success(['message' => 'Pipeline stages saved successfully!']);
}

/* STATEMENT FUNCTIONS MOVED TO PAYMENT MODULE */
/*
function crm_get_customer_statement_data($customer_id, $month = '') {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$customers_table} WHERE id = %d", $customer_id));

    if (!$customer) {
        return null;
    }

    $rows = function_exists('pos_get_customer_statement_rows')
        ? pos_get_customer_statement_rows($customer_id, $month)
        : [];

    $summary = [
        'month' => $month !== '' ? $month : current_time('Y-m'),
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

function crm_render_customer_statement_html($customer_id, $month = '') {
    $data = crm_get_customer_statement_data($customer_id, $month);
    if (!$data) {
        return '<div class="bntm-notice bntm-notice-error">Customer not found.</div>';
    }

    $customer = $data['customer'];
    $rows = $data['rows'];
    $summary = $data['summary'];
    $month_label = date_i18n('F Y', strtotime($summary['month'] . '-01'));

    ob_start();
    ?>
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin-bottom:16px;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
            <div><strong>Customer</strong><div><?php echo esc_html($customer->name); ?></div></div>
            <div><strong>Month</strong><div><?php echo esc_html($month_label); ?></div></div>
            <div><strong>Total Sales</strong><div><?php echo esc_html(crm_format_price($summary['total_sales'])); ?></div></div>
            <div><strong>Total Paid</strong><div><?php echo esc_html(crm_format_price($summary['total_paid'])); ?></div></div>
            <div><strong>Total Payables</strong><div><?php echo esc_html(crm_format_price($summary['total_payables'])); ?></div></div>
        </div>
    </div>

    <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Transaction #</th>
                    <th>Total</th>
                    <th>Payment Type</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Outstanding</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr><td colspan="7" style="text-align:center;">No transactions for this month.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo esc_html(date('M d, Y h:i A', strtotime($row->created_at))); ?></td>
                    <td>#<?php echo esc_html($row->transaction_number); ?></td>
                    <td><?php echo esc_html(crm_format_price($row->total)); ?></td>
                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_type))); ?></td>
                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_method))); ?></td>
                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $row->payment_status))); ?></td>
                    <td><?php echo esc_html(crm_format_price($row->payable_amount)); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

function bntm_ajax_crm_get_customer_statement() {
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $month = sanitize_text_field($_POST['month'] ?? '');
    wp_send_json_success(['html' => crm_render_customer_statement_html($customer_id, $month)]);
}

function bntm_ajax_crm_send_customer_statement() {
    check_ajax_referer('crm_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $customer_id = intval($_POST['customer_id'] ?? 0);
    $month = sanitize_text_field($_POST['month'] ?? '');
    $data = crm_get_customer_statement_data($customer_id, $month);

    if (!$data) {
        wp_send_json_error(['message' => 'Customer not found']);
    }

    $customer = $data['customer'];
    if (empty($customer->email)) {
        wp_send_json_error(['message' => 'Customer has no email address on file']);
    }

    $month_key = $data['summary']['month'];
    $month_label = date_i18n('F Y', strtotime($month_key . '-01'));
    $subject_template = bntm_get_setting('crm_statement_email_subject', 'Statement of Account - {month}');
    $subject = str_replace('{month}', $month_label, $subject_template);
    $from_email = bntm_get_setting('crm_statement_sender_email', get_option('admin_email'));
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    if (!empty($from_email)) {
        $headers[] = 'From: ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . ' <' . sanitize_email($from_email) . '>';
        $headers[] = 'Reply-To: ' . sanitize_email($from_email);
    }

    $message = '<p>Hello ' . esc_html($customer->name) . ',</p>';
    $message .= '<p>Here is your statement of account for <strong>' . esc_html($month_label) . '</strong>.</p>';
    $message .= crm_render_customer_statement_html($customer_id, $month);
    $message .= '<p>Please contact us if you need any clarification on your previous transactions or outstanding balance.</p>';

    $sent = wp_mail($customer->email, $subject, $message, $headers);
    if ($sent) {
        wp_send_json_success(['message' => 'Statement email sent to ' . $customer->email]);
    }

    wp_send_json_error(['message' => 'Failed to send email']);
}

add_action('wp_ajax_crm_save_general_settings', 'bntm_ajax_crm_save_general_settings');
function bntm_ajax_crm_save_general_settings() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('crm_quotation_validity', intval($_POST['quotation_validity_days'] ?? 30));
    bntm_set_setting('crm_currency', sanitize_text_field($_POST['currency'] ?? 'PHP'));
    // Statement email settings moved to payment module (op_email_settings)

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}
/* ---------- QUOTATION VIEW PAGE ---------- */

/* ---------- HELPER FUNCTIONS ---------- */
function crm_format_price($amount) {
    return '₱' . number_format((float)$amount, 2);
}
