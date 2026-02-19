<?php
/**
 * Module Name: Quotations and Billings
 * Module Slug: qb
 * Description: Quotations and Billing Schedules management
 * Version: 1.0.2
 * Author: Your Name
 * Icon: 📋
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_QB_PATH', dirname(__FILE__) . '/');
define('BNTM_QB_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 */
function bntm_qb_get_pages() {
    return [
        'Quotation' => '[qb_dashboard]',
        'Quotation View' => '[qb_quotation_view]'
    ];
}

/**
 * Get module database tables
 */
function bntm_qb_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'qb_quotations' => "CREATE TABLE {$prefix}qb_quotations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255),
            customer_phone VARCHAR(50),
            customer_company VARCHAR(255),
            customer_address TEXT,
            quotation_number VARCHAR(50) UNIQUE,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            amount DECIMAL(12,2) NOT NULL,
            tax DECIMAL(12,2) DEFAULT 0,
            discount DECIMAL(12,2) DEFAULT 0,
            total DECIMAL(12,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'PHP',
            valid_until DATE,
            status VARCHAR(50) DEFAULT 'draft',
            pdf_url VARCHAR(500),
            pdf_generated_at DATETIME,
            notes TEXT,
            terms_conditions TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            revision_number INT DEFAULT 1,
            parent_quotation_id BIGINT UNSIGNED,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_customer_email (customer_email)
        ) {$charset};",
        
        'qb_quotation_items' => "CREATE TABLE {$prefix}qb_quotation_items (
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
            FOREIGN KEY (quotation_id) REFERENCES {$prefix}qb_quotations(id) ON DELETE CASCADE
        ) {$charset};",
        
        'qb_billing_schedules' => "CREATE TABLE {$prefix}qb_billing_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255),
            customer_phone VARCHAR(50),
            quotation_id BIGINT UNSIGNED,
            schedule_type VARCHAR(50) NOT NULL,
            interval_value INT,
            interval_unit VARCHAR(20),
            specific_dates TEXT,
            payment_plan TEXT,
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
            INDEX idx_status (status),
            INDEX idx_next_date (next_invoice_date),
            INDEX idx_quotation (quotation_id)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 */
function bntm_qb_get_shortcodes() {
    return [
        'qb_dashboard' => 'bntm_shortcode_qb_dashboard',
        'qb_quotation_view' => 'bntm_shortcode_qb_quotation_view'
    ];
}

/**
 * Create module tables
 */
function bntm_qb_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_qb_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_qb_create_quotation', 'bntm_ajax_qb_create_quotation');
add_action('wp_ajax_qb_update_quotation', 'bntm_ajax_qb_update_quotation');
add_action('wp_ajax_qb_delete_quotation', 'bntm_ajax_qb_delete_quotation');
add_action('wp_ajax_qb_get_quotation', 'bntm_ajax_qb_get_quotation');
add_action('wp_ajax_qb_get_quotation_items', 'bntm_ajax_qb_get_quotation_items');
add_action('wp_ajax_qb_change_quotation_status', 'bntm_ajax_qb_change_quotation_status');
add_action('wp_ajax_qb_create_quotation_revision', 'bntm_ajax_qb_create_quotation_revision');
add_action('wp_ajax_qb_search_customers', 'bntm_ajax_qb_search_customers');
add_action('wp_ajax_qb_create_billing_schedule', 'bntm_ajax_qb_create_billing_schedule');
add_action('wp_ajax_qb_generate_scheduled_invoice', 'bntm_ajax_qb_generate_scheduled_invoice');

// Cron job for auto-generating invoices
add_action('init', 'qb_schedule_invoice_generation');
add_action('qb_generate_scheduled_invoices_hook', 'qb_process_scheduled_invoices');

function qb_schedule_invoice_generation() {
    if (!wp_next_scheduled('qb_generate_scheduled_invoices_hook')) {
        wp_schedule_event(time(), 'daily', 'qb_generate_scheduled_invoices_hook');
    }
}

/* ---------- DASHBOARD ---------- */
function bntm_shortcode_qb_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'quotations';
    
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
    .status-draft { background: #e5e7eb; color: #6b7280; }
    .status-sent { background: #bfdbfe; color: #1e40af; }
    .status-approved { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-billed { background: #fef3c7; color: #92400e; }
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
    
    .customer-search-wrapper {
        position: relative;
    }
    .customer-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e5e7eb;
        border-top: none;
        max-height: 200px;
        overflow-y: auto;
        z-index: 100;
        display: none;
    }
    .customer-search-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #f3f4f6;
    }
    .customer-search-item:hover {
        background: #f9fafb;
    }
    .customer-search-item strong {
        display: block;
        color: #1f2937;
    }
    .customer-search-item small {
        color: #6b7280;
        font-size: 12px;
    }
    </style>
    
    <div class="bntm-ecommerce-container">
        <div class="bntm-tabs">
            <a href="?tab=quotations" class="bntm-tab <?php echo $active_tab === 'quotations' ? 'active' : ''; ?>">Quotations</a>
            <?php if (bntm_is_module_enabled('op') && bntm_is_module_visible('op')): ?>
            <a href="?tab=billing" class="bntm-tab <?php echo $active_tab === 'billing' ? 'active' : ''; ?>">Billing Schedules</a>
            <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'quotations'): ?>
                <?php echo qb_quotations_tab($business_id); ?>
            <?php elseif ($active_tab === 'billing'): ?>
                <?php echo qb_billing_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo qb_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Quotations and Billing', $content);
}

