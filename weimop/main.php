<?php
/**
 * Module Name: WESM/EIMOP Trading Dashboard
 * Module Slug: weimop
 * Description: WESM/EIMOP real-time monitoring. Fetches live + historical data from IEMOP NMMS MPI
 *              via SOAP/HTTPS with PFX mutual-TLS authentication.
 *              Data stored in WordPress MySQL tables.
 * Version: 8.0.0
 * Author: BNTM
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BNTM_WEIMOP_PATH',       dirname( __FILE__ ) . '/' );
define( 'BNTM_WEIMOP_URL',        plugin_dir_url( __FILE__ ) );
define( 'BNTM_WEIMOP_PFX_SUBDIR', 'pfx_folder' );
define( 'BNTM_WEIMOP_VER',        '8.0.0' );

/* -------------------------------------------------------
   A. PATH HELPERS
------------------------------------------------------- */

function bntm_weimop_db_path() {
    return 'WordPress MySQL tables';
}

function bntm_weimop_pfx_dir() {
    $upload = wp_upload_dir();
    $dir    = trailingslashit( $upload['basedir'] ) . BNTM_WEIMOP_PFX_SUBDIR;
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        file_put_contents( $dir . '/index.php',  '<?php // silence' );
    }
    return $dir;
}

function bntm_weimop_pem_dir() {
    $dir = bntm_weimop_pfx_dir() . '/pem';
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );
    return $dir;
}

function bntm_weimop_logs_dir() {
    $dir = bntm_weimop_pfx_dir() . '/logs';
    if ( ! file_exists( $dir ) ) {
        wp_mkdir_p( $dir );
        file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
        file_put_contents( $dir . '/index.php',  '<?php // silence' );
    }
    return $dir;
}

function bntm_weimop_system_log_path() {
    return bntm_weimop_logs_dir() . '/weimop-system.log';
}

function bntm_weimop_log_event( $feature, $level, $message, $context = [] ) {
    $feature = strtoupper( trim( (string) $feature ) ?: 'GENERAL' );
    $level   = strtoupper( trim( (string) $level ) ?: 'INFO' );
    $message = trim( (string) $message );
    $uid     = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
    $time    = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s' );

    if ( ! empty( $context ) ) {
        $json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $context ) : json_encode( $context );
        $message .= ' | context=' . $json;
    }

    $line = sprintf( "[%s] [%s] [%s] [user:%d] %s\n", $time, $feature, $level, $uid, $message );
    $log_path = bntm_weimop_system_log_path();
    @file_put_contents( $log_path, $line, FILE_APPEND | LOCK_EX );

    if ( in_array( $level, [ 'ERROR', 'WARN' ], true ) ) {
        error_log( 'WEIMOP ' . trim( $line ) );
    }

    try {
        $db = bntm_weimop_open_db( false );
        if ( $db ) {
            $db->insert(
                bntm_weimop_table_name( 'ExtractorLog' ),
                [
                    'status'     => strtolower( $level ),
                    'message'    => '[' . $feature . '] ' . substr( $message, 0, 65000 ),
                    'rows_added' => 0,
                ],
                [ '%s', '%s', '%d' ]
            );
        }
    } catch ( Exception $e ) {
        error_log( 'WEIMOP LOGGER FAILED: ' . $e->getMessage() );
    }
}

function bntm_weimop_normalize_cert_path( $path ) {
    $path = is_string( $path ) ? trim( wp_unslash( $path ) ) : '';
    if ( $path === '' ) return '';

    $normalized = wp_normalize_path( $path );
    if ( file_exists( $normalized ) ) return $normalized;

    $basename = basename( str_replace('\\', '/', $path) );
    if ( $basename !== '' ) {
        $candidate = wp_normalize_path( trailingslashit( bntm_weimop_pfx_dir() ) . $basename );
        if ( file_exists( $candidate ) ) return $candidate;
    }

    return $normalized;
}

function bntm_weimop_normalize_local_file_path( $path ) {
    $path = is_string( $path ) ? trim( wp_unslash( $path ) ) : '';
    if ( $path === '' ) return '';
    return wp_normalize_path( $path );
}

function bntm_weimop_find_openssl_binary() {
    static $resolved = null;
    if ( $resolved !== null ) return $resolved;

    $candidates = [
        'C:/xampp/apache/bin/openssl.exe',
        'C:/xampp/php/extras/ssl/openssl.exe',
        'C:/xampp/php/openssl.exe',
    ];

    foreach ( $candidates as $candidate ) {
        $normalized = wp_normalize_path( $candidate );
        if ( file_exists( $normalized ) ) {
            $resolved = $normalized;
            return $resolved;
        }
    }

    if ( function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', ini_get('disable_functions')))) ) {
        $out = [];
        $rc  = 1;
        if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
            exec( 'where openssl 2>NUL', $out, $rc );
        } else {
            exec( 'command -v openssl 2>/dev/null', $out, $rc );
        }
        if ( $rc === 0 && ! empty( $out[0] ) ) {
            $resolved = trim( $out[0] );
            return $resolved;
        }
    }

    $resolved = '';
    return $resolved;
}

function bntm_weimop_build_openssl_passin_arg( $password ) {
    return escapeshellarg( 'pass:' . (string) $password );
}

function bntm_weimop_run_history_key( $feature ) {
    $feature = strtolower( preg_replace( '/[^a-z0-9_]+/i', '_', (string) $feature ) );
    return 'bntm_weimop_' . trim( $feature, '_' ) . '_history';
}

function bntm_weimop_get_run_history( $feature, $uid ) {
    $history = get_user_meta( (int) $uid, bntm_weimop_run_history_key( $feature ), true );
    return is_array( $history ) ? $history : [];
}

function bntm_weimop_get_previous_run_snapshot( $feature, $uid ) {
    $history = bntm_weimop_get_run_history( $feature, $uid );
    return ! empty( $history[0] ) && is_array( $history[0] ) ? $history[0] : null;
}

function bntm_weimop_store_run_snapshot( $feature, $uid, $snapshot, $limit = 8 ) {
    if ( ! is_array( $snapshot ) || empty( $snapshot ) ) return;

    $history = bntm_weimop_get_run_history( $feature, $uid );
    array_unshift( $history, $snapshot );
    $history = array_slice( $history, 0, max( 1, (int) $limit ) );
    update_user_meta( (int) $uid, bntm_weimop_run_history_key( $feature ), $history );
}

function bntm_weimop_recent_feature_logs( $feature, $limit = 12 ) {
    $path = bntm_weimop_system_log_path();
    if ( ! file_exists( $path ) || ! is_readable( $path ) ) return [];

    $lines = @file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    if ( ! is_array( $lines ) || empty( $lines ) ) return [];

    $needle   = '[' . strtoupper( trim( (string) $feature ) ) . ']';
    $matched  = [];
    $reversed = array_reverse( $lines );

    foreach ( $reversed as $line ) {
        if ( strpos( $line, $needle ) === false ) continue;
        $matched[] = trim( $line );
        if ( count( $matched ) >= $limit ) break;
    }

    return array_reverse( $matched );
}

function bntm_weimop_build_run_notice( $current, $previous ) {
    if ( ! is_array( $current ) ) return '';
    if ( empty( $previous ) || ! is_array( $previous ) ) {
        return 'This is the first saved run for this tool on your account.';
    }

    if ( array_key_exists( 'all_pass', $current ) ) {
        if ( ! empty( $current['all_pass'] ) && empty( $previous['all_pass'] ) ) {
            return 'Diagnostics recovered compared with the previous run. You can notify the team that the latest checks passed.';
        }
        if ( empty( $current['all_pass'] ) && ! empty( $previous['all_pass'] ) ) {
            return 'Diagnostics now show a new issue compared with the previous run. Review the failed checks before notifying.';
        }

        $current_failed  = isset( $current['failed_checks'] ) ? (int) $current['failed_checks'] : 0;
        $previous_failed = isset( $previous['failed_checks'] ) ? (int) $previous['failed_checks'] : 0;
        if ( $current_failed !== $previous_failed ) {
            return sprintf(
                'Diagnostics changed from %d failed check(s) to %d failed check(s) compared with the previous run.',
                $previous_failed,
                $current_failed
            );
        }
    }

    if ( array_key_exists( 'inserted', $current ) ) {
        $current_inserted  = isset( $current['inserted'] ) ? (int) $current['inserted'] : 0;
        $previous_inserted = isset( $previous['inserted'] ) ? (int) $previous['inserted'] : 0;
        if ( $current_inserted !== $previous_inserted ) {
            return sprintf(
                'Backfill inserted %d row(s) this time versus %d row(s) on the previous run.',
                $current_inserted,
                $previous_inserted
            );
        }

        $current_errors  = isset( $current['error_count'] ) ? (int) $current['error_count'] : 0;
        $previous_errors = isset( $previous['error_count'] ) ? (int) $previous['error_count'] : 0;
        if ( $current_errors !== $previous_errors ) {
            return sprintf(
                'Backfill error count changed from %d to %d compared with the previous run.',
                $previous_errors,
                $current_errors
            );
        }
    }

    return 'Latest run matches the previous saved result closely, so there is no major change to flag right now.';
}

function bntm_weimop_result_profile( $result_type, $settings = [] ) {
    $type = strtoupper( trim( (string) $result_type ) );
    $market_run = strtoupper( trim( (string) ( $settings['nmms_market_run'] ?? '' ) ) );

    switch ( $type ) {
        case 'RTD_LMP':
            $type = 'RTDLMP';
            break;
        case 'OCC_COMP':
            $type = 'OCCRESOURCECOMPLIANCEDETAIL';
            break;
        case 'HAP':
        case 'DAP':
        case 'WAP':
            $market_run = $type;
            $type = 'MPLMP';
            break;
    }

    $profile = [
        'type'           => $type,
        'table'          => 'RTDSchedules',
        'market_run'     => $market_run,
        'interval_shift' => 0,
    ];

    switch ( $type ) {
        case 'RTDSCHEDULES':
            $profile['table']      = 'RTDSchedules';
            $profile['market_run'] = $profile['market_run'] !== '' ? $profile['market_run'] : 'RTD';
            break;
        case 'RTDLMP':
            $profile['table']      = 'RTDSchedules';
            $profile['market_run'] = $profile['market_run'] !== '' ? $profile['market_run'] : 'RTD';
            break;
        case 'MPLMP':
            if ( $profile['market_run'] === 'HAP' ) {
                $profile['table']          = 'HAPResults';
                $profile['interval_shift'] = 5 * MINUTE_IN_SECONDS;
            } else {
                $profile['table']          = 'DAPResults';
                $profile['market_run']     = $profile['market_run'] !== '' ? $profile['market_run'] : 'DAP';
                $profile['interval_shift'] = HOUR_IN_SECONDS;
            }
            break;
        case 'TIPCLMP':
            $profile['table']          = 'DAPResults';
            $profile['interval_shift'] = HOUR_IN_SECONDS;
            break;
        case 'OCCRESOURCECOMPLIANCEDETAIL':
            $profile['table']      = 'OCCResourcesComplianceDetail';
            $profile['market_run'] = $profile['market_run'] !== '' ? $profile['market_run'] : 'RTD';
            break;
        default:
            $profile['table']      = 'RTDSchedules';
            $profile['market_run'] = $profile['market_run'] !== '' ? $profile['market_run'] : 'RTD';
            break;
    }

    return $profile;
}

function bntm_weimop_normalize_interval_value( $value, $result_type ) {
    $value = trim( (string) $value );
    if ( $value === '' ) return '';

    $ts = strtotime( $value );
    if ( ! $ts ) return $value;

    $profile = bntm_weimop_result_profile( $result_type );
    if ( ! empty( $profile['interval_shift'] ) ) {
        $ts += (int) $profile['interval_shift'];
    }

    return date( 'Y-m-d H:i:s', $ts );
}

/* -------------------------------------------------------
   B. MYSQL SCHEMA + OPEN
------------------------------------------------------- */

function bntm_weimop_db_tables() {
    global $wpdb;

    return [
        'RTDSchedules'                 => $wpdb->prefix . 'weimop_rtd_schedules',
        'HAPResults'                   => $wpdb->prefix . 'weimop_hap_results',
        'DAPResults'                   => $wpdb->prefix . 'weimop_dap_results',
        'OCCResourcesComplianceDetail' => $wpdb->prefix . 'weimop_occ_resources_compliance_detail',
        'ExtractorLog'                 => $wpdb->prefix . 'weimop_extractor_log',
        'weimop_watchlist'             => $wpdb->prefix . 'weimop_watchlist',
    ];
}

function bntm_weimop_table_name( $logical_name ) {
    $tables = bntm_weimop_db_tables();
    return $tables[ $logical_name ] ?? $logical_name;
}

function bntm_weimop_mysql_schema() {
    global $wpdb;

    $charset = $wpdb->get_charset_collate();
    $tables  = bntm_weimop_db_tables();

    return [
        'RTDSchedules' => "CREATE TABLE {$tables['RTDSchedules']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            TIME_INTERVAL DATETIME NOT NULL,
            SCHEDULE DOUBLE DEFAULT 0,
            LMP DOUBLE DEFAULT 0,
            PRICE_NODE VARCHAR(191) DEFAULT '',
            UNIT_ID VARCHAR(191) DEFAULT '',
            MARKET_RUN VARCHAR(64) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_time_interval (TIME_INTERVAL),
            KEY idx_time_interval (TIME_INTERVAL)
        ) {$charset};",
        'HAPResults' => "CREATE TABLE {$tables['HAPResults']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            TIME_INTERVAL DATETIME NOT NULL,
            PRICE DOUBLE DEFAULT 0,
            PRICE_NODE VARCHAR(191) DEFAULT '',
            MARKET_RUN VARCHAR(64) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_time_interval (TIME_INTERVAL),
            KEY idx_time_interval (TIME_INTERVAL)
        ) {$charset};",
        'DAPResults' => "CREATE TABLE {$tables['DAPResults']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            TIME_INTERVAL DATETIME NOT NULL,
            PRICE DOUBLE DEFAULT 0,
            PRICE_NODE VARCHAR(191) DEFAULT '',
            MARKET_RUN VARCHAR(64) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_time_interval (TIME_INTERVAL),
            KEY idx_time_interval (TIME_INTERVAL)
        ) {$charset};",
        'OCCResourcesComplianceDetail' => "CREATE TABLE {$tables['OCCResourcesComplianceDetail']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            TIME_INTERVAL DATETIME NOT NULL,
            OFFERED_CAP DOUBLE DEFAULT 0,
            SCHEDULED_CAP DOUBLE DEFAULT 0,
            UNIT_ID VARCHAR(191) DEFAULT '',
            REGION VARCHAR(191) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_time_interval (TIME_INTERVAL),
            KEY idx_time_interval (TIME_INTERVAL)
        ) {$charset};",
        'ExtractorLog' => "CREATE TABLE {$tables['ExtractorLog']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            run_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(32) DEFAULT 'ok',
            message TEXT NULL,
            rows_added BIGINT DEFAULT 0,
            PRIMARY KEY (id)
        ) {$charset};",
    ];
}

function bntm_weimop_ensure_data_tables() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( bntm_weimop_mysql_schema() as $sql ) dbDelta( $sql );
}

function bntm_weimop_open_db( $readonly = false ) {
    global $wpdb;

    bntm_weimop_ensure_data_tables();
    return isset( $wpdb ) ? $wpdb : null;
}

/* -------------------------------------------------------
   C. WP TABLE CONFIG
------------------------------------------------------- */

function bntm_weimop_get_pages()     { return [ 'WESM/EIMOP Trading Dashboard' => '[weimop_dashboard]' ]; }
function bntm_weimop_get_shortcodes(){ return [ 'weimop_dashboard' => 'bntm_shortcode_weimop' ]; }

function bntm_weimop_get_tables() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();
    $t = bntm_weimop_db_tables();
    return [
        'weimop_watchlist' => "CREATE TABLE {$t['weimop_watchlist']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            instrument VARCHAR(64) NOT NULL, note TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$c};",
    ];
}

function bntm_weimop_create_tables() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( bntm_weimop_get_tables() as $sql ) dbDelta( $sql );
    bntm_weimop_ensure_data_tables();
    bntm_weimop_open_db( false );
}

/* -------------------------------------------------------
   D. AJAX HOOKS
------------------------------------------------------- */

add_action( 'wp_ajax_weimop_get_market_snapshot', 'bntm_ajax_weimop_get_market_snapshot' );
add_action( 'wp_ajax_weimop_get_chart_series',    'bntm_ajax_weimop_get_chart_series'    );
add_action( 'wp_ajax_weimop_get_hap_series',      'bntm_ajax_weimop_get_hap_series'      );
add_action( 'wp_ajax_weimop_get_dap_series',      'bntm_ajax_weimop_get_dap_series'      );
add_action( 'wp_ajax_weimop_get_occ_series',      'bntm_ajax_weimop_get_occ_series'      );
add_action( 'wp_ajax_weimop_connection_status',   'bntm_ajax_weimop_connection_status'   );
add_action( 'wp_ajax_weimop_save_settings',       'bntm_ajax_weimop_save_settings'       );
add_action( 'wp_ajax_weimop_upload_pfx',          'bntm_ajax_weimop_upload_pfx'          );
add_action( 'wp_ajax_weimop_fetch_historical',    'bntm_ajax_weimop_fetch_historical'    );
add_action( 'wp_ajax_weimop_diag',               'bntm_ajax_weimop_diag'               );

