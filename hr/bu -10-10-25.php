<?php
/**
 * Module Name: Human Resources Management
 * Module Slug: hr
 * Description: Complete HR management solution with employee records, attendance tracking, and leave management
 * Version: 1.0.0
 * Author: Your Name
 * Icon: 👥
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_HR_PATH', dirname(__FILE__) . '/');
define('BNTM_HR_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_hr_get_pages() {
    return [
        'HR Portal' => '[hr_dashboard]',
        'HR Kiosk' => '[hr_kiosk]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_hr_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'hr_attendance' => "CREATE TABLE {$prefix}hr_attendance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            clock_in DATETIME NOT NULL,
            clock_out DATETIME NULL,
            total_hours DECIMAL(5,2) NULL,
            notes TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_business (business_id),
            INDEX idx_date (clock_in)
        ) {$charset};",
        
        'hr_leave_requests' => "CREATE TABLE {$prefix}hr_leave_requests (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            leave_type VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            total_days INT NOT NULL,
            reason TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_status (status),
            INDEX idx_dates (start_date, end_date)
        ) {$charset};",
        'hr_payslips' => "CREATE TABLE {$prefix}hr_payslips (
             id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
             rand_id VARCHAR(20) UNIQUE NOT NULL,
             employee_id BIGINT UNSIGNED NOT NULL,
             business_id BIGINT UNSIGNED NOT NULL,
             period_start DATE NOT NULL,
             period_end DATE NOT NULL,
             basic_pay DECIMAL(10,2) NOT NULL,
             overtime_pay DECIMAL(10,2) DEFAULT 0,
             total_deductions DECIMAL(10,2) DEFAULT 0,
             net_pay DECIMAL(10,2) NOT NULL,
             deductions_data TEXT,
             is_imported TINYINT(1) DEFAULT 0,
             created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
             INDEX idx_employee (employee_id),
             INDEX idx_period (period_start, period_end),
             INDEX idx_imported (is_imported)
         ) {$wpdb->get_charset_collate()};",
        
        'hr_employee_meta' => "CREATE TABLE {$prefix}hr_employee_meta (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            employee_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(100) NOT NULL,
            meta_value LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee (employee_id),
            INDEX idx_meta_key (meta_key)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_hr_get_shortcodes() {
    return [
        'hr_dashboard' => 'bntm_shortcode_hr',
        'hr_kiosk' => 'bntm_shortcode_hr_kiosk'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_hr_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_hr_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}


/* ---------- SHORTCODE HANDLERS ---------- */

function bntm_shortcode_hr() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to access the HR Portal.</p>';
    }

    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'dashboard';
    $user_id = get_current_user_id();
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_manage = $is_wp_admin || in_array($current_role, ['owner', 'manager']);

    ob_start();
    
    $tabs = [
       'dashboard' => 'Dashboard',
       'employees' => 'Employees',
       'attendance' => 'Attendance',
       'leaves' => 'Leave Requests',
       'payslips' => 'Payslips',  // NEW
       'settings' => 'Settings'
   ];
   
   if (!$can_manage) {
       $tabs = [
           'dashboard' => 'My Dashboard',
           'attendance' => 'My Attendance',
           'leaves' => 'My Leaves',
           'payslips' => 'My Payslips'  // NEW
       ];
   }
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <div class="bntm-tabs">
        <?php foreach ($tabs as $key => $label): 
            $active = ($type === $key) ? 'active' : '';
            $url = add_query_arg('type', $key, get_permalink());
        ?>
            <a href="<?php echo esc_url($url); ?>" class="bntm-tab <?php echo $active; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <?php
    switch ($type) {
        case 'employees':
            echo bntm_hr_employees_view($can_manage);
            break;
        case 'attendance':
            echo bntm_hr_attendance_view($user_id, $can_manage);
            break;
        case 'leaves':
            echo bntm_hr_leaves_view($user_id, $can_manage);
            break;
        case 'settings':
            echo bntm_hr_settings_view($can_manage);
            break;
             case 'payslips':
           echo bntm_hr_payslips_view($user_id, $can_manage);
           break;
        default:
            echo bntm_hr_dashboard_view($user_id, $can_manage);
            break;
    }

    $content = ob_get_clean();
    return bntm_universal_container('HR Portal', $content);
}


function bntm_shortcode_hr_kiosk() {
   $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        return '<p>You do not have permission to access this page.</p>';
    }
    ob_start();
    ?>
    
    <div class="bntm-kiosk-container" style="max-width: 500px; margin: 50px auto; text-align: center;">
        <h2>Employee Clock In/Out</h2>
        
        <div class="bntm-form-section">
            <div class="bntm-form-group">
                <label>Employee ID or PIN</label>
                <input type="text" id="kiosk-employee-id" placeholder="Enter your ID or PIN" style="font-size: 18px; text-align: center;" />
            </div>
            
            <div class="bntm-form-group">
                <button id="kiosk-clock-in" class="bntm-btn-primary" style="width: 45%; margin-right: 5%;">Clock In</button>
                <button id="kiosk-clock-out" class="bntm-btn-secondary" style="width: 45%;">Clock Out</button>
            </div>
            
            <div id="kiosk-message"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('bntm_hr_kiosk_nonce'); ?>'
        };
        
        async function handleKioskAction(actionType) {
            const employeeIdInput = $('#kiosk-employee-id');
            const messageDiv = $('#kiosk-message');
            const employeeId = employeeIdInput.val().trim();
            
            if (!employeeId) {
                messageDiv.html('<p style="color: red;">Please enter your Employee ID or PIN.</p>');
                return;
            }
            
            try {
                const response = await $.ajax({
                    url: bntmAjax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'bntm_hr_kiosk_clock',
                        nonce: bntmAjax.nonce,
                        employee_id: employeeId,
                        action_type: actionType
                    }
                });
                
                if (response.success) {
                    messageDiv.html('<p style="color: green; font-size: 18px; font-weight: bold;">' + response.data.message + '</p>');
                    employeeIdInput.val('');
                    setTimeout(function() { messageDiv.html(''); }, 5000);
                } else {
                    messageDiv.html('<p style="color: red; font-size: 16px;">' + response.data.message + '</p>');
                }
            } catch (error) {
                console.error('Error:', error);
                messageDiv.html('<p style="color: red;">An error occurred. Please try again.</p>');
            }
        }
        
        $('#kiosk-clock-in').on('click', function() {
            handleKioskAction('in');
        });
        
        $('#kiosk-clock-out').on('click', function() {
            handleKioskAction('out');
        });
        
        $('#kiosk-employee-id').on('keypress', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                handleKioskAction('in');
            }
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}

function bntm_ajax_hr_kiosk_clock() {
    check_ajax_referer('bntm_hr_kiosk_nonce', 'nonce');
    
    $employee_id = sanitize_text_field($_POST['employee_id']);
    $action_type = sanitize_text_field($_POST['action_type']);
    
    global $wpdb;
    
    $user = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'bntm_hr_pin' AND meta_value = %s",
        $employee_id
    ));
    
    if (!$user) {
        $user_obj = get_user_by('id', intval($employee_id));
        if (!$user_obj) {
            wp_send_json_error(['message' => 'Employee not found.']);
        }
        $user = $user_obj->ID;
    }
    
    $prefix = $wpdb->prefix;
    
    if ($action_type === 'in') {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL",
            $user,
            current_time('Y-m-d')
        ));
        
        if ($existing) {
            wp_send_json_error(['message' => 'Already clocked in today.']);
        }
        
        $result = $wpdb->insert(
            $prefix . 'hr_attendance',
            [
                'rand_id' => bntm_rand_id(),
                'employee_id' => $user,
                'business_id' => 1,
                'clock_in' => current_time('mysql'),
                'status' => 'active'
            ],
            ['%s', '%d', '%d', '%s', '%s']
        );
        
        if ($result) {
            $user_data = get_userdata($user);
            wp_send_json_success(['message' => 'Welcome ' . $user_data->display_name . '! Clocked in at ' . current_time('h:i A')]);
        } else {
            wp_send_json_error(['message' => 'Failed to clock in.']);
        }
        
    } else {
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
            $user,
            current_time('Y-m-d')
        ));
        
        if (!$record) {
            wp_send_json_error(['message' => 'No active clock in record found.']);
        }
        
        $clock_out = current_time('mysql');
        $clock_in = strtotime($record->clock_in);
        $clock_out_time = strtotime($clock_out);
        $total_hours = round(($clock_out_time - $clock_in) / 3600, 2);
        
        $result = $wpdb->update(
            $prefix . 'hr_attendance',
            [
                'clock_out' => $clock_out,
                'total_hours' => $total_hours,
                'status' => 'completed'
            ],
            ['id' => $record->id],
            ['%s', '%f', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            $user_data = get_userdata($user);
            wp_send_json_success(['message' => 'Goodbye ' . $user_data->display_name . '! Clocked out at ' . current_time('h:i A') . '. Total: ' . $total_hours . ' hrs']);
        } else {
            wp_send_json_error(['message' => 'Failed to clock out.']);
        }
    }
}

