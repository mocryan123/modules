<?php
/**
 * Module Name: Notepad
 * Module Slug: notepad
 * Description: Quick note-taking system with create, edit, delete, and PDF export capabilities
 * Version: 1.0.0
 * Author: BNTM Framework
 * Icon: 📝
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_NOTEPAD_PATH', dirname(__FILE__) . '/');
define('BNTM_NOTEPAD_URL', plugin_dir_url(__FILE__));

// ============================================================================
// MODULE CONFIGURATION FUNCTIONS
// ============================================================================

/**
 * Define module pages
 */
function bntm_notepad_get_pages() {
    return [
        'Notepad' => '[bntm_notepad]'
    ];
}

/**
 * Define database tables
 */
function bntm_notepad_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'notepad_notes' => "CREATE TABLE {$prefix}notepad_notes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            category VARCHAR(100) DEFAULT 'General',
            color VARCHAR(20) DEFAULT '#FFFFFF',
            is_pinned TINYINT(1) DEFAULT 0,
            is_archived TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_category (category),
            INDEX idx_pinned (is_pinned),
            INDEX idx_archived (is_archived),
            INDEX idx_created (created_at)
        ) {$charset};"
    ];
}

/**
 * Register shortcodes
 */
function bntm_notepad_get_shortcodes() {
    return [
        'bntm_notepad' => 'bntm_shortcode_notepad'
    ];
}

/**
 * Create tables
 */
function bntm_notepad_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_notepad_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// ============================================================================
// AJAX ACTION HOOKS
// ============================================================================

add_action('wp_ajax_notepad_create_note', 'bntm_ajax_notepad_create_note');
add_action('wp_ajax_notepad_update_note', 'bntm_ajax_notepad_update_note');
add_action('wp_ajax_notepad_delete_note', 'bntm_ajax_notepad_delete_note');
add_action('wp_ajax_notepad_get_note', 'bntm_ajax_notepad_get_note');
add_action('wp_ajax_notepad_toggle_pin', 'bntm_ajax_notepad_toggle_pin');
add_action('wp_ajax_notepad_toggle_archive', 'bntm_ajax_notepad_toggle_archive');
add_action('wp_ajax_notepad_export_pdf', 'bntm_ajax_notepad_export_pdf');
add_action('wp_ajax_notepad_bulk_delete', 'bntm_ajax_notepad_bulk_delete');
add_action('wp_ajax_notepad_search_notes', 'bntm_ajax_notepad_search_notes');

// ============================================================================
// MAIN SHORTCODE FUNCTION
// ============================================================================

function bntm_shortcode_notepad() {
    // Check authentication
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access your notepad.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    
    // Get active tab
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all';
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-notepad-container">
        <!-- Notepad Header -->
        <div class="notepad-page-header">
            <div class="notepad-header-content">
                <div class="notepad-title-section">
                    <div class="notepad-icon-badge">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="12" y1="11" x2="8" y2="11"></line>
                            <line x1="16" y1="15" x2="8" y2="15"></line>
                            <line x1="16" y1="19" x2="8" y2="19"></line>
                        </svg>
                    </div>
                    <div>
                        <h1>My Notepad</h1>
                        <p class="notepad-subtitle">Organize your thoughts and ideas</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab Navigation -->
        <div class="bntm-tabs notepad-tabs">
            <a href="?tab=all" class="bntm-tab <?php echo $active_tab === 'all' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <span>All Notes</span>
            </a>
            <a href="?tab=pinned" class="bntm-tab <?php echo $active_tab === 'pinned' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 17v5"></path>
                    <path d="M9 12.5 12 14l3-1.5"></path>
                    <path d="M12 3v4"></path>
                    <path d="m8 11 4-4 4 4"></path>
                </svg>
                <span>Pinned</span>
            </a>
            <a href="?tab=archived" class="bntm-tab <?php echo $active_tab === 'archived' ? 'active' : ''; ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <path d="M3 9h18"></path>
                    <path d="M9 15h6"></path>
                </svg>
                <span>Archived</span>
            </a>
        </div>
        
        <!-- Tab Content -->
        <div class="bntm-tab-content notepad-tab-content">
            <?php if ($active_tab === 'all'): ?>
                <?php echo notepad_all_notes_tab($business_id); ?>
            <?php elseif ($active_tab === 'pinned'): ?>
                <?php echo notepad_pinned_notes_tab($business_id); ?>
            <?php elseif ($active_tab === 'archived'): ?>
                <?php echo notepad_archived_notes_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Notepad', $content);
}

// ============================================================================
// TAB RENDERING FUNCTIONS
// ============================================================================

/**
 * All Notes Tab
 */
