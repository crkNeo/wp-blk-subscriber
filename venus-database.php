<?php
/**
 * Venus Member System - Database Tables Management
 * 
 * This class handles all database table creation and management
 * for the Venus Member System plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class VenusDatabaseTables {
    
    private $wpdb;
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->wpdb;
    }
    
    /**
     * Get full table name for queries
     */
    public function getTableNameForQuery($table_name) {
        return $this->wpdb->prefix . 'venus_' . $table_name;
    }
    
    /**
     * Create all tables
     */
    public function createAllTables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = array();
        
        // Create member applications table
        $results[] = $this->createMemberApplicationsTable();
        
        // Create application documents table
        $results[] = $this->createApplicationDocumentsTable();
        
        // Create consent records table
        $results[] = $this->createConsentRecordsTable();
        
        // Create payment records table
        $results[] = $this->createPaymentRecordsTable();
        
        // Create audit logs table
        $results[] = $this->createAuditLogsTable();
        
        return implode("\n", $results);
    }
    
    /**
     * Create member applications table
     */
    private function createMemberApplicationsTable() {
        $table_name = $this->getTableNameForQuery('member_applications');

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            application_number varchar(50) DEFAULT NULL,
            application_status varchar(50) NOT NULL DEFAULT 'pending',
            applicant_type varchar(20) NOT NULL DEFAULT 'individual',
            company_name varchar(200) DEFAULT NULL,
            company_tax_id varchar(50) DEFAULT NULL,
            company_establish_date date DEFAULT NULL,
            company_address text DEFAULT NULL,
            contact_address text DEFAULT NULL,
            principal_name varchar(100) DEFAULT NULL,
            contact_person_name varchar(100) DEFAULT NULL,
            company_phone varchar(50) DEFAULT NULL,
            individual_name varchar(100) DEFAULT NULL,
            gender varchar(10) DEFAULT NULL,
            id_number varchar(50) DEFAULT NULL,
            birth_date date DEFAULT NULL,
            mobile_phone varchar(50) DEFAULT NULL,
            consent_agreed tinyint(1) NOT NULL DEFAULT 0,
            consent_agreed_at datetime DEFAULT NULL,
            signature_completed tinyint(1) NOT NULL DEFAULT 0,
            signature_completed_at datetime DEFAULT NULL,
            payment_completed tinyint(1) NOT NULL DEFAULT 0,
            payment_completed_at datetime DEFAULT NULL,
            payment_amount decimal(10,2) DEFAULT NULL,
            documents_uploaded tinyint(1) NOT NULL DEFAULT 0,
            documents_uploaded_at datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
            rejection_reason text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY application_number (application_number),
            KEY user_id (user_id),
            KEY application_status (application_status),
            KEY applicant_type (applicant_type),
            KEY created_at (created_at)
        ) $this->charset_collate;";

        dbDelta($sql);
        return "✅ Member applications table created/updated: $table_name";
    }
    
    /**
     * Create application documents table
     */
    private function createApplicationDocumentsTable() {
        $table_name = $this->getTableNameForQuery('application_documents');
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            application_id mediumint(9) NOT NULL,
            document_type varchar(50) NOT NULL,
            original_filename varchar(255) NOT NULL,
            stored_filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            mime_type varchar(100) NOT NULL,
            upload_status varchar(50) NOT NULL DEFAULT 'uploaded',
            verification_status varchar(50) NOT NULL DEFAULT 'pending',
            is_required tinyint(1) NOT NULL DEFAULT 0,
            verified_at datetime DEFAULT NULL,
            verified_by bigint(20) UNSIGNED DEFAULT NULL,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_id (application_id),
            KEY document_type (document_type),
            KEY upload_status (upload_status),
            KEY verification_status (verification_status)
        ) $this->charset_collate;";
        
        dbDelta($sql);
        return "✅ Application documents table created/updated: $table_name";
    }
    
    /**
     * Create consent records table
     */
    private function createConsentRecordsTable() {
        $table_name = $this->getTableNameForQuery('consent_records');
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            application_id mediumint(9) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            consent_type varchar(50) NOT NULL DEFAULT 'member_agreement',
            consent_content longtext NOT NULL,
            consent_version varchar(20) NOT NULL DEFAULT '1.0',
            ip_address varchar(45) NOT NULL,
            user_agent text NOT NULL,
            agreed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_id (application_id),
            KEY user_id (user_id),
            KEY consent_type (consent_type),
            KEY agreed_at (agreed_at)
        ) $this->charset_collate;";
        
        dbDelta($sql);
        return "✅ Consent records table created/updated: $table_name";
    }
    
    /**
     * Create payment records table
     */
    private function createPaymentRecordsTable() {
        $table_name = $this->getTableNameForQuery('payment_records');
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            application_id mediumint(9) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            payment_method varchar(50) NOT NULL,
            payment_gateway varchar(50) NOT NULL,
            transaction_id varchar(100) DEFAULT NULL,
            payment_status varchar(50) NOT NULL DEFAULT 'pending',
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'TWD',
            gateway_response text DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_id (application_id),
            KEY user_id (user_id),
            KEY payment_status (payment_status),
            KEY transaction_id (transaction_id),
            KEY created_at (created_at)
        ) $this->charset_collate;";
        
        dbDelta($sql);
        return "✅ Payment records table created/updated: $table_name";
    }
    
    /**
     * Create audit logs table
     */
    private function createAuditLogsTable() {
        $table_name = $this->getTableNameForQuery('audit_logs');
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            application_id mediumint(9) DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            admin_user_id bigint(20) UNSIGNED DEFAULT NULL,
            action varchar(100) NOT NULL,
            action_type varchar(50) NOT NULL,
            old_values text DEFAULT NULL,
            new_values text DEFAULT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY application_id (application_id),
            KEY user_id (user_id),
            KEY admin_user_id (admin_user_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $this->charset_collate;";
        
        dbDelta($sql);
        return "✅ Audit logs table created/updated: $table_name";
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public function dropAllTables() {
        $tables = [
            'member_applications',
            'application_documents',
            'consent_records',
            'payment_records',
            'audit_logs'
        ];
        
        foreach ($tables as $table) {
            $table_name = $this->getTableNameForQuery($table);
            $this->wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        return "All Venus Member tables have been dropped.";
    }
    
    /**
     * Check if all tables exist
     */
    public function checkTablesExist() {
        $tables = [
            'member_applications',
            'application_documents',
            'consent_records',
            'payment_records',
            'audit_logs'
        ];
        
        $results = array();
        foreach ($tables as $table) {
            $table_name = $this->getTableNameForQuery($table);
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            $results[$table] = !empty($exists);
        }
        
        return $results;
    }
    
    /**
     * Get table statistics
     */
    public function getTableStats() {
        $stats = array();
        
        $applications_table = $this->getTableNameForQuery('member_applications');
        $documents_table = $this->getTableNameForQuery('application_documents');
        $consent_table = $this->getTableNameForQuery('consent_records');
        $payment_table = $this->getTableNameForQuery('payment_records');
        $audit_table = $this->getTableNameForQuery('audit_logs');
        
        // Check if tables exist first
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '$applications_table'");
        if (!$table_exists) {
            return array('error' => 'Tables do not exist. Please create them first.');
        }
        
        $stats['applications'] = array(
            'total' => $this->wpdb->get_var("SELECT COUNT(*) FROM $applications_table"),
            'pending' => $this->wpdb->get_var("SELECT COUNT(*) FROM $applications_table WHERE application_status = 'pending'"),
            'approved' => $this->wpdb->get_var("SELECT COUNT(*) FROM $applications_table WHERE application_status = 'approved'"),
            'rejected' => $this->wpdb->get_var("SELECT COUNT(*) FROM $applications_table WHERE application_status = 'rejected'")
        );
        
        $stats['documents'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $documents_table");
        $stats['consent_records'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $consent_table");
        $stats['payment_records'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $payment_table");
        $stats['audit_logs'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $audit_table");
        
        return $stats;
    }
}