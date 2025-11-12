<?php
$is_csgoj = ($OJ_MODE != 'cpcsys');
$modify_action = $is_csgoj ? '__OJ__/user/modify_ajax' : '__CPC__/user/modify_ajax';
?>
<h1 class="page-title bilingual-inline">修改信息<span class="en-text">Update User's Information</span></h1>

{if $OJ_SSO != false }
<div class="alert alert-info">
    <p class="bilingual-inline">在SSO主系统修改个人信息，<a href="{$OJ_SSO}" target="_blank">点击访问</a><span class="en-text">Modify your information in the<a href="{$OJ_SSO}" target="_blank"> main system</a></span></p>
</div>
{else /}
<div class="container-fluid px-3">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form id="modify_form" class="form-modify" method="post" action="{$modify_action}">
                        <div class="mb-3">
                            <label for="user_id" class="form-label bilingual-inline">用户ID<span class="en-text">User ID</span>：</label>
                            <input type="text" class="form-control" id="user_id" name="user_id" value="{$baseinfo['user_id']}" readonly>
                            <label for="user_id" class="notification_label"></label>
                        </div>
                        
                        {if $OJ_STATUS != 'exp'}
                        <div class="mb-3">
                            <label for="nick" class="form-label bilingual-inline">昵称<span class="en-text">Nick</span>：</label>
                            <input type="text" class="form-control" id="nick" name="nick" placeholder="最多30个字符 / No more than 30 characters" value="{$baseinfo['nick']}">
                            <label for="nick" class="notification_label"></label>
                        </div>
                        <div class="mb-3">
                            <label for="school" class="form-label bilingual-inline">学校<span class="en-text">School</span>：</label>
                            <input type="text" class="form-control" id="school" name="school" placeholder="学校 / School" value="{$baseinfo['school']}">
                            <label for="school" class="notification_label"></label>
                        </div>
                        {/if}
                        
                        <div class="mb-3">
                            <label for="email" class="form-label bilingual-inline">*邮箱<span class="en-text">E-Mail</span>：</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="邮箱 / E-Mail" value="{$baseinfo['email']}" required>
                            <label for="email" class="notification_label"></label>
                        </div>
                        
                        {if !$is_password_setter || $is_self }
                        <div class="mb-3">
                            <label for="modify_verify_password" class="form-label bilingual-inline">*验证密码<span class="en-text">Verify Password</span>：</label>
                            <input type="password" class="form-control" id="modify_verify_password" name="password" placeholder="至少6个字符 / At least 6 characters" required>
                            <label for="password" class="notification_label"></label>
                        </div>
                        {/if}
                        
                        <div class="mb-3">
                            <label for="modify_new_password" class="form-label bilingual-inline">新密码<span class="en-text">New Password</span>：</label>
                            <input type="password" class="form-control" id="modify_new_password" name="new_password" placeholder="留空或至少6个字符 / Let it blank or at least 6 characters">
                            <label for="new_password" class="notification_label"></label>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_new_password" class="form-label bilingual-inline">确认密码<span class="en-text">Confirm</span>：</label>
                            <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" placeholder="确认密码 / Confirm Password">
                            <label for="confirm_new_password" class="notification_label"></label>
                        </div>
                        
                        {if $is_csgoj && !$is_password_setter}
                        <div class="mb-3">
                            <label for="vcode" class="form-label bilingual-inline">*验证码<span class="en-text">V-Code</span>：</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" class="form-control" id="vcode" name="vcode" placeholder="验证码 / Verification Code" required>
                                <label id="vcode_img" class="mb-0" style="cursor: pointer;">{:captcha_img()}</label>
                            </div>
                            <label for="vcode" class="notification_label"></label>
                        </div>
                        {/if}
                        
                        <div class="d-grid gap-2 mt-4">
                            <button class="btn btn-primary bilingual-inline" id="submit_button" type="submit">
                                <span class="cn-text"><i class="bi bi-check-circle"></i> 提交</span><span class="en-text">Submit</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
// 配置变量
window.USER_MODIFY_CONFIG = {
    module: <?php echo json_encode(request()->module()); ?>,
    is_csgoj: <?php echo $is_csgoj ? 'true' : 'false'; ?>,
    has_vcode: <?php echo ($is_csgoj && !$is_password_setter) ? 'true' : 'false'; ?>,
    has_verify_password: <?php echo ($is_csgoj && (!$is_password_setter || $is_self)) ? 'true' : 'false'; ?>,
    modify_action: <?php echo json_encode($modify_action); ?>,
    userinfo_url: <?php echo json_encode($is_csgoj ? '__OJ__/user/userinfo' : '__CPC__/user/userinfo'); ?>
};