function notepad_all_notes_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    // Get active notes (not archived)
    $notes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} 
         WHERE business_id = %d AND is_archived = 0 
         ORDER BY is_pinned DESC, updated_at DESC",
        $business_id
    ));
    
    // Get categories
    $categories = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT category FROM {$table} WHERE business_id = %d",
        $business_id
    ));
    
    $nonce = wp_create_nonce('notepad_nonce');
    
    ob_start();
    ?>
    <div class="notepad-controls-section">
        <!-- Primary Actions -->
        <div class="notepad-primary-actions">
            <button id="new-note-btn" class="bntm-btn-primary notepad-new-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>New Note</span>
            </button>
        </div>
        
        <!-- Filters and Search -->
        <div class="notepad-filters">
            <div class="notepad-search-wrapper">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="text" id="search-notes" class="notepad-search-input" placeholder="Search by title or content...">
            </div>
            
            <select id="filter-category" class="notepad-filter-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                <?php endforeach; ?>
            </select>
            
            <button id="bulk-delete-btn" class="bntm-btn-danger notepad-bulk-delete" style="display: none;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Delete Selected
            </button>
        </div>
        
        <!-- Statistics -->
        <div class="notepad-stats-bar">
            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo count($notes); ?></div>
                    <div class="stat-label">Total Notes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pinned-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 17v5"></path>
                        <path d="M9 12.5 12 14l3-1.5"></path>
                        <path d="M12 3v4"></path>
                        <path d="m8 11 4-4 4 4"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo count(array_filter($notes, fn($n) => $n->is_pinned)); ?></div>
                    <div class="stat-label">Pinned Notes</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon archive-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <path d="M3 9h18"></path>
                        <path d="M9 15h6"></path>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="archived-count">0</div>
                    <div class="stat-label">Archived</div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="notes-grid" class="notes-grid">
        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <line x1="10" y1="9" x2="8" y2="9"></line>
                    </svg>
                </div>
                <h3>No notes yet</h3>
                <p>Start creating notes to organize your thoughts</p>
                <button id="empty-new-note-btn" class="bntm-btn-primary" style="margin-top: 16px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Create First Note
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <?php echo notepad_render_note_card($note); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Note Editor Modal -->
    <div id="note-modal" class="bntm-modal">
        <div class="bntm-modal-content note-modal-content">
            <div class="bntm-modal-header">
                <div class="modal-title-wrapper">
                    <svg class="modal-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="11" x2="8" y2="11"></line>
                        <line x1="16" y1="15" x2="8" y2="15"></line>
                    </svg>
                    <h3 id="modal-title">New Note</h3>
                </div>
                <button class="bntm-modal-close">&times;</button>
            </div>
            <div class="bntm-modal-body">
                <form id="note-form">
                    <input type="hidden" id="note-id" value="">
                    
                    <div class="bntm-form-group">
                        <label class="form-label">Note Title</label>
                        <input type="text" id="note-title" class="bntm-input note-title-input" placeholder="Enter a descriptive title" required>
                    </div>
                    
                    <div class="bntm-form-group">
                        <label class="form-label">Content</label>
                        <textarea id="note-content" class="bntm-textarea note-content-textarea" rows="12" placeholder="Write your thoughts here..." required></textarea>
                    </div>
                    
                    <div class="note-form-footer">
                        <div class="note-form-options">
                            <div class="bntm-form-group">
                                <label class="form-label">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px;">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"></path>
                                    </svg>
                                    Category
                                </label>
                                <input type="text" id="note-category" class="bntm-input" placeholder="General" list="category-list">
                                <datalist id="category-list">
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="bntm-form-group">
                                <label class="form-label">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px;">
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg>
                                    Color
                                </label>
                                <div class="color-picker">
                                    <input type="radio" name="note-color" value="#FFFFFF" id="color-white" checked>
                                    <label for="color-white" style="background: #FFFFFF; border: 2px solid #e5e7eb;" title="White"></label>
                                    
                                    <input type="radio" name="note-color" value="#FEF3C7" id="color-yellow">
                                    <label for="color-yellow" style="background: #FEF3C7;" title="Yellow"></label>
                                    
                                    <input type="radio" name="note-color" value="#DBEAFE" id="color-blue">
                                    <label for="color-blue" style="background: #DBEAFE;" title="Blue"></label>
                                    
                                    <input type="radio" name="note-color" value="#D1FAE5" id="color-green">
                                    <label for="color-green" style="background: #D1FAE5;" title="Green"></label>
                                    
                                    <input type="radio" name="note-color" value="#FCE7F3" id="color-pink">
                                    <label for="color-pink" style="background: #FCE7F3;" title="Pink"></label>
                                    
                                    <input type="radio" name="note-color" value="#E0E7FF" id="color-indigo">
                                    <label for="color-indigo" style="background: #E0E7FF;" title="Indigo"></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="note-form-actions">
                            <button type="button" class="bntm-btn-secondary" onclick="document.getElementById('note-modal').style.display='none'">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 20 4 4"></polyline>
                                    <polyline points="20 4 4 20"></polyline>
                                </svg>
                                Cancel
                            </button>
                            <button type="submit" class="bntm-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Note
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
    /* Page Header Styles */
    .notepad-page-header {
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 2px solid #f0f0f0;
    }
    
    .notepad-header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .notepad-title-section {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .notepad-icon-badge {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    .notepad-page-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    
    .notepad-subtitle {
        font-size: 14px;
        color: #6b7280;
        margin: 4px 0 0 0;
    }
    
    /* Tab Navigation Styles */
    .notepad-tabs {
        display: flex;
        gap: 8px;
        margin: 32px 0;
        background: transparent;
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 0;
    }
    
    .bntm-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        border: none;
        border-bottom: 3px solid transparent;
        background: transparent;
        color: #6b7280;
        cursor: pointer;
        font-size: 15px;
        font-weight: 500;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .bntm-tab:hover {
        color: #111827;
        background: #f9fafb;
    }
    
    .bntm-tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
    }
    
    .bntm-tab svg {
        width: 18px;
        height: 18px;
    }
    
    /* Controls Section */
    .notepad-controls-section {
        background: white;
        padding: 24px;
        border-radius: 12px;
        margin-bottom: 28px;
        border: 1px solid #f0f0f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }
    
    .notepad-primary-actions {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .notepad-new-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .notepad-new-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
    }
    
    /* Filters Section */
    .notepad-filters {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 12px;
        margin-bottom: 24px;
        align-items: center;
    }
    
    .notepad-search-wrapper {
        position: relative;
        width: 100%;
    }
    
    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }
    
    .notepad-search-input {
        width: 100%;
        padding: 10px 14px 10px 40px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        background: #f9fafb;
        transition: all 0.3s ease;
    }
    
    .notepad-search-input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .notepad-filter-select {
        padding: 10px 14px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        color: #111827;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .notepad-filter-select:hover {
        border-color: #d1d5db;
    }
    
    .notepad-filter-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .notepad-bulk-delete {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
        padding: 10px 16px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .notepad-bulk-delete:hover {
        background: #fecaca;
    }
    
    /* Statistics Bar */
    .notepad-stats-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid #f0f0f0;
    }
    
    .stat-card {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        background: #f9fafb;
        border-radius: 10px;
        border: 1px solid #f0f0f0;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        background: white;
        border-color: #e5e7eb;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
    }
    
    .stat-icon.total-icon {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-icon.pinned-icon {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .stat-icon.archive-icon {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .stat-content {
        display: flex;
        flex-direction: column;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
    }
    
    .stat-label {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    
    /* Notes Grid */
    .notes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    @media (max-width: 768px) {
        .notes-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Note Card Styles */
    .note-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 18px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .note-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
    }
    
    .note-card:hover {
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        transform: translateY(-4px);
        border-color: #d1d5db;
    }
    
    .note-card:hover::before {
        transform: scaleX(1);
    }
    
    .note-card.pinned {
        border-left: 4px solid #f59e0b;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    }
    
    .note-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    
    .note-title {
        font-size: 16px;
        font-weight: 600;
        color: #111827;
        margin: 0;
        flex: 1;
        word-break: break-word;
    }
    
    .note-actions {
        display: flex;
        gap: 4px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    
    .note-card:hover .note-actions {
        opacity: 1;
    }
    
    .note-action-btn {
        background: white;
        border: 1px solid #e5e7eb;
        padding: 6px;
        cursor: pointer;
        color: #6b7280;
        border-radius: 6px;
        transition: all 0.2s ease;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .note-action-btn:hover {
        background: #f3f4f6;
        color: #111827;
        border-color: #d1d5db;
    }
    
    .note-action-btn:active {
        transform: scale(0.95);
    }
    
    .note-content-preview {
        font-size: 13px;
        color: #4b5563;
        margin: 10px 0;
        line-height: 1.6;
        max-height: 100px;
        overflow: hidden;
        white-space: pre-wrap;
        word-break: break-word;
        flex: 1;
    }
    
    .note-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #f3f4f6;
        font-size: 12px;
    }
    
    .note-category {
        background: linear-gradient(135deg, #e5e7ff 0%, #f3e8ff 100%);
        color: #6366f1;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }
    
    .note-date {
        color: #9ca3af;
    }
    
    .note-checkbox {
        position: absolute;
        top: 14px;
        left: 14px;
        z-index: 10;
        display: none;
    }
    
    .note-card:hover .note-checkbox {
        display: block;
    }
    
    .note-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #667eea;
    }
    
    /* Empty State */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 80px 40px;
        background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
        border-radius: 12px;
        border: 2px dashed #d1d5db;
    }
    
    .empty-state-icon {
        margin: 0 auto 24px;
        color: #d1d5db;
    }
    
    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        color: #374151;
        margin: 0 0 8px;
    }
    
    .empty-state p {
        font-size: 14px;
        color: #6b7280;
        margin: 0;
    }
    
    /* Modal Styles */
    .note-modal-content {
        max-width: 720px;
    }
    
    .modal-title-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .modal-icon {
        color: #667eea;
    }
    
    .bntm-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .form-label {
        display: flex;
        align-items: center;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .note-title-input {
        font-size: 15px;
        font-weight: 500;
    }
    
    .note-content-textarea {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .note-form-footer {
        margin-top: 24px;
    }
    
    .note-form-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    @media (max-width: 600px) {
        .note-form-options {
            grid-template-columns: 1fr;
        }
    }
    
    .color-picker {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .color-picker input[type="radio"] {
        display: none;
    }
    
    .color-picker label {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        cursor: pointer;
        border: 3px solid transparent;
        transition: all 0.2s ease;
    }
    
    .color-picker label:hover {
        transform: scale(1.1);
    }
    
    .color-picker input[type="radio"]:checked + label {
        border-color: #667eea;
        box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        transform: scale(1.15);
    }
    
    .note-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .notepad-stats-bar {
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        }
        
        .notes-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
        
        .notepad-filters {
            grid-template-columns: 1fr auto;
        }
        
        .notepad-bulk-delete {
            grid-column: 1 / -1;
        }
    }
    
    @media (max-width: 640px) {
        .notepad-page-header {
            margin-bottom: 20px;
        }
        
        .notepad-title-section {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .notepad-icon-badge {
            width: 48px;
            height: 48px;
        }
        
        .notepad-page-header h1 {
            font-size: 24px;
        }
        
        .notepad-tabs {
            gap: 4px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .bntm-tab {
            padding: 10px 16px;
            font-size: 13px;
            white-space: nowrap;
        }
        
        .notepad-filters {
            flex-direction: column;
        }
        
        .notepad-search-wrapper {
            min-width: 100%;
        }
        
        .notepad-filter-select {
            width: 100%;
        }
        
        .notes-grid {
            grid-template-columns: 1fr;
        }
        
        .note-card {
            padding: 16px;
        }
        
        .empty-state {
            padding: 60px 20px;
        }
    }
    </style>
    
    <script>
    (function() {
        const nonce = '<?php echo $nonce; ?>';
        const modal = document.getElementById('note-modal');
        const noteForm = document.getElementById('note-form');
        const searchInput = document.getElementById('search-notes');
        const categoryFilter = document.getElementById('filter-category');
        const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
        
        // New note buttons
        document.getElementById('new-note-btn').addEventListener('click', openNewNoteModal);
        const emptyBtn = document.getElementById('empty-new-note-btn');
        if (emptyBtn) {
            emptyBtn.addEventListener('click', openNewNoteModal);
        }
        
        function openNewNoteModal() {
            noteForm.reset();
            document.getElementById('note-id').value = '';
            document.getElementById('modal-title').textContent = 'New Note';
            modal.style.display = 'flex';
        }
        
        // Close modal
        document.querySelector('.bntm-modal-close').addEventListener('click', function() {
            modal.style.display = 'none';
        });
        
        // Close on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
        
        // Save note
        noteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const noteId = document.getElementById('note-id').value;
            const action = noteId ? 'notepad_update_note' : 'notepad_create_note';
            
            const formData = new FormData();
            formData.append('action', action);
            if (noteId) formData.append('note_id', noteId);
            formData.append('title', document.getElementById('note-title').value);
            formData.append('content', document.getElementById('note-content').value);
            formData.append('category', document.getElementById('note-category').value || 'General');
            formData.append('color', document.querySelector('input[name="note-color"]:checked').value);
            formData.append('nonce', nonce);
            
            const btn = this.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>Saving...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    modal.style.display = 'none';
                    location.reload();
                } else {
                    alert(json.data.message || 'Failed to save note');
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            })
            .catch(err => {
                alert('Error saving note');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            });
        });
        
        // Search notes
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterNotes();
            }, 300);
        });
        
        // Filter by category
        categoryFilter.addEventListener('change', function() {
            filterNotes();
        });
        
        function filterNotes() {
            const searchTerm = searchInput.value.toLowerCase();
            const category = categoryFilter.value;
            const cards = document.querySelectorAll('.note-card');
            
            let visibleCount = 0;
            cards.forEach(card => {
                const title = card.dataset.title.toLowerCase();
                const content = card.dataset.content.toLowerCase();
                const noteCategory = card.dataset.category;
                
                const matchesSearch = !searchTerm || title.includes(searchTerm) || content.includes(searchTerm);
                const matchesCategory = !category || noteCategory === category;
                
                const isVisible = matchesSearch && matchesCategory;
                card.style.display = isVisible ? 'block' : 'none';
                if (isVisible) visibleCount++;
            });
            
            // Show/hide empty search result message
            if (visibleCount === 0 && cards.length > 0) {
                const emptyMsg = document.getElementById('no-results-message');
                if (!emptyMsg) {
                    const msg = document.createElement('div');
                    msg.id = 'no-results-message';
                    msg.className = 'empty-state';
                    msg.innerHTML = '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 16px;"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg><h3>No results found</h3><p>Try adjusting your search or filters</p>';
                    document.getElementById('notes-grid').appendChild(msg);
                }
            } else {
                const emptyMsg = document.getElementById('no-results-message');
                if (emptyMsg) emptyMsg.remove();
            }
        }
        
        // Bulk delete
        document.addEventListener('change', function(e) {
            if (e.target.matches('.note-checkbox input')) {
                const checkedBoxes = document.querySelectorAll('.note-checkbox input:checked');
                bulkDeleteBtn.style.display = checkedBoxes.length > 0 ? 'flex' : 'none';
            }
        });
        
        bulkDeleteBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.note-checkbox input:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.dataset.id);
            
            if (!confirm(`Delete ${ids.length} note(s)? This action cannot be undone.`)) return;
            
            const formData = new FormData();
            formData.append('action', 'notepad_bulk_delete');
            formData.append('note_ids', JSON.stringify(ids));
            formData.append('nonce', nonce);
            
            const originalHTML = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>Deleting...';
            
            fetch(ajaxurl, {method: 'POST', body: formData})
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    location.reload();
                } else {
                    alert(json.data.message || 'Failed to delete notes');
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                }
            });
        });
    })();
    
    // Individual note actions
    function editNote(noteId) {
        event.stopPropagation();
        
        const formData = new FormData();
        formData.append('action', 'notepad_get_note');
        formData.append('note_id', noteId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const note = json.data.note;
                document.getElementById('note-id').value = note.id;
                document.getElementById('note-title').value = note.title;
                document.getElementById('note-content').value = note.content;
                document.getElementById('note-category').value = note.category;
                document.querySelector(`input[name="note-color"][value="${note.color}"]`).checked = true;
                document.getElementById('modal-title').textContent = 'Edit Note';
                document.getElementById('note-modal').style.display = 'flex';
            }
        });
    }
    
    function deleteNote(noteId) {
        event.stopPropagation();
        
        if (!confirm('Delete this note? This action cannot be undone.')) return;
        
        const formData = new FormData();
        formData.append('action', 'notepad_delete_note');
        formData.append('note_id', noteId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            } else {
                alert(json.data.message || 'Failed to delete note');
            }
        });
    }
    
    function togglePin(noteId) {
        event.stopPropagation();
        
        const formData = new FormData();
        formData.append('action', 'notepad_toggle_pin');
        formData.append('note_id', noteId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            }
        });
    }
    
    function toggleArchive(noteId) {
        event.stopPropagation();
        
        const formData = new FormData();
        formData.append('action', 'notepad_toggle_archive');
        formData.append('note_id', noteId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                location.reload();
            }
        });
    }
    
    function exportPDF(noteId) {
        event.stopPropagation();
        
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>';
        
        const formData = new FormData();
        formData.append('action', 'notepad_export_pdf');
        formData.append('note_id', noteId);
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        fetch(ajaxurl, {method: 'POST', body: formData})
        .then(r => r.json())
        .then(json => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            
            if (json.success) {
                window.open(json.data.url, '_blank');
            } else {
                alert(json.data.message || 'Failed to export PDF');
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            alert('Error exporting PDF');
        });
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Pinned Notes Tab
 */
function notepad_pinned_notes_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    $notes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} 
         WHERE business_id = %d AND is_pinned = 1 AND is_archived = 0 
         ORDER BY updated_at DESC",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="notepad-controls-section">
        <div class="notepad-section-header">
            <div class="section-header-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 17v5"></path>
                    <path d="M9 12.5 12 14l3-1.5"></path>
                    <path d="M12 3v4"></path>
                    <path d="m8 11 4-4 4 4"></path>
                </svg>
            </div>
            <div>
                <h3 style="margin: 0; color: #111827; font-size: 18px; font-weight: 600;">Pinned Notes</h3>
                <p style="margin: 4px 0 0; color: #6b7280; font-size: 14px;">Your important notes at a glance</p>
            </div>
        </div>
    </div>
    
    <div class="notes-grid">
        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <path d="M12 17v5"></path>
                        <path d="M9 12.5 12 14l3-1.5"></path>
                        <path d="M12 3v4"></path>
                        <path d="m8 11 4-4 4 4"></path>
                    </svg>
                </div>
                <h3>No pinned notes</h3>
                <p>Pin important notes to keep them at the top</p>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <?php echo notepad_render_note_card($note); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <style>
    .notepad-section-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 0;
    }
    
    .section-header-icon {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Archived Notes Tab
 */
function notepad_archived_notes_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    $notes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} 
         WHERE business_id = %d AND is_archived = 1 
         ORDER BY updated_at DESC",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="notepad-controls-section">
        <div class="notepad-section-header">
            <div class="section-header-icon archived-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <path d="M3 9h18"></path>
                    <path d="M9 15h6"></path>
                </svg>
            </div>
            <div>
                <h3 style="margin: 0; color: #111827; font-size: 18px; font-weight: 600;">Archived Notes</h3>
                <p style="margin: 4px 0 0; color: #6b7280; font-size: 14px;">Notes you've archived</p>
            </div>
        </div>
    </div>
    
    <div class="notes-grid">
        <?php if (empty($notes)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <path d="M3 9h18"></path>
                        <path d="M9 15h6"></path>
                    </svg>
                </div>
                <h3>No archived notes</h3>
                <p>Archive notes you don't need right now</p>
            </div>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
                <?php echo notepad_render_note_card($note); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <style>
    .archived-icon {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Render individual note card
 */
function notepad_render_note_card($note) {
    $content_preview = mb_substr($note->content, 0, 150);
    if (mb_strlen($note->content) > 150) {
        $content_preview .= '...';
    }
    
    $date = date('M d, Y', strtotime($note->updated_at));
    $pinned_class = $note->is_pinned ? 'pinned' : '';
    
    ob_start();
    ?>
    <div class="note-card <?php echo $pinned_class; ?>" 
         style="background-color: <?php echo esc_attr($note->color); ?>;"
         data-title="<?php echo esc_attr($note->title); ?>"
         data-content="<?php echo esc_attr($note->content); ?>"
         data-category="<?php echo esc_attr($note->category); ?>"
         onclick="editNote(<?php echo $note->id; ?>)">
        
        <div class="note-checkbox">
            <input type="checkbox" data-id="<?php echo $note->id; ?>" onclick="event.stopPropagation()">
        </div>
        
        <div class="note-card-header">
            <h4 class="note-title"><?php echo esc_html($note->title); ?></h4>
            <div class="note-actions">
                <button class="note-action-btn" onclick="togglePin(<?php echo $note->id; ?>)" title="<?php echo $note->is_pinned ? 'Unpin' : 'Pin'; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $note->is_pinned ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                        <path d="M12 17v5"></path>
                        <path d="M9 12.5 12 14l3-1.5"></path>
                        <path d="M12 3v4"></path>
                        <path d="m8 11 4-4 4 4"></path>
                    </svg>
                </button>
                
                <button class="note-action-btn" onclick="exportPDF(<?php echo $note->id; ?>)" title="Export PDF">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                </button>
                
                <button class="note-action-btn" onclick="toggleArchive(<?php echo $note->id; ?>)" title="<?php echo $note->is_archived ? 'Unarchive' : 'Archive'; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <path d="M3 9h18"></path>
                        <path d="M9 15h6"></path>
                    </svg>
                </button>
                
                <button class="note-action-btn note-delete-btn" onclick="deleteNote(<?php echo $note->id; ?>)" title="Delete">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="note-content-preview"><?php echo esc_html($content_preview); ?></div>
        
        <div class="note-footer">
            <span class="note-category"><?php echo esc_html($note->category); ?></span>
            <span class="note-date"><?php echo $date; ?></span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================================
// AJAX HANDLER FUNCTIONS
// ============================================================================

/**
 * Create new note
 */
function bntm_ajax_notepad_create_note() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    $title = sanitize_text_field($_POST['title']);
    $content = sanitize_textarea_field($_POST['content']);
    $category = sanitize_text_field($_POST['category']);
    $color = sanitize_hex_color($_POST['color']);
    
    if (empty($title) || empty($content)) {
        wp_send_json_error(['message' => 'Title and content are required']);
    }
    
    $result = $wpdb->insert($table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'title' => $title,
        'content' => $content,
        'category' => $category,
        'color' => $color
    ], [
        '%s', '%d', '%s', '%s', '%s', '%s'
    ]);
    
    if ($result) {
        wp_send_json_success(['message' => 'Note created successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to create note']);
    }
}

/**
 * Update existing note
 */
function bntm_ajax_notepad_update_note() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    $note_id = intval($_POST['note_id']);
    $title = sanitize_text_field($_POST['title']);
    $content = sanitize_textarea_field($_POST['content']);
    $category = sanitize_text_field($_POST['category']);
    $color = sanitize_hex_color($_POST['color']);
    
    if (empty($title) || empty($content)) {
        wp_send_json_error(['message' => 'Title and content are required']);
    }
    
    $result = $wpdb->update(
        $table,
        [
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'color' => $color
        ],
        [
            'id' => $note_id,
            'business_id' => get_current_user_id()
        ],
        ['%s', '%s', '%s', '%s'],
        ['%d', '%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => 'Note updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update note']);
    }
}

/**
 * Delete note
 */
function bntm_ajax_notepad_delete_note() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    $note_id = intval($_POST['note_id']);
    
    $result = $wpdb->delete(
        $table,
        [
            'id' => $note_id,
            'business_id' => get_current_user_id()
        ],
        ['%d', '%d']
    );
    
    if ($result) {
        wp_send_json_success(['message' => 'Note deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete note']);
    }
}

