<div class="container">
    <!-- 页面头部 - 参考 admin-page-header 风格 -->
    <div class="admin-page-header" style="margin-bottom: 1.5rem;">
        <div class="admin-page-header-left">
            <div class="admin-page-header-icon">
                <i class="bi bi-gear-fill"></i>
            </div>
            <h1 class="admin-page-header-title">
                <div class="admin-page-header-title-main">
                    系统初始化<span class="en-text">System Initialization</span>
                </div>
                <div class="admin-page-header-title-right">
                    <span class="en-text">Create First Administrator</span>
                </div>
            </h1>
        </div>
    </div>
    
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 bilingual-inline">
                        <span class="cn-text"><i class="bi bi-person-plus-fill me-2"></i>
                        创建管理员账户</span><span class="en-text">Create Administrator Account</span>
                    </h5>
                </div>
                <div class="card-body">
                    <form id="init_user_form" class="form-init-user" method="post" action="__OJTOOL__/userinit/init_user_ajax">
                        <div class="mb-3">
                            <label class="form-label bilingual-inline">
                                用户名<span class="en-text">User ID</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   placeholder="5-20个字符 5-20 characters" 
                                   name="user_id" 
                                   id="user_id"
                                   required 
                                   autofocus>
                            <small class="form-text text-muted">5-20个字符，仅包含字母、数字、下划线<span class="en-text">5-20 characters, letters, numbers and underscores only</span></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label bilingual-inline">
                                密码<span class="en-text">Password</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   id="init_password" 
                                   class="form-control" 
                                   placeholder="至少6个字符 At least 6 characters" 
                                   name="password" 
                                   required>
                            <small class="form-text text-muted">至少6个字符，建议使用强密码<span class="en-text">At least 6 characters, strong password recommended</span></small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label bilingual-inline">
                                确认密码<span class="en-text">Confirm Password</span>
                                <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   placeholder="再次输入密码 Enter password again" 
                                   name="confirm_password" 
                                   id="confirm_password"
                                   required>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button class="btn btn-primary btn-lg bilingual-button bilingual-inline" id="submit_button" type="submit">
                                <span class="cn-text"><i class="bi bi-check-circle me-2"></i>
                                初始化系统</span><span class="en-text">Initialize System</span>
                            </button>
                        </div>
                        
                        <div class="alert alert-warning mt-4" role="alert">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                                <div>
                                    <strong class="bilingual-inline">注意：<span class="en-text">Warning:</span></strong>
                                    <div class="mt-2">
                                        此操作将创建系统首个管理员账户，并授予超级管理员权限。请妥善保管账户信息。
                                        <span class="en-text">This operation will create the first administrator account with super_admin privileges. Please keep the account information safe.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style type="text/css">
    .form-init-user {
        padding: 0;
    }
    
    .form-init-user .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #495057;
    }
    
    .form-init-user .form-control {
        border-radius: 0.375rem;
        border: 1px solid #ced4da;
        padding: 0.75rem;
        transition: all 0.15s ease-in-out;
    }
    
    .form-init-user .form-control:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-init-user .form-text {
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    
    .card {
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        padding: 1rem 1.25rem;
    }
    
    .card-header.bg-primary {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%) !important;
    }
    
    .card-header .bilingual-inline .en-text {
        color: rgba(255, 255, 255, 0.9) !important;
        opacity: 1 !important;
    }
    
    .card-body {
        padding: 2rem;
    }
    
    .alert-warning {
        background-color: #fff3cd;
        border-color: #ffc107;
        color: #856404;
        border-left: 4px solid #ffc107;
    }
    
    .alert-warning .bilingual-inline .en-text {
        color: inherit;
        opacity: 0.85;
    }
    
    @media (max-width: 768px) {
        .admin-page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .admin-page-header-left {
            width: 100%;
        }
        
        .admin-page-header-title {
            font-size: 1.5rem;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-init-user .form-control {
            font-size: 0.9rem;
        }
    }
</style>

<script type="text/javascript">
$(document).ready(function(){
    // 初始化表单验证
    if (typeof FormValidationTip !== 'undefined') {
        FormValidationTip.initFormValidation('#init_user_form', {
            user_id: {
                rules: {
                    required: true,
                    minlength: 5,
                    maxlength: 20,
                    pattern: /^[a-zA-Z0-9_]+$/
                },
                messages: {
                    required: FormValidationTip.createBilingualMessage('用户名不能为空', 'User ID required'),
                    minlength: FormValidationTip.createBilingualMessage('用户名至少5个字符', 'User ID must be at least 5 characters'),
                    maxlength: FormValidationTip.createBilingualMessage('用户名不能超过20个字符', 'User ID cannot exceed 20 characters'),
                    pattern: FormValidationTip.createBilingualMessage('用户名只能包含字母、数字和下划线', 'User ID can only contain letters, numbers and underscores')
                }
            },
            password: {
                rules: {
                    required: true,
                    minlength: 6,
                    maxlength: 255
                },
                messages: {
                    required: FormValidationTip.createBilingualMessage('密码不能为空', 'Password required'),
                    minlength: FormValidationTip.createBilingualMessage('密码至少6个字符', 'Password must be at least 6 characters'),
                    maxlength: FormValidationTip.createBilingualMessage('密码不能超过255个字符', 'Password cannot exceed 255 characters')
                }
            },
            confirm_password: {
                rules: {
                    required: true,
                    minlength: 6,
                    maxlength: 255,
                    custom: function(value, element) {
                        // 自定义验证：密码确认
                        var password = $('#init_password').val();
                        return password === value;
                    }
                },
                messages: {
                    required: FormValidationTip.createBilingualMessage('请确认密码', 'Please confirm password'),
                    minlength: FormValidationTip.createBilingualMessage('密码至少6个字符', 'Password must be at least 6 characters'),
                    maxlength: FormValidationTip.createBilingualMessage('密码不能超过255个字符', 'Password cannot exceed 255 characters'),
                    custom: FormValidationTip.createBilingualMessage('两次输入的密码不一致', 'Password confirmation mismatch')
                }
            }
        }, function(form) {
            // 再次验证密码确认（双重检查）
            var password = $('#init_password').val();
            var confirmPassword = $('#confirm_password').val();
            if (password !== confirmPassword) {
                FormValidationTip.showFieldError($('#confirm_password')[0], FormValidationTip.createBilingualMessage('两次输入的密码不一致', 'Password confirmation mismatch'));
                $('#confirm_password').focus();
                return;
            }
            
            // 提交表单
            $('#submit_button').attr('disabled', true);
            
            // 使用原生表单提交，而不是 ajaxSubmit（因为需要表单提交）
            var formData = $(form).serialize();
            $.ajax({
                url: $(form).attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(ret) {
                    if(ret.code == 1) {
                        alerty.alert({
                            message: '<strong class="text-success">系统初始化成功！</strong>\n\n点击确定跳转评测机配置页\n\n<strong>重要提示：</strong>\n1. 在评测机配置页面中，点击「添加评测机」按钮创建评测机账号\n2. 记录下评测机的<strong>用户名</strong>和<strong>密码</strong>\n3. <strong>必须</strong>先设置好评测机账号后，才能启动评测机服务\n4. 评测机启动需要使用 csgoj_deploy.sh judge 脚本，并使用评测机账号的用户名和密码',
                            message_en: '<strong class="text-success">System initialized successfully!</strong>\n\nClick OK to redirect to judger configuration page\n\n<strong>Important:</strong>\n1. Click the "Add Judger" button on the judger configuration page to create a judger account\n2. Record the judger\'s <strong>username</strong> and <strong>password</strong>\n3. You <strong>must</strong> configure the judger account before starting the judger service\n4. To start the judger, use the csgoj_deploy.sh judge script with the judger account username and password',
                            width: 'lg',
                            callback: function() {
                                location.href = '/admin/judger/index';
                            }
                        });
                        // // 5秒后自动跳转（如果用户没有点击关闭按钮）
                        // setTimeout(function(){
                        //     location.href = '/admin/judger/index';
                        // }, 5000);
                     
                    } else {
                        $('#submit_button').removeAttr('disabled');
                        alerty.error(ret.msg);
                    }
                    return false;
                },
                error: function() {
                    $('#submit_button').removeAttr('disabled');
                    alerty.error('请求失败，请重试', 'Request failed, please try again');
                }
            });
        });
        
        // 添加密码字段变化时重新验证确认密码的逻辑
        $('#init_password').on('input blur', function() {
            var confirmPasswordField = $('#confirm_password')[0];
            if (confirmPasswordField && confirmPasswordField.value) {
                var password = $('#init_password').val();
                var confirmPassword = $('#confirm_password').val();
                if (password !== confirmPassword) {
                    FormValidationTip.showFieldError(confirmPasswordField, FormValidationTip.createBilingualMessage('两次输入的密码不一致', 'Password confirmation mismatch'));
                } else {
                    FormValidationTip.clearFieldError(confirmPasswordField);
                }
            }
        });
    }
});
</script>