/* -------------------------------------------------------
   E. SHORTCODE REGISTRATION
------------------------------------------------------- */

function bntm_weimop_register_shortcodes() {
    add_shortcode( 'weimop_dashboard', 'bntm_shortcode_weimop' );
}
add_action( 'init', 'bntm_weimop_register_shortcodes' );

/* -------------------------------------------------------
   F. MAIN SHORTCODE
   All CSS, external scripts, config, and JS are loaded
   directly here. No separate enqueue function.
   Config uses <script type="application/json"> so WP
   filters (wptexturize, wpautop) never touch it.
   JS is output as a PHP heredoc string, also filter-safe.
------------------------------------------------------- */

function bntm_shortcode_weimop() {
    if ( ! is_user_logged_in() ) return '<div class="bntm-notice">Please log in to access the WESM/EIMOP Dashboard.</div>';

    bntm_weimop_open_db( false );

    $uid        = get_current_user_id();
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'overview';
    $settings   = bntm_weimop_get_settings( $uid );

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var weimop_nonce = '<?php echo wp_create_nonce('weimop_nonce'); ?>';
    </script>

    <div class="wesm-container">
        <!-- Sidebar Nav (matching WESM) -->
        <nav class="wesm-sidebar">
            <div class="wesm-sidebar-brand">
                <div class="wesm-brand-icon">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </div>
                <div>
                    <div class="wesm-brand-title">WEIMOP</div>
                    <div class="wesm-brand-sub">Trading Monitor</div>
                </div>
            </div>

            <div class="wesm-sidebar-section">MONITORING</div>
            <a href="?tab=overview" class="wesm-nav-item <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                Live Dashboard
            </a>
            <a href="?tab=trading" class="wesm-nav-item <?php echo $active_tab === 'trading' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Trading Analytics
            </a>
            <a href="?tab=suggestions" class="wesm-nav-item <?php echo $active_tab === 'suggestions' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                Smart Suggestions
            </a>

            <div class="wesm-sidebar-section">CONFIGURATION</div>
            <a href="?tab=settings" class="wesm-nav-item <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                Settings
            </a>

            <div class="wesm-sidebar-footer">
                <div class="wesm-connection-status" id="weimop-connection-dot">
                    <span class="wesm-dot wesm-dot-checking"></span>
                    <span id="weimop-conn-label">Checking...</span>
                </div>
                <div class="wesm-update-label">Updates every 60s</div>
            </div>
        </nav>

        <!-- Main Content (matching WESM) -->
        <main class="wesm-main">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo weimop_tab_overview(); ?>
            <?php elseif ($active_tab === 'trading'): ?>
                <?php echo weimop_tab_trading(); ?>
            <?php elseif ($active_tab === 'suggestions'): ?>
                <?php echo weimop_tab_suggestions(); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo weimop_tab_settings( $uid, $settings ); ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Include WESM styles for consistency -->
    <style>
    :root {
        --wesm-bg:         #0b0f1a;
        --wesm-surface:    #111827;
        --wesm-surface2:   #1a2235;
        --wesm-border:     rgba(255,255,255,0.07);
        --wesm-accent:     #00d4ff;
        --wesm-accent2:    #0ea5e9;
        --wesm-success:    #22c55e;
        --wesm-warning:    #f59e0b;
        --wesm-danger:     #ef4444;
        --wesm-text:       #e2e8f0;
        --wesm-muted:      #64748b;
        --wesm-sidebar-w:  230px;
    }

    .wesm-container { display:flex; min-height:600px; background:var(--wesm-bg); color:var(--wesm-text); font-family:'Segoe UI',system-ui,sans-serif; border-radius:12px; overflow:hidden; }
    .wesm-sidebar { width:var(--wesm-sidebar-w); background:var(--wesm-surface); border-right:1px solid var(--wesm-border); display:flex; flex-direction:column; flex-shrink:0; }
    .wesm-sidebar-brand { display:flex; align-items:center; gap:10px; padding:20px 16px; border-bottom:1px solid var(--wesm-border); }
    .wesm-brand-icon { width:40px; height:40px; background:linear-gradient(135deg,#0ea5e9,#00d4ff); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; flex-shrink:0; }
    .wesm-brand-title { font-size:14px; font-weight:700; letter-spacing:2px; color:var(--wesm-accent); }
    .wesm-brand-sub { font-size:10px; color:var(--wesm-muted); }
    .wesm-sidebar-section { font-size:10px; font-weight:600; letter-spacing:1.5px; color:var(--wesm-muted); padding:16px 16px 6px; text-transform:uppercase; }
    .wesm-nav-item { display:flex; align-items:center; gap:10px; padding:10px 16px; color:var(--wesm-muted); text-decoration:none; font-size:13px; border-radius:0; transition:all 0.15s; position:relative; }
    .wesm-nav-item:hover { color:var(--wesm-text); background:rgba(255,255,255,0.04); text-decoration:none; }
    .wesm-nav-item.active { color:var(--wesm-accent); background:rgba(0,212,255,0.08); }
    .wesm-nav-item.active::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--wesm-accent); border-radius:0 3px 3px 0; }
    .wesm-sidebar-footer { margin-top:auto; padding:16px; border-top:1px solid var(--wesm-border); }
    .wesm-connection-status { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--wesm-muted); margin-bottom:6px; }
    .wesm-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .wesm-dot-ok       { background:var(--wesm-success); box-shadow:0 0 6px var(--wesm-success); }
    .wesm-dot-error    { background:var(--wesm-danger); }
    .wesm-dot-checking { background:var(--wesm-warning); animation:pulse 1s infinite; }
    .wesm-update-label { font-size:10px; color:var(--wesm-muted); }
    .wesm-main { flex:1; overflow:auto; background:var(--wesm-bg); }
    .wesm-page { padding:24px; }
    .wesm-page-header { margin-bottom:24px; }
    .wesm-page-title { font-size:20px; font-weight:700; color:var(--wesm-text); margin:0 0 4px; }
    .wesm-page-sub { font-size:13px; color:var(--wesm-muted); }
    .wesm-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:24px; }
    .wesm-metric { background:var(--wesm-surface); border:1px solid var(--wesm-border); border-radius:10px; padding:18px; position:relative; overflow:hidden; transition:border-color 0.3s; }
    .wesm-metric::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:var(--wesm-accent); }
    .wesm-metric-label { font-size:11px; font-weight:600; letter-spacing:1px; color:var(--wesm-muted); text-transform:uppercase; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
    .wesm-metric-value { font-size:28px; font-weight:700; font-variant-numeric:tabular-nums; color:var(--wesm-text); line-height:1; margin-bottom:4px; }
    .wesm-metric-unit  { font-size:12px; color:var(--wesm-muted); }
    .wesm-chart-card { background:var(--wesm-surface); border:1px solid var(--wesm-border); border-radius:10px; padding:20px; margin-bottom:24px; }
    .wesm-chart-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
    .wesm-chart-title { font-size:14px; font-weight:600; color:var(--wesm-text); }
    #weimop-rtd-chart, #weimop-hap-chart, #weimop-dap-chart, #weimop-occ-chart { width:100%!important; height:280px!important; }
    .weimop-chart-wrap { width:100%; height:280px; position:relative; }
    @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
    </style>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
    (function() {
        var chartRtd = null, chartHap = null, chartDap = null, chartOcc = null;
        var liveInterval = null;
        var refreshBtn = null;

        function setConnectionStatus(state) {
            var dot = document.querySelector('#weimop-connection-dot .wesm-dot');
            var label = document.getElementById('weimop-conn-label');
            if (!dot) return;
            dot.className = 'wesm-dot';
            if (state === 'ok') {
                dot.classList.add('wesm-dot-ok');
                if (label) label.textContent = 'Connected';
            } else if (state === 'error') {
                dot.classList.add('wesm-dot-error');
                if (label) label.textContent = 'No Data';
            } else {
                dot.classList.add('wesm-dot-checking');
                if (label) label.textContent = 'Checking...';
            }
        }

        function post(action) {
            var fd = new FormData();
            fd.append('action', action);
            fd.append('nonce', weimop_nonce);
            return fetch(ajaxurl, {method:'POST', body:fd}).then(function(r){ return r.json(); });
        }

        function setMetric(id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '---';
        }

        function formatTimeLabel(unixTs) {
            var d = new Date(unixTs * 1000);
            return d.toLocaleString('en-PH', {
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function upsertChart(currentChart, canvasId, config) {
            var canvas = document.getElementById(canvasId);
            if (!canvas || !window.Chart) return currentChart;

            if (currentChart) {
                currentChart.data = config.data;
                currentChart.options = config.options;
                currentChart.update();
                return currentChart;
            }

            return new Chart(canvas.getContext('2d'), config);
        }

        window.weimopLoadData = function() {
            var lastUpdate = document.getElementById('weimop-last-update');
            refreshBtn = refreshBtn || document.querySelector('button[onclick="weimopLoadData()"]');

            if (lastUpdate) lastUpdate.textContent = 'Refreshing...';
            if (refreshBtn) refreshBtn.disabled = true;
            setConnectionStatus('checking');

            Promise.all([
                post('weimop_get_market_snapshot'),
                post('weimop_get_chart_series'),
                post('weimop_get_hap_series'),
                post('weimop_get_dap_series'),
                post('weimop_get_occ_series')
            ])
            .then(function(results) {
                var snapshot = results[0];
                var rtd = results[1];
                var hap = results[2];
                var dap = results[3];
                var occ = results[4];

                if (!snapshot.success) {
                    setConnectionStatus('error');
                    if (lastUpdate) lastUpdate.textContent = 'Snapshot unavailable';
                    return;
                }

                var snap = snapshot.data || {};
                setMetric('rtd-value', snap.rtd_schedule);
                setMetric('rtd-price-value', snap.lmp_price);
                setMetric('hap-value', snap.hap_price);
                setMetric('dap-value', snap.dap_price);
                setMetric('occ-value', snap.offered_cap);

                var rtdRows = (rtd.success && rtd.data) ? (rtd.data.rtd || []) : [];
                var lmpRows = (rtd.success && rtd.data) ? (rtd.data.lmp || []) : [];
                var hapRows = (hap.success && hap.data) ? (hap.data.hap || []) : [];
                var dapRows = (dap.success && dap.data) ? (dap.data.dap || []) : [];
                var occOfferedRows = (occ.success && occ.data) ? (occ.data.offered || []) : [];
                var occScheduledRows = (occ.success && occ.data) ? (occ.data.scheduled || []) : [];

                var hasAnyData = rtdRows.length || lmpRows.length || hapRows.length || dapRows.length || occOfferedRows.length || occScheduledRows.length;
                setConnectionStatus(hasAnyData ? 'ok' : 'error');

                chartRtd = upsertChart(chartRtd, 'weimop-rtd-chart', {
                    type: 'line',
                    data: {
                        labels: rtdRows.map(function(row) { return formatTimeLabel(row.time); }),
                        datasets: [
                            {
                                label: 'RTD Schedule (MW)',
                                data: rtdRows.map(function(row) { return row.value; }),
                                borderColor: '#00d4ff',
                                backgroundColor: 'rgba(0, 212, 255, 0.16)',
                                tension: 0.25,
                                borderWidth: 2,
                                yAxisID: 'y'
                            },
                            {
                                label: 'LMP (PHP/MWh)',
                                data: lmpRows.map(function(row) { return row.value; }),
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245, 158, 11, 0.12)',
                                tension: 0.25,
                                borderWidth: 2,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { labels: { color: '#e2e8f0' } } },
                        scales: {
                            x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.06)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.06)' } },
                            y1: { position: 'right', ticks: { color: '#94a3b8' }, grid: { drawOnChartArea: false } }
                        }
                    }
                });

                chartHap = upsertChart(chartHap, 'weimop-hap-chart', {
                    type: 'line',
                    data: {
                        labels: hapRows.map(function(row) { return formatTimeLabel(row.time); }),
                        datasets: [{
                            label: 'HAP (PHP/MWh)',
                            data: hapRows.map(function(row) { return row.value; }),
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.12)',
                            tension: 0.25,
                            borderWidth: 2,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#e2e8f0' } } },
                        scales: {
                            x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.06)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.06)' } }
                        }
                    }
                });

                chartDap = upsertChart(chartDap, 'weimop-dap-chart', {
                    type: 'line',
                    data: {
                        labels: dapRows.map(function(row) { return formatTimeLabel(row.time); }),
                        datasets: [{
                            label: 'DAP (PHP/MWh)',
                            data: dapRows.map(function(row) { return row.value; }),
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.12)',
                            tension: 0.25,
                            borderWidth: 2,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#e2e8f0' } } },
                        scales: {
                            x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.06)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.06)' } }
                        }
                    }
                });

                chartOcc = upsertChart(chartOcc, 'weimop-occ-chart', {
                    type: 'line',
                    data: {
                        labels: occOfferedRows.map(function(row) { return formatTimeLabel(row.time); }),
                        datasets: [
                            {
                                label: 'Offered Capacity (MW)',
                                data: occOfferedRows.map(function(row) { return row.value; }),
                                borderColor: '#fb923c',
                                backgroundColor: 'rgba(251, 146, 60, 0.12)',
                                tension: 0.25,
                                borderWidth: 2
                            },
                            {
                                label: 'Scheduled Capacity (MW)',
                                data: occScheduledRows.map(function(row) { return row.value; }),
                                borderColor: '#94a3b8',
                                backgroundColor: 'rgba(148, 163, 184, 0.08)',
                                tension: 0.25,
                                borderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#e2e8f0' } } },
                        scales: {
                            x: { ticks: { color: '#94a3b8', maxTicksLimit: 8 }, grid: { color: 'rgba(255,255,255,0.06)' } },
                            y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.06)' } }
                        }
                    }
                });

                if (lastUpdate) {
                    lastUpdate.textContent = snap.last_interval && snap.last_interval !== '--'
                        ? 'Last interval: ' + snap.last_interval
                        : 'Updated ' + new Date().toLocaleTimeString('en-PH');
                }
            })
            .catch(function() {
                setConnectionStatus('error');
                if (lastUpdate) lastUpdate.textContent = 'Refresh failed';
            })
            .finally(function() {
                if (refreshBtn) refreshBtn.disabled = false;
            });
        };

        weimopLoadData();
        liveInterval = setInterval(weimopLoadData, 60000);
    })();
    </script>
    <?php
    $out = ob_get_clean();
    return $out;
}

/* -------------------------------------------------------
   G. TAB -- OVERVIEW
------------------------------------------------------- */

function weimop_tab_overview() { ob_start(); ?>
    <div class="wesm-page wesm-animate">
        <div class="wesm-page-header">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h2 class="wesm-page-title">Live Dashboard</h2>
                    <p class="wesm-page-sub">Real-time WESM/EIMOP market data &mdash; Trading Analytics</p>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:11px;color:var(--wesm-muted);" id="weimop-last-update">Loading...</span>
                    <button class="wesm-btn wesm-btn-secondary wesm-btn-sm" onclick="weimopLoadData()">
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Market Snapshot KPIs -->
        <div class="wesm-metrics">
            <div class="wesm-metric" id="metric-rtd">
                <div class="wesm-metric-label">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    RTD Schedule
                </div>
                <div class="wesm-metric-value" id="rtd-value">---</div>
                <div class="wesm-metric-unit">MW</div>
            </div>

            <div class="wesm-metric" id="metric-rtd-price">
                <div class="wesm-metric-label">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
                    RTD LMP Price
                </div>
                <div class="wesm-metric-value" id="rtd-price-value">---</div>
                <div class="wesm-metric-unit">PHP/MWh</div>
            </div>

            <div class="wesm-metric" id="metric-hap">
                <div class="wesm-metric-label">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    HAP Price
                </div>
                <div class="wesm-metric-value" id="hap-value">---</div>
                <div class="wesm-metric-unit">PHP/MWh</div>
            </div>

            <div class="wesm-metric" id="metric-dap">
                <div class="wesm-metric-label">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    DAP Price
                </div>
                <div class="wesm-metric-value" id="dap-value">---</div>
                <div class="wesm-metric-unit">PHP/MWh</div>
            </div>

            <div class="wesm-metric" id="metric-occ">
                <div class="wesm-metric-label">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    Offered Capacity
                </div>
                <div class="wesm-metric-value" id="occ-value">---</div>
                <div class="wesm-metric-unit">MW</div>
            </div>
        </div>

        <!-- Charts matching WESM style -->
        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">RTD Schedule vs LMP Price</div>
            </div>
            <canvas id="weimop-rtd-chart"></canvas>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">HAP (Hour-Ahead Market) Price</div>
            </div>
            <canvas id="weimop-hap-chart"></canvas>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">DAP (Day-Ahead Market) Price</div>
            </div>
            <canvas id="weimop-dap-chart"></canvas>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">OCC (Offered Capacity)</div>
            </div>
            <canvas id="weimop-occ-chart"></canvas>
        </div>
    </div>
    <?php return ob_get_clean();
}

/* -------------------------------------------------------
   H. TAB -- TRADING / SUGGESTIONS
------------------------------------------------------- */

