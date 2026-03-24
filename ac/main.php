<?php
/**
 * Module Name: Accounting Module
 * Module Slug: ac
 * Description: Journal Entry, General Ledger, and Trial Balance management
 * Version: 1.0.0
 * Author: Your Name
 * Icon: 📊
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_AC_PATH', dirname(__FILE__) . '/');
define('BNTM_AC_URL', plugin_dir_url(__FILE__));

// ============================================================================
// 1. CORE MODULE FUNCTIONS
// ============================================================================

/**
 * Pages Configuration
 */
function bntm_ac_get_pages() {
    return [
        'Accounting Dashboard' => '[ac_dashboard]',
        'Chart of Accounts' => '[ac_chart_of_accounts]',
        'Journal Entries' => '[ac_journal_entries]',
        'General Ledger' => '[ac_general_ledger]',
        'Trial Balance' => '[ac_trial_balance]',
    ];
}

/**
 * Database Tables Configuration
 */
function bntm_ac_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'mrs_keywords' =>
            "CREATE TABLE {$wpdb->prefix}mrs_keywords (
                id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                rand_id       VARCHAR(20) UNIQUE NOT NULL,
                business_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
                keyword       VARCHAR(255) NOT NULL,
                osm_tag_key   VARCHAR(100) NOT NULL DEFAULT '',
                osm_tag_value VARCHAR(100) NOT NULL DEFAULT '',
                location      VARCHAR(255) NOT NULL DEFAULT '',
                lat           DECIMAL(10,7) NOT NULL DEFAULT 0.0000000,
                lng           DECIMAL(10,7) NOT NULL DEFAULT 0.0000000,
                radius_km     DECIMAL(6,2)  NOT NULL DEFAULT 5.00,
                business_name VARCHAR(255)  NOT NULL DEFAULT '',
                is_active     TINYINT(1)    NOT NULL DEFAULT 1,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_business (business_id)
            ) $charset;",
             'mrs_rank_results' =>
            "CREATE TABLE {$wpdb->prefix}mrs_rank_results (
                id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                rand_id          VARCHAR(20) UNIQUE NOT NULL,
                business_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
                keyword_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
                rank_position    TINYINT UNSIGNED NOT NULL DEFAULT 0,
                found            TINYINT(1) NOT NULL DEFAULT 0,
                results_snapshot LONGTEXT,
                checked_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_business (business_id),
                INDEX idx_keyword  (keyword_id)
            ) $charset;",
        'ac_chart_of_accounts' => "CREATE TABLE {$prefix}ac_chart_of_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            account_code VARCHAR(50) NOT NULL,
            account_name VARCHAR(255) NOT NULL,
            account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
            parent_id BIGINT UNSIGNED DEFAULT NULL,
            normal_balance ENUM('debit', 'credit') NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_type (account_type),
            INDEX idx_code (account_code),
            UNIQUE KEY unique_code_business (account_code, business_id)
        ) {$charset};",
        
        'ac_journal_entries' => "CREATE TABLE {$prefix}ac_journal_entries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entry_number VARCHAR(50) NOT NULL,
            entry_date DATE NOT NULL,
            reference_type VARCHAR(50) DEFAULT NULL,
            reference_id BIGINT UNSIGNED DEFAULT NULL,
            description TEXT,
            total_debit DECIMAL(15,2) DEFAULT 0.00,
            total_credit DECIMAL(15,2) DEFAULT 0.00,
            status ENUM('draft', 'posted', 'void') DEFAULT 'draft',
            created_by BIGINT UNSIGNED NOT NULL,
            posted_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_date (entry_date),
            INDEX idx_status (status),
            INDEX idx_reference (reference_type, reference_id),
            UNIQUE KEY unique_entry_number (entry_number, business_id)
        ) {$charset};",
        
        'ac_journal_lines' => "CREATE TABLE {$prefix}ac_journal_lines (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            journal_entry_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            debit_amount DECIMAL(15,2) DEFAULT 0.00,
            credit_amount DECIMAL(15,2) DEFAULT 0.00,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_journal (journal_entry_id),
            INDEX idx_account (account_id),
            FOREIGN KEY (journal_entry_id) REFERENCES {$prefix}ac_journal_entries(id) ON DELETE CASCADE,
            FOREIGN KEY (account_id) REFERENCES {$prefix}ac_chart_of_accounts(id)
        ) {$charset};",
        
        'ac_ledger_balance' => "CREATE TABLE {$prefix}ac_ledger_balance (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            account_id BIGINT UNSIGNED NOT NULL,
            period CHAR(7) NOT NULL,
            opening_balance DECIMAL(15,2) DEFAULT 0.00,
            total_debit DECIMAL(15,2) DEFAULT 0.00,
            total_credit DECIMAL(15,2) DEFAULT 0.00,
            closing_balance DECIMAL(15,2) DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_account (account_id),
            INDEX idx_period (period),
            UNIQUE KEY unique_account_period (account_id, period, business_id)
        ) {$charset};"
    ];
}

/**
 * Shortcodes Registration
 */
function bntm_ac_get_shortcodes() {
    return [
        'ac_dashboard' => 'bntm_shortcode_ac_dashboard',
        'ac_chart_of_accounts' => 'bntm_shortcode_ac_chart_of_accounts',
        'ac_journal_entries' => 'bntm_shortcode_ac_journal_entries',
        'ac_general_ledger' => 'bntm_shortcode_ac_general_ledger',
        'ac_trial_balance' => 'bntm_shortcode_ac_trial_balance',
    ];
}

/**
 * Table Creation Function
 */
function bntm_ac_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_ac_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    // Initialize default chart of accounts
    ac_initialize_default_accounts();
    
    return count($tables);
}

// ============================================================================
// 2. MAIN DASHBOARD SHORTCODE
// ============================================================================

function bntm_shortcode_ac_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-ac-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">
                Overview
            </a>
            <a href="?tab=journal" class="bntm-tab <?php echo $active_tab === 'journal' ? 'active' : ''; ?>">
                Journal Entries
            </a>
            <a href="?tab=ledger" class="bntm-tab <?php echo $active_tab === 'ledger' ? 'active' : ''; ?>">
                General Ledger
            </a>
            <a href="?tab=trial_balance" class="bntm-tab <?php echo $active_tab === 'trial_balance' ? 'active' : ''; ?>">
                Trial Balance
            </a>
            <a href="?tab=accounts" class="bntm-tab <?php echo $active_tab === 'accounts' ? 'active' : ''; ?>">
                Chart of Accounts
            </a>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=finance_sync" class="bntm-tab <?php echo $active_tab === 'finance_sync' ? 'active' : ''; ?>">
                Finance Sync
            </a>
            <?php endif; ?>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo ac_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'journal'): ?>
                <?php echo ac_journal_tab($business_id); ?>
            <?php elseif ($active_tab === 'ledger'): ?>
                <?php echo ac_ledger_tab($business_id); ?>
            <?php elseif ($active_tab === 'trial_balance'): ?>
                <?php echo ac_trial_balance_tab($business_id); ?>
            <?php elseif ($active_tab === 'accounts'): ?>
                <?php echo ac_accounts_tab($business_id); ?>
            <?php elseif ($active_tab === 'finance_sync'): ?>
                <?php echo ac_finance_sync_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Accounting Module', $content);
}

// ============================================================================
// 3. TAB FUNCTIONS
// ============================================================================

/**
 * Overview Tab
 */