function qb_quotations_tab($business_id) {
    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    
    $quotations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $quotations_table 
         ORDER BY created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('qb_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var qbNonce = '<?php echo $nonce; ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Quotations (<?php echo count($quotations); ?>)</h3>
            <button class="bntm-btn-primary" id="create-quotation-btn">+ Create Quotation</button>
        </div>
        
        <?php if (empty($quotations)): ?>
            <p>No quotations yet.</p>
        <?php else: ?>
        
        <div class="bntm-table-wrapper">
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
                            <td>
                                <strong><?php echo esc_html($quotation->quotation_number); ?></strong>
                                <?php if ($quotation->revision_number > 1): ?>
                                    <div style="font-size: 11px; color: #6b7280;">
                                        Revision <?php echo $quotation->revision_number; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($quotation->customer_name); ?></strong>
                                <?php if ($quotation->customer_company): ?>
                                    <div style="font-size: 12px; color: #6b7280;">
                                        <?php echo esc_html($quotation->customer_company); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($quotation->title); ?></td>
                            <td><?php echo qb_format_price($quotation->total); ?></td>
                            <td><?php echo $quotation->valid_until ? date('M d, Y', strtotime($quotation->valid_until)) : 'N/A'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($quotation->status); ?>">
                                    <?php echo ucfirst($quotation->status); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo get_page_link(get_page_by_path('quotation-view')) . '?id=' . esc_attr($quotation->rand_id); ?>" 
                                   class="bntm-btn-small" target="_blank">View</a>
                                <button class="bntm-btn-small edit-quotation-btn" data-id="<?php echo $quotation->id; ?>">Edit</button>
                                <?php if ($quotation->status !== 'billed'): ?>
                                    <button class="bntm-btn-small delete-quotation-btn" data-id="<?php echo $quotation->id; ?>" 
                                            style="background: #dc2626;">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
         </div>
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
                <input type="hidden" name="current_status" id="current_status">
                
                <!-- Status Management Section -->
                <div id="quotation-status-section" style="display: none; background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Current Status</label>
                            <span id="current-status-badge" class="status-badge"></span>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" id="create-revision-modal-btn" class="bntm-btn-secondary" style="display: none;">
                                📋 Create Revision
                            </button>
                        </div>
                    </div>
                    
                    <div id="status-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <!-- Status buttons will be dynamically inserted here -->
                    </div>
                </div>
                
                <!-- Customer Information -->
                <div class="bntm-form-group customer-search-wrapper">
                    <label>Customer Name *</label>
                    <input type="text" name="customer_name" id="customer_name" required 
                           placeholder="Type to search or enter new customer">
                    <div id="customer-search-results" class="customer-search-results"></div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Customer Email</label>
                    <input type="email" name="customer_email" id="customer_email">
                </div>
                
                <div class="bntm-form-group">
                    <label>Customer Phone</label>
                    <input type="text" name="customer_phone" id="customer_phone">
                </div>
                
                <div class="bntm-form-group">
                    <label>Company</label>
                    <input type="text" name="customer_company" id="customer_company">
                </div>
                
                <div class="bntm-form-group">
                    <label>Address</label>
                    <textarea name="customer_address" id="customer_address" rows="2"></textarea>
                </div>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
                
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

    <!-- Product Select Modal -->
    <div id="product-select-quotation-modal" class="bntm-modal">
        <div class="bntm-modal-content">
            <div class="bntm-modal-header">
                <h2>Select Product</h2>
                <span class="bntm-modal-close">&times;</span>
            </div>
            <div class="bntm-form" style="padding: 20px;">
                <div id="product-list-quotation-container">
                    <?php
                    $op_products_table = $wpdb->prefix . 'op_imported_products';
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$op_products_table'") === $op_products_table;
                    
                    if ($table_exists) {
                        $imported_products = $wpdb->get_results("SELECT * FROM {$op_products_table} ORDER BY product_name ASC");
                    } else {
                        $imported_products = [];
                    }
                    
                    if (empty($imported_products)):
                    ?>
                        <div style="text-align: center; padding: 20px;">
                            <p style="color: #6b7280; margin-bottom: 15px;">No products available.</p>
                            <p style="font-size: 14px; color: #9ca3af;">Import products from Inventory in the Payments module first.</p>
                        </div>
                    <?php else: ?>
                        <div class="product-selection-list">
                            <?php foreach ($imported_products as $product): ?>
                                <div class="product-select-item" 
                                     data-id="<?php echo $product->id; ?>" 
                                     data-name="<?php echo esc_attr($product->product_name); ?>"
                                     data-price="<?php echo $product->price; ?>">
                                    <strong><?php echo esc_html($product->product_name); ?></strong>
                                    <?php if (!empty($product->sku)): ?>
                                        <span class="product-sku">SKU: <?php echo esc_html($product->sku); ?></span>
                                    <?php endif; ?>
                                    <div class="product-details">
                                        <span class="product-price"><?php echo qb_format_price($product->price); ?></span>
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
    const customerNameInput = document.getElementById('customer_name');
    const customerSearchResults = document.getElementById('customer-search-results');
    
    let manualItemCounter = 1;
    let selectedQuotationProducts = [];
    let searchTimeout = null;
    
    // Customer search functionality
    customerNameInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.trim();
        
        if (searchTerm.length < 2) {
            customerSearchResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'qb_search_customers');
            formData.append('search_term', searchTerm);
            formData.append('nonce', qbNonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data.customers.length > 0) {
                    let html = '';
                    json.data.customers.forEach(customer => {
                        html += `
                            <div class="customer-search-item" data-name="${escapeHtml(customer.name)}" 
                                 data-email="${escapeHtml(customer.email || '')}"
                                 data-phone="${escapeHtml(customer.phone || '')}"
                                 data-company="${escapeHtml(customer.company || '')}"
                                 data-address="${escapeHtml(customer.address || '')}">
                                <strong>${escapeHtml(customer.name)}</strong>
                                <small>${escapeHtml(customer.company || '')} ${customer.email ? '• ' + escapeHtml(customer.email) : ''}</small>
                            </div>
                        `;
                    });
                    customerSearchResults.innerHTML = html;
                    customerSearchResults.style.display = 'block';
                    
                    // Add click handlers
                    document.querySelectorAll('.customer-search-item').forEach(item => {
                        item.addEventListener('click', function() {
                            document.getElementById('customer_name').value = this.dataset.name;
                            document.getElementById('customer_email').value = this.dataset.email;
                            document.getElementById('customer_phone').value = this.dataset.phone;
                            document.getElementById('customer_company').value = this.dataset.company;
                            document.getElementById('customer_address').value = this.dataset.address;
                            customerSearchResults.style.display = 'none';
                        });
                    });
                } else {
                    customerSearchResults.style.display = 'none';
                }
            });
        }, 300);
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.customer-search-wrapper')) {
            customerSearchResults.style.display = 'none';
        }
    });
    
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
        
        newRow.querySelector('.remove-item-btn').addEventListener('click', function() {
            newRow.remove();
        });
    });
    
    // Handle remove buttons
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
        document.getElementById('quotation-status-section').style.display = 'none';
        selectedQuotationProducts = [];
        renderSelectedQuotationProducts();
        
        quotationType.value = 'manual';
        manualItems.style.display = 'block';
        productsSection.style.display = 'none';
        
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
    
    // Product selection
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
                        <div style="font-size: 12px; color: #6b7280;">₱${product.price.toFixed(2)} each</div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="bntm-btn-small" onclick="updateQuotationQuantity(${index}, -1)">-</button>
                        <input type="number" value="${product.quantity}" min="1" 
                               onchange="updateQuotationQuantityInput(${index}, this.value)" 
                               style="width: 60px; text-align: center;">
                        <button type="button" class="bntm-btn-small" onclick="updateQuotationQuantity(${index}, 1)">+</button>
                    </div>
                    <div style="min-width: 100px; text-align: right; font-weight: 600;">
                        ₱${lineTotal.toFixed(2)}
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
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
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
        
        if (type === 'products') {
            if (selectedQuotationProducts.length === 0) {
                alert('Please add at least one product');
                return;
            }
            formData.append('products', JSON.stringify(selectedQuotationProducts));
        }
        
        const action = formData.get('quotation_id') ? 'qb_update_quotation' : 'qb_create_quotation';
        formData.append('action', action);
        formData.append('nonce', qbNonce);
        
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
    
    // Edit quotation - implementation similar to the original, adapted for QB
    document.querySelectorAll('.edit-quotation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const quotationId = this.dataset.id;
            
            const formData = new FormData();
            formData.append('action', 'qb_get_quotation');
            formData.append('quotation_id', quotationId);
            formData.append('nonce', qbNonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const quotation = json.data;
                    // Populate form fields
                    document.getElementById('quotation-modal-title').textContent = 'Edit Quotation';
                    document.getElementById('quotation_id').value = quotation.id;
                    document.getElementById('current_status').value = quotation.status;
                    document.getElementById('customer_name').value = quotation.customer_name;
                    document.getElementById('customer_email').value = quotation.customer_email || '';
                    document.getElementById('customer_phone').value = quotation.customer_phone || '';
                    document.getElementById('customer_company').value = quotation.customer_company || '';
                    document.getElementById('customer_address').value = quotation.customer_address || '';
                    document.getElementById('quotation_title').value = quotation.title;
                    document.getElementById('quotation_description').value = quotation.description || '';
                    document.getElementById('quotation_discount').value = quotation.discount;
                    document.getElementById('quotation_valid_until').value = quotation.valid_until || '';
                    document.getElementById('quotation_terms').value = quotation.terms_conditions || '';
                    document.getElementById('quotation_notes').value = quotation.notes || '';
                    
                    // Show status section
                    const statusSection = document.getElementById('quotation-status-section');
                    statusSection.style.display = 'block';
                    
                    const statusBadge = document.getElementById('current-status-badge');
                    statusBadge.textContent = quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1);
                    statusBadge.className = 'status-badge status-' + quotation.status;
                    
                    const revisionBtn = document.getElementById('create-revision-modal-btn');
                    if (quotation.status === 'sent' || quotation.status === 'approved') {
                        revisionBtn.style.display = 'inline-block';
                    } else {
                        revisionBtn.style.display = 'none';
                    }
                    
                    populateStatusActions(quotation.status);
                    
                    // Populate items
                    if (quotation.items && quotation.items.length > 0) {
                        const hasProducts = quotation.items.some(item => item.product_id && item.product_id > 0);
                        
                        if (hasProducts) {
                            document.getElementById('quotation_type').value = 'products';
                            quotationType.dispatchEvent(new Event('change'));
                            
                            selectedQuotationProducts = quotation.items.map(item => ({
                                id: item.product_id,
                                name: item.item_name,
                                price: parseFloat(item.unit_price),
                                quantity: parseInt(item.quantity)
                            }));
                            
                            renderSelectedQuotationProducts();
                        } else {
                            document.getElementById('quotation_type').value = 'manual';
                            quotationType.dispatchEvent(new Event('change'));
                            
                            const itemsList = document.getElementById('manual-items-list');
                            itemsList.innerHTML = '';
                            
                            quotation.items.forEach((item, index) => {
                                const newRow = document.createElement('div');
                                newRow.className = 'manual-item-row';
                                newRow.innerHTML = `
                                    <input type="text" name="manual_items[${index}][name]" placeholder="Item name" value="${escapeHtml(item.item_name)}" style="width: 35%;">
                                    <textarea name="manual_items[${index}][description]" placeholder="Description" rows="1" style="width: 30%;">${escapeHtml(item.description || '')}</textarea>
                                    <input type="number" name="manual_items[${index}][quantity]" placeholder="Qty" min="1" value="${item.quantity}" style="width: 10%;">
                                    <input type="number" name="manual_items[${index}][price]" placeholder="Price" step="0.01" min="0" value="${item.unit_price}" style="width: 15%;">
                                    <button type="button" class="bntm-btn-small remove-item-btn" ${index === 0 ? 'style="display:none;"' : ''}>×</button>
                                `;
                                itemsList.appendChild(newRow);
                                
                                newRow.querySelector('.remove-item-btn').addEventListener('click', function() {
                                    newRow.remove();
                                });
                            });
                            
                            manualItemCounter = quotation.items.length;
                        }
                    }
                    
                    quotationModal.style.display = 'block';
                }
            });
        });
    });
    
    function populateStatusActions(currentStatus) {
        const statusActions = document.getElementById('status-actions');
        statusActions.innerHTML = '';
        
        const statusWorkflow = {
            'draft': [
                { status: 'sent', label: 'Mark as Sent', color: '#3b82f6' }
            ],
            'sent': [
                { status: 'approved', label: 'Mark as Approved', color: '#10b981' },
                { status: 'rejected', label: 'Mark as Rejected', color: '#dc2626' }
            ],
            'approved': [
                { status: 'billed', label: 'Mark as Billed', color: '#f59e0b' }
            ],
            'rejected': [
                { status: 'draft', label: 'Reopen as Draft', color: '#6b7280' }
            ],
            'billed': []
        };
        
        const actions = statusWorkflow[currentStatus] || [];
        
        actions.forEach(action => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bntm-btn-small';
            btn.textContent = action.label;
            btn.style.background = action.color;
            btn.dataset.status = action.status;
            btn.addEventListener('click', function() {
                changeQuotationStatus(action.status, action.label);
            });
            statusActions.appendChild(btn);
        });
        
        if (actions.length === 0 && currentStatus === 'billed') {
            statusActions.innerHTML = '<p style="color: #6b7280; font-size: 13px; margin: 0;">This quotation has been billed and cannot be modified.</p>';
        }
    }
    
    function changeQuotationStatus(newStatus, label) {
        if (!confirm(`${label}?`)) return;
        
        const quotationId = document.getElementById('quotation_id').value;
        const formData = new FormData();
        formData.append('action', 'qb_change_quotation_status');
        formData.append('quotation_id', quotationId);
        formData.append('status', newStatus);
        formData.append('nonce', qbNonce);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('quotation-form-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
            }
        });
    }
    
    // Handle create revision button
    document.getElementById('create-revision-modal-btn').addEventListener('click', function() {
        if (!confirm('Create a new revision of this quotation?\n\nThe original will be kept for reference and a new draft revision will be created.')) return;
        
        const quotationId = document.getElementById('quotation_id').value;
        const formData = new FormData();
        formData.append('action', 'qb_create_quotation_revision');
        formData.append('quotation_id', quotationId);
        formData.append('nonce', qbNonce);
        
        this.disabled = true;
        this.textContent = '⏳ Creating...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('quotation-form-message');
            if (json.success) {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                this.disabled = false;
                this.textContent = '📋 Create Revision';
            }
        });
    });
    
    // Delete quotation
    document.querySelectorAll('.delete-quotation-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Are you sure you want to delete this quotation?')) return;
            
            const quotationId = this.dataset.id;
            const formData = new FormData();
            formData.append('action', 'qb_delete_quotation');
            formData.append('quotation_id', quotationId);
            formData.append('nonce', qbNonce);
            
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

