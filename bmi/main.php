<?php
/**
 * Module Name: BMI Calculator
 * Module Slug: bmi
 * Description: A simple Body Mass Index calculator with history tracking.
 * Version: 1.0.0
 * Author: BNTM
 * Icon: calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_BMI_PATH', dirname(__FILE__) . '/');
define('BNTM_BMI_URL', plugin_dir_url(__FILE__));

// =============================================================================
// MODULE CONFIGURATION FUNCTIONS
// =============================================================================

function bntm_bmi_get_pages() {
    return [
        'BMI Calculator' => '[bntm_bmi_calculator]',
    ];
}

function bntm_bmi_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix  = $wpdb->prefix;

    return [
        'bmi_records' => "CREATE TABLE {$prefix}bmi_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_label VARCHAR(100) DEFAULT '',
            weight_kg DECIMAL(6,2) NOT NULL,
            height_cm DECIMAL(6,2) NOT NULL,
            bmi DECIMAL(5,2) NOT NULL,
            category VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id)
        ) {$charset};"
    ];
}

function bntm_bmi_get_shortcodes() {
    return [
        'bntm_bmi_calculator' => 'bntm_shortcode_bmi',
    ];
}

function bntm_bmi_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_bmi_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// =============================================================================
// AJAX ACTION HOOKS
// =============================================================================

add_action('wp_ajax_bmi_save_record',        'bntm_ajax_bmi_save_record');
add_action('wp_ajax_nopriv_bmi_save_record', 'bntm_ajax_bmi_save_record');
add_action('wp_ajax_bmi_delete_record',      'bntm_ajax_bmi_delete_record');

// =============================================================================
// MAIN SHORTCODE FUNCTION
// =============================================================================

function bntm_shortcode_bmi() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to use the BMI Calculator.</div>';
    }

    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'calculator';

    ob_start();
    ?>
    <script>var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';</script>

    <div class="bntm-bmi-container">
        <div class="bntm-tabs">
            <a href="?tab=calculator" class="bntm-tab <?php echo $active_tab === 'calculator' ? 'active' : ''; ?>">Calculator</a>
            <a href="?tab=history"    class="bntm-tab <?php echo $active_tab === 'history'    ? 'active' : ''; ?>">History</a>
        </div>

        <div class="bntm-tab-content">
            <?php if ($active_tab === 'calculator'): ?>
                <?php echo bmi_calculator_tab($business_id); ?>
            <?php elseif ($active_tab === 'history'): ?>
                <?php echo bmi_history_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>

    <style>
    /* ── Shared modal ── */
    .bmi-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.45); z-index: 9999;
        align-items: center; justify-content: center;
    }
    .bmi-modal-overlay.open { display: flex; }
    .bmi-modal {
        background: #fff; border-radius: 12px; padding: 32px;
        width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2);
        animation: bmi-slide-in .2s ease;
    }
    @keyframes bmi-slide-in {
        from { transform: translateY(-16px); opacity: 0; }
        to   { transform: translateY(0);     opacity: 1; }
    }
    .bmi-modal h3 { margin: 0 0 20px; font-size: 18px; color: #111; }
    .bmi-modal-close {
        float: right; background: none; border: none;
        font-size: 20px; cursor: pointer; color: #6b7280; line-height: 1;
    }
    /* ── Shared confirm dialog ── */
    #bmi-confirm-overlay { display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.45); z-index: 10000;
        align-items: center; justify-content: center; }
    #bmi-confirm-overlay.open { display: flex; }
    #bmi-confirm-box {
        background: #fff; border-radius: 12px; padding: 28px 32px;
        max-width: 360px; width: 100%; text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,.2);
    }
    #bmi-confirm-box p { margin: 0 0 20px; font-size: 15px; color: #374151; }
    .bmi-confirm-actions { display: flex; gap: 10px; justify-content: center; }
    </style>

    <!-- Shared confirm dialog -->
    <div id="bmi-confirm-overlay">
        <div id="bmi-confirm-box">
            <p id="bmi-confirm-msg">Are you sure?</p>
            <div class="bmi-confirm-actions">
                <button class="bntm-btn-secondary" id="bmi-confirm-cancel">Cancel</button>
                <button class="bntm-btn-danger"    id="bmi-confirm-ok">Delete</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        window.bmiConfirm = function(msg, cb) {
            const ov = document.getElementById('bmi-confirm-overlay');
            document.getElementById('bmi-confirm-msg').textContent = msg;
            ov.classList.add('open');
            function cleanup() { ov.classList.remove('open'); }
            document.getElementById('bmi-confirm-ok').onclick = function() { cleanup(); cb(true); };
            document.getElementById('bmi-confirm-cancel').onclick = function() { cleanup(); cb(false); };
        };
    })();
    </script>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('BMI Calculator', $content);
}