/**
 * Get single note
 */
function bntm_ajax_notepad_get_note() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    $note_id = intval($_POST['note_id']);
    
    $note = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND business_id = %d",
        $note_id,
        get_current_user_id()
    ));
    
    if ($note) {
        wp_send_json_success(['note' => $note]);
    } else {
        wp_send_json_error(['message' => 'Note not found']);
    }
}

/**
 * Toggle pin status
 */
function bntm_ajax_notepad_toggle_pin() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    $note_id = intval($_POST['note_id']);
    
    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT is_pinned FROM {$table} WHERE id = %d AND business_id = %d",
        $note_id,
        get_current_user_id()
    ));
    
    $result = $wpdb->update(
        $table,
        ['is_pinned' => !$current],
        [
            'id' => $note_id,
            'business_id' => get_current_user_id()
        ],
        ['%d'],
        ['%d', '%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => $current ? 'Note unpinned' : 'Note pinned']);
    } else {
        wp_send_json_error(['message' => 'Failed to update note']);
    }
}

/**
 * Toggle archive status
 */
function bntm_ajax_notepad_toggle_archive() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    $note_id = intval($_POST['note_id']);
    
    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT is_archived FROM {$table} WHERE id = %d AND business_id = %d",
        $note_id,
        get_current_user_id()
    ));
    
    $result = $wpdb->update(
        $table,
        ['is_archived' => !$current],
        [
            'id' => $note_id,
            'business_id' => get_current_user_id()
        ],
        ['%d'],
        ['%d', '%d']
    );
    
    if ($result !== false) {
        wp_send_json_success(['message' => $current ? 'Note unarchived' : 'Note archived']);
    } else {
        wp_send_json_error(['message' => 'Failed to update note']);
    }
}