function qb_billing_tab($business_id) {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'qb_billing_schedules';
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, q.quotation_number, q.title as quotation_title, q.total as quotation_total
         FROM $schedules_table s
         LEFT JOIN $quotations_table q ON s.quotation_id = q.id
         ORDER BY s.created_at DESC",
        $business_id
    ));

    $nonce = wp_create_nonce('qb_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var qbNonce = '<?php echo $nonce; ?>';
    </script>

    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Billing Schedules (<?php echo count($schedules); ?>)</h3>
            <button class="bntm-btn-primary" id="create-schedule-btn">+ Create Schedule</button>
        </div>
        
        <?php if (empty($schedules)): ?>
            <p>No billing schedules yet.</p>
        <?php else: ?>
        
        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Quotation</th>
                        <th>Customer</th>
                        <th>Schedule Type</th>
                        <th>Amount/Invoice</th>
                        <th>Progress</th>
                        <th>Next Invoice</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                   <?php foreach ($schedules as $schedule): 
                         $paid_amount = $wpdb->get_var($wpdb->prepare(
                             "SELECT COALESCE(SUM(total), 0) FROM $invoices_table 
                              WHERE reference_type = 'qb_schedule' 
                              AND reference_id = %d 
                              AND payment_status = 'paid'",
                             $schedule->id
                         ));
                         
                         $total_expected = floatval($schedule->quotation_total);
                     
                         // Fix: use correct variable name and simplify ternary
                         $progress_percentage = ($total_expected > 0) 
                             ? ($paid_amount / $total_expected) * 100 
                             : 0;
                     
                         $payment_plan = json_decode(stripslashes($schedule->payment_plan), true);
                     ?>

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
                        <td>
                            <?php 
                            if ($schedule->amount_per_invoice > 0) {
                                echo qb_format_price($schedule->amount_per_invoice);
                            } else {
                                if ($payment_plan && isset($payment_plan['amount_per_invoice'])) {
                                    echo qb_format_price($payment_plan['amount_per_invoice']);
                                } else {
                                    echo qb_format_price(0);
                                }
                            }
                            ?>
                            <div style="font-size: 11px; color: #6b7280;">
                                <?php 
                                if ($payment_plan) {
                                    switch($payment_plan['type']) {
                                        case 'divided':
                                            echo "Equal Division";
                                            if (isset($payment_plan['installments'])) {
                                                echo " ({$payment_plan['installments']}x)";
                                            }
                                            break;
                                        case 'full':
                                            echo "Full Amount";
                                            break;
                                        case 'percentage':
                                            echo "Custom %";
                                            break;
                                    }
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <div style="margin-bottom: 5px;">
                                <strong><?php echo qb_format_price($paid_amount); ?></strong> / <?php echo qb_format_price($total_expected); ?>
                            </div>
                            <div style="background: #e5e7eb; border-radius: 4px; overflow: hidden; height: 8px;">
                                <div style="background: #10b981; height: 100%; width: <?php echo min(100, $progress_percentage); ?>%;"></div>
                            </div>
                            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                                <?php echo number_format($progress_percentage, 1); ?>% paid
                            </div>
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
                            <button class="bntm-btn-small view-schedule-invoices-btn" data-id="<?php echo $schedule->id; ?>"
                                    style="background: #3b82f6;">
                                View Invoices
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Billing Schedule Modal -->
<div id="schedule-modal" class="bntm-modal">
    <div class="bntm-modal-content" style="max-width: 800px;">
        <div class="bntm-modal-header">
            <h2>Create Billing Schedule</h2>
            <span class="bntm-modal-close">&times;</span>
        </div>
        <form id="schedule-form" class="bntm-form">
            <!-- Customer Information -->
            <div class="bntm-form-group customer-search-wrapper">
                <label>Customer Name *</label>
                <input type="text" name="customer_name" id="schedule_customer_name" required 
                       placeholder="Type to search or enter customer name">
                <div id="schedule-customer-search-results" class="customer-search-results"></div>
            </div>
            
            <div class="bntm-form-group">
                <label>Customer Email</label>
                <input type="email" name="customer_email" id="schedule_customer_email">
            </div>
            
            <div class="bntm-form-group">
                <label>Customer Phone</label>
                <input type="text" name="customer_phone" id="schedule_customer_phone">
            </div>
            
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
            
            <div class="bntm-form-group">
                <label>Select Quotation (Optional)</label>
                <select name="quotation_id" id="schedule_quotation_id">
                    <option value="">None - Manual Schedule</option>
                    <?php
                    $quotations = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM $quotations_table 
                         WHERE status IN ('approved')
                         ORDER BY created_at DESC",
                        $business_id
                    ));
                    foreach ($quotations as $quotation):
                    ?>
                    <option value="<?php echo $quotation->id; ?>" 
                            data-customer-name="<?php echo esc_attr($quotation->customer_name); ?>"
                            data-customer-email="<?php echo esc_attr($quotation->customer_email); ?>"
                            data-customer-phone="<?php echo esc_attr($quotation->customer_phone); ?>"
                            data-total="<?php echo $quotation->total; ?>"
                            data-description="<?php echo esc_attr($quotation->description); ?>"
                            data-quotation-number="<?php echo esc_attr($quotation->quotation_number); ?>">
                        <?php echo esc_html($quotation->quotation_number); ?> - 
                        <?php echo esc_html($quotation->customer_name); ?> - 
                        <?php echo qb_format_price($quotation->total); ?>
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
            
            <input type="hidden" name="payment_plan_json" id="payment_plan_json">
            
            <div class="bntm-form-group">
                <label>Total Amount *</label>
                <input type="number" name="total_amount" id="total_amount" step="0.01" min="0" required>
                <small>This will be used to calculate payment splits</small>
            </div>
            
            <div class="bntm-form-group">
                <label>Payment Plan Type *</label>
                <select name="payment_plan_type" id="payment_plan_type" required>
                    <option value="">Select Payment Plan</option>
                    <option value="divided">Equal Division - Divide total into equal installments</option>
                    <option value="full">Full Amount - Bill full amount each time</option>
                    <option value="percentage">Custom Percentage - Set custom % for each invoice</option>
                </select>
            </div>
            
            <!-- Payment plan fields -->
            <div id="divided-plan-fields" style="display: none;">
                <div class="bntm-form-group">
                    <label>Number of Installments *</label>
                    <input type="number" name="divided_installments" id="divided_installments" min="2" placeholder="e.g., 3, 6, 12">
                    <small>Total amount will be divided equally into this many invoices</small>
                </div>
                <div id="divided-preview" style="background: #eff6ff; padding: 12px; border-radius: 4px; margin-top: 10px; display: none;">
                    <strong>Preview:</strong>
                    <div id="divided-preview-content"></div>
                </div>
            </div>
            
            <div id="full-plan-fields" style="display: none;">
                <div class="bntm-form-group">
                    <label>Number of Invoices (optional)</label>
                    <input type="number" name="full_invoices" id="full_invoices" min="1" placeholder="Leave empty for unlimited">
                    <small>Each invoice will be for the full amount</small>
                </div>
                <div style="background: #fef3c7; padding: 12px; border-radius: 4px; margin-top: 10px;">
                    <strong>⚠️ Note:</strong> Each invoice will bill the full amount. This is useful for recurring services or subscriptions.
                </div>
            </div>
            
            <div id="percentage-plan-fields" style="display: none;">
                <div class="bntm-form-group">
                    <label>Percentage Split *</label>
                    <input type="text" name="percentage_split" id="percentage_split" placeholder="e.g., 10-20-70 or 30-30-40">
                    <small>Enter percentages separated by dashes (must total 100%)</small>
                </div>
                <div id="percentage-preview" style="background: #eff6ff; padding: 12px; border-radius: 4px; margin-top: 10px; display: none;">
                    <strong>Preview:</strong>
                    <div id="percentage-preview-content"></div>
                </div>
                <div id="percentage-error" style="color: #dc2626; margin-top: 5px; display: none;"></div>
            </div>
            
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
    const paymentPlanType = document.getElementById('payment_plan_type');
    const customerNameInput = document.getElementById('schedule_customer_name');
    const customerSearchResults = document.getElementById('schedule-customer-search-results');
    
    let quotationTotalAmount = 0;
    let searchTimeout = null;
    
    // Customer search functionality
    customerNameInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = this.value.trim();
        
        if (searchTerm.length < 2) {
            customerSearchResults.style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'qb_search_customers');
            formData.append('search_term', searchTerm);
            formData.append('nonce', qbNonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data.customers.length > 0) {
                    let html = '';
                    json.data.customers.forEach(customer => {
                        html += `
                            <div class="customer-search-item" data-name="${escapeHtml(customer.name)}" 
                                 data-email="${escapeHtml(customer.email || '')}"
                                 data-phone="${escapeHtml(customer.phone || '')}">
                                <strong>${escapeHtml(customer.name)}</strong>
                                <small>${escapeHtml(customer.company || '')} ${customer.email ? '• ' + escapeHtml(customer.email) : ''}</small>
                            </div>
                        `;
                    });
                    customerSearchResults.innerHTML = html;
                    customerSearchResults.style.display = 'block';
                    
                    // Add click handlers
                    document.querySelectorAll('#schedule-customer-search-results .customer-search-item').forEach(item => {
                        item.addEventListener('click', function() {
                            document.getElementById('schedule_customer_name').value = this.dataset.name;
                            document.getElementById('schedule_customer_email').value = this.dataset.email;
                            document.getElementById('schedule_customer_phone').value = this.dataset.phone;
                            customerSearchResults.style.display = 'none';
                        });
                    });
                } else {
                    customerSearchResults.style.display = 'none';
                }
            });
        }, 300);
    });
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.customer-search-wrapper')) {
            if (customerSearchResults) {
                customerSearchResults.style.display = 'none';
            }
        }
    });
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
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
    
    // Toggle payment plan fields
    paymentPlanType.addEventListener('change', function() {
        document.getElementById('divided-plan-fields').style.display = 'none';
        document.getElementById('full-plan-fields').style.display = 'none';
        document.getElementById('percentage-plan-fields').style.display = 'none';
        
        if (this.value === 'divided') {
            document.getElementById('divided-plan-fields').style.display = 'block';
        } else if (this.value === 'full') {
            document.getElementById('full-plan-fields').style.display = 'block';
        } else if (this.value === 'percentage') {
            document.getElementById('percentage-plan-fields').style.display = 'block';
        }
    });
    
    // Total amount input change
    document.getElementById('total_amount').addEventListener('input', function() {
        quotationTotalAmount = parseFloat(this.value) || 0;
        
        // Trigger preview updates
        const dividedInstallments = document.getElementById('divided_installments');
        if (dividedInstallments.value) {
            dividedInstallments.dispatchEvent(new Event('input'));
        }
        
        const percentageSplit = document.getElementById('percentage_split');
        if (percentageSplit.value) {
            percentageSplit.dispatchEvent(new Event('input'));
        }
    });
    
    // Divided installments preview
    document.getElementById('divided_installments').addEventListener('input', function() {
        const installments = parseInt(this.value);
        const preview = document.getElementById('divided-preview');
        const content = document.getElementById('divided-preview-content');
        
        if (installments > 0 && quotationTotalAmount > 0) {
            const amountPerInvoice = quotationTotalAmount / installments;
            content.innerHTML = `
                <div style="margin-top: 8px;">
                    Total: ₱${quotationTotalAmount.toFixed(2)}<br>
                    Installments: ${installments}<br>
                    <strong>Amount per invoice: ₱${amountPerInvoice.toFixed(2)}</strong>
                </div>
            `;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    });
    
    // Percentage split validation and preview
    document.getElementById('percentage_split').addEventListener('input', function() {
        const split = this.value.trim();
        const preview = document.getElementById('percentage-preview');
        const content = document.getElementById('percentage-preview-content');
        const error = document.getElementById('percentage-error');
        
        if (!split) {
            preview.style.display = 'none';
            error.style.display = 'none';
            return;
        }
        
        const percentages = split.split('-').map(p => parseFloat(p.trim()));
        const total = percentages.reduce((sum, p) => sum + (isNaN(p) ? 0 : p), 0);
        const isValid = percentages.every(p => !isNaN(p) && p > 0) && Math.abs(total - 100) < 0.01;
        
        if (isValid && quotationTotalAmount > 0) {
            let previewHtml = '<div style="margin-top: 8px;">';
            percentages.forEach((percentage, index) => {
                const amount = (quotationTotalAmount * percentage) / 100;
                previewHtml += `Invoice ${index + 1}: ${percentage}% = ₱${amount.toFixed(2)}<br>`;
            });
            previewHtml += `<strong>Total: ${total.toFixed(1)}% = ₱${quotationTotalAmount.toFixed(2)}</strong>`;
            previewHtml += '</div>';
            
            content.innerHTML = previewHtml;
            preview.style.display = 'block';
            error.style.display = 'none';
        } else if (split) {
            preview.style.display = 'none';
            if (Math.abs(total - 100) >= 0.01) {
                error.textContent = `Total is ${total.toFixed(1)}% - must equal 100%`;
                error.style.display = 'block';
            } else {
                error.textContent = 'Invalid format. Use numbers separated by dashes (e.g., 30-30-40)';
                error.style.display = 'block';
            }
        }
    });
    
    // Handle quotation selection
    quotationSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const quotationPreview = document.getElementById('quotation-preview');
        
        if (this.value) {
            const customerName = selectedOption.dataset.customerName;
            const customerEmail = selectedOption.dataset.customerEmail;
            const customerPhone = selectedOption.dataset.customerPhone;
            const total = selectedOption.dataset.total;
            const description = selectedOption.dataset.description;
            const quotationNumber = selectedOption.dataset.quotationNumber;
            const quotationId = this.value;
            
            quotationTotalAmount = parseFloat(total);
            
            // Populate customer fields
            document.getElementById('schedule_customer_name').value = customerName;
            document.getElementById('schedule_customer_email').value = customerEmail;
            document.getElementById('schedule_customer_phone').value = customerPhone;
            
            // Set total amount
            document.getElementById('total_amount').value = total;
            
            document.getElementById('preview-customer').textContent = customerName;
            document.getElementById('preview-total').textContent = '₱' + parseFloat(total).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
            document.getElementById('preview-description').textContent = description || 'No description';
            
            fetchQuotationItems(quotationId);
            
            quotationPreview.style.display = 'block';
        } else {
            quotationPreview.style.display = 'none';
            quotationTotalAmount = 0;
        }
    });
    
    function fetchQuotationItems(quotationId) {
        const formData = new FormData();
        formData.append('action', 'qb_get_quotation_items');
        formData.append('quotation_id', quotationId);
        formData.append('nonce', qbNonce);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data.items) {
                    const itemsContainer = document.getElementById('preview-items');
                    let itemsHtml = '';
                    let itemsTotal = 0;
                    
                    json.data.items.forEach(item => {
                        const lineTotal = parseFloat(item.total);
                        itemsTotal += lineTotal;
                        
                        itemsHtml += `
                            <div class="preview-item">
                                <strong>${item.item_name}</strong>
                                ${item.description ? '<div style="color: #6b7280; font-size: 12px; margin-top: 2px;">' + item.description + '</div>' : ''}
                                <div style="margin-top: 4px;">
                                    Qty: ${item.quantity} × ₱${parseFloat(item.unit_price).toFixed(2)} = 
                                    <strong>₱${lineTotal.toFixed(2)}</strong>
                                </div>
                            </div>
                        `;
                    });
                    
                    itemsHtml += `
                        <div style="border-top: 2px solid #e5e7eb; margin-top: 10px; padding-top: 10px; text-align: right;">
                            <strong>Items Total: ₱${itemsTotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}</strong>
                        </div>
                    `;
                    
                    itemsContainer.innerHTML = itemsHtml || '<div style="color: #6b7280;">No items found</div>';
                }
            })
            .catch(error => console.error('Error fetching items:', error));
    }
    
    // Open create schedule modal
    document.getElementById('create-schedule-btn').addEventListener('click', function() {
        scheduleForm.reset();
        document.getElementById('quotation-preview').style.display = 'none';
        document.getElementById('divided-plan-fields').style.display = 'none';
        document.getElementById('full-plan-fields').style.display = 'none';
        document.getElementById('percentage-plan-fields').style.display = 'none';
        scheduleModal.style.display = 'block';
        quotationTotalAmount = 0;
        
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
        
        const paymentPlan = paymentPlanType.value;
        let paymentPlanData = { type: paymentPlan };
        
        // Validate and build payment plan
        if (paymentPlan === 'divided') {
            const installments = parseInt(document.getElementById('divided_installments').value);
            if (!installments || installments < 2) {
                alert('Please enter a valid number of installments (minimum 2)');
                return;
            }
            paymentPlanData.installments = installments;
            paymentPlanData.amount_per_invoice = quotationTotalAmount / installments;
        } else if (paymentPlan === 'full') {
            const invoices = parseInt(document.getElementById('full_invoices').value) || 0;
            paymentPlanData.total_invoices = invoices;
            paymentPlanData.amount_per_invoice = quotationTotalAmount;
        } else if (paymentPlan === 'percentage') {
            const split = document.getElementById('percentage_split').value.trim();
            const percentages = split.split('-').map(p => parseFloat(p.trim()));
            const total = percentages.reduce((sum, p) => sum + (isNaN(p) ? 0 : p), 0);
            
            if (!percentages.every(p => !isNaN(p) && p > 0) || Math.abs(total - 100) >= 0.01) {
                alert('Invalid percentage split. Must total 100%');
                return;
            }
            
            paymentPlanData.percentages = split;
            paymentPlanData.percentage_array = percentages;
            paymentPlanData.amounts = percentages.map(p => (quotationTotalAmount * p) / 100);
        } else {
            alert('Please select a payment plan type');
            return;
        }
        
        document.getElementById('payment_plan_json').value = JSON.stringify(paymentPlanData);
        
        const formData = new FormData(this);
        formData.append('action', 'qb_create_billing_schedule');
        formData.append('nonce', qbNonce);
        
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
            formData.append('action', 'qb_generate_scheduled_invoice');
            formData.append('schedule_id', scheduleId);
            formData.append('nonce', qbNonce);
            
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
    
    // View invoices
    document.querySelectorAll('.view-schedule-invoices-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const scheduleId = this.dataset.id;
            window.location.href = window.location.origin + '/online-payments?tab=invoices&schedule_id=' + scheduleId;
        });
    });
})();
</script>
<?php
return ob_get_clean();
}
function qb_settings_tab($business_id) {
$nonce = wp_create_nonce('qb_nonce');
ob_start();
?>
<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
var qbNonce = '<?php echo $nonce; ?>';
</script>

<div class="bntm-form-section">
    <h3>General Settings</h3>
    <form id="general-settings-form">
        <div class="bntm-form-group">
            <label>Default Quotation Validity (days)</label>
            <input type="number" name="quotation_validity_days" 
                   value="<?php echo esc_attr(bntm_get_setting('qb_quotation_validity', '30')); ?>" min="1">
        </div>
        
        <div class="bntm-form-group">
            <label>Currency</label>
            <select name="currency">
                <option value="USD" <?php selected(bntm_get_setting('qb_currency', 'PHP'), 'USD'); ?>>USD</option>
                <option value="EUR" <?php selected(bntm_get_setting('qb_currency', 'PHP'), 'EUR'); ?>>EUR</option>
                <option value="GBP" <?php selected(bntm_get_setting('qb_currency', 'PHP'), 'GBP'); ?>>GBP</option>
                <option value="PHP" <?php selected(bntm_get_setting('qb_currency', 'PHP'), 'PHP'); ?>>PHP</option>
            </select>
        </div>
        
        <div class="bntm-form-group">
            <label>Tax Rate (%)</label>
            <input type="number" name="tax_rate" step="0.01" min="0" max="100"
                   value="<?php echo esc_attr(bntm_get_setting('qb_tax_rate', '0')); ?>">
        </div>
        
        <button type="submit" class="bntm-btn-primary">Save Settings</button>
        <div id="general-settings-message" style="margin-top: 15px;"></div>
    </form>
</div>

<script>
(function() {
    document.getElementById('general-settings-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'qb_save_general_settings');
        formData.append('nonce', qbNonce);
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            const msg = document.getElementById('general-settings-message');
            msg.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save Settings';
        });
    });
})();
</script>
<?php
return ob_get_clean();
}
/**
 * Quotation View Page
 */
