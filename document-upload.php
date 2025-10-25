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

// Check if applicant type is already set
$applicant_type = $application['applicant_type'] ?? 'individual';

// Define document types for company
$company_document_types = array(
    'company_certificate' => array('name' => '公司證明文件', 'required' => true, 'uploaded' => false),
    'principal_id_front' => array('name' => '負責人身分證正面', 'required' => true, 'uploaded' => false),
    'principal_id_back' => array('name' => '負責人身分證反面', 'required' => true, 'uploaded' => false),
    'company_bank_passbook' => array('name' => '公司戶收款存摺', 'required' => true, 'uploaded' => false),
    'government_approval' => array('name' => '市政府核准函', 'required' => true, 'uploaded' => false)
);

// Define document types for individual
$individual_document_types = array(
    'id_card_front' => array('name' => '身分證正面', 'required' => true, 'uploaded' => false),
    'id_card_back' => array('name' => '身分證反面', 'required' => true, 'uploaded' => false),
    'bank_passbook' => array('name' => '銀行存摺封面', 'required' => true, 'uploaded' => false)
);

// Choose document types based on applicant type
$document_types = $applicant_type === 'company' ? $company_document_types : $individual_document_types;

// Mark uploaded documents
foreach ($documents as $doc) {
    if (isset($document_types[$doc['document_type']])) {
        $document_types[$doc['document_type']]['uploaded'] = true;
        $document_types[$doc['document_type']]['document'] = $doc;
    }
}

// Check if all required documents are uploaded
$all_required_uploaded = true;
foreach ($document_types as $type => $info) {
    if ($info['required'] && !$info['uploaded']) {
        $all_required_uploaded = false;
        break;
    }
}
?>

