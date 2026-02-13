<?php
/**
 * Module Name: Project Management
 * Module Slug: pm
 * Description: Complete project and task management solution with team collaboration
 * Version: 1.0.1
 * Author: Your Name
 * Icon: 📋
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_PM_PATH', dirname(__FILE__) . '/');
define('BNTM_PM_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_pm_get_pages() {
    return [
        'Projects' => '[pm_dashboard]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_pm_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'pm_projects' => "CREATE TABLE {$prefix}pm_projects (
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_client (client_id),
            INDEX idx_status (status),
            INDEX idx_dates (start_date, due_date)
        ) {$charset};",
        
        'pm_tasks' => "CREATE TABLE {$prefix}pm_tasks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'todo',
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
        
        'pm_milestones' => "CREATE TABLE {$prefix}pm_milestones (
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
        
        'pm_team_members' => "CREATE TABLE {$prefix}pm_team_members (
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
        
        'pm_time_logs' => "CREATE TABLE {$prefix}pm_time_logs (
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
        
        'pm_comments' => "CREATE TABLE {$prefix}pm_comments (
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
        
        'pm_files' => "CREATE TABLE {$prefix}pm_files (
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
        
        'pm_activity_log' => "CREATE TABLE {$prefix}pm_activity_log (
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
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_pm_get_shortcodes() {
    return [
        'pm_dashboard' => 'bntm_pm_shortcode_dashboard'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_pm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_pm_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_pm_add_project', 'bntm_ajax_pm_add_project');
add_action('wp_ajax_pm_update_project', 'bntm_ajax_pm_update_project');
add_action('wp_ajax_pm_delete_project', 'bntm_ajax_pm_delete_project');
add_action('wp_ajax_pm_add_task', 'bntm_ajax_pm_add_task');
add_action('wp_ajax_pm_update_task', 'bntm_ajax_pm_update_task');
add_action('wp_ajax_pm_update_task_status', 'bntm_ajax_pm_update_task_status');
add_action('wp_ajax_pm_delete_task', 'bntm_ajax_pm_delete_task');
add_action('wp_ajax_pm_add_milestone', 'bntm_ajax_pm_add_milestone');
add_action('wp_ajax_pm_delete_milestone', 'bntm_ajax_pm_delete_milestone');
add_action('wp_ajax_pm_add_team_member', 'bntm_ajax_pm_add_team_member');
add_action('wp_ajax_pm_remove_team_member', 'bntm_ajax_pm_remove_team_member');
add_action('wp_ajax_pm_log_time', 'bntm_ajax_pm_log_time');
add_action('wp_ajax_pm_add_comment', 'bntm_ajax_pm_add_comment');
add_action('wp_ajax_pm_upload_file', 'bntm_ajax_pm_upload_file');
add_action('wp_ajax_pm_delete_file', 'bntm_ajax_pm_delete_file');
add_action('wp_ajax_pm_get_project_data', 'bntm_ajax_pm_get_project_data');
add_action('wp_ajax_pm_upload_project_image', 'bntm_ajax_pm_upload_project_image');

/* ---------- MAIN DASHBOARD SHORTCODE ---------- */
function bntm_pm_shortcode_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Project Management dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    
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
    </style>

    <div class="bntm-inventory-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=projects" class="bntm-tab <?php echo $active_tab === 'projects' ? 'active' : ''; ?>">Projects</a>
            <a href="?tab=project_board" class="bntm-tab <?php echo $active_tab === 'project_board' ? 'active' : ''; ?>">Project Board</a>
            <a href="?tab=tasks" class="bntm-tab <?php echo $active_tab === 'tasks' ? 'active' : ''; ?>">My Tasks</a>
            <a href="?tab=kanban" class="bntm-tab <?php echo $active_tab === 'kanban' ? 'active' : ''; ?>">Kanban Board</a>
            <a href="?tab=calendar" class="bntm-tab <?php echo $active_tab === 'calendar' ? 'active' : ''; ?>">Calendar</a>
            <a href="?tab=team" class="bntm-tab <?php echo $active_tab === 'team' ? 'active' : ''; ?>">Team</a>
            <a href="?tab=reports" class="bntm-tab <?php echo $active_tab === 'reports' ? 'active' : ''; ?>">Reports</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo pm_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'projects'): ?>
                <?php echo pm_projects_tab($business_id); ?>
            <?php elseif ($active_tab === 'project_board'): ?>
                <?php echo pm_project_board_tab($business_id); ?>
            <?php elseif ($active_tab === 'tasks'): ?>
                <?php echo pm_tasks_tab($business_id); ?>
            <?php elseif ($active_tab === 'kanban'): ?>
                <?php echo pm_kanban_tab($business_id); ?>
            <?php elseif ($active_tab === 'calendar'): ?>
                <?php echo pm_calendar_tab($business_id); ?>
            <?php elseif ($active_tab === 'team'): ?>
                <?php echo pm_team_tab($business_id); ?>
            <?php elseif ($active_tab === 'reports'): ?>
                <?php echo pm_reports_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Project Management', $content);
}

/* ---------- OVERVIEW TAB ---------- */
function pm_overview_tab($business_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    
    // Statistics
    $total_projects = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $projects_table ",
        $business_id
    ));
    
    $active_projects = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $projects_table WHERE  status IN ('planning', 'in_progress')",
        $business_id
    ));
    
    $completed_projects = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $projects_table WHERE  status = 'completed'",
        $business_id
    ));
    
    $total_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table t 
        INNER JOIN $projects_table p ON t.project_id = p.id ",
        $business_id
    ));
    
    $completed_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table t 
        INNER JOIN $projects_table p ON t.project_id = p.id 
        WHERE t.status = 'done'",
        $business_id
    ));
    
    $overdue_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table t 
        INNER JOIN $projects_table p ON t.project_id = p.id 
        WHERE t.status != 'done' AND t.due_date < CURDATE()",
        $business_id
    ));
    
    $my_tasks_today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table t 
        INNER JOIN $projects_table p ON t.project_id = p.id 
        WHERE  t.assigned_to = %d AND t.due_date = CURDATE() AND t.status != 'done'",
        $business_id, $business_id
    ));
    
    $total_hours_logged = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(tl.hours) FROM $time_logs_table tl
        INNER JOIN $tasks_table t ON tl.task_id = t.id
        INNER JOIN $projects_table p ON t.project_id = p.id
        ",
        $business_id
    ));
    
    // Recent projects
    $recent_projects = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $projects_table  ORDER BY created_at DESC LIMIT 5",
        $business_id
    ));
    
    // Upcoming deadlines
    $upcoming_deadlines = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, p.name as project_name, p.color as project_color
        FROM $tasks_table t
        INNER JOIN $projects_table p ON t.project_id = p.id
        WHERE AND t.status != 'done' 
        AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY t.due_date ASC LIMIT 10",
        $business_id
    ));
    
    // Recent activity
    $activity_log_table = $wpdb->prefix . 'pm_activity_log';
    $recent_activity = $wpdb->get_results($wpdb->prepare(
        "SELECT a.*, u.display_name as user_name
        FROM $activity_log_table a
        LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
        ORDER BY a.created_at DESC LIMIT 15",
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Projects</h3>
            <p class="bntm-stat-number"><?php echo esc_html($total_projects); ?></p>
            <small><?php echo $active_projects; ?> active, <?php echo $completed_projects; ?> completed</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Tasks</h3>
            <p class="bntm-stat-number"><?php echo esc_html($total_tasks); ?></p>
            <small><?php echo $completed_tasks; ?> completed (<?php echo $total_tasks > 0 ? round(($completed_tasks/$total_tasks)*100) : 0; ?>%)</small>
        </div>
        <div class="bntm-stat-card">
            <h3>My Tasks Today</h3>
            <p class="bntm-stat-number" style="color: <?php echo $my_tasks_today > 0 ? '#dc2626' : '#059669'; ?>">
                <?php echo esc_html($my_tasks_today); ?>
            </p>
            <small>Due today</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Overdue Tasks</h3>
            <p class="bntm-stat-number" style="color: <?php echo $overdue_tasks > 0 ? '#991b1b' : '#059669'; ?>">
                <?php echo esc_html($overdue_tasks); ?>
            </p>
            <small>Need attention</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Hours Logged</h3>
            <p class="bntm-stat-number"><?php echo number_format($total_hours_logged ?: 0, 1); ?></p>
            <small>Total time tracked</small>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
        <!-- Recent Projects -->
        <div class="bntm-form-section">
            <h3>Recent Projects</h3>
            <?php if (empty($recent_projects)): ?>
                <p>No projects yet. <a href="?tab=projects">Create your first project</a></p>
            <?php else: ?>
                <?php foreach ($recent_projects as $project): ?>
                    <div style="padding: 15px; background: #f9fafb; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid <?php echo esc_attr($project->color); ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="margin: 0 0 5px 0;"><?php echo esc_html($project->name); ?></h4>
                                <small style="color: #6b7280;">
                                    <?php echo ucfirst($project->status); ?> • 
                                    <?php echo $project->progress; ?>% complete
                                    <?php if ($project->due_date): ?>
                                        • Due <?php echo date('M d, Y', strtotime($project->due_date)); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <a href="?tab=projects&project_id=<?php echo $project->id; ?>" class="bntm-btn-small">View</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="bntm-form-section">
            <h3>Upcoming Deadlines (7 days)</h3>
            <?php if (empty($upcoming_deadlines)): ?>
                <p>No upcoming deadlines.</p>
            <?php else: ?>
                <?php foreach ($upcoming_deadlines as $task): ?>
                    <div style="padding: 12px; background: #f9fafb; border-radius: 6px; margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <div style="font-weight: 500; color: #1f2937;"><?php echo esc_html($task->title); ?></div>
                                <small style="color: #6b7280;">
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?php echo esc_attr($task->project_color); ?>; margin-right: 5px;"></span>
                                    <?php echo esc_html($task->project_name); ?>
                                </small>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: <?php echo strtotime($task->due_date) < strtotime('today') ? '#dc2626' : '#6b7280'; ?>; font-weight: 500;">
                                    <?php echo date('M d', strtotime($task->due_date)); ?>
                                </div>
                                <div style="font-size: 11px; color: #9ca3af;">
                                    <?php 
                                    $days = floor((strtotime($task->due_date) - strtotime('today')) / 86400);
                                    if ($days == 0) echo 'Today';
                                    elseif ($days == 1) echo 'Tomorrow';
                                    else echo $days . ' days';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bntm-form-section">
        <h3>Recent Activity</h3>
        <?php if (empty($recent_activity)): ?>
            <p>No activity yet.</p>
        <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($recent_activity as $activity): ?>
                    <div style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                        <div style="display: flex; gap: 10px; align-items: start;">
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #e0f2fe; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;">
                                <?php echo strtoupper(substr($activity->user_name, 0, 1)); ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 14px; color: #1f2937;">
                                    <strong><?php echo esc_html($activity->user_name); ?></strong>
                                    <?php echo esc_html($activity->action); ?>
                                </div>
                                <?php if ($activity->details): ?>
                                    <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                                        <?php echo esc_html($activity->details); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                                    <?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')); ?> ago
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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

/* ---------- PROJECTS TAB ---------- */
function pm_projects_tab($business_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $team_table = $wpdb->prefix . 'pm_team_members';
    
    // Check if viewing a specific project
    if (isset($_GET['project_id'])) {
        return pm_project_details($business_id, intval($_GET['project_id']));
    }
    
    // Get projects where user is:
    // 1. The owner (business_id = current user)
    // 2. A team member (staff, manager, or viewer)
      $projects = $wpdb->get_results($wpdb->prepare(
          "SELECT DISTINCT p.*, 
          (SELECT COUNT(*) FROM $tasks_table WHERE project_id = p.id) as total_tasks,
          (SELECT COUNT(*) FROM $tasks_table WHERE project_id = p.id AND status IN ('done', 'closed')) as completed_tasks
          FROM $projects_table p
          LEFT JOIN $team_table tm ON p.id = tm.project_id AND tm.user_id = %d
          WHERE p.business_id = %d OR tm.user_id = %d
          ORDER BY p.created_at DESC",
          $business_id, $business_id, $business_id
      ));
      
      // Get current project count
       $current_projects = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(*) FROM $projects_table ",
           $business_id
       ));
       
       // Get project limit from custom limits (set by tier + addons)
       $custom_limits = get_option('bntm_custom_limits', []);
       $project_limit = isset($custom_limits['pm_projects']) ? intval($custom_limits['pm_projects']) : 0;
       
       // Fallback to table limits if custom limits not set
       if ($project_limit == 0) {
           $table_limits = get_option('bntm_table_limits', []);
           $project_limit = isset($table_limits[$projects_table]) ? intval($table_limits[$projects_table]) : 0;
       }
       
       $limit_text = $project_limit > 0 ? " ({$current_projects}/{$project_limit})" : " ({$current_projects})";
       $limit_reached = $project_limit > 0 && $current_projects >= $project_limit;
       
    
    $nonce = wp_create_nonce('pm_nonce');
    $upload_nonce = wp_create_nonce('pm_upload_image');
    
    // Sort projects: in_progress first, then by relevance
      usort($projects, function($a, $b) {
          // Priority order
          $order = ['in_progress' => 1, 'planning' => 2, 'on_hold' => 3, 'review' => 4, 'completed' => 5];
          $a_priority = isset($order[$a->status]) ? $order[$a->status] : 99;
          $b_priority = isset($order[$b->status]) ? $order[$b->status] : 99;
          
          if ($a_priority === $b_priority) {
              // If same status, sort by due date (upcoming first)
              if ($a->due_date && $b->due_date) {
                  return strtotime($a->due_date) - strtotime($b->due_date);
              }
              return $a->due_date ? -1 : 1;
          }
          return $a_priority - $b_priority;
      });

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Projects<?php echo $limit_text; ?></h3>
            <button id="open-add-project-modal" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled title="Project limit reached"' : ''; ?>>
                + Create New Project
            </button>
        </div>
        
        <?php if ($limit_reached): ?>
        <div style="background: #fee2e2; border: 1px solid #fca5a5; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <strong>⚠️ Project Limit Reached:</strong> Maximum of <?php echo number_format($project_limit); ?> projects allowed. 
            <a href="<?php echo get_permalink(get_page_by_path('settings')); ?>?tab=billing" style="color: #dc2626; text-decoration: underline; font-weight: 600;">Upgrade your plan</a> to create more projects.
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Project Modal -->
    <div id="add-project-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Create New Project</h3>
            <form id="pm-add-project-form" class="bntm-form">
                <!-- Project Image Upload -->
                <div class="bntm-form-group">
                    <label>Project Image</label>
                    
                    <div class="in-product-image-preview" id="project-image-preview" style="display: none;">
                        <img src="" alt="Project Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-project-image">✕</button>
                    </div>
                    
                    <div class="bntm-upload-area" id="project-upload-area">
                        <input type="file" id="project-image-upload" accept="image/*" style="display: none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="project-upload-btn">
                            Choose Image
                        </button>
                        <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    
                    <input type="hidden" id="project_image" name="project_image" value="">
                </div>
                
                <div class="bntm-form-group">
                    <label>Project Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Client Name</label>
                        <input type="text" name="client_name">
                    </div>
                    <div class="bntm-form-group">
                        <label>Project Color</label>
                        <input type="color" name="color" value="#3b82f6">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Budget</label>
                    <input type="number" name="budget" step="0.01" value="0" min="0">
                </div>
                
                <div class="bntm-form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="planning">Planning</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                        <option value="review">Review</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" placeholder="Project description..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Create Project</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div id="edit-project-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Project</h3>
            <form id="pm-edit-project-form" class="bntm-form">
                <input type="hidden" id="edit-project-id" name="project_id">
                
                <!-- Project Image Upload -->
                <div class="bntm-form-group">
                    <label>Project Image</label>
                    
                    <div class="in-product-image-preview" id="edit-project-image-preview" style="display: none;">
                        <img src="" alt="Project Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-edit-project-image">✕</button>
                    </div>
                    
                    <div class="bntm-upload-area" id="edit-project-upload-area">
                        <input type="file" id="edit-project-image-upload" accept="image/*" style="display: none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="edit-project-upload-btn">
                            Choose Image
                        </button>
                        <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    
                    <input type="hidden" id="edit-project_image" name="project_image" value="">
                </div>
                
                <div class="bntm-form-group">
                    <label>Project Name *</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Client Name</label>
                        <input type="text" id="edit-client-name" name="client_name">
                    </div>
                    <div class="bntm-form-group">
                        <label>Project Color</label>
                        <input type="color" id="edit-color" name="color" value="#3b82f6">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Start Date</label>
                        <input type="date" id="edit-start-date" name="start_date">
                    </div>
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" id="edit-due-date" name="due_date">
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Budget</label>
                    <input type="number" id="edit-budget" name="budget" step="0.01" min="0">
                </div>
                
                <div class="bntm-form-group">
                    <label>Status *</label>
                    <select id="edit-status" name="status" required>
                        <option value="planning">Planning</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                        <option value="review">Review</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Progress (%)</label>
                    <input type="number" id="edit-progress" name="progress" min="0" max="100" value="0">
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-description" name="description" rows="4"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Project</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Projects Grid -->
    <div class="bntm-form-section">
        <h3>All Projects (<?php echo count($projects); ?>)</h3>
        
        <!-- Filter Tabs -->
        <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="pm-filter-btn active" data-status="all">All</button>
            <button class="pm-filter-btn" data-status="planning">Planning</button>
            <button class="pm-filter-btn" data-status="in_progress">In Progress</button>
            <button class="pm-filter-btn" data-status="on_hold">On Hold</button>
            <button class="pm-filter-btn" data-status="review">Review</button>
            <button class="pm-filter-btn" data-status="completed">Completed</button>
        </div>
        
        <?php if (empty($projects)): ?>
            <p>No projects found. Create your first project above.</p>
        <?php else: ?>
            
            <!-- Projects Table (Replace the grid div) -->
            <div class="pm-projects-table">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden;">
                    <thead>
                        <tr style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Project</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Client</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Progress</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Start Date</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Due Date</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): 
                            $percentage = $project->total_tasks > 0 ? round(($project->completed_tasks/$project->total_tasks)*100) : 0;
                            $is_overdue = $project->due_date && strtotime($project->due_date) < time() && $project->status != 'completed';
                            $show_due = $project->due_date && strtotime($project->due_date) >= strtotime('today');
                        ?>
                            <tr class="pm-project-row" data-status="<?php echo esc_attr($project->status); ?>" style="border-bottom: 1px solid #f3f4f6; transition: background 0.2s;">
                                <td style="padding: 12px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php if ($project->image): ?>
                                            <img src="<?php echo esc_url($project->image); ?>" style="width: 40px; height: 40px; border-radius: 6px; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 40px; height: 40px; border-radius: 6px; background: <?php echo $project->color; ?>;"></div>
                                        <?php endif; ?>
                                        <div>
                                            <a href="?tab=projects&project_id=<?php echo $project->id; ?>" style="font-weight: 600; color: #1f2937; text-decoration: none;">
                                                <?php echo esc_html($project->name); ?>
                                            </a>
                                            <?php if ($project->description): ?>
                                                <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                                                    <?php echo esc_html(substr($project->description, 0, 50)) . (strlen($project->description) > 50 ? '...' : ''); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px; color: #4b5563;">
                                    <?php echo $project->client_name ? esc_html($project->client_name) : '-'; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span class="pm-status-badge pm-status-<?php echo $project->status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $project->status)); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="width: 120px;">
                                        <div style="display: flex; justify-content: space-between; font-size: 11px; color: #6b7280; margin-bottom: 4px;">
                                            <span><?php echo $project->completed_tasks; ?>/<?php echo $project->total_tasks; ?></span>
                                            <span><?php echo $percentage; ?>%</span>
                                        </div>
                                        <div style="background: #e5e7eb; height: 6px; border-radius: 3px; overflow: hidden;">
                                            <div style="background: <?php echo $project->color; ?>; height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 12px; font-size: 13px; color: #6b7280;">
                                    <?php echo $project->start_date ? date('M d, Y', strtotime($project->start_date)) : '-'; ?>
                                </td>
                                <td style="padding: 12px; font-size: 13px;">
                                    <?php if ($show_due): ?>
                                        <span style="color: <?php echo $is_overdue ? '#dc2626' : '#6b7280'; ?>;">
                                            <?php echo date('M d, Y', strtotime($project->due_date)); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button class="bntm-btn-small pm-edit-project" 
                                            data-id="<?php echo $project->id; ?>"
                                            data-name="<?php echo esc_attr($project->name); ?>"
                                            data-client="<?php echo esc_attr($project->client_name); ?>"
                                            data-color="<?php echo esc_attr($project->color); ?>"
                                            data-start="<?php echo esc_attr($project->start_date); ?>"
                                            data-due="<?php echo esc_attr($project->due_date); ?>"
                                            data-budget="<?php echo $project->budget; ?>"
                                            data-status="<?php echo esc_attr($project->status); ?>"
                                            data-progress="<?php echo $percentage; ?>"
                                            data-description="<?php echo esc_attr($project->description); ?>"
                                            data-image="<?php echo esc_attr($project->image); ?>">Edit</button>
                                        <button class="bntm-btn-small bntm-btn-danger pm-delete-project" data-id="<?php echo $project->id; ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <style>
    
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
    .in-product-image-preview {
        position: relative;
        display: inline-block;
        margin-bottom: 15px;
        padding: 10px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }
    .in-product-image-preview img {
        max-width: 200px;
        max-height: 200px;
        display: block;
    }
    .bntm-btn-remove-logo {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .bntm-btn-remove-logo:hover {
        background: #dc2626;
    }
    .pm-project-row:hover {
          background: #f9fafb;
      }
      .pm-projects-table {
          overflow-x: auto;
      }
    .pm-filter-btn {
        padding: 8px 16px;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .pm-filter-btn:hover {
        background: #f3f4f6;
    }
    .pm-filter-btn.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    .pm-projects-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    .pm-project-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.3s;
    }
    .pm-project-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .pm-project-image {
        height: 140px;
        background-size: cover;
        background-position: center;
        background-color: #e5e7eb;
    }
    .pm-project-content {
        padding: 20px;
    }
    .pm-project-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
        gap: 10px;
    }
    .pm-project-header h4 {
        margin: 0;
        font-size: 18px;
        color: #1f2937;
        flex: 1;
    }
    .pm-status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .pm-status-planning {
        background: #fef3c7;
        color: #92400e;
    }
    .pm-status-in_progress {
        background: #dbeafe;
        color: #1e40af;
    }
    .pm-status-on_hold {
        background: #fee2e2;
        color: #991b1b;
    }
    .pm-status-review {
        background: #e9d5ff;
        color: #6b21a8;
    }
    .pm-status-completed {
        background: #d1fae5;
        color: #065f46;
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
        var uploadNonce = '<?php echo $upload_nonce; ?>';
        
        // ========== IMAGE UPLOAD SETUP ==========
        function setupImageUpload(prefix) {
            const uploadArea = document.getElementById(prefix + 'project-upload-area');
            const uploadBtn = document.getElementById(prefix + 'project-upload-btn');
            const fileInput = document.getElementById(prefix + 'project-image-upload');
            const imagePreview = document.getElementById(prefix + 'project-image-preview');
            const removeBtn = document.getElementById('remove-' + prefix + 'project-image');
            const hiddenInput = document.getElementById(prefix + 'project_image');
            
            if (!uploadBtn) return;
            
            uploadBtn.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    uploadProjectImage(this.files[0], prefix);
                }
            });
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    uploadProjectImage(e.dataTransfer.files[0], prefix);
                }
            });
            
            removeBtn.addEventListener('click', function() {
                imagePreview.style.display = 'none';
                uploadArea.style.display = 'block';
                hiddenInput.value = '';
            });
        }
        
        function uploadProjectImage(file, prefix) {
            if (!file.type.match('image.*')) {
                alert('Please select an image file');
                return;
            }
            
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'pm_upload_project_image');
            formData.append('image', file);
            formData.append('_ajax_nonce', uploadNonce);
            
            const uploadBtn = document.getElementById(prefix + 'project-upload-btn');
            const uploadArea = document.getElementById(prefix + 'project-upload-area');
            const imagePreview = document.getElementById(prefix + 'project-image-preview');
            const hiddenInput = document.getElementById(prefix + 'project_image');
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Choose Image';
                
                if (json.success) {
                    imagePreview.querySelector('img').src = json.data.url;
                    imagePreview.style.display = 'inline-block';
                    uploadArea.style.display = 'none';
                    hiddenInput.value = json.data.url;
                } else {
                    alert('Upload failed: ' + json.data);
                }
            })
            .catch(err => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Choose Image';
                alert('Upload error: ' + err.message);
            });
        }
        
        setupImageUpload('');
        setupImageUpload('edit-');
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-project-modal');
        const editModal = document.getElementById('edit-project-modal');
        
        document.getElementById('open-add-project-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== FILTER BUTTONS ==========
        document.querySelectorAll('.pm-filter-btn').forEach(btn => {
             btn.addEventListener('click', function() {
                 document.querySelectorAll('.pm-filter-btn').forEach(b => b.classList.remove('active'));
                 this.classList.add('active');
                 
                 const status = this.getAttribute('data-status');
                 document.querySelectorAll('.pm-project-row').forEach(row => {
                     if (status === 'all' || row.getAttribute('data-status') === status) {
                         row.style.display = 'table-row';
                     } else {
                         row.style.display = 'none';
                     }
                 });
             });
         });
        
        // ========== ADD PROJECT FORM ==========
        document.getElementById('pm-add-project-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_project');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
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
                    btn.textContent = 'Create Project';
                }
            });
        });
        
        // ========== EDIT PROJECT ==========
        document.querySelectorAll('.pm-edit-project').forEach(btn => {
            btn.addEventListener('click', function() {
                const data = this.dataset;
                
                document.getElementById('edit-project-id').value = data.id;
                document.getElementById('edit-name').value = data.name;
                document.getElementById('edit-client-name').value = data.client || '';
                document.getElementById('edit-color').value = data.color;
                document.getElementById('edit-start-date').value = data.start;
                document.getElementById('edit-due-date').value = data.due;
                document.getElementById('edit-budget').value = data.budget;
                document.getElementById('edit-status').value = data.status;
                document.getElementById('edit-progress').value = data.progress;
                document.getElementById('edit-description').value = data.description;
                
                // Load image
                const editImagePreview = document.getElementById('edit-project-image-preview');
                const editUploadArea = document.getElementById('edit-project-upload-area');
                const editImageInput = document.getElementById('edit-project_image');
                
                if (data.image) {
                    editImagePreview.querySelector('img').src = data.image;
                    editImagePreview.style.display = 'inline-block';
                    editUploadArea.style.display = 'none';
                    editImageInput.value = data.image;
                } else {
                    editImagePreview.style.display = 'none';
                    editUploadArea.style.display = 'block';
                    editImageInput.value = '';
                }
                
                editModal.style.display = 'flex';
            });
        });
        
        document.getElementById('pm-edit-project-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_update_project');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
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
                    btn.textContent = 'Update Project';
                }
            });
        });
        
        // ========== DELETE PROJECT ==========
        document.querySelectorAll('.pm-delete-project').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this project? All tasks and data will be removed.')) return;
                
                const projectId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'pm_delete_project');
                formData.append('project_id', projectId);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
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