function bntm_hr_settings_view($can_manage) {
    if (!$can_manage) {
        return '<p>You do not have permission to view this page.</p>';
    }
    
    ob_start();
    
    $custom_roles = bntm_get_setting('hr_custom_roles', '');
    $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    $leave_types = bntm_get_setting('hr_leave_types', 'Sick Leave,Vacation,Personal,Bereavement');
    $work_hours = bntm_get_setting('hr_work_hours', '8');
    $hourly_rate = bntm_get_setting('hr_hourly_rate', '15.00');
    
    // Get available modules
    $available_modules = bntm_get_available_modules();
    ?>
    
    <div class="bntm-form-section">
        <h3>HR Settings</h3>
        
    </div>
    
    <div class="bntm-form-section">
        <h3>Employee Roles & Module Access</h3>
        <p>Configure custom employee roles and their module permissions</p>
        
        <div id="roles-list">
            <?php if ($roles_array): ?>
                <?php foreach ($roles_array as $key => $role): ?>
                    <div class="role-item" style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <div>
                                <strong><?php echo esc_html($role['label']); ?></strong> 
                                <span style="color: #6b7280;">(<?php echo esc_html($key); ?>)</span>
                            </div>
                            <button class="bntm-btn-small bntm-btn-danger delete-role" data-key="<?php echo esc_attr($key); ?>">Delete</button>
                        </div>
                        <div style="font-size: 13px; color: #6b7280;">
                            <strong>Accessible Modules:</strong>
                            <?php 
                            $modules = isset($role['modules']) ? $role['modules'] : [];
                            if (!empty($modules)) {
                                $module_names = [];
                                foreach ($modules as $module_slug) {
                                    if (isset($available_modules[$module_slug])) {
                                        $module_names[] = $available_modules[$module_slug]['name'];
                                    }
                                }
                                echo esc_html(implode(', ', $module_names));
                            } else {
                                echo '<span style="color: #ef4444;">No modules assigned</span>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: #6b7280;">No custom roles defined yet.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
            <h4>Add New Role</h4>
            
            <div class="bntm-form-group">
                <label>Role Key (lowercase, no spaces)</label>
                <input type="text" id="new-role-key" placeholder="e.g., supervisor" />
            </div>
            
            <div class="bntm-form-group">
                <label>Role Label</label>
                <input type="text" id="new-role-label" placeholder="e.g., Supervisor" />
            </div>
            
            <div class="bntm-form-group">
                <label>Module Access</label>
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 10px;">
                    Select which modules this role can access:
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">
                    <?php foreach ($available_modules as $module_slug => $module_data): ?>
                        <label style="display: flex; align-items: center; padding: 8px; background: #f9fafb; border-radius: 4px; cursor: pointer;">
                            <input type="checkbox" class="role-module" value="<?php echo esc_attr($module_slug); ?>" style="margin-right: 8px;width:unset;" />
                            <span><?php echo esc_html($module_data['name']); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button id="add-role-btn" class="bntm-btn-primary">Add Role</button>
            <div id="role-message"></div>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h4>Leave Types</h4>
        <div class="bntm-form-group">
            <label>Available Leave Types (comma-separated)</label>
            <input type="text" id="hr-leave-types" value="<?php echo esc_attr($leave_types); ?>" />
            <button id="save-leave-types" class="bntm-btn-primary" style="margin-top: 10px;">Save Leave Types</button>
            <div id="leave-types-message"></div>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h4>Payroll Deductions</h4>
        <p>Configure standard deductions for payslip calculations</p>
        
        <div id="deductions-list">
            <?php
            $deductions = bntm_get_setting('hr_deductions', '');
            $deductions_array = $deductions ? json_decode($deductions, true) : [];
            
            if ($deductions_array):
                foreach ($deductions_array as $key => $deduction):
            ?>
                <div class="role-item" style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong><?php echo esc_html($deduction['name']); ?></strong>
                        <div style="font-size: 13px; color: #6b7280; margin-top: 5px;">
                            Type: <?php echo esc_html(ucfirst($deduction['type'])); ?> | 
                            <?php if ($deduction['type'] === 'percentage'): ?>
                                Rate: <?php echo esc_html($deduction['value']); ?>%
                            <?php else: ?>
                                Amount: ₱<?php echo esc_html(number_format($deduction['value'], 2)); ?>
                            <?php endif; ?>
                            <?php if (!empty($deduction['description'])): ?>
                                | <?php echo esc_html($deduction['description']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="bntm-btn-small bntm-btn-danger delete-deduction" data-key="<?php echo esc_attr($key); ?>">Delete</button>
                </div>
            <?php
                endforeach;
            else:
            ?>
                <p style="color: #6b7280;">No deductions configured yet.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
            <h4>Add New Deduction</h4>
            
            <div class="bntm-form-group">
                <label>Deduction Name *</label>
                <input type="text" id="deduction-name" placeholder="e.g., SSS, PhilHealth, Pag-IBIG, Tax" />
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Type *</label>
                    <select id="deduction-type">
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (₱)</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Value *</label>
                    <input type="number" id="deduction-value" step="0.01" placeholder="e.g., 3.63 or 100.00" />
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Description (Optional)</label>
                <input type="text" id="deduction-description" placeholder="e.g., Mandatory government contribution" />
            </div>
            
            <button id="add-deduction-btn" class="bntm-btn-primary">Add Deduction</button>
            <div id="deduction-message"></div>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h4>Working Hours Configuration</h4>
        <div class="bntm-form-row">
            <div class="bntm-form-group">
                <label>Standard Work Hours per Day</label>
                <input type="number" id="hr-work-hours" value="<?php echo esc_attr($work_hours); ?>" step="0.5" />
            </div>
            <div class="bntm-form-group">
                <label>Hourly Rate (Default)</label>
                <input type="number" id="hr-hourly-rate" value="<?php echo esc_attr($hourly_rate); ?>" step="0.01" />
            </div>
        </div>
        <button id="save-work-config" class="bntm-btn-primary">Save Configuration</button>
        <div id="work-config-message"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        const bntmAjax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
        };
        
        
        
        // Add Role
        $('#add-role-btn').on('click', function(e) {
            e.preventDefault();
            const roleKey = $('#new-role-key').val().trim();
            const roleLabel = $('#new-role-label').val().trim();
            const modules = [];
            
            $('.role-module:checked').each(function() {
                modules.push($(this).val());
            });
            
            if (!roleKey || !roleLabel) {
                $('#role-message').html('<p style="color: red;">Role key and label are required.</p>');
                return;
            }
            
            if (modules.length === 0) {
                $('#role-message').html('<p style="color: red;">Please select at least one module.</p>');
                return;
            }
            
            const $btn = $(this);
            $btn.prop('disabled', true).text('Adding...');
            
            $.post(bntmAjax.ajax_url, {
                action: 'bntm_hr_add_role',
                nonce: bntmAjax.nonce,
                role_key: roleKey,
                role_label: roleLabel,
                modules: modules
            })
            .done(function(response) {
                const messageDiv = $('#role-message');
                
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); }
                    catch(e) {
                        messageDiv.html('<p style="color: red;">Invalid response format</p>');
                        $btn.prop('disabled', false).text('Add Role');
                        return;
                    }
                }
                
                if (response && response.success) {
                    const message = (response.data && response.data.message) || 'Role added successfully';
                    messageDiv.html('<p style="color: green;">' + message + '</p>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    const message = (response.data && response.data.message) || 'An error occurred';
                    messageDiv.html('<p style="color: red;">' + message + '</p>');
                    $btn.prop('disabled', false).text('Add Role');
                }
            })
            .fail(function(xhr, status, error) {
                $('#role-message').html('<p style="color: red;">AJAX Error: ' + error + '</p>');
                $btn.prop('disabled', false).text('Add Role');
            });
        });
        
        // Delete Role
        $(document).on('click', '.delete-role', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this role?')) return;
            
            const $btn = $(this);
            const roleKey = $btn.data('key');
            $btn.prop('disabled', true).text('Deleting...');
            
            $.post(bntmAjax.ajax_url, {
                action: 'bntm_hr_delete_role',
                nonce: bntmAjax.nonce,
                role_key: roleKey
            })
            .done(function(response) {
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); }
                    catch(e) {
                        alert('Invalid response format');
                        $btn.prop('disabled', false).text('Delete');
                        return;
                    }
                }
                
                const message = (response.data && response.data.message) || (response.success ? 'Operation completed' : 'An error occurred');
                alert(message);
                
                if (response && response.success) {
                    location.reload();
                } else {
                    $btn.prop('disabled', false).text('Delete');
                }
            })
            .fail(function(xhr, status, error) {
                alert('AJAX Error: ' + error);
                $btn.prop('disabled', false).text('Delete');
            });
        });
        
        // Save Leave Types
        $('#save-leave-types').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const leaveTypes = $('#hr-leave-types').val();
            
            if (!leaveTypes || leaveTypes.trim() === '') {
                $('#leave-types-message').html('<p style="color: red;">Leave types cannot be empty.</p>');
                return;
            }
            
            $btn.prop('disabled', true).text('Saving...');
            
            $.post(bntmAjax.ajax_url, {
                action: 'bntm_hr_save_leave_types',
                nonce: bntmAjax.nonce,
                leave_types: leaveTypes
            })
            .done(function(response) {
                const messageDiv = $('#leave-types-message');
                
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); }
                    catch(e) {
                        messageDiv.html('<p style="color: red;">Invalid response format</p>');
                        return;
                    }
                }
                
                if (response && response.success) {
                    const message = (response.data && response.data.message) || 'Leave types saved successfully';
                    messageDiv.html('<p style="color: green;">' + message + '</p>');
                    setTimeout(function() { messageDiv.html(''); }, 3000);
                } else {
                    const message = (response.data && response.data.message) || 'An error occurred';
                    messageDiv.html('<p style="color: red;">' + message + '</p>');
                }
            })
            .fail(function(xhr, status, error) {
                $('#leave-types-message').html('<p style="color: red;">AJAX Error: ' + error + '</p>');
            })
            .always(function() {
                $btn.prop('disabled', false).text('Save Leave Types');
            });
        });
        
        // Add Deduction
        $('#add-deduction-btn').on('click', function(e) {
            e.preventDefault();
            const name = $('#deduction-name').val().trim();
            const type = $('#deduction-type').val();
            const value = $('#deduction-value').val();
            const description = $('#deduction-description').val().trim();
            
            if (!name || !value || parseFloat(value) < 0) {
                $('#deduction-message').html('<p style="color: red;">Please fill in all required fields with valid values.</p>');
                return;
            }
            
            const $btn = $(this);
            $btn.prop('disabled', true).text('Adding...');
            
            $.post(bntmAjax.ajax_url, {
                action: 'bntm_hr_add_deduction',
                nonce: bntmAjax.nonce,
                name: name,
                type: type,
                value: value,
                description: description
            })
            .done(function(response) {
                const messageDiv = $('#deduction-message');
                
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); }
                    catch(e) {
                        messageDiv.html('<p style="color: red;">Invalid response</p>');
                        return;
                    }
                }
                
                if (response && response.success) {
                    messageDiv.html('<p style="color: green;">' + response.data.message + '</p>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    messageDiv.html('<p style="color: red;">' + (response.data ? response.data.message : 'Error') + '</p>');
                    $btn.prop('disabled', false).text('Add Deduction');
                }
            })
            .fail(function() {
                $('#deduction-message').html('<p style="color: red;">AJAX Error</p>');
                $btn.prop('disabled', false).text('Add Deduction');
            });
        });
        
        // Delete Deduction
        $(document).on('click', '.delete-deduction', function(e) {
            e.preventDefault();
            if (!confirm('Delete this deduction?')) return;
            
            const $btn = $(this);
            const key = $btn.data('key');
            $btn.prop('disabled', true).text('Deleting...');
            
            $.post(bntmAjax.ajax_url, {
                action: 'bntm_hr_delete_deduction',
                nonce: bntmAjax.nonce,
                key: key
            })
            .done(function(response) {
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); }
                    catch(e) { alert('Invalid response'); return; }
                }
                
                alert(response.data ? response.data.message : 'Operation completed');
                if (response && response.success) location.reload();
                else $btn.prop('disabled', false).text('Delete');
            });
        });
        
        // Save Work Configuration
        $('#save-work-config').on('click', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const workHours = $('#hr-work-hours').val();
            const hourlyRate = $('#hr-hourly-rate').val();
            
            if (!workHours || parseFloat(workHours) <= 0) {
                $('#work-config-message').html('<p style="color: red;">Work hours must be greater than 0.</p>');
                return;
            }
            
            if (!hourlyRate || parseFloat(hourlyRate) < 0) {
                $('#work-config-message').html('<p style="color: red;">Hourly rate cannot be negative.</p>');
                return;
            }
            
            $btn.prop('disabled', true).text('Saving...');
            
            $.post(bntmAjax.ajax_url, {
                action: 'bntm_hr_save_work_config',
                nonce: bntmAjax.nonce,
                work_hours: workHours,
                hourly_rate: hourlyRate
            })
            .done(function(response) {
                const messageDiv = $('#work-config-message');
                
                if (typeof response === 'string') {
                    try { response = JSON.parse(response); }
                    catch(e) {
                        messageDiv.html('<p style="color: red;">Invalid response format</p>');
                        return;
                    }
                }
                
                if (response && response.success) {
                    const message = (response.data && response.data.message) || 'Configuration saved successfully';
                    messageDiv.html('<p style="color: green;">' + message + '</p>');
                    setTimeout(function() { messageDiv.html(''); }, 3000);
                } else {
                    const message = (response.data && response.data.message) || 'An error occurred';
                    messageDiv.html('<p style="color: red;">' + message + '</p>');
                }
            })
            .fail(function(xhr, status, error) {
                $('#work-config-message').html('<p style="color: red;">AJAX Error: ' + error + '</p>');
            })
            .always(function() {
                $btn.prop('disabled', false).text('Save Configuration');
            });
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}

// Updated AJAX Handler for Add Role
add_action('wp_ajax_bntm_hr_add_role', 'bntm_ajax_hr_add_role');
function bntm_ajax_hr_add_role() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner'])) {
        wp_send_json_error(['message' => 'Only owners can manage roles.']);
        exit;
    }
    
    $role_key = isset($_POST['role_key']) ? sanitize_key($_POST['role_key']) : '';
    $role_label = isset($_POST['role_label']) ? sanitize_text_field($_POST['role_label']) : '';
    $modules = isset($_POST['modules']) && is_array($_POST['modules']) ? array_map('sanitize_text_field', $_POST['modules']) : [];
    
    if (empty($role_key) || empty($role_label)) {
        wp_send_json_error(['message' => 'Role key and label are required.']);
        exit;
    }
    
    if (empty($modules)) {
        wp_send_json_error(['message' => 'At least one module must be selected.']);
        exit;
    }
    
    $default_roles = ['staff', 'manager', 'owner'];
    if (in_array($role_key, $default_roles)) {
        wp_send_json_error(['message' => 'Cannot use default role keys (staff, manager, owner).']);
        exit;
    }
    
    $custom_roles = bntm_get_setting('hr_custom_roles', '');
    $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    
    if (!is_array($roles_array)) {
        $roles_array = [];
    }
    
    if (isset($roles_array[$role_key])) {
        wp_send_json_error(['message' => 'Role key already exists.']);
        exit;
    }
    
    $roles_array[$role_key] = [
        'label' => $role_label,
        'modules' => $modules
    ];
    
    bntm_set_setting('hr_custom_roles', json_encode($roles_array));
    
    wp_send_json_success(['message' => 'Role added successfully!']);
    exit;
}

// AJAX Handler for Delete Role
add_action('wp_ajax_bntm_hr_delete_role', 'bntm_ajax_hr_delete_role');
function bntm_ajax_hr_delete_role() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner'])) {
        wp_send_json_error(['message' => 'Only owners can manage roles.']);
        exit;
    }
    
    $role_key = isset($_POST['role_key']) ? sanitize_key($_POST['role_key']) : '';
    
    if (empty($role_key)) {
        wp_send_json_error(['message' => 'Role key is required.']);
        exit;
    }
    
    $custom_roles = bntm_get_setting('hr_custom_roles', '');
    $roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    
    if (!is_array($roles_array)) {
        $roles_array = [];
    }
    
    if (!isset($roles_array[$role_key])) {
        wp_send_json_error(['message' => 'Role not found.']);
        exit;
    }
    
    unset($roles_array[$role_key]);
    
    bntm_set_setting('hr_custom_roles', json_encode($roles_array));
    
    wp_send_json_success(['message' => 'Role deleted successfully!']);
    exit;
}

