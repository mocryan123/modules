<?php
/**
 * Module Name: CRM
 * Module Slug: crm
 * Description: Complete Customer Relationship Management solution with customers, leads, sales pipeline, and activity tracking
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
 * Returns array of page_title => shortcode
 */
function bntm_crm_get_pages() {
    return [
        'CRM' => '[crm_dashboard]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
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
            deal_value DECIMAL(10,2) DEFAULT 0,
            stage ENUM('new', 'contacted', 'interested', 'quotation_sent', 'negotiation', 'won', 'lost') DEFAULT 'new',
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
            INDEX idx_close_date (expected_close_date)
        ) {$charset};",
        
        'crm_activities' => "CREATE TABLE {$prefix}crm_activities (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED NOT NULL,
            deal_id BIGINT UNSIGNED,
            activity_type ENUM('call', 'email', 'meeting', 'message', 'note', 'purchase', 'other') DEFAULT 'note',
            subject VARCHAR(255),
            description TEXT,
            activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_deal (deal_id),
            INDEX idx_type (activity_type),
            INDEX idx_date (activity_date)
        ) {$charset};",
        
        'crm_tasks' => "CREATE TABLE {$prefix}crm_tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            customer_id BIGINT UNSIGNED,
            deal_id BIGINT UNSIGNED,
            task_title VARCHAR(255) NOT NULL,
            description TEXT,
            due_date DATETIME,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
            assigned_to BIGINT UNSIGNED,
            created_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_customer (customer_id),
            INDEX idx_deal (deal_id),
            INDEX idx_assigned (assigned_to),
            INDEX idx_status (status),
            INDEX idx_due (due_date)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_crm_get_shortcodes() {
    return [
        'crm_dashboard' => 'bntm_crm_shortcode_dashboard'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_crm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_crm_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_crm_add_customer', 'bntm_ajax_crm_add_customer');
add_action('wp_ajax_crm_update_customer', 'bntm_ajax_crm_update_customer');
add_action('wp_ajax_crm_delete_customer', 'bntm_ajax_crm_delete_customer');
add_action('wp_ajax_crm_convert_to_customer', 'bntm_ajax_crm_convert_to_customer');
add_action('wp_ajax_crm_add_deal', 'bntm_ajax_crm_add_deal');
add_action('wp_ajax_crm_update_deal', 'bntm_ajax_crm_update_deal');
add_action('wp_ajax_crm_delete_deal', 'bntm_ajax_crm_delete_deal');
add_action('wp_ajax_crm_add_activity', 'bntm_ajax_crm_add_activity');
add_action('wp_ajax_crm_add_task', 'bntm_ajax_crm_add_task');
add_action('wp_ajax_crm_update_task', 'bntm_ajax_crm_update_task');
add_action('wp_ajax_crm_import_customers', 'bntm_ajax_crm_import_customers');

/* ---------- MAIN CRM SHORTCODE ---------- */
function bntm_crm_shortcode_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the CRM dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-inventory-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Dashboard</a>
            <a href="?tab=customers" class="bntm-tab <?php echo $active_tab === 'customers' ? 'active' : ''; ?>">Customers</a>
            <a href="?tab=leads" class="bntm-tab <?php echo $active_tab === 'leads' ? 'active' : ''; ?>">Leads</a>
            <a href="?tab=pipeline" class="bntm-tab <?php echo $active_tab === 'pipeline' ? 'active' : ''; ?>">Sales Pipeline</a>
            <a href="?tab=activities" class="bntm-tab <?php echo $active_tab === 'activities' ? 'active' : ''; ?>">Activities</a>
            <a href="?tab=tasks" class="bntm-tab <?php echo $active_tab === 'tasks' ? 'active' : ''; ?>">Tasks</a>
            <a href="?tab=communication" class="bntm-tab <?php echo $active_tab === 'communication' ? 'active' : ''; ?>">Communication</a>
            <a href="?tab=reports" class="bntm-tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">Reports</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo crm_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'customers'): ?>
                <?php echo crm_customers_tab($business_id); ?>
            <?php elseif ($active_tab === 'leads'): ?>
                <?php echo crm_leads_tab($business_id); ?>
            <?php elseif ($active_tab === 'pipeline'): ?>
                <?php echo crm_pipeline_tab($business_id); ?>
            <?php elseif ($active_tab === 'activities'): ?>
                <?php echo crm_activities_tab($business_id); ?>
            <?php elseif ($active_tab === 'tasks'): ?>
                <?php echo crm_tasks_tab($business_id); ?>
            <?php elseif ($active_tab === 'communication'): ?>
                <?php echo crm_communication_tab($business_id); ?>
            <?php elseif ($active_tab === 'reports'): ?>
                <?php echo crm_reports_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Customer Relationship Management', $content);
}

/* ---------- TAB FUNCTIONS ---------- */

function crm_overview_tab($business_id) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $deals_table = $wpdb->prefix . 'crm_deals';
    $tasks_table = $wpdb->prefix . 'crm_tasks';
    
    // Get statistics
    $total_customers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $customers_table WHERE type = 'customer'",
        $business_id
    ));
    
    $total_leads = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $customers_table WHERE AND type = 'lead'",
        $business_id
    ));
    
    $new_leads_this_week = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $customers_table 
        WHERE type = 'lead' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
        $business_id
    ));
    
    $active_deals = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $deals_table 
        WHERE  stage NOT IN ('won', 'lost')",
        $business_id
    ));
    
    $deals_won = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $deals_table WHERE  stage = 'won'",
        $business_id
    ));
    
    $deals_lost = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $deals_table WHERE stage = 'lost'",
        $business_id
    ));
    
    $total_deal_value = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(deal_value) FROM $deals_table 
        WHERE stage NOT IN ('lost')",
        $business_id
    ));
    
    $won_deal_value = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(deal_value) FROM $deals_table WHERE stage = 'won'",
        $business_id
    ));
    
    $pending_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table 
        WHERE status = 'pending'",
        $business_id
    ));
    
    $overdue_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table 
        WHERE status = 'pending' AND due_date < NOW()",
        $business_id
    ));
    
    // Recent activities
    $recent_leads = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table 
        WHERE type = 'lead'
        ORDER BY created_at DESC LIMIT 5",
        $business_id
    ));
    
    // Upcoming tasks
    $upcoming_tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, c.name as customer_name 
        FROM $tasks_table t
        LEFT JOIN $customers_table c ON t.customer_id = c.id
        WHERE t.status = 'pending'
        ORDER BY t.due_date ASC LIMIT 5",
        $business_id
    ));
    
    // Deal pipeline summary
    $pipeline_summary = $wpdb->get_results($wpdb->prepare(
        "SELECT stage, COUNT(*) as count, SUM(deal_value) as total_value
        FROM $deals_table 
        WHERE stage NOT IN ('won', 'lost')
        GROUP BY stage",
        $business_id
    ));
    
    $conversion_rate = $total_leads > 0 ? round(($total_customers / ($total_customers + $total_leads)) * 100, 1) : 0;

    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Customers</h3>
            <p class="bntm-stat-number"><?php echo esc_html($total_customers); ?></p>
            <small>Active customer base</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Active Leads</h3>
            <p class="bntm-stat-number" style="color: #3b82f6;"><?php echo esc_html($total_leads); ?></p>
            <small><?php echo esc_html($new_leads_this_week); ?> new this week</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Active Deals</h3>
            <p class="bntm-stat-number" style="color: #8b5cf6;"><?php echo esc_html($active_deals); ?></p>
            <small>In pipeline</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Deals Won</h3>
            <p class="bntm-stat-number" style="color: #059669;">✓ <?php echo esc_html($deals_won); ?></p>
            <small><?php echo esc_html($deals_lost); ?> lost</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Pipeline Value</h3>
            <p class="bntm-stat-number">₱<?php echo number_format($total_deal_value ?: 0, 2); ?></p>
            <small>Active deals value</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Revenue Closed</h3>
            <p class="bntm-stat-number" style="color: #059669;">₱<?php echo number_format($won_deal_value ?: 0, 2); ?></p>
            <small>From won deals</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Pending Tasks</h3>
            <p class="bntm-stat-number" style="color: <?php echo $overdue_tasks > 0 ? '#dc2626' : '#6b7280'; ?>">
                <?php echo esc_html($pending_tasks); ?>
            </p>
            <small><?php echo esc_html($overdue_tasks); ?> overdue</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Conversion Rate</h3>
            <p class="bntm-stat-number"><?php echo $conversion_rate; ?>%</p>
            <small>Lead to customer</small>
        </div>
    </div>

    <?php if (!empty($pipeline_summary)): ?>
    <div class="bntm-form-section">
        <h3>Sales Pipeline Overview</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Number of Deals</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pipeline_summary as $stage): ?>
                    <tr>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $stage->stage))); ?></td>
                        <td><?php echo esc_html($stage->count); ?></td>
                        <td>₱<?php echo number_format($stage->total_value, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="bntm-form-section">
        <h3>Recent Leads</h3>
        <?php if (empty($recent_leads)): ?>
            <p>No recent leads. <a href="?tab=leads">Add your first lead →</a></p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Source</th>
                        <th>Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_leads as $lead): ?>
                        <tr>
                            <td><?php echo esc_html($lead->name); ?></td>
                            <td><?php echo esc_html($lead->contact_number); ?></td>
                            <td><?php echo esc_html($lead->email); ?></td>
                            <td><?php echo esc_html($lead->source ?: 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($lead->created_at)); ?></td>
                            <td>
                                <a href="?tab=leads&view=<?php echo $lead->id; ?>" class="bntm-btn-small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="bntm-form-section">
        <h3>Upcoming Tasks & Follow-ups</h3>
        <?php if (empty($upcoming_tasks)): ?>
            <p>No pending tasks. You're all caught up! ✓</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Customer</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_tasks as $task): ?>
                        <?php
                        $is_overdue = strtotime($task->due_date) < time();
                        $priority_colors = [
                            'low' => '#6b7280',
                            'medium' => '#f59e0b',
                            'high' => '#ef4444',
                            'urgent' => '#991b1b'
                        ];
                        ?>
                        <tr style="<?php echo $is_overdue ? 'background: #fef2f2;' : ''; ?>">
                            <td><?php echo esc_html($task->task_title); ?></td>
                            <td><?php echo esc_html($task->customer_name ?: 'N/A'); ?></td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span style="color: #dc2626; font-weight: 500;">
                                        ⚠️ <?php echo date('M d, Y', strtotime($task->due_date)); ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo date('M d, Y', strtotime($task->due_date)); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php echo $priority_colors[$task->priority]; ?>; font-weight: 500;">
                                    <?php echo esc_html(ucfirst($task->priority)); ?>
                                </span>
                            </td>
                            <td>
                                <a href="?tab=tasks&view=<?php echo $task->id; ?>" class="bntm-btn-small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    .bntm-stat-card small {
        color: #9ca3af;
        font-size: 12px;
    }
    </style>
    <?php
    return ob_get_clean();
}