/**
 * Bulk delete notes
 */
function bntm_ajax_notepad_bulk_delete() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    $note_ids = json_decode(stripslashes($_POST['note_ids']), true);
    
    if (empty($note_ids) || !is_array($note_ids)) {
        wp_send_json_error(['message' => 'No notes selected']);
    }
    
    $placeholders = implode(',', array_fill(0, count($note_ids), '%d'));
    $business_id = get_current_user_id();
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE id IN ($placeholders) AND business_id = %d",
        array_merge($note_ids, [$business_id])
    ));
    
    if ($deleted) {
        wp_send_json_success(['message' => "Successfully deleted {$deleted} note(s)!"]);
    } else {
        wp_send_json_error(['message' => 'Failed to delete notes']);
    }
}

/**
 * Export note to PDF
 */
function bntm_ajax_notepad_export_pdf() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    $note_id = intval($_POST['note_id']);
    
    $note = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE id = %d AND business_id = %d",
        $note_id,
        get_current_user_id()
    ));
    
    if (!$note) {
        wp_send_json_error(['message' => 'Note not found']);
    }
    
    // Create PDF directory
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/notepad-exports/';
    
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $filename = 'note-' . $note->rand_id . '-' . time() . '.pdf';
    $filepath = $pdf_dir . $filename;
    
    // Prepare note data
    $note_title = $note->title;
    $note_content = $note->content;
    $note_category = $note->category;
    $note_date = date('F d, Y', strtotime($note->updated_at));
    
    // Load PDF generator
    require_once(BNTM_NOTEPAD_PATH . 'pdf-generator.php');
    
    // Try pure PHP method first (no dependencies)
    $pdf_generated = generate_simple_pdf($note_title, $note_content, $note_category, $note_date, $filepath);
    
    if (!$pdf_generated) {
        // Try DOMPDF if available
        $pdf_generated = generate_pdf_native($note_title, $note_content, $note_category, $note_date, $filepath);
    }
    
    if (!$pdf_generated) {
        // Try Python as last resort
        $pdf_generated = generate_pdf_python($note_title, $note_content, $note_category, $note_date, $filepath);
    }
    
    if ($pdf_generated && file_exists($filepath)) {
        $pdf_url = $upload_dir['baseurl'] . '/notepad-exports/' . $filename;
        wp_send_json_success([
            'message' => 'PDF exported successfully!',
            'url' => $pdf_url
        ]);
    } else {
        error_log('PDF Generation Failed for note ID: ' . $note_id);
        wp_send_json_error([
            'message' => 'Failed to generate PDF. Please contact your administrator.'
        ]);
    }
}

