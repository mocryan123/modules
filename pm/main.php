<?php
/**
 * Module Name: Project Management
 * Module Slug: pm
 * Description: Complete project and task management solution with team collaboration
 * Version: 
 * Author: Your Name
 * Icon: 📋
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Module constants
define("BNTM_PM_PATH", dirname(__FILE__) . "/");
define("BNTM_PM_URL", plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 */
function bntm_pm_get_pages()
{
    return [
        "Projects" => "[pm_dashboard]",
    ];
}

/**
 * Get module database tables
 */
function bntm_pm_get_tables()
{
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    return [
        "pm_projects" => "CREATE TABLE {$prefix}pm_projects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            client_name VARCHAR(255),
            client_id BIGINT UNSIGNED DEFAULT NULL,
            description TEXT,
            start_date DATE,
            due_date DATE,
            budget DECIMAL(12,2) DEFAULT 0,
            status VARCHAR(50) DEFAULT 'planning',
            progress INT DEFAULT 0,
            color VARCHAR(20) DEFAULT '#3b82f6',
            image VARCHAR(500),
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_client (client_id),
            INDEX idx_status (status),
            INDEX idx_dates (start_date, due_date),
            INDEX idx_sort (sort_order)
        ) {$charset};",

        "pm_tasks" => "CREATE TABLE {$prefix}pm_tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'To Do',
            priority VARCHAR(20) DEFAULT 'medium',
            assigned_to BIGINT UNSIGNED DEFAULT NULL,
            parent_task_id BIGINT UNSIGNED DEFAULT NULL,
            due_date DATE,
            completed_date DATETIME,
            estimated_hours DECIMAL(5,2) DEFAULT 0,
            actual_hours DECIMAL(5,2) DEFAULT 0,
            tags VARCHAR(500),
            milestone_id BIGINT UNSIGNED DEFAULT NULL,
            recurring VARCHAR(50) DEFAULT 'none',
            google_calendar_event_id VARCHAR(255),
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_project (project_id),
            INDEX idx_assigned (assigned_to),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_due_date (due_date),
            INDEX idx_milestone (milestone_id),
            INDEX idx_parent (parent_task_id)
        ) {$charset};",

        "pm_milestones" => "CREATE TABLE {$prefix}pm_milestones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            due_date DATE,
            status VARCHAR(50) DEFAULT 'pending',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_project (project_id),
            INDEX idx_status (status)
        ) {$charset};",

        "pm_team_members" => "CREATE TABLE {$prefix}pm_team_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50) DEFAULT 'staff',
            joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_project (project_id),
            INDEX idx_user (user_id),
            UNIQUE KEY unique_project_user (project_id, user_id)
        ) {$charset};",

        "pm_time_logs" => "CREATE TABLE {$prefix}pm_time_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            task_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            hours DECIMAL(5,2) NOT NULL,
            log_date DATE NOT NULL,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_task (task_id),
            INDEX idx_user (user_id),
            INDEX idx_date (log_date)
        ) {$charset};",

        "pm_comments" => "CREATE TABLE {$prefix}pm_comments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            task_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_task (task_id),
            INDEX idx_user (user_id)
        ) {$charset};",

        "pm_files" => "CREATE TABLE {$prefix}pm_files (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED DEFAULT NULL,
            task_id BIGINT UNSIGNED DEFAULT NULL,
            filename VARCHAR(255) NOT NULL,
            file_url VARCHAR(500) NOT NULL,
            file_size INT DEFAULT 0,
            file_type VARCHAR(50),
            uploaded_by BIGINT UNSIGNED NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_project (project_id),
            INDEX idx_task (task_id)
        ) {$charset};",

        "pm_activity_log" => "CREATE TABLE {$prefix}pm_activity_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED DEFAULT NULL,
            task_id BIGINT UNSIGNED DEFAULT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_project (project_id),
            INDEX idx_task (task_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) {$charset};",

        "pm_project_statuses" => "CREATE TABLE {$prefix}pm_project_statuses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL,
            status_name VARCHAR(100) NOT NULL,
            status_color VARCHAR(20) DEFAULT '#3b82f6',
            sort_order INT DEFAULT 0,
            is_default TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_project (project_id),
            INDEX idx_sort (sort_order)
        ) {$charset};",
    ];
}

/**
 * Get module shortcodes
 */
function bntm_pm_get_shortcodes()
{
    return [
        "pm_dashboard" => "bntm_shortcode_pm_dashboard",
    ];
}

/**
 * Create module tables
 */
function bntm_pm_create_tables()
{
    require_once ABSPATH . "wp-admin/includes/upgrade.php";

    $tables = bntm_pm_get_tables();

    foreach ($tables as $sql) {
        dbDelta($sql);
    }

    // Initialize default statuses for existing projects
    pm_initialize_default_statuses();

    return count($tables);
}

/**
 * Initialize default task statuses for projects
 */
function pm_initialize_default_statuses()
{
    global $wpdb;
    $statuses_table = $wpdb->prefix . "pm_project_statuses";
    $projects_table = $wpdb->prefix . "pm_projects";

    $default_statuses = [
        ["name" => "To Do", "color" => "#6b7280", "order" => 1],
        ["name" => "In Progress", "color" => "#3b82f6", "order" => 2],
        ["name" => "Review", "color" => "#f59e0b", "order" => 3],
        ["name" => "Completed", "color" => "#10b981", "order" => 4],
        ["name" => "Closed", "color" => "#000", "order" => 5],
    ];

    // Get all projects without custom statuses
    $projects = $wpdb->get_results(
        "SELECT id, business_id FROM {$projects_table}"
    );

    foreach ($projects as $project) {
        $has_statuses = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$statuses_table} WHERE project_id = %d",
                $project->id
            )
        );

        if ($has_statuses == 0) {
            foreach ($default_statuses as $status) {
                $wpdb->insert(
                    $statuses_table,
                    [
                        "rand_id" => bntm_rand_id(),
                        "business_id" => $project->business_id,
                        "project_id" => $project->id,
                        "status_name" => $status["name"],
                        "status_color" => $status["color"],
                        "sort_order" => $status["order"],
                        "is_default" => $status["name"] === "To Do" ? 1 : 0,
                    ],
                    ["%s", "%d", "%d", "%s", "%s", "%d", "%d"]
                );
            }
        }
    }
}

/* ---------- AJAX HANDLERS REGISTRATION ---------- */

add_action("wp_ajax_pm_create_project", "bntm_ajax_pm_create_project");
add_action("wp_ajax_pm_update_project", "bntm_ajax_pm_update_project");
add_action("wp_ajax_pm_delete_project", "bntm_ajax_pm_delete_project");
add_action("wp_ajax_pm_create_task", "bntm_ajax_pm_create_task");
add_action("wp_ajax_pm_update_task", "bntm_ajax_pm_update_task");
add_action("wp_ajax_pm_update_task_status", "bntm_ajax_pm_update_task_status");
add_action("wp_ajax_pm_delete_task", "bntm_ajax_pm_delete_task");
add_action("wp_ajax_pm_reorder_tasks", "bntm_ajax_pm_reorder_tasks");
add_action('wp_ajax_pm_import_tasks', 'bntm_ajax_pm_import_tasks');
add_action('wp_ajax_pm_export_tasks', 'bntm_ajax_pm_export_tasks');
add_action('wp_ajax_pm_export_tasks', 'bntm_ajax_pm_export_tasks');
add_action("wp_ajax_pm_create_milestone", "bntm_ajax_pm_create_milestone");
add_action("wp_ajax_pm_update_milestone", "bntm_ajax_pm_update_milestone");
add_action("wp_ajax_pm_delete_milestone", "bntm_ajax_pm_delete_milestone");
add_action("wp_ajax_pm_reorder_milestones", "bntm_ajax_pm_reorder_milestones");
add_action("wp_ajax_pm_export_to_google_calendar", "bntm_ajax_pm_export_to_google_calendar");
add_action("wp_ajax_pm_save_google_calendar_settings", "bntm_ajax_pm_save_google_calendar_settings");
add_action("wp_ajax_pm_get_google_calendar_auth_url", "bntm_ajax_pm_get_google_calendar_auth_url");
add_action("wp_ajax_pm_add_team_member", "bntm_ajax_pm_add_team_member");
add_action("wp_ajax_pm_remove_team_member", "bntm_ajax_pm_remove_team_member");
add_action("wp_ajax_pm_add_time_log", "bntm_ajax_pm_add_time_log");
add_action("wp_ajax_pm_delete_time_log", "bntm_ajax_pm_delete_time_log");
add_action("wp_ajax_pm_add_comment", "bntm_ajax_pm_add_comment");
add_action("wp_ajax_pm_delete_comment", "bntm_ajax_pm_delete_comment");
add_action("wp_ajax_pm_get_task_details", "bntm_ajax_pm_get_task_details");
add_action("wp_ajax_pm_create_status", "bntm_ajax_pm_create_status");
add_action("wp_ajax_pm_update_status", "bntm_ajax_pm_update_status");
add_action("wp_ajax_pm_delete_status", "bntm_ajax_pm_delete_status");
add_action("wp_ajax_pm_reorder_statuses", "bntm_ajax_pm_reorder_statuses");
add_action("wp_ajax_pm_update_project_progress","bntm_ajax_pm_update_project_progress");
add_action("wp_ajax_pm_log_activity", "bntm_ajax_pm_log_activity"); //INCLUDE ON ALL AJAX FUNCTION
add_action('wp_ajax_pm_reorder_projects', 'bntm_ajax_pm_reorder_projects');

/* ---------- MAIN DASHBOARD SHORTCODE ---------- */

function bntm_shortcode_pm_dashboard()
{
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access Project Management.</div>';
    }

    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_access_resources = $is_wp_admin || in_array($current_role, ['owner', 'manager']);


    // Check if viewing specific project
    if (isset($_GET["project_id"])) {
        return pm_project_detail_page($business_id);
    }

    // Main dashboard
    $active_tab = isset($_GET["tab"])
        ? sanitize_text_field($_GET["tab"])
        : "overview";

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
    var pmCurrentUser = <?php echo $current_user->ID; ?>;
    </script>
    
    <div class="bntm-pm-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === "overview" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Overview
            </a>
            <a href="?tab=projects" class="bntm-tab <?php echo $active_tab === "projects" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Projects
            </a>
            <a href="?tab=board" class="bntm-tab <?php echo $active_tab === "board" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Project Board
            </a>
            <a href="?tab=reports" class="bntm-tab <?php echo $active_tab === "reports" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reports
            </a>
            <?php if ($can_access_resources): ?>
            <a href="?tab=resources" class="bntm-tab <?php echo $active_tab === "resources" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Resources
            </a>
            <?php endif; ?>
            
            <?php if ($can_access_resources): ?>
            
            <?php endif; ?>
            <a href="?tab=logs" class="bntm-tab <?php echo $active_tab === "logs" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Logs
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php 
            if ($active_tab === "overview") {
                echo pm_overview_tab($business_id);
            } elseif ($active_tab === "projects") {
                echo pm_projects_tab($business_id);
            } elseif ($active_tab === "board") {
                echo pm_board_tab($business_id);
            } elseif ($active_tab === "reports") {
                echo pm_reports_tab($business_id);
            } elseif ($active_tab === "resources") {
                // Check permission before displaying resources tab
                if ($can_access_resources) {
                    echo pm_resource_load_tab($business_id);
                } else {
                    ?>
                    <div style="background: white; border-radius: 12px; padding: 60px 24px; text-align: center;">
                        <svg width="64" height="64" fill="none" stroke="#d1d5db" viewBox="0 0 24 24" style="margin: 0 auto 20px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <h3 style="margin: 0 0 8px 0; font-size: 20px; color: #374151;">Access Restricted</h3>
                        <p style="margin: 0; color: #6b7280; font-size: 15px;">You don't have permission to view resource management. This feature is only available to administrators, owners, and managers.</p>
                    </div>
                    <?php
                }
            } elseif ($active_tab === "logs") {
            echo pm_logs_tab($business_id);
        }
            ?>
        </div>
    </div>
      <style>
    
/* ========================================
   GLOBAL STYLES
======================================== */


.pm-status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    color: white;
    text-transform: capitalize;
    white-space: nowrap;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* ========================================
   MODAL STYLES
======================================== */
.bntm-form {
    padding: 24px;
}

.bntm-form-group {
    margin-bottom: 20px;
}
.bntm-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: modalFadeIn 0.2s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.bntm-modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.bntm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 24px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.bntm-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.bntm-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #9ca3af;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: all 0.2s;
    line-height: 1;
}

.bntm-modal-close:hover {
    background: #f3f4f6;
    color: #111827;
}

.bntm-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 24px 24px;
    border-top: 1px solid #e5e7eb;
    margin-top: 24px;
}
    </style>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container("Project Management", $content);
}

/* ---------- TAB FUNCTIONS ---------- */

/**
 * Overview Tab
 */