function weimop_tab_trading() { ob_start(); ?>
    <div class="wesm-page">
        <div class="wesm-page-header">
            <h2 class="wesm-page-title">Trading Analytics</h2>
            <p class="wesm-page-sub">Order blotter, position management, PnL and settlement preview</p>
        </div>
        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">Trading Workspace</div>
            </div>
            <p style="color:var(--wesm-muted);padding:20px;">This feature is scheduled for the next release. Check back soon for order management, position tracking, and PnL analytics.</p>
        </div>
    </div>
<?php return ob_get_clean(); }

function weimop_tab_suggestions() { ob_start(); ?>
    <div class="wesm-page">
        <div class="wesm-page-header">
            <h2 class="wesm-page-title">Feature Roadmap</h2>
            <p class="wesm-page-sub">Suggested additions for WESM/EIMOP trading platform</p>
        </div>
        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">Planned Features</div>
            </div>
            <div style="padding:20px;color:var(--wesm-text);">
                <ol style="line-height:1.8;margin:0;padding-left:20px;">
                    <li style="margin-bottom:12px;">NMMS MPI extractor scheduler and health monitor</li>
                    <li style="margin-bottom:12px;">RTD Schedule vs LMP with +1.5% / -3.0% OCC compliance bands</li>
                    <li style="margin-bottom:12px;">Per-interval OCC compliance alerts and threshold breach engine</li>
                    <li style="margin-bottom:12px;">Rule-based strategy cards for WESM/EIMOP dispatch decisions</li>
                    <li style="margin-bottom:12px;">Daily settlement and reconciliation panel</li>
                    <li>HAP/DAP spread alert when DAP exceeds HAP by a configurable margin</li>
                </ol>
            </div>
        </div>
    </div>
<?php return ob_get_clean(); }

/* -------------------------------------------------------
   I. TAB -- SETTINGS
------------------------------------------------------- */

function weimop_tab_settings( $uid, $s ) {
    $status  = bntm_weimop_check_connection();
    $ok      = empty( $status['issues'] );
    $tables  = bntm_weimop_db_tables();
    $pfx_ok  = ! empty( $s['nmms_cert_path'] ) && file_exists( $s['nmms_cert_path'] );

    ob_start(); ?>
    <div class="wesm-page">
        <div class="wesm-page-header">
            <h2 class="wesm-page-title">Settings</h2>
            <p class="wesm-page-sub">Configure WESM/EIMOP connection and database</p>
        </div>

        <!-- Database Configuration -->
        <div class="wesm-chart-card">
            <div class="wesm-chart-header">
                <div class="wesm-chart-title">MySQL Database Configuration</div>
            </div>
            <div style="padding:20px;color:var(--wesm-text);">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tr style="border-bottom:1px solid var(--wesm-border);">
                        <td style="padding:12px 0;">Database Engine</td>
                        <td style="padding:12px 0;"><code style="background:var(--wesm-surface2);padding:4px 8px;border-radius:4px;">WordPress / MySQL</code></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--wesm-border);">
                        <td style="padding:12px 0;">RTD Schedules Table</td>
                        <td style="padding:12px 0;"><code style="background:var(--wesm-surface2);padding:4px 8px;border-radius:4px;"><?php echo esc_html( $tables['RTDSchedules'] ); ?></code></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--wesm-border);">
                        <td style="padding:12px 0;">HAP Results Table</td>
                        <td style="padding:12px 0;"><code style="background:var(--wesm-surface2);padding:4px 8px;border-radius:4px;"><?php echo esc_html( $tables['HAPResults'] ); ?></code></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--wesm-border);">
                        <td style="padding:12px 0;">DAP Results Table</td>
                        <td style="padding:12px 0;"><code style="background:var(--wesm-surface2);padding:4px 8px;border-radius:4px;"><?php echo esc_html( $tables['DAPResults'] ); ?></code></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--wesm-border);">
                        <td style="padding:12px 0;">OCC Records Table</td>
                        <td style="padding:12px 0;"><code style="background:var(--wesm-surface2);padding:4px 8px;border-radius:4px;"><?php echo esc_html( $tables['OCCResourcesComplianceDetail'] ); ?></code></td>
                    </tr>
                    <tr>
                        <td style="padding:12px 0;">Status</td>
                        <td style="padding:12px 0;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--wesm-success);margin-right:8px;"></span>Connected</td>
                    </tr>
                </table>
                <div style="margin-top:20px;">
                    <form method="post">
                        <?php wp_nonce_field('weimop_bootstrap_db'); ?>
                        <input type="hidden" name="weimop_action" value="bootstrap_db">
                        <button type="submit" class="wesm-btn wesm-btn-secondary" style="display:inline-flex;">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                            Re-initialize Tables
                        </button>
                    </form>
                </div>
                <?php
                if ( isset($_POST['weimop_action']) && $_POST['weimop_action'] === 'bootstrap_db' && check_admin_referer('weimop_bootstrap_db') ) {
                    $db = bntm_weimop_open_db(false);
                    echo $db
                        ? '<p style="color:var(--wesm-success);margin-top:12px;">✓ Tables re-initialized successfully.</p>'
                        : '<p style="color:var(--wesm-danger);margin-top:12px;">✗ Re-initialization failed. Verify the WordPress database connection.</p>';
                }
                ?>
            </div>
        </div>
    </div>



    <!-- PFX / OpenSSL Diagnostics -->
    <div class="wesm-chart-card">
        <div class="wesm-chart-header">
            <div class="wesm-chart-title">PFX &amp; OpenSSL Diagnostics</div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap;gap:12px;">
                <input type="password" id="weimop-diag-password" class="wesm-input wesm-input-sm" placeholder="Certificate password">
                <button type="button" id="weimop-diag-btn" class="wesm-btn wesm-btn-primary wesm-btn-sm">Run Diagnostics</button>
            </div>
        </div>
        <p style="color:var(--wesm-muted);font-size:12px;margin:12px 0;">
            Runs a server-side check of PHP extensions, the .pfx file, and password parsing.
            If <strong>Fetch Previous 24h Data</strong> fails with a password error, run this first.
        </p>
        <div id="weimop-diag-notice" style="display:none;margin-bottom:12px;"></div>
        <div id="weimop-diag-results" style="display:none;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;" id="weimop-diag-table"></table>
        </div>
        <div id="weimop-diag-history" style="display:none;margin-top:12px;"></div>
    </div>

    <!-- Connection status -->
    <div class="wesm-chart-card" id="weimop-connection-card">
        <div class="wesm-chart-header">
            <div class="wesm-chart-title">
                <span style="width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:8px;background:<?php echo $ok ? 'var(--wesm-success)' : 'var(--wesm-warning)'; ?>;"></span>
                <?php echo $ok ? 'Connection Ready' : 'Setup Incomplete'; ?>
            </div>
            <?php if ( $ok ): ?>
            <div style="margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:flex-end;gap:8px;">
                    <input type="password" id="weimop-backfill-password" class="wesm-input wesm-input-sm" placeholder="Certificate password">
                    <button type="button" id="weimop-backfill-btn" class="wesm-btn wesm-btn-primary wesm-btn-sm">Fetch Previous 24h Data</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php if ( ! $ok ): ?>
        <p style="color:var(--wesm-muted);font-size:12px;margin-bottom:12px;">Resolve the items below before attempting a data connection.</p>
        <ul style="margin:0;padding-left:18px;color:var(--wesm-text);">
            <?php foreach ( $status['issues'] as $issue ): ?>
            <li style="font-size:12px;margin-bottom:6px;color:var(--wesm-warning);"><?php echo esc_html($issue); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p style="color:var(--wesm-muted);font-size:12px;">All prerequisite checks passed. Enter your certificate password and click <strong>Fetch Previous 24h Data</strong> to backfill the database, or wait for your NMMS MPI extractor to populate it automatically.</p>
        <?php endif; ?>
        <div id="weimop-backfill-status" style="display:none;margin-top:12px;padding:10px;border-radius:6px;font-size:12px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);color:var(--wesm-success);"></div>
        <div id="weimop-backfill-history" style="display:none;margin-top:12px;"></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:16px;">
            <?php foreach ( ['sqlite3_ext'=>'WordPress DB connection','db_exists'=>'MySQL storage','db_writable'=>'MySQL tables writable','tables_exist'=>'All WESM tables','has_rtd'=>'RTDSchedules has data','has_hap'=>'HAPResults has data','has_dap'=>'DAPResults has data','has_occ'=>'OCC table has data','pfx_set'=>'Certificate path set','pfx_exists'=>'Certificate file exists','curl_ssl'=>'cURL + OpenSSL','nmms_url'=>'NMMS URL set','cert_name'=>'Certificate Name set'] as $key=>$label ):
                $pass = $status['checks'][$key]??false; ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:11px;padding:6px 10px;border-radius:5px;background:<?php echo $pass?'rgba(34,197,94,0.15)':'rgba(239,68,68,0.15)'; ?>;color:<?php echo $pass?'var(--wesm-success)':'var(--wesm-danger)'; ?>;">
                <span><?php echo $pass?'✓':'✗'; ?></span><?php echo esc_html($label); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Certificate upload -->
    <div class="wesm-chart-card">
        <div class="wesm-chart-header">
            <div class="wesm-chart-title">NMMS Certificate Upload (.pfx / .p12)</div>
        </div>
        <p style="color:var(--wesm-muted);font-size:12px;margin:12px 0 16px;">Upload your IEMOP NMMS MPI mutual-TLS certificate. The file is stored in <code style="background:var(--wesm-surface2);padding:3px 6px;border-radius:4px;color:var(--wesm-accent);">uploads/<?php echo esc_html(BNTM_WEIMOP_PFX_SUBDIR); ?>/</code> with HTTP access blocked.</p>
        <?php if ( $pfx_ok ): ?>
        <div style="margin-bottom:12px;padding:10px;border-radius:6px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);color:var(--wesm-success);font-size:12px;">
            <strong>Current:</strong> <?php echo esc_html(basename($s['nmms_cert_path'])); ?>
        </div>
        <?php endif; ?>
        <div id="weimop-pfx-drop-zone" style="border:2px dashed var(--wesm-border);border-radius:8px;padding:24px;text-align:center;background:rgba(0,212,255,0.05);cursor:pointer;transition:all 0.2s;margin-bottom:12px;">
            <p id="weimop-pfx-drop-label" style="margin:0;font-size:13px;color:var(--wesm-muted);">Drag and drop a .pfx or .p12 file here, or click to browse</p>
            <input type="file" id="weimop-pfx-file-input" accept=".pfx,.p12" style="display:none;">
        </div>
        <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
                <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Certificate Password</label>
                <input type="password" id="weimop-pfx-password" class="wesm-input" placeholder="Leave blank if none">
            </div>
            <button type="button" class="wesm-btn wesm-btn-primary" id="weimop-pfx-upload-btn" disabled>Upload and Read</button>
        </div>
        <div id="weimop-pfx-progress" style="display:none;margin-bottom:12px;">
            <div style="height:4px;background:var(--wesm-surface2);border-radius:3px;overflow:hidden;">
                <div id="weimop-pfx-bar" style="height:100%;background:var(--wesm-accent);transition:width 0.25s;width:0%;"></div>
            </div>
            <span id="weimop-pfx-progress-label" style="font-size:11px;color:var(--wesm-muted);display:block;margin-top:4px;">Uploading...</span>
        </div>
        <div id="weimop-pfx-status" style="display:none;margin-bottom:12px;padding:10px;border-radius:6px;font-size:12px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.3);color:var(--wesm-success);"></div>
        <div id="weimop-pfx-preview" style="display:none;margin-bottom:12px;background:var(--wesm-surface2);border:1px solid var(--wesm-border);border-radius:6px;padding:12px;font-size:11px;"></div>
        <button type="button" class="wesm-btn wesm-btn-secondary" id="weimop-pfx-apply-btn" style="display:none;">Apply Certificate Info to Fields Below</button>
    </div>

    <!-- Settings form -->
    <form id="weimop-settings-form">
        <div class="wesm-chart-card">
            <div class="wesm-chart-header"><div class="wesm-chart-title">Email Alerts</div></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Sender Address</label>
                    <input type="text" class="wesm-input" name="email_sender" value="<?php echo esc_attr($s['email_sender']); ?>">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Password <span style="font-size:10px;color:var(--wesm-muted);">(leave blank to keep)</span></label>
                    <input type="password" class="wesm-input" name="email_password" value="">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Recipients <span style="font-size:10px;color:var(--wesm-muted);">(comma-separated)</span></label>
                    <input type="text" class="wesm-input" name="email_recipients" value="<?php echo esc_attr($s['email_recipients']); ?>">
                </div>
            </div>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header"><div class="wesm-chart-title">Dashboard Settings</div></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Price Alert <span style="font-size:10px;color:var(--wesm-muted);">(PHP/MWh)</span></label>
                    <input type="number" class="wesm-input" step="0.01" name="price_alert" value="<?php echo esc_attr($s['price_alert']); ?>">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Refresh Interval <span style="font-size:10px;color:var(--wesm-muted);">(seconds, min 60)</span></label>
                    <input type="number" class="wesm-input" step="1" min="60" name="refresh_interval" value="<?php echo esc_attr($s['refresh_interval']); ?>">
                </div>
            </div>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header"><div class="wesm-chart-title">NMMS MPI — Connection</div></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Main URL <?php echo empty($s['nmms_main_url'])?'<span style="font-size:10px;color:var(--wesm-warning);">Missing</span>':'<span style="font-size:10px;color:var(--wesm-success);">Set</span>'; ?></label>
                    <input type="text" class="wesm-input" name="nmms_main_url" value="<?php echo esc_attr($s['nmms_main_url']); ?>">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Backup URL</label>
                    <input type="text" class="wesm-input" name="nmms_backup_url" value="<?php echo esc_attr($s['nmms_backup_url']); ?>">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Service URL</label>
                    <input type="text" class="wesm-input" name="nmms_url" value="<?php echo esc_attr($s['nmms_url']); ?>">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Operation <span style="font-size:10px;color:var(--wesm-muted);">(fixed)</span></label>
                    <input type="text" class="wesm-input" name="nmms_operation" value="<?php echo esc_attr($s['nmms_operation']); ?>">
                </div>
            </div>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header"><div class="wesm-chart-title">NMMS MPI — Certificate <span style="font-size:11px;color:var(--wesm-muted);">(auto-filled on upload)</span></div></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Cert Name (CN) <?php echo empty($s['nmms_cert_name'])?'<span style="font-size:10px;color:var(--wesm-warning);">Missing</span>':'<span style="font-size:10px;color:var(--wesm-success);">Set</span>'; ?></label>
                    <input type="text" class="wesm-input" name="nmms_cert_name" id="weimop-cert-name" value="<?php echo esc_attr($s['nmms_cert_name']); ?>">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Cert File Path <?php if ($pfx_ok) echo '<span style="font-size:10px;color:var(--wesm-success);">Found</span>'; elseif (!empty($s['nmms_cert_path'])) echo '<span style="font-size:10px;color:var(--wesm-warning);">Not found</span>'; else echo '<span style="font-size:10px;color:var(--wesm-warning);">Upload above</span>'; ?></label>
                    <input type="text" class="wesm-input" name="nmms_cert_path" id="weimop-cert-path" value="<?php echo esc_attr($s['nmms_cert_path']); ?>" placeholder="Auto-filled on upload">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Cert Password <span style="font-size:10px;color:var(--wesm-muted);">(leave blank)</span></label>
                    <input type="password" class="wesm-input" name="nmms_cert_password" id="weimop-cert-password" value="">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Friendly Name</label>
                    <input type="text" class="wesm-input" name="nmms_friendly_name" id="weimop-friendly-name" value="<?php echo esc_attr($s['nmms_friendly_name']); ?>">
                </div>
            </div>
        </div>

        <div class="wesm-chart-card">
            <div class="wesm-chart-header"><div class="wesm-chart-title">NMMS MPI — Request Parameters</div></div>
            <p style="color:var(--wesm-muted);font-size:11px;margin:0 0 12px;">Values sent in SOAP exportResults request to IEMOP NMMS MPI service.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <?php foreach (['nmms_result_type'=>'Result Type','nmms_market_run'=>'Market Run','nmms_region_name'=>'Region Name','nmms_run_time'=>'Run Time','nmms_commodity'=>'Commodity','nmms_price_node'=>'Price Node','nmms_unit_id'=>'Unit ID','nmms_export_conf'=>'ExportResultsConf Path','nmms_interval_end'=>'Interval End (auto)'] as $name=>$label): ?>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;"><?php echo esc_html($label); ?></label>
                    <input type="text" class="wesm-input" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($s[$name]); ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </form>

    <div style="display:flex;align-items:center;gap:14px;margin-top:20px;">
        <button type="button" class="wesm-btn wesm-btn-primary" id="weimop-save-settings">Save Settings</button>
        <span id="weimop-settings-msg" style="display:none;font-size:12px;padding:8px 12px;border-radius:6px;"></span>
    </div>

    <?php return ob_get_clean();
}

/* -------------------------------------------------------
   J. NMMS MPI SOAP CALLER
   Extracts cert+key from .pfx to temp PEM files,
   calls IEMOP via cURL with mutual TLS, cleans up.
   $cert_pass must be the RAW plain-text password
   (no sanitize_text_field, no htmlspecialchars).
------------------------------------------------------- */