function bntm_shortcode_qb_quotation_view() {
    $quotation_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    
    if (empty($quotation_id)) {
        return '<div class="bntm-container"><p>Invalid quotation ID.</p></div>';
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $items_table = $wpdb->prefix . 'qb_quotation_items';
    
    $quotation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $quotations_table WHERE rand_id = %s",
        $quotation_id
    ));
    
    if (!$quotation) {
        return '<div class="bntm-container"><p>Quotation not found.</p></div>';
    }
    
    $logo = bntm_get_site_logo();
    $site_title = bntm_get_site_title();
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $items_table WHERE quotation_id = %d",
        $quotation->id
    ));
    $business_user = get_userdata($quotation->business_id);
    
    ob_start();
    ?>
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    .quotation-view { 
        max-width: 800px; 
        margin: 0 auto; 
        padding: 20px; 
        background: white; 
        font-family: Arial, sans-serif; 
        font-size: 11pt; 
        line-height: 1.4; 
        color: #000; 
    }
    .quotation-header { 
        display: flex; 
        justify-content: space-between; 
        padding-bottom: 15px; 
        margin-bottom: 20px; 
        border-bottom: 2px solid #000; 
    }
    .company-name { 
        font-size: 16pt; 
        font-weight: bold; 
        margin-bottom: 5px; 
    }
    .quotation-title { 
        font-size: 28pt; 
        font-weight: bold; 
        text-align: right; 
    }
    .quotation-number { 
        font-size: 14pt; 
        margin-top: 5px; 
        text-align: right; 
    }
    .info-section { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 30px; 
        margin-bottom: 20px; 
    }
    .info-label { 
        font-weight: bold; 
        font-size: 9pt; 
        text-transform: uppercase; 
        margin-bottom: 5px; 
    }
    .info-value strong { 
        font-size: 11pt; 
    }
    .description-box { 
        margin-bottom: 20px; 
        padding: 10px; 
        border: 1px solid #000; 
    }
    .description-title { 
        font-weight: bold; 
        margin-bottom: 5px; 
    }
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-bottom: 15px; 
    }
    th { 
        text-align: left; 
        font-weight: bold; 
        padding: 8px 5px; 
        border-bottom: 2px solid #000; 
        font-size: 9pt; 
        text-transform: uppercase; 
    }
    td { 
        padding: 8px 5px; 
        border-bottom: 1px solid #ddd; 
        font-size: 10pt; 
        vertical-align: top; 
    }
    tbody tr:last-child td { 
        border-bottom: 1px solid #000; 
    }
    .text-right { 
        text-align: right; 
    }
    .item-description { 
        font-size: 9pt; 
        color: #666; 
        margin-top: 3px; 
    }
    .quotation-totals { 
        max-width: 350px; 
        margin-left: auto; 
        margin-bottom: 20px; 
    }
    .total-row { 
        display: flex; 
        justify-content: space-between; 
        padding: 5px 0; 
        font-size: 10pt; 
    }
    .total-row.grand-total { 
        font-size: 12pt; 
        font-weight: bold; 
        padding: 10px 0; 
        margin-top: 5px; 
        border-top: 2px solid #000; 
        border-bottom: 2px solid #000; 
    }
    .terms-section { 
        margin-top: 20px; 
        padding-top: 15px; 
        border-top: 1px solid #000; 
    }
    .terms-title { 
        font-weight: bold; 
        font-size: 10pt; 
        text-transform: uppercase; 
        margin-bottom: 8px; 
    }
    .terms-content { 
        font-size: 9pt; 
        line-height: 1.5; 
    }
    .status-badge { 
        display: inline-block; 
        padding: 4px 12px; 
        border: 1px solid #000; 
        font-size: 10pt; 
        font-weight: bold; 
        text-transform: uppercase; 
    }
    .quotation-actions { 
        margin-top: 20px; 
        text-align: center; 
    }
    .print-btn {
        background: #3b82f6;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .print-btn:hover {
        background: #2563eb;
    }
    @media print {
        .quotation-view { 
            padding: 15px; 
        }
        .quotation-actions { 
            display: none !important; 
        }
    }
    @media screen and (max-width: 768px) {
        .quotation-header { 
            flex-direction: column; 
            gap: 15px; 
        }
        .quotation-title, 
        .quotation-number { 
            text-align: left; 
        }
        .info-section { 
            grid-template-columns: 1fr; 
            gap: 15px; 
        }
    }
    </style>
    
    <div class="quotation-view">
        <div class="quotation-header">
            <div>
                <?php if ($logo): ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="Logo" style="max-width: 120px; max-height: 50px; margin-bottom: 10px;">
                <?php endif; ?>
                <div class="company-name"><?php echo esc_html($site_title ?: bntm_get_setting('qb_company_name', 'Your Company')); ?></div>
                <div style="font-size: 10pt; margin-top: 5px;">
                    Date: <?php echo date('M d, Y', strtotime($quotation->created_at)); ?>
                </div>
                <?php if ($quotation->valid_until): ?>
                <div style="font-size: 10pt;">
                    Valid Until: <?php echo date('M d, Y', strtotime($quotation->valid_until)); ?>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="quotation-title">QUOTATION</div>
                <div class="quotation-number"><?php echo esc_html($quotation->quotation_number); ?></div>
                <div style="margin-top: 10px; text-align: right;">
                    <span class="status-badge">CONFIDENTIAL</span>
                </div>
            </div>
        </div>
        
        <div class="info-section">
            <div>
                <div class="info-label">FROM</div>
                <div class="info-value">
                    <strong><?php echo esc_html($business_user->display_name); ?></strong><br>
                    <?php echo esc_html($business_user->user_email); ?>
                </div>
            </div>
            <div>
                <div class="info-label">BILL TO</div>
                <div class="info-value">
                    <strong><?php echo esc_html($quotation->customer_name); ?></strong><br>
                    <?php if ($quotation->customer_company): ?>
                        <?php echo esc_html($quotation->customer_company); ?><br>
                    <?php endif; ?>
                    <?php if ($quotation->customer_email): ?>
                        <?php echo esc_html($quotation->customer_email); ?><br>
                    <?php endif; ?>
                    <?php if ($quotation->customer_phone): ?>
                        <?php echo esc_html($quotation->customer_phone); ?><br>
                    <?php endif; ?>
                    <?php if ($quotation->customer_address): ?>
                        <?php echo nl2br(esc_html($quotation->customer_address)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($quotation->description): ?>
        <div class="description-box">
            <div class="description-title"><?php echo esc_html($quotation->title); ?></div>
            <div><?php echo nl2br(esc_html($quotation->description)); ?></div>
        </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>ITEM</th>
                    <th class="text-right">QTY</th>
                    <th class="text-right">UNIT PRICE</th>
                    <th class="text-right">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->item_name); ?></strong>
                        <?php if ($item->description): ?>
                            <div class="item-description"><?php echo nl2br(esc_html($item->description)); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-right"><?php echo esc_html($item->quantity); ?></td>
                    <td class="text-right"><?php echo qb_format_price($item->unit_price); ?></td>
                    <td class="text-right"><strong><?php echo qb_format_price($item->total); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="quotation-totals">
            <div class="total-row" style="padding-top: 8px; border-top: 1px solid #ddd;">
                <span>Subtotal:</span>
                <span><?php echo qb_format_price($quotation->amount); ?></span>
            </div>
            <?php if ($quotation->tax > 0): ?>
            <div class="total-row">
                <span>Tax:</span>
                <span><?php echo qb_format_price($quotation->tax); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($quotation->discount > 0): ?>
            <div class="total-row">
                <span>Discount:</span>
                <span>-<?php echo qb_format_price($quotation->discount); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row grand-total">
                <span>TOTAL:</span>
                <span><?php echo qb_format_price($quotation->total); ?></span>
            </div>
        </div>
        
        <?php if ($quotation->terms_conditions): ?>
        <div class="terms-section">
            <div class="terms-title">Terms & Conditions</div>
            <div class="terms-content"><?php echo nl2br(esc_html($quotation->terms_conditions)); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($quotation->notes): ?>
        <div class="terms-section">
            <div class="terms-title">Notes</div>
            <div class="terms-content"><?php echo nl2br(esc_html($quotation->notes)); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="quotation-actions">
            <button onclick="window.print()" class="print-btn">🖨️ Print / Download PDF</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
