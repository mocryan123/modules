<?php
/**
 * Module Name: Class Scheduler
 * Module Slug: cs
 * Description: Organizes weekly timetables for multiple class sections with time slots, instructors, and room assignments.
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: calendar
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_CS_PATH', dirname(__FILE__) . '/');
define('BNTM_CS_URL', plugin_dir_url(__FILE__));

// ============================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================

function bntm_cs_get_pages() {
    return [
        'Class Scheduler'         => '[bntm_class_scheduler]',
        'Section Timetable'       => '[bntm_section_timetable]',
    ];
}

function bntm_cs_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'cs_sections' => "CREATE TABLE {$prefix}cs_sections (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            section_name VARCHAR(100) NOT NULL,
            course_code VARCHAR(20) NOT NULL,
            year_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
            block VARCHAR(10) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            semester VARCHAR(20) NOT NULL DEFAULT 'First',
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status)
        ) {$charset};",

        'cs_schedules' => "CREATE TABLE {$prefix}cs_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            section_id BIGINT UNSIGNED NOT NULL,
            subject_code VARCHAR(50) NOT NULL,
            subject_name VARCHAR(150) NOT NULL DEFAULT '',
            instructor_initials VARCHAR(30) NOT NULL,
            instructor_name VARCHAR(100) NOT NULL DEFAULT '',
            room VARCHAR(30) NOT NULL,
            day_group ENUM('mon_thu','tue_fri','wed_sat') NOT NULL,
            time_slot VARCHAR(20) NOT NULL,
            units TINYINT UNSIGNED NOT NULL DEFAULT 3,
            schedule_type ENUM('lecture','lab','both') NOT NULL DEFAULT 'lecture',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_section (section_id),
            INDEX idx_business (business_id),
            INDEX idx_day_time (day_group, time_slot)
        ) {$charset};",

        'cs_instructors' => "CREATE TABLE {$prefix}cs_instructors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            full_name VARCHAR(150) NOT NULL,
            initials VARCHAR(30) NOT NULL,
            department VARCHAR(100) NOT NULL DEFAULT '',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",

        'cs_rooms' => "CREATE TABLE {$prefix}cs_rooms (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            room_code VARCHAR(30) NOT NULL,
            building VARCHAR(80) NOT NULL DEFAULT '',
            capacity SMALLINT UNSIGNED NOT NULL DEFAULT 40,
            room_type ENUM('lecture','lab','both') NOT NULL DEFAULT 'lecture',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",
    ];
}

function bntm_cs_get_shortcodes() {
    return [
        'bntm_class_scheduler'  => 'bntm_shortcode_class_scheduler',
        'bntm_section_timetable'=> 'bntm_shortcode_section_timetable',
    ];
}

function bntm_cs_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_cs_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// ============================================================
// AJAX ACTION HOOKS
// ============================================================

add_action('wp_ajax_cs_save_section',        'bntm_ajax_cs_save_section');
add_action('wp_ajax_cs_delete_section',      'bntm_ajax_cs_delete_section');
add_action('wp_ajax_cs_save_schedule',       'bntm_ajax_cs_save_schedule');
add_action('wp_ajax_cs_delete_schedule',     'bntm_ajax_cs_delete_schedule');
add_action('wp_ajax_cs_get_section_data',    'bntm_ajax_cs_get_section_data');
add_action('wp_ajax_cs_save_instructor',     'bntm_ajax_cs_save_instructor');
add_action('wp_ajax_cs_delete_instructor',   'bntm_ajax_cs_delete_instructor');
add_action('wp_ajax_cs_save_room',           'bntm_ajax_cs_save_room');
add_action('wp_ajax_cs_delete_room',         'bntm_ajax_cs_delete_room');
add_action('wp_ajax_cs_bulk_import_section', 'bntm_ajax_cs_bulk_import_section');

// ============================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================

function bntm_shortcode_class_scheduler() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Class Scheduler.</div>';
    }

    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>

    <div class="bntm-cs-container">
        <!-- Tab Navigation -->
        <div class="bntm-tabs">
            <a href="?tab=overview"    class="bntm-tab <?php echo $active_tab === 'overview'    ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=timetable"   class="bntm-tab <?php echo $active_tab === 'timetable'   ? 'active' : ''; ?>">Timetable View</a>
            <a href="?tab=sections"    class="bntm-tab <?php echo $active_tab === 'sections'    ? 'active' : ''; ?>">Sections</a>
            <a href="?tab=schedule"    class="bntm-tab <?php echo $active_tab === 'schedule'    ? 'active' : ''; ?>">Schedule Entry</a>
            <a href="?tab=instructors" class="bntm-tab <?php echo $active_tab === 'instructors' ? 'active' : ''; ?>">Instructors</a>
            <a href="?tab=rooms"       class="bntm-tab <?php echo $active_tab === 'rooms'       ? 'active' : ''; ?>">Rooms</a>
        </div>

        <!-- Tab Content -->
        <div class="bntm-tab-content">
            <?php
            if      ($active_tab === 'overview')    echo cs_overview_tab($business_id);
            elseif  ($active_tab === 'timetable')   echo cs_timetable_tab($business_id);
            elseif  ($active_tab === 'sections')    echo cs_sections_tab($business_id);
            elseif  ($active_tab === 'schedule')    echo cs_schedule_tab($business_id);
            elseif  ($active_tab === 'instructors') echo cs_instructors_tab($business_id);
            elseif  ($active_tab === 'rooms')       echo cs_rooms_tab($business_id);
            ?>
        </div>
    </div>

    <!-- Global Modal -->
    <div id="cs-modal-overlay" class="cs-modal-overlay" style="display:none;">
        <div class="cs-modal" id="cs-modal">
            <div class="cs-modal-header">
                <h3 id="cs-modal-title">Modal</h3>
                <button class="cs-modal-close" id="cs-modal-close">&times;</button>
            </div>
            <div class="cs-modal-body" id="cs-modal-body"></div>
        </div>
    </div>

    <!-- Global Confirm Dialog -->
    <div id="cs-confirm-overlay" class="cs-modal-overlay" style="display:none;">
        <div class="cs-modal cs-modal-sm">
            <div class="cs-modal-header">
                <h3 id="cs-confirm-title">Confirm Action</h3>
            </div>
            <div class="cs-modal-body">
                <p id="cs-confirm-message"></p>
                <div class="cs-btn-row" style="margin-top:20px;">
                    <button id="cs-confirm-yes" class="bntm-btn-danger">Confirm</button>
                    <button id="cs-confirm-no"  class="bntm-btn-secondary">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* ---- Global CS Styles ---- */
    .bntm-cs-container { font-family: 'Inter', Arial, sans-serif; }

    /* Modal */
    .cs-modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,.45);
        z-index: 9999;
        display: flex; align-items: center; justify-content: center;
    }
    .cs-modal {
        background: #fff;
        border-radius: 12px;
        width: 100%; max-width: 680px;
        max-height: 90vh; overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,.25);
        animation: csModalIn .18s ease;
    }
    .cs-modal-sm { max-width: 420px; }
    @keyframes csModalIn { from { transform: translateY(-16px); opacity:0; } to { transform: none; opacity:1; } }
    .cs-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 20px 24px 16px;
        border-bottom: 1px solid #e5e7eb;
    }
    .cs-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #111827; }
    .cs-modal-close {
        background: none; border: none; cursor: pointer;
        font-size: 22px; line-height: 1; color: #6b7280;
        padding: 0 4px; border-radius: 4px;
        transition: color .15s;
    }
    .cs-modal-close:hover { color: #111827; }
    .cs-modal-body { padding: 24px; }

    /* Timetable styles */
    .cs-section-block {
        margin-bottom: 40px;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
    }
    .cs-section-header {
        background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
        color: #fff;
        padding: 14px 20px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .cs-section-header h3 { margin: 0; font-size: 15px; font-weight: 700; letter-spacing: .04em; }
    .cs-section-meta { font-size: 12px; opacity: .8; }
    
    /* Day Tables */
    .cs-day-tables, .cs-day-tables-simple { 
        display: grid; 
        grid-template-columns: 90px repeat(7,1fr); 
        gap: 0;
        border: 1px solid #e5e7eb;
        border-collapse: collapse;
    }
    
    /* Grid Headers (Time label and Day headers) */
    .cs-grid-header {
        background: #f0f4ff;
        text-align: center;
        padding: 10px 4px;
        font-size: 11px;
        font-weight: 700;
        color: #1e40af;
        text-transform: uppercase;
        letter-spacing: .06em;
        border-bottom: 2px solid #1e40af;
        border-right: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 40px;
    }
    .cs-grid-header:last-child {
        border-right: none;
    }
    
    /* Time Label Cell */
    .cs-grid-time-cell {
        background: #f8fafc;
        text-align: center;
        padding: 8px 4px;
        font-size: 9px;
        font-weight: 600;
        color: #4b5563;
        border-bottom: 1px solid #e5e7eb;
        border-right: 2px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80px;
    }
    
    /* Day Time Cell (for classes) */
    .cs-grid-day-cell {
        border-bottom: 1px solid #e5e7eb;
        border-right: 1px solid #e5e7eb;
        padding: 6px;
        min-height: 80px;
        background: #fafbfc;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .cs-grid-day-cell:last-child {
        border-right: none;
    }
    
    /* Old table-based layout (kept for backward compatibility) */
    .cs-day-table-wrap { border-right: 1px solid #e5e7eb; }
    .cs-day-table-wrap:last-child { border-right: none; }
    .cs-day-group-header {
        background: #f0f4ff;
        text-align: center;
        padding: 8px 4px;
        font-size: 11px; font-weight: 700;
        color: #1e40af;
        text-transform: uppercase;
        letter-spacing: .06em;
        border-bottom: 1px solid #e5e7eb;
    }
    .cs-timetable {
        width: 100%; border-collapse: collapse;
        font-size: 11px;
    }
    .cs-timetable thead th {
        background: #f8fafc;
        padding: 7px 6px;
        text-align: center;
        font-weight: 700;
        font-size: 10px;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-bottom: 2px solid #e5e7eb;
    }
    .cs-timetable td {
        border: 1px solid #e5e7eb;
        padding: 4px 5px;
        vertical-align: top;
        min-height: 52px;
    }
    .cs-time-cell {
        background: #f8fafc;
        color: #4b5563;
        font-weight: 700;
        font-size: 10px;
        white-space: nowrap;
        text-align: center;
        width: 68px;
    }
    .cs-slot-cell { flex: 1; }
    .cs-slot {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 5px;
        padding: 4px 5px;
        min-height: 48px;
    }
    .cs-slot-subject {
        font-weight: 700;
        color: #1e40af;
        font-size: 10px;
        line-height: 1.3;
    }
    .cs-slot-lab .cs-slot { background: #f0fdf4; border-color: #bbf7d0; }
    .cs-slot-lab .cs-slot-subject { color: #166534; }
    .cs-slot-instructor { color: #6b7280; font-size: 9px; margin-top: 2px; }
    .cs-slot-room {
        font-size: 9px; margin-top: 2px;
        color: #9333ea; font-weight: 600;
    }
    .cs-slot-empty { min-height: 48px; }
    
    /* New simple column layout */
    .cs-day-column {
        border-right: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
    }
    .cs-day-column:last-child { border-right: none; }
    .cs-day-column-header {
        background: #f0f4ff;
        text-align: center;
        padding: 10px 4px;
        font-size: 12px; font-weight: 700;
        color: #1e40af;
        text-transform: uppercase;
        letter-spacing: .06em;
        border-bottom: 1px solid #e5e7eb;
    }
    .cs-day-column-content {
        flex: 1;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-height: 300px;
        background: #fafbfc;
    }
    .cs-day-class-block {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 5px;
        padding: 6px;
        font-size: 11px;
    }
    .cs-day-class-block.cs-block-lab {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .cs-block-subject {
        font-weight: 700;
        color: #1e40af;
        font-size: 11px;
        line-height: 1.3;
    }
    .cs-day-class-block.cs-block-lab .cs-block-subject {
        color: #166534;
    }
    .cs-block-time {
        color: #6b7280;
        font-size: 9px;
        margin-top: 2px;
    }
    .cs-block-instructor {
        color: #6b7280;
        font-size: 9px;
        margin-top: 1px;
    }
    .cs-block-room {
        font-size: 9px;
        margin-top: 1px;
        color: #9333ea;
        font-weight: 600;
    }
    .cs-day-empty {
        color: #9ca3af;
        font-size: 12px;
        text-align: center;
        padding: 20px 4px;
        font-style: italic;
    }
        .cs-section-block { page-break-after: always; box-shadow: none; }
        .cs-modal-overlay { display: none !important; }
        .bntm-tabs { display: none; }
    }

    /* Button row */
    .cs-btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
    .cs-filter-bar {
        display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
        background: #f8fafc; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 12px 16px;
        margin-bottom: 20px;
    }
    .cs-filter-bar select,
    .cs-filter-bar input { flex: 1; min-width: 140px; }

    /* Conflict badge */
    .cs-conflict-badge {
        background: #fef2f2; border: 1px solid #fecaca;
        color: #b91c1c; border-radius: 4px;
        padding: 2px 6px; font-size: 10px; font-weight: 700;
    }

    /* Action links in table */
    .cs-action-link {
        background: none; border: none; cursor: pointer;
        font-size: 12px; padding: 2px 6px; border-radius: 4px;
        transition: background .15s;
    }
    .cs-action-link:hover { background: #f3f4f6; }
    .cs-action-link.edit  { color: #2563eb; }
    .cs-action-link.delete { color: #dc2626; }
    </style>

    <script>
    (function() {
        // ---------- Modal helpers ----------
        const overlay   = document.getElementById('cs-modal-overlay');
        const modal     = document.getElementById('cs-modal');
        const modalTitle= document.getElementById('cs-modal-title');
        const modalBody = document.getElementById('cs-modal-body');
        const closeBtn  = document.getElementById('cs-modal-close');

        window.csOpenModal = function(title, bodyHTML) {
            modalTitle.textContent = title;
            modalBody.innerHTML    = bodyHTML;
            overlay.style.display  = 'flex';
        };
        window.csCloseModal = function() { overlay.style.display = 'none'; };

        if (closeBtn) closeBtn.addEventListener('click', csCloseModal);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) csCloseModal(); });

        // ---------- Confirm dialog ----------
        const cOverlay  = document.getElementById('cs-confirm-overlay');
        const cMessage  = document.getElementById('cs-confirm-message');
        const cYes      = document.getElementById('cs-confirm-yes');
        const cNo       = document.getElementById('cs-confirm-no');

        window.csConfirm = function(message, onYes) {
            cMessage.textContent   = message;
            cOverlay.style.display = 'flex';
            cYes.onclick = function() { cOverlay.style.display = 'none'; onYes(); };
        };
        if (cNo) cNo.addEventListener('click', function() { cOverlay.style.display = 'none'; });

        // ---------- Toast ----------
        window.csToast = function(msg, type) {
            type = type || 'success';
            const t = document.createElement('div');
            t.style.cssText = 'position:fixed;top:24px;right:24px;z-index:99999;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);animation:csModalIn .18s ease;';
            t.style.background = type === 'success' ? '#059669' : '#dc2626';
            t.style.color = '#fff';
            t.textContent = msg;
            document.body.appendChild(t);
            setTimeout(function() { t.remove(); }, 3200);
        };

        // ---------- Generic AJAX helper ----------
        window.csAjax = function(action, data, onSuccess, onError) {
            const fd = new FormData();
            fd.append('action', action);
            Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    if (json.success) { if (onSuccess) onSuccess(json.data); }
                    else { if (onError) onError(json.data); else csToast((json.data && json.data.message) || 'Error', 'error'); }
                })
                .catch(function(err) { csToast('Network error', 'error'); });
        };
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Class Scheduler', $content);
}

// ============================================================
// TAB: OVERVIEW
// ============================================================

function cs_overview_tab($business_id) {
    global $wpdb;
    $s_table = $wpdb->prefix . 'cs_sections';
    $sch_table = $wpdb->prefix . 'cs_schedules';
    $i_table = $wpdb->prefix . 'cs_instructors';
    $r_table = $wpdb->prefix . 'cs_rooms';

    $total_sections    = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$s_table} WHERE business_id=%d AND status='active'", $business_id));
    $total_schedules   = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$sch_table} WHERE business_id=%d", $business_id));
    $total_instructors = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$i_table} WHERE business_id=%d AND status='active'", $business_id));
    $total_rooms       = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$r_table} WHERE business_id=%d AND status='active'", $business_id));

    ob_start();
    ?>
    <div class="bntm-stats-row">
        <!-- Sections -->
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Sections</h3>
                <p class="stat-number"><?php echo number_format($total_sections); ?></p>
                <span class="stat-label">Active sections</span>
            </div>
        </div>

        <!-- Scheduled Slots -->
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #059669 0%, #34d399 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Schedule Slots</h3>
                <p class="stat-number"><?php echo number_format($total_schedules); ?></p>
                <span class="stat-label">Entries across all sections</span>
            </div>
        </div>

        <!-- Instructors -->
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #7c3aed 0%, #a78bfa 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Instructors</h3>
                <p class="stat-number"><?php echo number_format($total_instructors); ?></p>
                <span class="stat-label">Active faculty</span>
            </div>
        </div>

        <!-- Rooms -->
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #d97706 0%, #fbbf24 100%);">
                <svg width="24" height="24" fill="none" stroke="white" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3>Rooms</h3>
                <p class="stat-number"><?php echo number_format($total_rooms); ?></p>
                <span class="stat-label">Available rooms</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bntm-form-section">
        <h3>Quick Actions</h3>
        <div class="cs-btn-row">
            <a href="?tab=sections"    class="bntm-btn-primary">Add New Section</a>
            <a href="?tab=schedule"    class="bntm-btn-primary">Add Schedule Entry</a>
            <a href="?tab=timetable"   class="bntm-btn-secondary">View Timetables</a>
            <a href="?tab=instructors" class="bntm-btn-secondary">Manage Instructors</a>
            <a href="?tab=rooms"       class="bntm-btn-secondary">Manage Rooms</a>
        </div>
    </div>

    <!-- How it Works -->
    <div class="bntm-form-section">
        <h3>How It Works</h3>
        <p style="color:#6b7280;line-height:1.7;">
            The Class Scheduler organizes weekly timetables for multiple sections.
            Each section displays a comprehensive seven-column timetable showing <strong>Monday through Sunday</strong>
            with fixed time slots from <strong>7:30 AM to 7:30 PM</strong>.
            Cells show the subject code, instructor initials, and room assignment.
            Empty slots indicate free periods. Use <strong>Timetable View</strong> to see all sections at once,
            or print individual section sheets.
        </p>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: TIMETABLE VIEW (core feature)
// ============================================================

function cs_timetable_tab($business_id) {
    global $wpdb;
    $s_table   = $wpdb->prefix . 'cs_sections';
    $sch_table = $wpdb->prefix . 'cs_schedules';

    // Filter options
    $filter_section = isset($_GET['cs_section']) ? intval($_GET['cs_section']) : 0;
    $filter_ay      = isset($_GET['cs_ay'])      ? sanitize_text_field($_GET['cs_ay'])  : '';
    $filter_sem     = isset($_GET['cs_sem'])      ? sanitize_text_field($_GET['cs_sem']) : '';

    $sections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$s_table} WHERE business_id=%d AND status='active' ORDER BY section_name ASC",
        $business_id
    ));

    // Academic years available
    $ay_list = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT academic_year FROM {$s_table} WHERE business_id=%d ORDER BY academic_year DESC",
        $business_id
    ));

    // Apply filter
    $where     = "WHERE s.business_id=%d AND s.status='active'";
    $where_vals= [$business_id];
    if ($filter_section) { $where .= " AND s.id=%d"; $where_vals[] = $filter_section; }
    if ($filter_ay)      { $where .= " AND s.academic_year=%s"; $where_vals[] = $filter_ay; }
    if ($filter_sem)     { $where .= " AND s.semester=%s"; $where_vals[] = $filter_sem; }

    $filtered_sections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$s_table} s {$where} ORDER BY s.course_code ASC, s.year_level ASC, s.block ASC",
        ...$where_vals
    ));

    $time_slots = cs_get_time_slots();
    $day_groups = cs_get_day_groups();

    ob_start();
    ?>
    <!-- Filter Bar -->
    <div class="cs-filter-bar">
        <select id="cs-filter-section" onchange="csApplyTimetableFilter()">
            <option value="">All Sections</option>
            <?php foreach ($sections as $sec): ?>
            <option value="<?php echo $sec->id; ?>" <?php selected($filter_section, $sec->id); ?>>
                <?php echo esc_html($sec->section_name); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select id="cs-filter-ay" onchange="csApplyTimetableFilter()">
            <option value="">All Academic Years</option>
            <?php foreach ($ay_list as $ay): ?>
            <option value="<?php echo esc_attr($ay); ?>" <?php selected($filter_ay, $ay); ?>>
                <?php echo esc_html($ay); ?>
            </option>
            <?php endforeach; ?>
        </select>

        <select id="cs-filter-sem" onchange="csApplyTimetableFilter()">
            <option value="">All Semesters</option>
            <option value="First"  <?php selected($filter_sem, 'First');  ?>>First Semester</option>
            <option value="Second" <?php selected($filter_sem, 'Second'); ?>>Second Semester</option>
            <option value="Summer" <?php selected($filter_sem, 'Summer'); ?>>Summer</option>
        </select>

        <button class="bntm-btn-secondary" onclick="window.print()">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print
        </button>
    </div>

    <?php if (empty($filtered_sections)): ?>
    <div class="bntm-notice">No sections found. <a href="?tab=sections">Add a section</a> to get started.</div>
    <?php else: ?>

    <?php foreach ($filtered_sections as $section): ?>
    <?php
        // Fetch all schedule entries for this section
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$sch_table} WHERE section_id=%d ORDER BY day_group, time_slot",
            $section->id
        ));

        // Build a lookup: [day_group][time_slot] => entry
        $schedule_map = [];
        foreach ($entries as $entry) {
            $schedule_map[$entry->day_group][$entry->time_slot] = $entry;
        }
    ?>
    <div class="cs-section-block">
        <!-- Section Header -->
        <div class="cs-section-header">
            <h3><?php echo esc_html($section->section_name); ?></h3>
            <span class="cs-section-meta">
                <?php echo esc_html($section->academic_year); ?> &bull;
                <?php echo esc_html($section->semester); ?> Semester
            </span>
        </div>

        <!-- Grid-based Timetable -->
        <div class="cs-day-tables-simple">
            <!-- Header Row: Time + Days -->
            <div class="cs-grid-header"></div>
            <?php foreach (cs_get_all_weekdays() as $day_key => $day_name): ?>
            <div class="cs-grid-header"><?php echo esc_html($day_name); ?></div>
            <?php endforeach; ?>

            <!-- Data Rows: Time slots + Classes -->
            <?php foreach (cs_get_time_slots() as $time_slot): ?>
            <!-- Time cell -->
            <div class="cs-grid-time-cell"><?php echo esc_html($time_slot); ?></div>
            
            <!-- Day cells for this time slot -->
            <?php foreach (cs_get_all_weekdays() as $day_key => $day_name): ?>
            <div class="cs-grid-day-cell">
                <?php
                // Find all classes for this day and time slot
                $slot_entries = [];
                foreach ($entries as $e) {
                    if ($e->time_slot === $time_slot) {
                        $day_mapping = cs_map_day_group_to_days($e->day_group);
                        if (array_key_exists($day_key, $day_mapping)) {
                            $slot_entries[] = $e;
                        }
                    }
                }
                ?>
                <?php foreach ($slot_entries as $entry): ?>
                <div class="cs-day-class-block <?php echo strpos($entry->subject_code, ' L') !== false ? 'cs-block-lab' : ''; ?>">
                    <div class="cs-block-subject"><?php echo esc_html($entry->subject_code); ?></div>
                    <div class="cs-block-instructor"><?php echo esc_html($entry->instructor_initials); ?></div>
                    <div class="cs-block-room"><?php echo esc_html($entry->room); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- PageBreak -->
    <?php endforeach; ?>
    <?php endif; ?>

    <script>
    function csApplyTimetableFilter() {
        const section = document.getElementById('cs-filter-section').value;
        const ay      = document.getElementById('cs-filter-ay').value;
        const sem     = document.getElementById('cs-filter-sem').value;
        const params  = new URLSearchParams(window.location.search);
        params.set('tab', 'timetable');
        if (section) params.set('cs_section', section); else params.delete('cs_section');
        if (ay)      params.set('cs_ay', ay);           else params.delete('cs_ay');
        if (sem)     params.set('cs_sem', sem);          else params.delete('cs_sem');
        window.location.search = params.toString();
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: SECTIONS MANAGEMENT
// ============================================================

function cs_sections_tab($business_id) {
    global $wpdb;
    $table   = $wpdb->prefix . 'cs_sections';
    $nonce   = wp_create_nonce('cs_section_nonce');
    $sections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id=%d ORDER BY course_code, year_level, block",
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="margin:0;">Manage Sections</h3>
            <button class="bntm-btn-primary" onclick="csOpenAddSectionModal()">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:4px;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add Section
            </button>
        </div>

        <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Section Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Block</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sections)): ?>
                <tr><td colspan="8" style="text-align:center;color:#6b7280;">No sections yet. Add your first section.</td></tr>
                <?php else: foreach ($sections as $s): ?>
                <tr>
                    <td><strong><?php echo esc_html($s->section_name); ?></strong></td>
                    <td><?php echo esc_html($s->course_code); ?></td>
                    <td><?php echo esc_html($s->year_level); ?></td>
                    <td><?php echo esc_html($s->block); ?></td>
                    <td><?php echo esc_html($s->academic_year); ?></td>
                    <td><?php echo esc_html($s->semester); ?></td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $s->status === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo esc_html(ucfirst($s->status)); ?>
                        </span>
                    </td>
                    <td>
                        <button class="cs-action-link edit"
                                onclick="csOpenEditSectionModal(<?php echo $s->id; ?>, <?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)">
                            Edit
                        </button>
                        <button class="cs-action-link delete"
                                onclick="csDeleteSection(<?php echo $s->id; ?>, '<?php echo esc_js($s->section_name); ?>')">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
    var csSectionNonce = '<?php echo $nonce; ?>';

    function csGetSectionFormHTML(data) {
        data = data || {};
        return `
        <div class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Course Code <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-sf-course" value="${data.course_code||''}" placeholder="e.g. CHE, CE, EE">
                </div>
                <div class="bntm-form-group">
                    <label>Year Level <span style="color:#dc2626">*</span></label>
                    <select id="cs-sf-year">
                        ${[1,2,3,4,5].map(y=>`<option value="${y}" ${data.year_level==y?'selected':''}>${y}</option>`).join('')}
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Block <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-sf-block" value="${data.block||''}" placeholder="e.g. A1, B2, C1">
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Academic Year <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-sf-ay" value="${data.academic_year||''}" placeholder="e.g. 2024-2025">
                </div>
                <div class="bntm-form-group">
                    <label>Semester</label>
                    <select id="cs-sf-sem">
                        <option value="First"  ${data.semester=='First'?'selected':''}>First</option>
                        <option value="Second" ${data.semester=='Second'?'selected':''}>Second</option>
                        <option value="Summer" ${data.semester=='Summer'?'selected':''}>Summer</option>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Status</label>
                    <select id="cs-sf-status">
                        <option value="active"   ${(!data.status||data.status==='active')?'selected':''}>Active</option>
                        <option value="archived" ${data.status==='archived'?'selected':''}>Archived</option>
                    </select>
                </div>
            </div>
            <div id="cs-sf-msg"></div>
        </div>`;
    }

    function csOpenAddSectionModal() {
        csOpenModal('Add New Section', csGetSectionFormHTML() + `
        <div style="margin-top:16px;">
            <button class="bntm-btn-primary" onclick="csSaveSectionSubmit(0)">Save Section</button>
        </div>`);
    }

    function csOpenEditSectionModal(id, data) {
        csOpenModal('Edit Section', csGetSectionFormHTML(data) + `
        <div style="margin-top:16px;">
            <button class="bntm-btn-primary" onclick="csSaveSectionSubmit(${id})">Update Section</button>
        </div>`);
    }

    function csSaveSectionSubmit(id) {
        const course  = document.getElementById('cs-sf-course').value.trim();
        const year    = document.getElementById('cs-sf-year').value;
        const block   = document.getElementById('cs-sf-block').value.trim();
        const ay      = document.getElementById('cs-sf-ay').value.trim();
        const sem     = document.getElementById('cs-sf-sem').value;
        const status  = document.getElementById('cs-sf-status').value;
        const msgEl   = document.getElementById('cs-sf-msg');

        if (!course || !block || !ay) {
            msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">Please fill all required fields.</div>';
            return;
        }

        const section_name = course + year + ' ' + block;

        csAjax('cs_save_section', {
            nonce: csSectionNonce,
            id: id,
            section_name: section_name,
            course_code: course,
            year_level: year,
            block: block,
            academic_year: ay,
            semester: sem,
            status: status
        }, function(data) {
            csToast(data.message, 'success');
            csCloseModal();
            setTimeout(function(){ location.reload(); }, 800);
        }, function(data) {
            msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">' + (data.message||'Error') + '</div>';
        });
    }

    function csDeleteSection(id, name) {
        csConfirm('Delete section "' + name + '"? All its schedule entries will also be deleted.', function() {
            csAjax('cs_delete_section', { nonce: csSectionNonce, id: id }, function(data) {
                csToast(data.message, 'success');
                setTimeout(function(){ location.reload(); }, 800);
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: SCHEDULE ENTRY
// ============================================================

function cs_schedule_tab($business_id) {
    global $wpdb;
    $s_table   = $wpdb->prefix . 'cs_sections';
    $sch_table = $wpdb->prefix . 'cs_schedules';
    $i_table   = $wpdb->prefix . 'cs_instructors';
    $r_table   = $wpdb->prefix . 'cs_rooms';

    $nonce      = wp_create_nonce('cs_schedule_nonce');
    $sections   = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$s_table} WHERE business_id=%d AND status='active' ORDER BY section_name", $business_id));
    $instructors= $wpdb->get_results($wpdb->prepare("SELECT * FROM {$i_table} WHERE business_id=%d AND status='active' ORDER BY initials", $business_id));
    $rooms      = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$r_table} WHERE business_id=%d AND status='active' ORDER BY room_code", $business_id));

    // Current section filter
    $sel_section = isset($_GET['cs_sch_sec']) ? intval($_GET['cs_sch_sec']) : 0;
    if (!$sel_section && !empty($sections)) $sel_section = $sections[0]->id;

    $entries = [];
    if ($sel_section) {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$sch_table} WHERE section_id=%d ORDER BY day_group, time_slot",
            $sel_section
        ));
    }

    $time_slots = cs_get_time_slots();
    $day_groups = cs_get_day_groups();

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="margin:0;">Schedule Entries</h3>
            <button class="bntm-btn-primary" onclick="csOpenAddScheduleModal()">Add Entry</button>
        </div>

        <!-- Section selector -->
        <div class="cs-filter-bar">
            <select id="cs-sch-section-filter" onchange="csChangeSectionFilter()">
                <?php foreach ($sections as $sec): ?>
                <option value="<?php echo $sec->id; ?>" <?php selected($sel_section, $sec->id); ?>>
                    <?php echo esc_html($sec->section_name); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Instructor</th>
                    <th>Room</th>
                    <th>Day Group</th>
                    <th>Time Slot</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                <tr><td colspan="8" style="text-align:center;color:#6b7280;">No schedule entries for this section.</td></tr>
                <?php else: foreach ($entries as $e): ?>
                <tr>
                    <td><strong><?php echo esc_html($e->subject_code); ?></strong></td>
                    <td><?php echo esc_html($e->subject_name); ?></td>
                    <td><?php echo esc_html($e->instructor_initials); ?>
                        <?php if ($e->instructor_name): ?>
                        <br><small style="color:#6b7280;"><?php echo esc_html($e->instructor_name); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($e->room); ?></td>
                    <td><?php echo esc_html(cs_get_day_groups()[$e->day_group] ?? $e->day_group); ?></td>
                    <td><?php echo esc_html($e->time_slot); ?></td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $e->schedule_type === 'lab' ? 'success' : 'info'; ?>">
                            <?php echo esc_html(ucfirst($e->schedule_type)); ?>
                        </span>
                    </td>
                    <td>
                        <button class="cs-action-link edit"
                                onclick="csOpenEditScheduleModal(<?php echo $e->id; ?>, <?php echo htmlspecialchars(json_encode($e), ENT_QUOTES); ?>)">
                            Edit
                        </button>
                        <button class="cs-action-link delete"
                                onclick="csDeleteSchedule(<?php echo $e->id; ?>, '<?php echo esc_js($e->subject_code); ?>')">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
    var csScheduleNonce = '<?php echo $nonce; ?>';
    var csCurrentSection = <?php echo $sel_section ?: 0; ?>;

    var csSections   = <?php echo json_encode(array_map(function($s){ return ['id'=>$s->id,'name'=>$s->section_name]; }, $sections)); ?>;
    var csInstructors= <?php echo json_encode(array_map(function($i){ return ['id'=>$i->id,'initials'=>$i->initials,'name'=>$i->full_name]; }, $instructors)); ?>;
    var csRooms      = <?php echo json_encode(array_map(function($r){ return ['id'=>$r->id,'code'=>$r->room_code]; }, $rooms)); ?>;
    var csTimeSlots  = <?php echo json_encode(cs_get_time_slots()); ?>;
    var csDayGroups  = <?php echo json_encode(cs_get_day_groups()); ?>;

    function csChangeSectionFilter() {
        const id = document.getElementById('cs-sch-section-filter').value;
        const params = new URLSearchParams(window.location.search);
        params.set('tab', 'schedule');
        params.set('cs_sch_sec', id);
        window.location.search = params.toString();
    }

    function csGetScheduleFormHTML(data) {
        data = data || {};
        const sectionOpts = csSections.map(s =>
            `<option value="${s.id}" ${(data.section_id==s.id||(!data.section_id&&s.id==csCurrentSection))?'selected':''}>${s.name}</option>`
        ).join('');
        const instrOpts = '<option value="">-- Select Instructor --</option>' + csInstructors.map(i =>
            `<option value="${i.initials}" data-name="${i.name}" ${data.instructor_initials==i.initials?'selected':''}>${i.initials} — ${i.name}</option>`
        ).join('');
        const roomOpts = '<option value="">-- Select Room --</option>' + csRooms.map(r =>
            `<option value="${r.code}" ${data.room==r.code?'selected':''}>${r.code}</option>`
        ).join('');
        const slotOpts = csTimeSlots.map(t =>
            `<option value="${t}" ${data.time_slot==t?'selected':''}>${t}</option>`
        ).join('');
        const dgOpts = Object.entries(csDayGroups).map(([k,v]) =>
            `<option value="${k}" ${data.day_group==k?'selected':''}>${v}</option>`
        ).join('');

        return `
        <div class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Section <span style="color:#dc2626">*</span></label>
                    <select id="cs-schf-section">${sectionOpts}</select>
                </div>
                <div class="bntm-form-group">
                    <label>Schedule Type</label>
                    <select id="cs-schf-type">
                        <option value="lecture" ${data.schedule_type==='lecture'||!data.schedule_type?'selected':''}>Lecture</option>
                        <option value="lab"     ${data.schedule_type==='lab'?'selected':''}>Laboratory</option>
                        <option value="both"    ${data.schedule_type==='both'?'selected':''}>Both</option>
                    </select>
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Subject Code <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-schf-code" value="${data.subject_code||''}" placeholder="e.g. MATH 89, CHEM 86 L">
                </div>
                <div class="bntm-form-group">
                    <label>Subject Name</label>
                    <input type="text" id="cs-schf-name" value="${data.subject_name||''}" placeholder="Full subject name">
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Day Group <span style="color:#dc2626">*</span></label>
                    <select id="cs-schf-dg">${dgOpts}</select>
                </div>
                <div class="bntm-form-group">
                    <label>Time Slot <span style="color:#dc2626">*</span></label>
                    <select id="cs-schf-slot">${slotOpts}</select>
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Instructor</label>
                    <select id="cs-schf-instr" onchange="csSchAutoInitials(this)">${instrOpts}</select>
                </div>
                <div class="bntm-form-group">
                    <label>Instructor Initials <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-schf-initials" value="${data.instructor_initials||''}" placeholder="e.g. EORTIZ, JKC">
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Room <span style="color:#dc2626">*</span></label>
                    <select id="cs-schf-room">${roomOpts}</select>
                </div>
                <div class="bntm-form-group">
                    <label>Custom Room (override)</label>
                    <input type="text" id="cs-schf-room-custom" value="${data.room||''}" placeholder="e.g. E207">
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Units</label>
                    <input type="number" id="cs-schf-units" value="${data.units||3}" min="1" max="6">
                </div>
            </div>
            <div id="cs-schf-msg"></div>
        </div>`;
    }

    function csSchAutoInitials(sel) {
        const opt = sel.options[sel.selectedIndex];
        const initials = opt.value;
        if (initials) document.getElementById('cs-schf-initials').value = initials;
    }

    function csOpenAddScheduleModal() {
        csOpenModal('Add Schedule Entry', csGetScheduleFormHTML() + `
        <div style="margin-top:16px;">
            <button class="bntm-btn-primary" onclick="csSaveScheduleSubmit(0)">Save Entry</button>
        </div>`);
    }

    function csOpenEditScheduleModal(id, data) {
        csOpenModal('Edit Schedule Entry', csGetScheduleFormHTML(data) + `
        <div style="margin-top:16px;">
            <button class="bntm-btn-primary" onclick="csSaveScheduleSubmit(${id})">Update Entry</button>
        </div>`);
    }

    function csSaveScheduleSubmit(id) {
        const section_id   = document.getElementById('cs-schf-section').value;
        const subject_code = document.getElementById('cs-schf-code').value.trim();
        const subject_name = document.getElementById('cs-schf-name').value.trim();
        const day_group    = document.getElementById('cs-schf-dg').value;
        const time_slot    = document.getElementById('cs-schf-slot').value;
        const initials     = document.getElementById('cs-schf-initials').value.trim();
        const instrSel     = document.getElementById('cs-schf-instr');
        const instrName    = instrSel.options[instrSel.selectedIndex] ? instrSel.options[instrSel.selectedIndex].dataset.name||'' : '';
        const roomSel      = document.getElementById('cs-schf-room').value;
        const roomCustom   = document.getElementById('cs-schf-room-custom').value.trim();
        const room         = roomCustom || roomSel;
        const schedule_type= document.getElementById('cs-schf-type').value;
        const units        = document.getElementById('cs-schf-units').value;
        const msgEl        = document.getElementById('cs-schf-msg');

        if (!section_id || !subject_code || !day_group || !time_slot || !initials || !room) {
            msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">Please fill all required fields.</div>';
            return;
        }

        csAjax('cs_save_schedule', {
            nonce: csScheduleNonce,
            id: id,
            section_id: section_id,
            subject_code: subject_code,
            subject_name: subject_name,
            instructor_initials: initials,
            instructor_name: instrName,
            room: room,
            day_group: day_group,
            time_slot: time_slot,
            schedule_type: schedule_type,
            units: units
        }, function(data) {
            csToast(data.message, 'success');
            csCloseModal();
            setTimeout(function(){ location.reload(); }, 800);
        }, function(data) {
            msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">' + (data.message||'Error') + '</div>';
        });
    }

    function csDeleteSchedule(id, code) {
        csConfirm('Delete schedule entry for "' + code + '"?', function() {
            csAjax('cs_delete_schedule', { nonce: csScheduleNonce, id: id }, function(data) {
                csToast(data.message, 'success');
                setTimeout(function(){ location.reload(); }, 800);
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: INSTRUCTORS
// ============================================================

function cs_instructors_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cs_instructors';
    $nonce = wp_create_nonce('cs_instructor_nonce');
    $instructors = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id=%d ORDER BY initials",
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="margin:0;">Instructors / Faculty</h3>
            <button class="bntm-btn-primary" onclick="csOpenAddInstructorModal()">Add Instructor</button>
        </div>

        <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Initials</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($instructors)): ?>
                <tr><td colspan="5" style="text-align:center;color:#6b7280;">No instructors yet.</td></tr>
                <?php else: foreach ($instructors as $i): ?>
                <tr>
                    <td><strong><?php echo esc_html($i->initials); ?></strong></td>
                    <td><?php echo esc_html($i->full_name); ?></td>
                    <td><?php echo esc_html($i->department); ?></td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $i->status === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo esc_html(ucfirst($i->status)); ?>
                        </span>
                    </td>
                    <td>
                        <button class="cs-action-link edit"
                                onclick="csOpenEditInstructorModal(<?php echo $i->id; ?>, <?php echo htmlspecialchars(json_encode($i), ENT_QUOTES); ?>)">
                            Edit
                        </button>
                        <button class="cs-action-link delete"
                                onclick="csDeleteInstructor(<?php echo $i->id; ?>, '<?php echo esc_js($i->initials); ?>')">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
    var csInstructorNonce = '<?php echo $nonce; ?>';

    function csGetInstructorFormHTML(data) {
        data = data || {};
        return `
        <div class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Initials <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-if-initials" value="${data.initials||''}" placeholder="e.g. EORTIZ, JKC, LMV">
                </div>
                <div class="bntm-form-group">
                    <label>Full Name <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-if-name" value="${data.full_name||''}" placeholder="Full name">
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Department</label>
                    <input type="text" id="cs-if-dept" value="${data.department||''}" placeholder="e.g. Engineering">
                </div>
                <div class="bntm-form-group">
                    <label>Status</label>
                    <select id="cs-if-status">
                        <option value="active"   ${(!data.status||data.status==='active')?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>
            </div>
            <div id="cs-if-msg"></div>
        </div>`;
    }

    function csOpenAddInstructorModal() {
        csOpenModal('Add Instructor', csGetInstructorFormHTML() + `
        <div style="margin-top:16px;"><button class="bntm-btn-primary" onclick="csSaveInstructorSubmit(0)">Save</button></div>`);
    }
    function csOpenEditInstructorModal(id, data) {
        csOpenModal('Edit Instructor', csGetInstructorFormHTML(data) + `
        <div style="margin-top:16px;"><button class="bntm-btn-primary" onclick="csSaveInstructorSubmit(${id})">Update</button></div>`);
    }
    function csSaveInstructorSubmit(id) {
        const initials = document.getElementById('cs-if-initials').value.trim();
        const name     = document.getElementById('cs-if-name').value.trim();
        const dept     = document.getElementById('cs-if-dept').value.trim();
        const status   = document.getElementById('cs-if-status').value;
        const msgEl    = document.getElementById('cs-if-msg');
        if (!initials || !name) {
            msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">Initials and name are required.</div>';
            return;
        }
        csAjax('cs_save_instructor', { nonce: csInstructorNonce, id, initials, full_name: name, department: dept, status },
            function(data) { csToast(data.message); csCloseModal(); setTimeout(()=>location.reload(),800); },
            function(data) { msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">'+(data.message||'Error')+'</div>'; }
        );
    }
    function csDeleteInstructor(id, initials) {
        csConfirm('Delete instructor "' + initials + '"?', function() {
            csAjax('cs_delete_instructor', { nonce: csInstructorNonce, id },
                function(data) { csToast(data.message); setTimeout(()=>location.reload(),800); }
            );
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: ROOMS
// ============================================================

function cs_rooms_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cs_rooms';
    $nonce = wp_create_nonce('cs_room_nonce');
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id=%d ORDER BY room_code",
        $business_id
    ));

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
            <h3 style="margin:0;">Rooms</h3>
            <button class="bntm-btn-primary" onclick="csOpenAddRoomModal()">Add Room</button>
        </div>

        <div class="bntm-table-wrapper">
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Room Code</th>
                    <th>Building</th>
                    <th>Capacity</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                <tr><td colspan="6" style="text-align:center;color:#6b7280;">No rooms yet.</td></tr>
                <?php else: foreach ($rooms as $r): ?>
                <tr>
                    <td><strong><?php echo esc_html($r->room_code); ?></strong></td>
                    <td><?php echo esc_html($r->building); ?></td>
                    <td><?php echo number_format($r->capacity); ?></td>
                    <td><?php echo esc_html(ucfirst($r->room_type)); ?></td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $r->status === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo esc_html(ucfirst($r->status)); ?>
                        </span>
                    </td>
                    <td>
                        <button class="cs-action-link edit"
                                onclick="csOpenEditRoomModal(<?php echo $r->id; ?>, <?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>)">
                            Edit
                        </button>
                        <button class="cs-action-link delete"
                                onclick="csDeleteRoom(<?php echo $r->id; ?>, '<?php echo esc_js($r->room_code); ?>')">
                            Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script>
    var csRoomNonce = '<?php echo $nonce; ?>';

    function csGetRoomFormHTML(data) {
        data = data || {};
        return `
        <div class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Room Code <span style="color:#dc2626">*</span></label>
                    <input type="text" id="cs-rf-code" value="${data.room_code||''}" placeholder="e.g. E207">
                </div>
                <div class="bntm-form-group">
                    <label>Building</label>
                    <input type="text" id="cs-rf-bldg" value="${data.building||''}" placeholder="e.g. Engineering Hall">
                </div>
            </div>
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Capacity</label>
                    <input type="number" id="cs-rf-cap" value="${data.capacity||40}" min="1">
                </div>
                <div class="bntm-form-group">
                    <label>Type</label>
                    <select id="cs-rf-type">
                        <option value="lecture" ${data.room_type==='lecture'||!data.room_type?'selected':''}>Lecture</option>
                        <option value="lab"     ${data.room_type==='lab'?'selected':''}>Laboratory</option>
                        <option value="both"    ${data.room_type==='both'?'selected':''}>Both</option>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Status</label>
                    <select id="cs-rf-status">
                        <option value="active"   ${(!data.status||data.status==='active')?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>
            </div>
            <div id="cs-rf-msg"></div>
        </div>`;
    }

    function csOpenAddRoomModal() {
        csOpenModal('Add Room', csGetRoomFormHTML() + `
        <div style="margin-top:16px;"><button class="bntm-btn-primary" onclick="csSaveRoomSubmit(0)">Save</button></div>`);
    }
    function csOpenEditRoomModal(id, data) {
        csOpenModal('Edit Room', csGetRoomFormHTML(data) + `
        <div style="margin-top:16px;"><button class="bntm-btn-primary" onclick="csSaveRoomSubmit(${id})">Update</button></div>`);
    }
    function csSaveRoomSubmit(id) {
        const code   = document.getElementById('cs-rf-code').value.trim();
        const bldg   = document.getElementById('cs-rf-bldg').value.trim();
        const cap    = document.getElementById('cs-rf-cap').value;
        const type   = document.getElementById('cs-rf-type').value;
        const status = document.getElementById('cs-rf-status').value;
        const msgEl  = document.getElementById('cs-rf-msg');
        if (!code) { msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">Room code is required.</div>'; return; }
        csAjax('cs_save_room', { nonce: csRoomNonce, id, room_code: code, building: bldg, capacity: cap, room_type: type, status },
            function(data) { csToast(data.message); csCloseModal(); setTimeout(()=>location.reload(),800); },
            function(data) { msgEl.innerHTML = '<div class="bntm-notice bntm-notice-error">'+(data.message||'Error')+'</div>'; }
        );
    }
    function csDeleteRoom(id, code) {
        csConfirm('Delete room "' + code + '"?', function() {
            csAjax('cs_delete_room', { nonce: csRoomNonce, id },
                function(data) { csToast(data.message); setTimeout(()=>location.reload(),800); }
            );
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// FRONTEND SHORTCODE: PUBLIC TIMETABLE VIEW
// ============================================================

function bntm_shortcode_section_timetable() {
    global $wpdb;
    $section_id = isset($_GET['section']) ? intval($_GET['section']) : 0;

    if (!$section_id) {
        return '<div class="bntm-notice">No section specified.</div>';
    }

    $section   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cs_sections WHERE id=%d", $section_id));
    if (!$section) return '<div class="bntm-notice">Section not found.</div>';

    $entries   = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d ORDER BY day_group, time_slot",
        $section_id
    ));

    $time_slots = cs_get_time_slots();

    ob_start();
    echo '<div style="font-family:Arial,sans-serif;">';
    echo '<div class="cs-section-block">';
    echo '<div class="cs-section-header"><h3>' . esc_html($section->section_name) . '</h3>';
    echo '<span class="cs-section-meta">' . esc_html($section->academic_year) . ' &bull; ' . esc_html($section->semester) . ' Semester</span></div>';
    echo '<div class="cs-day-tables">';

    foreach (cs_get_all_weekdays() as $day_key => $day_name) {
        $day_entries = [];
        foreach ($entries as $e) {
            $day_mapping = cs_map_day_group_to_days($e->day_group);
            if (array_key_exists($day_key, $day_mapping)) {
                $day_entries[] = $e;
            }
        }
        
        echo '<div class="cs-day-column">';
        echo '<div class="cs-day-column-header">' . esc_html($day_name) . '</div>';
        echo '<div class="cs-day-column-content">';
        
        if (empty($day_entries)) {
            echo '<div class="cs-day-empty">No classes</div>';
        } else {
            foreach ($day_entries as $entry) {
                $is_lab = strpos($entry->subject_code, ' L') !== false ? ' cs-block-lab' : '';
                echo '<div class="cs-day-class-block' . $is_lab . '">';
                echo '<div class="cs-block-subject">' . esc_html($entry->subject_code) . '</div>';
                echo '<div class="cs-block-time">' . esc_html($entry->time_slot) . '</div>';
                echo '<div class="cs-block-instructor">' . esc_html($entry->instructor_initials) . '</div>';
                echo '<div class="cs-block-room">' . esc_html($entry->room) . '</div>';
                echo '</div>';
            }
        }
        
        echo '</div></div>';
    }

    echo '</div></div></div>';
    return ob_get_clean();
}

// ============================================================
// AJAX HANDLERS
// ============================================================

function bntm_ajax_cs_save_section() {
    check_ajax_referer('cs_section_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table       = $wpdb->prefix . 'cs_sections';
    $business_id = get_current_user_id();
    $id          = intval($_POST['id'] ?? 0);

    $data = [
        'section_name'  => sanitize_text_field($_POST['section_name']),
        'course_code'   => sanitize_text_field($_POST['course_code']),
        'year_level'    => intval($_POST['year_level']),
        'block'         => sanitize_text_field($_POST['block']),
        'academic_year' => sanitize_text_field($_POST['academic_year']),
        'semester'      => sanitize_text_field($_POST['semester']),
        'status'        => sanitize_text_field($_POST['status']),
    ];
    $format = ['%s','%s','%d','%s','%s','%s','%s'];

    if ($id) {
        $result = $wpdb->update($table, $data, ['id' => $id, 'business_id' => $business_id], $format, ['%d','%d']);
        $msg = 'Section updated.';
    } else {
        $data['rand_id']     = bntm_rand_id();
        $data['business_id'] = $business_id;
        $format[] = '%s'; $format[] = '%d';
        $result = $wpdb->insert($table, $data, $format);
        $msg = 'Section added.';
    }

    if ($result === false) wp_send_json_error(['message' => 'Database error.']);
    wp_send_json_success(['message' => $msg]);
}

function bntm_ajax_cs_delete_section() {
    check_ajax_referer('cs_section_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id'] ?? 0);

    $wpdb->delete($wpdb->prefix . 'cs_schedules', ['section_id' => $id], ['%d']);
    $result = $wpdb->delete($wpdb->prefix . 'cs_sections', ['id' => $id, 'business_id' => $business_id], ['%d','%d']);

    if ($result === false) wp_send_json_error(['message' => 'Delete failed.']);
    wp_send_json_success(['message' => 'Section deleted.']);
}

function bntm_ajax_cs_save_schedule() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table       = $wpdb->prefix . 'cs_schedules';
    $business_id = get_current_user_id();
    $id          = intval($_POST['id'] ?? 0);

    // Conflict detection (same section + day_group + time_slot, different id)
    $section_id = intval($_POST['section_id']);
    $day_group  = sanitize_text_field($_POST['day_group']);
    $time_slot  = sanitize_text_field($_POST['time_slot']);

    $conflict = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE section_id=%d AND day_group=%s AND time_slot=%s AND id!=%d",
        $section_id, $day_group, $time_slot, $id
    ));
    if ($conflict) wp_send_json_error(['message' => 'Conflict: a schedule already exists for this section, day group, and time slot.']);

    $data = [
        'section_id'          => $section_id,
        'subject_code'        => sanitize_text_field($_POST['subject_code']),
        'subject_name'        => sanitize_text_field($_POST['subject_name']),
        'instructor_initials' => sanitize_text_field($_POST['instructor_initials']),
        'instructor_name'     => sanitize_text_field($_POST['instructor_name']),
        'room'                => sanitize_text_field($_POST['room']),
        'day_group'           => $day_group,
        'time_slot'           => $time_slot,
        'schedule_type'       => sanitize_text_field($_POST['schedule_type']),
        'units'               => intval($_POST['units']),
    ];
    $format = ['%d','%s','%s','%s','%s','%s','%s','%s','%s','%d'];

    if ($id) {
        $result = $wpdb->update($table, $data, ['id' => $id, 'business_id' => $business_id], $format, ['%d','%d']);
        $msg = 'Schedule entry updated.';
    } else {
        $data['rand_id']     = bntm_rand_id();
        $data['business_id'] = $business_id;
        $format[] = '%s'; $format[] = '%d';
        $result = $wpdb->insert($table, $data, $format);
        $msg = 'Schedule entry added.';
    }

    if ($result === false) wp_send_json_error(['message' => 'Database error.']);
    wp_send_json_success(['message' => $msg]);
}

function bntm_ajax_cs_delete_schedule() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cs_schedules', ['id' => intval($_POST['id']), 'business_id' => get_current_user_id()], ['%d','%d']);
    if ($result === false) wp_send_json_error(['message' => 'Delete failed.']);
    wp_send_json_success(['message' => 'Entry deleted.']);
}

function bntm_ajax_cs_save_instructor() {
    check_ajax_referer('cs_instructor_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table       = $wpdb->prefix . 'cs_instructors';
    $business_id = get_current_user_id();
    $id          = intval($_POST['id'] ?? 0);

    $data = [
        'initials'   => strtoupper(sanitize_text_field($_POST['initials'])),
        'full_name'  => sanitize_text_field($_POST['full_name']),
        'department' => sanitize_text_field($_POST['department']),
        'status'     => sanitize_text_field($_POST['status']),
    ];
    $format = ['%s','%s','%s','%s'];

    if ($id) {
        $result = $wpdb->update($table, $data, ['id' => $id, 'business_id' => $business_id], $format, ['%d','%d']);
        $msg = 'Instructor updated.';
    } else {
        $data['rand_id'] = bntm_rand_id();
        $data['business_id'] = $business_id;
        $format[] = '%s'; $format[] = '%d';
        $result = $wpdb->insert($table, $data, $format);
        $msg = 'Instructor added.';
    }

    if ($result === false) wp_send_json_error(['message' => 'Database error.']);
    wp_send_json_success(['message' => $msg]);
}

function bntm_ajax_cs_delete_instructor() {
    check_ajax_referer('cs_instructor_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cs_instructors', ['id' => intval($_POST['id']), 'business_id' => get_current_user_id()], ['%d','%d']);
    if ($result === false) wp_send_json_error(['message' => 'Delete failed.']);
    wp_send_json_success(['message' => 'Instructor deleted.']);
}

function bntm_ajax_cs_save_room() {
    check_ajax_referer('cs_room_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table       = $wpdb->prefix . 'cs_rooms';
    $business_id = get_current_user_id();
    $id          = intval($_POST['id'] ?? 0);

    $data = [
        'room_code'  => strtoupper(sanitize_text_field($_POST['room_code'])),
        'building'   => sanitize_text_field($_POST['building']),
        'capacity'   => intval($_POST['capacity']),
        'room_type'  => sanitize_text_field($_POST['room_type']),
        'status'     => sanitize_text_field($_POST['status']),
    ];
    $format = ['%s','%s','%d','%s','%s'];

    if ($id) {
        $result = $wpdb->update($table, $data, ['id' => $id, 'business_id' => $business_id], $format, ['%d','%d']);
        $msg = 'Room updated.';
    } else {
        $data['rand_id'] = bntm_rand_id();
        $data['business_id'] = $business_id;
        $format[] = '%s'; $format[] = '%d';
        $result = $wpdb->insert($table, $data, $format);
        $msg = 'Room added.';
    }

    if ($result === false) wp_send_json_error(['message' => 'Database error.']);
    wp_send_json_success(['message' => $msg]);
}

function bntm_ajax_cs_delete_room() {
    check_ajax_referer('cs_room_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $result = $wpdb->delete($wpdb->prefix . 'cs_rooms', ['id' => intval($_POST['id']), 'business_id' => get_current_user_id()], ['%d','%d']);
    if ($result === false) wp_send_json_error(['message' => 'Delete failed.']);
    wp_send_json_success(['message' => 'Room deleted.']);
}

function bntm_ajax_cs_get_section_data() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $section_id = intval($_POST['section_id'] ?? 0);
    $entries    = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d AND business_id=%d ORDER BY day_group, time_slot",
        $section_id, get_current_user_id()
    ));
    wp_send_json_success(['entries' => $entries]);
}

function bntm_ajax_cs_bulk_import_section() {
    check_ajax_referer('cs_section_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $entries     = json_decode(stripslashes($_POST['entries'] ?? '[]'), true);
    $section_id  = intval($_POST['section_id'] ?? 0);
    $business_id = get_current_user_id();
    $table       = $wpdb->prefix . 'cs_schedules';
    $inserted    = 0;

    if (empty($entries) || !is_array($entries)) {
        wp_send_json_error(['message' => 'No entries provided.']);
    }

    foreach ($entries as $entry) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE section_id=%d AND day_group=%s AND time_slot=%s",
            $section_id, $entry['day_group'] ?? '', $entry['time_slot'] ?? ''
        ));
        if ($exists) continue;

        $result = $wpdb->insert($table, [
            'rand_id'             => bntm_rand_id(),
            'business_id'         => $business_id,
            'section_id'          => $section_id,
            'subject_code'        => sanitize_text_field($entry['subject_code'] ?? ''),
            'subject_name'        => sanitize_text_field($entry['subject_name'] ?? ''),
            'instructor_initials' => sanitize_text_field($entry['instructor_initials'] ?? ''),
            'instructor_name'     => sanitize_text_field($entry['instructor_name'] ?? ''),
            'room'                => sanitize_text_field($entry['room'] ?? ''),
            'day_group'           => sanitize_text_field($entry['day_group'] ?? ''),
            'time_slot'           => sanitize_text_field($entry['time_slot'] ?? ''),
            'schedule_type'       => sanitize_text_field($entry['schedule_type'] ?? 'lecture'),
            'units'               => intval($entry['units'] ?? 3),
        ], ['%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%d']);

        if ($result) $inserted++;
    }

    wp_send_json_success(['message' => "Imported {$inserted} schedule entries."]);
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Returns the standard time slots used in all timetables.
 */
function cs_get_time_slots() {
    return [
        '7:30 - 9:00',
        '9:00 - 10:30',
        '10:30 - 12:00',
        '12:00 - 1:30',
        '1:30 - 3:00',
        '3:00 - 4:30',
        '4:30 - 6:00',
        '6:00 - 7:30',
    ];
}

/**
 * Returns the three day-pair groups.
 */
function cs_get_day_groups() {
    return [
        'mon_thu' => 'Monday / Thursday',
        'tue_fri' => 'Tuesday / Friday',
        'wed_sat' => 'Wednesday / Saturday',
    ];
}

/**
 * Map day_group to individual days.
 */
function cs_map_day_group_to_days($day_group) {
    $mapping = [
        'mon_thu' => ['monday' => 'Monday', 'thursday' => 'Thursday'],
        'tue_fri' => ['tuesday' => 'Tuesday', 'friday' => 'Friday'],
        'wed_sat' => ['wednesday' => 'Wednesday', 'saturday' => 'Saturday'],
    ];
    return $mapping[$day_group] ?? [];
}

/**
 * Get all weekdays in order.
 */
function cs_get_all_weekdays() {
    return [
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
        'sunday'    => 'Sunday',
    ];
}

/**
 * Get section schedule summary stats.
 */
function cs_get_section_stats($section_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'cs_schedules';
    return [
        'total_entries' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE section_id=%d", $section_id)),
        'lecture_count' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE section_id=%d AND schedule_type='lecture'", $section_id)),
        'lab_count'     => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE section_id=%d AND schedule_type='lab'", $section_id)),
        'total_units'   => (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(units) FROM {$table} WHERE section_id=%d", $section_id)),
    ];
}

/**
 * Check if a time slot conflicts with an existing schedule for any section in the same room.
 */
function cs_check_room_conflict($room, $day_group, $time_slot, $exclude_id = 0) {
    global $wpdb;
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}cs_schedules WHERE room=%s AND day_group=%s AND time_slot=%s AND id!=%d",
        $room, $day_group, $time_slot, $exclude_id
    ));
}

/**
 * Check instructor schedule conflict across all sections.
 */
function cs_check_instructor_conflict($instructor_initials, $day_group, $time_slot, $exclude_id = 0) {
    global $wpdb;
    return (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}cs_schedules WHERE instructor_initials=%s AND day_group=%s AND time_slot=%s AND id!=%d",
        $instructor_initials, $day_group, $time_slot, $exclude_id
    ));
}