/* -------------------------------------------------------
   PFX -> PEM EXTRACTION HELPER
   OpenSSL 3.x dropped legacy encryption (RC2/3DES) used
   by older Windows PFX exports. We try three methods:

   Method 1: openssl_pkcs12_read()
             Works if PHP was compiled with legacy OpenSSL
             support or the PFX uses modern encryption.

   Method 2: shell `openssl pkcs12 -legacy`
             Uses the system openssl binary with the
             -legacy flag (available in OpenSSL 3.x).
             Requires exec() or shell_exec() to be enabled.

   Method 3: Return native_pfx=true
             Tell cURL to read the PFX directly via
             CURLOPT_SSLCERTTYPE=P12. cURL uses its own
             OpenSSL context which often has legacy support
             enabled even when PHP does not.
             This is the most reliable fallback.
------------------------------------------------------- */

function bntm_weimop_extract_pem_from_pfx( $pfx_path, $pfx_pass, $cert_file, $key_file ) {
    $pfx_data = file_get_contents( $pfx_path );
    if ( $pfx_data === false ) {
        bntm_weimop_log_event( 'CERT', 'ERROR', 'Cannot read certificate file.', [ 'path' => $pfx_path ] );
        return [ 'error' => 'Cannot read certificate file at: ' . $pfx_path ];
    }

    // Method 1: openssl_pkcs12_read (PHP built-in)
    if ( function_exists('openssl_pkcs12_read') ) {
        $certs = [];
        // Suppress errors — we handle them manually
        $ok = @openssl_pkcs12_read( $pfx_data, $certs, $pfx_pass );
        if ( $ok && ! empty($certs['cert']) && ! empty($certs['pkey']) ) {
            file_put_contents( $cert_file, $certs['cert'] );
            file_put_contents( $key_file,  $certs['pkey'] );
            return [ 'method' => 1, 'native_pfx' => false ];
        }
    }

    // Method 2: shell openssl binary with -legacy flag
    // Only attempt if exec() is available and not disabled
    if ( function_exists('exec') && ! in_array('exec', array_map('trim', explode(',', ini_get('disable_functions')))) ) {
        $openssl_bin  = bntm_weimop_find_openssl_binary();
        if ( $openssl_bin === '' ) {
            bntm_weimop_log_event( 'CERT', 'ERROR', 'OpenSSL command-line binary not found on server.' );
            return [ 'error' => 'OpenSSL command-line binary not found on this server.' ];
        }
        $openssl_cmd  = $openssl_bin === 'openssl' ? 'openssl' : escapeshellarg( $openssl_bin );
        $passin_arg   = bntm_weimop_build_openssl_passin_arg( $pfx_pass );
        $pfx_escaped  = escapeshellarg( $pfx_path );
        $cert_escaped = escapeshellarg( $cert_file );
        $key_escaped  = escapeshellarg( $key_file );

        // Try with -legacy (OpenSSL 3.x)
        $cmd_cert = "{$openssl_cmd} pkcs12 -legacy -in {$pfx_escaped} -clcerts -nokeys -out {$cert_escaped} -passin {$passin_arg} 2>&1";
        $cmd_key  = "{$openssl_cmd} pkcs12 -legacy -in {$pfx_escaped} -nocerts -nodes  -out {$key_escaped}  -passin {$passin_arg} 2>&1";
        exec( $cmd_cert, $out1, $rc1 );
        exec( $cmd_key,  $out2, $rc2 );

        if ( $rc1 === 0 && $rc2 === 0 && file_exists($cert_file) && filesize($cert_file) > 0 ) {
            return [ 'method' => 2, 'native_pfx' => false ];
        }

        // Also try without -legacy (in case PFX uses modern encryption)
        $cmd_cert2 = "{$openssl_cmd} pkcs12 -in {$pfx_escaped} -clcerts -nokeys -out {$cert_escaped} -passin {$passin_arg} 2>&1";
        $cmd_key2  = "{$openssl_cmd} pkcs12 -in {$pfx_escaped} -nocerts -nodes  -out {$key_escaped}  -passin {$passin_arg} 2>&1";
        exec( $cmd_cert2, $out3, $rc3 );
        exec( $cmd_key2,  $out4, $rc4 );

        if ( $rc3 === 0 && $rc4 === 0 && file_exists($cert_file) && filesize($cert_file) > 0 ) {
            return [ 'method' => '2b', 'native_pfx' => false ];
        }

        bntm_weimop_log_event( 'CERT', 'WARN', 'PEM extraction via shell openssl failed.', [
            'openssl' => $openssl_bin,
            'legacy_cert_rc' => $rc1 ?? null,
            'legacy_key_rc' => $rc2 ?? null,
            'modern_cert_rc' => $rc3 ?? null,
            'modern_key_rc' => $rc4 ?? null,
        ] );
    }

    // Method 3: Let cURL read the PFX natively via CURLOPT_SSLCERTTYPE=P12
    // cURL's OpenSSL context often has legacy algorithms enabled.
    // No file extraction needed — signal caller to use P12 mode.
    return [ 'method' => 3, 'native_pfx' => true ];
}

function bntm_weimop_nmms_soap_request( $s, $result_type, $interval_end, $args = [] ) {
    if ( empty($s['nmms_cert_path']) || ! file_exists($s['nmms_cert_path']) ) {
        bntm_weimop_log_event( 'NMMS', 'ERROR', 'Certificate file not found before SOAP request.', [
            'result_type' => $result_type,
            'interval_end' => $interval_end,
            'path' => $s['nmms_cert_path'] ?? '',
        ] );
        return [ 'error' => 'Certificate file not found: ' . $s['nmms_cert_path'] ];
    }
    if ( ! function_exists('curl_init') ) {
        bntm_weimop_log_event( 'NMMS', 'ERROR', 'cURL extension is not available for SOAP request.', [
            'result_type' => $result_type,
            'interval_end' => $interval_end,
        ] );
        return [ 'error' => 'PHP cURL extension not available.' ];
    }

    $pfx_pass   = $s['_cert_pass_raw'] ?? '';
    $pem_dir    = bntm_weimop_pem_dir();
    $uid        = get_current_user_id();
    $cert_file  = $pem_dir . '/cert_' . $uid . '.pem';
    $key_file   = $pem_dir . '/key_'  . $uid . '.pem';
    $market_run = trim( (string) ( $args['market_run'] ?? $s['nmms_market_run'] ?? '' ) );

    // Try to extract PEM from PFX using three methods in order:
    // 1. openssl_pkcs12_read() — works on PHP/OpenSSL < 3 or compiled with legacy
    // 2. shell openssl command with -legacy flag — works on OpenSSL 3.x servers
    // 3. Pass PFX directly to cURL (CURLOPT_SSLCERTTYPE=P12) — no extraction needed
    $extracted = bntm_weimop_extract_pem_from_pfx(
        $s['nmms_cert_path'], $pfx_pass, $cert_file, $key_file
    );
    if ( isset($extracted['error']) ) return $extracted;
    $use_native_pfx = $extracted['native_pfx'] ?? false;

    $urls = [];
    if ( ! empty( $s['nmms_url'] ) ) {
        $urls[] = $s['nmms_url'];
    } else {
        if ( ! empty( $s['nmms_main_url'] ) ) $urls[] = $s['nmms_main_url'];
        if ( ! empty( $s['nmms_backup_url'] ) ) $urls[] = $s['nmms_backup_url'];
    }
    $urls = array_values( array_unique( array_filter( array_map( 'trim', $urls ) ) ) );
    if ( empty( $urls ) ) {
        bntm_weimop_log_event( 'NMMS', 'ERROR', 'NMMS MPI URL is not configured.', [ 'result_type' => $result_type ] );
        return [ 'error' => 'NMMS MPI URL is not configured.' ];
    }

    $soap_body = '<?xml version="1.0" encoding="utf-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ns="http://www.siemens.com/ptd/mms/mmsbase">
  <soapenv:Header/>
  <soapenv:Body>
    <ns:exportResults>
      <ns:operation>'   . htmlspecialchars($s['nmms_operation'] ?: 'exportResults', ENT_XML1) . '</ns:operation>
      <ns:resultType>'  . htmlspecialchars($result_type, ENT_XML1)                            . '</ns:resultType>
      <ns:marketRun>'   . htmlspecialchars($market_run, ENT_XML1)                              . '</ns:marketRun>
      <ns:regionName>'  . htmlspecialchars($s['nmms_region_name'], ENT_XML1)                  . '</ns:regionName>
      <ns:runTime>'     . htmlspecialchars($s['nmms_run_time'], ENT_XML1)                     . '</ns:runTime>
      <ns:commodity>'   . htmlspecialchars($s['nmms_commodity'], ENT_XML1)                    . '</ns:commodity>
      <ns:priceNode>'   . htmlspecialchars($s['nmms_price_node'], ENT_XML1)                   . '</ns:priceNode>
      <ns:intervalEnd>' . htmlspecialchars($interval_end, ENT_XML1)                           . '</ns:intervalEnd>
      <ns:unitId>'      . htmlspecialchars($s['nmms_unit_id'], ENT_XML1)                      . '</ns:unitId>
    </ns:exportResults>
  </soapenv:Body>
</soapenv:Envelope>';

    $response       = false;
    $http_code      = 0;
    $curl_err       = '';
    $attempt_errors = [];
    $used_url       = '';

    foreach ( $urls as $url ) {
    $ch = curl_init();
    $curl_opts = [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $soap_body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "exportResults"',
        ],
    ];

    if ( $use_native_pfx ) {
        // Method 3: cURL reads PFX directly — no PEM extraction needed
        // Works on OpenSSL 3.x without legacy flag
        $curl_opts[CURLOPT_SSLCERT]         = $s['nmms_cert_path'];
        $curl_opts[CURLOPT_SSLCERTTYPE]     = 'P12';
        $curl_opts[CURLOPT_SSLCERTPASSWD]   = $pfx_pass;
    } else {
        // Methods 1 & 2: PEM files already extracted
        $curl_opts[CURLOPT_SSLCERT] = $cert_file;
        $curl_opts[CURLOPT_SSLKEY]  = $key_file;
    }

    curl_setopt_array( $ch, $curl_opts );

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

        if ( ! $curl_err && $http_code === 200 && $response ) {
            $used_url = $url;
            break;
        }

        $attempt_errors[] = sprintf(
            '%s => %s',
            $url,
            $curl_err ? $curl_err : ( $http_code ? 'HTTP ' . $http_code : 'empty response' )
        );
    }

    if ( ! $use_native_pfx ) {
        @unlink($cert_file);
        @unlink($key_file);
    }

    if ( $used_url === '' ) {
        bntm_weimop_log_event( 'NMMS', 'ERROR', 'SOAP request failed across all configured URLs.', [
            'result_type' => $result_type,
            'interval_end' => $interval_end,
            'attempts' => $attempt_errors,
        ] );
        return [ 'error' => 'NMMS request failed across configured URL(s): ' . implode( ' | ', $attempt_errors ) ];
    }

    bntm_weimop_log_event( 'NMMS', 'INFO', 'SOAP request succeeded.', [
        'result_type' => $result_type,
        'interval_end' => $interval_end,
        'url' => $used_url,
        'http_code' => $http_code,
        'auth_mode' => $use_native_pfx ? 'native_pfx' : 'pem',
    ] );

    return [ 'xml' => $response, 'http_code' => $http_code, 'url' => $used_url ];
}

function bntm_weimop_load_export_results_conf( $config_path, $result_type ) {
    $config_path = bntm_weimop_normalize_local_file_path( $config_path );
    if ( $config_path === '' ) return [ 'error' => 'ExportResultsConf path is not set.' ];
    if ( ! file_exists( $config_path ) ) return [ 'error' => 'ExportResultsConf file not found: ' . $config_path ];

    $doc = new DOMDocument();
    libxml_use_internal_errors( true );
    if ( ! @$doc->load( $config_path ) ) return [ 'error' => 'ExportResultsConf XML could not be loaded.' ];

    $xp = new DOMXPath( $doc );
    $xp->registerNamespace( 'm', 'https://www.siemens.com/soa/ExportResults.xsd' );

    $type_name = strtoupper( trim( (string) $result_type ) );
    $query = sprintf( "//m:ExportResultsType[translate(@Name,'abcdefghijklmnopqrstuvwxyz','ABCDEFGHIJKLMNOPQRSTUVWXYZ')='%s']", $type_name );
    $type_node = $xp->query( $query )->item( 0 );
    if ( ! $type_node ) return [ 'error' => 'Result type not found in ExportResultsConf: ' . $result_type ];

    $read_text_list = function( $xpath ) use ( $xp, $type_node ) {
        $out = [];
        foreach ( $xp->query( $xpath, $type_node ) as $node ) $out[] = trim( $node->textContent );
        return $out;
    };

    return [
        'header_names'        => $read_text_list( 'm:ResultsSummary/m:Header/m:HeaderElement/m:Name' ),
        'header_data_types'   => $read_text_list( 'm:ResultsSummary/m:Header/m:HeaderElement/m:DataType' ),
        'header_data_lengths' => array_map( 'intval', $read_text_list( 'm:ResultsSummary/m:Header/m:HeaderElement/m:DataLength' ) ),
        'body_names'          => $read_text_list( 'm:ResultsSummary/m:Body/m:Column/m:Name' ),
        'body_data_types'     => $read_text_list( 'm:ResultsSummary/m:Body/m:Column/m:DataType' ),
        'body_data_lengths'   => array_map( 'intval', $read_text_list( 'm:ResultsSummary/m:Body/m:Column/m:DataLength' ) ),
        'path'                => $config_path,
    ];
}

function bntm_weimop_decode_int32_le( $chunk ) {
    $v = unpack( 'V', $chunk )[1];
    return $v >= 0x80000000 ? $v - 0x100000000 : $v;
}

function bntm_weimop_decode_int64_le( $chunk ) {
    $parts = unpack( 'V2', $chunk );
    $value = ( (int) $parts[2] << 32 ) | (int) $parts[1];
    if ( $parts[2] & 0x80000000 ) $value -= 18446744073709551616.0;
    return (int) $value;
}

function bntm_weimop_decode_nmms_binary_rows( $binary, $result_type, $market_run, $conf ) {
    if ( $binary === '' ) return [ 'rows' => [] ];

    $is_little_endian = pack( 'S', 1 ) === "\x01\x00";
    if ( $is_little_endian ) $binary = strrev( $binary );

    $index        = strlen( $binary );
    $header_names = $conf['header_names'] ?? [];
    $header_types = $conf['header_data_types'] ?? [];
    $body_names   = $conf['body_names'] ?? [];
    $body_types   = $conf['body_data_types'] ?? [];
    $body_lengths = $conf['body_data_lengths'] ?? [];

    $no_of_rows = 0;
    if ( isset( $header_names[0], $header_types[0] ) && strcasecmp( $header_names[0], 'NoOfRows' ) === 0 && strtolower( $header_types[0] ) === 'int' ) {
        $index -= 4;
        $no_of_rows = bntm_weimop_decode_int32_le( substr( $binary, $index, 4 ) );
    }

    if ( $no_of_rows <= 0 ) return [ 'rows' => [] ];

    $no_of_columns = count( $body_names );
    if ( isset( $header_names[1], $header_types[1] ) && strcasecmp( $header_names[1], 'NoOfColumns' ) === 0 && strtolower( $header_types[1] ) === 'int' ) {
        $index -= 4;
        $no_of_columns = max( 0, bntm_weimop_decode_int32_le( substr( $binary, $index, 4 ) ) );
    }

    $rows = [];
    for ( $i = 0; $i < $no_of_rows; $i++ ) {
        $row = [];
        for ( $j = 0; $j < $no_of_columns; $j++ ) {
            $name = $body_names[ $j ] ?? 'COL_' . $j;
            $type = strtolower( $body_types[ $j ] ?? '' );
            $length = (int) ( $body_lengths[ $j ] ?? 0 );

            if ( $type === 'long' ) {
                $index -= 8;
                $timestamp_ms = bntm_weimop_decode_int64_le( substr( $binary, $index, 8 ) );
                $seconds = (int) floor( $timestamp_ms / 1000 );
                $dt = function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s', $seconds ) : date( 'Y-m-d H:i:s', $seconds );
                if ( $name === 'TIME_INTERVAL' && ( strtoupper( $market_run ) === 'WAP' || strtoupper( $market_run ) === 'DAP' || strtoupper( $result_type ) === 'TIPCLMP' ) ) {
                    $dt = date( 'Y-m-d H:i:s', strtotime( $dt ) + HOUR_IN_SECONDS );
                } elseif ( $name === 'TIME_INTERVAL' && strtoupper( $market_run ) === 'HAP' ) {
                    $dt = date( 'Y-m-d H:i:s', strtotime( $dt ) + ( 5 * MINUTE_IN_SECONDS ) );
                }
                $row[ $name ] = $dt;
                if ( $name === 'TIME_INTERVAL' ) $row['_normalized_time_interval'] = $dt;
            } elseif ( $type === 'string' ) {
                $index -= $length;
                $raw = substr( $binary, $index, $length );
                $row[ $name ] = trim( strrev( $raw ), "\0 \t\n\r\0\x0B" );
            } elseif ( $type === 'double' ) {
                $index -= 8;
                $row[ $name ] = unpack( 'e', substr( $binary, $index, 8 ) )[1];
            } elseif ( $type === 'int' ) {
                $index -= 4;
                $row[ $name ] = bntm_weimop_decode_int32_le( substr( $binary, $index, 4 ) );
            }
        }
        if ( ! empty( $row ) ) $rows[] = $row;
    }

    return [ 'rows' => $rows ];
}