function pm_overview_tab($business_id)
{
    global $wpdb;
    $projects_table = $wpdb->prefix . "pm_projects";
    $tasks_table = $wpdb->prefix . "pm_tasks";
    $time_logs_table = $wpdb->prefix . "pm_time_logs";
    $team_table = $wpdb->prefix . "pm_team_members";

    // --- ACCESS CONTROL: determine visible project scope ---
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_manage_globally = $is_wp_admin || in_array($current_role, ['owner', 'manager']);

    if ($can_manage_globally) {
        // No project filter — see everything
        $project_filter_sql = "";         // for WHERE clauses that start with WHERE
        $project_filter_and = "";         // for WHERE clauses that already have a WHERE
        $project_join = "";               // no join needed
    } else {
        // Get the IDs of projects this user belongs to
        $member_project_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT project_id FROM {$team_table} WHERE user_id = %d",
            $current_user->ID
        ));

        if (empty($member_project_ids)) {
            // User has no projects — show zeros/empty everywhere
            $member_project_ids = [0]; // safe dummy that matches nothing
        }

        $ids_in = implode(',', array_map('intval', $member_project_ids));
        $project_filter_sql = "WHERE id IN ({$ids_in})";
        $project_filter_and = "AND project_id IN ({$ids_in})";
        $project_join = "INNER JOIN {$team_table} tm ON p.id = tm.project_id AND tm.user_id = {$current_user->ID}";
    }

    // Get statistics (scoped)
    $total_projects = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$projects_table} {$project_filter_sql}"
    );
    $active_projects = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$projects_table} 
         WHERE status NOT IN ('completed', 'cancelled', 'on_hold')
         " . ($can_manage_globally ? "" : "AND id IN (" . implode(',', array_map('intval', $member_project_ids)) . ")")
    );
    $total_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE 1=1 {$project_filter_and}"
    );
    $completed_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tasks_table} 
         WHERE status IN ('completed', 'closed') {$project_filter_and}"
    );
    $overdue_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tasks_table} 
         WHERE due_date < CURDATE() 
           AND status NOT IN ('completed', 'closed') 
           {$project_filter_and}"
    );

    // Total hours logged (scoped)
    $total_hours = $wpdb->get_var(
        "SELECT SUM(tl.hours) FROM {$time_logs_table} tl 
         INNER JOIN {$tasks_table} t ON tl.task_id = t.id 
         WHERE 1=1 {$project_filter_and}"
    );
    $total_hours = $total_hours ? floatval($total_hours) : 0;

    // Recent projects (scoped)
    $recent_projects = $wpdb->get_results(
        "SELECT * FROM {$projects_table} 
         {$project_filter_sql}
         ORDER BY updated_at DESC LIMIT 5"
    );

    // Upcoming tasks (scoped)
    $upcoming_tasks = $wpdb->get_results(
        "SELECT t.*, p.name as project_name, p.color as project_color 
         FROM {$tasks_table} t 
         INNER JOIN {$projects_table} p ON t.project_id = p.id 
         WHERE t.status NOT IN ('completed', 'closed') 
           AND t.due_date IS NOT NULL
           {$project_filter_and}
         ORDER BY t.due_date ASC LIMIT 10"
    );

    // Project status distribution (scoped)
    $status_stats = $wpdb->get_results(
        "SELECT status, COUNT(*) as count 
         FROM {$projects_table} 
         {$project_filter_sql}
         GROUP BY status"
    );

    // Task priority distribution (scoped)
    $priority_stats = $wpdb->get_results(
        "SELECT priority, COUNT(*) as count 
         FROM {$tasks_table} 
         WHERE status NOT IN ('completed', 'closed') {$project_filter_and}
         GROUP BY priority"
    );

    // Get statistics
    $total_projects = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$projects_table} "
    );
    $active_projects = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$projects_table} WHERE  status NOT IN ('completed', 'cancelled','on_hold')"
    );
    $total_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tasks_table}"
    );
    $completed_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE status IN ('completed', 'closed')"
    );
    $overdue_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$tasks_table} WHERE  due_date < CURDATE() AND status NOT IN ('completed', 'closed')"
    );

    // Total hours logged
    $total_hours = $wpdb->get_var("SELECT SUM(tl.hours) FROM {$time_logs_table} tl 
        INNER JOIN {$tasks_table} t ON tl.task_id = t.id 
       ");
    $total_hours = $total_hours ? floatval($total_hours) : 0;

    // Get recent projects
    $recent_projects = $wpdb->get_results("SELECT * FROM {$projects_table} 
        ORDER BY updated_at DESC LIMIT 5");

    // Get upcoming tasks
    $upcoming_tasks = $wpdb->get_results("SELECT t.*, p.name as project_name, p.color as project_color 
        FROM {$tasks_table} t 
        INNER JOIN {$projects_table} p ON t.project_id = p.id 
        WHERE  t.status NOT IN ('completed', 'closed') AND t.due_date IS NOT NULL
        ORDER BY t.due_date ASC LIMIT 10");

    // Project status distribution
    $status_stats = $wpdb->get_results("SELECT status, COUNT(*) as count 
        FROM {$projects_table} 
        GROUP BY status");

    // Task priority distribution
    $priority_stats = $wpdb->get_results("SELECT priority, COUNT(*) as count 
        FROM {$tasks_table} WHERE status NOT IN ('completed', 'closed')
        GROUP BY priority");

    ob_start();
    ?>
    <div class="pm-overview-grid">
        <!-- Stats Cards -->
        <div class="bntm-stats-row">
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>Total Projects</h3>
                    <p class="stat-number"><?php echo number_format( $total_projects ); ?></p>
                    <span class="stat-label"><?php echo $active_projects; ?> active</span>
                </div>
            </div>

                    <p class="stat-number"><?php echo number_format(
                        $total_projects
                    ); ?></p>
                    <span class="stat-label"><?php echo $active_projects; ?> active</span>
                </div>
            </div>
            
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>Total Tasks</h3>
                    <p class="stat-number"><?php echo number_format( $total_tasks ); ?></p>
                    <span class="stat-label"><?php echo $completed_tasks; ?> completed</span>
                </div>
            </div>

                    <p class="stat-number"><?php echo number_format(
                        $total_tasks
                    ); ?></p>
                    <span class="stat-label"><?php echo $completed_tasks; ?> completed</span>
                </div>
            </div>
            
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>Hours Logged</h3>
                    <p class="stat-number"><?php echo number_format( $total_hours, 1 ); ?></p>
                    <span class="stat-label">Total time tracked</span>
                </div>
            </div>

                    <p class="stat-number"><?php echo number_format(
                        $total_hours,
                        1
                    ); ?></p>
                    <span class="stat-label">Total time tracked</span>
                </div>
            </div>
            
            <div class="bntm-stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <h3>Overdue Tasks</h3>
                    <p class="stat-number" style="color: #ef4444;"><?php echo number_format( $overdue_tasks ); ?></p>
                    <p class="stat-number" style="color: #ef4444;"><?php echo number_format(
                        $overdue_tasks
                    ); ?></p>
                    <span class="stat-label">Require attention</span>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="pm-charts-row">
            <div class="pm-chart-card">
                <h3>Project Status Distribution</h3>
                <div class="pm-chart-content">
                    <?php if ( ! empty( $status_stats ) ) : ?>
                        <div class="pm-bar-chart">
                            <?php
                            $max_count = max( array_column( $status_stats, 'count' ) );
                            foreach ( $status_stats as $stat ) :
                                $percentage = $max_count > 0 ? ( $stat->count / $max_count ) * 100 : 0;
                                $color      = pm_get_status_color( $stat->status );
                            ?>
                                <div class="chart-bar-item">
                                    <div class="chart-bar-label"><?php echo ucfirst( $stat->status ); ?></div>
                                    <div class="chart-bar-wrapper">
                                        <div class="chart-bar" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>"></div>
                                        <span class="chart-bar-value"><?php echo $stat->count; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="pm-empty-state">No projects yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pm-chart-card">
                <h3>Task Priority Distribution</h3>
                <div class="pm-chart-content">
                    <?php if ( ! empty( $priority_stats ) ) : ?>
                        <div class="pm-priority-chart">
                            <?php
                            $total_priority_tasks = array_sum( array_column( $priority_stats, 'count' ) );
                            foreach ( $priority_stats as $stat ) :
                                $percentage = $total_priority_tasks > 0 ? ( $stat->count / $total_priority_tasks ) * 100 : 0;
                                $color      = pm_get_priority_color( $stat->priority );
                            ?>
                                <div class="priority-item">
                                    <div class="priority-header">
                                        <span class="priority-badge" style="background: <?php echo $color; ?>">
                                            <?php echo ucfirst( $stat->priority ); ?>
                                        </span>
                                        <span class="priority-count"><?php echo $stat->count; ?> tasks</span>
                                    </div>
                                    <div class="priority-bar">
                                        <div class="priority-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p class="pm-empty-state">No active tasks</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Projects -->
        <div class="pm-section-card">
            <div class="section-header">
                <h3>Recent Projects</h3>
                <a href="?tab=projects" class="bntm-btn-link">View All →</a>
            </div>
            <div class="pm-projects-list">
                <?php if ( ! empty( $recent_projects ) ) : ?>
                    <?php foreach ( $recent_projects as $project ) :
                        $tasks_count = $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$tasks_table} WHERE project_id = %d",
                            $project->id
                        ) );
                        $completed_count = $wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$tasks_table}
                             WHERE project_id = %d AND status IN ('completed','closed')",
                            $project->id
                        ) );
                        $progress = $tasks_count > 0 ? round( ( $completed_count / $tasks_count ) * 100 ) : 0;
                    ?>
                        <div class="pm-project-item" onclick="window.location.href='?project_id=<?php echo $project->id; ?>'">
                            <div class="project-color-bar" style="background: <?php echo esc_attr( $project->color ); ?>"></div>
                            <div class="project-info">
                                <h4><?php echo esc_html( $project->name ); ?></h4>
                                <div class="project-meta">
                                    <span class="project-status" style="background: <?php echo pm_get_status_color( $project->status ); ?>">
                                        <?php echo ucfirst( $project->status ); ?>
                                    </span>
                                    <?php if ( $project->client_name ) : ?>
                                        <span class="project-client">
                                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                            </svg>
                                            <?php echo esc_html( $project->client_name ); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="project-tasks"><?php echo $completed_count; ?>/<?php echo $tasks_count; ?> tasks</span>
                                </div>
                                <div class="project-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%; background: <?php echo esc_attr( $project->color ); ?>"></div>
                                    </div>
                                    <span class="progress-text"><?php echo $progress; ?>%</span>
                                </div>
                            </div>
                            <?php if ( $project->due_date ) : ?>
                                <div class="project-due-date">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <?php echo date( 'M d, Y', strtotime( $project->due_date ) ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="pm-empty-state">No projects yet. <a href="?tab=projects">Create your first project</a></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="pm-section-card">
            <div class="section-header">
                <h3>Upcoming Tasks</h3>
                <a href="?tab=board" class="bntm-btn-link">View Board →</a>
            </div>
            <div class="pm-tasks-list">
                <?php if ( ! empty( $upcoming_tasks ) ) : ?>
                    <?php foreach ( $upcoming_tasks as $task ) :
                        $is_overdue    = strtotime( $task->due_date ) < time();
                        $assigned_user = $task->assigned_to ? get_userdata( $task->assigned_to ) : null;
                    ?>
                        <div class="pm-task-item <?php echo $is_overdue ? 'overdue' : ''; ?>">
                            <div class="task-checkbox">
                                <input type="checkbox" <?php echo $task->status === 'completed' ? 'checked' : ''; ?> disabled>
                            </div>
                            <div class="task-content">
                                <div class="task-header">
                                    <h4><?php echo esc_html( $task->title ); ?></h4>
                                    <span class="task-priority priority-<?php echo $task->priority; ?>">
                                        <?php echo ucfirst( $task->priority ); ?>
                                    </span>
                                </div>
                                <div class="task-meta">
                                    <span class="task-project" style="color: <?php echo esc_attr( $task->project_color ); ?>">
                                        <?php echo esc_html( $task->project_name ); ?>
                                    </span>
                                    <?php if ( $assigned_user ) : ?>
                                        <span class="task-assignee">
                                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                            </svg>
                                            <?php echo esc_html( $assigned_user->display_name ); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="task-due-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <?php echo date( 'M d', strtotime( $task->due_date ) ); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="pm-empty-state">No upcoming tasks</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <style>
    .pm-overview-grid { display: grid; gap: 24px; }
    .bntm-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .bntm-stat-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 16px; align-items: flex-start; }
    .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-content { flex: 1; }
    .stat-content h3 { margin: 0 0 8px 0; font-size: 14px; color: #6b7280; font-weight: 500; }
    .stat-number { margin: 0 0 4px 0; font-size: 32px; font-weight: 700; color: #111827; line-height: 1; }
    .stat-label { font-size: 13px; color: #9ca3af; }
    .pm-charts-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; }
    .pm-chart-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .pm-chart-card h3 { margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #111827; }
    .pm-bar-chart { display: flex; flex-direction: column; gap: 16px; }
    .chart-bar-item { display: flex; align-items: center; gap: 12px; }
    .chart-bar-label { min-width: 100px; font-size: 14px; color: #4b5563; font-weight: 500; }
    .chart-bar-wrapper { flex: 1; display: flex; align-items: center; gap: 8px; }
    .chart-bar { height: 32px; border-radius: 6px; transition: width 0.3s ease; min-width: 40px; }
    .chart-bar-value { font-size: 13px; font-weight: 600; color: #374151; }
    .pm-priority-chart { display: flex; flex-direction: column; gap: 16px; }
    .priority-item { display: flex; flex-direction: column; gap: 8px; }
    .priority-header { display: flex; justify-content: space-between; align-items: center; }
    .priority-badge { padding: 4px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; color: white; }
    .priority-count { font-size: 13px; color: #6b7280; font-weight: 500; }
    .priority-bar { height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; }
    .priority-fill { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
    .pm-section-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .section-header h3 { margin: 0; font-size: 16px; font-weight: 600; color: #111827; }
    .pm-projects-list { display: grid; gap: 16px; }
    .pm-project-item { position: relative; background: #f9fafb; border-radius: 8px; padding: 16px 16px 16px 20px; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    .pm-project-item:hover { background: #f3f4f6; transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .project-color-bar { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 8px 0 0 8px; }
    .project-info { flex: 1; }
    .project-info h4 { margin: 0 0 8px 0; font-size: 15px; font-weight: 600; color: #111827; }
    .project-meta { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; align-items: center; }
    .project-status { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; color: white; }
    .project-client, .project-tasks { font-size: 13px; color: #6b7280; display: flex; align-items: center; gap: 4px; }
    .project-progress { display: flex; align-items: center; gap: 12px; }
    .progress-bar { flex: 1; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 3px; transition: width 0.3s ease; }
    .progress-text { font-size: 13px; font-weight: 600; color: #374151; min-width: 40px; text-align: right; }
    .project-due-date { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #6b7280; padding: 8px 12px; background: white; border-radius: 6px; }
    .pm-tasks-list { display: grid; gap: 12px; }
    .pm-task-item { display: flex; gap: 12px; padding: 12px; background: #f9fafb; border-radius: 8px; transition: background 0.2s; }
    .pm-task-item:hover { background: #f3f4f6; }
    .pm-task-item.overdue { background: #fef2f2; }
    .task-checkbox input { width: 18px; height: 18px; cursor: pointer; }
    .task-content { flex: 1; }
    .task-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .task-content h4 { margin: 0; font-size: 14px; font-weight: 600; color: #111827; }
    .task-priority { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .task-priority.priority-low { background: #dbeafe; color: #1e40af; }
    .task-priority.priority-medium { background: #fef3c7; color: #92400e; }
    .task-priority.priority-high { background: #fee2e2; color: #991b1b; }
    .task-meta { display: flex; flex-wrap: wrap; gap: 12px; font-size: 13px; }
    .task-project { font-weight: 600; }
    .task-assignee, .task-due-date { display: flex; align-items: center; gap: 4px; color: #6b7280; }
    .task-due-date.overdue { color: #ef4444; font-weight: 600; }
    .pm-empty-state { text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 14px; }
    .pm-empty-state a { color: #3b82f6; text-decoration: none; font-weight: 600; }
    .pm-empty-state a:hover { text-decoration: underline; }
    </style>
    <?php
    return ob_get_clean();
}
/**
 * Projects Tab with Role-Based Permissions
 */
function pm_projects_tab($business_id)
{
    global $wpdb;
    $projects_table = $wpdb->prefix . "pm_projects";
    $tasks_table = $wpdb->prefix . "pm_tasks";
    $team_table = $wpdb->prefix . "pm_team_members";
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_manage_globally = $is_wp_admin || in_array($current_role, ['owner', 'manager']);
    
    // Fetch projects based on role
    if ($can_manage_globally) {
        // Admins, owners, managers see ALL projects
        $projects = $wpdb->get_results("SELECT * FROM {$projects_table}
            ORDER BY 
                CASE 
                    WHEN status = 'cancelled' THEN 3
                    WHEN status = 'on_hold' THEN 2
                    WHEN status = 'completed' THEN 1
                    ELSE 0
                END ASC,
                status ASC,
                created_at DESC");
    } else {
        // Members and project managers only see projects they belong to
        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT p.* FROM {$projects_table} p
            INNER JOIN {$team_table} tm ON p.id = tm.project_id
            WHERE tm.user_id = %d
            ORDER BY 
                CASE 
                    WHEN p.status = 'cancelled' THEN 3
                    WHEN p.status = 'on_hold' THEN 2
                    WHEN p.status = 'completed' THEN 1
                    ELSE 0
                END ASC,
                p.status ASC,
                p.created_at DESC",
            $current_user->ID
        ));
    }
    
    
    $nonce = wp_create_nonce("pm_project_nonce");
    ob_start();
    ?>
 <div class="pm-projects-container">
     <div class="pm-section-header">
         <h2>All Projects</h2>
         <?php if ($can_manage_globally): ?>
         <button class="bntm-btn-primary" onclick="pmOpenProjectModal()">
             <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
             </svg>
             New Project
         </button>
         <?php endif; ?>
     </div>
 <div class="pm-projects-table-container bntm-table-wrapper">
     <table class="bntm-table pm-projects-table">
         <thead>
             <tr>
                 <th>Project Name</th>
                 <th>Client</th>
                 <th>Status</th>
                 <th>Progress</th>
                 <th>Due Date</th>
                 <th>Budget</th>
                 <th>Tasks</th>
                 <th width="120">Actions</th>
             </tr>
         </thead>
         <tbody>
             <?php if (empty($projects)): ?>
             <tr>
                 <td colspan="8" style="text-align: center; padding: 40px;">
                     <div class="pm-empty-state">
                         <svg width="64" height="64" fill="none" stroke="#d1d5db" viewBox="0 0 24 24" style="margin: 0 auto 16px;">
                             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                         </svg>
                         <p>No projects yet. Create your first project to get started!</p>
                     </div>
                 </td>
             </tr>
             <?php else: ?>
                 <?php foreach ($projects as $project):
                     // Check user's role in this specific project
                     $user_project_role = $wpdb->get_var($wpdb->prepare(
                         "SELECT role FROM {$team_table} WHERE project_id = %d AND user_id = %d",
                         $project->id,
                         $current_user->ID
                     ));
                     
                     // User can manage if they are: admin, owner/manager globally, or project manager
                     $can_manage_project = $can_manage_globally || $user_project_role === 'project_manager';

                     $tasks_count = $wpdb->get_var(
                         $wpdb->prepare(
                             "SELECT COUNT(*) FROM {$tasks_table} WHERE project_id = %d",
                             $project->id
                         )
                     );
                     $completed_count = $wpdb->get_var(
                         $wpdb->prepare(
                             "SELECT COUNT(*) FROM {$tasks_table} WHERE project_id = %d AND status IN ('completed', 'closed')",
                             $project->id
                         )
                     );
                     $progress =
                         $tasks_count > 0
                             ? round(($completed_count / $tasks_count) * 100)
                             : 0;
                     ?>
                 <tr class="pm-project-row" onclick="window.location.href='?project_id=<?php echo $project->id; ?>'" style="cursor: pointer;">
                     <td>
                         <div style="display: flex; align-items: center; gap: 12px;">
                             <div style="width: 4px; height: 40px; background: <?php echo esc_attr(
                                 $project->color
                             ); ?>; border-radius: 2px;"></div>
                             <div>
                                 <div style="font-weight: 600; color: #111827; margin-bottom: 4px;">
                                     <?php echo esc_html($project->name); ?>
                                 </div>
                                 <?php if ($project->description): ?>
                                     <div style="font-size: 13px; color: #6b7280;">
                                         <?php echo esc_html(
                                             wp_trim_words(
                                                 $project->description,
                                                 10
                                             )
                                         ); ?>
                                     </div>
                                 <?php endif; ?>
                             </div>
                         </div>
                     </td>
                     <td>
                         <?php if ($project->client_name): ?>
                             <span style="display: flex; align-items: center; gap: 6px; color: #4b5563;">
                                 <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                     <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                 </svg>
                                 <?php echo esc_html($project->client_name); ?>
                             </span>
                         <?php else: ?>
                             <span style="color: #9ca3af;">—</span>
                         <?php endif; ?>
                     </td>
                     <td>
                         <span class="pm-status-badge" style="background: <?php echo pm_get_status_color(
                             $project->status
                         ); ?>">
                             <?php echo ucfirst($project->status); ?>
                         </span>
                     </td>
                     <td>
                         <div style="display: flex; align-items: center; gap: 8px;">
                             <div class="pm-mini-progress">
                                 <div class="pm-mini-progress-fill" style="width: <?php echo $progress; ?>%; background: <?php echo esc_attr(
    $project->color
); ?>"></div>
                             </div>
                             <span style="font-size: 13px; font-weight: 600; color: #374151; min-width: 35px;">
                                 <?php echo $progress; ?>%
                             </span>
                         </div>
                     </td>
                     <td>
                         <?php if ($project->due_date && $project->due_date !== '0000-00-00'): ?>
                             <?php $is_overdue =
                                 strtotime($project->due_date) < time() &&
                                 $project->status !== "completed"; ?>
                             <span style="color: <?php echo $is_overdue
                                 ? "#ef4444"
                                 : "#6b7280"; ?>; font-size: 13px;">
                                 <?php echo date(
                                     "M d, Y",
                                     strtotime($project->due_date)
                                 ); ?>
                             </span>
                         <?php else: ?>
                             <span style="color: #9ca3af;">—</span>
                         <?php endif; ?>
                     </td>
                     <td>
                         <?php if ($project->budget > 0): ?>
                             <span style="font-weight: 600; color: #059669;">
                                 ₱<?php echo number_format(
                                     $project->budget,
                                     2
                                 ); ?>
                             </span>
                         <?php else: ?>
                             <span style="color: #9ca3af;">—</span>
                         <?php endif; ?>
                     </td>
                     <td>
                         <span style="font-size: 13px; color: #4b5563;">
                             <strong><?php echo $completed_count; ?></strong>/<strong><?php echo $tasks_count; ?></strong>
                         </span>
                     </td>
                     <td onclick="event.stopPropagation();">
                         <?php if ($can_manage_project): ?>
                         <div style="display: flex; gap: 8px;">
                             <button class="bntm-btn-small bntm-btn-secondary" 
                                     onclick="pmEditProject(<?php echo htmlspecialchars(
                                         json_encode($project)
                                     ); ?>)"
                                     title="Edit">
                                 <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                 </svg>
                             </button>
                             <button class="bntm-btn-small bntm-btn-danger" 
                                     onclick="pmDeleteProject(<?php echo $project->id; ?>, '<?php echo esc_js(
    $project->name
); ?>')"
                                     title="Delete">
                                 <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                 </svg>
                             </button>
                         </div>
                         <?php else: ?>
                         <span style="color: #9ca3af; font-size: 12px;">View Only</span>
                         <?php endif; ?>
                     </td>
                 </tr>
                 <?php
                 endforeach; ?>
             <?php endif; ?>
         </tbody>
     </table>
 </div>
 </div>
 <!-- Project Modal -->
 <div id="pm-project-modal" class="bntm-modal" style="display: none;">
     <div class="bntm-modal-content" style="max-width: 600px;">
         <div class="bntm-modal-header">
             <h3 id="pm-project-modal-title">Create New Project</h3>
             <button class="bntm-modal-close" onclick="pmCloseProjectModal()">&times;</button>
         </div>
         <form id="pm-project-form" class="bntm-form">
             <input type="hidden" name="project_id" id="pm-project-id">
         <div class="bntm-form-row">
             <div class="bntm-form-group" style="flex: 1;">
                 <label>Project Name *</label>
                 <input type="text" name="name" id="pm-project-name" required>
             </div>
             <div class="bntm-form-group" style="width: 120px;">
                 <label>Color</label>
                 <input type="color" name="color" id="pm-project-color" value="#3b82f6">
             </div>
         </div>
         
         <div class="bntm-form-group">
             <label>Description</label>
             <textarea name="description" id="pm-project-description" rows="3"></textarea>
         </div>
         
         <div class="bntm-form-row">
             <div class="bntm-form-group">
                 <label>Client Name</label>
                 <input type="text" name="client_name" id="pm-project-client">
             </div>
             <div class="bntm-form-group">
                 <label>Status</label>
                 <select name="status" id="pm-project-status">
                     <option value="planning">Planning</option>
                     <option value="in_progress">In Progress</option>
                     <option value="on_hold">On Hold</option>
                     <option value="completed">Completed</option>
                     <option value="cancelled">Cancelled</option>
                 </select>
             </div>
         </div>
         
         <div class="bntm-form-row">
             <div class="bntm-form-group">
                 <label>Start Date</label>
                 <input type="date" name="start_date" id="pm-project-start">
             </div>
             <div class="bntm-form-group">
                 <label>Due Date</label>
                 <input type="date" name="due_date" id="pm-project-due">
             </div>
         </div>
         
         <div class="bntm-form-group">
             <label>Budget (₱)</label>
             <input type="number" name="budget" id="pm-project-budget" step="0.01" min="0">
         </div>
         
         <div class="bntm-modal-footer">
             <button type="button" class="bntm-btn-secondary" onclick="pmCloseProjectModal()">Cancel</button>
             <button type="submit" class="bntm-btn-primary">
                 <span id="pm-project-submit-text">Create Project</span>
             </button>
         </div>
     </form>
 </div>
 </div>
 <style>
 .pm-projects-container {
     background: white;
     border-radius: 12px;
     padding: 24px;
     box-shadow: 0 1px 3px rgba(0,0,0,0.1);
 }
 
 .pm-section-header {
     display: flex;
     justify-content: space-between;
     align-items: center;
     margin-bottom: 24px;
 }
 
 .pm-section-header h2 {
     margin: 0;
     font-size: 20px;
     font-weight: 600;
     color: #111827;
 }
 
 .pm-projects-table-container {
     overflow-x: auto;
 }
 
 .pm-projects-table {
     width: 100%;
 }
 
 .pm-project-row:hover {
     background: #f9fafb;
 }
 
 .pm-status-badge {
     display: inline-block;
     padding: 4px 12px;
     border-radius: 6px;
     font-size: 12px;
     font-weight: 600;
     color: white;
 }
 
 .pm-mini-progress {
     flex: 1;
     height: 6px;
     background: #e5e7eb;
     border-radius: 3px;
     overflow: hidden;
     min-width: 60px;
 }
 
 .pm-mini-progress-fill {
     height: 100%;
     border-radius: 3px;
     transition: width 0.3s ease;
 }
</style>
<script>
function pmOpenProjectModal() {
    document.getElementById('pm-project-modal').style.display = 'flex';
    document.getElementById('pm-project-form').reset();
    document.getElementById('pm-project-id').value = '';
    document.getElementById('pm-project-modal-title').textContent = 'Create New Project';
    document.getElementById('pm-project-submit-text').textContent = 'Create Project';
    document.getElementById('pm-project-color').value = '#3b82f6';
}

function pmCloseProjectModal() {
    document.getElementById('pm-project-modal').style.display = 'none';
}

function pmEditProject(project) {
    document.getElementById('pm-project-modal').style.display = 'flex';
    document.getElementById('pm-project-id').value = project.id;
    document.getElementById('pm-project-name').value = project.name;
    document.getElementById('pm-project-description').value = project.description || '';
    document.getElementById('pm-project-client').value = project.client_name || '';
    document.getElementById('pm-project-status').value = project.status;
    document.getElementById('pm-project-start').value = project.start_date || '';
    document.getElementById('pm-project-due').value = project.due_date || '';
    document.getElementById('pm-project-budget').value = project.budget || '';
    document.getElementById('pm-project-color').value = project.color || '#3b82f6';
    document.getElementById('pm-project-modal-title').textContent = 'Edit Project';
    document.getElementById('pm-project-submit-text').textContent = 'Update Project';
}

function pmDeleteProject(id, name) {
    if (!confirm('Delete project "' + name + '"?\n\nThis will also delete all tasks, milestones, and related data.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_project');
    formData.append('project_id', id);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Failed to delete project');
        }
    });
}

document.getElementById('pm-project-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const projectId = document.getElementById('pm-project-id').value;
    formData.append('action', projectId ? 'pm_update_project' : 'pm_create_project');
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = projectId ? 'Updating...' : 'Creating...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Operation failed');
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = projectId ? 'Update Project' : 'Create Project';
        }
    });
});
</script>
<?php return ob_get_clean();
}
/**
 * Project Board Tab
 */
function pm_board_tab( $business_id ) {
    global $wpdb;

    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table    = $wpdb->prefix . 'pm_tasks';

    // Task visibility filter
    $vis = pm_get_task_visibility( 't' );

    $projects = $wpdb->get_results(
        "SELECT * FROM {$projects_table}
         WHERE status NOT IN ('completed','cancelled','on_hold')
         ORDER BY sort_order ASC, created_at DESC"
    );

    ob_start();
    ?>
    <div class="pm-board-container">
        <div class="pm-board-header">
            <h2>Project Board</h2>
            <div class="pm-board-controls bntm-form-group" style="width: unset;">
                <div class="pm-board-filter-toggle">
                    <button class="pm-board-filter-btn active" data-filter="all" onclick="pmBoardFilterTasks('all')">All Tasks</button>
                    <button class="pm-board-filter-btn" data-filter="my" onclick="pmBoardFilterTasks('my')">My Tasks</button>
                </div>
                <select id="pm-board-filter" class="bntm-input" style="width: 150px;">
                    <option value="all">All Due Dates</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="overdue">Overdue</option>
                </select>
                <input type="text" id="pm-board-search" placeholder="Search tasks..." class="bntm-input" style="width: 250px;">
            </div>
        </div>

        <?php if ( empty( $projects ) ) : ?>
            <div class="pm-empty-board">
                <svg width="80" height="80" fill="none" stroke="#d1d5db" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3>No Active Projects</h3>
                <p>Create a project to start managing tasks</p>
                <a href="?tab=projects" class="bntm-btn-primary">Go to Projects</a>
            </div>
        <?php else : ?>
            <div class="pm-board-projects sortable-projects">
                <?php foreach ( $projects as $project ) :

                    // Build task query for this project — apply visibility filter
                    if ( $vis['where'] === '' ) {
                        $tasks = $wpdb->get_results( $wpdb->prepare(
                            "SELECT * FROM {$tasks_table}
                             WHERE project_id = %d AND status NOT IN ('completed','closed')
                             ORDER BY sort_order ASC, created_at DESC",
                            $project->id
                        ) );
                    } else {
                        $tasks = $wpdb->get_results( $wpdb->prepare(
                            "SELECT t.* FROM {$tasks_table} t
                             WHERE t.project_id = %d
                               AND t.status NOT IN ('completed','closed')
                               {$vis['where']}
                             ORDER BY t.sort_order ASC, t.created_at DESC",
                            array_merge( [ $project->id ], $vis['params'] )
                        ) );
                    }
                ?>
                    <div class="pm-board-project" data-project-id="<?php echo $project->id; ?>" draggable="true"
                         style="border: 2px solid <?php echo esc_attr( $project->color ); ?>33;
                                background: <?php echo esc_attr( $project->color ); ?>0a;
                                box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                        <div class="pm-board-project-header">
                            <div class="project-drag-handle">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                </svg>
                            </div>
                            <div>
                                <h3><?php echo esc_html( $project->name ); ?></h3>
                                <p class="project-task-count"><?php echo count( $tasks ); ?> tasks</p>
                            </div>
                            <a href="?project_id=<?php echo $project->id; ?>" class="bntm-btn-small bntm-btn-secondary"
                               style="background-color: <?php echo esc_attr( $project->color ); ?>">
                                View Project →
                            </a>
                        </div>

                        <div class="pm-board-tasks" data-project-id="<?php echo $project->id; ?>">
                            <?php if ( empty( $tasks ) ) : ?>
                                <div class="pm-board-empty-tasks">
                                    <p>No tasks visible in this project</p>
                                </div>
                            <?php else : ?>
                                <?php foreach ( $tasks as $task ) :
                                    $assigned_user = $task->assigned_to ? get_userdata( $task->assigned_to ) : null;
                                    $is_overdue    = $task->due_date && strtotime( $task->due_date ) < time() && $task->status !== 'completed';
                                    $due_timestamp = $task->due_date ? strtotime( $task->due_date ) : 0;
                                ?>
                                    <div class="pm-board-task-card"
                                         data-task-id="<?php echo $task->id; ?>"
                                         data-due="<?php echo $due_timestamp; ?>"
                                         data-overdue="<?php echo $is_overdue ? '1' : '0'; ?>"
                                         data-assigned-to="<?php echo $task->assigned_to ? $task->assigned_to : '0'; ?>">
                                        <div class="task-card-header">
                                            <span class="task-card-priority priority-<?php echo $task->priority; ?>"></span>
                                            <h4><?php echo esc_html( $task->title ); ?></h4>
                                        </div>
                                        <?php if ( $task->description ) : ?>
                                            <p class="task-card-desc"><?php echo esc_html( wp_trim_words( $task->description, 15 ) ); ?></p>
                                        <?php endif; ?>
                                        <div class="task-card-meta">
                                            <span class="task-card-status" style="background: <?php echo pm_get_status_color( $task->status ); ?>">
                                                <?php echo ucfirst( $task->status ); ?>
                                            </span>
                                            <?php if ( $assigned_user ) : ?>
                                                <span class="task-card-assignee" title="<?php echo esc_attr( $assigned_user->display_name ); ?>">
                                                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <?php echo esc_html( $assigned_user->display_name ); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ( $task->due_date ) : ?>
                                                <span class="task-card-due <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                    <?php echo date( 'M d', strtotime( $task->due_date ) ); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
    .pm-board-container { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .pm-board-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .pm-board-header h2 { margin: 0; font-size: 20px; font-weight: 600; color: #111827; }
    .pm-board-controls { display: flex; gap: 12px; }
    .pm-empty-board { text-align: center; padding: 60px 20px; }
    .pm-empty-board svg { margin: 0 auto 20px; }
    .pm-empty-board h3 { margin: 0 0 8px 0; font-size: 18px; color: #374151; }
    .pm-empty-board p { margin: 0 0 24px 0; color: #6b7280; }
    .pm-board-projects { display: grid; gap: 24px; }
    .pm-board-project { background: #f9fafb; border-radius: 12px; padding: 20px; cursor: move; transition: all 0.2s; }
    .pm-board-project:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .pm-board-project.dragging { opacity: 0.5; }
    .pm-board-project-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-left: 12px; border-radius: 6px; gap: 12px; }
    .project-drag-handle { color: #9ca3af; cursor: move; padding: 4px; }
    .pm-board-project-header h3 { margin: 0 0 4px 0; font-size: 16px; font-weight: 600; color: #111827; }
    .pm-board-project-header p { margin: 0; font-size: 13px; color: #6b7280; }
    .pm-board-tasks { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .pm-board-empty-tasks { grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #9ca3af; font-size: 14px; }
    .pm-board-task-card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s; }
    .pm-board-task-card:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: translateY(-2px); }
    .pm-board-task-card.hidden { display: none; }
    .pm-board-filter-toggle { display: flex; background: #f3f4f6; border-radius: 8px; padding: 4px; gap: 4px; }
    .pm-board-filter-btn { padding: 8px 16px; border: none; background: transparent; color: #6b7280; font-size: 14px; font-weight: 500; border-radius: 6px; cursor: pointer; transition: all 0.2s; }
    .pm-board-filter-btn:hover { color: #374151; }
    .pm-board-filter-btn.active { background: white; color: #3b82f6; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .task-card-header { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
    .task-card-priority { width: 4px; height: 20px; border-radius: 2px; flex-shrink: 0; }
    .task-card-priority.priority-low { background: #3b82f6; }
    .task-card-priority.priority-medium { background: #f59e0b; }
    .task-card-priority.priority-high { background: #ef4444; }
    .task-card-header h4 { margin: 0; font-size: 14px; font-weight: 600; color: #111827; flex: 1; line-height: 1.4; }
    .task-card-desc { margin: 0 0 12px 12px; font-size: 13px; color: #6b7280; line-height: 1.5; }
    .task-card-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 12px; margin-left: 12px; }
    .task-card-status { padding: 3px 8px; border-radius: 4px; font-weight: 600; color: white; }
    .task-card-assignee, .task-card-due { display: flex; align-items: center; gap: 4px; color: #6b7280; }
    .task-card-due.overdue { color: #ef4444; font-weight: 600; }
    .pm-board-task-card.assignment-hidden { display: none !important; }
    </style>

    <script>
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';

    (function () {
        let draggedProject = null;

        document.getElementById('pm-board-filter').addEventListener('change', function () {
            const filter = this.value;
            const now = Math.floor(Date.now() / 1000);
            const weekFromNow = now + (7 * 24 * 60 * 60);
            const monthFromNow = now + (30 * 24 * 60 * 60);

            document.querySelectorAll('.pm-board-task-card').forEach(card => {
                if (card.classList.contains('assignment-hidden')) return;
                const dueTimestamp = parseInt(card.dataset.due);
                const isOverdue = card.dataset.overdue === '1';
                let show = true;
                if (filter === 'week')    show = dueTimestamp > 0 && dueTimestamp <= weekFromNow;
                else if (filter === 'month')   show = dueTimestamp > 0 && dueTimestamp <= monthFromNow;
                else if (filter === 'overdue') show = isOverdue;
                card.classList.toggle('hidden', !show);
            });

            updateProjectTaskCounts();
            updateEmptyStates();
        });

        document.getElementById('pm-board-search').addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.pm-board-task-card').forEach(card => {
                if (card.classList.contains('hidden') || card.classList.contains('assignment-hidden')) return;
                const title   = card.querySelector('h4').textContent.toLowerCase();
                const descEl  = card.querySelector('.task-card-desc');
                const descTxt = descEl ? descEl.textContent.toLowerCase() : '';
                card.style.display = (title.includes(searchTerm) || descTxt.includes(searchTerm)) ? 'block' : 'none';
            });
            updateEmptyStates();
        });

        function updateProjectTaskCounts() {
            document.querySelectorAll('.pm-board-project').forEach(project => {
                const visible = project.querySelectorAll('.pm-board-task-card:not(.hidden):not(.assignment-hidden)').length;
                const el = project.querySelector('.project-task-count');
                if (el) el.textContent = visible + ' task' + (visible !== 1 ? 's' : '');
            });
        }

        document.querySelectorAll('.pm-board-project').forEach(project => {
            project.addEventListener('dragstart', function () { draggedProject = this; this.classList.add('dragging'); });
            project.addEventListener('dragend',   function () { this.classList.remove('dragging'); draggedProject = null; });
        });

        const projectsContainer = document.querySelector('.sortable-projects');
        if (projectsContainer) {
            projectsContainer.addEventListener('dragover', function (e) {
                e.preventDefault();
                const after = getDragAfterElement(this, e.clientY);
                after ? this.insertBefore(draggedProject, after) : this.appendChild(draggedProject);
            });

            projectsContainer.addEventListener('drop', function () {
                const order = Array.from(this.querySelectorAll('.pm-board-project')).map(p => p.dataset.projectId);
                const fd = new FormData();
                fd.append('action', 'pm_reorder_projects');
                fd.append('project_order', JSON.stringify(order));
                fd.append('nonce', '<?php echo wp_create_nonce( 'pm_reorder_projects_nonce' ); ?>');
                fetch(ajaxurl, { method: 'POST', body: fd });
            });
        }

        function getDragAfterElement(container, y) {
            const els = [...container.querySelectorAll('.pm-board-project:not(.dragging)')];
            return els.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                return (offset < 0 && offset > closest.offset) ? { offset, element: child } : closest;
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        let currentAssignmentFilter = 'all';

        window.pmBoardFilterTasks = function (filter) {
            currentAssignmentFilter = filter;
            document.querySelectorAll('.pm-board-filter-btn').forEach(b => b.classList.remove('active'));
            document.querySelector(`.pm-board-filter-btn[data-filter="${filter}"]`).classList.add('active');

            const currentUserId = '<?php echo get_current_user_id(); ?>';

            document.querySelectorAll('.pm-board-task-card').forEach(card => {
                const assignedTo = card.getAttribute('data-assigned-to');
                const show = filter !== 'my' || assignedTo === currentUserId;
                card.classList.toggle('assignment-hidden', !show);
            });

            updateProjectTaskCounts();
            updateEmptyStates();
        };

        function updateEmptyStates() {
            document.querySelectorAll('.pm-board-project').forEach(project => {
                const tasksContainer = project.querySelector('.pm-board-tasks');
                const visible  = tasksContainer.querySelectorAll('.pm-board-task-card:not(.hidden):not(.assignment-hidden)');
                const allCards = tasksContainer.querySelectorAll('.pm-board-task-card');
                let emptyState = tasksContainer.querySelector('.pm-board-empty-tasks');

                if (visible.length === 0 && allCards.length > 0) {
                    if (!emptyState) {
                        emptyState = document.createElement('div');
                        emptyState.className = 'pm-board-empty-tasks dynamic-empty';
                        tasksContainer.appendChild(emptyState);
                    }
                    emptyState.textContent = currentAssignmentFilter === 'my'
                        ? 'No tasks assigned to you in this project'
                        : 'No tasks match the current filters';
                    emptyState.style.display = 'block';
                } else if (emptyState && emptyState.classList.contains('dynamic-empty')) {
                    emptyState.style.display = 'none';
                }
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
/* ============================================================
   RESOURCE LOAD TAB
   ============================================================ */

function pm_resource_load_tab( $business_id ) {
    global $wpdb;

    $tasks_table     = $wpdb->prefix . 'pm_tasks';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    $team_table      = $wpdb->prefix . 'pm_team_members';
    $projects_table  = $wpdb->prefix . 'pm_projects';

    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can( 'manage_options' );
    $current_role = bntm_get_user_role( $current_user->ID );
    $can_view_all = $is_wp_admin || in_array( $current_role, [ 'owner', 'manager' ] );

    $view          = isset( $_GET['view'] )          ? sanitize_text_field( $_GET['view'] )          : 'weekly';
    $selected_date = isset( $_GET['selected_date'] ) ? sanitize_text_field( $_GET['selected_date'] ) : date( 'Y-m-d' );

    // Date ranges
    switch ( $view ) {
        case 'weekly':
            $week_start = strtotime( 'last sunday', strtotime( $selected_date ) );
            if ( date( 'w', strtotime( $selected_date ) ) == 0 ) $week_start = strtotime( $selected_date );
            $date_from = date( 'Y-m-d', $week_start );
            $date_to   = date( 'Y-m-d', strtotime( $date_from . ' +6 days' ) );
            break;
        case 'monthly':
            $date_from = date( 'Y-m-01', strtotime( $selected_date ) );
            $date_to   = date( 'Y-m-t',  strtotime( $selected_date ) );
            break;
        default:
            $week_start = strtotime( 'last sunday' );
            if ( date( 'w' ) == 0 ) $week_start = strtotime( 'today' );
            $date_from = date( 'Y-m-d', $week_start );
            $date_to   = date( 'Y-m-d', strtotime( $date_from . ' +6 days' ) );
    }

    if ( $view === 'weekly' ) {
        $prev_date      = date( 'Y-m-d', strtotime( $date_from . ' -7 days' ) );
        $next_date      = date( 'Y-m-d', strtotime( $date_from . ' +7 days' ) );
        $period_display = date( 'M d', strtotime( $date_from ) ) . ' - ' . date( 'M d, Y', strtotime( $date_to ) );
    } else {
        $prev_date      = date( 'Y-m-d', strtotime( $date_from . ' -1 month' ) );
        $next_date      = date( 'Y-m-d', strtotime( $date_from . ' +1 month' ) );
        $period_display = date( 'F Y', strtotime( $date_from ) );
    }

    // ── Task visibility filter ────────────────────────────────────────────────
    // For the resource tab the alias used in sub-queries is "t"
    $vis = pm_get_task_visibility( 't' );

    // Build project-id list used to scope the team member list
    if ( $can_view_all ) {
        $user_project_ids  = [];
        $project_filter    = '';          // used in raw SQL below
        $team_members_where = '';
    } else {
        $user_project_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT project_id FROM {$team_table} WHERE user_id = %d AND role = 'project_manager'",
            $current_user->ID
        ) );
        $user_project_ids   = ! empty( $user_project_ids ) ? $user_project_ids : [ 0 ];
        $project_filter     = " AND t.project_id IN (" . implode( ',', array_map( 'intval', $user_project_ids ) ) . ")";
        $team_members_where = " WHERE project_id IN (" . implode( ',', array_map( 'intval', $user_project_ids ) ) . ")";
    }

    // ── Also apply task-visibility to the team workload query ─────────────────
    // When the user is staff (not PM, not admin/owner/manager) they only see
    // their own tasks, so scope workload to just themselves.
    $vis_extra = $vis['where'];     // already starts with " AND …"

    $team_workload = $wpdb->get_results( $wpdb->prepare(
        "SELECT u.ID, u.display_name, u.user_email,
         COUNT(DISTINCT CASE WHEN t.due_date BETWEEN %s AND %s THEN t.id END) as active_tasks,
         COUNT(DISTINCT CASE WHEN t.status NOT IN ('completed','closed') AND t.due_date BETWEEN %s AND %s THEN t.id END) as pending_tasks,
         COUNT(DISTINCT CASE WHEN t.priority = 'high' AND t.due_date BETWEEN %s AND %s THEN t.id END) as high_priority_tasks,
         COALESCE(SUM(CASE WHEN t.status NOT IN ('completed','closed') AND t.due_date BETWEEN %s AND %s THEN t.estimated_hours ELSE 0 END), 0) as estimated_hours,
         COALESCE(SUM(tl.hours), 0) as logged_hours,
         COUNT(DISTINCT CASE WHEN t.due_date BETWEEN %s AND %s THEN t.project_id END) as project_count
         FROM {$wpdb->users} u
         LEFT JOIN {$tasks_table} t ON u.ID = t.assigned_to {$project_filter} {$vis_extra}
         LEFT JOIN {$time_logs_table} tl ON t.id = tl.task_id AND tl.log_date BETWEEN %s AND %s
         WHERE u.ID IN (
             SELECT DISTINCT user_id FROM {$team_table}{$team_members_where}
         )
         GROUP BY u.ID
         ORDER BY pending_tasks DESC, estimated_hours DESC",
        array_merge(
            $vis['params'],   // params for vis_extra inside JOIN
            [
                $date_from, $date_to,
                $date_from, $date_to,
                $date_from, $date_to,
                $date_from, $date_to,
                $date_from, $date_to,
                $date_from, $date_to,
            ]
        )
    ) );

    // ── Project distribution ──────────────────────────────────────────────────
    $project_dist_where = $can_view_all ? '' : "WHERE p.id IN (" . implode( ',', array_map( 'intval', $user_project_ids ) ) . ")";

    $project_distribution = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.id, p.name, p.color,
         COUNT(DISTINCT t.assigned_to) as team_members,
         COUNT(DISTINCT t.id) as total_tasks,
         COUNT(DISTINCT CASE WHEN t.status NOT IN ('completed','closed') AND t.due_date BETWEEN %s AND %s THEN t.id END) as active_tasks,
         COALESCE(SUM(CASE WHEN t.status NOT IN ('completed','closed') AND t.due_date BETWEEN %s AND %s THEN t.estimated_hours ELSE 0 END), 0) as total_estimated_hours
         FROM {$projects_table} p
         LEFT JOIN {$tasks_table} t ON p.id = t.project_id {$vis_extra}
         {$project_dist_where}
         GROUP BY p.id
         HAVING active_tasks > 0
         ORDER BY active_tasks DESC",
        array_merge(
            $vis['params'],   // for vis_extra
            [ $date_from, $date_to, $date_from, $date_to ]
        )
    ) );

    // Capacity thresholds
    $overloaded_threshold = 40;
    $optimal_threshold    = 30;
    $overloaded = $optimal = $underutilized = 0;
    foreach ( $team_workload as $m ) {
        if ( $m->estimated_hours > $overloaded_threshold )       $overloaded++;
        elseif ( $m->estimated_hours >= $optimal_threshold )     $optimal++;
        else                                                       $underutilized++;
    }

    // User colour palette
    $user_colors = [
        '#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6',
        '#ec4899','#14b8a6','#f97316','#6366f1','#84cc16',
        '#06b6d4','#f43f5e','#22c55e','#eab308','#a855f7',
        '#64748b','#d946ef','#0ea5e9','#fb923c','#4ade80',
    ];

    $all_users_q = "SELECT DISTINCT u.ID, u.display_name
                    FROM {$wpdb->users} u
                    WHERE u.ID IN (
                        SELECT DISTINCT user_id FROM {$team_table}{$team_members_where}
                    )
                    ORDER BY u.display_name";
    $all_users = $wpdb->get_results( $all_users_q );

    $user_color_map = [];
    foreach ( $all_users as $idx => $user ) {
        $user_color_map[ $user->ID ] = [
            'name'  => $user->display_name,
            'color' => $user_colors[ $idx % count( $user_colors ) ],
        ];
    }

    // ── Daily distribution ────────────────────────────────────────────────────
    $user_daily_distribution = [];
    $num_days       = (int) ( ( strtotime( $date_to ) - strtotime( $date_from ) ) / 86400 ) + 1;
    $max_hours      = 8;
    $max_height_px  = 150;

    for ( $i = 0; $i < $num_days; $i++ ) {
        $current_day = date( 'Y-m-d', strtotime( $date_from . " +{$i} days" ) );
        $day_name    = $view === 'weekly' ? date( 'D', strtotime( $current_day ) ) : date( 'j', strtotime( $current_day ) );
        $day_date    = date( 'M d', strtotime( $current_day ) );

        $user_daily_distribution[ $current_day ] = [
            'day'      => $day_name,
            'date'     => $current_day,
            'day_date' => $day_date,
            'users'    => [],
        ];

        foreach ( $all_users as $user ) {
            // Estimated hours — apply visibility filter
            if ( $vis['where'] === '' ) {
                $estimated_hours = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(t.estimated_hours),0) FROM {$tasks_table} t
                     WHERE t.due_date = %s AND t.assigned_to = %d {$project_filter}",
                    $current_day, $user->ID
                ) );
                $actual_hours = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(tl.hours),0) FROM {$time_logs_table} tl
                     INNER JOIN {$tasks_table} t ON tl.task_id = t.id
                     WHERE tl.log_date = %s AND t.assigned_to = %d {$project_filter}",
                    $current_day, $user->ID
                ) );
            } else {
                $estimated_hours = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(t.estimated_hours),0) FROM {$tasks_table} t
                     WHERE t.due_date = %s AND t.assigned_to = %d {$project_filter} {$vis['where']}",
                    array_merge( [ $current_day, $user->ID ], $vis['params'] )
                ) );
                $actual_hours = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COALESCE(SUM(tl.hours),0) FROM {$time_logs_table} tl
                     INNER JOIN {$tasks_table} t ON tl.task_id = t.id
                     WHERE tl.log_date = %s AND t.assigned_to = %d {$project_filter} {$vis['where']}",
                    array_merge( [ $current_day, $user->ID ], $vis['params'] )
                ) );
            }

            if ( $estimated_hours > 0 || $actual_hours > 0 ) {
                $user_daily_distribution[ $current_day ]['users'][] = [
                    'user_id'          => $user->ID,
                    'user_name'        => $user->display_name,
                    'estimated_hours'  => floatval( $estimated_hours ),
                    'actual_hours'     => floatval( $actual_hours ),
                    'estimated_height' => ( $estimated_hours / $max_hours ) * $max_height_px,
                    'actual_height'    => ( $actual_hours   / $max_hours ) * $max_height_px,
                    'color'            => $user_color_map[ $user->ID ]['color'],
                ];
            }
        }
    }

document.getElementById('pm-project-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const projectId = document.getElementById('pm-project-id').value;
    formData.append('action', projectId ? 'pm_update_project' : 'pm_create_project');
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = projectId ? 'Updating...' : 'Creating...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Operation failed');
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = projectId ? 'Update Project' : 'Create Project';
        }
    });
});
</script>
<?php return ob_get_clean();
}
/**
 * Project Board Tab
 */
function pm_board_tab($business_id)
{
    global $wpdb;
    $projects_table = $wpdb->prefix . "pm_projects";
    $tasks_table = $wpdb->prefix . "pm_tasks";
    
    $projects = $wpdb->get_results("SELECT * FROM {$projects_table}
        WHERE  status NOT IN ('completed', 'cancelled','on_hold')
        ORDER BY sort_order ASC, created_at DESC");
    
    ob_start();
    // ── HTML (identical to original; only data changes) ───────────────────────
    ?>
    <div class="pm-resource-load-container">
        <div class="pm-resource-header">
            <h2>Resource Load Management</h2>
            <div class="pm-resource-controls">
                <select id="pm-view-filter" class="bntm-input" onchange="pmChangeView()">
                    <option value="weekly"  <?php selected( $view, 'weekly' ); ?>>Weekly View</option>
                    <option value="monthly" <?php selected( $view, 'monthly' ); ?>>Monthly View</option>
                </select>
            </div>
        </div>

        <div class="pm-date-navigation">
            <button class="pm-nav-btn" onclick="pmNavigateDate('prev')">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </button>
            <div class="pm-period-display"><strong><?php echo $period_display; ?></strong></div>
            <button class="pm-nav-btn" onclick="pmNavigateDate('next')">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <!-- Capacity Overview -->
        <div class="pm-capacity-overview">
            <h3>Team Capacity Overview</h3>
            <div class="pm-capacity-stats">
                <div class="pm-capacity-card overloaded">
                    <div class="capacity-icon">⚠️</div>
                    <div>
                        <h4>Overloaded</h4>
                        <p class="capacity-value"><?php echo $overloaded; ?></p>
                        <p class="capacity-label">Team members (&gt;<?php echo $overloaded_threshold; ?>h)</p>
                    </div>
                </div>
                <div class="pm-capacity-card optimal">
                    <div class="capacity-icon">✅</div>
                    <div>
                        <h4>Optimal Load</h4>
                        <p class="capacity-value"><?php echo $optimal; ?></p>
                        <p class="capacity-label">Team members (<?php echo $optimal_threshold; ?>–<?php echo $overloaded_threshold; ?>h)</p>
                    </div>
                </div>
                <div class="pm-capacity-card underutilized">
                    <div class="capacity-icon">📊</div>
                    <div>
                        <h4>Available</h4>
                        <p class="capacity-value"><?php echo $underutilized; ?></p>
                        <p class="capacity-label">Team members (&lt;<?php echo $optimal_threshold; ?>h)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Distribution Chart -->
        <?php if ( ! empty( $user_daily_distribution ) ) : ?>
        <div class="pm-resource-section">
            <h3>Task Distribution — <?php echo $view === 'weekly' ? 'This Week' : 'This Month'; ?></h3>
            <div class="pm-user-legend">
                <div style="width:100%;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #e5e7eb;">
                    <span style="font-size:12px;font-weight:600;color:#6b7280;">Legend:</span>
                    <span style="margin-left:16px;font-size:11px;">
                        <span style="display:inline-block;width:20px;height:12px;background:#3b82f6;border:2px solid rgba(0,0,0,0.1);border-radius:2px;vertical-align:middle;"></span> Estimated
                    </span>
                    <span style="margin-left:12px;font-size:11px;">
                        <span style="display:inline-block;width:20px;height:12px;background:#3b82f6;opacity:0.6;border:2px dashed rgba(255,255,255,0.5);border-radius:2px;vertical-align:middle;"></span> Actual
                    </span>
                </div>
                <?php foreach ( $user_color_map as $uid => $ud ) : ?>
                    <div class="legend-item">
                        <span class="legend-color" style="background:<?php echo $ud['color']; ?>;"></span>
                        <span class="legend-name"><?php echo esc_html( $ud['name'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="pm-chart-container">
                <div class="pm-y-axis">
                    <div class="y-axis-label" style="bottom:100%;">12h</div>
                    <div class="y-axis-label" style="bottom:83.33%;">10h</div>
                    <div class="y-axis-label" style="bottom:66.66%;">8h</div>
                    <div class="y-axis-label" style="bottom:50%;">6h</div>
                    <div class="y-axis-label" style="bottom:33.33%;">4h</div>
                    <div class="y-axis-label" style="bottom:16.66%;">2h</div>
                    <div class="y-axis-label" style="bottom:0;">0h</div>
                </div>
                <div class="pm-daily-distribution <?php echo $view === 'monthly' ? 'monthly-view' : ''; ?>">
                    <div class="pm-grid-lines">
                        <?php foreach ( [ '100%','83.33%','66.66%','50%','33.33%','16.66%','0' ] as $pct ) : ?>
                            <div class="grid-line" style="bottom:<?php echo $pct; ?>;"></div>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ( $user_daily_distribution as $day_data ) :
                        $is_today  = $day_data['date'] === date( 'Y-m-d' );
                        $has_tasks = ! empty( $day_data['users'] );
                    ?>
                        <div class="daily-bar-container">
                            <div class="daily-content">
                                <?php if ( $has_tasks ) :
                                    foreach ( $day_data['users'] as $ut ) : ?>
                                        <div class="user-task-group">
                                            <div class="user-task-bar estimated"
                                                 style="background:<?php echo $ut['color']; ?>;height:<?php echo max($ut['estimated_height'],5); ?>px;"
                                                 data-tooltip="<?php echo esc_attr($ut['user_name'].' - Estimated: '.number_format($ut['estimated_hours'],1).'h'); ?>"></div>
                                            <div class="user-task-bar actual"
                                                 style="background:<?php echo $ut['color']; ?>;opacity:0.6;height:<?php echo max($ut['actual_height'],5); ?>px;"
                                                 data-tooltip="<?php echo esc_attr($ut['user_name'].' - Actual: '.number_format($ut['actual_hours'],1).'h'); ?>"></div>
                                        </div>
                                    <?php endforeach;
                                else : ?>
                                    <div class="no-tasks-indicator"></div>
                                <?php endif; ?>
                            </div>
                            <span class="daily-label <?php echo $is_today ? 'today' : ''; ?>">
                                <?php echo $day_data['day']; ?><br>
                                <small style="font-size:10px;color:#9ca3af;"><?php echo $day_data['day_date']; ?></small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Team Workload Table -->
        <div class="pm-resource-section">
            <h3>Team Member Workload</h3>
            <div class="pm-workload-table-container bntm-table-wrapper">
                <table class="bntm-table pm-workload-table">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th>Active Tasks</th>
                            <th>High Priority</th>
                            <th>Projects</th>
                            <th>Estimated Hours</th>
                            <th>Logged Hours</th>
                            <th>Capacity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $team_workload ) ) :
                            foreach ( $team_workload as $member ) :
                                $cap_pct   = $overloaded_threshold > 0 ? min( ( $member->estimated_hours / $overloaded_threshold ) * 100, 100 ) : 0;
                                $cap_class = $member->estimated_hours > $overloaded_threshold ? 'overloaded' : ( $member->estimated_hours >= $optimal_threshold ? 'optimal' : 'available' );
                                $ucolor    = $user_color_map[ $member->ID ]['color'] ?? '#6b7280';
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="resource-avatar" style="background:<?php echo $ucolor; ?>;">
                                            <?php echo esc_html( strtoupper( substr( $member->display_name, 0, 2 ) ) ); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo esc_html( $member->display_name ); ?></strong>
                                            <div style="font-size:12px;color:#6b7280;"><?php echo esc_html( $member->user_email ); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="resource-badge"><?php echo $member->pending_tasks; ?></span></td>
                                <td>
                                    <?php if ( $member->high_priority_tasks > 0 ) : ?>
                                        <span class="resource-badge high-priority"><?php echo $member->high_priority_tasks; ?></span>
                                    <?php else : ?>
                                        <span style="color:#9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $member->project_count; ?></td>
                                <td><strong><?php echo number_format( $member->estimated_hours, 1 ); ?>h</strong></td>
                                <td><span style="color:#059669;"><?php echo number_format( $member->logged_hours, 1 ); ?>h</span></td>
                                <td>
                                    <div class="capacity-indicator">
                                        <div class="capacity-bar <?php echo $cap_class; ?>">
                                            <div class="capacity-fill" style="width:<?php echo $cap_pct; ?>%;"></div>
                                        </div>
                                        <span class="capacity-text <?php echo $cap_class; ?>"><?php echo round( $cap_pct ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;
                        else : ?>
                            <tr><td colspan="7" style="text-align:center;padding:40px;color:#9ca3af;">No team member data</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Project Resource Distribution -->
        <?php if ( ! empty( $project_distribution ) ) : ?>
        <div class="pm-resource-section">
            <h3>Project Resource Distribution</h3>
            <div class="pm-project-distribution-grid">
                <?php foreach ( $project_distribution as $project ) : ?>
                    <div class="pm-project-resource-card">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <div style="width:4px;height:32px;background:<?php echo esc_attr($project->color); ?>;border-radius:2px;"></div>
                            <h4 style="margin:0;font-size:15px;"><?php echo esc_html( $project->name ); ?></h4>
                        </div>
                        <div class="project-resource-stats">
                            <div class="project-resource-stat">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                                <span><?php echo $project->team_members; ?> members</span>
                            </div>
                            <div class="project-resource-stat">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>
                                <span><?php echo $project->active_tasks; ?> active tasks</span>
                            </div>
                            <div class="project-resource-stat">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                                <span><?php echo number_format( $project->total_estimated_hours, 1 ); ?>h estimated</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Additional Analytics -->
        <div class="pm-additional-analytics">
            <h3>Additional Insights</h3>
            <div class="pm-insights-grid">
                <div class="pm-insight-card">
                    <h4>Average Tasks per Member</h4>
                    <p class="insight-value">
                        <?php
                        $avg_tasks = count( $team_workload ) > 0
                            ? round( array_sum( array_column( $team_workload, 'pending_tasks' ) ) / count( $team_workload ), 1 )
                            : 0;
                        echo $avg_tasks;
                        ?>
                    </p>
                </div>
                <div class="pm-insight-card">
                    <h4>Total Estimated Hours</h4>
                    <p class="insight-value"><?php echo number_format( array_sum( array_column( $team_workload, 'estimated_hours' ) ), 1 ); ?>h</p>
                </div>
                <div class="pm-insight-card">
                    <h4>Total Logged Hours</h4>
                    <p class="insight-value"><?php echo number_format( array_sum( array_column( $team_workload, 'logged_hours' ) ), 1 ); ?>h</p>
                </div>
                <div class="pm-insight-card">
                    <h4>Tracking Accuracy</h4>
                    <p class="insight-value">
                        <?php
                        $te = array_sum( array_column( $team_workload, 'estimated_hours' ) );
                        $tl = array_sum( array_column( $team_workload, 'logged_hours' ) );
                        echo $te > 0 ? round( ( $tl / $te ) * 100 ) : 0;
                        ?>%
                    </p>
                </div>
            </div>
        </div>
    </div>

    <style>
    .pm-resource-load-container{background:white;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
    .pm-resource-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:16px;}
    .pm-resource-header h2{margin:0;font-size:20px;font-weight:600;color:#111827;}
    .pm-resource-controls{display:flex;gap:12px;align-items:center;}
    .pm-date-navigation{display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:24px;padding:16px;background:#f9fafb;border-radius:8px;}
    .pm-nav-btn{background:white;border:1px solid #e5e7eb;border-radius:6px;padding:8px 12px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.2s;color:#6b7280;}
    .pm-nav-btn:hover{background:#f3f4f6;border-color:#d1d5db;color:#111827;}
    .pm-period-display{min-width:250px;text-align:center;font-size:16px;color:#111827;}
    .pm-capacity-overview{margin-bottom:24px;}
    .pm-capacity-overview h3{margin:0 0 16px 0;font-size:18px;font-weight:600;color:#111827;}
    .pm-capacity-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;}
    .pm-capacity-card{background:#f9fafb;border-radius:12px;padding:20px;display:flex;gap:16px;align-items:center;}
    .pm-capacity-card.overloaded{background:#fef2f2;}
    .pm-capacity-card.optimal{background:#f0fdf4;}
    .pm-capacity-card.underutilized{background:#eff6ff;}
    .capacity-icon{font-size:32px;}
    .pm-capacity-card h4{margin:0 0 6px 0;font-size:14px;color:#6b7280;font-weight:500;}
    .capacity-value{margin:0 0 4px 0;font-size:28px;font-weight:700;color:#111827;}
    .capacity-label{margin:0;font-size:12px;color:#9ca3af;}
    .pm-user-legend{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:16px;padding:12px;background:white;border-radius:8px;border:1px solid #e5e7eb;}
    .legend-item{display:flex;align-items:center;gap:6px;}
    .legend-color{width:16px;height:16px;border-radius:3px;}
    .legend-name{font-size:13px;color:#374151;font-weight:500;}
    .pm-chart-container{display:flex;gap:12px;position:relative;}
    .pm-y-axis{width:40px;position:relative;height:250px;flex-shrink:0;}
    .y-axis-label{position:absolute;right:8px;font-size:11px;color:#6b7280;font-weight:500;transform:translateY(50%);}
    .pm-daily-distribution{flex:1;display:flex;gap:16px;align-items:flex-end;height:250px;padding:20px;background:#f9fafb;border-radius:12px;overflow-x:auto;position:relative;}
    .pm-daily-distribution.monthly-view{gap:8px;height:200px;}
    .pm-grid-lines{position:absolute;top:20px;left:20px;right:20px;bottom:50px;pointer-events:none;}
    .grid-line{position:absolute;left:0;right:0;height:1px;background:#e5e7eb;}
    .daily-bar-container{flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;min-width:60px;z-index:1;}
    .pm-daily-distribution.monthly-view .daily-bar-container{min-width:30px;}
    .daily-content{display:flex;gap:6px;width:100%;align-items:flex-end;justify-content:center;height:180px;padding-bottom:10px;}
    .pm-daily-distribution.monthly-view .daily-content{height:130px;gap:4px;}
    .user-task-group{display:flex;gap:2px;align-items:flex-end;}
    .user-task-bar{border-radius:3px 3px 0 0;min-width:2px;max-width:5px;transition:all 0.3s;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.1);position:relative;}
    .user-task-bar.estimated{border:2px solid rgba(0,0,0,0.1);}
    .user-task-bar.actual{border:2px dashed rgba(255,255,255,0.5);}
    .user-task-bar:hover{transform:translateY(-3px);box-shadow:0 2px 4px rgba(0,0,0,0.2);z-index:10;}
    .user-task-bar:hover::after{content:attr(data-tooltip);position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#111827;color:white;padding:6px 10px;border-radius:6px;font-size:11px;white-space:nowrap;z-index:1000;margin-bottom:5px;}
    .user-task-bar:hover::before{content:'';position:absolute;bottom:100%;left:50%;transform:translateX(-50%);border:5px solid transparent;border-top-color:#111827;z-index:1000;}
    .no-tasks-indicator{width:100%;height:6px;background:#e5e7eb;border-radius:3px;opacity:0.3;}
    .daily-label{margin-top:8px;font-size:12px;color:#6b7280;font-weight:500;text-align:center;line-height:1.3;}
    .daily-label.today{color:#3b82f6;font-weight:700;}
    .pm-resource-section{margin-top:24px;background:white;border-radius:12px;}
    .pm-resource-section h3{margin:0 0 16px 0;font-size:18px;font-weight:600;color:#111827;}
    .pm-workload-table-container{overflow-x:auto;}
    .pm-workload-table{width:100%;}
    .resource-avatar{width:36px;height:36px;border-radius:50%;color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0;}
    .resource-badge{display:inline-block;padding:4px 10px;background:#e5e7eb;color:#374151;border-radius:6px;font-size:13px;font-weight:600;}
    .resource-badge.high-priority{background:#fee2e2;color:#ef4444;}
    .capacity-indicator{display:flex;align-items:center;gap:8px;}
    .capacity-bar{flex:1;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;min-width:80px;}
    .capacity-fill{height:100%;border-radius:4px;transition:width 0.3s;}
    .capacity-bar.overloaded .capacity-fill{background:#ef4444;}
    .capacity-bar.optimal .capacity-fill{background:#10b981;}
    .capacity-bar.available .capacity-fill{background:#3b82f6;}
    .capacity-text{font-size:13px;font-weight:600;min-width:40px;}
    .capacity-text.overloaded{color:#ef4444;}
    .capacity-text.optimal{color:#10b981;}
    .capacity-text.available{color:#3b82f6;}
    .pm-project-distribution-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}
    .pm-project-resource-card{background:#f9fafb;border-radius:12px;padding:16px;}
    .project-resource-stats{display:flex;flex-direction:column;gap:8px;}
    .project-resource-stat{display:flex;align-items:center;gap:8px;font-size:13px;color:#6b7280;}
    .pm-additional-analytics{margin-top:24px;}
    .pm-additional-analytics h3{margin:0 0 16px 0;font-size:18px;font-weight:600;color:#111827;}
    .pm-insights-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}
    .pm-insight-card{background:#f9fafb;border-radius:12px;padding:20px;text-align:center;}
    .pm-insight-card h4{margin:0 0 12px 0;font-size:14px;color:#6b7280;font-weight:500;}
    .insight-value{margin:0;font-size:32px;font-weight:700;color:#111827;}
    </style>

    <script>
    function pmChangeView() {
        const view = document.getElementById('pm-view-filter').value;
        window.location.href = '?tab=resources&view=' + view + '&selected_date=<?php echo $selected_date; ?>';
    }
    function pmNavigateDate(direction) {
        const targetDate = direction === 'prev' ? '<?php echo $prev_date; ?>' : '<?php echo $next_date; ?>';
        window.location.href = '?tab=resources&view=<?php echo $view; ?>&selected_date=' + targetDate;
    }
    </script>
    <?php
    return ob_get_clean();
}


/* ============================================================
   REPORTS TAB
   ============================================================ */

function pm_reports_tab( $business_id ) {
    global $wpdb;

    $projects_table  = $wpdb->prefix . 'pm_projects';
    $tasks_table     = $wpdb->prefix . 'pm_tasks';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    $team_table      = $wpdb->prefix . 'pm_team_members';

    $current_user = wp_get_current_user();
    $is_wp_admin  = current_user_can( 'manage_options' );
    $current_role = bntm_get_user_role( $current_user->ID );
    $can_view_all = $is_wp_admin || in_array( $current_role, [ 'owner', 'manager' ] );

    $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-01' );
    $date_to   = isset( $_GET['date_to'] )   ? sanitize_text_field( $_GET['date_to'] )   : date( 'Y-m-d' );

    // Task visibility
    $vis = pm_get_task_visibility( 't' );

    // Build project-scope lists for queries that need them
    if ( $can_view_all ) {
        $proj_where_p = '';           // projects table, alias p
        $proj_where_and_p = '';
        $tasks_scope  = '';           // tasks table, no alias needed
    } else {
        $managed_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT project_id FROM {$team_table} WHERE user_id = %d AND role = 'project_manager'",
            $current_user->ID
        ) );
        $managed_ids   = ! empty( $managed_ids ) ? $managed_ids : [ 0 ];
        $ids_str       = implode( ',', array_map( 'intval', $managed_ids ) );
        $proj_where_p  = " WHERE p.id IN ({$ids_str})";
        $proj_where_and_p = " AND p.id IN ({$ids_str})";
        $tasks_scope   = " AND t.project_id IN ({$ids_str})";
    }

    // ── Overall statistics ────────────────────────────────────────────────────
    $total_projects     = $wpdb->get_var( "SELECT COUNT(*) FROM {$projects_table} p {$proj_where_p}" );
    $completed_projects = $wpdb->get_var( "SELECT COUNT(*) FROM {$projects_table} p {$proj_where_p}" . ( $proj_where_p ? ' AND' : ' WHERE' ) . " p.status IN ('completed','closed')" );

    if ( $vis['where'] === '' ) {
        $total_tasks     = $wpdb->get_var( "SELECT COUNT(*) FROM {$tasks_table} t {$tasks_scope}" === ' AND t.project_id IN (0)' ? "SELECT 0" : "SELECT COUNT(*) FROM {$tasks_table} t WHERE 1=1 {$tasks_scope}" );
        $completed_tasks = $wpdb->get_var( "SELECT COUNT(*) FROM {$tasks_table} t WHERE t.status IN ('completed','closed') {$tasks_scope}" );
        $total_hours     = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(tl.hours) FROM {$time_logs_table} tl
             INNER JOIN {$tasks_table} t ON tl.task_id = t.id
             WHERE tl.log_date BETWEEN %s AND %s {$tasks_scope}",
            $date_from, $date_to
        ) );
    } else {
        $total_tasks     = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} t WHERE 1=1 {$vis['where']} {$tasks_scope}",
            $vis['params']
        ) );
        $completed_tasks = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$tasks_table} t WHERE t.status IN ('completed','closed') {$vis['where']} {$tasks_scope}",
            $vis['params']
        ) );
        $total_hours     = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(tl.hours) FROM {$time_logs_table} tl
             INNER JOIN {$tasks_table} t ON tl.task_id = t.id
             WHERE tl.log_date BETWEEN %s AND %s {$vis['where']} {$tasks_scope}",
            array_merge( [ $date_from, $date_to ], $vis['params'] )
        ) );
    }
    $total_hours = $total_hours ? floatval( $total_hours ) : 0;

    // ── Team performance ──────────────────────────────────────────────────────
    if ( $vis['where'] === '' ) {
        $team_performance = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.display_name,
             COUNT(DISTINCT t.id) as total_tasks,
             COUNT(DISTINCT CASE WHEN t.status IN ('completed','closed') THEN t.id END) as completed_tasks,
             COALESCE(SUM(tl.hours),0) as total_hours
             FROM {$wpdb->users} u
             INNER JOIN {$tasks_table} t ON u.ID = t.assigned_to
             LEFT JOIN {$time_logs_table} tl ON t.id = tl.task_id AND tl.log_date BETWEEN %s AND %s
             WHERE 1=1 {$tasks_scope}
             GROUP BY u.ID
             ORDER BY completed_tasks DESC",
            $date_from, $date_to
        ) );
    } else {
        $team_performance = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, u.display_name,
             COUNT(DISTINCT t.id) as total_tasks,
             COUNT(DISTINCT CASE WHEN t.status IN ('completed','closed') THEN t.id END) as completed_tasks,
             COALESCE(SUM(tl.hours),0) as total_hours
             FROM {$wpdb->users} u
             INNER JOIN {$tasks_table} t ON u.ID = t.assigned_to
             LEFT JOIN {$time_logs_table} tl ON t.id = tl.task_id AND tl.log_date BETWEEN %s AND %s
             WHERE 1=1 {$vis['where']} {$tasks_scope}
             GROUP BY u.ID
             ORDER BY completed_tasks DESC",
            array_merge( [ $date_from, $date_to ], $vis['params'] )
        ) );
    }

    // ── Project stats ─────────────────────────────────────────────────────────
    $project_stats = $wpdb->get_results(
        "SELECT p.id, p.name, p.status, p.color,
         COUNT(t.id) as total_tasks,
         COUNT(CASE WHEN t.status IN ('completed','closed') THEN 1 END) as completed_tasks,
         COALESCE(SUM(tl.hours),0) as total_hours
         FROM {$projects_table} p
         LEFT JOIN {$tasks_table} t ON p.id = t.project_id
         LEFT JOIN {$time_logs_table} tl ON t.id = tl.task_id
         {$proj_where_p}
         GROUP BY p.id
         ORDER BY p.created_at DESC"
    );

    // ── Tasks by status / priority ────────────────────────────────────────────
    if ( $vis['where'] === '' ) {
        $tasks_by_status = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$tasks_table} t WHERE 1=1 {$tasks_scope} GROUP BY status"
        );
        $tasks_by_priority = $wpdb->get_results(
            "SELECT priority, COUNT(*) as count FROM {$tasks_table} t
             WHERE t.status NOT IN ('completed','closed') {$tasks_scope}
             GROUP BY priority"
        );
    } else {
        $tasks_by_status = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.status, COUNT(*) as count FROM {$tasks_table} t
             WHERE 1=1 {$vis['where']} {$tasks_scope}
             GROUP BY t.status",
            $vis['params']
        ) );
        $tasks_by_priority = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.priority, COUNT(*) as count FROM {$tasks_table} t
             WHERE t.status NOT IN ('completed','closed') {$vis['where']} {$tasks_scope}
             GROUP BY t.priority",
            $vis['params']
        ) );
    }

    // ── Personal performance (always scoped to current user, no filter needed) ─
    $personal_tasks     = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tasks_table} WHERE assigned_to = %d", $current_user->ID ) );
    $personal_completed = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tasks_table} WHERE assigned_to = %d AND status IN ('completed','closed')", $current_user->ID ) );
    $personal_hours     = $wpdb->get_var( $wpdb->prepare(
        "SELECT SUM(hours) FROM {$time_logs_table} WHERE user_id = %d AND log_date BETWEEN %s AND %s",
        $current_user->ID, $date_from, $date_to
    ) );
    $personal_hours = $personal_hours ? floatval( $personal_hours ) : 0;

    $personal_projects = $wpdb->get_results( $wpdb->prepare(
        "SELECT DISTINCT p.id, p.name, p.color,
         COUNT(t.id) as total_tasks,
         COUNT(CASE WHEN t.status IN ('completed','closed') THEN 1 END) as completed_tasks,
         COALESCE(SUM(tl.hours),0) as logged_hours
         FROM {$tasks_table} t
         INNER JOIN {$projects_table} p ON t.project_id = p.id
         LEFT JOIN {$time_logs_table} tl ON t.id = tl.task_id AND tl.user_id = %d
         WHERE t.assigned_to = %d
         GROUP BY p.id
         ORDER BY total_tasks DESC",
        $current_user->ID, $current_user->ID
    ) );

    ob_start();
    ?>
    <div class="pm-reports-container">
        <div class="pm-reports-header">
            <h2><?php echo $can_view_all ? 'Project Reports & Analytics' : 'My Reports & Analytics'; ?></h2>
            <div class="pm-reports-filters">
                <input type="date" id="pm-date-from" value="<?php echo esc_attr( $date_from ); ?>" class="bntm-input">
                <span>to</span>
                <input type="date" id="pm-date-to" value="<?php echo esc_attr( $date_to ); ?>" class="bntm-input">
                <button class="bntm-btn-primary" onclick="pmApplyDateFilter()">Apply</button>
            </div>
        </div>

        <!-- Personal Performance -->
        <div class="pm-personal-performance-section">
            <h3 style="margin:0 0 16px 0;font-size:18px;font-weight:600;color:#111827;">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" style="vertical-align:middle;margin-right:8px;">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
                My Performance
            </h3>
            <div class="pm-personal-stats">
                <div class="pm-personal-stat-card">
                    <div class="personal-stat-icon" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                        <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div><h4>My Tasks</h4><p class="personal-stat-value"><?php echo $personal_tasks; ?></p><p class="personal-stat-label">Total assigned</p></div>
                </div>
                <div class="pm-personal-stat-card">
                    <div class="personal-stat-icon" style="background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);">
                        <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h4>Completed</h4>
                        <p class="personal-stat-value"><?php echo $personal_completed; ?></p>
                        <p class="personal-stat-label"><?php echo $personal_tasks > 0 ? round( ( $personal_completed / $personal_tasks ) * 100 ) : 0; ?>% rate</p>
                    </div>
                </div>
                <div class="pm-personal-stat-card">
                    <div class="personal-stat-icon" style="background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);">
                        <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div><h4>Hours Logged</h4><p class="personal-stat-value"><?php echo number_format( $personal_hours, 1 ); ?>h</p><p class="personal-stat-label">In selected period</p></div>
                </div>
                <div class="pm-personal-stat-card">
                    <div class="personal-stat-icon" style="background:linear-gradient(135deg,#fa709a 0%,#fee140 100%);">
                        <svg width="20" height="20" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    </div>
                    <div><h4>Active Projects</h4><p class="personal-stat-value"><?php echo count( $personal_projects ); ?></p><p class="personal-stat-label">Contributing to</p></div>
                </div>
            </div>

            <?php if ( ! empty( $personal_projects ) ) : ?>
            <div class="pm-personal-projects" style="margin-top:20px;">
                <h4 style="margin:0 0 12px 0;font-size:15px;font-weight:600;color:#111827;">My Project Contributions</h4>
                <div class="pm-personal-projects-grid">
                    <?php foreach ( $personal_projects as $proj ) :
                        $completion = $proj->total_tasks > 0 ? round( ( $proj->completed_tasks / $proj->total_tasks ) * 100 ) : 0;
                    ?>
                        <div class="pm-personal-project-card">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                                <div style="width:3px;height:24px;background:<?php echo esc_attr( $proj->color ); ?>;border-radius:2px;"></div>
                                <strong style="font-size:14px;"><?php echo esc_html( $proj->name ); ?></strong>
                            </div>
                            <div class="personal-project-stats">
                                <span><?php echo $proj->completed_tasks; ?>/<?php echo $proj->total_tasks; ?> tasks</span>
                                <span><?php echo number_format( $proj->logged_hours, 1 ); ?>h logged</span>
                            </div>
                            <div class="pm-mini-progress" style="margin-top:8px;">
                                <div class="pm-mini-progress-fill" style="width:<?php echo $completion; ?>%;background:<?php echo esc_attr( $proj->color ); ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <hr style="margin:32px 0;border:none;border-top:2px solid #e5e7eb;">

        <!-- Team / Overall Statistics -->
        <?php if ( $can_view_all || ! empty( $vis['where'] ) ) : // show for PMs & admins ?>
        <h3 style="margin:0 0 20px 0;font-size:18px;font-weight:600;color:#111827;">
            <?php echo $can_view_all ? 'Overall Statistics' : 'My Projects Overview'; ?>
        </h3>

        <div class="pm-reports-stats">
            <div class="pm-report-stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                </div>
                <div>
                    <h3>Project Completion</h3>
                    <p class="stat-value"><?php echo $total_projects > 0 ? round( ( $completed_projects / $total_projects ) * 100 ) : 0; ?>%</p>
                    <p class="stat-label"><?php echo $completed_projects; ?> of <?php echo $total_projects; ?> projects</p>
                </div>
            </div>
            <div class="pm-report-stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </div>
                <div>
                    <h3>Task Completion</h3>
                    <p class="stat-value"><?php echo $total_tasks > 0 ? round( ( $completed_tasks / $total_tasks ) * 100 ) : 0; ?>%</p>
                    <p class="stat-label"><?php echo $completed_tasks; ?> of <?php echo $total_tasks; ?> tasks</p>
                </div>
            </div>
            <div class="pm-report-stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <h3>Hours Logged</h3>
                    <p class="stat-value"><?php echo number_format( $total_hours, 1 ); ?></p>
                    <p class="stat-label">In selected period</p>
                </div>
            </div>
            <div class="pm-report-stat-card">
                <div class="stat-icon" style="background:linear-gradient(135deg,#fa709a 0%,#fee140 100%);">
                    <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div>
                    <h3>Team Members</h3>
                    <p class="stat-value"><?php echo count( $team_performance ); ?></p>
                    <p class="stat-label">Active contributors</p>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="pm-reports-charts">
            <div class="pm-report-chart-card">
                <h3>Tasks by Status</h3>
                <div class="pm-status-chart">
                    <?php if ( ! empty( $tasks_by_status ) ) :
                        $max_status = max( array_column( $tasks_by_status, 'count' ) );
                        foreach ( $tasks_by_status as $stat ) :
                            $pct = $max_status > 0 ? ( $stat->count / $max_status ) * 100 : 0;
                    ?>
                        <div class="status-chart-item">
                            <span class="status-chart-label"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $stat->status ) ) ); ?></span>
                            <div class="status-chart-bar">
                                <div class="status-chart-fill" style="width:<?php echo $pct; ?>%;background:<?php echo pm_get_status_color( $stat->status ); ?>"></div>
                            </div>
                            <span class="status-chart-value"><?php echo $stat->count; ?></span>
                        </div>
                    <?php endforeach;
                    else : ?>
                        <p class="pm-empty-chart">No task data</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pm-report-chart-card">
                <h3>Tasks by Priority</h3>
                <div class="pm-priority-chart">
                    <?php if ( ! empty( $tasks_by_priority ) ) :
                        foreach ( $tasks_by_priority as $stat ) : ?>
                        <div class="priority-chart-item">
                            <div class="priority-chart-header">
                                <span class="priority-chart-badge" style="background:<?php echo pm_get_priority_color( $stat->priority ); ?>">
                                    <?php echo esc_html( ucfirst( $stat->priority ) ); ?>
                                </span>
                                <span class="priority-chart-count"><?php echo $stat->count; ?> tasks</span>
                            </div>
                        </div>
                    <?php endforeach;
                    else : ?>
                        <p class="pm-empty-chart">No priority data</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Project Performance -->
        <div class="pm-report-section">
            <h3>Project Performance</h3>
            <div class="pm-project-performance-table bntm-table-wrapper">
                <table class="bntm-table">
                    <thead>
                        <tr><th>Project</th><th>Status</th><th>Tasks</th><th>Completion</th><th>Hours Logged</th></tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $project_stats ) ) :
                            foreach ( $project_stats as $proj ) :
                                $comp = $proj->total_tasks > 0 ? round( ( $proj->completed_tasks / $proj->total_tasks ) * 100 ) : 0;
                        ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="width:4px;height:30px;background:<?php echo esc_attr($proj->color); ?>;border-radius:2px;"></div>
                                        <strong><?php echo esc_html( $proj->name ); ?></strong>
                                    </div>
                                </td>
                                <td><span class="pm-status-badge" style="background:<?php echo pm_get_status_color($proj->status); ?>"><?php echo esc_html(ucfirst($proj->status)); ?></span></td>
                                <td><?php echo $proj->completed_tasks; ?> / <?php echo $proj->total_tasks; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div class="pm-mini-progress" style="flex:1;max-width:150px;">
                                            <div class="pm-mini-progress-fill" style="width:<?php echo $comp; ?>%;background:<?php echo esc_attr($proj->color); ?>"></div>
                                        </div>
                                        <span style="font-weight:600;min-width:40px;"><?php echo $comp; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo number_format( $proj->total_hours, 1 ); ?>h</td>
                            </tr>
                        <?php endforeach;
                        else : ?>
                            <tr><td colspan="5" style="text-align:center;padding:40px;color:#9ca3af;">No projects found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Team Performance -->
        <?php if ( ! empty( $team_performance ) ) : ?>
        <div class="pm-report-section">
            <h3>Team Performance</h3>
            <div class="pm-team-performance-grid">
                <?php foreach ( $team_performance as $member ) :
                    $mc = $member->total_tasks > 0 ? round( ( $member->completed_tasks / $member->total_tasks ) * 100 ) : 0;
                ?>
                    <div class="pm-team-member-card">
                        <div class="team-member-header">
                            <div class="team-member-avatar"><?php echo esc_html( strtoupper( substr( $member->display_name, 0, 2 ) ) ); ?></div>
                            <div>
                                <h4><?php echo esc_html( $member->display_name ); ?></h4>
                                <p><?php echo $member->total_tasks; ?> tasks assigned</p>
                            </div>
                        </div>
                        <div class="team-member-stats">
                            <div class="team-stat"><span class="team-stat-label">Completed</span><span class="team-stat-value"><?php echo $member->completed_tasks; ?></span></div>
                            <div class="team-stat"><span class="team-stat-label">Hours</span><span class="team-stat-value"><?php echo number_format($member->total_hours,1); ?></span></div>
                            <div class="team-stat"><span class="team-stat-label">Rate</span><span class="team-stat-value"><?php echo $mc; ?>%</span></div>
                        </div>
                        <div class="team-member-progress">
                            <div class="progress-bar"><div class="progress-fill" style="width:<?php echo $mc; ?>%;background:#3b82f6;"></div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; // end overall stats block ?>
    </div>

    <style>
    .pm-reports-container{background:white;border-radius:12px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.1);}
    .pm-reports-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;}
    .pm-reports-header h2{margin:0;font-size:20px;font-weight:600;color:#111827;}
    .pm-reports-filters{display:flex;align-items:center;gap:12px;}
    .pm-personal-performance-section{background:#f9fafb;border-radius:12px;padding:20px;margin-bottom:24px;}
    .pm-personal-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}
    .pm-personal-stat-card{background:white;border-radius:8px;padding:16px;display:flex;gap:12px;align-items:flex-start;}
    .personal-stat-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .pm-personal-stat-card h4{margin:0 0 6px 0;font-size:13px;color:#6b7280;font-weight:500;}
    .pm-personal-stat-card .personal-stat-value{margin:0 0 4px 0;font-size:22px;font-weight:700;color:#111827;}
    .pm-personal-stat-card .personal-stat-label{font-size:12px;color:#9ca3af;margin:0;}
    .pm-personal-projects-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;}
    .pm-personal-project-card{background:white;border-radius:8px;padding:12px;}
    .personal-project-stats{display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-top:4px;}
    .pm-reports-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;margin-bottom:24px;}
    .pm-report-stat-card{background:#f9fafb;border-radius:12px;padding:20px;display:flex;gap:16px;align-items:flex-start;}
    .stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .pm-report-stat-card h3{margin:0 0 8px 0;font-size:14px;color:#6b7280;font-weight:500;}
    .pm-report-stat-card .stat-value{margin:0 0 4px 0;font-size:28px;font-weight:700;color:#111827;}
    .pm-report-stat-card .stat-label{font-size:13px;color:#9ca3af;}
    .pm-reports-charts{display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:24px;margin-bottom:24px;}
    .pm-report-chart-card{background:#f9fafb;border-radius:12px;padding:20px;}
    .pm-report-chart-card h3{margin:0 0 20px 0;font-size:16px;font-weight:600;color:#111827;}
    .pm-status-chart{display:flex;flex-direction:column;gap:12px;}
    .status-chart-item{display:flex;align-items:center;gap:12px;}
    .status-chart-label{min-width:100px;font-size:13px;color:#4b5563;font-weight:500;}
    .status-chart-bar{flex:1;height:24px;background:white;border-radius:4px;overflow:hidden;}
    .status-chart-fill{height:100%;transition:width 0.3s ease;}
    .status-chart-value{min-width:40px;text-align:right;font-weight:600;color:#374151;font-size:13px;}
    .pm-priority-chart{display:flex;flex-direction:column;gap:12px;}
    .priority-chart-item{background:white;padding:12px;border-radius:6px;}
    .priority-chart-header{display:flex;justify-content:space-between;align-items:center;}
    .priority-chart-badge{padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;color:white;}
    .priority-chart-count{font-size:13px;color:#6b7280;font-weight:600;}
    .pm-empty-chart{text-align:center;padding:40px 20px;color:#9ca3af;font-size:14px;}
    .pm-report-section{margin-top:24px;background:#f9fafb;border-radius:12px;padding:20px;}
    .pm-report-section h3{margin:0 0 16px 0;font-size:16px;font-weight:600;color:#111827;}
    .pm-team-performance-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;}
    .pm-team-member-card{background:white;border-radius:8px;padding:16px;}
    .team-member-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
    .team-member-avatar{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;}
    .team-member-header h4{margin:0 0 4px 0;font-size:15px;font-weight:600;color:#111827;}
    .team-member-header p{margin:0;font-size:13px;color:#6b7280;}
    .team-member-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:12px;}
    .team-stat{text-align:center;}
    .team-stat-label{display:block;font-size:12px;color:#6b7280;margin-bottom:4px;}
    .team-stat-value{display:block;font-size:18px;font-weight:700;color:#111827;}
    .team-member-progress{margin-top:12px;}
    .progress-bar{height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;}
    .progress-fill{height:100%;border-radius:3px;transition:width 0.3s ease;}
    .pm-mini-progress{flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden;min-width:60px;}
    .pm-mini-progress-fill{height:100%;border-radius:3px;transition:width 0.3s ease;}
    .pm-status-badge{display:inline-block;padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;color:white;}
    </style>

    <script>
    function pmApplyDateFilter() {
        const from = document.getElementById('pm-date-from').value;
        const to   = document.getElementById('pm-date-to').value;
        window.location.href = '?tab=reports&date_from=' + from + '&date_to=' + to;
    }
    </script>
    <?php
    return ob_get_clean();
}
/**
 * Logs Tab - Admin/Owner/Manager Only
 */
function pm_logs_tab($business_id) {
    global $wpdb;
    
    $current_user = wp_get_current_user();
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_access = $is_wp_admin || in_array($current_role, ['owner', 'manager']);
    
    // Check access
    if (!$can_access) {
        ob_start();
        ?>
        <div style="background: white; border-radius: 12px; padding: 60px 24px; text-align: center;">
            <svg width="64" height="64" fill="none" stroke="#d1d5db" viewBox="0 0 24 24" style="margin: 0 auto 20px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <h3 style="margin: 0 0 8px 0; font-size: 20px; color: #374151;">Access Restricted</h3>
            <p style="margin: 0; color: #6b7280; font-size: 15px;">You don't have permission to view activity logs. This feature is only available to administrators, owners, and managers.</p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    $activity_table = $wpdb->prefix . 'pm_activity_log';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    
    // Filters
    $filter_project = isset($_GET['filter_project']) ? intval($_GET['filter_project']) : 0;
    $filter_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';
    $filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
    $date_from = isset($_GET['log_date_from']) ? sanitize_text_field($_GET['log_date_from']) : date('Y-m-d', strtotime('-30 days'));
    $date_to = isset($_GET['log_date_to']) ? sanitize_text_field($_GET['log_date_to']) : date('Y-m-d');
    
    // Build query
    $where = ["DATE(a.created_at) BETWEEN %s AND %s"];
    $params = [$date_from, $date_to];
    
    if ($filter_project > 0) {
        $where[] = "a.project_id = %d";
        $params[] = $filter_project;
    }
    
    if ($filter_action) {
        $where[] = "a.action = %s";
        $params[] = $filter_action;
    }
    
    if ($filter_user > 0) {
        $where[] = "a.user_id = %d";
        $params[] = $filter_user;
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get logs with pagination
    $per_page = 50;
    $page = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    $total_logs = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$activity_table} a WHERE {$where_clause}",
        $params
    ));
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, u.display_name as user_name, p.name as project_name, p.color as project_color, t.title as task_title
         FROM {$activity_table} a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         LEFT JOIN {$projects_table} p ON a.project_id = p.id
         LEFT JOIN {$tasks_table} t ON a.task_id = t.id
         WHERE {$where_clause}
         ORDER BY a.created_at DESC
         LIMIT %d OFFSET %d",
        array_merge($params, [$per_page, $offset])
    ));
    
    // Get filter options
    $projects = $wpdb->get_results("SELECT id, name FROM {$projects_table} ORDER BY name ASC");
    $actions = $wpdb->get_col("SELECT DISTINCT action FROM {$activity_table} ORDER BY action ASC");
    $users = $wpdb->get_results(
        "SELECT DISTINCT u.ID, u.display_name 
         FROM {$wpdb->users} u
         INNER JOIN {$activity_table} a ON u.ID = a.user_id
         ORDER BY u.display_name ASC"
    );
    
    $total_pages = ceil($total_logs / $per_page);
    
    ob_start();
    ?>
    <div class="pm-logs-container">
        <div class="pm-logs-header">
            <h2>Activity Logs</h2>
            <div class="pm-logs-stats">
                <span class="log-stat">
                    <strong><?php echo number_format($total_logs); ?></strong> entries
                </span>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="pm-logs-filters">
            <div class="pm-filter-row bntm-form-group">
                <div class="pm-filter-group ">
                    <label>Date Range</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="date" id="pm-log-date-from" value="<?php echo esc_attr($date_from); ?>" class="bntm-input">
                        <span>to</span>
                        <input type="date" id="pm-log-date-to" value="<?php echo esc_attr($date_to); ?>" class="bntm-input">
                    </div>
                </div>
                
                <div class="pm-filter-group">
                    <label>Project</label>
                    <select id="pm-filter-project" class="bntm-input">
                        <option value="0">All Projects</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj->id; ?>" <?php selected($filter_project, $proj->id); ?>>
                                <?php echo esc_html($proj->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="pm-filter-group">
                    <label>Action Type</label>
                    <select id="pm-filter-action" class="bntm-input">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?php echo esc_attr($action); ?>" <?php selected($filter_action, $action); ?>>
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $action))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="pm-filter-group">
                    <label>User</label>
                    <select id="pm-filter-user" class="bntm-input">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user->ID; ?>" <?php selected($filter_user, $user->ID); ?>>
                                <?php echo esc_html($user->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="pm-filter-group" style="align-self: flex-end;">
                    <button class="bntm-btn-primary" onclick="pmApplyLogFilters()">Apply Filters</button>
                    <button class="bntm-btn-secondary" onclick="pmClearLogFilters()">Clear</button>
                </div>
            </div>
        </div>
        
        <!-- Logs Table -->
        <div class="pm-logs-table-container bntm-table-wrapper">
            <table class="bntm-table pm-logs-table">
                <thead>
                    <tr>
                        <th width="100">Date & Time</th>
                        <th width="300">User</th>
                        <th width="200">Project</th>
                        <th width="150">Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: #9ca3af;">
                                No activity logs found for the selected filters
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $action_icon = pm_get_action_icon($log->action);
                            $action_color = pm_get_action_color($log->action);
                        ?>
                            <tr>
                                <td>
                                    <div style="font-size: 13px; color: #374151;">
                                        <?php echo date('M d, Y', strtotime($log->created_at)); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #9ca3af;">
                                        <?php echo date('h:i A', strtotime($log->created_at)); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                      
                                        <span style="font-size: 13px; font-weight: 500;">
                                            <?php echo esc_html($log->user_name); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log->project_name): ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <div style="width: 3px; height: 24px; background: <?php echo esc_attr($log->project_color); ?>; border-radius: 2px;"></div>
                                            <span style="font-size: 13px;">
                                                <?php echo esc_html($log->project_name); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="log-action-badge" style="background: <?php echo $action_color; ?>15; color: <?php echo $action_color; ?>;">
                                        <?php echo $action_icon; ?>
                                        <span><?php echo esc_html(ucwords(str_replace('_', ' ', $log->action))); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 13px; color: #4b5563;">
                                        <?php echo esc_html($log->details); ?>
                                        <?php if ($log->task_title): ?>
                                            <span style="color: #9ca3af;"> • <?php echo esc_html($log->task_title); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pm-logs-pagination">
                <div class="pagination-info">
                    Showing <?php echo (($page - 1) * $per_page) + 1; ?> - <?php echo min($page * $per_page, $total_logs); ?> of <?php echo number_format($total_logs); ?> entries
                </div>
                <div class="pagination-buttons">
                    <?php if ($page > 1): ?>
                        <button class="bntm-btn-secondary" onclick="pmGoToLogPage(<?php echo $page - 1; ?>)">
                            ← Previous
                        </button>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <button class="bntm-btn-secondary <?php echo $i === $page ? 'active' : ''; ?>" 
                                onclick="pmGoToLogPage(<?php echo $i; ?>)">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <button class="bntm-btn-secondary" onclick="pmGoToLogPage(<?php echo $page + 1; ?>)">
                            Next →
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .pm-logs-container {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .pm-logs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .pm-logs-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
        color: #111827;
    }
    
    .pm-logs-stats {
        display: flex;
        gap: 20px;
    }
    
    .log-stat {
        font-size: 14px;
        color: #6b7280;
    }
    
    .log-stat strong {
        color: #111827;
        font-size: 18px;
    }
    
    .pm-logs-filters {
        background: #f9fafb;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
    }
    
    .pm-filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        align-items: end;
    }
    
    .pm-filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .pm-filter-group label {
        font-size: 13px;
        font-weight: 500;
        color: #374151;
    }
    
    .pm-logs-table-container {
        overflow-x: auto;
        margin-bottom: 20px;
    }
    
    .pm-logs-table {
        width: 100%;
    }
    

    
    .log-action-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .pm-logs-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    .pagination-info {
        font-size: 14px;
        color: #6b7280;
    }
    
    .pagination-buttons {
        display: flex;
        gap: 8px;
    }
    
    .pagination-buttons .bntm-btn-secondary.active {
        background: #3b82f6;
        color: white;
    }
    </style>
    
    <script>
    function pmApplyLogFilters() {
        const dateFrom = document.getElementById('pm-log-date-from').value;
        const dateTo = document.getElementById('pm-log-date-to').value;
        const project = document.getElementById('pm-filter-project').value;
        const action = document.getElementById('pm-filter-action').value;
        const user = document.getElementById('pm-filter-user').value;
        
        let url = '?tab=logs';
        url += '&log_date_from=' + dateFrom;
        url += '&log_date_to=' + dateTo;
        if (project > 0) url += '&filter_project=' + project;
        if (action) url += '&filter_action=' + action;
        if (user > 0) url += '&filter_user=' + user;
        
        window.location.href = url;
    }
    
    function pmClearLogFilters() {
        window.location.href = '?tab=logs';
    }
    
    function pmGoToLogPage(page) {
        const url = new URL(window.location.href);
        url.searchParams.set('log_page', page);
        window.location.href = url.toString();
    }
    </script>
    <?php
    return ob_get_clean();
}



/* ---------- PROJECT DETAIL PAGE ---------- */
/**
 * Project Detail Page
 */ 
 function pm_project_detail_page($business_id)
{
    global $wpdb;
    $project_id = intval($_GET["project_id"]);
    $projects_table = $wpdb->prefix . "pm_projects";
    $tasks_table = $wpdb->prefix . "pm_tasks";
    $milestones_table = $wpdb->prefix . "pm_milestones";
    $team_table = $wpdb->prefix . "pm_team_members";
    $statuses_table = $wpdb->prefix . "pm_project_statuses"; // Get project
    $project = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$projects_table} WHERE id = %d ",
            $project_id,
            $business_id
        )
    );
    
    if (!$project) {
        return '<div class="bntm-notice bntm-notice-error">Project not found.</div>';
    } // Get active subtab
    $subtab = isset($_GET["subtab"])
        ? sanitize_text_field($_GET["subtab"])
        : "overview";
        
        
        
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $is_wp_admin = current_user_can('manage_options');
    $current_role = bntm_get_user_role($current_user->ID);
    $can_access_resources = $is_wp_admin || in_array($current_role, ['owner', 'manager']);
    
    $nonce = wp_create_nonce("pm_project_detail_nonce");
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
    var pmProjectId = <?php echo $project_id; ?>;
    var pmCurrentUser = <?php echo $business_id; ?>;
    </script>
    <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === "overview" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Overview
            </a>
            <a href="?tab=projects" class="bntm-tab <?php echo $active_tab === "projects" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Projects
            </a>
            <a href="?tab=board" class="bntm-tab <?php echo $active_tab === "board" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Project Board
            </a>
            <a href="?tab=reports" class="bntm-tab <?php echo $active_tab === "reports" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Reports
            </a>
            <?php if ($can_access_resources): ?>
            <a href="?tab=resources" class="bntm-tab <?php echo $active_tab === "resources" ? "active" : ""; ?>">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Resources
            </a>
            <?php endif; ?>
        </div>
        
    <div class="pm-project-detail">
        
        <!-- Sidebar -->
        <div class="pm-sidebar" id="pm-sidebar">
            <div class="pm-sidebar-header">
                <div class="sidebar-header-content">
                    <button class="pm-sidebar-toggle" onclick="pmToggleSidebar()">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <a href="?tab=board" class="bntm-btn-secondary sidebar-back-btn">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Board
                    </a>
                </div>
            </div>
            
            <div class="pm-sidebar-content">
                <div class="pm-project-sidebar-header" style="border-left: 3px solid transparent; display: flex ; gap: 20px; align-items: center;">
                    <div class="project-color-shape" style="width: 30px; height: 30px;background: <?php echo esc_attr($project->color); ?>"></div>
                    <div style="display: flex ; gap: 10px; align-items: center; align-content: center; justify-content: center; flex-direction: column;">
                    <h3><?php echo esc_html($project->name); ?></h3>
                    <span class="pm-status-badge" style="background: <?php echo pm_get_status_color(
                        $project->status
                    ); ?>">
                        <?php echo ucfirst($project->status); ?>
                    </span>
                    </div>
                </div>
                
                <nav class="pm-sidebar-nav">
                    <a href="?project_id=<?php echo $project_id; ?>&subtab=overview" 
                       class="pm-sidebar-link <?php echo $subtab === "overview"
                           ? "active"
                           : ""; ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Overview
                    </a>
                    <a href="?project_id=<?php echo $project_id; ?>&subtab=tasks" 
                       class="pm-sidebar-link <?php echo $subtab === "tasks"
                           ? "active"
                           : ""; ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        Tasks
                    </a>
                    <a href="?project_id=<?php echo $project_id; ?>&subtab=kanban" 
                       class="pm-sidebar-link <?php echo $subtab === "kanban"
                           ? "active"
                           : ""; ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                        </svg>
                        Kanban
                    </a>
                    <a href="?project_id=<?php echo $project_id; ?>&subtab=team" 
                       class="pm-sidebar-link <?php echo $subtab === "team"
                           ? "active"
                           : ""; ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Team
                    </a>
                    <a href="?project_id=<?php echo $project_id; ?>&subtab=settings" 
                       class="pm-sidebar-link <?php echo $subtab === "settings"
                           ? "active"
                           : ""; ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                </nav>
                <div class="pm-sidebar-section pm-projects-list-section">
                    <div class="pm-sidebar-section-header" onclick="pmToggleProjectsList(this)">
                        <button type="button" class="sidebar-section-toggle-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="section-chevron">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <h4>Projects</h4>
                    </div>
                    
                    <div class="pm-sidebar-section-content">
                        <?php
                        $all_projects = $wpdb->get_results("SELECT * FROM {$projects_table}
                            WHERE status NOT IN ('completed', 'cancelled','on_hold')
                            ORDER BY sort_order ASC, created_at DESC");
                        
                        if (!empty($all_projects)):
                            foreach ($all_projects as $proj):
                                $proj_tasks = $wpdb->get_results($wpdb->prepare(
                                    "SELECT * FROM {$tasks_table} 
                                     WHERE project_id = %d AND status NOT IN ('completed', 'closed')
                                     ORDER BY sort_order ASC",
                                    $proj->id
                                ));
                                $is_current = ($project->id == $proj->id) ? 'current' : '';
                        ?>
                            <a href="?project_id=<?php echo $proj->id; ?>&subtab=<?php echo $subtab; ?>" 
                               class="pm-sidebar-project-card <?php echo $is_current; ?>">
                                <div class="project-color-shape" style="background: <?php echo esc_attr($proj->color); ?>"></div>
                                <div class="sidebar-card-info">
                                    <h5><?php echo esc_html($proj->name); ?></h5>
                                    <p><?php echo count($proj_tasks); ?> active</p>
                                </div>
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="project-arrow">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <div class="pm-sidebar-section-empty">
                                <p>No active projects</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="pm-main-content" id="pm-main-content">
            <?php if ($subtab === "overview") {
                echo pm_project_overview_subtab($project, $business_id);
            } elseif ($subtab === "tasks") {
                echo pm_project_tasks_subtab($project, $business_id, $nonce);
            } elseif ($subtab === "kanban") {
                echo pm_project_kanban_subtab($project, $business_id, $nonce);
            } elseif ($subtab === "team") {
                echo pm_project_team_subtab($project, $business_id, $nonce);
            } elseif ($subtab === "settings") {
                echo pm_project_settings_subtab($project, $business_id, $nonce);
            } ?>
        </div>
    </div>
    
    <style>
    
    .bntm-container {
    max-width: unset;
}
    .pm-project-detail {
        display: flex;
        gap: 0;
        min-height: 600px;
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .pm-sidebar {
    width: 280px;
    background: #f9fafb;
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.pm-sidebar.collapsed {
    width: 60px;
    min-width: 60px;
}

.pm-sidebar.collapsed .pm-sidebar-content {
    opacity: 0;
    visibility: hidden;
}

.pm-sidebar.collapsed .pm-project-sidebar-header,
.pm-sidebar.collapsed .pm-sidebar-nav {
    display: none;
}

.pm-sidebar.collapsed .sidebar-back-btn {
    display: none;
}

.pm-sidebar-header {
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.sidebar-header-content {
    display: flex;
    gap: 8px;
    align-items: center;
}

.pm-sidebar-toggle {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
    flex-shrink: 0;
}

.pm-sidebar-toggle:hover {
    background: #f3f4f6;
}

.sidebar-back-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    font-size: 14px;
    text-decoration: none;
}

.pm-sidebar-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.pm-project-sidebar-header {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    padding-left: 24px;
}

.pm-project-sidebar-header h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.pm-sidebar-nav {
    flex: 1;
    padding: 12px;
}

.pm-sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 8px;
    color: #4b5563;
    text-decoration: none;
    transition: all 0.2s;
    margin-bottom: 4px;
    font-size: 14px;
    font-weight: 500;
}

.pm-sidebar-link:hover {
    background: white;
    color: #111827;
}

.pm-sidebar-link.active {
    background: white;
    color: #3b82f6;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pm-sidebar-footer {
    padding: 16px;
    border-top: 1px solid #e5e7eb;
}

.pm-main-content {
    flex: 1;
    padding: 24px;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .pm-sidebar {
        position: absolute;
        height: 100%;
        z-index: 100;
        box-shadow: 2px 0 8px rgba(0,0,0,0.1);
    }
}
.pm-sidebar-section {
    margin-top: 16px;
    padding: 0 12px;
}

.pm-sidebar-section-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 8px;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s ease;
    border-radius: 6px;
}

.pm-sidebar-section-header:hover {
    background: rgba(255, 255, 255, 0.5);
}

.pm-sidebar-section-header h4 {
    margin: 0;
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.sidebar-section-toggle-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    display: flex;
    align-items: center;
    color: #9ca3af;
}

.section-chevron {
    transition: transform 0.3s ease;
}

.pm-sidebar-section.collapsed .section-chevron {
    transform: rotate(-90deg);
}

.pm-sidebar-section-content {
    display: grid;
    grid-template-rows: 1fr;
    gap: 6px;
    padding: 8px 0;
    overflow: hidden;
    transition: grid-template-rows 0.3s ease, opacity 0.3s ease, padding 0.3s ease;
    opacity: 1;
}

.pm-sidebar-section.collapsed .pm-sidebar-section-content {
    grid-template-rows: 0fr;
    opacity: 0;
    padding: 0;
}

.pm-sidebar-project-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: white;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.pm-sidebar-project-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    background: rgba(59, 130, 246, 0.05);
    transition: width 0.3s ease;
}

.pm-sidebar-project-card:hover::before {
    width: 100%;
}

.pm-sidebar-project-card:hover {
    transform: translateX(4px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.pm-sidebar-project-card.current {
    background: #eff6ff;
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.1);
}

.pm-sidebar-project-card.current .sidebar-card-info h5 {
    color: #3b82f6;
}

.project-color-shape {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}

.pm-sidebar-project-card:hover .project-color-shape {
    transform: scale(1.3);
}

.sidebar-card-info {
    flex: 1;
    min-width: 0;
}

.sidebar-card-info h5 {
    margin: 0 0 3px 0;
    font-size: 13px;
    font-weight: 600;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.2s ease;
}

.sidebar-card-info p {
    margin: 0;
    font-size: 11px;
    color: #9ca3af;
    transition: color 0.2s ease;
}

.pm-sidebar-project-card:hover .sidebar-card-info p {
    color: #6b7280;
}

.pm-sidebar-project-card .project-arrow {
    flex-shrink: 0;
    color: #d1d5db;
    transition: all 0.2s ease;
    opacity: 0;
}

.pm-sidebar-project-card:hover .project-arrow {
    opacity: 1;
    transform: translateX(2px);
}

.pm-sidebar-project-card.current .project-arrow {
    opacity: 1;
    color: #3b82f6;
}

.pm-sidebar-section-empty {
    text-align: center;
    padding: 16px 8px;
    animation: fadeIn 0.3s ease;
}

.pm-sidebar-section-empty p {
    margin: 0;
    font-size: 12px;
    color: #9ca3af;
}

@keyframes fadeIn {
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
    <style>
    
/* ========================================
   GLOBAL STYLES
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .pm-tasks-container {
        /*max-width: 1200px;*/
    }
    
    .pm-tasks-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .pm-tasks-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    
    .pm-tasks-actions {
        display: flex;
        gap: 12px;
    }
    
    .pm-milestone-section {
        background: #f9fafb;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .pm-milestone-section {
    position: relative;
}

.pm-milestone-section.dragging {
    opacity: 0.4;
    cursor: grabbing;
}

.pm-milestone-drag-handle {
    cursor: grab;
    padding: 4px 8px;
    color: #9ca3af;
    font-size: 18px;
    font-weight: bold;
    letter-spacing: -2px;
    user-select: none;
    margin-right: 8px;
}

.pm-milestone-drag-handle:hover {
    color: #6b7280;
}

.pm-milestone-drag-handle:active {
    cursor: grabbing;
}
    .pm-milestone-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .milestone-info {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .milestone-info h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    .milestone-due {
        font-size: 13px;
        color: #6b7280;
        background: white;
        padding: 4px 10px;
        border-radius: 6px;
    }
    
    .milestone-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .milestone-count {
        font-size: 13px;
        color: #6b7280;
        font-weight: 600;
    }
    
    .milestone-progress-mini {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .milestone-progress-mini .progress-bar {
        width: 80px;
        height: 6px;
        background: #e5e7eb;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .milestone-progress-mini span {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        min-width: 35px;
    }
    .pm-task-filter-toggle {
    display: flex;
    background: #f3f4f6;
    border-radius: 8px;
    padding: 4px;
    gap: 4px;
}
    
    .pm-filter-btn {
        padding: 8px 16px;
        border: none;
        background: transparent;
        color: #6b7280;
        font-size: 14px;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .pm-filter-btn:hover {
        color: #374151;
    }
    
    .pm-filter-btn.active {
        background: white;
        color: #3b82f6;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .pm-status-filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.pm-task-row.status-hidden {
    display: none !important;
}

    .sortable-milestone-tasks {
        min-height: 60px;
        max-height: 500px;
        overflow-y: scroll;
    }
    
    .sortable-milestone-tasks::-webkit-scrollbar {
        width: 8px;
    }
    
    .sortable-milestone-tasks::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .sortable-milestone-tasks::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }
    
    .sortable-milestone-tasks::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
    .milestone-toggle-btn {
        background: none;
        border: none;
        padding: 0;
        cursor: pointer;
        display: flex;
        align-items: center;
        color: #6b7280;
        transition: transform 0.2s;
    }
    
    .milestone-chevron {
        transition: transform 0.2s;
    }
    
    .pm-milestone-section.collapsed .milestone-chevron {
        transform: rotate(-90deg);
    }
    
    .pm-milestone-section.collapsed .pm-tasks-list {
        display: none;
    }
    
    .milestone-info {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
    }
    
    .pm-milestone-header {
        cursor: pointer;
        user-select: none;
    }
    .pm-empty-tasks {
        pointer-events: none; /* Allows drag events to pass through the empty state message */
    }
    .pm-tasks-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .pm-empty-tasks {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
        font-size: 14px;
        background: white;
        border-radius: 8px;
    }
    
    .pm-task-row {
        background: white;
        border-radius: 8px;
        padding: 16px;
        display: grid;
        grid-template-columns: auto 1fr auto auto auto;
        gap: 16px;
        align-items: center;
        transition: all 0.2s;
        cursor: move;
    }
    
    .pm-task-row:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .pm-task-row.dragging {
        opacity: 0.5;
    }
    
    .task-drag-handle {
        cursor: move;
        color: #9ca3af;
    }
    
    .task-row-content {
        min-width: 0;
    }
    
    .task-row-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
    
    .task-row-priority {
        width: 4px;
        height: 18px;
        border-radius: 2px;
        flex-shrink: 0;
    }
    
    .task-row-priority.priority-low {
        background: #3b82f6;
    }
    
    .task-row-priority.priority-medium {
        background: #f59e0b;
    }
    
    .task-row-priority.priority-high {
        background: #ef4444;
    }
    
    .task-row-title h4 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        flex: 1;
    }
    
    .task-row-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        font-size: 13px;
    }
    
    .task-row-assignee, .task-row-due {
        display: flex;
        align-items: center;
        gap: 4px;
        color: #6b7280;
    }
    
    .task-row-due.overdue {
        color: #ef4444;
        font-weight: 600;
    }
    
    .task-row-tags {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }
    
    .task-tag {
        padding: 3px 8px;
        background: #e5e7eb;
        color: #4b5563;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .task-row-status {
        min-width: 120px;
        overflow: visible;
    }
    
    .task-status-dropdown {
        position: relative;
        display: inline-block;
        width: 100%;
        overflow: visible !important;
        z-index: 10;
    }
    
    .task-status-btn {
        width: 100%;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        color: white;
        border: none;
        cursor: pointer;
        text-align: left;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .task-status-menu {
        position: fixed;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        margin-top: 4px;
        display: none;
        min-width: 150px;
    }
    
    .task-status-menu.show {
        display: block;
    }
    
    .task-status-option {
        padding: 10px 12px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: white;
        transition: opacity 0.2s;
    }
    
    .task-status-option:hover {
        opacity: 0.8;
    }
    
    .task-row-team {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #6b7280;
    }
    
    .task-row-actions {
        display: flex;
        gap: 6px;
    }
    
    .pm-empty-state-large {
        text-align: center;
        padding: 60px 20px;
    }
    
    .pm-empty-state-large svg {
        margin: 0 auto 20px;
    }
    
    .pm-empty-state-large h3 {
        margin: 0 0 8px 0;
        font-size: 20px;
        color: #374151;
    }
    
    .pm-empty-state-large p {
        margin: 0 0 24px 0;
        color: #6b7280;
        font-size: 15px;
    }
    
    .logs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .logs-header h4 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
        color: #111827;
    }
    
    .pm-time-log-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 8px;
    }
    
    .time-log-info p {
        margin: 0 0 4px 0;
        font-size: 13px;
        color: #111827;
    }
    
    .time-log-info small {
        font-size: 12px;
        color: #6b7280;
    }
    
    .time-log-hours {
        font-weight: 700;
        color: #3b82f6;
        font-size: 15px;
    }
    
    .pm-comment-item {
        padding: 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 12px;
    }
    
    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .comment-author {
        font-weight: 600;
        color: #111827;
        font-size: 13px;
    }
    
    .comment-time {
        font-size: 12px;
        color: #9ca3af;
    }
    
    .comment-text {
        margin: 0;
        font-size: 14px;
        color: #4b5563;
        line-height: 1.5;
    }
    
    .pm-add-comment {
        margin-top: 16px;
    }
    .pm-import-task-item {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 8px;
        display: flex;
        align-items: start;
        gap: 12px;
    }
    
    .pm-import-task-item input[type="checkbox"] {
        margin-top: 4px;
    }
    
    .pm-import-task-content {
        flex: 1;
    }
    
    .pm-import-task-title {
        font-weight: 600;
        color: #111827;
        margin-bottom: 4px;
    }
    
    .pm-import-task-meta {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #6b7280;
    }
    
    .pm-import-task-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    </style>
    
<script>
// Task filtering state
let currentAssignmentFilter = 'all';
let currentStatusFilter = '';
let showCompleted = false;

function pmOpenTaskModal() {
    document.getElementById('pm-task-modal').style.display = 'flex';
    document.getElementById('pm-task-form').reset();
    document.getElementById('pm-task-id').value = '';
    document.getElementById('pm-task-logs-section').style.display = 'none';
    document.getElementById('pm-export-google-calendar-btn').style.display = 'none';
    document.getElementById('pm-task-modal-title').textContent = 'Create New Task';
    document.getElementById('pm-task-submit-text').textContent = 'Create Task';
}

function pmCloseTaskModal() {
    document.getElementById('pm-task-modal').style.display = 'none';
}

function pmEditTask(taskId) {
    // Fetch task details via AJAX
    const formData = new FormData();
    formData.append('action', 'pm_get_task_details');
    formData.append('task_id', taskId);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            const task = json.data.task;
            document.getElementById('pm-task-modal').style.display = 'flex';
            document.getElementById('pm-task-id').value = taskId;
            document.getElementById('pm-task-title').value = task.title;
            document.getElementById('pm-task-description').value = task.description || '';
            document.getElementById('pm-task-status').value = task.status;
            document.getElementById('pm-task-priority').value = task.priority;
            document.getElementById('pm-task-assigned').value = task.assigned_to || '';
            document.getElementById('pm-task-milestone').value = task.milestone_id || '';
            document.getElementById('pm-task-due').value = task.due_date || '';
            document.getElementById('pm-task-estimated').value = task.estimated_hours || '';
            document.getElementById('pm-task-tags').value = task.tags || '';
            document.getElementById('pm-task-modal-title').textContent = 'Edit Task';
            document.getElementById('pm-task-submit-text').textContent = 'Update Task';
            
            // Show Google Calendar export button
            document.getElementById('pm-export-google-calendar-btn').style.display = 'inline-flex';
            
            // Show logs section
            document.getElementById('pm-task-logs-section').style.display = 'block';
            
            // Load time logs
            pmLoadTimeLogs(task.id, json.data.time_logs);
            
            // Load comments
            pmLoadComments(task.id, json.data.comments);
        }
    });
}

function pmDeleteTask(id, title) {
    if (!confirm('Delete task "' + title + '"?')) return;
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_task');
    formData.append('task_id', id);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Failed to delete task');
        }
    });
}

document.getElementById('pm-task-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const taskId = document.getElementById('pm-task-id').value;
    formData.append('action', taskId ? 'pm_update_task' : 'pm_create_task');
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = taskId ? 'Updating...' : 'Creating...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Operation failed');
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = taskId ? 'Update Task' : 'Create Task';
        }
    });
});

// Performance optimization: Debounce for filter functions
let filterDebounceTimer;
function debounceFilter() {
    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(applyAllFilters, 50);
}

// Task Filtering Functions
function pmFilterTasks(filter) {
    currentAssignmentFilter = filter;
    
    // Update active button - cache button queries
    const buttons = document.querySelectorAll('.pm-filter-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    const activeBtn = document.querySelector(`.pm-filter-btn[data-filter="${filter}"]`);
    if (activeBtn) activeBtn.classList.add('active');
    
    debounceFilter();
}

function pmFilterByStatus() {
    const statusFilter = document.getElementById('pm-status-filter');
    if (statusFilter) {
        currentStatusFilter = statusFilter.value;
        debounceFilter();
    }
}

// Optimized filtering with caching
function applyAllFilters() {
    const currentUserId = '<?php echo get_current_user_id(); ?>';
    const allTaskRows = document.querySelectorAll('.pm-task-row');
    
    // Batch DOM operations
    const updates = [];
    allTaskRows.forEach(taskRow => {
        const assignedTo = taskRow.getAttribute('data-assigned-to');
        const taskStatus = taskRow.getAttribute('data-task-status');
        
        let show = true;
        
        // Apply assignment filter
        if (currentAssignmentFilter === 'my' && assignedTo !== currentUserId) {
            show = false;
        }
        
        // Apply status filter
        if (currentStatusFilter && taskStatus !== currentStatusFilter) {
            show = false;
        }

        updates.push({row: taskRow, show, assignedTo, taskStatus});
    });
    
    // Apply all updates in batch
    updates.forEach(({row, show, assignedTo, taskStatus}) => {
        row.style.display = show ? '' : 'none';
        
        // Batch classList operations
        row.classList.toggle('assignment-hidden', currentAssignmentFilter === 'my' && assignedTo !== currentUserId);
        row.classList.toggle('status-hidden', currentStatusFilter && taskStatus !== currentStatusFilter);
    });
    
    // Update milestone counts and empty states
    pmUpdateMilestoneCounts();
}

function pmUpdateMilestoneCounts() {
    // Batch DOM operations to avoid reflows
    const milestoneSections = document.querySelectorAll('.pm-milestone-section');
    const updates = [];
    
    milestoneSections.forEach(section => {
        const milestoneContainer = section.querySelector('.sortable-milestone-tasks');
        if (!milestoneContainer) return;
        
        const taskRows = milestoneContainer.querySelectorAll('.pm-task-row');
        const visibleTasks = Array.from(taskRows).filter(row => row.style.display !== 'none');
        
        const countSpan = section.querySelector('.milestone-count');
        const allTasks = taskRows;
        let emptyState = milestoneContainer.querySelector('.pm-empty-tasks.pm-dynamic-empty');
        
        updates.push({
            countSpan,
            visibleCount: visibleTasks.length,
            hasAnyTasks: allTasks.length > 0,
            emptyState,
            section: milestoneContainer,
            shouldShowEmpty: visibleTasks.length === 0 && allTasks.length > 0
        });
    });
    
    // Apply all updates in batch
    updates.forEach(({countSpan, visibleCount, hasAnyTasks, emptyState, section, shouldShowEmpty}) => {
        if (countSpan) {
            countSpan.textContent = visibleCount + ' task' + (visibleCount !== 1 ? 's' : '');
        }
        
        if (shouldShowEmpty) {
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'pm-empty-tasks pm-dynamic-empty';
                section.appendChild(emptyState);
            }
            
            // Determine empty message based on active filters
            let message = 'No tasks match the current filters';
            if (currentAssignmentFilter === 'my') {
                message = 'No tasks assigned to you in this milestone';
            } else if (currentStatusFilter) {
                message = `No tasks with status: ${currentStatusFilter}`;
            } else if (!showCompleted) {
                message = 'No active tasks (completed tasks hidden)';
            }
            
            emptyState.textContent = message;
            emptyState.style.display = 'block';
        } else if (emptyState) {
            emptyState.style.display = 'none';
        }
    });
}

function pmToggleMilestone(headerElement) {
    const milestoneSection = headerElement.closest('.pm-milestone-section');
    milestoneSection.classList.toggle('collapsed');
}

// Milestone functions
function pmOpenMilestoneModal() {
    document.getElementById('pm-milestone-modal').style.display = 'flex';
    document.getElementById('pm-milestone-form').reset();
    document.getElementById('pm-milestone-id').value = '';
    document.getElementById('pm-milestone-modal-title').textContent = 'Create Milestone';
    document.getElementById('pm-milestone-submit-text').textContent = 'Create Milestone';
}

function pmCloseMilestoneModal() {
    document.getElementById('pm-milestone-modal').style.display = 'none';
}

function pmEditMilestone(milestone) {
    document.getElementById('pm-milestone-modal').style.display = 'flex';
    document.getElementById('pm-milestone-id').value = milestone.id;
    document.getElementById('pm-milestone-name').value = milestone.name;
    document.getElementById('pm-milestone-description').value = milestone.description || '';
    document.getElementById('pm-milestone-due').value = milestone.due_date || '';
    document.getElementById('pm-milestone-status').value = milestone.status;
    document.getElementById('pm-milestone-modal-title').textContent = 'Edit Milestone';
    document.getElementById('pm-milestone-submit-text').textContent = 'Update Milestone';
}

function pmDeleteMilestone(id, name) {
    if (!confirm('Delete milestone "' + name + '"?\n\nTasks in this milestone will be moved to "No Milestone".')) return;
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_milestone');
    formData.append('milestone_id', id);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Failed to delete milestone');
        }
    });
}

document.getElementById('pm-milestone-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const milestoneId = document.getElementById('pm-milestone-id').value;
    formData.append('action', milestoneId ? 'pm_update_milestone' : 'pm_create_milestone');
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = milestoneId ? 'Updating...' : 'Creating...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Operation failed');
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = milestoneId ? 'Update Milestone' : 'Create Milestone';
        }
    });
});

// Export Tasks Functions
function pmExportTasksJSON() {
    const projectId = '<?php echo $project->id; ?>';
    const projectName = '<?php echo esc_js($project->name); ?>';
    
    const formData = new FormData();
    formData.append('action', 'pm_export_tasks');
    formData.append('project_id', projectId);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            const tasks = json.data.tasks;
            const filename = `tasks_${projectName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.json`;
            const dataStr = JSON.stringify(tasks, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            alert(`Successfully exported ${tasks.length} task(s)`);
        } else {
            alert(json.data.message || 'Failed to export tasks');
        }
    })
    .catch(error => {
        alert('Error exporting tasks: ' + error.message);
    });
}

// Import Tasks Functions
let importedTasks = [];

function pmOpenImportModal() {
    document.getElementById('pm-import-modal').style.display = 'flex';
    document.getElementById('pm-import-file').value = '';
    document.getElementById('pm-import-preview').style.display = 'none';
    document.getElementById('pm-import-btn').style.display = 'none';
    importedTasks = [];
}

function pmCloseImportModal() {
    document.getElementById('pm-import-modal').style.display = 'none';
}

function pmLoadJsonFile(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const tasks = JSON.parse(e.target.result);
            
            if (!Array.isArray(tasks)) {
                alert('Invalid JSON format. Expected an array of tasks.');
                return;
            }
            
            importedTasks = tasks.map((task, index) => ({
                ...task,
                _index: index,
                _selected: true
            }));
            
            pmRenderTasksPreview();
            document.getElementById('pm-import-preview').style.display = 'block';
            document.getElementById('pm-import-btn').style.display = 'inline-block';
            pmUpdateSelectedCount();
            
        } catch (error) {
            alert('Error parsing JSON file: ' + error.message);
        }
    };
    reader.readAsText(file);
}