/* ---------- HELPER FUNCTIONS ---------- */
function qb_format_price($amount) {
return '₱' . number_format((float)$amount, 2);
}
/* ---------- AJAX HANDLERS ---------- */
// Customer search
function bntm_ajax_qb_search_customers() {
check_ajax_referer('qb_nonce', 'nonce');
if (!is_user_logged_in()) {
    wp_send_json_error(['message' => 'Unauthorized']);
}

global $wpdb;
$search_term = sanitize_text_field($_POST['search_term'] ?? '');

if (empty($search_term) || strlen($search_term) < 2) {
    wp_send_json_success(['customers' => []]);
}

// Search in CRM customers if module exists
$crm_table = $wpdb->prefix . 'crm_customers';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$crm_table'") === $crm_table;

$customers = [];

if ($table_exists) {
    $crm_customers = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT name, email, contact_number AS phone, company, address
             FROM $crm_table
             WHERE name LIKE %s OR email LIKE %s OR company LIKE %s
             LIMIT 10",
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%',
            '%' . $wpdb->esc_like($search_term) . '%'
        )
    );

    $customers = array_merge($customers, $crm_customers);
}


// Also search in existing quotations
$quotations_table = $wpdb->prefix . 'qb_quotations';
$quotation_customers = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT customer_name as name, customer_email as email, 
            customer_phone as phone, customer_company as company, customer_address as address
     FROM $quotations_table
     WHERE customer_name LIKE %s OR customer_email LIKE %s OR customer_company LIKE %s
     LIMIT 10",
    '%' . $wpdb->esc_like($search_term) . '%',
    '%' . $wpdb->esc_like($search_term) . '%',
    '%' . $wpdb->esc_like($search_term) . '%'
));