function bntm_weimop_parse_nmms_response( $xml_string, $result_type = '', $settings = [] ) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xml_string);
    if ( ! $xml ) {
        bntm_weimop_log_event( 'NMMS_PARSE', 'ERROR', 'Invalid XML in NMMS response.', [
            'result_type' => $result_type,
            'snippet' => substr( trim( (string) $xml_string ), 0, 300 ),
        ] );
        return [ 'error' => 'Invalid XML in NMMS response.' ];
    }

    $return_nodes = $xml->xpath('//*[local-name()="return"]');
    if ( ! $return_nodes ) return [ 'rows' => [] ];

    $first_return = $return_nodes[0];
    if ( $first_return instanceof SimpleXMLElement && count( $first_return->children() ) > 0 ) {
        $rows = [];
        foreach ( $return_nodes as $row ) {
            $r = [];
            foreach ( $row->children() as $child ) $r[$child->getName()] = (string)$child;
            if ( ! empty($r) ) $rows[] = $r;
        }
        return [ 'rows' => $rows ];
    }

    $payload = trim( (string) $first_return );
    if ( $payload === '' ) return [ 'rows' => [] ];

    $binary = base64_decode( preg_replace( '/\s+/', '', $payload ), true );
    if ( $binary === false ) return [ 'rows' => [] ];

    $conf_path = $settings['nmms_export_conf'] ?? '';
    $conf = bntm_weimop_load_export_results_conf( $conf_path, $result_type );
    if ( isset( $conf['error'] ) ) {
        bntm_weimop_log_event( 'NMMS_PARSE', 'ERROR', 'ExportResultsConf load failed.', [
            'result_type' => $result_type,
            'config_path' => $conf_path,
            'error' => $conf['error'],
        ] );
        return $conf;
    }

    return bntm_weimop_decode_nmms_binary_rows(
        $binary,
        $result_type,
        $settings['nmms_market_run'] ?? '',
        $conf
    );
}

function bntm_weimop_insert_rows( $db, $table, $rows, $result_type = '' ) {
    $inserted = 0;
    $table_name = bntm_weimop_table_name( $table );
    $profile = bntm_weimop_result_profile( $result_type );
    foreach ($rows as $r) {
        $ts = $r['_normalized_time_interval'] ?? $r['timeInterval'] ?? $r['TIME_INTERVAL'] ?? '';
        if (!$ts) continue;
        if ( empty( $r['_normalized_time_interval'] ) ) {
            $ts = bntm_weimop_normalize_interval_value( $ts, $result_type );
        }
        try {
            switch ($table) {
                case 'RTDSchedules':
                    $ok = $db->replace(
                        $table_name,
                        [
                            'TIME_INTERVAL' => $ts,
                            'SCHEDULE'      => (float)($r['schedule']??$r['SCHEDULE']??0),
                            'LMP'           => (float)($r['lmp']??$r['LMP']??0),
                            'PRICE_NODE'    => (string)($r['priceNode']??$r['PRICE_NODE']??''),
                            'UNIT_ID'       => (string)($r['unitId']??$r['UNIT_ID']??''),
                            'MARKET_RUN'    => (string)$profile['market_run'],
                        ],
                        [ '%s', '%f', '%f', '%s', '%s', '%s' ]
                    );
                    if ( false !== $ok ) $inserted++;
                    break;
                case 'HAPResults':
                    $ok = $db->replace(
                        $table_name,
                        [
                            'TIME_INTERVAL' => $ts,
                            'PRICE'         => (float)($r['price']??$r['PRICE']??0),
                            'PRICE_NODE'    => (string)($r['priceNode']??$r['PRICE_NODE']??''),
                            'MARKET_RUN'    => (string)$profile['market_run'],
                        ],
                        [ '%s', '%f', '%s', '%s' ]
                    );
                    if ( false !== $ok ) $inserted++;
                    break;
                case 'DAPResults':
                    $ok = $db->replace(
                        $table_name,
                        [
                            'TIME_INTERVAL' => $ts,
                            'PRICE'         => (float)($r['price']??$r['PRICE']??0),
                            'PRICE_NODE'    => (string)($r['priceNode']??$r['PRICE_NODE']??''),
                            'MARKET_RUN'    => (string)$profile['market_run'],
                        ],
                        [ '%s', '%f', '%s', '%s' ]
                    );
                    if ( false !== $ok ) $inserted++;
                    break;
                case 'OCCResourcesComplianceDetail':
                    $ok = $db->replace(
                        $table_name,
                        [
                            'TIME_INTERVAL' => $ts,
                            'OFFERED_CAP'   => (float)($r['offeredCapacity']??$r['OFFERED_CAP']??0),
                            'SCHEDULED_CAP' => (float)($r['scheduledCapacity']??$r['SCHEDULED_CAP']??0),
                            'UNIT_ID'       => (string)($r['unitId']??$r['UNIT_ID']??''),
                            'REGION'        => (string)($r['regionName']??$r['REGION']??''),
                        ],
                        [ '%s', '%f', '%f', '%s', '%s' ]
                    );
                    if ( false !== $ok ) $inserted++;
                    break;
            }
        } catch (Exception $e) {}
    }
    return $inserted;
}

/* -------------------------------------------------------
   K. AJAX -- FETCH HISTORICAL DATA
   CRITICAL FIX: cert password is passed via POST field
   'cert_password'. Use wp_unslash() ONLY — never
   sanitize_text_field() on passwords as it strips
   special characters that are valid in passwords.
------------------------------------------------------- */

function bntm_ajax_weimop_fetch_historical() {
    check_ajax_referer('weimop_nonce','nonce');
    if ( ! is_user_logged_in() ) wp_send_json_error(['message'=>'Unauthorized']);

    $uid = get_current_user_id();
    $s   = bntm_weimop_get_settings($uid);
    $previous_run = bntm_weimop_get_previous_run_snapshot( 'backfill', $uid );

    if ( empty($s['nmms_cert_path']) || ! file_exists($s['nmms_cert_path']) )
        wp_send_json_error(['message'=>'Certificate file not found. Upload your .pfx first.']);
    if ( ! function_exists('curl_init') )
        wp_send_json_error(['message'=>'PHP cURL extension not available on this server.']);

    // Raw password: wp_unslash only — sanitize_text_field would strip special chars
    $s['_cert_pass_raw'] = isset($_POST['cert_password']) ? wp_unslash($_POST['cert_password']) : '';

    $db = bntm_weimop_open_db(false);
    if (!$db) wp_send_json_error(['message'=>'Cannot open WordPress database.']);

    bntm_weimop_log_event( 'BACKFILL', 'INFO', 'Historical fetch started.', [
        'selected_result_type' => $s['nmms_result_type'] ?? '',
        'selected_market_run' => $s['nmms_market_run'] ?? '',
    ] );

    $now            = time();
    $step           = 300;
    $intervals      = 288;
    $total_inserted = 0;
    $errors         = [];

    $fetch_types = ! empty($s['nmms_result_type'])
        ? [ bntm_weimop_result_profile( $s['nmms_result_type'], $s ) ]
        : [
            bntm_weimop_result_profile( 'RTDSchedules', $s ),
            bntm_weimop_result_profile( 'MPLMP', array_merge( $s, [ 'nmms_market_run' => 'HAP' ] ) ),
            bntm_weimop_result_profile( 'MPLMP', array_merge( $s, [ 'nmms_market_run' => 'DAP' ] ) ),
            bntm_weimop_result_profile( 'OCCRESOURCECOMPLIANCEDETAIL', $s ),
          ];

    foreach ( $fetch_types as $ft ) {
        $type_inserted = 0;
        for ( $i = 1; $i <= $intervals; $i++ ) {
            $ts           = $now - ($intervals - $i) * $step;
            $interval_end = date('Y-m-d H:i:s', $ts - ($ts % $step));
            $result       = bntm_weimop_nmms_soap_request($s, $ft['type'], $interval_end, [ 'market_run' => $ft['market_run'] ]);
            if ( isset($result['error']) ) {
                bntm_weimop_log_event( 'BACKFILL', 'ERROR', 'NMMS request failed during historical fetch.', [
                    'type' => $ft['type'],
                    'market_run' => $ft['market_run'],
                    'interval_end' => $interval_end,
                    'error' => $result['error'],
                ] );
                $errors[] = $ft['type'] . ' @ ' . $interval_end . ': ' . $result['error'];
                break;
            }
            $parsed = bntm_weimop_parse_nmms_response(
                $result['xml'],
                $ft['type'],
                array_merge( $s, [ 'nmms_market_run' => $ft['market_run'] ] )
            );
            if ( isset($parsed['error']) ) {
                bntm_weimop_log_event( 'BACKFILL', 'ERROR', 'NMMS response parse failed during historical fetch.', [
                    'type' => $ft['type'],
                    'market_run' => $ft['market_run'],
                    'interval_end' => $interval_end,
                    'error' => $parsed['error'],
                ] );
                $errors[] = $ft['type'] . ' @ ' . $interval_end . ': ' . $parsed['error'];
                break;
            }
            if ( empty($parsed['rows']) ) continue;
            $type_inserted += bntm_weimop_insert_rows($db, $ft['table'], $parsed['rows'], $ft['type']);
        }
        $total_inserted += $type_inserted;
        try {
            $db->insert(
                bntm_weimop_table_name('ExtractorLog'),
                [
                    'status'    => 'ok',
                    'message'   => $ft['type'] . ' backfill',
                    'rows_added'=> $type_inserted,
                ],
                [ '%s', '%s', '%d' ]
            );
        } catch(Exception $e) {}

        bntm_weimop_log_event( 'BACKFILL', $type_inserted > 0 ? 'INFO' : 'WARN', 'Historical fetch type finished.', [
            'type' => $ft['type'],
            'market_run' => $ft['market_run'],
            'rows_inserted' => $type_inserted,
        ] );
    }

    $msg = 'Backfill complete. ' . $total_inserted . ' row(s) inserted.';
    if ( $total_inserted === 0 && empty( $errors ) ) {
        $msg .= ' The request completed, but NMMS returned no rows for the selected intervals/settings.';
    }
    if ($errors) $msg .= ' Note: ' . implode(' | ', array_slice($errors, 0, 3));

    bntm_weimop_log_event( 'BACKFILL', empty( $errors ) ? 'INFO' : 'ERROR', 'Historical fetch completed.', [
        'rows_inserted' => $total_inserted,
        'error_count' => count( $errors ),
        'errors' => array_slice( $errors, 0, 10 ),
    ] );

    $current_run = [
        'timestamp'   => function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s' ),
        'inserted'    => $total_inserted,
        'error_count' => count( $errors ),
        'message'     => $msg,
        'errors'      => array_slice( $errors, 0, 5 ),
    ];
    bntm_weimop_store_run_snapshot( 'backfill', $uid, $current_run );

    wp_send_json_success([
        'message'      => $msg,
        'inserted'     => $total_inserted,
        'errors'       => $errors,
        'logs'         => bntm_weimop_recent_feature_logs( 'BACKFILL', 12 ),
        'previous_run' => $previous_run,
        'notice'       => bntm_weimop_build_run_notice( $current_run, $previous_run ),
    ]);
}

/* -------------------------------------------------------
   L. CONNECTION CHECK
------------------------------------------------------- */

function bntm_weimop_check_connection() {
    global $wpdb;

    $s      = bntm_weimop_get_settings(get_current_user_id());
    $checks = []; $issues = [];
    $tables = bntm_weimop_db_tables();

    $checks['sqlite3_ext'] = empty( $wpdb->last_error );
    if (!$checks['sqlite3_ext']) $issues[] = 'WordPress database connection is not available.';

    $checks['db_exists']   = true;
    $checks['db_writable'] = true;

    $checks['tables_exist'] = $checks['has_rtd'] = $checks['has_hap'] = $checks['has_dap'] = $checks['has_occ'] = false;

    if ($checks['sqlite3_ext']) {
        bntm_weimop_ensure_data_tables();
        $needed = [ 'RTDSchedules', 'HAPResults', 'DAPResults', 'OCCResourcesComplianceDetail' ];
        $checks['tables_exist'] = true;
        foreach ( $needed as $logical ) {
            $table = $tables[ $logical ];
            $exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
            $checks['tables_exist'] = $checks['tables_exist'] && $exists;
        }
        if (!$checks['tables_exist']) $issues[] = 'One or more WESM tables are missing. Click Re-initialize Tables.';
        $checks['has_rtd'] = (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$tables['RTDSchedules']}" ) > 0;
        $checks['has_hap'] = (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$tables['HAPResults']}" ) > 0;
        $checks['has_dap'] = (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$tables['DAPResults']}" ) > 0;
        $checks['has_occ'] = (int)$wpdb->get_var( "SELECT COUNT(*) FROM {$tables['OCCResourcesComplianceDetail']}" ) > 0;
    }

    $checks['pfx_set']    = !empty($s['nmms_cert_path']);
    $checks['pfx_exists'] = $checks['pfx_set'] && file_exists($s['nmms_cert_path']);
    $checks['curl_ssl']   = function_exists('curl_init') && function_exists('openssl_pkcs12_read');

    if (!$checks['pfx_set'])      $issues[] = 'Certificate path not configured. Upload your .pfx file.';
    elseif (!$checks['pfx_exists']) $issues[] = 'Certificate file not found at saved path: ' . $s['nmms_cert_path'];
    if (!$checks['curl_ssl'])     $issues[] = 'PHP cURL or OpenSSL extension not available on this server.';

    $checks['nmms_url']  = !empty($s['nmms_main_url']);
    $checks['cert_name'] = !empty($s['nmms_cert_name']);
    if (!$checks['nmms_url'])  $issues[] = 'NMMS Main URL is not set.';
    if (!$checks['cert_name']) $issues[] = 'Certificate Name (CN) not set. Upload your .pfx to auto-fill.';

    return ['checks'=>$checks,'issues'=>$issues,'ok'=>empty($issues)];
}

/* -------------------------------------------------------
   M. AJAX -- MISC HANDLERS
------------------------------------------------------- */


/* -------------------------------------------------------
   DIAGNOSTIC HANDLER
   Tests every prerequisite for openssl_pkcs12_read and
   the NMMS SOAP call. Returns a structured report so the
   user can see exactly what is failing server-side.
------------------------------------------------------- */

