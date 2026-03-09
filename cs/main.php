<?php
/**
 * Module Name: Class Scheduler
 * Module Slug: cs
 * Description: Web-based weekly class timetable builder for school administrators.
 *              Manage sections, assign subjects to time slots, link instructors and rooms,
 *              and view a full weekly grid.
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────
// MODULE CONSTANTS
// ─────────────────────────────────────────────────────────────
define('BNTM_CS_PATH', dirname(__FILE__) . '/');
define('BNTM_CS_URL',  plugin_dir_url(__FILE__));

define('BNTM_CS_TIME_SLOTS', [
    '7:30 - 9:00',
    '9:00 - 10:30',
    '10:30 - 12:00',
    '12:00 - 1:30',
    '1:30 - 3:00',
    '3:00 - 4:30',
    '4:30 - 6:00',
    '6:00 - 7:30',
]);

define('BNTM_CS_DAY_GROUPS', [
    'mon_thu' => 'Monday / Thursday',
    'tue_fri' => 'Tuesday / Friday',
    'wed_sat' => 'Wednesday / Saturday',
]);

// ─────────────────────────────────────────────────────────────
// CORE MODULE FUNCTIONS
// ─────────────────────────────────────────────────────────────

function bntm_cs_get_pages() {
    return [
        'Class Scheduler' => '[bntm_class_scheduler]',
        'Section Timetable' => '[bntm_section_timetable]',
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
            year_level TINYINT NOT NULL DEFAULT 1,
            block VARCHAR(10) NOT NULL,
            academic_year VARCHAR(20) NOT NULL DEFAULT '',
            semester VARCHAR(20) NOT NULL DEFAULT 'First',
            status ENUM('active','archived') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",

        'cs_schedules' => "CREATE TABLE {$prefix}cs_schedules (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            section_id BIGINT UNSIGNED NOT NULL,
            subject_code VARCHAR(50) NOT NULL,
            subject_name VARCHAR(150) NOT NULL DEFAULT '',
            instructor_initials VARCHAR(30) NOT NULL DEFAULT '',
            instructor_name VARCHAR(100) NOT NULL DEFAULT '',
            room VARCHAR(30) NOT NULL DEFAULT '',
            day_group ENUM('mon_thu','tue_fri','wed_sat') NOT NULL,
            time_slot VARCHAR(20) NOT NULL,
            schedule_type ENUM('lecture','lab','both') NOT NULL DEFAULT 'lecture',
            units TINYINT NOT NULL DEFAULT 3,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_section (section_id)
        ) {$charset};",

        'cs_instructors' => "CREATE TABLE {$prefix}cs_instructors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            initials VARCHAR(30) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
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
            capacity SMALLINT NOT NULL DEFAULT 40,
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
        'bntm_class_scheduler'   => 'bntm_shortcode_cs',
        'bntm_section_timetable' => 'bntm_shortcode_cs_public',
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

// ─────────────────────────────────────────────────────────────
// AJAX ACTION HOOKS
// ─────────────────────────────────────────────────────────────
add_action('wp_ajax_cs_save_section',         'bntm_ajax_cs_save_section');
add_action('wp_ajax_cs_delete_section',       'bntm_ajax_cs_delete_section');
add_action('wp_ajax_cs_save_schedule',        'bntm_ajax_cs_save_schedule');
add_action('wp_ajax_cs_delete_schedule',      'bntm_ajax_cs_delete_schedule');
add_action('wp_ajax_cs_save_instructor',      'bntm_ajax_cs_save_instructor');
add_action('wp_ajax_cs_delete_instructor',    'bntm_ajax_cs_delete_instructor');
add_action('wp_ajax_cs_save_room',            'bntm_ajax_cs_save_room');
add_action('wp_ajax_cs_delete_room',          'bntm_ajax_cs_delete_room');
add_action('wp_ajax_cs_get_section_data',     'bntm_ajax_cs_get_section_data');
add_action('wp_ajax_cs_bulk_import_section',  'bntm_ajax_cs_bulk_import_section');

// ─────────────────────────────────────────────────────────────
// MAIN DASHBOARD SHORTCODE
// ─────────────────────────────────────────────────────────────

function bntm_shortcode_cs() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Class Scheduler.</div>';
    }

    $business_id = get_current_user_id();
    $active_tab  = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

    $tabs = [
        'overview'    => 'Overview',
        'timetable'   => 'Timetable View',
        'sections'    => 'Sections',
        'schedule'    => 'Schedule Entry',
        'instructors' => 'Instructors',
        'rooms'       => 'Rooms',
    ];

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';</script>

    <style>
    /* ── Shared Layout ─────────────────────────────────── */
    .cs-wrap * { box-sizing: border-box; }
    .cs-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 0; }
    .cs-tab-btn {
        padding: 10px 18px; border: none; background: transparent; cursor: pointer;
        font-size: 13.5px; font-weight: 500; color: #6b7280; border-radius: 6px 6px 0 0;
        border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all .15s;
    }
    .cs-tab-btn:hover { color: #1d4ed8; background: #eff6ff; }
    .cs-tab-btn.active { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #eff6ff; }

    /* ── Cards & Sections ──────────────────────────────── */
    .cs-stat-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 16px; margin-bottom: 28px; }
    .cs-stat-card {
        background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
        padding: 20px 18px; display: flex; align-items: center; gap: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .cs-stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .cs-stat-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
    .cs-stat-num { font-size: 26px; font-weight: 700; color: #111827; line-height: 1; }

    .cs-panel { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 22px; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
    .cs-panel h3 { font-size: 15px; font-weight: 600; color: #111827; margin: 0 0 16px; }
    .cs-panel h4 { font-size: 13.5px; font-weight: 600; color: #374151; margin: 0 0 12px; }

    /* ── Forms ─────────────────────────────────────────── */
    .cs-form-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 14px; }
    .cs-field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .4px; }
    .cs-field input, .cs-field select, .cs-field textarea {
        width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 7px;
        font-size: 13.5px; color: #111827; background: #fff; transition: border-color .15s;
    }
    .cs-field input:focus, .cs-field select:focus, .cs-field textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
    .cs-field.span2 { grid-column: span 2; }
    .cs-field.span3 { grid-column: span 3; }

    /* ── Buttons ────────────────────────────────────────── */
    .cs-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 7px; font-size: 13.5px; font-weight: 500; border: none; cursor: pointer; transition: all .15s; }
    .cs-btn-primary { background: #1d4ed8; color: #fff; }
    .cs-btn-primary:hover { background: #1e40af; }
    .cs-btn-secondary { background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; }
    .cs-btn-secondary:hover { background: #e5e7eb; }
    .cs-btn-danger { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .cs-btn-danger:hover { background: #fecaca; }
    .cs-btn-sm { padding: 5px 11px; font-size: 12px; }
    .cs-btn:disabled { opacity: .55; cursor: not-allowed; }
    .cs-btn-group { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 18px; align-items: center; }

    /* ── Table ──────────────────────────────────────────── */
    .cs-table-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid #e5e7eb; }
    .cs-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
    .cs-table thead th { background: #f9fafb; padding: 11px 14px; text-align: left; font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e5e7eb; }
    .cs-table tbody td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    .cs-table tbody tr:last-child td { border-bottom: none; }
    .cs-table tbody tr:hover { background: #f9fafb; }

    /* ── Badges ─────────────────────────────────────────── */
    .cs-badge { display: inline-block; padding: 2px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 500; }
    .cs-badge-green { background: #d1fae5; color: #065f46; }
    .cs-badge-gray  { background: #f3f4f6; color: #6b7280; }
    .cs-badge-blue  { background: #dbeafe; color: #1e40af; }
    .cs-badge-yellow { background: #fef3c7; color: #92400e; }

    /* ── Notices ─────────────────────────────────────────── */
    .cs-notice { padding: 10px 14px; border-radius: 7px; font-size: 13.5px; margin: 12px 0; }
    .cs-notice-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
    .cs-notice-error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
    .cs-notice-info    { background: #dbeafe; color: #1e40af; border-left: 3px solid #3b82f6; }

    /* ── Timetable Grid ──────────────────────────────────── */
    .cs-grid-wrap { overflow-x: auto; }
    .cs-timetable { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 780px; }
    .cs-timetable th { background: #1e3a5f; color: #fff; padding: 10px 8px; text-align: center; font-size: 12px; font-weight: 600; letter-spacing: .5px; border: 1px solid #1e3a5f; }
    .cs-timetable td { border: 1px solid #e5e7eb; padding: 0; vertical-align: top; min-width: 100px; }
    .cs-slot-label { background: #f8fafc; padding: 10px 12px; font-weight: 600; font-size: 12px; color: #374151; white-space: nowrap; text-align: center; border: 1px solid #e5e7eb; }
    .cs-cell { padding: 7px 9px; min-height: 64px; }
    .cs-cell-entry {
        background: #dbeafe; border: 1px solid #93c5fd; border-radius: 6px;
        padding: 6px 8px; line-height: 1.4; color: #1e3a5f;
    }
    .cs-cell-entry.lab { background: #d1fae5; border-color: #6ee7b7; color: #064e3b; }
    .cs-cell-entry .ce-code { font-weight: 700; font-size: 12px; display: block; }
    .cs-cell-entry .ce-inst { font-size: 11px; color: #374151; }
    .cs-cell-entry .ce-room { font-size: 11px; color: #374151; font-style: italic; }
    .cs-cell-empty { background: #fafafa; }

    /* ── Modal ───────────────────────────────────────────── */
    .cs-modal-overlay {
        display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
        z-index: 9999; align-items: center; justify-content: center;
    }
    .cs-modal-overlay.open { display: flex; }
    .cs-modal {
        background: #fff; border-radius: 14px; padding: 28px 30px; width: 92%;
        max-width: 580px; max-height: 90vh; overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,.25); position: relative;
    }
    .cs-modal-title { font-size: 16px; font-weight: 700; color: #111827; margin: 0 0 20px; }
    .cs-modal-close {
        position: absolute; top: 18px; right: 20px; background: #f3f4f6;
        border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; color: #6b7280;
        font-size: 16px;
    }
    .cs-modal-close:hover { background: #e5e7eb; }

    /* ── Print ───────────────────────────────────────────── */
    @media print {
        .cs-tabs, .cs-btn-group, .cs-panel:not(.cs-print-target), .cs-filter-bar { display: none !important; }
        .cs-print-target { display: block !important; }
    }
    </style>

    <div class="cs-wrap">
        <div class="cs-tabs">
            <?php foreach ($tabs as $key => $label): ?>
            <button class="cs-tab-btn <?php echo $active_tab === $key ? 'active' : ''; ?>"
                    data-tab="<?php echo esc_attr($key); ?>">
                <?php echo esc_html($label); ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div id="cs-tab-overview"    class="cs-tab-content" <?php echo $active_tab !== 'overview'    ? 'style="display:none"' : ''; ?>>
            <?php echo cs_overview_tab($business_id); ?>
        </div>
        <div id="cs-tab-timetable"   class="cs-tab-content" <?php echo $active_tab !== 'timetable'   ? 'style="display:none"' : ''; ?>>
            <?php echo cs_timetable_tab($business_id); ?>
        </div>
        <div id="cs-tab-sections"    class="cs-tab-content" <?php echo $active_tab !== 'sections'    ? 'style="display:none"' : ''; ?>>
            <?php echo cs_sections_tab($business_id); ?>
        </div>
        <div id="cs-tab-schedule"    class="cs-tab-content" <?php echo $active_tab !== 'schedule'    ? 'style="display:none"' : ''; ?>>
            <?php echo cs_schedule_tab($business_id); ?>
        </div>
        <div id="cs-tab-instructors" class="cs-tab-content" <?php echo $active_tab !== 'instructors' ? 'style="display:none"' : ''; ?>>
            <?php echo cs_instructors_tab($business_id); ?>
        </div>
        <div id="cs-tab-rooms"       class="cs-tab-content" <?php echo $active_tab !== 'rooms'       ? 'style="display:none"' : ''; ?>>
            <?php echo cs_rooms_tab($business_id); ?>
        </div>
    </div>

    <!-- Shared Modal -->
    <div class="cs-modal-overlay" id="cs-modal-overlay">
        <div class="cs-modal" id="cs-modal">
            <button class="cs-modal-close" id="cs-modal-close">&times;</button>
            <div id="cs-modal-body"></div>
        </div>
    </div>

    <script>
    (function () {
        // Tab switching
        document.querySelectorAll('.cs-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.cs-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.cs-tab-content').forEach(c => c.style.display = 'none');
                this.classList.add('active');
                document.getElementById('cs-tab-' + this.dataset.tab).style.display = 'block';
            });
        });

        // Modal helpers
        window.csOpenModal = function (html) {
            document.getElementById('cs-modal-body').innerHTML = html;
            document.getElementById('cs-modal-overlay').classList.add('open');
        };
        window.csCloseModal = function () {
            document.getElementById('cs-modal-overlay').classList.remove('open');
        };
        document.getElementById('cs-modal-close').addEventListener('click', csCloseModal);
        document.getElementById('cs-modal-overlay').addEventListener('click', function (e) {
            if (e.target === this) csCloseModal();
        });

        // Generic AJAX helper
        window.csAjax = function (formData) {
            return fetch(ajaxurl, { method: 'POST', body: formData }).then(r => r.json());
        };

        // Show notice helper
        window.csNotice = function (el, message, type) {
            type = type || 'success';
            el.innerHTML = '<div class="cs-notice cs-notice-' + type + '">' + message + '</div>';
            setTimeout(function () { el.innerHTML = ''; }, 4000);
        };
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return function_exists('bntm_universal_container')
        ? bntm_universal_container('Class Scheduler', $content)
        : $content;
}

// ─────────────────────────────────────────────────────────────
// TAB: OVERVIEW
// ─────────────────────────────────────────────────────────────

function cs_overview_tab($business_id) {
    global $wpdb;

    $sections_count    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cs_sections WHERE business_id=%d AND status='active'", $business_id));
    $schedules_count   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cs_schedules WHERE business_id=%d", $business_id));
    $instructors_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cs_instructors WHERE business_id=%d AND status='active'", $business_id));
    $rooms_count       = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cs_rooms WHERE business_id=%d AND status='active'", $business_id));

    $recent_sections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE business_id=%d ORDER BY created_at DESC LIMIT 5",
        $business_id
    ));

    ob_start();
    ?>
    <div class="cs-stat-row">
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#3b82f6)">
                <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Active Sections</div>
                <div class="cs-stat-num"><?php echo $sections_count; ?></div>
            </div>
        </div>
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)">
                <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Schedule Entries</div>
                <div class="cs-stat-num"><?php echo $schedules_count; ?></div>
            </div>
        </div>
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#0891b2,#22d3ee)">
                <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Instructors</div>
                <div class="cs-stat-num"><?php echo $instructors_count; ?></div>
            </div>
        </div>
        <div class="cs-stat-card">
            <div class="cs-stat-icon" style="background:linear-gradient(135deg,#059669,#34d399)">
                <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <div class="cs-stat-label">Rooms</div>
                <div class="cs-stat-num"><?php echo $rooms_count; ?></div>
            </div>
        </div>
    </div>

    <div class="cs-panel">
        <h3>Quick Start Guide</h3>
        <ol style="margin:0;padding-left:20px;line-height:2;font-size:13.5px;color:#374151;">
            <li>Go to <strong>Instructors</strong> and add your faculty members.</li>
            <li>Go to <strong>Rooms</strong> and add your classrooms/labs.</li>
            <li>Go to <strong>Sections</strong> and create your class groups (e.g. CHE3 A1).</li>
            <li>Go to <strong>Schedule Entry</strong> to assign subjects to time slots.</li>
            <li>View the complete timetable grid in <strong>Timetable View</strong>.</li>
        </ol>
    </div>

    <?php if (!empty($recent_sections)): ?>
    <div class="cs-panel">
        <h3>Recent Sections</h3>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Academic Year</th>
                    <th>Semester</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_sections as $sec): ?>
                <tr>
                    <td><strong><?php echo esc_html($sec->section_name); ?></strong></td>
                    <td><?php echo esc_html($sec->academic_year ?: '—'); ?></td>
                    <td><?php echo esc_html($sec->semester); ?></td>
                    <td>
                        <span class="cs-badge <?php echo $sec->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>">
                            <?php echo ucfirst($sec->status); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: TIMETABLE VIEW
// ─────────────────────────────────────────────────────────────

function cs_timetable_tab($business_id) {
    global $wpdb;

    $sections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE business_id=%d AND status='active' ORDER BY section_name ASC",
        $business_id
    ));

    $filter_section = isset($_GET['cs_section']) ? intval($_GET['cs_section']) : 0;
    $filter_year    = isset($_GET['cs_year']) ? sanitize_text_field($_GET['cs_year']) : '';
    $filter_sem     = isset($_GET['cs_sem'])  ? sanitize_text_field($_GET['cs_sem'])  : '';

    // Build section list to render
    $render_sections = [];
    foreach ($sections as $sec) {
        if ($filter_section && $sec->id != $filter_section) continue;
        if ($filter_year  && $sec->academic_year !== $filter_year) continue;
        if ($filter_sem   && $sec->semester !== $filter_sem) continue;
        $render_sections[] = $sec;
    }

    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $day_group_map = [
        'mon_thu' => [0, 3],
        'tue_fri' => [1, 4],
        'wed_sat' => [2, 5],
    ];

    ob_start();
    ?>
    <div class="cs-panel cs-filter-bar">
        <div class="cs-form-grid" style="align-items:flex-end;">
            <div class="cs-field">
                <label>Section</label>
                <select id="cs-filter-section">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($filter_section, $s->id); ?>>
                        <?php echo esc_html($s->section_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cs-field">
                <label>Academic Year</label>
                <input type="text" id="cs-filter-year" value="<?php echo esc_attr($filter_year); ?>" placeholder="e.g. 2024-2025">
            </div>
            <div class="cs-field">
                <label>Semester</label>
                <select id="cs-filter-sem">
                    <option value="">All Semesters</option>
                    <option value="First"  <?php selected($filter_sem, 'First'); ?>>First</option>
                    <option value="Second" <?php selected($filter_sem, 'Second'); ?>>Second</option>
                    <option value="Summer" <?php selected($filter_sem, 'Summer'); ?>>Summer</option>
                </select>
            </div>
            <div>
                <button class="cs-btn cs-btn-primary" id="cs-apply-filter">Apply Filter</button>
                <button class="cs-btn cs-btn-secondary" id="cs-print-btn" style="margin-left:8px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
            </div>
        </div>
    </div>

    <div id="cs-timetable-area">
    <?php if (empty($render_sections)): ?>
        <div class="cs-notice cs-notice-info">No sections found. Create sections in the Sections tab first.</div>
    <?php else: ?>
        <?php foreach ($render_sections as $sec):
            // Load all schedule entries for this section
            $entries = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d AND business_id=%d",
                $sec->id, $business_id
            ));
            // Index entries by day_index => time_slot
            $grid = [];
            foreach ($entries as $e) {
                foreach ($day_group_map[$e->day_group] as $di) {
                    $grid[$di][$e->time_slot] = $e;
                }
            }
        ?>
        <div class="cs-panel cs-print-target" style="margin-bottom:28px;">
            <h3>
                <?php echo esc_html($sec->section_name); ?>
                <?php if ($sec->academic_year): ?>
                <span style="font-weight:400;color:#6b7280;font-size:13px;margin-left:8px;"><?php echo esc_html($sec->academic_year); ?> &bull; <?php echo esc_html($sec->semester); ?> Semester</span>
                <?php endif; ?>
            </h3>
            <div class="cs-grid-wrap">
            <table class="cs-timetable">
                <thead>
                    <tr>
                        <th style="min-width:110px;">Time</th>
                        <?php foreach ($days as $d): ?>
                        <th><?php echo $d; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (BNTM_CS_TIME_SLOTS as $slot): ?>
                    <tr>
                        <td class="cs-slot-label"><?php echo esc_html($slot); ?></td>
                        <?php for ($di = 0; $di < 7; $di++):
                            if ($di === 6) { // Sunday always empty
                                echo '<td class="cs-cell cs-cell-empty"></td>';
                                continue;
                            }
                            if (isset($grid[$di][$slot])):
                                $e = $grid[$di][$slot];
                                $is_lab = (strpos($e->subject_code, ' L') !== false);
                        ?>
                        <td class="cs-cell">
                            <div class="cs-cell-entry <?php echo $is_lab ? 'lab' : ''; ?>">
                                <span class="ce-code"><?php echo esc_html($e->subject_code); ?></span>
                                <span class="ce-inst"><?php echo esc_html($e->instructor_initials); ?></span>
                                <span class="ce-room"><?php echo esc_html($e->room); ?></span>
                            </div>
                        </td>
                        <?php else: ?>
                        <td class="cs-cell cs-cell-empty"></td>
                        <?php endif; endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <script>
    document.getElementById('cs-apply-filter').addEventListener('click', function () {
        const sec = document.getElementById('cs-filter-section').value;
        const yr  = document.getElementById('cs-filter-year').value;
        const sem = document.getElementById('cs-filter-sem').value;
        const url = new URL(window.location.href);
        sec ? url.searchParams.set('cs_section', sec) : url.searchParams.delete('cs_section');
        yr  ? url.searchParams.set('cs_year', yr)     : url.searchParams.delete('cs_year');
        sem ? url.searchParams.set('cs_sem', sem)     : url.searchParams.delete('cs_sem');
        window.location.href = url.toString() + '#cs-tab-timetable';
    });
    document.getElementById('cs-print-btn').addEventListener('click', function () { window.print(); });
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: SECTIONS
// ─────────────────────────────────────────────────────────────

function cs_sections_tab($business_id) {
    global $wpdb;

    $sections = $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}cs_schedules WHERE section_id=s.id) as entry_count
         FROM {$wpdb->prefix}cs_sections s WHERE s.business_id=%d ORDER BY s.section_name ASC",
        $business_id
    ));

    $nonce = wp_create_nonce('cs_sections_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Class Sections</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-section-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Section
        </button>
        <div id="cs-section-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($sections)): ?>
        <p style="color:#6b7280;font-size:13.5px;">No sections yet. Click "Add Section" to create one.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Section Name</th>
                    <th>Course</th>
                    <th>Year</th>
                    <th>Block</th>
                    <th>Acad. Year</th>
                    <th>Semester</th>
                    <th>Entries</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $sec): ?>
                <tr>
                    <td><strong><?php echo esc_html($sec->section_name); ?></strong></td>
                    <td><?php echo esc_html($sec->course_code); ?></td>
                    <td>Year <?php echo esc_html($sec->year_level); ?></td>
                    <td><?php echo esc_html($sec->block); ?></td>
                    <td><?php echo esc_html($sec->academic_year ?: '—'); ?></td>
                    <td><?php echo esc_html($sec->semester); ?></td>
                    <td><span class="cs-badge cs-badge-blue"><?php echo (int)$sec->entry_count; ?></span></td>
                    <td><span class="cs-badge <?php echo $sec->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($sec->status); ?></span></td>
                    <td>
                        <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-section"
                                data-id="<?php echo $sec->id; ?>"
                                data-course="<?php echo esc_attr($sec->course_code); ?>"
                                data-year="<?php echo esc_attr($sec->year_level); ?>"
                                data-block="<?php echo esc_attr($sec->block); ?>"
                                data-acad="<?php echo esc_attr($sec->academic_year); ?>"
                                data-sem="<?php echo esc_attr($sec->semester); ?>"
                                data-status="<?php echo esc_attr($sec->status); ?>">Edit</button>
                        <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-section"
                                data-id="<?php echo $sec->id; ?>"
                                data-name="<?php echo esc_attr($sec->section_name); ?>"
                                style="margin-left:4px;">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';

        function sectionForm(data) {
            data = data || {};
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Section' : 'Add Section'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Course Code *</label>
                    <input type="text" id="sf-course" value="${data.course||''}" placeholder="e.g. CHE" maxlength="20">
                </div>
                <div class="cs-field">
                    <label>Year Level *</label>
                    <select id="sf-year">
                        ${[1,2,3,4,5].map(y=>`<option value="${y}" ${data.year==y?'selected':''}>${y}</option>`).join('')}
                    </select>
                </div>
                <div class="cs-field">
                    <label>Block *</label>
                    <input type="text" id="sf-block" value="${data.block||''}" placeholder="e.g. A1" maxlength="10">
                </div>
                <div class="cs-field">
                    <label>Academic Year</label>
                    <input type="text" id="sf-acad" value="${data.acad||''}" placeholder="e.g. 2024-2025">
                </div>
                <div class="cs-field">
                    <label>Semester</label>
                    <select id="sf-sem">
                        ${['First','Second','Summer'].map(s=>`<option value="${s}" ${data.sem===s?'selected':''}>${s}</option>`).join('')}
                    </select>
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="sf-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="archived" ${data.status==='archived'?'selected':''}>Archived</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="sf-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="sf-save">Save Section</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="sf-id" value="${data.id||0}">`;
        }

        document.getElementById('cs-add-section-btn').addEventListener('click', function () {
            csOpenModal(sectionForm());
            bindSectionSave();
        });

        document.querySelectorAll('.cs-edit-section').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(sectionForm({
                    id: this.dataset.id, course: this.dataset.course,
                    year: this.dataset.year, block: this.dataset.block,
                    acad: this.dataset.acad, sem: this.dataset.sem,
                    status: this.dataset.status
                }));
                bindSectionSave();
            });
        });

        function bindSectionSave() {
            document.getElementById('sf-save').addEventListener('click', function () {
                const btn = this;
                const fd  = new FormData();
                fd.append('action', 'cs_save_section');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('sf-id').value);
                fd.append('course_code', document.getElementById('sf-course').value.trim().toUpperCase());
                fd.append('year_level', document.getElementById('sf-year').value);
                fd.append('block', document.getElementById('sf-block').value.trim().toUpperCase());
                fd.append('academic_year', document.getElementById('sf-acad').value.trim());
                fd.append('semester', document.getElementById('sf-sem').value);
                const statusEl = document.getElementById('sf-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) { location.reload(); }
                    else {
                        csNotice(document.getElementById('sf-notice'), json.data.message, 'error');
                        btn.disabled = false; btn.textContent = 'Save Section';
                    }
                });
            });
        }

        document.querySelectorAll('.cs-del-section').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete section "' + this.dataset.name + '" and ALL its schedule entries? This cannot be undone.')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_section');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-section-notice'), json.data.message, 'error');
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: SCHEDULE ENTRY
// ─────────────────────────────────────────────────────────────

function cs_schedule_tab($business_id) {
    global $wpdb;

    $sections    = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE business_id=%d AND status='active' ORDER BY section_name ASC",
        $business_id
    ));
    $instructors = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_instructors WHERE business_id=%d AND status='active' ORDER BY full_name ASC",
        $business_id
    ));
    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_rooms WHERE business_id=%d AND status='active' ORDER BY room_code ASC",
        $business_id
    ));

    $selected_section = isset($_GET['cs_sched_section']) ? intval($_GET['cs_sched_section']) : (empty($sections) ? 0 : $sections[0]->id);
    $nonce = wp_create_nonce('cs_schedule_nonce');

    $entries = [];
    if ($selected_section) {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d AND business_id=%d ORDER BY day_group ASC, time_slot ASC",
            $selected_section, $business_id
        ));
    }

    $day_group_labels = BNTM_CS_DAY_GROUPS;

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Schedule Entry</h3>
        <div class="cs-form-grid" style="align-items:flex-end;">
            <div class="cs-field">
                <label>Select Section</label>
                <select id="cs-sched-section-filter">
                    <?php if (empty($sections)): ?>
                    <option value="">No sections available</option>
                    <?php else: foreach ($sections as $s): ?>
                    <option value="<?php echo $s->id; ?>" <?php selected($selected_section, $s->id); ?>>
                        <?php echo esc_html($s->section_name); ?>
                    </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <button class="cs-btn cs-btn-secondary" id="cs-go-section">View</button>
                <button class="cs-btn cs-btn-primary" id="cs-add-entry-btn" style="margin-left:6px;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Entry
                </button>
            </div>
        </div>
    </div>

    <div class="cs-panel" id="cs-entries-panel">
        <h3>Entries for Section</h3>
        <div id="cs-sched-notice"></div>
        <?php if (empty($entries)): ?>
        <p style="color:#6b7280;font-size:13.5px;">No schedule entries for this section yet.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Day Group</th>
                    <th>Time Slot</th>
                    <th>Instructor</th>
                    <th>Room</th>
                    <th>Type</th>
                    <th>Units</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td><strong><?php echo esc_html($e->subject_code); ?></strong></td>
                    <td><?php echo esc_html($e->subject_name ?: '—'); ?></td>
                    <td><?php echo esc_html($day_group_labels[$e->day_group] ?? $e->day_group); ?></td>
                    <td><?php echo esc_html($e->time_slot); ?></td>
                    <td><?php echo esc_html($e->instructor_initials); ?> <?php if ($e->instructor_name): ?><small style="color:#6b7280;">(<?php echo esc_html($e->instructor_name); ?>)</small><?php endif; ?></td>
                    <td><?php echo esc_html($e->room); ?></td>
                    <td><span class="cs-badge <?php echo $e->schedule_type === 'lab' ? 'cs-badge-green' : 'cs-badge-blue'; ?>"><?php echo ucfirst($e->schedule_type); ?></span></td>
                    <td><?php echo (int)$e->units; ?></td>
                    <td>
                        <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-entry"
                            data-id="<?php echo $e->id; ?>"
                            data-section="<?php echo $e->section_id; ?>"
                            data-code="<?php echo esc_attr($e->subject_code); ?>"
                            data-name="<?php echo esc_attr($e->subject_name); ?>"
                            data-initials="<?php echo esc_attr($e->instructor_initials); ?>"
                            data-instname="<?php echo esc_attr($e->instructor_name); ?>"
                            data-room="<?php echo esc_attr($e->room); ?>"
                            data-dg="<?php echo esc_attr($e->day_group); ?>"
                            data-slot="<?php echo esc_attr($e->time_slot); ?>"
                            data-type="<?php echo esc_attr($e->schedule_type); ?>"
                            data-units="<?php echo esc_attr($e->units); ?>">Edit</button>
                        <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-entry" data-id="<?php echo $e->id; ?>" style="margin-left:4px;">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce      = '<?php echo $nonce; ?>';
        const sections   = <?php echo json_encode(array_map(fn($s) => ['id'=>$s->id,'name'=>$s->section_name], $sections)); ?>;
        const instructors= <?php echo json_encode(array_map(fn($i) => ['id'=>$i->id,'initials'=>$i->initials,'name'=>$i->full_name], $instructors)); ?>;
        const rooms      = <?php echo json_encode(array_map(fn($r) => ['id'=>$r->id,'code'=>$r->room_code], $rooms)); ?>;
        const timeSlots  = <?php echo json_encode(BNTM_CS_TIME_SLOTS); ?>;
        const dayGroups  = <?php echo json_encode(BNTM_CS_DAY_GROUPS); ?>;
        const sectionId  = <?php echo $selected_section ?: 0; ?>;

        document.getElementById('cs-go-section').addEventListener('click', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('cs_sched_section', document.getElementById('cs-sched-section-filter').value);
            window.location.href = url.toString() + '#cs-tab-schedule';
        });

        function entryForm(data) {
            data = data || {};
            const secOptions = sections.map(s =>
                `<option value="${s.id}" ${(data.section||sectionId)==s.id?'selected':''}>${s.name}</option>`
            ).join('');
            const instOptions = '<option value="">-- Select Instructor --</option>' + instructors.map(i =>
                `<option value="${i.initials}|${i.name}" ${data.initials===i.initials?'selected':''}>${i.name} (${i.initials})</option>`
            ).join('');
            const roomOptions = '<option value="">-- Select Room --</option>' + rooms.map(r =>
                `<option value="${r.code}">${r.code}</option>`
            ).join('');
            const dgOptions = Object.entries(dayGroups).map(([k,v]) =>
                `<option value="${k}" ${data.dg===k?'selected':''}>${v}</option>`
            ).join('');
            const slotOptions = timeSlots.map(s =>
                `<option value="${s}" ${data.slot===s?'selected':''}>${s}</option>`
            ).join('');

            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Entry' : 'Add Schedule Entry'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Section *</label>
                    <select id="ef-section">${secOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Day Group *</label>
                    <select id="ef-dg">${dgOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Time Slot *</label>
                    <select id="ef-slot">${slotOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Subject Code * <small style="color:#6b7280;">(add " L" for lab)</small></label>
                    <input type="text" id="ef-code" value="${data.code||''}" placeholder="e.g. MATH 89 or CHEM 86 L">
                </div>
                <div class="cs-field">
                    <label>Subject Name</label>
                    <input type="text" id="ef-name" value="${data.name||''}" placeholder="Full subject name (optional)">
                </div>
                <div class="cs-field">
                    <label>Instructor</label>
                    <select id="ef-inst">${instOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Instructor Initials</label>
                    <input type="text" id="ef-initials" value="${data.initials||''}" placeholder="Auto-filled or custom">
                </div>
                <div class="cs-field">
                    <label>Room (from list)</label>
                    <select id="ef-room-select">${roomOptions}</select>
                </div>
                <div class="cs-field">
                    <label>Room Override <small style="color:#6b7280;">(overrides list)</small></label>
                    <input type="text" id="ef-room" value="${data.room||''}" placeholder="Custom room code">
                </div>
                <div class="cs-field">
                    <label>Schedule Type</label>
                    <select id="ef-type">
                        <option value="lecture" ${data.type==='lecture'?'selected':''}>Lecture</option>
                        <option value="lab"     ${data.type==='lab'?'selected':''}>Lab</option>
                        <option value="both"    ${data.type==='both'?'selected':''}>Both</option>
                    </select>
                </div>
                <div class="cs-field">
                    <label>Units</label>
                    <input type="number" id="ef-units" value="${data.units||3}" min="1" max="9">
                </div>
            </div>
            <div id="ef-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="ef-save">Save Entry</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="ef-id" value="${data.id||0}">`;
        }

        function bindEntryForm() {
            // Auto-fill initials from instructor dropdown
            document.getElementById('ef-inst').addEventListener('change', function () {
                const parts = this.value.split('|');
                document.getElementById('ef-initials').value = parts[0] || '';
            });
            document.getElementById('ef-save').addEventListener('click', function () {
                const btn = this;
                const roomOverride = document.getElementById('ef-room').value.trim();
                const roomSelect   = document.getElementById('ef-room-select').value;
                const roomFinal    = roomOverride || roomSelect;
                const fd = new FormData();
                fd.append('action', 'cs_save_schedule');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('ef-id').value);
                fd.append('section_id', document.getElementById('ef-section').value);
                fd.append('day_group', document.getElementById('ef-dg').value);
                fd.append('time_slot', document.getElementById('ef-slot').value);
                fd.append('subject_code', document.getElementById('ef-code').value.trim());
                fd.append('subject_name', document.getElementById('ef-name').value.trim());
                fd.append('instructor_initials', document.getElementById('ef-initials').value.trim().toUpperCase());
                const instParts = document.getElementById('ef-inst').value.split('|');
                fd.append('instructor_name', instParts[1] || '');
                fd.append('room', roomFinal.toUpperCase());
                fd.append('schedule_type', document.getElementById('ef-type').value);
                fd.append('units', document.getElementById('ef-units').value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else {
                        csNotice(document.getElementById('ef-notice'), json.data.message, 'error');
                        btn.disabled = false; btn.textContent = 'Save Entry';
                    }
                });
            });
        }

        document.getElementById('cs-add-entry-btn').addEventListener('click', function () {
            csOpenModal(entryForm());
            bindEntryForm();
        });

        document.querySelectorAll('.cs-edit-entry').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(entryForm({
                    id: this.dataset.id, section: this.dataset.section,
                    code: this.dataset.code, name: this.dataset.name,
                    initials: this.dataset.initials, instname: this.dataset.instname,
                    room: this.dataset.room, dg: this.dataset.dg,
                    slot: this.dataset.slot, type: this.dataset.type,
                    units: this.dataset.units
                }));
                bindEntryForm();
            });
        });

        document.querySelectorAll('.cs-del-entry').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this schedule entry?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_schedule');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-sched-notice'), json.data.message, 'error');
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: INSTRUCTORS
// ─────────────────────────────────────────────────────────────

function cs_instructors_tab($business_id) {
    global $wpdb;

    $instructors = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_instructors WHERE business_id=%d ORDER BY full_name ASC",
        $business_id
    ));

    $nonce = wp_create_nonce('cs_instructors_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Instructors</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-inst-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Instructor
        </button>
        <div id="cs-inst-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($instructors)): ?>
        <p style="color:#6b7280;font-size:13.5px;">No instructors yet.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr><th>Initials</th><th>Full Name</th><th>Department</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($instructors as $inst): ?>
                <tr>
                    <td><strong><?php echo esc_html($inst->initials); ?></strong></td>
                    <td><?php echo esc_html($inst->full_name); ?></td>
                    <td><?php echo esc_html($inst->department ?: '—'); ?></td>
                    <td><span class="cs-badge <?php echo $inst->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($inst->status); ?></span></td>
                    <td>
                        <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-inst"
                            data-id="<?php echo $inst->id; ?>"
                            data-initials="<?php echo esc_attr($inst->initials); ?>"
                            data-name="<?php echo esc_attr($inst->full_name); ?>"
                            data-dept="<?php echo esc_attr($inst->department); ?>"
                            data-status="<?php echo esc_attr($inst->status); ?>">Edit</button>
                        <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-inst" data-id="<?php echo $inst->id; ?>" style="margin-left:4px;">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';

        function instForm(data) {
            data = data || {};
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Instructor' : 'Add Instructor'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Initials * <small style="color:#6b7280;">(shown on timetable)</small></label>
                    <input type="text" id="if-initials" value="${data.initials||''}" placeholder="e.g. EORTIZ" maxlength="30">
                </div>
                <div class="cs-field">
                    <label>Full Name *</label>
                    <input type="text" id="if-name" value="${data.name||''}" placeholder="Full legal name">
                </div>
                <div class="cs-field">
                    <label>Department</label>
                    <input type="text" id="if-dept" value="${data.dept||''}" placeholder="Optional">
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="if-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="if-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="if-save">Save Instructor</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="if-id" value="${data.id||0}">`;
        }

        function bindInstForm() {
            document.getElementById('if-save').addEventListener('click', function () {
                const btn = this;
                const fd = new FormData();
                fd.append('action', 'cs_save_instructor');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('if-id').value);
                fd.append('initials', document.getElementById('if-initials').value.trim().toUpperCase());
                fd.append('full_name', document.getElementById('if-name').value.trim());
                fd.append('department', document.getElementById('if-dept').value.trim());
                const statusEl = document.getElementById('if-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else { csNotice(document.getElementById('if-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Save Instructor'; }
                });
            });
        }

        document.getElementById('cs-add-inst-btn').addEventListener('click', function () {
            csOpenModal(instForm()); bindInstForm();
        });

        document.querySelectorAll('.cs-edit-inst').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(instForm({ id: this.dataset.id, initials: this.dataset.initials, name: this.dataset.name, dept: this.dataset.dept, status: this.dataset.status }));
                bindInstForm();
            });
        });

        document.querySelectorAll('.cs-del-inst').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this instructor?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_instructor');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-inst-notice'), json.data.message, 'error');
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// TAB: ROOMS
// ─────────────────────────────────────────────────────────────

function cs_rooms_tab($business_id) {
    global $wpdb;

    $rooms = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_rooms WHERE business_id=%d ORDER BY room_code ASC",
        $business_id
    ));

    $nonce = wp_create_nonce('cs_rooms_nonce');

    ob_start();
    ?>
    <div class="cs-panel">
        <h3>Rooms</h3>
        <button class="cs-btn cs-btn-primary" id="cs-add-room-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Room
        </button>
        <div id="cs-room-notice"></div>
    </div>

    <div class="cs-panel">
        <?php if (empty($rooms)): ?>
        <p style="color:#6b7280;font-size:13.5px;">No rooms yet.</p>
        <?php else: ?>
        <div class="cs-table-wrap">
        <table class="cs-table">
            <thead>
                <tr><th>Room Code</th><th>Building</th><th>Capacity</th><th>Type</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $r): ?>
                <tr>
                    <td><strong><?php echo esc_html($r->room_code); ?></strong></td>
                    <td><?php echo esc_html($r->building ?: '—'); ?></td>
                    <td><?php echo (int)$r->capacity; ?></td>
                    <td><span class="cs-badge cs-badge-blue"><?php echo ucfirst($r->room_type); ?></span></td>
                    <td><span class="cs-badge <?php echo $r->status === 'active' ? 'cs-badge-green' : 'cs-badge-gray'; ?>"><?php echo ucfirst($r->status); ?></span></td>
                    <td>
                        <button class="cs-btn cs-btn-secondary cs-btn-sm cs-edit-room"
                            data-id="<?php echo $r->id; ?>"
                            data-code="<?php echo esc_attr($r->room_code); ?>"
                            data-building="<?php echo esc_attr($r->building); ?>"
                            data-capacity="<?php echo esc_attr($r->capacity); ?>"
                            data-type="<?php echo esc_attr($r->room_type); ?>"
                            data-status="<?php echo esc_attr($r->status); ?>">Edit</button>
                        <button class="cs-btn cs-btn-danger cs-btn-sm cs-del-room" data-id="<?php echo $r->id; ?>" style="margin-left:4px;">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function () {
        const nonce = '<?php echo $nonce; ?>';

        function roomForm(data) {
            data = data || {};
            return `
            <h2 class="cs-modal-title">${data.id ? 'Edit Room' : 'Add Room'}</h2>
            <div class="cs-form-grid">
                <div class="cs-field">
                    <label>Room Code * <small style="color:#6b7280;">(shown on timetable)</small></label>
                    <input type="text" id="rf-code" value="${data.code||''}" placeholder="e.g. E207" maxlength="30">
                </div>
                <div class="cs-field">
                    <label>Building</label>
                    <input type="text" id="rf-building" value="${data.building||''}" placeholder="Optional">
                </div>
                <div class="cs-field">
                    <label>Capacity</label>
                    <input type="number" id="rf-capacity" value="${data.capacity||40}" min="1" max="999">
                </div>
                <div class="cs-field">
                    <label>Room Type</label>
                    <select id="rf-type">
                        <option value="lecture" ${data.type==='lecture'?'selected':''}>Lecture</option>
                        <option value="lab"     ${data.type==='lab'?'selected':''}>Lab</option>
                        <option value="both"    ${data.type==='both'?'selected':''}>Both</option>
                    </select>
                </div>
                ${data.id ? `<div class="cs-field">
                    <label>Status</label>
                    <select id="rf-status">
                        <option value="active" ${data.status==='active'?'selected':''}>Active</option>
                        <option value="inactive" ${data.status==='inactive'?'selected':''}>Inactive</option>
                    </select>
                </div>` : ''}
            </div>
            <div id="rf-notice"></div>
            <div class="cs-btn-group">
                <button class="cs-btn cs-btn-primary" id="rf-save">Save Room</button>
                <button class="cs-btn cs-btn-secondary" onclick="csCloseModal()">Cancel</button>
            </div>
            <input type="hidden" id="rf-id" value="${data.id||0}">`;
        }

        function bindRoomForm() {
            document.getElementById('rf-save').addEventListener('click', function () {
                const btn = this;
                const fd = new FormData();
                fd.append('action', 'cs_save_room');
                fd.append('nonce', nonce);
                fd.append('id', document.getElementById('rf-id').value);
                fd.append('room_code', document.getElementById('rf-code').value.trim().toUpperCase());
                fd.append('building', document.getElementById('rf-building').value.trim());
                fd.append('capacity', document.getElementById('rf-capacity').value);
                fd.append('room_type', document.getElementById('rf-type').value);
                const statusEl = document.getElementById('rf-status');
                if (statusEl) fd.append('status', statusEl.value);
                btn.disabled = true; btn.textContent = 'Saving...';
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else { csNotice(document.getElementById('rf-notice'), json.data.message, 'error'); btn.disabled=false; btn.textContent='Save Room'; }
                });
            });
        }

        document.getElementById('cs-add-room-btn').addEventListener('click', function () {
            csOpenModal(roomForm()); bindRoomForm();
        });

        document.querySelectorAll('.cs-edit-room').forEach(function (btn) {
            btn.addEventListener('click', function () {
                csOpenModal(roomForm({ id: this.dataset.id, code: this.dataset.code, building: this.dataset.building, capacity: this.dataset.capacity, type: this.dataset.type, status: this.dataset.status }));
                bindRoomForm();
            });
        });

        document.querySelectorAll('.cs-del-room').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this room?')) return;
                const fd = new FormData();
                fd.append('action', 'cs_delete_room');
                fd.append('nonce', nonce);
                fd.append('id', this.dataset.id);
                csAjax(fd).then(json => {
                    if (json.success) location.reload();
                    else csNotice(document.getElementById('cs-room-notice'), json.data.message, 'error');
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────
// AJAX: SECTIONS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_section() {
    check_ajax_referer('cs_sections_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id  = get_current_user_id();
    $id           = intval($_POST['id']);
    $course_code  = strtoupper(sanitize_text_field($_POST['course_code']));
    $year_level   = intval($_POST['year_level']);
    $block        = strtoupper(sanitize_text_field($_POST['block']));
    $academic_year= sanitize_text_field($_POST['academic_year'] ?? '');
    $semester     = sanitize_text_field($_POST['semester'] ?? 'First');
    $status       = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($course_code) || empty($block) || $year_level < 1) {
        wp_send_json_error(['message' => 'Course code, year level, and block are required.']);
    }

    $section_name = $course_code . $year_level . ' ' . $block;

    $table = $wpdb->prefix . 'cs_sections';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('section_name','course_code','year_level','block','academic_year','semester','status'),
            ['id' => $id, 'business_id' => $business_id],
            ['%s','%s','%d','%s','%s','%s','%s'], ['%d','%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Section updated.']);
        else wp_send_json_error(['message' => 'Failed to update section.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'business_id'=>$business_id,'section_name'=>$section_name,
             'course_code'=>$course_code,'year_level'=>$year_level,'block'=>$block,
             'academic_year'=>$academic_year,'semester'=>$semester,'status'=>'active'],
            ['%s','%d','%s','%s','%d','%s','%s','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Section created.', 'id' => $wpdb->insert_id]);
        else wp_send_json_error(['message' => 'Failed to create section.']);
    }
}

function bntm_ajax_cs_delete_section() {
    check_ajax_referer('cs_sections_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id']);

    // Cascade delete schedule entries
    $wpdb->delete($wpdb->prefix . 'cs_schedules', ['section_id' => $id, 'business_id' => $business_id], ['%d','%d']);
    $result = $wpdb->delete($wpdb->prefix . 'cs_sections', ['id' => $id, 'business_id' => $business_id], ['%d','%d']);

    if ($result) wp_send_json_success(['message' => 'Section deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete section.']);
}

// ─────────────────────────────────────────────────────────────
// AJAX: SCHEDULES
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_schedule() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id        = get_current_user_id();
    $id                 = intval($_POST['id']);
    $section_id         = intval($_POST['section_id']);
    $day_group          = sanitize_text_field($_POST['day_group']);
    $time_slot          = sanitize_text_field($_POST['time_slot']);
    $subject_code       = sanitize_text_field($_POST['subject_code']);
    $subject_name       = sanitize_text_field($_POST['subject_name'] ?? '');
    $instructor_initials= strtoupper(sanitize_text_field($_POST['instructor_initials'] ?? ''));
    $instructor_name    = sanitize_text_field($_POST['instructor_name'] ?? '');
    $room               = strtoupper(sanitize_text_field($_POST['room'] ?? ''));
    $schedule_type      = sanitize_text_field($_POST['schedule_type'] ?? 'lecture');
    $units              = intval($_POST['units'] ?? 3);

    if (!$section_id || empty($subject_code) || empty($day_group) || empty($time_slot)) {
        wp_send_json_error(['message' => 'Section, subject code, day group, and time slot are required.']);
    }

    $valid_dg   = array_keys(BNTM_CS_DAY_GROUPS);
    $valid_slot = BNTM_CS_TIME_SLOTS;
    if (!in_array($day_group, $valid_dg))   wp_send_json_error(['message' => 'Invalid day group.']);
    if (!in_array($time_slot, $valid_slot)) wp_send_json_error(['message' => 'Invalid time slot.']);

    $table = $wpdb->prefix . 'cs_schedules';

    // Conflict check: same section + same day_group + same time_slot
    $conflict = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE section_id=%d AND day_group=%s AND time_slot=%s AND id != %d",
        $section_id, $day_group, $time_slot, $id
    ));
    if ($conflict) {
        wp_send_json_error(['message' => 'Conflict: This section already has a subject at ' . esc_html($day_group) . ' / ' . esc_html($time_slot) . '.']);
    }

    $data   = compact('section_id','business_id','subject_code','subject_name',
                      'instructor_initials','instructor_name','room','day_group',
                      'time_slot','schedule_type','units');
    $format = ['%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%d'];

    if ($id > 0) {
        $result = $wpdb->update($table, $data, ['id'=>$id,'business_id'=>$business_id], $format, ['%d','%d']);
        if ($result !== false) wp_send_json_success(['message' => 'Entry updated.']);
        else wp_send_json_error(['message' => 'Failed to update entry.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $data['rand_id'] = $rand_id;
        $result = $wpdb->insert($table, $data, array_merge(['%s'], $format));
        if ($result) wp_send_json_success(['message' => 'Entry added.', 'id' => $wpdb->insert_id]);
        else wp_send_json_error(['message' => 'Failed to add entry.']);
    }
}

function bntm_ajax_cs_delete_schedule() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_schedules', ['id'=>$id,'business_id'=>$business_id], ['%d','%d']);

    if ($result) wp_send_json_success(['message' => 'Entry deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete entry.']);
}

function bntm_ajax_cs_get_section_data() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $section_id  = intval($_POST['section_id']);

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d AND business_id=%d",
        $section_id, $business_id
    ));

    wp_send_json_success(['entries' => $entries]);
}

function bntm_ajax_cs_bulk_import_section() {
    check_ajax_referer('cs_schedule_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $section_id  = intval($_POST['section_id']);
    $entries     = json_decode(stripslashes($_POST['entries']), true);

    if (empty($entries) || !is_array($entries)) {
        wp_send_json_error(['message' => 'No entries provided.']);
    }

    $table   = $wpdb->prefix . 'cs_schedules';
    $imported = 0;

    foreach ($entries as $e) {
        $dg   = sanitize_text_field($e['day_group'] ?? '');
        $slot = sanitize_text_field($e['time_slot'] ?? '');
        if (!in_array($dg, array_keys(BNTM_CS_DAY_GROUPS))) continue;
        if (!in_array($slot, BNTM_CS_TIME_SLOTS)) continue;

        // Skip duplicates
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE section_id=%d AND day_group=%s AND time_slot=%s",
            $section_id, $dg, $slot
        ));
        if ($exists) continue;

        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table, [
            'rand_id'             => $rand_id,
            'business_id'         => $business_id,
            'section_id'          => $section_id,
            'subject_code'        => sanitize_text_field($e['subject_code'] ?? ''),
            'subject_name'        => sanitize_text_field($e['subject_name'] ?? ''),
            'instructor_initials' => strtoupper(sanitize_text_field($e['instructor_initials'] ?? '')),
            'instructor_name'     => sanitize_text_field($e['instructor_name'] ?? ''),
            'room'                => strtoupper(sanitize_text_field($e['room'] ?? '')),
            'day_group'           => $dg,
            'time_slot'           => $slot,
            'schedule_type'       => sanitize_text_field($e['schedule_type'] ?? 'lecture'),
            'units'               => intval($e['units'] ?? 3),
        ], ['%s','%d','%d','%s','%s','%s','%s','%s','%s','%s','%s','%d']);

        if ($result) $imported++;
    }

    wp_send_json_success(['message' => "Imported {$imported} entr" . ($imported === 1 ? 'y' : 'ies') . '.',"imported" => $imported]);
}

// ─────────────────────────────────────────────────────────────
// AJAX: INSTRUCTORS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_instructor() {
    check_ajax_referer('cs_instructors_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id']);
    $initials    = strtoupper(sanitize_text_field($_POST['initials']));
    $full_name   = sanitize_text_field($_POST['full_name']);
    $department  = sanitize_text_field($_POST['department'] ?? '');
    $status      = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($initials) || empty($full_name)) {
        wp_send_json_error(['message' => 'Initials and full name are required.']);
    }

    $table = $wpdb->prefix . 'cs_instructors';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('initials','full_name','department','status'),
            ['id'=>$id,'business_id'=>$business_id],
            ['%s','%s','%s','%s'], ['%d','%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Instructor updated.']);
        else wp_send_json_error(['message' => 'Failed to update.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'business_id'=>$business_id,'initials'=>$initials,
             'full_name'=>$full_name,'department'=>$department,'status'=>'active'],
            ['%s','%d','%s','%s','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Instructor added.']);
        else wp_send_json_error(['message' => 'Failed to add instructor.']);
    }
}

function bntm_ajax_cs_delete_instructor() {
    check_ajax_referer('cs_instructors_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_instructors', ['id'=>$id,'business_id'=>$business_id], ['%d','%d']);

    if ($result) wp_send_json_success(['message' => 'Instructor deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete.']);
}

// ─────────────────────────────────────────────────────────────
// AJAX: ROOMS
// ─────────────────────────────────────────────────────────────

function bntm_ajax_cs_save_room() {
    check_ajax_referer('cs_rooms_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id']);
    $room_code   = strtoupper(sanitize_text_field($_POST['room_code']));
    $building    = sanitize_text_field($_POST['building'] ?? '');
    $capacity    = intval($_POST['capacity'] ?? 40);
    $room_type   = sanitize_text_field($_POST['room_type'] ?? 'lecture');
    $status      = sanitize_text_field($_POST['status'] ?? 'active');

    if (empty($room_code)) {
        wp_send_json_error(['message' => 'Room code is required.']);
    }

    $table = $wpdb->prefix . 'cs_rooms';

    if ($id > 0) {
        $result = $wpdb->update($table,
            compact('room_code','building','capacity','room_type','status'),
            ['id'=>$id,'business_id'=>$business_id],
            ['%s','%s','%d','%s','%s'], ['%d','%d']
        );
        if ($result !== false) wp_send_json_success(['message' => 'Room updated.']);
        else wp_send_json_error(['message' => 'Failed to update room.']);
    } else {
        $rand_id = function_exists('bntm_rand_id') ? bntm_rand_id() : wp_generate_password(12, false);
        $result  = $wpdb->insert($table,
            ['rand_id'=>$rand_id,'business_id'=>$business_id,'room_code'=>$room_code,
             'building'=>$building,'capacity'=>$capacity,'room_type'=>$room_type,'status'=>'active'],
            ['%s','%d','%s','%s','%d','%s','%s']
        );
        if ($result) wp_send_json_success(['message' => 'Room added.']);
        else wp_send_json_error(['message' => 'Failed to add room.']);
    }
}

function bntm_ajax_cs_delete_room() {
    check_ajax_referer('cs_rooms_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $business_id = get_current_user_id();
    $id          = intval($_POST['id']);
    $result      = $wpdb->delete($wpdb->prefix . 'cs_rooms', ['id'=>$id,'business_id'=>$business_id], ['%d','%d']);

    if ($result) wp_send_json_success(['message' => 'Room deleted.']);
    else wp_send_json_error(['message' => 'Failed to delete.']);
}

// ─────────────────────────────────────────────────────────────
// PUBLIC SHORTCODE: SECTION TIMETABLE
// ─────────────────────────────────────────────────────────────

function bntm_shortcode_cs_public() {
    global $wpdb;

    $section_id = isset($_GET['section']) ? intval($_GET['section']) : 0;

    if (!$section_id) {
        return '<div class="cs-notice cs-notice-info" style="font-family:sans-serif;">Please provide a section ID in the URL: <code>?section=ID</code></div>';
    }

    $section = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_sections WHERE id=%d AND status='active'",
        $section_id
    ));

    if (!$section) {
        return '<div class="cs-notice cs-notice-error" style="font-family:sans-serif;">Section not found.</div>';
    }

    $entries = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cs_schedules WHERE section_id=%d",
        $section_id
    ));

    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $day_group_map = ['mon_thu'=>[0,3],'tue_fri'=>[1,4],'wed_sat'=>[2,5]];

    $grid = [];
    foreach ($entries as $e) {
        foreach ($day_group_map[$e->day_group] as $di) {
            $grid[$di][$e->time_slot] = $e;
        }
    }

    ob_start();
    ?>
    <style>
    .cs-pub * { box-sizing: border-box; font-family: system-ui,sans-serif; }
    .cs-pub h2 { font-size: 18px; font-weight: 700; color: #1e3a5f; margin: 0 0 4px; }
    .cs-pub .sub { font-size: 13px; color: #6b7280; margin-bottom: 18px; }
    .cs-pub-wrap { overflow-x: auto; }
    .cs-pub-table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 700px; }
    .cs-pub-table th { background: #1e3a5f; color: #fff; padding: 10px 8px; text-align: center; border: 1px solid #1e3a5f; font-size: 12px; }
    .cs-pub-table td { border: 1px solid #e5e7eb; padding: 0; vertical-align: top; }
    .cs-pub-slot { background: #f8fafc; padding: 10px 12px; font-weight: 600; font-size: 12px; color: #374151; text-align: center; white-space: nowrap; }
    .cs-pub-cell { padding: 6px 8px; min-height: 60px; }
    .cs-pub-entry { background: #dbeafe; border: 1px solid #93c5fd; border-radius: 5px; padding: 5px 7px; line-height: 1.45; }
    .cs-pub-entry.lab { background: #d1fae5; border-color: #6ee7b7; }
    .cs-pub-entry b { display: block; font-size: 12px; color: #1e3a5f; }
    .cs-pub-entry span { font-size: 11px; color: #374151; display: block; }
    .cs-pub-empty { background: #fafafa; }
    .cs-pub-legend { display: flex; gap: 16px; margin-bottom: 12px; font-size: 12px; }
    .cs-pub-legend-item { display: flex; align-items: center; gap: 6px; }
    .cs-pub-legend-box { width: 14px; height: 14px; border-radius: 3px; }
    </style>
    <div class="cs-pub">
        <h2><?php echo esc_html($section->section_name); ?></h2>
        <div class="sub">
            <?php echo esc_html($section->academic_year ?: ''); ?>
            <?php if ($section->academic_year && $section->semester): echo ' &bull; '; endif; ?>
            <?php echo esc_html($section->semester); ?> Semester
        </div>
        <div class="cs-pub-legend">
            <div class="cs-pub-legend-item"><div class="cs-pub-legend-box" style="background:#dbeafe;border:1px solid #93c5fd"></div> Lecture</div>
            <div class="cs-pub-legend-item"><div class="cs-pub-legend-box" style="background:#d1fae5;border:1px solid #6ee7b7"></div> Lab</div>
        </div>
        <div class="cs-pub-wrap">
        <table class="cs-pub-table">
            <thead>
                <tr>
                    <th style="min-width:100px;">Time</th>
                    <?php foreach ($days as $d): ?><th><?php echo $d; ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (BNTM_CS_TIME_SLOTS as $slot): ?>
                <tr>
                    <td class="cs-pub-slot"><?php echo esc_html($slot); ?></td>
                    <?php for ($di = 0; $di < 7; $di++):
                        if ($di === 6) { echo '<td class="cs-pub-cell cs-pub-empty"></td>'; continue; }
                        if (isset($grid[$di][$slot])):
                            $e = $grid[$di][$slot];
                            $is_lab = strpos($e->subject_code, ' L') !== false;
                    ?>
                    <td class="cs-pub-cell">
                        <div class="cs-pub-entry <?php echo $is_lab ? 'lab' : ''; ?>">
                            <b><?php echo esc_html($e->subject_code); ?></b>
                            <span><?php echo esc_html($e->instructor_initials); ?></span>
                            <span><?php echo esc_html($e->room); ?></span>
                        </div>
                    </td>
                    <?php else: ?>
                    <td class="cs-pub-cell cs-pub-empty"></td>
                    <?php endif; endfor; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php
    return ob_get_clean();
}