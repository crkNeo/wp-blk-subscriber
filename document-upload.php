<?php
/**
 * Venus Member Document Upload Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$db = new VenusDatabaseTables();
$wpdb = $db->getConnection();

// Get user application
$applications_table = $db->getTableNameForQuery('member_applications');
$application = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $applications_table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
    ARRAY_A
);

if (!$application) {
    wp_redirect(home_url('/consent-form/'));
    exit;
}

// Get uploaded documents
$documents_table = $db->getTableNameForQuery('application_documents');
$documents = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $documents_table WHERE application_id = %d ORDER BY uploaded_at DESC", $application['id']),
    ARRAY_A
);

// Group documents by type
$document_types = array(
    'id_card_front' => array('name' => '身分證正面', 'required' => true, 'uploaded' => false),
    'id_card_back' => array('name' => '身分證背面', 'required' => true, 'uploaded' => false),
    'bank_passbook' => array('name' => '銀行存摺封面', 'required' => false, 'uploaded' => false)
);

foreach ($documents as $doc) {
    if (isset($document_types[$doc['document_type']])) {
        $document_types[$doc['document_type']]['uploaded'] = true;
        $document_types[$doc['document_type']]['document'] = $doc;
    }
}

$required_uploaded = $document_types['id_card_front']['uploaded'] && $document_types['id_card_back']['uploaded'];
?>

    <div class="venus-documents-container">
        <h2>身分認證檔案上傳</h2>
        
        <div class="application-info">
            <p><strong>用戶編號:</strong> <?php echo esc_html($application['application_number']); ?></p>
            <p><strong>狀態:</strong> <?php echo esc_html(ucfirst(str_replace('_', ' ', $application['application_status']))); ?></p>
        </div>
        
        <?php if ($application['application_status'] === 'documents_uploaded'): ?>
            <div class="documents-completed">
                <div class="success-message">
                    <h3>✅ 檔案上傳成功</h3>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="upload-instructions">
            <h3>證明需求</h3>
            <div class="requirements-list">
                <div class="requirement-item required">
                    <span class="requirement-icon">📄</span>
                    <div class="requirement-content">
                        <h4>身分證正面(必須)</h4>
                    </div>
                </div>
                
                <div class="requirement-item required">
                    <span class="requirement-icon">📄</span>
                    <div class="requirement-content">
                        <h4>身分證背面(必須)</h4>
                    </div>
                </div>
                
                <div class="requirement-item optional">
                    <span class="requirement-icon">🏦</span>
                    <div class="requirement-content">
                        <h4>銀行存摺封面(選填)</h4>
                    </div>
                </div>
            </div>
            
            <div class="upload-guidelines">
                <h4>Upload</h4>
                <ul>
                    <li>僅支援 JPG, PNG, PDF</li>
                    <li>檔案大小: 5MB 以內</li>
                </ul>
            </div>
        </div>
        
        <div class="documents-section">
            <?php foreach ($document_types as $type => $info): ?>
                <div class="document-upload-section">
                    <div class="document-header">
                        <h4>
                            <?php echo esc_html($info['name']); ?>
                            <?php if ($info['required']): ?>
                                <span class="required-badge">必須</span>
                            <?php else: ?>
                                <span class="optional-badge">選填</span>
                            <?php endif; ?>
                        </h4>
                        
                        <?php if ($info['uploaded']): ?>
                            <div class="upload-status uploaded">
                                <span class="status-icon">✅</span>
                                <span class="status-text">已上傳</span>
                            </div>
                        <?php else: ?>
                            <div class="upload-status pending">
                                <span class="status-icon">⏳</span>
                                <span class="status-text">上傳中</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($info['uploaded']): ?>
                        <!-- Show uploaded document -->
                        <div class="uploaded-document">
                            <div class="document-info">
                                <p><strong>檔案:</strong> <?php echo esc_html($info['document']['original_filename']); ?></p>
                                <p><strong>容量:</strong> <?php echo number_format($info['document']['file_size'] / 1024 / 1024, 2); ?> MB</p>
                                <p><strong>上傳:</strong> <?php echo date('Y-m-d H:i', strtotime($info['document']['uploaded_at'])); ?></p>
                            </div>
                            
                            <?php if ($application['application_status'] !== 'documents_uploaded'): ?>
                                <div class="document-actions">
                                    <button type="button" class="button venus-delete-document" data-document-id="<?php echo $info['document']['id']; ?>">
                                        刪除 & 重新上傳
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Show upload form -->
                        <form class="venus-document-upload-form" enctype="multipart/form-data">
                            <input type="hidden" name="document_type" value="<?php echo esc_attr($type); ?>">
                            
                            <div class="file-input-container">
                                <input type="file" name="document_file" class="venus-file-input" accept=".jpg,.jpeg,.png,.pdf" required>
                                <div class="venus-file-preview" style="display: none;"></div>
                            </div>
                            
                            <div class="upload-actions">
                                <button type="submit" class="button button-primary">上傳文件</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($required_uploaded && $application['application_status'] !== 'documents_uploaded'): ?>
            <div class="completion-notice">
                <div class="notice-content">
                    <h3>🎉 必需的身分證明皆已上傳</h3>
                </div>
            </div>
        <?php endif; ?>
    </div>

<style>
.venus-documents-container {
    margin: 0 auto;
    padding: 20px;
}

.application-info {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.application-info p {
    margin: 0.5rem 0;
    font-size: 1.1rem;
}

.documents-completed {
    margin-bottom: 2rem;
}

.success-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
}

.success-message h3 {
    color: #155724;
    margin-bottom: 1rem;
}

.upload-instructions {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.requirements-list {
    margin: 1.5rem 0;
}

.requirement-item {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding: 1rem;
    border-radius: 8px;
}

.requirement-item.required {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
}

.requirement-item.optional {
    background: #e2e3e5;
    border-left: 4px solid #6c757d;
}

.requirement-icon {
    font-size: 2rem;
    margin-right: 1rem;
}

.requirement-content h4 {
    margin: 0 0 0.5rem 0;
    color: #495057;
}

.requirement-content p {
    margin: 0;
    color: #6c757d;
}

.upload-guidelines {
    background: #e3f2fd;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1.5rem;
}

.upload-guidelines h4 {
    margin-top: 0;
    color: #1976d2;
}

.upload-guidelines ul {
    margin: 0;
    padding-left: 1.5rem;
}

.upload-guidelines li {
    margin-bottom: 0.5rem;
}

.document-upload-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.document-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.document-header h4 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.required-badge {
    background: #dc3545;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: normal;
}

.optional-badge {
    background: #6c757d;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: normal;
}

.upload-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.upload-status.uploaded {
    color: #28a745;
}

.upload-status.pending {
    color: #ffc107;
}

.uploaded-document {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

.document-info p {
    margin: 0.5rem 0;
}

.document-actions {
    margin-top: 1rem;
}

.file-input-container {
    margin-bottom: 1rem;
}

.venus-file-input {
    width: 100%;
    padding: 0.5rem;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    background: #f8f9fa;
}

.venus-file-preview {
    margin-top: 1rem;
    padding: 1rem;
    background: #e9ecef;
    border-radius: 8px;
}

.file-info {
    margin-bottom: 0.5rem;
}

.image-preview img {
    border-radius: 4px;
    border: 1px solid #dee2e6;
}

.upload-actions {
    text-align: center;
}

.completion-notice {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 2rem;
    margin: 2rem 0;
    text-align: center;
}

.notice-content h3 {
    color: #155724;
    margin-bottom: 1rem;
}

.navigation-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin: 2rem 0;
}

.help-section {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 2rem;
}

.help-section h4 {
    margin-top: 0;
    color: #495057;
}

.help-section ul {
    margin: 0;
    padding-left: 1.5rem;
}

.help-section li {
    margin-bottom: 0.5rem;
}

.button {
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    border: none;
    cursor: pointer;
    font-size: 1rem;
}

.button-primary {
    background: #e74c3c;
    color: white;
}

.button-primary:hover {
    background: #c0392b;
    color: white;
}

.button-secondary {
    background: #6c757d;
    color: white;
}

.button-secondary:hover {
    background: #5a6268;
    color: white;
}

@media (max-width: 768px) {
    .document-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .navigation-actions {
        flex-direction: column;
    }
    
    .requirement-item {
        flex-direction: column;
        text-align: center;
    }
    
    .requirement-icon {
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
}
</style>