function bntm_ajax_weimop_diag() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);

    $uid      = get_current_user_id();
    $s        = bntm_weimop_get_settings($uid);
    $report   = [];
    $all_pass = true;
    $previous_run = bntm_weimop_get_previous_run_snapshot( 'diag', $uid );

    bntm_weimop_log_event( 'DIAG', 'INFO', 'Diagnostics started.', [
        'cert_path' => $s['nmms_cert_path'] ?? '',
        'nmms_url' => ! empty( $s['nmms_url'] ) ? $s['nmms_url'] : ( $s['nmms_main_url'] ?? '' ),
    ] );

    // Helper
    $chk = function($label, $pass, $detail='') use (&$report, &$all_pass) {
        if (!$pass) $all_pass = false;
        $report[] = ['label'=>$label,'pass'=>(bool)$pass,'detail'=>$detail];
    };

    // PHP extensions
    $chk('PHP OpenSSL extension loaded',    extension_loaded('openssl'),
         extension_loaded('openssl') ? 'openssl '.OPENSSL_VERSION_TEXT : 'Not loaded - contact your host to enable php-openssl');
    $chk('openssl_pkcs12_read() exists',   function_exists('openssl_pkcs12_read'),
         function_exists('openssl_pkcs12_read') ? 'Available' : 'Function missing despite openssl being loaded - unusual; try PHP 7.4+');
    $chk('PHP cURL extension loaded',       extension_loaded('curl'),
         extension_loaded('curl') ? curl_version()['version'] ?? 'ok' : 'Not loaded - contact your host to enable php-curl');
    global $wpdb;
    $chk('WordPress DB connection ready',    empty($wpdb->last_error),
         empty($wpdb->last_error) ? 'Available via '.$wpdb->dbname : 'DB error: '.$wpdb->last_error);

    // PFX file
    $pfx_path = $s['nmms_cert_path'] ?? '';
    $chk('Certificate path is set',         !empty($pfx_path),
         !empty($pfx_path) ? $pfx_path : 'Not set - upload your .pfx in the Certificate section');

    $pfx_exists = !empty($pfx_path) && file_exists($pfx_path);
    $chk('Certificate file exists on disk', $pfx_exists,
         $pfx_exists ? 'Found at '.$pfx_path : 'File not found at: '.$pfx_path);

    $pfx_readable = $pfx_exists && is_readable($pfx_path);
    $chk('Certificate file is readable',    $pfx_readable,
         $pfx_readable ? 'Readable' : 'File exists but PHP cannot read it - check file permissions (should be 0644)');

    // PFX size sanity
    if ($pfx_exists) {
        $sz = filesize($pfx_path);
        $chk('Certificate file size is reasonable', $sz > 100 && $sz < 1048576,
             $sz.' bytes'.($sz <= 100 ? ' - suspiciously small, may be corrupt' : ''));
    }

    // Try reading PFX with provided password
    // Raw password - wp_unslash ONLY, never sanitize_text_field
    $raw_pass = isset($_POST['cert_password']) ? wp_unslash($_POST['cert_password']) : '';
    $pass_len = strlen($raw_pass);
    $chk('Password received by server',  true,
         $pass_len > 0 ? $pass_len.' characters received' : 'Empty password (blank = no password on cert)');

    if ($pfx_readable && function_exists('openssl_pkcs12_read')) {
        $pfx_data = file_get_contents($pfx_path);
        $certs    = [];
        // Clear any stale OpenSSL errors first
        while (openssl_error_string() !== false) {}

        $ok_read  = @openssl_pkcs12_read($pfx_data, $certs, $raw_pass);

        $ossl_err = '';
        while ($e = openssl_error_string()) $ossl_err .= $e . ' ';
        $ossl_err = trim($ossl_err);

        $method1_msg = $ok_read
            ? 'Success — PFX opened with PHP built-in'
            : 'Failed: '.$ossl_err;
        if ( !$ok_read && stripos( $ossl_err, 'unsupported' ) !== false ) {
            $method1_msg .= ' (legacy PFX cipher not supported by this PHP/OpenSSL build; Method 2 or 3 can still work)';
        }
        $chk('Method 1: openssl_pkcs12_read()', $ok_read, $method1_msg);

        // Method 2: test shell openssl binary
        $shell_ok   = false;
        $shell_msg  = 'exec() disabled on this server — cannot test';
        $openssl_bin = bntm_weimop_find_openssl_binary();
        if (function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
            if ($openssl_bin === '') {
                $shell_msg = 'OpenSSL command-line binary not found. Expected locations like C:/xampp/apache/bin/openssl.exe were not found.';
            } else {
                $openssl_cmd = $openssl_bin === 'openssl' ? 'openssl' : escapeshellarg($openssl_bin);
                $shell_msg = 'Using '.$openssl_bin;
            $tmp_cert = sys_get_temp_dir().'/weimop_test_cert_'.$uid.'.pem';
            $pe = escapeshellarg($pfx_path);
            $passin_arg = bntm_weimop_build_openssl_passin_arg($raw_pass);
            $to = escapeshellarg($tmp_cert);
            exec("{$openssl_cmd} pkcs12 -legacy -in $pe -clcerts -nokeys -out $to -passin {$passin_arg} 2>&1", $sout, $src);
            if ($src === 0 && file_exists($tmp_cert) && filesize($tmp_cert) > 0) {
                $shell_ok  = true;
                $shell_msg = 'Success with '.$openssl_bin.' -legacy flag';
                @unlink($tmp_cert);
            } else {
                // Try without -legacy
                exec("{$openssl_cmd} pkcs12 -in $pe -clcerts -nokeys -out $to -passin {$passin_arg} 2>&1", $sout2, $src2);
                if ($src2 === 0 && file_exists($tmp_cert) && filesize($tmp_cert) > 0) {
                    $shell_ok  = true;
                    $shell_msg = 'Success with '.$openssl_bin.' without -legacy flag (modern PFX)';
                    @unlink($tmp_cert);
                } else {
                    $shell_msg = 'Failed (exit '.$src.'): '.implode(' ', array_slice($sout, 0, 2));
                    @unlink($tmp_cert);
                }
            }
            }
        }
        $chk('Method 2: shell openssl pkcs12', $shell_ok, $shell_msg);

        // Method 3: cURL P12 — always available if cURL is loaded
        $curl_p12_ok = function_exists('curl_init');
        $chk('Method 3: cURL P12 direct (CURLOPT_SSLCERTTYPE=P12)', $curl_p12_ok,
             $curl_p12_ok
                 ? 'cURL available — P12 passthrough will be used as fallback (most compatible)'
                 : 'cURL not available'
        );

        // For cert details, use whichever method worked
        if (!$ok_read && $shell_ok) {
            // re-extract for display
            $tmp2 = sys_get_temp_dir().'/weimop_disp_'.$uid.'.pem';
            $openssl_cmd = $openssl_bin === 'openssl' ? 'openssl' : escapeshellarg($openssl_bin);
            exec("{$openssl_cmd} pkcs12 -legacy -in $pe -clcerts -nokeys -out ".escapeshellarg($tmp2)." -passin {$passin_arg} 2>&1");
            if (file_exists($tmp2)) {
                $cert_pem = file_get_contents($tmp2); @unlink($tmp2);
                $certs['cert'] = $cert_pem;
            }
        }

        if ($ok_read || !empty($certs['cert'])) {
            $chk('Certificate bag present',    isset($certs['cert']) && !empty($certs['cert']),
                 isset($certs['cert']) ? 'cert key present ('.strlen($certs['cert']).' bytes)' : 'cert key missing in PFX');
            $chk('Private key bag present',    isset($certs['pkey']) && !empty($certs['pkey']),
                 isset($certs['pkey']) ? 'pkey present' : 'private key missing - PFX may be cert-only');

            // Parse cert to show subject
            if (!empty($certs['cert'])) {
                $parsed = openssl_x509_parse($certs['cert']);
                if ($parsed) {
                    $cn     = $parsed['subject']['CN'] ?? '(none)';
                    $expiry = isset($parsed['validTo_time_t'])
                        ? date('Y-m-d', $parsed['validTo_time_t']).' ('.round(($parsed['validTo_time_t']-time())/86400).' days)'
                        : '(unknown)';
                    $chk('Certificate Subject CN',   true, $cn);
                    $chk('Certificate Expiry',       time() < ($parsed['validTo_time_t']??0),
                         $expiry);
                }
            }
        }
    }

    // NMMS URL reachability (HEAD request, no cert needed)
    $url = !empty($s['nmms_url']) ? $s['nmms_url'] : ($s['nmms_main_url'] ?? '');
    if (!empty($url) && function_exists('curl_init')) {
        $raw_pass = isset($_POST['cert_password']) ? wp_unslash($_POST['cert_password']) : '';
        $ch = curl_init();
        $curl_opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSLVERSION     => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_SSL_VERIFYPEER => false, // diagnostic only
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($pfx_readable && $raw_pass !== '') {
            $curl_opts[CURLOPT_SSLCERT]       = $pfx_path;
            $curl_opts[CURLOPT_SSLCERTTYPE]   = 'P12';
            $curl_opts[CURLOPT_SSLCERTPASSWD] = $raw_pass;
        }
        curl_setopt_array($ch, $curl_opts);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        $handshake_rejected = stripos($err, 'handshake failure') !== false;
        $reachable = ($code > 0 && $code < 600) || $handshake_rejected;
        $msg = $reachable ? 'HTTP '.$code.' from '.$url : 'Could not reach '.$url.($err ? ': '.$err : ' (timeout or DNS failure)');
        if ($handshake_rejected) {
            $msg = 'TLS server reached at '.$url.' but the mutual TLS handshake was rejected: '.$err;
        }
        $chk('NMMS MPI URL reachable', $reachable,
             $msg);
    } else {
        $chk('NMMS MPI URL set', !empty($url), !empty($url) ? $url : 'Not set');
    }

    bntm_weimop_log_event( 'DIAG', $all_pass ? 'INFO' : 'WARN', 'Diagnostics completed.', [
        'all_pass' => $all_pass,
        'report' => $report,
    ] );

    $failed_checks = 0;
    foreach ( $report as $item ) {
        if ( empty( $item['pass'] ) ) $failed_checks++;
    }

    $current_run = [
        'timestamp'     => function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s' ),
        'all_pass'      => $all_pass,
        'failed_checks' => $failed_checks,
        'report'        => $report,
    ];
    bntm_weimop_store_run_snapshot( 'diag', $uid, $current_run );

    wp_send_json_success([
        'report'        => $report,
        'all_pass'      => $all_pass,
        'logs'          => bntm_weimop_recent_feature_logs( 'DIAG', 12 ),
        'previous_run'  => $previous_run,
        'notice'        => bntm_weimop_build_run_notice( $current_run, $previous_run ),
    ]);
}

function bntm_ajax_weimop_connection_status() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error();
    wp_send_json_success(bntm_weimop_check_connection());
}

function bntm_ajax_weimop_upload_pfx() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    if (empty($_FILES['pfx_file']) || $_FILES['pfx_file']['error'] !== UPLOAD_ERR_OK)
        wp_send_json_error(['message'=>'File upload failed (code: '.($_FILES['pfx_file']['error']??'?').').']);
    $file = $_FILES['pfx_file'];
    $ext  = strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if (!in_array($ext,['pfx','p12'],true)) wp_send_json_error(['message'=>'Only .pfx or .p12 files are permitted.']);
    $dir      = bntm_weimop_pfx_dir();
    $uid      = get_current_user_id();
    $filename = 'cert_u' . $uid . '_' . sanitize_file_name($file['name']);
    $dest     = trailingslashit($dir) . $filename;
    if (!move_uploaded_file($file['tmp_name'],$dest))
        wp_send_json_error(['message'=>'Could not write file. Check directory permissions on '.$dir]);
    $settings = bntm_weimop_get_settings($uid);
    $settings['nmms_cert_path'] = wp_normalize_path($dest);
    update_user_meta($uid,'bntm_weimop_settings',$settings);
    wp_send_json_success(['path'=>$dest,'filename'=>$filename,'message'=>'Certificate uploaded and path saved.']);
}

function bntm_ajax_weimop_save_settings() {
    check_ajax_referer('weimop_nonce','nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message'=>'Unauthorized']);
    $uid = get_current_user_id();
    $cur = bntm_weimop_get_settings($uid);
    $new = [
        'email_sender'       => sanitize_text_field($_POST['email_sender']??''),
        'email_password'     => $cur['email_password'],
        'email_recipients'   => sanitize_text_field($_POST['email_recipients']??''),
        'price_alert'        => floatval($_POST['price_alert']??0),
        'refresh_interval'   => max(60,intval($_POST['refresh_interval']??300)),
        'nmms_operation'     => 'exportResults',
        'nmms_main_url'      => esc_url_raw($_POST['nmms_main_url']??''),
        'nmms_backup_url'    => esc_url_raw($_POST['nmms_backup_url']??''),
        'nmms_url'           => esc_url_raw($_POST['nmms_url']??''),
        'nmms_cert_name'     => sanitize_text_field($_POST['nmms_cert_name']??''),
        'nmms_cert_path'     => bntm_weimop_normalize_cert_path(!empty($_POST['nmms_cert_path'])?$_POST['nmms_cert_path']:$cur['nmms_cert_path']),
        'nmms_cert_password' => $cur['nmms_cert_password'],
        'nmms_friendly_name' => sanitize_text_field($_POST['nmms_friendly_name']??''),
        'nmms_export_conf'   => bntm_weimop_normalize_local_file_path($_POST['nmms_export_conf']??''),
        'nmms_result_type'   => sanitize_text_field($_POST['nmms_result_type']??''),
        'nmms_market_run'    => sanitize_text_field($_POST['nmms_market_run']??''),
        'nmms_region_name'   => sanitize_text_field($_POST['nmms_region_name']??''),
        'nmms_run_time'      => sanitize_text_field($_POST['nmms_run_time']??''),
        'nmms_commodity'     => sanitize_text_field($_POST['nmms_commodity']??''),
        'nmms_price_node'    => sanitize_text_field($_POST['nmms_price_node']??''),
        'nmms_interval_end'  => sanitize_text_field($_POST['nmms_interval_end']??''),
        'nmms_unit_id'       => sanitize_text_field($_POST['nmms_unit_id']??''),
    ];
    // Passwords: wp_unslash only — sanitize_text_field strips special chars
    $ep = isset($_POST['email_password'])     ? wp_unslash($_POST['email_password'])     : '';
    if ($ep !== '') $new['email_password'] = wp_hash_password($ep);
    $cp = isset($_POST['nmms_cert_password']) ? wp_unslash($_POST['nmms_cert_password']) : '';
    if ($cp !== '') $new['nmms_cert_password'] = wp_hash_password($cp);
    update_user_meta($uid,'bntm_weimop_settings',$new);
    wp_send_json_success(['message'=>'Settings saved successfully.','status'=>bntm_weimop_check_connection()]);
}

function bntm_ajax_weimop_get_market_snapshot() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_snapshot());
}
function bntm_ajax_weimop_get_chart_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_rtd_series());
}
function bntm_ajax_weimop_get_hap_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_hap_series());
}
function bntm_ajax_weimop_get_dap_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_dap_series());
}
function bntm_ajax_weimop_get_occ_series() {
    check_ajax_referer('weimop_nonce','nonce'); if(!is_user_logged_in())wp_send_json_error();
    wp_send_json_success(bntm_weimop_read_occ_series());
}

/* -------------------------------------------------------
   N. MYSQL READERS
------------------------------------------------------- */

function bntm_weimop_fetch_rows($db,$sql){
    $rows = $db->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}
function bntm_weimop_read_snapshot(){
    $o=['last_interval'=>'--','rtd_schedule'=>'--','lmp_price'=>'--','offered_cap'=>'--','hap_price'=>'--','dap_price'=>'--'];
    $db=bntm_weimop_open_db(true);if(!$db)return $o;
    $r=$db->get_row("SELECT TIME_INTERVAL,SCHEDULE,LMP FROM ".bntm_weimop_table_name('RTDSchedules')." ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
    if(is_array($r)){$o['last_interval']=(string)($r['TIME_INTERVAL']??'--');$o['rtd_schedule']=isset($r['SCHEDULE'])?number_format((float)$r['SCHEDULE'],2):'--';$o['lmp_price']=isset($r['LMP'])?number_format((float)$r['LMP'],2):'--';}
    $r=$db->get_row("SELECT OFFERED_CAP FROM ".bntm_weimop_table_name('OCCResourcesComplianceDetail')." ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
    if(is_array($r)&&isset($r['OFFERED_CAP']))$o['offered_cap']=number_format((float)$r['OFFERED_CAP'],2);
    $r=$db->get_row("SELECT PRICE FROM ".bntm_weimop_table_name('HAPResults')." ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
    if(is_array($r)&&isset($r['PRICE']))$o['hap_price']=number_format((float)$r['PRICE'],2);
    $r=$db->get_row("SELECT PRICE FROM ".bntm_weimop_table_name('DAPResults')." ORDER BY TIME_INTERVAL DESC LIMIT 1", ARRAY_A);
    if(is_array($r)&&isset($r['PRICE']))$o['dap_price']=number_format((float)$r['PRICE'],2);
    return $o;
}
function bntm_weimop_read_rtd_series(){
    $o=['rtd'=>[],'lmp'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,SCHEDULE,LMP FROM ".bntm_weimop_table_name('RTDSchedules')." ORDER BY TIME_INTERVAL DESC LIMIT 288"));
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['rtd'][]=['time'=>$ts,'value'=>(float)($r['SCHEDULE']??0)];$o['lmp'][]=['time'=>$ts,'value'=>(float)($r['LMP']??0)];}
    return $o;
}
function bntm_weimop_read_hap_series(){
    $o=['hap'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,PRICE FROM ".bntm_weimop_table_name('HAPResults')." ORDER BY TIME_INTERVAL DESC LIMIT 48"));
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['hap'][]=['time'=>$ts,'value'=>(float)($r['PRICE']??0)];}
    return $o;
}
function bntm_weimop_read_dap_series(){
    $o=['dap'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,PRICE FROM ".bntm_weimop_table_name('DAPResults')." ORDER BY TIME_INTERVAL ASC LIMIT 288");
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['dap'][]=['time'=>$ts,'value'=>(float)($r['PRICE']??0)];}
    return $o;
}
function bntm_weimop_read_occ_series(){
    $o=['offered'=>[],'scheduled'=>[]];$db=bntm_weimop_open_db(true);if(!$db)return $o;
    $rows=array_reverse(bntm_weimop_fetch_rows($db,"SELECT TIME_INTERVAL,OFFERED_CAP,SCHEDULED_CAP FROM ".bntm_weimop_table_name('OCCResourcesComplianceDetail')." ORDER BY TIME_INTERVAL DESC LIMIT 288"));
    foreach($rows as $r){$ts=strtotime((string)($r['TIME_INTERVAL']??''));if(!$ts)continue;$o['offered'][]=['time'=>$ts,'value'=>(float)($r['OFFERED_CAP']??0)];$o['scheduled'][]=['time'=>$ts,'value'=>(float)($r['SCHEDULED_CAP']??0)];}
    return $o;
}

/* -------------------------------------------------------
   O. SETTINGS DEFAULTS & GETTER
------------------------------------------------------- */

function bntm_weimop_default_settings(){
    return [
        'email_sender'=>'','email_password'=>'','email_recipients'=>'',
        'price_alert'=>0,'refresh_interval'=>300,
        'nmms_operation'=>'exportResults',
        'nmms_main_url'=>'https://mpiwebp.iemop.ph/SiemensServices/ExportResultsServiceImpl',
        'nmms_backup_url'=>'https://mpiwebb.iemop.ph/SiemensServices/ExportResultsServiceImpl',
        'nmms_url'=>'','nmms_cert_name'=>'','nmms_cert_path'=>'',
        'nmms_cert_password'=>'','nmms_friendly_name'=>'','nmms_export_conf'=>'',
        'nmms_result_type'=>'','nmms_market_run'=>'','nmms_region_name'=>'',
        'nmms_run_time'=>'','nmms_commodity'=>'','nmms_price_node'=>'',
        'nmms_interval_end'=>'','nmms_unit_id'=>'',
    ];
}
function bntm_weimop_get_settings($uid){
    $s=get_user_meta($uid,'bntm_weimop_settings',true);
    if(!is_array($s))$s=[];
    $s = array_merge(bntm_weimop_default_settings(),$s);
    $fixed_path = bntm_weimop_normalize_cert_path($s['nmms_cert_path'] ?? '');
    if ($fixed_path !== ($s['nmms_cert_path'] ?? '')) {
        $s['nmms_cert_path'] = $fixed_path;
        update_user_meta($uid,'bntm_weimop_settings',$s);
    }
    return $s;
}

/* -------------------------------------------------------
   P. CSS  -- professional, no emoji, clean typography
------------------------------------------------------- */

function bntm_weimop_get_css(){return '
/* Reset & Global */
.wesm-input{background:var(--wesm-surface);border:1px solid var(--wesm-border);color:var(--wesm-text);padding:8px 12px;border-radius:6px;font-size:12px;font-family:inherit;box-sizing:border-box;width:100%;transition:all 0.2s ease;}
.wesm-input:focus{outline:none;border-color:var(--wesm-accent);box-shadow:0 0 0 3px rgba(0,212,255,0.15);}
.wesm-input:disabled{opacity:0.5;cursor:not-allowed;background:var(--wesm-surface2);}
.wesm-input-sm{padding:6px 10px;font-size:11px;width:auto;}
.wesm-input::placeholder{color:var(--wesm-muted);opacity:0.7;}

.wesm-btn{background:var(--wesm-surface2);color:var(--wesm-text);border:1px solid var(--wesm-border);padding:8px 16px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;transition:all 0.15s ease;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;}
.wesm-btn:hover{background:rgba(0,212,255,0.08);border-color:var(--wesm-accent);text-decoration:none;}
.wesm-btn:active{transform:scale(0.98);}
.wesm-btn:disabled{opacity:0.5;cursor:not-allowed;transform:none;}
.wesm-btn-primary{background:var(--wesm-accent);color:#000;border-color:var(--wesm-accent);font-weight:700;}
.wesm-btn-primary:hover{background:var(--wesm-accent2);border-color:var(--wesm-accent2);}
.wesm-btn-secondary{background:var(--wesm-surface2);color:var(--wesm-text);border:1px solid var(--wesm-border);}
.wesm-btn-secondary:hover{background:rgba(255,255,255,0.05);border-color:var(--wesm-accent);}
.wesm-btn-sm{padding:6px 12px;font-size:11px;}

/* Page layout */
.wesm-page{padding:24px;overflow-y:auto;}
.wesm-page-header{margin-bottom:24px;border-bottom:1px solid var(--wesm-border);padding-bottom:16px;}
.wesm-page-title{font-size:24px;font-weight:700;color:var(--wesm-text);margin:0 0 4px;letter-spacing:-0.5px;}
.wesm-page-sub{font-size:13px;color:var(--wesm-muted);margin:0;}
.wesm-animate{animation:fadeIn 0.3s ease-in;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:translateY(0);};}

/* Metrics grid */
.wesm-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;}
.wesm-metric{background:var(--wesm-surface);border:1px solid var(--wesm-border);border-radius:8px;padding:18px;position:relative;overflow:hidden;transition:all 0.3s ease;}
.wesm-metric::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--wesm-accent),var(--wesm-accent2));opacity:0.8;}
.wesm-metric:hover{border-color:var(--wesm-accent);box-shadow:0 4px 16px rgba(0,212,255,0.12);}
.wesm-metric-label{font-size:11px;font-weight:600;letter-spacing:0.8px;color:var(--wesm-muted);text-transform:uppercase;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.wesm-metric-value{font-size:28px;font-weight:700;font-variant-numeric:tabular-nums;color:var(--wesm-accent);line-height:1;margin-bottom:4px;}
.wesm-metric-unit{font-size:12px;color:var(--wesm-muted);}

/* Chart cards */
.wesm-chart-card{background:var(--wesm-surface);border:1px solid var(--wesm-border);border-radius:8px;padding:20px;margin-bottom:24px;transition:all 0.3s ease;}
.wesm-chart-card:hover{border-color:var(--wesm-accent);box-shadow:0 2px 8px rgba(0,212,255,0.08);}
.wesm-chart-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px;}
.wesm-chart-title{font-size:14px;font-weight:600;color:var(--wesm-text);letter-spacing:-0.3px;}
#weimop-rtd-chart,#weimop-hap-chart,#weimop-dap-chart,#weimop-occ-chart{width:100%!important;height:280px!important;border-radius:6px;}

