<?php
/**
 * Module Name: Basketball Teams & Player Statistics
 * Module Slug: spm
 * Description: Manages basketball teams, players, games, and player performance statistics including automatic leaderboard calculations.
 * Version: 1.0.0
 * Author: Development Team
 * Icon: basketball
 */

if (!defined('ABSPATH')) exit;

define('BNTM_SPM_PATH', dirname(__FILE__) . '/');
define('BNTM_SPM_URL', plugin_dir_url(__FILE__));

// ============================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================

function bntm_spm_get_pages() {
    return [
        'Basketball Dashboard' => '[spm_dashboard]',
        'Basketball Public'    => '[spm_public]',
    ];
}

function bntm_spm_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'spm_teams' => "CREATE TABLE {$prefix}spm_teams (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            team_name VARCHAR(255) NOT NULL,
            city VARCHAR(255) NOT NULL DEFAULT '',
            coach VARCHAR(255) NOT NULL DEFAULT '',
            logo VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",

        'spm_players' => "CREATE TABLE {$prefix}spm_players (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            team_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            player_name VARCHAR(255) NOT NULL,
            jersey_number INT NOT NULL DEFAULT 0,
            position VARCHAR(50) NOT NULL DEFAULT '',
            height VARCHAR(20) NOT NULL DEFAULT '',
            weight INT NOT NULL DEFAULT 0,
            photo VARCHAR(500) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_team (team_id)
        ) {$charset};",

        'spm_games' => "CREATE TABLE {$prefix}spm_games (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            game_date DATE NOT NULL,
            team_a_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            team_b_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            team_a_score INT NOT NULL DEFAULT 0,
            team_b_score INT NOT NULL DEFAULT 0,
            season VARCHAR(100) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",

        'spm_player_stats' => "CREATE TABLE {$prefix}spm_player_stats (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            game_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            player_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            minutes INT NOT NULL DEFAULT 0,
            points INT NOT NULL DEFAULT 0,
            rebounds INT NOT NULL DEFAULT 0,
            assists INT NOT NULL DEFAULT 0,
            steals INT NOT NULL DEFAULT 0,
            blocks INT NOT NULL DEFAULT 0,
            turnovers INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_game (game_id),
            INDEX idx_player (player_id)
        ) {$charset};",
    ];
}

function bntm_spm_get_shortcodes() {
    return [
        'spm_dashboard' => 'bntm_shortcode_spm_dashboard',
        'spm_public'    => 'bntm_shortcode_spm_public',
    ];
}

function bntm_spm_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_spm_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// ============================================================
// AJAX ACTION HOOKS
// ============================================================

// Teams
add_action('wp_ajax_spm_save_team',    'bntm_ajax_spm_save_team');
add_action('wp_ajax_spm_delete_team',  'bntm_ajax_spm_delete_team');
add_action('wp_ajax_spm_get_team',     'bntm_ajax_spm_get_team');

// Players
add_action('wp_ajax_spm_save_player',   'bntm_ajax_spm_save_player');
add_action('wp_ajax_spm_delete_player', 'bntm_ajax_spm_delete_player');
add_action('wp_ajax_spm_get_player',    'bntm_ajax_spm_get_player');

// Games
add_action('wp_ajax_spm_save_game',   'bntm_ajax_spm_save_game');
add_action('wp_ajax_spm_delete_game', 'bntm_ajax_spm_delete_game');
add_action('wp_ajax_spm_get_game',    'bntm_ajax_spm_get_game');

// Stats
add_action('wp_ajax_spm_save_stat',   'bntm_ajax_spm_save_stat');
add_action('wp_ajax_spm_delete_stat', 'bntm_ajax_spm_delete_stat');
add_action('wp_ajax_spm_get_game_players', 'bntm_ajax_spm_get_game_players');

// Public
add_action('wp_ajax_nopriv_spm_public_data', 'bntm_ajax_spm_public_data');
add_action('wp_ajax_spm_public_data',        'bntm_ajax_spm_public_data');

// ============================================================
// MAIN DASHBOARD SHORTCODE
// ============================================================

