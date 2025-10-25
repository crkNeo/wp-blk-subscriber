/**
 * Venus Member System - JavaScript
 */

jQuery(document).ready(function($) {
    
    // Applicant type selection handling
    $('input[name="applicant_type"]').on('change', function() {
        var selectedType = $(this).val();

        // Update active state
        $('.type-option').removeClass('active');
        $(this).closest('.type-option').addClass('active');

        // Show/hide forms
        if (selectedType === 'company') {
            $('#company-info-form').slideDown();
            $('#individual-info-form').slideUp();
        } else {
            $('#individual-info-form').slideDown();
            $('#company-info-form').slideUp();
        }
    });

    // Company info form submission
    $('#venus-company-info-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();

        // Get form data
        var formData = {
            action: 'venus_save_applicant_info',
            nonce: venus_ajax.nonce,
            applicant_type: 'company',
            company_name: $('#company_name').val(),
            company_tax_id: $('#company_tax_id').val(),
            company_establish_date: $('#company_establish_date').val(),
            company_phone: $('#company_phone').val(),
            company_address: $('#company_address').val(),
            contact_address: $('#contact_address').val(),
            principal_name: $('#principal_name').val(),
            contact_person_name: $('#contact_person_name').val(),
            mobile_phone: $('#mobile_phone_company').val()
        };

        $submitBtn.prop('disabled', true).text('儲存中...');

        $.ajax({
            url: venus_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('公司資料儲存成功！');
                    location.reload();
                } else {
                    alert(response.data || '儲存失敗，請重試');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('網路錯誤，請重試');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Individual info form submission
    $('#venus-individual-info-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var originalText = $submitBtn.text();

        // Get form data
        var formData = {
            action: 'venus_save_applicant_info',
            nonce: venus_ajax.nonce,
            applicant_type: 'individual',
            individual_name: $('#individual_name').val(),
            gender: $('#gender').val(),
            id_number: $('#id_number').val(),
            birth_date: $('#birth_date').val(),
            mobile_phone: $('#mobile_phone_individual').val(),
            contact_address: $('#contact_address_individual').val()
        };

        $submitBtn.prop('disabled', true).text('儲存中...');

        $.ajax({
            url: venus_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('個人資料儲存成功！');
                    location.reload();
                } else {
                    alert(response.data || '儲存失敗，請重試');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('網路錯誤，請重試');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Consent form handling
    $('#venus-consent-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!$('#agree-terms').is(':checked')) {
            alert(venus_ajax.messages.error || 'Please agree to the terms');
            return;
        }
        
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.text();
        
        $submitBtn.prop('disabled', true).text(venus_ajax.messages.loading || 'Processing...');
        
        $.ajax({
            url: venus_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'venus_submit_consent',
                nonce: venus_ajax.nonce,
                agreed: 1
            },
            success: function(response) {
                if (response.success) {
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.message || venus_ajax.messages.error);
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(venus_ajax.messages.error || 'Network error, please try again');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Document upload handling
    $('.venus-document-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $fileInput = $form.find('input[type="file"]');
        var $submitBtn = $form.find('button[type="submit"]');
        var documentType = $form.find('input[name="document_type"]').val();
        
        if (!$fileInput[0].files.length) {
            alert('Please select a file to upload');
            return;
        }
        
        var file = $fileInput[0].files[0];
        var maxSize = 5 * 1024 * 1024; // 5MB
        var allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        
        if (file.size > maxSize) {
            alert('File size cannot exceed 5MB');
            return;
        }
        
        if (!allowedTypes.includes(file.type)) {
            alert('Only JPG, PNG or PDF files are allowed');
            return;
        }
        
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text(venus_ajax.messages.loading || 'Uploading...');
        
        var formData = new FormData();
        formData.append('action', 'venus_upload_document');
        formData.append('nonce', venus_ajax.nonce);
        formData.append('document_type', documentType);
        formData.append('document_file', file);
        
        $.ajax({
            url: venus_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.message || venus_ajax.messages.success);
                    location.reload();
                } else {
                    alert(response.message || venus_ajax.messages.error);
                }
                $submitBtn.prop('disabled', false).text(originalText);
            },
            error: function() {
                alert(venus_ajax.messages.error || 'Upload failed, please try again');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Document delete handling
    $('.venus-delete-document').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this document?')) {
            return;
        }
        
        var $btn = $(this);
        var documentId = $btn.data('document-id');
        var originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Deleting...');
        
        $.ajax({
            url: venus_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'venus_delete_document',
                nonce: venus_ajax.nonce,
                document_id: documentId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message || venus_ajax.messages.success);
                    location.reload();
                } else {
                    alert(response.message || venus_ajax.messages.error);
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(venus_ajax.messages.error || 'Delete failed, please try again');
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // File input preview
    $('.venus-file-input').on('change', function() {
        var $input = $(this);
        var $preview = $input.siblings('.venus-file-preview');
        var file = this.files[0];
        
        if (file) {
            var fileName = file.name;
            var fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            
            $preview.html('<div class="file-info"><strong>Selected:</strong> ' + fileName + ' (' + fileSize + ')</div>').show();
            
            // Show image preview for image files
            if (file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $preview.append('<div class="image-preview"><img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; margin-top: 10px;" /></div>');
                };
                reader.readAsDataURL(file);
            }
        } else {
            $preview.hide();
        }
    });
    
    // Terms checkbox handling
    $('#agree-terms').on('change', function() {
        var $submitBtn = $('#venus-consent-submit');
        if (this.checked) {
            $submitBtn.prop('disabled', false);
        } else {
            $submitBtn.prop('disabled', true);
        }
    });
    
    // Progress indicator
    function showProgress(message) {
        if ($('#venus-progress').length === 0) {
            $('body').append('<div id="venus-progress" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 5px; z-index: 10000;">' + message + '</div>');
        }
    }
    
    function hideProgress() {
        $('#venus-progress').remove();
    }
    
    // Auto-hide alerts
    setTimeout(function() {
        $('.venus-alert').fadeOut();
    }, 5000);
    
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        var target = $(this.getAttribute('href'));
        if (target.length) {
            $('html, body').stop().animate({
                scrollTop: target.offset().top - 100
            }, 1000);
        }
    });
    
    // Form validation helpers
    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function validatePhone(phone) {
        var re = /^[\d\-\+\(\)\s]+$/;
        return re.test(phone) && phone.length >= 8;
    }
    
    // Real-time form validation
    $('.venus-form input[type="email"]').on('blur', function() {
        var $input = $(this);
        var email = $input.val();
        
        if (email && !validateEmail(email)) {
            $input.addClass('error');
            $input.siblings('.error-message').remove();
            $input.after('<div class="error-message" style="color: red; font-size: 12px; margin-top: 5px;">Please enter a valid email address</div>');
        } else {
            $input.removeClass('error');
            $input.siblings('.error-message').remove();
        }
    });
    
    $('.venus-form input[type="tel"]').on('blur', function() {
        var $input = $(this);
        var phone = $input.val();
        
        if (phone && !validatePhone(phone)) {
            $input.addClass('error');
            $input.siblings('.error-message').remove();
            $input.after('<div class="error-message" style="color: red; font-size: 12px; margin-top: 5px;">Please enter a valid phone number</div>');
        } else {
            $input.removeClass('error');
            $input.siblings('.error-message').remove();
        }
    });
    
    // Responsive table handling
    function makeTablesResponsive() {
        $('.venus-table').each(function() {
            var $table = $(this);
            if (!$table.parent().hasClass('table-responsive')) {
                $table.wrap('<div class="table-responsive" style="overflow-x: auto;"></div>');
            }
        });
    }
    
    makeTablesResponsive();
    
    // Initialize tooltips if available
    if (typeof $.fn.tooltip === 'function') {
        $('[data-toggle="tooltip"]').tooltip();
    }
    
});