/* Certificate upload dropzone */
#weimop-pfx-drop-zone{border:2px dashed var(--wesm-border);border-radius:8px;padding:24px;text-align:center;background:rgba(0,212,255,0.04);cursor:pointer;transition:all 0.2s ease;}
#weimop-pfx-drop-zone:hover{border-color:var(--wesm-accent);background:rgba(0,212,255,0.08);}
#weimop-pfx-drop-zone.drag-over{border-color:var(--wesm-accent);background:rgba(0,212,255,0.12);box-shadow:inset 0 2px 8px rgba(0,212,255,0.08);}
#weimop-pfx-drop-zone p{margin:0;font-size:13px;color:var(--wesm-text);font-weight:500;}

/* Certificate preview */
#weimop-pfx-preview{background:var(--wesm-surface2);border:1px solid var(--wesm-border);border-radius:6px;padding:12px;font-size:11px;margin-top:12px;}
#weimop-pfx-preview table{width:100%;border-collapse:collapse;}
#weimop-pfx-preview tr:not(:last-child){border-bottom:1px solid var(--wesm-border);}
#weimop-pfx-preview td{padding:6px;color:var(--wesm-text);}
#weimop-pfx-preview td:first-child{color:var(--wesm-muted);width:150px;font-weight:600;text-align:right;padding-right:12px;}

/* Progress bar */
#weimop-pfx-progress{margin-bottom:12px;}
#weimop-pfx-progress div:first-child{height:4px;background:var(--wesm-surface2);border-radius:3px;overflow:hidden;}
#weimop-pfx-bar{height:100%;background:linear-gradient(90deg,var(--wesm-accent),var(--wesm-accent2));transition:width 0.25s ease;box-shadow:0 0 8px rgba(0,212,255,0.3);}

/* Diagnostics table */
#weimop-diag-table{width:100%;border-collapse:collapse;font-size:12px;}
#weimop-diag-table tr:not(:last-child){border-bottom:1px solid var(--wesm-border);}
#weimop-diag-table td{padding:10px;color:var(--wesm-text);}
#weimop-diag-table td:first-child{color:var(--wesm-muted);width:40%;padding-right:16px;}
.weimop-diag-pass{color:var(--wesm-success);font-weight:600;}
.weimop-diag-fail{color:var(--wesm-danger);font-weight:600;}

/* Connection status card */
#weimop-connection-card ul{margin:0;padding-left:18px;color:var(--wesm-text);}
#weimop-connection-card ul li{font-size:12px;margin-bottom:6px;color:var(--wesm-warning);}

/* Notices & messages */
#weimop-backfill-status,#weimop-pfx-status,#weimop-settings-msg{font-size:12px;padding:10px 12px;border-radius:6px;border:1px solid;background-clip:padding-box;animation:slideDown 0.2s ease;}
#weimop-backfill-status{background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:var(--wesm-success);}
#weimop-pfx-status.weimop-notice--ok,#weimop-settings-msg[class*="--ok"]{background:rgba(34,197,94,0.1);border-color:rgba(34,197,94,0.3);color:var(--wesm-success);}
#weimop-pfx-status.weimop-notice--warn,#weimop-settings-msg[class*="--warn"]{background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.3);color:var(--wesm-warning);}
#weimop-pfx-status.weimop-notice--error,#weimop-settings-msg[class*="--error"]{background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);color:var(--wesm-danger);}
.weimop-history-card{margin-top:12px;padding:12px;border-radius:8px;background:var(--wesm-surface2);border:1px solid var(--wesm-border);}
.weimop-history-title{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--wesm-muted);margin-bottom:8px;}
.weimop-history-sub{font-size:12px;color:var(--wesm-text);margin-bottom:8px;}
.weimop-history-list{margin:0;padding-left:18px;color:var(--wesm-text);font-size:12px;line-height:1.6;}
.weimop-history-list li{margin-bottom:4px;}
.weimop-log-box{margin-top:10px;padding:10px;border-radius:6px;background:rgba(2,6,23,0.65);border:1px solid rgba(148,163,184,0.16);font-family:Consolas,Monaco,monospace;font-size:11px;line-height:1.55;color:#cbd5e1;max-height:220px;overflow:auto;white-space:pre-wrap;}
@keyframes slideDown{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:translateY(0);}}

/* Status grid */
[style*="display:grid"][style*="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))"] > div{display:flex;align-items:center;gap:6px;font-size:11px;padding:6px 10px;border-radius:5px;}
[style*="display:grid"][style*="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))"] > div span:first-child{font-weight:700;}

/* Form labels */
label[style*="display:block"][style*="text-transform:uppercase"]{display:block;font-size:11px;font-weight:600;color:var(--wesm-muted);margin-bottom:6px;letter-spacing:0.5px;}

/* Responsive */
@media(max-width:768px){
    .wesm-page{padding:16px;}
    .wesm-page-title{font-size:20px;}
    #weimop-rtd-chart,#weimop-hap-chart,#weimop-dap-chart,#weimop-occ-chart{height:220px!important;}
    .wesm-input-sm{width:100%;}
    .wesm-chart-header{flex-direction:column;align-items:flex-start;}
    .wesm-metrics{grid-template-columns:repeat(2,1fr);}
    div[style*="display:flex"][style*="flex-wrap:wrap"]{flex-direction:column!important;}
}
@media(max-width:480px){
    .wesm-metrics{grid-template-columns:1fr;}
    .wesm-page{padding:12px;}
    .wesm-chart-card{padding:16px;}
    .wesm-page-header{padding-bottom:12px;margin-bottom:16px;}
    #weimop-rtd-chart,#weimop-hap-chart,#weimop-dap-chart,#weimop-occ-chart{height:200px!important;}
}
';}

/* -------------------------------------------------------
   Q. JAVASCRIPT  -- heredoc, no PHP injection, no emojis
------------------------------------------------------- */

