<?php
/**
 * Module Name: Inventory Management
 * Module Slug: in
 * Description: Complete inventory management solution with products, batches, and cost tracking
 * Version: 1.1.0
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
 */
function bntm_in_get_pages() {
    return [
        'Inventory' => '[in_dashboard]'
    ];
}

/**
 * Get module database tables
 * CHANGED: Added `unit` column to in_products
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
            barcode VARCHAR(100) UNIQUE,
            inventory_type VARCHAR(50) DEFAULT 'Product',
            unit VARCHAR(30) DEFAULT 'pc',
            cost_per_unit DECIMAL(10,2) DEFAULT 0,
            selling_price DECIMAL(10,2) NOT NULL,
            stock_quantity DECIMAL(10,3) DEFAULT 0,
            reorder_level DECIMAL(10,3) DEFAULT 10,
            description TEXT,
            image VARCHAR(500),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_sku (sku),
            INDEX idx_barcode (barcode),
            INDEX idx_stock (stock_quantity),
            INDEX idx_type (inventory_type),
            INDEX idx_unit (unit)
        ) {$charset};",
        
        'in_batches' => "CREATE TABLE {$prefix}in_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_id BIGINT UNSIGNED NOT NULL,
            batch_code VARCHAR(100) NOT NULL,
            type ENUM('stock_in', 'stock_out') DEFAULT 'stock_in',
            quantity DECIMAL(10,3) NOT NULL,
            cost_per_unit DECIMAL(10,2) DEFAULT 0,
            total_cost DECIMAL(10,2) NOT NULL,
            reference_number VARCHAR(100),
            manufacture_date DATE,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_business (business_id),
            INDEX idx_product (product_id),
            INDEX idx_batch_code (batch_code),
            INDEX idx_type (type),
            INDEX idx_date (manufacture_date)
        ) {$charset};"
    ];
}

/**
 * Get module shortcodes
 */
function bntm_in_get_shortcodes() {
    return [
        'in_dashboard' => 'bntm_in_shortcode_dashboard'
    ];
}

/**
 * Create module tables
 */
function bntm_in_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $tables = bntm_in_get_tables();
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
    return count($tables);
}

// ── Helper: all supported quantity units ──────────────────────────────────────
function bntm_in_get_units() {
    return [
        // Count
        'pc'     => 'pc – Piece',
        'pair'   => 'pair – Pair',
        'set'    => 'set – Set',
        'box'    => 'box – Box',
        'pack'   => 'pack – Pack',
        'carton' => 'carton – Carton',
        'dozen'  => 'dozen – Dozen',
        'roll'   => 'roll – Roll',
        'sheet'  => 'sheet – Sheet',
        'bundle' => 'bundle – Bundle',
        'bag'    => 'bag – Bag',
        'bottle' => 'bottle – Bottle',
        'can'    => 'can – Can',
        'jar'    => 'jar – Jar',
        'tube'   => 'tube – Tube',
        // Weight
        'kg'     => 'kg – Kilogram',
        'g'      => 'g – Gram',
        'mg'     => 'mg – Milligram',
        'lb'     => 'lb – Pound',
        'oz'     => 'oz – Ounce',
        't'      => 't – Metric Ton',
        // Volume
        'L'      => 'L – Liter',
        'mL'     => 'mL – Milliliter',
        'gal'    => 'gal – Gallon',
        'fl oz'  => 'fl oz – Fluid Ounce',
        // Length / Area
        'm'      => 'm – Meter',
        'cm'     => 'cm – Centimeter',
        'mm'     => 'mm – Millimeter',
        'ft'     => 'ft – Foot',
        'in'     => 'in – Inch',
        'sqm'    => 'sqm – Square Meter',
        'sqft'   => 'sqft – Square Foot',
    ];
}

// ── Helper: render <option> tags for units ────────────────────────────────────
function bntm_in_unit_options( $selected = 'pc' ) {
    $units = bntm_in_get_units();
    $html  = '';
    foreach ( $units as $val => $label ) {
        $sel   = selected( $selected, $val, false );
        $html .= "<option value=\"" . esc_attr($val) . "\" $sel>" . esc_html($label) . "</option>\n";
    }
    return $html;
}

// AJAX handlers
add_action('wp_ajax_in_add_product',          'bntm_ajax_in_add_product');
add_action('wp_ajax_in_update_product',       'bntm_ajax_in_update_product');
add_action('wp_ajax_in_delete_product',       'bntm_ajax_in_delete_product');
add_action('wp_ajax_in_add_batch',            'bntm_ajax_in_add_batch');
add_action('wp_ajax_in_delete_batch',         'bntm_ajax_in_delete_batch');
add_action('wp_ajax_in_import_batch_expense', 'bntm_ajax_in_import_batch_expense');
add_action('wp_ajax_in_revert_batch_expense', 'bntm_ajax_in_revert_batch_expense');
add_action('wp_ajax_in_upload_product_image', 'bntm_ajax_in_upload_product_image');
add_action('wp_ajax_in_save_settings',        'bntm_ajax_in_save_settings');

/* ---------- MAIN INVENTORY SHORTCODE ---------- */
function bntm_in_shortcode_dashboard() {
    if (!is_user_logged_in()) {
        return '<div class="bntm-notice">Please log in to access the Inventory dashboard.</div>';
    }
    
    $current_user = wp_get_current_user();
    $business_id  = $current_user->ID;
    $active_tab   = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    
    ob_start();
    ?>
    <div class="bntm-inventory-container">
        <div class="bntm-tabs">
            <a href="?tab=overview"  class="bntm-tab <?php echo $active_tab === 'overview'  ? 'active' : ''; ?>">Overview</a>
            <a href="?tab=products"  class="bntm-tab <?php echo $active_tab === 'products'  ? 'active' : ''; ?>">Products</a>
            <a href="?tab=batches"   class="bntm-tab <?php echo $active_tab === 'batches'   ? 'active' : ''; ?>">Batches</a>
            <?php if (bntm_is_module_enabled('fn') && bntm_is_module_visible('fn')): ?>
            <a href="?tab=import"    class="bntm-tab <?php echo $active_tab === 'import'    ? 'active' : ''; ?>">Import to Finance</a>
            <?php endif; ?>
            <a href="?tab=settings"  class="bntm-tab <?php echo $active_tab === 'settings'  ? 'active' : ''; ?>">Settings</a>
        </div>
        
        <div class="bntm-tab-content">
            <?php
            if      ($active_tab === 'overview')  echo in_overview_tab($business_id);
            elseif  ($active_tab === 'products')  echo in_products_tab($business_id);
            elseif  ($active_tab === 'batches')   echo in_batches_tab($business_id);
            elseif  ($active_tab === 'import')    echo in_import_tab($business_id);
            elseif  ($active_tab === 'settings')  echo in_settings_tab($business_id);
            ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Inventory Management', $content);
}