/* ---------- PROJECT AJAX HANDLERS ---------- */
function bntm_ajax_pm_add_project() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_projects';
    $business_id = get_current_user_id();

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'name' => sanitize_text_field($_POST['name']),
        'client_name' => sanitize_text_field($_POST['client_name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'due_date' => sanitize_text_field($_POST['due_date']),
        'budget' => floatval($_POST['budget']),
        'status' => sanitize_text_field($_POST['status']),
        'color' => sanitize_text_field($_POST['color']),
        'image' => isset($_POST['project_image']) ? esc_url_raw($_POST['project_image']) : ''
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        $project_id = $wpdb->insert_id;
        
        // Log activity
        pm_log_activity($business_id, $project_id, null, 'created project', $data['name']);
        
        wp_send_json_success(['message' => 'Project created successfully!', 'project_id' => $project_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create project.']);
    }
}

function bntm_ajax_pm_update_project() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_projects';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);

    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'client_name' => sanitize_text_field($_POST['client_name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'due_date' => sanitize_text_field($_POST['due_date']),
        'budget' => floatval($_POST['budget']),
        'status' => sanitize_text_field($_POST['status']),
        'progress' => intval($_POST['progress']),
        'color' => sanitize_text_field($_POST['color']),
        'image' => isset($_POST['project_image']) ? esc_url_raw($_POST['project_image']) : ''
    ];

    $result = $wpdb->update($table, $data, [
        'id' => $project_id
    ]);

    if ($result !== false) {
        pm_log_activity($business_id, $project_id, null, 'updated project', $data['name']);
        wp_send_json_success(['message' => 'Project updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update project.']);
    }
}

function bntm_ajax_pm_delete_project() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);

    // Delete related tasks first
    $wpdb->delete($tasks_table, ['project_id' => $project_id]);
    
    // Delete project
    $result = $wpdb->delete($projects_table, [
        'id' => $project_id
    ]);

    if ($result) {
        wp_send_json_success(['message' => 'Project deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete project.']);
    }
}