add_action('wp_ajax_bntm_hr_save_leave_types', 'bntm_ajax_hr_save_leave_types');
function bntm_ajax_hr_save_leave_types() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
        exit;
    }
    
    $leave_types = isset($_POST['leave_types']) ? sanitize_text_field($_POST['leave_types']) : '';
    
    if (empty($leave_types)) {
        wp_send_json_error(['message' => 'Leave types cannot be empty.']);
        exit;
    }
    
    bntm_set_setting('hr_leave_types', $leave_types);
    
    wp_send_json_success(['message' => 'Leave types saved successfully!']);
    exit;
}

add_action('wp_ajax_bntm_hr_save_work_config', 'bntm_ajax_hr_save_work_config');
function bntm_ajax_hr_save_work_config() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
        exit;
    }
    
    $work_hours = isset($_POST['work_hours']) ? floatval($_POST['work_hours']) : 8;
    $hourly_rate = isset($_POST['hourly_rate']) ? floatval($_POST['hourly_rate']) : 15.00;
    
    if ($work_hours <= 0) {
        wp_send_json_error(['message' => 'Work hours must be greater than 0.']);
        exit;
    }
    
    if ($hourly_rate < 0) {
        wp_send_json_error(['message' => 'Hourly rate cannot be negative.']);
        exit;
    }
    
    bntm_set_setting('hr_work_hours', $work_hours);
    bntm_set_setting('hr_hourly_rate', $hourly_rate);
    
    wp_send_json_success(['message' => 'Configuration saved successfully!']);
    exit;
}


/* ---------- VIEW FUNCTIONS ---------- */

function bntm_hr_dashboard_view($user_id, $can_manage) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    $kiosk_page = get_page_by_path('hr-kiosk/');
    $kiosk_url = $kiosk_page ? get_permalink($kiosk_page) : '';
    
    ob_start();
    
    if ($can_manage):
        $total_employees = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE ID IN (SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'bntm_role' AND meta_value LIKE '%employee%')");
        $pending_leaves = $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}hr_leave_requests WHERE status = 'pending'");
        $today_attendance = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}hr_attendance WHERE DATE(clock_in) = %s",
            current_time('Y-m-d')
        ));
        ?>
    
    
        <div class="bntm-form-section">
            <h3>HR Overview</h3>
            
          <?php if ($kiosk_url): ?>
          <div class="bntm-form-section" style="background: #eff6ff; border-left: 4px solid #3b82f6;">
              <h3>Your Kiosk Page</h3>
              <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                  <input type="text" id="kiosk-url" value="<?php echo esc_url($kiosk_url); ?>" readonly style="flex: 1; min-width: 300px; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                  <button class="bntm-btn-secondary" id="copy-kiosk-url">Copy Link</button>
                  <a href="<?php echo esc_url($kiosk_url); ?>" target="_blank" class="bntm-btn-primary">Open Kiosk</a>
              </div>
          </div>
          <?php endif; ?>
            <div class="bntm-form-row">
                <div class="bntm-stat-card" style="background: #dbeafe; padding: 20px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">Total Employees</h4>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $total_employees; ?></p>
                </div>
                
                <div class="bntm-stat-card" style="background: #fef3c7; padding: 20px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">Pending Leave Requests</h4>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $pending_leaves; ?></p>
                </div>
                
                <div class="bntm-stat-card" style="background: #d1fae5; padding: 20px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0;">Today's Attendance</h4>
                    <p style="font-size: 32px; margin: 0; font-weight: bold;"><?php echo $today_attendance; ?></p>
                </div>
            </div>
        </div>
        
        <div class="bntm-form-section">
            <h3>Recent Leave Requests</h3>
            <?php
            $recent_leaves = $wpdb->get_results("SELECT * FROM {$prefix}hr_leave_requests ORDER BY created_at DESC LIMIT 5");
            
            if ($recent_leaves): ?>
                <table class="bntm-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_leaves as $leave):
                            $employee = get_userdata($leave->employee_id);
                            $status_class = $leave->status === 'approved' ? 'bntm-notice-success' : ($leave->status === 'rejected' ? 'bntm-notice-error' : '');
                        ?>
                            <tr>
                                <td><?php echo esc_html($employee->display_name); ?></td>
                                <td><?php echo esc_html($leave->leave_type); ?></td>
                                <td><?php echo esc_html($leave->start_date . ' to ' . $leave->end_date); ?></td>
                                <td>
                                    <span class="<?php echo $status_class; ?>" style="padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                        <?php echo esc_html($leave->status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($leave->status === 'pending'): ?>
                                        <button class="bntm-btn-small approve-leave" data-id="<?php echo $leave->id; ?>">Approve</button>
                                        <button class="bntm-btn-small bntm-btn-danger reject-leave" data-id="<?php echo $leave->id; ?>">Reject</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No recent leave requests.</p>
            <?php endif; ?>
        </div>
        
    <?php else:
        $user_data = get_userdata($user_id);
        $today_attendance = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s ORDER BY clock_in DESC LIMIT 1",
            $user_id,
            current_time('Y-m-d')
        ));
        $approved_leaves = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_days), 0) FROM {$prefix}hr_leave_requests WHERE employee_id = %d AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())",
            $user_id
        ));
        ?>
        
        <div class="bntm-form-section">
            <h3>Welcome, <?php echo esc_html($user_data->display_name); ?></h3>
            
            <div class="bntm-form-group">
                <button id="quick-clock-in" class="bntm-btn-primary">Clock In</button>
                <button id="quick-clock-out" class="bntm-btn-secondary">Clock Out</button>
                <div id="clock-message"></div>
            </div>
        </div>
        
        <div class="bntm-form-section">
            <h3>Today's Status</h3>
            <?php if ($today_attendance): ?>
                <p><strong>Clock In:</strong> <?php echo esc_html(date('h:i A', strtotime($today_attendance->clock_in))); ?></p>
                <?php if ($today_attendance->clock_out): ?>
                    <p><strong>Clock Out:</strong> <?php echo esc_html(date('h:i A', strtotime($today_attendance->clock_out))); ?></p>
                    <p><strong>Total Hours:</strong> <?php echo esc_html($today_attendance->total_hours); ?></p>
                <?php else: ?>
                    <p><em>Currently clocked in</em></p>
                <?php endif; ?>
            <?php else: ?>
                <p>No attendance record for today.</p>
            <?php endif; ?>
        </div>
        
        <div class="bntm-form-section">
            <h3>Leave Summary</h3>
            <p><strong>Days Used This Year:</strong> <?php echo esc_html($approved_leaves); ?></p>
        </div>
        
    <?php endif; ?>
    <script>
    (function() {
        const copyBtn = document.getElementById('copy-kiosk-url');
        if (copyBtn) {
            copyBtn.addEventListener('click', function() {
                const urlInput = document.getElementById('kiosk-url');
                urlInput.select();
                document.execCommand('copy');
                
                this.textContent = 'Copied!';
                setTimeout(() => {
                    this.textContent = 'Copy Link';
                }, 2000);
            });
        }
    })();
    </script>
    <script>
   jQuery(document).ready(function($) {
       const bntmAjax = {
           ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
           nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
       };
       
       // Quick Clock In
       $('#quick-clock-in').on('click', async function() {
           try {
               const response = await $.ajax({
                   url: bntmAjax.ajax_url,
                   method: 'POST',
                   data: {
                       action: 'bntm_hr_clock_in',
                       nonce: bntmAjax.nonce
                   }
               });
               
               const messageDiv = $('#clock-message');
               
               if (response.success) {
                   messageDiv.html('<p style="color: green; margin-top: 10px;">' + response.data.message + '</p>');
                   setTimeout(function() { location.reload(); }, 2000);
               } else {
                   messageDiv.html('<p style="color: red; margin-top: 10px;">' + response.data.message + '</p>');
               }
           } catch (error) {
               console.error('Error:', error);
           }
       });
       
       // Quick Clock Out
       $('#quick-clock-out').on('click', async function() {
           try {
               const response = await $.ajax({
                   url: bntmAjax.ajax_url,
                   method: 'POST',
                   data: {
                       action: 'bntm_hr_clock_out',
                       nonce: bntmAjax.nonce
                   }
               });
               
               const messageDiv = $('#clock-message');
               
               if (response.success) {
                   messageDiv.html('<p style="color: green; margin-top: 10px;">' + response.data.message + '</p>');
                   setTimeout(function() { location.reload(); }, 2000);
               } else {
                   messageDiv.html('<p style="color: red; margin-top: 10px;">' + response.data.message + '</p>');
               }
           } catch (error) {
               console.error('Error:', error);
           }
       });
       
       // Approve Leave
       $('.approve-leave').on('click', async function() {
           if (!confirm('Approve this leave request?')) return;
           
           try {
               const response = await $.ajax({
                   url: bntmAjax.ajax_url,
                   method: 'POST',
                   data: {
                       action: 'bntm_hr_approve_leave',
                       nonce: bntmAjax.nonce,
                       leave_id: $(this).data('id')
                   }
               });
               
               alert(response.success ? response.data.message : response.data.message);
               if (response.success) location.reload();
           } catch (error) {
               console.error('Error:', error);
           }
       });
       
       // Reject Leave
       $('.reject-leave').on('click', async function() {
           if (!confirm('Reject this leave request?')) return;
           
           try {
               const response = await $.ajax({
                   url: bntmAjax.ajax_url,
                   method: 'POST',
                   data: {
                       action: 'bntm_hr_reject_leave',
                       nonce: bntmAjax.nonce,
                       leave_id: $(this).data('id')
                   }
               });
               
               alert(response.success ? response.data.message : response.data.message);
               if (response.success) location.reload();
           } catch (error) {
               console.error('Error:', error);
           }
       });
   });
   </script>
    
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_bntm_hr_clock_in', 'bntm_ajax_hr_clock_in');
function bntm_ajax_hr_clock_in() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    $user_id = get_current_user_id();
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL",
        $user_id,
        current_time('Y-m-d')
    ));
    
    if ($existing) {
        wp_send_json_error(['message' => 'You are already clocked in.']);
    }
    
    $result = $wpdb->insert(
        $prefix . 'hr_attendance',
        [
            'rand_id' => bntm_rand_id(),
            'employee_id' => $user_id,
            'business_id' => 1,
            'clock_in' => current_time('mysql'),
            'status' => 'active'
        ],
        ['%s', '%d', '%d', '%s', '%s']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Clocked in successfully at ' . current_time('h:i A')]);
    } else {
        wp_send_json_error(['message' => 'Failed to clock in.']);
    }
}

add_action('wp_ajax_bntm_hr_clock_out', 'bntm_ajax_hr_clock_out');
function bntm_ajax_hr_clock_out() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    $user_id = get_current_user_id();
    
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$prefix}hr_attendance WHERE employee_id = %d AND DATE(clock_in) = %s AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
        $user_id,
        current_time('Y-m-d')
    ));
    
    if (!$record) {
        wp_send_json_error(['message' => 'No active clock in record found.']);
    }
    
    $clock_out = current_time('mysql');
    $clock_in = strtotime($record->clock_in);
    $clock_out_time = strtotime($clock_out);
    $total_hours = round(($clock_out_time - $clock_in) / 3600, 2);
    
    $result = $wpdb->update(
        $prefix . 'hr_attendance',
        [
            'clock_out' => $clock_out,
            'total_hours' => $total_hours,
            'status' => 'completed'
        ],
        ['id' => $record->id],
        ['%s', '%f', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Clocked out successfully. Total hours: ' . $total_hours]);
    } else {
        wp_send_json_error(['message' => 'Failed to clock out.']);
    }
}

add_action('wp_ajax_bntm_hr_kiosk_clock', 'bntm_ajax_hr_kiosk_clock');
add_action('wp_ajax_nopriv_bntm_hr_kiosk_clock', 'bntm_ajax_hr_kiosk_clock');

