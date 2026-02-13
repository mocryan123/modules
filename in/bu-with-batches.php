<?php
/**
 * Module Name: Inventory Management
 * Module Slug: in
 * Description: Complete inventory management solution with products, batches, and cost tracking
 * Version: 1.0.0
 * Author: Your Name
 * Icon: 📦
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Module constants
define('BNTM_IN_PATH', dirname(__FILE__) . '/');
define('BNTM_IN_URL', plugin_dir_url(__FILE__));

/* ---------- MODULE CONFIGURATION ---------- */

/**
 * Get module pages
 * Returns array of page_title => shortcode
 */
function bntm_in_get_pages() {
    return [
        'Inventory' => '[in_dashboard]'
    ];
}

/**
 * Get module database tables
 * Returns array of table_name => CREATE TABLE SQL
 */
function bntm_in_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;
    
    return [
        'in_products' => "CREATE TABLE {$prefix}in_products (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(255) NOT NULL,
            sku VARCHAR(50),
            selling_price DECIMAL(10,2) NOT NULL,
            stock_quantity INT DEFAULT 0,
            reorder_level INT DEFAULT 10,
            materials TEXT,
            description TEXT,
            image VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_sku (sku),
            INDEX idx_stock (stock_quantity)
        ) {$charset};",
        
        'in_batches' => "CREATE TABLE {$prefix}in_batches (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
          rand_id VARCHAR(20) UNIQUE NOT NULL,
          business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
          product_id BIGINT UNSIGNED NOT NULL,
          batch_code VARCHAR(100) NOT NULL,
          quantity INT NOT NULL,
          total_cost DECIMAL(10,2) NOT NULL,
          cost_breakdown TEXT,
          batch_costs TEXT,
          manufacture_date DATE,
          notes TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_business (business_id),
          INDEX idx_product (product_id),
          INDEX idx_batch_code (batch_code),
          INDEX idx_date (manufacture_date)
      ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 * Returns array of shortcode => callback_function
 */
function bntm_in_get_shortcodes() {
    return [
        'in_dashboard' => 'bntm_in_shortcode_dashboard'
    ];
}

/**
 * Create module tables
 * Called when "Generate Tables" is clicked in admin
 */
function bntm_in_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $tables = bntm_in_get_tables();
    
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    
    return count($tables);
}

// AJAX handlers
add_action('wp_ajax_in_add_product', 'bntm_ajax_in_add_product');
add_action('wp_ajax_in_update_product', 'bntm_ajax_in_update_product');
add_action('wp_ajax_in_delete_product', 'bntm_ajax_in_delete_product');
add_action('wp_ajax_in_add_batch', 'bntm_ajax_in_add_batch');
add_action('wp_ajax_in_delete_batch', 'bntm_ajax_in_delete_batch');
add_action('wp_ajax_in_import_batch_expense', 'bntm_ajax_in_import_batch_expense');
add_action('wp_ajax_in_revert_batch_expense', 'bntm_ajax_in_revert_batch_expense');

/* ---------- MAIN INVENTORY SHORTCODE ---------- */
function bntm_in_shortcode_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Inventory dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id = $current_user->ID;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-inventory-container">
        <div class="bntm-tabs">
            <a href="?tab=overview" class="bntm-tab <?php echo $active_tab === 'overview' ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=products" class="bntm-tab <?php echo $active_tab === 'products' ? 'active' : ''; ?>">Products</a>
            <a href="?tab=batches" class="bntm-tab <?php echo $active_tab === 'batches' ? 'active' : ''; ?>">Batches</a>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import" class="bntm-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">Import to Finance</a>
            <?php endif; ?>
            <a href="?tab=settings" class="bntm-tab <?php echo $active_tab === 'settings' ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php if ($active_tab === 'overview'): ?>
                <?php echo in_overview_tab($business_id); ?>
            <?php elseif ($active_tab === 'products'): ?>
                <?php echo in_products_tab($business_id); ?>
            <?php elseif ($active_tab === 'batches'): ?>
                <?php echo in_batches_tab($business_id); ?>
            <?php elseif ($active_tab === 'import'): ?>
                <?php echo in_import_tab($business_id); ?>
            <?php elseif ($active_tab === 'settings'): ?>
                <?php echo in_settings_tab($business_id); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Inventory Management', $content);
}

/* ---------- TAB FUNCTIONS ---------- */
function in_overview_tab($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    
    // Get statistics
    $total_products = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $products_table ",
        $business_id
    ));
    
    $total_stock = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(stock_quantity) FROM $products_table ",
        $business_id
    ));
    
    $total_batches = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $batches_table ",
        $business_id
    ));
    
    $low_stock_items = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $products_table WHERE stock_quantity <= reorder_level",
        $business_id
    ));
    
    $total_inventory_value = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(stock_quantity * selling_price) FROM $products_table ",
        $business_id
    ));
    
    $total_cost_value = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(total_cost) FROM $batches_table ",
        $business_id
    ));
    
    // Recent batches
    $recent_batches = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, p.name as product_name 
        FROM $batches_table b
        LEFT JOIN $products_table p ON b.product_id = p.id
        ORDER BY b.created_at DESC
        LIMIT 5",
        $business_id
    ));
    
    ob_start();
    ?>
    <div class="bntm-dashboard-stats">
        <div class="bntm-stat-card">
            <h3>Total Products</h3>
            <p class="bntm-stat-number"><?php echo esc_html($total_products); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Stock</h3>
            <p class="bntm-stat-number"><?php echo esc_html($total_stock ?: 0); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Batches</h3>
            <p class="bntm-stat-number"><?php echo esc_html($total_batches); ?></p>
        </div>
        <div class="bntm-stat-card">
            <h3>Low Stock Items</h3>
            <p class="bntm-stat-number" style="color: <?php echo $low_stock_items > 0 ? '#dc2626' : '#059669'; ?>">
                <?php echo esc_html($low_stock_items); ?>
            </p>
        </div>
        <div class="bntm-stat-card">
            <h3>Inventory Value</h3>
            <p class="bntm-stat-number">₱<?php echo number_format($total_inventory_value ?: 0, 2); ?></p>
            <small style="color: #6b7280;">Selling Price</small>
        </div>
        <div class="bntm-stat-card">
            <h3>Total Cost Invested</h3>
            <p class="bntm-stat-number">₱<?php echo number_format($total_cost_value ?: 0, 2); ?></p>
            <small style="color: #6b7280;">Production Cost</small>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>Recent Batches</h3>
        <?php if (empty($recent_batches)): ?>
            <p>No batches recorded yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Batch Code</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Cost</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_batches as $batch): ?>
                        <tr>
                            <td><?php echo esc_html($batch->batch_code); ?></td>
                            <td><?php echo esc_html($batch->product_name); ?></td>
                            <td><?php echo esc_html($batch->quantity); ?></td>
                            <td>₱<?php echo number_format($batch->total_cost, 2); ?></td>
                            <td><?php echo date('M d, Y', strtotime($batch->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <style>
    .bntm-dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .bntm-stat-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
    }
    .bntm-stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }
    .bntm-stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0;
    }
    </style>
    <?php
    return ob_get_clean();
}