function ac_overview_tab($business_id) {
    $stats = ac_get_dashboard_stats($business_id);
    
    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Assets</h3>
            <p class="bntm-stat-number"><?php echo ac_format_amount($stats['total_assets']); ?></p>
            <span class="bntm-stat-label">As of today</span>
        </div>
        
        <div class="bntm-stat-card">
            <h3>Total Liabilities</h3>
            <p class="bntm-stat-number"><?php echo ac_format_amount($stats['total_liabilities']); ?></p>
            <span class="bntm-stat-label">Current obligations</span>
        </div>
        
        <div class="bntm-stat-card">
            <h3>Total Equity</h3>
            <p class="bntm-stat-number"><?php echo ac_format_amount($stats['total_equity']); ?></p>
            <span class="bntm-stat-label">Owner's equity</span>
        </div>
        
        <div class="bntm-stat-card">
            <h3>Journal Entries</h3>
            <p class="bntm-stat-number"><?php echo number_format($stats['journal_entries']); ?></p>
            <span class="bntm-stat-label">This month</span>
        </div>
    </div>
    
    <div class="bntm-form-section">
        <h3>Recent Journal Entries</h3>
        <?php
        global $wpdb;
        $entries = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ac_journal_entries 
            WHERE business_id = $business_id 
            ORDER BY entry_date DESC, id DESC 
            LIMIT 10
        ");
        ?>
        
        <?php if (empty($entries)): ?>
            <p style="color: #6b7280;">No journal entries yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Entry Number</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><strong><?php echo esc_html($entry->entry_number); ?></strong></td>
                        <td><?php echo date('M d, Y', strtotime($entry->entry_date)); ?></td>
                        <td><?php echo esc_html($entry->description); ?></td>
                        <td><?php echo ac_format_amount($entry->total_debit); ?></td>
                        <td><?php echo ac_format_amount($entry->total_credit); ?></td>
                        <td>
                            <span class="bntm-badge bntm-badge-<?php echo $entry->status; ?>">
                                <?php echo ucfirst($entry->status); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .bntm-stat-card {
        background: white;
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .bntm-stat-card h3 {
        margin: 0;
        font-size: 13px;
        line-height: 1.4;
        color: #6b7280;
        font-weight: 500;
    }
    
    .bntm-stat-number {
        margin: 0;
        font-size: clamp(20px, 3vw, 24px);
        line-height: 1.15;
        font-weight: bold;
        color: #111827;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    
    .bntm-stat-label {
        font-size: 11px;
        line-height: 1.4;
        color: #9ca3af;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    
    .bntm-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .bntm-badge-draft { background: #f3f4f6; color: #6b7280; }
    .bntm-badge-posted { background: #d1fae5; color: #065f46; }
    .bntm-badge-void { background: #fee2e2; color: #991b1b; }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Journal Entries Tab
 */
function ac_journal_tab($business_id) {
    global $wpdb;
    
    $entries = $wpdb->get_results($wpdb->prepare("
        SELECT je.*, 
               (SELECT COUNT(*) FROM {$wpdb->prefix}ac_journal_lines WHERE journal_entry_id = je.id) as line_count
        FROM {$wpdb->prefix}ac_journal_entries je
        WHERE je.business_id = %d
        ORDER BY je.entry_date DESC, je.id DESC
    ", $business_id));
    
    $nonce = wp_create_nonce('ac_journal_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Journal Entries</h3>
            <button id="new-journal-btn" class="bntm-btn-primary">New Journal Entry</button>
        </div>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Entry #</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Lines</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                <tr><td colspan="8" style="text-align:center;">No journal entries yet</td></tr>
                <?php else: foreach ($entries as $entry): ?>
                <tr>
                    <td><strong><?php echo esc_html($entry->entry_number); ?></strong></td>
                    <td><?php echo date('M d, Y', strtotime($entry->entry_date)); ?></td>
                    <td><?php echo esc_html($entry->description); ?></td>
                    <td><?php echo $entry->line_count; ?> lines</td>
                    <td><?php echo ac_format_amount($entry->total_debit); ?></td>
                    <td><?php echo ac_format_amount($entry->total_credit); ?></td>
                    <td>
                        <span class="bntm-badge bntm-badge-<?php echo $entry->status; ?>">
                            <?php echo ucfirst($entry->status); ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small view-journal-btn" data-id="<?php echo $entry->id; ?>">
                            View
                        </button>
                        <?php if ($entry->status === 'draft'): ?>
                        <button class="bntm-btn-small bntm-btn-primary post-journal-btn" 
                                data-id="<?php echo $entry->id; ?>" data-nonce="<?php echo $nonce; ?>">
                            Post
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- New Journal Entry Modal -->
    <div id="journal-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content" style="max-width: 900px;">
            <span class="bntm-modal-close">&times;</span>
            <h2>New Journal Entry</h2>
            
            <form id="journal-entry-form" class="bntm-form">
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Entry Date *</label>
                        <input type="date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Entry Number *</label>
                        <input type="text" name="entry_number" 
                               value="JE-<?php echo date('Ymd') . '-' . sprintf('%04d', count($entries) + 1); ?>" required>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="2" required></textarea>
                </div>
                
                <h3>Journal Lines</h3>
                <div id="journal-lines-container">
                    <div class="journal-line-row">
                        <select name="account_id[]" required class="account-select">
                            <option value="">Select Account</option>
                            <?php echo ac_get_accounts_options($business_id); ?>
                        </select>
                        <input type="number" name="debit[]" placeholder="Debit" step="0.01" min="0" class="debit-input">
                        <input type="number" name="credit[]" placeholder="Credit" step="0.01" min="0" class="credit-input">
                        <input type="text" name="line_description[]" placeholder="Line description">
                        <button type="button" class="bntm-btn-small bntm-btn-danger remove-line-btn" style="display:none;">×</button>
                    </div>
                </div>
                
                <button type="button" id="add-line-btn" class="bntm-btn-secondary" style="margin: 10px 0;">
                    Add Line
                </button>
                
                <div style="margin: 15px 0; padding: 15px; background: #f9fafb; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold;">
                        <span>Total Debit: <span id="total-debit">₱0.00</span></span>
                        <span>Total Credit: <span id="total-credit">₱0.00</span></span>
                        <span id="balance-check" style="color: #dc2626;">Not Balanced</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="bntm-btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="bntm-btn-primary" id="save-journal-btn">Save as Draft</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Journal Modal -->
    <div id="view-journal-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content" style="max-width: 800px;">
            <span class="bntm-modal-close">&times;</span>
            <div id="view-journal-content"></div>
        </div>
    </div>
    
    <style>
    .bntm-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .bntm-modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 8px;
        max-width: 600px;
        max-height: 85vh;
        overflow-y: auto;
    }
    
    .bntm-modal-close {
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #6b7280;
    }
    
    .bntm-modal-close:hover {
        color: #111827;
    }
    
    .journal-line-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 2fr 40px;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
    
    .journal-line-row select,
    .journal-line-row input {
        padding: 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
    }
    
    .bntm-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    </style>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        
        // Modal handlers
        const journalModal = document.getElementById('journal-modal');
        const viewModal = document.getElementById('view-journal-modal');
        
        document.getElementById('new-journal-btn').addEventListener('click', function() {
            journalModal.style.display = 'block';
        });
        
        document.querySelectorAll('.bntm-modal-close, .modal-close-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                journalModal.style.display = 'none';
                viewModal.style.display = 'none';
            });
        });
        
        // Add journal line
        document.getElementById('add-line-btn').addEventListener('click', function() {
            const container = document.getElementById('journal-lines-container');
            const newLine = container.firstElementChild.cloneNode(true);
            newLine.querySelectorAll('input, select').forEach(input => input.value = '');
            newLine.querySelector('.remove-line-btn').style.display = 'inline-block';
            container.appendChild(newLine);
            updateTotals();
            attachLineEvents();
        });
        
        // Remove line
        function attachLineEvents() {
            document.querySelectorAll('.remove-line-btn').forEach(btn => {
                btn.removeEventListener('click', removeLine);
                btn.addEventListener('click', removeLine);
            });
            
            document.querySelectorAll('.debit-input, .credit-input').forEach(input => {
                input.removeEventListener('input', updateTotals);
                input.addEventListener('input', updateTotals);
            });
            
            // Ensure only debit OR credit is filled
            document.querySelectorAll('.journal-line-row').forEach(row => {
                const debitInput = row.querySelector('.debit-input');
                const creditInput = row.querySelector('.credit-input');
                
                debitInput.addEventListener('input', function() {
                    if (parseFloat(this.value) > 0) creditInput.value = '';
                });
                
                creditInput.addEventListener('input', function() {
                    if (parseFloat(this.value) > 0) debitInput.value = '';
                });
            });
        }
        
        function removeLine() {
            this.closest('.journal-line-row').remove();
            updateTotals();
        }
        
        // Update totals
        function updateTotals() {
            let totalDebit = 0;
            let totalCredit = 0;
            
            document.querySelectorAll('.debit-input').forEach(input => {
                totalDebit += parseFloat(input.value) || 0;
            });
            
            document.querySelectorAll('.credit-input').forEach(input => {
                totalCredit += parseFloat(input.value) || 0;
            });
            
            document.getElementById('total-debit').textContent = '₱' + totalDebit.toFixed(2);
            document.getElementById('total-credit').textContent = '₱' + totalCredit.toFixed(2);
            
            const balanceCheck = document.getElementById('balance-check');
            if (Math.abs(totalDebit - totalCredit) < 0.01 && totalDebit > 0) {
                balanceCheck.textContent = 'Balanced ✓';
                balanceCheck.style.color = '#059669';
            } else {
                balanceCheck.textContent = 'Not Balanced';
                balanceCheck.style.color = '#dc2626';
            }
        }
        
        attachLineEvents();
        
        // Submit journal entry
        document.getElementById('journal-entry-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'ac_save_journal_entry');
            formData.append('nonce', nonce);
            
            const btn = document.getElementById('save-journal-btn');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Save as Draft';
                }
            });
        });
        
        // View journal entry
        document.querySelectorAll('.view-journal-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const journalId = this.dataset.id;
                
                const formData = new FormData();
                formData.append('action', 'ac_get_journal_entry');
                formData.append('journal_id', journalId);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        document.getElementById('view-journal-content').innerHTML = json.data.html;
                        viewModal.style.display = 'block';
                    }
                });
            });
        });
        
        // Post journal entry
        document.querySelectorAll('.post-journal-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Post this journal entry? This action cannot be undone.')) return;
                
                const journalId = this.dataset.id;
                const formData = new FormData();
                formData.append('action', 'ac_post_journal_entry');
                formData.append('journal_id', journalId);
                formData.append('nonce', nonce);
                
                this.disabled = true;
                this.textContent = 'Posting...';
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * General Ledger Tab
 */