function bntm_hr_employees_view($can_manage) {
    if (!$can_manage) {
        return '<p>You do not have permission to view this page.</p>';
    }
    
    global $wpdb;
    ob_start();
    
    $roles = bntm_get_hr_roles();
    $employees = get_users([
        'exclude' => [1],
        'orderby' => 'display_name'
    ]);
    ?>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Employee Management</h3>
            <button id="add-employee-btn" class="bntm-btn-primary">+ Add Employee</button>
        </div>
        
        <!-- Add Employee Modal -->
        <div id="employee-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-top: 0;">Add New Employee</h3>
                
                <form id="add-employee-form" class="bntm-form">
                    <div class="bntm-form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Username *</label>
                        <input type="text" name="username" required />
                        <small>Used for login</small>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Password *</label>
                        <input type="password" name="password" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Role *</label>
                        <select name="role" required>
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" />
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Department</label>
                            <input type="text" name="department" placeholder="e.g., Sales, IT, HR" />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Position</label>
                            <input type="text" name="position" placeholder="e.g., Sales Manager" />
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" value="<?php echo current_time('Y-m-d'); ?>" />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Hourly Rate ($)</label>
                            <input type="number" name="hourly_rate" step="0.01" placeholder="15.00" />
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Employee PIN (for Kiosk)</label>
                        <input type="text" name="pin" maxlength="6" placeholder="4-6 digit PIN" />
                        <small>Used for clock in/out at kiosk</small>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_name" />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Emergency Contact Phone</label>
                        <input type="tel" name="emergency_phone" />
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="bntm-btn-primary">Add Employee</button>
                        <button type="button" id="close-employee-modal" class="bntm-btn-secondary">Cancel</button>
                    </div>
                    
                    <div id="employee-modal-message"></div>
                </form>
            </div>
        </div>
        
        <!-- Edit Employee Modal -->
        <div id="edit-employee-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-top: 0;">Edit Employee</h3>
                
                <form id="edit-employee-form" class="bntm-form">
                    <input type="hidden" name="user_id" />
                    
                    <div class="bntm-form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Role *</label>
                        <select name="role" required>
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" />
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Department</label>
                            <input type="text" name="department" />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Position</label>
                            <input type="text" name="position" />
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Hire Date</label>
                            <input type="date" name="hire_date" />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Hourly Rate ($)</label>
                            <input type="number" name="hourly_rate" step="0.01" />
                        </div>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Employee PIN (for Kiosk)</label>
                        <input type="text" name="pin" maxlength="6" />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Emergency Contact Name</label>
                        <input type="text" name="emergency_name" />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Emergency Contact Phone</label>
                        <input type="tel" name="emergency_phone" />
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="bntm-btn-primary">Update Employee</button>
                        <button type="button" id="close-edit-modal" class="bntm-btn-secondary">Cancel</button>
                    </div>
                    
                    <div id="edit-employee-message"></div>
                </form>
            </div>
        </div>
        
        <!-- Employees Table -->
        <?php if ($employees): ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>PIN</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee):
                        $role = get_user_meta($employee->ID, 'bntm_role', true);
                        $department = get_user_meta($employee->ID, 'bntm_department', true);
                        $status = get_user_meta($employee->ID, 'bntm_status', true) ?: 'active';
                        $pin = get_user_meta($employee->ID, 'bntm_hr_pin', true);
                        
                        $status_colors = [
                            'active' => 'background: #d1fae5; color: #065f46;',
                            'inactive' => 'background: #fee2e2; color: #991b1b;',
                            'on_leave' => 'background: #fef3c7; color: #92400e;',
                            'terminated' => 'background: #f3f4f6; color: #6b7280;'
                        ];
                        
                        $status_style = isset($status_colors[$status]) ? $status_colors[$status] : '';
                    ?>
                        <tr>
                            <td><?php echo esc_html($employee->display_name); ?></td>
                            <td><?php echo esc_html($employee->user_email); ?></td>
                            <td><?php echo esc_html($role ? ucfirst($role) : 'Not Set'); ?></td>
                            <td><?php echo esc_html($department ?: '-'); ?></td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; display: inline-block; <?php echo $status_style; ?>">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($pin ?: 'Not set'); ?></td>
                            <td>
                                <button class="bntm-btn-small edit-employee" data-id="<?php echo $employee->ID; ?>">Edit</button>
                                <button class="bntm-btn-small view-attendance" data-id="<?php echo $employee->ID; ?>" data-name="<?php echo esc_attr($employee->display_name); ?>">Attendance</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No employees found.</p>
        <?php endif; ?>
    </div>
    
    <script>
    (function() {
        const bntmAjax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
        };
        
        function serializeForm(form) {
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            return data;
        }
        
        function populateForm(form, data) {
            for (let key in data) {
                const input = form.querySelector('[name="' + key + '"]');
                if (input) {
                    if (input.type === 'checkbox') {
                        input.checked = data[key];
                    } else {
                        input.value = data[key] || '';
                    }
                }
            }
        }
        
        async function makeAjaxRequest(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', bntmAjax.nonce);
            
            for (let key in data) {
                if (Array.isArray(data[key])) {
                    data[key].forEach(val => formData.append(key + '[]', val));
                } else {
                    formData.append(key, data[key]);
                }
            }
            
            try {
                const response = await fetch(bntmAjax.ajax_url, {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON Parse Error:', text);
                    throw new Error('Invalid JSON response from server');
                }
            } catch (error) {
                console.error('AJAX Error:', error);
                throw error;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const addEmployeeBtn = document.getElementById('add-employee-btn');
            const employeeModal = document.getElementById('employee-modal');
            const closeEmployeeModal = document.getElementById('close-employee-modal');
            const addEmployeeForm = document.getElementById('add-employee-form');
            const editEmployeeModal = document.getElementById('edit-employee-modal');
            const closeEditModal = document.getElementById('close-edit-modal');
            const editEmployeeForm = document.getElementById('edit-employee-form');
            
            // Open Add Employee Modal
            if (addEmployeeBtn) {
                addEmployeeBtn.addEventListener('click', function() {
                    employeeModal.style.display = 'flex';
                });
            }
            
            // Close Add Employee Modal
            if (closeEmployeeModal) {
                closeEmployeeModal.addEventListener('click', function() {
                    employeeModal.style.display = 'none';
                    addEmployeeForm.reset();
                    document.getElementById('employee-modal-message').innerHTML = '';
                });
            }
            
            // Add Employee Form Submit
            if (addEmployeeForm) {
                addEmployeeForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const messageDiv = document.getElementById('employee-modal-message');
                    messageDiv.innerHTML = '<p>Processing...</p>';
                    
                    try {
                        const formData = serializeForm(this);
                        const data = await makeAjaxRequest('bntm_hr_add_employee', formData);
                        
                        if (data.success) {
                            messageDiv.innerHTML = '<p style="color: green;">' + data.data.message + '</p>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            messageDiv.innerHTML = '<p style="color: red;">' + (data.data ? data.data.message : 'An error occurred') + '</p>';
                        }
                    } catch (error) {
                        messageDiv.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                    }
                });
            }
            
            // Edit Employee Buttons
            document.querySelectorAll('.edit-employee').forEach(button => {
                button.addEventListener('click', async function() {
                    const userId = this.getAttribute('data-id');
                    
                    try {
                        const result = await makeAjaxRequest('bntm_hr_get_employee', { user_id: userId });
                        
                        if (result.success) {
                            populateForm(editEmployeeForm, result.data);
                            editEmployeeForm.querySelector('[name="user_id"]').value = userId;
                            editEmployeeModal.style.display = 'flex';
                        }
                    } catch (error) {
                        alert('Error loading employee data: ' + error.message);
                    }
                });
            });
            
            // Close Edit Modal
            if (closeEditModal) {
                closeEditModal.addEventListener('click', function() {
                    editEmployeeModal.style.display = 'none';
                    document.getElementById('edit-employee-message').innerHTML = '';
                });
            }
            
            // Edit Employee Form Submit
            if (editEmployeeForm) {
                editEmployeeForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const messageDiv = document.getElementById('edit-employee-message');
                    messageDiv.innerHTML = '<p>Processing...</p>';
                    
                    try {
                        const formData = serializeForm(this);
                        const data = await makeAjaxRequest('bntm_hr_update_employee', formData);
                        
                        if (data.success) {
                            messageDiv.innerHTML = '<p style="color: green;">' + data.data.message + '</p>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            messageDiv.innerHTML = '<p style="color: red;">' + (data.data ? data.data.message : 'An error occurred') + '</p>';
                        }
                    } catch (error) {
                        messageDiv.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                    }
                });
            }
            
            // View Attendance Buttons
            document.querySelectorAll('.view-attendance').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-id');
                    const userName = this.getAttribute('data-name');
                    
                    // Redirect to attendance page with employee filter
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('type', 'attendance');
                    currentUrl.searchParams.set('employee_id', userId);
                    window.location.href = currentUrl.toString();
                });
            });
        });
    })();
    </script>
    
    <?php
    return ob_get_clean();
}
/* ---------- HELPER FUNCTIONS ---------- */

function bntm_get_hr_roles() {
    $default_roles = [
        'staff' => 'Staff',
        'manager' => 'Manager',
        'owner' => 'Owner'
    ];
    
    $custom_roles = bntm_get_setting('hr_custom_roles', '');
    $custom_roles_array = $custom_roles ? json_decode($custom_roles, true) : [];
    
    $all_roles = $default_roles;
    if (is_array($custom_roles_array)) {
        foreach ($custom_roles_array as $key => $role) {
            $all_roles[$key] = $role['label'];
        }
    }
    
    return $all_roles;
}
/* ---------- AJAX HANDLERS ---------- */


add_action('wp_ajax_bntm_hr_add_employee', 'bntm_ajax_hr_add_employee');
function bntm_ajax_hr_add_employee() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $username = sanitize_user($_POST['username']);
    $password = $_POST['password'];
    $role = sanitize_text_field($_POST['role']);
    
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
    }
    
    wp_update_user([
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $first_name . ' ' . $last_name
    ]);
    
    update_user_meta($user_id, 'bntm_role', $role);
    update_user_meta($user_id, 'bntm_phone', sanitize_text_field($_POST['phone'] ?? ''));
    update_user_meta($user_id, 'bntm_dob', sanitize_text_field($_POST['dob'] ?? ''));
    update_user_meta($user_id, 'bntm_address', sanitize_textarea_field($_POST['address'] ?? ''));
    update_user_meta($user_id, 'bntm_department', sanitize_text_field($_POST['department'] ?? ''));
    update_user_meta($user_id, 'bntm_position', sanitize_text_field($_POST['position'] ?? ''));
    update_user_meta($user_id, 'bntm_hire_date', sanitize_text_field($_POST['hire_date'] ?? ''));
    update_user_meta($user_id, 'bntm_hourly_rate', floatval($_POST['hourly_rate'] ?? 0));
    update_user_meta($user_id, 'bntm_hr_pin', sanitize_text_field($_POST['pin'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_name', sanitize_text_field($_POST['emergency_name'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_phone', sanitize_text_field($_POST['emergency_phone'] ?? ''));
    update_user_meta($user_id, 'bntm_status', 'active');
    
    wp_send_json_success(['message' => 'Employee added successfully!']);
}

add_action('wp_ajax_bntm_hr_get_employee', 'bntm_ajax_hr_get_employee');
function bntm_ajax_hr_get_employee() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $user_id = intval($_POST['user_id']);
    $user = get_userdata($user_id);
    
    if (!$user) {
        wp_send_json_error(['message' => 'Employee not found.']);
    }
    
    $data = [
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->user_email,
        'role' => get_user_meta($user_id, 'bntm_role', true),
        'phone' => get_user_meta($user_id, 'bntm_phone', true),
        'dob' => get_user_meta($user_id, 'bntm_dob', true),
        'address' => get_user_meta($user_id, 'bntm_address', true),
        'department' => get_user_meta($user_id, 'bntm_department', true),
        'position' => get_user_meta($user_id, 'bntm_position', true),
        'hire_date' => get_user_meta($user_id, 'bntm_hire_date', true),
        'hourly_rate' => get_user_meta($user_id, 'bntm_hourly_rate', true),
        'pin' => get_user_meta($user_id, 'bntm_hr_pin', true),
        'emergency_name' => get_user_meta($user_id, 'bntm_emergency_contact_name', true),
        'emergency_phone' => get_user_meta($user_id, 'bntm_emergency_contact_phone', true),
        'status' => get_user_meta($user_id, 'bntm_status', true) ?: 'active'
    ];
    
    wp_send_json_success($data);
}