// =============================================================================
// TAB: CALCULATOR
// =============================================================================

function bmi_calculator_tab($business_id) {
    $nonce = wp_create_nonce('bmi_nonce');

    ob_start();
    ?>
    <div class="bmi-calc-wrap">

        <!-- Input card -->
        <div class="bntm-form-section bmi-input-card">
            <h3 style="margin:0 0 22px;font-size:16px;color:#111;">Enter Measurements</h3>

            <div class="bntm-form-group">
                <label>Label <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                <input type="text" id="bmi-label" class="bntm-input" placeholder="e.g. Morning check">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="bntm-form-group">
                    <label>Weight</label>
                    <div class="bmi-input-unit">
                        <input type="number" id="bmi-weight" class="bntm-input" placeholder="0" min="1" step="0.1">
                        <span class="bmi-unit">kg</span>
                    </div>
                </div>
                <div class="bntm-form-group">
                    <label>Height</label>
                    <div class="bmi-input-unit">
                        <input type="number" id="bmi-height" class="bntm-input" placeholder="0" min="1" step="0.1">
                        <span class="bmi-unit">cm</span>
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:4px;">
                <button id="bmi-calc-btn" class="bntm-btn-primary" style="flex:1;">Calculate BMI</button>
                <button id="bmi-clear-btn" class="bntm-btn-secondary">Clear</button>
            </div>
        </div>

        <!-- Result card (hidden until calculated) -->
        <div class="bntm-form-section bmi-result-card" id="bmi-result-card" style="display:none;">
            <div class="bmi-result-inner">
                <div class="bmi-gauge-wrap">
                    <svg id="bmi-gauge-svg" viewBox="0 0 200 110" width="200" height="110">
                        <!-- Background arc segments -->
                        <path d="M 20 100 A 80 80 0 0 1 57 28" fill="none" stroke="#93c5fd" stroke-width="14" stroke-linecap="round"/>
                        <path d="M 57 28 A 80 80 0 0 1 100 20" fill="none" stroke="#6ee7b7" stroke-width="14" stroke-linecap="round"/>
                        <path d="M 100 20 A 80 80 0 0 1 143 28" fill="none" stroke="#fcd34d" stroke-width="14" stroke-linecap="round"/>
                        <path d="M 143 28 A 80 80 0 0 1 180 100" fill="none" stroke="#fca5a5" stroke-width="14" stroke-linecap="round"/>
                        <!-- Needle -->
                        <line id="bmi-needle" x1="100" y1="100" x2="100" y2="28"
                              stroke="#111" stroke-width="2.5" stroke-linecap="round"
                              transform="rotate(0, 100, 100)"/>
                        <circle cx="100" cy="100" r="5" fill="#111"/>
                    </svg>
                </div>
                <div class="bmi-value-block">
                    <span class="bmi-number" id="bmi-number">--</span>
                    <span class="bmi-cat"    id="bmi-category">--</span>
                </div>
            </div>

            <!-- Category legend -->
            <div class="bmi-legend">
                <span class="bmi-leg-item"><i style="background:#93c5fd;"></i> Underweight &lt;18.5</span>
                <span class="bmi-leg-item"><i style="background:#6ee7b7;"></i> Normal 18.5–24.9</span>
                <span class="bmi-leg-item"><i style="background:#fcd34d;"></i> Overweight 25–29.9</span>
                <span class="bmi-leg-item"><i style="background:#fca5a5;"></i> Obese ≥30</span>
            </div>

            <button id="bmi-save-btn" class="bntm-btn-primary" style="width:100%;margin-top:18px;"
                    data-nonce="<?php echo $nonce; ?>">
                Save to History
            </button>
            <div id="bmi-save-msg" style="margin-top:10px;"></div>
        </div>

    </div>

    <style>
    .bmi-calc-wrap {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 640px) { .bmi-calc-wrap { grid-template-columns: 1fr; } }

    .bmi-input-unit { position: relative; }
    .bmi-input-unit .bntm-input { padding-right: 42px; }
    .bmi-unit {
        position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
        font-size: 13px; color: #9ca3af; pointer-events: none;
    }

    .bmi-result-inner {
        display: flex; align-items: center; gap: 24px; margin-bottom: 16px;
    }
    .bmi-gauge-wrap { flex-shrink: 0; }
    .bmi-value-block { display: flex; flex-direction: column; gap: 4px; }
    .bmi-number { font-size: 48px; font-weight: 700; line-height: 1; color: #111; }
    .bmi-cat    { font-size: 15px; font-weight: 600; color: #6b7280; }

    .bmi-legend {
        display: flex; flex-wrap: wrap; gap: 8px 16px;
        font-size: 12px; color: #6b7280;
    }
    .bmi-leg-item { display: flex; align-items: center; gap: 5px; }
    .bmi-leg-item i { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
    </style>

    <script>
    (function() {
        const catColors = {
            'Underweight': '#3b82f6',
            'Normal':      '#10b981',
            'Overweight':  '#f59e0b',
            'Obese':       '#ef4444'
        };

        // Gauge needle: map BMI 10-40 to -90 to +90 degrees
        function bmiToDeg(bmi) {
            const min = 10, max = 40;
            const clamped = Math.max(min, Math.min(max, bmi));
            return -90 + ((clamped - min) / (max - min)) * 180;
        }

        function getCategory(bmi) {
            if (bmi < 18.5) return 'Underweight';
            if (bmi < 25)   return 'Normal';
            if (bmi < 30)   return 'Overweight';
            return 'Obese';
        }

        let lastBMI = null, lastWeight = null, lastHeight = null, lastLabel = null;

        document.getElementById('bmi-calc-btn').addEventListener('click', function() {
            const weight = parseFloat(document.getElementById('bmi-weight').value);
            const height = parseFloat(document.getElementById('bmi-height').value);

            if (!weight || !height || weight <= 0 || height <= 0) {
                alert('Please enter valid weight and height.');
                return;
            }

            const heightM = height / 100;
            const bmi     = weight / (heightM * heightM);
            const cat     = getCategory(bmi);
            const deg     = bmiToDeg(bmi);

            // Show result card
            const card = document.getElementById('bmi-result-card');
            card.style.display = 'block';

            document.getElementById('bmi-number').textContent   = bmi.toFixed(1);
            document.getElementById('bmi-category').textContent = cat;
            document.getElementById('bmi-category').style.color = catColors[cat];

            // Animate needle
            document.getElementById('bmi-needle').setAttribute('transform',
                'rotate(' + deg + ', 100, 100)');

            // Cache for save
            lastBMI    = bmi;
            lastWeight = weight;
            lastHeight = height;
            lastLabel  = document.getElementById('bmi-label').value.trim();

            document.getElementById('bmi-save-msg').innerHTML = '';
        });

        document.getElementById('bmi-clear-btn').addEventListener('click', function() {
            document.getElementById('bmi-weight').value = '';
            document.getElementById('bmi-height').value = '';
            document.getElementById('bmi-label').value  = '';
            document.getElementById('bmi-result-card').style.display = 'none';
        });

        document.getElementById('bmi-save-btn').addEventListener('click', function() {
            if (!lastBMI) { alert('Calculate BMI first.'); return; }

            const btn  = this;
            const data = new FormData();
            data.append('action',   'bmi_save_record');
            data.append('nonce',    btn.dataset.nonce);
            data.append('label',    lastLabel);
            data.append('weight',   lastWeight);
            data.append('height',   lastHeight);
            data.append('bmi',      lastBMI.toFixed(2));
            data.append('category', document.getElementById('bmi-category').textContent);

            btn.disabled    = true;
            btn.textContent = 'Saving...';

            fetch(ajaxurl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(json => {
                const msg = document.getElementById('bmi-save-msg');
                msg.innerHTML = '<div class="bntm-notice bntm-notice-' +
                    (json.success ? 'success' : 'error') + '">' +
                    json.data.message + '</div>';
                btn.disabled    = false;
                btn.textContent = 'Save to History';
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// =============================================================================
// TAB: HISTORY
// =============================================================================

function bmi_history_tab($business_id) {
    global $wpdb;
    $table   = $wpdb->prefix . 'bmi_records';
    $records = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE business_id = %d ORDER BY created_at DESC LIMIT 100",
        $business_id
    ));

    $nonce = wp_create_nonce('bmi_nonce');

    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="margin:0;font-size:16px;color:#111;">Measurement History</h3>
            <span style="font-size:13px;color:#9ca3af;"><?php echo count($records); ?> record<?php echo count($records) !== 1 ? 's' : ''; ?></span>
        </div>

        <?php if (empty($records)): ?>
            <p style="color:#9ca3af;text-align:center;padding:40px 0;">No records yet. Calculate and save your BMI to see history.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Label</th>
                        <th>Weight</th>
                        <th>Height</th>
                        <th>BMI</th>
                        <th>Category</th>
                        <th width="60"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <?php
                        $cat_class = [
                            'Underweight' => 'bmi-chip-blue',
                            'Normal'      => 'bmi-chip-green',
                            'Overweight'  => 'bmi-chip-yellow',
                            'Obese'       => 'bmi-chip-red',
                        ][$r->category] ?? '';
                    ?>
                    <tr id="bmi-row-<?php echo $r->id; ?>">
                        <td><?php echo date('M d, Y', strtotime($r->created_at)); ?></td>
                        <td><?php echo $r->user_label ? esc_html($r->user_label) : '<span style="color:#d1d5db;">—</span>'; ?></td>
                        <td><?php echo number_format($r->weight_kg, 1); ?> kg</td>
                        <td><?php echo number_format($r->height_cm, 1); ?> cm</td>
                        <td><strong><?php echo number_format($r->bmi, 1); ?></strong></td>
                        <td><span class="bmi-chip <?php echo $cat_class; ?>"><?php echo esc_html($r->category); ?></span></td>
                        <td>
                            <button class="bntm-btn-small bntm-btn-danger bmi-delete-btn"
                                    data-id="<?php echo $r->id; ?>"
                                    data-nonce="<?php echo $nonce; ?>">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <style>
    .bmi-chip {
        display: inline-block; padding: 2px 10px; border-radius: 999px;
        font-size: 12px; font-weight: 600;
    }
    .bmi-chip-blue   { background: #dbeafe; color: #1d4ed8; }
    .bmi-chip-green  { background: #d1fae5; color: #065f46; }
    .bmi-chip-yellow { background: #fef3c7; color: #92400e; }
    .bmi-chip-red    { background: #fee2e2; color: #991b1b; }
    </style>

    <script>
    (function() {
        document.querySelectorAll('.bmi-delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id    = this.dataset.id;
                const nonce = this.dataset.nonce;

                bmiConfirm('Delete this record?', function(confirmed) {
                    if (!confirmed) return;

                    const data = new FormData();
                    data.append('action', 'bmi_delete_record');
                    data.append('nonce',  nonce);
                    data.append('id',     id);

                    fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            const row = document.getElementById('bmi-row-' + id);
                            if (row) row.remove();
                        } else {
                            alert(json.data.message);
                        }
                    });
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// =============================================================================
// AJAX HANDLERS
// =============================================================================

function bntm_ajax_bmi_save_record() {
    check_ajax_referer('bmi_nonce', 'nonce');

    global $wpdb;
    $table    = $wpdb->prefix . 'bmi_records';
    $user_id  = get_current_user_id();

    $label    = sanitize_text_field($_POST['label']    ?? '');
    $weight   = floatval($_POST['weight']  ?? 0);
    $height   = floatval($_POST['height']  ?? 0);
    $bmi      = floatval($_POST['bmi']     ?? 0);
    $category = sanitize_text_field($_POST['category'] ?? '');

    if ($weight <= 0 || $height <= 0 || $bmi <= 0) {
        wp_send_json_error(['message' => 'Invalid data.']);
    }

    $result = $wpdb->insert($table, [
        'rand_id'     => bntm_rand_id(),
        'business_id' => $user_id,
        'user_label'  => $label,
        'weight_kg'   => $weight,
        'height_cm'   => $height,
        'bmi'         => $bmi,
        'category'    => $category,
    ], ['%s','%d','%s','%f','%f','%f','%s']);

    if ($result) {
        wp_send_json_success(['message' => 'Record saved!']);
    } else {
        wp_send_json_error(['message' => 'Failed to save record.']);
    }
}

function bntm_ajax_bmi_delete_record() {
    check_ajax_referer('bmi_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized.']);
    }

    global $wpdb;
    $table   = $wpdb->prefix . 'bmi_records';
    $id      = intval($_POST['id'] ?? 0);
    $user_id = get_current_user_id();

    $result = $wpdb->delete($table,
        ['id' => $id, 'business_id' => $user_id],
        ['%d', '%d']
    );

    if ($result) {
        wp_send_json_success(['message' => 'Record deleted.']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete record.']);
    }
}