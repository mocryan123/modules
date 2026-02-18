<?php
/**
 * Module Name: QR Code Generator
 * Module Slug: qr
 * Description: Instantly generate scannable QR codes from any URL or text. Download for printing or digital sharing.
 * Version: 1.0.0
 * Author: BNTM
 * Icon: qr_code
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_QR_PATH', dirname(__FILE__) . '/');
define('BNTM_QR_URL', plugin_dir_url(__FILE__));


// ─────────────────────────────────────────────────────────────────────────────
// 1. MODULE CONFIGURATION FUNCTIONS
// ─────────────────────────────────────────────────────────────────────────────

function bntm_qr_get_pages() {
    return [
        'QR Code Generator' => '[bntm_qr_generator]',
    ];
}

function bntm_qr_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'qr_codes' => "CREATE TABLE {$prefix}qr_codes (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id     VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            label       VARCHAR(255) NOT NULL DEFAULT '',
            content     TEXT NOT NULL,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};",
    ];
}

function bntm_qr_get_shortcodes() {
    return [
        'bntm_qr_generator' => 'bntm_shortcode_qr_generator',
    ];
}

function bntm_qr_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_qr_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}


// ─────────────────────────────────────────────────────────────────────────────
// 2. AJAX ACTION HOOKS
// ─────────────────────────────────────────────────────────────────────────────

add_action('wp_ajax_bntm_qr_save',         'bntm_ajax_qr_save');
add_action('wp_ajax_bntm_qr_delete',       'bntm_ajax_qr_delete');
add_action('wp_ajax_bntm_qr_get_history',  'bntm_ajax_qr_get_history');


// ─────────────────────────────────────────────────────────────────────────────
// 3. MAIN SHORTCODE (DASHBOARD)
// ─────────────────────────────────────────────────────────────────────────────

function bntm_shortcode_qr_generator() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to use the QR Code Generator.</div>';
    }

    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generator';

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';</script>

    <div class="bntm-qr-container">

        <!-- Tab Navigation -->
        <div class="bntm-tabs">
            <a href="?tab=generator" class="bntm-tab <?php echo $active_tab === 'generator' ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1" stroke-width="2"/>
                    <rect x="14" y="3" width="7" height="7" rx="1" stroke-width="2"/>
                    <rect x="3" y="14" width="7" height="7" rx="1" stroke-width="2"/>
                    <rect x="14" y="14" width="3" height="3" stroke-width="2"/>
                    <rect x="18" y="14" width="3" height="3" stroke-width="2"/>
                    <rect x="14" y="18" width="3" height="3" stroke-width="2"/>
                    <rect x="18" y="18" width="3" height="3" stroke-width="2"/>
                </svg>
                Generator
            </a>
            <a href="?tab=history" class="bntm-tab <?php echo $active_tab === 'history' ? 'active' : ''; ?>">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                History
            </a>
        </div>

        <!-- Tab Content -->
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'generator'): ?>
                <?php echo bntm_qr_generator_tab($business_id); ?>
            <?php elseif ($active_tab === 'history'): ?>
                <?php echo bntm_qr_history_tab($business_id); ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Shared styles ───────────────────────────────────── -->
    <style>
    .bntm-qr-container { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }

    /* ── Generator card layout ── */
    .qr-main-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }
    @media (max-width: 768px) {
        .qr-main-grid { grid-template-columns: 1fr; }
    }

    /* ── Preview panel ── */
    .qr-preview-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 28px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        position: sticky;
        top: 20px;
    }
    .qr-preview-panel h3 {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #6b7280;
    }
    .qr-canvas-wrap {
        width: 220px;
        height: 220px;
        background: #f9fafb;
        border: 2px dashed #e5e7eb;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: border-color 0.2s;
    }
    .qr-canvas-wrap.has-code {
        border-style: solid;
        border-color: #e5e7eb;
        background: #fff;
    }
    .qr-canvas-wrap canvas { display: block; }
    .qr-placeholder-text {
        font-size: 13px;
        color: #9ca3af;
        text-align: center;
        padding: 0 16px;
        line-height: 1.5;
    }
    .qr-download-btn {
        width: 100%;
        padding: 11px 0;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: opacity 0.2s;
    }
    .qr-download-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .qr-download-btn:not(:disabled):hover { opacity: 0.88; }

    .qr-save-btn {
        width: 100%;
        padding: 9px 0;
        background: #fff;
        color: #4f46e5;
        border: 1.5px solid #4f46e5;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }
    .qr-save-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .qr-save-btn:not(:disabled):hover { background: #f5f3ff; }

    /* ── Size selector ── */
    .qr-size-row {
        display: flex;
        gap: 8px;
        width: 100%;
        justify-content: center;
    }
    .qr-size-btn {
        flex: 1;
        padding: 6px 0;
        font-size: 12px;
        font-weight: 600;
        border: 1.5px solid #e5e7eb;
        border-radius: 6px;
        background: #fff;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.15s;
    }
    .qr-size-btn.active,
    .qr-size-btn:hover { border-color: #6366f1; color: #4f46e5; background: #f5f3ff; }

    /* ── History table ── */
    .qr-history-empty {
        text-align: center;
        padding: 48px 0;
        color: #9ca3af;
        font-size: 14px;
    }
    .qr-regen-btn {
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        border: 1.5px solid #6366f1;
        border-radius: 6px;
        background: #fff;
        color: #4f46e5;
        cursor: pointer;
        transition: background 0.15s;
    }
    .qr-regen-btn:hover { background: #f5f3ff; }
    .qr-del-btn {
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 600;
        border: 1.5px solid #fca5a5;
        border-radius: 6px;
        background: #fff;
        color: #ef4444;
        cursor: pointer;
        transition: background 0.15s;
    }
    .qr-del-btn:hover { background: #fef2f2; }

    /* ── Feedback message ── */
    #qr-save-msg { font-size: 13px; text-align: center; min-height: 20px; }
    #qr-save-msg.ok  { color: #059669; }
    #qr-save-msg.err { color: #ef4444; }
    </style>

    <!-- ── QRious library (CDN, no offline needed) ── -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <?php

    $content = ob_get_clean();
    return bntm_universal_container('QR Code Generator', $content);
}


// ─────────────────────────────────────────────────────────────────────────────
// 4. TAB: GENERATOR
// ─────────────────────────────────────────────────────────────────────────────

function bntm_qr_generator_tab($business_id) {
    $nonce = wp_create_nonce('bntm_qr_nonce');

    ob_start();
    ?>
    <div class="qr-main-grid">

        <!-- ── Left: Input form ── -->
        <div>
            <div class="bntm-form-section">
                <h3>Create QR Code</h3>
                <p style="color:#6b7280;font-size:13px;margin-top:-4px;">
                    Paste a URL or enter any text. The QR code updates instantly.
                </p>

                <div class="bntm-form-group" style="margin-top:20px;">
                    <label for="qr-content-input">URL or Text <span style="color:#ef4444;">*</span></label>
                    <textarea id="qr-content-input" rows="4"
                              placeholder="https://example.com"
                              style="resize:vertical;font-size:14px;"></textarea>
                </div>

                <div class="bntm-form-group">
                    <label for="qr-label-input">Label <span style="color:#9ca3af;font-weight:400;">(optional — for your records)</span></label>
                    <input type="text" id="qr-label-input" placeholder="e.g. Company Website">
                </div>

                <div class="bntm-form-group">
                    <label>Foreground Color</label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <input type="color" id="qr-fg-color" value="#000000"
                               style="width:42px;height:38px;padding:2px;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;">
                        <span id="qr-fg-hex" style="font-size:13px;color:#374151;font-family:monospace;">#000000</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Right: Preview ── -->
        <div class="qr-preview-panel">
            <h3>Preview</h3>

            <div class="qr-canvas-wrap" id="qr-canvas-wrap">
                <p class="qr-placeholder-text">Enter a URL or text to generate your QR code</p>
            </div>

            <!-- Size selector -->
            <div class="qr-size-row" style="margin-top:4px;">
                <button class="qr-size-btn active" data-size="200">S</button>
                <button class="qr-size-btn"        data-size="400">M</button>
                <button class="qr-size-btn"        data-size="800">L</button>
            </div>
            <p style="font-size:11px;color:#9ca3af;margin:-8px 0 0;text-align:center;">
                Download size (px): 200 / 400 / 800
            </p>

            <button class="qr-download-btn" id="qr-download-btn" disabled>
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download PNG
            </button>

            <button class="qr-save-btn" id="qr-save-btn" disabled>
                Save to History
            </button>

            <div id="qr-save-msg"></div>
        </div>

    </div>

    <script>
    (function () {
        var qr      = null;
        var canvas  = null;
        var size    = 200;   // download resolution
        var nonce   = '<?php echo esc_js($nonce); ?>';

        var contentInput = document.getElementById('qr-content-input');
        var labelInput   = document.getElementById('qr-label-input');
        var fgColor      = document.getElementById('qr-fg-color');
        var fgHex        = document.getElementById('qr-fg-hex');
        var canvasWrap   = document.getElementById('qr-canvas-wrap');
        var downloadBtn  = document.getElementById('qr-download-btn');
        var saveBtn      = document.getElementById('qr-save-btn');
        var saveMsg      = document.getElementById('qr-save-msg');
        var sizeBtns     = document.querySelectorAll('.qr-size-btn');

        // ── Build / update QR ──
        function renderQR() {
            var val = contentInput.value.trim();
            if (!val) {
                destroyQR();
                return;
            }

            if (!canvas) {
                canvas = document.createElement('canvas');
                canvasWrap.innerHTML = '';
                canvasWrap.appendChild(canvas);
                canvasWrap.classList.add('has-code');
            }

            qr = new QRious({
                element   : canvas,
                value     : val,
                size      : 220,
                foreground: fgColor.value,
                background: '#ffffff',
                padding   : 16,
                level     : 'H',
            });

            downloadBtn.disabled = false;
            saveBtn.disabled     = false;
        }

        function destroyQR() {
            canvasWrap.innerHTML = '<p class="qr-placeholder-text">Enter a URL or text to generate your QR code</p>';
            canvasWrap.classList.remove('has-code');
            canvas = null; qr = null;
            downloadBtn.disabled = true;
            saveBtn.disabled     = true;
            saveMsg.textContent  = '';
        }

        // Live update
        var debounceTimer;
        contentInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(renderQR, 220);
        });
        fgColor.addEventListener('input', function () {
            fgHex.textContent = fgColor.value.toUpperCase();
            renderQR();
        });

        // ── Size selector ──
        sizeBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                sizeBtns.forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                size = parseInt(btn.dataset.size, 10);
            });
        });

        // ── Download ──
        downloadBtn.addEventListener('click', function () {
            var val = contentInput.value.trim();
            if (!val) return;

            // Render at export resolution
            var exportCanvas = document.createElement('canvas');
            new QRious({
                element   : exportCanvas,
                value     : val,
                size      : size,
                foreground: fgColor.value,
                background: '#ffffff',
                padding   : Math.round(size * 0.07),
                level     : 'H',
            });

            var link = document.createElement('a');
            link.href     = exportCanvas.toDataURL('image/png');
            link.download = 'qr-code-' + Date.now() + '.png';
            link.click();
        });

        // ── Save to history ──
        saveBtn.addEventListener('click', function () {
            var val   = contentInput.value.trim();
            var label = labelInput.value.trim();
            if (!val) return;

            saveBtn.disabled    = true;
            saveBtn.textContent = 'Saving...';
            saveMsg.textContent = '';
            saveMsg.className   = '';

            var fd = new FormData();
            fd.append('action',  'bntm_qr_save');
            fd.append('content', val);
            fd.append('label',   label);
            fd.append('nonce',   nonce);

            fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success) {
                    saveMsg.textContent = 'Saved to history!';
                    saveMsg.className   = 'ok';
                } else {
                    saveMsg.textContent = json.data && json.data.message ? json.data.message : 'Save failed.';
                    saveMsg.className   = 'err';
                }
            })
            .catch(function () {
                saveMsg.textContent = 'Network error. Please try again.';
                saveMsg.className   = 'err';
            })
            .finally(function () {
                saveBtn.disabled    = false;
                saveBtn.textContent = 'Save to History';
            });
        });

    })();
    </script>
    <?php
    return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────────────────────