function in_products_tab($business_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table  ORDER BY id DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('in_nonce');
    $upload_nonce = wp_create_nonce('in_upload_image');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <button id="open-add-product-modal" class="bntm-btn-primary">+ Add New Product</button>
    </div>

    <!-- Add Product Modal -->
    <div id="add-product-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Add New Product</h3>
            <form id="in-add-product-form" class="bntm-form">
                <!-- Product Image Upload -->
                <div class="bntm-form-group">
                    <label>Product Image</label>
                    
                    <div class="in-product-image-preview" id="product-image-preview" style="display: none;">
                        <img src="" alt="Product Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-product-image">✕</button>
                    </div>
                    
                    <div class="bntm-upload-area" id="product-upload-area">
                        <input type="file" id="product-image-upload" accept="image/*" style="display: none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="product-upload-btn">
                            Choose Image
                        </button>
                        <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    
                    <input type="hidden" id="product_image" name="product_image" value="">
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>SKU</label>
                        <input type="text" name="sku">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Selling Price *</label>
                        <input type="number" name="selling_price" step="0.01" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Reorder Level *</label>
                        <input type="number" name="reorder_level" value="10" required>
                        <small>Alert when stock falls below this</small>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Product description..."></textarea>
                </div>

                <div class="bntm-form-section" style="background: #f9fafb; padding: 15px; border-radius: 6px;">
                    <h4 style="margin-top: 0;">Production Cost Materials</h4>
                    <p style="color: #6b7280; font-size: 13px;">These materials will be pre-filled when creating batches (editable)</p>
                    <div id="materials-container">
                        <div class="material-row">
                            <input type="text" name="material_name[]" placeholder="Material/Component" style="flex: 2;">
                            <input type="number" name="material_cost[]" placeholder="Cost" step="0.01" style="flex: 1;">
                            <button type="button" class="bntm-btn-small bntm-btn-danger remove-material">✕</button>
                        </div>
                    </div>
                    <button type="button" id="add-material-btn" class="bntm-btn-secondary" style="margin-top: 10px;">+ Add Material</button>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Add Product</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="edit-product-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Edit Product</h3>
            <form id="in-edit-product-form" class="bntm-form">
                <input type="hidden" id="edit-product-id" name="product_id">
                <input type="hidden" id="edit-current-image" name="current_image" value="">
                
                <!-- Product Image Upload -->
                <div class="bntm-form-group">
                    <label>Product Image</label>
                    
                    <div class="in-product-image-preview" id="edit-product-image-preview" style="display: none;">
                        <img src="" alt="Product Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-edit-product-image">✕</button>
                    </div>
                    
                    <div class="bntm-upload-area" id="edit-product-upload-area">
                        <input type="file" id="edit-product-image-upload" accept="image/*" style="display: none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="edit-product-upload-btn">
                            Choose Image
                        </button>
                        <p style="margin: 10px 0; color: #6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    
                    <input type="hidden" id="edit_product_image" name="product_image" value="">
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Product Name *</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>SKU</label>
                        <input type="text" id="edit-sku" name="sku">
                    </div>
                </div>
                
                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Selling Price *</label>
                        <input type="number" id="edit-selling-price" name="selling_price" step="0.01" required>
                    </div>
                    <div class="bntm-form-group">
                        <label>Reorder Level *</label>
                        <input type="number" id="edit-reorder-level" name="reorder_level" required>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-description" name="description" rows="3"></textarea>
                </div>

                <div class="bntm-form-section" style="background: #f9fafb; padding: 15px; border-radius: 6px;">
                    <h4 style="margin-top: 0;">Production Cost Materials</h4>
                    <div id="edit-materials-container">
                        <!-- Will be populated dynamically -->
                    </div>
                    <button type="button" id="edit-add-material-btn" class="bntm-btn-secondary" style="margin-top: 10px;">+ Add Material</button>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="bntm-btn-primary">Update Product</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bntm-form-section">
        <h3>All Products (<?php echo count($products); ?>)</h3>
        <?php if (empty($products)): ?>
            <p>No products found. Add your first product above.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr data-product='<?php echo esc_attr(json_encode([
                            'id' => $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'selling_price' => $product->selling_price,
                            'reorder_level' => $product->reorder_level,
                            'description' => $product->description,
                            'image' => $product->image,
                            'materials' => $product->materials
                        ])); ?>'>
                            <td>
                                <?php if ($product->image): ?>
                                    <img src="<?php echo esc_url($product->image); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: #e5e7eb; border-radius: 4px;"></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($product->name); ?></td>
                            <td><?php echo esc_html($product->sku); ?></td>
                            <td>₱<?php echo number_format($product->selling_price, 2); ?></td>
                            <td><?php echo esc_html($product->stock_quantity); ?></td>
                            <td><?php echo esc_html($product->reorder_level); ?></td>
                            <td>
                                <?php if ($product->stock_quantity == 0): ?>
                                    <span style="color: #991b1b; font-weight: 500;">Out of Stock</span>
                                <?php elseif ($product->stock_quantity <= $product->reorder_level): ?>
                                    <span style="color: #dc2626; font-weight: 500;">Low Stock</span>
                                <?php else: ?>
                                    <span style="color: #059669; font-weight: 500;">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="bntm-btn-small in-edit-product">Edit</button>
                                <button class="bntm-btn-small bntm-btn-danger in-delete-product" data-id="<?php echo esc_attr($product->id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <style>
    .in-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .in-modal-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 700px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .material-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
    .material-row input {
        padding: 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
    }
    .bntm-upload-area {
        border: 2px dashed #d1d5db;
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s;
        background: #f9fafb;
    }
    .bntm-upload-area.dragover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .in-product-image-preview {
        position: relative;
        display: inline-block;
        margin-bottom: 15px;
        padding: 10px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        background: #f9fafb;
    }
    .in-product-image-preview img {
        max-width: 200px;
        max-height: 200px;
        display: block;
    }
    .bntm-btn-remove-logo {
        position: absolute;
        top: -10px;
        right: -10px;
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .bntm-btn-remove-logo:hover {
        background: #dc2626;
    }
    </style>

    <script>
    (function() {
        var uploadNonce = '<?php echo $upload_nonce; ?>';
        
        // ========== IMAGE UPLOAD SETUP ==========
        function setupImageUpload(prefix) {
            const uploadArea = document.getElementById(prefix + 'product-upload-area');
            const uploadBtn = document.getElementById(prefix + 'product-upload-btn');
            const fileInput = document.getElementById(prefix + 'product-image-upload');
            const imagePreview = document.getElementById(prefix + 'product-image-preview');
            const removeBtn = document.getElementById('remove-' + prefix + 'product-image');
            const hiddenInput = document.getElementById(prefix + 'product_image');
            
            if (!uploadBtn) return;
            
            uploadBtn.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    uploadProductImage(this.files[0], prefix);
                }
            });
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    uploadProductImage(e.dataTransfer.files[0], prefix);
                }
            });
            
            removeBtn.addEventListener('click', function() {
                imagePreview.style.display = 'none';
                uploadArea.style.display = 'block';
                hiddenInput.value = '';
            });
        }
        
        function uploadProductImage(file, prefix) {
            if (!file.type.match('image.*')) {
                alert('Please select an image file');
                return;
            }
            
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'in_upload_product_image');
            formData.append('image', file);
            formData.append('_ajax_nonce', uploadNonce);
            
            const uploadBtn = document.getElementById(prefix + 'product-upload-btn');
            const uploadArea = document.getElementById(prefix + 'product-upload-area');
            const imagePreview = document.getElementById(prefix + 'product-image-preview');
            const hiddenInput = document.getElementById(prefix + 'product_image');
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Choose Image';
                
                if (json.success) {
                    imagePreview.querySelector('img').src = json.data.url;
                    imagePreview.style.display = 'inline-block';
                    uploadArea.style.display = 'none';
                    hiddenInput.value = json.data.url;
                } else {
                    alert('Upload failed: ' + json.data);
                }
            })
            .catch(err => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Choose Image';
                alert('Upload error: ' + err.message);
            });
        }
        
        setupImageUpload('');
        setupImageUpload('edit-');
        
        // ========== MODAL CONTROLS ==========
        const addModal = document.getElementById('add-product-modal');
        const editModal = document.getElementById('edit-product-modal');
        
        document.getElementById('open-add-product-modal').addEventListener('click', () => {
            addModal.style.display = 'flex';
        });
        
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.in-modal').style.display = 'none';
            });
        });
        
        // ========== ADD MATERIAL BUTTONS ==========
        document.getElementById('add-material-btn').addEventListener('click', function() {
            const container = document.getElementById('materials-container');
            const row = document.createElement('div');
            row.className = 'material-row';
            row.innerHTML = `
                <input type="text" name="material_name[]" placeholder="Material/Component" style="flex: 2;">
                <input type="number" name="material_cost[]" placeholder="Cost" step="0.01" style="flex: 1;">
                <button type="button" class="bntm-btn-small bntm-btn-danger remove-material">✕</button>
            `;
            container.appendChild(row);
        });
        
        document.getElementById('edit-add-material-btn').addEventListener('click', function() {
            const container = document.getElementById('edit-materials-container');
            const row = document.createElement('div');
            row.className = 'material-row';
            row.innerHTML = `
                <input type="text" name="material_name[]" placeholder="Material/Component" style="flex: 2;">
                <input type="number" name="material_cost[]" placeholder="Cost" step="0.01" style="flex: 1;">
                <button type="button" class="bntm-btn-small bntm-btn-danger remove-material">✕</button>
            `;
            container.appendChild(row);
        });
        
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-material')) {
                const container = e.target.closest('[id$="-materials-container"], #materials-container');
                const rows = container.querySelectorAll('.material-row');
                if (rows.length > 1) {
                    e.target.closest('.material-row').remove();
                } else {
                    alert('At least one material is required');
                }
            }
        });
        
        // ========== ADD PRODUCT FORM ==========
        document.getElementById('in-add-product-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'in_add_product');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    alert(json.data.message);
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Add Product';
                }
            });
        });
        
        // ========== EDIT PRODUCT ==========
        document.querySelectorAll('.in-edit-product').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('tr');
                const product = JSON.parse(row.getAttribute('data-product'));
                
                document.getElementById('edit-product-id').value = product.id;
                document.getElementById('edit-name').value = product.name;
                document.getElementById('edit-sku').value = product.sku;
                document.getElementById('edit-selling-price').value = product.selling_price;
                document.getElementById('edit-reorder-level').value = product.reorder_level;
                document.getElementById('edit-description').value = product.description || '';
                
                // Load image
                const editImagePreview = document.getElementById('edit-product-image-preview');
                const editUploadArea = document.getElementById('edit-product-upload-area');
                const editImageInput = document.getElementById('edit_product_image');
                
                if (product.image) {
                    editImagePreview.querySelector('img').src = product.image;
                    editImagePreview.style.display = 'inline-block';
                    editUploadArea.style.display = 'none';
                    editImageInput.value = product.image;
                    document.getElementById('edit-current-image').value = product.image;
                } else {
                    editImagePreview.style.display = 'none';
                    editUploadArea.style.display = 'block';
                    editImageInput.value = '';
                }
                
                // Load materials
                const materialsContainer = document.getElementById('edit-materials-container');
                materialsContainer.innerHTML = '';
                
                let materials = [];
                try {
                    materials = JSON.parse(product.materials || '[]');
                } catch(e) {
                    materials = [];
                }
                
                if (materials.length === 0) {
                    materials = [{name: '', cost: ''}];
                }
                
                materials.forEach(mat => {
                    const row = document.createElement('div');
                    row.className = 'material-row';
                    row.innerHTML = `
                        <input type="text" name="material_name[]" value="${mat.name || ''}" placeholder="Material/Component" style="flex: 2;">
                        <input type="number" name="material_cost[]" value="${mat.cost || ''}" placeholder="Cost" step="0.01" style="flex: 1;">
                        <button type="button" class="bntm-btn-small bntm-btn-danger remove-material">✕</button>
                    `;
                    materialsContainer.appendChild(row);
                });
                
                editModal.style.display = 'flex';
            });
        });
        
        document.getElementById('in-edit-product-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'in_update_product');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Updating...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    location.reload();
                } else {
                    alert(json.data.message);
                    btn.disabled = false;
                    btn.textContent = 'Update Product';
                }
            });
        });
        
        // ========== DELETE PRODUCT ==========
        document.querySelectorAll('.in-delete-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this product?')) return;
                
                const productId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'in_delete_product');
                formData.append('product_id', productId);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
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
/* ========================================
   FUNCTION: in_batches_tab() - COMPLETE
   ======================================== */
