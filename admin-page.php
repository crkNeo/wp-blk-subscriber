<?php
/**
 * Venus Member System - Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

$db = new VenusDatabaseTables();
$wpdb = $db->getConnection();

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$message = '';

// Debug information
if ($_POST) {
    error_log('Venus Admin POST data: ' . print_r($_POST, true));
}

if ($_POST && wp_verify_nonce($_POST['venus_admin_nonce'], 'venus_admin_action')) {
    switch ($action) {
        case 'approve':
            $application_id = intval($_POST['application_id']);
            if ($application_id <= 0) {
                $message = 'Error: Invalid application ID.';
                break;
            }
            
            $table_name = $db->getTableNameForQuery('member_applications');
            
            // Check if application exists
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $application_id),
                ARRAY_A
            );
            
            if (!$existing) {
                $message = 'Error: Application not found.';
                break;
            }
            
            $result = $wpdb->update(
                $table_name,
                array(
                    'application_status' => 'approved',
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id()
                ),
                array('id' => $application_id),
                array('%s', '%s', '%d'),
                array('%d')
            );
            
            if ($result !== false) {
                // Update user role to subscriber
                $user_id = $existing['user_id'];
                $user = get_user_by('ID', $user_id);
                
                if ($user) {
                    error_log("Venus Approve: Approving user ID: $user_id");
                    $old_roles = $user->roles;
                    error_log("Venus Approve: Old roles: " . implode(', ', $old_roles));

                    // Remove all existing roles and set as subscriber
                    $user->set_role('subscriber');
                    
                    // Verify the role change
                    $user = get_user_by('ID', $user_id); // Re-fetch user data
                    $new_roles = $user->roles;
                    error_log("Venus Approve: New roles: " . implode(', ', $new_roles));
                    
                    if (in_array('subscriber', $new_roles)) {
                        error_log("Venus Approve: User role successfully updated to subscriber.");
                        // Optionally add custom meta to indicate Venus membership
                        update_user_meta($user_id, 'venus_member_status', 'approved');
                        update_user_meta($user_id, 'venus_member_approved_at', current_time('mysql'));
                        
                        $message = 'Application #' . $application_id . ' approved successfully. User role updated to subscriber.';
                    } else {
                        error_log("Venus Approve: FAILED to update user role to subscriber.");
                        $message = 'Application #' . $application_id . ' approved, but FAILED to update user role to subscriber. Please check logs.';
                    }
                } else {
                    error_log("Venus Approve: User not found for user ID: $user_id");
                    $message = 'Application approved but failed to update user role (user not found).';
                }
            } else {
                $message = 'Error: Failed to update application status.';
            }
            break;
            
        case 'reject':
            $application_id = intval($_POST['application_id']);
            $rejection_reason = sanitize_textarea_field($_POST['rejection_reason']);
            $table_name = $db->getTableNameForQuery('member_applications');
            $wpdb->update(
                $table_name,
                array(
                    'application_status' => 'rejected',
                    'reviewed_at' => current_time('mysql'),
                    'reviewed_by' => get_current_user_id(),
                    'rejection_reason' => $rejection_reason
                ),
                array('id' => $application_id),
                array('%s', '%s', '%d', '%s'),
                array('%d')
            );
            $message = 'Application rejected.';
            break;
            
        case 'upload_consent_doc':
            if (isset($_FILES['consent_document']) && $_FILES['consent_document']['error'] === UPLOAD_ERR_OK) {
                $result = handle_consent_document_upload($_FILES['consent_document']);
                if ($result['success']) {
                    $message = 'Consent document uploaded successfully.';
                } else {
                    $message = 'Error: ' . $result['message'];
                }
            }
            break;
            
        case 'update_consent_content':
            $consent_content = wp_kses_post($_POST['consent_content']);
            update_option('venus_consent_form_content', $consent_content);
            $message = 'Consent form content updated successfully.';
            break;
    }
}

// Handle consent document upload
function handle_consent_document_upload($file) {
    $allowed_types = array('application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/pdf');
    $max_size = 10 * 1024 * 1024; // 10MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return array('success' => false, 'message' => 'Only DOC, DOCX, or PDF files are allowed');
    }
    
    if ($file['size'] > $max_size) {
        return array('success' => false, 'message' => 'File size cannot exceed 10MB');
    }
    
    $upload_dir = wp_upload_dir();
    $venus_dir = $upload_dir['basedir'] . '/venus-consent-documents/';
    
    if (!file_exists($venus_dir)) {
        wp_mkdir_p($venus_dir);
        file_put_contents($venus_dir . '.htaccess', "Order deny,allow\nDeny from all\n");
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $stored_filename = 'consent_form_' . time() . '.' . $file_extension;
    $file_path = $venus_dir . $stored_filename;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Delete old consent document
        $old_file = get_option('venus_consent_document_path');
        if ($old_file && file_exists($old_file)) {
            unlink($old_file);
        }
        
        // Save new file info
        update_option('venus_consent_document_path', $file_path);
        update_option('venus_consent_document_name', sanitize_file_name($file['name']));
        update_option('venus_consent_document_url', $upload_dir['baseurl'] . '/venus-consent-documents/' . $stored_filename);
        
        // Try to extract text content from document
        $content = extract_document_content($file_path, $file_extension);
        if ($content) {
            update_option('venus_consent_form_content', $content);
        }
        
        return array('success' => true);
    }
    
    return array('success' => false, 'message' => 'Failed to upload file');
}

// Extract content from document
function extract_document_content($file_path, $extension) {
    $content = '';
    
    try {
        switch (strtolower($extension)) {
            case 'pdf':
                // For PDF, we'll just show a message that it's uploaded
                $content = '<p><strong>PDF Document Uploaded:</strong> ' . basename($file_path) . '</p>';
                $content .= '<p>PDF content cannot be automatically extracted. Please manually update the content below if needed.</p>';
                break;
                
            case 'doc':
            case 'docx':
                // For Word documents, we'll show a similar message
                $content = '<p><strong>Word Document Uploaded:</strong> ' . basename($file_path) . '</p>';
                $content .= '<p>Word document content extraction requires additional libraries. Please manually update the content below.</p>';
                break;
        }
    } catch (Exception $e) {
        $content = '<p>Document uploaded but content could not be extracted automatically.</p>';
    }
    
    return $content;
}

// Get statistics
$stats = array();
$applications_table = $db->getTableNameForQuery('member_applications');

// Check if table exists before querying
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $applications_table));

if ($table_exists) {
    $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM $applications_table");
    $stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM $applications_table WHERE application_status IN ('documents_uploaded', 'under_review')");
    $stats['approved'] = $wpdb->get_var("SELECT COUNT(*) FROM $applications_table WHERE application_status = 'approved'");
    $stats['rejected'] = $wpdb->get_var("SELECT COUNT(*) FROM $applications_table WHERE application_status = 'rejected'");
} else {
    $stats['total'] = 0;
    $stats['pending'] = 0;
    $stats['approved'] = 0;
    $stats['rejected'] = 0;
    $message = 'Database tables are missing. Please <a href="' . plugins_url('quick-fix.php', __FILE__) . '" target="_blank">click here to fix</a>.';
}

// Get applications list
$page = intval($_GET['page_num'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = $_GET['status_filter'] ?? '';
$where_clause = '';

if ($status_filter) {
    $where_clause = $wpdb->prepare("WHERE application_status = %s", $status_filter);
}

$applications = array();
$total_applications = 0;
$total_pages = 0;

if ($table_exists) {
    $applications = $wpdb->get_results(
        "SELECT a.*, u.display_name, u.user_email 
         FROM $applications_table a 
         LEFT JOIN {$wpdb->prefix}users u ON a.user_id = u.ID 
         $where_clause 
         ORDER BY a.created_at DESC 
         LIMIT $per_page OFFSET $offset",
        ARRAY_A
    );
    
    // Get total count for pagination
    $total_applications = $wpdb->get_var("SELECT COUNT(*) FROM $applications_table a $where_clause");
    $total_pages = ceil($total_applications / $per_page);
}
?>

<div class="wrap">
    <h1>Venus Member System - Admin Dashboard</h1>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=venus-member-admin&tab=dashboard" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'dashboard') ? 'nav-tab-active' : ''; ?>">Dashboard</a>
    </h2>
    
    <?php if ($message): ?>
        <div class="notice <?php echo (strpos($message, 'missing') !== false) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    

<?php
?>
<div class="venus-admin-stats">
    <div class="stat-box">
        <h3>總計</h3>
        <div class="stat-number"><?php echo $stats['total']; ?></div>
    </div>
    <div class="stat-box pending">
        <h3>待審核</h3>
        <div class="stat-number"><?php echo $stats['pending']; ?></div>
    </div>
    <div class="stat-box approved">
        <h3>通過</h3>
        <div class="stat-number"><?php echo $stats['approved']; ?></div>
    </div>
    <div class="stat-box rejected">
        <h3>拒絕</h3>
        <div class="stat-number"><?php echo $stats['rejected']; ?></div>
    </div>
</div>

<!-- Filters -->
<div class="venus-admin-filters">
    <form method="get">
        <input type="hidden" name="page" value="venus-member-admin">
        <select name="status_filter">
            <option value="">所有狀態</option>
            <option value="draft" <?php selected($status_filter, 'draft'); ?>>草稿</option>
            <option value="consent_agreed" <?php selected($status_filter, 'consent_agreed'); ?>>已簽署</option>
            <option value="documents_uploaded" <?php selected($status_filter, 'documents_uploaded'); ?>>文件上傳</option>
            <option value="under_review" <?php selected($status_filter, 'under_review'); ?>>待審核</option>
            <option value="approved" <?php selected($status_filter, 'approved'); ?>>通過</option>
            <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>拒絕</option>
        </select>
        <input type="submit" class="button" value="Filter">
    </form>
</div>

<!-- Applications Table -->
<div class="venus-admin-table">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Application #</th>
                <th>使用者</th>
                <th>Email</th>
                <th>狀態</th>
                <th>時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <td><?php echo esc_html('#' . $app['id']); ?></td>
                    <td><?php echo esc_html($app['display_name'] ?: 'User #' . $app['user_id']); ?></td>
                    <td><?php echo esc_html($app['user_email']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($app['application_status']); ?>">
                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $app['application_status']))); ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($app['created_at'])); ?></td>
                    <td>
                        <a href="#" class="button button-small view-details" data-app-id="<?php echo $app['id']; ?>">View</a>

                        <?php if (in_array($app['application_status'], ['documents_uploaded', 'under_review'])): ?>
                            <a href="#" class="button button-primary button-small approve-app" data-app-id="<?php echo $app['id']; ?>">Approve</a>
                            <a href="#" class="button button-secondary button-small reject-app" data-app-id="<?php echo $app['id']; ?>">Reject</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem;">No applications found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="venus-admin-pagination">
        <?php
        $base_url = admin_url('admin.php?page=venus-member-admin');
        if ($status_filter) {
            $base_url .= '&status_filter=' . urlencode($status_filter);
        }

        for ($i = 1; $i <= $total_pages; $i++):
            $class = ($i == $page) ? 'button button-primary' : 'button';
            $url = $base_url . '&page_num=' . $i;
        ?>
            <a href="<?php echo esc_url($url); ?>" class="<?php echo $class; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
</div>

<!-- Modal for application details -->
<div id="venus-app-modal" class="venus-modal" style="display: none;">
    <div class="venus-modal-content">
        <div class="venus-modal-header">
            <h2>詳細資訊</h2>
            <span class="venus-modal-close">&times;</span>
        </div>
        <div class="venus-modal-body">
            <div id="venus-app-details"></div>
        </div>
    </div>
</div>

<!-- Modal for rejection -->
<div id="venus-reject-modal" class="venus-modal" style="display: none;">
    <div class="venus-modal-content">
        <div class="venus-modal-header">
            <h2>拒絕原因</h2>
            <span class="venus-modal-close">&times;</span>
        </div>
        <div class="venus-modal-body">
            <form id="venus-reject-form" method="post">
                <?php wp_nonce_field('venus_admin_action', 'venus_admin_nonce'); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="application_id" id="reject-app-id">
                
                <p><label for="rejection_reason">拒絕原因</label></p>
                <textarea name="rejection_reason" id="rejection_reason" rows="4" style="width: 100%;" required></textarea>
                
                <div class="venus-modal-actions">
                    <input type="submit" class="button button-primary" value="Reject Application">
                    <button type="button" class="button venus-modal-close">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.venus-admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 1.5rem;
    text-align: center;
}

.stat-box h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #333;
}

.stat-box.pending .stat-number { color: #f39c12; }
.stat-box.approved .stat-number { color: #27ae60; }
.stat-box.rejected .stat-number { color: #e74c3c; }

.venus-admin-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 1rem;
    margin: 1rem 0;
}

.venus-admin-table {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 1rem 0;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.status-draft { background: #e9ecef; color: #495057; }
.status-badge.status-consent_agreed { background: #cce5ff; color: #0066cc; }
.status-badge.status-documents_uploaded { background: #fff3cd; color: #856404; }
.status-badge.status-under_review { background: #fff3cd; color: #856404; }
.status-badge.status-approved { background: #d4edda; color: #155724; }
.status-badge.status-rejected { background: #f8d7da; color: #721c24; }

.venus-admin-pagination {
    text-align: center;
    margin: 2rem 0;
}

.venus-admin-pagination .button {
    margin: 0 0.25rem;
}

.venus-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.venus-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    width: 80%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}

.venus-modal-header {
    padding: 1rem;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.venus-modal-header h2 {
    margin: 0;
}

.venus-modal-close {
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.venus-modal-close:hover {
    color: #000;
}

.venus-modal-body {
    padding: 1rem;
}

.venus-modal-actions {
    margin-top: 1rem;
    text-align: right;
}

.venus-modal-actions .button {
    margin-left: 0.5rem;
}

/* Consent Form Management Styles */
.venus-consent-management {
    margin-top: 20px;
}