$customers = array_merge($customers, $quotation_customers);

// Remove duplicates
$unique_customers = [];
$seen = [];
foreach ($customers as $customer) {
    $key = strtolower($customer->name . $customer->email);
    if (!isset($seen[$key])) {
        $seen[$key] = true;
        $unique_customers[] = $customer;
    }
}

wp_send_json_success(['customers' => array_slice($unique_customers, 0, 10)]);
}

/**
 * Create Billing Schedule
 */
function bntm_ajax_qb_create_billing_schedule() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $schedules_table = $wpdb->prefix . 'qb_billing_schedules';
    $business_id = get_current_user_id();
    
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $schedule_type = sanitize_text_field($_POST['schedule_type'] ?? 'interval');
    $interval_value = intval($_POST['interval_value'] ?? 0);
    $interval_unit = sanitize_text_field($_POST['interval_unit'] ?? 'months');
    $specific_dates = sanitize_textarea_field($_POST['specific_dates'] ?? '');
    $payment_plan_json = $_POST['payment_plan_json'] ?? '';
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date = sanitize_text_field($_POST['end_date'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if (empty($customer_name) || empty($start_date) || empty($payment_plan_json) || $total_amount <= 0) {
        wp_send_json_error(['message' => 'Required fields are missing or invalid']);
    }
    
    // Decode and re-encode to ensure clean JSON
    $payment_plan = json_decode(stripslashes($payment_plan_json), true);
    
    if (!$payment_plan) {
        wp_send_json_error(['message' => 'Invalid payment plan data']);
    }
    
    // Determine total invoices and amount based on plan type
    $total_invoices = 0;
    $amount_per_invoice = 0;
    
    if ($payment_plan['type'] === 'divided') {
        $total_invoices = intval($payment_plan['installments']);
        $amount_per_invoice = floatval($payment_plan['amount_per_invoice']);
    } elseif ($payment_plan['type'] === 'full') {
        $total_invoices = intval($payment_plan['total_invoices']) ?: 0;
        $amount_per_invoice = floatval($payment_plan['amount_per_invoice']);
    } elseif ($payment_plan['type'] === 'percentage') {
        $total_invoices = count($payment_plan['percentage_array']);
        $amount_per_invoice = 0; // Will be calculated per invoice
    }

    // Calculate next invoice date
    if ($schedule_type === 'interval') {
        $next_invoice_date = $start_date;
    } else {
        $dates = array_map('trim', explode(',', $specific_dates));
        $next_invoice_date = !empty($dates) ? $dates[0] : $start_date;
    }

    // Store as clean JSON
    $payment_plan_clean = wp_json_encode($payment_plan);

    $result = $wpdb->insert($schedules_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'quotation_id' => $quotation_id ?: null,
        'schedule_type' => $schedule_type,
        'interval_value' => $interval_value ?: null,
        'interval_unit' => $interval_unit ?: null,
        'specific_dates' => $specific_dates ?: null,
        'payment_plan' => $payment_plan_clean,
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
        wp_send_json_error(['message' => 'Failed to create billing schedule: ' . $wpdb->last_error]);
    }
}

/**
 * Generate Scheduled Invoice
 */
function bntm_ajax_qb_generate_scheduled_invoice() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    
    $result = qb_generate_invoice_from_schedule($schedule_id);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
}
/**
 * Create Quotation
 */
