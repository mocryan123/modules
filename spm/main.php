<?php
/**
 * Module Name: Basketball Teams & Player Statistics
 * Module Slug: spm
 * Description: Manages basketball teams, players, games, and player performance statistics including automatic leaderboard calculations.
 * Version: 2.0.0
 * Author: Development Team
 * Icon: 🏀
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
            primary_color VARCHAR(7) NOT NULL DEFAULT '#1F2D6D',
            secondary_color VARCHAR(7) NOT NULL DEFAULT '#FFD700',
            outline_color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
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
        'spm_seasons' => "CREATE TABLE {$prefix}spm_seasons (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            season_name VARCHAR(100) NOT NULL,
            start_date DATE DEFAULT NULL,
            end_date DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
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

add_action('wp_ajax_spm_save_team',    'bntm_ajax_spm_save_team');
add_action('wp_ajax_spm_delete_team',  'bntm_ajax_spm_delete_team');
add_action('wp_ajax_spm_get_team',     'bntm_ajax_spm_get_team');
add_action('wp_ajax_spm_get_team_profile', 'bntm_ajax_spm_get_team_profile');
add_action('wp_ajax_spm_save_player',   'bntm_ajax_spm_save_player');
add_action('wp_ajax_spm_delete_player', 'bntm_ajax_spm_delete_player');
add_action('wp_ajax_spm_get_player',    'bntm_ajax_spm_get_player');
add_action('wp_ajax_spm_get_player_profile', 'bntm_ajax_spm_get_player_profile');
add_action('wp_ajax_spm_save_game',   'bntm_ajax_spm_save_game');
add_action('wp_ajax_spm_delete_game', 'bntm_ajax_spm_delete_game');
add_action('wp_ajax_spm_get_game',    'bntm_ajax_spm_get_game');
add_action('wp_ajax_spm_save_season',   'bntm_ajax_spm_save_season');
add_action('wp_ajax_nopriv_spm_save_season',   'bntm_ajax_spm_save_season');
add_action('wp_ajax_spm_delete_season', 'bntm_ajax_spm_delete_season');
add_action('wp_ajax_nopriv_spm_delete_season', 'bntm_ajax_spm_delete_season');
add_action('wp_ajax_spm_get_season',    'bntm_ajax_spm_get_season');
add_action('wp_ajax_nopriv_spm_get_season',    'bntm_ajax_spm_get_season');
add_action('wp_ajax_spm_save_stat',   'bntm_ajax_spm_save_stat');
add_action('wp_ajax_spm_delete_stat', 'bntm_ajax_spm_delete_stat');
add_action('wp_ajax_spm_get_game_players', 'bntm_ajax_spm_get_game_players');
add_action('wp_ajax_nopriv_spm_public_data', 'bntm_ajax_spm_public_data');
add_action('wp_ajax_spm_public_data',        'bntm_ajax_spm_public_data');

// ============================================================
// TAB: OVERVIEW
// ============================================================

function spm_overview_tab($business_id) {
    global $wpdb;

    $total_teams   = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_teams WHERE business_id=%d", $business_id)));
    $total_players = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_players WHERE business_id=%d", $business_id)));
    $total_games   = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_games WHERE business_id=%d", $business_id)));
    $total_stats   = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_player_stats WHERE business_id=%d", $business_id)));

    $recent_games = $wpdb->get_results($wpdb->prepare(
        "SELECT g.*, 
                COALESCE(ta.team_name, 'Unknown') AS team_a_name, COALESCE(ta.logo, '') AS team_a_logo,
                COALESCE(tb.team_name, 'Unknown') AS team_b_name, COALESCE(tb.logo, '') AS team_b_logo
         FROM {$wpdb->prefix}spm_games g
         LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id = ta.id
         LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id = tb.id
         WHERE g.business_id = %d
         ORDER BY g.game_date DESC LIMIT 5",
        $business_id
    ));

    $top_scorers = $wpdb->get_results($wpdb->prepare(
        "SELECT p.player_name, COALESCE(p.photo,'') AS player_photo, t.team_name,
                COUNT(s.id) AS games_played,
                SUM(s.points) AS total_points,
                ROUND(SUM(s.points)/NULLIF(COUNT(s.id),0),1) AS ppg
         FROM {$wpdb->prefix}spm_player_stats s
         LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id = p.id
         LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
         WHERE s.business_id = %d
         GROUP BY s.player_id
         ORDER BY ppg DESC LIMIT 5",
        $business_id
    ));

    ob_start();
    ?>
    <!-- STAT CARDS -->
    <div class="spm-stat-grid">
        <div class="spm-stat-card">
            <div class="spm-stat-card__icon spm-stat-card__icon--blue">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="spm-stat-card__body">
                <span class="spm-stat-card__label">TEAMS</span>
                <span class="spm-stat-card__value"><?php echo $total_teams; ?></span>
                <span class="spm-stat-card__sub">Registered</span>
            </div>
        </div>
        <div class="spm-stat-card">
            <div class="spm-stat-card__icon spm-stat-card__icon--red">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div class="spm-stat-card__body">
                <span class="spm-stat-card__label">PLAYERS</span>
                <span class="spm-stat-card__value"><?php echo $total_players; ?></span>
                <span class="spm-stat-card__sub">On Rosters</span>
            </div>
        </div>
        <div class="spm-stat-card">
            <div class="spm-stat-card__icon spm-stat-card__icon--gold">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="spm-stat-card__body">
                <span class="spm-stat-card__label">GAMES</span>
                <span class="spm-stat-card__value"><?php echo $total_games; ?></span>
                <span class="spm-stat-card__sub">Recorded</span>
            </div>
        </div>
        <div class="spm-stat-card">
            <div class="spm-stat-card__icon spm-stat-card__icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div class="spm-stat-card__body">
                <span class="spm-stat-card__label">STAT ENTRIES</span>
                <span class="spm-stat-card__value"><?php echo $total_stats; ?></span>
                <span class="spm-stat-card__sub">Performance Records</span>
            </div>
        </div>
    </div>

    <!-- RECENT GAMES + TOP SCORERS -->
    <div class="spm-two-col">
        <div class="spm-panel">
            <div class="spm-panel__header">
                <span class="spm-panel__title">Recent Games</span>
                <span class="spm-panel__sub">Latest recorded matches</span>
            </div>
            <?php if (empty($recent_games)): ?>
                <p class="spm-empty">No games recorded yet.</p>
            <?php else: ?>
                <div class="spm-game-list">
                    <?php foreach ($recent_games as $g): 
                        $a_wins = $g->team_a_score > $g->team_b_score;
                        $b_wins = $g->team_b_score > $g->team_a_score;
                    ?>
                    <div class="spm-game-row">
                        <div class="spm-game-row__teams">
                            <?php if (!empty($g->team_a_logo)): ?>
                                <img src="<?php echo esc_url($g->team_a_logo); ?>" style="width:26px;height:26px;border-radius:3px;object-fit:cover;flex-shrink:0;">
                            <?php else: ?>
                                <div style="width:26px;height:26px;border-radius:3px;background:var(--nba-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:10px;flex-shrink:0;"><?php echo strtoupper(substr($g->team_a_name,0,2)); ?></div>
                            <?php endif; ?>
                            <span class="spm-game-row__team <?php echo $a_wins ? 'spm-game-row__team--winner' : ''; ?>"><?php echo esc_html($g->team_a_name); ?></span>
                            <span class="spm-game-row__vs">vs</span>
                            <?php if (!empty($g->team_b_logo)): ?>
                                <img src="<?php echo esc_url($g->team_b_logo); ?>" style="width:26px;height:26px;border-radius:3px;object-fit:cover;flex-shrink:0;">
                            <?php else: ?>
                                <div style="width:26px;height:26px;border-radius:3px;background:var(--nba-red);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:10px;flex-shrink:0;"><?php echo strtoupper(substr($g->team_b_name,0,2)); ?></div>
                            <?php endif; ?>
                            <span class="spm-game-row__team <?php echo $b_wins ? 'spm-game-row__team--winner' : ''; ?>"><?php echo esc_html($g->team_b_name); ?></span>
                        </div>
                        <div class="spm-game-row__right">
                            <span class="spm-game-row__score"><?php echo $g->team_a_score; ?> &ndash; <?php echo $g->team_b_score; ?></span>
                            <span class="spm-game-row__date"><?php echo date('M d, Y', strtotime($g->game_date)); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="spm-panel">
            <div class="spm-panel__header">
                <span class="spm-panel__title">Top Scorers</span>
                <span class="spm-panel__sub">Points per game</span>
            </div>
            <?php if (empty($top_scorers)): ?>
                <p class="spm-empty">No stats recorded yet.</p>
            <?php else: ?>
                <div class="spm-leaders-list">
                    <?php foreach ($top_scorers as $i => $p): ?>
                    <div class="spm-leader-row">
                        <span class="spm-rank spm-rank--<?php echo $i < 3 ? ($i + 1) : 'other'; ?>"><?php echo $i + 1; ?></span>
                        <?php if (!empty($p->player_photo)): ?>
                            <img src="<?php echo esc_url($p->player_photo); ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid var(--nba-gray-2);">
                        <?php else: ?>
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--nba-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:12px;flex-shrink:0;"><?php echo strtoupper(substr($p->player_name,0,2)); ?></div>
                        <?php endif; ?>
                        <div class="spm-leader-row__info">
                            <span class="spm-leader-row__name"><?php echo esc_html($p->player_name); ?></span>
                            <span class="spm-leader-row__team"><?php echo esc_html($p->team_name); ?></span>
                        </div>
                        <div class="spm-leader-row__stat">
                            <span class="spm-leader-row__value"><?php echo $p->ppg; ?></span>
                            <span class="spm-leader-row__label">PPG</span>
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
// MAIN DASHBOARD SHORTCODE
// ============================================================

function bntm_shortcode_spm_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="spm-notice spm-notice--error">Please log in to access the Basketball Dashboard.</div>';
    }

    // Ensure database tables are created
    bntm_spm_create_tables();

    $business_id = get_current_user_id();
    $active_tab  = isset($_GET['spm_tab']) ? sanitize_text_field($_GET['spm_tab']) : 'overview';

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>

    <!-- GOOGLE FONTS: Barlow Condensed for NBA-style numerics, Barlow for body -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">

    <div class="spm-wrap">

        <!-- ===== DASHBOARD HEADER ===== -->
        <div class="spm-header">
            <div class="spm-header__brand">
                <div class="spm-header__ball">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" width="36" height="36">
                        <circle cx="20" cy="20" r="18" fill="#C9531F"/>
                        <path d="M20 2C20 2 14 8 14 20C14 32 20 38 20 38" stroke="#1a1a1a" stroke-width="1.5"/>
                        <path d="M20 2C20 2 26 8 26 20C26 32 20 38 20 38" stroke="#1a1a1a" stroke-width="1.5"/>
                        <path d="M2 20H38" stroke="#1a1a1a" stroke-width="1.5"/>
                        <path d="M3.5 13H36.5" stroke="#1a1a1a" stroke-width="1.2"/>
                        <path d="M3.5 27H36.5" stroke="#1a1a1a" stroke-width="1.2"/>
                    </svg>
                </div>
                <div>
                    <div class="spm-header__title">Basketball League Manager</div>
                    <div class="spm-header__sub">Dashboard</div>
                </div>
            </div>
        </div>

        <!-- ===== TAB NAVIGATION ===== -->
        <nav class="spm-nav">
            <div class="spm-nav__inner">
                <?php
                $tabs = [
                    'overview'  => ['label' => 'Overview',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    'teams'     => ['label' => 'Teams',          'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    'players'   => ['label' => 'Players',        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                    'games'     => ['label' => 'Games',          'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    'stats'     => ['label' => 'Player Stats',   'icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    'seasons'   => ['label' => 'Seasons',        'icon' => 'M3 7h18M3 12h18M3 17h18'],
                    'rankings'  => ['label' => 'Rankings',       'icon' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
                ];
                foreach ($tabs as $key => $tab): ?>
                <button class="spm-nav__btn <?php echo $active_tab === $key ? 'spm-nav__btn--active' : ''; ?>" data-tab="<?php echo $key; ?>">
                    <svg class="spm-nav__icon" width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $tab['icon']; ?>"/></svg>
                    <?php echo $tab['label']; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </nav>

        <!-- ===== TAB PANELS ===== -->
        <div class="spm-content">
            <div class="spm-panel-wrap <?php echo $active_tab === 'overview'  ? 'spm-panel-wrap--active' : ''; ?>" id="panel-overview"><?php echo spm_overview_tab($business_id); ?></div>
            <div class="spm-panel-wrap <?php echo $active_tab === 'teams'     ? 'spm-panel-wrap--active' : ''; ?>" id="panel-teams"><?php echo spm_teams_tab($business_id); ?></div>
            <div class="spm-panel-wrap <?php echo $active_tab === 'players'   ? 'spm-panel-wrap--active' : ''; ?>" id="panel-players"><?php echo spm_players_tab($business_id); ?></div>
            <div class="spm-panel-wrap <?php echo $active_tab === 'games'     ? 'spm-panel-wrap--active' : ''; ?>" id="panel-games"><?php echo spm_games_tab($business_id); ?></div>
            <div class="spm-panel-wrap <?php echo $active_tab === 'stats'     ? 'spm-panel-wrap--active' : ''; ?>" id="panel-stats"><?php echo spm_stats_tab($business_id); ?></div>
            <div class="spm-panel-wrap <?php echo $active_tab === 'seasons'   ? 'spm-panel-wrap--active' : ''; ?>" id="panel-seasons"><?php echo spm_seasons_tab($business_id); ?></div>
            <div class="spm-panel-wrap <?php echo $active_tab === 'rankings'  ? 'spm-panel-wrap--active' : ''; ?>" id="panel-rankings"><?php echo spm_rankings_tab($business_id); ?></div>
        </div>

    </div><!-- /.spm-wrap -->

    <!-- ===== GLOBAL MODAL ===== -->
    <div id="spm-modal-overlay" class="spm-overlay" style="display:none;">
        <div class="spm-modal" id="spm-modal">
            <div class="spm-modal__head">
                <h3 class="spm-modal__title" id="spm-modal-title"></h3>
                <button class="spm-modal__close" id="spm-modal-close">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="spm-modal__body" id="spm-modal-body"></div>
        </div>
    </div>

    <!-- ===== GLOBAL STYLES ===== -->
    <style>
    /* === NBA DESIGN SYSTEM === */
    :root {
        --nba-navy:   #1D428A;
        --nba-red:    #C8102E;
        --nba-gold:   #FFC72C;
        --nba-white:  #FFFFFF;
        --nba-black:  #0A0A0A;
        --nba-dark:   #111827;
        --nba-gray-1: #F7F7F7;
        --nba-gray-2: #EDEDED;
        --nba-gray-3: #D1D1D1;
        --nba-gray-4: #9A9A9A;
        --nba-gray-5: #555555;
        --nba-text:   #1A1A1A;
        --nba-radius: 4px;
        --font-display: 'Barlow Condensed', 'Arial Narrow', Arial, sans-serif;
        --font-body:    'Barlow', 'Helvetica Neue', Arial, sans-serif;
    }

    * { box-sizing: border-box; }

    .spm-wrap {
        font-family: var(--font-body);
        color: var(--nba-text);
        background: var(--nba-gray-1);
        margin: 0;
        padding: 0;
    }

    /* ===== HEADER ===== */
    .spm-header {
        background: var(--nba-black);
        padding: 14px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 3px solid var(--nba-red);
    }

    .spm-header__brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .spm-header__ball {
        flex-shrink: 0;
        display: flex;
        align-items: center;
    }

    .spm-header__title {
        font-family: var(--font-display);
        font-size: 26px;
        font-weight: 800;
        color: var(--nba-white);
        letter-spacing: 0.5px;
        text-transform: uppercase;
        line-height: 1.1;
    }

    .spm-header__sub {
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 500;
        color: var(--nba-gold);
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }

    /* ===== NAVIGATION ===== */
    .spm-nav {
        background: var(--nba-white);
        border-bottom: 1px solid var(--nba-gray-3);
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }

    .spm-nav__inner {
        display: flex;
        align-items: stretch;
        overflow-x: auto;
        padding: 0 16px;
        scrollbar-width: none;
    }

    .spm-nav__inner::-webkit-scrollbar { display: none; }

    .spm-nav__btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 16px 18px 14px;
        border: none;
        border-bottom: 3px solid transparent;
        background: none;
        cursor: pointer;
        font-family: var(--font-body);
        font-size: 15px;
        font-weight: 600;
        color: var(--nba-gray-4);
        white-space: nowrap;
        transition: color .15s, border-color .15s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: -1px;
    }

    .spm-nav__btn:hover {
        color: var(--nba-dark);
        border-bottom-color: var(--nba-gray-3);
    }

    .spm-nav__btn--active {
        color: var(--nba-navy) !important;
        border-bottom-color: var(--nba-red) !important;
        font-weight: 700;
    }

    .spm-nav__icon { flex-shrink: 0; }

    /* ===== CONTENT AREA ===== */
    .spm-content { padding: 24px; }

    .spm-panel-wrap { display: none; }
    .spm-panel-wrap--active { display: block; }

    /* ===== STAT CARDS ===== */
    .spm-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1px;
        background: var(--nba-gray-3);
        border: 1px solid var(--nba-gray-3);
        border-radius: var(--nba-radius);
        overflow: hidden;
        margin-bottom: 20px;
    }

    .spm-stat-card {
        background: var(--nba-white);
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: background .15s;
    }

    .spm-stat-card:hover { background: var(--nba-gray-1); }

    .spm-stat-card__icon {
        width: 60px;
        height: 60px;
        border-radius: 3px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 32px;
    }

    .spm-stat-card__icon--blue  { background: var(--nba-navy); color: #fff; }
    .spm-stat-card__icon--red   { background: var(--nba-red); color: #fff; }
    .spm-stat-card__icon--gold  { background: var(--nba-gold); color: #1a1a1a; }
    .spm-stat-card__icon--green { background: #1a6b38; color: #fff; }

    .spm-stat-card__body {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .spm-stat-card__label {
        font-family: var(--font-display);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1.5px;
        color: var(--nba-gray-4);
        text-transform: uppercase;
    }

    .spm-stat-card__value {
        font-family: var(--font-display);
        font-size: 44px;
        font-weight: 900;
        line-height: 1;
        color: var(--nba-dark);
        letter-spacing: -0.5px;
    }

    .spm-stat-card__sub {
        font-size: 13px;
        color: var(--nba-gray-4);
        font-weight: 500;
    }

    /* ===== TWO-COLUMN LAYOUT ===== */
    .spm-two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    /* ===== PANELS ===== */
    .spm-panel {
        background: var(--nba-white);
        border: 1px solid var(--nba-gray-3);
        border-radius: var(--nba-radius);
        overflow: hidden;
    }

    .spm-panel__header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        padding: 14px 18px 12px;
        border-bottom: 1px solid var(--nba-gray-2);
        background: var(--nba-white);
        flex-wrap: wrap;
        gap: 4px 10px;
    }

    .spm-panel__title {
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--nba-dark);
    }

    .spm-panel__sub {
        font-size: 13px;
        color: var(--nba-gray-4);
        font-weight: 500;
    }

    /* ===== SECTION HEADER (inside panels) ===== */
    .spm-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 18px;
        border-bottom: 1px solid var(--nba-gray-2);
        gap: 16px;
        flex-wrap: nowrap;
    }

    .spm-section-head h3 {
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: var(--nba-dark);
        margin: 0;
        line-height: 1.1;
    }

    .spm-section-head p {
        font-size: 13px;
        color: var(--nba-gray-4);
        margin: 0 0 0 8px;
        line-height: 1.2;
    }

    .spm-section-head > div {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 8px;
        flex: 1;
    }

    /* ===== GAME LIST ===== */
    .spm-game-list { padding: 8px 0; }

    .spm-game-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px;
        border-bottom: 1px solid var(--nba-gray-2);
        transition: background .12s;
    }

    .spm-game-row:last-child { border-bottom: none; }
    .spm-game-row:hover { background: var(--nba-gray-1); }

    .spm-game-row__teams {
        display: flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        flex: 1;
    }

    .spm-game-row__team {
        font-size: 15px;
        font-weight: 600;
        color: var(--nba-dark);
        white-space: nowrap;
    }

    .spm-game-row__team--winner { color: var(--nba-dark); font-weight: 700; }

    .spm-game-row__vs {
        font-size: 12px;
        font-weight: 700;
        color: var(--nba-gray-4);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex-shrink: 0;
    }

    .spm-game-row__right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        flex-shrink: 0;
        margin-left: 12px;
    }

    .spm-game-row__score {
        font-family: var(--font-display);
        font-size: 22px;
        font-weight: 800;
        color: var(--nba-dark);
        letter-spacing: -0.5px;
        line-height: 1;
    }

    .spm-game-row__date {
        font-size: 12px;
        color: var(--nba-gray-4);
        margin-top: 2px;
    }

    /* ===== LEADERS LIST ===== */
    .spm-leaders-list { padding: 8px 0; }

    .spm-leader-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--nba-gray-2);
        transition: background .12s;
    }

    .spm-leader-row:last-child { border-bottom: none; }
    .spm-leader-row:hover { background: var(--nba-gray-1); }

    .spm-leader-row__info {
        flex: 1;
        min-width: 0;
    }

    .spm-leader-row__name {
        display: block;
        font-size: 15px;
        font-weight: 700;
        color: var(--nba-dark);
        white-space: nowrap;
    }

    .spm-leader-row__team {
        display: block;
        font-size: 13px;
        color: var(--nba-gray-4);
        margin-top: 1px;
    }

    .spm-leader-row__stat {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .spm-leader-row__value {
        font-family: var(--font-display);
        font-size: 26px;
        font-weight: 900;
        color: var(--nba-navy);
        line-height: 1;
    }

    .spm-leader-row__label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        color: var(--nba-gray-4);
        text-transform: uppercase;
    }

    /* ===== RANK BADGES ===== */
    .spm-rank {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-display);
        font-weight: 800;
        font-size: 16px;
        flex-shrink: 0;
    }

    .spm-rank--1 { background: var(--nba-gold); color: #1a1a1a; }
    .spm-rank--2 { background: #c0c0c0; color: #333; }
    .spm-rank--3 { background: #cd7f32; color: #fff; }
    .spm-rank--other { background: var(--nba-gray-2); color: var(--nba-dark); }

    /* ===== DATA TABLE ===== */
    .spm-table-wrap {
        overflow-x: auto;
        background: var(--nba-white);
        border: 1px solid var(--nba-gray-3);
        border-radius: var(--nba-radius);
        margin-bottom: 0;
    }

    .spm-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: var(--nba-white);
    }

    .spm-table thead {
        background: var(--nba-dark);
    }

    .spm-table thead th {
        padding: 13px 16px;
        text-align: left;
        font-family: var(--font-display);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #ffffff;
        white-space: nowrap;
        border: none;
    }

    .spm-table thead th:first-child { border-left: 3px solid var(--nba-red); }

    .spm-table tbody tr {
        border-bottom: 1px solid var(--nba-gray-2);
        transition: background .1s;
    }

    .spm-table tbody tr:last-child { border-bottom: none; }
    .spm-table tbody tr:hover { background: var(--nba-gray-1); }

    .spm-table td {
        padding: 14px 16px;
        color: var(--nba-text);
        vertical-align: middle;
    }

    .spm-table .spm-table__num {
        font-family: var(--font-display);
        font-size: 18px;
        font-weight: 800;
        color: var(--nba-navy);
    }

    /* ===== SECTION BOX ===== */
    .spm-box {
        background: var(--nba-white);
        border: 1px solid var(--nba-gray-3);
        border-radius: var(--nba-radius);
        overflow: hidden;
        margin-bottom: 20px;
    }

    /* ===== SEARCH BAR ===== */
    .spm-search {
        padding: 12px 18px;
        border-bottom: 1px solid var(--nba-gray-2);
        background: var(--nba-gray-1);
    }

    .spm-search input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--nba-gray-3);
        border-radius: var(--nba-radius);
        font-family: var(--font-body);
        font-size: 13px;
        color: var(--nba-text);
        background: var(--nba-white);
        transition: border-color .15s, box-shadow .15s;
    }

    .spm-search input:focus {
        outline: none;
        border-color: var(--nba-navy);
        box-shadow: 0 0 0 2px rgba(29,66,138,0.12);
    }

    /* ===== BUTTONS ===== */
    .spm-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        border: none;
        border-radius: var(--nba-radius);
        cursor: pointer;
        font-family: var(--font-body);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: background .15s, transform .1s;
        text-decoration: none;
    }

    .spm-btn:active { transform: scale(0.97); }

    .spm-btn--primary {
        background: var(--nba-navy);
        color: #fff;
    }

    .spm-btn--primary:hover { background: #152f6e; }

    .spm-btn--secondary {
        background: var(--nba-gray-2);
        color: var(--nba-dark);
    }

    .spm-btn--secondary:hover { background: var(--nba-gray-3); }

    .spm-btn--sm {
        padding: 6px 12px;
        font-size: 12px;
    }

    .spm-btn--edit {
        background: #e8f0fc;
        color: var(--nba-navy);
    }

    .spm-btn--edit:hover { background: #cddaf8; }

    .spm-btn--danger {
        background: #fce8ea;
        color: var(--nba-red);
    }

    .spm-btn--danger:hover { background: #f9d0d4; }

    /* ===== BADGES ===== */
    .spm-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 2px;
        font-family: var(--font-display);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .spm-badge--blue    { background: #e8f0fc; color: var(--nba-navy); }
    .spm-badge--green   { background: #e6f4ed; color: #1a6b38; }
    .spm-badge--gray    { background: var(--nba-gray-2); color: var(--nba-dark); }
    .spm-badge--orange  { background: #fff3e0; color: #b45309; }
    .spm-badge--red     { background: #fce8ea; color: var(--nba-red); }

    /* ===== AVATAR ===== */
    .spm-avatar {
        width: 48px;
        height: 48px;
        border-radius: 3px;
        background: var(--nba-navy);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-family: var(--font-display);
        font-weight: 800;
        font-size: 16px;
        flex-shrink: 0;
    }

    /* ===== PLAYER NAME CELL ===== */
    .spm-player-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .spm-player-cell__info {}
    .spm-player-cell__name { font-weight: 700; font-size: 15px; display: block; }
    .spm-player-cell__sub  { font-size: 12px; color: var(--nba-gray-4); display: block; margin-top: 1px; }

    .spm-player-name--clickable {
        color: var(--nba-navy);
        cursor: pointer;
        transition: opacity 0.15s ease;
    }

    .spm-player-cell:hover .spm-player-name--clickable {
        opacity: 0.75;
        font-weight: 800;
    }

    /* ===== FORM STYLES ===== */
    .spm-form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
        margin-bottom: 14px;
    }

    .spm-form-group label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--nba-dark);
    }

    .spm-form-group input,
    .spm-form-group select,
    .spm-form-group textarea {
        padding: 10px 13px;
        border: 1px solid var(--nba-gray-3);
        border-radius: var(--nba-radius);
        font-family: var(--font-body);
        font-size: 14px;
        color: var(--nba-text);
        background: var(--nba-white);
        transition: border-color .15s, box-shadow .15s;
        width: 100%;
    }

    .spm-form-group input:focus,
    .spm-form-group select:focus,
    .spm-form-group textarea:focus {
        outline: none;
        border-color: var(--nba-navy);
        box-shadow: 0 0 0 2px rgba(29,66,138,0.12);
    }

    .spm-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .spm-form-row--3 { grid-template-columns: 1fr 1fr 1fr; }

    /* ===== NOTICES ===== */
    .spm-notice {
        padding: 12px 16px;
        border-radius: var(--nba-radius);
        font-size: 14px;
        font-weight: 600;
        border-left: 3px solid;
        margin: 10px 0;
    }

    .spm-notice--success { background: #e6f4ed; color: #1a6b38; border-left-color: #2e7d32; }
    .spm-notice--error   { background: #fce8ea; color: var(--nba-red); border-left-color: var(--nba-red); }

    /* ===== EMPTY STATE ===== */
    .spm-empty {
        padding: 40px 32px;
        text-align: center;
        color: var(--nba-gray-4);
        font-size: 14px;
        font-style: italic;
        margin: 0;
    }

    /* ===== PLAYER PROFILE ===== */
    .spm-profile {
        background: linear-gradient(135deg, var(--team-primary, var(--nba-navy)) 0%, color-mix(in srgb, var(--team-primary, var(--nba-navy)) 80%, black) 100%);
        color: #fff;
        padding: 0;
        border-radius: 6px;
        overflow: hidden;
        max-height: 85vh;
        overflow-y: auto;
    }

    .spm-profile__hero {
        padding: 28px 28px 28px 60px;
        grid-template-columns: 140px 1fr;
        gap: 40px;
        align-items: flex-start;
    }

    .spm-profile__photo {
        width: 140px;
        height: 140px;
        border-radius: 8px;
        object-fit: cover;
        border: 5px solid var(--team-secondary, var(--nba-gold));
        margin-left: 8px;
    }

    .spm-profile__avatar {
        width: 140px;
        height: 140px;
        border-radius: 8px;
        background: rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-display);
        font-size: 56px;
        font-weight: 900;
        color: var(--team-secondary, var(--nba-gold));
        border: 5px solid var(--team-secondary, var(--nba-gold));
        flex-shrink: 0;
        margin-left: 8px;
    }

    .spm-profile__info h2 {
        font-family: var(--font-display);
        font-size: 42px;
        font-weight: 900;
        margin: 0 0 8px 0;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        line-height: 1.1;
        -webkit-text-stroke: 1.5px var(--team-outline, white);
        text-stroke: 1.5px var(--team-outline, white);
        paint-order: stroke fill;
    }

    .spm-profile__info p {
        font-size: 17px;
        margin: 4px 0;
        opacity: 0.95;
        font-weight: 500;
    }

    .spm-profile__info .spm-profile__team {
        font-size: 19px;
        font-weight: 700;
        color: var(--team-secondary, var(--nba-gold));
        margin-top: 10px;
        font-family: var(--font-display);
    }

    .spm-profile__details {
        background: rgba(255,255,255,0.05);
        padding: 28px 28px 28px 40px;
        margin: 0;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .spm-profile__detail-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .spm-profile__detail-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .spm-profile__detail-label {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        color: rgba(255,255,255,0.8);
        margin-bottom: 8px;
    }

    .spm-profile__detail-value {
        font-family: var(--font-display);
        font-size: 36px;
        font-weight: 900;
        color: var(--team-secondary, var(--nba-gold));
        line-height: 1;
        -webkit-text-stroke: 0.8px var(--team-outline, white);
        text-stroke: 0.8px var(--team-outline, white);
        paint-order: stroke fill;
    }

    .spm-profile__stats {
        background: rgba(0,0,0,0.2);
        padding: 28px 28px 28px 40px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .spm-profile__stats-title {
        font-family: var(--font-display);
        font-size: 18px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 18px;
        color: var(--team-secondary, var(--nba-gold));
        -webkit-text-stroke: 0.6px var(--team-outline, white);
        text-stroke: 0.6px var(--team-outline, white);
        paint-order: stroke fill;
    }

    .spm-profile__stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .spm-profile__stat-box {
        background: rgba(255,255,255,0.08);
        padding: 20px 16px;
        border-radius: 6px;
        text-align: center;
        border: 1.5px solid rgba(255,255,255,0.15);
    }

    .spm-profile__stat-value {
        font-family: var(--font-display);
        font-size: 32px;
        font-weight: 900;
        display: block;
        margin-bottom: 6px;
        line-height: 1;
        color: #ffffff;
    }

    .spm-profile__stat-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: rgba(255,255,255,0.85);
    }

    .spm-profile__footer {
        padding: 16px 24px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .spm-modal--profile .spm-modal {
        max-width: 700px;
        background: transparent;
        box-shadow: none;
        padding: 0;
    }

    .spm-modal--profile .spm-modal__head {
        display: none;
    }

    .spm-modal--profile .spm-modal__body {
        padding: 0;
        background: transparent;
        border-radius: 6px;
        overflow: hidden;
    }

    @media (max-width: 900px) {
        .spm-profile__detail-grid,
        .spm-profile__stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* ===== MODAL ===== */
    .spm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        backdrop-filter: blur(2px);
    }

    .spm-modal {
        background: var(--nba-white);
        border-radius: 6px;
        width: 100%;
        max-width: 580px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }

    .spm-modal__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        background: var(--nba-dark);
        border-bottom: 3px solid var(--nba-red);
    }

    .spm-modal__title {
        font-family: var(--font-display);
        font-size: 16px;
        font-weight: 800;
        color: var(--nba-white);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin: 0;
    }

    .spm-modal__close {
        background: none;
        border: none;
        cursor: pointer;
        color: rgba(255,255,255,0.6);
        padding: 4px;
        display: flex;
        align-items: center;
        transition: color .15s;
    }

    .spm-modal__close:hover { color: #fff; }

    .spm-modal__body { padding: 20px; }

    .spm-modal__footer {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        padding: 14px 20px;
        border-top: 1px solid var(--nba-gray-2);
        margin-top: 8px;
    }

    /* ===== RANKINGS GRID ===== */
    .spm-rankings-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    /* ===== SCORE DISPLAY ===== */
    .spm-score {
        font-family: var(--font-display);
        font-size: 20px;
        font-weight: 900;
        color: var(--nba-dark);
        letter-spacing: -0.5px;
    }

    .spm-score-winner { color: var(--nba-navy); }
    .spm-score-sub { font-size: 11px; color: #1a6b38; font-weight: 600; margin-top: 2px; }

    /* ===== STATUS DOTS ===== */
    .spm-status-dot {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .spm-status-dot::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .spm-status-dot--completed { color: #1a6b38; }
    .spm-status-dot--completed::before { background: #2e7d32; }
    .spm-status-dot--scheduled { color: var(--nba-navy); }
    .spm-status-dot--scheduled::before { background: var(--nba-navy); }
    .spm-status-dot--cancelled { color: var(--nba-gray-4); }
    .spm-status-dot--cancelled::before { background: var(--nba-gray-3); }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
        .spm-stat-grid { grid-template-columns: 1fr 1fr; }
        .spm-two-col, .spm-rankings-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 600px) {
        .spm-stat-grid { grid-template-columns: 1fr; gap: 1px; }
        .spm-content { padding: 16px; }
        .spm-form-row, .spm-form-row--3 { grid-template-columns: 1fr; }
        .spm-header { padding: 12px 16px; }
        .spm-table th, .spm-table td { padding: 10px; }
    }
    </style>

    <!-- ===== GLOBAL JS ===== -->
    <script>
    // Tab switching
    document.querySelectorAll('.spm-nav__btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.spm-nav__btn').forEach(function(b) { b.classList.remove('spm-nav__btn--active'); });
            document.querySelectorAll('.spm-panel-wrap').forEach(function(p) { p.classList.remove('spm-panel-wrap--active'); });
            this.classList.add('spm-nav__btn--active');
            var panel = document.getElementById('panel-' + this.dataset.tab);
            if (panel) panel.classList.add('spm-panel-wrap--active');
        });
    });

    // Modal helpers
    function spmOpenModal(title, html) {
        document.getElementById('spm-modal-title').textContent = title;
        document.getElementById('spm-modal-body').innerHTML = html;
        document.getElementById('spm-modal-overlay').style.display = 'flex';
        document.querySelector('.spm-modal').classList.remove('spm-modal--profile');
    }

    function spmOpenPlayerProfile(playerId) {
        spmAjax({action:'spm_get_player_profile',id:playerId,nonce:spmPlayerNonce}, function(res){
            if(res.success) {
                var html = res.data.html;
                document.getElementById('spm-modal-body').innerHTML = html;
                document.getElementById('spm-modal-overlay').style.display = 'flex';
                document.querySelector('.spm-modal').classList.add('spm-modal--profile');
            } else {
                spmNotice('spm-players-notice', res.data.message || 'Error loading player profile', 'error');
            }
        });
    }

    function spmCloseModal() {
        document.getElementById('spm-modal-overlay').style.display = 'none';
        document.querySelector('.spm-modal').classList.remove('spm-modal--profile');
    }

    document.getElementById('spm-modal-close').addEventListener('click', spmCloseModal);
    document.getElementById('spm-modal-overlay').addEventListener('click', function(e) {
        if (e.target === this) spmCloseModal();
    });

    // AJAX helper with file support
    function spmAjax(data, callback) {
        var hasFile = Object.values(data).some(function(v) { return v instanceof File; });
        if (hasFile) {
            var fd = new FormData();
            Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(callback)
                .catch(function(e) { console.error(e); });
        } else {
            var fd = new FormData();
            Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
            fetch(ajaxurl, { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(callback)
                .catch(function(e) { console.error(e); });
        }
    }

    // Notice helper
    function spmNotice(id, msg, type) {
        var el = document.getElementById(id);
        if (el) {
            el.innerHTML = '<div class="spm-notice spm-notice--' + type + '">' + msg + '</div>';
        }
    }

    // Confirm delete
    function spmConfirmDelete(msg, cb) {
        if (window.confirm(msg)) cb();
    }
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================
// TAB: SEASONS
// ============================================================

function spm_seasons_tab($business_id) {
    global $wpdb;
    $seasons = $wpdb->get_results($wpdb->prepare("SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}spm_games WHERE business_id=%d AND season=s.season_name) AS game_count FROM {$wpdb->prefix}spm_seasons s WHERE s.business_id=%d ORDER BY COALESCE(s.start_date,'9999-12-31') DESC, s.id DESC", $business_id, $business_id));
    $nonce = wp_create_nonce('spm_season_nonce');
    ob_start();
    ?>
    <div class="spm-box">
        <div class="spm-section-head">
            <div>
                <h3>Seasons</h3>
                <p>Create and manage league seasons</p>
            </div>
            <button class="spm-btn spm-btn--primary" onclick="spmOpenSeasonModal(0)">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Add Season
            </button>
        </div>
        <div id="spm-seasons-notice" style="padding:0 18px;"></div>
        <div class="spm-search"><input id="spm-seasons-search" placeholder="Search seasons by name…"></div>
        <div class="spm-table-wrap">
            <table class="spm-table" id="spm-seasons-table">
                <thead><tr><th>Season</th><th>Start Date</th><th>End Date</th><th>Games</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($seasons)): ?>
                    <tr><td colspan="5"><p class="spm-empty">No seasons yet. Create your first season.</p></td></tr>
                    <?php else: foreach ($seasons as $s): ?>
                    <tr id="season-row-<?php echo $s->id; ?>">
                        <td style="font-weight:700;"><?php echo esc_html($s->season_name); ?></td>
                        <td style="color:var(--nba-gray-5);font-size:13px;"><?php echo $s->start_date ? date('M d, Y', strtotime($s->start_date)) : '—'; ?></td>
                        <td style="color:var(--nba-gray-5);font-size:13px;"><?php echo $s->end_date ? date('M d, Y', strtotime($s->end_date)) : '—'; ?></td>
                        <td><span class="spm-badge spm-badge--blue"><?php echo $s->game_count; ?></span></td>
                        <td style="display:flex;gap:6px;">
                            <button class="spm-btn spm-btn--sm spm-btn--edit" onclick="spmOpenSeasonModal(<?php echo $s->id; ?>)">Edit</button>
                            <button class="spm-btn spm-btn--sm spm-btn--danger" onclick="spmDeleteSeason(<?php echo $s->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    var spmSeasonNonce = '<?php echo $nonce; ?>';
    function spmOpenSeasonModal(seasonId) {
        if (seasonId === 0) { spmOpenModal('Add Season', spmSeasonForm(null)); }
        else { spmAjax({ action: 'spm_get_season', id: seasonId, nonce: spmSeasonNonce }, function(res) { if (res.success) spmOpenModal('Edit Season', spmSeasonForm(res.data)); }); }
    }
    function spmSeasonForm(d) {
        var s = d || {};
        return '<div class="spm-form-group"><label>Season Name *</label><input type="text" id="spm-season-name" value="' + (s.season_name||'') + '" placeholder="e.g. 2025–2026"></div>' +
               '<div class="spm-form-row"><div class="spm-form-group"><label>Start Date</label><input type="date" id="spm-season-start" value="' + (s.start_date||'') + '"></div>' +
               '<div class="spm-form-group"><label>End Date</label><input type="date" id="spm-season-end" value="' + (s.end_date||'') + '"></div></div>' +
               '<div id="spm-season-form-notice"></div>' +
               '<div class="spm-modal__footer"><button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Cancel</button><button class="spm-btn spm-btn--primary" onclick="spmSaveSeason(' + (s.id||0) + ')">Save Season</button></div>';
    }
    function spmSaveSeason(id) {
        var name = document.getElementById('spm-season-name').value.trim();
        if (!name) { spmNotice('spm-season-form-notice','Season name is required.','error'); return; }
        var startDate = document.getElementById('spm-season-start').value;
        var endDate = document.getElementById('spm-season-end').value;
        spmAjax({ action:'spm_save_season', nonce:spmSeasonNonce, id:id, season_name:name, start_date:startDate, end_date:endDate }, function(res){ 
            if(res.success){
                spmCloseModal();
                setTimeout(function() { location.reload(); }, 300);
            } else {
                console.error('Error saving season:', res);
                spmNotice('spm-season-form-notice', (res.data && res.data.message) ? res.data.message : 'Error saving season', 'error');
            }
        });
    }
    function spmDeleteSeason(id, nonce) { spmConfirmDelete('Delete season and unassign games from it?', function(){ spmAjax({action:'spm_delete_season',id:id,nonce:nonce}, function(res){ if(res.success){var r=document.getElementById('season-row-'+id);if(r)r.remove();spmNotice('spm-seasons-notice',res.data.message,'success');}else spmNotice('spm-seasons-notice',res.data.message,'error'); }); }); }
    
    // Search functionality
    (function(){
        var ss=document.getElementById('spm-seasons-search');
        if(ss)ss.addEventListener('input',function(){
            var q=this.value.toLowerCase().trim();
            document.querySelectorAll('#spm-seasons-table tbody tr[id^="season-row-"]').forEach(function(row){
                if(!q){row.style.display='';return;}
                row.style.display=row.textContent.toLowerCase().indexOf(q)!==-1?'':'none';
            });
        });
    })();
    </script>
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
    <div class="spm-box">
        <div class="spm-section-head">
            <div><h3>Teams</h3><p>Manage all basketball teams</p></div>
            <button class="spm-btn spm-btn--primary" onclick="spmOpenTeamModal(0)">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Add Team
            </button>
        </div>
        <div id="spm-teams-notice" style="padding:0 18px;"></div>
        <div class="spm-search"><input id="spm-teams-search" placeholder="Search teams by name, city, coach…"></div>
        <div class="spm-table-wrap">
            <table class="spm-table" id="spm-teams-table">
                <thead><tr><th>Team</th><th>City</th><th>Coach</th><th>Players</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($teams)): ?>
                    <tr><td colspan="5"><p class="spm-empty">No teams found. Add your first team.</p></td></tr>
                    <?php else: foreach ($teams as $team): ?>
                    <tr id="team-row-<?php echo $team->id; ?>">
                        <td>
                            <div class="spm-player-cell" style="cursor:pointer;" onclick="spmOpenTeamProfile(<?php echo $team->id; ?>)">
                                <?php if (!empty($team->logo)): ?>
                                    <img src="<?php echo esc_url($team->logo); ?>" style="width:38px;height:38px;border-radius:3px;object-fit:cover;flex-shrink:0;">
                                <?php else: ?>
                                    <div class="spm-avatar"><?php echo strtoupper(substr($team->team_name,0,2)); ?></div>
                                <?php endif; ?>
                                <span class="spm-player-cell__name spm-clickable-name" style="font-weight:700; color: var(--nba-navy);"><?php echo esc_html($team->team_name); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($team->city ?: '—'); ?></td>
                        <td><?php echo esc_html($team->coach ?: '—'); ?></td>
                        <td><span class="spm-badge spm-badge--blue"><?php echo $team->player_count; ?> players</span></td>
                        <td style="display:flex;gap:6px;">
                            <button class="spm-btn spm-btn--sm spm-btn--edit" onclick="spmOpenTeamModal(<?php echo $team->id; ?>)">Edit</button>
                            <button class="spm-btn spm-btn--sm spm-btn--danger" onclick="spmDeleteTeam(<?php echo $team->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
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
        if(teamId===0){spmOpenModal('Add Team',spmTeamForm(null));}
        else{spmAjax({action:'spm_get_team',id:teamId,nonce:spmTeamNonce},function(res){if(res.success)spmOpenModal('Edit Team',spmTeamForm(res.data));});}
    }
    function spmOpenTeamProfile(teamId) {
        spmAjax({action:'spm_get_team_profile',id:teamId,nonce:spmTeamNonce},function(res){
            if(res.success){
                var html = res.data.html;
                document.getElementById('spm-modal-body').innerHTML = html;
                document.getElementById('spm-modal-overlay').style.display = 'flex';
                document.querySelector('.spm-modal').classList.add('spm-modal--profile');
            } else {
                spmNotice('spm-teams-notice', res.data.message || 'Error loading team profile', 'error');
            }
        });
    }
    function spmTeamForm(data) {
        var d = data || {};
        var logoPreview = d.logo
            ? '<img id="spm-team-logo-preview" src="'+d.logo+'" style="width:64px;height:64px;object-fit:cover;border-radius:4px;display:inline-block;border:1px solid var(--nba-gray-3);">'
            : '<img id="spm-team-logo-preview" style="display:none;width:64px;height:64px;object-fit:cover;border-radius:4px;border:1px solid var(--nba-gray-3);">';
        var primaryColor = d.primary_color || '#1F2D6D';
        var secondaryColor = d.secondary_color || '#FFD700';
        var outlineColor = d.outline_color || '#FFFFFF';
        return '<div class="spm-form-group"><label>Team Name *</label><input type="text" id="spm-team-name" value="'+(d.team_name||'')+'" placeholder="e.g. City Bulls"></div>' +
               '<div class="spm-form-row"><div class="spm-form-group"><label>City</label><input type="text" id="spm-team-city" value="'+(d.city||'')+'" placeholder="City"></div>' +
               '<div class="spm-form-group"><label>Coach</label><input type="text" id="spm-team-coach" value="'+(d.coach||'')+'" placeholder="Coach name"></div></div>' +
               '<div class="spm-form-group"><label>Team Logo</label>'+
               '<div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">'+logoPreview+
               '<label class="spm-btn spm-btn--secondary spm-btn--sm" style="cursor:pointer;margin:0;">'+
               '<svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'+
               'Upload Logo<input type="file" id="spm-team-logo-file" accept="image/*" style="display:none;" onchange="(function(el){var p=document.getElementById(\'spm-team-logo-preview\');if(el.files&&el.files[0]){p.src=URL.createObjectURL(el.files[0]);p.style.display=\'inline-block\';document.getElementById(\'spm-team-logo\').value=\'\';}})(this)"></label>'+
               '</div>'+
               '<div class="spm-form-group" style="margin-bottom:0;"><label>Or paste logo URL</label><input type="text" id="spm-team-logo" value="'+(d.logo||'')+'" placeholder="https://…" onchange="(function(v){if(v){var p=document.getElementById(\'spm-team-logo-preview\');p.src=v;p.style.display=\'inline-block\';}})(this.value)"></div></div>' +
               '<div class="spm-form-row"><div class="spm-form-group"><label>Primary Color (Background)</label><div style="display:flex;align-items:center;gap:8px;"><input type="color" id="spm-team-primary-color" value="'+primaryColor+'" style="width:50px;height:40px;border:1px solid var(--nba-gray-3);border-radius:4px;cursor:pointer;"><code style="font-size:12px;">'+primaryColor+'</code></div></div>' +
               '<div class="spm-form-group"><label>Secondary Color (Accent/Text)</label><div style="display:flex;align-items:center;gap:8px;"><input type="color" id="spm-team-secondary-color" value="'+secondaryColor+'" style="width:50px;height:40px;border:1px solid var(--nba-gray-3);border-radius:4px;cursor:pointer;"><code style="font-size:12px;">'+secondaryColor+'</code></div></div></div>' +
               '<div class="spm-form-group"><label>Text Outline Color</label><div style="display:flex;align-items:center;gap:8px;"><input type="color" id="spm-team-outline-color" value="'+outlineColor+'" style="width:50px;height:40px;border:1px solid var(--nba-gray-3);border-radius:4px;cursor:pointer;"><code style="font-size:12px;">'+outlineColor+'</code></div></div>' +
               '<div id="spm-team-form-notice"></div>' +
               '<div class="spm-modal__footer"><button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Cancel</button><button class="spm-btn spm-btn--primary" onclick="spmSaveTeam('+(d.id||0)+')">Save Team</button></div>';
    }
    function spmSaveTeam(id) {
        var name=document.getElementById('spm-team-name').value.trim();
        if(!name){spmNotice('spm-team-form-notice','Team name is required.','error');return;}
        var logoFile=document.getElementById('spm-team-logo-file').files[0];
        var payload={action:'spm_save_team',nonce:spmTeamNonce,id:id,team_name:name,city:document.getElementById('spm-team-city').value.trim(),coach:document.getElementById('spm-team-coach').value.trim(),logo:document.getElementById('spm-team-logo').value.trim(),primary_color:document.getElementById('spm-team-primary-color').value,secondary_color:document.getElementById('spm-team-secondary-color').value,outline_color:document.getElementById('spm-team-outline-color').value};
        if(logoFile)payload.logo_file=logoFile;
        spmAjax(payload,function(res){if(res.success){spmCloseModal();location.reload();}else spmNotice('spm-team-form-notice',res.data.message,'error');});
    }
    function spmDeleteTeam(id,nonce){spmConfirmDelete('Delete this team? Players assigned will become unassigned.',function(){spmAjax({action:'spm_delete_team',id:id,nonce:nonce},function(res){if(res.success){var row=document.getElementById('team-row-'+id);if(row)row.remove();spmNotice('spm-teams-notice',res.data.message,'success');}else spmNotice('spm-teams-notice',res.data.message,'error');});});}
    (function(){var st=document.getElementById('spm-teams-search');if(st)st.addEventListener('input',function(){var q=this.value.toLowerCase().trim();document.querySelectorAll('#spm-teams-table tbody tr').forEach(function(row){if(!q){row.style.display='';return;}row.style.display=row.textContent.toLowerCase().indexOf(q)!==-1?'':'none';});});})();
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
        SELECT p.*, t.team_name FROM {$wpdb->prefix}spm_players p
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE p.business_id = %d ORDER BY p.player_name ASC
    ", $business_id));
    $teams = $wpdb->get_results($wpdb->prepare("SELECT id, team_name FROM {$wpdb->prefix}spm_teams WHERE business_id=%d ORDER BY team_name ASC", $business_id));
    $teams_json = json_encode(array_map(function($t){ return ['id'=>$t->id,'name'=>$t->team_name]; }, $teams));
    $nonce = wp_create_nonce('spm_player_nonce');
    ob_start();
    ?>
    <div class="spm-box">
        <div class="spm-section-head">
            <div><h3>Players</h3><p>Manage player rosters and assignments</p></div>
            <button class="spm-btn spm-btn--primary" onclick="spmOpenPlayerModal(0)">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Add Player
            </button>
        </div>
        <div id="spm-players-notice" style="padding:0 18px;"></div>
        <div class="spm-search"><input id="spm-players-search" placeholder="Search players by name, team, position…"></div>
        <div class="spm-table-wrap">
            <table class="spm-table" id="spm-players-table">
                <thead><tr><th>Player</th><th>Team</th><th>#</th><th>Position</th><th>Height</th><th>Weight</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($players)): ?>
                    <tr><td colspan="8"><p class="spm-empty">No players found. Add your first player.</p></td></tr>
                    <?php else: foreach ($players as $pl): ?>
                    <tr id="player-row-<?php echo $pl->id; ?>">
                        <td>
                            <div class="spm-player-cell" style="cursor:pointer;" onclick="spmOpenPlayerProfile(<?php echo $pl->id; ?>)">
                                <?php if (!empty($pl->photo)): ?>
                                    <img src="<?php echo esc_url($pl->photo); ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                <?php else: ?>
                                    <div class="spm-avatar" style="width:48px;height:48px;"><?php echo strtoupper(substr($pl->player_name,0,2)); ?></div>
                                <?php endif; ?>
                                <div class="spm-player-cell__info">
                                    <span class="spm-player-cell__name spm-player-name--clickable"><?php echo esc_html($pl->player_name); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo esc_html($pl->team_name ?: '—'); ?></td>
                        <td><span class="spm-table__num"><?php echo $pl->jersey_number !== '' && $pl->jersey_number !== null ? $pl->jersey_number : '—'; ?></span></td>
                        <td><?php echo esc_html($pl->position ?: '—'); ?></td>
                        <td><?php echo esc_html($pl->height ?: '—'); ?></td>
                        <td><?php echo intval($pl->weight) > 0 ? intval($pl->weight) . ' lbs' : '—'; ?></td>
                        <td>
                            <span class="spm-badge <?php echo $pl->status === 'active' ? 'spm-badge--green' : 'spm-badge--gray'; ?>">
                                <?php echo ucfirst($pl->status); ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:6px;">
                            <button class="spm-btn spm-btn--sm spm-btn--edit" onclick="spmOpenPlayerModal(<?php echo $pl->id; ?>)">Edit</button>
                            <button class="spm-btn spm-btn--sm spm-btn--danger" onclick="spmDeletePlayer(<?php echo $pl->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    var spmPlayerNonce='<?php echo $nonce; ?>';
    var spmTeamsData=<?php echo $teams_json; ?>;

    function spmTeamOptions(selectedId){
        var html='<option value="">-- Select Team --</option>';
        spmTeamsData.forEach(function(t){html+='<option value="'+t.id+'"'+(t.id==selectedId?' selected':'')+'>'+t.name+'</option>';});
        return html;
    }

    function spmOpenPlayerModal(playerId){
        if(playerId===0){spmOpenModal('Add Player',spmPlayerForm(null));}
        else{spmAjax({action:'spm_get_player',id:playerId,nonce:spmPlayerNonce},function(res){if(res.success)spmOpenModal('Edit Player',spmPlayerForm(res.data));});}
    }

    // Parse stored height string (e.g. "6 ft 2 in" or "6'2\"") into {ft, in}
    function spmParseHeight(h) {
        if (!h) return {ft:'', in:''};
        // Match "6 ft 2 in" format
        var m = h.match(/(\d+)\s*ft\s*(\d*)/i);
        if (m) return {ft: m[1], in: m[2]||''};
        // Match "6'2" or "6'2\"" format
        m = h.match(/(\d+)['"'](\d*)/);
        if (m) return {ft: m[1], in: m[2]||''};
        // Just a number
        m = h.match(/^(\d+)$/);
        if (m) return {ft: m[1], in:''};
        return {ft:'', in:''};
    }

    function spmPlayerForm(data){
        var d=data||{};
        var hp = spmParseHeight(d.height||'');
        // jersey: only show if not 0 or empty
        var jerseyVal = (d.jersey_number !== undefined && d.jersey_number !== null && d.jersey_number !== 0 && d.jersey_number !== '0') ? d.jersey_number : '';
        var weightVal = (d.weight && parseInt(d.weight) > 0) ? parseInt(d.weight) : '';
        var photoHtml = d.photo
            ? '<img id="bp-photo-preview" src="'+d.photo+'" style="width:64px;height:64px;object-fit:cover;border-radius:50%;display:inline-block;border:2px solid var(--nba-gray-3);">'
            : '<img id="bp-photo-preview" style="display:none;width:64px;height:64px;object-fit:cover;border-radius:50%;border:2px solid var(--nba-gray-3);">';

        return '<div class="spm-form-group"><label>Player Name *</label><input type="text" id="bp-name" value="'+(d.player_name||'')+'" placeholder="Full name"></div>'+
               '<div class="spm-form-group"><label>Profile Photo</label>'+
               '<div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">'+photoHtml+
               '<label class="spm-btn spm-btn--secondary spm-btn--sm" style="cursor:pointer;margin:0;">'+
               '<svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'+
               'Choose Photo<input type="file" id="bp-photo-file" accept="image/*" style="display:none;" onchange="(function(el){var p=document.getElementById(\'bp-photo-preview\');if(el.files&&el.files[0]){p.src=URL.createObjectURL(el.files[0]);p.style.display=\'inline-block\';}})(this)"></label>'+
               '</div></div>'+
               '<div class="spm-form-group"><label>Team</label><select id="bp-team">'+spmTeamOptions(d.team_id||0)+'</select></div>'+
               '<div class="spm-form-row"><div class="spm-form-group"><label>Jersey #</label><input type="number" id="bp-jersey" value="'+jerseyVal+'" min="0" placeholder="—"></div>'+
               '<div class="spm-form-group"><label>Position</label><select id="bp-position"><option value="">—</option>'+['PG','SG','SF','PF','C'].map(function(p){return'<option value="'+p+'"'+(d.position===p?' selected':'')+'>'+p+'</option>';}).join('')+'</select></div></div>'+
               '<div class="spm-form-group"><label>Height</label>'+
               '<div style="display:flex;align-items:center;gap:8px;">'+
               '<input type="number" id="bp-height-ft" value="'+hp.ft+'" min="0" max="9" placeholder="ft" style="width:80px;">'+
               '<span style="color:var(--nba-gray-4);font-size:13px;font-weight:600;">ft</span>'+
               '<input type="number" id="bp-height-in" value="'+hp.in+'" min="0" max="11" placeholder="in" style="width:80px;">'+
               '<span style="color:var(--nba-gray-4);font-size:13px;font-weight:600;">in</span>'+
               '</div></div>'+
               '<div class="spm-form-group"><label>Weight (lbs)</label><input type="number" id="bp-weight" value="'+weightVal+'" min="0" placeholder="—"></div>'+
               '<div class="spm-form-group"><label>Status</label><select id="bp-status"><option value="active"'+(d.status==='active'||!d.status?' selected':'')+'>Active</option><option value="inactive"'+(d.status==='inactive'?' selected':'')+'>Inactive</option></select></div>'+
               '<div id="spm-player-form-notice"></div>'+
               '<div class="spm-modal__footer"><button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Cancel</button><button class="spm-btn spm-btn--primary" onclick="spmSavePlayer('+(d.id||0)+')">Save Player</button></div>';
    }

    function spmSavePlayer(id){
        var name=document.getElementById('bp-name').value.trim();
        if(!name){spmNotice('spm-player-form-notice','Player name is required.','error');return;}
        // Build height string from ft/in fields
        var ftVal = document.getElementById('bp-height-ft').value.trim();
        var inVal = document.getElementById('bp-height-in').value.trim();
        var heightStr = '';
        if (ftVal !== '') { heightStr = ftVal + ' ft'; if (inVal !== '') heightStr += ' ' + inVal + ' in'; }
        else if (inVal !== '') { heightStr = inVal + ' in'; }
        var jerseyRaw = document.getElementById('bp-jersey').value;
        var jerseyVal = jerseyRaw === '' ? '' : parseInt(jerseyRaw);
        var weightRaw = document.getElementById('bp-weight').value;
        var photoFile=document.getElementById('bp-photo-file') ? document.getElementById('bp-photo-file').files[0] : null;
        // Get the current preview image src to preserve existing photo if no new file is selected
        var photoPreview = document.getElementById('bp-photo-preview');
        var existingPhotoUrl = photoPreview && photoPreview.src && !photoPreview.src.startsWith('blob:') ? photoPreview.src : '';
        var payload={action:'spm_save_player',nonce:spmPlayerNonce,id:id,player_name:name,
            team_id:document.getElementById('bp-team').value,
            jersey_number:jerseyVal,
            position:document.getElementById('bp-position').value,
            height:heightStr,
            weight:weightRaw===''?'0':weightRaw,
            status:document.getElementById('bp-status').value};
        // Include existing photo URL if no new file is selected
        if(!photoFile && existingPhotoUrl) { payload.photo_url = existingPhotoUrl; }
        if(photoFile)payload.photo_file=photoFile;
        spmAjax(payload,function(res){
            if(res.success){
                spmCloseModal();
                setTimeout(function() { location.reload(); }, 300);
            } else {
                console.error('Error saving player:', res);
                spmNotice('spm-player-form-notice', (res.data && res.data.message) ? res.data.message : 'Error saving player', 'error');
            }
        });
    }

    function spmDeletePlayer(id,nonce){
        spmConfirmDelete('Delete this player? Their stats will also be removed.',function(){
            spmAjax({action:'spm_delete_player',id:id,nonce:nonce},function(res){
                if(res.success){var row=document.getElementById('player-row-'+id);if(row)row.remove();spmNotice('spm-players-notice',res.data.message,'success');}
                else spmNotice('spm-players-notice',res.data.message,'error');
            });
        });
    }

    (function(){
        var sp=document.getElementById('spm-players-search');
        if(sp)sp.addEventListener('input',function(){
            var q=this.value.toLowerCase().trim();
            document.querySelectorAll('#spm-players-table tbody tr[id^="player-row-"]').forEach(function(row){
                if(!q){row.style.display='';return;}
                row.style.display=row.textContent.toLowerCase().indexOf(q)!==-1?'':'none';
            });
        });
    })();
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
        SELECT g.id, g.rand_id, g.business_id, g.game_date, g.team_a_id, g.team_b_id,
               g.team_a_score, g.team_b_score, g.season,
               COALESCE(g.status,'scheduled') AS status,
               COALESCE(ta.team_name,'Unknown') AS team_a_name,
               COALESCE(tb.team_name,'Unknown') AS team_b_name
        FROM {$wpdb->prefix}spm_games g
        LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id=ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id=tb.id
        WHERE g.business_id=%d ORDER BY g.game_date DESC
    ", $business_id));
    $teams = $wpdb->get_results($wpdb->prepare("SELECT id, team_name FROM {$wpdb->prefix}spm_teams WHERE business_id=%d ORDER BY team_name ASC", $business_id));
    $seasons = $wpdb->get_results($wpdb->prepare("SELECT season_name FROM {$wpdb->prefix}spm_seasons WHERE business_id=%d ORDER BY start_date DESC", $business_id));
    $teams_json = json_encode(array_map(function($t){ return ['id'=>$t->id,'name'=>$t->team_name]; }, $teams));
    $nonce = wp_create_nonce('spm_game_nonce');
    ob_start();
    ?>
    <div class="spm-box">
        <div class="spm-section-head">
            <div><h3>Games</h3><p>Record and manage game results</p></div>
            <button class="spm-btn spm-btn--primary" onclick="spmOpenGameModal(0)">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Add Game
            </button>
        </div>
        <div id="spm-games-notice" style="padding:0 18px;"></div>
        <div class="spm-search"><input id="spm-games-search" placeholder="Search games by team, season, date…"></div>
        <div class="spm-table-wrap">
            <table class="spm-table">
                <thead><tr><th>Date</th><th>Matchup</th><th>Score</th><th>Season</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($games)): ?>
                    <tr><td colspan="6"><p class="spm-empty">No games recorded yet.</p></td></tr>
                    <?php else: foreach ($games as $g):
                        $g->status = ($g->status === '0' || empty($g->status)) ? 'scheduled' : $g->status;
                        $winner = '';
                        if ($g->team_a_score > $g->team_b_score) $winner = $g->team_a_name;
                        elseif ($g->team_b_score > $g->team_a_score) $winner = $g->team_b_name;
                    ?>
                    <tr id="game-row-<?php echo $g->id; ?>">
                        <td style="white-space:nowrap; color:var(--nba-gray-5); font-size:13px;"><?php echo date('M d, Y', strtotime($g->game_date)); ?></td>
                        <td>
                            <div class="spm-player-cell__name"><?php echo esc_html($g->team_a_name); ?></div>
                            <div style="font-size:11px;color:var(--nba-gray-4);">vs <?php echo esc_html($g->team_b_name); ?></div>
                        </td>
                        <td>
                            <span class="spm-score"><?php echo $g->team_a_score; ?> &ndash; <?php echo $g->team_b_score; ?></span>
                            <?php if ($winner): ?><div class="spm-score-sub"><?php echo esc_html($winner); ?> wins</div><?php endif; ?>
                        </td>
                        <td><?php echo esc_html($g->season ?: '—'); ?></td>
                        <td>
                            <span class="spm-status-dot spm-status-dot--<?php echo $g->status; ?>"><?php echo ucfirst($g->status); ?></span>
                        </td>
                        <td style="display:flex;gap:6px;">
                            <button class="spm-btn spm-btn--sm spm-btn--edit" onclick="spmOpenGameModal(<?php echo $g->id; ?>)">Edit</button>
                            <button class="spm-btn spm-btn--sm spm-btn--danger" onclick="spmDeleteGame(<?php echo $g->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    var spmGameNonce='<?php echo $nonce; ?>';
    var spmTeamsGame=<?php echo $teams_json; ?>;
    var spmSeasonsData=<?php echo json_encode(array_map(function($s){ return $s->season_name; }, $seasons)); ?>;
    function spmGameTeamOptions(selectedId){var html='<option value="">-- Select Team --</option>';spmTeamsGame.forEach(function(t){html+='<option value="'+t.id+'"'+(t.id==selectedId?' selected':'')+'>'+t.name+'</option>';});return html;}
    function spmSeasonsOptions(selectedName){var html='<option value="">-- Select Season --</option>';spmSeasonsData.forEach(function(s){html+='<option value="'+s+'"'+(s==selectedName?' selected':'')+'>'+s+'</option>';});return html;}
    function spmOpenGameModal(gameId){
        if(gameId===0){spmOpenModal('Add Game',spmGameForm(null));}
        else{spmAjax({action:'spm_get_game',id:gameId,nonce:spmGameNonce},function(res){if(res.success)spmOpenModal('Edit Game',spmGameForm(res.data));});}
    }
    function spmGameForm(data){
        var d=data||{};
        var today=new Date().toISOString().split('T')[0];
        // For editing: show stored score (could be 0). For new: empty placeholder
        var isEdit = d.id && d.id > 0;
        var scoreA = isEdit ? d.team_a_score : '';
        var scoreB = isEdit ? d.team_b_score : '';
        var gameStatus = d.status || 'scheduled';
        if(gameStatus==='0'||gameStatus==='') gameStatus='scheduled';
        return '<div class="spm-form-group"><label>Game Date *</label><input type="date" id="bg-date" value="'+(d.game_date||today)+'"></div>'+
               '<div class="spm-form-group"><label>Team A *</label><select id="bg-team-a">'+spmGameTeamOptions(d.team_a_id||0)+'</select></div>'+
               '<div class="spm-form-group"><label>Team B *</label><select id="bg-team-b">'+spmGameTeamOptions(d.team_b_id||0)+'</select></div>'+
               '<div class="spm-form-row"><div class="spm-form-group"><label>Team A Score</label><input type="number" id="bg-score-a" value="'+scoreA+'" min="0" placeholder="0"></div>'+
               '<div class="spm-form-group"><label>Team B Score</label><input type="number" id="bg-score-b" value="'+scoreB+'" min="0" placeholder="0"></div></div>'+
               '<div class="spm-form-group"><label>Season</label><select id="bg-season">'+spmSeasonsOptions(d.season||'')+'</select></div>'+
               '<div class="spm-form-group"><label>Status</label><select id="bg-status">'+
               '<option value="scheduled"'+(gameStatus==='scheduled'?' selected':'')+'>Scheduled</option>'+
               '<option value="completed"'+(gameStatus==='completed'?' selected':'')+'>Completed</option>'+
               '<option value="cancelled"'+(gameStatus==='cancelled'?' selected':'')+'>Cancelled</option>'+
               '</select></div>'+
               '<div id="spm-game-form-notice"></div>'+
               '<div class="spm-modal__footer"><button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Cancel</button><button class="spm-btn spm-btn--primary" onclick="spmSaveGame('+(d.id||0)+')">Save Game</button></div>';
    }
    function spmSaveGame(id){
        var ta=document.getElementById('bg-team-a').value,tb=document.getElementById('bg-team-b').value;
        if(!ta||!tb){spmNotice('spm-game-form-notice','Both teams are required.','error');return;}
        if(ta===tb){spmNotice('spm-game-form-notice','A team cannot play against itself.','error');return;}
        spmAjax({action:'spm_save_game',nonce:spmGameNonce,id:id,game_date:document.getElementById('bg-date').value,team_a_id:ta,team_b_id:tb,team_a_score:parseInt(document.getElementById('bg-score-a').value)||0,team_b_score:parseInt(document.getElementById('bg-score-b').value)||0,season:document.getElementById('bg-season').value,status:document.getElementById('bg-status').value},function(res){if(res.success){spmCloseModal();location.reload();}else spmNotice('spm-game-form-notice',res.data.message,'error');});
    }
    function spmDeleteGame(id,nonce){spmConfirmDelete('Delete this game? All associated player stats will also be removed.',function(){spmAjax({action:'spm_delete_game',id:id,nonce:nonce},function(res){if(res.success){var row=document.getElementById('game-row-'+id);if(row)row.remove();spmNotice('spm-games-notice',res.data.message,'success');}else spmNotice('spm-games-notice',res.data.message,'error');});});}
    (function(){var sg=document.getElementById('spm-games-search');if(sg)sg.addEventListener('input',function(){var q=this.value.toLowerCase().trim();document.querySelectorAll('tr[id^="game-row-"]').forEach(function(row){if(!q){row.style.display='';return;}row.style.display=row.textContent.toLowerCase().indexOf(q)!==-1?'':'none';});});})();
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
               COALESCE(p.player_name,'Unknown Player') AS player_name,
               p.jersey_number, p.position,
               COALESCE(t.team_name,'Unassigned') AS team_name,
               CONCAT(COALESCE(ta.team_name,'Unknown'),' vs ',COALESCE(tb.team_name,'Unknown'),' (',DATE_FORMAT(g.game_date,'%%b %%d, %%Y'),')') AS game_label
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id
        LEFT JOIN {$wpdb->prefix}spm_games g ON s.game_id=g.id
        LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id=ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id=tb.id
        WHERE s.business_id=%d ORDER BY s.id DESC
    ", $business_id));
    $games = $wpdb->get_results($wpdb->prepare("
        SELECT g.id, CONCAT(COALESCE(ta.team_name,'Unknown'),' vs ',COALESCE(tb.team_name,'Unknown'),' (',DATE_FORMAT(g.game_date,'%%b %%d, %%Y'),')') AS label
        FROM {$wpdb->prefix}spm_games g
        LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id=ta.id
        LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id=tb.id
        WHERE g.business_id=%d ORDER BY g.game_date DESC
    ", $business_id));
    $games_json = json_encode(array_map(function($g){ return ['id'=>$g->id,'label'=>$g->label]; }, $games));
    $nonce = wp_create_nonce('spm_stat_nonce');
    ob_start();
    ?>
    <div class="spm-box">
        <div class="spm-section-head">
            <div><h3>Player Statistics</h3><p>Record individual performance per game</p></div>
            <button class="spm-btn spm-btn--primary" onclick="spmOpenStatModal(0)">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                Add Stats Entry
            </button>
        </div>
        <div id="spm-stats-notice" style="padding:0 18px;"></div>
        <div class="spm-search"><input id="spm-stats-search" placeholder="Search stats by player, team, game…"></div>
        <div class="spm-table-wrap">
            <table class="spm-table" id="spm-stats-table">
                <thead><tr><th>Player</th><th>Game</th><th>MIN</th><th>PTS</th><th>REB</th><th>AST</th><th>STL</th><th>BLK</th><th>TO</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($stats)): ?>
                    <tr><td colspan="10"><p class="spm-empty">No statistics recorded yet.</p></td></tr>
                    <?php else: foreach ($stats as $s): ?>
                    <tr id="stat-row-<?php echo $s->id; ?>">
                        <td>
                            <div class="spm-player-cell__name"><?php echo esc_html($s->player_name); ?></div>
                            <div style="font-size:11px;color:var(--nba-gray-4);"><?php echo esc_html($s->team_name ?: '—'); ?></div>
                        </td>
                        <td style="font-size:12px;color:var(--nba-gray-4);max-width:180px;white-space:normal;"><?php echo esc_html($s->game_label); ?></td>
                        <td><?php echo $s->minutes; ?></td>
                        <td><span class="spm-table__num"><?php echo $s->points; ?></span></td>
                        <td><?php echo $s->rebounds; ?></td>
                        <td><?php echo $s->assists; ?></td>
                        <td><?php echo $s->steals; ?></td>
                        <td><?php echo $s->blocks; ?></td>
                        <td><?php echo $s->turnovers; ?></td>
                        <td style="display:flex;gap:6px;">

                            <button class="spm-btn spm-btn--sm spm-btn--edit" onclick="spmOpenStatModal(<?php echo $s->id; ?>)">Edit</button>
                            <button class="spm-btn spm-btn--sm spm-btn--danger" onclick="spmDeleteStat(<?php echo $s->id; ?>, '<?php echo esc_js($nonce); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
    var spmStatNonce='<?php echo $nonce; ?>';
    var spmGamesData=<?php echo $games_json; ?>;
    function spmGamesOptions(selectedId){var html='<option value="">-- Select Game --</option>';spmGamesData.forEach(function(g){html+='<option value="'+g.id+'"'+(g.id==selectedId?' selected':'')+'>'+g.label+'</option>';});return html;}
    function spmOpenStatModal(statId){
        if(statId===0){spmOpenModal('Add Player Statistics',spmStatFormShell(null));setTimeout(function(){spmBindGameChange();},50);}
        else{spmAjax({action:'spm_get_stat',id:statId,nonce:spmStatNonce},function(res){if(res.success){spmOpenModal('Edit Player Statistics',spmStatFormShell(res.data));setTimeout(function(){spmBindGameChange(res.data.game_id,res.data.player_id);},50);}});}
    }
    function spmStatFormShell(data){
        var d=data||{};
        return '<div class="spm-form-group"><label>Game *</label><select id="bs-game">'+spmGamesOptions(d.game_id||0)+'</select></div>'+
               '<div class="spm-form-group"><label>Player *</label><select id="bs-player"><option value="">-- Select Game First --</option></select></div>'+
               spmStatFields(d)+
               '<div id="spm-stat-form-notice"></div>'+
               '<div class="spm-modal__footer"><button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Cancel</button><button class="spm-btn spm-btn--primary" onclick="spmSaveStat('+(d.id||0)+')">Save Stats</button></div>';
    }
    function spmStatFields(d){
        d=d||{};
        var isEdit = d.id && parseInt(d.id) > 0;
        // For new entries show empty; for edits show stored value (even if 0)
        function sv(val){ return isEdit ? val : ''; }
        return '<div class="spm-form-row spm-form-row--3">'+
               '<div class="spm-form-group"><label>Minutes</label><input type="number" id="bs-min" value="'+sv(d.minutes)+'" min="0" placeholder="0"></div>'+
               '<div class="spm-form-group"><label>Points</label><input type="number" id="bs-pts" value="'+sv(d.points)+'" min="0" placeholder="0"></div>'+
               '<div class="spm-form-group"><label>Rebounds</label><input type="number" id="bs-reb" value="'+sv(d.rebounds)+'" min="0" placeholder="0"></div>'+
               '<div class="spm-form-group"><label>Assists</label><input type="number" id="bs-ast" value="'+sv(d.assists)+'" min="0" placeholder="0"></div>'+
               '<div class="spm-form-group"><label>Steals</label><input type="number" id="bs-stl" value="'+sv(d.steals)+'" min="0" placeholder="0"></div>'+
               '<div class="spm-form-group"><label>Blocks</label><input type="number" id="bs-blk" value="'+sv(d.blocks)+'" min="0" placeholder="0"></div></div>'+
               '<div class="spm-form-group" style="max-width:180px;"><label>Turnovers</label><input type="number" id="bs-to" value="'+sv(d.turnovers)+'" min="0" placeholder="0"></div>';
    }
    function spmBindGameChange(preselectedGame,preselectedPlayer){
        var sel=document.getElementById('bs-game');if(!sel)return;
        sel.addEventListener('change',function(){spmLoadGamePlayers(this.value,preselectedPlayer);});
        if(preselectedGame)spmLoadGamePlayers(preselectedGame,preselectedPlayer);
    }
    function spmLoadGamePlayers(gameId,preselectedPlayer){
        if(!gameId)return;
        spmAjax({action:'spm_get_game_players',game_id:gameId,nonce:spmStatNonce},function(res){
            var sel=document.getElementById('bs-player');if(!sel||!res.success)return;
            sel.innerHTML='<option value="">-- Select Player --</option>';
            res.data.forEach(function(p){var opt=document.createElement('option');opt.value=p.id;opt.textContent='#'+p.jersey_number+' '+p.player_name+' ('+p.team_name+')';if(preselectedPlayer&&p.id==preselectedPlayer)opt.selected=true;sel.appendChild(opt);});
        });
    }
    function spmSaveStat(id){
        var gid=document.getElementById('bs-game').value,pid=document.getElementById('bs-player').value;
        if(!gid){spmNotice('spm-stat-form-notice','Please select a game.','error');return;}
        if(!pid){spmNotice('spm-stat-form-notice','Please select a player.','error');return;}
        spmAjax({action:'spm_save_stat',nonce:spmStatNonce,id:id,game_id:gid,player_id:pid,minutes:document.getElementById('bs-min').value,points:document.getElementById('bs-pts').value,rebounds:document.getElementById('bs-reb').value,assists:document.getElementById('bs-ast').value,steals:document.getElementById('bs-stl').value,blocks:document.getElementById('bs-blk').value,turnovers:document.getElementById('bs-to').value},function(res){if(res.success){spmCloseModal();location.reload();}else spmNotice('spm-stat-form-notice',res.data.message,'error');});
    }
    function spmDeleteStat(id,nonce){spmConfirmDelete('Delete this stat entry?',function(){spmAjax({action:'spm_delete_stat',id:id,nonce:nonce},function(res){if(res.success){var row=document.getElementById('stat-row-'+id);if(row)row.remove();spmNotice('spm-stats-notice',res.data.message,'success');}else spmNotice('spm-stats-notice',res.data.message,'error');});});}
    
    // Search functionality
    (function(){
        var ss=document.getElementById('spm-stats-search');
        if(ss)ss.addEventListener('input',function(){
            var q=this.value.toLowerCase().trim();
            document.querySelectorAll('#spm-stats-table tbody tr[id^="stat-row-"]').forEach(function(row){
                if(!q){row.style.display='';return;}
                row.style.display=row.textContent.toLowerCase().indexOf(q)!==-1?'':'none';
            });
        });
    })();
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
        SELECT p.id, p.player_name, COALESCE(p.photo,'') AS player_photo, t.team_name, COUNT(s.id) AS gp,
               SUM(s.points) AS total_pts,
               ROUND(SUM(s.points)/COUNT(s.id),1) AS ppg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id
        WHERE s.business_id=%d GROUP BY s.player_id HAVING gp>0 ORDER BY ppg DESC LIMIT 15
    ", $business_id));

    $rebounders = $wpdb->get_results($wpdb->prepare("
        SELECT p.player_name, COALESCE(p.photo,'') AS player_photo, t.team_name, COUNT(s.id) AS gp, ROUND(SUM(s.rebounds)/COUNT(s.id),1) AS rpg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id
        WHERE s.business_id=%d GROUP BY s.player_id HAVING gp>0 ORDER BY rpg DESC LIMIT 10
    ", $business_id));

    $assisters = $wpdb->get_results($wpdb->prepare("
        SELECT p.player_name, COALESCE(p.photo,'') AS player_photo, t.team_name, COUNT(s.id) AS gp, ROUND(SUM(s.assists)/COUNT(s.id),1) AS apg
        FROM {$wpdb->prefix}spm_player_stats s
        LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id
        WHERE s.business_id=%d GROUP BY s.player_id HAVING gp>0 ORDER BY apg DESC LIMIT 10
    ", $business_id));

    $team_standings = $wpdb->get_results($wpdb->prepare("
        SELECT t.id, t.team_name, COALESCE(t.logo,'') AS team_logo,
               COALESCE(SUM(CASE WHEN (g.team_a_id=t.id AND g.team_a_score>g.team_b_score) OR (g.team_b_id=t.id AND g.team_b_score>g.team_a_score) THEN 1 ELSE 0 END),0) AS wins,
               COALESCE(SUM(CASE WHEN (g.team_a_id=t.id AND g.team_a_score<g.team_b_score) OR (g.team_b_id=t.id AND g.team_b_score<g.team_a_score) THEN 1 ELSE 0 END),0) AS losses,
               COUNT(DISTINCT g.id) AS total_games
        FROM {$wpdb->prefix}spm_teams t
        LEFT JOIN {$wpdb->prefix}spm_games g ON (g.team_a_id=t.id OR g.team_b_id=t.id) AND g.business_id=%d AND g.status='completed'
        WHERE t.business_id=%d GROUP BY t.id, t.team_name ORDER BY wins DESC, losses ASC
    ", $business_id, $business_id));

    ob_start();
    ?>
    <div class="spm-rankings-grid">
        <!-- TOP SCORERS -->
        <div class="spm-box">
            <div class="spm-panel__header">
                <span class="spm-panel__title">Top Scorers</span>
                <span class="spm-panel__sub">Points per game</span>
            </div>
            <div class="spm-table-wrap" style="border:none; border-radius:0;">
                <table class="spm-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>PPG</th></tr></thead>
                    <tbody>
                        <?php if (empty($scorers)): ?><tr><td colspan="5"><p class="spm-empty">No data yet</p></td></tr>
                        <?php else: foreach ($scorers as $i => $p): ?>
                        <tr>
                            <td><span class="spm-rank spm-rank--<?php echo $i<3?($i+1):'other'; ?>"><?php echo $i+1; ?></span></td>
                            <td>
                                <div class="spm-player-cell">
                                    <?php if (!empty($p->player_photo)): ?>
                                        <img src="<?php echo esc_url($p->player_photo); ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <?php else: ?>
                                        <div style="width:28px;height:28px;border-radius:50%;background:var(--nba-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:10px;flex-shrink:0;"><?php echo strtoupper(substr($p->player_name,0,2)); ?></div>
                                    <?php endif; ?>
                                    <span style="font-weight:700;font-size:13px;"><?php echo esc_html($p->player_name); ?></span>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--nba-gray-4);"><?php echo esc_html($p->team_name); ?></td>
                            <td><?php echo $p->gp; ?></td>
                            <td><span class="spm-table__num"><?php echo $p->ppg; ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TEAM STANDINGS -->
        <div class="spm-box">
            <div class="spm-panel__header">
                <span class="spm-panel__title">Team Standings</span>
                <span class="spm-panel__sub">Completed games only</span>
            </div>
            <div class="spm-table-wrap" style="border:none; border-radius:0;">
                <table class="spm-table">
                    <thead><tr><th>Rank</th><th>Team</th><th>W</th><th>L</th><th>GP</th></tr></thead>
                    <tbody>
                        <?php if (empty($team_standings)): ?><tr><td colspan="5"><p class="spm-empty">No data yet</p></td></tr>
                        <?php else: foreach ($team_standings as $i => $s): ?>
                        <tr>
                            <td><span class="spm-rank spm-rank--<?php echo $i<3?($i+1):'other'; ?>"><?php echo $i+1; ?></span></td>
                            <td>
                                <div class="spm-player-cell">
                                    <?php if (!empty($s->team_logo)): ?>
                                        <img src="<?php echo esc_url($s->team_logo); ?>" style="width:28px;height:28px;border-radius:3px;object-fit:cover;flex-shrink:0;">
                                    <?php else: ?>
                                        <div style="width:28px;height:28px;border-radius:3px;background:var(--nba-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:10px;flex-shrink:0;"><?php echo strtoupper(substr($s->team_name,0,2)); ?></div>
                                    <?php endif; ?>
                                    <span style="font-weight:700;font-size:13px;"><?php echo esc_html($s->team_name); ?></span>
                                </div>
                            </td>
                            <td style="font-weight:800;color:#1a6b38;font-family:var(--font-display);font-size:16px;"><?php echo $s->wins; ?></td>
                            <td style="font-weight:800;color:var(--nba-red);font-family:var(--font-display);font-size:16px;"><?php echo $s->losses; ?></td>
                            <td><?php echo $s->total_games; ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="spm-rankings-grid">
        <!-- TOP REBOUNDERS -->
        <div class="spm-box">
            <div class="spm-panel__header">
                <span class="spm-panel__title">Top Rebounders</span>
                <span class="spm-panel__sub">Rebounds per game</span>
            </div>
            <div class="spm-table-wrap" style="border:none; border-radius:0;">
                <table class="spm-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>RPG</th></tr></thead>
                    <tbody>
                        <?php if (empty($rebounders)): ?><tr><td colspan="5"><p class="spm-empty">No data yet</p></td></tr>
                        <?php else: foreach ($rebounders as $i => $p): ?>
                        <tr>
                            <td><span class="spm-rank spm-rank--<?php echo $i<3?($i+1):'other'; ?>"><?php echo $i+1; ?></span></td>
                            <td>
                                <div class="spm-player-cell">
                                    <?php if (!empty($p->player_photo)): ?>
                                        <img src="<?php echo esc_url($p->player_photo); ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <?php else: ?>
                                        <div style="width:28px;height:28px;border-radius:50%;background:var(--nba-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:10px;flex-shrink:0;"><?php echo strtoupper(substr($p->player_name,0,2)); ?></div>
                                    <?php endif; ?>
                                    <span style="font-weight:700;font-size:13px;"><?php echo esc_html($p->player_name); ?></span>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--nba-gray-4);"><?php echo esc_html($p->team_name); ?></td>
                            <td><?php echo $p->gp; ?></td>
                            <td><span class="spm-table__num" style="color:#6d28d9;"><?php echo $p->rpg; ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TOP ASSISTERS -->
        <div class="spm-box">
            <div class="spm-panel__header">
                <span class="spm-panel__title">Top Assists</span>
                <span class="spm-panel__sub">Assists per game</span>
            </div>
            <div class="spm-table-wrap" style="border:none; border-radius:0;">
                <table class="spm-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>APG</th></tr></thead>
                    <tbody>
                        <?php if (empty($assisters)): ?><tr><td colspan="5"><p class="spm-empty">No data yet</p></td></tr>
                        <?php else: foreach ($assisters as $i => $p): ?>
                        <tr>
                            <td><span class="spm-rank spm-rank--<?php echo $i<3?($i+1):'other'; ?>"><?php echo $i+1; ?></span></td>
                            <td>
                                <div class="spm-player-cell">
                                    <?php if (!empty($p->player_photo)): ?>
                                        <img src="<?php echo esc_url($p->player_photo); ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                    <?php else: ?>
                                        <div style="width:28px;height:28px;border-radius:50%;background:var(--nba-navy);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--font-display);font-weight:800;font-size:10px;flex-shrink:0;"><?php echo strtoupper(substr($p->player_name,0,2)); ?></div>
                                    <?php endif; ?>
                                    <span style="font-weight:700;font-size:13px;"><?php echo esc_html($p->player_name); ?></span>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--nba-gray-4);"><?php echo esc_html($p->team_name); ?></td>
                            <td><?php echo $p->gp; ?></td>
                            <td><span class="spm-table__num" style="color:#1a6b38;"><?php echo $p->apg; ?></span></td>
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
    // Ensure database tables are created
    bntm_spm_create_tables();
    
    ob_start();
    ?>
    <div class="spm-wrap" id="spm-public-wrap">
        <div class="spm-header">
            <div class="spm-header__brand">
                <div class="spm-header__ball">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" width="32" height="32">
                        <circle cx="20" cy="20" r="18" fill="#C9531F"/>
                        <path d="M20 2C20 2 14 8 14 20C14 32 20 38 20 38" stroke="#1a1a1a" stroke-width="1.5"/>
                        <path d="M20 2C20 2 26 8 26 20C26 32 20 38 20 38" stroke="#1a1a1a" stroke-width="1.5"/>
                        <path d="M2 20H38" stroke="#1a1a1a" stroke-width="1.5"/>
                    </svg>
                </div>
                <div>
                    <div class="spm-header__title">Basketball League</div>
                    <div class="spm-header__sub">Public Stats</div>
                </div>
            </div>
        </div>
        <nav class="spm-nav">
            <div class="spm-nav__inner">
                <button class="spm-nav__btn spm-nav__btn--active" data-ptab="pub-teams">Teams</button>
                <button class="spm-nav__btn" data-ptab="pub-players">Players</button>
                <button class="spm-nav__btn" data-ptab="pub-results">Results</button>
                <button class="spm-nav__btn" data-ptab="pub-rankings">Rankings</button>
            </div>
        </nav>
        <div class="spm-content">
            <div id="pub-teams"    class="spm-panel-wrap spm-panel-wrap--active"><p class="spm-empty" style="background:#fff;border:1px solid #ddd;border-radius:4px;">Loading…</p></div>
            <div id="pub-players"  class="spm-panel-wrap"></div>
            <div id="pub-results"  class="spm-panel-wrap"></div>
            <div id="pub-rankings" class="spm-panel-wrap"></div>
        </div>
    </div>
    <script>
    var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';
    (function(){
        document.querySelectorAll('#spm-public-wrap .spm-nav__btn').forEach(function(btn){
            btn.addEventListener('click',function(){
                document.querySelectorAll('#spm-public-wrap .spm-nav__btn').forEach(function(b){b.classList.remove('spm-nav__btn--active');});
                document.querySelectorAll('#spm-public-wrap .spm-panel-wrap').forEach(function(p){p.classList.remove('spm-panel-wrap--active');});
                this.classList.add('spm-nav__btn--active');
                var panel=document.getElementById(this.dataset.ptab);
                if(panel){panel.classList.add('spm-panel-wrap--active');if(!panel.dataset.loaded)loadPublicTab(this.dataset.ptab);}
            });
        });
        loadPublicTab('pub-teams');
        function loadPublicTab(tabId){
            var map={'pub-teams':'teams','pub-players':'players','pub-results':'results','pub-rankings':'rankings'};
            var type=map[tabId];if(!type)return;
            var fd=new FormData();fd.append('action','spm_public_data');fd.append('type',type);
            fetch(ajaxurl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(res){var panel=document.getElementById(tabId);if(panel&&res.success){panel.innerHTML=res.data.html;panel.dataset.loaded='1';}});
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
    switch ($type) {
        case 'teams':
            $rows = $wpdb->get_results("SELECT t.id, t.team_name, COALESCE(t.city,'') AS city, COALESCE(t.coach,'') AS coach, (SELECT COUNT(*) FROM {$wpdb->prefix}spm_players WHERE team_id=t.id AND status='active') AS player_count FROM {$wpdb->prefix}spm_teams t ORDER BY t.team_name ASC");
            ob_start();
            ?><div class="spm-box"><div class="spm-table-wrap"><table class="spm-table"><thead><tr><th>Team</th><th>City</th><th>Coach</th><th>Players</th></tr></thead><tbody>
            <?php if(empty($rows)):?><tr><td colspan="4"><p class="spm-empty">No teams yet.</p></td></tr>
            <?php else:foreach($rows as $r):?><tr><td style="font-weight:700;"><?php echo esc_html($r->team_name);?></td><td><?php echo esc_html($r->city);?></td><td><?php echo esc_html($r->coach?:'—');?></td><td><span class="spm-badge spm-badge--blue"><?php echo $r->player_count;?></span></td></tr>
            <?php endforeach;endif;?></tbody></table></div></div><?php
            wp_send_json_success(['html'=>ob_get_clean()]); break;
        case 'players':
            $rows = $wpdb->get_results("SELECT p.player_name, p.jersey_number, COALESCE(p.position,'') AS position, COALESCE(p.height,'') AS height, COALESCE(t.team_name,'Unassigned') AS team_name FROM {$wpdb->prefix}spm_players p LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id WHERE p.status='active' ORDER BY p.player_name ASC");
            ob_start();
            ?><div class="spm-box"><div class="spm-table-wrap"><table class="spm-table"><thead><tr><th>Player</th><th>Team</th><th>#</th><th>Position</th><th>Height</th></tr></thead><tbody>
            <?php if(empty($rows)):?><tr><td colspan="5"><p class="spm-empty">No players yet.</p></td></tr>
            <?php else:foreach($rows as $r):?><tr><td style="font-weight:700;"><?php echo esc_html($r->player_name);?></td><td><?php echo esc_html($r->team_name?:'—');?></td><td><span class="spm-table__num"><?php echo $r->jersey_number;?></span></td><td><?php echo esc_html($r->position?:'—');?></td><td><?php echo esc_html($r->height?:'—');?></td></tr>
            <?php endforeach;endif;?></tbody></table></div></div><?php
            wp_send_json_success(['html'=>ob_get_clean()]); break;
        case 'results':
            $rows = $wpdb->get_results("SELECT g.game_date, g.team_a_score, g.team_b_score, g.season, COALESCE(ta.team_name,'Unknown') AS ta, COALESCE(tb.team_name,'Unknown') AS tb FROM {$wpdb->prefix}spm_games g LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id=ta.id LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id=tb.id ORDER BY g.game_date DESC LIMIT 30");
            ob_start();
            ?><div class="spm-box"><div class="spm-table-wrap"><table class="spm-table"><thead><tr><th>Date</th><th>Teams</th><th>Score</th><th>Season</th></tr></thead><tbody>
            <?php if(empty($rows)):?><tr><td colspan="4"><p class="spm-empty">No results yet.</p></td></tr>
            <?php else:foreach($rows as $r):?><tr><td style="color:var(--nba-gray-5);font-size:13px;"><?php echo date('M d, Y',strtotime($r->game_date));?></td><td><?php echo esc_html($r->ta);?> vs <?php echo esc_html($r->tb);?></td><td><span class="spm-score"><?php echo $r->team_a_score;?> &ndash; <?php echo $r->team_b_score;?></span></td><td><?php echo esc_html($r->season?:'—');?></td></tr>
            <?php endforeach;endif;?></tbody></table></div></div><?php
            wp_send_json_success(['html'=>ob_get_clean()]); break;
        case 'rankings':
            $scorers = $wpdb->get_results("SELECT COALESCE(p.player_name,'Unknown') AS player_name, COALESCE(t.team_name,'Unassigned') AS team_name, COUNT(s.id) AS gp, ROUND(SUM(s.points)/COUNT(s.id),1) AS ppg, ROUND(SUM(s.rebounds)/COUNT(s.id),1) AS rpg, ROUND(SUM(s.assists)/COUNT(s.id),1) AS apg FROM {$wpdb->prefix}spm_player_stats s LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id GROUP BY s.player_id HAVING gp>0 ORDER BY ppg DESC LIMIT 10");
            ob_start();
            ?><div class="spm-box"><div class="spm-table-wrap"><table class="spm-table"><thead><tr><th>Rank</th><th>Player</th><th>Team</th><th>GP</th><th>PPG</th><th>RPG</th><th>APG</th></tr></thead><tbody>
            <?php if(empty($scorers)):?><tr><td colspan="7"><p class="spm-empty">No stats yet.</p></td></tr>
            <?php else:foreach($scorers as $i=>$p):?><tr><td><span class="spm-rank spm-rank--<?php echo $i<3?($i+1):'other';?>"><?php echo $i+1;?></span></td><td style="font-weight:700;"><?php echo esc_html($p->player_name);?></td><td style="font-size:12px;color:var(--nba-gray-4);"><?php echo esc_html($p->team_name);?></td><td><?php echo $p->gp;?></td><td><span class="spm-table__num"><?php echo $p->ppg;?></span></td><td><span class="spm-table__num" style="color:#6d28d9;"><?php echo $p->rpg;?></span></td><td><span class="spm-table__num" style="color:#1a6b38;"><?php echo $p->apg;?></span></td></tr>
            <?php endforeach;endif;?></tbody></table></div></div><?php
            wp_send_json_success(['html'=>ob_get_clean()]); break;
        default: wp_send_json_error(['message'=>'Invalid type']);
    }
}

// ============================================================
// AJAX HANDLERS: TEAMS
// ============================================================

function bntm_ajax_spm_get_team() {
    check_ajax_referer('spm_team_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $team=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_teams WHERE id=%d AND business_id=%d",intval($_POST['id']),get_current_user_id()));
    if(!$team)wp_send_json_error(['message'=>'Team not found']);
    wp_send_json_success($team);
}

function bntm_ajax_spm_save_team() {
    check_ajax_referer('spm_team_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $id=intval($_POST['id']??0);$business_id=get_current_user_id();
    $data=['team_name'=>sanitize_text_field($_POST['team_name']),'city'=>sanitize_text_field($_POST['city']??''),'coach'=>sanitize_text_field($_POST['coach']??''),'logo'=>esc_url_raw($_POST['logo']??''),'primary_color'=>sanitize_hex_color($_POST['primary_color']??'#1F2D6D'),'secondary_color'=>sanitize_hex_color($_POST['secondary_color']??'#FFD700'),'outline_color'=>sanitize_hex_color($_POST['outline_color']??'#FFFFFF')];
    $formats=['%s','%s','%s','%s','%s','%s','%s'];
    if(empty($data['team_name']))wp_send_json_error(['message'=>'Team name is required']);
    if(!empty($_FILES['logo_file'])&&!empty($_FILES['logo_file']['tmp_name'])){require_once(ABSPATH.'wp-admin/includes/file.php');$uploaded=wp_handle_upload($_FILES['logo_file'],['test_form'=>false,'mimes'=>null]);if(empty($uploaded['error'])&&!empty($uploaded['url']))$data['logo']=esc_url_raw($uploaded['url']);}
    if($id>0){$wpdb->update("{$wpdb->prefix}spm_teams",$data,['id'=>$id,'business_id'=>$business_id],$formats,['%d','%d']);wp_send_json_success(['message'=>'Team updated!']);}
    else{$data['rand_id']=bntm_rand_id();$data['business_id']=$business_id;$wpdb->insert("{$wpdb->prefix}spm_teams",$data,array_merge(['%s','%d'],$formats));wp_send_json_success(['message'=>'Team created!']);}
}

function bntm_ajax_spm_delete_team() {
    check_ajax_referer('spm_team_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}spm_teams",['id'=>intval($_POST['id']),'business_id'=>get_current_user_id()],['%d','%d']);
    wp_send_json_success(['message'=>'Team deleted.']);
}

function bntm_ajax_spm_get_team_profile() {
    check_ajax_referer('spm_team_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $team_id = intval($_POST['id']);
    $business_id = get_current_user_id();

    $team = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}spm_teams WHERE id=%d AND business_id=%d", $team_id, $business_id));
    if(!$team) wp_send_json_error(['message'=>'Team not found']);

    $players = $wpdb->get_results($wpdb->prepare(
        "SELECT id, player_name, COALESCE(photo,'') AS photo, jersey_number, position, status
         FROM {$wpdb->prefix}spm_players WHERE team_id=%d AND business_id=%d ORDER BY player_name ASC",
        $team_id, $business_id));

    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT
            SUM(CASE WHEN (team_a_id=%d AND team_a_score>team_b_score) OR (team_b_id=%d AND team_b_score>team_a_score) THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN (team_a_id=%d AND team_a_score<team_b_score) OR (team_b_id=%d AND team_b_score<team_a_score) THEN 1 ELSE 0 END) AS losses,
            COUNT(*) AS total_games
         FROM {$wpdb->prefix}spm_games
         WHERE business_id=%d AND status='completed' AND (team_a_id=%d OR team_b_id=%d)",
        $team_id,$team_id,$team_id,$team_id,$business_id,$team_id,$team_id));

    $wins   = intval($record->wins ?? 0);
    $losses = intval($record->losses ?? 0);
    $gp     = intval($record->total_games ?? 0);
    $pct    = $gp > 0 ? round($wins/$gp*100) : 0;

    $pc = !empty($team->primary_color)   ? $team->primary_color   : '#1D428A';
    $sc = !empty($team->secondary_color) ? $team->secondary_color : '#FFC72C';
    $oc = !empty($team->outline_color)   ? $team->outline_color   : '#FFFFFF';

    $logo_html = !empty($team->logo)
        ? '<img src="'.esc_url($team->logo).'" class="spm-profile__photo" style="border-radius:6px;border:4px solid '.$sc.';" alt="'.esc_attr($team->team_name).'">'
        : '<div class="spm-profile__avatar-lg" style="background:rgba(255,255,255,.1);border:4px solid '.$sc.';color:'.$sc.';">'.esc_html(strtoupper(substr($team->team_name,0,2))).'</div>';

    $players_html = '';
    if (!empty($players)) {
        foreach ($players as $pl) {
            $av = !empty($pl->photo)
                ? '<img src="'.esc_url($pl->photo).'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid '.$sc.';flex-shrink:0;">'
                : '<div style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:2px solid '.$sc.';display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:800;font-size:13px;color:'.$sc.';flex-shrink:0;">'.esc_html(strtoupper(substr($pl->player_name,0,2))).'</div>';
            $status_badge = $pl->status === 'active'
                ? '<span style="font-size:9px;background:rgba(46,125,50,.3);color:#a5d6a7;padding:2px 6px;border-radius:2px;font-weight:700;text-transform:uppercase;">Active</span>'
                : '<span style="font-size:9px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.5);padding:2px 6px;border-radius:2px;font-weight:700;text-transform:uppercase;">Inactive</span>';
            $players_html .= '<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.07);">'.$av.'<div style="flex:1;min-width:0;"><div style="font-weight:700;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:'.$sc.';">'.esc_html($pl->player_name).'</div><div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px;">'.esc_html($pl->position?:'—').' &bull; #'.intval($pl->jersey_number).'</div></div>'.$status_badge.'</div>';
        }
    } else {
        $players_html = '<p style="color:rgba(255,255,255,.4);font-style:italic;font-size:13px;padding:16px 0;">No players rostered yet.</p>';
    }

    $html = '
    <div class="spm-profile" style="--prof-primary:'.$pc.';--prof-secondary:'.$sc.';--prof-outline:'.$oc.';background:linear-gradient(140deg,'.$pc.' 0%,color-mix(in srgb,'.$pc.' 65%,#000) 100%);">
        <div class="spm-profile__hero">
            '.$logo_html.'
            <div class="spm-profile__hero-info">
                <h2 style="-webkit-text-stroke:1.5px '.$oc.';text-stroke:1.5px '.$oc.';paint-order:stroke fill;">'.esc_html($team->team_name).'</h2>
                '.(!empty($team->city)?'<p class="spm-profile__hero-sub">'.esc_html($team->city).'</p>':'').
                (!empty($team->coach)?'<p class="spm-profile__hero-sub">Coach: '.esc_html($team->coach).'</p>':'').'
                <p class="spm-profile__hero-team" style="color:'.$sc.';">'.intval(count($players)).' Players</p>
            </div>
        </div>
        <div class="spm-profile__details" style="background:rgba(0,0,0,.12);border-top:1px solid rgba(255,255,255,.1);">
            <div class="spm-profile__detail-row">
                <div class="spm-profile__detail-item"><div class="spm-profile__detail-label">Wins</div><div class="spm-profile__detail-value" style="color:'.$sc.';">'.$wins.'</div></div>
                <div class="spm-profile__detail-item"><div class="spm-profile__detail-label">Losses</div><div class="spm-profile__detail-value" style="color:'.$sc.';">'.$losses.'</div></div>
                <div class="spm-profile__detail-item"><div class="spm-profile__detail-label">Games</div><div class="spm-profile__detail-value" style="color:'.$sc.';">'.$gp.'</div></div>
                <div class="spm-profile__detail-item"><div class="spm-profile__detail-label">Win %</div><div class="spm-profile__detail-value" style="color:'.$sc.';">'.$pct.'%</div></div>
            </div>
        </div>
        <div class="spm-profile__stats-section" style="background:rgba(0,0,0,.08);border-top:1px solid rgba(255,255,255,.1);">
            <div class="spm-profile__stats-title">Roster</div>
            '.$players_html.'
        </div>
        <div class="spm-profile__footer" style="background:rgba(0,0,0,.15);border-top:1px solid rgba(255,255,255,.08);">
            <button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Close</button>
        </div>
    </div>';

    wp_send_json_success(['html' => $html]);
}

// ============================================================
// AJAX HANDLERS: PLAYERS
// ============================================================

function bntm_ajax_spm_get_player() {
    check_ajax_referer('spm_player_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_players WHERE id=%d AND business_id=%d",intval($_POST['id']),get_current_user_id()));
    if(!$row)wp_send_json_error(['message'=>'Player not found']);
    wp_send_json_success($row);
}

function bntm_ajax_spm_get_player_profile() {
    check_ajax_referer('spm_player_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $player_id = intval($_POST['id']);
    $business_id = get_current_user_id();
    
    // Get player data with team info
    $player = $wpdb->get_row($wpdb->prepare("
        SELECT p.*, t.team_name, t.primary_color, t.secondary_color, t.outline_color FROM {$wpdb->prefix}spm_players p
        LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id = t.id
        WHERE p.id=%d AND p.business_id=%d
    ", $player_id, $business_id));
    
    if(!$player) wp_send_json_error(['message'=>'Player not found']);
    
    // Get player stats aggregated
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as games_played,
            ROUND(AVG(points), 1) as ppg,
            ROUND(AVG(rebounds), 1) as rpg,
            ROUND(AVG(assists), 1) as apg,
            ROUND(AVG(steals), 1) as spg,
            ROUND(AVG(blocks), 1) as bpg,
            SUM(points) as total_points
        FROM {$wpdb->prefix}spm_player_stats 
        WHERE player_id=%d AND business_id=%d
    ", $player_id, $business_id));
    
    if (!$stats) $stats = (object)['games_played'=>0,'ppg'=>0,'rpg'=>0,'apg'=>0,'spg'=>0,'bpg'=>0,'total_points'=>0];
    
    // Determine if secondary text should be light or dark based on primary color
    $primary_color = !empty($player->primary_color) ? $player->primary_color : '#1F2D6D';
    $secondary_color = !empty($player->secondary_color) ? $player->secondary_color : '#FFD700';
    $outline_color = !empty($player->outline_color) ? $player->outline_color : '#FFFFFF';
    
    // Calculate luminance to determine text color contrast
    $rgb = sscanf($secondary_color, "#%02x%02x%02x");
    $luminance = (0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2]) / 255;
    $secondary_text_color = $luminance > 0.5 ? '#000000' : '#FFFFFF';
    
    // Build profile HTML with team colors
    $photo_html = !empty($player->photo) 
        ? '<img src="'.esc_url($player->photo).'" class="spm-profile__photo" alt="'.esc_attr($player->player_name).'">'
        : '<div class="spm-profile__avatar">'.esc_html(strtoupper(substr($player->player_name,0,2))).'</div>';
    
    $height_display = !empty($player->height) ? $player->height : '—';
    $weight_display = intval($player->weight) > 0 ? intval($player->weight).' lbs' : '—';
    $position_display = !empty($player->position) ? $player->position : '—';
    $jersey_display = intval($player->jersey_number) > 0 ? '#'.intval($player->jersey_number) : '—';
    
    $status_color = $player->status === 'active' ? 'green' : 'gray';
    
    // Create inline style with team colors
    $profile_style = 'style="--team-primary: '.esc_attr($primary_color).'; --team-secondary: '.esc_attr($secondary_color).'; --team-secondary-text: '.esc_attr($secondary_text_color).'; --team-outline: '.esc_attr($outline_color).';"';
    
    $html = '
    <div class="spm-profile" '.$profile_style.'>
        <div class="spm-profile__header">
            '.$photo_html.'
            <div class="spm-profile__info">
                <h2>'.esc_html($player->player_name).'</h2>
                <p class="spm-profile__team">'.esc_html($player->team_name ?: 'No Team').'</p>
                <p style="font-size:14px;margin-top:12px;">'.$position_display.' • '.$jersey_display.'</p>
            </div>
        </div>
        <div class="spm-profile__details">
            <div class="spm-profile__detail-grid">
                <div class="spm-profile__detail-item">
                    <div class="spm-profile__detail-label">Jersey</div>
                    <div class="spm-profile__detail-value">'.esc_html($jersey_display).'</div>
                </div>
                <div class="spm-profile__detail-item">
                    <div class="spm-profile__detail-label">Position</div>
                    <div class="spm-profile__detail-value" style="font-size:20px;">'.esc_html($position_display).'</div>
                </div>
                <div class="spm-profile__detail-item">
                    <div class="spm-profile__detail-label">Height</div>
                    <div class="spm-profile__detail-value" style="font-size:20px;">'.esc_html($height_display).'</div>
                </div>
                <div class="spm-profile__detail-item">
                    <div class="spm-profile__detail-label">Weight</div>
                    <div class="spm-profile__detail-value" style="font-size:20px;">'.esc_html($weight_display).'</div>
                </div>
            </div>
        </div>
        <div class="spm-profile__stats">
            <div class="spm-profile__stats-title">Career Statistics</div>
            <div class="spm-profile__stats-grid">
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.esc_html($stats->ppg).'</span>
                    <span class="spm-profile__stat-label">PPG</span>
                </div>
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.esc_html($stats->rpg).'</span>
                    <span class="spm-profile__stat-label">RPG</span>
                </div>
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.esc_html($stats->apg).'</span>
                    <span class="spm-profile__stat-label">APG</span>
                </div>
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.esc_html($stats->spg).'</span>
                    <span class="spm-profile__stat-label">SPG</span>
                </div>
            </div>
            <div class="spm-profile__stats-grid" style="margin-top:12px;">
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.esc_html($stats->bpg).'</span>
                    <span class="spm-profile__stat-label">BPG</span>
                </div>
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.intval($stats->games_played).'</span>
                    <span class="spm-profile__stat-label">GAMES</span>
                </div>
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value">'.intval($stats->total_points).'</span>
                    <span class="spm-profile__stat-label">TOTAL PTS</span>
                </div>
                <div class="spm-profile__stat-box">
                    <span class="spm-profile__stat-value" style="font-size:18px;">'.ucfirst($player->status).'</span>
                    <span class="spm-profile__stat-label">Status</span>
                </div>
            </div>
        </div>
        <div class="spm-profile__footer">
            <button class="spm-btn spm-btn--secondary" onclick="spmCloseModal()">Close</button>
        </div>
    </div>
    ';
    
    wp_send_json_success(['html'=>$html]);
}

function bntm_ajax_spm_save_player() {
    check_ajax_referer('spm_player_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $id=intval($_POST['id']??0);
    $business_id=get_current_user_id();
    // Fixed: Handle empty jersey_number and weight - only convert to int if not empty
    $jersey_val = isset($_POST['jersey_number']) && $_POST['jersey_number'] !== '' ? intval($_POST['jersey_number']) : 0;
    $weight_val = isset($_POST['weight']) && $_POST['weight'] !== '' ? intval($_POST['weight']) : 0;
    $player_name = sanitize_text_field($_POST['player_name'] ?? '');
    $team_id = intval($_POST['team_id'] ?? 0);
    $position = sanitize_text_field($_POST['position'] ?? '');
    $height = sanitize_text_field($_POST['height'] ?? '');
    $status = sanitize_text_field($_POST['status'] ?? 'active');
    
    if(empty($player_name))wp_send_json_error(['message'=>'Player name is required']);
    
    // Handle photo upload - prioritize new file, then existing URL
    $photo = '';
    if(!empty($_FILES['photo_file'])&&!empty($_FILES['photo_file']['tmp_name'])){
        require_once(ABSPATH.'wp-admin/includes/file.php');
        $uploaded=wp_handle_upload($_FILES['photo_file'],['test_form'=>false,'mimes'=>null]);
        if(empty($uploaded['error'])&&!empty($uploaded['url'])){
            $photo=esc_url_raw($uploaded['url']);
        } else {
            wp_send_json_error(['message'=>'Error uploading photo: '.$uploaded['error']]);
        }
    } elseif(!empty($_POST['photo_url'])) {
        // Preserve existing photo URL if no new file is selected
        $photo=esc_url_raw($_POST['photo_url']);
    }
    
    if($id > 0){
        // UPDATE existing player
        $update_data=['player_name'=>$player_name,'team_id'=>$team_id,'jersey_number'=>$jersey_val,'position'=>$position,'height'=>$height,'weight'=>$weight_val,'status'=>$status];
        if(!empty($photo))$update_data['photo']=$photo;
        $result=$wpdb->update("{$wpdb->prefix}spm_players",$update_data,['id'=>$id,'business_id'=>$business_id],['%s','%d','%d','%s','%s','%d','%s','%s'],['%d','%d']);
        if($wpdb->last_error)wp_send_json_error(['message'=>'Database error: '.$wpdb->last_error]);
        wp_send_json_success(['message'=>'Player updated!']);
    } else {
        // INSERT new player
        $data=[
            'rand_id'=>bntm_rand_id(),
            'business_id'=>$business_id,
            'player_name'=>$player_name,
            'team_id'=>$team_id,
            'jersey_number'=>$jersey_val,
            'position'=>$position,
            'height'=>$height,
            'weight'=>$weight_val,
            'photo'=>$photo,
            'status'=>$status
        ];
        $formats=['%s','%d','%s','%d','%d','%s','%s','%d','%s','%s'];
        $result=$wpdb->insert("{$wpdb->prefix}spm_players",$data,$formats);
        if($wpdb->last_error)wp_send_json_error(['message'=>'Database error: '.$wpdb->last_error]);
        wp_send_json_success(['message'=>'Player added!','player_id'=>$wpdb->insert_id]);
    }
}

function bntm_ajax_spm_delete_player() {
    check_ajax_referer('spm_player_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;$id=intval($_POST['id']);
    $wpdb->delete("{$wpdb->prefix}spm_player_stats",['player_id'=>$id,'business_id'=>get_current_user_id()],['%d','%d']);
    $wpdb->delete("{$wpdb->prefix}spm_players",['id'=>$id,'business_id'=>get_current_user_id()],['%d','%d']);
    wp_send_json_success(['message'=>'Player deleted.']);
}

// ============================================================
// AJAX HANDLERS: GAMES
// ============================================================

function bntm_ajax_spm_get_game() {
    check_ajax_referer('spm_game_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_games WHERE id=%d AND business_id=%d",intval($_POST['id']),get_current_user_id()));
    if(!$row)wp_send_json_error(['message'=>'Game not found']);
    wp_send_json_success($row);
}

function bntm_ajax_spm_save_game() {
    check_ajax_referer('spm_game_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $id=intval($_POST['id']??0);$business_id=get_current_user_id();
    $team_a=intval($_POST['team_a_id']);$team_b=intval($_POST['team_b_id']);
    if(!$team_a||!$team_b)wp_send_json_error(['message'=>'Both teams are required']);
    if($team_a===$team_b)wp_send_json_error(['message'=>'A team cannot play against itself']);
    // Fixed: Handle empty scores properly
    $score_a = isset($_POST['team_a_score']) && $_POST['team_a_score'] !== '' ? max(0,intval($_POST['team_a_score'])) : 0;
    $score_b = isset($_POST['team_b_score']) && $_POST['team_b_score'] !== '' ? max(0,intval($_POST['team_b_score'])) : 0;
    $data=['game_date'=>sanitize_text_field($_POST['game_date']),'team_a_id'=>$team_a,'team_b_id'=>$team_b,'team_a_score'=>$score_a,'team_b_score'=>$score_b,'season'=>sanitize_text_field($_POST['season']??''),'status'=>sanitize_text_field($_POST['status']??'scheduled')];
    $formats=['%s','%d','%d','%d','%d','%s','%s'];
    if($id>0){$wpdb->update("{$wpdb->prefix}spm_games",$data,['id'=>$id,'business_id'=>$business_id],$formats,['%d','%d']);wp_send_json_success(['message'=>'Game updated!']);}
    else{$data['rand_id']=bntm_rand_id();$data['business_id']=$business_id;$wpdb->insert("{$wpdb->prefix}spm_games",$data,array_merge(['%s','%d'],$formats));wp_send_json_success(['message'=>'Game recorded!']);}
}

function bntm_ajax_spm_delete_game() {
    check_ajax_referer('spm_game_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;$id=intval($_POST['id']);
    $wpdb->delete("{$wpdb->prefix}spm_player_stats",['game_id'=>$id,'business_id'=>get_current_user_id()],['%d','%d']);
    $wpdb->delete("{$wpdb->prefix}spm_games",['id'=>$id,'business_id'=>get_current_user_id()],['%d','%d']);
    wp_send_json_success(['message'=>'Game deleted.']);
}

// ============================================================
// AJAX HANDLERS: STATS
// ============================================================

function bntm_ajax_spm_get_game_players() {
    check_ajax_referer('spm_stat_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $game_id=intval($_POST['game_id']);$business_id=get_current_user_id();
    $game=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_games WHERE id=%d AND business_id=%d",$game_id,$business_id));
    if(!$game)wp_send_json_error(['message'=>'Game not found']);
    // Get ALL players from both teams, not just active ones, to allow stats for any player
    $players=$wpdb->get_results($wpdb->prepare("SELECT p.id,p.player_name,p.jersey_number,COALESCE(t.team_name,'Unassigned') AS team_name FROM {$wpdb->prefix}spm_players p LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id WHERE p.team_id IN (%d,%d) ORDER BY t.team_name,p.player_name",$game->team_a_id,$game->team_b_id));
    wp_send_json_success($players);
}

function bntm_ajax_spm_get_stat() {
    check_ajax_referer('spm_stat_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $row=$wpdb->get_row($wpdb->prepare("SELECT s.*,COALESCE(p.player_name,'Unknown Player') AS player_name,p.jersey_number,p.position,COALESCE(t.team_name,'Unassigned') AS team_name,CONCAT(COALESCE(ta.team_name,'Unknown'),' vs ',COALESCE(tb.team_name,'Unknown'),' (',DATE_FORMAT(g.game_date,'%%b %%d, %%Y'),')') AS game_label FROM {$wpdb->prefix}spm_player_stats s LEFT JOIN {$wpdb->prefix}spm_players p ON s.player_id=p.id LEFT JOIN {$wpdb->prefix}spm_teams t ON p.team_id=t.id LEFT JOIN {$wpdb->prefix}spm_games g ON s.game_id=g.id LEFT JOIN {$wpdb->prefix}spm_teams ta ON g.team_a_id=ta.id LEFT JOIN {$wpdb->prefix}spm_teams tb ON g.team_b_id=tb.id WHERE s.id=%d AND s.business_id=%d",intval($_POST['id']),get_current_user_id()));
    if(!$row)wp_send_json_error(['message'=>'Stat not found']);
    wp_send_json_success($row);
}
add_action('wp_ajax_spm_get_stat','bntm_ajax_spm_get_stat');

function bntm_ajax_spm_save_stat() {
    check_ajax_referer('spm_stat_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $id=intval($_POST['id']??0);$business_id=get_current_user_id();
    $game_id=intval($_POST['game_id']);$player_id=intval($_POST['player_id']);
    if(!$game_id||!$player_id)wp_send_json_error(['message'=>'Game and player are required']);
    $game=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_games WHERE id=%d AND business_id=%d",$game_id,$business_id));
    if(!$game)wp_send_json_error(['message'=>'Game not found']);
    $player=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_players WHERE id=%d",$player_id));
    if(!$player||!in_array($player->team_id,[$game->team_a_id,$game->team_b_id]))wp_send_json_error(['message'=>'Player is not in a team participating in this game']);
    // Fixed: Handle empty stat values properly
    $minutes = isset($_POST['minutes']) && $_POST['minutes'] !== '' ? max(0,intval($_POST['minutes'])) : 0;
    $points = isset($_POST['points']) && $_POST['points'] !== '' ? max(0,intval($_POST['points'])) : 0;
    $rebounds = isset($_POST['rebounds']) && $_POST['rebounds'] !== '' ? max(0,intval($_POST['rebounds'])) : 0;
    $assists = isset($_POST['assists']) && $_POST['assists'] !== '' ? max(0,intval($_POST['assists'])) : 0;
    $steals = isset($_POST['steals']) && $_POST['steals'] !== '' ? max(0,intval($_POST['steals'])) : 0;
    $blocks = isset($_POST['blocks']) && $_POST['blocks'] !== '' ? max(0,intval($_POST['blocks'])) : 0;
    $turnovers = isset($_POST['turnovers']) && $_POST['turnovers'] !== '' ? max(0,intval($_POST['turnovers'])) : 0;
    $data=['game_id'=>$game_id,'player_id'=>$player_id,'minutes'=>$minutes,'points'=>$points,'rebounds'=>$rebounds,'assists'=>$assists,'steals'=>$steals,'blocks'=>$blocks,'turnovers'=>$turnovers];
    $formats=['%d','%d','%d','%d','%d','%d','%d','%d','%d'];
    if($id>0){$wpdb->update("{$wpdb->prefix}spm_player_stats",$data,['id'=>$id,'business_id'=>$business_id],$formats,['%d','%d']);wp_send_json_success(['message'=>'Stats updated!']);}
    else{$data['rand_id']=bntm_rand_id();$data['business_id']=$business_id;$wpdb->insert("{$wpdb->prefix}spm_player_stats",$data,array_merge(['%s','%d'],$formats));wp_send_json_success(['message'=>'Stats recorded!']);}
}

function bntm_ajax_spm_delete_stat() {
    check_ajax_referer('spm_stat_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $wpdb->delete("{$wpdb->prefix}spm_player_stats",['id'=>intval($_POST['id']),'business_id'=>get_current_user_id()],['%d','%d']);
    wp_send_json_success(['message'=>'Stat entry deleted.']);
}

// ============================================================
// SEASONS AJAX
// ============================================================

function bntm_ajax_spm_save_season() {
    check_ajax_referer('spm_season_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;
    $id=intval($_POST['id']??0);
    $business_id=get_current_user_id();
    $season_name=sanitize_text_field($_POST['season_name']??'');
    $start_date=sanitize_text_field($_POST['start_date']??'');
    $end_date=sanitize_text_field($_POST['end_date']??'');
    if(empty($season_name))wp_send_json_error(['message'=>'Season name required']);
    
    if($id>0){
        $result=$wpdb->update("{$wpdb->prefix}spm_seasons",['season_name'=>$season_name,'start_date'=>$start_date,'end_date'=>$end_date],['id'=>$id,'business_id'=>$business_id],['%s','%s','%s'],['%d','%d']);
        if($wpdb->last_error)wp_send_json_error(['message'=>'Database error: '.$wpdb->last_error]);
        wp_send_json_success(['message'=>'Season updated']);
    } else {
        $rand_id=bntm_rand_id();
        $result=$wpdb->insert("{$wpdb->prefix}spm_seasons",['season_name'=>$season_name,'start_date'=>$start_date,'end_date'=>$end_date,'rand_id'=>$rand_id,'business_id'=>$business_id],['%s','%s','%s','%s','%d']);
        if($wpdb->last_error)wp_send_json_error(['message'=>'Database error: '.$wpdb->last_error]);
        wp_send_json_success(['message'=>'Season created','season_id'=>$wpdb->insert_id]);
    }
}

function bntm_ajax_spm_get_season() {
    check_ajax_referer('spm_season_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;$id=intval($_POST['id']);
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}spm_seasons WHERE id=%d AND business_id=%d",$id,get_current_user_id()));
    if(!$row)wp_send_json_error(['message'=>'Season not found']);
    wp_send_json_success($row);
}

function bntm_ajax_spm_delete_season() {
    check_ajax_referer('spm_season_nonce','nonce');
    if(!is_user_logged_in())wp_send_json_error(['message'=>'Unauthorized']);
    global $wpdb;$id=intval($_POST['id']);
    $season_name=$wpdb->get_var($wpdb->prepare("SELECT season_name FROM {$wpdb->prefix}spm_seasons WHERE id=%d AND business_id=%d",$id,get_current_user_id()));
    $wpdb->delete("{$wpdb->prefix}spm_seasons",['id'=>$id,'business_id'=>get_current_user_id()],['%d','%d']);
    if($season_name)$wpdb->update("{$wpdb->prefix}spm_games",['season'=>''],['season'=>$season_name],['%s'],['%s']);
    wp_send_json_success(['message'=>'Season deleted']);
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function spm_get_player_averages($player_id) {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT COUNT(id) AS gp,ROUND(SUM(points)/COUNT(id),1) AS ppg,ROUND(SUM(rebounds)/COUNT(id),1) AS rpg,ROUND(SUM(assists)/COUNT(id),1) AS apg,ROUND(SUM(steals)/COUNT(id),1) AS spg,ROUND(SUM(blocks)/COUNT(id),1) AS bpg,ROUND(SUM(minutes)/COUNT(id),1) AS mpg FROM {$wpdb->prefix}spm_player_stats WHERE player_id=%d",$player_id));
    return $row ?: (object)['gp'=>0,'ppg'=>0,'rpg'=>0,'apg'=>0,'spg'=>0,'bpg'=>0,'mpg'=>0];
}

function spm_get_team_record($team_id, $business_id) {
    global $wpdb;
    $wins=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_games WHERE business_id=%d AND status='completed' AND ((team_a_id=%d AND team_a_score>team_b_score) OR (team_b_id=%d AND team_b_score>team_a_score))",$business_id,$team_id,$team_id));
    $losses=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}spm_games WHERE business_id=%d AND status='completed' AND ((team_a_id=%d AND team_a_score<team_b_score) OR (team_b_id=%d AND team_b_score<team_a_score))",$business_id,$team_id,$team_id));
    return ['wins'=>$wins,'losses'=>$losses];
}