// 5. TAB: HISTORY
// ─────────────────────────────────────────────────────────────────────────────

function bntm_qr_history_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'qr_codes';

    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id = %d ORDER BY created_at DESC LIMIT 200",
        $business_id
    ));

    $nonce = wp_create_nonce('bntm_qr_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Saved QR Codes</h3>
        <p style="color:#6b7280;font-size:13px;margin-top:-4px;">
            Click <strong>Re-generate</strong> to load a saved entry back into the generator.
        </p>
    </div>

    <?php if (empty($records)): ?>
        <div class="qr-history-empty">
            <svg width="48" height="48" fill="none" stroke="#d1d5db" viewBox="0 0 24 24" style="margin-bottom:12px;">
                <rect x="3" y="3" width="7" height="7" rx="1" stroke-width="1.5"/>
                <rect x="14" y="3" width="7" height="7" rx="1" stroke-width="1.5"/>
                <rect x="3" y="14" width="7" height="7" rx="1" stroke-width="1.5"/>
            </svg>
            <p>No saved QR codes yet. Generate one and click <strong>Save to History</strong>.</p>
        </div>
    <?php else: ?>
        <div class="bntm-table-wrapper">
            <table class="bntm-table" id="qr-history-table">
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Content / URL</th>
                        <th>Saved On</th>
                        <th style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                    <tr data-id="<?php echo esc_attr($row->id); ?>"
                        data-content="<?php echo esc_attr($row->content); ?>"
                        data-label="<?php echo esc_attr($row->label); ?>">
                        <td>
                            <?php echo $row->label ? esc_html($row->label) : '<span style="color:#9ca3af;">—</span>'; ?>
                        </td>
                        <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <span title="<?php echo esc_attr($row->content); ?>">
                                <?php echo esc_html(mb_strimwidth($row->content, 0, 60, '…')); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(date('M d, Y g:i A', strtotime($row->created_at))); ?></td>
                        <td style="display:flex;gap:8px;">
                            <button class="qr-regen-btn"
                                    onclick="bntmQrLoadHistory(this)"
                                    data-content="<?php echo esc_attr($row->content); ?>"
                                    data-label="<?php echo esc_attr($row->label); ?>">
                                Re-generate
                            </button>
                            <button class="qr-del-btn"
                                    onclick="bntmQrDelete(this, <?php echo intval($row->id); ?>)"
                                    data-nonce="<?php echo esc_attr($nonce); ?>">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <script>
    function bntmQrLoadHistory(btn) {
        var content = btn.dataset.content;
        var label   = btn.dataset.label;
        // Navigate to generator tab with values stored in sessionStorage
        sessionStorage.setItem('bntm_qr_prefill_content', content);
        sessionStorage.setItem('bntm_qr_prefill_label',   label);
        window.location.href = '?tab=generator';
    }

    function bntmQrDelete(btn, id) {
        if (!confirm('Delete this saved QR code?')) return;
        var nonce = btn.dataset.nonce;

        btn.disabled    = true;
        btn.textContent = '...';

        var fd = new FormData();
        fd.append('action', 'bntm_qr_delete');
        fd.append('id',     id);
        fd.append('nonce',  nonce);

        fetch(ajaxurl, {method: 'POST', body: fd})
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json.success) {
                var row = btn.closest('tr');
                row.style.transition = 'opacity 0.3s';
                row.style.opacity    = '0';
                setTimeout(function () { row.remove(); }, 300);
            } else {
                alert(json.data && json.data.message ? json.data.message : 'Delete failed.');
                btn.disabled    = false;
                btn.textContent = 'Delete';
            }
        });
    }

    // ── Pre-fill from history on generator tab ──
    (function () {
        var content = sessionStorage.getItem('bntm_qr_prefill_content');
        var label   = sessionStorage.getItem('bntm_qr_prefill_label');
        if (content) {
            var ci = document.getElementById('qr-content-input');
            var li = document.getElementById('qr-label-input');
            if (ci) {
                ci.value = content;
                ci.dispatchEvent(new Event('input'));
            }
            if (li && label) li.value = label;
            sessionStorage.removeItem('bntm_qr_prefill_content');
            sessionStorage.removeItem('bntm_qr_prefill_label');
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}


// ─────────────────────────────────────────────────────────────────────────────
// 6. AJAX HANDLERS
// ─────────────────────────────────────────────────────────────────────────────

function bntm_ajax_qr_save() {
    check_ajax_referer('bntm_qr_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $content = sanitize_textarea_field($_POST['content'] ?? '');
    $label   = sanitize_text_field($_POST['label']   ?? '');

    if (empty($content)) {
        wp_send_json_error(['message' => 'Content is required.']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'qr_codes';

    $result = $wpdb->insert($table, [
        'rand_id'     => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'label'       => $label,
        'content'     => $content,
    ], ['%s', '%d', '%s', '%s']);

    if ($result) {
        wp_send_json_success(['message' => 'QR code saved!']);
    } else {
        wp_send_json_error(['message' => 'Failed to save. Please try again.']);
    }
}

function bntm_ajax_qr_delete() {
    check_ajax_referer('bntm_qr_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'qr_codes';
    $id    = intval($_POST['id'] ?? 0);

    if (!$id) {
        wp_send_json_error(['message' => 'Invalid ID.']);
    }

    $result = $wpdb->delete($table, [
        'id'          => $id,
        'business_id' => get_current_user_id(),
    ], ['%d', '%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Deleted.']);
    } else {
        wp_send_json_error(['message' => 'Delete failed.']);
    }
}

function bntm_ajax_qr_get_history() {
    check_ajax_referer('bntm_qr_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'qr_codes';
    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT id, rand_id, label, content, created_at FROM {$table} WHERE business_id = %d ORDER BY created_at DESC LIMIT 200",
        get_current_user_id()
    ));

    wp_send_json_success(['records' => $records]);
}