function bntm_ajax_pm_upload_project_image() {
    check_ajax_referer('pm_upload_image', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
    }

    if (!isset($_FILES['image'])) {
        wp_send_json_error('No file uploaded');
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $file = $_FILES['image'];
    $upload = wp_handle_upload($file, ['test_form' => false]);

    if (isset($upload['error'])) {
        wp_send_json_error($upload['error']);
    }

    wp_send_json_success(['url' => $upload['url']]);
}

/* ---------- PROJECT DETAILS VIEW ---------- */
function pm_project_details($business_id, $project_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $team_table = $wpdb->prefix . 'pm_team_members';
    $files_table = $wpdb->prefix . 'pm_files';
    
    $is_wp_admin = current_user_can('manage_options');
    // Check if current user is a team member
   $team_member = $wpdb->get_row($wpdb->prepare(
       "SELECT * FROM $team_table WHERE project_id = %d AND user_id = %d",
       $project_id, $business_id
   ));
   
   
   $creator = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $projects_table WHERE business_id = %d ",
        $business_id
    ));
    
   if (!$team_member) {
    if (!$creator) {
        return '<div class="bntm-notice bntm-notice-error">You do not have access to this project.</div>';
    }
}

   $is_pm = ($team_member->role === 'manager') || $is_wp_admin ||$creator;
   $is_staff = ($team_member->role === 'staff');
   $is_viewer = ($team_member->role === 'viewer');
   
    $project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $projects_table WHERE id = %d ",
        $project_id, $business_id
    ));
    
    if (!$project) {
        return '<div class="bntm-notice bntm-notice-error">Project not found.</div>';
    }
    
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, u.display_name as assigned_name 
        FROM $tasks_table t
        LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
        WHERE t.project_id = %d
        ORDER BY t.sort_order ASC, t.created_at DESC",
        $project_id
    ));
    
    $milestones = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*,
        (SELECT COUNT(*) FROM $tasks_table WHERE milestone_id = m.id) as total_tasks,
        (SELECT COUNT(*) FROM $tasks_table WHERE milestone_id = m.id AND status = 'done') as completed_tasks
        FROM $milestones_table m
        WHERE m.project_id = %d
        ORDER BY m.sort_order ASC, m.due_date ASC",
        $project_id
    ));
    
    $team_members = $wpdb->get_results($wpdb->prepare(
        "SELECT tm.*, u.display_name, u.user_email
        FROM $team_table tm
        LEFT JOIN {$wpdb->users} u ON tm.user_id = u.ID
        WHERE tm.project_id = %d",
        $project_id
    ));
    
    $files = $wpdb->get_results($wpdb->prepare(
        "SELECT f.*, u.display_name as uploaded_by_name
        FROM $files_table f
        LEFT JOIN {$wpdb->users} u ON f.uploaded_by = u.ID
        WHERE f.project_id = %d
        ORDER BY f.uploaded_at DESC",
        $project_id
    ));
    
    // Get all users for assignment dropdown
    $all_users = get_users([
       'fields' => ['ID', 'display_name'],
       'role__not_in' => ['administrator']
   ]);

    
    $nonce = wp_create_nonce('pm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var projectId = <?php echo $project_id; ?>;
    </script>
    
    <div style="margin-bottom: 20px;">
        <a href="?tab=projects" class="bntm-btn-secondary">← Back to Projects</a>
    </div>
    
    <!-- Project Header -->
    <div class="pm-project-header-detail" style="background: linear-gradient(135deg, <?php echo $project->color; ?> 0%, <?php echo $project->color; ?>88 100%); padding: 30px; border-radius: 8px; color: white; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="flex: 1;">
                <h2 style="margin: 0 0 10px 0; color: white;"><?php echo esc_html($project->name); ?></h2>
                <?php if ($project->client_name): ?>
                    <p style="margin: 0 0 10px 0; opacity: 0.9;">Client: <?php echo esc_html($project->client_name); ?></p>
                <?php endif; ?>
                <?php if ($project->description): ?>
                    <p style="margin: 10px 0; opacity: 0.9;"><?php echo esc_html($project->description); ?></p>
                <?php endif; ?>
                <div style="display: flex; gap: 20px; margin-top: 15px; flex-wrap: wrap;">
                    <div>
                        <small style="opacity: 0.8;">Status</small><br>
                        <strong><?php echo ucfirst(str_replace('_', ' ', $project->status)); ?></strong>
                    </div>
                    <div>
                        <small style="opacity: 0.8;">Progress</small><br>
                        <strong><?php echo $project->progress; ?>%</strong>
                    </div>
                    <?php if ($project->budget > 0): ?>
                    <div>
                        <small style="opacity: 0.8;">Budget</small><br>
                        <strong>₱<?php echo number_format($project->budget, 2); ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($project->due_date): ?>
                    <div>
                        <small style="opacity: 0.8;">Due Date</small><br>
                        <strong><?php echo date('M d, Y', strtotime($project->due_date)); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($project->image): ?>
                <div style="margin-left: 20px;">
                    <img src="<?php echo esc_url($project->image); ?>" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 3px solid rgba(255,255,255,0.3);">
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Project Tabs -->
    <div class="bntm-tabs" style="margin-bottom: 20px;">
        <button class="bntm-tab active" data-pm-tab="tasks">Tasks</button>
        <button class="bntm-tab" data-pm-tab="milestones">Milestones</button>
        <button class="bntm-tab" data-pm-tab="team">Team</button>
        <button class="bntm-tab" data-pm-tab="files">Files</button>
        <button class="bntm-tab" data-pm-tab="timeline">Timeline</button>
    </div>
    
    <!-- Tasks Tab -->
    <div class="pm-tab-content" id="pm-content-tasks">
        <div class="bntm-form-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Tasks (<?php echo count($tasks); ?>)</h3>
                <?php if ($is_pm): ?>
                   <button id="open-add-task-modal" class="bntm-btn-primary">+ Add Task</button>
               <?php endif; ?>
            </div>
            
            <!-- Task Status Filters -->
            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="pm-task-filter-btn active" data-status="todo">To Do</button>
                <button class="pm-task-filter-btn" data-status="in_progress">In Progress</button>
                <button class="pm-task-filter-btn" data-status="done">Done</button>
                <button class="pm-task-filter-btn" data-status="on_hold">On Hold</button>
                <button class="pm-task-filter-btn" data-status="closed">Closed</button>
                <button class="pm-task-filter-btn" data-status="all">All Tasks</button>
            </div>
            
            <?php if (empty($tasks)): ?>
                <p>No tasks yet. Add your first task above.</p>
            <?php else: ?>
                <div class="pm-tasks-list">
                  <!-- Task Items -->
                  <?php foreach ($tasks as $task): ?>
                      <div class="pm-task-item" data-task-status="<?php echo esc_attr($task->status); ?>" data-task-id="<?php echo $task->id; ?>">
                          <div style="display: flex; gap: 15px; align-items: start;">
                              <!-- Checkbox - Only for assigned staff -->
                              <?php if ($is_staff || $is_pm): ?>
                                  <input type="checkbox" class="pm-task-checkbox" 
                                         data-id="<?php echo $task->id; ?>"
                                         <?php echo $task->status === 'done' ? 'checked' : ''; ?>>
                              <?php else: ?>
                                  <div style="width: 20px;"></div>
                              <?php endif; ?>
                              
                              <div style="flex: 1;">
                                  <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                                      <h4 style="margin: 0; cursor: pointer; color: #3b82f6;" class="pm-task-title" data-task-id="<?php echo $task->id; ?>" 
                                          <?php echo $task->status === 'done' ? 'style="text-decoration: line-through; opacity: 0.6; cursor: pointer;"' : 'style="cursor: pointer;"'; ?>>
                                          <?php echo esc_html($task->title); ?>
                                      </h4>
                                      <span class="pm-priority-badge pm-priority-<?php echo $task->priority; ?>">
                                          <?php echo ucfirst($task->priority); ?>
                                      </span>
                                  </div>
                                  
                                  <?php if ($task->description): ?>
                                      <p style="color: #6b7280; font-size: 14px; margin: 5px 0;">
                                          <?php echo esc_html($task->description); ?>
                                      </p>
                                  <?php endif; ?>
                                  
                                  <div style="display: flex; gap: 15px; align-items: center; font-size: 13px; color: #6b7280; margin-top: 8px;">
                                      <?php if ($task->assigned_name): ?>
                                          <span>👤 <?php echo esc_html($task->assigned_name); ?></span>
                                      <?php endif; ?>
                                      <?php if ($task->due_date): ?>
                                          <span style="color: <?php echo strtotime($task->due_date) < time() && $task->status != 'done' ? '#dc2626' : '#6b7280'; ?>;">
                                              📅 <?php echo date('M d, Y', strtotime($task->due_date)); ?>
                                          </span>
                                      <?php endif; ?>
                                      <?php if ($task->estimated_hours > 0): ?>
                                          <span>⏱️ <?php echo $task->estimated_hours; ?>h estimated</span>
                                      <?php endif; ?>
                                      <?php if ($task->actual_hours > 0): ?>
                                          <span>✅ <?php echo $task->actual_hours; ?>h logged</span>
                                      <?php endif; ?>
                                  </div>
                              </div>
                              
                              <!-- Actions - Only PM can edit/delete -->
                              <?php if ($is_pm): ?>
                                  <div style="display: flex; gap: 5px;">
                                      <button class="bntm-btn-small pm-view-task" data-id="<?php echo $task->id; ?>">View</button>
                                      <button class="bntm-btn-small bntm-btn-danger pm-delete-task" data-id="<?php echo $task->id; ?>">Delete</button>
                                  </div>
                              <?php else: ?>
                                  <button class="bntm-btn-small pm-view-task" data-id="<?php echo $task->id; ?>">View</button>
                              <?php endif; ?>
                          </div>
                      </div>
                  <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Milestones Tab -->
      <div class="pm-tab-content" id="pm-content-milestones" style="display: none;">
          <div class="bntm-form-section">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                  <h3>Milestones (<?php echo count($milestones); ?>)</h3>
                  <?php if ($is_pm): ?>
                      <button id="open-add-milestone-modal" class="bntm-btn-primary">+ Add Milestone</button>
                  <?php endif; ?>
              </div>
              
              <?php if (empty($milestones)): ?>
                  <p>No milestones yet.</p>
              <?php else: ?>
                  <div class="pm-milestones-list">
                      <?php foreach ($milestones as $milestone): ?>
                          <?php 
                          $milestone_progress = $milestone->total_tasks > 0 ? 
                              round(($milestone->completed_tasks / $milestone->total_tasks) * 100) : 0;
                          ?>
                          <div class="pm-milestone-item">
                              <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                  <div style="flex: 1;">
                                      <h4 style="margin: 0 0 5px 0;">🎯 <?php echo esc_html($milestone->name); ?></h4>
                                      <?php if ($milestone->description): ?>
                                          <p style="color: #6b7280; font-size: 14px; margin: 0;">
                                              <?php echo esc_html($milestone->description); ?>
                                          </p>
                                      <?php endif; ?>
                                  </div>
                                  <div style="text-align: right;">
                                      <span class="pm-status-badge pm-status-<?php echo $milestone->status; ?>">
                                          <?php echo ucfirst($milestone->status); ?>
                                      </span>
                                      <?php if ($milestone->due_date): ?>
                                          <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">
                                              Due: <?php echo date('M d, Y', strtotime($milestone->due_date)); ?>
                                          </div>
                                      <?php endif; ?>
                                  </div>
                              </div>
                              
                              <div style="margin: 10px 0;">
                                  <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                      <span style="font-size: 12px; color: #6b7280;">Progress</span>
                                      <span style="font-size: 12px; font-weight: 600;">
                                          <?php echo $milestone->completed_tasks; ?>/<?php echo $milestone->total_tasks; ?> tasks
                                      </span>
                                  </div>
                                  <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                      <div style="background: <?php echo $project->color; ?>; height: 100%; width: <?php echo $milestone_progress; ?>%;"></div>
                                  </div>
                                  <div style="text-align: right; font-size: 11px; color: #9ca3af; margin-top: 2px;">
                                      <?php echo $milestone_progress; ?>%
                                  </div>
                              </div>
                              
                              <div style="display: flex; gap: 10px; margin-top: 10px;">
                                  <?php if ($is_pm): ?>
                                      <button class="bntm-btn-small pm-edit-milestone" 
                                          data-id="<?php echo $milestone->id; ?>"
                                          data-name="<?php echo esc_attr($milestone->name); ?>"
                                          data-description="<?php echo esc_attr($milestone->description); ?>"
                                          data-due="<?php echo esc_attr($milestone->due_date); ?>"
                                          data-status="<?php echo esc_attr($milestone->status); ?>">Edit</button>
                                      <button class="bntm-btn-small bntm-btn-danger pm-delete-milestone" data-id="<?php echo $milestone->id; ?>">Delete</button>
                                  <?php else: ?>
                                      <?php if ($milestone_progress == 100 && $milestone->status !== 'completed'): ?>
                                          <button class="bntm-btn-small pm-complete-milestone" data-id="<?php echo $milestone->id; ?>">Mark Complete</button>
                                      <?php endif; ?>
                                  <?php endif; ?>
                              </div>
                          </div>
                      <?php endforeach; ?>
                  </div>
              <?php endif; ?>
          </div>
      </div>
    
    <!-- Team Tab -->
    <div class="pm-tab-content" id="pm-content-team" style="display: none;">
        <div class="bntm-form-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Team Members (<?php echo count($team_members); ?>)</h3>
                <button id="open-add-team-modal" class="bntm-btn-primary">+ Add Team Member</button>
            </div>
            
            <?php if (empty($team_members)): ?>
                <p>No team members assigned yet.</p>
            <?php else: ?>
                <div class="pm-team-grid">
                    <?php foreach ($team_members as $member): ?>
                        <div class="pm-team-card">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: <?php echo $project->color; ?>; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 600;">
                                    <?php echo strtoupper(substr($member->display_name, 0, 1)); ?>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 5px 0;"><?php echo esc_html($member->display_name); ?></h4>
                                    <p style="margin: 0; color: #6b7280; font-size: 13px;">
                                        <?php echo esc_html($member->user_email); ?>
                                    </p>
                                    <span style="font-size: 12px; color: #6b7280;">
                                        Role: <?php echo ucfirst($member->role); ?>
                                    </span>
                                </div>
                                <button class="bntm-btn-small bntm-btn-danger pm-remove-team-member" data-id="<?php echo $member->id; ?>">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Files Tab -->
    <div class="pm-tab-content" id="pm-content-files" style="display: none;">
        <div class="bntm-form-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Project Files (<?php echo count($files); ?>)</h3>
                <button id="open-upload-file-modal" class="bntm-btn-primary">+ Upload File</button>
            </div>
            
            <?php if (empty($files)): ?>
                <p>No files uploaded yet.</p>
            <?php else: ?>
                <table class="bntm-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td><?php echo esc_html($file->filename); ?></td>
                                <td><?php echo esc_html($file->file_type); ?></td>
                                <td><?php echo size_format($file->file_size); ?></td>
                                <td><?php echo esc_html($file->uploaded_by_name); ?></td>
                                <td><?php echo date('M d, Y', strtotime($file->uploaded_at)); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($file->file_url); ?>" target="_blank" class="bntm-btn-small">Download</a>
                                    <button class="bntm-btn-small bntm-btn-danger pm-delete-file" data-id="<?php echo $file->id; ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Timeline Tab -->
    <div class="pm-tab-content" id="pm-content-timeline" style="display: none;">
        <div class="bntm-form-section">
            <h3>Project Timeline</h3>
            <div class="pm-gantt-placeholder" style="padding: 60px; background: #f9fafb; border-radius: 8px; text-align: center; color: #6b7280;">
                <p style="font-size: 18px; margin: 0;">📊 Gantt Chart View</p>
                <p style="margin: 10px 0 0 0;">Timeline visualization coming soon</p>
            </div>
        </div>
    </div>
    
    <!-- Add Task Modal -->
    <div id="add-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Task</h3>
            <form id="pm-add-task-form" class="bntm-form">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <div class="bntm-form-group">
                    <label>Task Title *</label>
                    <input type="text" name="title" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Priority *</label>
                        <select name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                      <label>Status *</label>
                      <select name="status" required>
                          <option value="todo" selected>To Do</option>
                          <option value="in_progress">In Progress</option>
                          <option value="on_hold">On Hold</option>
                          <option value="done">Done</option>
                          <option value="closed">Closed</option>
                      </select>
                  </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Assign To</label>
                        <select name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Estimated Hours</label>
                        <input type="number" name="estimated_hours" step="0.5" min="0" value="0">
                    </div>
                    <div class="bntm-form-group">
                        <label>Milestone</label>
                        <select name="milestone_id">
                            <option value="">No Milestone</option>
                            <?php foreach ($milestones as $milestone): ?>
                                <option value="<?php echo $milestone->id; ?>"><?php echo esc_html($milestone->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Tags (comma-separated)</label>
                    <input type="text" name="tags" placeholder="frontend, urgent, bug">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Task</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Milestone Modal -->
    <div id="add-milestone-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Milestone</h3>
            <form id="pm-add-milestone-form" class="bntm-form">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <div class="bntm-form-group">
                    <label>Milestone Name *</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date">
                    </div>
                    <div class="bntm-form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Milestone</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Milestone Modal -->
   <div id="edit-milestone-modal" class="in-modal" style="display: none;">
       <div class="in-modal-content">
           <h3>Edit Milestone</h3>
           <form id="pm-edit-milestone-form" class="bntm-form">
               <input type="hidden" id="edit-milestone-id" name="milestone_id">
               <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
               
               <div class="bntm-form-group">
                   <label>Milestone Name *</label>
                   <input type="text" id="edit-milestone-name" name="name" required>
               </div>
               
               <div class="bntm-form-group">
                   <label>Description</label>
                   <textarea id="edit-milestone-description" name="description" rows="3"></textarea>
               </div>
               
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Due Date</label>
                       <input type="date" id="edit-milestone-due" name="due_date">
                   </div>
                   <div class="bntm-form-group">
                       <label>Status</label>
                       <select id="edit-milestone-status" name="status">
                           <option value="pending">Pending</option>
                           <option value="in_progress">In Progress</option>
                           <option value="completed">Completed</option>
                       </select>
                   </div>
               </div>
               
               <div style="display: flex; gap: 10px; margin-top: 20px;">
                   <button type="submit" class="bntm-btn-primary">Update Milestone</button>
                   <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
               </div>
           </form>
       </div>
   </div>
    <!-- Add Team Member Modal -->
    <div id="add-team-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add Team Member</h3>
            <form id="pm-add-team-form" class="bntm-form">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <div class="bntm-form-group">
                    <label>Select User *</label>
                    <select name="user_id" required>
                        <option value="">Choose a user...</option>
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="bntm-form-group">
                    <label>Role *</label>
                    <select name="role" required>
                        <option value="staff">Staff</option>
                        <option value="manager">Project Manager</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Member</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Upload File Modal -->
    <div id="upload-file-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Upload File</h3>
            <form id="pm-upload-file-form" class="bntm-form">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <div class="bntm-form-group">
                    <label>Select File *</label>
                    <input type="file" id="pm-file-input" required>
                </div>
                
                <div id="pm-upload-progress" style="display: none; margin: 15px 0;">
                    <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div id="pm-upload-progress-bar" style="background: <?php echo $project->color; ?>; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p style="text-align: center; margin: 5px 0; font-size: 12px; color: #6b7280;">
                        <span id="pm-upload-status">Uploading...</span>
                    </p>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Upload</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- View/Edit Task Modal -->
    <div id="view-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content" style="max-width: 900px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="task-modal-title">Task Details</h3>
                <button class="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
            </div>
            
            <div id="task-details-content">
                <!-- Task details will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Edit Task Modal -->
    <div id="edit-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Task</h3>
            <form id="pm-edit-task-form" class="bntm-form">
                <input type="hidden" id="edit-task-id" name="task_id">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <div class="bntm-form-group">
                    <label>Task Title *</label>
                    <input type="text" id="edit-task-title" name="title" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-task-description" name="description" rows="3"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Priority *</label>
                        <select id="edit-task-priority" name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                         <label>Status *</label>
                         <select id="edit-task-status" name="status" required>
                             <option value="todo">To Do</option>
                             <option value="in_progress">In Progress</option>
                             <option value="on_hold">On Hold</option>
                             <option value="done">Done</option>
                             <option value="closed">Closed</option>
                         </select>
                     </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Assign To</label>
                        <select id="edit-task-assigned" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" id="edit-task-due-date" name="due_date">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Estimated Hours</label>
                        <input type="number" id="edit-task-estimated-hours" name="estimated_hours" step="0.5" min="0">
                    </div>
                    <div class="bntm-form-group">
                        <label>Milestone</label>
                        <select id="edit-task-milestone" name="milestone_id">
                            <option value="">No Milestone</option>
                            <?php foreach ($milestones as $milestone): ?>
                                <option value="<?php echo $milestone->id; ?>"><?php echo esc_html($milestone->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Tags (comma-separated)</label>
                    <input type="text" id="edit-task-tags" name="tags" placeholder="frontend, urgent, bug">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Task</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Time Modal -->
    <div id="log-time-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Log Time</h3>
            <form id="pm-log-time-form" class="bntm-form">
                <input type="hidden" id="log-time-task-id" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Hours Worked *</label>
                    <input type="number" name="hours" step="0.5" min="0.5" required placeholder="e.g., 2.5">
                </div>
                
                <div class="bntm-form-group">
                    <label>Date *</label>
                    <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="What did you work on?"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Log Time</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Comment Modal -->
    <div id="add-comment-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add Comment</h3>
            <form id="pm-add-comment-form" class="bntm-form">
                <input type="hidden" id="comment-task-id" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Comment *</label>
                    <textarea name="comment" rows="4" required placeholder="Write your comment..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Comment</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .pm-task-item {
        padding: 15px;
        background: #f9fafb;
        border-radius: 6px;
        margin-bottom: 10px;
        border-left: 4px solid <?php echo $project->color; ?>;
        transition: all 0.2s;
    }
    .pm-task-item:hover {
        background: #f3f4f6;
    }
    .pm-task-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
        margin-top: 2px;
    }
    .pm-priority-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .pm-priority-low {
        background: #dbeafe;
        color: #1e40af;
    }
    .pm-priority-medium {
        background: #fef3c7;
        color: #92400e;
    }
    .pm-priority-high {
        background: #fee2e2;
        color: #991b1b;
    }
    .pm-milestone-item {
        padding: 20px;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 15px;
        border: 1px solid #e5e7eb;
    }
    .pm-team-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }
    .pm-team-card {
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }
    .pm-task-filter-btn {
        padding: 8px 16px;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .pm-task-filter-btn:hover {
        background: #f3f4f6;
    }
    .pm-task-filter-btn.active {
        background: <?php echo $project->color; ?>;
        color: white;
        border-color: <?php echo $project->color; ?>;
    }
    </style>
    
    <style>
    /* Add these additional styles */
    .pm-task-detail-section {
        background: #f9fafb;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
    }
    .pm-task-detail-row {
        display: flex;
        gap: 30px;
        margin-bottom: 10px;
    }
    .pm-task-detail-item {
        flex: 1;
    }
    .pm-task-detail-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 5px;
    }
    .pm-task-detail-value {
        font-size: 14px;
        color: #1f2937;
        font-weight: 600;
    }
    .pm-comment-item {
        padding: 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 10px;
        border-left: 3px solid #3b82f6;
    }
    .pm-time-log-item {
        padding: 10px;
        background: white;
        border-radius: 6px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .pm-status-on_hold {
          background: #fed7aa;
          color: #ea580c;
      }
      .pm-status-closed {
          background: #e5e7eb;
          color: #374151;
      }
    </style>
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== TAB SWITCHING ==========
        document.querySelectorAll('[data-pm-tab]').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('.bntm-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                const tabName = this.getAttribute('data-pm-tab');
                document.querySelectorAll('.pm-tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                document.getElementById('pm-content-' + tabName).style.display = 'block';
            });
        });
        
        // ========== MODAL CONTROLS ==========
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        <?php if ($is_pm): ?>
           document.getElementById('open-add-task-modal').addEventListener('click', () => {
               document.getElementById('add-task-modal').style.display = 'flex';
           });
           document.getElementById('open-add-milestone-modal').addEventListener('click', () => {
               document.getElementById('add-milestone-modal').style.display = 'flex';
           });
           
           document.getElementById('open-add-team-modal').addEventListener('click', () => {
               document.getElementById('add-team-modal').style.display = 'flex';
           });
         <?php endif; ?>
        
        
        
        
        document.getElementById('open-upload-file-modal').addEventListener('click', () => {
            document.getElementById('upload-file-modal').style.display = 'flex';
        });
        
        // ========== TASK STATUS FILTERS ==========
        document.querySelectorAll('.pm-task-filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.pm-task-filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const status = this.getAttribute('data-status');
                document.querySelectorAll('.pm-task-item').forEach(task => {
                    if (status === 'all' || task.getAttribute('data-task-status') === status) {
                        task.style.display = 'block';
                    } else {
                        task.style.display = 'none';
                    }
                });
            });
        });
        // ========== INITIALIZE FILTER TO "TO DO" ON PAGE LOAD ==========
         document.addEventListener('DOMContentLoaded', function() {
             // Filter tasks to show only "To Do" by default
             document.querySelectorAll('.pm-task-item').forEach(task => {
                 const status = task.getAttribute('data-task-status');
                 if (status === 'todo') {
                     task.style.display = 'block';
                 } else {
                     task.style.display = 'none';
                 }
             });
         });
        // ========== ADD TASK ==========
        document.getElementById('pm-add-task-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_task');
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
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Add Task';
                }
            });
        });
        
        // ========== TASK CHECKBOX ==========
        document.querySelectorAll('.pm-task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskId = this.getAttribute('data-id');
                const newStatus = this.checked ? 'done' : 'todo';
                
                const formData = new FormData();
                formData.append('action', 'pm_update_task_status');
                formData.append('task_id', taskId);
                formData.append('status', newStatus);
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
                        alert('Failed to update task status');
                        this.checked = !this.checked;
                    }
                });
            });
        });
        
        // ========== DELETE TASK ==========
        document.querySelectorAll('.pm-delete-task').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this task?')) return;
                
                const taskId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'pm_delete_task');
                formData.append('task_id', taskId);
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
         // ========== VIEW TASK ==========
        document.querySelectorAll('.pm-view-task').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.getAttribute('data-id');
                loadTaskDetails(taskId);
            });
        });
        
        function loadTaskDetails(taskId) {
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    displayTaskDetails(json.data);
                    document.getElementById('view-task-modal').style.display = 'flex';
                } else {
                    alert(json.data.message || 'Failed to load task details');
                }
            })
            .catch(err => {
                alert('Error loading task: ' + err.message);
            });
        }
        
        function displayTaskDetails(data) {
            const task = data.task;
            const comments = data.comments || [];
            const timeLogs = data.time_logs || [];
            
            const statusColors = {
                'todo': '#fef3c7',
                'in_progress': '#dbeafe',
                'done': '#d1fae5'
            };
            
            const statusTextColors = {
                'todo': '#92400e',
                'in_progress': '#1e40af',
                'done': '#065f46'
            };
            
            const priorityColors = {
                'low': '#dbeafe',
                'medium': '#fef3c7',
                'high': '#fee2e2'
            };
            
            let html = `
                <div class="pm-task-detail-section">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <h2 style="margin: 0 0 10px 0;">${escapeHtml(task.title)}</h2>
                            ${task.description ? `<p style="color: #6b7280; margin: 0;">${escapeHtml(task.description)}</p>` : ''}
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <?php if ($is_pm): ?>
                                <button class="bntm-btn-small" onclick="openEditTaskModal(${task.id})">Edit</button>
                            <?php endif; ?>
                            <?php if ($is_staff || $is_pm): ?>
                                <button class="bntm-btn-small" onclick="openLogTimeModal(${task.id})">Log Time</button>
                                <button class="bntm-btn-small" onclick="openAddCommentModal(${task.id})">Comment</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="pm-task-detail-row">
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Status</div>
                            <div class="pm-task-detail-value">
                                <span style="background: ${statusColors[task.status]}; color: ${statusTextColors[task.status]}; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                    ${task.status.replace('_', ' ').toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Priority</div>
                            <div class="pm-task-detail-value">
                                <span class="pm-priority-badge pm-priority-${task.priority}">
                                    ${task.priority.toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Assigned To</div>
                            <div class="pm-task-detail-value">${task.assigned_name || 'Unassigned'}</div>
                        </div>
                    </div>
                    
                    <div class="pm-task-detail-row">
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Due Date</div>
                            <div class="pm-task-detail-value">${task.due_date ? formatDate(task.due_date) : 'Not set'}</div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Estimated Hours</div>
                            <div class="pm-task-detail-value">${task.estimated_hours || 0}h</div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Actual Hours</div>
                            <div class="pm-task-detail-value">${task.actual_hours || 0}h</div>
                        </div>
                    </div>
                    
                    ${task.tags ? `
                    <div style="margin-top: 10px;">
                        <div class="pm-task-detail-label">Tags</div>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px;">
                            ${task.tags.split(',').map(tag => `
                                <span style="background: #e0f2fe; color: #0c4a6e; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    ${tag.trim()}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <!-- Time Logs -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0;">Time Logs (${timeLogs.length})</h4>
                    ${timeLogs.length > 0 ? `
                        <div class="pm-task-detail-section">
                            ${timeLogs.map(log => `
                                <div class="pm-time-log-item">
                                    <div>
                                        <strong>${log.user_name}</strong> logged <strong>${log.hours}h</strong>
                                        ${log.notes ? `<br><small style="color: #6b7280;">${escapeHtml(log.notes)}</small>` : ''}
                                    </div>
                                    <div style="text-align: right; font-size: 12px; color: #6b7280;">
                                        ${formatDate(log.log_date)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p style="color: #6b7280;">No time logged yet.</p>'}
                </div>
                
                <!-- Comments -->
                <div>
                    <h4 style="margin: 0 0 10px 0;">Comments (${comments.length})</h4>
                    ${comments.length > 0 ? `
                        <div class="pm-task-detail-section">
                            ${comments.map(comment => `
                                <div class="pm-comment-item">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <strong style="color: #1f2937;">${comment.user_name}</strong>
                                        <span style="font-size: 12px; color: #9ca3af;">
                                            ${formatDateTime(comment.created_at)}
                                        </span>
                                    </div>
                                    <div style="color: #4b5563; white-space: pre-wrap;">${escapeHtml(comment.comment)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p style="color: #6b7280;">No comments yet.</p>'}
                </div>
            `;
            
            document.getElementById('task-details-content').innerHTML = html;
        }
        
        // ========== EDIT TASK ==========
        window.openEditTaskModal = function(taskId) {
            document.getElementById('view-task-modal').style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const task = json.data.task;
                    
                    document.getElementById('edit-task-id').value = task.id;
                    document.getElementById('edit-task-title').value = task.title;
                    document.getElementById('edit-task-description').value = task.description || '';
                    document.getElementById('edit-task-priority').value = task.priority;
                    document.getElementById('edit-task-status').value = task.status;
                    document.getElementById('edit-task-assigned').value = task.assigned_to || '';
                    document.getElementById('edit-task-due-date').value = task.due_date || '';
                    document.getElementById('edit-task-estimated-hours').value = task.estimated_hours || 0;
                    document.getElementById('edit-task-milestone').value = task.milestone_id || '';
                    document.getElementById('edit-task-tags').value = task.tags || '';
                    
                    document.getElementById('edit-task-modal').style.display = 'flex';
                } else {
                    alert('Failed to load task details');
                }
            });
        };
        
        document.getElementById('pm-edit-task-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_update_task');
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
        
        // ========== LOG TIME ==========
        window.openLogTimeModal = function(taskId) {
            document.getElementById('view-task-modal').style.display = 'none';
            document.getElementById('log-time-task-id').value = taskId;
            document.getElementById('log-time-modal').style.display = 'flex';
        };
        
        document.getElementById('pm-log-time-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_log_time');
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
                    btn.textContent = 'Log Time';
                }
            });
        });
        
        // ========== ADD COMMENT ==========
        window.openAddCommentModal = function(taskId) {
            document.getElementById('view-task-modal').style.display = 'none';
            document.getElementById('comment-task-id').value = taskId;
            document.getElementById('add-comment-modal').style.display = 'flex';
        };
        
        document.getElementById('pm-add-comment-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_comment');
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
                    btn.textContent = 'Add Comment';
                }
            });
        });
        
        // ========== UTILITY FUNCTIONS ==========
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + ' minutes ago';
            if (diffHours < 24) return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
            if (diffDays < 7) return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
            
            return formatDate(dateString);
        }
        // ========== ADD MILESTONE ==========
        document.getElementById('pm-add-milestone-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_milestone');
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
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Add Milestone';
                }
            });
        });
        
        // ========== DELETE MILESTONE ==========
        document.querySelectorAll('.pm-delete-milestone').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this milestone? Tasks will remain but will be unassigned from this milestone.')) return;
                
                const milestoneId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'pm_delete_milestone');
                formData.append('milestone_id', milestoneId);
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
        
        // ========== ADD TEAM MEMBER ==========
        document.getElementById('pm-add-team-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_team_member');
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
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Add Member';
                }
            });
        });
        
        // ========== REMOVE TEAM MEMBER ==========
        document.querySelectorAll('.pm-remove-team-member').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this team member from the project?')) return;
                
                const memberId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'pm_remove_team_member');
                formData.append('member_id', memberId);
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
        
        // ========== UPLOAD FILE ==========
        document.getElementById('pm-upload-file-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('pm-file-input');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a file');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'pm_upload_file');
            formData.append('project_id', projectId);
            formData.append('file', file);
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            const progressDiv = document.getElementById('pm-upload-progress');
            const progressBar = document.getElementById('pm-upload-progress-bar');
            const statusText = document.getElementById('pm-upload-status');
            
            btn.disabled = true;
            progressDiv.style.display = 'block';
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                    statusText.textContent = 'Uploading... ' + Math.round(percentComplete) + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        statusText.textContent = 'Upload complete!';
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert('Upload failed: ' + response.data.message);
                        btn.disabled = false;
                        progressDiv.style.display = 'none';
                    }
                } else {
                    alert('Upload error');
                    btn.disabled = false;
                    progressDiv.style.display = 'none';
                }
            });
            
            xhr.open('POST', ajaxurl);
            xhr.send(formData);
        });
        
        // ========== DELETE FILE ==========
        document.querySelectorAll('.pm-delete-file').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Delete this file?')) return;
                
                const fileId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'pm_delete_file');
                formData.append('file_id', fileId);
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
        
        // ========== MAKE TASK TITLES CLICKABLE ==========
         document.querySelectorAll('.pm-task-title').forEach(title => {
             title.addEventListener('click', function() {
                 const taskId = this.getAttribute('data-task-id');
                 loadTaskDetails(taskId);
             });
         });
         
         // ========== EDIT MILESTONE ==========
         document.querySelectorAll('.pm-edit-milestone').forEach(btn => {
             btn.addEventListener('click', function() {
                 const data = this.dataset;
                 document.getElementById('edit-milestone-id').value = data.id;
                 document.getElementById('edit-milestone-name').value = data.name;
                 document.getElementById('edit-milestone-description').value = data.description || '';
                 document.getElementById('edit-milestone-due').value = data.due || '';
                 document.getElementById('edit-milestone-status').value = data.status;
                 document.getElementById('edit-milestone-modal').style.display = 'flex';
             });
         });
         
         document.getElementById('pm-edit-milestone-form').addEventListener('submit', function(e) {
             e.preventDefault();
             
             const formData = new FormData(this);
             formData.append('action', 'pm_update_milestone');
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
                     btn.textContent = 'Update Milestone';
                 }
             });
         });
         
         // ========== COMPLETE MILESTONE ==========
         document.querySelectorAll('.pm-complete-milestone').forEach(btn => {
             btn.addEventListener('click', function() {
                 const milestoneId = this.getAttribute('data-id');
                 
                 const formData = new FormData();
                 formData.append('action', 'pm_update_milestone_status');
                 formData.append('milestone_id', milestoneId);
                 formData.append('status', 'completed');
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
         
         // ========== ROLES CONSTANT ==========
         const userRoles = {
             pm: <?php echo $is_pm ? 'true' : 'false'; ?>,
             staff: <?php echo $is_staff ? 'true' : 'false'; ?>,
             viewer: <?php echo $is_viewer ? 'true' : 'false'; ?>
         };
    })();
    </script>
    <?php
    return ob_get_clean();
}
/* ---------- ADD THESE AJAX HANDLERS ---------- */

function bntm_ajax_pm_get_task_details() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $comments_table = $wpdb->prefix . 'pm_comments';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    $business_id = get_current_user_id();
    $task_id = intval($_POST['task_id']);

    // Get task details
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, u.display_name as assigned_name
        FROM $tasks_table t
        LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
        WHERE t.id = %d",
        $task_id
    ));

    if (!$task) {
        wp_send_json_error(['message' => 'Task not found']);
    }

    // Get comments
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name as user_name
        FROM $comments_table c
        LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
        WHERE c.task_id = %d
        ORDER BY c.created_at DESC",
        $task_id
    ));

    // Get time logs
    $time_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT tl.*, u.display_name as user_name
        FROM $time_logs_table tl
        LEFT JOIN {$wpdb->users} u ON tl.user_id = u.ID
        WHERE tl.task_id = %d
        ORDER BY tl.log_date DESC",
        $task_id
    ));

    wp_send_json_success([
        'task' => $task,
        'comments' => $comments,
        'time_logs' => $time_logs
    ]);
}
add_action('wp_ajax_pm_get_task_details', 'bntm_ajax_pm_get_task_details');