<div class="venus-documents-container">
    <h2>身分認證資料上傳</h2>

    <div class="application-info">
        <p><strong>申請編號:</strong> <?php echo esc_html($application['application_number']); ?></p>
        <p><strong>狀態:</strong> <?php echo esc_html(ucfirst(str_replace('_', ' ', $application['application_status']))); ?></p>
    </div>

    <?php if ($application['application_status'] === 'under_review' || $application['application_status'] === 'approved'): ?>
        <div class="documents-completed">
            <div class="success-message">
                <h3>✅ 資料已提交完成</h3>
                <p>您的申請已進入審核階段，我們會盡快處理。</p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Applicant Type Selection -->
    <?php if (empty($application['applicant_type']) || $application['applicant_type'] === 'individual'): ?>
    <div class="applicant-type-section">
        <h3>選擇身份類型</h3>
        <div class="type-selector">
            <label class="type-option <?php echo $applicant_type === 'individual' ? 'active' : ''; ?>">
                <input type="radio" name="applicant_type" value="individual" <?php checked($applicant_type, 'individual'); ?>>
                <div class="type-card">
                    <span class="type-icon">👤</span>
                    <h4>個人</h4>
                    <p>個人申請會員</p>
                </div>
            </label>

            <label class="type-option <?php echo $applicant_type === 'company' ? 'active' : ''; ?>">
                <input type="radio" name="applicant_type" value="company" <?php checked($applicant_type, 'company'); ?>>
                <div class="type-card">
                    <span class="type-icon">🏢</span>
                    <h4>公司</h4>
                    <p>公司法人申請</p>
                </div>
            </label>
        </div>
    </div>
    <?php else: ?>
    <div class="applicant-type-display">
        <p><strong>申請身份:</strong>
            <?php echo $applicant_type === 'company' ? '🏢 公司' : '👤 個人'; ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- Company Information Form -->
    <div id="company-info-form" class="info-form-section" style="display: <?php echo $applicant_type === 'company' ? 'block' : 'none'; ?>;">
        <h3>公司資料</h3>
        <form id="venus-company-info-form" class="venus-info-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="company_name">公司全名 <span class="required">*</span></label>
                    <input type="text" id="company_name" name="company_name"
                           value="<?php echo esc_attr($application['company_name'] ?? ''); ?>"
                           required <?php echo !empty($application['company_name']) ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="company_tax_id">統一編號 <span class="required">*</span></label>
                    <input type="text" id="company_tax_id" name="company_tax_id"
                           value="<?php echo esc_attr($application['company_tax_id'] ?? ''); ?>"
                           maxlength="8" required <?php echo !empty($application['company_tax_id']) ? 'readonly' : ''; ?>>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="company_establish_date">公司設立日期 <span class="required">*</span></label>
                    <input type="date" id="company_establish_date" name="company_establish_date"
                           value="<?php echo esc_attr($application['company_establish_date'] ?? ''); ?>"
                           required <?php echo !empty($application['company_establish_date']) ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="company_phone">公司電話 <span class="required">*</span></label>
                    <input type="tel" id="company_phone" name="company_phone"
                           value="<?php echo esc_attr($application['company_phone'] ?? ''); ?>"
                           required <?php echo !empty($application['company_phone']) ? 'readonly' : ''; ?>>
                </div>
            </div>

            <div class="form-group">
                <label for="company_address">公司地址 <span class="required">*</span></label>
                <input type="text" id="company_address" name="company_address"
                       value="<?php echo esc_attr($application['company_address'] ?? ''); ?>"
                       required <?php echo !empty($application['company_address']) ? 'readonly' : ''; ?>>
            </div>

            <div class="form-group">
                <label for="contact_address">聯絡地址 <span class="required">*</span></label>
                <input type="text" id="contact_address" name="contact_address"
                       value="<?php echo esc_attr($application['contact_address'] ?? ''); ?>"
                       required <?php echo !empty($application['contact_address']) ? 'readonly' : ''; ?>>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="principal_name">負責人姓名 <span class="required">*</span></label>
                    <input type="text" id="principal_name" name="principal_name"
                           value="<?php echo esc_attr($application['principal_name'] ?? ''); ?>"
                           required <?php echo !empty($application['principal_name']) ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="contact_person_name">聯絡人姓名 <span class="required">*</span></label>
                    <input type="text" id="contact_person_name" name="contact_person_name"
                           value="<?php echo esc_attr($application['contact_person_name'] ?? ''); ?>"
                           required <?php echo !empty($application['contact_person_name']) ? 'readonly' : ''; ?>>
                </div>
            </div>

            <div class="form-group">
                <label for="mobile_phone_company">手機號碼 <span class="required">*</span></label>
                <input type="tel" id="mobile_phone_company" name="mobile_phone"
                       value="<?php echo esc_attr($application['mobile_phone'] ?? ''); ?>"
                       required <?php echo !empty($application['mobile_phone']) ? 'readonly' : ''; ?>>
            </div>

            <?php if (empty($application['company_name'])): ?>
            <div class="form-actions">
                <button type="submit" class="button button-primary">儲存公司資料</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Individual Information Form -->
    <div id="individual-info-form" class="info-form-section" style="display: <?php echo $applicant_type === 'individual' ? 'block' : 'none'; ?>;">
        <h3>個人資料</h3>
        <form id="venus-individual-info-form" class="venus-info-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="individual_name">姓名 <span class="required">*</span></label>
                    <input type="text" id="individual_name" name="individual_name"
                           value="<?php echo esc_attr($application['individual_name'] ?? ''); ?>"
                           required <?php echo !empty($application['individual_name']) ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="gender">性別 <span class="required">*</span></label>
                    <select id="gender" name="gender" required <?php echo !empty($application['gender']) ? 'disabled' : ''; ?>>
                        <option value="">請選擇</option>
                        <option value="male" <?php selected($application['gender'] ?? '', 'male'); ?>>男</option>
                        <option value="female" <?php selected($application['gender'] ?? '', 'female'); ?>>女</option>
                        <option value="other" <?php selected($application['gender'] ?? '', 'other'); ?>>其他</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="id_number">身分證字號 <span class="required">*</span></label>
                    <input type="text" id="id_number" name="id_number"
                           value="<?php echo esc_attr($application['id_number'] ?? ''); ?>"
                           maxlength="10" required <?php echo !empty($application['id_number']) ? 'readonly' : ''; ?>>
                </div>

                <div class="form-group">
                    <label for="birth_date">出生年月日 <span class="required">*</span></label>
                    <input type="date" id="birth_date" name="birth_date"
                           value="<?php echo esc_attr($application['birth_date'] ?? ''); ?>"
                           required <?php echo !empty($application['birth_date']) ? 'readonly' : ''; ?>>
                </div>
            </div>

            <div class="form-group">
                <label for="mobile_phone_individual">手機號碼 <span class="required">*</span></label>
                <input type="tel" id="mobile_phone_individual" name="mobile_phone"
                       value="<?php echo esc_attr($application['mobile_phone'] ?? ''); ?>"
                       required <?php echo !empty($application['mobile_phone']) ? 'readonly' : ''; ?>>
            </div>

            <div class="form-group">
                <label for="contact_address_individual">聯絡地址 <span class="required">*</span></label>
                <input type="text" id="contact_address_individual" name="contact_address"
                       value="<?php echo esc_attr($application['contact_address'] ?? ''); ?>"
                       required <?php echo !empty($application['contact_address']) ? 'readonly' : ''; ?>>
            </div>

            <?php if (empty($application['individual_name'])): ?>
            <div class="form-actions">
                <button type="submit" class="button button-primary">儲存個人資料</button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Document Upload Section -->
    <div class="upload-instructions">
        <h3>文件上傳要求</h3>
        <div class="upload-guidelines">
            <h4>上傳規範</h4>
            <ul>
                <li>僅支援 JPG, PNG, PDF 格式</li>
                <li>檔案大小: 5MB 以內</li>
                <li>請確保文件清晰可讀</li>
                <li>標示為 <span class="required-badge">必須</span> 的文件必須上傳</li>
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
                            <span class="status-text">待上傳</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($info['uploaded']): ?>
                    <!-- Show uploaded document -->
                    <div class="uploaded-document">
                        <div class="document-info">
                            <p><strong>檔案:</strong> <?php echo esc_html($info['document']['original_filename']); ?></p>
                            <p><strong>容量:</strong> <?php echo number_format($info['document']['file_size'] / 1024 / 1024, 2); ?> MB</p>
                            <p><strong>上傳時間:</strong> <?php echo date('Y-m-d H:i', strtotime($info['document']['uploaded_at'])); ?></p>
                        </div>

                        <?php if ($application['application_status'] !== 'under_review' && $application['application_status'] !== 'approved'): ?>
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

    <?php if ($all_required_uploaded && ($application['application_status'] === 'documents_pending' || $application['application_status'] === 'pending')): ?>
        <div class="completion-notice">
            <div class="notice-content">
                <h3>🎉 所有必需文件已上傳完成</h3>
                <p>您的申請已自動提交審核，我們會盡快處理。</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.venus-documents-container {
    margin: 0 auto;
    padding: 20px;
    max-width: 1200px;
}