add_action('wp_ajax_bntm_hr_update_employee', 'bntm_ajax_hr_update_employee');
function bntm_ajax_hr_update_employee() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    $user_id = intval($_POST['user_id']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $role = sanitize_text_field($_POST['role']);
    
    wp_update_user([
        'ID' => $user_id,
        'user_email' => $email,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $first_name . ' ' . $last_name
    ]);
    
    update_user_meta($user_id, 'bntm_role', $role);
    update_user_meta($user_id, 'bntm_phone', sanitize_text_field($_POST['phone'] ?? ''));
    update_user_meta($user_id, 'bntm_dob', sanitize_text_field($_POST['dob'] ?? ''));
    update_user_meta($user_id, 'bntm_address', sanitize_textarea_field($_POST['address'] ?? ''));
    update_user_meta($user_id, 'bntm_department', sanitize_text_field($_POST['department'] ?? ''));
    update_user_meta($user_id, 'bntm_position', sanitize_text_field($_POST['position'] ?? ''));
    update_user_meta($user_id, 'bntm_hire_date', sanitize_text_field($_POST['hire_date'] ?? ''));
    update_user_meta($user_id, 'bntm_hourly_rate', floatval($_POST['hourly_rate'] ?? 0));
    update_user_meta($user_id, 'bntm_hr_pin', sanitize_text_field($_POST['pin'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_name', sanitize_text_field($_POST['emergency_name'] ?? ''));
    update_user_meta($user_id, 'bntm_emergency_contact_phone', sanitize_text_field($_POST['emergency_phone'] ?? ''));
    update_user_meta($user_id, 'bntm_status', sanitize_text_field($_POST['status'] ?? 'active'));
    
    wp_send_json_success(['message' => 'Employee updated successfully!']);
}


function bntm_hr_attendance_view($user_id, $can_manage) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    ob_start();
    
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
    $filter_employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
    
    // Get employee info if filtering by specific employee
    $employee_name = '';
    if ($filter_employee_id > 0) {
        $employee = get_userdata($filter_employee_id);
        if ($employee) {
            $employee_name = $employee->display_name;
        }
    }
    
    // Build query based on permissions and filters
    if ($can_manage) {
        if ($filter_employee_id > 0) {
            // Filter by specific employee
            $attendance = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, u.display_name FROM {$prefix}hr_attendance a 
                LEFT JOIN {$wpdb->users} u ON a.employee_id = u.ID 
                WHERE a.employee_id = %d AND DATE(a.clock_in) BETWEEN %s AND %s 
                ORDER BY a.clock_in DESC",
                $filter_employee_id,
                $date_from,
                $date_to
            ));
        } else {
            // Show all employees
            $attendance = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, u.display_name FROM {$prefix}hr_attendance a 
                LEFT JOIN {$wpdb->users} u ON a.employee_id = u.ID 
                WHERE DATE(a.clock_in) BETWEEN %s AND %s 
                ORDER BY a.clock_in DESC",
                $date_from,
                $date_to
            ));
        }
    } else {
        // Regular employees can only see their own attendance
        $attendance = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_attendance 
            WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s 
            ORDER BY clock_in DESC",
            $user_id,
            $date_from,
            $date_to
        ));
    }
    
    // Get all employees for filter dropdown (if manager)
    $all_employees = [];
    if ($can_manage) {
        $all_employees = get_users([
            'exclude' => [1],
            'orderby' => 'display_name'
        ]);
    }
    ?>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">
                Attendance Records
                <?php if ($employee_name): ?>
                    - <?php echo esc_html($employee_name); ?>
                <?php endif; ?>
            </h3>
            <?php if ($filter_employee_id > 0): ?>
                <a href="?type=attendance" class="bntm-btn-secondary">← Back to All Employees</a>
            <?php endif; ?>
        </div>
        
        <form method="get" class="bntm-form" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>" />
            <input type="hidden" name="type" value="attendance" />
            
            <div class="bntm-form-row">
                <?php if ($can_manage): ?>
                    <div class="bntm-form-group">
                        <label>Employee</label>
                        <select name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($all_employees as $emp): ?>
                                <option value="<?php echo $emp->ID; ?>" <?php selected($filter_employee_id, $emp->ID); ?>>
                                    <?php echo esc_html($emp->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="bntm-form-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                </div>
                
                <div class="bntm-form-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                </div>
                
                <div class="bntm-form-group" style="align-self: end;">
                    <button type="submit" class="bntm-btn-primary">Filter</button>
                </div>
            </div>
        </form>
        
        <?php if ($attendance): 
            // Calculate totals
            $total_hours = 0;
            $total_days = 0;
            foreach ($attendance as $record) {
                if ($record->total_hours) {
                    $total_hours += floatval($record->total_hours);
                    $total_days++;
                }
            }
        ?>
            <!-- Summary Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; border-left: 4px solid #0284c7;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Total Records</div>
                    <div style="font-size: 24px; font-weight: bold; color: #0f172a;"><?php echo count($attendance); ?></div>
                </div>
                <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; border-left: 4px solid #16a34a;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Total Hours</div>
                    <div style="font-size: 24px; font-weight: bold; color: #0f172a;"><?php echo number_format($total_hours, 2); ?></div>
                </div>
                <div style="background: #fefce8; padding: 15px; border-radius: 8px; border-left: 4px solid #ca8a04;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Days Worked</div>
                    <div style="font-size: 24px; font-weight: bold; color: #0f172a;"><?php echo $total_days; ?></div>
                </div>
                <div style="background: #faf5ff; padding: 15px; border-radius: 8px; border-left: 4px solid #9333ea;">
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Avg Hours/Day</div>
                    <div style="font-size: 24px; font-weight: bold; color: #0f172a;">
                        <?php echo $total_days > 0 ? number_format($total_hours / $total_days, 2) : '0'; ?>
                    </div>
                </div>
            </div>
            
            <table class="bntm-table">
                <thead>
                    <tr>
                        <?php if ($can_manage && !$filter_employee_id): ?><th>Employee</th><?php endif; ?>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                        <?php if ($can_manage): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendance as $record): 
                        $status_colors = [
                            'present' => 'background: #d1fae5; color: #065f46;',
                            'late' => 'background: #fef3c7; color: #92400e;',
                            'absent' => 'background: #fee2e2; color: #991b1b;',
                            'on_leave' => 'background: #e0e7ff; color: #3730a3;'
                        ];
                        $status_style = isset($status_colors[$record->status]) ? $status_colors[$record->status] : '';
                    ?>
                        <tr>
                            <?php if ($can_manage && !$filter_employee_id): ?>
                                <td><?php echo esc_html($record->display_name); ?></td>
                            <?php endif; ?>
                            <td><?php echo esc_html(date('M d, Y', strtotime($record->clock_in))); ?></td>
                            <td><?php echo esc_html(date('h:i A', strtotime($record->clock_in))); ?></td>
                            <td>
                                <?php if ($record->clock_out): ?>
                                    <?php echo esc_html(date('h:i A', strtotime($record->clock_out))); ?>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 500;">Not clocked out</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record->total_hours): ?>
                                    <strong><?php echo esc_html(number_format($record->total_hours, 2)); ?> hrs</strong>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; display: inline-block; font-size: 12px; <?php echo $status_style; ?>">
                                    <?php echo esc_html(ucfirst($record->status)); ?>
                                </span>
                            </td>
                            <?php if ($can_manage): ?>
                                <td>
                                    <button class="bntm-btn-small edit-attendance" 
                                            data-id="<?php echo $record->id; ?>"
                                            data-clock-in="<?php echo esc_attr($record->clock_in); ?>"
                                            data-clock-out="<?php echo esc_attr($record->clock_out); ?>"
                                            data-status="<?php echo esc_attr($record->status); ?>">
                                        Edit
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; background: #f9fafb; border-radius: 8px;">
                <p style="color: #64748b; margin: 0;">No attendance records found for the selected period.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Edit Attendance Modal (for managers) -->
    <?php if ($can_manage): ?>
    <div id="edit-attendance-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3 style="margin-top: 0;">Edit Attendance Record</h3>
            
            <form id="edit-attendance-form" class="bntm-form">
                <input type="hidden" name="attendance_id" />
                
                <div class="bntm-form-group">
                    <label>Clock In</label>
                    <input type="datetime-local" name="clock_in" required />
                </div>
                
                <div class="bntm-form-group">
                    <label>Clock Out</label>
                    <input type="datetime-local" name="clock_out" />
                </div>
                
                <div class="bntm-form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="on_leave">On Leave</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update</button>
                    <button type="button" id="close-attendance-modal" class="bntm-btn-secondary">Cancel</button>
                </div>
                
                <div id="edit-attendance-message"></div>
            </form>
        </div>
    </div>
    
    <script>
    (function() {
        const bntmAjax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            const editAttendanceModal = document.getElementById('edit-attendance-modal');
            const closeAttendanceModal = document.getElementById('close-attendance-modal');
            const editAttendanceForm = document.getElementById('edit-attendance-form');
            
            // Edit Attendance Buttons
            document.querySelectorAll('.edit-attendance').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const clockIn = this.getAttribute('data-clock-in');
                    const clockOut = this.getAttribute('data-clock-out');
                    const status = this.getAttribute('data-status');
                    
                    // Format datetime for input
                    const formatDateTime = (datetime) => {
                        if (!datetime) return '';
                        const d = new Date(datetime);
                        const year = d.getFullYear();
                        const month = String(d.getMonth() + 1).padStart(2, '0');
                        const day = String(d.getDate()).padStart(2, '0');
                        const hours = String(d.getHours()).padStart(2, '0');
                        const minutes = String(d.getMinutes()).padStart(2, '0');
                        return `${year}-${month}-${day}T${hours}:${minutes}`;
                    };
                    
                    editAttendanceForm.querySelector('[name="attendance_id"]').value = id;
                    editAttendanceForm.querySelector('[name="clock_in"]').value = formatDateTime(clockIn);
                    editAttendanceForm.querySelector('[name="clock_out"]').value = formatDateTime(clockOut);
                    editAttendanceForm.querySelector('[name="status"]').value = status;
                    
                    editAttendanceModal.style.display = 'flex';
                });
            });
            
            // Close Modal
            if (closeAttendanceModal) {
                closeAttendanceModal.addEventListener('click', function() {
                    editAttendanceModal.style.display = 'none';
                    document.getElementById('edit-attendance-message').innerHTML = '';
                });
            }
            
            // Submit Edit Form
            if (editAttendanceForm) {
                editAttendanceForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const messageDiv = document.getElementById('edit-attendance-message');
                    messageDiv.innerHTML = '<p>Processing...</p>';
                    
                    const formData = new FormData(this);
                    formData.append('action', 'bntm_hr_update_attendance');
                    formData.append('nonce', bntmAjax.nonce);
                    
                    try {
                        const response = await fetch(bntmAjax.ajax_url, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            messageDiv.innerHTML = '<p style="color: green;">' + data.data.message + '</p>';
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            messageDiv.innerHTML = '<p style="color: red;">' + (data.data ? data.data.message : 'An error occurred') + '</p>';
                        }
                    } catch (error) {
                        messageDiv.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                    }
                });
            }
        });
    })();
    </script>
    <?php endif; ?>
    
    <?php
    return ob_get_clean();
}

// AJAX Handler for updating attendance records
add_action('wp_ajax_bntm_hr_update_attendance', 'bntm_ajax_hr_update_attendance');
function bntm_ajax_hr_update_attendance() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $attendance_id = intval($_POST['attendance_id']);
    $clock_in = sanitize_text_field($_POST['clock_in']);
    $clock_out = sanitize_text_field($_POST['clock_out']);
    $status = sanitize_text_field($_POST['status']);
    
    // Calculate total hours if both clock in and out are provided
    $total_hours = null;
    if ($clock_in && $clock_out) {
        $start = new DateTime($clock_in);
        $end = new DateTime($clock_out);
        $interval = $start->diff($end);
        $total_hours = $interval->h + ($interval->i / 60) + ($interval->days * 24);
        $total_hours = round($total_hours, 2);
    }
    
    $update_data = [
        'clock_in' => $clock_in,
        'status' => $status
    ];
    
    $format = ['%s', '%s'];
    
    if ($clock_out) {
        $update_data['clock_out'] = $clock_out;
        $update_data['total_hours'] = $total_hours;
        $format[] = '%s';
        $format[] = '%f';
    }
    
    $result = $wpdb->update(
        $prefix . 'hr_attendance',
        $update_data,
        ['id' => $attendance_id],
        $format,
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Failed to update attendance record.']);
    }
    
    wp_send_json_success(['message' => 'Attendance record updated successfully!']);
}

