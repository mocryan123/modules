<?php
/**
 * Module Name: Pomodoro Timer
 * Module Slug: pt
 * Description: Productivity timer with 25/5 work/break cycles, session tracking, and statistics
 * Version: 1.0.0
 * Author: Business Network Team Manager
 * Icon: ⏱️
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_POMODORO_PATH', dirname(__FILE__) . '/');
define('BNTM_POMODORO_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================================

function bntm_pt_get_pages() {
    return [
        'Pomodoro Timer' => '[pt_timer_dashboard]',
        'My Sessions' => '[pt_my_sessions]',
    ];
}

function bntm_pt_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'pt_sessions' => "CREATE TABLE {$prefix}pt_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            session_type ENUM('work', 'short_break', 'long_break') NOT NULL DEFAULT 'work',
            planned_duration INT NOT NULL DEFAULT 1500,
            actual_duration INT NOT NULL DEFAULT 0,
            task_name VARCHAR(255),
            status ENUM('completed', 'interrupted', 'skipped') NOT NULL DEFAULT 'completed',
            completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_status (status),
            INDEX idx_completed (completed_at)
        ) {$charset};",
        
        'pt_presets' => "CREATE TABLE {$prefix}pt_presets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            preset_name VARCHAR(100) NOT NULL,
            work_duration INT NOT NULL DEFAULT 1500,
            short_break_duration INT NOT NULL DEFAULT 300,
            long_break_duration INT NOT NULL DEFAULT 900,
            sessions_before_long_break INT NOT NULL DEFAULT 4,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_default (is_default)
        ) {$charset};"
    ];
}

function bntm_pt_get_shortcodes() {
    return [
        'pt_timer_dashboard' => 'bntm_shortcode_pt_timer',
        'pt_my_sessions' => 'bntm_shortcode_pt_sessions',
    ];
}

function bntm_pt_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_pt_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Create default preset
    global $wpdb;
    $business_id = get_current_user_id();
    $preset_table = $wpdb->prefix . 'pt_presets';
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$preset_table} WHERE business_id = %d AND is_default = 1",
        $business_id
    ));
    
    if (!$exists) {
        $wpdb->insert($preset_table, [
            'rand_id' => bntm_rand_id(),
            'business_id' => $business_id,
            'preset_name' => 'Classic Pomodoro',
            'work_duration' => 1500,
            'short_break_duration' => 300,
            'long_break_duration' => 900,
            'sessions_before_long_break' => 4,
            'is_default' => 1
        ], ['%s','%d','%s','%d','%d','%d','%d','%d']);
    }
    
    return count($tables);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

add_action('wp_ajax_pt_save_session', 'bntm_ajax_pt_save_session');
add_action('wp_ajax_pt_get_stats', 'bntm_ajax_pt_get_stats');
add_action('wp_ajax_pt_save_preset', 'bntm_ajax_pt_save_preset');
add_action('wp_ajax_pt_delete_preset', 'bntm_ajax_pt_delete_preset');
add_action('wp_ajax_pt_set_default_preset', 'bntm_ajax_pt_set_default_preset');
add_action('wp_ajax_pt_delete_session', 'bntm_ajax_pt_delete_session');
add_action('wp_ajax_pt_get_preset', 'bntm_ajax_pt_get_preset');

// ============================================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_pt_timer() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to use the Pomodoro Timer.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'timer';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <style>
    .pomodoro-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s;
    }
    
    .pomodoro-modal-content {
        background-color: #ffffff;
        margin: 5% auto;
        padding: 0;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: slideDown 0.3s;
    }
    
    .pomodoro-modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .pomodoro-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: #111827;
    }
    
    .pomodoro-modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
    }
    
    .pomodoro-modal-close:hover {
        background-color: #f3f4f6;
        color: #111827;
    }
    
    .pomodoro-modal-body {
        padding: 25px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideDown {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    </style>
    
    <div class="bntm-pomodoro-container">
        <div class="bntm-tabs">
            <a href="?tab=timer" class="bntm-tab <?php echo $active_tab === 'timer' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                Timer
            </a>
            <a href="?tab=history" class="bntm-tab <?php echo $active_tab === 'history' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3v18h18"></path>
                    <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"></path>
                </svg>
                History
            </a>
            <a href="?tab=presets" class="bntm-tab <?php echo $active_tab === 'presets' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6M5.6 5.6l4.2 4.2m4.2 4.2l4.2 4.2M1 12h6m6 0h6M5.6 18.4l4.2-4.2m4.2-4.2l4.2-4.2"></path>
                </svg>
                Presets
            </a>
            <a href="?tab=stats" class="bntm-tab <?php echo $active_tab === 'stats' ? 'active' : ''; ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="20" x2="12" y2="10"></line>
                    <line x1="18" y1="20" x2="18" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="16"></line>
                </svg>
                Statistics
            </a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'timer'): ?>
                <?php echo pt_timer_tab($business_id); ?>
            <?php elseif ($active_tab === 'history'): ?>
                <?php echo pt_history_tab($business_id); ?>
            <?php elseif ($active_tab === 'presets'): ?>
                <?php echo pt_presets_tab($business_id); ?>
            <?php elseif ($active_tab === 'stats'): ?>
                <?php echo pt_stats_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Pomodoro Timer', $content);
}

// ============================================================================
// TAB RENDERING FUNCTIONS
// ============================================================================

function pt_timer_tab($business_id) {
    global $wpdb;
    $presets_table = $wpdb->prefix . 'pt_presets';
    
    // Get default preset
    $preset = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$presets_table} WHERE business_id = %d AND is_default = 1 LIMIT 1",
        $business_id
    ));
    
    if (!$preset) {
        $preset = (object)[
            'work_duration' => 1500,
            'short_break_duration' => 300,
            'long_break_duration' => 900,
            'sessions_before_long_break' => 4
        ];
    }
    
    // Get all presets for dropdown
    $all_presets = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$presets_table} WHERE business_id = %d ORDER BY is_default DESC, preset_name ASC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('pt_timer_nonce');
    
    ob_start();
    ?>
    <div class="pomodoro-timer-wrapper">
        <div class="pomodoro-controls-top">
            <div class="bntm-form-group" style="margin-bottom: 0; max-width: 300px;">
                <label style="font-size: 13px; margin-bottom: 8px; display: block; color: #6b7280;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6M5.6 5.6l4.2 4.2m4.2 4.2l4.2 4.2M1 12h6m6 0h6M5.6 18.4l4.2-4.2m4.2-4.2l4.2-4.2"></path>
                    </svg>
                    Timer Preset
                </label>
                <select id="preset-selector" class="bntm-input">
                    <?php foreach ($all_presets as $p): ?>
                        <option value="<?php echo $p->id; ?>" <?php selected($p->is_default, 1); ?>
                                data-work="<?php echo $p->work_duration; ?>"
                                data-short="<?php echo $p->short_break_duration; ?>"
                                data-long="<?php echo $p->long_break_duration; ?>"
                                data-sessions="<?php echo $p->sessions_before_long_break; ?>">
                            <?php echo esc_html($p->preset_name); ?>
                            <?php echo $p->is_default ? '(Default)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="pomodoro-timer-container">
            <div class="pomodoro-session-indicator">
                <div class="pomodoro-session-dots" id="session-dots"></div>
            </div>
            
            <div class="pomodoro-timer-display">
                <div class="timer-mode" id="timer-mode">Work Session</div>
                <div class="timer-circle">
                    <svg class="timer-ring" width="300" height="300">
                        <circle class="timer-ring-bg" cx="150" cy="150" r="140"></circle>
                        <circle class="timer-ring-progress" cx="150" cy="150" r="140" id="timer-progress-ring"></circle>
                    </svg>
                    <div class="timer-content">
                        <div class="timer-time" id="timer-display">25:00</div>
                        <div class="timer-status" id="timer-status">Ready to focus</div>
                    </div>
                </div>
            </div>
            
            <div class="pomodoro-task-input">
                <input type="text" id="task-input" class="bntm-input" placeholder="What are you working on?" maxlength="255">
            </div>
            
            <div class="pomodoro-controls">
                <button id="start-btn" class="pomodoro-btn pomodoro-btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    Start
                </button>
                <button id="pause-btn" class="pomodoro-btn pomodoro-btn-secondary" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="6" y="4" width="4" height="16"></rect>
                        <rect x="14" y="4" width="4" height="16"></rect>
                    </svg>
                    Pause
                </button>
                <button id="skip-btn" class="pomodoro-btn pomodoro-btn-ghost">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="5 4 15 12 5 20 5 4"></polygon>
                        <line x1="19" y1="5" x2="19" y2="19"></line>
                    </svg>
                    Skip
                </button>
                <button id="reset-btn" class="pomodoro-btn pomodoro-btn-ghost">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                    Reset
                </button>
            </div>
        </div>
        
        <div class="pomodoro-quick-stats">
            <div class="quick-stat-card">
                <div class="quick-stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-label">Today</div>
                    <div class="quick-stat-value" id="today-sessions">0</div>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon" style="background: #fef3c7; color: #b45309;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-label">This Week</div>
                    <div class="quick-stat-value" id="week-sessions">0</div>
                </div>
            </div>
            
            <div class="quick-stat-card">
                <div class="quick-stat-icon" style="background: #d1fae5; color: #065f46;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="quick-stat-info">
                    <div class="quick-stat-label">Total Time</div>
                    <div class="quick-stat-value" id="total-time">0h 0m</div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .pomodoro-timer-wrapper {
        max-width: 800px;
        margin: 0 auto;
    }
    
    .pomodoro-controls-top {
        margin-bottom: 30px;
        display: flex;
        justify-content: center;
    }
    
    .pomodoro-timer-container {
        background: #ffffff;
        border-radius: 16px;
        padding: 40px 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .pomodoro-session-indicator {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .pomodoro-session-dots {
        display: inline-flex;
        gap: 10px;
    }
    
    .session-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #e5e7eb;
        transition: all 0.3s;
    }
    
    .session-dot.completed {
        background: #10b981;
        box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
    }
    
    .session-dot.active {
        background: #3b82f6;
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.5);
        transform: scale(1.2);
    }
    
    .pomodoro-timer-display {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .timer-mode {
        font-size: 18px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 20px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .timer-circle {
        position: relative;
        display: inline-block;
    }
    
    .timer-ring {
        transform: rotate(-90deg);
    }
    
    .timer-ring-bg {
        fill: none;
        stroke: #f3f4f6;
        stroke-width: 8;
    }
    
    .timer-ring-progress {
        fill: none;
        stroke: #3b82f6;
        stroke-width: 8;
        stroke-linecap: round;
        stroke-dasharray: 880;
        stroke-dashoffset: 0;
        transition: stroke-dashoffset 1s linear, stroke 0.3s;
    }
    
    .timer-ring-progress.work {
        stroke: #3b82f6;
    }
    
    .timer-ring-progress.break {
        stroke: #10b981;
    }
    
    .timer-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
    }
    
    .timer-time {
        font-size: 56px;
        font-weight: 700;
        color: #111827;
        font-variant-numeric: tabular-nums;
        letter-spacing: -2px;
    }
    
    .timer-status {
        font-size: 14px;
        color: #6b7280;
        margin-top: 8px;
        font-weight: 500;
    }
    
    .pomodoro-task-input {
        margin-bottom: 30px;
    }
    
    #task-input {
        text-align: center;
        font-size: 16px;
        padding: 12px 20px;
    }
    
    #task-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .pomodoro-controls {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .pomodoro-btn {
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 15px;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        font-family: inherit;
    }
    
    .pomodoro-btn-primary {
        background: #3b82f6;
        color: white;
        box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
    }
    
    .pomodoro-btn-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }
    
    .pomodoro-btn-primary:active {
        transform: translateY(0);
    }
    
    .pomodoro-btn-secondary {
        background: #f59e0b;
        color: white;
        box-shadow: 0 4px 14px rgba(245, 158, 11, 0.3);
    }
    
    .pomodoro-btn-secondary:hover {
        background: #d97706;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4);
    }
    
    .pomodoro-btn-ghost {
        background: #f3f4f6;
        color: #6b7280;
    }
    
    .pomodoro-btn-ghost:hover {
        background: #e5e7eb;
        color: #374151;
    }
    
    .pomodoro-quick-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }
    
    .quick-stat-card {
        background: #ffffff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .quick-stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .quick-stat-info {
        flex: 1;
    }
    
    .quick-stat-label {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 4px;
        font-weight: 500;
    }
    
    .quick-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    
    @media (max-width: 768px) {
        .timer-time {
            font-size: 48px;
        }
        
        .timer-ring {
            width: 250px !important;
            height: 250px !important;
        }
        
        .pomodoro-controls {
            flex-direction: column;
        }
        
        .pomodoro-btn {
            width: 100%;
            justify-content: center;
        }
    }
    </style>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        let timerInterval = null;
        let timeLeft = 1500;
        let totalTime = 1500;
        let isRunning = false;
        let currentMode = 'work';
        let sessionCount = 0;
        let completedSessions = 0;
        let currentPreset = {
            work: <?php echo $preset->work_duration; ?>,
            short: <?php echo $preset->short_break_duration; ?>,
            long: <?php echo $preset->long_break_duration; ?>,
            sessions: <?php echo $preset->sessions_before_long_break; ?>
        };
        
        const timerDisplay = document.getElementById('timer-display');
        const timerStatus = document.getElementById('timer-status');
        const timerMode = document.getElementById('timer-mode');
        const startBtn = document.getElementById('start-btn');
        const pauseBtn = document.getElementById('pause-btn');
        const skipBtn = document.getElementById('skip-btn');
        const resetBtn = document.getElementById('reset-btn');
        const taskInput = document.getElementById('task-input');
        const progressRing = document.getElementById('timer-progress-ring');
        const sessionDots = document.getElementById('session-dots');
        const presetSelector = document.getElementById('preset-selector');
        
        // Load stats
        loadStats();
        
        // Initialize session dots
        updateSessionDots();
        
        // Preset change handler
        presetSelector.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            currentPreset = {
                work: parseInt(option.dataset.work),
                short: parseInt(option.dataset.short),
                long: parseInt(option.dataset.long),
                sessions: parseInt(option.dataset.sessions)
            };
            
            if (!isRunning) {
                resetTimer();
            }
        });
        
        // Start button
        startBtn.addEventListener('click', function() {
            if (!isRunning) {
                startTimer();
            }
        });
        
        // Pause button
        pauseBtn.addEventListener('click', function() {
            if (isRunning) {
                pauseTimer();
            }
        });
        
        // Skip button
        skipBtn.addEventListener('click', function() {
            if (confirm('Skip this session?')) {
                skipSession();
            }
        });
        
        // Reset button
        resetBtn.addEventListener('click', function() {
            if (confirm('Reset the timer?')) {
                resetTimer(); }
    });
    
    function startTimer() {
        isRunning = true;
        startBtn.style.display = 'none';
        pauseBtn.style.display = 'inline-flex';
        timerStatus.textContent = currentMode === 'work' ? 'Stay focused!' : 'Take a break!';
        
        timerInterval = setInterval(() => {
            timeLeft--;
            updateDisplay();
            updateProgressRing();
            
            if (timeLeft <= 0) {
                completeSession();
            }
        }, 1000);
    }
    
    function pauseTimer() {
        isRunning = false;
        clearInterval(timerInterval);
        startBtn.style.display = 'inline-flex';
        pauseBtn.style.display = 'none';
        timerStatus.textContent = 'Paused';
    }
    
    function resetTimer() {
        pauseTimer();
        sessionCount = 0;
        completedSessions = 0;
        currentMode = 'work';
        timeLeft = currentPreset.work;
        totalTime = currentPreset.work;
        updateDisplay();
        updateProgressRing();
        updateSessionDots();
        timerMode.textContent = 'Work Session';
        timerStatus.textContent = 'Ready to focus';
    }
    
    function skipSession() {
        const taskName = taskInput.value.trim() || null;
        
        if (currentMode === 'work') {
            saveSession(totalTime, totalTime - timeLeft, taskName, 'skipped');
        }
        
        nextSession();
    }
    
    function completeSession() {
        pauseTimer();
        playSound();
        
        const taskName = taskInput.value.trim() || null;
        
        if (currentMode === 'work') {
            saveSession(totalTime, totalTime, taskName, 'completed');
            completedSessions++;
            sessionCount++;
        }
        
        // Show notification
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Pomodoro Complete!', {
                body: currentMode === 'work' ? 'Great work! Time for a break.' : 'Break over! Ready to focus?',
                icon: '<?php echo BNTM_POMODORO_URL; ?>icon.png'
            });
        }
        
        setTimeout(() => {
            nextSession();
        }, 2000);
    }
    
    function nextSession() {
        if (currentMode === 'work') {
            if (sessionCount >= currentPreset.sessions) {
                currentMode = 'long_break';
                timeLeft = currentPreset.long;
                totalTime = currentPreset.long;
                timerMode.textContent = 'Long Break';
                progressRing.classList.add('break');
                progressRing.classList.remove('work');
                sessionCount = 0;
            } else {
                currentMode = 'short_break';
                timeLeft = currentPreset.short;
                totalTime = currentPreset.short;
                timerMode.textContent = 'Short Break';
                progressRing.classList.add('break');
                progressRing.classList.remove('work');
            }
        } else {
            currentMode = 'work';
            timeLeft = currentPreset.work;
            totalTime = currentPreset.work;
            timerMode.textContent = 'Work Session';
            progressRing.classList.add('work');
            progressRing.classList.remove('break');
            taskInput.value = '';
        }
        
        updateDisplay();
        updateProgressRing();
        updateSessionDots();
        timerStatus.textContent = currentMode === 'work' ? 'Ready to focus' : 'Time to relax';
    }
    
    function updateDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    
    function updateProgressRing() {
        const circumference = 2 * Math.PI * 140;
        const progress = (timeLeft / totalTime) * circumference;
        progressRing.style.strokeDashoffset = circumference - progress;
    }
    
    function updateSessionDots() {
        sessionDots.innerHTML = '';
        for (let i = 0; i < currentPreset.sessions; i++) {
            const dot = document.createElement('div');
            dot.className = 'session-dot';
            if (i < completedSessions) {
                dot.classList.add('completed');
            } else if (i === completedSessions && currentMode === 'work') {
                dot.classList.add('active');
            }
            sessionDots.appendChild(dot);
        }
    }
    
    function saveSession(planned, actual, task, status) {
        const formData = new FormData();
        formData.append('action', 'pomodoro_save_session');
        formData.append('session_type', currentMode);
        formData.append('planned_duration', planned);
        formData.append('actual_duration', actual);
        formData.append('task_name', task || '');
        formData.append('status', status);
        formData.append('nonce', nonce);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                loadStats();
            }
        });
    }
    
    function loadStats() {
        const formData = new FormData();
        formData.append('action', 'pomodoro_get_stats');
        formData.append('nonce', nonce);
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                document.getElementById('today-sessions').textContent = json.data.today;
                document.getElementById('week-sessions').textContent = json.data.week;
                document.getElementById('total-time').textContent = json.data.total_time;
            }
        });
    }
    
    function playSound() {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZTA0PVqzn77BZFgpDmuL0xHIpBSuBzvLZiTYIG2m98OSkUhELTKXh8bllHAU2jdXyzn0vBSR3x/DdkEAKFF606+2oVRQKRp/g8r5sIQUxh9Hz04IzBh5uwO/jmUwND1as5+6wWRUKQ5vi9MRyKQUrge==');
        audio.play().catch(e => console.log('Sound play failed:', e));
    }
    
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    // Initialize display
    updateDisplay();
    updateProgressRing();
})();
</script>
<?php
return ob_get_clean();
}
function pt_history_tab($business_id) {
global $wpdb;
$sessions_table = $wpdb->prefix . 'pt_sessions';
// Get filter
$filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';

$where = "business_id = %d";
$params = [$business_id];

if ($filter === 'today') {
    $where .= " AND DATE(completed_at) = CURDATE()";
} elseif ($filter === 'week') {
    $where .= " AND YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'month') {
    $where .= " AND YEAR(completed_at) = YEAR(CURDATE()) AND MONTH(completed_at) = MONTH(CURDATE())";
}

$sessions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$sessions_table} WHERE {$where} ORDER BY completed_at DESC LIMIT 100",
    ...$params
));

$nonce = wp_create_nonce('pt_history_nonce');

ob_start();
?>
<div class="bntm-form-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">Session History</h3>
        <select id="history-filter" class="bntm-input" style="max-width: 200px;">
            <option value="all" <?php selected($filter, 'all'); ?>>All Time</option>
            <option value="today" <?php selected($filter, 'today'); ?>>Today</option>
            <option value="week" <?php selected($filter, 'week'); ?>>This Week</option>
            <option value="month" <?php selected($filter, 'month'); ?>>This Month</option>
        </select>
    </div>
    
    <?php if (empty($sessions)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #6b7280;">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 20px; opacity: 0.3;">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <p style="font-size: 16px; margin: 0;">No sessions recorded yet</p>
            <p style="font-size: 14px; margin-top: 8px;">Start a timer to track your productivity!</p>
        </div>
    <?php else: ?>
        <div class="pomodoro-sessions-list">
            <?php foreach ($sessions as $session): ?>
                <div class="session-item">
                    <div class="session-type-badge session-type-<?php echo $session->session_type; ?>">
                        <?php 
                        $type_labels = [
                            'work' => 'Work',
                            'short_break' => 'Short Break',
                            'long_break' => 'Long Break'
                        ];
                        echo $type_labels[$session->session_type];
                        ?>
                    </div>
                    
                    <div class="session-content">
                        <?php if ($session->task_name): ?>
                            <div class="session-task"><?php echo esc_html($session->task_name); ?></div>
                        <?php else: ?>
                            <div class="session-task" style="color: #9ca3af;">No task specified</div>
                        <?php endif; ?>
                        
                        <div class="session-meta">
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <?php echo floor($session->actual_duration / 60); ?> min
                            </span>
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <?php echo date('M d, Y g:i A', strtotime($session->completed_at)); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="session-actions">
                        <span class="session-status session-status-<?php echo $session->status; ?>">
                            <?php echo ucfirst($session->status); ?>
                        </span>
                        <button class="bntm-btn-icon delete-session-btn" data-id="<?php echo $session->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.pomodoro-sessions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.session-item {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s;
}

.session-item:hover {
    border-color: #d1d5db;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.session-type-badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.session-type-work {
    background: #dbeafe;
    color: #1e40af;
}

.session-type-short_break {
    background: #d1fae5;
    color: #065f46;
}

.session-type-long_break {
    background: #fef3c7;
    color: #b45309;
}

.session-content {
    flex: 1;
}

.session-task {
    font-weight: 500;
    color: #111827;
    margin-bottom: 6px;
}

.session-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: #6b7280;
}

.session-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.session-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.session-status {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
}

.session-status-completed {
    background: #d1fae5;
    color: #065f46;
}

.session-status-interrupted {
    background: #fee2e2;
    color: #991b1b;
}

.session-status-skipped {
    background: #f3f4f6;
    color: #6b7280;
}

.bntm-btn-icon {
    background: none;
    border: none;
    padding: 6px;
    cursor: pointer;
    color: #9ca3af;
    border-radius: 6px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.bntm-btn-icon:hover {
    background: #fee2e2;
    color: #dc2626;
}
</style>

<script>
(function() {
    // Filter change
    document.getElementById('history-filter').addEventListener('change', function() {
        window.location.href = '?tab=history&filter=' + this.value;
    });
    
    // Delete session
    document.querySelectorAll('.delete-session-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this session?')) return;
            
            const formData = new FormData();
            formData.append('action', 'pomodoro_delete_session');
            formData.append('session_id', this.dataset.id);
            formData.append('nonce', this.dataset.nonce);
            
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
})();
</script>
<?php
return ob_get_clean();
}
function pt_presets_tab($business_id) {
global $wpdb;
$presets_table = $wpdb->prefix . 'pt_presets';
$presets = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$presets_table} WHERE business_id = %d ORDER BY is_default DESC, preset_name ASC",
    $business_id
));

$nonce = wp_create_nonce('pt_preset_nonce');

ob_start();
?>
<div class="bntm-form-section">
    <h3>Timer Presets</h3>
    <p>Create and manage custom timer configurations</p>
    
    <div class="presets-grid">
        <?php foreach ($presets as $preset): ?>
            <div class="preset-card">
                <div class="preset-header">
                    <h4><?php echo esc_html($preset->preset_name); ?></h4>
                    <?php if ($preset->is_default): ?>
                        <span class="preset-badge preset-badge-default">Default</span>
                    <?php endif; ?>
                </div>
                
                <div class="preset-times">
                    <div class="preset-time-item">
                        <span class="preset-time-label">Work</span>
                        <span class="preset-time-value"><?php echo floor($preset->work_duration / 60); ?> min</span>
                    </div>
                    <div class="preset-time-item">
                        <span class="preset-time-label">Short Break</span>
                        <span class="preset-time-value"><?php echo floor($preset->short_break_duration / 60); ?> min</span>
                    </div>
                    <div class="preset-time-item">
                        <span class="preset-time-label">Long Break</span>
                        <span class="preset-time-value"><?php echo floor($preset->long_break_duration / 60); ?> min</span>
                    </div>
                    <div class="preset-time-item">
                        <span class="preset-time-label">Sessions</span>
                        <span class="preset-time-value"><?php echo $preset->sessions_before_long_break; ?></span>
                    </div>
                </div>
                
                <div class="preset-actions">
                    <?php if (!$preset->is_default): ?>
                        <button class="bntm-btn-small bntm-btn-secondary set-default-btn" 
                                data-id="<?php echo $preset->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            Set as Default
                        </button>
                        <button class="bntm-btn-small bntm-btn-danger delete-preset-btn" 
                                data-id="<?php echo $preset->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <button id="add-preset-btn" class="bntm-btn-primary" style="margin-top: 20px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        Add New Preset
    </button>
</div>

<!-- Add/Edit Preset Modal -->
<div id="preset-modal" class="pomodoro-modal">
    <div class="pomodoro-modal-content">
        <div class="pomodoro-modal-header">
            <h3 id="modal-title">Add New Preset</h3>
            <button class="pomodoro-modal-close">&times;</button>
        </div>
        <div class="pomodoro-modal-body">
            <form id="preset-form" class="bntm-form">
                <input type="hidden" id="preset-id" value="">
                
                <div class="bntm-form-group">
                    <label>Preset Name *</label>
                    <input type="text" id="preset-name" class="bntm-input" required placeholder="e.g., Deep Work Session">
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Work Duration (minutes) *</label>
                        <input type="number" id="work-duration" class="bntm-input" required min="1" max="120" value="25">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Short Break (minutes) *</label>
                        <input type="number" id="short-break" class="bntm-input" required min="1" max="30" value="5">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Long Break (minutes) *</label>
                        <input type="number" id="long-break" class="bntm-input" required min="1" max="60" value="15">
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Sessions Before Long Break *</label>
                        <input type="number" id="sessions-count" class="bntm-input" required min="1" max="10" value="4">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Save Preset</button>
                    <button type="button" class="bntm-btn-secondary pomodoro-modal-close">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.presets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.preset-card {
    background: #ffffff;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
}

.preset-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
}

.preset-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.preset-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}

.preset-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.preset-badge-default {
    background: #dbeafe;
    color: #1e40af;
}

.preset-times {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.preset-time-item {
    background: #f9fafb;
    padding: 12px;
    border-radius: 8px;
}

.preset-time-label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
}

.preset-time-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #111827;
}

.preset-actions {
    display: flex;
    gap: 8px;
}

.bntm-btn-small {
    padding: 8px 14px;
    font-size: 13px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.bntm-btn-secondary {
    background: #f3f4f6;
    color: #374151;
}

.bntm-btn-secondary:hover {
    background: #e5e7eb;
}

.bntm-btn-danger {
    background: #fee2e2;
    color: #dc2626;
}

.bntm-btn-danger:hover {
    background: #fecaca;
}

.bntm-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 768px) {
    .bntm-form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
(function() {
    const modal = document.getElementById('preset-modal');
    const form = document.getElementById('preset-form');
    const addBtn = document.getElementById('add-preset-btn');
    const closeButtons = document.querySelectorAll('.pomodoro-modal-close');
    
    // Open modal
    addBtn.addEventListener('click', function() {
        document.getElementById('modal-title').textContent = 'Add New Preset';
        form.reset();
        document.getElementById('preset-id').value = '';
        modal.style.display = 'block';
    });
    
    // Close modal
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    });
    
    // Close on outside click
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Submit form
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'pomodoro_save_preset');
        formData.append('preset_id', document.getElementById('preset-id').value);
        formData.append('preset_name', document.getElementById('preset-name').value);
        formData.append('work_duration', parseInt(document.getElementById('work-duration').value) * 60);
        formData.append('short_break', parseInt(document.getElementById('short-break').value) * 60);
        formData.append('long_break', parseInt(document.getElementById('long-break').value) * 60);
        formData.append('sessions_count', document.getElementById('sessions-count').value);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
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
    
    // Set default
    document.querySelectorAll('.set-default-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'pomodoro_set_default_preset');
            formData.append('preset_id', this.dataset.id);
            formData.append('nonce', this.dataset.nonce);
            
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
    
    // Delete preset
    document.querySelectorAll('.delete-preset-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this preset?')) return;
            
            const formData = new FormData();
            formData.append('action', 'pomodoro_delete_preset');
            formData.append('preset_id', this.dataset.id);
            formData.append('nonce', this.dataset.nonce);
            
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
})();
</script>
<?php
return ob_get_clean();
}

// ============================================================================
// STATS TAB
// ============================================================================

function pt_stats_tab($business_id) {
    global $wpdb;
    $sessions_table = $wpdb->prefix . 'pt_sessions';
    
    // Get stats data
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) as today_sessions,
            SUM(CASE WHEN YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) as week_sessions,
            SUM(CASE WHEN session_type = 'work' THEN actual_duration ELSE 0 END) as total_work_time,
            AVG(CASE WHEN session_type = 'work' THEN actual_duration ELSE NULL END) as avg_work_time,
            SUM(CASE WHEN session_type = 'work' AND status = 'completed' THEN 1 ELSE 0 END) as completed_work_sessions,
            SUM(CASE WHEN session_type = 'work' AND status = 'interrupted' THEN 1 ELSE 0 END) as interrupted_sessions,
            SUM(CASE WHEN session_type = 'work' AND status = 'skipped' THEN 1 ELSE 0 END) as skipped_sessions
        FROM {$sessions_table} WHERE business_id = %d",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Statistics & Analytics</h3>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #dbeafe; color: #1e40af;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Sessions</div>
                    <div class="stat-value"><?php echo $stats->total_sessions ?? 0; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #b45309;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Completed Sessions</div>
                    <div class="stat-value"><?php echo $stats->completed_work_sessions ?? 0; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Work Time</div>
                    <div class="stat-value"><?php 
                        $hours = intval(($stats->total_work_time ?? 0) / 3600);
                        $minutes = intval((($stats->total_work_time ?? 0) % 3600) / 60);
                        echo "{$hours}h {$minutes}m";
                    ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fee2e2; color: #991b1b;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Interrupted Sessions</div>
                    <div class="stat-value"><?php echo $stats->interrupted_sessions ?? 0; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3f4f6; color: #6b7280;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 8h12M6 16h12"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Skipped Sessions</div>
                    <div class="stat-value"><?php echo $stats->skipped_sessions ?? 0; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #e9d5ff; color: #6b21a8;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Avg Session Time</div>
                    <div class="stat-value"><?php echo round(($stats->avg_work_time ?? 0) / 60, 1); ?> min</div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s;
    }
    
    .stat-card:hover {
        border-color: #d1d5db;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .stat-content {
        flex: 1;
    }
    
    .stat-label {
        font-size: 13px;
        color: #6b7280;
        margin-bottom: 6px;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    </style>
    <?php
    return ob_get_clean();
}

// ============================================================================
// SESSIONS SHORTCODE
// ============================================================================

function bntm_shortcode_pt_sessions() {
    return bntm_shortcode_pt_timer();
}

// ============================================================================
// AJAX HANDLERS
// ============================================================================

function bntm_ajax_pomodoro_save_session() {
    check_ajax_referer('pomodoro_timer_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $sessions_table = $wpdb->prefix . 'pomodoro_sessions';
    
    $wpdb->insert($sessions_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'session_type' => sanitize_text_field($_POST['session_type']),
        'planned_duration' => intval($_POST['planned_duration']),
        'actual_duration' => intval($_POST['actual_duration']),
        'task_name' => sanitize_text_field($_POST['task_name']) ?: null,
        'status' => sanitize_text_field($_POST['status']),
        'completed_at' => current_time('mysql')
    ], ['%s','%d','%s','%d','%d','%s','%s','%s']);
    
    wp_send_json_success(['message' => 'Session saved']);
}

function bntm_ajax_pomodoro_get_stats() {
    check_ajax_referer('pomodoro_timer_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $sessions_table = $wpdb->prefix . 'pomodoro_sessions';
    
    $today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$sessions_table} WHERE business_id = %d AND session_type = 'work' AND DATE(completed_at) = CURDATE()",
        $business_id
    ));
    
    $week = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$sessions_table} WHERE business_id = %d AND session_type = 'work' AND YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)",
        $business_id
    ));
    
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(actual_duration) FROM {$sessions_table} WHERE business_id = %d AND session_type = 'work'",
        $business_id
    ));
    
    $hours = intval(($total ?? 0) / 3600);
    $minutes = intval((($total ?? 0) % 3600) / 60);
    
    wp_send_json_success([
        'today' => $today ?? 0,
        'week' => $week ?? 0,
        'total_time' => "{$hours}h {$minutes}m"
    ]);
}

function bntm_ajax_pomodoro_save_preset() {
    check_ajax_referer('pomodoro_preset_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $presets_table = $wpdb->prefix . 'pomodoro_presets';
    
    $preset_id = intval($_POST['preset_id'] ?? 0);
    
    $data = [
        'preset_name' => sanitize_text_field($_POST['preset_name']),
        'work_duration' => intval($_POST['work_duration']),
        'short_break_duration' => intval($_POST['short_break']),
        'long_break_duration' => intval($_POST['long_break']),
        'sessions_before_long_break' => intval($_POST['sessions_count']),
        'updated_at' => current_time('mysql')
    ];
    
    if ($preset_id) {
        $wpdb->update($presets_table, $data, ['id' => $preset_id], ['%s','%d','%d','%d','%d','%s'], ['%d']);
    } else {
        $data['rand_id'] = bntm_rand_id();
        $data['business_id'] = $business_id;
        $data['is_default'] = 0;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($presets_table, $data, ['%s','%d','%s','%d','%d','%d','%d','%s','%s']);
    }
    
    wp_send_json_success(['message' => 'Preset saved']);
}

function bntm_ajax_pomodoro_delete_preset() {
    check_ajax_referer('pomodoro_preset_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $presets_table = $wpdb->prefix . 'pomodoro_presets';
    
    $preset_id = intval($_POST['preset_id']);
    
    $wpdb->delete($presets_table, ['id' => $preset_id, 'business_id' => $business_id], ['%d', '%d']);
    
    wp_send_json_success(['message' => 'Preset deleted']);
}

function bntm_ajax_pomodoro_set_default_preset() {
    check_ajax_referer('pomodoro_preset_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $presets_table = $wpdb->prefix . 'pomodoro_presets';
    
    $preset_id = intval($_POST['preset_id']);
    
    $wpdb->update($presets_table, ['is_default' => 0], ['business_id' => $business_id], ['%d'], ['%d']);
    $wpdb->update($presets_table, ['is_default' => 1], ['id' => $preset_id, 'business_id' => $business_id], ['%d'], ['%d', '%d']);
    
    wp_send_json_success(['message' => 'Default preset updated']);
}

function bntm_ajax_pomodoro_delete_session() {
    check_ajax_referer('pomodoro_history_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $sessions_table = $wpdb->prefix . 'pomodoro_sessions';
    
    $session_id = intval($_POST['session_id']);
    
    $wpdb->delete($sessions_table, ['id' => $session_id, 'business_id' => $business_id], ['%d', '%d']);
    
    wp_send_json_success(['message' => 'Session deleted']);
}

function bntm_ajax_pomodoro_get_preset() {
    check_ajax_referer('pomodoro_preset_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $presets_table = $wpdb->prefix . 'pomodoro_presets';
    
    $preset_id = intval($_POST['preset_id']);
    
    $preset = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$presets_table} WHERE id = %d AND business_id = %d",
        $preset_id,
        $business_id
    ));
    
    if ($preset) {
        wp_send_json_success($preset);
    } else {
        wp_send_json_error(['message' => 'Preset not found']);
    }
}

// ============================================================================
// HELPER FUNCTION
// ============================================================================