.application-info {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border-left: 4px solid #007bff;
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

/* Applicant Type Selection */
.applicant-type-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.applicant-type-section h3 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    color: #333;
}

.type-selector {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.type-option {
    cursor: pointer;
    display: block;
}

.type-option input[type="radio"] {
    display: none;
}

.type-card {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    background: #fff;
}

.type-option:hover .type-card {
    border-color: #007bff;
    box-shadow: 0 4px 8px rgba(0,123,255,0.1);
}

.type-option.active .type-card,
.type-option input[type="radio"]:checked + .type-card {
    border-color: #007bff;
    background: #e7f3ff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.2);
}

.type-icon {
    font-size: 3rem;
    display: block;
    margin-bottom: 1rem;
}

.type-card h4 {
    margin: 0.5rem 0;
    color: #333;
}

.type-card p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.applicant-type-display {
    background: #e7f3ff;
    border: 1px solid #007bff;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 2rem;
}

.applicant-type-display p {
    margin: 0;
    font-size: 1.1rem;
    color: #0056b3;
}

/* Information Forms */
.info-form-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.info-form-section h3 {
    margin-top: 0;
    margin-bottom: 1.5rem;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 0.5rem;
}

.venus-info-form .form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.venus-info-form .form-group {
    margin-bottom: 1.5rem;
}

.venus-info-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
}

.venus-info-form input[type="text"],
.venus-info-form input[type="tel"],
.venus-info-form input[type="date"],
.venus-info-form select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.venus-info-form input:focus,
.venus-info-form select:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}

.venus-info-form input[readonly],
.venus-info-form select[disabled] {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.form-actions {
    text-align: center;
    margin-top: 2rem;
}

.required {
    color: #dc3545;
    font-weight: bold;
}

/* Upload Instructions */
.upload-instructions {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.upload-instructions h3 {
    margin-top: 0;
    color: #333;
}

.upload-guidelines {
    background: #e3f2fd;
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
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

/* Document Upload Sections */
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
    font-size: 0.75rem;
    font-weight: normal;
}

.optional-badge {
    background: #6c757d;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
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

.button {
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    transition: background 0.3s;
}

.button-primary {
    background: #007bff;
    color: white;
}

.button-primary:hover {
    background: #0056b3;
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

    .type-selector {
        grid-template-columns: 1fr;
    }

    .venus-info-form .form-row {
        grid-template-columns: 1fr;
    }
}
</style>