function bntm_ajax_pm_update_task() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $task_id = intval($_POST['task_id']);

    $data = [
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description']),
        'status' => sanitize_text_field($_POST['status']),
        'priority' => sanitize_text_field($_POST['priority']),
        'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
        'due_date' => !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null,
        'estimated_hours' => floatval($_POST['estimated_hours']),
        'milestone_id' => !empty($_POST['milestone_id']) ? intval($_POST['milestone_id']) : null,
        'tags' => sanitize_text_field($_POST['tags'])
    ];
    
    if ($data['status'] === 'done') {
        $data['completed_date'] = current_time('mysql');
    }

    $result = $wpdb->update($table, $data, ['id' => $task_id]);

    if ($result !== false) {
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $task_id));
        pm_log_activity($business_id, $task->project_id, $task_id, 'updated task', $data['title']);
        pm_update_project_progress($task->project_id);
        
        wp_send_json_success(['message' => 'Task updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update task.']);
    }
}
add_action('wp_ajax_pm_update_task', 'bntm_ajax_pm_update_task');
function bntm_ajax_pm_update_milestone() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_milestones';
    $milestone_id = intval($_POST['milestone_id']);

    $data = [
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'due_date' => !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null,
        'status' => sanitize_text_field($_POST['status'])
    ];

    $result = $wpdb->update($table, $data, ['id' => $milestone_id]);

    if ($result !== false) {
        wp_send_json_success(['message' => 'Milestone updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update milestone.']);
    }
}
add_action('wp_ajax_pm_update_milestone', 'bntm_ajax_pm_update_milestone');

function bntm_ajax_pm_update_milestone_status() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_milestones';
    $milestone_id = intval($_POST['milestone_id']);
    $status = sanitize_text_field($_POST['status']);

    $result = $wpdb->update($table, ['status' => $status], ['id' => $milestone_id]);

    if ($result !== false) {
        wp_send_json_success(['message' => 'Milestone marked complete!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update milestone.']);
    }
}
add_action('wp_ajax_pm_update_milestone_status', 'bntm_ajax_pm_update_milestone_status');

/* ---------- PROJECT BOARD TAB ---------- */
function pm_project_board_tab($business_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $team_table = $wpdb->prefix . 'pm_team_members';

    $current_user_id = get_current_user_id();
    
    // Get projects where user is owner or team member
    // Only show: in_progress, planning, on_hold, review (in that order)
    $status_order = ['in_progress', 'planning', 'on_hold', 'review'];
    
      // Get saved project order
    $saved_order = get_user_meta($current_user_id, 'pm_project_board_order', true);
    $saved_order = is_array($saved_order) ? $saved_order : [];
    
    // Get projects where user is owner or team member
    $projects = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT p.*, 
        (SELECT COUNT(*) FROM $tasks_table WHERE project_id = p.id) as total_tasks,
        (SELECT COUNT(*) FROM $tasks_table WHERE project_id = p.id AND status IN ('done', 'closed')) as completed_tasks
        FROM $projects_table p
        LEFT JOIN $team_table tm ON p.id = tm.project_id AND tm.user_id = %d
        WHERE (p.business_id = %d OR tm.user_id = %d)
        AND p.status IN ('in_progress', 'planning', 'on_hold', 'review')
        ORDER BY FIELD(p.status, 'in_progress', 'planning', 'on_hold', 'review'), p.name ASC",
        $current_user_id, $current_user_id, $current_user_id
    ));
    
    // Apply saved order if exists
    if (!empty($saved_order)) {
        usort($projects, function($a, $b) use ($saved_order) {
            $pos_a = array_search($a->id, $saved_order);
            $pos_b = array_search($b->id, $saved_order);
            
            // If both found in saved order, sort by position
            if ($pos_a !== false && $pos_b !== false) {
                return $pos_a - $pos_b;
            }
            
            // If only one found, prioritize it
            if ($pos_a !== false) return -1;
            if ($pos_b !== false) return 1;
            
            // If neither found, maintain current order
            return 0;
        });
    }
    $nonce = wp_create_nonce('pm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Project Board</h3>
            <div style="font-size: 13px; color: #6b7280;">
                Drag to reorder projects • Click task to view details
            </div>
        </div>
        
        <?php if (empty($projects)): ?>
            <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 8px;">
                <p style="color: #6b7280; font-size: 16px; margin: 0;">No active projects found.</p>
                <p style="color: #9ca3af; font-size: 14px; margin: 10px 0 0 0;">Projects with status: In Progress, Planning, On Hold, or Review will appear here.</p>
            </div>
        <?php else: ?>
            <div class="pm-project-board" id="project-board">
                <?php foreach ($projects as $project): 
                    // Get tasks for this project
                    $tasks = $wpdb->get_results($wpdb->prepare(
                      "SELECT t.*, u.display_name as assigned_name
                       FROM $tasks_table t
                       LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
                       WHERE t.project_id = %d
                         AND t.status != 'closed'
                       ORDER BY FIELD(t.status, 'in_progress', 'todo', 'on_hold', 'done'), 
                                t.priority DESC, t.created_at DESC",
                      $project->id
                  ));

                    
                    $percentage = $project->total_tasks > 0 ? round(($project->completed_tasks/$project->total_tasks)*100) : 0;
                ?>
                    <div class="pm-board-project-card" draggable="true" data-project-id="<?php echo $project->id; ?>">
                        <!-- Project Header -->
                        <div class="pm-board-project-header" style="background: linear-gradient(135deg, <?php echo $project->color; ?> 0%, <?php echo $project->color; ?>dd 100%);">
                            <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                                <?php if ($project->image): ?>
                                    <img src="<?php echo esc_url($project->image); ?>" style="width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3);">
                                <?php else: ?>
                                    <div style="width: 48px; height: 48px; border-radius: 8px; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3);"></div>
                                <?php endif; ?>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 4px 0; color: white; font-size: 18px;">
                                        <?php echo esc_html($project->name); ?>
                                    </h4>
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <span class="pm-status-badge" style="background: rgba(255,255,255,0.9); color: #1f2937; font-size: 11px;">
                                            <?php echo ucfirst(str_replace('_', ' ', $project->status)); ?>
                                        </span>
                                        <?php if ($project->client_name): ?>
                                            <span style="color: rgba(255,255,255,0.95); font-size: 13px;">
                                                👤 <?php echo esc_html($project->client_name); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right; color: white;">
                                <div style="font-size: 24px; font-weight: 700; line-height: 1;">
                                    <?php echo $percentage; ?>%
                                </div>
                                <div style="font-size: 11px; opacity: 0.9; margin-top: 2px;">
                                    <?php echo $project->completed_tasks; ?>/<?php echo $project->total_tasks; ?> tasks
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tasks Grid -->
                        <div class="pm-board-tasks-grid">
                            <?php if (empty($tasks)): ?>
                                <div style="text-align: center; padding: 30px 20px; color: #9ca3af; font-size: 14px;">
                                    No tasks in this project
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                    <div class="pm-board-task-item" data-task-id="<?php echo $task->id; ?>" style="border-left-color: <?php echo $project->color; ?>;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 6px;">
                                            <h5 style="margin: 0; font-size: 14px; font-weight: 600; color: #1f2937; flex: 1;">
                                                <?php echo esc_html($task->title); ?>
                                            </h5>
                                            <span class="pm-priority-badge pm-priority-<?php echo $task->priority; ?>" style="font-size: 10px; padding: 3px 8px; margin-left: 8px;">
                                                <?php echo strtoupper(substr($task->priority, 0, 1)); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($task->description): ?>
                                            <p style="color: #6b7280; font-size: 12px; margin: 4px 0; line-height: 1.4;">
                                                <?php echo esc_html(substr($task->description, 0, 60)) . (strlen($task->description) > 60 ? '...' : ''); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; font-size: 11px; color: #6b7280;">
                                            <span class="pm-status-badge pm-status-<?php echo $task->status; ?>" style="font-size: 10px; padding: 2px 8px;">
                                                <?php echo ucfirst(str_replace('_', ' ', $task->status)); ?>
                                            </span>
                                            <?php if ($task->assigned_name): ?>
                                                <span>👤 <?php echo esc_html($task->assigned_name); ?></span>
                                            <?php endif; ?>
                                            <?php if ($task->due_date && strtotime($task->due_date) >= strtotime('today')): ?>
                                                <span style="color: <?php echo strtotime($task->due_date) < time() ? '#dc2626' : '#6b7280'; ?>;">
                                                    📅 <?php echo date('M d', strtotime($task->due_date)); ?>
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
            <!-- View Task Modal -->
    <div id="view-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content" style="max-width: 900px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="task-modal-title">Task Details</h3>
                <button class="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
            </div>
            
            <div id="task-details-content">
                <!-- Task details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="edit-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Task</h3>
            <form id="pm-edit-task-form" class="bntm-form">
                <input type="hidden" id="edit-task-id" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Task Title *</label>
                    <input type="text" id="edit-task-title" name="title" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-task-description" name="description" rows="3"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Priority *</label>
                        <select id="edit-task-priority" name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Status *</label>
                        <select id="edit-task-status" name="status" required>
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="done">Done</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Assign To</label>
                        <select id="edit-task-assigned" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php 
                            $all_users = get_users([
                                'fields' => ['ID', 'display_name'],
                                'role__not_in' => ['administrator']
                            ]);
                            foreach ($all_users as $user): ?>
                                <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" id="edit-task-due-date" name="due_date">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Estimated Hours</label>
                        <input type="number" id="edit-task-estimated-hours" name="estimated_hours" step="0.5" min="0">
                    </div>
                    <div class="bntm-form-group">
                        <label>Milestone</label>
                        <select id="edit-task-milestone" name="milestone_id">
                            <option value="">No Milestone</option>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Tags (comma-separated)</label>
                    <input type="text" id="edit-task-tags" name="tags" placeholder="frontend, urgent, bug">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Task</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Time Modal -->
    <div id="log-time-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Log Time</h3>
            <form id="pm-log-time-form" class="bntm-form">
                <input type="hidden" id="log-time-task-id" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Hours Worked *</label>
                    <input type="number" name="hours" step="0.5" min="0.5" required placeholder="e.g., 2.5">
                </div>
                
                <div class="bntm-form-group">
                    <label>Date *</label>
                    <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="What did you work on?"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Log Time</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Comment Modal -->
    <div id="add-comment-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add Comment</h3>
            <form id="pm-add-comment-form" class="bntm-form">
                <input type="hidden" id="comment-task-id" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Comment *</label>
                    <textarea name="comment" rows="4" required placeholder="Write your comment..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Comment</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
        <?php endif; ?>
    </div>
   <style>
    /* Project Board Styles */
    .pm-project-board {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .pm-board-project-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        transition: all 0.3s;
        cursor: move;
    }
    
    .pm-board-project-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        transform: translateY(-2px);
    }
    
    .pm-board-project-card.dragging {
        opacity: 0.5;
        transform: rotate(2deg);
    }
    
    .pm-board-project-header {
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }
    
    .pm-board-tasks-grid {
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 12px;
        background: #f9fafb;
    }
    
    .pm-board-task-item {
        background: white;
        padding: 12px;
        border-radius: 6px;
        border-left: 3px solid #3b82f6;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .pm-board-task-item:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transform: translateY(-1px);
    }
    
    /* Modal Styles */
    .in-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .in-modal-content {
        background-color: #fff;
        padding: 30px;
        border-radius: 12px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .in-modal-content h3 {
        margin: 0 0 20px 0;
        font-size: 20px;
        color: #1f2937;
        font-weight: 600;
    }
    
    /* Form Styles */
    .bntm-form-group {
        margin-bottom: 16px;
    }
    
    .bntm-form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }
    
    .bntm-form-group input[type="text"],
    .bntm-form-group input[type="number"],
    .bntm-form-group input[type="date"],
    .bntm-form-group select,
    .bntm-form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.2s;
        box-sizing: border-box;
    }
    
    .bntm-form-group input:focus,
    .bntm-form-group select:focus,
    .bntm-form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .bntm-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    /* Button Styles */
    .bntm-btn-primary {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .bntm-btn-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .bntm-btn-primary:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        transform: none;
    }
    
    .bntm-btn-secondary {
        background: #6b7280;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .bntm-btn-secondary:hover {
        background: #4b5563;
    }
    
    .bntm-btn-small {
        padding: 6px 12px;
        font-size: 13px;
        border-radius: 4px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .bntm-btn-small:hover {
        background: #f9fafb;
        border-color: #9ca3af;
    }
    
    /* Task Detail Styles */
    .pm-task-detail-section {
        background: #f9fafb;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    
    .pm-task-detail-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .pm-task-detail-item {
        flex: 1;
    }
    
    .pm-task-detail-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .pm-task-detail-value {
        font-size: 14px;
        color: #1f2937;
        font-weight: 600;
    }
    
    .pm-comment-item {
        padding: 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 10px;
        border-left: 3px solid #3b82f6;
    }
    
    .pm-time-log-item {
        padding: 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-left: 3px solid #10b981;
    }
    
    /* Status Badges */
    .pm-status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
        display: inline-block;
    }
    
    .pm-status-todo {
        background: #fef3c7;
        color: #92400e;
    }
    
    .pm-status-in_progress {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .pm-status-on_hold {
        background: #fed7aa;
        color: #ea580c;
    }
    
    .pm-status-done {
        background: #d1fae5;
        color: #065f46;
    }
    
    .pm-status-closed {
        background: #e5e7eb;
        color: #374151;
    }
    
    .pm-status-review {
        background: #e9d5ff;
        color: #6b21a8;
    }
    
    /* Priority Badges */
    .pm-priority-badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
        display: inline-block;
    }
    
    .pm-priority-low {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .pm-priority-medium {
        background: #fef3c7;
        color: #92400e;
    }
    
    .pm-priority-high {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Loading Animation */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .pm-board-tasks-grid {
            grid-template-columns: 1fr;
        }
        
        .pm-task-detail-row {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        
        .bntm-form-row {
            grid-template-columns: 1fr;
        }
        
        .in-modal-content {
            padding: 20px;
        }
    }
    </style>
   <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        const currentUserId = <?php echo get_current_user_id(); ?>;
        
        // ========== MODAL CONTROLS ==========
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // Close modal on outside click
        document.querySelectorAll('.in-modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // ========== DRAG AND DROP FOR PROJECTS ==========
        const projectCards = document.querySelectorAll('.pm-board-project-card');
        const projectBoard = document.getElementById('project-board');
        
        projectCards.forEach(card => {
            card.addEventListener('dragstart', function(e) {
                draggedProject = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            
            card.addEventListener('dragend', function() {
                this.classList.remove('dragging');
                draggedProject = null;
            });
            
            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(projectBoard, e.clientY);
                if (afterElement == null) {
                    projectBoard.appendChild(draggedProject);
                } else {
                    projectBoard.insertBefore(draggedProject, afterElement);
                }
            });
        });
        
        function getDragAfterElement(container, y) {
            const draggableElements = [...container.querySelectorAll('.pm-board-project-card:not(.dragging)')];
            
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
        
        // Save order on drag end
        projectBoard.addEventListener('dragend', function() {
            const projectIds = [...document.querySelectorAll('.pm-board-project-card')].map(card => 
                card.getAttribute('data-project-id')
            );
            
            // Save order to server
            const formData = new FormData();
            formData.append('action', 'pm_save_project_order');
            formData.append('project_ids', JSON.stringify(projectIds));
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (!json.success) {
                    console.error('Failed to save project order');
                }
            })
            .catch(err => console.error('Error saving order:', err));
        });
        
        // ========== TASK CLICK - OPEN MODAL ==========
        document.querySelectorAll('.pm-board-task-item').forEach(taskItem => {
            taskItem.addEventListener('click', function(e) {
                e.stopPropagation();
                const taskId = this.getAttribute('data-task-id');
                loadTaskDetails(taskId);
            });
        });
        
        function loadTaskDetails(taskId) {
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            // Show loading
            document.getElementById('task-details-content').innerHTML = '<div style="text-align: center; padding: 40px;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f4f6; border-top-color: #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div></div>';
            document.getElementById('view-task-modal').style.display = 'flex';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    displayTaskDetails(json.data);
                } else {
                    alert(json.data.message || 'Failed to load task details');
                    document.getElementById('view-task-modal').style.display = 'none';
                }
            })
            .catch(err => {
                alert('Error loading task: ' + err.message);
                document.getElementById('view-task-modal').style.display = 'none';
            });
        }
        function displayTaskDetails(data) {
            const task = data.task;
            const project = data.project || {};
            const comments = data.comments || [];
            const timeLogs = data.time_logs || [];
            
            // Check permissions: project owner or task owner can edit
            const isProjectOwner = project.business_id == currentUserId;
            const isTaskOwner = task.created_by == currentUserId;
            const canEdit = isProjectOwner || isTaskOwner;
            
            const statusColors = {
                'todo': '#fef3c7',
                'in_progress': '#dbeafe',
                'done': '#d1fae5',
                'on_hold': '#fed7aa',
                'closed': '#e5e7eb'
            };
            
            const statusTextColors = {
                'todo': '#92400e',
                'in_progress': '#1e40af',
                'done': '#065f46',
                'on_hold': '#ea580c',
                'closed': '#374151'
            };
            
            let html = `
                <div class="pm-task-detail-section">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <h2 style="margin: 0 0 10px 0;">${escapeHtml(task.title)}</h2>
                            ${task.description ? `<p style="color: #6b7280; margin: 0; white-space: pre-wrap;">${escapeHtml(task.description)}</p>` : ''}
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button class="bntm-btn-small" onclick="openEditTaskModal(${task.id})">Edit</button>
                            <button class="bntm-btn-small" onclick="openLogTimeModal(${task.id})">Log Time</button>
                            <button class="bntm-btn-small" onclick="openAddCommentModal(${task.id})">Comment</button>
                        </div>
                    </div>
                    
                    <div class="pm-task-detail-row">
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Status</div>
                            <div class="pm-task-detail-value">
                                <span style="background: ${statusColors[task.status]}; color: ${statusTextColors[task.status]}; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                    ${task.status.replace('_', ' ').toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Priority</div>
                            <div class="pm-task-detail-value">
                                <span class="pm-priority-badge pm-priority-${task.priority}">
                                    ${task.priority.toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Assigned To</div>
                            <div class="pm-task-detail-value">${task.assigned_name || 'Unassigned'}</div>
                        </div>
                    </div>
                    
                    <div class="pm-task-detail-row">
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Due Date</div>
                            <div class="pm-task-detail-value">${task.due_date ? formatDate(task.due_date) : 'Not set'}</div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Estimated Hours</div>
                            <div class="pm-task-detail-value">${task.estimated_hours || 0}h</div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Actual Hours</div>
                            <div class="pm-task-detail-value">${task.actual_hours || 0}h</div>
                        </div>
                    </div>
                    
                    ${task.tags ? `
                    <div style="margin-top: 10px;">
                        <div class="pm-task-detail-label">Tags</div>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px;">
                            ${task.tags.split(',').map(tag => `
                                <span style="background: #e0f2fe; color: #0c4a6e; padding: 4px 10px; border-radius: 6px; font-size: 12px;">
                                    ${tag.trim()}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <!-- Time Logs -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #1f2937;">Time Logs (${timeLogs.length})</h4>
                    ${timeLogs.length > 0 ? `
                        <div class="pm-task-detail-section" style="padding: 10px;">
                            ${timeLogs.map(log => `
                                <div class="pm-time-log-item">
                                    <div>
                                        <strong style="color: #1f2937;">${log.user_name}</strong> logged <strong style="color: #10b981;">${log.hours}h</strong>
                                        ${log.notes ? `<br><small style="color: #6b7280; font-size: 12px;">${escapeHtml(log.notes)}</small>` : ''}
                                    </div>
                                    <div style="text-align: right; font-size: 12px; color: #6b7280;">
                                        ${formatDate(log.log_date)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p style="color: #6b7280; font-size: 14px;">No time logged yet.</p>'}
                </div>
                
                <!-- Comments -->
                <div>
                    <h4 style="margin: 0 0 10px 0; color: #1f2937;">Comments (${comments.length})</h4>
                    ${comments.length > 0 ? `
                        <div class="pm-task-detail-section" style="padding: 10px;">
                            ${comments.map(comment => `
                                <div class="pm-comment-item">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <strong style="color: #1f2937;">${comment.user_name}</strong>
                                        <span style="font-size: 12px; color: #9ca3af;">
                                            ${formatDateTime(comment.created_at)}
                                        </span>
                                    </div>
                                    <div style="color: #4b5563; white-space: pre-wrap; font-size: 14px;">${escapeHtml(comment.comment)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p style="color: #6b7280; font-size: 14px;">No comments yet.</p>'}
                </div>
            `;
            
            document.getElementById('task-details-content').innerHTML = html;
        }
        // ========== UTILITY FUNCTIONS ==========
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + ' minutes ago';
            if (diffHours < 24) return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
            if (diffDays < 7) return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
            
            return formatDate(dateString);
        }
        
        // Make functions global for modal buttons
        window.openEditTaskModal = function(taskId) {
            document.getElementById('view-task-modal').style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const task = json.data.task;
                    
                    document.getElementById('edit-task-id').value = task.id;
                    document.getElementById('edit-task-title').value = task.title;
                    document.getElementById('edit-task-description').value = task.description || '';
                    document.getElementById('edit-task-priority').value = task.priority;
                    document.getElementById('edit-task-status').value = task.status;
                    document.getElementById('edit-task-assigned').value = task.assigned_to || '';
                    document.getElementById('edit-task-due-date').value = task.due_date || '';
                    document.getElementById('edit-task-estimated-hours').value = task.estimated_hours || 0;
                    document.getElementById('edit-task-milestone').value = task.milestone_id || '';
                    document.getElementById('edit-task-tags').value = task.tags || '';
                    
                    document.getElementById('edit-task-modal').style.display = 'flex';
                } else {
                    alert('Failed to load task details');
                }
            });
        };
        
        window.openLogTimeModal = function(taskId) {
            document.getElementById('view-task-modal').style.display = 'none';
            document.getElementById('log-time-task-id').value = taskId;
            document.getElementById('log-time-modal').style.display = 'flex';
        };
        
        window.openAddCommentModal = function(taskId) {
            document.getElementById('view-task-modal').style.display = 'none';
            document.getElementById('comment-task-id').value = taskId;
            document.getElementById('add-comment-modal').style.display = 'flex';
        };
        
        // Add spin animation for loading
        const style = document.createElement('style');
        style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
        document.head.appendChild(style);
        
// ========== EDIT TASK FORM SUBMIT ==========
        document.getElementById('pm-edit-task-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_update_task');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Updating...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    // Show success message briefly then reload
                    btn.textContent = '✓ Updated!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(json.data.message || 'Failed to update task');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
        
        // ========== LOG TIME FORM SUBMIT ==========
        document.getElementById('pm-log-time-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_log_time');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Logging...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    btn.textContent = '✓ Logged!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(json.data.message || 'Failed to log time');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
        
        // ========== ADD COMMENT FORM SUBMIT ==========
        document.getElementById('pm-add-comment-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_comment');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    btn.textContent = '✓ Added!';
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    alert(json.data.message || 'Failed to add comment');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
        
    })();
    </script>
    <?php
    return ob_get_clean();
}