function pmRenderTasksPreview() {
    const container = document.getElementById('pm-tasks-preview-list');
    let html = '';
    
    importedTasks.forEach(task => {
        const priority = task.priority || 'medium';
        const priorityColors = {
            low: '#3b82f6',
            medium: '#f59e0b',
            high: '#ef4444'
        };
        
        html += `
            <div class="pm-import-task-item">
                <input type="checkbox" 
                       ${task._selected ? 'checked' : ''} 
                       onchange="pmToggleTaskSelection(${task._index})"
                       id="import-task-${task._index}">
                <label for="import-task-${task._index}" class="pm-import-task-content" style="cursor: pointer;">
                    <div class="pm-import-task-title">${task.title || 'Untitled Task'}</div>
                    ${task.description ? `<div style="font-size: 13px; color: #6b7280; margin-bottom: 6px;">${task.description}</div>` : ''}
                    <div class="pm-import-task-meta">
                        <span style="color: ${priorityColors[priority]};">
                            <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                            ${priority.charAt(0).toUpperCase() + priority.slice(1)} Priority
                        </span>
                        ${task.status ? `<span>Status: ${task.status}</span>` : ''}
                        ${task.milestone ? `<span>📍 ${task.milestone}</span>` : ''}
                        ${task.assigned_to ? `<span>👤 ${task.assigned_to}</span>` : ''}
                        ${task.due_date ? `<span>📅 ${task.due_date}</span>` : ''}
                        ${task.estimated_hours ? `<span>⏱️ ${task.estimated_hours}h</span>` : ''}
                    </div>
                </label>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function pmToggleTaskSelection(index) {
    importedTasks[index]._selected = !importedTasks[index]._selected;
    pmUpdateSelectedCount();
}

function pmSelectAllTasks(select) {
    importedTasks.forEach(task => task._selected = select);
    document.querySelectorAll('#pm-tasks-preview-list input[type="checkbox"]').forEach(cb => {
        cb.checked = select;
    });
    pmUpdateSelectedCount();
}

function pmUpdateSelectedCount() {
    const count = importedTasks.filter(t => t._selected).length;
    document.getElementById('pm-selected-count').textContent = count;
}

function pmImportTasks() {
    const selectedTasks = importedTasks.filter(t => t._selected);
    
    if (selectedTasks.length === 0) {
        alert('Please select at least one task to import');
        return;
    }
    
    const importBtn = document.getElementById('pm-import-btn');
    importBtn.disabled = true;
    importBtn.textContent = 'Importing...';
    
    const formData = new FormData();
    formData.append('action', 'pm_import_tasks');
    formData.append('project_id', '<?php echo $project->id; ?>');
    formData.append('tasks', JSON.stringify(selectedTasks));
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            alert(`Successfully imported ${json.data.imported} task(s)`);
            location.reload();
        } else {
            alert(json.data.message || 'Failed to import tasks');
            importBtn.disabled = false;
            importBtn.textContent = 'Import Selected Tasks';
        }
    })
    .catch(error => {
        alert('Error importing tasks: ' + error.message);
        importBtn.disabled = false;
        importBtn.textContent = 'Import Selected Tasks';
    });
}

// Time log functions
function pmOpenTimeLogForm() {
    document.getElementById('pm-time-log-form').style.display = 'block';
}

function pmCloseTimeLogForm() {
    document.getElementById('pm-time-log-form').style.display = 'none';
    document.getElementById('pm-log-hours').value = '';
    document.getElementById('pm-log-notes').value = '';
}

function pmSaveTimeLog() {
    const taskId = document.getElementById('pm-task-id').value;
    const hours = document.getElementById('pm-log-hours').value;
    const logDate = document.getElementById('pm-log-date').value;
    const notes = document.getElementById('pm-log-notes').value;
    
    if (!hours || !logDate) {
        alert('Please fill in required fields (Hours and Date)');
        return;
    }
    /*
    if (parseFloat(hours) < 0.5) {
        alert('Hours must be at least 0.5');
        return;
    }
    */
    
    const formData = new FormData();
    formData.append('action', 'pm_add_time_log');
    formData.append('task_id', taskId);
    formData.append('hours', hours);
    formData.append('log_date', logDate);
    formData.append('notes', notes);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            pmCloseTimeLogForm();
            pmEditTask(taskId);
        } else {
            alert(json.data.message || 'Failed to add time log');
        }
    });
}
// URL regex - cached as regex compiles only once
const PM_URL_REGEX = /(https?:\/\/[^\s]+)/g;

function pmFormatText(text) {
    if (!text) return '';
    // Escape HTML & convert newlines in one pass
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/\n/g, '<br>');
}

function pmMakeLinkClickable(text) {
    if (!text || !PM_URL_REGEX.test(text)) return text;
    PM_URL_REGEX.lastIndex = 0; // Reset regex
    return text.replace(PM_URL_REGEX, '<a href="$1" target="_blank" class="time-log-link" style="color: #3b82f6; text-decoration: underline;">$1</a>');
}

function pmLoadTimeLogs(taskId, logs) {
    const container = document.getElementById('pm-time-logs-list');
    if (!logs || logs.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #9ca3af; padding: 20px; font-size: 13px;">No time logs yet</p>';
        return;
    }
    
    // Use DocumentFragment for better performance
    const fragment = document.createDocumentFragment();
    const tempDiv = document.createElement('div');
    
    logs.forEach(log => {
        const user = log.user_name || 'Unknown';
        const date = new Date(log.log_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
        const formattedNotes = log.notes ? pmMakeLinkClickable(pmFormatText(log.notes)) : '';
        
        tempDiv.innerHTML = `
            <div class="pm-time-log-item">
                <div class="time-log-info">
                    <p><strong>${user}</strong> logged <span class="time-log-hours">${log.hours}h</span></p>
                    <small>${date}${formattedNotes ? ' - <span class="time-log-notes">' + formattedNotes + '</span>' : ''}</small>
                </div>
                <button class="bntm-btn-small bntm-btn-danger bntm-delete-log" data-log-id="${log.id}" data-task-id="${taskId}">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        fragment.appendChild(tempDiv.firstElementChild);
    });
    
    container.innerHTML = '';
    container.appendChild(fragment);
}
function pmDeleteTimeLog(logId, taskId) {
    if (!confirm('Delete this time log?')) return;
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_time_log');
    formData.append('log_id', logId);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            pmEditTask(taskId);
        } else {
            alert(json.data.message || 'Failed to delete time log');
        }
    });
}