function in_batches_tab($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'in_products';
    $batches_table = $wpdb->prefix . 'in_batches';
    
    $products = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $products_table ORDER BY name ASC",
        $business_id
    ));
    
    $batches = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, p.name as product_name 
        FROM $batches_table b
        LEFT JOIN $products_table p ON b.product_id = p.id
        ORDER BY b.created_at DESC",
        $business_id
    ));
    
    $nonce = wp_create_nonce('in_nonce');

    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Add New Batch</h3>
        <form id="in-add-batch-form" class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Product *</label>
                    <select name="product_id" id="batch-product-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product->id; ?>" 
                                    data-materials='<?php echo esc_attr($product->materials); ?>'>
                                <?php echo esc_html($product->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Batch Code *</label>
                    <input type="text" name="batch_code" placeholder="e.g., BATCH-001" required>
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" id="batch-quantity" min="1" required>
                </div>
                <div class="bntm-form-group">
                    <label>Manufacturing Date</label>
                    <input type="date" name="manufacture_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="bntm-form-section" style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <h4 style="margin-top: 0;">Per Unit Costs</h4>
                <p style="color: #6b7280; font-size: 13px; margin-bottom: 10px;">
                    <i class="dashicons dashicons-info"></i> Materials will auto-load when you select a product. These costs are per unit and will be multiplied by quantity.
                </p>
                <div id="cost-items-container">
                    <div class="cost-item-row">
                        <input type="text" name="cost_item_name[]" placeholder="Item/Material/Labor" style="flex: 2;" required>
                        <input type="number" name="cost_item_amount[]" placeholder="Amount per unit" step="0.01" style="flex: 1;" required>
                        <button type="button" class="bntm-btn-small bntm-btn-danger remove-cost-item">✕</button>
                    </div>
                </div>
                <button type="button" id="add-cost-item-btn" class="bntm-btn-secondary" style="margin-top: 10px;">+ Add Per Unit Cost</button>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #d1d5db;">
                    <strong>Subtotal (Per Unit): ₱<span id="per-unit-cost-display">0.00</span></strong><br>
                    <strong>Subtotal (Total): ₱<span id="per-unit-total-display">0.00</span></strong>
                </div>
            </div>

            <div class="bntm-form-section" style="background: #fef3c7; padding: 15px; border-radius: 6px;">
                <h4 style="margin-top: 0;">Whole Batch Costs</h4>
                <p style="color: #92400e; font-size: 13px; margin-bottom: 10px;">
                    <i class="dashicons dashicons-info"></i> One-time costs for the entire batch (e.g., setup fees, equipment rental, utilities).
                </p>
                <div id="batch-cost-items-container">
                    <div class="batch-cost-item-row">
                        <input type="text" name="batch_cost_item_name[]" placeholder="e.g., Setup Fee, Utilities" style="flex: 2;">
                        <input type="number" name="batch_cost_item_amount[]" placeholder="Total amount" step="0.01" style="flex: 1;">
                        <button type="button" class="bntm-btn-small bntm-btn-danger remove-batch-cost-item">✕</button>
                    </div>
                </div>
                <button type="button" id="add-batch-cost-item-btn" class="bntm-btn-secondary" style="margin-top: 10px;">+ Add Batch Cost</button>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #d1d5db;">
                    <strong>Batch Costs Total: ₱<span id="batch-cost-display">0.00</span></strong>
                </div>
            </div>

            <div style="background: #e0f2fe; padding: 15px; border-radius: 6px; margin-top: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="margin: 0; color: #0c4a6e;">Grand Total Cost: ₱<span id="grand-total-cost-display">0.00</span></h3>
                        <p style="margin: 5px 0 0 0; color: #075985; font-size: 13px;">Cost Per Unit: ₱<span id="final-cost-per-unit-display">0.00</span></p>
                    </div>
                </div>
            </div>

            <div class="bntm-form-group" style="margin-top: 15px;">
                <label>Notes</label>
                <textarea name="notes" rows="3" placeholder="Additional information about this batch"></textarea>
            </div>

            <button type="submit" class="bntm-btn-primary">Add Batch</button>
        </form>
        <div id="batch-message"></div>
    </div>

    <div class="bntm-form-section">
        <h3>All Batches (<?php echo count($batches); ?>)</h3>
        <?php if (empty($batches)): ?>
            <p>No batches recorded yet.</p>
        <?php else: ?>
            <table class="bntm-table">
                <thead>
                    <tr>
                        <th>Batch Code</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Cost</th>
                        <th>Cost Per Unit</th>
                        <th>Mfg. Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch): 
                        $cost_per_unit = $batch->quantity > 0 ? $batch->total_cost / $batch->quantity : 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html($batch->batch_code); ?></td>
                            <td><?php echo esc_html($batch->product_name); ?></td>
                            <td><?php echo esc_html($batch->quantity); ?></td>
                            <td>₱<?php echo number_format($batch->total_cost, 2); ?></td>
                            <td>₱<?php echo number_format($cost_per_unit, 2); ?></td>
                            <td><?php echo $batch->manufacture_date ? date('M d, Y', strtotime($batch->manufacture_date)) : 'N/A'; ?></td>
                            <td>
                                <button class="bntm-btn-small view-batch-details" data-details='<?php echo esc_attr(json_encode([
                                    'batch_code' => $batch->batch_code,
                                    'product' => $batch->product_name,
                                    'quantity' => $batch->quantity,
                                    'total_cost' => $batch->total_cost,
                                    'cost_breakdown' => $batch->cost_breakdown,
                                    'batch_costs' => $batch->batch_costs,
                                    'notes' => $batch->notes,
                                    'manufacture_date' => $batch->manufacture_date,
                                    'created_at' => $batch->created_at
                                ])); ?>'>View</button>
                                <button class="bntm-btn-small bntm-btn-danger in-delete-batch" data-id="<?php echo $batch->id; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Batch Details Modal -->
    <div id="batch-details-modal" class="in-modal" style="display: none;">
        <div class="in-modal-content">
            <h3>Batch Details</h3>
            <div id="batch-details-content"></div>
            <button class="bntm-btn-secondary close-batch-modal" style="margin-top: 20px;">Close</button>
        </div>
    </div>

    <style>
    .cost-item-row, .batch-cost-item-row {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
        align-items: center;
    }
    .cost-item-row input, .batch-cost-item-row input {
        padding: 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
    }
    .in-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .in-modal-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 700px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    </style>

    <script>
    (function() {
        // ========== LOAD MATERIALS ON PRODUCT SELECT ==========
        const productSelect = document.getElementById('batch-product-select');
        
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const materialsJson = selectedOption.getAttribute('data-materials');
            
            const container = document.getElementById('cost-items-container');
            container.innerHTML = '';
            
            let materials = [];
            try {
                materials = JSON.parse(materialsJson || '[]');
            } catch(e) {
                materials = [];
            }
            
            if (materials.length === 0) {
                // Add empty row if no materials
                addCostItemRow(container, '', '');
            } else {
                // Load materials from product
                materials.forEach(mat => {
                    addCostItemRow(container, mat.name || '', mat.cost || '');
                });
            }
            
            updateAllCosts();
        });
        
        // ========== ADD COST ITEM ROW ==========
        function addCostItemRow(container, name = '', amount = '') {
            const row = document.createElement('div');
            row.className = 'cost-item-row';
            row.innerHTML = `
                <input type="text" name="cost_item_name[]" value="${name}" placeholder="Item/Material/Labor" style="flex: 2;" required>
                <input type="number" name="cost_item_amount[]" value="${amount}" placeholder="Amount per unit" step="0.01" style="flex: 1;" required>
                <button type="button" class="bntm-btn-small bntm-btn-danger remove-cost-item">✕</button>
            `;
            container.appendChild(row);
        }
        
        // ========== ADD BATCH COST ITEM ROW ==========
        function addBatchCostItemRow(container, name = '', amount = '') {
            const row = document.createElement('div');
            row.className = 'batch-cost-item-row';
            row.innerHTML = `
                <input type="text" name="batch_cost_item_name[]" value="${name}" placeholder="e.g., Setup Fee, Utilities" style="flex: 2;">
                <input type="number" name="batch_cost_item_amount[]" value="${amount}" placeholder="Total amount" step="0.01" style="flex: 1;">
                <button type="button" class="bntm-btn-small bntm-btn-danger remove-batch-cost-item">✕</button>
            `;
            container.appendChild(row);
        }
        
        // ========== ADD NEW COST ITEM BUTTON ==========
        document.getElementById('add-cost-item-btn').addEventListener('click', function() {
            const container = document.getElementById('cost-items-container');
            addCostItemRow(container);
            updateAllCosts();
        });
        
        // ========== ADD NEW BATCH COST ITEM BUTTON ==========
        document.getElementById('add-batch-cost-item-btn').addEventListener('click', function() {
            const container = document.getElementById('batch-cost-items-container');
            addBatchCostItemRow(container);
            updateAllCosts();
        });
        
        // ========== REMOVE COST ITEM ==========
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-cost-item')) {
                const rows = document.querySelectorAll('.cost-item-row');
                if (rows.length > 1) {
                    e.target.closest('.cost-item-row').remove();
                    updateAllCosts();
                } else {
                    alert('At least one per-unit cost item is required');
                }
            }
            
            if (e.target.classList.contains('remove-batch-cost-item')) {
                const rows = document.querySelectorAll('.batch-cost-item-row');
                if (rows.length > 1) {
                    e.target.closest('.batch-cost-item-row').remove();
                    updateAllCosts();
                } else {
                    // Just clear the values instead of removing the last row
                    const row = e.target.closest('.batch-cost-item-row');
                    row.querySelectorAll('input').forEach(input => input.value = '');
                    updateAllCosts();
                }
            }
        });
        
        // ========== UPDATE ALL COSTS ON INPUT ==========
        document.addEventListener('input', function(e) {
            if (e.target.name === 'cost_item_amount[]' || 
                e.target.name === 'batch_cost_item_amount[]' ||
                e.target.id === 'batch-quantity') {
                updateAllCosts();
            }
        });
        
        function updateAllCosts() {
            const quantity = parseInt(document.getElementById('batch-quantity').value) || 0;
            
            // Calculate per-unit costs
            const perUnitAmounts = document.querySelectorAll('input[name="cost_item_amount[]"]');
            let perUnitCost = 0;
            perUnitAmounts.forEach(input => {
                const value = parseFloat(input.value);
                if (!isNaN(value)) {
                    perUnitCost += value;
                }
            });
            
            // Calculate batch costs
            const batchAmounts = document.querySelectorAll('input[name="batch_cost_item_amount[]"]');
            let batchCost = 0;
            batchAmounts.forEach(input => {
                const value = parseFloat(input.value);
                if (!isNaN(value)) {
                    batchCost += value;
                }
            });
            
            // Calculate totals
            const perUnitTotal = perUnitCost * quantity;
            const grandTotal = perUnitTotal + batchCost;
            const finalCostPerUnit = quantity > 0 ? grandTotal / quantity : 0;
            
            // Update displays
            document.getElementById('per-unit-cost-display').textContent = perUnitCost.toFixed(2);
            document.getElementById('per-unit-total-display').textContent = perUnitTotal.toFixed(2);
            document.getElementById('batch-cost-display').textContent = batchCost.toFixed(2);
            document.getElementById('grand-total-cost-display').textContent = grandTotal.toFixed(2);
            document.getElementById('final-cost-per-unit-display').textContent = finalCostPerUnit.toFixed(2);
        }
        
        // ========== ADD BATCH FORM SUBMIT ==========
        document.getElementById('in-add-batch-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'in_add_batch');
            formData.append('nonce', '<?php echo $nonce; ?>');
            
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Adding...';
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(json => {
                const message = document.getElementById('batch-message');
                if (json.success) {
                    message.innerHTML = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    message.innerHTML = '<div class="bntm-notice bntm-notice-error">' + json.data.message + '</div>';
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            })
            .catch(err => {
                const message = document.getElementById('batch-message');
                message.innerHTML = '<div class="bntm-notice bntm-notice-error">Error: ' + err.message + '</div>';
                btn.disabled = false;
                btn.textContent = originalText;
            });
        });
        
        // ========== VIEW BATCH DETAILS ==========
        document.querySelectorAll('.view-batch-details').forEach(btn => {
            btn.addEventListener('click', function() {
                const details = JSON.parse(this.getAttribute('data-details'));
                let breakdown = [];
                let batchCosts = [];
                
                try {
                    breakdown = JSON.parse(details.cost_breakdown || '[]');
                } catch(e) {
                    breakdown = [];
                }
                
                try {
                    batchCosts = JSON.parse(details.batch_costs || '[]');
                } catch(e) {
                    batchCosts = [];
                }
                
                let breakdownHtml = '<ul style="margin: 10px 0; padding-left: 20px;">';
                if (breakdown.length > 0) {
                    breakdown.forEach(item => {
                        const perUnitAmount = parseFloat(item.amount);
                        const totalAmount = perUnitAmount * details.quantity;
                        breakdownHtml += `<li>${item.name}: ₱${perUnitAmount.toFixed(2)} × ${details.quantity} = ₱${totalAmount.toFixed(2)}</li>`;
                    });
                } else {
                    breakdownHtml += '<li>No per-unit costs</li>';
                }
                breakdownHtml += '</ul>';
                
                let batchCostsHtml = '<ul style="margin: 10px 0; padding-left: 20px;">';
                if (batchCosts.length > 0) {
                    batchCosts.forEach(item => {
                        batchCostsHtml += `<li>${item.name}: ₱${parseFloat(item.amount).toFixed(2)}</li>`;
                    });
                } else {
                    batchCostsHtml += '<li>No batch costs</li>';
                }
                batchCostsHtml += '</ul>';
                
                const costPerUnit = details.quantity > 0 ? (details.total_cost / details.quantity) : 0;
                
                const content = `
                    <div style="background: #f9fafb; padding: 20px; border-radius: 6px;">
                        <p><strong>Batch Code:</strong> ${details.batch_code}</p>
                        <p><strong>Product:</strong> ${details.product}</p>
                        <p><strong>Quantity:</strong> ${details.quantity} units</p>
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                        <div style="margin-top: 15px;">
                            <strong>Per Unit Costs:</strong>
                            ${breakdownHtml}
                        </div>
                        <div style="margin-top: 15px;">
                            <strong>Whole Batch Costs:</strong>
                            ${batchCostsHtml}
                        </div>
                        <hr style="margin: 15px 0; border: none; border-top: 2px solid #3b82f6;">
                        <p style="font-size: 16px;"><strong>Total Cost:</strong> ₱${parseFloat(details.total_cost).toFixed(2)}</p>
                        <p style="font-size: 16px;"><strong>Cost Per Unit:</strong> ₱${costPerUnit.toFixed(2)}</p>
                        <hr style="margin: 15px 0; border: none; border-top: 1px solid #e5e7eb;">
                        <p><strong>Manufacturing Date:</strong> ${details.manufacture_date ? new Date(details.manufacture_date).toLocaleDateString() : 'N/A'}</p>
                        <p><strong>Created:</strong> ${new Date(details.created_at).toLocaleDateString()}</p>
                        ${details.notes ? `<p style="margin-top: 15px;"><strong>Notes:</strong><br>${details.notes}</p>` : ''}
                    </div>
                `;
                
                document.getElementById('batch-details-content').innerHTML = content;
                document.getElementById('batch-details-modal').style.display = 'flex';
            });
        });
        
        // ========== CLOSE BATCH DETAILS MODAL ==========
        document.querySelector('.close-batch-modal').addEventListener('click', function() {
            document.getElementById('batch-details-modal').style.display = 'none';
        });
        
        // Close modal on background click
        document.getElementById('batch-details-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        
        // ========== DELETE BATCH ==========
        document.querySelectorAll('.in-delete-batch').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to delete this batch?\n\nThis will reduce the product stock by ' + this.closest('tr').children[2].textContent + ' units.')) return;
                
                const batchId = this.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'in_delete_batch');
                formData.append('batch_id', batchId);
                formData.append('nonce', '<?php echo $nonce; ?>');
                
                this.disabled = true;
                this.textContent = 'Deleting...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        location.reload();
                    } else {
                        alert(json.data.message);
                        this.disabled = false;
                        this.textContent = 'Delete';
                    }
                })
                .catch(err => {
                    alert('Error: ' + err.message);
                    this.disabled = false;
                    this.textContent = 'Delete';
                });
            });
        });
        
        // Initialize cost calculation on page load
        updateAllCosts();
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ========================================
   SUPPORTING AJAX FUNCTION 1: Add Batch
   ======================================== */