function bntm_ajax_pm_save_project_order() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $business_id = get_current_user_id();
    $project_ids = json_decode(stripslashes($_POST['project_ids']), true);
    
    if (!is_array($project_ids)) {
        wp_send_json_error(['message' => 'Invalid data']);
    }
    
    // Save order to user meta
    update_user_meta($business_id, 'pm_project_board_order', $project_ids);
    
    wp_send_json_success(['message' => 'Order saved']);
}
add_action('wp_ajax_pm_save_project_order', 'bntm_ajax_pm_save_project_order');

/* ---------- TASKS TAB (My Tasks View) ---------- */
function pm_tasks_tab($business_id) {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $team_table = $wpdb->prefix . 'pm_team_members';
    
 // Get only tasks assigned to the current user
$my_tasks = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, p.name as project_name, p.color as project_color, u.display_name as assigned_name
    FROM $tasks_table t
    INNER JOIN $projects_table p ON t.project_id = p.id
    LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
    WHERE t.assigned_to = %d
    ORDER BY 
        CASE WHEN t.status = 'done' THEN 1 
             WHEN t.status = 'closed' THEN 1 
             ELSE 0 END,
        CASE t.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        t.due_date ASC",
    $business_id
));
    
    // Get all users for assignment dropdown
    $all_users = get_users([
        'fields' => ['ID', 'display_name'],
        'role__not_in' => ['administrator']
    ]);
    
    $nonce = wp_create_nonce('pm_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>My Tasks (<?php echo count($my_tasks); ?>)</h3>
        
       <!-- Filter Tabs -->
         <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
             <button class="pm-task-filter-btn active" data-status="todo">To Do (<?php echo count(array_filter($my_tasks, fn($t) => $t->status === 'todo')); ?>)</button>
             <button class="pm-task-filter-btn" data-status="in_progress">In Progress (<?php echo count(array_filter($my_tasks, fn($t) => $t->status === 'in_progress')); ?>)</button>
             <button class="pm-task-filter-btn" data-status="on_hold">On Hold (<?php echo count(array_filter($my_tasks, fn($t) => $t->status === 'on_hold')); ?>)</button>
             <button class="pm-task-filter-btn" data-status="done">Done (<?php echo count(array_filter($my_tasks, fn($t) => $t->status === 'done')); ?>)</button>
             <button class="pm-task-filter-btn" data-status="closed">Closed (<?php echo count(array_filter($my_tasks, fn($t) => $t->status === 'closed')); ?>)</button>
             <button class="pm-task-filter-btn" data-status="others">Others (<?php echo count(array_filter($my_tasks, fn($t) => !in_array($t->status, ['done', 'closed', 'on_hold']))); ?>)</button>
             <button class="pm-task-filter-btn" data-status="all">All Tasks</button>
         </div>
        
        <?php if (empty($my_tasks)): ?>
            <div style="text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 8px;">
                <p style="font-size: 18px; color: #6b7280; margin: 0;">📋 No tasks assigned to you yet.</p>
            </div>
        <?php else: ?>
            <div class="pm-tasks-list">
                <?php foreach ($my_tasks as $task): ?>
                    <div class="pm-task-item-mytasks" data-task-status="<?php echo esc_attr($task->status); ?>" data-task-id="<?php echo $task->id; ?>" style="border-left-color: <?php echo esc_attr($task->project_color); ?>;">
                        <div style="display: flex; gap: 15px; align-items: start;">
                          
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 5px;">
                                    <h4 style="margin: 0; cursor: pointer; color: #1f2937; <?php echo $task->status === 'done' || $task->status === 'closed' ? 'text-decoration: line-through; opacity: 0.6;' : ''; ?>" 
                                        class="pm-task-title-mytasks" data-task-id="<?php echo $task->id; ?>">
                                        <?php echo esc_html($task->title); ?>
                                    </h4>
                                    <span class="pm-priority-badge pm-priority-<?php echo $task->priority; ?>">
                                        <?php echo ucfirst($task->priority); ?>
                                    </span>
                                </div>
                                
                                <?php if ($task->description): ?>
                                    <p style="color: #6b7280; font-size: 14px; margin: 5px 0; line-height: 1.4;">
                                        <?php echo esc_html(substr($task->description, 0, 100)) . (strlen($task->description) > 100 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 15px; align-items: center; font-size: 13px; color: #6b7280; margin-top: 8px; flex-wrap: wrap;">
                                    <span style="display: inline-block; padding: 2px 8px; background: <?php echo $task->project_color; ?>; color: white; border-radius: 4px; font-size: 11px;">
                                        📁 <?php echo esc_html($task->project_name); ?>
                                    </span>
                                    <?php if ($task->assigned_name): ?>
                                        <span>👤 <?php echo esc_html($task->assigned_name); ?></span>
                                    <?php endif; ?>
                                    <?php if ($task->due_date): ?>
                                        <?php 
                                        $is_overdue = strtotime($task->due_date) < time() && !in_array($task->status, ['done', 'closed']);
                                        $due_color = $is_overdue ? '#dc2626' : '#6b7280';
                                        ?>
                                        <span style="color: <?php echo $due_color; ?>; font-weight: <?php echo $is_overdue ? '600' : '400'; ?>;">
                                            📅 <?php echo date('M d, Y', strtotime($task->due_date)); ?>
                                            <?php if ($is_overdue): ?>
                                                <strong style="color: #dc2626;">⚠️ OVERDUE</strong>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($task->estimated_hours > 0): ?>
                                        <span>⏱️ <?php echo $task->estimated_hours; ?>h est.</span>
                                    <?php endif; ?>
                                    <?php if ($task->actual_hours > 0): ?>
                                        <span>✅ <?php echo $task->actual_hours; ?>h logged</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button class="bntm-btn-small pm-view-task-mytasks" data-id="<?php echo $task->id; ?>">
                                View
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- View/Edit Task Modal -->
    <div id="view-task-modal-mytasks" class="in-modal" style="display: none;">
        <div class="in-modal-content" style="max-width: 900px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="task-modal-title-mytasks">Task Details</h3>
                <button class="close-modal-mytasks" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
            </div>
            
            <div id="task-details-content-mytasks">
            </div>
        </div>
    </div>
    
    <!-- Edit Task Modal -->
    <div id="edit-task-modal-mytasks" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Task</h3>
            <form id="pm-edit-task-form-mytasks" class="bntm-form">
                <input type="hidden" id="edit-task-id-mytasks" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Task Title *</label>
                    <input type="text" id="edit-task-title-mytasks" name="title" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-task-description-mytasks" name="description" rows="3"></textarea>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Priority *</label>
                        <select id="edit-task-priority-mytasks" name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                         <label>Status *</label>
                         <select id="edit-task-status-mytasks" name="status" required>
                             <option value="todo">To Do</option>
                             <option value="in_progress">In Progress</option>
                             <option value="on_hold">On Hold</option>
                             <option value="done">Done</option>
                             <option value="closed">Closed</option>
                         </select>
                     </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Assign To</label>
                        <select id="edit-task-assigned-mytasks" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bntm-form-group">
                        <label>Due Date</label>
                        <input type="date" id="edit-task-due-date-mytasks" name="due_date">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Estimated Hours</label>
                        <input type="number" id="edit-task-estimated-hours-mytasks" name="estimated_hours" step="0.5" min="0">
                    </div>
                    <div class="bntm-form-group">
                        <label>Tags (comma-separated)</label>
                        <input type="text" id="edit-task-tags-mytasks" name="tags" placeholder="frontend, urgent, bug">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Task</button>
                    <button type="button" class="close-modal-mytasks bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Time Modal -->
    <div id="log-time-modal-mytasks" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Log Time</h3>
            <form id="pm-log-time-form-mytasks" class="bntm-form">
                <input type="hidden" id="log-time-task-id-mytasks" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Hours Worked *</label>
                    <input type="number" name="hours" step="0.5" min="0.5" required placeholder="e.g., 2.5">
                </div>
                
                <div class="bntm-form-group">
                    <label>Date *</label>
                    <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Notes</label>
                    <textarea name="notes" rows="3" placeholder="What did you work on?"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Log Time</button>
                    <button type="button" class="close-modal-mytasks bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Comment Modal -->
    <div id="add-comment-modal-mytasks" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add Comment</h3>
            <form id="pm-add-comment-form-mytasks" class="bntm-form">
                <input type="hidden" id="comment-task-id-mytasks" name="task_id">
                
                <div class="bntm-form-group">
                    <label>Comment *</label>
                    <textarea name="comment" rows="4" required placeholder="Write your comment..."></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Comment</button>
                    <button type="button" class="close-modal-mytasks bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
    .pm-task-item-mytasks {
        padding: 15px;
        background: #ffffff;
        border-radius: 8px;
        margin-bottom: 12px;
        border-left: 4px solid #3b82f6;
        transition: all 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .pm-task-item-mytasks:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .pm-task-title-mytasks {
        cursor: pointer;
        transition: color 0.2s;
    }
    .pm-task-title-mytasks:hover {
        color: #3b82f6 !important;
    }
    .pm-priority-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .pm-priority-low {
        background: #dbeafe;
        color: #1e40af;
    }
    .pm-priority-medium {
        background: #fef3c7;
        color: #92400e;
    }
    .pm-priority-high {
        background: #fee2e2;
        color: #991b1b;
    }
    .pm-task-filter-btn {
        padding: 8px 16px;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .pm-task-filter-btn:hover {
        background: #f3f4f6;
    }
    .pm-task-filter-btn.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    /* Task Detail Styles */
    .pm-task-detail-section {
        background: #f9fafb;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
    }
    .pm-task-detail-row {
        display: flex;
        gap: 30px;
        margin-bottom: 10px;
    }
    .pm-task-detail-item {
        flex: 1;
    }
    .pm-task-detail-label {
        font-size: 12px;
        color: #6b7280;
        font-weight: 500;
        margin-bottom: 5px;
    }
    .pm-task-detail-value {
        font-size: 14px;
        color: #1f2937;
        font-weight: 600;
    }
    .pm-comment-item {
        padding: 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 10px;
        border-left: 3px solid #3b82f6;
    }
    .pm-time-log-item {
        padding: 10px;
        background: white;
        border-radius: 6px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    </style>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // ========== MODAL CONTROLS ==========
        document.querySelectorAll('.close-modal-mytasks').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== INITIALIZE FILTER TO "TO DO" ON PAGE LOAD ==========
         document.addEventListener('DOMContentLoaded', function() {
             // Check URL for task_id parameter
             const urlParams = new URLSearchParams(window.location.search);
             const taskIdFromUrl = urlParams.get('task_id');
             
             if (taskIdFromUrl) {
                 // If coming from notification, open that specific task
                 loadTaskDetails(taskIdFromUrl);
             }
             
             // Set initial filter
             document.querySelectorAll('.pm-task-item-mytasks').forEach(task => {
                 const status = task.getAttribute('data-task-status');
                 task.style.display = status === 'todo' ? 'block' : 'none';
             });
         });
         
         // ========== TASK STATUS FILTERS ==========
         document.querySelectorAll('.pm-task-filter-btn').forEach(btn => {
             btn.addEventListener('click', function() {
                 document.querySelectorAll('.pm-task-filter-btn').forEach(b => b.classList.remove('active'));
                 this.classList.add('active');
                 
                 const status = this.getAttribute('data-status');
                 document.querySelectorAll('.pm-task-item-mytasks').forEach(task => {
                     const taskStatus = task.getAttribute('data-task-status');
                     
                     if (status === 'all') {
                         task.style.display = 'block';
                     } else if (status === 'others') {
                         // Show tasks that are not done, closed, or on_hold
                         if (!['done', 'closed', 'on_hold'].includes(taskStatus)) {
                             task.style.display = 'block';
                         } else {
                             task.style.display = 'none';
                         }
                     } else if (taskStatus === status) {
                         task.style.display = 'block';
                     } else {
                         task.style.display = 'none';
                     }
                 });
             });
         });
        
        // ========== VIEW TASK DETAILS ==========
        document.querySelectorAll('.pm-view-task-mytasks, .pm-task-title-mytasks').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.getAttribute('data-id') || this.getAttribute('data-task-id');
                loadTaskDetails(taskId);
            });
        });
        
        function loadTaskDetails(taskId) {
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    displayTaskDetails(json.data);
                    document.getElementById('view-task-modal-mytasks').style.display = 'flex';
                } else {
                    alert(json.data.message || 'Failed to load task details');
                }
            })
            .catch(err => {
                alert('Error loading task: ' + err.message);
            });
        }
        
        function displayTaskDetails(data) {
            const task = data.task;
            const comments = data.comments || [];
            const timeLogs = data.time_logs || [];
            
            const statusColors = {
                'todo': '#fef3c7',
                'in_progress': '#dbeafe',
                'done': '#d1fae5',
                'on_hold': '#fed7aa',
                'closed': '#e5e7eb'
            };
            
            const statusTextColors = {
                'todo': '#92400e',
                'in_progress': '#1e40af',
                'done': '#065f46',
                'on_hold': '#ea580c',
                'closed': '#374151'
            };
            
            let html = `
                <div class="pm-task-detail-section">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <h2 style="margin: 0 0 10px 0;">${escapeHtml(task.title)}</h2>
                            ${task.description ? `<p style="color: #6b7280; margin: 0;">${escapeHtml(task.description)}</p>` : ''}
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="bntm-btn-small" onclick="openEditTaskModalMyTasks(${task.id})">Edit</button>
                            <button class="bntm-btn-small" onclick="openLogTimeModalMyTasks(${task.id})">Log Time</button>
                            <button class="bntm-btn-small" onclick="openAddCommentModalMyTasks(${task.id})">Comment</button>
                        </div>
                    </div>
                    
                    <div class="pm-task-detail-row">
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Status</div>
                            <div class="pm-task-detail-value">
                                <span style="background: ${statusColors[task.status]}; color: ${statusTextColors[task.status]}; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                    ${task.status.replace('_', ' ').toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Priority</div>
                            <div class="pm-task-detail-value">
                                <span class="pm-priority-badge pm-priority-${task.priority}">
                                    ${task.priority.toUpperCase()}
                                </span>
                            </div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Assigned To</div>
                            <div class="pm-task-detail-value">${task.assigned_name || 'Unassigned'}</div>
                        </div>
                    </div>
                    
                    <div class="pm-task-detail-row">
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Project</div>
                            <div class="pm-task-detail-value">${task.project_name || 'N/A'}</div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Due Date</div>
                            <div class="pm-task-detail-value">${task.due_date ? formatDate(task.due_date) : 'Not set'}</div>
                        </div>
                        <div class="pm-task-detail-item">
                            <div class="pm-task-detail-label">Time</div>
                            <div class="pm-task-detail-value">${task.actual_hours || 0}h / ${task.estimated_hours || 0}h</div>
                        </div>
                    </div>
                    
                    ${task.tags ? `
                    <div style="margin-top: 10px;">
                        <div class="pm-task-detail-label">Tags</div>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px;">
                            ${task.tags.split(',').map(tag => `
                                <span style="background: #e0f2fe; color: #0c4a6e; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    ${tag.trim()}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                
                <!-- Time Logs -->
                <div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0;">Time Logs (${timeLogs.length})</h4>
                    ${timeLogs.length > 0 ? `
                        <div class="pm-task-detail-section">
                            ${timeLogs.map(log => `
                                <div class="pm-time-log-item">
                                    <div>
                                        <strong>${log.user_name}</strong> logged <strong>${log.hours}h</strong>
                                        ${log.notes ? `<br><small style="color: #6b7280;">${escapeHtml(log.notes)}</small>` : ''}
                                    </div>
                                    <div style="text-align: right; font-size: 12px; color: #6b7280;">
                                        ${formatDate(log.log_date)}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p style="color: #6b7280;">No time logged yet.</p>'}
                </div>
                
                <!-- Comments -->
                <div>
                    <h4 style="margin: 0 0 10px 0;">Comments (${comments.length})</h4>
                    ${comments.length > 0 ? `
                        <div class="pm-task-detail-section">
                            ${comments.map(comment => `
                                <div class="pm-comment-item">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <strong style="color: #1f2937;">${comment.user_name}</strong>
                                        <span style="font-size: 12px; color: #9ca3af;">
                                            ${formatDateTime(comment.created_at)}
                                        </span>
                                    </div>
                                    <div style="color: #4b5563; white-space: pre-wrap;">${escapeHtml(comment.comment)}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p style="color: #6b7280;">No comments yet.</p>'}
                </div>
            `;
            
            document.getElementById('task-details-content-mytasks').innerHTML = html;
        }
        
        // ========== EDIT TASK ==========
        window.openEditTaskModalMyTasks = function(taskId) {
            document.getElementById('view-task-modal-mytasks').style.display = 'none';
            
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    const task = json.data.task;
                    
                    document.getElementById('edit-task-id-mytasks').value = task.id;
                    document.getElementById('edit-task-title-mytasks').value = task.title;
                    document.getElementById('edit-task-description-mytasks').value = task.description || '';
                    document.getElementById('edit-task-priority-mytasks').value = task.priority;
                    document.getElementById('edit-task-status-mytasks').value = task.status;
                    document.getElementById('edit-task-assigned-mytasks').value = task.assigned_to || '';
                    document.getElementById('edit-task-due-date-mytasks').value = task.due_date || '';
                    document.getElementById('edit-task-estimated-hours-mytasks').value = task.estimated_hours || 0;
                    document.getElementById('edit-task-tags-mytasks').value = task.tags || '';
                    
                    document.getElementById('edit-task-modal-mytasks').style.display = 'flex';
                } else {
                    alert('Failed to load task details');
                }
            });
        };
        
        document.getElementById('pm-edit-task-form-mytasks').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_update_task');
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
        
        // ========== LOG TIME ==========
        window.openLogTimeModalMyTasks = function(taskId) {
            document.getElementById('view-task-modal-mytasks').style.display = 'none';
            document.getElementById('log-time-task-id-mytasks').value = taskId;
            document.getElementById('log-time-modal-mytasks').style.display = 'flex';
        };
        
        document.getElementById('pm-log-time-form-mytasks').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_log_time');
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
                    btn.textContent = 'Log Time';
                }
            });
        });
        
        // ========== ADD COMMENT ==========
        window.openAddCommentModalMyTasks = function(taskId) {
            document.getElementById('view-task-modal-mytasks').style.display = 'none';
            document.getElementById('comment-task-id-mytasks').value = taskId;
            document.getElementById('add-comment-modal-mytasks').style.display = 'flex';
        };
        
        document.getElementById('pm-add-comment-form-mytasks').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'pm_add_comment');
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
                    btn.textContent = 'Add Comment';
                }
            });
        });
        
        // ========== UTILITY FUNCTIONS ==========
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + ' minutes ago';
            if (diffHours < 24) return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
            if (diffDays < 7) return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
            
            return formatDate(dateString);
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- KANBAN TAB ---------- */
function pm_kanban_tab($business_id) {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $team_table = $wpdb->prefix . 'pm_team_members';

    $current_user_id = get_current_user_id();
    
    // Filters
    $selected_project = isset($_GET['project_filter']) ? intval($_GET['project_filter']) : 0;
    $task_scope = isset($_GET['task_scope']) && $_GET['task_scope'] === 'my' ? 'my' : 'all';
    
    // Get projects where user is owner or team member
    $projects = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT p.id, p.name, p.color 
        FROM $projects_table p
        LEFT JOIN $team_table tm ON p.id = tm.project_id AND tm.user_id = %d
        WHERE p.business_id = %d OR tm.user_id = %d
        ORDER BY p.name ASC",
        $current_user_id, $current_user_id, $current_user_id
    ));

    // Build query based on filters
    $where_conditions = [];
    $join_team = "";
    
    // Always filter by projects where user has access (owner or team member)
    $join_team = "LEFT JOIN $team_table tm ON p.id = tm.project_id AND tm.user_id = $current_user_id";
    $where_conditions[] = $wpdb->prepare("(p.business_id = %d OR tm.user_id = %d)", $current_user_id, $current_user_id);
    
    // Filter by specific project if selected
    if ($selected_project > 0) {
        $where_conditions[] = $wpdb->prepare("t.project_id = %d", $selected_project);
    }
    
    // Filter by assigned user if "My Tasks" is selected
    if ($task_scope === 'my') {
        $where_conditions[] = $wpdb->prepare("t.assigned_to = %d", $current_user_id);
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

    $tasks = $wpdb->get_results("
        SELECT DISTINCT t.*, p.name as project_name, p.color as project_color, u.display_name as assigned_name
        FROM $tasks_table t
        INNER JOIN $projects_table p ON t.project_id = p.id
        $join_team
        LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
        $where_clause
        ORDER BY t.sort_order ASC, t.created_at DESC
    ");

    $task_columns = [
        'todo' => [],
        'in_progress' => [],
        'on_hold' => [],
        'done' => [],
        'closed' => []
    ];

    foreach ($tasks as $task) {
        if (isset($task_columns[$task->status])) {
            $task_columns[$task->status][] = $task;
        }
    }
    
    // Check user permissions
    $is_wp_admin = current_user_can('manage_options');
    
    // Get all users for assignment dropdown (for modals)
    $all_users = get_users([
        'fields' => ['ID', 'display_name'],
        'role__not_in' => ['administrator']
    ]);
      
    $nonce = wp_create_nonce('pm_nonce');
      
    ob_start();
   ?>
   <script>
   var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
   </script>
   
   <div class="bntm-form-section">
       <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
           <h3>Kanban Board</h3>
           <div style="display:flex;gap:10px;">
               <select id="pm-project-filter" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
                   <option value="0">All Projects</option>
                   <?php foreach ($projects as $project): ?>
                       <option value="<?php echo $project->id; ?>" <?php selected($selected_project, $project->id); ?>>
                           <?php echo esc_html($project->name); ?>
                       </option>
                   <?php endforeach; ?>
               </select>

               <select id="pm-task-scope" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;">
                   <option value="all" <?php selected($task_scope, 'all'); ?>>All Tasks</option>
                   <option value="my" <?php selected($task_scope, 'my'); ?>>My Tasks</option>
               </select>
           </div>
       </div>
       
       <div class="pm-kanban-board">
           <!-- To Do Column -->
           <div class="pm-kanban-column" data-status="todo">
               <div class="pm-kanban-header" style="background: #fef3c7; color: #92400e;">
                   <h4>📝 To Do (<?php echo count($task_columns['todo']); ?>)</h4>
               </div>
               <div class="pm-kanban-tasks" id="kanban-todo">
                   <?php foreach ($task_columns['todo'] as $task): ?>
                       <?php echo pm_render_kanban_task($task); ?>
                   <?php endforeach; ?>
                   <?php if (empty($task_columns['todo'])): ?>
                       <p style="text-align: center; color: #9ca3af; padding: 20px;">No tasks</p>
                   <?php endif; ?>
               </div>
           </div>
           
           <!-- In Progress Column -->
           <div class="pm-kanban-column" data-status="in_progress">
               <div class="pm-kanban-header" style="background: #dbeafe; color: #1e40af;">
                   <h4>🚀 In Progress (<?php echo count($task_columns['in_progress']); ?>)</h4>
               </div>
               <div class="pm-kanban-tasks" id="kanban-in_progress">
                   <?php foreach ($task_columns['in_progress'] as $task): ?>
                       <?php echo pm_render_kanban_task($task); ?>
                   <?php endforeach; ?>
                   <?php if (empty($task_columns['in_progress'])): ?>
                       <p style="text-align: center; color: #9ca3af; padding: 20px;">No tasks</p>
                   <?php endif; ?>
               </div>
           </div>
           
           <!-- Done Column -->
           <div class="pm-kanban-column" data-status="done">
               <div class="pm-kanban-header" style="background: #d1fae5; color: #065f46;">
                   <h4>✅ Done (<?php echo count($task_columns['done']); ?>)</h4>
               </div>
               <div class="pm-kanban-tasks" id="kanban-done">
                   <?php foreach ($task_columns['done'] as $task): ?>
                       <?php echo pm_render_kanban_task($task); ?>
                   <?php endforeach; ?>
                   <?php if (empty($task_columns['done'])): ?>
                       <p style="text-align: center; color: #9ca3af; padding: 20px;">No tasks</p>
                   <?php endif; ?>
               </div>
           </div>
           <!-- Closed Column -->
           <div class="pm-kanban-column" data-status="closed">
               <div class="pm-kanban-header" style="background: #e5e7eb; color: #374151;">
                   <h4>🔒 Closed (<?php echo count($task_columns['closed']); ?>)</h4>
               </div>
               <div class="pm-kanban-tasks" id="kanban-closed">
                   <?php foreach ($task_columns['closed'] as $task): ?>
                       <?php echo pm_render_kanban_task($task); ?>
                   <?php endforeach; ?>
                   <?php if (empty($task_columns['closed'])): ?>
                       <p style="text-align: center; color: #9ca3af; padding: 20px;">No tasks</p>
                   <?php endif; ?>
               </div>
           </div>
           <!-- On Hold Column -->
           <div class="pm-kanban-column" data-status="on_hold">
               <div class="pm-kanban-header" style="background: #fed7aa; color: #ea580c;">
                   <h4>⏸️ On Hold(<?php echo count($task_columns['on_hold']); ?>)</h4>
               </div>
               <div class="pm-kanban-tasks" id="kanban-on_hold">
                   <?php foreach ($task_columns['on_hold'] as $task): ?>
                       <?php echo pm_render_kanban_task($task); ?>
                   <?php endforeach; ?>
                   <?php if (empty($task_columns['on_hold'])): ?>
                       <p style="text-align: center; color: #9ca3af; padding: 20px;">No tasks</p>
                   <?php endif; ?>
               </div>
           </div>
           
       </div>
   </div>
   
   <!-- View Task Modal (Same as project details) -->
   <div id="view-task-modal" class="in-modal" style="display: none;">
       <div class="in-modal-content" style="max-width: 900px;">
           <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
               <h3 id="task-modal-title">Task Details</h3>
               <button class="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
           </div>
           
           <div id="task-details-content">
               <!-- Task details will be loaded here -->
           </div>
       </div>
   </div>
   
   <!-- Edit Task Modal -->
   <div id="edit-task-modal" class="in-modal" style="display: none;">
       <div class="in-modal-content">
           <h3>Edit Task</h3>
           <form id="pm-edit-task-form" class="bntm-form">
               <input type="hidden" id="edit-task-id" name="task_id">
               
               <div class="bntm-form-group">
                   <label>Task Title *</label>
                   <input type="text" id="edit-task-title" name="title" required>
               </div>
               
               <div class="bntm-form-group">
                   <label>Description</label>
                   <textarea id="edit-task-description" name="description" rows="3"></textarea>
               </div>
               
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Priority *</label>
                       <select id="edit-task-priority" name="priority" required>
                           <option value="low">Low</option>
                           <option value="medium">Medium</option>
                           <option value="high">High</option>
                       </select>
                   </div>
                   <div class="bntm-form-group">
                        <label>Status *</label>
                        <select id="edit-task-status" name="status" required>
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="done">Done</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
               </div>
               
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Assign To</label>
                       <select id="edit-task-assigned" name="assigned_to">
                           <option value="">Unassigned</option>
                           <?php foreach ($all_users as $user): ?>
                               <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                           <?php endforeach; ?>
                       </select>
                   </div>
                   <div class="bntm-form-group">
                       <label>Due Date</label>
                       <input type="date" id="edit-task-due-date" name="due_date">
                   </div>
               </div>
               
               <div class="bntm-form-row">
                   <div class="bntm-form-group">
                       <label>Estimated Hours</label>
                       <input type="number" id="edit-task-estimated-hours" name="estimated_hours" step="0.5" min="0">
                   </div>
                   <div class="bntm-form-group">
                       <label>Milestone</label>
                       <select id="edit-task-milestone" name="milestone_id">
                           <option value="">No Milestone</option>
                       </select>
                   </div>
               </div>
               
               <div class="bntm-form-group">
                   <label>Tags (comma-separated)</label>
                   <input type="text" id="edit-task-tags" name="tags" placeholder="frontend, urgent, bug">
               </div>
               
               <div style="display: flex; gap: 10px; margin-top: 20px;">
                   <button type="submit" class="bntm-btn-primary">Update Task</button>
                   <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
               </div>
           </form>
       </div>
   </div>

   <!-- Log Time Modal -->
   <div id="log-time-modal" class="in-modal" style="display: none;">
       <div class="in-modal-content">
           <h3>Log Time</h3>
           <form id="pm-log-time-form" class="bntm-form">
               <input type="hidden" id="log-time-task-id" name="task_id">
               
               <div class="bntm-form-group">
                   <label>Hours Worked *</label>
                   <input type="number" name="hours" step="0.5" min="0.5" required placeholder="e.g., 2.5">
               </div>
               
               <div class="bntm-form-group">
                   <label>Date *</label>
                   <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" required>
               </div>
               
               <div class="bntm-form-group">
                   <label>Notes</label>
                   <textarea name="notes" rows="3" placeholder="What did you work on?"></textarea>
               </div>
               
               <div style="display: flex; gap: 10px; margin-top: 20px;">
                   <button type="submit" class="bntm-btn-primary">Log Time</button>
                   <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
               </div>
           </form>
       </div>
   </div>

   <!-- Add Comment Modal -->
   <div id="add-comment-modal" class="in-modal" style="display: none;">
       <div class="in-modal-content">
           <h3>Add Comment</h3>
           <form id="pm-add-comment-form" class="bntm-form">
               <input type="hidden" id="comment-task-id" name="task_id">
               
               <div class="bntm-form-group">
                   <label>Comment *</label>
                   <textarea name="comment" rows="4" required placeholder="Write your comment..."></textarea>
               </div>
               
               <div style="display: flex; gap: 10px; margin-top: 20px;">
                   <button type="submit" class="bntm-btn-primary">Add Comment</button>
                   <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
               </div>
           </form>
       </div>
   </div>
   
   <style>
   .pm-kanban-board {
      display: flex;
      gap: 20px;
      margin-top: 20px;
      overflow-x: auto;
      overflow-y: hidden;
      white-space: nowrap;
      scroll-behavior: smooth;
      padding-bottom: 10px;
  }
  
  .pm-kanban-board::-webkit-scrollbar {
      height: 8px;
  }
  
  .pm-kanban-board::-webkit-scrollbar-thumb {
      background: #ccc;
      border-radius: 10px;
  }
  
  .pm-kanban-board::-webkit-scrollbar-thumb:hover {
      background: #999;
  }

   .pm-kanban-column {
       background: #f9fafb;
       border-radius: 8px;
       min-height: 500px;
       min-width: 300px;
   }
   .pm-kanban-header {
       padding: 15px;
       border-radius: 8px 8px 0 0;
       font-weight: 600;
   }
   .pm-kanban-header h4 {
       margin: 0;
       font-size: 16px;
   }
   .pm-kanban-tasks {
      padding: 15px;
      min-height: 400px;
      max-height: 600px;
      overflow: auto;
   }
   .pm-kanban-task-card {
       background: white;
       padding: 15px;
       border-radius: 6px;
       margin-bottom: 10px;
       border-left: 4px solid #3b82f6;
       cursor: move;
       transition: all 0.2s;
       box-shadow: 0 1px 3px rgba(0,0,0,0.1);
   }
   .pm-kanban-task-card:hover {
       box-shadow: 0 4px 6px rgba(0,0,0,0.1);
       transform: translateY(-2px);
   }
   .pm-kanban-task-card.dragging {
       opacity: 0.5;
   }
   .pm-kanban-tasks.drag-over {
       background: #eff6ff;
       border: 2px dashed #3b82f6;
   }
   .pm-task-detail-section {
       background: #f9fafb;
       padding: 15px;
       border-radius: 6px;
       margin-bottom: 15px;
   }
   .pm-task-detail-row {
       display: flex;
       gap: 30px;
       margin-bottom: 10px;
   }
   .pm-task-detail-item {
       flex: 1;
   }
   .pm-task-detail-label {
       font-size: 12px;
       color: #6b7280;
       font-weight: 500;
       margin-bottom: 5px;
   }
   .pm-task-detail-value {
       font-size: 14px;
       color: #1f2937;
       font-weight: 600;
   }
   .pm-comment-item {
       padding: 12px;
       background: white;
       border-radius: 6px;
       margin-bottom: 10px;
       border-left: 3px solid #3b82f6;
   }
   .pm-time-log-item {
       padding: 10px;
       background: white;
       border-radius: 6px;
       margin-bottom: 8px;
       display: flex;
       justify-content: space-between;
       align-items: center;
   }
   .pm-priority-badge {
       padding: 4px 12px;
       border-radius: 12px;
       font-size: 11px;
       font-weight: 600;
       text-transform: uppercase;
       white-space: nowrap;
   }
   .pm-priority-low {
       background: #dbeafe;
       color: #1e40af;
   }
   .pm-priority-medium {
       background: #fef3c7;
       color: #92400e;
   }
   .pm-priority-high {
       background: #fee2e2;
       color: #991b1b;
   }
   .pm-status-on_hold {
       background: #fed7aa;
       color: #ea580c;
   }
   .pm-status-closed {
       background: #e5e7eb;
       color: #374151;
   }
   .pm-kanban-view-btn {
       background: #3b82f6;
       color: white;
       border: none;
       padding: 6px 12px;
       border-radius: 4px;
       cursor: pointer;
       font-size: 12px;
       margin-top: 8px;
       width: 100%;
       transition: background 0.2s;
   }
   .pm-kanban-view-btn:hover {
       background: #2563eb;
   }
   </style>
   
   <script>
   (function() {
       const nonce = '<?php echo $nonce; ?>';
       
       // ========== MODAL CONTROLS ==========
       document.querySelectorAll('.close-modal').forEach(btn => {
           btn.addEventListener('click', function() {
               this.closest('.in-modal').style.display = 'none';
           });
       });
       
       // ========== FILTERS ==========
        document.getElementById('pm-project-filter').addEventListener('change', updateFilters);
       document.getElementById('pm-task-scope').addEventListener('change', updateFilters);

       function updateFilters() {
           const projectId = document.getElementById('pm-project-filter').value;
           const taskScope = document.getElementById('pm-task-scope').value;
           const params = new URLSearchParams(window.location.search);
           params.set('tab', 'kanban');
           if (projectId > 0) params.set('project_filter', projectId); else params.delete('project_filter');
           params.set('task_scope', taskScope);
           window.location.search = params.toString();
       }
       
       // ========== DRAG AND DROP ==========
       const taskCards = document.querySelectorAll('.pm-kanban-task-card');
       const columns = document.querySelectorAll('.pm-kanban-tasks');
       
       taskCards.forEach(card => {
           card.addEventListener('dragstart', function() {
               this.classList.add('dragging');
           });
           
           card.addEventListener('dragend', function() {
               this.classList.remove('dragging');
           });
       });
       
       columns.forEach(column => {
           column.addEventListener('dragover', function(e) {
               e.preventDefault();
               this.classList.add('drag-over');
           });
           
           column.addEventListener('dragleave', function() {
               this.classList.remove('drag-over');
           });
           
           column.addEventListener('drop', function(e) {
               e.preventDefault();
               this.classList.remove('drag-over');
               
               const draggingCard = document.querySelector('.dragging');
               if (draggingCard) {
                   const newStatus = this.id.replace('kanban-', '');
                   const taskId = draggingCard.getAttribute('data-task-id');
                   
                   // Update task status via AJAX
                   const formData = new FormData();
                   formData.append('action', 'pm_update_task_status');
                   formData.append('task_id', taskId);
                   formData.append('status', newStatus);
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
                           alert('Failed to update task status');
                       }
                   });
               }
           });
       });
       
       // ========== VIEW TASK (Using same function as project details) ==========
       document.querySelectorAll('.pm-kanban-view-btn').forEach(btn => {
           btn.addEventListener('click', function(e) {
               e.stopPropagation(); // Prevent drag event
               const taskId = this.getAttribute('data-task-id');
               loadTaskDetails(taskId);
           });
       });
       
       function loadTaskDetails(taskId) {
           const formData = new FormData();
           formData.append('action', 'pm_get_task_details');
           formData.append('task_id', taskId);
           formData.append('nonce', nonce);
           
           fetch(ajaxurl, {
               method: 'POST',
               body: formData
           })
           .then(r => r.json())
           .then(json => {
               if (json.success) {
                   displayTaskDetails(json.data);
                   document.getElementById('view-task-modal').style.display = 'flex';
               } else {
                   alert(json.data.message || 'Failed to load task details');
               }
           })
           .catch(err => {
               alert('Error loading task: ' + err.message);
           });
       }
       
       function displayTaskDetails(data) {
           const task = data.task;
           const comments = data.comments || [];
           const timeLogs = data.time_logs || [];
           
           const statusColors = {
               'todo': '#fef3c7',
               'in_progress': '#dbeafe',
               'done': '#d1fae5',
               'on_hold': '#fed7aa',
               'closed': '#e5e7eb'
           };
           
           const statusTextColors = {
               'todo': '#92400e',
               'in_progress': '#1e40af',
               'done': '#065f46',
               'on_hold': '#ea580c',
               'closed': '#374151'
           };
           
           let html = `
               <div class="pm-task-detail-section">
                   <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                       <div style="flex: 1;">
                           <h2 style="margin: 0 0 10px 0;">${escapeHtml(task.title)}</h2>
                           ${task.description ? `<p style="color: #6b7280; margin: 0;">${escapeHtml(task.description)}</p>` : ''}
                       </div>
                       <div style="display: flex; gap: 8px;">
                           <button class="bntm-btn-small" onclick="openEditTaskModal(${task.id})">Edit</button>
                           <button class="bntm-btn-small" onclick="openLogTimeModal(${task.id})">Log Time</button>
                           <button class="bntm-btn-small" onclick="openAddCommentModal(${task.id})">Comment</button>
                       </div>
                   </div>
                   
                   <div class="pm-task-detail-row">
                       <div class="pm-task-detail-item">
                           <div class="pm-task-detail-label">Status</div>
                           <div class="pm-task-detail-value">
                               <span style="background: ${statusColors[task.status]}; color: ${statusTextColors[task.status]}; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                   ${task.status.replace('_', ' ').toUpperCase()}
                               </span>
                           </div>
                       </div>
                       <div class="pm-task-detail-item">
                           <div class="pm-task-detail-label">Priority</div>
                           <div class="pm-task-detail-value">
                               <span class="pm-priority-badge pm-priority-${task.priority}">
                                   ${task.priority.toUpperCase()}
                               </span>
                           </div>
                       </div>
                       <div class="pm-task-detail-item">
                           <div class="pm-task-detail-label">Assigned To</div>
                           <div class="pm-task-detail-value">${task.assigned_name || 'Unassigned'}</div>
                       </div>
                   </div>
                   
                   <div class="pm-task-detail-row">
                       <div class="pm-task-detail-item">
                           <div class="pm-task-detail-label">Due Date</div>
                           <div class="pm-task-detail-value">${task.due_date ? formatDate(task.due_date) : 'Not set'}</div>
                       </div>
                       <div class="pm-task-detail-item">
                           <div class="pm-task-detail-label">Estimated Hours</div>
                           <div class="pm-task-detail-value">${task.estimated_hours || 0}h</div>
                       </div>
                       <div class="pm-task-detail-item">
                           <div class="pm-task-detail-label">Actual Hours</div>
                           <div class="pm-task-detail-value">${task.actual_hours || 0}h</div>
                       </div>
                   </div>
                   
                   ${task.tags ? `
                   <div style="margin-top: 10px;">
                       <div class="pm-task-detail-label">Tags</div>
                       <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 5px;">
                           ${task.tags.split(',').map(tag => `
                               <span style="background: #e0f2fe; color: #0c4a6e; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                   ${tag.trim()}
                               </span>
                           `).join('')}
                       </div>
                   </div>
                   ` : ''}
               </div>
               
               <!-- Time Logs -->
               <div style="margin-bottom: 20px;">
                   <h4 style="margin: 0 0 10px 0;">Time Logs (${timeLogs.length})</h4>
                   ${timeLogs.length > 0 ? `
                       <div class="pm-task-detail-section">
                           ${timeLogs.map(log => `
                               <div class="pm-time-log-item">
                                   <div>
                                       <strong>${log.user_name}</strong> logged <strong>${log.hours}h</strong>
                                       ${log.notes ? `<br><small style="color: #6b7280;">${escapeHtml(log.notes)}</small>` : ''}
                                   </div>
                                   <div style="text-align: right; font-size: 12px; color: #6b7280;">
                                       ${formatDate(log.log_date)}
                                   </div>
                               </div>
                           `).join('')}
                       </div>
                   ` : '<p style="color: #6b7280;">No time logged yet.</p>'}
               </div>
               
               <!-- Comments -->
               <div>
                   <h4 style="margin: 0 0 10px 0;">Comments (${comments.length})</h4>
                   ${comments.length > 0 ? `
                       <div class="pm-task-detail-section">
                           ${comments.map(comment => `
                               <div class="pm-comment-item">
                                   <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                       <strong style="color: #1f2937;">${comment.user_name}</strong>
                                       <span style="font-size: 12px; color: #9ca3af;">
                                           ${formatDateTime(comment.created_at)}
                                       </span>
                                   </div>
                                   <div style="color: #4b5563; white-space: pre-wrap;">${escapeHtml(comment.comment)}</div>
                               </div>
                           `).join('')}
                       </div>
                   ` : '<p style="color: #6b7280;">No comments yet.</p>'}
               </div>
           `;
           
           document.getElementById('task-details-content').innerHTML = html;
       }
       
       // ========== EDIT TASK ==========
       window.openEditTaskModal = function(taskId) {
           document.getElementById('view-task-modal').style.display = 'none';
           
           const formData = new FormData();
           formData.append('action', 'pm_get_task_details');
           formData.append('task_id', taskId);
           formData.append('nonce', nonce);
           
           fetch(ajaxurl, {
               method: 'POST',
               body: formData
           })
           .then(r => r.json())
           .then(json => {
               if (json.success) {
                   const task = json.data.task;
                   
                   document.getElementById('edit-task-id').value = task.id;
                   document.getElementById('edit-task-title').value = task.title;
                   document.getElementById('edit-task-description').value = task.description || '';
                   document.getElementById('edit-task-priority').value = task.priority;
                   document.getElementById('edit-task-status').value = task.status;
                   document.getElementById('edit-task-assigned').value = task.assigned_to || '';
                   document.getElementById('edit-task-due-date').value = task.due_date || '';
                   document.getElementById('edit-task-estimated-hours').value = task.estimated_hours || 0;
                   document.getElementById('edit-task-milestone').value = task.milestone_id || '';
                   document.getElementById('edit-task-tags').value = task.tags || '';
                   
                   document.getElementById('edit-task-modal').style.display = 'flex';
               } else {
                   alert('Failed to load task details');
               }
           });
       };
       
       document.getElementById('pm-edit-task-form').addEventListener('submit', function(e) {
           e.preventDefault();
           
           const formData = new FormData(this);
           formData.append('action', 'pm_update_task');
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
       
       // ========== LOG TIME ==========
       window.openLogTimeModal = function(taskId) {
           document.getElementById('view-task-modal').style.display = 'none';
           document.getElementById('log-time-task-id').value = taskId;
           document.getElementById('log-time-modal').style.display = 'flex';
       };
       
       document.getElementById('pm-log-time-form').addEventListener('submit', function(e) {
           e.preventDefault();
           
           const formData = new FormData(this);
           formData.append('action', 'pm_log_time');
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
                   btn.textContent = 'Log Time';
               }
           });
       });
       
       // ========== ADD COMMENT ==========
       window.openAddCommentModal = function(taskId) {
           document.getElementById('view-task-modal').style.display = 'none';
           document.getElementById('comment-task-id').value = taskId;
           document.getElementById('add-comment-modal').style.display = 'flex';
       };
       
       document.getElementById('pm-add-comment-form').addEventListener('submit', function(e) {
           e.preventDefault();
           
           const formData = new FormData(this);
           formData.append('action', 'pm_add_comment');
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
                   btn.textContent = 'Add Comment';
               }
           });
       });
       
       // ========== UTILITY FUNCTIONS ==========
       function escapeHtml(text) {
           const div = document.createElement('div');
           div.textContent = text;
           return div.innerHTML;
       }
       
       function formatDate(dateString) {
           const date = new Date(dateString);
           return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
       }
       
       function formatDateTime(dateString) {
           const date = new Date(dateString);
           const now = new Date();
           const diffMs = now - date;
           const diffMins = Math.floor(diffMs / 60000);
           const diffHours = Math.floor(diffMins / 60);
           const diffDays = Math.floor(diffHours / 24);
           
           if (diffMins < 1) return 'Just now';
           if (diffMins < 60) return diffMins + ' minutes ago';
           if (diffHours < 24) return diffHours + ' hour' + (diffHours > 1 ? 's' : '') + ' ago';
           if (diffDays < 7) return diffDays + ' day' + (diffDays > 1 ? 's' : '') + ' ago';
           
           return formatDate(dateString);
       }
   })();
   </script>
   <?php
   return ob_get_clean();
}