/* ---------- OVERVIEW TAB ---------- */
function in_overview_tab($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'in_products';
    $batches_table  = $wpdb->prefix . 'in_batches';
    
    $total_products       = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
    $total_stock          = $wpdb->get_var("SELECT SUM(stock_quantity) FROM $products_table");
    $low_stock_items      = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE stock_quantity <= reorder_level AND stock_quantity > 0");
    $out_of_stock         = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE stock_quantity = 0");
    $total_inventory_value= $wpdb->get_var("SELECT SUM(stock_quantity * cost_per_unit) FROM $products_table");
    $potential_revenue    = $wpdb->get_var("SELECT SUM(stock_quantity * selling_price) FROM $products_table");
    $stock_in_count       = $wpdb->get_var("SELECT COUNT(*) FROM $batches_table WHERE type = 'stock_in'  AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stock_out_count      = $wpdb->get_var("SELECT COUNT(*) FROM $batches_table WHERE type = 'stock_out' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    $top_products = $wpdb->get_results(
        "SELECT name, stock_quantity, reorder_level, unit FROM $products_table WHERE stock_quantity > 0 ORDER BY stock_quantity DESC LIMIT 10",
        ARRAY_A
    ) ?: [];
    
    $inventory_by_type = $wpdb->get_results(
        "SELECT inventory_type, COUNT(*) as count, SUM(stock_quantity) as total_stock FROM $products_table GROUP BY inventory_type",
        ARRAY_A
    );
    
    $stock_status = $wpdb->get_results("
        SELECT 
            CASE 
                WHEN stock_quantity = 0          THEN 'Out of Stock'
                WHEN stock_quantity <= reorder_level THEN 'Low Stock'
                ELSE 'In Stock'
            END as status,
            COUNT(*) as count
        FROM $products_table
        GROUP BY status
    ", ARRAY_A);
    
    $recent_transactions = $wpdb->get_results(
        "SELECT b.*, p.name as product_name, p.unit as product_unit
        FROM $batches_table b
        LEFT JOIN $products_table p ON b.product_id = p.id
        ORDER BY b.created_at DESC LIMIT 10"
    );
    
    $low_stock_products = $wpdb->get_results(
        "SELECT * FROM $products_table WHERE stock_quantity <= reorder_level ORDER BY stock_quantity ASC LIMIT 5"
    );

    ob_start();
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <div class="in-dashboard-stats">
        <?php
        $stats = [
            ['icon'=>'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z',
              'extra'=>'<polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
              'class'=>'primary','title'=>'Total Products','value'=>esc_html($total_products),'sub'=>'Unique items tracked'],
            ['icon'=>'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z',
              'extra'=>'<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>',
              'class'=>'primary','title'=>'Total Stock Units','value'=>esc_html(number_format($total_stock ?: 0, 3) * 1),'sub'=>'Items in inventory'],
            ['icon'=>'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
              'extra'=>'','class'=>'primary','title'=>'Inventory Value',
              'value'=>'₱'.number_format($total_inventory_value ?: 0, 2),'sub'=>'Total cost value'],
            ['icon'=>'M22 12l-4 0-3 9-6-18-3 9-4 0',
              'extra'=>'','class'=>'primary','title'=>'Potential Revenue',
              'value'=>'₱'.number_format($potential_revenue ?: 0, 2),'sub'=>'If all stock sold'],
            ['icon'=>'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z',
              'extra'=>'<line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
              'class'=>'warning','title'=>'Low Stock Alerts',
              'value'=>'<span style="color:'.($low_stock_items > 0 ? '#dc2626':'#059669').'">'.esc_html($low_stock_items).'</span>',
              'sub'=>'At or below reorder level'],
            ['icon'=>'M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zM15 9l-6 6M9 9l6 6',
              'extra'=>'','class'=>'danger','title'=>'Out of Stock',
              'value'=>'<span style="color:'.($out_of_stock > 0 ? '#991b1b':'#059669').'">'.esc_html($out_of_stock).'</span>',
              'sub'=>'Items with 0 stock'],
            ['icon'=>'M12 5v14M19 12l-7 7-7-7','extra'=>'','class'=>'success',
              'title'=>'Stock In (30 days)','value'=>'<span style="color:#059669;">'.esc_html($stock_in_count).'</span>','sub'=>'Incoming transactions'],
            ['icon'=>'M12 19V5M5 12l7-7 7 7','extra'=>'','class'=>'danger',
              'title'=>'Stock Out (30 days)','value'=>'<span style="color:#dc2626;">'.esc_html($stock_out_count).'</span>','sub'=>'Outgoing transactions'],
        ];
        foreach ($stats as $s): ?>
        <div class="in-stat-card">
            <div class="in-stat-icon in-stat-icon-<?php echo $s['class']; ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="<?php echo $s['icon']; ?>"></path><?php echo $s['extra']; ?>
                </svg>
            </div>
            <div class="in-stat-content">
                <h3><?php echo $s['title']; ?></h3>
                <p class="in-stat-number"><?php echo $s['value']; ?></p>
                <small><?php echo $s['sub']; ?></small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="in-charts-grid">
        <div class="in-chart-card in-chart-large">
            <h3>Top 10 Products by Stock Level</h3>
            <canvas id="productStockChart"></canvas>
        </div>
        <div class="in-chart-card">
            <h3>Inventory by Type</h3>
            <canvas id="inventoryTypeChart"></canvas>
        </div>
        <div class="in-chart-card">
            <h3>Stock Status</h3>
            <canvas id="stockStatusChart"></canvas>
        </div>
    </div>

    <?php if (!empty($low_stock_products)): ?>
    <div class="in-alert-section">
        <h3>Low Stock Alerts</h3>
        <div class="bntm-table-wrapper">
           <table class="bntm-table">
               <thead><tr><th>Product</th><th>Type</th><th>Unit</th><th>Current Stock</th><th>Reorder Level</th><th>Status</th></tr></thead>
               <tbody>
                   <?php foreach ($low_stock_products as $p): ?>
                   <tr>
                       <td><?php echo esc_html($p->name); ?></td>
                       <td><?php echo esc_html($p->inventory_type); ?></td>
                       <td><span class="in-unit-badge"><?php echo esc_html($p->unit ?? 'pc'); ?></span></td>
                       <td><?php echo esc_html($p->stock_quantity + 0); ?> <?php echo esc_html($p->unit ?? 'pc'); ?></td>
                       <td><?php echo esc_html($p->reorder_level + 0); ?> <?php echo esc_html($p->unit ?? 'pc'); ?></td>
                       <td><?php if ($p->stock_quantity == 0): ?>
                           <span style="color:#991b1b;font-weight:500;">Out of Stock</span>
                           <?php else: ?>
                           <span style="color:#dc2626;font-weight:500;">Low Stock</span>
                           <?php endif; ?></td>
                   </tr>
                   <?php endforeach; ?>
               </tbody>
           </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="in-recent-section">
        <h3>Recent Transactions</h3>
        <?php if (empty($recent_transactions)): ?>
            <p>No transactions recorded yet.</p>
        <?php else: ?>
        <div class="bntm-table-wrapper">
           <table class="bntm-table">
               <thead><tr><th>Date</th><th>Type</th><th>Reference</th><th>Product</th><th>Quantity</th><th>Total Cost</th></tr></thead>
               <tbody>
                   <?php foreach ($recent_transactions as $txn): ?>
                   <tr>
                       <td><?php echo date('M d, Y', strtotime($txn->created_at)); ?></td>
                       <td><?php if ($txn->type === 'stock_in'): ?>
                           <span style="color:#059669;font-weight:500;">Stock In</span>
                           <?php else: ?>
                           <span style="color:#dc2626;font-weight:500;">Stock Out</span>
                           <?php endif; ?></td>
                       <td><?php echo esc_html($txn->reference_number ?: $txn->batch_code); ?></td>
                       <td><?php echo esc_html($txn->product_name); ?></td>
                       <td><?php echo esc_html($txn->quantity + 0); ?> <span class="in-unit-badge"><?php echo esc_html($txn->product_unit ?? 'pc'); ?></span></td>
                       <td>₱<?php echo number_format($txn->total_cost, 2); ?></td>
                   </tr>
                   <?php endforeach; ?>
               </tbody>
           </table>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    .in-dashboard-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:30px}
    .in-stat-card{background:#fff;padding:24px;border-radius:12px;display:flex;align-items:flex-start;gap:16px;border:1px solid #e5e7eb;transition:all .2s}
    .in-stat-card:hover{border-color:#d1d5db;box-shadow:0 4px 6px rgba(0,0,0,.05)}
    .in-stat-icon{width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
    .in-stat-icon-primary{background:var(--bntm-primary,#374151);color:#fff}
    .in-stat-icon-success{background:#10b981;color:#fff}
    .in-stat-icon-warning{background:#f59e0b;color:#fff}
    .in-stat-icon-danger{background:#ef4444;color:#fff}
    .in-stat-content{flex:1}
    .in-stat-content h3{margin:0 0 8px;font-size:13px;color:#6b7280;font-weight:500;text-transform:uppercase;letter-spacing:.5px}
    .in-stat-number{font-size:18px;font-weight:700;color:#111827;margin:0;line-height:1.3;overflow-wrap:anywhere;word-break:break-word}
    .in-stat-content small{color:#9ca3af;font-size:12px}
    .in-charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;margin-bottom:30px}
    .in-chart-card{background:#fff;padding:24px;border-radius:12px;border:1px solid #e5e7eb}
    .in-chart-large{grid-column:1/-1}
    .in-chart-card h3{margin:0 0 20px;font-size:16px;font-weight:600;color:#111827}
    .in-chart-card canvas{max-height:300px}
    .in-alert-section,.in-recent-section{background:#fff;padding:24px;border-radius:12px;border:1px solid #e5e7eb;margin-bottom:20px}
    .in-alert-section h3,.in-recent-section h3{margin:0 0 20px;font-size:18px;font-weight:600;color:#111827}
    .in-unit-badge{display:inline-block;background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:600;padding:2px 6px;border-radius:4px;line-height:1.4}
    @media(max-width:768px){.in-chart-card{grid-column:1/-1}}
    </style>
    
    <script>
    (function(){
        const topProducts=<?php echo json_encode($top_products); ?>;
        const colors=['#3b82f6','#ef4444','#10b981','#f59e0b','#8b5cf6','#ec4899','#06b6d4','#84cc16','#f97316','#14b8a6'];
        const stockCtx=document.getElementById('productStockChart');
        if(stockCtx&&topProducts.length>0){
            new Chart(stockCtx,{
                type:'bar',
                data:{
                    labels:topProducts.map(p=>p.name+' ('+p.unit+')'),
                    datasets:[
                        {label:'Current Stock',data:topProducts.map(p=>parseFloat(p.stock_quantity)),backgroundColor:colors.slice(0,topProducts.length),borderWidth:1},
                        {label:'Reorder Level',data:topProducts.map(p=>parseFloat(p.reorder_level)),backgroundColor:'#f59e0b40',borderColor:'#f59e0b',borderWidth:2,type:'line',pointRadius:0}
                    ]
                },
                options:{indexAxis:'y',responsive:true,maintainAspectRatio:true,
                    plugins:{legend:{position:'top'},tooltip:{backgroundColor:'#111827',padding:12,cornerRadius:8,
                        callbacks:{label:ctx=>ctx.dataset.label+': '+ctx.parsed.x+' '+topProducts[ctx.dataIndex].unit}}},
                    scales:{x:{beginAtZero:true,grid:{color:'#f3f4f6'},title:{display:true,text:'Stock Quantity'}},y:{grid:{display:false}}}
                }
            });
        }
        const typeCtx=document.getElementById('inventoryTypeChart');
        if(typeCtx){
            const d=<?php echo json_encode($inventory_by_type); ?>;
            if(d.length>0){
                new Chart(typeCtx,{type:'doughnut',data:{labels:d.map(t=>t.inventory_type),datasets:[{data:d.map(t=>parseFloat(t.total_stock)),backgroundColor:['#374151','#6b7280','#9ca3af','#d1d5db','#e5e7eb'],borderWidth:2,borderColor:'#fff'}]},
                    options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom'}}}});
            }
        }
        const statusCtx=document.getElementById('stockStatusChart');
        if(statusCtx){
            const s=<?php echo json_encode($stock_status); ?>;
            if(s.length>0){
                new Chart(statusCtx,{type:'pie',data:{labels:s.map(x=>x.status),datasets:[{data:s.map(x=>parseInt(x.count)),backgroundColor:['#10b981','#f59e0b','#ef4444'],borderWidth:2,borderColor:'#fff'}]},
                    options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom'}}}});
            }
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- PRODUCTS TAB ---------- */
function in_products_tab($business_id) {
    global $wpdb;
    $table    = $wpdb->prefix . 'in_products';
    $products = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
    
    $nonce        = wp_create_nonce('in_nonce');
    $upload_nonce = wp_create_nonce('in_upload_image');
    
    $current_products = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $limits           = get_option('bntm_table_limits', []);
    $product_limit    = isset($limits[$table]) ? $limits[$table] : 0;
    $limit_text       = $product_limit > 0 ? " ({$current_products}/{$product_limit})" : " ({$current_products})";
    $limit_reached    = $product_limit > 0 && $current_products >= $product_limit;

    // Build distinct inventory types for filter dropdown
    $all_types = $wpdb->get_col("SELECT DISTINCT inventory_type FROM $table ORDER BY inventory_type ASC");

    ob_start();
    ?>
    <script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <!-- ── ADD PRODUCT BUTTON ──────────────────────────────────────────── -->
    <div class="bntm-form-section">
        <button id="open-add-product-modal" class="bntm-btn-primary" <?php echo $limit_reached ? 'disabled' : ''; ?>>
            + Add New Product/Item<?php echo $limit_text; ?>
        </button>
        <?php if ($limit_reached): ?>
        <div style="background:#fee2e2;border:1px solid #fca5a5;padding:10px;border-radius:4px;margin-top:10px;">
            <strong>⚠️ Product Limit Reached:</strong> Maximum of <?php echo $product_limit; ?> products allowed.
        </div>
        <?php endif; ?>
    </div>

    <!-- ── ADD PRODUCT MODAL ──────────────────────────────────────────── -->
    <div id="add-product-modal" class="in-modal" style="display:none;">
        <div class="in-modal-content">
            <h3>Add New Product/Item</h3>
            <form id="in-add-product-form" class="bntm-form">
                <!-- Image upload -->
                <div class="bntm-form-group">
                    <label>Product Image</label>
                    <div class="in-product-image-preview" id="product-image-preview" style="display:none;">
                        <img src="" alt="Product Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-product-image">✕</button>
                    </div>
                    <div class="bntm-upload-area" id="product-upload-area">
                        <input type="file" id="product-image-upload" accept="image/*" style="display:none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="product-upload-btn">Choose Image</button>
                        <p style="margin:10px 0;color:#6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    <input type="hidden" id="product_image" name="product_image" value="">
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Inventory Type *</label>
                        <select name="inventory_type" required>
                            <option value="Product">Product</option>
                            <option value="Raw Material">Raw Material</option>
                            <option value="Finished Goods">Finished Goods</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <!-- CHANGED: Unit selector -->
                    <div class="bntm-form-group">
                        <label>Unit of Measure *</label>
                        <select name="unit" required>
                            <?php echo bntm_in_unit_options('pc'); ?>
                        </select>
                        <small>How this item is counted/measured</small>
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" placeholder="Auto-generated if blank">
                    </div>
                    <div class="bntm-form-group">
                        <label>Barcode</label>
                        <input type="text" id="product-barcode" name="barcode" placeholder="e.g., 8901234567890">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Product/Item Name *</label>
                    <input type="text" name="name" required>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Cost Per Unit</label>
                        <input type="number" name="cost_per_unit" step="0.01" value="0" min="0">
                        <small>Your cost to acquire/produce this item</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Selling Price *</label>
                        <input type="number" name="selling_price" step="0.01" required min="0">
                        <small>Price you sell this item for</small>
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Initial Stock Quantity</label>
                        <input type="number" name="initial_stock" value="0" min="0" step="0.001">
                        <small>Starting inventory (optional)</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Reorder Level *</label>
                        <input type="number" name="reorder_level" value="10" required min="0" step="0.001">
                        <small>Alert when stock falls below this</small>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Product description..."></textarea>
                </div>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button type="submit" class="bntm-btn-primary">Add Product</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── EDIT PRODUCT MODAL ─────────────────────────────────────────── -->
    <div id="edit-product-modal" class="in-modal" style="display:none;">
        <div class="in-modal-content">
            <h3>Edit Product/Item</h3>
            <form id="in-edit-product-form" class="bntm-form">
                <input type="hidden" id="edit-product-id" name="product_id">

                <!-- Image upload -->
                <div class="bntm-form-group">
                    <label>Product Image</label>
                    <div class="in-product-image-preview" id="edit-product-image-preview" style="display:none;">
                        <img src="" alt="Product Preview">
                        <button type="button" class="bntm-btn-remove-logo" id="remove-edit-product-image">✕</button>
                    </div>
                    <div class="bntm-upload-area" id="edit-product-upload-area">
                        <input type="file" id="edit-product-image-upload" accept="image/*" style="display:none;">
                        <button type="button" class="bntm-btn bntm-btn-secondary" id="edit-product-upload-btn">Choose Image</button>
                        <p style="margin:10px 0;color:#6b7280;">or drag and drop here</p>
                        <small>Recommended: JPG or PNG, max 2MB</small>
                    </div>
                    <input type="hidden" id="edit-product_image" name="product_image" value="">
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Inventory Type *</label>
                        <select id="edit-inventory-type" name="inventory_type" required>
                            <option value="Product">Product</option>
                            <option value="Raw Material">Raw Material</option>
                            <option value="Finished Goods">Finished Goods</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <!-- CHANGED: Unit selector in edit modal -->
                    <div class="bntm-form-group">
                        <label>Unit of Measure *</label>
                        <select id="edit-unit" name="unit" required>
                            <?php echo bntm_in_unit_options('pc'); ?>
                        </select>
                        <small>How this item is counted/measured</small>
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>SKU</label>
                        <input type="text" id="edit-sku" name="sku">
                    </div>
                    <div class="bntm-form-group">
                        <label>Barcode</label>
                        <input type="text" id="edit-barcode" name="barcode">
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Product/Item Name *</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Cost Per Unit</label>
                        <input type="number" id="edit-cost-per-unit" name="cost_per_unit" step="0.01" min="0">
                    </div>
                    <div class="bntm-form-group">
                        <label>Selling Price *</label>
                        <input type="number" id="edit-selling-price" name="selling_price" step="0.01" required min="0">
                    </div>
                </div>

                <div class="bntm-form-row">
                    <div class="bntm-form-group">
                        <label>Current Stock</label>
                        <input type="number" id="edit-current-stock" readonly style="background:#f3f4f6;" step="0.001">
                        <small>Use Stock In/Out to adjust</small>
                    </div>
                    <div class="bntm-form-group">
                        <label>Stock Adjustment</label>
                        <div style="display:flex;gap:5px;">
                            <input type="number" id="edit-stock-adjustment" name="stock_adjustment" value="0" step="0.001" style="flex:1;">
                            <select id="edit-adjustment-type" name="adjustment_type" style="width:100px;">
                                <option value="add">Add</option>
                                <option value="subtract">Subtract</option>
                                <option value="set">Set To</option>
                            </select>
                        </div>
                        <small>Manually adjust stock quantity</small>
                    </div>
                </div>

                <div class="bntm-form-group">
                    <label>Reorder Level *</label>
                    <input type="number" id="edit-reorder-level" name="reorder_level" required min="0" step="0.001">
                </div>

                <div class="bntm-form-group">
                    <label>Description</label>
                    <textarea id="edit-description" name="description" rows="3"></textarea>
                </div>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button type="submit" class="bntm-btn-primary">Update Product</button>
                    <button type="button" class="close-modal bntm-btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── PRODUCTS TABLE with FILTER ────────────────────────────────── -->
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h3 style="margin:0;">All Products (<?php echo count($products); ?>)</h3>

            <!-- CHANGED: Inventory-type filter + search bar -->
            <div class="in-filter-bar">
                <input type="text" id="in-product-search" placeholder="🔍 Search name / SKU / barcode…" class="in-filter-input">
                <select id="in-type-filter" class="in-filter-select">
                    <option value="">All Types</option>
                    <?php foreach ($all_types as $t): ?>
                    <option value="<?php echo esc_attr($t); ?>"><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="in-unit-filter" class="in-filter-select">
                    <option value="">All Units</option>
                    <?php
                    $used_units = $wpdb->get_col("SELECT DISTINCT unit FROM $table WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC");
                    foreach ($used_units as $u): ?>
                    <option value="<?php echo esc_attr($u); ?>"><?php echo esc_html($u); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="in-stock-filter" class="in-filter-select">
                    <option value="">All Stock</option>
                    <option value="in_stock">In Stock</option>
                    <option value="low_stock">Low Stock</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
                <button id="in-clear-filters" class="bntm-btn-secondary" style="white-space:nowrap;">Clear</button>
            </div>
        </div>

        <div id="in-no-results" style="display:none;padding:20px;text-align:center;color:#6b7280;">No products match your filters.</div>

        <?php if (empty($products)): ?>
            <p>No products found. Add your first product above.</p>
        <?php else: ?>
        <div class="bntm-table-wrapper">
            <table class="bntm-table" id="in-products-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Unit</th><!-- CHANGED: new column -->
                        <th>Cost</th>
                        <th>Selling Price</th>
                        <th>Stock</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product):
                        $qty   = $product->stock_quantity + 0;
                        $reord = $product->reorder_level + 0;
                        if ($qty == 0)            $status_label = '<span style="color:#991b1b;font-weight:500;">Out of Stock</span>';
                        elseif ($qty <= $reord)   $status_label = '<span style="color:#dc2626;font-weight:500;">Low Stock</span>';
                        else                       $status_label = '<span style="color:#059669;font-weight:500;">In Stock</span>';
                        $stock_key = ($qty == 0) ? 'out_of_stock' : (($qty <= $reord) ? 'low_stock' : 'in_stock');
                    ?>
                    <tr data-type="<?php echo esc_attr($product->inventory_type); ?>"
                        data-unit="<?php echo esc_attr($product->unit ?? 'pc'); ?>"
                        data-stock-status="<?php echo $stock_key; ?>"
                        data-search="<?php echo esc_attr(strtolower($product->name . ' ' . $product->sku . ' ' . $product->barcode)); ?>">
                        <td>
                            <?php if ($product->image): ?>
                            <img src="<?php echo esc_url($product->image); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
                            <?php else: ?>
                            <div style="width:50px;height:50px;background:#e5e7eb;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#9ca3af;">📦</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($product->inventory_type); ?></td>
                        <td><?php echo esc_html($product->name); ?></td>
                        <td><?php echo esc_html($product->sku); ?></td>
                        <td><?php echo esc_html($product->barcode ?: 'N/A'); ?></td>
                        <!-- CHANGED: unit badge -->
                        <td><span class="in-unit-badge"><?php echo esc_html($product->unit ?? 'pc'); ?></span></td>
                        <td>₱<?php echo number_format($product->cost_per_unit, 2); ?></td>
                        <td>₱<?php echo number_format($product->selling_price, 2); ?></td>
                        <td><?php echo esc_html($qty); ?> <small style="color:#6b7280;"><?php echo esc_html($product->unit ?? 'pc'); ?></small></td>
                        <td><?php echo esc_html($reord); ?> <small style="color:#6b7280;"><?php echo esc_html($product->unit ?? 'pc'); ?></small></td>
                        <td><?php echo $status_label; ?></td>
                        <td>
                            <button class="bntm-btn-small in-edit-product"
                                data-id="<?php echo $product->id; ?>"
                                data-name="<?php echo esc_attr($product->name); ?>"
                                data-sku="<?php echo esc_attr($product->sku); ?>"
                                data-barcode="<?php echo esc_attr($product->barcode); ?>"
                                data-type="<?php echo esc_attr($product->inventory_type); ?>"
                                data-unit="<?php echo esc_attr($product->unit ?? 'pc'); ?>"
                                data-cost="<?php echo $product->cost_per_unit; ?>"
                                data-price="<?php echo $product->selling_price; ?>"
                                data-stock="<?php echo $qty; ?>"
                                data-reorder="<?php echo $reord; ?>"
                                data-description="<?php echo esc_attr($product->description); ?>"
                                data-image="<?php echo esc_attr($product->image); ?>">Edit</button>
                            <button class="bntm-btn-small bntm-btn-danger in-delete-product" data-id="<?php echo esc_attr($product->id); ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <style>
    .in-modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center}
    .in-modal-content{background:#fff;padding:30px;border-radius:8px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto}
    .bntm-upload-area{border:2px dashed #d1d5db;border-radius:8px;padding:30px;text-align:center;transition:all .3s;background:#f9fafb}
    .bntm-upload-area.dragover{border-color:#3b82f6;background:#eff6ff}
    .in-product-image-preview{position:relative;display:inline-block;margin-bottom:15px;padding:10px;border:2px solid #e5e7eb;border-radius:8px;background:#f9fafb}
    .in-product-image-preview img{max-width:200px;max-height:200px;display:block}
    .bntm-btn-remove-logo{position:absolute;top:-10px;right:-10px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:14px;box-shadow:0 2px 4px rgba(0,0,0,.2)}
    /* CHANGED: filter bar styles */
    .in-filter-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .in-filter-input{padding:7px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:200px}
    .in-filter-select{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;cursor:pointer}
    .in-unit-badge{display:inline-block;background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:600;padding:2px 6px;border-radius:4px;line-height:1.4}
    </style>

    <script>
    (function(){
        var uploadNonce='<?php echo $upload_nonce; ?>';

        // ── Image upload setup ──────────────────────────────────────────
        function setupImageUpload(prefix){
            const uploadArea=document.getElementById(prefix+'product-upload-area');
            const uploadBtn=document.getElementById(prefix+'product-upload-btn');
            const fileInput=document.getElementById(prefix+'product-image-upload');
            const preview=document.getElementById(prefix+'product-image-preview');
            const removeBtn=document.getElementById('remove-'+prefix+'product-image');
            const hidden=document.getElementById(prefix+'product_image');
            if(!uploadBtn)return;
            uploadBtn.addEventListener('click',()=>fileInput.click());
            fileInput.addEventListener('change',function(){if(this.files&&this.files[0])uploadImg(this.files[0],prefix);});
            uploadArea.addEventListener('dragover',e=>{e.preventDefault();uploadArea.classList.add('dragover');});
            uploadArea.addEventListener('dragleave',()=>uploadArea.classList.remove('dragover'));
            uploadArea.addEventListener('drop',e=>{e.preventDefault();uploadArea.classList.remove('dragover');if(e.dataTransfer.files&&e.dataTransfer.files[0])uploadImg(e.dataTransfer.files[0],prefix);});
            removeBtn.addEventListener('click',()=>{preview.style.display='none';uploadArea.style.display='block';hidden.value='';});
        }
        function uploadImg(file,prefix){
            if(!file.type.match('image.*'))return alert('Please select an image file');
            if(file.size>2*1024*1024)return alert('File size must be less than 2MB');
            const fd=new FormData();
            fd.append('action','in_upload_product_image');fd.append('image',file);fd.append('_ajax_nonce',uploadNonce);
            const btn=document.getElementById(prefix+'product-upload-btn');
            const area=document.getElementById(prefix+'product-upload-area');
            const preview=document.getElementById(prefix+'product-image-preview');
            const hidden=document.getElementById(prefix+'product_image');
            btn.disabled=true;btn.textContent='Uploading...';
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
                btn.disabled=false;btn.textContent='Choose Image';
                if(j.success){preview.querySelector('img').src=j.data.url;preview.style.display='inline-block';area.style.display='none';hidden.value=j.data.url;}
                else alert('Upload failed: '+j.data);
            }).catch(e=>{btn.disabled=false;btn.textContent='Choose Image';alert('Upload error: '+e.message);});
        }
        setupImageUpload('');
        setupImageUpload('edit-');

        // ── Modal controls ──────────────────────────────────────────────
        const addModal=document.getElementById('add-product-modal');
        const editModal=document.getElementById('edit-product-modal');
        document.getElementById('open-add-product-modal').addEventListener('click',()=>addModal.style.display='flex');
        document.querySelectorAll('.close-modal').forEach(b=>b.addEventListener('click',function(){this.closest('.in-modal').style.display='none';}));

        // ── Add product ─────────────────────────────────────────────────
        document.getElementById('in-add-product-form').addEventListener('submit',function(e){
            e.preventDefault();
            const fd=new FormData(this);fd.append('action','in_add_product');fd.append('nonce','<?php echo $nonce; ?>');
            const btn=this.querySelector('button[type="submit"]');btn.disabled=true;btn.textContent='Adding...';
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
                if(j.success){alert(j.data.message);location.reload();}
                else{alert(j.data.message);btn.disabled=false;btn.textContent='Add Product';}
            });
        });

        // ── Edit product ────────────────────────────────────────────────
        document.querySelectorAll('.in-edit-product').forEach(btn=>btn.addEventListener('click',function(){
            const d=this.dataset;
            document.getElementById('edit-product-id').value=d.id;
            document.getElementById('edit-name').value=d.name;
            document.getElementById('edit-sku').value=d.sku;
            document.getElementById('edit-barcode').value=d.barcode||'';
            document.getElementById('edit-inventory-type').value=d.type;
            document.getElementById('edit-unit').value=d.unit||'pc'; // CHANGED
            document.getElementById('edit-cost-per-unit').value=d.cost;
            document.getElementById('edit-selling-price').value=d.price;
            document.getElementById('edit-current-stock').value=d.stock;
            document.getElementById('edit-reorder-level').value=d.reorder;
            document.getElementById('edit-description').value=d.description;
            document.getElementById('edit-stock-adjustment').value='0';
            document.getElementById('edit-adjustment-type').value='add';
            const ep=document.getElementById('edit-product-image-preview');
            const ea=document.getElementById('edit-product-upload-area');
            const ei=document.getElementById('edit-product_image');
            if(d.image){ep.querySelector('img').src=d.image;ep.style.display='inline-block';ea.style.display='none';ei.value=d.image;}
            else{ep.style.display='none';ea.style.display='block';ei.value='';}
            editModal.style.display='flex';
        }));
        document.getElementById('in-edit-product-form').addEventListener('submit',function(e){
            e.preventDefault();
            const fd=new FormData(this);fd.append('action','in_update_product');fd.append('nonce','<?php echo $nonce; ?>');
            const btn=this.querySelector('button[type="submit"]');btn.disabled=true;btn.textContent='Updating...';
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
                if(j.success)location.reload();
                else{alert(j.data.message);btn.disabled=false;btn.textContent='Update Product';}
            });
        });

        // ── Delete product ──────────────────────────────────────────────
        document.querySelectorAll('.in-delete-product').forEach(btn=>btn.addEventListener('click',function(){
            if(!confirm('Are you sure you want to delete this product?'))return;
            const fd=new FormData();fd.append('action','in_delete_product');fd.append('product_id',this.getAttribute('data-id'));fd.append('nonce','<?php echo $nonce; ?>');
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
                if(j.success)location.reload();else alert(j.data.message);
            });
        }));

        // ── CHANGED: Live filtering ─────────────────────────────────────
        const searchInput  = document.getElementById('in-product-search');
        const typeFilter   = document.getElementById('in-type-filter');
        const unitFilter   = document.getElementById('in-unit-filter');
        const stockFilter  = document.getElementById('in-stock-filter');
        const clearBtn     = document.getElementById('in-clear-filters');
        const noResults    = document.getElementById('in-no-results');
        const tableBody    = document.querySelector('#in-products-table tbody');

        function applyFilters(){
            if(!tableBody)return;
            const search = (searchInput.value||'').toLowerCase().trim();
            const type   = typeFilter.value;
            const unit   = unitFilter.value;
            const stock  = stockFilter.value;
            let visible  = 0;
            tableBody.querySelectorAll('tr').forEach(row=>{
                const matchSearch = !search || (row.dataset.search||'').includes(search);
                const matchType   = !type   || row.dataset.type  === type;
                const matchUnit   = !unit   || row.dataset.unit  === unit;
                const matchStock  = !stock  || row.dataset.stockStatus === stock;
                const show = matchSearch && matchType && matchUnit && matchStock;
                row.style.display = show ? '' : 'none';
                if(show) visible++;
            });
            noResults.style.display = visible === 0 ? 'block' : 'none';
        }

        if(searchInput) searchInput.addEventListener('input', applyFilters);
        if(typeFilter)  typeFilter.addEventListener('change', applyFilters);
        if(unitFilter)  unitFilter.addEventListener('change', applyFilters);
        if(stockFilter) stockFilter.addEventListener('change', applyFilters);
        if(clearBtn) clearBtn.addEventListener('click',()=>{
            searchInput.value=''; typeFilter.value=''; unitFilter.value=''; stockFilter.value='';
            applyFilters();
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX: ADD PRODUCT ---------- */
function bntm_ajax_in_add_product() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table       = $wpdb->prefix . 'in_products';
    $business_id = get_current_user_id();

    // Limit check
    $limits = get_option('bntm_table_limits', []);
    if (isset($limits[$table]) && $limits[$table] > 0) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        if ($count >= $limits[$table]) wp_send_json_error(['message' => "Product limit reached. Maximum {$limits[$table]} products allowed."]);
    }

    $name          = sanitize_text_field($_POST['name']);
    $sku           = sanitize_text_field($_POST['sku']);
    $barcode       = sanitize_text_field($_POST['barcode']);
    $inventory_type= sanitize_text_field($_POST['inventory_type']);
    $unit          = sanitize_text_field($_POST['unit'] ?? 'pc'); // CHANGED
    $cost_per_unit = floatval($_POST['cost_per_unit']);
    $selling_price = floatval($_POST['selling_price']);
    $initial_stock = floatval($_POST['initial_stock']); // CHANGED: float for decimals
    $reorder_level = floatval($_POST['reorder_level']); // CHANGED
    $description   = sanitize_textarea_field($_POST['description']);
    $product_image = isset($_POST['product_image']) ? esc_url_raw($_POST['product_image']) : '';

    if (empty($sku))     $sku     = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
    if (empty($barcode)) $barcode = 'TMP-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE barcode = %s", $barcode));
    if ($exists) wp_send_json_error(['message' => 'Barcode already exists. Please use a unique barcode.']);

    $result = $wpdb->insert($table, [
        'rand_id'       => bntm_rand_id(),
        'business_id'   => $business_id,
        'name'          => $name,
        'sku'           => $sku,
        'barcode'       => $barcode,
        'inventory_type'=> $inventory_type,
        'unit'          => $unit, // CHANGED
        'cost_per_unit' => $cost_per_unit,
        'selling_price' => $selling_price,
        'stock_quantity'=> $initial_stock,
        'reorder_level' => $reorder_level,
        'description'   => $description,
        'image'         => $product_image
    ], ['%s','%d','%s','%s','%s','%s','%s','%f','%f','%f','%f','%s','%s']); // CHANGED format list

    if ($result) wp_send_json_success(['message' => 'Product added successfully!']);
    else         wp_send_json_error(['message' => 'Failed to add product.']);
}

/* ---------- AJAX: DELETE PRODUCT ---------- */
function bntm_ajax_in_delete_product() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table      = $wpdb->prefix . 'in_products';
    $product_id = intval($_POST['product_id']);
    $result     = $wpdb->delete($table, ['id' => $product_id], ['%d']);

    if ($result) wp_send_json_success(['message' => 'Product deleted successfully!']);
    else         wp_send_json_error(['message' => 'Failed to delete product.']);
}

/* ---------- AJAX: UPDATE PRODUCT ---------- */
function bntm_ajax_in_update_product() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    global $wpdb;
    $table      = $wpdb->prefix . 'in_products';
    $product_id = intval($_POST['product_id']);
    $barcode    = sanitize_text_field($_POST['barcode']);

    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $product_id));
    if (!$product) wp_send_json_error(['message' => 'Product not found.']);

    if (empty($barcode)) $barcode = 'TMP-' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    if (!empty($barcode) && $barcode !== $product->barcode) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE barcode = %s", $barcode));
        if ($exists) wp_send_json_error(['message' => 'Barcode already exists.']);
    }

    $stock_adjustment = floatval($_POST['stock_adjustment']); // CHANGED
    $adjustment_type  = sanitize_text_field($_POST['adjustment_type']);
    $current_stock    = floatval($product->stock_quantity);
    $new_stock        = $current_stock;

    if ($stock_adjustment != 0) {
        if      ($adjustment_type === 'add')      $new_stock = $current_stock + $stock_adjustment;
        elseif  ($adjustment_type === 'subtract') $new_stock = max(0, $current_stock - $stock_adjustment);
        elseif  ($adjustment_type === 'set')      $new_stock = max(0, $stock_adjustment);
    }

    $result = $wpdb->update(
        $table,
        [
            'name'          => sanitize_text_field($_POST['name']),
            'sku'           => sanitize_text_field($_POST['sku']),
            'barcode'       => $barcode,
            'inventory_type'=> sanitize_text_field($_POST['inventory_type']),
            'unit'          => sanitize_text_field($_POST['unit'] ?? 'pc'), // CHANGED
            'cost_per_unit' => floatval($_POST['cost_per_unit']),
            'selling_price' => floatval($_POST['selling_price']),
            'stock_quantity'=> $new_stock,
            'reorder_level' => floatval($_POST['reorder_level']), // CHANGED
            'description'   => sanitize_textarea_field($_POST['description']),
            'image'         => isset($_POST['product_image']) ? esc_url_raw($_POST['product_image']) : ''
        ],
        ['id' => $product_id],
        ['%s','%s','%s','%s','%s','%f','%f','%f','%f','%s','%s'], // CHANGED
        ['%d']
    );

    if ($result !== false) wp_send_json_success(['message' => 'Product updated successfully!']);
    else                   wp_send_json_error(['message' => 'Failed to update product.']);
}

/* ---------- AJAX: UPLOAD PRODUCT IMAGE ---------- */
function bntm_ajax_in_upload_product_image() {
    check_ajax_referer('in_upload_image', '_ajax_nonce');
    if (!is_user_logged_in()) wp_send_json_error('Unauthorized');
    if (!isset($_FILES['image'])) wp_send_json_error('No file uploaded');

    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $upload = wp_handle_upload($_FILES['image'], ['test_form' => false]);
    if (isset($upload['error'])) wp_send_json_error($upload['error']);
    wp_send_json_success(['url' => $upload['url']]);
}

/* ---------- BATCHES TAB ---------- */
function in_batches_tab($business_id) {
    global $wpdb;
    $products_table = $wpdb->prefix . 'in_products';
    $batches_table  = $wpdb->prefix . 'in_batches';
    
    $products = $wpdb->get_results("SELECT * FROM $products_table ORDER BY name ASC");
    $batches  = $wpdb->get_results(
        "SELECT b.*, p.name as product_name, p.unit as product_unit, p.cost_per_unit as product_cost
        FROM $batches_table b
        LEFT JOIN $products_table p ON b.product_id = p.id
        ORDER BY b.created_at DESC"
    );
    
    $nonce = wp_create_nonce('in_nonce');

    // Build distinct type list from products for filter
    $all_types = $wpdb->get_col("SELECT DISTINCT inventory_type FROM $products_table ORDER BY inventory_type ASC");

    ob_start();
    ?>
    <script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <!-- ── STOCK MOVEMENT FORM ───────────────────────────────────────── -->
    <div class="bntm-form-section">
        <h3>Stock Movement</h3>
        <form id="in-add-batch-form" class="bntm-form">
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Product *</label>
                    <select name="product_id" id="batch-product-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p->id; ?>"
                                data-cost="<?php echo $p->cost_per_unit; ?>"
                                data-unit="<?php echo esc_attr($p->unit ?? 'pc'); ?>">
                            <?php echo esc_html($p->name); ?>
                            (<?php echo esc_html($p->unit ?? 'pc'); ?>)
                            – Stock: <?php echo ($p->stock_quantity + 0); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bntm-form-group">
                    <label>Transaction Type *</label>
                    <select name="type" id="batch-type-select" required>
                        <option value="stock_in">Stock In (Receive/Produce)</option>
                        <option value="stock_out">Stock Out (Sell/Use)</option>
                    </select>
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <label>Reference Number</label>
                    <input type="text" name="reference_number" placeholder="e.g., PO-001, INV-001">
                </div>
                <div class="bntm-form-group">
                    <label>Date</label>
                    <input type="date" name="transaction_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="bntm-form-row">
                <div class="bntm-form-group">
                    <!-- CHANGED: unit shown next to quantity label -->
                    <label>Quantity * <span id="batch-unit-label" style="font-weight:400;color:#6b7280;"></span></label>
                    <input type="number" name="quantity" id="batch-quantity" min="0.001" step="0.001" required>
                </div>
                <div class="bntm-form-group">
                    <label>Cost Per Unit</label>
                    <input type="number" name="cost_per_unit" id="batch-cost-per-unit" step="0.01" min="0">
                    <small id="cost-hint">Select a product first</small>
                </div>
            </div>

            <div style="background:#e0f2fe;padding:15px;border-radius:6px;margin-top:15px;">
                <h3 style="margin:0;color:#0c4a6e;">Total Cost: ₱<span id="total-cost-display">0.00</span></h3>
                <p style="margin:5px 0 0;color:#075985;font-size:13px;">Quantity × Cost Per Unit</p>
            </div>

            <div class="bntm-form-group" style="margin-top:15px;">
                <label>Notes</label>
                <textarea name="notes" rows="3" placeholder="Additional information about this transaction"></textarea>
            </div>

            <button type="submit" class="bntm-btn-primary">Record Transaction</button>
        </form>
        <div id="batch-message"></div>
    </div>

    <!-- ── BATCH HISTORY with FILTER ─────────────────────────────────── -->
    <div class="bntm-form-section">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <h3 style="margin:0;">Stock Movement History (<?php echo count($batches); ?>)</h3>

            <!-- CHANGED: batch filter bar -->
            <div class="in-filter-bar">
                <input type="text" id="in-batch-search" placeholder="🔍 Search product / reference…" class="in-filter-input">
                <select id="in-batch-type-filter" class="in-filter-select">
                    <option value="">All Types</option>
                    <option value="stock_in">Stock In</option>
                    <option value="stock_out">Stock Out</option>
                </select>
                <select id="in-batch-unit-filter" class="in-filter-select">
                    <option value="">All Units</option>
                    <?php
                    $used_units = $wpdb->get_col("SELECT DISTINCT unit FROM $products_table WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC");
                    foreach ($used_units as $u): ?>
                    <option value="<?php echo esc_attr($u); ?>"><?php echo esc_html($u); ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="in-batch-clear-filters" class="bntm-btn-secondary" style="white-space:nowrap;">Clear</button>
            </div>
        </div>

        <div id="in-batch-no-results" style="display:none;padding:20px;text-align:center;color:#6b7280;">No transactions match your filters.</div>

        <?php if (empty($batches)): ?>
            <p>No stock movements recorded yet.</p>
        <?php else: ?>
        <div class="bntm-table-wrapper">
            <table class="bntm-table" id="in-batches-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Product</th>
                        <th>Unit</th><!-- CHANGED -->
                        <th>Quantity</th>
                        <th>Cost/Unit</th>
                        <th>Total Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $b): ?>
                    <tr data-batch-type="<?php echo esc_attr($b->type); ?>"
                        data-batch-unit="<?php echo esc_attr($b->product_unit ?? ''); ?>"
                        data-batch-search="<?php echo esc_attr(strtolower(($b->product_name ?? '') . ' ' . ($b->reference_number ?? '') . ' ' . $b->batch_code)); ?>">
                        <td><?php echo date('M d, Y', strtotime($b->manufacture_date ?: $b->created_at)); ?></td>
                        <td><?php if ($b->type === 'stock_in'): ?>
                            <span style="color:#059669;font-weight:500;">▲ Stock In</span>
                            <?php else: ?>
                            <span style="color:#dc2626;font-weight:500;">▼ Stock Out</span>
                            <?php endif; ?></td>
                        <td><?php echo esc_html($b->reference_number ?: $b->batch_code); ?></td>
                        <td><?php echo esc_html($b->product_name); ?></td>
                        <!-- CHANGED: unit column -->
                        <td><span class="in-unit-badge"><?php echo esc_html($b->product_unit ?? '—'); ?></span></td>
                        <td><?php echo esc_html($b->quantity + 0); ?> <small style="color:#6b7280;"><?php echo esc_html($b->product_unit ?? ''); ?></small></td>
                        <td>₱<?php echo number_format($b->cost_per_unit, 2); ?></td>
                        <td>₱<?php echo number_format($b->total_cost, 2); ?></td>
                        <td>
                            <button class="bntm-btn-small view-batch-details" data-details='<?php echo esc_attr(json_encode([
                                'type'         => $b->type,
                                'reference'    => $b->reference_number ?: $b->batch_code,
                                'product'      => $b->product_name,
                                'unit'         => $b->product_unit ?? 'pc',
                                'quantity'     => $b->quantity + 0,
                                'cost_per_unit'=> $b->cost_per_unit,
                                'total_cost'   => $b->total_cost,
                                'notes'        => $b->notes,
                                'date'         => $b->manufacture_date ?: $b->created_at,
                                'created_at'   => $b->created_at
                            ])); ?>'>View</button>
                            <button class="bntm-btn-small bntm-btn-danger in-delete-batch"
                                data-id="<?php echo $b->id; ?>"
                                data-type="<?php echo $b->type; ?>"
                                data-qty="<?php echo $b->quantity + 0; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Batch Details Modal -->
    <div id="batch-details-modal" class="in-modal" style="display:none;">
        <div class="in-modal-content">
            <h3>Transaction Details</h3>
            <div id="batch-details-content"></div>
            <button class="bntm-btn-secondary close-batch-modal" style="margin-top:20px;">Close</button>
        </div>
    </div>

    <style>
    .in-modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;display:flex;align-items:center;justify-content:center}
    .in-modal-content{background:#fff;padding:30px;border-radius:8px;max-width:700px;width:90%;max-height:90vh;overflow-y:auto}
    .in-filter-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .in-filter-input{padding:7px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-width:200px}
    .in-filter-select{padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#fff;cursor:pointer}
    .in-unit-badge{display:inline-block;background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:600;padding:2px 6px;border-radius:4px;line-height:1.4}
    </style>

    <script>
    (function(){
        // ── Load product cost + unit on select ──────────────────────────
        const productSel = document.getElementById('batch-product-select');
        const costInput  = document.getElementById('batch-cost-per-unit');
        const costHint   = document.getElementById('cost-hint');
        const unitLabel  = document.getElementById('batch-unit-label');

        productSel.addEventListener('change',function(){
            const opt  = this.options[this.selectedIndex];
            const cost = opt.getAttribute('data-cost') || '0';
            const unit = opt.getAttribute('data-unit') || 'pc';
            costInput.value = cost;
            costHint.textContent = 'Default cost: ₱'+parseFloat(cost).toFixed(2);
            unitLabel.textContent = '('+unit+')'; // CHANGED: show unit next to label
            updateTotal();
        });

        document.getElementById('batch-quantity').addEventListener('input', updateTotal);
        costInput.addEventListener('input', updateTotal);
        function updateTotal(){
            const q = parseFloat(document.getElementById('batch-quantity').value)||0;
            const c = parseFloat(costInput.value)||0;
            document.getElementById('total-cost-display').textContent = (q*c).toFixed(2);
        }

        // ── Add batch submit ────────────────────────────────────────────
        document.getElementById('in-add-batch-form').addEventListener('submit',function(e){
            e.preventDefault();
            const fd=new FormData(this);fd.append('action','in_add_batch');fd.append('nonce','<?php echo $nonce; ?>');
            const btn=this.querySelector('button[type="submit"]');
            btn.disabled=true;btn.textContent='Processing...';
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
                const msg=document.getElementById('batch-message');
                if(j.success){msg.innerHTML='<div class="bntm-notice bntm-notice-success">'+j.data.message+'</div>';setTimeout(()=>location.reload(),1500);}
                else{msg.innerHTML='<div class="bntm-notice bntm-notice-error">'+j.data.message+'</div>';btn.disabled=false;btn.textContent='Record Transaction';}
            });
        });

        // ── View details modal ──────────────────────────────────────────
        document.querySelectorAll('.view-batch-details').forEach(btn=>btn.addEventListener('click',function(){
            const d=JSON.parse(this.getAttribute('data-details'));
            const typeLabel=d.type==='stock_in'?
                '<span style="color:#059669;font-weight:500;">▲ Stock In</span>':
                '<span style="color:#dc2626;font-weight:500;">▼ Stock Out</span>';
            document.getElementById('batch-details-content').innerHTML=`
                <div style="background:#f9fafb;padding:20px;border-radius:6px;">
                    <p><strong>Type:</strong> ${typeLabel}</p>
                    <p><strong>Reference:</strong> ${d.reference}</p>
                    <p><strong>Product:</strong> ${d.product}</p>
                    <p><strong>Unit:</strong> <span class="in-unit-badge">${d.unit}</span></p>
                    <p><strong>Quantity:</strong> ${d.quantity} ${d.unit}</p>
                    <hr style="margin:15px 0;border:none;border-top:1px solid #e5e7eb;">
                    <p><strong>Cost Per Unit:</strong> ₱${parseFloat(d.cost_per_unit).toFixed(2)}</p>
                    <p style="font-size:16px;"><strong>Total Cost:</strong> ₱${parseFloat(d.total_cost).toFixed(2)}</p>
                    <hr style="margin:15px 0;border:none;border-top:1px solid #e5e7eb;">
                    <p><strong>Transaction Date:</strong> ${new Date(d.date).toLocaleDateString()}</p>
                    <p><strong>Recorded:</strong> ${new Date(d.created_at).toLocaleDateString()}</p>
                    ${d.notes?`<p style="margin-top:15px;"><strong>Notes:</strong><br>${d.notes}</p>`:''}
                </div>`;
            document.getElementById('batch-details-modal').style.display='flex';
        }));

        document.querySelector('.close-batch-modal').addEventListener('click',()=>document.getElementById('batch-details-modal').style.display='none');
        document.getElementById('batch-details-modal').addEventListener('click',function(e){if(e.target===this)this.style.display='none';});

        // ── Delete batch ────────────────────────────────────────────────
        document.querySelectorAll('.in-delete-batch').forEach(btn=>btn.addEventListener('click',function(){
            const type=this.getAttribute('data-type');
            const qty=this.getAttribute('data-qty');
            const action=type==='stock_in'?'reduce':'increase';
            if(!confirm(`Delete this transaction?\nThis will ${action} the product stock by ${qty}.`))return;
            const fd=new FormData();fd.append('action','in_delete_batch');fd.append('batch_id',this.getAttribute('data-id'));fd.append('nonce','<?php echo $nonce; ?>');
            this.disabled=true;this.textContent='Deleting...';
            fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
                if(j.success)location.reload();else{alert(j.data.message);this.disabled=false;this.textContent='Delete';}
            });
        }));

        // ── CHANGED: Batch filter ───────────────────────────────────────
        const bSearch   = document.getElementById('in-batch-search');
        const bType     = document.getElementById('in-batch-type-filter');
        const bUnit     = document.getElementById('in-batch-unit-filter');
        const bClear    = document.getElementById('in-batch-clear-filters');
        const bNoResult = document.getElementById('in-batch-no-results');
        const bBody     = document.querySelector('#in-batches-table tbody');

        function applyBatchFilters(){
            if(!bBody)return;
            const s=( bSearch.value||'').toLowerCase().trim();
            const t=bType.value;
            const u=bUnit.value;
            let vis=0;
            bBody.querySelectorAll('tr').forEach(row=>{
                const ms=!s||(row.dataset.batchSearch||'').includes(s);
                const mt=!t||row.dataset.batchType===t;
                const mu=!u||row.dataset.batchUnit===u;
                const show=ms&&mt&&mu;
                row.style.display=show?'':'none';
                if(show)vis++;
            });
            bNoResult.style.display=vis===0?'block':'none';
        }
        if(bSearch) bSearch.addEventListener('input', applyBatchFilters);
        if(bType)   bType.addEventListener('change', applyBatchFilters);
        if(bUnit)   bUnit.addEventListener('change', applyBatchFilters);
        if(bClear)  bClear.addEventListener('click',()=>{bSearch.value='';bType.value='';bUnit.value='';applyBatchFilters();});
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX: ADD BATCH ---------- */
function bntm_ajax_in_add_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized access.']);

    global $wpdb;
    $batches_table  = $wpdb->prefix . 'in_batches';
    $products_table = $wpdb->prefix . 'in_products';
    $business_id    = get_current_user_id();

    $product_id       = intval($_POST['product_id']);
    $type             = sanitize_text_field($_POST['type']);
    $reference_number = sanitize_text_field($_POST['reference_number']);
    $quantity         = floatval($_POST['quantity']); // CHANGED: float
    $cost_per_unit    = floatval($_POST['cost_per_unit']);
    $transaction_date = sanitize_text_field($_POST['transaction_date']);
    $notes            = sanitize_textarea_field($_POST['notes']);

    $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table WHERE id = %d", $product_id));
    if (!$product) wp_send_json_error(['message' => 'Product not found.']);

    if ($type === 'stock_out' && $product->stock_quantity < $quantity)
        wp_send_json_error(['message' => 'Insufficient stock. Available: ' . ($product->stock_quantity + 0) . ' ' . ($product->unit ?? 'pc')]);

    $total_cost = $quantity * $cost_per_unit;
    if (empty($reference_number))
        $reference_number = strtoupper($type) . '-' . date('Ymd') . '-' . substr(md5(uniqid()), 0, 6);

    $result = $wpdb->insert($batches_table, [
        'rand_id'          => bntm_rand_id(),
        'business_id'      => $business_id,
        'product_id'       => $product_id,
        'batch_code'       => $reference_number,
        'type'             => $type,
        'quantity'         => $quantity,
        'cost_per_unit'    => $cost_per_unit,
        'total_cost'       => $total_cost,
        'reference_number' => $reference_number,
        'manufacture_date' => $transaction_date,
        'notes'            => $notes
    ], ['%s','%d','%d','%s','%s','%f','%f','%f','%s','%s','%s']); // CHANGED: %f for quantity

    if ($result) {
        $col = $type === 'stock_in' ? '+' : '-';
        $wpdb->query($wpdb->prepare(
            "UPDATE $products_table SET stock_quantity = GREATEST(0, stock_quantity {$col} %f) WHERE id = %d",
            $quantity, $product_id
        ));
        $unit = $product->unit ?? 'pc';
        $msg  = $type === 'stock_in'
            ? "Stock added: {$quantity} {$unit} received."
            : "Stock removed: {$quantity} {$unit} deducted.";
        wp_send_json_success(['message' => $msg]);
    } else {
        wp_send_json_error(['message' => 'Failed to record transaction.']);
    }
}

/* ---------- AJAX: DELETE BATCH ---------- */
function bntm_ajax_in_delete_batch() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized access.']);

    global $wpdb;
    $batches_table  = $wpdb->prefix . 'in_batches';
    $products_table = $wpdb->prefix . 'in_products';
    $business_id    = get_current_user_id();
    $batch_id       = intval($_POST['batch_id']);

    $batch = $wpdb->get_row($wpdb->prepare("SELECT * FROM $batches_table WHERE id = %d", $batch_id));
    if (!$batch) wp_send_json_error(['message' => 'Transaction not found.']);

    $result = $wpdb->delete($batches_table, ['id' => $batch_id, 'business_id' => $business_id], ['%d','%d']);

    if ($result) {
        $col = $batch->type === 'stock_in' ? '-' : '+';
        $wpdb->query($wpdb->prepare(
            "UPDATE $products_table SET stock_quantity = GREATEST(0, stock_quantity {$col} %f) WHERE id = %d",
            floatval($batch->quantity), $batch->product_id
        ));
        wp_send_json_success(['message' => 'Transaction deleted. Stock updated.']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete transaction.']);
    }
}

/* ---------- IMPORT TAB ---------- */
function in_import_tab($business_id) {
    global $wpdb;
    $batches_table  = $wpdb->prefix . 'in_batches';
    $txn_table      = $wpdb->prefix . 'fn_transactions';
    $products_table = $wpdb->prefix . 'in_products';
    
    $batches = $wpdb->get_results($wpdb->prepare("
        SELECT b.*, p.name as product_name, p.unit as product_unit,
        (SELECT COUNT(*) FROM {$txn_table} WHERE reference_type='inventory_batch' AND reference_id=b.id) as is_imported
        FROM {$batches_table} b
        LEFT JOIN {$products_table} p ON b.product_id = p.id
        WHERE b.type = 'stock_in' AND b.total_cost > 0
        ORDER BY b.created_at DESC
    ", $business_id));
    
    $nonce = wp_create_nonce('in_nonce');
    ob_start();
    ?>
    <script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
    
    <div class="bntm-form-section">
        <h3>Import Stock Purchases to Finance</h3>
        <p>Import stock-in transactions with costs as expense records in the Finance module.</p>
        
        <div style="margin-bottom:15px;">
            <label style="cursor:pointer;margin-right:20px;"><input type="checkbox" id="select-all-not-imported"> <strong>Select All (Not Imported)</strong></label>
            <label style="cursor:pointer;"><input type="checkbox" id="select-all-imported"> <strong>Select All (Imported)</strong></label>
        </div>
        <div style="margin-bottom:15px;">
            <button id="bulk-import-btn" class="bntm-btn-primary" data-nonce="<?php echo $nonce; ?>" style="margin-right:10px;">Import Selected</button>
            <button id="bulk-revert-btn" class="bntm-btn-secondary" data-nonce="<?php echo $nonce; ?>">Revert Selected</button>
            <span id="selected-count" style="margin-left:15px;color:#6b7280;"></span>
        </div>

        <div class="bntm-table-wrapper">
            <table class="bntm-table">
                <thead><tr><th width="40"></th><th>Date</th><th>Reference</th><th>Product</th><th>Unit</th><th>Quantity</th><th>Cost/Unit</th><th>Total Cost</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                    <tr><td colspan="9" style="text-align:center;">No stock-in transactions with costs found</td></tr>
                    <?php else: foreach ($batches as $b): ?>
                    <tr>
                        <td><input type="checkbox"
                            class="batch-checkbox <?php echo $b->is_imported ? 'imported-batch' : 'not-imported-batch'; ?>"
                            data-id="<?php echo $b->id; ?>"
                            data-amount="<?php echo $b->total_cost; ?>"
                            data-ref="<?php echo esc_attr($b->reference_number ?: $b->batch_code); ?>"
                            data-product="<?php echo esc_attr($b->product_name); ?>"
                            data-qty="<?php echo $b->quantity + 0; ?>"
                            data-unit="<?php echo esc_attr($b->product_unit ?? 'pc'); ?>"
                            data-cost="<?php echo $b->cost_per_unit; ?>"
                            data-imported="<?php echo $b->is_imported ? '1' : '0'; ?>"></td>
                        <td><?php echo date('M d, Y', strtotime($b->manufacture_date ?: $b->created_at)); ?></td>
                        <td><?php echo esc_html($b->reference_number ?: $b->batch_code); ?></td>
                        <td><?php echo esc_html($b->product_name); ?></td>
                        <td><span class="in-unit-badge"><?php echo esc_html($b->product_unit ?? '—'); ?></span></td>
                        <td><?php echo esc_html($b->quantity + 0); ?> <?php echo esc_html($b->product_unit ?? ''); ?></td>
                        <td>₱<?php echo number_format($b->cost_per_unit, 2); ?></td>
                        <td class="bntm-stat-expense">₱<?php echo number_format($b->total_cost, 2); ?></td>
                        <td><?php if ($b->is_imported): ?><span style="color:#059669;">✓ Imported</span><?php else: ?><span style="color:#6b7280;">Not Imported</span><?php endif; ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <style>
    .in-unit-badge{display:inline-block;background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:600;padding:2px 6px;border-radius:4px;line-height:1.4}
    </style>
    <script>
    (function(){
        const nonce='<?php echo $nonce; ?>';
        function updateCount(){const n=document.querySelectorAll('.batch-checkbox:checked').length;document.getElementById('selected-count').textContent=n>0?n+' selected':'';}
        document.getElementById('select-all-not-imported').addEventListener('change',function(){document.querySelectorAll('.not-imported-batch').forEach(c=>c.checked=this.checked);if(this.checked)document.getElementById('select-all-imported').checked=false;updateCount();});
        document.getElementById('select-all-imported').addEventListener('change',function(){document.querySelectorAll('.imported-batch').forEach(c=>c.checked=this.checked);if(this.checked)document.getElementById('select-all-not-imported').checked=false;updateCount();});
        document.querySelectorAll('.batch-checkbox').forEach(c=>c.addEventListener('change',updateCount));

        document.getElementById('bulk-import-btn').addEventListener('click',function(){
            const sel=Array.from(document.querySelectorAll('.batch-checkbox:checked')).filter(c=>c.dataset.imported==='0');
            if(!sel.length)return alert('Please select at least one batch that is not imported');
            const total=sel.reduce((s,c)=>s+parseFloat(c.dataset.amount),0);
            if(!confirm(`Import ${sel.length} stock purchase(s) as expenses?\nTotal: ₱${total.toFixed(2)}`))return;
            this.disabled=true;this.textContent='Importing...';
            let done=0;
            sel.forEach(c=>{
                const fd=new FormData();fd.append('action','in_import_batch_expense');fd.append('batch_id',c.dataset.id);fd.append('amount',c.dataset.amount);fd.append('reference',c.dataset.ref);fd.append('product',c.dataset.product);fd.append('quantity',c.dataset.qty);fd.append('unit',c.dataset.unit);fd.append('cost_per_unit',c.dataset.cost);fd.append('nonce',nonce);
                fetch(ajaxurl,{method:'POST',body:fd}).finally(()=>{done++;if(done===sel.length){alert('Imported '+sel.length+' purchase(s)');location.reload();}});
            });
        });

        document.getElementById('bulk-revert-btn').addEventListener('click',function(){
            const sel=Array.from(document.querySelectorAll('.batch-checkbox:checked')).filter(c=>c.dataset.imported==='1');
            if(!sel.length)return alert('Please select at least one imported batch');
            if(!confirm(`Remove ${sel.length} purchase(s) from Finance?`))return;
            this.disabled=true;this.textContent='Reverting...';
            let done=0;
            sel.forEach(c=>{
                const fd=new FormData();fd.append('action','in_revert_batch_expense');fd.append('batch_id',c.dataset.id);fd.append('nonce',nonce);
                fetch(ajaxurl,{method:'POST',body:fd}).finally(()=>{done++;if(done===sel.length){alert('Reverted '+sel.length+' purchase(s)');location.reload();}});
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- SETTINGS TAB ---------- */
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
                <label>Default Unit of Measure</label>
                <select name="default_unit">
                    <?php echo bntm_in_unit_options(bntm_get_setting('in_default_unit', 'pc')); ?>
                </select>
                <small>Default unit applied to new products</small>
            </div>
            <div class="bntm-form-group">
                <label>Low Stock Alert Email</label>
                <input type="email" name="low_stock_email" value="<?php echo esc_attr(bntm_get_setting('in_low_stock_email', '')); ?>" placeholder="your@email.com">
            </div>
            <div class="bntm-form-group">
                <label>Currency</label>
                <select name="currency">
                    <option value="PHP" <?php selected(bntm_get_setting('in_currency','PHP'),'PHP'); ?>>PHP – Philippine Peso</option>
                    <option value="USD" <?php selected(bntm_get_setting('in_currency','PHP'),'USD'); ?>>USD – US Dollar</option>
                    <option value="EUR" <?php selected(bntm_get_setting('in_currency','PHP'),'EUR'); ?>>EUR – Euro</option>
                </select>
            </div>
            <button type="submit" class="bntm-btn-primary">Save Settings</button>
            <div id="settings-message"></div>
        </form>
    </div>
    <script>
    var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';
    document.getElementById('in-settings-form').addEventListener('submit',function(e){
        e.preventDefault();
        const fd=new FormData(this);fd.append('action','in_save_settings');fd.append('nonce','<?php echo $nonce; ?>');
        const btn=this.querySelector('button[type="submit"]');btn.disabled=true;btn.textContent='Saving...';
        fetch(ajaxurl,{method:'POST',body:fd}).then(r=>r.json()).then(j=>{
            document.getElementById('settings-message').innerHTML='<div class="bntm-notice bntm-notice-'+(j.success?'success':'error')+'">'+j.data.message+'</div>';
            btn.disabled=false;btn.textContent='Save Settings';
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

/* ---------- AJAX HANDLERS ---------- */
function bntm_ajax_in_import_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Please log in.']);

    global $wpdb;
    $txn_table    = $wpdb->prefix . 'fn_transactions';
    $batch_id     = intval($_POST['batch_id']);
    $amount       = floatval($_POST['amount']);
    $reference    = sanitize_text_field($_POST['reference']);
    $product      = sanitize_text_field($_POST['product']);
    $quantity     = sanitize_text_field($_POST['quantity']);
    $unit         = sanitize_text_field($_POST['unit'] ?? 'pc'); // CHANGED
    $cost_per_unit= sanitize_text_field($_POST['cost_per_unit']);

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$txn_table} WHERE reference_type='inventory_batch' AND reference_id=%d", $batch_id));
    if ($exists) wp_send_json_error(['message' => 'Already imported.']);

    $notes = "Inventory Stock Purchase\nReference: {$reference}\nProduct: {$product}\nQuantity: {$quantity} {$unit} @ ₱{$cost_per_unit}"; // CHANGED

    $result = $wpdb->insert($txn_table, [
        'rand_id'        => bntm_rand_id(),
        'business_id'    => get_current_user_id(),
        'type'           => 'expense',
        'amount'         => $amount,
        'category'       => 'Inventory Purchase',
        'notes'          => $notes,
        'reference_type' => 'inventory_batch',
        'reference_id'   => $batch_id
    ]);

    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) bntm_fn_update_cashflow_summary();
        wp_send_json_success(['message' => 'Imported to Finance successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to import.']);
    }
}

function bntm_ajax_in_revert_batch_expense() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Please log in.']);

    global $wpdb;
    $table    = $wpdb->prefix . 'fn_transactions';
    $batch_id = intval($_POST['batch_id']);

    $result = $wpdb->delete($table, [
        'reference_type' => 'inventory_batch',
        'reference_id'   => $batch_id,
        'business_id'    => get_current_user_id()
    ], ['%s','%d','%d']);

    if ($result) {
        if (function_exists('bntm_fn_update_cashflow_summary')) bntm_fn_update_cashflow_summary();
        wp_send_json_success(['message' => 'Removed from Finance.']);
    } else {
        wp_send_json_error(['message' => 'Failed to revert.']);
    }
}

function bntm_ajax_in_save_settings() {
    check_ajax_referer('in_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Unauthorized']);

    bntm_update_setting('in_default_reorder_level', intval($_POST['default_reorder_level']));
    bntm_update_setting('in_default_unit',          sanitize_text_field($_POST['default_unit'] ?? 'pc')); // CHANGED
    bntm_update_setting('in_low_stock_email',       sanitize_email($_POST['low_stock_email']));
    bntm_update_setting('in_currency',              sanitize_text_field($_POST['currency']));

    wp_send_json_success(['message' => 'Settings saved successfully!']);
}