function bntm_ajax_in_add_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $batches_table = $wpdb->prefix . 'in_batches';
    $products_table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $product_id = intval($_POST['product_id']);
    $batch_code = sanitize_text_field($_POST['batch_code']);
    $quantity = intval($_POST['quantity']);
    $manufacture_date = sanitize_text_field($_POST['manufacture_date']);
    $notes = sanitize_textarea_field($_POST['notes']);
    
    // Validate product exists
    $product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $products_table ",
        $product_id, $business_id
    ));
    
    if (!$product) {
        wp_send_json_error(['message' => 'Product not found.']);
    }
    
    // Process per-unit cost breakdown
    $cost_items = [];
    $per_unit_total = 0;
    
    if (isset($_POST['cost_item_name']) && is_array($_POST['cost_item_name'])) {
        foreach ($_POST['cost_item_name'] as $index => $item_name) {
            if (!empty($item_name) && isset($_POST['cost_item_amount'][$index])) {
                $amount = floatval($_POST['cost_item_amount'][$index]);
                if ($amount > 0) {
                    $cost_items[] = [
                        'name' => sanitize_text_field($item_name),
                        'amount' => $amount
                    ];
                    $per_unit_total += $amount;
                }
            }
        }
    }
    
    if (empty($cost_items)) {
        wp_send_json_error(['message' => 'At least one per-unit cost item is required.']);
    }
    
    // Process batch costs (whole batch)
    $batch_cost_items = [];
    $batch_costs_total = 0;
    
    if (isset($_POST['batch_cost_item_name']) && is_array($_POST['batch_cost_item_name'])) {
        foreach ($_POST['batch_cost_item_name'] as $index => $item_name) {
            if (!empty($item_name) && isset($_POST['batch_cost_item_amount'][$index])) {
                $amount = floatval($_POST['batch_cost_item_amount'][$index]);
                if ($amount > 0) {
                    $batch_cost_items[] = [
                        'name' => sanitize_text_field($item_name),
                        'amount' => $amount
                    ];
                    $batch_costs_total += $amount;
                }
            }
        }
    }
    
    // Calculate total cost: (per_unit_cost × quantity) + batch_costs
    $total_cost = ($per_unit_total * $quantity) + $batch_costs_total;

    // Check if batch code already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $batches_table WHERE batch_code = %s ",
        $batch_code, $business_id
    ));
    
    if ($exists) {
        wp_send_json_error(['message' => 'Batch code already exists. Please use a unique code.']);
    }

    // Insert batch
    $result = $wpdb->insert($batches_table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'product_id' => $product_id,
        'batch_code' => $batch_code,
        'quantity' => $quantity,
        'total_cost' => $total_cost,
        'cost_breakdown' => json_encode($cost_items),
        'batch_costs' => json_encode($batch_cost_items),
        'manufacture_date' => $manufacture_date,
        'notes' => $notes
    ], ['%s', '%d', '%d', '%s', '%d', '%f', '%s', '%s', '%s', '%s']);

    if ($result) {
        // Update product stock
        $wpdb->query($wpdb->prepare(
            "UPDATE $products_table SET stock_quantity = stock_quantity + %d WHERE id = %d",
            $quantity, $product_id
        ));
        
        wp_send_json_success(['message' => 'Batch added successfully! Stock updated.']);
    } else {
        wp_send_json_error(['message' => 'Failed to add batch. Please try again.']);
    }
}