function bntm_hr_leaves_view($user_id, $can_manage) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    ob_start();
    
    if (!$can_manage):
        $leave_types = explode(',', bntm_get_setting('hr_leave_types', 'Sick Leave,Vacation,Personal,Bereavement'));
    ?>
        <div class="bntm-form-section">
            <h3>Request Leave</h3>
            <form id="leave-request-form" class="bntm-form">
                <div class="bntm-form-group">
                    <label>Leave Type</label>
                    <select id="leave-type" required>
                        <option value="">Select type...</option>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?php echo esc_attr(trim($type)); ?>"><?php echo esc_html(trim($type)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Start Date</label>
                        <input type="date" id="leave-start-date" required />
                    </div>
                    <div class="bntm-form-group">
                        <label>End Date</label>
                        <input type="date" id="leave-end-date" required />
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Reason</label>
                    <textarea id="leave-reason" rows="3"></textarea>
                </div>
                
                <button type="submit" class="bntm-btn-primary">Submit Request</button>
                <div id="leave-request-message"></div>
            </form>
        </div>
    <?php endif;
    
    if ($can_manage) {
        $leaves = $wpdb->get_results(
            "SELECT l.*, u.display_name FROM {$prefix}hr_leave_requests l 
            LEFT JOIN {$wpdb->users} u ON l.employee_id = u.ID 
            ORDER BY l.created_at DESC"
        );
    } else {
        $leaves = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_leave_requests WHERE employee_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }
    ?>
    
    <div class="bntm-form-section">
        <h3><?php echo $can_manage ? 'All Leave Requests' : 'My Leave Requests'; ?></h3>
        
        <?php if ($leaves): ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <?php if ($can_manage): ?><th>Employee</th><?php endif; ?>
                        <th>Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaves as $leave):
                        $status_class = $leave->status === 'approved' ? 'bntm-notice-success' : ($leave->status === 'rejected' ? 'bntm-notice-error' : '');
                    ?>
                        <tr>
                            <?php if ($can_manage): ?>
                                <td><?php echo esc_html($leave->display_name); ?></td>
                            <?php endif; ?>
                            <td><?php echo esc_html($leave->leave_type); ?></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($leave->start_date))); ?></td>
                            <td><?php echo esc_html(date('M d, Y', strtotime($leave->end_date))); ?></td>
                            <td><?php echo esc_html($leave->total_days); ?></td>
                            <td>
                                <span class="<?php echo $status_class; ?>" style="padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                    <?php echo esc_html($leave->status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($can_manage && $leave->status === 'pending'): ?>
                                    <button class="bntm-btn-small approve-leave" data-id="<?php echo $leave->id; ?>">Approve</button>
                                    <button class="bntm-btn-small bntm-btn-danger reject-leave" data-id="<?php echo $leave->id; ?>">Reject</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No leave requests found.</p>
        <?php endif; ?>
    </div>
    
    <script>
jQuery(document).ready(function($) {
    const bntmAjax = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
    };
    
    // Submit Leave Request
    $('#leave-request-form').on('submit', async function(e) {
        e.preventDefault();
        
        try {
            const response = await $.ajax({
                url: bntmAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'bntm_hr_submit_leave',
                    nonce: bntmAjax.nonce,
                    leave_type: $('#leave-type').val(),
                    start_date: $('#leave-start-date').val(),
                    end_date: $('#leave-end-date').val(),
                    reason: $('#leave-reason').val()
                }
            });
            
            const messageDiv = $('#leave-request-message');
            
            if (response.success) {
                messageDiv.html('<p style="color: green; margin-top: 10px;">' + response.data.message + '</p>');
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                messageDiv.html('<p style="color: red; margin-top: 10px;">' + response.data.message + '</p>');
            }
        } catch (error) {
            console.error('Error:', error);
        }
    });
    
    // Approve Leave
    $('.approve-leave').on('click', async function() {
        if (!confirm('Approve this leave request?')) return;
        
        try {
            const response = await $.ajax({
                url: bntmAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'bntm_hr_approve_leave',
                    nonce: bntmAjax.nonce,
                    leave_id: $(this).data('id')
                }
            });
            
            alert(response.success ? response.data.message : response.data.message);
            if (response.success) location.reload();
        } catch (error) {
            console.error('Error:', error);
        }
    });
    
    // Reject Leave
    $('.reject-leave').on('click', async function() {
        if (!confirm('Reject this leave request?')) return;
        
        try {
            const response = await $.ajax({
                url: bntmAjax.ajax_url,
                method: 'POST',
                data: {
                    action: 'bntm_hr_reject_leave',
                    nonce: bntmAjax.nonce,
                    leave_id: $(this).data('id')
                }
            });
            
            alert(response.success ? response.data.message : response.data.message);
            if (response.success) location.reload();
        } catch (error) {
            console.error('Error:', error);
        }
    });
});
</script>
    
    <?php
    return ob_get_clean();
}


add_action('wp_ajax_bntm_hr_submit_leave', 'bntm_ajax_hr_submit_leave');
function bntm_ajax_hr_submit_leave() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $leave_type = sanitize_text_field($_POST['leave_type']);
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    $reason = sanitize_textarea_field($_POST['reason']);
    
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $total_days = $interval->days + 1;
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $result = $wpdb->insert(
        $prefix . 'hr_leave_requests',
        [
            'rand_id' => bntm_rand_id(),
            'employee_id' => $user_id,
            'business_id' => 1,
            'leave_type' => $leave_type,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'total_days' => $total_days,
            'reason' => $reason,
            'status' => 'pending'
        ],
        ['%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Leave request submitted successfully.']);
    } else {
        wp_send_json_error(['message' => 'Failed to submit leave request.']);
    }
}

add_action('wp_ajax_bntm_hr_approve_leave', 'bntm_ajax_hr_approve_leave');
function bntm_ajax_hr_approve_leave() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Unauthorized. Only Admins, Owners, and Managers can approve leaves.']);
    }
    
    $leave_id = intval($_POST['leave_id']);
    $user_id = get_current_user_id();
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $result = $wpdb->update(
        $prefix . 'hr_leave_requests',
        [
            'status' => 'approved',
            'approved_by' => $user_id,
            'approved_at' => current_time('mysql')
        ],
        ['id' => $leave_id],
        ['%s', '%d', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Leave request approved.']);
    } else {
        wp_send_json_error(['message' => 'Failed to approve leave request.']);
    }
}

add_action('wp_ajax_bntm_hr_reject_leave', 'bntm_ajax_hr_reject_leave');
function bntm_ajax_hr_reject_leave() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Unauthorized. Only Admins, Owners, and Managers can reject leaves.']);
    }
    
    $leave_id = intval($_POST['leave_id']);
    $user_id = get_current_user_id();
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $result = $wpdb->update(
        $prefix . 'hr_leave_requests',
        [
            'status' => 'rejected',
            'approved_by' => $user_id,
            'approved_at' => current_time('mysql')
        ],
        ['id' => $leave_id],
        ['%s', '%d', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Leave request rejected.']);
    } else {
        wp_send_json_error(['message' => 'Failed to reject leave request.']);
    }
}

function bntm_hr_payslips_view($user_id, $can_manage) {
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    ob_start();
    
    if ($can_manage) {
        $employees = get_users(['exclude' => [1], 'orderby' => 'display_name']);
        $payslips = $wpdb->get_results(
            "SELECT p.*, u.display_name 
            FROM {$prefix}hr_payslips p 
            LEFT JOIN {$wpdb->users} u ON p.employee_id = u.ID 
            ORDER BY p.created_at DESC LIMIT 50"
        );
    } else {
        $payslips = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_payslips WHERE employee_id = %d ORDER BY created_at DESC",
            $user_id
        ));
    }
    ?>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">Payslip Management</h3>
            <?php if ($can_manage): ?>
                <button id="generate-payslip-btn" class="bntm-btn-primary">+ Generate Payslip</button>
            <?php endif; ?>
        </div>
        
        <?php if ($can_manage): ?>
        <!-- Generate Payslip Modal -->
        <div id="payslip-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="margin-top: 0;">Generate Payslip(s)</h3>
                
                <form id="generate-payslip-form" class="bntm-form">
                    <div class="bntm-form-group">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="bulk-generate" />
                            <span>Generate for multiple employees</span>
                        </label>
                    </div>
                    
                    <div id="single-employee-section">
                        <div class="bntm-form-group">
                            <label>Employee *</label>
                            <select name="employee_id" id="employee-select">
                                <option value="">Select employee...</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp->ID; ?>"><?php echo esc_html($emp->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="bulk-employee-section" style="display: none;">
                        <div class="bntm-form-group">
                            <label>Select Employees *</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px;">
                                <?php foreach ($employees as $emp): ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" class="bulk-employee" value="<?php echo $emp->ID; ?>" />
                                        <?php echo esc_html($emp->display_name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bntm-form-row">
                        <div class="bntm-form-group">
                            <label>Period Start *</label>
                            <input type="date" name="period_start" required />
                        </div>
                        
                        <div class="bntm-form-group">
                            <label>Period End *</label>
                            <input type="date" name="period_end" required />
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="bntm-btn-primary">Generate & Download PDF</button>
                        <button type="button" id="close-payslip-modal" class="bntm-btn-secondary">Cancel</button>
                    </div>
                    
                    <div id="payslip-modal-message"></div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Payslips Table -->
        <?php if ($payslips): ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <?php if ($can_manage): ?><th><input type="checkbox" id="select-all-payslips" /></th><?php endif; ?>
                        <?php if ($can_manage): ?><th>Employee</th><?php endif; ?>
                        <th>Period</th>
                        <th>Basic Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payslips as $payslip): ?>
                        <tr>
                            <?php if ($can_manage): ?>
                                <td><input type="checkbox" class="payslip-checkbox" value="<?php echo $payslip->id; ?>" data-imported="<?php echo $payslip->is_imported; ?>" /></td>
                            <?php endif; ?>
                            <?php if ($can_manage): ?>
                                <td><?php echo esc_html($payslip->display_name); ?></td>
                            <?php endif; ?>
                            <td><?php echo date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end)); ?></td>
                            <td class="bntm-stat-income">₱<?php echo number_format($payslip->basic_pay, 2); ?></td>
                            <td class="bntm-stat-expense">₱<?php echo number_format($payslip->total_deductions, 2); ?></td>
                            <td><strong>₱<?php echo number_format($payslip->net_pay, 2); ?></strong></td>
                            <td>
                                <?php if ($payslip->is_imported): ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; display: inline-block; background: #d1fae5; color: #065f46; font-size: 12px;">
                                        ✓ Imported
                                    </span>
                                <?php else: ?>
                                    <span style="padding: 4px 8px; border-radius: 4px; display: inline-block; background: #f3f4f6; color: #6b7280; font-size: 12px;">
                                        Not Imported
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="bntm-btn-small download-payslip" data-id="<?php echo $payslip->id; ?>">Download PDF</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($can_manage): ?>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button id="bulk-import-payslips" class="bntm-btn-primary">Import Selected to Finance</button>
                <button id="bulk-revert-payslips" class="bntm-btn-secondary">Revert Selected</button>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <p>No payslips found.</p>
        <?php endif; ?>
    </div>
    
    <script>
    (function() {
        const bntmAjax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('bntm_hr_nonce'); ?>'
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle bulk generation
            const bulkCheckbox = document.getElementById('bulk-generate');
            if (bulkCheckbox) {
                bulkCheckbox.addEventListener('change', function() {
                    const singleSection = document.getElementById('single-employee-section');
                    const bulkSection = document.getElementById('bulk-employee-section');
                    const employeeSelect = document.getElementById('employee-select');
                    
                    if (this.checked) {
                        singleSection.style.display = 'none';
                        bulkSection.style.display = 'block';
                        employeeSelect.removeAttribute('required');
                    } else {
                        singleSection.style.display = 'block';
                        bulkSection.style.display = 'none';
                        employeeSelect.setAttribute('required', 'required');
                    }
                });
            }
            
            // Open modal
            const generateBtn = document.getElementById('generate-payslip-btn');
            const modal = document.getElementById('payslip-modal');
            if (generateBtn) {
                generateBtn.addEventListener('click', () => modal.style.display = 'flex');
            }
            
            // Close modal
            const closeBtn = document.getElementById('close-payslip-modal');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    modal.style.display = 'none';
                    document.getElementById('generate-payslip-form').reset();
                    document.getElementById('payslip-modal-message').innerHTML = '';
                });
            }
            
            // Generate payslip
            const form = document.getElementById('generate-payslip-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const messageDiv = document.getElementById('payslip-modal-message');
                    const isBulk = document.getElementById('bulk-generate').checked;
                    
                    let employeeIds = [];
                    if (isBulk) {
                        document.querySelectorAll('.bulk-employee:checked').forEach(cb => {
                            employeeIds.push(cb.value);
                        });
                        
                        if (employeeIds.length === 0) {
                            messageDiv.innerHTML = '<p style="color: red;">Please select at least one employee.</p>';
                            return;
                        }
                    } else {
                        const singleId = document.getElementById('employee-select').value;
                        if (!singleId) {
                            messageDiv.innerHTML = '<p style="color: red;">Please select an employee.</p>';
                            return;
                        }
                        employeeIds = [singleId];
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'bntm_hr_generate_payslip');
                    formData.append('nonce', bntmAjax.nonce);
                    formData.append('employee_ids', JSON.stringify(employeeIds));
                    formData.append('period_start', this.querySelector('[name="period_start"]').value);
                    formData.append('period_end', this.querySelector('[name="period_end"]').value);
                    
                    messageDiv.innerHTML = '<p>Generating payslip(s)...</p>';
                    
                    try {
                        const response = await fetch(bntmAjax.ajax_url, {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            messageDiv.innerHTML = '<p style="color: green;">' + data.data.message + '</p>';
                            
                            // Download PDF
                            if (data.data.pdf_url) {
                                window.open(data.data.pdf_url, '_blank');
                            }
                            if (response.success && response.data.reload) {
                               location.reload(); // refresh page on success
                           }
                            
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            messageDiv.innerHTML = '<p style="color: red;">' + (data.data ? data.data.message : 'Error') + '</p>';
                        }
                    } catch (error) {
                        messageDiv.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                    }
                });
            }
            
            // Download individual payslip
            /*document.querySelectorAll('.download-payslip').forEach(btn => {
                btn.addEventListener('click', function() {
                    const url = bntmAjax.ajax_url + '?action=bntm_hr_download_payslip&payslip_id=' + this.dataset.id + '&nonce=' + bntmAjax.nonce;
                    window.open(url, '_blank');
                });
            });*/
            // REPLACE YOUR EXISTING DOWNLOAD HANDLER WITH THIS CODE:

         // Download individual payslip with one-time token
         document.querySelectorAll('.download-payslip').forEach(btn => {
             btn.addEventListener('click', async function() {
                 const payslipId = this.dataset.id;
                 
                 // Show loading state
                 const originalText = this.textContent;
                 this.textContent = 'Generating...';
                 this.disabled = true;
                 
                 try {
                     // Generate one-time token
                     const formData = new FormData();
                     formData.append('action', 'bntm_hr_generate_payslip_token');
                     formData.append('nonce', bntmAjax.nonce);
                     formData.append('payslip_id', payslipId);
                     
                     const response = await fetch(bntmAjax.ajax_url, {
                         method: 'POST',
                         body: formData
                     });
                     
                     const data = await response.json();
                     
                     if (data.success) {
                         // Open one-time URL in new window (will auto-trigger save)
                         const downloadWindow = window.open(data.data.url, '_blank');
                         
                         // Check if popup was blocked
                         if (!downloadWindow || downloadWindow.closed || typeof downloadWindow.closed == 'undefined') {
                             // Popup blocked - show message with manual link
                             const message = 'Please allow popups for this site, or click OK to download in current tab.';
                             if (confirm(message)) {
                                 window.location.href = data.data.url;
                             }
                         } else {
                             // Success - show info message
                             /*setTimeout(() => {
                                 alert('✓ Payslip download opened!\n\nNote: This link can only be used once for security.\nThe PDF will auto-save to your downloads folder.');
                             }, 500);*/
                         }
                     } else {
                         alert('Failed to generate download link: ' + (data.data?.message || 'Unknown error'));
                     }
                 } catch (error) {
                     alert('Error generating download link: ' + error.message);
                 } finally {
                     // Restore button state
                     this.textContent = originalText;
                     this.disabled = false;
                 }
             });
         });
            
            // Select all checkboxes
            const selectAll = document.getElementById('select-all-payslips');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.payslip-checkbox').forEach(cb => {
                        cb.checked = this.checked;
                    });
                });
            }
            
            // Bulk import
            const bulkImportBtn = document.getElementById('bulk-import-payslips');
            if (bulkImportBtn) {
                bulkImportBtn.addEventListener('click', async function() {
                    const selected = [];
                    document.querySelectorAll('.payslip-checkbox:checked').forEach(cb => {
                        if (cb.dataset.imported === '0') {
                            selected.push(cb.value);
                        }
                    });
                    
                    if (selected.length === 0) {
                        alert('No eligible payslips selected (not already imported).');
                        return;
                    }
                    
                    if (!confirm(`Import ${selected.length} payslip(s) to Finance as expenses?`)) return;
                    
                    const formData = new FormData();
                    formData.append('action', 'bntm_hr_import_payslips');
                    formData.append('nonce', bntmAjax.nonce);
                    formData.append('payslip_ids', JSON.stringify(selected));
                    
                    try {
                        const response = await fetch(bntmAjax.ajax_url, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        alert(data.data ? data.data.message : 'Operation completed');
                        if (data.success) location.reload();
                    } catch (error) {
                        alert('Error: ' + error.message);
                    }
                });
            }
            
            // Bulk revert
            const bulkRevertBtn = document.getElementById('bulk-revert-payslips');
            if (bulkRevertBtn) {
                bulkRevertBtn.addEventListener('click', async function() {
                    const selected = [];
                    document.querySelectorAll('.payslip-checkbox:checked').forEach(cb => {
                        if (cb.dataset.imported === '1') {
                            selected.push(cb.value);
                        }
                    });
                    
                    if (selected.length === 0) {
                        alert('No eligible payslips selected (already imported).');
                        return;
                    }
                    
                    if (!confirm(`Revert ${selected.length} payslip(s) from Finance?`)) return;
                    
                    const formData = new FormData();
                    formData.append('action', 'bntm_hr_revert_payslips');
                    formData.append('nonce', bntmAjax.nonce);
                    formData.append('payslip_ids', JSON.stringify(selected));
                    
                    try {
                        const response = await fetch(bntmAjax.ajax_url, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await response.json();
                        alert(data.data ? data.data.message : 'Operation completed');
                        if (data.success) location.reload();
                    } catch (error) {
                        alert('Error: ' + error.message);
                    }
                });
            }
        });
    })();
    </script>
    
    <?php
    return ob_get_clean();
}