$(document).ready(function(){
    // 验证码刷新
    if (window.USER_MODIFY_CONFIG.has_vcode) {
        $('#vcode_img').on('click', function(){
            var ts = Date.parse(new Date())/1000;
            var img = this.getElementsByTagName('img')[0];
            if (img) {
                img.src = "/captcha?id=" + ts;
            }
        });
    }
    
    // 表单验证配置
    var fieldConfigs = {
        user_id: {
            rules: {
                required: true,
                minlength: 3,
                maxlength: 20
            }
        }
    };
    
    // csgoj模式的验证规则
    if (window.USER_MODIFY_CONFIG.is_csgoj) {
        fieldConfigs.email = {
            rules: {
                required: true,
                email: true,
                maxlength: 100
            }
        };
        
        if (window.USER_MODIFY_CONFIG.has_verify_password) {
            fieldConfigs.password = {
                rules: {
                    required: true,
                    minlength: 6,
                    maxlength: 64
                }
            };
        }
        
        if (window.USER_MODIFY_CONFIG.has_vcode) {
            fieldConfigs.vcode = {
                rules: {
                    required: true
                }
            };
        }
        
        // 仅在非exp模式下显示nick和school
        <?php if ($OJ_STATUS != 'exp'): ?>
        fieldConfigs.nick = {
            rules: {
                maxlength: 32
            }
        };
        fieldConfigs.school = {
            rules: {
                maxlength: 20
            }
        };
        <?php endif; ?>
    } else {
        // cpcsys模式
        fieldConfigs.nick = {
            rules: {
                maxlength: 32
            }
        };
        fieldConfigs.school = {
            rules: {
                maxlength: 20
            }
        };
    }
    
    // 新密码验证
    fieldConfigs.new_password = {
        rules: {
            minlength: 6,
            maxlength: 64
        }
    };
    
    fieldConfigs.confirm_new_password = {
        rules: {
            custom: [
                function(value, element) {
                    var newPassword = document.getElementById('modify_new_password').value;
                    if (!newPassword) return true; // 如果新密码为空，则不验证
                    return value === newPassword;
                }
            ]
        },
        messages: {
            custom: FormValidationTip.createBilingualMessage('两次输入的密码不一致', 'Password confirmation mismatch')
        }
    };
    
    // 初始化表单验证
    FormValidationTip.initFormValidation('#modify_form', fieldConfigs, function(form) {
        // 再次验证密码确认（双重检查）
        var newPassword = $('#modify_new_password').val();
        var confirmPassword = $('#confirm_new_password').val();
        
        if (newPassword && newPassword !== confirmPassword) {
            FormValidationTip.showFieldError(
                document.getElementById('confirm_new_password'),
                FormValidationTip.createBilingualMessage('两次输入的密码不一致', 'Password confirmation mismatch')
            );
            $('#confirm_new_password').focus();
            return;
        }
        
        // 禁用提交按钮
        var submitBtn = $('#submit_button');
        var submitTexts = window.Bilingual ? window.Bilingual.getBilingualText(submitBtn) : {chinese: '提交', english: 'Submit'};
        submitBtn.attr('disabled', true);
        submitBtn.html('<span class="cn-text"><i class="bi bi-arrow-clockwise"></i> 处理中...</span><span class="en-text">Processing...</span>');
        
        // 使用jQuery提交表单
        $(form).ajaxSubmit({
            success: function(ret) {
                if (ret["code"] == 1) {
                    alerty.alert({
                        message: ret['msg'] || '修改成功',
                        message_en: ret['msg'] || 'Successfully updated',
                        callback: function() {
                            var userId = ret['data'] && ret['data']['user_id'] ? ret['data']['user_id'] : $('#user_id').val();
                            var redirectUrl = window.USER_MODIFY_CONFIG.userinfo_url + '?user_id=' + userId;
                            location.href = redirectUrl;
                        }
                    });
                } else {
                    button_delay(submitBtn, 3, submitTexts.chinese, null, submitTexts.english);
                    
                    // 刷新验证码
                    if (window.USER_MODIFY_CONFIG.has_vcode) {
                        var ts = Date.parse(new Date())/1000;
                        var img = $('#vcode_img').find('img');
                        if (img.length > 0) {
                            img.attr('src', "/captcha?id=" + ts);
                        }
                    }
                    
                    alerty.alert({
                        message: ret['msg'] || '修改失败',
                        message_en: ret['msg'] || 'Update failed'
                    });
                }
                return false;
            },
            error: function() {
                button_delay(submitBtn, 3, submitTexts.chinese, null, submitTexts.english);
                alerty.error('网络错误<span class="en-text">Network error</span>');
            }
        });
        return false;
    });
});
</script>

<style type="text/css">
#modify_form {
    max-width: 100%;
}

#modify_form .form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

#modify_form .notification_label {
    display: none;
}

#modify_form input[readonly] {
    background-color: #e9ecef;
    cursor: not-allowed;
}

#vcode_img {
    height: 38px;
    display: flex;
    align-items: center;
}

#vcode_img img {
    height: 100%;
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
}
</style>
{/if}