/* ========================================
   SUPPORTING AJAX FUNCTION 2: Delete Batch
   ======================================== */
function bntm_ajax_in_delete_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    global $wpdb;
    $batches_table = $wpdb->prefix . 'in_batches';
    $products_table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $batch_id = intval($_POST['batch_id']);
    
    // Get batch info
    $batch = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $batches_table WHERE id = %d ",
        $batch_id, $business_id
    ));
    
    if (!$batch) {
        wp_send_json_error(['message' => 'Batch not found.']);
    }

    // Delete batch
    $result = $wpdb->delete($batches_table, [
        'id' => $batch_id,
        'business_id' => $business_id
    ], ['%d', '%d']);

    if ($result) {
        // Reduce product stock (don't go below 0)
        $wpdb->query($wpdb->prepare(
            "UPDATE $products_table SET stock_quantity = GREATEST(0, stock_quantity - %d) WHERE id = %d",
            $batch->quantity, $batch->product_id
        ));
        
        wp_send_json_success(['message' => 'Batch deleted successfully! Stock updated.']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete batch. Please try again.']);
    }
}
function in_import_tab($business_id) {
    global $wpdb;
    $batches_table = $wpdb->prefix . 'in_batches';
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $products_table = $wpdb->prefix . 'in_products';
    
    $batches = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as product_name,
        (SELECT COUNT(*) FROM {$txn_table} WHERE reference_type='inventory_batch' AND reference_id=b.id) as is_imported
        FROM {$batches_table} b
        LEFT JOIN {$products_table} p ON b.product_id = p.id
        ORDER BY b.created_at DESC
    ", $business_id));
    
    $nonce = wp_create_nonce('in_nonce');
    
    ob_start();
    ?>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    </script>
    
    <div class="bntm-form-section">
        <h3>Import Batches to Finance</h3>
        <p>Import batch production costs as expense transactions in the Finance module.</p>
        
        <table class="bntm-table">
            <thead>
                <tr>
                    <th>Batch Code</th>
                    <th>Product</th>
                    <th>Date</th>
                    <th>Quantity</th>
                    <th>Total Cost</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($batches)): ?>
                <tr><td colspan="6" style="text-align:center;">No batches found</td></tr>
                <?php else: foreach ($batches as $batch): 
                    $breakdown = json_decode($batch->cost_breakdown, true);
                    $breakdown_text = '';
                    if (is_array($breakdown)) {
                        $items = array_map(function($item) {
                            return $item['name'] . ': ₱' . number_format($item['amount'], 2);
                        }, $breakdown);
                        $breakdown_text = implode(', ', $items);
                    }
                ?>
                <tr>
                    <td><?php echo esc_html($batch->batch_code); ?></td>
                    <td><?php echo esc_html($batch->product_name); ?></td>
                    <td><?php echo date('M d, Y', strtotime($batch->created_at)); ?></td>
                    <td><?php echo esc_html($batch->quantity); ?> units</td>
                    <td class="bntm-stat-expense">₱<?php echo number_format($batch->total_cost, 2); ?></td>
                    <td>
                        <?php if ($batch->is_imported): ?>
                        <button class="bntm-btn-small in-revert-batch" 
                                data-id="<?php echo $batch->id; ?>" 
                                data-nonce="<?php echo $nonce; ?>">Revert</button>
                        <span style="color:#059669; margin-left:8px;">✓ Imported</span>
                        <?php else: ?>
                        <button class="bntm-btn-small in-import-batch" 
                                data-id="<?php echo $batch->id; ?>" 
                                data-amount="<?php echo $batch->total_cost; ?>"
                                data-batch="<?php echo esc_attr($batch->batch_code); ?>"
                                data-product="<?php echo esc_attr($batch->product_name); ?>"
                                data-breakdown="<?php echo esc_attr($breakdown_text); ?>"
                                data-nonce="<?php echo $nonce; ?>">Import</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    (function() {
        // Import batch
        document.querySelectorAll('.in-import-batch').forEach(btn => {
            btn.addEventListener('click', function() {
                const batchCode = this.dataset.batch;
                const product = this.dataset.product;
                const breakdown = this.dataset.breakdown;
                
                if (!confirm(`Import batch ${batchCode} (${product}) as expense?\n\nCost Breakdown:\n${breakdown}`)) return;
                
                const data = new FormData();
                data.append('action', 'in_import_batch_expense');
                data.append('batch_id', this.dataset.id);
                data.append('amount', this.dataset.amount);
                data.append('batch_code', batchCode);
                data.append('product', product);
                data.append('breakdown', breakdown);
                data.append('nonce', this.dataset.nonce);
                
                this.disabled = true;
                this.textContent = 'Importing...';
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                    else {
                        this.disabled = false;
                        this.textContent = 'Import';
                    }
                });
            });
        });
        
        // Revert batch
        document.querySelectorAll('.in-revert-batch').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!confirm('Remove this batch from finance transactions?')) return;
                
                const data = new FormData();
                data.append('action', 'in_revert_batch_expense');
                data.append('batch_id', this.dataset.id);
                data.append('nonce', this.dataset.nonce);
                
                this.disabled = true;
                this.textContent = 'Reverting...';
                
                fetch(ajaxurl, {method: 'POST', body: data})
                .then(r => r.json())
                .then(json => {
                    alert(json.data.message);
                    if (json.success) location.reload();
                    else {
                        this.disabled = false;
                        this.textContent = 'Revert';
                    }
                });
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