// Add/Delete Deductions
add_action('wp_ajax_bntm_hr_add_deduction', 'bntm_ajax_hr_add_deduction');
function bntm_ajax_hr_add_deduction() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    $name = sanitize_text_field($_POST['name']);
    $type = sanitize_text_field($_POST['type']);
    $value = floatval($_POST['value']);
    $description = sanitize_text_field($_POST['description']);
    
    $deductions = bntm_get_setting('hr_deductions', '');
    $deductions_array = $deductions ? json_decode($deductions, true) : [];
    
    $key = sanitize_title($name);
    $deductions_array[$key] = [
        'name' => $name,
        'type' => $type,
        'value' => $value,
        'description' => $description
    ];
    
    bntm_set_setting('hr_deductions', json_encode($deductions_array));
    
    wp_send_json_success(['message' => 'Deduction added successfully!']);
}

add_action('wp_ajax_bntm_hr_delete_deduction', 'bntm_ajax_hr_delete_deduction');
function bntm_ajax_hr_delete_deduction() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    $key = sanitize_text_field($_POST['key']);
    
    $deductions = bntm_get_setting('hr_deductions', '');
    $deductions_array = $deductions ? json_decode($deductions, true) : [];
    
    if (isset($deductions_array[$key])) {
        unset($deductions_array[$key]);
        bntm_set_setting('hr_deductions', json_encode($deductions_array));
        wp_send_json_success(['message' => 'Deduction deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Deduction not found.']);
    }
}

// Generate Payslip
add_action('wp_ajax_bntm_hr_generate_payslip', 'bntm_ajax_hr_generate_payslip');
function bntm_ajax_hr_generate_payslip() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $employee_ids = json_decode(stripslashes($_POST['employee_ids']), true);
    $period_start = sanitize_text_field($_POST['period_start']);
    $period_end = sanitize_text_field($_POST['period_end']);
    
    $deductions = bntm_get_setting('hr_deductions', '');
    $deductions_array = $deductions ? json_decode($deductions, true) : [];
    
    $hourly_rate = floatval(bntm_get_setting('hr_hourly_rate', '15.00'));
    
    $generated_count = 0;
    
    foreach ($employee_ids as $employee_id) {
        // Calculate hours worked in period
        $total_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_hours), 0) FROM {$prefix}hr_attendance 
            WHERE employee_id = %d AND DATE(clock_in) BETWEEN %s AND %s",
            $employee_id,
            $period_start,
            $period_end
        ));
        
        $employee_hourly_rate = floatval(get_user_meta($employee_id, 'bntm_hourly_rate', true)) ?: $hourly_rate;
        $basic_pay = $total_hours * $employee_hourly_rate;
        
        // Calculate deductions
        $total_deductions = 0;
        $deductions_data = [];
        
        foreach ($deductions_array as $key => $deduction) {
            $amount = 0;
            if ($deduction['type'] === 'percentage') {
                $amount = ($basic_pay * $deduction['value']) / 100;
            } else {
                $amount = $deduction['value'];
            }
            $total_deductions += $amount;
            $deductions_data[$key] = [
                'name' => $deduction['name'],
                'type' => $deduction['type'],
                'value' => $deduction['value'],
                'amount' => $amount
            ];
        }
        
        $net_pay = $basic_pay - $total_deductions;
        
        // Insert payslip record
        $result = $wpdb->insert(
            $prefix . 'hr_payslips',
            [
                'rand_id' => bntm_rand_id(),
                'employee_id' => $employee_id,
                'business_id' => 1,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'basic_pay' => $basic_pay,
                'overtime_pay' => 0,
                'total_deductions' => $total_deductions,
                'net_pay' => $net_pay,
                'deductions_data' => json_encode($deductions_data)
            ],
            ['%s', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s']
        );
        
        if ($result) {
            $generated_count++;
        }
    }
    
    if ($generated_count > 0) {
        wp_send_json_success([
            'message' => "Generated {$generated_count} payslip(s) successfully!",
            'reload'  => true, // flag for frontend refresh
        ]);
    } else {
        wp_send_json_error(['message' => 'Failed to generate payslips.']);
    }
}
// IMPROVED PAYSLIP DOWNLOAD WITH DIRECT PDF GENERATION

// 1. MODIFIED DOWNLOAD FUNCTION - ONE-TIME VIEW WITH AUTO-SAVE
// Download Payslip PDF
add_action('wp_ajax_bntm_hr_download_payslip', 'bntm_ajax_hr_download_payslip');
add_action('wp_ajax_nopriv_bntm_hr_download_payslip', 'bntm_ajax_hr_download_payslip');
function bntm_ajax_hr_download_payslip() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    // Check for one-time token
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    
    if ($token) {
        // One-time token access
        $token_data = get_transient('payslip_token_' . $token);
        
        if (!$token_data) {
            wp_die('This download link has expired or already been used. Please generate a new download link.');
        }
        
        $payslip_id = $token_data['payslip_id'];
        
        // Delete the token immediately (one-time use)
        delete_transient('payslip_token_' . $token);
        
    } else {
        // Regular access (for initial generation)
        $payslip_id = sanitize_text_field($_GET['payslip_id']);
    }
    
    if ($payslip_id === 'latest') {
        $payslip = $wpdb->get_row("SELECT * FROM {$prefix}hr_payslips ORDER BY id DESC LIMIT 1");
    } else {
        $payslip = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_payslips WHERE id = %d",
            intval($payslip_id)
        ));
    }
    
    if (!$payslip) {
        wp_die('Payslip not found.');
    }
    
    // Get employee data
    $employee = get_userdata($payslip->employee_id);
    $position = get_user_meta($payslip->employee_id, 'bntm_position', true);
    $department = get_user_meta($payslip->employee_id, 'bntm_department', true);
    
    // Parse deductions
    $deductions_data = json_decode($payslip->deductions_data, true) ?: [];
    
    // Get site info
    $logo = bntm_get_site_logo();
    $site_title = bntm_get_site_title();
    
    // Generate filename
    $filename = 'Payslip_' . sanitize_file_name($employee->display_name) . '_' . date('Y-m-d', strtotime($payslip->period_end));
    
    // Generate PDF HTML using improved function
    $html = bntm_generate_payslip_pdf_html($payslip, $employee, $position, $department, $deductions_data, $logo, $site_title, $filename);
    
    // Output PDF using browser print
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    echo '<script>window.print();</script>';
    exit;
}