function bntm_shortcode_spm_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Basketball Dashboard.</div>';
    }

    $business_id = get_current_user_id();
    $active_tab  = isset($_GET['spm_tab']) ? sanitize_text_field($_GET['spm_tab']) : 'overview';

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>

    <div class="bntm-spm-container">

        <!-- Tab Navigation -->
        <div class="bntm-tabs">
            <button class="bntm-tab <?php echo $active_tab === 'overview'  ? 'active' : ''; ?>" data-tab="overview">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Overview
            </button>
            <button class="bntm-tab <?php echo $active_tab === 'teams'    ? 'active' : ''; ?>" data-tab="teams">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Teams
            </button>
            <button class="bntm-tab <?php echo $active_tab === 'players'  ? 'active' : ''; ?>" data-tab="players">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Players
            </button>
            <button class="bntm-tab <?php echo $active_tab === 'games'    ? 'active' : ''; ?>" data-tab="games">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Games
            </button>
            <button class="bntm-tab <?php echo $active_tab === 'stats'    ? 'active' : ''; ?>" data-tab="stats">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Player Stats
            </button>
            <button class="bntm-tab <?php echo $active_tab === 'rankings' ? 'active' : ''; ?>" data-tab="rankings">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Rankings
            </button>
        </div>

        <!-- Tab Content Panels -->
        <div class="bntm-tab-content">
            <div class="bntm-tab-panel <?php echo $active_tab === 'overview'  ? 'active' : ''; ?>" id="panel-overview">
                <?php echo spm_overview_tab($business_id); ?>
            </div>
            <div class="bntm-tab-panel <?php echo $active_tab === 'teams'    ? 'active' : ''; ?>" id="panel-teams">
                <?php echo spm_teams_tab($business_id); ?>
            </div>
            <div class="bntm-tab-panel <?php echo $active_tab === 'players'  ? 'active' : ''; ?>" id="panel-players">
                <?php echo spm_players_tab($business_id); ?>
            </div>
            <div class="bntm-tab-panel <?php echo $active_tab === 'games'    ? 'active' : ''; ?>" id="panel-games">
                <?php echo spm_games_tab($business_id); ?>
            </div>
            <div class="bntm-tab-panel <?php echo $active_tab === 'stats'    ? 'active' : ''; ?>" id="panel-stats">
                <?php echo spm_stats_tab($business_id); ?>
            </div>
            <div class="bntm-tab-panel <?php echo $active_tab === 'rankings' ? 'active' : ''; ?>" id="panel-rankings">
                <?php echo spm_rankings_tab($business_id); ?>
            </div>
        </div>
    </div>

    <!-- ===================== GLOBAL MODAL ===================== -->
    <div id="spm-modal-overlay" class="spm-modal-overlay" style="display:none;">
        <div class="spm-modal" id="spm-modal">
            <div class="spm-modal-header">
                <h3 id="spm-modal-title">Modal</h3>
                <button class="spm-modal-close" id="spm-modal-close">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="spm-modal-body" id="spm-modal-body"></div>
        </div>
    </div>

    <!-- ===================== GLOBAL STYLES ===================== -->
    <style>
    .bntm-spm-container { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #111827; }

    /* Tabs */
    .bntm-spm-container .bntm-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin-bottom: 24px; flex-wrap: wrap; }
    .bntm-spm-container .bntm-tab  { display: flex; align-items: center; gap: 6px; padding: 10px 16px; background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; font-size: 14px; font-weight: 500; color: #6b7280; transition: all .2s; border-radius: 4px 4px 0 0; }
    .bntm-spm-container .bntm-tab:hover  { color: #374151; background: #f9fafb; }
    .bntm-spm-container .bntm-tab.active { color: #2563eb; border-bottom-color: #2563eb; background: none; }

    /* Panel */
    .bntm-tab-panel { display: none; }
    .bntm-tab-panel.active { display: block; }

    /* Stat cards */
    .bntm-stats-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .bntm-stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .stat-icon { width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .stat-content h3 { margin: 0 0 2px; font-size: 13px; color: #6b7280; font-weight: 500; }
    .stat-number { font-size: 26px; font-weight: 700; color: #111827; margin: 0; }
    .stat-label  { font-size: 12px; color: #9ca3af; }

    /* Section */
    .bntm-form-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
    .bntm-form-section h3 { margin: 0 0 4px; font-size: 16px; font-weight: 600; color: #111827; }
    .bntm-form-section > p { color: #6b7280; margin: 0 0 20px; font-size: 14px; }

    /* Form */
    .spm-form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .spm-form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .bntm-form-group  { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
    .bntm-form-group label { font-size: 13px; font-weight: 500; color: #374151; }
    .bntm-form-group input,
    .bntm-form-group select,
    .bntm-form-group textarea { padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; color: #111827; background: #fff; transition: border-color .2s, box-shadow .2s; width: 100%; box-sizing: border-box; }
    .bntm-form-group input:focus,
    .bntm-form-group select:focus,
    .bntm-form-group textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

    /* Buttons */
    .bntm-btn-primary   { padding: 9px 18px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background .2s; }
    .bntm-btn-primary:hover { background: #1d4ed8; }
    .bntm-btn-primary:disabled { opacity: .6; cursor: not-allowed; }
    .bntm-btn-secondary { padding: 9px 18px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background .2s; }
    .bntm-btn-secondary:hover { background: #e5e7eb; }
    .bntm-btn-small     { padding: 5px 12px; font-size: 12px; border-radius: 6px; cursor: pointer; border: none; font-weight: 500; }
    .bntm-btn-edit      { background: #eff6ff; color: #2563eb; }
    .bntm-btn-edit:hover { background: #dbeafe; }
    .bntm-btn-danger    { background: #fef2f2; color: #dc2626; }
    .bntm-btn-danger:hover { background: #fee2e2; }

    /* Table */
    .bntm-table-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid #e5e7eb; }
    .bntm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .bntm-table thead tr { background: #f9fafb; }
    .bntm-table th { padding: 11px 14px; text-align: left; font-weight: 600; color: #374151; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e5e7eb; }
    .bntm-table td { padding: 12px 14px; border-bottom: 1px solid #f3f4f6; color: #374151; vertical-align: middle; }
    .bntm-table tbody tr:last-child td { border-bottom: none; }
    .bntm-table tbody tr:hover td { background: #f9fafb; }

    /* Badge */
    .bntm-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .bntm-badge-blue   { background: #eff6ff; color: #2563eb; }
    .bntm-badge-green  { background: #f0fdf4; color: #16a34a; }
    .bntm-badge-gray   { background: #f3f4f6; color: #6b7280; }
    .bntm-badge-orange { background: #fff7ed; color: #ea580c; }

    /* Notices */
    .bntm-notice         { padding: 10px 16px; border-radius: 8px; font-size: 14px; margin: 10px 0; }
    .bntm-notice-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .bntm-notice-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    /* Section header row */
    .bntm-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
    .bntm-section-header h3 { margin: 0; font-size: 16px; font-weight: 600; }
    .bntm-section-header p { margin: 4px 0 0; font-size: 13px; color: #6b7280; }

    /* Search bar */
    .spm-search-bar { position: relative; }
    .spm-search-bar input { padding-left: 36px; }
    .spm-search-bar svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; }

    /* Avatar */
    .spm-avatar { width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #7c3aed); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 13px; flex-shrink: 0; }

    /* Rankings */
    .spm-rank-badge { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0; }
    .rank-1 { background: #fef3c7; color: #b45309; }
    .rank-2 { background: #f3f4f6; color: #4b5563; }
    .rank-3 { background: #fef3c7; color: #92400e; }
    .rank-other { background: #f3f4f6; color: #6b7280; }

    /* Modal */
    .spm-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px); }
    .spm-modal { background: #fff; border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
    .spm-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
    .spm-modal-header h3 { margin: 0; font-size: 17px; font-weight: 600; }
    .spm-modal-close { background: #f3f4f6; border: none; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #374151; }
    .spm-modal-close:hover { background: #e5e7eb; }
    .spm-modal-body { padding: 24px; }
    .spm-modal-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end; }

    @media (max-width: 640px) {
        .spm-form-row, .spm-form-row.cols-3 { grid-template-columns: 1fr; }
        .bntm-stats-row { grid-template-columns: 1fr 1fr; }
    }
    </style>

    <!-- ===================== GLOBAL JS ===================== -->
    <script>
    (function() {
        // Tab switching
        document.querySelectorAll('.bntm-spm-container .bntm-tab').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tab = this.dataset.tab;
                document.querySelectorAll('.bntm-spm-container .bntm-tab').forEach(function(b) { b.classList.remove('active'); });
                document.querySelectorAll('.spm-modal-overlay .bntm-tab-panel, .bntm-spm-container .bntm-tab-panel').forEach(function(p) { p.classList.remove('active'); });
                this.classList.add('active');
                var panel = document.getElementById('panel-' + tab);
                if (panel) panel.classList.add('active');
            });
        });

        // Modal helpers
        window.spmOpenModal = function(title, html) {
            document.getElementById('spm-modal-title').textContent = title;
            document.getElementById('spm-modal-body').innerHTML = html;
            document.getElementById('spm-modal-overlay').style.display = 'flex';
        };
        window.spmCloseModal = function() {
            document.getElementById('spm-modal-overlay').style.display = 'none';
        };
        document.getElementById('spm-modal-close').addEventListener('click', window.spmCloseModal);
        document.getElementById('spm-modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) window.spmCloseModal();
        });

        // Generic AJAX post
        window.spmAjax = function(data, callback) {
            var fd = new FormData();
            for (var k in data) fd.append(k, data[k]);
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(callback)
                .catch(function(err) { console.error('spm ajax error', err); });
        };

        // Notice helper
        window.spmNotice = function(targetId, message, type) {
            var el = document.getElementById(targetId);
            if (!el) return;
            el.innerHTML = '<div class="bntm-notice bntm-notice-' + type + '">' + message + '</div>';
            setTimeout(function() { el.innerHTML = ''; }, 4000);
        };

        // Confirm delete helper
        window.spmConfirmDelete = function(msg, callback) {
            if (confirm(msg || 'Are you sure you want to delete this record?')) callback();
        };
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Basketball Management', $content);
}

// ============================================================
// TAB: OVERVIEW
// ============================================================

function spm_overview_tab($business_id) {
    global $wpdb;

    $total_teams   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_teams WHERE business_id=%d", $business_id));
    $total_players = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_players WHERE business_id=%d AND status='active'", $business_id));
    $total_games   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_games WHERE business_id=%d", $business_id));
    $total_stats   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_player_stats WHERE business_id=%d", $business_id));

    $recent_games = $wpdb->get_results($wpdb->prepare("
        SELECT g.*, ta.team_name AS team_a_name, tb.team_name AS team_b_name
        FROM {$wpdb->prefix}spm_games g
        LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id = ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id = tb.id
        WHERE g.business_id = %d
        ORDER BY g.game_date DESC LIMIT 5
    ", $business_id));

    $top_scorers = $wpdb->get_results($wpdb->prepare("
        SELECT p.player_name, t.team_name, 
               COUNT(s.id) AS games_played,
               SUM(s.points) AS total_points,
               ROUND(SUM(s.points)/COUNT(s.id),1) AS ppg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id = p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE s.business_id = %d
        GROUP BY s.player_id
        ORDER BY ppg DESC LIMIT 5
    ", $business_id));

    ob_start();
    ?>
    <div class="bntm-stats-row">
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
                <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="stat-content">
                <h3>Teams</h3>
                <p class="stat-number"><?php echo $total_teams; ?></p>
                <span class="stat-label">registered</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);">
                <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div class="stat-content">
                <h3>Active Players</h3>
                <p class="stat-number"><?php echo $total_players; ?></p>
                <span class="stat-label">on rosters</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#059669,#047857);">
                <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="stat-content">
                <h3>Games</h3>
                <p class="stat-number"><?php echo $total_games; ?></p>
                <span class="stat-label">recorded</span>
            </div>
        </div>
        <div class="bntm-stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#b45309);">
                <svg width="22" height="22" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="stat-content">
                <h3>Stat Entries</h3>
                <p class="stat-number"><?php echo $total_stats; ?></p>
                <span class="stat-label">performance records</span>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; flex-wrap:wrap;">
        <!-- Recent Games -->
        <div class="bntm-form-section" style="margin-bottom:0;">
            <div class="bntm-section-header">
                <div>
                    <h3>Recent Games</h3>
                    <p>Latest recorded matches</p>
                </div>
            </div>
            <?php if (empty($recent_games)): ?>
                <p style="color:#9ca3af; text-align:center; padding:20px 0;">No games recorded yet.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($recent_games as $g): ?>
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px; background:#f9fafb; border-radius:8px; font-size:13px;">
                        <div>
                            <div style="font-weight:600; color:#111827;"><?php echo esc_html($g->team_a_name); ?> vs <?php echo esc_html($g->team_b_name); ?></div>
                            <div style="color:#6b7280; margin-top:2px;"><?php echo date('M d, Y', strtotime($g->game_date)); ?></div>
                        </div>
                        <div style="text-align:right; font-weight:700; font-size:16px; color:#111827;">
                            <?php echo $g->team_a_score; ?> &ndash; <?php echo $g->team_b_score; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Top Scorers -->
        <div class="bntm-form-section" style="margin-bottom:0;">
            <div class="bntm-section-header">
                <div>
                    <h3>Top Scorers</h3>
                    <p>By points per game</p>
                </div>
            </div>
            <?php if (empty($top_scorers)): ?>
                <p style="color:#9ca3af; text-align:center; padding:20px 0;">No stats recorded yet.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($top_scorers as $i => $p): ?>
                    <div style="display:flex; align-items:center; gap:12px; padding:10px 12px; background:#f9fafb; border-radius:8px;">
                        <div class="spm-rank-badge rank-<?php echo ($i < 3 ? ($i+1) : 'other'); ?>"><?php echo $i+1; ?></div>
                        <div style="flex:1;">
                            <div style="font-weight:600; font-size:13px;"><?php echo esc_html($p->player_name); ?></div>
                            <div style="font-size:12px; color:#6b7280;"><?php echo esc_html($p->team_name); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700; font-size:15px; color:#2563eb;"><?php echo $p->ppg; ?></div>
                            <div style="font-size:11px; color:#9ca3af;">PPG</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: TEAMS
// ============================================================

function spm_teams_tab($business_id) {
    global $wpdb;
    $teams = $wpdb->get_results($wpdb->prepare(
        "SELECT t.*, (SELECT COUNT(*) FROM {$wpdb->prefix}spm_players WHERE team_id=t.id AND status='active') AS player_count
         FROM {$wpdb->prefix}spm_teams t WHERE t.business_id=%d ORDER BY t.team_name ASC",
        $business_id
    ));
    $nonce = wp_create_nonce('spm_team_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="bntm-section-header">
            <div>
                <h3>Teams</h3>
                <p>Manage all basketball teams in the league</p>
            </div>
            <button class="bntm-btn-primary" onclick="spmOpenTeamModal(0)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Team
            </button>
        </div>

        <div id="spm-teams-notice"></div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table" id="spm-teams-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>City</th>
                        <th>Coach</th>
                        <th>Players</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teams)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:32px;">No teams found. Add your first team.</td></tr>
                    <?php else: foreach ($teams as $team): ?>
                    <tr id="team-row-<?php echo $team->id; ?>">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <?php if (!empty($team->logo)): ?>
                                    <img src="<?php echo esc_url($team->logo); ?>" style="width:36px;height:36px;border-radius:8px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="spm-avatar"><?php echo strtoupper(substr($team->team_name,0,2)); ?></div>
                                <?php endif; ?>
                                <span style="font-weight:600;"><?php echo esc_html($team->team_name); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($team->city); ?></td>
                        <td><?php echo esc_html($team->coach); ?></td>
                        <td><span class="bntm-badge bntm-badge-blue"><?php echo $team->player_count; ?> players</span></td>
                        <td style="display:flex; gap:6px;">
                            <button class="bntm-btn-small bntm-btn-edit" onclick="spmOpenTeamModal(<?php echo $team->id; ?>)">Edit</button>
                            <button class="bntm-btn-small bntm-btn-danger" onclick="spmDeleteTeam(<?php echo $team->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    var spmTeamNonce = '<?php echo $nonce; ?>';

    function spmOpenTeamModal(teamId) {
        if (teamId === 0) {
            spmOpenModal('Add Team', spmTeamForm(null));
        } else {
            spmAjax({ action: 'spm_get_team', id: teamId, nonce: spmTeamNonce }, function(res) {
                if (res.success) spmOpenModal('Edit Team', spmTeamForm(res.data));
            });
        }
    }

    function spmTeamForm(data) {
        var d = data || {};
        return '<div class="bntm-form-group"><label>Team Name *</label><input type="text" id="spm-team-name" value="' + (d.team_name||'') + '" placeholder="e.g. City Bulls"></div>' +
               '<div class="spm-form-row">' +
               '<div class="bntm-form-group"><label>City</label><input type="text" id="spm-team-city" value="' + (d.city||'') + '" placeholder="City"></div>' +
               '<div class="bntm-form-group"><label>Coach</label><input type="text" id="spm-team-coach" value="' + (d.coach||'') + '" placeholder="Coach name"></div>' +
               '</div>' +
               '<div class="bntm-form-group"><label>Logo URL</label><input type="text" id="spm-team-logo" value="' + (d.logo||'') + '" placeholder="https://..."></div>' +
               '<div id="spm-team-form-notice"></div>' +
               '<div class="spm-modal-footer">' +
               '<button class="bntm-btn-secondary" onclick="spmCloseModal()">Cancel</button>' +
               '<button class="bntm-btn-primary" onclick="spmSaveTeam(' + (d.id||0) + ')">Save Team</button>' +
               '</div>';
    }

    function spmSaveTeam(id) {
        var name = document.getElementById('spm-team-name').value.trim();
        if (!name) { spmNotice('spm-team-form-notice', 'Team name is required.', 'error'); return; }
        spmAjax({
            action: 'spm_save_team',
            nonce: spmTeamNonce,
            id: id,
            team_name: name,
            city: document.getElementById('spm-team-city').value.trim(),
            coach: document.getElementById('spm-team-coach').value.trim(),
            logo: document.getElementById('spm-team-logo').value.trim()
        }, function(res) {
            if (res.success) { spmCloseModal(); location.reload(); }
            else spmNotice('spm-team-form-notice', res.data.message, 'error');
        });
    }

    function spmDeleteTeam(id, nonce) {
        spmConfirmDelete('Delete this team? Players assigned to it will remain but become unassigned.', function() {
            spmAjax({ action: 'spm_delete_team', id: id, nonce: nonce }, function(res) {
                if (res.success) {
                    var row = document.getElementById('team-row-' + id);
                    if (row) row.remove();
                    spmNotice('spm-teams-notice', res.data.message, 'success');
                } else spmNotice('spm-teams-notice', res.data.message, 'error');
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: PLAYERS
// ============================================================

function spm_players_tab($business_id) {
    global $wpdb;

    $players = $wpdb->get_results($wpdb->prepare("
        SELECT p.*, t.team_name
        FROM {$wpdb->prefix}spm_players p
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE p.business_id = %d
        ORDER BY p.player_name ASC
    ", $business_id));

    $teams = $wpdb->get_results($wpdb->prepare(
        "SELECT id, team_name FROM {$wpdb->prefix}spm_teams WHERE business_id=%d ORDER BY team_name ASC",
        $business_id
    ));

    $teams_json = json_encode(array_map(function($t){ return ['id'=>$t->id,'name'=>$t->team_name]; }, $teams));
    $nonce = wp_create_nonce('spm_player_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="bntm-section-header">
            <div>
                <h3>Players</h3>
                <p>Manage player rosters and assignments</p>
            </div>
            <button class="bntm-btn-primary" onclick="spmOpenPlayerModal(0)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Player
            </button>
        </div>

        <div id="spm-players-notice"></div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Team</th>
                        <th>#</th>
                        <th>Position</th>
                        <th>Height</th>
                        <th>Weight</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($players)): ?>
                    <tr><td colspan="8" style="text-align:center; color:#9ca3af; padding:32px;">No players found. Add your first player.</td></tr>
                    <?php else: foreach ($players as $pl): ?>
                    <tr id="player-row-<?php echo $pl->id; ?>">
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="spm-avatar"><?php echo strtoupper(substr($pl->player_name,0,2)); ?></div>
                                <span style="font-weight:600;"><?php echo esc_html($pl->player_name); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($pl->team_name ?: '—'); ?></td>
                        <td><?php echo $pl->jersey_number; ?></td>
                        <td><?php echo esc_html($pl->position ?: '—'); ?></td>
                        <td><?php echo esc_html($pl->height ?: '—'); ?></td>
                        <td><?php echo $pl->weight > 0 ? $pl->weight . ' lbs' : '—'; ?></td>
                        <td>
                            <span class="bntm-badge <?php echo $pl->status === 'active' ? 'bntm-badge-green' : 'bntm-badge-gray'; ?>">
                                <?php echo ucfirst($pl->status); ?>
                            </span>
                        </td>
                        <td style="display:flex; gap:6px;">
                            <button class="bntm-btn-small bntm-btn-edit" onclick="spmOpenPlayerModal(<?php echo $pl->id; ?>)">Edit</button>
                            <button class="bntm-btn-small bntm-btn-danger" onclick="spmDeletePlayer(<?php echo $pl->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    var spmPlayerNonce = '<?php echo $nonce; ?>';
    var spmTeamsData   = <?php echo $teams_json; ?>;

    function spmTeamOptions(selectedId) {
        var html = '<option value="">-- Select Team --</option>';
        spmTeamsData.forEach(function(t) {
            html += '<option value="' + t.id + '"' + (t.id == selectedId ? ' selected' : '') + '>' + t.name + '</option>';
        });
        return html;
    }

    function spmOpenPlayerModal(playerId) {
        if (playerId === 0) {
            spmOpenModal('Add Player', spmPlayerForm(null));
        } else {
            spmAjax({ action: 'spm_get_player', id: playerId, nonce: spmPlayerNonce }, function(res) {
                if (res.success) spmOpenModal('Edit Player', spmPlayerForm(res.data));
            });
        }
    }

    function spmPlayerForm(data) {
        var d = data || {};
        return '<div class="bntm-form-group"><label>Player Name *</label><input type="text" id="bp-name" value="' + (d.player_name||'') + '" placeholder="Full name"></div>' +
               '<div class="bntm-form-group"><label>Team</label><select id="bp-team">' + spmTeamOptions(d.team_id||0) + '</select></div>' +
               '<div class="spm-form-row">' +
               '<div class="bntm-form-group"><label>Jersey Number</label><input type="number" id="bp-jersey" value="' + (d.jersey_number||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Position</label><select id="bp-position"><option value="">Select</option>' +
               ['PG','SG','SF','PF','C'].map(function(p){ return '<option value="'+p+'"'+(d.position===p?' selected':'')+'>'+p+'</option>'; }).join('') +
               '</select></div></div>' +
               '<div class="spm-form-row">' +
               '<div class="bntm-form-group"><label>Height</label><input type="text" id="bp-height" value="' + (d.height||'') + '" placeholder=\'6\'2"\'></div>' +
               '<div class="bntm-form-group"><label>Weight (lbs)</label><input type="number" id="bp-weight" value="' + (d.weight||0) + '" min="0"></div>' +
               '</div>' +
               '<div class="bntm-form-group"><label>Status</label><select id="bp-status"><option value="active"'+(d.status==='active'?' selected':'')+'>Active</option><option value="inactive"'+(d.status==='inactive'?' selected':'')+'>Inactive</option></select></div>' +
               '<div id="spm-player-form-notice"></div>' +
               '<div class="spm-modal-footer"><button class="bntm-btn-secondary" onclick="spmCloseModal()">Cancel</button><button class="bntm-btn-primary" onclick="spmSavePlayer(' + (d.id||0) + ')">Save Player</button></div>';
    }

    function spmSavePlayer(id) {
        var name = document.getElementById('bp-name').value.trim();
        if (!name) { spmNotice('spm-player-form-notice', 'Player name is required.', 'error'); return; }
        spmAjax({
            action: 'spm_save_player',
            nonce: spmPlayerNonce,
            id: id,
            player_name: name,
            team_id:      document.getElementById('bp-team').value,
            jersey_number:document.getElementById('bp-jersey').value,
            position:     document.getElementById('bp-position').value,
            height:       document.getElementById('bp-height').value.trim(),
            weight:       document.getElementById('bp-weight').value,
            status:       document.getElementById('bp-status').value
        }, function(res) {
            if (res.success) { spmCloseModal(); location.reload(); }
            else spmNotice('spm-player-form-notice', res.data.message, 'error');
        });
    }

    function spmDeletePlayer(id, nonce) {
        spmConfirmDelete('Delete this player? Their stats will also be removed.', function() {
            spmAjax({ action: 'spm_delete_player', id: id, nonce: nonce }, function(res) {
                if (res.success) {
                    var row = document.getElementById('player-row-' + id);
                    if (row) row.remove();
                    spmNotice('spm-players-notice', res.data.message, 'success');
                } else spmNotice('spm-players-notice', res.data.message, 'error');
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: GAMES
// ============================================================

function spm_games_tab($business_id) {
    global $wpdb;

    $games = $wpdb->get_results($wpdb->prepare("
        SELECT g.*, ta.team_name AS team_a_name, tb.team_name AS team_b_name
        FROM {$wpdb->prefix}spm_games g
        LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id = ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id = tb.id
        WHERE g.business_id = %d
        ORDER BY g.game_date DESC
    ", $business_id));

    $teams = $wpdb->get_results($wpdb->prepare(
        "SELECT id, team_name FROM {$wpdb->prefix}spm_teams WHERE business_id=%d ORDER BY team_name ASC",
        $business_id
    ));

    $teams_json = json_encode(array_map(function($t){ return ['id'=>$t->id,'name'=>$t->team_name]; }, $teams));
    $nonce = wp_create_nonce('spm_game_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="bntm-section-header">
            <div>
                <h3>Games</h3>
                <p>Record and manage game results</p>
            </div>
            <button class="bntm-btn-primary" onclick="spmOpenGameModal(0)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Game
            </button>
        </div>

        <div id="spm-games-notice"></div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Matchup</th>
                        <th>Score</th>
                        <th>Season</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($games)): ?>
                    <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:32px;">No games recorded yet.</td></tr>
                    <?php else: foreach ($games as $g):
                        $winner = '';
                        if ($g->team_a_score > $g->team_b_score) $winner = $g->team_a_name;
                        elseif ($g->team_b_score > $g->team_a_score) $winner = $g->team_b_name;
                    ?>
                    <tr id="game-row-<?php echo $g->id; ?>">
                        <td><?php echo date('M d, Y', strtotime($g->game_date)); ?></td>
                        <td>
                            <div style="font-weight:600;"><?php echo esc_html($g->team_a_name); ?></div>
                            <div style="font-size:12px; color:#9ca3af;">vs <?php echo esc_html($g->team_b_name); ?></div>
                        </td>
                        <td>
                            <span style="font-weight:700; font-size:15px;"><?php echo $g->team_a_score; ?> &ndash; <?php echo $g->team_b_score; ?></span>
                            <?php if ($winner): ?><div style="font-size:11px; color:#059669;"><?php echo esc_html($winner); ?> wins</div><?php endif; ?>
                        </td>
                        <td><?php echo esc_html($g->season ?: '—'); ?></td>
                        <td>
                            <span class="bntm-badge <?php
                                echo $g->status === 'completed' ? 'bntm-badge-green' :
                                    ($g->status === 'scheduled' ? 'bntm-badge-blue' : 'bntm-badge-gray');
                            ?>"><?php echo ucfirst($g->status); ?></span>
                        </td>
                        <td style="display:flex; gap:6px;">
                            <button class="bntm-btn-small bntm-btn-edit" onclick="spmOpenGameModal(<?php echo $g->id; ?>)">Edit</button>
                            <button class="bntm-btn-small bntm-btn-danger" onclick="spmDeleteGame(<?php echo $g->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    var spmGameNonce  = '<?php echo $nonce; ?>';
    var spmTeamsGame  = <?php echo $teams_json; ?>;

    function spmGameTeamOptions(selectedId) {
        var html = '<option value="">-- Select Team --</option>';
        spmTeamsGame.forEach(function(t) {
            html += '<option value="' + t.id + '"' + (t.id == selectedId ? ' selected' : '') + '>' + t.name + '</option>';
        });
        return html;
    }

    function spmOpenGameModal(gameId) {
        if (gameId === 0) {
            spmOpenModal('Add Game', spmGameForm(null));
        } else {
            spmAjax({ action: 'spm_get_game', id: gameId, nonce: spmGameNonce }, function(res) {
                if (res.success) spmOpenModal('Edit Game', spmGameForm(res.data));
            });
        }
    }

    function spmGameForm(data) {
        var d = data || {};
        var today = new Date().toISOString().split('T')[0];
        return '<div class="bntm-form-group"><label>Game Date *</label><input type="date" id="bg-date" value="' + (d.game_date||today) + '"></div>' +
               '<div class="bntm-form-group"><label>Team A *</label><select id="bg-team-a">' + spmGameTeamOptions(d.team_a_id||0) + '</select></div>' +
               '<div class="bntm-form-group"><label>Team B *</label><select id="bg-team-b">' + spmGameTeamOptions(d.team_b_id||0) + '</select></div>' +
               '<div class="spm-form-row">' +
               '<div class="bntm-form-group"><label>Team A Score</label><input type="number" id="bg-score-a" value="' + (d.team_a_score||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Team B Score</label><input type="number" id="bg-score-b" value="' + (d.team_b_score||0) + '" min="0"></div>' +
               '</div>' +
               '<div class="bntm-form-group"><label>Season</label><input type="text" id="bg-season" value="' + (d.season||'') + '" placeholder="e.g. 2024-2025"></div>' +
               '<div class="bntm-form-group"><label>Status</label><select id="bg-status"><option value="scheduled"'+(d.status==='scheduled'?' selected':'')+'>Scheduled</option><option value="completed"'+(d.status==='completed'?' selected':'')+'>Completed</option><option value="cancelled"'+(d.status==='cancelled'?' selected':'')+'>Cancelled</option></select></div>' +
               '<div id="spm-game-form-notice"></div>' +
               '<div class="spm-modal-footer"><button class="bntm-btn-secondary" onclick="spmCloseModal()">Cancel</button><button class="bntm-btn-primary" onclick="spmSaveGame(' + (d.id||0) + ')">Save Game</button></div>';
    }

    function spmSaveGame(id) {
        var ta = document.getElementById('bg-team-a').value;
        var tb = document.getElementById('bg-team-b').value;
        if (!ta || !tb)  { spmNotice('spm-game-form-notice', 'Both teams are required.', 'error'); return; }
        if (ta === tb)   { spmNotice('spm-game-form-notice', 'A team cannot play against itself.', 'error'); return; }
        var sa = parseInt(document.getElementById('bg-score-a').value);
        var sb = parseInt(document.getElementById('bg-score-b').value);
        if (sa < 0 || sb < 0) { spmNotice('spm-game-form-notice', 'Scores must be non-negative.', 'error'); return; }
        spmAjax({
            action: 'spm_save_game',
            nonce: spmGameNonce,
            id: id,
            game_date:    document.getElementById('bg-date').value,
            team_a_id:    ta,
            team_b_id:    tb,
            team_a_score: sa,
            team_b_score: sb,
            season:       document.getElementById('bg-season').value.trim(),
            status:       document.getElementById('bg-status').value
        }, function(res) {
            if (res.success) { spmCloseModal(); location.reload(); }
            else spmNotice('spm-game-form-notice', res.data.message, 'error');
        });
    }

    function spmDeleteGame(id, nonce) {
        spmConfirmDelete('Delete this game? All associated player stats will also be removed.', function() {
            spmAjax({ action: 'spm_delete_game', id: id, nonce: nonce }, function(res) {
                if (res.success) {
                    var row = document.getElementById('game-row-' + id);
                    if (row) row.remove();
                    spmNotice('spm-games-notice', res.data.message, 'success');
                } else spmNotice('spm-games-notice', res.data.message, 'error');
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: PLAYER STATS
// ============================================================

function spm_stats_tab($business_id) {
    global $wpdb;

    $stats = $wpdb->get_results($wpdb->prepare("
        SELECT s.*,
               p.player_name, p.jersey_number, p.position,
               t.team_name,
               CONCAT(ta.team_name,' vs ',tb.team_name,' (',DATE_FORMAT(g.game_date,'%%b %%d, %%Y'),')') AS game_label
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id = p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t   ON p.team_id = t.id
        LEFT JOIN {$wpdb->prefix}spm_games g   ON s.game_id = g.id
        LEFT JOIN {$wpdb->prefix}spm_teams ta  ON g.team_a_id = ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb  ON g.team_b_id = tb.id
        WHERE s.business_id = %d
        ORDER BY s.id DESC
    ", $business_id));

    $games = $wpdb->get_results($wpdb->prepare("
        SELECT g.id, CONCAT(ta.team_name,' vs ',tb.team_name,' (',DATE_FORMAT(g.game_date,'%%b %%d, %%Y'),')') AS label
        FROM {$wpdb->prefix}spm_games g
        LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id = ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id = tb.id
        WHERE g.business_id = %d
        ORDER BY g.game_date DESC
    ", $business_id));

    $games_json = json_encode(array_map(function($g){ return ['id'=>$g->id,'label'=>$g->label]; }, $games));
    $nonce = wp_create_nonce('spm_stat_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div class="bntm-section-header">
            <div>
                <h3>Player Statistics</h3>
                <p>Record individual performance per game</p>
            </div>
            <button class="bntm-btn-primary" onclick="spmOpenStatModal(0)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:6px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Stats Entry
            </button>
        </div>

        <div id="spm-stats-notice"></div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Game</th>
                        <th>MIN</th>
                        <th>PTS</th>
                        <th>REB</th>
                        <th>AST</th>
                        <th>STL</th>
                        <th>BLK</th>
                        <th>TO</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                    <tr><td colspan="10" style="text-align:center; color:#9ca3af; padding:32px;">No statistics recorded yet.</td></tr>
                    <?php else: foreach ($stats as $s): ?>
                    <tr id="stat-row-<?php echo $s->id; ?>">
                        <td>
                            <div style="font-weight:600;"><?php echo esc_html($s->player_name); ?></div>
                            <div style="font-size:12px; color:#9ca3af;"><?php echo esc_html($s->team_name ?: '—'); ?></div>
                        </td>
                        <td style="font-size:12px; color:#6b7280;"><?php echo esc_html($s->game_label); ?></td>
                        <td><?php echo $s->minutes; ?></td>
                        <td style="font-weight:700; color:#2563eb;"><?php echo $s->points; ?></td>
                        <td><?php echo $s->rebounds; ?></td>
                        <td><?php echo $s->assists; ?></td>
                        <td><?php echo $s->steals; ?></td>
                        <td><?php echo $s->blocks; ?></td>
                        <td><?php echo $s->turnovers; ?></td>
                        <td style="display:flex; gap:6px;">
                            <button class="bntm-btn-small bntm-btn-edit" onclick="spmOpenStatModal(<?php echo $s->id; ?>)">Edit</button>
                            <button class="bntm-btn-small bntm-btn-danger" onclick="spmDeleteStat(<?php echo $s->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    var spmStatNonce = '<?php echo $nonce; ?>';
    var spmGamesData = <?php echo $games_json; ?>;

    function spmGamesOptions(selectedId) {
        var html = '<option value="">-- Select Game --</option>';
        spmGamesData.forEach(function(g) {
            html += '<option value="' + g.id + '"' + (g.id == selectedId ? ' selected' : '') + '>' + g.label + '</option>';
        });
        return html;
    }

    function spmOpenStatModal(statId) {
        if (statId === 0) {
            spmOpenModal('Add Player Statistics', spmStatFormShell(null));
            spmBindGameChange();
        } else {
            spmAjax({ action: 'spm_get_stat', id: statId, nonce: spmStatNonce }, function(res) {
                if (res.success) {
                    spmOpenModal('Edit Player Statistics', spmStatFormShell(res.data));
                    spmBindGameChange(res.data.game_id, res.data.player_id);
                }
            });
        }
    }

    function spmStatFormShell(data) {
        var d = data || {};
        return '<div class="bntm-form-group"><label>Game *</label><select id="bs-game">' + spmGamesOptions(d.game_id||0) + '</select></div>' +
               '<div class="bntm-form-group"><label>Player *</label><select id="bs-player"><option value="">-- Select Game First --</option></select></div>' +
               '<div id="bs-stats-fields">' + spmStatFields(d) + '</div>' +
               '<div id="spm-stat-form-notice"></div>' +
               '<div class="spm-modal-footer"><button class="bntm-btn-secondary" onclick="spmCloseModal()">Cancel</button><button class="bntm-btn-primary" onclick="spmSaveStat(' + (d.id||0) + ')">Save Stats</button></div>';
    }

    function spmStatFields(d) {
        d = d || {};
        return '<div class="spm-form-row cols-3">' +
               '<div class="bntm-form-group"><label>Minutes</label><input type="number" id="bs-min" value="' + (d.minutes||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Points</label><input type="number" id="bs-pts" value="' + (d.points||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Rebounds</label><input type="number" id="bs-reb" value="' + (d.rebounds||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Assists</label><input type="number" id="bs-ast" value="' + (d.assists||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Steals</label><input type="number" id="bs-stl" value="' + (d.steals||0) + '" min="0"></div>' +
               '<div class="bntm-form-group"><label>Blocks</label><input type="number" id="bs-blk" value="' + (d.blocks||0) + '" min="0"></div>' +
               '</div>' +
               '<div class="bntm-form-group" style="max-width:200px;"><label>Turnovers</label><input type="number" id="bs-to" value="' + (d.turnovers||0) + '" min="0"></div>';
    }

    function spmBindGameChange(preselectedGame, preselectedPlayer) {
        var sel = document.getElementById('bs-game');
        if (!sel) return;
        sel.addEventListener('change', function() {
            spmLoadGamePlayers(this.value, preselectedPlayer);
        });
        if (preselectedGame) spmLoadGamePlayers(preselectedGame, preselectedPlayer);
    }

    function spmLoadGamePlayers(gameId, preselectedPlayer) {
        if (!gameId) return;
        spmAjax({ action: 'spm_get_game_players', game_id: gameId, nonce: spmStatNonce }, function(res) {
            var sel = document.getElementById('bs-player');
            if (!sel || !res.success) return;
            sel.innerHTML = '<option value="">-- Select Player --</option>';
            res.data.forEach(function(p) {
                var opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = '#' + p.jersey_number + ' ' + p.player_name + ' (' + p.team_name + ')';
                if (preselectedPlayer && p.id == preselectedPlayer) opt.selected = true;
                sel.appendChild(opt);
            });
        });
    }

    function spmSaveStat(id) {
        var gid = document.getElementById('bs-game').value;
        var pid = document.getElementById('bs-player').value;
        if (!gid) { spmNotice('spm-stat-form-notice', 'Please select a game.', 'error'); return; }
        if (!pid) { spmNotice('spm-stat-form-notice', 'Please select a player.', 'error'); return; }
        spmAjax({
            action: 'spm_save_stat',
            nonce: spmStatNonce,
            id: id,
            game_id:   gid,
            player_id: pid,
            minutes:   document.getElementById('bs-min').value,
            points:    document.getElementById('bs-pts').value,
            rebounds:  document.getElementById('bs-reb').value,
            assists:   document.getElementById('bs-ast').value,
            steals:    document.getElementById('bs-stl').value,
            blocks:    document.getElementById('bs-blk').value,
            turnovers: document.getElementById('bs-to').value
        }, function(res) {
            if (res.success) { spmCloseModal(); location.reload(); }
            else spmNotice('spm-stat-form-notice', res.data.message, 'error');
        });
    }

    function spmDeleteStat(id, nonce) {
        spmConfirmDelete('Delete this stat entry?', function() {
            spmAjax({ action: 'spm_delete_stat', id: id, nonce: nonce }, function(res) {
                if (res.success) {
                    var row = document.getElementById('stat-row-' + id);
                    if (row) row.remove();
                    spmNotice('spm-stats-notice', res.data.message, 'success');
                } else spmNotice('spm-stats-notice', res.data.message, 'error');
            });
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: RANKINGS
// ============================================================

function spm_rankings_tab($business_id) {
    global $wpdb;

    $scorers = $wpdb->get_results($wpdb->prepare("
        SELECT p.id, p.player_name, t.team_name,
               COUNT(s.id) AS gp,
               SUM(s.points) AS total_pts,
               ROUND(SUM(s.points)/COUNT(s.id),1) AS ppg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id = p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE s.business_id = %d
        GROUP BY s.player_id HAVING gp > 0
        ORDER BY ppg DESC LIMIT 15
    ", $business_id));

    $rebounders = $wpdb->get_results($wpdb->prepare("
        SELECT p.player_name, t.team_name,
               COUNT(s.id) AS gp,
               ROUND(SUM(s.rebounds)/COUNT(s.id),1) AS rpg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id = p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE s.business_id = %d
        GROUP BY s.player_id HAVING gp > 0
        ORDER BY rpg DESC LIMIT 10
    ", $business_id));

    $assisters = $wpdb->get_results($wpdb->prepare("
        SELECT p.player_name, t.team_name,
               COUNT(s.id) AS gp,
               ROUND(SUM(s.assists)/COUNT(s.id),1) AS apg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id = p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE s.business_id = %d
        GROUP BY s.player_id HAVING gp > 0
        ORDER BY apg DESC LIMIT 10
    ", $business_id));

    $team_standings = $wpdb->get_results($wpdb->prepare("
        SELECT t.team_name,
               SUM(CASE WHEN (g.team_a_id=t.id AND g.team_a_score>g.team_b_score) OR (g.team_b_id=t.id AND g.team_b_score>g.team_a_score) THEN 1 ELSE 0 END) AS wins,
               SUM(CASE WHEN (g.team_a_id=t.id AND g.team_a_score<g.team_b_score) OR (g.team_b_id=t.id AND g.team_b_score<g.team_a_score) THEN 1 ELSE 0 END) AS losses,
               COUNT(g.id) AS total_games
        FROM {$wpdb->prefix}spm_teams t
        LEFT JOIN {$wpdb->prefix}spm_games g 
            ON (g.team_a_id=t.id OR g.team_b_id=t.id) AND g.status='completed' AND g.business_id=%d
        WHERE t.business_id = %d
        GROUP BY t.id
        ORDER BY wins DESC, losses ASC
    ", $business_id, $business_id));

    ob_start();
    ?>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
        <!-- Top Scorers -->
        <div class="bntm-form-section" style="margin-bottom:0;">
            <h3 style="margin-bottom:16px;">Top Scorers</h3>
            <div class="bntm-table-wrapper">
                <table class="bntm-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>PPG</th></tr></thead>
                    <tbody>
                        <?php if (empty($scorers)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:20px;">No data</td></tr>
                        <?php else: foreach ($scorers as $i => $p): ?>
                        <tr>
                            <td><div class="spm-rank-badge <?php echo $i<3 ? 'rank-'.($i+1) : 'rank-other'; ?>"><?php echo $i+1; ?></div></td>
                            <td style="font-weight:600;"><?php echo esc_html($p->player_name); ?></td>
                            <td style="font-size:12px; color:#6b7280;"><?php echo esc_html($p->team_name); ?></td>
                            <td><?php echo $p->gp; ?></td>
                            <td style="font-weight:700; color:#2563eb;"><?php echo $p->ppg; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Team Standings -->
        <div class="bntm-form-section" style="margin-bottom:0;">
            <h3 style="margin-bottom:16px;">Team Standings</h3>
            <div class="bntm-table-wrapper">
                <table class="bntm-table">
                    <thead><tr><th>Rank</th><th>Team</th><th>W</th><th>L</th><th>GP</th></tr></thead>
                    <tbody>
                        <?php if (empty($team_standings)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:20px;">No data</td></tr>
                        <?php else: foreach ($team_standings as $i => $s): ?>
                        <tr>
                            <td><div class="spm-rank-badge <?php echo $i<3 ? 'rank-'.($i+1) : 'rank-other'; ?>"><?php echo $i+1; ?></div></td>
                            <td style="font-weight:600;"><?php echo esc_html($s->team_name); ?></td>
                            <td style="font-weight:700; color:#059669;"><?php echo $s->wins; ?></td>
                            <td style="font-weight:700; color:#dc2626;"><?php echo $s->losses; ?></td>
                            <td><?php echo $s->total_games; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
        <!-- Top Rebounders -->
        <div class="bntm-form-section" style="margin-bottom:0;">
            <h3 style="margin-bottom:16px;">Top Rebounders</h3>
            <div class="bntm-table-wrapper">
                <table class="bntm-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>RPG</th></tr></thead>
                    <tbody>
                        <?php if (empty($rebounders)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:20px;">No data</td></tr>
                        <?php else: foreach ($rebounders as $i => $p): ?>
                        <tr>
                            <td><div class="spm-rank-badge <?php echo $i<3 ? 'rank-'.($i+1) : 'rank-other'; ?>"><?php echo $i+1; ?></div></td>
                            <td style="font-weight:600;"><?php echo esc_html($p->player_name); ?></td>
                            <td style="font-size:12px; color:#6b7280;"><?php echo esc_html($p->team_name); ?></td>
                            <td><?php echo $p->gp; ?></td>
                            <td style="font-weight:700; color:#7c3aed;"><?php echo $p->rpg; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Assisters -->
        <div class="bntm-form-section" style="margin-bottom:0;">
            <h3 style="margin-bottom:16px;">Top Assists</h3>
            <div class="bntm-table-wrapper">
                <table class="bntm-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>APG</th></tr></thead>
                    <tbody>
                        <?php if (empty($assisters)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:20px;">No data</td></tr>
                        <?php else: foreach ($assisters as $i => $p): ?>
                        <tr>
                            <td><div class="spm-rank-badge <?php echo $i<3 ? 'rank-'.($i+1) : 'rank-other'; ?>"><?php echo $i+1; ?></div></td>
                            <td style="font-weight:600;"><?php echo esc_html($p->player_name); ?></td>
                            <td style="font-size:12px; color:#6b7280;"><?php echo esc_html($p->team_name); ?></td>
                            <td><?php echo $p->gp; ?></td>
                            <td style="font-weight:700; color:#059669;"><?php echo $p->apg; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// PUBLIC SHORTCODE
// ============================================================

function bntm_shortcode_spm_public() {
    // Public-facing read-only view
    ob_start();
    ?>
    <div class="bntm-spm-container" id="spm-public-wrap">
        <div class="bntm-tabs">
            <button class="bntm-tab active" data-ptab="pub-teams">Teams</button>
            <button class="bntm-tab" data-ptab="pub-players">Players</button>
            <button class="bntm-tab" data-ptab="pub-results">Results</button>
            <button class="bntm-tab" data-ptab="pub-rankings">Rankings</button>
        </div>
        <div id="pub-teams"    class="bntm-tab-panel active"><p style="text-align:center; color:#9ca3af; padding:30px;">Loading...</p></div>
        <div id="pub-players"  class="bntm-tab-panel"></div>
        <div id="pub-results"  class="bntm-tab-panel"></div>
        <div id="pub-rankings" class="bntm-tab-panel"></div>
    </div>

    <style>
    /* reuse shared dashboard styles already declared above */
    </style>

    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    (function() {
        // Tab switching
        document.querySelectorAll('#spm-public-wrap .bntm-tab').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#spm-public-wrap .bntm-tab').forEach(function(b){ b.classList.remove('active'); });
                document.querySelectorAll('#spm-public-wrap .bntm-tab-panel').forEach(function(p){ p.classList.remove('active'); });
                this.classList.add('active');
                var panel = document.getElementById(this.dataset.ptab);
                if (panel) {
                    panel.classList.add('active');
                    if (!panel.dataset.loaded) loadPublicTab(this.dataset.ptab);
                }
            });
        });

        loadPublicTab('pub-teams');

        function loadPublicTab(tabId) {
            var map = { 'pub-teams': 'teams', 'pub-players': 'players', 'pub-results': 'results', 'pub-rankings': 'rankings' };
            var type = map[tabId];
            if (!type) return;
            var fd = new FormData();
            fd.append('action', 'spm_public_data');
            fd.append('type', type);
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(res) {
                    var panel = document.getElementById(tabId);
                    if (panel && res.success) {
                        panel.innerHTML = res.data.html;
                        panel.dataset.loaded = '1';
                    }
                });
        }
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Basketball League', $content);
}

// ============================================================
// AJAX: PUBLIC DATA
// ============================================================

function bntm_ajax_spm_public_data() {
    global $wpdb;
    $type = sanitize_text_field($_POST['type'] ?? '');

    // For public views we show all records (no business_id filter, or could filter by a global setting)
    switch ($type) {
        case 'teams':
            $rows = $wpdb->get_results("SELECT t.*, (SELECT COUNT(*) FROM {$wpdb->prefix}spm_players WHERE team_id=t.id AND status='active') AS player_count FROM {$wpdb->prefix}spm_teams t ORDER BY t.team_name ASC");
            ob_start();
            ?>
            <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead><tr><th>Team</th><th>City</th><th>Coach</th><th>Players</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="4" style="text-align:center; color:#9ca3af; padding:24px;">No teams yet.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?php echo esc_html($r->team_name); ?></strong></td>
                    <td><?php echo esc_html($r->city); ?></td>
                    <td><?php echo esc_html($r->coach ?: '—'); ?></td>
                    <td><span class="bntm-badge bntm-badge-blue"><?php echo $r->player_count; ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <?php
            wp_send_json_success(['html' => ob_get_clean()]);
            break;

        case 'players':
            $rows = $wpdb->get_results("SELECT p.*, t.team_name FROM {$wpdb->prefix}spm_players p LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id WHERE p.status='active' ORDER BY p.player_name ASC");
            ob_start();
            ?>
            <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead><tr><th>Player</th><th>Team</th><th>#</th><th>Position</th><th>Height</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="5" style="text-align:center; color:#9ca3af; padding:24px;">No players yet.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><strong><?php echo esc_html($r->player_name); ?></strong></td>
                    <td><?php echo esc_html($r->team_name ?: '—'); ?></td>
                    <td><?php echo $r->jersey_number; ?></td>
                    <td><?php echo esc_html($r->position ?: '—'); ?></td>
                    <td><?php echo esc_html($r->height ?: '—'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <?php
            wp_send_json_success(['html' => ob_get_clean()]);
            break;

        case 'results':
            $rows = $wpdb->get_results("SELECT g.*, ta.team_name AS ta, tb.team_name AS tb FROM {$wpdb->prefix}spm_games g LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id=ta.id LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id=tb.id ORDER BY g.game_date DESC LIMIT 30");
            ob_start();
            ?>
            <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead><tr><th>Date</th><th>Teams</th><th>Score</th><th>Season</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="4" style="text-align:center; color:#9ca3af; padding:24px;">No results yet.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($r->game_date)); ?></td>
                    <td><?php echo esc_html($r->ta); ?> vs <?php echo esc_html($r->tb); ?></td>
                    <td style="font-weight:700;"><?php echo $r->team_a_score; ?> &ndash; <?php echo $r->team_b_score; ?></td>
                    <td><?php echo esc_html($r->season ?: '—'); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <?php
            wp_send_json_success(['html' => ob_get_clean()]);
            break;

        case 'rankings':
            $scorers = $wpdb->get_results("SELECT p.player_name, t.team_name, COUNT(s.id) AS gp, ROUND(SUM(s.points)/COUNT(s.id),1) AS ppg, ROUND(SUM(s.rebounds)/COUNT(s.id),1) AS rpg, ROUND(SUM(s.assists)/COUNT(s.id),1) AS apg FROM {$wpdb->prefix}spm_player_stats s LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id GROUP BY s.player_id HAVING gp>0 ORDER BY ppg DESC LIMIT 10");
            ob_start();
            ?>
            <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>PPG</th><th>RPG</th><th>APG</th></tr></thead>
                <tbody>
                <?php if (empty($scorers)): ?><tr><td colspan="7" style="text-align:center; color:#9ca3af; padding:24px;">No stats yet.</td></tr>
                <?php else: foreach ($scorers as $i => $p): ?>
                <tr>
                    <td><div class="spm-rank-badge <?php echo $i<3?'rank-'.($i+1):'rank-other'; ?>"><?php echo $i+1; ?></div></td>
                    <td style="font-weight:600;"><?php echo esc_html($p->player_name); ?></td>
                    <td style="font-size:12px; color:#6b7280;"><?php echo esc_html($p->team_name); ?></td>
                    <td><?php echo $p->gp; ?></td>
                    <td style="font-weight:700; color:#2563eb;"><?php echo $p->ppg; ?></td>
                    <td style="font-weight:700; color:#7c3aed;"><?php echo $p->rpg; ?></td>
                    <td style="font-weight:700; color:#059669;"><?php echo $p->apg; ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            <?php
            wp_send_json_success(['html' => ob_get_clean()]);
            break;

        default:
            wp_send_json_error(['message' => 'Invalid type']);
    }
}

// ============================================================
// AJAX HANDLERS: TEAMS
// ============================================================

function bntm_ajax_spm_get_team() {
    check_ajax_referer('spm_team_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $id   = intval($_POST['id']);
    $team = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_teams WHERE id=%d AND business_id=%d", $id, get_current_user_id()));
    if (!$team) wp_send_json_error(['message' => 'Team not found']);
    wp_send_json_success($team);
}

function bntm_ajax_spm_save_team() {
    check_ajax_referer('spm_team_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;

    $id          = intval($_POST['id'] ?? 0);
    $business_id = get_current_user_id();
    $data        = [
        'team_name' => sanitize_text_field($_POST['team_name']),
        'city'      => sanitize_text_field($_POST['city'] ?? ''),
        'coach'     => sanitize_text_field($_POST['coach'] ?? ''),
        'logo'      => esc_url_raw($_POST['logo'] ?? ''),
    ];
    $formats = ['%s','%s','%s','%s'];

    if (empty($data['team_name'])) wp_send_json_error(['message' => 'Team name is required']);

    if ($id > 0) {
        $result = $wpdb->update("{$wpdb->prefix}spm_teams", $data, ['id' => $id, 'business_id' => $business_id], $formats, ['%d','%d']);
        wp_send_json_success(['message' => 'Team updated successfully!']);
    } else {
        $data['rand_id']     = bntm_rand_id();
        $data['business_id'] = $business_id;
        $wpdb->insert("{$wpdb->prefix}spm_teams", $data, array_merge(['%s','%d'], $formats));
        wp_send_json_success(['message' => 'Team created successfully!']);
    }
}

function bntm_ajax_spm_delete_team() {
    check_ajax_referer('spm_team_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $id = intval($_POST['id']);
    $wpdb->delete("{$wpdb->prefix}spm_teams", ['id' => $id, 'business_id' => get_current_user_id()], ['%d','%d']);
    wp_send_json_success(['message' => 'Team deleted.']);
}

// ============================================================
// AJAX HANDLERS: PLAYERS
// ============================================================

function bntm_ajax_spm_get_player() {
    check_ajax_referer('spm_player_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_players WHERE id=%d AND business_id=%d", intval($_POST['id']), get_current_user_id()));
    if (!$row) wp_send_json_error(['message' => 'Player not found']);
    wp_send_json_success($row);
}

function bntm_ajax_spm_save_player() {
    check_ajax_referer('spm_player_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;

    $id          = intval($_POST['id'] ?? 0);
    $business_id = get_current_user_id();
    $data        = [
        'team_id'       => intval($_POST['team_id'] ?? 0),
        'player_name'   => sanitize_text_field($_POST['player_name']),
        'jersey_number' => intval($_POST['jersey_number'] ?? 0),
        'position'      => sanitize_text_field($_POST['position'] ?? ''),
        'height'        => sanitize_text_field($_POST['height'] ?? ''),
        'weight'        => intval($_POST['weight'] ?? 0),
        'status'        => sanitize_text_field($_POST['status'] ?? 'active'),
    ];
    $formats = ['%d','%s','%d','%s','%s','%d','%s'];

    if (empty($data['player_name'])) wp_send_json_error(['message' => 'Player name is required']);

    if ($id > 0) {
        $wpdb->update("{$wpdb->prefix}spm_players", $data, ['id' => $id, 'business_id' => $business_id], $formats, ['%d','%d']);
        wp_send_json_success(['message' => 'Player updated successfully!']);
    } else {
        $data['rand_id']     = bntm_rand_id();
        $data['business_id'] = $business_id;
        $wpdb->insert("{$wpdb->prefix}spm_players", $data, array_merge(['%s','%d'], $formats));
        wp_send_json_success(['message' => 'Player added successfully!']);
    }
}

function bntm_ajax_spm_delete_player() {
    check_ajax_referer('spm_player_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $id = intval($_POST['id']);
    $wpdb->delete("{$wpdb->prefix}spm_player_stats", ['player_id' => $id, 'business_id' => get_current_user_id()], ['%d','%d']);
    $wpdb->delete("{$wpdb->prefix}spm_players",      ['id' => $id, 'business_id' => get_current_user_id()], ['%d','%d']);
    wp_send_json_success(['message' => 'Player deleted.']);
}

// ============================================================
// AJAX HANDLERS: GAMES
// ============================================================

function bntm_ajax_spm_get_game() {
    check_ajax_referer('spm_game_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_games WHERE id=%d AND business_id=%d", intval($_POST['id']), get_current_user_id()));
    if (!$row) wp_send_json_error(['message' => 'Game not found']);
    wp_send_json_success($row);
}

function bntm_ajax_spm_save_game() {
    check_ajax_referer('spm_game_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;

    $id          = intval($_POST['id'] ?? 0);
    $business_id = get_current_user_id();
    $team_a      = intval($_POST['team_a_id']);
    $team_b      = intval($_POST['team_b_id']);

    if (!$team_a || !$team_b)         wp_send_json_error(['message' => 'Both teams are required']);
    if ($team_a === $team_b)          wp_send_json_error(['message' => 'A team cannot play against itself']);
    $score_a = intval($_POST['team_a_score'] ?? 0);
    $score_b = intval($_POST['team_b_score'] ?? 0);
    if ($score_a < 0 || $score_b < 0) wp_send_json_error(['message' => 'Scores must be non-negative']);

    $data = [
        'game_date'    => sanitize_text_field($_POST['game_date']),
        'team_a_id'    => $team_a,
        'team_b_id'    => $team_b,
        'team_a_score' => $score_a,
        'team_b_score' => $score_b,
        'season'       => sanitize_text_field($_POST['season'] ?? ''),
        'status'       => sanitize_text_field($_POST['status'] ?? 'scheduled'),
    ];
    $formats = ['%s','%d','%d','%d','%d','%s','%s'];

    if ($id > 0) {
        $wpdb->update("{$wpdb->prefix}spm_games", $data, ['id' => $id, 'business_id' => $business_id], $formats, ['%d','%d']);
        wp_send_json_success(['message' => 'Game updated successfully!']);
    } else {
        $data['rand_id']     = bntm_rand_id();
        $data['business_id'] = $business_id;
        $wpdb->insert("{$wpdb->prefix}spm_games", $data, array_merge(['%s','%d'], $formats));
        wp_send_json_success(['message' => 'Game recorded successfully!']);
    }
}

function bntm_ajax_spm_delete_game() {
    check_ajax_referer('spm_game_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $id = intval($_POST['id']);
    $wpdb->delete("{$wpdb->prefix}spm_player_stats", ['game_id' => $id, 'business_id' => get_current_user_id()], ['%d','%d']);
    $wpdb->delete("{$wpdb->prefix}spm_games",        ['id' => $id, 'business_id' => get_current_user_id()], ['%d','%d']);
    wp_send_json_success(['message' => 'Game deleted.']);
}

// ============================================================
// AJAX HANDLERS: STATS
// ============================================================

function bntm_ajax_spm_get_game_players() {
    check_ajax_referer('spm_stat_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;

    $game_id     = intval($_POST['game_id']);
    $business_id = get_current_user_id();

    $game = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}spm_games WHERE id=%d AND business_id=%d",
        $game_id, $business_id
    ));
    if (!$game) wp_send_json_error(['message' => 'Game not found']);

    $players = $wpdb->get_results($wpdb->prepare(
        "SELECT p.id, p.player_name, p.jersey_number, t.team_name
         FROM {$wpdb->prefix}spm_players p
         LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id
         WHERE p.team_id IN (%d, %d) AND p.status='active'
         ORDER BY t.team_name, p.player_name",
        $game->team_a_id, $game->team_b_id
    ));

    wp_send_json_success($players);
}

function bntm_ajax_spm_get_stat() {
    // Note: uses stat_nonce but action is spm_get_stat — we reuse check here
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    // nonce checked by caller via spm_stat_nonce
    check_ajax_referer('spm_stat_nonce', 'nonce');
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_player_stats WHERE id=%d AND business_id=%d", intval($_POST['id']), get_current_user_id()));
    if (!$row) wp_send_json_error(['message' => 'Stat not found']);
    wp_send_json_success($row);
}
add_action('wp_ajax_spm_get_stat', 'bntm_ajax_spm_get_stat');

function bntm_ajax_spm_save_stat() {
    check_ajax_referer('spm_stat_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;

    $id          = intval($_POST['id'] ?? 0);
    $business_id = get_current_user_id();
    $game_id     = intval($_POST['game_id']);
    $player_id   = intval($_POST['player_id']);

    if (!$game_id || !$player_id) wp_send_json_error(['message' => 'Game and player are required']);

    // Verify player belongs to a team in this game
    $game = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_games WHERE id=%d AND business_id=%d", $game_id, $business_id));
    if (!$game) wp_send_json_error(['message' => 'Game not found']);

    $player = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_players WHERE id=%d", $player_id));
    if (!$player || !in_array($player->team_id, [$game->team_a_id, $game->team_b_id])) {
        wp_send_json_error(['message' => 'Player is not in a team participating in this game']);
    }

    $data = [
        'game_id'   => $game_id,
        'player_id' => $player_id,
        'minutes'   => max(0, intval($_POST['minutes']   ?? 0)),
        'points'    => max(0, intval($_POST['points']    ?? 0)),
        'rebounds'  => max(0, intval($_POST['rebounds']  ?? 0)),
        'assists'   => max(0, intval($_POST['assists']   ?? 0)),
        'steals'    => max(0, intval($_POST['steals']    ?? 0)),
        'blocks'    => max(0, intval($_POST['blocks']    ?? 0)),
        'turnovers' => max(0, intval($_POST['turnovers'] ?? 0)),
    ];
    $formats = ['%d','%d','%d','%d','%d','%d','%d','%d','%d'];

    if ($id > 0) {
        $wpdb->update("{$wpdb->prefix}spm_player_stats", $data, ['id' => $id, 'business_id' => $business_id], $formats, ['%d','%d']);
        wp_send_json_success(['message' => 'Statistics updated successfully!']);
    } else {
        $data['rand_id']     = bntm_rand_id();
        $data['business_id'] = $business_id;
        $wpdb->insert("{$wpdb->prefix}spm_player_stats", $data, array_merge(['%s','%d'], $formats));
        wp_send_json_success(['message' => 'Statistics recorded successfully!']);
    }
}

function bntm_ajax_spm_delete_stat() {
    check_ajax_referer('spm_stat_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}spm_player_stats", ['id' => intval($_POST['id']), 'business_id' => get_current_user_id()], ['%d','%d']);
    wp_send_json_success(['message' => 'Stat entry deleted.']);
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Get computed stats summary for a player
 */
function spm_get_player_averages($player_id) {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("
        SELECT COUNT(id) AS gp,
               ROUND(SUM(points)/COUNT(id),1)    AS ppg,
               ROUND(SUM(rebounds)/COUNT(id),1)  AS rpg,
               ROUND(SUM(assists)/COUNT(id),1)   AS apg,
               ROUND(SUM(steals)/COUNT(id),1)    AS spg,
               ROUND(SUM(blocks)/COUNT(id),1)    AS bpg,
               ROUND(SUM(minutes)/COUNT(id),1)   AS mpg
        FROM {$wpdb->prefix}spm_player_stats
        WHERE player_id = %d
    ", $player_id));
    return $row ?: (object)['gp'=>0,'ppg'=>0,'rpg'=>0,'apg'=>0,'spg'=>0,'bpg'=>0,'mpg'=>0];
}

/**
 * Get team win/loss record
 */
function spm_get_team_record($team_id, $business_id) {
    global $wpdb;
    $wins   = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}spm_games
        WHERE business_id=%d AND status='completed'
          AND ((team_a_id=%d AND team_a_score>team_b_score)
            OR (team_b_id=%d AND team_b_score>team_a_score))
    ", $business_id, $team_id, $team_id));
    $losses = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}spm_games
        WHERE business_id=%d AND status='completed'
          AND ((team_a_id=%d AND team_a_score<team_b_score)
            OR (team_b_id=%d AND team_b_score<team_a_score))
    ", $business_id, $team_id, $team_id));
    return ['wins' => $wins, 'losses' => $losses];
}