function in_settings_tab($business_id) {
    $nonce = wp_create_nonce('in_nonce');
    
    ob_start();
    ?>
    <div class="bntm-form-section">
        <h3>Inventory Settings</h3>
        <form id="in-settings-form" class="bntm-form">
            <div class="bntm-form-group">
                <label>Default Reorder Level</label>
                <input type="number" name="default_reorder_level" value="<?php echo esc_attr(bntm_get_setting('in_default_reorder_level', '10')); ?>">
                <small>Default minimum stock level for new products</small>
            </div>
            
            <div class="bntm-form-group">
                <label>Low Stock Alert Email</label>
                <input type="email" name="low_stock_email" value="<?php echo esc_attr(bntm_get_setting('in_low_stock_email', '')); ?>" placeholder="your@email.com">
                <small>Receive notifications when products reach low stock</small>
            </div>
            
            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="PHP" <?php selected(bntm_get_setting('in_currency', 'PHP'), 'PHP'); ?>>PHP - Philippine Peso</option>
                    <option value="USD" <?php selected(bntm_get_setting('in_currency', 'PHP'), 'USD'); ?>>USD - US Dollar</option>
                    <option value="EUR" <?php selected(bntm_get_setting('in_currency', 'PHP'), 'EUR'); ?>>EUR - Euro</option>
                </select>
            </div>
            
            <button type="submit" class="bntm-btn-primary">Save Settings</button>
            <div id="settings-message"></div>
        </form>
    </div>

    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    document.getElementById('in-settings-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'in_save_settings');
        formData.append('nonce', '<?php echo $nonce; ?>');
        
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(json => {
            const message = document.getElementById('settings-message');
            message.innerHTML = '<div class="bntm-notice bntm-notice-' + (json.success ? 'success' : 'error') + '">' + json.data.message + '</div>';
            btn.disabled = false;
            btn.textContent = 'Save Settings';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLERS ---------- */

/* ========================================
   FUNCTION 3: AJAX - Upload Product Image
   ======================================== */
function bntm_ajax_in_upload_product_image() {
    check_ajax_referer('in_upload_image', '_ajax_nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Unauthorized');
    }

    if (!isset($_FILES['image'])) {
        wp_send_json_error('No file uploaded');
    }

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $file = $_FILES['image'];
    $upload = wp_handle_upload($file, ['test_form' => false]);

    if (isset($upload['error'])) {
        wp_send_json_error($upload['error']);
    }

    wp_send_json_success(['url' => $upload['url']]);
}
add_action('wp_ajax_in_upload_product_image', 'bntm_ajax_in_upload_product_image');

/* ========================================
   FUNCTION 4: AJAX - Add Product (MODIFIED)
   ======================================== */
function bntm_ajax_in_add_product() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $name = sanitize_text_field($_POST['name']);
    $sku = sanitize_text_field($_POST['sku']);
    $selling_price = floatval($_POST['selling_price']);
    $reorder_level = intval($_POST['reorder_level']);
    $description = sanitize_textarea_field($_POST['description']);
    $product_image = isset($_POST['product_image']) ? esc_url_raw($_POST['product_image']) : '';
    
    // Process materials
    $materials = [];
    if (isset($_POST['material_name']) && is_array($_POST['material_name'])) {
        foreach ($_POST['material_name'] as $index => $mat_name) {
            if (!empty($mat_name) && isset($_POST['material_cost'][$index])) {
                $materials[] = [
                    'name' => sanitize_text_field($mat_name),
                    'cost' => floatval($_POST['material_cost'][$index])
                ];
            }
        }
    }

    $result = $wpdb->insert($table, [
        'rand_id' => bntm_rand_id(),
        'business_id' => $business_id,
        'name' => $name,
        'sku' => $sku,
        'selling_price' => $selling_price,
        'stock_quantity' => 0,
        'reorder_level' => $reorder_level,
        'materials' => json_encode($materials),
        'description' => $description,
        'image' => $product_image
    ], ['%s', '%d', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s']);

    if ($result) {
        wp_send_json_success(['message' => 'Product added successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to add product.']);
    }
}

/* ========================================
   FUNCTION 5: AJAX - Update Product (MODIFIED)
   ======================================== */
function bntm_ajax_in_update_product() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $product_id = intval($_POST['product_id']);
    $product_image = isset($_POST['product_image']) ? esc_url_raw($_POST['product_image']) : '';
    
    // If no new image, keep current image
    if (empty($product_image) && isset($_POST['current_image'])) {
        $product_image = esc_url_raw($_POST['current_image']);
    }
    
    // Process materials
    $materials = [];
    if (isset($_POST['material_name']) && is_array($_POST['material_name'])) {
        foreach ($_POST['material_name'] as $index => $mat_name) {
            if (!empty($mat_name) && isset($_POST['material_cost'][$index])) {
                $materials[] = [
                    'name' => sanitize_text_field($mat_name),
                    'cost' => floatval($_POST['material_cost'][$index])
                ];
            }
        }
    }
    
    $update_data = [
        'name' => sanitize_text_field($_POST['name']),
        'sku' => sanitize_text_field($_POST['sku']),
        'selling_price' => floatval($_POST['selling_price']),
        'reorder_level' => intval($_POST['reorder_level']),
        'description' => sanitize_textarea_field($_POST['description']),
        'materials' => json_encode($materials),
        'image' => $product_image
    ];

    $result = $wpdb->update(
        $table,
        $update_data,
        ['id' => $product_id, 'business_id' => $business_id],
        ['%s', '%s', '%f', '%d', '%s', '%s', '%s'],
        ['%d', '%d']
    );

    if ($result !== false) {
        wp_send_json_success(['message' => 'Product updated successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to update product.']);
    }
}

function bntm_ajax_in_delete_product() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    global $wpdb;
    $table = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    $product_id = intval($_POST['product_id']);

    $result = $wpdb->delete($table, [
        'id' => $product_id,
        'business_id' => $business_id
    ], ['%d', '%d']);

    if ($result) {
        wp_send_json_success(['message' => 'Product deleted successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete product.']);
    }
}



function bntm_ajax_in_import_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }
    
    global $wpdb;
    $txn_table = $wpdb->prefix . 'fn_transactions';
    $batch_id = intval($_POST['batch_id']);
    $amount = floatval($_POST['amount']);
    $batch_code = sanitize_text_field($_POST['batch_code']);
    $product = sanitize_text_field($_POST['product']);
    $breakdown = sanitize_text_field($_POST['breakdown']);
    
    // Check if already imported
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$txn_table} WHERE reference_type='inventory_batch' AND reference_id=%d",
        $batch_id
    ));
    
    if ($exists) {
        wp_send_json_error(['message' => 'Batch already imported.']);
    }
    
    $notes = "Inventory Batch: {$batch_code} - {$product}\nCost Breakdown: {$breakdown}";
    
    $data = [
        'rand_id' => bntm_rand_id(),
        'business_id' => get_current_user_id(),
        'type' => 'expense',
        'amount' => $amount,
        'category' => 'Production Cost',
        'notes' => $notes,
        'reference_type' => 'inventory_batch',
        'reference_id' => $batch_id
    ];
    
    $result = $wpdb->insert($txn_table, $data);
    
    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Batch imported successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to import batch.']);
    }
}

function bntm_ajax_in_revert_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fn_transactions';
    $batch_id = intval($_POST['batch_id']);
    
    $result = $wpdb->delete($table, [
        'reference_type' => 'inventory_batch',
        'reference_id' => $batch_id
    ]);
    
    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) {
            bntm_fn_update_cashflow_summary();
        }
        wp_send_json_success(['message' => 'Batch reverted from transactions.']);
    } else {
        wp_send_json_error(['message' => 'Failed to revert batch.']);
    }
}

function bntm_ajax_in_save_settings() {
    check_ajax_referer('in_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    bntm_update_setting('in_default_reorder_level', intval($_POST['default_reorder_level']));
    bntm_update_setting('in_low_stock_email', sanitize_email($_POST['low_stock_email']));
    bntm_update_setting('in_currency', sanitize_text_field($_POST['currency']));
    
    wp_send_json_success(['message' => 'Settings saved successfully!']);
}
add_action('wp_ajax_in_save_settings', 'bntm_ajax_in_save_settings');