// 1b. GENERATE ONE-TIME DOWNLOAD TOKEN
add_action('wp_ajax_bntm_hr_generate_payslip_token', 'bntm_ajax_generate_payslip_token');
function bntm_ajax_generate_payslip_token() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $payslip_id = isset($_POST['payslip_id']) ? intval($_POST['payslip_id']) : 0;
    
    if (!$payslip_id) {
        wp_send_json_error(['message' => 'Invalid payslip ID']);
    }
    
    // Generate unique token
    $token = wp_generate_password(32, false);
    
    // Store token with 5 minutes expiry
    set_transient('payslip_token_' . $token, [
        'payslip_id' => $payslip_id,
        'generated_at' => time()
    ], 300); // 5 minutes
    
    // Build one-time URL
    $url = admin_url('admin-ajax.php') . '?action=bntm_hr_download_payslip&token=' . $token . '&nonce=' . wp_create_nonce('bntm_hr_nonce');
    
    wp_send_json_success(['url' => $url]);
}
// 2. ENHANCED PDF HTML TEMPLATE WITH LOGO AND PROFESSIONAL DESIGN
function bntm_generate_payslip_pdf_html($payslip, $employee, $position, $department, $deductions_data, $logo, $site_title) {
    // Sanitize outputs
    $employee_name = esc_html($employee->display_name);
    $position_text = esc_html($position ?: 'N/A');
    $department_text = esc_html($department ?: 'N/A');
    $employee_id = intval($payslip->employee_id);
    $site_name = esc_html($site_title ?: get_bloginfo('name'));
    
    // Format dates
    $period_start = date('F d, Y', strtotime($payslip->period_start));
    $period_end = date('F d, Y', strtotime($payslip->period_end));
    $period_short = date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end));
    $generated_date = date('F d, Y h:i A');
    $payslip_number = 'PS-' . str_pad($payslip->id, 6, '0', STR_PAD_LEFT);
    
    // Calculate amounts
    $basic_pay = floatval($payslip->basic_pay);
    $overtime_pay = floatval($payslip->overtime_pay);
    $total_deductions = floatval($payslip->total_deductions);
    $gross_pay = $basic_pay + $overtime_pay;
    $net_pay = floatval($payslip->net_pay);
    
    // Logo handling
    $logo_html = '';
    if (!empty($logo)) {
        $logo_html = '<img src="' . esc_url($logo) . '" alt="' . $site_name . '" style="max-height: 60px; max-width: 200px; object-fit: contain;">';
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payslip - <?php echo $employee_name; ?> - <?php echo $period_short; ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            @page {
                size: A4;
                margin: 1.5cm;
            }
            
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                }
                .no-print {
                    display: none !important;
                }
                .page-break {
                    page-break-after: always;
                }
            }
            
            body {
                font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: #1f2937;
                background: #ffffff;
                padding: 20px;
                max-width: 210mm;
                margin: 0 auto;
            }
            
            /* Header Section */
            .document-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding-bottom: 20px;
                border-bottom: 3px solid #2563eb;
                margin-bottom: 30px;
            }
            
            .company-info {
                flex: 1;
            }
            
            .company-logo {
                margin-bottom: 10px;
            }
            
            .company-name {
                font-size: 24px;
                font-weight: 700;
                color: #1e40af;
                margin-bottom: 5px;
            }
            
            .document-info {
                text-align: right;
            }
            
            .document-title {
                font-size: 32px;
                font-weight: 700;
                color: #1e40af;
                margin-bottom: 5px;
                letter-spacing: 1px;
            }
            
            .payslip-number {
                font-size: 14px;
                color: #6b7280;
                margin-bottom: 10px;
            }
            
            .period-badge {
                display: inline-block;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                padding: 8px 16px;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 600;
            }
            
            /* Employee Information Card */
            .employee-card {
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 25px;
                margin-bottom: 30px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }
            
            .employee-card-title {
                font-size: 14px;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 15px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .info-item {
                display: flex;
                align-items: baseline;
            }
            
            .info-label {
                font-weight: 600;
                color: #475569;
                min-width: 120px;
                font-size: 14px;
            }
            
            .info-value {
                color: #1f2937;
                font-size: 14px;
            }
            
            /* Earnings & Deductions Table */
            .earnings-section {
                margin-bottom: 30px;
            }
            
            .section-title {
                font-size: 16px;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #e5e7eb;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            thead {
                background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
                color: white;
            }
            
            th {
                padding: 12px 15px;
                text-align: left;
                font-weight: 600;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            th.text-right {
                text-align: right;
            }
            
            tbody tr {
                border-bottom: 1px solid #e5e7eb;
            }
            
            tbody tr:hover {
                background: #f9fafb;
            }
            
            td {
                padding: 12px 15px;
                font-size: 14px;
            }
            
            td.text-right {
                text-align: right;
                font-weight: 600;
            }
            
            .amount-positive {
                color: #059669;
            }
            
            .amount-negative {
                color: #dc2626;
            }
            
            .row-highlight {
                background: #fef3c7 !important;
                font-weight: 600;
            }
            
            .row-subtotal {
                background: #f3f4f6 !important;
                font-weight: 600;
            }
            
            /* Summary Box */
            .summary-container {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border: 2px solid #bae6fd;
                border-radius: 12px;
                padding: 25px;
                margin: 30px 0;
            }
            
            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                font-size: 15px;
            }
            
            .summary-row.total {
                border-top: 3px solid #2563eb;
                margin-top: 15px;
                padding-top: 20px;
            }
            
            .summary-label {
                font-weight: 600;
                color: #0f172a;
            }
            
            .summary-value {
                font-weight: 700;
            }
            
            .summary-row.total .summary-label {
                font-size: 20px;
                color: #1e40af;
            }
            
            .summary-row.total .summary-value {
                font-size: 24px;
                color: #059669;
            }
            
            /* Footer */
            .document-footer {
                margin-top: 50px;
                padding-top: 20px;
                border-top: 2px solid #e5e7eb;
                text-align: center;
            }
            
            .footer-note {
                font-size: 12px;
                color: #6b7280;
                margin-bottom: 8px;
                font-style: italic;
            }
            
            .footer-notice {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 12px 15px;
                margin: 15px 0;
                font-size: 12px;
                color: #92400e;
                text-align: left;
            }
            
            .generated-info {
                font-size: 11px;
                color: #9ca3af;
                margin-top: 10px;
            }
            
            /* Watermark */
            .confidential-watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 100px;
                color: rgba(0, 0, 0, 0.03);
                font-weight: 900;
                z-index: -1;
                pointer-events: none;
            }
        </style>
    </head>
    <body>
        <!-- Watermark -->
        <div class="confidential-watermark">CONFIDENTIAL</div>
        
        <!-- Header -->
        <div class="document-header">
            <div class="company-info">
                <?php if ($logo_html): ?>
                    <div class="company-logo"><?php echo $logo_html; ?></div>
                <?php endif; ?>
                <div class="company-name"><?php echo $site_name; ?></div>
            </div>
            <div class="document-info">
                <div class="document-title">PAYSLIP</div>
                <div class="payslip-number"><?php echo $payslip_number; ?></div>
                <div class="period-badge">
                    <?php echo $period_short; ?>
                </div>
            </div>
        </div>
        
        <!-- Employee Information -->
        <div class="employee-card">
            <div class="employee-card-title">Employee Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Employee Name:</span>
                    <span class="info-value"><?php echo $employee_name; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Employee ID:</span>
                    <span class="info-value">#<?php echo $employee_id; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Position:</span>
                    <span class="info-value"><?php echo $position_text; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Department:</span>
                    <span class="info-value"><?php echo $department_text; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Earnings & Deductions -->
        <div class="earnings-section">
            <div class="section-title">Earnings & Deductions Breakdown</div>
            
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Earnings -->
                    <tr class="row-highlight">
                        <td colspan="2"><strong>EARNINGS</strong></td>
                    </tr>
                    <tr>
                        <td>Basic Pay</td>
                        <td class="text-right amount-positive">₱<?php echo number_format($basic_pay, 2); ?></td>
                    </tr>
                    <?php if ($overtime_pay > 0): ?>
                    <tr>
                        <td>Overtime Pay</td>
                        <td class="text-right amount-positive">₱<?php echo number_format($overtime_pay, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="row-subtotal">
                        <td><strong>Gross Pay</strong></td>
                        <td class="text-right"><strong>₱<?php echo number_format($gross_pay, 2); ?></strong></td>
                    </tr>
                    
                    <?php if (!empty($deductions_data)): ?>
                    <!-- Deductions -->
                    <tr class="row-highlight">
                        <td colspan="2"><strong>DEDUCTIONS</strong></td>
                    </tr>
                    <?php foreach ($deductions_data as $deduction): ?>
                    <tr>
                        <td><?php echo esc_html($deduction['name'] ?? 'Unknown Deduction'); ?></td>
                        <td class="text-right amount-negative">-₱<?php echo number_format(floatval($deduction['amount'] ?? 0), 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="row-subtotal">
                        <td><strong>Total Deductions</strong></td>
                        <td class="text-right amount-negative"><strong>-₱<?php echo number_format($total_deductions, 2); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary -->
        <div class="summary-container">
            <div class="summary-row">
                <span class="summary-label">Gross Pay:</span>
                <span class="summary-value">₱<?php echo number_format($gross_pay, 2); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Deductions:</span>
                <span class="summary-value amount-negative">-₱<?php echo number_format($total_deductions, 2); ?></span>
            </div>
            <div class="summary-row total">
                <span class="summary-label">NET PAY:</span>
                <span class="summary-value">₱<?php echo number_format($net_pay, 2); ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="document-footer">
            <div class="footer-notice">
                <strong>⚠️ IMPORTANT NOTICE:</strong> This payslip is confidential and intended solely for the named employee. 
                Any unauthorized distribution, disclosure, or copying of this document is strictly prohibited.
            </div>
            
            <div class="footer-note">
                This is a computer-generated payslip and does not require a physical signature.
            </div>
            <div class="footer-note">
                For any discrepancies or questions, please contact the HR Department immediately.
            </div>
            
            <div class="generated-info">
                Generated on <?php echo $generated_date; ?> | Document ID: <?php echo $payslip_number; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Import Payslips to Finance
add_action('wp_ajax_bntm_hr_import_payslips', 'bntm_ajax_hr_import_payslips');
function bntm_ajax_hr_import_payslips() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $payslip_ids = json_decode(stripslashes($_POST['payslip_ids']), true);
    
    if (empty($payslip_ids)) {
        wp_send_json_error(['message' => 'No payslips selected.']);
    }
    
    $imported_count = 0;
    
    foreach ($payslip_ids as $payslip_id) {
        $payslip = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}hr_payslips WHERE id = %d AND is_imported = 0",
            $payslip_id
        ));
        
        if (!$payslip) continue;
        
        $employee = get_userdata($payslip->employee_id);
        
        // Insert into finance transactions
        $result = $wpdb->insert(
            $prefix . 'fn_transactions',
            [
                'rand_id' => bntm_rand_id(),
                'business_id' => 0,
                'type' => 'expense',
                'amount' => $payslip->net_pay,
                'category' => 'Payroll',
                'notes' => 'Payslip for ' . $employee->display_name . ' (' . date('M d', strtotime($payslip->period_start)) . ' - ' . date('M d, Y', strtotime($payslip->period_end)) . ')',
                'reference_type' => 'payslip',
                'reference_id' => $payslip->id
            ],
            ['%s', '%d', '%s', '%f', '%s', '%s', '%s', '%d']
        );
        
        if ($result) {
            // Mark as imported
            $wpdb->update(
                $prefix . 'hr_payslips',
                ['is_imported' => 1],
                ['id' => $payslip->id],
                ['%d'],
                ['%d']
            );
            $imported_count++;
        }
    }
    
    if ($imported_count > 0) {
        // Update cashflow summary if function exists
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        
        wp_send_json_success(['message' => "Imported {$imported_count} payslip(s) to Finance successfully!"]);
    } else {
        wp_send_json_error(['message' => 'Failed to import payslips or already imported.']);
    }
}

// Revert Payslips from Finance
add_action('wp_ajax_bntm_hr_revert_payslips', 'bntm_ajax_hr_revert_payslips');
function bntm_ajax_hr_revert_payslips() {
    check_ajax_referer('bntm_hr_nonce', 'nonce');
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    
    if (!$is_wp_admin && !in_array($current_role, ['owner', 'manager'])) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }
    
    global $wpdb;
    $prefix = $wpdb->prefix;
    
    $payslip_ids = json_decode(stripslashes($_POST['payslip_ids']), true);
    
    if (empty($payslip_ids)) {
        wp_send_json_error(['message' => 'No payslips selected.']);
    }
    
    $reverted_count = 0;
    
    foreach ($payslip_ids as $payslip_id) {
        // Delete from finance transactions
        $result = $wpdb->delete(
            $prefix . 'fn_transactions',
            [
                'reference_type' => 'payslip',
                'reference_id' => $payslip_id
            ],
            ['%s', '%d']
        );
        
        if ($result) {
            // Mark as not imported
            $wpdb->update(
                $prefix . 'hr_payslips',
                ['is_imported' => 0],
                ['id' => $payslip_id],
                ['%d'],
                ['%d']
            );
            $reverted_count++;
        }
    }
    
    if ($reverted_count > 0) {
        // Update cashflow summary if function exists
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        
        wp_send_json_success(['message' => "Reverted {$reverted_count} payslip(s) from Finance successfully!"]);
    } else {
        wp_send_json_error(['message' => 'Failed to revert payslips.']);
    }
}
           