// Event delegation for time log deletions
document.addEventListener('click', function(e) {
    if (e.target.closest('.bntm-delete-log')) {
        const btn = e.target.closest('.bntm-delete-log');
        pmDeleteTimeLog(btn.dataset.logId, btn.dataset.taskId);
    }
}, true);
// Comment functions
function pmLoadComments(taskId, comments) {
    const container = document.getElementById('pm-comments-list');
    if (!comments || comments.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #9ca3af; padding: 20px; font-size: 13px;">No comments yet</p>';
        return;
    }
    
    // Use DocumentFragment for better performance
    const fragment = document.createDocumentFragment();
    const tempDiv = document.createElement('div');
    
    comments.forEach(comment => {
        const user = comment.user_name || 'Unknown';
        const timeAgo = pmTimeAgo(comment.created_at);
        
        // Safe text formatting with minimal operations
        const div = document.createElement('div');
        div.textContent = comment.comment;
        let safeText = div.innerHTML
            .replace(/\n/g, '<br>')
            .replace(/(https?:\/\/[^\s]+|www\.[^\s]+)/g, (url) => {
                const fullUrl = url.startsWith('www.') ? 'http://' + url : url;
                return `<a href="${fullUrl}" target="_blank" class="comment-link" style="color: #3b82f6; text-decoration: underline;">${url}</a>`;
            });
        
        tempDiv.innerHTML = `
            <div class="pm-comment-item">
                <div class="comment-header">
                    <span class="comment-author">${user}</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="comment-time">${timeAgo}</span>
                        <button class="bntm-btn-small bntm-btn-danger bntm-delete-comment" data-comment-id="${comment.id}" data-task-id="${taskId}" style="padding: 4px 6px;">
                            <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="comment-text" style="white-space: pre-line; word-break: break-word;">${safeText}</p>
            </div>
        `;
        fragment.appendChild(tempDiv.firstElementChild);
    });
    
    container.innerHTML = '';
    container.appendChild(fragment);
}

