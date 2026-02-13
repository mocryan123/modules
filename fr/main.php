<?php
/**
 * Module Name: Forms
 * Module Slug: forms
 * Description: Advanced form builder with custom fields, entry management, templates, and public/private publishing
 * Version: 1.0.0
 * Author: BusiNest
 * Icon: 📋
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_FORMS_PATH', dirname(__FILE__) . '/');
define('BNTM_FORMS_URL', plugin_dir_url(__FILE__));

// ============================================================================
// CORE MODULE FUNCTIONS (Required by Framework)
// ============================================================================

/**
 * Define module pages
 */
function bntm_forms_get_pages() {
    return [
        'Forms Dashboard' => '[forms_dashboard]',
        'Form Builder' => '[forms_builder]',
        'Form Entries' => '[forms_entries]',
        'Public Form' => '[forms_public_form]',
    ];
}

/**
 * Define database tables
 */
function bntm_forms_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'forms_forms' => "CREATE TABLE {$prefix}forms_forms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            fields LONGTEXT NOT NULL,
            settings LONGTEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            visibility ENUM('private', 'public') DEFAULT 'private',
            entry_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_visibility (visibility)
        ) {$charset};",
        
        'forms_entries' => "CREATE TABLE {$prefix}forms_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            form_rand_id VARCHAR(20) NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            data LONGTEXT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('read', 'unread') DEFAULT 'unread',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_form (form_id),
            INDEX idx_form_rand (form_rand_id),
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) {$charset};",
    ];
}

/**
 * Register shortcodes
 */
function bntm_forms_get_shortcodes() {
    return [
        'forms_dashboard' => 'bntm_shortcode_forms_dashboard',
        'forms_builder' => 'bntm_shortcode_forms_builder',
        'forms_entries' => 'bntm_shortcode_forms_entries',
        'forms_public_form' => 'bntm_shortcode_forms_public_form',
    ];
}

/**
 * Create tables
 */
function bntm_forms_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_forms_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

add_action('wp_ajax_forms_create_form', 'bntm_ajax_forms_create_form');
add_action('wp_ajax_forms_update_form', 'bntm_ajax_forms_update_form');
add_action('wp_ajax_forms_delete_form', 'bntm_ajax_forms_delete_form');
add_action('wp_ajax_forms_get_form', 'bntm_ajax_forms_get_form');
add_action('wp_ajax_forms_duplicate_form', 'bntm_ajax_forms_duplicate_form');
add_action('wp_ajax_forms_toggle_visibility', 'bntm_ajax_forms_toggle_visibility');
add_action('wp_ajax_forms_get_entries', 'bntm_ajax_forms_get_entries');
add_action('wp_ajax_forms_get_entry_details', 'bntm_ajax_forms_get_entry_details');
add_action('wp_ajax_forms_delete_entry', 'bntm_ajax_forms_delete_entry');
add_action('wp_ajax_forms_mark_entry_read', 'bntm_ajax_forms_mark_entry_read');
add_action('wp_ajax_forms_export_entries', 'bntm_ajax_forms_export_entries');

// Public form submission (no login required)
add_action('wp_ajax_forms_submit_entry', 'bntm_ajax_forms_submit_entry');
add_action('wp_ajax_nopriv_forms_submit_entry', 'bntm_ajax_forms_submit_entry');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_forms_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access Forms.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'forms';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-forms-container">
        <div class="bntm-tabs">
            <a href="?tab=forms" class="bntm-tab <?php echo $active_tab === 'forms' ? 'active' : ''; ?>">
                My Forms
            </a>
            <a href="?tab=templates" class="bntm-tab <?php echo $active_tab === 'templates' ? 'active' : ''; ?>">
                Templates
            </a>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                Settings
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'forms'): ?>
                <?php echo forms_list_tab($business_id); ?>
            <?php elseif ($active_tab === 'templates'): ?>
                <?php echo forms_templates_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo forms_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Forms Manager', $content);
}

// ============================================================================
// TAB FUNCTIONS
// ============================================================================

/**
 * Forms List Tab
 */