function bntm_weimop_get_js(){return <<<'JSCODE'
(function(){
'use strict';

var cfgEl = document.getElementById('weimop-config-data');
var CFG   = cfgEl ? JSON.parse(cfgEl.textContent || cfgEl.innerHTML) : {};
var AJAX  = CFG.ajaxurl  || '';
var NONCE = CFG.nonce    || '';
var REFRESH_MS = CFG.refreshMs || 300000;

if (!AJAX || !NONCE) {
    var b = document.getElementById('weimop-conn-banner');
    if (b) { b.className='weimop-banner weimop-banner--error'; document.getElementById('weimop-conn-text').textContent='Configuration missing. Check plugin installation.'; }
    return;
}

function post(action, extra) {
    var fd = new FormData();
    fd.append('action', action); fd.append('nonce', NONCE);
    if (extra) Object.keys(extra).forEach(function(k){ fd.append(k, extra[k]); });
    return fetch(AJAX, {method:'POST',body:fd}).then(function(r){return r.json();});
}
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function nl2br(s){ return esc(s).replace(/\n/g,'<br>'); }
function renderRunHistory(targetId, payload, mode) {
    var el = document.getElementById(targetId);
    if (!el) return;
    var logs = payload && payload.logs ? payload.logs : [];
    var previous = payload && payload.previous_run ? payload.previous_run : null;
    var notice = payload && payload.notice ? payload.notice : '';
    var html = '';

    if (notice) {
        html += '<div class="weimop-history-card"><div class="weimop-history-title">Awareness Notice</div><div class="weimop-history-sub">'+esc(notice)+'</div></div>';
    }

    if (previous) {
        html += '<div class="weimop-history-card"><div class="weimop-history-title">Previous Run</div><ul class="weimop-history-list">';
        html += '<li><strong>Timestamp:</strong> '+esc(previous.timestamp || 'Unknown')+'</li>';
        if (mode === 'diag') {
            html += '<li><strong>Status:</strong> '+(previous.all_pass ? 'Passed' : 'Has issue(s)')+'</li>';
            html += '<li><strong>Failed checks:</strong> '+esc(previous.failed_checks != null ? previous.failed_checks : '0')+'</li>';
        } else {
            html += '<li><strong>Rows inserted:</strong> '+esc(previous.inserted != null ? previous.inserted : '0')+'</li>';
            html += '<li><strong>Error count:</strong> '+esc(previous.error_count != null ? previous.error_count : '0')+'</li>';
            if (previous.message) html += '<li><strong>Summary:</strong> '+esc(previous.message)+'</li>';
        }
        html += '</ul></div>';
    }

    if (logs.length) {
        html += '<div class="weimop-history-card"><div class="weimop-history-title">Latest Logs</div><div class="weimop-log-box">'+nl2br(logs.join('\n'))+'</div></div>';
    }

    el.innerHTML = html;
    el.style.display = html ? 'block' : 'none';
}

/* connection banner */
function updateBanner(data) {
    var b=document.getElementById('weimop-conn-banner');
    var t=document.getElementById('weimop-conn-text');
    var f=document.getElementById('weimop-conn-fix');
    if (!b) return;
    var hasData = data.checks && (data.checks.has_rtd || data.checks.has_hap);
    var hasIssues = data.issues && data.issues.length > 0;
    if (!hasIssues) {
        b.className='weimop-banner weimop-banner--ok';
        t.textContent='Connection ready.';
        if(f)f.style.display='none';
    } else if (data.checks && data.checks.db_exists && !hasData) {
        b.className='weimop-banner weimop-banner--warn';
        t.textContent='Database is ready but contains no data. Use Settings to fetch historical data from IEMOP.';
        if(f)f.style.display='inline';
    } else {
        b.className='weimop-banner weimop-banner--error';
        var first = (data.issues && data.issues[0]) ? data.issues[0] : 'Setup incomplete.';
        t.textContent = first.length > 100 ? first.slice(0,100)+'...' : first;
        if(f)f.style.display='inline';
    }
}

post('weimop_connection_status').then(function(j){
    if (j.success) updateBanner(j.data);
    else {
        var b=document.getElementById('weimop-conn-banner');
        if(b){b.className='weimop-banner weimop-banner--error';document.getElementById('weimop-conn-text').textContent='Could not reach server.';}
    }
}).catch(function(err){
    var b=document.getElementById('weimop-conn-banner');
    if(b){b.className='weimop-banner weimop-banner--error';document.getElementById('weimop-conn-text').textContent='Network error: '+err.message;}
});

/* KPI snapshot */
var snap = document.getElementById('weimop-snapshot');
function fetchSnap(){
    if(!snap)return;
    post('weimop_get_market_snapshot').then(function(j){
        if(!j.success)return;
        ['last_interval','rtd_schedule','lmp_price','offered_cap','hap_price','dap_price'].forEach(function(k){
            var el=snap.querySelector('[data-key="'+k+'"]');
            if(el)el.textContent=(j.data[k]!==undefined)?j.data[k]:'--';
        });
        var fe=document.getElementById('weimop-last-fetch');
        if(fe)fe.textContent='Updated '+new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }).catch(function(){});
}

/* chart factory */
function makeChart(id){
    var el=document.getElementById(id);
    if(!el||!window.LightweightCharts)return null;
    return LightweightCharts.createChart(el,{
        layout:{textColor:'#4a5568',background:{type:'solid',color:'#ffffff'}},
        rightPriceScale:{borderColor:'#dde3ea'},
        leftPriceScale:{visible:true,borderColor:'#dde3ea'},
        timeScale:{borderColor:'#dde3ea',timeVisible:true,secondsVisible:false},
        grid:{vertLines:{color:'#f2f5f8'},horzLines:{color:'#f2f5f8'}},
        crosshair:{mode:LightweightCharts.CrosshairMode.Normal},
        handleScroll:true,handleScale:true,
    });
}
function hidePH(id){var e=document.getElementById(id+'-placeholder');if(e)e.style.display='none';}
function showPH(id,msg){var e=document.getElementById(id+'-placeholder');if(e){e.querySelector('p').textContent=msg;e.style.display='flex';}}

/* chart 1: RTD vs LMP */
var rtdEl=document.getElementById('weimop-rtd-chart');
var rtdC=null,rtdS=null,lmpS=null;
if(rtdEl&&window.LightweightCharts){
    rtdC=makeChart('weimop-rtd-chart');
    rtdS=rtdC.addLineSeries({color:'#2563eb',lineWidth:2,title:'RTD Schedule (MW)',priceScaleId:'left'});
    lmpS=rtdC.addLineSeries({color:'#dc2626',lineWidth:2,title:'LMP (PHP/MWh)'});
}
function fetchRtd(){
    if(!rtdC)return;
    post('weimop_get_chart_series').then(function(j){
        if(!j.success||!j.data)return;
        var r=j.data.rtd||[],l=j.data.lmp||[];
        if(r.length)rtdS.setData(r);if(l.length)lmpS.setData(l);
        if(r.length||l.length){hidePH('weimop-rtd-chart');rtdC.timeScale().fitContent();}
        else showPH('weimop-rtd-chart','No data available. Fetch historical data from Settings.');
    }).catch(function(){showPH('weimop-rtd-chart','Error loading data.');});
}

/* chart 2: HAP */
var hapEl=document.getElementById('weimop-hap-chart');
var hapC=null,hapS=null;
if(hapEl&&window.LightweightCharts){hapC=makeChart('weimop-hap-chart');hapS=hapC.addLineSeries({color:'#7c3aed',lineWidth:2,title:'HAP (PHP/MWh)'});}
function fetchHap(){
    if(!hapC)return;
    post('weimop_get_hap_series').then(function(j){
        var rows=(j.success&&j.data)?(j.data.hap||[]):[];
        if(rows.length){hapS.setData(rows);hidePH('weimop-hap-chart');hapC.timeScale().fitContent();}
        else showPH('weimop-hap-chart','No data available.');
    }).catch(function(){showPH('weimop-hap-chart','Error loading data.');});
}

/* chart 3: DAP */
var dapEl=document.getElementById('weimop-dap-chart');
var dapC=null,dapS=null;
if(dapEl&&window.LightweightCharts){dapC=makeChart('weimop-dap-chart');dapS=dapC.addLineSeries({color:'#059669',lineWidth:2,title:'DAP (PHP/MWh)'});}
function fetchDap(){
    if(!dapC)return;
    post('weimop_get_dap_series').then(function(j){
        var rows=(j.success&&j.data)?(j.data.dap||[]):[];
        if(rows.length){dapS.setData(rows);hidePH('weimop-dap-chart');dapC.timeScale().fitContent();}
        else showPH('weimop-dap-chart','No data available.');
    }).catch(function(){showPH('weimop-dap-chart','Error loading data.');});
}

/* chart 4: OCC */
var occEl=document.getElementById('weimop-occ-chart');
var occC=null,occOS=null,occSS=null;
if(occEl&&window.LightweightCharts){
    occC=makeChart('weimop-occ-chart');
    occOS=occC.addLineSeries({color:'#d97706',lineWidth:2,title:'Offered Capacity (MW)'});
    occSS=occC.addLineSeries({color:'#64748b',lineWidth:1,title:'Scheduled Capacity (MW)',priceScaleId:'left',lineStyle:1});
}
function fetchOcc(){
    if(!occC)return;
    post('weimop_get_occ_series').then(function(j){
        if(!j.success||!j.data)return;
        var o=j.data.offered||[],s=j.data.scheduled||[];
        if(o.length)occOS.setData(o);if(s.length)occSS.setData(s);
        if(o.length||s.length){hidePH('weimop-occ-chart');occC.timeScale().fitContent();}
        else showPH('weimop-occ-chart','No data available.');
    }).catch(function(){showPH('weimop-occ-chart','Error loading data.');});
}

window.addEventListener('resize',function(){
    [[rtdC,rtdEl],[hapC,hapEl],[dapC,dapEl],[occC,occEl]].forEach(function(p){
        if(p[0]&&p[1])p[0].applyOptions({width:p[1].clientWidth});
    });
});

function refreshAll(){fetchSnap();fetchRtd();fetchHap();fetchDap();fetchOcc();}
if(snap||rtdEl||hapEl||dapEl||occEl){
    refreshAll();
    setInterval(refreshAll,REFRESH_MS);
    var rb=document.getElementById('weimop-refresh-all');
    if(rb)rb.addEventListener('click',refreshAll);
    var badge=document.getElementById('weimop-auto-badge');
    if(badge)setInterval(function(){badge.style.opacity=badge.style.opacity==='0.3'?'1':'0.3';},1800);
}

/* backfill - password comes from inline input, not prompt() */
var backfillBtn = document.getElementById('weimop-backfill-btn');
if (backfillBtn) {
    backfillBtn.addEventListener('click', function() {
        var pwInput = document.getElementById('weimop-backfill-password');
        var pw = pwInput ? pwInput.value : '';
        var bstat = document.getElementById('weimop-backfill-status');
        var history = document.getElementById('weimop-backfill-history');
        backfillBtn.disabled = true;
        backfillBtn.textContent = 'Connecting to IEMOP...';
        if (bstat) { bstat.className='weimop-notice weimop-notice--warn'; bstat.style.display='block'; bstat.textContent='Connecting to IEMOP NMMS MPI service and retrieving 24 h of interval data. This may take 1-2 minutes.'; }
        if (history) { history.style.display='none'; history.innerHTML=''; }
        post('weimop_fetch_historical', {cert_password: pw}).then(function(j){
            backfillBtn.disabled = false;
            backfillBtn.textContent = 'Fetch Previous 24h Data from IEMOP';
            if (bstat) {
                bstat.className = j.success ? 'weimop-notice weimop-notice--ok' : 'weimop-notice weimop-notice--error';
                bstat.style.display = 'block';
                bstat.textContent = j.success ? j.data.message : ((j.data&&j.data.message)?j.data.message:'Fetch failed.');
            }
            if (j.success && j.data) renderRunHistory('weimop-backfill-history', j.data, 'backfill');
            if (j.success && j.data.inserted > 0) {
                setTimeout(function(){ location.reload(); }, 1800);
            }
        }).catch(function(err){
            backfillBtn.disabled=false;
            backfillBtn.textContent='Fetch Previous 24h Data from IEMOP';
            if(bstat){bstat.className='weimop-notice weimop-notice--error';bstat.style.display='block';bstat.textContent='Request failed: '+err.message;}
        });
    });
}

/* save settings */
var saveBtn=document.getElementById('weimop-save-settings');
if(saveBtn){
    saveBtn.addEventListener('click',function(){
        var fd=new FormData(document.getElementById('weimop-settings-form'));
        fd.append('action','weimop_save_settings');fd.append('nonce',NONCE);
        var msg=document.getElementById('weimop-settings-msg');
        msg.className='weimop-notice weimop-notice--warn';msg.style.display='block';msg.textContent='Saving...';
        fetch(AJAX,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(j){
            if(j.success){
                msg.className='weimop-notice weimop-notice--ok';msg.textContent=j.data.message;
                if(j.data.status)updateBanner(j.data.status);
                setTimeout(function(){location.reload();},900);
            }else{
                msg.className='weimop-notice weimop-notice--error';
                msg.textContent=(j.data&&j.data.message)?j.data.message:'Save failed.';
            }
        }).catch(function(){msg.className='weimop-notice weimop-notice--error';msg.style.display='block';msg.textContent='Save failed.';});
    });
}

/* PFX upload + client-side cert reader */
var pfxFile=null,parsedCert=null;
var dropZone=document.getElementById('weimop-pfx-drop-zone');
var fileInput=document.getElementById('weimop-pfx-file-input');
var pfxPwd=document.getElementById('weimop-pfx-password');
var uploadBtn=document.getElementById('weimop-pfx-upload-btn');
var preview=document.getElementById('weimop-pfx-preview');
var applyBtn=document.getElementById('weimop-pfx-apply-btn');
var pfxStat=document.getElementById('weimop-pfx-status');

if(dropZone){
    dropZone.addEventListener('dragover',function(e){e.preventDefault();dropZone.classList.add('drag-over');});
    dropZone.addEventListener('dragleave',function(){dropZone.classList.remove('drag-over');});
    dropZone.addEventListener('drop',function(e){e.preventDefault();dropZone.classList.remove('drag-over');if(e.dataTransfer.files[0])loadFile(e.dataTransfer.files[0]);});
    dropZone.addEventListener('click',function(){if(fileInput)fileInput.click();});
}
if(fileInput)fileInput.addEventListener('change',function(){if(fileInput.files[0])loadFile(fileInput.files[0]);});

function loadFile(f){
    if(!/\.(pfx|p12)$/i.test(f.name)){showPS('Only .pfx or .p12 files are accepted.','error');return;}
    pfxFile=f;parsedCert=null;
    document.getElementById('weimop-pfx-drop-label').textContent=f.name+' ('+(f.size/1024).toFixed(1)+' KB) - click Upload and Read Certificate';
    if(preview)preview.style.display='none';
    if(applyBtn)applyBtn.style.display='none';
    if(uploadBtn)uploadBtn.disabled=false;
    showPS('File ready. Enter password if required, then click Upload and Read Certificate.','warn');
}

if(uploadBtn)uploadBtn.addEventListener('click',function(){
    if(!pfxFile){showPS('No file selected.','error');return;}
    var bar=document.getElementById('weimop-pfx-bar');
    var prog=document.getElementById('weimop-pfx-progress');
    var lbl=document.getElementById('weimop-pfx-progress-label');
    if(prog)prog.style.display='block';
    showPS('Uploading...','warn');
    var fd=new FormData();
    fd.append('action','weimop_upload_pfx');fd.append('nonce',NONCE);fd.append('pfx_file',pfxFile);
    var xhr=new XMLHttpRequest();xhr.open('POST',AJAX);
    xhr.upload.onprogress=function(e){if(e.lengthComputable&&bar){var p=Math.round(e.loaded/e.total*100);bar.style.width=p+'%';if(lbl)lbl.textContent='Uploading '+p+'%';}};
    xhr.onload=function(){
        if(prog)prog.style.display='none';
        var resp;try{resp=JSON.parse(xhr.responseText);}catch(e){showPS('Upload failed: invalid server response.','error');return;}
        if(!resp.success){showPS('Upload failed: '+((resp.data&&resp.data.message)?resp.data.message:'Unknown error.'),'error');return;}
        var pf=document.getElementById('weimop-cert-path');if(pf)pf.value=resp.data.path;
        showPS('Certificate uploaded: '+resp.data.path,'ok');
        if(!window.forge)return;
        var reader=new FileReader();
        reader.onload=function(ev){
            try{
                var u8=new Uint8Array(ev.target.result),bin='';
                for(var i=0;i<u8.length;i++)bin+=String.fromCharCode(u8[i]);
                var p12=forge.pkcs12.pkcs12FromAsn1(forge.asn1.fromDer(bin),false,pfxPwd?pfxPwd.value:'');
                var bags=(p12.getBags({bagType:forge.pki.oids.certBag})[forge.pki.oids.certBag])||[];
                if(!bags.length){showPS('Uploaded successfully. No certificate bags found (check password to read details).','ok');return;}
                var bag=bags[0],cert=bag.cert;
                function rdn(s,n){var a=s.getField(n);return a?a.value:'';}
                var der=forge.asn1.toDer(forge.pki.certificateToAsn1(cert)).getBytes();
                var thumb=forge.md.sha1.create().update(der).digest().toHex().match(/.{2}/g).join(':').toUpperCase();
                var now=new Date(),dl=Math.ceil((cert.validity.notAfter-now)/86400000);
                var vc=dl<0?'weimop-cert-expired':dl<=30?'weimop-cert-expiring':'weimop-cert-valid';
                var vt=dl<0?'EXPIRED':dl<=30?'Expiring in '+dl+' day(s)':'Valid ('+dl+' days remaining)';
                var fn=(bag.attributes&&bag.attributes.friendlyName)?(bag.attributes.friendlyName[0]||''):'';
                if(!fn)fn=rdn(cert.subject,'CN');
                parsedCert={fn:fn,cn:rdn(cert.subject,'CN'),o:rdn(cert.subject,'O'),ou:rdn(cert.subject,'OU'),
                    ic:rdn(cert.issuer,'CN'),io:rdn(cert.issuer,'O'),
                    nb:cert.validity.notBefore.toISOString().slice(0,19).replace('T',' ')+' UTC',
                    na:cert.validity.notAfter.toISOString().slice(0,19).replace('T',' ')+' UTC',
                    vt:vt,vc:vc,ser:cert.serialNumber,thumb:thumb,cnt:bags.length};
                if(preview){
                    preview.innerHTML='<strong style="font-size:12px;color:#102a43;display:block;margin-bottom:8px;">Certificate Details</strong><table>'+
                        '<tr><td>Friendly Name</td><td>'+esc(parsedCert.fn)+'</td></tr>'+
                        '<tr><td>Subject CN</td><td>'+esc(parsedCert.cn)+'</td></tr>'+
                        '<tr><td>Organization</td><td>'+esc(parsedCert.o)+'</td></tr>'+
                        '<tr><td>Org Unit</td><td>'+esc(parsedCert.ou)+'</td></tr>'+
                        '<tr><td>Issuer CN</td><td>'+esc(parsedCert.ic)+'</td></tr>'+
                        '<tr><td>Issuer Org</td><td>'+esc(parsedCert.io)+'</td></tr>'+
                        '<tr><td>Valid From</td><td>'+esc(parsedCert.nb)+'</td></tr>'+
                        '<tr><td>Valid Until</td><td>'+esc(parsedCert.na)+'</td></tr>'+
                        '<tr><td>Status</td><td><span class="'+vc+'">'+esc(vt)+'</span></td></tr>'+
                        '<tr><td>SHA-1 Thumbprint</td><td>'+esc(parsedCert.thumb)+'</td></tr>'+
                        '<tr><td>Certs in bundle</td><td>'+parsedCert.cnt+'</td></tr></table>';
                    preview.style.display='block';
                }
                if(applyBtn)applyBtn.style.display='inline-block';
                showPS('Certificate read successfully. Click Apply to populate the CN and Friendly Name fields.','ok');
            }catch(err){showPS('Uploaded. Could not read certificate details: '+(err.message||'incorrect password?'),'ok');}
        };
        reader.readAsArrayBuffer(pfxFile);
    };
    xhr.onerror=function(){if(prog)prog.style.display='none';showPS('Upload failed (network error).','error');};
    xhr.send(fd);
});

if(applyBtn)applyBtn.addEventListener('click',function(){
    if(!parsedCert){showPS('No parsed certificate details are available yet. Upload and read the certificate first.','error');return;}
    var applied=[];
    var cf=document.getElementById('weimop-cert-name');
    var ff=document.getElementById('weimop-friendly-name');
    var cn=parsedCert.cn||parsedCert.fn||'';
    var fn=parsedCert.fn||parsedCert.cn||'';
    if(cf&&cn){cf.value=cn;markAppliedField(cf);applied.push('Certificate Name');}
    if(ff&&fn){ff.value=fn;markAppliedField(ff);applied.push('Friendly Name');}
    if(!applied.length){showPS('Apply could not populate any fields from this certificate. Check the password and certificate contents.','error');return;}
    if(cf){try{cf.scrollIntoView({behavior:'smooth',block:'center'});}catch(e){}}
    showPS('Applied to: '+applied.join(', ')+'. Click Save Settings to persist the values.','ok');
});

function showPS(msg,type){
    if(!pfxStat)return;
    pfxStat.textContent=msg;
    pfxStat.className='weimop-notice weimop-notice--'+(type==='error'?'error':type==='ok'?'ok':'warn');
    pfxStat.style.display='block';
}

function markAppliedField(el){
    if(!el)return;
    var origBorder=el.style.borderColor,origBg=el.style.backgroundColor;
    el.style.borderColor='#16a34a';
    el.style.backgroundColor='#f0fdf4';
    try{el.dispatchEvent(new Event('input',{bubbles:true}));}catch(e){}
    try{el.dispatchEvent(new Event('change',{bubbles:true}));}catch(e){}
    setTimeout(function(){
        el.style.borderColor=origBorder;
        el.style.backgroundColor=origBg;
    },1800);
}


/* ---- diagnostics ---- */
var diagBtn = document.getElementById('weimop-diag-btn');
if (diagBtn) {
    diagBtn.addEventListener('click', function() {
        var pw   = (document.getElementById('weimop-diag-password') || {}).value || '';
        var res  = document.getElementById('weimop-diag-results');
        var tbl  = document.getElementById('weimop-diag-table');
        var notice = document.getElementById('weimop-diag-notice');
        var history = document.getElementById('weimop-diag-history');
        diagBtn.disabled = true; diagBtn.textContent = 'Running...';
        if (notice) { notice.style.display='none'; notice.className=''; notice.textContent=''; }
        if (history) { history.style.display='none'; history.innerHTML=''; }
        post('weimop_diag', {cert_password: pw}).then(function(j) {
            diagBtn.disabled = false; diagBtn.textContent = 'Run Diagnostics';
            if (!j.success) {
                if (res) { res.style.display='block'; tbl.innerHTML='<tr><td colspan="2" class="weimop-diag-fail">'+esc(j.data&&j.data.message?j.data.message:'Diagnostic request failed.')+'</td></tr>'; }
                return;
            }
            var rows = j.data.report || [];
            var html = rows.map(function(r) {
                var cls = r.pass ? 'weimop-diag-pass' : 'weimop-diag-fail';
                var tick = r.pass ? '&#10003;' : '&#10007;';
                return '<tr><td><span class="'+cls+'">'+tick+'</span> '+esc(r.label)+'</td><td class="'+cls+'">'+esc(r.detail)+'</td></tr>';
            }).join('');
            if (res)  res.style.display = 'block';
            if (tbl)  tbl.innerHTML = html;
            if (notice && j.data && j.data.notice) {
                notice.className = 'weimop-notice ' + (j.data.all_pass ? 'weimop-notice--ok' : 'weimop-notice--warn');
                notice.textContent = j.data.notice;
                notice.style.display = 'block';
            }
            if (j.data) renderRunHistory('weimop-diag-history', j.data, 'diag');
        }).catch(function(err) {
            diagBtn.disabled = false; diagBtn.textContent = 'Run Diagnostics';
            if (res) { res.style.display='block'; tbl.innerHTML='<tr><td colspan="2" class="weimop-diag-fail">Request failed: '+esc(err.message)+'</td></tr>'; }
        });
    });
}

})();
JSCODE;
}