function pmAddComment() {
    const taskId = document.getElementById('pm-task-id').value;
    const comment = document.getElementById('pm-new-comment').value.trim();
    
    if (!comment) {
        alert('Please enter a comment');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'pm_add_comment');
    formData.append('task_id', taskId);
    formData.append('comment', comment);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            document.getElementById('pm-new-comment').value = '';
            pmEditTask(taskId);
        } else {
            alert(json.data.message || 'Failed to add comment');
        }
    });
}

// Google Calendar Export
function pmExportToGoogleCalendar() {
    const taskId = document.getElementById('pm-task-id').value;
    
    if (!taskId) {
        alert('Please save the task first');
        return;
    }
    
    const btn = document.getElementById('pm-export-google-calendar-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Exporting...';
    
    const formData = new FormData();
    formData.append('action', 'pm_export_to_google_calendar');
    formData.append('task_id', taskId);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        
        if (json.success) {
            alert('✓ Task exported to Google Calendar successfully!');
        } else {
            if (json.data.auth_required) {
                if (confirm('Google Calendar not connected. Would you like to connect now?')) {
                    pmOpenGoogleCalendarSettings();
                }
            } else {
                alert('Error: ' + (json.data.message || 'Failed to export'));
            }
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error: ' + err.message);
    });
}

function pmOpenGoogleCalendarSettings() {
    document.getElementById('pm-google-calendar-modal').style.display = 'flex';
}

function pmStartGoogleCalendarAuth() {
    // Save empty client_id first to initialize settings, then request auth URL
    const formData = new FormData();
    formData.append('action', 'pm_get_google_calendar_auth_url');
    formData.append('nonce', '<?php echo wp_create_nonce("pm_nonce"); ?>');
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right: 8px;"><circle cx="12" cy="12" r="10"/></svg> Connecting...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success && json.data.auth_url) {
            // Redirect to Google OAuth
            window.location.href = json.data.auth_url;
        } else {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Error: ' + (json.data.message || 'Failed to get auth URL'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        alert('Error connecting to Google: ' + err.message);
    });
}

function pmConnectGoogleCalendar() {
    const clientId = document.getElementById('pm-google-client-id').value.trim();
    
    if (!clientId) {
        alert('Please enter your Google OAuth Client ID');
        return;
    }
    
    // Save Client ID first
    const formData = new FormData();
    formData.append('action', 'pm_save_google_calendar_settings');
    formData.append('client_id', clientId);
    formData.append('nonce', '<?php echo wp_create_nonce("pm_nonce"); ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            // Now get the auth URL
            const authFormData = new FormData();
            authFormData.append('action', 'pm_get_google_calendar_auth_url');
            authFormData.append('nonce', '<?php echo wp_create_nonce("pm_nonce"); ?>');
            
            return fetch(ajaxurl, {method: 'POST', body: authFormData}).then(r => r.json());
        } else {
            alert('Failed to save Client ID');
            throw new Error('Save failed');
        }
    })
    .then(json => {
        if (json.success) {
            window.location.href = json.data.auth_url;
        } else {
            alert('Error: ' + (json.data.message || 'Failed to get auth URL'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error connecting Google Calendar');
    });
}

function pmDeleteComment(commentId, taskId) {
    if (!confirm('Delete this comment?')) return;
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_comment');
    formData.append('comment_id', commentId);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            pmEditTask(taskId);
        } else {
            alert(json.data.message || 'Failed to delete comment');
        }
    });
}