function bntm_ajax_qb_create_quotation() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $items_table = $wpdb->prefix . 'qb_quotation_items';
    $business_id = get_current_user_id();
    
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_company = sanitize_text_field($_POST['customer_company'] ?? '');
    $customer_address = sanitize_textarea_field($_POST['customer_address'] ?? '');
    $title = sanitize_text_field($_POST['title'] ?? '');
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $discount = floatval($_POST['discount'] ?? 0);
    $valid_until = sanitize_text_field($_POST['valid_until'] ?? '');
    $terms_conditions = sanitize_textarea_field($_POST['terms_conditions'] ?? '');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $quotation_type = sanitize_text_field($_POST['quotation_type'] ?? 'manual');
    
    if (empty($title) || empty($customer_name)) {
        wp_send_json_error(['message' => 'Title and customer name are required']);
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
    
    $tax_rate = floatval(bntm_get_setting('qb_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax - $discount;
    
    // Set default valid_until if empty
    if (empty($valid_until)) {
        $validity_days = intval(bntm_get_setting('qb_quotation_validity', '30'));
        $valid_until = date('Y-m-d', strtotime("+$validity_days days"));
    }

    // Insert quotation
    $result = $wpdb->insert($quotations_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $customer_phone,
        'customer_company' => $customer_company,
        'customer_address' => $customer_address,
        'quotation_number' => $quotation_number,
        'title' => $title,
        'description' => $description,
        'amount' => $amount,
        'tax' => $tax,
        'discount' => $discount,
        'total' => $total,
        'currency' => bntm_get_setting('qb_currency', 'PHP'),
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

/**
 * Update Quotation
 */
function bntm_ajax_qb_update_quotation() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $items_table = $wpdb->prefix . 'qb_quotation_items';
    $business_id = get_current_user_id();
    
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
    $customer_company = sanitize_text_field($_POST['customer_company'] ?? '');
    $customer_address = sanitize_textarea_field($_POST['customer_address'] ?? '');
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
    
    $tax_rate = floatval(bntm_get_setting('qb_tax_rate', '0'));
    $tax = $amount * ($tax_rate / 100);
    $total = $amount + $tax - $discount;
    
    // Update quotation
    $result = $wpdb->update(
        $quotations_table,
        [
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'customer_company' => $customer_company,
            'customer_address' => $customer_address,
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
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s'],
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

/**
 * Delete Quotation
 */
function bntm_ajax_qb_delete_quotation() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
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

/**
 * Get Quotation
 */
function bntm_ajax_qb_get_quotation() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $items_table = $wpdb->prefix . 'qb_quotation_items';
    $business_id = get_current_user_id();
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    
    $quotation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $quotations_table WHERE id = %d",
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

/**
 * Get Quotation Items
 */
function bntm_ajax_qb_get_quotation_items() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $items_table = $wpdb->prefix . 'qb_quotation_items';
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $items_table WHERE quotation_id = %d",
        $quotation_id
    ));

    wp_send_json_success(['items' => $items]);
}

/**
 * Change Quotation Status
 */
function bntm_ajax_qb_change_quotation_status() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $business_id = get_current_user_id();
    
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    
    $allowed_statuses = ['draft', 'sent', 'approved', 'rejected', 'billed'];
    
    if (!in_array($status, $allowed_statuses)) {
        wp_send_json_error(['message' => 'Invalid status']);
    }
    
    $result = $wpdb->update(
        $quotations_table,
        ['status' => $status],
        ['id' => $quotation_id, 'business_id' => $business_id],
        ['%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Quotation status updated to ' . ucfirst($status)]);
    } else {
        wp_send_json_error(['message' => 'Failed to update quotation status']);
    }
}

/**
 * Create Quotation Revision
 */
function bntm_ajax_qb_create_quotation_revision() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $items_table = $wpdb->prefix . 'qb_quotation_items';
    $business_id = get_current_user_id();
    
    $quotation_id = intval($_POST['quotation_id'] ?? 0);
    
    // Get original quotation
    $original = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $quotations_table WHERE id = %d ",
        $quotation_id, $business_id
    ));
    
    if (!$original) {
        wp_send_json_error(['message' => 'Original quotation not found']);
    }
    
    // Get original items
    $original_items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $items_table WHERE quotation_id = %d",
        $quotation_id
    ));
    
    // Get current max revision number
    $max_revision = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(CAST(revision_number AS UNSIGNED)) FROM $quotations_table 
         WHERE quotation_number LIKE %s ",
        $wpdb->esc_like(substr($original->quotation_number, 0, strrpos($original->quotation_number, '-'))) . '%',
        $business_id
    ));
    
    $new_revision = ($max_revision ?: 0) + 1;
    
    // Create new quotation number with revision
    $base_number = substr($original->quotation_number, 0, strrpos($original->quotation_number, '-') ?: strlen($original->quotation_number));
    $new_quotation_number = $base_number . '-R' . $new_revision;
    
    // Insert new revision as draft
    $result = $wpdb->insert($quotations_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_name' => $original->customer_name,
        'customer_email' => $original->customer_email,
        'customer_phone' => $original->customer_phone,
        'customer_company' => $original->customer_company,
        'customer_address' => $original->customer_address,
        'quotation_number' => $new_quotation_number,
        'title' => $original->title,
        'description' => $original->description,
        'amount' => $original->amount,
        'tax' => $original->tax,
        'discount' => $original->discount,
        'total' => $original->total,
        'currency' => $original->currency,
        'valid_until' => date('Y-m-d', strtotime('+30 days')),
        'status' => 'draft',
        'notes' => $original->notes,
        'terms_conditions' => $original->terms_conditions,
        'revision_number' => $new_revision,
        'parent_quotation_id' => $quotation_id,
        'created_at' => current_time('mysql')
    ]);

    if ($result) {
        $new_quotation_id = $wpdb->insert_id;
        
        // Copy items
        foreach ($original_items as $item) {
            $wpdb->insert($items_table, [
                'quotation_id' => $new_quotation_id,
                'product_id' => $item->product_id,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total
            ]);
        }
        
        wp_send_json_success(['message' => 'Revision created successfully!', 'new_id' => $new_quotation_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create revision']);
    }
}

/**
 * Save General Settings
 */
add_action('wp_ajax_qb_save_general_settings', 'bntm_ajax_qb_save_general_settings');
function bntm_ajax_qb_save_general_settings() {
    check_ajax_referer('qb_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    bntm_set_setting('qb_quotation_validity', intval($_POST['quotation_validity_days'] ?? 30));
    bntm_set_setting('qb_currency', sanitize_text_field($_POST['currency'] ?? 'PHP'));
    bntm_set_setting('qb_tax_rate', floatval($_POST['tax_rate'] ?? 0));

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}
/**
 * Process Scheduled Invoices (Cron Job)
 */
function qb_process_scheduled_invoices() {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'qb_billing_schedules';
    
    $today = date('Y-m-d');
    
    // Get all active schedules that are due
    $due_schedules = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $schedules_table 
         WHERE status = 'active' 
         AND next_invoice_date <= %s",
        $today
    ));
    
    foreach ($due_schedules as $schedule) {
        qb_generate_invoice_from_schedule($schedule->id);
    }
}