function forms_list_tab($business_id) {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $forms = $wpdb->get_results(
        "SELECT * FROM $forms_table ORDER BY created_at DESC"
    );
    
    $stats = forms_get_stats($business_id);
    
    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>All Forms</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['total_forms']); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>My Forms</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['my_forms']); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>My Entries</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['total_entries']); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Unread</h3>
            <p class="bntm-stat-number"><?php echo esc_html($stats['unread_entries']); ?></p>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Your Forms</h3>
            <button id="create-new-form-btn" class="bntm-btn-primary">
                Create New Form
            </button>
        </div>
        
        <?php if (empty($forms)): ?>
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <p style="font-size: 18px; margin-bottom: 10px;">No forms created yet</p>
            <p>Start by creating your first form or using a template</p>
        </div>
        <?php else: ?>
        <div class="forms-grid">
            <?php foreach ($forms as $form): ?>
                <div class="form-card" data-form-id="<?php echo $form->id; ?>">
                    <div class="form-card-header">
                        <h4><?php echo esc_html($form->title); ?></h4>
                        <div>
                            <?php if ($form->business_id == $business_id): ?>
                                <span class="owner-badge">Owner</span>
                            <?php endif; ?>
                            <span class="form-status form-status-<?php echo $form->status; ?>">
                                <?php echo ucfirst($form->status); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="form-card-body">
                        <?php if ($form->description): ?>
                        <p class="form-description"><?php echo esc_html(substr($form->description, 0, 100)); ?>...</p>
                        <?php endif; ?>
                        
                        <div class="form-meta">
                            <span>📊 <?php echo $form->entry_count; ?> entries</span>
                            <span>
                                <?php if ($form->visibility === 'public'): ?>
                                    🌐 Public
                                <?php else: ?>
                                    🔒 Private
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="form-meta-date">
                            Created: <?php echo date('M d, Y', strtotime($form->created_at)); ?>
                        </div>
                    </div>
                    
                    <div class="form-card-actions">
                        <button class="bntm-btn-small view-entries-btn" data-form-id="<?php echo $form->id; ?>">
                            View Entries
                        </button>
                        
                        <?php if ($form->business_id == $business_id): ?>
                            <button class="bntm-btn-small edit-form-btn" data-form-id="<?php echo $form->id; ?>">
                                Edit
                            </button>
                            <button class="bntm-btn-small duplicate-form-btn" data-form-id="<?php echo $form->id; ?>">
                                Duplicate
                            </button>
                            <button class="bntm-btn-small bntm-btn-danger delete-form-btn" data-form-id="<?php echo $form->id; ?>">
                                Delete
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($form->visibility === 'public'): ?>
                            <button class="bntm-btn-small view-public-btn" data-rand-id="<?php echo $form->rand_id; ?>">
                                View Public
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Create/Edit Form Modal -->
    <div id="form-builder-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content" style="max-width: 900px;">
            <span class="bntm-modal-close">&times;</span>
            <h2 id="form-builder-title">Create New Form</h2>
            
            <form id="form-builder-form" class="bntm-form">
                <input type="hidden" id="form-id" name="form_id">
                
                <div class="bntm-form-group">
                    <label>Form Title *</label>
                    <input type="text" id="form-title" name="form_title" required placeholder="e.g., Contact Form, Registration Form">
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="form-description" name="form_description" rows="3" placeholder="Brief description of this form"></textarea>
                </div>
                
                <div class="bntm-form-group">
                    <label>Visibility</label>
                    <select id="form-visibility" name="form_visibility">
                        <option value="private">Private - Only you can see and use</option>
                        <option value="public">Public - Anyone with the link can submit</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Status</label>
                    <select id="form-status" name="form_status">
                        <option value="active">Active - Accepting submissions</option>
                        <option value="inactive">Inactive - Not accepting submissions</option>
                    </select>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3>Form Fields</h3>
                    <button type="button" id="add-field-btn" class="bntm-btn-secondary">
                        Add Field
                    </button>
                </div>
                
                <div id="form-fields-container">
                    <!-- Fields will be added here dynamically -->
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="bntm-btn-primary">Save Form</button>
                    <button type="button" class="bntm-btn-secondary" id="cancel-form-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Entries Modal -->
    <div id="entries-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content" style="max-width: 1200px;">
            <span class="bntm-modal-close">&times;</span>
            <h2 id="entries-modal-title">Form Entries</h2>
            
            <div style="margin-bottom: 15px;">
                <button id="export-entries-btn" class="bntm-btn-secondary">Export to CSV</button>
            </div>
            
            <div id="entries-table-container">
                <!-- Entries table will load here -->
            </div>
        </div>
    </div>
    
    <!-- Entry Details Modal -->
    <div id="entry-details-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content" style="max-width: 700px;">
            <span class="bntm-modal-close">&times;</span>
            <h2>Entry Details</h2>
            <div id="entry-details-content">
                <!-- Entry details will load here -->
            </div>
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
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    
    .bntm-stat-number {
        margin: 0;
        font-size: 32px;
        font-weight: 700;
        color: #059669;
    }
    
    .forms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    
    .form-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        transition: transform 0.2s, box-shadow 0.2s;
        overflow: hidden;
    }
    
    .form-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .form-card-header {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .form-card-header h4 {
        margin: 0;
        font-size: 18px;
        color: #111827;
    }
    
    .form-status {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .form-status-active {
        background: #d1fae5;
        color: #065f46;
    }
    
    .form-status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .form-card-body {
        padding: 20px;
    }
    
    .form-description {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    .form-meta {
        display: flex;
        gap: 15px;
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 10px;
    }
    
    .form-meta-date {
        font-size: 12px;
        color: #9ca3af;
    }
    
    .form-card-actions {
        padding: 15px 20px;
        background: #f9fafb;
        border-top: 1px solid #e5e7eb;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .bntm-btn-small {
        padding: 6px 12px;
        font-size: 13px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        background: #059669;
        color: white;
        transition: background 0.2s;
    }
    
    .bntm-btn-small:hover {
        background: #047857;
    }
    
    .bntm-btn-danger {
        background: #dc2626;
    }
    
    .bntm-btn-danger:hover {
        background: #b91c1c;
    }
    
    .bntm-modal {
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }
    
    .bntm-modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 8px;
        max-width: 600px;
        max-height: 85vh;
        overflow-y: auto;
    }
    
    .bntm-modal-close {
        color: #9ca3af;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 20px;
    }
    
    .bntm-modal-close:hover {
        color: #374151;
    }
    
    .field-builder {
        background: #f9fafb;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
        border: 1px solid #e5e7eb;
    }
    
    .field-builder-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .field-builder-header h4 {
        margin: 0;
        font-size: 14px;
        color: #374151;
    }
    
    .remove-field-btn {
        background: #dc2626;
        color: white;
        border: none;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .remove-field-btn:hover {
        background: #b91c1c;
    }
    
    .bntm-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .entries-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    .entries-table th,
    .entries-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .entries-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #374151;
    }
    
    .entries-table tr:hover {
        background: #f9fafb;
    }
    
    .entry-status-unread {
        font-weight: 600;
        background: #dbeafe;
    }
    
    .entry-details-grid {
        display: grid;
        gap: 15px;
    }
    
    .entry-detail-item {
        padding: 12px;
        background: #f9fafb;
        border-radius: 6px;
    }
    
    .entry-detail-label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 5px;
        font-size: 13px;
    }
    
    .entry-detail-value {
        color: #6b7280;
        font-size: 14px;
    }
    .owner-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        background: #dbeafe;
        color: #1e40af;
        margin-right: 8px;
    }
    
    .form-card-header > div {
        display: flex;
        align-items: center;
    }
    </style>
    
    <script>
    (function() {
        let currentFormId = null;
        let currentEntryFormId = null;
        let fieldCounter = 0;
        
        // Field types configuration
        const fieldTypes = {
            'text': 'Single Line Text',
            'textarea': 'Multi-line Text',
            'email': 'Email',
            'phone': 'Phone Number',
            'number': 'Number',
            'date': 'Date',
            'select': 'Dropdown',
            'radio': 'Radio Buttons',
            'checkbox': 'Checkboxes',
            'file': 'File Upload'
        };
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modals when clicking X or outside
        document.querySelectorAll('.bntm-modal-close').forEach(el => {
            el.addEventListener('click', function() {
                this.closest('.bntm-modal').style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('bntm-modal')) {
                e.target.style.display = 'none';
            }
        });
        
        // Create new form button
        document.getElementById('create-new-form-btn').addEventListener('click', function() {
            currentFormId = null;
            document.getElementById('form-builder-title').textContent = 'Create New Form';
            document.getElementById('form-builder-form').reset();
            document.getElementById('form-id').value = '';
            document.getElementById('form-fields-container').innerHTML = '';
            fieldCounter = 0;
            
            // Add default field
            addFormField({
                type: 'text',
                label: 'Name',
                required: true
            });
            
            openModal('form-builder-modal');
        });
        
        // Edit form buttons
        document.querySelectorAll('.edit-form-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const formId = this.dataset.formId;
                loadFormForEdit(formId);
            });
        });
        
        // Load form for editing
        function loadFormForEdit(formId) {
            const formData = new FormData();
            formData.append('action', 'forms_get_form');
            formData.append('form_id', formId);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const form = json.data.form;
                    currentFormId = form.id;
                    
                    document.getElementById('form-builder-title').textContent = 'Edit Form';
                    document.getElementById('form-id').value = form.id;
                    document.getElementById('form-title').value = form.title;
                    document.getElementById('form-description').value = form.description || '';
                    document.getElementById('form-visibility').value = form.visibility;
                    document.getElementById('form-status').value = form.status;
                    
                    // Load fields
                    const fields = JSON.parse(form.fields);
                    document.getElementById('form-fields-container').innerHTML = '';
                    fieldCounter = 0;
                    
                    fields.forEach(field => {
                        addFormField(field);
                    });
                    
                    openModal('form-builder-modal');
                } else {
                    alert(json.data.message);
                }
            });
        }
        
        // Add field button
        document.getElementById('add-field-btn').addEventListener('click', function() {
            addFormField({type: 'text', label: '', required: false});
        });
        
        // Add form field
        function addFormField(field) {
            const fieldId = 'field_' + fieldCounter++;
            const container = document.getElementById('form-fields-container');
            
            const fieldHtml = `
                <div class="field-builder" data-field-id="${fieldId}">
                    <div class="field-builder-header">
                        <h4>Field ${fieldCounter}</h4>
                        <button type="button" class="remove-field-btn" onclick="this.closest('.field-builder').remove()">Remove</button>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Field Type</label>
                            <select name="field_type[]" class="field-type-select" required>
                                ${Object.entries(fieldTypes).map(([key, label]) => 
                                    `<option value="${key}" ${field.type === key ? 'selected' : ''}>${label}</option>`
                                ).join('')}
                            </select>
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Field Label</label>
                            <input type="text" name="field_label[]" value="${field.label || ''}" required placeholder="e.g., Full Name">
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Placeholder (optional)</label>
                            <input type="text" name="field_placeholder[]" value="${field.placeholder || ''}" placeholder="e.g., Enter your name">
                        </div>
                        
                        <div class="bntm-form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="field_required[]" value="1" ${field.required ? 'checked' : ''}>
                                Required Field
                            </label>
                        </div>
                    </div>
                    
                    <div class="field-options-container" style="${['select', 'radio', 'checkbox'].includes(field.type) ? '' : 'display:none;'}">
                        <div class="bntm-form-group">
                            <label>Options (one per line)</label>
                            <textarea name="field_options[]" rows="3" placeholder="Option 1\nOption 2\nOption 3">${field.options ? field.options.join('\n') : ''}</textarea>
                            <small>For dropdown, radio, and checkbox fields</small>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', fieldHtml);
            
            // Add change listener for field type
            const lastField = container.lastElementChild;
            const typeSelect = lastField.querySelector('.field-type-select');
            const optionsContainer = lastField.querySelector('.field-options-container');
            
            typeSelect.addEventListener('change', function() {
                if (['select', 'radio', 'checkbox'].includes(this.value)) {
                    optionsContainer.style.display = 'block';
                } else {
                    optionsContainer.style.display = 'none';
                }
            });
        }
        
        // Cancel form button
        document.getElementById('cancel-form-btn').addEventListener('click', function() {
            closeModal('form-builder-modal');
        });
        
        // Submit form builder
        document.getElementById('form-builder-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Collect fields
            const fields = [];
            const fieldTypes = formData.getAll('field_type[]');
            const fieldLabels = formData.getAll('field_label[]');
            const fieldPlaceholders = formData.getAll('field_placeholder[]');
            const fieldRequired = formData.getAll('field_required[]');
            const fieldOptions = formData.getAll('field_options[]');
            
            fieldTypes.forEach((type, index) => {
                const field = {
                    type: type,
                    label: fieldLabels[index],
                    placeholder: fieldPlaceholders[index],
                    required: fieldRequired.includes('1'),
                };
                
                if (['select', 'radio', 'checkbox'].includes(type)) {
                    field.options = fieldOptions[index].split('\n').filter(o => o.trim());
                }
                
                fields.push(field);
            });
            
            const submitData = new FormData();
            submitData.append('action', currentFormId ? 'forms_update_form' : 'forms_create_form');
            if (currentFormId) submitData.append('form_id', currentFormId);
            submitData.append('title', formData.get('form_title'));
            submitData.append('description', formData.get('form_description'));
            submitData.append('visibility', formData.get('form_visibility'));
            submitData.append('status', formData.get('form_status'));
            submitData.append('fields', JSON.stringify(fields));
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: submitData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    closeModal('form-builder-modal');
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Save Form';
                }
            });
        });
        
        // Delete form
        document.querySelectorAll('.delete-form-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this form? All entries will also be deleted.')) return;
                
                const formId = this.dataset.formId;
                const formData = new FormData();
                formData.append('action', 'forms_delete_form');
                formData.append('form_id', formId);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
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
        
        // Duplicate form
        document.querySelectorAll('.duplicate-form-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const formId = this.dataset.formId;
                const formData = new FormData();
                formData.append('action', 'forms_duplicate_form');
                formData.append('form_id', formId);
                
                this.disabled = true;
                this.textContent = 'Duplicating...';
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.data.message);
                        this.disabled = false;
                        this.textContent = 'Duplicate';
                    }
                });
            });
        });
        
        // View entries
        document.querySelectorAll('.view-entries-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const formId = this.dataset.formId;
                loadFormEntries(formId);
            });
        });
        
        // Load form entries
        function loadFormEntries(formId) {
            currentEntryFormId = formId;
            
            const formData = new FormData();
            formData.append('action', 'forms_get_entries');
            formData.append('form_id', formId);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const data = json.data;
                    document.getElementById('entries-modal-title').textContent = `Entries: ${data.form_title}`;
                    
                    let html = '';
                    if (data.entries.length === 0) {
                        html = '<p style="text-align:center; padding:40px; color:#6b7280;">No entries yet</p>';
                    } else {
                        html = `
                            <table class="entries-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Status</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.entries.map(entry => `
                                        <tr class="${entry.status === 'unread' ? 'entry-status-unread' : ''}">
                                            <td>#${entry.id}</td>
                                            <td>${entry.status === 'unread' ? '🔵 Unread' : '✓ Read'}</td>
                                            <td>${entry.created_at}</td>
                                            <td>
                                                <button class="bntm-btn-small view-entry-btn" data-entry-id="${entry.id}">View</button>
                                                ${data.is_owner ? `
                                                    <button class="bntm-btn-small bntm-btn-danger delete-entry-btn" data-entry-id="${entry.id}">Delete</button>
                                                ` : ''}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    }
                    
                    document.getElementById('entries-table-container').innerHTML = html;
                    
                    // Also conditionally show/hide export button
                    if (data.is_owner) {
                        document.getElementById('export-entries-btn').style.display = 'inline-block';
                    } else {
                        document.getElementById('export-entries-btn').style.display = 'none';
                    }
                    
                    // Attach event listeners
                    document.querySelectorAll('.view-entry-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            viewEntryDetails(this.dataset.entryId);
                        });
                    });
                    
                    document.querySelectorAll('.delete-entry-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (!confirm('Delete this entry?')) return;
                            deleteEntry(this.dataset.entryId);
                        });
                    });
                    
                    openModal('entries-modal');
                }
            });
        }
        
        // View entry details
        function viewEntryDetails(entryId) {
            const formData = new FormData();
            formData.append('action', 'forms_get_entry_details');
            formData.append('entry_id', entryId);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const entry = json.data.entry;
                    const data = JSON.parse(entry.data);
                    
                    let html = `
                        <div style="margin-bottom: 20px; padding: 12px; background: #f9fafb; border-radius: 6px;">
                            <div><strong>Entry ID:</strong> #${entry.id}</div>
                            <div><strong>Submitted:</strong> ${entry.created_at}</div>
                            <div><strong>IP Address:</strong> ${entry.ip_address || 'N/A'}</div>
                        </div>
                        
                        <div class="entry-details-grid">
                    `;
                    
                    Object.entries(data).forEach(([key, value]) => {
                        html += `
                            <div class="entry-detail-item">
                                <div class="entry-detail-label">${key}</div>
                                <div class="entry-detail-value">${Array.isArray(value) ? value.join(', ') : value}</div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    
                    document.getElementById('entry-details-content').innerHTML = html;
                    openModal('entry-details-modal');
                    
                    // Mark as read
                    const markReadData = new FormData();
                    markReadData.append('action', 'forms_mark_entry_read');
                    markReadData.append('entry_id', entryId);
                    fetch(ajaxurl, {method: 'POST', body: markReadData});
                }
            });
        }
        
        // Delete entry
        function deleteEntry(entryId) {
            const formData = new FormData();
            formData.append('action', 'forms_delete_entry');
            formData.append('entry_id', entryId);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    loadFormEntries(currentEntryFormId);
                } else {
                    alert(json.data.message);
                }
            });
        }
        
        // Export entries
        document.getElementById('export-entries-btn').addEventListener('click', function() {
            if (!currentEntryFormId) return;
            
            window.location.href = ajaxurl + '?action=forms_export_entries&form_id=' + currentEntryFormId;
        });
        
        // View public form
        document.querySelectorAll('.view-public-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const randId = this.dataset.randId;
                const url = '<?php echo get_permalink(get_page_by_path("public-form")); ?>?form=' + randId;
                window.open(url, '_blank');
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Templates Tab
 */
function forms_templates_tab($business_id) {
    $templates = forms_get_default_templates();
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Form Templates</h3>
        <p>Start with a pre-built template and customize it to your needs</p>
        
        <div class="templates-grid">
            <?php foreach ($templates as $template): ?>
                <div class="template-card">
                    <div class="template-icon"><?php echo $template['icon']; ?></div>
                    <h4><?php echo esc_html($template['name']); ?></h4>
                    <p><?php echo esc_html($template['description']); ?></p>
                    <div class="template-fields">
                        <small><?php echo count($template['fields']); ?> fields included</small>
                    </div>
                    <button class="bntm-btn-primary use-template-btn" 
                            data-template='<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES); ?>'>
                        Use This Template
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .template-card {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
        transition: transform 0.2s;
    }
    
    .template-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .template-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }
    
    .template-card h4 {
        margin: 0 0 10px 0;
        color: #111827;
    }
    
    .template-card p {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    .template-fields {
        margin-bottom: 15px;
        color: #9ca3af;
        font-size: 13px;
    }
    </style>
    
    <script>
    (function() {
        document.querySelectorAll('.use-template-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const template = JSON.parse(this.dataset.template);
                
                const formData = new FormData();
                formData.append('action', 'forms_create_form');
                formData.append('title', template.name);
                formData.append('description', template.description);
                formData.append('visibility', 'private');
                formData.append('status', 'active');
                formData.append('fields', JSON.stringify(template.fields));
                
                this.disabled = true;
                this.textContent = 'Creating...';
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert('Template created successfully!');
                        window.location.href = '?tab=forms';
                    } else {
                        alert(json.data.message);
                        this.disabled = false;
                        this.textContent = 'Use This Template';
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
function forms_settings_tab($business_id) {
    $email_notifications = bntm_get_setting('forms_email_notifications', 'yes');
    $notification_email = bntm_get_setting('forms_notification_email', wp_get_current_user()->user_email);
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Form Settings</h3>
        
        <form id="forms-settings-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Email Notifications</label>
                <select name="email_notifications">
                    <option value="yes" <?php selected($email_notifications, 'yes'); ?>>Enable - Receive email for each submission</option>
                    <option value="no" <?php selected($email_notifications, 'no'); ?>>Disable - No email notifications</option>
                </select>
            </div>
            
            <div class="bntm-form-group">
                <label>Notification Email</label>
                <input type="email" name="notification_email" value="<?php echo esc_attr($notification_email); ?>" required>
                <small>Email address to receive form submission notifications</small>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Settings</button>
            <div id="settings-message"></div>
        </form>
    </div>
    
    <script>
    document.getElementById('forms-settings-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'forms_save_settings');
        
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
// PUBLIC FORM SHORTCODE
// ============================================================================

function bntm_shortcode_forms_public_form() {
    $form_rand_id = isset($_GET['form']) ? sanitize_text_field($_GET['form']) : '';
    
    if (empty($form_rand_id)) {
        return '<div class="bntm-notice">Invalid form link.</div>';
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE rand_id = %s AND visibility = 'public' AND status = 'active'",
        $form_rand_id
    ));
    
    if (!$form) {
        return '<div class="bntm-notice">Form not found or not available.</div>';
    }
    
    $fields = json_decode($form->fields, true);
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-public-form-container">
        <div class="bntm-public-form-header">
            <h1><?php echo esc_html($form->title); ?></h1>
            <?php if ($form->description): ?>
            <p class="form-description"><?php echo esc_html($form->description); ?></p>
            <?php endif; ?>
        </div>
        
        <form id="public-form-submit" class="bntm-public-form">
            <input type="hidden" name="form_rand_id" value="<?php echo esc_attr($form->rand_id); ?>">
            
            <?php foreach ($fields as $index => $field): ?>
                <div class="bntm-form-group">
                    <label>
                        <?php echo esc_html($field['label']); ?>
                        <?php if ($field['required']): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    
                    <?php
                    $fieldName = 'field_' . $index;
                    $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
                    $required = $field['required'] ? 'required' : '';
                    
                    switch ($field['type']) {
                        case 'textarea':
                            echo "<textarea name='$fieldName' placeholder='$placeholder' $required rows='4'></textarea>";
                            break;
                            
                        case 'select':
                            echo "<select name='$fieldName' $required>";
                            echo "<option value=''>-- Select --</option>";
                            foreach ($field['options'] as $option) {
                                echo "<option value='" . esc_attr($option) . "'>" . esc_html($option) . "</option>";
                            }
                            echo "</select>";
                            break;
                            
                        case 'radio':
                            foreach ($field['options'] as $option) {
                                echo "<label class='radio-label'>";
                                echo "<input type='radio' name='$fieldName' value='" . esc_attr($option) . "' $required>";
                                echo " " . esc_html($option);
                                echo "</label>";
                            }
                            break;
                            
                        case 'checkbox':
                            foreach ($field['options'] as $option) {
                                echo "<label class='checkbox-label'>";
                                echo "<input type='checkbox' name='{$fieldName}[]' value='" . esc_attr($option) . "'>";
                                echo " " . esc_html($option);
                                echo "</label>";
                            }
                            break;
                            
                        case 'file':
                            echo "<input type='file' name='$fieldName' $required>";
                            break;
                            
                        default:
                            echo "<input type='{$field['type']}' name='$fieldName' placeholder='$placeholder' $required>";
                    }
                    ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="bntm-btn-primary bntm-btn-large">Submit</button>
            <div id="form-message"></div>
        </form>
        
        <div id="success-message" style="display: none;">
            <div class="success-checkmark">✓</div>
            <h2>Thank You!</h2>
            <p>Your submission has been received successfully.</p>
        </div>
    </div>
    
    <style>
    .bntm-public-form-container {
        max-width: 700px;
        margin: 40px auto;
        padding: 40px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .bntm-public-form-header {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .bntm-public-form-header h1 {
        margin: 0 0 10px 0;
        color: #111827;
        font-size: 32px;
    }
    
    .form-description {
        color: #6b7280;
        font-size: 16px;
        margin: 0;
    }
    
    .bntm-public-form .bntm-form-group {
        margin-bottom: 25px;
    }
    
    .bntm-public-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
    }
    
    .required {
        color: #dc2626;
    }
    
    .bntm-public-form input[type="text"],
    .bntm-public-form input[type="email"],
    .bntm-public-form input[type="phone"],
    .bntm-public-form input[type="number"],
    .bntm-public-form input[type="date"],
    .bntm-public-form textarea,
    .bntm-public-form select {
        width: 100%;
        padding: 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 15px;
        transition: border-color 0.2s;
    }
    
    .bntm-public-form input:focus,
    .bntm-public-form textarea:focus,
    .bntm-public-form select:focus {
        outline: none;
        border-color: #059669;
        box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }
    
    .radio-label,
    .checkbox-label {
        display: block;
        padding: 8px 0;
        font-weight: normal !important;
        cursor: pointer;
    }
    
    .radio-label input,
    .checkbox-label input {
        margin-right: 8px;
    }
    
    .bntm-btn-large {
        width: 100%;
        padding: 15px;
        font-size: 16px;
        font-weight: 600;
    }
    
    #success-message {
        text-align: center;
        padding: 60px 20px;
    }
    
    .success-checkmark {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: #059669;
        color: white;
        font-size: 48px;
        line-height: 80px;
        border-radius: 50%;
    }
    
    #success-message h2 {
        color: #111827;
        margin-bottom: 10px;
    }
    
    #success-message p {
        color: #6b7280;
        font-size: 16px;
    }
    </style>
    
    <script>
    document.getElementById('public-form-submit').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'forms_submit_entry');
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Submitting...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                document.querySelector('.bntm-public-form').style.display = 'none';
                document.getElementById('success-message').style.display = 'block';
            } else {
                document.getElementById('form-message').innerHTML = 
                    '<div class="bntm-notice bntm-notice-error" style="margin-top:15px;">' + 
                    json.data.message + '</div>';
                btn.disabled = false;
                btn.textContent = 'Submit';
            }
        })
        .catch(err => {
            document.getElementById('form-message').innerHTML = 
                '<div class="bntm-notice bntm-notice-error" style="margin-top:15px;">An error occurred. Please try again.</div>';
            btn.disabled = false;
            btn.textContent = 'Submit';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

/**
 * Create form
 */
function bntm_ajax_forms_create_form() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $visibility = sanitize_text_field($_POST['visibility']);
    $status = sanitize_text_field($_POST['status']);
    $fields = stripslashes($_POST['fields']);
    
    // Validate
    if (empty($title)) {
        wp_send_json_error(['message' => 'Form title is required']);
    }
    
    $business_id = get_current_user_id();
    
    $result = $wpdb->insert($forms_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'title' => $title,
        'description' => $description,
        'fields' => $fields,
        'visibility' => $visibility,
        'status' => $status,
        'entry_count' => 0
    ], ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Form created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create form']);
    }
}

/**
 * Update form
 */
function bntm_ajax_forms_update_form() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $form_id = intval($_POST['form_id']);
    $title = sanitize_text_field($_POST['title']);
    $description = sanitize_textarea_field($_POST['description']);
    $visibility = sanitize_text_field($_POST['visibility']);
    $status = sanitize_text_field($_POST['status']);
    $fields = stripslashes($_POST['fields']);
    
    $business_id = get_current_user_id();
    
    // Check ownership
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d AND business_id = %d",
        $form_id, $business_id
    ));
    
    if (!$form) {
        wp_send_json_error(['message' => 'Form not found']);
    }
    
    $result = $wpdb->update($forms_table, [
        'title' => $title,
        'description' => $description,
        'fields' => $fields,
        'visibility' => $visibility,
        'status' => $status
    ], ['id' => $form_id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Form updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update form']);
    }
}

/**
 * Delete form
 */
function bntm_ajax_forms_delete_form() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    $entries_table = $wpdb->prefix . 'forms_entries';
    
    $form_id = intval($_POST['form_id']);
    $business_id = get_current_user_id();
    
    // Check ownership
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d AND business_id = %d",
        $form_id, $business_id
    ));
    
    if (!$form) {
        wp_send_json_error(['message' => 'Form not found']);
    }
    
    // Delete entries first
    $wpdb->delete($entries_table, ['form_id' => $form_id], ['%d']);
    
    // Delete form
    $result = $wpdb->delete($forms_table, ['id' => $form_id], ['%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Form deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete form']);
    }
}

/**
 * Get form
 */
function bntm_ajax_forms_get_form() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $form_id = intval($_POST['form_id']);
    $business_id = get_current_user_id();
    
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d AND business_id = %d",
        $form_id, $business_id
    ));
    
    if ($form) {
        wp_send_json_success(['form' => $form]);
    } else {
        wp_send_json_error(['message' => 'Form not found']);
    }
}

/**
 * Duplicate form
 */
function bntm_ajax_forms_duplicate_form() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $form_id = intval($_POST['form_id']);
    $business_id = get_current_user_id();
    
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d AND business_id = %d",
        $form_id, $business_id
    ));
    
    if (!$form) {
        wp_send_json_error(['message' => 'Form not found']);
    }
    
    $result = $wpdb->insert($forms_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'title' => $form->title . ' (Copy)',
        'description' => $form->description,
        'fields' => $form->fields,
        'visibility' => 'private',
        'status' => 'inactive',
        'entry_count' => 0
    ], ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d']);
    
    if ($result) {
        wp_send_json_success(['message' => 'Form duplicated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to duplicate form']);
    }
}
/**
 * Get form entries
 */
function bntm_ajax_forms_get_entries() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    $entries_table = $wpdb->prefix . 'forms_entries';
    
    $form_id = intval($_POST['form_id']);
    $business_id = get_current_user_id();
    
    // Get form (no ownership check - anyone can view)
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d",
        $form_id
    ));
    
    if (!$form) {
        wp_send_json_error(['message' => 'Form not found']);
    }
    
    // Check if current user is the owner
    $is_owner = ($form->business_id == $business_id);
    
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $entries_table WHERE form_id = %d ORDER BY created_at DESC",
        $form_id
    ));
    
    // Format dates
    foreach ($entries as &$entry) {
        $entry->created_at = date('M d, Y g:i A', strtotime($entry->created_at));
    }
    
    wp_send_json_success([
        'form_title' => $form->title,
        'entries' => $entries,
        'is_owner' => $is_owner
    ]);
}
/**
 * Get entry details
 */
function bntm_ajax_forms_get_entry_details() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $entries_table = $wpdb->prefix . 'forms_entries';
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $entry_id = intval($_POST['entry_id']);
    
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, f.business_id FROM $entries_table e
         JOIN $forms_table f ON e.form_id = f.id
         WHERE e.id = %d",
        $entry_id
    ));
    
    if (!$entry) {
        wp_send_json_error(['message' => 'Entry not found']);
    }
    
    // Anyone can view, but note ownership for future use
    $is_owner = ($entry->business_id == get_current_user_id());
    
    $entry->created_at = date('M d, Y g:i A', strtotime($entry->created_at));
    
    wp_send_json_success([
        'entry' => $entry,
        'is_owner' => $is_owner
    ]);
}

/**
 * Mark entry as read
 */
function bntm_ajax_forms_mark_entry_read() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $entries_table = $wpdb->prefix . 'forms_entries';
    
    $entry_id = intval($_POST['entry_id']);
    
    $wpdb->update($entries_table, ['status' => 'read'], ['id' => $entry_id], ['%s'], ['%d']);
    
    wp_send_json_success(['message' => 'Entry marked as read']);
}

/**
 * Delete entry
 */
function bntm_ajax_forms_delete_entry() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $entries_table = $wpdb->prefix . 'forms_entries';
    $forms_table = $wpdb->prefix . 'forms_forms';
    
    $entry_id = intval($_POST['entry_id']);
    
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT e.*, f.business_id FROM $entries_table e
         JOIN $forms_table f ON e.form_id = f.id
         WHERE e.id = %d",
        $entry_id
    ));
    
    if (!$entry) {
        wp_send_json_error(['message' => 'Entry not found']);
    }
    
    // Check ownership
    if ($entry->business_id != get_current_user_id()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $result = $wpdb->delete($entries_table, ['id' => $entry_id], ['%d']);
    
    if ($result) {
        // Update entry count
        $wpdb->query($wpdb->prepare(
            "UPDATE $forms_table SET entry_count = entry_count - 1 WHERE id = %d",
            $entry->form_id
        ));
        
        wp_send_json_success(['message' => 'Entry deleted']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete entry']);
    }
}

/**
 * Submit form entry (Public)
 */
function bntm_ajax_forms_submit_entry() {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    $entries_table = $wpdb->prefix . 'forms_entries';
    
    $form_rand_id = sanitize_text_field($_POST['form_rand_id']);
    
    // Get form
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE rand_id = %s AND visibility = 'public' AND status = 'active'",
        $form_rand_id
    ));
    
    if (!$form) {
        wp_send_json_error(['message' => 'Form not available']);
    }
    
    $fields = json_decode($form->fields, true);
    
    // Collect submitted data
    $submitted_data = [];
    foreach ($fields as $index => $field) {
        $field_name = 'field_' . $index;
        
        if ($field['type'] === 'checkbox') {
            $submitted_data[$field['label']] = isset($_POST[$field_name]) ? $_POST[$field_name] : [];
        } else {
            $value = isset($_POST[$field_name]) ? sanitize_text_field($_POST[$field_name]) : '';
            
            // Validate required fields
            if ($field['required'] && empty($value)) {
                wp_send_json_error(['message' => $field['label'] . ' is required']);
            }
            
            $submitted_data[$field['label']] = $value;
        }
    }
    
    // Get IP and user agent
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // Insert entry
    $result = $wpdb->insert($entries_table, [
        'rand_id' => bntm_rand_id(),
        'form_id' => $form->id,
        'form_rand_id' => $form->rand_id,
        'business_id' => $form->business_id,
        'data' => json_encode($submitted_data),
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'status' => 'unread'
    ], ['%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s']);
    
    if ($result) {
        // Update entry count
        $wpdb->query($wpdb->prepare(
            "UPDATE $forms_table SET entry_count = entry_count + 1 WHERE id = %d",
            $form->id
        ));
        
        // Send email notification
        forms_send_notification_email($form, $submitted_data);
        
        wp_send_json_success(['message' => 'Form submitted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to submit form']);
    }
}

/**
 * Export entries to CSV
 */
function bntm_ajax_forms_export_entries() {
    if (!is_user_logged_in()) {
        wp_die('Unauthorized');
    }
    
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    $entries_table = $wpdb->prefix . 'forms_entries';
    
    $form_id = intval($_GET['form_id']);
    $business_id = get_current_user_id();
    
    // Check ownership
    $form = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $forms_table WHERE id = %d AND business_id = %d",
        $form_id, $business_id
    ));
    
    if (!$form) {
        wp_die('Form not found');
    }
    
    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $entries_table WHERE form_id = %d ORDER BY created_at DESC",
        $form_id
    ));
    
    if (empty($entries)) {
        wp_die('No entries to export');
    }
    
    // Prepare CSV
    $filename = sanitize_file_name($form->title) . '_entries_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Get headers from first entry
    $first_entry_data = json_decode($entries[0]->data, true);
    $headers = array_merge(['Entry ID', 'Submitted Date', 'Status', 'IP Address'], array_keys($first_entry_data));
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($entries as $entry) {
        $data = json_decode($entry->data, true);
        $row = [
            $entry->id,
            date('Y-m-d H:i:s', strtotime($entry->created_at)),
            $entry->status,
            $entry->ip_address
        ];
        
        foreach ($first_entry_data as $key => $val) {
            $value = isset($data[$key]) ? $data[$key] : '';
            $row[] = is_array($value) ? implode(', ', $value) : $value;
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get forms statistics
 */
function forms_get_stats($business_id) {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'forms_forms';
    $entries_table = $wpdb->prefix . 'forms_entries';
    
    // All forms
    $total_forms = $wpdb->get_var("SELECT COUNT(*) FROM $forms_table");
    
    // Personal forms
    $my_forms = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $forms_table WHERE business_id = %d",
        $business_id
    ));
    
    $my_active_forms = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $forms_table WHERE business_id = %d AND status = 'active'",
        $business_id
    ));
    
    $my_entries = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $entries_table WHERE business_id = %d",
        $business_id
    ));
    
    $my_unread = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $entries_table WHERE business_id = %d AND status = 'unread'",
        $business_id
    ));
    
    return [
        'total_forms' => $total_forms ?: 0,
        'my_forms' => $my_forms ?: 0,
        'active_forms' => $my_active_forms ?: 0,
        'total_entries' => $my_entries ?: 0,
        'unread_entries' => $my_unread ?: 0,
    ];
}
/**
 * Get default form templates
 */
function forms_get_default_templates() {
    return [
        [
            'name' => 'Contact Form',
            'icon' => '📧',
            'description' => 'Simple contact form with name, email, and message fields',
            'fields' => [
                [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'placeholder' => 'Enter your full name',
                    'required' => true
                ],
                [
                    'type' => 'email',
                    'label' => 'Email Address',
                    'placeholder' => 'your@email.com',
                    'required' => true
                ],
                [
                    'type' => 'phone',
                    'label' => 'Phone Number',
                    'placeholder' => '+63 XXX XXX XXXX',
                    'required' => false
                ],
                [
                    'type' => 'textarea',
                    'label' => 'Message',
                    'placeholder' => 'How can we help you?',
                    'required' => true
                ]
            ]
        ],
        [
            'name' => 'Event Registration',
            'icon' => '🎟️',
            'description' => 'Registration form for events with attendee information',
            'fields' => [
                [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'placeholder' => 'Enter your full name',
                    'required' => true
                ],
                [
                    'type' => 'email',
                    'label' => 'Email Address',
                    'placeholder' => 'your@email.com',
                    'required' => true
                ],
                [
                    'type' => 'phone',
                    'label' => 'Phone Number',
                    'placeholder' => '+63 XXX XXX XXXX',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => 'Company/Organization',
                    'placeholder' => 'Your company name',
                    'required' => false
                ],
                [
                    'type' => 'select',
                    'label' => 'Ticket Type',
                    'required' => true,
                    'options' => ['General Admission', 'VIP', 'Student', 'Group (5+)']
                ],
                [
                    'type' => 'checkbox',
                    'label' => 'Dietary Restrictions',
                    'required' => false,
                    'options' => ['Vegetarian', 'Vegan', 'Gluten-free', 'Halal', 'None']
                ],
                [
                    'type' => 'textarea',
                    'label' => 'Special Requirements',
                    'placeholder' => 'Any special needs or requests?',
                    'required' => false
                ]
            ]
        ],
        [
            'name' => 'Job Application',
            'icon' => '💼',
            'description' => 'Comprehensive job application form with work experience',
            'fields' => [
                [
                    'type' => 'text',
                    'label' => 'Full Name',
                    'placeholder' => 'Enter your full name',
                    'required' => true
                ],
                [
                    'type' => 'email',
                    'label' => 'Email Address',
                    'placeholder' => 'your@email.com',
                    'required' => true
                ],
                [
                    'type' => 'phone',
                    'label' => 'Phone Number',
                    'placeholder' => '+63 XXX XXX XXXX',
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => 'Position Applying For',
                    'placeholder' => 'e.g., Software Developer',
                    'required' => true
                ],
                [
                    'type' => 'select',
                    'label' => 'Years of Experience',
                    'required' => true,
                    'options' => ['0-1 years', '1-3 years', '3-5 years', '5-10 years', '10+ years']
                ],
                [
                    'type' => 'select',
                    'label' => 'Highest Education',
                    'required' => true,
                    'options' => ['High School', 'Associate Degree', 'Bachelor Degree', 'Master Degree', 'Doctorate']
                ],
                [
                    'type' => 'textarea',
                    'label' => 'Work Experience',
                    'placeholder' => 'Briefly describe your relevant work experience',
                    'required' => true
                ],
                [
                    'type' => 'textarea',
                    'label' => 'Why do you want to work with us?',
                    'placeholder' => 'Tell us what interests you about this position',
                    'required' => true
                ],
                [
                    'type' => 'date',
                    'label' => 'Available Start Date',
                    'required' => true
                ]
            ]
        ]
    ];
}

/**
 * Send notification email for new entry
 */
function forms_send_notification_email($form, $submitted_data) {
    $email_notifications = bntm_get_setting('forms_email_notifications', 'yes');
    
    if ($email_notifications !== 'yes') {
        return;
    }
    
    $notification_email = bntm_get_setting('forms_notification_email', wp_get_current_user()->user_email);
    
    if (empty($notification_email)) {
        return;
    }
    
    $subject = 'New Form Submission: ' . $form->title;
    
    $message = "You have received a new submission for the form: {$form->title}\n\n";
    $message .= "Submission Details:\n";
    $message .= "==================\n\n";
    
    foreach ($submitted_data as $label => $value) {
        $display_value = is_array($value) ? implode(', ', $value) : $value;
        $message .= "$label: $display_value\n";
    }
    
    $message .= "\n==================\n";
    $message .= "Submitted: " . date('F d, Y g:i A') . "\n";
    
    wp_mail($notification_email, $subject, $message);
}

/**
 * Save forms settings
 */
add_action('wp_ajax_forms_save_settings', 'bntm_ajax_forms_save_settings');
function bntm_ajax_forms_save_settings() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $email_notifications = sanitize_text_field($_POST['email_notifications']);
    $notification_email = sanitize_email($_POST['notification_email']);
    
    bntm_set_setting('forms_email_notifications', $email_notifications);
    bntm_set_setting('forms_notification_email', $notification_email);
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}