// Event delegation for comment deletions
document.addEventListener('click', function(e) {
    if (e.target.closest('.bntm-delete-comment')) {
        const btn = e.target.closest('.bntm-delete-comment');
        pmDeleteComment(btn.dataset.commentId, btn.dataset.taskId);
    }
}, true);

function pmTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };
    
    for (let [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return interval + ' ' + unit + (interval > 1 ? 's' : '') + ' ago';
        }
    }
    return 'just now';
}

// Task status update
function pmUpdateTaskStatus(taskId, statusBtn) {
    const menu = statusBtn.nextElementSibling;
    
    // Close other open menus
    document.querySelectorAll('.task-status-menu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });
    
    menu.classList.toggle('show');
}

function pmSetTaskStatus(taskId, status, color, statusName) {
    const formData = new FormData();
     const taskRow = document.querySelector(`.pm-task-row[data-task-id="${taskId}"]`);
         // Close the menu
        const menu = taskRow.querySelector('.task-status-menu');
        if (menu) {
            menu.classList.remove('show');
        }
        
        // Update data attribute
        taskRow.setAttribute('data-task-status', status);
    formData.append('action', 'pm_update_task_status');
    formData.append('task_id', taskId);
    formData.append('status', status);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
           
        if (json.success) {
            if (taskRow) {
                
                const statusBtn = taskRow.querySelector('.task-status-btn');
                if (statusBtn) {
                    statusBtn.style.background = color;
                    statusBtn.querySelector('span').textContent = statusName;
                }
                
               
                
                // Move task to bottom if completed or closed
                if (status === 'completed' || status === 'closed') {
                    const tasksList = taskRow.closest('.pm-tasks-list');
                    tasksList.appendChild(taskRow);
                } else {
                    // Move to top if changing from completed/closed to active
                   /* const tasksList = taskRow.closest('.pm-tasks-list');
                    const firstActiveTask = Array.from(tasksList.querySelectorAll('.pm-task-row'))
                        .find(row => !['completed', 'closed'].includes(row.getAttribute('data-task-status')));
                    
                    if (firstActiveTask) {
                        
                        tasksList.insertBefore(taskRow, firstActiveTask);
                    } else {
                        tasksList.insertBefore(taskRow, tasksList.firstChild);
                    }*/
                }
                
                // Reapply all filters
                applyAllFilters();
                
                // Update milestone progress
                updateMilestoneProgress(taskRow);
            }
        }
    });
}

function updateMilestoneProgress(taskRow) {
    const milestoneSection = taskRow.closest('.pm-milestone-section');
    if (!milestoneSection) return;
    
    const allTasks = milestoneSection.querySelectorAll('.pm-task-row');
    const completedTasks = Array.from(allTasks).filter(row => {
        const status = row.getAttribute('data-task-status');
        return status === 'completed' || status === 'closed';
    });
    
    const progress = allTasks.length > 0 ? Math.round((completedTasks.length / allTasks.length) * 100) : 0;
    
    const progressFill = milestoneSection.querySelector('.progress-fill');
    const progressText = milestoneSection.querySelector('.milestone-progress-mini span');
    
    if (progressFill) {
        progressFill.style.width = progress + '%';
    }
    if (progressText) {
        progressText.textContent = progress + '%';
    }
}

// Close status menus when clicking outside
document.addEventListener('click', function(e) {
    // Handle status button click with event delegation
    if (e.target.closest('.pm-task-status-btn')) {
        const btn = e.target.closest('.pm-task-status-btn');
        const menu = btn.nextElementSibling;
        
        // Close other open menus
        document.querySelectorAll('.task-status-menu').forEach(m => {
            if (m !== menu) m.classList.remove('show');
        });
        
        if (menu.classList.contains('show')) {
            menu.classList.remove('show');
        } else {
            // Position the menu below the button
            const rect = btn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 4) + 'px';
            menu.style.left = rect.left + 'px';
            menu.style.width = rect.width + 'px';
            menu.classList.add('show');
        }
        return;
    }
    
    // Handle status option click with event delegation
    if (e.target.closest('.pm-task-status-option')) {
        const option = e.target.closest('.pm-task-status-option');
        const taskId = option.dataset.taskId;
        const status = option.dataset.status;
        const color = option.dataset.color;
        const statusName = option.textContent.trim();
        
        pmSetTaskStatus(taskId, status, color, statusName);
        return;
    }
    
    // Handle edit task button with event delegation
    if (e.target.closest('.pm-edit-task-btn')) {
        const btn = e.target.closest('.pm-edit-task-btn');
        pmEditTask(btn.dataset.taskId);
        return;
    }
    
    // Handle delete task button with event delegation
    if (e.target.closest('.pm-delete-task-btn')) {
        const btn = e.target.closest('.pm-delete-task-btn');
        pmDeleteTask(btn.dataset.taskId, btn.dataset.taskTitle);
        return;
    }
    
    // Close menus if clicking outside status dropdown
    if (!e.target.closest('.task-status-dropdown')) {
        document.querySelectorAll('.task-status-menu').forEach(m => {
            m.classList.remove('show');
        });
    }
});

// Close dropdown menus when scrolling
document.addEventListener('scroll', function() {
    document.querySelectorAll('.task-status-menu.show').forEach(m => {
        m.classList.remove('show');
    });
}, true);

// Drag and drop for task reordering (optimized with event delegation)
(function() {
    let draggedTask = null;
    
    // Use event delegation instead of attaching listeners to every element
    document.addEventListener('dragstart', function(e) {
        if (e.target.closest('.pm-task-row')) {
            draggedTask = e.target.closest('.pm-task-row');
            draggedTask.classList.add('dragging');
        }
    }, true);
    
    document.addEventListener('dragend', function(e) {
        if (draggedTask) {
            draggedTask.classList.remove('dragging');
            draggedTask = null;
        }
    }, true);
    
    document.addEventListener('dragover', function(e) {
        if (draggedTask) {
            const container = e.target.closest('.sortable-milestone-tasks');
            if (container) {
                e.preventDefault();
                
                // Remove empty state message if it exists during drag
                const emptyState = container.querySelector('.pm-empty-tasks:not(.pm-dynamic-empty)');
                if (emptyState) {
                    emptyState.style.display = 'none';
                }
                
                const afterElement = getDragAfterElement(container, e.clientY);
                if (afterElement == null) {
                    container.appendChild(draggedTask);
                } else {
                    container.insertBefore(draggedTask, afterElement);
                }
            }
        }
    }, true);
    
    document.addEventListener('dragleave', function(e) {
        const container = e.target.closest('.sortable-milestone-tasks');
        if (container) {
            // Show empty state again if drag leaves and container is empty
            const emptyState = container.querySelector('.pm-empty-tasks:not(.pm-dynamic-empty)');
            if (emptyState && container.querySelectorAll('.pm-task-row').length === 0) {
                emptyState.style.display = 'block';
            }
        }
    }, true);
    
    document.addEventListener('drop', function(e) {
        const container = e.target.closest('.sortable-milestone-tasks');
        if (container && draggedTask) {
            e.preventDefault();
            
            // Remove empty state message if it exists
            const emptyState = container.querySelector('.pm-empty-tasks:not(.pm-dynamic-empty)');
            if (emptyState) {
                emptyState.remove();
            }
            
            // Update counts for all milestones
            pmUpdateMilestoneCounts();
            
            const milestoneId = container.dataset.milestoneId;
            const taskOrder = Array.from(container.querySelectorAll('.pm-task-row'))
                .map(row => row.dataset.taskId);
            
            const formData = new FormData();
            formData.append('action', 'pm_reorder_tasks');
            formData.append('milestone_id', milestoneId);
            formData.append('task_order', JSON.stringify(taskOrder));
            formData.append('nonce', '<?php echo wp_create_nonce("pm_reorder_nonce"); ?>');
            fetch(ajaxurl, {method: 'POST', body: formData});
        }
    }, true);

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.pm-task-row:not(.dragging)')]
        .filter(el => el.style.display !== 'none'); // Only consider visible tasks
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
})();

// Milestone drag and drop
(function() {
    let draggedMilestone = null;
    let placeholder = null;
    
    function initMilestoneDragDrop() {
        // Add drag handles to milestones
        document.querySelectorAll('.pm-milestone-section').forEach(section => {
            // Check if drag handle already exists
            if (section.querySelector('.pm-milestone-drag-handle')) return;
            
            const header = section.querySelector('.pm-milestone-header');
            if (!header) return;
            
            const milestoneInfo = header.querySelector('.milestone-info');
            if (!milestoneInfo) return;
            
            const toggleBtn = milestoneInfo.querySelector('.milestone-toggle-btn');
            if (!toggleBtn) return;
            
            // Create drag handle
            const dragHandle = document.createElement('span');
            dragHandle.className = 'pm-milestone-drag-handle';
            dragHandle.innerHTML = '⋮';
            dragHandle.setAttribute('draggable', 'true');
            dragHandle.title = 'Drag to reorder';
            
            // Insert drag handle before the toggle button
            milestoneInfo.insertBefore(dragHandle, toggleBtn);
            
            // Prevent dragging from interfering with other events
            dragHandle.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });
            
            // Drag events on the handle
            dragHandle.addEventListener('dragstart', function(e) {
                draggedMilestone = section;
                
                // Create placeholder
                placeholder = document.createElement('div');
                placeholder.style.height = section.offsetHeight + 'px';
                placeholder.style.background = '#e5e7eb';
                placeholder.style.borderRadius = '12px';
                placeholder.style.margin = '20px 0';
                placeholder.style.opacity = '0.5';
                
                setTimeout(() => {
                    section.classList.add('dragging');
                    section.parentNode.insertBefore(placeholder, section.nextSibling);
                }, 0);
                
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', ''); // Required for Firefox
            });
            
            dragHandle.addEventListener('dragend', function() {
                section.classList.remove('dragging');
                if (placeholder && placeholder.parentNode) {
                    placeholder.remove();
                }
                placeholder = null;
                
                if (draggedMilestone) {
                    saveMilestoneOrder();
                }
                draggedMilestone = null;
            });
        });
        
        // Set up drop zones on milestone sections
        document.querySelectorAll('.pm-milestone-section').forEach(section => {
            section.addEventListener('dragover', function(e) {
                e.preventDefault();
                
                if (!draggedMilestone || draggedMilestone === this || !placeholder) return;
                
                e.dataTransfer.dropEffect = 'move';
                
                const rect = this.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                const mouseY = e.clientY;
                
                // Move placeholder based on position
                if (mouseY < midpoint) {
                    // Insert placeholder before this section
                    if (this.previousSibling !== placeholder) {
                        this.parentNode.insertBefore(placeholder, this);
                    }
                } else {
                    // Insert placeholder after this section
                    if (this.nextSibling !== placeholder) {
                        if (this.nextSibling) {
                            this.parentNode.insertBefore(placeholder, this.nextSibling);
                        } else {
                            this.parentNode.appendChild(placeholder);
                        }
                    }
                }
            });
            
            section.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        // Also handle dragover on the container
        const container = document.querySelector('.pm-tasks-container');
        if (container) {
            container.addEventListener('dragover', function(e) {
                if (!draggedMilestone || !placeholder) return;
                e.preventDefault();
            });
            
            container.addEventListener('drop', function(e) {
                e.preventDefault();
                
                if (draggedMilestone && placeholder && placeholder.parentNode) {
                    // Move the dragged milestone to where the placeholder is
                    placeholder.parentNode.insertBefore(draggedMilestone, placeholder);
                }
            });
        }
    }
    
    function saveMilestoneOrder() {
        const milestones = document.querySelectorAll('.pm-milestone-section');
        const milestoneOrder = [];
        
        milestones.forEach(section => {
            const tasksList = section.querySelector('.sortable-milestone-tasks');
            if (tasksList) {
                const milestoneId = tasksList.getAttribute('data-milestone-id');
                if (milestoneId && milestoneId !== '0') {
                    milestoneOrder.push(parseInt(milestoneId));
                }
            }
        });
        
        if (milestoneOrder.length > 0) {
            const formData = new FormData();
            formData.append('action', 'pm_reorder_milestones');
            formData.append('milestone_order', JSON.stringify(milestoneOrder));
            formData.append('nonce', '<?php echo wp_create_nonce("pm_reorder_nonce"); ?>');
            
            fetch(ajaxurl, {
                method: 'POST', 
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    console.log('Milestone order saved successfully');
                } else {
                    console.error('Failed to save milestone order:', json);
                }
            })
            .catch(err => {
                console.error('Error saving milestone order:', err);
            });
        }
    }
    
    // Initialize on page load
    setTimeout(initMilestoneDragDrop, 100);
})();
</script>
<?php
return ob_get_clean();
}
/**
 * Render Task Row
 */
function pm_render_task_row($task, $team_members, $custom_statuses, $nonce) {
    $assigned_user = $task->assigned_to ? get_userdata($task->assigned_to) : null;
    $is_overdue = $task->due_date && strtotime($task->due_date) < time() && $task->status !== 'completed';
    
    // Get status color
    $status_color = '#6b7280';
    $status_display = ucfirst(str_replace('_', ' ', $task->status));
    
    if (!empty($custom_statuses)) {
        foreach ($custom_statuses as $status) {
            if ($status->status_name === $task->status) {
                $status_color = $status->status_color;
                $status_display = $status->status_name;
                break;
            }
        }
    } else {
        // Default colors
        $default_colors = [
            'To Do' => '#6b7280',
            'In Progress' => '#3b82f6',
            'Review' => '#f59e0b',
            'completed' => '#10b981',
            'closed' => '#000'
            
        ];
        $status_color = $default_colors[$task->status] ?? '#6b7280';
    }
    
    ob_start();
    ?>
       
    <div class="pm-task-row" 
         draggable="true" 
         data-task-id="<?php echo $task->id; ?>" 
         data-task-status="<?php echo esc_attr($task->status); ?>"
         data-assigned-to="<?php echo $task->assigned_to ? $task->assigned_to : '0'; ?>">
        <div class="task-drag-handle">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
            </svg>
        </div>
        
        <div class="task-row-content">
            <div class="task-row-title">
                <div class="task-row-priority priority-<?php echo $task->priority; ?>"></div>
                <h4><?php echo esc_html($task->title); ?></h4>
            </div>
            <div class="task-row-meta">
                <?php if ($assigned_user): ?>
                    <span class="task-row-assignee">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo esc_html($assigned_user->display_name); ?>
                    </span>
                <?php endif; ?>
                <?php if ($task->due_date): ?>
                    <span class="task-row-due <?php echo $is_overdue ? 'overdue' : ''; ?>">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <?php echo date('M d, Y', strtotime($task->due_date)); ?>
                    </span>
                <?php endif; ?>
                <?php if ($task->tags): ?>
                    <div class="task-row-tags">
                        <?php foreach (explode(',', $task->tags) as $tag): ?>
                            <span class="task-tag"><?php echo esc_html(trim($tag)); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="task-row-status">
            <div class="task-status-dropdown">
                <button type="button" 
                        class="task-status-btn pm-task-status-btn" 
                        style="background: <?php echo esc_attr($status_color); ?>"
                        data-task-id="<?php echo $task->id; ?>">
                    <span><?php echo esc_html($status_display); ?></span>
                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div class="task-status-menu">
                    <?php if (!empty($custom_statuses)): ?>
                        <?php foreach ($custom_statuses as $status): ?>
                            <div class="task-status-option pm-task-status-option" 
                                 style="background: <?php echo esc_attr($status->status_color); ?>"
                                 data-task-id="<?php echo $task->id; ?>"
                                 data-status="<?php echo esc_attr($status->status_name); ?>"
                                 data-color="<?php echo esc_attr($status->status_color); ?>">
                                <?php echo esc_html($status->status_name); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="task-status-option pm-task-status-option" style="background: #6b7280"
                             data-task-id="<?php echo $task->id; ?>"
                             data-status="To Do"
                             data-color="#6b7280">
                            To Do
                        </div>
                        <div class="task-status-option pm-task-status-option" style="background: #3b82f6"
                             data-task-id="<?php echo $task->id; ?>"
                             data-status="In Progress"
                             data-color="#3b82f6">
                            In Progress
                        </div>
                        <div class="task-status-option pm-task-status-option" style="background: #f59e0b"
                             data-task-id="<?php echo $task->id; ?>"
                             data-status="Review"
                             data-color="#f59e0b">
                            Review
                        </div>
                        <div class="task-status-option pm-task-status-option" style="background: #10b981"
                             data-task-id="<?php echo $task->id; ?>"
                             data-status="Completed"
                             data-color="#10b981">
                            Completed
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="task-row-actions">
            <button class="bntm-btn-small bntm-btn-secondary pm-edit-task-btn" data-task-id="<?php echo $task->id; ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </button>
            <button class="bntm-btn-small bntm-btn-danger pm-delete-task-btn" data-task-id="<?php echo $task->id; ?>" data-task-title="<?php echo esc_attr($task->title); ?>">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
/**
 * Project Kanban Subtab
 */
function pm_project_kanban_subtab($project, $business_id, $nonce) {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $statuses_table = $wpdb->prefix . 'pm_project_statuses';
    
    // Get custom statuses for this project
    $custom_statuses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$statuses_table} WHERE project_id = %d ORDER BY sort_order ASC",
        $project->id
    ));
    
    // If no custom statuses, use default
    if (empty($custom_statuses)) {
        $custom_statuses = [
            (object)['id' => 0, 'status_name' => 'To Do', 'status_color' => '#6b7280'],
            (object)['id' => 0, 'status_name' => 'In Progress', 'status_color' => '#3b82f6'],
            (object)['id' => 0, 'status_name' => 'Review', 'status_color' => '#f59e0b'],
            (object)['id' => 0, 'status_name' => 'Completed', 'status_color' => '#10b981'],
            (object)['id' => 0, 'status_name' => 'Closed', 'status_color' => '#000']
        ];
    }
    
    ob_start();
    ?>
    <div class="pm-kanban-container">
        <div class="pm-kanban-header">
            <h2>Kanban Board</h2>
            <button class="bntm-btn-primary" onclick="pmOpenTaskModal()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Task
            </button>
        </div>
        
        <div class="pm-kanban-board">
            <?php foreach ($custom_statuses as $status): 
                $status_tasks = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$tasks_table} WHERE project_id = %d AND status = %s ORDER BY sort_order ASC, created_at DESC",
                    $project->id, $status->status_name
                ));
            ?>
                <div class="pm-kanban-column" data-status="<?php echo esc_attr($status->status_name); ?>">
                    <div class="kanban-column-header" style="background: <?php echo esc_attr($status->status_color); ?>">
                        <h3><?php echo esc_html($status->status_name); ?></h3>
                        <span class="kanban-count"><?php echo count($status_tasks); ?></span>
                    </div>
                    <div class="kanban-column-content sortable-kanban-column" data-status="<?php echo esc_attr($status->status_name); ?>">
                        <?php if (empty($status_tasks)): ?>
                            <div class="kanban-empty-state">No tasks</div>
                        <?php else: ?>
                            <?php foreach ($status_tasks as $task): 
                                $assigned_user = $task->assigned_to ? get_userdata($task->assigned_to) : null;
                                $is_overdue = $task->due_date && strtotime($task->due_date) < time() && $task->status !== 'completed';
                            ?>
                                <div class="pm-kanban-card" data-task-id="<?php echo $task->id; ?>" draggable="true" onclick="pmEditTask(<?php echo $task->id; ?>)">
                                    <div class="kanban-card-header">
                                        <span class="kanban-card-priority priority-<?php echo $task->priority; ?>"></span>
                                        <h4><?php echo esc_html($task->title); ?></h4>
                                    </div>
                                    <?php if ($task->description): ?>
                                        <p class="kanban-card-desc"><?php echo esc_html(wp_trim_words($task->description, 15)); ?></p>
                                    <?php endif; ?>
                                    <div class="kanban-card-meta">
                                        <?php if ($assigned_user): ?>
                                            <div class="kanban-card-avatar" title="<?php echo esc_attr($assigned_user->display_name); ?>">
                                                <?php echo strtoupper(substr($assigned_user->display_name, 0, 2)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($task->due_date): ?>
                                            <span class="kanban-card-due <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                                <?php echo date('M d', strtotime($task->due_date)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($task->estimated_hours > 0): ?>
                                            <span class="kanban-card-hours">
                                                <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <?php echo $task->estimated_hours; ?>h
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
    .pm-kanban-container {
        max-width: 100%;
        overflow-x: auto;
    }
    
    .pm-kanban-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .pm-kanban-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    
    .pm-kanban-board {
        display: flex;
        gap: 20px;
        min-height: 600px;
        padding-bottom: 20px;
    }
    
    .pm-kanban-column {
        flex: 0 0 320px;
        background: #f9fafb;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        max-height: 80vh;
    }
    
    .kanban-column-header {
        padding: 16px 20px;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
    }
    
    .kanban-column-header h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 600;
    }
    
    .kanban-count {
        background: rgba(255,255,255,0.3);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
    }
    
    .kanban-column-content {
        flex: 1;
        padding: 16px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .kanban-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #9ca3af;
        font-size: 14px;
    }
    
    .pm-kanban-card {
        background: white;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .pm-kanban-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }
    
    .pm-kanban-card.dragging {
        opacity: 0.5;
    }
    
    .kanban-card-header {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    </style>
    
    .kanban-card-priority {
        width: 4px;
        height: 20px;
        border-radius: 2px;
        flex-shrink: 0;
    }
    
    .kanban-card-priority.priority-low {
        background: #3b82f6;
    }
}

function applyAllFilters() {
    // Get current user ID from PHP
    const currentUserId = '<?php echo get_current_user_id(); ?>';
    
    .kanban-card-priority.priority-medium {
        background: #f59e0b;
    }
    
    .kanban-card-priority.priority-high {
        background: #ef4444;
    }
    
    .kanban-card-header h4 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        flex: 1;
        line-height: 1.4;
    }
    
    .kanban-card-desc {
        margin: 0 0 12px 12px;
        font-size: 13px;
        color: #6b7280;
        line-height: 1.5;
    }
    
    .kanban-card-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 12px;
        font-size: 12px;
    }
    return 'just now';
}

// Task status update
function pmUpdateTaskStatus(taskId, statusBtn) {
    const menu = statusBtn.nextElementSibling;
    
    // Close other open menus
    document.querySelectorAll('.task-status-menu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });
    
    .kanban-card-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 11px;
    }
    
    .kanban-card-due, .kanban-card-hours {
        display: flex;
        align-items: center;
        gap: 4px;
        color: #6b7280;
    }
    
    .kanban-card-due.overdue {
        color: #ef4444;
        font-weight: 600;
    }
    </style>
    
    <script>
    (function() {
        let draggedCard = null;
        
        // Drag and drop
        document.querySelectorAll('.pm-kanban-card').forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedCard = this;
                this.classList.add('dragging');
                e.stopPropagation();
            });
            
            card.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                draggedCard = null;
            });
        });
        
        document.querySelectorAll('.sortable-kanban-column').forEach(column => {
            column.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(column, e.clientY);
                if (afterElement == null) {
                    column.appendChild(draggedCard);
                } else {
                    column.insertBefore(draggedCard, afterElement);
                }
            });
            
            column.addEventListener('drop', function(e) {
                e.preventDefault();
                
                // Remove empty state message if it exists
                const emptyState = this.querySelector('.kanban-empty-state');
                if (emptyState) {
                    emptyState.remove();
                }
                
                const newStatus = this.dataset.status;
                const taskId = draggedCard.dataset.taskId;
                
                // Update task status
                const formData = new FormData();
                formData.append('action', 'pm_update_task_status');
                formData.append('task_id', taskId);
                formData.append('status', newStatus);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        // Update the counts
                        document.querySelectorAll('.kanban-column-header').forEach(header => {
                            const column = header.nextElementSibling;
                            const count = column.querySelectorAll('.pm-kanban-card').length;
                            header.querySelector('.kanban-count').textContent = count;
                        });
                    }
                });
            });
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.pm-kanban-card:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Project Team Subtab
 */
function pm_project_team_subtab($project, $business_id, $nonce) {
    global $wpdb;
    $team_table = $wpdb->prefix . 'pm_team_members';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    
    // Get team members
    $team_members = $wpdb->get_results($wpdb->prepare(
        "SELECT tm.*, u.display_name, u.user_email 
         FROM {$team_table} tm
         INNER JOIN {$wpdb->users} u ON tm.user_id = u.ID
         WHERE tm.project_id = %d
         ORDER BY tm.joined_at DESC",
        $project->id
    ));
    
    // Get all users for adding members
    $all_users = get_users(['fields' => ['ID', 'display_name', 'user_email']]);
    $member_ids = array_column($team_members, 'user_id');
    
    ob_start();
    ?>
    <div class="pm-team-container">
        <div class="pm-team-header">
            <h2>Team Members</h2>
            <button class="bntm-btn-primary" onclick="pmOpenAddMemberModal()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Member
            </button>
        </div>
        
        <?php if (empty($team_members)): ?>
            <div class="pm-empty-state-large">
                <svg width="80" height="80" fill="none" stroke="#d1d5db" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <h3>No Team Members Yet</h3>
                <p>Add team members to collaborate on this project</p>
            </div>
        <?php else: ?>
            <div class="pm-team-grid">
                <?php foreach ($team_members as $member): 
                    // Get member performance
                    $member_tasks = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$tasks_table} WHERE project_id = %d AND assigned_to = %d",
                        $project->id, $member->user_id
                    ));
                    
                    $completed_tasks = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$tasks_table} WHERE project_id = %d AND assigned_to = %d AND status IN ('completed', 'closed')",
                        $project->id, $member->user_id
                    ));
                    
                    $hours_logged = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(tl.hours) FROM {$time_logs_table} tl
                         INNER JOIN {$tasks_table} t ON tl.task_id = t.id
                         WHERE t.project_id = %d AND tl.user_id = %d",
                        $project->id, $member->user_id
                    ));
                    $hours_logged = $hours_logged ? floatval($hours_logged) : 0;
                    
                    $completion_rate = $member_tasks > 0 ? round(($completed_tasks / $member_tasks) * 100) : 0;
                ?>
                    <div class="pm-member-card">
                        <div class="member-card-header">
                            <div class="member-avatar">
                                <?php echo strtoupper(substr($member->display_name, 0, 2)); ?>
                            </div>
                            <div class="member-info">
                                <h4><?php echo esc_html($member->display_name); ?></h4>
                                <p><?php echo esc_html($member->user_email); ?></p>
                                <span class="member-role"><?php echo ucfirst($member->role); ?></span>
                            </div>
                            <button class="bntm-btn-small bntm-btn-danger" 
                                    onclick="pmRemoveTeamMember(<?php echo $member->id; ?>, '<?php echo esc_js($member->display_name); ?>')">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="member-stats">
                            <div class="member-stat">
                                <span class="stat-value"><?php echo $member_tasks; ?></span>
                                <span class="stat-label">Total Tasks</span>
                            </div>
                            <div class="member-stat">
                                <span class="stat-value"><?php echo $completed_tasks; ?></span>
                                <span class="stat-label">Completed</span>
                            </div>
                            <div class="member-stat">
                                <span class="stat-value"><?php echo number_format($hours_logged, 1); ?>h</span>
                                <span class="stat-label">Hours Logged</span>
                            </div>
                        </div>
                        
                        <div class="member-performance">
                            <div class="performance-label">
                                <span>Completion Rate</span>
                                <span class="performance-percent"><?php echo $completion_rate; ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%; background: <?php echo esc_attr($project->color); ?>"></div>
                            </div>
                        </div>
                        
                        <div class="member-joined">
                            Joined <?php echo human_time_diff(strtotime($member->joined_at), current_time('timestamp')); ?> ago
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Member Modal -->
    <div id="pm-add-member-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content" style="max-width: 500px;">
            <div class="bntm-modal-header">
                <h3>Add Team Member</h3>
                <button class="bntm-modal-close" onclick="pmCloseAddMemberModal()">&times;</button>
            </div>
            <form id="pm-add-member-form" class="bntm-form">
                <input type="hidden" name="project_id" value="<?php echo $project->id; ?>">
                
                <div class="bntm-form-group">
                    <label>Select User *</label>
                    <select name="user_id" id="pm-member-user" required>
                        <option value="">Choose a user</option>
                        <?php foreach ($all_users as $user): ?>
                            <?php if (!in_array($user->ID, $member_ids)): ?>
                                <option value="<?php echo $user->ID; ?>">
                                    <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Role</label>
                    <select name="role" id="pm-member-role">
                        <option value="staff">Staff</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="bntm-modal-footer">
                    <button type="button" class="bntm-btn-secondary" onclick="pmCloseAddMemberModal()">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Add Member</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .pm-team-container {
        max-width: 1200px;
    }
    
    .pm-team-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    
    .pm-team-header h2 {
        margin: 0;
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    
    .pm-team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    
    .pm-member-card {
        background: #f9fafb;
        border-radius: 12px;
        padding: 20px;
    }
    
    .member-card-header {
        display: flex;
        gap: 16px;
        margin-bottom: 20px;
        align-items: flex-start;
    }
    
    .member-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .member-info {
        flex: 1;
        min-width: 0;
    }
    
    .member-info h4 {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: 600;
        color: #111827;
    }
    
    .member-info p {
        margin: 0 0 8px 0;
        font-size: 13px;
        color: #6b7280;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .member-role {
        display: inline-block;
        padding: 4px 10px;
        background: white;
        color: #4b5563;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .member-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 20px;
        padding: 16px;
        background: white;
        border-radius: 8px;
    }
    
    .member-stat {
        text-align: center;
    }
    
    .member-stat .stat-value {
        display: block;
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 4px;
    }
    
    .member-stat .stat-label {
        display: block;
        font-size: 12px;
        color: #6b7280;
    }
    
    .member-performance {
        margin-bottom: 16px;
    }
    
    .performance-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13px;
        color: #4b5563;
    }
    
    .performance-percent {
        font-weight: 700;
        color: #111827;
    }
    
    .member-joined {
        font-size: 12px;
        color: #9ca3af;
        text-align: center;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
    }
    </style>
    
    <script>
    function pmOpenAddMemberModal() {
        document.getElementById('pm-add-member-modal').style.display = 'flex';
        document.getElementById('pm-add-member-form').reset();
    }
    
    function pmCloseAddMemberModal() {
        document.getElementById('pm-add-member-modal').style.display = 'none';
    }
    
    function pmRemoveTeamMember(memberId, memberName) {
        if (!confirm('Remove ' + memberName + ' from this project?')) return;
        
        const formData = new FormData();
        formData.append('action', 'pm_remove_team_member');
        formData.append('member_id', memberId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            } else {
                alert(json.data.message || 'Failed to remove member');
            }
        });
    }
    
    document.getElementById('pm-add-member-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'pm_add_team_member');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            } else {
                alert(json.data.message || 'Failed to add member');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Member';
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Project Settings Subtab
 */
function pm_project_settings_subtab($project, $business_id, $nonce) {
    global $wpdb;
    $statuses_table = $wpdb->prefix . 'pm_project_statuses';
    
    // Get custom statuses
    $custom_statuses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$statuses_table} WHERE project_id = %d ORDER BY sort_order ASC",
        $project->id
    ));
    
    ob_start();
    ?>
    <div class="pm-settings-container">
        <h2>Project Settings</h2>
        
        <!-- Project Information -->
        <div class="pm-settings-section">
            <h3>Project Information</h3>
            <form id="pm-update-project-form" class="bntm-form">
                <input type="hidden" name="project_id" value="<?php echo $project->id; ?>">
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group" style="flex: 1;">
                        <label>Project Name *</label>
                        <input type="text" name="name" value="<?php echo esc_attr($project->name); ?>" required>
                    </div>
                    <div class="bntm-form-group" style="width: 120px;">
                        <label>Color</label>
                        <input type="color" name="color" value="<?php echo esc_attr($project->color); ?>">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"><?php echo esc_textarea($project->description); ?></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
