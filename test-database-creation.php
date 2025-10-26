<?php
/**
 * Venus Member System - Database Creation Test Script
 *
 * 訪問此腳本來手動測試資料表創建
 * URL: http://your-site.com/wp-content/plugins/wp-blk-subscriber/test-database-creation.php
 */

// 載入 WordPress
require_once('../../../wp-load.php');

// 檢查是否為管理員
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

echo '<h1>Venus Member System - Database Creation Test</h1>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>';

echo '<h2>Step 1: Loading Dependencies</h2>';
if (file_exists(__DIR__ . '/venus-database.php')) {
    require_once __DIR__ . '/venus-database.php';
    echo '<p class="success">✅ venus-database.php loaded successfully</p>';
} else {
    echo '<p class="error">❌ venus-database.php not found</p>';
    die();
}

echo '<h2>Step 2: Loading dbDelta Function</h2>';
if (!function_exists('dbDelta')) {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    echo '<p class="success">✅ dbDelta function loaded</p>';
} else {
    echo '<p class="success">✅ dbDelta function already available</p>';
}

echo '<h2>Step 3: Database Connection Check</h2>';
global $wpdb;
echo '<p class="info">Database name: ' . DB_NAME . '</p>';
echo '<p class="info">Table prefix: ' . $wpdb->prefix . '</p>';
echo '<p class="info">Charset: ' . $wpdb->get_charset_collate() . '</p>';

// 測試資料庫連接
$test_query = $wpdb->get_var("SELECT 1");
if ($test_query === '1') {
    echo '<p class="success">✅ Database connection working</p>';
} else {
    echo '<p class="error">❌ Database connection failed</p>';
}

echo '<h2>Step 4: Check Existing Tables</h2>';
$existing_tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}venus_%'");
if (empty($existing_tables)) {
    echo '<p class="info">No Venus Member tables found (expected if deleted)</p>';
} else {
    echo '<p class="info">Found existing tables:</p>';
    echo '<ul>';
    foreach ($existing_tables as $table) {
        $table_name = array_values((array)$table)[0];
        echo '<li>' . $table_name . '</li>';
    }
    echo '</ul>';
}

echo '<h2>Step 5: Create Database Instance</h2>';
try {
    $database = new VenusDatabaseTables();
    echo '<p class="success">✅ VenusDatabaseTables instance created</p>';
} catch (Exception $e) {
    echo '<p class="error">❌ Failed to create instance: ' . $e->getMessage() . '</p>';
    die();
}

echo '<h2>Step 6: Creating Tables</h2>';
ob_start();
$result = $database->createAllTables();
$output = ob_get_clean();

echo '<p class="info">Creation result:</p>';
echo '<pre>' . htmlspecialchars($result) . '</pre>';

if (!empty($output)) {
    echo '<p class="info">Output during creation:</p>';
    echo '<pre>' . htmlspecialchars($output) . '</pre>';
}

// 檢查最後的錯誤
if (!empty($wpdb->last_error)) {
    echo '<p class="error">Database Error: ' . htmlspecialchars($wpdb->last_error) . '</p>';
}

echo '<h2>Step 7: Verify Tables Created</h2>';
$tables_status = $database->checkTablesExist();
echo '<ul>';
foreach ($tables_status as $table => $exists) {
    if ($exists) {
        echo '<li class="success">✅ ' . $table . ' - EXISTS</li>';
    } else {
        echo '<li class="error">❌ ' . $table . ' - MISSING</li>';
    }
}
echo '</ul>';

echo '<h2>Step 8: Check Table Structure</h2>';
$table_name = $wpdb->prefix . 'venus_member_applications';
$columns = $wpdb->get_results("SHOW COLUMNS FROM `$table_name`");
if ($columns) {
    echo '<p class="success">✅ Table structure for venus_member_applications:</p>';
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>';
    foreach ($columns as $column) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($column->Field) . '</td>';
        echo '<td>' . htmlspecialchars($column->Type) . '</td>';
        echo '<td>' . htmlspecialchars($column->Null) . '</td>';
        echo '<td>' . htmlspecialchars($column->Default) . '</td>';
        echo '</tr>';
    }
    echo '</table>';

    // 檢查新欄位
    $required_columns = ['applicant_type', 'company_name', 'company_tax_id', 'company_establish_date',
                         'company_address', 'contact_address', 'principal_name', 'contact_person_name',
                         'company_phone', 'individual_name', 'gender', 'id_number', 'birth_date', 'mobile_phone'];

    $existing_columns = array_map(function($col) { return $col->Field; }, $columns);
    echo '<h3>New Columns Check:</h3>';
    echo '<ul>';
    foreach ($required_columns as $col) {
        if (in_array($col, $existing_columns)) {
            echo '<li class="success">✅ ' . $col . '</li>';
        } else {
            echo '<li class="error">❌ ' . $col . ' - MISSING</li>';
        }
    }
    echo '</ul>';
} else {
    echo '<p class="error">❌ Could not retrieve table structure</p>';
    if (!empty($wpdb->last_error)) {
        echo '<p class="error">Error: ' . htmlspecialchars($wpdb->last_error) . '</p>';
    }
}

echo '<h2>Step 9: Database Version Options</h2>';
$plugin_version = get_option('venus_member_version', 'not set');
$db_version = get_option('venus_member_db_version', 'not set');
echo '<p class="info">Plugin Version: ' . $plugin_version . '</p>';
echo '<p class="info">Database Version: ' . $db_version . '</p>';

echo '<hr>';
echo '<p><strong>Test completed!</strong></p>';
echo '<p>如果看到錯誤，請複製上面的訊息給開發者。</p>';