function crm_customers_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE type = 'customer' ORDER BY created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <button id="open-add-customer-modal" class="bntm-btn-primary">+ Add New Customer</button>
        <button id="open-import-modal" class="bntm-btn-secondary" style="margin-left: 10px;">📥 Import Customers</button>
    </div>

    <!-- Add Customer Modal -->
    <div id="add-customer-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Customer</h3>
            <form id="crm-add-customer-form" class="bntm-form">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="contact_number" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="bntm-form-group">
                        <label>Company</label>
                        <input type="text" name="company">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Address</label>
                    <textarea name="address" rows="2"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Birthday</label>
                        <input type="date" name="birthday">
                    </div>
                    <div class="bntm-form-group">
                        <label>Anniversary</label>
                        <input type="date" name="anniversary">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Source</label>
                        <select name="source">
                            <option value="">Select Source</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Referral">Referral</option>
                            <option value="Website">Website</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Tags (comma-separated)</label>
                        <input type="text" name="tags" placeholder="VIP, Regular, etc.">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Customer</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="import-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Import Customers</h3>
            <p>Upload CSV or Excel file with columns: Name, Contact, Email, Company, Address</p>
            
            <div class="bntm-upload-area" id="import-upload-area">
                <input type="file" id="import-file" accept=".csv,.xlsx,.xls" style="display: none;">
                <button type="button" class="bntm-btn bntm-btn-secondary" id="import-upload-btn">
                    Choose File
                </button>
                <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                <small>Supported: CSV, XLSX, XLS</small>
            </div>
            
            <div id="import-preview" style="display: none; margin-top: 20px;">
                <h4>Preview (First 5 rows)</h4>
                <div id="preview-content"></div>
                <button id="confirm-import-btn" class="bntm-btn-primary" style="margin-top: 15px;">Import Data</button>
            </div>
            
            <button class="close-modal bntm-btn-secondary" style="margin-top: 15px;">Close</button>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>All Customers (<?php echo count($customers); ?>)</h3>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="customer-search" placeholder="Search customers..." style="padding: 8px; width: 300px; border: 1px solid #d1d5db; border-radius: 6px;">
        </div>
        
        <?php if (empty($customers)): ?>
            <p>No customers found. Add your first customer above.</p>
        <?php else: ?>
            <table class="bntm-table" id="customers-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Source</th>
                        <th>Tags</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr data-search="<?php echo esc_attr(strtolower($customer->name . ' ' . $customer->contact_number . ' ' . $customer->email . ' ' . $customer->company)); ?>">
                            <td><strong><?php echo esc_html($customer->name); ?></strong></td>
                            <td><?php echo esc_html($customer->contact_number); ?></td>
                            <td><?php echo esc_html($customer->email); ?></td>
                            <td><?php echo esc_html($customer->company); ?></td>
                            <td><?php echo esc_html($customer->source ?: 'N/A'); ?></td>
                            <td><?php if ($customer->tags): ?>
                                    <?php 
                                    $tags = explode(',', $customer->tags);
                                    foreach ($tags as $tag): 
                                        $tag = trim($tag);
                                    ?>
                                        <span style="background: #e0f2fe; color: #0c4a6e; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px;">
                                            <?php echo esc_html($tag); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($customer->created_at)); ?></td>
                            <td>
                                <a href="?tab=customers&view=<?php echo $customer->id; ?>" class="bntm-btn-small">View</a>
                                <button class="bntm-btn-small crm-edit-customer" 
                                    data-id="<?php echo $customer->id; ?>"
                                    data-name="<?php echo esc_attr($customer->name); ?>"
                                    data-contact="<?php echo esc_attr($customer->contact_number); ?>"
                                    data-email="<?php echo esc_attr($customer->email); ?>"
                                    data-company="<?php echo esc_attr($customer->company); ?>"
                                    data-address="<?php echo esc_attr($customer->address); ?>"
                                    data-birthday="<?php echo esc_attr($customer->birthday); ?>"
                                    data-anniversary="<?php echo esc_attr($customer->anniversary); ?>"
                                    data-source="<?php echo esc_attr($customer->source); ?>"
                                    data-tags="<?php echo esc_attr($customer->tags); ?>"
                                    data-notes="<?php echo esc_attr($customer->notes); ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger crm-delete-customer" data-id="<?php echo $customer->id; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Edit Customer Modal -->
    <div id="edit-customer-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Customer</h3>
            <form id="crm-edit-customer-form" class="bntm-form">
                <input type="hidden" id="edit-customer-id" name="customer_id">
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Contact Number *</label>
                        <input type="text" id="edit-contact" name="contact_number" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Email</label>
                        <input type="email" id="edit-email" name="email">
                    </div>
                    <div class="bntm-form-group">
                        <label>Company</label>
                        <input type="text" id="edit-company" name="company">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Address</label>
                    <textarea id="edit-address" name="address" rows="2"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Birthday</label>
                        <input type="date" id="edit-birthday" name="birthday">
                    </div>
                    <div class="bntm-form-group">
                        <label>Anniversary</label>
                        <input type="date" id="edit-anniversary" name="anniversary">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Source</label>
                        <select id="edit-source" name="source">
                            <option value="">Select Source</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Referral">Referral</option>
                            <option value="Website">Website</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Tags (comma-separated)</label>
                        <input type="text" id="edit-tags" name="tags" placeholder="VIP, Regular, etc.">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea id="edit-notes" name="notes" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Customer</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
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
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .bntm-btn-secondary:hover {
        background: #4b5563;
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
    </style>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-customer-modal');
        const editModal = document.getElementById('edit-customer-modal');
        const importModal = document.getElementById('import-modal');
        
        document.getElementById('open-add-customer-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.getElementById('open-import-modal').addEventListener('click', () => {
            importModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== SEARCH FUNCTIONALITY ==========
        document.getElementById('customer-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#customers-table tbody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // ========== ADD CUSTOMER FORM ==========
        document.getElementById('crm-add-customer-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_add_customer');
            formData.append('nonce', nonce);
            formData.append('type', 'customer');
            
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
                    btn.textContent = 'Add Customer';
                }
            });
        });
        
        // ========== EDIT CUSTOMER ==========
        document.querySelectorAll('.crm-edit-customer').forEach(btn => {
            btn.addEventListener('click', function() {
                const data = this.dataset;
                
                document.getElementById('edit-customer-id').value = data.id;
                document.getElementById('edit-name').value = data.name;
                document.getElementById('edit-contact').value = data.contact;
                document.getElementById('edit-email').value = data.email;
                document.getElementById('edit-company').value = data.company;
                document.getElementById('edit-address').value = data.address;
                document.getElementById('edit-birthday').value = data.birthday;
                document.getElementById('edit-anniversary').value = data.anniversary;
                document.getElementById('edit-source').value = data.source;
                document.getElementById('edit-tags').value = data.tags;
                document.getElementById('edit-notes').value = data.notes;
                
                editModal.style.display = 'flex';
            });
        });
        
        document.getElementById('crm-edit-customer-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_update_customer');
            formData.append('nonce', nonce);
            
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
                    btn.textContent = 'Update Customer';
                }
            });
        });
        
        // ========== DELETE CUSTOMER ==========
        document.querySelectorAll('.crm-delete-customer').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this customer?')) return;
                
                const customerId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'crm_delete_customer');
                formData.append('customer_id', customerId);
                formData.append('nonce', nonce);
                
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
        
        // ========== IMPORT FUNCTIONALITY ==========
        let importData = [];
        
        const importUploadBtn = document.getElementById('import-upload-btn');
        const importFile = document.getElementById('import-file');
        const importUploadArea = document.getElementById('import-upload-area');
        
        importUploadBtn.addEventListener('click', () => importFile.click());
        
        importFile.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                handleImportFile(this.files[0]);
            }
        });
        
        importUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            importUploadArea.classList.add('dragover');
        });
        
        importUploadArea.addEventListener('dragleave', () => {
            importUploadArea.classList.remove('dragover');
        });
        
        importUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            importUploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleImportFile(e.dataTransfer.files[0]);
            }
        });
        
        function handleImportFile(file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const content = e.target.result;
                
                // Simple CSV parsing
                const lines = content.split('\n');
                const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
                
                importData = [];
                for (let i = 1; i < Math.min(lines.length, 6); i++) {
                    if (lines[i].trim()) {
                        const values = lines[i].split(',');
                        const row = {};
                        headers.forEach((header, index) => {
                            row[header] = values[index] ? values[index].trim() : '';
                        });
                        importData.push(row);
                    }
                }
                
                // Show preview
                let previewHTML = '<table class="bntm-table"><thead><tr>';
                headers.forEach(h => {
                    previewHTML += `<th>${h}</th>`;
                });
                previewHTML += '</tr></thead><tbody>';
                
                importData.forEach(row => {
                    previewHTML += '<tr>';
                    headers.forEach(h => {
                        previewHTML += `<td>${row[h] || ''}</td>`;
                    });
                    previewHTML += '</tr>';
                });
                previewHTML += '</tbody></table>';
                
                document.getElementById('preview-content').innerHTML = previewHTML;
                document.getElementById('import-preview').style.display = 'block';
            };
            
            reader.readAsText(file);
        }
        
        document.getElementById('confirm-import-btn').addEventListener('click', function() {
            if (importData.length === 0) {
                alert('No data to import');
                return;
            }
            
            this.disabled = true;
            this.textContent = 'Importing...';
            
            const formData = new FormData();
            formData.append('action', 'crm_import_customers');
            formData.append('nonce', nonce);
            formData.append('data', JSON.stringify(importData));
            
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
                    this.disabled = false;
                    this.textContent = 'Import Data';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function crm_leads_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    
    $leads = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE type = 'lead' ORDER BY created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <button id="open-add-lead-modal" class="bntm-btn-primary">+ Add New Lead</button>
    </div>

    <!-- Add Lead Modal -->
    <div id="add-lead-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Lead</h3>
            <form id="crm-add-lead-form" class="bntm-form">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="contact_number" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="bntm-form-group">
                        <label>Company</label>
                        <input type="text" name="company">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Lead Source *</label>
                        <select name="source" required>
                            <option value="">Select Source</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Referral">Referral</option>
                            <option value="Website">Website</option>
                            <option value="Phone Call">Phone Call</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Tags (comma-separated)</label>
                        <input type="text" name="tags" placeholder="Hot Lead, etc.">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="Initial conversation notes..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Lead</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>All Leads (<?php echo count($leads); ?>)</h3>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="lead-search" placeholder="Search leads..." style="padding: 8px; width: 300px; border: 1px solid #d1d5db; border-radius: 6px;">
        </div>
        
        <?php if (empty($leads)): ?>
            <p>No leads found. Add your first lead above.</p>
        <?php else: ?>
            <table class="bntm-table" id="leads-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Company</th>
                        <th>Source</th>
                        <th>Tags</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                        <tr data-search="<?php echo esc_attr(strtolower($lead->name . ' ' . $lead->contact_number . ' ' . $lead->email . ' ' . $lead->company)); ?>">
                            <td><strong><?php echo esc_html($lead->name); ?></strong></td>
                            <td>
                                <?php echo esc_html($lead->contact_number); ?>
                                <?php if ($lead->contact_number): ?>
                                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $lead->contact_number); ?>" 
                                       target="_blank" 
                                       style="color: #25D366; text-decoration: none; margin-left: 5px;" 
                                       title="WhatsApp">📱</a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($lead->email); ?></td>
                            <td><?php echo esc_html($lead->company); ?></td>
                            <td>
                                <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 11px;">
                                    <?php echo esc_html($lead->source ?: 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($lead->tags): ?>
                                    <?php 
                                    $tags = explode(',', $lead->tags);
                                    foreach ($tags as $tag): 
                                        $tag = trim($tag);
                                    ?>
                                        <span style="background: #e0f2fe; color: #0c4a6e; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-right: 4px;">
                                            <?php echo esc_html($tag); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($lead->created_at)); ?></td>
                            <td>
                                <button class="bntm-btn-small" style="background: #059669;" 
                                    onclick="convertToCustomer(<?php echo $lead->id; ?>)">Convert</button>
                                <button class="bntm-btn-small crm-edit-lead" 
                                    data-id="<?php echo $lead->id; ?>"
                                    data-name="<?php echo esc_attr($lead->name); ?>"
                                    data-contact="<?php echo esc_attr($lead->contact_number); ?>"
                                    data-email="<?php echo esc_attr($lead->email); ?>"
                                    data-company="<?php echo esc_attr($lead->company); ?>"
                                    data-source="<?php echo esc_attr($lead->source); ?>"
                                    data-tags="<?php echo esc_attr($lead->tags); ?>"
                                    data-notes="<?php echo esc_attr($lead->notes); ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger crm-delete-lead" data-id="<?php echo $lead->id; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Edit Lead Modal -->
    <div id="edit-lead-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Lead</h3>
            <form id="crm-edit-lead-form" class="bntm-form">
                <input type="hidden" id="edit-lead-id" name="customer_id">
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Full Name *</label>
                        <input type="text" id="edit-lead-name" name="name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Contact Number *</label>
                        <input type="text" id="edit-lead-contact" name="contact_number" required>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Email</label>
                        <input type="email" id="edit-lead-email" name="email">
                    </div>
                    <div class="bntm-form-group">
                        <label>Company</label>
                        <input type="text" id="edit-lead-company" name="company">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Lead Source</label>
                        <select id="edit-lead-source" name="source">
                            <option value="">Select Source</option>
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Walk-in">Walk-in</option>
                            <option value="Referral">Referral</option>
                            <option value="Website">Website</option>
                            <option value="Phone Call">Phone Call</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Tags (comma-separated)</label>
                        <input type="text" id="edit-lead-tags" name="tags">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea id="edit-lead-notes" name="notes" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Lead</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
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
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .bntm-btn-secondary:hover {
        background: #4b5563;
    }
    </style>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-lead-modal');
        const editModal = document.getElementById('edit-lead-modal');
        
        document.getElementById('open-add-lead-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== SEARCH FUNCTIONALITY ==========
        document.getElementById('lead-search').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#leads-table tbody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // ========== ADD LEAD FORM ==========
        document.getElementById('crm-add-lead-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_add_customer');
            formData.append('nonce', nonce);
            formData.append('type', 'lead');
            
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
                    btn.textContent = 'Add Lead';
                }
            });
        });
        
        // ========== EDIT LEAD ==========
        document.querySelectorAll('.crm-edit-lead').forEach(btn => {
            btn.addEventListener('click', function() {
                const data = this.dataset;
                
                document.getElementById('edit-lead-id').value = data.id;
                document.getElementById('edit-lead-name').value = data.name;
                document.getElementById('edit-lead-contact').value = data.contact;
                document.getElementById('edit-lead-email').value = data.email;
                document.getElementById('edit-lead-company').value = data.company;
                document.getElementById('edit-lead-source').value = data.source;
                document.getElementById('edit-lead-tags').value = data.tags;
                document.getElementById('edit-lead-notes').value = data.notes;
                
                editModal.style.display = 'flex';
            });
        });
        
        document.getElementById('crm-edit-lead-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_update_customer');
            formData.append('nonce', nonce);
            
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
                    btn.textContent = 'Update Lead';
                }
            });
        });
        
        // ========== DELETE LEAD ==========
        document.querySelectorAll('.crm-delete-lead').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this lead?')) return;
                
                const leadId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'crm_delete_customer');
                formData.append('customer_id', leadId);
                formData.append('nonce', nonce);
                
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
    
    // ========== CONVERT TO CUSTOMER ==========
    function convertToCustomer(leadId) {
        if (!confirm('Convert this lead to a customer?')) return;
        
        const formData = new FormData();
        formData.append('action', 'crm_convert_to_customer');
        formData.append('customer_id', leadId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
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
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
}
function crm_pipeline_tab($business_id) {
    global $wpdb;
    $deals_table = $wpdb->prefix . 'crm_deals';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $products_table = $wpdb->prefix . 'in_products';
    
    // Check if inventory module is enabled
    $has_inventory = bntm_is_module_enabled('in') && $wpdb->get_var("SHOW TABLES LIKE '{$products_table}'") == $products_table;
    
    $deals = $wpdb->get_results($wpdb->prepare(
        "SELECT d.*, c.name as customer_name, c.contact_number, c.email
        FROM $deals_table d
        LEFT JOIN $customers_table c ON d.customer_id = c.id
        ORDER BY d.created_at DESC",
        $business_id
    ));
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table ORDER BY name ASC",
        $business_id
    ));
    
    $products = [];
    if ($has_inventory) {
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $products_table ORDER BY name ASC",
            $business_id
        ));
    }
    
    $nonce = wp_create_nonce('crm_nonce');
    
    // Group deals by stage
    $stages = [
        'new' => [],
        'contacted' => [],
        'interested' => [],
        'quotation_sent' => [],
        'negotiation' => [],
        'won' => [],
        'lost' => []
    ];
    
    foreach ($deals as $deal) {
        $stages[$deal->stage][] = $deal;
    }

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <button id="open-add-deal-modal" class="bntm-btn-primary">+ Add New Deal</button>
        <button id="toggle-view" class="bntm-btn-secondary" style="margin-left: 10px;">Switch to Table View</button>
    </div>

    <!-- Add Deal Modal -->
    <div id="add-deal-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Deal</h3>
            <form id="crm-add-deal-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Customer/Lead *</label>
                    <select name="customer_id" required>
                        <option value="">Select Customer/Lead</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer->id; ?>">
                                <?php echo esc_html($customer->name); ?> 
                                (<?php echo $customer->type === 'lead' ? 'Lead' : 'Customer'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Deal Name *</label>
                        <input type="text" name="deal_name" required placeholder="e.g., Website Development">
                    </div>
                    <div class="bntm-form-group">
                        <label>Deal Value (₱) *</label>
                        <input type="number" name="deal_value" step="0.01" required min="0">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Stage *</label>
                        <select name="stage" required>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="interested">Interested</option>
                            <option value="quotation_sent">Quotation Sent</option>
                            <option value="negotiation">Negotiation</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Probability (%)</label>
                        <input type="number" name="probability" min="0" max="100" value="50">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Expected Close Date</label>
                    <input type="date" name="expected_close_date">
                </div>

                <?php if ($has_inventory && !empty($products)): ?>
                <div class="bntm-form-group">
                    <label>Products/Services</label>
                    <select name="products[]" multiple size="5">
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product->id; ?>">
                                <?php echo esc_html($product->name); ?> - ₱<?php echo number_format($product->selling_price, 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Hold Ctrl/Cmd to select multiple</small>
                </div>
                <?php endif; ?>

                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="Deal details, requirements, etc."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Deal</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Deal Modal -->
    <div id="view-deal-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Deal Details</h3>
            <div id="view-deal-content"></div>
            <button class="close-modal bntm-btn-secondary" style="margin-top: 20px;">Close</button>
        </div>
    </div>

    <!-- Kanban View -->
    <div id="kanban-view" class="bntm-form-section">
        <h3>Sales Pipeline - Kanban View</h3>
        <div class="crm-kanban-board">
            <?php 
            $stage_labels = [
                'new' => 'New',
                'contacted' => 'Contacted',
                'interested' => 'Interested',
                'quotation_sent' => 'Quotation Sent',
                'negotiation' => 'Negotiation',
                'won' => 'Won',
                'lost' => 'Lost'
            ];
            
            $stage_colors = [
                'new' => '#6b7280',
                'contacted' => '#3b82f6',
                'interested' => '#8b5cf6',
                'quotation_sent' => '#f59e0b',
                'negotiation' => '#ef4444',
                'won' => '#059669',
                'lost' => '#991b1b'
            ];
            
            foreach ($stages as $stage_key => $stage_deals):
                $total_value = array_sum(array_column($stage_deals, 'deal_value'));
            ?>
            <div class="crm-kanban-column" data-stage="<?php echo $stage_key; ?>">
                <div class="crm-kanban-header" style="background: <?php echo $stage_colors[$stage_key]; ?>;">
                    <h4><?php echo $stage_labels[$stage_key]; ?></h4>
                    <div class="crm-kanban-meta">
                        <span><?php echo count($stage_deals); ?> deals</span>
                        <span>₱<?php echo number_format($total_value, 2); ?></span>
                    </div>
                </div>
                <div class="crm-kanban-cards" data-stage="<?php echo $stage_key; ?>">
                    <?php if (empty($stage_deals)): ?>
                        <div class="crm-empty-stage">No deals in this stage</div>
                    <?php else: ?>
                        <?php foreach ($stage_deals as $deal): ?>
                            <div class="crm-kanban-card" 
                                 draggable="true" 
                                 data-deal-id="<?php echo $deal->id; ?>"
                                 data-customer-id="<?php echo $deal->customer_id; ?>"
                                 data-deal-name="<?php echo esc_attr($deal->deal_name); ?>"
                                 data-deal-value="<?php echo $deal->deal_value; ?>"
                                 data-current-stage="<?php echo $deal->stage; ?>"
                                 data-probability="<?php echo $deal->probability; ?>"
                                 data-expected-date="<?php echo $deal->expected_close_date; ?>"
                                 data-products="<?php echo esc_attr($deal->products); ?>"
                                 data-notes="<?php echo esc_attr($deal->notes); ?>"
                                 data-customer-name="<?php echo esc_attr($deal->customer_name); ?>"
                                 data-contact="<?php echo esc_attr($deal->contact_number); ?>"
                                 data-email="<?php echo esc_attr($deal->email); ?>">
                                <h5><?php echo esc_html($deal->deal_name); ?></h5>
                                <p class="crm-card-customer"><?php echo esc_html($deal->customer_name); ?></p>
                                <div class="crm-card-value">₱<?php echo number_format($deal->deal_value, 2); ?></div>
                                <?php if ($deal->probability): ?>
                                    <div class="crm-card-probability"><?php echo $deal->probability; ?>% probability</div>
                                <?php endif; ?>
                                <?php if ($deal->expected_close_date): ?>
                                    <div class="crm-card-date">
                                        📅 <?php echo date('M d, Y', strtotime($deal->expected_close_date)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="crm-card-actions" onclick="event.stopPropagation();">
                                    <button class="bntm-btn-small crm-view-deal" data-deal-id="<?php echo $deal->id; ?>">View</button>
                                    <button class="bntm-btn-small crm-edit-deal" 
                                        data-id="<?php echo $deal->id; ?>"
                                        data-customer="<?php echo $deal->customer_id; ?>"
                                        data-name="<?php echo esc_attr($deal->deal_name); ?>"
                                        data-value="<?php echo $deal->deal_value; ?>"
                                        data-stage="<?php echo $deal->stage; ?>"
                                        data-probability="<?php echo $deal->probability; ?>"
                                        data-date="<?php echo $deal->expected_close_date; ?>"
                                        data-products="<?php echo esc_attr($deal->products); ?>"
                                        data-notes="<?php echo esc_attr($deal->notes); ?>">Edit</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Table View -->
    <div id="table-view" class="bntm-form-section" style="display: none;">
        <h3>Sales Pipeline - Table View</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Deal Name</th>
                    <th>Customer</th>
                    <th>Value</th>
                    <th>Stage</th>
                    <th>Probability</th>
                    <th>Expected Close</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($deals)): ?>
                    <tr><td colspan="7" style="text-align: center;">No deals found</td></tr>
                <?php else: ?>
                    <?php foreach ($deals as $deal): ?>
                        <tr>
                            <td><strong><?php echo esc_html($deal->deal_name); ?></strong></td>
                            <td><?php echo esc_html($deal->customer_name); ?></td>
                            <td>₱<?php echo number_format($deal->deal_value, 2); ?></td>
                            <td>
                                <span style="background: <?php echo $stage_colors[$deal->stage]; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                    <?php echo $stage_labels[$deal->stage]; ?>
                                </span>
                            </td>
                            <td><?php echo $deal->probability; ?>%</td>
                            <td><?php echo $deal->expected_close_date ? date('M d, Y', strtotime($deal->expected_close_date)) : 'N/A'; ?></td>
                            <td>
                                <button class="bntm-btn-small crm-view-deal-table" 
                                    data-id="<?php echo $deal->id; ?>"
                                    data-name="<?php echo esc_attr($deal->deal_name); ?>"
                                    data-value="<?php echo $deal->deal_value; ?>"
                                    data-stage="<?php echo $deal->stage; ?>"
                                    data-probability="<?php echo $deal->probability; ?>"
                                    data-date="<?php echo $deal->expected_close_date; ?>"
                                    data-notes="<?php echo esc_attr($deal->notes); ?>"
                                    data-customer-name="<?php echo esc_attr($deal->customer_name); ?>"
                                    data-contact="<?php echo esc_attr($deal->contact_number); ?>"
                                    data-email="<?php echo esc_attr($deal->email); ?>">View</button>
                                <button class="bntm-btn-small crm-edit-deal" 
                                    data-id="<?php echo $deal->id; ?>"
                                    data-customer="<?php echo $deal->customer_id; ?>"
                                    data-name="<?php echo esc_attr($deal->deal_name); ?>"
                                    data-value="<?php echo $deal->deal_value; ?>"
                                    data-stage="<?php echo $deal->stage; ?>"
                                    data-probability="<?php echo $deal->probability; ?>"
                                    data-date="<?php echo $deal->expected_close_date; ?>"
                                    data-products="<?php echo esc_attr($deal->products); ?>"
                                    data-notes="<?php echo esc_attr($deal->notes); ?>">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger crm-delete-deal" data-id="<?php echo $deal->id; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Deal Modal -->
    <div id="edit-deal-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Deal</h3>
            <form id="crm-edit-deal-form" class="bntm-form">
                <input type="hidden" id="edit-deal-id" name="deal_id">
                
                <div class="bntm-form-group">
                    <label>Customer/Lead *</label>
                    <select id="edit-deal-customer" name="customer_id" required>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer->id; ?>">
                                <?php echo esc_html($customer->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Deal Name *</label>
                        <input type="text" id="edit-deal-name" name="deal_name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Deal Value (₱) *</label>
                        <input type="number" id="edit-deal-value" name="deal_value" step="0.01" required min="0">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Stage *</label>
                        <select id="edit-deal-stage" name="stage" required>
                            <option value="new">New</option>
                            <option value="contacted">Contacted</option>
                            <option value="interested">Interested</option>
                            <option value="quotation_sent">Quotation Sent</option>
                            <option value="negotiation">Negotiation</option>
                            <option value="won">Won</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Probability (%)</label>
                        <input type="number" id="edit-deal-probability" name="probability" min="0" max="100">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Expected Close Date</label>
                    <input type="date" id="edit-deal-date" name="expected_close_date">
                </div>

                <?php if ($has_inventory && !empty($products)): ?>
                <div class="bntm-form-group">
                    <label>Products/Services</label>
                    <select id="edit-deal-products" name="products[]" multiple size="5">
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product->id; ?>">
                                <?php echo esc_html($product->name); ?> - ₱<?php echo number_format($product->selling_price, 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea id="edit-deal-notes" name="notes" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Deal</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
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
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .crm-kanban-board {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 20px;
        overflow-x: auto;
        padding-bottom: 20px;
    }
    .crm-kanban-column {
        background: #f9fafb;
        border-radius: 8px;
        overflow: hidden;
        min-width: 250px;
    }
    .crm-kanban-header {
        padding: 15px;
        color: white;
    }
    .crm-kanban-header h4 {
        margin: 0 0 8px 0;
        font-size: 14px;
        font-weight: 600;
    }
    .crm-kanban-meta {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        opacity: 0.9;
    }
    .crm-kanban-cards {
        padding: 10px;
        min-height: 200px;
    }
    .crm-kanban-cards.drag-over {
        background: #e0f2fe;
    }
    .crm-kanban-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        cursor: grab;
        transition: all 0.2s;
    }
    .crm-kanban-card:active {
        cursor: grabbing;
    }
    .crm-kanban-card.dragging {
        opacity: 0.5;
        transform: rotate(2deg);
    }
    .crm-kanban-card:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .crm-kanban-card h5 {
        margin: 0 0 8px 0;
        font-size: 14px;
        color: #1f2937;
    }
    .crm-card-customer {
        font-size: 12px;
        color: #6b7280;
        margin: 0 0 8px 0;
    }
    .crm-card-value {
        font-size: 16px;
        font-weight: 700;
        color: #059669;
        margin-bottom: 8px;
    }
    .crm-card-probability {
        font-size: 11px;
        color: #8b5cf6;
        margin-bottom: 4px;
    }
    .crm-card-date {
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 8px;
    }
    .crm-card-actions {
        display: flex;
        gap: 5px;
        margin-top: 10px;
    }
    .crm-card-actions button {
        cursor: pointer;
    }
    .crm-empty-stage {
        text-align: center;
        color: #9ca3af;
        padding: 20px;
        font-size: 13px;
    }
    </style>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== VIEW TOGGLE ==========
        const toggleBtn = document.getElementById('toggle-view');
        const kanbanView = document.getElementById('kanban-view');
        const tableView = document.getElementById('table-view');
        let isKanban = true;
        
        toggleBtn.addEventListener('click', () => {
            isKanban = !isKanban;
            if (isKanban) {
                kanbanView.style.display = 'block';
                tableView.style.display = 'none';
                toggleBtn.textContent = 'Switch to Table View';
            } else {
                kanbanView.style.display = 'none';
                tableView.style.display = 'block';
                toggleBtn.textContent = 'Switch to Kanban View';
            }
        });
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-deal-modal');
        const editModal = document.getElementById('edit-deal-modal');
        const viewModal = document.getElementById('view-deal-modal');
        
        document.getElementById('open-add-deal-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        
        // ========== VIEW DEAL ==========
        function showDealDetails(data) {
            const stageLabels = {
                'new': 'New',
                'contacted': 'Contacted',
                'interested': 'Interested',
                'quotation_sent': 'Quotation Sent',
                'negotiation': 'Negotiation',
                'won': 'Won',
                'lost': 'Lost'
            };
            
            const dealName = data.dealName || data.name || 'N/A';
            const dealValue = data.dealValue || data.value || '0';
            const currentStage = data.currentStage || data.stage || 'new';
            const probability = data.probability || '0';
            const expectedDate = data.expectedDate || data.date || '';
            const notes = data.notes || '';
            const customerName = data.customerName || 'N/A';
            const contact = data.contact || '';
            const email = data.email || '';
            
            const content = `
                <div style="background: #f9fafb; padding: 20px; border-radius: 6px;">
                    <h4 style="margin: 0 0 15px 0; color: #1f2937;">${dealName}</h4>
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                    <p><strong>Customer:</strong> ${customerName}</p>
                    ${contact ? `<p><strong>Contact:</strong> ${contact}</p>` : ''}
                    ${email ? `<p><strong>Email:</strong> ${email}</p>` : ''}
                    <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                    <p><strong>Deal Value:</strong> ₱${parseFloat(dealValue).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                    <p><strong>Stage:</strong> ${stageLabels[currentStage] || currentStage}</p>
                    <p><strong>Probability:</strong> ${probability}%</p>
                    ${expectedDate ? `<p><strong>Expected Close:</strong> ${new Date(expectedDate).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</p>` : ''}
                    ${notes ? `<hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;"><p><strong>Notes:</strong><br>${notes}</p>` : ''}
                </div>
            `;
            
            document.getElementById('view-deal-content').innerHTML = content;
            viewModal.style.display = 'flex';
        }
        
         // View from Kanban
        document.querySelectorAll('.crm-view-deal').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const card = this.closest('.crm-kanban-card');
                showDealDetails(card.dataset);
            });
        });
        
        // View from Table
        document.querySelectorAll('.crm-view-deal-table').forEach(btn => {
            btn.addEventListener('click', function() {
                showDealDetails(this.dataset);
            });
        });
        
        // ========== DRAG AND DROP ==========
        let draggedCard = null;
        
        // Make cards draggable
        document.querySelectorAll('.crm-kanban-card').forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedCard = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
            });
            
            card.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                draggedCard = null;
            });
        });
        
        // Make columns droppable
        document.querySelectorAll('.crm-kanban-cards').forEach(column => {
            column.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                this.classList.add('drag-over');
            });
            
            column.addEventListener('dragleave', function() {
                this.classList.remove('drag-over');
            });
            
            column.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                if (draggedCard) {
                    const newStage = this.getAttribute('data-stage');
                    const dealData = draggedCard.dataset;
                    const oldStage = dealData.currentStage;
                    
                    if (newStage !== oldStage) {
                        // Build complete form data matching the edit modal structure
                        const formData = new FormData();
                        formData.append('action', 'crm_update_deal');
                        formData.append('deal_id', dealData.dealId);
                        formData.append('customer_id', dealData.customerId);
                        formData.append('deal_name', dealData.dealName);
                        formData.append('deal_value', dealData.dealValue);
                        formData.append('stage', newStage);  // Only change the stage
                        formData.append('probability', dealData.probability || '0');
                        formData.append('expected_close_date', dealData.expectedDate || '');
                        formData.append('notes', dealData.notes || '');
                        formData.append('nonce', nonce);
                        
                        // Handle products if they exist
                        if (dealData.products) {
                            const productIds = dealData.products.split(',').filter(id => id.trim() !== '');
                            productIds.forEach(id => {
                                formData.append('products[]', id.trim());
                            });
                        }
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(r => r.json())
                        .then(json => {
                            if (json.success) {
                                // Reload to update counts and positions
                                location.reload();
                            } else {
                                alert('Failed to move deal: ' + json.data.message);
                            }
                        })
                        .catch(err => {
                            alert('Error moving deal: ' + err.message);
                        });
                    }
                }
            });
        });
        
        // ========== ADD DEAL FORM ==========
        document.getElementById('crm-add-deal-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_add_deal');
            formData.append('nonce', nonce);
            
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
                    btn.textContent = 'Add Deal';
                }
            });
        });
        
        // ========== EDIT DEAL ==========
        document.querySelectorAll('.crm-edit-deal').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const data = this.dataset;
                
                document.getElementById('edit-deal-id').value = data.id;
                document.getElementById('edit-deal-customer').value = data.customer;
                document.getElementById('edit-deal-name').value = data.name;
                document.getElementById('edit-deal-value').value = data.value;
                document.getElementById('edit-deal-stage').value = data.stage;
                document.getElementById('edit-deal-probability').value = data.probability;
                document.getElementById('edit-deal-date').value = data.date;
                document.getElementById('edit-deal-notes').value = data.notes;
                
                // Set products if available
                if (data.products && document.getElementById('edit-deal-products')) {
                    const productIds = data.products.split(',').filter(id => id.trim() !== '');
                    Array.from(document.getElementById('edit-deal-products').options).forEach(option => {
                        option.selected = productIds.includes(option.value);
                    });
                }
                
                editModal.style.display = 'flex';
            });
        });
        
        document.getElementById('crm-edit-deal-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_update_deal');
            formData.append('nonce', nonce);
            
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
                    btn.textContent = 'Update Deal';
                }
            });
        });
        
        // ========== DELETE DEAL ==========
        document.querySelectorAll('.crm-delete-deal').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this deal?')) return;
                
                const dealId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'crm_delete_deal');
                formData.append('deal_id', dealId);
                formData.append('nonce', nonce);
                
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
    <?php
    return ob_get_clean();
}

function crm_activities_tab($business_id) {
    global $wpdb;
    $activities_table = $wpdb->prefix . 'crm_activities';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $deals_table = $wpdb->prefix . 'crm_deals';
    
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, c.name as customer_name, d.deal_name
        FROM $activities_table a
        LEFT JOIN $customers_table c ON a.customer_id = c.id
        LEFT JOIN $deals_table d ON a.deal_id = d.id
        ORDER BY a.activity_date DESC
        LIMIT 50",
        $business_id
    ));
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table ORDER BY name ASC",
        $business_id
    ));
    
    $deals = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $deals_table ORDER BY deal_name ASC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <button id="open-add-activity-modal" class="bntm-btn-primary">+ Log New Activity</button>
    </div>

    <!-- Add Activity Modal -->
    <div id="add-activity-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Log Activity</h3>
            <form id="crm-add-activity-form" class="bntm-form">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Customer/Lead *</label>
                        <select name="customer_id" required>
                            <option value="">Select Customer/Lead</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer->id; ?>">
                                    <?php echo esc_html($customer->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Related Deal (Optional)</label>
                        <select name="deal_id">
                            <option value="">No related deal</option>
                            <?php foreach ($deals as $deal): ?>
                                <option value="<?php echo $deal->id; ?>">
                                    <?php echo esc_html($deal->deal_name); ?>
                                </option>
                            <?php endforeach; ?></select>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Activity Type *</label>
                        <select name="activity_type" required>
                            <option value="call">Phone Call</option>
                            <option value="email">Email</option>
                            <option value="meeting">Meeting</option>
                            <option value="message">Message/SMS</option>
                            <option value="note">Note</option>
                            <option value="purchase">Purchase</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Activity Date *</label>
                        <input type="datetime-local" name="activity_date" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" required placeholder="e.g., Follow-up call about quotation">
                </div>

                <div class="bntm-form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="4" required placeholder="Details of the activity..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Log Activity</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Activity Timeline (<?php echo count($activities); ?>)</h3>
        
        <?php if (empty($activities)): ?>
            <p>No activities logged yet. Start tracking your customer interactions above.</p>
        <?php else: ?>
            <div class="crm-timeline">
                <?php 
                $activity_icons = [
                    'call' => '📞',
                    'email' => '📧',
                    'meeting' => '🤝',
                    'message' => '💬',
                    'note' => '📝',
                    'purchase' => '🛒',
                    'other' => '📌'
                ];
                
                $activity_colors = [
                    'call' => '#3b82f6',
                    'email' => '#8b5cf6',
                    'meeting' => '#059669',
                    'message' => '#f59e0b',
                    'note' => '#6b7280',
                    'purchase' => '#10b981',
                    'other' => '#6b7280'
                ];
                
                foreach ($activities as $activity): 
                    $icon = $activity_icons[$activity->activity_type] ?? '📌';
                    $color = $activity_colors[$activity->activity_type] ?? '#6b7280';
                ?>
                    <div class="crm-timeline-item">
                        <div class="crm-timeline-marker" style="background: <?php echo $color; ?>;">
                            <?php echo $icon; ?>
                        </div>
                        <div class="crm-timeline-content">
                            <div class="crm-timeline-header">
                                <h4><?php echo esc_html($activity->subject); ?></h4>
                                <span class="crm-timeline-date"><?php echo date('M d, Y g:i A', strtotime($activity->activity_date)); ?></span>
                            </div>
                            <div class="crm-timeline-meta">
                                <span><strong>Customer:</strong> <?php echo esc_html($activity->customer_name); ?></span>
                                <?php if ($activity->deal_name): ?>
                                    <span><strong>Deal:</strong> <?php echo esc_html($activity->deal_name); ?></span>
                                <?php endif; ?>
                                <span><strong>Type:</strong> <?php echo esc_html(ucfirst($activity->activity_type)); ?></span>
                            </div>
                            <p class="crm-timeline-description"><?php echo nl2br(esc_html($activity->description)); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .crm-timeline {
        position: relative;
        padding-left: 50px;
    }
    .crm-timeline::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }
    .crm-timeline-item {
        position: relative;
        margin-bottom: 30px;
    }
    .crm-timeline-marker {
        position: absolute;
        left: -40px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .crm-timeline-content {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 15px;
    }
    .crm-timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    .crm-timeline-header h4 {
        margin: 0;
        font-size: 16px;
        color: #1f2937;
    }
    .crm-timeline-date {
        font-size: 12px;
        color: #6b7280;
        white-space: nowrap;
    }
    .crm-timeline-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 10px;
    }
    .crm-timeline-description {
        margin: 0;
        color: #374151;
        font-size: 14px;
        line-height: 1.6;
    }
    </style>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-activity-modal');
        
        document.getElementById('open-add-activity-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== ADD ACTIVITY FORM ==========
        document.getElementById('crm-add-activity-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_add_activity');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Logging...';
            
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
                    btn.textContent = 'Log Activity';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function crm_tasks_tab($business_id) {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'crm_tasks';
    $customers_table = $wpdb->prefix . 'crm_customers';
    $deals_table = $wpdb->prefix . 'crm_deals';
    
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, c.name as customer_name, d.deal_name
        FROM $tasks_table t
        LEFT JOIN $customers_table c ON t.customer_id = c.id
        LEFT JOIN $deals_table d ON t.deal_id = d.id
        ORDER BY t.due_date ASC, t.priority DESC",
        $business_id
    ));
    
    $customers = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $customers_table  ORDER BY name ASC",
        $business_id
    ));
    
    $deals = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $deals_table ORDER BY deal_name ASC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('crm_nonce');
    
    // Group tasks by status
    $pending = array_filter($tasks, fn($t) => $t->status === 'pending');
    $in_progress = array_filter($tasks, fn($t) => $t->status === 'in_progress');
    $completed = array_filter($tasks, fn($t) => $t->status === 'completed');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <button id="open-add-task-modal" class="bntm-btn-primary">+ Add New Task</button>
    </div>

    <!-- Add Task Modal -->
    <div id="add-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Create New Task</h3>
            <form id="crm-add-task-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Task Title *</label>
                    <input type="text" name="task_title" required placeholder="e.g., Call customer for follow-up">
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Customer/Lead</label>
                        <select name="customer_id">
                            <option value="">No customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer->id; ?>">
                                    <?php echo esc_html($customer->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Related Deal</label>
                        <select name="deal_id">
                            <option value="">No related deal</option>
                            <?php foreach ($deals as $deal): ?>
                                <option value="<?php echo $deal->id; ?>">
                                    <?php echo esc_html($deal->deal_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Due Date *</label>
                        <input type="datetime-local" name="due_date" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Priority *</label>
                        <select name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Task details..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Create Task</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Tasks Overview</h3>
        <div class="bntm-dashboard-stats">
            <div class="bntm-stat-card">
                <h3>Pending</h3>
                <p class="bntm-stat-number" style="color: #f59e0b;"><?php echo count($pending); ?></p>
                <small>Awaiting action</small>
            </div>
            <div class="bntm-stat-card">
                <h3>In Progress</h3>
                <p class="bntm-stat-number" style="color: #3b82f6;"><?php echo count($in_progress); ?></p>
                <small>Currently working</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Completed</h3>
                <p class="bntm-stat-number" style="color: #059669;"><?php echo count($completed); ?></p>
                <small>Finished tasks</small>
            </div>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>All Tasks (<?php echo count($tasks); ?>)</h3>
        
        <?php if (empty($tasks)): ?>
            <p>No tasks found. Create your first task above.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th width="30"></th>
                        <th>Task</th>
                        <th>Customer</th>
                        <th>Deal</th>
                        <th>Due Date</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $priority_colors = [
                        'low' => '#6b7280',
                        'medium' => '#f59e0b',
                        'high' => '#ef4444',
                        'urgent' => '#991b1b'
                    ];
                    
                    $status_colors = [
                        'pending' => '#f59e0b',
                        'in_progress' => '#3b82f6',
                        'completed' => '#059669',
                        'cancelled' => '#6b7280'
                    ];
                    
                    foreach ($tasks as $task): 
                        $is_overdue = strtotime($task->due_date) < time() && $task->status === 'pending';
                    ?>
                        <tr style="<?php echo $is_overdue ? 'background: #fef2f2;' : ''; ?>">
                            <td>
                                <input type="checkbox" 
                                    class="task-checkbox" 
                                    data-id="<?php echo $task->id; ?>"
                                    <?php echo $task->status === 'completed' ? 'checked' : ''; ?>>
                            </td>
                            <td>
                                <strong><?php echo esc_html($task->task_title); ?></strong>
                                <?php if ($task->description): ?>
                                    <br><small style="color: #6b7280;"><?php echo esc_html(substr($task->description, 0, 50)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($task->customer_name ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($task->deal_name ?: 'N/A'); ?></td>
                            <td>
                                <?php if ($is_overdue): ?>
                                    <span style="color: #dc2626; font-weight: 500;">
                                        ⚠️ <?php echo date('M d, Y g:i A', strtotime($task->due_date)); ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo date('M d, Y g:i A', strtotime($task->due_date)); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php echo $priority_colors[$task->priority]; ?>; font-weight: 500;">
                                    <?php echo esc_html(ucfirst($task->priority)); ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: <?php echo $status_colors[$task->status]; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $task->status))); ?>
                                </span>
                            </td>
                            <td>
                                <button class="bntm-btn-small crm-edit-task"
                                    data-id="<?php echo $task->id; ?>"
                                    data-title="<?php echo esc_attr($task->task_title); ?>"
                                    data-customer="<?php echo $task->customer_id; ?>"
                                    data-deal="<?php echo $task->deal_id; ?>"
                                    data-due="<?php echo date('Y-m-d\TH:i', strtotime($task->due_date)); ?>"
                                    data-priority="<?php echo $task->priority; ?>"
                                    data-status="<?php echo $task->status; ?>"
                                    data-description="<?php echo esc_attr($task->description); ?>">Edit</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Edit Task Modal -->
    <div id="edit-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Task</h3>
            <form id="crm-edit-task-form" class="bntm-form">
                <input type="hidden" id="edit-task-id" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Task Title *</label>
                    <input type="text" id="edit-task-title" name="task_title" required>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Customer/Lead</label>
                        <select id="edit-task-customer" name="customer_id">
                            <option value="">No customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer->id; ?>">
                                    <?php echo esc_html($customer->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Related Deal</label>
                        <select id="edit-task-deal" name="deal_id">
                            <option value="">No related deal</option>
                            <?php foreach ($deals as $deal): ?>
                                <option value="<?php echo $deal->id; ?>">
                                    <?php echo esc_html($deal->deal_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Due Date *</label>
                        <input type="datetime-local" id="edit-task-due" name="due_date" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Priority *</label>
                        <select id="edit-task-priority" name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Status *</label>
                    <select id="edit-task-status" name="status" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-task-description" name="description" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Task</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
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
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    .bntm-stat-card small {
        color: #9ca3af;
        font-size: 12px;
    }
    .task-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    </style>

    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-task-modal');
        const editModal = document.getElementById('edit-task-modal');
        
        document.getElementById('open-add-task-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== ADD TASK FORM ==========
        document.getElementById('crm-add-task-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_add_task');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Creating...';
            
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
                    btn.textContent = 'Create Task';
                }
            });
        });
        
        // ========== TASK CHECKBOX ==========
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.getAttribute('data-id');
                const status = this.checked ? 'completed' : 'pending';
                
                const formData = new FormData();
                formData.append('action', 'crm_update_task');
                formData.append('task_id', taskId);
                formData.append('status', status);
                formData.append('nonce', nonce);
                
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
                        this.checked = !this.checked;
                    }
                });
            });
        });
        
        // ========== EDIT TASK ==========
        document.querySelectorAll('.crm-edit-task').forEach(btn => {
            btn.addEventListener('click', function() {
                const data = this.dataset;
                
                document.getElementById('edit-task-id').value = data.id;
                document.getElementById('edit-task-title').value = data.title;
                document.getElementById('edit-task-customer').value = data.customer || '';
                document.getElementById('edit-task-deal').value = data.deal || '';
                document.getElementById('edit-task-due').value = data.due;
                document.getElementById('edit-task-priority').value = data.priority;
                document.getElementById('edit-task-status').value = data.status;
                document.getElementById('edit-task-description').value = data.description;
                
                editModal.style.display = 'flex';
            });
        });
        
        document.getElementById('crm-edit-task-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'crm_update_task');
            formData.append('nonce', nonce);
            
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
                    btn.textContent = 'Update Task';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function crm_communication_tab($business_id) {
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Communication Tools</h3>
        <p style="background: #eff6ff; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6;">
            <strong>🚀 Coming Soon:</strong> This section is API-ready for integration with messaging platforms.
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px;">
            <div style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #1f2937;">📧 Email Integration</h4>
                <p style="color: #6b7280; font-size: 14px;">Send bulk emails and campaigns directly from CRM.</p>
                <button class="bntm-btn-secondary" disabled>Configure Email API</button>
            </div>
            
            <div style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #1f2937;">💬 SMS Integration</h4>
                <p style="color: #6b7280; font-size: 14px;">Send SMS messages via Twilio or other providers.</p>
                <button class="bntm-btn-secondary" disabled>Configure SMS API</button>
            </div>
            
            <div style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #1f2937;">📱 WhatsApp Business</h4>
                <p style="color: #6b7280; font-size: 14px;">Integrate WhatsApp Business API for messaging.</p>
                <button class="bntm-btn-secondary" disabled>Configure WhatsApp API</button>
            </div>
            
            <div style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #1f2937;">🔔 Push Notifications</h4>
                <p style="color: #6b7280; font-size: 14px;">Send automated notifications to customers.</p>
                <button class="bntm-btn-secondary" disabled>Configure Notifications</button>
            </div>
        </div>
        
        <div style="margin-top: 30px; background: #f9fafb; padding: 20px; border-radius: 8px;">
            <h4>API Integration Guide</h4>
            <p>To enable these features, you'll need to:</p>
            <ol style="color: #6b7280;">
                <li>Choose your preferred service provider (e.g., Twilio for SMS, SendGrid for Email)</li>
                <li>Obtain API credentials from the provider</li>
                <li>Configure the API settings in your CRM</li>
                <li>Test the integration before going live</li>
            </ol>
            <p style="margin-top: 15px;"><strong>Need help?</strong> Contact support for API integration assistance.</p>
        </div>
    </div>

    <style>
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        margin-top: 10px;
    }
    .bntm-btn-secondary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    </style>
    <?php
    return ob_get_clean();
}

function crm_reports_tab($business_id) {
    global $wpdb;
    $customers_table = $wpdb->prefix . 'crm_customers';
    $deals_table = $wpdb->prefix . 'crm_deals';
    
    // Sales Report
    $total_deals = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $deals_table ",
        $business_id
    ));
    
    $won_deals = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $deals_table WHERE  stage = 'won'",
        $business_id
    ));
    
    $lost_deals = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $deals_table WHERE  stage = 'lost'",
        $business_id
    ));
    
    $total_revenue = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(deal_value) FROM $deals_table WHERE  stage = 'won'",
        $business_id
    ));
    
    $conversion_rate = $total_deals > 0 ? round(($won_deals / $total_deals) * 100, 1) : 0;
    
    // Lead sources
    $lead_sources = $wpdb->get_results($wpdb->prepare(
        "SELECT source, COUNT(*) as count 
        FROM $customers_table 
        WHERE  source IS NOT NULL AND source != ''
        GROUP BY source 
        ORDER BY count DESC",
        $business_id
    ));
    
    // Monthly deals
    $monthly_deals = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            DATE_FORMAT(created_at, '%%Y-%%m') as month,
            COUNT(*) as total_deals,
            SUM(CASE WHEN stage = 'won' THEN 1 ELSE 0 END) as won_deals,
            SUM(CASE WHEN stage = 'won' THEN deal_value ELSE 0 END) as revenue
        FROM $deals_table 
        GROUP BY month
        ORDER BY month DESC
        LIMIT 12",
        $business_id
    ));
    
    // Customer retention
    $new_customers_30d = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $customers_table 
        WHERE type = 'customer' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
        $business_id
    ));
    
    $total_customers = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $customers_table WHERE type = 'customer'",
        $business_id
    ));
    
    $repeat_customers = $total_customers - $new_customers_30d;
    
    // Deal stages breakdown
    $stage_breakdown = $wpdb->get_results($wpdb->prepare(
        "SELECT stage, COUNT(*) as count, SUM(deal_value) as total_value
        FROM $deals_table 
        GROUP BY stage",
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Sales Performance Report</h3>
        <div class="bntm-dashboard-stats">
            <div class="bntm-stat-card">
                <h3>Total Deals</h3>
                <p class="bntm-stat-number"><?php echo esc_html($total_deals); ?></p>
                <small>All time</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Won Deals</h3>
                <p class="bntm-stat-number" style="color: #059669;"><?php echo esc_html($won_deals); ?></p>
                <small>Successful closes</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Lost Deals</h3>
                <p class="bntm-stat-number" style="color: #dc2626;"><?php echo esc_html($lost_deals); ?></p>
                <small>Unsuccessful</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Total Revenue</h3>
                <p class="bntm-stat-number" style="color: #059669;">₱<?php echo number_format($total_revenue ?: 0, 2); ?></p>
                <small>From won deals</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Conversion Rate</h3>
                <p class="bntm-stat-number" style="color: #3b82f6;"><?php echo $conversion_rate; ?>%</p>
                <small>Win rate</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Avg Deal Value</h3>
                <p class="bntm-stat-number">₱<?php echo $won_deals > 0 ? number_format($total_revenue / $won_deals, 2) : '0.00'; ?></p>
                <small>Per won deal</small>
            </div>
        </div>
    </div>

    <?php if (!empty($lead_sources)): ?>
    <div class="bntm-form-section">
        <h3>Lead Source Analysis</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Number of Leads</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_leads_with_source = array_sum(array_column($lead_sources, 'count'));
                foreach ($lead_sources as $source): 
                    $percentage = round(($source->count / $total_leads_with_source) * 100, 1);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($source->source); ?></strong></td>
                        <td><?php echo esc_html($source->count); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="background: #e5e7eb; height: 20px; flex: 1; border-radius: 4px; overflow: hidden;">
                                    <div style="background: #3b82f6; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                                </div>
                                <span><?php echo $percentage; ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($monthly_deals)): ?>
    <div class="bntm-form-section">
        <h3>Monthly Performance (Last 12 Months)</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Total Deals</th>
                    <th>Won Deals</th>
                    <th>Revenue</th>
                    <th>Win Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_deals as $month): 
                    $win_rate = $month->total_deals > 0 ? round(($month->won_deals / $month->total_deals) * 100, 1) : 0;
                    $month_name = date('F Y', strtotime($month->month . '-01'));
                ?>
                    <tr>
                        <td><strong><?php echo $month_name; ?></strong></td>
                        <td><?php echo esc_html($month->total_deals); ?></td>
                        <td style="color: #059669;"><?php echo esc_html($month->won_deals); ?></td>
                        <td>₱<?php echo number_format($month->revenue, 2); ?></td>
                        <td><?php echo $win_rate; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($stage_breakdown)): ?>
    <div class="bntm-form-section">
        <h3>Pipeline Breakdown by Stage</h3>
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Number of Deals</th>
                    <th>Total Value</th>
                    <th>Avg Deal Value</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $stage_labels = [
                    'new' => 'New',
                    'contacted' => 'Contacted',
                    'interested' => 'Interested',
                    'quotation_sent' => 'Quotation Sent',
                    'negotiation' => 'Negotiation',
                    'won' => 'Won',
                    'lost' => 'Lost'
                ];
                
                foreach ($stage_breakdown as $stage): 
                    $avg_value = $stage->count > 0 ? $stage->total_value / $stage->count : 0;
                ?>
                    <tr>
                        <td><strong><?php echo $stage_labels[$stage->stage] ?? ucfirst($stage->stage); ?></strong></td>
                        <td><?php echo esc_html($stage->count); ?></td>
                        <td>₱<?php echo number_format($stage->total_value, 2); ?></td>
                        <td>₱<?php echo number_format($avg_value, 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="bntm-form-section">
        <h3>Customer Retention</h3>
        <div class="bntm-dashboard-stats">
            <div class="bntm-stat-card">
                <h3>Total Customers</h3>
                <p class="bntm-stat-number"><?php echo esc_html($total_customers); ?></p>
                <small>Customer base</small>
            </div>
            <div class="bntm-stat-card">
                <h3>New Customers (30d)</h3>
                <p class="bntm-stat-number" style="color: #3b82f6;"><?php echo esc_html($new_customers_30d); ?></p>
                <small>Last 30 days</small>
            </div>
            <div class="bntm-stat-card">
                <h3>Repeat Customers</h3>
                <p class="bntm-stat-number" style="color: #8b5cf6;"><?php echo esc_html($repeat_customers); ?></p>
                <small>Existing customers</small>
            </div>
        </div>
        
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4>Key Insights</h4>
            <ul style="color: #6b7280; line-height: 1.8;">
                <li>Your conversion rate is <strong><?php echo $conversion_rate; ?>%</strong> - <?php echo $conversion_rate >= 20 ? 'Great job!' : 'There\'s room for improvement.'; ?></li>
                <li>Average deal value: <strong>₱<?php echo $won_deals > 0 ? number_format($total_revenue / $won_deals, 2) : '0.00'; ?></strong></li>
                <li><?php echo $new_customers_30d; ?> new customers acquired in the last 30 days</li>
                <li>Focus on <?php echo $lost_deals > 0 ? 'reducing lost deals' : 'maintaining your success rate'; ?></li>
            </ul>
        </div>
    </div>

    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    .bntm-stat-card small {
        color: #9ca3af;
        font-size: 12px;
    }
    </style>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLERS ---------- */

function bntm_ajax_crm_add_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'type' => sanitize_text_field($_POST['type']),
        'name' => sanitize_text_field($_POST['name']),
        'contact_number' => sanitize_text_field($_POST['contact_number']),
        'email' => sanitize_email($_POST['email']),
        'company' => sanitize_text_field($_POST['company']),
        'address' => sanitize_textarea_field($_POST['address']),
        'birthday' => !empty($_POST['birthday']) ? sanitize_text_field($_POST['birthday']) : null,
        'anniversary' => !empty($_POST['anniversary']) ? sanitize_text_field($_POST['anniversary']) : null,
        'source' => sanitize_text_field($_POST['source']),
        'tags' => sanitize_text_field($_POST['tags']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        $type_label = $data['type'] === 'lead' ? 'Lead' : 'Customer';
        wp_send_json_success(['message' => $type_label . ' added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add. Please try again.']);
    }
}

function bntm_ajax_crm_update_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();

    $customer_id = intval($_POST['customer_id']);

    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'contact_number' => sanitize_text_field($_POST['contact_number']),
        'email' => sanitize_email($_POST['email']),
        'company' => sanitize_text_field($_POST['company']),
        'address' => sanitize_textarea_field($_POST['address']),
        'birthday' => !empty($_POST['birthday']) ? sanitize_text_field($_POST['birthday']) : null,
        'anniversary' => !empty($_POST['anniversary']) ? sanitize_text_field($_POST['anniversary']) : null,
        'source' => sanitize_text_field($_POST['source']),
        'tags' => sanitize_text_field($_POST['tags']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    $result = $wpdb->update(
        $table,
        $data,
        ['id' => $customer_id],
        ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update. Please try again.']);
    }
}

function bntm_ajax_crm_delete_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();

    $customer_id = intval($_POST['customer_id']);

    $result = $wpdb->delete($table, [
        'id' => $customer_id
    ], ['%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete. Please try again.']);
    }
}

function bntm_ajax_crm_convert_to_customer() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();

    $customer_id = intval($_POST['customer_id']);

    $result = $wpdb->update(
        $table,
        ['type' => 'customer'],
        ['id' => $customer_id],
        ['%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Lead converted to customer successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to convert. Please try again.']);
    }
}

function bntm_ajax_crm_add_deal() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();

    $products = isset($_POST['products']) ? implode(',', array_map('intval', $_POST['products'])) : '';

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => intval($_POST['customer_id']),
        'deal_name' => sanitize_text_field($_POST['deal_name']),
        'deal_value' => floatval($_POST['deal_value']),
        'stage' => sanitize_text_field($_POST['stage']),
        'probability' => intval($_POST['probability']),
        'expected_close_date' => !empty($_POST['expected_close_date']) ? sanitize_text_field($_POST['expected_close_date']) : null,
        'products' => $products,
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        wp_send_json_success(['message' => 'Deal added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add deal. Please try again.']);
    }
}

function bntm_ajax_crm_update_deal() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();

    $deal_id = intval($_POST['deal_id']);
    $products = isset($_POST['products']) ? implode(',', array_map('intval', $_POST['products'])) : '';

    $data = [
        'customer_id' => intval($_POST['customer_id']),
        'deal_name' => sanitize_text_field($_POST['deal_name']),
        'deal_value' => floatval($_POST['deal_value']),
        'stage' => sanitize_text_field($_POST['stage']),
        'probability' => intval($_POST['probability']),
        'expected_close_date' => !empty($_POST['expected_close_date']) ? sanitize_text_field($_POST['expected_close_date']) : null,
        'products' => $products,
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    // If stage is won or lost, set actual close date
    if (in_array($data['stage'], ['won', 'lost'])) {
        $data['actual_close_date'] = current_time('mysql');
    }

    $result = $wpdb->update(
        $table,
        $data,
        ['id' => $deal_id],
        ['%d', '%s', '%f', '%s', '%d', '%s', '%s', '%s'],
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Deal updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update deal. Please try again.']);
    }
}

function bntm_ajax_crm_delete_deal() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_deals';
    $business_id = get_current_user_id();

    $deal_id = intval($_POST['deal_id']);

    $result = $wpdb->delete($table, [
        'id' => $deal_id
    ], [ '%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Deal deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete deal. Please try again.']);
    }
}

function bntm_ajax_crm_add_activity() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_activities';
    $business_id = get_current_user_id();

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => intval($_POST['customer_id']),
        'deal_id' => !empty($_POST['deal_id']) ? intval($_POST['deal_id']) : null,
        'activity_type' => sanitize_text_field($_POST['activity_type']),
        'subject' => sanitize_text_field($_POST['subject']),
        'description' => sanitize_textarea_field($_POST['description']),
        'activity_date' => sanitize_text_field($_POST['activity_date']),
        'created_by' => $business_id
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        wp_send_json_success(['message' => 'Activity logged successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to log activity. Please try again.']);
    }
}

function bntm_ajax_crm_add_task() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_tasks';
    $business_id = get_current_user_id();

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'customer_id' => !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null,
        'deal_id' => !empty($_POST['deal_id']) ? intval($_POST['deal_id']) : null,
        'task_title' => sanitize_text_field($_POST['task_title']),
        'description' => sanitize_textarea_field($_POST['description']),
        'due_date' => sanitize_text_field($_POST['due_date']),
        'priority' => sanitize_text_field($_POST['priority']),
        'status' => 'pending',
        'assigned_to' => $business_id,
        'created_by' => $business_id
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        wp_send_json_success(['message' => 'Task created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create task. Please try again.']);
    }
}

function bntm_ajax_crm_update_task() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_tasks';
    $business_id = get_current_user_id();

    $task_id = intval($_POST['task_id']);

    // Handle quick status update from checkbox
    if (isset($_POST['status']) && !isset($_POST['task_title'])) {
        $data = ['status' => sanitize_text_field($_POST['status'])];
        $format = ['%s'];
    } else {
        $data = [
            'task_title' => sanitize_text_field($_POST['task_title']),
            'customer_id' => !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null,
            'deal_id' => !empty($_POST['deal_id']) ? intval($_POST['deal_id']) : null,
            'description' => sanitize_textarea_field($_POST['description']),
            'due_date' => sanitize_text_field($_POST['due_date']),
            'priority' => sanitize_text_field($_POST['priority']),
            'status' => sanitize_text_field($_POST['status'])
        ];
        $format = ['%s', '%d', '%d', '%s', '%s', '%s', '%s'];
    }

    $result = $wpdb->update(
        $table,
        $data,
        ['id' => $task_id],
        $format,
        ['%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Task updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update task. Please try again.']);
    }
}

function bntm_ajax_crm_import_customers() {
    check_ajax_referer('crm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'crm_customers';
    $business_id = get_current_user_id();
 $import_data = json_decode(stripslashes($_POST['data']), true);
    
    if (empty($import_data)) {
        wp_send_json_error(['message' => 'No data to import.']);
    }

    $imported = 0;
    $errors = 0;

    foreach ($import_data as $row) {
        // Map CSV columns to database fields (case insensitive)
        $name = $row['name'] ?? $row['full name'] ?? '';
        $contact = $row['contact'] ?? $row['contact number'] ?? $row['phone'] ?? '';
        $email = $row['email'] ?? '';
        $company = $row['company'] ?? '';
        $address = $row['address'] ?? '';

        // Validate required fields
        if (empty($name) || empty($contact)) {
            $errors++;
            continue;
        }

        $data = [
            'rand_id' => bntm_rand_id(),
            'business_id' => $business_id,
            'type' => 'customer',
            'name' => sanitize_text_field($name),
            'contact_number' => sanitize_text_field($contact),
            'email' => sanitize_email($email),
            'company' => sanitize_text_field($company),
            'address' => sanitize_textarea_field($address),
            'source' => 'Import'
        ];

        $result = $wpdb->insert($table, $data);
        
        if ($result) {
            $imported++;
        } else {
            $errors++;
        }
    }

    if ($imported > 0) {
        $message = "Successfully imported {$imported} customer(s).";
        if ($errors > 0) {
            $message .= " {$errors} row(s) failed.";
        }
        wp_send_json_success(['message' => $message]);
    } else {
        wp_send_json_error(['message' => 'Failed to import customers. Please check your data.']);
    }
}