/**
 * Generate Invoice from Schedule
 */
function qb_generate_invoice_from_schedule($schedule_id) {
    global $wpdb;
    $schedules_table = $wpdb->prefix . 'qb_billing_schedules';
    $quotations_table = $wpdb->prefix . 'qb_quotations';
    $quotation_items_table = $wpdb->prefix . 'qb_quotation_items';
    $invoices_table = $wpdb->prefix . 'op_invoices';
    
    $schedule = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $schedules_table WHERE id = %d",
        $schedule_id
    ));
    
    if (!$schedule || $schedule->status !== 'active') {
        return ['success' => false, 'message' => 'Schedule not found or inactive'];
    }
    
    // Check if we've reached the total invoices limit
    if ($schedule->total_invoices > 0 && $schedule->invoices_generated >= $schedule->total_invoices) {
        $wpdb->update($schedules_table, ['status' => 'completed'], ['id' => $schedule_id]);
        return ['success' => false, 'message' => 'Schedule completed - all invoices generated'];
    }
    
    // Check if Payments module invoice table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$invoices_table'") === $invoices_table;
    
    if (!$table_exists) {
        return ['success' => false, 'message' => 'Payments module not available'];
    }
    
    // Get quotation info if exists
    $quotation = null;
    $quotation_items = [];
    
    if ($schedule->quotation_id) {
        $quotation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $quotations_table WHERE id = %d",
            $schedule->quotation_id
        ));
        
        if ($quotation) {
            $quotation_items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $quotation_items_table WHERE quotation_id = %d",
                $schedule->quotation_id
            ));
        }
    }
    
    // Parse payment plan
    $payment_plan = json_decode($schedule->payment_plan, true);
    $current_invoice_number = $schedule->invoices_generated + 1;
    
    // Calculate invoice amount based on payment plan
    $invoice_amount = 0;
    $percentage_info = '';
    
    if ($payment_plan['type'] === 'divided') {
        $invoice_amount = isset($payment_plan['amount_per_invoice']) 
            ? floatval($payment_plan['amount_per_invoice']) 
            : floatval($schedule->amount_per_invoice);
    } elseif ($payment_plan['type'] === 'full') {
        $invoice_amount = isset($payment_plan['amount_per_invoice']) 
            ? floatval($payment_plan['amount_per_invoice']) 
            : floatval($schedule->amount_per_invoice);
    } elseif ($payment_plan['type'] === 'percentage') {
        $index = $current_invoice_number - 1;
        if (isset($payment_plan['amounts'][$index])) {
            $invoice_amount = floatval($payment_plan['amounts'][$index]);
            $percentage = $payment_plan['percentage_array'][$index];
            $percentage_info = " ({$percentage}% of total)";
        }
    }
    
    // Ensure amount is not zero
    if ($invoice_amount <= 0) {
        error_log("QB Schedule #{$schedule_id}: Invalid invoice amount - " . print_r($payment_plan, true));
        return ['success' => false, 'message' => 'Invalid invoice amount calculated'];
    }
    
    // Build description
    $description_lines = [];
    
    if ($quotation) {
        $description_lines[] = "Based on Quotation: " . $quotation->quotation_number;
        $description_lines[] = "Title: " . $quotation->title;
        
        if ($quotation->description) {
            $description_lines[] = "";
            $description_lines[] = "Description:";
            $description_lines[] = $quotation->description;
        }
        
        $description_lines[] = "";
        $description_lines[] = "=== Items/Services ===";
        $description_lines[] = "";
        
        if (!empty($quotation_items)) {
            foreach ($quotation_items as $item) {
                $description_lines[] = "• " . $item->item_name;
                
                if (!empty($item->description)) {
                    $description_lines[] = "  Description: " . $item->description;
                }
                
                $description_lines[] = "  Quantity: " . $item->quantity . " × ₱" . number_format($item->unit_price, 2) . " = ₱" . number_format($item->total, 2);
                $description_lines[] = "";
            }
        }
    } else {
        $description_lines[] = "Billing Schedule Invoice";
        $description_lines[] = "Customer: " . $schedule->customer_name;
        $description_lines[] = "";
    }
    
    $description_lines[] = "=== Payment Info ===";
    if ($schedule->total_invoices > 0) {
        $description_lines[] = "Invoice " . $current_invoice_number . " of " . $schedule->total_invoices . $percentage_info;
    } else {
        $description_lines[] = "Invoice " . $current_invoice_number . $percentage_info;
    }
    
    if ($schedule->notes) {
        $description_lines[] = "";
        $description_lines[] = "Notes: " . $schedule->notes;
    }
    
    $description = implode("\n", $description_lines);
    
    // Calculate tax
    $tax_rate = floatval(bntm_get_setting('qb_tax_rate', '0'));
    $tax = $invoice_amount * ($tax_rate / 100);
    $total = $invoice_amount + $tax;
    
    // Set due date
    $payment_terms = intval(bntm_get_setting('op_payment_terms', '30'));
    $due_date = date('Y-m-d', strtotime("+$payment_terms days"));
    
    // Create invoice
    $result = $wpdb->insert($invoices_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $schedule->business_id,
        'reference_type' => 'qb_schedule',
        'reference_id' => $schedule_id,
        'customer_name' => $schedule->customer_name,
        'customer_email' => $schedule->customer_email ?: '',
        'customer_phone' => $schedule->customer_phone ?: '',
        'customer_address' => '',
        'description' => $description,
        'amount' => $invoice_amount,
        'tax' => $tax,
        'total' => $total,
        'currency' => bntm_get_setting('qb_currency', 'PHP'),
        'status' => 'sent',
        'payment_status' => 'unpaid',
        'due_date' => $due_date,
        'notes' => 'Auto-generated from QBiz billing schedule #' . $schedule->rand_id,
        'created_at' => current_time('mysql')
    ], [
        '%s','%d','%s','%d','%s','%s','%s','%s','%s','%f','%f','%f','%s','%s','%s','%s','%s','%s'
    ]);
    
    if ($result) {
        // Update schedule
        $invoices_generated = $schedule->invoices_generated + 1;
        $next_invoice_date = qb_calculate_next_invoice_date($schedule);
        
        // If no more dates or reached limit, mark as completed
        $new_status = ($next_invoice_date && ($schedule->total_invoices == 0 || $invoices_generated < $schedule->total_invoices)) ? 'active' : 'completed';
        
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

/**
 * Calculate Next Invoice Date
 */
function qb_calculate_next_invoice_date($schedule) {
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