function pm_render_kanban_task($task) {
    ob_start();
    ?>
    <div class="pm-kanban-task-card" draggable="true" data-task-id="<?php echo $task->id; ?>" style="border-left-color: <?php echo esc_attr($task->project_color); ?>;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
            <h5 style="margin: 0; font-size: 14px; font-weight: 600; color: #1f2937;">
                <?php echo esc_html(substr($task->title, 0, 10)) . (strlen($task->title) > 10 ? '...' : ''); ?>
            </h5>
            <span class="pm-priority-badge pm-priority-<?php echo $task->priority; ?>" style="font-size: 10px; padding: 2px 8px;">
                <?php echo strtoupper(substr($task->priority, 0, 1)); ?>
            </span>
        </div>
        
        <?php if ($task->description): ?>
            <p style="color: #6b7280; font-size: 12px; margin: 5px 0; line-height: 1.4;">
                <?php //echo esc_html(substr($task->description, 0, 80)) . (strlen($task->description) > 80 ? '...' : ''); ?>
                <?php echo esc_html(substr($task->description, 0, 30)) . (strlen($task->description) > 30 ? '...' : ''); ?>
            </p>
        <?php endif; ?>
        
        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; font-size: 11px; color: #6b7280;">
            <span style="display: inline-block; padding: 2px 6px; background: <?php echo $task->project_color; ?>; color: white; border-radius: 3px;">
                <?php echo esc_html($task->project_name); ?>
            </span>
            <?php if ($task->assigned_name): ?>
                <span>👤 <?php echo esc_html($task->assigned_name); ?></span>
            <?php endif; ?>
            <?php if ($task->due_date): ?>
                <span style="color: <?php echo strtotime($task->due_date) < time() ? '#dc2626' : '#6b7280'; ?>;">
                    📅 <?php echo date('M d', strtotime($task->due_date)); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <!-- View Button -->
        <button class="pm-kanban-view-btn"  style="background: <?php echo esc_attr($task->project_color); ?>;" data-task-id="<?php echo $task->id; ?>">
            View Details
        </button>
    </div>
    <?php
    return ob_get_clean();
}
/* ---------- CALENDAR TAB ---------- */
function pm_calendar_tab($business_id) {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $current_user = get_current_user_id();

    // ===== Filter: All or My Tasks =====
    $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'all';
    $filter_condition = ($view === 'mine') ? "AND t.assigned_to = $current_user" : "";

    // ===== Current Month =====
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

    $first_day = date('Y-m-01', strtotime("$current_year-$current_month-01"));
    $last_day = date('Y-m-t', strtotime("$current_year-$current_month-01"));

    // ===== Fetch Tasks =====
    $tasks = $wpdb->get_results($wpdb->prepare("
        SELECT t.*, p.name as project_name, p.color as project_color, u.display_name as assigned_name
        FROM $tasks_table t
        INNER JOIN $projects_table p ON t.project_id = p.id
        LEFT JOIN {$wpdb->users} u ON t.assigned_to = u.ID
        WHERE t.due_date BETWEEN %s AND %s
        $filter_condition
        ORDER BY t.due_date ASC
    ", $first_day, $last_day));

    // ===== Organize Tasks by Date =====
    $tasks_by_date = [];
    foreach ($tasks as $task) {
        $tasks_by_date[$task->due_date][] = $task;
    }

    // ===== Month Navigation =====
    $prev_month = $current_month - 1;
    $prev_year = $current_year;
    if ($prev_month < 1) { $prev_month = 12; $prev_year--; }

    $next_month = $current_month + 1;
    $next_year = $current_year;
    if ($next_month > 12) { $next_month = 1; $next_year++; }

    $nonce = wp_create_nonce('pm_nonce');

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>

    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="?tab=calendar&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>&view=<?php echo $view; ?>" class="bntm-btn-secondary">← Previous</a>
                <h3 style="margin: 0;"><?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?></h3>
                <a href="?tab=calendar&month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>&view=<?php echo $view; ?>" class="bntm-btn-secondary">Next →</a>
            </div>

            <!-- Task Filter -->
            <select id="taskViewSelect" style="padding:6px 10px;border-radius:6px;border:1px solid #d1d5db;">
                <option value="all" <?php selected($view, 'all'); ?>>All Tasks</option>
                <option value="mine" <?php selected($view, 'mine'); ?>>My Tasks</option>
            </select>
        </div>
        
        <div class="pm-calendar">
            <div class="pm-calendar-header">
                <div>Sunday</div>
                <div>Monday</div>
                <div>Tuesday</div>
                <div>Wednesday</div>
                <div>Thursday</div>
                <div>Friday</div>
                <div>Saturday</div>
            </div>
            
            <div class="pm-calendar-body">
                <?php
                $first_day_of_month = date('w', strtotime("$current_year-$current_month-01"));
                $days_in_month = date('t', strtotime("$current_year-$current_month-01"));
                
                // Empty cells before first day
                for ($i = 0; $i < $first_day_of_month; $i++) {
                    echo '<div class="pm-calendar-day pm-calendar-day-empty"></div>';
                }
                
                // Days of the month
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                    $is_today = ($date === date('Y-m-d'));
                    $day_tasks = isset($tasks_by_date[$date]) ? $tasks_by_date[$date] : [];
                    
                    echo '<div class="pm-calendar-day' . ($is_today ? ' pm-calendar-day-today' : '') . '">';
                    echo '<div class="pm-calendar-day-number">' . $day . '</div>';
                    
                    if (!empty($day_tasks)) {
                         // Sort tasks: incomplete first, then completed (done/closed) at bottom
                         usort($day_tasks, function($a, $b) {
                             $a_completed = in_array($a->status, ['done', 'closed']) ? 1 : 0;
                             $b_completed = in_array($b->status, ['done', 'closed']) ? 1 : 0;
                             return $a_completed - $b_completed;
                         });
                         
                         echo '<div class="pm-calendar-tasks">';
                         foreach (array_slice($day_tasks, 0, 3) as $task) {
                             $is_completed = in_array($task->status, ['done', 'closed']);
                             $status_icon = $is_completed ? '✅' : ($task->status === 'in_progress' ? '🚀' : '📝');
                             
                             // Grey out completed tasks
                             $task_style = 'border-left-color: ' . esc_attr($task->project_color) . ';';
                             if ($is_completed) {
                                 $task_style .= ' opacity: 0.5; background-color: #f3f4f6; text-decoration: line-through;';
                             }
                             
                             echo '<div class="pm-calendar-task pm-calendar-task-clickable" 
                                        style="' . $task_style . '" 
                                        data-task-id="' . $task->id . '"
                                        data-project-id="' . $task->project_id . '"
                                        title="' . esc_attr($task->title . ($task->assigned_name ? ' - Assigned to: ' . $task->assigned_name : ' - Unassigned')) . '">';
                             echo $status_icon . ' ' . esc_html(substr($task->title, 0, 18)) . (strlen($task->title) > 18 ? '...' : '');
                             if ($task->assigned_name) {
                                 echo '<br><small style="font-size: 9px; color: ' . ($is_completed ? '#9ca3af' : '#6b7280') . ';">👤 ' . esc_html(substr($task->assigned_name, 0, 15)) . '</small>';
                             }
                             echo '</div>';
                         }
                         if (count($day_tasks) > 3) {
                             echo '<div style="font-size: 11px; color: #6b7280; padding: 2px 0; cursor: pointer;" class="pm-show-more-tasks" data-date="' . $date . '">+' . (count($day_tasks) - 3) . ' more</div>';
                         }
                         echo '</div>';
                     }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Task Quick View Modal -->
    <div id="calendar-task-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content" style="max-width: 700px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Task Details</h3>
                <button class="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
            </div>
            <div id="calendar-task-content"></div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button id="go-to-project-btn" class="bntm-btn-primary" style="display: none;">Go to Project</button>
                <button class="close-modal bntm-btn-secondary">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Day Tasks Modal (for "more" tasks) -->
    <div id="day-tasks-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="day-tasks-title">Tasks</h3>
                <button class="close-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">×</button>
            </div>
            <div id="day-tasks-list"></div>
        </div>
    </div>
    
    <style>
    .pm-calendar {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
    }
    .pm-calendar-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
    }
    .pm-calendar-header div {
        padding: 12px;
        text-align: center;
        font-weight: 600;
        font-size: 14px;
        color: #6b7280;
    }
    .pm-calendar-body {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
    .pm-calendar-day {
        min-height: 120px;
        padding: 8px;
        border: 1px solid #e5e7eb;
        background: white;
        transition: all 0.2s;
    }
    .pm-calendar-day:hover {
        background: #f9fafb;
    }
    .pm-calendar-day-empty {
        background: #f9fafb;
    }
    .pm-calendar-day-today {
        background: #eff6ff;
        border: 2px solid #3b82f6;
    }
    .pm-calendar-day-number {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 5px;
    }
    .pm-calendar-tasks {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }
    .pm-calendar-task {
        font-size: 11px;
        padding: 4px 6px;
        background: #f9fafb;
        border-radius: 3px;
        border-left: 3px solid #3b82f6;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }
    .pm-calendar-task-clickable {
        cursor: pointer;
        transition: all 0.2s;
    }
    .pm-calendar-task-clickable:hover {
        background: #e0f2fe;
        transform: translateX(2px);
    }
    .pm-show-more-tasks {
        padding: 2px 4px;
        border-radius: 3px;
        transition: all 0.2s;
    }
    .pm-show-more-tasks:hover {
        background: #e0f2fe;
        color: #0c4a6e;
    }
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
    </style>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        const tasksData = <?php echo json_encode($tasks_by_date); ?>;
        
           document.getElementById('taskViewSelect').addEventListener('change', function() {
              const view = this.value;
              const params = new URLSearchParams(window.location.search);
              params.set('view', view);
              window.location.search = params.toString();
          });
        // ========== CLOSE MODAL ==========
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // Close modal when clicking outside
        document.querySelectorAll('.in-modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // ========== CLICK ON TASK ==========
        document.querySelectorAll('.pm-calendar-task-clickable').forEach(task => {
            task.addEventListener('click', function() {
                const taskId = this.getAttribute('data-task-id');
                const projectId = this.getAttribute('data-project-id');
                loadCalendarTaskDetails(taskId, projectId);
            });
        });
        
        function loadCalendarTaskDetails(taskId, projectId) {
            const formData = new FormData();
            formData.append('action', 'pm_get_task_details');
            formData.append('task_id', taskId);
            formData.append('nonce', nonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    displayCalendarTaskDetails(json.data, projectId);
                    document.getElementById('calendar-task-modal').style.display = 'flex';
                } else {
                    alert(json.data.message || 'Failed to load task details');
                }
            })
            .catch(err => {
                alert('Error loading task: ' + err.message);
            });
        }
        
        function displayCalendarTaskDetails(data, projectId) {
            const task = data.task;
            
            const statusColors = {
                'todo': '#fef3c7',
                'in_progress': '#dbeafe',
                'on_hold': '#fed7aa',
                'done': '#d1fae5',
                'closed': '#e5e7eb'
            };
            
            const statusTextColors = {
                'todo': '#92400e',
                'in_progress': '#1e40af',
                'on_hold': '#ea580c',
                'done': '#065f46',
                'closed': '#374151'
            };
            
            let html = `
                <div style="background: #f9fafb; padding: 20px; border-radius: 8px;">
                    <h2 style="margin: 0 0 10px 0;">${escapeHtml(task.title)}</h2>
                    ${task.description ? `<p style="color: #6b7280; margin: 10px 0;">${escapeHtml(task.description)}</p>` : ''}
                    
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Status</div>
                            <span style="background: ${statusColors[task.status]}; color: ${statusTextColors[task.status]}; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                ${task.status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Priority</div>
                            <span class="pm-priority-badge pm-priority-${task.priority}">
                                ${task.priority.toUpperCase()}
                            </span>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Assigned To</div>
                            <div style="font-weight: 600;">${task.assigned_name || 'Unassigned'}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Due Date</div>
                            <div style="font-weight: 600;">${task.due_date ? formatDate(task.due_date) : 'Not set'}</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Estimated Hours</div>
                            <div style="font-weight: 600;">${task.estimated_hours || 0}h</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Actual Hours</div>
                            <div style="font-weight: 600;">${task.actual_hours || 0}h</div>
                        </div>
                    </div>
                    
                    ${task.tags ? `
                    <div style="margin-top: 15px;">
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Tags</div>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            ${task.tags.split(',').map(tag => `
                                <span style="background: #e0f2fe; color: #0c4a6e; padding: 2px 8px; border-radius: 4px; font-size: 12px;">
                                    ${tag.trim()}
                                </span>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('calendar-task-content').innerHTML = html;
            
            // Setup "Go to Project" button
            const goToProjectBtn = document.getElementById('go-to-project-btn');
            goToProjectBtn.style.display = 'inline-block';
            goToProjectBtn.onclick = function() {
                window.location.href = '?tab=projects&project_id=' + projectId;
            };
        }
        
        // ========== SHOW MORE TASKS ==========
        document.querySelectorAll('.pm-show-more-tasks').forEach(btn => {
            btn.addEventListener('click', function() {
                const date = this.getAttribute('data-date');
                showDayTasks(date);
            });
        });
        
       function showDayTasks(date) {
    const tasks = tasksData[date] || [];
    const dateObj = new Date(date);
    const formattedDate = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    document.getElementById('day-tasks-title').textContent = 'Tasks for ' + formattedDate;
    
    // Sort tasks: incomplete first, then completed (done/closed) at bottom
    const sortedTasks = tasks.sort((a, b) => {
        const aCompleted = ['done', 'closed'].includes(a.status) ? 1 : 0;
        const bCompleted = ['done', 'closed'].includes(b.status) ? 1 : 0;
        return aCompleted - bCompleted;
    });
    
    let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
    
    sortedTasks.forEach(task => {
        const isCompleted = ['done', 'closed'].includes(task.status);
        const statusIcon = isCompleted ? '✅' : (task.status === 'in_progress' ? '🚀' : '📝');
        
        // Grey out completed tasks
        const taskStyle = `border-left-color: ${task.project_color}; padding: 12px; font-size: 13px;` +
                         (isCompleted ? ' opacity: 0.5; background-color: #f3f4f6; text-decoration: line-through;' : '');
        
        const textColor = isCompleted ? '#9ca3af' : '#6b7280';
        
        html += `
            <div class="pm-calendar-task pm-calendar-task-clickable" 
                 style="${taskStyle}"
                 data-task-id="${task.id}"
                 data-project-id="${task.project_id}">
                ${statusIcon} <strong style="${isCompleted ? 'text-decoration: line-through;' : ''}">${escapeHtml(task.title)}</strong>
                <br>
                <small style="color: ${textColor};">
                    Project: ${escapeHtml(task.project_name)}
                    ${task.assigned_name ? ' • Assigned to: ' + escapeHtml(task.assigned_name) : ' • Unassigned'}
                </small>
            </div>
        `;
    });
    
    html += '</div>';
    
    document.getElementById('day-tasks-list').innerHTML = html;
    document.getElementById('day-tasks-modal').style.display = 'flex';
    
    // Add click handlers to the new task items
    document.querySelectorAll('#day-tasks-list .pm-calendar-task-clickable').forEach(task => {
        task.addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            const projectId = this.getAttribute('data-project-id');
            document.getElementById('day-tasks-modal').style.display = 'none';
            loadCalendarTaskDetails(taskId, projectId);
        });
    });
}
        
        // ========== UTILITY FUNCTIONS ==========
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- TEAM TAB ---------- */
function pm_team_tab($business_id) {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    
   // Get all users involved in projects
      $team_stats = $wpdb->get_results($wpdb->prepare(
          "SELECT 
              u.ID as user_id,
              u.display_name,
              u.user_email,
              COUNT(DISTINCT t.id) as total_tasks,
              SUM(CASE WHEN t.status IN ('done', 'closed') THEN 1 ELSE 0 END) as completed_tasks,
              SUM(CASE WHEN t.status NOT IN ('done', 'closed') AND t.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks,
              COALESCE(SUM(tl.hours), 0) as total_hours
          FROM {$wpdb->users} u
          LEFT JOIN $tasks_table t ON u.ID = t.assigned_to
          LEFT JOIN $projects_table p ON t.project_id = p.id 
          LEFT JOIN $time_logs_table tl ON t.id = tl.task_id
          WHERE u.ID IN (
              SELECT DISTINCT assigned_to FROM $tasks_table t2
              INNER JOIN $projects_table p2 ON t2.project_id = p2.id
              WHERE t2.assigned_to IS NOT NULL
          )
          GROUP BY u.ID
          ORDER BY total_tasks DESC",
          $business_id, $business_id
      ));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Team Performance</h3>
        
        <?php if (empty($team_stats)): ?>
            <p>No team members with assigned tasks yet.</p>
        <?php else: ?>
            <div class="pm-team-stats-grid">
                <?php foreach ($team_stats as $member): ?>
                    <div class="pm-team-stat-card">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 600;">
                                <?php echo strtoupper(substr($member->display_name, 0, 1)); ?>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 5px 0;"><?php echo esc_html($member->display_name); ?></h4>
                                <p style="margin: 0; color: #6b7280; font-size: 13px;">
                                    <?php echo esc_html($member->user_email); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="pm-team-metrics">
                            <div class="pm-team-metric">
                                <div class="pm-metric-value"><?php echo $member->total_tasks; ?></div>
                                <div class="pm-metric-label">Total Tasks</div>
                            </div>
                            <div class="pm-team-metric">
                                <div class="pm-metric-value" style="color: #059669;"><?php echo $member->completed_tasks; ?></div>
                                <div class="pm-metric-label">Completed</div>
                            </div>
                            <div class="pm-team-metric">
                                <div class="pm-metric-value" style="color: <?php echo $member->overdue_tasks > 0 ? '#dc2626' : '#6b7280'; ?>">
                                    <?php echo $member->overdue_tasks; ?>
                                </div>
                                <div class="pm-metric-label">Overdue</div>
                            </div>
                            <div class="pm-team-metric">
                                <div class="pm-metric-value"><?php echo number_format($member->total_hours, 1); ?>h</div>
                                <div class="pm-metric-label">Hours Logged</div>
                            </div>
                        </div>
                        
                        <?php if ($member->total_tasks > 0): ?>
                            <div style="margin-top: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span style="font-size: 12px; color: #6b7280;">Completion Rate</span>
                                    <span style="font-size: 12px; font-weight: 600;">
                                        <?php echo round(($member->completed_tasks / $member->total_tasks) * 100); ?>%
                                    </span>
                                </div>
                                <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                    <div style="background: #059669; height: 100%; width: <?php echo round(($member->completed_tasks / $member->total_tasks) * 100); ?>%;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
    .pm-team-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    .pm-team-stat-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }
    .pm-team-metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    .pm-team-metric {
        text-align: center;
    }
    .pm-metric-value {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
    }
    .pm-metric-label {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }
    </style>
    <?php
    return ob_get_clean();
}
/* ---------- REPORTS TAB ---------- */
function pm_reports_tab($business_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    // Project completion report
   $project_stats = $wpdb->get_results($wpdb->prepare(
       "SELECT 
           p.id,
           p.name,
           p.status,
           p.progress,
           p.due_date,
           COUNT(t.id) as total_tasks,
           SUM(CASE WHEN t.status IN ('done', 'closed') THEN 1 ELSE 0 END) as completed_tasks,
           SUM(t.estimated_hours) as estimated_hours,
           SUM(t.actual_hours) as actual_hours
       FROM $projects_table p
       LEFT JOIN $tasks_table t ON p.id = t.project_id
       GROUP BY p.id
       ORDER BY p.created_at DESC",
       $business_id
   ));
   
   // Task completion trend (last 30 days)
   $completion_trend = $wpdb->get_results($wpdb->prepare(
       "SELECT 
           DATE(t.completed_date) as completion_date,
           COUNT(*) as tasks_completed
       FROM $tasks_table t
       INNER JOIN $projects_table p ON t.project_id = p.id
       WHERE t.status IN ('done', 'closed')
           AND t.completed_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       GROUP BY DATE(t.completed_date)
       ORDER BY completion_date ASC",
       $business_id
   ));
    
    // Time tracking report
    $time_report = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            p.name as project_name,
            SUM(tl.hours) as total_hours,
            COUNT(DISTINCT tl.user_id) as team_members
        FROM $time_logs_table tl
        INNER JOIN $tasks_table t ON tl.task_id = t.id
        INNER JOIN $projects_table p ON t.project_id = p.id
        
        GROUP BY p.id
        ORDER BY total_hours DESC
        LIMIT 10",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>📊 Project Reports</h3>
        
        <!-- Project Completion Report -->
        <div style="margin-bottom: 30px;">
            <h4>Project Progress Overview</h4>
            <?php if (empty($project_stats)): ?>
                <p>No projects to report.</p>
            <?php else: ?>
                <table class="bntm-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Tasks</th>
                            <th>Est. Hours</th>
                            <th>Actual Hours</th>
                            <th>Variance</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($project_stats as $project): ?>
                            <tr>
                                <td><?php echo esc_html($project->name); ?></td>
                                <td>
                                    <span class="pm-status-badge pm-status-<?php echo $project->status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $project->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="flex: 1; background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                                            <div style="background: #3b82f6; height: 100%; width: <?php $percentage = $project->total_tasks > 0 ? number_format(($project->completed_tasks/$project->total_tasks)*100, 0) : '0'; echo $percentage; ?>%;"></div>
                                        </div>
                                        <span style="font-size: 12px; font-weight: 600;"><?php $percentage = $project->total_tasks > 0 ? number_format(($project->completed_tasks/$project->total_tasks)*100, 0) : '0'; echo $percentage; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo $project->completed_tasks; ?>/<?php echo $project->total_tasks; ?></td>
                                <td><?php echo number_format($project->estimated_hours, 1); ?>h</td>
                                <td><?php echo number_format($project->actual_hours, 1); ?>h</td>
                                <td style="color: <?php echo $project->actual_hours > $project->estimated_hours ? '#dc2626' : '#059669'; ?>;">
                                    <?php 
                                    if ($project->estimated_hours > 0) {
                                        $variance = (($project->actual_hours - $project->estimated_hours) / $project->estimated_hours) * 100;
                                        echo ($variance > 0 ? '+' : '') . number_format($variance, 0) . '%';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($project->due_date): ?>
                                        <span style="color: <?php echo strtotime($project->due_date) < time() && $project->status != 'completed' ? '#dc2626' : '#6b7280'; ?>;">
                                            <?php echo date('M d, Y', strtotime($project->due_date)); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">Not set</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Task Completion Trend -->
        <div style="margin-bottom: 30px;">
            <h4>Task Completion Trend (Last 30 Days)</h4>
            <?php if (empty($completion_trend)): ?>
                <p>No completed tasks in the last 30 days.</p>
            <?php else: ?>
                <div class="pm-chart-container">
                    <?php
                    $max_tasks = max(array_column($completion_trend, 'tasks_completed'));
                    foreach ($completion_trend as $data): 
                        $height = ($data->tasks_completed / $max_tasks) * 100;
                    ?>
                        <div class="pm-chart-bar" style="height: <?php echo $height; ?>%;" title="<?php echo $data->tasks_completed; ?> tasks on <?php echo date('M d', strtotime($data->completion_date)); ?>">
                            <span><?php echo $data->tasks_completed; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p style="text-align: center; color: #6b7280; font-size: 13px; margin-top: 10px;">
                    Total: <?php echo array_sum(array_column($completion_trend, 'tasks_completed')); ?> tasks completed
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Time Tracking Report -->
        <div>
            <h4>Time Tracking by Project (Top 10)</h4>
            <?php if (empty($time_report)): ?>
                <p>No time logged yet.</p>
            <?php else: ?>
                <table class="bntm-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Total Hours</th>
                            <th>Team Members</th>
                            <th>Avg Hours/Member</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_report as $report): ?>
                            <tr>
                                <td><?php echo esc_html($report->project_name); ?></td>
                                <td><?php echo number_format($report->total_hours, 1); ?>h</td>
                                <td><?php echo $report->team_members; ?></td>
                                <td><?php echo number_format($report->total_hours / $report->team_members, 1); ?>h</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .pm-chart-container {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 200px;
        padding: 20px;
        background: #f9fafb;
        border-radius: 8px;
        gap: 4px;
    }
    .pm-chart-bar {
        flex: 1;
        background: linear-gradient(to top, #3b82f6, #60a5fa);
        border-radius: 4px 4px 0 0;
        position: relative;
        min-height: 20px;
        transition: all 0.3s;
        cursor: pointer;
    }
    .pm-chart-bar:hover {
        background: linear-gradient(to top, #2563eb, #3b82f6);
    }
    .pm-chart-bar span {
        position: absolute;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 11px;
        font-weight: 600;
        color: #1f2937;
    }
    </style>
    <?php
    return ob_get_clean();
}

/* ---------- TASK AJAX HANDLERS ---------- */
function bntm_ajax_pm_add_task() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'project_id' => $project_id,
        'title' => sanitize_text_field($_POST['title']),
        'description' => sanitize_textarea_field($_POST['description']),
        'status' => sanitize_text_field($_POST['status']),
        'priority' => sanitize_text_field($_POST['priority']),
        'assigned_to' => !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
        'due_date' => !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null,
        'estimated_hours' => floatval($_POST['estimated_hours']),
        'milestone_id' => !empty($_POST['milestone_id']) ? intval($_POST['milestone_id']) : null,
        'tags' => sanitize_text_field($_POST['tags'])
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        $task_id = $wpdb->insert_id;
        pm_log_activity($business_id, $project_id, $task_id, 'created task', $data['title']);
        wp_send_json_success(['message' => 'Task created successfully!', 'task_id' => $task_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create task.']);
    }
}

function bntm_ajax_pm_update_task_status() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $task_id = intval($_POST['task_id']);
    $status = sanitize_text_field($_POST['status']);

    $update_data = ['status' => $status];
    
    if ($status === 'done') {
        $update_data['completed_date'] = current_time('mysql');
    }

    $result = $wpdb->update($table, $update_data, ['id' => $task_id]);

    if ($result !== false) {
        // Get task details for logging
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $task_id));
        pm_log_activity($business_id, $task->project_id, $task_id, 'updated task status to ' . $status, $task->title);
        
        // Update project progress
        pm_update_project_progress($task->project_id);
        
        wp_send_json_success(['message' => 'Task status updated!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update task status.']);
    }
}

function bntm_ajax_pm_delete_task() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $task_id = intval($_POST['task_id']);

    // Get task details before deletion
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $task_id));
    
    $result = $wpdb->delete($table, ['id' => $task_id]);

    if ($result) {
        pm_log_activity($business_id, $task->project_id, null, 'deleted task', $task->title);
        pm_update_project_progress($task->project_id);
        wp_send_json_success(['message' => 'Task deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete task.']);
    }
}

/* ---------- MILESTONE AJAX HANDLERS ---------- */
function bntm_ajax_pm_add_milestone() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_milestones';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'project_id' => $project_id,
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'due_date' => !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null,
        'status' => sanitize_text_field($_POST['status'])
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        $milestone_id = $wpdb->insert_id;
        pm_log_activity($business_id, $project_id, null, 'created milestone', $data['name']);
        wp_send_json_success(['message' => 'Milestone created successfully!', 'milestone_id' => $milestone_id]);
    } else {
        wp_send_json_error(['message' => 'Failed to create milestone.']);
    }
}

function bntm_ajax_pm_delete_milestone() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $milestones_table = $wpdb->prefix . 'pm_milestones';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $milestone_id = intval($_POST['milestone_id']);

    // Get milestone details before deletion
    $milestone = $wpdb->get_row($wpdb->prepare("SELECT * FROM $milestones_table WHERE id = %d", $milestone_id));
    
    // Unassign tasks from this milestone
    $wpdb->update($tasks_table, ['milestone_id' => null], ['milestone_id' => $milestone_id]);
    
    // Delete milestone
    $result = $wpdb->delete($milestones_table, ['id' => $milestone_id]);

    if ($result) {
        pm_log_activity($business_id, $milestone->project_id, null, 'deleted milestone', $milestone->name);
        wp_send_json_success(['message' => 'Milestone deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete milestone.']);
    }
}

/* ---------- TEAM AJAX HANDLERS ---------- */
function bntm_ajax_pm_add_team_member() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_team_members';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);
    $user_id = intval($_POST['user_id']);

    // Check if already a member
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE project_id = %d AND user_id = %d",
        $project_id, $user_id
    ));

    if ($exists) {
        wp_send_json_error(['message' => 'User is already a team member.']);
    }

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'project_id' => $project_id,
        'user_id' => $user_id,
        'role' => sanitize_text_field($_POST['role'])
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        $user = get_user_by('id', $user_id);
        pm_log_activity($business_id, $project_id, null, 'added team member', $user->display_name);
        wp_send_json_success(['message' => 'Team member added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add team member.']);
    }
}

function bntm_ajax_pm_remove_team_member() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_team_members';
    $business_id = get_current_user_id();
    $member_id = intval($_POST['member_id']);

    // Get member details before deletion
    $member = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $member_id));
    $user = get_user_by('id', $member->user_id);
    
    $result = $wpdb->delete($table, ['id' => $member_id]);

    if ($result) {
        pm_log_activity($business_id, $member->project_id, null, 'removed team member', $user->display_name);
        wp_send_json_success(['message' => 'Team member removed successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to remove team member.']);
    }
}

/* ---------- TIME LOG AJAX HANDLERS ---------- */
function bntm_ajax_pm_log_time() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $time_logs_table = $wpdb->prefix . 'pm_time_logs';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $task_id = intval($_POST['task_id']);

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'task_id' => $task_id,
        'user_id' => $business_id,
        'hours' => floatval($_POST['hours']),
        'log_date' => sanitize_text_field($_POST['log_date']),
        'notes' => sanitize_textarea_field($_POST['notes'])
    ];

    $result = $wpdb->insert($time_logs_table, $data);

    if ($result) {
        // Update task actual hours
        $wpdb->query($wpdb->prepare(
            "UPDATE $tasks_table SET actual_hours = (
                SELECT SUM(hours) FROM $time_logs_table WHERE task_id = %d
            ) WHERE id = %d",
            $task_id, $task_id
        ));
        
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tasks_table WHERE id = %d", $task_id));
        pm_log_activity($business_id, $task->project_id, $task_id, 'logged time', $data['hours'] . ' hours');
        
        wp_send_json_success(['message' => 'Time logged successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to log time.']);
    }
}

/* ---------- FILE AJAX HANDLERS ---------- */
function bntm_ajax_pm_upload_file() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    if (!isset($_FILES['file'])) {
        wp_send_json_error(['message' => 'No file uploaded']);
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    global $wpdb;
    $table = $wpdb->prefix . 'pm_files';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);

    $file = $_FILES['file'];
    $upload = wp_handle_upload($file, ['test_form' => false]);

    if (isset($upload['error'])) {
        wp_send_json_error(['message' => $upload['error']]);
    }

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'project_id' => $project_id,
        'filename' => basename($upload['file']),
        'file_url' => $upload['url'],
        'file_size' => filesize($upload['file']),
        'file_type' => $upload['type'],
        'uploaded_by' => $business_id
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        pm_log_activity($business_id, $project_id, null, 'uploaded file', $data['filename']);
        wp_send_json_success(['message' => 'File uploaded successfully!', 'file_url' => $upload['url']]);
    } else {
        wp_send_json_error(['message' => 'Failed to save file record.']);
    }
}

function bntm_ajax_pm_delete_file() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_files';
    $business_id = get_current_user_id();
    $file_id = intval($_POST['file_id']);

    // Get file details before deletion
    $file = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $file_id));
    
    $result = $wpdb->delete($table, ['id' => $file_id]);

    if ($result) {
        pm_log_activity($business_id, $file->project_id, null, 'deleted file', $file->filename);
        wp_send_json_success(['message' => 'File deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete file.']);
    }
}

/* ---------- COMMENT AJAX HANDLERS ---------- */
function bntm_ajax_pm_add_comment() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pm_comments';
    $business_id = get_current_user_id();
    $task_id = intval($_POST['task_id']);

    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'task_id' => $task_id,
        'user_id' => $business_id,
        'comment' => sanitize_textarea_field($_POST['comment'])
    ];

    $result = $wpdb->insert($table, $data);

    if ($result) {
        $tasks_table = $wpdb->prefix . 'pm_tasks';
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tasks_table WHERE id = %d", $task_id));
        pm_log_activity($business_id, $task->project_id, $task_id, 'commented on task', substr($data['comment'], 0, 50));
        
        wp_send_json_success(['message' => 'Comment added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add comment.']);
    }
}
/* ---------- HELPER FUNCTIONS ---------- */
function pm_log_activity($business_id, $project_id, $task_id, $action, $details) {
    global $wpdb;
    $table = $wpdb->prefix . 'pm_activity_log';
    
    // Get the WordPress current local time (based on site timezone)
    $wp_time = current_time('mysql', false); // false = local time, true = GMT
    
    $wpdb->insert($table, [
        'rand_id'     => bntm_rand_id(),
        'business_id' => $business_id,
        'project_id'  => $project_id,
        'task_id'     => $task_id,
        'user_id'     => get_current_user_id(),
        'action'      => sanitize_text_field($action),
        'details'     => sanitize_textarea_field($details),
        'created_at'  => $wp_time,
    ]);
}

function pm_update_project_progress($project_id) {
    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    
    // Calculate progress based on completed tasks
    $total_tasks = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tasks_table WHERE project_id = %d",
        $project_id
    ));
    
    if ($total_tasks > 0) {
       $completed_tasks = $wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM $tasks_table WHERE project_id = %d AND status IN ('done', 'closed')",
          $project_id
      ));
        
        $progress = round(($completed_tasks / $total_tasks) * 100);
        
        $wpdb->update($projects_table, 
            ['progress' => $progress],
            ['id' => $project_id]
        );
    }
}

function bntm_ajax_pm_get_project_data() {
    check_ajax_referer('pm_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $projects_table = $wpdb->prefix . 'pm_projects';
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $business_id = get_current_user_id();
    $project_id = intval($_POST['project_id']);

    $project = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $projects_table WHERE id = %d ",
        $project_id, $business_id
    ));

    if (!$project) {
        wp_send_json_error(['message' => 'Project not found']);
    }

    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $tasks_table WHERE project_id = %d ORDER BY created_at DESC",
        $project_id
    ));

    wp_send_json_success([
        'project' => $project,
        'tasks' => $tasks
    ]);
}

/* ---------- NOTIFICATION FUNCTIONS ---------- */
function pm_send_task_notification($task_id, $type = 'assigned') {
    global $wpdb;
    $tasks_table = $wpdb->prefix . 'pm_tasks';
    $projects_table = $wpdb->prefix . 'pm_projects';
    
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, p.name as project_name 
        FROM $tasks_table t
        INNER JOIN $projects_table p ON t.project_id = p.id
        WHERE t.id = %d",
        $task_id
    ));
    
    if (!$task || !$task->assigned_to) {
        return;
    }
    
    $user = get_user_by('id', $task->assigned_to);
    if (!$user) {
        return;
    }
    
    $subject = '';
    $message = '';
    
    switch ($type) {
        case 'assigned':
            $subject = 'New Task Assigned: ' . $task->title;
            $message = "You have been assigned a new task:\n\n";
            $message .= "Project: " . $task->project_name . "\n";
            $message .= "Task: " . $task->title . "\n";
            $message .= "Priority: " . ucfirst($task->priority) . "\n";
            if ($task->due_date) {
                $message .= "Due Date: " . date('M d, Y', strtotime($task->due_date)) . "\n";
            }
            break;
            
        case 'reminder':
            $subject = 'Task Due Soon: ' . $task->title;
            $message = "Reminder: Your task is due soon:\n\n";
            $message .= "Project: " . $task->project_name . "\n";
            $message .= "Task: " . $task->title . "\n";
            $message .= "Due Date: " . date('M d, Y', strtotime($task->due_date)) . "\n";
            break;
            
        case 'overdue':
            $subject = 'Task Overdue: ' . $task->title;
            $message = "Alert: Your task is overdue:\n\n";
            $message .= "Project: " . $task->project_name . "\n";
            $message .= "Task: " . $task->title . "\n";
            $message .= "Due Date: " . date('M d, Y', strtotime($task->due_date)) . "\n";
            break;
    }
    
    if ($subject && $message) {
        wp_mail($user->user_email, $subject, $message);
    }
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