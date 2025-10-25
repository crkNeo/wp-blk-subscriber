<?php
/**
 * Venus Member API - Handle all API endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class Venus_Member_API {
    
    private $db;
    
    public function __construct() {
        $this->db = new VenusDatabaseTables();
    }
    
    /**
     * Submit consent form
     */
    public function submit_consent($data) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return array('success' => false, 'message' => 'Please login first');
        }
        
        try {
            $wpdb = $this->db->getConnection();
            $applications_table = $this->db->getTableNameForQuery('member_applications');
            $consent_table = $this->db->getTableNameForQuery('consent_records');
            
            // Check if user already has an application
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM $applications_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
                ARRAY_A
            );
            
            if (!$existing) {
                // Create new application
                $application_number = 'VMS' . date('Ymd') . sprintf('%06d', $user_id) . rand(100, 999);
                
                $wpdb->insert(
                    $applications_table,
                    array(
                        'user_id' => $user_id,
                        'application_number' => $application_number,
                        'application_status' => 'documents_pending',
                        'consent_agreed_at' => current_time('mysql'),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
                $application_id = $wpdb->insert_id;
            } else {
                // Update existing application
                $application_id = $existing['id'];
                $wpdb->update(
                    $applications_table,
                    array(
                        'application_status' => 'documents_pending',
                        'consent_agreed_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $application_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }
            
            // Save consent record
            $consent_content = $this->get_consent_content();
            $wpdb->insert(
                $consent_table,
                array(
                    'application_id' => $application_id,
                    'user_id' => $user_id,
                    'consent_type' => 'member_benefits',
                    'consent_version' => '1.0',
                    'consent_content' => $consent_content,
                    'agreed_at' => current_time('mysql'),
                    'ip_address' => $this->get_client_ip(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            return array(
                'success' => true,
                'message' => 'Consent submitted successfully',
                'redirect_url' => wc_get_account_endpoint_url('venus-documents')
            );
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Database error: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload document
     */
    public function upload_document($files, $post_data) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return array('success' => false, 'message' => 'Please login first');
        }
        
        // Get user application
        $wpdb = $this->db->getConnection();
        $applications_table = $this->db->getTableNameForQuery('member_applications');
        $application = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $applications_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
            ARRAY_A
        );
        
        if (!$application) {
            return array('success' => false, 'message' => 'No application found');
        }
        
        $document_type = sanitize_text_field($post_data['document_type'] ?? '');

        // Allowed document types for both individual and company
        $allowed_document_types = array(
            // Individual types
            'id_card_front',
            'id_card_back',
            'bank_passbook',
            // Company types
            'company_certificate',
            'principal_id_front',
            'principal_id_back',
            'company_bank_passbook',
            'government_approval'
        );

        if (!in_array($document_type, $allowed_document_types)) {
            return array('success' => false, 'message' => 'Invalid document type');
        }
        
        if (!isset($files['document_file']) || $files['document_file']['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'File upload failed');
        }
        
        $file = $files['document_file'];
        
        // Validate file
        $validation = $this->validate_file($file);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['message']);
        }
        
        // Save file
        $save_result = $this->save_file($file, $application['id'], $document_type);
        
        if ($save_result['success']) {
            // Check if all required documents are uploaded
            $this->check_documents_completion($application['id']);
            
            return array(
                'success' => true,
                'message' => 'Document uploaded successfully',
                'file_id' => $save_result['file_id']
            );
        }
        
        return array('success' => false, 'message' => 'Failed to save file');
    }
    
    /**
     * Delete document
     */
    public function delete_document($data) {
        $user_id = get_current_user_id();
        $document_id = intval($data['document_id'] ?? 0);
        
        if (!$user_id || !$document_id) {
            return array('success' => false, 'message' => 'Invalid parameters');
        }
        
        try {
            $wpdb = $this->db->getConnection();
            $applications_table = $this->db->getTableNameForQuery('member_applications');
            $documents_table = $this->db->getTableNameForQuery('application_documents');
            
            // Get user application
            $application = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM $applications_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
                ARRAY_A
            );
            
            if (!$application) {
                return array('success' => false, 'message' => 'No application found');
            }
            
            // Get document
            $document = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $documents_table WHERE id = %d AND application_id = %d", $document_id, $application['id']),
                ARRAY_A
            );
            
            if (!$document) {
                return array('success' => false, 'message' => 'Document not found');
            }
            
            // Delete physical file
            if (file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }
            
            // Delete database record
            $wpdb->delete(
                $documents_table,
                array('id' => $document_id),
                array('%d')
            );
            
            return array('success' => true, 'message' => 'Document deleted successfully');
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Database error: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        $allowed_types = array('image/jpeg', 'image/png', 'image/jpg', 'application/pdf');
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            return array('valid' => false, 'message' => 'Only JPG, PNG or PDF files are allowed');
        }
        
        if ($file['size'] > $max_size) {
            return array('valid' => false, 'message' => 'File size cannot exceed 5MB');
        }
        
        // Check actual file content
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return array('valid' => false, 'message' => 'Invalid file format');
        }
        
        return array('valid' => true);
    }
    
    /**
     * Save uploaded file
     */
    private function save_file($file, $application_id, $document_type) {
        $upload_dir = wp_upload_dir();
        $venus_dir = $upload_dir['basedir'] . '/venus-member-documents/';
        
        if (!file_exists($venus_dir)) {
            wp_mkdir_p($venus_dir);
            file_put_contents($venus_dir . '.htaccess', "Order deny,allow\nDeny from all\n");
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored_filename = $application_id . '_' . $document_type . '_' . time() . '.' . $file_extension;
        $file_path = $venus_dir . $stored_filename;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            try {
                $wpdb = $this->db->getConnection();
                $documents_table = $this->db->getTableNameForQuery('application_documents');
                
                $wpdb->insert(
                    $documents_table,
                    array(
                        'application_id' => $application_id,
                        'document_type' => $document_type,
                        'original_filename' => sanitize_file_name($file['name']),
                        'stored_filename' => $stored_filename,
                        'file_path' => $file_path,
                        'file_size' => $file['size'],
                        'mime_type' => $file['type'],
                        'is_required' => in_array($document_type, array('id_card_front', 'id_card_back')) ? 1 : 0,
                        'uploaded_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s')
                );
                
                return array('success' => true, 'file_id' => $wpdb->insert_id);
                
            } catch (Exception $e) {
                unlink($file_path);
                return array('success' => false, 'message' => 'Database error: ' . $e->getMessage());
            }
        }
        
        return array('success' => false, 'message' => 'Failed to move uploaded file');
    }
    
    /**
     * Check if all required documents are uploaded
     */
    private function check_documents_completion($application_id) {
        try {
            $wpdb = $this->db->getConnection();
            $documents_table = $this->db->getTableNameForQuery('application_documents');
            $applications_table = $this->db->getTableNameForQuery('member_applications');

            // Get application info to check applicant type
            $application = $wpdb->get_row(
                $wpdb->prepare("SELECT applicant_type FROM $applications_table WHERE id = %d", $application_id),
                ARRAY_A
            );

            if (!$application) {
                return;
            }

            $documents = $wpdb->get_col(
                $wpdb->prepare("SELECT document_type FROM $documents_table WHERE application_id = %d", $application_id)
            );

            // Define required documents based on applicant type
            if ($application['applicant_type'] === 'company') {
                $required_types = array(
                    'company_certificate',
                    'principal_id_front',
                    'principal_id_back',
                    'company_bank_passbook',
                    'government_approval'
                );
            } else {
                // Individual
                $required_types = array(
                    'id_card_front',
                    'id_card_back',
                    'bank_passbook'
                );
            }

            $missing_required = array_diff($required_types, $documents);

            if (empty($missing_required)) {
                // All required documents uploaded - change to under_review
                $wpdb->update(
                    $applications_table,
                    array(
                        'application_status' => 'under_review',
                        'documents_uploaded_at' => current_time('mysql'),
                        'submitted_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $application_id),
                    array('%s', '%s', '%s', '%s'),
                    array('%d')
                );
            }

        } catch (Exception $e) {
            error_log('Venus Member: Error checking documents completion - ' . $e->getMessage());
        }
    }
    
    /**
     * Get consent content
     */
    private function get_consent_content() {
        return 'Venus Member Benefits Agreement - Version 1.0
        
Member Benefits:
- Exclusive member discounts: 10% off all products
- Priority booking service: Early access to new products
- Member-exclusive events: Participate in limited events and giveaways
- Birthday perks: Special offers during birthday month
- Points reward system: Earn points for purchases, redeemable for products

Member Obligations:
- Comply with website terms of use
- Provide accurate personal information
- Pay annual fees on time
- Cooperate with identity verification procedures

Fee Information:
Annual fee: NT$ 1,000
Payment methods: Credit card, ATM transfer, convenience store payment

Notes:
1. Membership is valid for one year from payment completion date
2. Member benefits are non-transferable
3. In case of disputes, the company reserves the right of final interpretation';
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