/**
 * Generate PDF using native PHP (DOMPDF or mPDF)
 */
function generate_pdf_native($title, $content, $category, $date, $filepath) {
    try {
        // Check if DOMPDF is available
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            
            $html = sprintf(
                '<html><head><meta charset="UTF-8"><style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    h1 { color: #667eea; font-size: 28px; margin-bottom: 10px; }
                    .meta { color: #6b7280; font-size: 12px; margin-bottom: 20px; }
                    .content { white-space: pre-wrap; word-wrap: break-word; line-height: 1.6; }
                    .footer { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; color: #999; font-size: 10px; text-align: center; }
                </style></head><body>
                <h1>%s</h1>
                <div class="meta"><strong>Category:</strong> %s | <strong>Date:</strong> %s</div>
                <div class="content">%s</div>
                <div class="footer">Generated from BNTM Notepad</div>
                </body></html>',
                htmlspecialchars($title),
                htmlspecialchars($category),
                htmlspecialchars($date),
                htmlspecialchars($content)
            );
            
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            file_put_contents($filepath, $dompdf->output());
            
            return file_exists($filepath);
        }
        
        // Check if mPDF is available
        if (class_exists('Mpdf\Mpdf')) {
            $mpdf = new \Mpdf\Mpdf();
            
            $html = sprintf(
                '<h1 style="color: #667eea;">%s</h1>
                <p style="color: #6b7280;"><strong>Category:</strong> %s | <strong>Date:</strong> %s</p>
                <hr>
                <div style="white-space: pre-wrap; word-wrap: break-word; line-height: 1.6;">%s</div>
                <hr style="margin-top: 30px;">
                <p style="color: #999; font-size: 10px; text-align: center;">Generated from BNTM Notepad</p>',
                htmlspecialchars($title),
                htmlspecialchars($category),
                htmlspecialchars($date),
                htmlspecialchars($content)
            );
            
            $mpdf->WriteHTML($html);
            $mpdf->Output($filepath, 'F');
            
            return file_exists($filepath);
        }
        
        return false;
    } catch (Exception $e) {
        error_log('PHP PDF Generation Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generate PDF using Python
 */
function generate_pdf_python($title, $content, $category, $date, $filepath) {
    $python_script = BNTM_NOTEPAD_PATH . 'create_note_pdf.py';
    
    if (!file_exists($python_script)) {
        error_log('Python PDF script not found: ' . $python_script);
        return false;
    }
    
    // Prepare temporary data file
    $temp_dir = dirname($filepath);
    $data_file = $temp_dir . '/data-' . time() . '-' . uniqid() . '.json';
    
    $note_data = [
        'title' => $title,
        'content' => $content,
        'category' => $category,
        'date' => $date,
        'filepath' => $filepath
    ];
    
    if (!file_put_contents($data_file, json_encode($note_data))) {
        error_log('Failed to write data file: ' . $data_file);
        return false;
    }
    
    // Find Python executable
    $python_cmd = find_python_executable();
    
    if (!$python_cmd) {
        error_log('Python executable not found');
        @unlink($data_file);
        return false;
    }
    
    // Execute Python script
    $command = sprintf(
        '%s %s %s',
        escapeshellarg($python_cmd),
        escapeshellarg($python_script),
        escapeshellarg($data_file)
    );
    
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    
    // Clean up
    @unlink($data_file);
    
    $output_text = implode("\n", $output);
    
    if (strpos($output_text, 'SUCCESS') !== false && file_exists($filepath)) {
        return true;
    }
    
    error_log('Python PDF Generation Error: ' . $output_text);
    return false;
}

/**
 * Find Python executable in system
 */
function find_python_executable() {
    $is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    
    // List of possible Python paths
    $possible_paths = $is_windows ? [
        'python.exe',
        'python3.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python310\\python.exe',
        'C:\\Python39\\python.exe',
        'C:\\Program Files\\Python311\\python.exe',
        'C:\\Program Files\\Python310\\python.exe',
        'C:\\Program Files (x86)\\Python311\\python.exe',
    ] : [
        '/usr/bin/python3',
        '/usr/bin/python',
        '/usr/local/bin/python3',
        'python3',
        'python',
    ];
    
    // Check each path
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    // Try using 'where' or 'which' commands
    if (function_exists('shell_exec')) {
        if ($is_windows) {
            $result = @shell_exec('where python 2>&1');
        } else {
            $result = @shell_exec('which python3 2>&1');
        }
        
        if ($result && strpos($result, 'python') !== false) {
            return trim(explode("\n", $result)[0]);
        }
    }
    
    return false;
}

/**
 * Search notes
 */
function bntm_ajax_notepad_search_notes() {
    check_ajax_referer('notepad_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    $search = sanitize_text_field($_POST['search']);
    
    $notes = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} 
         WHERE business_id = %d 
         AND (title LIKE %s OR content LIKE %s)
         AND is_archived = 0
         ORDER BY is_pinned DESC, updated_at DESC",
        get_current_user_id(),
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
    ));
    
    wp_send_json_success(['notes' => $notes]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get note statistics
 */
function notepad_get_stats($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND is_archived = 0",
        $business_id
    ));
    
    $pinned = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND is_pinned = 1 AND is_archived = 0",
        $business_id
    ));
    
    $archived = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE business_id = %d AND is_archived = 1",
        $business_id
    ));
    
    return [
        'total' => $total,
        'pinned' => $pinned,
        'archived' => $archived
    ];
}

/**
 * Get categories with note counts
 */
function notepad_get_categories($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'notepad_notes';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT category, COUNT(*) as count 
         FROM {$table} 
         WHERE business_id = %d AND is_archived = 0 
         GROUP BY category 
         ORDER BY count DESC",
        $business_id
    ));
}