function ac_ledger_tab($business_id) {
    global $wpdb;
    
    $selected_account = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : date('Y-m');
    $ledger_pdf_nonce = wp_create_nonce('ac_ledger_pdf_nonce');
    
    $accounts = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ac_chart_of_accounts
        WHERE business_id = %d AND is_active = 1
        ORDER BY account_code ASC
    ", $business_id));
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>General Ledger</h3>
        
        <div class="bntm-form-row" style="margin-bottom: 20px;">
            <div class="bntm-form-group">
                <label>Select Account</label>
                <select id="account-filter" onchange="window.location.href='?tab=ledger&account_id='+this.value+'&period=<?php echo $period; ?>'">
                    <option value="">Select Account</option>
                    <?php foreach ($accounts as $account): ?>
                    <option value="<?php echo $account->id; ?>" <?php selected($selected_account, $account->id); ?>>
                        <?php echo esc_html($account->account_code . ' - ' . $account->account_name); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="bntm-form-group">
                <label>Period</label>
                <input type="month" id="period-filter" value="<?php echo $period; ?>" 
                       onchange="window.location.href='?tab=ledger&account_id=<?php echo $selected_account; ?>&period='+this.value">
            </div>
        </div>
        
        <?php if ($selected_account): ?>
            <?php
            $ledger_data = ac_get_ledger_report_data($business_id, $selected_account, $period);
            $account = $ledger_data['account'];
            $transactions = $ledger_data['transactions'];
            $opening_balance = $ledger_data['opening_balance'];
            $closing_balance = $ledger_data['closing_balance'];
            ?>
            <?php if ($account): ?>
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; margin-bottom: 20px;">
                <div style="background: #f9fafb; padding: 15px; border-radius: 8px; flex: 1 1 320px;">
                    <h4 style="margin: 0 0 10px 0;"><?php echo esc_html($account->account_code . ' - ' . $account->account_name); ?></h4>
                    <p style="margin: 0; color: #6b7280;">
                        Account Type: <strong><?php echo ucfirst($account->account_type); ?></strong> | 
                        Normal Balance: <strong><?php echo ucfirst($account->normal_balance); ?></strong>
                    </p>
                    <p style="margin: 10px 0 0 0; font-size: 16px;">
                        Opening Balance: <strong><?php echo ac_format_amount($opening_balance); ?></strong>
                    </p>
                </div>
                <div style="display: flex; align-items: flex-start;">
                    <button
                        type="button"
                        id="export-ledger-pdf-btn"
                        class="bntm-btn-secondary"
                        data-account-id="<?php echo esc_attr($selected_account); ?>"
                        data-period="<?php echo esc_attr($period); ?>"
                        data-nonce="<?php echo esc_attr($ledger_pdf_nonce); ?>"
                    >
                        Export General Ledger PDF
                    </button>
                </div>
            </div>
            
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Entry #</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background: #f9fafb; font-weight: bold;">
                        <td colspan="5">Opening Balance</td>
                        <td><?php echo ac_format_amount($opening_balance); ?></td>
                    </tr>
                    
                    <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" style="text-align:center;">No transactions in this period</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($txn->entry_date)); ?></td>
                            <td><?php echo esc_html($txn->entry_number); ?></td>
                            <td><?php echo esc_html($txn->description ?: $txn->journal_desc); ?></td>
                            <td><?php echo $txn->debit_amount > 0 ? ac_format_amount($txn->debit_amount) : '-'; ?></td>
                            <td><?php echo $txn->credit_amount > 0 ? ac_format_amount($txn->credit_amount) : '-'; ?></td>
                            <td><strong><?php echo ac_format_amount($txn->running_balance); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr style="background: #f9fafb; font-weight: bold;">
                            <td colspan="5">Closing Balance</td>
                            <td><?php echo ac_format_amount($closing_balance); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color: #dc2626; text-align: center; padding: 20px 0;">
                The selected account could not be found.
            </p>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #6b7280; text-align: center; padding: 40px 0;">
                Please select an account to view its general ledger
            </p>
        <?php endif; ?>
    </div>
    <script>
    (function() {
        const exportBtn = document.getElementById('export-ledger-pdf-btn');
        if (!exportBtn) return;
        
        exportBtn.addEventListener('click', function() {
            const params = new URLSearchParams({
                action: 'ac_export_general_ledger_pdf',
                account_id: this.dataset.accountId,
                period: this.dataset.period,
                _ajax_nonce: this.dataset.nonce
            });
            
            window.open(ajaxurl + '?' + params.toString(), '_blank');
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Trial Balance Tab
 */
function ac_trial_balance_tab($business_id) {
    global $wpdb;
    
    $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : date('Y-m');
    
    // Get all accounts with balances
    $accounts = $wpdb->get_results($wpdb->prepare("
        SELECT 
            coa.*,
            COALESCE(SUM(jl.debit_amount), 0) as total_debit,
            COALESCE(SUM(jl.credit_amount), 0) as total_credit
        FROM {$wpdb->prefix}ac_chart_of_accounts coa
        LEFT JOIN {$wpdb->prefix}ac_journal_lines jl ON coa.id = jl.account_id
        LEFT JOIN {$wpdb->prefix}ac_journal_entries je ON jl.journal_entry_id = je.id
            AND je.status = 'posted'
            AND DATE_FORMAT(je.entry_date, '%%Y-%%m') <= %s
        WHERE coa.business_id = %d AND coa.is_active = 1
        GROUP BY coa.id
        ORDER BY coa.account_type ASC, coa.account_code ASC
    ", $period, $business_id));
    
    $total_debit = 0;
    $total_credit = 0;
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Trial Balance</h3>
            <div class="bntm-form-group" style="margin: 0; max-width: 200px;">
                <input type="month" value="<?php echo $period; ?>" 
                       onchange="window.location.href='?tab=trial_balance&period='+this.value">
            </div>
        </div>
        
        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <h4 style="margin: 0;">Trial Balance as of <?php echo date('F Y', strtotime($period . '-01')); ?></h4>
        </div>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Account Code</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th style="text-align: right;">Debit</th>
                    <th style="text-align: right;">Credit</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_type = '';
                foreach ($accounts as $account): 
                    $balance = 0;
                    $debit_col = 0;
                    $credit_col = 0;
                    
                    if ($account->normal_balance === 'debit') {
                        $balance = $account->total_debit - $account->total_credit;
                        if ($balance > 0) $debit_col = $balance;
                    } else {
                        $balance = $account->total_credit - $account->total_debit;
                        if ($balance > 0) $credit_col = $balance;
                    }
                    
                    // Show subtotal when type changes
                    if ($current_type && $current_type !== $account->account_type): ?>
                        <tr style="border-top: 2px solid #d1d5db;"><td colspan="5"></td></tr>
                    <?php endif; 
                    $current_type = $account->account_type;
                    
                    if ($balance != 0):
                        $total_debit += $debit_col;
                        $total_credit += $credit_col;
                    ?>
                <tr>
                    <td><?php echo esc_html($account->account_code); ?></td>
                    <td><?php echo esc_html($account->account_name); ?></td>
                    <td><span class="bntm-badge"><?php echo ucfirst($account->account_type); ?></span></td>
                    <td style="text-align: right;"><?php echo $debit_col > 0 ? ac_format_amount($debit_col) : '-'; ?></td>
                    <td style="text-align: right;"><?php echo $credit_col > 0 ? ac_format_amount($credit_col) : '-'; ?></td>
                </tr>
                <?php endif; endforeach; ?>
                
                <tr style="background: #f3f4f6; font-weight: bold; border-top: 2px solid #111827;">
                    <td colspan="3">TOTAL</td>
                    <td style="text-align: right;"><?php echo ac_format_amount($total_debit); ?></td>
                    <td style="text-align: right;"><?php echo ac_format_amount($total_credit); ?></td>
                </tr>
                
                <?php if (abs($total_debit - $total_credit) < 0.01): ?>
                <tr style="background: #d1fae5;">
                    <td colspan="5" style="text-align: center; color: #065f46; font-weight: bold;">
                        Trial Balance is Balanced ✓
                    </td>
                </tr>
                <?php else: ?>
                <tr style="background: #fee2e2;">
                    <td colspan="5" style="text-align: center; color: #991b1b; font-weight: bold;">
                        Trial Balance is NOT Balanced - Difference: <?php echo ac_format_amount(abs($total_debit - $total_credit)); ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 20px; text-align: right;">
            <button onclick="window.print()" class="bntm-btn-secondary">Print Trial Balance</button>
        </div>
    </div>
    
    <style>
    @media print {
        .bntm-tabs, button { display: none !important; }
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Chart of Accounts Tab
 */
function ac_accounts_tab($business_id) {
    global $wpdb;
    
    $accounts = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ac_chart_of_accounts
        WHERE business_id = %d
        ORDER BY account_type ASC, account_code ASC
    ", $business_id));
    
    $nonce = wp_create_nonce('ac_account_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Chart of Accounts</h3>
            <button id="new-account-btn" class="bntm-btn-primary">New Account</button>
        </div>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th>Normal Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="6" style="text-align:center;">No accounts configured</td></tr>
                <?php else: 
                    $current_type = '';
                    foreach ($accounts as $account): 
                        if ($current_type !== $account->account_type):
                            $current_type = $account->account_type;
                ?>
                <tr style="background: #f3f4f6; font-weight: bold;">
                    <td colspan="6"><?php echo strtoupper($account->account_type); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?php echo esc_html($account->account_code); ?></strong></td>
                    <td><?php echo esc_html($account->account_name); ?></td>
                    <td><?php echo ucfirst($account->account_type); ?></td>
                    <td><?php echo ucfirst($account->normal_balance); ?></td>
                    <td>
                        <span class="bntm-badge <?php echo $account->is_active ? 'bntm-badge-posted' : 'bntm-badge-void'; ?>">
                            <?php echo $account->is_active ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="bntm-btn-small edit-account-btn" data-id="<?php echo $account->id; ?>">
                            Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Account Modal -->
    <div id="account-modal" class="bntm-modal" style="display: none;">
        <div class="bntm-modal-content">
            <span class="bntm-modal-close">&times;</span>
            <h2 id="account-modal-title">New Account</h2>
            
            <form id="account-form" class="bntm-form">
                <input type="hidden" name="account_id" id="account_id">
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Account Code *</label>
                        <input type="text" name="account_code" id="account_code" required>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label>Account Type *</label>
                        <select name="account_type" id="account_type" required>
                            <option value="">Select Type</option>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="revenue">Revenue</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                </div>
                
                <div class="bntm-form-group">
                    <label>Account Name *</label>
                    <input type="text" name="account_name" id="account_name" required>
                </div>
                
                <div class="bntm-form-group">
                    <label>Normal Balance *</label>
                    <select name="normal_balance" id="normal_balance" required>
                        <option value="">Select Normal Balance</option>
                        <option value="debit">Debit</option>
                        <option value="credit">Credit</option>
                    </select>
                    <small>Assets & Expenses = Debit | Liabilities, Equity & Revenue = Credit</small>
                </div>
                
                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" rows="3"></textarea>
                </div>
                
                <div class="bntm-form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                        Active Account
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="bntm-btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="bntm-btn-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        const modal = document.getElementById('account-modal');
        const form = document.getElementById('account-form');
        
        // New account
        document.getElementById('new-account-btn').addEventListener('click', function() {
            document.getElementById('account-modal-title').textContent = 'New Account';
            form.reset();
            document.getElementById('account_id').value = '';
            document.getElementById('is_active').checked = true;
            modal.style.display = 'block';
        });
        
        // Edit account
        document.querySelectorAll('.edit-account-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const accountId = this.dataset.id;
                
                const formData = new FormData();
                formData.append('action', 'ac_get_account');
                formData.append('account_id', accountId);
                formData.append('nonce', nonce);
                
                fetch(ajaxurl, {method: 'POST', body: formData})
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        const account = json.data.account;
                        document.getElementById('account-modal-title').textContent = 'Edit Account';
                        document.getElementById('account_id').value = account.id;
                        document.getElementById('account_code').value = account.account_code;
                        document.getElementById('account_name').value = account.account_name;
                        document.getElementById('account_type').value = account.account_type;
                        document.getElementById('normal_balance').value = account.normal_balance;
                        document.getElementById('description').value = account.description || '';
                        document.getElementById('is_active').checked = account.is_active == 1;
                        modal.style.display = 'block';
                    }
                });
            });
        });
        
        // Modal close
        document.querySelectorAll('.bntm-modal-close, .modal-close-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        });
        
        // Save account
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'ac_save_account');
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Save Account';
                }
            });
        });
        
        // Auto-suggest normal balance based on account type
        document.getElementById('account_type').addEventListener('change', function() {
            const normalBalance = document.getElementById('normal_balance');
            const type = this.value;
            
            if (type === 'asset' || type === 'expense') {
                normalBalance.value = 'debit';
            } else if (type === 'liability' || type === 'equity' || type === 'revenue') {
                normalBalance.value = 'credit';
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Finance Sync Tab
 */
function ac_finance_sync_tab($business_id) {
    if (!bntm_is_module_enabled('fn')) {
        return '<div class="bntm-notice">Finance Module is not enabled.</div>';
    }
    
    global $wpdb;
    $fn_table = $wpdb->prefix . 'fn_transactions';
    
    // Get finance transactions not yet imported
    $fn_transactions = $wpdb->get_results($wpdb->prepare("
        SELECT ft.*,
        (SELECT COUNT(*) FROM {$wpdb->prefix}ac_journal_entries 
         WHERE reference_type='fn_transaction' AND reference_id=ft.id) as is_imported
        FROM {$fn_table} ft
        WHERE ft.business_id = %d
        ORDER BY ft.created_at DESC
        LIMIT 100
    ", $business_id));
    
    $nonce = wp_create_nonce('ac_fn_sync_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Sync with Finance Module</h3>
        <p>Import transactions from Finance Module to create journal entries</p>
        
        <div style="margin-bottom: 15px;">
            <button id="sync-selected-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>">
                Sync Selected Transactions
            </button>
            <span id="selected-count" style="margin-left: 15px;"></span>
        </div>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th width="40"><input type="checkbox" id="select-all"></th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($fn_transactions)): ?>
                <tr><td colspan="7" style="text-align:center;">No finance transactions found</td></tr>
                <?php else: foreach ($fn_transactions as $txn): ?>
                <tr>
                    <td>
                        <input type="checkbox" class="txn-checkbox" 
                               data-id="<?php echo $txn->id; ?>"
                               data-imported="<?php echo $txn->is_imported; ?>"
                               <?php echo $txn->is_imported ? 'disabled' : ''; ?>>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($txn->created_at)); ?></td>
                    <td>
                        <span class="bntm-badge <?php echo $txn->type === 'income' ? 'bntm-badge-posted' : 'bntm-badge-draft'; ?>">
                            <?php echo ucfirst($txn->type); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html($txn->category); ?></td>
                    <td><?php echo ac_format_amount($txn->amount); ?></td>
                    <td><?php echo esc_html($txn->notes); ?></td>
                    <td>
                        <?php if ($txn->is_imported): ?>
                        <span style="color: #059669;">Synced ✓</span>
                        <?php else: ?>
                        <span style="color: #6b7280;">Not Synced</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    (function() {
        const selectAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.txn-checkbox:not([disabled])');
        const selectedCount = document.getElementById('selected-count');
        
        function updateCount() {
            const selected = document.querySelectorAll('.txn-checkbox:checked').length;
            selectedCount.textContent = selected > 0 ? `${selected} selected` : '';
        }
        
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateCount();
        });
        
        checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
        
        document.getElementById('sync-selected-btn').addEventListener('click', function() {
            const selected = Array.from(document.querySelectorAll('.txn-checkbox:checked'))
                .map(cb => cb.dataset.id);
            
            if (selected.length === 0) {
                alert('Please select at least one transaction');
                return;
            }
            
            if (!confirm(`Sync ${selected.length} transaction(s)?`)) return;
            
            this.disabled = true;
            this.textContent = 'Syncing...';
            
            const formData = new FormData();
            formData.append('action', 'ac_sync_fn_transactions');
            formData.append('transaction_ids', JSON.stringify(selected));
            formData.append('nonce', this.dataset.nonce);
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                alert(json.data.message);
                if (json.success) location.reload();
                else {
                    this.disabled = false;
                    this.textContent = 'Sync Selected Transactions';
                }
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ============================================================================
// 4. AJAX HANDLERS
// ============================================================================

/**
 * Save Journal Entry
 */
function bntm_ajax_ac_save_journal_entry() {
    check_ajax_referer('ac_journal_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    
    $entry_date = sanitize_text_field($_POST['entry_date']);
    $entry_number = sanitize_text_field($_POST['entry_number']);
    $description = sanitize_textarea_field($_POST['description']);
    
    $account_ids = $_POST['account_id'];
    $debits = $_POST['debit'];
    $credits = $_POST['credit'];
    $line_descriptions = $_POST['line_description'];
    
    // Validate balanced entry
    $total_debit = 0;
    $total_credit = 0;
    
    foreach ($debits as $debit) {
        $total_debit += floatval($debit);
    }
    foreach ($credits as $credit) {
        $total_credit += floatval($credit);
    }
    
    if (abs($total_debit - $total_credit) > 0.01) {
        wp_send_json_error(['message' => 'Journal entry must be balanced']);
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Insert journal entry
        $result = $wpdb->insert(
            $wpdb->prefix . 'ac_journal_entries',
            [
                'rand_id' => bntm_rand_id(),
                'business_id' => $business_id,
                'entry_number' => $entry_number,
                'entry_date' => $entry_date,
                'description' => $description,
                'total_debit' => $total_debit,
                'total_credit' => $total_credit,
                'status' => 'draft',
                'created_by' => $business_id
            ],
            ['%s','%d','%s','%s','%s','%f','%f','%s','%d']
        );
        
        if (!$result) {
            throw new Exception('Failed to create journal entry');
        }
        
        $journal_id = $wpdb->insert_id;
        
        // Insert journal lines
        for ($i = 0; $i < count($account_ids); $i++) {
            $debit = floatval($debits[$i]);
            $credit = floatval($credits[$i]);
            
            if ($debit == 0 && $credit == 0) continue;
            
            $line_result = $wpdb->insert(
                $wpdb->prefix . 'ac_journal_lines',
                [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'journal_entry_id' => $journal_id,
                    'account_id' => intval($account_ids[$i]),
                    'debit_amount' => $debit,
                    'credit_amount' => $credit,
                    'description' => sanitize_text_field($line_descriptions[$i])
                ],
                ['%s','%d','%d','%d','%f','%f','%s']
            );
            
            if (!$line_result) {
                throw new Exception('Failed to create journal line');
            }
        }
        
        $wpdb->query('COMMIT');
        wp_send_json_success(['message' => 'Journal entry created successfully!']);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
add_action('wp_ajax_ac_save_journal_entry', 'bntm_ajax_ac_save_journal_entry');

/**
 * Get Journal Entry Details
 */
function bntm_ajax_ac_get_journal_entry() {
    check_ajax_referer('ac_journal_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $journal_id = intval($_POST['journal_id']);
    
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ac_journal_entries WHERE id = %d",
        $journal_id
    ));
    
    if (!$entry) {
        wp_send_json_error(['message' => 'Journal entry not found']);
    }
    
    $lines = $wpdb->get_results($wpdb->prepare("
        SELECT jl.*, coa.account_code, coa.account_name
        FROM {$wpdb->prefix}ac_journal_lines jl
        JOIN {$wpdb->prefix}ac_chart_of_accounts coa ON jl.account_id = coa.id
        WHERE jl.journal_entry_id = %d
        ORDER BY jl.id ASC
    ", $journal_id));
    
    ob_start();
    ?>
    <h3>Journal Entry: <?php echo esc_html($entry->entry_number); ?></h3>
    <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin: 15px 0;">
        <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($entry->entry_date)); ?></p>
        <p><strong>Description:</strong> <?php echo esc_html($entry->description); ?></p>
        <p><strong>Status:</strong> 
            <span class="bntm-badge bntm-badge-<?php echo $entry->status; ?>">
                <?php echo ucfirst($entry->status); ?>
            </span>
        </p>
    </div>
    
    <table class="bntm-table">
        <thead>
            <tr>
                <th>Account</th>
                <th>Description</th>
                <th>Debit</th>
                <th>Credit</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $line): ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($line->account_code); ?></strong><br>
                    <small><?php echo esc_html($line->account_name); ?></small>
                </td>
                <td><?php echo esc_html($line->description); ?></td>
                <td><?php echo $line->debit_amount > 0 ? ac_format_amount($line->debit_amount) : '-'; ?></td>
                <td><?php echo $line->credit_amount > 0 ? ac_format_amount($line->credit_amount) : '-'; ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background: #f3f4f6; font-weight: bold;">
                <td colspan="2">TOTAL</td>
                <td><?php echo ac_format_amount($entry->total_debit); ?></td>
                <td><?php echo ac_format_amount($entry->total_credit); ?></td>
            </tr>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_ac_get_journal_entry', 'bntm_ajax_ac_get_journal_entry');

/**
 * Post Journal Entry
 */
function bntm_ajax_ac_post_journal_entry() {
    check_ajax_referer('ac_journal_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $journal_id = intval($_POST['journal_id']);
    $business_id = get_current_user_id();
    
    // Verify ownership
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ac_journal_entries WHERE id = %d AND business_id = %d",
        $journal_id, $business_id
    ));
    
    if (!$entry) {
        wp_send_json_error(['message' => 'Journal entry not found']);
    }
    
    if ($entry->status !== 'draft') {
        wp_send_json_error(['message' => 'Only draft entries can be posted']);
    }
    
    // Update status to posted
    $result = $wpdb->update(
        $wpdb->prefix . 'ac_journal_entries',
        [
            'status' => 'posted',
            'posted_at' => current_time('mysql')
        ],
        ['id' => $journal_id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        // Update ledger balances
        ac_update_ledger_balances($journal_id);
        wp_send_json_success(['message' => 'Journal entry posted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to post journal entry']);
    }
}
add_action('wp_ajax_ac_post_journal_entry', 'bntm_ajax_ac_post_journal_entry');

/**
 * Export General Ledger PDF
 */
function bntm_ajax_ac_export_general_ledger_pdf() {
    check_ajax_referer('ac_ledger_pdf_nonce');
    
    if (!is_user_logged_in()) {
        wp_die('Unauthorized access');
    }
    
    $business_id = get_current_user_id();
    $account_id = intval($_GET['account_id'] ?? 0);
    $period = sanitize_text_field($_GET['period'] ?? date('Y-m'));
    
    if ($account_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $period)) {
        wp_die('Invalid export parameters');
    }
    
    $ledger_data = ac_get_ledger_report_data($business_id, $account_id, $period);
    $account = $ledger_data['account'];
    
    if (!$account) {
        wp_die('Ledger account not found');
    }
    
    $period_label = date('F Y', strtotime($period . '-01'));
    $company_name = bntm_get_setting('site_title', get_bloginfo('name'));
    $generated_at = current_time('F d, Y h:i A');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>General Ledger - <?php echo esc_html($account->account_code); ?></title>
        <style>
            @page { margin: 1.5cm; }
            body {
                font-family: Arial, sans-serif;
                font-size: 11pt;
                color: #111;
                line-height: 1.4;
            }
            .header {
                text-align: center;
                margin-bottom: 24px;
                padding-bottom: 14px;
                border-bottom: 2px solid #111;
            }
            .header h1,
            .header h2,
            .header p {
                margin: 0 0 6px;
            }
            .meta {
                margin-bottom: 18px;
                padding: 12px 14px;
                background: #f7f7f7;
                border: 1px solid #ddd;
            }
            .meta-row {
                margin: 4px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th, td {
                padding: 8px 10px;
                border: 1px solid #d9d9d9;
                vertical-align: top;
            }
            th {
                background: #f0f0f0;
                text-align: left;
            }
            .text-right {
                text-align: right;
            }
            .summary-row {
                background: #f9f9f9;
                font-weight: bold;
            }
            .footer {
                margin-top: 24px;
                font-size: 9pt;
                color: #555;
            }
            @media print {
                .no-print {
                    display: none !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?php echo esc_html($company_name); ?></h1>
            <h2>GENERAL LEDGER</h2>
            <p><?php echo esc_html($period_label); ?></p>
        </div>

        <div class="meta">
            <div class="meta-row"><strong>Account:</strong> <?php echo esc_html($account->account_code . ' - ' . $account->account_name); ?></div>
            <div class="meta-row"><strong>Account Type:</strong> <?php echo esc_html(ucfirst($account->account_type)); ?></div>
            <div class="meta-row"><strong>Normal Balance:</strong> <?php echo esc_html(ucfirst($account->normal_balance)); ?></div>
            <div class="meta-row"><strong>Opening Balance:</strong> <?php echo esc_html(ac_format_amount($ledger_data['opening_balance'])); ?></div>
            <div class="meta-row"><strong>Closing Balance:</strong> <?php echo esc_html(ac_format_amount($ledger_data['closing_balance'])); ?></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 14%;">Date</th>
                    <th style="width: 16%;">Entry #</th>
                    <th style="width: 30%;">Description</th>
                    <th style="width: 13%;" class="text-right">Debit</th>
                    <th style="width: 13%;" class="text-right">Credit</th>
                    <th style="width: 14%;" class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                <tr class="summary-row">
                    <td colspan="5">Opening Balance</td>
                    <td class="text-right"><?php echo esc_html(ac_format_amount($ledger_data['opening_balance'])); ?></td>
                </tr>
                <?php if (empty($ledger_data['transactions'])): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No transactions in this period</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($ledger_data['transactions'] as $txn): ?>
                    <tr>
                        <td><?php echo esc_html(date('M d, Y', strtotime($txn->entry_date))); ?></td>
                        <td><?php echo esc_html($txn->entry_number); ?></td>
                        <td><?php echo esc_html($txn->description ?: $txn->journal_desc); ?></td>
                        <td class="text-right"><?php echo $txn->debit_amount > 0 ? esc_html(ac_format_amount($txn->debit_amount)) : '-'; ?></td>
                        <td class="text-right"><?php echo $txn->credit_amount > 0 ? esc_html(ac_format_amount($txn->credit_amount)) : '-'; ?></td>
                        <td class="text-right"><?php echo esc_html(ac_format_amount($txn->running_balance)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="summary-row">
                    <td colspan="5">Closing Balance</td>
                    <td class="text-right"><?php echo esc_html(ac_format_amount($ledger_data['closing_balance'])); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            Generated on <?php echo esc_html($generated_at); ?> by BNTM Hub.
        </div>

        <script class="no-print">window.print();</script>
    </body>
    </html>
    <?php
    
    header('Content-Type: text/html; charset=UTF-8');
    echo ob_get_clean();
    exit;
}
add_action('wp_ajax_ac_export_general_ledger_pdf', 'bntm_ajax_ac_export_general_ledger_pdf');

/**
 * Save Account
 */
function bntm_ajax_ac_save_account() {
    check_ajax_referer('ac_account_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    
    $account_id = intval($_POST['account_id']);
    $account_code = sanitize_text_field($_POST['account_code']);
    $account_name = sanitize_text_field($_POST['account_name']);
    $account_type = sanitize_text_field($_POST['account_type']);
    $normal_balance = sanitize_text_field($_POST['normal_balance']);
    $description = sanitize_textarea_field($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $data = [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'account_type' => $account_type,
        'normal_balance' => $normal_balance,
        'description' => $description,
        'is_active' => $is_active
    ];
    
    $format = ['%s','%s','%s','%s','%s','%d'];
    
    if ($account_id > 0) {
        // Update existing account
        $result = $wpdb->update(
            $wpdb->prefix . 'ac_chart_of_accounts',
            $data,
            ['id' => $account_id, 'business_id' => $business_id],
            $format,
            ['%d', '%d']
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Account updated successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to update account']);
        }
    } else {
        // Create new account
        $data['rand_id'] = bntm_rand_id();
        $data['business_id'] = $business_id;
        $format = array_merge(['%s','%d'], $format);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ac_chart_of_accounts',
            $data,
            $format
        );
        
        if ($result) {
            wp_send_json_success(['message' => 'Account created successfully!']);
        } else {
            wp_send_json_error(['message' => 'Failed to create account. Code may already exist.']);
        }
    }
}
add_action('wp_ajax_ac_save_account', 'bntm_ajax_ac_save_account');

/**
 * Get Account Details
 */
function bntm_ajax_ac_get_account() {
    check_ajax_referer('ac_account_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $account_id = intval($_POST['account_id']);
    
    $account = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ac_chart_of_accounts WHERE id = %d",
        $account_id
    ));
    
    if ($account) {
        wp_send_json_success(['account' => $account]);
    } else {
        wp_send_json_error(['message' => 'Account not found']);
    }
}
add_action('wp_ajax_ac_get_account', 'bntm_ajax_ac_get_account');

/**
 * Sync Finance Transactions
 */
function bntm_ajax_ac_sync_fn_transactions() {
    check_ajax_referer('ac_fn_sync_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $business_id = get_current_user_id();
    $transaction_ids = json_decode(stripslashes($_POST['transaction_ids']), true);
    
    if (empty($transaction_ids)) {
        wp_send_json_error(['message' => 'No transactions selected']);
    }
    
    $fn_table = $wpdb->prefix . 'fn_transactions';
    $synced = 0;
    
    foreach ($transaction_ids as $txn_id) {
        $txn = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$fn_table} WHERE id = %d AND business_id = %d",
            $txn_id, $business_id
        ));
        
        if (!$txn) continue;
        
        // Check if already synced
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ac_journal_entries 
             WHERE reference_type = 'fn_transaction' AND reference_id = %d",
            $txn_id
        ));
        
        if ($exists) continue;
        
        // Create journal entry from finance transaction
        $entry_number = 'FN-' . $txn->id . '-' . date('Ymd');
        $description = 'Finance: ' . $txn->category . ' - ' . $txn->notes;
        
        // Determine accounts (this is simplified - you should map to actual accounts)
        // Income: Debit Cash, Credit Revenue
        // Expense: Debit Expense, Credit Cash
        
        if ($txn->type === 'income') {
            $debit_account = ac_get_account_by_code('1010', $business_id); // Cash
            $credit_account = ac_get_account_by_code('4010', $business_id); // Revenue
        } else {
            $debit_account = ac_get_account_by_code('5010', $business_id); // Expense
            $credit_account = ac_get_account_by_code('1010', $business_id); // Cash
        }
        
        if (!$debit_account || !$credit_account) continue;
        
        // Create journal entry
        $wpdb->query('START TRANSACTION');
        
        try {
            $result = $wpdb->insert(
                $wpdb->prefix . 'ac_journal_entries',
                [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'entry_number' => $entry_number,
                    'entry_date' => date('Y-m-d', strtotime($txn->created_at)),
                    'reference_type' => 'fn_transaction',
                    'reference_id' => $txn->id,
                    'description' => $description,
                    'total_debit' => $txn->amount,
                    'total_credit' => $txn->amount,
                    'status' => 'posted',
                    'created_by' => $business_id,
                    'posted_at' => current_time('mysql')
                ],
                ['%s','%d','%s','%s','%s','%d','%s','%f','%f','%s','%d','%s']
            );
            
            if (!$result) throw new Exception('Failed to create journal entry');
            
            $journal_id = $wpdb->insert_id;
            
            // Debit line
            $wpdb->insert(
                $wpdb->prefix . 'ac_journal_lines',
                [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'journal_entry_id' => $journal_id,
                    'account_id' => $debit_account->id,
                    'debit_amount' => $txn->amount,
                    'credit_amount' => 0,
                    'description' => $description
                ],
                ['%s','%d','%d','%d','%f','%f','%s']
            );
            
            // Credit line
            $wpdb->insert(
                $wpdb->prefix . 'ac_journal_lines',
                [
                    'rand_id' => bntm_rand_id(),
                    'business_id' => $business_id,
                    'journal_entry_id' => $journal_id,
                    'account_id' => $credit_account->id,
                    'debit_amount' => 0,
                    'credit_amount' => $txn->amount,
                    'description' => $description
                ],
                ['%s','%d','%d','%d','%f','%f','%s']
            );
            
            $wpdb->query('COMMIT');
            $synced++;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
        }
    }
    
    wp_send_json_success(['message' => "Successfully synced {$synced} transaction(s)!"]);
}
add_action('wp_ajax_ac_sync_fn_transactions', 'bntm_ajax_ac_sync_fn_transactions');

// ============================================================================
// 5. HELPER FUNCTIONS
// ============================================================================

/**
 * Format amount with currency
 */
function ac_format_amount($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Get general ledger report data for a single account and period
 */
function ac_get_ledger_report_data($business_id, $account_id, $period) {
    global $wpdb;
    
    $account = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ac_chart_of_accounts WHERE id = %d AND business_id = %d",
        $account_id,
        $business_id
    ));
    
    if (!$account) {
        return [
            'account' => null,
            'transactions' => [],
            'opening_balance' => 0,
            'closing_balance' => 0,
        ];
    }
    
    $transactions = $wpdb->get_results($wpdb->prepare("
        SELECT jl.*, je.entry_number, je.entry_date, je.description as journal_desc,
               coa.account_name
        FROM {$wpdb->prefix}ac_journal_lines jl
        JOIN {$wpdb->prefix}ac_journal_entries je ON jl.journal_entry_id = je.id
        JOIN {$wpdb->prefix}ac_chart_of_accounts coa ON jl.account_id = coa.id
        WHERE jl.account_id = %d 
          AND je.status = 'posted'
          AND DATE_FORMAT(je.entry_date, '%%Y-%%m') = %s
        ORDER BY je.entry_date ASC, je.id ASC, jl.id ASC
    ", $account_id, $period));
    
    $opening = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COALESCE(SUM(jl.debit_amount), 0) as total_debit,
            COALESCE(SUM(jl.credit_amount), 0) as total_credit
        FROM {$wpdb->prefix}ac_journal_lines jl
        JOIN {$wpdb->prefix}ac_journal_entries je ON jl.journal_entry_id = je.id
        WHERE jl.account_id = %d 
          AND je.status = 'posted'
          AND je.entry_date < %s
    ", $account_id, $period . '-01'));
    
    $opening_balance = ($account->normal_balance === 'debit')
        ? ($opening->total_debit - $opening->total_credit)
        : ($opening->total_credit - $opening->total_debit);
    
    $running_balance = $opening_balance;
    foreach ($transactions as $txn) {
        if ($account->normal_balance === 'debit') {
            $running_balance += $txn->debit_amount - $txn->credit_amount;
        } else {
            $running_balance += $txn->credit_amount - $txn->debit_amount;
        }
        $txn->running_balance = $running_balance;
    }
    
    return [
        'account' => $account,
        'transactions' => $transactions,
        'opening_balance' => $opening_balance,
        'closing_balance' => $running_balance,
    ];
}

/**
 * Get dashboard statistics
 */
function ac_get_dashboard_stats($business_id) {
    global $wpdb;
    
    $stats = [
        'total_assets' => 0,
        'total_liabilities' => 0,
        'total_equity' => 0,
        'journal_entries' => 0
    ];
    
    // Get account balances by type
    $balances = $wpdb->get_results($wpdb->prepare("
        SELECT 
            coa.account_type,
            coa.normal_balance,
            COALESCE(SUM(jl.debit_amount), 0) as total_debit,
            COALESCE(SUM(jl.credit_amount), 0) as total_credit
        FROM {$wpdb->prefix}ac_chart_of_accounts coa
        LEFT JOIN {$wpdb->prefix}ac_journal_lines jl ON coa.id = jl.account_id
        LEFT JOIN {$wpdb->prefix}ac_journal_entries je ON jl.journal_entry_id = je.id
            AND je.status = 'posted'
        WHERE coa.business_id = %d AND coa.is_active = 1
        GROUP BY coa.account_type, coa.normal_balance
    ", $business_id));
    
    foreach ($balances as $balance) {
        $amount = ($balance->normal_balance === 'debit')
            ? ($balance->total_debit - $balance->total_credit)
            : ($balance->total_credit - $balance->total_debit);
        
        if ($balance->account_type === 'asset') {
            $stats['total_assets'] += $amount;
        } elseif ($balance->account_type === 'liability') {
            $stats['total_liabilities'] += $amount;
        } elseif ($balance->account_type === 'equity') {
            $stats['total_equity'] += $amount;
        }
    }
    
    // Get journal entries this month
    $stats['journal_entries'] = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->prefix}ac_journal_entries
        WHERE business_id = %d 
        AND DATE_FORMAT(entry_date, '%%Y-%%m') = %s
    ", $business_id, date('Y-m')));
    
    return $stats;
}

/**
 * Get accounts as options HTML
 */
function ac_get_accounts_options($business_id) {
    global $wpdb;
    
    $accounts = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ac_chart_of_accounts
        WHERE business_id = %d AND is_active = 1
        ORDER BY account_code ASC
    ", $business_id));
    
    $html = '';
    foreach ($accounts as $account) {
        $html .= sprintf(
            '<option value="%d">%s - %s</option>',
            $account->id,
            esc_html($account->account_code),
            esc_html($account->account_name)
        );
    }
    
    return $html;
}

/**
 * Get account by code
 */
function ac_get_account_by_code($code, $business_id) {
    global $wpdb;
    
    return $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ac_chart_of_accounts
        WHERE account_code = %s AND business_id = %d
    ", $code, $business_id));
}

/**
 * Update ledger balances after posting
 */
function ac_update_ledger_balances($journal_id) {
    global $wpdb;
    
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ac_journal_entries WHERE id = %d",
        $journal_id
    ));
    
    if (!$entry) return;
    
    $period = date('Y-m', strtotime($entry->entry_date));
    
    // Get all lines for this journal entry
    $lines = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}ac_journal_lines WHERE journal_entry_id = %d",
        $journal_id
    ));
    
    foreach ($lines as $line) {
        // Update or create ledger balance
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ac_ledger_balance
            WHERE account_id = %d AND period = %s
        ", $line->account_id, $period));
        
        if ($existing) {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}ac_ledger_balance
                SET total_debit = total_debit + %f,
                    total_credit = total_credit + %f
                WHERE id = %d
            ", $line->debit_amount, $line->credit_amount, $existing->id));
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'ac_ledger_balance',
                [
                    'business_id' => $entry->business_id,
                    'account_id' => $line->account_id,
                    'period' => $period,
                    'total_debit' => $line->debit_amount,
                    'total_credit' => $line->credit_amount
                ],
                ['%d','%d','%s','%f','%f']
            );
        }
    }
}

/**
 * Initialize default chart of accounts
 */
function ac_initialize_default_accounts() {
    global $wpdb;
    
    // Check if already initialized
    $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ac_chart_of_accounts");
    if ($existing > 0) return;
    
    $default_accounts = [
        // Assets
        ['1010', 'Cash', 'asset', 'debit'],
        ['1020', 'Accounts Receivable', 'asset', 'debit'],
        ['1030', 'Inventory', 'asset', 'debit'],
        ['1040', 'Prepaid Expenses', 'asset', 'debit'],
        ['1510', 'Equipment', 'asset', 'debit'],
        ['1520', 'Accumulated Depreciation - Equipment', 'asset', 'credit'],
        
        // Liabilities
        ['2010', 'Accounts Payable', 'liability', 'credit'],
        ['2020', 'Notes Payable', 'liability', 'credit'],
        ['2030', 'Accrued Expenses', 'liability', 'credit'],
        ['2040', 'Unearned Revenue', 'liability', 'credit'],
        
        // Equity
        ['3010', 'Owner\'s Capital', 'equity', 'credit'],
        ['3020', 'Owner\'s Drawings', 'equity', 'debit'],
        ['3030', 'Retained Earnings', 'equity', 'credit'],
        
        // Revenue
        ['4010', 'Sales Revenue', 'revenue', 'credit'],
        ['4020', 'Service Revenue', 'revenue', 'credit'],
        ['4030', 'Other Income', 'revenue', 'credit'],
        
        // Expenses
        ['5010', 'Cost of Goods Sold', 'expense', 'debit'],
        ['5020', 'Salaries Expense', 'expense', 'debit'],
        ['5030', 'Rent Expense', 'expense', 'debit'],
        ['5040', 'Utilities Expense', 'expense', 'debit'],
        ['5050', 'Depreciation Expense', 'expense', 'debit'],
        ['5060', 'Supplies Expense', 'expense', 'debit'],
        ['5070', 'Insurance Expense', 'expense', 'debit'],
        ['5080', 'Advertising Expense', 'expense', 'debit'],
        ['5090', 'Miscellaneous Expense', 'expense', 'debit'],
    ];
    
    foreach ($default_accounts as $account) {
        $wpdb->insert(
            $wpdb->prefix . 'ac_chart_of_accounts',
            [
                'rand_id' => bntm_rand_id(),
                'business_id' => 0, // Default accounts
                'account_code' => $account[0],
                'account_name' => $account[1],
                'account_type' => $account[2],
                'normal_balance' => $account[3],
                'is_active' => 1
            ],
            ['%s','%d','%s','%s','%s','%s','%d']
        );
    }
}

?>