<label>Client Name</label>
<input type="text" name="client_name" value="<?php echo esc_attr($project->client_name); ?>">
</div>
<div class="bntm-form-group">
<label>Status</label>
<select name="status">
<option value="planning" <?php selected($project->status, 'planning'); ?>>Planning</option>
<option value="in_progress" <?php selected($project->status, 'In Progress'); ?>>In Progress</option>
<option value="on_hold" <?php selected($project->status, 'on_hold'); ?>>On Hold</option>
<option value="completed" <?php selected($project->status, 'completed'); ?>>Completed</option>
<option value="completed" <?php selected($project->status, 'closed'); ?>>Closed</option>
<option value="cancelled" <?php selected($project->status, 'cancelled'); ?>>Cancelled</option>
</select>
</div>
</div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo esc_attr($project->start_date); ?>">
                </div>
                <div class="bntm-form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?php echo esc_attr($project->due_date); ?>">
                </div>
            </div>
            
            <div class="bntm-form-group">
                <label>Budget (₱)</label>
                <input type="number" name="budget" step="0.01" min="0" value="<?php echo esc_attr($project->budget); ?>">
            </div>
            
            <button type="submit" class="bntm-btn-primary">Update Project</button>
        </form>
    </div>
    
    <!-- Task Statuses -->
    <div class="pm-settings-section">
        <div class="settings-section-header">
            <h3>Task Statuses</h3>
            <button class="bntm-btn-secondary" onclick="pmOpenStatusModal()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Status
            </button>
        </div>
        <p class="settings-description">Customize task statuses for this project. Drag to reorder.</p>
        
        <?php if (empty($custom_statuses)): ?>
            <div class="pm-empty-statuses">
                <p>No custom statuses. Using default statuses (To Do, In Progress, Review, Completed).</p>
                <button class="bntm-btn-primary" onclick="pmOpenStatusModal()">Create First Status</button>
            </div>
        <?php else: ?>
            <div class="pm-statuses-list sortable-statuses">
                <?php foreach ($custom_statuses as $status): ?>
                    <div class="pm-status-item" data-status-id="<?php echo $status->id; ?>" draggable="true">
                        <div class="status-drag-handle">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                            </svg>
                        </div>
                        <div class="status-color-pReview" style="background: <?php echo esc_attr($status->status_color); ?>"></div>
                        <div class="status-info">
                            <strong><?php echo esc_html($status->status_name); ?></strong>
                        </div>
                        <div class="status-actions">
                            <button class="bntm-btn-small bntm-btn-secondary" 
                                    onclick="pmEditStatus(<?php echo htmlspecialchars(json_encode($status)); ?>)">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <button class="bntm-btn-small bntm-btn-danger" 
                                    onclick="pmDeleteStatus(<?php echo $status->id; ?>, '<?php echo esc_js($status->status_name); ?>')">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Danger Zone -->
    <div class="pm-settings-section danger-zone">
        <h3>Danger Zone</h3>
        <div class="danger-actions">
            <div>
                <strong>Delete this project</strong>
                <p>Once you delete a project, there is no going back. All tasks, milestones, and data will be permanently deleted.</p>
            </div>
            <button class="bntm-btn-danger" onclick="pmDeleteProjectFromSettings()">Delete Project</button>
        </div>
    </div>
</div>

<!-- Status Modal -->
<div id="pm-status-modal" class="bntm-modal" style="display: none;">
    <div class="bntm-modal-content" style="max-width: 500px;">
        <div class="bntm-modal-header">
            <h3 id="pm-status-modal-title">Create Status</h3>
            <button class="bntm-modal-close" onclick="pmCloseStatusModal()">&times;</button>
        </div>
        <form id="pm-status-form" class="bntm-form">
            <input type="hidden" name="status_id" id="pm-status-id">
            <input type="hidden" name="project_id" value="<?php echo $project->id; ?>">
            
            <div class="bntm-form-row">
                <div class="bntm-form-group" style="flex: 1;">
                    <label>Status Name *</label>
                    <input type="text" name="status_name" id="pm-status-name" required>
                </div>
                <div class="bntm-form-group" style="width: 120px;">
                    <label>Color</label>
                    <input type="color" name="status_color" id="pm-status-color" value="#3b82f6">
                </div>
            </div>
            
            <div class="bntm-modal-footer">
                <button type="button" class="bntm-btn-secondary" onclick="pmCloseStatusModal()">Cancel</button>
                <button type="submit" class="bntm-btn-primary">
                    <span id="pm-status-submit-text">Create Status</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.pm-settings-container {
    max-width: 800px;
}

.pm-settings-container > h2 {
    margin: 0 0 24px 0;
    font-size: 24px;
    font-weight: 700;
    color: #111827;
}

.pm-settings-section {
    background: #f9fafb;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.pm-settings-section h3 {
    margin: 0 0 16px 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.settings-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.settings-section-header h3 {
    margin: 0;
}

.settings-description {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #6b7280;
}

.pm-empty-statuses {
    text-align: center;
    padding: 40px 20px;
    background: white;
    border-radius: 8px;
}

.pm-empty-statuses p {
    margin: 0 0 16px 0;
    color: #6b7280;
}

.pm-statuses-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.pm-status-item {
    background: white;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: move;
    transition: all 0.2s;
}

.pm-status-item:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.pm-status-item.dragging {
    opacity: 0.5;
}

.status-drag-handle {
    color: #9ca3af;
}

.status-color-pReview {
    width: 24px;
    height: 24px;
    border-radius: 6px;
}

.status-info {
    flex: 1;
}

.status-info strong {
    font-size: 14px;
    color: #111827;
}

.status-actions {
    display: flex;
    gap: 6px;
}

.danger-zone {
    background: #fef2f2;
    border: 2px solid #fee2e2;
}

.danger-zone h3 {
    color: #991b1b;
}

.danger-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.danger-actions strong {
    display: block;
    margin-bottom: 4px;
    color: #111827;
}

.danger-actions p {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
}
</style>

<script>
// Update project
document.getElementById('pm-update-project-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'pm_update_project');
    formData.append('nonce', '<?php echo wp_create_nonce("pm_project_nonce"); ?>'); // Changed here
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Updating...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Failed to update project');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Update Project';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the project');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Update Project';
    });
});
// Status management
function pmOpenStatusModal() {
    document.getElementById('pm-status-modal').style.display = 'flex';
    document.getElementById('pm-status-form').reset();
    document.getElementById('pm-status-id').value = '';
    document.getElementById('pm-status-modal-title').textContent = 'Create Status';
    document.getElementById('pm-status-submit-text').textContent = 'Create Status';
    document.getElementById('pm-status-color').value = '#3b82f6';
}

function pmCloseStatusModal() {
    document.getElementById('pm-status-modal').style.display = 'none';
}

function pmEditStatus(status) {
    document.getElementById('pm-status-modal').style.display = 'flex';
    document.getElementById('pm-status-id').value = status.id;
    document.getElementById('pm-status-name').value = status.status_name;
    document.getElementById('pm-status-color').value = status.status_color;
    document.getElementById('pm-status-modal-title').textContent = 'Edit Status';
    document.getElementById('pm-status-submit-text').textContent = 'Update Status';
}

function pmDeleteStatus(id, name) {
    if (!confirm('Delete status "' + name + '"?\n\nTasks with this status will be moved to the default status.')) return;
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_status');
    formData.append('status_id', id);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Failed to delete status');
        }
    });
}

document.getElementById('pm-status-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const statusId = document.getElementById('pm-status-id').value;
    formData.append('action', statusId ? 'pm_update_status' : 'pm_create_status');
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = statusId ? 'Updating...' : 'Creating...';
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            location.reload();
        } else {
            alert(json.data.message || 'Operation failed');
            submitBtn.disabled = false;
            submitBtn.querySelector('span').textContent = statusId ? 'Update Status' : 'Create Status';
        }
    });
});

// Drag and drop for status reordering
(function() {
    const statusList = document.querySelector('.sortable-statuses');
    if (!statusList) return;
    
    let draggedStatus = null;
    
    document.querySelectorAll('.pm-status-item').forEach(item => {
        item.addEventListener('dragstart', function() {
            draggedStatus = this;
            this.classList.add('dragging');
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            draggedStatus = null;
        });
    });
    
    statusList.addEventListener('dragover', function(e) {
        e.preventDefault();
        const afterElement = getDragAfterElement(statusList, e.clientY);
        if (afterElement == null) {
            statusList.appendChild(draggedStatus);
        } else {
            statusList.insertBefore(draggedStatus, afterElement);
        }
    });
    
    statusList.addEventListener('drop', function() {
        const statusOrder = Array.from(statusList.querySelectorAll('.pm-status-item'))
            .map(item => item.dataset.statusId);
        
        const formData = new FormData();
        formData.append('action', 'pm_reorder_statuses');
        formData.append('status_order', JSON.stringify(statusOrder));
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData});
    });
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.pm-status-item:not(.dragging)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
})();

function pmDeleteProjectFromSettings() {
    if (!confirm('Are you absolutely sure you want to delete this project?\n\nThis action cannot be undone. All tasks, milestones, time logs, and comments will be permanently deleted.')) {
        return;
    }
    
    if (!confirm('Last confirmation: Delete project "<?php echo esc_js($project->name); ?>"?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'pm_delete_project');
    formData.append('project_id', <?php echo $project->id; ?>);
    formData.append('nonce', '<?php echo $nonce; ?>');
    
    fetch(ajaxurl, {method: 'POST', body: formData})
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            window.location.href = '?tab=projects';
        } else {
            alert(json.data.message || 'Failed to delete project');
        }
    });
}
</script>
<?php
return ob_get_clean();
}
/**
 * Project Management Module - AJAX Handlers
 * All AJAX endpoints for the PM module
 */

/* ---------- PROJECT AJAX HANDLERS ---------- */

/**
 * Create Project
 */
function bntm_ajax_pm_create_project() {
    check_ajax_referer('pm_project_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $current_user = wp_get_current_user();
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? 'planning'),
        'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
        'budget' => floatval($_POST['budget'] ?? 0),
        'color' => sanitize_text_field($_POST['color'] ?? '#3b82f6'),
    ];
    
    $result = $wpdb->insert($projects_table, $data);
    
    if ($result) {
        $project_id = $wpdb->insert_id;
        
        // Initialize default statuses for new project
        pm_initialize_project_statuses($project_id, $current_user->ID);
        
        // Log activity
        pm_log_activity($project_id, null, $current_user->ID, 'created_project', 'Project created');
        
        wp_send_json_success(['message' => 'Project created successfully', 'project_id' => $project_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create project']);
    }
}

/**
 * Update Project
 */
function bntm_ajax_pm_update_project() {
    check_ajax_referer('pm_project_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
}

/* ---------- TASK AJAX HANDLERS ---------- */

/**
 * Create Task
 */
function bntm_ajax_pm_create_task() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $current_user = wp_get_current_user();
    $project_id = intval($_POST['project_id']);
    
    // Verify ownership
    $project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$projects_table} WHERE id = %d ",
        $project_id, $current_user->ID
    ));
    
    if (!$project) {
        wp_send_json_error(['message' => 'Project not found']);
    }
    
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? 'planning'),
        'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
        'budget' => floatval($_POST['budget'] ?? 0),
        'color' => sanitize_text_field($_POST['color'] ?? '#3b82f6'),
    ];
    
    $result = $wpdb->update($projects_table, $data, ['id' => $project_id]);
    
    if ($result !== false) {
        pm_log_activity($project_id, null, $current_user->ID, 'updated_project', 'Project updated');
        wp_send_json_success(['message' => 'Project updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update project']);
    }
}

/**
 * Delete Project
 */
function bntm_ajax_pm_delete_project() {
    check_ajax_referer('pm_project_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $project_id = intval($_POST['project_id']);
    
    // Verify ownership
    $project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_projects WHERE id = %d ",
        $project_id, $current_user->ID
    ));
    
    if (!$project) {
        wp_send_json_error(['message' => 'Project not found']);
    }
    
    // Delete all related data
    $wpdb->delete($wpdb->prefix . 'pm_tasks', ['project_id' => $project_id]);
    $wpdb->delete($wpdb->prefix . 'pm_milestones', ['project_id' => $project_id]);
    $wpdb->delete($wpdb->prefix . 'pm_team_members', ['project_id' => $project_id]);
    $wpdb->delete($wpdb->prefix . 'pm_files', ['project_id' => $project_id]);
    $wpdb->delete($wpdb->prefix . 'pm_activity_log', ['project_id' => $project_id]);
    $wpdb->delete($wpdb->prefix . 'pm_project_statuses', ['project_id' => $project_id]);
    
    // Delete time logs and comments related to project tasks
    $task_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}pm_tasks WHERE project_id = %d",
        $project_id
    ));
    
    if (!empty($task_ids)) {
        $task_ids_str = implode(',', array_map('intval', $task_ids));
        $wpdb->query("DELETE FROM {$wpdb->prefix}pm_time_logs WHERE task_id IN ($task_ids_str)");
        $wpdb->query("DELETE FROM {$wpdb->prefix}pm_comments WHERE task_id IN ($task_ids_str)");
    }
    
    // Delete project
    $result = $wpdb->delete($wpdb->prefix . 'pm_projects', ['id' => $project_id]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Project deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete project']);
    }
}

/**
 * Update Project Progress
 */
function bntm_ajax_pm_update_project_progress() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $project_id = intval($_POST['project_id']);
    $progress = intval($_POST['progress']);
    
    $result = $wpdb->update(
        $wpdb->prefix . 'pm_projects',
        ['progress' => $progress],
        ['id' => $project_id]
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Progress updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update progress']);
    }
}

/* ---------- TASK AJAX HANDLERS ---------- */

/**
 * Create Task
 */
function bntm_ajax_pm_create_task() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $current_user = wp_get_current_user();
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'project_id' => intval($_POST['project_id']),
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? 'To Do'),
        'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
        'assigned_to' => intval($_POST['assigned_to'] ?? 0) ?: null,
        'milestone_id' => intval($_POST['milestone_id'] ?? 0) ?: null,
        'due_date' => sanitize_text_field($_POST['due_date'] ?? '') ?: null,
        'estimated_hours' => floatval($_POST['estimated_hours'] ?? 0),
        'tags' => sanitize_text_field($_POST['tags'] ?? ''),
    ];
    
    $result = $wpdb->insert($tasks_table, $data);
    
    if ($result) {
        $task_id = $wpdb->insert_id;
        pm_log_activity($data['project_id'], $task_id, $current_user->ID, 'created_task', 'Task created: ' . $data['title']);
        wp_send_json_success(['message' => 'Task created successfully', 'task_id' => $task_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create task']);
    }
}

/**
 * Update Task
 */
function bntm_ajax_pm_update_task() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $current_user = wp_get_current_user();
    $task_id = intval($_POST['task_id']);
    
    // Get existing task
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$tasks_table} WHERE id = %d ",
        $task_id, $current_user->ID
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    $data = [
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'status' => sanitize_text_field($_POST['status'] ?? 'To Do'),
        'priority' => sanitize_text_field($_POST['priority'] ?? 'medium'),
        'assigned_to' => intval($_POST['assigned_to'] ?? 0) ?: null,
        'milestone_id' => intval($_POST['milestone_id'] ?? 0) ?: null,
        'due_date' => sanitize_text_field($_POST['due_date'] ?? '') ?: null,
        'estimated_hours' => floatval($_POST['estimated_hours'] ?? 0),
        'tags' => sanitize_text_field($_POST['tags'] ?? ''),
    ];
    
    // Mark as completed if status changed to completed
    if ($data['status'] === 'completed' && $task->status !== 'completed') {
        $data['completed_date'] = current_time('mysql');
    }
    
    $result = $wpdb->update($tasks_table, $data, ['id' => $task_id]);
    
    if ($result !== false) {
        pm_log_activity($task->project_id, $task_id, $current_user->ID, 'updated_task', 'Task updated: ' . $data['title']);
        wp_send_json_success(['message' => 'Task updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update task']);
    }
}

/**
 * Update Task Status
 */
function bntm_ajax_pm_update_task_status() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $current_user = wp_get_current_user();
    $task_id = intval($_POST['task_id']);
    $status = sanitize_text_field($_POST['status']);
    
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$tasks_table} WHERE id = %d",
        $task_id
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    $data = ['status' => $status];
    
    // Mark as completed if status is completed
    if ($status === 'completed' || strtolower($status) === 'completed') {
        $data['completed_date'] = current_time('mysql');
    } else {
        $data['completed_date'] = null;
    }
    
    $result = $wpdb->update($tasks_table, $data, ['id' => $task_id]);
    
    if ($result !== false) {
        pm_log_activity($task->project_id, $task_id, $current_user->ID, 'status_changed', "Status changed to: $status");
        wp_send_json_success(['message' => 'Status updated']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}

/**
 * Delete Task
 */
function bntm_ajax_pm_delete_task() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $task_id = intval($_POST['task_id']);
    
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_tasks WHERE id = %d ",
        $task_id, $current_user->ID
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    // Delete related data
    $wpdb->delete($wpdb->prefix . 'pm_time_logs', ['task_id' => $task_id]);
    $wpdb->delete($wpdb->prefix . 'pm_comments', ['task_id' => $task_id]);
    $wpdb->delete($wpdb->prefix . 'pm_files', ['task_id' => $task_id]);
    
    // Delete task
    $result = $wpdb->delete($wpdb->prefix . 'pm_tasks', ['id' => $task_id]);
    
    if ($result) {
        pm_log_activity($task->project_id, null, $current_user->ID, 'deleted_task', 'Task deleted: ' . $task->title);
        wp_send_json_success(['message' => 'Task deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete task']);
    }
}
/**
 * Reorder Tasks
 */
function bntm_ajax_pm_reorder_tasks() {
    check_ajax_referer('pm_reorder_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $milestone_id = intval($_POST['milestone_id'] ?? 0);
    $task_order = json_decode(stripslashes($_POST['task_order']), true);
    
    if (!is_array($task_order)) {
        wp_send_json_error(['message' => 'Invalid task order']);
    }
    
    foreach ($task_order as $index => $task_id) {
        $update_data = ['sort_order' => $index];
        
        // Update milestone_id - use NULL if milestone_id is 0 (No Milestone)
        if ($milestone_id === 0) {
            $update_data['milestone_id'] = null;
        } else {
            $update_data['milestone_id'] = $milestone_id;
        }
        
        $wpdb->update(
            $tasks_table,
            $update_data,
            ['id' => intval($task_id)]
        );
    }
    
    wp_send_json_success(['message' => 'Tasks reordered']);
}

/**
 * Get Task Details
 */
function bntm_ajax_pm_get_task_details() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $task_id = intval($_POST['task_id']);
    
    // Get task
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_tasks WHERE id = %d",
        $task_id
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    // Get time logs with user names
    $time_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT tl.*, u.display_name as user_name 
         FROM {$wpdb->prefix}pm_time_logs tl
         LEFT JOIN {$wpdb->users} u ON tl.user_id = u.ID
         WHERE tl.task_id = %d
         ORDER BY tl.log_date DESC, tl.created_at DESC",
        $task_id
    ));
    
    // Get comments with user names
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name as user_name 
         FROM {$wpdb->prefix}pm_comments c
         LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
         WHERE c.task_id = %d
         ORDER BY c.created_at DESC",
        $task_id
    ));
    
    wp_send_json_success([
        'task' => $task,
        'time_logs' => $time_logs,
        'comments' => $comments
    ]);
}
/**
 * Import Tasks from JSON
 */
function bntm_ajax_pm_import_tasks() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $team_table = $wpdb->prefix . 'pm_team_members';
    $current_user = wp_get_current_user();
    
    $project_id = intval($_POST['project_id']);
    $tasks_json = stripslashes($_POST['tasks']);
    $tasks = json_decode($tasks_json, true);
    
    if (!is_array($tasks)) {
        wp_send_json_error(['message' => 'Invalid tasks data']);
    }
    
    // Get all milestones for this project
    $milestones = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$milestones_table} WHERE project_id = %d",
        $project_id
    ), OBJECT_K);
    
    // Get all team members for this project
    $team_members = $wpdb->get_results($wpdb->prepare(
        "SELECT tm.user_id, u.display_name, u.user_email 
         FROM {$team_table} tm
         INNER JOIN {$wpdb->users} u ON tm.user_id = u.ID
         WHERE tm.project_id = %d",
        $project_id
    ), ARRAY_A);
    
    $imported_count = 0;
    $created_milestones = [];
    
    foreach ($tasks as $task) {
        $milestone_id = null;
        
        // Check if milestone exists or create new one
        if (!empty($task['milestone'])) {
            $milestone_name = trim($task['milestone']);
            
            // Search for milestone by name (case-insensitive)
            $found = false;
            foreach ($milestones as $milestone) {
                if (strcasecmp($milestone->name, $milestone_name) === 0) {
                    $milestone_id = $milestone->id;
                    $found = true;
                    break;
                }
            }
            
            // If milestone doesn't exist, create it
            if (!$found) {
                // Check if we already created this milestone in this import session
                $milestone_key = strtolower($milestone_name);
                if (isset($created_milestones[$milestone_key])) {
                    $milestone_id = $created_milestones[$milestone_key];
                } else {
                    // Create new milestone
                    $milestone_data = [
                        'rand_id' => bntm_rand_id(),
                        'business_id' => $current_user->ID,
                        'project_id' => $project_id,
                        'name' => $milestone_name,
                        'description' => 'Auto-created from task import',
                        'status' => 'pending',
                        'due_date' => null,
                    ];
                    
                    $result = $wpdb->insert($milestones_table, $milestone_data);
                    
                    if ($result) {
                        $milestone_id = $wpdb->insert_id;
                        $created_milestones[$milestone_key] = $milestone_id;
                        
                        // Add to milestones array for subsequent tasks
                        $new_milestone = new stdClass();
                        $new_milestone->id = $milestone_id;
                        $new_milestone->name = $milestone_name;
                        $milestones[$milestone_id] = $new_milestone;
                        
                        pm_log_activity($project_id, null, $current_user->ID, 'created_milestone', 'Milestone auto-created from import: ' . $milestone_name);
                    }
                }
            }
        }
        
        // Find assigned user by name or email
        $assigned_to = null;
        if (!empty($task['assigned_to'])) {
            $assigned_name = trim($task['assigned_to']);
            
            foreach ($team_members as $member) {
                if (strcasecmp($member['display_name'], $assigned_name) === 0 || 
                    strcasecmp($member['user_email'], $assigned_name) === 0) {
                    $assigned_to = $member['user_id'];
                    break;
                }
            }
        }
        
        $data = [
            'rand_id' => bntm_rand_id(),
            'business_id' => $current_user->ID,
            'project_id' => $project_id,
            'title' => sanitize_text_field($task['title'] ?? 'Untitled Task'),
            'description' => sanitize_textarea_field($task['description'] ?? ''),
            'status' => sanitize_text_field($task['status'] ?? 'To Do'),
            'priority' => sanitize_text_field($task['priority'] ?? 'medium'),
            'assigned_to' => $assigned_to,
            'milestone_id' => $milestone_id,
            'due_date' => !empty($task['due_date']) ? sanitize_text_field($task['due_date']) : null,
            'estimated_hours' => floatval($task['estimated_hours'] ?? 0),
            'tags' => sanitize_text_field($task['tags'] ?? ''),
        ];
        
        $result = $wpdb->insert($tasks_table, $data);
        
        if ($result) {
            $imported_count++;
            pm_log_activity($project_id, $wpdb->insert_id, $current_user->ID, 'imported_task', 'Task imported: ' . $data['title']);
        }
    }
    
    $message = "Successfully imported {$imported_count} task(s)";
    if (count($created_milestones) > 0) {
        $message .= " and created " . count($created_milestones) . " new milestone(s)";
    }
    
    wp_send_json_success([
        'message' => $message,
        'imported' => $imported_count,
        'milestones_created' => count($created_milestones)
    ]);
}

/**
 * Export Tasks to JSON
 */
function bntm_ajax_pm_export_tasks() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $current_user = wp_get_current_user();
    
    $project_id = intval($_POST['project_id']);
    
    // Get all tasks for this project
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, m.name as milestone_name, u.display_name as assigned_user_name
         FROM {$tasks_table} t
         LEFT JOIN {$milestones_table} m ON t.milestone_id = m.id
         LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
         WHERE t.project_id = %d
         ORDER BY t.sort_order ASC, t.created_at DESC",
        $project_id
    ));
    
    if (!$tasks) {
        wp_send_json_success([
            'message' => 'No tasks to export',
            'tasks' => []
        ]);
        return;
    }
    
    // Format tasks for export
    $export_tasks = [];
    foreach ($tasks as $task) {
        $export_tasks[] = [
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'milestone' => $task->milestone_name,
            'assigned_to' => $task->assigned_user_name,
            'due_date' => $task->due_date,
            'estimated_hours' => floatval($task->estimated_hours),
            'tags' => $task->tags,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
        ];
    }
    
    wp_send_json_success([
        'message' => 'Tasks exported successfully',
        'tasks' => $export_tasks,
        'count' => count($export_tasks)
    ]);
}

/* ---------- MILESTONE AJAX HANDLERS ---------- */

/**
 * Create Milestone
 */
function bntm_ajax_pm_create_milestone() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $current_user = wp_get_current_user();
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'project_id' => intval($_POST['project_id']),
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? '') ?: null,
        'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
    ];
    
    $result = $wpdb->insert($milestones_table, $data);
    
    if ($result) {
        $milestone_id = $wpdb->insert_id;
        pm_log_activity($data['project_id'], null, $current_user->ID, 'created_milestone', 'Milestone created: ' . $data['name']);
        wp_send_json_success(['message' => 'Milestone created successfully', 'milestone_id' => $milestone_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create milestone']);
    }
}

/**
 * Update Milestone
 */
function bntm_ajax_pm_update_milestone() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $current_user = wp_get_current_user();
    $milestone_id = intval($_POST['milestone_id']);
    
    $milestone = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$milestones_table} WHERE id = %d ",
        $milestone_id, $current_user->ID
    ));
    
    if (!$milestone) {
        wp_send_json_error(['message' => 'Milestone not found']);
    }
    
    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description'] ?? ''),
        'due_date' => sanitize_text_field($_POST['due_date'] ?? '') ?: null,
        'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
    ];
    
    $result = $wpdb->update($milestones_table, $data, ['id' => $milestone_id]);
    
    if ($result !== false) {
        pm_log_activity($milestone->project_id, null, $current_user->ID, 'updated_milestone', 'Milestone updated: ' . $data['name']);
        wp_send_json_success(['message' => 'Milestone updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update milestone']);
    }
}

/**
 * Delete Milestone
 */
function bntm_ajax_pm_delete_milestone() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $milestone_id = intval($_POST['milestone_id']);
    
    $milestone = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_milestones WHERE id = %d ",
        $milestone_id, $current_user->ID
    ));
    
    if (!$milestone) {
        wp_send_json_error(['message' => 'Milestone not found']);
    }
    
    // Move tasks to no milestone
    $wpdb->update(
        $wpdb->prefix . 'pm_tasks',
        ['milestone_id' => null],
        ['milestone_id' => $milestone_id]
    );
    
    // Delete milestone
    $result = $wpdb->delete($wpdb->prefix . 'pm_milestones', ['id' => $milestone_id]);
    
    if ($result) {
        pm_log_activity($milestone->project_id, null, $current_user->ID, 'deleted_milestone', 'Milestone deleted: ' . $milestone->name);
        wp_send_json_success(['message' => 'Milestone deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete milestone']);
    }
}

/**
 * Reorder Milestones
 */
function bntm_ajax_pm_reorder_milestones() {
    check_ajax_referer('pm_reorder_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $milestone_order = json_decode(stripslashes($_POST['milestone_order']), true);
    
    if (!is_array($milestone_order)) {
        wp_send_json_error(['message' => 'Invalid milestone order']);
    }
    
    foreach ($milestone_order as $index => $milestone_id) {
        $wpdb->update(
            $milestones_table,
            ['sort_order' => $index],
            ['id' => intval($milestone_id)]
        );
    }
    
    wp_send_json_success(['message' => 'Milestones reordered']);
}

/* ---------- TEAM AJAX HANDLERS ---------- */

/**
 * Add Team Member
 */
function bntm_ajax_pm_add_team_member() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $team_table = $wpdb->prefix . 'pm_team_members';
    $current_user = wp_get_current_user();
    
    $project_id = intval($_POST['project_id']);
    $user_id = intval($_POST['user_id']);
    $role = sanitize_text_field($_POST['role'] ?? 'staff');
    
    // Check if already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$team_table} WHERE project_id = %d AND user_id = %d",
        $project_id, $user_id
    ));
    
    if ($exists) {
        wp_send_json_error(['message' => 'User is already a team member']);
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'project_id' => $project_id,
        'user_id' => $user_id,
        'role' => $role,
    ];
    
    $result = $wpdb->insert($team_table, $data);
    
    if ($result) {
        $user = get_userdata($user_id);
        pm_log_activity($project_id, null, $current_user->ID, 'added_member', 'Added team member: ' . $user->display_name);
        wp_send_json_success(['message' => 'Team member added successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to add team member']);
    }
}

/**
 * Remove Team Member
 */
function bntm_ajax_pm_remove_team_member() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $member_id = intval($_POST['member_id']);
    
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_team_members WHERE id = %d",
        $member_id, $current_user->ID
    ));
    
    if (!$member) {
        wp_send_json_error(['message' => 'Team member not found']);
    }
    
    $result = $wpdb->delete($wpdb->prefix . 'pm_team_members', ['id' => $member_id]);
    
    if ($result) {
        $user = get_userdata($member->user_id);
        pm_log_activity($member->project_id, null, $current_user->ID, 'removed_member', 'Removed team member: ' . $user->display_name);
        wp_send_json_success(['message' => 'Team member removed successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to remove team member']);
    }
}