.venus-admin-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.venus-admin-section h4 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 1px solid #e1e1e1;
    padding-bottom: 10px;
}

.current-document {
    background: #f0f6fc;
    border: 1px solid #c3d9ff;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
}

.consent-preview {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.preview-content {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.6;
}

.preview-content h1, .preview-content h2, .preview-content h3, .preview-content h4 {
    color: #23282d;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}

.preview-content ul, .preview-content ol {
    padding-left: 20px;
}

.preview-content li {
    margin-bottom: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // View application details
    $('.view-details').on('click', function(e) {
        e.preventDefault();
        var appId = $(this).data('app-id');
        
        // Load application details via AJAX
        $.post(ajaxurl, {
            action: 'venus_get_application_details',
            application_id: appId,
            nonce: '<?php echo wp_create_nonce('venus_admin_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#venus-app-details').html(response.data.html);
                $('#venus-app-modal').show();
            } else {
                alert('Failed to load application details: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Network error occurred while loading application details');
        });
    });
    
    // Approve application
    $('.approve-app').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to approve this application?')) {
            var appId = $(this).data('app-id');
            
            // Create and submit form properly
            var form = $('<form>', {
                method: 'post',
                action: window.location.href
            });
            
            // Add nonce field
            form.append($('<input>', {
                type: 'hidden',
                name: 'venus_admin_nonce',
                value: '<?php echo wp_create_nonce('venus_admin_action'); ?>'
            }));
            
            // Add action field
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'approve'
            }));
            
            // Add application ID
            form.append($('<input>', {
                type: 'hidden',
                name: 'application_id',
                value: appId
            }));
            
            // Append to body and submit
            $('body').append(form);
            form.submit();
        }
    });
    
    // Reject application
    $('.reject-app').on('click', function(e) {
        e.preventDefault();
        var appId = $(this).data('app-id');
        $('#reject-app-id').val(appId);
        $('#venus-reject-modal').show();
    });
    
    // Close modals
    $('.venus-modal-close').on('click', function() {
        $('.venus-modal').hide();
    });
    
    // Close modal when clicking outside
    $('.venus-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>
