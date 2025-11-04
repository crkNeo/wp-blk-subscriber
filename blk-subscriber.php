<?php
/**
 * Plugin Name: Venus Member System
 * Plugin URI: https://your-website.com/venus-member-system
 * Description: A comprehensive membership application system integrated with WooCommerce. Provides member application flow, document upload, consent management, and admin review functionality.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * Text Domain: venus-member
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VENUS_MEMBER_VERSION', '1.0.0');
define('VENUS_MEMBER_DB_VERSION', '1.2.0'); // Database version
define('VENUS_MEMBER_PLUGIN_FILE', __FILE__);
define('VENUS_MEMBER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VENUS_MEMBER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VENUS_MEMBER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WordPress is loaded
if (!function_exists('add_action')) {
    exit;
}

class Venus_Member_System
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_hooks();
        $this->load_dependencies();
    }

    private function init_hooks()
    {
        register_activation_hook(VENUS_MEMBER_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(VENUS_MEMBER_PLUGIN_FILE, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'check_database_version'));
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_consent_button_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));

        add_filter('woocommerce_account_menu_items', array($this, 'add_venus_member_menu_items'));
        add_action('woocommerce_account_venus-documents_endpoint', array($this, 'venus_documents_content'));

        add_action('init', array($this, 'add_endpoints'));
        add_action('wp_loaded', array($this, 'add_shortcodes'));

        add_action('wp_ajax_venus_submit_consent', array($this, 'handle_consent_submission'));
        add_action('wp_ajax_venus_sign_document', array($this, 'handle_sign_document'));
        add_action('wp_ajax_venus_upload_document', array($this, 'handle_document_upload'));
        add_action('wp_ajax_venus_delete_document', array($this, 'handle_document_delete'));
        add_action('wp_ajax_venus_get_application_details', array($this, 'handle_get_application_details'));
        add_action('wp_ajax_venus_download_document', array($this, 'handle_download_document'));
        add_action('wp_ajax_venus_save_applicant_info', array($this, 'handle_save_applicant_info'));

        add_action('wp_footer', array($this, 'add_vip_member_button_logic'));

        // 暫時註解掉，等資料表建立後再啟用
        // add_action('admin_init', array($this, 'check_database_tables'));
        // add_action('admin_notices', array($this, 'database_missing_notice'));
    }

    private function load_dependencies()
    {
        require_once VENUS_MEMBER_PLUGIN_DIR . 'venus-database.php';
        require_once VENUS_MEMBER_PLUGIN_DIR . 'api.php';
    }

    public function activate()
    {
        // 載入必要的WordPress函數
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // 強制載入資料庫類別
        require_once VENUS_MEMBER_PLUGIN_DIR . 'venus-database.php';

        try {
            $database = new VenusDatabaseTables();

            // 檢查數據庫版本
            $installed_db_version = get_option('venus_member_db_version', '0');

            // 記錄開始建立/更新資料表
            error_log('Venus Member System: Starting database table creation/update...');
            error_log('Venus Member System: Installed DB version: ' . $installed_db_version);
            error_log('Venus Member System: Current DB version: ' . VENUS_MEMBER_DB_VERSION);

            // 先執行 dbDelta 來建立或更新表結構
            $result = $database->createAllTables();

            // 記錄建立結果
            error_log('Venus Member System: Database creation result - ' . $result);

            // 如果表格已存在，再執行 ALTER TABLE 確保所有新欄位都被添加
            global $wpdb;
            $table_name = $wpdb->prefix . 'venus_member_applications';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

            if ($table_exists) {
                error_log('Venus Member System: Table exists, checking for missing columns...');
                $this->upgrade_database_schema();
            }

            // 檢查表格是否真的建立成功
            $tables_exist = $database->checkTablesExist();
            $missing_tables = array();

            foreach ($tables_exist as $table => $exists) {
                if (!$exists) {
                    $missing_tables[] = $table;
                }
            }

            if (!empty($missing_tables)) {
                $error_msg = 'Some tables failed to create: ' . implode(', ', $missing_tables);
                error_log('Venus Member System: ' . $error_msg);

                // 設定管理員通知
                update_option('venus_member_needs_repair', true);
                update_option('venus_member_missing_tables', $missing_tables);

                // 顯示管理員通知
                add_action('admin_notices', function () use ($missing_tables) {
                    if (current_user_can('manage_options')) {
                        echo '<div class="error notice">';
                        echo '<p><strong>Venus Member System:</strong> 部分資料表建立失敗！</p>';
                        echo '<p>缺少的表格：' . implode(', ', $missing_tables) . '</p>';
                        echo '<p>請執行測試腳本檢查問題：<code>' . plugin_dir_url(__FILE__) . 'test-database-creation.php</code></p>';
                        echo '</div>';
                    }
                });
            } else {
                error_log('Venus Member System: All tables created successfully');
                delete_option('venus_member_needs_repair');
                delete_option('venus_member_missing_tables');
            }

        } catch (Exception $e) {
            $error_msg = 'Venus Member System activation error: ' . $e->getMessage();
            error_log($error_msg);
            error_log('Stack trace: ' . $e->getTraceAsString());

            update_option('venus_member_needs_repair', true);
            update_option('venus_member_activation_error', $error_msg);

            // 顯示錯誤通知
            add_action('admin_notices', function () use ($error_msg) {
                if (current_user_can('manage_options')) {
                    echo '<div class="error notice">';
                    echo '<p><strong>Venus Member System 啟用錯誤:</strong></p>';
                    echo '<p>' . esc_html($error_msg) . '</p>';
                    echo '<p>請檢查錯誤日誌或執行測試腳本：<code>' . plugin_dir_url(__FILE__) . 'test-database-creation.php</code></p>';
                    echo '</div>';
                }
            });
        }

        $this->add_endpoints();
        flush_rewrite_rules();

        // 設定插件版本選項，用於將來的更新檢查
        update_option('venus_member_version', VENUS_MEMBER_VERSION);
        update_option('venus_member_db_version', VENUS_MEMBER_DB_VERSION);

        error_log('Venus Member System: Database version updated to ' . VENUS_MEMBER_DB_VERSION);
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    public function check_database_version()
    {
        global $wpdb;
        $installed_db_version = get_option('venus_member_db_version', '0');

        // 如果數據庫版本不匹配，自動升級
        if (version_compare($installed_db_version, VENUS_MEMBER_DB_VERSION, '<')) {
            error_log('Venus Member System: Database version mismatch. Upgrading from ' . $installed_db_version . ' to ' . VENUS_MEMBER_DB_VERSION);

            // 載入必要的WordPress函數
            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }

            // 執行數據庫升級
            require_once VENUS_MEMBER_PLUGIN_DIR . 'venus-database.php';
            $database = new VenusDatabaseTables();

            // 先執行 ALTER TABLE 添加新欄位（如果不存在）
            $this->upgrade_database_schema();

            // 然後執行 dbDelta 確保完整結構
            $result = $database->createAllTables();

            // 更新數據庫版本
            update_option('venus_member_db_version', VENUS_MEMBER_DB_VERSION);

            error_log('Venus Member System: Database upgraded successfully - ' . $result);

            // 添加管理員通知
            add_action('admin_notices', function () {
                if (current_user_can('manage_options')) {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>Venus Member System:</strong> 數據庫已自動升級到版本 ' . VENUS_MEMBER_DB_VERSION . '</p>';
                    echo '</div>';
                }
            });
        }
    }

    /**
     * 使用 ALTER TABLE 升級資料庫架構
     * 確保新欄位被添加到現有表格
     */
    private function upgrade_database_schema()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'venus_member_applications';

        // 檢查表格是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('Venus Member System: Table does not exist, will be created by dbDelta');
            return;
        }

        error_log('Venus Member System: Upgrading database schema for table: ' . $table_name);

        // 定義需要添加的新欄位
        $new_columns = array(
            'applicant_type' => "VARCHAR(20) NOT NULL DEFAULT 'individual' AFTER application_status",
            'company_name' => "VARCHAR(200) DEFAULT NULL AFTER applicant_type",
            'company_tax_id' => "VARCHAR(50) DEFAULT NULL AFTER company_name",
            'company_establish_date' => "DATE DEFAULT NULL AFTER company_tax_id",
            'company_address' => "TEXT DEFAULT NULL AFTER company_establish_date",
            'contact_address' => "TEXT DEFAULT NULL AFTER company_address",
            'principal_name' => "VARCHAR(100) DEFAULT NULL AFTER contact_address",
            'contact_person_name' => "VARCHAR(100) DEFAULT NULL AFTER principal_name",
            'company_phone' => "VARCHAR(50) DEFAULT NULL AFTER contact_person_name",
            'individual_name' => "VARCHAR(100) DEFAULT NULL AFTER company_phone",
            'gender' => "VARCHAR(10) DEFAULT NULL AFTER individual_name",
            'id_number' => "VARCHAR(50) DEFAULT NULL AFTER gender",
            'birth_date' => "DATE DEFAULT NULL AFTER id_number",
            'mobile_phone' => "VARCHAR(50) DEFAULT NULL AFTER birth_date",
            'referrer_email' => "VARCHAR(255) DEFAULT NULL AFTER mobile_phone",
            'referrer_user_id' => "BIGINT(20) UNSIGNED DEFAULT NULL AFTER referrer_email"
        );

        // 檢查每個欄位是否存在，不存在則添加
        foreach ($new_columns as $column_name => $column_definition) {
            $column_exists = $wpdb->get_results(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM `$table_name` LIKE %s",
                    $column_name
                )
            );

            if (empty($column_exists)) {
                $sql = "ALTER TABLE `$table_name` ADD COLUMN `$column_name` $column_definition";
                $result = $wpdb->query($sql);

                if ($result !== false) {
                    error_log("Venus Member System: Added column '$column_name' to table '$table_name'");
                } else {
                    error_log("Venus Member System: Failed to add column '$column_name' - " . $wpdb->last_error);
                }
            } else {
                error_log("Venus Member System: Column '$column_name' already exists");
            }
        }

        // 添加索引（如果不存在）
        $indexes = array(
            'applicant_type' => 'applicant_type',
            'referrer_email' => 'referrer_email',
            'referrer_user_id' => 'referrer_user_id'
        );

        foreach ($indexes as $index_name => $column_name) {
            $index_exists = $wpdb->get_results("SHOW INDEX FROM `$table_name` WHERE Key_name = '$index_name'");
            if (empty($index_exists)) {
                $wpdb->query("ALTER TABLE `$table_name` ADD INDEX `$index_name` (`$column_name`)");
                error_log("Venus Member System: Added index for '$column_name' column");
            }
        }
    }

    public function init()
    {
        load_plugin_textdomain('venus-member', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('venus-member-js', VENUS_MEMBER_PLUGIN_URL . 'js.js', array('jquery'), VENUS_MEMBER_VERSION, true);

        wp_localize_script('venus-member-js', 'venus_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('venus_member_nonce'),
            'messages' => array(
                'loading' => 'Processing...',
                'error' => 'An error occurred, please try again',
                'success' => 'Operation successful'
            )
        ));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Venus Member System',
            'Venus Members',
            'manage_options',
            'venus-member-admin',
            array($this, 'admin_page'),
            'dashicons-groups',
            30
        );
    }

    public function admin_page()
    {
        include VENUS_MEMBER_PLUGIN_DIR . 'admin-page.php';
    }

    public function add_endpoints()
    {
        add_rewrite_endpoint('venus-documents', EP_ROOT | EP_PAGES);
    }

    public function add_shortcodes()
    {
        add_shortcode('venus_consent_form', array($this, 'venus_consent_form_shortcode'));
    }

    public function add_venus_member_menu_items($items)
    {
        $new_items = array();

        foreach ($items as $key => $item) {
            $new_items[$key] = $item;

            if ($key === 'dashboard') {
                if ($this->user_has_application()) {
                    $new_items['venus-documents'] = '身份證明上傳';
                }
            }
        }

        return $new_items;
    }

    public function venus_consent_form_shortcode($atts)
    {
        // 確保依賴項已載入
        if (!class_exists('VenusDatabaseTables')) {
            return '<p>Venus Member System is loading...</p>';
        }

        $atts = shortcode_atts(array(
            'show_login_check' => 'true'
        ), $atts, 'venus_consent_form');

        try {
            ob_start();
            $this->display_consent_form($atts['show_login_check'] === 'true');
            return ob_get_clean();
        } catch (Exception $e) {
            error_log('Venus Consent Form Shortcode Error: ' . $e->getMessage());
            return '<p>Error loading consent form. Please try again later.</p>';
        }
    }

    public function venus_documents_content()
    {
        $this->display_document_upload();
    }

    public function add_vip_member_button_logic()
    {
        ?>
        <script>
            jQuery(document).ready(function ($) {
                // 綁定到前台的 vip-member 按鈕
                $('[title="vip-member"], .vip-member, #vip-member').on('click', function (e) {
                    e.preventDefault();

                    var $btn = $(this);
                    var originalText = $btn.text();
                    var originalHtml = $btn.html();

                    // 檢查用戶是否已登入
                    <?php if (!is_user_logged_in()): ?>
                    // 未登入用戶 → 跳轉到 WooCommerce 註冊頁面
                    window.location.href = '<?php echo home_url('/my-account/'); ?>';
                    return;
                    <?php else: ?>
                    // 已登入用戶 → 檢查是否已有申請
                    <?php if ($this->user_has_application()): ?>
                    // 已有申請 → 跳轉到文件上傳頁面
                    window.location.href = '<?php echo wc_get_account_endpoint_url('venus-documents'); ?>';
                    return;
                    <?php else: ?>
                    // 沒有申請 → 跳轉到會員權益書頁面
                    window.location.href = '<?php echo home_url('/consent-form/'); ?>';
                    return;
                    <?php endif; ?>
                    <?php endif; ?>
                });

                // 也可以通過 data 屬性來識別按鈕
                $('[data-venus-action="apply-membership"]').on('click', function (e) {
                    e.preventDefault();

                    <?php if (!is_user_logged_in()): ?>
                    window.location.href = '<?php echo home_url('/my-account/'); ?>';
                    <?php else: ?>
                    <?php if ($this->user_has_application()): ?>
                    window.location.href = '<?php echo wc_get_account_endpoint_url('venus-documents'); ?>';
                    <?php else: ?>
                    window.location.href = '<?php echo home_url('/consent-form/'); ?>';
                    <?php endif; ?>
                    <?php endif; ?>
                });
            });
        </script>

        <style>
            /* 為 vip-member 按鈕添加一些樣式 */
            [title="vip-member"], .vip-member, #vip-member {
                cursor: pointer;
                transition: opacity 0.3s ease;
            }

            [title="vip-member"]:hover, .vip-member:hover, #vip-member:hover {
                opacity: 0.8;
            }
        </style>
        <?php
    }

    private function user_has_application()
    {
        if (!is_user_logged_in()) return false;

        $user_id = get_current_user_id();
        $db = new VenusDatabaseTables();
        $wpdb = $db->getConnection();
        $table_name = $db->getTableNameForQuery('member_applications');

        $result = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id)
        );

        return $result !== null;
    }

    private function get_user_application($user_id)
    {
        $db = new VenusDatabaseTables();
        $wpdb = $db->getConnection();
        $table_name = $db->getTableNameForQuery('member_applications');

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
            ARRAY_A
        );
    }

    private function display_consent_form($show_login_check = true)
    {
        // 確保依賴項已載入
        if (!class_exists('VenusDatabaseTables')) {
            echo '<p>Venus Member System dependencies not loaded.</p>';
            return;
        }

        $user_id = get_current_user_id();
        $application = null;

        if ($user_id) {
            try {
                $application = $this->get_user_application($user_id);
            } catch (Exception $e) {
                error_log('Venus Member System: Error getting user application - ' . $e->getMessage());
                $application = null;
            }
        }

        // 傳遞登入檢查參數到模板
        $login_check_enabled = $show_login_check;
    }

    private function display_document_upload()
    {
        $user_id = get_current_user_id();
        $application = $this->get_user_application($user_id);

        if (!$application) {
            wp_redirect(home_url('/consent-form/'));
            exit;
        }

        include VENUS_MEMBER_PLUGIN_DIR . 'document-upload.php';
    }

    public function handle_consent_submission()
    {
        check_ajax_referer('venus_member_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please login first');
        }

        $api = new Venus_Member_API();
        $result = $api->submit_consent($_POST);

        wp_send_json($result);
    }

    public function handle_sign_document()
    {
        // 檢查用戶是否登入
        if (!is_user_logged_in()) {
            wp_send_json_error('Please login first');
        }

        // 檢查 nonce (暫時放寬檢查用於測試)
        if (isset($_POST['nonce']) && $_POST['nonce'] !== 'bypass_nonce_for_testing' && !wp_verify_nonce($_POST['nonce'], 'venus_sign_document')) {
            wp_send_json_error('Security check failed');
        }

        $user_id = get_current_user_id();

        try {
            $db = new VenusDatabaseTables();
            $wpdb = $db->getConnection();

            // 檢查是否已有申請記錄
            $applications_table = $db->getTableNameForQuery('member_applications');
            $existing_application = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $applications_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
                ARRAY_A
            );

            if ($existing_application) {
                // 更新現有申請狀態
                $wpdb->update(
                    $applications_table,
                    array(
                        'application_status' => 'document_signed',
                        'consent_agreed_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $existing_application['id']),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                $application_id = $existing_application['id'];
            } else {
                // 創建新的申請記錄
                $application_number = 'VMS' . date('Ymd') . str_pad($user_id, 4, '0', STR_PAD_LEFT) . rand(100, 999);

                $wpdb->insert(
                    $applications_table,
                    array(
                        'user_id' => $user_id,
                        'application_number' => $application_number,
                        'application_status' => 'document_signed',
                        'consent_agreed_at' => current_time('mysql'),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s')
                );
                $application_id = $wpdb->insert_id;
            }
            // 處理推薦碼邏輯
            $referral_code = sanitize_text_field($_POST['referral_code'] ?? '');

            // 取得用戶 email
            $user_info = get_userdata($user_id);
            $user_email = $user_info ? $user_info->user_email : '';

            // 如果有提供推薦碼，進行驗證和綁定
            if (!empty($referral_code)) {
                $commission_table = $db->getCommissionTableNameForQuery('commission_coupons');
                $coupon = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $commission_table WHERE coupon_code = %s AND status = %s",
                        $referral_code,
                        'active'
                    ),
                    ARRAY_A
                );

                if (!$coupon) {
                    // 推薦碼無效或不存在，返回錯誤
                    wp_send_json_error('無效的推薦碼');
                    return; // 明確返回，雖然 wp_send_json_error 會終止執行
                }

                // 推薦碼有效，檢查是否已經綁定
                $downlines_table = $db->getCommissionTableNameForQuery('commission_downlines');
                $downline = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $downlines_table WHERE downline_email = %s AND holder_email = %s",
                        $user_email,
                        $coupon['holder_email']
                    ),
                    ARRAY_A
                );

                if (!$downline) {
                    // 建立推薦關係
                    $insert_result = $wpdb->insert(
                        $downlines_table,
                        array(
                            'holder_email' => $coupon['holder_email'],
                            'downline_email' => $user_email,
                            'created_at' => current_time('mysql')
                        ),
                        array('%s', '%s', '%s')
                    );

                    if (!$insert_result) {
                        error_log('Venus Member System: Failed to create referral relationship - ' . $wpdb->last_error);
                    } else {
                        error_log('Venus Member System: Referral relationship created successfully');
                    }
                }
            }
            // 記錄同意書簽署
            $consent_table = $db->getTableNameForQuery('consent_records');
            $wpdb->insert(
                $consent_table,
                array(
                    'application_id' => $application_id,
                    'user_id' => $user_id,
                    'consent_type' => 'member_benefits_agreement',
                    'consent_version' => '1.0',
                    'agreed_at' => current_time('mysql'),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s')
            );

            // 暫時跳過點點簽和金流，直接進入文件上傳階段
            wp_send_json_success(array(
//                'message' => 'Document signed successfully! Redirecting to document upload...',
                'redirect_url' => wc_get_account_endpoint_url('payment')
            ));

        } catch (Exception $e) {
            error_log('Venus Sign Document Error: ' . $e->getMessage());
            wp_send_json_error('An error occurred while processing your request');
        }
    }

    public function handle_document_upload()
    {
        check_ajax_referer('venus_member_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please login first');
        }

        $api = new Venus_Member_API();
        $result = $api->upload_document($_FILES, $_POST);

        wp_send_json($result);
    }

    public function handle_document_delete()
    {
        check_ajax_referer('venus_member_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please login first');
        }

        $api = new Venus_Member_API();
        $result = $api->delete_document($_POST);

        wp_send_json($result);
    }

    public function handle_save_applicant_info()
    {
        check_ajax_referer('venus_member_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('Please login first');
        }

        $user_id = get_current_user_id();

        try {
            $db = new VenusDatabaseTables();
            $wpdb = $db->getConnection();
            $applications_table = $db->getTableNameForQuery('member_applications');

            // Get user application
            $application = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $applications_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
                ARRAY_A
            );

            if (!$application) {
                wp_send_json_error('No application found');
            }

            $applicant_type = sanitize_text_field($_POST['applicant_type'] ?? '');

            if (!in_array($applicant_type, array('individual', 'company'))) {
                wp_send_json_error('Invalid applicant type');
            }

            $update_data = array(
                'applicant_type' => $applicant_type,
                'updated_at' => current_time('mysql')
            );

            if ($applicant_type === 'company') {
                // Company information
                $update_data['company_name'] = sanitize_text_field($_POST['company_name'] ?? '');
                $update_data['company_tax_id'] = sanitize_text_field($_POST['company_tax_id'] ?? '');
                $update_data['company_establish_date'] = sanitize_text_field($_POST['company_establish_date'] ?? '');
                $update_data['company_address'] = sanitize_text_field($_POST['company_address'] ?? '');
                $update_data['contact_address'] = sanitize_text_field($_POST['contact_address'] ?? '');
                $update_data['principal_name'] = sanitize_text_field($_POST['principal_name'] ?? '');
                $update_data['contact_person_name'] = sanitize_text_field($_POST['contact_person_name'] ?? '');
                $update_data['company_phone'] = sanitize_text_field($_POST['company_phone'] ?? '');
                $update_data['mobile_phone'] = sanitize_text_field($_POST['mobile_phone'] ?? '');
            } else {
                // Individual information
                $update_data['individual_name'] = sanitize_text_field($_POST['individual_name'] ?? '');
                $update_data['gender'] = sanitize_text_field($_POST['gender'] ?? '');
                $update_data['id_number'] = sanitize_text_field($_POST['id_number'] ?? '');
                $update_data['birth_date'] = sanitize_text_field($_POST['birth_date'] ?? '');
                $update_data['mobile_phone'] = sanitize_text_field($_POST['mobile_phone'] ?? '');
                $update_data['contact_address'] = sanitize_text_field($_POST['contact_address'] ?? '');
            }

            $wpdb->update(
                $applications_table,
                $update_data,
                array('id' => $application['id']),
                array_fill(0, count($update_data), '%s'),
                array('%d')
            );

            wp_send_json_success(array(
                'message' => 'Information saved successfully',
                'applicant_type' => $applicant_type
            ));

        } catch (Exception $e) {
            error_log('Venus Save Applicant Info Error: ' . $e->getMessage());
            wp_send_json_error('An error occurred while saving information');
        }
    }

    public function handle_get_application_details()
    {
        check_ajax_referer('venus_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $application_id = intval($_POST['application_id'] ?? 0);

        if (!$application_id) {
            wp_send_json_error('Invalid application ID');
        }

        try {
            $db = new VenusDatabaseTables();
            $wpdb = $db->getConnection();

            // Get application details
            $applications_table = $db->getTableNameForQuery('member_applications');
            $documents_table = $db->getTableNameForQuery('application_documents');
            $consent_table = $db->getTableNameForQuery('consent_records');

            $application = $wpdb->get_row(
                $wpdb->prepare("
                    SELECT a.*, u.display_name, u.user_email, u.user_registered 
                    FROM $applications_table a 
                    LEFT JOIN {$wpdb->prefix}users u ON a.user_id = u.ID 
                    WHERE a.id = %d
                ", $application_id),
                ARRAY_A
            );

            if (!$application) {
                wp_send_json_error('Application not found');
            }

            // Get documents
            $documents = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $documents_table WHERE application_id = %d ORDER BY uploaded_at DESC", $application_id),
                ARRAY_A
            );

            // Get consent records
            $consent_records = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $consent_table WHERE application_id = %d ORDER BY agreed_at DESC", $application_id),
                ARRAY_A
            );

            // Generate HTML
            $html = $this->generate_application_details_html($application, $documents, $consent_records);

            wp_send_json_success(array('html' => $html));

        } catch (Exception $e) {
            wp_send_json_error('Database error: ' . $e->getMessage());
        }
    }

    public function handle_download_document()
    {
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // 檢查 nonce
        if (!wp_verify_nonce($_GET['nonce'], 'venus_download_document')) {
            wp_die('Security check failed');
        }

        $doc_id = intval($_GET['doc_id'] ?? 0);

        if (!$doc_id) {
            wp_die('Invalid document ID');
        }

        try {
            $db = new VenusDatabaseTables();
            $wpdb = $db->getConnection();

            // Get document details
            $documents_table = $db->getTableNameForQuery('application_documents');
            $document = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $documents_table WHERE id = %d", $doc_id),
                ARRAY_A
            );

            if (!$document) {
                wp_die('Document not found');
            }

            $file_path = $document['file_path'];

            // 檢查文件是否存在
            if (!file_exists($file_path)) {
                wp_die('File not found on server');
            }

            // 設置適當的 headers
            $file_type = $document['file_type'] ?? $document['mime_type'] ?? 'application/octet-stream';
            $original_filename = $document['original_filename'];

            header('Content-Type: ' . $file_type);
            header('Content-Disposition: inline; filename="' . $original_filename . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            // 輸出文件內容
            readfile($file_path);
            exit;

        } catch (Exception $e) {
            wp_die('Error downloading document: ' . $e->getMessage());
        }
    }

    /**
     * Get document type display name
     */
    private function get_document_type_name($type)
    {
        $document_types = array(
            // Individual document types
            'id_card_front' => '身分證正面',
            'id_card_back' => '身分證反面',
            'bank_passbook' => '銀行存摺封面',
            // Company document types
            'company_certificate' => '公司證明文件',
            'principal_id_front' => '負責人身分證正面',
            'principal_id_back' => '負責人身分證反面',
            'company_bank_passbook' => '公司戶收款存摺',
            'government_approval' => '市政府核准函'
        );

        return isset($document_types[$type]) ? $document_types[$type] : ucwords(str_replace('_', ' ', $type));
    }

    private function generate_application_details_html($application, $documents, $consent_records)
    {
        ob_start();

        // Determine applicant type display
        $applicant_type = $application['applicant_type'] ?? 'individual';
        $applicant_type_display = $applicant_type === 'company' ? '🏢 公司' : '👤 個人';
        ?>
        <div class="venus-app-details">
            <h3>申請詳情 - #<?php echo esc_html($application['id']); ?></h3>

            <div class="venus-details-section">
                <h4>申請人資訊</h4>
                <table class="form-table">
                    <tr>
                        <th>申請身份:</th>
                        <td><strong style="font-size: 1.1em;"><?php echo $applicant_type_display; ?></strong></td>
                    </tr>
                    <?php if ($applicant_type === 'company'): ?>
                        <!-- Company Information -->
                        <?php if (!empty($application['company_name'])): ?>
                            <tr>
                                <th>公司全名:</th>
                                <td><?php echo esc_html($application['company_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['company_tax_id'])): ?>
                            <tr>
                                <th>統一編號:</th>
                                <td><?php echo esc_html($application['company_tax_id']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['company_establish_date'])): ?>
                            <tr>
                                <th>公司設立日期:</th>
                                <td><?php echo esc_html($application['company_establish_date']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['company_address'])): ?>
                            <tr>
                                <th>公司地址:</th>
                                <td><?php echo esc_html($application['company_address']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['principal_name'])): ?>
                            <tr>
                                <th>負責人姓名:</th>
                                <td><?php echo esc_html($application['principal_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['contact_person_name'])): ?>
                            <tr>
                                <th>聯絡人姓名:</th>
                                <td><?php echo esc_html($application['contact_person_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['company_phone'])): ?>
                            <tr>
                                <th>公司電話:</th>
                                <td><?php echo esc_html($application['company_phone']); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Individual Information -->
                        <?php if (!empty($application['individual_name'])): ?>
                            <tr>
                                <th>姓名:</th>
                                <td><?php echo esc_html($application['individual_name']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['gender'])): ?>
                            <tr>
                                <th>性別:</th>
                                <td><?php echo esc_html($application['gender'] === 'male' ? '男' : ($application['gender'] === 'female' ? '女' : '其他')); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['id_number'])): ?>
                            <tr>
                                <th>身分證字號:</th>
                                <td><?php echo esc_html($application['id_number']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($application['birth_date'])): ?>
                            <tr>
                                <th>出生年月日:</th>
                                <td><?php echo esc_html($application['birth_date']); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($application['mobile_phone'])): ?>
                        <tr>
                            <th>手機號碼:</th>
                            <td><?php echo esc_html($application['mobile_phone']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($application['contact_address'])): ?>
                        <tr>
                            <th>聯絡地址:</th>
                            <td><?php echo esc_html($application['contact_address']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>User ID:</th>
                        <td><?php echo esc_html($application['user_id']); ?></td>
                    </tr>
                    <tr>
                        <th>Display Name:</th>
                        <td><?php echo esc_html($application['display_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><?php echo esc_html($application['user_email']); ?></td>
                    </tr>
                    <tr>
                        <th>User Registered:</th>
                        <td><?php echo esc_html($application['user_registered']); ?></td>
                    </tr>
                </table>
            </div>

            <div class="venus-details-section">
                <h4>Application Status</h4>
                <table class="form-table">
                    <tr>
                        <th>Status:</th>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($application['application_status']); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $application['application_status']))); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Created:</th>
                        <td><?php echo esc_html($application['created_at']); ?></td>
                    </tr>
                    <tr>
                        <th>Updated:</th>
                        <td><?php echo esc_html($application['updated_at']); ?></td>
                    </tr>
                    <?php if ($application['consent_agreed_at']): ?>
                        <tr>
                            <th>Consent Agreed:</th>
                            <td><?php echo esc_html($application['consent_agreed_at']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($application['documents_uploaded_at']): ?>
                        <tr>
                            <th>Documents Uploaded:</th>
                            <td><?php echo esc_html($application['documents_uploaded_at']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($application['reviewed_at']): ?>
                        <tr>
                            <th>Reviewed:</th>
                            <td><?php echo esc_html($application['reviewed_at']); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($application['rejection_reason']): ?>
                        <tr>
                            <th>Rejection Reason:</th>
                            <td><?php echo esc_html($application['rejection_reason']); ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="venus-details-section">
                <h4>Uploaded Documents (<?php echo count($documents); ?>)</h4>
                <?php if (!empty($documents)): ?>
                    <table class="widefat">
                        <thead>
                        <tr>
                            <th>Document Type</th>
                            <th>Original Filename</th>
                            <th>File Size</th>
                            <th>Upload Date</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($this->get_document_type_name($doc['document_type'])); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html($doc['original_filename']); ?>
                                    <br>
                                    <button type="button" class="button button-small preview-document"
                                            data-doc-id="<?php echo esc_attr($doc['id']); ?>"
                                            data-file-path="<?php echo esc_attr($doc['file_path']); ?>"
                                            data-file-type="<?php echo esc_attr($doc['mime_type']); ?>"
                                            data-original-name="<?php echo esc_attr($doc['original_filename']); ?>">
                                        預覽
                                    </button>
                                </td>
                                <td><?php echo esc_html(size_format($doc['file_size'])); ?></td>
                                <td><?php echo esc_html($doc['uploaded_at']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($doc['verification_status']); ?>"><?php echo esc_html(ucwords($doc['verification_status'])); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No documents uploaded yet.</p>
                <?php endif; ?>
            </div>

            <div class="venus-details-section">
                <h4>Consent Records (<?php echo count($consent_records); ?>)</h4>
                <?php if (!empty($consent_records)): ?>
                    <table class="widefat">
                        <thead>
                        <tr>
                            <th>Consent Type</th>
                            <th>Version</th>
                            <th>Agreed Date</th>
                            <th>IP Address</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($consent_records as $consent): ?>
                            <tr>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $consent['consent_type']))); ?></td>
                                <td><?php echo esc_html($consent['consent_version']); ?></td>
                                <td><?php echo esc_html($consent['agreed_at']); ?></td>
                                <td><?php echo esc_html($consent['user_ip']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No consent records found.</p>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .venus-details-section {
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .venus-details-section h4 {
                margin-top: 0;
                color: #23282d;
            }

            .status-badge {
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
            }

            .status-pending {
                background: #ffc107;
                color: #856404;
            }

            .status-approved {
                background: #28a745;
                color: white;
            }

            .status-rejected {
                background: #dc3545;
                color: white;
            }

            .status-under_review {
                background: #17a2b8;
                color: white;
            }

            .status-documents_pending {
                background: #fd7e14;
                color: white;
            }

            .status-documents_uploaded {
                background: #6f42c1;
                color: white;
            }

            .preview-document {
                margin-top: 5px;
            }
        </style>

        <!-- Document Preview Modal -->
        <div id="venus-document-preview-modal" class="venus-modal" style="display: none;">
            <div class="venus-modal-content" style="max-width: 90%; max-height: 90%;">
                <div class="venus-modal-header">
                    <h2 id="preview-modal-title">Document Preview</h2>
                    <span class="venus-modal-close">&times;</span>
                </div>
                <div class="venus-modal-body">
                    <div id="venus-document-preview-content"></div>
                </div>
            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                // Document preview functionality
                $(document).on('click', '.preview-document', function (e) {
                    e.preventDefault();

                    var docId = $(this).data('doc-id');
                    var filePath = $(this).data('file-path');
                    var fileType = $(this).data('file-type');
                    var originalName = $(this).data('original-name');

                    $('#preview-modal-title').text('預覽: ' + originalName);

                    var previewContent = $('#venus-document-preview-content');
                    previewContent.html('<p>載入中...</p>');

                    // Show modal
                    $('#venus-document-preview-modal').show();

                    // Generate preview based on file type - use secure download URL for all files
                    if (fileType && fileType.startsWith('image/')) {
                        // For images, use secure download URL
                        var imageUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=venus_download_document&doc_id=' + docId + '&nonce=<?php echo wp_create_nonce('venus_download_document'); ?>';
                        previewContent.html('<div style="text-align: center;"><img src="' + imageUrl + '" style="max-width: 100%; max-height: 70vh; border: 1px solid #ddd;" /></div>');
                    } else if (fileType === 'application/pdf') {
                        // For PDFs, use secure download URL
                        var pdfUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=venus_download_document&doc_id=' + docId + '&nonce=<?php echo wp_create_nonce('venus_download_document'); ?>';
                        previewContent.html('<iframe src="' + pdfUrl + '" style="width: 100%; height: 70vh; border: 1px solid #ddd;"></iframe>');
                    } else {
                        // For other files or unknown types, show download link
                        var downloadUrl = '<?php echo admin_url('admin-ajax.php'); ?>?action=venus_download_document&doc_id=' + docId + '&nonce=<?php echo wp_create_nonce('venus_download_document'); ?>';
                        var fileTypeText = fileType || '未知類型';
                        previewContent.html('<div style="text-align: center; padding: 40px;"><p>文件類型: ' + fileTypeText + '</p><p>此文件類型無法預覽</p><a href="' + downloadUrl + '" class="button button-primary" target="_blank">下載文件</a></div>');
                    }
                });

                // Close preview modal
                $(document).on('click', '.venus-modal-close', function () {
                    $('#venus-document-preview-modal').hide();
                });

                $(document).on('click', '#venus-document-preview-modal', function (e) {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });
            });
        </script>

        <?php
        return ob_get_clean();
    }

    /**
     * 檢查資料表是否存在
     */
    public function check_database_tables()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        try {
            $database = new VenusDatabaseTables();
            $tables_exist = $database->checkTablesExist();

            $missing_tables = array();
            foreach ($tables_exist as $table => $exists) {
                if (!$exists) {
                    $missing_tables[] = $table;
                }
            }

            if (!empty($missing_tables)) {
                set_transient('venus_member_missing_tables', $missing_tables, 300); // 5分鐘
            } else {
                delete_transient('venus_member_missing_tables');
            }

        } catch (Exception $e) {
            error_log('Venus Member System: Error checking database tables - ' . $e->getMessage());
        }
    }

    /**
     * 顯示資料表遺失的通知
     */
    public function database_missing_notice()
    {
        $missing_tables = get_transient('venus_member_missing_tables');

        if (!empty($missing_tables) && current_user_can('manage_options')) {
            $fix_url = plugins_url('fix-database.php', __FILE__);
            echo '<div class="error notice">';
            echo '<p><strong>Venus Member System:</strong> 有些資料表遺失，插件可能無法正常運作。</p>';
            echo '<p>遺失的表格： ' . implode(', ', $missing_tables) . '</p>';
            echo '<p><a href="' . esc_url($fix_url) . '" class="button button-primary" target="_blank">修復資料表</a> ';
            echo '<a href="' . admin_url('plugins.php') . '" class="button">重新啟用插件</a></p>';
            echo '</div>';
        }
    }

    public function add_consent_button_scripts()
    {
        // 檢查是否在 consent-form 頁面或包含按鈕的頁面
        global $post;
        if (!$post || (get_post_field('post_name', $post) !== 'consent-form' && strpos($post->post_content, 'venus-sign-document-btn') === false)) {
            return;
        }
        ?>
        <script>
            jQuery(document).ready(function ($) {
                $('#venus-sign-document-btn').on('click', function (e) {
                    e.preventDefault();

                    // 檢查是否登入
                    <?php if (!is_user_logged_in()): ?>
                    window.location.href = '<?php echo wp_login_url(home_url('/consent-form/')); ?>';
                    return;
                    <?php endif; ?>

                    var $btn = $(this);
                    var originalText = $btn.text();

                    // 獲取推薦碼
                    var referralCode = $('#referral-code-input').length ? $('#referral-code-input').val().trim() : '';

                    console.log('=== Venus AJAX Debug ===');
                    console.log('Referral Code:', referralCode);
                    console.log('AJAX URL:', '<?php echo admin_url('admin-ajax.php'); ?>');
                    console.log('Nonce:', '<?php echo wp_create_nonce('venus_sign_document'); ?>');

                    // 禁用按鈕
                    $btn.prop('disabled', true).text('處理中...');

                    var ajaxData = {
                        action: 'venus_sign_document',
                        nonce: '<?php echo wp_create_nonce('venus_sign_document'); ?>',
                        referral_code: referralCode
                    };

                    console.log('AJAX Data:', ajaxData);

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: ajaxData,
                        success: function (response) {
                            console.log('AJAX Success Response:', response);

                            if (response.success) {
                                // 檢查推薦碼綁定結果
                                if (response.data.referral) {
                                    if (response.data.referral.success) {
                                        showMessage('✓ ' + response.data.referral.message, 'success');
                                    } else {
                                        showMessage('⚠ ' + response.data.referral.message, 'warning');
                                    }

                                    setTimeout(function () {
                                        window.location.href = response.data.redirect_url;
                                    }, 2000);
                                } else {
                                    window.location.href = response.data.redirect_url;
                                }
                            } else {
                                console.error('Response Error:', response.data);
                                alert('錯誤: ' + response.data);
                                $btn.prop('disabled', false).text(originalText);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('=== AJAX Error ===');
                            console.error('Status:', status);
                            console.error('Error:', error);
                            console.error('Response Status:', xhr.status);
                            console.error('Response Text:', xhr.responseText);
                            console.error('Full XHR:', xhr);

                            alert('發生錯誤 (' + xhr.status + '): ' + error + '\n請查看控制台了解詳情');
                            $btn.prop('disabled', false).text(originalText);
                        }
                    });
                });
            });
        </script>

        <style>
            .venus-sign-document-section {
                margin: 30px 0;
                padding: 20px;
                border: 2px solid #0073aa;
                border-radius: 5px;
                background: #f8f9fa;
                text-align: center;
            }

            #venus-sign-document-btn {
                background: #0073aa;
                color: white;
                padding: 15px 30px;
                font-size: 16px;
                font-weight: bold;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                transition: background 0.3s;
            }

            #venus-sign-document-btn:hover {
                background: #005a87;
            }

            #venus-sign-document-btn:disabled {
                background: #ccc;
                cursor: not-allowed;
            }

            .venus-login-notice {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
        </style>
        <?php
    }
}

add_action('plugins_loaded', 'venus_member_check_woocommerce');

function venus_member_check_woocommerce()
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'venus_member_woocommerce_missing_notice');
        return;
    }

    Venus_Member_System::get_instance();
}

function venus_member_woocommerce_missing_notice()
{
    echo '<div class="error"><p><strong>Venus Member System</strong> requires WooCommerce plugin to work.</p></div>';
}