/* ---------- TIME LOG AJAX HANDLERS ---------- */

/**
 * Add Time Log
 */
function bntm_ajax_pm_add_time_log() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    $current_user = wp_get_current_user();
    
    $task_id = intval($_POST['task_id']);
    $hours = floatval($_POST['hours']);
    $log_date = sanitize_text_field($_POST['log_date']);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    
    if ($hours <= 0) {
        wp_send_json_error(['message' => 'Hours must be greater than 0']);
    }
    
    // Get task to update actual hours
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_tasks WHERE id = %d",
        $task_id
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'task_id' => $task_id,
        'user_id' => $current_user->ID,
        'hours' => $hours,
        'log_date' => $log_date,
        'notes' => $notes,
    ];
    
    $result = $wpdb->insert($time_logs_table, $data);
    
    if ($result) {
        // Update task actual hours
        $total_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(hours) FROM {$time_logs_table} WHERE task_id = %d",
            $task_id
        ));
        
        $wpdb->update(
            $wpdb->prefix . 'pm_tasks',
            ['actual_hours' => $total_hours],
            ['id' => $task_id]
        );
        
        pm_log_activity($task->project_id, $task_id, $current_user->ID, 'logged_time', "Logged {$hours}h");
        wp_send_json_success(['message' => 'Time log added successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to add time log']);
    }
}

/**
 * Delete Time Log
 */
function bntm_ajax_pm_delete_time_log() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $log_id = intval($_POST['log_id']);
    
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_time_logs WHERE id = %d",
        $log_id
    ));
    
    if (!$log) {
        wp_send_json_error(['message' => 'Time log not found']);
    }
    
    // Only allow deletion if user created it or is project owner
    if ($log->user_id != $current_user->ID && $log->business_id != $current_user->ID) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $result = $wpdb->delete($wpdb->prefix . 'pm_time_logs', ['id' => $log_id]);
    
    if ($result) {
        // Update task actual hours
        $total_hours = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(hours) FROM {$wpdb->prefix}pm_time_logs WHERE task_id = %d",
            $log->task_id
        ));
        
        $wpdb->update(
            $wpdb->prefix . 'pm_tasks',
            ['actual_hours' => $total_hours ?: 0],
            ['id' => $log->task_id]
        );
        
        wp_send_json_success(['message' => 'Time log deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete time log']);
    }
}

/* ---------- COMMENT AJAX HANDLERS ---------- */

/**
 * Add Comment
 */
function bntm_ajax_pm_add_comment() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    global $wpdb;
    $comments_table = $wpdb->prefix . 'pm_comments';
    $current_user = wp_get_current_user();
    
    $task_id = intval($_POST['task_id']);
    $comment = sanitize_textarea_field($_POST['comment']);
    
    if (empty($comment)) {
        wp_send_json_error(['message' => 'Comment cannot be empty']);
    }
    
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_tasks WHERE id = %d",
        $task_id
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'task_id' => $task_id,
        'user_id' => $current_user->ID,
        'comment' => $comment,
    ];
    
    $result = $wpdb->insert($comments_table, $data);
    
    if ($result) {
        pm_log_activity($task->project_id, $task_id, $current_user->ID, 'added_comment', 'Added a comment');
        wp_send_json_success(['message' => 'Comment added successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to add comment']);
    }
}

/**
 * Delete Comment
 */
function bntm_ajax_pm_delete_comment() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $comment_id = intval($_POST['comment_id']);
    
    $comment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_comments WHERE id = %d",
        $comment_id
    ));
    
    if (!$comment) {
        wp_send_json_error(['message' => 'Comment not found']);
    }
    
    // Only allow deletion if user created it or is project owner
    if ($comment->user_id != $current_user->ID && $comment->business_id != $current_user->ID) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $result = $wpdb->delete($wpdb->prefix . 'pm_comments', ['id' => $comment_id]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Comment deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete comment']);
    }
}

/* ---------- STATUS AJAX HANDLERS ---------- */

/**
 * Create Status
 */
function bntm_ajax_pm_create_status() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $statuses_table = $wpdb->prefix . 'pm_project_statuses';
    $current_user = wp_get_current_user();
    
    $project_id = intval($_POST['project_id']);
    $status_name = sanitize_text_field($_POST['status_name']);
    $status_color = sanitize_text_field($_POST['status_color'] ?? '#3b82f6');
    
    // Get max sort order
    $max_order = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(sort_order) FROM {$statuses_table} WHERE project_id = %d",
        $project_id
    ));
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'project_id' => $project_id,
        'status_name' => $status_name,
        'status_color' => $status_color,
        'sort_order' => ($max_order ?? 0) + 1,
        'is_default' => 0,
    ];
    
    $result = $wpdb->insert($statuses_table, $data);
    
    if ($result) {
        pm_log_activity($project_id, null, $current_user->ID, 'created_status', 'Created status: ' . $status_name);
        wp_send_json_success(['message' => 'Status created successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to create status']);
    }
}

/**
 * Update Status
 */
function bntm_ajax_pm_update_status() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $statuses_table = $wpdb->prefix . 'pm_project_statuses';
    $current_user = wp_get_current_user();
    
    $status_id = intval($_POST['status_id']);
    $status_name = sanitize_text_field($_POST['status_name']);
    $status_color = sanitize_text_field($_POST['status_color'] ?? '#3b82f6');
    
    $status = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$statuses_table} WHERE id = %d ",
        $status_id, $current_user->ID
    ));
    
    if (!$status) {
        wp_send_json_error(['message' => 'Status not found']);
    }
    
    $old_name = $status->status_name;
    
    $data = [
        'status_name' => $status_name,
        'status_color' => $status_color,
    ];
    
    $result = $wpdb->update($statuses_table, $data, ['id' => $status_id]);
    
    if ($result !== false) {
        // Update tasks with this status
        if ($old_name !== $status_name) {
            $wpdb->update(
                $wpdb->prefix . 'pm_tasks',
                ['status' => $status_name],
                ['status' => $old_name, 'project_id' => $status->project_id]
            );
        }
        
        pm_log_activity($status->project_id, null, $current_user->ID, 'updated_status', 'Updated status: ' . $status_name);
        wp_send_json_success(['message' => 'Status updated successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to update status']);
    }
}

/**
 * Delete Status
 */
function bntm_ajax_pm_delete_status() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $status_id = intval($_POST['status_id']);
    
    $status = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_project_statuses WHERE id = %d",
        $status_id, $current_user->ID
    ));
    
    if (!$status) {
        wp_send_json_error(['message' => 'Status not found']);
    }
    
    // Get default status or first status
    $default_status = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_project_statuses 
         WHERE project_id = %d AND id != %d 
         ORDER BY is_default DESC, sort_order ASC LIMIT 1",
        $status->project_id, $status_id
    ));
    
    if (!$default_status) {
        wp_send_json_error(['message' => 'Cannot delete the only status. Create another status first.']);
    }
    
    // Move tasks to default status
    $wpdb->update(
        $wpdb->prefix . 'pm_tasks',
        ['status' => $default_status->status_name],
        ['status' => $status->status_name, 'project_id' => $status->project_id]
    );
    
    // Delete status
    $result = $wpdb->delete($wpdb->prefix . 'pm_project_statuses', ['id' => $status_id]);
    
    if ($result) {
        pm_log_activity($status->project_id, null, $current_user->ID, 'deleted_status', 'Deleted status: ' . $status->status_name);
        wp_send_json_success(['message' => 'Status deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete status']);
    }
}

/**
 * Reorder Statuses
 */
function bntm_ajax_pm_reorder_statuses() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $statuses_table = $wpdb->prefix . 'pm_project_statuses';
    $status_order = json_decode(stripslashes($_POST['status_order']), true);
    
    if (!is_array($status_order)) {
        wp_send_json_error(['message' => 'Invalid status order']);
    }
    
    foreach ($status_order as $index => $status_id) {
        $wpdb->update(
            $statuses_table,
            ['sort_order' => $index],
            ['id' => intval($status_id)]
        );
    }
    
    wp_send_json_success(['message' => 'Statuses reordered']);
}

/**
 * Reorder Projects
 */
function bntm_ajax_pm_reorder_projects() {
    check_ajax_referer('pm_reorder_projects_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $project_order = json_decode(stripslashes($_POST['project_order']), true);
    
    if (!is_array($project_order)) {
        wp_send_json_error(['message' => 'Invalid project order']);
    }
    
    foreach ($project_order as $index => $project_id) {
        $wpdb->update(
            $projects_table,
            ['sort_order' => $index],
            ['id' => intval($project_id)]
        );
    }
    
    wp_send_json_success(['message' => 'Projects reordered']);
}

/* ---------- ACTIVITY LOG ---------- */

/**
 * Log Activity
 */
function bntm_ajax_pm_log_activity() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $current_user->ID,
        'project_id' => intval($_POST['project_id'] ?? 0) ?: null,
        'task_id' => intval($_POST['task_id'] ?? 0) ?: null,
        'user_id' => $current_user->ID,
        'action' => sanitize_text_field($_POST['action']),
        'details' => sanitize_textarea_field($_POST['details'] ?? ''),
    ];
    
    $result = $wpdb->insert($wpdb->prefix . 'pm_activity_log', $data);
    
    if ($result) {
        wp_send_json_success(['message' => 'Activity logged']);
    } else {
        wp_send_json_error(['message' => 'Failed to log activity']);
    }
}

/* ---------- HELPER FUNCTIONS ---------- */

/**
 * Initialize project statuses
 */
function pm_initialize_project_statuses($project_id, $business_id) {
    global $wpdb;
    $statuses_table = $wpdb->prefix . 'pm_project_statuses';
    
    $default_statuses = [
        ['name' => 'To Do', 'color' => '#6b7280', 'order' => 1, 'default' => 1],
        ['name' => 'In Progress', 'color' => '#3b82f6', 'order' => 2, 'default' => 0],
        ['name' => 'Review', 'color' => '#f59e0b', 'order' => 3, 'default' => 0],
        ['name' => 'Completed', 'color' => '#10b981', 'order' => 4, 'default' => 0],
    ];
    
    foreach ($default_statuses as $status) {
        $wpdb->insert(
            $statuses_table,
            [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'project_id' => $project_id,
                'status_name' => $status['name'],
                'status_color' => $status['color'],
                'sort_order' => $status['order'],
                'is_default' => $status['default'],
            ]
        );
    }
}

/**
 * Returns a SQL WHERE fragment + params array that restricts task rows to
 * only those visible to the current user based on their role.
 *
 * @param string $task_alias  The SQL alias used for the tasks table (default "t")
 * @return array { where: string, params: array }
 */
function pm_get_task_visibility( $task_alias = 't' ) {
    global $wpdb;

    $current_user = wp_get_current_user();

    // ── Full access: WP admins, global owners/managers ──────────────────────
    $is_wp_admin    = current_user_can( 'manage_options' );
    $current_role   = bntm_get_user_role( $current_user->ID );
    $can_view_all   = $is_wp_admin || in_array( $current_role, [ 'owner', 'manager' ] );

    if ( $can_view_all ) {
        return [ 'where' => '', 'params' => [] ];
    }

    // ── Project-manager: can see all tasks in projects they manage ───────────
    $team_table         = $wpdb->prefix . 'pm_team_members';
    $managed_project_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT project_id FROM {$team_table}
         WHERE user_id = %d AND role = 'project_manager'",
        $current_user->ID
    ) );

    // ── Build the WHERE fragment ─────────────────────────────────────────────
    $uid = intval( $current_user->ID );

    if ( ! empty( $managed_project_ids ) ) {
        // Can see: tasks assigned to them  OR  tasks in projects they manage
        $placeholders = implode( ',', array_fill( 0, count( $managed_project_ids ), '%d' ) );
        $where  = " AND ( {$task_alias}.assigned_to = %d OR {$task_alias}.project_id IN ({$placeholders}) )";
        $params = array_merge( [ $uid ], array_map( 'intval', $managed_project_ids ) );
    } else {
        // Plain staff: only tasks assigned to them
        $where  = " AND {$task_alias}.assigned_to = %d";
        $params = [ $uid ];
    }

    return [ 'where' => $where, 'params' => $params ];
}

/**
 * Log activity helper
 */

function pm_log_activity($project_id, $task_id, $user_id, $action, $details) {
    global $wpdb;
    
    // Get current time in Philippine timezone (Asia/Manila)
    $manila_timezone = new DateTimeZone('Asia/Manila');
    $current_time = new DateTime('now', $manila_timezone);
    $mysql_time = $current_time->format('Y-m-d H:i:s');
    
    $wpdb->insert(
        $wpdb->prefix . 'pm_activity_log',
        [
            'rand_id' => bntm_rand_id(),
            'business_id' => $user_id,
            'project_id' => $project_id,
            'task_id' => $task_id,
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'created_at' => $mysql_time, // Philippine time
        ]
    );
}

/**
 * Get status color helper
 */
function pm_get_status_color($status) {
    $colors = [
        'planning' => '#6b7280',
        'In Progress' => '#3b82f6',
        'in_progress' => '#3b82f6',
        'on_hold' => '#f59e0b',
        'completed' => '#10b981',
        'cancelled' => '#ef4444',
        'closed' => '#000',
        'To Do' => '#6b7280',
        'Review' => '#f59e0b',
        'pending' => '#9ca3af',
    ];
    
    return $colors[$status] ?? '#6b7280';
}

/**
 * Get priority color helper
 */
function pm_get_priority_color($priority) {
    $colors = [
        'low' => '#3b82f6',
        'medium' => '#f59e0b',
        'high' => '#ef4444',
    ];
    
    return $colors[$priority] ?? '#6b7280';
}

/**
 * Helper function to get action icon
 */
function pm_get_action_icon($action) {
    $icons = [
        'created_project' => '📁',
        'updated_project' => '✏️',
        'deleted_project' => '🗑️',
        'created_task' => '✅',
        'updated_task' => '📝',
        'deleted_task' => '❌',
        'created_milestone' => '🎯',
        'updated_milestone' => '🔄',
        'deleted_milestone' => '🗑️',
        'added_member' => '👤',
        'removed_member' => '👋',
        'imported_task' => '📥',
        'added_time_log' => '⏱️',
        'added_comment' => '💬',
    ];
    
    return $icons[$action] ?? '•';
}

/**
 * Helper function to get action color
 */
function pm_get_action_color($action) {
    $colors = [
        'created_project' => '#10b981',
        'updated_project' => '#3b82f6',
        'deleted_project' => '#ef4444',
        'created_task' => '#10b981',
        'updated_task' => '#3b82f6',
        'deleted_task' => '#ef4444',
        'created_milestone' => '#8b5cf6',
        'updated_milestone' => '#3b82f6',
        'deleted_milestone' => '#ef4444',
        'added_member' => '#10b981',
        'removed_member' => '#f59e0b',
        'imported_task' => '#06b6d4',
        'added_time_log' => '#3b82f6',
        'added_comment' => '#8b5cf6',
    ];
    
    return $colors[$action] ?? '#6b7280';
}

/* ---------- CRON JOBS FOR NOTIFICATIONS ---------- */
function pm_setup_cron_jobs() {
    if (!wp_next_scheduled('pm_daily_task_reminders')) {
        wp_schedule_event(time(), 'daily', 'pm_daily_task_reminders');
    }
}
add_action('wp', 'pm_setup_cron_jobs');

function pm_send_daily_task_reminders() {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    
    // Get tasks due in the next 24 hours
    $upcoming_tasks = $wpdb->get_results(
        "SELECT * FROM $tasks_table 
        WHERE status != 'done' 
        AND due_date = CURDATE() + INTERVAL 1 DAY"
    );
    
    foreach ($upcoming_tasks as $task) {
        pm_send_task_notification($task->id, 'reminder');
    }
    
    // Get overdue tasks
    $overdue_tasks = $wpdb->get_results(
        "SELECT * FROM $tasks_table 
        WHERE status != 'done' 
        AND due_date < CURDATE()"
    );
    
    foreach ($overdue_tasks as $task) {
        pm_send_task_notification($task->id, 'overdue');
    }
}
add_action('pm_daily_task_reminders', 'pm_send_daily_task_reminders');

/* ---------- EXPORT FUNCTIONS ---------- */
function pm_export_project_report($project_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    
    $project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $projects_table WHERE id = %d",
        $project_id
    ));
    
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tasks_table WHERE project_id = %d",
        $project_id
    ));
    
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="project-' . $project_id . '-report.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Project info
    fputcsv($output, ['Project Report']);
    fputcsv($output, ['Project Name', $project->name]);
    fputcsv($output, ['Status', $project->status]);
    fputcsv($output, ['Progress', $project->progress . '%']);
    fputcsv($output, []);
    
    // Tasks
    fputcsv($output, ['Task ID', 'Title', 'Status', 'Priority', 'Due Date', 'Estimated Hours', 'Actual Hours']);
    
    foreach ($tasks as $task) {
        fputcsv($output, [
            $task->id,
            $task->title,
            $task->status,
            $task->priority,
            $task->due_date,
            $task->estimated_hours,
            $task->actual_hours
        ]);
    }
    
    fclose($output);
    exit;
}
/* ---------- NOTIFICATION SYSTEM ---------- */
function pm_get_user_notifications($business_id, $limit = 15) {
    global $wpdb;
    $activity_log_table = $wpdb->prefix . 'pm_activity_log';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $team_table = $wpdb->prefix . 'pm_team_members';
    $comments_table = $wpdb->prefix . 'pm_comments';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    
    $current_user_id = get_current_user_id();
    
    // Get activity where:
    // 1. Someone else did something (not me)
    // 2. It's related to my tasks, projects, or tasks I've engaged with
    $notifications = $wpdb->get_results($wpdb->prepare("
        SELECT 
            a.id,
            a.user_id,
            a.action,
            a.details,
            a.task_id,
            a.project_id,
            a.created_at,
            u.display_name as user_name,
            t.title as task_title,
            t.assigned_to as task_assigned_to,
            t.status as task_status,
            p.name as project_name,
            p.color as project_color,
            p.business_id as project_owner
        FROM $activity_log_table a
        LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
        LEFT JOIN $tasks_table t ON a.task_id = t.id
        LEFT JOIN $projects_table p ON COALESCE(a.project_id, t.project_id) = p.id
        LEFT JOIN $team_table tm ON p.id = tm.project_id AND tm.user_id = %d
        WHERE a.user_id != %d
        AND (
            -- Task assigned to me
            t.assigned_to = %d
            OR
            -- I'm a team member on this project
            tm.user_id = %d
            OR
            -- I own the project
            p.business_id = %d
            OR
            -- I've commented on this task
            EXISTS (
                SELECT 1 FROM $comments_table c 
                WHERE c.task_id = t.id AND c.user_id = %d
            )
            OR
            -- I've logged time on this task
            EXISTS (
                SELECT 1 FROM $time_logs_table tl 
                WHERE tl.task_id = t.id AND tl.user_id = %d
            )
        )
        ORDER BY a.created_at DESC
        LIMIT %d
    ", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, 
       $current_user_id, $current_user_id, $limit));
    
    return $notifications;
}

function pm_get_unread_notification_count($business_id) {
    global $wpdb;
    $activity_log_table = $wpdb->prefix . 'pm_activity_log';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $team_table = $wpdb->prefix . 'pm_team_members';
    
    $current_user_id = get_current_user_id();
    
    // Check for last seen notification timestamp
    $last_seen = get_user_meta($current_user_id, 'pm_notifications_last_seen', true);
    if (!$last_seen) {
        $last_seen = '2000-01-01 00:00:00';
    }
    
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(DISTINCT a.id)
        FROM $activity_log_table a
        LEFT JOIN $tasks_table t ON a.task_id = t.id
        LEFT JOIN $projects_table p ON COALESCE(a.project_id, t.project_id) = p.id
        LEFT JOIN $team_table tm ON p.id = tm.project_id AND tm.user_id = %d
        WHERE a.user_id != %d
        AND a.created_at > %s
        AND (
            t.assigned_to = %d
            OR tm.user_id = %d
            OR p.business_id = %d
        )
    ", $current_user_id, $current_user_id, $last_seen, $current_user_id, $current_user_id, $current_user_id));
    
    return (int)$count;
}
function pm_notification_bell_button($business_id) {
    $unread_count = pm_get_unread_notification_count($business_id);
    $notifications = pm_get_user_notifications($business_id, 15);
    
    ob_start();
    ?>
    <div class="pm-notification-wrapper" style="position: relative; display: inline-block;">
        <button id="pm-notification-bell" class="pm-notification-bell-btn" title="Notifications">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <?php if ($unread_count > 0): ?>
                <span class="pm-notification-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
            <?php endif; ?>
        </button>
        
        <div id="pm-notification-dropdown" class="pm-notification-dropdown" style="display: none;">
            <div class="pm-notification-header">
                <h4 style="margin: 0; font-size: 16px;">Notifications</h4>
                <button id="pm-mark-all-read" class="pm-mark-read-btn" title="Mark all as read">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </button>
            </div>
            
            <div class="pm-notification-list">
                <?php if (empty($notifications)): ?>
                    <div class="pm-notification-empty">
                        <p>No notifications yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                            <?php
                            // Determine notification icon
                            $icon = '📝';
                            if (strpos($notification->action, 'completed') !== false) $icon = '✅';
                            elseif (strpos($notification->action, 'commented') !== false) $icon = '💬';
                            elseif (strpos($notification->action, 'assigned') !== false) $icon = '👤';
                            elseif (strpos($notification->action, 'logged time') !== false) $icon = '⏱️';
                            elseif (strpos($notification->action, 'created') !== false) $icon = '✨';
                            elseif (strpos($notification->action, 'updated') !== false) $icon = '🔄';
                            
                            // Build link - redirect to home URL with proper parameters
                            $home_url = home_url('/');
                            if ($notification->task_id) {
                                // Task-related notification - go to My Tasks tab with task detail
                                $link = $home_url . 'projects?tab=tasks&task_id=' . $notification->task_id;
                            } elseif ($notification->project_id) {
                                // Project-related notification - go to Projects tab with project view
                                $link = $home_url . 'projects?tab=projects&project_id=' . $notification->project_id;
                            } else {
                                // General notification - go to overview
                                $link = $home_url . 'projects?tab=overview';
                            }
                            
                            // Check if unread
                            $last_seen = get_user_meta(get_current_user_id(), 'pm_notifications_last_seen', true);
                            $is_unread = !$last_seen || strtotime($notification->created_at) > strtotime($last_seen);
                            ?>
                            <a href="<?php echo esc_url($link); ?>" class="pm-notification-item <?php echo $is_unread ? 'pm-unread' : ''; ?>">
                            <div class="pm-notification-icon"><?php echo $icon; ?></div>
                            <div class="pm-notification-content">
                                <div class="pm-notification-text">
                                    <strong><?php echo esc_html($notification->user_name); ?></strong>
                                    <?php echo esc_html($notification->action); ?>
                                </div>
                                <?php if ($notification->task_title): ?>
                                    <div class="pm-notification-meta">
                                        <span style="color: <?php echo esc_attr($notification->project_color); ?>;">●</span>
                                        <?php echo esc_html($notification->task_title); ?>
                                    </div>
                                <?php elseif ($notification->project_name): ?>
                                    <div class="pm-notification-meta">
                                        <span style="color: <?php echo esc_attr($notification->project_color); ?>;">●</span>
                                        <?php echo esc_html($notification->project_name); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($notification->details): ?>
                                    <div class="pm-notification-details">
                                        <?php echo esc_html(wp_trim_words($notification->details, 10)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="pm-notification-time">
                                    <?php echo human_time_diff(strtotime($notification->created_at), current_time('timestamp')); ?> ago
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="pm-notification-footer">
                <a href="<?php echo home_url('/projects?tab=overview'); ?>" style="color: #3b82f6; text-decoration: none; font-size: 13px; font-weight: 500;">
                    View all activity
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .pm-notification-bell-btn {
        position: relative;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 8px 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        color: #6b7280;
    }
    .pm-notification-bell-btn:hover {
        background: #f9fafb;
        border-color: #d1d5db;
        color: #1f2937;
    }
    .pm-notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #dc2626;
        color: white;
        font-size: 10px;
        font-weight: 600;
        padding: 2px 5px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }
    .pm-notification-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 400px;
        max-width: 90vw;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    .pm-notification-header {
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pm-mark-read-btn {
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .pm-mark-read-btn:hover {
        background: #f3f4f6;
        color: #1f2937;
    }
    .pm-notification-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .pm-notification-item {
        display: flex;
        gap: 12px;
        padding: 12px 15px;
        border-bottom: 1px solid #f3f4f6;
        text-decoration: none;
        color: inherit;
        transition: background 0.2s;
    }
    .pm-notification-item:hover {
        background: #f9fafb;
    }
    .pm-notification-item.pm-unread {
        background: #eff6ff;
    }
    .pm-notification-icon {
        width: 36px;
        height: 36px;
        background: #f3f4f6;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .pm-notification-content {
        flex: 1;
        min-width: 0;
    }
    .pm-notification-text {
        font-size: 14px;
        color: #1f2937;
        margin-bottom: 4px;
        line-height: 1.4;
    }
    .pm-notification-meta {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 2px;
    }
    .pm-notification-details {
        font-size: 12px;
        color: #9ca3af;
        margin-top: 4px;
    }
    .pm-notification-time {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 4px;
    }
    .pm-notification-footer {
        padding: 12px 15px;
        border-top: 1px solid #e5e7eb;
        text-align: center;
    }
    .pm-notification-empty {
        padding: 40px 20px;
        text-align: center;
        color: #9ca3af;
    }
    </style>
    
    <script>
    (function() {
        const bellBtn = document.getElementById('pm-notification-bell');
        const dropdown = document.getElementById('pm-notification-dropdown');
        const markAllReadBtn = document.getElementById('pm-mark-all-read');
        
        // Toggle dropdown
        bellBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isVisible = dropdown.style.display === 'block';
            dropdown.style.display = isVisible ? 'none' : 'block';
            
            // Mark as seen when opened
            if (!isVisible) {
                markNotificationsAsSeen();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.pm-notification-wrapper')) {
                dropdown.style.display = 'none';
            }
        });
        
        // Mark all as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                markNotificationsAsSeen();
                
                // Remove unread styling
                document.querySelectorAll('.pm-notification-item.pm-unread').forEach(item => {
                    item.classList.remove('pm-unread');
                });
                
                // Remove badge
                const badge = document.querySelector('.pm-notification-badge');
                if (badge) {
                    badge.remove();
                }
            });
        }
        
        function markNotificationsAsSeen() {
            const formData = new FormData();
            formData.append('action', 'pm_mark_notifications_seen');
            formData.append('nonce', '<?php echo wp_create_nonce('pm_nonce'); ?>');
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}   

// AJAX Handler for marking notifications as seen
add_action('wp_ajax_pm_mark_notifications_seen', 'pm_mark_notifications_seen_ajax');
function pm_mark_notifications_seen_ajax() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    $current_user_id = get_current_user_id();
    update_user_meta($current_user_id, 'pm_notifications_last_seen', current_time('mysql'));
    
    wp_send_json_success(['message' => 'Notifications marked as seen']);
}

/* ---------- GOOGLE CALENDAR INTEGRATION ---------- */

/**
 * Export Task to Google Calendar
 */
function bntm_ajax_pm_export_to_google_calendar() {
    check_ajax_referer('pm_project_detail_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $current_user = wp_get_current_user();
    $task_id = intval($_POST['task_id']);
    
    // Get task details
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}pm_tasks WHERE id = %d",
        $task_id
    ));
    
    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }
    
    // Get Google Calendar API credentials from user meta
    $access_token = get_user_meta($current_user->ID, 'pm_google_calendar_access_token', true);
    
    if (!$access_token) {
        wp_send_json_error([
            'message' => 'Google Calendar not configured. Please connect your Google account.',
            'auth_required' => true
        ]);
        return;
    }
    
    // Prepare event data
    $event_data = [
        'summary' => $task->title,
        'description' => $task->description ?: '',
        'start' => [
            'date' => $task->due_date ?: date('Y-m-d')
        ],
        'end' => [
            'date' => $task->due_date ?: date('Y-m-d')
        ]
    ];
    
    // Add time details if due_date exists
    if ($task->due_date) {
        $event_data['start'] = [
            'dateTime' => $task->due_date . 'T09:00:00',
            'timeZone' => 'UTC'
        ];
        $event_data['end'] = [
            'dateTime' => $task->due_date . 'T10:00:00',
            'timeZone' => 'UTC'
        ];
    }
    
    // Call Google Calendar API
    $response = pm_create_google_calendar_event($access_token, $event_data);
    
    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to export to Google Calendar: ' . $response->get_error_message()]);
    } else {
        // Update task with Google Calendar event ID
        $wpdb->update(
            $wpdb->prefix . 'pm_tasks',
            ['google_calendar_event_id' => $response['id']],
            ['id' => $task_id]
        );
        
        wp_send_json_success([
            'message' => 'Task exported to Google Calendar successfully!',
            'event_id' => $response['id']
        ]);
    }
}

/**
 * Create Google Calendar Event
 */
function pm_create_google_calendar_event($access_token, $event_data) {
    $url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($event_data),
        'timeout' => 30
    ]);
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (wp_remote_retrieve_response_code($response) !== 200) {
        return new WP_Error(
            'google_calendar_error',
            isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error'
        );
    }
    
    return $body;
}

/**
 * Save Google Calendar Settings
 */
function bntm_ajax_pm_save_google_calendar_settings() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $current_user = wp_get_current_user();
    $client_id = sanitize_text_field($_POST['client_id']);
    $access_token = isset($_POST['access_token']) ? sanitize_text_field($_POST['access_token']) : null;
    
    if ($client_id) {
        // Save Client ID as a WordPress option
        update_option('pm_google_calendar_client_id', $client_id);
    }
    
    if ($access_token) {
        // Save Access Token in user meta
        update_user_meta($current_user->ID, 'pm_google_calendar_access_token', $access_token);
        update_user_meta($current_user->ID, 'pm_google_calendar_connected', true);
    }
    
    wp_send_json_success(['message' => 'Google Calendar settings saved successfully!']);
}

/**
 * Get Google Calendar Auth URL
 */
function bntm_ajax_pm_get_google_calendar_auth_url() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    // Use WP option for Google Calendar API credentials
    $client_id = get_option('pm_google_calendar_client_id');
    $redirect_uri = admin_url('admin-ajax.php?action=pm_google_calendar_callback');
    
    if (!$client_id) {
        wp_send_json_error([
            'message' => 'Google Calendar API not configured. Please add your credentials in settings.'
        ]);
        return;
    }
    
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'access_type' => 'offline',
        'prompt' => 'consent'
    ]);
    
    wp_send_json_success(['auth_url' => $auth_url]);
}